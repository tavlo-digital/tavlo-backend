<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\MenuCategory;
use App\Models\MenuItemIngredient;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_details_returns_real_recipe_links_and_recorded_stock_activity(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/inventory/items",
            [
                'name' => 'Tomatoes',
                'quantity' => 10,
                'unit' => 'kg',
                'minStock' => 2,
                'reorderQuantity' => 8,
                'costPerUnit' => 3.5,
                'trackStock' => true,
            ],
            $this->vendorHeaders($vendor),
        )->assertCreated();

        $inventoryItem = InventoryItem::findOrFail((int) $response->json('id'));
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
            'price' => 14,
            'available' => false,
            'is_active' => true,
        ]);
        MenuItemIngredient::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 0.25,
            'unit' => 'kg',
            'is_critical' => true,
        ]);

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/inventory/items/{$inventoryItem->id}/details",
            $this->vendorHeaders($vendor),
        )
            ->assertOk()
            ->assertJsonPath('affectedMenuItems.0.name', 'Tomato Pasta')
            ->assertJsonPath('affectedMenuItems.0.quantity', 0.25)
            ->assertJsonPath('affectedMenuItems.0.isCritical', true)
            ->assertJsonPath('affectedMenuItems.0.available', false)
            ->assertJsonPath('activityLog.0.source', 'Initial Stock')
            ->assertJsonPath('activityLog.0.amount', 10)
            ->assertJsonPath('activityLog.0.quantityBefore', 0)
            ->assertJsonPath('activityLog.0.quantityAfter', 10);
    }

    public function test_adjusting_stock_updates_quantity_and_appends_a_real_activity_entry(): void
    {
        $vendor = Vendor::factory()->create();
        $item = $vendor->inventoryItems()->create([
            'name' => 'Basil',
            'quantity' => 10,
            'unit' => 'kg',
            'min_stock' => 2,
            'reorder_quantity' => 5,
            'cost_per_unit' => 1,
            'track_stock' => true,
        ]);

        $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/inventory/items/{$item->id}/adjust-stock",
            [
                'amount' => -2.5,
                'type' => 'waste',
                'reason' => 'Spoiled batch',
            ],
            $this->vendorHeaders($vendor),
        )
            ->assertOk()
            ->assertJsonPath('item.quantity', 7.5)
            ->assertJsonPath('activity.type', 'waste')
            ->assertJsonPath('activity.source', 'Waste')
            ->assertJsonPath('activity.amount', -2.5)
            ->assertJsonPath('activity.note', 'Spoiled batch')
            ->assertJsonPath('activity.user', 'Manager');

        $this->assertSame('7.50', $item->fresh()->quantity);
        $this->assertDatabaseHas('inventory_stock_movements', [
            'inventory_item_id' => $item->id,
            'quantity_before' => 10,
            'quantity_after' => 7.5,
            'quantity_change' => -2.5,
            'note' => 'Spoiled batch',
        ]);
    }

    public function test_adjustment_cannot_make_stock_negative(): void
    {
        $vendor = Vendor::factory()->create();
        $item = $vendor->inventoryItems()->create([
            'name' => 'Basil',
            'quantity' => 1,
            'unit' => 'kg',
            'min_stock' => 0,
            'reorder_quantity' => 0,
            'cost_per_unit' => 0,
        ]);

        $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/inventory/items/{$item->id}/adjust-stock",
            ['amount' => -2, 'type' => 'waste'],
            $this->vendorHeaders($vendor),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->assertSame('1.00', $item->fresh()->quantity);
        $this->assertSame(0, InventoryStockMovement::count());
    }

    public function test_vendor_cannot_read_another_vendor_inventory_details(): void
    {
        $vendor = Vendor::factory()->create();
        $otherVendor = Vendor::factory()->create();
        $item = $otherVendor->inventoryItems()->create([
            'name' => 'Private ingredient',
            'quantity' => 1,
            'unit' => 'kg',
            'min_stock' => 0,
            'reorder_quantity' => 0,
            'cost_per_unit' => 0,
        ]);

        $this->getJson(
            "/api/vendor/{$otherVendor->vendor_public_id}/inventory/items/{$item->id}/details",
            $this->vendorHeaders($vendor),
        )->assertForbidden();
    }

    private function vendorHeaders(Vendor $vendor): array
    {
        $token = $vendor->createToken('inventory-details-test')->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }
}
