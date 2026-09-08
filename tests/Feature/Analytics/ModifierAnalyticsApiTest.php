<?php

namespace Tests\Feature\Analytics;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * Covers App\Services\Analytics\ModifierAnalyticsService via the `modifiers`
 * key of GET /analytics — revenue attribution for both customization
 * systems (free-text menu-item addons and structured modifier groups),
 * selection-rate maths, and the derived recommendation lists.
 *
 * No TaxCategory rows are seeded in these tests, so every VAT lookup falls
 * through to 0% (see TaxCalculationService::resolveVatRate) — gross equals
 * net throughout, which keeps the money assertions simple without weakening
 * the thing actually under test (attribution and rate maths, not VAT math,
 * which is already covered by VendorAnalyticsApiTest).
 */
class ModifierAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    public function test_modifiers_is_unavailable_without_any_orders(): void
    {
        $vendor = $this->vendor();

        $body = $this->modifiers($vendor);

        $this->assertFalse($body['available']);
        $this->assertEquals(0, $body['ordersAnalyzed']);
    }

    public function test_paid_addon_revenue_and_selection_rate_are_computed_from_cart_snapshot(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor, 'Starters');

        $item = $this->menuItem($vendor, $category, 'Bruschetta', 8.00, [
            'paid_addons' => [['id' => 1, 'name' => 'Extra Cheese', 'price' => 2.00]],
        ]);

        // 20 orders total; 10 of them add the paid addon.
        for ($i = 0; $i < 20; $i++) {
            $order = $this->order($vendor, $customer, $session);
            $addons = $i < 10 ? [['id' => 1, 'price' => 2.00]] : [];
            $this->cartItem($session, $item, $order, ['paid_addons' => $addons]);
        }

        $body = $this->modifiers($vendor);
        $entry = $this->find($body['paidAddonsRevenue'], 'Extra Cheese');

        $this->assertNotNull($entry);
        $this->assertEquals(20.0, $entry['revenue']);
        $this->assertEquals(10, $entry['timesSelected']);
        $this->assertEquals(20, $entry['eligible']);
        $this->assertEquals(50.0, $entry['selectionRatePercent']);
        $this->assertEquals(2.0, $entry['price']);

        // 50% clears the 40% promotion threshold.
        $this->assertNotNull($this->find($body['promotionCandidates'], 'Extra Cheese'));
    }

    public function test_free_addon_is_tracked_with_zero_revenue_and_excluded_from_paid_revenue(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor, 'Drinks');

        $item = $this->menuItem($vendor, $category, 'Iced Tea', 4.00, [
            'free_addons' => [['id' => 5, 'name' => 'No Ice']],
        ]);

        for ($i = 0; $i < 12; $i++) {
            $order = $this->order($vendor, $customer, $session);
            $this->cartItem($session, $item, $order, ['free_addons' => [5]]);
        }

        $body = $this->modifiers($vendor);

        $this->assertNull($this->find($body['paidAddonsRevenue'], 'No Ice'));
        $entry = $this->find($body['mostPopular'], 'No Ice');
        $this->assertNotNull($entry);
        $this->assertEquals('free_addon', $entry['kind']);
        $this->assertEquals(0.0, $entry['revenue']);
        $this->assertEquals(12, $entry['timesSelected']);
        $this->assertEquals(100.0, $entry['selectionRatePercent']);
    }

    public function test_removable_item_removal_rate_and_significant_removal_recommendation(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor, 'Sides');

        $item = $this->menuItem($vendor, $category, 'Fries', 4.50, [
            'removable_items' => [['id' => 7, 'name' => 'Salt']],
        ]);

        // 20 orders; salt removed on 6 of them (30% — clears the 20% bar).
        for ($i = 0; $i < 20; $i++) {
            $order = $this->order($vendor, $customer, $session);
            $removed = $i < 6 ? [7] : [];
            $this->cartItem($session, $item, $order, ['removed_items' => $removed]);
        }

        $body = $this->modifiers($vendor);
        $entry = $this->find($body['removableItems'], 'Salt');

        $this->assertNotNull($entry);
        $this->assertEquals(6, $entry['timesSelected']);
        $this->assertEquals(30.0, $entry['removalRatePercent']);

        $flagged = $this->find($body['significantRemovals'], 'Salt');
        $this->assertNotNull($flagged);
        $this->assertStringContainsString('Fries', $flagged['recommendation']);
        $this->assertStringContainsString('Salt', $flagged['recommendation']);
    }

    public function test_never_selected_configured_addon_is_flagged_for_removal(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor, 'Mains');

        $item = $this->menuItem($vendor, $category, 'Burger', 12.00, [
            'paid_addons' => [['id' => 9, 'name' => 'Truffle Sauce', 'price' => 3.00]],
        ]);

        // Ordered 15 times; the addon is configured but picked zero times.
        for ($i = 0; $i < 15; $i++) {
            $order = $this->order($vendor, $customer, $session);
            $this->cartItem($session, $item, $order, []);
        }

        $body = $this->modifiers($vendor);
        $entry = $this->find($body['underperforming'], 'Truffle Sauce');

        $this->assertNotNull($entry);
        $this->assertEquals(0, $entry['timesSelected']);
        $this->assertEquals(0.0, $entry['selectionRatePercent']);
        $this->assertStringContainsString('Never selected', $entry['recommendation']);
        $this->assertEquals(1, $body['summary']['configuredNeverSelectedCount']);
    }

    public function test_structured_modifier_option_revenue_is_computed_and_distinguished_from_addons(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor, 'Pasta');

        $item = $this->menuItem($vendor, $category, 'Carbonara', 14.00);

        $group = ModifierGroup::create([
            'vendor_id' => $vendor->id, 'name' => 'Extra Toppings', 'type' => 'multiple',
            'min_selection' => 0, 'max_selection' => 3, 'is_required' => false, 'is_active' => true,
        ]);
        $option = ModifierOption::create([
            'modifier_group_id' => $group->id, 'name' => 'Bacon', 'price_adjustment' => 1.50, 'is_active' => true,
        ]);
        $item->modifierGroups()->attach($group->id, ['sort_order' => 0]);

        for ($i = 0; $i < 10; $i++) {
            $order = $this->order($vendor, $customer, $session);
            $modifiers = $i < 4 ? [[
                'modifier_group_id' => $group->id, 'type' => 'multiple', 'tax_category' => null,
                'options' => [['id' => $option->id, 'price_adjustment' => 1.50]],
            ]] : [];
            $this->cartItem($session, $item, $order, ['selected_modifiers' => $modifiers]);
        }

        $body = $this->modifiers($vendor);
        $entry = $this->find($body['paidAddonsRevenue'], 'Bacon');

        $this->assertNotNull($entry);
        $this->assertEquals('modifier', $entry['system']);
        $this->assertEquals('Extra Toppings', $entry['groupName']);
        $this->assertTrue($entry['allowsMultiple']);
        $this->assertEquals(6.0, $entry['revenue']);
        $this->assertEquals(4, $entry['timesSelected']);
        $this->assertEquals(40.0, $entry['selectionRatePercent']);
    }

    public function test_new_addon_opportunity_flags_item_missing_paid_addons_in_a_strong_category(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor, 'Starters');

        $withAddon = $this->menuItem($vendor, $category, 'Bruschetta', 8.00, [
            'paid_addons' => [['id' => 1, 'name' => 'Extra Cheese', 'price' => 2.00]],
        ]);
        $withoutAddon = $this->menuItem($vendor, $category, 'Caprese Salad', 9.00);

        // Bruschetta: strong 50% adoption on its paid addon.
        for ($i = 0; $i < 20; $i++) {
            $order = $this->order($vendor, $customer, $session);
            $addons = $i < 10 ? [['id' => 1, 'price' => 2.00]] : [];
            $this->cartItem($session, $withAddon, $order, ['paid_addons' => $addons]);
        }

        // Caprese Salad: real volume, but no paid add-on configured at all.
        for ($i = 0; $i < 15; $i++) {
            $order = $this->order($vendor, $customer, $session);
            $this->cartItem($session, $withoutAddon, $order, []);
        }

        $body = $this->modifiers($vendor);
        $opportunity = collect($body['newAddonOpportunities'])->firstWhere('menuItemName', 'Caprese Salad');

        $this->assertNotNull($opportunity);
        $this->assertEquals('Starters', $opportunity['categoryName']);
        $this->assertEquals(15, $opportunity['eligibleQty']);
        $this->assertEquals(50.0, $opportunity['categoryBestAdoptionPercent']);
    }

    public function test_pricing_analysis_verdicts_reflect_relative_adoption(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor, 'Mains');

        $high = $this->menuItem($vendor, $category, 'High Adoption Item', 10.00, [
            'paid_addons' => [['id' => 1, 'name' => 'High Addon', 'price' => 1.00]],
        ]);
        $mid = $this->menuItem($vendor, $category, 'Mid Adoption Item', 10.00, [
            'paid_addons' => [['id' => 2, 'name' => 'Mid Addon', 'price' => 1.00]],
        ]);
        $low = $this->menuItem($vendor, $category, 'Low Adoption Item', 10.00, [
            'paid_addons' => [['id' => 3, 'name' => 'Low Addon', 'price' => 1.00]],
        ]);

        $this->seedAdoption($vendor, $customer, $session, $high, 1, 'High Addon', 1.00, 20, 16); // 80%
        $this->seedAdoption($vendor, $customer, $session, $mid, 2, 'Mid Addon', 1.00, 20, 10);   // 50%
        $this->seedAdoption($vendor, $customer, $session, $low, 3, 'Low Addon', 1.00, 20, 2);    // 10%

        $body = $this->modifiers($vendor);
        $this->assertTrue($body['pricingAnalysis']['available']);

        $items = $body['pricingAnalysis']['items'];
        $this->assertEquals('possibly_underpriced', $this->find($items, 'High Addon')['verdict']);
        $this->assertEquals('appropriately_priced', $this->find($items, 'Mid Addon')['verdict']);
        $this->assertEquals('possibly_overpriced', $this->find($items, 'Low Addon')['verdict']);
    }

    public function test_shared_order_addon_revenue_is_split_between_sharers_not_doubled(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $category = $this->category($vendor, 'Starters');
        $item = $this->menuItem($vendor, $category, 'Shared Plate', 10.00, [
            'paid_addons' => [['id' => 1, 'name' => 'Extra Cheese', 'price' => 2.00]],
        ]);

        // A second session so the "sharer" order isn't the same dine-in
        // session as the owner (ShareOrderService's own exclusion rule).
        $table2 = $this->table($vendor, 2);
        $session2 = TableScanSession::create([
            'vendor_id' => $vendor->id, 'restaurant_table_id' => $table2->id, 'customer_id' => $customer->id,
            'pin' => '4242', 'type' => 'dine_in', 'status' => 'active', 'scanned_at' => now(),
        ]);

        $owner = $this->order($vendor, $customer, $session);
        $sharer = $this->order($vendor, $customer, $session2);

        $this->cartItem($session, $item, $owner, [
            'paid_addons' => [['id' => 1, 'price' => 2.00]],
            'shared_order_ids' => [$sharer->id],
        ]);

        $body = $this->modifiers($vendor);
        $entry = $this->find($body['paidAddonsRevenue'], 'Extra Cheese');

        // Selection counts dedupe by cart-item id first — same convention
        // as VendorAnalyticsService::aggregateLines() — so a physically
        // single shared dish counts as ONE eligible order and ONE
        // selection, not two. Revenue does NOT dedupe: it's attributed once
        // per order that owns a share, each divided by the share count, so
        // the 2.00 charged is correctly split 1.00 + 1.00 = 2.00, never
        // doubled to 4.00.
        $this->assertNotNull($entry);
        $this->assertEquals(1, $entry['eligible']);
        $this->assertEquals(1, $entry['timesSelected']);
        $this->assertEquals(2.0, $entry['revenue']);
    }

    // ---------------------------------------------------------------- helpers

    private function seedAdoption(
        Vendor $vendor, Customer $customer, TableScanSession $session, MenuItem $item,
        int $addonId, string $addonName, float $price, int $orders, int $selected,
    ): void {
        for ($i = 0; $i < $orders; $i++) {
            $order = $this->order($vendor, $customer, $session);
            $addons = $i < $selected ? [['id' => $addonId, 'price' => $price]] : [];
            $this->cartItem($session, $item, $order, ['paid_addons' => $addons]);
        }
    }

    private function find(array $rows, string $name): ?array
    {
        foreach ($rows as $row) {
            if (($row['name'] ?? null) === $name) {
                return $row;
            }
        }

        return null;
    }

    /**
     * GB has a 0% food VAT rate in the seeded tax_categories reference data
     * (see 2026_03_30_000001_create_tax_categories_table.php), so gross
     * equals net for every food-tax-category addon/modifier here — that
     * keeps this file's money assertions simple without weakening what's
     * actually under test (attribution and rate maths, not VAT math, which
     * VendorAnalyticsApiTest already covers with a real rate).
     */
    private function vendor(): Vendor
    {
        return $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'GB']));
    }

    /** @return array{0: Customer, 1: TableScanSession} */
    private function context(Vendor $vendor): array
    {
        $customer = Customer::factory()->create();
        $table = $this->table($vendor, 1);

        $session = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '9001',
            'type' => 'dine_in',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        return [$customer, $session];
    }

    private function table(Vendor $vendor, int $number): RestaurantTable
    {
        return $vendor->restaurantTables()->create([
            'number' => $number,
            'name' => "Table {$number}",
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
    }

    private function category(Vendor $vendor, string $name): MenuCategory
    {
        return MenuCategory::create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function menuItem(Vendor $vendor, MenuCategory $category, string $name, float $price, array $customizations = []): MenuItem
    {
        return $vendor->menuItems()->create(array_merge([
            'menu_category_id' => $category->id,
            'name' => $name,
            'price' => $price,
            'vat_rate' => 0,
            'available' => true,
            'is_active' => true,
            'ordered_count' => 0,
        ], $customizations));
    }

    private function order(Vendor $vendor, ?Customer $customer, TableScanSession $session, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'vendor_id' => $vendor->id,
            'customer_id' => $customer?->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_SERVED,
            'order_type' => 'dine_in',
            'amount' => 10,
            'vat_amount' => 0,
            'service_fee' => 0,
            'tip_amount' => 0,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'payment_received' => true,
            'confirmed_at' => now()->subMinutes(30),
            'in_progress_at' => now()->subMinutes(28),
            'served_at' => now()->subMinutes(10),
            'payment_confirmed_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(30),
        ], $attributes));
    }

    private function cartItem(TableScanSession $session, MenuItem $item, Order $order, array $customizations): CartItem
    {
        return CartItem::create(array_merge([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $item->id,
            'order_id' => $order->id,
            'quantity' => 1,
        ], $customizations));
    }

    private function modifiers(Vendor $vendor): array
    {
        return $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period=custom&from=".now()->subDays(60)->format('Y-m-d').'&to='.now()->addDay()->format('Y-m-d'),
            $this->headers($vendor),
        )->assertOk()->json('modifiers');
    }

    private function headers(Vendor $vendor): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
