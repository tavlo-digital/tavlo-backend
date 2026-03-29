<?php

namespace Tests\Feature\Menu;

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\TaxCategory;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuCategoryTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;
    private TaxCategory $taxFood;
    private TaxCategory $taxBeverage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);

        // Tax categories are seeded by migration
        $this->taxFood     = TaxCategory::where(['country' => 'AT', 'slug' => 'food'])->firstOrFail();
        $this->taxBeverage = TaxCategory::where(['country' => 'AT', 'slug' => 'beverage_non_alcoholic'])->firstOrFail();
    }

    private function authHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/menu/tax-categories
    // ----------------------------------------------------------------

    public function test_tax_categories_returns_country_rates(): void
    {
        $response = $this->getJson('/api/vendor/menu/tax-categories', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['slug' => 'food', 'vatRate' => 10])
            ->assertJsonFragment(['slug' => 'beverage_non_alcoholic', 'vatRate' => 20])
            ->assertJsonFragment(['slug' => 'beverage_alcoholic', 'vatRate' => 20]);
    }

    public function test_tax_categories_requires_authentication(): void
    {
        $this->getJson('/api/vendor/menu/tax-categories')
            ->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/menu/categories
    // ----------------------------------------------------------------

    public function test_index_returns_empty_for_new_vendor(): void
    {
        $this->getJson('/api/vendor/menu/categories', $this->authHeaders())
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_index_returns_vendor_categories_with_tax_category(): void
    {
        MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Starters',
            'slug'                 => 'starters',
            'tax_category_id'      => $this->taxFood->id,
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $response = $this->getJson('/api/vendor/menu/categories', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Starters')
            ->assertJsonPath('data.0.taxCategory.slug', 'food')
            ->assertJsonPath('data.0.taxCategory.vatRate', 10)
            ->assertJsonPath('data.0.isActive', true)
            ->assertJsonPath('data.0.itemCount', 0);
    }

    public function test_index_does_not_return_another_vendors_categories(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);
        MenuCategory::create([
            'vendor_id'            => $other->id,
            'name'                 => 'Other Starters',
            'slug'                 => 'other-starters',
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $this->getJson('/api/vendor/menu/categories', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_item_count_excludes_inactive_items(): void
    {
        $category = MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Pizza',
            'slug'                 => 'pizza',
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        // Active item
        $this->vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name'             => 'Margherita',
            'price'            => 12.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        // Inactive (soft-deleted) item
        $this->vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name'             => 'Hidden Pizza',
            'price'            => 10.0,
            'available'        => false,
            'is_active'        => false,
        ]);

        $this->getJson('/api/vendor/menu/categories', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.itemCount', 1);
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/menu/categories
    // ----------------------------------------------------------------

    public function test_store_creates_category(): void
    {
        $response = $this->postJson('/api/vendor/menu/categories', [
            'name'          => 'Pasta',
            'taxCategoryId' => $this->taxFood->id,
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Pasta')
            ->assertJsonPath('data.slug', 'pasta')
            ->assertJsonPath('data.taxCategory.id', $this->taxFood->id)
            ->assertJsonPath('data.taxCategory.vatRate', 10)
            ->assertJsonPath('data.isActive', true)
            ->assertJsonPath('data.itemCount', 0);

        $this->assertDatabaseHas('menu_categories', [
            'vendor_id' => $this->vendor->id,
            'name'      => 'Pasta',
            'slug'      => 'pasta',
        ]);
    }

    public function test_store_defaults_to_food_tax_when_no_tax_category(): void
    {
        $response = $this->postJson('/api/vendor/menu/categories', [
            'name' => 'Desserts',
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.taxCategory.slug', 'food');
    }

    public function test_store_requires_name(): void
    {
        $this->postJson('/api/vendor/menu/categories', [], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Salads',
            'slug'                 => 'salads',
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $this->postJson('/api/vendor/menu/categories', ['name' => 'Salads'], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'A category with this name already exists.']);
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/menu/categories/{id}
    // ----------------------------------------------------------------

    public function test_update_changes_name_and_slug(): void
    {
        $category = MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Old Name',
            'slug'                 => 'old-name',
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $this->patchJson("/api/vendor/menu/categories/{$category->id}", [
            'name' => 'New Name',
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.slug', 'new-name');
    }

    public function test_update_changes_tax_category(): void
    {
        $category = MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Drinks',
            'slug'                 => 'drinks',
            'tax_category_id'      => $this->taxFood->id,
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $this->patchJson("/api/vendor/menu/categories/{$category->id}", [
            'taxCategoryId' => $this->taxBeverage->id,
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.taxCategory.slug', 'beverage_non_alcoholic')
            ->assertJsonPath('data.taxCategory.vatRate', 20);
    }

    public function test_update_rejects_duplicate_name_on_different_category(): void
    {
        MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Existing',
            'slug'                 => 'existing',
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $category = MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Another',
            'slug'                 => 'another',
            'default_tax_category' => 'food',
            'sort_order'           => 1,
            'is_active'            => true,
        ]);

        $this->patchJson("/api/vendor/menu/categories/{$category->id}", [
            'name' => 'Existing',
        ], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'A category with this name already exists.']);
    }

    public function test_update_cannot_modify_another_vendors_category(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);
        $category = MenuCategory::create([
            'vendor_id'            => $other->id,
            'name'                 => 'Private Category',
            'slug'                 => 'private-category',
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $this->patchJson("/api/vendor/menu/categories/{$category->id}", [
            'name' => 'Hacked',
        ], $this->authHeaders())
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // DELETE /api/vendor/menu/categories/{id}
    // ----------------------------------------------------------------

    public function test_destroy_deletes_empty_category(): void
    {
        $category = MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Empty',
            'slug'                 => 'empty',
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $this->deleteJson("/api/vendor/menu/categories/{$category->id}", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['message' => 'Category deleted']);

        $this->assertDatabaseMissing('menu_categories', ['id' => $category->id]);
    }

    public function test_destroy_rejects_category_with_active_items(): void
    {
        $category = MenuCategory::create([
            'vendor_id'            => $this->vendor->id,
            'name'                 => 'Full Category',
            'slug'                 => 'full-category',
            'default_tax_category' => 'food',
            'sort_order'           => 0,
            'is_active'            => true,
        ]);

        $this->vendor->menuItems()->create([
            'menu_category_id' => $category->id,
            'name'             => 'Active Item',
            'price'            => 10.0,
            'available'        => true,
            'is_active'        => true,
        ]);

        $this->deleteJson("/api/vendor/menu/categories/{$category->id}", [], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Category cannot be deleted because menu items are assigned to it.']);
    }
}
