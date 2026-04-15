<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    /**
     * Get all loyalty wallets (one per restaurant).
     */
    public function index(Request $request): JsonResponse
    {
        $wallets = CustomerLoyaltyPoint::where('customer_id', $request->user()->id)
            ->with([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,points_per_euro,minimum_redemption_points,point_value,logo_url',
            ])
            ->get()
            ->map(function ($wallet) {
                $settings = $wallet->vendor->vendorSetting;
                $minRedemption = $settings?->minimum_redemption_points ?? 100;
                $pointValue = $settings?->point_value ?? 0.01;

                return [
                    'vendor'               => $wallet->vendor->only('vendor_public_id', 'restaurant_name'),
                    'logo_url'             => $settings?->logo_url,
                    'points_balance'       => $wallet->points_balance,
                    'total_earned'         => $wallet->total_earned,
                    'total_redeemed'       => $wallet->total_redeemed,
                    'next_reward_at'       => $minRedemption,
                    'points_to_next_reward' => max(0, $minRedemption - $wallet->points_balance),
                    'reward_value_eur'     => round($minRedemption * $pointValue, 2),
                    'is_redeemable'        => $wallet->points_balance >= $minRedemption,
                ];
            });

        return response()->json($wallets);
    }

    /**
     * Get loyalty wallet detail for a specific restaurant.
     */
    public function show(Request $request, string $vendorPublicId): JsonResponse
    {
        $wallet = CustomerLoyaltyPoint::where('customer_id', $request->user()->id)
            ->whereHas('vendor', fn ($q) => $q->where('vendor_public_id', $vendorPublicId))
            ->with([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,points_per_euro,minimum_redemption_points,point_value',
            ])
            ->firstOrFail();

        $transactions = LoyaltyTransaction::where('customer_id', $request->user()->id)
            ->where('vendor_id', $wallet->vendor_id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        $settings = $wallet->vendor->vendorSetting;

        return response()->json([
            'wallet' => [
                'points_balance'       => $wallet->points_balance,
                'total_earned'         => $wallet->total_earned,
                'total_redeemed'       => $wallet->total_redeemed,
                'next_reward_at'       => $settings?->minimum_redemption_points ?? 100,
                'reward_value_eur'     => round(($settings?->minimum_redemption_points ?? 100) * ($settings?->point_value ?? 0.01), 2),
            ],
            'transactions' => $transactions,
        ]);
    }
}
