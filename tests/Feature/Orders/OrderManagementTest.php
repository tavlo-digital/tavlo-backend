<?php

namespace Tests\Feature\Orders;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;
    private Customer $customer;
    private RestaurantTable $table;
    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);
        $this->customer = Customer::factory()->create();
        $this->table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 7,
            'name' => 'Table 7',
            'qr_token' => 'qr-' . uniqid(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
        ]);

        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Pasta',
            'price' => 12.50,
        ]);
    }

    private function vendorHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function staffHeaders(string $role = 'waiter'): array
    {
        $member = TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name' => ucfirst($role),
            'email' => $role . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'permissions' => TeamMember::defaultPermissions($role),
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $member->createToken('test', ['role:team_member', "role:{$role}"])->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function scanSession(?Customer $customer = null, array $attrs = []): TableScanSession
    {
        return TableScanSession::create(array_merge([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ], $attrs));
    }

    private function order(TableScanSession $session, array $attrs = []): Order
    {
        return Order::create(array_merge([
            'order_public_id' => 'ord-' . uniqid(),
            'customer_id' => $session->customer_id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => 'confirmed',
            'amount' => 12.50,
            'currency' => 'EUR',
            'payment_method' => 'cash',
            'payment_pending' => true,
            'payment_received' => false,
            'order_type' => 'dine-in',
        ], $attrs));
    }

    private function cartItem(TableScanSession $session, array $attrs = []): CartItem
    {
        return CartItem::create(array_merge([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'notes' => null,
        ], $attrs));
    }

    public function test_index_returns_active_table_scan_sessions_grouped_by_table(): void
    {
        $first = $this->scanSession();
        $second = $this->scanSession(Customer::factory()->create());
        $this->order($first, ['status' => 'confirmed', 'amount' => 10]);
        $this->order($second, ['status' => 'in_progress', 'amount' => 15]);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->vendorHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'sessions')
            ->assertJsonPath('sessions.0.tableId', (string) $this->table->id)
            ->assertJsonPath('sessions.0.tableNumber', 7)
            ->assertJsonPath('sessions.0.guestCount', 2)
            ->assertJsonPath('sessions.0.totalAmount', 25)
            ->assertJsonPath('sessions.0.kitchenSummary.total', 2)
            ->assertJsonPath('sessions.0.kitchenSummary.in_progress', 1)
            ->assertJsonCount(2, 'sessions.0.orders');
    }

    public function test_index_excludes_closed_table_scan_sessions(): void
    {
        $this->scanSession(null, ['status' => 'closed', 'closed_at' => now()]);

        $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->vendorHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'sessions');
    }

    public function test_kitchen_can_update_cart_item_status(): void
    {
        $session = $this->scanSession();
        $order = $this->order($session);
        $item = $this->cartItem($session, ['order_id' => $order->id]);

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'preparing'],
            $this->staffHeaders('kitchen')
        )->assertOk()
            ->assertJsonPath('items.0.status', 'preparing')
            ->assertJsonPath('status', 'in_progress');

        $this->assertNotNull($item->fresh()->preparing_start_at);
        $this->assertTrue(Notification::where('event', 'order_item_status_changed')
            ->whereNotNull('kitchen_id')
            ->where('is_silent', true)
            ->exists());

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'ready'],
            $this->staffHeaders('kitchen')
        )->assertOk()
            ->assertJsonPath('items.0.status', 'ready')
            ->assertJsonPath('status', 'in_progress');

        $this->assertNotNull($item->fresh()->ready_at);
    }

    public function test_waiter_can_mark_item_served_but_kitchen_cannot(): void
    {
        $session = $this->scanSession();
        $order = $this->order($session);
        $item = $this->cartItem($session, ['order_id' => $order->id, 'ready_at' => now()]);

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'served'],
            $this->staffHeaders('kitchen')
        )->assertForbidden();
        $this->app['auth']->forgetGuards();

        $response = $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'served'],
            $this->staffHeaders('waiter')
        );
        $response->assertOk()
            ->assertJsonPath('items.0.status', 'served')
            ->assertJsonPath('status', 'served');

        $this->assertNotNull($item->fresh()->served_at);
    }

    public function test_close_table_session_requires_force_when_unpaid_then_closes(): void
    {
        $session = $this->scanSession();
        $this->order($session, [
            'amount' => 20,
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            [],
            $this->staffHeaders('waiter')
        )->assertStatus(409)
            ->assertJsonPath('paymentSummary.remainingAmount', 20);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            ['force' => true],
            $this->staffHeaders('waiter')
        )->assertOk()
            ->assertJsonPath('message', 'Table session closed');

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_close_table_session_requires_waiter_role(): void
    {
        $this->scanSession();

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            ['force' => true],
            $this->staffHeaders('kitchen')
        )->assertForbidden();
    }

    public function test_close_table_session_rejects_wrong_vendor(): void
    {
        $this->scanSession();
        $otherVendor = Vendor::factory()->create(['country' => 'Austria']);
        $token = $otherVendor->createToken('test')->plainTextToken;

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            ['force' => true],
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']
        )->assertForbidden();
    }
}
