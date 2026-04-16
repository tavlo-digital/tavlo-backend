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
     * List all active menu categories across discoverable restaurants.
     */
    public function allCategories(): JsonResponse
    {
        $categories = MenuCategory::where('is_active', true)
            ->whereHas('vendor', fn ($q) => $q->whereHas('vendorSetting', fn ($s) => $s->where('is_live_and_discoverable', true)))
            ->select('id', 'name', 'slug')
            ->distinct('name')
            ->orderBy('name')
            ->get()
            ->unique('name')
            ->values();

        return response()->json($categories);
    }

    /**
     * List all discoverable restaurants with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::whereHas('vendorSetting', fn ($q) => $q->where('is_live_and_discoverable', true))
            ->with([
                'vendorSetting:id,vendor_id,logo_url,cover_photo_url,currency,enable_reservations,loyalty_enabled,points_per_euro,accept_card,accept_cash',
                'menuCategories' => fn ($q) => $q->where('is_active', true)->select('id', 'vendor_id', 'name', 'slug'),
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->select([
                'id', 'vendor_public_id', 'slug', 'restaurant_name',
                'country', 'city', 'address', 'latitude', 'longitude',
            ]);

        // Text search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('restaurant_name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('city')) {
            $query->where('city', $request->city);
        }

        // Cuisine filter — show vendors that have the given menu category
        if ($request->filled('cuisine')) {
            $cuisineId = (int) $request->cuisine;
            $query->whereHas('menuCategories', function ($q) use ($cuisineId) {
                $q->where('is_active', true)
                  ->where('id', $cuisineId);
            });
        }

        // Price range filter — filter by avg menu item price brackets
        // 1 = $0-10, 2 = $10-25, 3 = $25-50, 4 = $50+
        if ($request->filled('price_range')) {
            $range = (int) $request->price_range;
            $query->whereHas('menuItems', function ($q) use ($range) {
                $q->where('is_active', true);
                match ($range) {
                    1 => $q->where('price', '<=', 10),
                    2 => $q->whereBetween('price', [10, 25]),
                    3 => $q->whereBetween('price', [25, 50]),
                    4 => $q->where('price', '>=', 50),
                    default => null,
                };
            });
        }

        // Service type filter (dine_in, takeaway, reservation)
        if ($request->filled('service_type')) {
            $type = $request->service_type;
            $query->whereHas('vendorSetting', function ($q) use ($type) {
                match ($type) {
                    'reservation' => $q->where('enable_reservations', true),
                    'takeaway' => $q->whereHas('vendor', fn ($v) => $v->whereHas('takeawayQr')),
                    default => null,
                };
            });
        }

        // Rating filter — minimum average rating
        if ($request->filled('rating')) {
            $query->having('reviews_avg_rating', '>=', (float) $request->rating);
        }

        // Distance filter & sorting — requires customer lat/lng
        $lat = $request->float('latitude');
        $lng = $request->float('longitude');

        if ($lat && $lng) {
            $query->selectRaw(
                '( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance_km',
                [$lat, $lng, $lat]
            );

            // Distance filter (in km)
            if ($request->filled('distance')) {
                $query->having('distance_km', '<=', (float) $request->distance);
            }
        }

        // Sort
        $sortBy = $request->input('sort_by', 'name');
        match ($sortBy) {
            'distance' => $lat && $lng ? $query->orderBy('distance_km') : $query->orderBy('restaurant_name'),
            'rating' => $query->orderByDesc('reviews_avg_rating'),
            default => $query->orderBy('restaurant_name'),
        };

        $restaurants = $query->paginate($request->integer('per_page', 20));

        // Append computed fields to each restaurant
        $restaurants->getCollection()->transform(function ($vendor) {
            $cuisines = $vendor->menuCategories->pluck('name')->unique()->values();
            $avgPrice = $vendor->menuItems()->where('is_active', true)->avg('price');
            $priceLabel = match (true) {
                $avgPrice === null => null,
                $avgPrice <= 10    => 'Budget-friendly',
                $avgPrice <= 25    => 'Mid-range',
                $avgPrice <= 50    => 'Fine dining',
                default            => 'Premium',
            };

            $setting = $vendor->vendorSetting;

            return [
                'vendor_public_id'    => $vendor->vendor_public_id,
                'slug'                => $vendor->slug,
                'restaurant_name'     => $vendor->restaurant_name,
                'city'                => $vendor->city,
                'address'             => $vendor->address,
                'logo_url'            => $setting?->logo_url,
                'cover_photo_url'     => $setting?->cover_photo_url,
                'currency'            => $setting?->currency,
                'cuisines'            => $cuisines,
                'price_label'         => $priceLabel,
                'avg_rating'          => round($vendor->reviews_avg_rating ?? 0, 1),
                'review_count'        => $vendor->reviews_count ?? 0,
                'payment_methods'     => [
                    'card' => (bool) $setting?->accept_card,
                    'cash' => (bool) $setting?->accept_cash,
                ],
                'loyalty'             => $setting?->loyalty_enabled ? [
                    'enabled'        => true,
                    'points_per_euro' => $setting->points_per_euro,
                ] : ['enabled' => false],
                'enable_reservations' => (bool) $setting?->enable_reservations,
                'distance_km'         => isset($vendor->distance_km) ? round($vendor->distance_km, 1) : null,
            ];
        });

        return response()->json($restaurants);
    }

    /**
     * Restaurant profile — public detail page.
     */
    public function show(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)
            ->whereHas('vendorSetting', fn ($q) => $q->where('is_live_and_discoverable', true))
            ->with([
                'vendorSetting:id,vendor_id,logo_url,cover_photo_url,business_hours,currency,accept_card,accept_cash,enable_reservations,loyalty_enabled,points_per_euro',
                'menuCategories' => fn ($q) => $q->where('is_active', true)->select('id', 'vendor_id', 'name'),
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->select([
                'id', 'vendor_public_id', 'slug', 'restaurant_name',
                'country', 'city', 'address', 'latitude', 'longitude',
            ])
            ->firstOrFail();

        $setting = $vendor->vendorSetting;

        // Determine open/closed from business_hours
        $isOpen = false;
        $todayHours = null;
        $businessHours = $setting->business_hours ?? [];
        $dayKey = strtolower(now()->format('l')); // e.g. "thursday"
        if (isset($businessHours[$dayKey]) && !($businessHours[$dayKey]['closed'] ?? false)) {
            $todayHours = $businessHours[$dayKey]['open'] . ' – ' . $businessHours[$dayKey]['close'];
            $now = now()->format('H:i');
            $isOpen = $now >= $businessHours[$dayKey]['open'] && $now <= $businessHours[$dayKey]['close'];
        }

        // Distance
        $distanceKm = null;
        if ($request->filled(['latitude', 'longitude']) && $vendor->latitude && $vendor->longitude) {
            $lat = (float) $request->latitude;
            $lng = (float) $request->longitude;
            $distanceKm = round(
                6371 * acos(
                    cos(deg2rad($lat)) * cos(deg2rad($vendor->latitude))
                    * cos(deg2rad($vendor->longitude) - deg2rad($lng))
                    + sin(deg2rad($lat)) * sin(deg2rad($vendor->latitude))
                ),
                1
            );
        }

        return response()->json([
            'vendor_public_id' => $vendor->vendor_public_id,
            'slug'             => $vendor->slug,
            'restaurant_name'  => $vendor->restaurant_name,
            'city'             => $vendor->city,
            'address'          => $vendor->address,
            'logo_url'         => $setting->logo_url,
            'cover_photo_url'  => $setting->cover_photo_url,
            'currency'         => $setting->currency,
            'cuisines'         => $vendor->menuCategories->pluck('name')->values(),
            'avg_rating'       => round($vendor->reviews_avg_rating ?? 0, 1),
            'review_count'     => (int) $vendor->reviews_count,
            'is_open'          => $isOpen,
            'today_hours'      => $todayHours,
            'distance_km'      => $distanceKm,
            'payment_methods'  => [
                'card' => (bool) $setting->accept_card,
                'cash' => (bool) $setting->accept_cash,
            ],
            'loyalty' => [
                'enabled'        => (bool) $setting->loyalty_enabled,
                'points_per_euro' => $setting->points_per_euro,
            ],
            'enable_reservations' => (bool) $setting->enable_reservations,
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
                'allergens:id,name',
                'tags:id,label',
                'modifierGroups' => fn ($q) => $q->with('options'),
            ]);

        if ($request->filled('category_id')) {
            $query->where('menu_category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $items = $query->orderBy('sort_order')->get();

        // Compute popularity rank (by ordered_count desc)
        $ranked = $items->sortByDesc('ordered_count')->values();

        $data = $items->map(function ($item) use ($ranked) {
            $rank = $ranked->search(fn ($r) => $r->id === $item->id);

            return [
                'id'                => $item->id,
                'name'              => $item->name,
                'description'       => $item->description,
                'image_url'         => $item->image_url,
                'price'             => (float) $item->price,
                'has_discount'      => (bool) $item->has_discount,
                'discount_percent'  => $item->has_discount ? (float) $item->discount_percent : null,
                'discounted_price'  => $item->has_discount ? (float) $item->discounted_price : null,
                'rating'            => (float) ($item->rating ?? 0),
                'review_count'      => (int) ($item->review_count ?? 0),
                'ordered_count'     => (int) ($item->ordered_count ?? 0),
                'popularity_rank'   => $rank !== false ? $rank + 1 : null,
                'calories'          => $item->calories,
                'dietary_preference' => $item->dietary_preference,
                'category'          => $item->category ? [
                    'id'   => $item->category->id,
                    'name' => $item->category->name,
                    'slug' => $item->category->slug,
                ] : null,
                'allergens'         => $item->allergens->pluck('name'),
                'tags'              => $item->tags->pluck('label'),
                'modifier_groups'   => $item->modifierGroups,
            ];
        });

        return response()->json($data);
    }

    /**
     * Show a single menu item with full public details.
     */
    public function menuItem(string $vendorPublicId, int $itemId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        $item = MenuItem::where('vendor_id', $vendor->id)
            ->where('id', $itemId)
            ->where('is_active', true)
            ->with([
                'category:id,name,slug',
                'allergens:id,name,icon',
                'tags:id,label,icon',
                'modifierGroups' => fn ($q) => $q->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')]),
            ])
            ->firstOrFail();

        return response()->json([
            'id'                 => $item->id,
            'name'               => $item->name,
            'description'        => $item->description,
            'image_url'          => $item->image_url,
            'price'              => (float) $item->price,
            'has_discount'       => (bool) $item->has_discount,
            'discount_percent'   => $item->has_discount ? (float) $item->discount_percent : null,
            'discounted_price'   => $item->has_discount ? (float) $item->discounted_price : null,
            'available'          => (bool) $item->available,
            'rating'             => (float) ($item->rating ?? 0),
            'review_count'       => (int) ($item->review_count ?? 0),
            'ordered_count'      => (int) ($item->ordered_count ?? 0),
            'calories'           => $item->calories,
            'fat'                => $item->fat ? (float) $item->fat : null,
            'carbs'              => $item->carbs ? (float) $item->carbs : null,
            'protein'            => $item->protein ? (float) $item->protein : null,
            'dietary_preference' => $item->dietary_preference,
            'ingredients'        => $item->ingredients,
            'category'           => $item->category ? [
                'id'   => $item->category->id,
                'name' => $item->category->name,
                'slug' => $item->category->slug,
            ] : null,
            'allergens' => $item->allergens->map(fn ($a) => [
                'id'   => $a->id,
                'name' => $a->name,
                'icon' => $a->icon,
            ]),
            'tags' => $item->tags->map(fn ($t) => [
                'id'    => $t->id,
                'label' => $t->label,
                'icon'  => $t->icon,
            ]),
            'modifier_groups' => $item->modifierGroups->map(fn ($g) => [
                'id'            => $g->id,
                'name'          => $g->name,
                'type'          => $g->type,
                'is_required'   => (bool) $g->is_required,
                'min_selection' => $g->min_selection,
                'max_selection' => $g->max_selection,
                'options'       => $g->options->map(fn ($o) => [
                    'id'               => $o->id,
                    'name'             => $o->name,
                    'price_adjustment' => (float) $o->price_adjustment,
                ]),
            ]),
        ]);
    }

    /**
     * Get tables for a restaurant (for dine-in).
     */
    public function tables(string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        $tables = RestaurantTable::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->get(['id', 'number', 'name']);

        return response()->json($tables);
    }
}
