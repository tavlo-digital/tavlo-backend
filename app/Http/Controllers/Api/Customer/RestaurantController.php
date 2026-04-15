<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    /**
     * List all discoverable restaurants with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::whereHas('vendorSetting', fn ($q) => $q->where('is_live_and_discoverable', true))
            ->with(['vendorSetting:id,vendor_id,description,logo_url,cover_photo_url,business_hours,currency,enable_reservations,loyalty_enabled'])
            ->select([
                'id', 'vendor_public_id', 'slug', 'restaurant_name',
                'country', 'city', 'address', 'phone',
            ]);

        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('restaurant_name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $restaurants = $query->paginate($request->integer('per_page', 20));

        return response()->json($restaurants);
    }

    /**
     * Show a single restaurant's details.
     */
    public function show(string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)
            ->whereHas('vendorSetting', fn ($q) => $q->where('is_live_and_discoverable', true))
            ->with([
                'vendorSetting:id,vendor_id,description,logo_url,cover_photo_url,business_hours,currency,enable_reservations,loyalty_enabled,points_per_euro,minimum_redemption_points,point_value,service_fee_rate',
                'reviews' => fn ($q) => $q->latest()->limit(5),
                'reviews.customer:id,first_name,last_name',
            ])
            ->select([
                'id', 'vendor_public_id', 'slug', 'restaurant_name',
                'country', 'city', 'address', 'phone',
            ])
            ->firstOrFail();

        $avgRating = $vendor->reviews()->avg('rating');
        $reviewCount = $vendor->reviews()->count();

        return response()->json([
            'restaurant' => $vendor,
            'avg_rating'  => round($avgRating ?? 0, 1),
            'review_count' => $reviewCount,
        ]);
    }

    /**
     * Get menu categories for a restaurant.
     */
    public function categories(string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        $categories = MenuCategory::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'sort_order']);

        return response()->json($categories);
    }

    /**
     * Get menu items for a restaurant, optionally filtered by category.
     */
    public function menu(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        $query = MenuItem::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->with([
                'category:id,name,slug',
                'allergens',
                'tags',
                'modifierGroups' => fn ($q) => $q->with('options'),
            ]);

        if ($request->filled('category_id')) {
            $query->where('menu_category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $items = $query->orderBy('sort_order')->get();

        return response()->json($items);
    }

    /**
     * Show a single menu item details.
     */
    public function menuItem(string $vendorPublicId, int $itemId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        $item = MenuItem::where('vendor_id', $vendor->id)
            ->where('id', $itemId)
            ->where('is_active', true)
            ->with([
                'category:id,name,slug',
                'allergens',
                'tags',
                'modifierGroups' => fn ($q) => $q->with('options'),
            ])
            ->firstOrFail();

        return response()->json($item);
    }

    /**
     * Get tables for a restaurant (for dine-in).
     */
    public function tables(string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        $tables = RestaurantTable::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->get(['id', 'number', 'name', 'qr_token']);

        return response()->json($tables);
    }
}
