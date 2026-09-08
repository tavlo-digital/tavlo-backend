<?php

namespace Tests\Feature\Analytics\Concerns;

use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;

/**
 * AnalyticsController now gates every route on the vendor's current plan
 * carrying the "Basic Analytics" feature (see AnalyticsController::
 * hasAnalyticsAccess()) — without this, every test vendor would hit the
 * locked/reduced-preview response instead of the real payload the test is
 * actually exercising. Shared across the four Analytics test files rather
 * than copy-pasted so the feature name/shape can't drift out of sync
 * between them.
 */
trait GrantsAnalyticsFeature
{
    private function withAnalyticsAccess(Vendor $vendor): Vendor
    {
        $feature = Feature::firstOrCreate(
            ['name' => 'Basic Analytics'],
            ['description' => 'Sales overview & trends', 'category' => 'Analytics'],
        );

        $plan = SubscriptionPlan::firstOrCreate(
            ['name' => 'Test Plan (Analytics)'],
            ['monthly_price' => 0, 'yearly_price' => 0, 'max_users' => 99],
        );

        PlanFeature::firstOrCreate(
            ['plan_id' => $plan->id, 'feature_id' => $feature->id],
            ['is_inherited' => false],
        );

        Subscription::create([
            'vendor_id' => $vendor->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'start_date' => now(),
            'next_billing_date' => now()->addMonth(),
            'auto_renew' => true,
        ]);

        return $vendor;
    }
}
