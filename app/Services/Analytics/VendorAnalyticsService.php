<?php

namespace App\Services\Analytics;

use App\Models\CartItem;
use App\Models\CustomerLoyaltyPoint;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryStockMovement;
use App\Models\LoyaltyTransaction;
use App\Models\MenuItem;
use App\Models\MenuItemIngredient;
use App\Models\MenuItemView;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Models\VendorActivity;
use App\Services\PaymentMethodDetailsService;
use App\Services\Reports\ReportPeriodTooLargeException;
use App\Services\TaxCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the vendor analytics payload from live order data.
 *
 * Order scoping rules applied consistently everywhere in this class:
 *  - drafts are excluded (confirmed_at must be set) — a draft is a basket, not an order
 *  - cancelled orders are excluded from revenue and from order counts
 *  - revenue counts only orders where payment_received is true
 *  - average order value divides paid revenue by PAID orders, never by all orders
 *
 * Money notes:
 *  - Order.amount is gross: it already includes VAT and the service fee.
 *  - VAT is derived fresh from each paid order's own cart items via
 *    TaxCalculationService::computeTaxGroups(), never read from the stored
 *    `orders.vat_amount` column — a full grep of every live write path
 *    (CartController, PaymentController, OrderAmountRecalculationService)
 *    found nothing that ever populates that column outside of seeders, so on
 *    real vendor data it is always 0. FinancialReportService hit and solved
 *    the exact same problem; see its class doc comment and vatByOrderId().
 *    Net revenue = amount - the derived VAT.
 *  - Order.tip_amount sits outside amount and is reported separately.
 */
class VendorAnalyticsService
{
    private string $country;

    public function __construct(
        private readonly PaymentMethodDetailsService $paymentDetails,
        private readonly ModifierAnalyticsService $modifierAnalytics,
    ) {}

    /**
     * Only the raw `orders`/`previousOrders` fetch is cached, NOT `lines`/
     * `previousLines` and NOT the assembled payload. `index`, `insights`,
     * `askInsights` and `suggestedQuestions` (AnalyticsController) all call
     * `build()` independently, so a single page load previously re-ran
     * `ordersIn()` 3-4 times over — cheap, relation-free queries, worth
     * skipping on a repeat request within the TTL. `linesFor()` (the actual
     * stress-test bottleneck: the chunked owned-items queries plus the
     * full-history shared-item scan) is deliberately NOT cached despite being
     * the more expensive half to compute: caching the hydrated
     * CartItem→MenuItem→Category object graph was measured (2026-09-07) to
     * push peak memory on a realistic vendor's "Last 12 months" view to
     * 652MB, because serializing a large nested object graph for the
     * database cache store costs substantially more than holding the live
     * objects — recomputing it fresh every call is a straight memory-for-
     * time trade, not a regression. Every downstream sub-aggregation also
     * still runs fresh on every call regardless of the orders cache — this
     * matters because several of them read LIVE state that must never lag
     * behind a vendor's own just-made change (confirmed the hard way: an
     * earlier version cached the whole assembled payload and broke
     * `test_inventory_reports_auto_deduction_enabled_from_settings`, since
     * `inventory()`'s `autoDeductionEnabled` reads `$vendor->inventorySettings`
     * fresh and has nothing to do with order/line data — caching the whole
     * response papered over a real settings change with a stale snapshot).
     * The cache key bakes in the resolved start/end timestamps (not just the
     * period name), so a "custom" range gets its own entry and a "today"/
     * "daily" entry naturally rebuilds as the window rolls forward.
     */
    private const CACHE_TTL_SECONDS = 120;

    public function build(Vendor $vendor, AnalyticsPeriod $period): array
    {
        $this->country = $vendor->country ?: 'AT';

        [$orders, $previousOrders, $lines, $previousLines] = $this->ordersAndLinesFor($vendor, $period);

        $paidOrders = $orders->where('payment_received', true);

        return [
            'period' => $period->key,
            'currency' => $vendor->currency,
            'timezone' => $period->timezone,
            'range' => [
                'from' => $period->start->toISOString(),
                'to' => $period->end->toISOString(),
                'label' => $period->rangeLabel(),
                'periodLabel' => $period->label,
                'comparisonLabel' => $period->comparisonLabel,
                // True only for period=custom, when the vendor's requested
                // range exceeded AnalyticsPeriod::MAX_CUSTOM_DAYS and was
                // shortened — see AnalyticsPeriod::buildCustom()'s comment.
                'truncated' => $period->customRangeTruncated,
                'maxCustomDays' => AnalyticsPeriod::MAX_CUSTOM_DAYS,
            ],
            'hasData' => $orders->isNotEmpty(),
            'summary' => $this->summary($vendor, $period, $orders, $previousOrders, $lines, $previousLines),
            'trend' => $this->trend($period, $orders),
            'menu' => $this->menu($vendor, $period, $lines, $previousLines),
            'soldOut' => $this->soldOutFrequency($vendor, $period),
            'categories' => $this->categories($lines),
            'discounts' => $this->discounts($paidOrders, $lines),
            'payments' => $this->payments($vendor, $period, $orders),
            'tips' => $this->tips($orders, $previousOrders, $period),
            'service' => $this->service($vendor, $period, $orders),
            'channels' => $this->channels($orders),
            'cancellations' => $this->cancellations($vendor, $period),
            'peak' => $this->peak($period, $orders),
            'customers' => $this->customers($vendor, $orders),
            'reservations' => $this->reservations($vendor, $period),
            'retention' => $this->retention($vendor, $period),
            'reviews' => $this->reviews($vendor, $period, $orders),
            'inventory' => $this->inventory($vendor, $period, $lines),
            'loyalty' => $this->loyalty($vendor, $period, $paidOrders, $lines),
            'modifiers' => $this->modifierAnalytics->build($vendor, $lines, $paidOrders),
        ];
    }

    /** @return array{0: Collection, 1: Collection, 2: Collection, 3: Collection} orders, previousOrders, lines, previousLines */
    private function ordersAndLinesFor(Vendor $vendor, AnalyticsPeriod $period): array
    {
        // Only orders are cached — see the class doc comment above for why
        // lines deliberately are not (2026-09-07: measured caching the
        // hydrated CartItem→MenuItem→Category object graph pushing peak
        // memory on a realistic vendor's "Last 12 months" view to 652MB,
        // because PHP's serialize() representation of a large nested object
        // graph runs well past the size of the live data itself — the
        // caching layer meant to fix "the dashboard is slow" was turning
        // into a new way for it to crash on exactly the vendors it most
        // needed to help).
        //
        // Even orders-only still crashes at large enough scale (2026-09-08
        // audit finding, reproduced directly): simply BUILDING (not even
        // caching) the order/line collections for a ~2,900-order period —
        // and Analytics' own default "Last 12 months" trend view reaches
        // that for any established vendor, not an extreme edge case —
        // reliably exhausted a 128MB PHP process, well below what the "too
        // big to run live" threshold below previously assumed was safe. See
        // config/reports.php's doc comments for the full measurement. Two
        // separate guards follow, mirroring FinancialReportService's own
        // (both features share the same underlying crash class):
        //
        //  1. Above `max_order_count`, refuse outright — throwing here
        //     (rather than attempting the hydration and possibly hitting an
        //     uncatchable out-of-memory fatal error) is what makes this
        //     failure catchable at all. A live request is already kept well
        //     below this ceiling by `async_order_threshold` below, so in
        //     practice this only ever fires from the background-job path,
        //     whose existing `catch (Throwable $e)` handles it exactly like
        //     any other failure — and AnalyticsController's insights()/
        //     askInsights()/suggestedQuestions() endpoints, which call
        //     build() directly with no gate at all, now also catch it
        //     explicitly (see that controller).
        //  2. Above `async_order_threshold` (but still under the hard
        //     ceiling), skip the raw-orders cache and return the
        //     freshly-built collections directly — a request this large is
        //     always routed to the background-job path by the controller,
        //     which already caches its own much leaner, fully-computed JSON
        //     result (see FinancialReportJob), so this lower-level cache
        //     was never doing useful work for these requests anyway, only
        //     risking adding Cache::remember()'s own serialize() call on top
        //     of an already-tight memory budget.
        $orderCount = $this->ordersCountIn($vendor, $period->queryStart(), $period->queryEnd());

        $maxOrderCount = (int) config('reports.max_order_count');
        if ($orderCount > $maxOrderCount) {
            throw new ReportPeriodTooLargeException($orderCount, $maxOrderCount);
        }

        if ($orderCount > (int) config('reports.async_order_threshold')) {
            [$orders, $previousOrders] = [
                $this->ordersIn($vendor, $period->queryStart(), $period->queryEnd()),
                $this->ordersIn($vendor, $period->previousQueryStart(), $period->previousQueryEnd()),
            ];
        } else {
            $cacheKey = sprintf(
                'analytics:orders:v%d:%s:%s:%s',
                $vendor->id,
                $period->key,
                $period->start->toIso8601String(),
                $period->end->toIso8601String(),
            );

            [$orders, $previousOrders] = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn () => [
                $this->ordersIn($vendor, $period->queryStart(), $period->queryEnd()),
                $this->ordersIn($vendor, $period->previousQueryStart(), $period->previousQueryEnd()),
            ]);
        }

        // sharedIntoCandidates() has no date filter — it's every shared cart
        // item this vendor has EVER had, scoped by vendor only (see its own
        // doc comment) — so it is identical for the current and previous
        // window and would otherwise be fetched and hydrated twice per
        // request for no reason. Fetched once here and handed to both
        // linesFor() calls below.
        $sharedCandidates = $orders->isNotEmpty() || $previousOrders->isNotEmpty()
            ? $this->sharedIntoCandidates((int) $vendor->id)
            : collect();

        // Line-level reporting (menu, categories, discounts, basket depth) is
        // scoped to PAID orders so it reconciles with headline revenue. Using
        // all confirmed orders here would make the menu table add up to more
        // than the revenue figure above it, with nothing on screen to explain
        // the difference.
        $lines = $this->linesFor($orders->where('payment_received', true), $sharedCandidates);
        // The previous window's lines are only ever used for a revenue/
        // quantity comparison (menu()'s $previous map reads nothing but
        // ['revenue']) — the category name aggregateLines() also computes
        // for them is built but never read for the previous half, so
        // skipping the category/masterCategory relation chain here is safe
        // and cuts real memory on every request (see aggregateLines()'s
        // own $includeCategory parameter).
        $previousLines = $this->linesFor(
            $previousOrders->where('payment_received', true),
            $sharedCandidates,
            withCatalogRelations: false,
        );

        return [$orders, $previousOrders, $lines, $previousLines];
    }

    /**
     * Every shared cart item this vendor has ever had (`shared_order_ids` not
     * null), completely unscoped by date — deliberately, since a shared
     * item's OWN order can fall far outside the window of the order it was
     * shared INTO, so there is no safe date bound to add without risking a
     * silently-dropped share. Callers narrow this candidate set down to
     * "does this intersect the order IDs I actually care about" in PHP (see
     * linesFor()). Fetched once per build() and reused for both the current
     * and previous period — see ordersAndLinesFor().
     */
    private function sharedIntoCandidates(int $vendorId): Collection
    {
        return CartItem::with(['menuItem.category.masterCategory'])
            ->whereNotNull('shared_order_ids')
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))
            ->get([
                'id', 'order_id', 'menu_item_id', 'quantity', 'paid_addons', 'free_addons',
                'removed_items', 'selected_modifiers', 'shared_order_ids', 'table_scan_session_id',
            ]);
    }

    // ---------------------------------------------------------------- scoping

    /** Confirmed, non-cancelled orders inside a window. */
    private function ordersIn(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->baseQuery($vendor)
            ->whereBetween('created_at', [$from, $to])
            ->get([
                'id', 'vendor_id', 'customer_id', 'amount', 'service_fee', 'tip_amount',
                'payment_received', 'payment_method', 'order_type', 'table_scan_session_id',
                'created_at', 'confirmed_at', 'in_progress_at', 'served_at',
                'picked_up_at', 'payment_confirmed_at',
            ]);
    }

    /**
     * Same filter as ordersIn(), a bare COUNT — used by the controller to
     * decide whether a period is cheap enough to build live or big enough to
     * hand off to the background job (see FinancialReportJob's doc comment,
     * shared by this and FinancialReportService's identically-named method).
     * Deliberately public: the only method on this class a caller outside it
     * needs before deciding whether to call build() at all.
     */
    public function ordersCountIn(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->baseQuery($vendor)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    private function baseQuery(Vendor $vendor): Builder
    {
        return Order::query()
            ->where('vendor_id', $vendor->id)
            ->whereNotNull('confirmed_at')
            ->whereNull('cancelled_at');
    }

    /**
     * Cart items are the real line-item store (order_items is unused legacy).
     * menuItem() is already ->withTrashed(), so each line resolves to the exact
     * menu version that was live when it was ordered — which is what makes
     * historical pricing and discount reporting accurate.
     *
     * Every item genuinely on at least one order in `$orders` — owned
     * outright, or shared into from another order's cart — mirrors the same
     * "owned + sharedInto" pattern FinancialReportService::cartItemsFor()
     * uses, for the same reason: a cart line is stored once, against its
     * owning order, so a query scoped only to `order_id IN (...)` misses a
     * sharer's half entirely whenever the *owning* order falls outside
     * `$orders` (a different reporting window) while the sharer's own order
     * is inside it — even though that sharer's `orders.amount` genuinely
     * includes their share. That silently understated derived VAT (hence
     * `summary.netRevenue`), Menu Performance, Category Mix and Discounts
     * whenever a bill-split crossed a period boundary.
     *
     * Each returned item carries a synthetic `attributedOrderId` — NOT the
     * same as the item's real `order_id` for a shared-into entry. Callers
     * that attribute a line back to "the order it belongs to" (discounts'
     * per-order tracking, `vatByOrderId`) must group by `attributedOrderId`.
     * Callers that total revenue/cost per menu item (aggregateLines,
     * categories, costByProductUid) must divide each occurrence's gross by
     * its own share count and/or dedupe by cart-item id for quantity — see
     * each method's own comment for why.
     *
     * @return Collection<int, CartItem>
     */
    private function linesFor(Collection $orders, Collection $sharedCandidates, bool $withCatalogRelations = true): Collection
    {
        if ($orders->isEmpty()) {
            return collect();
        }

        $orderIds = $orders->pluck('id');
        $sessionIdByOrderId = $orders->pluck('table_scan_session_id', 'id');

        // MenuCategory::$name is an accessor that reads through masterCategory,
        // so the chain has to be eager-loaded rather than column-selected.
        // Skipped entirely when $withCatalogRelations is false (the previous-
        // period call, see ordersAndLinesFor()) — aggregateLines() computes but
        // never reads a category name for those rows.
        //
        // Chunked at 1,000 IDs per query — a single `whereIn('order_id', $orderIds)`
        // with every order ID in the window hit SQLite's ~32,766 bound-parameter
        // ceiling (and would keep growing on MySQL/Postgres) once a realistic
        // multi-year vendor's "monthly"/"custom" window passed that many orders —
        // same failure class as the shared-bill query fixed above, just on the
        // owned-items half instead. concat(), not merge(), to match the
        // no-dedup-by-id requirement explained below.
        $owned = collect();
        foreach ($orderIds->chunk(1000) as $orderIdChunk) {
            $query = CartItem::whereIn('order_id', $orderIdChunk);
            if ($withCatalogRelations) {
                $query->with(['menuItem.category.masterCategory']);
            } else {
                $query->with('menuItem');
            }
            $owned = $owned->concat($query->get([
                'id', 'order_id', 'menu_item_id', 'quantity', 'paid_addons', 'free_addons',
                'removed_items', 'selected_modifiers', 'shared_order_ids', 'table_scan_session_id',
            ]));
        }
        $owned->each(function (CartItem $item) {
            $item->attributedOrderId = (int) $item->order_id;
        });

        // $sharedCandidates is this vendor's full shared-item history, fetched
        // once by the caller (see sharedIntoCandidates()) — deliberately NOT
        // restricted to orders outside $orderIds, since a shared item's owner
        // and sharer are usually BOTH in the same window, and each still
        // needs its own attributed entry. The intersect-and-dedupe loop below
        // does the actual "does this row belong to one of $orderIds" check
        // per row in PHP.
        $expanded = collect();
        foreach ($sharedCandidates as $item) {
            $sharingOrderIds = collect($item->shared_order_ids ?? [])->map(fn ($id) => (int) $id);

            foreach ($sharingOrderIds->intersect($orderIds) as $sharingOrderId) {
                // Mirrors ShareOrderService::recalcOrder()'s own exclusion — a
                // share within the same dine-in session isn't a genuine
                // cross-order split, it's already represented via the
                // owning order.
                if ((int) $sessionIdByOrderId->get($sharingOrderId) === (int) $item->table_scan_session_id) {
                    continue;
                }

                $clone = $item->replicate();
                $clone->id = $item->id;
                $clone->setRelation('menuItem', $item->menuItem);
                $clone->attributedOrderId = $sharingOrderId;
                $expanded->push($clone);
            }
        }

        // concat(), NOT merge() — Eloquent's Collection::merge() de-duplicates
        // by primary key, and every clone above deliberately keeps the SAME
        // id as its source item (for traceability). merge() here would
        // silently collapse a shared item's owned and shared-into entries
        // back down to one.
        return $owned->concat($expanded);
    }

    /**
     * VAT per order, derived fresh from that order's own cart items — see the
     * class-level doc comment for why this replaces `orders.vat_amount`. Same
     * approach as FinancialReportService::vatByOrderId(): one
     * computeTaxGroups() call per order, cheap even across this class's
     * 12-month monthly lookback since it runs over cart items already loaded
     * into memory rather than issuing any new queries.
     * `applySharing: true` matches how the order's own `amount` was actually
     * charged (CartController/PaymentController both split a shared line's
     * gross by its sharer count) — without it, a shared order's derived VAT
     * would be computed against a bigger gross than the order was ever
     * actually charged.
     *
     * @return array<int, float> order_id => vatAmount
     */
    private function vatByOrderId(Collection $lines): array
    {
        return $lines->groupBy(fn (CartItem $item) => $item->attributedOrderId ?? $item->order_id)
            ->map(function (Collection $items) {
                $groups = TaxCalculationService::computeTaxGroups($items, $this->country, applySharing: true);

                return round((float) array_sum(array_column($groups, 'vat_amount')), 2);
            })
            ->all();
    }

    // ---------------------------------------------------------------- summary

    private function summary(
        Vendor $vendor,
        AnalyticsPeriod $period,
        Collection $orders,
        Collection $previous,
        Collection $lines,
        Collection $previousLines,
    ): array {
        $paid = $orders->where('payment_received', true);
        $paidPrev = $previous->where('payment_received', true);

        $gross = round((float) $paid->sum('amount'), 2);
        $grossPrev = round((float) $paidPrev->sum('amount'), 2);

        $vat = round(array_sum($this->vatByOrderId($lines)), 2);
        $tips = round((float) $paid->sum('tip_amount'), 2);
        $tipsPrev = round((float) $paidPrev->sum('tip_amount'), 2);

        $guests = $this->guestStats($vendor, $period);
        $guestsPrev = $this->guestStats($vendor, $period, true);

        $items = $lines->sum('quantity');
        $itemsPrev = $previousLines->sum('quantity');

        $repeat = $this->repeatRate($vendor, $orders);
        $repeatPrev = $this->repeatRate($vendor, $previous);

        return [
            'grossRevenue' => $this->metric($gross, $grossPrev),
            'netRevenue' => $this->metric(round($gross - $vat, 2), null),
            'vatCollected' => $this->metric($vat, null),
            'orders' => $this->metric($orders->count(), $previous->count()),
            'paidOrders' => $this->metric($paid->count(), $paidPrev->count()),
            'avgOrderValue' => $this->metric(
                $paid->count() ? round($gross / $paid->count(), 2) : null,
                $paidPrev->count() ? round($grossPrev / $paidPrev->count(), 2) : null,
            ),
            'avgItemsPerOrder' => $this->metric(
                $paid->count() ? round($items / $paid->count(), 1) : null,
                $paidPrev->count() ? round($itemsPrev / $paidPrev->count(), 1) : null,
                'absolute',
            ),
            'repeatCustomerRate' => $this->metric($repeat, $repeatPrev, 'absolute'),
            'avgGuestsPerTable' => $this->metric($guests['average'], $guestsPrev['average'], 'absolute'),
            'totalGuests' => $this->metric($guests['guests'], $guestsPrev['guests']),
            'tableVisits' => $this->metric($guests['visits'], $guestsPrev['visits']),
            'revenuePerGuest' => $this->metric(
                $guests['guests'] ? round($this->dineInRevenue($paid) / $guests['guests'], 2) : null,
                null,
            ),
            'tips' => $this->metric($tips, $tipsPrev),
        ];
    }

    private function dineInRevenue(Collection $paid): float
    {
        return round((float) $paid->filter(
            fn ($o) => in_array($o->order_type, ['dine-in', 'dine_in'], true)
        )->sum('amount'), 2);
    }

    /**
     * Share of orders placed by a customer who had already ordered from this
     * vendor before that order. Guest orders (no customer_id) are excluded from
     * both sides of the ratio because they cannot be attributed.
     */
    private function repeatRate(Vendor $vendor, Collection $orders): ?float
    {
        $identified = $orders->whereNotNull('customer_id');

        if ($identified->isEmpty()) {
            return null;
        }

        // Chunked at 1,000 customer IDs per query — a vendor with a large paid
        // customer base in the window can exceed SQLite's ~32,766 bound-
        // parameter ceiling on a single whereIn(), the same failure class
        // fixed elsewhere in this file. pluck() returns a plain Collection
        // keyed by customer_id — union(), NOT merge(): merge() calls PHP's
        // array_merge() under the hood, which silently renumbers *integer*
        // keys instead of preserving them, destroying the customer_id
        // mapping. union() uses the `+` operator, which keeps them.
        $firstSeen = collect();
        foreach ($identified->pluck('customer_id')->unique()->chunk(1000) as $customerIdChunk) {
            $firstSeen = $firstSeen->union(
                Order::query()
                    ->where('vendor_id', $vendor->id)
                    ->whereNotNull('confirmed_at')
                    ->whereNull('cancelled_at')
                    ->whereIn('customer_id', $customerIdChunk)
                    ->selectRaw('customer_id, MIN(created_at) as first_at')
                    ->groupBy('customer_id')
                    ->pluck('first_at', 'customer_id')
            );
        }

        $repeat = $identified->filter(function ($order) use ($firstSeen) {
            $first = $firstSeen[$order->customer_id] ?? null;

            return $first !== null && $order->created_at->gt(CarbonImmutable::parse($first));
        })->count();

        return round(($repeat / $identified->count()) * 100, 1);
    }

    /**
     * Groups scan sessions into table visits.
     *
     * Guests are counted the way the KDS and Waiter Display count them: one
     * TableScanSession per guest device. Sessions on the same table that overlap
     * in time are one visit, so a party of four is a single occupancy rather
     * than four. A session that never closed has no known end, so its visit is
     * marked incomplete and left out of turnover.
     *
     * @return Collection<int, array{start: CarbonImmutable, end: ?CarbonImmutable, guests: int, complete: bool}>
     */
    private function tableVisits(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $sessions = TableScanSession::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('scanned_at', [$from, $to])
            ->orderBy('restaurant_table_id')
            ->orderBy('scanned_at')
            ->get(['id', 'restaurant_table_id', 'scanned_at', 'closed_at']);

        $visits = collect();

        foreach ($sessions->groupBy('restaurant_table_id') as $tableSessions) {
            $current = null;

            foreach ($tableSessions as $session) {
                $start = CarbonImmutable::parse($session->scanned_at);
                $closed = $session->closed_at ? CarbonImmutable::parse($session->closed_at) : null;
                // An open session still holds the table; assume a sitting so a
                // later scan is not wrongly treated as a fresh party.
                $holdsUntil = $closed ?? $start->addHours(2);

                if ($current === null || $start->gt($current['holdsUntil'])) {
                    if ($current !== null) {
                        $visits->push($current);
                    }

                    $current = [
                        'start' => $start,
                        'end' => $closed,
                        'holdsUntil' => $holdsUntil,
                        'guests' => 1,
                        'complete' => $closed !== null,
                    ];

                    continue;
                }

                $current['guests']++;

                if ($closed === null) {
                    $current['complete'] = false;
                } elseif ($current['end'] === null || $closed->gt($current['end'])) {
                    $current['end'] = $closed;
                }

                if ($holdsUntil->gt($current['holdsUntil'])) {
                    $current['holdsUntil'] = $holdsUntil;
                }
            }

            if ($current !== null) {
                $visits->push($current);
            }
        }

        return $visits;
    }

    private function guestStats(Vendor $vendor, AnalyticsPeriod $period, bool $previous = false): array
    {
        $visits = $previous
            ? $this->tableVisits($vendor, $period->previousQueryStart(), $period->previousQueryEnd())
            : $this->tableVisits($vendor, $period->queryStart(), $period->queryEnd());

        if ($visits->isEmpty()) {
            return ['guests' => 0, 'visits' => 0, 'average' => null];
        }

        $guests = (int) $visits->sum('guests');

        return [
            'guests' => $guests,
            'visits' => $visits->count(),
            'average' => round($guests / $visits->count(), 1),
        ];
    }

    // ------------------------------------------------------------------ trend

    private function trend(AnalyticsPeriod $period, Collection $orders): array
    {
        $revenue = array_fill(0, count($period->buckets), 0.0);
        $tips = array_fill(0, count($period->buckets), 0.0);
        $counts = array_fill(0, count($period->buckets), 0);

        foreach ($orders as $order) {
            $i = $period->bucketIndexFor($order->created_at);

            if ($i === null) {
                continue;
            }

            $counts[$i]++;

            if ($order->payment_received) {
                $revenue[$i] += (float) $order->amount;
                $tips[$i] += (float) $order->tip_amount;
            }
        }

        return collect($period->buckets)->map(fn ($bucket, $i) => [
            'label' => $bucket['label'],
            'revenue' => round($revenue[$i], 2),
            'tips' => round($tips[$i], 2),
            'orders' => $counts[$i],
        ])->all();
    }

    // ------------------------------------------------------------------- menu

    /**
     * Grouped by product_uid so every version of an item rolls up together,
     * but priced from the version each line actually points at.
     */
    /**
     * Every item currently on the menu, not just the ones that sold this
     * period — a vendor scanning for what to fix needs to see the zero rows
     * too, not just the winners that happened to have cart lines. Soft-deleted
     * items are excluded (MenuItem uses SoftDeletes), since a removed item
     * isn't something to act on any more.
     */
    private function menu(Vendor $vendor, AnalyticsPeriod $period, Collection $lines, Collection $previousLines): array
    {
        $current = $this->aggregateLines($lines);
        $previous = $this->aggregateLines($previousLines, includeCategory: false);
        $costByUid = $this->costByProductUid($lines);

        $allItems = MenuItem::where('vendor_id', $vendor->id)
            ->with('category.masterCategory')
            ->get(['id', 'product_uid', 'name', 'menu_category_id', 'available']);

        // Real "someone opened this dish" signal, not a proxy — MenuItemView is
        // written by the customer-facing item-detail endpoint on every open.
        $viewsByItemId = MenuItemView::where('vendor_id', $vendor->id)
            ->whereIn('menu_item_id', $allItems->pluck('id'))
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->selectRaw('menu_item_id, count(*) as views')
            ->groupBy('menu_item_id')
            ->pluck('views', 'menu_item_id');

        // Whether an item has a recipe configured at all — distinguishes "this
        // item genuinely costs nothing to make" (impossible) from "no recipe/
        // inventory tracking set up yet", so menu profitability never shows a
        // dish as 100% margin just because nobody configured its ingredients.
        $itemsWithRecipes = MenuItemIngredient::whereIn('menu_item_id', $allItems->pluck('id'))
            ->distinct()
            ->pluck('menu_item_id')
            ->all();

        $rows = $allItems->mapWithKeys(function (MenuItem $item) use ($current, $previous, $viewsByItemId, $costByUid, $itemsWithRecipes) {
            $uid = $item->product_uid ?? ('item-'.$item->id);
            $row = $current[$uid] ?? [
                'name' => $item->name,
                'category' => $item->category?->name,
                'quantity' => 0,
                'revenue' => 0.0,
            ];
            $prevRevenue = $previous[$uid]['revenue'] ?? null;
            $cost = round((float) ($costByUid[$uid] ?? 0.0), 2);
            $profit = round($row['revenue'] - $cost, 2);

            return [$uid => [
                'productUid' => $uid,
                'name' => $row['name'],
                'category' => $row['category'],
                'available' => (bool) $item->available,
                'quantity' => $row['quantity'],
                'revenue' => round($row['revenue'], 2),
                'avgPrice' => $row['quantity'] ? round($row['revenue'] / $row['quantity'], 2) : null,
                'deltaPercent' => ($prevRevenue !== null && $prevRevenue > 0)
                    ? round((($row['revenue'] - $prevRevenue) / $prevRevenue) * 100, 1)
                    : null,
                'views' => (int) ($viewsByItemId[$item->id] ?? 0),
                'cost' => $cost,
                'profit' => $profit,
                'marginPercent' => $row['revenue'] > 0 ? round(($profit / $row['revenue']) * 100, 1) : null,
                'hasCostData' => in_array($item->id, $itemsWithRecipes, true),
            ]];
        });

        // A cart line can outlive the menu item it was ordered from (price
        // changes create a new product_uid, or the item gets deleted after
        // selling) — those still belong in the period's sales history even
        // though there's no current MenuItem row to hang them off any more.
        foreach ($current as $uid => $row) {
            if ($rows->has($uid)) {
                continue;
            }

            $prevRevenue = $previous[$uid]['revenue'] ?? null;
            $cost = round((float) ($costByUid[$uid] ?? 0.0), 2);
            $profit = round($row['revenue'] - $cost, 2);
            $rows[$uid] = [
                'productUid' => $uid,
                'name' => $row['name'],
                'category' => $row['category'],
                'available' => null,
                'quantity' => $row['quantity'],
                'revenue' => round($row['revenue'], 2),
                'avgPrice' => $row['quantity'] ? round($row['revenue'] / $row['quantity'], 2) : null,
                'deltaPercent' => ($prevRevenue !== null && $prevRevenue > 0)
                    ? round((($row['revenue'] - $prevRevenue) / $prevRevenue) * 100, 1)
                    : null,
                // No current MenuItem row to key a view count off — genuinely
                // unknown rather than zero, but there's nothing else to show.
                'views' => 0,
                'cost' => $cost,
                'profit' => $profit,
                'marginPercent' => $row['revenue'] > 0 ? round(($profit / $row['revenue']) * 100, 1) : null,
                // No current MenuItem row to check for a configured recipe —
                // fall back to "did we actually record any cost movement".
                'hasCostData' => $cost > 0,
            ];
        }

        return $rows->sortByDesc('revenue')->values()->all();
    }

    /**
     * How often each menu item's availability flipped to unavailable this
     * period, read from VendorActivity — the ledger MenuItemController now
     * writes to on every toggle. Silent for a vendor whose items simply
     * haven't sold out, not zero-filled to look like a measurement.
     */
    private function soldOutFrequency(Vendor $vendor, AnalyticsPeriod $period): array
    {
        $events = VendorActivity::where('vendor_id', $vendor->id)
            ->where('event_type', 'menu_item.availability_toggled')
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->get(['metadata', 'created_at']);

        $wentUnavailable = $events->filter(fn ($e) => ($e->metadata['to'] ?? null) === false);

        if ($wentUnavailable->isEmpty()) {
            return [];
        }

        return $wentUnavailable
            ->groupBy(fn ($e) => $e->metadata['menu_item_id'] ?? 'unknown')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'menuItemId' => $first->metadata['menu_item_id'] ?? null,
                    'name' => $first->metadata['menu_item_name'] ?? 'Unknown item',
                    'count' => $group->count(),
                    'lastToggledAt' => $group->max('created_at'),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * Quantity and revenue need different treatment now that a shared item
     * can appear more than once in `$lines` (once per order attributed a
     * share of it — see linesFor()). Quantity is a physical count: a shared
     * dish was still one unit served regardless of how many people split the
     * bill for it, so it's counted once per real cart item (deduped by id).
     * Revenue is a monetary split: each occurrence's gross is divided by its
     * own share count before summing, so the total reconciles with
     * `summary.grossRevenue` instead of counting a shared item's full price
     * once per sharer.
     */
    private function aggregateLines(Collection $lines, bool $includeCategory = true): array
    {
        $out = [];

        foreach ($lines->unique('id') as $line) {
            $item = $line->menuItem;

            if (! $item) {
                continue;
            }

            $uid = $item->product_uid ?? ('item-'.$item->id);
            $out[$uid] ??= [
                'name' => $item->name,
                // $includeCategory is false for menu()'s previous-period call —
                // its map is only ever read for ['revenue'], and the category
                // relation chain isn't eager-loaded on those rows (see
                // linesFor()'s $withCatalogRelations), so touching it here
                // would trigger a lazy-load per item instead of just returning
                // an unused null.
                'category' => $includeCategory ? $item->category?->name : null,
                'quantity' => 0,
                'revenue' => 0.0,
            ];

            $out[$uid]['quantity'] += (int) $line->quantity;
        }

        foreach ($lines as $line) {
            $item = $line->menuItem;

            if (! $item) {
                continue;
            }

            $uid = $item->product_uid ?? ('item-'.$item->id);
            $shareCount = 1 + count($line->shared_order_ids ?? []);
            $out[$uid]['revenue'] += TaxCalculationService::cartItemLineTotalGross($line, $this->country) / $shareCount;
        }

        return $out;
    }

    /**
     * Real per-item cost of goods sold — the same inventory_stock_movements
     * (type='order') × cost_per_unit valuation FinancialReportService's
     * costOfGoodsSold() uses in aggregate, here broken down per menu item via
     * each movement's cart_item_id (InventoryConsumptionService links every
     * deduction back to the exact cart line it came from). Items without
     * recipe/inventory tracking configured simply have no movements and
     * contribute 0 — flagged via hasCostData in menu() above so the frontend
     * never shows a misleadingly perfect margin for an untracked dish.
     *
     * Deduped by cart-item id first (`$lines` may now hold the same shared
     * item twice — once per order attributed a share of it, see linesFor())
     * — the inventory deduction itself happened exactly once regardless of
     * how many orders share the dish, so attributing it once per unique
     * cart item avoids double-counting the same cost.
     *
     * @return array<string, float> productUid => total cost
     */
    private function costByProductUid(Collection $lines): array
    {
        if ($lines->isEmpty()) {
            return [];
        }

        $uniqueLines = $lines->unique('id');

        // Chunked for the same reason as linesFor()'s owned-items query above —
        // this is the call that actually crashed in a realistic 3-year-scale
        // stress test (a "monthly" request's unique cart-item count exceeded
        // SQLite's bound-parameter ceiling).
        $movements = collect();
        foreach ($uniqueLines->pluck('id')->chunk(1000) as $cartItemIdChunk) {
            $movements = $movements->concat(
                InventoryStockMovement::query()
                    ->where('type', 'order')
                    ->whereIn('cart_item_id', $cartItemIdChunk)
                    ->with('inventoryItem:id,cost_per_unit')
                    ->get()
            );
        }

        $costByCartItemId = $movements
            ->groupBy('cart_item_id')
            ->map(fn (Collection $group) => $group->sum(
                fn ($movement) => abs((float) $movement->quantity_change) * (float) ($movement->inventoryItem?->cost_per_unit ?? 0)
            ));

        $out = [];
        foreach ($uniqueLines as $line) {
            $item = $line->menuItem;
            if (! $item) {
                continue;
            }

            $uid = $item->product_uid ?? ('item-'.$item->id);
            $out[$uid] = ($out[$uid] ?? 0.0) + (float) ($costByCartItemId[$line->id] ?? 0.0);
        }

        return $out;
    }

    private function categories(Collection $lines): array
    {
        $out = [];

        // Same quantity/revenue split as aggregateLines() — see its comment.
        foreach ($lines->unique('id') as $line) {
            $item = $line->menuItem;

            if (! $item) {
                continue;
            }

            $name = $item->category?->name ?? 'Uncategorised';
            $out[$name] ??= ['name' => $name, 'quantity' => 0, 'revenue' => 0.0];
            $out[$name]['quantity'] += (int) $line->quantity;
        }

        foreach ($lines as $line) {
            $item = $line->menuItem;

            if (! $item) {
                continue;
            }

            $name = $item->category?->name ?? 'Uncategorised';
            $shareCount = 1 + count($line->shared_order_ids ?? []);
            $out[$name]['revenue'] += TaxCalculationService::cartItemLineTotalGross($line, $this->country) / $shareCount;
        }

        return collect($out)
            ->map(fn ($row) => [...$row, 'revenue' => round($row['revenue'], 2)])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    // -------------------------------------------------------------- discounts

    /**
     * Historically accurate because a discount change forces a new MenuItem
     * version (see MenuItemController::hasVersionedFieldChanged), and each cart
     * line points at the version that was live when it was ordered.
     */
    private function discounts(Collection $paidOrders, Collection $lines): array
    {
        $discountedOrderIds = [];
        $discountedRevenue = 0.0;
        $listRevenue = 0.0;
        $depths = [];

        foreach ($lines as $line) {
            $item = $line->menuItem;

            if (! $item || ! $item->has_discount || $item->discounted_price === null) {
                continue;
            }

            // Same sharing divisor as aggregateLines()/categories() — a
            // shared discounted line must have its actual charge split
            // across its sharers, or it's double-counted (see linesFor()).
            $shareCount = 1 + count($line->shared_order_ids ?? []);
            $discountedOrderIds[$line->attributedOrderId ?? $line->order_id] = true;

            $vat = TaxCalculationService::itemVatRate($item, $this->country);
            $soldGross = (TaxCalculationService::gross((float) $item->discounted_price, $vat) * (int) $line->quantity) / $shareCount;
            $listGross = (TaxCalculationService::gross((float) $item->price, $vat) * (int) $line->quantity) / $shareCount;

            $discountedRevenue += $soldGross;
            $listRevenue += $listGross;
            $depths[] = (float) $item->discount_percent;
        }

        $discountedCount = count($discountedOrderIds);
        $totalOrders = $paidOrders->count();

        if ($totalOrders === 0) {
            return ['available' => false];
        }

        $itemsByOrder = $lines->groupBy(fn (CartItem $l) => $l->attributedOrderId ?? $l->order_id)
            ->map(fn ($g) => $g->sum('quantity'));
        $discountedItems = collect($discountedOrderIds)->keys()
            ->map(fn ($id) => $itemsByOrder[$id] ?? 0);
        $fullItems = $itemsByOrder->reject(fn ($v, $id) => isset($discountedOrderIds[$id]));

        return [
            'available' => true,
            'discountedOrders' => $discountedCount,
            'fullPriceOrders' => $totalOrders - $discountedCount,
            'discountedShare' => round(($discountedCount / $totalOrders) * 100, 1),
            'discountedRevenue' => round($discountedRevenue, 2),
            'revenueForgone' => round($listRevenue - $discountedRevenue, 2),
            'averageDepth' => $depths ? round(array_sum($depths) / count($depths), 1) : null,
            'discountedAvgItems' => $discountedItems->count() ? round($discountedItems->avg(), 1) : null,
            'fullPriceAvgItems' => $fullItems->count() ? round($fullItems->avg(), 1) : null,
        ];
    }

    // --------------------------------------------------------------- payments

    private function payments(Vendor $vendor, AnalyticsPeriod $period, Collection $orders): array
    {
        $paid = $orders->where('payment_received', true);

        // "Stripe" is the processor, not what the guest actually paid with —
        // real card brand / wallet type live on OrderPayment.payment_method_details
        // (backfilled from Stripe on first read by PaymentMethodDetailsService,
        // the same service the receipt/checkout flow already uses). Not every
        // paid order has a matching OrderPayment row (older/imported orders
        // were written straight to Order.payment_received without one) —
        // details() accepts a null payment and falls back to the plain
        // processor label in that case, so nothing drops off the breakdown.
        // Chunked at 1,000 order IDs per query — same unbounded-whereIn failure
        // class fixed elsewhere in this file, hit here on a vendor with a large
        // paid-order volume in the window. concat() (not merge()) — Eloquent's
        // merge() dedupes by primary key, which is safe here since `id` is
        // selected and genuinely unique, but concat()-then-keyBy() avoids
        // relying on that and matches this file's established convention.
        $paymentsByOrderId = collect();
        foreach ($paid->pluck('id')->chunk(1000) as $orderIdChunk) {
            $paymentsByOrderId = $paymentsByOrderId->concat(
                OrderPayment::query()
                    ->where('vendor_id', $vendor->id)
                    ->whereIn('order_id', $orderIdChunk)
                    ->get(['id', 'order_id', 'payment_method', 'payment_method_details', 'stripe_payment_intent_id'])
            );
        }
        $paymentsByOrderId = $paymentsByOrderId->keyBy('order_id');

        $methods = $paid
            ->groupBy(function ($order) use ($paymentsByOrderId) {
                $fallback = $order->payment_method ?: 'unknown';

                return $this->paymentDetails->vendorDetails($paymentsByOrderId->get($order->id), $fallback)['method'] ?: $fallback;
            })
            ->map(fn ($group, $method) => [
                'method' => $method,
                'orders' => $group->count(),
                'revenue' => round((float) $group->sum('amount'), 2),
                'share' => $paid->count() ? round(($group->count() / $paid->count()) * 100, 1) : 0,
            ])
            ->sortByDesc('orders')
            ->values()
            ->all();

        $attempts = OrderPayment::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->get(['order_id', 'status', 'paid_at', 'created_at']);

        $succeeded = $attempts->whereIn('status', ['succeeded', 'paid', 'completed'])->count();
        $failed = $attempts->where('status', 'failed')->count();
        $settled = $succeeded + $failed;

        // Checkout duration is the payment attempt itself: intent created to
        // captured. Measuring from order confirmation instead would span the
        // whole meal on a dine-in bill and simply restate orderToPaymentMinutes.
        $durations = $attempts
            ->filter(fn ($p) => $p->paid_at && $p->created_at)
            ->map(fn ($p) => CarbonImmutable::parse($p->created_at)->diffInSeconds(CarbonImmutable::parse($p->paid_at)))
            ->filter(fn ($s) => $s >= 0);

        return [
            'methods' => $methods,
            'attempts' => $attempts->count(),
            'completionRate' => $settled ? round(($succeeded / $settled) * 100, 1) : null,
            'failureRate' => $settled ? round(($failed / $settled) * 100, 1) : null,
            'failedCount' => $failed,
            'medianCheckoutSeconds' => $durations->count() ? $this->median($durations->values()->all()) : null,
            'tipTotal' => round((float) $paid->sum('tip_amount'), 2),
            'tipAverage' => $paid->count() ? round((float) $paid->sum('tip_amount') / $paid->count(), 2) : null,
            'tipRate' => $paid->sum('amount') > 0
                ? round(((float) $paid->sum('tip_amount') / (float) $paid->sum('amount')) * 100, 1)
                : null,
        ];
    }

    // ---------------------------------------------------------------- service

    /**
     * Movement smaller than this is treated as noise rather than a change.
     * It is a reporting threshold, not a quality target — there is no
     * industry benchmark behind any figure in this block, so every judgement
     * here is the venue measured against its own previous window.
     */
    private const SERVICE_NOISE_FLOOR = 5.0;

    /**
     * At least this many completed buckets must carry data before a baseline is
     * offered. One prior week is a coin toss, not a normal.
     */
    private const MIN_BASELINE_SAMPLES = 2;

    /** A member who hasn't ordered in this many days is treated as gone quiet. */
    private const LOYALTY_INACTIVITY_DAYS = 30;

    /** Basket-depth movement smaller than this is noise, not a real difference. */
    private const BASKET_DEPTH_NOISE_FLOOR = 10.0;

    /** Visit-rate movement smaller than this, per member, is noise. */
    private const VISIT_RATE_NOISE_FLOOR = 10.0;

    /**
     * A before/after window shorter than this isn't long enough to say
     * anything — a handful of days either side of joining is not a pace.
     */
    private const LOYALTY_MIN_WINDOW_DAYS = 14;

    /** Fewer qualifying members than this and the before/after comparison stays silent. */
    private const LOYALTY_BEFORE_AFTER_MIN_SAMPLE = 10;

    /** Fewer identified customers than this on either side and the this-period comparison stays silent. */
    private const LOYALTY_VISIT_MIN_SAMPLE = 10;

    private const SERVICE_KEYS = [
        'tableTurnoverMinutes',
        'orderToKitchenMinutes',
        'kitchenToServedMinutes',
        'orderToPaymentMinutes',
        'lunchMinutes',
        'dinnerMinutes',
    ];

    /**
     * Service timings, each compared against a rolling baseline: the venue's
     * own average for that stage across the completed buckets behind the
     * current window.
     *
     * A single previous window is a poor yardstick — one unusually bad week
     * makes the next look excellent. Averaging across several is steadier, and
     * it grows more reliable on its own as a venue accumulates history: nothing
     * to compare against in the first period, then a two-week average, and so
     * on up to the lookback cap.
     */
    private function service(Vendor $vendor, AnalyticsPeriod $period, Collection $orders): array
    {
        $current = $this->serviceTimings(
            $vendor,
            $period,
            $orders,
            $period->queryStart(),
            $period->queryEnd(),
        );

        // One fetch across the whole lookback, then sliced per bucket, rather
        // than a query per bucket.
        $history = $this->ordersIn($vendor, $period->baselineQueryStart(), $period->queryStart()->subSecond());
        $historyVisits = $this->tableVisits($vendor, $period->baselineQueryStart(), $period->queryStart()->subSecond());

        $samples = array_fill_keys(self::SERVICE_KEYS, []);

        foreach ($period->baselineBuckets() as $bucket) {
            $from = $bucket['start']->setTimezone(config('app.timezone'));
            $to = $bucket['end']->setTimezone(config('app.timezone'));

            $bucketOrders = $history->filter(
                fn ($o) => CarbonImmutable::parse($o->created_at)->betweenIncluded($from, $to)
            );

            $bucketVisits = $historyVisits->filter(
                fn ($visit) => $visit['start']->setTimezone(config('app.timezone'))->betweenIncluded($from, $to)
            );

            if ($bucketOrders->isEmpty() && $bucketVisits->isEmpty()) {
                continue;
            }

            $measured = $this->serviceTimings($vendor, $period, $bucketOrders, $from, $to, $bucketVisits);

            foreach (self::SERVICE_KEYS as $key) {
                if ($measured[$key] !== null) {
                    $samples[$key][] = $measured[$key];
                }
            }
        }

        $block = [
            'tableVisitsMeasured' => $current['tableVisitsMeasured'],
            'baselineUnit' => $period->baselineUnitLabel(),
        ];

        foreach (self::SERVICE_KEYS as $key) {
            $values = $samples[$key];
            $sampleCount = count($values);
            $baseline = $sampleCount >= self::MIN_BASELINE_SAMPLES
                ? round(array_sum($values) / $sampleCount, 1)
                : null;

            $value = $current[$key];

            $block[$key] = [
                'value' => $value,
                'baseline' => $baseline,
                'baselineSamples' => $sampleCount,
                'delta' => ($value !== null && $baseline !== null) ? round($value - $baseline, 1) : null,
                'status' => $this->durationStatus($value, $baseline),
            ];
        }

        return $block;
    }

    /**
     * @param  Collection|null  $visits  pre-sliced visits, when measuring a baseline bucket
     * @return array<string, float|int|null>
     */
    private function serviceTimings(
        Vendor $vendor,
        AnalyticsPeriod $period,
        Collection $orders,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?Collection $visits = null,
    ): array {
        $stage = function (string $fromField, string $toField) use ($orders) {
            $values = $orders
                ->filter(fn ($o) => $o->{$fromField} && $o->{$toField})
                ->map(fn ($o) => CarbonImmutable::parse($o->{$fromField})->diffInSeconds(CarbonImmutable::parse($o->{$toField})) / 60)
                ->filter(fn ($m) => $m >= 0);

            return $values->count() ? round($this->median($values->values()->all()), 1) : null;
        };

        // Turnover is how long the TABLE was held, from the first guest scanning
        // to the last one closing — not how long one guest's session lasted.
        $completeVisits = ($visits ?? $this->tableVisits($vendor, $from, $to))
            ->filter(fn ($visit) => $visit['complete'] && $visit['end'] !== null);

        $turnover = $completeVisits
            ->map(fn ($visit) => $visit['start']->diffInSeconds($visit['end']) / 60)
            ->filter(fn ($minutes) => $minutes > 0);

        $byDaypart = fn (array $hours) => $this->medianOrNull(
            $orders->filter(function ($o) use ($hours, $period) {
                $h = (int) CarbonImmutable::parse($o->created_at)->setTimezone($period->timezone)->format('G');

                return $h >= $hours[0] && $h < $hours[1];
            })->filter(fn ($o) => $o->confirmed_at && $o->served_at)
                ->map(fn ($o) => CarbonImmutable::parse($o->confirmed_at)->diffInSeconds(CarbonImmutable::parse($o->served_at)) / 60)
                ->values()->all()
        );

        return [
            'tableTurnoverMinutes' => $turnover->count() ? round($this->median($turnover->values()->all()), 1) : null,
            'tableVisitsMeasured' => $turnover->count(),
            'orderToKitchenMinutes' => $stage('confirmed_at', 'in_progress_at'),
            'kitchenToServedMinutes' => $stage('in_progress_at', 'served_at'),
            'orderToPaymentMinutes' => $stage('confirmed_at', 'payment_confirmed_at'),
            'lunchMinutes' => $byDaypart([11, 15]),
            'dinnerMinutes' => $byDaypart([18, 23]),
        ];
    }

    /** faster | steady | slower, or null when there is no baseline yet. */
    private function durationStatus(?float $current, ?float $baseline): ?string
    {
        if ($current === null || $baseline === null || $baseline <= 0.0) {
            return null;
        }

        $changePercent = (($current - $baseline) / $baseline) * 100;

        return match (true) {
            $changePercent <= -self::SERVICE_NOISE_FLOOR => 'faster',
            $changePercent >= self::SERVICE_NOISE_FLOOR => 'slower',
            default => 'steady',
        };
    }


    // ------------------------------------------------------------------- tips

    /**
     * Tips across every paid order, cash included.
     *
     * Cash tips do reach the system: a guest can enter one when requesting cash
     * payment (PaymentController::requestCash), and a waiter can enter or adjust
     * it when confirming the payment (OrderController::confirmCashPayment). The
     * only case Tavlo cannot see is a note handed over at the table that nobody
     * types in — which is precisely why cash and digital are reported side by
     * side. A large gap between them is more likely a recording habit than a
     * difference in generosity, and that is something a vendor can fix.
     */
    private function tips(Collection $orders, Collection $previousOrders, AnalyticsPeriod $period): array
    {
        $paid = $orders->where('payment_received', true);
        $paidPrevious = $previousOrders->where('payment_received', true);

        if ($paid->isEmpty()) {
            return ['available' => false, 'total' => 0.0];
        }

        $tipped = $paid->filter(fn ($o) => (float) $o->tip_amount > 0);
        $total = round((float) $paid->sum('tip_amount'), 2);
        $orderValue = (float) $paid->sum('amount');

        return [
            'available' => true,
            'total' => $total,
            'paidOrders' => $paid->count(),
            'tippedOrders' => $tipped->count(),

            'participationRate' => $this->metric(
                round(($tipped->count() / $paid->count()) * 100, 1),
                $this->participation($paidPrevious),
                'absolute',
            ),

            // Two averages, because one on its own misleads: the overall figure
            // is dragged down by every guest who left nothing, so a venue where
            // few tip generously looks the same as one where everybody tips a
            // little.
            'averageTip' => round($total / $paid->count(), 2),
            'averageWhenTipped' => $tipped->count() ? round($total / $tipped->count(), 2) : null,

            'tipRate' => $this->metric(
                $orderValue > 0 ? round(($total / $orderValue) * 100, 1) : null,
                $this->tipRateOf($paidPrevious),
                'absolute',
            ),

            'byPaymentMethod' => $this->tipsGrouped($paid, fn ($o) => $o->payment_method ?: 'unknown', 'method'),
            'byChannel' => $this->tipsGrouped($paid, fn ($o) => $o->order_type ?: 'unknown', 'type'),
            'byServiceSpeed' => $this->tipsByServiceSpeed($paid),
            'cashCapture' => $this->cashCapture($paid),
        ];
    }

    private function isCash($order): bool
    {
        return strtolower((string) $order->payment_method) === 'cash';
    }

    private function participation(Collection $paid): ?float
    {
        if ($paid->isEmpty()) {
            return null;
        }

        $tipped = $paid->filter(fn ($o) => (float) $o->tip_amount > 0)->count();

        return round(($tipped / $paid->count()) * 100, 1);
    }

    private function tipRateOf(Collection $paid): ?float
    {
        $value = (float) $paid->sum('amount');

        return $value > 0 ? round(((float) $paid->sum('tip_amount') / $value) * 100, 1) : null;
    }

    /** @param  callable  $key  how to group; $labelField names the key in the output */
    private function tipsGrouped(Collection $paid, callable $key, string $labelField): array
    {
        return $paid->groupBy($key)
            ->map(function ($group, $label) use ($labelField) {
                $tipped = $group->filter(fn ($o) => (float) $o->tip_amount > 0);
                $value = (float) $group->sum('amount');

                return [
                    $labelField => $label,
                    'orders' => $group->count(),
                    'tippedOrders' => $tipped->count(),
                    'participationRate' => round(($tipped->count() / $group->count()) * 100, 1),
                    'averageTip' => round((float) $group->sum('tip_amount') / $group->count(), 2),
                    'tipRate' => $value > 0 ? round(((float) $group->sum('tip_amount') / $value) * 100, 1) : null,
                ];
            })
            ->sortByDesc('orders')
            ->values()
            ->all();
    }

    /**
     * How often a cash tip gets recorded, against the digital rate.
     *
     * A digital tip is captured by the checkout itself. A cash tip only exists
     * in the data if someone typed it in, so this comparison shows whether that
     * is happening — not whether cash guests are less generous.
     */
    private function cashCapture(Collection $paid): array
    {
        $cash = $paid->filter(fn ($o) => $this->isCash($o));
        $digital = $paid->reject(fn ($o) => $this->isCash($o));

        if ($cash->isEmpty() || $digital->isEmpty()) {
            return ['comparable' => false, 'cashOrders' => $cash->count()];
        }

        $cashParticipation = $this->participation($cash);
        $digitalParticipation = $this->participation($digital);

        return [
            'comparable' => true,
            'cashOrders' => $cash->count(),
            'cashTipped' => $cash->filter(fn ($o) => (float) $o->tip_amount > 0)->count(),
            'cashParticipation' => $cashParticipation,
            'digitalOrders' => $digital->count(),
            'digitalParticipation' => $digitalParticipation,
            'gap' => round($digitalParticipation - $cashParticipation, 1),
        ];
    }

    /**
     * Does getting food out faster earn better tips?
     *
     * Orders are split at their own median service time so the comparison is
     * like-for-like within this venue. It is a correlation, not proof of cause —
     * a quiet evening produces both quicker service and more relaxed guests —
     * but it is measured from real orders and it points somewhere useful.
     */
    private function tipsByServiceSpeed(Collection $paid): array
    {
        $served = $paid->filter(fn ($o) => $o->confirmed_at && $o->served_at);

        if ($served->count() < 20) {
            return [];
        }

        $withMinutes = $served->map(function ($o) {
            $minutes = CarbonImmutable::parse($o->confirmed_at)->diffInSeconds(CarbonImmutable::parse($o->served_at)) / 60;

            return ['order' => $o, 'minutes' => $minutes];
        })->filter(fn ($row) => $row['minutes'] >= 0);

        if ($withMinutes->count() < 20) {
            return [];
        }

        $median = $this->median($withMinutes->pluck('minutes')->values()->all());

        $summarise = function (Collection $rows, string $label) use ($median) {
            if ($rows->isEmpty()) {
                return null;
            }

            $orders = $rows->pluck('order');
            $tipped = $orders->filter(fn ($o) => (float) $o->tip_amount > 0);
            $value = (float) $orders->sum('amount');

            return [
                'label' => $label,
                'thresholdMinutes' => round($median, 1),
                'orders' => $orders->count(),
                'participationRate' => round(($tipped->count() / $orders->count()) * 100, 1),
                'averageTip' => round((float) $orders->sum('tip_amount') / $orders->count(), 2),
                'tipRate' => $value > 0 ? round(((float) $orders->sum('tip_amount') / $value) * 100, 1) : null,
            ];
        };

        return array_values(array_filter([
            $summarise($withMinutes->filter(fn ($r) => $r['minutes'] <= $median), 'Served faster than usual'),
            $summarise($withMinutes->filter(fn ($r) => $r['minutes'] > $median), 'Served slower than usual'),
        ]));
    }

    // --------------------------------------------------------------- channels

    private function channels(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        return $orders->groupBy(fn ($o) => $o->order_type ?: 'unknown')
            ->map(fn ($group, $type) => [
                'type' => $type,
                'orders' => $group->count(),
                'revenue' => round((float) $group->where('payment_received', true)->sum('amount'), 2),
                'share' => round(($group->count() / $orders->count()) * 100, 1),
            ])
            ->sortByDesc('orders')
            ->values()
            ->all();
    }

    private function cancellations(Vendor $vendor, AnalyticsPeriod $period): array
    {
        $cancelled = Order::query()
            ->where('vendor_id', $vendor->id)
            ->whereNotNull('confirmed_at')
            ->whereNotNull('cancelled_at')
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->get(['cancelled_reason']);

        $confirmed = $this->baseQuery($vendor)
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->count();

        $total = $confirmed + $cancelled->count();

        return [
            'count' => $cancelled->count(),
            'rate' => $total ? round(($cancelled->count() / $total) * 100, 1) : null,
            'reasons' => $cancelled
                ->groupBy(fn ($o) => $o->cancelled_reason ?: 'Not specified')
                ->map(fn ($g, $reason) => ['reason' => $reason, 'count' => $g->count()])
                ->sortByDesc('count')
                ->values()
                ->all(),
        ];
    }

    // ------------------------------------------------------------------- peak

    private function peak(AnalyticsPeriod $period, Collection $orders): array
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $grid = [];
        $byDay = array_fill_keys($days, 0);
        $revenueByDay = array_fill_keys($days, 0.0);

        foreach ($orders as $order) {
            $local = CarbonImmutable::parse($order->created_at)->setTimezone($period->timezone);
            $hour = (int) $local->format('G');
            $day = $days[(int) $local->dayOfWeekIso - 1];

            $grid[$hour][$day] = ($grid[$hour][$day] ?? 0) + 1;
            $byDay[$day]++;

            if ($order->payment_received) {
                $revenueByDay[$day] += (float) $order->amount;
            }
        }

        if ($grid === []) {
            return ['hours' => [], 'days' => [], 'busiest' => null, 'quietest' => null];
        }

        // Hours where the venue barely trades (a stray midnight order, a single
        // early delivery) are not soft slots worth acting on — they are closed
        // time. Only hours carrying a real share of the week count as trading,
        // so "quietest" means quiet within opening hours rather than at 4am.
        $hourTotals = [];

        foreach ($grid as $hour => $cellsForHour) {
            $hourTotals[$hour] = array_sum($cellsForHour);
        }

        $busiestHourTotal = $hourTotals ? max($hourTotals) : 0;
        $tradingFloor = $busiestHourTotal * 0.2;

        $hours = [];
        // Every slot matching the current best/worst is kept, not just the
        // first one found — a real tie (two hours both peaking at 4 orders)
        // is a genuinely different fact from one hour edging out the other,
        // and silently picking whichever the scan hits first misrepresents it.
        $quietestOrders = null;
        $quietestSlots = [];
        $busiestOrders = -1;
        $busiestSlots = [];

        for ($h = min(array_keys($grid)); $h <= max(array_keys($grid)); $h++) {
            $row = ['hour' => $h, 'label' => sprintf('%02d:00', $h), 'values' => []];
            $isTradingHour = ($hourTotals[$h] ?? 0) >= $tradingFloor && $tradingFloor > 0;

            foreach ($days as $day) {
                $count = $grid[$h][$day] ?? 0;
                $row['values'][$day] = $count;

                // A trading hour with zero orders on a given day is a real,
                // actionable "empty" slot — worth surfacing as quietest, not
                // just the smallest nonzero count. The trading-floor check
                // above already keeps closed hours (e.g. 4am) out of this.
                if ($isTradingHour) {
                    if ($quietestOrders === null || $count < $quietestOrders) {
                        $quietestOrders = $count;
                        $quietestSlots = [['day' => $day, 'hour' => $h]];
                    } elseif ($count === $quietestOrders) {
                        $quietestSlots[] = ['day' => $day, 'hour' => $h];
                    }
                }

                if ($count > $busiestOrders) {
                    $busiestOrders = $count;
                    $busiestSlots = [['day' => $day, 'hour' => $h]];
                } elseif ($count === $busiestOrders && $count > 0) {
                    $busiestSlots[] = ['day' => $day, 'hour' => $h];
                }
            }

            $row['trading'] = $isTradingHour;
            $hours[] = $row;
        }

        return [
            'hours' => $hours,
            'days' => collect($days)->map(fn ($d) => [
                'day' => $d,
                'orders' => $byDay[$d],
                'revenue' => round($revenueByDay[$d], 2),
            ])->all(),
            'busiest' => $busiestSlots !== [] ? [
                'day' => $busiestSlots[0]['day'],
                'hour' => $busiestSlots[0]['hour'],
                'orders' => $busiestOrders,
                'tiedSlots' => $busiestSlots,
            ] : null,
            'quietest' => $quietestSlots !== [] ? [
                'day' => $quietestSlots[0]['day'],
                'hour' => $quietestSlots[0]['hour'],
                'orders' => $quietestOrders,
                'tiedSlots' => $quietestSlots,
            ] : null,
        ];
    }

    // -------------------------------------------------------------- customers

    private function customers(Vendor $vendor, Collection $orders): array
    {
        $paid = $orders->where('payment_received', true)->whereNotNull('customer_id');
        $totalPaidRevenue = round((float) $orders->where('payment_received', true)->sum('amount'), 2);

        if ($paid->isEmpty()) {
            return ['available' => false, 'attributedRevenue' => 0.0, 'totalRevenue' => $totalPaidRevenue];
        }

        $spendByCustomer = $paid->groupBy('customer_id')
            ->map(fn ($group) => [
                'orders' => $group->count(),
                'spend' => round((float) $group->sum('amount'), 2),
            ])
            ->sortByDesc('spend');

        $attributed = round($spendByCustomer->sum('spend'), 2);
        $topCount = max(1, (int) ceil($spendByCustomer->count() * 0.2));
        $topSpend = $spendByCustomer->take($topCount)->sum('spend');

        $segments = [
            ['label' => '1 visit', 'min' => 1, 'max' => 1],
            ['label' => '2–3 visits', 'min' => 2, 'max' => 3],
            ['label' => '4+ visits', 'min' => 4, 'max' => PHP_INT_MAX],
        ];

        return [
            'available' => true,
            'identifiedCustomers' => $spendByCustomer->count(),
            'attributedRevenue' => $attributed,
            'totalRevenue' => $totalPaidRevenue,
            'attributedShare' => $totalPaidRevenue > 0 ? round(($attributed / $totalPaidRevenue) * 100, 1) : null,
            'topQuintileShare' => $attributed > 0 ? round(($topSpend / $attributed) * 100, 1) : null,
            'topQuintileCount' => $topCount,
            'segments' => collect($segments)->map(function ($segment) use ($spendByCustomer) {
                $bucket = $spendByCustomer->filter(
                    fn ($c) => $c['orders'] >= $segment['min'] && $c['orders'] <= $segment['max']
                );

                return [
                    'label' => $segment['label'],
                    'customers' => $bucket->count(),
                    'revenue' => round($bucket->sum('spend'), 2),
                    'averageSpend' => $bucket->count() ? round($bucket->sum('spend') / $bucket->count(), 2) : null,
                ];
            })->all(),
        ];
    }

    /**
     * Scoped by `date` (the dining date the guest booked), not `created_at`
     * (when the booking was made) — the same "when did the thing actually
     * happen" convention every other section here uses for orders. `date`
     * is filtered against `$period->start`/`$period->end` (vendor-local
     * Carbon instances), not `queryStart()`/`queryEnd()` (app-timezone) —
     * `date` is a pure calendar date with no time-of-day meaning, so
     * converting it through a timezone before comparing risks shifting it
     * into the wrong day entirely.
     *
     * `noShowRate` divides by completed+no_show only ("of reservations
     * that reached their date without being cancelled beforehand, how many
     * were no-shows") — a cancellation is a guest proactively freeing the
     * table, not a failure to show up, so folding it into the same
     * denominator would understate the real no-show problem.
     */
    private function reservations(Vendor $vendor, AnalyticsPeriod $period): array
    {
        $reservations = Reservation::where('vendor_id', $vendor->id)
            ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
            ->get(['id', 'date', 'time', 'party_size', 'status', 'created_at']);

        if ($reservations->isEmpty()) {
            return ['available' => false];
        }

        $byStatus = $reservations->countBy('status');
        $total = $reservations->count();
        $completed = (int) ($byStatus['completed'] ?? 0);
        $noShow = (int) ($byStatus['no_show'] ?? 0);
        $cancelled = (int) ($byStatus['cancelled'] ?? 0);
        $kept = $completed + $noShow;

        $leadTimeDays = $reservations->map(
            fn (Reservation $r) => $r->created_at->copy()->startOfDay()->diffInDays($r->date)
        );

        // Volume per bucket — plain calendar-date string comparison, not
        // AnalyticsPeriod::bucketIndexFor() (built for real timestamps that
        // genuinely need timezone conversion); see the class doc above for
        // why `date` shouldn't go through that same conversion.
        $volume = array_fill(0, count($period->buckets), 0);
        foreach ($reservations as $reservation) {
            $dateStr = $reservation->date->toDateString();

            foreach ($period->buckets as $i => $bucket) {
                if ($dateStr >= $bucket['start']->toDateString() && $dateStr <= $bucket['end']->toDateString()) {
                    $volume[$i]++;

                    break;
                }
            }
        }

        return [
            'available' => true,
            'total' => $total,
            'byStatus' => [
                'pending' => (int) ($byStatus['pending'] ?? 0),
                'confirmed' => (int) ($byStatus['confirmed'] ?? 0),
                'completed' => $completed,
                'cancelled' => $cancelled,
                'noShow' => $noShow,
            ],
            'noShowRate' => $kept > 0 ? round(($noShow / $kept) * 100, 1) : null,
            'cancellationRate' => round(($cancelled / $total) * 100, 1),
            'averagePartySize' => round((float) $reservations->avg('party_size'), 1),
            'averageLeadTimeDays' => round((float) $leadTimeDays->avg(), 1),
            'volume' => collect($period->buckets)->map(fn ($bucket, $i) => [
                'label' => $bucket['label'],
                'count' => $volume[$i],
            ])->all(),
        ];
    }

    private function retention(Vendor $vendor, AnalyticsPeriod $period): array
    {
        $firstOrders = Order::query()
            ->where('vendor_id', $vendor->id)
            ->whereNotNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, MIN(created_at) as first_at')
            ->groupBy('customer_id')
            ->havingRaw('MIN(created_at) BETWEEN ? AND ?', [$period->queryStart(), $period->queryEnd()])
            ->pluck('first_at', 'customer_id');

        if ($firstOrders->isEmpty()) {
            return ['available' => false, 'newCustomers' => 0];
        }

        // Chunked at 1,000 customer IDs — same unbounded-whereIn failure class
        // fixed elsewhere in this file. concat(), not merge() — Eloquent's
        // merge() dedupes by primary key, and `id` isn't selected here, which
        // would collapse every row to one per chunk if merge() were used.
        $subsequent = collect();
        foreach ($firstOrders->keys()->chunk(1000) as $customerIdChunk) {
            $subsequent = $subsequent->concat(
                Order::query()
                    ->where('vendor_id', $vendor->id)
                    ->whereNotNull('confirmed_at')
                    ->whereNull('cancelled_at')
                    ->whereIn('customer_id', $customerIdChunk)
                    ->get(['customer_id', 'created_at'])
            );
        }
        $subsequent = $subsequent->groupBy('customer_id');

        $within30 = 0;
        $within60 = 0;
        $threePlus = 0;
        $gaps = [];

        foreach ($firstOrders as $customerId => $firstAt) {
            $first = CarbonImmutable::parse($firstAt);
            $later = ($subsequent[$customerId] ?? collect())
                ->filter(fn ($o) => CarbonImmutable::parse($o->created_at)->gt($first))
                ->sortBy('created_at');

            if ($later->isEmpty()) {
                continue;
            }

            $next = CarbonImmutable::parse($later->first()->created_at);
            $days = $first->diffInDays($next);

            $gaps[] = $days;

            if ($days <= 30) {
                $within30++;
            }

            if ($days <= 60) {
                $within60++;
            }

            if ($later->count() >= 2) {
                $threePlus++;
            }
        }

        $new = $firstOrders->count();

        return [
            'available' => true,
            'newCustomers' => $new,
            'returnedWithin30' => $within30,
            'returnedWithin60' => $within60,
            'repeatThreePlus' => $threePlus,
            'return30Rate' => round(($within30 / $new) * 100, 1),
            'return60Rate' => round(($within60 / $new) * 100, 1),
            'averageDaysToReturn' => $gaps ? round(array_sum($gaps) / count($gaps), 1) : null,
        ];
    }

    // ---------------------------------------------------------------- reviews

    private function reviews(Vendor $vendor, AnalyticsPeriod $period, Collection $orders): array
    {
        $inPeriod = Review::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->get(['rating', 'created_at']);

        $unanswered = Review::query()
            ->where('vendor_id', $vendor->id)
            ->whereNull('vendor_reply')
            ->count();

        $unansweredLow = Review::query()
            ->where('vendor_id', $vendor->id)
            ->whereNull('vendor_reply')
            ->where('rating', '<=', 3)
            ->count();

        $distribution = [];

        for ($star = 5; $star >= 1; $star--) {
            $count = $inPeriod->where('rating', $star)->count();
            $distribution[] = [
                'rating' => $star,
                'count' => $count,
                'share' => $inPeriod->count() ? round(($count / $inPeriod->count()) * 100, 1) : 0,
            ];
        }

        return [
            'count' => $inPeriod->count(),
            'averageRating' => $inPeriod->count() ? round($inPeriod->avg('rating'), 1) : null,
            'perHundredOrders' => $orders->count()
                ? round(($inPeriod->count() / $orders->count()) * 100, 1)
                : null,
            'distribution' => $distribution,
            'unanswered' => $unanswered,
            'unansweredCritical' => $unansweredLow,
        ];
    }

    // -------------------------------------------------------------- inventory

    /**
     * Read-only analysis over the Inventory package. Usage and waste are read
     * straight from InventoryStockMovement — the real, transaction-locked
     * ledger the backend now writes to on every completed order (type=order)
     * and every manual adjustment (type=delivery/waste/correction) — so this
     * is measured consumption, not an estimate reconstructed from recipes.
     * This class never writes to any of these tables and never changes their
     * behaviour.
     *
     * If auto-deduction is switched off for this vendor (`autoDeductionEnabled:
     * false`), the movement ledger will simply have no `order` rows to read,
     * so usage/waste/forecast figures correctly come back empty rather than
     * silently estimating — the frontend is expected to explain that to the
     * vendor rather than us guessing on their behalf.
     */
    private function inventory(Vendor $vendor, AnalyticsPeriod $period, Collection $lines): array
    {
        $items = $vendor->inventoryItems()->get([
            'id', 'name', 'category', 'quantity', 'unit', 'min_stock',
            'cost_per_unit', 'is_critical', 'track_stock',
        ]);

        if ($items->isEmpty()) {
            return ['available' => false];
        }

        $tracked = $items->where('track_stock', true);
        $critical = $tracked->filter(fn ($i) => (float) $i->quantity <= 0)->values();
        $low = $tracked->filter(fn ($i) => (float) $i->quantity > 0 && (float) $i->quantity <= (float) $i->min_stock)->values();

        $recipeLinks = MenuItemIngredient::whereIn('inventory_item_id', $items->pluck('id'))
            ->with('menuItem:id,name,product_uid,available')
            ->get(['id', 'menu_item_id', 'inventory_item_id', 'quantity', 'unit']);

        $linksByInventoryId = $recipeLinks->groupBy('inventory_item_id');
        $linksByMenuItemId = $recipeLinks->groupBy('menu_item_id');

        $revenueByUid = collect($this->aggregateLines($lines));
        // Deduped by cart-item id first — a shared item can now appear once
        // per order attributed a share of it (see linesFor()), but it was
        // still one physical unit served, same reasoning as aggregateLines().
        $uniqueLines = $lines->unique('id');
        $qtyByMenuItemId = $uniqueLines->groupBy('menu_item_id')->map(fn ($g) => (int) $g->sum('quantity'));

        // ---- at-risk menu items: sold this period, recipe touches a critical/low item
        $atRisk = [];
        foreach ($critical->concat($low) as $invItem) {
            foreach ($linksByInventoryId[$invItem->id] ?? [] as $link) {
                $menuItem = $link->menuItem;

                if (! $menuItem) {
                    continue;
                }

                $uid = $menuItem->product_uid ?? ('item-'.$menuItem->id);
                $atRisk[$uid] ??= [
                    'name' => $menuItem->name,
                    'available' => (bool) $menuItem->available,
                    'periodQuantity' => $revenueByUid[$uid]['quantity'] ?? 0,
                    'periodRevenue' => round($revenueByUid[$uid]['revenue'] ?? 0.0, 2),
                    'ingredients' => [],
                ];
                $atRisk[$uid]['ingredients'][] = [
                    'name' => $invItem->name,
                    'status' => (float) $invItem->quantity <= 0 ? 'out' : 'low',
                ];
            }
        }
        $atRisk = collect($atRisk)->sortByDesc('periodRevenue')->values()->all();
        $revenueAtRisk = round(array_sum(array_column($atRisk, 'periodRevenue')), 2);

        // ---- recipe coverage: share of sold quantity whose menu item has at
        // least one recipe link, regardless of whether it moved any stock.
        $coveredQty = 0;
        foreach ($qtyByMenuItemId as $menuItemId => $qtySold) {
            if (($linksByMenuItemId[$menuItemId] ?? collect())->isNotEmpty()) {
                $coveredQty += $qtySold;
            }
        }
        $totalSoldQty = $uniqueLines->sum('quantity');

        // ---- real usage and waste, read from the stock-movement ledger the
        // backend writes on every completed order (type=order) and every
        // manual adjustment (type=delivery/waste/correction). Scoped to this
        // period so the daily consumption rate below reflects only what
        // actually moved within it.
        $movements = InventoryStockMovement::where('vendor_id', $vendor->id)
            ->whereIn('inventory_item_id', $items->pluck('id'))
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->get(['inventory_item_id', 'type', 'quantity_change']);

        $usageByInventoryId = $movements->where('type', 'order')
            ->groupBy('inventory_item_id')
            ->map(fn ($g) => abs((float) $g->sum('quantity_change')));

        $wasteByInventoryId = $movements->where('type', 'waste')
            ->groupBy('inventory_item_id')
            ->map(fn ($g) => abs((float) $g->sum('quantity_change')));

        // Carbon 3's diffInDays() returns a float, and a whole-day span lands
        // just under the round number (a 365-day period reads 364.9999999999884).
        // Rounding first keeps every consumer on the same integer denominator:
        // the daily-rate divisions below used the raw float while
        // inventoryExpiryRiskPlaceholder()'s int parameter silently truncated
        // it, so one report divided by two different day counts.
        $periodDays = max(1, (int) round($period->queryStart()->diffInDays($period->queryEnd())));

        $usage = $items
            ->map(function ($item) use ($usageByInventoryId, $periodDays) {
                $qty = (float) ($usageByInventoryId[$item->id] ?? 0.0);

                if ($qty <= 0) {
                    return null;
                }

                $dailyRate = $qty / $periodDays;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'usage' => round($qty, 2),
                    'currentQuantity' => (float) $item->quantity,
                    'trackStock' => (bool) $item->track_stock,
                    'daysRemaining' => ($item->track_stock && $dailyRate > 0 && $item->quantity > 0)
                        ? round((float) $item->quantity / $dailyRate, 1)
                        : null,
                ];
            })
            ->filter()
            ->values();

        $waste = $items
            ->map(function ($item) use ($wasteByInventoryId) {
                $qty = (float) ($wasteByInventoryId[$item->id] ?? 0.0);

                if ($qty <= 0) {
                    return null;
                }

                return [
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'quantity' => round($qty, 2),
                    'value' => round($qty * (float) $item->cost_per_unit, 2),
                ];
            })
            ->filter()
            ->sortByDesc('value')
            ->values();

        // ---- forecast: which ingredients run out soonest at the real
        // consumption pace measured this period, and which sold menu items
        // that would take down.
        $soonestStockouts = $usage
            ->filter(fn ($row) => $row['daysRemaining'] !== null && $row['daysRemaining'] <= 14)
            ->sortBy('daysRemaining')
            ->take(5)
            ->map(function ($row) use ($linksByInventoryId, $revenueByUid) {
                $affected = collect($linksByInventoryId[$row['id']] ?? [])
                    ->map(function ($link) use ($revenueByUid) {
                        $menuItem = $link->menuItem;

                        if (! $menuItem) {
                            return null;
                        }

                        $uid = $menuItem->product_uid ?? ('item-'.$menuItem->id);

                        return [
                            'name' => $menuItem->name,
                            'periodRevenue' => round($revenueByUid[$uid]['revenue'] ?? 0.0, 2),
                        ];
                    })
                    ->filter()
                    ->sortByDesc('periodRevenue')
                    ->values()
                    ->all();

                return [
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'currentQuantity' => $row['currentQuantity'],
                    'daysRemaining' => $row['daysRemaining'],
                    'affectedMenuItems' => $affected,
                ];
            })
            ->values()
            ->all();

        // ---- capital sitting in stock that barely moved this period,
        // valued at the vendor's own recorded cost.
        $usageById = $usage->keyBy('id');
        // "No usage row for this item" only means "barely moving" when the
        // ledger has *some* data this period to have recorded that absence
        // from. With zero movements recorded at all (e.g. auto-deduction is
        // off for this vendor), every item would trivially qualify —
        // indistinguishable from real slow stock — so this stays empty
        // rather than presenting an unmeasured guess.
        $slowMovers = $movements->isEmpty() ? collect() : $tracked
            ->filter(fn ($i) => (float) $i->quantity > 0 && (float) $i->cost_per_unit > 0)
            ->filter(function ($i) use ($usageById) {
                $row = $usageById->get($i->id);

                return $row === null || $row['daysRemaining'] === null || $row['daysRemaining'] > 45;
            })
            ->map(fn ($i) => [
                'name' => $i->name,
                'value' => round((float) $i->quantity * (float) $i->cost_per_unit, 2),
                'quantity' => (float) $i->quantity,
                'unit' => $i->unit,
            ])
            ->sortByDesc('value')
            ->values();

        $tiedUpCapital = round($slowMovers->sum('value'), 2);

        // ---- purchase orders that need the vendor's eyes: currently pending
        // with a supplier, or stuck because dispatch failed / needs manual
        // action. Not gated to this period — a stuck order from last week is
        // still stuck today.
        $purchaseOrders = InventoryPurchaseOrder::where('vendor_id', $vendor->id)
            ->whereIn('status', ['pending', 'sent', 'manual_action_required', 'failed'])
            ->get(['purchase_order_public_id', 'supplier_name', 'status', 'dispatch_error', 'quantity', 'unit', 'unit_cost']);

        $pendingPOs = $purchaseOrders->whereIn('status', ['pending', 'sent']);
        $needsAttentionPOs = $purchaseOrders->whereIn('status', ['manual_action_required', 'failed']);

        // ---- valuation: every tracked item's stock on hand, at the vendor's
        // own recorded cost, plus a per-item consumption rate and runout
        // estimate — not just the top-5 soonest-to-run-out.
        $totalValue = round($tracked->sum(fn ($i) => (float) $i->quantity * (float) $i->cost_per_unit), 2);

        $stockLevels = $tracked
            ->map(function ($item) use ($usageByInventoryId, $periodDays) {
                $qty = (float) ($usageByInventoryId[$item->id] ?? 0.0);
                $dailyRate = $qty / $periodDays;

                return [
                    'name' => $item->name,
                    'category' => $item->category,
                    'unit' => $item->unit,
                    'quantity' => (float) $item->quantity,
                    'costPerUnit' => (float) $item->cost_per_unit,
                    'value' => round((float) $item->quantity * (float) $item->cost_per_unit, 2),
                    'dailyUsageRate' => round($dailyRate, 3),
                    'daysRemaining' => ($dailyRate > 0 && $item->quantity > 0)
                        ? round((float) $item->quantity / $dailyRate, 1)
                        : null,
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();

        // ---- price history: real unit_cost recorded on each purchase order
        // for this item, oldest to newest. Not gated to this period — a price
        // change from three months ago is still the most recent price change
        // there is, and gating it to a 7-day window would just hide it.
        $priceChanges = InventoryPurchaseOrder::where('vendor_id', $vendor->id)
            ->whereIn('inventory_item_id', $items->pluck('id'))
            ->whereNotNull('inventory_item_id')
            ->orderBy('created_at')
            ->get(['inventory_item_id', 'unit', 'unit_cost', 'created_at'])
            ->groupBy('inventory_item_id')
            ->map(function ($orders, $invId) use ($items) {
                if ($orders->count() < 2) {
                    return null;
                }

                $item = $items->firstWhere('id', (int) $invId);

                if (! $item) {
                    return null;
                }

                $latest = $orders->last();
                $previous = $orders->slice(-2, 1)->first();
                $changePercent = (float) $previous->unit_cost > 0
                    ? round(((((float) $latest->unit_cost) - (float) $previous->unit_cost) / (float) $previous->unit_cost) * 100, 1)
                    : null;

                return [
                    'name' => $item->name,
                    'unit' => $latest->unit,
                    'previousCost' => round((float) $previous->unit_cost, 2),
                    'latestCost' => round((float) $latest->unit_cost, 2),
                    'changePercent' => $changePercent,
                    'previousDate' => CarbonImmutable::parse($previous->created_at)->toISOString(),
                    'latestDate' => CarbonImmutable::parse($latest->created_at)->toISOString(),
                ];
            })
            ->filter()
            ->sortByDesc(fn ($row) => abs($row['changePercent'] ?? 0))
            ->values()
            ->take(10)
            ->all();

        $expiryRisk = $this->inventoryExpiryRiskPlaceholder($vendor, $tracked, $usageByInventoryId, $periodDays, $linksByInventoryId);

        return [
            'available' => true,
            'autoDeductionEnabled' => $this->inventoryAutoDeductionEnabled($vendor),
            'totalItems' => $items->count(),
            'trackedItems' => $tracked->count(),
            'criticalCount' => $critical->count(),
            'lowStockCount' => $low->count(),
            'revenueAtRisk' => $revenueAtRisk,
            'critical' => $critical->map(fn ($i) => $this->inventoryRow($i))->all(),
            'lowStock' => $low->map(fn ($i) => $this->inventoryRow($i))->all(),
            'atRiskMenuItems' => $atRisk,
            'soonestStockouts' => $soonestStockouts,
            'tiedUpCapital' => $tiedUpCapital,
            'slowMovers' => $slowMovers->take(20)->all(),
            'usage' => $usage->sortByDesc('usage')->take(10)->values()->all(),
            'waste' => [
                'totalValue' => round($waste->sum('value'), 2),
                'items' => $waste->take(10)->all(),
            ],
            'purchaseOrders' => [
                'pendingCount' => $pendingPOs->count(),
                'pendingValue' => round($pendingPOs->sum(fn ($po) => (float) $po->quantity * (float) $po->unit_cost), 2),
                'needsAttentionCount' => $needsAttentionPOs->count(),
                'needsAttention' => $needsAttentionPOs->take(10)->map(fn ($po) => [
                    'purchaseOrderPublicId' => $po->purchase_order_public_id,
                    'supplierName' => $po->supplier_name,
                    'status' => $po->status,
                    'dispatchError' => $po->dispatch_error,
                    'quantity' => (float) $po->quantity,
                    'unit' => $po->unit,
                ])->values()->all(),
            ],
            'recipeCoveragePercent' => $totalSoldQty > 0 ? round(($coveredQty / $totalSoldQty) * 100, 1) : null,
            'valuation' => [
                'totalValue' => $totalValue,
                'stockLevels' => $stockLevels,
                'breakdown' => [
                    'onHandValue' => $totalValue,
                    'wastedValue' => round($waste->sum('value'), 2),
                    'expiryRiskValue' => $expiryRisk['totalValue'],
                    'expiryRiskIsPlaceholder' => true,
                ],
            ],
            'priceChanges' => $priceChanges,
            'expiryRisk' => $expiryRisk,
        ];
    }

    /**
     * PLACEHOLDER, not a real measurement — the Inventory package has no
     * expiry-date field yet. Until it does, this is the best honest proxy
     * available: an assumed shelf life per category (a hardcoded guess, not
     * vendor data) counted forward from the most recent delivery movement,
     * compared against how fast the item is actually being used. Anything
     * projected to still be on the shelf after its assumed shelf life is
     * flagged, valued at cost.
     *
     * Every consumer of this data (frontend, README) MUST label it as an
     * estimate built on a placeholder assumption, never as a measured fact.
     * `isPlaceholder: true` and `assumptionNote` are included in the payload
     * itself precisely so nothing downstream can present it as real without
     * deliberately dropping that flag. Replace this whole method once the
     * backend has a real `expires_at` (or received-batch) field to read.
     */
    private const PLACEHOLDER_SHELF_LIFE_DAYS = [
        'seafood' => 3, 'fish' => 3, 'meat' => 4, 'poultry' => 4,
        'dairy' => 7, 'bakery' => 3, 'produce' => 5,
        'frozen' => 90, 'dry' => 180, 'pantry' => 180, 'beverage' => 180, 'alcohol' => 365,
    ];

    /** Used when no category keyword matches — a conservative, generic guess. */
    private const PLACEHOLDER_DEFAULT_SHELF_LIFE_DAYS = 21;

    private function inventoryExpiryRiskPlaceholder(
        Vendor $vendor,
        Collection $tracked,
        Collection $usageByInventoryId,
        int $periodDays,
        Collection $linksByInventoryId,
    ): array {
        // Most recent delivery movement per item, all-time — not gated to
        // this period, since the shelf-life clock started on delivery
        // whenever that actually happened, not when the analytics window did.
        $lastDeliveryByItem = InventoryStockMovement::where('vendor_id', $vendor->id)
            ->whereIn('inventory_item_id', $tracked->pluck('id'))
            ->where('type', 'delivery')
            ->selectRaw('inventory_item_id, MAX(created_at) as last_delivered_at')
            ->groupBy('inventory_item_id')
            ->pluck('last_delivered_at', 'inventory_item_id');

        $now = CarbonImmutable::now();

        $items = $tracked
            ->filter(fn ($i) => (float) $i->quantity > 0 && (float) $i->cost_per_unit > 0)
            ->map(function ($item) use ($lastDeliveryByItem, $usageByInventoryId, $periodDays, $now, $linksByInventoryId) {
                $lastDelivery = $lastDeliveryByItem[$item->id] ?? null;

                // No delivery on record for this item means there is nothing
                // to count the assumed shelf life forward from — stay silent
                // rather than guess a start date too.
                if ($lastDelivery === null) {
                    return null;
                }

                $shelfLifeDays = $this->placeholderShelfLifeDays($item->category);
                $assumedExpiryAt = CarbonImmutable::parse($lastDelivery)->addDays($shelfLifeDays);
                $daysUntilAssumedExpiry = max(0, (int) round($now->diffInDays($assumedExpiryAt, false)));

                $dailyRate = ($usageByInventoryId[$item->id] ?? 0.0) / $periodDays;

                if ($dailyRate > 0) {
                    $consumableBeforeExpiry = $dailyRate * $daysUntilAssumedExpiry;
                    $atRiskQty = max(0.0, (float) $item->quantity - $consumableBeforeExpiry);
                } else {
                    // No measured usage at all — flag the full amount once the
                    // assumed shelf life is close, rather than claim a pace
                    // that was never observed.
                    $atRiskQty = $daysUntilAssumedExpiry <= 14 ? (float) $item->quantity : 0.0;
                }

                if ($atRiskQty <= 0) {
                    return null;
                }

                $suggestedMenuItems = collect($linksByInventoryId[$item->id] ?? [])
                    ->map(fn ($link) => $link->menuItem?->name)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'quantity' => round($atRiskQty, 2),
                    'value' => round($atRiskQty * (float) $item->cost_per_unit, 2),
                    'assumedShelfLifeDays' => $shelfLifeDays,
                    'daysUntilAssumedExpiry' => $daysUntilAssumedExpiry,
                    'suggestedMenuItems' => $suggestedMenuItems,
                ];
            })
            ->filter()
            ->sortByDesc('value')
            ->values();

        return [
            'isPlaceholder' => true,
            'assumptionNote' => 'Estimated from an assumed shelf life per category and the last recorded '
                .'delivery — the Inventory package has no real expiry-date field yet. Add one on the backend '
                .'for an accurate figure; until then, treat this as a rough signal, not a measured fact.',
            'totalValue' => round($items->sum('value'), 2),
            'items' => $items->take(10)->all(),
        ];
    }

    private function placeholderShelfLifeDays(?string $category): int
    {
        $normalized = mb_strtolower(trim((string) $category));

        foreach (self::PLACEHOLDER_SHELF_LIFE_DAYS as $keyword => $days) {
            if ($normalized !== '' && str_contains($normalized, $keyword)) {
                return $days;
            }
        }

        return self::PLACEHOLDER_DEFAULT_SHELF_LIFE_DAYS;
    }

    /**
     * Mirrors InventoryConsumptionService::isEnabled() — that method is
     * private to the consumption service, so this is a read-only duplicate of
     * the same small settings check, not a call into the service itself.
     */
    private function inventoryAutoDeductionEnabled(Vendor $vendor): bool
    {
        $settings = $vendor->inventorySettings;
        $stored = $settings?->settings ?? [];
        $general = is_array($stored['general'] ?? null) ? $stored['general'] : [];

        $trackingEnabled = $general['enableInventoryTracking'] ?? true;
        $deductionEnabled = $general['enableAutoStockDeduction']
            ?? $settings?->link_menu_items
            ?? true;

        return (bool) $trackingEnabled && (bool) $deductionEnabled;
    }

    private function inventoryRow($item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'quantity' => (float) $item->quantity,
            'unit' => $item->unit,
            'minStock' => (float) $item->min_stock,
        ];
    }

    // ---------------------------------------------------------------- loyalty

    /**
     * Read-only analysis over the existing Loyalty package (CustomerLoyaltyPoint,
     * LoyaltyTransaction, VendorSetting). This class never awards or redeems a
     * point — it only reads whatever the loyalty package has already written.
     *
     * As of this package, nothing in the order/payment flow ever writes to
     * customer_loyalty_points or loyalty_transactions, so on most vendors this
     * will correctly report `enabled: true, available: false` even when the
     * toggle is on — that is the honest state, not a bug in this section.
     */
    private function loyalty(Vendor $vendor, AnalyticsPeriod $period, Collection $paidOrders, Collection $lines): array
    {
        $settings = $vendor->vendorSetting;
        $enabled = (bool) ($settings?->loyalty_enabled ?? false);

        if (! $enabled) {
            return ['enabled' => false, 'available' => false];
        }

        $wallets = CustomerLoyaltyPoint::where('vendor_id', $vendor->id)->get();
        $transactions = LoyaltyTransaction::where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->get();

        if ($wallets->isEmpty() && $transactions->isEmpty()) {
            return ['enabled' => true, 'available' => false];
        }

        $pointValue = (float) ($settings->point_value ?? 0.01);
        $minRedemption = (int) ($settings->minimum_redemption_points ?? 100);

        $eligible = $wallets->filter(fn ($w) => $w->points_balance >= $minRedemption);
        $eligibleThatRedeemed = $eligible->filter(fn ($w) => $w->total_redeemed > 0);

        $earnedPoints = (int) $transactions->where('type', 'earned')->sum('points');
        $redeemedPoints = (int) abs($transactions->where('type', 'redeemed')->sum('points'));

        $newMembers = $wallets->filter(
            fn ($w) => $w->created_at !== null && $w->created_at->between($period->queryStart(), $period->queryEnd())
        )->count();

        return [
            'enabled' => true,
            'available' => true,
            // `contains()`, not `every()` — see FinancialReportService's
            // identical loyaltyLiability()/laborCost() comment: a vendor
            // mixing real and demo activity must still flag the whole
            // section as demo-tainted rather than silently presenting
            // fabricated activity as measured fact. Checks both tables —
            // wallets and transactions are seeded together today, but this
            // stays correct even if that ever changes.
            'isDemoData' => $wallets->contains(fn (CustomerLoyaltyPoint $w) => $w->source === 'demo')
                || $transactions->contains(fn (LoyaltyTransaction $tr) => $tr->source === 'demo'),
            'members' => $wallets->count(),
            'newMembersThisPeriod' => $newMembers,
            'pointValue' => $pointValue,
            'minimumRedemptionPoints' => $minRedemption,
            'outstandingLiability' => round($wallets->sum('points_balance') * $pointValue, 2),
            'eligibleToRedeem' => $eligible->count(),
            // How many of those eligible members have gone quiet — Tavlo naming
            // who is worth a nudge, rather than asking the vendor to guess.
            'eligibleInactiveCount' => $this->eligibleInactiveCount($vendor, $eligible->pluck('customer_id')->all()),
            'pointsEarned' => $earnedPoints,
            'pointsRedeemed' => $redeemedPoints,
            'redemptionRate' => $eligible->count() > 0
                ? round(($eligibleThatRedeemed->count() / $eligible->count()) * 100, 1)
                : null,
            'memberLift' => $this->loyaltyMemberLift($paidOrders, $lines, $wallets->pluck('customer_id')->all()),
            'visitFrequency' => [
                'thisPeriod' => $this->loyaltyVisitsThisPeriod($paidOrders, $wallets->pluck('customer_id')->all()),
                'beforeAfterJoining' => $this->loyaltyVisitsBeforeAfterJoining($vendor, $wallets),
            ],
        ];
    }

    /**
     * Visits per identified customer this period, members against everyone
     * else who has an account but no wallet. A "visit" here is a calendar day
     * with at least one paid order — simple, honest, and works the same
     * whether the order was dine-in, pickup or takeaway, at the cost of
     * treating two orders on the same day (e.g. a drink then a meal) as one
     * visit rather than two.
     *
     * Guests with no account at all are excluded on both sides: an anonymous
     * order has no id to link a repeat visit to, so it cannot be counted
     * either way. This is why the comparison is against "identified
     * customers without a wallet," not "guests" in the walk-in sense.
     */
    private function loyaltyVisitsThisPeriod(Collection $paidOrders, array $memberCustomerIds): ?array
    {
        $memberSet = array_flip($memberCustomerIds);
        $identified = $paidOrders->filter(fn ($o) => $o->customer_id !== null);

        $memberOrders = $identified->filter(fn ($o) => isset($memberSet[$o->customer_id]));
        $nonMemberOrders = $identified->filter(fn ($o) => ! isset($memberSet[$o->customer_id]));

        $summarize = function (Collection $orders): ?array {
            $byCustomer = $orders->groupBy('customer_id');

            if ($byCustomer->isEmpty()) {
                return null;
            }

            $visitDays = $byCustomer->map(
                fn ($group) => $group->pluck('created_at')->map(fn ($d) => $d->toDateString())->unique()->count()
            );

            return ['customers' => $byCustomer->count(), 'avgVisits' => round((float) $visitDays->avg(), 2)];
        };

        $memberStats = $summarize($memberOrders);
        $nonMemberStats = $summarize($nonMemberOrders);

        if (
            ! $memberStats || ! $nonMemberStats
            || $memberStats['customers'] < self::LOYALTY_VISIT_MIN_SAMPLE
            || $nonMemberStats['customers'] < self::LOYALTY_VISIT_MIN_SAMPLE
        ) {
            return null;
        }

        return [
            'memberCustomers' => $memberStats['customers'],
            'memberAvgVisits' => $memberStats['avgVisits'],
            'nonMemberCustomers' => $nonMemberStats['customers'],
            'nonMemberAvgVisits' => $nonMemberStats['avgVisits'],
        ];
    }

    /**
     * For each member, compares their own visit pace before they joined
     * loyalty against their pace after — the closest this data can get to
     * "did the program change behaviour," since it is each customer measured
     * against themselves rather than a different group of people entirely.
     *
     * Only counts members who actually had an ordering relationship with the
     * vendor before joining — someone whose first-ever order was also the
     * order they joined loyalty on has no "before" to compare, and is
     * correctly excluded rather than being scored as an infinite increase.
     * Both windows also need at least LOYALTY_MIN_WINDOW_DAYS of real time to
     * count, so a handful of days either side of joining isn't mistaken for
     * a pace.
     */
    private function loyaltyVisitsBeforeAfterJoining(Vendor $vendor, Collection $wallets): array
    {
        $memberIds = $wallets->pluck('customer_id')->all();

        if ($memberIds === []) {
            return ['available' => false, 'membersAnalyzed' => 0];
        }

        // Chunked at 1,000 member IDs — $memberIds is this vendor's ENTIRE
        // loyalty membership, not scoped to the report period, so this grows
        // with total loyalty history alone and is the least-bounded of this
        // file's whereIn() queries. concat(), not merge() — `id` isn't
        // selected here (see retention()'s identical note above).
        $orders = collect();
        foreach (collect($memberIds)->chunk(1000) as $memberIdChunk) {
            $orders = $orders->concat(
                Order::query()
                    ->where('vendor_id', $vendor->id)
                    ->whereIn('customer_id', $memberIdChunk)
                    ->where('payment_received', true)
                    ->get(['customer_id', 'created_at'])
            );
        }

        $ordersByCustomer = $orders->groupBy('customer_id');
        $joinedAtByCustomer = $wallets->keyBy('customer_id');
        $now = CarbonImmutable::now();

        $rates = [];

        foreach ($memberIds as $customerId) {
            $wallet = $joinedAtByCustomer->get($customerId);
            $customerOrders = $ordersByCustomer->get($customerId);

            if (! $wallet || ! $wallet->created_at || ! $customerOrders) {
                continue;
            }

            $joinedAt = CarbonImmutable::parse($wallet->created_at);
            $before = $customerOrders->filter(fn ($o) => CarbonImmutable::parse($o->created_at)->lt($joinedAt));
            $after = $customerOrders->filter(fn ($o) => CarbonImmutable::parse($o->created_at)->gte($joinedAt));

            if ($before->isEmpty() || $after->isEmpty()) {
                continue;
            }

            $firstOrderAt = CarbonImmutable::parse($customerOrders->min('created_at'));
            $daysBefore = $firstOrderAt->diffInDays($joinedAt);
            $daysAfter = $joinedAt->diffInDays($now);

            if ($daysBefore < self::LOYALTY_MIN_WINDOW_DAYS || $daysAfter < self::LOYALTY_MIN_WINDOW_DAYS) {
                continue;
            }

            $visitsBefore = $before->pluck('created_at')->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->unique()->count();
            $visitsAfter = $after->pluck('created_at')->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->unique()->count();

            $rates[] = [
                'before' => $visitsBefore / ($daysBefore / 7),
                'after' => $visitsAfter / ($daysAfter / 7),
            ];
        }

        if (count($rates) < self::LOYALTY_BEFORE_AFTER_MIN_SAMPLE) {
            return ['available' => false, 'membersAnalyzed' => count($rates)];
        }

        $avgBefore = array_sum(array_column($rates, 'before')) / count($rates);
        $avgAfter = array_sum(array_column($rates, 'after')) / count($rates);

        $increased = 0;
        $decreased = 0;
        $unchanged = 0;

        foreach ($rates as $rate) {
            $delta = $rate['before'] > 0
                ? (($rate['after'] - $rate['before']) / $rate['before']) * 100
                : ($rate['after'] > 0 ? 100.0 : 0.0);

            match (true) {
                $delta > self::VISIT_RATE_NOISE_FLOOR => $increased++,
                $delta < -self::VISIT_RATE_NOISE_FLOOR => $decreased++,
                default => $unchanged++,
            };
        }

        return [
            'available' => true,
            'membersAnalyzed' => count($rates),
            'avgVisitsPerWeekBefore' => round($avgBefore, 2),
            'avgVisitsPerWeekAfter' => round($avgAfter, 2),
            'increasedCount' => $increased,
            'decreasedCount' => $decreased,
            'unchangedCount' => $unchanged,
        ];
    }

    /**
     * Of the members eligible to redeem, how many haven't ordered in the last
     * LOYALTY_INACTIVITY_DAYS days (or have no recorded paid order at all).
     * These are members who already have a reason to come back — the reward
     * is sitting there waiting — rather than a segment the vendor has to work
     * out for themselves.
     */
    private function eligibleInactiveCount(Vendor $vendor, array $eligibleCustomerIds): int
    {
        if ($eligibleCustomerIds === []) {
            return 0;
        }

        // Chunked at 1,000 customer IDs — same unbounded, all-time-scoped
        // pattern as loyaltyVisitsBeforeAfterJoining() above. pluck() returns
        // a plain Collection keyed by customer_id — union(), not merge(), for
        // the same integer-key-renumbering reason noted in repeatRate() above.
        $lastOrderByCustomer = collect();
        foreach (collect($eligibleCustomerIds)->chunk(1000) as $customerIdChunk) {
            $lastOrderByCustomer = $lastOrderByCustomer->union(
                Order::query()
                    ->where('vendor_id', $vendor->id)
                    ->whereIn('customer_id', $customerIdChunk)
                    ->where('payment_received', true)
                    ->selectRaw('customer_id, MAX(created_at) as last_order_at')
                    ->groupBy('customer_id')
                    ->pluck('last_order_at', 'customer_id')
            );
        }

        $cutoff = now()->subDays(self::LOYALTY_INACTIVITY_DAYS);

        return collect($eligibleCustomerIds)
            ->filter(function ($customerId) use ($lastOrderByCustomer, $cutoff) {
                $lastOrderAt = $lastOrderByCustomer->get($customerId);

                return $lastOrderAt === null || CarbonImmutable::parse($lastOrderAt)->lt($cutoff);
            })
            ->count();
    }

    /**
     * Compares average order value AND average items per basket between
     * guests with a loyalty wallet at this vendor and everyone else, on this
     * period's paid orders. The basket-depth comparison is what turns "spend
     * is different" into an actual explanation — smaller baskets vs the same
     * basket with cheaper choices — instead of leaving the vendor to guess.
     * Requires at least 10 orders on each side before offering a verdict.
     */
    private function loyaltyMemberLift(Collection $paidOrders, Collection $lines, array $memberCustomerIds): ?array
    {
        $memberSet = array_flip($memberCustomerIds);
        $memberOrders = $paidOrders->filter(fn ($o) => $o->customer_id && isset($memberSet[$o->customer_id]));
        $nonMemberOrders = $paidOrders->filter(fn ($o) => ! $o->customer_id || ! isset($memberSet[$o->customer_id]));

        if ($memberOrders->count() < 10 || $nonMemberOrders->count() < 10) {
            return null;
        }

        $memberAvg = (float) $memberOrders->avg('amount');
        $nonMemberAvg = (float) $nonMemberOrders->avg('amount');
        $upliftPercent = $nonMemberAvg > 0
            ? round((($memberAvg - $nonMemberAvg) / $nonMemberAvg) * 100, 1)
            : null;

        $itemsByOrder = $lines->groupBy(fn (CartItem $l) => $l->attributedOrderId ?? $l->order_id)
            ->map(fn ($g) => (int) $g->sum('quantity'));
        $memberAvgItems = round((float) $memberOrders->map(fn ($o) => $itemsByOrder[$o->id] ?? 0)->avg(), 1);
        $nonMemberAvgItems = round((float) $nonMemberOrders->map(fn ($o) => $itemsByOrder[$o->id] ?? 0)->avg(), 1);

        $basketDepthVerdict = 'similar';
        if ($nonMemberAvgItems > 0) {
            $basketGapPercent = (($memberAvgItems - $nonMemberAvgItems) / $nonMemberAvgItems) * 100;

            if ($basketGapPercent > self::BASKET_DEPTH_NOISE_FLOOR) {
                $basketDepthVerdict = 'larger';
            } elseif ($basketGapPercent < -self::BASKET_DEPTH_NOISE_FLOOR) {
                $basketDepthVerdict = 'smaller';
            }
        }

        return [
            'memberOrders' => $memberOrders->count(),
            'memberAvgOrderValue' => round($memberAvg, 2),
            'nonMemberOrders' => $nonMemberOrders->count(),
            'nonMemberAvgOrderValue' => round($nonMemberAvg, 2),
            'memberAvgItems' => $memberAvgItems,
            'nonMemberAvgItems' => $nonMemberAvgItems,
            'basketDepthVerdict' => $basketDepthVerdict,
            'upliftPercent' => $upliftPercent,
            // An observed gap, not a causal claim: what the member orders
            // were worth against what they would have been at the
            // non-member average, over this period's member order count.
            'revenueImpact' => round(($memberAvg - $nonMemberAvg) * $memberOrders->count(), 2),
        ];
    }

    // ----------------------------------------------------------------- helpers

    private function metric(float|int|null $value, float|int|null $previous, string $unit = 'percent'): array
    {
        $delta = null;

        // An absolute delta (e.g. avgGuestsPerTable 0 -> 4) is well-defined
        // even when $previous is zero — only a *percent* delta needs the
        // zero-guard, to avoid dividing by zero / an undefined "% change
        // from nothing." Previously the zero-guard blocked both, so any
        // absolute-unit metric moving from 0 silently showed no delta
        // instead of the real number (same bug FinancialReportService's
        // own metric() was fixed for).
        if ($value !== null && $previous !== null) {
            if ($unit === 'absolute') {
                $delta = round($value - $previous, 2);
            } elseif ((float) $previous !== 0.0) {
                $delta = round((($value - $previous) / abs((float) $previous)) * 100, 1);
            }
        }

        return [
            'value' => $value,
            'previous' => $previous,
            'delta' => $delta,
            'deltaUnit' => $unit,
        ];
    }

    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2
            ? (float) $values[$middle]
            : (float) (($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function medianOrNull(array $values): ?float
    {
        return $values === [] ? null : round($this->median($values), 1);
    }
}
