<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use App\Services\VendorDateTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly VendorDateTimeService $dateTimes,
        private readonly LoyaltyService $loyaltyService,
    ) {}

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
                    'vendor' => $wallet->vendor->only('vendor_public_id', 'restaurant_name'),
                    'logo_url' => $settings?->logo_url,
                    'points_balance' => $wallet->points_balance,
                    'total_earned' => $wallet->total_earned,
                    'total_redeemed' => $wallet->total_redeemed,
                    'next_reward_at' => $minRedemption,
                    'points_to_next_reward' => max(0, $minRedemption - $wallet->points_balance),
                    'reward_value_eur' => round($minRedemption * $pointValue, 2),
                    'is_redeemable' => $wallet->points_balance >= $minRedemption,
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
                'vendor.vendorSetting:id,vendor_id,points_per_euro,minimum_redemption_points,point_value,date_format,time_format',
            ])
            ->firstOrFail();

        $transactions = LoyaltyTransaction::where('customer_id', $request->user()->id)
            ->where('vendor_id', $wallet->vendor_id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));
        $transactions->getCollection()->transform(function (LoyaltyTransaction $transaction) use ($wallet) {
            $payload = $transaction->toArray();
            $payload['created_at'] = $this->dateTimes->formatDateTime(
                $transaction->created_at,
                $wallet->vendor,
            );
            $payload['updated_at'] = $this->dateTimes->formatDateTime(
                $transaction->updated_at,
                $wallet->vendor,
            );

            return $payload;
        });

        $settings = $wallet->vendor->vendorSetting;

        return response()->json([
            'wallet' => [
                'points_balance' => $wallet->points_balance,
                'total_earned' => $wallet->total_earned,
                'total_redeemed' => $wallet->total_redeemed,
                'next_reward_at' => $settings?->minimum_redemption_points ?? 100,
                'reward_value_eur' => round(($settings?->minimum_redemption_points ?? 100) * ($settings?->point_value ?? 0.01), 2),
            ],
            'transactions' => $transactions,
        ]);
    }

    /**
     * GET /api/customer/loyalty/{vendorPublicId}/info
     * Payment-screen info: balance + redemption options for a specific vendor.
     */
    public function info(Request $request, string $vendorPublicId): JsonResponse
    {
        $customer = $request->user();
        $vendor = \App\Models\Vendor::with('vendorSetting')
            ->where('vendor_public_id', $vendorPublicId)
            ->firstOrFail();

        $settings = $vendor->vendorSetting;
        if (! $settings?->loyalty_enabled) {
            return response()->json(['loyalty_enabled' => false]);
        }

        $wallet = CustomerLoyaltyPoint::where('customer_id', $customer->id)
            ->where('vendor_id', $vendor->id)
            ->first();

        $minRedemption = (int) ($settings->minimum_redemption_points ?? 100);
        $pointValue = (float) ($settings->point_value ?? 0.01);
        $pointsPerEuro = (int) ($settings->points_per_euro ?? 10);
        $balance = $wallet?->points_balance ?? 0;

        // Build quick-select buttons (multiples of minimum that customer can afford)
        $options = [];
        for ($i = 1; $i <= 3; $i++) {
            $pts = $minRedemption * $i;
            if ($pts <= $balance) {
                $options[] = ['points' => $pts, 'value_eur' => round($pts * $pointValue, 2)];
            }
        }

        return response()->json([
            'loyalty_enabled'       => true,
            'points_balance'        => $balance,
            'minimum_redemption'    => $minRedemption,
            'point_value'           => $pointValue,
            'points_per_euro'       => $pointsPerEuro,
            'is_redeemable'         => $balance >= $minRedemption,
            'points_to_next_reward' => max(0, $minRedemption - $balance),
            'redemption_options'    => $options,
        ]);
    }

    /**
     * POST /api/customer/loyalty/{vendorPublicId}/redeem
     * Validate a redemption (does not commit — call after order is saved).
     */
    public function redeem(Request $request, string $vendorPublicId): JsonResponse
    {
        $data = $request->validate([
            'points_to_redeem' => ['required', 'integer', 'min:1'],
            'order_total'      => ['required', 'numeric', 'min:0'],
        ]);

        $vendor = \App\Models\Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        try {
            $discount = $this->loyaltyService->redeemPoints(
                $request->user()->id,
                $vendor->id,
                (int) $data['points_to_redeem'],
                (float) $data['order_total'],
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'points_to_redeem' => (int) $data['points_to_redeem'],
            'discount_eur'     => $discount,
        ]);
    }
}
