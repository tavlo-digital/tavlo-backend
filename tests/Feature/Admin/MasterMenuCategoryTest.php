<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\MenuCategoryController;
use App\Models\MasterMenuCategory;
use App\Models\MenuCategory;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'icon' => 'cat-icons/pizza.png',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = app(MenuCategoryController::class)->index();

        $this->assertInstanceOf(Response::class, $response);

        $component = new ReflectionProperty($response, 'component');
        $props = new ReflectionProperty($response, 'props');

        $this->assertSame('admin/menu-categories/index', $component->getValue($response));
        $this->assertSame('Pizza', $props->getValue($response)['categories'][0]['name']);
        $this->assertSame(url('media/cat-icons/pizza.png'), $props->getValue($response)['categories'][0]['icon']);
        $this->assertSame('Pizza', $props->getValue($response)['categories'][0]['translations']['en']['name']);
        $this->assertSame('en', $props->getValue($response)['languages'][0]['code']);
    }

    public function test_admin_can_create_master_menu_category(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('pasta.png', 64, 64);

        $this->actingAs($this->admin)
            ->post('/admin/menu-categories', [
                'name' => 'Pasta',
                'icon' => $file,
                'sort_order' => 2,
                'is_active' => true,
                'translations' => [
                    'en' => ['name' => 'Pasta'],
                    'de' => ['name' => 'Nudeln'],
                ],
            ])
            ->assertRedirect('/admin/menu-categories');

        $this->assertDatabaseHas('master_menu_categories', [
            'name' => 'Pasta',
            'slug' => 'pasta',
        ]);

        $category = MasterMenuCategory::where('slug', 'pasta')->firstOrFail();
        $this->assertDatabaseHas('master_menu_category_translations', [
            'master_menu_category_id' => $category->id,
            'language' => 'de',
            'name' => 'Nudeln',
        ]);
        $this->assertStringStartsWith('cat-icons/', $category->icon);
        Storage::disk('public')->assertExists($category->icon);
    }

    public function test_admin_can_update_master_menu_category(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cat-icons/old.png', 'old');

        $category = MasterMenuCategory::create([
            'name' => 'Old',
            'slug' => 'old',
            'icon' => 'cat-icons/old.png',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/menu-categories/{$category->id}", [
                '_method' => 'put',
                'name' => 'Desserts',
                'icon' => UploadedFile::fake()->image('desserts.webp', 64, 64),
                'sort_order' => 3,
                'is_active' => false,
                'translations' => [
                    'en' => ['name' => 'Desserts'],
                    'fr' => ['name' => 'Desserts français'],
                ],
            ])
            ->assertRedirect('/admin/menu-categories');

        $this->assertDatabaseHas('master_menu_categories', [
            'id' => $category->id,
            'name' => 'Desserts',
            'slug' => 'desserts',
            'is_active' => false,
        ]);

        $category->refresh();
        $this->assertDatabaseHas('master_menu_category_translations', [
            'master_menu_category_id' => $category->id,
            'language' => 'fr',
            'name' => 'Desserts français',
        ]);
        $this->assertStringStartsWith('cat-icons/', $category->icon);
        $this->assertNotSame('cat-icons/old.png', $category->icon);
        Storage::disk('public')->assertMissing('cat-icons/old.png');
        Storage::disk('public')->assertExists($category->icon);
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
