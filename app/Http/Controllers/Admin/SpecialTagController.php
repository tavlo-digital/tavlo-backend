<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialTag;
use App\Services\LocaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SpecialTagController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function index(): Response
    {
        $tags = SpecialTag::with('localizedTranslations')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (SpecialTag $t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'label' => $t->label,
                'icon' => $t->icon,
                'sortOrder' => $t->sort_order,
                'isActive' => $t->is_active,
                'translations' => $this->locales->translationMap(
                    $t,
                    'localizedTranslations',
                    ['label'],
                    ['label' => $t->label]
                ),
            ]);

        return Inertia::render('admin/special-tags/index', [
            'tags' => $tags,
            'languages' => $this->locales->languageOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        $maxSort = SpecialTag::max('sort_order') ?? -1;

        $tag = SpecialTag::create([
            'label' => $validated['label'],
            'slug' => $validated['slug'] ?: Str::slug($validated['label']),
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $maxSort + 1,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['translations'])) {
            $this->locales->syncTranslations(
                $tag,
                'localizedTranslations',
                $validated['translations'],
                ['label']
            );
        }

        return to_route('admin.special-tags.index')->with('status', 'Special tag created.');
    }

    public function update(Request $request, SpecialTag $specialTag): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        $specialTag->update([
            'label' => $validated['label'],
            'slug' => $validated['slug'] ?: Str::slug($validated['label']),
            'icon' => $validated['icon'] ?? $specialTag->icon,
            'sort_order' => $validated['sort_order'] ?? $specialTag->sort_order,
            'is_active' => $validated['is_active'] ?? $specialTag->is_active,
        ]);

        if (array_key_exists('translations', $validated)) {
            $this->locales->syncTranslations(
                $specialTag,
                'localizedTranslations',
                $validated['translations'] ?? [],
                ['label']
            );
        }

        return to_route('admin.special-tags.index')->with('status', 'Special tag updated.');
    }

    public function destroy(SpecialTag $specialTag): RedirectResponse
    {
        $specialTag->delete();

        return to_route('admin.special-tags.index')->with('status', 'Special tag deleted.');
    }
}
