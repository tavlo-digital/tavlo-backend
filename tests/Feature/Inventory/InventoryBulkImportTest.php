<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryItem;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryBulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_and_update_inventory_items_in_bulk(): void
    {
        $vendor = Vendor::factory()->create();
        $category = $vendor->inventoryCategories()->create([
            'name' => 'Existing Category',
            'sort_order' => 0,
        ]);
        $existing = $vendor->inventoryItems()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Tomatoes',
            'category' => $category->name,
            'quantity' => 5,
            'unit' => 'kg',
            'min_stock' => 2,
            'reorder_quantity' => 8,
            'cost_per_unit' => 4.25,
            'supplier' => 'Existing Supplier',
            'track_stock' => true,
        ]);

        $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/inventory/items/bulk",
            [
                'items' => [
                    [
                        'ingredientName' => 'Tomatoes',
                        'unit' => 'boxes',
                        'currentStock' => 12,
                    ],
                    [
                        'ingredientName' => 'Mozzarella',
                        'unit' => 'kg',
                        'category' => 'Dairy',
                        'currentStock' => 7.5,
                        'reorderLevel' => 3,
                        'reorderQuantity' => 10,
                        'supplier' => 'Italian Foods',
                        'costPerUnit' => 8.75,
                    ],
                ],
            ],
            $this->vendorHeaders($vendor),
        )
            ->assertOk()
            ->assertExactJson([
                'created' => 1,
                'updated' => 1,
                'skipped' => 0,
                'errors' => [],
            ]);

        $existing->refresh();
        $this->assertSame('boxes', $existing->unit);
        $this->assertSame('12.00', $existing->quantity);
        $this->assertSame($category->id, $existing->inventory_category_id);
        $this->assertSame('Existing Category', $existing->category);
        $this->assertSame('2.00', $existing->min_stock);
        $this->assertSame('8.00', $existing->reorder_quantity);
        $this->assertSame('4.2500', $existing->cost_per_unit);
        $this->assertSame('Existing Supplier', $existing->supplier);
        $this->assertTrue($existing->track_stock);

        $created = InventoryItem::where('vendor_id', $vendor->id)
            ->where('name', 'Mozzarella')
            ->firstOrFail();
        $this->assertSame('Dairy', $created->category);
        $this->assertSame('7.50', $created->quantity);
        $this->assertSame('8.7500', $created->cost_per_unit);
        $this->assertDatabaseHas('inventory_categories', [
            'vendor_id' => $vendor->id,
            'name' => 'Dairy',
        ]);
        $this->assertDatabaseHas('inventory_item_translations', [
            'inventory_item_id' => $created->id,
            'language' => 'en',
            'name' => 'Mozzarella',
            'supplier' => 'Italian Foods',
        ]);
    }

    public function test_invalid_bulk_payload_does_not_partially_change_inventory(): void
    {
        $vendor = Vendor::factory()->create();

        $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/inventory/items/bulk",
            [
                'items' => [
                    ['ingredientName' => 'Valid Row', 'unit' => 'kg'],
                    ['ingredientName' => 'Invalid Row', 'unit' => 'kg', 'currentStock' => -1],
                ],
            ],
            $this->vendorHeaders($vendor),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.1.currentStock');

        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_vendor_cannot_bulk_import_inventory_for_another_vendor(): void
    {
        $vendor = Vendor::factory()->create();
        $otherVendor = Vendor::factory()->create();

        $this->postJson(
            "/api/vendor/{$otherVendor->vendor_public_id}/inventory/items/bulk",
            ['items' => [['ingredientName' => 'Tomatoes', 'unit' => 'kg']]],
            $this->vendorHeaders($vendor),
        )->assertForbidden();

        $this->assertDatabaseCount('inventory_items', 0);
    }

    private function vendorHeaders(Vendor $vendor): array
    {
        $token = $vendor->createToken('inventory-import-test')->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }
}
