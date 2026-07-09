<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableCartTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Vendor $vendor;

    private RestaurantTable $table;

    private TableScanSession $session;

    private MenuItem $menuItem;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $this->vendor = Vendor::factory()->create();

        $token = $this->customer->createToken('test', ['role:customer'])->plainTextToken;
        $this->headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->table = $this->vendor->restaurantTables()->create([
            'number' => 1,
            'name' => 'T1',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Sides',
            'slug' => 'sides-'.$this->vendor->id,
        ]);

        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Fries',
            'price' => 3.50,
            'vat_rate' => 20,
            'paid_addons' => [
                ['name' => 'Cheese sauce', 'price' => 1.50],
                ['name' => 'Truffle mayo', 'price' => 2.00],
            ],
            'free_addons' => ['Ketchup', 'Chili flakes'],
            'removable_items' => ['Salt'],
        ]);

        $this->session = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $this->customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);
    }

    private function configureArabicCustomizations(): array
    {
        VendorSetting::updateOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'supported_languages' => ['en', 'ar'],
                'is_live_and_discoverable' => true,
            ],
        );

        $this->menuItem->update([
            'paid_addons' => [
                [
                    'id' => 5,
                    'name' => 'Cheese sauce',
                    'price' => 1.50,
                    'translations' => ['ar' => ['name' => 'صلصة الجبن']],
                ],
            ],
            'free_addons' => [
                ['id' => 8, 'name' => 'Ketchup', 'translations' => ['ar' => ['name' => 'كاتشب']]],
            ],
            'removable_items' => [
                ['id' => 11, 'name' => 'Salt', 'translations' => ['ar' => ['name' => 'ملح']]],
            ],
        ]);
        $this->menuItem->itemTranslations()->updateOrCreate(
            ['language' => 'ar'],
            ['name' => 'بطاطس مقلية', 'description' => 'بطاطس مقرمشة'],
        );

        $group = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Choose your side',
            'type' => 'single',
            'min_selection' => 1,
            'max_selection' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);
        $group->localizedTranslations()->create(['language' => 'ar', 'name' => 'اختر الطبق الجانبي']);

        $option = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Onion Rings',
            'price_adjustment' => 1.50,
            'is_active' => true,
        ]);
        $option->localizedTranslations()->create(['language' => 'ar', 'name' => 'حلقات البصل']);
        $this->menuItem->modifierGroups()->sync([$group->id => ['sort_order' => 0]]);

        return [$group, $option];
    }

    private function assertArabicCustomizationPayload(array $payload): void
    {
        $this->assertSame('بطاطس مقلية', data_get($payload, 'menu_item.name', $payload['name'] ?? null));
        $this->assertSame('صلصة الجبن', data_get($payload, 'paid_addons.0.name'));
        $this->assertSame('كاتشب', data_get($payload, 'free_addons.0'));
        $this->assertSame('ملح', data_get($payload, 'removed_items.0'));
        $this->assertSame('اختر الطبق الجانبي', data_get($payload, 'selected_modifiers.0.name'));
        $this->assertSame('حلقات البصل', data_get($payload, 'selected_modifiers.0.options.0.name'));
    }

    // ----------------------------------------------------------------
    // GET /api/customer/cart
    // ----------------------------------------------------------------

    public function test_get_cart_requires_auth(): void
    {
        $this->getJson('/api/customer/cart')
            ->assertUnauthorized();
    }

    public function test_get_cart_returns_422_when_no_active_session(): void
    {
        $this->session->update(['status' => 'closed']);

        $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart')
            ->assertStatus(422)
            ->assertJson(['message' => 'No active table session found.']);
    }

    public function test_get_cart_returns_empty_cart_for_new_session(): void
    {
        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk()
            ->assertJsonStructure(['people']);

        $this->assertCount(1, $response->json('people'));
        $this->assertSame([], $response->json('people.0.personal_items'));
    }

    public function test_get_cart_includes_all_people_at_table(): void
    {
        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $other->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk();
        $this->assertCount(2, $response->json('people'));
    }

    public function test_get_cart_items_include_price_vat_and_line_total(): void
    {
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
            'notes' => 'No salt',
        ]);

        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $otherSession = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $other->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk();

        $people = collect($response->json('people'))->keyBy('session_id');
        $myItem = $people[$this->session->id]['personal_items'][0];
        $otherItem = $people[$otherSession->id]['personal_items'][0];

        $this->assertSame(3.85, $myItem['price']);
        $this->assertSame(0.7, $myItem['vat_amount']);
        $this->assertSame(7.7, $myItem['line_total']);
        $this->assertSame(10, $myItem['menu_item']['vat_rate']);
        $this->assertSame('food', $myItem['menu_item']['tax_category']);

        $this->assertSame(0.35, $otherItem['vat_amount']);
        $this->assertSame(10, $otherItem['menu_item']['vat_rate']);
    }

    public function test_get_cart_does_not_include_closed_sessions(): void
    {
        $closed = Customer::factory()->create();
        TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $closed->id,
            'pin' => '',
            'status' => 'closed',
            'scanned_at' => now(),
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk();
        $this->assertCount(1, $response->json('people'));
    }

    public function test_get_cart_keeps_items_visible_while_order_is_draft(): void
    {
        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);
        $item->forceFill(['created_at' => now()->subMinute()])->save();

        $draft = Order::create([
            'order_public_id' => 'ord-draft-cart-visible',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'draft',
            'amount' => 3.50,
            'currency' => 'EUR',
        ]);

        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $otherSession = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $other->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
            'shared_order_ids' => [$draft->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk();

        $people = collect($response->json('people'))->keyBy('session_id');
        $this->assertCount(1, $people[$this->session->id]['personal_items']);
        $this->assertCount(1, $people[$otherSession->id]['personal_items']);
    }

    public function test_get_cart_hides_items_after_order_is_confirmed(): void
    {
        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);
        $item->forceFill(['created_at' => now()->subMinute()])->save();

        $order = Order::create([
            'order_public_id' => 'ord-confirmed-cart-hidden',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'confirmed',
            'amount' => 3.50,
            'currency' => 'EUR',
        ]);
        $item->update(['order_id' => $order->id]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk()
            ->assertJsonPath('people.0.personal_items', []);
    }

    // ----------------------------------------------------------------
    // POST /api/customer/cart/items
    // ----------------------------------------------------------------

    public function test_add_item_requires_auth(): void
    {
        $this->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertUnauthorized();
    }

    public function test_add_item_fails_without_active_session(): void
    {
        $this->session->update(['status' => 'closed']);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertStatus(422);
    }

    public function test_add_item_validates_menu_item_id(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['menu_item_id']);
    }

    public function test_add_item_creates_cart_item(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'notes' => 'No salt',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'quantity', 'notes', 'price', 'vat_amount', 'line_total', 'menu_item'])
            ->assertJsonPath('quantity', 2)
            ->assertJsonPath('notes', 'No salt')
            ->assertJsonPath('price', 3.85)
            ->assertJsonPath('vat_amount', 0.7)
            ->assertJsonPath('line_total', 7.7)
            ->assertJsonPath('menu_item.name', 'Fries')
            ->assertJsonPath('menu_item.vat_rate', 10);

        $this->assertDatabaseHas('cart_items', [
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ]);
    }

    public function test_add_item_accepts_customization_options(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'paid_addons' => [
                    ['name' => 'Cheese sauce', 'price' => 0],
                ],
                'free_addons' => ['Ketchup'],
                'removed_items' => ['Salt'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('quantity', 2)
            ->assertJsonPath('price', 5.5)
            ->assertJsonPath('line_total', 11)
            ->assertJsonPath('vat_amount', 1)
            ->assertJsonPath('paid_addons.0.name', 'Cheese sauce')
            ->assertJsonPath('paid_addons.0.price', 1.65)
            ->assertJsonPath('free_addons.0', 'Ketchup')
            ->assertJsonPath('removed_items.0', 'Salt');

        $cart = $this->withHeaders($this->headers)->getJson('/api/customer/cart');

        $cart->assertOk()
            ->assertJsonPath('people.0.personal_items.0.paid_addons.0.name', 'Cheese sauce')
            ->assertJsonPath('people.0.personal_items.0.free_addons.0', 'Ketchup')
            ->assertJsonPath('people.0.personal_items.0.removed_items.0', 'Salt')
            ->assertJsonPath('people.0.personal_items.0.line_total', 11);
    }

    public function test_add_item_accepts_id_based_customizations_and_returns_language_based_names(): void
    {
        [$group, $option] = $this->configureArabicCustomizations();

        $headers = array_merge($this->headers, ['Accept-Language' => 'ar']);

        $response = $this->withHeaders($headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'paid_addons' => [['id' => 5]],
                'free_addons' => [['id' => 8]],
                'removed_items' => [['id' => 11]],
                'selected_modifiers' => [
                    [
                        'modifier_group_id' => $group->id,
                        'option_ids' => [$option->id],
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('menu_item.name', 'بطاطس مقلية')
            ->assertJsonPath('paid_addons.0.id', 5)
            ->assertJsonPath('paid_addons.0.name', 'صلصة الجبن')
            ->assertJsonPath('free_addons.0', 'كاتشب')
            ->assertJsonPath('removed_items.0', 'ملح')
            ->assertJsonPath('selected_modifiers.0.name', 'اختر الطبق الجانبي')
            ->assertJsonPath('selected_modifiers.0.options.0.name', 'حلقات البصل');

        $cartItem = CartItem::firstOrFail();
        $this->assertSame(5, $cartItem->paid_addons[0]['id']);
        $this->assertArrayNotHasKey('name', $cartItem->paid_addons[0]);
        $this->assertSame([8], $cartItem->free_addons);
        $this->assertSame([11], $cartItem->removed_items);
        $this->assertSame($group->id, $cartItem->selected_modifiers[0]['modifier_group_id']);
        $this->assertArrayNotHasKey('name', $cartItem->selected_modifiers[0]);
        $this->assertArrayNotHasKey('name', $cartItem->selected_modifiers[0]['options'][0]);
    }

    public function test_accept_language_localizes_cart_and_all_order_item_responses(): void
    {
        [$group, $option] = $this->configureArabicCustomizations();
        $this->menuItem->itemTranslations()->delete();
        $this->menuItem->update([
            'translations' => [
                'ar' => ['name' => 'بطاطس مقلية', 'description' => 'بطاطس مقرمشة'],
            ],
        ]);

        $legacyCustomizations = [
            'paid_addons' => [['name' => 'Cheese sauce', 'price' => 1.50]],
            'free_addons' => ['Ketchup'],
            'removed_items' => ['Salt'],
            'selected_modifiers' => [[
                'modifier_group_id' => $group->id,
                'name' => 'Choose your side',
                'options' => [[
                    'id' => $option->id,
                    'name' => 'Onion Rings',
                    'price_adjustment' => 1.50,
                ]],
            ]],
        ];

        CartItem::create(array_merge($legacyCustomizations, [
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]));

        $order = Order::create([
            'order_public_id' => 'ord-arabic-responses',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'confirmed',
            'amount' => 7,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_received' => true,
            'payment_pending' => false,
            'payment_confirmed_at' => now(),
        ]);

        CartItem::create(array_merge($legacyCustomizations, [
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
        ]));

        $headers = array_merge($this->headers, ['Accept-Language' => 'ar']);

        $cart = $this->withHeaders($headers)->getJson('/api/customer/cart')->assertOk();
        $this->assertArabicCustomizationPayload($cart->json('people.0.personal_items.0'));

        $history = $this->withHeaders($headers)->getJson('/api/customer/orders/history')->assertOk();
        $this->assertArabicCustomizationPayload($history->json('history.0.orders.0.items.0'));

        $detail = $this->withHeaders($headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}")
            ->assertOk();
        $this->assertArabicCustomizationPayload($detail->json('items.0'));

        $tracking = $this->withHeaders($headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking")
            ->assertOk();
        $this->assertArabicCustomizationPayload($tracking->json('items.0'));

        $receipt = $this->withHeaders($headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/receipt")
            ->assertOk();
        $this->assertArabicCustomizationPayload($receipt->json('data.order.items.0'));
    }

    public function test_add_item_accepts_translated_customization_names_for_legacy_clients(): void
    {
        VendorSetting::updateOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'supported_languages' => ['en', 'ar'],
                'is_live_and_discoverable' => true,
            ],
        );

        $this->menuItem->update([
            'paid_addons' => [
                [
                    'id' => 5,
                    'name' => 'Cheese sauce',
                    'price' => 1.50,
                    'translations' => ['ar' => ['name' => 'صلصة الجبن']],
                ],
            ],
            'free_addons' => [
                ['id' => 8, 'name' => 'Ketchup', 'translations' => ['ar' => ['name' => 'كاتشب']]],
            ],
            'removable_items' => [
                ['id' => 11, 'name' => 'Salt', 'translations' => ['ar' => ['name' => 'ملح']]],
            ],
        ]);

        $response = $this->withHeaders(array_merge($this->headers, ['Accept-Language' => 'ar']))
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'paid_addons' => [['name' => 'صلصة الجبن']],
                'free_addons' => ['كاتشب'],
                'removed_items' => ['ملح'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('paid_addons.0.id', 5)
            ->assertJsonPath('paid_addons.0.name', 'صلصة الجبن')
            ->assertJsonPath('free_addons.0', 'كاتشب')
            ->assertJsonPath('removed_items.0', 'ملح');

        $cartItem = CartItem::firstOrFail();
        $this->assertSame(5, $cartItem->paid_addons[0]['id']);
        $this->assertArrayNotHasKey('name', $cartItem->paid_addons[0]);
        $this->assertSame([8], $cartItem->free_addons);
        $this->assertSame([11], $cartItem->removed_items);
    }

    public function test_notifications_use_admin_template_for_requested_language(): void
    {
        VendorSetting::updateOrCreate(
            ['vendor_id' => $this->vendor->id],
            [
                'supported_languages' => ['en', 'ar'],
                'is_live_and_discoverable' => true,
            ],
        );
        $this->menuItem->itemTranslations()->create([
            'language' => 'ar',
            'name' => 'بطاطس مقلية',
            'description' => null,
        ]);
        NotificationTemplate::create([
            'key' => 'cart.item_added',
            'language' => 'ar',
            'message' => '{customer_name} أضاف {item_name}',
        ]);

        $this->withHeaders(array_merge($this->headers, ['Accept-Language' => 'ar']))
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
            ])
            ->assertCreated();

        $this->withHeaders(array_merge($this->headers, ['Accept-Language' => 'ar']))
            ->getJson('/api/customer/notifications')
            ->assertOk()
            ->assertJsonPath('notifications.0.message', 'Alice Smith أضاف بطاطس مقلية');
    }

    public function test_add_item_accepts_selected_modifier_groups_and_prices_them(): void
    {
        $group = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Choose your side',
            'type' => 'single',
            'min_selection' => 1,
            'max_selection' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);
        $fries = ModifierOption::create([
            'modifier_group_id' => $group->id,
            'name' => 'Onion Rings',
            'price_adjustment' => 1.50,
            'is_active' => true,
        ]);
        $this->menuItem->modifierGroups()->sync([$group->id => ['sort_order' => 0]]);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'selected_modifiers' => [
                    [
                        'modifier_group_id' => $group->id,
                        'option_ids' => [$fries->id],
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('price', 5.5)
            ->assertJsonPath('line_total', 11)
            ->assertJsonPath('vat_amount', 1)
            ->assertJsonPath('selected_modifiers.0.name', 'Choose your side')
            ->assertJsonPath('selected_modifiers.0.options.0.name', 'Onion Rings')
            ->assertJsonPath('selected_modifiers.0.options.0.price_adjustment', 1.65);

        $cart = $this->withHeaders($this->headers)->getJson('/api/customer/cart');

        $cart->assertOk()
            ->assertJsonPath('people.0.personal_items.0.selected_modifiers.0.options.0.name', 'Onion Rings')
            ->assertJsonPath('people.0.personal_items.0.line_total', 11);
    }

    public function test_add_item_rejects_missing_required_modifier_selection(): void
    {
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
            'name' => 'Fries',
            'price_adjustment' => 0,
            'is_active' => true,
        ]);
        $this->menuItem->modifierGroups()->sync([$group->id => ['sort_order' => 0]]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'selected_modifiers' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['selected_modifiers']);
    }

    public function test_add_item_rejects_unavailable_customization_options(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'paid_addons' => [
                    ['name' => 'Gold flakes', 'price' => 0],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['paid_addons']);
    }

    public function test_add_item_rejects_menu_item_from_another_restaurant(): void
    {
        $otherVendor = Vendor::factory()->create();
        $otherCategory = MenuCategory::create([
            'vendor_id' => $otherVendor->id,
            'name' => 'Other Sides',
            'slug' => 'other-sides-'.$otherVendor->id,
        ]);
        $otherItem = MenuItem::create([
            'vendor_id' => $otherVendor->id,
            'menu_category_id' => $otherCategory->id,
            'name' => 'Other Fries',
            'price' => 4,
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $otherItem->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['menu_item_id']);
    }

    public function test_add_item_rejects_unavailable_menu_item(): void
    {
        $this->menuItem->update(['available' => false]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['menu_item_id']);
    }

    public function test_same_menu_item_with_different_customizations_creates_separate_cart_rows(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'paid_addons' => [
                    ['name' => 'Cheese sauce'],
                ],
            ])
            ->assertCreated();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'paid_addons' => [
                    ['name' => 'Truffle mayo'],
                ],
            ])
            ->assertCreated();

        $response = $this->withHeaders($this->headers)->getJson('/api/customer/cart');

        $response->assertOk()
            ->assertJsonCount(2, 'people.0.personal_items');
    }

    public function test_add_item_defaults_quantity_to_1(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id]);

        $response->assertCreated()->assertJsonPath('quantity', 1);
    }

    // ----------------------------------------------------------------
    // PATCH /api/customer/cart/items/{id}
    // ----------------------------------------------------------------

    public function test_update_item_changes_quantity_and_notes(): void
    {
        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);

        $this->withHeaders($this->headers)
            ->patchJson("/api/customer/cart/items/{$item->id}", ['quantity' => 3, 'notes' => 'Extra crispy'])
            ->assertOk()
            ->assertJsonPath('quantity', 3)
            ->assertJsonPath('notes', 'Extra crispy');
    }

    public function test_update_item_returns_404_for_another_sessions_item(): void
    {
        $other = Customer::factory()->create();
        $otherSession = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $other->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);

        $this->withHeaders($this->headers)
            ->patchJson("/api/customer/cart/items/{$item->id}", ['quantity' => 5])
            ->assertNotFound();
    }

    // ----------------------------------------------------------------
    // DELETE /api/customer/cart/items/{id}
    // ----------------------------------------------------------------

    public function test_remove_item_deletes_own_item(): void
    {
        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);

        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/cart/items/{$item->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_remove_item_returns_404_for_foreign_item(): void
    {
        $other = Customer::factory()->create();
        $otherSession = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $other->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);

        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/cart/items/{$item->id}")
            ->assertNotFound();
    }

    public function test_cart_response_contains_personal_items_with_customer_id(): void
    {
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk()
            ->assertJsonPath('people.0.customer_id', $this->customer->id)
            ->assertJsonPath('people.0.is_me', true)
            ->assertJsonPath('people.0.name', 'Alice Smith');

        $this->assertCount(1, $response->json('people.0.personal_items'));
    }

    public function test_confirmed_order_binds_open_cart_items_and_next_same_item_stays_visible(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertCreated();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/draft')
            ->assertCreated();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/confirmed')
            ->assertOk();

        $order = Order::where('customer_id', $this->customer->id)->latest('id')->first();
        $this->assertDatabaseHas('cart_items', [
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertCreated();

        $response = $this->withHeaders($this->headers)->getJson('/api/customer/cart');

        $response->assertOk()
            ->assertJsonCount(1, 'people.0.personal_items')
            ->assertJsonPath('people.0.personal_items.0.quantity', 1);
    }

    public function test_confirm_order_merges_open_items_into_existing_unpaid_submitted_order(): void
    {
        $existingOrder = Order::create([
            'order_public_id' => 'ord-existing-unpaid',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'amount' => 3.50,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'order_id' => $existingOrder->id,
            'received_at' => now(),
        ]);

        $draftOrder = Order::create([
            'order_public_id' => 'ord-stray-draft',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_DRAFT,
            'amount' => 3.50,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $newItem = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);

        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $otherSession = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $other->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);
        $sharedItem = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
            'shared_order_ids' => [$draftOrder->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/confirmed');

        $response->assertOk()
            ->assertJsonPath('people.0.orders_count', 1)
            ->assertJsonPath('people.0.orders.0.id', $existingOrder->id)
            ->assertJsonPath('people.0.orders.0.status', Order::STATUS_IN_PROGRESS);

        $this->assertDatabaseMissing('orders', ['id' => $draftOrder->id]);
        $this->assertSame($existingOrder->id, $newItem->fresh()->order_id);
        $this->assertSame([$existingOrder->id], $sharedItem->fresh()->shared_order_ids);
        $this->assertSame(11.55, (float) $existingOrder->fresh()->amount);
    }

    public function test_confirm_order_does_not_confirm_draft_from_a_different_active_session(): void
    {
        $order = Order::create([
            'order_public_id' => 'ord-wrong-session-draft',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'draft',
            'amount' => 3.50,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $otherTable = $this->vendor->restaurantTables()->create([
            'number' => 2,
            'name' => 'T2',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
        TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $otherTable->id,
            'customer_id' => $this->customer->id,
            'pin' => '5678',
            'status' => 'active',
            'scanned_at' => now()->addMinute(),
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/confirmed')
            ->assertNotFound();

        $this->assertSame('draft', $order->fresh()->status);
    }

    public function test_update_order_rejects_duplicate_shared_item(): void
    {
        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $otherSession = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $other->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-share-caller',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_DRAFT,
            'amount' => 0,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'shared_order_ids' => [$order->id],
        ]);

        $this->withHeaders($this->headers)
            ->putJson("/api/customer/table/order/update/{$order->id}", [
                'shared_item' => $item->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This item is already shared with your order.');

        $this->assertSame([$order->id], $item->fresh()->shared_order_ids);
    }

    public function test_update_order_rejects_shared_item_from_order_paid_by_current_customer(): void
    {
        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $otherSession = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $other->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-share-payer',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_DRAFT,
            'amount' => 0,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $otherOrder = Order::create([
            'order_public_id' => 'ord-paid-by-caller',
            'customer_id' => $other->id,
            'paid_by' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status' => Order::STATUS_CONFIRMED,
            'amount' => 3.50,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => false,
            'payment_received' => false,
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $otherOrder->id,
            'quantity' => 1,
        ]);

        $this->withHeaders($this->headers)
            ->putJson("/api/customer/table/order/update/{$order->id}", [
                'shared_item' => $item->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You are already paying for this item.');

        $this->assertSame([], $item->fresh()->shared_order_ids ?? []);
    }

    public function test_table_history_items_include_status_from_preparation_timestamps(): void
    {
        VendorSetting::factory()->create([
            'vendor_id' => $this->vendor->id,
            'date_format' => 'MM/DD/YYYY',
            'time_format' => '12h',
        ]);

        $new = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);
        $preparing = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'preparing_start_at' => now(),
        ]);
        $ready = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'preparing_start_at' => now()->subMinutes(5),
            'ready_at' => now(),
        ]);
        $served = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'preparing_start_at' => now()->subMinutes(10),
            'ready_at' => now()->subMinutes(5),
            'served_at' => now(),
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-status-test',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'confirmed',
            'amount' => 10,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
        ]);
        CartItem::whereIn('id', [$new->id, $preparing->id, $ready->id, $served->id])
            ->update(['order_id' => $order->id]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/table/history');

        $response->assertOk();

        $items = collect($response->json('people.0.orders.0.items'))->keyBy('cart_item_id');

        $this->assertSame('new', $items[$new->id]['status']);
        $this->assertSame('preparing', $items[$preparing->id]['status']);
        $this->assertSame('ready', $items[$ready->id]['status']);
        $this->assertSame('served', $items[$served->id]['status']);
        $this->assertSame($served->fresh()->served_at->copy()->setTimezone($this->vendor->resolveTimezone())->format('m/d/Y g:i A'), $items[$served->id]['served_at']);
    }

    public function test_table_history_returns_all_orders_for_active_table_session(): void
    {
        $firstItem = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);
        $secondItem = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ]);

        $firstOrder = Order::create([
            'order_public_id' => 'ord-history-first',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'completed',
            'amount' => 3.50,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $secondOrder = Order::create([
            'order_public_id' => 'ord-history-second',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'confirmed',
            'amount' => 7,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstItem->update(['order_id' => $firstOrder->id]);
        $secondItem->update(['order_id' => $secondOrder->id]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/table/history');

        $response->assertOk()
            ->assertJsonCount(2, 'people.0.orders')
            ->assertJsonPath('people.0.orders_count', 2)
            ->assertJsonPath('people.0.total_amount', 10.5)
            ->assertJsonPath('people.0.orders.0.order_public_id', 'ord-history-first')
            ->assertJsonPath('people.0.orders.0.items.0.cart_item_id', $firstItem->id)
            ->assertJsonPath('people.0.orders.1.order_public_id', 'ord-history-second')
            ->assertJsonPath('people.0.orders.1.items.0.cart_item_id', $secondItem->id)
            ->assertJsonPath('summary.orders_count', 2)
            ->assertJsonPath('summary.total_amount', 10.5);
    }

    public function test_table_history_items_include_vat_and_customizations(): void
    {
        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
            'paid_addons' => [
                ['name' => 'Cheese sauce', 'price' => 1.50],
            ],
            'free_addons' => ['Ketchup'],
            'removed_items' => ['Salt'],
            'selected_modifiers' => [
                [
                    'modifier_group_id' => 1,
                    'name' => 'Choose your side',
                    'type' => 'single',
                    'is_required' => true,
                    'min_selection' => 1,
                    'max_selection' => 1,
                    'options' => [
                        ['id' => 1, 'name' => 'Onion Rings', 'price_adjustment' => 1.50],
                    ],
                ],
            ],
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-history-customizations',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'confirmed',
            'amount' => 10,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
        ]);
        $item->update(['order_id' => $order->id]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/table/history');

        $response->assertOk();

        $payload = collect($response->json('people.0.orders.0.items'))->keyBy('cart_item_id')[$item->id];

        $this->assertSame(7.15, $payload['unit_price']);
        $this->assertSame(14.3, $payload['line_total']);
        $this->assertSame(10, $payload['vat_rate']);
        $this->assertSame('food', $payload['tax_category']);
        $this->assertSame(1.3, $payload['vat_amount']);
        $this->assertSame('Cheese sauce', $payload['paid_addons'][0]['name']);
        $this->assertSame(1.65, $payload['paid_addons'][0]['price']);
        $this->assertSame('Ketchup', $payload['free_addons'][0]);
        $this->assertSame('Salt', $payload['removed_items'][0]);
        $this->assertSame('Choose your side', $payload['selected_modifiers'][0]['name']);
        $this->assertSame('Onion Rings', $payload['selected_modifiers'][0]['options'][0]['name']);
    }

    public function test_order_tracking_returns_own_items_and_empty_shared_items_by_default(): void
    {
        VendorSetting::factory()->create([
            'vendor_id' => $this->vendor->id,
            'estimated_prep_time' => 30,
            'date_format' => 'MM/DD/YYYY',
            'time_format' => '12h',
        ]);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
            'notes' => 'No salt',
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-tracking-default',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'draft',
            'amount' => 7,
            'currency' => 'EUR',
            'order_number' => 1001,
            'order_type' => 'dine-in',
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('order_public_id', 'ord-tracking-default')
            ->assertJsonPath('order_number', '1001')
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('total_amount', 7)
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('payment_pending', true)
            ->assertJsonPath('payment_received', false)
            ->assertJsonPath('items.0.name', 'Fries')
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.unit_price', 3.85)
            ->assertJsonPath('items.0.line_total', 7.7)
            ->assertJsonPath('items.0.notes', 'No salt')
            ->assertJsonPath('shared_items', []);

        $this->assertSame(
            $order->created_at->copy()->addMinutes(30)->setTimezone($this->vendor->resolveTimezone())->format('m/d/Y g:i A'),
            $response->json('estimated_delivery_time')
        );
    }

    public function test_order_tracking_includes_only_actual_shared_items(): void
    {
        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $otherSession = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $other->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $pizza = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $this->menuItem->menu_category_id,
            'name' => 'Pizza',
            'price' => 18.99,
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-tracking-shared',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'confirmed',
            'amount' => 25.99,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
        ]);

        $otherOrder = Order::create([
            'order_public_id' => 'ord-bob-shared',
            'customer_id' => $other->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status' => 'confirmed',
            'amount' => 9.50,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
        ]);

        $ownedShared = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 2,
            'shared_order_ids' => [$otherOrder->id],
            'preparing_start_at' => now(),
        ]);

        $sharedInto = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $pizza->id,
            'quantity' => 1,
            'shared_order_ids' => [$order->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk()
            ->assertJsonPath('items.0.cart_item_id', $ownedShared->id)
            ->assertJsonPath('items.0.status', 'preparing');

        $sharedItems = collect($response->json('shared_items'))->keyBy('cart_item_id');

        $this->assertSame(2, $sharedItems->count());
        $this->assertSame(2, $sharedItems[$ownedShared->id]['shared_between']);
        $this->assertSame(3.85, $sharedItems[$ownedShared->id]['my_share']);
        $this->assertSame($otherOrder->id, $sharedItems[$ownedShared->id]['shared_with'][0]['order_id']);
        $this->assertSame($other->id, $sharedItems[$ownedShared->id]['shared_with'][0]['customer_id']);
        $this->assertSame('Bob Jones', $sharedItems[$ownedShared->id]['shared_with'][0]['customer_name']);
        $this->assertSame(10.45, $sharedItems[$sharedInto->id]['my_share']);
        $this->assertSame($order->id, $sharedItems[$sharedInto->id]['shared_with'][0]['order_id']);
        $this->assertSame($this->customer->id, $sharedItems[$sharedInto->id]['shared_with'][0]['customer_id']);
        $this->assertSame('Alice Smith', $sharedItems[$sharedInto->id]['shared_with'][0]['customer_name']);
    }

    // ----------------------------------------------------------------
    // Split-Payment Arithmetic (ISSUE-019)
    // ----------------------------------------------------------------

    public function test_unshared_item_pays_full_line_total(): void
    {
        $order = $this->createConfirmedOrder();
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk();
        $item = $response->json('items.0');
        $this->assertSame(3.85, $item['line_total']);
        $this->assertSame(3.85, $item['unit_price']);
        $this->assertEmpty($response->json('shared_items'));
    }

    public function test_item_shared_between_three_pays_one_third(): void
    {
        [$order, $order2, $order3] = $this->createThreeWaySharing();

        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'shared_order_ids' => [$order2->id, $order3->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk();
        $shared = collect($response->json('shared_items'))->firstWhere('cart_item_id', $item->id);
        $this->assertSame(3, $shared['shared_between']);
        $this->assertSame(1.28, $shared['my_share']);
    }

    public function test_quantity_two_shared_between_two_pays_half_line_total(): void
    {
        $other = Customer::factory()->create();
        $otherSession = $this->createSession($other);
        $order = $this->createConfirmedOrder();
        $otherOrder = $this->createConfirmedOrder($other, $otherSession);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 2,
            'shared_order_ids' => [$otherOrder->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk();
        $shared = $response->json('shared_items.0');
        $this->assertSame(7.7, $shared['line_total']);
        $this->assertSame(2, $shared['shared_between']);
        $this->assertSame(3.85, $shared['my_share']);
    }

    public function test_paid_addon_included_in_split_share(): void
    {
        $other = Customer::factory()->create();
        $otherSession = $this->createSession($other);
        $order = $this->createConfirmedOrder();
        $otherOrder = $this->createConfirmedOrder($other, $otherSession);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'paid_addons' => [['name' => 'Cheese sauce', 'price' => 1.50]],
            'shared_order_ids' => [$otherOrder->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk();
        $shared = $response->json('shared_items.0');
        $this->assertSame(5.5, $shared['line_total']);
        $this->assertSame(2.75, $shared['my_share']);
    }

    public function test_modifier_price_adjustment_included_in_split_share(): void
    {
        $other = Customer::factory()->create();
        $otherSession = $this->createSession($other);
        $order = $this->createConfirmedOrder();
        $otherOrder = $this->createConfirmedOrder($other, $otherSession);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'selected_modifiers' => [[
                'group_id' => 1,
                'group_name' => 'Size',
                'tax_category' => 'food',
                'options' => [['id' => 1, 'name' => 'Large', 'price_adjustment' => 1.50]],
            ]],
            'shared_order_ids' => [$otherOrder->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk();
        $shared = $response->json('shared_items.0');
        $this->assertSame(5.5, $shared['line_total']);
        $this->assertSame(2.75, $shared['my_share']);
    }

    public function test_three_way_split_rounds_correctly(): void
    {
        [$order, $order2, $order3] = $this->createThreeWaySharing();

        $tenEuroItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $this->menuItem->menu_category_id,
            'name' => 'Steak',
            'price' => 10.00,
            'tax_category' => 'food',
        ]);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $tenEuroItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'shared_order_ids' => [$order2->id, $order3->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk();
        $shared = $response->json('shared_items.0');
        $lineTotal = $shared['line_total'];
        $myShare = $shared['my_share'];
        $this->assertSame(3, $shared['shared_between']);
        $this->assertSame(round($lineTotal / 3, 2), $myShare);
    }

    public function test_zero_price_item_shared_returns_zero_share(): void
    {
        $other = Customer::factory()->create();
        $otherSession = $this->createSession($other);
        $order = $this->createConfirmedOrder();
        $otherOrder = $this->createConfirmedOrder($other, $otherSession);

        $freeItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $this->menuItem->menu_category_id,
            'name' => 'Free Bread',
            'price' => 0,
            'tax_category' => 'food',
        ]);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $freeItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'shared_order_ids' => [$otherOrder->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk();
        $shared = $response->json('shared_items.0');
        $this->assertEquals(0, $shared['line_total']);
        $this->assertEquals(0, $shared['my_share']);
    }

    public function test_discounted_item_uses_discounted_price_in_split(): void
    {
        $other = Customer::factory()->create();
        $otherSession = $this->createSession($other);
        $order = $this->createConfirmedOrder();
        $otherOrder = $this->createConfirmedOrder($other, $otherSession);

        $discountedItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $this->menuItem->menu_category_id,
            'name' => 'Sale Burger',
            'price' => 20.00,
            'has_discount' => true,
            'discounted_price' => 15.00,
            'tax_category' => 'food',
        ]);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $discountedItem->id,
            'order_id' => $order->id,
            'quantity' => 1,
            'shared_order_ids' => [$otherOrder->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk();
        $shared = $response->json('shared_items.0');
        $this->assertSame(16.5, $shared['line_total']);
        $this->assertSame(8.25, $shared['my_share']);
    }

    // ----------------------------------------------------------------
    // Helpers for split-payment tests
    // ----------------------------------------------------------------

    private function createSession(Customer $customer): TableScanSession
    {
        return TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $customer->id,
            'pin' => '',
            'status' => 'active',
            'scanned_at' => now(),
        ]);
    }

    private function createConfirmedOrder(?Customer $customer = null, ?TableScanSession $session = null): Order
    {
        $customer ??= $this->customer;
        $session ??= $this->session;

        return Order::create([
            'order_public_id' => 'ord-split-' . uniqid(),
            'customer_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => 'confirmed',
            'amount' => 0,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => true,
            'payment_received' => false,
        ]);
    }

    private function createThreeWaySharing(): array
    {
        $customer2 = Customer::factory()->create();
        $customer3 = Customer::factory()->create();
        $session2 = $this->createSession($customer2);
        $session3 = $this->createSession($customer3);
        $order = $this->createConfirmedOrder();
        $order2 = $this->createConfirmedOrder($customer2, $session2);
        $order3 = $this->createConfirmedOrder($customer3, $session3);

        return [$order, $order2, $order3];
    }
}
