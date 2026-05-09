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
        $this->stripe()->accounts->delete($stripeAccountId);
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
