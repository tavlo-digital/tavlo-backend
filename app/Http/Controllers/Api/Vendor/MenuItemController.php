<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    /**
     * GET /api/vendor/menu/items
     * List all menu items for the authenticated vendor.
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $query = $vendor->menuItems()->with('category');

        if ($request->filled('category_id')) {
            $query->where('menu_category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'ilike', "%{$search}%");
        }

        if ($request->filled('available')) {
            $query->where('available', $request->boolean('available'));
        }

        $items = $query->orderBy('sort_order')->get()
            ->map(fn (MenuItem $item) => $this->formatItem($item));

        return response()->json(['data' => $items]);
    }

    /**
     * POST /api/vendor/menu/items
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $data = $request->validate([
            'categoryId' => ['required', 'integer', 'exists:menu_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'imageUrl' => ['nullable', 'string', 'max:2000'],
            'available' => ['sometimes', 'boolean'],
            'calories' => ['sometimes', 'integer', 'min:0'],
            'fat' => ['sometimes', 'numeric', 'min:0'],
            'carbs' => ['sometimes', 'numeric', 'min:0'],
            'protein' => ['sometimes', 'numeric', 'min:0'],
            'taxCategory' => ['sometimes', 'string'],
            'dietaryPreference' => ['nullable', 'string'],
            'allergies' => ['sometimes', 'array'],
            'allergies.*' => ['string'],
            'specialTags' => ['sometimes', 'array'],
            'specialTags.*' => ['string'],
            'hasDiscount' => ['sometimes', 'boolean'],
            'discountPercent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'paidAddons' => ['sometimes', 'array'],
            'paidAddons.*.name' => ['required_with:paidAddons', 'string'],
            'paidAddons.*.price' => ['required_with:paidAddons', 'numeric', 'min:0'],
            'freeAddons' => ['sometimes', 'array'],
            'freeAddons.*' => ['string'],
            'removableItems' => ['sometimes', 'array'],
            'removableItems.*' => ['string'],
            'translations' => ['sometimes', 'array'],
            'ingredients' => ['sometimes', 'array'],
        ]);

        // Verify category belongs to this vendor
        $category = $vendor->menuCategories()->findOrFail($data['categoryId']);

        // Calculate VAT rate from tax category and vendor country
        $taxCategory = $data['taxCategory'] ?? $category->default_tax_category ?? 'food';
        $vatRate = $this->getVatRate($vendor->country ?? 'AT', $taxCategory);

        // Calculate discounted price
        $discountedPrice = null;
        if (!empty($data['hasDiscount']) && !empty($data['discountPercent'])) {
            $discountedPrice = $data['price'] * (1 - $data['discountPercent'] / 100);
        }

        $maxSort = $vendor->menuItems()->where('menu_category_id', $category->id)->max('sort_order') ?? -1;

        $item = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'image_url' => $data['imageUrl'] ?? null,
            'available' => $data['available'] ?? true,
            'calories' => $data['calories'] ?? 0,
            'fat' => $data['fat'] ?? 0,
            'carbs' => $data['carbs'] ?? 0,
            'protein' => $data['protein'] ?? 0,
            'vat_rate' => $vatRate,
            'tax_category' => $taxCategory,
            'dietary_preference' => $data['dietaryPreference'] ?? null,
            'allergies' => $data['allergies'] ?? [],
            'special_tags' => $data['specialTags'] ?? [],
            'has_discount' => $data['hasDiscount'] ?? false,
            'discount_percent' => $data['discountPercent'] ?? 0,
            'discounted_price' => $discountedPrice,
            'paid_addons' => $data['paidAddons'] ?? [],
            'free_addons' => $data['freeAddons'] ?? [],
            'removable_items' => $data['removableItems'] ?? [],
            'translations' => $data['translations'] ?? [],
            'ingredients' => $data['ingredients'] ?? [],
            'sort_order' => $maxSort + 1,
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
        $item = $vendor->menuItems()->with('category')->findOrFail($itemId);

        return response()->json(['data' => $this->formatItem($item)]);
    }

    /**
     * PATCH /api/vendor/menu/items/{itemId}
     */
    public function update(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item = $vendor->menuItems()->findOrFail($itemId);

        $data = $request->validate([
            'categoryId' => ['sometimes', 'integer', 'exists:menu_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'imageUrl' => ['nullable', 'string', 'max:2000'],
            'available' => ['sometimes', 'boolean'],
            'calories' => ['sometimes', 'integer', 'min:0'],
            'fat' => ['sometimes', 'numeric', 'min:0'],
            'carbs' => ['sometimes', 'numeric', 'min:0'],
            'protein' => ['sometimes', 'numeric', 'min:0'],
            'taxCategory' => ['sometimes', 'string'],
            'dietaryPreference' => ['nullable', 'string'],
            'allergies' => ['sometimes', 'array'],
            'allergies.*' => ['string'],
            'specialTags' => ['sometimes', 'array'],
            'specialTags.*' => ['string'],
            'hasDiscount' => ['sometimes', 'boolean'],
            'discountPercent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'paidAddons' => ['sometimes', 'array'],
            'freeAddons' => ['sometimes', 'array'],
            'removableItems' => ['sometimes', 'array'],
            'translations' => ['sometimes', 'array'],
            'ingredients' => ['sometimes', 'array'],
        ]);

        $mapped = [];

        if (isset($data['categoryId'])) {
            $vendor->menuCategories()->findOrFail($data['categoryId']);
            $mapped['menu_category_id'] = $data['categoryId'];
        }

        $directFields = [
            'name' => 'name',
            'description' => 'description',
            'price' => 'price',
            'imageUrl' => 'image_url',
            'available' => 'available',
            'calories' => 'calories',
            'fat' => 'fat',
            'carbs' => 'carbs',
            'protein' => 'protein',
            'taxCategory' => 'tax_category',
            'dietaryPreference' => 'dietary_preference',
            'allergies' => 'allergies',
            'specialTags' => 'special_tags',
            'hasDiscount' => 'has_discount',
            'discountPercent' => 'discount_percent',
            'paidAddons' => 'paid_addons',
            'freeAddons' => 'free_addons',
            'removableItems' => 'removable_items',
            'translations' => 'translations',
            'ingredients' => 'ingredients',
        ];

        foreach ($directFields as $camel => $snake) {
            if (array_key_exists($camel, $data)) {
                $mapped[$snake] = $data[$camel];
            }
        }

        // Recalculate VAT rate if tax category changed
        if (isset($mapped['tax_category'])) {
            $mapped['vat_rate'] = $this->getVatRate(
                $vendor->country ?? 'AT',
                $mapped['tax_category']
            );
        }

        // Recalculate discounted price
        $price = $mapped['price'] ?? $item->price;
        $hasDiscount = $mapped['has_discount'] ?? $item->has_discount;
        $discountPercent = $mapped['discount_percent'] ?? $item->discount_percent;

        if ($hasDiscount && $discountPercent > 0) {
            $mapped['discounted_price'] = $price * (1 - $discountPercent / 100);
        } else {
            $mapped['discounted_price'] = null;
        }

        $item->update($mapped);
        $item->load('category');

        return response()->json(['data' => $this->formatItem($item->fresh()->load('category'))]);
    }

    /**
     * DELETE /api/vendor/menu/items/{itemId}
     * Soft delete — sets available=false and is hidden.
     * A hard delete only happens if the item has never been ordered.
     */
    public function destroy(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item = $vendor->menuItems()->findOrFail($itemId);

        // For now, we hard-delete. When orders system is live,
        // check if item exists in orders and soft-delete instead.
        $item->delete();

        return response()->json(['message' => 'Menu item deleted']);
    }

    /**
     * PATCH /api/vendor/menu/items/{itemId}/toggle
     * Quick toggle availability.
     */
    public function toggleAvailability(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item = $vendor->menuItems()->findOrFail($itemId);

        $item->update(['available' => !$item->available]);
        $item->load('category');

        return response()->json(['data' => $this->formatItem($item)]);
    }

    // ----------------------------------------------------------------

    private function formatItem(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'categoryId' => $item->menu_category_id,
            'categoryName' => $item->category?->name ?? '',
            'name' => $item->name,
            'description' => $item->description,
            'price' => (float) $item->price,
            'imageUrl' => $item->image_url,
            'available' => $item->available,
            'calories' => $item->calories,
            'fat' => (float) $item->fat,
            'carbs' => (float) $item->carbs,
            'protein' => (float) $item->protein,
            'vatRate' => (float) $item->vat_rate,
            'taxCategory' => $item->tax_category,
            'dietaryPreference' => $item->dietary_preference,
            'allergies' => $item->allergies ?? [],
            'specialTags' => $item->special_tags ?? [],
            'hasDiscount' => $item->has_discount,
            'discountPercent' => (float) $item->discount_percent,
            'discountedPrice' => $item->discounted_price ? (float) $item->discounted_price : null,
            'paidAddons' => $item->paid_addons ?? [],
            'freeAddons' => $item->free_addons ?? [],
            'removableItems' => $item->removable_items ?? [],
            'translations' => $item->translations ?? (object) [],
            'ingredients' => $item->ingredients ?? [],
            'rating' => (float) $item->rating,
            'reviewCount' => $item->review_count,
            'orderedCount' => $item->ordered_count,
            'sortOrder' => $item->sort_order,
        ];
    }

    private function getVatRate(string $country, string $taxCategory): float
    {
        $rates = [
            'AT' => ['food' => 10, 'beverage_non_alcoholic' => 20, 'beverage_alcoholic' => 20],
            'DE' => ['food' => 7, 'beverage_non_alcoholic' => 19, 'beverage_alcoholic' => 19],
        ];

        return $rates[strtoupper($country)][$taxCategory]
            ?? $rates['AT'][$taxCategory]
            ?? 20;
    }
}
