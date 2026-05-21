<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\TableScanSession;
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

    public function test_hidden_restaurant_public_child_endpoints_return_404(): void
    {
        $hidden = Vendor::factory()->create();
        VendorSetting::create([
            'vendor_id' => $hidden->id,
            'is_live_and_discoverable' => false,
        ]);
        $category = MenuCategory::create([
            'vendor_id' => $hidden->id,
            'name' => 'Hidden Mains',
            'slug' => 'hidden-mains-' . $hidden->id,
        ]);
        $item = MenuItem::create([
            'vendor_id' => $hidden->id,
            'menu_category_id' => $category->id,
            'name' => 'Hidden Burger',
            'price' => 9,
            'is_active' => true,
            'available' => true,
        ]);

        foreach ([
            "/api/customer/restaurants/{$hidden->vendor_public_id}/categories",
            "/api/customer/restaurants/{$hidden->vendor_public_id}/menu",
            "/api/customer/restaurants/{$hidden->vendor_public_id}/menu/{$item->id}",
            "/api/customer/restaurants/{$hidden->vendor_public_id}/tables",
            "/api/customer/restaurants/{$hidden->vendor_public_id}/reviews",
            "/api/customer/restaurants/{$hidden->vendor_public_id}/about",
        ] as $url) {
            $this->getJson($url)->assertNotFound();
        }
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

        $item = MenuItem::create([
            'vendor_id'        => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name'             => 'Schnitzel',
            'price'            => 14.90,
            'vat_rate'         => 10,
            'tax_category'     => 'food',
            'calories'         => 640,
            'fat'              => 28.5,
            'carbs'            => 42.25,
            'protein'          => 36,
            'paid_addons'      => [
                ['name' => 'Extra cheese', 'price' => 1.50],
            ],
            'free_addons'      => ['Ketchup'],
            'removable_items'  => ['Onions'],
            'is_active'        => true,
            'available'        => true,
            'sort_order'       => 1,
        ]);
        $group = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Choose your side',
            'type' => 'single',
            'min_selection' => 1,
            'max_selection' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);
        ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Onion Rings',
            'price_adjustment' => 1.50,
            'is_active' => true,
        ]);
        $item->modifierGroups()->sync([$group->id => ['sort_order' => 0]]);

        $response = $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu");

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Schnitzel')
            ->assertJsonPath('0.vat_rate', 10)
            ->assertJsonPath('0.tax_category', 'food')
            ->assertJsonPath('0.vat_amount', 1.49)
            ->assertJsonPath('0.calories', 640)
            ->assertJsonPath('0.fat', 28.5)
            ->assertJsonPath('0.carbs', 42.25)
            ->assertJsonPath('0.protein', 36)
            ->assertJsonPath('0.paid_addons.0.name', 'Extra cheese')
            ->assertJsonPath('0.paid_addons.0.price', 1.5)
            ->assertJsonPath('0.free_addons.0', 'Ketchup')
            ->assertJsonPath('0.removable_items.0', 'Onions')
            ->assertJsonPath('0.modifier_groups.0.name', 'Choose your side')
            ->assertJsonPath('0.modifier_groups.0.options.0.name', 'Onion Rings')
            ->assertJsonPath('0.modifier_groups.0.options.0.price_adjustment', 1.5);
    }

    public function test_restaurant_menu_vat_amount_uses_discounted_price(): void
    {
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Mains',
            'slug'      => 'discounted-mains',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'vendor_id'        => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name'             => 'Discounted Pasta',
            'price'            => 20.00,
            'has_discount'     => true,
            'discount_percent' => 25,
            'discounted_price' => 15.00,
            'vat_rate'         => 10,
            'tax_category'     => 'food',
            'is_active'        => true,
            'available'        => true,
            'sort_order'       => 1,
        ]);

        $response = $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu");

        $response->assertOk()
            ->assertJsonPath('0.discounted_price', 15)
            ->assertJsonPath('0.vat_amount', 1.5);
    }

    public function test_unavailable_menu_items_are_not_browsable(): void
    {
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Unavailable',
            'slug' => 'unavailable-' . $this->vendor->id,
        ]);
        $item = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Sold Out Soup',
            'price' => 5,
            'is_active' => true,
            'available' => false,
        ]);
        $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu")
            ->assertOk()
            ->assertJsonCount(0);

        $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu/{$item->id}")
            ->assertNotFound();
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

    public function test_can_get_restaurant_menu_item_detail_with_vat(): void
    {
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Mains',
            'slug'      => 'detail-mains',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $item = MenuItem::create([
            'vendor_id'        => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name'             => 'Caesar Salad',
            'price'            => 12.50,
            'vat_rate'         => 20,
            'tax_category'     => 'food',
            'paid_addons'      => [
                ['name' => 'Grilled chicken', 'price' => 3.00],
            ],
            'free_addons'      => ['Croutons'],
            'removable_items'  => ['Parmesan'],
            'is_active'        => true,
            'available'        => true,
            'sort_order'       => 1,
        ]);
        $group = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Choose your protein',
            'type' => 'multiple',
            'min_selection' => 0,
            'max_selection' => 2,
            'is_required' => false,
            'is_active' => true,
        ]);
        ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Grilled chicken',
            'price_adjustment' => 3.00,
            'is_active' => true,
        ]);
        $item->modifierGroups()->sync([$group->id => ['sort_order' => 0]]);

        $response = $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu/{$item->id}");

        $response->assertOk()
            ->assertJsonPath('name', 'Caesar Salad')
            ->assertJsonPath('vat_rate', 20)
            ->assertJsonPath('tax_category', 'food')
            ->assertJsonPath('vat_amount', 2.5)
            ->assertJsonPath('paid_addons.0.name', 'Grilled chicken')
            ->assertJsonPath('paid_addons.0.price', 3)
            ->assertJsonPath('free_addons.0', 'Croutons')
            ->assertJsonPath('removable_items.0', 'Parmesan')
            ->assertJsonPath('modifier_groups.0.name', 'Choose your protein')
            ->assertJsonPath('modifier_groups.0.options.0.price_adjustment', 3);
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

    // ----------------------------------------------------------------
    // GET /api/customer/restaurants/{vendorPublicId}/reviews
    // ----------------------------------------------------------------

    public function test_restaurant_reviews_derive_menu_items_from_cart_items(): void
    {
        $customer = Customer::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Diner',
        ]);

        $table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 7,
            'name' => 'Table 7',
            'qr_token' => 'review-table-token',
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $session = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Caesar Salad',
            'price' => 8.50,
            'image_url' => 'menu-items/57/photo.png',
            'is_active' => true,
            'available' => true,
            'sort_order' => 1,
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'payment_received' => true,
            'payment_pending' => false,
            'status' => 'completed',
        ]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $order->id,
            'quantity' => 2,
        ]);

        Review::create([
            'review_public_id' => 'rev_public_menu_items',
            'customer_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
            'rating' => 5,
            'text' => 'Fresh and fast.',
        ]);

        $response = $this->getJson("/api/customer/restaurants/{$this->vendor->vendor_public_id}/reviews");

        $response->assertOk()
            ->assertJsonPath('data.0.menu_items.0.id', $menuItem->id)
            ->assertJsonPath('data.0.menu_items.0.name', 'Caesar Salad')
            ->assertJsonPath('data.0.menu_items.0.slug', 'caesar-salad')
            ->assertJsonPath('data.0.menu_items.0.image_url', 'menu-items/57/photo.png')
            ->assertJsonPath('data.0.menu_items.0.quantity', 2)
            ->assertJsonPath('review_summary.total_reviews', 1);
    }
}
