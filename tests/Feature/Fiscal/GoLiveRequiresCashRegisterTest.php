<?php

namespace Tests\Feature\Fiscal;

use App\Models\FiscalDevice;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * No restaurant goes live without a registered cash register. Approval of the
 * legal details can succeed while registration fails, so approval alone is not
 * enough of a gate.
 */
class GoLiveRequiresCashRegisterTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fiskaly.enabled' => true,
            'services.fiskaly.api_key' => 'test-key',
            'services.fiskaly.api_secret' => 'test-secret',
            'services.fiskaly.countries' => ['AT', 'DE'],
        ]);

        $this->vendor = Vendor::factory()->create(['country' => 'AT', 'vat_number' => 'ATU12345678']);
        VendorSetting::factory()->create([
            'vendor_id' => $this->vendor->id,
            'is_live_and_discoverable' => false,
        ]);

        VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'vat_number' => 'ATU12345678',
            'status' => 'approved',
        ]);
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->vendor->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    private function goLive(): TestResponse
    {
        return $this->putJson(
            "/api/vendor/{$this->vendor->id}/settings",
            ['isLiveAndDiscoverable' => true],
            $this->headers(),
        );
    }

    private function device(string $state): FiscalDevice
    {
        return FiscalDevice::create([
            'vendor_id' => $this->vendor->id,
            'country' => 'AT',
            'environment' => 'sandbox',
            'signature_unit_id' => '11111111-1111-4111-8111-111111111111',
            'register_id' => '22222222-2222-4222-8222-222222222222',
            'serial_number' => 'TAVLO-TEST',
            'state' => $state,
        ]);
    }

    public function test_going_live_is_blocked_without_a_cash_register(): void
    {
        $this->goLive()
            ->assertStatus(422)
            ->assertJsonPath('code', 'CASH_REGISTER_REQUIRED');

        $this->assertFalse((bool) $this->vendor->vendorSetting->fresh()->is_live_and_discoverable);
    }

    public function test_going_live_is_blocked_while_registration_is_only_awaiting_approval(): void
    {
        $this->device(FiscalDevice::STATE_AWAITING_APPROVAL);

        $this->goLive()->assertStatus(422)->assertJsonPath('code', 'CASH_REGISTER_REQUIRED');
    }

    public function test_going_live_is_blocked_when_registration_failed(): void
    {
        $this->device(FiscalDevice::STATE_FAILED);

        $this->goLive()->assertStatus(422)->assertJsonPath('code', 'CASH_REGISTER_REQUIRED');
    }

    public function test_going_live_is_allowed_once_the_cash_register_is_registered(): void
    {
        $this->device(FiscalDevice::STATE_INITIALIZED);

        $this->goLive()->assertOk();

        $this->assertTrue((bool) $this->vendor->vendorSetting->fresh()->is_live_and_discoverable);
    }

    public function test_an_unfiscalized_country_needs_no_cash_register_to_go_live(): void
    {
        $this->vendor->update(['country' => 'GB']);

        $this->goLive()->assertOk();

        $this->assertTrue((bool) $this->vendor->vendorSetting->fresh()->is_live_and_discoverable);
    }

    public function test_the_settings_response_explains_why_going_live_is_blocked(): void
    {
        // The dashboard disables the switch for the same reason the API would
        // refuse it, so the two cannot drift apart.
        $this->getJson("/api/vendor/{$this->vendor->id}/settings", $this->headers())
            ->assertOk()
            ->assertJsonPath('goLive.allowed', false)
            ->assertJsonPath('goLive.code', 'CASH_REGISTER_REQUIRED')
            ->assertJsonPath('goLive.message', fn (?string $m) => is_string($m) && $m !== '');
    }

    public function test_the_settings_response_reports_pending_legal_details(): void
    {
        VendorRequestChange::where('vendor_id', $this->vendor->id)->delete();
        VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'vat_number' => 'ATU12345678',
            'status' => 'pending',
        ]);

        $this->getJson("/api/vendor/{$this->vendor->id}/settings", $this->headers())
            ->assertOk()
            ->assertJsonPath('goLive.allowed', false)
            ->assertJsonPath('goLive.code', 'LEGAL_INFO_PENDING');
    }

    public function test_the_settings_response_allows_going_live_once_everything_is_ready(): void
    {
        $this->device(FiscalDevice::STATE_INITIALIZED);

        $this->getJson("/api/vendor/{$this->vendor->id}/settings", $this->headers())
            ->assertOk()
            ->assertJsonPath('goLive.allowed', true)
            ->assertJsonPath('goLive.code', null);
    }

    public function test_a_vendor_that_is_already_live_is_not_re_checked(): void
    {
        $this->vendor->vendorSetting->update(['is_live_and_discoverable' => true]);

        // The guard only runs on the transition into live, so an unrelated
        // settings save on a live restaurant is not blocked.
        $this->putJson(
            "/api/vendor/{$this->vendor->id}/settings",
            ['isLiveAndDiscoverable' => true, 'estimatedPrepTime' => 25],
            $this->headers(),
        )->assertOk();
    }
}
