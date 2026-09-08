<?php

namespace Tests\Feature\Analytics;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerLoyaltyPoint;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryStockMovement;
use App\Models\LoyaltyTransaction;
use App\Models\MenuCategory;
use App\Models\MenuItemIngredient;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\Analytics\InsightEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * Covers the behaviours that were wrong in the previous analytics endpoint,
 * so a regression on any of them fails here rather than in front of a vendor.
 */
class VendorAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    public function test_order_count_excludes_drafts_and_cancellations(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // Two real, paid orders.
        $this->order($vendor, $customer, $session, ['amount' => 40, 'payment_received' => true]);
        $this->order($vendor, $customer, $session, ['amount' => 60, 'payment_received' => true]);

        // A draft — a basket that was never confirmed.
        $this->order($vendor, $customer, $session, [
            'amount' => 500,
            'confirmed_at' => null,
            'payment_received' => false,
        ]);

        // A cancellation.
        $this->order($vendor, $customer, $session, [
            'amount' => 500,
            'cancelled_at' => now(),
            'payment_received' => false,
        ]);

        $body = $this->analytics($vendor);

        $this->assertEquals(2, $body['summary']['orders']['value']);
        $this->assertEquals(100.0, $body['summary']['grossRevenue']['value']);
    }

    public function test_average_order_value_divides_paid_revenue_by_paid_orders(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, ['amount' => 50, 'payment_received' => true]);
        $this->order($vendor, $customer, $session, ['amount' => 30, 'payment_received' => true]);

        // Confirmed but unpaid: counts as an order, must not drag AOV down.
        $this->order($vendor, $customer, $session, ['amount' => 90, 'payment_received' => false]);

        $body = $this->analytics($vendor);

        $this->assertEquals(3, $body['summary']['orders']['value']);
        $this->assertEquals(2, $body['summary']['paidOrders']['value']);
        $this->assertEquals(40.0, $body['summary']['avgOrderValue']['value']);
    }

    public function test_menu_revenue_uses_the_price_that_was_live_when_ordered(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        // Version one at 10.00, later superseded by a version at 30.00.
        $oldVersion = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Ribeye',
            'price' => 10,
            'vat_rate' => 0,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        $productUid = $oldVersion->product_uid;
        $oldVersion->delete();

        $vendor->menuItems()->create([
            'product_uid' => $productUid,
            'menu_category_id' => $category->id,
            'name' => 'Ribeye',
            'price' => 30,
            'vat_rate' => 0,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 20,
            'payment_received' => true,
        ]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $oldVersion->id,
            'order_id' => $order->id,
            'quantity' => 2,
        ]);

        $body = $this->analytics($vendor);

        $this->assertCount(1, $body['menu']);
        // Priced at the 10.00 version that was ordered, not the 30.00 one that
        // replaced it. Gross, so 2 × 10.00 + 10% Austrian food VAT.
        $this->assertEquals(22.0, $body['menu'][0]['revenue']);
        $this->assertEquals(2, $body['menu'][0]['quantity']);
    }

    public function test_discounted_orders_are_identified_from_the_version_that_was_ordered(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $discounted = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Discounted plate',
            'price' => 20,
            'vat_rate' => 0,
            'has_discount' => true,
            'discount_percent' => 25,
            'discounted_price' => 15,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 15,
            'payment_received' => true,
        ]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $discounted->id,
            'order_id' => $order->id,
            'quantity' => 1,
        ]);

        $body = $this->analytics($vendor);

        $this->assertTrue($body['discounts']['available']);
        $this->assertEquals(1, $body['discounts']['discountedOrders']);
        $this->assertEquals(0, $body['discounts']['fullPriceOrders']);
        // Gross of 10% VAT: sold 15.00 net -> 16.50, list 20.00 net -> 22.00.
        $this->assertEquals(16.5, $body['discounts']['discountedRevenue']);
        $this->assertEquals(5.5, $body['discounts']['revenueForgone']);
    }

    public function test_menu_and_categories_are_scoped_to_the_requested_period(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $item = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Old seller',
            'price' => 10,
            'vat_rate' => 0,
            'available' => true,
            'is_active' => true,
            // A large lifetime counter the endpoint must ignore.
            'ordered_count' => 9999,
        ]);

        $stale = $this->order($vendor, $customer, $session, [
            'amount' => 10,
            'payment_received' => true,
            'created_at' => now()->subMonths(6),
            'confirmed_at' => now()->subMonths(6),
        ]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $item->id,
            'order_id' => $stale->id,
            'quantity' => 3,
        ]);

        $body = $this->analytics($vendor, 'daily');

        $this->assertFalse($body['hasData']);
        // menu() deliberately lists every current menu item regardless of
        // period sales (see its own doc comment — a vendor scanning for what
        // to fix needs to see the zero rows too), so "Old seller" still
        // appears here. What must actually be scoped to the period is its
        // quantity/revenue: the stale 6-month-old order's cart line must not
        // leak into today's figures.
        $this->assertCount(1, $body['menu']);
        $this->assertEquals('Old seller', $body['menu'][0]['name']);
        $this->assertEquals(0, $body['menu'][0]['quantity']);
        $this->assertEquals(0, $body['menu'][0]['revenue']);
        // categories(), unlike menu(), is built purely from this period's
        // cart lines with no "every current X" pass — correctly empty here.
        $this->assertSame([], $body['categories']);
    }

    public function test_split_bill_orders_are_not_double_counted(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $main = $this->order($vendor, $customer, $session, [
            'amount' => 30,
            'payment_received' => true,
        ]);

        // A share order carries only its own portion.
        $this->order($vendor, $customer, $session, [
            'amount' => 15,
            'payment_received' => true,
            'parent_order_id' => $main->id,
        ]);

        $body = $this->analytics($vendor);

        $this->assertEquals(45.0, $body['summary']['grossRevenue']['value']);
    }

    /**
     * Regression test: `orders.vat_amount` is never written by any live
     * write path (only seeders touch it), so it is always 0 on real vendor
     * data. VAT/net revenue must be derived fresh from the paid order's own
     * cart items instead — this also proves an unpaid order's cart items
     * (which would inflate VAT by 20.00 here if wrongly included) stay out.
     */
    public function test_vat_and_net_revenue_are_derived_from_cart_items_not_the_stale_stored_column(): void
    {
        $vendor = $this->vendor(); // country AT: food 10%
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $food = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Schnitzel',
            'price' => 100,
            'vat_rate' => 0,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        $alcohol = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Wine',
            'price' => 100,
            'vat_rate' => 0,
            'tax_category' => 'beverage_alcoholic',
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        // Paid order: 100.00 net food @ 10% => 110.00 gross. `vat_amount` is
        // set to an obviously wrong value to prove it is never read.
        $paid = $this->order($vendor, $customer, $session, [
            'amount' => 110,
            'vat_amount' => 999999,
            'payment_received' => true,
        ]);
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $food->id,
            'order_id' => $paid->id,
            'quantity' => 1,
        ]);

        // Confirmed but unpaid — its 100.00 net alcohol line would add 20.00
        // of VAT (20% AT alcohol rate) if it leaked into the total.
        $unpaid = $this->order($vendor, $customer, $session, [
            'amount' => 120,
            'payment_received' => false,
        ]);
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $alcohol->id,
            'order_id' => $unpaid->id,
            'quantity' => 1,
        ]);

        $body = $this->analytics($vendor);

        $this->assertEquals(10.0, $body['summary']['vatCollected']['value']);
        $this->assertEquals(100.0, $body['summary']['netRevenue']['value']);
        $this->assertEquals(110.0, $body['summary']['grossRevenue']['value']);
    }

    public function test_vat_collected_sums_across_multiple_tax_categories(): void
    {
        $vendor = $this->vendor(); // country AT: food 10%, alcohol 20%
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $food = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Schnitzel',
            'price' => 50,
            'vat_rate' => 0,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        $alcohol = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Wine',
            'price' => 50,
            'vat_rate' => 0,
            'tax_category' => 'beverage_alcoholic',
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        // 50.00 net food @ 10% => 55.00 gross; 50.00 net alcohol @ 20% => 60.00 gross.
        $order = $this->order($vendor, $customer, $session, [
            'amount' => 115,
            'payment_received' => true,
        ]);
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $food->id,
            'order_id' => $order->id,
            'quantity' => 1,
        ]);
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $alcohol->id,
            'order_id' => $order->id,
            'quantity' => 1,
        ]);

        $body = $this->analytics($vendor);

        // 5.00 food VAT + 10.00 alcohol VAT.
        $this->assertEquals(15.0, $body['summary']['vatCollected']['value']);
        $this->assertEquals(100.0, $body['summary']['netRevenue']['value']);
    }

    /**
     * Mirrors FinancialReportService's own regression test for the same
     * bug shape: without applySharing, a shared cart line's VAT is derived
     * from its FULL gross even though the order was only ever actually
     * charged its 1/N share, inflating vatCollected/netRevenue for any
     * vendor using order-sharing.
     */
    public function test_shared_cart_items_are_divided_by_sharer_count_for_vat(): void
    {
        $vendor = $this->vendor(); // country AT: food 10%
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $menuItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Shared Pizza',
            'price' => 100,
            'vat_rate' => 0,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        // 100.00 net @ 10% => 110.00 gross, shared with one other order —
        // this order was only ever actually charged its half.
        $order = $this->order($vendor, $customer, $session, [
            'amount' => 55,
            'payment_received' => true,
        ]);
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'shared_order_ids' => [999999],
        ]);

        $body = $this->analytics($vendor);

        $this->assertEquals(5.0, $body['summary']['vatCollected']['value']);
        $this->assertEquals(50.0, $body['summary']['netRevenue']['value']);
    }

    /**
     * The cart item is stored once, against its owning order — if that
     * owning order falls outside the current window while the *sharing*
     * order (whose own `orders.amount` genuinely includes its half) falls
     * inside it, a query scoped only to `order_id IN (this period's orders)`
     * misses the shared line entirely. That silently zeroed the sharer's own
     * derived VAT and dropped the item from Menu Performance, even though
     * the sharer's revenue was still counted in the headline total.
     */
    public function test_shared_cart_item_across_a_period_boundary_still_counts_the_sharers_side(): void
    {
        $vendor = $this->vendor(); // AT: food 10%
        [$ownerCustomer, $ownerSession] = $this->context($vendor);

        $sharerCustomer = Customer::factory()->create();
        $sharerTable = $this->table($vendor, 2);
        $sharerSession = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $sharerTable->id,
            'customer_id' => $sharerCustomer->id,
            'pin' => '9002',
            'type' => 'dine_in',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $category = $this->category($vendor);
        $menuItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Shared Pizza',
            'price' => 100,
            'vat_rate' => 0,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        // Owner order: OUTSIDE the "daily" (last 7 days) window used below.
        $ownerOrder = $this->order($vendor, $ownerCustomer, $ownerSession, [
            'amount' => 55, 'payment_received' => true,
            'created_at' => now()->subDays(10),
        ]);

        // Sharer order: INSIDE the window.
        $sharerOrder = $this->order($vendor, $sharerCustomer, $sharerSession, [
            'amount' => 55, 'payment_received' => true,
            'created_at' => now(),
        ]);

        // The cart item lives on the owner's order — this is the row a
        // naive `whereIn('order_id', $currentPeriodOrderIds)` query misses.
        CartItem::create([
            'table_scan_session_id' => $ownerSession->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $ownerOrder->id,
            'quantity' => 1,
            'shared_order_ids' => [$sharerOrder->id],
        ]);

        $body = $this->analytics($vendor, 'daily');

        // Only the sharer's order is inside the window.
        $this->assertEquals(1, $body['summary']['orders']['value']);
        $this->assertEquals(5.0, $body['summary']['vatCollected']['value']);
        $this->assertEquals(50.0, $body['summary']['netRevenue']['value']);

        $menuRow = collect($body['menu'])->firstWhere('name', 'Shared Pizza');
        $this->assertNotNull($menuRow, 'Shared item should still appear in Menu Performance for the sharer.');
        $this->assertEquals(1, $menuRow['quantity']);
        $this->assertEqualsWithDelta(55.0, $menuRow['revenue'], 0.01);
    }

    public function test_guests_per_table_groups_overlapping_scan_sessions_into_one_visit(): void
    {
        $vendor = $this->vendor();
        $customer = Customer::factory()->create();
        $table = $this->table($vendor, 1);

        // Three guests scanning the same table within one sitting.
        foreach (['1001', '1002', '1003'] as $i => $pin) {
            TableScanSession::create([
                'vendor_id' => $vendor->id,
                'restaurant_table_id' => $table->id,
                'customer_id' => $customer->id,
                'pin' => $pin,
                'type' => 'dine_in',
                'status' => 'closed',
                'scanned_at' => now()->subMinutes(90 - $i * 5),
                'closed_at' => now()->subMinutes(10),
            ]);
        }

        $body = $this->analytics($vendor);

        $this->assertEquals(3, $body['summary']['totalGuests']['value']);
        $this->assertEquals(1, $body['summary']['tableVisits']['value']);
        $this->assertEquals(3.0, $body['summary']['avgGuestsPerTable']['value']);
    }

    public function test_payment_completion_is_measured_from_payment_attempts(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $paid = $this->order($vendor, $customer, $session, [
            'amount' => 25,
            'payment_received' => true,
        ]);
        $failedOrder = $this->order($vendor, $customer, $session, ['amount' => 25]);

        $this->payment($vendor, $paid, 'succeeded');
        $this->payment($vendor, $failedOrder, 'failed');

        $body = $this->analytics($vendor);

        $this->assertEquals(50.0, $body['payments']['completionRate']);
        $this->assertEquals(50.0, $body['payments']['failureRate']);
        $this->assertEquals(1, $body['payments']['failedCount']);
    }

    public function test_empty_vendor_returns_nulls_rather_than_zeros(): void
    {
        $vendor = $this->vendor();

        $body = $this->analytics($vendor);

        $this->assertFalse($body['hasData']);
        $this->assertNull($body['summary']['avgOrderValue']['value']);
        $this->assertNull($body['summary']['avgItemsPerOrder']['value']);
        $this->assertNull($body['payments']['completionRate']);
        $this->assertNull($body['service']['tableTurnoverMinutes']['value']);
        $this->assertNull($body['service']['tableTurnoverMinutes']['baseline']);
        $this->assertNull($body['service']['tableTurnoverMinutes']['status']);
        $this->assertSame([], $body['menu']);
    }

    public function test_insights_stay_silent_below_the_minimum_sample(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, ['amount' => 20, 'payment_received' => true]);

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights?period=daily",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertSame([], $body['insights']);
    }

    public function test_unanswered_reviews_are_reported(): void
    {
        $vendor = $this->vendor();
        $customer = Customer::factory()->create();

        Review::create([
            'review_public_id' => 'rev_'.Str::random(10),
            'vendor_id' => $vendor->id,
            'customer_id' => $customer->id,
            'rating' => 2,
            'text' => 'Long wait.',
        ]);

        Review::create([
            'review_public_id' => 'rev_'.Str::random(10),
            'vendor_id' => $vendor->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'text' => 'Great.',
            'vendor_reply' => 'Thank you.',
            'vendor_replied_at' => now(),
        ]);

        $body = $this->analytics($vendor);

        $this->assertEquals(1, $body['reviews']['unanswered']);
        $this->assertEquals(1, $body['reviews']['unansweredCritical']);
    }


    public function test_menu_revenue_reconciles_with_headline_revenue(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $item = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Plate',
            'price' => 10,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        $paid = $this->order($vendor, $customer, $session, [
            'amount' => 11,
            'payment_received' => true,
        ]);

        // Confirmed but never paid. Its items must not appear in menu revenue,
        // or the menu table adds up to more than the revenue headline.
        $unpaid = $this->order($vendor, $customer, $session, [
            'amount' => 55,
            'payment_received' => false,
        ]);

        foreach ([$paid->id => 1, $unpaid->id => 5] as $orderId => $quantity) {
            CartItem::create([
                'table_scan_session_id' => $session->id,
                'menu_item_id' => $item->id,
                'order_id' => $orderId,
                'quantity' => $quantity,
            ]);
        }

        $body = $this->analytics($vendor);

        $menuRevenue = array_sum(array_column($body['menu'], 'revenue'));
        $categoryRevenue = array_sum(array_column($body['categories'], 'revenue'));

        // Only the paid order's single item: 10.00 net + 10% VAT.
        $this->assertEquals(11.0, $menuRevenue);
        $this->assertEquals(11.0, $categoryRevenue);
        $this->assertEquals(11.0, $body['summary']['grossRevenue']['value']);
    }

    public function test_quietest_slot_ignores_hours_outside_trading(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // A busy evening hour across several days.
        foreach (range(1, 5) as $day) {
            foreach (range(1, 4) as $n) {
                $this->order($vendor, $customer, $session, [
                    'created_at' => now()->subDays($day)->setTime(19, 0),
                    'confirmed_at' => now()->subDays($day)->setTime(19, 0),
                ]);
            }
        }

        // A softer but still-trading hour.
        $this->order($vendor, $customer, $session, [
            'created_at' => now()->subDays(2)->setTime(18, 0),
            'confirmed_at' => now()->subDays(2)->setTime(18, 0),
        ]);
        $this->order($vendor, $customer, $session, [
            'created_at' => now()->subDays(3)->setTime(18, 0),
            'confirmed_at' => now()->subDays(3)->setTime(18, 0),
        ]);
        $this->order($vendor, $customer, $session, [
            'created_at' => now()->subDays(4)->setTime(18, 0),
            'confirmed_at' => now()->subDays(4)->setTime(18, 0),
        ]);
        $this->order($vendor, $customer, $session, [
            'created_at' => now()->subDays(5)->setTime(18, 0),
            'confirmed_at' => now()->subDays(5)->setTime(18, 0),
        ]);

        // One stray order in the middle of the night — closed time, not a slot
        // the vendor can act on.
        $this->order($vendor, $customer, $session, [
            'created_at' => now()->subDays(3)->setTime(3, 0),
            'confirmed_at' => now()->subDays(3)->setTime(3, 0),
        ]);

        $body = $this->analytics($vendor, 'weekly');

        $this->assertNotNull($body['peak']['quietest']);
        $this->assertNotSame(3, $body['peak']['quietest']['hour']);
    }

    public function test_checkout_duration_measures_the_payment_attempt_not_the_whole_bill(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $order = $this->order($vendor, $customer, $session, [
            'amount' => 20,
            'payment_received' => true,
            'confirmed_at' => now()->subMinutes(90),
            'created_at' => now()->subMinutes(90),
        ]);

        $payment = $this->payment($vendor, $order, 'succeeded');
        // Intent opened 40s before capture, 90 minutes after the order started.
        OrderPayment::where('id', $payment->id)->update([
            'created_at' => now()->subSeconds(40),
            'paid_at' => now(),
        ]);

        $body = $this->analytics($vendor);

        // Seconds, not the ~90 minutes the table was occupied.
        $this->assertLessThan(120, $body['payments']['medianCheckoutSeconds']);
    }


    public function test_table_turnover_measures_the_visit_not_each_guest_session(): void
    {
        $vendor = $this->vendor();
        $customer = Customer::factory()->create();
        $table = $this->table($vendor, 1);

        // One party of three. The table is held from the first scan at 19:00
        // until the last guest closes at 21:00 — two hours, even though the
        // guest who joined at 19:30 only had a 90 minute session of their own.
        $opened = now()->subDay()->setTime(19, 0);

        foreach ([0, 15, 30] as $index => $offsetMinutes) {
            TableScanSession::create([
                'vendor_id' => $vendor->id,
                'restaurant_table_id' => $table->id,
                'customer_id' => $customer->id,
                'pin' => '70'.$index.'1',
                'type' => 'dine_in',
                'status' => 'closed',
                'scanned_at' => $opened->copy()->addMinutes($offsetMinutes),
                'closed_at' => $opened->copy()->addMinutes(120),
            ]);
        }

        $body = $this->analytics($vendor, 'weekly');

        $this->assertEquals(1, $body['service']['tableVisitsMeasured']);
        $this->assertEquals(120.0, $body['service']['tableTurnoverMinutes']['value']);
        $this->assertEquals(3, $body['summary']['totalGuests']['value']);
    }

    public function test_service_timings_build_a_rolling_baseline_from_prior_days(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // Four prior days at a steady 10 minutes. These become the baseline.
        foreach ([8, 9, 10, 11] as $daysAgo) {
            $at = now()->subDays($daysAgo)->setTime(19, 0);
            $this->order($vendor, $customer, $session, [
                'created_at' => $at,
                'confirmed_at' => $at,
                'in_progress_at' => $at,
                'served_at' => $at->copy()->addMinutes(10),
            ]);
        }

        // Today runs at 30 minutes — three times the established normal.
        $today = now()->setTime(19, 0);
        $this->order($vendor, $customer, $session, [
            'created_at' => $today,
            'confirmed_at' => $today,
            'in_progress_at' => $today,
            'served_at' => $today->copy()->addMinutes(30),
        ]);

        $body = $this->analytics($vendor, 'daily');
        $stage = $body['service']['kitchenToServedMinutes'];

        // The daily view builds its baseline day by day.
        $this->assertSame('day', $body['service']['baselineUnit']);
        $this->assertEquals(4, $stage['baselineSamples'], 'each prior day with data contributes one sample');
        $this->assertEquals(10.0, $stage['baseline']);
        $this->assertEquals(30.0, $stage['value']);
        $this->assertEquals(20.0, $stage['delta']);
        $this->assertSame('slower', $stage['status']);
    }

    public function test_no_baseline_is_offered_until_enough_history_exists(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // A single prior day is not a normal to judge against.
        $earlier = now()->subDays(9)->setTime(19, 0);
        $this->order($vendor, $customer, $session, [
            'created_at' => $earlier,
            'confirmed_at' => $earlier,
            'in_progress_at' => $earlier,
            'served_at' => $earlier->copy()->addMinutes(10),
        ]);

        $today = now()->setTime(19, 0);
        $this->order($vendor, $customer, $session, [
            'created_at' => $today,
            'confirmed_at' => $today,
            'in_progress_at' => $today,
            'served_at' => $today->copy()->addMinutes(40),
        ]);

        $stage = $this->analytics($vendor, 'daily')['service']['kitchenToServedMinutes'];

        $this->assertEquals(1, $stage['baselineSamples']);
        $this->assertNull($stage['baseline']);
        $this->assertNull($stage['status'], 'no verdict from a single prior period');
        $this->assertEquals(40.0, $stage['value']);
    }


    public function test_cash_tips_are_counted_and_compared_against_digital(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // Cash can carry a tip: the guest can add one when requesting cash
        // payment, and a waiter can enter one when confirming it.
        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 5, 'payment_method' => 'cash', 'payment_received' => true,
        ]);
        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 0, 'payment_method' => 'cash', 'payment_received' => true,
        ]);
        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 0, 'payment_method' => 'cash', 'payment_received' => true,
        ]);
        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 0, 'payment_method' => 'cash', 'payment_received' => true,
        ]);

        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 10, 'payment_method' => 'card', 'payment_received' => true,
        ]);
        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 10, 'payment_method' => 'card', 'payment_received' => true,
        ]);

        $tips = $this->analytics($vendor)['tips'];

        // Every paid order counts, cash included.
        $this->assertEquals(6, $tips['paidOrders']);
        $this->assertEquals(3, $tips['tippedOrders']);
        $this->assertEquals(25.0, $tips['total']);

        $capture = $tips['cashCapture'];
        $this->assertTrue($capture['comparable']);
        $this->assertEquals(25.0, $capture['cashParticipation'], '1 of 4 cash orders tipped');
        $this->assertEquals(100.0, $capture['digitalParticipation'], '2 of 2 card orders tipped');
        $this->assertEquals(75.0, $capture['gap']);
    }

    public function test_cash_capture_is_not_comparable_without_both_kinds_of_payment(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 10, 'payment_method' => 'card', 'payment_received' => true,
        ]);

        $capture = $this->analytics($vendor)['tips']['cashCapture'];

        $this->assertFalse($capture['comparable']);
        $this->assertEquals(0, $capture['cashOrders']);
    }

    public function test_average_tip_is_reported_both_overall_and_among_tippers(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // One generous tipper among four card orders.
        $this->order($vendor, $customer, $session, [
            'amount' => 100, 'tip_amount' => 20, 'payment_method' => 'card', 'payment_received' => true,
        ]);

        foreach (range(1, 3) as $n) {
            $this->order($vendor, $customer, $session, [
                'amount' => 100, 'tip_amount' => 0, 'payment_method' => 'card', 'payment_received' => true,
            ]);
        }

        $tips = $this->analytics($vendor)['tips'];

        // Spread across everyone it looks like 5.00; among those who actually
        // tipped it is 20.00. Reporting only the first would hide the split.
        $this->assertEquals(5.0, $tips['averageTip']);
        $this->assertEquals(20.0, $tips['averageWhenTipped']);
        $this->assertEquals(25.0, $tips['participationRate']['value']);
    }

    public function test_absolute_metric_shows_a_real_delta_when_the_previous_period_was_zero(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // Previous "daily" window (7-14 days ago): one paid order, no tip at
        // all — participationRate is a real, measured 0%, not "no data".
        $this->order($vendor, $customer, $session, [
            'amount' => 20, 'tip_amount' => 0, 'payment_received' => true,
            'created_at' => now()->subDays(10),
        ]);

        // Current window: everyone tips.
        $this->order($vendor, $customer, $session, [
            'amount' => 20, 'tip_amount' => 5, 'payment_received' => true,
            'created_at' => now(),
        ]);

        $body = $this->analytics($vendor, 'daily');

        $this->assertEquals(100.0, $body['tips']['participationRate']['value']);
        $this->assertEquals(0.0, $body['tips']['participationRate']['previous']);
        // Previously this stayed null even though 0 -> 100 is a real,
        // well-defined absolute delta (an unguarded zero-previous check
        // blocked it for every absolute-unit metric, not just percent ones).
        $this->assertEquals(100.0, $body['tips']['participationRate']['delta']);
    }

    public function test_today_period_covers_only_the_current_calendar_day(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, [
            'amount' => 30, 'payment_received' => true, 'created_at' => now(),
        ]);
        // Yesterday's order must not leak into "today".
        $this->order($vendor, $customer, $session, [
            'amount' => 999, 'payment_received' => true, 'created_at' => now()->subDay(),
        ]);

        $body = $this->analytics($vendor, 'today');

        $this->assertEquals(1, $body['summary']['orders']['value']);
        $this->assertEquals(30.0, $body['summary']['grossRevenue']['value']);
        $this->assertEquals('Today', $body['range']['periodLabel']);
        $this->assertEquals('vs yesterday', $body['range']['comparisonLabel']);
    }

    public function test_custom_period_respects_an_explicit_date_range(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, [
            'amount' => 40, 'payment_received' => true,
            'created_at' => Carbon::parse('2026-06-15 12:00:00', $vendor->resolveTimezone()),
        ]);
        // Outside the requested range — must not be counted.
        $this->order($vendor, $customer, $session, [
            'amount' => 500, 'payment_received' => true,
            'created_at' => Carbon::parse('2026-07-01 12:00:00', $vendor->resolveTimezone()),
        ]);

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period=custom&from=2026-06-01&to=2026-06-30",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertEquals(1, $body['summary']['orders']['value']);
        $this->assertEquals(40.0, $body['summary']['grossRevenue']['value']);
    }

    public function test_custom_period_falls_back_to_a_sensible_default_when_dates_are_missing(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, [
            'amount' => 15, 'payment_received' => true, 'created_at' => now(),
        ]);

        // No from/to at all — must not error, just fall back rather than 500.
        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period=custom",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertEquals(1, $body['summary']['orders']['value']);
    }

    public function test_malformed_custom_dates_are_rejected_rather_than_silently_misparsed(): void
    {
        $vendor = $this->vendor();

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period=custom&from=9999-99-99&to=2026-06-30",
            $this->headers($vendor),
        )->assertUnprocessable()->assertJsonValidationErrors('from');

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights?period=custom&from=2026-06-01&to=not-a-date",
            $this->headers($vendor),
        )->assertUnprocessable()->assertJsonValidationErrors('to');

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights/suggested-questions?from=9999-99-99",
            $this->headers($vendor),
        )->assertUnprocessable()->assertJsonValidationErrors('from');
    }

    public function test_custom_period_wholly_in_the_future_collapses_to_today_instead_of_inverting(): void
    {
        // Well-formed and to >= from, so GetAnalyticsRequest lets it through —
        // neither check catches a range that's entirely in the future, which
        // used to leave `end` clamped to today while `start` stayed in the
        // future: an inverted (start > end) range reported as if it were real.
        $vendor = $this->vendor();

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period=custom&from=2099-01-01&to=2099-01-05",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertLessThanOrEqual(strtotime($body['range']['to']), strtotime($body['range']['from']));
    }

    public function test_a_custom_range_longer_than_the_max_is_truncated_and_flagged(): void
    {
        $vendor = $this->vendor();

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period=custom&from=2020-01-01&to=2026-01-01",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertTrue($body['range']['truncated']);
        $this->assertEquals(366, $body['range']['maxCustomDays']);
        // `from`/`to` are ISO instants in UTC, not the vendor's own
        // timezone, so a vendor west/east of UTC can shift the calendar
        // date by one day either side of midnight — assert on the span
        // instead of an exact date string.
        $spanDays = (strtotime($body['range']['to']) - strtotime($body['range']['from'])) / 86400;
        $this->assertEqualsWithDelta(366, $spanDays, 1.0);
        // Truncated to ~366 days from `from`, not silently left at the requested `to`.
        $this->assertNotEquals('2026-01-01', substr($body['range']['to'], 0, 10));
    }

    public function test_a_custom_range_within_the_max_is_not_flagged_as_truncated(): void
    {
        $vendor = $this->vendor();

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period=custom&from=2026-01-01&to=2026-03-01",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertFalse($body['range']['truncated']);
    }

    // -------------------------------------------------------------- inventory

    public function test_inventory_with_no_items_reports_unavailable(): void
    {
        $vendor = $this->vendor();

        $inventory = $this->analytics($vendor)['inventory'];

        $this->assertFalse($inventory['available']);
    }

    public function test_inventory_flags_at_risk_menu_items_from_low_stock_ingredients(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $salmon = $vendor->inventoryItems()->create([
            'name' => 'Salmon', 'unit' => 'kg', 'quantity' => 0, 'min_stock' => 2, 'track_stock' => true,
        ]);
        $flour = $vendor->inventoryItems()->create([
            'name' => 'Flour', 'unit' => 'kg', 'quantity' => 50, 'min_stock' => 5, 'track_stock' => true,
        ]);

        $dish = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Salmon plate',
            'price' => 20, 'vat_rate' => 0, 'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        MenuItemIngredient::create([
            'menu_item_id' => $dish->id, 'inventory_item_id' => $salmon->id, 'quantity' => 0.3, 'unit' => 'kg',
        ]);
        MenuItemIngredient::create([
            'menu_item_id' => $dish->id, 'inventory_item_id' => $flour->id, 'quantity' => 0.1, 'unit' => 'kg',
        ]);

        $order = $this->order($vendor, $customer, $session, ['amount' => 20, 'payment_received' => true]);
        CartItem::create([
            'table_scan_session_id' => $session->id, 'menu_item_id' => $dish->id, 'order_id' => $order->id, 'quantity' => 1,
        ]);

        // The real deduction the backend records for this sale — read
        // straight from the ledger, not re-derived from the recipe here.
        $this->movement($vendor, $salmon, 'order', -0.3, $order->id);

        $inventory = $this->analytics($vendor)['inventory'];

        $this->assertTrue($inventory['available']);
        $this->assertEquals(1, $inventory['criticalCount']);
        $this->assertEquals(0, $inventory['lowStockCount']);
        $this->assertCount(1, $inventory['atRiskMenuItems']);
        $this->assertEquals('Salmon plate', $inventory['atRiskMenuItems'][0]['name']);
        // Gross of 10% VAT on 20.00 net.
        $this->assertEquals(22.0, $inventory['atRiskMenuItems'][0]['periodRevenue']);
        $this->assertEquals(22.0, $inventory['revenueAtRisk']);

        // Real usage, read from the stock-movement ledger.
        $salmonUsage = collect($inventory['usage'])->firstWhere('name', 'Salmon');
        $this->assertEquals(0.3, $salmonUsage['usage']);
    }

    public function test_inventory_forecasts_stockouts_from_real_consumption_pace(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        // Well above min_stock, so the threshold-based lists would say nothing
        // is wrong — the forecast is a genuinely separate signal.
        $basil = $vendor->inventoryItems()->create([
            'name' => 'Basil', 'unit' => 'kg', 'quantity' => 0.5, 'min_stock' => 0, 'track_stock' => true,
        ]);

        $dish = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Caprese',
            'price' => 12, 'vat_rate' => 0, 'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        MenuItemIngredient::create([
            'menu_item_id' => $dish->id, 'inventory_item_id' => $basil->id, 'quantity' => 0.1, 'unit' => 'kg',
        ]);

        // 3 sold this (7-day) window, each deducting 0.1kg -> 0.3kg used over
        // the window -> ~0.043kg/day -> 0.5kg on hand runs out well under 14 days.
        foreach (range(1, 3) as $n) {
            $order = $this->order($vendor, $customer, $session, ['amount' => 12, 'payment_received' => true]);
            CartItem::create([
                'table_scan_session_id' => $session->id, 'menu_item_id' => $dish->id, 'order_id' => $order->id, 'quantity' => 1,
            ]);
            $this->movement($vendor, $basil, 'order', -0.1, $order->id);
        }

        $inventory = $this->analytics($vendor)['inventory'];

        $this->assertEquals(0, $inventory['criticalCount']);
        $this->assertEquals(0, $inventory['lowStockCount']);
        $this->assertCount(1, $inventory['soonestStockouts']);
        $this->assertEquals('Basil', $inventory['soonestStockouts'][0]['name']);
        $this->assertLessThanOrEqual(14, $inventory['soonestStockouts'][0]['daysRemaining']);
        $this->assertEquals('Caprese', $inventory['soonestStockouts'][0]['affectedMenuItems'][0]['name']);
    }

    public function test_inventory_flags_slow_moving_stock_with_no_sales_this_period(): void
    {
        $vendor = $this->vendor();

        $vendor->inventoryItems()->create([
            'name' => 'Truffle oil', 'unit' => 'L', 'quantity' => 4, 'min_stock' => 0,
            'cost_per_unit' => 45, 'track_stock' => true,
        ]);

        // The ledger needs at least one real movement *somewhere* this period —
        // with zero movements recorded at all, "slow mover" is indistinguishable
        // from "auto-deduction is off for this vendor", so the service stays
        // silent by design (see inventory()'s $slowMovers doc comment). This
        // item moves briskly (8 of 10 units consumed over the "daily" period's
        // rolling 7-day window -> a runout estimate well under the 45-day
        // slow-mover threshold regardless of the exact day count), so it must
        // NOT itself appear as a slow mover — only Truffle oil, which never
        // moved, should.
        $flour = $vendor->inventoryItems()->create([
            'name' => 'Flour', 'unit' => 'kg', 'quantity' => 10, 'min_stock' => 0,
            'cost_per_unit' => 2, 'track_stock' => true,
        ]);
        $this->movement($vendor, $flour, 'order', -8);

        $inventory = $this->analytics($vendor)['inventory'];

        $this->assertCount(1, $inventory['slowMovers']);
        $this->assertEquals('Truffle oil', $inventory['slowMovers'][0]['name']);
        $this->assertEquals(180.0, $inventory['slowMovers'][0]['value']);
        $this->assertEquals(180.0, $inventory['tiedUpCapital']);
    }

    public function test_inventory_reports_waste_from_the_stock_movement_ledger(): void
    {
        $vendor = $this->vendor();

        $milk = $vendor->inventoryItems()->create([
            'name' => 'Milk', 'unit' => 'L', 'quantity' => 10, 'min_stock' => 1,
            'cost_per_unit' => 2, 'track_stock' => true,
        ]);

        // A waste adjustment logged through the Inventory page's Adjust Stock modal.
        $this->movement($vendor, $milk, 'waste', -3);

        $inventory = $this->analytics($vendor)['inventory'];

        $this->assertEquals(6.0, $inventory['waste']['totalValue']);
        $this->assertCount(1, $inventory['waste']['items']);
        $this->assertEquals('Milk', $inventory['waste']['items'][0]['name']);
        $this->assertEquals(3.0, $inventory['waste']['items'][0]['quantity']);
        $this->assertEquals(6.0, $inventory['waste']['items'][0]['value']);
    }

    public function test_inventory_flags_purchase_orders_needing_attention(): void
    {
        $vendor = $this->vendor();

        $flour = $vendor->inventoryItems()->create([
            'name' => 'Flour', 'unit' => 'kg', 'quantity' => 10, 'min_stock' => 1, 'track_stock' => true,
        ]);

        InventoryPurchaseOrder::create([
            'vendor_id' => $vendor->id,
            'inventory_item_id' => $flour->id,
            'supplier_id' => 'sup-1',
            'supplier_name' => 'Acme Foods',
            'ordering_method' => 'API',
            'quantity' => 20,
            'unit' => 'kg',
            'unit_cost' => 1.5,
            'currency' => 'EUR',
            'status' => 'failed',
            'dispatch_error' => 'Supplier API timed out',
        ]);

        InventoryPurchaseOrder::create([
            'vendor_id' => $vendor->id,
            'inventory_item_id' => $flour->id,
            'supplier_id' => 'sup-1',
            'supplier_name' => 'Acme Foods',
            'ordering_method' => 'API',
            'quantity' => 10,
            'unit' => 'kg',
            'unit_cost' => 1.5,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $inventory = $this->analytics($vendor)['inventory'];

        $this->assertEquals(1, $inventory['purchaseOrders']['needsAttentionCount']);
        $this->assertEquals('Acme Foods', $inventory['purchaseOrders']['needsAttention'][0]['supplierName']);
        $this->assertEquals('Supplier API timed out', $inventory['purchaseOrders']['needsAttention'][0]['dispatchError']);
        $this->assertEquals(1, $inventory['purchaseOrders']['pendingCount']);
        $this->assertEquals(15.0, $inventory['purchaseOrders']['pendingValue']);
    }

    public function test_inventory_reports_auto_deduction_enabled_from_settings(): void
    {
        $vendor = $this->vendor();
        $vendor->inventoryItems()->create([
            'name' => 'Flour', 'unit' => 'kg', 'quantity' => 10, 'min_stock' => 1, 'track_stock' => true,
        ]);

        $enabled = $this->analytics($vendor)['inventory']['autoDeductionEnabled'];
        $this->assertTrue($enabled);

        $vendor->inventorySettings()->create([
            'settings' => ['general' => ['enableAutoStockDeduction' => false]],
        ]);

        $disabled = $this->analytics($vendor)['inventory']['autoDeductionEnabled'];
        $this->assertFalse($disabled);
    }

    public function test_inventory_valuation_totals_only_tracked_stock_at_cost(): void
    {
        $vendor = $this->vendor();

        $vendor->inventoryItems()->create([
            'name' => 'Flour', 'unit' => 'kg', 'quantity' => 10, 'min_stock' => 1,
            'cost_per_unit' => 2, 'category' => 'Dry goods', 'track_stock' => true,
        ]);
        // Not tracked — must not count toward the valuation total.
        $vendor->inventoryItems()->create([
            'name' => 'Napkins', 'unit' => 'pack', 'quantity' => 100, 'min_stock' => 0,
            'cost_per_unit' => 5, 'track_stock' => false,
        ]);

        $inventory = $this->analytics($vendor)['inventory'];

        $this->assertEquals(20.0, $inventory['valuation']['totalValue']);
        $this->assertEquals(20.0, $inventory['valuation']['breakdown']['onHandValue']);
        $flour = collect($inventory['valuation']['stockLevels'])->firstWhere('name', 'Flour');
        $this->assertEquals(20.0, $flour['value']);
        $this->assertEquals('Dry goods', $flour['category']);
    }

    public function test_inventory_price_changes_read_from_purchase_order_history(): void
    {
        $vendor = $this->vendor();

        $salmon = $vendor->inventoryItems()->create([
            'name' => 'Salmon', 'unit' => 'kg', 'quantity' => 10, 'min_stock' => 1, 'track_stock' => true,
        ]);

        $this->purchaseOrderAt($vendor, $salmon, 20.0, now()->subWeeks(2));
        $this->purchaseOrderAt($vendor, $salmon, 23.0, now()->subWeek());

        $priceChanges = $this->analytics($vendor)['inventory']['priceChanges'];

        $this->assertCount(1, $priceChanges);
        $this->assertEquals('Salmon', $priceChanges[0]['name']);
        $this->assertEquals(20.0, $priceChanges[0]['previousCost']);
        $this->assertEquals(23.0, $priceChanges[0]['latestCost']);
        $this->assertEquals(15.0, $priceChanges[0]['changePercent']);
    }

    public function test_inventory_expiry_risk_is_a_flagged_placeholder_not_a_measurement(): void
    {
        $vendor = $this->vendor();

        $milk = $vendor->inventoryItems()->create([
            'name' => 'Milk', 'unit' => 'L', 'quantity' => 50, 'min_stock' => 1,
            'cost_per_unit' => 1, 'category' => 'Dairy', 'track_stock' => true,
        ]);

        // Delivered 10 days ago with no usage recorded since — dairy's
        // placeholder shelf life (7 days) has already passed.
        $this->movementAt($vendor, $milk, 'delivery', 50, now()->subDays(10));

        $inventory = $this->analytics($vendor)['inventory'];

        $this->assertTrue($inventory['expiryRisk']['isPlaceholder']);
        $this->assertNotEmpty($inventory['expiryRisk']['assumptionNote']);
        $this->assertEquals(50.0, $inventory['expiryRisk']['totalValue']);
        $this->assertEquals('Milk', $inventory['expiryRisk']['items'][0]['name']);
        $this->assertTrue($inventory['valuation']['breakdown']['expiryRiskIsPlaceholder']);
    }

    public function test_inventory_expiry_risk_stays_silent_without_a_recorded_delivery(): void
    {
        $vendor = $this->vendor();

        $vendor->inventoryItems()->create([
            'name' => 'Milk', 'unit' => 'L', 'quantity' => 50, 'min_stock' => 1,
            'cost_per_unit' => 1, 'category' => 'Dairy', 'track_stock' => true,
        ]);

        $inventory = $this->analytics($vendor)['inventory'];

        $this->assertEquals(0.0, $inventory['expiryRisk']['totalValue']);
        $this->assertEmpty($inventory['expiryRisk']['items']);
    }

    // ---------------------------------------------------------------- loyalty

    public function test_loyalty_reports_disabled_when_the_setting_is_off(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => false]);

        $loyalty = $this->analytics($vendor)['loyalty'];

        $this->assertFalse($loyalty['enabled']);
        $this->assertFalse($loyalty['available']);
    }

    public function test_loyalty_reports_enabled_but_unavailable_without_any_activity(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => true]);

        $loyalty = $this->analytics($vendor)['loyalty'];

        // Honest state: the toggle is on but nothing has ever earned a point.
        $this->assertTrue($loyalty['enabled']);
        $this->assertFalse($loyalty['available']);
    }

    public function test_loyalty_computes_liability_and_redemption_rate(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create([
            'loyalty_enabled' => true, 'point_value' => 0.01, 'minimum_redemption_points' => 100,
        ]);

        $a = Customer::factory()->create();
        $b = Customer::factory()->create();
        $c = Customer::factory()->create();

        // Two members eligible to redeem (balance >= 100), one of whom has.
        CustomerLoyaltyPoint::create([
            'customer_id' => $a->id, 'vendor_id' => $vendor->id,
            'points_balance' => 150, 'total_earned' => 150, 'total_redeemed' => 0,
        ]);
        CustomerLoyaltyPoint::create([
            'customer_id' => $b->id, 'vendor_id' => $vendor->id,
            'points_balance' => 120, 'total_earned' => 220, 'total_redeemed' => 100,
        ]);
        // Below the redemption threshold.
        CustomerLoyaltyPoint::create([
            'customer_id' => $c->id, 'vendor_id' => $vendor->id,
            'points_balance' => 40, 'total_earned' => 40, 'total_redeemed' => 0,
        ]);

        LoyaltyTransaction::create([
            'customer_id' => $b->id, 'vendor_id' => $vendor->id,
            'type' => 'redeemed', 'points' => -100, 'reference_type' => 'redemption',
        ]);

        $loyalty = $this->analytics($vendor)['loyalty'];

        $this->assertTrue($loyalty['available']);
        $this->assertEquals(3, $loyalty['members']);
        $this->assertEquals(3, $loyalty['newMembersThisPeriod']);
        $this->assertEquals(2, $loyalty['eligibleToRedeem']);
        // 1 of 2 eligible members has ever redeemed.
        $this->assertEquals(50.0, $loyalty['redemptionRate']);
        // (150 + 120 + 40) points x 0.01.
        $this->assertEquals(3.10, $loyalty['outstandingLiability']);
    }

    public function test_loyalty_reports_member_lift_with_uplift_and_revenue_impact(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => true]);
        [, $session] = $this->context($vendor);

        $member = Customer::factory()->create();
        CustomerLoyaltyPoint::create([
            'customer_id' => $member->id, 'vendor_id' => $vendor->id,
            'points_balance' => 50, 'total_earned' => 50, 'total_redeemed' => 0,
        ]);

        // 10 member orders at 40.00, 10 non-member orders at 20.00 — a clean
        // 100% uplift that is easy to check the arithmetic on.
        foreach (range(1, 10) as $n) {
            $this->order($vendor, $member, $session, ['amount' => 40, 'payment_received' => true]);
        }
        foreach (range(1, 10) as $n) {
            $other = Customer::factory()->create();
            $this->order($vendor, $other, $session, ['amount' => 20, 'payment_received' => true]);
        }

        $lift = $this->analytics($vendor)['loyalty']['memberLift'];

        $this->assertNotNull($lift);
        $this->assertEquals(40.0, $lift['memberAvgOrderValue']);
        $this->assertEquals(20.0, $lift['nonMemberAvgOrderValue']);
        $this->assertEquals(100.0, $lift['upliftPercent']);
        // (40 - 20) x 10 member orders.
        $this->assertEquals(200.0, $lift['revenueImpact']);
    }

    public function test_loyalty_member_lift_flags_smaller_baskets(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => true]);
        [, $session] = $this->context($vendor);
        $category = $this->category($vendor);

        $item = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Side',
            'price' => 5, 'vat_rate' => 0, 'available' => true, 'is_active' => true, 'ordered_count' => 0,
        ]);

        $member = Customer::factory()->create();
        CustomerLoyaltyPoint::create([
            'customer_id' => $member->id, 'vendor_id' => $vendor->id,
            'points_balance' => 10, 'total_earned' => 10, 'total_redeemed' => 0,
        ]);

        // 10 member orders with 1 item each.
        foreach (range(1, 10) as $n) {
            $order = $this->order($vendor, $member, $session, ['amount' => 20, 'payment_received' => true]);
            CartItem::create([
                'table_scan_session_id' => $session->id, 'menu_item_id' => $item->id, 'order_id' => $order->id, 'quantity' => 1,
            ]);
        }

        // 10 non-member orders with 2 items each — same spend, bigger basket.
        foreach (range(1, 10) as $n) {
            $other = Customer::factory()->create();
            $order = $this->order($vendor, $other, $session, ['amount' => 20, 'payment_received' => true]);
            CartItem::create([
                'table_scan_session_id' => $session->id, 'menu_item_id' => $item->id, 'order_id' => $order->id, 'quantity' => 2,
            ]);
        }

        $lift = $this->analytics($vendor)['loyalty']['memberLift'];

        $this->assertEquals(1.0, $lift['memberAvgItems']);
        $this->assertEquals(2.0, $lift['nonMemberAvgItems']);
        $this->assertEquals('smaller', $lift['basketDepthVerdict']);
    }

    public function test_loyalty_flags_eligible_members_who_have_gone_quiet(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => true, 'minimum_redemption_points' => 100]);
        [, $session] = $this->context($vendor);

        $active = Customer::factory()->create();
        $neverOrdered = Customer::factory()->create();
        $wentQuiet = Customer::factory()->create();

        foreach ([$active, $neverOrdered, $wentQuiet] as $customer) {
            CustomerLoyaltyPoint::create([
                'customer_id' => $customer->id, 'vendor_id' => $vendor->id,
                'points_balance' => 150, 'total_earned' => 150, 'total_redeemed' => 0,
            ]);
        }

        // Active: ordered two days ago, well inside the inactivity window.
        $this->order($vendor, $active, $session, [
            'payment_received' => true, 'created_at' => now()->subDays(2),
        ]);

        // Went quiet: last order well past the 30-day cutoff.
        $this->order($vendor, $wentQuiet, $session, [
            'payment_received' => true, 'created_at' => now()->subDays(45),
        ]);

        // $neverOrdered has no orders on record at all — also counts as inactive.

        $loyalty = $this->analytics($vendor)['loyalty'];

        $this->assertEquals(3, $loyalty['eligibleToRedeem']);
        $this->assertEquals(2, $loyalty['eligibleInactiveCount']);
    }

    // --------------------------------------------------------------- insights

    public function test_inventory_at_risk_insight_fires_when_impact_is_meaningful(): void
    {
        $payload = $this->basePayload();
        $payload['inventory'] = [
            'available' => true,
            'revenueAtRisk' => 120.0,
            'atRiskMenuItems' => [
                ['name' => 'Salmon plate', 'periodRevenue' => 120.0, 'periodQuantity' => 8],
            ],
        ];

        $insights = (new InsightEngine)->derive($payload);

        $this->assertNotNull(collect($insights)->firstWhere('id', 'inventory-at-risk'));
    }

    public function test_slow_moving_stock_insight_fires_when_meaningful(): void
    {
        $payload = $this->basePayload();
        $payload['inventory'] = [
            'available' => true,
            'tiedUpCapital' => 180.0,
            'slowMovers' => [
                ['name' => 'Truffle oil', 'value' => 180.0, 'quantity' => 4, 'unit' => 'L'],
            ],
        ];

        $insights = (new InsightEngine)->derive($payload);

        $this->assertNotNull(collect($insights)->firstWhere('id', 'slow-moving-stock'));
    }

    public function test_food_waste_insight_fires_when_meaningful(): void
    {
        $payload = $this->basePayload();
        $payload['inventory'] = [
            'available' => true,
            'waste' => [
                'totalValue' => 60.0,
                'items' => [
                    ['name' => 'Milk', 'unit' => 'L', 'quantity' => 30, 'value' => 60.0],
                ],
            ],
        ];

        $insights = (new InsightEngine)->derive($payload);

        $this->assertNotNull(collect($insights)->firstWhere('id', 'food-waste'));
    }

    public function test_food_waste_insight_stays_silent_below_threshold(): void
    {
        $payload = $this->basePayload();
        $payload['inventory'] = [
            'available' => true,
            'waste' => [
                'totalValue' => 5.0,
                'items' => [
                    ['name' => 'Milk', 'unit' => 'L', 'quantity' => 2.5, 'value' => 5.0],
                ],
            ],
        ];

        $insights = (new InsightEngine)->derive($payload);

        $this->assertNull(collect($insights)->firstWhere('id', 'food-waste'));
    }

    public function test_purchase_order_needs_attention_insight_fires(): void
    {
        $payload = $this->basePayload();
        $payload['inventory'] = [
            'available' => true,
            'purchaseOrders' => [
                'pendingCount' => 0,
                'pendingValue' => 0.0,
                'needsAttentionCount' => 1,
                'needsAttention' => [
                    [
                        'purchaseOrderPublicId' => 'PO-ABC123',
                        'supplierName' => 'Acme Foods',
                        'status' => 'failed',
                        'dispatchError' => 'Supplier API timed out',
                        'quantity' => 20,
                        'unit' => 'kg',
                    ],
                ],
            ],
        ];

        $insight = collect((new InsightEngine)->derive($payload))->firstWhere('id', 'purchase-order-needs-attention');

        $this->assertNotNull($insight);
        $this->assertStringContainsString('Acme Foods', $insight['description']);
    }

    public function test_price_increase_insight_fires_on_a_notable_jump(): void
    {
        $payload = $this->basePayload();
        $payload['inventory'] = [
            'available' => true,
            'priceChanges' => [
                [
                    'name' => 'Salmon', 'unit' => 'kg', 'previousCost' => 20.0, 'latestCost' => 23.0,
                    'changePercent' => 15.0, 'previousDate' => now()->subWeek()->toISOString(),
                    'latestDate' => now()->toISOString(),
                ],
            ],
        ];

        $insight = collect((new InsightEngine)->derive($payload))->firstWhere('id', 'price-increase');

        $this->assertNotNull($insight);
        $this->assertStringContainsString('Salmon', $insight['title']);
        $this->assertStringContainsString('15', $insight['title']);
    }

    public function test_price_increase_insight_stays_silent_below_threshold(): void
    {
        $payload = $this->basePayload();
        $payload['inventory'] = [
            'available' => true,
            'priceChanges' => [
                [
                    'name' => 'Salmon', 'unit' => 'kg', 'previousCost' => 20.0, 'latestCost' => 20.8,
                    'changePercent' => 4.0, 'previousDate' => now()->subWeek()->toISOString(),
                    'latestDate' => now()->toISOString(),
                ],
            ],
        ];

        $insights = (new InsightEngine)->derive($payload);

        $this->assertNull(collect($insights)->firstWhere('id', 'price-increase'));
    }

    public function test_expiry_risk_promotion_insight_names_suggested_menu_items(): void
    {
        $payload = $this->basePayload();
        $payload['inventory'] = [
            'available' => true,
            'expiryRisk' => [
                'isPlaceholder' => true,
                'assumptionNote' => 'Estimated from an assumed shelf life...',
                'totalValue' => 50.0,
                'items' => [
                    [
                        'name' => 'Milk', 'unit' => 'L', 'quantity' => 50.0, 'value' => 50.0,
                        'assumedShelfLifeDays' => 7, 'daysUntilAssumedExpiry' => 0,
                        'suggestedMenuItems' => ['Latte', 'Cappuccino'],
                    ],
                ],
            ],
        ];

        $insight = collect((new InsightEngine)->derive($payload))->firstWhere('id', 'expiry-risk-promotion');

        $this->assertNotNull($insight);
        $this->assertStringContainsString('Latte', $insight['description']);
        $this->assertStringContainsString('ESTIMATE', $insight['whatHappening']);
        // The recommendation is a concrete price cut, not just "feature it".
        $this->assertStringContainsString('%', $insight['suggestedAction']);
        $this->assertStringContainsString('Latte', $insight['suggestedAction']);
    }

    public function test_expiry_risk_promotion_insight_suggests_a_deeper_cut_the_more_imminent_the_risk(): void
    {
        $urgent = $this->basePayload();
        $urgent['inventory'] = [
            'available' => true,
            'expiryRisk' => [
                'isPlaceholder' => true,
                'assumptionNote' => 'Estimated from an assumed shelf life...',
                'totalValue' => 50.0,
                'items' => [
                    ['name' => 'Milk', 'unit' => 'L', 'quantity' => 50.0, 'value' => 50.0,
                     'assumedShelfLifeDays' => 7, 'daysUntilAssumedExpiry' => 1, 'suggestedMenuItems' => ['Latte']],
                ],
            ],
        ];

        $relaxed = $this->basePayload();
        $relaxed['inventory'] = [
            'available' => true,
            'expiryRisk' => [
                'isPlaceholder' => true,
                'assumptionNote' => 'Estimated from an assumed shelf life...',
                'totalValue' => 50.0,
                'items' => [
                    ['name' => 'Milk', 'unit' => 'L', 'quantity' => 50.0, 'value' => 50.0,
                     'assumedShelfLifeDays' => 7, 'daysUntilAssumedExpiry' => 10, 'suggestedMenuItems' => ['Latte']],
                ],
            ],
        ];

        $urgentInsight = collect((new InsightEngine)->derive($urgent))->firstWhere('id', 'expiry-risk-promotion');
        $relaxedInsight = collect((new InsightEngine)->derive($relaxed))->firstWhere('id', 'expiry-risk-promotion');

        $this->assertStringContainsString('30%', $urgentInsight['suggestedAction']);
        $this->assertStringContainsString('10%', $relaxedInsight['suggestedAction']);
    }

    public function test_expiry_risk_promotion_insight_stays_silent_below_threshold(): void
    {
        $payload = $this->basePayload();
        $payload['inventory'] = [
            'available' => true,
            'expiryRisk' => [
                'isPlaceholder' => true,
                'assumptionNote' => 'Estimated from an assumed shelf life...',
                'totalValue' => 5.0,
                'items' => [
                    ['name' => 'Milk', 'unit' => 'L', 'quantity' => 5.0, 'value' => 5.0,
                     'assumedShelfLifeDays' => 7, 'daysUntilAssumedExpiry' => 0, 'suggestedMenuItems' => []],
                ],
            ],
        ];

        $insights = (new InsightEngine)->derive($payload);

        $this->assertNull(collect($insights)->firstWhere('id', 'expiry-risk-promotion'));
    }

    public function test_loyalty_stalled_insight_fires_on_low_redemption(): void
    {
        $payload = $this->basePayload();
        $payload['loyalty'] = [
            'available' => true,
            'eligibleToRedeem' => 12,
            'redemptionRate' => 8.3,
            'outstandingLiability' => 45.0,
            'eligibleInactiveCount' => 0,
        ];

        $insights = (new InsightEngine)->derive($payload);

        $this->assertNotNull(collect($insights)->firstWhere('id', 'loyalty-redemption-stalled'));
    }

    public function test_loyalty_stalled_insight_names_the_inactive_members(): void
    {
        $payload = $this->basePayload();
        $payload['loyalty'] = [
            'available' => true,
            'eligibleToRedeem' => 12,
            'redemptionRate' => 8.3,
            'outstandingLiability' => 45.0,
            'eligibleInactiveCount' => 5,
        ];

        $insight = collect((new InsightEngine)->derive($payload))->firstWhere('id', 'loyalty-redemption-stalled');

        $this->assertNotNull($insight);
        $this->assertStringContainsString('5', $insight['description']);
        $this->assertStringContainsString("haven't ordered", $insight['description']);
    }

    // -------------------------------------------------------- visit frequency

    public function test_loyalty_visits_compares_members_against_identified_non_members(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => true]);
        [, $session] = $this->context($vendor);

        // 10 members, each visiting on 2 distinct days this period.
        for ($i = 0; $i < 10; $i++) {
            $member = Customer::factory()->create();
            $this->walletJoinedAt($vendor, $member, now()->subDays(90));
            $this->order($vendor, $member, $session, ['payment_received' => true, 'created_at' => now()->subDay()]);
            $this->order($vendor, $member, $session, ['payment_received' => true, 'created_at' => now()->subHours(2)]);
        }

        // 10 identified non-members, each visiting on 1 day this period.
        for ($i = 0; $i < 10; $i++) {
            $nonMember = Customer::factory()->create();
            $this->order($vendor, $nonMember, $session, ['payment_received' => true, 'created_at' => now()->subHour()]);
        }

        // A guest with no account at all — must not count on either side.
        $this->order($vendor, null, $session, ['payment_received' => true, 'created_at' => now()->subHour()]);

        $thisPeriod = $this->analytics($vendor)['loyalty']['visitFrequency']['thisPeriod'];

        $this->assertNotNull($thisPeriod);
        $this->assertEquals(10, $thisPeriod['memberCustomers']);
        $this->assertEquals(2.0, $thisPeriod['memberAvgVisits']);
        $this->assertEquals(10, $thisPeriod['nonMemberCustomers']);
        $this->assertEquals(1.0, $thisPeriod['nonMemberAvgVisits']);
    }

    public function test_loyalty_visits_before_after_joining_excludes_members_with_no_prior_history(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => true]);
        [, $session] = $this->context($vendor);

        // Joined loyalty on their very first order — no "before" to compare.
        $newMember = Customer::factory()->create();
        $this->walletJoinedAt($vendor, $newMember, now()->subDays(30));
        $this->order($vendor, $newMember, $session, ['payment_received' => true, 'created_at' => now()->subDays(30)]);
        $this->order($vendor, $newMember, $session, ['payment_received' => true, 'created_at' => now()->subDays(2)]);

        $beforeAfter = $this->analytics($vendor)['loyalty']['visitFrequency']['beforeAfterJoining'];

        $this->assertFalse($beforeAfter['available']);
        $this->assertEquals(0, $beforeAfter['membersAnalyzed']);
    }

    public function test_loyalty_visits_before_after_joining_computes_the_rate_change(): void
    {
        $vendor = $this->vendor();
        $vendor->vendorSetting()->create(['loyalty_enabled' => true]);
        [, $session] = $this->context($vendor);

        // 10 members: first order 56 days ago, joined 28 days ago, today is
        // the observation point. 4 distinct visit-days in the 4 weeks before
        // joining (1.0/week), 8 distinct visit-days in the 4 weeks after
        // (2.0/week) — a clean, checkable doubling.
        for ($i = 0; $i < 10; $i++) {
            $member = Customer::factory()->create();
            $this->walletJoinedAt($vendor, $member, now()->subDays(28));

            foreach ([56, 49, 42, 35] as $daysAgo) {
                $this->order($vendor, $member, $session, [
                    'payment_received' => true, 'created_at' => now()->subDays($daysAgo),
                ]);
            }
            foreach ([27, 24, 21, 18, 15, 12, 9, 6] as $daysAgo) {
                $this->order($vendor, $member, $session, [
                    'payment_received' => true, 'created_at' => now()->subDays($daysAgo),
                ]);
            }
        }

        $beforeAfter = $this->analytics($vendor)['loyalty']['visitFrequency']['beforeAfterJoining'];

        $this->assertTrue($beforeAfter['available']);
        $this->assertEquals(10, $beforeAfter['membersAnalyzed']);
        $this->assertEquals(1.0, $beforeAfter['avgVisitsPerWeekBefore']);
        $this->assertEquals(2.0, $beforeAfter['avgVisitsPerWeekAfter']);
        $this->assertEquals(10, $beforeAfter['increasedCount']);
        $this->assertEquals(0, $beforeAfter['decreasedCount']);
    }

    public function test_loyalty_visit_impact_insight_fires_on_a_clear_increase(): void
    {
        $payload = $this->basePayload();
        $payload['loyalty'] = [
            'available' => true,
            'visitFrequency' => [
                'beforeAfterJoining' => [
                    'available' => true,
                    'membersAnalyzed' => 20,
                    'avgVisitsPerWeekBefore' => 1.0,
                    'avgVisitsPerWeekAfter' => 1.6,
                    'increasedCount' => 15,
                    'decreasedCount' => 2,
                    'unchangedCount' => 3,
                ],
            ],
        ];

        $insight = collect((new InsightEngine)->derive($payload))->firstWhere('id', 'loyalty-visit-impact');

        $this->assertNotNull($insight);
        $this->assertStringContainsString('more often', $insight['title']);
        $this->assertEquals(60.0, $insight['impact']['value']);
    }

    public function test_loyalty_visit_impact_insight_fires_on_a_clear_decrease(): void
    {
        $payload = $this->basePayload();
        $payload['loyalty'] = [
            'available' => true,
            'visitFrequency' => [
                'beforeAfterJoining' => [
                    'available' => true,
                    'membersAnalyzed' => 20,
                    'avgVisitsPerWeekBefore' => 2.0,
                    'avgVisitsPerWeekAfter' => 1.2,
                    'increasedCount' => 2,
                    'decreasedCount' => 15,
                    'unchangedCount' => 3,
                ],
            ],
        ];

        $insight = collect((new InsightEngine)->derive($payload))->firstWhere('id', 'loyalty-visit-impact');

        $this->assertNotNull($insight);
        $this->assertStringContainsString('less often', $insight['title']);
        $this->assertEquals(-40.0, $insight['impact']['value']);
    }

    public function test_loyalty_visit_impact_insight_stays_silent_below_the_noise_floor(): void
    {
        $payload = $this->basePayload();
        $payload['loyalty'] = [
            'available' => true,
            'visitFrequency' => [
                'beforeAfterJoining' => [
                    'available' => true,
                    'membersAnalyzed' => 20,
                    'avgVisitsPerWeekBefore' => 2.0,
                    'avgVisitsPerWeekAfter' => 2.05,
                    'increasedCount' => 8,
                    'decreasedCount' => 7,
                    'unchangedCount' => 5,
                ],
            ],
        ];

        $insights = (new InsightEngine)->derive($payload);

        $this->assertNull(collect($insights)->firstWhere('id', 'loyalty-visit-impact'));
    }

    public function test_reservations_reports_totals_by_status_and_rates(): void
    {
        $vendor = $this->vendor();
        $today = now()->subDays(2);

        Reservation::create(['reservation_public_id' => 'r1', 'vendor_id' => $vendor->id, 'date' => $today->toDateString(), 'time' => '19:00', 'party_size' => 2, 'status' => 'completed']);
        Reservation::create(['reservation_public_id' => 'r2', 'vendor_id' => $vendor->id, 'date' => $today->toDateString(), 'time' => '19:30', 'party_size' => 4, 'status' => 'completed']);
        Reservation::create(['reservation_public_id' => 'r3', 'vendor_id' => $vendor->id, 'date' => $today->toDateString(), 'time' => '20:00', 'party_size' => 2, 'status' => 'no_show']);
        Reservation::create(['reservation_public_id' => 'r4', 'vendor_id' => $vendor->id, 'date' => $today->toDateString(), 'time' => '20:30', 'party_size' => 6, 'status' => 'cancelled']);

        $body = $this->analytics($vendor, 'weekly');

        $this->assertTrue($body['reservations']['available']);
        $this->assertEquals(4, $body['reservations']['total']);
        $this->assertEquals([
            'pending' => 0, 'confirmed' => 0, 'completed' => 2, 'cancelled' => 1, 'noShow' => 1,
        ], $body['reservations']['byStatus']);
        // No-show rate excludes the cancellation from its denominator: of the
        // reservations that actually reached their date (2 completed + 1
        // no-show), one third never showed up — a cancellation is a guest
        // proactively freeing the table, not a failure to show.
        $this->assertEquals(33.3, $body['reservations']['noShowRate']);
        $this->assertEquals(25.0, $body['reservations']['cancellationRate']);
        $this->assertEquals(3.5, $body['reservations']['averagePartySize']);
    }

    public function test_reservations_no_show_rate_is_null_when_none_reached_their_date(): void
    {
        $vendor = $this->vendor();
        $today = now()->subDays(2);

        Reservation::create(['reservation_public_id' => 'r1', 'vendor_id' => $vendor->id, 'date' => $today->toDateString(), 'time' => '19:00', 'party_size' => 2, 'status' => 'cancelled']);
        Reservation::create(['reservation_public_id' => 'r2', 'vendor_id' => $vendor->id, 'date' => $today->toDateString(), 'time' => '19:30', 'party_size' => 2, 'status' => 'pending']);

        $body = $this->analytics($vendor, 'weekly');

        $this->assertNull($body['reservations']['noShowRate']);
        $this->assertEquals(50.0, $body['reservations']['cancellationRate']);
    }

    public function test_reservations_average_lead_time_reflects_days_between_booking_and_date(): void
    {
        $vendor = $this->vendor();
        $date = now()->subDays(2);

        // Booked 5 days before its date.
        $r1 = Reservation::create(['reservation_public_id' => 'r1', 'vendor_id' => $vendor->id, 'date' => $date->toDateString(), 'time' => '19:00', 'party_size' => 2, 'status' => 'completed']);
        Reservation::where('id', $r1->id)->update(['created_at' => $date->copy()->subDays(5)]);

        // Booked 1 day before its date.
        $r2 = Reservation::create(['reservation_public_id' => 'r2', 'vendor_id' => $vendor->id, 'date' => $date->toDateString(), 'time' => '19:30', 'party_size' => 2, 'status' => 'completed']);
        Reservation::where('id', $r2->id)->update(['created_at' => $date->copy()->subDays(1)]);

        $body = $this->analytics($vendor, 'weekly');

        $this->assertEquals(3.0, $body['reservations']['averageLeadTimeDays']);
    }

    public function test_reservations_are_scoped_by_dining_date_not_booking_date(): void
    {
        $vendor = $this->vendor();

        // Booked (created_at) this week, but for a dining date next month —
        // must NOT count in this week's reservations, since `date` (the
        // event itself) is what this section reports, not when it was booked.
        $futureReservation = Reservation::create([
            'reservation_public_id' => 'r1', 'vendor_id' => $vendor->id,
            'date' => now()->addMonth()->toDateString(), 'time' => '19:00', 'party_size' => 2, 'status' => 'confirmed',
        ]);
        Reservation::where('id', $futureReservation->id)->update(['created_at' => now()]);

        $body = $this->analytics($vendor, 'weekly');

        $this->assertFalse($body['reservations']['available']);
    }

    public function test_reservations_is_unavailable_when_none_fall_in_the_selected_period(): void
    {
        $vendor = $this->vendor();

        $body = $this->analytics($vendor, 'weekly');

        $this->assertEquals(['available' => false], $body['reservations']);
    }

    // ---------------------------------------------------------------- helpers

    /** Minimal payload shape that clears the InsightEngine's sample gate. */
    private function basePayload(): array
    {
        return [
            'hasData' => true,
            'currency' => 'EUR',
            'summary' => ['orders' => ['value' => 30]],
        ];
    }




    private function vendor(): Vendor
    {
        return $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
    }

    /** @return array{0: Customer, 1: TableScanSession} */
    private function context(Vendor $vendor): array
    {
        $customer = Customer::factory()->create();
        $table = $this->table($vendor, 1);

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

    private function table(Vendor $vendor, int $number): RestaurantTable
    {
        return $vendor->restaurantTables()->create([
            'number' => $number,
            'name' => "Table {$number}",
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
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

    /** A loyalty wallet with a controlled join date — ::create() ignores created_at, so it's forced after. */
    private function walletJoinedAt(Vendor $vendor, Customer $customer, $joinedAt): CustomerLoyaltyPoint
    {
        $wallet = CustomerLoyaltyPoint::create([
            'customer_id' => $customer->id, 'vendor_id' => $vendor->id,
            'points_balance' => 10, 'total_earned' => 10, 'total_redeemed' => 0,
        ]);

        CustomerLoyaltyPoint::where('id', $wallet->id)->update(['created_at' => $joinedAt]);

        return $wallet->fresh();
    }

    /**
     * Records a real stock-movement row the way InventoryConsumptionService
     * or InventoryController::adjustStock() would — this class reads that
     * ledger, it never derives it, so tests must write it directly.
     */
    private function movement($vendor, $inventoryItem, string $type, float $quantityChange, ?int $orderId = null): InventoryStockMovement
    {
        return InventoryStockMovement::create([
            'vendor_id' => $vendor->id,
            'inventory_item_id' => $inventoryItem->id,
            'order_id' => $orderId,
            'type' => $type,
            'source' => $type === 'order' ? 'Order' : ucfirst($type),
            'quantity_change' => $quantityChange,
            'quantity_before' => (float) $inventoryItem->quantity,
            'quantity_after' => (float) $inventoryItem->quantity + $quantityChange,
            'actor_name' => $type === 'order' ? 'Auto' : 'Test',
        ]);
    }

    /** A stock movement backdated to a specific timestamp, e.g. a delivery received last week. */
    private function movementAt($vendor, $inventoryItem, string $type, float $quantityChange, $createdAt): InventoryStockMovement
    {
        $movement = $this->movement($vendor, $inventoryItem, $type, $quantityChange);
        InventoryStockMovement::where('id', $movement->id)->update(['created_at' => $createdAt]);

        return $movement->fresh();
    }

    /** A purchase order backdated to a specific timestamp, for price-history tests. */
    private function purchaseOrderAt($vendor, $inventoryItem, float $unitCost, $createdAt): InventoryPurchaseOrder
    {
        $po = InventoryPurchaseOrder::create([
            'vendor_id' => $vendor->id,
            'inventory_item_id' => $inventoryItem->id,
            'supplier_id' => 'sup-1',
            'supplier_name' => 'Test Supplier',
            'ordering_method' => 'API',
            'quantity' => 10,
            'unit' => $inventoryItem->unit,
            'unit_cost' => $unitCost,
            'currency' => 'EUR',
            'status' => 'sent',
        ]);
        InventoryPurchaseOrder::where('id', $po->id)->update(['created_at' => $createdAt]);

        return $po->fresh();
    }

    private function order(
        Vendor $vendor,
        ?Customer $customer,
        TableScanSession $session,
        array $attributes = [],
    ): Order {
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
            'in_progress_at' => now()->subMinutes(28),
            'served_at' => now()->subMinutes(10),
            'payment_confirmed_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(30),
        ], $attributes));
    }

    private function payment(Vendor $vendor, Order $order, string $status): OrderPayment
    {
        return OrderPayment::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'stripe_account_id' => 'acct_test',
            'stripe_payment_intent_id' => 'pi_'.Str::random(20),
            'amount' => $order->amount,
            'currency' => 'EUR',
            'status' => $status,
            'payment_method' => 'card',
            'paid_at' => $status === 'succeeded' ? now()->subMinutes(5) : null,
            'failed_at' => $status === 'failed' ? now()->subMinutes(5) : null,
        ]);
    }

    private function analytics(Vendor $vendor, string $period = 'daily'): array
    {
        return $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period={$period}",
            $this->headers($vendor),
        )->assertOk()->json();
    }

    private function headers(Vendor $vendor): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
