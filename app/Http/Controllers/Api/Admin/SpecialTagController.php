<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpecialTag;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SpecialTagController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    public function index(): JsonResponse
    {
        $tags = SpecialTag::with('localizedTranslations')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn (SpecialTag $t) => $this->formatTag($t));

        return response()->json(['data' => $tags]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
            'translations' => ['sometimes', 'array'],
        ]);

        $maxSort = SpecialTag::max('sort_order') ?? -1;

        $tag = SpecialTag::create([
            'label' => $data['label'],
            'slug' => $data['slug'] ?? Str::slug($data['label']),
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sortOrder'] ?? $maxSort + 1,
            'is_active' => $data['isActive'] ?? true,
        ]);

        $this->locales->syncTranslations(
            $tag,
            'localizedTranslations',
            $data['translations'] ?? [],
            ['label']
        );

        $tag->load('localizedTranslations');

        return response()->json(['data' => $this->formatTag($tag)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tag = SpecialTag::findOrFail($id);

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
            'isActive' => ['sometimes', 'boolean'],
            'translations' => ['sometimes', 'array'],
        ]);

        if (isset($data['label'])) {
            $tag->label = $data['label'];
        }
        if (array_key_exists('slug', $data)) {
            $tag->slug = $data['slug'] ?? Str::slug($tag->label);
        }
        if (array_key_exists('icon', $data)) {
            $tag->icon = $data['icon'];
        }
        if (isset($data['sortOrder'])) {
            $tag->sort_order = $data['sortOrder'];
        }
        if (isset($data['isActive'])) {
            $tag->is_active = $data['isActive'];
        }

        $tag->save();

        if (array_key_exists('translations', $data)) {
            $this->locales->syncTranslations(
                $tag,
                'localizedTranslations',
                $data['translations'],
                ['label']
            );
        }

        $tag->load('localizedTranslations');

        return response()->json(['data' => $this->formatTag($tag)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $tag = SpecialTag::findOrFail($id);
        $tag->delete();

        return response()->json(['message' => 'Special tag deleted.']);
    }

    private function formatTag(SpecialTag $t): array
    {
        return [
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
        ];
    }
}
