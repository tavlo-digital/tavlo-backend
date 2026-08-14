<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\InventoryPurchaseOrderMail;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventorySettings;
use App\Models\InventoryStockMovement;
use App\Models\MenuItemIngredient;
use App\Models\Vendor;
use App\Services\LocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class InventoryController extends Controller
{
    public function __construct(private readonly LocaleService $locales) {}

    /**
     * GET /api/vendor/{vendorId}/inventory/items
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

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
        $supplier = $this->optionalTranslatedValue(
            $data['supplier'] ?? null,
            $translations,
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

        $this->recordMovement(
            $item,
            'initial',
            'Initial Stock',
            0,
            (float) $item->quantity,
            $this->actorName($request),
            'Inventory item created'
        );

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
     * POST /api/vendor/{vendorId}/inventory/items/bulk
     *
     * Accepts a JSON array of items (already parsed + mapped by the frontend).
     * Each item is upserted by name (case-insensitive): creates if new, updates if exists.
     * Returns a summary of created / updated / skipped counts plus per-row errors.
     */
    public function bulkImport(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.ingredientName' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:20'],
            'items.*.category' => ['nullable', 'string', 'max:255'],
            'items.*.currentStock' => ['nullable', 'numeric', 'min:0'],
            'items.*.reorderLevel' => ['nullable', 'numeric', 'min:0'],
            'items.*.reorderQuantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.supplier' => ['nullable', 'string', 'max:255'],
            'items.*.costPerUnit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $created = 0;
        $updated = 0;
        $errors = [];
        $actorName = $this->actorName($request);

        foreach ($data['items'] as $index => $row) {
            try {
                $result = DB::transaction(function () use ($vendor, $row, $actorName): string {
                    $name = trim($row['ingredientName']);
                    $existing = $vendor->inventoryItems()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                        ->lockForUpdate()
                        ->first();

                    $fields = [
                        'unit' => trim($row['unit']),
                    ];

                    if (array_key_exists('category', $row)) {
                        $category = trim((string) ($row['category'] ?? '')) === ''
                            ? null
                            : $this->resolveCategoryFromPayload($vendor, $row);
                        $fields['inventory_category_id'] = $category?->id;
                        $fields['category'] = $category?->name;
                    }

                    $optionalFields = [
                        'currentStock' => 'quantity',
                        'reorderLevel' => 'min_stock',
                        'reorderQuantity' => 'reorder_quantity',
                        'costPerUnit' => 'cost_per_unit',
                    ];
                    foreach ($optionalFields as $input => $column) {
                        if (array_key_exists($input, $row)) {
                            $fields[$column] = (float) ($row[$input] ?? 0);
                        }
                    }

                    if (array_key_exists('supplier', $row)) {
                        $supplier = trim((string) ($row['supplier'] ?? ''));
                        $fields['supplier'] = $supplier === '' ? null : $supplier;
                    }

                    $translation = ['name' => $name];
                    if (array_key_exists('supplier', $fields) && $fields['supplier'] !== null) {
                        $translation['supplier'] = $fields['supplier'];
                    }

                    if ($existing) {
                        $quantityBefore = (float) $existing->quantity;
                        $existing->update(array_merge(['name' => $name], $fields));
                        $this->locales->syncTranslations(
                            $existing,
                            'localizedTranslations',
                            ['en' => $translation],
                            ['name', 'supplier']
                        );
                        $this->recordMovement(
                            $existing,
                            'import',
                            'Excel Import',
                            $quantityBefore,
                            (float) $existing->quantity,
                            $actorName,
                            'Bulk import update'
                        );

                        return 'updated';
                    }

                    $item = $vendor->inventoryItems()->create(array_merge([
                        'name' => $name,
                        'inventory_category_id' => null,
                        'category' => null,
                        'quantity' => 0,
                        'min_stock' => 0,
                        'reorder_quantity' => 0,
                        'cost_per_unit' => 0,
                        'supplier' => null,
                    ], $fields));
                    $this->locales->syncTranslations(
                        $item,
                        'localizedTranslations',
                        ['en' => $translation],
                        ['name', 'supplier']
                    );
                    $this->recordMovement(
                        $item,
                        'import',
                        'Excel Import',
                        0,
                        (float) $item->quantity,
                        $actorName,
                        'Created by bulk import'
                    );

                    return 'created';
                });

                if ($result === 'created') {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = [
                    'row' => $index + 1,
                    'name' => $row['ingredientName'] ?? '',
                    'message' => 'Unable to import this row.',
                ];
            }
        }

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => count($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * GET /api/vendor/{vendorId}/inventory/items/{itemId}/details
     */
    public function details(Request $request, string $vendorId, int $itemId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $locale = $this->locales->dashboardLanguage($vendor);

        $item = $vendor->inventoryItems()->findOrFail($itemId);

        $affectedMenuItems = MenuItemIngredient::query()
            ->where('inventory_item_id', $item->id)
            ->whereHas('menuItem', fn ($query) => $query->where('vendor_id', $vendor->id))
            ->with('menuItem.itemTranslations')
            ->orderBy('menu_item_id')
            ->get()
            ->filter(fn (MenuItemIngredient $ingredient) => $ingredient->menuItem !== null)
            ->map(function (MenuItemIngredient $ingredient) use ($vendor, $locale) {
                $menuItem = $ingredient->menuItem;

                return [
                    'id' => (string) $menuItem->id,
                    'name' => $this->locales->translated(
                        $menuItem,
                        'itemTranslations',
                        'name',
                        $vendor,
                        $locale,
                        $menuItem->name
                    ),
                    'quantity' => (float) $ingredient->quantity,
                    'unit' => $ingredient->unit,
                    'isCritical' => (bool) $ingredient->is_critical,
                    'available' => (bool) $menuItem->available,
                ];
            })
            ->values();

        $activityLog = $item->stockMovements()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (InventoryStockMovement $movement) => $this->formatMovement($movement));

        return response()->json([
            'affectedMenuItems' => $affectedMenuItems,
            'activityLog' => $activityLog,
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/inventory/items/{itemId}/adjust-stock
     */
    public function adjustStock(Request $request, string $vendorId, int $itemId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'not_in:0'],
            'type' => ['required', 'string', 'in:delivery,waste,correction'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $amount = (float) $data['amount'];
        if ($data['type'] === 'delivery' && $amount < 0) {
            throw ValidationException::withMessages([
                'amount' => ['A delivery must increase stock.'],
            ]);
        }
        if ($data['type'] === 'waste' && $amount > 0) {
            throw ValidationException::withMessages([
                'amount' => ['Waste must decrease stock.'],
            ]);
        }

        [$item, $movement] = DB::transaction(function () use ($vendor, $itemId, $amount, $data, $request) {
            $item = $vendor->inventoryItems()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $quantityBefore = (float) $item->quantity;
            $quantityAfter = round($quantityBefore + $amount, 2);

            if ($quantityAfter < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['This adjustment would make stock negative.'],
                ]);
            }
            if ($quantityAfter === $quantityBefore) {
                throw ValidationException::withMessages([
                    'amount' => ['The adjustment is too small to change the stored quantity.'],
                ]);
            }

            $item->update(['quantity' => $quantityAfter]);

            $source = match ($data['type']) {
                'delivery' => 'Supplier Delivery',
                'waste' => 'Waste',
                default => 'Manual Correction',
            };

            $movement = $this->recordMovement(
                $item,
                $data['type'],
                $source,
                $quantityBefore,
                $quantityAfter,
                $this->actorName($request),
                $data['reason'] ?? null
            );

            return [$item, $movement];
        });

        $item->load(['localizedTranslations', 'inventoryCategory.localizedTranslations']);

        return response()->json([
            'item' => $this->formatItem(
                $item,
                $vendor,
                $this->locales->dashboardLanguage($vendor)
            ),
            'activity' => $this->formatMovement($movement),
        ]);
    }

    /**
     * PATCH /api/vendor/{vendorId}/inventory/items/{itemId}
     */
    public function update(Request $request, string $vendorId, int $itemId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $item = $vendor->inventoryItems()->findOrFail($itemId);
        $quantityBefore = (float) $item->quantity;

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

        if (array_key_exists('quantity', $mapped)) {
            $this->recordMovement(
                $item,
                'correction',
                'Manual Update',
                $quantityBefore,
                (float) $item->quantity,
                $this->actorName($request),
                'Stock changed while editing inventory item'
            );
        }

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
     * GET /api/vendor/{vendorId}/inventory/purchase-orders
     */
    public function purchaseOrdersIndex(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $purchaseOrders = $vendor->inventoryPurchaseOrders()
            ->with('inventoryItem:id,name')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (InventoryPurchaseOrder $purchaseOrder) => $this->formatPurchaseOrder($purchaseOrder));

        return response()->json($purchaseOrders);
    }

    /**
     * POST /api/vendor/{vendorId}/inventory/purchase-orders
     */
    public function storePurchaseOrder(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'supplierId' => ['required', 'string', 'max:255'],
            'inventoryItemId' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'estimatedDeliveryDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = $vendor->inventoryItems()->findOrFail((int) $data['inventoryItemId']);
        $storedSettings = $vendor->inventorySettings?->settings ?? [];
        $suppliers = is_array($storedSettings['suppliers'] ?? null) ? $storedSettings['suppliers'] : [];
        $supplier = collect($suppliers)->first(
            fn ($candidate) => is_array($candidate)
                && (string) ($candidate['id'] ?? '') === $data['supplierId']
        );

        if (! is_array($supplier) || ($supplier['status'] ?? 'inactive') !== 'active') {
            throw ValidationException::withMessages([
                'supplierId' => ['The selected supplier is not active or no longer exists.'],
            ]);
        }

        $supportedIngredients = collect($supplier['supportedIngredients'] ?? [])
            ->map(fn ($value) => mb_strtolower(trim((string) $value)));
        $ingredientConfigs = collect($supplier['ingredientConfigs'] ?? [])
            ->filter(fn ($config) => is_array($config));
        $supportsItem = $supportedIngredients->contains(mb_strtolower($item->name))
            || $supportedIngredients->contains((string) $item->id)
            || $ingredientConfigs->contains(fn ($config) => (string) ($config['ingredientId'] ?? '') === (string) $item->id)
            || $ingredientConfigs->contains(fn ($config) => mb_strtolower(trim((string) ($config['ingredientName'] ?? ''))) === mb_strtolower($item->name))
            || mb_strtolower(trim((string) ($item->supplier ?? ''))) === mb_strtolower(trim((string) ($supplier['name'] ?? '')));
        if (! $supportsItem) {
            throw ValidationException::withMessages([
                'supplierId' => ['The selected supplier is not linked to this inventory item.'],
            ]);
        }

        $minimumQuantity = (float) ($supplier['minimumOrderQty'] ?? 0);
        if ($minimumQuantity > 0 && (float) $data['quantity'] < $minimumQuantity) {
            throw ValidationException::withMessages([
                'quantity' => ["The supplier requires a minimum order quantity of {$minimumQuantity}."],
            ]);
        }

        $orderingMethod = (string) ($supplier['orderingMethod'] ?? 'Phone');
        if (! in_array($orderingMethod, ['Email', 'API', 'Phone'], true)) {
            throw ValidationException::withMessages([
                'supplierId' => ['The supplier has an unsupported ordering method.'],
            ]);
        }

        $user = $request->user();
        $purchaseOrder = $vendor->inventoryPurchaseOrders()->create([
            'inventory_item_id' => $item->id,
            'supplier_id' => (string) $supplier['id'],
            'supplier_name' => (string) $supplier['name'],
            'supplier_email' => $supplier['email'] ?? null,
            'supplier_phone' => $supplier['phone'] ?? null,
            'ordering_method' => $orderingMethod,
            'ordering_url' => $supplier['orderingUrl'] ?? null,
            'quantity' => (float) $data['quantity'],
            'unit' => $item->unit,
            'unit_cost' => (float) $item->cost_per_unit,
            'currency' => $vendor->currency,
            'estimated_delivery_date' => $data['estimatedDeliveryDate'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
            'created_by_type' => $user?->getTable(),
            'created_by_id' => $user?->id,
            'created_by_name' => $this->actorName($request),
        ]);
        $purchaseOrder->setRelation('inventoryItem', $item);
        $this->dispatchPurchaseOrder($purchaseOrder);

        return response()->json($this->formatPurchaseOrder($purchaseOrder->fresh('inventoryItem')), 201);
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
            'suppliers.*.orderingUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'suppliers.*.orderCutoffTime' => ['nullable', 'date_format:H:i'],
            'suppliers.*.minimumOrderQty' => ['nullable', 'numeric', 'min:0'],
            'suppliers.*.ingredientConfigs' => ['nullable', 'array'],
            'suppliers.*.ingredientConfigs.*.ingredientId' => ['required', 'string'],
            'suppliers.*.ingredientConfigs.*.ingredientName' => ['required', 'string', 'max:255'],
            'suppliers.*.ingredientConfigs.*.supplyUnit' => ['required', 'string', 'max:30'],
            'suppliers.*.ingredientConfigs.*.supplierSku' => ['nullable', 'string', 'max:100'],
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

    private function recordMovement(
        InventoryItem $item,
        string $type,
        string $source,
        float $quantityBefore,
        float $quantityAfter,
        string $actorName,
        ?string $note = null
    ): ?InventoryStockMovement {
        $quantityChange = round($quantityAfter - $quantityBefore, 3);
        if ($quantityChange === 0.0) {
            return null;
        }

        return $item->stockMovements()->create([
            'vendor_id' => $item->vendor_id,
            'type' => $type,
            'source' => $source,
            'quantity_change' => $quantityChange,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'actor_name' => $actorName,
            'note' => $note,
        ]);
    }

    private function formatMovement(InventoryStockMovement $movement): array
    {
        return [
            'id' => (string) $movement->id,
            'date' => $movement->created_at?->toISOString(),
            'type' => $movement->type,
            'source' => $movement->source,
            'amount' => (float) $movement->quantity_change,
            'quantityBefore' => (float) $movement->quantity_before,
            'quantityAfter' => (float) $movement->quantity_after,
            'note' => $movement->note,
            'user' => $movement->actor_name ?? 'System',
        ];
    }

    private function dispatchPurchaseOrder(InventoryPurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->ordering_method === 'Phone') {
            $purchaseOrder->update(['status' => 'manual_action_required']);

            return;
        }

        try {
            if ($purchaseOrder->ordering_method === 'Email') {
                if (! $purchaseOrder->supplier_email) {
                    throw new \RuntimeException('The supplier has no ordering email.');
                }

                Mail::to($purchaseOrder->supplier_email)->send(
                    new InventoryPurchaseOrderMail($purchaseOrder)
                );
            } else {
                if (! $purchaseOrder->ordering_url) {
                    throw new \RuntimeException('The supplier has no ordering API URL.');
                }

                Http::timeout(10)
                    ->acceptJson()
                    ->post($purchaseOrder->ordering_url, [
                        'purchaseOrderId' => $purchaseOrder->purchase_order_public_id,
                        'vendor' => $purchaseOrder->vendor?->restaurant_name ?: $purchaseOrder->vendor?->name,
                        'ingredient' => $purchaseOrder->inventoryItem?->name,
                        'quantity' => (float) $purchaseOrder->quantity,
                        'unit' => $purchaseOrder->unit,
                        'unitCost' => (float) $purchaseOrder->unit_cost,
                        'totalCost' => round((float) $purchaseOrder->quantity * (float) $purchaseOrder->unit_cost, 2),
                        'currency' => $purchaseOrder->currency,
                        'estimatedDeliveryDate' => $purchaseOrder->estimated_delivery_date?->toDateString(),
                        'notes' => $purchaseOrder->notes,
                    ])
                    ->throw();
            }

            $purchaseOrder->update([
                'status' => 'sent',
                'dispatched_at' => now(),
                'dispatch_error' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $purchaseOrder->update([
                'status' => 'failed',
                'dispatch_error' => $exception->getMessage(),
            ]);
        }
    }

    private function formatPurchaseOrder(InventoryPurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => (string) $purchaseOrder->id,
            'purchaseOrderPublicId' => $purchaseOrder->purchase_order_public_id,
            'inventoryItemId' => $purchaseOrder->inventory_item_id ? (string) $purchaseOrder->inventory_item_id : null,
            'ingredientName' => $purchaseOrder->inventoryItem?->name,
            'supplierId' => $purchaseOrder->supplier_id,
            'supplierName' => $purchaseOrder->supplier_name,
            'orderingMethod' => $purchaseOrder->ordering_method,
            'quantity' => (float) $purchaseOrder->quantity,
            'unit' => $purchaseOrder->unit,
            'unitCost' => (float) $purchaseOrder->unit_cost,
            'totalCost' => round((float) $purchaseOrder->quantity * (float) $purchaseOrder->unit_cost, 2),
            'currency' => $purchaseOrder->currency,
            'estimatedDeliveryDate' => $purchaseOrder->estimated_delivery_date?->toDateString(),
            'notes' => $purchaseOrder->notes,
            'status' => $purchaseOrder->status,
            'dispatchError' => $purchaseOrder->dispatch_error,
            'createdBy' => $purchaseOrder->created_by_name,
            'createdAt' => $purchaseOrder->created_at?->toISOString(),
        ];
    }

    private function actorName(Request $request): string
    {
        $user = $request->user();
        if (! $user) {
            return 'System';
        }

        return $user->getTable() === 'team_members'
            ? ($user->name ?: 'Staff')
            : 'Manager';
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

    private function optionalTranslatedValue(mixed $value, array $translations, string $field): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        $value = $value ?: collect($translations)
            ->pluck($field)
            ->first(fn ($translation) => is_string($translation) && trim($translation) !== '');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function baseTranslatedValue(
        Vendor $vendor,
        mixed $value,
        array $translations,
        string $field,
        string $label
    ): string {
        $value = is_string($value) ? trim($value) : '';
        $value = $value
            ?: ($translations['en'][$field] ?? '')
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
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
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
