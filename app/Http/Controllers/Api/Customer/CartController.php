<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\TableScanSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
            'cartItems.menuItem:id,name,price,image_url',
            'restaurantTable:id,number,name',
            'vendor:id,vendor_public_id,restaurant_name',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        $people = $sessions->map(function (TableScanSession $s) use ($mySession) {
            return [
                'session_id'  => $s->id,
                'customer_id' => $s->customer_id,
                'is_me'       => $s->id === $mySession->id,
                'name'        => $s->customer
                    ? trim($s->customer->first_name . ' ' . $s->customer->last_name)
                    : 'Guest',
                'personal_items' => $s->cartItems->map(fn(CartItem $item) => $this->itemPayload($item)),
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

    /**
     * POST /api/customer/cart/items
     *
     * Add an item to the authenticated customer's cart.
     */
    public function addItem(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity'     => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ])->validate();

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $item = CartItem::create([
            'table_scan_session_id' => $mySession->id,
            'menu_item_id'          => $data['menu_item_id'],
            'quantity'              => $data['quantity'] ?? 1,
            'notes'                 => $data['notes'] ?? null,
        ]);

        $item->load('menuItem:id,name,price,image_url');

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
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes'    => ['nullable', 'string', 'max:500'],
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

        $item->update(array_filter($data, fn($v) => $v !== null));
        $item->load('menuItem:id,name,price,image_url');

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
            'cartItems.menuItem:id,name,price,image_url',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        $tableTotal = 0.0;
        $tableItemCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, &$tableTotal, &$tableItemCount) {
            $personalTotal = 0.0;
            $personalCount = 0;
            $isMe = $s->id === $mySession->id;

            $items = $s->cartItems->map(function (CartItem $item) use ($isMe, &$personalTotal, &$personalCount, &$tableTotal, &$tableItemCount) {
                $unitPrice = $item->menuItem ? (float) $item->menuItem->price : 0.0;
                $lineTotal = round($unitPrice * $item->quantity, 2);

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

        $myCartItems = CartItem::with('menuItem:id,name,price')
            ->where('table_scan_session_id', $mySession->id)
            ->get();

        $myTotal = 0.0;
        foreach ($myCartItems as $item) {
            $unitPrice  = $item->menuItem ? (float) $item->menuItem->price : 0.0;
            $lineTotal  = $unitPrice * $item->quantity;
            $shareCount = 1 + count($item->order_ids ?? []);
            $myTotal   += $lineTotal / $shareCount;
        }
        $myTotal = round($myTotal, 2);

        DB::transaction(function () use ($request, $mySession, $myTotal) {
            return Order::create([
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
     * - shared_item:   append caller's order_id to that cart_item's order_ids
     * - unshared_item: remove  caller's order_id from that cart_item's order_ids (no-op if not present)
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

            $existing = is_array($cartItem->order_ids) ? $cartItem->order_ids : [];
            $existing = array_values(array_unique(array_map('intval', array_merge($existing, [$order->id]))));
            $cartItem->update(['order_ids' => $existing]);
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

            $existing = is_array($cartItem->order_ids) ? $cartItem->order_ids : [];
            $filtered = array_values(array_filter(
                array_map('intval', $existing),
                fn(int $id) => $id !== $order->id
            ));
            $cartItem->update(['order_ids' => $filtered]);
        }

        return response()->json($this->buildTableHistoryResponse($mySession));
    }

    /**
     * POST /api/customer/table/order/confirmed
     *
     * Confirm the customer's latest draft order. Recomputes the final amount
     * from cart_items: owned items split by (1 + count(order_ids)), plus a
     * share of every cart_item whose order_ids contains this order's id.
     */
    public function createOrderConfirmed(Request $request): JsonResponse
    {
        $customerId = $request->user()->id;

        $order = Order::where('customer_id', $customerId)
            ->where('status', 'draft')
            ->latest('id')
            ->first();

        if (! $order) {
            return response()->json(['message' => 'No draft order found.'], 404);
        }

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $total = $this->computeOrderAmount($order, $mySession->id);

        $order->update([
            'status' => 'confirmed',
            'amount' => $total,
        ]);

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
     *   - For each cart_item owned by the order's session: line_total / (1 + count(order_ids))
     *   - For each cart_item where this order's id is in order_ids: same per-share amount
     */
    private function computeOrderAmount(Order $order, int $ownerSessionId): float
    {
        $owned = CartItem::with('menuItem:id,price')
            ->where('table_scan_session_id', $ownerSessionId)
            ->get();

        $sharedInto = CartItem::with('menuItem:id,price')
            ->whereJsonContains('order_ids', $order->id)
            ->where('table_scan_session_id', '!=', $ownerSessionId)
            ->get();

        $total = 0.0;

        foreach ($owned as $item) {
            $unitPrice  = $item->menuItem ? (float) $item->menuItem->price : 0.0;
            $lineTotal  = $unitPrice * $item->quantity;
            $shareCount = 1 + count($item->order_ids ?? []);
            $total     += $lineTotal / $shareCount;
        }

        foreach ($sharedInto as $item) {
            $unitPrice  = $item->menuItem ? (float) $item->menuItem->price : 0.0;
            $lineTotal  = $unitPrice * $item->quantity;
            $shareCount = 1 + count($item->order_ids ?? []);
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
            ->get()
            ->groupBy('table_scan_session_id');

        $allCartItems = CartItem::with('menuItem:id,name,price,image_url')
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

            $ownedCartItems = $allCartItems->where('table_scan_session_id', $s->id);

            $latestOrder = $personOrders->last();
            $orderPayload = null;

            if ($latestOrder) {
                $sharedIntoItems = $allCartItems->filter(function (CartItem $ci) use ($latestOrder, $ownedCartItems) {
                    if ($ownedCartItems->contains('id', $ci->id)) {
                        return false;
                    }
                    $ids = is_array($ci->order_ids) ? $ci->order_ids : [];
                    return in_array($latestOrder->id, array_map('intval', $ids), true);
                });

                $itemRows = $ownedCartItems->merge($sharedIntoItems)
                    ->map(fn(CartItem $ci) => $this->cartItemPayload($ci, $latestOrder, $mySession, $ordersById, $sessionCustomerNames))
                    ->values()
                    ->all();

                $orderPayload = $this->orderPayload($latestOrder, $itemRows);
            }

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
                'order'        => $orderPayload,
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
        $unitPrice = $ci->menuItem ? (float) $ci->menuItem->price : 0.0;
        $lineTotal = round($unitPrice * $ci->quantity, 2);
        $orderIds  = array_values(array_map('intval', is_array($ci->order_ids) ? $ci->order_ids : []));
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
            'line_total'         => $lineTotal,
            'is_mine'            => (int) $ci->table_scan_session_id === (int) $mySession->id,
            'shared_between'     => $sharedBetween,
            'shared_with'        => $sharedWith,
            'my_share'           => $myShare,
            'preparing_start_at' => $ci->preparing_start_at?->toIso8601String(),
            'ready_at'           => $ci->ready_at?->toIso8601String(),
        ];
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

    private function itemPayload(CartItem $item): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;

        return [
            'id'        => $item->id,
            'quantity'  => $item->quantity,
            'notes'     => $item->notes,
            'menu_item' => $menuItem ? [
                'id'        => $menuItem->id,
                'name'      => $menuItem->name,
                'price'     => (float) $menuItem->price,
                'image_url' => $menuItem->image_url,
            ] : null,
        ];
    }
}