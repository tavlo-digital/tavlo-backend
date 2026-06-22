<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\TableSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/orders
     * Returns orders grouped by table session (dine-in) and flat list (takeaway).
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $statusFilter = $request->query('status');
        $orderTypeFilter = $request->query('orderType');

        $activeScanSessions = TableScanSession::with([
            'customer:id,first_name,last_name,email,phone,customer_public_id',
            'restaurantTable:id,number,name',
        ])
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->orderBy('scanned_at')
            ->get();

        $ordersByScanSession = Order::with([
            'customer:id,first_name,last_name,email,phone,customer_public_id',
            'tableScanSession.restaurantTable:id,number,name',
        ])
            ->where('vendor_id', $vendor->id)
            ->whereIn('table_scan_session_id', $activeScanSessions->pluck('id'))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->orderBy('created_at')
            ->get()
            ->groupBy('table_scan_session_id');

        $sessions = $activeScanSessions
            ->groupBy('restaurant_table_id')
            ->map(fn (Collection $group) => $this->formatTableScanSessionGroup($group, $ordersByScanSession))
            ->values();

        // Takeaway / non-session orders
        $takeawayQuery = $vendor->orders()
            ->whereNull('table_scan_session_id')
            ->where('order_type', '!=', 'dine-in')
            ->with('customer:id,first_name,last_name,email,phone,customer_public_id')
            ->orderByDesc('created_at');

        if ($statusFilter) {
            $takeawayQuery->where('status', $statusFilter);
        }

        if ($orderTypeFilter) {
            $takeawayQuery->where('order_type', $orderTypeFilter);
        }

        $takeaway = $takeawayQuery->get()->map(fn (Order $order) => $this->formatOrder($order));

        return response()->json([
            'sessions' => $sessions,
            'takeaway' => $takeaway,
        ]);
    }

    /**
     * GET /api/vendor/{vendorId}/orders/{orderId}
     */
    public function show(Request $request, string $vendorId, string $orderId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        // ISSUE-025: Authorise BEFORE loading the order.
        // Loading first leaks order existence via timing differences (IDOR).
        // Scoping the query to $vendor->orders() means a wrong-vendor request
        // gets a 404 (order not found for this vendor) rather than a 403,
        // which avoids revealing that the order ID exists at all.
        $this->authorizeVendor($request, $vendor);

        $order = $vendor->orders()
            ->where(function ($q) use ($orderId) {
                $q->where('order_public_id', $orderId)->orWhere('id', $orderId);
            })
            ->with([
                'customer:id,first_name,last_name,email,phone,customer_public_id',
                'tableScanSession.restaurantTable:id,number,name',
            ])
            ->firstOrFail();

        return response()->json($this->formatOrder($order));
    }

    /**
     * PATCH /api/orders/{orderId}
     */
    public function update(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,confirmed,preparing,ready,delivered,picked_up,cancelled'],
            'paymentPending' => ['sometimes', 'boolean'],
            'paymentReceived' => ['sometimes', 'boolean'],
            'paymentNote' => ['nullable', 'string', 'max:500'],
        ]);

        $mapped = [];
        if (isset($data['status'])) {
            $mapped['status'] = $data['status'];
        }
        if (isset($data['paymentPending'])) {
            $mapped['payment_pending'] = $data['paymentPending'];
        }
        if (isset($data['paymentReceived'])) {
            $mapped['payment_received'] = $data['paymentReceived'];
            if ($data['paymentReceived']) {
                $mapped['payment_confirmed_at'] = now();
            }
        }
        if (array_key_exists('paymentNote', $data)) {
            $mapped['payment_note'] = $data['paymentNote'];
        }

        $order->update($mapped);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/confirm
     * Waiter confirms a non-prepaid / pending order.
     */
    public function confirm(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $order->update([
            'status' => 'confirmed',
            'waiter_confirmed' => true,
            'waiter_confirmed_at' => now(),
        ]);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/confirm-cash
     * Confirms cash payment received for an order.
     */
    public function confirmCashPayment(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $order->update([
            'payment_received' => true,
            'payment_confirmed_at' => now(),
            'payment_pending' => false,
        ]);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/ready
     *
     * Marks the order as ready and stamps every linked cart_item with ready_at = now().
     * Linked = owned by the order's session OR order's id is in cart_item.shared_order_ids.
     */
    public function markReady(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $now = now();

        if ($order->table_scan_session_id) {
            CartItem::where('table_scan_session_id', $order->table_scan_session_id)
                ->update(['ready_at' => $now]);
        }

        CartItem::whereJsonContains('shared_order_ids', $order->id)
            ->update(['ready_at' => $now]);

        $order->update(['status' => 'ready']);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/vendor/orders/{orderId}/items/{cartItemId}
     */
    public function updateItemStatus(Request $request, string $orderId, string $cartItemId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:new,preparing,ready,served'],
        ]);

        $this->authorizeItemStatus($request, $data['status']);

        $item = $this->loadLinkedCartItems($order)
            ->first(fn (CartItem $cartItem) => (string) $cartItem->id === (string) $cartItemId);

        if (! $item) {
            return response()->json(['message' => 'Cart item is not linked to this order.'], 404);
        }

        $now = now();

        $updates = match ($data['status']) {
            'new' => [
                'preparing_start_at' => null,
                'ready_at' => null,
                'served_at' => null,
            ],
            'preparing' => [
                'preparing_start_at' => $item->preparing_start_at ?? $now,
                'ready_at' => null,
                'served_at' => null,
            ],
            'ready' => [
                'preparing_start_at' => $item->preparing_start_at ?? $now,
                'ready_at' => $item->ready_at ?? $now,
                'served_at' => null,
            ],
            'served' => [
                'preparing_start_at' => $item->preparing_start_at ?? $now,
                'ready_at' => $item->ready_at ?? $now,
                'served_at' => $item->served_at ?? $now,
            ],
        };

        $item->update($updates);
        $this->syncOrderStatusFromCartItems($order);

        return response()->json($this->formatOrder(
            $order->fresh()->load(['customer', 'tableScanSession.restaurantTable'])
        ));
    }

    /**
     * PATCH /api/orders/{orderId}/picked-up
     */
    public function markPickedUp(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $order->update(['status' => 'picked_up']);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/served
     */
    public function markServed(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $now = now();

        $this->loadLinkedCartItems($order)->each(function (CartItem $item) use ($now) {
            $item->update([
                'preparing_start_at' => $item->preparing_start_at ?? $now,
                'ready_at' => $item->ready_at ?? $now,
                'served_at' => $item->served_at ?? $now,
            ]);
        });

        $order->update([
            'status' => 'served',
            'served_at' => $now,
        ]);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/cancel
     */
    public function cancel(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $data['reason'] ?? null,
        ]);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    // ----------------------------------------------------------------
    // Session-level actions
    // ----------------------------------------------------------------

    /**
     * POST /api/vendor/{vendorId}/sessions/{sessionId}/release
     * Release batch to kitchen immediately (override the batch window).
     */
    public function releaseToKitchen(Request $request, string $vendorId, string $sessionId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $session = $this->resolveSession($vendor, $sessionId);

        $session->update(['batch_released_at' => now()]);

        $session->load('orders.customer');

        return response()->json($this->formatSession($session->fresh()));
    }

    /**
     * POST /api/vendor/{vendorId}/sessions/{sessionId}/fire-course
     * Advance to the next course.
     */
    public function fireNextCourse(Request $request, string $vendorId, string $sessionId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $session = $this->resolveSession($vendor, $sessionId);

        $next = $session->nextCourse();

        if ($next === null) {
            return response()->json(['message' => 'Already on the last course (desserts).'], 422);
        }

        $session->update(['current_course' => $next]);

        $session->load('orders.customer');

        return response()->json($this->formatSession($session->fresh()));
    }

    /**
     * POST /api/vendor/{vendorId}/sessions/{sessionId}/close
     * Close the table session (end of visit).
     */
    public function closeSession(Request $request, string $vendorId, string $sessionId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $session = $this->resolveSession($vendor, $sessionId);

        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $session->load('orders.customer');

        return response()->json($this->formatSession($session->fresh()));
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function formatTableScanSessionGroup(Collection $scanSessions, Collection $ordersByScanSession): array
    {
        /** @var TableScanSession $first */
        $first = $scanSessions->first();
        $table = $first->restaurantTable;

        $sessionIds = $scanSessions->pluck('id')->map(fn ($id) => (int) $id)->values();
        $orders = $sessionIds
            ->flatMap(fn (int $id) => $ordersByScanSession->get($id, collect()))
            ->sortBy('created_at')
            ->values();

        $nonCancelled = $orders->where('status', '!=', 'cancelled');
        $totalAmount = round((float) $nonCancelled->sum(fn (Order $order) => (float) $order->amount), 2);
        $paidAmount = round((float) $nonCancelled
            ->filter(fn (Order $order) => (bool) $order->payment_received)
            ->sum(fn (Order $order) => (float) $order->amount), 2);

        $hasCashPending = $nonCancelled->contains(
            fn (Order $order) => $order->payment_method === 'cash'
                && (bool) $order->payment_pending
                && ! (bool) $order->payment_received
        );

        $paymentStatus = match (true) {
            $totalAmount > 0 && $paidAmount >= $totalAmount => 'paid',
            $paidAmount > 0 => 'partial',
            $hasCashPending => 'cash_pending',
            default => 'unpaid',
        };

        $kitchenSummary = [
            'total' => $orders->count(),
            'pending' => $orders->where('status', 'pending')->count(),
            'draft' => $orders->where('status', 'draft')->count(),
            'confirmed' => $orders->where('status', 'confirmed')->count(),
            'preparing' => $orders->where('status', 'preparing')->count(),
            'ready' => $orders->where('status', 'ready')->count(),
            'served' => $orders->where('status', 'served')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
        ];

        return [
            'sessionId' => 'table-'.($table?->id ?? $first->restaurant_table_id),
            'tableId' => $table ? (string) $table->id : (string) $first->restaurant_table_id,
            'sessionIds' => $sessionIds->map(fn (int $id) => (string) $id)->all(),
            'vendorId' => (string) $first->vendor_id,
            'tableNumber' => $table?->number,
            'tableName' => $table?->name,
            'status' => 'active',
            'guestCount' => $scanSessions->count(),
            'totalAmount' => $totalAmount,
            'paidAmount' => $paidAmount,
            'paymentStatus' => $paymentStatus,
            'cashPending' => $hasCashPending,
            'closedAt' => null,
            'kitchenSummary' => $kitchenSummary,
            'orders' => $orders->map(fn (Order $order) => $this->formatOrder($order))->values(),
            'createdAt' => $scanSessions->min('scanned_at')?->toISOString() ?? $first->created_at?->toISOString(),
            'updatedAt' => $scanSessions->max('updated_at')?->toISOString() ?? $first->updated_at?->toISOString(),
        ];
    }

    private function formatSession(TableSession $session): array
    {
        $orders = $session->orders ?? collect();

        $kitchenSummary = [
            'total' => $orders->count(),
            'pending' => $orders->where('status', 'pending')->count(),
            'confirmed' => $orders->where('status', 'confirmed')->count(),
            'preparing' => $orders->where('status', 'preparing')->count(),
            'ready' => $orders->where('status', 'ready')->count(),
            'served' => $orders->where('status', 'served')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
        ];

        return [
            'sessionId' => (string) $session->id,
            'vendorId' => (string) $session->vendor_id,
            'tableNumber' => $session->table_number,
            'tableName' => $session->table_name,
            'status' => $session->status,
            'currentCourse' => $session->current_course,
            'batchStartedAt' => $session->batch_started_at?->toISOString(),
            'batchWindowSeconds' => $session->batch_window_seconds,
            'batchReleasedAt' => $session->batch_released_at?->toISOString(),
            'batchOpen' => $session->isBatchOpen(),
            'batchSecondsRemaining' => $session->batchSecondsRemaining(),
            'totalAmount' => $session->totalAmount(),
            'closedAt' => $session->closed_at?->toISOString(),
            'kitchenSummary' => $kitchenSummary,
            'orders' => $orders->map(fn (Order $o) => $this->formatOrder($o))->values(),
            'createdAt' => $session->created_at->toISOString(),
            'updatedAt' => $session->updated_at->toISOString(),
        ];
    }

    private function formatOrder(Order $order): array
    {
        $serviceFee = (float) ($order->service_fee ?? 0);
        $vatAmount = (float) ($order->vat_amount ?? 0);
        $total = (float) $order->amount;
        $subtotal = max(0, $total - $serviceFee - $vatAmount);

        $linkedItems = $this->loadLinkedCartItems($order);
        $itemsCount = (int) $linkedItems->sum('quantity');
        $readyAt = $linkedItems->isNotEmpty() && $linkedItems->every(fn (CartItem $ci) => $ci->ready_at !== null)
            ? $linkedItems->max(fn (CartItem $ci) => $ci->ready_at)
            : null;

        // Display status bucket used by the UI tabs
        $rawStatus = $order->status;
        $displayStatus = match ($rawStatus) {
            'pending', 'confirmed', 'preparing' => 'received',
            'delivered' => 'served',
            'picked_up' => 'picked-up',
            default => $rawStatus,
        };

        $pickupStatus = match ($rawStatus) {
            'picked_up' => 'picked-up',
            'ready' => 'ready',
            default => 'pending',
        };

        $timeline = [];
        if ($order->created_at) {
            $timeline[] = ['status' => 'received', 'timestamp' => $order->created_at->toISOString()];
        }
        if ($order->waiter_confirmed_at) {
            $timeline[] = ['status' => 'confirmed', 'timestamp' => $order->waiter_confirmed_at->toISOString()];
        }
        if ($readyAt) {
            $timeline[] = ['status' => 'ready', 'timestamp' => $readyAt->toISOString()];
        }
        if ($order->served_at) {
            $timeline[] = ['status' => 'served', 'timestamp' => $order->served_at->toISOString()];
        }
        if ($order->cancelled_at) {
            $timeline[] = ['status' => 'cancelled', 'timestamp' => $order->cancelled_at->toISOString()];
        }

        $items = $linkedItems->map(function (CartItem $ci) {
            $unitPrice = $this->cartItemUnitPrice($ci);
            $lineTotal = round($unitPrice * $ci->quantity, 2);
            $orderIds = array_values(array_map('intval', is_array($ci->shared_order_ids) ? $ci->shared_order_ids : []));
            $sharedBetween = 1 + count($orderIds);
            $itemStatus = $this->cartItemStatus($ci);
            $modifiers = $this->cartItemModifiers($ci);

            return [
                'cartItemId' => $ci->id,
                'cart_item_id' => $ci->id,
                'menuItemId' => $ci->menu_item_id,
                'menu_item_id' => $ci->menu_item_id,
                'name' => $ci->menuItem?->name,
                'imageUrl' => $ci->menuItem?->image_url,
                'image_url' => $ci->menuItem?->image_url,
                'category' => strtolower((string) ($ci->menuItem?->category?->display_name ?? 'other')),
                'quantity' => $ci->quantity,
                'notes' => $ci->notes,
                'specialInstructions' => $ci->notes,
                'unitPrice' => $unitPrice,
                'unit_price' => $unitPrice,
                'price' => $unitPrice,
                'lineTotal' => $lineTotal,
                'line_total' => $lineTotal,
                'paidAddons' => $ci->paid_addons ?? [],
                'paid_addons' => $ci->paid_addons ?? [],
                'freeAddons' => $ci->free_addons ?? [],
                'free_addons' => $ci->free_addons ?? [],
                'removedItems' => $ci->removed_items ?? [],
                'removed_items' => $ci->removed_items ?? [],
                'selectedModifiers' => $ci->selected_modifiers ?? [],
                'selected_modifiers' => $ci->selected_modifiers ?? [],
                'modifiers' => $modifiers,
                'status' => $itemStatus,
                'sharedBetween' => $sharedBetween,
                'sharedWithOrderIds' => $orderIds,
                'preparingStartAt' => $ci->preparing_start_at?->toISOString(),
                'preparing_start_at' => $ci->preparing_start_at?->toISOString(),
                'readyAt' => $ci->ready_at?->toISOString(),
                'ready_at' => $ci->ready_at?->toISOString(),
                'servedAt' => $ci->served_at?->toISOString(),
                'served_at' => $ci->served_at?->toISOString(),
            ];
        })->values()->all();

        $table = $order->tableScanSession?->restaurantTable;
        $customerName = $order->customer
            ? trim(($order->customer->first_name ?? '').' '.($order->customer->last_name ?? ''))
            : null;
        $customerName = $customerName !== '' ? $customerName : null;

        return [
            'id' => (string) $order->id,
            'orderPublicId' => $order->order_public_id,
            'orderNumber' => $order->order_number ?? $order->id,
            'orderType' => $order->order_type ?? 'dine-in',
            'tableNumber' => $order->table_number ?? $table?->number,
            'tableId' => $table ? (string) $table->id : null,
            'tableScanSessionId' => $order->table_scan_session_id ? (string) $order->table_scan_session_id : null,
            'course' => $order->course,
            'waiterConfirmed' => (bool) $order->waiter_confirmed,
            'waiterConfirmedAt' => $order->waiter_confirmed_at?->toISOString(),
            'customer' => $order->customer ? [
                'id' => (string) $order->customer->id,
                'name' => $customerName,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
            ] : null,
            'customerName' => $customerName,
            'customerPhone' => $order->customer?->phone,
            'customerEmail' => $order->customer?->email,
            'status' => $rawStatus,
            'displayStatus' => $displayStatus,
            'pickupStatus' => $pickupStatus,
            'itemsCount' => $itemsCount,
            'items' => $items,
            'amount' => $total,
            'total' => $total,
            'subtotal' => $subtotal,
            'serviceFee' => $serviceFee,
            'vatAmount' => $vatAmount,
            'currency' => $order->currency,
            'paymentMethod' => $order->payment_method,
            'paymentPending' => (bool) $order->payment_pending,
            'paymentReceived' => (bool) $order->payment_received,
            'paymentConfirmedAt' => $order->payment_confirmed_at?->toISOString(),
            'paymentNote' => $order->payment_note,
            'readyAt' => $readyAt?->toISOString(),
            'servedAt' => $order->served_at?->toISOString(),
            'cancelledAt' => $order->cancelled_at?->toISOString(),
            'cancelledReason' => $order->cancelled_reason,
            'timeline' => $timeline,
            'createdAt' => $order->created_at->toISOString(),
            'updatedAt' => $order->updated_at->toISOString(),
        ];
    }

    /**
     * Load every cart_item linked to an order: owned by the order's session
     * (if any) plus any cart_item whose shared_order_ids JSON contains the order id.
     */
    private function loadLinkedCartItems(Order $order)
    {
        return CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,menu_category_id', 'menuItem.category.masterCategory')
            ->where(function ($q) use ($order) {
                if ($order->status === 'draft' && $order->table_scan_session_id) {
                    $q->where(function ($owned) use ($order) {
                        $owned->where('table_scan_session_id', $order->table_scan_session_id)
                            ->whereNull('order_id');
                    });
                } else {
                    $q->where('order_id', $order->id);
                }

                $q->orWhereJsonContains('shared_order_ids', $order->id);
            })
            ->get();
    }

    private function cartItemUnitPrice(CartItem $item): float
    {
        $menuItem = $item->menuItem;
        $basePrice = 0.0;

        if ($menuItem) {
            $basePrice = $menuItem->has_discount && $menuItem->discounted_price !== null
                ? (float) $menuItem->discounted_price
                : (float) $menuItem->price;
        }

        $addonsTotal = collect($item->paid_addons ?? [])->sum(fn ($addon) => (float) ($addon['price'] ?? 0));
        $modifiersTotal = collect($item->selected_modifiers ?? [])
            ->flatMap(fn ($group) => is_array($group) ? ($group['options'] ?? []) : [])
            ->sum(fn ($option) => (float) ($option['price_adjustment'] ?? 0));

        return round($basePrice + $addonsTotal + $modifiersTotal, 2);
    }

    private function cartItemModifiers(CartItem $item): array
    {
        $paidAddons = collect($item->paid_addons ?? [])
            ->map(fn ($addon) => [
                'name' => (string) ($addon['name'] ?? ''),
                'price' => (float) ($addon['price'] ?? 0),
            ]);

        $freeAddons = collect($item->free_addons ?? [])
            ->map(fn ($name) => [
                'name' => (string) $name,
                'price' => 0.0,
            ]);

        $removedItems = collect($item->removed_items ?? [])
            ->map(fn ($name) => [
                'name' => 'No '.(string) $name,
                'price' => 0.0,
            ]);

        $selectedModifiers = collect($item->selected_modifiers ?? [])
            ->flatMap(fn ($group) => is_array($group) ? ($group['options'] ?? []) : [])
            ->map(fn ($option) => [
                'name' => (string) ($option['name'] ?? ''),
                'price' => (float) ($option['price_adjustment'] ?? 0),
            ]);

        return $paidAddons
            ->merge($freeAddons)
            ->merge($removedItems)
            ->merge($selectedModifiers)
            ->filter(fn (array $modifier) => $modifier['name'] !== '')
            ->values()
            ->all();
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->orWhere('id', $vendorId)
            ->firstOrFail();
    }

    private function resolveOrder(string $orderId): Order
    {
        return Order::where('order_public_id', $orderId)
            ->orWhere('id', $orderId)
            ->with(['vendor', 'tableScanSession.restaurantTable'])
            ->firstOrFail();
    }

    private function resolveSession(Vendor $vendor, string $sessionId): TableSession
    {
        return $vendor->tableSessions()
            ->where('id', $sessionId)
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();

        if ($user instanceof Vendor && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }

        if ($user instanceof TeamMember && $user->vendor_id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }

    private function authorizeItemStatus(Request $request, string $status): void
    {
        $user = $request->user();

        if (! $user instanceof TeamMember) {
            return;
        }

        if ($user->role === 'kitchen' && ! in_array($status, ['new', 'preparing', 'ready'], true)) {
            abort(403, 'Kitchen staff cannot mark items served.');
        }

        if ($user->role === 'waiter' && $status !== 'served') {
            abort(403, 'Waiters can only mark items served.');
        }
    }

    private function cartItemStatus(CartItem $item): string
    {
        // ISSUE-015: Standardised to lowercase enum matching the KDS send API and customer API.
        // 'in_progress' was renamed to 'preparing' across all surfaces.
        if ($item->served_at) {
            return 'served';
        }

        if ($item->ready_at) {
            return 'ready';
        }

        if ($item->preparing_start_at) {
            return 'preparing';
        }

        return 'new';
    }

    private function syncOrderStatusFromCartItems(Order $order): void
    {
        $items = $this->loadLinkedCartItems($order);

        if ($items->isEmpty() || $order->status === 'cancelled') {
            return;
        }

        if ($items->every(fn (CartItem $item) => $item->served_at !== null)) {
            $order->update([
                'status' => 'served',
                'served_at' => $order->served_at ?? now(),
            ]);

            return;
        }

        if ($items->every(fn (CartItem $item) => $item->ready_at !== null || $item->served_at !== null)) {
            $order->update(['status' => 'ready']);

            return;
        }

        if ($items->contains(fn (CartItem $item) => $item->preparing_start_at !== null)) {
            $order->update(['status' => 'preparing']);

            return;
        }

        if (in_array($order->status, ['ready', 'preparing', 'served'], true)) {
            $order->update([
                'status' => $order->waiter_confirmed ? 'confirmed' : 'pending',
                'served_at' => null,
            ]);
        }
    }
}
