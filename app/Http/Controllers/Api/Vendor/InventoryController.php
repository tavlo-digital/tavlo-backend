<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventorySettings;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/inventory/items
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $items = $vendor->inventoryItems()
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $item) => $this->formatItem($item));

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
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'minStock' => ['required', 'numeric', 'min:0'],
            'reorderQuantity' => ['required', 'numeric', 'min:0'],
            'costPerUnit' => ['required', 'numeric', 'min:0'],
            'supplier' => ['required', 'string', 'max:255'],
            'isCritical' => ['nullable', 'boolean'],
            'autoReorder' => ['nullable', 'boolean'],
            'trackStock' => ['nullable', 'boolean'],
            'nutrition' => ['nullable', 'array'],
        ]);

        $item = $vendor->inventoryItems()->create([
            'name' => $data['name'],
            'category' => $data['category'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'min_stock' => $data['minStock'],
            'reorder_quantity' => $data['reorderQuantity'],
            'cost_per_unit' => $data['costPerUnit'],
            'supplier' => $data['supplier'],
            'is_critical' => $data['isCritical'] ?? false,
            'auto_reorder' => $data['autoReorder'] ?? false,
            'track_stock' => $data['trackStock'] ?? false,
            'nutrition' => $data['nutrition'] ?? null,
        ]);

        return response()->json($this->formatItem($item), 201);
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
        ]);

        $mapped = [];
        $keyMap = [
            'name' => 'name',
            'category' => 'category',
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

        $item->update($mapped);

        return response()->json($this->formatItem($item->fresh()));
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

        if (isset($data['general']))      $stored['general']      = $data['general'];
        if (isset($data['automation']))   $stored['automation']   = $data['automation'];
        if (isset($data['availability'])) $stored['availability'] = $data['availability'];
        if (isset($data['suppliers']))    $stored['suppliers']    = $data['suppliers'];
        if (isset($data['alerts']))       $stored['alerts']       = $data['alerts'];

        // Also sync flat columns for backwards compatibility
        $lowStockAlerts    = $data['automation']['enableLowStockAlerts'] ?? $data['alerts']['emailAlerts'] ?? $existing?->low_stock_alerts ?? true;
        $autoReorder       = $data['automation']['enableAutoGeneratedPurchaseOrders'] ?? $existing?->auto_reorder_enabled ?? false;
        $linkMenuItems     = $data['general']['enableAutoStockDeduction'] ?? $existing?->link_menu_items ?? true;

        $settings = $vendor->inventorySettings()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'low_stock_alerts'    => $lowStockAlerts,
                'auto_reorder_enabled'=> $autoReorder,
                'link_menu_items'     => $linkMenuItems,
                'settings'            => $stored,
            ]
        );

        $s = $settings->settings ?? [];

        return response()->json([
            'general'      => $s['general']      ?? [],
            'automation'   => $s['automation']   ?? [],
            'availability' => $s['availability'] ?? [],
            'suppliers'    => $s['suppliers']    ?? [],
            'alerts'       => $s['alerts']       ?? [],
        ]);
    }

    // ----------------------------------------------------------------
    // Categories
    // ----------------------------------------------------------------

    private const DEFAULT_CATEGORIES = [];
    private const DEFAULT_UNITS      = [];

    /**
     * GET /api/vendor/{vendorId}/inventory/categories
     */
    public function categoriesIndex(string $vendorId): JsonResponse
    {
        $vendor   = $this->resolveVendor($vendorId);
        $settings = $vendor->inventorySettings;
        $cats     = $settings?->categories ?? self::DEFAULT_CATEGORIES;

        return response()->json($cats);
    }

    /**
     * POST /api/vendor/{vendorId}/inventory/categories
     */
    public function categoriesStore(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $settings = $this->getOrCreateSettings($vendor);
        $cats = $settings->categories ?? self::DEFAULT_CATEGORIES;

        if (in_array(strtolower($data['name']), array_map('strtolower', $cats))) {
            return response()->json(['message' => 'Category already exists'], 422);
        }

        $cats[] = $data['name'];
        $settings->update(['categories' => $cats]);

        return response()->json($cats, 201);
    }

    /**
     * DELETE /api/vendor/{vendorId}/inventory/categories/{name}
     */
    public function categoriesDestroy(Request $request, string $vendorId, string $name): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $settings = $this->getOrCreateSettings($vendor);
        $cats = array_values(array_filter(
            $settings->categories ?? self::DEFAULT_CATEGORIES,
            fn ($c) => strtolower($c) !== strtolower($name)
        ));
        $settings->update(['categories' => $cats]);

        return response()->json($cats);
    }

    // ----------------------------------------------------------------
    // Units
    // ----------------------------------------------------------------

    /**
     * GET /api/vendor/{vendorId}/inventory/units
     */
    public function unitsIndex(string $vendorId): JsonResponse
    {
        $vendor   = $this->resolveVendor($vendorId);
        $settings = $vendor->inventorySettings;
        $units    = $settings?->units ?? self::DEFAULT_UNITS;

        return response()->json($units);
    }

    /**
     * POST /api/vendor/{vendorId}/inventory/units
     */
    public function unitsStore(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate(['name' => ['required', 'string', 'max:50']]);

        $settings = $this->getOrCreateSettings($vendor);
        $units = $settings->units ?? self::DEFAULT_UNITS;

        if (in_array(strtolower($data['name']), array_map('strtolower', $units))) {
            return response()->json(['message' => 'Unit already exists'], 422);
        }

        $units[] = $data['name'];
        $settings->update(['units' => $units]);

        return response()->json($units, 201);
    }

    /**
     * DELETE /api/vendor/{vendorId}/inventory/units/{name}
     */
    public function unitsDestroy(Request $request, string $vendorId, string $name): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $settings = $this->getOrCreateSettings($vendor);
        $units = array_values(array_filter(
            $settings->units ?? self::DEFAULT_UNITS,
            fn ($u) => strtolower($u) !== strtolower($name)
        ));
        $settings->update(['units' => $units]);

        return response()->json($units);
    }

    private function getOrCreateSettings(Vendor $vendor): InventorySettings
    {
        return $vendor->inventorySettings ?? InventorySettings::create(['vendor_id' => $vendor->id]);
    }

    // ----------------------------------------------------------------

    private function formatItem(InventoryItem $item): array
    {
        return [
            'id' => (string) $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'quantity' => (float) $item->quantity,
            'unit' => $item->unit,
            'minStock' => (float) $item->min_stock,
            'reorderQuantity' => (float) $item->reorder_quantity,
            'costPerUnit' => (float) $item->cost_per_unit,
            'supplier' => $item->supplier,
            'isCritical' => $item->is_critical,
            'autoReorder' => $item->auto_reorder,
            'trackStock' => $item->track_stock,
            'nutrition' => $item->nutrition,
        ];
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
