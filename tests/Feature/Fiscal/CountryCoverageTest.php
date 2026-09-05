<?php

namespace Tests\Feature\Fiscal;

use App\Models\FiscalDevice;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\StubFiscalProvider;
use Tests\TestCase;

/**
 * Austria is the only country that asks anything of the merchant. Every other
 * country fiskaly supports registers on admin approval alone, and adding one
 * should be configuration rather than a change to the flow.
 */
class CountryCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.fiskaly.enabled' => true,
            'services.fiskaly.api_key' => 'test-key',
            'services.fiskaly.api_secret' => 'test-secret',
        ]);

        $role = Role::create(['name' => 'admin', 'label' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    private function vendor(string $country): Vendor
    {
        $vendor = Vendor::factory()->create([
            'country' => $country,
            'vat_number' => 'VAT123',
            'slug' => 'vendor-'.strtolower($country),
        ]);
        VendorSetting::factory()->create([
            'vendor_id' => $vendor->id,
            'is_live_and_discoverable' => false,
        ]);

        return $vendor;
    }

    private function pendingChange(Vendor $vendor): VendorRequestChange
    {
        return VendorRequestChange::create([
            'vendor_id' => $vendor->id,
            'legal_entity_name' => 'Chez Tavlo SARL',
            'business_registration_number' => '123456789',
            'vat_number' => 'VAT123',
            'country' => $vendor->country,
            'city' => 'Lyon',
            'address' => '1 Rue de la Paix',
            'status' => 'pending',
        ]);
    }

    private function approve(Vendor $vendor, VendorRequestChange $change): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post("/admin/vendor/{$vendor->slug}/changes/{$change->id}/approve");
    }

    public function test_a_newly_supported_country_registers_on_approval_with_no_merchant_input(): void
    {
        // Everything needed to add a country: the country list, a provider and
        // a base URL. No code path branches on it.
        config([
            'services.fiskaly.countries' => ['AT', 'DE', 'FR'],
            'services.fiskaly.providers.FR' => StubFiscalProvider::class,
            'services.fiskaly.fr.base_url' => 'https://test.api.fiskaly.com',
        ]);

        $vendor = $this->vendor('FR');
        Http::fake();

        $this->getJson("/api/vendor/{$vendor->id}/fiscal/status", [
            'Authorization' => 'Bearer '.$vendor->createToken('t')->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('required', true)
            // No FinanzOnline equivalent, so no second activation step.
            ->assertJsonPath('needsFinanzOnline', false)
            ->assertJsonPath('needsMerchantAction', false);

        $this->approve($vendor, $this->pendingChange($vendor))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            FiscalDevice::STATE_INITIALIZED,
            FiscalDevice::where('vendor_id', $vendor->id)->firstOrFail()->state,
        );
    }

    public function test_a_country_listed_without_a_provider_fails_loudly_rather_than_silently(): void
    {
        // A misconfiguration must not look like "this country needs nothing".
        config(['services.fiskaly.countries' => ['AT', 'DE', 'IT']]);

        $vendor = $this->vendor('IT');
        Http::fake();

        $change = $this->pendingChange($vendor);

        $this->approve($vendor, $change)
            ->assertRedirect()
            ->assertSessionHas(
                'warning',
                fn (string $message) => str_contains($message, 'does not support cash register registration'),
            );

        // A misconfiguration is ours, not the restaurant's: the submission is
        // held rather than sent back, and nothing is approved either way. The
        // prepared device row remains available for an operational retry once
        // a provider is configured.
        $this->assertSame('pending', $change->fresh()->status);
        $this->assertNotSame('Chez Tavlo SARL', $vendor->fresh()->legal_entity_name);
        $this->assertDatabaseHas('fiscal_devices', [
            'vendor_id' => $vendor->id,
            'state' => FiscalDevice::STATE_AWAITING_APPROVAL,
        ]);
    }

    public function test_a_country_without_a_provider_still_blocks_going_live(): void
    {
        // Better to block than to let a restaurant take orders whose receipts
        // nothing will ever sign.
        config(['services.fiskaly.countries' => ['AT', 'DE', 'IT']]);

        $vendor = $this->vendor('IT');
        VendorRequestChange::create([
            'vendor_id' => $vendor->id,
            'vat_number' => 'VAT123',
            'status' => 'approved',
        ]);

        $this->putJson(
            "/api/vendor/{$vendor->id}/settings",
            ['isLiveAndDiscoverable' => true],
            [
                'Authorization' => 'Bearer '.$vendor->createToken('t')->plainTextToken,
                'Accept' => 'application/json',
            ],
        )
            ->assertStatus(422)
            ->assertJsonPath('code', 'CASH_REGISTER_REQUIRED');
    }

    public function test_austria_remains_the_only_country_asking_the_merchant_for_credentials(): void
    {
        config([
            'services.fiskaly.countries' => ['AT', 'DE', 'FR'],
            'services.fiskaly.providers.FR' => StubFiscalProvider::class,
            'services.fiskaly.fr.base_url' => 'https://test.api.fiskaly.com',
        ]);

        foreach (['DE' => false, 'FR' => false, 'AT' => true] as $country => $expected) {
            $vendor = $this->vendor($country);

            // The auth guard caches the first user it resolves for the life of
            // the application instance, so a second request in the same test
            // would still be authenticated as the previous vendor.
            $this->app['auth']->forgetGuards();

            $this->getJson("/api/vendor/{$vendor->id}/fiscal/status", [
                'Authorization' => 'Bearer '.$vendor->createToken('t')->plainTextToken,
                'Accept' => 'application/json',
            ])
                ->assertOk()
                ->assertJsonPath('needsFinanzOnline', $expected);
        }
    }
}
