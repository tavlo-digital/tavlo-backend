<?php

namespace Tests\Feature\Customer;

use App\Http\Controllers\Api\Customer\CartController;
use App\Jobs\ProcessCustomerCommand;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Services\CustomerCommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
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

    public function test_cart_payload_eager_loads_name_translations_without_n_plus_one(): void
    {
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains-'.$this->vendor->id,
        ]);

        // Several distinct items in the cart. Each historically triggered its own
        // itemTranslations lookup while the cart payload was built (N+1).
        foreach (range(1, 4) as $i) {
            $menuItem = MenuItem::create([
                'vendor_id' => $this->vendor->id,
                'menu_category_id' => $category->id,
                'name' => "Dish {$i}",
                'price' => 5 + $i,
                'vat_rate' => 20,
            ]);
            MenuItemTranslation::create([
                'menu_item_id' => $menuItem->id,
                'language' => 'de',
                'name' => "Gericht {$i}",
            ]);
            CartItem::create([
                'table_scan_session_id' => $this->session->id,
                'menu_item_id' => $menuItem->id,
                'quantity' => 1,
            ]);
        }

        $translationQueries = 0;
        DB::listen(function ($query) use (&$translationQueries) {
            if (str_contains($query->sql, 'menu_item_translations')) {
                $translationQueries++;
            }
        });

        $this->getJson('/api/customer/cart', $this->headers)->assertOk();

        // A single eager-load query for all items, not one per cart item.
        $this->assertLessThanOrEqual(
            1,
            $translationQueries,
            "Expected item name translations to be eager-loaded in one query, got {$translationQueries}."
        );
    }

    public function test_cart_add_can_be_accepted_as_an_ordered_redis_command(): void
    {
        $commands = Mockery::mock(CustomerCommandBus::class);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldReceive('workerAlive')->once()->andReturnTrue();
        $commands->shouldReceive('dispatch')->once()->andReturn([
            'command_id' => '0190f26e-7c87-7def-8e46-111111111111',
            'sequence' => 1,
            'operation' => 'cart.add',
            'status' => 'accepted',
        ]);
        $this->app->instance(CustomerCommandBus::class, $commands);

        $this->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ], $this->headers)
            ->assertStatus(202)
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('sequence', 1);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_add_falls_back_to_sync_when_no_worker_is_alive(): void
    {
        // Async enabled, but no worker draining the queue: the command must NOT
        // be enqueued (where it would be silently lost). Instead the item is
        // written synchronously and the normal 201 cart response is returned.
        $commands = Mockery::mock(CustomerCommandBus::class);
        $commands->shouldReceive('enabled')->andReturnTrue();
        $commands->shouldReceive('workerAlive')->andReturnFalse();
        $commands->shouldReceive('dispatch')->never();
        $this->app->instance(CustomerCommandBus::class, $commands);

        $this->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ], $this->headers)->assertCreated();

        $this->assertDatabaseHas('cart_items', [
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ]);
    }

    public function test_customer_get_cache_hits_and_mutations_invalidate_it(): void
    {
        config()->set('services.customer_api_cache.enabled', true);
        config()->set('services.customer_api_cache.store', 'array');
        config()->set('services.customer_api_cache.ttl', 120);
        Cache::store('array')->clear();

        $this->getJson('/api/customer/cart', $this->headers)
            ->assertOk()
            ->assertHeader('X-Tavlo-Cache', 'MISS');
        $this->getJson('/api/customer/cart', $this->headers)
            ->assertOk()
            ->assertHeader('X-Tavlo-Cache', 'HIT');

        $this->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
        ], $this->headers)->assertCreated();

        $this->getJson('/api/customer/cart', $this->headers)
            ->assertOk()
            ->assertHeader('X-Tavlo-Cache', 'MISS')
            ->assertJsonCount(1, 'people.0.personal_items');
    }

    public function test_ordered_cart_command_is_committed_by_the_worker(): void
    {
        $commandId = '0190f26e-7c87-7def-8e46-222222222222';
        $commands = Mockery::mock(CustomerCommandBus::class);
        $commands->shouldReceive('expectedSequence')->once()->with($this->session->id)->andReturn(1);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldReceive('finish')->once()->withArgs(
            fn (string $id, int $sessionId, int $sequence, string $status) => $id === $commandId
                && $sessionId === $this->session->id
                && $sequence === 1
                && $status === 'completed'
        );
        $this->app->instance(CustomerCommandBus::class, $commands);

        $job = new ProcessCustomerCommand(
            $commandId,
            1,
            $this->customer->id,
            $this->session->id,
            'cart.add',
            ['menu_item_id' => $this->menuItem->id, 'quantity' => 2],
            'en',
        );
        $job->handle($commands, $this->app->make(CartController::class));

        $this->assertDatabaseHas('customer_commands', [
            'command_id' => $commandId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('cart_items', [
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
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

    public function test_get_cart_keeps_items_another_guest_shared_into_a_submitted_order(): void
    {
        $draft = Order::create([
            'order_public_id' => 'ord-draft-owner-share',
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

        // The side order ShareOrderService opens for a guest whose own order is
        // covered by somebody else — created as `confirmed`, never a draft.
        $sideOrder = Order::create([
            'order_public_id' => 'ord-side-share',
            'customer_id' => $other->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status' => 'confirmed',
            'amount' => 0,
            'currency' => 'EUR',
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'order_id' => $draft->id,
            'shared_order_ids' => [$sideOrder->id],
        ]);
        $item->forceFill(['created_at' => now()->subMinute()])->save();

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk();

        $people = collect($response->json('people'))->keyBy('session_id');
        $this->assertCount(1, $people[$this->session->id]['personal_items']);
        $this->assertSame($item->id, $people[$this->session->id]['personal_items'][0]['id']);
    }

    public function test_get_cart_reports_each_shared_line_as_the_owner_share(): void
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

        $theirOrder = Order::create([
            'order_public_id' => 'ord-share-halves',
            'customer_id' => $other->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status' => 'draft',
            'amount' => 0,
            'currency' => 'EUR',
        ]);

        $item = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
            'shared_order_ids' => [$theirOrder->id],
        ]);
        $item->forceFill(['created_at' => now()->subMinute()])->save();

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/cart');

        $response->assertOk();

        $mine = collect($response->json('people'))
            ->firstWhere('session_id', $this->session->id);
        $line = $mine['personal_items'][0];

        // The line is 2 x 3.85; split with Bob it costs this customer half.
        $this->assertSame(7.7, $line['line_total']);
        $this->assertSame(2, $line['shared_between']);
        $this->assertSame(3.85, $line['my_share']);

        // The row has to agree with the total the cart bills, which already
        // divides a shared line by its sharers.
        $this->assertSame($line['my_share'], $mine['totals']['grand_total']);
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

    public function test_replaying_an_add_with_the_same_client_item_id_does_not_stack_quantity(): void
    {
        // Assert against the write itself. The queue worker re-invokes this same
        // action, so the guard is identical either way, but queuing would answer
        // 202 and move the assertions off the thing under test.
        config()->set('services.customer_commands.enabled', false);

        $clientItemId = (string) Str::uuid();

        $first = $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'client_item_id' => $clientItemId,
            ])->assertCreated();

        // The same write arriving twice — a retry, a replayed queue job, or a
        // double tap — must land on the line that already exists.
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 2,
                'client_item_id' => $clientItemId,
            ])->assertSuccessful()
            ->assertJsonPath('id', $first->json('id'))
            ->assertJsonPath('quantity', 2);

        $this->assertSame(
            1,
            CartItem::where('table_scan_session_id', $this->session->id)->count(),
        );
        $this->assertSame(
            2,
            (int) CartItem::where('client_item_id', $clientItemId)->sole()->quantity,
        );
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
            ->assertJsonPath('menu_item.vat_rate', 10)
            ->assertJsonPath('cart.people.0.personal_items.0.quantity', 2)
            ->assertJsonPath('cart.people.0.is_me', true);

        $this->assertDatabaseHas('cart_items', [
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ]);
    }

    public function test_add_item_twice_merges_into_one_line_and_sums_quantity(): void
    {
        // Adding the same item (same customizations) again must increment the
        // existing cart line rather than create a second row. This guards the
        // find-or-increment path that is wrapped in a locked transaction to
        // stay race-safe under concurrent add-to-cart requests.
        foreach ([2, 3] as $qty) {
            $this->withHeaders($this->headers)
                ->postJson('/api/customer/cart/items', [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => $qty,
                ])->assertCreated();
        }

        $this->assertSame(
            1,
            CartItem::where('table_scan_session_id', $this->session->id)
                ->where('menu_item_id', $this->menuItem->id)
                ->whereNull('order_id')
                ->count(),
        );

        $this->assertDatabaseHas('cart_items', [
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 5,
        ]);
    }

    public function test_add_item_increments_the_existing_line_when_it_is_shared_but_not_in_checkout(): void
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

        $theirOrder = Order::create([
            'order_public_id' => 'ord-share-split',
            'customer_id' => $other->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status' => 'draft',
            'amount' => 0,
            'currency' => 'EUR',
        ]);

        // Step-1 sharing is live: both participants see their share change as
        // the owner edits the row, until either related order reaches checkout.
        $shared = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'shared_order_ids' => [$theirOrder->id],
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ])->assertCreated();

        $lines = CartItem::where('table_scan_session_id', $this->session->id)
            ->where('menu_item_id', $this->menuItem->id)
            ->get();

        $this->assertCount(1, $lines);
        $this->assertSame(2, (int) $shared->fresh()->quantity);
        $this->assertSame([$theirOrder->id], array_map('intval', $shared->fresh()->shared_order_ids));
    }

    public function test_add_item_opens_a_new_line_when_the_existing_one_is_bound_to_a_locked_order(): void
    {
        $covered = Order::create([
            'order_public_id' => 'ord-covered-bind',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'draft',
            'amount' => 3.85,
            'currency' => 'EUR',
            'paid_by' => Customer::factory()->create()->id,
            'payment_pending' => true,
        ]);

        $bound = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'order_id' => $covered->id,
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ])->assertCreated();

        $lines = CartItem::where('table_scan_session_id', $this->session->id)
            ->where('menu_item_id', $this->menuItem->id)
            ->get();

        $this->assertCount(2, $lines);
        $this->assertSame(1, (int) $bound->fresh()->quantity);
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
            ->assertJsonPath('notes', 'Extra crispy')
            ->assertJsonPath('cart.people.0.personal_items.0.quantity', 3);
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
            ->assertOk()
            ->assertJsonPath('cart.people.0.personal_items', []);

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

        $draft = Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertFalse((bool) $draft->payment_pending);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/confirmed')
            ->assertOk();

        $order = Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertFalse((bool) $order->payment_pending);
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

    public function test_draft_order_accepts_updates_and_clears_optional_note(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertCreated();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/draft', ['note' => 'Please pack sauces separately.'])
            ->assertCreated()
            ->assertJsonPath('people.0.orders.0.note', 'Please pack sauces separately.');

        $order = Order::where('customer_id', $this->customer->id)->sole();
        $this->assertSame('Please pack sauces separately.', $order->note);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/draft', ['note' => null])
            ->assertOk()
            ->assertJsonPath('people.0.orders.0.note', null);

        $this->assertNull($order->fresh()->note);
    }

    public function test_confirm_order_accepts_and_clears_optional_note(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertCreated();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/confirmed', ['note' => 'Allergy: no peanuts.'])
            ->assertOk()
            ->assertJsonPath('people.0.orders.0.note', 'Allergy: no peanuts.');

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'status' => Order::STATUS_CONFIRMED,
            'note' => 'Allergy: no peanuts.',
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertCreated();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/confirmed', ['note' => null])
            ->assertOk()
            ->assertJsonPath('people.0.orders.0.note', null);

        $this->assertNull(
            Order::where('customer_id', $this->customer->id)->sole()->note
        );
    }

    public function test_confirm_order_preserves_draft_note_when_note_is_omitted(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/cart/items', ['menu_item_id' => $this->menuItem->id])
            ->assertCreated();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/draft', ['note' => 'Ring the bell at collection.'])
            ->assertCreated();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/confirmed')
            ->assertOk()
            ->assertJsonPath('people.0.orders.0.note', 'Ring the bell at collection.');

        $this->assertSame(
            'Ring the bell at collection.',
            Order::where('customer_id', $this->customer->id)->sole()->note
        );
    }

    public function test_order_note_is_limited_to_500_characters(): void
    {
        $note = str_repeat('x', 501);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/draft', ['note' => $note])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/confirmed', ['note' => $note])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('note');
    }

    public function test_confirm_order_keeps_open_items_separate_from_existing_checkout_locked_orders(): void
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
            'note' => 'Keep this draft note.',
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
            ->assertJsonPath('people.0.orders_count', 3)
            ->assertJsonPath('people.0.orders.0.id', $existingOrder->id)
            ->assertJsonPath('people.0.orders.0.status', Order::STATUS_IN_PROGRESS);

        $newOrder = Order::where('customer_id', $this->customer->id)->latest('id')->firstOrFail();
        $this->assertDatabaseHas('orders', ['id' => $draftOrder->id]);
        $this->assertSame($newOrder->id, $newItem->fresh()->order_id);
        $this->assertSame([$draftOrder->id], array_map('intval', $sharedItem->fresh()->shared_order_ids));
        $this->assertSame(3.50, (float) $existingOrder->fresh()->amount);
        $this->assertNull($existingOrder->fresh()->note);
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
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Your cart is empty. Add items before confirming your order.');

        $this->assertSame('draft', $order->fresh()->status);
    }

    public function test_history_prices_a_draft_from_the_items_it_lists(): void
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

        // Both drafts carry a stale amount, as any cart edit since the last
        // re-price would leave behind.
        $myOrder = Order::create([
            'order_public_id' => 'ord-live-mine',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_DRAFT,
            'amount' => 0.5,
            'service_fee' => 0,
            'currency' => 'EUR',
        ]);

        $theirOrder = Order::create([
            'order_public_id' => 'ord-live-theirs',
            'customer_id' => $other->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status' => Order::STATUS_DRAFT,
            'amount' => 99.0,
            'service_fee' => 0,
            'currency' => 'EUR',
        ]);

        // Mine: two unassigned lines of my own, 2 x 3.85 each.
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ]);

        // Bob's line, split with me: half of 3.85 lands on each order.
        CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'shared_order_ids' => [$myOrder->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/table/history');

        $response->assertOk();

        $orders = collect($response->json('people'))
            ->flatMap(fn (array $person) => $person['orders'])
            ->keyBy('id');

        // 7.70 of my own plus my 1.93 half of Bob's line, and three lines listed.
        $this->assertSame(9.63, $orders[$myOrder->id]['amount']);
        $this->assertCount(2, $orders[$myOrder->id]['items']);

        // Bob keeps his own line at his half of it, not its full 3.85.
        $this->assertSame(1.93, $orders[$theirOrder->id]['amount']);
        $this->assertCount(1, $orders[$theirOrder->id]['items']);
    }

    public function test_order_draft_is_not_created_when_there_is_nothing_left_to_draft(): void
    {
        $payer = Customer::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);

        // My order is covered by Bob, which closes it to new items and binds
        // the ones it had.
        $covered = Order::create([
            'order_public_id' => 'ord-covered-draft',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_DRAFT,
            'amount' => 3.85,
            'currency' => 'EUR',
            'paid_by' => $payer->id,
        ]);

        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'order_id' => $covered->id,
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/table/order/draft')
            ->assertOk();

        // A second, empty draft would show up on the payment step as another
        // order card under the same name with no items and no amount.
        $this->assertSame(
            1,
            Order::where('table_scan_session_id', $this->session->id)->count(),
        );
    }

    public function test_update_order_keeps_my_own_unbound_items_in_the_order_amount(): void
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
            'order_public_id' => 'ord-share-keeps-own',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_DRAFT,
            'amount' => 7.7,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
        ]);

        // My own two lines: added to the cart, so still unassigned — a draft is
        // not confirmed, and nothing has locked it into binding them.
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ]);

        $theirItem = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ]);

        $this->withHeaders($this->headers)
            ->putJson("/api/customer/table/order/update/{$order->id}", [
                'shared_item' => $theirItem->id,
            ])
            ->assertOk();

        // 2 x 3.85 of my own, plus half of Bob's 3.85 line.
        $this->assertSame(9.63, (float) $order->fresh()->amount);
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

    public function test_update_order_returns_compact_patch_matching_realtime_and_excludes_unrelated_rows(): void
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

        $myOrder = Order::create([
            'order_public_id' => 'ord-compact-share-caller',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_CONFIRMED,
            'amount' => 0,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => false,
            'payment_received' => false,
        ]);
        $otherOrder = Order::create([
            'order_public_id' => 'ord-compact-share-owner',
            'customer_id' => $other->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status' => Order::STATUS_CONFIRMED,
            'amount' => 4.2,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => false,
            'payment_received' => false,
        ]);
        $sharedItem = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $otherOrder->id,
            'quantity' => 1,
        ]);

        $unrelatedOrder = Order::create([
            'order_public_id' => 'ord-unrelated-to-share',
            'customer_id' => $other->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status' => Order::STATUS_CONFIRMED,
            'amount' => 4.2,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => false,
            'payment_received' => false,
        ]);
        $unrelatedItem = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $unrelatedOrder->id,
            'quantity' => 1,
        ]);

        // Unrelated table history must not increase the mutation's query
        // count: the endpoint should only load the changed sharing graph.
        foreach (range(1, 12) as $sequence) {
            $extraOrder = Order::create([
                'order_public_id' => "ord-unrelated-share-budget-{$sequence}",
                'customer_id' => $other->id,
                'vendor_id' => $this->vendor->id,
                'table_scan_session_id' => $otherSession->id,
                'status' => Order::STATUS_CONFIRMED,
                'amount' => 4.2,
                'currency' => 'EUR',
                'order_type' => 'dine-in',
                'payment_pending' => false,
                'payment_received' => false,
            ]);
            CartItem::create([
                'table_scan_session_id' => $otherSession->id,
                'menu_item_id' => $this->menuItem->id,
                'order_id' => $extraOrder->id,
                'quantity' => 1,
            ]);
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/customer/table/order/update/{$myOrder->id}", [
                'shared_item' => $sharedItem->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Item sharing updated.')
            ->assertJsonPath('state_patch.operation', 'order.sharing_updated')
            ->assertJsonMissingPath('people')
            ->assertJsonMissingPath('summary');

        $statePatch = $response->json('state_patch');
        $orderPatches = collect($statePatch['orders']['upsert']);
        $itemPatches = collect($statePatch['items']['upsert']);
        $sharedItemPatch = $itemPatches->firstWhere('cart_item_id', $sharedItem->id);

        $this->assertNotNull($orderPatches->firstWhere('id', $myOrder->id));
        $this->assertNotNull($orderPatches->firstWhere('id', $otherOrder->id));
        $this->assertNull($orderPatches->firstWhere('id', $unrelatedOrder->id));
        $this->assertSame([$myOrder->id], $sharedItemPatch['shared_order_ids']);
        $this->assertNull($itemPatches->firstWhere('cart_item_id', $unrelatedItem->id));

        $notification = Notification::where('customer_id', $other->id)
            ->where('event', 'order_updated')
            ->get()
            ->first(fn (Notification $row) => ($row->metadata['template'] ?? null) === 'order.sharing_updated');

        $this->assertNotNull($notification);
        $this->assertSame($statePatch, $notification->metadata['state_patch']);
        $this->assertArrayNotHasKey('person_snapshots', $notification->metadata);
        $this->assertLessThanOrEqual(23, $queryCount, "Sharing update executed {$queryCount} database queries.");
    }

    public function test_unshare_compact_patch_reports_deleted_empty_side_order(): void
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

        $mainOrder = Order::create([
            'order_public_id' => 'ord-unshare-main',
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_CONFIRMED,
            'amount' => 0,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => false,
            'payment_received' => false,
        ]);
        $sideOrder = Order::create([
            'order_public_id' => 'ord-unshare-side',
            'parent_order_id' => $mainOrder->id,
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => Order::STATUS_CONFIRMED,
            'amount' => 2.1,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => false,
            'payment_received' => false,
        ]);
        $ownerOrder = Order::create([
            'order_public_id' => 'ord-unshare-owner',
            'customer_id' => $other->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $otherSession->id,
            'status' => Order::STATUS_CONFIRMED,
            'amount' => 2.1,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => false,
            'payment_received' => false,
        ]);
        $sharedItem = CartItem::create([
            'table_scan_session_id' => $otherSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $ownerOrder->id,
            'quantity' => 1,
            'shared_order_ids' => [$sideOrder->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->putJson("/api/customer/table/order/update/{$sideOrder->id}", [
                'unshared_item' => $sharedItem->id,
            ])
            ->assertOk()
            ->assertJsonPath('state_patch.operation', 'order.sharing_updated')
            ->assertJsonPath('removed_order_ids.0', $sideOrder->id)
            ->assertJsonPath('state_patch.orders.remove_ids.0', $sideOrder->id)
            ->assertJsonMissingPath('people');

        $this->assertDatabaseMissing('orders', ['id' => $sideOrder->id]);
        $this->assertSame([], array_map('intval', $sharedItem->fresh()->shared_order_ids ?? []));

        $statePatch = $response->json('state_patch');
        $this->assertNull(collect($statePatch['orders']['upsert'])->firstWhere('id', $sideOrder->id));
        $this->assertSame(
            [],
            collect($statePatch['items']['upsert'])->firstWhere('cart_item_id', $sharedItem->id)['shared_order_ids'],
        );

        $notification = Notification::where('customer_id', $other->id)
            ->where('event', 'order_updated')
            ->get()
            ->first(fn (Notification $row) => ($row->metadata['template'] ?? null) === 'order.sharing_updated');

        $this->assertNotNull($notification);
        $this->assertSame($statePatch, $notification->metadata['state_patch']);
        $this->assertSame([$sideOrder->id], $notification->metadata['removed_order_ids']);
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
            'order_public_id' => 'ord-split-'.uniqid(),
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
