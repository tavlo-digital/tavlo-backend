<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_endpoint_returns_active_admin_plans_and_dynamic_comparison(): void
    {
        $menuFeature = Feature::create([
            'name' => 'Menu Management',
            'description' => 'Manage menu items',
            'category' => 'Menu',
        ]);
        $analyticsFeature = Feature::create([
            'name' => 'Advanced Analytics',
            'description' => 'Detailed reporting',
            'category' => 'Analytics',
        ]);

        $basic = SubscriptionPlan::create([
            'name' => 'Basic',
            'monthly_price' => 99,
            'yearly_price' => 831.6,
            'currency' => 'EUR',
            'max_users' => 3,
            'is_active' => true,
        ]);
        $standard = SubscriptionPlan::create([
            'name' => 'Standard',
            'monthly_price' => 199,
            'yearly_price' => 1671.6,
            'currency' => 'EUR',
            'max_users' => 10,
            'is_active' => true,
        ]);
        $inactive = SubscriptionPlan::create([
            'name' => 'Legacy',
            'monthly_price' => 49,
            'yearly_price' => 490,
            'currency' => 'EUR',
            'max_users' => 1,
            'is_active' => false,
        ]);

        $basic->features()->attach($menuFeature->id, ['is_inherited' => false]);
        $standard->features()->attach([
            $menuFeature->id => ['is_inherited' => true],
            $analyticsFeature->id => ['is_inherited' => false],
        ]);
        $inactive->features()->attach($analyticsFeature->id, ['is_inherited' => false]);

        $response = $this->getJson('/api/restaurant/plans');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hero.billingOptions.default', 'monthly')
            ->assertJsonPath('data.plans.0.id', 'basic')
            ->assertJsonPath('data.plans.0.name', 'Basic')
            ->assertJsonPath('data.plans.0.prices.monthly.amount', 99)
            ->assertJsonPath('data.plans.0.prices.yearly.monthlyEquivalent', 69.3)
            ->assertJsonPath('data.plans.1.id', 'standard')
            ->assertJsonPath('data.comparison.plans', ['basic', 'standard'])
            ->assertJsonPath('data.comparison.features.0.id', 'advanced-analytics')
            ->assertJsonPath('data.comparison.features.0.availability.basic', false)
            ->assertJsonPath('data.comparison.features.0.availability.standard', true)
            ->assertJsonPath('data.comparison.features.1.id', 'menu-management')
            ->assertJsonPath('data.comparison.features.1.availability.basic', true)
            ->assertJsonPath('data.comparison.features.1.availability.standard', true)
            ->assertJsonCount(2, 'data.plans')
            ->assertJsonCount(5, 'data.logoSection.logos')
            ->assertJsonMissing(['name' => 'Legacy']);
    }
}
