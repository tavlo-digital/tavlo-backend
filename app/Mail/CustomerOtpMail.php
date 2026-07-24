<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $code     The plaintext one-time code.
     * @param  string  $purpose  "registration" | "password_reset".
     */
    public function __construct(
        public string $code,
        public string $purpose,
    ) {}

    public function build(): self
    {
        $appName = config('app.name', 'Tavlo');
        $ttl = (int) config('services.customer_otp.ttl_minutes', 10);

        $intro = $this->purpose === 'password_reset'
            ? 'Use the code below to reset your password.'
            : 'Use the code below to verify your email address.';

        $subject = $this->purpose === 'password_reset'
            ? "{$appName} password reset code"
            : "{$appName} verification code";

        return $this
            ->subject($subject)
            ->html(<<<HTML
                <p>Hello,</p>
                <p>{$intro}</p>
                <p style="font-size:24px;font-weight:bold;letter-spacing:4px;">{$this->code}</p>
                <p>This code expires in {$ttl} minutes. If you did not request it, you can safely ignore this email.</p>
                <p>— {$appName}</p>
            HTML);
    }
}
