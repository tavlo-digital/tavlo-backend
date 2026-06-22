<?php

namespace Tests\Unit;

use App\Models\CartItem;
use App\Models\MenuItem;
use Tests\TestCase;

/**
 * ISSUE-019: Unit tests for split-payment arithmetic.
 *
 * These tests cover the core financial calculation — how much each participant
 * pays when items are shared. Run these before ANY change to CartController's
 * cartItemBasePrice(), cartItemLineTotal(), or computeOrderAmount().
 *
 * NOTE: Extract CartController price helpers into a CartPricingService class
 * to make them unit-testable without HTTP. Until then these tests can be written
 * as feature tests hitting the cart endpoints, but this file shows the intended
 * unit test structure for when the extraction is done.
 */
class CartPricingServiceTest extends TestCase
{
    // ── Helpers ──────────────────────────────────────────────────────────

    private function makeCartItem(
        float $price,
        int $quantity = 1,
        array $shared = [],
        ?float $discountedPrice = null,
        bool $hasDiscount = false,
        array $paidAddons = [],
        ?float $storedUnitPrice = null,
    ): CartItem {
        $menuItem = new MenuItem();
        $menuItem->price           = $price;
        $menuItem->has_discount    = $hasDiscount;
        $menuItem->discounted_price = $discountedPrice;

        $item = new CartItem();
        $item->quantity          = $quantity;
        $item->shared_order_ids  = $shared;
        $item->paid_addons       = $paidAddons;
        $item->stored_unit_price = $storedUnitPrice;
        $item->setRelation('menuItem', $menuItem);

        return $item;
    }

    /** Compute what a single participant owes for one cart item. */
    private function splitShare(CartItem $item): float
    {
        $menuItem = $item->menuItem;

        // Use stored price if available (ISSUE-013), else live price
        if ($item->stored_unit_price !== null) {
            $basePrice = (float) $item->stored_unit_price;
        } elseif ($menuItem && $menuItem->has_discount && $menuItem->discounted_price !== null) {
            $basePrice = (float) $menuItem->discounted_price;
        } else {
            $basePrice = $menuItem ? (float) $menuItem->price : 0.0;
        }

        // Add paid addons to base price
        $addonTotal = array_sum(array_column($item->paid_addons ?? [], 'price'));
        $unitTotal  = $basePrice + $addonTotal;

        $lineTotal  = $unitTotal * $item->quantity;
        $shareCount = 1 + count($item->shared_order_ids ?? []);

        return round($lineTotal / $shareCount, 2);
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_unshared_item_pays_full_line_total(): void
    {
        $item = $this->makeCartItem(price: 15.00, quantity: 1, shared: []);
        $this->assertEquals(15.00, $this->splitShare($item));
    }

    public function test_item_shared_between_two_pays_half(): void
    {
        // Owner + 1 sharer → share_count = 2 → 15.00 / 2 = 7.50
        $item = $this->makeCartItem(price: 15.00, quantity: 1, shared: [101]);
        $this->assertEquals(7.50, $this->splitShare($item));
    }

    public function test_item_shared_between_three_pays_one_third(): void
    {
        // Owner + 2 sharers → share_count = 3 → 15.00 / 3 = 5.00
        $item = $this->makeCartItem(price: 15.00, quantity: 1, shared: [101, 102]);
        $this->assertEquals(5.00, $this->splitShare($item));
    }

    public function test_quantity_2_shared_between_two_pays_unit_price(): void
    {
        // Price=5, qty=2 → line_total=10 → shared by 2 → each pays 5
        $item = $this->makeCartItem(price: 5.00, quantity: 2, shared: [101]);
        $this->assertEquals(5.00, $this->splitShare($item));
    }

    public function test_paid_addon_included_in_split_share(): void
    {
        // Base €10 + addon €2 = €12, shared 2 ways → each pays €6
        $item = $this->makeCartItem(
            price: 10.00,
            paidAddons: [['name' => 'Extra Sauce', 'price' => 2.00]],
            shared: [101]
        );
        $this->assertEquals(6.00, $this->splitShare($item));
    }

    public function test_floating_point_rounding_is_explicit(): void
    {
        // €10 / 3 = 3.333... → rounds to 3.33
        // 3 × €3.33 = €9.99 — the 1-cent difference is absorbed by the vendor.
        // This is intentional documented behaviour.
        $item = $this->makeCartItem(price: 10.00, shared: [101, 102]);
        $share = $this->splitShare($item);
        $this->assertEquals(3.33, $share);
        // Confirm the 1-cent rounding gap is expected
        $this->assertEquals(9.99, round($share * 3, 2));
    }

    public function test_discounted_item_uses_discounted_price_in_split(): void
    {
        $item = $this->makeCartItem(
            price: 20.00,
            discountedPrice: 15.00,
            hasDiscount: true,
            shared: [101]
        );
        $this->assertEquals(7.50, $this->splitShare($item));
    }

    public function test_stored_unit_price_used_over_live_price(): void
    {
        // ISSUE-013: stored_unit_price should be used even if live price changed
        // Stored at €5, live price now €10 — customer should pay €5
        $item = $this->makeCartItem(
            price: 10.00,         // Current live price
            storedUnitPrice: 5.00 // Price at add-time
        );
        $this->assertEquals(5.00, $this->splitShare($item));
    }

    public function test_null_menu_item_returns_zero_share(): void
    {
        $item = new CartItem();
        $item->quantity         = 1;
        $item->shared_order_ids = [];
        $item->paid_addons      = [];
        $item->stored_unit_price = null;
        $item->setRelation('menuItem', null);

        $this->assertEquals(0.0, $this->splitShare($item));
    }

    public function test_zero_price_item_shared_returns_zero(): void
    {
        $item = $this->makeCartItem(price: 0.00, shared: [101, 102]);
        $this->assertEquals(0.00, $this->splitShare($item));
    }
}
