<?php

namespace Tests\Feature\Menu;

use App\Models\Allergen;
use App\Models\SpecialTag;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceLookupsTest extends TestCase
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
    // GET /api/vendor/allergens
    // ----------------------------------------------------------------

    public function test_allergens_returns_active_allergens(): void
    {
        Allergen::create(['name' => 'Gluten',  'icon' => '🌾', 'sort_order' => 1, 'is_active' => true]);
        Allergen::create(['name' => 'Dairy',   'icon' => '🥛', 'sort_order' => 2, 'is_active' => true]);
        Allergen::create(['name' => 'Inactive', 'icon' => null,  'sort_order' => 3, 'is_active' => false]);

        $response = $this->getJson('/api/vendor/allergens', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Gluten')
            ->assertJsonPath('data.0.icon', '🌾')
            ->assertJsonFragment(['name' => 'Dairy'])
            ->assertJsonMissing(['name' => 'Inactive']);
    }

    public function test_allergens_response_has_expected_fields(): void
    {
        $allergen = Allergen::create(['name' => 'Eggs', 'icon' => '🥚', 'sort_order' => 1, 'is_active' => true]);
        $allergen->localizedTranslations()->create(['language' => 'de', 'name' => 'Eier']);

        $this->getJson('/api/vendor/allergens', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.key', 'Eggs')
            ->assertJsonPath('data.0.translations.de.name', 'Eier')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'key', 'name', 'icon', 'translations'],
                ],
            ]);
    }

    public function test_allergens_requires_authentication(): void
    {
        $this->getJson('/api/vendor/allergens')->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    // GET /api/vendor/special-tags
    // ----------------------------------------------------------------

    public function test_special_tags_returns_active_tags(): void
    {
        SpecialTag::create(['slug' => 'popular',     'label' => 'Popular',     'sort_order' => 1, 'is_active' => true]);
        SpecialTag::create(['slug' => 'new',         'label' => 'New',         'sort_order' => 2, 'is_active' => true]);
        SpecialTag::create(['slug' => 'discontinued', 'label' => 'Discontinued', 'sort_order' => 3, 'is_active' => false]);

        $response = $this->getJson('/api/vendor/special-tags', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['slug' => 'popular', 'label' => 'Popular'])
            ->assertJsonMissing(['slug' => 'discontinued']);
    }

    public function test_special_tags_response_has_expected_fields(): void
    {
        $tag = SpecialTag::create(['slug' => 'spicy', 'label' => 'Spicy', 'icon' => '🌶', 'sort_order' => 1, 'is_active' => true]);
        $tag->localizedTranslations()->create(['language' => 'de', 'label' => 'Scharf']);

        $this->getJson('/api/vendor/special-tags', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.translations.de.label', 'Scharf')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'label', 'icon', 'translations'],
                ],
            ]);
    }

    public function test_special_tags_requires_authentication(): void
    {
        $this->getJson('/api/vendor/special-tags')->assertUnauthorized();
    }
}
