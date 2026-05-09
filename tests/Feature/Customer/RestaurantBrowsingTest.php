<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantBrowsingTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create([
            'restaurant_name' => 'Test Restaurant',
            'city'            => 'Vienna',
        ]);

        VendorSetting::create([
            'vendor_id'                 => $this->vendor->id,
            'is_live_and_discoverable'  => true,
            'description'               => 'A great restaurant',
            'currency'                  => 'EUR',
        ]);
    }

    // ----------------------------------------------------------------
    // GET /api/customer/restaurants
    // ----------------------------------------------------------------

    public function test_can_list_restaurants(): void
    {
        $response = $this->getJson('/api/customer/restaurants');

        $response->assertOk()
            ->assertJsonPath('data.0.restaurant_name', 'Test Restaurant')
            ->assertJsonPath('data.0.payment_methods.on-site', true)
            ->assertJsonPath('data.0.payment_methods.stripe', false);
    }

    public function test_can_filter_restaurants_by_city(): void
    {
        $response = $this->getJson('/api/customer/restaurants?city=Vienna');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_search_restaurants(): void
    {
        $response = $this->getJson('/api/customer/restaurants?search=Test');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_hidden_restaurants_not_listed(): void
    {
        $hidden = Vendor::factory()->create();
        VendorSetting::create([
            'vendor_id'                => $hidden->id,
            'is_live_and_discoverable' => false,
        ]);

        $response = $this->getJson('/api/customer/restaurants');

        $response->assertOk()
            ->assertJsonCount(1, 'data'); // only the discoverable one
    }

    // ----------------------------------------------------------------
    // GET /api/customer/restaurants/{vendorPublicId}
    // ----------------------------------------------------------------

    public function test_can_show_restaurant_details(): void
    {
        $response = $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}");

        $response->assertOk()
            ->assertJsonPath('restaurant_name', 'Test Restaurant')
            ->assertJsonPath('payment_methods.on-site', true)
            ->assertJsonPath('payment_methods.stripe', false)
            ->assertJsonStructure(['vendor_public_id', 'restaurant_name', 'avg_rating', 'review_count']);
    }

    public function test_restaurant_about_uses_simplified_payment_methods(): void
    {
        $this->vendor->vendorSetting->update([
            'accept_on_site' => false,
            'stripe_enabled' => true,
            'stripe_account_id' => 'acct_test_123',
            'stripe_onboarding_complete' => true,
        ]);

        $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/about")
            ->assertOk()
            ->assertJsonPath('payment_methods.on-site', false)
            ->assertJsonPath('payment_methods.stripe', true);
    }

    public function test_show_returns_404_for_invalid_id(): void
    {
        $this->getJson('/api/customer/restaurants/nonexistent')
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // GET /api/customer/restaurants/{vendorPublicId}/categories
    // ----------------------------------------------------------------

    public function test_can_get_restaurant_categories(): void
    {
        MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Starters',
            'slug'      => 'starters',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/categories");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Starters');
    }

    // ----------------------------------------------------------------
    // GET /api/customer/restaurants/{vendorPublicId}/menu
    // ----------------------------------------------------------------

    public function test_can_get_restaurant_menu(): void
    {
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Mains',
            'slug'      => 'mains',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'vendor_id'        => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name'             => 'Schnitzel',
            'price'            => 14.90,
            'is_active'        => true,
            'available'        => true,
            'sort_order'       => 1,
        ]);

        $response = $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Schnitzel');
    }

    public function test_can_filter_menu_by_category(): void
    {
        $cat1 = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Starters',
            'slug'      => 'starters',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $cat2 = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Mains',
            'slug'      => 'mains',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        MenuItem::create([
            'vendor_id'        => $this->vendor->id,
            'menu_category_id' => $cat1->id,
            'name'             => 'Soup',
            'price'            => 5.00,
            'is_active'        => true,
            'available'        => true,
            'sort_order'       => 1,
        ]);

        MenuItem::create([
            'vendor_id'        => $this->vendor->id,
            'menu_category_id' => $cat2->id,
            'name'             => 'Steak',
            'price'            => 25.00,
            'is_active'        => true,
            'available'        => true,
            'sort_order'       => 1,
        ]);

        $response = $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu?category_id={$cat2->id}");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Steak');
    }

    // ----------------------------------------------------------------
    // GET /api/customer/restaurants/{vendorPublicId}/tables
    // ----------------------------------------------------------------

    public function test_can_get_restaurant_tables(): void
    {
        RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number'    => 1,
            'name'      => 'Table 1',
            'qr_token'  => 'token-123',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/tables");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Table 1');
    }
}
