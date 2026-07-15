<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds viewer-independent, absolute table-state changes for mutation
 * responses and realtime delivery. Only explicitly affected orders and the
 * items visible on those orders are loaded; this never rebuilds table history.
 */
class TableStatePatchService
{
    /**
     * @param  iterable<int|string>  $orderIds
     * @param  iterable<int|string>  $itemIds
     * @param  iterable<int|string>  $removedOrderIds
     * @param  iterable<int|string>  $removedItemIds
     * @return array<string, mixed>
     */
    public function build(
        string $operation,
        iterable $orderIds = [],
        iterable $itemIds = [],
        iterable $removedOrderIds = [],
        iterable $removedItemIds = [],
    ): array {
        $removedOrders = $this->ids($removedOrderIds);
        $removedItems = $this->ids($removedItemIds);
        $upsertOrderIds = $this->ids($orderIds)->diff($removedOrders)->values();

        $orders = $upsertOrderIds->isEmpty()
            ? collect()
            : Order::with([
                'paidBy:id,first_name,last_name',
                'vendor:id,country',
                'tableScanSession.customer:id,first_name,last_name',
            ])->whereIn('id', $upsertOrderIds)->get();

        $explicitItemIds = $this->ids($itemIds)->diff($removedItems)->values();
        $items = $this->loadVisibleItems($upsertOrderIds, $explicitItemIds)
            ->reject(fn (CartItem $item) => $removedItems->contains((int) $item->id))
            ->unique('id')
            ->values();

        return $this->makePatch(
            $operation,
            $orders,
            $items,
            $removedOrders,
            $removedItems,
            null,
        );
    }

    /**
     * Build a patch without reloading records already made authoritative by a
     * mutation transaction.
     *
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, CartItem>  $items
     * @param  iterable<int|string>  $removedOrderIds
     * @param  iterable<int|string>  $removedItemIds
     * @return array<string, mixed>
     */
    public function buildFromLoadedState(
        string $operation,
        Collection $orders,
        Collection $items,
        iterable $removedOrderIds = [],
        iterable $removedItemIds = [],
        ?string $vendorCountry = null,
    ): array {
        $removedOrders = $this->ids($removedOrderIds);
        $removedItems = $this->ids($removedItemIds);
        $orders = $orders
            ->reject(fn (Order $order) => $removedOrders->contains((int) $order->id))
            ->unique('id')
            ->values();
        $items = $items
            ->reject(fn (CartItem $item) => $removedItems->contains((int) $item->id))
            ->unique('id')
            ->values();

        return $this->makePatch(
            $operation,
            $orders,
            $items,
            $removedOrders,
            $removedItems,
            $vendorCountry,
        );
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, CartItem>  $items
     * @return array<string, mixed>
     */
    private function makePatch(
        string $operation,
        Collection $orders,
        Collection $items,
        Collection $removedOrders,
        Collection $removedItems,
        ?string $vendorCountry,
    ): array {
        $upsertOrderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->values();

        $referencedOrderIds = $items
            ->flatMap(fn (CartItem $item) => $this->sharedOrderIds($item))
            ->merge($items->pluck('order_id')->filter())
            ->merge($upsertOrderIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $identityOrders = $orders->keyBy('id');
        $missingIdentityIds = $referencedOrderIds->diff($identityOrders->keys()->map(fn ($id) => (int) $id));
        if ($missingIdentityIds->isNotEmpty()) {
            $identityOrders = $identityOrders->merge(
                Order::with('tableScanSession.customer:id,first_name,last_name')
                    ->whereIn('id', $missingIdentityIds)
                    ->get()
                    ->keyBy('id'),
            );
        }

        $countryByVendor = $vendorCountry !== null
            ? $orders->mapWithKeys(fn (Order $order) => [
                (int) $order->vendor_id => $vendorCountry,
            ])
            : $orders
                ->filter(fn (Order $order) => $order->vendor !== null)
                ->mapWithKeys(fn (Order $order) => [
                    (int) $order->vendor_id => (string) ($order->vendor?->country ?? 'AT'),
                ]);

        $ownerVendorIds = $identityOrders
            ->mapWithKeys(fn (Order $order) => [(int) $order->id => (int) $order->vendor_id]);

        $itemPatches = $items->map(function (CartItem $item) use ($identityOrders, $countryByVendor, $ownerVendorIds) {
            $ownerVendorId = $item->order_id ? $ownerVendorIds->get((int) $item->order_id) : null;
            $country = $ownerVendorId ? $countryByVendor->get((int) $ownerVendorId, 'AT') : 'AT';

            return $this->itemPatch($item, $identityOrders, (string) $country);
        })->values();

        $visibleItemIds = $orders->mapWithKeys(function (Order $order) use ($items) {
            $ids = $items->filter(function (CartItem $item) use ($order) {
                return (int) $item->order_id === (int) $order->id
                    || in_array((int) $order->id, $this->sharedOrderIds($item), true);
            })->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();

            return [(int) $order->id => $ids];
        });

        return [
            'id' => (string) Str::uuid7(),
            'version' => (int) floor(microtime(true) * 1_000_000),
            'operation' => $operation,
            'orders' => [
                'upsert' => $orders
                    ->map(fn (Order $order) => $this->orderPatch(
                        $order,
                        $visibleItemIds->get((int) $order->id, []),
                    ))
                    ->values()
                    ->all(),
                'remove_ids' => $removedOrders->all(),
            ],
            'items' => [
                'upsert' => $itemPatches->all(),
                'remove_ids' => $removedItems->all(),
            ],
        ];
    }

    /** @return Collection<int, CartItem> */
    private function loadVisibleItems(Collection $orderIds, Collection $explicitItemIds): Collection
    {
        if ($orderIds->isEmpty() && $explicitItemIds->isEmpty()) {
            return collect();
        }

        return CartItem::with([
            'menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category',
        ])->where(function ($query) use ($orderIds, $explicitItemIds) {
            if ($explicitItemIds->isNotEmpty()) {
                $query->whereIn('id', $explicitItemIds);
            }

            if ($orderIds->isNotEmpty()) {
                $method = $explicitItemIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $query->{$method}('order_id', $orderIds);

                foreach ($orderIds as $orderId) {
                    $query->orWhereJsonContains('shared_order_ids', (int) $orderId);
                }
            }
        })->get();
    }

    /** @param array<int> $visibleItemIds */
    private function orderPatch(Order $order, array $visibleItemIds): array
    {
        $paidBy = $order->paidBy;

        return [
            'id' => (int) $order->id,
            'order_public_id' => (string) $order->order_public_id,
            'parent_order_id' => $order->parent_order_id ? (int) $order->parent_order_id : null,
            'customer_id' => $order->customer_id ? (int) $order->customer_id : null,
            'vendor_id' => (int) $order->vendor_id,
            'table_scan_session_id' => $order->table_scan_session_id ? (int) $order->table_scan_session_id : null,
            'status' => (string) $order->status,
            'amount' => (float) $order->amount,
            'tip_amount' => (float) ($order->tip_amount ?? 0),
            'currency' => $order->currency,
            'order_number' => $order->order_number,
            'order_type' => $order->order_type,
            'table_number' => $order->table_number,
            'service_fee' => (float) ($order->service_fee ?? 0),
            'vat_amount' => (float) ($order->vat_amount ?? 0),
            'course' => $order->course,
            'payment_method' => $order->payment_method,
            'payment_pending' => (bool) $order->payment_pending,
            'payment_received' => (bool) $order->payment_received,
            'payment_confirmed_at' => $order->payment_confirmed_at?->toISOString(),
            'payment_note' => $order->payment_note,
            'transaction_id' => $order->transaction_id,
            'served_at' => $order->served_at?->toISOString(),
            'cancelled_at' => $order->cancelled_at?->toISOString(),
            'cancelled_reason' => $order->cancelled_reason,
            'waiter_confirmed' => (bool) $order->waiter_confirmed,
            'waiter_confirmed_at' => $order->waiter_confirmed_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString(),
            'paid_by' => $paidBy ? [
                'id' => (int) $paidBy->id,
                'name' => trim(($paidBy->first_name ?? '').' '.($paidBy->last_name ?? '')) ?: 'Guest',
            ] : null,
            'visible_item_ids' => array_values(array_map('intval', $visibleItemIds)),
        ];
    }

    private function itemPatch(CartItem $item, Collection $ordersById, string $country): array
    {
        $menuItem = $item->menuItem;
        $sharedOrderIds = $this->sharedOrderIds($item);
        $sharedBetween = 1 + count($sharedOrderIds);
        $unitPrice = TaxCalculationService::cartItemUnitPriceGross($item, $country);
        $lineTotal = TaxCalculationService::cartItemLineTotalGross($item, $country);
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $country);

        return [
            'cart_item_id' => (int) $item->id,
            'menu_item_id' => $item->menu_item_id ? (int) $item->menu_item_id : null,
            'owner_order_id' => $item->order_id ? (int) $item->order_id : null,
            'owner_table_scan_session_id' => (int) $item->table_scan_session_id,
            'name' => $menuItem?->name,
            'image_url' => $menuItem?->image_url,
            'quantity' => (int) $item->quantity,
            'notes' => $item->notes,
            'paid_addons' => $item->paid_addons ?? [],
            'free_addons' => $item->free_addons ?? [],
            'removed_items' => $item->removed_items ?? [],
            'selected_modifiers' => $item->selected_modifiers ?? [],
            'shared_order_ids' => $sharedOrderIds,
            'shared_between' => $sharedBetween,
            'shared_with' => array_values(array_filter(array_map(function (int $orderId) use ($ordersById) {
                $order = $ordersById->get($orderId);
                if (! $order) {
                    return null;
                }

                $customer = $order->relationLoaded('customer')
                    ? $order->customer
                    : $order->tableScanSession?->customer;

                return [
                    'order_id' => (int) $order->id,
                    'customer_id' => $order->customer_id
                        ? (int) $order->customer_id
                        : ($order->tableScanSession?->customer_id ? (int) $order->tableScanSession->customer_id : null),
                    'customer_name' => $customer
                        ? (trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Guest')
                        : 'Waiter',
                ];
            }, $sharedOrderIds))),
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'share_price' => round($lineTotal / $sharedBetween, 2),
            'vat_rate' => $vatRate,
            'tax_category' => (string) ($menuItem?->tax_category ?? 'food'),
            'vat_amount' => TaxCalculationService::vatFromGross($lineTotal, $vatRate),
            'status' => $item->status(),
            'received_at' => $item->received_at?->toISOString(),
            'preparing_start_at' => $item->preparing_start_at?->toISOString(),
            'ready_at' => $item->ready_at?->toISOString(),
            'served_at' => $item->served_at?->toISOString(),
        ];
    }

    /** @return array<int> */
    private function sharedOrderIds(CartItem $item): array
    {
        return array_values(array_unique(array_map(
            'intval',
            is_array($item->shared_order_ids) ? $item->shared_order_ids : [],
        )));
    }

    /** @return Collection<int, int> */
    private function ids(iterable $ids): Collection
    {
        return collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();
    }
}
