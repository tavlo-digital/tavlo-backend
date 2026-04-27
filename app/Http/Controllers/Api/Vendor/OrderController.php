<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TableSession;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/orders
     * Returns orders grouped by table session (dine-in) and flat list (takeaway).
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $statusFilter    = $request->query('status');
        $orderTypeFilter = $request->query('orderType');

        // Dine-in: grouped by active sessions
        $sessionsQuery = $vendor->tableSessions()
            ->where('status', 'active')
            ->with([
                'orders' => function ($q) use ($statusFilter) {
                    $q->with('customer:id,name,email,phone,customer_public_id')
                      ->orderBy('created_at');
                    if ($statusFilter) {
                        $q->where('status', $statusFilter);
                    }
                },
            ]);

        $sessions = $sessionsQuery->get()->map(fn (TableSession $session) => $this->formatSession($session));

        // Takeaway / non-session orders
        $takeawayQuery = $vendor->orders()
            ->whereNull('table_scan_session_id')
            ->where('order_type', '!=', 'dine-in')
            ->with('customer:id,name,email,phone,customer_public_id')
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

        $order = $vendor->orders()
            ->where(function ($q) use ($orderId) {
                $q->where('order_public_id', $orderId)->orWhere('id', $orderId);
            })
            ->with('customer:id,name,email,phone,customer_public_id')
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
        if (isset($data['status'])) $mapped['status'] = $data['status'];
        if (isset($data['paymentPending'])) $mapped['payment_pending'] = $data['paymentPending'];
        if (isset($data['paymentReceived'])) {
            $mapped['payment_received'] = $data['paymentReceived'];
            if ($data['paymentReceived']) {
                $mapped['payment_confirmed_at'] = now();
            }
        }
        if (array_key_exists('paymentNote', $data)) $mapped['payment_note'] = $data['paymentNote'];

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
            'status'              => 'confirmed',
            'waiter_confirmed'    => true,
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
            'payment_received'     => true,
            'payment_confirmed_at' => now(),
            'payment_pending'      => false,
        ]);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/ready
     */
    public function markReady(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $order->update([
            'status'   => 'ready',
            'ready_at' => now(),
        ]);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/picked-up
     */
    public function markPickedUp(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $order->update([
            'status'       => 'picked_up',
            'picked_up_at' => now(),
        ]);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/served
     */
    public function markServed(Request $request, string $orderId): JsonResponse
    {
        $order = $this->resolveOrder($orderId);
        $this->authorizeVendor($request, $order->vendor);

        $order->update([
            'status'    => 'served',
            'served_at' => now(),
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
            'status'           => 'cancelled',
            'cancelled_at'     => now(),
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
        $vendor  = $this->resolveVendor($vendorId);
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
        $vendor  = $this->resolveVendor($vendorId);
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
        $vendor  = $this->resolveVendor($vendorId);
        $session = $this->resolveSession($vendor, $sessionId);

        $session->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        $session->load('orders.customer');
        return response()->json($this->formatSession($session->fresh()));
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function formatSession(TableSession $session): array
    {
        $orders = $session->orders ?? collect();

        $kitchenSummary = [
            'total'     => $orders->count(),
            'pending'   => $orders->where('status', 'pending')->count(),
            'confirmed' => $orders->where('status', 'confirmed')->count(),
            'preparing' => $orders->where('status', 'preparing')->count(),
            'ready'     => $orders->where('status', 'ready')->count(),
            'served'    => $orders->where('status', 'served')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
        ];

        return [
            'sessionId'             => (string) $session->id,
            'vendorId'              => (string) $session->vendor_id,
            'tableNumber'           => $session->table_number,
            'tableName'             => $session->table_name,
            'status'                => $session->status,
            'currentCourse'         => $session->current_course,
            'batchStartedAt'        => $session->batch_started_at?->toISOString(),
            'batchWindowSeconds'    => $session->batch_window_seconds,
            'batchReleasedAt'       => $session->batch_released_at?->toISOString(),
            'batchOpen'             => $session->isBatchOpen(),
            'batchSecondsRemaining' => $session->batchSecondsRemaining(),
            'totalAmount'           => $session->totalAmount(),
            'closedAt'              => $session->closed_at?->toISOString(),
            'kitchenSummary'        => $kitchenSummary,
            'orders'                => $orders->map(fn (Order $o) => $this->formatOrder($o))->values(),
            'createdAt'             => $session->created_at->toISOString(),
            'updatedAt'             => $session->updated_at->toISOString(),
        ];
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id'                 => (string) $order->id,
            'orderPublicId'      => $order->order_public_id,
            'orderNumber'        => $order->order_number ?? "#{$order->id}",
            'orderType'          => $order->order_type ?? 'dine-in',
            'tableNumber'        => $order->table_number,
            'tableScanSessionId' => $order->table_scan_session_id ? (string) $order->table_scan_session_id : null,
            'course'             => $order->course,
            'guestCount'         => $order->guest_count,
            'waiterConfirmed'    => (bool) $order->waiter_confirmed,
            'waiterConfirmedAt'  => $order->waiter_confirmed_at?->toISOString(),
            'customer' => $order->customer ? [
                'id'    => (string) $order->customer->id,
                'name'  => $order->customer->name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
            ] : null,
            'status'             => $order->status,
            'itemsCount'         => $order->items_count,
            'items'              => $order->items ?? [],
            'amount'             => (float) $order->amount,
            'serviceFee'         => (float) ($order->service_fee ?? 0),
            'vatAmount'          => (float) ($order->vat_amount ?? 0),
            'currency'           => $order->currency,
            'paymentMethod'      => $order->payment_method,
            'paymentPending'     => (bool) $order->payment_pending,
            'paymentReceived'    => (bool) $order->payment_received,
            'paymentConfirmedAt' => $order->payment_confirmed_at?->toISOString(),
            'paymentNote'        => $order->payment_note,
            'readyAt'            => $order->ready_at?->toISOString(),
            'pickedUpAt'         => $order->picked_up_at?->toISOString(),
            'servedAt'           => $order->served_at?->toISOString(),
            'cancelledAt'        => $order->cancelled_at?->toISOString(),
            'cancelledReason'    => $order->cancelled_reason,
            'createdAt'          => $order->created_at->toISOString(),
            'updatedAt'          => $order->updated_at->toISOString(),
        ];
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
            ->with('vendor')
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
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
