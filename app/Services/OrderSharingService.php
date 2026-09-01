<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\TableScanSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderSharingService
{
    public function __construct(
        private readonly OrderAmountRecalculationService $amounts,
        private readonly TableStatePatchService $statePatches,
        private readonly CheckoutHoldService $checkoutHolds,
    ) {}

    /**
     * @param  array<int>  $tableSessionIds
     * @return array{order: Order, state_patch: array<string, mixed>, order_snapshots: array<int, array<string, mixed>>, removed_order_ids: array<int>}
     */
    public function update(
        int $orderId,
        int $customerId,
        TableScanSession $session,
        array $tableSessionIds,
        ?int $sharedItemId,
        ?int $unsharedItemId,
        string $vendorCountry,
        float $serviceFeeRate,
    ): array {
        return DB::transaction(function () use (
            $orderId,
            $customerId,
            $session,
            $tableSessionIds,
            $sharedItemId,
            $unsharedItemId,
            $vendorCountry,
            $serviceFeeRate,
        ) {
            $order = $orderId > 0
                ? Order::whereKey($orderId)
                    ->where('customer_id', $customerId)
                    ->lockForUpdate()
                    ->first()
                : null;

            // A guest who has ordered nothing themselves still has a bill to
            // put a share on — they simply have no order yet. The client sends
            // 0 to say so, and refusing it made "tap an item to share its cost"
            // silently do nothing for exactly the person most likely to use it.
            if (! $order && $orderId === 0 && $sharedItemId) {
                $order = $this->resolveOrCreateShareOrder($customerId, $session);
            }

            abort_unless($order, 404, 'Order not found.');

            $affectedOrderIds = collect([(int) $order->id]);

            if ($sharedItemId) {
                $this->share(
                    $sharedItemId,
                    $order,
                    $customerId,
                    $session,
                    $tableSessionIds,
                    $affectedOrderIds,
                );
            }

            if ($unsharedItemId) {
                $this->unshare(
                    $unsharedItemId,
                    $order,
                    $tableSessionIds,
                    $affectedOrderIds,
                );
            }

            $state = $this->amounts->recalculate(
                $affectedOrderIds,
                $vendorCountry,
                $serviceFeeRate,
            );

            $removedOrderIds = collect();
            foreach ($state['orders'] as $affectedOrder) {
                if ($affectedOrder->parent_order_id && ShareOrderService::deleteIfEmpty($affectedOrder)) {
                    $removedOrderIds->push((int) $affectedOrder->id);

                    continue;
                }

                // An order minted purely to hold a share has nothing left once
                // that share is dropped. Leaving it behind puts an empty €0.00
                // card under the guest's name, which reads as a duplicate order
                // they never placed.
                if ($this->deleteIfShareOnlyAndEmpty($affectedOrder)) {
                    $removedOrderIds->push((int) $affectedOrder->id);
                }
            }
            $removedOrderIds = $removedOrderIds->unique()->values();

            $orders = $state['orders']
                ->reject(fn (Order $affectedOrder) => $removedOrderIds->contains((int) $affectedOrder->id))
                ->values();

            $statePatch = $this->statePatches->buildFromLoadedState(
                'order.sharing_updated',
                $orders,
                $state['items'],
                $removedOrderIds,
                [],
                $vendorCountry,
            );

            return [
                'order' => $order,
                'state_patch' => $statePatch,
                'order_snapshots' => $this->orderSnapshots($statePatch),
                'removed_order_ids' => $removedOrderIds->all(),
            ];
        });
    }

    /**
     * Remove a main draft that exists only because a share was put on it, and
     * no longer holds anything at all.
     *
     * Deliberately narrow: it must be an unpaid draft with no items of its own,
     * no items shared into it, and no loose cart rows in the session that it
     * would otherwise claim. Anything else is a real order.
     */
    private function deleteIfShareOnlyAndEmpty(Order $order): bool
    {
        if ($order->parent_order_id
            || $order->status !== Order::STATUS_DRAFT
            || $order->payment_received
            || $order->payment_pending) {
            return false;
        }

        $hasOwnItems = CartItem::where('order_id', $order->id)->exists();
        $hasShares = CartItem::whereJsonContains('shared_order_ids', $order->id)->exists();
        $hasLooseSessionItems = $order->table_scan_session_id
            && CartItem::where('table_scan_session_id', $order->table_scan_session_id)
                ->whereNull('order_id')
                ->exists();

        if ($hasOwnItems || $hasShares || $hasLooseSessionItems) {
            return false;
        }

        $order->delete();

        return true;
    }

    /**
     * The order a guest shares into, created empty when they have none.
     *
     * Prefers an existing open order so a guest who already has one never ends
     * up with two. A locked order is skipped: its amount is frozen and a new
     * share must not move it.
     */
    private function resolveOrCreateShareOrder(int $customerId, TableScanSession $session): Order
    {
        $existing = Order::where('customer_id', $customerId)
            ->where('table_scan_session_id', $session->id)
            ->whereIn('status', [Order::STATUS_DRAFT, Order::STATUS_CONFIRMED])
            ->where('payment_received', false)
            ->where('payment_pending', false)
            ->whereNull('parent_order_id')
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $session->loadMissing('vendor');

        return Order::create([
            'order_public_id' => 'ord-'.Str::random(12),
            'customer_id' => $customerId,
            'vendor_id' => $session->vendor_id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_DRAFT,
            'draft_at' => now(),
            'amount' => 0,
            'currency' => $session->vendor?->currency ?? 'EUR',
            'payment_pending' => false,
            'payment_received' => false,
            'order_type' => in_array($session->type, ['pickup', 'takeaway'], true)
                ? $session->type
                : 'dine-in',
        ]);
    }

    private function share(
        int $cartItemId,
        Order $order,
        int $customerId,
        TableScanSession $session,
        array $tableSessionIds,
        Collection $affectedOrderIds,
    ): void {
        $cartItem = $this->lockedTableItem($cartItemId, $tableSessionIds, 'Shared');
        $this->guardPaymentLocks($order, $cartItem);

        if ((int) $cartItem->table_scan_session_id === (int) $session->id) {
            throw ValidationException::withMessages([
                'shared_item' => ['You cannot share your own cart item with yourself.'],
            ]);
        }

        if ($cartItem->order_id
            && $cartItem->getAttribute('owner_paid_by') !== null
            && (int) $cartItem->getAttribute('owner_paid_by') === $customerId) {
            throw ValidationException::withMessages([
                'shared_item' => ['You are already paying for this item.'],
            ]);
        }

        $isCoveredByOther = $order->paid_by !== null && (int) $order->paid_by !== $customerId;
        $sideOrder = $isCoveredByOther
            ? Order::where('parent_order_id', $order->id)
                ->where('payment_received', false)
                ->lockForUpdate()
                ->first()
            : null;

        $myShareOrderIds = collect([$order->id, $sideOrder?->id])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();
        $existing = $this->sharedOrderIds($cartItem);

        if ($myShareOrderIds->intersect($existing)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'shared_item' => ['This item is already shared with your order.'],
            ]);
        }

        $target = $isCoveredByOther
            ? ($sideOrder ?? ShareOrderService::sideOrderFor($order))
            : $order;
        $updated = collect($existing)->push((int) $target->id)->unique()->values();
        $cartItem->update(['shared_order_ids' => $updated->all()]);

        $affectedOrderIds
            ->push((int) $target->id)
            ->push(PaymentGuardService::owningOrderIdFor($cartItem));
        foreach ($updated as $id) {
            $affectedOrderIds->push((int) $id);
        }
        $this->normaliseIds($affectedOrderIds);
    }

    private function unshare(
        int $cartItemId,
        Order $order,
        array $tableSessionIds,
        Collection $affectedOrderIds,
    ): void {
        $cartItem = $this->lockedTableItem($cartItemId, $tableSessionIds, 'Unshared');
        $this->guardPaymentLocks($order, $cartItem);

        if ((bool) $cartItem->getAttribute('owner_payment_received')) {
            throw ValidationException::withMessages([
                'unshared_item' => ['Cannot unshare an item whose owner has already paid.'],
            ]);
        }

        $existing = $this->sharedOrderIds($cartItem);
        foreach ($existing as $id) {
            $affectedOrderIds->push($id);
        }
        $affectedOrderIds->push(PaymentGuardService::owningOrderIdFor($cartItem));

        $cartItem->update([
            'shared_order_ids' => $existing
                ->reject(fn (int $id) => $id === (int) $order->id)
                ->values()
                ->all(),
        ]);

        $this->normaliseIds($affectedOrderIds);
    }

    private function lockedTableItem(int $cartItemId, array $tableSessionIds, string $operation): CartItem
    {
        $cartItem = CartItem::query()
            ->select('cart_items.*')
            ->addSelect([
                'owner_paid_by' => Order::query()
                    ->select('paid_by')
                    ->whereColumn('orders.id', 'cart_items.order_id')
                    ->limit(1),
                'owner_payment_received' => Order::query()
                    ->select('payment_received')
                    ->whereColumn('orders.id', 'cart_items.order_id')
                    ->limit(1),
            ])
            ->whereKey($cartItemId)
            ->whereIn('table_scan_session_id', $tableSessionIds)
            ->lockForUpdate()
            ->first();

        if (! $cartItem) {
            throw ValidationException::withMessages([
                strtolower($operation).'_item' => ["{$operation} cart item does not belong to this table."],
            ]);
        }

        return $cartItem;
    }

    private function guardPaymentLocks(Order $order, CartItem $cartItem): void
    {
        $relatedOrderIds = collect([$order->id, $cartItem->order_id, PaymentGuardService::owningOrderIdFor($cartItem)])
            ->merge($this->sharedOrderIds($cartItem))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if (PaymentGuardService::hasActivePaymentCoveringGraph($relatedOrderIds, [$order->id])) {
            abort(409, 'These items are locked while a payment is in progress.');
        }

        // A guest standing on the payment step holds these orders before any
        // payment exists. Splitting one of their items now would move the total
        // they are about to pay out from under them.
        $heldByOthers = $this->checkoutHolds->heldByOthers($relatedOrderIds, (int) $order->customer_id);

        if ($heldByOthers->isNotEmpty()) {
            abort(409, 'These items are locked while another guest is checking out.');
        }
    }

    /** @return Collection<int, int> */
    private function sharedOrderIds(CartItem $cartItem): Collection
    {
        return collect(is_array($cartItem->shared_order_ids) ? $cartItem->shared_order_ids : [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function normaliseIds(Collection $ids): void
    {
        $normalised = $ids
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $ids->splice(0, $ids->count(), $normalised->all());
    }

    /** @return array<int, array<string, mixed>> */
    private function orderSnapshots(array $statePatch): array
    {
        return collect($statePatch['orders']['upsert'] ?? [])->map(fn (array $order) => [
            'order_id' => $order['id'],
            'order_public_id' => $order['order_public_id'],
            'table_scan_session_id' => $order['table_scan_session_id'],
            'status' => $order['status'],
            'amount' => (float) $order['amount'],
            'tip_amount' => (float) ($order['tip_amount'] ?? 0),
            'service_fee' => (float) ($order['service_fee'] ?? 0),
            'currency' => $order['currency'],
            'payment_method' => $order['payment_method'],
            'payment_pending' => (bool) $order['payment_pending'],
            'payment_received' => (bool) $order['payment_received'],
            'paid_by' => $order['paid_by'],
        ])->values()->all();
    }
}
