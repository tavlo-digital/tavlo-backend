<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $categories = $vendor->menuCategories()
            ->withCount('items')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuCategory $cat) => $this->formatCategory($cat));

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'defaultTaxCategory' => ['sometimes', 'string', 'in:food,beverage_non_alcoholic,beverage_alcoholic'],
        ]);

        $slug = Str::slug($data['name']);

        if ($vendor->menuCategories()->where('slug', $slug)->exists()) {
            return response()->json(['message' => 'A category with this name already exists.'], 422);
        }

        $maxSort = $vendor->menuCategories()->max('sort_order') ?? -1;

        $category = $vendor->menuCategories()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'default_tax_category' => $data['defaultTaxCategory'] ?? 'food',
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        $category->loadCount('items');

        return response()->json(['data' => $this->formatCategory($category)], 201);
    }

    public function update(Request $request, int $categoryId): JsonResponse
    {
        $vendor = $request->user();
        $category = $vendor->menuCategories()->findOrFail($categoryId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'defaultTaxCategory' => ['sometimes', 'string', 'in:food,beverage_non_alcoholic,beverage_alcoholic'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $slug = Str::slug($data['name']);
            if ($vendor->menuCategories()->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                return response()->json(['message' => 'A category with this name already exists.'], 422);
            }
            $category->name = $data['name'];
            $category->slug = $slug;
        }

        if (isset($data['defaultTaxCategory'])) {
            $category->default_tax_category = $data['defaultTaxCategory'];
        }

        if (isset($data['sortOrder'])) {
            $category->sort_order = $data['sortOrder'];
        }

        if (isset($data['isActive'])) {
            $category->is_active = $data['isActive'];
        }

        $category->save();
        $category->loadCount('items');

        return response()->json(['data' => $this->formatCategory($category)]);
    }

    public function destroy(Request $request, int $categoryId): JsonResponse
    {
        $vendor = $request->user();
        $category = $vendor->menuCategories()->findOrFail($categoryId);

        if ($category->items()->exists()) {
            return response()->json([
                'message' => 'Category cannot be deleted because menu items are assigned to it.',
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    private function formatCategory(MenuCategory $cat): array
    {
        return [
            'id' => $cat->id,
            'name' => $cat->name,
            'slug' => $cat->slug,
            'defaultTaxCategory' => $cat->default_tax_category,
            'sortOrder' => $cat->sort_order,
            'isActive' => $cat->is_active,
            'itemCount' => $cat->items_count ?? 0,
        ];
    }
}
