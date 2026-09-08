<?php

namespace Tests\Feature\FinancialReports;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerLoyaltyPoint;
use App\Models\FinancialExpense;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryStockMovement;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Refund;
use App\Models\RestaurantTable;
use App\Models\StaffShift;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * Fixture pattern (vendor()/context()/order()/headers()) copied from
 * VendorAnalyticsApiTest — same repo convention, no model factories for
 * these relations, tests build fixtures directly.
 */
class VendorFinancialReportsApiTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    public function test_summary_reflects_only_paid_orders_confirmed_in_period(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $countedOrder = $this->order($vendor, $customer, $session, [
            'amount' => 100, 'payment_received' => true,
            'payment_confirmed_at' => now(),
        ]);

        // VAT is derived from cart_items, not orders.vat_amount (see
        // FinancialReportService class doc) — 100.00 net @ 10% AT food = 10.00 VAT.
        $menuItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Schnitzel',
            'price' => 100,
            'vat_rate' => 0,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $countedOrder->id,
            'quantity' => 1,
        ]);

        // Confirmed by the kitchen but never paid — must not count.
        $this->order($vendor, $customer, $session, [
            'amount' => 500, 'payment_received' => false, 'payment_confirmed_at' => null,
        ]);

        // Paid, but outside the "today" window being requested below.
        $this->order($vendor, $customer, $session, [
            'amount' => 500, 'payment_received' => true,
            'payment_confirmed_at' => now()->subDays(5),
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertTrue($body['hasData']);
        $this->assertEquals(1, $body['summary']['totalOrders']['value']);
        $this->assertEquals(100.0, $body['summary']['grossRevenue']['value']);
        $this->assertEquals(90.0, $body['summary']['netRevenue']['value']);
        $this->assertEquals(10.0, $body['summary']['totalTax']['value']);
    }

    public function test_tax_breakdown_uses_the_seeded_country_vat_rate(): void
    {
        $vendor = $this->vendor(); // country AT: food 10%
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $menuItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Schnitzel',
            'price' => 20,
            'vat_rate' => 0, // deliberately wrong, to prove TaxCategory('AT','food') wins
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 44, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 2,
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertCount(1, $body['taxBreakdown']['groups']);
        $group = $body['taxBreakdown']['groups'][0];
        $this->assertEquals('food', $group['taxCategory']);
        $this->assertEquals(10.0, $group['vatRate']);
        // menu_items.price is NET (TaxCalculationService::itemBaseGross adds
        // VAT on top) — 2 x 20.00 net @ 10% => gross 44.00, net 40.00, vat 4.00.
        $this->assertEquals(44.0, $group['grossAmount']);
        $this->assertEquals(40.0, $group['netAmount']);
        $this->assertEquals(4.0, $group['vatAmount']);
    }

    /**
     * Regression test for a real discrepancy found in production data: the
     * tax breakdown's totals must reconcile against summary.grossRevenue
     * (orders.amount), which CartController/PaymentController compute by
     * dividing a shared cart item's gross by its sharer count. Without
     * passing applySharing to computeTaxGroups() here too, a shared line
     * was counted at its FULL gross in the tax breakdown while the order
     * was only ever actually charged its 1/N share — inflating Total Tax
     * and the tax breakdown's gross/net/vat totals for any vendor using
     * order-sharing.
     */
    public function test_shared_cart_items_are_divided_by_sharer_count_for_tax_purposes(): void
    {
        $vendor = $this->vendor(); // country AT: food 10%
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $menuItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id, 'name' => 'Shared Pizza',
            'price' => 100, 'vat_rate' => 0, 'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 55, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);

        // 100.00 net @ 10% => 110.00 gross, shared with one other order —
        // this order was only ever actually charged its half.
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'shared_order_ids' => [999999],
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(5.0, $body['summary']['totalTax']['value']);
        $this->assertEquals(55.0, $body['taxBreakdown']['totals']['grossAmount']);
        $this->assertEquals(50.0, $body['taxBreakdown']['totals']['netAmount']);
        $this->assertEquals(5.0, $body['taxBreakdown']['totals']['vatAmount']);
    }

    /**
     * Regression test for a real, live-verified discrepancy: when BOTH
     * sides of a split order are genuinely inside the report period, the
     * sharer's half of the shared item previously never appeared anywhere
     * in this report — not in the tax breakdown, not in revenue by
     * category — even though it's genuinely part of that sharer's own
     * charged `orders.amount`. cartItemsFor() now returns both sides.
     */
    public function test_shared_cart_item_reconciles_across_tax_breakdown_and_revenue_by_category(): void
    {
        $vendor = $this->vendor(); // AT: food 10%
        [$customerA, $sessionA] = $this->context($vendor);
        [$customerB, $sessionB] = $this->secondContext($vendor);
        $category = $this->category($vendor);

        $menuItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id, 'name' => 'Shared Pizza',
            'price' => 100, 'vat_rate' => 0, 'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        // Two real orders genuinely splitting one dish 50/50 — both paid
        // within the same report period, each charged its real 1/2 share
        // (110 gross / 2 = 55), exactly like ShareOrderService::
        // recalcOrder() actually computes it at checkout.
        $orderA = $this->order($vendor, $customerA, $sessionA, [
            'amount' => 55, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        $orderB = $this->order($vendor, $customerB, $sessionB, [
            'amount' => 55, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);

        CartItem::create([
            'table_scan_session_id' => $sessionA->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $orderA->id,
            'quantity' => 1,
            'shared_order_ids' => [$orderB->id],
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(110.0, $body['summary']['grossRevenue']['value']);
        // Previously only order A's half (55) ever reached the tax
        // breakdown — this must reconcile exactly against grossRevenue.
        $this->assertEquals(110.0, $body['taxBreakdown']['totals']['grossAmount']);
        $this->assertEquals(100.0, $body['taxBreakdown']['totals']['netAmount']);
        $this->assertEquals(10.0, $body['taxBreakdown']['totals']['vatAmount']);

        // Same reconciliation for revenue by category — previously this
        // counted the item's full, undivided gross once (55 short).
        $this->assertCount(1, $body['revenueByCategory']);
        $this->assertEquals(110.0, $body['revenueByCategory'][0]['grossAmount']);
        // Quantity is a physical count, not a monetary split — one dish
        // was served, not two, regardless of how the bill was split.
        $this->assertEquals(1, $body['revenueByCategory'][0]['quantity']);
    }

    /**
     * Regression test: a shared dish's inventory deduction is recorded
     * once, against whichever of the two split orders happened to
     * complete first — which can be the order OUTSIDE the report period
     * being viewed. Matching by `cart_item_id` (not `order_id`) must find
     * it either way.
     */
    public function test_cost_of_goods_sold_finds_a_shared_dishes_deduction_regardless_of_which_order_it_is_recorded_against(): void
    {
        $vendor = $this->vendor();
        [$customerA, $sessionA] = $this->context($vendor);
        [$customerB, $sessionB] = $this->secondContext($vendor);
        $category = $this->category($vendor);

        $menuItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id, 'name' => 'Shared Pizza',
            'price' => 100, 'vat_rate' => 0, 'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        // Order A is paid today; order B (sharing the same dish) was paid
        // yesterday — outside today's report period.
        $orderA = $this->order($vendor, $customerA, $sessionA, [
            'amount' => 55, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        $orderB = $this->order($vendor, $customerB, $sessionB, [
            'amount' => 55, 'payment_received' => true, 'payment_confirmed_at' => now()->subDay(),
        ]);

        $cartItem = CartItem::create([
            'table_scan_session_id' => $sessionB->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $orderB->id, // owned by the order OUTSIDE today's period
            'quantity' => 1,
            'shared_order_ids' => [$orderA->id],
        ]);

        $beef = InventoryItem::create([
            'vendor_id' => $vendor->id, 'name' => 'Beef', 'unit' => 'kg', 'cost_per_unit' => 8.0,
        ]);

        // The deduction was triggered by order B's completion (outside
        // today's period) — matching by order_id scoped to today's
        // orders alone would never have found this.
        InventoryStockMovement::create([
            'vendor_id' => $vendor->id, 'inventory_item_id' => $beef->id,
            'order_id' => $orderB->id, 'cart_item_id' => $cartItem->id,
            'type' => 'order', 'source' => 'Order test',
            'quantity_change' => -2.5, 'quantity_before' => 10, 'quantity_after' => 7.5,
        ]);

        $body = $this->report($vendor, 'today');

        // 2.5kg x 8.00/kg = 20.00 — found via cart_item_id even though the
        // movement's own order_id sits outside today's period.
        $this->assertEquals(20.0, $body['summary']['costOfGoodsSold']['value']);
    }

    public function test_refunds_are_counted_in_the_period_they_were_resolved_not_the_order_date(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 80, 'payment_received' => true,
            'payment_confirmed_at' => now()->subDays(10),
        ]);

        Refund::create([
            'refund_public_id' => 'RF-'.Str::upper(Str::random(10)),
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => 'refund',
            'status' => 'approved',
            'amount' => 25,
            'currency' => 'EUR',
            'reason' => 'wrong_item',
            'resolved_at' => now(), // resolved today, order was 10 days ago
        ]);

        $todayBody = $this->report($vendor, 'today');
        $this->assertEquals(25.0, $todayBody['refunds']['totalAmount']);
        $this->assertEquals(1, $todayBody['refunds']['count']);
        // The order itself falls outside "today", so gross revenue here is 0
        // even though the refund shows up — that's the intended reconciliation split.
        $this->assertEquals(0.0, $todayBody['summary']['grossRevenue']['value']);

        $yearBody = $this->report($vendor, 'year');
        $this->assertEquals(80.0, $yearBody['summary']['grossRevenue']['value']);
        $this->assertEquals(25.0, $yearBody['refunds']['totalAmount']);
    }

    public function test_open_refund_requests_are_reported_separately_and_excluded_from_totals(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $order = $this->order($vendor, $customer, $session, [
            'amount' => 50, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);

        Refund::create([
            'refund_public_id' => 'RF-'.Str::upper(Str::random(10)),
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => 'refund',
            'status' => 'open',
            'amount' => 15,
            'currency' => 'EUR',
            'reason' => 'other',
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(0.0, $body['refunds']['totalAmount']);
        $this->assertEquals(0, $body['refunds']['count']);
        $this->assertEquals(1, $body['refunds']['pendingCount']);
    }

    public function test_inventory_purchase_cost_excludes_failed_dispatches(): void
    {
        $vendor = $this->vendor();

        InventoryPurchaseOrder::create([
            'vendor_id' => $vendor->id,
            'supplier_id' => 'sup-1',
            'supplier_name' => 'Test Supplier',
            'ordering_method' => 'API',
            'quantity' => 10,
            'unit' => 'kg',
            'unit_cost' => 4.5,
            'currency' => 'EUR',
            'status' => 'sent',
        ]);

        InventoryPurchaseOrder::create([
            'vendor_id' => $vendor->id,
            'supplier_id' => 'sup-1',
            'supplier_name' => 'Test Supplier',
            'ordering_method' => 'API',
            'quantity' => 100,
            'unit' => 'kg',
            'unit_cost' => 9,
            'currency' => 'EUR',
            'status' => 'failed',
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(1, $body['costs']['inventoryPurchases']['count']);
        $this->assertEquals(45.0, $body['costs']['inventoryPurchases']['amount']);
    }

    public function test_cost_of_goods_sold_and_gross_profit_come_from_real_inventory_consumption(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 100, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        $category = $this->category($vendor);
        $menuItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id, 'name' => 'Steak', 'price' => 100,
            'vat_rate' => 0, 'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);
        $cartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
        ]);

        $beef = InventoryItem::create([
            'vendor_id' => $vendor->id,
            'name' => 'Beef',
            'unit' => 'kg',
            'cost_per_unit' => 8.0,
        ]);

        // The automatic per-order deduction InventoryConsumptionService
        // writes: negative quantity_change, linked to both the order AND
        // the specific cart item it was deducted for (real production
        // rows always carry cart_item_id — see
        // InventoryConsumptionService::deductCartItem()).
        InventoryStockMovement::create([
            'vendor_id' => $vendor->id,
            'inventory_item_id' => $beef->id,
            'order_id' => $order->id,
            'cart_item_id' => $cartItem->id,
            'type' => 'order',
            'source' => 'Order test',
            'quantity_change' => -2.5,
            'quantity_before' => 10,
            'quantity_after' => 7.5,
        ]);

        // A manual adjustment (waste) must NOT be counted as cost of goods sold.
        InventoryStockMovement::create([
            'vendor_id' => $vendor->id,
            'inventory_item_id' => $beef->id,
            'type' => 'waste',
            'source' => 'Manual',
            'quantity_change' => -1,
            'quantity_before' => 7.5,
            'quantity_after' => 6.5,
        ]);

        $body = $this->report($vendor, 'today');

        // 2.5kg x 8.00/kg = 20.00 COGS
        $this->assertEquals(20.0, $body['summary']['costOfGoodsSold']['value']);
        $this->assertEquals(100.0, $body['summary']['grossRevenue']['value']);
        // The cart item's own price (100 net, AT food 10% VAT) is what
        // drives VAT here, independent of the order's own `amount` fixture
        // above — netRevenue = 100 - vat(10) = 90; grossProfit = 90 - COGS(20) = 70.
        $this->assertEquals(70.0, $body['summary']['grossProfit']['value']);
        $this->assertEquals(77.8, $body['summary']['grossMarginPercent']['value']);
        // Every cart item here came from a menu item with recipe tracking
        // configured (none was set up), so coverage is 0% — not null,
        // since there IS real revenue to compute a share of.
        $this->assertEquals(0.0, $body['costOfGoodsSoldCoveragePercent']);
    }

    public function test_revenue_by_category_groups_by_the_vendors_own_menu_category_name(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $mains = $this->category($vendor);
        $drinks = MenuCategory::create([
            'vendor_id' => $vendor->id, 'name' => 'Drinks', 'slug' => 'drinks', 'sort_order' => 1, 'is_active' => true,
        ]);

        $steak = $vendor->menuItems()->create([
            'menu_category_id' => $mains->id, 'name' => 'Steak', 'price' => 30,
            'vat_rate' => 0, 'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);
        $cola = $vendor->menuItems()->create([
            'menu_category_id' => $drinks->id, 'name' => 'Cola', 'price' => 4,
            'vat_rate' => 0, 'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 34, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        CartItem::create(['table_scan_session_id' => $session->id, 'menu_item_id' => $steak->id, 'order_id' => $order->id, 'quantity' => 1]);
        CartItem::create(['table_scan_session_id' => $session->id, 'menu_item_id' => $cola->id, 'order_id' => $order->id, 'quantity' => 1]);

        $body = $this->report($vendor, 'today');

        $this->assertCount(2, $body['revenueByCategory']);
        $names = array_column($body['revenueByCategory'], 'category');
        $this->assertContains('Mains', $names);
        $this->assertContains('Drinks', $names);
    }

    public function test_payment_methods_are_broken_down_between_cash_and_card(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, [
            'amount' => 60, 'payment_method' => 'card', 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        $this->order($vendor, $customer, $session, [
            'amount' => 40, 'payment_method' => 'cash', 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);

        $body = $this->report($vendor, 'today');

        $byMethod = collect($body['paymentMethods'])->keyBy('method');
        $this->assertEquals(60.0, $byMethod['card']['grossAmount']);
        $this->assertEquals(40.0, $byMethod['cash']['grossAmount']);
    }

    public function test_tips_are_excluded_from_revenue_and_broken_down_by_payment_method(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 10, 'payment_method' => 'card',
            'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        $this->order($vendor, $customer, $session, [
            'amount' => 50, 'tip_amount' => 5, 'payment_method' => 'cash',
            'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);

        $body = $this->report($vendor, 'today');

        // Tips sit outside `amount` — gross revenue must not include them.
        $this->assertEquals(150.0, $body['summary']['grossRevenue']['value']);
        $this->assertEquals(15.0, $body['summary']['totalTips']['value']);
        // 15 tips / 150 gross = 10%
        $this->assertEquals(10.0, $body['summary']['tipRatePercent']['value']);

        $byMethod = collect($body['tipsByPaymentMethod'])->keyBy('method');
        $this->assertEquals(10.0, $byMethod['card']['amount']);
        $this->assertEquals(5.0, $byMethod['cash']['amount']);
    }

    public function test_sales_invoices_list_only_orders_with_a_real_invoice_number(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // Order::booted() now assigns invoice_number automatically the
        // moment payment_confirmed_at is set — no need to fake one here,
        // this exercises the real assignment.
        $this->order($vendor, $customer, $session, [
            'amount' => 42, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);

        // Never paid — an unpaid order still genuinely has no invoice
        // number (nothing ever triggers assignment) and must not appear
        // in the register.
        $this->order($vendor, $customer, $session, [
            'amount' => 500, 'payment_received' => false, 'payment_confirmed_at' => null,
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertCount(1, $body['salesInvoices']);
        $this->assertEquals('INV-0001001', $body['salesInvoices'][0]['invoiceNumber']);
        $this->assertEquals(42.0, $body['salesInvoices'][0]['amount']);
    }

    public function test_paid_order_is_assigned_an_invoice_number_automatically(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // Created unpaid — no invoice number yet.
        $order = $this->order($vendor, $customer, $session, [
            'amount' => 30, 'payment_received' => false, 'payment_confirmed_at' => null,
        ]);
        $this->assertNull($order->fresh()->invoice_number);

        // Confirming payment must assign one immediately, without anyone
        // ever viewing a receipt — this is the behavior change from the
        // old "assigned the first time a receipt is opened" design.
        $order->update(['payment_received' => true, 'payment_confirmed_at' => now()]);

        $this->assertEquals('INV-0001001', $order->fresh()->invoice_number);
    }

    public function test_subscription_invoices_are_itemized_real_tavlo_invoices(): void
    {
        $vendor = $this->vendor();

        $plan = SubscriptionPlan::create([
            'name' => 'Basic', 'monthly_price' => 49, 'yearly_price' => 490,
            'currency' => 'EUR', 'max_users' => 3, 'is_active' => true,
        ]);
        $subscription = Subscription::create([
            'vendor_id' => $vendor->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'start_date' => now()->subMonth(), 'next_billing_date' => now(),
        ]);
        Invoice::create([
            'subscription_id' => $subscription->id, 'invoice_number' => 'INV-1',
            'amount' => 49, 'currency' => 'EUR', 'status' => 'paid',
            'billing_period_start' => now()->subMonth(), 'billing_period_end' => now(),
            'due_date' => now(), 'paid_at' => now(),
        ]);
        // Pending invoice — must not appear (not yet real income to the vendor's supplier relationship).
        Invoice::create([
            'subscription_id' => $subscription->id, 'invoice_number' => 'INV-2',
            'amount' => 49, 'currency' => 'EUR', 'status' => 'pending',
            'billing_period_start' => now(), 'billing_period_end' => now()->addMonth(),
            'due_date' => now()->addDays(7),
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertCount(1, $body['costs']['subscriptionInvoices']);
        $this->assertEquals('INV-1', $body['costs']['subscriptionInvoices'][0]['invoiceNumber']);
        $this->assertEquals(49.0, $body['costs']['subscriptionInvoices'][0]['amount']);
        $this->assertFalse($body['costs']['subscriptionInvoices'][0]['hasDocument']);
        $this->assertEquals(49.0, $body['costs']['subscriptionFees']['amount']);
    }

    public function test_purchase_orders_are_labeled_as_orders_not_invoices(): void
    {
        $vendor = $this->vendor();

        InventoryPurchaseOrder::create([
            'vendor_id' => $vendor->id, 'supplier_id' => 'sup-1', 'supplier_name' => 'Fresh Farms',
            'ordering_method' => 'API', 'quantity' => 10, 'unit' => 'kg', 'unit_cost' => 3.5,
            'currency' => 'EUR', 'status' => 'sent',
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertArrayNotHasKey('invoiceNumber', $body['costs']['purchaseOrders'][0]);
        $this->assertEquals('Fresh Farms', $body['costs']['purchaseOrders'][0]['supplierName']);
        $this->assertEquals(35.0, $body['costs']['purchaseOrders'][0]['amount']);
        $this->assertEquals(35.0, $body['costs']['inventoryPurchases']['amount']);
    }

    public function test_voided_orders_are_reported_separately_and_never_counted_as_revenue(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, [
            'amount' => 500, 'payment_received' => false, 'cancelled_at' => now(),
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(1, $body['voidedOrders']['count']);
        $this->assertEquals(500.0, $body['voidedOrders']['amount']);
        $this->assertEquals(0, $body['summary']['totalOrders']['value']);
        $this->assertEquals(0.0, $body['summary']['grossRevenue']['value']);
    }

    public function test_voided_orders_excludes_a_paid_order_that_was_cancelled(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // A paid order should never end up here — if it does, cancel() blocked
        // it (see OrderManagementTest::test_cancel_rejects_an_order_that_has_already_been_paid)
        // or some other path let a paid order through, and this section's own
        // doc comment ("never paid — not included in revenue") would be a lie.
        $this->order($vendor, $customer, $session, [
            'amount' => 75, 'payment_received' => true,
            'payment_confirmed_at' => now(), 'cancelled_at' => now(),
        ]);

        $this->order($vendor, $customer, $session, [
            'amount' => 40, 'payment_received' => false, 'cancelled_at' => now(),
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(1, $body['voidedOrders']['count']);
        $this->assertEquals(40.0, $body['voidedOrders']['amount']);
    }

    public function test_labor_cost_and_prime_cost_come_from_staff_shifts(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // Anchored to fixed points safely inside "today" (see vendorToday()'s
        // doc comment) rather than built from relative offsets off now().
        $this->order($vendor, $customer, $session, [
            'amount' => 200, 'payment_received' => true, 'payment_confirmed_at' => $this->vendorToday($vendor, 9),
        ]);

        $waiter = TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Waiter', 'email' => 'waiter@example.com',
            'password' => bcrypt('password'), 'role' => 'waiter', 'status' => 'active',
        ]);

        // A real (non-demo) shift: 5 hours at 14.00/hr = 70.00.
        StaffShift::create([
            'vendor_id' => $vendor->id,
            'team_member_id' => $waiter->id,
            'clock_in_at' => $this->vendorToday($vendor, 9),
            'clock_out_at' => $this->vendorToday($vendor, 14),
            'hourly_rate' => 14.00,
            'source' => 'manual',
        ]);

        // A still-open shift must contribute 0 until it clocks out.
        StaffShift::create([
            'vendor_id' => $vendor->id,
            'team_member_id' => $waiter->id,
            'clock_in_at' => $this->vendorToday($vendor, 15),
            'clock_out_at' => null,
            'hourly_rate' => 14.00,
            'source' => 'manual',
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(70.0, $body['summary']['laborCost']['value']);
        $this->assertEquals(70.0, $body['labor']['amount']);
        $this->assertEquals(5.0, $body['labor']['hours']);
        $this->assertFalse($body['labor']['isDemoData']);
        // No inventory tracking configured here, so COGS is 0 — prime cost is labor only.
        $this->assertEquals(70.0, $body['summary']['primeCost']['value']);
        // 70 / 200 gross (no VAT set up) = 35%
        $this->assertEquals(35.0, $body['summary']['primeCostPercent']['value']);
        // Food Cost % / Labor Cost % are the two halves of Prime Cost % above.
        $this->assertEquals(0.0, $body['summary']['costOfGoodsSoldPercent']['value']);
        $this->assertEquals(35.0, $body['summary']['laborCostPercent']['value']);
    }

    public function test_sales_by_hour_buckets_orders_by_the_vendors_local_hour_of_day(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $tz = $vendor->resolveTimezone();

        // Two orders confirmed "today" in the vendor's own local timezone,
        // at hours far enough apart that no plausible UTC offset could
        // collapse them into the same bucket. Eloquent's datetime cast
        // naively formats whatever timezone a Carbon instance carries with
        // no conversion (it does not normalize to config('app.timezone')
        // before storing) — so the Vienna-local instant must be converted
        // to app timezone here first, exactly like FinancialReportPeriod::
        // queryStart()/queryEnd() already do before every query in this
        // service, or the stored value silently means a different instant.
        $morningLocal = now($tz)->setTime(8, 15, 0)->setTimezone(config('app.timezone'));
        $eveningLocal = now($tz)->setTime(20, 45, 0)->setTimezone(config('app.timezone'));

        $this->order($vendor, $customer, $session, [
            'amount' => 30, 'payment_received' => true, 'payment_confirmed_at' => $morningLocal,
        ]);
        $this->order($vendor, $customer, $session, [
            'amount' => 50, 'payment_received' => true, 'payment_confirmed_at' => $eveningLocal,
        ]);

        $body = $this->report($vendor, 'today');

        $byHour = collect($body['salesByHour'])->keyBy('hour');
        $this->assertCount(24, $byHour);

        $this->assertEquals(1, $byHour[8]['orders']);
        $this->assertEquals(30.0, $byHour[8]['grossAmount']);
        $this->assertEquals(1, $byHour[20]['orders']);
        $this->assertEquals(50.0, $byHour[20]['grossAmount']);

        // An hour with no activity is a real, present zero row — not omitted.
        $this->assertEquals(0, $byHour[0]['orders']);
        $this->assertEquals(0.0, $byHour[0]['grossAmount']);

        $this->assertEquals(2, collect($body['salesByHour'])->sum('orders'));
        $this->assertEquals(80.0, collect($body['salesByHour'])->sum('grossAmount'));
    }

    public function test_labor_cost_is_flagged_as_demo_data_when_every_shift_is_seeded(): void
    {
        $vendor = $this->vendor();
        $waiter = TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Waiter', 'email' => 'waiter2@example.com',
            'password' => bcrypt('password'), 'role' => 'waiter', 'status' => 'active',
        ]);

        StaffShift::create([
            'vendor_id' => $vendor->id,
            'team_member_id' => $waiter->id,
            'clock_in_at' => $this->vendorToday($vendor, 9),
            'clock_out_at' => $this->vendorToday($vendor, 13),
            'hourly_rate' => 15.00,
            'source' => 'demo',
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertTrue($body['labor']['isDemoData']);
    }

    public function test_labor_cost_is_flagged_as_demo_data_when_only_some_shifts_are_seeded(): void
    {
        $vendor = $this->vendor();
        $waiter = TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Waiter', 'email' => 'waiter3@example.com',
            'password' => bcrypt('password'), 'role' => 'waiter', 'status' => 'active',
        ]);

        // One real shift and one demo shift in the same period — the whole
        // figure must still be flagged, not reported as fully real just
        // because it isn't *entirely* fabricated.
        StaffShift::create([
            'vendor_id' => $vendor->id, 'team_member_id' => $waiter->id,
            'clock_in_at' => $this->vendorToday($vendor, 9), 'clock_out_at' => $this->vendorToday($vendor, 11),
            'hourly_rate' => 15.00, 'source' => 'manual',
        ]);
        StaffShift::create([
            'vendor_id' => $vendor->id, 'team_member_id' => $waiter->id,
            'clock_in_at' => $this->vendorToday($vendor, 12), 'clock_out_at' => $this->vendorToday($vendor, 14),
            'hourly_rate' => 15.00, 'source' => 'demo',
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertTrue($body['labor']['isDemoData']);
    }

    public function test_discounts_report_revenue_forgone_using_the_same_math_as_analytics(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        // List price 20.00 net, discounted to 15.00 net, 10% AT food VAT:
        // list gross 22.00, discounted gross 16.50 -> revenue forgone 5.50.
        $item = $vendor->menuItems()->create([
            'menu_category_id' => $category->id, 'name' => 'Discounted plate',
            'price' => 20, 'vat_rate' => 0, 'has_discount' => true,
            'discount_percent' => 25, 'discounted_price' => 15,
            'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 16.50, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        CartItem::create(['table_scan_session_id' => $session->id, 'menu_item_id' => $item->id, 'order_id' => $order->id, 'quantity' => 1]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(1, $body['discounts']['discountedOrders']);
        $this->assertEquals(100.0, $body['discounts']['discountedSharePercent']);
        $this->assertEquals(5.5, $body['discounts']['revenueForgone']);
        $this->assertEquals(25.0, $body['discounts']['averageDiscountPercent']);

        // topDiscountedItems carries the menu's own displayed prices (20 /
        // 15), not the VAT-grossed revenueForgone figures above — those
        // answer a different question ("what did this cost the business
        // across every order") than "what does the menu say for one unit".
        $this->assertCount(1, $body['discounts']['topDiscountedItems']);
        $this->assertEquals([
            'name' => 'Discounted plate',
            'originalPrice' => 20.0,
            'discountedPrice' => 15.0,
            'discountPercent' => 25.0,
            'quantityOrdered' => 1,
        ], $body['discounts']['topDiscountedItems'][0]);
    }

    public function test_top_discounted_items_quantity_is_a_physical_count_not_divided_by_sharers(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        [$otherCustomer, $otherSession] = $this->secondContext($vendor);
        $category = $this->category($vendor);

        $item = $vendor->menuItems()->create([
            'menu_category_id' => $category->id, 'name' => 'Shared discounted plate',
            'price' => 20, 'vat_rate' => 0, 'has_discount' => true,
            'discount_percent' => 25, 'discounted_price' => 15,
            'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        $ownerOrder = $this->order($vendor, $customer, $session, [
            'amount' => 8.25, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        $sharerOrder = $this->order($vendor, $otherCustomer, $otherSession, [
            'amount' => 8.25, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        // One physical plate (quantity 1), split across two orders — revenue
        // is halved per sharer (tested elsewhere), but the plate was still
        // only ordered once, not twice.
        CartItem::create([
            'table_scan_session_id' => $session->id, 'menu_item_id' => $item->id, 'order_id' => $ownerOrder->id,
            'quantity' => 1, 'shared_order_ids' => [$sharerOrder->id],
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertCount(1, $body['discounts']['topDiscountedItems']);
        $this->assertEquals(1, $body['discounts']['topDiscountedItems'][0]['quantityOrdered']);
    }

    public function test_top_discounted_items_are_ranked_by_revenue_forgone_not_insertion_order(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        // Small forgone total (2.00 gross) despite being created first.
        $cheapItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id, 'name' => 'Small discount',
            'price' => 10, 'vat_rate' => 0, 'has_discount' => true,
            'discount_percent' => 20, 'discounted_price' => 8,
            'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);
        // Larger forgone total (10.00 gross) despite being created second.
        $expensiveItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id, 'name' => 'Big discount',
            'price' => 50, 'vat_rate' => 0, 'has_discount' => true,
            'discount_percent' => 20, 'discounted_price' => 40,
            'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 48.0, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);
        CartItem::create(['table_scan_session_id' => $session->id, 'menu_item_id' => $cheapItem->id, 'order_id' => $order->id, 'quantity' => 1]);
        CartItem::create(['table_scan_session_id' => $session->id, 'menu_item_id' => $expensiveItem->id, 'order_id' => $order->id, 'quantity' => 1]);

        $body = $this->report($vendor, 'today');

        $names = array_column($body['discounts']['topDiscountedItems'], 'name');
        $this->assertEquals(['Big discount', 'Small discount'], $names);
    }

    public function test_custom_period_requires_from_and_to(): void
    {
        $vendor = $this->vendor();

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period=custom",
            $this->headers($vendor),
        )->assertStatus(422);
    }

    public function test_custom_period_wholly_in_the_future_collapses_to_today_instead_of_inverting(): void
    {
        // GetFinancialReportRequest only checks date_format and to >= from —
        // neither catches a range that's entirely in the future, which used
        // to leave `end` clamped to today by clampFutureEnd() while `start`
        // stayed in the future, an inverted (start > end) range.
        $vendor = $this->vendor();

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period=custom&from=2099-01-01&to=2099-01-05",
            $this->headers($vendor),
        )->assertOk()->json();

        $today = now($vendor->resolveTimezone())->toDateString();
        $this->assertEquals($today, $body['range']['from']);
        $this->assertEquals($today, $body['range']['to']);
    }

    public function test_a_custom_range_longer_than_the_max_is_truncated_and_flagged(): void
    {
        $vendor = $this->vendor();

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period=custom&from=2020-01-01&to=2026-01-01",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertTrue($body['range']['truncated']);
        $this->assertEquals(731, $body['range']['maxCustomDays']);
        $this->assertEquals('2020-01-01', $body['range']['from']);
        // Truncated to 731 days from `from`, not silently left at the requested `to`.
        $this->assertNotEquals('2026-01-01', $body['range']['to']);
    }

    public function test_a_custom_range_within_the_max_is_not_flagged_as_truncated(): void
    {
        $vendor = $this->vendor();

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period=custom&from=2026-01-01&to=2026-03-01",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertFalse($body['range']['truncated']);
    }

    public function test_team_member_token_is_forbidden_by_default(): void
    {
        $vendor = $this->vendor();
        $staff = TeamMember::create([
            'vendor_id' => $vendor->id,
            'name' => 'Kitchen Staff',
            'email' => 'kitchen@example.com',
            'password' => bcrypt('password'),
            'role' => 'kitchen',
            'status' => 'active',
        ]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports",
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
        )->assertStatus(403);
    }

    public function test_manual_expenses_are_itemized_and_folded_into_total_costs(): void
    {
        $vendor = $this->vendor();

        FinancialExpense::create([
            'vendor_id' => $vendor->id, 'name' => 'August rent', 'category' => 'rent',
            'payee' => 'Landlord GmbH', 'amount' => 1500, 'payment_method' => 'bank_transfer',
            'occurred_at' => now(), 'is_recurring' => true, 'recurrence_frequency' => 'monthly',
        ]);
        // Outside the requested "today" window — must not appear.
        FinancialExpense::create([
            'vendor_id' => $vendor->id, 'name' => 'Old repair', 'category' => 'maintenance_repairs',
            'amount' => 200, 'occurred_at' => now()->subDays(10),
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertCount(1, $body['costs']['manualExpensesList']);
        $this->assertEquals('August rent', $body['costs']['manualExpensesList'][0]['name']);
        $this->assertEquals('rent', $body['costs']['manualExpensesList'][0]['category']);
        $this->assertTrue($body['costs']['manualExpensesList'][0]['isRecurring']);
        $this->assertEquals(1500.0, $body['costs']['manualExpenses']['amount']);
        $this->assertEquals(1500.0, $body['costs']['manualExpenses']['recurringAmount']);
        $this->assertEquals(1500.0, $body['costs']['totalCosts']);
    }

    public function test_manual_expense_can_be_created_updated_and_deleted_via_the_api(): void
    {
        // Attachments live on the private `local` disk, never `public` — see
        // FinancialExpenseController's class doc comment (2026-09-08 audit
        // finding: these used to be served from a fully public,
        // unauthenticated URL).
        Storage::fake('local');
        $vendor = $this->vendor();

        $upload = $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-expenses/upload-attachment",
            ['attachment' => UploadedFile::fake()->create('receipt.pdf', 500, 'application/pdf')],
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertNotEmpty($upload['attachmentPath']);
        Storage::disk('local')->assertExists($upload['attachmentPath']);
        // The returned URL must be a signed link to the new authenticated
        // showAttachment() route, never a bare/guessable public path.
        $this->assertStringContainsString('/financial-expenses/attachment/', $upload['attachmentUrl']);
        $this->assertStringContainsString('signature=', $upload['attachmentUrl']);

        $create = $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-expenses",
            [
                'name' => 'Fix ice machine', 'category' => 'maintenance_repairs',
                'payee' => 'ACME Repairs', 'amount' => 250.5, 'paymentMethod' => 'card',
                'occurredAt' => now()->toIso8601String(), 'description' => 'Compressor replacement',
                'isRecurring' => false, 'attachmentPath' => $upload['attachmentPath'],
                'attachmentOriginalName' => $upload['attachmentOriginalName'],
            ],
            $this->headers($vendor),
        )->assertStatus(201)->json();

        $expenseId = $create['data']['id'];
        $this->assertEquals(250.5, $create['data']['amount']);
        $this->assertNotNull($create['data']['attachmentUrl']);

        // Recurring without a frequency must be rejected.
        $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-expenses",
            [
                'name' => 'Bad entry', 'category' => 'other', 'amount' => 10,
                'occurredAt' => now()->toIso8601String(), 'isRecurring' => true,
            ],
            $this->headers($vendor),
        )->assertStatus(422);

        $update = $this->putJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-expenses/{$expenseId}",
            ['amount' => 300],
            $this->headers($vendor),
        )->assertOk()->json();
        $this->assertEquals(300.0, $update['data']['amount']);

        $this->deleteJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-expenses/{$expenseId}",
            [],
            $this->headers($vendor),
        )->assertOk();

        $this->assertDatabaseMissing('financial_expenses', ['id' => $expenseId]);
        Storage::disk('local')->assertMissing($upload['attachmentPath']);
    }

    public function test_attachment_path_outside_the_vendors_own_prefix_is_rejected(): void
    {
        Storage::fake('local');
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();

        // A real receipt that belongs to a DIFFERENT vendor entirely — never
        // uploaded by vendorA.
        Storage::disk('local')->put("financial-expenses/{$vendorB->id}/receipts/other-vendors-receipt.pdf", 'fake-pdf-bytes');

        // Attempting to create an expense that points at it must be rejected
        // outright — without this, vendorA could later delete() it via
        // update()/destroy() on their own expense, permanently destroying a
        // file that belongs to vendorB.
        $this->postJson(
            "/api/vendor/{$vendorA->vendor_public_id}/financial-expenses",
            [
                'name' => 'Suspicious entry', 'category' => 'other', 'amount' => 10,
                'occurredAt' => now()->toIso8601String(),
                'attachmentPath' => "financial-expenses/{$vendorB->id}/receipts/other-vendors-receipt.pdf",
            ],
            $this->headers($vendorA),
        )->assertStatus(422);

        Storage::disk('local')->assertExists("financial-expenses/{$vendorB->id}/receipts/other-vendors-receipt.pdf");

        // A real expense of vendorA's own, with no attachment, must not be
        // affected by attempting to redirect its attachment cross-vendor.
        $create = $this->postJson(
            "/api/vendor/{$vendorA->vendor_public_id}/financial-expenses",
            ['name' => 'Legit entry', 'category' => 'other', 'amount' => 10, 'occurredAt' => now()->toIso8601String()],
            $this->headers($vendorA),
        )->assertStatus(201)->json();

        $this->putJson(
            "/api/vendor/{$vendorA->vendor_public_id}/financial-expenses/{$create['data']['id']}",
            ['attachmentPath' => "financial-expenses/{$vendorB->id}/receipts/other-vendors-receipt.pdf"],
            $this->headers($vendorA),
        )->assertStatus(422);

        Storage::disk('local')->assertExists("financial-expenses/{$vendorB->id}/receipts/other-vendors-receipt.pdf");
    }

    /**
     * Even a valid-looking OWN-vendor path can't be used to fetch another
     * vendor's file: showAttachment() is reached only via a signed URL
     * (2026-09-08 audit finding — this replaces the previous fully public,
     * unauthenticated `/media/{path}` exposure of these receipts). A forged
     * URL for a path outside the vendor the signature was minted for must be
     * rejected even with a technically-valid signature for a DIFFERENT path.
     */
    public function test_attachment_is_only_reachable_via_a_valid_signed_url_for_that_vendor(): void
    {
        Storage::fake('local');
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();

        Storage::disk('local')->put("financial-expenses/{$vendorA->id}/receipts/mine.pdf", 'vendor-a-bytes');
        Storage::disk('local')->put("financial-expenses/{$vendorB->id}/receipts/theirs.pdf", 'vendor-b-bytes');

        $ownUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'vendor.financial-expenses.attachment',
            now()->addMinutes(30),
            ['vendorId' => $vendorA->id, 'path' => "financial-expenses/{$vendorA->id}/receipts/mine.pdf"],
        );
        $this->get($ownUrl)->assertOk();

        // Same signature-minting call, but for vendor B's own file addressed
        // under vendor A's id — the belongsToVendor() prefix check must
        // reject this even though the signature itself is valid for the
        // (vendorId, path) pair it was actually generated for.
        $crossVendorUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'vendor.financial-expenses.attachment',
            now()->addMinutes(30),
            ['vendorId' => $vendorA->id, 'path' => "financial-expenses/{$vendorB->id}/receipts/theirs.pdf"],
        );
        $this->get($crossVendorUrl)->assertNotFound();

        // A bare, unsigned request for the same path must also be rejected.
        $this->get('/api/vendor/'.$vendorA->id.'/financial-expenses/attachment/financial-expenses/'.$vendorA->id.'/receipts/mine.pdf')
            ->assertForbidden();
    }

    /**
     * A "../" segment can make a plain str_starts_with() prefix check pass
     * as a string while resolving to a different vendor's directory once the
     * storage disk actually walks the path — rejected explicitly rather than
     * relying only on the prefix check (2026-09-07 audit finding).
     */
    public function test_attachment_path_containing_a_traversal_segment_is_rejected(): void
    {
        $vendorA = $this->vendor();

        $this->postJson(
            "/api/vendor/{$vendorA->vendor_public_id}/financial-expenses",
            [
                'name' => 'Suspicious entry', 'category' => 'other', 'amount' => 10,
                'occurredAt' => now()->toIso8601String(),
                'attachmentPath' => "financial-expenses/{$vendorA->id}/receipts/../../999/receipts/x.pdf",
            ],
            $this->headers($vendorA),
        )->assertStatus(422);
    }

    public function test_financial_expense_endpoints_are_forbidden_for_team_members(): void
    {
        $vendor = $this->vendor();
        $staff = TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Kitchen Staff', 'email' => 'kitchen-fe@example.com',
            'password' => bcrypt('password'), 'role' => 'kitchen', 'status' => 'active',
        ]);
        $token = $staff->createToken('test')->plainTextToken;
        $staffHeaders = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-expenses",
            ['name' => 'x', 'category' => 'other', 'amount' => 10, 'occurredAt' => now()->toIso8601String()],
            $staffHeaders,
        )->assertStatus(403);
    }

    public function test_inventory_waste_is_reported_as_a_loss_and_excluded_from_total_costs(): void
    {
        $vendor = $this->vendor();

        $lettuce = InventoryItem::create([
            'vendor_id' => $vendor->id, 'name' => 'Lettuce', 'unit' => 'kg', 'cost_per_unit' => 2.0,
        ]);

        InventoryStockMovement::create([
            'vendor_id' => $vendor->id, 'inventory_item_id' => $lettuce->id,
            'type' => 'waste', 'source' => 'Waste', 'note' => 'Spoiled overnight',
            'quantity_change' => -3, 'quantity_before' => 10, 'quantity_after' => 7,
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(1, $body['inventoryWaste']['count']);
        $this->assertEquals('Lettuce', $body['inventoryWaste']['items'][0]['itemName']);
        $this->assertEquals(3.0, $body['inventoryWaste']['items'][0]['quantity']);
        $this->assertEquals('Spoiled overnight', $body['inventoryWaste']['items'][0]['reason']);
        // 3kg x 2.00/kg = 6.00
        $this->assertEquals(6.0, $body['inventoryWaste']['amount']);
        // Must NOT be folded into costs — the cash was already accounted for
        // as a purchase/COGS, this is a loss figure, not an added expense.
        $this->assertEquals(0.0, $body['costs']['totalCosts']);
    }

    public function test_tips_are_split_equally_among_active_team_members_excluding_owner(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 30, 'payment_received' => true, 'payment_confirmed_at' => now(),
        ]);

        TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Anna', 'email' => 'anna@example.com',
            'password' => bcrypt('password'), 'role' => 'waiter', 'status' => 'active',
        ]);
        TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Bilal', 'email' => 'bilal@example.com',
            'password' => bcrypt('password'), 'role' => 'kitchen', 'status' => 'active',
        ]);
        // Not currently active — must not be counted as eligible for the split.
        TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Carla', 'email' => 'carla@example.com',
            'password' => bcrypt('password'), 'role' => 'waiter', 'status' => 'suspended',
        ]);
        TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Deniz', 'email' => 'deniz@example.com',
            'password' => bcrypt('password'), 'role' => 'waiter', 'status' => 'invited',
        ]);

        $body = $this->report($vendor, 'today');

        $this->assertEquals(30.0, $body['tipDistribution']['totalTips']);
        $this->assertEquals(2, $body['tipDistribution']['eligibleTeamMemberCount']);
        $this->assertEquals(15.0, $body['tipDistribution']['perPersonAmount']);
        $names = array_column($body['tipDistribution']['members'], 'name');
        $this->assertEqualsCanonicalizing(['Anna', 'Bilal'], $names);
    }

    public function test_upcoming_recurring_expenses_are_projected_independent_of_the_selected_period(): void
    {
        $vendor = $this->vendor();

        // Two occurrences of the "same" recurring cost (grouped by name+category+frequency) —
        // only the most recent should be used to project the next date.
        FinancialExpense::create([
            'vendor_id' => $vendor->id, 'name' => 'Rent', 'category' => 'rent',
            'amount' => 1400, 'is_recurring' => true, 'recurrence_frequency' => 'monthly',
            'occurred_at' => now()->subMonthsNoOverflow(2),
        ]);
        FinancialExpense::create([
            'vendor_id' => $vendor->id, 'name' => 'Rent', 'category' => 'rent',
            'amount' => 1500, 'is_recurring' => true, 'recurrence_frequency' => 'monthly',
            'occurred_at' => now()->subDays(40), // last month, overdue for a new monthly occurrence
        ]);
        // A one-off cost, not recurring — must not appear in the projection.
        FinancialExpense::create([
            'vendor_id' => $vendor->id, 'name' => 'One-off repair', 'category' => 'maintenance_repairs',
            'amount' => 80, 'is_recurring' => false, 'occurred_at' => now(),
        ]);

        // Requesting "today" — the projection must still surface the rent
        // reminder even though the underlying expense rows fall outside today.
        $body = $this->report($vendor, 'today');

        $this->assertCount(1, $body['upcomingRecurringExpenses']);
        $rent = $body['upcomingRecurringExpenses'][0];
        $this->assertEquals('Rent', $rent['name']);
        $this->assertEquals(1500.0, $rent['amount']);
        $this->assertEquals('monthly', $rent['recurrenceFrequency']);
        $this->assertTrue($rent['isOverdue']);
    }

    public function test_loyalty_liability_reflects_the_outstanding_points_balance(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => true, 'point_value' => 0.02]);

        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();
        CustomerLoyaltyPoint::create([
            'customer_id' => $customerA->id, 'vendor_id' => $vendor->id,
            'points_balance' => 300, 'total_earned' => 300, 'total_redeemed' => 0,
        ]);
        CustomerLoyaltyPoint::create([
            'customer_id' => $customerB->id, 'vendor_id' => $vendor->id,
            'points_balance' => 200, 'total_earned' => 200, 'total_redeemed' => 0,
        ]);

        $body = $this->report($vendor, 'today');

        // 500 outstanding points at €0.02 each = €10.00 — a real accounting
        // liability, deliberately independent of the selected report period
        // (a balance-sheet snapshot as of now, not scoped to "today").
        $this->assertEquals(500, $body['loyaltyLiability']['pointsOutstanding']);
        $this->assertEquals(0.02, $body['loyaltyLiability']['pointValue']);
        $this->assertEquals(10.0, $body['loyaltyLiability']['estimatedLiability']);
        $this->assertFalse($body['loyaltyLiability']['isDemoData']);
    }

    public function test_loyalty_liability_is_null_when_loyalty_is_not_enabled(): void
    {
        $vendor = $this->vendor();

        $body = $this->report($vendor, 'today');

        $this->assertNull($body['loyaltyLiability']);
    }

    public function test_loyalty_liability_flags_the_figure_as_demo_data_when_any_wallet_is_seeded(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => true, 'point_value' => 0.05]);

        $realCustomer = Customer::factory()->create();
        $demoCustomer = Customer::factory()->create();
        CustomerLoyaltyPoint::create([
            'customer_id' => $realCustomer->id, 'vendor_id' => $vendor->id,
            'points_balance' => 100, 'total_earned' => 100, 'total_redeemed' => 0,
        ]);
        CustomerLoyaltyPoint::create([
            'customer_id' => $demoCustomer->id, 'vendor_id' => $vendor->id,
            'points_balance' => 400, 'total_earned' => 400, 'total_redeemed' => 0, 'source' => 'demo',
        ]);

        $body = $this->report($vendor, 'today');

        // A vendor mixing one real wallet with a seeded demo one must still
        // show the whole figure as demo-tainted, not silently present a
        // partially-fabricated number as fully measured — same reasoning as
        // laborCost.isDemoData in the Profit & Loss summary.
        $this->assertEquals(500, $body['loyaltyLiability']['pointsOutstanding']);
        $this->assertTrue($body['loyaltyLiability']['isDemoData']);
    }

    public function test_operating_and_net_profit_extend_the_waterfall_past_prime_cost(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // No cart items attached, so VAT is 0 and netRevenue == grossRevenue —
        // keeps this test focused on the new waterfall math, not tax derivation
        // (already covered by test_summary_reflects_only_paid_orders_confirmed_in_period).
        $order = $this->order($vendor, $customer, $session, [
            'amount' => 200, 'payment_received' => true, 'payment_confirmed_at' => $this->vendorToday($vendor, 9),
        ]);

        // Labor: 2 hours at 10.00/hr = 20.00.
        $waiter = TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Waiter', 'email' => 'pl-waiter@example.com',
            'password' => bcrypt('password'), 'role' => 'waiter', 'status' => 'active',
        ]);
        StaffShift::create([
            'vendor_id' => $vendor->id, 'team_member_id' => $waiter->id,
            'clock_in_at' => $this->vendorToday($vendor, 9), 'clock_out_at' => $this->vendorToday($vendor, 11),
            'hourly_rate' => 10.00, 'source' => 'manual',
        ]);

        // Manual "other cost": 15.00.
        FinancialExpense::create([
            'vendor_id' => $vendor->id, 'name' => 'Cleaning supplies', 'category' => 'other',
            'amount' => 15, 'occurred_at' => $this->vendorToday($vendor, 10),
        ]);

        // Subscription fee: 25.00.
        $plan = SubscriptionPlan::create([
            'name' => 'Basic', 'monthly_price' => 25, 'yearly_price' => 250,
            'currency' => 'EUR', 'max_users' => 3, 'is_active' => true,
        ]);
        $subscription = Subscription::create([
            'vendor_id' => $vendor->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'start_date' => $this->vendorToday($vendor)->subMonth(), 'next_billing_date' => $this->vendorToday($vendor, 12),
        ]);
        Invoice::create([
            'subscription_id' => $subscription->id, 'invoice_number' => 'INV-PL-1',
            'amount' => 25, 'currency' => 'EUR', 'status' => 'paid',
            'billing_period_start' => $this->vendorToday($vendor)->subMonth(), 'billing_period_end' => $this->vendorToday($vendor, 12),
            'due_date' => $this->vendorToday($vendor, 12), 'paid_at' => $this->vendorToday($vendor, 12),
        ]);

        // Inventory waste: 5kg x 2.00/kg = 10.00.
        $lettuce = InventoryItem::create([
            'vendor_id' => $vendor->id, 'name' => 'Lettuce', 'unit' => 'kg', 'cost_per_unit' => 2.0,
        ]);
        InventoryStockMovement::create([
            'vendor_id' => $vendor->id, 'inventory_item_id' => $lettuce->id,
            'type' => 'waste', 'source' => 'Waste', 'note' => 'Spoiled',
            'quantity_change' => -5, 'quantity_before' => 10, 'quantity_after' => 5,
        ]);

        // Refund: 30.00, approved and resolved today.
        Refund::create([
            'refund_public_id' => 'RF-'.Str::upper(Str::random(10)),
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => 'refund',
            'status' => 'approved',
            'amount' => 30,
            'currency' => 'EUR',
            'reason' => 'other',
            'resolved_at' => $this->vendorToday($vendor, 13),
        ]);

        $body = $this->report($vendor, 'today');

        // grossProfit = netRevenue(200) - COGS(0) = 200.
        $this->assertEquals(200.0, $body['summary']['grossProfit']['value']);
        // operatingExpenses = otherCosts(15) + subscriptionFees(25) = 40.
        $this->assertEquals(40.0, $body['summary']['operatingExpenses']['value']);
        // operatingProfit = grossProfit(200) - laborCost(20) - operatingExpenses(40) = 140.
        $this->assertEquals(140.0, $body['summary']['operatingProfit']['value']);
        // netProfit = operatingProfit(140) - waste(10) - refunds(30) = 100.
        $this->assertEquals(100.0, $body['summary']['netProfit']['value']);
        // netMarginPercent = 100 / netRevenue(200) * 100 = 50.0.
        $this->assertEquals(50.0, $body['summary']['netMarginPercent']['value']);
    }

    public function test_cash_flow_statement_buckets_by_month_using_the_direct_method(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // Cash in: 200.00 paid this month.
        $order = $this->order($vendor, $customer, $session, [
            'amount' => 200, 'payment_received' => true, 'payment_confirmed_at' => $this->vendorToday($vendor, 9),
        ]);

        // Cash out — inventory: 45.00 (a failed dispatch must not count).
        InventoryPurchaseOrder::create([
            'vendor_id' => $vendor->id, 'supplier_id' => 'sup-1', 'supplier_name' => 'Test Supplier',
            'ordering_method' => 'API', 'quantity' => 10, 'unit' => 'kg', 'unit_cost' => 4.5,
            'currency' => 'EUR', 'status' => 'sent', 'created_at' => $this->vendorToday($vendor, 9),
        ]);

        // Cash out — subscription fees: 49.00.
        $plan = SubscriptionPlan::create([
            'name' => 'Basic', 'monthly_price' => 49, 'yearly_price' => 490,
            'currency' => 'EUR', 'max_users' => 3, 'is_active' => true,
        ]);
        $subscription = Subscription::create([
            'vendor_id' => $vendor->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'start_date' => $this->vendorToday($vendor)->subMonth(), 'next_billing_date' => $this->vendorToday($vendor, 12),
        ]);
        Invoice::create([
            'subscription_id' => $subscription->id, 'invoice_number' => 'INV-CF-1',
            'amount' => 49, 'currency' => 'EUR', 'status' => 'paid',
            'billing_period_start' => $this->vendorToday($vendor)->subMonth(), 'billing_period_end' => $this->vendorToday($vendor, 12),
            'due_date' => $this->vendorToday($vendor, 12), 'paid_at' => $this->vendorToday($vendor, 12),
        ]);

        // Cash out — other expenses: 1500.00 rent.
        FinancialExpense::create([
            'vendor_id' => $vendor->id, 'name' => 'Rent', 'category' => 'rent',
            'amount' => 1500, 'occurred_at' => $this->vendorToday($vendor, 10),
        ]);

        // Cash out — labor: 5 hours at 14.00/hr = 70.00.
        $waiter = TeamMember::create([
            'vendor_id' => $vendor->id, 'name' => 'Waiter', 'email' => 'waiter@example.com',
            'password' => bcrypt('password'), 'role' => 'waiter', 'status' => 'active',
        ]);
        StaffShift::create([
            'vendor_id' => $vendor->id, 'team_member_id' => $waiter->id,
            'clock_in_at' => $this->vendorToday($vendor, 9), 'clock_out_at' => $this->vendorToday($vendor, 14),
            'hourly_rate' => 14.00, 'source' => 'manual',
        ]);

        // Cash out — refunds: 30.00.
        Refund::create([
            'refund_public_id' => 'RF-'.Str::upper(Str::random(10)),
            'customer_id' => $customer->id, 'order_id' => $order->id,
            'type' => 'refund', 'status' => 'approved', 'amount' => 30,
            'currency' => 'EUR', 'reason' => 'other', 'resolved_at' => $this->vendorToday($vendor, 13),
        ]);

        $body = $this->report($vendor, 'month');

        $this->assertCount(1, $body['cashFlow']['monthly']);
        $row = $body['cashFlow']['monthly'][0];

        $this->assertEquals(now($vendor->resolveTimezone())->startOfMonth()->toDateString(), $row['month']);
        $this->assertEquals(200.0, $row['cashIn']);
        $this->assertEquals(45.0, $row['cashOutInventory']);
        $this->assertEquals(49.0, $row['cashOutSubscriptionFees']);
        $this->assertEquals(1500.0, $row['cashOutOtherExpenses']);
        $this->assertEquals(70.0, $row['cashOutLabor']);
        $this->assertEquals(30.0, $row['cashOutRefunds']);
        // netCashFlow = 200 - (45 + 49 + 1500 + 70 + 30) = -1494.
        $this->assertEquals(-1494.0, $row['netCashFlow']);

        // Single-month period, so totals must equal that one row.
        $totals = $body['cashFlow']['totals'];
        $this->assertEquals($row['cashIn'], $totals['cashIn']);
        $this->assertEquals($row['cashOutInventory'], $totals['cashOutInventory']);
        $this->assertEquals($row['cashOutSubscriptionFees'], $totals['cashOutSubscriptionFees']);
        $this->assertEquals($row['cashOutOtherExpenses'], $totals['cashOutOtherExpenses']);
        $this->assertEquals($row['cashOutLabor'], $totals['cashOutLabor']);
        $this->assertEquals($row['cashOutRefunds'], $totals['cashOutRefunds']);
        $this->assertEquals($row['netCashFlow'], $totals['netCashFlow']);
        $this->assertFalse($body['cashFlow']['isLaborDemoData']);
    }

    public function test_cash_flow_statement_fills_every_month_in_a_longer_period_even_with_no_activity(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // Only this month has any activity — a "year" period must still
        // return a row for every month so the chart's spacing is real
        // calendar time, not just "months that happened to have activity".
        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'payment_received' => true, 'payment_confirmed_at' => $this->vendorToday($vendor, 9),
        ]);

        $body = $this->report($vendor, 'year');

        // "This year" runs from January through *today*, not through
        // December 31st — a period that hasn't happened yet has no months
        // to fill (see FinancialReportPeriod::clampFutureEnd()). Computed in
        // the vendor's own timezone, same as the API itself — see
        // vendorToday()'s doc comment for why the bare now() (app timezone)
        // can disagree with it.
        $this->assertCount((int) now($vendor->resolveTimezone())->month, $body['cashFlow']['monthly']);
        $activeMonths = array_filter($body['cashFlow']['monthly'], fn (array $row) => $row['cashIn'] > 0);
        $this->assertCount(1, $activeMonths);
        $this->assertEquals(100.0, array_values($activeMonths)[0]['cashIn']);
    }

    public function test_month_and_year_periods_do_not_extend_into_the_future(): void
    {
        $vendor = $this->vendor();

        // Computed in the vendor's own timezone, same as the API itself —
        // see vendorToday()'s doc comment for why the bare now() (app
        // timezone) can disagree with it.
        $today = now($vendor->resolveTimezone())->toDateString();

        $body = $this->report($vendor, 'month');
        $this->assertEquals($today, $body['range']['to']);

        $yearBody = $this->report($vendor, 'year');
        $this->assertEquals($today, $yearBody['range']['to']);
    }

    public function test_cash_flow_statement_expands_to_the_full_month_for_a_narrower_selected_period(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $tz = $vendor->resolveTimezone();

        // This test's whole premise is "today" being narrower than the
        // calendar month containing it — that's only true when today isn't
        // the 1st of the month (on the 1st, "today" and "the month so far"
        // are the same range, and expandedToFullMonth is correctly false).
        // Pinning the clock to a safe mid-month day makes the test's
        // expectations true regardless of which real-world day it runs on.
        $this->travelTo(now($tz)->setDate(2026, 6, 15)->setTime(12, 0));

        // Rent paid earlier this month — outside "today", but still within
        // the current calendar month. Computed in the vendor's own
        // timezone (see vendorToday()'s doc comment), then converted to app
        // timezone before storing.
        FinancialExpense::create([
            'vendor_id' => $vendor->id, 'name' => 'Rent', 'category' => 'rent',
            'amount' => 1500, 'occurred_at' => now($tz)->startOfMonth()->setTimezone(config('app.timezone')),
        ]);

        $this->order($vendor, $customer, $session, [
            'amount' => 50, 'payment_received' => true, 'payment_confirmed_at' => $this->vendorToday($vendor, 9),
        ]);

        $body = $this->report($vendor, 'today');

        // The main report period itself must stay exactly "today" — only
        // the Cash Flow section expands.
        $today = now($tz)->toDateString();
        $this->assertEquals($today, $body['range']['from']);
        $this->assertEquals($today, $body['range']['to']);

        $this->assertCount(1, $body['cashFlow']['monthly']);
        $row = $body['cashFlow']['monthly'][0];
        $this->assertEquals(now($tz)->startOfMonth()->toDateString(), $row['month']);
        $this->assertEquals(50.0, $row['cashIn']);
        // Rent from earlier in the month must still be included, even
        // though it happened before "today".
        $this->assertEquals(1500.0, $row['cashOutOtherExpenses']);
        $this->assertTrue($body['cashFlow']['expandedToFullMonth']);
    }

    public function test_cash_flow_statement_is_not_marked_expanded_for_a_full_month_period(): void
    {
        $vendor = $this->vendor();

        $body = $this->report($vendor, 'month');

        $this->assertFalse($body['cashFlow']['expandedToFullMonth']);
    }

    // ------------------------------------------------------------------- fixtures

    private function vendor(): Vendor
    {
        // Every test in this file exercises real report data, not the
        // plan-gate itself (that's FinancialReportFeatureGateTest) — granted
        // by default here so the 2026-09-07 gating fix doesn't turn every
        // one of them into a locked-response assertion instead.
        return $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
    }

    /** @return array{0: Customer, 1: TableScanSession} */
    private function context(Vendor $vendor): array
    {
        $customer = Customer::factory()->create();
        $table = $vendor->restaurantTables()->create([
            'number' => 1,
            'name' => 'Table 1',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $session = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '9001',
            'type' => 'dine_in',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        return [$customer, $session];
    }

    /** Same as context(), but at a different table — for tests needing two distinct sessions on the same vendor. */
    private function secondContext(Vendor $vendor): array
    {
        $customer = Customer::factory()->create();
        $table = $vendor->restaurantTables()->create([
            'number' => 2,
            'name' => 'Table 2',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $session = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '9002',
            'type' => 'dine_in',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        return [$customer, $session];
    }

    private function category(Vendor $vendor): MenuCategory
    {
        return MenuCategory::create([
            'vendor_id' => $vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function order(Vendor $vendor, ?Customer $customer, TableScanSession $session, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'vendor_id' => $vendor->id,
            'customer_id' => $customer?->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_SERVED,
            'order_type' => 'dine_in',
            'amount' => 10,
            'vat_amount' => 0,
            'service_fee' => 0,
            'tip_amount' => 0,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'payment_received' => false,
            'confirmed_at' => now()->subMinutes(30),
            'payment_confirmed_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(30),
        ], $attributes));
    }

    private function report(Vendor $vendor, string $period = 'week'): array
    {
        return $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period={$period}",
            $this->headers($vendor),
        )->assertOk()->json();
    }

    /**
     * A vendor-local instant, safely anchored inside "today" rather than
     * built from an offset off the bare now() helper (app timezone/UTC).
     * "N hours before now()" can silently land in *yesterday* the moment
     * the vendor's own timezone (e.g. Europe/Vienna, UTC+2 in summer) has
     * already crossed local midnight while app-timezone now() has not —
     * a real ~2-hour window every day, not a hypothetical one. Also
     * converts to app timezone before returning: Eloquent's datetime cast
     * naively formats whatever timezone a Carbon instance carries with no
     * conversion (it does not normalize to config('app.timezone') before
     * storing), so a vendor-local instant handed to a factory/create()
     * un-converted would silently mean a different real instant once read
     * back — confirmed directly against Order::factory() earlier in this
     * suite's own history.
     */
    private function vendorToday(Vendor $vendor, int $hour = 9, int $minute = 0): \Carbon\CarbonInterface
    {
        return now($vendor->resolveTimezone())
            ->startOfDay()
            ->addHours($hour)
            ->addMinutes($minute)
            ->setTimezone(config('app.timezone'));
    }

    private function headers(Vendor $vendor): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
