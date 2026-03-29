<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorSettingsController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/settings
     */
    public function show(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        return response()->json([
            'id' => (string) $vendor->id,
            'vendorPublicId' => $vendor->vendor_public_id,
            'name' => $vendor->name,
            'restaurantName' => $vendor->restaurant_name,
            'legalEntityName' => $vendor->legal_entity_name,
            'businessRegistrationNumber' => $vendor->business_registration_number,
            'vatNumber' => $vendor->vat_number,
            'website' => $vendor->website,
            'country' => $vendor->country,
            'city' => $vendor->city,
            'address' => $vendor->address,
            'phone' => $vendor->phone,
            'email' => $vendor->email,
            'status' => $vendor->status,
            'liveStatus' => $vendor->live_status,
        ]);
    }

    /**
     * PUT /api/vendor/{vendorId}/settings
     */
    public function update(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'restaurantName' => ['sometimes', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'city' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:500'],
            'phone' => ['sometimes', 'string', 'max:30'],
        ]);

        $mapped = [];
        if (isset($data['restaurantName'])) $mapped['restaurant_name'] = $data['restaurantName'];
        if (array_key_exists('website', $data)) $mapped['website'] = $data['website'];
        if (isset($data['city'])) $mapped['city'] = $data['city'];
        if (isset($data['address'])) $mapped['address'] = $data['address'];
        if (isset($data['phone'])) $mapped['phone'] = $data['phone'];

        $vendor->update($mapped);

        return $this->show($vendorId);
    }

    /**
     * GET /api/vendor/{vendorId}/subscription
     */
    public function subscription(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $subscription = $vendor->subscriptions()
            ->with('plan')
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json(null);
        }

        return response()->json([
            'id' => (string) $subscription->id,
            'planName' => $subscription->plan?->name,
            'billingCycle' => $subscription->billing_cycle,
            'status' => $subscription->status,
            'currentPeriodStart' => $subscription->current_period_start?->toISOString(),
            'currentPeriodEnd' => $subscription->current_period_end?->toISOString(),
            'autoRenew' => $subscription->auto_renew,
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/legal-info
     */
    public function submitLegalInfo(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'legalEntityName' => ['required', 'string', 'max:255'],
            'businessRegistrationNumber' => ['required', 'string', 'max:100'],
            'vatNumber' => ['required', 'string', 'max:50'],
        ]);

        // Create a pending change request instead of directly updating
        $vendor->requestChanges()->create([
            'field' => 'legal_info',
            'old_value' => json_encode([
                'legal_entity_name' => $vendor->legal_entity_name,
                'business_registration_number' => $vendor->business_registration_number,
                'vat_number' => $vendor->vat_number,
            ]),
            'new_value' => json_encode([
                'legal_entity_name' => $data['legalEntityName'],
                'business_registration_number' => $data['businessRegistrationNumber'],
                'vat_number' => $data['vatNumber'],
            ]),
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Legal info submitted for approval']);
    }

    // ----------------------------------------------------------------

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
            abort(403, 'Unauthorized');
        }
    }
}
