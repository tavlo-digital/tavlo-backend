<?php

namespace Tests\Feature\Customer;

use App\Models\Allergen;
use App\Models\DietaryPreference;
use App\Models\MasterMenuCategory;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\SpecialTag;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantTranslationTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private MenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);
        VendorSetting::create([
            'vendor_id' => $this->vendor->id,
            'is_live_and_discoverable' => true,
            'dashboard_language' => 'en',
            'supported_languages' => ['de', 'en'],
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Starters',
            'slug' => 'starters',
            'is_active' => true,
        ]);
        $category->localizedTranslations()->create(['language' => 'de', 'name' => 'Vorspeisen']);

        $this->item = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Grilled Chicken',
            'description' => 'Served with vegetables',
            'price' => 10,
            'available' => true,
            'is_active' => true,
        ]);
        $this->item->itemTranslations()->create([
            'language' => 'de',
            'name' => 'Gegrilltes Hähnchen',
            'description' => 'Mit Gemüse serviert',
        ]);

        $allergen = Allergen::create([
            'name' => 'Milk',
            'is_active' => true,
        ]);
        $allergen->localizedTranslations()->create([
            'language' => 'de',
            'name' => 'Milch',
        ]);

        $tag = SpecialTag::create([
            'slug' => 'chefs-pick',
            'label' => "Chef's Pick",
            'is_active' => true,
        ]);
        $tag->localizedTranslations()->create([
            'language' => 'de',
            'label' => 'Empfehlung des Küchenchefs',
        ]);

        $preference = DietaryPreference::where('slug', 'vegetarian')->firstOrFail();
        $preference->localizedTranslations()->updateOrCreate(
            ['language' => 'de'],
            ['name' => 'Vegetarisch']
        );

        $this->item->update([
            'allergies' => ['Milk'],
            'special_tags' => ['chefs-pick'],
            'dietary_preference' => 'vegetarian',
        ]);

        $group = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Choose a size',
            'type' => 'single',
            'is_active' => true,
        ]);
        $group->localizedTranslations()->create(['language' => 'de', 'name' => 'Größe wählen']);
        $option = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Large',
            'is_active' => true,
        ]);
        $option->localizedTranslations()->create(['language' => 'de', 'name' => 'Groß']);
        $this->item->modifierGroups()->sync([$group->id => ['sort_order' => 0]]);
    }

    public function test_query_language_localizes_the_complete_menu_tree(): void
    {
        $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu?lang=de")
            ->assertOk()
            ->assertHeader('Content-Language', 'de')
            ->assertJsonPath('0.name', 'Gegrilltes Hähnchen')
            ->assertJsonPath('0.description', 'Mit Gemüse serviert')
            ->assertJsonPath('0.category.name', 'Vorspeisen')
            ->assertJsonPath('0.allergens.0', 'Milch')
            ->assertJsonPath('0.tags.0', 'Empfehlung des Küchenchefs')
            ->assertJsonPath('0.dietary_preference', 'Vegetarisch')
            ->assertJsonPath('0.modifier_groups.0.name', 'Größe wählen')
            ->assertJsonPath('0.modifier_groups.0.options.0.name', 'Groß');
    }

    public function test_accept_language_localizes_content_when_query_language_is_absent(): void
    {
        $response = $this->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8')
            ->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu/{$this->item->id}");

        $response
            ->assertOk()
            ->assertHeader('Content-Language', 'de')
            ->assertJsonPath('name', 'Gegrilltes Hähnchen')
            ->assertJsonPath('description', 'Mit Gemüse serviert')
            ->assertJsonPath('category.name', 'Vorspeisen')
            ->assertJsonPath('allergens.0.name', 'Milch')
            ->assertJsonPath('tags.0.label', 'Empfehlung des Küchenchefs')
            ->assertJsonPath('dietary_preference', 'Vegetarisch')
            ->assertJsonPath('modifier_groups.0.name', 'Größe wählen')
            ->assertJsonPath('modifier_groups.0.options.0.name', 'Groß');

        $this->assertStringContainsString('Accept-Language', $response->headers->get('Vary'));
    }

    public function test_accept_language_localizes_categories_from_master_category_translations(): void
    {
        $masterCategory = MasterMenuCategory::create([
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
        ]);
        $masterCategory->localizedTranslations()->create([
            'language' => 'de',
            'name' => 'Hauptgerichte',
        ]);
        MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'master_menu_category_id' => $masterCategory->id,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8')
            ->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/categories")
            ->assertOk()
            ->assertHeader('Content-Language', 'de')
            ->assertJsonFragment(['name' => 'Hauptgerichte']);
    }

    public function test_query_language_overrides_accept_language(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu?lang=de")
            ->assertOk()
            ->assertHeader('Content-Language', 'de')
            ->assertJsonPath('0.name', 'Gegrilltes Hähnchen');
    }

    public function test_unsupported_language_uses_english(): void
    {
        $url = "/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu";

        $this->withHeader('Accept-Language', 'en')
            ->getJson($url.'?lang=fr')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('0.name', 'Grilled Chicken')
            ->assertJsonPath('0.allergens.0', 'Milk')
            ->assertJsonPath('0.tags.0', "Chef's Pick")
            ->assertJsonPath('0.dietary_preference', 'Vegetarian');

        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->getJson($url)
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('0.name', 'Grilled Chicken');

        $this->getJson($url.'?lang=en')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('0.name', 'Grilled Chicken');
    }

    public function test_search_matches_requested_translation(): void
    {
        $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu?lang=de&search=Hähnchen")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->item->id);
    }
}
