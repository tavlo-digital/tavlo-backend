<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MasterMenuCategory;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Services\MenuCustomizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function __construct(private readonly MenuCustomizationService $customizations) {}

    /**
     * GET /api/restaurants/{vendorId}/menu
     *
     * Returns the full menu for a vendor: categories + items in the shape
     * the frontend expects: { categories: [...], items: [...] }
     */
    public function show(string $vendorId): JsonResponse
    {
        $vendor = \App\Models\Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();

        $categories = $vendor->menuCategories()
            ->with('masterCategory')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuCategory $cat) => [
                'id' => (string) $cat->id,
                'masterCategoryId' => $cat->master_menu_category_id,
                'name' => $cat->display_name,
                'slug' => $cat->display_slug,
                'icon' => $cat->display_icon,
                'defaultTaxCategory' => $cat->default_tax_category,
                'sortOrder' => $cat->sort_order,
                'isActive' => $cat->is_active,
            ]);

        $items = $vendor->menuItems()
            ->with(['category.masterCategory', 'modifierGroups.options'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuItem $item) => $this->formatItem($item));

        return response()->json([
            'categories' => $categories,
            'items' => $items,
        ]);
    }

    /**
     * PUT /api/restaurants/{vendorId}/menu
     *
     * Full menu replacement – receives { categories, items } and syncs.
     */
    public function update(Request $request, string $vendorId): JsonResponse
    {
        $vendor = \App\Models\Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();

        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'categories' => ['sometimes', 'array'],
            'categories.*.id' => ['sometimes', 'string'],
            'categories.*.masterCategoryId' => ['required', 'integer', 'exists:master_menu_categories,id'],
            'categories.*.defaultTaxCategory' => ['sometimes', 'string'],
            'items' => ['sometimes', 'array'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.category' => ['required', 'string'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        // ---- Sync categories ----
        $categoryMap = []; // frontend-id => db-id

        if (isset($data['categories'])) {
            $existingIds = [];

            foreach ($data['categories'] as $i => $catData) {
                $masterCategory = MasterMenuCategory::findOrFail($catData['masterCategoryId']);

                $category = $vendor->menuCategories()->updateOrCreate(
                    ['master_menu_category_id' => $masterCategory->id],
                    [
                        'default_tax_category' => $catData['defaultTaxCategory'] ?? 'food',
                        'sort_order' => $i,
                        'is_active' => true,
                    ]
                );

                $frontendId = $catData['id'] ?? (string) $masterCategory->id;
                $categoryMap[$frontendId] = $category->id;
                $categoryMap[$masterCategory->name] = $category->id;
                $categoryMap[$masterCategory->slug] = $category->id;
                $categoryMap[(string) $masterCategory->id] = $category->id;
                $existingIds[] = $category->id;
            }

            // Remove categories not in the payload
            $vendor->menuCategories()->whereNotIn('id', $existingIds)->delete();
        }

        // ---- Sync items ----
        if (isset($data['items'])) {
            $existingItemIds = [];

            foreach ($data['items'] as $i => $itemData) {
                // Resolve category
                $categoryId = $categoryMap[$itemData['category']] ?? null;
                if (! $categoryId) {
                    $masterCategory = MasterMenuCategory::where('slug', $itemData['category'])
                        ->orWhere('name', $itemData['category'])
                        ->first();
                    $cat = $masterCategory
                        ? $vendor->menuCategories()->where('master_menu_category_id', $masterCategory->id)->first()
                        : null;
                    if (! $cat) {
                        return response()->json(['message' => 'Menu item category must reference an admin category.'], 422);
                    }
                    $categoryId = $cat->id;
                }

                // Determine if an existing item or new
                $existingItem = null;
                if (isset($itemData['id']) && is_numeric($itemData['id'])) {
                    $existingItem = $vendor->menuItems()->find($itemData['id']);
                }

                $itemData = $this->customizations->normalizeMenuPayloadCustomizations($itemData);

                $attributes = [
                    'menu_category_id' => $categoryId,
                    'name' => $itemData['name'],
                    'description' => $itemData['description'] ?? null,
                    'price' => $itemData['price'],
                    'image_url' => $itemData['imageUrl'] ?? null,
                    'available' => $itemData['available'] ?? true,
                    'calories' => $itemData['calories'] ?? 0,
                    'fat' => $itemData['fat'] ?? 0,
                    'carbs' => $itemData['carbs'] ?? 0,
                    'protein' => $itemData['protein'] ?? 0,
                    'vat_rate' => $itemData['vatRate'] ?? 20,
                    'tax_category' => $itemData['taxCategory'] ?? 'food',
                    'dietary_preference' => $itemData['dietaryPreference'] ?? null,
                    'allergies' => $itemData['allergies'] ?? [],
                    'special_tags' => $itemData['specialTags'] ?? [],
                    'has_discount' => $itemData['hasDiscount'] ?? false,
                    'discount_percent' => $itemData['discountPercent'] ?? 0,
                    'discounted_price' => $itemData['discountedPrice'] ?? null,
                    'paid_addons' => $itemData['paidAddons'] ?? [],
                    'free_addons' => $itemData['freeAddons'] ?? [],
                    'removable_items' => $itemData['removableItems'] ?? [],
                    'translations' => $itemData['translations'] ?? [],
                    'ingredients' => $itemData['ingredients'] ?? [],
                    'rating' => $itemData['rating'] ?? 0,
                    'review_count' => $itemData['reviewCount'] ?? 0,
                    'ordered_count' => $itemData['orderedCount'] ?? 0,
                    'sort_order' => $i,
                ];

                if ($existingItem) {
                    $existingItem->update($attributes);
                    if (array_key_exists('modifierGroupIds', $itemData)) {
                        $this->syncModifierGroups($existingItem, $vendor, $itemData['modifierGroupIds'] ?? []);
                    }
                    $existingItemIds[] = $existingItem->id;
                } else {
                    $newItem = $vendor->menuItems()->create(array_merge(
                        $attributes,
                        ['vendor_id' => $vendor->id]
                    ));
                    $this->syncModifierGroups($newItem, $vendor, $itemData['modifierGroupIds'] ?? []);
                    $existingItemIds[] = $newItem->id;
                }
            }

            // Remove items not in the payload
            $vendor->menuItems()->whereNotIn('id', $existingItemIds)->delete();
        }

        // Return updated menu
        return $this->show($vendorId);
    }

    /**
     * PATCH /api/vendor/{vendorId}/menu/items/{itemId}
     *
     * Partial update for a single menu item (e.g., toggle availability).
     */
    public function updateItem(Request $request, string $vendorId, int $itemId): JsonResponse
    {
        $vendor = \App\Models\Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();

        $this->authorizeVendor($request, $vendor);

        $item = $vendor->menuItems()->findOrFail($itemId);

        $allowed = $request->only([
            'available', 'name', 'description', 'price',
            'calories', 'fat', 'carbs', 'protein',
            'has_discount', 'discount_percent',
        ]);

        // Convert camelCase keys from the frontend to snake_case
        $mapped = [];
        foreach ($allowed as $key => $value) {
            $mapped[Str::snake($key)] = $value;
        }

        $item->update($mapped);

        return response()->json($this->formatItem($item->fresh()));
    }

    // ----------------------------------------------------------------

    private function formatItem(MenuItem $item): array
    {
        if (! $item->relationLoaded('modifierGroups')) {
            $item->load('modifierGroups.options');
        }

        return [
            'id' => (string) $item->id,
            'category' => $item->category?->display_name ?? 'Unknown',
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
            'paidAddons' => $this->customizations->paidAddonDefinitions($item->paid_addons ?? [])->values()->all(),
            'freeAddons' => $this->customizations->namedDefinitions($item->free_addons ?? [])->values()->all(),
            'removableItems' => $this->customizations->namedDefinitions($item->removable_items ?? [])->values()->all(),
            'modifierGroupIds' => $item->modifierGroups->pluck('id')->values()->all(),
            'modifierGroups' => $item->modifierGroups
                ->map(fn (ModifierGroup $group) => $this->formatModifierGroup($group))
                ->values()
                ->all(),
            'translations' => $item->translations ?? (object) [],
            'ingredients' => $item->ingredients ?? [],
            'rating' => (float) $item->rating,
            'reviewCount' => $item->review_count,
            'orderedCount' => $item->ordered_count,
        ];
    }

    private function syncModifierGroups(MenuItem $item, \App\Models\Vendor $vendor, array $ids): void
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $item->modifierGroups()->sync([]);

            return;
        }

        $validIds = $vendor->modifierGroups()
            ->where('is_active', true)
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validIds) !== $ids->count()) {
            abort(422, 'One or more selected modifier groups are invalid for this vendor.');
        }

        $item->modifierGroups()->sync(
            $ids->mapWithKeys(fn (int $id, int $index) => [$id => ['sort_order' => $index]])->all()
        );
    }

    private function formatModifierGroup(ModifierGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'type' => $group->type,
            'minSelection' => (int) $group->min_selection,
            'maxSelection' => (int) $group->max_selection,
            'isRequired' => (bool) $group->is_required,
            'sortOrder' => (int) ($group->pivot?->sort_order ?? $group->sort_order),
            'isActive' => (bool) $group->is_active,
            'options' => $group->options->map(fn (ModifierOption $option) => [
                'id' => $option->id,
                'name' => $option->name,
                'priceAdjustment' => (float) $option->price_adjustment,
                'sortOrder' => (int) $option->sort_order,
                'isActive' => (bool) $option->is_active,
            ])->values()->all(),
        ];
    }

    private function authorizeVendor(Request $request, \App\Models\Vendor $vendor): void
    {
        $user = $request->user();
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
