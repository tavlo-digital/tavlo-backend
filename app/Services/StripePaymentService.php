<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

class StripePaymentService
{
    private ?StripeClient $stripe = null;

    /**
     * @param  array<string, string>  $metadata
     * @return array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null, payment_method_details: array<string, string|null>|null}
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
     * @return array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null, payment_method_details: array<string, string|null>|null}
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        return $this->paymentIntentPayload(
            $this->stripe()->paymentIntents->retrieve($paymentIntentId, [
                'expand' => ['payment_method'],
            ])
        );
    }

    /** @return array<string, string|null>|null */
    public function retrievePaymentMethodDetails(string $paymentMethodId): ?array
    {
        return $this->paymentMethodDetailsPayload(
            $this->stripe()->paymentMethods->retrieve($paymentMethodId)
        );
    }

    /**
     * @param  array<string, string>  $metadata
     * @return array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null, payment_method_details: array<string, string|null>|null}
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
     * @return array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null, payment_method_details: array<string, string|null>|null}
     */
    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        return $this->paymentIntentPayload(
            $this->stripe()->paymentIntents->cancel($paymentIntentId)
        );
    }

    /**
     * @return array{type: string, payment_intent: array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null, payment_method_details: array<string, string|null>|null}}
     */
    public function parseWebhookEvent(string $payload, ?string $signature): array
    {
        $event = Webhook::constructEvent(
            $payload,
            $signature ?? '',
            config('services.stripe.webhook_secret')
        );

        $intent = $this->paymentIntentPayload($event->data->object);

        if (! $intent['payment_method_details'] && $intent['payment_method']) {
            try {
                $intent['payment_method_details'] = $this->retrievePaymentMethodDetails($intent['payment_method']);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [
            'type' => $event->type,
            'payment_intent' => $intent,
        ];
    }

    /**
     * @return array{id: string, client_secret: string|null, status: string|null, metadata: array<string, mixed>, payment_method: string|null, payment_method_details: array<string, string|null>|null}
     */
    private function paymentIntentPayload(object $intent): array
    {
        $metadata = $intent->metadata ?? [];
        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            $metadata = $metadata->toArray();
        } elseif (is_object($metadata) && method_exists($metadata, 'toArrayRecursive')) {
            $metadata = $metadata->toArrayRecursive();
        }

        $paymentMethod = $intent->payment_method ?? null;

        return [
            'id' => (string) $intent->id,
            'client_secret' => $intent->client_secret ?? null,
            'status' => $intent->status ?? null,
            'metadata' => is_array($metadata) ? $metadata : [],
            'payment_method' => is_object($paymentMethod)
                ? (isset($paymentMethod->id) ? (string) $paymentMethod->id : null)
                : ($paymentMethod ? (string) $paymentMethod : null),
            'payment_method_details' => $this->paymentMethodDetailsPayload($paymentMethod),
        ];
    }

    /** @return array<string, string|null>|null */
    private function paymentMethodDetailsPayload(mixed $paymentMethod): ?array
    {
        if (! is_object($paymentMethod) && ! is_array($paymentMethod)) {
            return null;
        }

        if (is_object($paymentMethod) && method_exists($paymentMethod, 'toArrayRecursive')) {
            $paymentMethod = $paymentMethod->toArrayRecursive();
        } elseif (is_object($paymentMethod) && method_exists($paymentMethod, 'toArray')) {
            $paymentMethod = $paymentMethod->toArray();
        } elseif (is_object($paymentMethod)) {
            $paymentMethod = get_object_vars($paymentMethod);
        }

        if (! is_array($paymentMethod)) {
            return null;
        }

        $type = strtolower((string) ($paymentMethod['type'] ?? ''));
        if ($type === '') {
            return null;
        }

        $card = is_array($paymentMethod['card'] ?? null) ? $paymentMethod['card'] : [];
        $wallet = is_array($card['wallet'] ?? null) ? $card['wallet'] : [];
        $walletType = $this->nullableLowerString($wallet['type'] ?? null);
        $cardBrand = $this->nullableLowerString($card['brand'] ?? null);
        $cardLast4 = trim((string) ($card['last4'] ?? '')) ?: null;
        $method = $walletType ?? $cardBrand ?? $type;

        return [
            'provider' => 'stripe',
            'method' => $method,
            'type' => $type,
            'display_name' => $this->paymentMethodDisplayName($method),
            'card_brand' => $cardBrand,
            'card_last4' => $cardLast4,
            'masked_card' => $cardLast4 ? '**** **** **** '.$cardLast4 : null,
            'wallet_type' => $walletType,
        ];
    }

    private function nullableLowerString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? strtolower($value) : null;
    }

    private function paymentMethodDisplayName(string $method): string
    {
        return match ($method) {
            'apple_pay' => 'Apple Pay',
            'google_pay' => 'Google Pay',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            'diners' => 'Diners Club',
            'jcb' => 'JCB',
            'unionpay' => 'UnionPay',
            'paypal' => 'PayPal',
            'cashapp' => 'Cash App Pay',
            'sepa_debit' => 'SEPA Direct Debit',
            'us_bank_account' => 'US Bank Account',
            default => ucwords(str_replace('_', ' ', $method)),
        };
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
