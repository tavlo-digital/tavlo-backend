<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\TaxCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $categories = $vendor->menuCategories()
            ->with('taxCategory')
            ->withCount(['items' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuCategory $cat) => $this->formatCategory($cat));

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
                'id'      => $tc->id,
                'slug'    => $tc->slug,
                'name'    => $tc->name,
                'vatRate' => (float) $tc->vat_rate,
            ]);

        return response()->json(['data' => $taxCategories]);
    }

    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $data = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'taxCategoryId'      => ['sometimes', 'nullable', 'integer', 'exists:tax_categories,id'],
            'defaultTaxCategory' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $slug = Str::slug($data['name']);
        if ($vendor->menuCategories()->where('slug', $slug)->exists()) {
            return response()->json(['message' => 'A category with this name already exists.'], 422);
        }

        $maxSort = $vendor->menuCategories()->max('sort_order') ?? -1;

        $taxCategoryId = $data['taxCategoryId']
            ?? $this->taxCategoryIdForSlug($vendor, $data['defaultTaxCategory'] ?? null)
            ?? $this->defaultTaxCategoryId($vendor);

        $category = $vendor->menuCategories()->create([
            'name'              => $data['name'],
            'slug'              => $slug,
            'tax_category_id'   => $taxCategoryId,
            'default_tax_category' => $this->slugForTaxCategory($taxCategoryId),
            'sort_order'        => $maxSort + 1,
            'is_active'         => true,
        ]);

        $category->load('taxCategory');
        $category->loadCount(['items' => fn ($q) => $q->where('is_active', true)]);

        return response()->json(['data' => $this->formatCategory($category)], 201);
    }

    public function update(Request $request, int $categoryId): JsonResponse
    {
        $vendor  = $request->user();
        $category = $vendor->menuCategories()->findOrFail($categoryId);

        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'taxCategoryId'      => ['sometimes', 'nullable', 'integer', 'exists:tax_categories,id'],
            'defaultTaxCategory' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sortOrder'          => ['sometimes', 'integer', 'min:0'],
            'isActive'           => ['sometimes', 'boolean'],
        ]);

        if (!isset($data['taxCategoryId']) && array_key_exists('defaultTaxCategory', $data)) {
            $resolved = $this->taxCategoryIdForSlug($vendor, $data['defaultTaxCategory']);
            if ($resolved !== null) {
                $data['taxCategoryId'] = $resolved;
            }
        }

        if (isset($data['name'])) {
            $slug = Str::slug($data['name']);
            if ($vendor->menuCategories()->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                return response()->json(['message' => 'A category with this name already exists.'], 422);
            }
            $category->name = $data['name'];
            $category->slug = $slug;
        }

        if (isset($data['taxCategoryId'])) {
            $category->tax_category_id       = $data['taxCategoryId'];
            $category->default_tax_category  = $this->slugForTaxCategory($data['taxCategoryId']);
        }

        if (isset($data['sortOrder'])) {
            $category->sort_order = $data['sortOrder'];
        }

        if (isset($data['isActive'])) {
            $category->is_active = $data['isActive'];
        }

        $category->save();
        $category->load('taxCategory');
        $category->loadCount(['items' => fn ($q) => $q->where('is_active', true)]);

        return response()->json(['data' => $this->formatCategory($category)]);
    }

    public function destroy(Request $request, int $categoryId): JsonResponse
    {
        $vendor   = $request->user();
        $category = $vendor->menuCategories()->findOrFail($categoryId);

        if ($category->items()->where('is_active', true)->exists()) {
            return response()->json([
                'message' => 'Category cannot be deleted because menu items are assigned to it.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    // ----------------------------------------------------------------

    private function formatCategory(MenuCategory $cat): array
    {
        $tc = $cat->taxCategory;

        return [
            'id'                 => $cat->id,
            'name'               => $cat->name,
            'slug'               => $cat->slug,
            'defaultTaxCategory' => $cat->default_tax_category ?? ($tc?->slug ?? 'food'),
            'taxCategory'        => $tc ? [
                'id'      => $tc->id,
                'slug'    => $tc->slug,
                'name'    => $tc->name,
                'vatRate' => (float) $tc->vat_rate,
            ] : null,
            'sortOrder'          => $cat->sort_order,
            'isActive'           => $cat->is_active,
            'itemCount'          => $cat->items_count ?? 0,
        ];
    }

    private function taxCategoryIdForSlug($vendor, ?string $slug): ?int
    {
        if (!$slug) {
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
        if (!$taxCategoryId) {
            return 'food';
        }
        $tc = TaxCategory::find($taxCategoryId);
        return $tc?->slug ?? 'food';
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
}
