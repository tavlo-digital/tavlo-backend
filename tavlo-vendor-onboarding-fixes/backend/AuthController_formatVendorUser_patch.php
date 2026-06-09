<?php
// ─────────────────────────────────────────────────────────────────────────────
// INSTRUCTIONS FOR WASIM
// ─────────────────────────────────────────────────────────────────────────────
// In AuthController.php, replace the formatVendorUser() private method with
// this version. It adds `status` and `liveStatus` to the user object so the
// frontend can read the real vendor status without an extra API call.
// ─────────────────────────────────────────────────────────────────────────────

private function formatVendorUser(Vendor $vendor): array
{
    // Eagerly load vendorSetting if not already loaded
    $vendor->loadMissing('vendorSetting');

    return [
        'id'               => $vendor->id,
        'vendorId'         => (string) $vendor->id,
        'vendorPublicId'   => $vendor->vendor_public_id,
        'vendor_public_id' => $vendor->vendor_public_id,
        'actorType'        => 'vendor',
        'role'             => 'manager',
        'name'             => $vendor->name,
        'restaurantName'   => $vendor->restaurant_name,
        'country'          => $vendor->country,
        'phone'            => $vendor->phone,
        'email'            => $vendor->email,
        'permissions'      => ['*'],
        'created_at'       => $vendor->created_at?->toISOString(),

        // ── Onboarding status fields (NEW) ──────────────────────────────────
        // status:     'pending' | 'active' | 'suspended' | 'deactivated'
        // liveStatus: 'not-live' | 'online' | 'offline'
        // isLiveAndDiscoverable: true when restaurant is visible on Tavlo
        'status'                => $vendor->status ?? 'pending',
        'liveStatus'            => $vendor->live_status ?? 'not-live',
        'isLiveAndDiscoverable' => (bool) ($vendor->vendorSetting?->is_live_and_discoverable ?? false),
    ];
}
