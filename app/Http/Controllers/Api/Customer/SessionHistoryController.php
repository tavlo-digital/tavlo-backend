<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Services\OrderSessionService;
use App\Services\SessionClosureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The customer's own scan sessions, past and present.
 *
 * Deliberately unfiltered: every session row this customer owns, with every
 * order on it whatever its status, and — where a session never got as far as an
 * order — the cart rows they had added. This is a history view, so it reports
 * what the database holds rather than what is payable or served.
 */
class SessionHistoryController extends Controller
{
    public function __construct(
        private readonly OrderSessionService $sessions,
        private readonly SessionClosureService $closures,
    ) {}

    /**
     * GET /api/customer/sessions
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = (int) $request->user()->id;

        $sessions = TableScanSession::query()
            ->with(['vendor:id,vendor_public_id,name,restaurant_name,slug', 'restaurantTable:id,number'])
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->get();

        if ($sessions->isEmpty()) {
            return response()->json(['sessions' => []]);
        }

        $sessionIds = $sessions->pluck('id');

        $orders = Order::query()
            ->whereIn('table_scan_session_id', $sessionIds)
            ->orderBy('id')
            ->get()
            ->groupBy('table_scan_session_id');

        $items = CartItem::query()
            ->with('menuItem:id,name')
            ->whereIn('table_scan_session_id', $sessionIds)
            ->orderBy('id')
            ->get()
            ->groupBy('table_scan_session_id');

        return response()->json([
            'sessions' => $sessions
                ->map(fn (TableScanSession $session) => $this->sessionPayload(
                    $session,
                    collect($orders->get($session->id) ?? []),
                    collect($items->get($session->id) ?? []),
                ))
                ->values()
                ->all(),
        ]);
    }

    /**
     * POST /api/customer/sessions/{session}/close
     *
     * Closes the whole group the session belongs to, exactly as closing from
     * inside the ordering flow does, and under the same rules.
     */
    public function close(Request $request, int $session): JsonResponse
    {
        $customerId = (int) $request->user()->id;

        $target = TableScanSession::query()
            ->where('id', $session)
            ->where('customer_id', $customerId)
            ->first();

        if (! $target) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        if ($target->status !== 'active') {
            return response()->json(['message' => 'This session is already closed.'], 422);
        }

        $groupSessionIds = $this->sessions->groupSessionIds($target);
        $isOffPremise = $this->sessions->isOffPremise($target);

        if ($reason = $this->closures->blockingReason($groupSessionIds, $isOffPremise)) {
            return response()->json(['message' => $reason], 422);
        }

        TableScanSession::query()
            ->whereIn('id', $groupSessionIds)
            ->update(['status' => 'closed', 'closed_at' => now()]);

        return response()->json([
            'message' => 'Session closed.',
            'closed_session_ids' => collect($groupSessionIds)->map(fn ($id) => (int) $id)->values()->all(),
        ]);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, CartItem>  $items
     * @return array<string, mixed>
     */
    private function sessionPayload(TableScanSession $session, Collection $orders, Collection $items): array
    {
        $isOffPremise = $this->sessions->isOffPremise($session);
        $isActive = $session->status === 'active';

        // Only an open session can be closed, so the rules are evaluated only
        // then — running them over closed history would be wasted queries.
        $blockingReason = $isActive
            ? $this->closures->blockingReason($this->sessions->groupSessionIds($session), $isOffPremise)
            : null;

        $boundItemIds = $orders->pluck('id');

        // Rows the guest added that never made it onto an order. For a session
        // that ended before anything was placed, this is the whole story.
        $unplacedItems = $items->filter(
            fn (CartItem $item) => $item->order_id === null || ! $boundItemIds->contains($item->order_id)
        );

        return [
            'id' => (int) $session->id,
            'status' => $session->status,
            'type' => $session->type,
            'is_off_premise' => $isOffPremise,
            'pin' => $session->pin,
            'table_number' => $session->restaurantTable?->number,
            'vendor' => $session->vendor ? [
                'vendor_public_id' => $session->vendor->vendor_public_id,
                'slug' => $session->vendor->slug,
                'name' => $session->vendor->restaurant_name ?: $session->vendor->name,
            ] : null,
            'scanned_at' => $session->scanned_at?->toISOString(),
            'closed_at' => $session->closed_at?->toISOString(),
            'scheduled_for' => $session->scheduled_for?->toISOString(),
            'can_close' => $isActive && $blockingReason === null,
            'close_blocked_reason' => $blockingReason,
            'order_count' => $orders->count(),
            'item_count' => $items->sum(fn (CartItem $item) => (int) $item->quantity),
            'orders' => $orders->map(fn (Order $order) => [
                'id' => (int) $order->id,
                'order_public_id' => $order->order_public_id,
                'status' => $order->status(),
                'amount' => (float) $order->amount,
                'tip_amount' => (float) ($order->tip_amount ?? 0),
                'currency' => $order->currency,
                'payment_received' => (bool) $order->payment_received,
                'payment_pending' => (bool) $order->payment_pending,
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at?->toISOString(),
                'items' => $this->itemPayloads($items->where('order_id', $order->id)),
            ])->values()->all(),
            'unplaced_items' => $this->itemPayloads($unplacedItems),
        ];
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function itemPayloads(Collection $items): array
    {
        return $items->map(fn (CartItem $item) => [
            'cart_item_id' => (int) $item->id,
            // The menu item may have been deleted since; the row is still part
            // of this session's history and must not vanish from it.
            'name' => $item->menuItem?->name,
            'quantity' => (int) $item->quantity,
            'notes' => $item->notes,
            'status' => $item->status(),
            'served_at' => $item->served_at?->toISOString(),
        ])->values()->all();
    }
}
