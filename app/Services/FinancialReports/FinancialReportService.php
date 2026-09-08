<?php

namespace App\Services\FinancialReports;

use App\Models\CartItem;
use App\Models\CustomerLoyaltyPoint;
use App\Models\FinancialExpense;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryStockMovement;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Refund;
use App\Models\StaffShift;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Services\MediaService;
use App\Services\Reports\ReportPeriodTooLargeException;
use App\Services\TaxCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * FinancialReportService
 *
 * Aggregates existing, already-live data into the vendor Financial Reports
 * payload. Deliberately does NOT snapshot anything new onto `orders` —
 * every figure is computed at query time from data this app already
 * captures (Order, CartItem + TaxCalculationService, Refund,
 * InventoryPurchaseOrder, Invoice/Subscription). No migrations, no new
 * webhook wiring, no backfill command.
 *
 * Two intentional differences from VendorAnalyticsService, both because
 * this is a bookkeeping tool rather than an operational dashboard:
 *
 *  1. Orders are windowed by `payment_confirmed_at` (cash basis — revenue
 *     recognised the moment money was actually captured), not `created_at`
 *     like Analytics. An order placed on the 31st but paid on the 2nd will
 *     therefore land in a different period here than it does in Analytics —
 *     that's correct for a report an accountant will use, not a bug.
 *
 *  2. `summary.netRevenue` = gross − VAT, matching Analytics' definition
 *     exactly (so the two pages never disagree on the same period). It is
 *     NOT reduced by refunds or costs — those are their own sections,
 *     because "net revenue" and "cash actually kept after refunds/costs"
 *     are different questions and conflating them would misinform whoever
 *     reads this for tax purposes.
 *
 * Refunds are windowed by `refunds.resolved_at` (the moment the refund was
 * actually approved/paid out), not by the original order's date — a refund
 * against a prior period's sale is a cash event that happened in THIS
 * period and must be reported here for the numbers to reconcile against a
 * bank/Stripe statement.
 *
 * VAT is ALWAYS derived fresh from `cart_items` via
 * `TaxCalculationService::computeTaxGroups()`, never read from
 * `orders.vat_amount` — confirmed against real vendor data that this column
 * can be stale/unpopulated on historical orders even though the underlying
 * cart items have real tax categories. Deriving it fresh means the
 * headline "Total Tax"/"Net Revenue" figures always agree with the Tax
 * Breakdown card and the daily table's VAT column, instead of silently
 * showing €0 tax next to a tax breakdown that isn't zero.
 *
 * `summary.costOfGoodsSold` / `grossProfit` / `grossMarginPercent` are
 * derived from `inventory_stock_movements` (type='order', the automatic
 * per-order deduction InventoryConsumptionService writes) × each
 * InventoryItem's `cost_per_unit` — real consumption cost, not the
 * purchasing-based `costs.inventoryPurchases` figure below (those answer
 * different questions: "what did we sell the cost of" vs "what did we
 * buy"). This only covers menu items with recipe/inventory tracking
 * configured — items without it contribute 0 COGS, so this is a floor on
 * true cost, not a guaranteed-complete figure. Documented to the frontend
 * via a visible caveat, never silently presented as exact.
 *
 * `summary.laborCost` / `primeCost` / `primeCostPercent` come from a new
 * `staff_shifts` table (+ `team_members.hourly_wage`) — nothing like a
 * timesheet or wage rate existed anywhere in this codebase before this
 * feature. Every row is real, queryable infrastructure (not a canned
 * number), but since no clock-in feature exists yet to populate it,
 * `App\Console\Commands\SeedDemoLaborData` seeds plausible sample shifts
 * tagged `source='demo'` so the report isn't empty. `laborCost` is 0 for
 * any vendor/period with no shift rows — real zero, not a bug.
 * Prime Cost = COGS + Labor Cost, the standard restaurant health metric
 * (industry-typical healthy range is 55-65% of net revenue). It is a cost
 * total, not a waterfall step — it does not itself appear as a subtraction
 * anywhere below.
 *
 * `summary.operatingProfit` / `netProfit` / `netMarginPercent` continue the
 * same waterfall past Gross Profit: Gross Profit − Labor Cost −
 * (manual "other costs" + subscription fees) = Operating Profit; Operating
 * Profit − Inventory Waste − Refunds = Net Profit. `costs.inventoryPurchases`
 * is deliberately NOT part of this chain — it is cash-basis purchasing,
 * already represented on the cost side via `costOfGoodsSold` (consumption)
 * or, for stock that spoiled instead of sold, via Inventory Waste below;
 * including it a third time here would double-count the same outflow.
 * Net Profit inherits both the Cost of Goods Sold caveat (menu items
 * without inventory tracking contribute 0 COGS) and the Labor Cost
 * demo-data caveat above — it is only ever as complete as those two inputs.
 *
 * `loyaltyLiability` comes from `customer_loyalty_points` (real,
 * queryable infrastructure) but nothing in the order/payment flow ever
 * writes to it yet — no points-earning feature exists. Until one does,
 * `App\Console\Commands\SeedDemoLoyaltyData` seeds plausible sample wallets
 * (tagged `source='demo'`, onto real customers who have actually ordered)
 * so the card isn't empty, exactly like the Labor Cost demo-data pattern
 * above. `isDemoData` flags the figure whenever any wallet behind it is
 * seeded rather than earned; the card is null (hidden) entirely when the
 * vendor hasn't turned loyalty on, same gate the real feature will use.
 *
 * `discounts` reuses the exact same logic as
 * `VendorAnalyticsService::discounts()` (menu_items.has_discount /
 * discounted_price / discount_percent, historically accurate because a
 * discount change forces a new MenuItem version) rather than re-deriving
 * discount math for this feature — this data was already real and already
 * computed elsewhere; only the "revenue forgone" framing is new.
 */
class FinancialReportService
{
    public function __construct(
        private readonly MediaService $media,
    ) {}

    /**
     * Only the raw `orders`/`previousOrders` fetch is cached (see
     * `ordersAndCartItemsFor()`) — two cheap, relation-free queries, worth
     * skipping on a repeat request within the TTL. Cart items are
     * deliberately NOT cached despite being the more expensive half to
     * compute: caching the hydrated CartItem→MenuItem→Category object graph
     * was measured (2026-09-07) to more than double peak memory versus just
     * recomputing it, because serializing a large nested object graph for
     * the database cache store costs substantially more than holding the
     * live objects. Refunds, labor, manual expenses, subscription invoices,
     * and inventory waste are all bounded by the requested period rather
     * than full vendor history and also stay live/uncached — several of them
     * (manual expenses, labor shifts) are vendor-editable and must reflect a
     * just-made change immediately, the same lesson learned the hard way on
     * `VendorAnalyticsService::build()`'s equivalent cache (see its own doc
     * comment).
     */
    private const CACHE_TTL_SECONDS = 120;

    public function build(Vendor $vendor, FinancialReportPeriod $period): array
    {
        [$orders, $previousOrders, $cartItems, $previousCartItems] = $this->ordersAndCartItemsFor($vendor, $period);

        $refunds = $this->refundsIn($vendor, $period->queryStart(), $period->queryEnd());
        $previousRefunds = $this->refundsIn($vendor, $period->previousQueryStart(), $period->previousQueryEnd());

        $refundsTotal = round((float) $refunds->sum('amount'), 2);
        $previousRefundsTotal = round((float) $previousRefunds->sum('amount'), 2);

        $vatByOrderId = $this->vatByOrderId($cartItems, $vendor);
        $previousVatByOrderId = $this->vatByOrderId($previousCartItems, $vendor);

        $vatTotal = round(array_sum($vatByOrderId), 2);
        $previousVatTotal = round(array_sum($previousVatByOrderId), 2);

        // $applySharing=true to match every real charge-computation caller
        // (CartController, PaymentController, OrderHistoryController) — a
        // cart line split across a shared order divides its gross by the
        // number of sharers there too, so this breakdown's totals actually
        // reconcile against `summary.grossRevenue` (orders.amount, which is
        // computed the same way) instead of over-counting split lines.
        $taxGroups = TaxCalculationService::computeTaxGroups($cartItems, $vendor->country ?? '', applySharing: true);

        $cogs = $this->costOfGoodsSold($orders, $cartItems);
        $previousCogs = $this->costOfGoodsSold($previousOrders, $previousCartItems);

        $labor = $this->laborCost($vendor, $period->queryStart(), $period->queryEnd());
        $previousLabor = $this->laborCost($vendor, $period->previousQueryStart(), $period->previousQueryEnd());

        // Computed once here (not inside costs()) so both the current AND
        // previous windows are available for summary()'s waterfall — costs()
        // only ever needed the current period before Net Profit existed.
        $manualExpenses = $this->manualExpenses($vendor, $period->queryStart(), $period->queryEnd());
        $previousManualExpenses = $this->manualExpenses($vendor, $period->previousQueryStart(), $period->previousQueryEnd());

        $subscriptionInvoices = $this->subscriptionInvoicesList($vendor, $period->queryStart(), $period->queryEnd());
        $previousSubscriptionInvoices = $this->subscriptionInvoicesList($vendor, $period->previousQueryStart(), $period->previousQueryEnd());
        $subscriptionAmount = round((float) array_sum(array_column($subscriptionInvoices, 'amount')), 2);
        $previousSubscriptionAmount = round((float) array_sum(array_column($previousSubscriptionInvoices, 'amount')), 2);

        $waste = $this->inventoryWaste($vendor, $period->queryStart(), $period->queryEnd());
        $previousWaste = $this->inventoryWaste($vendor, $period->previousQueryStart(), $period->previousQueryEnd());

        $costs = $this->costs($vendor, $period, $subscriptionInvoices, $manualExpenses);

        // Previously just `$orders->isNotEmpty()` — a day/week with only
        // refunds, only a manually-logged expense, or only labor cost (no
        // paid orders) has real money to show, but the frontend treats
        // `hasData: false` as "nothing to see" and hides the P&L, Costs,
        // and Cash Flow sections entirely. Any real monetary activity
        // should count, not just paid orders.
        $hasData = $orders->isNotEmpty()
            || $refunds->isNotEmpty()
            || $costs['totalCosts'] > 0
            || $waste['amount'] > 0
            || $labor['amount'] > 0;

        return [
            'period' => $period->key,
            'currency' => $vendor->currency,
            // Added so the frontend can render each itemized row's own
            // timestamp (expenses, waste, invoices, purchase orders) in the
            // RESTAURANT's timezone rather than the viewer's browser
            // timezone — previously nothing in this payload exposed it,
            // unlike VendorAnalyticsService::build(), which already returns
            // the identical field the same way (2026-09-08 audit finding).
            'timezone' => $period->timezone,
            'range' => $period->toArray(),
            'previousRange' => $period->previousToArray(),
            'bucketUnit' => $period->bucketUnit(),
            'hasData' => $hasData,
            'costOfGoodsSoldCoveragePercent' => $this->costOfGoodsSoldCoveragePercent($cartItems, $vendor),
            'summary' => $this->summary(
                $orders, $previousOrders, $vatTotal, $previousVatTotal, $refundsTotal, $previousRefundsTotal,
                $cogs, $previousCogs, $labor['amount'], $previousLabor['amount'],
                $manualExpenses['amount'], $previousManualExpenses['amount'],
                $subscriptionAmount, $previousSubscriptionAmount,
                $waste['amount'], $previousWaste['amount'],
            ),
            'taxBreakdown' => $this->formatTaxBreakdown($taxGroups),
            'revenueByOrderType' => $this->revenueByOrderType($orders),
            'revenueByCategory' => $this->revenueByCategory($cartItems, $vendor),
            'paymentMethods' => $this->paymentMethodBreakdown($orders),
            'tipsByPaymentMethod' => $this->tipsByPaymentMethod($orders),
            'labor' => $labor,
            'discounts' => $this->discountsSummary($cartItems, $orders, $vendor),
            'refunds' => $this->refundsSummary($vendor, $period, $refunds),
            'voidedOrders' => $this->voidedOrders($vendor, $period),
            'salesInvoices' => $this->salesInvoices($orders),
            'costs' => $costs,
            'inventoryWaste' => $waste,
            'tipDistribution' => $this->tipDistribution($vendor, $orders),
            'upcomingRecurringExpenses' => $this->upcomingRecurringExpenses($vendor),
            'daily' => $this->dailyBreakdown($orders, $refunds, $vatByOrderId, $period),
            'salesByHour' => $this->salesByHour($orders, $period),
            'cashFlow' => $this->cashFlowStatement($vendor, $period),
            'loyaltyLiability' => $this->loyaltyLiability($vendor),
        ];
    }

    /** @return array{0: Collection, 1: Collection, 2: Collection, 3: Collection} orders, previousOrders, cartItems, previousCartItems */
    private function ordersAndCartItemsFor(Vendor $vendor, FinancialReportPeriod $period): array
    {
        // Only orders are cached now — see the class doc comment above for
        // why cart items deliberately are not (2026-09-07: measured caching
        // the hydrated CartItem→MenuItem→Category object graph pushing peak
        // memory on a realistic vendor's "This year" view from ~194MB of real
        // data to a 508MB+ peak, because PHP's serialize() representation of
        // a large nested object graph runs 2-3x the size of the live data —
        // the caching layer meant to fix "the report is slow" was turning
        // into a new way for it to crash on exactly the vendors it most
        // needed to help).
        //
        // Even orders-only still crashes at large enough scale (2026-09-08
        // audit finding, reproduced directly): simply BUILDING (not even
        // caching) the order/cart-item collections for a ~2,900-order period
        // reliably exhausted a 128MB PHP process — well below what the
        // "too big to run live" threshold below previously assumed was
        // safe. See config/reports.php's doc comments for the full
        // measurement. Two separate guards follow:
        //
        //  1. Above `max_order_count`, refuse outright — throwing here
        //     (rather than attempting the hydration and possibly hitting an
        //     uncatchable out-of-memory fatal error) is what makes this
        //     failure catchable at all. A live request is already kept well
        //     below this ceiling by `async_order_threshold` below, so in
        //     practice this only ever fires from the background-job path,
        //     whose existing `catch (Throwable $e)` handles it exactly like
        //     any other failure.
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
                'financial-report:orders:v%d:%s:%s:%s',
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
        // cartItemsFor() calls below.
        $sharedCandidates = $orders->isNotEmpty() || $previousOrders->isNotEmpty()
            ? $this->sharedIntoCandidates((int) $vendor->id)
            : collect();

        $cartItems = $this->cartItemsFor($orders, $sharedCandidates);
        // previousCartItems only ever feeds vatByOrderId()/costOfGoodsSold()
        // below (see summary()'s call sites) — neither touches
        // menuItem.category or .recipeIngredients (only
        // revenueByCategory()/costOfGoodsSoldCoveragePercent() do, and those
        // only ever run against the CURRENT period's items). Skipping those
        // two relations for the previous window's OWN owned-items query cuts
        // real memory on every request; the shared-into rows above are
        // already hydrated with the full relation set regardless (shared
        // once, for the current window's sake), so nothing is lost.
        $previousCartItems = $this->cartItemsFor($previousOrders, $sharedCandidates, withCatalogRelations: false);

        return [$orders, $previousOrders, $cartItems, $previousCartItems];
    }

    /**
     * Every shared cart item this vendor has ever had (`shared_order_ids` not
     * null), completely unscoped by date — deliberately, since a shared
     * item's OWN order can fall far outside the window of the order it was
     * shared INTO, so there is no safe date bound to add without risking a
     * silently-dropped share. Callers narrow this candidate set down to
     * "does this intersect the order IDs I actually care about" in PHP (see
     * cartItemsFor()). Fetched once per build() and reused for both the
     * current and previous period — see ordersAndCartItemsFor().
     */
    private function sharedIntoCandidates(int $vendorId): Collection
    {
        return CartItem::with($this->menuItemEagerLoad())
            ->whereNotNull('shared_order_ids')
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))
            ->get();
    }

    /**
     * `menuItem` column-restricted to only the fields anything in this class
     * actually reads (TaxCalculationService's gross/VAT math, discountsSummary's
     * name/discount fields, revenueByCategory's category link) — `menu_items`
     * also carries several large TEXT columns (description, ingredients,
     * allergies, translations, ...) that were previously hydrated in full for
     * every cart item in the report, real and unused memory on a vendor with a
     * large order history. `category` is deliberately left unrestricted:
     * `MenuCategory::$name` is an accessor that resolves through the
     * `masterCategory` relation (see MenuCategory::getDisplayNameAttribute()),
     * so trimming its columns risks silently breaking category names or
     * forcing a lazy-load per row instead of the existing per-category cost.
     * `recipeIngredients` only needs to answer `isNotEmpty()`
     * (costOfGoodsSoldCoveragePercent()), so it's restricted to its id/FK.
     *
     * @return array<int|string, string|\Closure>
     */
    private function menuItemEagerLoad(): array
    {
        return [
            'menuItem' => fn ($q) => $q->select([
                'id', 'menu_category_id', 'name', 'price', 'vat_rate',
                'tax_category', 'has_discount', 'discounted_price', 'discount_percent',
            ]),
            'menuItem.category',
            'menuItem.recipeIngredients' => fn ($q) => $q->select(['id', 'menu_item_id']),
        ];
    }

    // ------------------------------------------------------------------- queries

    private function ordersIn(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Order::query()
            ->where('vendor_id', $vendor->id)
            ->where('payment_received', true)
            ->whereNotNull('payment_confirmed_at')
            ->whereNull('cancelled_at')
            ->whereBetween('payment_confirmed_at', [$from, $to])
            ->get([
                'id', 'vendor_id', 'order_public_id', 'invoice_number', 'amount', 'service_fee',
                'tip_amount', 'order_type', 'payment_method', 'payment_confirmed_at',
                'table_scan_session_id',
            ]);
    }

    /**
     * Same filter as ordersIn(), a bare COUNT — used by the controller to
     * decide whether a period is cheap enough to build live or big enough to
     * hand off to the background job (see FinancialReportJob's doc comment).
     * Deliberately public: the only method on this class a caller outside it
     * needs before deciding whether to call build() at all.
     */
    public function ordersCountIn(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return Order::query()
            ->where('vendor_id', $vendor->id)
            ->where('payment_received', true)
            ->whereNotNull('payment_confirmed_at')
            ->whereNull('cancelled_at')
            ->whereBetween('payment_confirmed_at', [$from, $to])
            ->count();
    }

    /**
     * Every cart item that genuinely contributes to at least one order in
     * `$orders` — owned outright, or shared into from another order's
     * cart, mirroring the same "owned + sharedInto" query
     * ShareOrderService::recalcOrder() already uses to compute the order's
     * own real charged `amount` (the figure every total in this report
     * must reconcile against).
     *
     * Previously this only fetched items an order in the period *owns* —
     * when two orders split a bill, the sharer's half of a shared item
     * never appeared anywhere in this report (not in the tax breakdown,
     * not in revenue by category, not in discounts), silently
     * understating all three by exactly the sharer's share, even though
     * `orders.amount` for that sharer's order genuinely includes it.
     *
     * Each returned item carries a synthetic `attributedOrderId` — NOT
     * the same as the item's real `order_id` column for a shared-into
     * entry. Every per-order computation (vatByOrderId) must group by
     * `attributedOrderId`, not `order_id`, or it silently re-attributes a
     * sharer's portion back to the owner. An item shared among several
     * orders that are ALL inside this period legitimately appears
     * several times in the returned collection, once per attributed
     * order — each occurrence's own `shared_order_ids`-derived share
     * count (read by TaxCalculationService/discountsSummary/
     * revenueByCategory) still divides it down to that one order's real
     * 1/N portion, so the sum across all occurrences reconciles exactly
     * to the item's full gross, matching how it was actually charged
     * across those N separate orders.
     *
     * @return Collection<int, CartItem>
     */
    private function cartItemsFor(Collection $orders, Collection $sharedCandidates, bool $withCatalogRelations = true): Collection
    {
        if ($orders->isEmpty()) {
            return collect();
        }

        $orderIds = $orders->pluck('id');
        $sessionIdByOrderId = $orders->pluck('table_scan_session_id', 'id');

        // Chunked at 1,000 IDs per query — a single `whereIn('order_id', $orderIds)`
        // with every order ID in the period hit SQLite's ~32,766 bound-parameter
        // ceiling (confirmed: a 731-day custom range at realistic multi-year
        // volume crashes outright) and would keep growing on MySQL/Postgres.
        // Same failure class as the shared-bill query fixed below, just on the
        // owned-items half instead. $withCatalogRelations is false for the
        // previous-period call (see ordersAndCartItemsFor()) — that window's
        // items never reach revenueByCategory()/costOfGoodsSoldCoveragePercent(),
        // the only two callers that read category/recipeIngredients.
        $owned = collect();
        foreach ($orderIds->chunk(1000) as $orderIdChunk) {
            $query = CartItem::whereIn('order_id', $orderIdChunk);
            $query = $withCatalogRelations
                ? $query->with($this->menuItemEagerLoad())
                : $query->with(['menuItem' => fn ($q) => $q->select([
                    'id', 'menu_category_id', 'name', 'price', 'vat_rate',
                    'tax_category', 'has_discount', 'discounted_price', 'discount_percent',
                ])]);
            $owned = $owned->concat($query->get());
        }
        $owned->each(function (CartItem $item) {
            $item->attributedOrderId = (int) $item->order_id;
        });

        // $sharedCandidates is this vendor's full shared-item history, fetched
        // once by the caller (see sharedIntoCandidates()) — deliberately NOT
        // restricted to orders outside $orderIds, since a shared item's owner
        // and sharer are usually BOTH in the same report period, and each
        // still needs its own attributed entry. The intersect-and-dedupe loop
        // below does the actual "does this row belong to one of $orderIds"
        // check per row in PHP.
        $expanded = collect();
        foreach ($sharedCandidates as $item) {
            $sharingOrderIds = collect($item->shared_order_ids ?? [])->map(fn ($id) => (int) $id);

            foreach ($sharingOrderIds->intersect($orderIds) as $sharingOrderId) {
                // Mirrors recalcOrder()'s own exclusion — a share within
                // the same dine-in session isn't a genuine cross-order
                // split, it's already represented via the owning order.
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

        // concat(), NOT merge() — Eloquent's Collection::merge() de-
        // duplicates by primary key, and every clone above deliberately
        // keeps the SAME id as its source item (for traceability). Using
        // merge() here silently collapsed a shared item's owned and
        // shared-into entries back down to one, exactly re-introducing
        // the bug this method exists to fix.
        return $owned->concat($expanded);
    }

    private function refundsIn(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Refund::query()
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->where('type', 'refund')
            ->where('status', 'approved')
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$from, $to])
            ->get(['id', 'order_id', 'amount', 'reason', 'resolved_at']);
    }

    /**
     * VAT per order, derived from that order's own cart items — see the
     * class-level doc comment for why this is used everywhere instead of
     * `orders.vat_amount`. One computeTaxGroups() call per order is cheap
     * at the vendor scale this report runs at (the same cost profile
     * VendorAnalyticsService already accepts for its 12-month lookback).
     * `applySharing: true` matches how the order's own `amount` was
     * actually charged (CartController/PaymentController both split a
     * shared line's gross by its sharer count) — without it, a shared
     * order's derived VAT is computed against a bigger gross than the
     * order was ever actually charged.
     *
     * @return array<int, float> order_id => vatAmount
     */
    private function vatByOrderId(Collection $cartItems, Vendor $vendor): array
    {
        $country = $vendor->country ?? '';

        return $cartItems->groupBy(fn (CartItem $item) => $item->attributedOrderId ?? $item->order_id)
            ->map(function (Collection $items) use ($country) {
                $groups = TaxCalculationService::computeTaxGroups($items, $country, applySharing: true);

                return round((float) array_sum(array_column($groups, 'vat_amount')), 2);
            })
            ->all();
    }

    // ------------------------------------------------------------------- sections

    private function summary(
        Collection $orders,
        Collection $previousOrders,
        float $vatTotal,
        float $previousVatTotal,
        float $refundsTotal,
        float $previousRefundsTotal,
        float $cogs,
        float $previousCogs,
        float $laborCost,
        float $previousLaborCost,
        float $otherCosts,
        float $previousOtherCosts,
        float $subscriptionFees,
        float $previousSubscriptionFees,
        float $waste,
        float $previousWaste,
    ): array {
        $gross = round((float) $orders->sum('amount'), 2);
        $grossPrev = round((float) $previousOrders->sum('amount'), 2);

        $tips = round((float) $orders->sum('tip_amount'), 2);
        $tipsPrev = round((float) $previousOrders->sum('tip_amount'), 2);

        $serviceFees = round((float) $orders->sum('service_fee'), 2);
        $serviceFeesPrev = round((float) $previousOrders->sum('service_fee'), 2);

        $totalOrders = $orders->count();
        $totalOrdersPrev = $previousOrders->count();

        $netRevenue = round($gross - $vatTotal, 2);
        $netRevenuePrev = round($grossPrev - $previousVatTotal, 2);

        $grossProfit = round($netRevenue - $cogs, 2);
        $grossProfitPrev = round($netRevenuePrev - $previousCogs, 2);

        $margin = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 1) : null;
        $marginPrev = $netRevenuePrev > 0 ? round(($grossProfitPrev / $netRevenuePrev) * 100, 1) : null;

        $primeCost = round($cogs + $laborCost, 2);
        $primeCostPrev = round($previousCogs + $previousLaborCost, 2);

        $primeCostPercent = $netRevenue > 0 ? round(($primeCost / $netRevenue) * 100, 1) : null;
        $primeCostPercentPrev = $netRevenuePrev > 0 ? round(($primeCostPrev / $netRevenuePrev) * 100, 1) : null;

        // "Food Cost %" and "Labor Cost %" are the two halves restaurant
        // operators actually watch day to day — Prime Cost % (above) is
        // their sum, useful as a single health check, but hides which of
        // the two is driving it. Same net-revenue divisor as Prime Cost %.
        $foodCostPercent = $netRevenue > 0 ? round(($cogs / $netRevenue) * 100, 1) : null;
        $foodCostPercentPrev = $netRevenuePrev > 0 ? round(($previousCogs / $netRevenuePrev) * 100, 1) : null;

        $laborCostPercent = $netRevenue > 0 ? round(($laborCost / $netRevenue) * 100, 1) : null;
        $laborCostPercentPrev = $netRevenuePrev > 0 ? round(($previousLaborCost / $netRevenuePrev) * 100, 1) : null;

        // Waterfall continues past Gross Profit — see the class doc comment
        // for why `costs.inventoryPurchases` never enters this chain.
        $operatingExpenses = round($otherCosts + $subscriptionFees, 2);
        $operatingExpensesPrev = round($previousOtherCosts + $previousSubscriptionFees, 2);

        $operatingProfit = round($grossProfit - $laborCost - $operatingExpenses, 2);
        $operatingProfitPrev = round($grossProfitPrev - $previousLaborCost - $operatingExpensesPrev, 2);

        $netProfit = round($operatingProfit - $waste - $refundsTotal, 2);
        $netProfitPrev = round($operatingProfitPrev - $previousWaste - $previousRefundsTotal, 2);

        $netMargin = $netRevenue > 0 ? round(($netProfit / $netRevenue) * 100, 1) : null;
        $netMarginPrev = $netRevenuePrev > 0 ? round(($netProfitPrev / $netRevenuePrev) * 100, 1) : null;

        // Tips sit outside `amount` entirely (confirmed in VendorAnalyticsService's
        // own doc comment) — they were never part of gross/net revenue above,
        // so this rate is against gross, not double-counted into it.
        $tipRate = $gross > 0 ? round(($tips / $gross) * 100, 1) : null;
        $tipRatePrev = $grossPrev > 0 ? round(($tipsPrev / $grossPrev) * 100, 1) : null;

        return [
            'grossRevenue' => $this->metric($gross, $grossPrev),
            'netRevenue' => $this->metric($netRevenue, $netRevenuePrev),
            'totalTax' => $this->metric($vatTotal, $previousVatTotal),
            'totalOrders' => $this->metric($totalOrders, $totalOrdersPrev, 'absolute'),
            'avgOrderValue' => $this->metric(
                $totalOrders ? round($gross / $totalOrders, 2) : null,
                $totalOrdersPrev ? round($grossPrev / $totalOrdersPrev, 2) : null,
            ),
            'totalTips' => $this->metric($tips, $tipsPrev),
            'tipRatePercent' => $this->metric($tipRate, $tipRatePrev, 'absolute'),
            'totalServiceFees' => $this->metric($serviceFees, $serviceFeesPrev),
            'totalRefunds' => $this->metric($refundsTotal, $previousRefundsTotal),
            'costOfGoodsSold' => $this->metric($cogs, $previousCogs),
            'costOfGoodsSoldPercent' => $this->metric($foodCostPercent, $foodCostPercentPrev, 'absolute'),
            'grossProfit' => $this->metric($grossProfit, $grossProfitPrev),
            'grossMarginPercent' => $this->metric($margin, $marginPrev, 'absolute'),
            'laborCost' => $this->metric($laborCost, $previousLaborCost),
            'laborCostPercent' => $this->metric($laborCostPercent, $laborCostPercentPrev, 'absolute'),
            'primeCost' => $this->metric($primeCost, $primeCostPrev),
            'primeCostPercent' => $this->metric($primeCostPercent, $primeCostPercentPrev, 'absolute'),
            'operatingExpenses' => $this->metric($operatingExpenses, $operatingExpensesPrev),
            'operatingProfit' => $this->metric($operatingProfit, $operatingProfitPrev),
            'netProfit' => $this->metric($netProfit, $netProfitPrev),
            'netMarginPercent' => $this->metric($netMargin, $netMarginPrev, 'absolute'),
        ];
    }

    /**
     * Real consumption cost — see the class doc comment for the caveat
     * about menu items without recipe/inventory tracking. `type='order'`
     * rows are written by InventoryConsumptionService at the moment stock
     * is deducted for a placed order; `quantity_change` is negative, so
     * ABS(...) × cost_per_unit gives the cost of what was actually sold.
     */
    /**
     * Matched by `cart_item_id`, not `order_id` — a shared dish's
     * inventory deduction is recorded exactly once, against whichever of
     * the two split orders happened to complete first
     * (InventoryConsumptionService::deductForCompletedOrder() processes
     * both the owner's and every sharer's cart items the moment either
     * order completes, then marks them deducted so the other side never
     * re-deducts). If that first-completing order falls in a different
     * report period than its co-diner's, matching by `order_id` alone
     * would silently drop the cost from both periods' reports. `$cartItems`
     * already carries every item connected to this period, owned or
     * shared-into (see cartItemsFor()), so matching on the item itself —
     * every `type='order'` movement always has a `cart_item_id` — finds
     * the deduction regardless of which side of the split it landed on,
     * with no risk of pulling in a co-diner's unrelated, non-shared items
     * the way matching on their whole `order_id` would.
     */
    private function costOfGoodsSold(Collection $orders, Collection $cartItems): float
    {
        if ($orders->isEmpty()) {
            return 0.0;
        }

        // Chunked for the same reason as cartItemsFor()'s owned-items query
        // above — the identical pattern crashed there at realistic scale, and
        // this call has no lower a ceiling on cart-item count.
        $total = 0.0;
        foreach ($cartItems->pluck('id')->unique()->chunk(1000) as $cartItemIdChunk) {
            $total += InventoryStockMovement::query()
                ->where('type', 'order')
                ->whereIn('cart_item_id', $cartItemIdChunk)
                ->with('inventoryItem:id,cost_per_unit')
                ->get()
                ->sum(fn (InventoryStockMovement $movement) => abs((float) $movement->quantity_change) * (float) ($movement->inventoryItem?->cost_per_unit ?? 0));
        }

        return round((float) $total, 2);
    }

    /**
     * What share of this period's gross revenue came from menu items that
     * actually have inventory/recipe tracking configured — the number the
     * "Cost of Goods Sold only reflects tracked items" caveat has always
     * referred to without ever saying how much of the catalog that really
     * is. A vendor with 5% coverage and one with 95% coverage previously
     * saw the exact same soft caveat text; the frontend uses this to
     * escalate that caveat's visual weight when coverage is low, since a
     * low-coverage Gross/Net Profit figure could be wildly understated
     * rather than just "a little conservative." `null` when there's no
     * revenue at all to compute a share of, so the frontend can tell
     * "no data" apart from "0% covered."
     */
    private function costOfGoodsSoldCoveragePercent(Collection $cartItems, Vendor $vendor): ?float
    {
        $country = $vendor->country ?? '';
        $trackedGross = 0.0;
        $totalGross = 0.0;

        foreach ($cartItems as $item) {
            if (! $item->menuItem) {
                continue;
            }

            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $gross = TaxCalculationService::cartItemLineTotalGross($item, $country) / $shareCount;
            $totalGross += $gross;

            if ($item->menuItem->relationLoaded('recipeIngredients') && $item->menuItem->recipeIngredients->isNotEmpty()) {
                $trackedGross += $gross;
            }
        }

        return $totalGross > 0 ? round(($trackedGross / $totalGross) * 100, 1) : null;
    }

    /**
     * Real labor cost from `staff_shifts` — hourly_rate is snapshotted per
     * shift (see the migration doc comment), so a later wage change never
     * rewrites historical labor cost. Windowed by `clock_in_at`, same
     * "when the work actually happened" logic used everywhere else in this
     * service. Shifts still clocked in (`clock_out_at` null) contribute 0
     * hours/cost until they close out.
     */
    private function laborCost(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $shifts = StaffShift::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('clock_in_at', [$from, $to])
            ->get();

        $hours = round((float) $shifts->sum(fn (StaffShift $s) => $s->hours()), 2);
        $amount = round((float) $shifts->sum(fn (StaffShift $s) => $s->cost()), 2);

        return [
            'amount' => $amount,
            'hours' => $hours,
            'shiftCount' => $shifts->count(),
            // `contains()`, not `every()` — a period mixing real and demo
            // shifts (not possible today with no real clock-in feature yet,
            // but will be the moment one ships) must still flag the whole
            // figure as demo-tainted rather than silently presenting a
            // partially-fabricated number as fully measured.
            'isDemoData' => $shifts->contains(fn (StaffShift $s) => $s->source === 'demo'),
        ];
    }

    /**
     * A direct-method Cash Flow Statement — always bucketed by calendar
     * month regardless of the report's own `bucketUnit`, per vendor
     * feedback: many restaurant costs (rent, subscription fees, monthly
     * supplier settlements) only happen once a month, so a daily/weekly
     * bucket would mostly be empty rows either way.
     *
     * Uses `$period->cashFlowRange()`, NOT `$period->queryStart()`/
     * `queryEnd()` — every other section of this report reflects exactly
     * what the vendor selected (Today, This week, ...), but this one
     * always expands to the full calendar month(s) containing that
     * selection. Without that, "Today" or "This week" would show a cash
     * flow row where lumpy monthly costs (rent, a supplier settlement)
     * read as zero simply because they didn't happen on that exact day,
     * defeating the entire point of a monthly cash flow view. That means
     * this re-queries orders/refunds/costs/labor itself over the wider
     * range rather than reusing what `build()` already fetched for the
     * narrower selected period.
     *
     * Deliberately uses cash PAID for inventory (`purchaseOrders`, i.e.
     * `costs.inventoryPurchases`) as the Inventory line, NOT
     * `costOfGoodsSold` — COGS is accrual-basis consumption (what was
     * sold), purchases are the actual cash outflow (what was paid to
     * suppliers), and they happen at different times. The P&L waterfall
     * above deliberately excludes purchases to avoid double-counting
     * against COGS; a cash flow statement wants the opposite: it only
     * cares about when cash actually moved.
     *
     * `cashIn` is the gross amount actually charged (`orders.amount`),
     * VAT included — that whole amount is real cash that landed in the
     * vendor's till/bank, so excluding VAT would understate real cash
     * received. The VAT portion is a liability owed to the tax authority,
     * not something Tavlo has a "remit VAT" transaction for, so unlike a
     * textbook cash flow statement this doesn't have a matching future
     * cash-out line for it — a real gap in what data exists, not a claim
     * that VAT has already been paid out.
     *
     * Tavlo also has no bank-balance, loan, or equipment-purchase data, so
     * there is nothing real to put in the standard Investing/Financing
     * sections of a cash flow statement, and no starting cash position to
     * build a running balance from — this only ever covers Operating
     * Activities and never fabricates either. The frontend must present
     * both of these as a caveat, not silently omit them.
     */
    private function cashFlowStatement(Vendor $vendor, FinancialReportPeriod $period): array
    {
        $tz = $period->timezone;
        [$rangeStart, $rangeEnd] = $period->cashFlowRange();
        $appTz = config('app.timezone');
        $queryStart = $rangeStart->setTimezone($appTz);
        $queryEnd = $rangeEnd->setTimezone($appTz);

        $orders = $this->ordersIn($vendor, $queryStart, $queryEnd);
        $refunds = $this->refundsIn($vendor, $queryStart, $queryEnd);
        $purchaseOrders = $this->purchaseOrdersList($vendor, $queryStart, $queryEnd);
        $subscriptionInvoices = $this->subscriptionInvoicesList($vendor, $queryStart, $queryEnd);
        $manualExpensesList = $this->manualExpenses($vendor, $queryStart, $queryEnd)['items'];

        $monthKey = fn ($moment): string => $moment->setTimezone($tz)->startOfMonth()->toDateString();

        $months = [];
        $cursor = $rangeStart;
        $end = $rangeEnd;
        for ($guard = 0; $cursor->lte($end) && $guard < 400; $guard++) {
            $months[$cursor->toDateString()] = [
                'month' => $cursor->toDateString(),
                'cashIn' => 0.0,
                'cashOutInventory' => 0.0,
                'cashOutLabor' => 0.0,
                'cashOutSubscriptionFees' => 0.0,
                'cashOutOtherExpenses' => 0.0,
                'cashOutRefunds' => 0.0,
            ];
            $cursor = $cursor->addMonth();
        }

        foreach ($orders as $order) {
            $key = $monthKey($order->payment_confirmed_at);
            if (isset($months[$key])) {
                $months[$key]['cashIn'] += (float) $order->amount;
            }
        }

        foreach ($refunds as $refund) {
            $key = $monthKey($refund->resolved_at);
            if (isset($months[$key])) {
                $months[$key]['cashOutRefunds'] += (float) $refund->amount;
            }
        }

        foreach ($purchaseOrders as $po) {
            $key = $monthKey(CarbonImmutable::parse($po['date']));
            if (isset($months[$key])) {
                $months[$key]['cashOutInventory'] += (float) $po['amount'];
            }
        }

        foreach ($subscriptionInvoices as $invoice) {
            $key = $monthKey(CarbonImmutable::parse($invoice['date']));
            if (isset($months[$key])) {
                $months[$key]['cashOutSubscriptionFees'] += (float) $invoice['amount'];
            }
        }

        foreach ($manualExpensesList as $expense) {
            $key = $monthKey(CarbonImmutable::parse($expense['occurredAt']));
            if (isset($months[$key])) {
                $months[$key]['cashOutOtherExpenses'] += (float) $expense['amount'];
            }
        }

        $shifts = StaffShift::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('clock_in_at', [$queryStart, $queryEnd])
            ->get();

        $isLaborDemoData = false;
        foreach ($shifts as $shift) {
            $key = $monthKey($shift->clock_in_at);
            if (isset($months[$key])) {
                $months[$key]['cashOutLabor'] += $shift->cost();
            }
            if ($shift->source === 'demo') {
                $isLaborDemoData = true;
            }
        }

        $rows = array_values(array_map(function (array $row) {
            $cashOut = $row['cashOutInventory'] + $row['cashOutLabor'] + $row['cashOutSubscriptionFees']
                + $row['cashOutOtherExpenses'] + $row['cashOutRefunds'];

            return [
                'month' => $row['month'],
                'cashIn' => round($row['cashIn'], 2),
                'cashOutInventory' => round($row['cashOutInventory'], 2),
                'cashOutLabor' => round($row['cashOutLabor'], 2),
                'cashOutSubscriptionFees' => round($row['cashOutSubscriptionFees'], 2),
                'cashOutOtherExpenses' => round($row['cashOutOtherExpenses'], 2),
                'cashOutRefunds' => round($row['cashOutRefunds'], 2),
                'netCashFlow' => round($row['cashIn'] - $cashOut, 2),
            ];
        }, $months));

        $totals = [
            'cashIn' => round((float) array_sum(array_column($rows, 'cashIn')), 2),
            'cashOutInventory' => round((float) array_sum(array_column($rows, 'cashOutInventory')), 2),
            'cashOutLabor' => round((float) array_sum(array_column($rows, 'cashOutLabor')), 2),
            'cashOutSubscriptionFees' => round((float) array_sum(array_column($rows, 'cashOutSubscriptionFees')), 2),
            'cashOutOtherExpenses' => round((float) array_sum(array_column($rows, 'cashOutOtherExpenses')), 2),
            'cashOutRefunds' => round((float) array_sum(array_column($rows, 'cashOutRefunds')), 2),
            'netCashFlow' => round((float) array_sum(array_column($rows, 'netCashFlow')), 2),
        ];

        return [
            'monthly' => $rows,
            'totals' => $totals,
            'isLaborDemoData' => $isLaborDemoData,
            // True whenever the selected report period (Today, This week,
            // a custom range) is narrower than a calendar month — tells the
            // frontend to say "this covers the full month" rather than let
            // a vendor assume these figures are scoped to what they picked
            // everywhere else on the page.
            'expandedToFullMonth' => ! $rangeStart->equalTo($period->start) || ! $rangeEnd->equalTo($period->end),
        ];
    }

    /**
     * Same math as VendorAnalyticsService::discounts() (see class doc
     * comment) — a cart line is "discounted" when the menu item version it
     * points at had has_discount=true at the moment it was ordered.
     * `revenueForgone` is the headline bookkeeping figure: list-price gross
     * minus what was actually charged, both VAT-inclusive.
     */
    private function discountsSummary(Collection $cartItems, Collection $orders, Vendor $vendor): array
    {
        $country = $vendor->country ?? '';
        $discountedOrderIds = [];
        $discountedRevenue = 0.0;
        $listRevenue = 0.0;
        $depths = [];
        // Keyed by menu_item_id, not name — a price/discount change creates
        // a new MenuItem row (see cartItemsFor()'s versioning note), so two
        // rows sharing a name can legitimately carry different prices here.
        $itemTotals = [];
        // Tracks which physical cart-item rows have already contributed to
        // quantityOrdered — see the dedup note below.
        $seenLineIds = [];

        foreach ($cartItems as $line) {
            $item = $line->menuItem;

            if (! $item || ! $item->has_discount || $item->discounted_price === null) {
                continue;
            }

            // Same sharing divisor as computeTaxGroups()/recalcOrder() — a
            // shared line's discounted/list revenue must be split across
            // its sharers the same way its actual charge is, or a shared
            // discounted item is double-counted here (see cartItemsFor()).
            $shareCount = 1 + count($line->shared_order_ids ?? []);
            $discountedOrderIds[$line->attributedOrderId ?? $line->order_id] = true;

            $vat = TaxCalculationService::itemVatRate($item, $country);
            $lineDiscountedGross = (TaxCalculationService::gross((float) $item->discounted_price, $vat) * (int) $line->quantity) / $shareCount;
            $lineListGross = (TaxCalculationService::gross((float) $item->price, $vat) * (int) $line->quantity) / $shareCount;
            $discountedRevenue += $lineDiscountedGross;
            $listRevenue += $lineListGross;
            $depths[] = (float) $item->discount_percent;

            $itemTotals[$item->id] ??= [
                'name' => $item->name,
                // The menu's own displayed prices, not the gross figures
                // above — those are scaled by quantity/VAT for the P&L
                // total, this is just "what the menu says for one unit".
                'originalPrice' => (float) $item->price,
                'discountedPrice' => (float) $item->discounted_price,
                'discountPercent' => (float) $item->discount_percent,
                'revenueForgone' => 0.0,
                'quantityOrdered' => 0,
            ];
            $itemTotals[$item->id]['revenueForgone'] += $lineListGross - $lineDiscountedGross;

            // Quantity is a physical count, not a monetary split — unlike
            // revenue above, it must NOT be divided by $shareCount, but it
            // DOES need deduping: cartItemsFor()'s owned+sharedInto
            // expansion means the same physical row (same $line->id)
            // appears once per sharer, and counting it each time would
            // make a single plate look like it was ordered once per person
            // splitting the bill for it.
            if (! isset($seenLineIds[$line->id])) {
                $itemTotals[$item->id]['quantityOrdered'] += (int) $line->quantity;
                $seenLineIds[$line->id] = true;
            }
        }

        $discountedOrders = count($discountedOrderIds);
        $totalOrders = $orders->count();

        return [
            'discountedOrders' => $discountedOrders,
            'discountedSharePercent' => $totalOrders ? round(($discountedOrders / $totalOrders) * 100, 1) : null,
            'discountedRevenue' => round($discountedRevenue, 2),
            'revenueForgone' => round($listRevenue - $discountedRevenue, 2),
            'averageDiscountPercent' => $depths ? round(array_sum($depths) / count($depths), 1) : null,
            // Ranked by revenue forgone — the most bookkeeping-relevant
            // discounts first, not just insertion order.
            'topDiscountedItems' => collect($itemTotals)
                ->sortByDesc('revenueForgone')
                ->values()
                ->map(fn (array $i) => [
                    'name' => $i['name'],
                    'originalPrice' => round($i['originalPrice'], 2),
                    'discountedPrice' => round($i['discountedPrice'], 2),
                    'discountPercent' => round($i['discountPercent'], 1),
                    'quantityOrdered' => $i['quantityOrdered'],
                ])
                ->all(),
        ];
    }

    /**
     * Formats already-computed TaxCalculationService::computeTaxGroups()
     * output into the API shape. Category labels are intentionally NOT
     * included here: `taxCategory` is a stable slug (food,
     * beverage_non_alcoholic, beverage_alcoholic, and legacy
     * drinks_alcoholic/drinks_non_alcoholic variants seen in real vendor
     * data) and the frontend renders the vendor-language label for it, the
     * same way it renders every other piece of UI copy. Returning an
     * English label from the backend would bypass that i18n layer.
     */
    private function formatTaxBreakdown(array $groups): array
    {
        return [
            'groups' => array_map(fn (array $g) => [
                'code' => $g['code'],
                'taxCategory' => $g['tax_category'],
                'vatRate' => $g['vat_rate'],
                'netAmount' => $g['net_amount'],
                'vatAmount' => $g['vat_amount'],
                'grossAmount' => $g['gross_amount'],
            ], $groups),
            'totals' => [
                'netAmount' => round((float) array_sum(array_column($groups, 'net_amount')), 2),
                'vatAmount' => round((float) array_sum(array_column($groups, 'vat_amount')), 2),
                'grossAmount' => round((float) array_sum(array_column($groups, 'gross_amount')), 2),
            ],
        ];
    }

    private function revenueByOrderType(Collection $orders): array
    {
        return $orders->groupBy(fn (Order $order) => $order->order_type ?: 'unknown')
            ->map(fn (Collection $group, string $type) => [
                'orderType' => $type,
                'orders' => $group->count(),
                'grossAmount' => round((float) $group->sum('amount'), 2),
            ])
            ->values()
            ->sortByDesc('grossAmount')
            ->values()
            ->all();
    }

    /**
     * `category` is the vendor's own menu category name (e.g. "Mains",
     * "Starters") — free text the vendor wrote themselves, not a slug.
     * Returned as-is; the frontend does not attempt to translate it, the
     * same way it never translates a refund's freeform `reason`. Items
     * whose menu category was deleted/never set roll up under a null
     * `categoryId` — the frontend renders that with a translated
     * "Uncategorized" label rather than a raw fallback.
     */
    private function revenueByCategory(Collection $cartItems, Vendor $vendor): array
    {
        $country = $vendor->country ?? '';

        return $cartItems
            ->filter(fn (CartItem $item) => $item->menuItem !== null)
            ->groupBy(fn (CartItem $item) => $item->menuItem->category?->id ?? 0)
            ->map(function (Collection $items, int $categoryId) use ($country) {
                // Same sharing divisor as computeTaxGroups()/recalcOrder()
                // — see cartItemsFor()'s doc comment. A shared line now
                // appears once per order attributed a share of it, so
                // without dividing, its gross would be double-counted.
                $gross = $items->sum(function (CartItem $item) use ($country) {
                    $shareCount = 1 + count($item->shared_order_ids ?? []);

                    return TaxCalculationService::cartItemLineTotalGross($item, $country) / $shareCount;
                });

                // Quantity is a physical count, not a monetary split — a
                // shared dish was still one unit served regardless of how
                // many people split the bill for it, so it's counted once
                // per real cart item (by id) even though the same item
                // may appear here several times, once per attributed
                // order.
                $quantity = $items->unique('id')->sum('quantity');

                return [
                    'categoryId' => $categoryId ?: null,
                    'category' => $categoryId ? $items->first()->menuItem->category?->name : null,
                    'grossAmount' => round((float) $gross, 2),
                    'quantity' => (int) $quantity,
                ];
            })
            ->values()
            ->sortByDesc('grossAmount')
            ->values()
            ->all();
    }

    /**
     * `method` is the raw `orders.payment_method` value ('cash', 'card', ...)
     * — a small, stable set the frontend translates like `orderType`.
     * Surfaced because cash needs manual bank deposit/reconciliation while
     * card settles automatically via Stripe — a distinction every POS
     * financial report (Toast, Square) calls out explicitly.
     */
    private function paymentMethodBreakdown(Collection $orders): array
    {
        return $orders->groupBy(fn (Order $order) => $order->payment_method ?: 'unknown')
            ->map(fn (Collection $group, string $method) => [
                'method' => $method,
                'orders' => $group->count(),
                'grossAmount' => round((float) $group->sum('amount'), 2),
            ])
            ->values()
            ->sortByDesc('grossAmount')
            ->values()
            ->all();
    }

    /**
     * Tips by payment method — the real bookkeeping question, distinct from
     * Analytics' operational tip-behavior reporting (participation rate,
     * service-speed correlation, etc., which this feature deliberately
     * does not duplicate). A cash tip is already in the server's pocket;
     * a card tip was captured through Stripe and is money the vendor
     * physically holds until it's paid out to staff — a real liability a
     * bookkeeper needs to see separately from the vendor's own revenue,
     * the same reason `costs`/`paymentMethods` split cash from card.
     */
    private function tipsByPaymentMethod(Collection $orders): array
    {
        return $orders->groupBy(fn (Order $order) => $order->payment_method ?: 'unknown')
            ->map(fn (Collection $group, string $method) => [
                'method' => $method,
                'orders' => $group->count(),
                'amount' => round((float) $group->sum('tip_amount'), 2),
            ])
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->values()
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * Orders cancelled in the period — never paid, so never part of any
     * revenue figure above. Windowed by `cancelled_at` (when the
     * cancellation happened), not `payment_confirmed_at` (cancelled orders
     * don't have one). Purely informational: "how much business walked
     * away", matching Toast/Square's "Cash and Loss Management" voided-
     * orders reporting. Excludes `payment_received` orders: OrderController's
     * cancel() blocks cancelling a paid order (it must be refunded instead),
     * but this filter also protects the doc comment's guarantee above against
     * any other path that sets cancelled_at on a paid order.
     */
    private function voidedOrders(Vendor $vendor, FinancialReportPeriod $period): array
    {
        $result = Order::query()
            ->where('vendor_id', $vendor->id)
            ->where('payment_received', false)
            ->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', [$period->queryStart(), $period->queryEnd()])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->first();

        return [
            'count' => (int) ($result->count ?? 0),
            'amount' => round((float) ($result->amount ?? 0), 2),
        ];
    }

    /**
     * `pendingCount` covers refunds/disputes still `open` (requested but not
     * yet approved or declined) — informational only, deliberately excluded
     * from every monetary total since that money hasn't actually moved yet.
     */
    private function refundsSummary(Vendor $vendor, FinancialReportPeriod $period, Collection $approvedRefunds): array
    {
        $byReason = $approvedRefunds->groupBy(fn (Refund $r) => $r->reason ?: 'unspecified')
            ->map(fn (Collection $group, string $reason) => [
                'reason' => $reason,
                'count' => $group->count(),
                'amount' => round((float) $group->sum('amount'), 2),
            ])
            ->values()
            ->sortByDesc('amount')
            ->values()
            ->all();

        $pendingCount = Refund::query()
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->where('status', 'open')
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->count();

        return [
            'count' => $approvedRefunds->count(),
            'totalAmount' => round((float) $approvedRefunds->sum('amount'), 2),
            'byReason' => $byReason,
            'pendingCount' => $pendingCount,
        ];
    }

    /**
     * Three real cost sources, all approximate/self-reported by nature and
     * clearly labelled as such to the frontend via their own section (never
     * folded into "net revenue"):
     *
     *  - Inventory purchases: quantity × unit_cost on InventoryPurchaseOrder
     *    rows placed in the period (excludes `failed` dispatches — those
     *    never actually went to a supplier). Windowed by `created_at`
     *    (the purchasing decision date) since these orders have no
     *    "received"/"delivered" status to recognise cost against instead.
     *  - Subscription/platform fees: paid Tavlo invoices for the vendor's
     *    subscription in the period — the one real "platform fee" this
     *    system charges today (there is no per-order application fee
     *    anywhere in this codebase; Stripe's application_fee_amount is not
     *    used, so it is intentionally absent from this report rather than
     *    invented as an always-zero placeholder).
     *  - Manual expenses: vendor-entered `FinancialExpense` rows for costs
     *    with no other real data source in Tavlo (rent, repairs, equipment,
     *    insurance, etc.) — see that model's doc comment. These are real,
     *    vendor-attested cash outflows, so they belong in `totalCosts`
     *    exactly like the two sources above (unlike `inventoryWaste`,
     *    which is deliberately kept OUT of this total — see that method).
     */
    /**
     * `totalCosts` here is a cash-outflow total (inventory buying +
     * subscription fees + manual expenses) — a different question from
     * `summary.operatingExpenses` (manual expenses + subscription fees
     * only), which deliberately excludes inventory purchases because those
     * are already represented in the Profit & Loss chain via
     * `costOfGoodsSold`. The two totals are expected to disagree; see the
     * class doc comment.
     */
    private function costs(Vendor $vendor, FinancialReportPeriod $period, array $subscriptionInvoices, array $manualExpenses): array
    {
        // Itemized first, aggregate derived from it — one query per source
        // instead of two, and the total is guaranteed to match the list.
        // $subscriptionInvoices / $manualExpenses are computed once in
        // build() (it needs both current AND previous windows for the P&L
        // waterfall) and passed in here rather than re-queried.
        $purchaseOrders = $this->purchaseOrdersList($vendor, $period->queryStart(), $period->queryEnd());

        $inventoryAmount = round((float) array_sum(array_column($purchaseOrders, 'amount')), 2);
        $subscriptionAmount = round((float) array_sum(array_column($subscriptionInvoices, 'amount')), 2);

        return [
            'inventoryPurchases' => [
                'count' => count($purchaseOrders),
                'amount' => $inventoryAmount,
            ],
            'subscriptionFees' => [
                'count' => count($subscriptionInvoices),
                'amount' => $subscriptionAmount,
            ],
            'manualExpenses' => [
                'count' => $manualExpenses['count'],
                'amount' => $manualExpenses['amount'],
                'recurringCount' => $manualExpenses['recurringCount'],
                'recurringAmount' => $manualExpenses['recurringAmount'],
            ],
            'totalCosts' => round($inventoryAmount + $subscriptionAmount + $manualExpenses['amount'], 2),
            // All three itemized lists below are expenditures, grouped
            // under `costs` (not a separate "invoices" section) per vendor
            // feedback — a subscription bill, a supplier order, and a rent
            // payment are all money going out, and belong next to the cost
            // totals they back up.
            'purchaseOrders' => $purchaseOrders,
            'subscriptionInvoices' => $subscriptionInvoices,
            'manualExpensesList' => $manualExpenses['items'],
        ];
    }

    /**
     * The real invoice register for the period: every paid order that has
     * an `invoice_number` (assigned from the vendor's own sequential
     * `vendor_settings.next_invoice_number` counter when the order was
     * confirmed — the same gapless numbering a tax authority expects to
     * see). Orders without one yet are excluded rather than shown with a
     * placeholder — an "invoice list" entry with no invoice number isn't
     * one.
     *
     * `orderReference` (= `order_public_id`) doubles as the id the
     * frontend passes to the existing `OrderReceiptModal` /
     * `api.getOrderReceipt()` — the same real invoice/receipt viewer
     * already used from the Orders page — so a vendor can view and
     * download the actual invoice document from here, not just see a
     * number. `date` is a full ISO datetime (not date-only) so the
     * frontend can render both the vendor's date format and time format.
     */
    private function salesInvoices(Collection $orders): array
    {
        return $orders
            ->filter(fn (Order $order) => ! empty($order->invoice_number))
            ->map(fn (Order $order) => [
                'invoiceNumber' => (string) $order->invoice_number,
                'orderReference' => $order->order_public_id,
                'date' => $order->payment_confirmed_at?->toIso8601String(),
                'amount' => round((float) $order->amount, 2),
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }

    /**
     * Real invoices Tavlo itself issued to this vendor for their
     * subscription — Tavlo is a supplier here, and these are genuine
     * formal invoices (invoice_number, amount, paid date), unlike
     * `purchaseOrdersList()` below. Same `status='paid'` + `paid_at`
     * filter as `subscriptionFees` above, itemized. `id` is the raw
     * `invoices.id`, needed by the existing
     * `GET /vendor/{vendorId}/billing/invoices/{id}/download` endpoint
     * (`api.downloadInvoice()`) so each row is a real downloadable PDF,
     * not just a line of text. `hasDocument` mirrors the exact same
     * computed check BillingSubscription.tsx already does client-side
     * (`pdf_url || stripe_hosted_url`) — real dev/seed invoices have
     * neither populated yet, so without this flag the frontend would
     * offer a Download button that always 404s.
     */
    private function subscriptionInvoicesList(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Invoice::query()
            ->whereHas('subscription', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->orderBy('paid_at')
            ->get(['id', 'invoice_number', 'amount', 'paid_at', 'pdf_url', 'stripe_hosted_url'])
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoiceNumber' => $invoice->invoice_number,
                'date' => $invoice->paid_at?->toIso8601String(),
                'amount' => round((float) $invoice->amount, 2),
                'hasDocument' => (bool) ($invoice->pdf_url || $invoice->stripe_hosted_url),
            ])
            ->all();
    }

    /**
     * Deliberately NOT called "invoices" — `inventory_purchase_orders` has
     * no invoice number, due date, or paid/unpaid status of its own, so
     * labeling these as formal invoices would fabricate paperwork that
     * doesn't exist on top of a real transaction. Same `status != 'failed'`
     * + `created_at` filter as `inventoryPurchases` above, itemized per
     * supplier order.
     */
    private function purchaseOrdersList(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return InventoryPurchaseOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', '!=', 'failed')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['purchase_order_public_id', 'supplier_name', 'quantity', 'unit', 'unit_cost', 'status', 'created_at'])
            ->map(fn (InventoryPurchaseOrder $po) => [
                'reference' => $po->purchase_order_public_id,
                'supplierName' => $po->supplier_name,
                'date' => $po->created_at?->toIso8601String(),
                'quantity' => (float) $po->quantity,
                'unit' => $po->unit,
                'amount' => round((float) $po->quantity * (float) $po->unit_cost, 2),
                'status' => $po->status,
            ])
            ->all();
    }

    /**
     * Vendor-entered "other costs" — see FinancialExpense's doc comment.
     * Windowed by `occurred_at` (when the vendor says the cost actually
     * happened/was paid), same cash-basis philosophy as every other
     * section here. `recurringAmount` is a sub-total of rows the vendor
     * tagged `is_recurring` — informational only, it does NOT mean this
     * amount recurs automatically; nothing in this feature generates
     * future rows on its own (see the migration doc comment).
     *
     * `amount` also feeds `summary.operatingExpenses`/`operatingProfit` —
     * see the class doc comment for the full Net Profit waterfall.
     */
    private function manualExpenses(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $expenses = $vendor->financialExpenses()
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get();

        $items = $expenses->map(fn (FinancialExpense $expense) => [
            'id' => $expense->id,
            'name' => $expense->name,
            'category' => $expense->category,
            'payee' => $expense->payee,
            'amount' => round((float) $expense->amount, 2),
            'paymentMethod' => $expense->payment_method,
            'occurredAt' => $expense->occurred_at?->toIso8601String(),
            'description' => $expense->description,
            'isRecurring' => $expense->is_recurring,
            'recurrenceFrequency' => $expense->recurrence_frequency,
            'attachmentUrl' => $this->media->url($expense->attachment_path),
            'attachmentOriginalName' => $expense->attachment_original_name,
        ])->values()->all();

        $recurring = $expenses->where('is_recurring', true);

        return [
            'count' => $expenses->count(),
            'amount' => round((float) $expenses->sum('amount'), 2),
            'recurringCount' => $recurring->count(),
            'recurringAmount' => round((float) $recurring->sum('amount'), 2),
            'items' => $items,
        ];
    }

    /**
     * Real inventory shrinkage — `inventory_stock_movements` rows the
     * vendor logged with `type='waste'` via the existing Inventory page's
     * "Adjust Stock" action (spoilage, breakage, expiry — `note` is
     * whatever free-text reason they gave). Cost = quantity × the item's
     * current `cost_per_unit`, the same valuation `costOfGoodsSold()` uses.
     *
     * Deliberately excluded from `costs.totalCosts`: the cash for this
     * stock was already spent when it was purchased (`costs.inventoryPurchases`)
     * or is already baked into `costOfGoodsSold` if it was consumed by an
     * order — this section answers "how much of what I already paid for
     * went to waste", a loss/liability figure, not an additional cash
     * outflow. Folding it into total costs would double-count the same
     * money leaving the business.
     *
     * It IS subtracted in `summary.netProfit` (see the class doc comment):
     * that is a profitability question — "how much of what we sold-or-lost
     * counted against the bottom line" — distinct from `costs.totalCosts`
     * answering "how much cash went out the door". No double-count there
     * either, since neither `costOfGoodsSold` nor `costs.totalCosts`
     * subtracts waste today.
     */
    private function inventoryWaste(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = InventoryStockMovement::query()
            ->where('vendor_id', $vendor->id)
            ->where('type', 'waste')
            ->whereBetween('created_at', [$from, $to])
            ->with('inventoryItem:id,name,unit,cost_per_unit')
            ->orderBy('created_at')
            ->get()
            ->map(fn (InventoryStockMovement $movement) => [
                'itemName' => $movement->inventoryItem?->name ?? 'Unknown item',
                'quantity' => abs((float) $movement->quantity_change),
                'unit' => $movement->inventoryItem?->unit ?? '',
                'amount' => round(abs((float) $movement->quantity_change) * (float) ($movement->inventoryItem?->cost_per_unit ?? 0), 2),
                'reason' => $movement->note,
                'date' => $movement->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'count' => count($items),
            'amount' => round((float) array_sum(array_column($items, 'amount')), 2),
            'items' => $items,
        ];
    }

    /**
     * Equal-split tip pool estimate — Square's simplest tip-pooling mode
     * ("split equally among all tip-eligible team members"), matching
     * exactly what was asked: divide the tip pool by headcount, not by
     * hours worked or role weighting. Eligible = `team_members` rows with
     * `status='active'` — the vendor owner is never a TeamMember row (the
     * `Vendor` account itself), so it's excluded automatically, and
     * `invited`/`suspended` members are excluded as not currently working.
     * This is a bookkeeping estimate the vendor can act on, not a legally
     * binding payout — every restaurant's actual tip-out policy differs
     * (by role, by hours, by shift), so the UI must present it as such.
     */
    private function tipDistribution(Vendor $vendor, Collection $orders): array
    {
        $totalTips = round((float) $orders->sum('tip_amount'), 2);

        $members = $vendor->teamMembers()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['name', 'role']);

        $count = $members->count();

        return [
            'totalTips' => $totalTips,
            'eligibleTeamMemberCount' => $count,
            'perPersonAmount' => $count > 0 ? round($totalTips / $count, 2) : null,
            'members' => $members->map(fn (TeamMember $member) => [
                'name' => $member->name,
                'role' => $member->role,
            ])->all(),
        ];
    }

    /**
     * A forward-looking reminder built purely from the vendor's own past
     * `FinancialExpense` entries tagged `is_recurring` — deliberately
     * independent of the report's selected period (a vendor viewing "Today"
     * should still see "rent is due again in 12 days", not lose the
     * reminder just because today isn't when it last happened).
     *
     * There is no "series" concept in the schema (see the migration doc
     * comment — recurring is metadata only, nothing links one occurrence to
     * the next), so occurrences are grouped heuristically by
     * name+category+frequency: every time the vendor logs "August rent"
     * then "September rent" as separate rows, this groups them because the
     * name/category/frequency match, and projects the next date from
     * whichever occurrence is most recent. If a vendor names each
     * occurrence completely differently, this simply won't group them —
     * no harm done, just a missed grouping, never a fabricated one.
     */
    private function upcomingRecurringExpenses(Vendor $vendor): array
    {
        $recurring = $vendor->financialExpenses()
            ->where('is_recurring', true)
            ->whereNotNull('recurrence_frequency')
            ->orderByDesc('occurred_at')
            ->get(['name', 'category', 'recurrence_frequency', 'amount', 'occurred_at']);

        $latestBySeries = [];
        foreach ($recurring as $expense) {
            $key = mb_strtolower(trim($expense->name)).'|'.$expense->category.'|'.$expense->recurrence_frequency;
            // Already sorted newest-first, so the first hit per key is the latest.
            $latestBySeries[$key] ??= $expense;
        }

        // Vendor's own timezone, same as every other date computation in
        // this class — not the app server's, so "overdue" matches what the
        // vendor sees as "today" in their own local time.
        $now = CarbonImmutable::now($vendor->resolveTimezone());

        return collect($latestBySeries)
            ->map(function (FinancialExpense $expense) use ($now) {
                $last = CarbonImmutable::parse($expense->occurred_at);
                $next = match ($expense->recurrence_frequency) {
                    'daily' => $last->addDay(),
                    'weekly' => $last->addWeek(),
                    'monthly' => $last->addMonthNoOverflow(),
                    'yearly' => $last->addYear(),
                    default => $last,
                };

                return [
                    'name' => $expense->name,
                    'category' => $expense->category,
                    'recurrenceFrequency' => $expense->recurrence_frequency,
                    'amount' => round((float) $expense->amount, 2),
                    'lastOccurredAt' => $last->toIso8601String(),
                    'nextExpectedAt' => $next->toIso8601String(),
                    'isOverdue' => $next->lessThan($now),
                ];
            })
            ->sortBy('nextExpectedAt')
            ->values()
            ->all();
    }

    /**
     * Orders are bucketed by `payment_confirmed_at`, refunds by their own
     * `resolved_at` — so a refund against an older order still lands in the
     * bucket it was actually paid out in. A bucket can therefore show
     * refunds with zero orders. `netAfterRefunds` = gross − refunds for that
     * bucket only (cash reconciliation), distinct from the "Net Revenue" KPI
     * in `summary` (gross − VAT) — the frontend must not label both "Net".
     *
     * @param  array<int, float>  $vatByOrderId
     */
    private function dailyBreakdown(Collection $orders, Collection $refunds, array $vatByOrderId, FinancialReportPeriod $period): array
    {
        $unit = $period->bucketUnit();
        $tz = $period->timezone;

        $bucketKey = function (CarbonImmutable $moment) use ($unit, $tz): string {
            $local = $moment->setTimezone($tz);

            return match ($unit) {
                'week' => $local->startOfWeek()->toDateString(),
                'month' => $local->startOfMonth()->toDateString(),
                default => $local->toDateString(),
            };
        };

        $bucketEnd = function (string $key) use ($unit): string {
            return match ($unit) {
                'week' => CarbonImmutable::parse($key)->endOfWeek()->toDateString(),
                'month' => CarbonImmutable::parse($key)->endOfMonth()->toDateString(),
                default => $key,
            };
        };

        $buckets = [];

        $ensure = function (string $key) use (&$buckets, $bucketEnd) {
            $buckets[$key] ??= [
                'date' => $key,
                'dateTo' => $bucketEnd($key),
                'orders' => 0,
                'grossAmount' => 0.0,
                'vatAmount' => 0.0,
                'refundAmount' => 0.0,
            ];
        };

        foreach ($orders as $order) {
            if (! $order->payment_confirmed_at) {
                continue;
            }

            $key = $bucketKey(CarbonImmutable::parse($order->payment_confirmed_at));
            $ensure($key);
            $buckets[$key]['orders']++;
            $buckets[$key]['grossAmount'] += (float) $order->amount;
            $buckets[$key]['vatAmount'] += $vatByOrderId[$order->id] ?? 0.0;
        }

        foreach ($refunds as $refund) {
            if (! $refund->resolved_at) {
                continue;
            }

            $key = $bucketKey(CarbonImmutable::parse($refund->resolved_at));
            $ensure($key);
            $buckets[$key]['refundAmount'] += (float) $refund->amount;
        }

        ksort($buckets);

        return array_values(array_map(function (array $bucket) {
            $bucket['grossAmount'] = round($bucket['grossAmount'], 2);
            $bucket['vatAmount'] = round($bucket['vatAmount'], 2);
            $bucket['refundAmount'] = round($bucket['refundAmount'], 2);
            $bucket['netAfterRefunds'] = round($bucket['grossAmount'] - $bucket['refundAmount'], 2);

            return $bucket;
        }, $buckets));
    }

    /**
     * Gross revenue and order count bucketed by hour-of-day (0-23, the
     * vendor's own local timezone) across the whole selected period —
     * reuses the exact same `$orders` this report already fetched for
     * `summary()`/`dailyBreakdown()`, no new query. Every hour 0-23 is
     * always present, even with zero orders, so a chart's bar position
     * always matches its true hour and a quiet hour renders as a visible
     * zero bar, not a gap — same reasoning as `fillBucketSeries()` on the
     * frontend for daily buckets. Answers "when do we actually get busy",
     * distinct from `dailyBreakdown()` (which answers "which day/week").
     */
    private function salesByHour(Collection $orders, FinancialReportPeriod $period): array
    {
        $tz = $period->timezone;

        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $hours[$h] = ['hour' => $h, 'orders' => 0, 'grossAmount' => 0.0];
        }

        foreach ($orders as $order) {
            if (! $order->payment_confirmed_at) {
                continue;
            }

            $hour = (int) CarbonImmutable::parse($order->payment_confirmed_at)->setTimezone($tz)->format('G');
            $hours[$hour]['orders']++;
            $hours[$hour]['grossAmount'] += (float) $order->amount;
        }

        return array_values(array_map(fn (array $row) => [
            'hour' => $row['hour'],
            'orders' => $row['orders'],
            'grossAmount' => round($row['grossAmount'], 2),
        ], $hours));
    }

    /**
     * A vendor's outstanding loyalty-point balance, valued at their own
     * configured point value — a real accounting concept (deferred revenue /
     * "breakage": points a customer could still redeem for value), distinct
     * from every other figure in this report because it never appears as a
     * cash movement anywhere — VendorAnalyticsService::loyalty() surfaces
     * the exact same number for operational analysis, but a bookkeeper
     * working from this report (not Analytics) had no way to see it here.
     * Deliberately period-independent — a balance-sheet snapshot as of now,
     * not scoped to the selected report period — same reasoning as
     * upcomingRecurringExpenses(). `null` when loyalty isn't enabled for
     * this vendor, so the frontend omits the section entirely rather than
     * show a false "€0.00".
     */
    private function loyaltyLiability(Vendor $vendor): ?array
    {
        if (! (bool) ($vendor->vendorSetting?->loyalty_enabled ?? false)) {
            return null;
        }

        $wallets = CustomerLoyaltyPoint::where('vendor_id', $vendor->id)->get();
        $pointValue = (float) ($vendor->vendorSetting->point_value ?? 0.01);
        $pointsOutstanding = (int) $wallets->sum('points_balance');

        return [
            'pointsOutstanding' => $pointsOutstanding,
            'pointValue' => $pointValue,
            'estimatedLiability' => round($pointsOutstanding * $pointValue, 2),
            // `contains()`, not `every()` — see the identical laborCost()
            // comment above: a vendor mixing real and demo wallets must
            // still flag the whole figure as demo-tainted.
            'isDemoData' => $wallets->contains(fn (CustomerLoyaltyPoint $w) => $w->source === 'demo'),
        ];
    }

    private function metric(float|int|null $value, float|int|null $previous, string $unit = 'percent'): array
    {
        $delta = null;

        // An absolute delta (e.g. totalOrders 0 -> 12) is well-defined even
        // when $previous is zero — only a *percent* delta needs the
        // zero-guard, to avoid dividing by zero / an undefined "% change
        // from nothing." Previously the zero-guard blocked both, so any
        // metric going from 0 silently showed no delta instead of the
        // real number.
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
}
