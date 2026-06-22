<?php

namespace Tests\Feature\QR;

use App\Models\Customer;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Models\VendorTakeawayQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrManagementTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);
    }

    private function authHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function makeTable(int $number = 1, array $extra = []): RestaurantTable
    {
        return $this->vendor->restaurantTables()->create(array_merge([
            'number'        => $number,
            'name'          => "Table {$number}",
            'qr_token'      => RestaurantTable::generateQrToken(),
            'is_active'     => true,
            'qr_created_at' => now(),
        ], $extra));
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/tables
    // ----------------------------------------------------------------

    public function test_index_returns_empty_array_when_no_tables(): void
    {
        $this->getJson("/api/vendor/{$this->vendor->id}/tables", $this->authHeaders())
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_index_returns_tables_with_status_field(): void
    {
        $this->makeTable(1);
        $this->makeTable(2);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/tables", $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['number' => 1, 'status' => 'idle'])
            ->assertJsonFragment(['number' => 2, 'status' => 'idle']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson("/api/vendor/{$this->vendor->id}/tables")
            ->assertUnauthorized();
    }

    public function test_index_table_status_is_active_when_pending_order_exists(): void
    {
        $table    = $this->makeTable(3);
        $customer = Customer::factory()->create();
        $session = TableScanSession::create([
            'vendor_id'            => $this->vendor->id,
            'restaurant_table_id'  => $table->id,
            'customer_id'          => $customer->id,
            'pin'                  => '1234',
            'status'               => 'active',
            'scanned_at'           => now(),
        ]);

        Order::create([
            'order_public_id'      => 'ORD-001',
            'vendor_id'            => $this->vendor->id,
            'customer_id'          => $customer->id,
            'table_scan_session_id' => $session->id,
            'status'               => 'confirmed',
            'table_number'         => '3',
            'order_type'           => 'dine-in',
            'amount'               => 25.00,
            'currency'             => 'EUR',
        ]);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/tables", $this->authHeaders());

        $response->assertOk()
            ->assertJsonFragment(['number' => 3, 'status' => 'active']);
    }

    public function test_index_table_status_is_waiting_payment_when_served_and_unpaid(): void
    {
        $table    = $this->makeTable(4);
        $customer = Customer::factory()->create();
        $session = TableScanSession::create([
            'vendor_id'            => $this->vendor->id,
            'restaurant_table_id'  => $table->id,
            'customer_id'          => $customer->id,
            'pin'                  => '5678',
            'status'               => 'active',
            'scanned_at'           => now(),
        ]);

        Order::create([
            'order_public_id'      => 'ORD-002',
            'vendor_id'            => $this->vendor->id,
            'customer_id'          => $customer->id,
            'table_scan_session_id' => $session->id,
            'status'               => 'served',
            'table_number'         => '4',
            'order_type'           => 'dine-in',
            'amount'               => 30.00,
            'currency'             => 'EUR',
            'payment_pending'      => true,
        ]);

        $response = $this->getJson("/api/vendor/{$this->vendor->id}/tables", $this->authHeaders());

        $response->assertOk()
            ->assertJsonFragment(['number' => 4, 'status' => 'waiting_payment']);
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/tables
    // ----------------------------------------------------------------

    public function test_store_creates_table_with_qr_token(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables",
            ['number' => 5],
            $this->authHeaders()
        );

        $response->assertCreated()
            ->assertJsonFragment(['number' => 5, 'name' => 'Table 5', 'isActive' => true])
            ->assertJsonStructure(['id', 'number', 'name', 'qrToken', 'isActive', 'status', 'qrCreatedAt']);

        $this->assertDatabaseHas('restaurant_tables', [
            'vendor_id' => $this->vendor->id,
            'number'    => 5,
        ]);
    }

    public function test_store_auto_generates_name_when_not_provided(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables",
            ['number' => 7],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonFragment(['name' => 'Table 7']);
    }

    public function test_store_uses_custom_name_when_provided(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables",
            ['number' => 8, 'name' => 'VIP Room'],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonFragment(['name' => 'VIP Room']);
    }

    public function test_store_requires_number(): void
    {
        $this->postJson("/api/vendor/{$this->vendor->id}/tables", [], $this->authHeaders())
            ->assertUnprocessable();
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/{vendorId}/tables/{tableId}
    // ----------------------------------------------------------------

    public function test_update_patches_table_name(): void
    {
        $table = $this->makeTable(1);

        $this->patchJson(
            "/api/vendor/{$this->vendor->id}/tables/{$table->id}",
            ['name' => 'Window Table'],
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonFragment(['name' => 'Window Table']);
    }

    public function test_update_patches_is_active(): void
    {
        $table = $this->makeTable(1);

        $this->patchJson(
            "/api/vendor/{$this->vendor->id}/tables/{$table->id}",
            ['is_active' => false],
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonFragment(['isActive' => false]);
    }

    public function test_update_returns_404_for_unknown_table(): void
    {
        $this->patchJson(
            "/api/vendor/{$this->vendor->id}/tables/9999",
            ['name' => 'Ghost'],
            $this->authHeaders()
        )->assertNotFound();
    }

    // ----------------------------------------------------------------
    // DELETE /api/vendor/{vendorId}/tables/{tableId}
    // ----------------------------------------------------------------

    public function test_destroy_deletes_table(): void
    {
        $table = $this->makeTable(1);

        $this->deleteJson(
            "/api/vendor/{$this->vendor->id}/tables/{$table->id}",
            [],
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonFragment(['message' => 'Table deleted']);

        $this->assertDatabaseMissing('restaurant_tables', ['id' => $table->id]);
    }

    public function test_destroy_rejects_table_with_active_orders(): void
    {
        $table    = $this->makeTable(2);
        $customer = Customer::factory()->create();

        Order::create([
            'order_public_id' => 'ORD-003',
            'vendor_id'       => $this->vendor->id,
            'customer_id'     => $customer->id,
            'status'          => 'in_progress',
            'table_number'    => '2',
            'order_type'      => 'dine-in',
            'amount'          => 20.00,
            'currency'        => 'EUR',
        ]);

        $this->deleteJson(
            "/api/vendor/{$this->vendor->id}/tables/{$table->id}",
            [],
            $this->authHeaders()
        )->assertStatus(409);

        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id]);
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/tables/regenerate-all
    // ----------------------------------------------------------------

    public function test_regenerate_all_replaces_all_qr_tokens(): void
    {
        $t1 = $this->makeTable(1);
        $t2 = $this->makeTable(2);

        $oldToken1 = $t1->qr_token;
        $oldToken2 = $t2->qr_token;

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/regenerate-all",
            [],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonCount(2);

        $this->assertDatabaseMissing('restaurant_tables', ['qr_token' => $oldToken1]);
        $this->assertDatabaseMissing('restaurant_tables', ['qr_token' => $oldToken2]);
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/tables/{tableId}/refresh-qr
    // ----------------------------------------------------------------

    public function test_refresh_qr_replaces_token_for_single_table(): void
    {
        $table    = $this->makeTable(1);
        $oldToken = $table->qr_token;

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$table->id}/refresh-qr",
            [],
            $this->authHeaders()
        );

        $response->assertOk();
        $newToken = $response->json('qrToken');

        $this->assertNotEquals($oldToken, $newToken);
        $this->assertDatabaseMissing('restaurant_tables', ['qr_token' => $oldToken]);
        $this->assertDatabaseHas('restaurant_tables', ['qr_token' => $newToken]);
    }

    public function test_cannot_regenerate_qr_with_active_session(): void
    {
        $table = $this->makeTable(1);
        $customer = Customer::factory()->create();
        $oldToken = $table->qr_token;

        TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$table->id}/refresh-qr",
            [],
            $this->authHeaders()
        )->assertStatus(409)
            ->assertJsonPath('active_sessions', 1);

        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id, 'qr_token' => $oldToken]);
    }

    public function test_can_regenerate_qr_after_session_is_closed(): void
    {
        $table = $this->makeTable(1);
        $customer = Customer::factory()->create();
        $oldToken = $table->qr_token;

        TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'closed',
            'scanned_at' => now(),
            'closed_at' => now(),
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$table->id}/refresh-qr",
            [],
            $this->authHeaders()
        )->assertOk();

        $this->assertDatabaseMissing('restaurant_tables', ['qr_token' => $oldToken]);
    }

    public function test_regenerate_all_skips_tables_with_active_sessions(): void
    {
        $t1 = $this->makeTable(1);
        $t2 = $this->makeTable(2);
        $customer = Customer::factory()->create();

        $oldToken1 = $t1->qr_token;
        $oldToken2 = $t2->qr_token;

        TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $t1->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/regenerate-all",
            [],
            $this->authHeaders()
        )->assertOk();

        $this->assertDatabaseHas('restaurant_tables', ['id' => $t1->id, 'qr_token' => $oldToken1]);
        $this->assertDatabaseMissing('restaurant_tables', ['qr_token' => $oldToken2]);
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/tables/{tableId}/scan  (public)
    // ----------------------------------------------------------------

    public function test_record_scan_updates_last_scanned_at(): void
    {
        $table = $this->makeTable(1);

        $this->postJson("/api/vendor/{$this->vendor->id}/tables/{$table->id}/scan")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Scan recorded', 'tableNumber' => 1]);

        $this->assertNotNull(RestaurantTable::find($table->id)->last_scanned_at);
    }

    public function test_record_scan_with_valid_token_succeeds(): void
    {
        $table = $this->makeTable(1);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$table->id}/scan?token={$table->qr_token}"
        )->assertOk();
    }

    public function test_record_scan_with_invalid_token_returns_410(): void
    {
        $table = $this->makeTable(1);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/{$table->id}/scan?token=invalid-token-xyz"
        )->assertStatus(410);
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/tables/takeaway-qr
    // ----------------------------------------------------------------

    public function test_get_takeaway_qr_creates_record_if_not_exists(): void
    {
        $this->assertDatabaseMissing('vendor_takeaway_qrs', ['vendor_id' => $this->vendor->id]);

        $response = $this->getJson(
            "/api/vendor/{$this->vendor->id}/tables/takeaway-qr",
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure(['id', 'qrToken', 'qrCreatedAt', 'lastRegeneratedAt', 'lastScannedAt', 'isActive'])
            ->assertJsonFragment(['isActive' => true]);

        $this->assertDatabaseHas('vendor_takeaway_qrs', ['vendor_id' => $this->vendor->id]);
    }

    public function test_get_takeaway_qr_is_idempotent(): void
    {
        $firstToken = $this->getJson(
            "/api/vendor/{$this->vendor->id}/tables/takeaway-qr",
            $this->authHeaders()
        )->json('qrToken');

        $secondToken = $this->getJson(
            "/api/vendor/{$this->vendor->id}/tables/takeaway-qr",
            $this->authHeaders()
        )->json('qrToken');

        $this->assertEquals($firstToken, $secondToken);
        $this->assertDatabaseCount('vendor_takeaway_qrs', 1);
    }

    public function test_get_takeaway_qr_requires_authentication(): void
    {
        $this->getJson("/api/vendor/{$this->vendor->id}/tables/takeaway-qr")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/tables/takeaway-qr/refresh
    // ----------------------------------------------------------------

    public function test_refresh_takeaway_qr_changes_token(): void
    {
        $oldToken = $this->getJson(
            "/api/vendor/{$this->vendor->id}/tables/takeaway-qr",
            $this->authHeaders()
        )->json('qrToken');

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/takeaway-qr/refresh",
            [],
            $this->authHeaders()
        );

        $response->assertOk();
        $newToken = $response->json('qrToken');

        $this->assertNotEquals($oldToken, $newToken);
        $this->assertNotNull($response->json('lastRegeneratedAt'));
    }

    public function test_refresh_takeaway_qr_requires_authentication(): void
    {
        $this->postJson("/api/vendor/{$this->vendor->id}/tables/takeaway-qr/refresh")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/takeaway/scan  (public)
    // ----------------------------------------------------------------

    public function test_record_takeaway_scan_with_valid_token_succeeds(): void
    {
        $qr = VendorTakeawayQr::create([
            'vendor_id' => $this->vendor->id,
            'qr_token'  => 'valid-token-abc',
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/takeaway/scan?token=valid-token-abc"
        )
            ->assertOk()
            ->assertJsonFragment(['message' => 'Scan recorded', 'type' => 'takeaway']);

        $this->assertNotNull(VendorTakeawayQr::find($qr->id)->last_scanned_at);
    }

    public function test_record_takeaway_scan_without_token_succeeds(): void
    {
        VendorTakeawayQr::create([
            'vendor_id' => $this->vendor->id,
            'qr_token'  => 'some-token',
        ]);

        $this->postJson("/api/vendor/{$this->vendor->id}/takeaway/scan")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Scan recorded']);
    }

    public function test_record_takeaway_scan_with_invalid_token_returns_410(): void
    {
        VendorTakeawayQr::create([
            'vendor_id' => $this->vendor->id,
            'qr_token'  => 'correct-token',
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->id}/takeaway/scan?token=wrong-token"
        )->assertStatus(410);
    }

    public function test_record_takeaway_scan_returns_410_when_no_qr_exists(): void
    {
        $this->postJson(
            "/api/vendor/{$this->vendor->id}/takeaway/scan?token=anything"
        )->assertStatus(410);
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/tables/sync
    // ----------------------------------------------------------------

    public function test_sync_creates_tables_to_reach_desired_count(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/sync",
            ['count' => 3],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonCount(3);

        $this->assertDatabaseCount('restaurant_tables', 3);
    }

    public function test_sync_removes_excess_tables(): void
    {
        $this->makeTable(1);
        $this->makeTable(2);
        $this->makeTable(3);

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/sync",
            ['count' => 1],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonCount(1);

        $this->assertDatabaseCount('restaurant_tables', 1);
    }

    public function test_sync_is_idempotent_when_count_matches(): void
    {
        $this->makeTable(1);
        $this->makeTable(2);

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/sync",
            ['count' => 2],
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonCount(2);

        $this->assertDatabaseCount('restaurant_tables', 2);
    }

    public function test_sync_requires_count(): void
    {
        $this->postJson(
            "/api/vendor/{$this->vendor->id}/tables/sync",
            [],
            $this->authHeaders()
        )->assertUnprocessable();
    }
}
