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

        return response()->json([
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
     * GET /api/customer/table/payment
     *
     * Returns a payment summary for the customer's current table:
     * - the authenticated customer's own line (name, item count, total)
     * - every active session at the same table (name, items, total)
     * - a flat list of every item on the table with an `is_mine` flag
     * - the table-wide grand total
     */
    public function tablePayment(Request $request): JsonResponse
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
     * POST /api/customer/table/payment
     *
     * Create a pending order for the authenticated customer based on the
     * current table cart, optionally splitting some items across N people.
     *
     * Body:
     * {
     *   "shared_items": [
     *     { "cart_item_id": 3, "shared_between": 3 }
     *   ]
     * }
     *
     * Rules:
     * - Each `cart_item_id` must belong to a cart_item whose session is at
     *   the same table as the authenticated customer's active session.
     * - `shared_between` must be >= 2.
     * - For each shared item:
     *     share = line_total / shared_between
     *     - if the item is in MY cart: I pay only `share` (instead of full line_total)
     *     - if the item is in someone else's cart: I add `share` to my total
     * - Items not listed in `shared_items` are billed normally:
     *     - mine → full line total
     *     - others → not on my bill
     *
     * Result: an Order row with `payment_pending = true`, `status = 'pending'`,
     * `amount` = my computed total, `items` snapshot, and `shared_items` JSON.
     */
    public function payNow(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $sessionIds = $this->tableSessionIds($mySession);

        $data = Validator::make($request->all(), [
            'shared_items'                  => ['sometimes', 'array'],
            'shared_items.*.cart_item_id'   => ['required_with:shared_items', 'integer'],
            'shared_items.*.shared_between' => ['required_with:shared_items', 'integer', 'min:2', 'max:99'],
        ])->validate();

        $sharedInput = collect($data['shared_items'] ?? [])
            ->keyBy(fn ($row) => (int) $row['cart_item_id']);

        // Validate every shared cart_item_id belongs to a session at the same table.
        if ($sharedInput->isNotEmpty()) {
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
        }

        // Pull every cart item across the table (need them to compute totals).
        $tableItems = CartItem::with('menuItem:id,name,price,image_url')
            ->whereIn('table_scan_session_id', $sessionIds)
            ->get();

        $myTotal      = 0.0;
        $myItemCount  = 0;
        $myItems      = [];
        $sharedDetail = [];

        foreach ($tableItems as $item) {
            $unitPrice = $item->menuItem ? (float) $item->menuItem->price : 0.0;
            $lineTotal = round($unitPrice * $item->quantity, 2);
            $isMine    = $item->table_scan_session_id === $mySession->id;
            $shared    = $sharedInput->get($item->id);

            if ($shared) {
                $splitBy = (int) $shared['shared_between'];
                $myShare = round($lineTotal / $splitBy, 2);

                $myTotal += $myShare;
                if ($isMine) {
                    $myItemCount += $item->quantity;
                }

                $myItems[] = [
                    'cart_item_id'   => $item->id,
                    'menu_item_id'   => $item->menu_item_id,
                    'name'           => $item->menuItem?->name,
                    'image_url'      => $item->menuItem?->image_url,
                    'quantity'       => $item->quantity,
                    'unit_price'     => $unitPrice,
                    'line_total'     => $lineTotal,
                    'is_mine'        => $isMine,
                    'shared'         => true,
                    'shared_between' => $splitBy,
                    'my_share'       => $myShare,
                    'amount_billed'  => $myShare,
                ];

                $sharedDetail[] = [
                    'cart_item_id'   => $item->id,
                    'menu_item_id'   => $item->menu_item_id,
                    'name'           => $item->menuItem?->name,
                    'quantity'       => $item->quantity,
                    'line_total'     => $lineTotal,
                    'shared_between' => $splitBy,
                    'my_share'       => $myShare,
                    'is_mine'        => $isMine,
                ];

                continue;
            }

            // Non-shared items only count if they're mine.
            if ($isMine) {
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
        }

        $myTotal = round($myTotal, 2);

        $order = DB::transaction(function () use ($mySession, $myTotal, $myItemCount, $myItems, $sharedDetail) {
            return Order::create([
                'order_public_id'       => 'ord-' . Str::random(12),
                'vendor_id'             => $mySession->vendor_id,
                'table_scan_session_id' => $mySession->id,
                'status'                => 'pending',
                'items_count'           => $myItemCount,
                'items'                 => $myItems,
                'shared_items'          => $sharedDetail ?: null,
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
