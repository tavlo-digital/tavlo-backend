<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\MenuCategoryController;
use App\Models\MasterMenuCategory;
use App\Models\MenuCategory;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Response;
use ReflectionProperty;
use Tests\TestCase;

class MasterMenuCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin', 'label' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_admin_can_view_master_menu_categories(): void
    {
        MasterMenuCategory::create([
            'name' => 'Pizza',
            'slug' => 'pizza',
            'icon' => '🍕',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = app(MenuCategoryController::class)->index();

        $this->assertInstanceOf(Response::class, $response);

        $component = new ReflectionProperty($response, 'component');
        $props = new ReflectionProperty($response, 'props');

        $this->assertSame('admin/menu-categories/index', $component->getValue($response));
        $this->assertSame('Pizza', $props->getValue($response)['categories'][0]['name']);
        $this->assertSame('🍕', $props->getValue($response)['categories'][0]['icon']);
    }

    public function test_admin_can_create_master_menu_category(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/menu-categories', [
                'name' => 'Pasta',
                'icon' => '🍝',
                'sort_order' => 2,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/menu-categories');

        $this->assertDatabaseHas('master_menu_categories', [
            'name' => 'Pasta',
            'slug' => 'pasta',
            'icon' => '🍝',
        ]);
    }

    public function test_admin_can_update_master_menu_category(): void
    {
        $category = MasterMenuCategory::create([
            'name' => 'Old',
            'slug' => 'old',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/menu-categories/{$category->id}", [
                'name' => 'Desserts',
                'icon' => '🍰',
                'sort_order' => 3,
                'is_active' => false,
            ])
            ->assertRedirect('/admin/menu-categories');

        $this->assertDatabaseHas('master_menu_categories', [
            'id' => $category->id,
            'name' => 'Desserts',
            'slug' => 'desserts',
            'icon' => '🍰',
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_delete_category_used_by_vendor(): void
    {
        $master = MasterMenuCategory::create([
            'name' => 'Pizza',
            'slug' => 'pizza',
        ]);
        $vendor = Vendor::factory()->create();
        MenuCategory::create([
            'vendor_id' => $vendor->id,
            'master_menu_category_id' => $master->id,
            'name' => 'Pizza',
            'slug' => 'pizza',
            'default_tax_category' => 'food',
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/menu-categories/{$master->id}")
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('master_menu_categories', ['id' => $master->id]);
    }
}
