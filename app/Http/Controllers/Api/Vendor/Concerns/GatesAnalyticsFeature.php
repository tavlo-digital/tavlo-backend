<?php

namespace App\Http\Controllers\Api\Vendor\Concerns;

use App\Models\Vendor;
use Illuminate\Http\JsonResponse;

/**
 * The plan-feature gate shared by Analytics and Financial Reports
 * (2026-09-07) — both require the SAME "Basic Analytics" feature, checked by
 * name against the vendor's *current* plan's live `plan_features` rows
 * rather than any hardcoded plan tier, which plan(s) actually carry it is
 * entirely admin-configurable (today every plan does; that can change
 * without a code change here).
 *
 * Financial Reports (and the Financial Expenses feature built on top of it)
 * originally shipped with no gating at all — a 2026-09-07 audit finding.
 * Founder's decision was to reuse this existing feature rather than
 * introduce a second, separately-priced one. Extracted here (out of
 * AnalyticsController, where this originated) once a second controller
 * needed the identical check, rather than duplicating the same
 * security-sensitive logic a third time.
 */
trait GatesAnalyticsFeature
{
    private const REQUIRED_FEATURE = 'Basic Analytics';

    /**
     * Only an `active`/`trialing` subscription counts as currently entitled
     * — matching AuthController::formatVendorUser()'s existing definition of
     * "does this vendor actually have a live subscription" for the same
     * question. Previously this picked the vendor's newest subscription ROW
     * with no status filter at all, so a `pending` row (inserted the instant
     * Stripe checkout starts, before any payment) or a `cancelled` row
     * (checkout/cancellation update the same row in place rather than
     * creating a new one) still "won" as the latest record and granted
     * permanent free access to Financial Reports, Financial Expenses, and
     * Analytics alike (2026-09-08 audit finding).
     */
    private function hasAnalyticsAccess(Vendor $vendor): bool
    {
        $plan = $vendor->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->with('plan.features')
            ->latest()
            ->first()?->plan;

        return $plan !== null && $plan->features->contains('name', self::REQUIRED_FEATURE);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function lockedResponse(Vendor $vendor, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'locked' => true,
            'requiredFeature' => self::REQUIRED_FEATURE,
            'currency' => $vendor->currency,
        ], $extra));
    }
}
