<?php

namespace Tests\Feature\Settings;

use App\Models\Language;
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
                'slug',
                'restaurantName',
                'acceptOnSite',
                'acceptPickupCash',
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
                'availableLanguages' => [
                    '*' => ['code', 'name', 'nativeName', 'flag', 'direction'],
                ],
            ]);

        $response->assertJsonPath('slug', $this->vendor->slug);

        $payload = $response->json();
        $this->assertArrayNotHasKey('acceptCash', $payload);
        $this->assertArrayNotHasKey('acceptCard', $payload);
        $this->assertArrayNotHasKey('acceptVisa', $payload);
        $this->assertArrayNotHasKey('acceptMastercard', $payload);
        $this->assertArrayNotHasKey('acceptAmex', $payload);
        $this->assertArrayNotHasKey('acceptBankTransfer', $payload);
        $this->assertArrayNotHasKey('defaultLanguage', $payload);
        $this->assertFalse(Schema::hasColumn('vendor_settings', 'default_language'));
    }

    public function test_get_settings_requires_authentication(): void
    {
        $this->getJson("/api/vendor/{$this->vendor->vendor_public_id}/settings")
            ->assertUnauthorized();
    }

    public function test_available_languages_come_from_active_database_records(): void
    {
        Language::where('code', 'de')->update(['is_active' => false]);
        Language::create([
            'code' => 'pt',
            'name' => 'Portuguese',
            'native_name' => 'Português',
            'flag' => '🇵🇹',
            'direction' => 'ltr',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonFragment(['code' => 'pt', 'name' => 'Portuguese'])
            ->assertJsonMissing(['code' => 'de']);
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
                'acceptPickupCash' => true,
                'stripeEnabled' => true,
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonFragment([
                'acceptOnSite' => false,
                'acceptPickupCash' => false,
                'stripeEnabled' => true,
            ]);

        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
            'accept_on_site' => false,
            'accept_pickup_cash' => false,
            'stripe_enabled' => true,
        ]);
    }

    public function test_cannot_enable_pickup_cash_while_on_site_payments_are_disabled(): void
    {
        $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'acceptOnSite' => false,
                'acceptPickupCash' => false,
            ],
            $this->authHeaders()
        )->assertOk();

        $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            ['acceptPickupCash' => true],
            $this->authHeaders()
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('acceptPickupCash');

        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
            'accept_on_site' => false,
            'accept_pickup_cash' => false,
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

    public function test_can_update_supported_date_and_time_formats(): void
    {
        $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'dateFormat' => 'MM/DD/YYYY',
                'timeFormat' => '12h',
            ],
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonPath('dateFormat', 'MM/DD/YYYY')
            ->assertJsonPath('timeFormat', '12h');

        $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'dateFormat' => 'unsupported',
                'timeFormat' => '25h',
            ],
            $this->authHeaders()
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dateFormat', 'timeFormat']);
    }

    public function test_language_settings_keep_english_enabled_without_a_vendor_default(): void
    {
        $response = $this->putJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings",
            [
                'dashboardLanguage' => 'de',
                'supportedLanguages' => ['it'],
            ],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('dashboardLanguage', 'de')
            ->assertJsonPath('supportedLanguages.0', 'en')
            ->assertJsonPath('supportedLanguages.1', 'it');

        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $this->vendor->id,
            'dashboard_language' => 'de',
        ]);
        $this->assertArrayNotHasKey('defaultLanguage', $response->json());
        $this->assertFalse(Schema::hasColumn('vendor_settings', 'default_language'));
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
                'postalCode' => '1010',
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
                'postalCode',
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
            'postal_code' => '1010',
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
            ->assertJsonPath('legalInfo.postalCode', '1010')
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

    public function test_upload_cover_photo_accepts_16_9_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('cover.jpg', 1600, 900);

        $response = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings/cover-photo",
            ['cover' => $file],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonStructure(['coverPhotoUrl']);

        $this->assertNotEmpty($response->json('coverPhotoUrl'));
    }

    public function test_upload_cover_photo_rejects_non_16_9_ratio(): void
    {
        Storage::fake('public');

        // 4:3 image at sufficient resolution — wrong aspect ratio.
        $file = UploadedFile::fake()->image('cover.jpg', 1600, 1200);

        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings/cover-photo",
            ['cover' => $file],
            $this->authHeaders()
        )->assertStatus(422)
            ->assertJsonValidationErrors('cover');
    }

    public function test_upload_cover_photo_rejects_image_below_minimum_size(): void
    {
        Storage::fake('public');

        // Correct 16:9 ratio but below the 1200×675 minimum.
        $file = UploadedFile::fake()->image('cover.jpg', 800, 450);

        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/settings/cover-photo",
            ['cover' => $file],
            $this->authHeaders()
        )->assertStatus(422)
            ->assertJsonValidationErrors('cover');
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
