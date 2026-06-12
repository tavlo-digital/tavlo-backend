<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\TaxCategory;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModifierGroupController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    // ─── GET /api/vendor/menu/modifier-groups ────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $groups = ModifierGroup::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->with([
                'localizedTranslations',
                'options' => fn ($q) => $q->where('is_active', true)
                    ->with('localizedTranslations')
                    ->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ModifierGroup $g) => $this->formatGroup(
                $g,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ));

        return response()->json(['data' => $groups]);
    }

    // ─── POST /api/vendor/menu/modifier-groups ───────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:single,multiple,remove'],
            'minSelection' => ['sometimes', 'integer', 'min:0'],
            'maxSelection' => ['sometimes', 'integer', 'min:1'],
            'isRequired' => ['sometimes', 'boolean'],
            'taxCategory' => ['sometimes', 'nullable', 'string', Rule::in(TaxCategory::pluck('slug')->unique())],
            'translations' => ['sometimes', 'array'],
            'options' => ['sometimes', 'array', 'max:10'],
            'options.*.name' => ['required', 'string', 'max:255'],
            'options.*.priceAdjustment' => ['sometimes', 'numeric'],
            'options.*.translations' => ['sometimes', 'array'],
        ]);

        $maxSort = ModifierGroup::where('vendor_id', $vendor->id)->max('sort_order') ?? -1;

        $group = ModifierGroup::create([
            'vendor_id' => $vendor->id,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'single',
            'min_selection' => $data['minSelection'] ?? 0,
            'max_selection' => $data['maxSelection'] ?? 1,
            'is_required' => $data['isRequired'] ?? false,
            'tax_category' => $data['taxCategory'] ?? null,
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);
        $this->locales->syncTranslations(
            $group,
            'localizedTranslations',
            $data['translations'] ?? [],
            ['name']
        );

        if (! empty($data['options'])) {
            foreach ($data['options'] as $i => $opt) {
                $option = $group->options()->create([
                    'name' => $opt['name'],
                    'price_adjustment' => $opt['priceAdjustment'] ?? 0,
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
                $this->locales->syncTranslations(
                    $option,
                    'localizedTranslations',
                    $opt['translations'] ?? [],
                    ['name']
                );
            }
        }

        $group->load([
            'localizedTranslations',
            'options' => fn ($q) => $q->where('is_active', true)
                ->with('localizedTranslations')
                ->orderBy('sort_order'),
        ]);

        return response()->json([
            'data' => $this->formatGroup(
                $group,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ),
        ], 201);
    }

    // ─── PATCH /api/vendor/menu/modifier-groups/{groupId} ────────────────────
    public function update(Request $request, int $groupId): JsonResponse
    {
        $vendor = $request->user();
        $group = ModifierGroup::where('vendor_id', $vendor->id)->findOrFail($groupId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:single,multiple,remove'],
            'minSelection' => ['sometimes', 'integer', 'min:0'],
            'maxSelection' => ['sometimes', 'integer', 'min:1'],
            'isRequired' => ['sometimes', 'boolean'],
            'taxCategory' => ['sometimes', 'nullable', 'string', Rule::in(TaxCategory::pluck('slug')->unique())],
            'isActive' => ['sometimes', 'boolean'],
            'translations' => ['sometimes', 'array'],
            'options' => ['sometimes', 'array', 'max:10'],
            'options.*.id' => ['sometimes', 'integer'],
            'options.*.name' => ['required', 'string', 'max:255'],
            'options.*.priceAdjustment' => ['sometimes', 'numeric'],
            'options.*.isActive' => ['sometimes', 'boolean'],
            'options.*.translations' => ['sometimes', 'array'],
        ]);

        if (isset($data['name'])) {
            $group->name = $data['name'];
        }
        if (isset($data['type'])) {
            $group->type = $data['type'];
        }
        if (isset($data['minSelection'])) {
            $group->min_selection = $data['minSelection'];
        }
        if (isset($data['maxSelection'])) {
            $group->max_selection = $data['maxSelection'];
        }
        if (isset($data['isRequired'])) {
            $group->is_required = $data['isRequired'];
        }
        if (array_key_exists('taxCategory', $data)) {
            $group->tax_category = $data['taxCategory'];
        }
        if (isset($data['isActive'])) {
            $group->is_active = $data['isActive'];
        }

        $group->save();
        if (array_key_exists('translations', $data)) {
            $this->locales->syncTranslations(
                $group,
                'localizedTranslations',
                $data['translations'],
                ['name']
            );
        }

        if (array_key_exists('options', $data)) {
            $keepIds = [];
            foreach ($data['options'] as $i => $opt) {
                if (! empty($opt['id'])) {
                    $option = $group->options()->findOrFail($opt['id']);
                    $option->update([
                        'name' => $opt['name'],
                        'price_adjustment' => $opt['priceAdjustment'] ?? $option->price_adjustment,
                        'sort_order' => $i,
                        'is_active' => $opt['isActive'] ?? true,
                    ]);
                    if (array_key_exists('translations', $opt)) {
                        $this->locales->syncTranslations(
                            $option,
                            'localizedTranslations',
                            $opt['translations'],
                            ['name']
                        );
                    }
                    $keepIds[] = $option->id;
                } else {
                    $newOpt = $group->options()->create([
                        'name' => $opt['name'],
                        'price_adjustment' => $opt['priceAdjustment'] ?? 0,
                        'sort_order' => $i,
                        'is_active' => true,
                    ]);
                    $this->locales->syncTranslations(
                        $newOpt,
                        'localizedTranslations',
                        $opt['translations'] ?? [],
                        ['name']
                    );
                    $keepIds[] = $newOpt->id;
                }
            }
            $group->options()->whereNotIn('id', $keepIds)->delete();
        }

        $group->load([
            'localizedTranslations',
            'options' => fn ($q) => $q->with('localizedTranslations')->orderBy('sort_order'),
        ]);

        return response()->json([
            'data' => $this->formatGroup(
                $group,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ),
        ]);
    }

    // ─── DELETE /api/vendor/menu/modifier-groups/{groupId} ───────────────────
    public function destroy(Request $request, int $groupId): JsonResponse
    {
        $vendor = $request->user();
        $group = ModifierGroup::where('vendor_id', $vendor->id)->findOrFail($groupId);

        // Soft delete: just mark inactive
        $group->update(['is_active' => false]);

        return response()->json(['message' => 'Modifier group removed.']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatGroup(ModifierGroup $g, $vendor, string $locale): array
    {
        return [
            'id' => $g->id,
            'name' => $this->locales->translated(
                $g,
                'localizedTranslations',
                'name',
                $vendor,
                $locale,
                $g->name
            ),
            'translations' => $this->locales->translationMap(
                $g,
                'localizedTranslations',
                ['name'],
                ['name' => $g->name]
            ),
            'type' => $g->type,
            'minSelection' => $g->min_selection,
            'maxSelection' => $g->max_selection,
            'isRequired' => $g->is_required,
            'taxCategory' => $g->tax_category,
            'sortOrder' => $g->sort_order,
            'isActive' => $g->is_active,
            'options' => $g->options->map(fn (ModifierOption $o) => [
                'id' => $o->id,
                'name' => $this->locales->translated(
                    $o,
                    'localizedTranslations',
                    'name',
                    $vendor,
                    $locale,
                    $o->name
                ),
                'translations' => $this->locales->translationMap(
                    $o,
                    'localizedTranslations',
                    ['name'],
                    ['name' => $o->name]
                ),
                'priceAdjustment' => (float) $o->price_adjustment,
                'sortOrder' => $o->sort_order,
                'isActive' => $o->is_active,
            ])->values()->toArray(),
        ];
    }
}
