<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterMenuCategory;
use App\Services\LocaleService;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MenuCategoryController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly LocaleService $locales,
    ) {}

    public function index(): Response
    {
        $categories = MasterMenuCategory::query()
            ->with('localizedTranslations')
            ->withCount('vendorCategories')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MasterMenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon_url,
                'sortOrder' => $category->sort_order,
                'isActive' => $category->is_active,
                'vendorCount' => $category->vendor_categories_count,
                'translations' => $this->locales->translationMap(
                    $category,
                    'localizedTranslations',
                    ['name'],
                    ['name' => $category->name]
                ),
            ]);

        return Inertia::render('admin/menu-categories/index', [
            'categories' => $categories,
            'languages' => $this->locales->languageOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $slug = Str::slug((string) $request->input('name'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('master_menu_categories', 'name')],
            'icon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        if (MasterMenuCategory::where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'A category with this name already exists.'])->withInput();
        }

        $category = MasterMenuCategory::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'icon' => $request->hasFile('icon')
                ? $this->media->store($request->file('icon'), 'cat-icons')
                : null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $translations = $validated['translations'] ?? [];
        $translations['en'] = ['name' => $validated['name']];
        $this->locales->syncTranslations(
            $category,
            'localizedTranslations',
            $translations,
            ['name']
        );

        return to_route('admin.menu-categories.index')->with('status', 'Category created.');
    }

    public function update(Request $request, MasterMenuCategory $category): RedirectResponse
    {
        $slug = Str::slug((string) $request->input('name'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('master_menu_categories', 'name')->ignore($category->id)],
            'icon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_icon' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        if (MasterMenuCategory::where('slug', $slug)->whereKeyNot($category->id)->exists()) {
            return back()->withErrors(['name' => 'A category with this name already exists.'])->withInput();
        }

        $updates = [
            'name' => $validated['name'],
            'slug' => $slug,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? false,
        ];

        if ($request->hasFile('icon')) {
            $this->media->delete($category->getRawOriginal('icon'));
            $updates['icon'] = $this->media->store($request->file('icon'), 'cat-icons');
        } elseif ($request->boolean('remove_icon')) {
            $this->media->delete($category->getRawOriginal('icon'));
            $updates['icon'] = null;
        }

        $category->update($updates);

        $translations = $validated['translations'] ?? [];
        $translations['en'] = ['name' => $validated['name']];
        $this->locales->syncTranslations(
            $category,
            'localizedTranslations',
            $translations,
            ['name']
        );

        return to_route('admin.menu-categories.index')->with('status', 'Category updated.');
    }

    public function destroy(MasterMenuCategory $category): RedirectResponse
    {
        if ($category->vendorCategories()->exists()) {
            return back()->withErrors([
                'category' => 'This category is used by vendors and cannot be deleted.',
            ]);
        }

        $this->media->delete($category->getRawOriginal('icon'));
        $category->delete();

        return to_route('admin.menu-categories.index')->with('status', 'Category deleted.');
    }
}
