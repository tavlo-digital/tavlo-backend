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

        $opt = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Option A',
            'price_adjustment' => 1.0,
            'is_active' => true,
        ]);

        $this->deleteJson("/api/vendor/menu/modifier-groups/{$group->id}", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['message' => 'Modifier group removed.']);

        $this->assertSoftDeleted('modifier_groups', ['id' => $group->id, 'is_active' => false]);
        $this->assertSoftDeleted('modifier_options', ['id' => $opt->id]);
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

    // ----------------------------------------------------------------
    // Versioning: product_uid & option price versioning
    // ----------------------------------------------------------------

    public function test_product_uid_auto_generated_on_create(): void
    {
        $response = $this->postJson('/api/vendor/menu/modifier-groups', [
            'name' => 'Size',
            'type' => 'single',
            'options' => [
                ['name' => 'Small', 'priceAdjustment' => 0],
                ['name' => 'Large', 'priceAdjustment' => 2.0],
            ],
        ], $this->authHeaders());

        $response->assertCreated();
        $groupId = $response->json('data.id');

        $group = ModifierGroup::find($groupId);
        $this->assertNotNull($group->product_uid);

        $options = $group->options;
        foreach ($options as $option) {
            $this->assertNotNull($option->product_uid);
        }
    }

    public function test_update_option_price_creates_new_version(): void
    {
        $group = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Extras',
            'type' => 'multiple',
            'min_selection' => 0,
            'max_selection' => 3,
            'is_required' => false,
            'is_active' => true,
        ]);

        $option = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Extra Cheese',
            'price_adjustment' => 1.50,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $oldId = $option->id;
        $oldProductUid = $option->product_uid;

        $response = $this->patchJson("/api/vendor/menu/modifier-groups/{$group->id}", [
            'options' => [
                ['id' => $oldId, 'name' => 'Extra Cheese', 'priceAdjustment' => 2.50],
            ],
        ], $this->authHeaders());

        $response->assertOk();

        $this->assertSoftDeleted('modifier_options', ['id' => $oldId]);

        $newOption = ModifierOption::where('modifier_group_id', $group->id)
            ->where('product_uid', $oldProductUid)
            ->whereNull('deleted_at')
            ->first();

        $this->assertNotNull($newOption);
        $this->assertNotEquals($oldId, $newOption->id);
        $this->assertEquals($oldProductUid, $newOption->product_uid);
        $this->assertEquals('2.50', $newOption->price_adjustment);
    }

    public function test_update_option_same_price_does_not_version(): void
    {
        $group = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Toppings',
            'type' => 'multiple',
            'min_selection' => 0,
            'max_selection' => 3,
            'is_required' => false,
            'is_active' => true,
        ]);

        $option = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Peppers',
            'price_adjustment' => 1.00,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->patchJson("/api/vendor/menu/modifier-groups/{$group->id}", [
            'options' => [
                ['id' => $option->id, 'name' => 'Peppers Updated', 'priceAdjustment' => 1.00],
            ],
        ], $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseHas('modifier_options', [
            'id' => $option->id,
            'name' => 'Peppers Updated',
            'deleted_at' => null,
        ]);
    }

    public function test_destroy_soft_deletes_group_and_options(): void
    {
        $group = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'To Delete',
            'type' => 'single',
            'is_active' => true,
        ]);

        $opt1 = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'A', 'price_adjustment' => 0, 'is_active' => true,
        ]);
        $opt2 = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'B', 'price_adjustment' => 1, 'is_active' => true,
        ]);

        $this->deleteJson("/api/vendor/menu/modifier-groups/{$group->id}", [], $this->authHeaders())
            ->assertOk();

        $this->assertSoftDeleted('modifier_groups', ['id' => $group->id]);
        $this->assertSoftDeleted('modifier_options', ['id' => $opt1->id]);
        $this->assertSoftDeleted('modifier_options', ['id' => $opt2->id]);

        $this->assertDatabaseHas('modifier_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('modifier_options', ['id' => $opt1->id]);
    }
}
