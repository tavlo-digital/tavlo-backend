<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterMenuCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MenuCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = MasterMenuCategory::query()
            ->withCount('vendorCategories')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MasterMenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'sortOrder' => $category->sort_order,
                'isActive' => $category->is_active,
                'vendorCount' => $category->vendor_categories_count,
            ]);

        return Inertia::render('admin/menu-categories/index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $slug = Str::slug((string) $request->input('name'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('master_menu_categories', 'name')],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if (MasterMenuCategory::where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A category with this name already exists.'])->withInput();
        }

        MasterMenuCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return to_route('admin.menu-categories.index')->with('status', 'Category created.');
    }

    public function update(Request $request, MasterMenuCategory $category): RedirectResponse
    {
        $slug = Str::slug((string) $request->input('name'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('master_menu_categories', 'name')->ignore($category->id)],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        if (MasterMenuCategory::where('slug', $slug)->whereKeyNot($category->id)->exists()) {
            return back()->withErrors(['name' => 'A category with this name already exists.'])->withInput();
        }

        $category->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return to_route('admin.menu-categories.index')->with('status', 'Category updated.');
    }

    public function destroy(MasterMenuCategory $category): RedirectResponse
    {
        if ($category->vendorCategories()->exists()) {
            return back()->withErrors([
                'category' => 'This category is used by vendors and cannot be deleted.',
            ]);
        }

        $category->delete();

        return to_route('admin.menu-categories.index')->with('status', 'Category deleted.');
    }
}
