<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DietaryPreference;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DietaryPreferenceController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();
        $locale = $this->locales->dashboardLanguage($vendor);

        $preferences = DietaryPreference::where('is_active', true)
            ->with('localizedTranslations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DietaryPreference $preference) => [
                'id' => $preference->id,
                'slug' => $preference->slug,
                'name' => $this->locales->translated(
                    $preference,
                    'localizedTranslations',
                    'name',
                    $vendor,
                    $locale,
                    $preference->name
                ),
                'icon' => $preference->icon,
                'translations' => $this->locales->translationMap(
                    $preference,
                    'localizedTranslations',
                    ['name'],
                    ['name' => $preference->name]
                ),
            ]);

        return response()->json(['data' => $preferences]);
    }
}
