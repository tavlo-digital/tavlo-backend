<?php

namespace App\Services;

use App\Models\Vendor;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Stripe\StripeClient;

class StripeConnectService
{
    private ?StripeClient $stripe = null;

    /**
     * Create a Stripe Express account for the vendor and return the account ID.
     */
    public function createExpressAccount(Vendor $vendor): string
    {
        $account = $this->stripe()->accounts->create([
            'type'         => 'express',
            'email'        => $vendor->email,
            'business_profile' => [
                'name' => $vendor->restaurant_name ?? $vendor->name,
            ],
            'capabilities' => [
                'card_payments'  => ['requested' => true],
                'transfers'      => ['requested' => true],
            ],
            'metadata' => [
                'vendor_id' => (string) $vendor->id,
            ],
        ]);

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
        $link = $this->stripe()->accountLinks->create([
            'account'     => $stripeAccountId,
            'refresh_url' => $refreshUrl,
            'return_url'  => $returnUrl,
            'type'        => 'account_onboarding',
        ]);

        return $link->url;
    }

    /**
     * Retrieve account details to check onboarding status.
     */
    public function getAccountStatus(string $stripeAccountId): array
    {
        $account = $this->stripe()->accounts->retrieve($stripeAccountId);

        return [
            'id'                  => $account->id,
            'charges_enabled'     => $account->charges_enabled,
            'payouts_enabled'     => $account->payouts_enabled,
            'details_submitted'   => $account->details_submitted,
            // The merchant has finished filling the onboarding form. We use
            // details_submitted alone (not charges_enabled) because card_payments /
            // transfers capabilities can stay "pending" for a while after onboarding,
            // especially in test mode — Stripe still considers the account onboarded.
            'onboarding_complete' => (bool) $account->details_submitted,
        ];
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
