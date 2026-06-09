<?php
// ─────────────────────────────────────────────────────────────────────────────
// INSTRUCTIONS FOR WASIM
// ─────────────────────────────────────────────────────────────────────────────
// 1. Add this method to BillingController.php
// 2. Add this route in routes/api/vendor.php BEFORE the {vendorId} billing routes:
//
//    Route::get('billing/plans', [BillingController::class, 'plans'])->name('billing.plans');
//
// 3. Add SubscriptionPlan to the imports at the top of BillingController if not present:
//    use App\Models\SubscriptionPlan;
// ─────────────────────────────────────────────────────────────────────────────

/**
 * GET /api/vendor/billing/plans
 *
 * Returns all active subscription plans with their features.
 * Used by the vendor onboarding activation flow to render the plans page.
 * No vendorId needed — plans are global.
 */
public function plans(Request $request): JsonResponse
{
    $plans = SubscriptionPlan::where('is_active', true)
        ->with(['features' => function ($q) {
            $q->orderBy('category')->orderBy('name');
        }])
        ->orderBy('monthly_price')
        ->get()
        ->map(fn (SubscriptionPlan $plan) => [
            'id'           => $plan->id,
            'name'         => $plan->name,
            'description'  => $plan->description,
            'monthlyPrice' => (float) $plan->monthly_price,
            'yearlyPrice'  => (float) $plan->yearly_price,
            'currency'     => $plan->currency ?? 'EUR',
            'isPopular'    => (bool) $plan->is_popular,
            'maxUsers'     => $plan->max_users,
            'features'     => $plan->features->map(fn ($f) => [
                'name'        => $f->name,
                'description' => $f->description,
                'category'    => $f->category,
                'isInherited' => (bool) $f->pivot->is_inherited,
            ])->values(),
        ]);

    return response()->json(['data' => $plans]);
}
