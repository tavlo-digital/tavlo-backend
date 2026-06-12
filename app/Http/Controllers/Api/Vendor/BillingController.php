<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billingService)
    {
    }

    /**
     * GET /api/vendor/billing/plans
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

    /**
     * GET /api/vendor/{vendorId}/billing
     */
    public function show(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        return response()->json(
            $this->billingService->getSubscriptionDetails($vendor)
        );
    }

    /**
     * GET /api/vendor/{vendorId}/billing/invoices
     */
    public function invoices(Request $request, string $vendorId): JsonResponse
    {
        $vendor  = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $paged   = $this->billingService->getInvoices($vendor);

        if (is_array($paged)) {
            return response()->json($paged);
        }

        return response()->json([
            'data' => $paged->items(),
            'total' => $paged->total(),
            'perPage' => $paged->perPage(),
            'currentPage' => $paged->currentPage(),
            'lastPage' => $paged->lastPage(),
        ]);
    }

    /**
     * GET /api/vendor/{vendorId}/billing/invoices/{invoiceId}/download
     */
    public function downloadInvoice(Request $request, string $vendorId, string $invoiceId): JsonResponse
    {
        $vendor  = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $invoice = $vendor->subscriptions()
            ->latest()
            ->first()
            ?->invoices()
            ->findOrFail($invoiceId);

        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found.'], 404);
        }

        $url = $invoice->pdf_url ?? $invoice->stripe_hosted_url;

        if (! $url) {
            return response()->json(['message' => 'Invoice PDF is not yet available.'], 404);
        }

        return response()->json(['url' => $url]);
    }

    /**
     * GET /api/vendor/{vendorId}/billing/usage
     */
    public function usage(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        return response()->json(
            $this->billingService->getUsageStats($vendor)
        );
    }

    /**
     * POST /api/vendor/{vendorId}/billing/upgrade
     */
    public function upgradePlan(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'planId' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        try {
            $subscription = $this->billingService->upgradePlan($vendor, (int) $data['planId']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Subscription plan not found.'], 404);
        }

        return response()->json([
            'message'      => 'Plan upgraded successfully.',
            'subscription' => $this->billingService->getSubscriptionDetails($vendor),
        ]);
    }

    /**
     * PATCH /api/vendor/{vendorId}/billing/cycle
     */
    public function changeCycle(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'cycle' => ['required', 'string', 'in:monthly,yearly'],
        ]);

        try {
            $this->billingService->changeBillingCycle($vendor, $data['cycle']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'No active subscription found.'], 404);
        }

        return response()->json([
            'message'      => 'Billing cycle updated successfully.',
            'subscription' => $this->billingService->getSubscriptionDetails($vendor),
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/billing/payment-method
     */
    public function updatePaymentMethod(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'cardBrand'               => ['sometimes', 'nullable', 'string', 'max:20'],
            'last4'                   => ['sometimes', 'nullable', 'string', 'size:4'],
            'expMonth'                => ['sometimes', 'nullable', 'string', 'max:2'],
            'expYear'                 => ['sometimes', 'nullable', 'string', 'max:4'],
            'stripePaymentMethodId'   => ['required', 'string', 'max:255'],
            'billingEmail'            => ['sometimes', 'nullable', 'email', 'max:255'],
        ]);

        $method = $this->billingService->updatePaymentMethod($vendor, $data);

        return response()->json([
            'message'       => 'Payment method updated successfully.',
            'paymentMethod' => [
                'brand'    => $method->card_brand,
                'last4'    => $method->last4,
                'expMonth' => $method->exp_month,
                'expYear'  => $method->exp_year,
                'isDefault' => $method->is_default,
            ],
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/billing/cancel
     */
    public function cancel(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        try {
            $this->billingService->cancelSubscription($vendor);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'No active subscription found.'], 404);
        }

        return response()->json([
            'message'      => 'Subscription cancelled successfully.',
            'subscription' => $this->billingService->getSubscriptionDetails($vendor),
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/billing/portal
     */
    public function portalSession(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $url = $this->billingService->createPortalSession($vendor);

        if (! $url) {
            return response()->json([
                'url'      => null,
                'message'  => 'Billing portal is not yet configured. Please contact support to update your payment method.',
            ]);
        }

        return response()->json(['url' => $url]);
    }

    /**
     * POST /api/vendor/{vendorId}/billing/checkout-session
     */
    public function checkoutSession(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'planId'       => ['required', 'integer', 'exists:subscription_plans,id'],
            'billingCycle' => ['required', 'string', 'in:monthly,yearly'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['planId']);

        $priceId = $data['billingCycle'] === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (!$priceId) {
            return response()->json([
                'message' => 'Stripe pricing is not configured for this plan. Please contact support.',
            ], 422);
        }

        $secret = config('services.stripe.secret');

        if (!$secret) {
            return response()->json([
                'message' => 'Payment system is not configured.',
            ], 503);
        }

        $stripe = new \Stripe\StripeClient($secret);

        $frontendBase = rtrim(config('services.vendor_frontend.url'), '/');

        $session = $stripe->checkout->sessions->create([
            'mode'        => 'subscription',
            'line_items'  => [[
                'price'    => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => $frontendBase . '/activate/success?plan=' . urlencode($plan->name) . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $frontendBase . '/activate/checkout',
            'client_reference_id' => (string) $vendor->id,
            'customer_email'      => $vendor->email,
            'metadata'            => [
                'vendor_id' => $vendor->id,
                'plan_id'   => $plan->id,
                'cycle'     => $data['billingCycle'],
            ],
        ]);

        return response()->json(['checkoutUrl' => $session->url]);
    }

    /**
     * POST /api/vendor/{vendorId}/billing/verify-checkout
     */
    public function verifyCheckout(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'sessionId' => ['required', 'string'],
        ]);

        $secret = config('services.stripe.secret');
        if (!$secret) {
            return response()->json(['message' => 'Payment system is not configured.'], 503);
        }

        $stripe = new \Stripe\StripeClient($secret);

        try {
            $session = $stripe->checkout->sessions->retrieve($data['sessionId']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Could not verify checkout session.'], 422);
        }

        if ($session->payment_status !== 'paid') {
            return response()->json([
                'verified' => false,
                'message'  => 'Payment has not been completed yet.',
            ]);
        }

        $metaVendorId = $session->metadata->vendor_id ?? $session->client_reference_id ?? null;
        if ((string) $metaVendorId !== (string) $vendor->id) {
            return response()->json(['message' => 'Session does not belong to this vendor.'], 403);
        }

        if (!$vendor->status || $vendor->status === 'pending') {
            $vendor->update(['status' => 'active']);
        }

        $existing = $vendor->subscriptions()
            ->where('stripe_subscription_id', $session->subscription)
            ->first();

        if (!$existing && $session->subscription) {
            $planId = $session->metadata->plan_id ?? null;
            $cycle = $session->metadata->cycle ?? 'monthly';

            \App\Models\Subscription::create([
                'vendor_id'              => $vendor->id,
                'plan_id'                => $planId,
                'status'                 => 'active',
                'billing_cycle'          => $cycle,
                'start_date'             => now(),
                'next_billing_date'      => $cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                'auto_renew'             => true,
                'stripe_subscription_id' => $session->subscription,
                'stripe_customer_id'     => $session->customer,
            ]);
        }

        return response()->json([
            'verified' => true,
            'status'   => $vendor->fresh()->status,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->orWhere('id', $vendorId)
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Forbidden.');
        }
    }
}
