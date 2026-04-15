<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor   = Vendor::factory()->create(['country' => 'Austria']);
        $this->customer = Customer::factory()->create();
    }

    private function authHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function makeOrder(array $attrs = []): Order
    {
        return $this->vendor->orders()->create(array_merge([
            'order_public_id' => 'ord-' . uniqid(),
            'customer_id'     => $this->customer->id,
            'status'          => 'pending',
            'items_count'     => 2,
            'items'           => [['name' => 'Burger', 'qty' => 1, 'price' => 12.50]],
            'amount'          => 12.50,
            'currency'        => 'EUR',
            'payment_method'  => 'cash',
            'payment_pending' => true,
            'payment_received'=> false,
            'order_type'      => 'takeaway',
        ], $attrs));
    }

    private function makeSession(array $attrs = []): TableSession
    {
        return $this->vendor->tableSessions()->create(array_merge([
            'table_number'        => 1,
            'table_name'          => 'Table 1',
            'status'              => 'active',
            'batch_window_seconds'=> 90,
            'current_course'      => 'drinks',
        ], $attrs));
    }

    private function makeSessionOrder(TableSession $session, array $attrs = []): Order
    {
        return $this->vendor->orders()->create(array_merge([
            'order_public_id' => 'ord-' . uniqid(),
            'customer_id'     => $this->customer->id,
            'table_session_id'=> $session->id,
            'status'          => 'pending',
            'items_count'     => 1,
            'items'           => [['name' => 'Water', 'qty' => 1, 'price' => 3.00]],
            'amount'          => 3.00,
            'currency'        => 'EUR',
            'payment_method'  => 'cash',
            'payment_pending' => false,
            'payment_received'=> false,
            'order_type'      => 'dine-in',
        ], $attrs));
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/orders
    // ----------------------------------------------------------------

    public function test_index_returns_sessions_and_takeaway_structure(): void
    {
        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonStructure(['sessions', 'takeaway']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson("/api/vendor/{$this->vendor->id}/orders")
            ->assertUnauthorized();
    }

    public function test_index_returns_active_sessions_grouped(): void
    {
        $session = $this->makeSession();
        $this->makeSessionOrder($session);
        $this->makeSessionOrder($session);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'sessions')
            ->assertJsonPath('sessions.0.sessionId', (string) $session->id)
            ->assertJsonPath('sessions.0.tableNumber', 1)
            ->assertJsonCount(2, 'sessions.0.orders');
    }

    public function test_index_session_includes_kitchen_summary(): void
    {
        $session = $this->makeSession();
        $this->makeSessionOrder($session, ['status' => 'pending']);
        $this->makeSessionOrder($session, ['status' => 'ready']);
        $this->makeSessionOrder($session, ['status' => 'cancelled']);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('sessions.0.kitchenSummary.total', 3)
            ->assertJsonPath('sessions.0.kitchenSummary.pending', 1)
            ->assertJsonPath('sessions.0.kitchenSummary.ready', 1)
            ->assertJsonPath('sessions.0.kitchenSummary.cancelled', 1);
    }

    public function test_index_session_includes_batch_fields(): void
    {
        $session = $this->makeSession(['batch_started_at' => now()]);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('sessions.0.batchWindowSeconds', 90)
            ->assertJsonStructure(['sessions' => [['batchOpen', 'batchSecondsRemaining', 'batchReleasedAt']]]);
    }

    public function test_index_excludes_closed_sessions(): void
    {
        $this->makeSession(['status' => 'closed', 'closed_at' => now()]);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(0, 'sessions');
    }

    public function test_index_returns_takeaway_orders(): void
    {
        $this->makeOrder(['order_type' => 'takeaway']);
        $this->makeOrder(['order_type' => 'takeaway']);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(2, 'takeaway');
    }

    public function test_index_filters_takeaway_by_status(): void
    {
        $this->makeOrder(['order_type' => 'takeaway', 'status' => 'pending']);
        $this->makeOrder(['order_type' => 'takeaway', 'status' => 'ready']);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders?status=ready", $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'takeaway')
            ->assertJsonPath('takeaway.0.status', 'ready');
    }

    public function test_index_only_returns_own_vendor_orders(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);
        $other->orders()->create([
            'order_public_id' => 'ord-other',
            'customer_id'     => $this->customer->id,
            'status'          => 'pending',
            'items_count'     => 1,
            'items'           => [],
            'amount'          => 10,
            'currency'        => 'EUR',
            'payment_method'  => 'cash',
            'payment_pending' => false,
            'payment_received'=> false,
            'order_type'      => 'takeaway',
        ]);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(0, 'takeaway');
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/orders/{orderId}
    // ----------------------------------------------------------------

    public function test_show_returns_single_order(): void
    {
        $order = $this->makeOrder();

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders/{$order->id}", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('id', (string) $order->id)
            ->assertJsonPath('status', 'pending');
    }

    public function test_show_requires_authentication(): void
    {
        $order = $this->makeOrder();
        $this->getJson("/api/vendor/{$this->vendor->id}/orders/{$order->id}")
            ->assertUnauthorized();
    }

    public function test_show_returns_404_for_unknown_order(): void
    {
        $this->getJson("/api/vendor/{$this->vendor->id}/orders/999999", $this->authHeaders())
            ->assertNotFound();
    }

    public function test_show_returns_session_fields(): void
    {
        $session = $this->makeSession();
        $order   = $this->makeSessionOrder($session);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders/{$order->id}", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('tableSessionId', (string) $session->id)
            ->assertJsonFragment(['waiterConfirmed' => false]);
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/orders/{orderId}/confirm
    // ----------------------------------------------------------------

    public function test_confirm_sets_waiter_confirmed_and_status(): void
    {
        $order = $this->makeOrder(['status' => 'pending']);

        $response = $this->patchJson("/api/vendor/orders/{$order->id}/confirm", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('waiterConfirmed', true);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'status'         => 'confirmed',
            'waiter_confirmed'=> true,
        ]);
    }

    public function test_confirm_requires_authentication(): void
    {
        $order = $this->makeOrder();
        $this->patchJson("/api/vendor/orders/{$order->id}/confirm")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/orders/{orderId}/confirm-cash
    // ----------------------------------------------------------------

    public function test_confirm_cash_sets_payment_received(): void
    {
        $order = $this->makeOrder(['payment_pending' => true, 'payment_received' => false]);

        $response = $this->patchJson("/api/vendor/orders/{$order->id}/confirm-cash", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('paymentReceived', true)
            ->assertJsonPath('paymentPending', false);

        $this->assertDatabaseHas('orders', [
            'id'              => $order->id,
            'payment_received'=> true,
            'payment_pending' => false,
        ]);
    }

    public function test_confirm_cash_requires_authentication(): void
    {
        $order = $this->makeOrder();
        $this->patchJson("/api/vendor/orders/{$order->id}/confirm-cash")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/orders/{orderId}/ready
    // ----------------------------------------------------------------

    public function test_mark_ready_sets_status_and_ready_at(): void
    {
        $order = $this->makeOrder();

        $response = $this->patchJson("/api/vendor/orders/{$order->id}/ready", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', 'ready');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready']);
        $this->assertNotNull(Order::find($order->id)->ready_at);
    }

    public function test_mark_ready_requires_authentication(): void
    {
        $order = $this->makeOrder();
        $this->patchJson("/api/vendor/orders/{$order->id}/ready")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/orders/{orderId}/picked-up
    // ----------------------------------------------------------------

    public function test_mark_picked_up_sets_status(): void
    {
        $order = $this->makeOrder();

        $response = $this->patchJson("/api/vendor/orders/{$order->id}/picked-up", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', 'picked_up');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'picked_up']);
        $this->assertNotNull(Order::find($order->id)->picked_up_at);
    }

    public function test_mark_picked_up_requires_authentication(): void
    {
        $order = $this->makeOrder();
        $this->patchJson("/api/vendor/orders/{$order->id}/picked-up")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/orders/{orderId}/served
    // ----------------------------------------------------------------

    public function test_mark_served_sets_status(): void
    {
        $order = $this->makeOrder();

        $response = $this->patchJson("/api/vendor/orders/{$order->id}/served", [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', 'served');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'served']);
        $this->assertNotNull(Order::find($order->id)->served_at);
    }

    public function test_mark_served_requires_authentication(): void
    {
        $order = $this->makeOrder();
        $this->patchJson("/api/vendor/orders/{$order->id}/served")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/orders/{orderId}/cancel
    // ----------------------------------------------------------------

    public function test_cancel_sets_status_and_reason(): void
    {
        $order = $this->makeOrder();

        $response = $this->patchJson("/api/vendor/orders/{$order->id}/cancel", [
            'reason' => 'Customer changed mind',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('cancelledReason', 'Customer changed mind');

        $this->assertDatabaseHas('orders', [
            'id'               => $order->id,
            'status'           => 'cancelled',
            'cancelled_reason' => 'Customer changed mind',
        ]);
        $this->assertNotNull(Order::find($order->id)->cancelled_at);
    }

    public function test_cancel_works_without_reason(): void
    {
        $order = $this->makeOrder();

        $this->patchJson("/api/vendor/orders/{$order->id}/cancel", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('cancelledReason', null);
    }

    public function test_cancel_requires_authentication(): void
    {
        $order = $this->makeOrder();
        $this->patchJson("/api/vendor/orders/{$order->id}/cancel")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/orders/{orderId} (generic update)
    // ----------------------------------------------------------------

    public function test_update_status_via_generic_endpoint(): void
    {
        $order = $this->makeOrder();

        $response = $this->patchJson("/api/vendor/orders/{$order->id}", [
            'status' => 'preparing',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('status', 'preparing');
    }

    public function test_update_payment_info(): void
    {
        $order = $this->makeOrder(['payment_pending' => true, 'payment_received' => false]);

        $response = $this->patchJson("/api/vendor/orders/{$order->id}", [
            'paymentReceived' => true,
            'paymentNote'     => 'Paid at the counter',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('paymentReceived', true)
            ->assertJsonPath('paymentNote', 'Paid at the counter');
    }

    public function test_update_requires_authentication(): void
    {
        $order = $this->makeOrder();
        $this->patchJson("/api/vendor/orders/{$order->id}", ['status' => 'confirmed'])
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/sessions/{sessionId}/release
    // ----------------------------------------------------------------

    public function test_release_to_kitchen_sets_batch_released_at(): void
    {
        $session = $this->makeSession(['batch_started_at' => now()]);

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/sessions/{$session->id}/release",
            [],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('sessionId', (string) $session->id)
            ->assertJsonPath('batchOpen', false);

        $this->assertNotNull(TableSession::find($session->id)->batch_released_at);
    }

    public function test_release_to_kitchen_requires_authentication(): void
    {
        $session = $this->makeSession();
        $this->postJson("/api/vendor/{$this->vendor->id}/sessions/{$session->id}/release")
            ->assertUnauthorized();
    }

    public function test_release_to_kitchen_returns_404_for_wrong_vendor(): void
    {
        $other   = Vendor::factory()->create(['country' => 'Austria']);
        $session = $this->makeSession();

        $token   = $other->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->postJson("/api/vendor/{$other->id}/sessions/{$session->id}/release", [], $headers)
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/sessions/{sessionId}/fire-course
    // ----------------------------------------------------------------

    public function test_fire_next_course_advances_from_drinks_to_appetizers(): void
    {
        $session = $this->makeSession(['current_course' => 'drinks']);

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/sessions/{$session->id}/fire-course",
            [],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('currentCourse', 'appetizers');

        $this->assertDatabaseHas('table_sessions', [
            'id'             => $session->id,
            'current_course' => 'appetizers',
        ]);
    }

    public function test_fire_next_course_advances_through_full_sequence(): void
    {
        $session = $this->makeSession(['current_course' => 'appetizers']);
        $this->postJson("/api/vendor/{$this->vendor->id}/sessions/{$session->id}/fire-course", [], $this->authHeaders())
            ->assertOk()->assertJsonPath('currentCourse', 'mains');

        $session->refresh();
        $this->postJson("/api/vendor/{$this->vendor->id}/sessions/{$session->id}/fire-course", [], $this->authHeaders())
            ->assertOk()->assertJsonPath('currentCourse', 'desserts');
    }

    public function test_fire_next_course_returns_422_on_desserts(): void
    {
        $session = $this->makeSession(['current_course' => 'desserts']);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/sessions/{$session->id}/fire-course",
            [],
            $this->authHeaders()
        )->assertUnprocessable();
    }

    public function test_fire_next_course_requires_authentication(): void
    {
        $session = $this->makeSession();
        $this->postJson("/api/vendor/{$this->vendor->id}/sessions/{$session->id}/fire-course")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/sessions/{sessionId}/close
    // ----------------------------------------------------------------

    public function test_close_session_sets_status_closed(): void
    {
        $session = $this->makeSession();

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/sessions/{$session->id}/close",
            [],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('status', 'closed');

        $this->assertDatabaseHas('table_sessions', [
            'id'     => $session->id,
            'status' => 'closed',
        ]);
        $this->assertNotNull(TableSession::find($session->id)->closed_at);
    }

    public function test_close_session_requires_authentication(): void
    {
        $session = $this->makeSession();
        $this->postJson("/api/vendor/{$this->vendor->id}/sessions/{$session->id}/close")
            ->assertUnauthorized();
    }

    public function test_close_session_excludes_it_from_active_index(): void
    {
        $session = $this->makeSession();
        $this->makeSessionOrder($session);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/sessions/{$session->id}/close",
            [],
            $this->authHeaders()
        )->assertOk();

        $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'sessions');
    }

    // ----------------------------------------------------------------
    // Batch window logic
    // ----------------------------------------------------------------

    public function test_batch_open_when_within_window(): void
    {
        // batch started 10 seconds ago, 90-second window => still open
        $session = $this->makeSession([
            'batch_started_at'    => now()->subSeconds(10),
            'batch_window_seconds'=> 90,
        ]);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('sessions.0.batchOpen', true);
    }

    public function test_batch_not_open_after_window_expired(): void
    {
        $session = $this->makeSession([
            'batch_started_at'    => now()->subSeconds(100),
            'batch_window_seconds'=> 90,
        ]);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('sessions.0.batchOpen', false);
    }

    public function test_total_amount_excludes_cancelled_orders(): void
    {
        $session = $this->makeSession();
        $this->makeSessionOrder($session, ['amount' => 10.50, 'status' => 'confirmed']);
        $this->makeSessionOrder($session, ['amount' => 20.00, 'status' => 'cancelled']);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('sessions.0.totalAmount', 10.5);
    }
}
