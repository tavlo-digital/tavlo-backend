<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\PlanFeature;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly LocaleService $locales,
    ) {}
    public function index(Request $request): Response
    {
        $tab = $request->route('tab', 'plans');

        $plans = SubscriptionPlan::query()
            ->with(['parentPlan:id,name', 'features.localizedTranslations', 'localizedTranslations'])
            ->withCount(['subscriptions as active_subs_count' => function ($q) {
                $q->where('status', 'active');
            }])
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->get()
            ->map(function (SubscriptionPlan $plan) {
                $activeSubs = $plan->active_subs_count;
                $mrr = $activeSubs * $plan->monthly_price;
                $yearlySavings = ($plan->monthly_price * 12) - $plan->yearly_price;

                $allFeatures = $this->getAllPlanFeatures($plan);
                $directFeatures = $plan->features->pluck('name')->toArray();
                $previewFeatures = array_slice($allFeatures, 0, 3);

                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'monthlyPrice' => (float) $plan->monthly_price,
                    'yearlyPrice' => (float) $plan->yearly_price,
                    'yearlySavings' => (float) $yearlySavings,
                    'maxUsers' => $plan->max_users,
                    'activeSubs' => $activeSubs,
                    'mrr' => '€' . number_format($mrr, 0, '.', ','),
                    'featureCount' => count($allFeatures),
                    'isPopular' => $plan->is_popular,
                    'parentPlanId' => $plan->parent_plan_id,
                    'parentPlanName' => $plan->parentPlan?->name,
                    'previewFeatures' => $previewFeatures,
                    'moreCount' => max(0, count($allFeatures) - 3),
                    'featureNames' => $directFeatures,
                    'stripeMonthlyPriceId' => $plan->stripe_monthly_price_id,
                    'stripeYearlyPriceId' => $plan->stripe_yearly_price_id,
                    'translations' => $this->locales->translationMap(
                        $plan,
                        'localizedTranslations',
                        ['name', 'description'],
                        ['name' => $plan->name, 'description' => $plan->description ?? '']
                    ),
                ];
            });

        $features = Feature::query()
            ->with(['requiredFeature:id,name', 'localizedTranslations'])
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category')
            ->map(function ($categoryFeatures, $category) {
                return [
                    'name' => $category,
                    'features' => $categoryFeatures->map(fn (Feature $f) => [
                        'id' => $f->id,
                        'name' => $f->name,
                        'description' => $f->description,
                        'requires' => $f->requiredFeature?->name,
                        'translations' => $this->locales->translationMap(
                            $f,
                            'localizedTranslations',
                            ['name', 'description'],
                            ['name' => $f->name, 'description' => $f->description ?? '']
                        ),
                    ])->values()->toArray(),
                ];
            })->values()->toArray();

        $activeSubscriptions = Subscription::query()
            ->with(['vendor:id,name,vendor_public_id', 'plan:id,name'])
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Subscription $sub) => [
                'vendor' => $sub->vendor->name ?? 'Unknown',
                'vendorId' => $sub->vendor->vendor_public_id ?? $sub->vendor_id,
                'plan' => $sub->plan->name ?? 'N/A',
                'planColor' => $this->getPlanColor($sub->plan->name ?? ''),
                'status' => ucfirst($sub->status),
                'startDate' => $sub->start_date->format('n/j/Y'),
                'nextBilling' => $sub->next_billing_date->format('n/j/Y'),
                'mrr' => '€' . number_format($sub->plan->monthly_price ?? 0, 0),
                'autoRenew' => $sub->auto_renew,
            ]);

        $overdueSubscriptions = Subscription::query()
            ->with(['vendor:id,name,vendor_public_id', 'plan:id,name,monthly_price'])
            ->where('status', 'overdue')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Subscription $sub) => [
                'vendor' => $sub->vendor->name ?? 'Unknown',
                'vendorId' => $sub->vendor->vendor_public_id ?? $sub->vendor_id,
                'plan' => $sub->plan->name ?? 'N/A',
                'planColor' => $this->getPlanColor($sub->plan->name ?? ''),
                'status' => ucfirst($sub->status),
                'startDate' => $sub->start_date->format('n/j/Y'),
                'nextBilling' => $sub->next_billing_date->format('n/j/Y'),
                'mrr' => '€' . number_format($sub->plan->monthly_price ?? 0, 0),
                'autoRenew' => $sub->auto_renew,
            ]);

        // Metrics
        $totalMrr = Subscription::query()
            ->where('status', 'active')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->selectRaw("SUM(CASE WHEN billing_cycle = 'yearly' THEN yearly_price / 12 ELSE monthly_price END) as total_mrr")
            ->value('total_mrr') ?? 0;

        $activeSubsCount = Subscription::where('status', 'active')->count();
        $planCount = SubscriptionPlan::where('is_active', true)->count();

        $overdueTotal = Subscription::query()
            ->where('status', 'overdue')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->sum('monthly_price');
        $overdueCount = Subscription::where('status', 'overdue')->count();

        return Inertia::render('admin/subscriptions/index', [
            'tab' => $tab,
            'plans' => $plans,
            'features' => $features,
            'languages' => $this->locales->languageOptions(),
            'activeSubscriptions' => $activeSubscriptions,
            'overdueSubscriptions' => $overdueSubscriptions,
            'metrics' => [
                'totalMrr' => (float) $totalMrr,
                'activeSubsCount' => $activeSubsCount,
                'planCount' => $planCount,
                'overdueTotal' => (float) $overdueTotal,
                'overdueCount' => $overdueCount,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('subscription_plans')],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'max_users' => ['required', 'integer', 'min:1'],
            'parent_plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'is_popular' => ['boolean'],
            'stripe_monthly_price_id' => ['nullable', 'string', 'max:255'],
            'stripe_yearly_price_id' => ['nullable', 'string', 'max:255'],
            'feature_ids' => ['array'],
            'feature_ids.*' => ['exists:features,id'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'feature_translations' => ['nullable', 'array'],
            'feature_translations.*' => ['array'],
            'feature_translations.*.*' => ['array'],
            'feature_translations.*.*.name' => ['nullable', 'string', 'max:255'],
            'feature_translations.*.*.description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated) {
            $plan = SubscriptionPlan::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'monthly_price' => $validated['monthly_price'],
                'yearly_price' => $validated['yearly_price'],
                'max_users' => $validated['max_users'],
                'parent_plan_id' => $validated['parent_plan_id'] ?? null,
                'is_popular' => $validated['is_popular'] ?? false,
                'stripe_monthly_price_id' => $validated['stripe_monthly_price_id'] ?? null,
                'stripe_yearly_price_id' => $validated['stripe_yearly_price_id'] ?? null,
            ]);

            $translations = $validated['translations'] ?? [];
            $translations['en'] = ['name' => $validated['name'], 'description' => $validated['description'] ?? ''];
            $this->locales->syncTranslations($plan, 'localizedTranslations', $translations, ['name', 'description']);

            $this->syncFeatureTranslations($validated['feature_translations'] ?? []);

            $featureIds = $validated['feature_ids'] ?? [];

            // Attach direct features
            foreach ($featureIds as $featureId) {
                PlanFeature::create([
                    'plan_id' => $plan->id,
                    'feature_id' => $featureId,
                    'is_inherited' => false,
                ]);
            }

            // Attach inherited features from parent
            if ($plan->parent_plan_id) {
                $parentFeatureIds = $this->getAllPlanFeatureIds($plan->parentPlan);
                foreach ($parentFeatureIds as $parentFeatureId) {
                    if (!in_array($parentFeatureId, $featureIds)) {
                        PlanFeature::create([
                            'plan_id' => $plan->id,
                            'feature_id' => $parentFeatureId,
                            'is_inherited' => true,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('admin.subscriptions.plans')
            ->with('success', 'Plan created successfully.');
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('subscription_plans')->ignore($plan->id)],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'yearly_price' => ['required', 'numeric', 'min:0'],
            'max_users' => ['required', 'integer', 'min:1'],
            'parent_plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'is_popular' => ['boolean'],
            'stripe_monthly_price_id' => ['nullable', 'string', 'max:255'],
            'stripe_yearly_price_id' => ['nullable', 'string', 'max:255'],
            'feature_ids' => ['array'],
            'feature_ids.*' => ['exists:features,id'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'feature_translations' => ['nullable', 'array'],
            'feature_translations.*' => ['array'],
            'feature_translations.*.*' => ['array'],
            'feature_translations.*.*.name' => ['nullable', 'string', 'max:255'],
            'feature_translations.*.*.description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($plan, $validated) {
            $plan->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'monthly_price' => $validated['monthly_price'],
                'yearly_price' => $validated['yearly_price'],
                'max_users' => $validated['max_users'],
                'parent_plan_id' => $validated['parent_plan_id'] ?? null,
                'is_popular' => $validated['is_popular'] ?? false,
                'stripe_monthly_price_id' => $validated['stripe_monthly_price_id'] ?? null,
                'stripe_yearly_price_id' => $validated['stripe_yearly_price_id'] ?? null,
            ]);

            // Remove old features and re-sync
            PlanFeature::where('plan_id', $plan->id)->delete();

            $featureIds = $validated['feature_ids'] ?? [];

            // Attach direct features
            foreach ($featureIds as $featureId) {
                PlanFeature::create([
                    'plan_id' => $plan->id,
                    'feature_id' => $featureId,
                    'is_inherited' => false,
                ]);
            }

            // Attach inherited features from parent
            if ($plan->parent_plan_id) {
                $plan->load('parentPlan');
                $parentFeatureIds = $this->getAllPlanFeatureIds($plan->parentPlan);
                foreach ($parentFeatureIds as $parentFeatureId) {
                    if (!in_array($parentFeatureId, $featureIds)) {
                        PlanFeature::create([
                            'plan_id' => $plan->id,
                            'feature_id' => $parentFeatureId,
                            'is_inherited' => true,
                        ]);
                    }
                }
            }

            // Cascade inherited features to child plans
            $this->cascadeInheritedFeatures($plan);

            $translations = $validated['translations'] ?? [];
            $translations['en'] = ['name' => $validated['name'], 'description' => $validated['description'] ?? ''];
            $this->locales->syncTranslations($plan, 'localizedTranslations', $translations, ['name', 'description']);

            $this->syncFeatureTranslations($validated['feature_translations'] ?? []);
        });

        return redirect()->route('admin.subscriptions.plans')
            ->with('success', 'Plan updated successfully.');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        $activeCount = $plan->subscriptions()->where('status', 'active')->count();

        if ($activeCount > 0) {
            return back()->withErrors([
                'plan' => "Cannot delete plan with {$activeCount} active subscription(s).",
            ]);
        }

        $plan->update(['is_active' => false]);

        return redirect()->route('admin.subscriptions.plans')
            ->with('success', 'Plan deactivated successfully.');
    }

    /**
     * Get all feature names for a plan including inherited ones.
     */
    private function getAllPlanFeatures(SubscriptionPlan $plan): array
    {
        $features = $plan->features->pluck('name')->toArray();

        if ($plan->parent_plan_id && $plan->parentPlan) {
            $parentFeatures = $this->getAllPlanFeatures($plan->parentPlan);
            $features = array_unique(array_merge($parentFeatures, $features));
        }

        return array_values($features);
    }

    /**
     * Get all feature IDs for a plan including inherited ones.
     */
    private function getAllPlanFeatureIds(SubscriptionPlan $plan): array
    {
        $plan->load('features');
        $ids = $plan->features->pluck('id')->toArray();

        if ($plan->parent_plan_id) {
            $plan->load('parentPlan');
            if ($plan->parentPlan) {
                $parentIds = $this->getAllPlanFeatureIds($plan->parentPlan);
                $ids = array_unique(array_merge($parentIds, $ids));
            }
        }

        return array_values($ids);
    }

    /**
     * Cascade inherited features to all child plans.
     */
    private function cascadeInheritedFeatures(SubscriptionPlan $plan): void
    {
        $childPlans = SubscriptionPlan::where('parent_plan_id', $plan->id)->get();

        foreach ($childPlans as $child) {
            $allParentFeatureIds = $this->getAllPlanFeatureIds($plan);
            $directFeatureIds = PlanFeature::where('plan_id', $child->id)
                ->where('is_inherited', false)
                ->pluck('feature_id')
                ->toArray();

            // Remove old inherited
            PlanFeature::where('plan_id', $child->id)
                ->where('is_inherited', true)
                ->delete();

            // Re-add inherited
            foreach ($allParentFeatureIds as $featureId) {
                if (!in_array($featureId, $directFeatureIds)) {
                    PlanFeature::create([
                        'plan_id' => $child->id,
                        'feature_id' => $featureId,
                        'is_inherited' => true,
                    ]);
                }
            }

            // Recurse to grandchildren
            $this->cascadeInheritedFeatures($child);
        }
    }

    private function syncFeatureTranslations(array $featureTranslations): void
    {
        foreach ($featureTranslations as $featureId => $translations) {
            $feature = Feature::find($featureId);
            if (! $feature) {
                continue;
            }
            $translations['en'] = ['name' => $feature->name, 'description' => $feature->description ?? ''];
            $this->locales->syncTranslations($feature, 'localizedTranslations', $translations, ['name', 'description']);
        }
    }

    private function getPlanColor(string $planName): string
    {
        return match (strtolower($planName)) {
            'premium' => 'purple',
            'standard' => 'blue',
            'basic' => 'gray',
            default => 'gray',
        };
    }
}
