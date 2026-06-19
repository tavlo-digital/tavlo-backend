<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RestaurantPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->with(['features' => function ($query) {
                $query->orderBy('category')->orderBy('name');
            }])
            ->orderBy('monthly_price')
            ->orderBy('id')
            ->get();

        $planKeys = $this->planKeys($plans);
        $comparisonFeatures = $plans
            ->flatMap(fn (SubscriptionPlan $plan) => $plan->features)
            ->unique('id')
            ->sortBy(fn (Feature $feature) => strtolower("{$feature->category}|{$feature->name}"))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'hero' => [
                    'title' => 'Plans that fit your',
                    'titleHighlight' => 'needs',
                    'billingOptions' => [
                        'default' => 'monthly',
                        'monthlyLabel' => 'Monthly',
                        'yearlyLabel' => 'Yearly',
                        'yearlyBadge' => 'Save up to 30%',
                    ],
                ],
                'plans' => $plans->map(function (SubscriptionPlan $plan) use ($planKeys) {
                    $yearlyPrice = (float) $plan->yearly_price;

                    return [
                        'id' => $planKeys[$plan->id],
                        'name' => $plan->name,
                        'prices' => [
                            'monthly' => [
                                'amount' => (float) $plan->monthly_price,
                                'currency' => strtoupper($plan->currency ?? 'EUR'),
                                'period' => '/Month',
                            ],
                            'yearly' => [
                                'amount' => $yearlyPrice,
                                'currency' => strtoupper($plan->currency ?? 'EUR'),
                                'period' => '/Year',
                                'monthlyEquivalent' => round($yearlyPrice / 12, 2),
                            ],
                        ],
                        'features' => $plan->features->pluck('name')->values()->all(),
                    ];
                })->values(),
                'logoSection' => [
                    'title' => 'Join hundreds of restaurants with Tavlo',
                    'logos' => array_fill(0, 5, [
                        'name' => 'Qormuz',
                        'image' => 'https://cdn.example.com/logos/qormuz.svg',
                    ]),
                ],
                'comparison' => [
                    'title' => 'Compare Plans',
                    'plans' => $plans->map(fn (SubscriptionPlan $plan) => $planKeys[$plan->id])->values(),
                    'features' => $comparisonFeatures->map(function (Feature $feature) use ($plans, $planKeys) {
                        return [
                            'id' => Str::slug($feature->name) ?: "feature-{$feature->id}",
                            'label' => $feature->name,
                            'availability' => $plans->mapWithKeys(fn (SubscriptionPlan $plan) => [
                                $planKeys[$plan->id] => $plan->features->contains('id', $feature->id),
                            ])->all(),
                        ];
                    })->values(),
                ],
            ],
        ]);
    }

    private function planKeys(Collection $plans): array
    {
        $used = [];

        return $plans->mapWithKeys(function (SubscriptionPlan $plan) use (&$used) {
            $base = Str::slug($plan->name) ?: "plan-{$plan->id}";
            $key = isset($used[$base]) ? "{$base}-{$plan->id}" : $base;
            $used[$key] = true;

            return [$plan->id => $key];
        })->all();
    }
}
