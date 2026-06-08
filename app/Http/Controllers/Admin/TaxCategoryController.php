<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\TaxCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaxCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = TaxCategory::query()
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
        ]);

        TaxCategory::create([
            'country'   => $validated['country'],
            'slug'      => $validated['slug'],
            'name'      => $validated['name'],
            'vat_rate'  => $validated['vat_rate'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

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
        ]);

        $taxCategory->update([
            'country'   => $validated['country'],
            'slug'      => $validated['slug'],
            'name'      => $validated['name'],
            'vat_rate'  => $validated['vat_rate'],
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return to_route('admin.tax-categories.index')->with('status', 'Tax category updated.');
    }

    public function destroy(TaxCategory $taxCategory): RedirectResponse
    {
        $taxCategory->delete();

        return to_route('admin.tax-categories.index')->with('status', 'Tax category deleted.');
    }
}
