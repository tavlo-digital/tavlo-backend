<?php

namespace Tests\Feature\Fiscal;

use App\Mail\FinanzOnlineInstructionsMail;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The "Connect your Registrierkasse" activation step.
 */
class RegistrierkasseActivationTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.fiskaly.enabled' => true,
            'services.fiskaly.api_key' => 'test-key',
            'services.fiskaly.api_secret' => 'test-secret',
            'services.fiskaly.countries' => ['AT', 'DE'],
        ]);

        $this->vendor = Vendor::factory()->create(['country' => 'AT', 'vat_number' => null]);
        VendorSetting::factory()->create(['vendor_id' => $this->vendor->id]);
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->vendor->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    /** Step one submits into the approval queue rather than onto the vendor. */
    private function submitLegalInfo(string $vat = 'ATU12345678'): void
    {
        VendorRequestChange::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_name' => 'La Bella Cucina',
            'legal_entity_name' => 'La Bella Cucina GmbH',
            'business_registration_number' => 'FN 123456 a',
            'vat_number' => $vat,
            'company_type' => 'gmbh',
            'country' => 'AT',
            'city' => 'Vienna',
            'address' => 'Mariahilfer Straße 45',
            'status' => 'pending',
        ]);
    }

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

    public function test_a_vendor_activating_for_the_first_time_is_recognised_as_austrian(): void
    {
        // During activation the country has not been approved onto the vendor
        // yet — it sits on the pending change with the rest of the legal info.
        // Reading only the vendor made a new Austrian restaurant look like it
        // needed no cash register, so no second step ever appeared.
        $this->vendor->update(['country' => null]);
        $this->submitLegalInfo();

        $this->getJson("/api/vendor/{$this->vendor->id}/fiscal/status", $this->headers())
            ->assertOk()
            ->assertJsonPath('country', 'AT')
            ->assertJsonPath('required', true)
            ->assertJsonPath('needsFinanzOnline', true)
            ->assertJsonPath('needsMerchantAction', true);
    }

    public function test_status_reports_an_austrian_vendor_as_needing_finanzonline(): void
    {
        $response = $this->getJson("/api/vendor/{$this->vendor->id}/fiscal/status", $this->headers());

        $response->assertOk()
            ->assertJsonPath('required', true)
            ->assertJsonPath('country', 'AT')
            ->assertJsonPath('needsFinanzOnline', true)
            ->assertJsonPath('connected', false)
            ->assertJsonPath('legalInfoSubmitted', false)
            ->assertJsonPath('activationComplete', false);
    }

    public function test_status_reports_a_german_vendor_as_needing_no_credentials(): void
    {
        $this->vendor->update(['country' => 'DE']);

        $this->getJson("/api/vendor/{$this->vendor->id}/fiscal/status", $this->headers())
            ->assertOk()
            ->assertJsonPath('required', true)
            ->assertJsonPath('needsFinanzOnline', false);
    }

    public function test_a_country_without_fiscalization_needs_no_second_step(): void
    {
        $this->vendor->update(['country' => 'GB']);
        $this->submitLegalInfo();

        $this->getJson("/api/vendor/{$this->vendor->id}/fiscal/status", $this->headers())
            ->assertOk()
            ->assertJsonPath('required', false)
            // Step one alone finishes activation for them.
            ->assertJsonPath('activationComplete', true);
    }

    public function test_connecting_before_legal_details_is_refused(): void
    {
        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [
            'fonParticipantId' => 'TID1234567',
            'fonUserId' => 'tavlo12345',
            'fonUserPin' => 'pin12345',
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('code', 'LEGAL_INFO_REQUIRED');
    }

    public function test_submitting_joins_the_pending_legal_change_and_calls_nothing(): void
    {
        $this->submitLegalInfo();
        Http::fake();

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [
            'fonParticipantId' => 'TID1234567',
            'fonUserId' => 'tavlo12345',
            'fonUserPin' => 'pin12345',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('submitted', true)
            ->assertJsonPath('connected', false)
            ->assertJsonPath('awaitingApproval', true);

        // The details ride along on the same approval request as the legal
        // info, rather than on a device of their own.
        $change = VendorRequestChange::where('vendor_id', $this->vendor->id)
            ->where('status', 'pending')
            ->latest()
            ->firstOrFail();
        $this->assertSame('TID1234567', $change->fon_participant_id);
        $this->assertSame('pin12345', $change->fon_user_pin);
        $this->assertTrue($change->hasFiscalDetails());

        // One request for the admin, no device and no provider call yet.
        $this->assertSame(1, VendorRequestChange::where('vendor_id', $this->vendor->id)->count());
        $this->assertDatabaseMissing('fiscal_devices', ['vendor_id' => $this->vendor->id]);
        Http::assertNothingSent();
    }

    public function test_the_pin_is_encrypted_at_rest(): void
    {
        $this->submitLegalInfo();
        Http::fake();

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [
            'fonParticipantId' => 'TID1234567',
            'fonUserId' => 'tavlo12345',
            'fonUserPin' => 'pin12345',
        ], $this->headers())->assertOk();

        $raw = DB::table('vendor_request_changes')
            ->where('vendor_id', $this->vendor->id)
            ->value('fon_user_pin');

        $this->assertNotSame('pin12345', $raw);
        $this->assertStringNotContainsString('pin12345', (string) $raw);
    }

    public function test_submitting_without_a_pending_change_opens_one(): void
    {
        // Legal details already approved onto the vendor, nothing in review.
        $this->vendor->update([
            'vat_number' => 'ATU12345678',
            'legal_entity_name' => 'La Bella Cucina GmbH',
            'business_registration_number' => 'FN 123456 a',
            'address' => 'Mariahilfer Straße 45',
        ]);
        Http::fake();

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [
            'fonParticipantId' => 'TID1234567',
            'fonUserId' => 'tavlo12345',
            'fonUserPin' => 'pin12345',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('awaitingApproval', true);

        // Changing cash register details always goes back to an admin, so a
        // request is opened carrying the vendor's current legal values.
        $change = VendorRequestChange::where('vendor_id', $this->vendor->id)
            ->where('status', 'pending')
            ->firstOrFail();
        $this->assertSame('ATU12345678', $change->vat_number);
        $this->assertSame('TID1234567', $change->fon_participant_id);
        Http::assertNothingSent();
    }

    public function test_germany_asks_nothing_of_the_vendor(): void
    {
        $this->vendor->update(['country' => 'DE']);
        $this->submitLegalInfo();
        Http::fake();

        // There is no German equivalent of the FinanzOnline user, so there is
        // no second step: registration follows the legal approval on its own.
        $this->getJson("/api/vendor/{$this->vendor->id}/fiscal/status", $this->headers())
            ->assertOk()
            ->assertJsonPath('required', true)
            ->assertJsonPath('needsFinanzOnline', false)
            ->assertJsonPath('needsMerchantAction', false)
            ->assertJsonPath('submitted', true)
            ->assertJsonPath('activationComplete', true);

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [], $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('code', 'NO_CREDENTIALS_REQUIRED');

        Http::assertNothingSent();
    }

    public function test_an_unfiscalized_country_cannot_connect_and_registers_nothing(): void
    {
        $this->vendor->update(['country' => 'GB']);
        $this->submitLegalInfo();
        Http::fake();

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [], $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Fiscalization does not apply to this restaurant.');

        // Nothing is registered anywhere: no local device, no fiskaly call.
        $this->assertDatabaseMissing('fiscal_devices', ['vendor_id' => $this->vendor->id]);
        Http::assertNothingSent();
    }

    public function test_credentials_are_validated_to_finanzonline_rules(): void
    {
        $this->submitLegalInfo();

        // fiskaly's schema bounds the Teilnehmer-Identifikation to 8–12, so
        // both ends have to fail in the form rather than at approval time.
        foreach (['234234234234c', '1234567'] as $badTid) {
            $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [
                'fonParticipantId' => $badTid,
                'fonUserId' => 'tavlo12345',
                'fonUserPin' => 'pin12345',
            ], $this->headers())
                ->assertStatus(422)
                ->assertJsonValidationErrors('fonParticipantId');
        }

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [
            'fonParticipantId' => 'TID1234567',
            'fonUserId' => 'short1',        // under 8 characters
            'fonUserPin' => 'pin12345',
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('fonUserId');

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [
            'fonParticipantId' => 'TID1234567',
            'fonUserId' => 'allletters',    // no digit
            'fonUserPin' => 'pin12345',
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('fonUserId');
    }

    public function test_resubmitting_updates_the_same_pending_request(): void
    {
        $this->submitLegalInfo();
        Http::fake();

        $body = [
            'fonParticipantId' => 'TID1234567',
            'fonUserId' => 'tavlo12345',
            'fonUserPin' => 'pin12345',
        ];

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", $body, $this->headers())
            ->assertOk();

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [
            ...$body,
            'fonUserPin' => 'corrected1',
        ], $this->headers())->assertOk();

        // A correction before approval updates the request in place rather than
        // queuing a second one for the admin.
        $this->assertSame(1, VendorRequestChange::where('vendor_id', $this->vendor->id)->count());
        $this->assertSame(
            'corrected1',
            VendorRequestChange::where('vendor_id', $this->vendor->id)->firstOrFail()->fon_user_pin,
        );
    }

    public function test_the_vendors_part_is_complete_once_submitted_even_before_approval(): void
    {
        $this->submitLegalInfo();
        Http::fake();

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/connect", [
            'fonParticipantId' => 'TID1234567',
            'fonUserId' => 'tavlo12345',
            'fonUserPin' => 'pin12345',
        ], $this->headers())->assertOk();

        $this->getJson("/api/vendor/{$this->vendor->id}/fiscal/status", $this->headers())
            ->assertOk()
            ->assertJsonPath('submitted', true)
            ->assertJsonPath('awaitingApproval', true)
            ->assertJsonPath('connected', false)
            // Waiting on a Tavlo admin is not the vendor's to chase.
            ->assertJsonPath('activationComplete', true);
    }

    public function test_a_rejected_first_submission_returns_the_vendor_to_legal_details(): void
    {
        $this->vendor->update(['country' => null]);
        $this->submitLegalInfo();

        VendorRequestChange::where('vendor_id', $this->vendor->id)->update([
            'status' => 'rejected',
            'admin_notes' => 'The FinanzOnline details were rejected.',
        ]);

        $this->getJson("/api/vendor/{$this->vendor->id}/fiscal/status", $this->headers())
            ->assertOk()
            // The rejected country still determines which setup flow applies.
            ->assertJsonPath('country', 'AT')
            ->assertJsonPath('required', true)
            ->assertJsonPath('needsFinanzOnline', true)
            // Rejection means step one is not complete and cannot be skipped.
            ->assertJsonPath('legalInfoSubmitted', false)
            ->assertJsonPath('activationComplete', false);
    }

    public function test_instructions_can_be_emailed_to_an_accountant(): void
    {
        Mail::fake();

        $this->postJson("/api/vendor/{$this->vendor->id}/fiscal/send-instructions", [
            'email' => 'accountant@example.com',
            'name' => 'Frau Huber',
        ], $this->headers())->assertOk();

        Mail::assertSent(
            FinanzOnlineInstructionsMail::class,
            fn ($mail) => $mail->hasTo('accountant@example.com'),
        );
    }

    public function test_another_vendor_cannot_read_or_connect(): void
    {
        $other = Vendor::factory()->create(['country' => 'AT']);

        $this->getJson("/api/vendor/{$other->id}/fiscal/status", $this->headers())
            ->assertForbidden();

        $this->postJson("/api/vendor/{$other->id}/fiscal/connect", [
            'fonParticipantId' => 'TID1234567',
            'fonUserId' => 'tavlo12345',
            'fonUserPin' => 'pin12345',
        ], $this->headers())->assertForbidden();
    }
}
