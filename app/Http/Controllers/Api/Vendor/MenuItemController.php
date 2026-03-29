<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\TaxCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    /**
     * GET /api/vendor/menu/items
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $query = $vendor->menuItems()
            ->where('is_active', true)
            ->with(['category', 'allergens', 'tags', 'modifierGroups.options', 'itemTranslations', 'recipeIngredients']);

        if ($request->filled('categoryId')) {
            $query->where('menu_category_id', $request->input('categoryId'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->input('search') . '%');
        }

        if ($request->filled('available')) {
            $query->where('available', $request->boolean('available'));
        }

        $items = $query->orderBy('sort_order')->get();

        $stats = [
            'totalItems'      => $items->count(),
            'totalCategories' => $vendor->menuCategories()->count(),
            'averagePrice'    => $items->count() ? round($items->avg('price'), 2) : 0,
            'averageRating'   => $items->count() ? round($items->avg('rating'), 2) : 0,
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

        $data = $request->validate([
            'categoryId'         => ['required', 'integer', 'exists:menu_categories,id'],
            'name'               => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'price'              => ['required', 'numeric', 'min:0'],
            'imageUrl'           => ['nullable', 'string', 'max:2000'],
            'available'          => ['sometimes', 'boolean'],
            'calories'           => ['sometimes', 'integer', 'min:0'],
            'fat'                => ['sometimes', 'numeric', 'min:0'],
            'carbs'              => ['sometimes', 'numeric', 'min:0'],
            'protein'            => ['sometimes', 'numeric', 'min:0'],
            'taxCategoryId'      => ['sometimes', 'nullable', 'integer', 'exists:tax_categories,id'],
            'dietaryPreference'  => ['nullable', 'string'],
            'allergenIds'        => ['sometimes', 'array'],
            'allergenIds.*'      => ['integer', 'exists:allergens,id'],
            'tagIds'             => ['sometimes', 'array'],
            'tagIds.*'           => ['integer', 'exists:special_tags,id'],
            'modifierGroupIds'   => ['sometimes', 'array'],
            'modifierGroupIds.*' => ['integer', 'exists:modifier_groups,id'],
            'hasDiscount'        => ['sometimes', 'boolean'],
            'discountPercent'    => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'translations'       => ['sometimes', 'array'],
            'translations.*.language'    => ['required_with:translations', 'string', 'max:10'],
            'translations.*.name'        => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'ingredients'        => ['sometimes', 'array'],
            'ingredients.*.inventoryItemId' => ['required_with:ingredients', 'integer'],
            'ingredients.*.quantity'        => ['required_with:ingredients', 'numeric', 'min:0'],
            'ingredients.*.unit'            => ['sometimes', 'string'],
            'ingredients.*.isCritical'      => ['sometimes', 'boolean'],
        ]);

        $category = $vendor->menuCategories()->findOrFail($data['categoryId']);

        [$taxCategoryId, $vatRate, $taxSlug] = $this->resolveTax($data, $category, $vendor);

        $discountedPrice = null;
        if (!empty($data['hasDiscount']) && !empty($data['discountPercent'])) {
            $discountedPrice = round($data['price'] * (1 - $data['discountPercent'] / 100), 2);
        }

        $maxSort = $vendor->menuItems()->where('menu_category_id', $category->id)->max('sort_order') ?? -1;

        $item = $vendor->menuItems()->create([
            'menu_category_id'  => $category->id,
            'name'              => $data['name'],
            'description'       => $data['description'] ?? null,
            'price'             => $data['price'],
            'image_url'         => $data['imageUrl'] ?? null,
            'available'         => $data['available'] ?? true,
            'is_active'         => true,
            'calories'          => $data['calories'] ?? 0,
            'fat'               => $data['fat'] ?? 0,
            'carbs'             => $data['carbs'] ?? 0,
            'protein'           => $data['protein'] ?? 0,
            'vat_rate'          => $vatRate,
            'tax_category'      => $taxSlug,
            'dietary_preference' => $data['dietaryPreference'] ?? null,
            'has_discount'      => $data['hasDiscount'] ?? false,
            'discount_percent'  => $data['discountPercent'] ?? 0,
            'discounted_price'  => $discountedPrice,
            'sort_order'        => $maxSort + 1,
        ]);

        $this->syncRelations($item, $data);

        $item->load(['category', 'allergens', 'tags', 'modifierGroups.options', 'itemTranslations', 'recipeIngredients']);

        return response()->json(['data' => $this->formatItem($item)], 201);
    }

    /**
     * GET /api/vendor/menu/items/{itemId}
     */
    public function show(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item = $vendor->menuItems()
            ->where('is_active', true)
            ->with(['category', 'allergens', 'tags', 'modifierGroups.options', 'itemTranslations', 'recipeIngredients'])
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

        $data = $request->validate([
            'categoryId'         => ['sometimes', 'integer', 'exists:menu_categories,id'],
            'name'               => ['sometimes', 'string', 'max:255'],
            'description'        => ['nullable', 'string', 'max:2000'],
            'price'              => ['sometimes', 'numeric', 'min:0'],
            'imageUrl'           => ['nullable', 'string', 'max:2000'],
            'available'          => ['sometimes', 'boolean'],
            'calories'           => ['sometimes', 'integer', 'min:0'],
            'fat'                => ['sometimes', 'numeric', 'min:0'],
            'carbs'              => ['sometimes', 'numeric', 'min:0'],
            'protein'            => ['sometimes', 'numeric', 'min:0'],
            'taxCategoryId'      => ['sometimes', 'nullable', 'integer', 'exists:tax_categories,id'],
            'dietaryPreference'  => ['nullable', 'string'],
            'allergenIds'        => ['sometimes', 'array'],
            'allergenIds.*'      => ['integer', 'exists:allergens,id'],
            'tagIds'             => ['sometimes', 'array'],
            'tagIds.*'           => ['integer', 'exists:special_tags,id'],
            'modifierGroupIds'   => ['sometimes', 'array'],
            'modifierGroupIds.*' => ['integer', 'exists:modifier_groups,id'],
            'hasDiscount'        => ['sometimes', 'boolean'],
            'discountPercent'    => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'translations'       => ['sometimes', 'array'],
            'translations.*.language'    => ['required_with:translations', 'string', 'max:10'],
            'translations.*.name'        => ['required_with:translations', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'ingredients'        => ['sometimes', 'array'],
            'ingredients.*.inventoryItemId' => ['required_with:ingredients', 'integer'],
            'ingredients.*.quantity'        => ['required_with:ingredients', 'numeric', 'min:0'],
            'ingredients.*.unit'            => ['sometimes', 'string'],
            'ingredients.*.isCritical'      => ['sometimes', 'boolean'],
        ]);

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
            'fat'               => 'fat',
            'carbs'             => 'carbs',
            'protein'           => 'protein',
            'dietaryPreference' => 'dietary_preference',
            'hasDiscount'       => 'has_discount',
            'discountPercent'   => 'discount_percent',
        ];

        foreach ($directFields as $camel => $snake) {
            if (array_key_exists($camel, $data)) {
                $mapped[$snake] = $data[$camel];
            }
        }

        if (isset($data['taxCategoryId'])) {
            $tc = TaxCategory::find($data['taxCategoryId']);
            $mapped['tax_category'] = $tc?->slug ?? $item->tax_category;
            $mapped['vat_rate']     = $tc?->vat_rate ?? $item->vat_rate;
        }

        $price          = $mapped['price'] ?? $item->price;
        $hasDiscount    = $mapped['has_discount'] ?? $item->has_discount;
        $discountPercent = $mapped['discount_percent'] ?? $item->discount_percent;
        $mapped['discounted_price'] = ($hasDiscount && $discountPercent > 0)
            ? round($price * (1 - $discountPercent / 100), 2)
            : null;

        $item->update($mapped);

        $this->syncRelations($item, $data);

        $item->load(['category', 'allergens', 'tags', 'modifierGroups.options', 'itemTranslations', 'recipeIngredients']);

        return response()->json(['data' => $this->formatItem($item->fresh()->load(['category', 'allergens', 'tags', 'modifierGroups.options', 'itemTranslations', 'recipeIngredients']))]);
    }

    /**
     * DELETE /api/vendor/menu/items/{itemId}
     * Soft delete if item has been ordered, otherwise hard delete.
     */
    public function destroy(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item   = $vendor->menuItems()->findOrFail($itemId);

        $hasOrders = \DB::table('order_items')->where('menu_item_id', $item->id)->exists();

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
        $item->load(['category', 'allergens', 'tags', 'modifierGroups.options', 'itemTranslations', 'recipeIngredients']);

        return response()->json(['data' => $this->formatItem($item)]);
    }

    // ----------------------------------------------------------------

    private function syncRelations(MenuItem $item, array $data): void
    {
        if (array_key_exists('allergenIds', $data)) {
            $item->allergens()->sync($data['allergenIds'] ?? []);
        }

        if (array_key_exists('tagIds', $data)) {
            $item->tags()->sync($data['tagIds'] ?? []);
        }

        if (array_key_exists('modifierGroupIds', $data)) {
            $sync = [];
            foreach ($data['modifierGroupIds'] ?? [] as $idx => $groupId) {
                $sync[$groupId] = ['sort_order' => $idx];
            }
            $item->modifierGroups()->sync($sync);
        }

        if (array_key_exists('translations', $data)) {
            foreach ($data['translations'] ?? [] as $t) {
                $item->itemTranslations()->updateOrCreate(
                    ['language' => $t['language']],
                    ['name' => $t['name'], 'description' => $t['description'] ?? null]
                );
            }
        }

        if (array_key_exists('ingredients', $data)) {
            $item->recipeIngredients()->delete();
            foreach ($data['ingredients'] ?? [] as $ing) {
                $item->recipeIngredients()->create([
                    'inventory_item_id' => $ing['inventoryItemId'],
                    'quantity'          => $ing['quantity'],
                    'unit'              => $ing['unit'] ?? null,
                    'is_critical'       => $ing['isCritical'] ?? false,
                ]);
            }
        }
    }

    private function resolveTax(array $data, $category, $vendor): array
    {
        if (!empty($data['taxCategoryId'])) {
            $tc = TaxCategory::find($data['taxCategoryId']);
            if ($tc) {
                return [$tc->id, $tc->vat_rate, $tc->slug];
            }
        }

        // Inherit from category
        if ($category->tax_category_id) {
            $tc = TaxCategory::find($category->tax_category_id);
            if ($tc) {
                return [$tc->id, $tc->vat_rate, $tc->slug];
            }
        }

        // Fall back to country default
        $country = $this->resolveCountryCode($vendor->country ?? 'AT');
        $tc = TaxCategory::where('country', $country)->where('slug', 'food')->first();
        return [$tc?->id, $tc?->vat_rate ?? 10, $tc?->slug ?? 'food'];
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
        $translations = [];
        foreach ($item->itemTranslations as $t) {
            $translations[$t->language] = [
                'language'    => $t->language,
                'name'        => $t->name,
                'description' => $t->description,
            ];
        }

        return [
            'id'                => $item->id,
            'categoryId'        => $item->menu_category_id,
            'categoryName'      => $item->category?->name ?? '',
            'name'              => $item->name,
            'description'       => $item->description,
            'price'             => (float) $item->price,
            'imageUrl'          => $item->image_url,
            'available'         => $item->available,
            'isActive'          => $item->is_active,
            'calories'          => $item->calories,
            'fat'               => (float) $item->fat,
            'carbs'             => (float) $item->carbs,
            'protein'           => (float) $item->protein,
            'vatRate'           => (float) $item->vat_rate,
            'taxCategory'       => $item->tax_category,
            'dietaryPreference' => $item->dietary_preference,
            'allergens'         => $item->allergens->map(fn ($a) => [
                'id'   => $a->id,
                'name' => $a->name,
                'icon' => $a->icon ?? null,
            ])->values()->toArray(),
            'tags'              => $item->tags->map(fn ($t) => [
                'id'    => $t->id,
                'slug'  => $t->slug,
                'label' => $t->label,
                'icon'  => $t->icon ?? null,
            ])->values()->toArray(),
            'modifierGroups'    => $item->modifierGroups->map(fn ($mg) => [
                'id'           => $mg->id,
                'name'         => $mg->name,
                'type'         => $mg->type,
                'minSelection' => $mg->min_selection,
                'maxSelection' => $mg->max_selection,
                'isRequired'   => $mg->is_required,
                'sortOrder'    => $mg->pivot?->sort_order ?? 0,
                'options'      => $mg->options->map(fn ($o) => [
                    'id'              => $o->id,
                    'name'            => $o->name,
                    'priceAdjustment' => (float) $o->price_adjustment,
                    'sortOrder'       => $o->sort_order,
                    'isActive'        => $o->is_active,
                ])->values()->toArray(),
            ])->values()->toArray(),
            'translations'      => $translations,
            'ingredients'       => $item->recipeIngredients->map(fn ($ri) => [
                'id'              => $ri->id,
                'inventoryItemId' => $ri->inventory_item_id,
                'quantity'        => (float) $ri->quantity,
                'unit'            => $ri->unit,
                'isCritical'      => $ri->is_critical,
            ])->values()->toArray(),
            'hasDiscount'       => $item->has_discount,
            'discountPercent'   => (float) $item->discount_percent,
            'discountedPrice'   => $item->discounted_price ? (float) $item->discounted_price : null,
            'rating'            => (float) $item->rating,
            'reviewCount'       => $item->review_count,
            'orderedCount'      => $item->ordered_count,
            'sortOrder'         => $item->sort_order,
        ];
    }
}

