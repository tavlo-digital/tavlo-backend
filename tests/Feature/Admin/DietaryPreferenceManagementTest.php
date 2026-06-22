<?php

namespace Tests\Feature\Admin;

use App\Models\DietaryPreference;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DietaryPreferenceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin', 'label' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_admin_can_view_dietary_preferences(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/dietary-preferences')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dietary-preferences/index')
                ->has('preferences', 3)
                ->where('preferences.0.slug', 'vegetarian'));
    }

    public function test_admin_can_create_and_translate_dietary_preference(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/dietary-preferences', [
                'name' => 'Flexitarian',
                'slug' => 'flexitarian',
                'icon' => '🌿',
                'sort_order' => 3,
                'is_active' => true,
                'translations' => [
                    'en' => ['name' => 'Flexitarian'],
                    'de' => ['name' => 'Flexitarisch'],
                ],
            ])
            ->assertRedirect('/admin/dietary-preferences');

        $preference = DietaryPreference::where('slug', 'flexitarian')->firstOrFail();
        $this->assertDatabaseHas('dietary_preference_translations', [
            'dietary_preference_id' => $preference->id,
            'language' => 'de',
            'name' => 'Flexitarisch',
        ]);
    }

    public function test_vendor_lookup_is_localized_and_only_returns_active_preferences(): void
    {
        $vendor = Vendor::factory()->create();
        VendorSetting::create([
            'vendor_id' => $vendor->id,
            'dashboard_language' => 'de',
            'supported_languages' => ['en', 'de'],
        ]);

        $preference = DietaryPreference::where('slug', 'vegan')->firstOrFail();
        $preference->localizedTranslations()->create(['language' => 'de', 'name' => 'Vegan auf Deutsch']);
        DietaryPreference::where('slug', 'pescetarian')->update(['is_active' => false]);

        $token = $vendor->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/vendor/dietary-preferences')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'vegan', 'name' => 'Vegan auf Deutsch'])
            ->assertJsonPath('data.1.translations.de.name', 'Vegan auf Deutsch')
            ->assertJsonMissing(['slug' => 'pescetarian']);
    }
}
