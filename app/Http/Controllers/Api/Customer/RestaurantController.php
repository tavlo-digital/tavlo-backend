<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use App\Models\CartItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\SpecialTag;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Services\LocaleService;
use App\Services\TaxCalculationService;
use App\Services\VendorDateTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RestaurantController extends Controller
{
    public function __construct(
        private readonly LocaleService $locales,
        private readonly VendorDateTimeService $dateTimes,
    ) {}

    /**
     * List all active menu categories across discoverable restaurants.
     */
    public function allCategories(): JsonResponse
    {
        $categories = MenuCategory::where('is_active', true)
            ->whereHas('vendor', fn ($q) => $q->whereHas('vendorSetting', fn ($s) => $s->where('is_live_and_discoverable', true)))
            ->with('masterCategory')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->display_name,
                'slug' => $category->display_slug,
                'icon' => $category->display_icon,
            ])
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
                'vendorSetting:id,vendor_id,logo_url,cover_photo_url,business_hours,enable_reservations,loyalty_enabled,points_per_euro,accept_on_site,stripe_enabled,stripe_account_id,stripe_onboarding_complete,date_format,time_format',
                'countryRecord:id,code,currency,timezone',
                'menuCategories' => fn ($q) => $q->where('is_active', true)->with('masterCategory'),
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
                $like = '%'.$search.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('restaurant_name', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhere('slug', 'like', $like)
                        ->orWhereHas('menuCategories', function ($mc) use ($like) {
                            $mc->where('is_active', true)
                                ->whereHas('masterCategory', fn ($master) => $master->where('name', 'like', $like));
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
                'takeaway' => $query->whereHas('takeawayQr'),
                'dine_in' => $query->whereHas('restaurantTables'),
                default => null,
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
            $cuisines = $vendor->menuCategories
                ->map(fn (MenuCategory $category) => $category->display_name)
                ->unique()
                ->values();
            $avgPrice = $vendor->menuItems()->where('is_active', true)->avg('price');
            $priceLabel = match (true) {
                $avgPrice === null => null,
                $avgPrice <= 10 => 'Budget-friendly',
                $avgPrice <= 25 => 'Mid-range',
                $avgPrice <= 50 => 'Fine dining',
                default => 'Premium',
            };

            $setting = $vendor->vendorSetting;

            // Open/closed + today hours from business_hours
            $isOpen = false;
            $todayHours = null;
            $businessHours = $setting?->business_hours ?? [];
            if (is_string($businessHours)) {
                $businessHours = json_decode($businessHours, true) ?: [];
            }
            $vendorNow = $this->dateTimes->vendorNow($vendor);
            $dayKey = strtolower($vendorNow->format('l'));
            if (isset($businessHours[$dayKey]) && ! ($businessHours[$dayKey]['closed'] ?? false)) {
                $open = $businessHours[$dayKey]['open'] ?? null;
                $close = $businessHours[$dayKey]['close'] ?? null;
                if ($open && $close) {
                    $todayHours = $this->dateTimes->formatLocalTime($open, $vendor)
                        .' – '.$this->dateTimes->formatLocalTime($close, $vendor);
                    $localTime = $vendorNow->format('H:i');
                    $isOpen = $localTime >= $open && $localTime <= $close;
                }
            }

            return [
                'vendor_public_id' => $vendor->vendor_public_id,
                'slug' => $vendor->slug,
                'restaurant_name' => $vendor->restaurant_name,
                'city' => $vendor->city,
                'address' => $vendor->address,
                'latitude' => $vendor->latitude !== null ? (float) $vendor->latitude : null,
                'longitude' => $vendor->longitude !== null ? (float) $vendor->longitude : null,
                'logo_url' => $setting?->logo_url,
                'cover_photo_url' => $setting?->cover_photo_url,
                'currency' => $vendor->currency,
                'cuisines' => $cuisines,
                'price_label' => $priceLabel,
                'avg_rating' => round($vendor->reviews_avg_rating ?? 0, 1),
                'review_count' => $vendor->reviews_count ?? 0,
                'is_open' => $isOpen,
                'today_hours' => $todayHours,
                'business_hours' => $this->dateTimes->formatBusinessHours($businessHours, $vendor),
                'payment_methods' => $this->paymentMethods($setting),
                'loyalty' => $setting?->loyalty_enabled ? [
                    'enabled' => true,
                    'points_per_euro' => $setting->points_per_euro,
                ] : ['enabled' => false],
                'service_types' => array_values(array_filter([
                    ($vendor->restaurant_tables_count ?? 0) > 0 ? 'dine_in' : null,
                    $vendor->takeawayQr ? 'takeaway' : null,
                    $setting?->enable_reservations ? 'reservation' : null,
                ])),
                'distance_km' => isset($vendor->distance_km) ? round($vendor->distance_km, 1) : null,
                'is_favorite' => in_array($vendor->id, $favoriteVendorIds, true),
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
                'vendorSetting:id,vendor_id,logo_url,cover_photo_url,business_hours,accept_on_site,stripe_enabled,stripe_account_id,stripe_onboarding_complete,enable_reservations,loyalty_enabled,points_per_euro,date_format,time_format',
                'countryRecord:id,code,currency,timezone',
                'menuCategories' => fn ($q) => $q->where('is_active', true)->with('masterCategory'),
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
        $vendorNow = $this->dateTimes->vendorNow($vendor);
        $dayKey = strtolower($vendorNow->format('l'));
        if (isset($businessHours[$dayKey]) && ! ($businessHours[$dayKey]['closed'] ?? false)) {
            $todayHours = $this->dateTimes->formatLocalTime($businessHours[$dayKey]['open'], $vendor)
                .' – '.$this->dateTimes->formatLocalTime($businessHours[$dayKey]['close'], $vendor);
            $localTime = $vendorNow->format('H:i');
            $isOpen = $localTime >= $businessHours[$dayKey]['open'] && $localTime <= $businessHours[$dayKey]['close'];
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
            'slug' => $vendor->slug,
            'restaurant_name' => $vendor->restaurant_name,
            'city' => $vendor->city,
            'address' => $vendor->address,
            'latitude' => $vendor->latitude !== null ? (float) $vendor->latitude : null,
            'longitude' => $vendor->longitude !== null ? (float) $vendor->longitude : null,
            'logo_url' => $setting->logo_url,
            'cover_photo_url' => $setting->cover_photo_url,
            'currency' => $vendor->currency,
            'cuisines' => $vendor->menuCategories
                ->map(fn (MenuCategory $category) => $category->display_name)
                ->values(),
            'avg_rating' => round($vendor->reviews_avg_rating ?? 0, 1),
            'review_count' => (int) $vendor->reviews_count,
            'is_open' => $isOpen,
            'today_hours' => $todayHours,
            'business_hours' => $this->dateTimes->formatBusinessHours($businessHours, $vendor),
            'distance_km' => $distanceKm,
            'payment_methods' => $this->paymentMethods($setting),
            'loyalty' => [
                'enabled' => (bool) $setting->loyalty_enabled,
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
    public function categories(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = $this->discoverableVendor($vendorPublicId);
        $locale = $this->locales->resolveCustomerLocale($request, $vendor);

        $categories = MenuCategory::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->with(['masterCategory', 'localizedTranslations'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $this->locales->translated(
                    $category,
                    'localizedTranslations',
                    'name',
                    $vendor,
                    $locale,
                    $category->display_name
                ),
                'slug' => $category->display_slug,
                'icon' => $category->display_icon,
                'sort_order' => $category->sort_order,
            ]);

        return response()->json($categories)
            ->header('Content-Language', $locale)
            ->header('Vary', 'Accept-Language');
    }

    /**
     * Get menu items for a restaurant, optionally filtered by category.
     */
    public function menu(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = $this->discoverableVendor($vendorPublicId);
        $locale = $this->locales->resolveCustomerLocale($request, $vendor);

        $query = MenuItem::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->where('available', true)
            ->with([
                'category.masterCategory',
                'category.localizedTranslations',
                'itemTranslations',
                'allergens:id,name',
                'tags:id,slug,label',
                'modifierGroups' => fn ($q) => $q->where('is_active', true)
                    ->orderByPivot('sort_order')
                    ->with([
                        'localizedTranslations',
                        'options' => fn ($o) => $o->where('is_active', true)
                            ->with('localizedTranslations')
                            ->orderBy('sort_order'),
                    ]),
            ]);

        if ($request->filled('category_id')) {
            $query->where('menu_category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $fallbackLanguages = $this->locales->fallbackChain($vendor, $locale);
            $query->where(function ($itemQuery) use ($search, $fallbackLanguages) {
                $itemQuery->where('name', 'like', $search)
                    ->orWhereHas('itemTranslations', fn ($translationQuery) => $translationQuery
                        ->whereIn('language', $fallbackLanguages)
                        ->where('name', 'like', $search));
            });
        }

        $items = $query->orderBy('sort_order')->get();

        // Compute popularity rank (by ordered_count desc)
        $ranked = $items->sortByDesc('ordered_count')->values();

        $vendorCountry = $vendor->country;
        $showNutrition = (bool) ($vendor->vendorSetting?->show_nutrition ?? true);

        $data = $items->map(function ($item) use ($ranked, $vendorCountry, $vendor, $locale, $showNutrition) {
            $rank = $ranked->search(fn ($r) => $r->id === $item->id);
            $vatRate = TaxCalculationService::itemVatRate($item, $vendorCountry);

            return [
                'id' => $item->id,
                'name' => $this->locales->translated(
                    $item,
                    'itemTranslations',
                    'name',
                    $vendor,
                    $locale,
                    $item->name
                ),
                'description' => $this->locales->translated(
                    $item,
                    'itemTranslations',
                    'description',
                    $vendor,
                    $locale,
                    $item->description
                ),
                'image_url' => $item->image_url,
                'price' => TaxCalculationService::gross((float) $item->price, $vatRate),
                'has_discount' => (bool) $item->has_discount,
                'discount_percent' => $item->has_discount ? (float) $item->discount_percent : null,
                'discounted_price' => $item->has_discount && $item->discounted_price !== null
                    ? TaxCalculationService::gross((float) $item->discounted_price, $vatRate)
                    : null,
                'vat_rate' => $vatRate,
                'tax_category' => $item->tax_category,
                'rating' => (float) ($item->rating ?? 0),
                'review_count' => (int) ($item->review_count ?? 0),
                'ordered_count' => (int) ($item->ordered_count ?? 0),
                'popularity_rank' => $rank !== false ? $rank + 1 : null,
                'calories' => $showNutrition ? $item->calories : null,
                'fat' => $showNutrition && $item->fat ? (float) $item->fat : null,
                'carbs' => $showNutrition && $item->carbs ? (float) $item->carbs : null,
                'protein' => $showNutrition && $item->protein ? (float) $item->protein : null,
                'dietary_preference' => $item->dietary_preference,
                'paid_addons' => $this->formatPaidAddonsGross($item->paid_addons ?? [], $item->tax_category ?? 'food', $vendorCountry),
                'free_addons' => $item->free_addons ?? [],
                'removable_items' => $item->removable_items ?? [],
                'category' => $item->category ? [
                    'id' => $item->category->id,
                    'name' => $this->locales->translated(
                        $item->category,
                        'localizedTranslations',
                        'name',
                        $vendor,
                        $locale,
                        $item->category->display_name
                    ),
                    'slug' => $item->category->display_slug,
                    'icon' => $item->category->display_icon,
                ] : null,
                'allergens' => $this->menuItemAllergenNames($item),
                'tags' => $this->menuItemTagLabels($item),
                'modifier_groups' => $item->modifierGroups->map(fn ($g) => $this->formatModifierGroup(
                    $g,
                    $vendor,
                    $locale,
                    $item->tax_category ?? 'food',
                    $vendorCountry
                ))->values(),
            ];
        });

        return response()->json($data)
            ->header('Content-Language', $locale)
            ->header('Vary', 'Accept-Language');
    }

    /**
     * Show a single menu item with full public details.
     */
    public function menuItem(Request $request, string $vendorPublicId, int $itemId): JsonResponse
    {
        $vendor = $this->discoverableVendor($vendorPublicId);
        $locale = $this->locales->resolveCustomerLocale($request, $vendor);

        $item = MenuItem::where('vendor_id', $vendor->id)
            ->where('id', $itemId)
            ->where('is_active', true)
            ->where('available', true)
            ->with([
                'category.masterCategory',
                'category.localizedTranslations',
                'itemTranslations',
                'allergens:id,name,icon',
                'tags:id,slug,label,icon',
                'modifierGroups' => fn ($q) => $q->where('is_active', true)
                    ->orderByPivot('sort_order')
                    ->with([
                        'localizedTranslations',
                        'options' => fn ($o) => $o->where('is_active', true)
                            ->with('localizedTranslations')
                            ->orderBy('sort_order'),
                    ]),
            ])
            ->firstOrFail();

        $vendorCountry = $vendor->country;
        $vatRate = TaxCalculationService::itemVatRate($item, $vendorCountry);
        $showNutrition = (bool) ($vendor->vendorSetting?->show_nutrition ?? true);

        return response()->json([
            'id' => $item->id,
            'name' => $this->locales->translated(
                $item,
                'itemTranslations',
                'name',
                $vendor,
                $locale,
                $item->name
            ),
            'description' => $this->locales->translated(
                $item,
                'itemTranslations',
                'description',
                $vendor,
                $locale,
                $item->description
            ),
            'image_url' => $item->image_url,
            'price' => TaxCalculationService::gross((float) $item->price, $vatRate),
            'has_discount' => (bool) $item->has_discount,
            'discount_percent' => $item->has_discount ? (float) $item->discount_percent : null,
            'discounted_price' => $item->has_discount && $item->discounted_price !== null
                ? TaxCalculationService::gross((float) $item->discounted_price, $vatRate)
                : null,
            'vat_rate' => $vatRate,
            'tax_category' => $item->tax_category,
            'available' => (bool) $item->available,
            'rating' => (float) ($item->rating ?? 0),
            'review_count' => (int) ($item->review_count ?? 0),
            'ordered_count' => (int) ($item->ordered_count ?? 0),
            'calories' => $showNutrition ? $item->calories : null,
            'fat' => $showNutrition && $item->fat ? (float) $item->fat : null,
            'carbs' => $showNutrition && $item->carbs ? (float) $item->carbs : null,
            'protein' => $showNutrition && $item->protein ? (float) $item->protein : null,
            'dietary_preference' => $item->dietary_preference,
            'paid_addons' => $this->formatPaidAddonsGross($item->paid_addons ?? [], $item->tax_category ?? 'food', $vendorCountry),
            'free_addons' => $item->free_addons ?? [],
            'removable_items' => $item->removable_items ?? [],
            'ingredients' => $item->ingredients,
            'category' => $item->category ? [
                'id' => $item->category->id,
                'name' => $this->locales->translated(
                    $item->category,
                    'localizedTranslations',
                    'name',
                    $vendor,
                    $locale,
                    $item->category->display_name
                ),
                'slug' => $item->category->display_slug,
                'icon' => $item->category->display_icon,
            ] : null,
            'allergens' => $this->formatMenuItemAllergens($item),
            'tags' => $this->formatMenuItemTags($item),
            'modifier_groups' => $item->modifierGroups->map(fn ($g) => $this->formatModifierGroup(
                $g,
                $vendor,
                $locale,
                $item->tax_category ?? 'food',
                $vendorCountry
            ))->values(),
        ])
            ->header('Content-Language', $locale)
            ->header('Vary', 'Accept-Language');
    }

    private function formatModifierGroup(
        $group,
        Vendor $vendor,
        string $locale,
        string $itemTaxCategory = 'food',
        string $vendorCountry = 'AT'
    ): array {
        $groupVatRate = TaxCalculationService::modifierGroupVatRate(
            $group->tax_category ?? '',
            $itemTaxCategory,
            $vendorCountry
        );

        return [
            'id' => $group->id,
            'name' => $this->locales->translated(
                $group,
                'localizedTranslations',
                'name',
                $vendor,
                $locale,
                $group->name
            ),
            'type' => $group->type,
            'is_required' => (bool) $group->is_required,
            'min_selection' => (int) $group->min_selection,
            'max_selection' => (int) $group->max_selection,
            'vat_rate' => $groupVatRate,
            'options' => $group->options->map(fn ($option) => [
                'id' => $option->id,
                'name' => $this->locales->translated(
                    $option,
                    'localizedTranslations',
                    'name',
                    $vendor,
                    $locale,
                    $option->name
                ),
                'price_adjustment' => TaxCalculationService::gross((float) $option->price_adjustment, $groupVatRate),
            ])->values(),
        ];
    }

    private function formatPaidAddonsGross(array $addons, string $itemTaxCategory, string $vendorCountry): array
    {
        return collect($addons)->map(function ($addon) use ($itemTaxCategory, $vendorCountry) {
            $vatRate = TaxCalculationService::addonVatRate($addon, $itemTaxCategory, $vendorCountry);

            return [
                'name' => $addon['name'],
                'price' => TaxCalculationService::gross((float) ($addon['price'] ?? 0), $vatRate),
                'vat_rate' => $vatRate,
            ];
        })->values()->all();
    }

    private function menuItemAllergenNames(MenuItem $item): Collection
    {
        return $item->allergens
            ->pluck('name')
            ->merge($this->stringValues($item->allergies))
            ->filter()
            ->unique(fn ($value) => Str::lower($value))
            ->values();
    }

    private function menuItemTagLabels(MenuItem $item): Collection
    {
        return $item->tags
            ->pluck('label')
            ->merge($this->stringValues($item->special_tags))
            ->filter()
            ->unique(fn ($value) => Str::lower($value))
            ->values();
    }

    private function formatMenuItemAllergens(MenuItem $item): Collection
    {
        $formatted = $item->allergens
            ->map(fn ($allergen) => [
                'id' => $allergen->id,
                'name' => $allergen->name,
                'icon' => $allergen->icon,
            ])
            ->keyBy(fn ($allergen) => Str::lower($allergen['name']));

        $jsonNames = $this->stringValues($item->allergies);
        $missingNames = $jsonNames
            ->reject(fn ($name) => $formatted->has(Str::lower($name)))
            ->values();

        if ($missingNames->isNotEmpty()) {
            Allergen::where('is_active', true)
                ->whereIn('name', $missingNames->all())
                ->get(['id', 'name', 'icon'])
                ->each(function (Allergen $allergen) use ($formatted) {
                    $formatted->put(Str::lower($allergen->name), [
                        'id' => $allergen->id,
                        'name' => $allergen->name,
                        'icon' => $allergen->icon,
                    ]);
                });

            $missingNames
                ->reject(fn ($name) => $formatted->has(Str::lower($name)))
                ->each(fn ($name) => $formatted->put(Str::lower($name), [
                    'id' => null,
                    'name' => $name,
                    'icon' => null,
                ]));
        }

        return $formatted->values();
    }

    private function formatMenuItemTags(MenuItem $item): Collection
    {
        $formatted = $item->tags
            ->map(fn ($tag) => [
                'id' => $tag->id,
                'slug' => $tag->slug,
                'label' => $tag->label,
                'icon' => $tag->icon,
            ])
            ->keyBy(fn ($tag) => Str::lower($tag['slug'] ?? $tag['label']));

        $jsonValues = $this->stringValues($item->special_tags);
        $missingValues = $jsonValues
            ->reject(fn ($value) => $formatted->has(Str::lower($value)))
            ->values();

        if ($missingValues->isNotEmpty()) {
            SpecialTag::where('is_active', true)
                ->where(function ($query) use ($missingValues) {
                    $query->whereIn('slug', $missingValues->all())
                        ->orWhereIn('label', $missingValues->all());
                })
                ->get(['id', 'slug', 'label', 'icon'])
                ->each(function (SpecialTag $tag) use ($formatted) {
                    $value = [
                        'id' => $tag->id,
                        'slug' => $tag->slug,
                        'label' => $tag->label,
                        'icon' => $tag->icon,
                    ];
                    $formatted->put(Str::lower($tag->slug), $value);
                    $formatted->put(Str::lower($tag->label), $value);
                });

            $missingValues
                ->reject(fn ($value) => $formatted->has(Str::lower($value)))
                ->each(fn ($value) => $formatted->put(Str::lower($value), [
                    'id' => null,
                    'slug' => $value,
                    'label' => $value,
                    'icon' => null,
                ]));
        }

        return $formatted
            ->unique(fn ($tag) => $tag['id'] ?? $tag['slug'])
            ->map(fn ($tag) => [
                'id' => $tag['id'],
                'label' => $tag['label'],
                'icon' => $tag['icon'],
            ])
            ->values();
    }

    private function stringValues(?array $values): Collection
    {
        return collect($values)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim($value))
            ->values();
    }

    /**
     * Get tables for a restaurant (for dine-in).
     */
    public function tables(string $vendorPublicId): JsonResponse
    {
        $vendor = $this->discoverableVendor($vendorPublicId);

        $tables = RestaurantTable::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->get(['id', 'number', 'name']);

        return response()->json($tables);
    }

    /**
     * Get public menu language settings for a restaurant.
     */
    public function languages(string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)
            ->whereHas('vendorSetting', fn ($q) => $q->where('is_live_and_discoverable', true))
            ->with('vendorSetting:id,vendor_id,supported_languages,date_format,time_format')
            ->firstOrFail();

        $setting = $vendor->vendorSetting;
        $availableLanguages = collect($this->locales->supportedLanguages($vendor));

        return response()->json([
            'vendor' => [
                'id' => $vendor->vendor_public_id,
                'name' => $vendor->restaurant_name ?? $vendor->name,
            ],
            'default_language' => 'en',
            'available_languages' => $availableLanguages->all(),
            'date_format' => $setting?->date_format ?? 'DD.MM.YYYY',
            'time_format' => $setting?->time_format ?? '24h',
            'languages' => $availableLanguages
                ->map(fn (string $code) => [
                    'code' => $code,
                    'name' => $this->languageName($code),
                    'is_default' => $code === 'en',
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Get reviews for a restaurant (public).
     */
    public function reviews(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = $this->discoverableVendor($vendorPublicId);

        $query = \App\Models\Review::where('vendor_id', $vendor->id)
            ->where('flagged', false)
            ->with([
                'customer:id,first_name,last_name,profile_picture',
                'order:id,table_scan_session_id',
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
            'lowest' => $query->orderBy('rating')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $reviews = $query->paginate($request->integer('per_page', 20));

        $cartItemsByOrderId = $this->cartItemsByReviewOrder(
            $reviews->getCollection()->pluck('order')->filter()
        );

        $reviews->getCollection()->transform(function ($review) use ($cartItemsByOrderId, $vendor) {
            $customer = $review->customer;
            $reviewerName = $customer
                ? trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))
                : 'Anonymous';

            $menuItems = $cartItemsByOrderId
                ->get($review->order_id, collect())
                ->map(function (CartItem $item) {
                    $menuItem = $item->menuItem;
                    $name = $menuItem?->name;

                    return [
                        'id' => $menuItem?->id,
                        'name' => $name,
                        'slug' => $name ? Str::slug($name) : null,
                        'image_url' => $menuItem?->image_url,
                        'quantity' => (int) $item->quantity,
                    ];
                })
                ->values()
                ->all();

            return [
                'review_public_id' => $review->review_public_id,
                'rating' => $review->rating,
                'text' => $review->text,
                'images' => $review->images ?: [],
                'created_at' => $this->dateTimes->formatDateTime($review->created_at, $vendor),
                'reviewer' => [
                    'name' => $reviewerName !== '' ? $reviewerName : 'Anonymous',
                    'profile_picture' => $customer?->profile_picture,
                ],
                'menu_items' => $menuItems,
                'vendor_reply' => $review->vendor_reply,
                'vendor_replied_at' => $this->dateTimes->formatDateTime($review->vendor_replied_at, $vendor),
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
                'star' => $star,
                'count' => $count,
                'percent' => $totalReviews > 0 ? round(($count / $totalReviews) * 100, 1) : 0,
            ];
        }

        $payload = $reviews->toArray();
        $payload['review_summary'] = [
            'average_rating' => $averageRating,
            'total_reviews' => $totalReviews,
            'rating_breakdown' => $breakdown,
        ];

        return response()->json($payload);
    }

    private function cartItemsByReviewOrder(Collection $orders): Collection
    {
        $orders = $orders->filter()->keyBy('id');

        if ($orders->isEmpty()) {
            return collect();
        }

        $orderIds = $orders->keys()->map(fn ($id) => (int) $id)->values();
        $sessionIds = $orders
            ->pluck('table_scan_session_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $cartItems = CartItem::with('menuItem:id,name,image_url')
            ->where(function ($query) use ($orderIds, $sessionIds) {
                if ($sessionIds->isNotEmpty()) {
                    $query->whereIn('table_scan_session_id', $sessionIds->all())
                        ->whereNull('order_id');
                }

                foreach ($orderIds as $orderId) {
                    $query->orWhere('order_id', $orderId);
                    $query->orWhereJsonContains('shared_order_ids', $orderId);
                }
            })
            ->orderBy('id')
            ->get();

        return $orders->mapWithKeys(function ($order, int $orderId) use ($cartItems) {
            $items = $cartItems
                ->filter(function (CartItem $item) use ($orderId) {
                    $orderIds = array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []);

                    return (int) $item->order_id === (int) $orderId
                        || in_array($orderId, $orderIds, true);
                })
                ->unique('id')
                ->values();

            return [$orderId => $items];
        });
    }

    /**
     * Get the public "About" profile for a restaurant.
     */
    public function about(Request $request, string $vendorPublicId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)
            ->whereHas('vendorSetting', fn ($q) => $q->where('is_live_and_discoverable', true))
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

        $paymentMethods = $this->paymentMethods($setting);

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
            'vendor_public_id' => $vendor->vendor_public_id,
            'restaurant_name' => $vendor->restaurant_name,
            'description' => $setting?->description,
            'years_of_experience' => $setting?->years_of_experience !== null ? (int) $setting->years_of_experience : null,
            'signature_recipes_count' => $setting?->signature_recipes_count !== null ? (int) $setting->signature_recipes_count : null,
            'happy_customers_count' => $setting?->happy_customers_count !== null ? (int) $setting->happy_customers_count : null,
            'restaurant_features' => $features,
            'payment_methods' => $paymentMethods,
            'vat_number' => $vendor->vat_number,
            'address' => $vendor->address,
            'city' => $vendor->city,
            'country' => $vendor->country,
            'latitude' => $vendor->latitude !== null ? (float) $vendor->latitude : null,
            'longitude' => $vendor->longitude !== null ? (float) $vendor->longitude : null,
            'business_hours' => $this->dateTimes->formatBusinessHours($businessHours, $vendor),
            'service_types' => array_values(array_filter([
                ($vendor->restaurant_tables_count ?? 0) > 0 ? 'dine_in' : null,
                $vendor->takeawayQr ? 'takeaway' : null,
                $setting?->enable_reservations ? 'reservation' : null,
            ])),
            'contact' => $contact,
            'is_favorite' => $this->isFavoriteFor($request, $vendor->id),
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

    private function discoverableVendor(string $vendorPublicId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorPublicId)
            ->whereHas('vendorSetting', fn ($q) => $q->where('is_live_and_discoverable', true))
            ->firstOrFail();
    }

    private function languageName(string $code): string
    {
        return [
            'en' => 'English',
            'de' => 'Deutsch (German)',
            'it' => 'Italiano (Italian)',
            'fr' => 'Français (French)',
            'ar' => 'العربية (Arabic)',
            'tr' => 'Türkçe (Turkish)',
            'zh' => '中文 (Chinese)',
            'ja' => '日本語 (Japanese)',
            'sr' => 'Српски (Serbian)',
            'cs' => 'Čeština (Czech)',
            'es' => 'Español (Spanish)',
            'nl' => 'Nederlands (Dutch)',
        ][$code] ?? Str::upper($code);
    }

    private function paymentMethods(?VendorSetting $setting): array
    {
        return [
            'on-site' => (bool) ($setting?->accept_on_site ?? true),
            'stripe' => (bool) (
                $setting?->stripe_enabled
                && $setting->stripe_account_id
                && $setting->stripe_onboarding_complete
            ),
        ];
    }
}
