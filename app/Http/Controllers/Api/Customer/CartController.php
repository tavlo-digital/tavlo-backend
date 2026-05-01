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
                'personal_items' => $s->cartItems->map(fn (CartItem $item) => $this->itemPayload($item)),
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

        $item->update(array_filter($data, fn ($v) => $v !== null));
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
     * Snapshots the customer's items at full price (no splitting).
     * The order is linked to the authenticated customer via `customer_id`.
     */
    public function createOrderDraft(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $myCartItems = CartItem::with('menuItem:id,name,price,image_url')
            ->where('table_scan_session_id', $mySession->id)
            ->get();

        $myTotal     = 0.0;
        $myItemCount = 0;
        $myItems     = [];

        foreach ($myCartItems as $item) {
            $unitPrice = $item->menuItem ? (float) $item->menuItem->price : 0.0;
            $lineTotal = round($unitPrice * $item->quantity, 2);

            $myTotal     += $lineTotal;
            $myItemCount += $item->quantity;

            $myItems[] = [
                'cart_item_id'  => $item->id,
                'menu_item_id'  => $item->menu_item_id,
                'name'          => $item->menuItem?->name,
                'image_url'     => $item->menuItem?->image_url,
                'quantity'      => $item->quantity,
                'unit_price'    => $unitPrice,
                'line_total'    => $lineTotal,
                'is_mine'       => true,
                'shared'        => false,
                'amount_billed' => $lineTotal,
            ];
        }

        $myTotal = round($myTotal, 2);

        $order = DB::transaction(function () use ($request, $mySession, $myTotal, $myItemCount, $myItems) {
            return Order::create([
                'order_public_id'       => 'ord-' . Str::random(12),
                'customer_id'           => $request->user()->id,
                'vendor_id'             => $mySession->vendor_id,
                'table_scan_session_id' => $mySession->id,
                'status'                => 'draft',
                'items_count'           => $myItemCount,
                'items'                 => $myItems,
                'amount'                => $myTotal,
                'currency'              => 'EUR',
                'payment_pending'       => true,
                'payment_received'      => false,
                'order_type'            => 'dine-in',
            ]);
        });

        return response()->json([
            'order' => [
                'id'                    => $order->id,
                'order_public_id'       => $order->order_public_id,
                'customer_id'           => $order->customer_id,
                'status'                => $order->status,
                'payment_pending'       => (bool) $order->payment_pending,
                'amount'                => (float) $order->amount,
                'currency'              => $order->currency,
                'items_count'           => $order->items_count,
                'table_scan_session_id' => $order->table_scan_session_id,
                'vendor_id'             => $order->vendor_id,
                'created_at'            => $order->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PUT /api/customer/table/order/update
     *
     * Update an existing order owned by the authenticated customer (matched by
     * `id` + `customer_id`). The frontend may pass `items_count`, `items`, and
     * `shared_items` — these are persisted as-is. `shared_items` is validated
     * (the cart_item_ids must belong to the same table; the shared_between_ids
     * customers must also be at the same table).
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'id'                                  => ['required', 'integer'],
            'items_count'                         => ['sometimes', 'integer', 'min:0'],
            'items'                               => ['sometimes', 'array'],
            'shared_items'                        => ['sometimes', 'array'],
            'shared_items.*.cart_item_id'         => ['required_with:shared_items', 'integer'],
            'shared_items.*.shared_between'       => ['required_with:shared_items', 'integer', 'min:2', 'max:99'],
            'shared_items.*.shared_between_ids'   => ['sometimes', 'array'],
            'shared_items.*.shared_between_ids.*' => ['integer'],
        ])->validate();

        $customerId = $request->user()->id;

        $order = Order::where('id', $data['id'])
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

        if (! empty($data['shared_items'])) {
            $sharedInput = collect($data['shared_items'])
                ->keyBy(fn ($row) => (int) $row['cart_item_id']);

            $validIds = CartItem::whereIn('table_scan_session_id', $sessionIds)
                ->whereIn('id', $sharedInput->keys()->all())
                ->pluck('id')
                ->all();

            $invalid = array_diff($sharedInput->keys()->all(), $validIds);
            if (! empty($invalid)) {
                return response()->json([
                    'message' => 'One or more shared cart items do not belong to this table.',
                    'invalid_cart_item_ids' => array_values($invalid),
                ], 422);
            }

            $allCustomerIds = $sharedInput
                ->flatMap(fn ($row) => $row['shared_between_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (! empty($allCustomerIds)) {
                $tableCustomerIds = TableScanSession::whereIn('id', $sessionIds)
                    ->pluck('customer_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $invalidCustomers = array_values(array_diff($allCustomerIds, $tableCustomerIds));
                if (! empty($invalidCustomers)) {
                    return response()->json([
                        'message' => 'One or more shared_between_ids customers are not at this table.',
                        'invalid_customer_ids' => $invalidCustomers,
                    ], 422);
                }
            }
        }

        $update = array_intersect_key($data, array_flip(['items_count', 'items', 'shared_items']));

        $order->update($update);
        $order->refresh();

        return response()->json([
            'order' => [
                'id'                    => $order->id,
                'order_public_id'       => $order->order_public_id,
                'customer_id'           => $order->customer_id,
                'status'                => $order->status,
                'payment_pending'       => (bool) $order->payment_pending,
                'amount'                => (float) $order->amount,
                'currency'              => $order->currency,
                'items_count'           => $order->items_count,
                'items'                 => $order->items,
                'shared_items'          => $order->shared_items,
                'table_scan_session_id' => $order->table_scan_session_id,
                'vendor_id'             => $order->vendor_id,
                'updated_at'            => $order->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/customer/table/order/confirmed
     *
     * Confirm the customer's latest draft order. No body required.
     *
     * Calculates the final amount using the order's saved `items` and
     * `shared_items`:
     *   - Sum every line_total from `items` (full price).
     *   - For each entry in `shared_items` with `shared_between = N`:
     *       share = line_total / N
     *       - if the shared item is in MY cart (`is_mine = true`):
     *           subtract (N - 1) × share from my total
     *           (I keep paying only 1 share instead of the full line_total)
     *       - if the shared item is in someone else's cart (`is_mine = false`):
     *           add 1 × share to my total
     *           (I owe my share for an item I didn't add to the cart)
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

        $items       = is_array($order->items) ? $order->items : [];
        $sharedItems = is_array($order->shared_items) ? $order->shared_items : [];

        $total = 0.0;
        foreach ($items as $line) {
            $total += (float) ($line['line_total'] ?? 0);
        }

        foreach ($sharedItems as $shared) {
            $lineTotal = (float) ($shared['line_total'] ?? 0);
            $splitBy   = max(2, (int) ($shared['shared_between'] ?? 2));
            $share     = round($lineTotal / $splitBy, 2);
            $isMine    = (bool) ($shared['is_mine'] ?? false);

            if ($isMine) {
                $total -= round(($splitBy - 1) * $share, 2);
            } else {
                $total += $share;
            }
        }

        $total = round($total, 2);

        $order->update([
            'status' => 'confirmed',
            'amount' => $total,
        ]);
        $order->refresh();

        return response()->json([
            'order' => [
                'id'                    => $order->id,
                'order_public_id'       => $order->order_public_id,
                'customer_id'           => $order->customer_id,
                'status'                => $order->status,
                'payment_pending'       => (bool) $order->payment_pending,
                'amount'                => (float) $order->amount,
                'currency'              => $order->currency,
                'items_count'           => $order->items_count,
                'table_scan_session_id' => $order->table_scan_session_id,
                'vendor_id'             => $order->vendor_id,
                'created_at'            => $order->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/customer/table/history
     *
     * Returns a full history view of the customer's currently active table:
     * - the current table + vendor + active session metadata
     * - every active session at the same table (people), with `is_me`
     * - for each person, every order they have placed during this table
     *   session (full snapshot: items, shared_items, status, totals)
     * - a table-wide summary (orders count + total amount across all people)
     */
    public function tableHistory(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'restaurantTable:id,number,name',
            'vendor:id,vendor_public_id,restaurant_name',
        ])
            ->where('restaurant_table_id', $mySession->restaurant_table_id)
            ->where('vendor_id', $mySession->vendor_id)
            ->where('status', 'active')
            ->get();

        $sessionIds = $sessions->pluck('id');

        $orders = Order::whereIn('table_scan_session_id', $sessionIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy('table_scan_session_id');

        $tableTotal      = 0.0;
        $tableOrderCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $orders, &$tableTotal, &$tableOrderCount) {
            $personOrders = $orders->get($s->id, collect());
            $personTotal  = (float) $personOrders->sum(fn (Order $o) => (float) $o->amount);

            $tableTotal      += $personTotal;
            $tableOrderCount += $personOrders->count();

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
                'orders'       => $personOrders->map(function (Order $o) {
                    $row = $o->toArray();
                    unset($row['id']);

                    return $row;
                })->values(),
            ];
        })->values();

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
        ]);
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
