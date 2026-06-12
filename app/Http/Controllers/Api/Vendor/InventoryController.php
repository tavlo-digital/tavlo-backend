<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventorySettings;
use App\Models\Vendor;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    /**
     * GET /api/vendor/{vendorId}/inventory/items
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $items = $vendor->inventoryItems()
            ->with(['localizedTranslations', 'inventoryCategory.localizedTranslations'])
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => $this->formatItem(
                $item,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ));

        return response()->json($items);
    }

    /**
     * POST /api/vendor/{vendorId}/inventory/items
     */
    public function store(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'categoryId' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'minStock' => ['required', 'numeric', 'min:0'],
            'reorderQuantity' => ['required', 'numeric', 'min:0'],
            'costPerUnit' => ['required', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'isCritical' => ['nullable', 'boolean'],
            'autoReorder' => ['nullable', 'boolean'],
            'trackStock' => ['nullable', 'boolean'],
            'nutrition' => ['nullable', 'array'],
            'translations' => ['sometimes', 'array'],
        ]);

        $translations = $this->locales->normalizeTranslationPayload(
            $data['translations'] ?? [],
            ['name', 'supplier']
        );
        $name = $this->baseName($vendor, $data['name'] ?? null, $translations);
        $supplier = $this->baseTranslatedValue(
            $vendor,
            $data['supplier'] ?? null,
            $translations,
            'supplier',
            'supplier'
        );
        $category = $this->resolveCategoryFromPayload($vendor, $data);

        $item = $vendor->inventoryItems()->create([
            'inventory_category_id' => $category?->id,
            'name' => $name,
            'category' => $category?->name ?? $data['category'] ?? null,
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'min_stock' => $data['minStock'],
            'reorder_quantity' => $data['reorderQuantity'],
            'cost_per_unit' => $data['costPerUnit'],
            'supplier' => $supplier,
            'is_critical' => $data['isCritical'] ?? false,
            'auto_reorder' => $data['autoReorder'] ?? false,
            'track_stock' => $data['trackStock'] ?? false,
            'nutrition' => $data['nutrition'] ?? null,
        ]);

        $translationPayload = $translations !== []
            ? $translations
            : ['en' => ['name' => $name, 'supplier' => $supplier]];
        $this->locales->syncTranslations(
            $item,
            'localizedTranslations',
            $translationPayload,
            ['name', 'supplier']
        );
        $item->load(['localizedTranslations', 'inventoryCategory.localizedTranslations']);

        return response()->json(
            $this->formatItem($item, $vendor, $this->locales->dashboardLanguage($vendor)),
            201
        );
    }

    /**
     * PATCH /api/vendor/{vendorId}/inventory/items/{itemId}
     */
    public function update(Request $request, string $vendorId, int $itemId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $item = $vendor->inventoryItems()->findOrFail($itemId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'categoryId' => ['sometimes', 'nullable', 'integer', 'exists:inventory_categories,id'],
            'category' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'minStock' => ['sometimes', 'numeric', 'min:0'],
            'reorderQuantity' => ['sometimes', 'numeric', 'min:0'],
            'costPerUnit' => ['sometimes', 'numeric', 'min:0'],
            'supplier' => ['sometimes', 'string', 'max:255'],
            'isCritical' => ['nullable', 'boolean'],
            'autoReorder' => ['nullable', 'boolean'],
            'trackStock' => ['nullable', 'boolean'],
            'nutrition' => ['nullable', 'array'],
            'translations' => ['sometimes', 'array'],
        ]);

        $mapped = [];
        $keyMap = [
            'name' => 'name',
            'quantity' => 'quantity',
            'unit' => 'unit',
            'minStock' => 'min_stock',
            'reorderQuantity' => 'reorder_quantity',
            'costPerUnit' => 'cost_per_unit',
            'supplier' => 'supplier',
            'isCritical' => 'is_critical',
            'autoReorder' => 'auto_reorder',
            'trackStock' => 'track_stock',
            'nutrition' => 'nutrition',
        ];

        foreach ($data as $key => $value) {
            if (isset($keyMap[$key])) {
                $mapped[$keyMap[$key]] = $value;
            }
        }

        if (array_key_exists('categoryId', $data) || array_key_exists('category', $data)) {
            $category = $this->resolveCategoryFromPayload($vendor, $data);
            $mapped['inventory_category_id'] = $category?->id;
            $mapped['category'] = $category?->name ?? $data['category'] ?? null;
        }

        $item->update($mapped);

        if (array_key_exists('translations', $data)) {
            $this->locales->syncTranslations(
                $item,
                'localizedTranslations',
                $data['translations'],
                ['name', 'supplier']
            );
        }
        if (array_key_exists('name', $data) || array_key_exists('supplier', $data)) {
            $this->locales->syncTranslations(
                $item,
                'localizedTranslations',
                ['en' => array_filter([
                    'name' => $data['name'] ?? null,
                    'supplier' => $data['supplier'] ?? null,
                ], fn ($value) => $value !== null)],
                ['name', 'supplier']
            );
        }

        $item = $item->fresh(['localizedTranslations', 'inventoryCategory.localizedTranslations']);

        return response()->json(
            $this->formatItem($item, $vendor, $this->locales->dashboardLanguage($vendor))
        );
    }

    /**
     * DELETE /api/vendor/{vendorId}/inventory/items/{itemId}
     */
    public function destroy(Request $request, string $vendorId, int $itemId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $item = $vendor->inventoryItems()->findOrFail($itemId);
        $item->delete();

        return response()->json(['message' => 'Inventory item deleted']);
    }

    /**
     * GET /api/vendor/{vendorId}/inventory/settings
     */
    public function settings(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $settings = $vendor->inventorySettings ?? InventorySettings::create([
            'vendor_id' => $vendor->id,
        ]);

        $stored = $settings->settings ?? [];

        return response()->json([
            'general' => $stored['general'] ?? [
                'enableInventoryTracking' => $settings->low_stock_alerts ?? true,
                'enableAutoStockDeduction' => $settings->link_menu_items ?? true,
                'allowNegativeStock' => false,
            ],
            'automation' => $stored['automation'] ?? [
                'enableAIStockPrediction' => false,
                'enableLowStockAlerts' => $settings->low_stock_alerts ?? true,
                'enableAutoGeneratedPurchaseOrders' => $settings->auto_reorder_enabled ?? false,
                'autoOrderApprovalMode' => 'draft',
                'budgetCapPerOrder' => 500,
            ],
            'availability' => $stored['availability'] ?? [
                'autoMarkUnavailableWhenCriticalOut' => true,
                'autoMarkUnavailableWhenAllOut' => false,
            ],
            'suppliers' => $stored['suppliers'] ?? [],
            'alerts' => $stored['alerts'] ?? [
                'dashboardAlerts' => true,
                'emailAlerts' => $settings->low_stock_alerts ?? true,
                'smsAlerts' => false,
                'alertFrequency' => 'immediate',
                'dailyLowStockSummary' => true,
                'dailySummaryTime' => '09:00',
            ],
        ]);
    }

    /**
     * PUT /api/vendor/{vendorId}/inventory/settings
     */
    public function updateSettings(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'general' => ['nullable', 'array'],
            'general.enableInventoryTracking' => ['nullable', 'boolean'],
            'general.enableAutoStockDeduction' => ['nullable', 'boolean'],
            'general.allowNegativeStock' => ['nullable', 'boolean'],
            'automation' => ['nullable', 'array'],
            'automation.enableAIStockPrediction' => ['nullable', 'boolean'],
            'automation.enableLowStockAlerts' => ['nullable', 'boolean'],
            'automation.enableAutoGeneratedPurchaseOrders' => ['nullable', 'boolean'],
            'automation.autoOrderApprovalMode' => ['nullable', 'string', 'in:draft,auto_send'],
            'automation.budgetCapPerOrder' => ['nullable', 'numeric', 'min:0'],
            'availability' => ['nullable', 'array'],
            'availability.autoMarkUnavailableWhenCriticalOut' => ['nullable', 'boolean'],
            'availability.autoMarkUnavailableWhenAllOut' => ['nullable', 'boolean'],
            'suppliers' => ['nullable', 'array'],
            'suppliers.*.id' => ['required', 'string'],
            'suppliers.*.name' => ['required', 'string', 'max:255'],
            'suppliers.*.leadTime' => ['nullable', 'integer', 'min:0'],
            'suppliers.*.orderingMethod' => ['nullable', 'string', 'in:Email,API,Phone'],
            'suppliers.*.status' => ['nullable', 'string', 'in:active,inactive'],
            'suppliers.*.email' => ['nullable', 'email', 'max:255'],
            'suppliers.*.phone' => ['nullable', 'string', 'max:50'],
            'suppliers.*.supportedIngredients' => ['nullable', 'array'],
            'alerts' => ['nullable', 'array'],
            'alerts.dashboardAlerts' => ['nullable', 'boolean'],
            'alerts.emailAlerts' => ['nullable', 'boolean'],
            'alerts.smsAlerts' => ['nullable', 'boolean'],
            'alerts.alertFrequency' => ['nullable', 'string', 'in:immediate,daily,weekly'],
            'alerts.dailyLowStockSummary' => ['nullable', 'boolean'],
            'alerts.dailySummaryTime' => ['nullable', 'string'],
        ]);

        $existing = $vendor->inventorySettings;
        $stored = $existing?->settings ?? [];

        if (isset($data['general'])) {
            $stored['general'] = $data['general'];
        }
        if (isset($data['automation'])) {
            $stored['automation'] = $data['automation'];
        }
        if (isset($data['availability'])) {
            $stored['availability'] = $data['availability'];
        }
        if (isset($data['suppliers'])) {
            $stored['suppliers'] = $data['suppliers'];
        }
        if (isset($data['alerts'])) {
            $stored['alerts'] = $data['alerts'];
        }

        // Also sync flat columns for backwards compatibility
        $lowStockAlerts = $data['automation']['enableLowStockAlerts'] ?? $data['alerts']['emailAlerts'] ?? $existing?->low_stock_alerts ?? true;
        $autoReorder = $data['automation']['enableAutoGeneratedPurchaseOrders'] ?? $existing?->auto_reorder_enabled ?? false;
        $linkMenuItems = $data['general']['enableAutoStockDeduction'] ?? $existing?->link_menu_items ?? true;

        $settings = $vendor->inventorySettings()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'low_stock_alerts' => $lowStockAlerts,
                'auto_reorder_enabled' => $autoReorder,
                'link_menu_items' => $linkMenuItems,
                'settings' => $stored,
            ]
        );

        $s = $settings->settings ?? [];

        return response()->json([
            'general' => $s['general'] ?? [],
            'automation' => $s['automation'] ?? [],
            'availability' => $s['availability'] ?? [],
            'suppliers' => $s['suppliers'] ?? [],
            'alerts' => $s['alerts'] ?? [],
        ]);
    }

    // ----------------------------------------------------------------
    // Categories
    // ----------------------------------------------------------------

    private const DEFAULT_CATEGORIES = [];

    private const DEFAULT_UNITS = [];

    /**
     * GET /api/vendor/{vendorId}/inventory/categories
     */
    public function categoriesIndex(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $locale = $this->locales->dashboardLanguage($vendor);
        $cats = $vendor->inventoryCategories()
            ->with('localizedTranslations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryCategory $category) => $this->formatCategory($category, $vendor, $locale));

        return response()->json($cats);
    }

    /**
     * POST /api/vendor/{vendorId}/inventory/categories
     */
    public function categoriesStore(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'translations' => ['sometimes', 'array'],
        ]);
        $translations = $this->locales->normalizeTranslationPayload($data['translations'] ?? [], ['name']);
        $name = $this->baseName($vendor, $data['name'] ?? null, $translations);

        if ($vendor->inventoryCategories()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            return response()->json(['message' => 'Category already exists'], 422);
        }

        $category = $vendor->inventoryCategories()->create([
            'name' => $name,
            'sort_order' => ($vendor->inventoryCategories()->max('sort_order') ?? -1) + 1,
        ]);
        $translationPayload = $translations !== []
            ? $translations
            : ['en' => ['name' => $name]];
        $this->locales->syncTranslations(
            $category,
            'localizedTranslations',
            $translationPayload,
            ['name']
        );
        $category->load('localizedTranslations');

        return response()->json(
            $this->formatCategory($category, $vendor, $this->locales->dashboardLanguage($vendor)),
            201
        );
    }

    /**
     * PATCH /api/vendor/{vendorId}/inventory/categories/{categoryId}
     */
    public function categoriesUpdate(
        Request $request,
        string $vendorId,
        int $categoryId
    ): JsonResponse {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $category = $vendor->inventoryCategories()->findOrFail($categoryId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'translations' => ['sometimes', 'array'],
        ]);

        if (array_key_exists('name', $data)) {
            $duplicate = $vendor->inventoryCategories()
                ->where('id', '!=', $category->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
                ->exists();

            if ($duplicate) {
                return response()->json(['message' => 'Category already exists'], 422);
            }

            $category->update(['name' => $data['name']]);
            $this->locales->syncTranslations(
                $category,
                'localizedTranslations',
                ['en' => ['name' => $data['name']]],
                ['name']
            );
        }

        if (array_key_exists('translations', $data)) {
            $this->locales->syncTranslations(
                $category,
                'localizedTranslations',
                $data['translations'],
                ['name']
            );
        }

        $category->load('localizedTranslations');

        return response()->json(
            $this->formatCategory($category, $vendor, $this->locales->dashboardLanguage($vendor))
        );
    }

    /**
     * DELETE /api/vendor/{vendorId}/inventory/categories/{categoryId}
     */
    public function categoriesDestroy(Request $request, string $vendorId, string $categoryId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $category = is_numeric($categoryId)
            ? $vendor->inventoryCategories()->findOrFail((int) $categoryId)
            : $vendor->inventoryCategories()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryId)])
                ->firstOrFail();

        DB::transaction(function () use ($category) {
            $category->items()->update(['inventory_category_id' => null, 'category' => null]);
            $category->delete();
        });

        return response()->json(['message' => 'Inventory category deleted']);
    }

    // ----------------------------------------------------------------
    // Units
    // ----------------------------------------------------------------

    private function normalizeUnits(array $units): array
    {
        return array_values(array_map(function ($unit) {
            if (is_string($unit)) {
                return ['name' => $unit, 'translations' => ['en' => ['name' => $unit]]];
            }

            return $unit;
        }, $units));
    }

    /**
     * GET /api/vendor/{vendorId}/inventory/units
     */
    public function unitsIndex(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $settings = $vendor->inventorySettings;
        $units = $this->normalizeUnits($settings?->units ?? self::DEFAULT_UNITS);

        return response()->json($units);
    }

    /**
     * POST /api/vendor/{vendorId}/inventory/units
     */
    public function unitsStore(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:50'],
            'translations' => ['sometimes', 'array'],
        ]);

        $translations = $this->locales->normalizeTranslationPayload($data['translations'] ?? [], ['name']);
        $name = $this->baseTranslatedValue($vendor, $data['name'] ?? null, $translations, 'name', 'name');

        $settings = $this->getOrCreateSettings($vendor);
        $units = $this->normalizeUnits($settings->units ?? self::DEFAULT_UNITS);

        $existingNames = array_map(fn ($u) => strtolower($u['name']), $units);
        if (in_array(strtolower($name), $existingNames)) {
            return response()->json(['message' => 'Unit already exists'], 422);
        }

        $translationPayload = $translations !== [] ? $translations : ['en' => ['name' => $name]];
        $units[] = ['name' => $name, 'translations' => $translationPayload];
        $settings->update(['units' => $units]);

        return response()->json($units, 201);
    }

    /**
     * PATCH /api/vendor/{vendorId}/inventory/units/{name}
     */
    public function unitsUpdate(Request $request, string $vendorId, string $name): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'translations' => ['required', 'array'],
        ]);

        $settings = $this->getOrCreateSettings($vendor);
        $units = $this->normalizeUnits($settings->units ?? self::DEFAULT_UNITS);

        $index = collect($units)->search(fn ($u) => strtolower($u['name']) === strtolower($name));
        if ($index === false) {
            return response()->json(['message' => 'Unit not found'], 404);
        }

        $translations = $this->locales->normalizeTranslationPayload($data['translations'], ['name']);
        $newName = $this->baseTranslatedValue($vendor, null, $translations, 'name', 'name');

        $units[$index]['translations'] = array_merge($units[$index]['translations'] ?? [], $translations);
        $units[$index]['name'] = $newName;
        $settings->update(['units' => array_values($units)]);

        return response()->json(array_values($units));
    }

    /**
     * DELETE /api/vendor/{vendorId}/inventory/units/{name}
     */
    public function unitsDestroy(Request $request, string $vendorId, string $name): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $settings = $this->getOrCreateSettings($vendor);
        $units = $this->normalizeUnits($settings->units ?? self::DEFAULT_UNITS);
        $units = array_values(array_filter(
            $units,
            fn ($u) => strtolower($u['name']) !== strtolower($name)
        ));
        $settings->update(['units' => $units]);

        return response()->json($units);
    }

    private function getOrCreateSettings(Vendor $vendor): InventorySettings
    {
        return $vendor->inventorySettings ?? InventorySettings::create(['vendor_id' => $vendor->id]);
    }

    // ----------------------------------------------------------------

    private function formatItem(InventoryItem $item, Vendor $vendor, string $locale): array
    {
        $category = $item->inventoryCategory;

        return [
            'id' => (string) $item->id,
            'name' => $this->locales->translated(
                $item,
                'localizedTranslations',
                'name',
                $vendor,
                $locale,
                $item->name
            ),
            'translations' => $this->locales->translationMap(
                $item,
                'localizedTranslations',
                ['name', 'supplier']
            ),
            'categoryId' => $category?->id,
            'category' => $category
                ? $this->locales->translated(
                    $category,
                    'localizedTranslations',
                    'name',
                    $vendor,
                    $locale,
                    $category->name
                )
                : $item->category,
            'quantity' => (float) $item->quantity,
            'unit' => $item->unit,
            'minStock' => (float) $item->min_stock,
            'reorderQuantity' => (float) $item->reorder_quantity,
            'costPerUnit' => (float) $item->cost_per_unit,
            'supplier' => $this->locales->translated(
                $item,
                'localizedTranslations',
                'supplier',
                $vendor,
                $locale,
                $item->supplier
            ),
            'isCritical' => $item->is_critical,
            'autoReorder' => $item->auto_reorder,
            'trackStock' => $item->track_stock,
            'nutrition' => $item->nutrition,
            'lastUpdated' => $item->updated_at?->toISOString(),
        ];
    }

    private function formatCategory(InventoryCategory $category, Vendor $vendor, string $locale): array
    {
        return [
            'id' => $category->id,
            'name' => $this->locales->translated(
                $category,
                'localizedTranslations',
                'name',
                $vendor,
                $locale,
                $category->name
            ),
            'translations' => $this->locales->translationMap(
                $category,
                'localizedTranslations',
                ['name']
            ),
        ];
    }

    private function baseName(Vendor $vendor, mixed $name, array $translations): string
    {
        return $this->baseTranslatedValue(
            $vendor,
            $name,
            $translations,
            'name',
            'name'
        );
    }

    private function baseTranslatedValue(
        Vendor $vendor,
        mixed $value,
        array $translations,
        string $field,
        string $label
    ): string {
        $value = is_string($value) ? trim($value) : '';
        $default = $this->locales->defaultLanguage($vendor);
        $value = $value
            ?: ($translations['en'][$field] ?? '')
            ?: ($translations[$default][$field] ?? '')
            ?: collect($translations)
                ->pluck($field)
                ->first(fn ($translation) => is_string($translation) && trim($translation) !== '');

        if (! is_string($value) || trim($value) === '') {
            throw ValidationException::withMessages([
                $label => ["A {$label} is required in at least one enabled language."],
            ]);
        }

        return trim($value);
    }

    private function resolveCategoryFromPayload(Vendor $vendor, array $data): ?InventoryCategory
    {
        if (array_key_exists('categoryId', $data) && $data['categoryId'] !== null) {
            return $vendor->inventoryCategories()->findOrFail((int) $data['categoryId']);
        }

        $legacyName = trim((string) ($data['category'] ?? ''));
        if ($legacyName === '') {
            return null;
        }

        $category = $vendor->inventoryCategories()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($legacyName)])
            ->first();

        if ($category) {
            return $category;
        }

        $category = $vendor->inventoryCategories()->create([
            'name' => $legacyName,
            'sort_order' => ($vendor->inventoryCategories()->max('sort_order') ?? -1) + 1,
        ]);
        $this->locales->syncTranslations(
            $category,
            'localizedTranslations',
            ['en' => ['name' => $legacyName]],
            ['name']
        );

        return $category;
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->orWhere('id', $vendorId)
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
