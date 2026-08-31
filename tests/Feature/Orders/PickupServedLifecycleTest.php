<?php

namespace Tests\Feature\Orders;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A pickup order now completes as `served`, exactly like a dine-in one — the
 * waiter no longer has a separate picked-up action. These pin the two things
 * that would otherwise break quietly: the payment guard that used to live on
 * the picked-up endpoint, and the stale-session sweeper's idea of "finished".
 */
class PickupServedLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private MenuItem $menuItem;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create();
        VendorSetting::create(['vendor_id' => $this->vendor->id, 'is_live_and_discoverable' => true]);
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id, 'name' => 'Mains', 'slug' => 'm-'.$this->vendor->id,
        ]);
        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id, 'menu_category_id' => $category->id,
            'name' => 'Burger', 'price' => 10,
        ]);

        $this->headers = [
            'Authorization' => 'Bearer '.$this->vendor->createToken('vendor-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    private function pickupOrder(array $attributes = []): Order
    {
        $session = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'customer_id' => Customer::factory()->create()->id,
            'type' => 'pickup',
            'pin' => (string) random_int(1000, 9999),
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-'.uniqid(),
            'customer_id' => $session->customer_id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'amount' => 10,
            'currency' => 'EUR',
            'payment_received' => true,
            ...$attributes,
        ]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'received_at' => now(),
            'ready_at' => now(),
        ]);

        return $order;
    }

    public function test_a_paid_pickup_order_is_served_like_a_dine_in_one(): void
    {
        $order = $this->pickupOrder();

        $this->withHeaders($this->headers)
            ->patchJson("/api/vendor/orders/{$order->order_public_id}/served")
            ->assertOk();

        $order = $order->fresh();
        $this->assertSame(Order::STATUS_SERVED, $order->status);
        $this->assertNotNull($order->served_at);
        $this->assertNull($order->picked_up_at);
    }

    public function test_an_unpaid_pickup_order_cannot_be_served(): void
    {
        $order = $this->pickupOrder(['payment_received' => false]);

        // The guard used to sit on the picked-up endpoint. Serving pickup orders
        // through the shared endpoint must not hand food over unpaid.
        $this->withHeaders($this->headers)
            ->patchJson("/api/vendor/orders/{$order->order_public_id}/served")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Payment must be confirmed before handing this order over.');

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_staff_can_close_a_pickup_session_that_has_no_table(): void
    {
        $order = $this->pickupOrder();

        $this->withHeaders($this->headers)
            ->patchJson("/api/vendor/orders/{$order->order_public_id}/served")
            ->assertOk();

        // Addressed by session, because a pickup group has no table to resolve
        // from — the table close rejected it with "No active table session found".
        $this->withHeaders($this->headers)
            ->postJson("/api/vendor/{$this->vendor->vendor_public_id}/scan-sessions/{$order->table_scan_session_id}/close")
            ->assertOk()
            ->assertJsonPath('message', 'Order session closed');

        $this->assertSame('closed', TableScanSession::find($order->table_scan_session_id)->status);
    }

    public function test_closing_a_pickup_session_is_refused_while_items_are_unserved(): void
    {
        $order = $this->pickupOrder();

        $this->withHeaders($this->headers)
            ->postJson("/api/vendor/{$this->vendor->vendor_public_id}/scan-sessions/{$order->table_scan_session_id}/close")
            ->assertStatus(409)
            ->assertJsonPath('code', 'unfinished_items');

        $this->assertSame('active', TableScanSession::find($order->table_scan_session_id)->status);
    }

    public function test_a_waiter_may_close_a_pickup_session(): void
    {
        $order = $this->pickupOrder();

        $this->withHeaders($this->headers)
            ->patchJson("/api/vendor/orders/{$order->order_public_id}/served")
            ->assertOk();

        // Staff tokens are allowlisted per route name. A new close endpoint that
        // only the owner token can reach is useless: the waiter is who presses it.
        $waiter = TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Waiter',
            'email' => 'waiter'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'waiter',
            'permissions' => TeamMember::defaultPermissions('waiter'),
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $token = $waiter->createToken('t', ['role:team_member', 'role:waiter'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
            ->postJson("/api/vendor/{$this->vendor->vendor_public_id}/scan-sessions/{$order->table_scan_session_id}/close")
            ->assertOk();

        $this->assertSame('closed', TableScanSession::find($order->table_scan_session_id)->status);
    }

    public function test_a_paid_but_unserved_pickup_session_cannot_be_closed_even_with_force(): void
    {
        // Paid is not the same as handed over: forcing past an unpaid balance is
        // a business call staff can make, but food nobody has collected is not.
        $order = $this->pickupOrder();

        $this->withHeaders($this->headers)
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/scan-sessions/{$order->table_scan_session_id}/close",
                ['force' => true],
            )
            ->assertStatus(409)
            ->assertJsonPath('code', 'unfinished_items');

        $this->assertSame('active', TableScanSession::find($order->table_scan_session_id)->status);
    }

    public function test_a_served_pickup_session_is_closed_by_the_sweeper(): void
    {
        $order = $this->pickupOrder();

        $this->withHeaders($this->headers)
            ->patchJson("/api/vendor/orders/{$order->order_public_id}/served")
            ->assertOk();

        // Age the session past the sweeper's window.
        TableScanSession::whereKey($order->table_scan_session_id)
            ->update(['scanned_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertSame('closed', TableScanSession::find($order->table_scan_session_id)->status);
    }
}
