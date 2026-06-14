<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\CustomerLoyaltyPoint;
use App\Models\LoyaltyTransaction;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyStatsController extends Controller
{
    /** GET /api/vendor/{vendorId}/loyalty/stats */
    public function stats(Request $request, string $vendorId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorId)->firstOrFail();

        $totalIssued     = LoyaltyTransaction::where('vendor_id', $vendor->id)->where('type', 'earned')->sum('points');
        $totalRedeemed   = LoyaltyTransaction::where('vendor_id', $vendor->id)->where('type', 'redeemed')->sum('points');
        $activeBalance   = CustomerLoyaltyPoint::where('vendor_id', $vendor->id)->sum('points_balance');
        $uniqueCustomers = CustomerLoyaltyPoint::where('vendor_id', $vendor->id)->where('total_earned', '>', 0)->count();
        $pointValue      = (float) ($vendor->vendorSetting?->point_value ?? 0.01);

        return response()->json([
            'total_issued'     => (int) $totalIssued,
            'total_redeemed'   => (int) $totalRedeemed,
            'active_balance'   => (int) $activeBalance,
            'unique_customers' => (int) $uniqueCustomers,
            'point_value'      => $pointValue,
            'liability_eur'    => round($activeBalance * $pointValue, 2),
        ]);
    }
}
