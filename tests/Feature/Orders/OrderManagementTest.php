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
use Illuminate\Support\Facades\DB;
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

    public function test_index_loads_active_languages_only_once_while_formatting_orders(): void
    {
        $session = $this->scanSession();

        foreach (range(1, 3) as $number) {
            $order = $this->order($session, [
                'order_public_id' => 'ord-'.$number.'-'.uniqid(),
            ]);
            $this->cartItem($session, ['order_id' => $order->id]);
        }

        $languageQueries = 0;
        DB::listen(function ($query) use (&$languageQueries) {
            if (str_contains($query->sql, 'languages')) {
                $languageQueries++;
            }
        });

        $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->vendorHeaders())
            ->assertOk()
            ->assertJsonCount(3, 'sessions.0.orders');

        $this->assertSame(1, $languageQueries);
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

    public function test_item_status_can_jump_forward_directly_to_ready(): void
    {
        $session = $this->scanSession();
        $order = $this->order($session);
        $item = $this->cartItem($session, [
            'order_id' => $order->id,
            'received_at' => now(),
        ]);

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'ready'],
            $this->vendorHeaders()
        )->assertOk()
            ->assertJsonPath('items.0.status', 'ready');

        $item->refresh();
        $this->assertNotNull($item->preparing_start_at);
        $this->assertNotNull($item->ready_at);
        $this->assertNull($item->served_at);
    }

    public function test_late_backward_item_status_update_returns_conflict_without_erasing_progress(): void
    {
        $session = $this->scanSession();
        $order = $this->order($session);
        $item = $this->cartItem($session, [
            'order_id' => $order->id,
            'received_at' => now(),
        ]);
        $headers = $this->vendorHeaders();

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'ready'],
            $headers
        )->assertOk();

        $readyItem = $item->fresh();
        $preparingAt = $readyItem->preparing_start_at;
        $readyAt = $readyItem->ready_at;
        $notificationCount = Notification::where('event', 'order_item_status_changed')->count();

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'new'],
            $headers
        )->assertStatus(409)
            ->assertJson([
                'message' => 'Item status has already advanced.',
                'current_status' => 'ready',
                'requested_status' => 'new',
            ]);

        $item->refresh();
        $this->assertTrue($item->preparing_start_at->equalTo($preparingAt));
        $this->assertTrue($item->ready_at->equalTo($readyAt));
        $this->assertNull($item->served_at);
        $this->assertSame('in_progress', $order->fresh()->status);
        $this->assertSame(
            $notificationCount,
            Notification::where('event', 'order_item_status_changed')->count()
        );
    }

    public function test_new_request_is_an_idempotent_no_op_for_received_item(): void
    {
        $session = $this->scanSession();
        $order = $this->order($session);
        $item = $this->cartItem($session, [
            'order_id' => $order->id,
            'received_at' => now(),
        ]);

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'new'],
            $this->vendorHeaders()
        )->assertOk()
            ->assertJsonPath('items.0.status', 'received');

        $item->refresh();
        $this->assertNotNull($item->received_at);
        $this->assertNull($item->preparing_start_at);
        $this->assertNull($item->ready_at);
        $this->assertNull($item->served_at);
        $this->assertSame(0, Notification::where('event', 'order_item_status_changed')->count());
    }

    public function test_repeating_item_status_is_idempotent_without_duplicate_notifications(): void
    {
        $session = $this->scanSession();
        $order = $this->order($session);
        $item = $this->cartItem($session, ['order_id' => $order->id]);
        $headers = $this->vendorHeaders();

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'preparing'],
            $headers
        )->assertOk();

        $item->refresh();
        $preparingAt = $item->preparing_start_at;
        $notificationCount = Notification::where('event', 'order_item_status_changed')->count();

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'preparing'],
            $headers
        )->assertOk()
            ->assertJsonPath('items.0.status', 'preparing');

        $this->assertTrue($item->fresh()->preparing_start_at->equalTo($preparingAt));
        $this->assertSame(
            $notificationCount,
            Notification::where('event', 'order_item_status_changed')->count()
        );
    }

    public function test_served_item_cannot_move_back_to_ready(): void
    {
        $session = $this->scanSession();
        $order = $this->order($session);
        $servedAt = now();
        $item = $this->cartItem($session, [
            'order_id' => $order->id,
            'preparing_start_at' => $servedAt->copy()->subMinutes(10),
            'ready_at' => $servedAt->copy()->subMinutes(5),
            'served_at' => $servedAt,
        ]);
        $persistedServedAt = $item->fresh()->served_at;

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/items/{$item->id}",
            ['status' => 'ready'],
            $this->vendorHeaders()
        )->assertStatus(409)
            ->assertJsonPath('current_status', 'served')
            ->assertJsonPath('requested_status', 'ready');

        $this->assertTrue($item->fresh()->served_at->equalTo($persistedServedAt));
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
        $order = $this->order($session, [
            'amount' => 20,
            'payment_pending' => true,
            'payment_received' => false,
        ]);
        $this->cartItem($session, [
            'order_id' => $order->id,
            'served_at' => now(),
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            [],
            $this->staffHeaders('waiter')
        )->assertStatus(409)
            ->assertJsonPath('code', 'unpaid_balance')
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

    public function test_close_table_session_blocks_unfinished_items_even_when_forced_and_keeps_ticket_visible(): void
    {
        $session = $this->scanSession();
        $order = $this->order($session, [
            'payment_pending' => false,
            'payment_received' => true,
        ]);
        $this->cartItem($session, [
            'order_id' => $order->id,
            'received_at' => now(),
        ]);
        $headers = $this->staffHeaders('waiter');

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            [],
            $headers
        )->assertStatus(409)
            ->assertJsonPath('code', 'unfinished_items')
            ->assertJsonPath('fulfillmentSummary.unfinishedOrdersCount', 1)
            ->assertJsonPath('fulfillmentSummary.unservedItemsCount', 1);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            ['force' => true],
            $headers
        )->assertStatus(409)
            ->assertJsonPath('code', 'unfinished_items');

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);

        $this->getJson(
            "/api/vendor/{$this->vendor->id}/orders",
            $headers
        )->assertOk()
            ->assertJsonPath('sessions.0.orders.0.id', (string) $order->id);
    }

    public function test_close_table_session_allows_fully_served_paid_orders(): void
    {
        $session = $this->scanSession();
        $order = $this->order($session, [
            'payment_pending' => false,
            'payment_received' => true,
        ]);
        $this->cartItem($session, [
            'order_id' => $order->id,
            'served_at' => now(),
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            [],
            $this->staffHeaders('waiter')
        )->assertOk();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_cancelled_and_draft_orders_do_not_block_table_closure(): void
    {
        $session = $this->scanSession();
        $cancelledOrder = $this->order($session, [
            'status' => 'cancelled',
            'payment_pending' => false,
            'payment_received' => false,
        ]);
        $draftOrder = $this->order($session, [
            'status' => 'draft',
            'payment_pending' => true,
            'payment_received' => false,
        ]);
        $this->cartItem($session, ['order_id' => $cancelledOrder->id]);
        $this->cartItem($session, ['order_id' => $draftOrder->id]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            [],
            $this->staffHeaders('waiter')
        )->assertOk();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_shared_unserved_item_blocks_each_affected_order_but_is_counted_once(): void
    {
        $session = $this->scanSession();
        $firstOrder = $this->order($session, [
            'payment_pending' => false,
            'payment_received' => true,
        ]);
        $secondOrder = $this->order($session, [
            'payment_pending' => false,
            'payment_received' => true,
        ]);
        $this->cartItem($session, [
            'order_id' => $firstOrder->id,
            'shared_order_ids' => [$secondOrder->id],
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/close-session",
            [],
            $this->staffHeaders('waiter')
        )->assertStatus(409)
            ->assertJsonPath('code', 'unfinished_items')
            ->assertJsonPath('fulfillmentSummary.unfinishedOrdersCount', 2)
            ->assertJsonPath('fulfillmentSummary.unservedItemsCount', 1);
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

    public function test_waiter_can_transfer_all_active_sessions_and_orders_to_an_empty_table(): void
    {
        $secondCustomer = Customer::factory()->create();
        $firstSession = $this->scanSession();
        $secondSession = $this->scanSession($secondCustomer, ['pin' => '5678']);
        $firstOrder = $this->order($firstSession, ['table_number' => '7']);
        $secondOrder = $this->order($secondSession, ['table_number' => '7']);
        $this->cartItem($firstSession, ['order_id' => $firstOrder->id]);
        $this->cartItem($secondSession, ['order_id' => $secondOrder->id]);
        $this->table->update(['call_waiter_at' => now()]);

        $targetTable = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 12,
            'name' => 'Table 12',
            'qr_token' => 'qr-' . uniqid(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/transfer",
            ['target_table_id' => $targetTable->id],
            $this->staffHeaders('waiter')
        );

        $response->assertOk()
            ->assertJsonPath('source_table_id', (string) $this->table->id)
            ->assertJsonPath('target_table_id', (string) $targetTable->id)
            ->assertJsonPath('sessions_transferred', 2)
            ->assertJsonPath('orders_updated', 2);

        foreach ([$firstSession, $secondSession] as $session) {
            $this->assertDatabaseHas('table_scan_sessions', [
                'id' => $session->id,
                'restaurant_table_id' => $targetTable->id,
                'status' => 'active',
            ]);
        }

        foreach ([$firstOrder, $secondOrder] as $order) {
            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
                'table_number' => '12',
                'table_scan_session_id' => $order->table_scan_session_id,
            ]);
        }

        $this->assertNull($this->table->fresh()->call_waiter_at);
        $this->assertNotNull($targetTable->fresh()->call_waiter_at);
        $this->assertDatabaseHas('notifications', [
            'customer_id' => $this->customer->id,
            'event' => 'table_session_transferred',
        ]);
        $this->assertDatabaseHas('notifications', [
            'customer_id' => $secondCustomer->id,
            'event' => 'table_session_transferred',
        ]);

        $this->getJson(
            "/api/vendor/{$this->vendor->id}/orders",
            $this->staffHeaders('waiter')
        )->assertOk()
            ->assertJsonCount(1, 'sessions')
            ->assertJsonPath('sessions.0.tableId', (string) $targetTable->id)
            ->assertJsonPath('sessions.0.tableNumber', 12)
            ->assertJsonPath('sessions.0.guestCount', 2);
    }

    public function test_transfer_rejects_an_occupied_target_without_changing_the_source(): void
    {
        $sourceSession = $this->scanSession();
        $sourceOrder = $this->order($sourceSession, ['table_number' => '7']);
        $targetTable = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 12,
            'name' => 'Table 12',
            'qr_token' => 'qr-' . uniqid(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
        $this->scanSession(Customer::factory()->create(), [
            'restaurant_table_id' => $targetTable->id,
            'pin' => '5678',
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/transfer",
            ['target_table_id' => $targetTable->id],
            $this->staffHeaders('waiter')
        )->assertStatus(409)
            ->assertJsonPath('code', 'target_table_occupied')
            ->assertJsonPath('target_table_id', (string) $targetTable->id);

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $sourceSession->id,
            'restaurant_table_id' => $this->table->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $sourceOrder->id,
            'table_number' => '7',
        ]);
    }

    public function test_transfer_rejects_the_source_table_as_its_own_target(): void
    {
        $this->scanSession();

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/transfer",
            ['target_table_id' => $this->table->id],
            $this->staffHeaders('waiter')
        )->assertStatus(422)
            ->assertJsonPath('code', 'same_table');
    }

    public function test_transfer_rejects_an_inactive_target_table(): void
    {
        $sourceSession = $this->scanSession();
        $targetTable = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 12,
            'name' => 'Table 12',
            'qr_token' => 'qr-' . uniqid(),
            'is_active' => false,
            'qr_created_at' => now(),
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/transfer",
            ['target_table_id' => $targetTable->id],
            $this->staffHeaders('waiter')
        )->assertStatus(409)
            ->assertJsonPath('code', 'inactive_target');

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $sourceSession->id,
            'restaurant_table_id' => $this->table->id,
        ]);
    }

    public function test_transfer_rejects_a_vendor_token_from_another_restaurant(): void
    {
        $this->scanSession();
        $targetTable = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 12,
            'name' => 'Table 12',
            'qr_token' => 'qr-' . uniqid(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
        $otherVendor = Vendor::factory()->create(['country' => 'Austria']);
        $token = $otherVendor->createToken('test')->plainTextToken;

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/transfer",
            ['target_table_id' => $targetTable->id],
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']
        )->assertForbidden();
    }

    public function test_transfer_also_accepts_a_vendor_public_id(): void
    {
        $sourceSession = $this->scanSession();
        $targetTable = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 12,
            'name' => 'Table 12',
            'qr_token' => 'qr-' . uniqid(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/transfer",
            ['target_table_id' => $targetTable->id],
            $this->staffHeaders('waiter')
        )->assertOk()
            ->assertJsonPath('target_table_id', (string) $targetTable->id);

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $sourceSession->id,
            'restaurant_table_id' => $targetTable->id,
        ]);
    }
}
