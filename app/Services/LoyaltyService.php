<?php

namespace App\Services;

use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * Earn points for a completed order. Safe to call multiple times — checks
     * loyalty_transactions to avoid double-crediting the same order.
     */
    public function earnPointsForOrder(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        $vendor = $order->vendor()->with('vendorSetting')->first();
        $settings = $vendor?->vendorSetting;

        if (! $settings?->loyalty_enabled) {
            return;
        }

        // Avoid double-crediting
        $alreadyEarned = LoyaltyTransaction::where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->where('type', 'earned')
            ->exists();
        if ($alreadyEarned) {
            return;
        }

        // Points earned on final charged amount (after discounts)
        $chargedAmount = $order->amount; // already net amount
        $pointsPerEuro = (int) ($settings->points_per_euro ?? 10);
        $points = (int) floor($chargedAmount * $pointsPerEuro);

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $vendor, $points) {
            $wallet = CustomerLoyaltyPoint::firstOrCreate(
                ['customer_id' => $order->customer_id, 'vendor_id' => $vendor->id],
                ['points_balance' => 0, 'total_earned' => 0, 'total_redeemed' => 0]
            );
            $wallet->increment('points_balance', $points);
            $wallet->increment('total_earned', $points);

            LoyaltyTransaction::create([
                'customer_id'    => $order->customer_id,
                'vendor_id'      => $vendor->id,
                'type'           => 'earned',
                'points'         => $points,
                'reference_type' => 'order',
                'reference_id'   => $order->id,
                'description'    => "Earned {$points} points from order #{$order->order_number}",
            ]);
        });
    }

    /**
     * Validate and redeem points at payment. Returns the euro discount amount.
     * Throws if insufficient balance or loyalty disabled.
     */
    public function redeemPoints(int $customerId, int $vendorId, int $pointsToRedeem, float $orderTotal): float
    {
        $vendor = \App\Models\Vendor::with('vendorSetting')->findOrFail($vendorId);
        $settings = $vendor->vendorSetting;

        if (! $settings?->loyalty_enabled) {
            throw new \RuntimeException('Loyalty programme is not enabled for this restaurant.');
        }

        $minRedemption = (int) ($settings->minimum_redemption_points ?? 100);
        $pointValue = (float) ($settings->point_value ?? 0.01);

        if ($pointsToRedeem < $minRedemption) {
            throw new \RuntimeException("Minimum redemption is {$minRedemption} points.");
        }

        $wallet = CustomerLoyaltyPoint::where('customer_id', $customerId)
            ->where('vendor_id', $vendorId)
            ->firstOrFail();

        if ($wallet->points_balance < $pointsToRedeem) {
            throw new \RuntimeException('Insufficient points balance.');
        }

        // Cap discount at order total
        $discount = round($pointsToRedeem * $pointValue, 2);
        $discount = min($discount, $orderTotal);

        return $discount;
    }

    /**
     * Commit a redemption. Call after the order is saved.
     */
    public function commitRedemption(int $customerId, int $vendorId, int $pointsRedeemed, int $orderId): void
    {
        DB::transaction(function () use ($customerId, $vendorId, $pointsRedeemed, $orderId) {
            $wallet = CustomerLoyaltyPoint::where('customer_id', $customerId)
                ->where('vendor_id', $vendorId)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->decrement('points_balance', $pointsRedeemed);
            $wallet->increment('total_redeemed', $pointsRedeemed);

            LoyaltyTransaction::create([
                'customer_id'    => $customerId,
                'vendor_id'      => $vendorId,
                'type'           => 'redeemed',
                'points'         => $pointsRedeemed,
                'reference_type' => 'order',
                'reference_id'   => $orderId,
                'description'    => "Redeemed {$pointsRedeemed} points for discount",
            ]);
        });
    }

    /**
     * Evaluate active promotions for a vendor at the current moment.
     * Returns the best matching promotion or null.
     */
    public function evaluatePromotion(int $vendorId, \Carbon\Carbon $orderTime): ?\App\Models\Promotion
    {
        $today = $orderTime->format('Y-m-d');
        $time = $orderTime->format('H:i:s');
        $dayName = $orderTime->format('l'); // e.g. "Monday"

        $promotions = \App\Models\Promotion::where('vendor_id', $vendorId)
            ->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();

        foreach ($promotions as $promo) {
            // Check time window
            if ($promo->start_time && $promo->end_time) {
                if ($time < $promo->start_time || $time > $promo->end_time) {
                    continue;
                }
            }
            // Check active days
            if ($promo->active_days && count($promo->active_days) > 0) {
                if (! in_array($dayName, $promo->active_days, true)) {
                    continue;
                }
            }

            return $promo;
        }

        return null;
    }
}
