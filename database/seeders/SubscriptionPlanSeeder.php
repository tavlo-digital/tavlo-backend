<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $featuresByName = Feature::all()->keyBy('name');

        // Basic Plan — 9 features
        $basic = SubscriptionPlan::updateOrCreate(
            ['name' => 'Basic'],
            [
                'description' => 'Essential tools to get your restaurant online with digital menus and QR ordering.',
                'monthly_price' => 99,
                'yearly_price' => 950,
                'max_users' => 3,
                'parent_plan_id' => null,
                'is_popular' => false,
                'is_active' => true,
            ]
        );

        $basicFeatures = [
            'Basic Menu Management',
            'Menu Categories',
            'Menu Item Images',
            'QR Code Ordering',
            'Table Management',
            'Card Payments',
            'Cash Payment Option',
            'Basic Analytics',
            'Email Support',
        ];

        $this->syncFeatures($basic, $basicFeatures, [], $featuresByName);

        // Standard Plan — inherits Basic + 9 own = 18 total
        $standard = SubscriptionPlan::updateOrCreate(
            ['name' => 'Standard'],
            [
                'description' => 'Advanced features for growing restaurants including analytics, tipping, and multi-language support.',
                'monthly_price' => 199,
                'yearly_price' => 1910,
                'max_users' => 10,
                'parent_plan_id' => $basic->id,
                'is_popular' => true,
                'is_active' => true,
            ]
        );

        $standardFeatures = [
            'Unlimited Menu Items',
            'Item Modifiers & Options',
            'Split Bill',
            'Tipping',
            'Advanced Analytics',
            'Export Reports',
            'Multi-Language Support',
            'Loyalty Program',
            'Priority Support',
        ];

        $this->syncFeatures($standard, $standardFeatures, $basicFeatures, $featuresByName);

        // Premium Plan — inherits Standard (which includes Basic) + 9 own = 27 total
        $premium = SubscriptionPlan::updateOrCreate(
            ['name' => 'Premium'],
            [
                'description' => 'Full-featured plan for multi-location restaurants with dedicated support, API access, and white-label options.',
                'monthly_price' => 299,
                'yearly_price' => 2870,
                'max_users' => 50,
                'parent_plan_id' => $standard->id,
                'is_popular' => false,
                'is_active' => true,
            ]
        );

        $premiumFeatures = [
            'Real-Time Dashboard',
            'Customer Reviews',
            'Email Marketing',
            'Dedicated Account Manager',
            'Multi-User Access',
            'API Access',
            'Webhooks',
            'White-Label Options',
            'POS Integration',
        ];

        $allInherited = array_merge($basicFeatures, $standardFeatures);
        $this->syncFeatures($premium, $premiumFeatures, $allInherited, $featuresByName);
    }

    private function syncFeatures(
        SubscriptionPlan $plan,
        array $directFeatureNames,
        array $inheritedFeatureNames,
        $featuresByName,
    ): void {
        PlanFeature::where('plan_id', $plan->id)->delete();

        foreach ($directFeatureNames as $name) {
            if ($feature = $featuresByName->get($name)) {
                PlanFeature::create([
                    'plan_id' => $plan->id,
                    'feature_id' => $feature->id,
                    'is_inherited' => false,
                ]);
            }
        }

        foreach ($inheritedFeatureNames as $name) {
            if ($feature = $featuresByName->get($name)) {
                PlanFeature::create([
                    'plan_id' => $plan->id,
                    'feature_id' => $feature->id,
                    'is_inherited' => true,
                ]);
            }
        }
    }
}
