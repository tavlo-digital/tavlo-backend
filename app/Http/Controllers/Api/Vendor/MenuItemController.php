<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\TaxCategory;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function __construct(private readonly MediaService $media)
    {
    }

    /**
     * GET /api/vendor/menu/items
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $query = $vendor->menuItems()
            ->where('is_active', true)
            ->with(['category']);

        // Accept both `categoryId` and legacy `category_id`
        $categoryId = $request->input('categoryId') ?? $request->input('category_id');
        if (!empty($categoryId)) {
            $query->where('menu_category_id', $categoryId);
        }

        if ($request->filled('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', [strtolower('%' . $request->input('search') . '%')]);
        }

        if ($request->filled('available')) {
            $query->where('available', $request->boolean('available'));
        }

        $items = $query->orderBy('sort_order')->get();

        $stats = [
            'totalItems'      => $items->count(),
            'totalCategories' => $vendor->menuCategories()->count(),
            'averagePrice'    => $items->count() ? round((float) $items->avg('price'), 2) : 0,
            'averageRating'   => $items->count() ? round((float) $items->avg('rating'), 2) : 0,
        ];

        return response()->json([
            'data'  => $items->map(fn (MenuItem $item) => $this->formatItem($item)),
            'stats' => $stats,
        ]);
    }

    /**
     * POST /api/vendor/menu/items
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $data = $this->validatePayload($request, true);

        $category = $vendor->menuCategories()->findOrFail($data['categoryId']);

        [$vatRate, $taxSlug] = $this->resolveTax($data, $category, $vendor);

        $price           = (float) $data['price'];
        $hasDiscount     = (bool) ($data['hasDiscount'] ?? false);
        $discountPercent = (float) ($data['discountPercent'] ?? 0);
        $discountedPrice = ($hasDiscount && $discountPercent > 0)
            ? round($price * (1 - $discountPercent / 100), 2)
            : null;

        $maxSort = $vendor->menuItems()->where('menu_category_id', $category->id)->max('sort_order') ?? -1;

        $item = $vendor->menuItems()->create([
            'menu_category_id'   => $category->id,
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'price'              => $price,
            'image_url'          => $data['imageUrl'] ?? null,
            'available'          => $data['available'] ?? true,
            'is_active'          => true,
            'calories'           => $data['calories'] ?? 0,
            'fat'                => $data['fat'] ?? 0,
            'carbs'              => $data['carbs'] ?? 0,
            'protein'            => $data['protein'] ?? 0,
            'vat_rate'           => $vatRate,
            'tax_category'       => $taxSlug,
            'dietary_preference' => $data['dietaryPreference'] ?? null,
            'allergies'          => $data['allergies'] ?? [],
            'special_tags'       => $data['specialTags'] ?? [],
            'has_discount'       => $hasDiscount,
            'discount_percent'   => $discountPercent,
            'discounted_price'   => $discountedPrice,
            'paid_addons'        => $data['paidAddons'] ?? [],
            'free_addons'        => $data['freeAddons'] ?? [],
            'removable_items'    => $data['removableItems'] ?? [],
            'translations'       => $this->normalizeTranslations($data['translations'] ?? null),
            'ingredients'        => $data['ingredients'] ?? [],
            'sort_order'         => $maxSort + 1,
        ]);

        $item->load('category');

        return response()->json(['data' => $this->formatItem($item)], 201);
    }

    /**
     * GET /api/vendor/menu/items/{itemId}
     */
    public function show(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item   = $vendor->menuItems()
            ->where('is_active', true)
            ->with('category')
            ->findOrFail($itemId);

        return response()->json(['data' => $this->formatItem($item)]);
    }

    /**
     * PATCH /api/vendor/menu/items/{itemId}
     */
    public function update(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item   = $vendor->menuItems()->where('is_active', true)->findOrFail($itemId);

        $data = $this->validatePayload($request, false);

        $mapped = [];

        if (isset($data['categoryId'])) {
            $vendor->menuCategories()->findOrFail($data['categoryId']);
            $mapped['menu_category_id'] = $data['categoryId'];
        }

        $directFields = [
            'name'              => 'name',
            'description'       => 'description',
            'price'             => 'price',
            'imageUrl'          => 'image_url',
            'available'         => 'available',
            'calories'          => 'calories',
            'fat'                => 'fat',
            'carbs'              => 'carbs',
            'protein'            => 'protein',
            'dietaryPreference' => 'dietary_preference',
            'hasDiscount'       => 'has_discount',
            'discountPercent'   => 'discount_percent',
            'allergies'         => 'allergies',
            'specialTags'       => 'special_tags',
            'paidAddons'        => 'paid_addons',
            'freeAddons'        => 'free_addons',
            'removableItems'    => 'removable_items',
            'ingredients'       => 'ingredients',
        ];

        foreach ($directFields as $camel => $snake) {
            if (array_key_exists($camel, $data)) {
                $mapped[$snake] = $data[$camel];
            }
        }

        if (array_key_exists('translations', $data)) {
            // Merge with existing translations so partial updates don't drop other languages
            $existing = $item->translations ?? [];
            $incoming = $this->normalizeTranslations($data['translations']);
            $mapped['translations'] = array_merge($existing, $incoming);
        }

        // Tax: prefer explicit slug, then explicit ID, else inherit
        if (array_key_exists('taxCategory', $data) || array_key_exists('taxCategoryId', $data)) {
            $category = $item->category ?? $vendor->menuCategories()->find($mapped['menu_category_id'] ?? $item->menu_category_id);
            [$vatRate, $taxSlug] = $this->resolveTax($data, $category, $vendor);
            $mapped['tax_category'] = $taxSlug;
            $mapped['vat_rate']     = $vatRate;
        }

        $price           = (float) ($mapped['price'] ?? $item->price);
        $hasDiscount     = (bool) ($mapped['has_discount'] ?? $item->has_discount);
        $discountPercent = (float) ($mapped['discount_percent'] ?? $item->discount_percent);
        $mapped['discounted_price'] = ($hasDiscount && $discountPercent > 0)
            ? round($price * (1 - $discountPercent / 100), 2)
            : null;

        $item->update($mapped);
        $item->load('category');

        return response()->json(['data' => $this->formatItem($item)]);
    }

    /**
     * DELETE /api/vendor/menu/items/{itemId}
     * Soft delete if item has been ordered, otherwise hard delete.
     */
    public function destroy(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item   = $vendor->menuItems()->findOrFail($itemId);

        $hasOrders = OrderItem::where('menu_item_id', $item->id)->exists();

        if ($hasOrders) {
            $item->update(['is_active' => false, 'available' => false]);
            return response()->json(['message' => 'Menu item hidden (soft deleted — referenced in orders)']);
        }

        $item->delete();
        return response()->json(['message' => 'Menu item deleted']);
    }

    /**
     * PATCH /api/vendor/menu/items/{itemId}/toggle
     */
    public function toggleAvailability(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item   = $vendor->menuItems()->where('is_active', true)->findOrFail($itemId);

        $item->update(['available' => !$item->available]);
        $item->load('category');

        return response()->json(['data' => $this->formatItem($item)]);
    }

    /**
     * POST /api/vendor/menu/items/upload-image
     * Returns: { imageUrl: "<absolute media url>" }
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $path = $this->media->store(
            $request->file('image'),
            "menu-items/{$vendor->id}/photos"
        );

        return response()->json(['imageUrl' => $this->media->url($path)]);
    }

    // ----------------------------------------------------------------

    private function validatePayload(Request $request, bool $isCreate): array
    {
        $rules = [
            'categoryId'         => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:menu_categories,id'],
            'name'               => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'description'        => ['nullable', 'string', 'max:5000'],
            'price'              => [$isCreate ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'imageUrl'           => ['nullable', 'string', 'max:2000'],
            'available'          => ['sometimes', 'boolean'],
            'calories'           => ['sometimes', 'integer', 'min:0'],
            'fat'                => ['sometimes', 'numeric', 'min:0'],
            'carbs'              => ['sometimes', 'numeric', 'min:0'],
            'protein'            => ['sometimes', 'numeric', 'min:0'],
            'taxCategory'        => ['sometimes', 'nullable', 'string', 'max:64'],
            'taxCategoryId'      => ['sometimes', 'nullable', 'integer', 'exists:tax_categories,id'],
            'dietaryPreference'  => ['nullable', 'string', 'max:64'],
            'allergies'          => ['sometimes', 'array'],
            'allergies.*'        => ['string', 'max:64'],
            'specialTags'        => ['sometimes', 'array'],
            'specialTags.*'      => ['string', 'max:64'],
            'hasDiscount'        => ['sometimes', 'boolean'],
            'discountPercent'    => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'paidAddons'         => ['sometimes', 'array'],
            'paidAddons.*.name'  => ['required_with:paidAddons', 'string', 'max:255'],
            'paidAddons.*.price' => ['required_with:paidAddons', 'numeric', 'min:0'],
            'freeAddons'         => ['sometimes', 'array'],
            'freeAddons.*'       => ['string', 'max:255'],
            'removableItems'     => ['sometimes', 'array'],
            'removableItems.*'   => ['string', 'max:255'],
            // translations: accept either a nested map { en: {name, description}, ... }
            // or an array of objects [{language, name, description}, ...]
            'translations'       => ['sometimes'],
            'ingredients'        => ['sometimes', 'array'],
            'ingredients.*.ingredientId'   => ['sometimes', 'string'],
            'ingredients.*.ingredientName' => ['sometimes', 'string', 'max:255'],
            'ingredients.*.quantity'       => ['required_with:ingredients', 'numeric', 'min:0'],
            'ingredients.*.unit'           => ['sometimes', 'string', 'max:32'],
            'ingredients.*.isCritical'     => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }

    /**
     * Returns translations in nested-map shape for storage:
     *   { en: { name, description }, de: { name, description }, ... }
     */
    private function normalizeTranslations(mixed $value): array
    {
        if (empty($value) || !is_array($value)) {
            return [];
        }

        $out = [];

        // Detect array-of-objects shape: [{language, name, description}, ...]
        $isArrayOfObjects = array_is_list($value) && isset($value[0]) && is_array($value[0]) && isset($value[0]['language']);

        if ($isArrayOfObjects) {
            foreach ($value as $entry) {
                if (!is_array($entry) || empty($entry['language'])) {
                    continue;
                }
                $out[$entry['language']] = [
                    'name'        => $entry['name'] ?? '',
                    'description' => $entry['description'] ?? null,
                ];
            }
            return $out;
        }

        // Already in nested-map shape
        foreach ($value as $lang => $entry) {
            if (!is_string($lang) || !is_array($entry)) {
                continue;
            }
            $out[$lang] = [
                'name'        => $entry['name'] ?? '',
                'description' => $entry['description'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Returns [vatRate, slug].
     */
    private function resolveTax(array $data, $category, $vendor): array
    {
        // 1. explicit slug
        if (!empty($data['taxCategory'])) {
            $country = $this->resolveCountryCode($vendor->country ?? 'AT');
            $tc = TaxCategory::where('country', $country)
                ->where('slug', $data['taxCategory'])
                ->first();
            if ($tc) {
                return [(float) $tc->vat_rate, $tc->slug];
            }
        }

        // 2. explicit id
        if (!empty($data['taxCategoryId'])) {
            $tc = TaxCategory::find($data['taxCategoryId']);
            if ($tc) {
                return [(float) $tc->vat_rate, $tc->slug];
            }
        }

        // 3. inherit from category
        if ($category && $category->tax_category_id) {
            $tc = TaxCategory::find($category->tax_category_id);
            if ($tc) {
                return [(float) $tc->vat_rate, $tc->slug];
            }
        }

        // 4. country default
        $country = $this->resolveCountryCode($vendor->country ?? 'AT');
        $tc = TaxCategory::where('country', $country)->where('slug', 'food')->first();
        return [(float) ($tc?->vat_rate ?? 10), $tc?->slug ?? 'food'];
    }

    private function resolveCountryCode(string $country): string
    {
        $map = [
            'austria'        => 'AT',
            'germany'        => 'DE',
            'united kingdom' => 'GB',
            'uk'             => 'GB',
            'great britain'  => 'GB',
        ];
        $lower = strtolower(trim($country));
        return $map[$lower] ?? strtoupper(substr($country, 0, 2));
    }

    private function formatItem(MenuItem $item): array
    {
        return [
            'id'                => $item->id,
            'categoryId'        => $item->menu_category_id,
            'categoryName'      => $item->category?->name ?? '',
            'name'              => $item->name,
            'description'       => $item->description,
            'price'             => (float) $item->price,
            'imageUrl'          => $this->media->url($item->image_url),
            'available'         => (bool) $item->available,
            'isActive'          => (bool) $item->is_active,
            'calories'          => (int) $item->calories,
            'fat'               => (float) $item->fat,
            'carbs'             => (float) $item->carbs,
            'protein'           => (float) $item->protein,
            'vatRate'           => (float) $item->vat_rate,
            'taxCategory'       => $item->tax_category,
            'dietaryPreference' => $item->dietary_preference,
            'allergies'         => $item->allergies ?? [],
            'specialTags'       => $item->special_tags ?? [],
            'hasDiscount'       => (bool) $item->has_discount,
            'discountPercent'   => (float) $item->discount_percent,
            'discountedPrice'   => $item->discounted_price !== null ? (float) $item->discounted_price : null,
            'paidAddons'        => $item->paid_addons ?? [],
            'freeAddons'        => $item->free_addons ?? [],
            'removableItems'    => $item->removable_items ?? [],
            'translations'      => $item->translations ?? new \stdClass(),
            'ingredients'       => $item->ingredients ?? [],
            'rating'            => (float) $item->rating,
            'reviewCount'       => (int) $item->review_count,
            'orderedCount'      => (int) $item->ordered_count,
            'sortOrder'         => (int) $item->sort_order,
        ];
    }
}
