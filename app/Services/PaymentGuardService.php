<?php

namespace App\Services;

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
}
