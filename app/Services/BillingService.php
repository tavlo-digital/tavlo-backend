<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BillingService
{
    /**
     * Persist a checkout attempt before redirecting the vendor to Stripe.
     */
    public function createPendingSubscription(Vendor $vendor, SubscriptionPlan $plan, string $cycle): Subscription
    {
        return Subscription::create([
            'vendor_id' => $vendor->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'billing_cycle' => $cycle,
            'start_date' => Carbon::today(),
            'next_billing_date' => $cycle === 'yearly'
                ? Carbon::today()->addYear()
                : Carbon::today()->addMonth(),
            'auto_renew' => false,
        ]);
    }

    /**
     * Activate the local checkout row. This is safe to call from both the
     * webhook and the browser verification fallback.
     */
    public function activateCheckoutSession(object $session): string
    {
        if (($session->mode ?? null) !== 'subscription') {
            return 'ignored_non_subscription';
        }

        $vendorId = $session->metadata->vendor_id ?? $session->client_reference_id ?? null;
        $localSubscriptionId = $session->metadata->local_subscription_id ?? null;
        $planId = $session->metadata->plan_id ?? null;
        $cycle = $session->metadata->cycle ?? 'monthly';

        if (! $vendorId) {
            return 'missing_vendor_id';
        }

        $vendor = Vendor::find($vendorId);
        if (! $vendor) {
            return 'vendor_not_found';
        }

        return DB::transaction(function () use (
            $session,
            $vendor,
            $localSubscriptionId,
            $planId,
            $cycle
        ): string {
            $subscription = null;

            if ($localSubscriptionId || isset($session->id)) {
                $subscription = Subscription::query()
                    ->where('vendor_id', $vendor->id)
                    ->where(function ($query) use ($localSubscriptionId, $session) {
                        if ($localSubscriptionId) {
                            $query->whereKey($localSubscriptionId);
                        }

                        if (isset($session->id)) {
                            $method = $localSubscriptionId ? 'orWhere' : 'where';
                            $query->{$method}('stripe_checkout_session_id', $session->id);
                        }
                    })
                    ->lockForUpdate()
                    ->first();
            }

            if (! $subscription && isset($session->subscription)) {
                $subscription = Subscription::where(
                    'stripe_subscription_id',
                    $session->subscription
                )
                    ->where('vendor_id', $vendor->id)
                    ->lockForUpdate()
                    ->first();
            }

            if (
                $subscription
                && $subscription->status === 'active'
                && $subscription->stripe_subscription_id === ($session->subscription ?? null)
            ) {
                return 'already_processed';
            }

            if (! $subscription) {
                if (! $planId) {
                    return 'missing_plan_id';
                }

                $subscription = new Subscription([
                    'vendor_id' => $vendor->id,
                    'plan_id' => $planId,
                    'billing_cycle' => $cycle,
                ]);
            }

            $vendor->subscriptions()
                ->where('id', '!=', $subscription->id ?? 0)
                ->where('status', 'active')
                ->update([
                    'status' => 'superseded',
                    'cancelled_at' => Carbon::now(),
                    'auto_renew' => false,
                ]);

            $subscription->fill([
                'plan_id' => $planId ?: $subscription->plan_id,
                'status' => 'active',
                'billing_cycle' => $cycle,
                'start_date' => Carbon::now(),
                'next_billing_date' => $cycle === 'yearly'
                    ? Carbon::now()->addYear()
                    : Carbon::now()->addMonth(),
                'auto_renew' => true,
                'stripe_checkout_session_id' => $session->id ?? $subscription->stripe_checkout_session_id,
                'stripe_subscription_id' => $session->subscription ?? null,
                'stripe_customer_id' => $session->customer ?? null,
                'cancelled_at' => null,
            ])->save();

            if (! $vendor->status || $vendor->status === 'pending') {
                $vendor->update(['status' => 'active']);
            }

            SubscriptionEvent::firstOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'event_type' => 'subscription_created',
                ],
                [
                    'new_plan_id' => $subscription->plan_id,
                    'metadata' => [
                        'source' => 'stripe_checkout',
                        'checkout_session_id' => $session->id ?? null,
                    ],
                ]
            );

            return 'subscription_activated';
        });
    }

    public function expireCheckoutSession(object $session): string
    {
        $localSubscriptionId = $session->metadata->local_subscription_id ?? null;

        if (! $localSubscriptionId && ! isset($session->id)) {
            return 'pending_subscription_not_found';
        }

        $subscription = Subscription::query()
            ->where(function ($query) use ($localSubscriptionId, $session) {
                if ($localSubscriptionId) {
                    $query->whereKey($localSubscriptionId);
                }

                if (isset($session->id)) {
                    $method = $localSubscriptionId ? 'orWhere' : 'where';
                    $query->{$method}('stripe_checkout_session_id', $session->id);
                }
            })
            ->where('status', 'pending')
            ->first();

        if (! $subscription) {
            return 'pending_subscription_not_found';
        }

        $subscription->update([
            'status' => 'expired',
            'cancelled_at' => Carbon::now(),
            'auto_renew' => false,
        ]);

        return 'checkout_expired';
    }

    /**
     * Return the full billing details shape consumed by the frontend.
     */
    public function getSubscriptionDetails(Vendor $vendor): array
    {
        $subscription = $vendor->subscriptions()
            ->with('plan')
            ->latest()
            ->first();

        $paymentMethod = $vendor->paymentMethods()
            ->where('is_default', true)
            ->first()
            ?? $vendor->paymentMethods()->latest()->first();

        $invoices = $subscription
            ? $subscription->invoices()->latest()->take(5)->get()
            : collect();

        return [
            'subscriptionId' => $subscription ? (string) $subscription->id : null,
            'planName' => $subscription?->plan?->name,
            'billingCycle' => $subscription?->billing_cycle ?? 'monthly',
            'status' => $subscription?->status ?? 'none',
            'price' => $this->resolvePrice($subscription),
            'interval' => $subscription?->billing_cycle === 'yearly' ? 'year' : 'month',
            'nextBillingDate' => $subscription?->next_billing_date?->format('M j, Y'),
            'autoRenew' => $subscription?->auto_renew ?? false,
            'stripeSubscriptionId' => $subscription?->stripe_subscription_id,
            'paymentMethod' => $paymentMethod ? [
                'brand' => $paymentMethod->card_brand,
                'last4' => $paymentMethod->last4,
                'expMonth' => $paymentMethod->exp_month,
                'expYear' => $paymentMethod->exp_year,
                'isDefault' => $paymentMethod->is_default,
            ] : null,
            'billingEmail' => $paymentMethod?->billing_email ?? $vendor->email,
            'paymentMethodUpdated' => $paymentMethod?->updated_at?->format('M j, Y'),
        ];
    }

    /**
     * Return paginated invoices for the vendor.
     */
    public function getInvoices(Vendor $vendor, int $perPage = 10): LengthAwarePaginator|array
    {
        $subscription = $vendor->subscriptions()->latest()->first();

        if (! $subscription) {
            return [
                'data' => [],
                'total' => 0,
            ];
        }

        return $subscription->invoices()
            ->latest('billing_period_start')
            ->paginate($perPage);
    }

    /**
     * Return real-time usage statistics for the vendor.
     */
    public function getUsageStats(Vendor $vendor): array
    {
        $now = Carbon::now();

        $activeTables = $vendor->restaurantTables()
            ->where('is_active', true)
            ->count();

        $ordersThisMonth = $vendor->orders()
            ->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        $staffAccounts = $vendor->teamMembers()
            ->where('status', 'active')
            ->count();

        // One QR per active table + 1 for takeaway if vendor has it configured
        $takeawayCount = $vendor->takeawayQr ? 1 : 0;
        $qrCodes = $activeTables + $takeawayCount;

        return [
            'activeTables' => $activeTables,
            'ordersThisMonth' => $ordersThisMonth,
            'staffAccounts' => $staffAccounts,
            'qrCodes' => $qrCodes,
        ];
    }

    /**
     * Upgrade or change the vendor's subscription plan.
     *
     * @throws \InvalidArgumentException if the plan doesn't exist or is already active
     */
    public function upgradePlan(Vendor $vendor, int $newPlanId): Subscription
    {
        $newPlan = SubscriptionPlan::where('id', $newPlanId)
            ->where('is_active', true)
            ->firstOrFail();

        $subscription = $vendor->subscriptions()->latest()->first();

        if ($subscription && (int) $subscription->plan_id === $newPlanId) {
            throw new \InvalidArgumentException('The selected plan is already active.');
        }

        $previousPlanId = $subscription?->plan_id;

        if ($subscription) {
            $subscription->update(['plan_id' => $newPlanId]);
        } else {
            $subscription = Subscription::create([
                'vendor_id' => $vendor->id,
                'plan_id' => $newPlanId,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'start_date' => Carbon::today(),
                'next_billing_date' => Carbon::today()->addMonth(),
                'auto_renew' => true,
            ]);
        }

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => 'plan_changed',
            'previous_plan_id' => $previousPlanId,
            'new_plan_id' => $newPlanId,
            'metadata' => ['changed_by' => 'vendor'],
        ]);

        return $subscription->fresh(['plan']);
    }

    /**
     * Change the billing cycle (monthly/yearly) for the vendor's subscription.
     *
     * @throws \InvalidArgumentException if the cycle is already in use
     */
    public function changeBillingCycle(Vendor $vendor, string $cycle): Subscription
    {
        if (! in_array($cycle, ['monthly', 'yearly'])) {
            throw new \InvalidArgumentException('Billing cycle must be monthly or yearly.');
        }

        $subscription = $vendor->subscriptions()->latest()->firstOrFail();

        if ($subscription->billing_cycle === $cycle) {
            throw new \InvalidArgumentException("Billing cycle is already set to {$cycle}.");
        }

        $nextDate = $cycle === 'yearly'
            ? Carbon::parse($subscription->next_billing_date)->addYear()
            : Carbon::parse($subscription->next_billing_date)->addMonth();

        $subscription->update([
            'billing_cycle' => $cycle,
            'next_billing_date' => $nextDate,
        ]);

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => 'cycle_changed',
            'metadata' => ['new_cycle' => $cycle],
        ]);

        return $subscription->fresh(['plan']);
    }

    /**
     * Upsert the vendor's default payment method (card metadata only — no raw card numbers).
     */
    public function updatePaymentMethod(Vendor $vendor, array $data): PaymentMethod
    {
        // Mark all existing default methods as non-default first
        $vendor->paymentMethods()->where('is_default', true)->update(['is_default' => false]);

        return PaymentMethod::updateOrCreate(
            ['vendor_id' => $vendor->id, 'stripe_payment_method_id' => $data['stripePaymentMethodId'] ?? null],
            [
                'vendor_id' => $vendor->id,
                'card_brand' => $data['cardBrand'] ?? null,
                'last4' => $data['last4'] ?? null,
                'exp_month' => $data['expMonth'] ?? null,
                'exp_year' => $data['expYear'] ?? null,
                'stripe_payment_method_id' => $data['stripePaymentMethodId'] ?? null,
                'billing_email' => $data['billingEmail'] ?? $vendor->email,
                'is_default' => true,
            ]
        );
    }

    /**
     * Cancel the vendor's active subscription.
     *
     * @throws \InvalidArgumentException if already cancelled
     */
    public function cancelSubscription(Vendor $vendor): Subscription
    {
        $subscription = $vendor->subscriptions()->latest()->firstOrFail();

        if ($subscription->status === 'cancelled') {
            throw new \InvalidArgumentException('This subscription is already cancelled.');
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => Carbon::now(),
            'auto_renew' => false,
        ]);

        $hasOtherActive = $vendor->subscriptions()
            ->where('id', '!=', $subscription->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasOtherActive && $vendor->status === 'active') {
            $vendor->update(['status' => 'pending']);
        }

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => 'subscription_cancelled',
            'metadata' => ['cancelled_by' => 'vendor'],
        ]);

        return $subscription->fresh();
    }

    /**
     * Return a Stripe billing portal session URL for the vendor.
     * Returns null gracefully if Stripe is not configured.
     */
    public function createPortalSession(Vendor $vendor): ?string
    {
        $secret = config('services.stripe.secret');
        if (! $secret) {
            return null;
        }

        $subscription = $vendor->subscriptions()->latest()->first();
        if (! $subscription?->stripe_customer_id) {
            return null;
        }

        try {
            $stripe = new \Stripe\StripeClient($secret);
            $session = $stripe->billingPortal->sessions->create([
                'customer' => $subscription->stripe_customer_id,
                'return_url' => rtrim(config('services.vendor_frontend.url'), '/').'/billing?payment_updated=success',
            ]);

            return $session->url;
        } catch (\Exception $e) {
            \Log::warning('Stripe portal session failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Return all active subscription plans.
     */
    public function getAvailablePlans(): \Illuminate\Database\Eloquent\Collection
    {
        return SubscriptionPlan::where('is_active', true)->get();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolvePrice(?Subscription $subscription): ?float
    {
        if (! $subscription?->plan) {
            return null;
        }

        return $subscription->billing_cycle === 'yearly'
            ? (float) $subscription->plan->yearly_price
            : (float) $subscription->plan->monthly_price;
    }
}
