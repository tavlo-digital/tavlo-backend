<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\LocaleService;
use App\Services\MenuCustomizationService;
use App\Services\NotificationService;
use App\Services\PaymentGuardService;
use App\Services\ShareOrderService;
use App\Services\TaxCalculationService;
use App\Services\VendorDateTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        private readonly VendorDateTimeService $dateTimes,
        private readonly LocaleService $locales,
        private readonly MenuCustomizationService $customizations,
    ) {}

    /**
     * Resolve the authenticated customer's active session.
     * Returns null if none exists.
     */
    private function activeSession(Request $request): ?TableScanSession
    {
        return TableScanSession::where('customer_id', $request->user()->id)
            ->where('status', 'active')
            ->latest('scanned_at')
            ->first();
    }

    /**
     * Ids of all active sessions at the same table as $session.
     *
     * @return array<int, int>
     */
    private function tableSessionIds(TableScanSession $session): array
    {
        return TableScanSession::where('restaurant_table_id', $session->restaurant_table_id)
            ->where('vendor_id', $session->vendor_id)
            ->where('status', 'active')
            ->pluck('id')
            ->all();
    }

    private function customerName($customer): string
    {
        return trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest';
    }

    /**
     * Sharing or unsharing changes the amounts of every order the cart item
     * touches. While any of them is covered by an active payment (an open
     * checkout), the item is locked.
     */
    private function itemLockedByActivePayment(Order $targetOrder, CartItem $cartItem): bool
    {
        $relatedOrderIds = collect([$targetOrder->id, $cartItem->order_id])
            ->merge(is_array($cartItem->shared_order_ids) ? $cartItem->shared_order_ids : [])
            ->merge(Order::where('parent_order_id', $targetOrder->id)->pluck('id'))
            ->filter()
            ->unique();

        return PaymentGuardService::activePaymentsCovering($relatedOrderIds)->isNotEmpty();
    }

    /**
     * GET /api/customer/cart
     *
     * Returns the full table cart split into per-person personal items.
     */
    public function index(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);

        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $payload = $this->buildCartPayload($mySession, $request);
        $payload['people'] = collect($payload['people'])
            ->map(fn (array $person) => [...$person, 'is_me' => $person['session_id'] === $mySession->id])
            ->values()
            ->all();

        return response()->json($payload);
    }

    /**
     * Build the table cart payload shared by GET /cart and realtime cart
     * pushes. People entries carry no is_me flag — each consumer derives it
     * from its own session or customer id.
     */
    private function buildCartPayload(TableScanSession $mySession, Request $request): array
    {
        $sessionIds = $this->tableSessionIds($mySession);

        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'cartItems.menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations',
            'restaurantTable:id,number,name',
            'vendor:id,vendor_public_id,restaurant_name,country',
            'vendor.vendorSetting:id,vendor_id,service_fee_rate,supported_languages',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        $orderedStatuses = array_merge(Order::ACTIVE_STATUSES, Order::COMPLETED_STATUSES);

        $orderedOrderIds = Order::whereIn('table_scan_session_id', $sessionIds)
            ->whereIn('status', $orderedStatuses)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $mySession = $sessions->firstWhere('id', $mySession->id) ?? $mySession;
        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $vendor = $mySession->vendor;
        $locale = $vendor
            ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor)
            : 'en';

        $translateTaxGroups = fn (array $groups) => array_map(fn (array $g) => array_merge($g, [
            'label' => $this->locales->translatedTaxCategoryName($g['tax_category'], $vendorCountry, $locale),
        ]), $groups);

        $people = $sessions->map(function (TableScanSession $s) use ($orderedOrderIds, $vendorCountry, $serviceFeeRate, $vendor, $locale, $translateTaxGroups) {
            $personItems = $s->cartItems
                ->filter(fn (CartItem $item) => $item->order_id === null
                    && ! $this->cartItemBelongsToOrderedOrder($item, $orderedOrderIds))
                ->values();

            $personTaxGroups = TaxCalculationService::computeTaxGroups($personItems, $vendorCountry, true);
            $personTotals = TaxCalculationService::computeTotals($personTaxGroups, $serviceFeeRate);

            return [
                'session_id' => $s->id,
                'customer_id' => $s->customer_id,
                'name' => $s->customer
                    ? trim($s->customer->first_name.' '.$s->customer->last_name)
                    : 'Waiter',
                'personal_items' => $personItems
                    ->map(fn (CartItem $item) => $this->itemPayload($item, $vendorCountry, $vendor, $locale))
                    ->all(),
                'tax_groups' => $translateTaxGroups($personTaxGroups),
                'totals' => $personTotals,
            ];
        });

        $table = $mySession->restaurantTable;

        return [
            'table' => $table ? [
                'id' => $table->id,
                'number' => $table->number ?? null,
                'name' => $table->name ?? null,
            ] : null,
            'vendor' => $vendor ? [
                'vendor_public_id' => $vendor->vendor_public_id ?? null,
                'restaurant_name' => $vendor->restaurant_name ?? null,
            ] : null,
            'people' => $people->values()->all(),
        ];
    }

    /**
     * Realtime metadata containing the fresh cart payload so customer clients
     * can apply it directly without another network request.
     */
    private function realtimeCartMetadata(TableScanSession $mySession, Request $request): array
    {
        $cart = $this->buildCartPayload($mySession, $request);

        return [
            'cart' => $cart,
            'payload_version' => now()->getTimestampMs(),
        ];
    }

    private function cartItemBelongsToOrderedOrder(CartItem $item, array $orderedOrderIds): bool
    {
        if (empty($orderedOrderIds)) {
            return false;
        }

        $itemOrderIds = array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []);

        return ! empty(array_intersect($itemOrderIds, $orderedOrderIds));
    }

    /**
     * POST /api/customer/cart/items
     *
     * Add an item to the authenticated customer's cart.
     */
    public function addItem(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_addons' => ['sometimes', 'array'],
            'paid_addons.*.id' => ['sometimes', 'integer', 'min:1'],
            'paid_addons.*.name' => ['sometimes', 'string', 'max:255'],
            'paid_addons.*.price' => ['sometimes', 'numeric', 'min:0'],
            'free_addons' => ['sometimes', 'array'],
            'removed_items' => ['sometimes', 'array'],
            'removable_items' => ['sometimes', 'array'],
            'selected_modifiers' => ['sometimes', 'array'],
            'selected_modifiers.*.modifier_group_id' => ['sometimes', 'integer'],
            'selected_modifiers.*.group_id' => ['sometimes', 'integer'],
            'selected_modifiers.*.id' => ['sometimes', 'integer'],
            'selected_modifiers.*.option_ids' => ['sometimes', 'array'],
            'selected_modifiers.*.option_ids.*' => ['integer'],
            'selected_modifiers.*.options' => ['sometimes', 'array'],
            'modifiers' => ['sometimes', 'array'],
            'modifiers.*.modifier_group_id' => ['sometimes', 'integer'],
            'modifiers.*.group_id' => ['sometimes', 'integer'],
            'modifiers.*.id' => ['sometimes', 'integer'],
            'modifiers.*.option_ids' => ['sometimes', 'array'],
            'modifiers.*.option_ids.*' => ['integer'],
            'modifiers.*.options' => ['sometimes', 'array'],
        ])->validate();

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $menuItem = MenuItem::where('id', $data['menu_item_id'])
            ->where('vendor_id', $mySession->vendor_id)
            ->where('is_active', true)
            ->where('available', true)
            ->with(['modifierGroups' => fn ($q) => $q->where('is_active', true)
                ->orderByPivot('sort_order')
                ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')])])
            ->first();

        if (! $menuItem) {
            throw ValidationException::withMessages([
                'menu_item_id' => ['The selected menu item is not available for this table.'],
            ]);
        }

        $customizations = $this->normalizeCustomizations($menuItem, $data);

        $existing = CartItem::where('table_scan_session_id', $mySession->id)
            ->where('menu_item_id', $data['menu_item_id'])
            ->whereNull('order_id')
            ->get()
            ->first(fn (CartItem $cartItem) => $this->cartCustomizationsMatch($cartItem, $customizations));

        if ($existing) {
            $existing->update([
                'quantity' => $existing->quantity + ($data['quantity'] ?? 1),
            ]);
            $item = $existing;
        } else {
            $item = CartItem::create([
                'table_scan_session_id' => $mySession->id,
                'menu_item_id' => $data['menu_item_id'],
                'order_id' => null,
                'quantity' => $data['quantity'] ?? 1,
                'notes' => $data['notes'] ?? null,
                'paid_addons' => $customizations['paid_addons'],
                'free_addons' => $customizations['free_addons'],
                'removed_items' => $customizations['removed_items'],
                'selected_modifiers' => $customizations['selected_modifiers'],
            ]);
        }

        $item->load('menuItem:id,vendor_id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations');

        $customerName = $this->customerName($request->user());
        $vendor = $mySession->vendor;
        $locale = $vendor ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor) : 'en';
        $itemName = $item->menuItem && $vendor
            ? $this->customizations->menuItemName($item->menuItem, $vendor, $locale)
            : ($item->menuItem?->name ?? 'an item');
        NotificationService::notifyTableCustomers(
            $mySession->restaurant_table_id,
            'cart_updated',
            "{$customerName} added {$itemName} to the cart.",
            [
                'template' => 'cart.item_added',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'menu_item_id' => $item->menu_item_id,
                'item_name' => $itemName,
                ...$this->realtimeCartMetadata($mySession, $request),
            ],
        );

        return response()->json($this->itemPayload($item, $mySession->vendor?->country, $vendor, $locale), 201);
    }

    /**
     * PATCH /api/customer/cart/items/{id}
     *
     * Update quantity or notes of a cart item owned by the current session.
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_addons' => ['sometimes', 'array'],
            'paid_addons.*.id' => ['sometimes', 'integer', 'min:1'],
            'paid_addons.*.name' => ['sometimes', 'string', 'max:255'],
            'paid_addons.*.price' => ['sometimes', 'numeric', 'min:0'],
            'free_addons' => ['sometimes', 'array'],
            'removed_items' => ['sometimes', 'array'],
            'removable_items' => ['sometimes', 'array'],
            'selected_modifiers' => ['sometimes', 'array'],
            'selected_modifiers.*.modifier_group_id' => ['sometimes', 'integer'],
            'selected_modifiers.*.group_id' => ['sometimes', 'integer'],
            'selected_modifiers.*.id' => ['sometimes', 'integer'],
            'selected_modifiers.*.option_ids' => ['sometimes', 'array'],
            'selected_modifiers.*.option_ids.*' => ['integer'],
            'selected_modifiers.*.options' => ['sometimes', 'array'],
            'modifiers' => ['sometimes', 'array'],
            'modifiers.*.modifier_group_id' => ['sometimes', 'integer'],
            'modifiers.*.group_id' => ['sometimes', 'integer'],
            'modifiers.*.id' => ['sometimes', 'integer'],
            'modifiers.*.option_ids' => ['sometimes', 'array'],
            'modifiers.*.option_ids.*' => ['integer'],
            'modifiers.*.options' => ['sometimes', 'array'],
        ])->validate();

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $item = CartItem::where('id', $id)
            ->where('table_scan_session_id', $mySession->id)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        if ($item->received_at) {
            return response()->json([
                'message' => 'This item has already been submitted and cannot be modified. Please add a new item instead.',
            ], 409);
        }

        $updates = array_filter(
            array_intersect_key($data, array_flip(['quantity', 'notes'])),
            fn ($v) => $v !== null
        );
        if (
            array_key_exists('paid_addons', $data)
            || array_key_exists('free_addons', $data)
            || array_key_exists('removed_items', $data)
            || array_key_exists('removable_items', $data)
            || array_key_exists('selected_modifiers', $data)
            || array_key_exists('modifiers', $data)
        ) {
            $item->load(['menuItem' => fn ($q) => $q->select('id', 'paid_addons', 'free_addons', 'removable_items')
                ->with(['modifierGroups' => fn ($g) => $g->where('is_active', true)
                    ->orderByPivot('sort_order')
                    ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')])])]);
            $updates = array_merge($updates, $this->normalizeCustomizations($item->menuItem, $data, $item));
        }

        $item->update($updates);
        $item->load('menuItem:id,vendor_id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations');

        $customerName = $this->customerName($request->user());
        $vendor = $mySession->vendor;
        $locale = $vendor ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor) : 'en';
        $itemName = $item->menuItem && $vendor
            ? $this->customizations->menuItemName($item->menuItem, $vendor, $locale)
            : ($item->menuItem?->name ?? 'an item');
        NotificationService::notifyTableCustomers(
            $mySession->restaurant_table_id,
            'cart_updated',
            "{$customerName} updated {$itemName} in the cart.",
            [
                'template' => 'cart.item_updated',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'menu_item_id' => $item->menu_item_id,
                'item_name' => $itemName,
                ...$this->realtimeCartMetadata($mySession, $request),
            ],
        );

        return response()->json($this->itemPayload($item, $mySession->vendor?->country, $vendor, $locale));
    }

    /**
     * DELETE /api/customer/cart/items/{id}
     *
     * Remove a cart item owned by the current session.
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $item = CartItem::where('id', $id)
            ->where('table_scan_session_id', $mySession->id)
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        if ($item->received_at) {
            return response()->json([
                'message' => 'This item has already been submitted and cannot be removed.',
            ], 409);
        }

        $itemName = $item->menuItem?->name ?? 'an item';
        $menuItemId = $item->menu_item_id;
        $item->delete();

        $customerName = $this->customerName($request->user());
        NotificationService::notifyTableCustomers(
            $mySession->restaurant_table_id,
            'cart_updated',
            "{$customerName} removed {$itemName} from the cart.",
            [
                'template' => 'cart.item_removed',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'menu_item_id' => $menuItemId,
                'item_name' => $itemName,
                ...$this->realtimeCartMetadata($mySession, $request),
            ],
        );

        return response()->json(null, 204);
    }

    /**
     * GET /api/customer/table/order/start
     *
     * Returns a payment summary for the customer's current table:
     * - the authenticated customer's own line (name, item count, total)
     * - every active session at the same table (name, items, total)
     * - a flat list of every item on the table with an `is_mine` flag
     * - the table-wide grand total
     */
    public function orderStart(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $sessionIds = $this->tableSessionIds($mySession);

        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'restaurantTable:id,number,name',
            'cartItems.menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations',
            'vendor:id,vendor_public_id,restaurant_name,country',
            'vendor.vendorSetting:id,vendor_id,service_fee_rate,supported_languages',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        $mySession = $sessions->firstWhere('id', $mySession->id) ?? $mySession;
        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $vendor = $mySession->vendor;
        $locale = $vendor
            ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor)
            : 'en';
        $tableTotal = 0.0;
        $tableItemCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $vendorCountry, $serviceFeeRate, $vendor, $locale, &$tableTotal, &$tableItemCount) {
            $personalTotal = 0.0;
            $personalCount = 0;
            $isMe = $s->id === $mySession->id;
            $itemTaxCategoryFn = fn (CartItem $item) => $item->menuItem?->tax_category ?? 'food';

            $personCartItems = $s->cartItems->filter(fn (CartItem $item) => $item->order_id === null);

            $items = $personCartItems
                ->map(function (CartItem $item) use ($isMe, $vendorCountry, $itemTaxCategoryFn, &$personalTotal, &$personalCount, &$tableTotal, &$tableItemCount) {
                    $unitPrice = $this->cartItemUnitPrice($item, $vendorCountry);
                    $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
                    $itc = $itemTaxCategoryFn($item);

                    $personalTotal += $lineTotal;
                    $personalCount += $item->quantity;
                    $tableTotal += $lineTotal;
                    $tableItemCount += $item->quantity;

                    return [
                        'cart_item_id' => $item->id,
                        'menu_item_id' => $item->menu_item_id,
                        'name' => $item->menuItem && $vendor
                            ? $this->customizations->menuItemName($item->menuItem, $vendor, $locale)
                            : $item->menuItem?->name,
                        'image_url' => $item->menuItem?->image_url,
                        'quantity' => $item->quantity,
                        'unit_price' => $unitPrice,
                        'paid_addons' => $this->formatCartPaidAddons($item, $itc, $vendorCountry, $vendor, $locale),
                        'free_addons' => $this->formatCartNamedSelections($item, 'free_addons', $vendor, $locale),
                        'removed_items' => $this->formatCartNamedSelections($item, 'removed_items', $vendor, $locale),
                        'selected_modifiers' => $this->formatCartSelectedModifiers($item, $itc, $vendorCountry, $vendor, $locale),
                        'total_price' => $lineTotal,
                        'is_mine' => $isMe,
                    ];
                })->values();

            $personTaxGroups = TaxCalculationService::computeTaxGroups($personCartItems, $vendorCountry, true);
            $personTotals = TaxCalculationService::computeTotals($personTaxGroups, $serviceFeeRate);

            return [
                'session_id' => $s->id,
                'customer_id' => $s->customer_id,
                'is_me' => $isMe,
                'name' => $s->customer
                    ? trim($s->customer->first_name.' '.$s->customer->last_name)
                    : 'Waiter',
                'item_count' => $personalCount,
                'total_price' => round($personalTotal, 2),
                'items' => $items,
                'tax_groups' => array_map(fn (array $g) => array_merge($g, [
                    'label' => $this->locales->translatedTaxCategoryName($g['tax_category'], $vendorCountry, $locale),
                ]), $personTaxGroups),
                'totals' => $personTotals,
            ];
        })->values();

        $table = $mySession->restaurantTable;

        return response()->json([
            'table' => $table ? [
                'id' => $table->id,
                'number' => $table->number ?? null,
                'name' => $table->name ?? null,
            ] : null,
            'people' => $people,
            'summary' => [
                'item_count' => $tableItemCount,
                'total_price' => round($tableTotal, 2),
            ],
        ]);
    }

    /**
     * POST /api/customer/table/order/draft
     *
     * Create a draft order from the customer's own cart items. No body required.
     * Amount is computed live from owned cart_items at draft time. The order's
     * final amount is recalculated on confirm to include any shared-into items.
     */
    public function createOrderDraft(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $existingSubmittedOrder = $this->currentUnpaidSubmittedOrder($request->user()->id, $mySession->id);

        if ($existingSubmittedOrder) {
            return response()->json($this->buildTableHistoryResponse($mySession, $request));
        }

        $existingOrder = $this->currentOpenOrder($request->user()->id, $mySession->id);

        $myCartItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where('table_scan_session_id', $mySession->id)
            ->whereNull('order_id')
            ->get();

        $vendorCountry = $this->vendorCountry($mySession);
        $myTotal = 0.0;
        foreach ($myCartItems as $item) {
            $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $myTotal += $lineTotal / $shareCount;
        }
        $myTotal = round($myTotal, 2);

        $customerName = $this->customerName($request->user());

        if ($existingOrder) {
            $existingOrder->update(['amount' => $myTotal]);

            $history = $this->buildTableHistoryResponse($mySession, $request);

            $personSnapshot = collect($history['people'])
                ->first(fn (array $p) => $p['session_id'] === $mySession->id);

            NotificationService::notifyTableCustomers(
                $mySession->restaurant_table_id,
                'order_updated',
                "{$customerName} updated their order draft.",
                [
                    'template' => 'order.draft_updated',
                    'customer_id' => $request->user()->id,
                    'customer_name' => $customerName,
                    'order_id' => $existingOrder->id,
                    'order_snapshots' => [NotificationService::orderSnapshot($existingOrder->fresh()->load('paidBy'))],
                    'person_snapshot' => $personSnapshot,
                ],
            );

            return response()->json($history);
        }

        DB::transaction(function () use ($request, $mySession, $myTotal) {
            $currency = $mySession->vendor?->currency ?? 'EUR';

            Order::create([
                'order_public_id' => 'ord-'.Str::random(12),
                'customer_id' => $request->user()->id,
                'vendor_id' => $mySession->vendor_id,
                'table_scan_session_id' => $mySession->id,
                'status' => 'draft',
                'draft_at' => now(),
                'amount' => $myTotal,
                'currency' => $currency,
                'payment_pending' => false,
                'payment_received' => false,
                'order_type' => 'dine-in',
            ]);
        });

        $freshDraft = Order::where('table_scan_session_id', $mySession->id)
            ->where('customer_id', $request->user()->id)
            ->where('status', 'draft')
            ->latest()
            ->first();

        $history = $this->buildTableHistoryResponse($mySession, $request);

        $personSnapshot = collect($history['people'])
            ->first(fn (array $p) => $p['session_id'] === $mySession->id);

        NotificationService::notifyTableCustomers(
            $mySession->restaurant_table_id,
            'order_updated',
            "{$customerName} created an order draft.",
            [
                'template' => 'order.draft_created',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'order_snapshots' => $freshDraft
                    ? [NotificationService::orderSnapshot($freshDraft)]
                    : [],
                'person_snapshot' => $personSnapshot,
            ],
        );

        return response()->json($history, 201);
    }

    /**
     * PUT /api/customer/table/order/update/{order_id}
     *
     * Share or unshare a cart_item for the caller's order.
     * - shared_item:   append caller's order_id to that cart_item's shared_order_ids
     * - unshared_item: remove  caller's order_id from that cart_item's shared_order_ids (no-op if not present)
     * At least one field must be provided.
     */
    public function updateOrder(Request $request, int $order_id): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'shared_item' => ['nullable', 'integer'],
            'unshared_item' => ['nullable', 'integer'],
        ])->validate();

        if (empty($data['shared_item']) && empty($data['unshared_item'])) {
            return response()->json(['message' => 'Provide shared_item or unshared_item.'], 422);
        }

        $customerId = $request->user()->id;

        $order = Order::where('id', $order_id)
            ->where('customer_id', $customerId)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $sessionIds = $this->tableSessionIds($mySession);

        $affectedOrderIds = collect([$order->id]);

        if (! empty($data['shared_item'])) {
            $cartItem = CartItem::where('id', $data['shared_item'])
                ->whereIn('table_scan_session_id', $sessionIds)
                ->first();

            if (! $cartItem) {
                return response()->json([
                    'message' => 'Shared cart item does not belong to this table.',
                ], 422);
            }

            if ($this->itemLockedByActivePayment($order, $cartItem)) {
                return response()->json([
                    'message' => 'These items are locked while a payment is in progress.',
                ], 409);
            }

            if ((int) $cartItem->table_scan_session_id === (int) $mySession->id) {
                return response()->json([
                    'message' => 'You cannot share your own cart item with yourself.',
                ], 422);
            }

            if ($cartItem->order_id) {
                $ownerOrder = Order::where('id', $cartItem->order_id)->first();

                if ($ownerOrder && (int) $ownerOrder->paid_by === (int) $customerId) {
                    return response()->json([
                        'message' => 'You are already paying for this item.',
                    ], 422);
                }
            }

            // When my order is covered by another payer, my opt-in share must
            // stay payable by me: it attaches to my side order instead of the
            // covered order, so the payer keeps covering only my own items.
            $isCoveredByOther = $order->paid_by !== null && (int) $order->paid_by !== (int) $customerId;
            $existingSideOrderId = $isCoveredByOther
                ? Order::where('parent_order_id', $order->id)->where('payment_received', false)->value('id')
                : null;
            $myShareOrderIds = array_values(array_filter([$order->id, $existingSideOrderId]));

            $existingSharedOrderIds = array_map('intval', is_array($cartItem->shared_order_ids) ? $cartItem->shared_order_ids : []);
            if (array_intersect($myShareOrderIds, $existingSharedOrderIds) !== []) {
                return response()->json([
                    'message' => 'This item is already shared with your order.',
                ], 422);
            }

            $targetOrderId = DB::transaction(function () use ($cartItem, $order, $isCoveredByOther, $myShareOrderIds) {
                $target = $isCoveredByOther ? ShareOrderService::sideOrderFor($order) : $order;
                $locked = CartItem::where('id', $cartItem->id)->lockForUpdate()->first();
                $existing = array_map('intval', is_array($locked->shared_order_ids) ? $locked->shared_order_ids : []);
                if (array_intersect([...$myShareOrderIds, $target->id], $existing) !== []) {
                    throw ValidationException::withMessages([
                        'shared_item' => ['This item is already shared with your order.'],
                    ]);
                }
                $updated = array_values(array_unique(array_merge($existing, [$target->id])));
                $locked->update(['shared_order_ids' => $updated]);

                return $target->id;
            });
            $affectedOrderIds->push($targetOrderId);

            $cartItem->refresh();
            $freshIds = array_map('intval', is_array($cartItem->shared_order_ids) ? $cartItem->shared_order_ids : []);

            if ($cartItem->order_id) {
                $affectedOrderIds->push($cartItem->order_id);
            }
            $affectedOrderIds = $affectedOrderIds->merge($freshIds);
        }

        if (! empty($data['unshared_item'])) {
            $cartItem = CartItem::where('id', $data['unshared_item'])
                ->whereIn('table_scan_session_id', $sessionIds)
                ->first();

            if (! $cartItem) {
                return response()->json([
                    'message' => 'Unshared cart item does not belong to this table.',
                ], 422);
            }

            if ($this->itemLockedByActivePayment($order, $cartItem)) {
                return response()->json([
                    'message' => 'These items are locked while a payment is in progress.',
                ], 409);
            }

            if ($cartItem->order_id) {
                $ownerOrder = Order::where('id', $cartItem->order_id)->first();

                if ($ownerOrder && $ownerOrder->payment_received) {
                    return response()->json([
                        'message' => 'Cannot unshare an item whose owner has already paid.',
                    ], 422);
                }
            }

            DB::transaction(function () use ($cartItem, $order, &$affectedOrderIds) {
                $locked = CartItem::where('id', $cartItem->id)->lockForUpdate()->first();
                $existing = is_array($locked->shared_order_ids) ? $locked->shared_order_ids : [];
                $affectedOrderIds = $affectedOrderIds->merge(array_map('intval', $existing));
                if ($locked->order_id) {
                    $affectedOrderIds->push($locked->order_id);
                }

                $filtered = array_values(array_filter(
                    array_map('intval', $existing),
                    fn (int $id) => $id !== $order->id
                ));
                $locked->update(['shared_order_ids' => $filtered]);
            });
        }

        if (! $mySession->relationLoaded('vendor')) {
            $mySession->load('vendor.vendorSetting');
        }
        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $ordersToRecalc = Order::whereIn('id', $affectedOrderIds->unique()->values()->all())
            ->where('payment_received', false)
            ->whereNotNull('table_scan_session_id')
            ->get();

        foreach ($ordersToRecalc as $affectedOrder) {
            $itemsTotal = $this->computeOrderAmount(
                $affectedOrder,
                $affectedOrder->table_scan_session_id,
                vendorCountry: $vendorCountry
            );
            $serviceFee = round($itemsTotal * ($serviceFeeRate / 100), 2);
            $affectedOrder->update([
                'amount' => round($itemsTotal + $serviceFee, 2),
                'service_fee' => $serviceFee,
            ]);
        }

        // A side order that lost its last share reference has no reason to
        // exist anymore.
        foreach ($ordersToRecalc as $affectedOrder) {
            if ($affectedOrder->parent_order_id) {
                ShareOrderService::deleteIfEmpty($affectedOrder);
            }
        }

        $customerName = $this->customerName($request->user());
        $snapshots = Order::with('paidBy:id,first_name,last_name')
            ->whereIn('id', $affectedOrderIds->unique()->values()->all())
            ->get()
            ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
            ->values()->all();
        $history = $this->buildTableHistoryResponse($mySession, $request);
        $affectedSessionIds = $ordersToRecalc
            ->pluck('table_scan_session_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $personSnapshots = collect($history['people'])
            ->filter(fn (array $person) => in_array((int) $person['session_id'], $affectedSessionIds, true))
            ->values()
            ->all();
        NotificationService::notifyTableCustomers(
            $mySession->restaurant_table_id,
            'order_updated',
            "{$customerName} updated item sharing on the order.",
            [
                'template' => 'order.sharing_updated',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'order_id' => $order->id,
                'order_snapshots' => $snapshots,
                'person_snapshots' => $personSnapshots,
            ],
        );

        return response()->json($history);
    }

    /**
     * POST /api/customer/table/order/confirmed
     *
     * Confirm the customer's latest draft order. Recomputes the final amount
     * from cart_items: owned items split by (1 + count(shared_order_ids)), plus a
     * share of every cart_item whose shared_order_ids contains this order's id.
     */
    public function createOrderConfirmed(Request $request): JsonResponse
    {
        $customerId = $request->user()->id;

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $draftOrder = $this->currentDraftOrder($customerId, $mySession->id);
        $submittedOrder = $this->currentUnpaidSubmittedOrder($customerId, $mySession->id);
        $order = $submittedOrder ?? $draftOrder;

        if (! $mySession->relationLoaded('vendor')) {
            $mySession->load('vendor.vendorSetting');
        }

        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $vendorCountry = $this->vendorCountry($mySession);

        $openItems = CartItem::where('table_scan_session_id', $mySession->id)
            ->whereNull('order_id')
            ->with('menuItem:id,name,is_active,available')
            ->get();

        if ($openItems->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty. Add items before confirming your order.',
            ], 422);
        }

        if (! $order) {
            $myTotal = 0.0;
            foreach ($openItems as $item) {
                $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
                $shareCount = 1 + count($item->shared_order_ids ?? []);
                $myTotal += $lineTotal / $shareCount;
            }
            $myTotal = round($myTotal, 2);
            $currency = $mySession->vendor?->currency ?? 'EUR';

            $order = Order::create([
                'order_public_id' => 'ord-'.Str::random(12),
                'customer_id' => $customerId,
                'vendor_id' => $mySession->vendor_id,
                'table_scan_session_id' => $mySession->id,
                'status' => 'draft',
                'draft_at' => now(),
                'amount' => $myTotal,
                'currency' => $currency,
                'payment_pending' => false,
                'payment_received' => false,
                'order_type' => 'dine-in',
            ]);
            $draftOrder = $order;
        }

        $unavailableItems = $openItems->filter(
            fn (CartItem $item) => ! $item->menuItem
                || $item->menuItem->trashed()
                || ! $item->menuItem->is_active
                || ! $item->menuItem->available
        );

        if ($unavailableItems->isNotEmpty()) {
            return response()->json([
                'message'           => 'Some items in your cart are no longer available. Please remove them before confirming.',
                'unavailable_items' => $unavailableItems->map(fn (CartItem $item) => [
                    'cart_item_id' => $item->id,
                    'menu_item_id' => $item->menu_item_id,
                    'name'         => $item->menuItem?->name ?? 'Unknown item',
                ])->values(),
            ], 422);
        }

        $order = DB::transaction(function () use ($order, $draftOrder, $mySession, $vendorCountry, $serviceFeeRate, $openItems) {
            $targetOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $draftToMerge = $draftOrder && (int) $draftOrder->id !== (int) $targetOrder->id
                ? Order::whereKey($draftOrder->id)->lockForUpdate()->first()
                : null;

            CartItem::whereIn('id', $openItems->pluck('id'))
                ->update([
                    'order_id' => $targetOrder->id,
                    'received_at' => now(),
                ]);

            if ($draftToMerge) {
                $this->moveSharedOrderReferences($draftToMerge->id, $targetOrder->id);
                $draftToMerge->delete();
            }

            $itemsTotal = $this->computeOrderAmount($targetOrder, $mySession->id, vendorCountry: $vendorCountry);
            $serviceFee = round($itemsTotal * ($serviceFeeRate / 100), 2);
            $total = round($itemsTotal + $serviceFee, 2);

            $updates = [
                'amount' => $total,
                'service_fee' => $serviceFee,
            ];

            if ($targetOrder->status === Order::STATUS_DRAFT) {
                $updates['status'] = Order::STATUS_CONFIRMED;
                $updates['confirmed_at'] = now();
            } elseif ($targetOrder->status === Order::STATUS_CONFIRMED && ! $targetOrder->confirmed_at) {
                $updates['confirmed_at'] = now();
            }

            $targetOrder->update($updates);

            return $targetOrder->fresh();
        });

        $history = $this->buildTableHistoryResponse($mySession, $request);

        $personSnapshot = collect($history['people'])
            ->first(fn (array $p) => $p['session_id'] === $mySession->id);

        $customerName = $this->customerName($request->user());
        NotificationService::notifyTableCustomers(
            $mySession->restaurant_table_id,
            'order_updated',
            "{$customerName} confirmed their order.",
            [
                'template' => 'order.confirmed',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'order_id' => $order->id,
                'order_snapshots' => [NotificationService::orderSnapshot($order->load('paidBy'), true)],
                'person_snapshot' => $personSnapshot,
                ...$this->realtimeCartMetadata($mySession, $request),
            ],
            false,
        );

        NotificationService::notifyOperations(
            $mySession->vendor_id,
            'order_confirmed',
            "{$customerName} confirmed their order.",
            [NotificationService::VENDOR, NotificationService::WAITER, NotificationService::KITCHEN],
            [
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => 'staff.order_confirmed',
                'table_id' => $mySession->restaurant_table_id,
                'order_id' => $order->id,
                'severity' => 'urgent',
                'sound' => 'new_order',
                'source_actor_type' => 'customer',
                'source_actor_id' => $request->user()->id,
            ],
        );

        return response()->json($history);
    }

    /**
     * GET /api/customer/table/history
     *
     * Returns the unified table view (same shape as /draft, /update, /confirmed).
     */
    public function tableHistory(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        return response()->json($this->buildTableHistoryResponse($mySession, $request));
    }

    /**
     * Compute the bill-split amount an order owes:
     *   - For each cart_item owned by the order's session: line_total / (1 + count(shared_order_ids))
     *   - For each cart_item where this order's id is in shared_order_ids: same per-share amount
     */
    private function computeOrderAmount(Order $order, int $ownerSessionId, bool $includeOpenOwnedItems = false, string $vendorCountry = 'AT'): float
    {
        $owned = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where(function ($query) use ($order, $ownerSessionId, $includeOpenOwnedItems) {
                $query->where('order_id', $order->id);

                if ($includeOpenOwnedItems) {
                    $query->orWhere(function ($open) use ($ownerSessionId) {
                        $open->where('table_scan_session_id', $ownerSessionId)
                            ->whereNull('order_id');
                    });
                }
            })
            ->get();

        $sharedInto = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->whereJsonContains('shared_order_ids', $order->id)
            ->where('table_scan_session_id', '!=', $ownerSessionId)
            ->get();

        $total = 0.0;

        foreach ($owned as $item) {
            $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $total += $lineTotal / $shareCount;
        }

        foreach ($sharedInto as $item) {
            $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $total += $lineTotal / $shareCount;
        }

        return round($total, 2);
    }

    /**
     * Build the unified table-view response: per-session people, with each
     * person's orders enriched with computed items (owned + shared-into).
     */
    private function buildTableHistoryResponse(TableScanSession $mySession, ?Request $request = null): array
    {
        if (! $mySession->relationLoaded('vendor')) {
            $mySession->load('vendor.vendorSetting');
        }

        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $vendor = $mySession->vendor;
        $locale = $request && $vendor
            ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor)
            : 'en';

        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'restaurantTable:id,number,name',
            'vendor:id,vendor_public_id,restaurant_name,country',
        ])
            ->where('restaurant_table_id', $mySession->restaurant_table_id)
            ->where('vendor_id', $mySession->vendor_id)
            ->where('status', 'active')
            ->get();

        $sessionIds = $sessions->pluck('id')->all();

        $orders = Order::with('paidBy:id,first_name,last_name')
            ->whereIn('table_scan_session_id', $sessionIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('table_scan_session_id');

        $allCartItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->whereIn('table_scan_session_id', $sessionIds)
            ->get();

        $ordersById = $orders->flatten()->keyBy('id');

        $sessionCustomerNames = $sessions->mapWithKeys(fn (TableScanSession $s) => [
            $s->id => $s->customer
                ? trim($s->customer->first_name.' '.$s->customer->last_name)
                : 'Waiter',
        ]);

        $tableTotal = 0.0;
        $tableOrderCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $orders, $allCartItems, $ordersById, $sessionCustomerNames, $vendorCountry, $serviceFeeRate, $vendor, $locale, &$tableTotal, &$tableOrderCount) {
            $personOrders = $orders->get($s->id, collect());
            $personTotal = (float) $personOrders->sum(fn (Order $o) => (float) $o->amount);

            $tableTotal += $personTotal;
            $tableOrderCount += $personOrders->count();

            $personCartItems = collect();

            $orderPayloads = $personOrders->map(function (Order $order) use ($s, $allCartItems, $mySession, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor, $locale, &$personCartItems) {
                $ownedCartItems = $allCartItems->filter(function (CartItem $ci) use ($s, $order) {
                    if ($order->status === 'draft') {
                        return (int) $ci->table_scan_session_id === (int) $s->id
                            && $ci->order_id === null;
                    }

                    return (int) $ci->order_id === (int) $order->id;
                });

                $sharedIntoItems = $allCartItems->filter(function (CartItem $ci) use ($order, $ownedCartItems) {
                    if ($ownedCartItems->contains('id', $ci->id)) {
                        return false;
                    }
                    $ids = is_array($ci->shared_order_ids) ? $ci->shared_order_ids : [];

                    return in_array($order->id, array_map('intval', $ids), true);
                });

                $orderItems = $ownedCartItems->merge($sharedIntoItems);
                $personCartItems = $personCartItems->merge($orderItems);

                $itemRows = $orderItems
                    ->map(fn (CartItem $ci) => $this->cartItemPayload($ci, $order, $mySession, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor, $locale))
                    ->values()
                    ->all();

                return $this->orderPayload($order, $itemRows, $vendor);
            })->values();

            $personTaxGroups = TaxCalculationService::computeTaxGroups($personCartItems, $vendorCountry, true);
            $personTotals = TaxCalculationService::computeTotals($personTaxGroups, 0);

            $personServiceFee = round((float) $personOrders->sum(fn (Order $o) => (float) ($o->service_fee ?? 0)), 2);
            $personTotals['service_fee'] = $personServiceFee;
            $personTotals['grand_total'] = round($personTotals['grand_total'] + $personServiceFee, 2);

            $totalTips = round((float) $personOrders->sum(fn (Order $o) => (float) ($o->tip_amount ?? 0)), 2);
            $personTotals['total_tips'] = $totalTips;
            $personTotals['grand_total'] = round($personTotals['grand_total'] + $totalTips, 2);

            return [
                'session_id' => $s->id,
                'customer_id' => $s->customer_id,
                'is_me' => $s->id === $mySession->id,
                'name' => $s->customer
                    ? trim($s->customer->first_name.' '.$s->customer->last_name)
                    : 'Waiter',
                'scanned_at' => $this->dateTimes->formatDateTime($s->scanned_at, $vendor),
                'status' => $s->status,
                'orders_count' => $personOrders->count(),
                'total_amount' => round($personTotal, 2),
                'orders' => $orderPayloads,
                'tax_groups' => array_map(fn (array $g) => array_merge($g, [
                    'label' => $this->locales->translatedTaxCategoryName($g['tax_category'], $vendorCountry, $locale),
                ]), $personTaxGroups),
                'totals' => $personTotals,
            ];
        })->values();

        $table = $mySession->restaurantTable;

        return [
            'table' => $table ? [
                'id' => $table->id,
                'number' => $table->number ?? null,
                'name' => $table->name ?? null,
            ] : null,
            'vendor' => $vendor ? [
                'vendor_public_id' => $vendor->vendor_public_id ?? null,
                'restaurant_name' => $vendor->restaurant_name ?? null,
            ] : null,
            'session' => [
                'id' => $mySession->id,
                'status' => $mySession->status,
                'scanned_at' => $this->dateTimes->formatDateTime($mySession->scanned_at, $vendor),
            ],
            'people' => $people,
            'summary' => [
                'orders_count' => $tableOrderCount,
                'total_amount' => round($tableTotal, 2),
            ],
        ];
    }

    /**
     * Build the per-item row used inside an order's `items` array.
     */
    private function cartItemPayload(
        CartItem $ci,
        Order $order,
        TableScanSession $mySession,
        $ordersById = null,
        $sessionCustomerNames = null,
        ?string $vendorCountry = null,
        ?Vendor $vendor = null,
        string $locale = 'en',
    ): array {
        $vendorCountry ??= 'AT';
        $menuItem = $ci->menuItem;
        $itemTaxCategory = $menuItem?->tax_category ?? 'food';
        $unitPrice = $this->cartItemUnitPrice($ci, $vendorCountry);
        $lineTotal = $this->cartItemLineTotal($ci, $vendorCountry);
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);
        $vatAmount = TaxCalculationService::vatFromGross($lineTotal, $vatRate);
        $orderIds = array_values(array_map('intval', is_array($ci->shared_order_ids) ? $ci->shared_order_ids : []));
        $sharedBetween = 1 + count($orderIds);
        $myShare = round($lineTotal / $sharedBetween, 2);

        $sharedWith = array_values(array_filter(array_map(function (int $oid) use ($ordersById, $sessionCustomerNames) {
            if ($ordersById === null) {
                return ['order_id' => $oid, 'customer_id' => null, 'customer_name' => null];
            }
            $o = $ordersById->get($oid);
            if (! $o) {
                return null;
            }

            return [
                'order_id' => $o->id,
                'customer_id' => $o->customer_id,
                'customer_name' => $sessionCustomerNames?->get($o->table_scan_session_id, 'Guest') ?? 'Guest',
            ];
        }, $orderIds)));

        return [
            'cart_item_id' => $ci->id,
            'menu_item_id' => $ci->menu_item_id,
            'name' => $menuItem && $vendor
                ? $this->customizations->menuItemName($menuItem, $vendor, $locale)
                : $menuItem?->name,
            'image_url' => $menuItem?->image_url,
            'quantity' => $ci->quantity,
            'unit_price' => $unitPrice,
            'paid_addons' => $this->formatCartPaidAddons($ci, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'free_addons' => $this->formatCartNamedSelections($ci, 'free_addons', $vendor, $locale),
            'removed_items' => $this->formatCartNamedSelections($ci, 'removed_items', $vendor, $locale),
            'selected_modifiers' => $this->formatCartSelectedModifiers($ci, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'vat_rate' => $vatRate,
            'tax_category' => $itemTaxCategory,
            'vat_amount' => $vatAmount,
            'line_total' => $lineTotal,
            'is_mine' => (int) $ci->table_scan_session_id === (int) $mySession->id,
            'shared_between' => $sharedBetween,
            'shared_with' => $sharedWith,
            'my_share' => $myShare,
            'status' => $ci->status(),
            'received_at' => $this->dateTimes->formatDateTime($ci->received_at, $vendor),
            'preparing_start_at' => $this->dateTimes->formatDateTime($ci->preparing_start_at, $vendor),
            'ready_at' => $this->dateTimes->formatDateTime($ci->ready_at, $vendor),
            'served_at' => $this->dateTimes->formatDateTime($ci->served_at, $vendor),
        ];
    }


    /**
     * Build the per-order dict (without its `items` array — that is computed by the caller).
     */
    private function orderPayload(Order $o, array $items, ?Vendor $vendor = null): array
    {
        return [
            'id' => $o->id,
            'order_public_id' => $o->order_public_id,
            'parent_order_id' => $o->parent_order_id,
            'customer_id' => $o->customer_id,
            'paid_by' => $o->paidBy ? [
                'id' => $o->paidBy->id,
                'name' => trim(($o->paidBy->first_name ?? '').' '.($o->paidBy->last_name ?? '')) ?: 'Guest',
            ] : null,
            'vendor_id' => $o->vendor_id,
            'table_scan_session_id' => $o->table_scan_session_id,
            'status' => $o->status,
            'amount' => (float) $o->amount,
            'tip_amount' => (float) ($o->tip_amount ?? 0),
            'currency' => $o->currency,
            'order_number' => $o->order_number,
            'order_type' => $o->order_type,
            'table_number' => $o->table_number,
            'service_fee' => (float) ($o->service_fee ?? 0),
            'vat_amount' => (float) ($o->vat_amount ?? 0),
            'course' => $o->course,
            'payment_method' => $o->payment_method,
            'payment_pending' => (bool) $o->payment_pending,
            'payment_received' => (bool) $o->payment_received,
            'payment_confirmed_at' => $this->dateTimes->formatDateTime($o->payment_confirmed_at, $vendor),
            'payment_note' => $o->payment_note,
            'transaction_id' => $o->transaction_id,
            'served_at' => $this->dateTimes->formatDateTime($o->served_at, $vendor),
            'cancelled_at' => $this->dateTimes->formatDateTime($o->cancelled_at, $vendor),
            'cancelled_reason' => $o->cancelled_reason,
            'waiter_confirmed' => (bool) $o->waiter_confirmed,
            'waiter_confirmed_at' => $this->dateTimes->formatDateTime($o->waiter_confirmed_at, $vendor),
            'created_at' => $this->dateTimes->formatDateTime($o->created_at, $vendor),
            'updated_at' => $this->dateTimes->formatDateTime($o->updated_at, $vendor),
            'items' => $items,
        ];
    }

    private function currentOpenOrder(int $customerId, int $sessionId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->where('table_scan_session_id', $sessionId)
            ->whereIn('status', ['draft', 'confirmed'])
            ->where('payment_received', false)
            ->latest('id')
            ->first();
    }

    private function currentDraftOrder(int $customerId, int $sessionId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->where('table_scan_session_id', $sessionId)
            ->where('status', Order::STATUS_DRAFT)
            ->where('payment_received', false)
            ->latest('id')
            ->first();
    }

    private function currentUnpaidSubmittedOrder(int $customerId, int $sessionId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->where('table_scan_session_id', $sessionId)
            ->where('payment_received', false)
            ->whereNotIn('status', [Order::STATUS_DRAFT, Order::STATUS_CANCELLED])
            ->latest('id')
            ->first();
    }

    private function moveSharedOrderReferences(int $fromOrderId, int $toOrderId): void
    {
        CartItem::whereJsonContains('shared_order_ids', $fromOrderId)
            ->get()
            ->each(function (CartItem $item) use ($fromOrderId, $toOrderId) {
                $ids = array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []);
                $ids = array_values(array_unique(array_merge(
                    array_filter($ids, fn (int $id) => $id !== $fromOrderId),
                    [$toOrderId]
                )));

                $item->update(['shared_order_ids' => $ids]);
            });
    }

    private function normalizeCustomizations(MenuItem $menuItem, array $data, ?CartItem $existing = null): array
    {
        return [
            'paid_addons' => array_key_exists('paid_addons', $data)
                ? $this->normalizePaidAddons($menuItem, $data['paid_addons'] ?? [])
                : ($existing?->paid_addons ?? []),
            'free_addons' => array_key_exists('free_addons', $data)
                ? $this->normalizeStringSelections('free_addons', $menuItem->free_addons ?? [], $data['free_addons'] ?? [])
                : ($existing?->free_addons ?? []),
            'removed_items' => (array_key_exists('removed_items', $data) || array_key_exists('removable_items', $data))
                ? $this->normalizeStringSelections(
                    'removed_items',
                    $menuItem->removable_items ?? [],
                    $data['removed_items'] ?? $data['removable_items'] ?? []
                )
                : ($existing?->removed_items ?? []),
            'selected_modifiers' => $this->normalizeSelectedModifiers(
                $menuItem,
                $data['selected_modifiers'] ?? $data['modifiers'] ?? $existing?->selected_modifiers ?? []
            ),
        ];
    }

    private function normalizePaidAddons(MenuItem $menuItem, array $selected): array
    {
        return $this->customizations->normalizePaidAddons($menuItem, $selected);
    }

    private function normalizeStringSelections(string $field, array $configured, array $selected): array
    {
        return $this->customizations->normalizeNamedSelections($field, $configured, $selected);
    }

    private function normalizeSelectedModifiers(MenuItem $menuItem, array $selected): array
    {
        if (! $menuItem->relationLoaded('modifierGroups')) {
            $menuItem->load(['modifierGroups' => fn ($q) => $q->where('is_active', true)
                ->orderByPivot('sort_order')
                ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')])]);
        }

        $groups = $menuItem->modifierGroups->keyBy('id');
        $submitted = collect($selected)
            ->filter(fn ($entry) => is_array($entry))
            ->mapWithKeys(function (array $entry) {
                $groupId = (int) ($entry['modifier_group_id'] ?? $entry['group_id'] ?? $entry['id'] ?? 0);
                $optionIds = $entry['option_ids'] ?? $entry['options'] ?? [];

                if (! is_array($optionIds)) {
                    $optionIds = [$optionIds];
                }

                return $groupId > 0 ? [$groupId => $optionIds] : [];
            });

        $normalized = [];

        foreach ($groups as $groupId => $group) {
            $rawOptionIds = collect($submitted->get($groupId, []))
                ->map(fn ($id) => is_array($id) ? ($id['id'] ?? $id['option_id'] ?? null) : $id)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            $count = $rawOptionIds->count();
            $minRequired = max((int) $group->min_selection, $group->is_required ? 1 : 0);
            $maxSelection = max(1, (int) $group->max_selection);

            if ($count < $minRequired) {
                throw ValidationException::withMessages([
                    'selected_modifiers' => ["Please choose at least {$minRequired} option(s) for {$group->name}."],
                ]);
            }

            if ($count === 0) {
                continue;
            }

            if ($group->type === 'single' && $count > 1) {
                throw ValidationException::withMessages([
                    'selected_modifiers' => ["Only one option can be selected for {$group->name}."],
                ]);
            }

            if ($count > $maxSelection) {
                throw ValidationException::withMessages([
                    'selected_modifiers' => ["You can choose at most {$maxSelection} option(s) for {$group->name}."],
                ]);
            }

            $optionsById = $group->options->keyBy('id');
            $options = $rawOptionIds->map(function (int $optionId) use ($optionsById, $group) {
                $option = $optionsById->get($optionId);

                if (! $option) {
                    throw ValidationException::withMessages([
                        'selected_modifiers' => ["The selected option is not available for {$group->name}."],
                    ]);
                }

                return [
                    'id' => $option->id,
                    'price_adjustment' => round((float) $option->price_adjustment, 2),
                ];
            })->values()->all();

            $normalized[] = [
                'modifier_group_id' => $group->id,
                'type' => $group->type,
                'is_required' => (bool) $group->is_required,
                'min_selection' => (int) $group->min_selection,
                'max_selection' => (int) $group->max_selection,
                'tax_category' => $group->tax_category,
                'options' => $options,
            ];
        }

        $unknownGroupIds = $submitted->keys()
            ->map(fn ($id) => (int) $id)
            ->diff($groups->keys()->map(fn ($id) => (int) $id));

        if ($unknownGroupIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'selected_modifiers' => ['One or more selected modifier groups are not available for this menu item.'],
            ]);
        }

        return $normalized;
    }

    private function cartCustomizationsMatch(CartItem $item, array $customizations): bool
    {
        return json_encode($item->paid_addons ?? []) === json_encode($customizations['paid_addons'])
            && json_encode($item->free_addons ?? []) === json_encode($customizations['free_addons'])
            && json_encode($item->removed_items ?? []) === json_encode($customizations['removed_items'])
            && $this->modifiersMatchForDedup($item->selected_modifiers ?? [], $customizations['selected_modifiers']);
    }

    private function modifiersMatchForDedup(array $existing, array $incoming): bool
    {
        if (count($existing) !== count($incoming)) {
            return false;
        }

        foreach ($existing as $i => $group) {
            if (! isset($incoming[$i])) {
                return false;
            }
            $inGroup = $incoming[$i];

            if (($group['modifier_group_id'] ?? 0) !== ($inGroup['modifier_group_id'] ?? 0)) {
                return false;
            }

            $existingOptionIds = array_column($group['options'] ?? [], 'id');
            $incomingOptionIds = array_column($inGroup['options'] ?? [], 'id');

            sort($existingOptionIds);
            sort($incomingOptionIds);

            if ($existingOptionIds !== $incomingOptionIds) {
                return false;
            }
        }

        return true;
    }

    private function vendorCountry(TableScanSession $session): string
    {
        $vendor = $session->relationLoaded('vendor') ? $session->vendor : null;

        return $vendor?->country ?? 'AT';
    }

    private function serviceFeeRate(TableScanSession $session): float
    {
        $vendor = $session->relationLoaded('vendor') ? $session->vendor : null;
        $settings = $vendor?->relationLoaded('vendorSetting') ? $vendor->vendorSetting : $vendor?->vendorSetting;

        return (float) ($settings?->service_fee_rate ?? 0);
    }

    private function cartItemUnitPrice(CartItem $item, string $vendorCountry = 'AT'): float
    {
        return TaxCalculationService::cartItemUnitPriceGross($item, $vendorCountry);
    }

    private function cartItemLineTotal(CartItem $item, string $vendorCountry = 'AT'): float
    {
        return TaxCalculationService::cartItemLineTotalGross($item, $vendorCountry);
    }

    private function itemPayload(CartItem $item, ?string $vendorCountry = null, ?Vendor $vendor = null, string $locale = 'en'): array
    {
        $vendorCountry ??= 'AT';
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);
        $baseGross = TaxCalculationService::itemBaseGross($menuItem, $vendorCountry);
        $unitPrice = $this->cartItemUnitPrice($item, $vendorCountry);
        $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
        $vatAmount = TaxCalculationService::vatFromGross($lineTotal, $vatRate);
        $itemTaxCategory = $menuItem?->tax_category ?? 'food';

        return [
            'id' => $item->id,
            'quantity' => $item->quantity,
            'notes' => $item->notes,
            'price' => $unitPrice,
            'paid_addons' => $this->formatCartPaidAddons($item, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'free_addons' => $this->formatCartNamedSelections($item, 'free_addons', $vendor, $locale),
            'removed_items' => $this->formatCartNamedSelections($item, 'removed_items', $vendor, $locale),
            'selected_modifiers' => $this->formatCartSelectedModifiers($item, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'line_total' => $lineTotal,
            'menu_item' => $menuItem ? [
                'id' => $menuItem->id,
                'name' => $vendor
                    ? $this->customizations->menuItemName($menuItem, $vendor, $locale)
                    : $menuItem->name,
                'price' => $baseGross,
                'vat_rate' => $vatRate,
                'tax_category' => $menuItem->tax_category,
                'image_url' => $menuItem->image_url,
            ] : null,
        ];
    }

    private function formatCartPaidAddons(CartItem $item, string $itemTaxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;

        if (! $menuItem || ! $vendor) {
            return collect($item->paid_addons ?? [])->map(function ($addon) use ($itemTaxCategory, $vendorCountry) {
                $vatRate = TaxCalculationService::addonVatRate(is_array($addon) ? $addon : [], $itemTaxCategory, $vendorCountry);

                return [
                    'id' => is_array($addon) ? ($addon['id'] ?? null) : null,
                    'name' => is_array($addon) ? ($addon['name'] ?? '') : '',
                    'price' => TaxCalculationService::gross((float) (is_array($addon) ? ($addon['price'] ?? 0) : 0), $vatRate),
                    'vat_rate' => $vatRate,
                ];
            })->values()->all();
        }

        return $this->customizations->formatPaidAddons($menuItem, $item->paid_addons ?? [], $vendor, $locale, $itemTaxCategory, $vendorCountry);
    }

    private function formatCartNamedSelections(CartItem $item, string $field, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;
        $selected = $field === 'free_addons'
            ? ($item->free_addons ?? [])
            : ($item->removed_items ?? []);

        if (! $menuItem || ! $vendor) {
            return collect($selected)->map(fn ($value) => is_array($value) ? (string) ($value['name'] ?? '') : (string) $value)
                ->filter()
                ->values()
                ->all();
        }

        $configured = $field === 'free_addons'
            ? ($menuItem->free_addons ?? [])
            : ($menuItem->removable_items ?? []);

        return $this->customizations->formatNamedSelections($configured, $selected, $vendor, $locale);
    }

    private function formatCartSelectedModifiers(CartItem $item, string $itemTaxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        if ($vendor) {
            return $this->customizations->formatSelectedModifiers($item->selected_modifiers ?? [], $vendor, $locale, $itemTaxCategory, $vendorCountry);
        }

        return collect($item->selected_modifiers ?? [])->map(function ($group) use ($itemTaxCategory, $vendorCountry) {
            $groupTaxCategory = $group['tax_category'] ?? '';
            $vatRate = TaxCalculationService::modifierGroupVatRate($groupTaxCategory, $itemTaxCategory, $vendorCountry);

            $options = collect($group['options'] ?? [])->map(function ($option) use ($vatRate) {
                return [
                    'id' => $option['id'] ?? null,
                    'name' => $option['name'] ?? null,
                    'price_adjustment' => TaxCalculationService::gross((float) ($option['price_adjustment'] ?? 0), $vatRate),
                ];
            })->values()->all();

            return array_merge($group, [
                'vat_rate' => $vatRate,
                'options' => $options,
            ]);
        })->values()->all();
    }
}
