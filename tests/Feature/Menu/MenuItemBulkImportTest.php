<?php

namespace Tests\Feature\Menu;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\TaxCategory;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemBulkImportTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private MenuCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);
        $taxCategory = TaxCategory::where(['country' => 'AT', 'slug' => 'food'])->firstOrFail();
        $this->category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Main Courses',
            'slug' => 'main-courses',
            'tax_category_id' => $taxCategory->id,
            'default_tax_category' => 'food',
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function headers(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    public function test_bulk_import_creates_menu_items_with_optional_fields(): void
    {
        $this->postJson('/api/vendor/menu/items/bulk', [
            'items' => [[
                'name' => 'Margherita Pizza',
                'category' => 'main courses',
                'price' => 12.5,
                'description' => 'Tomato, mozzarella, and basil',
                'available' => false,
                'calories' => 720,
                'allergies' => ['gluten', 'dairy'],
                'specialTags' => ['popular'],
                'discountPercent' => 10,
            ]],
        ], $this->headers())
            ->assertOk()
            ->assertJson([
                'created' => 1,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ]);

        $item = MenuItem::where('vendor_id', $this->vendor->id)->firstOrFail();
        $this->assertSame('Margherita Pizza', $item->name);
        $this->assertSame($this->category->id, $item->menu_category_id);
        $this->assertSame(12.5, (float) $item->price);
        $this->assertFalse($item->available);
        $this->assertTrue($item->has_discount);
        $this->assertSame(11.25, (float) $item->discounted_price);
        $this->assertSame(['gluten', 'dairy'], $item->allergies);
    }

    public function test_bulk_import_updates_existing_item_and_preserves_omitted_fields(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name' => 'Burger',
            'description' => 'Keep this description',
            'price' => 10,
            'available' => false,
            'is_active' => true,
            'calories' => 500,
        ]);
        $productUid = $item->product_uid;

        $this->postJson('/api/vendor/menu/items/bulk', [
            'items' => [[
                'name' => 'burger',
                'category' => 'Main Courses',
                'price' => 14,
            ]],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('skipped', 0);

        $current = MenuItem::where('vendor_id', $this->vendor->id)->firstOrFail();
        $this->assertNotSame($item->id, $current->id, 'A price change must use normal menu versioning.');
        $this->assertSame($productUid, $current->product_uid);
        $this->assertSame('Keep this description', $current->description);
        $this->assertFalse($current->available);
        $this->assertSame(500, $current->calories);
        $this->assertSoftDeleted('menu_items', ['id' => $item->id]);
    }

    public function test_bulk_import_skips_unknown_categories_without_losing_valid_rows(): void
    {
        $this->postJson('/api/vendor/menu/items/bulk', [
            'items' => [
                ['name' => 'Valid Item', 'category' => 'Main Courses', 'price' => 8],
                ['name' => 'Invalid Item', 'category' => 'Not My Category', 'price' => 9],
            ],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('errors.0.row', 3)
            ->assertJsonPath('errors.0.name', 'Invalid Item')
            ->assertJsonPath('errors.0.message', 'Category "Not My Category" does not exist.');

        $this->assertDatabaseHas('menu_items', ['vendor_id' => $this->vendor->id, 'name' => 'Valid Item']);
        $this->assertDatabaseMissing('menu_items', ['vendor_id' => $this->vendor->id, 'name' => 'Invalid Item']);
    }

    public function test_bulk_import_skips_invalid_row_validation_without_losing_valid_rows(): void
    {
        $this->postJson('/api/vendor/menu/items/bulk', [
            'items' => [
                ['name' => 'Valid Item', 'category' => 'Main Courses', 'price' => 8],
                ['name' => 'Invalid Item', 'category' => 'Main Courses', 'price' => -1],
            ],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('errors.0.row', 3)
            ->assertJsonPath('errors.0.name', 'Invalid Item');

        $this->assertDatabaseHas('menu_items', ['vendor_id' => $this->vendor->id, 'name' => 'Valid Item']);
        $this->assertDatabaseMissing('menu_items', ['vendor_id' => $this->vendor->id, 'name' => 'Invalid Item']);
    }

    public function test_bulk_import_rejects_more_than_500_rows(): void
    {
        $items = collect(range(1, 501))->map(fn (int $number) => [
            'name' => "Item {$number}",
            'category' => 'Main Courses',
            'price' => 1,
        ])->all();

        $this->postJson('/api/vendor/menu/items/bulk', ['items' => $items], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_bulk_import_requires_authentication(): void
    {
        $this->postJson('/api/vendor/menu/items/bulk', [
            'items' => [['name' => 'Pizza', 'category' => 'Main Courses', 'price' => 10]],
        ])->assertUnauthorized();
    }
}
