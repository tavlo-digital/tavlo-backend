<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\SpecialTag;
use Illuminate\Http\JsonResponse;

class SpecialTagController extends Controller
{
    public function index(): JsonResponse
    {
        $tags = SpecialTag::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (SpecialTag $t) => [
                'id'    => $t->id,
                'slug'  => $t->slug,
                'label' => $t->label,
                'icon'  => $t->icon,
            ]);

        return response()->json(['data' => $tags]);
    }
}
