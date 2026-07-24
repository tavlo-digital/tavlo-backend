<?php

namespace App\Services;

use App\Models\CustomerOtp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Issues and verifies short numeric one-time passwords (OTPs) delivered to a
 * customer's email address. Codes are stored hashed, single-use, time-limited
 * and attempt-limited.
 */
class OtpService
{
    /**
     * Generate a fresh OTP for the given email + purpose and return the
     * plaintext code so the caller can deliver it. Any previous unconsumed
     * codes for the same email/purpose are invalidated first.
     *
     * @throws ValidationException when the resend cooldown has not elapsed.
     */
    public function issue(string $email, string $purpose): string
    {
        $email = $this->normalizeEmail($email);
        $cooldown = (int) config('services.customer_otp.resend_cooldown_seconds', 60);

        $latest = CustomerOtp::where('email', $email)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if ($latest && $latest->last_sent_at
            && $latest->last_sent_at->gt(Carbon::now()->subSeconds($cooldown))) {
            $wait = max(1, $cooldown - (int) $latest->last_sent_at->diffInSeconds(Carbon::now()));

            throw ValidationException::withMessages([
                'email' => ["Please wait {$wait} seconds before requesting another code."],
            ]);
        }

        // Invalidate any outstanding codes so only the newest one is usable.
        CustomerOtp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => Carbon::now()]);

        $code = $this->generateCode();
        $ttl = (int) config('services.customer_otp.ttl_minutes', 10);

        CustomerOtp::create([
            'email'        => $email,
            'purpose'      => $purpose,
            'code_hash'    => Hash::make($code),
            'attempts'     => 0,
            'expires_at'   => Carbon::now()->addMinutes($ttl),
            'last_sent_at' => Carbon::now(),
        ]);

        return $code;
    }

    /**
     * Verify a submitted code. When $consume is true a correct code is marked
     * used so it cannot be replayed.
     *
     * @throws ValidationException with an `otp` error when the code is missing,
     *                             expired, wrong, or has been exhausted.
     */
    public function verify(string $email, string $purpose, string $code, bool $consume = true): void
    {
        $email = $this->normalizeEmail($email);

        $otp = CustomerOtp::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || $otp->isExpired()) {
            $this->fail();
        }

        $maxAttempts = (int) config('services.customer_otp.max_attempts', 5);

        if ($otp->attempts >= $maxAttempts) {
            // Too many wrong guesses — burn the code and force a resend.
            $otp->update(['consumed_at' => Carbon::now()]);
            $this->fail('Too many incorrect attempts. Please request a new code.');
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $this->fail();
        }

        if ($consume) {
            $otp->update(['consumed_at' => Carbon::now()]);
        }
    }

    protected function generateCode(): string
    {
        $length = (int) config('services.customer_otp.length', 6);
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    protected function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * @throws ValidationException
     */
    protected function fail(string $message = 'The provided code is invalid or has expired.'): never
    {
        throw ValidationException::withMessages([
            'otp' => [$message],
        ]);
    }
}
