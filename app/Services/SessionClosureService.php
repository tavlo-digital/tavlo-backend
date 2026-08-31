<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Whether a set of scan sessions may be closed, and why not.
 *
 * The rule lived inline in TableScanController::close, which is reachable only
 * from inside the ordering flow. The sessions list in account settings needs
 * the same answer — both to disable its close button with a reason and to
 * enforce it on the way in — so the predicate lives here and both call it.
 * Two copies of "can this table be closed" is how a guest ends up able to close
 * a table from one screen that the other screen refuses.
 */
class SessionClosureService
{
    /**
     * Why $sessionIds cannot be closed, or null when they can.
     *
     * Dine-in and off-premise differ: a table also has to have handed over
     * everything it was paid for, whereas a pickup group is done once nothing
     * is outstanding.
     *
     * @param  iterable<int|string>  $sessionIds
     */
    public function blockingReason(iterable $sessionIds, bool $isOffPremise): ?string
    {
        $ids = collect($sessionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return null;
        }

        if ($isOffPremise) {
            $hasUnpaid = Order::query()
                ->whereIn('table_scan_session_id', $ids)
                ->where('status', '!=', Order::STATUS_CANCELLED)
                ->where('payment_received', false)
                ->exists();

            return $hasUnpaid ? 'This order group still has unpaid orders.' : null;
        }

        // A draft was never sent to the kitchen, and a cancelled order is not
        // owed — neither holds the table open.
        $orders = Order::query()
            ->whereIn('table_scan_session_id', $ids)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_DRAFT])
            ->get();

        if ($orders->contains(fn (Order $order) => ! $order->payment_received)) {
            return 'There is an active order on this table.';
        }

        $paidOrderIds = $orders->where('payment_received', true)->pluck('id')->values();

        if ($paidOrderIds->isNotEmpty() && $this->hasUnservedItems($paidOrderIds)) {
            return 'All the items on table are not served.';
        }

        return null;
    }

    /**
     * An item counts as outstanding whether it belongs to one of these orders
     * or was only shared into it — a split starter nobody has carried out yet
     * still keeps the table open.
     *
     * @param  Collection<int, int>  $orderIds
     */
    private function hasUnservedItems(Collection $orderIds): bool
    {
        return CartItem::query()
            ->where(function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds->all());

                foreach ($orderIds as $orderId) {
                    $query->orWhereJsonContains('shared_order_ids', $orderId);
                }
            })
            ->whereNull('served_at')
            ->exists();
    }
}
