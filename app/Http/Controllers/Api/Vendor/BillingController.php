<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
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
