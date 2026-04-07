<?php

namespace App\Services;

use App\Models\Vendor;
use Stripe\StripeClient;

class StripeConnectService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a Stripe Express account for the vendor and return the account ID.
     */
    public function createExpressAccount(Vendor $vendor): string
    {
        $account = $this->stripe->accounts->create([
            'type'         => 'express',
            'email'        => $vendor->email,
            'display_name' => $vendor->restaurant_name ?? $vendor->name,
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
        $link = $this->stripe->accountLinks->create([
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
        $account = $this->stripe->accounts->retrieve($stripeAccountId);

        return [
            'id'                  => $account->id,
            'charges_enabled'     => $account->charges_enabled,
            'payouts_enabled'     => $account->payouts_enabled,
            'details_submitted'   => $account->details_submitted,
            'onboarding_complete' => $account->charges_enabled && $account->details_submitted,
        ];
    }
}
