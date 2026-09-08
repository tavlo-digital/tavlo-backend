<?php

namespace App\Services\Analytics;

use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Vendor;
use App\Services\TaxCalculationService;
use Illuminate\Support\Collection;

/**
 * Modifier/add-on/removable-item analytics — not just "how often was X
 * picked" but business recommendations: what to promote, what to reconsider,
 * where default composition might be wrong, where a paid upsell is missing,
 * and whether a paid add-on's price looks off relative to its own adoption.
 *
 * Tavlo has two parallel, coexisting customization systems, both covered
 * here (see class docs on MenuItem/CartItem/ModifierGroup for the schema):
 *  - System A ("addon"): free-text definitions on the menu item itself —
 *    `menu_items.paid_addons` / `free_addons` / `removable_items`, selected
 *    via `cart_items.paid_addons` / `free_addons` / `removed_items`.
 *  - System B ("modifier"): relational `modifier_groups` / `modifier_options`
 *    (reusable across menu items), selected via `cart_items.selected_modifiers`.
 *    A group's `type` of `remove` marks it as a removable-component choice,
 *    the System B analogue of System A's `removable_items`.
 *
 * Every catalog entry (one per distinct addon/option, scoped to the menu
 * item it belongs to — see linesFor()'s doc on why addons aren't unique
 * across items) carries `timesSelected` and `eligible`: `eligible` is how
 * many times the owning menu item itself was ordered in the period — the
 * number of times a guest COULD have picked this modifier — so every rate
 * below is a real adoption/removal rate, not a raw count that quietly favors
 * whatever item sells the most.
 *
 * Money is gross (VAT-inclusive), same convention as the rest of this
 * namespace. Revenue is attributed the same shared-line-safe way as
 * menu()/discounts() (divide by 1 + shared_order_ids count); selection
 * counts are deduped by cart-item id first, same as aggregateLines() —
 * see VendorAnalyticsService::linesFor()'s doc comment for why both of
 * those rules exist.
 *
 * Pricing verdicts (underpriced/overpriced/appropriate) are a heuristic
 * read of adoption-rate-vs-the-vendor's-own-baseline, not a controlled
 * price experiment — every response carries an explicit disclaimer, and
 * every list is gated behind MIN_SAMPLE so a single lucky/unlucky order
 * never produces a confident-sounding recommendation.
 */
class ModifierAnalyticsService
{
    /** Below this many opportunities-to-select, a rate is noise, not signal. */
    private const MIN_SAMPLE = 10;

    /** Pricing verdicts need a larger sample than a plain popularity read. */
    private const MIN_PRICING_SAMPLE = 15;

    private const PROMOTE_THRESHOLD_PERCENT = 40.0;

    private const RECONSIDER_THRESHOLD_PERCENT = 5.0;

    private const REMOVE_THRESHOLD_PERCENT = 2.0;

    private const SIGNIFICANT_REMOVAL_THRESHOLD_PERCENT = 20.0;

    private const OPPORTUNITY_BENCHMARK_PERCENT = 15.0;

    private const LIST_LIMIT = 12;

    private string $country;

    public function build(Vendor $vendor, Collection $lines, Collection $paidOrders): array
    {
        $this->country = $vendor->country ?: 'AT';

        $ordersAnalyzed = $paidOrders->count();

        if ($lines->isEmpty() || $ordersAnalyzed === 0) {
            return [
                'available' => false,
                'ordersAnalyzed' => $ordersAnalyzed,
                'minSampleSize' => self::MIN_SAMPLE,
            ];
        }

        $uniqueLines = $lines->unique('id');
        $itemsSeen = $this->itemsSeen($uniqueLines);

        if ($itemsSeen === []) {
            return [
                'available' => false,
                'ordersAnalyzed' => $ordersAnalyzed,
                'minSampleSize' => self::MIN_SAMPLE,
            ];
        }

        [$groups, $options] = $this->preloadModifierModels($uniqueLines);
        $activeGroupsByMenuItemId = $this->activeModifierGroupsByMenuItem($uniqueLines);

        $catalog = $this->seedConfiguredDefinitions($itemsSeen);
        $catalog = $this->seedActiveModifierOptions($catalog, $itemsSeen, $activeGroupsByMenuItemId);
        $catalog = $this->accumulateSelectionCounts($catalog, $uniqueLines, $itemsSeen, $groups, $options);
        $catalog = $this->accumulateRevenue($catalog, $lines, $itemsSeen);
        $catalog = $this->finalizeRates($catalog);

        $ordersById = $paidOrders->keyBy('id');
        $baselineAvgOrderValue = $ordersAnalyzed > 0
            ? round((float) $paidOrders->sum('amount') / $ordersAnalyzed, 2)
            : null;

        $addonRevenueTotal = round(collect($catalog)->where('kind', 'paid_addon')->sum('revenue'), 2);
        $totalPaidRevenue = round((float) $paidOrders->sum('amount'), 2);

        return [
            'available' => true,
            'ordersAnalyzed' => $ordersAnalyzed,
            'minSampleSize' => self::MIN_SAMPLE,
            'summary' => [
                'totalAddonModifierRevenue' => $addonRevenueTotal,
                'revenueSharePercent' => $totalPaidRevenue > 0
                    ? round(($addonRevenueTotal / $totalPaidRevenue) * 100, 1)
                    : null,
                'avgModifierRevenuePerOrder' => $ordersAnalyzed > 0
                    ? round($addonRevenueTotal / $ordersAnalyzed, 2)
                    : null,
                'configuredNeverSelectedCount' => collect($catalog)
                    ->where('kind', '!=', 'removable_item')
                    ->where('timesSelected', 0)
                    ->count(),
            ],
            'paidAddonsRevenue' => $this->topByRevenue($catalog),
            'freeAddonsImpact' => $this->freeAddonsImpact($catalog, $lines, $ordersById, $baselineAvgOrderValue),
            'mostPopular' => $this->mostPopular($catalog),
            'leastPopular' => $this->leastPopular($catalog),
            'promotionCandidates' => $this->promotionCandidates($catalog),
            'underperforming' => $this->underperforming($catalog),
            'removableItems' => $this->removableItems($catalog),
            'significantRemovals' => $this->significantRemovals($catalog),
            'newAddonOpportunities' => $this->newAddonOpportunities($catalog, $itemsSeen),
            'pricingAnalysis' => $this->pricingAnalysis($catalog),
            'note' => 'Popularity, promotion, and pricing read-outs below are derived from this period\'s order behavior — treat them as analytical signals to investigate, not definitive conclusions. A pricing verdict in particular reflects adoption relative to your own catalog, not a controlled price test.',
        ];
    }

    // ---------------------------------------------------------------- setup

    /**
     * One entry per distinct menu item that appeared in a paid order this
     * period: display name, category (for the opportunity heuristic), the
     * quantity ordered (the "eligible" denominator every rate below divides
     * into), and whether it has any System A paid add-ons configured at all.
     *
     * @return array<string, array{name: string, categoryName: ?string, hasPaidAddonsConfigured: bool, eligibleQty: int, menuItem: MenuItem}>
     */
    private function itemsSeen(Collection $uniqueLines): array
    {
        $out = [];

        foreach ($uniqueLines as $line) {
            $item = $line->menuItem;
            if (! $item) {
                continue;
            }

            $uid = $this->menuItemUid($item);
            $out[$uid] ??= [
                'name' => $item->name,
                'categoryName' => $item->category?->name,
                'hasPaidAddonsConfigured' => ! empty($item->paid_addons),
                'eligibleQty' => 0,
                'menuItem' => $item,
            ];
            $out[$uid]['eligibleQty'] += (int) $line->quantity;
        }

        return $out;
    }

    private function menuItemUid(MenuItem $item): string
    {
        return $item->product_uid ?? ('item-'.$item->id);
    }

    /** @return array{0: Collection<int, ModifierGroup>, 1: Collection<int, ModifierOption>} */
    private function preloadModifierModels(Collection $uniqueLines): array
    {
        $groupIds = collect();
        $optionIds = collect();

        foreach ($uniqueLines as $line) {
            foreach ($line->selected_modifiers ?? [] as $group) {
                $groupIds->push((int) ($group['modifier_group_id'] ?? 0));
                foreach ($group['options'] ?? [] as $option) {
                    $optionIds->push((int) ($option['id'] ?? 0));
                }
            }
        }

        // Chunked at 1,000 IDs per query, same convention as every
        // order/cart-item-volume whereIn() in VendorAnalyticsService/
        // FinancialReportService — catalog size bounds these today (a menu's
        // distinct modifier groups/options), not order volume, so this was
        // low real-world risk, but it's the one place in this feature that
        // quietly skipped the house rule adopted after that class of crash
        // (2026-09-07 audit finding).
        $groups = collect();
        foreach ($groupIds->filter()->unique()->chunk(1000) as $groupIdChunk) {
            $groups = $groups->concat(ModifierGroup::withTrashed()->whereIn('id', $groupIdChunk)->get());
        }
        $groups = $groups->keyBy('id');

        $options = collect();
        foreach ($optionIds->filter()->unique()->chunk(1000) as $optionIdChunk) {
            $options = $options->concat(ModifierOption::withTrashed()->whereIn('id', $optionIdChunk)->get());
        }
        $options = $options->keyBy('id');

        return [$groups, $options];
    }

    /**
     * Currently-active, non-deleted modifier groups/options attached to each
     * menu item that appeared in orders — used only to seed zero-selection
     * catalog entries (a paid option nobody ever picks should still show up
     * as a 0%-adoption row, not be invisible). Deliberately excludes
     * inactive/deleted configurations: recommending removal of something
     * already removed isn't useful.
     *
     * @return array<int, Collection<int, ModifierGroup>> menu_item_id => groups (with options loaded)
     */
    private function activeModifierGroupsByMenuItem(Collection $uniqueLines): array
    {
        $menuItemIds = $uniqueLines->map(fn (CartItem $l) => $l->menuItem?->id)->filter()->unique()->values();

        if ($menuItemIds->isEmpty()) {
            return [];
        }

        // Chunked at 1,000 for the same reason as preloadModifierModels()
        // above — see its comment.
        $items = collect();
        foreach ($menuItemIds->chunk(1000) as $menuItemIdChunk) {
            $items = $items->concat(
                MenuItem::query()
                    ->whereIn('id', $menuItemIdChunk)
                    ->with(['modifierGroups' => fn ($q) => $q->where('is_active', true)
                        ->with(['options' => fn ($oq) => $oq->where('is_active', true)])])
                    ->get()
            );
        }

        return $items->mapWithKeys(fn (MenuItem $item) => [$item->id => $item->modifierGroups])->all();
    }

    // -------------------------------------------------------------- seeding

    /** @return array<string, array<string, mixed>> */
    private function seedConfiguredDefinitions(array $itemsSeen): array
    {
        $catalog = [];

        foreach ($itemsSeen as $uid => $info) {
            $item = $info['menuItem'];

            foreach ($item->paid_addons ?? [] as $index => $addon) {
                $id = $this->definitionId($addon, $index);
                $catalog["addon:{$uid}:{$id}"] = $this->newEntry(
                    'addon', 'paid_addon', $addon['name'] ?? 'Add-on', $uid, $info,
                    price: round((float) ($addon['price'] ?? 0), 2), allowsMultiple: false,
                );
            }

            foreach ($item->free_addons ?? [] as $index => $addon) {
                $id = $this->definitionId($addon, $index);
                $name = is_array($addon) ? ($addon['name'] ?? 'Free add-on') : (string) $addon;
                $catalog["free:{$uid}:{$id}"] = $this->newEntry(
                    'addon', 'free_addon', $name, $uid, $info, price: 0.0, allowsMultiple: false,
                );
            }

            foreach ($item->removable_items ?? [] as $index => $removable) {
                $id = $this->definitionId($removable, $index);
                $name = is_array($removable) ? ($removable['name'] ?? 'Removable item') : (string) $removable;
                $catalog["removable:{$uid}:{$id}"] = $this->newEntry(
                    'addon', 'removable_item', $name, $uid, $info, price: null, allowsMultiple: false,
                );
            }
        }

        return $catalog;
    }

    /** @param array<string, array<string, mixed>> $catalog */
    private function seedActiveModifierOptions(array $catalog, array $itemsSeen, array $activeGroupsByMenuItem): array
    {
        foreach ($itemsSeen as $uid => $info) {
            $menuItemId = $info['menuItem']->id;
            $groups = $activeGroupsByMenuItem[$menuItemId] ?? collect();

            foreach ($groups as $group) {
                $kind = $group->type === 'remove' ? 'removable_item' : null; // paid/free resolved per-option below

                foreach ($group->options as $option) {
                    $priceAdj = (float) $option->price_adjustment;
                    $entryKind = $kind ?? ($priceAdj > 0 ? 'paid_addon' : 'free_addon');
                    $key = "modifier:{$uid}:{$group->id}:{$option->id}";

                    $catalog[$key] = $this->newEntry(
                        'modifier', $entryKind, $option->name, $uid, $info,
                        price: $priceAdj, allowsMultiple: $group->type === 'multiple', groupName: $group->name,
                    );
                }
            }
        }

        return $catalog;
    }

    private function newEntry(
        string $system,
        string $kind,
        string $name,
        string $menuItemUid,
        array $itemInfo,
        ?float $price,
        bool $allowsMultiple,
        ?string $groupName = null,
    ): array {
        return [
            'system' => $system,
            'kind' => $kind,
            'name' => $name,
            'groupName' => $groupName,
            'menuItemName' => $itemInfo['name'],
            'categoryName' => $itemInfo['categoryName'],
            'menuItemUid' => $menuItemUid,
            'allowsMultiple' => $allowsMultiple,
            'price' => $price !== null ? round($price, 2) : null,
            'timesSelected' => 0,
            'eligible' => $itemInfo['eligibleQty'],
            'revenue' => 0.0,
        ];
    }

    private function definitionId(mixed $definition, int $index): int
    {
        $id = is_array($definition) ? ($definition['id'] ?? null) : null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : $index + 1;
    }

    // --------------------------------------------------------- accumulation

    /** @param array<string, array<string, mixed>> $catalog */
    private function accumulateSelectionCounts(
        array $catalog,
        Collection $uniqueLines,
        array $itemsSeen,
        Collection $groups,
        Collection $options,
    ): array {
        foreach ($uniqueLines as $line) {
            $item = $line->menuItem;
            if (! $item) {
                continue;
            }

            $uid = $this->menuItemUid($item);
            $info = $itemsSeen[$uid];
            $qty = (int) $line->quantity;

            foreach ($line->paid_addons ?? [] as $addon) {
                $id = (int) ($addon['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $catalog = $this->bump($catalog, "addon:{$uid}:{$id}", $qty, fn () => $this->newEntry(
                    'addon', 'paid_addon', $this->resolveDefinitionName($item->paid_addons ?? [], $id, 'Add-on'),
                    $uid, $info, price: (float) ($addon['price'] ?? 0), allowsMultiple: false,
                ));
            }

            foreach ($line->free_addons ?? [] as $id) {
                $id = (int) $id;
                if ($id <= 0) {
                    continue;
                }
                $catalog = $this->bump($catalog, "free:{$uid}:{$id}", $qty, fn () => $this->newEntry(
                    'addon', 'free_addon', $this->resolveDefinitionName($item->free_addons ?? [], $id, 'Free add-on'),
                    $uid, $info, price: 0.0, allowsMultiple: false,
                ));
            }

            foreach ($line->removed_items ?? [] as $id) {
                $id = (int) $id;
                if ($id <= 0) {
                    continue;
                }
                $catalog = $this->bump($catalog, "removable:{$uid}:{$id}", $qty, fn () => $this->newEntry(
                    'addon', 'removable_item', $this->resolveDefinitionName($item->removable_items ?? [], $id, 'Removable item'),
                    $uid, $info, price: null, allowsMultiple: false,
                ));
            }

            foreach ($line->selected_modifiers ?? [] as $group) {
                $groupId = (int) ($group['modifier_group_id'] ?? 0);
                $groupType = $group['type'] ?? 'single';
                $groupModel = $groups->get($groupId);
                $groupName = $groupModel?->name ?? ($group['name'] ?? 'Modifier');

                foreach ($group['options'] ?? [] as $option) {
                    $optionId = (int) ($option['id'] ?? 0);
                    if ($optionId <= 0) {
                        continue;
                    }

                    $priceAdj = (float) ($option['price_adjustment'] ?? 0);
                    $kind = $groupType === 'remove' ? 'removable_item' : ($priceAdj > 0 ? 'paid_addon' : 'free_addon');
                    $optionModel = $options->get($optionId);
                    $optionName = $optionModel?->name ?? ($option['name'] ?? 'Option');

                    $key = "modifier:{$uid}:{$groupId}:{$optionId}";
                    $catalog = $this->bump($catalog, $key, $qty, fn () => $this->newEntry(
                        'modifier', $kind, $optionName, $uid, $info,
                        price: $priceAdj, allowsMultiple: $groupType === 'multiple', groupName: $groupName,
                    ));
                }
            }
        }

        return $catalog;
    }

    /** @param array<string, array<string, mixed>> $catalog */
    private function bump(array $catalog, string $key, int $qty, \Closure $makeDefault): array
    {
        $catalog[$key] ??= $makeDefault();
        $catalog[$key]['timesSelected'] += $qty;

        return $catalog;
    }

    private function resolveDefinitionName(array $definitions, int $id, string $fallback): string
    {
        foreach ($definitions as $index => $definition) {
            if ($this->definitionId($definition, $index) === $id) {
                return is_array($definition) ? ($definition['name'] ?? $fallback) : (string) $definition;
            }
        }

        return $fallback;
    }

    /** @param array<string, array<string, mixed>> $catalog */
    private function accumulateRevenue(array $catalog, Collection $lines, array $itemsSeen): array
    {
        foreach ($lines as $line) {
            $item = $line->menuItem;
            if (! $item) {
                continue;
            }

            $uid = $this->menuItemUid($item);
            $itemTaxCategory = $item->tax_category ?? 'food';
            $qty = (int) $line->quantity;
            $shareCount = 1 + count($line->shared_order_ids ?? []);

            foreach ($line->paid_addons ?? [] as $addon) {
                $id = (int) ($addon['id'] ?? 0);
                if ($id <= 0 || ! isset($catalog["addon:{$uid}:{$id}"])) {
                    continue;
                }
                $vatRate = TaxCalculationService::addonVatRate($addon, $itemTaxCategory, $this->country);
                $gross = TaxCalculationService::gross((float) ($addon['price'] ?? 0), $vatRate) * $qty / $shareCount;
                $catalog["addon:{$uid}:{$id}"]['revenue'] += $gross;
            }

            foreach ($line->selected_modifiers ?? [] as $group) {
                $groupId = (int) ($group['modifier_group_id'] ?? 0);
                $groupTaxCategory = $group['tax_category'] ?? '';
                $vatRate = TaxCalculationService::modifierGroupVatRate($groupTaxCategory, $itemTaxCategory, $this->country);

                foreach ($group['options'] ?? [] as $option) {
                    $optionId = (int) ($option['id'] ?? 0);
                    $key = "modifier:{$uid}:{$groupId}:{$optionId}";
                    if ($optionId <= 0 || ! isset($catalog[$key])) {
                        continue;
                    }
                    $gross = TaxCalculationService::gross((float) ($option['price_adjustment'] ?? 0), $vatRate) * $qty / $shareCount;
                    $catalog[$key]['revenue'] += $gross;
                }
            }
        }

        return $catalog;
    }

    /** @param array<string, array<string, mixed>> $catalog */
    private function finalizeRates(array $catalog): array
    {
        foreach ($catalog as $key => $entry) {
            $catalog[$key]['revenue'] = round($entry['revenue'], 2);
            $catalog[$key]['selectionRatePercent'] = $entry['eligible'] > 0
                ? round(($entry['timesSelected'] / $entry['eligible']) * 100, 1)
                : null;

            // A selected paid entry's effective average charged price can
            // differ from its currently-configured price (past price
            // changes, or a stale seed) — the actually-charged average is
            // the more honest number to show once there's real revenue.
            if ($entry['timesSelected'] > 0 && $entry['kind'] === 'paid_addon') {
                $catalog[$key]['price'] = round($entry['revenue'] / $entry['timesSelected'], 2);
            }
        }

        return $catalog;
    }

    // ------------------------------------------------------------- views

    private function topByRevenue(array $catalog): array
    {
        return collect($catalog)
            ->where('kind', 'paid_addon')
            ->where('revenue', '>', 0)
            ->sortByDesc('revenue')
            ->take(self::LIST_LIMIT)
            ->values()
            ->map(fn ($e) => $this->present($e))
            ->all();
    }

    /**
     * "Revenue impact of free add-ons" (#2): free add-ons don't charge
     * anything directly, so their impact is read as a correlation — the
     * average value of orders that included the add-on vs the vendor's own
     * baseline average order value this period. Framed as a lift, not a
     * causal claim — an order that adds a free extra may simply come from a
     * guest who was already ordering more.
     */
    private function freeAddonsImpact(array $catalog, Collection $lines, Collection $ordersById, ?float $baseline): array
    {
        $candidates = collect($catalog)
            ->where('kind', 'free_addon')
            ->where('timesSelected', '>=', self::MIN_SAMPLE)
            ->sortByDesc('timesSelected')
            ->take(self::LIST_LIMIT);

        if ($candidates->isEmpty() || $baseline === null || $baseline <= 0) {
            return ['available' => false, 'baselineAvgOrderValue' => $baseline];
        }

        // Map each catalog key back to the distinct orders that selected it,
        // by re-walking lines once (cheap: only for the handful of
        // candidates above the sample gate, not the whole catalog).
        $ordersByKey = [];
        foreach ($lines as $line) {
            $item = $line->menuItem;
            if (! $item) {
                continue;
            }
            $uid = $this->menuItemUid($item);
            $orderId = $line->attributedOrderId ?? $line->order_id;

            foreach ($line->free_addons ?? [] as $id) {
                $key = "free:{$uid}:".(int) $id;
                if (isset($candidates[$key])) {
                    $ordersByKey[$key][$orderId] = true;
                }
            }
            foreach ($line->selected_modifiers ?? [] as $group) {
                foreach ($group['options'] ?? [] as $option) {
                    $key = "modifier:{$uid}:".(int) ($group['modifier_group_id'] ?? 0).':'.(int) ($option['id'] ?? 0);
                    if (isset($candidates[$key])) {
                        $ordersByKey[$key][$orderId] = true;
                    }
                }
            }
        }

        $items = $candidates->map(function ($entry, $key) use ($ordersByKey, $ordersById, $baseline) {
            $orderIds = array_keys($ordersByKey[$key] ?? []);
            $amounts = collect($orderIds)->map(fn ($id) => $ordersById->get($id)?->amount)->filter(fn ($v) => $v !== null);

            if ($amounts->isEmpty()) {
                return null;
            }

            $avgWithAddon = round((float) $amounts->avg(), 2);

            return $this->present($entry) + [
                'avgOrderValueWithAddon' => $avgWithAddon,
                'upliftPercent' => round((($avgWithAddon - $baseline) / $baseline) * 100, 1),
            ];
        })->filter()->values()->all();

        return [
            'available' => $items !== [],
            'baselineAvgOrderValue' => $baseline,
            'items' => $items,
        ];
    }

    private function mostPopular(array $catalog): array
    {
        return collect($catalog)
            ->filter(fn ($e) => $e['eligible'] >= self::MIN_SAMPLE)
            ->sortByDesc('timesSelected')
            ->take(self::LIST_LIMIT)
            ->values()
            ->map(fn ($e) => $this->present($e))
            ->all();
    }

    private function leastPopular(array $catalog): array
    {
        return collect($catalog)
            ->filter(fn ($e) => $e['eligible'] >= self::MIN_SAMPLE && $e['kind'] !== 'removable_item')
            ->sortBy('timesSelected')
            ->take(self::LIST_LIMIT)
            ->values()
            ->map(fn ($e) => $this->present($e))
            ->all();
    }

    /** "Frequently selected — consider promoting/offering more prominently" (#6). */
    private function promotionCandidates(array $catalog): array
    {
        return collect($catalog)
            ->filter(fn ($e) => $e['kind'] !== 'removable_item'
                && $e['eligible'] >= self::MIN_SAMPLE
                && ($e['selectionRatePercent'] ?? 0) >= self::PROMOTE_THRESHOLD_PERCENT)
            ->sortByDesc('selectionRatePercent')
            ->take(self::LIST_LIMIT)
            ->values()
            ->map(fn ($e) => $this->present($e))
            ->all();
    }

    /**
     * "Rarely selected — may need to be removed, changed or repositioned"
     * (#7) and "opportunities to remove unnecessary/low-value modifiers"
     * (#12) are the same underlying signal at different severities, so this
     * single list covers both, distinguished by `recommendation`.
     */
    private function underperforming(array $catalog): array
    {
        return collect($catalog)
            ->filter(fn ($e) => $e['kind'] !== 'removable_item'
                && $e['eligible'] >= self::MIN_SAMPLE
                && ($e['selectionRatePercent'] ?? 100) <= self::RECONSIDER_THRESHOLD_PERCENT)
            ->sortBy('selectionRatePercent')
            ->take(self::LIST_LIMIT)
            ->values()
            ->map(function ($e) {
                $rate = $e['selectionRatePercent'] ?? 0;
                $recommendation = $rate <= self::REMOVE_THRESHOLD_PERCENT
                    ? ($e['timesSelected'] === 0
                        ? 'Never selected this period — consider removing it from the menu.'
                        : 'Almost never selected — consider removing or replacing it.')
                    : 'Rarely selected — consider renaming, repricing, or repositioning it where guests see it earlier in the flow.';

                return $this->present($e) + ['recommendation' => $recommendation];
            })
            ->all();
    }

    private function removableItems(array $catalog): array
    {
        return collect($catalog)
            ->where('kind', 'removable_item')
            ->filter(fn ($e) => $e['eligible'] >= self::MIN_SAMPLE)
            ->sortByDesc('selectionRatePercent')
            ->take(self::LIST_LIMIT)
            ->values()
            ->map(fn ($e) => $this->present($e) + ['removalRatePercent' => $e['selectionRatePercent']])
            ->all();
    }

    /** "Significant % of guests remove a component" + "should the default change?" (#9, #10). */
    private function significantRemovals(array $catalog): array
    {
        return collect($catalog)
            ->where('kind', 'removable_item')
            ->filter(fn ($e) => $e['eligible'] >= self::MIN_SAMPLE
                && ($e['selectionRatePercent'] ?? 0) >= self::SIGNIFICANT_REMOVAL_THRESHOLD_PERCENT)
            ->sortByDesc('selectionRatePercent')
            ->take(self::LIST_LIMIT)
            ->values()
            ->map(fn ($e) => $this->present($e) + [
                'removalRatePercent' => $e['selectionRatePercent'],
                'recommendation' => sprintf(
                    "%.0f%% of guests who ordered %s removed %s — worth testing %s as an opt-in extra instead of the default.",
                    $e['selectionRatePercent'], $e['menuItemName'], $e['name'], $e['name'],
                ),
            ])
            ->all();
    }

    /**
     * "Opportunities to add new paid add-ons based on customer behavior"
     * (#11) — scoped to System A (menu_items.paid_addons) for the
     * comparison benchmark: find a menu item with real order volume and NO
     * paid add-on configured at all, sitting in a category where a sibling
     * item's paid add-on already sees meaningful adoption. That sibling's
     * adoption rate is real, in-category evidence that guests in this
     * category are willing to pay for an extra — not a guess.
     */
    private function newAddonOpportunities(array $catalog, array $itemsSeen): array
    {
        $categoryBestAdoption = collect($catalog)
            ->where('system', 'addon')
            ->where('kind', 'paid_addon')
            ->whereNotNull('selectionRatePercent')
            ->groupBy('categoryName')
            ->map(fn ($entries) => $entries->max('selectionRatePercent'));

        return collect($itemsSeen)
            ->filter(fn ($info) => ! $info['hasPaidAddonsConfigured'] && $info['eligibleQty'] >= self::MIN_SAMPLE)
            ->map(function ($info) use ($categoryBestAdoption) {
                $benchmark = $categoryBestAdoption->get($info['categoryName']);
                if ($benchmark === null || $benchmark < self::OPPORTUNITY_BENCHMARK_PERCENT) {
                    return null;
                }

                return [
                    'menuItemName' => $info['name'],
                    'categoryName' => $info['categoryName'],
                    'eligibleQty' => $info['eligibleQty'],
                    'categoryBestAdoptionPercent' => round($benchmark, 1),
                ];
            })
            ->filter()
            ->sortByDesc('eligibleQty')
            ->take(self::LIST_LIMIT)
            ->values()
            ->all();
    }

    /**
     * "Assess whether a paid add-on appears underpriced, overpriced, or
     * appropriately priced" (#13, #14) — a heuristic read of this add-on's
     * adoption rate against the vendor's own average paid-add-on adoption
     * rate this period, not a price experiment. Explicitly labeled as
     * analytical, both per-item (`note`) and once more at the top level.
     */
    private function pricingAnalysis(array $catalog): array
    {
        $paid = collect($catalog)->where('kind', 'paid_addon');
        $sampled = $paid->filter(fn ($e) => $e['eligible'] >= self::MIN_PRICING_SAMPLE);

        if ($sampled->isEmpty()) {
            return ['available' => false];
        }

        $baselineAdoption = round((float) $sampled->avg('selectionRatePercent'), 1);

        $items = $sampled->sortByDesc('revenue')->take(self::LIST_LIMIT * 2)->map(function ($e) use ($baselineAdoption) {
            $rate = $e['selectionRatePercent'] ?? 0;
            [$verdict, $note] = match (true) {
                $baselineAdoption <= 0 => ['insufficient_data', 'Not enough catalog-wide adoption yet to compare this add-on against.'],
                $rate >= $baselineAdoption * 1.5 => ['possibly_underpriced', 'Adoption is well above your other paid add-ons — a small price increase would likely still be accepted by most guests choosing it.'],
                $rate <= $baselineAdoption * 0.4 => ['possibly_overpriced', 'Adoption is well below your other paid add-ons despite real visibility — the price (or the value guests perceive) may be the barrier.'],
                default => ['appropriately_priced', 'Adoption is in line with your other paid add-ons — no clear pricing signal either way.'],
            };

            return $this->present($e) + [
                'adoptionRatePercent' => $rate,
                'verdict' => $verdict,
                'note' => $note,
            ];
        })->values()->all();

        return [
            'available' => true,
            'baselineAdoptionRatePercent' => $baselineAdoption,
            'items' => $items,
        ];
    }

    private function present(array $entry): array
    {
        return [
            'system' => $entry['system'],
            'kind' => $entry['kind'],
            'name' => $entry['name'],
            'groupName' => $entry['groupName'],
            'menuItemName' => $entry['menuItemName'],
            'categoryName' => $entry['categoryName'],
            'allowsMultiple' => $entry['allowsMultiple'],
            'price' => $entry['price'],
            'timesSelected' => $entry['timesSelected'],
            'eligible' => $entry['eligible'],
            'selectionRatePercent' => $entry['selectionRatePercent'],
            'revenue' => $entry['revenue'],
        ];
    }
}
