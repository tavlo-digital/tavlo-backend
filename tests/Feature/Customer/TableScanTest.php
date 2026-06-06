<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\CartItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableScanTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Vendor $vendor;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->vendor   = Vendor::factory()->create();
        $this->vendor->vendorSetting()->create(['currency' => 'CHF']);

        $this->headers = $this->headersFor($this->customer);
    }

    private function headersFor(Customer $customer): array
    {
        $token = $customer->createToken('test', ['role:customer'])->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ];
    }

    private function makeTable(array $extra = []): RestaurantTable
    {
        return $this->vendor->restaurantTables()->create(array_merge([
            'number'        => 1,
            'name'          => 'Table 1',
            'qr_token'      => RestaurantTable::generateQrToken(),
            'is_active'     => true,
            'qr_created_at' => now(),
        ], $extra));
    }

    private function postScan(?string $token, ?array $headers = null)
    {
        $headers ??= $this->headers;
        $this->app['auth']->forgetGuards();

        $server = [];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        // Ensure we send raw body to match production usage.
        $server['CONTENT_TYPE'] = 'text/plain';

        return $this->call('POST', '/api/customer/table/scan', [], [], [], $server, (string) ($token ?? ''));
    }

    private function postPin(string $token, string $pin, ?array $headers = null)
    {
        return $this->withHeaders($headers ?? $this->headers)
            ->postJson('/api/customer/table/pin', [
                'token' => $token,
                'pin'   => $pin,
            ]);
    }

    private function postClose(array $payload, ?array $headers = null)
    {
        return $this->withHeaders($headers ?? $this->headers)
            ->postJson('/api/customer/table/close', $payload);
    }

    private function getStatus(?string $token, ?array $headers = null)
    {
        return $this->withHeaders($headers ?? $this->headers)
            ->getJson('/api/customer/table/status'.($token !== null ? '?token='.$token : ''));
    }

    private function activeSession(RestaurantTable $table, ?Customer $customer = null): TableScanSession
    {
        return TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id'         => ($customer ?? $this->customer)->id,
            'pin'                 => '1234',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);
    }

    private function cartItem(TableScanSession $session): CartItem
    {
        $category = MenuCategory::firstOrCreate([
            'vendor_id' => $this->vendor->id,
            'slug'      => 'mains',
        ], [
            'vendor_id' => $this->vendor->id,
            'name'      => 'Mains',
            'slug'      => 'mains',
            'is_active' => true,
        ]);

        $item = MenuItem::create([
            'vendor_id'         => $this->vendor->id,
            'menu_category_id'  => $category->id,
            'name'              => 'Margherita',
            'price'             => 12.50,
            'available'         => true,
        ]);

        return CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id'          => $item->id,
            'quantity'              => 1,
        ]);
    }

    private function order(TableScanSession $session, array $extra = []): Order
    {
        return Order::create(array_merge([
            'order_public_id'       => 'ord-' . uniqid(),
            'customer_id'           => $session->customer_id,
            'vendor_id'             => $session->vendor_id,
            'table_scan_session_id' => $session->id,
            'status'                => 'confirmed',
            'amount'                => 10,
            'currency'              => 'EUR',
            'order_type'            => 'dine-in',
            'payment_pending'       => false,
            'payment_received'      => false,
        ], $extra));
    }

    // ----------------------------------------------------------------
    // GET /api/customer/table/status
    // ----------------------------------------------------------------

    public function test_status_does_not_require_authentication(): void
    {
        $table = $this->makeTable();

        $this->getStatus($table->qr_token, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'available')
            ->assertJsonPath('table.id', (string) $table->id);
    }

    public function test_status_token_is_required(): void
    {
        $this->getStatus(null)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_status_returns_410_for_invalid_token(): void
    {
        $this->getStatus('does-not-exist')
            ->assertStatus(410)
            ->assertJson(['message' => 'This QR code is no longer valid']);
    }

    public function test_status_returns_available_when_table_has_no_active_session(): void
    {
        $table = $this->makeTable(['number' => 3, 'name' => 'T3']);
        $this->vendor->update([
            'vendor_public_id' => 'VID-8492',
            'name'             => 'Bella Italia',
        ]);

        $this->getStatus($table->qr_token)
            ->assertOk()
            ->assertJson([
                'table' => [
                    'id'     => (string) $table->id,
                    'number' => 3,
                    'name'   => 'T3',
                ],
                'vendor' => [
                    'id'   => 'VID-8492',
                    'name' => 'Bella Italia',
                ],
                'status' => 'available',
            ]);
    }

    public function test_status_returns_draft_when_active_session_has_no_cart_items(): void
    {
        $table = $this->makeTable();
        $this->activeSession($table);

        $this->getStatus($table->qr_token)
            ->assertOk()
            ->assertJsonPath('status', 'draft');
    }

    public function test_status_returns_active_when_active_session_has_cart_items(): void
    {
        $table = $this->makeTable();
        $session = $this->activeSession($table);
        $this->cartItem($session);

        $this->getStatus($table->qr_token)
            ->assertOk()
            ->assertJsonPath('status', 'active');
    }

    public function test_status_ignores_closed_sessions(): void
    {
        $table = $this->makeTable();
        $session = $this->activeSession($table);
        $session->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);
        $this->cartItem($session);

        $this->getStatus($table->qr_token)
            ->assertOk()
            ->assertJsonPath('status', 'available');
    }

    // ----------------------------------------------------------------
    // POST /api/customer/table/scan
    // ----------------------------------------------------------------

    public function test_unauthenticated_request_is_rejected(): void
    {
        $table = $this->makeTable();

        $this->postScan($table->qr_token, ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    public function test_token_is_required(): void
    {
        $this->postScan('', $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_invalid_token_returns_410(): void
    {
        $this->postScan('does-not-exist', $this->headers)
            ->assertStatus(410)
            ->assertJson(['message' => 'This QR code is no longer valid']);
    }

    public function test_inactive_table_returns_410(): void
    {
        $table = $this->makeTable(['is_active' => false]);

        $this->postScan($table->qr_token, $this->headers)
            ->assertStatus(410);
    }

    public function test_valid_scan_creates_active_session_with_pin_without_requiring_pin_entry(): void
    {
        $table = $this->makeTable(['number' => 7, 'name' => 'T7']);
        $this->vendor->vendorSetting->update(['currency' => 'PKR']);

        $response = $this->postScan($table->qr_token, $this->headers);

        $response->assertCreated()
            ->assertJsonStructure([
                'pin',
                'session' => ['id', 'status', 'scannedAt'],
                'table'   => ['id', 'number', 'name'],
                'vendor'  => ['id', 'name', 'currency'],
            ])
            ->assertJsonPath('table.number', 7)
            ->assertJsonPath('table.name', 'T7')
            ->assertJsonPath('vendor.currency', 'PKR')
            ->assertJsonPath('session.status', 'active')
            ->assertJsonPath('requiresPin', false);

        $pin = $response->json('pin');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);

        $this->assertDatabaseHas('table_scan_sessions', [
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id'         => $this->customer->id,
            'status'              => 'active',
            'pin'                 => $pin,
        ]);
    }

    public function test_guest_scan_without_existing_active_session_does_not_require_pin_entry(): void
    {
        $guest = Customer::factory()->create([
            'account_type'        => 'guest',
            'registration_source' => 'guest',
        ]);
        $table = $this->makeTable();

        $this->postScan($table->qr_token, $this->headersFor($guest))
            ->assertCreated()
            ->assertJsonPath('requiresPin', false)
            ->assertJsonPath('session.status', 'active');

        $this->assertDatabaseHas('table_scan_sessions', [
            'restaurant_table_id' => $table->id,
            'customer_id'         => $guest->id,
            'status'              => 'active',
        ]);
    }

    public function test_scan_route_also_accepts_json_token_body_for_backward_compatibility(): void
    {
        $table = $this->makeTable();

        $this->postJson('/api/customer/table/scan', ['token' => $table->qr_token], $this->headers)
            ->assertCreated();
    }

    public function test_scan_updates_table_last_scanned_at(): void
    {
        $table = $this->makeTable(['last_scanned_at' => null]);

        $this->postScan($table->qr_token, $this->headers)
            ->assertCreated();

        $this->assertNotNull($table->fresh()->last_scanned_at);
    }

    public function test_pin_is_unique_among_active_sessions(): void
    {
        // Pre-fill 9999 of the 10 000 possible PINs as active sessions to
        // force the generator to pick the only remaining one.
        $table = $this->makeTable();
        $otherTable = $this->makeTable(['number' => 2, 'name' => 'Table 2', 'qr_token' => RestaurantTable::generateQrToken()]);

        for ($i = 0; $i < 9999; $i++) {
            TableScanSession::create([
                'vendor_id'           => $this->vendor->id,
                'restaurant_table_id' => $otherTable->id,
                'customer_id'         => $this->customer->id,
                'pin'                 => str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'status'              => 'active',
                'scanned_at'          => now(),
            ]);
        }

        $response = $this->postScan($table->qr_token, $this->headers);

        $response->assertCreated()
            ->assertJsonPath('pin', '9999');
    }

    public function test_closed_session_pin_can_be_reused(): void
    {
        $table = $this->makeTable();

        TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id'         => $this->customer->id,
            'pin'                 => '1234',
            'status'              => 'closed',
            'scanned_at'          => now()->subHour(),
            'closed_at'           => now()->subMinutes(5),
        ]);

        // Should not throw or loop forever — closed sessions don't block PIN.
        $response = $this->postScan($table->qr_token, $this->headers);

        $response->assertCreated();
        $this->assertDatabaseCount('table_scan_sessions', 2);
    }

    public function test_same_customer_second_scan_returns_existing_session_without_requiring_pin_entry(): void
    {
        $table = $this->makeTable();

        $first = $this->postScan($table->qr_token, $this->headers)
            ->assertCreated()
            ->json();

        $this->postScan($table->qr_token, $this->headers)
            ->assertCreated()
            ->assertJson([
                'message'     => 'Table session was already started',
                'status'      => 'active',
                'requiresPin' => false,
                'pin'         => $first['pin'],
            ])
            ->assertJsonPath('session.id', $first['session']['id']);

        $this->assertSame(1, TableScanSession::where('restaurant_table_id', $table->id)->where('status', 'active')->count());
    }

    public function test_other_customer_scan_is_blocked_when_active_session_exists_for_table(): void
    {
        $table = $this->makeTable();

        $first = $this->postScan($table->qr_token, $this->headers)
            ->assertCreated()
            ->json();

        $otherCustomer = Customer::factory()->create();

        $this->postScan($table->qr_token, $this->headersFor($otherCustomer))
            ->assertStatus(409)
            ->assertJson([
                'message'     => 'This table already has an active session',
                'status'      => 'active',
                'requiresPin' => true,
                'pin'         => null,
            ]);

        $this->assertSame(1, TableScanSession::where('restaurant_table_id', $table->id)->where('status', 'active')->count());
        $this->assertSame('active', $first['session']['status']);
    }

    public function test_409_response_does_not_expose_pin(): void
    {
        $table = $this->makeTable();

        $this->postScan($table->qr_token, $this->headers)->assertCreated();

        $otherCustomer = Customer::factory()->create();
        $response = $this->postScan($table->qr_token, $this->headersFor($otherCustomer));

        $response->assertStatus(409)
            ->assertJsonPath('requiresPin', true)
            ->assertJsonPath('pin', null);
    }

    // ----------------------------------------------------------------
    // POST /api/customer/table/pin
    // ----------------------------------------------------------------

    public function test_pin_route_requires_authentication(): void
    {
        $table = $this->makeTable();

        $this->postJson('/api/customer/table/pin', [
            'token' => $table->qr_token,
            'pin'   => '1234',
        ])->assertUnauthorized();
    }

    public function test_pin_route_requires_valid_token_and_pin(): void
    {
        $this->postJson('/api/customer/table/pin', [], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'pin']);
    }

    public function test_pin_route_rejects_invalid_qr_token(): void
    {
        $this->postPin('does-not-exist', '1234')
            ->assertStatus(410)
            ->assertJson(['message' => 'This QR code is no longer valid']);
    }

    public function test_pin_route_rejects_invalid_pin_for_table(): void
    {
        $table = $this->makeTable();

        $this->postScan($table->qr_token, $this->headers)->assertCreated();

        $this->postPin($table->qr_token, '9999')
            ->assertStatus(422)
            ->assertJson(['message' => 'The provided PIN is invalid for this table']);
    }

    public function test_pin_route_joins_existing_active_table_without_creating_new_pin(): void
    {
        $table = $this->makeTable();
        $ownerPin = $this->postScan($table->qr_token, $this->headers)
            ->assertCreated()
            ->json('pin');

        /** @var Customer $secondCustomer */
        $secondCustomer = Customer::factory()->create();

        $response = $this->actingAs($secondCustomer, 'customer')
            ->postJson('/api/customer/table/pin', [
                'token' => $table->qr_token,
                'pin'   => $ownerPin,
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('pin', $ownerPin)
            ->assertJsonPath('session.status', 'active')
            ->assertJsonPath('table.id', (string) $table->id)
            ->assertJsonPath('vendor.currency', 'CHF');

        $this->assertDatabaseHas('table_scan_sessions', [
            'restaurant_table_id' => $table->id,
            'customer_id'         => $secondCustomer->id,
            'status'              => 'active',
            'pin'                 => $ownerPin,
        ]);
    }

    public function test_pin_route_stores_entered_pin_on_existing_customer_session(): void
    {
        $table = $this->makeTable();
        $secondCustomer = Customer::factory()->create();

        TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id'         => $this->customer->id,
            'pin'                 => '2468',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        $secondSession = TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id'         => $secondCustomer->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        $this->actingAs($secondCustomer, 'customer')
            ->postJson('/api/customer/table/pin', [
                'token' => $table->qr_token,
                'pin'   => '2468',
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('pin', '2468')
            ->assertJsonPath('session.id', (string) $secondSession->id);

        $this->assertDatabaseHas('table_scan_sessions', [
            'id'  => $secondSession->id,
            'pin' => '2468',
        ]);
    }

    public function test_pin_route_is_idempotent_for_customer_already_joined_to_active_table(): void
    {
        $table = $this->makeTable();
        $ownerPin = $this->postScan($table->qr_token, $this->headers)
            ->assertCreated()
            ->json('pin');

        /** @var Customer $secondCustomer */
        $secondCustomer = Customer::factory()->create();

        $this->actingAs($secondCustomer, 'customer')
            ->postJson('/api/customer/table/pin', [
                'token' => $table->qr_token,
                'pin'   => $ownerPin,
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->actingAs($secondCustomer, 'customer')
            ->postJson('/api/customer/table/pin', [
                'token' => $table->qr_token,
                'pin'   => $ownerPin,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('pin', $ownerPin);

        $this->assertSame(1, TableScanSession::where('restaurant_table_id', $table->id)
            ->where('customer_id', $secondCustomer->id)
            ->where('status', 'active')
            ->count());
    }

    // ----------------------------------------------------------------
    // POST /api/customer/table/close
    // ----------------------------------------------------------------

    public function test_close_route_requires_authentication(): void
    {
        $table = $this->makeTable();

        $this->postJson('/api/customer/table/close', [
            'table_id' => $table->id,
        ])->assertUnauthorized();
    }

    public function test_close_route_requires_table_id(): void
    {
        $this->postClose([])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['table_id']);
    }

    public function test_session_user_close_closes_all_sessions_for_table(): void
    {
        $table = $this->makeTable();
        $mySession = $this->activeSession($table);
        $otherCustomer = Customer::factory()->create();
        $otherSession = $this->activeSession($table, $otherCustomer);

        $this->postClose(['table_id' => $table->id])
            ->assertOk()
            ->assertJsonPath('message', 'Table session closed')
            ->assertJsonPath('status', 'closed')
            ->assertJsonPath('session.id', (string) $mySession->id);

        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $mySession->id,
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $otherSession->id,
            'status' => 'closed',
        ]);
    }

    public function test_close_route_accepts_legacy_vendor_public_id_when_supplied(): void
    {
        $table = $this->makeTable();
        $session = $this->activeSession($table);

        $this->postClose([
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'table_id'         => $table->id,
        ])
            ->assertOk()
            ->assertJsonPath('session.id', (string) $session->id);
    }

    public function test_close_route_blocks_when_any_session_user_has_unpaid_order(): void
    {
        $table = $this->makeTable();
        $session = $this->activeSession($table);
        $this->order($session, ['payment_received' => false]);

        $this->postClose(['table_id' => $table->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'There is an active order on this table.');

        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $session->id,
            'status' => 'active',
        ]);
    }

    public function test_session_user_close_blocked_when_other_session_user_has_unpaid_order(): void
    {
        $table = $this->makeTable();
        $mySession = $this->activeSession($table);
        $otherCustomer = Customer::factory()->create();
        $otherSession = $this->activeSession($table, $otherCustomer);
        $this->order($otherSession, ['payment_received' => false]);

        $this->postClose(['table_id' => $table->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'There is an active order on this table.');

        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $mySession->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $otherSession->id,
            'status' => 'active',
        ]);
    }

    public function test_close_route_allows_draft_order_for_session(): void
    {
        $table = $this->makeTable();
        $session = $this->activeSession($table);
        $this->order($session, [
            'status'           => 'draft',
            'payment_received' => false,
        ]);

        $this->postClose(['table_id' => $table->id])
            ->assertOk()
            ->assertJsonPath('message', 'Table session closed')
            ->assertJsonPath('status', 'closed');

        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_close_route_blocks_when_paid_order_has_unserved_items(): void
    {
        $table = $this->makeTable();
        $session = $this->activeSession($table);
        $order = $this->order($session, [
            'payment_received'      => true,
            'payment_confirmed_at'  => now(),
        ]);
        $item = $this->cartItem($session);
        $item->update(['order_id' => $order->id]);

        $this->postClose(['table_id' => $table->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'All the items on table are not served.');

        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $session->id,
            'status' => 'active',
        ]);
    }

    public function test_close_route_allows_paid_order_when_items_are_served(): void
    {
        $table = $this->makeTable();
        $session = $this->activeSession($table);
        $order = $this->order($session, [
            'payment_received'      => true,
            'payment_confirmed_at'  => now(),
        ]);
        $item = $this->cartItem($session);
        $item->update([
            'order_id'  => $order->id,
            'served_at' => now(),
        ]);

        $this->postClose(['table_id' => $table->id])
            ->assertOk()
            ->assertJsonPath('message', 'Table session closed')
            ->assertJsonPath('status', 'closed');

        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $session->id,
            'status' => 'closed',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /api/customer/table/close — Non-session user
    // ----------------------------------------------------------------

    public function test_non_session_user_blocked_when_order_exists(): void
    {
        $table = $this->makeTable();
        $sessionOwner = Customer::factory()->create();
        $session = $this->activeSession($table, $sessionOwner);

        $this->order($session, ['payment_received' => false]);

        $this->postClose(['table_id' => $table->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'There is an active order on this table.');
    }

    public function test_non_session_user_can_close_when_no_orders(): void
    {
        $table = $this->makeTable();
        $sessionOwner = Customer::factory()->create();
        $session = $this->activeSession($table, $sessionOwner);

        $this->postClose(['table_id' => $table->id])
            ->assertOk()
            ->assertJsonPath('message', 'Table session closed');

        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_non_session_user_close_closes_all_sessions(): void
    {
        $table = $this->makeTable();
        $owner1 = Customer::factory()->create();
        $owner2 = Customer::factory()->create();
        $session1 = $this->activeSession($table, $owner1);
        $session2 = $this->activeSession($table, $owner2);

        $this->postClose(['table_id' => $table->id])
            ->assertOk();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $session1->id,
            'status' => 'closed',
        ]);
        $this->assertDatabaseHas('table_scan_sessions', [
            'id'     => $session2->id,
            'status' => 'closed',
        ]);
    }

    public function test_close_returns_422_when_no_active_session_for_table(): void
    {
        $table = $this->makeTable();

        $this->postClose(['table_id' => $table->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No active table session found.');
    }

    // ----------------------------------------------------------------
    // POST /api/customer/table/call
    // ----------------------------------------------------------------

    private function postCall(?array $headers = null)
    {
        return $this->withHeaders($headers ?? $this->headers)
            ->postJson('/api/customer/table/call');
    }

    public function test_call_requires_authentication(): void
    {
        $this->postJson('/api/customer/table/call')
            ->assertUnauthorized();
    }

    public function test_call_requires_active_session(): void
    {
        $this->postCall()
            ->assertStatus(422)
            ->assertJsonPath('message', 'You do not have an active table session.');
    }

    public function test_call_notifies_all_waiters(): void
    {
        $table = $this->makeTable();
        $this->activeSession($table);

        $waiter1 = TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Waiter One',
            'email'     => 'w1@test.com',
            'role'      => 'waiter',
            'status'    => 'active',
        ]);
        $waiter2 = TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Waiter Two',
            'email'     => 'w2@test.com',
            'role'      => 'waiter',
            'status'    => 'active',
        ]);

        $this->postCall()
            ->assertOk()
            ->assertJsonPath('message', 'Waiters have been notified.');

        $this->assertDatabaseHas('notifications', [
            'user_id'   => $waiter1->id,
            'event'     => 'table_call',
            'user_role' => 'team_member',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id'   => $waiter2->id,
            'event'     => 'table_call',
            'user_role' => 'team_member',
        ]);
    }

    public function test_call_does_not_notify_kitchen_staff(): void
    {
        $table = $this->makeTable();
        $this->activeSession($table);

        TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Waiter',
            'email'     => 'waiter@test.com',
            'role'      => 'waiter',
            'status'    => 'active',
        ]);
        $kitchen = TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Chef',
            'email'     => 'chef@test.com',
            'role'      => 'kitchen',
            'status'    => 'active',
        ]);

        $this->postCall()->assertOk();

        $this->assertDatabaseMissing('notifications', [
            'user_id'   => $kitchen->id,
            'event'     => 'table_call',
        ]);
        $this->assertCount(1, Notification::where('event', 'table_call')->get());
    }

    public function test_call_returns_422_when_no_waiters_available(): void
    {
        $table = $this->makeTable();
        $this->activeSession($table);

        $this->postCall()
            ->assertStatus(422)
            ->assertJsonPath('message', 'No waiters available at this restaurant.');
    }

    public function test_call_does_not_notify_suspended_waiters(): void
    {
        $table = $this->makeTable();
        $this->activeSession($table);

        TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Suspended Waiter',
            'email'     => 'suspended@test.com',
            'role'      => 'waiter',
            'status'    => 'suspended',
        ]);

        $this->postCall()
            ->assertStatus(422)
            ->assertJsonPath('message', 'No waiters available at this restaurant.');
    }
}
