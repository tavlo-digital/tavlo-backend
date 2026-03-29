<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use Illuminate\Http\JsonResponse;

class AllergenController extends Controller
{
    public function index(): JsonResponse
    {
        $allergens = Allergen::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Allergen $a) => [
                'id'   => $a->id,
                'name' => $a->name,
                'icon' => $a->icon,
            ]);

        return response()->json(['data' => $allergens]);
    }
}
