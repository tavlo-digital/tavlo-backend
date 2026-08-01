<?php

namespace App\Services;

use App\Models\OrderPayment;
use Throwable;

class PaymentMethodDetailsService
{
    public function __construct(private readonly StripePaymentService $stripe) {}

    /**
     * Return a stable, receipt-safe description of the method used. Historical
     * Stripe payments are enriched once on first read and then persisted.
     *
     * @return array{
     *   provider: string,
     *   method: string,
     *   type: string,
     *   display_name: string,
     *   card_brand: string|null,
     *   card_last4: string|null,
     *   masked_card: string|null,
     *   wallet_type: string|null
     * }
     */
    public function details(?OrderPayment $payment, ?string $fallbackMethod = null): array
    {
        $stored = $payment?->payment_method_details;

        if ((! is_array($stored) || $stored === []) && $payment?->stripe_payment_intent_id) {
            $paymentMethodId = trim((string) $payment->payment_method);

            if ($paymentMethodId !== '') {
                try {
                    $stored = $this->stripe->retrievePaymentMethodDetails($paymentMethodId);

                    if ($stored) {
                        $payment->forceFill(['payment_method_details' => $stored])->saveQuietly();
                    }
                } catch (Throwable $exception) {
                    try {
                        report($exception);
                    } catch (Throwable) {
                        // Payment display must still fall back safely if logging is unavailable.
                    }
                }
            }
        }

        if (is_array($stored) && $stored !== []) {
            return $this->normalize($stored, 'stripe');
        }

        $provider = $payment?->stripe_payment_intent_id || $fallbackMethod === 'stripe'
            ? 'stripe'
            : ($fallbackMethod ?: 'cash');

        if ($provider === 'cash' || $fallbackMethod === 'cash') {
            return $this->normalize([
                'provider' => 'cash',
                'method' => 'cash',
                'type' => 'cash',
                'display_name' => 'Cash',
            ], 'cash');
        }

        if ($provider !== 'stripe') {
            return $this->normalize([
                'provider' => $provider,
                'method' => $provider,
                'type' => $provider,
                'display_name' => $this->headline($provider),
            ], $provider);
        }

        return $this->normalize([
            'provider' => 'stripe',
            'method' => 'stripe',
            'type' => 'stripe',
            'display_name' => 'Stripe',
        ], 'stripe');
    }

    /** @return array<string, string|null> */
    public function vendorDetails(?OrderPayment $payment, ?string $fallbackMethod = null): array
    {
        $details = $this->details($payment, $fallbackMethod);

        return [
            'provider' => $details['provider'],
            'method' => $details['method'],
            'type' => $details['type'],
            'displayName' => $details['display_name'],
            'cardBrand' => $details['card_brand'],
            'cardLast4' => $details['card_last4'],
            'maskedCard' => $details['masked_card'],
            'walletType' => $details['wallet_type'],
        ];
    }

    /** @param array<string, mixed> $details */
    private function normalize(array $details, string $defaultProvider): array
    {
        $type = strtolower((string) ($details['type'] ?? $details['method'] ?? $defaultProvider));
        $walletType = $this->nullableString($details['wallet_type'] ?? null);
        $cardBrand = $this->nullableString($details['card_brand'] ?? null);
        $cardLast4 = $this->nullableString($details['card_last4'] ?? null);
        $method = strtolower((string) ($details['method'] ?? $walletType ?? $cardBrand ?? $type));

        return [
            'provider' => strtolower((string) ($details['provider'] ?? $defaultProvider)),
            'method' => $method,
            'type' => $type,
            'display_name' => (string) ($details['display_name'] ?? $this->headline($method)),
            'card_brand' => $cardBrand,
            'card_last4' => $cardLast4,
            'masked_card' => $cardLast4 ? '**** **** **** '.$cardLast4 : null,
            'wallet_type' => $walletType,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? strtolower($value) : null;
    }

    private function headline(string $value): string
    {
        return match ($value) {
            'apple_pay' => 'Apple Pay',
            'google_pay' => 'Google Pay',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            'paypal' => 'PayPal',
            'cashapp' => 'Cash App Pay',
            'sepa_debit' => 'SEPA Direct Debit',
            default => ucwords(str_replace('_', ' ', $value)),
        };
    }
}
