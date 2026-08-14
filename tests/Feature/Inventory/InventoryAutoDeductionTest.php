<?php

namespace Tests\Feature\Inventory;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\InventorySettings;
use App\Models\MenuCategory;
use App\Models\MenuItemIngredient;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAutoDeductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_serving_an_order_deducts_recipe_stock_once_with_unit_conversion(): void
    {
        [$vendor, $order, $cartItem, $inventoryItem] = $this->orderWithRecipe(true);

        $headers = $this->vendorHeaders($vendor);
        $this->patchJson("/api/vendor/orders/{$order->id}/served", [], $headers)->assertOk();

        $this->assertSame('9.80', $inventoryItem->fresh()->quantity);
        $this->assertNotNull($cartItem->fresh()->inventory_deducted_at);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'inventory_item_id' => $inventoryItem->id,
            'order_id' => $order->id,
            'cart_item_id' => $cartItem->id,
            'type' => 'order',
            'quantity_before' => 10,
            'quantity_after' => 9.8,
            'quantity_change' => -0.2,
        ]);

        $this->patchJson("/api/vendor/orders/{$order->id}/served", [], $headers)->assertOk();
        $this->assertSame('9.80', $inventoryItem->fresh()->quantity);
        $this->assertDatabaseCount('inventory_stock_movements', 1);
    }

    public function test_disabled_auto_deduction_leaves_stock_unchanged(): void
    {
        [$vendor, $order, $cartItem, $inventoryItem] = $this->orderWithRecipe(false);

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/served",
            [],
            $this->vendorHeaders($vendor),
        )->assertOk();

        $this->assertSame('10.00', $inventoryItem->fresh()->quantity);
        $this->assertNull($cartItem->fresh()->inventory_deducted_at);
        $this->assertDatabaseCount('inventory_stock_movements', 0);
    }

    public function test_order_level_completion_deducts_items_without_item_completion_timestamps(): void
    {
        [$vendor, $order, $cartItem, $inventoryItem] = $this->orderWithRecipe(true);

        $this->patchJson(
            "/api/vendor/orders/{$order->id}",
            ['status' => Order::STATUS_SERVED],
            $this->vendorHeaders($vendor),
        )->assertOk();

        $this->assertNull($cartItem->served_at);
        $this->assertSame('9.80', $inventoryItem->fresh()->quantity);
        $this->assertNotNull($cartItem->fresh()->inventory_deducted_at);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'inventory_item_id' => $inventoryItem->id,
            'order_id' => $order->id,
            'cart_item_id' => $cartItem->id,
            'type' => 'order',
        ]);
    }

    private function orderWithRecipe(bool $autoDeduction): array
    {
        $vendor = Vendor::factory()->create();
        InventorySettings::create([
            'vendor_id' => $vendor->id,
            'link_menu_items' => $autoDeduction,
            'settings' => [
                'general' => [
                    'enableInventoryTracking' => true,
                    'enableAutoStockDeduction' => $autoDeduction,
                    'allowNegativeStock' => false,
                ],
            ],
        ]);
        $inventoryItem = $vendor->inventoryItems()->create([
            'name' => 'Tomatoes',
            'quantity' => 10,
            'unit' => 'kg',
            'min_stock' => 1,
            'reorder_quantity' => 5,
            'cost_per_unit' => 2,
            'track_stock' => true,
        ]);
        $category = MenuCategory::create([
            'vendor_id' => $vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $menuItem = $vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name' => 'Tomato Pasta',
            'price' => 12,
            'available' => true,
            'is_active' => true,
        ]);
        MenuItemIngredient::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 100,
            'unit' => 'g',
            'is_critical' => true,
        ]);

        $customer = Customer::factory()->create();
        $table = RestaurantTable::create([
            'vendor_id' => $vendor->id,
            'number' => 1,
            'name' => 'Table 1',
            'qr_token' => 'inventory-test-'.uniqid(),
            'is_active' => true,
        ]);
        $session = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);
        $order = Order::create([
            'order_public_id' => 'ord-inventory-test-'.uniqid(),
            'order_number' => 1001,
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'amount' => 24,
            'currency' => 'EUR',
            'payment_method' => 'cash',
            'order_type' => 'dine-in',
        ]);
        $cartItem = CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 2,
            'ready_at' => now(),
        ]);

        return [$vendor, $order, $cartItem, $inventoryItem];
    }

    private function vendorHeaders(Vendor $vendor): array
    {
        $token = $vendor->createToken('inventory-deduction-test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
