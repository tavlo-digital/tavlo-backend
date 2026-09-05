<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Services\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeConnectController extends Controller
{
    public function __construct(private readonly StripeConnectService $stripeService) {}

    /**
     * POST /api/vendor/{vendorId}/stripe/connect
     * Create (or return existing) Stripe Express account.
     */
    public function createAccount(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $settings = $vendor->vendorSetting ?? new VendorSetting;

        if ($settings->stripe_account_id) {
            return response()->json([
                'stripeAccountId' => $settings->stripe_account_id,
                'alreadyExists' => true,
            ]);
        }

        $accountId = $this->stripeService->createExpressAccount($vendor);

        $vendor->vendorSetting()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            ['stripe_account_id' => $accountId]
        );

        return response()->json(['stripeAccountId' => $accountId]);
    }

    /**
     * POST /api/vendor/{vendorId}/stripe/onboarding-link
     * Generate a Stripe onboarding link for the Express account.
     */
    public function createOnboardingLink(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'refreshUrl' => ['required', 'url'],
            'returnUrl' => ['required', 'url'],
        ]);

        $settings = $vendor->vendorSetting;

        if (! $settings?->stripe_account_id) {
            return response()->json(['message' => 'No Stripe account found. Call connect first.'], 422);
        }

        $url = $this->stripeService->createOnboardingLink(
            $settings->stripe_account_id,
            $data['refreshUrl'],
            $data['returnUrl']
        );

        return response()->json(['onboardingUrl' => $url]);
    }

    /**
     * GET /api/vendor/{vendorId}/stripe/status
     * Get current Stripe Connect account status.
     */
    public function getStatus(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $settings = $vendor->vendorSetting;

        if (! $settings?->stripe_account_id) {
            return response()->json([
                'connected' => false,
                'onboardingComplete' => false,
            ]);
        }

        $status = $this->stripeService->getAccountStatus($settings->stripe_account_id);

        // Two-way sync: set true when charges_enabled, clear if Stripe later deactivates it
        if ($status['onboarding_complete'] !== (bool) $settings->stripe_onboarding_complete) {
            $settings->update(['stripe_onboarding_complete' => $status['onboarding_complete']]);
        }

        return response()->json([
            'connected' => true,
            'stripeAccountId' => $settings->stripe_account_id,
            'onboardingComplete' => $status['onboarding_complete'],
            'chargesEnabled' => $status['charges_enabled'],
            'payoutsEnabled' => $status['payouts_enabled'],
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/stripe/disconnect
     */
    public function disconnect(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $settings = $vendor->vendorSetting;

        if (! $settings?->stripe_account_id) {
            return response()->json(['message' => 'No Stripe account is connected.'], 422);
        }

        try {
            $this->stripeService->deleteAccount($settings->stripe_account_id);
        } catch (\Exception $e) {
            \Log::warning("Stripe account deletion failed for vendor {$vendor->id}: {$e->getMessage()}");
        }

        $settings->update([
            'stripe_account_id' => null,
            'stripe_onboarding_complete' => false,
            'stripe_enabled' => false,
        ]);

        return response()->json(['message' => 'Stripe account disconnected successfully.']);
    }

    // ----------------------------------------------------------------

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
