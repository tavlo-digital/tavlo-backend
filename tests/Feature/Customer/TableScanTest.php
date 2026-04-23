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

        $token = $this->customer->createToken('test', ['role:customer'])->plainTextToken;
        $this->headers = [
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

        $server = [];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        // Ensure we send raw body to match production usage.
        $server['CONTENT_TYPE'] = 'text/plain';

        return $this->call('POST', '/api/customer/scan', [], [], [], $server, (string) ($token ?? ''));
    }

    // ----------------------------------------------------------------
    // POST /api/customer/scan
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

    public function test_valid_scan_creates_active_session_with_pin_and_returns_payload(): void
    {
        $table = $this->makeTable(['number' => 7, 'name' => 'T7']);

        $response = $this->postScan($table->qr_token, $this->headers);

        $response->assertCreated()
            ->assertJsonStructure([
                'pin',
                'session' => ['id', 'status', 'scannedAt'],
                'table'   => ['id', 'number', 'name'],
                'vendor'  => ['id', 'name'],
            ])
            ->assertJsonPath('table.number', 7)
            ->assertJsonPath('table.name', 'T7')
            ->assertJsonPath('session.status', 'active');

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

    public function test_second_scan_is_blocked_when_active_session_exists_for_table(): void
    {
        $table = $this->makeTable();

        $first = $this->postScan($table->qr_token, $this->headers)
            ->assertCreated()
            ->json();

        $this->postScan($table->qr_token, $this->headers)
            ->assertStatus(409)
            ->assertJson([
                'message' => 'This table already has an active session',
                'status'  => 'active',
            ]);

        $this->assertSame(1, TableScanSession::where('restaurant_table_id', $table->id)->where('status', 'active')->count());
        $this->assertSame('active', $first['session']['status']);
    }
}
