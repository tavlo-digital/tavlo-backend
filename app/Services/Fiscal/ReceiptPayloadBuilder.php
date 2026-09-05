<?php

namespace App\Services\Fiscal;

use App\Exceptions\FiscalizationException;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\TaxCalculationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Turns an order into the VAT and payment breakdown fiskaly signs.
 *
 * Two rules drive everything here: every cent charged must sit in exactly one
 * VAT bucket, and the buckets must add up to the amount actually taken from the
 * customer. A breakdown that is a cent out is a false declaration, not a
 * rounding nicety, so the totals are reconciled and then asserted.
 */
class ReceiptPayloadBuilder
{
    /**
     * @return array{
     *     amounts_per_vat_rate: array<int, array{vat_rate: string, amount: string}>,
     *     amounts_per_payment_type: array<int, array{payment_type: string, amount: string}>,
     *     total_gross: float,
     *     currency: string,
     *     tax_groups: array<int, array<string, mixed>>
     * }
     */
    public function build(Order $order, string $countryCode): array
    {
        $items = $this->linkedCartItems($order);
        $vendorCountry = $order->vendor?->country ?? $countryCode;

        $taxGroups = TaxCalculationService::computeTaxGroups($items, $vendorCountry, true);

        $buckets = [];

        foreach ($taxGroups as $group) {
            $this->addToBucket(
                $buckets,
                $this->bucketName($countryCode, (float) $group['vat_rate']),
                (float) $group['gross_amount'],
            );
        }

        $serviceFee = round((float) ($order->service_fee ?? 0), 2);
        $tip = round((float) ($order->tip_amount ?? 0), 2);

        if ($serviceFee != 0.0) {
            $this->addToBucket(
                $buckets,
                $this->configuredBucket($countryCode, 'service_fee_vat'),
                $serviceFee,
            );
        }

        if ($tip != 0.0) {
            $this->addToBucket(
                $buckets,
                $this->configuredBucket($countryCode, 'tip_vat'),
                $tip,
            );
        }

        // The charged amount is the authority: orders.amount already carries the
        // service fee, and the tip is added on top at checkout.
        $charged = round((float) $order->amount + $tip, 2);

        if ($charged <= 0.0) {
            throw new FiscalizationException('Refusing to fiscalize a non-positive total.', [
                'order_id' => $order->id,
                'charged' => $charged,
            ]);
        }

        $buckets = $this->reconcileToTotal($buckets, $charged, $order);

        return [
            'amounts_per_vat_rate' => $this->formatBuckets($buckets),
            'amounts_per_payment_type' => [[
                'payment_type' => $this->paymentType($order),
                'amount' => $this->money($charged),
            ]],
            'total_gross' => $charged,
            'currency' => strtoupper((string) ($order->currency ?? $order->vendor?->currency ?? 'EUR')),
            'tax_groups' => $taxGroups,
        ];
    }

    /**
     * Per-item rounding and the division of shared items across payers each
     * leave sub-cent dust, so the buckets can land a cent or two off the charge.
     * Push the difference onto the largest bucket — the conventional POS
     * treatment — and refuse anything bigger, which would mean a real bug
     * upstream rather than rounding.
     *
     * @param  array<string, float>  $buckets
     * @return array<string, float>
     */
    private function reconcileToTotal(array $buckets, float $charged, Order $order): array
    {
        $sum = round(array_sum($buckets), 2);
        $delta = round($charged - $sum, 2);

        if ($delta == 0.0) {
            return $buckets;
        }

        if (abs($delta) > 0.05) {
            throw new FiscalizationException(
                'Receipt breakdown does not match the amount charged.',
                [
                    'order_id' => $order->id,
                    'charged' => $charged,
                    'buckets_total' => $sum,
                    'difference' => $delta,
                ],
            );
        }

        if ($buckets === []) {
            throw new FiscalizationException('Receipt has no VAT buckets to carry the total.', [
                'order_id' => $order->id,
            ]);
        }

        $largest = array_search(max($buckets), $buckets, true);
        $buckets[$largest] = round($buckets[$largest] + $delta, 2);

        return $buckets;
    }

    /**
     * An unmapped rate is a tax question, not something to guess at: bucketing
     * it as standard would misdeclare it.
     */
    private function bucketName(string $countryCode, float $vatRate): string
    {
        $map = (array) config("services.fiskaly.vat_rate_map.{$countryCode}", []);
        $key = rtrim(rtrim(number_format($vatRate, 2, '.', ''), '0'), '.');

        if (! isset($map[$key])) {
            throw new FiscalizationException('No fiskaly VAT bucket is mapped for this rate.', [
                'country' => $countryCode,
                'vat_rate' => $vatRate,
                'mapped_rates' => array_keys($map),
            ]);
        }

        return (string) $map[$key];
    }

    private function configuredBucket(string $countryCode, string $setting): string
    {
        $choice = (string) config("services.fiskaly.{$setting}", 'standard');

        return match ($choice) {
            'zero' => $this->bucketName($countryCode, 0.0),
            'standard' => $this->bucketName($countryCode, $this->standardRate($countryCode)),
            default => throw new FiscalizationException("Unknown fiskaly {$setting} setting.", [
                'value' => $choice,
                'allowed' => ['standard', 'zero'],
            ]),
        };
    }

    private function standardRate(string $countryCode): float
    {
        return match ($countryCode) {
            'AT' => 20.0,
            'DE' => 19.0,
            default => throw new FiscalizationException('No standard VAT rate known for this country.', [
                'country' => $countryCode,
            ]),
        };
    }

    private function paymentType(Order $order): string
    {
        return $order->payment_method === 'cash' ? 'CASH' : 'NON_CASH';
    }

    /** @param array<string, float> $buckets */
    private function addToBucket(array &$buckets, string $name, float $amount): void
    {
        $buckets[$name] = round(($buckets[$name] ?? 0.0) + $amount, 2);
    }

    /**
     * @param  array<string, float>  $buckets
     * @return array<int, array{vat_rate: string, amount: string}>
     */
    private function formatBuckets(array $buckets): array
    {
        ksort($buckets);

        return collect($buckets)
            ->reject(fn (float $amount) => $amount == 0.0)
            ->map(fn (float $amount, string $name) => [
                'vat_rate' => $name,
                'amount' => $this->money($amount),
            ])
            ->values()
            ->all();
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Mirrors the receipt endpoints: an order's own items plus the items other
     * guests shared into it.
     */
    private function linkedCartItems(Order $order): Collection
    {
        $owned = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category')
            ->where('order_id', $order->id)
            ->get();

        $sharedIn = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category')
            ->whereJsonContains('shared_order_ids', $order->id)
            ->when($order->table_scan_session_id, fn (Builder $query) => $query
                ->where('table_scan_session_id', '!=', $order->table_scan_session_id))
            ->get();

        return $owned->merge($sharedIn)->unique('id')->values();
    }
}
