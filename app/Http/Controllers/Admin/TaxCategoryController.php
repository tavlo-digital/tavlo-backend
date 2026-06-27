<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\TaxCategory;
use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaxCategoryController extends Controller
{
    public function __construct(
        private readonly LocaleService $locales,
    ) {}

    public function index(): Response
    {
        $categories = TaxCategory::query()
            ->with('localizedTranslations')
            ->orderBy('country')
            ->orderBy('slug')
            ->get()
            ->map(fn (TaxCategory $tc) => [
                'id'       => $tc->id,
                'country'  => $tc->country,
                'slug'     => $tc->slug,
                'name'     => $tc->name,
                'vatRate'  => (float) $tc->vat_rate,
                'isActive' => $tc->is_active,
                'translations' => $this->locales->translationMap(
                    $tc,
                    'localizedTranslations',
                    ['name'],
                    ['name' => $tc->name]
                ),
            ]);

        $countries = Country::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Country $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'flag' => $c->flag,
            ]);

        return Inertia::render('admin/tax-categories/index', [
            'categories' => $categories,
            'countries'  => $countries,
            'languages'  => $this->locales->languageOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country'  => ['required', 'string', 'max:5'],
            'slug'     => [
                'required', 'string', 'max:255',
                Rule::unique('tax_categories')->where('country', $request->input('country')),
            ],
            'name'     => ['required', 'string', 'max:255'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $category = TaxCategory::create([
            'country'   => $validated['country'],
            'slug'      => $validated['slug'],
            'name'      => $validated['name'],
            'vat_rate'  => $validated['vat_rate'],
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

        return to_route('admin.tax-categories.index')->with('status', 'Tax category created.');
    }

    public function update(Request $request, TaxCategory $taxCategory): RedirectResponse
    {
        $validated = $request->validate([
            'country'  => ['required', 'string', 'max:5'],
            'slug'     => [
                'required', 'string', 'max:255',
                Rule::unique('tax_categories')->where('country', $request->input('country'))->ignore($taxCategory->id),
            ],
            'name'     => ['required', 'string', 'max:255'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $taxCategory->update([
            'country'   => $validated['country'],
            'slug'      => $validated['slug'],
            'name'      => $validated['name'],
            'vat_rate'  => $validated['vat_rate'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        $translations = $validated['translations'] ?? [];
        $translations['en'] = ['name' => $validated['name']];
        $this->locales->syncTranslations(
            $taxCategory,
            'localizedTranslations',
            $translations,
            ['name']
        );

        return to_route('admin.tax-categories.index')->with('status', 'Tax category updated.');
    }

    public function destroy(TaxCategory $taxCategory): RedirectResponse
    {
        $taxCategory->delete();

        return to_route('admin.tax-categories.index')->with('status', 'Tax category deleted.');
    }
}
