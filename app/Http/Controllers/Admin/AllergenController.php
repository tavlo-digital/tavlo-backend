<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AllergenController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function index(): Response
    {
        $allergens = Allergen::with('localizedTranslations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Allergen $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'icon' => $a->icon,
                'sortOrder' => $a->sort_order,
                'isActive' => $a->is_active,
                'translations' => $this->locales->translationMap(
                    $a,
                    'localizedTranslations',
                    ['name'],
                    ['name' => $a->name]
                ),
            ]);

        return Inertia::render('admin/allergens/index', [
            'allergens' => $allergens,
            'languages' => $this->locales->languageOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $maxSort = Allergen::max('sort_order') ?? -1;

        $allergen = Allergen::create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $maxSort + 1,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['translations'])) {
            $this->locales->syncTranslations(
                $allergen,
                'localizedTranslations',
                $validated['translations'],
                ['name']
            );
        }

        return to_route('admin.allergens.index')->with('status', 'Allergen created.');
    }

    public function update(Request $request, Allergen $allergen): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $allergen->update([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? $allergen->icon,
            'sort_order' => $validated['sort_order'] ?? $allergen->sort_order,
            'is_active' => $validated['is_active'] ?? $allergen->is_active,
        ]);

        if (array_key_exists('translations', $validated)) {
            $this->locales->syncTranslations(
                $allergen,
                'localizedTranslations',
                $validated['translations'] ?? [],
                ['name']
            );
        }

        return to_route('admin.allergens.index')->with('status', 'Allergen updated.');
    }

    public function destroy(Allergen $allergen): RedirectResponse
    {
        $allergen->delete();

        return to_route('admin.allergens.index')->with('status', 'Allergen deleted.');
    }
}
