<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Exceptions\StaffCommandConflictException;
use App\Http\Controllers\Api\Vendor\Concerns\QueuesStaffCommands;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\TableScanSession;
use App\Models\TableSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Services\LocaleService;
use App\Services\MediaService;
use App\Services\MenuCustomizationService;
use App\Services\NotificationService;
use App\Services\PaymentMethodDetailsService;
use App\Services\StaffCommandBus;
use App\Services\TableStatePatchService;
use App\Services\TaxCalculationService;
use App\Services\VendorDateTimeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderController extends Controller
{
    use QueuesStaffCommands;

    public function __construct(
        private readonly LocaleService $locales,
        private readonly MenuCustomizationService $customizations,
        private readonly TableStatePatchService $statePatches,
        private readonly StaffCommandBus $staffCommands,
        private readonly MediaService $media,
        private readonly VendorDateTimeService $dateTimes,
        private readonly PaymentMethodDetailsService $paymentMethods,
    ) {}

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
            'restaurantTable:id,number,name',
        ])
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->orderBy('scanned_at')
            ->get();

        $sessionOrders = Order::with([
            'customer:id,first_name,last_name,email,phone,customer_public_id',
            'payments:id,order_id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
            'coveredPayments:id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
        ])
            ->where('vendor_id', $vendor->id)
            ->whereIn('table_scan_session_id', $activeScanSessions->pluck('id'))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->orderBy('created_at')
            ->get();

        $scanSessionsById = $activeScanSessions->keyBy('id');
        $sessionOrders->each(function (Order $order) use ($scanSessionsById, $vendor) {
            $order->setRelation('tableScanSession', $scanSessionsById->get($order->table_scan_session_id));
            $order->setRelation('vendor', $vendor);
        });

        $takeawayQuery = $vendor->orders()
            ->whereNull('table_scan_session_id')
            ->where('order_type', '!=', 'dine-in')
            ->with([
                'customer:id,first_name,last_name,email,phone,customer_public_id',
                'payments:id,order_id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
                'coveredPayments:id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
            ])
            ->orderByDesc('created_at');

        if ($statusFilter) {
            $takeawayQuery->where('status', $statusFilter);
        }

        if ($orderTypeFilter) {
            $takeawayQuery->where('order_type', $orderTypeFilter);
        }

        $takeawayOrders = $takeawayQuery->get();
        $takeawayOrders->each(fn (Order $order) => $order->setRelation('vendor', $vendor));

        $allOrders = $sessionOrders->merge($takeawayOrders);
        $cartItemCache = $this->batchLoadLinkedCartItems($allOrders);
        $this->customizations->preloadSelectedModifiers($cartItemCache->flatten(1));

        $ordersByScanSession = $sessionOrders->groupBy('table_scan_session_id');

        $sessions = $activeScanSessions
            ->groupBy('restaurant_table_id')
            ->map(fn (Collection $group) => $this->formatTableScanSessionGroup($group, $ordersByScanSession, $cartItemCache))
            ->values();

        $takeaway = $takeawayOrders->map(fn (Order $order) => $this->formatOrder($order, $cartItemCache));

        return response()->json([
            'sessions' => $sessions,
            'takeaway' => $takeaway,
        ]);
    }

    /**
     * GET /api/vendor/{vendorId}/orders/history
     */
    public function history(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $perPage = min((int) ($request->query('perPage') ?? 20), 50);
        $dates = $request->validate([
            'dateFrom' => ['nullable', 'date_format:d.m.Y'],
            'dateTo' => ['nullable', 'date_format:d.m.Y'],
        ]);

        $timezone = $vendor->resolveTimezone();
        $dateFrom = isset($dates['dateFrom'])
            ? Carbon::createFromFormat('!d.m.Y', $dates['dateFrom'], $timezone)->startOfDay()
            : null;
        $dateTo = isset($dates['dateTo'])
            ? Carbon::createFromFormat('!d.m.Y', $dates['dateTo'], $timezone)->endOfDay()
            : null;

        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            throw ValidationException::withMessages([
                'dateTo' => ['The to date must be the same as or later than the from date.'],
            ]);
        }

        $query = $vendor->orders()
            ->with([
                'customer:id,first_name,last_name,email,phone,customer_public_id',
                'tableScanSession.restaurantTable:id,number,name',
                'payments:id,order_id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
                'coveredPayments:id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
            ])
            ->whereNotIn('status', ['draft'])
            ->orderByDesc('created_at');

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom->utc());
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo->utc());
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($orderType = $request->query('orderType')) {
            $query->where('order_type', $orderType);
        }
        if ($payment = $request->query('payment')) {
            if ($payment === 'paid') {
                $query->where('payment_received', true);
            } elseif ($payment === 'unpaid') {
                $query->where('payment_received', false)->where(function ($q) {
                    $q->where('payment_pending', false)->orWhereNull('payment_pending');
                });
            } elseif ($payment === 'pending-cash') {
                $query->where('payment_pending', true)->where('payment_method', 'cash')->where('payment_received', false);
            }
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('order_public_id', 'like', "%{$search}%");
            });
        }

        $paginated = $query->paginate($perPage);

        $orders = collect($paginated->items());
        $orders->each(fn (Order $order) => $order->setRelation('vendor', $vendor));
        $cartItemCache = $this->batchLoadLinkedCartItems($orders);
        $this->customizations->preloadSelectedModifiers($cartItemCache->flatten(1));

        return response()->json([
            'data' => $orders->map(fn (Order $order) => $this->formatOrder($order, $cartItemCache))->values(),
            'meta' => [
                'currentPage' => $paginated->currentPage(),
                'lastPage' => $paginated->lastPage(),
                'perPage' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/vendor/{vendorId}/orders/{orderId}
     */
    public function show(Request $request, string $vendorId, string $orderId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $order = $vendor->orders()
            ->where(function ($q) use ($orderId) {
                $q->where('order_public_id', $orderId)->orWhere('id', $orderId);
            })
            ->with([
                'customer:id,first_name,last_name,email,phone,customer_public_id',
                'tableScanSession.restaurantTable:id,number,name',
                'payments:id,order_id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
                'coveredPayments:id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
            ])
            ->firstOrFail();

        return response()->json($this->formatOrder($order));
    }

    /**
     * PATCH /api/orders/{orderId}
     */
    public function update(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId, $request);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:draft,confirmed,waiter_confirmed,in_progress,served,picked_up,cancelled'],
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

        $this->notifySessionCustomers($order, 'order_updated', 'Your order status has been updated.', [
            'template' => 'order.status_updated',
            'order_id' => $order->id,
            'order_snapshots' => [NotificationService::orderSnapshot($order->fresh()->load('paidBy'))],
        ]);
        $this->notifyOperations($request, $order, 'order_status_changed', 'Order status was updated.', silent: true);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/confirm
     * Waiter confirms a non-prepaid / pending order.
     */
    public function confirm(Request $request, string $orderId): JsonResponse
    {
        if ($queued = $this->queuedStaffCommand(
            $request,
            $this->staffCommands,
            'order.confirm',
            ['order_id' => $orderId],
            $this->staffCommandResourcesForOrderRoute($request, $orderId),
        )) {
            return $queued;
        }

        $order = $this->resolveOrder($orderId, $request);

        $order->update([
            'status' => 'waiter_confirmed',
            'waiter_confirmed' => true,
            'waiter_confirmed_at' => now(),
        ]);

        $this->notifySessionCustomers($order, 'order_updated', 'Your order has been confirmed by the waiter.', [
            'template' => 'order.waiter_confirmed',
            'order_id' => $order->id,
            'order_snapshots' => [NotificationService::orderSnapshot($order->fresh()->load('paidBy'))],
        ]);
        $this->notifyOperations(
            $request,
            $order,
            'order_confirmed',
            'A new order was confirmed.',
            template: 'staff.order_confirmed',
            sound: 'new_order',
        );

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/confirm-cash
     * Confirms cash payment received for an order.
     */
    public function confirmCashPayment(Request $request, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'paymentNote' => ['sometimes', 'nullable', 'string', 'max:500'],
            'tipAmount' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        if ($queued = $this->queuedStaffCommand(
            $request,
            $this->staffCommands,
            'order.confirm_cash',
            ['order_id' => $orderId, ...$data],
            $this->staffCommandResourcesForOrderRoute($request, $orderId),
        )) {
            return $queued;
        }

        $order = $this->resolveOrder($orderId, $request);

        $order->update([
            'payment_received' => true,
            'payment_confirmed_at' => now(),
            'payment_pending' => false,
            // This endpoint records an in-person cash collection regardless
            // of whether the customer previously selected another method.
            'payment_method' => 'cash',
            ...(array_key_exists('tipAmount', $data)
                ? ['tip_amount' => round((float) $data['tipAmount'], 2)]
                : []),
            ...(array_key_exists('paymentNote', $data)
                ? ['payment_note' => $data['paymentNote']]
                : []),
        ]);

        $statePatch = $this->statePatches->build('payment.cash_confirmed', [$order->id]);

        $this->notifySessionCustomers($order, 'payment_updated', 'Your cash payment has been confirmed.', [
            'template' => 'payment.cash_confirmed',
            'order_id' => $order->id,
            'order_snapshots' => [NotificationService::orderSnapshot($order->fresh()->load('paidBy'))],
            'state_patch' => $statePatch,
        ]);
        $this->notifyOperations(
            $request,
            $order,
            'payment_updated',
            'A cash payment was confirmed.',
            audiences: [NotificationService::VENDOR, NotificationService::WAITER],
            template: 'staff.payment_updated',
            sound: 'payment',
        );

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
        if ($queued = $this->queuedStaffCommand(
            $request,
            $this->staffCommands,
            'order.ready',
            ['order_id' => $orderId],
            $this->staffCommandResourcesForOrderRoute($request, $orderId),
        )) {
            return $queued;
        }

        $order = $this->resolveOrder($orderId, $request);

        $now = now();

        if ($order->table_scan_session_id) {
            CartItem::where('table_scan_session_id', $order->table_scan_session_id)
                ->update(['ready_at' => $now]);
        }

        CartItem::whereJsonContains('shared_order_ids', $order->id)
            ->update(['ready_at' => $now]);

        $order->update([
            'status' => 'in_progress',
            'in_progress_at' => $order->in_progress_at ?? $now,
        ]);

        $this->notifySessionCustomers($order, 'order_updated', 'Your order is ready!', [
            'template' => 'order.ready',
            'order_id' => $order->id,
            'order_snapshots' => [NotificationService::orderSnapshot($order->fresh()->load('paidBy'), true)],
        ]);
        $this->notifyOperations(
            $request,
            $order,
            'order_ready',
            'An order is ready.',
            template: 'staff.order_ready',
            severity: 'urgent',
            sound: 'ready',
        );

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/vendor/orders/{orderId}/items/{cartItemId}
     */
    public function updateItemStatus(Request $request, string $orderId, string $cartItemId): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:new,preparing,ready,served'],
        ]);

        $this->authorizeItemStatus($request, $data['status']);

        if ($queued = $this->queuedStaffCommand(
            $request,
            $this->staffCommands,
            'order.item_status',
            [
                'order_id' => $orderId,
                'cart_item_id' => $cartItemId,
                'status' => $data['status'],
            ],
            $this->staffCommandResourcesForOrderRoute($request, $orderId),
        )) {
            return $queued;
        }

        $order = $this->resolveOrder($orderId, $request);

        $transition = DB::transaction(function () use ($order, $cartItemId, $data) {
            $item = $this->linkedCartItemsQuery($order)
                ->whereKey($cartItemId)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                return ['outcome' => 'not_found'];
            }

            $currentStatus = $item->status();
            $currentRank = $this->itemStatusRank($currentStatus);
            $requestedRank = $this->itemStatusRank($data['status']);

            if ($requestedRank < $currentRank) {
                return [
                    'outcome' => 'conflict',
                    'current_status' => $currentStatus,
                ];
            }

            if ($requestedRank === $currentRank) {
                return [
                    'outcome' => 'unchanged',
                    'item' => $item,
                ];
            }

            $now = now();
            $updates = match ($data['status']) {
                'preparing' => [
                    'preparing_start_at' => $item->preparing_start_at ?? $now,
                ],
                'ready' => [
                    'preparing_start_at' => $item->preparing_start_at ?? $now,
                    'ready_at' => $item->ready_at ?? $now,
                ],
                'served' => [
                    'preparing_start_at' => $item->preparing_start_at ?? $now,
                    'ready_at' => $item->ready_at ?? $now,
                    'served_at' => $item->served_at ?? $now,
                ],
            };

            $item->update($updates);
            $this->syncOrderStatusFromCartItems($order);

            return [
                'outcome' => 'updated',
                'item' => $item,
            ];
        });

        if ($transition['outcome'] === 'not_found') {
            return response()->json(['message' => 'Cart item is not linked to this order.'], 404);
        }

        if ($transition['outcome'] === 'conflict') {
            return response()->json([
                'message' => 'Item status has already advanced.',
                'current_status' => $transition['current_status'],
                'requested_status' => $data['status'],
            ], 409);
        }

        if ($transition['outcome'] === 'unchanged') {
            return response()->json($this->formatOrder(
                $order->fresh()->load(['customer', 'tableScanSession.restaurantTable'])
            ));
        }

        /** @var CartItem $item */
        $item = $transition['item'];

        $template = match ($data['status']) {
            'preparing' => 'cart.item_preparing',
            'ready' => 'cart.item_ready',
            'served' => 'cart.item_served',
            default => 'cart.item_status_updated',
        };
        $statusLabel = match ($data['status']) {
            'preparing' => 'is now being prepared',
            'ready' => 'is ready',
            'served' => 'has been served',
            default => 'has been updated',
        };
        $itemName = $item->menuItem?->name ?? 'An item';
        // A shared item appears under every order in shared_order_ids — snapshot them all.
        $affectedOrderIds = collect([$order->id, $item->order_id])
            ->merge($item->shared_order_ids ?? [])
            ->filter()
            ->unique()
            ->values();
        $snapshots = Order::with('paidBy:id,first_name,last_name')
            ->whereIn('id', $affectedOrderIds)
            ->get()
            ->map(fn (Order $o) => NotificationService::orderSnapshot($o, true))
            ->values()->all();
        $this->notifySessionCustomers($order, 'cart_item_updated', "{$itemName} {$statusLabel}.", [
            'template' => $template,
            'order_id' => $order->id,
            'cart_item_id' => $item->id,
            'menu_item_id' => $item->menu_item_id,
            'item_name' => $itemName,
            'order_snapshots' => $snapshots,
        ]);
        $this->notifyOperations(
            $request,
            $order,
            'order_item_status_changed',
            "{$itemName} {$statusLabel}.",
            template: $data['status'] === 'ready' ? 'staff.item_ready' : null,
            severity: $data['status'] === 'ready' ? 'urgent' : 'info',
            sound: $data['status'] === 'ready' ? 'ready' : null,
            silent: $data['status'] !== 'ready',
            extra: [
                'cart_item_id' => $item->id,
                'menu_item_id' => $item->menu_item_id,
                'item_name' => $itemName,
                'item_status' => $data['status'],
            ],
        );

        return response()->json($this->formatOrder(
            $order->fresh()->load(['customer', 'tableScanSession.restaurantTable'])
        ));
    }

    /**
     * PATCH /api/vendor/orders/{orderId}/items/serve-ready
     * Marks multiple ready items served in one ordered staff command.
     */
    public function serveReadyItems(Request $request, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'cartItemIds' => ['required', 'array', 'min:1', 'max:50'],
            'cartItemIds.*' => ['required', 'integer', 'distinct:strict'],
        ]);

        $this->authorizeItemStatus($request, 'served');

        $cartItemIds = collect($data['cartItemIds'])
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($queued = $this->queuedStaffCommand(
            $request,
            $this->staffCommands,
            'order.items_serve_ready',
            [
                'order_id' => $orderId,
                'cartItemIds' => $cartItemIds,
            ],
            $this->staffCommandResourcesForOrderRoute($request, $orderId),
        )) {
            return $queued;
        }

        $order = $this->resolveOrder($orderId, $request);

        $transition = DB::transaction(function () use ($order, $cartItemIds): array {
            $items = $this->linkedCartItemsQuery($order)
                ->whereKey($cartItemIds)
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count($cartItemIds)) {
                return ['outcome' => 'not_found'];
            }

            $notReady = $items->first(function (CartItem $item): bool {
                return $this->itemStatusRank($item->status()) < $this->itemStatusRank(CartItem::STATUS_READY);
            });
            if ($notReady) {
                return [
                    'outcome' => 'conflict',
                    'cart_item_id' => (int) $notReady->id,
                    'current_status' => $notReady->status(),
                ];
            }

            $readyItemIds = $items
                ->filter(fn (CartItem $item) => $item->status() === CartItem::STATUS_READY)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            if ($readyItemIds->isEmpty()) {
                return [
                    'outcome' => 'unchanged',
                    'served_item_ids' => [],
                ];
            }

            CartItem::query()
                ->whereKey($readyItemIds)
                ->whereNull('served_at')
                ->update(['served_at' => now()]);

            $this->syncOrderStatusFromCartItems($order);

            return [
                'outcome' => 'updated',
                'served_item_ids' => $readyItemIds->all(),
            ];
        });

        if ($transition['outcome'] === 'not_found') {
            return response()->json(['message' => 'One or more cart items are not linked to this order.'], 404);
        }

        if ($transition['outcome'] === 'conflict') {
            return response()->json([
                'message' => 'All selected items must be ready before they can be served.',
                'cart_item_id' => $transition['cart_item_id'],
                'current_status' => $transition['current_status'],
            ], 409);
        }

        $order = $order->fresh()->load(['customer', 'tableScanSession.restaurantTable']);
        $servedItemIds = $transition['served_item_ids'];

        if ($servedItemIds !== []) {
            $affectedItems = CartItem::query()->whereKey($servedItemIds)->get();
            $affectedOrderIds = $affectedItems
                ->flatMap(fn (CartItem $item) => [
                    $order->id,
                    $item->order_id,
                    ...($item->shared_order_ids ?? []),
                ])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $snapshots = Order::with('paidBy:id,first_name,last_name')
                ->whereIn('id', $affectedOrderIds)
                ->get()
                ->map(fn (Order $affectedOrder) => NotificationService::orderSnapshot($affectedOrder, true))
                ->values()
                ->all();
            $statePatch = $this->statePatches->build(
                'order.items_served',
                $affectedOrderIds,
                $servedItemIds,
            );
            $servedCount = count($servedItemIds);

            $this->notifySessionCustomers(
                $order,
                'cart_items_updated',
                "{$servedCount} ready item".($servedCount === 1 ? ' was' : 's were').' served.',
                [
                    'template' => 'cart.items_served',
                    'order_id' => $order->id,
                    'cart_item_ids' => $servedItemIds,
                    'served_count' => $servedCount,
                    'order_snapshots' => $snapshots,
                    'state_patch' => $statePatch,
                ],
            );
            $this->notifyOperations(
                $request,
                $order,
                'order_items_status_changed',
                "{$servedCount} ready item".($servedCount === 1 ? ' was' : 's were').' served.',
                silent: true,
                extra: [
                    'cart_item_ids' => $servedItemIds,
                    'item_status' => 'served',
                    'served_count' => $servedCount,
                    'state_patch' => $statePatch,
                ],
            );
        }

        return response()->json($this->formatOrder($order));
    }

    /**
     * POST /api/vendor/orders/items/status-batch
     * Each item is an independent ordered/idempotent staff command.
     */
    public function batchItemStatus(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof TeamMember) {
            abort(403, 'Batch item status is only available to staff actors.');
        }

        $data = $request->validate([
            'commands' => ['required', 'array', 'min:1', 'max:50'],
            'commands.*.idempotency_key' => ['required', 'uuid', 'distinct:strict'],
            'commands.*.order_id' => ['required', 'string', 'max:64'],
            'commands.*.cart_item_id' => ['required', 'integer'],
            'commands.*.status' => ['required', 'string', 'in:new,preparing,ready,served'],
        ]);

        $prepared = [];
        foreach ($data['commands'] as $entry) {
            $this->authorizeItemStatus($request, $entry['status']);

            $prepared[] = [
                'idempotency_key' => strtolower($entry['idempotency_key']),
                'payload' => [
                    'order_id' => (string) $entry['order_id'],
                    'cart_item_id' => (string) $entry['cart_item_id'],
                    'status' => $entry['status'],
                ],
                'resources' => $this->staffCommandResourcesForOrderRoute($request, (string) $entry['order_id']),
            ];
        }

        if (! $this->staffCommands->enabled()) {
            return response()->json([
                'message' => 'Staff command processing is temporarily unavailable.',
                'code' => 'staff_commands_unavailable',
            ], 503);
        }

        $accepted = [];
        foreach ($prepared as $entry) {
            try {
                $status = $this->staffCommands->dispatch(
                    $actor,
                    $entry['idempotency_key'],
                    'order.item_status',
                    $entry['payload'],
                    $entry['resources'],
                    $request->header('Accept-Language'),
                );
                $accepted[] = $this->staffCommandAcceptedPayload($status);
            } catch (StaffCommandConflictException $exception) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'code' => 'idempotency_key_reused',
                    'command_id' => $exception->commandId,
                    'accepted_commands' => $accepted,
                ], 409);
            } catch (Throwable $exception) {
                report($exception);

                return response()->json([
                    'message' => 'Staff command processing is temporarily unavailable.',
                    'code' => 'staff_commands_unavailable',
                    'accepted_commands' => $accepted,
                ], 503);
            }
        }

        return response()->json(['commands' => $accepted], 202);
    }

    /**
     * PATCH /api/orders/{orderId}/picked-up
     */
    public function markPickedUp(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId, $request);

        $order->update([
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);

        $this->notifySessionCustomers($order, 'order_updated', 'Your order has been picked up.', [
            'template' => 'order.picked_up',
            'order_id' => $order->id,
            'order_snapshots' => [NotificationService::orderSnapshot($order->fresh()->load('paidBy'))],
        ]);
        $this->notifyOperations($request, $order, 'order_picked_up', 'An order was picked up.', silent: true);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/served
     */
    public function markServed(Request $request, string $orderId): JsonResponse
    {
        if ($queued = $this->queuedStaffCommand(
            $request,
            $this->staffCommands,
            'order.served',
            ['order_id' => $orderId],
            $this->staffCommandResourcesForOrderRoute($request, $orderId),
        )) {
            return $queued;
        }

        $order = $this->resolveOrder($orderId, $request);

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

        $this->notifySessionCustomers($order, 'order_updated', 'Your order has been served. Enjoy!', [
            'template' => 'order.served',
            'order_id' => $order->id,
            'order_snapshots' => [NotificationService::orderSnapshot($order->fresh()->load('paidBy'), true)],
        ]);
        $this->notifyOperations($request, $order, 'order_served', 'An order was served.', silent: true);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/cancel
     */
    public function cancel(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId, $request);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_reason' => $data['reason'] ?? null,
        ]);

        $this->notifySessionCustomers($order, 'order_updated', 'Your order has been cancelled.', [
            'template' => 'order.cancelled',
            'order_id' => $order->id,
            'order_snapshots' => [NotificationService::orderSnapshot($order->fresh()->load('paidBy'))],
        ]);
        $this->notifyOperations(
            $request,
            $order,
            'order_cancelled',
            'An order was cancelled.',
            template: 'staff.order_cancelled',
            severity: 'urgent',
        );

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * GET /api/vendor/{vendorId}/orders/{orderId}/receipt
     */
    public function receipt(Request $request, string $vendorId, string $orderId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $order = Order::with([
            'vendor.vendorSetting',
            'tableScanSession.restaurantTable:id,number,name',
            'paidBy:id,first_name,last_name',
        ])
            ->where('vendor_id', $vendor->id)
            ->where(fn (Builder $q) => $q->where('order_public_id', $orderId)
                ->when(ctype_digit($orderId), fn ($q2) => $q2->orWhere('id', $orderId)))
            ->firstOrFail();

        if (! $order->payment_received) {
            return response()->json(['message' => 'Receipt is only available for paid orders.'], 422);
        }

        $settings = $vendor->vendorSetting;
        $vendorCountry = $vendor->country ?? 'AT';
        $locale = $this->locales->dashboardLanguage($vendor);
        $countryCode = TaxCalculationService::countryCode($vendorCountry);
        $receiptLocale = 'en-'.$countryCode;

        $payment = OrderPayment::where('order_id', $order->id)
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->first();

        if (! $payment) {
            $payment = OrderPayment::whereHas(
                'orders',
                fn (Builder $q) => $q->where('orders.id', $order->id)
            )
                ->where('vendor_id', $vendor->id)
                ->whereNotNull('paid_at')
                ->latest('paid_at')
                ->first();
        }

        if ($payment) {
            $payment->load([
                'orders.tableScanSession.restaurantTable:id,number,name',
                'orders.paidBy:id,first_name,last_name',
            ]);
            $orders = $this->paymentCoveredOrders($payment);
        } else {
            $orders = collect([$order]);
        }

        $allItems = collect();
        $orderBlocks = $orders->map(function (Order $o) use (&$allItems, $vendorCountry, $vendor, $locale) {
            $items = $this->receiptLinkedCartItems($o);
            $allItems = $allItems->merge($items);

            return [
                'order_id' => $o->order_public_id,
                'order_number' => $o->order_number ?? $o->id,
                'paid_by' => $this->receiptPaidByPayload($o),
                'tip_amount' => round((float) ($o->tip_amount ?? 0), 2),
                'items' => $items
                    ->map(fn (CartItem $item) => $this->formatReceiptItem($item, $o, $vendorCountry, $vendor, $locale))
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        $uniqueItems = $allItems->unique('id')->values();
        $taxGroups = TaxCalculationService::computeTaxGroups($uniqueItems, $vendorCountry, true);
        $totals = TaxCalculationService::computeTotals($taxGroups, 0);

        $serviceFee = round((float) $orders->sum(fn (Order $o) => (float) ($o->service_fee ?? 0)), 2);
        $tipTotal = round((float) $orders->sum(fn (Order $o) => (float) ($o->tip_amount ?? 0)), 2);
        $totals['service_fee'] = $serviceFee;
        $totals['total_tips'] = $tipTotal;
        $totals['grand_total'] = round($totals['grand_total'] + $serviceFee + $tipTotal, 2);
        if ($payment) {
            $totals['amount_charged'] = round((float) $payment->amount, 2);
        }

        $anchor = $orders->first(fn (Order $o) => $payment && (int) $o->id === (int) $payment->order_id)
            ?? $orders->first();
        $table = $anchor->tableScanSession?->restaurantTable;
        $tableName = $table ? ($table->name ?? 'Table '.$table->number) : null;

        $taxGroupsFormatted = array_map(fn (array $group) => array_merge($group, [
            'tax_category' => strtoupper($group['tax_category']),
            'label' => $this->locales->translatedTaxCategoryName($group['tax_category'], $vendorCountry, $locale),
        ]), $taxGroups);

        $invoiceNumber = $this->resolveReceiptInvoiceNumber($anchor, $settings);
        $paymentDetails = $this->paymentMethods->details($payment, $order->payment_method);

        return response()->json([
            'data' => [
                'restaurant' => [
                    'name' => $vendor->restaurant_name,
                    'logo_url' => $this->media->url($settings?->logo_url),
                    'address' => $this->receiptFormatFullAddress($vendor),
                    'vat_id' => $vendor->vat_number,
                    'phone' => $vendor->phone,
                    'email' => $vendor->email,
                    'company_register_number' => $vendor->business_registration_number,
                ],
                'receipt' => [
                    'receipt_id' => $payment?->id,
                    'invoice_number' => $invoiceNumber,
                    'date' => $this->dateTimes->formatDate($payment?->paid_at ?? $anchor->created_at, $vendor),
                    'time' => $this->dateTimes->formatTime($payment?->paid_at ?? $anchor->created_at, $vendor),
                    'table' => $tableName,
                    'order_ids' => $orders->pluck('order_public_id')->values()->all(),
                    'currency' => $payment?->currency ?? $anchor->currency ?? $vendor->currency ?? 'EUR',
                    'locale' => $receiptLocale,
                ],
                'orders' => $orderBlocks,
                'tax_groups' => $taxGroupsFormatted,
                'totals' => $totals,
                'payment' => [
                    'provider' => $paymentDetails['provider'],
                    'method' => $paymentDetails['method'],
                    'method_details' => $paymentDetails,
                    'status' => 'CONFIRMED',
                    'transaction_id' => $payment?->stripe_payment_intent_id ?? $order->transaction_id,
                    'paid_at' => $this->dateTimes->formatDateTime(
                        $payment?->paid_at ?? $order->payment_confirmed_at,
                        $vendor,
                    ),
                ],
                'legal' => $this->receiptLegalBlock($countryCode, $vendor),
            ],
            'meta' => [
                'generated_at' => $this->dateTimes->formatDateTime(now(), $vendor),
                'template' => 'tavlo-receipt-template',
                'version' => '1.0',
            ],
        ]);
    }

    // ----------------------------------------------------------------
    // Session-level actions
    // ----------------------------------------------------------------

    /**
     * POST /api/vendor/{vendorId}/sessions/{sessionId}/release
     * [Legacy] Release batch to kitchen immediately (override the batch window).
     * Only operates on legacy table_sessions — not QR-scan sessions.
     */
    public function releaseToKitchen(Request $request, string $vendorId, string $sessionId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $session = $this->resolveSession($vendor, $sessionId);

        $session->update(['batch_released_at' => now()]);

        $session->load('orders.customer');

        return $this->legacyResponse($this->formatSession($session->fresh()));
    }

    /**
     * POST /api/vendor/{vendorId}/sessions/{sessionId}/fire-course
     * [Legacy] Advance to the next course.
     * Only operates on legacy table_sessions — not QR-scan sessions.
     */
    public function fireNextCourse(Request $request, string $vendorId, string $sessionId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $session = $this->resolveSession($vendor, $sessionId);

        $next = $session->nextCourse();

        if ($next === null) {
            return $this->legacyResponse(['message' => 'Already on the last course (desserts).'], 422);
        }

        $session->update(['current_course' => $next]);

        $session->load('orders.customer');

        return $this->legacyResponse($this->formatSession($session->fresh()));
    }

    /**
     * POST /api/vendor/{vendorId}/sessions/{sessionId}/close
     * [Legacy] Close the table session (end of visit).
     * Only operates on legacy table_sessions — not QR-scan sessions.
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

        return $this->legacyResponse($this->formatSession($session->fresh()));
    }

    private function legacyResponse(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('Deprecation', 'true')
            ->header('X-Deprecation-Notice', 'This endpoint operates on legacy table_sessions only. It has no effect on QR-scan sessions.');
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function formatTableScanSessionGroup(Collection $scanSessions, Collection $ordersByScanSession, ?Collection $cartItemCache = null): array
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
            'draft' => $orders->where('status', 'draft')->count(),
            'confirmed' => $orders->where('status', 'confirmed')->count(),
            'waiter_confirmed' => $orders->where('status', 'waiter_confirmed')->count(),
            'in_progress' => $orders->where('status', 'in_progress')->count(),
            'served' => $orders->where('status', 'served')->count(),
            'picked_up' => $orders->where('status', 'picked_up')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
        ];

        return [
            'sessionId' => 'table-'.($table?->id ?? $first->restaurant_table_id),
            'tableId' => $table ? (string) $table->id : (string) $first->restaurant_table_id,
            'sessionIds' => $sessionIds->map(fn (int $id) => (string) $id)->all(),
            'vendorId' => (string) $first->vendor_id,
            'tableNumber' => $table?->number,
            'tableName' => $table?->name,
            'pin' => $first->pin,
            'callWaiterRequested' => $table?->call_waiter_at !== null,
            'status' => 'active',
            'guestCount' => $scanSessions->count(),
            'totalAmount' => $totalAmount,
            'paidAmount' => $paidAmount,
            'paymentStatus' => $paymentStatus,
            'cashPending' => $hasCashPending,
            'closedAt' => null,
            'kitchenSummary' => $kitchenSummary,
            'orders' => $orders->map(fn (Order $order) => $this->formatOrder($order, $cartItemCache))->values(),
            'createdAt' => $scanSessions->min('scanned_at')?->toISOString() ?? $first->created_at?->toISOString(),
            'updatedAt' => $scanSessions->max('updated_at')?->toISOString() ?? $first->updated_at?->toISOString(),
        ];
    }

    private function formatSession(TableSession $session): array
    {
        $orders = $session->orders ?? collect();

        $kitchenSummary = [
            'total' => $orders->count(),
            'draft' => $orders->where('status', 'draft')->count(),
            'confirmed' => $orders->where('status', 'confirmed')->count(),
            'waiter_confirmed' => $orders->where('status', 'waiter_confirmed')->count(),
            'in_progress' => $orders->where('status', 'in_progress')->count(),
            'served' => $orders->where('status', 'served')->count(),
            'picked_up' => $orders->where('status', 'picked_up')->count(),
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

    private function formatOrder(Order $order, ?Collection $cartItemCache = null): array
    {
        $vendor = $order->relationLoaded('vendor') ? $order->vendor : ($order->vendor ?? null);
        $vendorCountry = $vendor?->country ?? 'AT';
        $locale = $vendor ? $this->locales->dashboardLanguage($vendor) : 'en';
        $serviceFeeRate = (float) ($vendor?->vendorSetting?->service_fee_rate ?? 0);
        $total = (float) $order->amount;

        $linkedItems = $cartItemCache ? ($cartItemCache->get($order->id) ?? collect()) : $this->loadLinkedCartItems($order);
        $itemsCount = (int) $linkedItems->sum('quantity');
        $readyAt = $linkedItems->isNotEmpty() && $linkedItems->every(fn (CartItem $ci) => $ci->ready_at !== null)
            ? $linkedItems->max(fn (CartItem $ci) => $ci->ready_at)
            : null;

        $taxGroups = TaxCalculationService::computeTaxGroups($linkedItems, $vendorCountry);
        $totals = TaxCalculationService::computeTotals($taxGroups, $serviceFeeRate);

        $rawStatus = $order->status;
        $displayStatus = match ($rawStatus) {
            'draft' => 'draft',
            'confirmed' => 'received',
            'picked_up' => 'picked-up',
            default => $rawStatus,
        };

        $pickupStatus = match ($rawStatus) {
            'picked_up' => 'picked-up',
            default => 'pending',
        };

        $timeline = [];
        if ($order->draft_at) {
            $timeline[] = ['status' => 'draft', 'timestamp' => $order->draft_at->toISOString()];
        }
        if ($order->confirmed_at) {
            $timeline[] = ['status' => 'confirmed', 'timestamp' => $order->confirmed_at->toISOString()];
        }
        if ($order->waiter_confirmed_at) {
            $timeline[] = ['status' => 'waiter_confirmed', 'timestamp' => $order->waiter_confirmed_at->toISOString()];
        }
        if ($order->in_progress_at) {
            $timeline[] = ['status' => 'in_progress', 'timestamp' => $order->in_progress_at->toISOString()];
        }
        if ($order->served_at) {
            $timeline[] = ['status' => 'served', 'timestamp' => $order->served_at->toISOString()];
        }
        if ($order->picked_up_at) {
            $timeline[] = ['status' => 'picked_up', 'timestamp' => $order->picked_up_at->toISOString()];
        }
        if ($order->cancelled_at) {
            $timeline[] = ['status' => 'cancelled', 'timestamp' => $order->cancelled_at->toISOString()];
        }

        $isDraftSession = $order->status === 'draft' && $order->table_scan_session_id;

        $items = $linkedItems->map(function (CartItem $ci) use ($order, $isDraftSession, $vendorCountry, $vendor, $locale) {
            $isOwner = $isDraftSession
                ? ($ci->table_scan_session_id == $order->table_scan_session_id && $ci->order_id === null)
                : ($ci->order_id == $order->id);

            $itemTaxCategory = $ci->menuItem?->tax_category ?? 'food';
            $unitPrice = $this->cartItemUnitPrice($ci, $vendorCountry);
            $lineTotal = round($unitPrice * $ci->quantity, 2);
            $vatRate = TaxCalculationService::itemVatRate($ci->menuItem, $vendorCountry);
            $orderIds = array_values(array_map('intval', is_array($ci->shared_order_ids) ? $ci->shared_order_ids : []));
            $sharedBetween = 1 + count($orderIds);
            $itemStatus = $ci->status();
            $paidAddons = $this->formatPaidAddons($ci, $itemTaxCategory, $vendorCountry, $vendor, $locale);
            $freeAddons = $this->formatNamedSelections($ci, 'free_addons', $vendor, $locale);
            $removedItems = $this->formatNamedSelections($ci, 'removed_items', $vendor, $locale);
            $selectedModifiers = $this->formatSelectedModifiers($ci, $itemTaxCategory, $vendorCountry, $vendor, $locale);
            $modifiers = $this->cartItemModifiers($ci, $vendorCountry, $vendor, $locale);

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
                'vatRate' => $vatRate,
                'vat_rate' => $vatRate,
                'taxCategory' => $itemTaxCategory,
                'tax_category' => $itemTaxCategory,
                'paidAddons' => $paidAddons,
                'paid_addons' => $paidAddons,
                'freeAddons' => $freeAddons,
                'free_addons' => $freeAddons,
                'removedItems' => $removedItems,
                'removed_items' => $removedItems,
                'selectedModifiers' => $selectedModifiers,
                'selected_modifiers' => $selectedModifiers,
                'modifiers' => $modifiers,
                'status' => $itemStatus,
                'sharedBetween' => $sharedBetween,
                'sharedWithOrderIds' => $orderIds,
                'isSharedCopy' => ! $isOwner,
                'is_shared_copy' => ! $isOwner,
                'receivedAt' => $ci->received_at?->toISOString(),
                'received_at' => $ci->received_at?->toISOString(),
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
        $payment = $this->orderPayment($order);
        $paymentDetails = $this->paymentMethods->vendorDetails($payment, $order->payment_method);

        return [
            'id' => (string) $order->id,
            'orderPublicId' => $order->order_public_id,
            'orderNumber' => $order->order_number ?? $order->id,
            'orderType' => $order->order_type ?? 'dine-in',
            'tableNumber' => $order->table_number ?? $table?->number,
            'tableId' => $table ? (string) $table->id : null,
            'tableScanSessionId' => $order->table_scan_session_id ? (string) $order->table_scan_session_id : null,
            'course' => $order->course,
            'placedBy' => $order->placed_by ?? ($order->customer_id ? 'customer' : 'waiter'),
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
            'tip' => (float) ($order->tip_amount ?? 0),
            'tipAmount' => (float) ($order->tip_amount ?? 0),
            'taxGroups' => $taxGroups,
            'tax_groups' => $taxGroups,
            'totals' => $totals,
            'currency' => $order->currency,
            'paymentMethod' => $order->payment_method,
            'paymentProvider' => $paymentDetails['provider'],
            'paymentMethodLabel' => $paymentDetails['displayName'],
            'paymentMethodDetails' => $paymentDetails,
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
    /**
     * Batch-load all cart items for a collection of orders in a single query.
     * Returns a Collection keyed by order ID, each value being the linked cart items.
     */
    private function batchLoadLinkedCartItems(Collection $orders): Collection
    {
        if ($orders->isEmpty()) {
            return collect();
        }

        $orderIds = $orders->pluck('id')->all();

        $draftSessionIds = $orders
            ->filter(fn (Order $o) => $o->status === 'draft' && $o->table_scan_session_id)
            ->pluck('table_scan_session_id')
            ->unique()
            ->all();

        $nonDraftOrderIds = $orders
            ->reject(fn (Order $o) => $o->status === 'draft' && $o->table_scan_session_id)
            ->pluck('id')
            ->all();

        $allItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,menu_category_id,vat_rate,tax_category,paid_addons,free_addons,removable_items', 'menuItem.category.masterCategory')
            ->where(function ($q) use ($nonDraftOrderIds, $draftSessionIds, $orderIds) {
                if (! empty($nonDraftOrderIds)) {
                    $q->orWhereIn('order_id', $nonDraftOrderIds);
                }
                if (! empty($draftSessionIds)) {
                    $q->orWhere(function ($sub) use ($draftSessionIds) {
                        $sub->whereIn('table_scan_session_id', $draftSessionIds)
                            ->whereNull('order_id');
                    });
                }
                foreach ($orderIds as $orderId) {
                    $q->orWhereJsonContains('shared_order_ids', $orderId);
                }
            })
            ->get();

        $cache = collect();
        foreach ($orders as $order) {
            $items = $allItems->filter(function (CartItem $ci) use ($order) {
                if ($order->status === 'draft' && $order->table_scan_session_id) {
                    if ($ci->table_scan_session_id == $order->table_scan_session_id && $ci->order_id === null) {
                        return true;
                    }
                } else {
                    if ($ci->order_id == $order->id) {
                        return true;
                    }
                }

                $shared = is_array($ci->shared_order_ids) ? $ci->shared_order_ids : [];

                return in_array($order->id, $shared);
            })->values();

            $cache->put($order->id, $items);
        }

        return $cache;
    }

    private function linkedCartItemsQuery(Order $order)
    {
        return CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,menu_category_id,vat_rate,tax_category,paid_addons,free_addons,removable_items', 'menuItem.category.masterCategory')
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
            });
    }

    private function loadLinkedCartItems(Order $order)
    {
        return $this->linkedCartItemsQuery($order)->get();
    }

    private function itemStatusRank(string $status): int
    {
        return match ($status) {
            CartItem::STATUS_NEW, CartItem::STATUS_RECEIVED => 0,
            CartItem::STATUS_PREPARING => 1,
            CartItem::STATUS_READY => 2,
            CartItem::STATUS_SERVED => 3,
            CartItem::STATUS_PICKED_UP => 4,
        };
    }

    private function cartItemUnitPrice(CartItem $item, string $vendorCountry = 'AT'): float
    {
        return TaxCalculationService::cartItemUnitPriceGross($item, $vendorCountry);
    }

    private function formatPaidAddons(CartItem $item, string $itemTaxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;

        if ($menuItem && $vendor) {
            return $this->customizations->formatPaidAddons($menuItem, $item->paid_addons ?? [], $vendor, $locale, $itemTaxCategory, $vendorCountry);
        }

        return collect($item->paid_addons ?? [])->map(function ($addon) use ($itemTaxCategory, $vendorCountry) {
            $addon = is_array($addon) ? $addon : [];
            $vatRate = TaxCalculationService::addonVatRate($addon, $itemTaxCategory, $vendorCountry);

            return [
                'id' => $addon['id'] ?? null,
                'name' => $addon['name'] ?? '',
                'price' => TaxCalculationService::gross((float) ($addon['price'] ?? 0), $vatRate),
                'vat_rate' => $vatRate,
            ];
        })->values()->all();
    }

    private function formatNamedSelections(CartItem $item, string $field, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;
        $selected = $field === 'free_addons'
            ? ($item->free_addons ?? [])
            : ($item->removed_items ?? []);

        if ($menuItem && $vendor) {
            $configured = $field === 'free_addons'
                ? ($menuItem->free_addons ?? [])
                : ($menuItem->removable_items ?? []);

            return $this->customizations->formatNamedSelections($configured, $selected, $vendor, $locale);
        }

        return collect($selected)->map(fn ($value) => is_array($value) ? (string) ($value['name'] ?? '') : (string) $value)
            ->filter()
            ->values()
            ->all();
    }

    private function formatSelectedModifiers(CartItem $item, string $itemTaxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        if ($vendor) {
            return $this->customizations->formatSelectedModifiers($item->selected_modifiers ?? [], $vendor, $locale, $itemTaxCategory, $vendorCountry);
        }

        return $item->selected_modifiers ?? [];
    }

    private function cartItemModifiers(CartItem $item, string $vendorCountry = 'AT', ?Vendor $vendor = null, string $locale = 'en'): array
    {
        $itemTaxCategory = $item->menuItem?->tax_category ?? 'food';

        $paidAddons = collect($this->formatPaidAddons($item, $itemTaxCategory, $vendorCountry, $vendor, $locale))
            ->map(fn (array $addon) => [
                'name' => (string) ($addon['name'] ?? ''),
                'price' => (float) ($addon['price'] ?? 0),
            ]);

        $freeAddons = collect($this->formatNamedSelections($item, 'free_addons', $vendor, $locale))
            ->map(fn ($name) => [
                'name' => (string) $name,
                'price' => 0.0,
            ]);

        $removedItems = collect($this->formatNamedSelections($item, 'removed_items', $vendor, $locale))
            ->map(fn ($name) => [
                'name' => 'No '.(string) $name,
                'price' => 0.0,
            ]);

        $selectedModifiers = collect($this->formatSelectedModifiers($item, $itemTaxCategory, $vendorCountry, $vendor, $locale))
            ->flatMap(fn ($group) => collect($group['options'] ?? [])
                ->map(fn ($option) => [
                    'name' => (string) ($option['name'] ?? ''),
                    'price' => (float) ($option['price_adjustment'] ?? 0),
                ]));

        return $paidAddons
            ->merge($freeAddons)
            ->merge($removedItems)
            ->merge($selectedModifiers)
            ->filter(fn (array $modifier) => $modifier['name'] !== '')
            ->values()
            ->all();
    }

    private function notifySessionCustomers(Order $order, string $event, string $message, array $metadata = []): void
    {
        if (! $order->table_scan_session_id) {
            return;
        }

        $session = $order->relationLoaded('tableScanSession')
            ? $order->tableScanSession
            : TableScanSession::find($order->table_scan_session_id);

        if ($session) {
            NotificationService::notifyTableCustomers($session->restaurant_table_id, $event, $message, $metadata, false);
        }
    }

    private function notifyOperations(
        Request $request,
        Order $order,
        string $event,
        string $message,
        ?array $audiences = null,
        ?string $template = null,
        string $severity = 'info',
        ?string $sound = null,
        bool $silent = false,
        array $extra = [],
    ): void {
        $order->loadMissing('tableScanSession.restaurantTable');
        $table = $order->tableScanSession?->restaurantTable;
        $actor = $request->user();

        NotificationService::notifyOperations(
            $order->vendor_id,
            $event,
            $message,
            $audiences ?? [
                NotificationService::VENDOR,
                NotificationService::WAITER,
                NotificationService::KITCHEN,
            ],
            [
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => $template,
                'order_id' => $order->id,
                'order_number' => $order->order_number ?? $order->id,
                'table_id' => $table?->id,
                'table_label' => $table?->name ?? $table?->number ?? $order->table_number ?? 'Takeaway',
                'severity' => $severity,
                'sound' => $sound,
                'source_actor_type' => $actor instanceof TeamMember ? 'team_member' : 'vendor',
                'source_actor_id' => $actor?->id,
                'order' => NotificationService::operationalOrderSnapshot($order),
                ...$extra,
            ],
            $silent,
        );
    }

    private function orderPayment(Order $order): ?OrderPayment
    {
        $payments = collect();

        if ($order->relationLoaded('payments')) {
            $payments = $payments->concat($order->payments);
        }

        if ($order->relationLoaded('coveredPayments')) {
            $payments = $payments->concat($order->coveredPayments);
        }

        return $payments
            ->unique('id')
            ->sortByDesc(fn (OrderPayment $payment) => $payment->paid_at?->getTimestamp() ?? $payment->id)
            ->first();
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::with('vendorSetting')
            ->where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();
    }

    private function resolveOrder(string $orderId, ?Request $request = null): Order
    {
        $query = Order::with([
            'vendor.vendorSetting',
            'tableScanSession.restaurantTable',
            'payments:id,order_id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
            'coveredPayments:id,stripe_payment_intent_id,status,payment_method,payment_method_details,paid_at',
        ]);

        if ($request) {
            $user = $request->user();
            $vendorId = $user instanceof TeamMember ? $user->vendor_id : $user->id;
            $query->where('vendor_id', $vendorId);
        }

        return $query->where(function ($q) use ($orderId) {
            $q->where('order_public_id', $orderId)->orWhere('id', $orderId);
        })->firstOrFail();
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

    /** @return array<int, string> */
    private function staffCommandResourcesForOrderRoute(Request $request, string $orderId): array
    {
        $actor = $request->user();
        $vendorId = $actor instanceof TeamMember ? $actor->vendor_id : $actor->id;

        return ["vendor:{$vendorId}:order:{$orderId}"];
    }

    private function syncOrderStatusFromCartItems(Order $order): void
    {
        $items = $this->loadLinkedCartItems($order);

        if ($items->isEmpty() || $order->status === 'cancelled') {
            return;
        }

        $now = now();

        if ($items->every(fn (CartItem $item) => $item->served_at !== null)) {
            $order->update([
                'status' => 'served',
                'served_at' => $order->served_at ?? $now,
            ]);

            return;
        }

        if ($items->contains(fn (CartItem $item) => $item->preparing_start_at !== null || $item->ready_at !== null)) {
            $order->update([
                'status' => 'in_progress',
                'in_progress_at' => $order->in_progress_at ?? $now,
            ]);

            return;
        }

        if (in_array($order->status, ['in_progress', 'served'], true)) {
            $order->update([
                'status' => $order->waiter_confirmed ? 'waiter_confirmed' : 'confirmed',
                'served_at' => null,
            ]);
        }
    }

    // ----------------------------------------------------------------
    // Receipt helpers
    // ----------------------------------------------------------------

    private function paymentCoveredOrders(OrderPayment $payment): Collection
    {
        if ($payment->orders->isNotEmpty()) {
            return collect($payment->orders->all())->values();
        }

        return $payment->order ? collect([$payment->order]) : collect();
    }

    private function receiptLinkedCartItems(Order $order): Collection
    {
        $items = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where(function (Builder $query) use ($order) {
                if ($order->status === 'draft' && $order->table_scan_session_id) {
                    $query->where('table_scan_session_id', $order->table_scan_session_id)
                        ->whereNull('order_id');
                } else {
                    $query->where('order_id', $order->id);
                }
                $query->orWhereJsonContains('shared_order_ids', $order->id);
            })
            ->orderBy('id')
            ->get();

        if ($items->isNotEmpty() || ! $order->table_scan_session_id || $order->status === 'draft') {
            return $items;
        }

        $orderedAt = $order->updated_at ?? $order->created_at;

        return CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where('table_scan_session_id', $order->table_scan_session_id)
            ->whereNull('order_id')
            ->when($orderedAt, fn (Builder $q) => $q->where('created_at', '<=', $orderedAt))
            ->orderBy('id')
            ->get();
    }

    private function formatReceiptItem(CartItem $item, Order $order, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->menuItem;
        $unitPrice = $this->cartItemUnitPrice($item, $vendorCountry);
        $lineGross = round($unitPrice * $item->quantity, 2);
        $taxCategory = $menuItem?->tax_category ?? 'food';
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);
        $vatAmount = TaxCalculationService::vatFromGross($lineGross, $vatRate);

        $sharedBetween = 1 + count(is_array($item->shared_order_ids) ? $item->shared_order_ids : []);
        $myShare = round($lineGross / $sharedBetween, 2);

        return [
            'id' => $item->id,
            'name' => $menuItem && $vendor
                ? $this->customizations->menuItemName($menuItem, $vendor, $locale)
                : $menuItem?->name,
            'quantity' => $item->quantity,
            'unit_price_gross' => $unitPrice,
            'line_gross' => $lineGross,
            'paid_addons' => $this->formatPaidAddons($item, $taxCategory, $vendorCountry, $vendor, $locale),
            'free_addons' => $this->formatNamedSelections($item, 'free_addons', $vendor, $locale),
            'removed_items' => $this->formatNamedSelections($item, 'removed_items', $vendor, $locale),
            'selected_modifiers' => $this->formatSelectedModifiers($item, $taxCategory, $vendorCountry, $vendor, $locale),
            'tax_category' => strtoupper($taxCategory),
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'is_mine' => (int) $item->order_id === (int) $order->id,
            'shared_between' => $sharedBetween,
            'my_share' => $myShare,
        ];
    }

    private function resolveReceiptInvoiceNumber(Order $order, $settings): string
    {
        if ($order->invoice_number) {
            return $order->invoice_number;
        }

        $prefix = $settings?->invoice_prefix ?? 'INV';

        $nextNumber = DB::table('vendor_settings')
            ->where('vendor_id', $order->vendor_id)
            ->lockForUpdate()
            ->value('next_invoice_number') ?? 1001;

        DB::table('vendor_settings')
            ->where('vendor_id', $order->vendor_id)
            ->increment('next_invoice_number');

        $invoiceNumber = $prefix.'-'.str_pad((string) $nextNumber, 7, '0', STR_PAD_LEFT);

        $order->update(['invoice_number' => $invoiceNumber]);

        return $invoiceNumber;
    }

    private function receiptPaidByPayload(Order $order): ?array
    {
        if (! $order->relationLoaded('paidBy')) {
            $order->load('paidBy:id,first_name,last_name');
        }

        if (! $order->paidBy) {
            return null;
        }

        return [
            'id' => $order->paidBy->id,
            'name' => trim(($order->paidBy->first_name ?? '').' '.($order->paidBy->last_name ?? '')) ?: 'Guest',
        ];
    }

    private function receiptFormatFullAddress($vendor): ?string
    {
        if (! $vendor) {
            return null;
        }

        $parts = array_filter([
            $vendor->address,
            $vendor->city,
            $vendor->country,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    private function receiptLegalBlock(string $countryCode, $vendor): array
    {
        $regNumber = $vendor?->business_registration_number;

        $legalNotes = match ($countryCode) {
            'AT' => [
                'invoice_note' => 'This invoice was issued in accordance with § 11 UStG (Austria).',
                'tax_note' => 'All prices include statutory VAT. The service date corresponds to the invoice date.',
            ],
            'DE' => [
                'invoice_note' => 'This invoice was issued in accordance with § 14 UStG (Germany).',
                'tax_note' => 'All prices include statutory VAT. The service date corresponds to the invoice date.',
            ],
            'GB' => [
                'invoice_note' => 'This invoice was issued in accordance with UK VAT regulations.',
                'tax_note' => 'All prices include VAT where applicable.',
            ],
            default => [
                'invoice_note' => 'This invoice was issued in accordance with applicable tax regulations.',
                'tax_note' => 'All prices include statutory VAT where applicable.',
            ],
        };

        return [
            'invoice_note' => $legalNotes['invoice_note'],
            'tax_note' => $legalNotes['tax_note'],
            'company_register_note' => $regNumber
                ? 'Registration number: '.$regNumber
                : null,
            'rksv_required_check' => $countryCode === 'AT',
        ];
    }
}
