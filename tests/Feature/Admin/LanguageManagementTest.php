<?php

namespace Tests\Feature\Admin;

use App\Models\Language;
use App\Models\Role;
use App\Models\User;
use App\Services\LocaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LanguageManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin', 'label' => 'Admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_admin_can_view_managed_languages(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/languages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/languages/index')
                ->has('languages', 12)
                ->where('activeLanguages.0.code', 'en')
                ->where('activeLanguages.0.flag', '🇬🇧'));
    }

    public function test_admin_can_create_and_edit_a_language(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/languages', [
                'code' => 'pt',
                'name' => 'Portuguese',
                'native_name' => 'Português',
                'flag' => '🇵🇹',
                'direction' => 'ltr',
                'sort_order' => 12,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/languages');

        $language = Language::where('code', 'pt')->firstOrFail();
        $this->assertSame('pt', app(LocaleService::class)->normalize('pt-PT'));

        $this->actingAs($this->admin)
            ->put("/admin/languages/{$language->id}", [
                'code' => 'pt',
                'name' => 'Portuguese',
                'native_name' => 'Português',
                'flag' => '🇵🇹',
                'direction' => 'ltr',
                'sort_order' => 12,
                'is_active' => false,
            ])
            ->assertRedirect('/admin/languages');

        $this->assertFalse($language->fresh()->is_active);
        $this->assertNull(app(LocaleService::class)->normalize('pt'));
    }

    public function test_english_cannot_be_disabled_or_deleted(): void
    {
        $english = Language::where('code', 'en')->firstOrFail();

        $this->actingAs($this->admin)
            ->put("/admin/languages/{$english->id}", [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'flag' => '🇬🇧',
                'direction' => 'ltr',
                'sort_order' => 0,
                'is_active' => false,
            ])
            ->assertRedirect('/admin/languages');

        $this->assertTrue($english->fresh()->is_active);

        $this->actingAs($this->admin)
            ->delete("/admin/languages/{$english->id}")
            ->assertSessionHasErrors('language');

        $this->assertDatabaseHas('languages', ['code' => 'en']);
    }
}
