<?php

namespace App\Services;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripePaymentService
{
    private ?StripeClient $stripe = null;

    /**
     * @param array<string, string> $metadata
     * @return array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null}
     */
    public function createPaymentIntent(int $amountMinor, string $currency, string $stripeAccountId, array $metadata): array
    {
        $intent = $this->stripe()->paymentIntents->create([
            'amount' => $amountMinor,
            'currency' => strtolower($currency),
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'transfer_data' => [
                'destination' => $stripeAccountId,
            ],
            'metadata' => $metadata,
        ]);

        return $this->paymentIntentPayload($intent);
    }

    /**
     * @return array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null}
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        return $this->paymentIntentPayload(
            $this->stripe()->paymentIntents->retrieve($paymentIntentId)
        );
    }

    /**
     * @param array<string, string> $metadata
     * @return array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null}
     */
    public function updatePaymentIntent(string $paymentIntentId, int $amountMinor, string $currency, array $metadata = []): array
    {
        $payload = [
            'amount' => $amountMinor,
        ];

        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        return $this->paymentIntentPayload(
            $this->stripe()->paymentIntents->update($paymentIntentId, $payload)
        );
    }

    /**
     * @return array{type: string, payment_intent: array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null}}
     */
    public function parseWebhookEvent(string $payload, ?string $signature): array
    {
        $event = Webhook::constructEvent(
            $payload,
            $signature ?? '',
            config('services.stripe.webhook_secret')
        );

        return [
            'type' => $event->type,
            'payment_intent' => $this->paymentIntentPayload($event->data->object),
        ];
    }

    /**
     * @return array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null}
     */
    private function paymentIntentPayload(object $intent): array
    {
        $metadata = $intent->metadata ?? [];
        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            $metadata = $metadata->toArray();
        } elseif (is_object($metadata) && method_exists($metadata, 'toArrayRecursive')) {
            $metadata = $metadata->toArrayRecursive();
        }

        return [
            'id' => (string) $intent->id,
            'client_secret' => $intent->client_secret ?? null,
            'status' => $intent->status ?? null,
            'metadata' => is_array($metadata) ? $metadata : [],
            'payment_method' => isset($intent->payment_method) ? (string) $intent->payment_method : null,
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
