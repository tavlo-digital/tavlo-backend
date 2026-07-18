<?php

namespace Tests\Feature\Console;

use App\Jobs\DeliverOperationalNotification;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CloseStaleTableScanSessionsTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Customer $customer;

    private RestaurantTable $table;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->table = $this->vendor->restaurantTables()->create([
            'number' => 1,
            'name' => 'Table 1',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
        ]);

        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Margherita',
            'price' => 12.50,
            'available' => true,
        ]);
    }

    public function test_closes_session_with_no_order_after_ten_minutes(): void
    {
        $session = $this->tableScanSession([
            'scanned_at' => now()->subMinutes(11),
            'created_at' => now()->subMinutes(11),
            'updated_at' => now()->subMinutes(11),
        ]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_expired_session_push_is_silent_and_closes_the_waiter_table(): void
    {
        Queue::fake();
        $this->tableScanSession([
            'scanned_at' => now()->subMinutes(11),
            'created_at' => now()->subMinutes(11),
            'updated_at' => now()->subMinutes(11),
        ]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();
        app(DeferredCallbackCollection::class)->invoke();

        Queue::assertPushed(DeliverOperationalNotification::class, fn (DeliverOperationalNotification $job): bool => $job->payload['event'] === 'table_session_changed'
            && $job->payload['silent'] === true
            && ($job->payload['metadata']['table_action'] ?? null) === 'closed'
            && (int) ($job->payload['metadata']['table_id'] ?? 0) === $this->table->id
        );
    }

    public function test_keeps_session_with_no_order_before_ten_minutes(): void
    {
        $session = $this->tableScanSession([
            'scanned_at' => now()->subMinutes(9),
            'created_at' => now()->subMinutes(9),
            'updated_at' => now()->subMinutes(9),
        ]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'active',
        ]);
    }

    public function test_closes_session_when_draft_order_is_stale(): void
    {
        $session = $this->tableScanSession();
        $this->order($session, [
            'status' => 'draft',
            'payment_received' => false,
            'created_at' => now()->subMinutes(11),
            'updated_at' => now()->subMinutes(11),
        ]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_keeps_session_when_draft_order_is_recent(): void
    {
        $session = $this->tableScanSession();
        $this->order($session, [
            'status' => 'draft',
            'payment_received' => false,
            'created_at' => now()->subMinutes(11),
            'updated_at' => now()->subMinutes(5),
        ]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'active',
        ]);
    }

    public function test_keeps_session_with_unpaid_non_draft_order(): void
    {
        $session = $this->tableScanSession([
            'scanned_at' => now()->subMinutes(30),
        ]);
        $this->order($session, [
            'status' => 'confirmed',
            'payment_received' => false,
        ]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'active',
        ]);
    }

    public function test_keeps_paid_session_when_order_items_are_not_served(): void
    {
        $session = $this->tableScanSession();
        $order = $this->order($session, [
            'status' => 'confirmed',
            'payment_received' => true,
            'payment_confirmed_at' => now(),
        ]);
        $this->cartItem($session, ['order_id' => $order->id, 'served_at' => null]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'active',
        ]);
    }

    public function test_keeps_paid_session_when_shared_item_for_order_is_not_served(): void
    {
        $session = $this->tableScanSession();
        $order = $this->order($session, [
            'status' => 'confirmed',
            'payment_received' => true,
            'payment_confirmed_at' => now(),
        ]);
        $otherCustomer = Customer::factory()->create();
        $otherSession = $this->tableScanSession([
            'customer_id' => $otherCustomer->id,
        ]);
        $this->cartItem($otherSession, [
            'shared_order_ids' => [$order->id],
            'served_at' => null,
        ]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'active',
        ]);
    }

    public function test_closes_paid_session_when_all_order_items_are_served(): void
    {
        $session = $this->tableScanSession();
        $order = $this->order($session, [
            'status' => 'confirmed',
            'payment_received' => true,
            'payment_confirmed_at' => now(),
        ]);
        $this->cartItem($session, [
            'order_id' => $order->id,
            'served_at' => now(),
        ]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    private function tableScanSession(array $extra = []): TableScanSession
    {
        $timestamps = array_intersect_key($extra, array_flip(['created_at', 'updated_at']));
        $extra = array_diff_key($extra, $timestamps);

        $session = TableScanSession::create(array_merge([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $this->customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ], $extra));

        if ($timestamps !== []) {
            $session->forceFill($timestamps)->save();
        }

        return $session;
    }

    private function order(TableScanSession $session, array $extra = []): Order
    {
        $timestamps = array_intersect_key($extra, array_flip(['created_at', 'updated_at']));
        $extra = array_diff_key($extra, $timestamps);

        $order = Order::create(array_merge([
            'order_public_id' => 'ord-'.uniqid(),
            'customer_id' => $session->customer_id,
            'vendor_id' => $session->vendor_id,
            'table_scan_session_id' => $session->id,
            'status' => 'confirmed',
            'amount' => 10,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => false,
            'payment_received' => false,
        ], $extra));

        if ($timestamps !== []) {
            $order->forceFill($timestamps)->save();
        }

        return $order;
    }

    private function cartItem(TableScanSession $session, array $extra = []): CartItem
    {
        return CartItem::create(array_merge([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ], $extra));
    }
}
