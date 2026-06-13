<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\TaxCategory;
use Illuminate\Support\Collection;

class TaxCalculationService
{
    private static array $taxCategoryCache = [];

    public static function gross(float $net, float $vatRate): float
    {
        return round($net * (1 + $vatRate / 100), 2);
    }

    public static function netFromGross(float $gross, float $vatRate): float
    {
        return round($gross / (1 + $vatRate / 100), 2);
    }

    public static function vatFromGross(float $gross, float $vatRate): float
    {
        return round($gross - self::netFromGross($gross, $vatRate), 2);
    }

    public static function resolveVatRate(string $slug, string $vendorCountry): float
    {
        $country = self::resolveCountryCode($vendorCountry);
        $cacheKey = "{$country}:{$slug}";

        if (isset(self::$taxCategoryCache[$cacheKey])) {
            return self::$taxCategoryCache[$cacheKey];
        }

        $tc = TaxCategory::where('country', $country)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        $rate = $tc ? (float) $tc->vat_rate : 0.0;
        self::$taxCategoryCache[$cacheKey] = $rate;

        return $rate;
    }

    public static function resolveTaxLabel(string $slug): string
    {
        return match ($slug) {
            'food' => 'Food',
            'beverage_non_alcoholic', 'beverage-non-alcoholic' => 'Beverage (Non-Alcoholic)',
            'beverage_alcoholic', 'beverage-alcoholic' => 'Beverage (Alcoholic)',
            default => ucfirst(str_replace(['_', '-'], ' ', $slug)),
        };
    }

    public static function itemBaseGross(mixed $menuItem, string $vendorCountry): float
    {
        if (! $menuItem) {
            return 0.0;
        }

        $vatRate = self::itemVatRate($menuItem, $vendorCountry);
        $netPrice = ($menuItem->has_discount && $menuItem->discounted_price !== null)
            ? (float) $menuItem->discounted_price
            : (float) $menuItem->price;

        return self::gross($netPrice, $vatRate);
    }

    public static function itemVatRate(mixed $menuItem, string $vendorCountry): float
    {
        if (! $menuItem) {
            return 0.0;
        }

        if ($menuItem->tax_category) {
            $rate = self::resolveVatRate($menuItem->tax_category, $vendorCountry);

            if ($rate > 0) {
                return $rate;
            }
        }

        return (float) ($menuItem->vat_rate ?? 0);
    }

    public static function addonVatRate(array $addon, string $itemTaxCategory, string $vendorCountry): float
    {
        $slug = $addon['taxCategory'] ?? $addon['tax_category'] ?? $itemTaxCategory;

        if ($slug) {
            return self::resolveVatRate($slug, $vendorCountry);
        }

        return 0.0;
    }

    public static function modifierGroupVatRate(string $groupTaxCategory, string $itemTaxCategory, string $vendorCountry): float
    {
        $slug = $groupTaxCategory ?: $itemTaxCategory;

        if ($slug) {
            return self::resolveVatRate($slug, $vendorCountry);
        }

        return 0.0;
    }

    /**
     * Compute unit price (gross) for a cart item: base + addons + modifiers, all VAT-inclusive.
     */
    public static function cartItemUnitPriceGross(CartItem $item, string $vendorCountry): float
    {
        $menuItem = $item->menuItem;
        $baseGross = self::itemBaseGross($menuItem, $vendorCountry);
        $addonsGross = self::cartItemPaidAddonsGross($item, $vendorCountry);
        $modifiersGross = self::cartItemModifiersGross($item, $vendorCountry);

        return round($baseGross + $addonsGross + $modifiersGross, 2);
    }

    public static function cartItemLineTotalGross(CartItem $item, string $vendorCountry): float
    {
        return round(self::cartItemUnitPriceGross($item, $vendorCountry) * (int) $item->quantity, 2);
    }

    public static function cartItemPaidAddonsGross(CartItem $item, string $vendorCountry): float
    {
        $itemTaxCategory = $item->menuItem?->tax_category ?? 'food';

        return round(collect($item->paid_addons ?? [])->sum(function ($addon) use ($itemTaxCategory, $vendorCountry) {
            $netPrice = (float) ($addon['price'] ?? 0);
            $vatRate = self::addonVatRate($addon, $itemTaxCategory, $vendorCountry);

            return self::gross($netPrice, $vatRate);
        }), 2);
    }

    public static function cartItemModifiersGross(CartItem $item, string $vendorCountry): float
    {
        $itemTaxCategory = $item->menuItem?->tax_category ?? 'food';

        return round(collect($item->selected_modifiers ?? [])
            ->sum(function ($group) use ($itemTaxCategory, $vendorCountry) {
                $groupTaxCategory = $group['tax_category'] ?? '';
                $vatRate = self::modifierGroupVatRate($groupTaxCategory, $itemTaxCategory, $vendorCountry);

                return collect($group['options'] ?? [])
                    ->sum(function ($option) use ($vatRate) {
                        $net = (float) ($option['price_adjustment'] ?? 0);

                        return self::gross($net, $vatRate);
                    });
            }), 2);
    }

    /**
     * Build tax_groups array from a collection of cart items.
     * Each item's line total is decomposed into base / addon / modifier contributions,
     * each attributed to its own tax category.
     */
    public static function computeTaxGroups(Collection $cartItems, string $vendorCountry): array
    {
        $buckets = [];

        foreach ($cartItems as $item) {
            $menuItem = $item->menuItem;
            if (! $menuItem) {
                continue;
            }

            $qty = (int) $item->quantity;
            $itemTaxCategory = $menuItem->tax_category ?? 'food';
            $itemVatRate = self::itemVatRate($menuItem, $vendorCountry);

            $baseGross = self::itemBaseGross($menuItem, $vendorCountry) * $qty;
            self::addToBucket($buckets, $itemTaxCategory, $itemVatRate, $baseGross);

            foreach ($item->paid_addons ?? [] as $addon) {
                $addonSlug = $addon['taxCategory'] ?? $addon['tax_category'] ?? $itemTaxCategory;
                $addonVatRate = self::addonVatRate($addon, $itemTaxCategory, $vendorCountry);
                $addonGross = self::gross((float) ($addon['price'] ?? 0), $addonVatRate) * $qty;
                self::addToBucket($buckets, $addonSlug, $addonVatRate, $addonGross);
            }

            foreach ($item->selected_modifiers ?? [] as $group) {
                $groupSlug = $group['tax_category'] ?? $itemTaxCategory;
                $groupVatRate = self::modifierGroupVatRate($groupSlug, $itemTaxCategory, $vendorCountry);

                foreach ($group['options'] ?? [] as $option) {
                    $optGross = self::gross((float) ($option['price_adjustment'] ?? 0), $groupVatRate) * $qty;
                    self::addToBucket($buckets, $groupSlug, $groupVatRate, $optGross);
                }
            }
        }

        ksort($buckets);
        $code = 'A';
        $result = [];

        foreach ($buckets as $slug => $bucket) {
            $gross = round($bucket['gross'], 2);
            $net = self::netFromGross($gross, $bucket['vat_rate']);
            $vat = round($gross - $net, 2);

            $result[] = [
                'code' => $code,
                'label' => self::resolveTaxLabel($slug),
                'tax_category' => $slug,
                'vat_rate' => $bucket['vat_rate'],
                'vat_amount' => $vat,
                'gross_amount' => $gross,
                'net_amount' => $net,
            ];

            $code++;
        }

        return $result;
    }

    /**
     * Build totals from tax_groups + service fee rate.
     */
    public static function computeTotals(array $taxGroups, float $serviceFeeRate): array
    {
        $netTotal = round(array_sum(array_column($taxGroups, 'net_amount')), 2);
        $vatTotal = round(array_sum(array_column($taxGroups, 'vat_amount')), 2);
        $grossTotal = round(array_sum(array_column($taxGroups, 'gross_amount')), 2);
        $serviceFee = round($grossTotal * ($serviceFeeRate / 100), 2);

        return [
            'net_total' => $netTotal,
            'vat_total' => $vatTotal,
            'service_fee' => $serviceFee,
            'grand_total' => round($grossTotal + $serviceFee, 2),
        ];
    }

    private static function addToBucket(array &$buckets, string $slug, float $vatRate, float $grossAmount): void
    {
        if ($grossAmount == 0) {
            return;
        }

        if (! isset($buckets[$slug])) {
            $buckets[$slug] = ['vat_rate' => $vatRate, 'gross' => 0.0];
        }

        $buckets[$slug]['gross'] += $grossAmount;
    }

    public static function countryCode(string $country): string
    {
        return self::resolveCountryCode($country);
    }

    private static function resolveCountryCode(string $country): string
    {
        $map = [
            'austria' => 'AT',
            'germany' => 'DE',
            'united kingdom' => 'GB',
            'uk' => 'GB',
            'great britain' => 'GB',
        ];

        return $map[strtolower(trim($country))] ?? strtoupper(substr($country, 0, 2));
    }
}
