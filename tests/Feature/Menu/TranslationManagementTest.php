<?php

namespace Tests\Feature\Menu;

use App\Models\MasterMenuCategory;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationManagementTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);
        VendorSetting::create([
            'vendor_id' => $this->vendor->id,
            'dashboard_language' => 'de',
            'supported_languages' => ['de', 'en'],
        ]);
    }

    private function authHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    public function test_vendor_can_translate_inventory_categories_and_items(): void
    {
        $category = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/inventory/categories",
            [
                'name' => 'Vegetables',
                'translations' => [
                    'en' => ['name' => 'Vegetables'],
                    'de' => ['name' => 'Gemüse'],
                ],
            ],
            $this->authHeaders()
        )->assertCreated()
            ->assertJsonPath('name', 'Gemüse')
            ->json();

        $itemId = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/inventory/items",
            [
                'name' => 'Tomatoes',
                'categoryId' => $category['id'],
                'quantity' => 5,
                'unit' => 'kg',
                'minStock' => 1,
                'reorderQuantity' => 5,
                'costPerUnit' => 2,
                'supplier' => 'Fresh Foods',
                'translations' => [
                    'en' => ['name' => 'Tomatoes', 'supplier' => 'Fresh Foods'],
                    'de' => ['name' => 'Tomaten', 'supplier' => 'Frische Lebensmittel'],
                ],
            ],
            $this->authHeaders()
        )->assertCreated()
            ->assertJsonPath('name', 'Tomaten')
            ->assertJsonPath('category', 'Gemüse')
            ->assertJsonPath('translations.en.name', 'Tomatoes')
            ->assertJsonPath('translations.en.supplier', 'Fresh Foods')
            ->assertJsonPath('translations.de.name', 'Tomaten')
            ->assertJsonPath('translations.de.supplier', 'Frische Lebensmittel')
            ->assertJsonPath('supplier', 'Frische Lebensmittel')
            ->json('id');

        $this->patchJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/inventory/categories/{$category['id']}",
            ['translations' => ['de' => ['name' => 'Frisches Gemüse']]],
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonPath('name', 'Frisches Gemüse')
            ->assertJsonPath('translations.en.name', 'Vegetables')
            ->assertJsonPath('translations.de.name', 'Frisches Gemüse');

        $this->getJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/inventory/items",
            $this->authHeaders()
        )
            ->assertOk()
            ->assertJsonPath('0.id', (string) $itemId)
            ->assertJsonPath('0.category', 'Frisches Gemüse');
    }

    public function test_inventory_keeps_unentered_languages_empty(): void
    {
        $category = $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/inventory/categories",
            [
                'translations' => [
                    'de' => ['name' => 'Obst'],
                ],
            ],
            $this->authHeaders()
        )
            ->assertCreated()
            ->assertJsonPath('name', 'Obst')
            ->assertJsonMissingPath('translations.en')
            ->json();

        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/inventory/items",
            [
                'categoryId' => $category['id'],
                'quantity' => 5,
                'unit' => 'kg',
                'minStock' => 1,
                'reorderQuantity' => 5,
                'costPerUnit' => 2,
                'translations' => [
                    'de' => [
                        'name' => 'Äpfel',
                        'supplier' => 'Obst Lieferant',
                    ],
                ],
            ],
            $this->authHeaders()
        )
            ->assertCreated()
            ->assertJsonPath('name', 'Äpfel')
            ->assertJsonPath('supplier', 'Obst Lieferant')
            ->assertJsonMissingPath('translations.en')
            ->assertJsonPath('translations.de.supplier', 'Obst Lieferant');
    }

    public function test_vendor_can_translate_categories_modifiers_and_items(): void
    {
        $master = MasterMenuCategory::create([
            'name' => 'Starters',
            'slug' => 'starters',
            'is_active' => true,
        ]);

        $categoryId = $this->postJson('/api/vendor/menu/categories', [
            'masterCategoryId' => $master->id,
            'translations' => ['de' => ['name' => 'Vorspeisen']],
        ], $this->authHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Vorspeisen')
            ->json('data.id');

        $groupId = $this->postJson('/api/vendor/menu/modifier-groups', [
            'name' => 'Choose a size',
            'translations' => ['de' => ['name' => 'Größe wählen']],
            'options' => [[
                'name' => 'Large',
                'translations' => ['de' => ['name' => 'Groß']],
            ]],
        ], $this->authHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Größe wählen')
            ->assertJsonPath('data.options.0.name', 'Groß')
            ->json('data.id');

        $this->postJson('/api/vendor/menu/items', [
            'categoryId' => $categoryId,
            'price' => 12,
            'modifierGroupIds' => [$groupId],
            'translations' => [
                'en' => ['name' => 'Grilled Chicken', 'description' => 'Served with vegetables'],
                'de' => ['name' => 'Gegrilltes Hähnchen', 'description' => 'Mit Gemüse serviert'],
            ],
        ], $this->authHeaders())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Gegrilltes Hähnchen')
            ->assertJsonPath('data.translations.en.name', 'Grilled Chicken')
            ->assertJsonPath('data.translations.de.description', 'Mit Gemüse serviert');
    }
}
