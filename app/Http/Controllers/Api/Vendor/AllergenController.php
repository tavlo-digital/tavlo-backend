<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllergenController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();
        $locale = $this->locales->dashboardLanguage($vendor);

        $allergens = Allergen::where('is_active', true)
            ->with('localizedTranslations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Allergen $a) => [
                'id' => $a->id,
                'key' => $a->name,
                'name' => $this->locales->translated(
                    $a,
                    'localizedTranslations',
                    'name',
                    $vendor,
                    $locale,
                    $a->name
                ),
                'icon' => $a->icon,
                'translations' => $this->locales->translationMap(
                    $a,
                    'localizedTranslations',
                    ['name'],
                    ['name' => $a->name]
                ),
            ]);

        return response()->json(['data' => $allergens]);
    }
}
