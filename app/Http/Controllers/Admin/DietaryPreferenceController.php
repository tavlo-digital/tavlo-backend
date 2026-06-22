<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DietaryPreference;
use App\Models\MenuItem;
use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DietaryPreferenceController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function index(): Response
    {
        $preferences = DietaryPreference::with('localizedTranslations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DietaryPreference $preference) => [
                'id' => $preference->id,
                'slug' => $preference->slug,
                'name' => $preference->name,
                'icon' => $preference->icon,
                'sortOrder' => $preference->sort_order,
                'isActive' => $preference->is_active,
                'itemCount' => MenuItem::where('dietary_preference', $preference->slug)->count(),
                'translations' => $this->locales->translationMap(
                    $preference,
                    'localizedTranslations',
                    ['name'],
                    ['name' => $preference->name]
                ),
            ]);

        return Inertia::render('admin/dietary-preferences/index', [
            'preferences' => $preferences,
            'languages' => $this->locales->languageOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePreference($request);
        $slug = $validated['slug'] ?: Str::slug($validated['name']);

        if (DietaryPreference::where('slug', $slug)->exists()) {
            return back()->withErrors(['slug' => 'A dietary preference with this slug already exists.'])->withInput();
        }

        $preference = DietaryPreference::create([
            'slug' => $slug,
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? ((DietaryPreference::max('sort_order') ?? -1) + 1),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $this->syncTranslations($preference, $validated);

        return to_route('admin.dietary-preferences.index')->with('status', 'Dietary preference created.');
    }

    public function update(Request $request, DietaryPreference $dietaryPreference): RedirectResponse
    {
        $validated = $this->validatePreference($request, $dietaryPreference);
        $slug = $validated['slug'] ?: Str::slug($validated['name']);
        $previousSlug = $dietaryPreference->slug;

        $dietaryPreference->update([
            'slug' => $slug,
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $dietaryPreference->sort_order,
            'is_active' => $validated['is_active'] ?? $dietaryPreference->is_active,
        ]);

        if ($slug !== $previousSlug) {
            MenuItem::where('dietary_preference', $previousSlug)->update(['dietary_preference' => $slug]);
        }

        $this->syncTranslations($dietaryPreference, $validated);

        return to_route('admin.dietary-preferences.index')->with('status', 'Dietary preference updated.');
    }

    public function destroy(DietaryPreference $dietaryPreference): RedirectResponse
    {
        if (MenuItem::where('dietary_preference', $dietaryPreference->slug)->exists()) {
            return back()->withErrors(['preference' => 'This dietary preference is used by menu items. Deactivate it instead.']);
        }

        $dietaryPreference->delete();

        return to_route('admin.dietary-preferences.index')->with('status', 'Dietary preference deleted.');
    }

    private function validatePreference(Request $request, ?DietaryPreference $preference = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:64', Rule::unique('dietary_preferences', 'slug')->ignore($preference?->id)],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function syncTranslations(DietaryPreference $preference, array $validated): void
    {
        $translations = $validated['translations'] ?? [];
        $translations['en'] = ['name' => $validated['name']];
        $this->locales->syncTranslations($preference, 'localizedTranslations', $translations, ['name']);
    }
}
