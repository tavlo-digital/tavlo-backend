<?php

namespace Tests\Feature\Settings;

use App\Models\Country;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Services\LocaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A receipt is the restaurant's legal document, so it is written in the
 * language of the country on their legal and tax details — not in whatever
 * language the diner's browser happens to ask for.
 */
class ReceiptLanguageTest extends TestCase
{
    use RefreshDatabase;

    private LocaleService $locales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locales = app(LocaleService::class);
    }

    private function vendor(?string $country, array $supported = []): Vendor
    {
        $vendor = Vendor::factory()->create(['country' => $country]);
        VendorSetting::factory()->create([
            'vendor_id' => $vendor->id,
            'supported_languages' => $supported,
            'is_live_and_discoverable' => true,
        ]);

        return $vendor->fresh();
    }

    public function test_an_austrian_restaurant_issues_german_receipts(): void
    {
        $this->assertSame('de', $this->locales->defaultLanguage($this->vendor('AT')));
        // The country is stored as a name on older vendors.
        $this->assertSame('de', $this->locales->defaultLanguage($this->vendor('Austria')));
    }

    public function test_a_german_restaurant_issues_german_receipts(): void
    {
        $this->assertSame('de', $this->locales->defaultLanguage($this->vendor('DE')));
    }

    public function test_a_uk_restaurant_issues_english_receipts(): void
    {
        $this->assertSame('en', $this->locales->defaultLanguage($this->vendor('GB')));
    }

    public function test_a_vendor_with_no_country_falls_back_to_english(): void
    {
        $this->assertSame('en', $this->locales->defaultLanguage($this->vendor(null)));
    }

    public function test_an_unknown_country_falls_back_to_english(): void
    {
        $this->assertSame('en', $this->locales->defaultLanguage($this->vendor('ZZ')));
    }

    public function test_a_country_pointing_at_a_disabled_language_falls_back(): void
    {
        // Better an English receipt than one nothing can translate.
        Country::where('code', 'AT')->update(['default_language' => 'xx']);

        $this->assertSame('en', $this->locales->defaultLanguage($this->vendor('AT')));
    }

    public function test_the_restaurants_own_language_is_always_offered(): void
    {
        // Austrian restaurant that never ticked German in its settings.
        $supported = $this->locales->supportedLanguages($this->vendor('AT', ['it']));

        $this->assertContains('de', $supported, 'receipts are written in it, so it must be available');
        $this->assertContains('en', $supported);
        $this->assertContains('it', $supported);
    }

    public function test_the_languages_endpoint_reports_the_country_default(): void
    {
        $vendor = $this->vendor('AT', ['it']);

        $response = $this->getJson("/api/customer/restaurants/{$vendor->vendor_public_id}/languages");

        $response->assertOk()
            ->assertJsonPath('default_language', 'de')
            ->assertJsonPath('available_languages', fn (array $codes) => in_array('de', $codes, true));

        $languages = collect($response->json('languages'));
        $this->assertSame('de', $languages->firstWhere('is_default', true)['code']);
        $this->assertCount(1, $languages->where('is_default', true));
    }

    public function test_a_uk_restaurant_reports_english_as_default(): void
    {
        $vendor = $this->vendor('GB', ['fr']);

        $this->getJson("/api/customer/restaurants/{$vendor->vendor_public_id}/languages")
            ->assertOk()
            ->assertJsonPath('default_language', 'en');
    }
}
