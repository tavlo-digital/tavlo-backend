<?php

namespace Tests\Feature\Menu;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
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

    public function test_bulk_import_uses_the_validated_vendor_category_id(): void
    {
        $this->postJson('/api/vendor/menu/items/bulk', [
            'items' => [[
                'name' => 'Category ID Item',
                'categoryId' => $this->category->id,
                'price' => 9.5,
            ]],
        ], $this->headers())
            ->assertOk()
            ->assertJson([
                'created' => 1,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ]);

        $this->assertDatabaseHas('menu_items', [
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $this->category->id,
            'name' => 'Category ID Item',
            'price' => 9.5,
        ]);
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

    public function test_full_spreadsheet_payload_imports_translations_recipe_and_customizations(): void
    {
        $beef = $this->vendor->inventoryItems()->create([
            'name' => 'Beef Patty',
            'quantity' => 20,
            'unit' => 'kg',
            'track_stock' => true,
        ]);
        $cheddar = $this->vendor->inventoryItems()->create([
            'name' => 'Cheddar',
            'quantity' => 20,
            'unit' => 'piece',
            'track_stock' => true,
        ]);
        $modifierGroup = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Burger Extras',
            'type' => 'multiple',
            'min_selection' => 0,
            'max_selection' => 3,
            'is_required' => false,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->postJson('/api/vendor/menu/items/bulk', [
            'items' => [[
                'name' => 'House Smash',
                'category' => 'Main Courses',
                'price' => 14.9,
                'description' => 'Two beef patties',
                'translations' => [
                    'en' => ['name' => 'House Smash', 'description' => 'Two beef patties'],
                    'de' => ['name' => 'Haus Smash'],
                    'ar' => ['name' => 'هاوس سماش'],
                ],
                'imageUrl' => 'https://cdn.example.com/house-smash.jpg',
                'available' => true,
                'hasDiscount' => true,
                'discountPercent' => 10,
                'taxCategory' => 'Food',
                'dietaryPreference' => 'Vegan',
                'allergies' => ['Gluten', 'Milk', 'Mustard'],
                'specialTags' => ['Popular', 'Spicy'],
                'calories' => 450,
                'fat' => 32,
                'carbs' => 25,
                'protein' => 41,
                'manualNutritionOverride' => true,
                'ingredients' => [
                    ['ingredientName' => 'Beef Patty', 'quantity' => 0.5, 'isCritical' => true],
                    ['ingredientName' => 'Cheddar', 'quantity' => 1, 'isCritical' => false],
                ],
                'modifierGroupNames' => ['Burger Extras'],
                'paidAddons' => [[
                    'name' => 'Extra Patty',
                    'price' => 3.5,
                    'taxCategory' => 'Food',
                    'translations' => ['en' => ['name' => 'Extra Patty'], 'de' => ['name' => 'Extra Patty DE']],
                ]],
                'freeAddons' => [[
                    'name' => 'Mayonnaise',
                    'translations' => ['en' => ['name' => 'Mayonnaise'], 'de' => ['name' => 'Mayonnaise DE']],
                ]],
                'removableItems' => [[
                    'name' => 'Onion',
                    'translations' => ['en' => ['name' => 'Onion'], 'de' => ['name' => 'Zwiebel']],
                ]],
            ]],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('skipped', 0);

        $item = MenuItem::where('vendor_id', $this->vendor->id)->where('name', 'House Smash')->firstOrFail();
        $this->assertTrue($item->manual_nutrition_override);
        $this->assertSame('food', $item->tax_category);
        $this->assertSame('vegan', $item->dietary_preference);
        $this->assertSame(['gluten', 'milk', 'mustard'], $item->allergies);
        $this->assertSame(['popular', 'spicy'], $item->special_tags);
        $this->assertSame('Haus Smash', $item->itemTranslations()->where('language', 'de')->value('name'));
        $this->assertSame([$modifierGroup->id], $item->modifierGroups()->pluck('modifier_groups.id')->all());
        $this->assertDatabaseHas('menu_item_ingredients', [
            'menu_item_id' => $item->id,
            'inventory_item_id' => $beef->id,
            'quantity' => 0.5,
            'unit' => 'kg',
            'is_critical' => true,
        ]);
        $this->assertDatabaseHas('menu_item_ingredients', [
            'menu_item_id' => $item->id,
            'inventory_item_id' => $cheddar->id,
            'quantity' => 1,
            'unit' => 'piece',
            'is_critical' => false,
        ]);
        $this->assertSame('Extra Patty', $item->paid_addons[0]['name']);
        $this->assertSame('Extra Patty DE', $item->paid_addons[0]['translations']['de']['name']);
        $this->assertSame('Mayonnaise DE', $item->free_addons[0]['translations']['de']['name']);
        $this->assertSame('Zwiebel', $item->removable_items[0]['translations']['de']['name']);
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
