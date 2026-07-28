<?php

namespace Tests\Feature\Dashboard;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_current_operational_contract_without_inflating_occupied_tables(): void
    {
        $vendor = Vendor::factory()->create(['country' => 'GB']);
        $customer = Customer::factory()->create();
        $tableOne = $this->createTable($vendor, 1);
        $tableTwo = $this->createTable($vendor, 2);
        $this->createTable($vendor, 3, false);

        $sessionOne = $this->createSession($vendor, $tableOne, $customer, '1001');
        $sessionTwo = $this->createSession($vendor, $tableOne, $customer, '1002');
        $sessionWithoutActiveOrders = $this->createSession($vendor, $tableTwo, $customer, '1003');

        $firstOrder = $this->createOrder($vendor, $customer, $sessionOne, [
            'status' => Order::STATUS_IN_PROGRESS,
            'amount' => 20,
            'tip_amount' => 2,
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $this->createOrder($vendor, $customer, $sessionOne, [
            'status' => Order::STATUS_CONFIRMED,
            'amount' => 30,
            'tip_amount' => 3,
            'payment_received' => true,
        ]);

        $this->createOrder($vendor, $customer, $sessionOne, [
            'status' => Order::STATUS_CONFIRMED,
        ]);
        $this->createOrder($vendor, $customer, $sessionTwo, [
            'status' => Order::STATUS_WAITER_CONFIRMED,
        ]);
        $this->createOrder($vendor, $customer, $sessionTwo, [
            'status' => Order::STATUS_IN_PROGRESS,
        ]);

        $this->createOrder($vendor, $customer, $sessionWithoutActiveOrders, [
            'status' => Order::STATUS_SERVED,
            'amount' => 10,
            'tip_amount' => 1.5,
            'payment_received' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->createOrder($vendor, $customer, $sessionOne, [
            'status' => Order::STATUS_SERVED,
            'amount' => 15,
            'payment_received' => false,
        ]);

        Review::create([
            'review_public_id' => 'rev_'.Str::random(12),
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'order_id' => $firstOrder->id,
            'table_scan_session_id' => $sessionOne->id,
            'rating' => 4,
        ]);
        $oldReview = Review::create([
            'review_public_id' => 'rev_'.Str::random(12),
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'rating' => 1,
        ]);
        $oldReview->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $response = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/dashboard",
            $this->vendorHeaders($vendor),
        );

        $response->assertOk()
            ->assertJsonPath('liveStatus.occupiedTables', 1)
            ->assertJsonPath('liveStatus.totalTables', 2)
            ->assertJsonPath('liveStatus.activeOrders', 5)
            ->assertJsonPath('liveStatus.tablesWaitingToPay', 1)
            ->assertJsonPath('liveStatus.ordersWaitingToPay', 4)
            ->assertJsonPath('liveStatus.kitchenLoad', 'medium')
            ->assertJsonPath('todayKPIs.ordersToday', 6)
            ->assertJsonPath('todayKPIs.ordersYesterday', 1)
            ->assertJsonPath('todayKPIs.revenueToday', 30)
            ->assertJsonPath('todayKPIs.revenueYesterday', 10)
            ->assertJsonPath('todayKPIs.tipsToday', 3)
            ->assertJsonPath('todayKPIs.tipsYesterday', 1.5)
            ->assertJsonPath('todayKPIs.paidOrdersToday', 1)
            ->assertJsonPath('todayKPIs.avgOrderValue', 30)
            ->assertJsonPath('todayKPIs.customerRating', 4)
            ->assertJsonPath('todayKPIs.currency', 'GBP')
            ->assertJsonPath('revenueAtRisk.total', 15)
            ->assertJsonPath('revenueAtRisk.currency', 'GBP')
            ->assertJsonPath('timezone', 'Europe/London')
            ->assertJsonMissingPath('liveStatus.openTables')
            ->assertJsonMissingPath('revenueAtRisk.unpaidTables')
            ->assertJsonPath('recentOrders.0.paymentReceived', false);

        $response->assertJsonFragment(['tableNumber' => 1]);
    }

    public function test_dashboard_uses_vendor_local_payment_day_and_excludes_drafts_and_cancelled_orders(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-19 23:30:00', 'UTC'));

        try {
            $vendor = Vendor::factory()->create(['country' => 'AT']);
            $customer = Customer::factory()->create();
            $table = $this->createTable($vendor, 1);
            $session = $this->createSession($vendor, $table, $customer, '2001');

            // Created on July 19 in Vienna, then paid just after Vienna's
            // midnight. Revenue belongs to July 20; the placed order belongs
            // to July 19.
            $this->createOrder($vendor, $customer, $session, [
                'status' => Order::STATUS_SERVED,
                'amount' => 50,
                'tip_amount' => 5,
                'payment_received' => true,
                'payment_confirmed_at' => Carbon::parse('2026-07-19 22:30:00', 'UTC'),
                'created_at' => Carbon::parse('2026-07-19 20:00:00', 'UTC'),
                'updated_at' => Carbon::parse('2026-07-19 22:30:00', 'UTC'),
            ]);
            $this->createOrder($vendor, $customer, $session, [
                'status' => Order::STATUS_DRAFT,
                'amount' => 999,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->createOrder($vendor, $customer, $session, [
                'status' => Order::STATUS_CANCELLED,
                'amount' => 999,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->getJson(
                "/api/vendor/{$vendor->vendor_public_id}/dashboard",
                $this->vendorHeaders($vendor),
            )
                ->assertOk()
                ->assertJsonPath('timezone', 'Europe/Vienna')
                ->assertJsonPath('todayKPIs.ordersToday', 0)
                ->assertJsonPath('todayKPIs.ordersYesterday', 1)
                ->assertJsonPath('todayKPIs.paidOrdersToday', 1)
                ->assertJsonPath('todayKPIs.revenueToday', 50)
                ->assertJsonPath('todayKPIs.tipsToday', 5)
                ->assertJsonPath('todayKPIs.avgOrderValue', 50)
                ->assertJsonCount(2, 'recentOrders');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_top_items_come_from_submitted_cart_items_across_menu_versions(): void
    {
        $vendor = Vendor::factory()->create();
        $customer = Customer::factory()->create();
        $table = $this->createTable($vendor, 1);
        $session = $this->createSession($vendor, $table, $customer, '3001');
        $category = MenuCategory::create([
            'vendor_id' => $vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $oldItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Old Pizza Name',
            'price' => 10,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);
        $productUid = $oldItem->product_uid;
        $oldItem->delete();
        $currentItem = $vendor->menuItems()->create([
            'product_uid' => $productUid,
            'menu_category_id' => $category->id,
            'name' => 'Current Pizza Name',
            'price' => 12,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ]);

        $firstOrder = $this->createOrder($vendor, $customer, $session);
        $secondOrder = $this->createOrder($vendor, $customer, $session);
        $cancelledOrder = $this->createOrder($vendor, $customer, $session, ['status' => Order::STATUS_CANCELLED]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $oldItem->id,
            'order_id' => $firstOrder->id,
            'quantity' => 2,
        ]);
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $currentItem->id,
            'order_id' => $secondOrder->id,
            'quantity' => 1,
        ]);
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $currentItem->id,
            'order_id' => $cancelledOrder->id,
            'quantity' => 99,
        ]);

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/dashboard",
            $this->vendorHeaders($vendor),
        )
            ->assertOk()
            ->assertJsonCount(1, 'topItems')
            ->assertJsonPath('topItems.0.id', (string) $currentItem->id)
            ->assertJsonPath('topItems.0.name', 'Current Pizza Name')
            ->assertJsonPath('topItems.0.orderedCount', 2)
            ->assertJsonPath('topItems.0.quantitySold', 3);
    }

    public function test_dashboard_does_not_use_an_all_time_rating_as_todays_rating(): void
    {
        $vendor = Vendor::factory()->create();
        $customer = Customer::factory()->create();

        $oldReview = Review::create([
            'review_public_id' => 'rev_'.Str::random(12),
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'rating' => 5,
        ]);
        $oldReview->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/dashboard",
            $this->vendorHeaders($vendor),
        )
            ->assertOk()
            ->assertJsonPath('todayKPIs.customerRating', 0);
    }

    public function test_vendor_cannot_read_another_vendors_dashboard(): void
    {
        $vendor = Vendor::factory()->create();
        $otherVendor = Vendor::factory()->create();

        $this->getJson(
            "/api/vendor/{$otherVendor->vendor_public_id}/dashboard",
            $this->vendorHeaders($vendor),
        )->assertForbidden();
    }

    private function vendorHeaders(Vendor $vendor): array
    {
        $token = $vendor->createToken('dashboard-test')->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }

    private function createTable(Vendor $vendor, int $number, bool $active = true): RestaurantTable
    {
        return $vendor->restaurantTables()->create([
            'number' => $number,
            'name' => "Table {$number}",
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => $active,
            'qr_created_at' => now(),
        ]);
    }

    private function createSession(
        Vendor $vendor,
        RestaurantTable $table,
        Customer $customer,
        string $pin,
    ): TableScanSession {
        return TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => $pin,
            'status' => 'active',
            'scanned_at' => now(),
        ]);
    }

    private function createOrder(
        Vendor $vendor,
        Customer $customer,
        TableScanSession $session,
        array $attributes = [],
    ): Order {
        return Order::factory()->create(array_merge([
            'vendor_id' => $vendor->id,
            'customer_id' => $customer->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_CONFIRMED,
            'amount' => 5,
            'tip_amount' => 0,
            'currency' => 'GBP',
            'payment_pending' => false,
            'payment_received' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }
}
