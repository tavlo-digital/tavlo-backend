<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllergenController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function index(): JsonResponse
    {
        $allergens = Allergen::with('localizedTranslations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Allergen $a) => $this->formatAllergen($a));

        return response()->json(['data' => $allergens]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
            'translations' => ['sometimes', 'array'],
        ]);

        $maxSort = Allergen::max('sort_order') ?? -1;

        $allergen = Allergen::create([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sortOrder'] ?? $maxSort + 1,
            'is_active' => $data['isActive'] ?? true,
        ]);

        $this->locales->syncTranslations(
            $allergen,
            'localizedTranslations',
            $data['translations'] ?? [],
            ['name']
        );

        $allergen->load('localizedTranslations');

        return response()->json(['data' => $this->formatAllergen($allergen)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $allergen = Allergen::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
            'translations' => ['sometimes', 'array'],
        ]);

        if (isset($data['name'])) {
            $allergen->name = $data['name'];
        }
        if (array_key_exists('icon', $data)) {
            $allergen->icon = $data['icon'];
        }
        if (isset($data['sortOrder'])) {
            $allergen->sort_order = $data['sortOrder'];
        }
        if (isset($data['isActive'])) {
            $allergen->is_active = $data['isActive'];
        }

        $allergen->save();

        if (array_key_exists('translations', $data)) {
            $this->locales->syncTranslations(
                $allergen,
                'localizedTranslations',
                $data['translations'],
                ['name']
            );
        }

        $allergen->load('localizedTranslations');

        return response()->json(['data' => $this->formatAllergen($allergen)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $allergen = Allergen::findOrFail($id);
        $allergen->delete();

        return response()->json(['message' => 'Allergen deleted.']);
    }

    private function formatAllergen(Allergen $a): array
    {
        return [
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
        ];
    }
}
