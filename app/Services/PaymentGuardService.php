<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PaymentGuardService
{
    public const TERMINAL_PAYMENT_STATUSES = ['succeeded', 'canceled', 'failed', 'payment_failed'];

    /**
     * Non-terminal payments (Stripe intents or cash requests) covering any of
     * the given orders — either as the payment's anchor order or through the
     * order_payment_orders pivot. While such a payment exists, the covered
     * orders are locked: they cannot be claimed, released, shared or unshared.
     *
     * @param  iterable<int>  $orderIds
     */
    public static function activePaymentsCovering(iterable $orderIds): EloquentCollection
    {
        $ids = collect($orderIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return new EloquentCollection;
        }

        return OrderPayment::whereNotIn('status', self::TERMINAL_PAYMENT_STATUSES)
            ->where(function (Builder $query) use ($ids) {
                $query->whereIn('order_id', $ids)
                    ->orWhereHas('orders', fn (Builder $covered) => $covered->whereIn('orders.id', $ids));
            })
            ->get();
    }

    /**
     * Check an order set and any side orders belonging to the supplied
     * parents in one query. This avoids first loading child ids solely to run
     * the payment-lock query.
     *
     * @param  iterable<int>  $orderIds
     * @param  iterable<int>  $parentOrderIds
     */
    public static function hasActivePaymentCoveringGraph(
        iterable $orderIds,
        iterable $parentOrderIds = [],
    ): bool {
        $ids = collect($orderIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $parents = collect($parentOrderIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty() && $parents->isEmpty()) {
            return false;
        }

        $coveredOrderIds = Order::query()
            ->select('id')
            ->where(function (Builder $query) use ($ids, $parents) {
                if ($ids->isNotEmpty()) {
                    $query->whereIn('id', $ids);
                }

                if ($parents->isNotEmpty()) {
                    $method = $ids->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('parent_order_id', $parents);
                }
            });

        return OrderPayment::whereNotIn('status', self::TERMINAL_PAYMENT_STATUSES)
            ->where(function (Builder $query) use ($coveredOrderIds) {
                $query->whereIn('order_id', clone $coveredOrderIds)
                    ->orWhereHas('orders', fn (Builder $covered) => $covered->whereIn(
                        'orders.id',
                        clone $coveredOrderIds,
                    ));
            })
            ->exists();
    }

    /**
     * True once somebody has committed to paying for an order, so its cart
     * items may no longer be added to, edited or removed: another guest covers
     * it, a payment is in progress, or it is already settled.
     */
    public static function orderIsCartLocked(Order $order): bool
    {
        return (bool) $order->payment_received
            || (bool) $order->payment_pending
            || $order->paid_by !== null;
    }

    /**
     * Freeze a draft order's claim on its session's cart items.
     *
     * A draft owns every unassigned cart item in its session implicitly, so
     * without this a guest's later additions would silently join an order
     * somebody is already paying for — and adding the same menu item again
     * would increment a line that is being paid. Binding the current items to
     * the order at lock time fixes the claim: anything added afterwards stays
     * unassigned and is picked up by the next draft instead.
     *
     * Only drafts need this; a confirmed order already owns its items outright.
     */
    public static function freezeDraftItems(Order $order): void
    {
        if ($order->status !== 'draft' || ! $order->table_scan_session_id) {
            return;
        }

        CartItem::where('table_scan_session_id', $order->table_scan_session_id)
            ->whereNull('order_id')
            ->update(['order_id' => $order->id]);
    }

    /**
     * Release a draft's items back into the open pool when its lock is lifted —
     * the coverage was released, or the payment was cancelled. Without this the
     * items stay bound and a repeat of the same menu item would stack as a
     * second line instead of merging, long after the lock is gone.
     *
     * Items already sent to the kitchen keep their order: they are submitted,
     * not merely locked.
     */
    public static function thawDraftItems(Order $order): void
    {
        if ($order->status !== 'draft') {
            return;
        }

        CartItem::where('order_id', $order->id)
            ->whereNull('received_at')
            ->update(['order_id' => null]);
    }
}
