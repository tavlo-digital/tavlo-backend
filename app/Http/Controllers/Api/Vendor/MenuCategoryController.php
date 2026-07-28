<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MasterMenuCategory;
use App\Models\MenuCategory;
use App\Models\TaxCategory;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MenuCategoryController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $categories = $vendor->menuCategories()
            ->with(['masterCategory.localizedTranslations', 'taxCategory', 'localizedTranslations'])
            ->withCount(['items' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuCategory $cat) => $this->formatCategory(
                $cat,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ));

        return response()->json(['data' => $categories]);
    }

    public function categoryOptions(): JsonResponse
    {
        $categories = MasterMenuCategory::where('is_active', true)
            ->with('localizedTranslations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MasterMenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon_url,
                'sortOrder' => $category->sort_order,
                'translations' => $this->locales->translationMap(
                    $category,
                    'localizedTranslations',
                    ['name'],
                    ['name' => $category->name]
                ),
            ]);

        return response()->json(['data' => $categories]);
    }

    public function taxCategories(Request $request): JsonResponse
    {
        $vendor = $request->user();
        $country = $this->resolveCountryCode($vendor->country ?? 'AT');

        $taxCategories = TaxCategory::where('country', $country)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (TaxCategory $tc) => [
                'id' => $tc->id,
                'slug' => $tc->slug,
                'name' => $tc->name,
                'vatRate' => (float) $tc->vat_rate,
            ]);

        return response()->json(['data' => $taxCategories]);
    }

    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $data = $request->validate([
            'masterCategoryId' => ['required', 'integer', Rule::exists('master_menu_categories', 'id')->where('is_active', true)],
            'taxCategoryId' => ['sometimes', 'nullable', 'integer', 'exists:tax_categories,id'],
            'defaultTaxCategory' => ['sometimes', 'nullable', 'string', 'max:64'],
            'translations' => ['sometimes', 'array'],
        ]);

        $masterCategory = MasterMenuCategory::where('is_active', true)->findOrFail($data['masterCategoryId']);
        if ($vendor->menuCategories()->where('master_menu_category_id', $masterCategory->id)->exists()) {
            return response()->json(['message' => 'A category with this name already exists.'], 422);
        }

        $maxSort = $vendor->menuCategories()->max('sort_order') ?? -1;

        $taxCategoryId = $data['taxCategoryId']
            ?? $this->taxCategoryIdForSlug($vendor, $data['defaultTaxCategory'] ?? null)
            ?? $this->defaultTaxCategoryId($vendor);

        $category = $vendor->menuCategories()->create([
            'master_menu_category_id' => $masterCategory->id,
            'tax_category_id' => $taxCategoryId,
            'default_tax_category' => $this->slugForTaxCategory($taxCategoryId),
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        $this->locales->syncTranslations(
            $category,
            'localizedTranslations',
            $data['translations'] ?? [],
            ['name']
        );
        $category->load(['masterCategory.localizedTranslations', 'taxCategory', 'localizedTranslations']);
        $category->loadCount(['items' => fn ($q) => $q->where('is_active', true)]);

        return response()->json([
            'data' => $this->formatCategory(
                $category,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ),
        ], 201);
    }

    public function update(Request $request, int $categoryId): JsonResponse
    {
        $vendor = $request->user();
        $category = $vendor->menuCategories()->findOrFail($categoryId);

        $data = $request->validate([
            'masterCategoryId' => ['sometimes', 'nullable', 'integer', Rule::exists('master_menu_categories', 'id')->where('is_active', true)],
            'taxCategoryId' => ['sometimes', 'nullable', 'integer', 'exists:tax_categories,id'],
            'defaultTaxCategory' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
            'translations' => ['sometimes', 'array'],
        ]);

        if (! isset($data['taxCategoryId']) && array_key_exists('defaultTaxCategory', $data)) {
            $resolved = $this->taxCategoryIdForSlug($vendor, $data['defaultTaxCategory']);
            if ($resolved !== null) {
                $data['taxCategoryId'] = $resolved;
            }
        }

        if (isset($data['masterCategoryId']) && (int) $data['masterCategoryId'] !== $category->master_menu_category_id) {
            $masterCategory = MasterMenuCategory::where('is_active', true)->findOrFail($data['masterCategoryId']);
            if ($vendor->menuCategories()->where('master_menu_category_id', $masterCategory->id)->where('id', '!=', $category->id)->exists()) {
                return response()->json(['message' => 'A category with this name already exists.'], 422);
            }
            $category->master_menu_category_id = $masterCategory->id;
        }

        if (isset($data['taxCategoryId'])) {
            $category->tax_category_id = $data['taxCategoryId'];
            $category->default_tax_category = $this->slugForTaxCategory($data['taxCategoryId']);
        }

        if (isset($data['sortOrder'])) {
            $category->sort_order = $data['sortOrder'];
        }

        if (isset($data['isActive'])) {
            $category->is_active = $data['isActive'];
        }

        $category->save();
        if (array_key_exists('translations', $data)) {
            $this->locales->syncTranslations(
                $category,
                'localizedTranslations',
                $data['translations'],
                ['name']
            );
        }
        $category->load(['masterCategory.localizedTranslations', 'taxCategory', 'localizedTranslations']);
        $category->loadCount(['items' => fn ($q) => $q->where('is_active', true)]);

        return response()->json([
            'data' => $this->formatCategory(
                $category,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ),
        ]);
    }

    public function destroy(Request $request, int $categoryId): JsonResponse
    {
        $vendor = $request->user();
        $category = $vendor->menuCategories()->findOrFail($categoryId);

        if ($category->items()->where('is_active', true)->exists()) {
            return response()->json([
                'message' => 'Category cannot be deleted because menu items are assigned to it.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $data = $request->validate([
            'orderedIds' => ['required', 'array', 'min:1'],
            'orderedIds.*' => ['integer'],
        ]);

        $ids = $data['orderedIds'];
        $existing = $vendor->menuCategories()->pluck('id')->toArray();
        $invalid = array_diff($ids, $existing);
        if ($invalid) {
            return response()->json(['message' => 'One or more category IDs do not belong to this vendor.'], 422);
        }

        foreach ($ids as $index => $id) {
            $vendor->menuCategories()->where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Categories reordered']);
    }

    // ----------------------------------------------------------------

    private function formatCategory(MenuCategory $cat, $vendor, string $locale): array
    {
        $tc = $cat->taxCategory;
        $masterTranslations = $cat->masterCategory
            ? $this->locales->translationMap(
                $cat->masterCategory,
                'localizedTranslations',
                ['name'],
                ['name' => $cat->display_name]
            )
            : ['en' => ['name' => $cat->display_name]];
        $vendorTranslations = $this->locales->translationMap(
            $cat,
            'localizedTranslations',
            ['name']
        );
        $translations = array_replace_recursive($masterTranslations, $vendorTranslations);

        return [
            'id' => $cat->id,
            'masterCategoryId' => $cat->master_menu_category_id,
            'name' => $translations[$locale]['name'] ?? $translations['en']['name'] ?? $cat->display_name,
            'slug' => $cat->display_slug,
            'icon' => $cat->display_icon,
            'defaultTaxCategory' => $cat->default_tax_category ?? ($tc?->slug ?? 'food'),
            'taxCategory' => $tc ? [
                'id' => $tc->id,
                'slug' => $tc->slug,
                'name' => $tc->name,
                'vatRate' => (float) $tc->vat_rate,
            ] : null,
            'sortOrder' => $cat->sort_order,
            'isActive' => $cat->is_active,
            'itemCount' => $cat->items_count ?? 0,
            'translations' => $translations,
        ];
    }

    private function taxCategoryIdForSlug($vendor, ?string $slug): ?int
    {
        if (! $slug) {
            return null;
        }
        $country = $this->resolveCountryCode($vendor->country ?? 'AT');
        $tc = TaxCategory::where('country', $country)->where('slug', $slug)->first();

        return $tc?->id;
    }

    private function defaultTaxCategoryId($vendor): ?int
    {
        $country = $this->resolveCountryCode($vendor->country ?? 'AT');
        $tc = TaxCategory::where('country', $country)->where('slug', 'food')->first();

        return $tc?->id;
    }

    private function slugForTaxCategory(?int $taxCategoryId): string
    {
        if (! $taxCategoryId) {
            return 'food';
        }
        $tc = TaxCategory::find($taxCategoryId);

        return $tc?->slug ?? 'food';
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
}
