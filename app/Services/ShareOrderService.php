<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A "side order" holds the personal opt-in shares of a customer whose own
 * order is covered by another payer. It keeps payments strictly order-level:
 * the payer covers the main order (own items only) while the sharer pays the
 * side order themselves.
 */
class ShareOrderService
{
    /**
     * Find or create the unpaid side order attached to $main.
     */
    public static function sideOrderFor(Order $main): Order
    {
        $existing = Order::where('parent_order_id', $main->id)
            ->where('payment_received', false)
            ->whereNotIn('status', [Order::STATUS_CANCELLED])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Order::create([
            'order_public_id' => 'ord-'.Str::random(12),
            'parent_order_id' => $main->id,
            'customer_id' => $main->customer_id,
            'vendor_id' => $main->vendor_id,
            'table_scan_session_id' => $main->table_scan_session_id,
            'status' => Order::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'amount' => 0,
            'currency' => $main->currency,
            'order_type' => $main->order_type,
            'table_number' => $main->table_number,
            'payment_method' => $main->payment_method,
            'payment_pending' => false,
            'payment_received' => false,
        ]);
    }

    /**
     * Move the covered order's opt-in shares (cart items owned by another
     * session but shared into $covered) onto its side order, so the payer
     * covers only the owner's own items. Returns the side order, or null when
     * there was nothing to move.
     */
    public static function moveOptInShares(Order $covered): ?Order
    {
        $optInItems = CartItem::whereJsonContains('shared_order_ids', $covered->id)
            ->where('table_scan_session_id', '!=', $covered->table_scan_session_id)
            ->lockForUpdate()
            ->get();

        if ($optInItems->isEmpty()) {
            return null;
        }

        $side = self::sideOrderFor($covered);

        foreach ($optInItems as $item) {
            self::replaceShareReference($item, $covered->id, $side->id);
        }

        self::recalcOrder($covered);
        self::recalcOrder($side);

        return $side;
    }

    /**
     * Merge the unpaid side order's shares back into $main (used when the
     * coverage on $main is released). A side order that is already paid is
     * left untouched. Returns the merged side order id or null.
     */
    public static function mergeBack(Order $main): ?int
    {
        $side = Order::where('parent_order_id', $main->id)
            ->where('payment_received', false)
            ->lockForUpdate()
            ->first();

        // A side order the owner already started (or finished) paying stays as
        // a standalone payable order — deleting it would cascade into its
        // payment records. Functionally equivalent: the owner still owes it.
        if (! $side || self::hasPayments($side)) {
            return null;
        }

        $shares = CartItem::whereJsonContains('shared_order_ids', $side->id)
            ->lockForUpdate()
            ->get();

        foreach ($shares as $item) {
            self::replaceShareReference($item, $side->id, $main->id);
        }

        $sideId = $side->id;
        $side->delete();
        self::recalcOrder($main);

        return $sideId;
    }

    /**
     * Delete the side order when no cart item references it anymore.
     * Returns true when it was deleted.
     */
    public static function deleteIfEmpty(Order $side): bool
    {
        if (! $side->parent_order_id || $side->payment_received) {
            return false;
        }

        $hasShares = CartItem::whereJsonContains('shared_order_ids', $side->id)->exists();
        $hasOwnItems = CartItem::where('order_id', $side->id)->exists();

        if ($hasShares || $hasOwnItems || self::hasPayments($side)) {
            return false;
        }

        $side->delete();

        return true;
    }

    private static function hasPayments(Order $side): bool
    {
        return OrderPayment::where('order_id', $side->id)->exists()
            || DB::table('order_payment_orders')->where('order_id', $side->id)->exists();
    }

    /**
     * Recompute amount + service fee for an order from its cart items
     * (owned + shared-into), mirroring the customer order recalculation.
     */
    public static function recalcOrder(Order $order): void
    {
        if (! $order->relationLoaded('vendor')) {
            $order->load('vendor.vendorSetting');
        }

        $vendorCountry = $order->vendor?->country ?? 'AT';
        $serviceFeeRate = (float) ($order->vendor?->vendorSetting?->service_fee_rate ?? 0);

        $owned = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where('order_id', $order->id)
            ->get();

        $sharedInto = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->whereJsonContains('shared_order_ids', $order->id)
            ->where('table_scan_session_id', '!=', $order->table_scan_session_id)
            ->get();

        $itemsTotal = round($owned->merge($sharedInto)->sum(function (CartItem $item) use ($vendorCountry) {
            $lineTotal = TaxCalculationService::cartItemLineTotalGross($item, $vendorCountry);
            $shareCount = 1 + count($item->shared_order_ids ?? []);

            return $lineTotal / $shareCount;
        }), 2);

        $serviceFee = round($itemsTotal * ($serviceFeeRate / 100), 2);

        $order->update([
            'amount' => round($itemsTotal + $serviceFee, 2),
            'service_fee' => $serviceFee,
        ]);
    }

    /**
     * Swap one order id for another inside a cart item's shared_order_ids.
     */
    private static function replaceShareReference(CartItem $item, int $fromOrderId, int $toOrderId): void
    {
        $ids = array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []);
        $ids = array_values(array_unique(array_map(
            fn (int $id) => $id === $fromOrderId ? $toOrderId : $id,
            $ids
        )));

        $item->update(['shared_order_ids' => $ids]);
    }
}
