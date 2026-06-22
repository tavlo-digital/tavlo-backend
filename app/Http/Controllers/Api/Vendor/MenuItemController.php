<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\TaxCategory;
use App\Services\LocaleService;
use App\Services\MenuCustomizationService;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuItemController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly LocaleService $locales,
        private readonly MenuCustomizationService $customizations,
    ) {}

    /**
     * GET /api/vendor/menu/items
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $query = $vendor->menuItems()
            ->where('is_active', true)
            ->with([
                'itemTranslations',
                'category.masterCategory',
                'category.localizedTranslations',
                'modifierGroups.localizedTranslations',
                'modifierGroups.options.localizedTranslations',
            ]);

        // Accept both `categoryId` and legacy `category_id`
        $categoryId = $request->input('categoryId') ?? $request->input('category_id');
        if (! empty($categoryId)) {
            $query->where('menu_category_id', $categoryId);
        }

        if ($request->filled('search')) {
            $query->whereRaw('LOWER(name) LIKE ?', [strtolower('%'.$request->input('search').'%')]);
        }

        if ($request->filled('available')) {
            $query->where('available', $request->boolean('available'));
        }

        $items = $query->orderBy('sort_order')->get();

        $stats = [
            'totalItems' => $items->count(),
            'totalCategories' => $vendor->menuCategories()->count(),
            'averagePrice' => $items->count() ? round((float) $items->avg('price'), 2) : 0,
            'averageRating' => $items->count() ? round((float) $items->avg('rating'), 2) : 0,
        ];

        return response()->json([
            'data' => $items->map(fn (MenuItem $item) => $this->formatItem(
                $item,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            )),
            'stats' => $stats,
        ]);
    }

    /**
     * POST /api/vendor/menu/items
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $data = $this->customizations->normalizeMenuPayloadCustomizations(
            $this->validatePayload($request, true)
        );
        $translations = $this->locales->normalizeTranslationPayload(
            $data['translations'] ?? [],
            ['name', 'description']
        );
        $name = $this->baseName($vendor, $data['name'] ?? null, $translations);
        $description = $data['description']
            ?? $translations['en']['description']
            ?? null;

        $category = $vendor->menuCategories()->findOrFail($data['categoryId']);

        [$vatRate, $taxSlug] = $this->resolveTax($data, $category, $vendor);

        $price = (float) $data['price'];
        $hasDiscount = (bool) ($data['hasDiscount'] ?? false);
        $discountPercent = (float) ($data['discountPercent'] ?? 0);
        $discountedPrice = ($hasDiscount && $discountPercent > 0)
            ? round($price * (1 - $discountPercent / 100), 2)
            : null;

        $maxSort = $vendor->menuItems()->where('menu_category_id', $category->id)->max('sort_order') ?? -1;

        $item = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'image_url' => $data['imageUrl'] ?? null,
            'available' => $data['available'] ?? true,
            'is_active' => true,
            'calories' => $data['calories'] ?? 0,
            'fat' => $data['fat'] ?? 0,
            'carbs' => $data['carbs'] ?? 0,
            'protein' => $data['protein'] ?? 0,
            'vat_rate' => $vatRate,
            'tax_category' => $taxSlug,
            'dietary_preference' => $data['dietaryPreference'] ?? null,
            'allergies' => $data['allergies'] ?? [],
            'special_tags' => $data['specialTags'] ?? [],
            'has_discount' => $hasDiscount,
            'discount_percent' => $discountPercent,
            'discounted_price' => $discountedPrice,
            'paid_addons' => $data['paidAddons'] ?? [],
            'free_addons' => $data['freeAddons'] ?? [],
            'removable_items' => $data['removableItems'] ?? [],
            'translations' => $this->normalizeTranslations($data['translations'] ?? null),
            'ingredients' => $data['ingredients'] ?? [],
            'sort_order' => $maxSort + 1,
        ]);
        $this->locales->syncTranslations(
            $item,
            'itemTranslations',
            array_merge([
                'en' => ['name' => $name, 'description' => $description],
            ], $translations),
            ['name', 'description']
        );

        $this->syncModifierGroups($item, $vendor, $data['modifierGroupIds'] ?? []);

        $item->load([
            'itemTranslations',
            'category.masterCategory',
            'category.localizedTranslations',
            'modifierGroups.localizedTranslations',
            'modifierGroups.options.localizedTranslations',
        ]);

        return response()->json([
            'data' => $this->formatItem(
                $item,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ),
        ], 201);
    }

    /**
     * GET /api/vendor/menu/items/{itemId}
     */
    public function show(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item = $vendor->menuItems()
            ->where('is_active', true)
            ->with([
                'itemTranslations',
                'category.masterCategory',
                'category.localizedTranslations',
                'modifierGroups.localizedTranslations',
                'modifierGroups.options.localizedTranslations',
            ])
            ->findOrFail($itemId);

        return response()->json([
            'data' => $this->formatItem(
                $item,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ),
        ]);
    }

    /**
     * PATCH /api/vendor/menu/items/{itemId}
     */
    public function update(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item = $vendor->menuItems()->where('is_active', true)->findOrFail($itemId);

        $data = $this->customizations->normalizeMenuPayloadCustomizations(
            $this->validatePayload($request, false)
        );

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
            'dietaryPreference' => 'dietary_preference',
            'hasDiscount' => 'has_discount',
            'discountPercent' => 'discount_percent',
            'allergies' => 'allergies',
            'specialTags' => 'special_tags',
            'paidAddons' => 'paid_addons',
            'freeAddons' => 'free_addons',
            'removableItems' => 'removable_items',
            'ingredients' => 'ingredients',
        ];

        foreach ($directFields as $camel => $snake) {
            if (array_key_exists($camel, $data)) {
                $mapped[$snake] = $data[$camel];
            }
        }

        if (array_key_exists('translations', $data)) {
            $existing = $item->translations ?? [];
            $incoming = $this->normalizeTranslations($data['translations']);
            $mapped['translations'] = array_merge($existing, $incoming);
            if (! array_key_exists('name', $mapped) && ! empty($incoming['en']['name'])) {
                $mapped['name'] = $incoming['en']['name'];
            }
            if (! array_key_exists('description', $mapped)
                && array_key_exists('description', $incoming['en'] ?? [])) {
                $mapped['description'] = $incoming['en']['description'];
            }
        }

        if (array_key_exists('taxCategory', $data) || array_key_exists('taxCategoryId', $data)) {
            $category = $item->category ?? $vendor->menuCategories()->find($mapped['menu_category_id'] ?? $item->menu_category_id);
            [$vatRate, $taxSlug] = $this->resolveTax($data, $category, $vendor);
            $mapped['tax_category'] = $taxSlug;
            $mapped['vat_rate'] = $vatRate;
        }

        $price = (float) ($mapped['price'] ?? $item->price);
        $hasDiscount = (bool) ($mapped['has_discount'] ?? $item->has_discount);
        $discountPercent = (float) ($mapped['discount_percent'] ?? $item->discount_percent);
        $mapped['discounted_price'] = ($hasDiscount && $discountPercent > 0)
            ? round($price * (1 - $discountPercent / 100), 2)
            : null;

        $needsVersioning = $this->hasVersionedFieldChanged($item, $mapped, $data);

        if ($needsVersioning) {
            $item = $this->createNewVersion($item, $mapped, $data, $vendor);
        } else {
            $item->update($mapped);
            if (array_key_exists('translations', $data)) {
                $this->locales->syncTranslations(
                    $item,
                    'itemTranslations',
                    $data['translations'],
                    ['name', 'description']
                );
            }
            if (array_key_exists('name', $data) || array_key_exists('description', $data)) {
                $this->locales->syncTranslations(
                    $item,
                    'itemTranslations',
                    ['en' => array_filter([
                        'name' => $data['name'] ?? null,
                        'description' => $data['description'] ?? null,
                    ], fn ($value) => $value !== null)],
                    ['name', 'description']
                );
            }

            if (array_key_exists('modifierGroupIds', $data)) {
                $this->syncModifierGroups($item, $vendor, $data['modifierGroupIds'] ?? []);
            }
        }

        $item->load([
            'itemTranslations',
            'category.masterCategory',
            'category.localizedTranslations',
            'modifierGroups.localizedTranslations',
            'modifierGroups.options.localizedTranslations',
        ]);

        return response()->json([
            'data' => $this->formatItem(
                $item,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ),
        ]);
    }

    /**
     * DELETE /api/vendor/menu/items/{itemId}
     */
    public function destroy(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item = $vendor->menuItems()->findOrFail($itemId);

        $item->update(['is_active' => false, 'available' => false]);
        $item->delete();

        return response()->json(['message' => 'Menu item deleted']);
    }

    /**
     * PATCH /api/vendor/menu/items/{itemId}/toggle
     */
    public function toggleAvailability(Request $request, int $itemId): JsonResponse
    {
        $vendor = $request->user();
        $item = $vendor->menuItems()->where('is_active', true)->findOrFail($itemId);

        $item->update(['available' => ! $item->available]);
        $item->load([
            'itemTranslations',
            'category.masterCategory',
            'category.localizedTranslations',
            'modifierGroups.localizedTranslations',
            'modifierGroups.options.localizedTranslations',
        ]);

        return response()->json([
            'data' => $this->formatItem(
                $item,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ),
        ]);
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
            'categoryId' => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:menu_categories,id'],
            'name' => [$isCreate ? 'required_without:translations' : 'sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => [$isCreate ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'imageUrl' => ['nullable', 'string', 'max:2000'],
            'available' => ['sometimes', 'boolean'],
            'calories' => ['sometimes', 'integer', 'min:0'],
            'fat' => ['sometimes', 'numeric', 'min:0'],
            'carbs' => ['sometimes', 'numeric', 'min:0'],
            'protein' => ['sometimes', 'numeric', 'min:0'],
            'taxCategory' => ['sometimes', 'nullable', 'string', 'max:64'],
            'taxCategoryId' => ['sometimes', 'nullable', 'integer', 'exists:tax_categories,id'],
            'dietaryPreference' => ['nullable', 'string', 'max:64'],
            'allergies' => ['sometimes', 'array'],
            'allergies.*' => ['string', 'max:64'],
            'specialTags' => ['sometimes', 'array'],
            'specialTags.*' => ['string', 'max:64'],
            'hasDiscount' => ['sometimes', 'boolean'],
            'discountPercent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'paidAddons' => ['sometimes', 'array'],
            'paidAddons.*.id' => ['sometimes', 'integer', 'min:1'],
            'paidAddons.*.name' => ['required_with:paidAddons', 'string', 'max:255'],
            'paidAddons.*.price' => ['required_with:paidAddons', 'numeric', 'min:0'],
            'paidAddons.*.taxCategory' => ['sometimes', 'nullable', 'string', Rule::in(TaxCategory::pluck('slug')->unique())],
            'paidAddons.*.translations' => ['sometimes', 'array'],
            'freeAddons' => ['sometimes', 'array'],
            'freeAddons.*.id' => ['sometimes', 'integer', 'min:1'],
            'freeAddons.*.name' => ['sometimes', 'string', 'max:255'],
            'freeAddons.*.translations' => ['sometimes', 'array'],
            'removableItems' => ['sometimes', 'array'],
            'removableItems.*.id' => ['sometimes', 'integer', 'min:1'],
            'removableItems.*.name' => ['sometimes', 'string', 'max:255'],
            'removableItems.*.translations' => ['sometimes', 'array'],
            'modifierGroupIds' => ['sometimes', 'array'],
            'modifierGroupIds.*' => ['integer'],
            // translations: accept either a nested map { en: {name, description}, ... }
            // or an array of objects [{language, name, description}, ...]
            'translations' => ['sometimes'],
            'ingredients' => ['sometimes', 'array'],
            'ingredients.*.ingredientId' => ['sometimes', 'string'],
            'ingredients.*.ingredientName' => ['sometimes', 'string', 'max:255'],
            'ingredients.*.quantity' => ['required_with:ingredients', 'numeric', 'min:0'],
            'ingredients.*.unit' => ['sometimes', 'string', 'max:32'],
            'ingredients.*.isCritical' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }

    /**
     * Returns translations in nested-map shape for storage:
     *   { en: { name, description }, de: { name, description }, ... }
     */
    private function normalizeTranslations(mixed $value): array
    {
        if (empty($value) || ! is_array($value)) {
            return [];
        }

        $out = [];

        // Detect array-of-objects shape: [{language, name, description}, ...]
        $isArrayOfObjects = array_is_list($value) && isset($value[0]) && is_array($value[0]) && isset($value[0]['language']);

        if ($isArrayOfObjects) {
            foreach ($value as $entry) {
                if (! is_array($entry) || empty($entry['language'])) {
                    continue;
                }
                $out[$entry['language']] = [
                    'name' => $entry['name'] ?? '',
                    'description' => $entry['description'] ?? null,
                ];
            }

            return $out;
        }

        // Already in nested-map shape
        foreach ($value as $lang => $entry) {
            if (! is_string($lang) || ! is_array($entry)) {
                continue;
            }
            $out[$lang] = [
                'name' => $entry['name'] ?? '',
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
        if (! empty($data['taxCategory'])) {
            $country = $this->resolveCountryCode($vendor->country ?? 'AT');
            $tc = TaxCategory::where('country', $country)
                ->where('slug', $data['taxCategory'])
                ->first();
            if ($tc) {
                return [(float) $tc->vat_rate, $tc->slug];
            }
        }

        // 2. explicit id
        if (! empty($data['taxCategoryId'])) {
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
            'austria' => 'AT',
            'germany' => 'DE',
            'united kingdom' => 'GB',
            'uk' => 'GB',
            'great britain' => 'GB',
        ];
        $lower = strtolower(trim($country));

        return $map[$lower] ?? strtoupper(substr($country, 0, 2));
    }

    private function formatItem(MenuItem $item, $vendor, string $locale): array
    {
        if (! $item->relationLoaded('modifierGroups')) {
            $item->load([
                'modifierGroups.localizedTranslations',
                'modifierGroups.options.localizedTranslations',
            ]);
        }

        return [
            'id' => $item->id,
            'productUid' => $item->product_uid,
            'categoryId' => $item->menu_category_id,
            'categoryName' => $item->category
                ? $this->locales->translated(
                    $item->category,
                    'localizedTranslations',
                    'name',
                    $vendor,
                    $locale,
                    $item->category->display_name
                )
                : 'Unknown',
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
            'price' => (float) $item->price,
            'imageUrl' => $this->media->url($item->image_url),
            'available' => (bool) $item->available,
            'isActive' => (bool) $item->is_active,
            'calories' => (int) $item->calories,
            'fat' => (float) $item->fat,
            'carbs' => (float) $item->carbs,
            'protein' => (float) $item->protein,
            'vatRate' => $item->liveVatRate(),
            'taxCategory' => $item->tax_category,
            'dietaryPreference' => $item->dietary_preference,
            'allergies' => $item->allergies ?? [],
            'specialTags' => $item->special_tags ?? [],
            'hasDiscount' => (bool) $item->has_discount,
            'discountPercent' => (float) $item->discount_percent,
            'discountedPrice' => $item->discounted_price !== null ? (float) $item->discounted_price : null,
            'paidAddons' => $this->customizations->paidAddonDefinitions($item->paid_addons ?? [])->values()->all(),
            'freeAddons' => $this->customizations->namedDefinitions($item->free_addons ?? [])->values()->all(),
            'removableItems' => $this->customizations->namedDefinitions($item->removable_items ?? [])->values()->all(),
            'modifierGroupIds' => $item->modifierGroups->pluck('id')->values()->all(),
            'modifierGroups' => $item->modifierGroups
                ->map(fn (ModifierGroup $group) => $this->formatModifierGroup(
                    $group,
                    $vendor,
                    $locale
                ))
                ->values()
                ->all(),
            'translations' => $this->locales->translationMap(
                $item,
                'itemTranslations',
                ['name', 'description'],
                ['name' => $item->name, 'description' => $item->description]
            ),
            'ingredients' => $item->ingredients ?? [],
            'rating' => (float) $item->rating,
            'reviewCount' => (int) $item->review_count,
            'orderedCount' => (int) $item->ordered_count,
            'sortOrder' => (int) $item->sort_order,
        ];
    }

    private function syncModifierGroups(MenuItem $item, $vendor, array $ids): void
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

        $existingIds = $vendor->modifierGroups()
            ->where('is_active', true)
            ->whereIn('id', $ids->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($existingIds) !== $ids->count()) {
            throw ValidationException::withMessages([
                'modifierGroupIds' => ['One or more selected modifier groups are invalid for this vendor.'],
            ]);
        }

        $sync = $ids
            ->mapWithKeys(fn (int $id, int $index) => [$id => ['sort_order' => $index]])
            ->all();

        $item->modifierGroups()->sync($sync);
    }

    private function formatModifierGroup(ModifierGroup $group, $vendor, string $locale): array
    {
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
            'translations' => $this->locales->translationMap(
                $group,
                'localizedTranslations',
                ['name'],
                ['name' => $group->name]
            ),
            'type' => $group->type,
            'minSelection' => (int) $group->min_selection,
            'maxSelection' => (int) $group->max_selection,
            'isRequired' => (bool) $group->is_required,
            'sortOrder' => (int) ($group->pivot?->sort_order ?? $group->sort_order),
            'isActive' => (bool) $group->is_active,
            'options' => $group->options
                ->map(fn (ModifierOption $option) => [
                    'id' => $option->id,
                    'name' => $this->locales->translated(
                        $option,
                        'localizedTranslations',
                        'name',
                        $vendor,
                        $locale,
                        $option->name
                    ),
                    'translations' => $this->locales->translationMap(
                        $option,
                        'localizedTranslations',
                        ['name'],
                        ['name' => $option->name]
                    ),
                    'priceAdjustment' => (float) $option->price_adjustment,
                    'sortOrder' => (int) $option->sort_order,
                    'isActive' => (bool) $option->is_active,
                ])
                ->values()
                ->all(),
        ];
    }

    private function hasVersionedFieldChanged(MenuItem $item, array $mapped, array $data): bool
    {
        $numericFields = ['price', 'discount_percent', 'vat_rate'];
        foreach ($numericFields as $field) {
            if (array_key_exists($field, $mapped) && round((float) $mapped[$field], 2) !== round((float) $item->{$field}, 2)) {
                return true;
            }
        }

        $stringFields = ['tax_category'];
        foreach ($stringFields as $field) {
            if (array_key_exists($field, $mapped) && (string) ($mapped[$field] ?? '') !== (string) ($item->{$field} ?? '')) {
                return true;
            }
        }

        if (array_key_exists('has_discount', $mapped) && (bool) $mapped['has_discount'] !== (bool) $item->has_discount) {
            return true;
        }

        if (array_key_exists('paid_addons', $mapped)) {
            $oldAddons = collect($item->paid_addons ?? [])->sortBy('id')->values()->toJson();
            $newAddons = collect($mapped['paid_addons'] ?? [])->sortBy('id')->values()->toJson();
            if ($oldAddons !== $newAddons) {
                return true;
            }
        }

        if (array_key_exists('free_addons', $mapped)) {
            $oldFree = collect($item->free_addons ?? [])->sortBy('id')->values()->toJson();
            $newFree = collect($mapped['free_addons'] ?? [])->sortBy('id')->values()->toJson();
            if ($oldFree !== $newFree) {
                return true;
            }
        }

        if (array_key_exists('modifierGroupIds', $data)) {
            $currentIds = $item->modifierGroups()->pluck('modifier_groups.id')->sort()->values()->toArray();
            $newIds = collect($data['modifierGroupIds'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->toArray();
            if ($currentIds !== $newIds) {
                return true;
            }
        }

        return false;
    }

    private function createNewVersion(MenuItem $oldItem, array $mapped, array $data, $vendor): MenuItem
    {
        return DB::transaction(function () use ($oldItem, $mapped, $data, $vendor) {
            $attributes = $oldItem->attributesToArray();
            unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

            foreach ($mapped as $key => $value) {
                $attributes[$key] = $value;
            }

            $attributes['product_uid'] = $oldItem->product_uid;

            $oldItem->delete();

            $newItem = MenuItem::create($attributes);

            foreach ($oldItem->itemTranslations as $translation) {
                $newItem->itemTranslations()->create([
                    'language' => $translation->language,
                    'name' => $translation->name,
                    'description' => $translation->description,
                ]);
            }

            if (array_key_exists('translations', $data)) {
                $this->locales->syncTranslations(
                    $newItem,
                    'itemTranslations',
                    $data['translations'],
                    ['name', 'description']
                );
            }
            if (array_key_exists('name', $data) || array_key_exists('description', $data)) {
                $this->locales->syncTranslations(
                    $newItem,
                    'itemTranslations',
                    ['en' => array_filter([
                        'name' => $data['name'] ?? null,
                        'description' => $data['description'] ?? null,
                    ], fn ($value) => $value !== null)],
                    ['name', 'description']
                );
            }

            if (array_key_exists('modifierGroupIds', $data)) {
                $this->syncModifierGroups($newItem, $vendor, $data['modifierGroupIds'] ?? []);
            } else {
                $pivotData = DB::table('menu_item_modifier_groups')
                    ->where('menu_item_id', $oldItem->id)
                    ->get();
                foreach ($pivotData as $pivot) {
                    DB::table('menu_item_modifier_groups')->insert([
                        'menu_item_id' => $newItem->id,
                        'modifier_group_id' => $pivot->modifier_group_id,
                        'sort_order' => $pivot->sort_order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $allergenIds = DB::table('menu_item_allergens')
                ->where('menu_item_id', $oldItem->id)
                ->pluck('allergen_id')
                ->toArray();
            if (! empty($allergenIds)) {
                $newItem->allergens()->attach($allergenIds);
            }

            $tagIds = DB::table('menu_item_tags')
                ->where('menu_item_id', $oldItem->id)
                ->pluck('special_tag_id')
                ->toArray();
            if (! empty($tagIds)) {
                $newItem->tags()->attach($tagIds);
            }

            $ingredients = DB::table('menu_item_ingredients')
                ->where('menu_item_id', $oldItem->id)
                ->get();
            foreach ($ingredients as $ingredient) {
                DB::table('menu_item_ingredients')->insert([
                    'menu_item_id' => $newItem->id,
                    'inventory_item_id' => $ingredient->inventory_item_id,
                    'quantity' => $ingredient->quantity,
                    'unit' => $ingredient->unit,
                    'is_critical' => $ingredient->is_critical,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $newItem;
        });
    }

    private function baseName($vendor, mixed $name, array $translations): string
    {
        $name = is_string($name) ? trim($name) : '';
        $name = $name
            ?: ($translations['en']['name'] ?? '');

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['A name is required in English.'],
            ]);
        }

        return $name;
    }
}
