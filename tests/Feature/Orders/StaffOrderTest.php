<?php

namespace Tests\Feature\Orders;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffOrderTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;
    private RestaurantTable $table;
    private MenuItem $menuItem;
    private ModifierGroup $modifierGroup;
    private int $modifierOptionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = Vendor::factory()->create(['country' => 'Austria']);
        $this->vendor->vendorSetting()->create([
            'service_fee_rate' => 10,
            'is_live_and_discoverable' => true,
        ]);

        $this->table = $this->makeTable(7);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
        ]);

        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Pasta',
            'price' => 10.00,
            'vat_rate' => 10,
            'is_active' => true,
            'available' => true,
        ]);

        $this->modifierGroup = ModifierGroup::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Size',
            'type' => 'single',
            'min_selection' => 1,
            'max_selection' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);
        $option = $this->modifierGroup->options()->create([
            'name' => 'Large',
            'price_adjustment' => 2.00,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $this->modifierOptionId = $option->id;
        $this->menuItem->modifierGroups()->attach($this->modifierGroup->id, ['sort_order' => 1]);
    }

    private function makeTable(int $number): RestaurantTable
    {
        return $this->vendor->restaurantTables()->create([
            'number' => $number,
            'name' => "Table {$number}",
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
    }

    private function vendorHeaders(): array
    {
        $token = $this->vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function staffHeaders(string $role = 'waiter'): array
    {
        $member = $this->makeTeamMember($role);
        $token = $member->createToken('test', ['role:team_member', "role:{$role}"])->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function makeTeamMember(string $role = 'waiter'): TeamMember
    {
        return TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name' => ucfirst($role),
            'email' => $role . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'permissions' => TeamMember::defaultPermissions($role),
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    private function customerHeaders(Customer $customer): array
    {
        $token = $customer->createToken('test', ['role:customer'])->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function staffOrderItems(int $quantity = 2): array
    {
        return [
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => $quantity,
                    'selected_modifiers' => [
                        ['modifier_group_id' => $this->modifierGroup->id, 'option_ids' => [$this->modifierOptionId]],
                    ],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Session creation
    // ------------------------------------------------------------------

    public function test_waiter_creates_session_on_empty_table_and_it_is_idempotent(): void
    {
        $headers = $this->staffHeaders('waiter');
        $url = "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/session";

        $first = $this->withHeaders($headers)->postJson($url);

        $first->assertStatus(201)->assertJson(['created' => true]);
        $pin = $first->json('pin');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);

        $session = TableScanSession::findOrFail((int) $first->json('session_id'));
        $this->assertNull($session->customer_id);
        $this->assertSame('active', $session->status);

        $second = $this->withHeaders($headers)->postJson($url);
        $second->assertStatus(200)->assertJson(['created' => false, 'pin' => $pin]);
        $this->assertSame(1, TableScanSession::where('restaurant_table_id', $this->table->id)->count());
    }

    public function test_session_creation_rejects_inactive_table(): void
    {
        $this->table->update(['is_active' => false]);

        $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson("/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/session")
            ->assertStatus(422)
            ->assertJson(['code' => 'inactive_table']);
    }

    public function test_customer_can_join_waiter_created_session_via_pin(): void
    {
        $created = $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson("/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/session");
        $created->assertStatus(201);

        $customer = Customer::factory()->create();

        $this->withHeaders($this->customerHeaders($customer))
            ->postJson('/api/customer/table/pin', [
                'token' => $this->table->qr_token,
                'pin' => $created->json('pin'),
            ])
            ->assertSuccessful();

        $this->assertTrue(
            TableScanSession::where('restaurant_table_id', $this->table->id)
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->exists()
        );
    }

    // ------------------------------------------------------------------
    // Staff order placement
    // ------------------------------------------------------------------

    public function test_waiter_staff_order_creates_cart_items_and_order_with_service_fee(): void
    {
        $response = $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $this->staffOrderItems()
            );

        $response->assertStatus(201)
            ->assertJsonPath('order.id', fn ($id) => is_string($id) && $id !== '')
            ->assertJsonPath('order.tableId', (string) $this->table->id)
            ->assertJsonPath('order.displayStatus', 'received')
            ->assertJsonPath('order.items.0.name', $this->menuItem->name)
            ->assertJsonPath('order.items.0.status', 'received');

        $order = Order::where('order_public_id', $response->json('order_id'))->firstOrFail();

        // Base 10.00 net @10% VAT = 11.00 gross; modifier +2.00 @10% VAT (seeded
        // AT food rate) = 2.20; unit gross 13.20; qty 2 = 26.40; 10% service fee = 2.64.
        $this->assertSame(26.40 + 2.64, (float) $order->amount);
        $this->assertSame(2.64, (float) $order->service_fee);
        $this->assertSame('waiter', $order->placed_by);
        $this->assertNotNull($order->placed_by_team_member_id);
        $this->assertSame('confirmed', $order->status);

        // No predefined payment method — the order stays payable by a customer
        // (pay-for/Stripe) or collectable by the waiter.
        $this->assertNull($order->payment_method);
        $this->assertFalse((bool) $order->payment_pending);

        $cartItem = CartItem::where('order_id', $order->id)->firstOrFail();
        $this->assertNotNull($cartItem->received_at);
        $this->assertSame(2, $cartItem->quantity);
        $this->assertSame(2.00, (float) $cartItem->selected_modifiers[0]['options'][0]['price_adjustment']);
    }

    public function test_staff_orders_merge_into_single_order_until_paid(): void
    {
        $headers = $this->staffHeaders('waiter');
        $url = "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order";

        $first = $this->withHeaders($headers)->postJson($url, $this->staffOrderItems(2));
        $first->assertStatus(201);
        $orderId = $first->json('order_id');

        $second = $this->withHeaders($headers)->postJson($url, $this->staffOrderItems(1));
        $second->assertStatus(201);
        $this->assertSame($orderId, $second->json('order_id'));

        $this->assertSame(1, Order::count());
        $order = Order::firstOrFail();
        // 3 units total: 3 × 13.20 gross = 39.60; 10% service fee = 3.96.
        $this->assertSame(43.56, (float) $order->amount);
        $this->assertSame(3.96, (float) $order->service_fee);
        $this->assertSame(2, CartItem::where('order_id', $order->id)->count());

        // Once the order is paid, the next staff order starts a fresh one.
        $order->update(['payment_received' => true, 'payment_pending' => false]);

        $third = $this->withHeaders($headers)->postJson($url, $this->staffOrderItems(1));
        $third->assertStatus(201);
        $this->assertNotSame($orderId, $third->json('order_id'));
        $this->assertSame(2, Order::count());
    }

    public function test_staff_order_accepts_numeric_vendor_id(): void
    {
        $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson(
                "/api/vendor/{$this->vendor->id}/tables/{$this->table->id}/staff-order",
                $this->staffOrderItems()
            )
            ->assertStatus(201);
    }

    public function test_staff_order_rejects_other_vendors(): void
    {
        $otherVendor = Vendor::factory()->create(['country' => 'Austria']);

        $token = $otherVendor->createToken('test')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $this->staffOrderItems()
            )
            ->assertStatus(403);
    }

    public function test_owner_staff_order_has_no_team_member_attribution(): void
    {
        $response = $this->withHeaders($this->vendorHeaders())
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $this->staffOrderItems()
            );

        $response->assertStatus(201);

        $order = Order::where('order_public_id', $response->json('order_id'))->firstOrFail();
        $this->assertSame('waiter', $order->placed_by);
        $this->assertNull($order->placed_by_team_member_id);
    }

    public function test_staff_order_amount_matches_customer_confirmed_order(): void
    {
        // Staff order on table 7.
        $staffResponse = $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $this->staffOrderItems()
            );
        $staffResponse->assertStatus(201);
        $staffOrder = Order::where('order_public_id', $staffResponse->json('order_id'))->firstOrFail();

        // Same items ordered by a customer on another table.
        $customer = Customer::factory()->create();
        $customerTable = $this->makeTable(8);
        TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $customerTable->id,
            'customer_id' => $customer->id,
            'pin' => TableScanSession::generateUniquePin(),
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $headers = $this->customerHeaders($customer);
        $this->withHeaders($headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'selected_modifiers' => [
                    ['modifier_group_id' => $this->modifierGroup->id, 'option_ids' => [$this->modifierOptionId]],
                ],
            ])
            ->assertSuccessful();

        $this->withHeaders($headers)
            ->postJson('/api/customer/table/order/confirmed')
            ->assertSuccessful();

        $customerOrder = Order::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame((float) $customerOrder->amount, (float) $staffOrder->amount);
        $this->assertSame((float) $customerOrder->service_fee, (float) $staffOrder->service_fee);
    }

    public function test_orders_index_reports_shared_between_on_items(): void
    {
        $customer = Customer::factory()->create();
        $session = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $customer->id,
            'pin' => TableScanSession::generateUniquePin(),
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $owner = Order::create([
            'order_public_id' => 'ord-' . uniqid(),
            'customer_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'amount' => 9.41,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
        ]);
        $sharer = Order::create([
            'order_public_id' => 'ord-' . uniqid(),
            'customer_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'amount' => 9.41,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
        ]);

        CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $owner->id,
            'quantity' => 2,
            'shared_order_ids' => [$sharer->id],
            'selected_modifiers' => [],
            'received_at' => now(),
        ]);

        $index = $this->withHeaders($this->vendorHeaders())
            ->getJson("/api/vendor/{$this->vendor->vendor_public_id}/orders");
        $index->assertSuccessful();

        $orders = collect($index->json('sessions.0.orders'));
        $ownerItem = collect($orders->firstWhere('id', (string) $owner->id)['items'] ?? [])
            ->first(fn ($item) => ! ($item['isSharedCopy'] ?? false));

        $this->assertNotNull($ownerItem);
        $this->assertSame(2, $ownerItem['sharedBetween']);
    }

    public function test_waiter_order_is_a_separate_person_not_merged_into_customer(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
        $customerSession = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        // Waiter places an order on the table the customer already occupies.
        $response = $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $this->staffOrderItems()
            );
        $response->assertStatus(201);

        // The waiter gets his own session row (customer_id null) sharing the table PIN.
        $waiterSessionId = (int) $response->json('session_id');
        $this->assertNotSame($customerSession->id, $waiterSessionId);
        $waiterSession = TableScanSession::findOrFail($waiterSessionId);
        $this->assertNull($waiterSession->customer_id);
        $this->assertSame('1234', $waiterSession->pin);

        // The waiter order is not attached to the customer's session.
        $order = Order::where('order_public_id', $response->json('order_id'))->firstOrFail();
        $this->assertSame($waiterSessionId, (int) $order->table_scan_session_id);

        // Customer table history: waiter appears as a separate person named "Waiter".
        $history = $this->withHeaders($this->customerHeaders($customer))
            ->getJson('/api/customer/table/history');
        $history->assertSuccessful();

        $people = collect($history->json('people'));
        $me = $people->firstWhere('session_id', $customerSession->id);
        $waiter = $people->firstWhere('session_id', $waiterSessionId);

        $this->assertNotNull($me);
        $this->assertNotNull($waiter);
        $this->assertSame(0, count($me['orders'] ?? []));
        $this->assertSame('Waiter', $waiter['name']);
        $this->assertNull($waiter['customer_id']);
        $this->assertSame(1, count($waiter['orders'] ?? []));

        // A second waiter order still merges into the unpaid waiter order.
        $second = $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $this->staffOrderItems(1)
            );
        $second->assertStatus(201);
        $this->assertSame($response->json('order_id'), $second->json('order_id'));
        $this->assertSame($waiterSessionId, (int) $second->json('session_id'));
    }

    public function test_waiter_can_collect_order_without_cash_request(): void
    {
        $customer = Customer::factory()->create();
        $session = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $customer->id,
            'pin' => TableScanSession::generateUniquePin(),
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-' . uniqid(),
            'customer_id' => $customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'amount' => 30.91,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_method' => null,
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $this->withHeaders($this->staffHeaders('waiter'))
            ->patchJson("/api/vendor/orders/{$order->id}/confirm-cash")
            ->assertSuccessful();

        $order->refresh();
        $this->assertTrue((bool) $order->payment_received);
        $this->assertFalse((bool) $order->payment_pending);
        $this->assertSame('cash', $order->payment_method);
    }

    public function test_orders_index_flags_waiter_orders(): void
    {
        $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $this->staffOrderItems()
            )
            ->assertStatus(201);

        $index = $this->withHeaders($this->vendorHeaders())
            ->getJson("/api/vendor/{$this->vendor->vendor_public_id}/orders");

        $index->assertSuccessful();
        $this->assertSame('waiter', $index->json('sessions.0.orders.0.placedBy'));
    }

    // ------------------------------------------------------------------
    // Modifier validation (strict, customer parity)
    // ------------------------------------------------------------------

    public function test_staff_order_rejects_missing_required_modifier_group(): void
    {
        $payload = $this->staffOrderItems();
        unset($payload['items'][0]['selected_modifiers']);

        $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $payload
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('selected_modifiers');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, CartItem::count());
    }

    public function test_staff_order_rejects_too_many_options_for_single_group(): void
    {
        $secondOption = $this->modifierGroup->options()->create([
            'name' => 'Extra Large',
            'price_adjustment' => 3.00,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $payload = $this->staffOrderItems();
        $payload['items'][0]['selected_modifiers'][0]['option_ids'] = [$this->modifierOptionId, $secondOption->id];

        $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $payload
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('selected_modifiers');
    }

    public function test_staff_order_rejects_unknown_modifier_option(): void
    {
        $payload = $this->staffOrderItems();
        $payload['items'][0]['selected_modifiers'][0]['option_ids'] = [999999];

        $this->withHeaders($this->staffHeaders('waiter'))
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $payload
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('selected_modifiers');
    }

    // ------------------------------------------------------------------
    // Role access
    // ------------------------------------------------------------------

    public function test_waiter_can_browse_menu_items(): void
    {
        $response = $this->withHeaders($this->staffHeaders('waiter'))
            ->getJson('/api/vendor/menu/items?available=1');

        $response->assertSuccessful();
        $this->assertNotEmpty($response->json('data'));
        $this->assertSame(10.0, (float) $response->json('serviceFeeRate'));
    }

    public function test_vendor_menu_gross_prices_match_customer_menu(): void
    {
        $customer = $this->getJson(
            "/api/customer/restaurants/{$this->vendor->vendor_public_id}/menu/{$this->menuItem->id}"
        );
        $customer->assertSuccessful();

        $vendor = $this->withHeaders($this->staffHeaders('waiter'))->getJson('/api/vendor/menu/items');
        $vendor->assertSuccessful();

        $vendorItem = collect($vendor->json('data'))->firstWhere('id', $this->menuItem->id);
        $this->assertNotNull($vendorItem);

        $this->assertSame($customer->json('price'), $vendorItem['grossPrice']);

        $customerOption = collect($customer->json('modifier_groups.0.options'))
            ->firstWhere('id', $this->modifierOptionId);
        $vendorOption = collect($vendorItem['modifierGroups'][0]['options'])
            ->firstWhere('id', $this->modifierOptionId);

        $this->assertSame($customerOption['price_adjustment'], $vendorOption['grossPriceAdjustment']);
    }

    public function test_kitchen_role_cannot_place_staff_order_or_create_session(): void
    {
        $headers = $this->staffHeaders('kitchen');

        $this->withHeaders($headers)
            ->postJson(
                "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/staff-order",
                $this->staffOrderItems()
            )
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->postJson("/api/vendor/{$this->vendor->vendor_public_id}/tables/{$this->table->id}/session")
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->getJson('/api/vendor/menu/items')
            ->assertStatus(403);
    }
}
