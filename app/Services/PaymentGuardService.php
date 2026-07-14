<?php

namespace App\Services;

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
     * @param iterable<int> $orderIds
     */
    public static function activePaymentsCovering(iterable $orderIds): EloquentCollection
    {
        $ids = collect($orderIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return new EloquentCollection();
        }

        return OrderPayment::whereNotIn('status', self::TERMINAL_PAYMENT_STATUSES)
            ->where(function (Builder $query) use ($ids) {
                $query->whereIn('order_id', $ids)
                    ->orWhereHas('orders', fn (Builder $covered) => $covered->whereIn('orders.id', $ids));
            })
            ->get();
    }
}
