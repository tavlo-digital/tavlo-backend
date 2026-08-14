<?php

namespace Tests\Feature\Vendor;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards the vendor settings round-trip: every key the dashboard sends on save
 * must come back changed on the next GET. A rename on either side silently
 * drops the field, which is exactly the class of bug this test exists to catch.
 */
class VendorSettingsRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = Vendor::factory()->create();
    }

    private function vendorHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    /**
     * The payload below mirrors the object built in Settings.tsx handleSave().
     *
     * @return array<string, mixed>
     */
    private function dashboardPayload(): array
    {
        return [
            // tables tab — "Total Tables for Reservations"
            'totalTablesForReservations' => 42,
            'maxTableCapacity' => 9,
            'numberOfTables' => 15,
            'maxGuestsPerTable' => 7,
            // appearance tab
            'menuTheme' => 'minimal',
            'primaryColor' => '#123456',
            'accentColor' => '#654321',
            'menuLayout' => 'list',
            // tax tab
            'serviceFeeRate' => 4,
            'invoicePrefix' => 'ACME',
            'nextInvoiceNumber' => 2001,
            // payment tab
            'acceptOnSite' => true,
            'acceptPickupCash' => true,
            // ordering tab
            'estimatedPrepTime' => 35,
            'maxOrdersPerSlot' => 12,
            // notifications tab
            'notificationEmail' => 'alerts@example.com',
            // loyalty
            'loyaltyEnabled' => true,
            'minimumRedemptionPoints' => 555,
            'pointsExpiryDays' => 99,
            'pointsPerEuro' => 3,
            // privacy tab
            'showInTopCustomers' => true,
        ];
    }

    public function test_settings_save_round_trips_every_field(): void
    {
        $response = $this->putJson(
            "/api/vendor/{$this->vendor->id}/settings",
            $this->dashboardPayload(),
            $this->vendorHeaders()
        );

        $response->assertOk();

        foreach ($this->dashboardPayload() as $key => $expected) {
            // the GET response renames this one field
            $responseKey = $key === 'totalTablesForReservations' ? 'totalTables' : $key;
            $this->assertSame(
                $expected,
                $response->json($responseKey),
                "Setting [{$key}] did not persist."
            );
        }
    }

    public function test_accept_pickup_cash_persists(): void
    {
        $this->putJson("/api/vendor/{$this->vendor->id}/settings", [
            'acceptOnSite' => true,
            'acceptPickupCash' => false,
        ], $this->vendorHeaders())->assertOk();

        $this->getJson("/api/vendor/{$this->vendor->id}/settings", $this->vendorHeaders())
            ->assertOk()
            ->assertJsonPath('acceptPickupCash', false);
    }

    public function test_disabling_on_site_clears_pickup_cash(): void
    {
        $this->putJson("/api/vendor/{$this->vendor->id}/settings", [
            'acceptOnSite' => false,
            'acceptPickupCash' => true,
        ], $this->vendorHeaders())
            ->assertOk()
            ->assertJsonPath('acceptPickupCash', false);
    }

    public function test_background_image_uploads_and_can_be_cleared(): void
    {
        Storage::fake('public');

        $upload = $this->postJson(
            "/api/vendor/{$this->vendor->id}/settings/background-image",
            ['background' => UploadedFile::fake()->image('bg.jpg', 1200, 800)],
            $this->vendorHeaders()
        );

        $upload->assertOk();
        $this->assertNotEmpty($upload->json('backgroundImageUrl'));

        // The stored value must be a relative path, not a full URL, so the
        // accessor can build the URL consistently with logo / cover photo.
        $this->assertStringNotContainsString(
            'http',
            $this->vendor->fresh()->vendorSetting->getRawOriginal('background_image_url')
        );

        $this->getJson("/api/vendor/{$this->vendor->id}/settings", $this->vendorHeaders())
            ->assertOk()
            ->assertJsonPath('backgroundImageUrl', $upload->json('backgroundImageUrl'));

        // Removing the image in the dashboard sends an explicit null.
        $this->putJson("/api/vendor/{$this->vendor->id}/settings", [
            'backgroundImageUrl' => null,
        ], $this->vendorHeaders())
            ->assertOk()
            ->assertJsonPath('backgroundImageUrl', null);
    }

    public function test_clearing_logo_and_cover_persists(): void
    {
        $this->vendor->vendorSetting()->updateOrCreate(
            ['vendor_id' => $this->vendor->id],
            ['logo_url' => 'vendors/1/logo/a.png', 'cover_photo_url' => 'vendors/1/cover/b.png']
        );

        $this->putJson("/api/vendor/{$this->vendor->id}/settings", [
            'logoUrl' => null,
            'coverPhotoUrl' => null,
        ], $this->vendorHeaders())
            ->assertOk()
            ->assertJsonPath('logo', null)
            ->assertJsonPath('coverPhoto', null);
    }
}
