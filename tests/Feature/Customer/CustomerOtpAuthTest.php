<?php

namespace Tests\Feature\Customer;

use App\Mail\CustomerOtpMail;
use App\Models\Customer;
use App\Models\CustomerOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerOtpAuthTest extends TestCase
{
    use RefreshDatabase;

    // ----------------------------------------------------------------
    // Registration OTP
    // ----------------------------------------------------------------

    public function test_register_creates_unverified_customer_and_sends_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/customer/register', [
            'first_name'            => 'John',
            'last_name'             => 'Doe',
            'phone'                 => '+43123456789',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()->assertJsonStructure(['user', 'token']);

        $customer = Customer::firstWhere('email', 'john@example.com');
        $this->assertNull($customer->email_verified_at);

        $this->assertDatabaseHas('customer_otps', [
            'email'   => 'john@example.com',
            'purpose' => CustomerOtp::PURPOSE_REGISTRATION,
        ]);

        Mail::assertSent(CustomerOtpMail::class, fn ($mail) => $mail->hasTo('john@example.com'));
    }

    public function test_customer_can_verify_email_with_otp(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create([
            'email'             => 'jane@example.com',
            'email_verified_at' => null,
        ]);
        $code = $this->issueOtp('jane@example.com', CustomerOtp::PURPOSE_REGISTRATION);

        $response = $this->postJson('/api/customer/register/verify-otp', [
            'email' => 'jane@example.com',
            'otp'   => $code,
        ]);

        $response->assertOk()->assertJsonPath('message', 'Email address verified.');
        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_verify_email_rejects_wrong_otp(): void
    {
        Customer::factory()->create([
            'email'             => 'jane@example.com',
            'email_verified_at' => null,
        ]);
        $this->issueOtp('jane@example.com', CustomerOtp::PURPOSE_REGISTRATION);

        $this->postJson('/api/customer/register/verify-otp', [
            'email' => 'jane@example.com',
            'otp'   => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors(['otp']);
    }

    public function test_resend_registration_otp_is_generic(): void
    {
        Mail::fake();

        Customer::factory()->create([
            'email'             => 'jane@example.com',
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/customer/register/resend-otp', [
            'email' => 'jane@example.com',
        ])->assertOk();

        Mail::assertSent(CustomerOtpMail::class);
    }

    // ----------------------------------------------------------------
    // Forgot / reset password
    // ----------------------------------------------------------------

    public function test_forgot_password_sends_otp_for_existing_account(): void
    {
        Mail::fake();

        Customer::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/customer/password/forgot', [
            'email' => 'reset@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('customer_otps', [
            'email'   => 'reset@example.com',
            'purpose' => CustomerOtp::PURPOSE_PASSWORD_RESET,
        ]);
        Mail::assertSent(CustomerOtpMail::class, fn ($mail) => $mail->hasTo('reset@example.com'));
    }

    public function test_forgot_password_does_not_leak_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/api/customer/password/forgot', [
            'email' => 'nobody@example.com',
        ])->assertOk()->assertJsonPath(
            'message',
            'If an account exists for this email, a reset code has been sent.',
        );

        Mail::assertNothingSent();
    }

    public function test_customer_can_reset_password_with_otp(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['email' => 'reset@example.com']);
        $code = $this->issueOtp('reset@example.com', CustomerOtp::PURPOSE_PASSWORD_RESET);

        $response = $this->postJson('/api/customer/password/reset', [
            'email'                 => 'reset@example.com',
            'otp'                   => $code,
            'password'              => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('brand-new-pass', $customer->fresh()->password));

        // The code is single-use — a second reset must fail.
        $this->postJson('/api/customer/password/reset', [
            'email'                 => 'reset@example.com',
            'otp'                   => $code,
            'password'              => 'another-pass',
            'password_confirmation' => 'another-pass',
        ])->assertUnprocessable()->assertJsonValidationErrors(['otp']);
    }

    public function test_verify_password_otp_does_not_consume_it(): void
    {
        $customer = Customer::factory()->create(['email' => 'reset@example.com']);
        $code = $this->issueOtp('reset@example.com', CustomerOtp::PURPOSE_PASSWORD_RESET);

        $this->postJson('/api/customer/password/verify-otp', [
            'email' => 'reset@example.com',
            'otp'   => $code,
        ])->assertOk()->assertJsonPath('message', 'Code verified.');

        // Still usable for the actual reset since verify did not consume it.
        $this->postJson('/api/customer/password/reset', [
            'email'                 => 'reset@example.com',
            'otp'                   => $code,
            'password'              => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertOk();
    }

    public function test_reset_password_rejects_wrong_otp(): void
    {
        Customer::factory()->create(['email' => 'reset@example.com']);
        $this->issueOtp('reset@example.com', CustomerOtp::PURPOSE_PASSWORD_RESET);

        $this->postJson('/api/customer/password/reset', [
            'email'                 => 'reset@example.com',
            'otp'                   => '000000',
            'password'              => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertUnprocessable()->assertJsonValidationErrors(['otp']);
    }

    public function test_expired_otp_is_rejected(): void
    {
        Customer::factory()->create(['email' => 'reset@example.com']);
        $code = $this->issueOtp('reset@example.com', CustomerOtp::PURPOSE_PASSWORD_RESET);

        CustomerOtp::where('email', 'reset@example.com')->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/customer/password/reset', [
            'email'                 => 'reset@example.com',
            'otp'                   => $code,
            'password'              => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertUnprocessable()->assertJsonValidationErrors(['otp']);
    }

    /**
     * Insert an OTP row directly with a known plaintext code and return it.
     */
    protected function issueOtp(string $email, string $purpose): string
    {
        $code = '123456';

        CustomerOtp::create([
            'email'        => $email,
            'purpose'      => $purpose,
            'code_hash'    => Hash::make($code),
            'attempts'     => 0,
            'expires_at'   => now()->addMinutes(10),
            'last_sent_at' => now(),
        ]);

        return $code;
    }
}
