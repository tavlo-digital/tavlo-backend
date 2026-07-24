<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\SubscriptionPlan;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RestaurantPlanController extends Controller
{
    public function __construct(
        private readonly LocaleService $locales,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->locales->resolveHeaderLocale($request);

        $vendorFrontendUrl = rtrim((string) config('app.vendor_frontend_url'), '/');
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->with(['features' => function ($query) {
                $query->with('localizedTranslations')->orderBy('category')->orderBy('name');
            }, 'localizedTranslations'])
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
                'plans' => $plans->map(function (SubscriptionPlan $plan) use ($planKeys, $vendorFrontendUrl, $locale) {
                    $yearlyPrice = (float) $plan->yearly_price;

                    return [
                        'id' => $planKeys[$plan->id],
                        'name' => $this->translatedField($plan, 'name', $locale),
                        'description' => $this->translatedField($plan, 'description', $locale),
                        'link' => "{$vendorFrontendUrl}/activate?plan={$plan->id}",
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
                        'features' => $plan->features->map(fn (Feature $f) => $this->translatedField($f, 'name', $locale))->values()->all(),
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
                    'features' => $comparisonFeatures->map(function (Feature $feature) use ($plans, $planKeys, $locale) {
                        return [
                            'id' => Str::slug($feature->name) ?: "feature-{$feature->id}",
                            'label' => $this->translatedField($feature, 'name', $locale),
                            'availability' => $plans->mapWithKeys(fn (SubscriptionPlan $plan) => [
                                $planKeys[$plan->id] => $plan->features->contains('id', $feature->id),
                            ])->all(),
                        ];
                    })->values(),
                ],
            ],
        ]);
    }

    private function translatedField($model, string $field, string $locale): mixed
    {
        if ($locale !== 'en') {
            $value = $model->localizedTranslations->firstWhere('language', $locale)?->{$field};
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $model->{$field};
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
