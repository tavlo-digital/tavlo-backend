<?php

namespace Tests\Feature\Menu;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModifierGroupTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);
    }

    private function authHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/menu/modifier-groups
    // ----------------------------------------------------------------

    public function test_index_returns_empty_for_new_vendor(): void
    {
        $this->getJson('/api/vendor/menu/modifier-groups', $this->authHeaders())
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_index_returns_active_groups_with_options(): void
    {
        $group = ModifierGroup::create([
            'vendor_id'     => $this->vendor->id,
            'name'          => 'Size',
            'type'          => 'single',
            'min_selection' => 1,
            'max_selection' => 1,
            'is_required'   => true,
            'sort_order'    => 0,
            'is_active'     => true,
        ]);

        ModifierOption::create(['modifier_group_id' => $group->id, 'name' => 'Small', 'price_adjustment' => 0, 'sort_order' => 0, 'is_active' => true]);
        ModifierOption::create(['modifier_group_id' => $group->id, 'name' => 'Large', 'price_adjustment' => 3.0, 'sort_order' => 1, 'is_active' => true]);

        $this->getJson('/api/vendor/menu/modifier-groups', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Size')
            ->assertJsonPath('data.0.type', 'single')
            ->assertJsonPath('data.0.minSelection', 1)
            ->assertJsonPath('data.0.maxSelection', 1)
            ->assertJsonPath('data.0.isRequired', true)
            ->assertJsonCount(2, 'data.0.options')
            ->assertJsonPath('data.0.options.0.name', 'Small')
            ->assertJsonPath('data.0.options.1.priceAdjustment', 3);
    }

    public function test_index_excludes_inactive_groups(): void
    {
        ModifierGroup::create([
            'vendor_id'     => $this->vendor->id,
            'name'          => 'Hidden',
            'type'          => 'single',
            'min_selection' => 0,
            'max_selection' => 1,
            'is_required'   => false,
            'sort_order'    => 0,
            'is_active'     => false,
        ]);

        $this->getJson('/api/vendor/menu/modifier-groups', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_does_not_return_other_vendor_groups(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);
        ModifierGroup::create([
            'vendor_id'     => $other->id,
            'name'          => 'Other Group',
            'type'          => 'single',
            'min_selection' => 0,
            'max_selection' => 1,
            'is_required'   => false,
            'sort_order'    => 0,
            'is_active'     => true,
        ]);

        $this->getJson('/api/vendor/menu/modifier-groups', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ----------------------------------------------------------------
    // POST /api/vendor/menu/modifier-groups
    // ----------------------------------------------------------------

    public function test_store_creates_group_with_options(): void
    {
        $response = $this->postJson('/api/vendor/menu/modifier-groups', [
            'name'         => 'Cooking Level',
            'type'         => 'single',
            'minSelection' => 1,
            'maxSelection' => 1,
            'isRequired'   => true,
            'options'      => [
                ['name' => 'Rare',        'priceAdjustment' => 0],
                ['name' => 'Medium Rare', 'priceAdjustment' => 0],
                ['name' => 'Well Done',   'priceAdjustment' => 0],
            ],
        ], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Cooking Level')
            ->assertJsonPath('data.type', 'single')
            ->assertJsonPath('data.isRequired', true)
            ->assertJsonPath('data.isActive', true)
            ->assertJsonCount(3, 'data.options')
            ->assertJsonPath('data.options.0.name', 'Rare')
            ->assertJsonPath('data.options.1.name', 'Medium Rare')
            ->assertJsonPath('data.options.2.name', 'Well Done');

        $this->assertDatabaseHas('modifier_groups', [
            'vendor_id' => $this->vendor->id,
            'name'      => 'Cooking Level',
        ]);
        $this->assertDatabaseCount('modifier_options', 3);
    }

    public function test_store_creates_group_without_options(): void
    {
        $this->postJson('/api/vendor/menu/modifier-groups', [
            'name' => 'Empty Group',
            'type' => 'multiple',
        ], $this->authHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Empty Group')
            ->assertJsonPath('data.type', 'multiple')
            ->assertJsonCount(0, 'data.options');
    }

    public function test_store_requires_name(): void
    {
        $this->postJson('/api/vendor/menu/modifier-groups', ['type' => 'single'], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_assigns_sequential_sort_order(): void
    {
        $this->postJson('/api/vendor/menu/modifier-groups', ['name' => 'First', 'type' => 'single'], $this->authHeaders())->assertCreated();
        $response = $this->postJson('/api/vendor/menu/modifier-groups', ['name' => 'Second', 'type' => 'single'], $this->authHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.sortOrder', 1);
    }

    // ----------------------------------------------------------------
    // PATCH /api/vendor/menu/modifier-groups/{id}
    // ----------------------------------------------------------------

    public function test_update_renames_group(): void
    {
        $group = ModifierGroup::create([
            'vendor_id'     => $this->vendor->id,
            'name'          => 'Old Name',
            'type'          => 'single',
            'min_selection' => 0,
            'max_selection' => 1,
            'is_required'   => false,
            'sort_order'    => 0,
            'is_active'     => true,
        ]);

        $this->patchJson("/api/vendor/menu/modifier-groups/{$group->id}", [
            'name' => 'New Name',
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_update_syncs_options_create_update_delete(): void
    {
        $group = ModifierGroup::create([
            'vendor_id'     => $this->vendor->id,
            'name'          => 'Toppings',
            'type'          => 'multiple',
            'min_selection' => 0,
            'max_selection' => 3,
            'is_required'   => false,
            'sort_order'    => 0,
            'is_active'     => true,
        ]);

        $opt1 = ModifierOption::create(['modifier_group_id' => $group->id, 'name' => 'Mushrooms', 'price_adjustment' => 1.0, 'sort_order' => 0, 'is_active' => true]);
        $opt2 = ModifierOption::create(['modifier_group_id' => $group->id, 'name' => 'Olives', 'price_adjustment' => 0.5, 'sort_order' => 1, 'is_active' => true]);

        // Keep opt1 with price change, delete opt2, add new
        $response = $this->patchJson("/api/vendor/menu/modifier-groups/{$group->id}", [
            'options' => [
                ['id' => $opt1->id, 'name' => 'Mushrooms', 'priceAdjustment' => 1.5],
                ['name' => 'Salami', 'priceAdjustment' => 2.0],
            ],
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(2, 'data.options')
            ->assertJsonPath('data.options.0.name', 'Mushrooms')
            ->assertJsonPath('data.options.0.priceAdjustment', 1.5)
            ->assertJsonPath('data.options.1.name', 'Salami');

        $this->assertSoftDeleted('modifier_options', ['id' => $opt2->id]);
    }

    public function test_update_cannot_modify_another_vendors_group(): void
    {
        $other = Vendor::factory()->create(['country' => 'Austria']);
        $group = ModifierGroup::create([
            'vendor_id'     => $other->id,
            'name'          => 'Other Group',
            'type'          => 'single',
            'min_selection' => 0,
            'max_selection' => 1,
            'is_required'   => false,
            'sort_order'    => 0,
            'is_active'     => true,
        ]);

        $this->patchJson("/api/vendor/menu/modifier-groups/{$group->id}", [
            'name' => 'Hacked',
        ], $this->authHeaders())
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // DELETE /api/vendor/menu/modifier-groups/{id}
    // ----------------------------------------------------------------

    public function test_destroy_soft_deletes_group(): void
    {
        $group = ModifierGroup::create([
            'vendor_id'     => $this->vendor->id,
            'name'          => 'Delete Me',
            'type'          => 'single',
            'min_selection' => 0,
            'max_selection' => 1,
            'is_required'   => false,
            'sort_order'    => 0,
            'is_active'     => true,
        ]);

        $this->deleteJson("/api/vendor/menu/modifier-groups/{$group->id}", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['message' => 'Modifier group removed.']);

        $this->assertDatabaseHas('modifier_groups', ['id' => $group->id, 'is_active' => false]);
    }

    public function test_destroy_removes_group_from_index(): void
    {
        $group = ModifierGroup::create([
            'vendor_id'     => $this->vendor->id,
            'name'          => 'Gone Soon',
            'type'          => 'single',
            'min_selection' => 0,
            'max_selection' => 1,
            'is_required'   => false,
            'sort_order'    => 0,
            'is_active'     => true,
        ]);

        $this->deleteJson("/api/vendor/menu/modifier-groups/{$group->id}", [], $this->authHeaders());

        $this->getJson('/api/vendor/menu/modifier-groups', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ----------------------------------------------------------------
    // Authentication
    // ----------------------------------------------------------------

    public function test_all_endpoints_require_authentication(): void
    {
        $this->getJson('/api/vendor/menu/modifier-groups')->assertUnauthorized();
        $this->postJson('/api/vendor/menu/modifier-groups', [])->assertUnauthorized();
        $this->patchJson('/api/vendor/menu/modifier-groups/1', [])->assertUnauthorized();
        $this->deleteJson('/api/vendor/menu/modifier-groups/1')->assertUnauthorized();
    }
}
