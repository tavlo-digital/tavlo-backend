<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The claim a customer takes on the orders they are about to settle.
 *
 * Reaching the payment step used to change nothing on the server until a
 * Stripe intent existed, so a cash checkout locked nothing at all: a second
 * guest could still cover the same order, or split one of its items, while the
 * payer sat looking at a total that had already stopped being true.
 *
 * A hold closes that window. It sets `payment_pending`, which every existing
 * guard and lock banner already understands, and records who holds it and
 * since when so it can be released by its owner — and only its owner — and
 * expired if they walk away from the checkout without going back.
 */
class CheckoutHoldService
{
    public function __construct(
        private readonly EditableOrderMergeService $editableOrderMerges,
    ) {}

    /**
     * How long a hold survives without the payer finishing or releasing it.
     *
     * Long enough to read a total and count out cash, short enough that a shut
     * laptop does not strand the rest of the table. Matches the window
     * payments:reconcile-stale uses for an abandoned Stripe intent.
     */
    public const TTL_MINUTES = 10;

    /**
     * Claim every order in $orders for $customerId.
     *
     * @param  iterable<int, Order>  $orders
     * @return array<int, int> the order ids now held
     */
    public function hold(int $customerId, iterable $orders): array
    {
        $heldIds = [];
        $now = now();

        foreach ($orders as $order) {
            $order->forceFill([
                'payment_pending' => true,
                'checkout_hold_by' => $customerId,
                'checkout_hold_at' => $now,
            ])->save();

            // Marking a draft pending stops it implicitly owning its session's
            // unassigned items, so without binding them the lines would drop
            // out of the order the payer is standing on — off its item list and
            // out of its total. Every other path that sets payment_pending
            // freezes for the same reason.
            PaymentGuardService::freezeDraftItems($order);

            $heldIds[] = (int) $order->id;
        }

        return $heldIds;
    }

    /**
     * Drop $customerId's hold on every order in $orders.
     *
     * An order held by somebody else is left alone, and so is one whose
     * payment_pending came from a real payment rather than a hold — releasing
     * that would unlock an order mid-charge.
     *
     * @param  iterable<int, Order>  $orders
     * @return array<int, int> the order ids released
     */
    public function release(int $customerId, iterable $orders): array
    {
        $releasedIds = [];

        foreach ($orders as $order) {
            if (! $this->isHeldBy($order, $customerId)) {
                continue;
            }

            // A payment started while the hold stood owns payment_pending from
            // here on, and a cash request is exactly that: it carries no Stripe
            // intent for the checkout "back" path to cancel, so dropping the
            // flag here would unlock an order the waiter is walking over to
            // collect on. Give up the hold, leave the lock to the payment.
            $stillPaying = $order->payments()
                ->whereNotIn('status', PaymentGuardService::TERMINAL_PAYMENT_STATUSES)
                ->exists()
                || PaymentGuardService::activePaymentsCovering([(int) $order->id])->isNotEmpty();

            $order->forceFill([
                'payment_pending' => $stillPaying,
                'checkout_hold_by' => null,
                'checkout_hold_at' => null,
            ])->save();

            if ($stillPaying) {
                continue;
            }

            $releasedIds[] = (int) $order->id;
        }

        return $releasedIds;
    }

    /**
     * Clear a hold regardless of who owns it — for the paths that supersede it:
     * a payment completing, failing, or being cancelled.
     */
    public function clear(Order $order): void
    {
        if ($order->checkout_hold_by === null && $order->checkout_hold_at === null) {
            return;
        }

        $order->forceFill([
            'checkout_hold_by' => null,
            'checkout_hold_at' => null,
        ])->save();
    }

    /** The customer holding $order right now, or null when free or expired. */
    public function holderId(Order $order): ?int
    {
        if (! $order->checkout_hold_by || ! $order->checkout_hold_at) {
            return null;
        }

        return $this->isExpired($order->checkout_hold_at) ? null : (int) $order->checkout_hold_by;
    }

    public function isHeldBy(Order $order, int $customerId): bool
    {
        return $this->holderId($order) === $customerId;
    }

    public function isHeldByAnyoneElse(Order $order, int $customerId): bool
    {
        $holder = $this->holderId($order);

        return $holder !== null && $holder !== $customerId;
    }

    /**
     * Orders in $orderIds another customer is checking out right now.
     *
     * @param  iterable<int, int|string>  $orderIds
     * @return EloquentCollection<int, Order>
     */
    public function heldByOthers(iterable $orderIds, int $customerId): EloquentCollection
    {
        $ids = collect($orderIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return new EloquentCollection;
        }

        return Order::whereIn('id', $ids)
            ->whereNotNull('checkout_hold_by')
            ->where('checkout_hold_by', '!=', $customerId)
            ->where('checkout_hold_at', '>=', $this->staleBefore())
            ->get();
    }

    /**
     * Whether $customerId is standing on the payment step right now.
     *
     * Their total is built from which of their orders they still owe and which
     * somebody else covers, so changing the coverage on any of them moves the
     * number they are looking at — even the orders they are not paying for
     * themselves, which is why holding the payable set alone is not enough.
     *
     * @param  iterable<int, int>  $sessionIds
     */
    public function customerIsCheckingOut(int $customerId, iterable $sessionIds): bool
    {
        $ids = collect($sessionIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return false;
        }

        return Order::whereIn('table_scan_session_id', $ids)
            ->where('checkout_hold_by', $customerId)
            ->where('checkout_hold_at', '>=', $this->staleBefore())
            ->exists();
    }

    /**
     * Release every hold that has outlived its TTL, so an abandoned checkout
     * cannot lock a table indefinitely.
     *
     * @return int how many orders were freed
     */
    public function releaseExpired(): int
    {
        $expired = Order::whereNotNull('checkout_hold_by')
            ->where('checkout_hold_at', '<', $this->staleBefore())
            ->get();

        $sessionIds = collect();

        foreach ($expired as $order) {
            $stillPaying = $order->payments()->whereNotIn(
                'status',
                PaymentGuardService::TERMINAL_PAYMENT_STATUSES,
            )->exists();

            $order->forceFill([
                // Only the hold is undone. A payment that started in the
                // meantime owns payment_pending and keeps it.
                'payment_pending' => $stillPaying,
                'checkout_hold_by' => null,
                'checkout_hold_at' => null,
            ])->save();

            if (! $stillPaying && $order->table_scan_session_id) {
                $sessionIds->push((int) $order->table_scan_session_id);
            }
        }

        foreach ($sessionIds->unique() as $sessionId) {
            $this->editableOrderMerges->mergeForSession((int) $sessionId);
        }

        return $expired->count();
    }

    private function isExpired(CarbonInterface $heldAt): bool
    {
        return $heldAt->lt($this->staleBefore());
    }

    private function staleBefore(): CarbonInterface
    {
        return now()->subMinutes(self::TTL_MINUTES);
    }
}
