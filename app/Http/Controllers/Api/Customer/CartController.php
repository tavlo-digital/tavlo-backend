<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableScanSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
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
            'vendor:id,vendor_public_id,restaurant_name',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        $orderedStatuses = ['confirmed', 'preparing', 'ready', 'delivered', 'completed'];

        $orderedOrderIds = Order::whereIn('table_scan_session_id', $sessionIds)
            ->whereIn('status', $orderedStatuses)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $orderedOrderIds) {
            return [
                'session_id'  => $s->id,
                'customer_id' => $s->customer_id,
                'is_me'       => $s->id === $mySession->id,
                'name'        => $s->customer
                    ? trim($s->customer->first_name . ' ' . $s->customer->last_name)
                    : 'Guest',
                'personal_items' => $s->cartItems
                    ->filter(fn(CartItem $item) => $item->order_id === null
                        && ! $this->cartItemBelongsToOrderedOrder($item, $orderedOrderIds))
                    ->values()
                    ->map(fn(CartItem $item) => $this->itemPayload($item)),
            ];
        });

        $table  = $mySession->restaurantTable;
        $vendor = $mySession->vendor;

        return response()->json([
            'table' => $table ? [
                'id'     => $table->id,
                'number' => $table->number ?? null,
                'name'   => $table->name ?? null,
            ] : null,
            'vendor' => $vendor ? [
                'vendor_public_id' => $vendor->vendor_public_id ?? null,
                'restaurant_name'  => $vendor->restaurant_name ?? null,
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
            'menu_item_id'           => ['required', 'integer', 'exists:menu_items,id'],
            'quantity'               => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes'                  => ['nullable', 'string', 'max:500'],
            'paid_addons'            => ['sometimes', 'array'],
            'paid_addons.*.name'     => ['required_with:paid_addons', 'string', 'max:255'],
            'paid_addons.*.price'    => ['sometimes', 'numeric', 'min:0'],
            'free_addons'            => ['sometimes', 'array'],
            'free_addons.*'          => ['string', 'max:255'],
            'removed_items'          => ['sometimes', 'array'],
            'removed_items.*'        => ['string', 'max:255'],
            'removable_items'        => ['sometimes', 'array'],
            'removable_items.*'      => ['string', 'max:255'],
            'selected_modifiers'                         => ['sometimes', 'array'],
            'selected_modifiers.*.modifier_group_id'     => ['sometimes', 'integer'],
            'selected_modifiers.*.group_id'              => ['sometimes', 'integer'],
            'selected_modifiers.*.id'                    => ['sometimes', 'integer'],
            'selected_modifiers.*.option_ids'            => ['sometimes', 'array'],
            'selected_modifiers.*.option_ids.*'          => ['integer'],
            'selected_modifiers.*.options'               => ['sometimes', 'array'],
            'modifiers'                                  => ['sometimes', 'array'],
            'modifiers.*.modifier_group_id'              => ['sometimes', 'integer'],
            'modifiers.*.group_id'                       => ['sometimes', 'integer'],
            'modifiers.*.id'                             => ['sometimes', 'integer'],
            'modifiers.*.option_ids'                     => ['sometimes', 'array'],
            'modifiers.*.option_ids.*'                   => ['integer'],
            'modifiers.*.options'                        => ['sometimes', 'array'],
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
                'menu_item_id'          => $data['menu_item_id'],
                'order_id'              => null,
                'quantity'              => $data['quantity'] ?? 1,
                'notes'                 => $data['notes'] ?? null,
                'paid_addons'           => $customizations['paid_addons'],
                'free_addons'           => $customizations['free_addons'],
                'removed_items'         => $customizations['removed_items'],
                'selected_modifiers'    => $customizations['selected_modifiers'],
            ]);
        }

        $item->load('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category');

        return response()->json($this->itemPayload($item), 201);
    }

    /**
     * PATCH /api/customer/cart/items/{id}
     *
     * Update quantity or notes of a cart item owned by the current session.
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'quantity'               => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes'                  => ['nullable', 'string', 'max:500'],
            'paid_addons'            => ['sometimes', 'array'],
            'paid_addons.*.name'     => ['required_with:paid_addons', 'string', 'max:255'],
            'paid_addons.*.price'    => ['sometimes', 'numeric', 'min:0'],
            'free_addons'            => ['sometimes', 'array'],
            'free_addons.*'          => ['string', 'max:255'],
            'removed_items'          => ['sometimes', 'array'],
            'removed_items.*'        => ['string', 'max:255'],
            'removable_items'        => ['sometimes', 'array'],
            'removable_items.*'      => ['string', 'max:255'],
            'selected_modifiers'                         => ['sometimes', 'array'],
            'selected_modifiers.*.modifier_group_id'     => ['sometimes', 'integer'],
            'selected_modifiers.*.group_id'              => ['sometimes', 'integer'],
            'selected_modifiers.*.id'                    => ['sometimes', 'integer'],
            'selected_modifiers.*.option_ids'            => ['sometimes', 'array'],
            'selected_modifiers.*.option_ids.*'          => ['integer'],
            'selected_modifiers.*.options'               => ['sometimes', 'array'],
            'modifiers'                                  => ['sometimes', 'array'],
            'modifiers.*.modifier_group_id'              => ['sometimes', 'integer'],
            'modifiers.*.group_id'                       => ['sometimes', 'integer'],
            'modifiers.*.id'                             => ['sometimes', 'integer'],
            'modifiers.*.option_ids'                     => ['sometimes', 'array'],
            'modifiers.*.option_ids.*'                   => ['integer'],
            'modifiers.*.options'                        => ['sometimes', 'array'],
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

        return response()->json($this->itemPayload($item));
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

        $item->delete();

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
            'cartItems.menuItem:id,name,price,has_discount,discounted_price,image_url',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        $tableTotal = 0.0;
        $tableItemCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, &$tableTotal, &$tableItemCount) {
            $personalTotal = 0.0;
            $personalCount = 0;
            $isMe = $s->id === $mySession->id;

            $items = $s->cartItems
                ->filter(fn (CartItem $item) => $item->order_id === null)
                ->map(function (CartItem $item) use ($isMe, &$personalTotal, &$personalCount, &$tableTotal, &$tableItemCount) {
                $unitPrice = $this->cartItemUnitPrice($item);
                $lineTotal = $this->cartItemLineTotal($item);

                $personalTotal += $lineTotal;
                $personalCount += $item->quantity;
                $tableTotal += $lineTotal;
                $tableItemCount += $item->quantity;

                return [
                    'cart_item_id' => $item->id,
                    'menu_item_id' => $item->menu_item_id,
                    'name'         => $item->menuItem?->name,
                    'image_url'    => $item->menuItem?->image_url,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $unitPrice,
                    'paid_addons'  => $item->paid_addons ?? [],
                    'free_addons'  => $item->free_addons ?? [],
                    'removed_items' => $item->removed_items ?? [],
                    'selected_modifiers' => $item->selected_modifiers ?? [],
                    'total_price'  => $lineTotal,
                    'is_mine'      => $isMe,
                ];
            })->values();

            return [
                'session_id'  => $s->id,
                'customer_id' => $s->customer_id,
                'is_me'       => $isMe,
                'name'        => $s->customer
                    ? trim($s->customer->first_name . ' ' . $s->customer->last_name)
                    : 'Guest',
                'item_count'  => $personalCount,
                'total_price' => round($personalTotal, 2),
                'items'       => $items,
            ];
        })->values();

        $table = $mySession->restaurantTable;

        return response()->json([
            'table' => $table ? [
                'id'     => $table->id,
                'number' => $table->number ?? null,
                'name'   => $table->name ?? null,
            ] : null,
            'people'   => $people,
            'summary'  => [
                'item_count'  => $tableItemCount,
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

        $myCartItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price')
            ->where('table_scan_session_id', $mySession->id)
            ->whereNull('order_id')
            ->get();

        $myTotal = 0.0;
        foreach ($myCartItems as $item) {
            $lineTotal  = $this->cartItemLineTotal($item);
            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $myTotal   += $lineTotal / $shareCount;
        }
        $myTotal = round($myTotal, 2);

        if ($existingOrder) {
            $existingOrder->update(['amount' => $myTotal]);

            return response()->json($this->buildTableHistoryResponse($mySession));
        }

        DB::transaction(function () use ($request, $mySession, $myTotal) {
            Order::create([
                'order_public_id'       => 'ord-' . Str::random(12),
                'customer_id'           => $request->user()->id,
                'vendor_id'             => $mySession->vendor_id,
                'table_scan_session_id' => $mySession->id,
                'status'                => 'draft',
                'amount'                => $myTotal,
                'currency'              => 'EUR',
                'payment_pending'       => true,
                'payment_received'      => false,
                'order_type'            => 'dine-in',
            ]);
        });

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
            'shared_item'   => ['nullable', 'integer'],
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

            $existing = is_array($cartItem->shared_order_ids) ? $cartItem->shared_order_ids : [];
            $filtered = array_values(array_filter(
                array_map('intval', $existing),
                fn(int $id) => $id !== $order->id
            ));
            $cartItem->update(['shared_order_ids' => $filtered]);
        }

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

        $total = $this->computeOrderAmount($order, $mySession->id, includeOpenOwnedItems: true);

        DB::transaction(function () use ($order, $mySession, $total) {
            CartItem::where('table_scan_session_id', $mySession->id)
                ->whereNull('order_id')
                ->update(['order_id' => $order->id]);

            $order->update([
                'status' => 'confirmed',
                'amount' => $total,
            ]);
        });

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
    private function computeOrderAmount(Order $order, int $ownerSessionId, bool $includeOpenOwnedItems = false): float
    {
        $owned = CartItem::with('menuItem:id,price,has_discount,discounted_price')
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

        $sharedInto = CartItem::with('menuItem:id,price,has_discount,discounted_price')
            ->whereJsonContains('shared_order_ids', $order->id)
            ->where('table_scan_session_id', '!=', $ownerSessionId)
            ->get();

        $total = 0.0;

        foreach ($owned as $item) {
            $lineTotal  = $this->cartItemLineTotal($item);
            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $total     += $lineTotal / $shareCount;
        }

        foreach ($sharedInto as $item) {
            $lineTotal  = $this->cartItemLineTotal($item);
            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $total     += $lineTotal / $shareCount;
        }

        return round($total, 2);
    }

    /**
     * Build the unified table-view response: per-session people, with each
     * person's orders enriched with computed items (owned + shared-into).
     */
    private function buildTableHistoryResponse(TableScanSession $mySession): array
    {
        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'restaurantTable:id,number,name',
            'vendor:id,vendor_public_id,restaurant_name',
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

        $sessionCustomerNames = $sessions->mapWithKeys(fn(TableScanSession $s) => [
            $s->id => $s->customer
                ? trim($s->customer->first_name . ' ' . $s->customer->last_name)
                : 'Guest',
        ]);

        $tableTotal      = 0.0;
        $tableOrderCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $orders, $allCartItems, $ordersById, $sessionCustomerNames, &$tableTotal, &$tableOrderCount) {
            $personOrders = $orders->get($s->id, collect());
            $personTotal  = (float) $personOrders->sum(fn(Order $o) => (float) $o->amount);

            $tableTotal      += $personTotal;
            $tableOrderCount += $personOrders->count();

            $orderPayloads = $personOrders->map(function (Order $order) use ($s, $allCartItems, $mySession, $ordersById, $sessionCustomerNames) {
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

                $itemRows = $ownedCartItems->merge($sharedIntoItems)
                    ->map(fn(CartItem $ci) => $this->cartItemPayload($ci, $order, $mySession, $ordersById, $sessionCustomerNames))
                    ->values()
                    ->all();

                return $this->orderPayload($order, $itemRows);
            })->values();

            return [
                'session_id'   => $s->id,
                'customer_id'  => $s->customer_id,
                'is_me'        => $s->id === $mySession->id,
                'name'         => $s->customer
                    ? trim($s->customer->first_name . ' ' . $s->customer->last_name)
                    : 'Guest',
                'scanned_at'   => $s->scanned_at?->toIso8601String(),
                'status'       => $s->status,
                'orders_count' => $personOrders->count(),
                'total_amount' => round($personTotal, 2),
                'order'        => $orderPayloads,
            ];
        })->values();

        $table  = $mySession->restaurantTable;
        $vendor = $mySession->vendor;

        return [
            'table' => $table ? [
                'id'     => $table->id,
                'number' => $table->number ?? null,
                'name'   => $table->name ?? null,
            ] : null,
            'vendor' => $vendor ? [
                'vendor_public_id' => $vendor->vendor_public_id ?? null,
                'restaurant_name'  => $vendor->restaurant_name ?? null,
            ] : null,
            'session' => [
                'id'         => $mySession->id,
                'status'     => $mySession->status,
                'scanned_at' => $mySession->scanned_at?->toIso8601String(),
            ],
            'people'  => $people,
            'summary' => [
                'orders_count' => $tableOrderCount,
                'total_amount' => round($tableTotal, 2),
            ],
        ];
    }

    /**
     * Build the per-item row used inside an order's `items` array.
     */
    private function cartItemPayload(CartItem $ci, Order $order, TableScanSession $mySession, $ordersById = null, $sessionCustomerNames = null): array
    {
        $unitPrice = $this->cartItemUnitPrice($ci);
        $lineTotal = $this->cartItemLineTotal($ci);
        $vatRate = $ci->menuItem ? (float) $ci->menuItem->vat_rate : 0.0;
        $vatAmount = round($lineTotal * ($vatRate / 100), 2);
        $orderIds  = array_values(array_map('intval', is_array($ci->shared_order_ids) ? $ci->shared_order_ids : []));
        $sharedBetween = 1 + count($orderIds);
        $myShare   = round($lineTotal / $sharedBetween, 2);

        $sharedWith = array_values(array_filter(array_map(function (int $oid) use ($ordersById, $sessionCustomerNames) {
            if ($ordersById === null) {
                return ['order_id' => $oid, 'customer_id' => null, 'customer_name' => null];
            }
            $o = $ordersById->get($oid);
            if (! $o) {
                return null;
            }
            return [
                'order_id'      => $o->id,
                'customer_id'   => $o->customer_id,
                'customer_name' => $sessionCustomerNames?->get($o->table_scan_session_id, 'Guest') ?? 'Guest',
            ];
        }, $orderIds)));

        return [
            'cart_item_id'       => $ci->id,
            'menu_item_id'       => $ci->menu_item_id,
            'name'               => $ci->menuItem?->name,
            'image_url'          => $ci->menuItem?->image_url,
            'quantity'           => $ci->quantity,
            'unit_price'         => $unitPrice,
            'paid_addons'        => $ci->paid_addons ?? [],
            'free_addons'        => $ci->free_addons ?? [],
            'removed_items'      => $ci->removed_items ?? [],
            'selected_modifiers' => $ci->selected_modifiers ?? [],
            'vat_rate'           => $vatRate,
            'tax_category'       => $ci->menuItem?->tax_category,
            'vat_amount'         => $vatAmount,
            'line_total'         => $lineTotal,
            'is_mine'            => (int) $ci->table_scan_session_id === (int) $mySession->id,
            'shared_between'     => $sharedBetween,
            'shared_with'        => $sharedWith,
            'my_share'           => $myShare,
            'status'             => $this->cartItemStatus($ci),
            'preparing_start_at' => $ci->preparing_start_at?->toIso8601String(),
            'ready_at'           => $ci->ready_at?->toIso8601String(),
            'served_at'          => $ci->served_at?->toIso8601String(),
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

        return null;
    }

    /**
     * Build the per-order dict (without its `items` array — that is computed by the caller).
     */
    private function orderPayload(Order $o, array $items): array
    {
        return [
            'id'                    => $o->id,
            'order_public_id'       => $o->order_public_id,
            'customer_id'           => $o->customer_id,
            'vendor_id'             => $o->vendor_id,
            'table_scan_session_id' => $o->table_scan_session_id,
            'status'                => $o->status,
            'amount'                => (float) $o->amount,
            'currency'              => $o->currency,
            'order_number'          => $o->order_number,
            'order_type'            => $o->order_type,
            'table_number'          => $o->table_number,
            'service_fee'           => (float) ($o->service_fee ?? 0),
            'vat_amount'            => (float) ($o->vat_amount ?? 0),
            'course'                => $o->course,
            'payment_method'        => $o->payment_method,
            'payment_pending'       => (bool) $o->payment_pending,
            'payment_received'      => (bool) $o->payment_received,
            'payment_confirmed_at'  => $o->payment_confirmed_at?->toIso8601String(),
            'payment_note'          => $o->payment_note,
            'transaction_id'        => $o->transaction_id,
            'served_at'             => $o->served_at?->toIso8601String(),
            'cancelled_at'          => $o->cancelled_at?->toIso8601String(),
            'cancelled_reason'      => $o->cancelled_reason,
            'waiter_confirmed'      => (bool) $o->waiter_confirmed,
            'waiter_confirmed_at'   => $o->waiter_confirmed_at?->toIso8601String(),
            'created_at'            => $o->created_at?->toIso8601String(),
            'updated_at'            => $o->updated_at?->toIso8601String(),
            'items'                 => $items,
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
            'selected_modifiers' => (array_key_exists('selected_modifiers', $data) || array_key_exists('modifiers', $data))
                ? $this->normalizeSelectedModifiers($menuItem, $data['selected_modifiers'] ?? $data['modifiers'] ?? [])
                : ($existing?->selected_modifiers ?? []),
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

            if ($group->is_required && $count < (int) $group->min_selection) {
                throw ValidationException::withMessages([
                    'selected_modifiers' => ["Please choose at least {$group->min_selection} option(s) for {$group->name}."],
                ]);
            }

            if ($count === 0) {
                continue;
            }

            $maxSelection = max(1, (int) $group->max_selection);
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

            if ($count < (int) $group->min_selection) {
                throw ValidationException::withMessages([
                    'selected_modifiers' => ["Please choose at least {$group->min_selection} option(s) for {$group->name}."],
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

    private function selectedPaidAddonsTotal(CartItem $item): float
    {
        return round(collect($item->paid_addons ?? [])->sum(fn ($addon) => (float) ($addon['price'] ?? 0)), 2);
    }

    private function selectedModifiersTotal(CartItem $item): float
    {
        return round(collect($item->selected_modifiers ?? [])
            ->flatMap(fn ($group) => is_array($group) ? ($group['options'] ?? []) : [])
            ->sum(fn ($option) => (float) ($option['price_adjustment'] ?? 0)), 2);
    }

    private function cartItemUnitPrice(CartItem $item): float
    {
        $basePrice = $this->cartItemBasePrice($item);

        return round($basePrice + $this->selectedPaidAddonsTotal($item) + $this->selectedModifiersTotal($item), 2);
    }

    private function cartItemBasePrice(CartItem $item): float
    {
        $menuItem = $item->menuItem;

        if (! $menuItem) {
            return 0.0;
        }

        if ($menuItem->has_discount && $menuItem->discounted_price !== null) {
            return (float) $menuItem->discounted_price;
        }

        return (float) $menuItem->price;
    }

    private function cartItemLineTotal(CartItem $item): float
    {
        return round($this->cartItemUnitPrice($item) * (int) $item->quantity, 2);
    }

    private function itemPayload(CartItem $item): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;
        $basePrice = $this->cartItemBasePrice($item);
        $price = $this->cartItemUnitPrice($item);
        $vatRate = $menuItem ? (float) $menuItem->vat_rate : 0.0;
        $lineTotal = $this->cartItemLineTotal($item);
        $vatAmount = round($lineTotal * ($vatRate / 100), 2);

        return [
            'id'         => $item->id,
            'quantity'   => $item->quantity,
            'notes'      => $item->notes,
            'price'      => $price,
            'paid_addons' => $item->paid_addons ?? [],
            'free_addons' => $item->free_addons ?? [],
            'removed_items' => $item->removed_items ?? [],
            'selected_modifiers' => $item->selected_modifiers ?? [],
            'vat_amount' => $vatAmount,
            'line_total' => $lineTotal,
            'menu_item'  => $menuItem ? [
                'id'           => $menuItem->id,
                'name'         => $menuItem->name,
                'price'        => $basePrice,
                'vat_rate'     => $vatRate,
                'vat_amount'   => $vatAmount,
                'tax_category' => $menuItem->tax_category,
                'image_url'    => $menuItem->image_url,
            ] : null,
        ];
    }
}
