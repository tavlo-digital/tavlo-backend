<?php

namespace App\Services;

use App\Models\Vendor;
use Closure;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class StripeConnectService
{
    private const ACCOUNTS_V2_ADVISORY = 'We recommend building your integration using Accounts v2.';

    private ?StripeClient $stripe = null;

    /**
     * Create a Stripe Express account for the vendor and return the account ID.
     */
    public function createExpressAccount(Vendor $vendor): string
    {
        $settingsVersion = $vendor->vendorSetting?->updated_at?->format('Uv')
            ?? $vendor->created_at?->format('Uv')
            ?? 'initial';

        $account = $this->stripeRequest(fn () => $this->stripe()->accounts->create([
            'type' => 'express',
            'email' => $vendor->email,
            'business_profile' => [
                'name' => $vendor->restaurant_name ?? $vendor->name,
            ],
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'vendor_id' => (string) $vendor->id,
            ],
        ], [
            // Replaying a request after a lost response must return the same
            // connected account rather than create a duplicate. Disconnecting
            // updates the settings timestamp and therefore starts a new attempt.
            'idempotency_key' => hash('sha256', "tavlo-stripe-connect:{$vendor->id}:{$settingsVersion}"),
        ]));

        return $account->id;
    }

    /**
     * Create an onboarding link for the Stripe Express account.
     */
    public function createOnboardingLink(
        string $stripeAccountId,
        string $refreshUrl,
        string $returnUrl
    ): string {
        $link = $this->stripeRequest(fn () => $this->stripe()->accountLinks->create([
            'account' => $stripeAccountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]));

        return $link->url;
    }

    /**
     * Retrieve account details to check onboarding status.
     */
    public function getAccountStatus(string $stripeAccountId): array
    {
        $account = $this->stripeRequest(
            fn () => $this->stripe()->accounts->retrieve($stripeAccountId)
        );

        return [
            'id' => $account->id,
            'charges_enabled' => $account->charges_enabled,
            'payouts_enabled' => $account->payouts_enabled,
            'details_submitted' => $account->details_submitted,
            // charges_enabled is true only when Stripe has approved both card_payments
            // and transfers capabilities. We require this (not just details_submitted)
            // because transfer_data[destination] payments fail with an InvalidRequestException
            // if transfers capability is pending, even after the form is submitted.
            'onboarding_complete' => (bool) $account->charges_enabled,
        ];
    }

    /**
     * Delete (disconnect) the connected Stripe Express account.
     */
    public function deleteAccount(string $stripeAccountId): void
    {
        $this->stripeRequest(fn () => $this->stripe()->accounts->delete($stripeAccountId));
    }

    /**
     * Stripe can return advisory response headers alongside a successful API
     * response. stripe-php emits those headers as E_USER_WARNING, which Laravel
     * normally converts into an ErrorException before the response is parsed.
     * Ignore only the known Accounts v2 recommendation; every other warning and
     * every Stripe API exception continues through Laravel's normal handler.
     */
    protected function stripeRequest(Closure $request): mixed
    {
        $previousHandler = null;
        $previousHandler = set_error_handler(
            function (int $severity, string $message, string $file, int $line) use (&$previousHandler) {
                if ($severity === E_USER_WARNING
                    && str_starts_with($message, self::ACCOUNTS_V2_ADVISORY)) {
                    Log::notice('Stripe API advisory received.', ['message' => $message]);

                    return true;
                }

                if (is_callable($previousHandler)) {
                    return $previousHandler($severity, $message, $file, $line);
                }

                return false;
            }
        );

        try {
            return $request();
        } finally {
            restore_error_handler();
        }
    }

    private function stripe(): StripeClient
    {
        if ($this->stripe) {
            return $this->stripe;
        }

        $secret = trim((string) config('services.stripe.secret'));

        if ($secret === '') {
            throw new ServiceUnavailableHttpException(
                null,
                'Stripe is not configured. Set STRIPE_SECRET and clear Laravel config cache.'
            );
        }

        return $this->stripe = new StripeClient($secret);
    }
}
