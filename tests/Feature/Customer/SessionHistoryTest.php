<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private MenuItem $menuItem;

    private Customer $customer;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create(['restaurant_name' => 'Bella Italia']);
        VendorSetting::create(['vendor_id' => $this->vendor->id, 'is_live_and_discoverable' => true]);
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id, 'name' => 'Mains', 'slug' => 'mains-'.$this->vendor->id,
        ]);
        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id, 'menu_category_id' => $category->id,
            'name' => 'Bruschetta', 'price' => 9,
        ]);

        $this->customer = Customer::factory()->create();
        $this->headers = [
            'Authorization' => 'Bearer '.$this->customer->createToken('t', ['role:customer'])->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    private function makeSession(array $attributes = []): TableScanSession
    {
        return TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'customer_id' => $this->customer->id,
            'type' => 'pickup',
            'pin' => (string) random_int(1000, 9999),
            'status' => 'active',
            'scanned_at' => now(),
            ...$attributes,
        ]);
    }

    public function test_it_lists_every_session_the_customer_owns_newest_first(): void
    {
        $older = $this->makeSession(['status' => 'closed', 'closed_at' => now()->subDay()]);
        $newer = $this->makeSession();

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/sessions')
            ->assertOk();

        $this->assertSame([$newer->id, $older->id], array_column($response->json('sessions'), 'id'));
        $this->assertSame('active', $response->json('sessions.0.status'));
        $this->assertSame('closed', $response->json('sessions.1.status'));
        $this->assertSame('Bella Italia', $response->json('sessions.0.vendor.name'));
    }

    public function test_another_customers_sessions_are_never_listed(): void
    {
        $mine = $this->makeSession();
        $stranger = Customer::factory()->create();
        TableScanSession::create([
            'vendor_id' => $this->vendor->id, 'customer_id' => $stranger->id, 'type' => 'pickup',
            'pin' => '5150', 'status' => 'active', 'scanned_at' => now(),
        ]);

        $response = $this->withHeaders($this->headers)->getJson('/api/customer/sessions')->assertOk();

        $this->assertSame([$mine->id], array_column($response->json('sessions'), 'id'));
    }

    public function test_it_reports_orders_of_every_status_not_only_paid_ones(): void
    {
        $session = $this->makeSession();

        foreach ([['draft', false], ['confirmed', true]] as [$status, $paid]) {
            $order = Order::create([
                'order_public_id' => 'ord-'.$status,
                'customer_id' => $this->customer->id,
                'vendor_id' => $this->vendor->id,
                'table_scan_session_id' => $session->id,
                'status' => $status,
                'amount' => 9,
                'currency' => 'EUR',
                'payment_received' => $paid,
                ...($status === 'confirmed' ? ['confirmed_at' => now()] : ['draft_at' => now()]),
            ]);
            CartItem::create([
                'table_scan_session_id' => $session->id,
                'menu_item_id' => $this->menuItem->id,
                'order_id' => $order->id,
                'quantity' => 2,
            ]);
        }

        $response = $this->withHeaders($this->headers)->getJson('/api/customer/sessions')->assertOk();

        // A history view reports the database, not the payable subset.
        $this->assertSame(2, $response->json('sessions.0.order_count'));
        $this->assertSame(4, $response->json('sessions.0.item_count'));
        $this->assertEqualsCanonicalizing(
            ['draft', 'confirmed'],
            array_column($response->json('sessions.0.orders'), 'status'),
        );
        $this->assertSame('Bruschetta', $response->json('sessions.0.orders.0.items.0.name'));
    }

    public function test_a_session_with_no_order_still_reports_the_items_that_were_added(): void
    {
        $session = $this->makeSession();
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 3,
            'notes' => 'no garlic',
        ]);

        $response = $this->withHeaders($this->headers)->getJson('/api/customer/sessions')->assertOk();

        $this->assertSame(0, $response->json('sessions.0.order_count'));
        $this->assertCount(1, $response->json('sessions.0.unplaced_items'));
        $this->assertSame(3, $response->json('sessions.0.unplaced_items.0.quantity'));
        $this->assertSame('no garlic', $response->json('sessions.0.unplaced_items.0.notes'));
    }

    public function test_close_is_refused_and_reported_while_an_order_is_unpaid(): void
    {
        $session = $this->makeSession();
        Order::create([
            'order_public_id' => 'ord-unpaid',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'amount' => 9,
            'currency' => 'EUR',
            'payment_received' => false,
        ]);

        // The list disables its button for the same reason the endpoint refuses.
        $response = $this->withHeaders($this->headers)->getJson('/api/customer/sessions')->assertOk();
        $this->assertFalse($response->json('sessions.0.can_close'));
        $this->assertSame(
            'This order group still has unpaid orders.',
            $response->json('sessions.0.close_blocked_reason'),
        );

        $this->withHeaders($this->headers)
            ->postJson("/api/customer/sessions/{$session->id}/close")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This order group still has unpaid orders.');

        $this->assertSame('active', $session->fresh()->status);
    }

    public function test_close_settles_the_session_when_nothing_is_outstanding(): void
    {
        $session = $this->makeSession();

        $this->withHeaders($this->headers)->getJson('/api/customer/sessions')
            ->assertOk()
            ->assertJsonPath('sessions.0.can_close', true)
            ->assertJsonPath('sessions.0.close_blocked_reason', null);

        $this->withHeaders($this->headers)
            ->postJson("/api/customer/sessions/{$session->id}/close")
            ->assertOk();

        $session = $session->fresh();
        $this->assertSame('closed', $session->status);
        $this->assertNotNull($session->closed_at);
    }

    public function test_a_dine_in_table_cannot_be_closed_with_unserved_paid_items(): void
    {
        $table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id, 'number' => 7, 'name' => 'Table 7',
            'qr_token' => 'tbl-'.uniqid(), 'is_active' => true,
        ]);
        $session = $this->makeSession(['type' => 'dine_in', 'restaurant_table_id' => $table->id]);
        $order = Order::create([
            'order_public_id' => 'ord-served',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'amount' => 9,
            'currency' => 'EUR',
            'payment_received' => true,
        ]);
        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'served_at' => null,
        ]);

        $this->withHeaders($this->headers)
            ->postJson("/api/customer/sessions/{$session->id}/close")
            ->assertStatus(422)
            ->assertJsonPath('message', 'All the items on table are not served.');
    }

    public function test_closing_someone_elses_session_is_a_404(): void
    {
        $stranger = Customer::factory()->create();
        $theirs = TableScanSession::create([
            'vendor_id' => $this->vendor->id, 'customer_id' => $stranger->id, 'type' => 'pickup',
            'pin' => '4242', 'status' => 'active', 'scanned_at' => now(),
        ]);

        $this->withHeaders($this->headers)
            ->postJson("/api/customer/sessions/{$theirs->id}/close")
            ->assertNotFound();

        $this->assertSame('active', $theirs->fresh()->status);
    }

    public function test_an_already_closed_session_cannot_be_closed_again(): void
    {
        $session = $this->makeSession(['status' => 'closed', 'closed_at' => now()]);

        $this->withHeaders($this->headers)
            ->postJson("/api/customer/sessions/{$session->id}/close")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This session is already closed.');
    }

    public function test_the_endpoints_require_authentication(): void
    {
        $this->getJson('/api/customer/sessions')->assertUnauthorized();
        $this->postJson('/api/customer/sessions/1/close')->assertUnauthorized();
    }
}
