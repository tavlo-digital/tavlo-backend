<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CountryController extends Controller
{
    public function index(): Response
    {
        $countries = Country::query()
            ->withCount('taxCategories')
            ->orderBy('name')
            ->get()
            ->map(fn (Country $c) => [
                'id'               => $c->id,
                'code'             => $c->code,
                'name'             => $c->name,
                'flag'             => $c->flag,
                'currency'         => $c->currency,
                'isActive'         => $c->is_active,
                'taxCategoryCount' => $c->tax_categories_count,
            ]);

        return Inertia::render('admin/countries/index', [
            'countries' => $countries,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'      => ['required', 'string', 'max:5', Rule::unique('countries', 'code')],
            'name'      => ['required', 'string', 'max:255'],
            'flag'      => ['nullable', 'string', 'max:10'],
            'currency'  => ['required', 'string', 'max:5'],
            'is_active' => ['boolean'],
        ]);

        Country::create([
            'code'      => strtoupper($validated['code']),
            'name'      => $validated['name'],
            'flag'      => $validated['flag'] ?? null,
            'currency'  => strtoupper($validated['currency']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return to_route('admin.countries.index')->with('status', 'Country added.');
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        $validated = $request->validate([
            'code'      => ['required', 'string', 'max:5', Rule::unique('countries', 'code')->ignore($country->id)],
            'name'      => ['required', 'string', 'max:255'],
            'flag'      => ['nullable', 'string', 'max:10'],
            'currency'  => ['required', 'string', 'max:5'],
            'is_active' => ['boolean'],
        ]);

        $country->update([
            'code'      => strtoupper($validated['code']),
            'name'      => $validated['name'],
            'flag'      => $validated['flag'] ?? null,
            'currency'  => strtoupper($validated['currency']),
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return to_route('admin.countries.index')->with('status', 'Country updated.');
    }

    public function destroy(Country $country): RedirectResponse
    {
        if ($country->taxCategories()->exists()) {
            return back()->withErrors([
                'country' => 'This country has tax categories and cannot be deleted. Remove its tax categories first.',
            ]);
        }

        $country->delete();

        return to_route('admin.countries.index')->with('status', 'Country deleted.');
    }
}
