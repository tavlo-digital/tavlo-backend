<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Get all favorite restaurants.
     */
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()
            ->favorites()
            ->with('vendorSetting:id,vendor_id,logo_url,cover_photo_url,description')
            ->get([
                'vendors.id', 'vendor_public_id', 'restaurant_name', 'slug',
                'city', 'address',
            ]);

        return response()->json($favorites);
    }

    /**
     * Add a restaurant to favorites.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_public_id' => ['required', 'string', 'exists:vendors,vendor_public_id'],
        ]);

        $vendor = Vendor::where('vendor_public_id', $validated['vendor_public_id'])->firstOrFail();

        $request->user()->favorites()->syncWithoutDetaching([$vendor->id]);

        return response()->json(['message' => 'Restaurant added to favorites.'], 201);
    }

    /**
     * Remove a restaurant from favorites.
     */
    public function destroy(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        $request->user()->favorites()->detach($vendor->id);

        return response()->json(['message' => 'Restaurant removed from favorites.']);
    }
}
