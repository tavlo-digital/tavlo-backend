<?php

namespace App\Services;

use Stripe\StripeClient;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class StripeSubscriptionService
{
    public function createCheckoutSession(array $parameters): object
    {
        return $this->client()->checkout->sessions->create($parameters);
    }

    public function retrieveCheckoutSession(string $sessionId): object
    {
        return $this->client()->checkout->sessions->retrieve($sessionId);
    }

    public function retrieveSubscription(string $subscriptionId): object
    {
        return $this->client()->subscriptions->retrieve($subscriptionId);
    }

    private function client(): StripeClient
    {
        $secret = trim((string) config('services.stripe.secret'));

        if ($secret === '') {
            throw new ServiceUnavailableHttpException(
                null,
                'Stripe is not configured. Set STRIPE_SECRET and clear Laravel config cache.'
            );
        }

        return new StripeClient($secret);
    }
}
