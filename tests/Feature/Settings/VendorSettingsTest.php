<?php

namespace Tests\Feature\Settings;

use App\Models\Vendor;
use App\Models\VendorRequestChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
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
        VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'status' => 'approved',
            'changes' => json_encode(['legalEntityName' => 'Test GmbH']),
        ]);

        $response = $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'restaurantName' => 'New Name',
                'description' => 'A great place',
                'isLiveAndDiscoverable' => true,
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonFragment(['restaurantName' => 'New Name']);

        $this->assertDatabaseHas('vendors', [
            'id' => $this->vendor->id,
            'restaurant_name' => 'New Name',
        ]);
        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
            'description' => 'A great place',
        ]);
    }

    public function test_can_update_payment_settings(): void
    {
        $response = $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'acceptOnSite' => false,
                'stripeEnabled' => true,
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonFragment([
                'acceptOnSite' => false,
                'stripeEnabled' => true,
            ]);

        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
            'accept_on_site' => false,
            'stripe_enabled' => true,
        ]);
    }

    public function test_currency_is_derived_from_country_and_not_saved_in_settings(): void
    {
        $this->vendor->update(['country' => 'GB']);

        $response = $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'currency' => 'USD',
                'latitude' => 51.5074,
                'longitude' => -0.1278,
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('currency', 'GBP');

        $this->assertFalse(Schema::hasColumn('vendor_settings', 'currency'));
    }

    public function test_can_update_loyalty_settings(): void
    {
        $response = $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'loyaltyEnabled' => true,
                'pointsPerEuro' => 20,
                'redemptionRate' => 0.05,
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonFragment([
                'loyaltyEnabled' => true,
                'redemptionRate' => 0.05,
            ]);
    }

    public function test_language_settings_are_independent_and_keep_english_enabled(): void
    {
        $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'dashboardLanguage' => 'de',
                'defaultLanguage' => 'it',
                'supportedLanguages' => ['it'],
            ],
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonPath('dashboardLanguage', 'de')
            ->assertJsonPath('defaultLanguage', 'it')
            ->assertJsonPath('supportedLanguages.0', 'it')
            ->assertJsonPath('supportedLanguages.1', 'en');

        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
            'dashboard_language' => 'de',
            'default_language' => 'it',
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
                'restaurantName' => 'My Restaurant',
                'legalEntityName' => 'My GmbH',
                'businessRegistrationNumber' => 'FN123456a',
                'vatNumber' => 'ATU12345678',
                'companyType' => 'GmbH',
                'country' => 'AT',
                'city' => 'Vienna',
                'address' => 'Main Street 1',
            ],
            $this->authHeaders()
        );

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Legal info submitted for approval.']);

        $this->assertDatabaseHas('vendor_request_changes', [
            'vendor_id' => $this->vendor->id,
            'legal_entity_name' => 'My GmbH',
            'vat_number' => 'ATU12345678',
            'company_type' => 'GmbH',
            'status' => 'pending',
        ]);
    }

    public function test_first_legal_info_submission_requires_all_fields(): void
    {
        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/legal-info",
            [],
            $this->authHeaders()
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'restaurantName',
                'legalEntityName',
                'businessRegistrationNumber',
                'vatNumber',
                'companyType',
                'country',
                'city',
                'address',
            ]);
    }

    public function test_submit_legal_info_updates_existing_pending_request(): void
    {
        $pendingChange = VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_name' => 'Old Name',
            'legal_entity_name' => 'Old GmbH',
            'business_registration_number' => 'FN000001a',
            'vat_number' => 'ATU00000001',
            'company_type' => 'GmbH',
            'city' => 'Vienna',
            'status' => 'pending',
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/legal-info",
            [
                'legalEntityName' => 'New GmbH',
                'businessRegistrationNumber' => 'FN999999b',
                'vatNumber' => 'ATU99999999',
                'companyType' => 'AG',
                'city' => 'Graz',
            ],
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonFragment([
                'message' => 'Pending legal info updated successfully.',
            ]);

        $this->assertDatabaseCount('vendor_request_changes', 1);
        $this->assertDatabaseHas('vendor_request_changes', [
            'id' => $pendingChange->id,
            'vendor_id' => $this->vendor->id,
            'legal_entity_name' => 'New GmbH',
            'business_registration_number' => 'FN999999b',
            'vat_number' => 'ATU99999999',
            'company_type' => 'AG',
            'city' => 'Graz',
            'status' => 'pending',
        ]);
    }

    public function test_existing_pending_request_allows_partial_update(): void
    {
        $pendingChange = VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_name' => 'Pending Restaurant',
            'legal_entity_name' => 'Pending GmbH',
            'business_registration_number' => 'FN000001a',
            'vat_number' => 'ATU00000001',
            'company_type' => 'GmbH',
            'country' => 'AT',
            'city' => 'Vienna',
            'address' => 'Old Street 1',
            'status' => 'pending',
        ]);

        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/legal-info",
            ['city' => 'Graz'],
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonFragment([
                'message' => 'Pending legal info updated successfully.',
            ]);

        $this->assertDatabaseHas('vendor_request_changes', [
            'id' => $pendingChange->id,
            'restaurant_name' => 'Pending Restaurant',
            'legal_entity_name' => 'Pending GmbH',
            'business_registration_number' => 'FN000001a',
            'vat_number' => 'ATU00000001',
            'company_type' => 'GmbH',
            'country' => 'AT',
            'city' => 'Graz',
            'address' => 'Old Street 1',
            'status' => 'pending',
        ]);
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/{vendorId}/legal-info/status
    // ----------------------------------------------------------------

    public function test_can_get_legal_change_status(): void
    {
        VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_name' => 'Test',
            'legal_entity_name' => 'Test GmbH',
            'business_registration_number' => 'FN123456a',
            'vat_number' => 'ATU12345678',
            'company_type' => 'GmbH',
            'country' => 'AT',
            'city' => 'Vienna',
            'address' => 'Main Street 1',
            'status' => 'pending',
        ]);

        $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/legal-info/status",
            $this->authHeaders()
        )->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('legalInfo.restaurantName', 'Test')
            ->assertJsonPath('legalInfo.legalEntityName', 'Test GmbH')
            ->assertJsonPath('legalInfo.businessRegistrationNumber', 'FN123456a')
            ->assertJsonPath('legalInfo.vatNumber', 'ATU12345678')
            ->assertJsonPath('legalInfo.companyType', 'GmbH')
            ->assertJsonPath('legalInfo.country', 'AT')
            ->assertJsonPath('legalInfo.city', 'Vienna')
            ->assertJsonPath('legalInfo.address', 'Main Street 1');
    }

    public function test_legal_change_status_falls_back_to_current_company_type(): void
    {
        $this->vendor->vendorSetting()->create([
            'company_type' => 'GmbH',
        ]);

        VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'legal_entity_name' => 'Test GmbH',
            'business_registration_number' => 'FN123456a',
            'vat_number' => 'ATU12345678',
            'company_type' => null,
            'status' => 'pending',
        ]);

        $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/legal-info/status",
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonPath('legalInfo.companyType', 'GmbH');
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
