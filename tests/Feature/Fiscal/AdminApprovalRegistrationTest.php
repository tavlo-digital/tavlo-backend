<?php

namespace Tests\Feature\Fiscal;

use App\Http\Controllers\Admin\VendorController;
use App\Models\FiscalDevice;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use ReflectionClass;
use Tests\TestCase;

/**
 * Registration is triggered by the admin approving the legal details, because
 * the VAT number it registers against is only trustworthy once approved.
 */
class AdminApprovalRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fiskaly.enabled' => true,
            'services.fiskaly.api_key' => 'test-key',
            'services.fiskaly.api_secret' => 'test-secret',
            'services.fiskaly.countries' => ['AT', 'DE'],
            // Existing product-flow tests stay focused on SIGN. The dedicated
            // Unit test below turns Management provisioning on explicitly.
            'services.fiskaly.managed_organization_countries' => [],
        ]);

        // Http::fake() with patterns lets unmatched URLs through to the real
        // network. Nothing here should ever reach fiskaly for real.
        Http::preventStrayRequests();

        $role = Role::create(['name' => 'admin', 'label' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
        $this->vendor = Vendor::factory()->create([
            'country' => 'AT',
            'vat_number' => null,
            'slug' => 'la-bella-cucina',
        ]);
        VendorSetting::factory()->create(['vendor_id' => $this->vendor->id]);
    }

    /**
     * Cash register details ride on the same approval request as the legal
     * info, which is what the vendor endpoint writes.
     */
    private function pendingChange(string $vat = 'ATU12345678', bool $withFiscal = true): VendorRequestChange
    {
        return VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_name' => 'La Bella Cucina',
            'legal_entity_name' => 'La Bella Cucina GmbH',
            'business_registration_number' => 'FN 123456 a',
            'vat_number' => $vat,
            'company_type' => 'gmbh',
            'country' => 'AT',
            'city' => 'Vienna',
            'postal_code' => '1060',
            'address' => 'Mariahilfer Straße 45',
            'status' => 'pending',
            ...($withFiscal ? [
                'fon_participant_id' => 'TID1234567',
                'fon_user_id' => 'tavlo12345',
                'fon_user_pin' => 'pin12345',
            ] : []),
        ]);
    }

    /**
     * Pattern order matters: '*&#47;auth' also matches '*&#47;fon/auth', so the more
     * specific paths come first and a catch-all closes the list.
     */
    private function fakeFiskaly(): void
    {
        Http::fake([
            '*/fon/auth' => Http::response([]),
            '*/signature-creation-unit/*' => Http::response(['state' => 'INITIALIZED']),
            '*/cash-register/*' => Http::response(['state' => 'INITIALIZED']),
            '*/tss/*' => Http::response(['admin_puk' => 'puk-123']),
            '*' => Http::response(['access_token' => 'tok']),
        ]);
    }

    /** A device left failed by the manual provisioning command. */
    private function strandedDevice(): FiscalDevice
    {
        return FiscalDevice::create([
            'vendor_id' => $this->vendor->id,
            'country' => 'AT',
            'environment' => 'sandbox',
            'signature_unit_id' => '11111111-1111-4111-8111-111111111111',
            'register_id' => '22222222-2222-4222-8222-222222222222',
            'serial_number' => 'TAVLO-TEST',
            'state' => FiscalDevice::STATE_FAILED,
            'last_error' => 'fiskaly rejected the registration.',
            'credentials' => [
                'fon_participant_id' => 'TID1234567',
                'fon_user_id' => 'tavlo12345',
                'fon_user_pin' => 'pin12345',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function cashRegisterCard(): array
    {
        $controller = new VendorController;
        $method = (new ReflectionClass($controller))->getMethod('getCashRegister');

        return $method->invoke($controller, $this->vendor->fresh());
    }

    private function approve(VendorRequestChange $change): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post("/admin/vendor/{$this->vendor->slug}/changes/{$change->id}/approve");
    }

    public function test_the_admin_card_shows_a_submission_before_any_device_exists(): void
    {
        $this->pendingChange();

        $card = $this->cashRegisterCard();

        // The device row only appears once registration is attempted, so
        // reading it alone reported "not submitted" while the vendor's details
        // sat in the diff right below it.
        $this->assertSame(FiscalDevice::STATE_AWAITING_APPROVAL, $card['state']);
        $this->assertNotNull($card['submittedAt']);
        $this->assertFalse($card['canRetry']);
        $this->assertDatabaseMissing('fiscal_devices', ['vendor_id' => $this->vendor->id]);
    }

    public function test_the_admin_card_reports_nothing_submitted_when_nothing_is(): void
    {
        $this->assertSame('not_submitted', $this->cashRegisterCard()['state']);
    }

    public function test_the_admin_card_offers_a_retry_for_a_stranded_registration(): void
    {
        $this->approve($this->pendingChange(withFiscal: false));
        $this->strandedDevice();

        $card = $this->cashRegisterCard();
        $this->assertSame(FiscalDevice::STATE_FAILED, $card['state']);
        $this->assertTrue($card['canRetry']);
        $this->assertNotNull($card['lastError']);
    }

    public function test_the_admin_card_shows_the_latest_rejection_instead_of_a_stale_device_error(): void
    {
        $device = $this->strandedDevice();
        $device->forceFill([
            'last_error' => 'Old VAT validation error.',
            'last_attempted_at' => now()->subMinute(),
        ])->save();

        $change = $this->pendingChange();
        $change->forceFill([
            'status' => 'rejected',
            'admin_notes' => 'fiskaly rejected the registration. E_SCU_LIMIT_REACHED.',
            'reviewed_at' => now(),
        ])->save();

        $card = $this->cashRegisterCard();

        $this->assertStringContainsString('E_SCU_LIMIT_REACHED', $card['lastError']);
        $this->assertStringNotContainsString('Old VAT validation error', $card['lastError']);
    }

    public function test_a_corrected_resubmission_hides_the_previous_device_failure(): void
    {
        $this->strandedDevice();
        $this->pendingChange();

        $card = $this->cashRegisterCard();

        $this->assertSame(FiscalDevice::STATE_AWAITING_APPROVAL, $card['state']);
        $this->assertNull($card['lastError']);
        $this->assertFalse($card['canRetry']);
    }

    public function test_approval_registers_the_cash_register_with_the_approved_vat_number(): void
    {
        $change = $this->pendingChange('ATU99999999');
        $this->fakeFiskaly();

        $this->approve($change)->assertRedirect()->assertSessionHas('success');

        $device = FiscalDevice::where('vendor_id', $this->vendor->id)->firstOrFail();
        $this->assertSame(FiscalDevice::STATE_INITIALIZED, $device->state);
        $this->assertNotNull($device->initialized_at);
        $this->assertNotNull($device->last_attempted_at);

        // The number registered is the one the admin just approved onto the
        // vendor, not whatever was typed during activation.
        $this->assertSame('ATU99999999', $this->vendor->fresh()->vat_number);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'signature-creation-unit')
            && ($request['legal_entity_id']['vat_id'] ?? null) === 'ATU99999999');
    }

    public function test_approval_creates_a_managed_unit_and_uses_its_encrypted_key_for_sign(): void
    {
        config(['services.fiskaly.managed_organization_countries' => ['AT', 'DE']]);
        $change = $this->pendingChange();

        Http::fake(function ($request) {
            $url = $request->url();
            $method = $request->method();

            if ($url === 'https://dashboard.fiskaly.com/api/v0/auth') {
                return Http::response([
                    'access_token' => 'management-token',
                    'access_token_claims' => [
                        'env' => 'TEST',
                        'organization_id' => '33333333-3333-4333-8333-333333333333',
                    ],
                ]);
            }

            if (parse_url($url, PHP_URL_PATH) === '/api/v0/organizations' && $method === 'GET') {
                return Http::response(['data' => [], 'count' => 0]);
            }

            if (str_ends_with((string) parse_url($url, PHP_URL_PATH), '/api-keys') && $method === 'GET') {
                return Http::response(['data' => [], 'count' => 0]);
            }

            if (str_ends_with($url, '/organizations') && $method === 'POST') {
                return Http::response(['_id' => '44444444-4444-4444-8444-444444444444']);
            }

            if (str_ends_with($url, '/api-keys') && $method === 'POST') {
                return Http::response([
                    '_id' => '55555555-5555-4555-8555-555555555555',
                    'key' => 'managed-unit-key',
                    'secret' => 'managed-unit-secret',
                ]);
            }

            if (str_ends_with($url, '/fon/auth')) {
                return Http::response([]);
            }

            if (str_contains($url, '/signature-creation-unit/')
                || str_contains($url, '/cash-register/')) {
                return Http::response(['state' => 'INITIALIZED']);
            }

            if (str_ends_with($url, '/auth')) {
                return Http::response(['access_token' => 'unit-sign-token']);
            }

            return Http::response([], 404);
        });

        $this->approve($change)->assertSessionHas('success');

        $device = FiscalDevice::where('vendor_id', $this->vendor->id)->firstOrFail();
        $this->assertSame('44444444-4444-4444-8444-444444444444', $device->fiskaly_organization_id);
        $this->assertSame('55555555-5555-4555-8555-555555555555', $device->fiskaly_api_key_id);
        $this->assertSame('managed-unit-key', $device->credentials['fiskaly_api_key']);
        $this->assertSame('managed-unit-secret', $device->credentials['fiskaly_api_secret']);
        $this->assertStringNotContainsString(
            'managed-unit-secret',
            (string) $device->getRawOriginal('credentials'),
        );

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/organizations')
            && $request->method() === 'POST'
            && ($request['managed_by_organization_id'] ?? null) === '33333333-3333-4333-8333-333333333333'
            && ($request['zip'] ?? null) === '1060');
        Http::assertSent(fn ($request) => $request->url() === 'https://rksv.fiskaly.com/api/v1/auth'
            && ($request['api_key'] ?? null) === 'managed-unit-key'
            && ($request['api_secret'] ?? null) === 'managed-unit-secret');
    }

    public function test_a_rejected_registration_rejects_the_whole_change(): void
    {
        $change = $this->pendingChange();
        Http::fake([
            '*/fon/auth' => Http::response([
                'code' => 'E_FAILED_SCHEMA_VALIDATION',
                'message' => 'body.fon_participant_id should NOT be shorter than 8 characters',
            ], 400),
            '*' => Http::response(['access_token' => 'tok']),
        ]);

        $this->approve($change)->assertRedirect()->assertSessionHas('warning');

        // Nothing was approved: a register the tax office will not accept means
        // the details behind it are wrong.
        $change->refresh();
        $this->assertSame('rejected', $change->status);
        $this->assertStringContainsString('shorter than 8 characters', (string) $change->admin_notes);

        // The vendor record is untouched, so it cannot go live. The prepared
        // ids remain locally so a correction resumes the same remote resources.
        $this->assertNull($this->vendor->fresh()->vat_number);
        $this->assertDatabaseHas('fiscal_devices', [
            'vendor_id' => $this->vendor->id,
            'state' => FiscalDevice::STATE_AWAITING_APPROVAL,
        ]);
    }

    public function test_our_own_outage_does_not_cost_the_restaurant_its_submission(): void
    {
        $change = $this->pendingChange();
        // A bad API key of ours, not anything the restaurant sent.
        Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);

        $this->approve($change)->assertRedirect()->assertSessionHas('warning');

        $change->refresh();
        $this->assertSame('pending', $change->status, 'the submission must survive our own failure');
        $this->assertNull($change->admin_notes);
        $this->assertNull($this->vendor->fresh()->vat_number);
    }

    public function test_a_provider_outage_leaves_the_change_pending(): void
    {
        $change = $this->pendingChange();
        Http::fake([
            '*/fon/auth' => Http::response(['error' => 'gateway'], 503),
            '*' => Http::response(['access_token' => 'tok']),
        ]);

        $this->approve($change)->assertRedirect()->assertSessionHas('warning');

        $this->assertSame('pending', $change->fresh()->status);
    }

    public function test_the_pin_survives_a_failed_approval_so_the_retry_can_use_it(): void
    {
        $change = $this->pendingChange();
        Http::fake([
            '*/fon/auth' => Http::response(['error' => 'gateway'], 503),
            '*' => Http::response(['access_token' => 'tok']),
        ]);

        $this->approve($change);

        // The rollback has to take the PIN clearing with it, or approving again
        // would register with no credentials.
        $this->assertSame('pin12345', $change->fresh()->fon_user_pin);
    }

    public function test_approving_again_after_a_transient_failure_succeeds(): void
    {
        $change = $this->pendingChange();
        // One attempt per approval: the client retries 5xx by itself, which
        // would otherwise consume both steps of this sequence in one go.
        config(['services.fiskaly.retries' => 1]);
        Http::fake([
            '*/fon/auth' => Http::sequence()
                ->push(['error' => 'gateway'], 503)
                ->push([], 200),
            '*/signature-creation-unit/*' => Http::response(['state' => 'INITIALIZED']),
            '*/cash-register/*' => Http::response(['state' => 'INITIALIZED']),
            '*' => Http::response(['access_token' => 'tok']),
        ]);

        $this->approve($change)->assertSessionHas('warning');
        $this->approve($change->fresh())->assertSessionHas('success');

        $this->assertSame('approved', $change->fresh()->status);
        $this->assertSame('ATU12345678', $this->vendor->fresh()->vat_number);
        $this->assertSame(
            FiscalDevice::STATE_INITIALIZED,
            FiscalDevice::where('vendor_id', $this->vendor->id)->firstOrFail()->state,
        );
    }

    public function test_the_admin_is_told_what_fiskaly_actually_rejected(): void
    {
        $change = $this->pendingChange();
        Http::fake([
            '*/fon/auth' => Http::response([
                'code' => 'E_FAILED_SCHEMA_VALIDATION',
                'status_code' => 400,
                'message' => 'body.fon_participant_id should NOT be longer than 12 characters',
                'error' => 'Bad Request',
            ], 400),
            '*' => Http::response(['access_token' => 'tok']),
        ]);

        $this->approve($change);

        // "fiskaly rejected the request" on its own tells nobody anything. The
        // provider's own words have to reach the restaurant, which reads them
        // as the reason its submission came back.
        $reason = (string) $change->fresh()->admin_notes;

        $this->assertStringContainsString('E_FAILED_SCHEMA_VALIDATION', $reason);
        $this->assertStringContainsString('longer than 12 characters', $reason);
    }

    public function test_a_configuration_failure_names_the_missing_credentials(): void
    {
        config(['services.fiskaly.api_key' => '', 'services.fiskaly.api_secret' => '']);
        $change = $this->pendingChange();
        Http::fake();

        // Our missing credentials are not the restaurant's fault, so the
        // submission is held rather than sent back.
        $this->approve($change)
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'credentials are configured'));

        $this->assertSame('pending', $change->fresh()->status);
    }

    /**
     * Approval rolls a failure back, so a stranded device comes from the manual
     * path — `php artisan fiskaly:provision` — which is what this covers.
     */
    public function test_the_admin_can_retry_a_stranded_registration(): void
    {
        $this->approve($this->pendingChange(withFiscal: false));
        $failed = $this->strandedDevice();

        $this->fakeFiskaly();
        $this->actingAs($this->admin)
            ->post("/admin/vendor/{$this->vendor->slug}/fiscal/retry")
            ->assertRedirect()
            ->assertSessionHas('success');

        $retried = FiscalDevice::where('vendor_id', $this->vendor->id)->firstOrFail();
        $this->assertSame(FiscalDevice::STATE_INITIALIZED, $retried->state);
        // The ids are chosen and stored before the first network call, so the
        // retry reuses them and no duplicate register exists at fiskaly.
        $this->assertSame($failed->signature_unit_id, $retried->signature_unit_id);
        $this->assertSame($failed->register_id, $retried->register_id);
    }

    public function test_approval_registers_nothing_while_the_vendor_owes_credentials(): void
    {
        // Austria cannot be registered without the FinanzOnline user, so an
        // approval that carries none waits rather than failing.
        $change = $this->pendingChange(withFiscal: false);
        Http::fake();

        $this->approve($change)->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('fiscal_devices', ['vendor_id' => $this->vendor->id]);
        Http::assertNothingSent();
    }

    public function test_germany_registers_on_approval_with_nothing_from_the_vendor(): void
    {
        $this->vendor->update(['country' => 'DE']);
        $change = $this->pendingChange(withFiscal: false);
        $change->update(['country' => 'DE']);
        $this->fakeFiskaly();

        $this->approve($change)->assertRedirect()->assertSessionHas('success');

        $this->assertSame(
            FiscalDevice::STATE_INITIALIZED,
            FiscalDevice::where('vendor_id', $this->vendor->id)->firstOrFail()->state,
        );
    }

    public function test_the_pin_is_dropped_from_the_approval_history_once_used(): void
    {
        $change = $this->pendingChange();
        $this->fakeFiskaly();

        $this->approve($change);

        // The device holds it now; keeping it in the audit trail as well would
        // be a second copy of a live tax-office secret.
        $this->assertNull($change->fresh()->fon_user_pin);
        $this->assertSame(
            'pin12345',
            FiscalDevice::where('vendor_id', $this->vendor->id)->firstOrFail()->credentials['fon_user_pin'],
        );
    }

    public function test_approval_registers_nothing_for_an_unfiscalized_country(): void
    {
        $this->vendor->update(['country' => 'GB']);
        $change = $this->pendingChange(withFiscal: false);
        $change->update(['country' => 'GB']);
        Http::fake();

        $this->approve($change)->assertRedirect();

        Http::assertNothingSent();
    }

    public function test_approving_a_second_time_does_not_re_register(): void
    {
        $change = $this->pendingChange();
        $this->fakeFiskaly();

        $this->approve($change);
        $callsAfterFirst = count(Http::recorded());

        // A later legal change on an already-registered vendor must not create
        // a second cash register.
        $second = $this->pendingChange('ATU11111111', withFiscal: false);
        $this->approve($second)->assertRedirect();

        $this->assertSame($callsAfterFirst, count(Http::recorded()));
        $this->assertSame(
            FiscalDevice::STATE_INITIALIZED,
            FiscalDevice::where('vendor_id', $this->vendor->id)->firstOrFail()->state,
        );
    }
}
