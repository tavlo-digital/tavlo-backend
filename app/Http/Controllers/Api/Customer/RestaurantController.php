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
                'vendorSetting:id,vendor_id,logo_url,cover_photo_url,business_hours,currency,enable_reservations,loyalty_enabled,points_per_euro,accept_card,accept_cash',
                'menuCategories' => fn ($q) => $q->where('is_active', true)->select('id', 'vendor_id', 'name', 'slug'),
                'takeawayQr:id,vendor_id',
            ])
            ->withCount('restaurantTables')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->select([
                'id', 'vendor_public_id', 'slug', 'restaurant_name',
                'country', 'city', 'address', 'latitude', 'longitude',
            ]);

        // Text search — restaurant name, city, address, slug, or cuisine (menu category name)
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            if ($search !== '') {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('restaurant_name', 'like', $like)
                      ->orWhere('city', 'like', $like)
                      ->orWhere('address', 'like', $like)
                      ->orWhere('slug', 'like', $like)
                      ->orWhereHas('menuCategories', function ($mc) use ($like) {
                          $mc->where('is_active', true)->where('name', 'like', $like);
                      });
                });
            }
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

        // Service type filter
        // - dine_in:     restaurant has at least one active table (RestaurantTable)
        // - takeaway:    restaurant has a takeaway QR configured (VendorTakeawayQr)
        // - reservation: vendor_settings.enable_reservations = true
        if ($request->filled('service_type')) {
            $type = $request->service_type;
            match ($type) {
                'reservation' => $query->whereHas('vendorSetting', fn ($q) => $q->where('enable_reservations', true)),
                'takeaway'    => $query->whereHas('takeawayQr'),
                'dine_in'     => $query->whereHas('restaurantTables'),
                default       => null,
            };
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

        // Pre-fetch favorite vendor IDs for the authenticated customer (if any).
        $customer = $request->user('customer');
        $favoriteVendorIds = $customer
            ? $customer->favorites()->pluck('vendors.id')->all()
            : [];

        // Append computed fields to each restaurant
        $restaurants->getCollection()->transform(function ($vendor) use ($favoriteVendorIds) {
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

            // Open/closed + today hours from business_hours
            $isOpen = false;
            $todayHours = null;
            $businessHours = $setting?->business_hours ?? [];
            if (is_string($businessHours)) {
                $businessHours = json_decode($businessHours, true) ?: [];
            }
            $dayKey = strtolower(now()->format('l'));
            if (isset($businessHours[$dayKey]) && !($businessHours[$dayKey]['closed'] ?? false)) {
                $open  = $businessHours[$dayKey]['open']  ?? null;
                $close = $businessHours[$dayKey]['close'] ?? null;
                if ($open && $close) {
                    $todayHours = $open . ' – ' . $close;
                    $now = now()->format('H:i');
                    $isOpen = $now >= $open && $now <= $close;
                }
            }

            return [
                'vendor_public_id'    => $vendor->vendor_public_id,
                'slug'                => $vendor->slug,
                'restaurant_name'     => $vendor->restaurant_name,
                'city'                => $vendor->city,
                'address'             => $vendor->address,
                'latitude'            => $vendor->latitude !== null ? (float) $vendor->latitude : null,
                'longitude'           => $vendor->longitude !== null ? (float) $vendor->longitude : null,
                'logo_url'            => $setting?->logo_url,
                'cover_photo_url'     => $setting?->cover_photo_url,
                'currency'            => $setting?->currency,
                'cuisines'            => $cuisines,
                'price_label'         => $priceLabel,
                'avg_rating'          => round($vendor->reviews_avg_rating ?? 0, 1),
                'review_count'        => $vendor->reviews_count ?? 0,
                'is_open'             => $isOpen,
                'today_hours'         => $todayHours,
                'business_hours'      => $businessHours ?: null,
                'payment_methods'     => [
                    'card' => (bool) $setting?->accept_card,
                    'cash' => (bool) $setting?->accept_cash,
                ],
                'loyalty'             => $setting?->loyalty_enabled ? [
                    'enabled'        => true,
                    'points_per_euro' => $setting->points_per_euro,
                ] : ['enabled' => false],
                'service_types'       => array_values(array_filter([
                    ($vendor->restaurant_tables_count ?? 0) > 0 ? 'dine_in' : null,
                    $vendor->takeawayQr ? 'takeaway' : null,
                    $setting?->enable_reservations ? 'reservation' : null,
                ])),
                'distance_km'         => isset($vendor->distance_km) ? round($vendor->distance_km, 1) : null,
                'is_favorite'         => in_array($vendor->id, $favoriteVendorIds, true),
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
                'takeawayQr:id,vendor_id',
            ])
            ->withCount('restaurantTables')
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
        if (is_string($businessHours)) {
            $businessHours = json_decode($businessHours, true) ?: [];
        }
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
            'latitude'         => $vendor->latitude !== null ? (float) $vendor->latitude : null,
            'longitude'        => $vendor->longitude !== null ? (float) $vendor->longitude : null,
            'logo_url'         => $setting->logo_url,
            'cover_photo_url'  => $setting->cover_photo_url,
            'currency'         => $setting->currency,
            'cuisines'         => $vendor->menuCategories->pluck('name')->values(),
            'avg_rating'       => round($vendor->reviews_avg_rating ?? 0, 1),
            'review_count'     => (int) $vendor->reviews_count,
            'is_open'          => $isOpen,
            'today_hours'      => $todayHours,
            'business_hours'   => $businessHours ?: null,
            'distance_km'      => $distanceKm,
            'payment_methods'  => [
                'card' => (bool) $setting->accept_card,
                'cash' => (bool) $setting->accept_cash,
            ],
            'loyalty' => [
                'enabled'        => (bool) $setting->loyalty_enabled,
                'points_per_euro' => $setting->points_per_euro,
            ],
            'service_types' => array_values(array_filter([
                ($vendor->restaurant_tables_count ?? 0) > 0 ? 'dine_in' : null,
                $vendor->takeawayQr ? 'takeaway' : null,
                $setting->enable_reservations ? 'reservation' : null,
            ])),
            'is_favorite' => $this->isFavoriteFor($request, $vendor->id),
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

    /**
     * Get reviews for a restaurant (public).
     */
    public function reviews(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        $query = \App\Models\Review::where('vendor_id', $vendor->id)
            ->where('flagged', false)
            ->with([
                'customer:id,first_name,last_name,profile_picture',
                'order:id,items',
            ]);

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        if ($request->boolean('with_images')) {
            $query->whereNotNull('images');
        }

        $sort = $request->get('sort_by', 'recent');
        match ($sort) {
            'highest' => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'lowest'  => $query->orderBy('rating')->orderByDesc('created_at'),
            default   => $query->orderByDesc('created_at'),
        };

        $reviews = $query->paginate($request->integer('per_page', 20));

        // Collect all distinct item names across the paginated reviews' orders,
        // then look them up once in the vendor's menu for id/slug/image parity.
        $itemNames = collect();
        foreach ($reviews->getCollection() as $review) {
            foreach ((array) ($review->order?->items ?? []) as $line) {
                if (is_array($line) && !empty($line['name'])) {
                    $itemNames->push($line['name']);
                }
            }
        }
        $itemNames = $itemNames->unique()->values();

        $menuLookup = $itemNames->isEmpty()
            ? collect()
            : MenuItem::where('vendor_id', $vendor->id)
                ->whereIn('name', $itemNames)
                ->get(['id', 'name', 'image_url'])
                ->keyBy('name');

        $reviews->getCollection()->transform(function ($review) use ($menuLookup) {
            $customer = $review->customer;
            $reviewerName = $customer
                ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''))
                : 'Anonymous';

            $menuItems = [];
            foreach ((array) ($review->order?->items ?? []) as $line) {
                if (!is_array($line) || empty($line['name'])) {
                    continue;
                }
                $match = $menuLookup->get($line['name']);
                $menuItems[] = [
                    'id'        => $match?->id,
                    'name'      => $line['name'],
                    'slug'      => \Illuminate\Support\Str::slug($line['name']),
                    'image_url' => $match?->image_url,
                    'quantity'  => (int) ($line['quantity'] ?? $line['qty'] ?? 1),
                ];
            }

            return [
                'review_public_id' => $review->review_public_id,
                'rating'           => $review->rating,
                'text'             => $review->text,
                'images'           => $review->images ?: [],
                'created_at'       => $review->created_at?->toIso8601String(),
                'reviewer' => [
                    'name'            => $reviewerName !== '' ? $reviewerName : 'Anonymous',
                    'profile_picture' => $customer?->profile_picture,
                ],
                'menu_items'        => $menuItems,
                'vendor_reply'      => $review->vendor_reply,
                'vendor_replied_at' => $review->vendor_replied_at?->toIso8601String(),
            ];
        });

        // Aggregate summary across ALL non-flagged reviews for this vendor
        // (independent of filters/pagination so the breakdown is stable).
        $counts = \App\Models\Review::where('vendor_id', $vendor->id)
            ->where('flagged', false)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $totalReviews = (int) $counts->sum();
        $weighted = 0;
        foreach ($counts as $star => $count) {
            $weighted += (int) $star * (int) $count;
        }
        $averageRating = $totalReviews > 0 ? round($weighted / $totalReviews, 1) : 0;

        $breakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $count = (int) ($counts[$star] ?? 0);
            $breakdown[] = [
                'star'    => $star,
                'count'   => $count,
                'percent' => $totalReviews > 0 ? round(($count / $totalReviews) * 100, 1) : 0,
            ];
        }

        $payload = $reviews->toArray();
        $payload['review_summary'] = [
            'average_rating'    => $averageRating,
            'total_reviews'     => $totalReviews,
            'rating_breakdown'  => $breakdown,
        ];

        return response()->json($payload);
    }

    /**
     * Get the public "About" profile for a restaurant.
     */
    public function about(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)
            ->with([
                'vendorSetting',
                'takeawayQr:id,vendor_id',
            ])
            ->withCount('restaurantTables')
            ->firstOrFail();

        $setting = $vendor->vendorSetting;

        $businessHours = $setting?->business_hours ?? [];
        if (is_string($businessHours)) {
            $businessHours = json_decode($businessHours, true) ?: [];
        }

        $paymentMethods = [
            'cash'          => (bool) ($setting?->accept_cash),
            'card'          => (bool) ($setting?->accept_card),
            'visa'          => (bool) ($setting?->accept_visa),
            'mastercard'    => (bool) ($setting?->accept_mastercard),
            'amex'          => (bool) ($setting?->accept_amex),
            'apple_pay'     => (bool) ($setting?->accept_apple_pay),
            'google_pay'    => (bool) ($setting?->accept_google_pay),
            'bank_transfer' => (bool) ($setting?->accept_bank_transfer),
        ];

        $contact = [];
        if ($setting?->show_phone_public ?? true) {
            $contact['phone'] = $vendor->phone;
        }
        if ($setting?->show_email_public ?? false) {
            $contact['email'] = $vendor->email;
        }
        if ($setting?->show_website_public ?? true) {
            $contact['website'] = $vendor->website;
        }

        $features = array_values(array_filter(array_map(function ($item) {
            if (is_string($item)) {
                $title = trim($item);
                return $title === '' ? null : ['title' => $title, 'description' => null];
            }
            if (is_array($item)) {
                $title = isset($item['title']) ? trim((string) $item['title']) : '';
                if ($title === '') {
                    return null;
                }
                $description = isset($item['description']) && $item['description'] !== ''
                    ? (string) $item['description']
                    : null;
                return ['title' => $title, 'description' => $description];
            }
            return null;
        }, (array) ($setting?->restaurant_features ?? []))));

        return response()->json([
            'vendor_public_id'        => $vendor->vendor_public_id,
            'restaurant_name'         => $vendor->restaurant_name,
            'description'             => $setting?->description,
            'years_of_experience'     => $setting?->years_of_experience !== null ? (int) $setting->years_of_experience : null,
            'signature_recipes_count' => $setting?->signature_recipes_count !== null ? (int) $setting->signature_recipes_count : null,
            'happy_customers_count'   => $setting?->happy_customers_count !== null ? (int) $setting->happy_customers_count : null,
            'restaurant_features'     => $features,
            'payment_methods'         => $paymentMethods,
            'vat_number'              => $vendor->vat_number,
            'address'                 => $vendor->address,
            'city'                    => $vendor->city,
            'country'                 => $vendor->country,
            'latitude'                => $vendor->latitude !== null ? (float) $vendor->latitude : null,
            'longitude'               => $vendor->longitude !== null ? (float) $vendor->longitude : null,
            'business_hours'          => $businessHours ?: null,
            'service_types'           => array_values(array_filter([
                ($vendor->restaurant_tables_count ?? 0) > 0 ? 'dine_in' : null,
                $vendor->takeawayQr ? 'takeaway' : null,
                $setting?->enable_reservations ? 'reservation' : null,
            ])),
            'contact'                 => $contact,
            'is_favorite'             => $this->isFavoriteFor($request, $vendor->id),
        ]);
    }

    /**
     * True if the request's authenticated customer (if any) has favorited the given vendor.
     */
    private function isFavoriteFor(Request $request, int $vendorId): bool
    {
        $customer = $request->user('customer');
        if (! $customer) {
            return false;
        }
        return $customer->favorites()->where('vendors.id', $vendorId)->exists();
    }
}
