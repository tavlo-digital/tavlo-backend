<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
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
            ]);

        $this->assertSame(1, TableScanSession::where('restaurant_table_id', $table->id)->where('status', 'active')->count());
        $this->assertSame('active', $first['session']['status']);
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
            ->assertJsonPath('pin', null)
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
            ->assertJsonPath('pin', null);

        $this->assertSame(1, TableScanSession::where('restaurant_table_id', $table->id)
            ->where('customer_id', $secondCustomer->id)
            ->where('status', 'active')
            ->count());
    }
}
