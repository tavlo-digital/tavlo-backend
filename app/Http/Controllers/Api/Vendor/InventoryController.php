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
            'category' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'minStock' => ['nullable', 'numeric', 'min:0'],
            'costPerUnit' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'isCritical' => ['nullable', 'boolean'],
            'autoReorder' => ['nullable', 'boolean'],
            'nutrition' => ['nullable', 'array'],
        ]);

        $item = $vendor->inventoryItems()->create([
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'min_stock' => $data['minStock'] ?? 0,
            'cost_per_unit' => $data['costPerUnit'] ?? 0,
            'supplier' => $data['supplier'] ?? null,
            'is_critical' => $data['isCritical'] ?? false,
            'auto_reorder' => $data['autoReorder'] ?? false,
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
            'category' => ['nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'minStock' => ['nullable', 'numeric', 'min:0'],
            'costPerUnit' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'isCritical' => ['nullable', 'boolean'],
            'autoReorder' => ['nullable', 'boolean'],
            'nutrition' => ['nullable', 'array'],
        ]);

        $mapped = [];
        $keyMap = [
            'name' => 'name',
            'category' => 'category',
            'quantity' => 'quantity',
            'unit' => 'unit',
            'minStock' => 'min_stock',
            'costPerUnit' => 'cost_per_unit',
            'supplier' => 'supplier',
            'isCritical' => 'is_critical',
            'autoReorder' => 'auto_reorder',
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

        return response()->json([
            'lowStockAlerts' => $settings->low_stock_alerts,
            'autoReorderEnabled' => $settings->auto_reorder_enabled,
            'lowStockThreshold' => $settings->low_stock_threshold,
            'trackNutrition' => $settings->track_nutrition,
            'linkMenuItems' => $settings->link_menu_items,
            'settings' => $settings->settings ?? (object) [],
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
            'lowStockAlerts' => ['nullable', 'boolean'],
            'autoReorderEnabled' => ['nullable', 'boolean'],
            'lowStockThreshold' => ['nullable', 'integer', 'min:0'],
            'trackNutrition' => ['nullable', 'boolean'],
            'linkMenuItems' => ['nullable', 'boolean'],
            'settings' => ['nullable', 'array'],
        ]);

        $settings = $vendor->inventorySettings()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'low_stock_alerts' => $data['lowStockAlerts'] ?? true,
                'auto_reorder_enabled' => $data['autoReorderEnabled'] ?? false,
                'low_stock_threshold' => $data['lowStockThreshold'] ?? 10,
                'track_nutrition' => $data['trackNutrition'] ?? true,
                'link_menu_items' => $data['linkMenuItems'] ?? true,
                'settings' => $data['settings'] ?? null,
            ]
        );

        return response()->json([
            'lowStockAlerts' => $settings->low_stock_alerts,
            'autoReorderEnabled' => $settings->auto_reorder_enabled,
            'lowStockThreshold' => $settings->low_stock_threshold,
            'trackNutrition' => $settings->track_nutrition,
            'linkMenuItems' => $settings->link_menu_items,
            'settings' => $settings->settings ?? (object) [],
        ]);
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
            'costPerUnit' => (float) $item->cost_per_unit,
            'supplier' => $item->supplier,
            'isCritical' => $item->is_critical,
            'autoReorder' => $item->auto_reorder,
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
