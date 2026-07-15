<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderAmountRecalculationService
{
    /**
     * Recalculate every affected unpaid order from one shared item graph.
     *
     * @param  iterable<int|string>  $orderIds
     * @return array{orders: EloquentCollection<int, Order>, items: EloquentCollection<int, CartItem>}
     */
    public function recalculate(
        iterable $orderIds,
        string $vendorCountry,
        float $serviceFeeRate,
    ): array {
        $ids = collect($orderIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [
                'orders' => new EloquentCollection,
                'items' => new EloquentCollection,
            ];
        }

        $orders = Order::whereIn('id', $ids)
            ->where('payment_received', false)
            ->whereNotNull('table_scan_session_id')
            ->get();

        $this->loadCustomerIdentities($orders);
        $items = $this->visibleItems($orders->pluck('id'), $vendorCountry);
        $updates = [];

        foreach ($orders as $order) {
            $itemsTotal = round($items->sum(function (CartItem $item) use ($order, $vendorCountry) {
                if (! $this->isVisibleOn($item, $order)) {
                    return 0.0;
                }

                $lineTotal = TaxCalculationService::cartItemLineTotalGross($item, $vendorCountry);
                $shareCount = 1 + count($item->shared_order_ids ?? []);

                return $lineTotal / $shareCount;
            }), 2);

            $serviceFee = round($itemsTotal * ($serviceFeeRate / 100), 2);
            $updates[(int) $order->id] = [
                'amount' => round($itemsTotal + $serviceFee, 2),
                'service_fee' => $serviceFee,
            ];
        }

        $this->persistAmounts($orders, $updates);

        return compact('orders', 'items');
    }

    /** @return EloquentCollection<int, CartItem> */
    private function visibleItems(Collection $orderIds, string $vendorCountry): EloquentCollection
    {
        if ($orderIds->isEmpty()) {
            return new EloquentCollection;
        }

        $countryCode = TaxCalculationService::countryCode($vendorCountry);

        return CartItem::with([
            'menuItem' => fn ($query) => $query
                ->select([
                    'menu_items.id',
                    'menu_items.name',
                    'menu_items.price',
                    'menu_items.has_discount',
                    'menu_items.discounted_price',
                    'menu_items.image_url',
                    'menu_items.vat_rate',
                    'menu_items.tax_category',
                    DB::raw('COALESCE(resolved_tax.vat_rate, menu_items.vat_rate) AS resolved_vat_rate'),
                ])
                ->leftJoin('tax_categories as resolved_tax', function ($join) use ($countryCode) {
                    $join->on('resolved_tax.slug', '=', 'menu_items.tax_category')
                        ->where('resolved_tax.country', '=', $countryCode)
                        ->where('resolved_tax.is_active', '=', true);
                }),
        ])->where(function ($query) use ($orderIds) {
            $query->whereIn('order_id', $orderIds);

            foreach ($orderIds as $orderId) {
                $query->orWhereJsonContains('shared_order_ids', (int) $orderId);
            }
        })->get();
    }

    /** @param EloquentCollection<int, Order> $orders */
    private function loadCustomerIdentities(EloquentCollection $orders): void
    {
        $customerIds = $orders
            ->flatMap(fn (Order $order) => [$order->customer_id, $order->paid_by])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $customers = $customerIds->isEmpty()
            ? collect()
            : Customer::query()
                ->select(['id', 'first_name', 'last_name'])
                ->whereIn('id', $customerIds)
                ->get()
                ->keyBy('id');

        foreach ($orders as $order) {
            $order->setRelation('customer', $customers->get((int) $order->customer_id));
            $order->setRelation('paidBy', $customers->get((int) $order->paid_by));
        }
    }

    /**
     * Persist all recalculated totals in one UPDATE while retaining the
     * authoritative values on the already-loaded models used by the patch.
     *
     * @param  EloquentCollection<int, Order>  $orders
     * @param  array<int, array{amount: float, service_fee: float}>  $updates
     */
    private function persistAmounts(EloquentCollection $orders, array $updates): void
    {
        if ($updates === []) {
            return;
        }

        $grammar = DB::connection()->getQueryGrammar();
        $table = $grammar->wrapTable('orders');
        $id = $grammar->wrap('id');
        $amount = $grammar->wrap('amount');
        $serviceFee = $grammar->wrap('service_fee');
        $updatedAt = $grammar->wrap('updated_at');
        $amountCases = [];
        $serviceFeeCases = [];
        $amountBindings = [];
        $serviceFeeBindings = [];

        foreach ($updates as $orderId => $values) {
            $amountCases[] = 'WHEN ? THEN ?';
            $amountBindings[] = $orderId;
            $amountBindings[] = $values['amount'];
            $serviceFeeCases[] = 'WHEN ? THEN ?';
            $serviceFeeBindings[] = $orderId;
            $serviceFeeBindings[] = $values['service_fee'];
        }

        $orderIds = array_keys($updates);
        $placeholders = implode(', ', array_fill(0, count($orderIds), '?'));
        $timestamp = now();
        $sql = "UPDATE {$table} SET "
            ."{$amount} = CASE {$id} ".implode(' ', $amountCases)." ELSE {$amount} END, "
            ."{$serviceFee} = CASE {$id} ".implode(' ', $serviceFeeCases)." ELSE {$serviceFee} END, "
            ."{$updatedAt} = ? WHERE {$id} IN ({$placeholders})";

        DB::update($sql, [
            ...$amountBindings,
            ...$serviceFeeBindings,
            $timestamp,
            ...$orderIds,
        ]);

        foreach ($orders as $order) {
            $values = $updates[(int) $order->id];
            $order->forceFill([
                ...$values,
                'updated_at' => $timestamp,
            ])->syncOriginal();
        }
    }

    private function isVisibleOn(CartItem $item, Order $order): bool
    {
        if ((int) $item->order_id === (int) $order->id) {
            return true;
        }

        return (int) $item->table_scan_session_id !== (int) $order->table_scan_session_id
            && in_array((int) $order->id, $this->sharedOrderIds($item), true);
    }

    /** @return array<int> */
    private function sharedOrderIds(CartItem $item): array
    {
        return array_values(array_unique(array_map(
            'intval',
            is_array($item->shared_order_ids) ? $item->shared_order_ids : [],
        )));
    }
}
