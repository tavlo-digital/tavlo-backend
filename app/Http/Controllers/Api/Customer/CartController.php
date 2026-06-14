<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\NotificationService;
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
    public function __construct(private readonly VendorDateTimeService $dateTimes) {}

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

        $sessionIds = $this->tableSessionIds($mySession);

        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'cartItems.menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category',
            'restaurantTable:id,number,name',
            'vendor:id,vendor_public_id,restaurant_name,country',
            'vendor.vendorSetting:id,vendor_id,service_fee_rate',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        $orderedStatuses = ['confirmed', 'preparing', 'ready', 'delivered', 'completed'];

        $orderedOrderIds = Order::whereIn('table_scan_session_id', $sessionIds)
            ->whereIn('status', $orderedStatuses)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $mySession = $sessions->firstWhere('id', $mySession->id) ?? $mySession;
        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $orderedOrderIds, $vendorCountry, $serviceFeeRate) {
            $personItems = $s->cartItems
                ->filter(fn (CartItem $item) => $item->order_id === null
                    && ! $this->cartItemBelongsToOrderedOrder($item, $orderedOrderIds))
                ->values();

            $personTaxGroups = TaxCalculationService::computeTaxGroups($personItems, $vendorCountry, true);
            $personTotals = TaxCalculationService::computeTotals($personTaxGroups, $serviceFeeRate);

            return [
                'session_id' => $s->id,
                'customer_id' => $s->customer_id,
                'is_me' => $s->id === $mySession->id,
                'name' => $s->customer
                    ? trim($s->customer->first_name.' '.$s->customer->last_name)
                    : 'Guest',
                'personal_items' => $personItems
                    ->map(fn (CartItem $item) => $this->itemPayload($item, $vendorCountry)),
                'tax_groups' => $personTaxGroups,
                'totals' => $personTotals,
            ];
        });

        $table = $mySession->restaurantTable;
        $vendor = $mySession->vendor;

        return response()->json([
            'table' => $table ? [
                'id' => $table->id,
                'number' => $table->number ?? null,
                'name' => $table->name ?? null,
            ] : null,
            'vendor' => $vendor ? [
                'vendor_public_id' => $vendor->vendor_public_id ?? null,
                'restaurant_name' => $vendor->restaurant_name ?? null,
            ] : null,
            'people' => $people->values(),
        ]);
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
            'paid_addons.*.name' => ['required_with:paid_addons', 'string', 'max:255'],
            'paid_addons.*.price' => ['sometimes', 'numeric', 'min:0'],
            'free_addons' => ['sometimes', 'array'],
            'free_addons.*' => ['string', 'max:255'],
            'removed_items' => ['sometimes', 'array'],
            'removed_items.*' => ['string', 'max:255'],
            'removable_items' => ['sometimes', 'array'],
            'removable_items.*' => ['string', 'max:255'],
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

        $item->load('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category');

        $customerName = $this->customerName($request->user());
        $itemName = $item->menuItem?->name ?? 'an item';
        NotificationService::notifyTableCustomers($mySession->restaurant_table_id, 'cart_updated', "{$customerName} added {$itemName} to the cart.");

        return response()->json($this->itemPayload($item, $mySession->vendor?->country), 201);
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
            'paid_addons.*.name' => ['required_with:paid_addons', 'string', 'max:255'],
            'paid_addons.*.price' => ['sometimes', 'numeric', 'min:0'],
            'free_addons' => ['sometimes', 'array'],
            'free_addons.*' => ['string', 'max:255'],
            'removed_items' => ['sometimes', 'array'],
            'removed_items.*' => ['string', 'max:255'],
            'removable_items' => ['sometimes', 'array'],
            'removable_items.*' => ['string', 'max:255'],
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
        $item->load('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category');

        $customerName = $this->customerName($request->user());
        $itemName = $item->menuItem?->name ?? 'an item';
        NotificationService::notifyTableCustomers($mySession->restaurant_table_id, 'cart_updated', "{$customerName} updated {$itemName} in the cart.");

        return response()->json($this->itemPayload($item, $mySession->vendor?->country));
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

        $itemName = $item->menuItem?->name ?? 'an item';
        $item->delete();

        $customerName = $this->customerName($request->user());
        NotificationService::notifyTableCustomers($mySession->restaurant_table_id, 'cart_updated', "{$customerName} removed {$itemName} from the cart.");

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
            'cartItems.menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category',
            'vendor:id,vendor_public_id,restaurant_name,country',
            'vendor.vendorSetting:id,vendor_id,service_fee_rate',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        $mySession = $sessions->firstWhere('id', $mySession->id) ?? $mySession;
        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $tableTotal = 0.0;
        $tableItemCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $vendorCountry, $serviceFeeRate, &$tableTotal, &$tableItemCount) {
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
                        'name' => $item->menuItem?->name,
                        'image_url' => $item->menuItem?->image_url,
                        'quantity' => $item->quantity,
                        'unit_price' => $unitPrice,
                        'paid_addons' => $this->formatCartPaidAddons($item, $itc, $vendorCountry),
                        'free_addons' => $item->free_addons ?? [],
                        'removed_items' => $item->removed_items ?? [],
                        'selected_modifiers' => $this->formatCartSelectedModifiers($item, $itc, $vendorCountry),
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
                    : 'Guest',
                'item_count' => $personalCount,
                'total_price' => round($personalTotal, 2),
                'items' => $items,
                'tax_groups' => $personTaxGroups,
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

        $existingOrder = $this->currentOpenOrder($request->user()->id, $mySession->id);

        if ($existingOrder && $existingOrder->status === 'confirmed') {
            return response()->json($this->buildTableHistoryResponse($mySession));
        }

        $myCartItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,vat_rate,tax_category')
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

            NotificationService::notifyTableCustomers($mySession->restaurant_table_id, 'order_updated', "{$customerName} updated their order draft.");

            return response()->json($this->buildTableHistoryResponse($mySession));
        }

        DB::transaction(function () use ($request, $mySession, $myTotal) {
            $currency = $mySession->vendor?->currency ?? 'EUR';

            Order::create([
                'order_public_id' => 'ord-'.Str::random(12),
                'customer_id' => $request->user()->id,
                'vendor_id' => $mySession->vendor_id,
                'table_scan_session_id' => $mySession->id,
                'status' => 'draft',
                'amount' => $myTotal,
                'currency' => $currency,
                'payment_pending' => true,
                'payment_received' => false,
                'order_type' => 'dine-in',
            ]);
        });

        NotificationService::notifyTableCustomers($mySession->restaurant_table_id, 'order_updated', "{$customerName} created an order draft.");

        return response()->json($this->buildTableHistoryResponse($mySession), 201);
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

            if ((int) $cartItem->table_scan_session_id === (int) $mySession->id) {
                return response()->json([
                    'message' => 'You cannot share your own cart item with yourself.',
                ], 422);
            }

            $existing = is_array($cartItem->shared_order_ids) ? $cartItem->shared_order_ids : [];
            $existing = array_values(array_unique(array_map('intval', array_merge($existing, [$order->id]))));
            $cartItem->update(['shared_order_ids' => $existing]);

            if ($cartItem->order_id) {
                $affectedOrderIds->push($cartItem->order_id);
            }
            $affectedOrderIds = $affectedOrderIds->merge(array_map('intval', $existing));
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

            if ($cartItem->order_id) {
                $ownerOrder = Order::where('id', $cartItem->order_id)->first();

                if ($ownerOrder && $ownerOrder->payment_received) {
                    return response()->json([
                        'message' => 'Cannot unshare an item whose owner has already paid.',
                    ], 422);
                }
            }

            $existing = is_array($cartItem->shared_order_ids) ? $cartItem->shared_order_ids : [];
            $affectedOrderIds = $affectedOrderIds->merge(array_map('intval', $existing));
            if ($cartItem->order_id) {
                $affectedOrderIds->push($cartItem->order_id);
            }

            $filtered = array_values(array_filter(
                array_map('intval', $existing),
                fn (int $id) => $id !== $order->id
            ));
            $cartItem->update(['shared_order_ids' => $filtered]);
        }

        if (! $mySession->relationLoaded('vendor')) {
            $mySession->load('vendor.vendorSetting');
        }
        $vendorCountry = $this->vendorCountry($mySession);
        $ordersToRecalc = Order::whereIn('id', $affectedOrderIds->unique()->values()->all())
            ->where('payment_received', false)
            ->whereNotNull('table_scan_session_id')
            ->get();

        foreach ($ordersToRecalc as $affectedOrder) {
            $newAmount = $this->computeOrderAmount(
                $affectedOrder,
                $affectedOrder->table_scan_session_id,
                vendorCountry: $vendorCountry
            );
            $affectedOrder->update(['amount' => $newAmount]);
        }

        $customerName = $this->customerName($request->user());
        NotificationService::notifyTableCustomers($mySession->restaurant_table_id, 'order_updated', "{$customerName} updated item sharing on the order.");

        return response()->json($this->buildTableHistoryResponse($mySession));
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

        $order = $this->currentOpenOrder($customerId, $mySession->id);

        if (! $order) {
            return response()->json(['message' => 'No open draft order found.'], 404);
        }

        $total = $this->computeOrderAmount($order, $mySession->id, includeOpenOwnedItems: true, vendorCountry: $this->vendorCountry($mySession));

        DB::transaction(function () use ($order, $mySession, $total) {
            CartItem::where('table_scan_session_id', $mySession->id)
                ->whereNull('order_id')
                ->update([
                    'order_id' => $order->id,
                    'received_at' => now(),
                ]);

            $order->update([
                'status' => 'confirmed',
                'amount' => $total,
            ]);
        });

        $customerName = $this->customerName($request->user());
        NotificationService::notifyTableCustomers($mySession->restaurant_table_id, 'order_updated', "{$customerName} confirmed their order.");

        return response()->json($this->buildTableHistoryResponse($mySession));
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

        return response()->json($this->buildTableHistoryResponse($mySession));
    }

    /**
     * Compute the bill-split amount an order owes:
     *   - For each cart_item owned by the order's session: line_total / (1 + count(shared_order_ids))
     *   - For each cart_item where this order's id is in shared_order_ids: same per-share amount
     */
    private function computeOrderAmount(Order $order, int $ownerSessionId, bool $includeOpenOwnedItems = false, string $vendorCountry = 'AT'): float
    {
        $owned = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category')
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

        $sharedInto = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category')
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
    private function buildTableHistoryResponse(TableScanSession $mySession): array
    {
        if (! $mySession->relationLoaded('vendor')) {
            $mySession->load('vendor.vendorSetting');
        }

        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $vendor = $mySession->vendor;

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

        $orders = Order::whereIn('table_scan_session_id', $sessionIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('table_scan_session_id');

        $allCartItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category')
            ->whereIn('table_scan_session_id', $sessionIds)
            ->get();

        $ordersById = $orders->flatten()->keyBy('id');

        $sessionCustomerNames = $sessions->mapWithKeys(fn (TableScanSession $s) => [
            $s->id => $s->customer
                ? trim($s->customer->first_name.' '.$s->customer->last_name)
                : 'Guest',
        ]);

        $tableTotal = 0.0;
        $tableOrderCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $orders, $allCartItems, $ordersById, $sessionCustomerNames, $vendorCountry, $serviceFeeRate, $vendor, &$tableTotal, &$tableOrderCount) {
            $personOrders = $orders->get($s->id, collect());
            $personTotal = (float) $personOrders->sum(fn (Order $o) => (float) $o->amount);

            $tableTotal += $personTotal;
            $tableOrderCount += $personOrders->count();

            $personCartItems = collect();

            $orderPayloads = $personOrders->map(function (Order $order) use ($s, $allCartItems, $mySession, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor, &$personCartItems) {
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
                    ->map(fn (CartItem $ci) => $this->cartItemPayload($ci, $order, $mySession, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor))
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
                    : 'Guest',
                'scanned_at' => $this->dateTimes->formatDateTime($s->scanned_at, $vendor),
                'status' => $s->status,
                'orders_count' => $personOrders->count(),
                'total_amount' => round($personTotal, 2),
                'orders' => $orderPayloads,
                'tax_groups' => $personTaxGroups,
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
            'name' => $menuItem?->name,
            'image_url' => $menuItem?->image_url,
            'quantity' => $ci->quantity,
            'unit_price' => $unitPrice,
            'paid_addons' => $this->formatCartPaidAddons($ci, $itemTaxCategory, $vendorCountry),
            'free_addons' => $ci->free_addons ?? [],
            'removed_items' => $ci->removed_items ?? [],
            'selected_modifiers' => $this->formatCartSelectedModifiers($ci, $itemTaxCategory, $vendorCountry),
            'vat_rate' => $vatRate,
            'tax_category' => $itemTaxCategory,
            'vat_amount' => $vatAmount,
            'line_total' => $lineTotal,
            'is_mine' => (int) $ci->table_scan_session_id === (int) $mySession->id,
            'shared_between' => $sharedBetween,
            'shared_with' => $sharedWith,
            'my_share' => $myShare,
            'status' => $this->cartItemStatus($ci),
            'received_at' => $this->dateTimes->formatDateTime($ci->received_at, $vendor),
            'preparing_start_at' => $this->dateTimes->formatDateTime($ci->preparing_start_at, $vendor),
            'ready_at' => $this->dateTimes->formatDateTime($ci->ready_at, $vendor),
            'served_at' => $this->dateTimes->formatDateTime($ci->served_at, $vendor),
        ];
    }

    private function cartItemStatus(CartItem $item): ?string
    {
        if ($item->served_at) {
            return 'Served';
        }

        if ($item->ready_at) {
            return 'Ready';
        }

        if ($item->preparing_start_at) {
            return 'Preparing';
        }

        if ($item->received_at) {
            return 'Received';
        }

        return null;
    }

    /**
     * Build the per-order dict (without its `items` array — that is computed by the caller).
     */
    private function orderPayload(Order $o, array $items, ?Vendor $vendor = null): array
    {
        return [
            'id' => $o->id,
            'order_public_id' => $o->order_public_id,
            'customer_id' => $o->customer_id,
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
        $configured = collect($menuItem->paid_addons ?? [])
            ->filter(fn ($addon) => is_array($addon) && isset($addon['name']))
            ->mapWithKeys(fn ($addon) => [
                strtolower(trim((string) $addon['name'])) => [
                    'name' => (string) $addon['name'],
                    'price' => round((float) ($addon['price'] ?? 0), 2),
                ],
            ]);

        $normalized = collect($selected)
            ->map(function ($addon) use ($configured) {
                $name = is_array($addon) ? trim((string) ($addon['name'] ?? '')) : '';
                $matched = $configured->get(strtolower($name));

                if (! $matched) {
                    throw ValidationException::withMessages([
                        'paid_addons' => ["The selected paid add-on '{$name}' is not available for this menu item."],
                    ]);
                }

                return $matched;
            })
            ->unique('name')
            ->sortBy('name')
            ->values()
            ->all();

        return $normalized;
    }

    private function normalizeStringSelections(string $field, array $configured, array $selected): array
    {
        $available = collect($configured)
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->mapWithKeys(fn ($item) => [strtolower(trim($item)) => trim($item)]);

        return collect($selected)
            ->map(function ($item) use ($field, $available) {
                $value = trim((string) $item);
                $matched = $available->get(strtolower($value));

                if (! $matched) {
                    throw ValidationException::withMessages([
                        $field => ["The selected {$field} value '{$value}' is not available for this menu item."],
                    ]);
                }

                return $matched;
            })
            ->unique()
            ->sort()
            ->values()
            ->all();
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
                    'name' => $option->name,
                    'price_adjustment' => round((float) $option->price_adjustment, 2),
                ];
            })->values()->all();

            $normalized[] = [
                'modifier_group_id' => $group->id,
                'name' => $group->name,
                'type' => $group->type,
                'is_required' => (bool) $group->is_required,
                'min_selection' => (int) $group->min_selection,
                'max_selection' => (int) $group->max_selection,
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
        return ($item->paid_addons ?? []) === $customizations['paid_addons']
            && ($item->free_addons ?? []) === $customizations['free_addons']
            && ($item->removed_items ?? []) === $customizations['removed_items']
            && ($item->selected_modifiers ?? []) === $customizations['selected_modifiers'];
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

    private function itemPayload(CartItem $item, ?string $vendorCountry = null): array
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
            'paid_addons' => $this->formatCartPaidAddons($item, $itemTaxCategory, $vendorCountry),
            'free_addons' => $item->free_addons ?? [],
            'removed_items' => $item->removed_items ?? [],
            'selected_modifiers' => $this->formatCartSelectedModifiers($item, $itemTaxCategory, $vendorCountry),
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'line_total' => $lineTotal,
            'menu_item' => $menuItem ? [
                'id' => $menuItem->id,
                'name' => $menuItem->name,
                'price' => $baseGross,
                'vat_rate' => $vatRate,
                'tax_category' => $menuItem->tax_category,
                'image_url' => $menuItem->image_url,
            ] : null,
        ];
    }

    private function formatCartPaidAddons(CartItem $item, string $itemTaxCategory, string $vendorCountry): array
    {
        return collect($item->paid_addons ?? [])->map(function ($addon) use ($itemTaxCategory, $vendorCountry) {
            $vatRate = TaxCalculationService::addonVatRate($addon, $itemTaxCategory, $vendorCountry);

            return [
                'name' => $addon['name'],
                'price' => TaxCalculationService::gross((float) ($addon['price'] ?? 0), $vatRate),
                'vat_rate' => $vatRate,
            ];
        })->values()->all();
    }

    private function formatCartSelectedModifiers(CartItem $item, string $itemTaxCategory, string $vendorCountry): array
    {
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
