<?php

namespace Tests\Feature\Settings;

use App\Models\Vendor;
use App\Models\VendorRequestChange;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorSettingsTest extends TestCase
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

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/settings
    // ----------------------------------------------------------------

    public function test_can_get_vendor_settings(): void
    {
        $response = $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'restaurantName',
                'acceptOnSite',
                'stripeEnabled',
                'stripeAccountId',
                'stripeOnboardingComplete',
                'redemptionRate',
                'notifyPushOrderReady',
                'dateFormat',
                'timeFormat',
                'showInTopCustomers',
                'dataRetentionDays',
                'menuTheme',
                'currency',
            ]);

        $payload = $response->json();
        $this->assertArrayNotHasKey('acceptCash', $payload);
        $this->assertArrayNotHasKey('acceptCard', $payload);
        $this->assertArrayNotHasKey('acceptVisa', $payload);
        $this->assertArrayNotHasKey('acceptMastercard', $payload);
        $this->assertArrayNotHasKey('acceptAmex', $payload);
        $this->assertArrayNotHasKey('acceptBankTransfer', $payload);
    }

    public function test_get_settings_requires_authentication(): void
    {
        $this->getJson("/api/vendor/{$this->vendor->vendor_public_id}/settings")
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // PUT /api/vendor/{vendorId}/settings
    // ----------------------------------------------------------------

    public function test_can_update_basic_settings(): void
    {
        $response = $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'restaurantName'     => 'New Name',
                'description'        => 'A great place',
                'isLiveAndDiscoverable' => true,
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonFragment(['restaurantName' => 'New Name']);

        $this->assertDatabaseHas('vendors', [
            'id'              => $this->vendor->id,
            'restaurant_name' => 'New Name',
        ]);
        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id'   => $this->vendor->id,
            'description' => 'A great place',
        ]);
    }

    public function test_can_update_payment_settings(): void
    {
        $response = $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'acceptOnSite'  => false,
                'stripeEnabled' => true,
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonFragment([
                'acceptOnSite'  => false,
                'stripeEnabled' => true,
            ]);

        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id'       => $this->vendor->id,
            'accept_on_site'  => false,
            'stripe_enabled'  => true,
        ]);
    }

    public function test_can_update_loyalty_settings(): void
    {
        $response = $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'loyaltyEnabled'  => true,
                'pointsPerEuro'   => 20,
                'redemptionRate'  => 0.05,
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonFragment([
                'loyaltyEnabled' => true,
                'redemptionRate' => 0.05,
            ]);
    }

    public function test_cannot_update_another_vendors_settings(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);

        $this->putJson(
            "/api/vendor/{$other->vendor_public_id}/settings",
            ['description' => 'Hacked'],
            $this->authHeaders()
        )->assertForbidden();
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/legal-info
    // ----------------------------------------------------------------

    public function test_submit_legal_info_creates_pending_record(): void
    {
        $response = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/legal-info",
            [
                'legalEntityName'            => 'My GmbH',
                'businessRegistrationNumber' => 'FN123456a',
                'vatNumber'                  => 'ATU12345678',
            ],
            $this->authHeaders()
        );

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Legal info submitted for approval.']);

        $this->assertDatabaseHas('vendor_request_changes', [
            'vendor_id'          => $this->vendor->id,
            'legal_entity_name'  => 'My GmbH',
            'vat_number'         => 'ATU12345678',
            'status'             => 'pending',
        ]);
    }

    public function test_cannot_submit_legal_info_while_pending(): void
    {
        VendorRequestChange::create([
            'vendor_id'                      => $this->vendor->id,
            'restaurant_name'                => 'Old Name',
            'legal_entity_name'              => 'Old GmbH',
            'business_registration_number'   => 'FN000001a',
            'vat_number'                     => 'ATU00000001',
            'status'                         => 'pending',
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/legal-info",
            [
                'legalEntityName'            => 'New GmbH',
                'businessRegistrationNumber' => 'FN999999b',
                'vatNumber'                  => 'ATU99999999',
            ],
            $this->authHeaders()
        )->assertStatus(422);
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/legal-info/status
    // ----------------------------------------------------------------

    public function test_can_get_legal_change_status(): void
    {
        VendorRequestChange::create([
            'vendor_id'                      => $this->vendor->id,
            'restaurant_name'                => 'Test',
            'legal_entity_name'              => 'Test GmbH',
            'business_registration_number'   => 'FN123456a',
            'vat_number'                     => 'ATU12345678',
            'status'                         => 'pending',
        ]);

        $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/legal-info/status",
            $this->authHeaders()
        )->assertOk()
            ->assertJsonFragment(['status' => 'pending']);
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/{vendorId}/settings/logo
    // ----------------------------------------------------------------

    public function test_upload_logo_stores_file_and_returns_url(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings/logo",
            ['logo' => $file],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure(['logoUrl']);

        $logoUrl = $response->json('logoUrl');
        $this->assertNotEmpty($logoUrl);

        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
        ]);
    }

    public function test_upload_logo_rejects_non_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('malicious.pdf', 100, 'application/pdf');

        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings/logo",
            ['logo' => $file],
            $this->authHeaders()
        )->assertStatus(422);
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/settings/export
    // ----------------------------------------------------------------

    public function test_export_data_returns_json(): void
    {
        $response = $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings/export",
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure(['exportedAt', 'vendor']);
    }
}
