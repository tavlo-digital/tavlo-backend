<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Folds a customer's editable orders back into their oldest order.
 *
 * A second order is only a temporary boundary created while an earlier order
 * is frozen at checkout. Once every participating order is editable again the
 * boundary is no longer useful: items, sharing references and canceled-payment
 * audit records move back to the oldest order and the temporary order is
 * removed. Orders that are still being paid are never touched.
 */
class EditableOrderMergeService
{
    /**
     * @return array{order_ids: Collection<int, int>, removed_order_ids: Collection<int, int>, removed_item_ids: Collection<int, int>}
     */
    public function mergeForSession(int $sessionId): array
    {
        $orders = Order::query()
            ->where('table_scan_session_id', $sessionId)
            ->whereNull('parent_order_id')
            ->whereIn('status', [Order::STATUS_DRAFT, Order::STATUS_CONFIRMED])
            ->where('payment_received', false)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->filter(fn (Order $order) => $this->isEditable($order))
            ->values();

        if ($orders->isEmpty()) {
            return $this->emptyResult();
        }

        $target = $orders->shift();
        $removedOrderIds = collect();
        $removedItemIds = collect();
        $affectedOrderIds = collect([(int) $target->id]);

        foreach ($orders as $source) {
            $merged = $this->mergeOrder($target, $source);
            $removedOrderIds->push((int) $source->id);
            $removedOrderIds = $removedOrderIds->merge($merged['removed_order_ids']);
            $removedItemIds = $removedItemIds->merge($merged['removed_item_ids']);
            $affectedOrderIds = $affectedOrderIds->merge($merged['affected_order_ids']);
        }

        if ($removedOrderIds->isNotEmpty()) {
            $target->refresh();
            ShareOrderService::recalcOrder($target);
        }

        // A sole editable draft owns the session's open rows implicitly.
        // Return its previously frozen rows to that pool so subsequent adds
        // can increment the same cart line again. The guard keeps them bound
        // when another editable draft still exists.
        PaymentGuardService::thawDraftItems($target);

        return [
            'order_ids' => $affectedOrderIds
                ->diff($removedOrderIds)
                ->push((int) $target->id)
                ->unique()
                ->values(),
            'removed_order_ids' => $removedOrderIds->unique()->values(),
            'removed_item_ids' => $removedItemIds->unique()->values(),
        ];
    }

    private function isEditable(Order $order): bool
    {
        if (PaymentGuardService::orderIsCartLocked($order)
            || PaymentGuardService::activePaymentsCovering([(int) $order->id])->isNotEmpty()) {
            return false;
        }

        // A personal side order may be part of the same checkout even when the
        // main order itself is not. Keep the main boundary until its child is
        // editable too.
        $childIds = Order::where('parent_order_id', $order->id)->pluck('id');

        return $childIds->isEmpty()
            || (Order::whereIn('id', $childIds)
                ->where(fn ($query) => $query
                    ->where('payment_received', true)
                    ->orWhere('payment_pending', true))
                ->doesntExist()
                && PaymentGuardService::activePaymentsCovering($childIds)->isEmpty());
    }

    /**
     * @return array{affected_order_ids: Collection<int, int>, removed_order_ids: Collection<int, int>, removed_item_ids: Collection<int, int>}
     */
    private function mergeOrder(Order $target, Order $source): array
    {
        $affectedOrderIds = collect([(int) $target->id]);
        $removedOrderIds = collect();

        CartItem::where('order_id', $source->id)
            ->lockForUpdate()
            ->update(['order_id' => $target->id]);

        $referencingItems = CartItem::whereJsonContains('shared_order_ids', $source->id)
            ->lockForUpdate()
            ->get();

        foreach ($referencingItems as $item) {
            $ids = collect(is_array($item->shared_order_ids) ? $item->shared_order_ids : [])
                ->map(fn ($id) => (int) $id)
                ->map(fn (int $id) => $id === (int) $source->id ? (int) $target->id : $id)
                ->unique()
                ->values();
            $item->update(['shared_order_ids' => $ids->all()]);
            $affectedOrderIds = $affectedOrderIds->merge($ids);
        }

        $this->movePaymentAudit($source, $target);

        // Side orders retain their own payment identity but now belong to the
        // surviving main order. This is safe because isEditable() rejected a
        // source with a side order that is still in checkout.
        Order::where('parent_order_id', $source->id)
            ->update(['parent_order_id' => $target->id]);

        DB::table('reviews')->where('order_id', $source->id)->update(['order_id' => $target->id]);
        DB::table('inventory_stock_movements')->where('order_id', $source->id)->update(['order_id' => $target->id]);

        $targetUpdates = [];
        if ($target->paid_by === null && $source->paid_by !== null) {
            $targetUpdates['paid_by'] = $source->paid_by;
        }
        if ($target->note === null && $source->note !== null) {
            $targetUpdates['note'] = $source->note;
        }
        if ($target->status === Order::STATUS_DRAFT && $source->status === Order::STATUS_CONFIRMED) {
            $targetUpdates['status'] = Order::STATUS_CONFIRMED;
            $targetUpdates['confirmed_at'] = $source->confirmed_at ?? now();
        }
        if ($targetUpdates !== []) {
            $target->update($targetUpdates);
        }

        $source->delete();

        $duplicates = $this->combineDuplicateItems($target);

        return [
            'affected_order_ids' => $affectedOrderIds,
            'removed_order_ids' => $removedOrderIds,
            'removed_item_ids' => $duplicates,
        ];
    }

    private function movePaymentAudit(Order $source, Order $target): void
    {
        $paymentIds = DB::table('order_payment_orders')
            ->where('order_id', $source->id)
            ->pluck('order_payment_id')
            ->merge(OrderPayment::where('order_id', $source->id)->pluck('id'))
            ->unique()
            ->values();

        foreach ($paymentIds as $paymentId) {
            $sourcePivot = DB::table('order_payment_orders')
                ->where('order_payment_id', $paymentId)
                ->where('order_id', $source->id)
                ->first();
            $targetPivot = DB::table('order_payment_orders')
                ->where('order_payment_id', $paymentId)
                ->where('order_id', $target->id)
                ->first();

            if ($sourcePivot && $targetPivot) {
                DB::table('order_payment_orders')
                    ->where('id', $targetPivot->id)
                    ->update([
                        'amount' => round((float) $targetPivot->amount + (float) $sourcePivot->amount, 2),
                        'updated_at' => now(),
                    ]);
                DB::table('order_payment_orders')->where('id', $sourcePivot->id)->delete();
            } elseif ($sourcePivot) {
                DB::table('order_payment_orders')
                    ->where('id', $sourcePivot->id)
                    ->update(['order_id' => $target->id, 'updated_at' => now()]);
            }
        }

        OrderPayment::where('order_id', $source->id)->update(['order_id' => $target->id]);

        OrderPayment::whereIn('id', $paymentIds)->get()->each(function (OrderPayment $payment) use ($source, $target): void {
            $ids = collect(is_array($payment->order_ids) ? $payment->order_ids : [])
                ->map(fn ($id) => (int) $id)
                ->map(fn (int $id) => $id === (int) $source->id ? (int) $target->id : $id)
                ->unique()
                ->values()
                ->all();
            $payment->update(['order_ids' => $ids]);
        });
    }

    /** @return Collection<int, int> */
    private function combineDuplicateItems(Order $order): Collection
    {
        $removed = collect();
        $items = CartItem::where('table_scan_session_id', $order->table_scan_session_id)
            ->where(fn ($query) => $query
                ->where('order_id', $order->id)
                ->orWhereNull('order_id'))
            ->whereNull('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $keepers = [];
        foreach ($items as $item) {
            $key = json_encode([
                (int) $item->menu_item_id,
                (string) ($item->notes ?? ''),
                $this->normalizedJson($item->paid_addons),
                $this->normalizedJson($item->free_addons),
                $this->normalizedJson($item->removed_items),
                $this->normalizedJson($item->selected_modifiers),
                collect($item->shared_order_ids ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all(),
            ]);

            if (! isset($keepers[$key])) {
                $keepers[$key] = $item;

                continue;
            }

            $keepers[$key]->increment('quantity', (int) $item->quantity);
            $removed->push((int) $item->id);
            $item->delete();
        }

        return $removed;
    }

    private function normalizedJson(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        array_walk_recursive($value, static function (&$entry): void {
            if (is_numeric($entry)) {
                $entry = (string) $entry;
            }
        });

        return $value;
    }

    /**
     * @return array{order_ids: Collection<int, int>, removed_order_ids: Collection<int, int>, removed_item_ids: Collection<int, int>}
     */
    private function emptyResult(): array
    {
        return [
            'order_ids' => collect(),
            'removed_order_ids' => collect(),
            'removed_item_ids' => collect(),
        ];
    }
}
