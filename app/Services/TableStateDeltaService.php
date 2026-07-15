<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TableStateDeltaService
{
    public function __construct(
        private readonly VendorDateTimeService $dateTimes,
        private readonly LocaleService $locales,
        private readonly MenuCustomizationService $customizations,
    ) {}

    /**
     * @param  array<int, int>  $sessionIds
     * @param  array<int, int>  $removedOrderIds
     * @param  array<int, int>  $affectedItemIds
     */
    public function historyDelta(
        TableScanSession $viewerSession,
        array $sessionIds,
        string $operation,
        array $removedOrderIds = [],
        array $affectedItemIds = [],
        ?Request $request = null,
    ): array {
        return [
            ...$this->base($operation),
            'changed_people' => $this->historyPeople($viewerSession, $sessionIds, $request),
            'changed_cart_people' => [],
            'removed_order_ids' => array_values(array_unique(array_map('intval', $removedOrderIds))),
            'affected_item_ids' => array_values(array_unique(array_map('intval', $affectedItemIds))),
        ];
    }

    /** @param array<int, int> $sessionIds */
    public function cartDelta(
        TableScanSession $viewerSession,
        array $sessionIds,
        string $operation,
        ?Request $request = null,
    ): array {
        return [
            ...$this->base($operation),
            'changed_people' => [],
            'changed_cart_people' => $this->cartPeople($viewerSession, $sessionIds, $request),
            'removed_order_ids' => [],
            'affected_item_ids' => [],
        ];
    }

    private function base(string $operation): array
    {
        return [
            'event_id' => (string) Str::uuid7(),
            'event_version' => (int) floor(microtime(true) * 1_000_000),
            'operation' => $operation,
        ];
    }

    /**
     * Build complete person rows only for the affected sessions. Orders and
     * cart items are loaded once for the table so shared references can be
     * resolved without issuing queries per order.
     *
     * @param  array<int, int>  $affectedSessionIds
     */
    private function historyPeople(
        TableScanSession $viewerSession,
        array $affectedSessionIds,
        ?Request $request,
    ): array {
        $affectedSessionIds = array_values(array_unique(array_map('intval', $affectedSessionIds)));
        if ($affectedSessionIds === []) {
            return [];
        }

        if (! $viewerSession->relationLoaded('vendor')) {
            $viewerSession->load('vendor.vendorSetting');
        }

        $vendor = $viewerSession->vendor;
        $vendorCountry = $vendor?->country ?? 'AT';
        $locale = $request && $vendor
            ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor)
            : 'en';

        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'restaurantTable:id,number,name',
        ])
            ->where('restaurant_table_id', $viewerSession->restaurant_table_id)
            ->where('vendor_id', $viewerSession->vendor_id)
            ->where('status', 'active')
            ->get();

        $tableSessionIds = $sessions->pluck('id')->all();
        $orders = Order::with('paidBy:id,first_name,last_name')
            ->whereIn('table_scan_session_id', $tableSessionIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('table_scan_session_id');
        $allCartItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->whereIn('table_scan_session_id', $tableSessionIds)
            ->get();
        $ordersById = $orders->flatten()->keyBy('id');
        $sessionCustomerNames = $sessions->mapWithKeys(fn (TableScanSession $session) => [
            $session->id => $session->customer
                ? trim($session->customer->first_name.' '.$session->customer->last_name)
                : 'Waiter',
        ]);

        return $sessions
            ->filter(fn (TableScanSession $session) => in_array((int) $session->id, $affectedSessionIds, true))
            ->map(function (TableScanSession $session) use ($viewerSession, $orders, $allCartItems, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor, $locale) {
                $personOrders = $orders->get($session->id, collect());
                $personCartItems = collect();

                $orderPayloads = $personOrders->map(function (Order $order) use ($session, $allCartItems, $viewerSession, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor, $locale, &$personCartItems) {
                    $ownedItems = $allCartItems->filter(function (CartItem $item) use ($session, $order) {
                        if ($order->status === Order::STATUS_DRAFT) {
                            return (int) $item->table_scan_session_id === (int) $session->id
                                && $item->order_id === null;
                        }

                        return (int) $item->order_id === (int) $order->id;
                    });
                    $sharedItems = $allCartItems->filter(function (CartItem $item) use ($order, $ownedItems) {
                        if ($ownedItems->contains('id', $item->id)) {
                            return false;
                        }

                        return in_array(
                            $order->id,
                            array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []),
                            true,
                        );
                    });
                    $orderItems = $ownedItems->merge($sharedItems);
                    $personCartItems = $personCartItems->merge($orderItems);
                    $items = $orderItems
                        ->map(fn (CartItem $item) => $this->historyItem(
                            $item,
                            $order,
                            $viewerSession,
                            $ordersById,
                            $sessionCustomerNames,
                            $vendorCountry,
                            $vendor,
                            $locale,
                        ))
                        ->values()
                        ->all();

                    return $this->order($order, $items, $vendor);
                })->values();

                $taxGroups = TaxCalculationService::computeTaxGroups($personCartItems, $vendorCountry, true);
                $totals = TaxCalculationService::computeTotals($taxGroups, 0);
                $serviceFee = round((float) $personOrders->sum(fn (Order $order) => (float) ($order->service_fee ?? 0)), 2);
                $tips = round((float) $personOrders->sum(fn (Order $order) => (float) ($order->tip_amount ?? 0)), 2);
                $totals['service_fee'] = $serviceFee;
                $totals['total_tips'] = $tips;
                $totals['grand_total'] = round($totals['grand_total'] + $serviceFee + $tips, 2);

                return [
                    'session_id' => $session->id,
                    'customer_id' => $session->customer_id,
                    'is_me' => $session->id === $viewerSession->id,
                    'name' => $sessionCustomerNames->get($session->id, 'Waiter'),
                    'scanned_at' => $this->dateTimes->formatDateTime($session->scanned_at, $vendor),
                    'status' => $session->status,
                    'orders_count' => $personOrders->count(),
                    'total_amount' => round((float) $personOrders->sum(fn (Order $order) => (float) $order->amount), 2),
                    'orders' => $orderPayloads,
                    'tax_groups' => array_map(fn (array $group) => array_merge($group, [
                        'label' => $this->locales->translatedTaxCategoryName($group['tax_category'], $vendorCountry, $locale),
                    ]), $taxGroups),
                    'totals' => $totals,
                ];
            })
            ->values()
            ->all();
    }

    /** @param array<int, int> $affectedSessionIds */
    private function cartPeople(
        TableScanSession $viewerSession,
        array $affectedSessionIds,
        ?Request $request,
    ): array {
        $affectedSessionIds = array_values(array_unique(array_map('intval', $affectedSessionIds)));
        if ($affectedSessionIds === []) {
            return [];
        }

        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'cartItems.menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations',
            'vendor:id,vendor_public_id,restaurant_name,country',
            'vendor.vendorSetting:id,vendor_id,service_fee_rate,supported_languages',
        ])
            ->where('restaurant_table_id', $viewerSession->restaurant_table_id)
            ->where('vendor_id', $viewerSession->vendor_id)
            ->where('status', 'active')
            ->whereIn('id', $affectedSessionIds)
            ->get();

        $vendor = $sessions->first()?->vendor ?? $viewerSession->vendor;
        $vendorCountry = $vendor?->country ?? 'AT';
        $serviceFeeRate = (float) ($vendor?->vendorSetting?->service_fee_rate ?? 0);
        $locale = $request && $vendor
            ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor)
            : 'en';
        $tableSessionIds = TableScanSession::where('restaurant_table_id', $viewerSession->restaurant_table_id)
            ->where('vendor_id', $viewerSession->vendor_id)
            ->where('status', 'active')
            ->pluck('id');
        $orderedOrderIds = Order::whereIn('table_scan_session_id', $tableSessionIds)
            ->whereIn('status', array_merge(Order::ACTIVE_STATUSES, Order::COMPLETED_STATUSES))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $sessions->map(function (TableScanSession $session) use ($viewerSession, $orderedOrderIds, $vendorCountry, $serviceFeeRate, $vendor, $locale) {
            $items = $session->cartItems
                ->filter(function (CartItem $item) use ($orderedOrderIds) {
                    if ($item->order_id !== null) {
                        return false;
                    }

                    return array_intersect(
                        array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []),
                        $orderedOrderIds,
                    ) === [];
                })
                ->values();
            $taxGroups = TaxCalculationService::computeTaxGroups($items, $vendorCountry, true);

            return [
                'session_id' => $session->id,
                'customer_id' => $session->customer_id,
                'is_me' => $session->id === $viewerSession->id,
                'name' => $session->customer
                    ? trim($session->customer->first_name.' '.$session->customer->last_name)
                    : 'Waiter',
                'personal_items' => $items
                    ->map(fn (CartItem $item) => $this->cartItem($item, $vendorCountry, $vendor, $locale))
                    ->all(),
                'tax_groups' => array_map(fn (array $group) => array_merge($group, [
                    'label' => $this->locales->translatedTaxCategoryName($group['tax_category'], $vendorCountry, $locale),
                ]), $taxGroups),
                'totals' => TaxCalculationService::computeTotals($taxGroups, $serviceFeeRate),
            ];
        })->values()->all();
    }

    private function historyItem(
        CartItem $item,
        Order $order,
        TableScanSession $viewerSession,
        Collection $ordersById,
        Collection $sessionCustomerNames,
        string $vendorCountry,
        ?Vendor $vendor,
        string $locale,
    ): array {
        $menuItem = $item->menuItem;
        $taxCategory = $menuItem?->tax_category ?? 'food';
        $lineTotal = TaxCalculationService::cartItemLineTotalGross($item, $vendorCountry);
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);
        $sharedOrderIds = array_values(array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []));
        $sharedBetween = 1 + count($sharedOrderIds);
        $sharedWith = array_values(array_filter(array_map(function (int $orderId) use ($ordersById, $sessionCustomerNames) {
            $sharedOrder = $ordersById->get($orderId);
            if (! $sharedOrder) {
                return null;
            }

            return [
                'order_id' => $sharedOrder->id,
                'customer_id' => $sharedOrder->customer_id,
                'customer_name' => $sessionCustomerNames->get($sharedOrder->table_scan_session_id, 'Guest'),
            ];
        }, $sharedOrderIds)));

        return [
            'cart_item_id' => $item->id,
            'menu_item_id' => $item->menu_item_id,
            'name' => $menuItem && $vendor
                ? $this->customizations->menuItemName($menuItem, $vendor, $locale)
                : $menuItem?->name,
            'image_url' => $menuItem?->image_url,
            'quantity' => $item->quantity,
            'unit_price' => TaxCalculationService::cartItemUnitPriceGross($item, $vendorCountry),
            'paid_addons' => $this->paidAddons($item, $taxCategory, $vendorCountry, $vendor, $locale),
            'free_addons' => $this->namedSelections($item, 'free_addons', $vendor, $locale),
            'removed_items' => $this->namedSelections($item, 'removed_items', $vendor, $locale),
            'selected_modifiers' => $this->selectedModifiers($item, $taxCategory, $vendorCountry, $vendor, $locale),
            'vat_rate' => $vatRate,
            'tax_category' => $taxCategory,
            'vat_amount' => TaxCalculationService::vatFromGross($lineTotal, $vatRate),
            'line_total' => $lineTotal,
            'is_mine' => (int) $item->table_scan_session_id === (int) $viewerSession->id,
            'shared_between' => $sharedBetween,
            'shared_with' => $sharedWith,
            'my_share' => round($lineTotal / $sharedBetween, 2),
            'status' => $item->status(),
            'received_at' => $this->dateTimes->formatDateTime($item->received_at, $vendor),
            'preparing_start_at' => $this->dateTimes->formatDateTime($item->preparing_start_at, $vendor),
            'ready_at' => $this->dateTimes->formatDateTime($item->ready_at, $vendor),
            'served_at' => $this->dateTimes->formatDateTime($item->served_at, $vendor),
        ];
    }

    private function order(Order $order, array $items, ?Vendor $vendor): array
    {
        return [
            'id' => $order->id,
            'order_public_id' => $order->order_public_id,
            'parent_order_id' => $order->parent_order_id,
            'customer_id' => $order->customer_id,
            'paid_by' => $order->paidBy ? [
                'id' => $order->paidBy->id,
                'name' => trim(($order->paidBy->first_name ?? '').' '.($order->paidBy->last_name ?? '')) ?: 'Guest',
            ] : null,
            'vendor_id' => $order->vendor_id,
            'table_scan_session_id' => $order->table_scan_session_id,
            'status' => $order->status,
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
            'payment_confirmed_at' => $this->dateTimes->formatDateTime($order->payment_confirmed_at, $vendor),
            'payment_note' => $order->payment_note,
            'transaction_id' => $order->transaction_id,
            'served_at' => $this->dateTimes->formatDateTime($order->served_at, $vendor),
            'cancelled_at' => $this->dateTimes->formatDateTime($order->cancelled_at, $vendor),
            'cancelled_reason' => $order->cancelled_reason,
            'waiter_confirmed' => (bool) $order->waiter_confirmed,
            'waiter_confirmed_at' => $this->dateTimes->formatDateTime($order->waiter_confirmed_at, $vendor),
            'created_at' => $this->dateTimes->formatDateTime($order->created_at, $vendor),
            'updated_at' => $this->dateTimes->formatDateTime($order->updated_at, $vendor),
            'items_count' => count($items),
            'items' => $items,
        ];
    }

    private function cartItem(CartItem $item, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->menuItem;
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);
        $lineTotal = TaxCalculationService::cartItemLineTotalGross($item, $vendorCountry);
        $taxCategory = $menuItem?->tax_category ?? 'food';

        return [
            'id' => $item->id,
            'quantity' => $item->quantity,
            'notes' => $item->notes,
            'price' => TaxCalculationService::cartItemUnitPriceGross($item, $vendorCountry),
            'paid_addons' => $this->paidAddons($item, $taxCategory, $vendorCountry, $vendor, $locale),
            'free_addons' => $this->namedSelections($item, 'free_addons', $vendor, $locale),
            'removed_items' => $this->namedSelections($item, 'removed_items', $vendor, $locale),
            'selected_modifiers' => $this->selectedModifiers($item, $taxCategory, $vendorCountry, $vendor, $locale),
            'vat_rate' => $vatRate,
            'vat_amount' => TaxCalculationService::vatFromGross($lineTotal, $vatRate),
            'line_total' => $lineTotal,
            'menu_item' => $menuItem ? [
                'id' => $menuItem->id,
                'name' => $vendor ? $this->customizations->menuItemName($menuItem, $vendor, $locale) : $menuItem->name,
                'price' => TaxCalculationService::itemBaseGross($menuItem, $vendorCountry),
                'vat_rate' => $vatRate,
                'tax_category' => $menuItem->tax_category,
                'image_url' => $menuItem->image_url,
            ] : null,
        ];
    }

    private function paidAddons(CartItem $item, string $taxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        if ($item->menuItem && $vendor) {
            return $this->customizations->formatPaidAddons($item->menuItem, $item->paid_addons ?? [], $vendor, $locale, $taxCategory, $vendorCountry);
        }

        return collect($item->paid_addons ?? [])->map(function ($addon) use ($taxCategory, $vendorCountry) {
            $vatRate = TaxCalculationService::addonVatRate(is_array($addon) ? $addon : [], $taxCategory, $vendorCountry);

            return [
                'id' => is_array($addon) ? ($addon['id'] ?? null) : null,
                'name' => is_array($addon) ? ($addon['name'] ?? '') : '',
                'price' => TaxCalculationService::gross((float) (is_array($addon) ? ($addon['price'] ?? 0) : 0), $vatRate),
                'vat_rate' => $vatRate,
            ];
        })->values()->all();
    }

    private function namedSelections(CartItem $item, string $field, ?Vendor $vendor, string $locale): array
    {
        $selected = $field === 'free_addons' ? ($item->free_addons ?? []) : ($item->removed_items ?? []);
        if (! $item->menuItem || ! $vendor) {
            return collect($selected)
                ->map(fn ($value) => is_array($value) ? (string) ($value['name'] ?? '') : (string) $value)
                ->filter()
                ->values()
                ->all();
        }

        $configured = $field === 'free_addons'
            ? ($item->menuItem->free_addons ?? [])
            : ($item->menuItem->removable_items ?? []);

        return $this->customizations->formatNamedSelections($configured, $selected, $vendor, $locale);
    }

    private function selectedModifiers(CartItem $item, string $taxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        if ($vendor) {
            return $this->customizations->formatSelectedModifiers($item->selected_modifiers ?? [], $vendor, $locale, $taxCategory, $vendorCountry);
        }

        return collect($item->selected_modifiers ?? [])->map(function ($group) use ($taxCategory, $vendorCountry) {
            $vatRate = TaxCalculationService::modifierGroupVatRate($group['tax_category'] ?? '', $taxCategory, $vendorCountry);

            return array_merge($group, [
                'vat_rate' => $vatRate,
                'options' => collect($group['options'] ?? [])->map(fn ($option) => [
                    'id' => $option['id'] ?? null,
                    'name' => $option['name'] ?? null,
                    'price_adjustment' => TaxCalculationService::gross((float) ($option['price_adjustment'] ?? 0), $vatRate),
                ])->values()->all(),
            ]);
        })->values()->all();
    }
}
