<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
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
        $this->vendor   = Vendor::factory()->create();

        $token = $this->customer->createToken('test', ['role:customer'])->plainTextToken;
        $this->headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $this->table = $this->vendor->restaurantTables()->create([
            'number'        => 1,
            'name'          => 'T1',
            'qr_token'      => RestaurantTable::generateQrToken(),
            'is_active'     => true,
            'qr_created_at' => now(),
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name'      => 'Sides',
            'slug'      => 'sides-' . $this->vendor->id,
        ]);

        $this->menuItem = MenuItem::create([
            'vendor_id'        => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name'             => 'Fries',
            'price'            => 3.50,
            'vat_rate'         => 20,
            'paid_addons'      => [
                ['name' => 'Cheese sauce', 'price' => 1.50],
                ['name' => 'Truffle mayo', 'price' => 2.00],
            ],
            'free_addons'      => ['Ketchup', 'Chili flakes'],
            'removable_items'  => ['Salt'],
        ]);

        $this->session = TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $this->customer->id,
            'pin'                 => '1234',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);
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
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $other->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
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
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 2,
            'notes'                 => 'No salt',
        ]);

        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $otherSession = TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $other->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
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
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $closed->id,
            'pin'                 => '',
            'status'              => 'closed',
            'scanned_at'          => now(),
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
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);
        $item->forceFill(['created_at' => now()->subMinute()])->save();

        $draft = Order::create([
            'order_public_id'       => 'ord-draft-cart-visible',
            'customer_id'           => $this->customer->id,
            'vendor_id'             => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status'                => 'draft',
            'amount'                => 3.50,
            'currency'              => 'EUR',
        ]);

        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $otherSession = TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $other->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 2,
            'shared_order_ids'             => [$draft->id],
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
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);
        $item->forceFill(['created_at' => now()->subMinute()])->save();

        $order = Order::create([
            'order_public_id'       => 'ord-confirmed-cart-hidden',
            'customer_id'           => $this->customer->id,
            'vendor_id'             => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status'                => 'confirmed',
            'amount'                => 3.50,
            'currency'              => 'EUR',
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
                'quantity'     => 2,
                'notes'        => 'No salt',
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
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 2,
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
            'slug' => 'other-sides-' . $otherVendor->id,
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
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
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
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $other->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
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
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
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
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $other->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);

        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/cart/items/{$item->id}")
            ->assertNotFound();
    }

    public function test_cart_response_contains_personal_items_with_customer_id(): void
    {
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
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

    public function test_table_history_items_include_status_from_preparation_timestamps(): void
    {
        $new = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);
        $preparing = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
            'preparing_start_at'    => now(),
        ]);
        $ready = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
            'preparing_start_at'    => now()->subMinutes(5),
            'ready_at'              => now(),
        ]);
        $served = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
            'preparing_start_at'    => now()->subMinutes(10),
            'ready_at'              => now()->subMinutes(5),
            'served_at'             => now(),
        ]);

        $order = Order::create([
            'order_public_id'       => 'ord-status-test',
            'customer_id'           => $this->customer->id,
            'vendor_id'             => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status'                => 'confirmed',
            'amount'                => 10,
            'currency'              => 'EUR',
            'order_type'            => 'dine-in',
        ]);
        CartItem::whereIn('id', [$new->id, $preparing->id, $ready->id, $served->id])
            ->update(['order_id' => $order->id]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/table/history');

        $response->assertOk();

        $items = collect($response->json('people.0.orders.0.items'))->keyBy('cart_item_id');

        $this->assertNull($items[$new->id]['status']);
        $this->assertSame('Preparing', $items[$preparing->id]['status']);
        $this->assertSame('Ready', $items[$ready->id]['status']);
        $this->assertSame('Served', $items[$served->id]['status']);
        $this->assertSame($served->fresh()->served_at->toIso8601String(), $items[$served->id]['served_at']);
    }

    public function test_table_history_returns_all_orders_for_active_table_session(): void
    {
        $firstItem = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 1,
        ]);
        $secondItem = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 2,
        ]);

        $firstOrder = Order::create([
            'order_public_id'       => 'ord-history-first',
            'customer_id'           => $this->customer->id,
            'vendor_id'             => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status'                => 'completed',
            'amount'                => 3.50,
            'currency'              => 'EUR',
            'order_type'            => 'dine-in',
            'created_at'            => now()->subMinute(),
            'updated_at'            => now()->subMinute(),
        ]);
        $secondOrder = Order::create([
            'order_public_id'       => 'ord-history-second',
            'customer_id'           => $this->customer->id,
            'vendor_id'             => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status'                => 'confirmed',
            'amount'                => 7,
            'currency'              => 'EUR',
            'order_type'            => 'dine-in',
            'created_at'            => now(),
            'updated_at'            => now(),
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
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 2,
            'paid_addons'           => [
                ['name' => 'Cheese sauce', 'price' => 1.50],
            ],
            'free_addons'           => ['Ketchup'],
            'removed_items'         => ['Salt'],
            'selected_modifiers'    => [
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
            'order_public_id'       => 'ord-history-customizations',
            'customer_id'           => $this->customer->id,
            'vendor_id'             => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status'                => 'confirmed',
            'amount'                => 10,
            'currency'              => 'EUR',
            'order_type'            => 'dine-in',
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
            'currency' => 'EUR',
            'estimated_prep_time' => 30,
        ]);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'quantity'              => 2,
            'notes'                 => 'No salt',
        ]);

        $order = Order::create([
            'order_public_id'       => 'ord-tracking-default',
            'customer_id'           => $this->customer->id,
            'vendor_id'             => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status'                => 'draft',
            'amount'                => 7,
            'currency'              => 'EUR',
            'order_number'          => 1001,
            'order_type'            => 'dine-in',
            'payment_pending'       => true,
            'payment_received'      => false,
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
            $order->created_at->copy()->addMinutes(30)->toIso8601String(),
            $response->json('estimated_delivery_time')
        );
    }

    public function test_order_tracking_includes_only_actual_shared_items(): void
    {
        $other = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);
        $otherSession = TableScanSession::create([
            'vendor_id'           => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id'         => $other->id,
            'pin'                 => '',
            'status'              => 'active',
            'scanned_at'          => now(),
        ]);

        $pizza = MenuItem::create([
            'vendor_id'        => $this->vendor->id,
            'menu_category_id' => $this->menuItem->menu_category_id,
            'name'             => 'Pizza',
            'price'            => 18.99,
        ]);

        $order = Order::create([
            'order_public_id'       => 'ord-tracking-shared',
            'customer_id'           => $this->customer->id,
            'vendor_id'             => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status'                => 'confirmed',
            'amount'                => 25.99,
            'currency'              => 'EUR',
            'order_type'            => 'dine-in',
        ]);

        $otherOrder = Order::create([
            'order_public_id'       => 'ord-bob-shared',
            'customer_id'           => $other->id,
            'vendor_id'             => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status'                => 'confirmed',
            'amount'                => 9.50,
            'currency'              => 'EUR',
            'order_type'            => 'dine-in',
        ]);

        $ownedShared = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id'          => $this->menuItem->id,
            'order_id'              => $order->id,
            'quantity'              => 2,
            'shared_order_ids'             => [$otherOrder->id],
            'preparing_start_at'    => now(),
        ]);

        $sharedInto = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id'          => $pizza->id,
            'quantity'              => 1,
            'shared_order_ids'             => [$order->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking");

        $response->assertOk()
            ->assertJsonPath('items.0.cart_item_id', $ownedShared->id)
            ->assertJsonPath('items.0.status', 'Preparing');

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
}
