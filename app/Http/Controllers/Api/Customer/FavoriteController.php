<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
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
            ->with([
                'vendorSetting:id,vendor_id,logo_url,cover_photo_url,description,business_hours',
                'menuCategories' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('masterCategory'),
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->get([
                'vendors.id', 'vendor_public_id', 'restaurant_name', 'slug',
                'city', 'address',
            ]);

        $dayKey = strtolower(now()->format('l'));
        $now = now()->format('H:i');

        $payload = $favorites->map(function ($vendor) use ($dayKey, $now) {
            $setting = $vendor->vendorSetting;

            $isOpen = false;
            $businessHours = $setting?->business_hours ?? [];
            if (is_string($businessHours)) {
                $businessHours = json_decode($businessHours, true) ?: [];
            }
            if (isset($businessHours[$dayKey]) && ! ($businessHours[$dayKey]['closed'] ?? false)) {
                $open = $businessHours[$dayKey]['open'] ?? null;
                $close = $businessHours[$dayKey]['close'] ?? null;
                if ($open && $close) {
                    $isOpen = $now >= $open && $now <= $close;
                }
            }

            return [
                'id' => $vendor->id,
                'vendor_public_id' => $vendor->vendor_public_id,
                'restaurant_name' => $vendor->restaurant_name,
                'slug' => $vendor->slug,
                'city' => $vendor->city,
                'address' => $vendor->address,
                'logo_url' => $setting?->logo_url,
                'cover_photo_url' => $setting?->cover_photo_url,
                'avg_rating' => round($vendor->reviews_avg_rating ?? 0, 1),
                'review_count' => $vendor->reviews_count ?? 0,
                'is_open' => $isOpen,
                'status' => $isOpen ? 'Open' : 'Closed',
                'business_hours' => $businessHours ?: null,
                'cuisines' => $vendor->menuCategories
                    ->map(fn (MenuCategory $category) => $category->display_name)
                    ->unique()
                    ->values(),
            ];
        });

        return response()->json($payload);
    }

    /**
     * Add a restaurant to favorites.
     */
    public function store(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

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
