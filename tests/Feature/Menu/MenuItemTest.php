<?php

namespace Tests\Feature\Menu;

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\OrderItem;
use App\Models\SpecialTag;
use App\Models\TaxCategory;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;
    private MenuCategory $category;
    private TaxCategory $taxFood;
    private Allergen $allergenGluten;
    private Allergen $allergenDairy;
    private SpecialTag $tagPopular;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);

        // Tax categories are seeded by migration
        $this->taxFood = TaxCategory::where(['country' => 'AT', 'slug' => 'food'])->firstOrFail();

        $this->category = MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Starters',
            'slug'                 => 'starters',
            'tax_category_id'      => $this->taxFood->id,
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $this->allergenGluten = Allergen::create(['name' => 'Gluten', 'icon' => '🌾', 'sort_order' => 1, 'is_active' => true]);
        $this->allergenDairy  = Allergen::create(['name' => 'Dairy',  'icon' => '🥛', 'sort_order' => 2, 'is_active' => true]);
        $this->tagPopular     = SpecialTag::create(['slug' => 'popular', 'label' => 'Popular', 'sort_order' => 1, 'is_active' => true]);
    }

    private function authHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function baseItemPayload(array $overrides = []): array
    {
        return array_merge([
            'categoryId'  => $this->category->id,
            'name'        => 'Bruschetta',
            'description' => 'Toasted bread with tomatoes',
            'price'       => 8.90,
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/menu/items
    // ----------------------------------------------------------------

    public function test_index_returns_empty_stats_for_new_vendor(): void
    {
        $this->getJson('/api/vendor/menu/items', $this->authHeaders())
            ->assertOk()
            ->assertJson(['data' => []])
            ->assertJsonPath('stats.totalItems', 0)
            ->assertJsonPath('stats.totalCategories', 1); // category exists from setUp
    }

    public function test_index_returns_active_items_only(): void
    {
        $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Visible',
            'price'            => 8.0,
            'available'        => true,
            'is_active'        => true,
        ]);
        $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Soft Deleted',
            'price'            => 5.0,
            'available'        => false,
            'is_active'        => false,
        ]);

        $this->getJson('/api/vendor/menu/items', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Visible')
            ->assertJsonPath('stats.totalItems', 1);
    }

    public function test_index_filters_by_category_id(): void
    {
        $other = MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Mains',
            'slug'                 => 'mains',
            'default_tax_category' => 'food',
            'sort_order'           => 1,
            'is_active'            => true,
        ]);

        $this->vendor->menuItems()->create(['menu_category_id' => $this->category->id, 'name' => 'Starter Item', 'price' => 8.0, 'available' => true, 'is_active' => true]);
        $this->vendor->menuItems()->create(['menu_category_id' => $other->id, 'name' => 'Main Item', 'price' => 15.0, 'available' => true, 'is_active' => true]);

        $this->getJson("/api/vendor/menu/items?categoryId={$this->category->id}", $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Starter Item');
    }

    public function test_index_filters_by_search(): void
    {
        $this->vendor->menuItems()->create(['menu_category_id' => $this->category->id, 'name' => 'Bruschetta', 'price' => 8.0, 'available' => true, 'is_active' => true]);
        $this->vendor->menuItems()->create(['menu_category_id' => $this->category->id, 'name' => 'Caprese', 'price' => 10.0, 'available' => true, 'is_active' => true]);

        $this->getJson('/api/vendor/menu/items?search=brusc', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bruschetta');
    }

    public function test_index_filters_by_availability(): void
    {
        $this->vendor->menuItems()->create(['menu_category_id' => $this->category->id, 'name' => 'Available Item', 'price' => 8.0, 'available' => true, 'is_active' => true]);
        $this->vendor->menuItems()->create(['menu_category_id' => $this->category->id, 'name' => 'Sold Out', 'price' => 5.0, 'available' => false, 'is_active' => true]);

        $this->getJson('/api/vendor/menu/items?available=true', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Available Item');
    }

    public function test_index_returns_allergies_and_special_tags(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', array_merge($this->baseItemPayload(), [
            'allergies'   => ['gluten'],
            'specialTags' => ['popular'],
        ]), $this->authHeaders());

        $response->assertCreated();

        $this->getJson('/api/vendor/menu/items', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.allergies.0', 'gluten')
            ->assertJsonPath('data.0.specialTags.0', 'popular');
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/menu/items/{id}
    // ----------------------------------------------------------------

    public function test_show_returns_single_item(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Caprese',
            'price'            => 11.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        $this->getJson("/api/vendor/menu/items/{$item->id}", $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.name', 'Caprese');
    }

    public function test_show_returns_404_for_another_vendors_item(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);
        $item = $other->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Private Item',
            'price'            => 9.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        $this->getJson("/api/vendor/menu/items/{$item->id}", $this->authHeaders())
            ->assertNotFound();
    }

    public function test_show_returns_404_for_soft_deleted_item(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Deleted',
            'price'            => 9.0,
            'available'        => false,
            'is_active'        => false,
        ]);

        $this->getJson("/api/vendor/menu/items/{$item->id}", $this->authHeaders())
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/menu/items
    // ----------------------------------------------------------------

    public function test_store_creates_basic_item(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload(), $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Bruschetta')
            ->assertJsonPath('data.price', 8.90)
            ->assertJsonPath('data.categoryId', $this->category->id)
            ->assertJsonPath('data.isActive', true)
            ->assertJsonPath('data.available', true);

        $this->assertDatabaseHas('menu_items', [
            'vendor_id' => $this->vendor->id,
            'name'      => 'Bruschetta',
        ]);
    }

    public function test_store_inherits_vat_from_category(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload(), $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.vatRate', 10)
            ->assertJsonPath('data.taxCategory', 'food');
    }

    public function test_store_persists_allergies(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', array_merge($this->baseItemPayload(), [
            'allergies' => ['gluten', 'dairy'],
        ]), $this->authHeaders());

        $response->assertCreated()
            ->assertJsonCount(2, 'data.allergies')
            ->assertJsonPath('data.allergies.0', 'gluten')
            ->assertJsonPath('data.allergies.1', 'dairy');
    }

    public function test_store_persists_special_tags(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', array_merge($this->baseItemPayload(), [
            'specialTags' => ['popular'],
        ]), $this->authHeaders());

        $response->assertCreated()
            ->assertJsonCount(1, 'data.specialTags')
            ->assertJsonPath('data.specialTags.0', 'popular');
    }

    public function test_store_saves_translations(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', array_merge($this->baseItemPayload(), [
            'translations' => [
                ['language' => 'en', 'name' => 'Toast Bread', 'description' => 'With tomatoes'],
                ['language' => 'de', 'name' => 'Bruschetta', 'description' => 'Mit Tomaten'],
            ],
        ]), $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.translations.en.name', 'Toast Bread')
            ->assertJsonPath('data.translations.de.name', 'Bruschetta')
            ->assertJsonPath('data.translations.de.description', 'Mit Tomaten');
    }

    public function test_store_computes_discounted_price(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', array_merge($this->baseItemPayload(), [
            'price'           => 10.0,
            'hasDiscount'     => true,
            'discountPercent' => 20,
        ]), $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.hasDiscount', true)
            ->assertJsonPath('data.discountPercent', 20)
            ->assertJsonPath('data.discountedPrice', 8);
    }

    public function test_store_requires_name_and_price(): void
    {
        $this->postJson('/api/vendor/menu/items', ['categoryId' => $this->category->id], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price']);
    }

    public function test_store_rejects_category_from_another_vendor(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);
        $otherCat = MenuCategory::create([
            'vendor_id'            => $other->id,
            'name'                 => 'Other',
            'slug'                 => 'other',
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $this->postJson('/api/vendor/menu/items', array_merge($this->baseItemPayload(), [
            'categoryId' => $otherCat->id,
        ]), $this->authHeaders())
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/menu/items/{id}
    // ----------------------------------------------------------------

    public function test_update_changes_price(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Pizza',
            'price'            => 12.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        $this->patchJson("/api/vendor/menu/items/{$item->id}", ['price' => 14.5], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.price', 14.5);
    }

    public function test_update_replaces_allergies(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', array_merge($this->baseItemPayload(), [
            'allergies' => ['gluten', 'dairy'],
        ]), $this->authHeaders());

        $itemId = $response->json('data.id');

        $this->patchJson("/api/vendor/menu/items/{$itemId}", [
            'allergies' => ['dairy'],
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data.allergies')
            ->assertJsonPath('data.allergies.0', 'dairy');
    }

    public function test_update_adds_translation_without_affecting_others(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', array_merge($this->baseItemPayload(), [
            'translations' => [
                ['language' => 'en', 'name' => 'Bread', 'description' => 'With tomatoes'],
            ],
        ]), $this->authHeaders());

        $itemId = $response->json('data.id');

        $this->patchJson("/api/vendor/menu/items/{$itemId}", [
            'translations' => [
                ['language' => 'de', 'name' => 'Brot', 'description' => 'Mit Tomaten'],
            ],
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.translations.en.name', 'Bread')  // original preserved
            ->assertJsonPath('data.translations.de.name', 'Brot');   // new added
    }

    public function test_update_cannot_modify_another_vendors_item(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);
        $item = $other->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Private',
            'price'            => 9.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        $this->patchJson("/api/vendor/menu/items/{$item->id}", ['price' => 1.0], $this->authHeaders())
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // DELETE /api/vendor/menu/items/{id}
    // ----------------------------------------------------------------

    public function test_destroy_soft_deletes_item_with_no_orders(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Delete Me',
            'price'            => 8.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        $this->deleteJson("/api/vendor/menu/items/{$item->id}", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['message' => 'Menu item deleted']);

        $this->assertSoftDeleted('menu_items', ['id' => $item->id, 'is_active' => false]);
    }

    public function test_destroy_soft_deletes_item_referenced_in_orders(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Ordered Item',
            'price'            => 10.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        OrderItem::create([
            'menu_item_id' => $item->id,
            'name'         => 'Ordered Item',
            'price'        => 10.0,
            'quantity'     => 1,
        ]);

        $this->deleteJson("/api/vendor/menu/items/{$item->id}", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['message' => 'Menu item deleted']);

        $this->assertSoftDeleted('menu_items', ['id' => $item->id, 'is_active' => false]);
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/menu/items/{id}/toggle
    // ----------------------------------------------------------------

    public function test_toggle_flips_availability(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Toggle Me',
            'price'            => 8.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        $this->patchJson("/api/vendor/menu/items/{$item->id}/toggle", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->patchJson("/api/vendor/menu/items/{$item->id}/toggle", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.available', true);
    }

    // ----------------------------------------------------------------
    // Modifier groups on items
    // ----------------------------------------------------------------

    public function test_store_and_update_sync_modifier_groups(): void
    {
        $sideGroup = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Choose your side',
            'type' => 'single',
            'min_selection' => 1,
            'max_selection' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);
        ModifierOption::create([
            'modifier_group_id' => $sideGroup->id,
            'name' => 'Fries',
            'price_adjustment' => 0,
            'is_active' => true,
        ]);

        $sauceGroup = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Sauce',
            'type' => 'multiple',
            'min_selection' => 0,
            'max_selection' => 2,
            'is_required' => false,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'modifierGroupIds' => [$sideGroup->id],
        ]), $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.modifierGroupIds.0', $sideGroup->id)
            ->assertJsonPath('data.modifierGroups.0.name', 'Choose your side')
            ->assertJsonPath('data.modifierGroups.0.options.0.name', 'Fries');

        $itemId = $response->json('data.id');
        $this->assertDatabaseHas('menu_item_modifier_groups', [
            'menu_item_id' => $itemId,
            'modifier_group_id' => $sideGroup->id,
        ]);

        $response = $this->patchJson("/api/vendor/menu/items/{$itemId}", [
            'modifierGroupIds' => [$sauceGroup->id],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('data.modifierGroupIds.0', $sauceGroup->id)
            ->assertJsonMissingPath('data.modifierGroupIds.1');

        $newItemId = $response->json('data.id');

        $this->assertSoftDeleted('menu_items', ['id' => $itemId]);
        $this->assertDatabaseHas('menu_item_modifier_groups', [
            'menu_item_id' => $newItemId,
            'modifier_group_id' => $sauceGroup->id,
        ]);
    }

    public function test_store_rejects_modifier_group_from_another_vendor(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);
        $otherGroup = ModifierGroup::create([
            'vendor_id' => $other->id,
            'name' => 'Private group',
            'type' => 'single',
            'is_active' => true,
        ]);

        $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'modifierGroupIds' => [$otherGroup->id],
        ]), $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['modifierGroupIds']);
    }

    // ----------------------------------------------------------------
    // Authentication guard
    // ----------------------------------------------------------------

    public function test_all_item_endpoints_require_authentication(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name'             => 'Auth Test',
            'price'            => 8.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        $this->getJson('/api/vendor/menu/items')->assertUnauthorized();
        $this->postJson('/api/vendor/menu/items', [])->assertUnauthorized();
        $this->getJson("/api/vendor/menu/items/{$item->id}")->assertUnauthorized();
        $this->patchJson("/api/vendor/menu/items/{$item->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/vendor/menu/items/{$item->id}")->assertUnauthorized();
        $this->patchJson("/api/vendor/menu/items/{$item->id}/toggle")->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // Versioning: product_uid & clone-on-price-change
    // ----------------------------------------------------------------

    public function test_product_uid_auto_generated_on_create(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload(), $this->authHeaders());
        $response->assertCreated();

        $itemId = $response->json('data.id');
        $productUid = $response->json('data.productUid');

        $this->assertNotNull($productUid);
        $this->assertDatabaseHas('menu_items', ['id' => $itemId, 'product_uid' => $productUid]);
    }

    public function test_update_price_creates_new_version(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'price' => 8.90,
        ]), $this->authHeaders());
        $response->assertCreated();

        $oldId = $response->json('data.id');
        $productUid = $response->json('data.productUid');

        $updateResponse = $this->patchJson("/api/vendor/menu/items/{$oldId}", [
            'price' => 12.50,
        ], $this->authHeaders());

        $updateResponse->assertOk();
        $newId = $updateResponse->json('data.id');

        $this->assertNotEquals($oldId, $newId);
        $this->assertEquals($productUid, $updateResponse->json('data.productUid'));
        $this->assertEquals(12.50, $updateResponse->json('data.price'));

        $this->assertSoftDeleted('menu_items', ['id' => $oldId]);
        $this->assertDatabaseHas('menu_items', ['id' => $newId, 'product_uid' => $productUid]);
    }

    public function test_update_non_versioned_field_updates_in_place(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload(), $this->authHeaders());
        $response->assertCreated();

        $itemId = $response->json('data.id');

        $this->patchJson("/api/vendor/menu/items/{$itemId}", [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'available' => false,
            'calories' => 250,
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $itemId)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.available', false);

        $this->assertDatabaseHas('menu_items', [
            'id' => $itemId,
            'name' => 'Updated Name',
            'deleted_at' => null,
        ]);
    }

    public function test_update_same_price_does_not_version(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'price' => 8.90,
        ]), $this->authHeaders());
        $response->assertCreated();

        $itemId = $response->json('data.id');

        $this->patchJson("/api/vendor/menu/items/{$itemId}", [
            'price' => 8.90,
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $itemId);

        $this->assertDatabaseHas('menu_items', ['id' => $itemId, 'deleted_at' => null]);
    }

    public function test_update_discount_creates_new_version(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'price' => 10.00,
        ]), $this->authHeaders());
        $response->assertCreated();

        $oldId = $response->json('data.id');
        $productUid = $response->json('data.productUid');

        $updateResponse = $this->patchJson("/api/vendor/menu/items/{$oldId}", [
            'hasDiscount' => true,
            'discountPercent' => 20,
        ], $this->authHeaders());

        $updateResponse->assertOk();
        $newId = $updateResponse->json('data.id');

        $this->assertNotEquals($oldId, $newId);
        $this->assertEquals($productUid, $updateResponse->json('data.productUid'));
        $this->assertTrue($updateResponse->json('data.hasDiscount'));
        $this->assertEquals(20, $updateResponse->json('data.discountPercent'));

        $this->assertSoftDeleted('menu_items', ['id' => $oldId]);
    }

    public function test_update_paid_addons_creates_new_version(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'paidAddons' => [
                ['id' => 1, 'name' => 'Extra Cheese', 'price' => 1.50],
            ],
        ]), $this->authHeaders());
        $response->assertCreated();

        $oldId = $response->json('data.id');
        $productUid = $response->json('data.productUid');

        $updateResponse = $this->patchJson("/api/vendor/menu/items/{$oldId}", [
            'paidAddons' => [
                ['id' => 1, 'name' => 'Extra Cheese', 'price' => 2.00],
            ],
        ], $this->authHeaders());

        $updateResponse->assertOk();
        $newId = $updateResponse->json('data.id');

        $this->assertNotEquals($oldId, $newId);
        $this->assertEquals($productUid, $updateResponse->json('data.productUid'));
        $this->assertSoftDeleted('menu_items', ['id' => $oldId]);
    }

    public function test_update_free_addons_deletion_creates_new_version(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'freeAddons' => [
                ['id' => 1, 'name' => 'Ketchup'],
                ['id' => 2, 'name' => 'Mustard'],
            ],
        ]), $this->authHeaders());
        $response->assertCreated();

        $oldId = $response->json('data.id');

        $updateResponse = $this->patchJson("/api/vendor/menu/items/{$oldId}", [
            'freeAddons' => [
                ['id' => 1, 'name' => 'Ketchup'],
            ],
        ], $this->authHeaders());

        $updateResponse->assertOk();
        $this->assertNotEquals($oldId, $updateResponse->json('data.id'));
        $this->assertSoftDeleted('menu_items', ['id' => $oldId]);
    }

    public function test_update_modifier_groups_creates_new_version(): void
    {
        $group1 = ModifierGroup::create([
            'vendor_id' => $this->vendor->id, 'name' => 'Size',
            'type' => 'single', 'is_active' => true,
        ]);
        $group2 = ModifierGroup::create([
            'vendor_id' => $this->vendor->id, 'name' => 'Extras',
            'type' => 'multiple', 'is_active' => true,
        ]);

        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'modifierGroupIds' => [$group1->id],
        ]), $this->authHeaders());
        $response->assertCreated();

        $oldId = $response->json('data.id');
        $productUid = $response->json('data.productUid');

        $updateResponse = $this->patchJson("/api/vendor/menu/items/{$oldId}", [
            'modifierGroupIds' => [$group1->id, $group2->id],
        ], $this->authHeaders());

        $updateResponse->assertOk();
        $newId = $updateResponse->json('data.id');

        $this->assertNotEquals($oldId, $newId);
        $this->assertEquals($productUid, $updateResponse->json('data.productUid'));
        $this->assertSoftDeleted('menu_items', ['id' => $oldId]);
        $this->assertDatabaseHas('menu_item_modifier_groups', [
            'menu_item_id' => $newId,
            'modifier_group_id' => $group2->id,
        ]);
    }

    public function test_versioning_clones_translations(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'translations' => [
                'en' => ['name' => 'Bruschetta', 'description' => 'Toasted bread'],
                'de' => ['name' => 'Bruschetta DE', 'description' => 'Geröstetes Brot'],
            ],
        ]), $this->authHeaders());
        $response->assertCreated();

        $oldId = $response->json('data.id');

        $updateResponse = $this->patchJson("/api/vendor/menu/items/{$oldId}", [
            'price' => 15.00,
        ], $this->authHeaders());

        $updateResponse->assertOk();
        $newId = $updateResponse->json('data.id');

        $this->assertDatabaseHas('menu_item_translations', [
            'menu_item_id' => $newId,
            'language' => 'de',
            'name' => 'Bruschetta DE',
        ]);
    }

    public function test_versioning_clones_allergens_and_tags(): void
    {
        $response = $this->postJson('/api/vendor/menu/items', $this->baseItemPayload([
            'allergies' => [$this->allergenGluten->name],
            'specialTags' => [$this->tagPopular->slug],
        ]), $this->authHeaders());
        $response->assertCreated();
        $oldId = $response->json('data.id');

        $item = MenuItem::find($oldId);
        $item->allergens()->attach($this->allergenGluten->id);
        $item->tags()->attach($this->tagPopular->id);

        $updateResponse = $this->patchJson("/api/vendor/menu/items/{$oldId}", [
            'price' => 15.00,
        ], $this->authHeaders());

        $updateResponse->assertOk();
        $newId = $updateResponse->json('data.id');

        $this->assertDatabaseHas('menu_item_allergens', [
            'menu_item_id' => $newId,
            'allergen_id' => $this->allergenGluten->id,
        ]);
        $this->assertDatabaseHas('menu_item_tags', [
            'menu_item_id' => $newId,
            'special_tag_id' => $this->tagPopular->id,
        ]);
    }

    public function test_soft_deleted_item_excluded_from_vendor_menu(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name' => 'Hidden Item',
            'price' => 5.0,
            'available' => true,
            'is_active' => true,
        ]);

        $item->delete();

        $this->getJson('/api/vendor/menu/items', $this->authHeaders())
            ->assertOk()
            ->assertJsonMissing(['name' => 'Hidden Item']);
    }

    public function test_destroy_always_soft_deletes(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name' => 'To Delete',
            'price' => 5.0,
            'available' => true,
            'is_active' => true,
        ]);

        $this->deleteJson("/api/vendor/menu/items/{$item->id}", [], $this->authHeaders())
            ->assertOk();

        $this->assertSoftDeleted('menu_items', ['id' => $item->id]);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id]);
    }

    public function test_cart_item_loads_soft_deleted_menu_item(): void
    {
        $item = $this->vendor->menuItems()->create([
            'menu_category_id' => $this->category->id,
            'name' => 'Deleted Item',
            'price' => 9.0,
            'available' => true,
            'is_active' => true,
        ]);

        $table = $this->vendor->restaurantTables()->create([
            'number' => 1, 'name' => 'T1',
            'qr_token' => 'tok', 'is_active' => true,
        ]);
        $session = \App\Models\TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'pin' => '1234',
            'status' => 'active',
        ]);

        $cartItem = \App\Models\CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $item->id,
            'quantity' => 1,
        ]);

        $item->delete();

        $cartItem->refresh();
        $loaded = $cartItem->menuItem;

        $this->assertNotNull($loaded);
        $this->assertEquals('Deleted Item', $loaded->name);
        $this->assertTrue($loaded->trashed());
    }
}
