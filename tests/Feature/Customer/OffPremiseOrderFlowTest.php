<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Models\VendorTakeawayQr;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OffPremiseOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('services.customer_api_cache.store', 'array');
        config()->set('services.customer_api_cache.enabled', true);

        $this->vendor = Vendor::factory()->create();
        VendorSetting::create([
            'vendor_id' => $this->vendor->id,
            'stripe_enabled' => true,
            'stripe_account_id' => 'acct_off_premise',
            'stripe_onboarding_complete' => true,
        ]);
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'off-premise-'.$this->vendor->id,
        ]);
        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Burger',
            'price' => 10,
        ]);
    }

    public function test_pickup_owner_can_schedule_and_tablemate_can_join_the_same_pin_group(): void
    {
        $owner = Customer::factory()->create(['first_name' => 'Owner']);
        $mate = Customer::factory()->create(['first_name' => 'Mate']);
        $scheduledFor = now()->addDay()->startOfHour();

        $started = $this->asCustomer($this->headers($owner, 'pickup'))
            ->postJson('/api/customer/table/scan', [
                'vendor_public_id' => $this->vendor->vendor_public_id,
                'scheduled_for' => $scheduledFor->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('session.type', 'pickup')
            ->assertJsonPath('table', null);

        $pin = $started->json('pin');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);

        $this->asCustomer($this->headers($mate, 'pickup'))
            ->postJson('/api/customer/table/pin', [
                'vendor_public_id' => $this->vendor->vendor_public_id,
                'pin' => $pin,
            ])
            ->assertSuccessful()
            ->assertJsonPath('pin', $pin);

        $sessions = TableScanSession::query()
            ->where('vendor_id', $this->vendor->id)
            ->where('type', 'pickup')
            ->where('pin', $pin)
            ->get();

        $this->assertCount(2, $sessions);
        $this->assertTrue($sessions->every(fn (TableScanSession $session) => $session->scheduled_for?->equalTo($scheduledFor)));
    }

    public function test_takeaway_uses_the_same_endpoints_but_isolated_as_asap(): void
    {
        $qr = VendorTakeawayQr::create([
            'vendor_id' => $this->vendor->id,
            'qr_token' => 'takeaway-token',
        ]);
        $customer = Customer::factory()->create();

        $this->withHeader('X-Order-Mode', 'takeaway')
            ->getJson("/api/customer/table/status?token={$qr->qr_token}")
            ->assertOk()
            ->assertJsonPath('orderMode', 'takeaway')
            ->assertJsonPath('vendor.slug', $this->vendor->slug);

        $this->getJson("/api/customer/pickup/status?token={$qr->qr_token}")
            ->assertOk()
            ->assertJsonPath('type', 'takeaway')
            ->assertJsonPath('vendor.slug', $this->vendor->slug);

        $this->asCustomer($this->headers($customer, 'takeaway'))
            ->postJson('/api/customer/table/scan', [
                'token' => $qr->qr_token,
                'scheduled_for' => now()->addDay()->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('session.type', 'takeaway')
            ->assertJsonPath('session.scheduledFor', null);

        $this->asCustomer($this->headers($customer, 'pickup'))
            ->getJson('/api/customer/table/session/status')
            ->assertOk()
            ->assertJsonPath('active', false);

        $this->asCustomer($this->headers($customer, 'takeaway'))
            ->getJson('/api/customer/table/session/status')
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('session.type', 'takeaway');
    }

    public function test_group_members_see_each_others_drafts_and_can_cover_them(): void
    {
        [$owner, $ownerHeaders, $pin] = $this->startPickup();
        $mate = Customer::factory()->create();
        $mateHeaders = $this->headers($mate, 'pickup');

        $this->asCustomer($mateHeaders)->postJson('/api/customer/table/pin', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'pin' => $pin,
        ])->assertSuccessful();

        foreach ([$ownerHeaders, $mateHeaders] as $headers) {
            $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ])->assertCreated();
            $this->asCustomer($headers)
                ->postJson('/api/customer/table/order/draft')
                ->assertCreated();
        }

        $history = $this->asCustomer($ownerHeaders)
            ->getJson('/api/customer/table/history')
            ->assertOk()
            ->assertJsonCount(2, 'people');

        $mateOrder = collect($history->json('people'))
            ->first(fn (array $person) => ! $person['is_me'])['orders'][0];

        $this->asCustomer($ownerHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $mateOrder['order_public_id']])
            ->assertOk()
            ->assertJsonPath('paid_by.id', $owner->id);

        $this->assertDatabaseHas('orders', [
            'id' => $mateOrder['id'],
            'status' => Order::STATUS_DRAFT,
            'paid_by' => $owner->id,
        ]);
    }

    public function test_mutual_payers_can_checkout_the_remaining_unpaid_pickup_order_in_sequence(): void
    {
        [$left, $leftHeaders, $pin] = $this->startPickup();
        $right = Customer::factory()->create();
        $rightHeaders = $this->headers($right, 'pickup');
        $stripe = new OffPremiseFakeStripePaymentService;
        $this->app->instance(StripePaymentService::class, $stripe);

        $this->asCustomer($rightHeaders)->postJson('/api/customer/table/pin', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'pin' => $pin,
        ])->assertSuccessful();

        foreach ([$leftHeaders, $rightHeaders] as $headers) {
            $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ])->assertCreated();
            $this->asCustomer($headers)
                ->postJson('/api/customer/table/order/draft')
                ->assertCreated();
        }

        $leftOrder = Order::where('customer_id', $left->id)->sole();
        $rightOrder = Order::where('customer_id', $right->id)->sole();

        // Each guest agrees to cover the other guest's order.
        $this->asCustomer($leftHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $rightOrder->order_public_id])
            ->assertOk();
        $this->asCustomer($rightHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $leftOrder->order_public_id])
            ->assertOk();

        // Left pays first, settling only Right's order.
        $leftIntent = $this->asCustomer($leftHeaders)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();
        $leftIntentId = $leftIntent->json('paymentIntentId');
        $stripe->intents[$leftIntentId]['status'] = 'succeeded';
        $this->asCustomer($leftHeaders)
            ->getJson("/api/customer/payments/verify?payment_intent={$leftIntentId}")
            ->assertOk()
            ->assertJsonPath('status', 'succeeded');

        $this->assertTrue($rightOrder->fresh()->payment_received);
        $this->assertFalse($leftOrder->fresh()->payment_received);
        $this->assertSame(Order::STATUS_DRAFT, $leftOrder->fresh()->status);

        // Both paid/confirmed and unpaid/unconfirmed orders remain in the
        // shared history, and Right can start checkout for the one they cover.
        $history = $this->asCustomer($rightHeaders)
            ->getJson('/api/customer/table/history')
            ->assertOk();
        $orders = collect($history->json('people'))
            ->flatMap(fn (array $person) => $person['orders'])
            ->keyBy('id');

        $this->assertTrue($orders[$rightOrder->id]['payment_received']);
        $this->assertFalse($orders[$leftOrder->id]['payment_received']);
        $this->assertSame(Order::STATUS_DRAFT, $orders[$leftOrder->id]['status']);

        $rightIntent = $this->asCustomer($rightHeaders)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        $rightPayment = OrderPayment::where('stripe_payment_intent_id', $rightIntent->json('paymentIntentId'))
            ->sole();
        $this->assertSame([$leftOrder->id], array_map('intval', $rightPayment->order_ids));
    }

    public function test_step_one_coverage_stays_editable_and_checkout_back_merges_a_temporary_draft(): void
    {
        [$owner, $ownerHeaders, $pin] = $this->startPickup();
        $stripe = new OffPremiseFakeStripePaymentService;
        $this->app->instance(StripePaymentService::class, $stripe);
        $payer = Customer::factory()->create();
        $payerHeaders = $this->headers($payer, 'pickup');

        $this->asCustomer($payerHeaders)->postJson('/api/customer/table/pin', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'pin' => $pin,
        ])->assertSuccessful();

        // Selecting "pay for" on step 1 is only an assignment. The original
        // row stays editable and the same item increments its quantity.
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)
            ->postJson('/api/customer/table/order/draft')
            ->assertCreated();

        $firstOrder = Order::where('customer_id', $owner->id)->sole();
        $firstItem = CartItem::where('table_scan_session_id', $firstOrder->table_scan_session_id)->sole();
        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $firstOrder->order_public_id])
            ->assertOk();

        $this->assertNull($firstItem->fresh()->order_id);
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->assertSame(2, $firstItem->fresh()->quantity);
        $this->assertSame(1, Order::where('customer_id', $owner->id)->count());

        // Entering step 2 freezes the first order. A later add therefore opens
        // a new row and draft instead of changing the payer's frozen amount.
        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/checkout-hold')
            ->assertOk();
        $this->assertSame($firstOrder->id, (int) $firstItem->fresh()->order_id);

        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)
            ->postJson('/api/customer/table/order/draft')
            ->assertCreated();

        $this->assertSame(2, Order::where('customer_id', $owner->id)->count());
        $this->assertCount(2, CartItem::where('table_scan_session_id', $firstOrder->table_scan_session_id)->get());

        // Back releases the hold. Both drafts are now editable, so the newer
        // one folds backward into the first and equal rows combine.
        $this->asCustomer($payerHeaders)
            ->deleteJson('/api/customer/payments/checkout-hold')
            ->assertOk();

        $this->assertSame(1, Order::where('customer_id', $owner->id)->count());
        $mergedItem = CartItem::where('table_scan_session_id', $firstOrder->table_scan_session_id)->sole();
        $this->assertSame(3, $mergedItem->quantity);
        $this->assertNull($mergedItem->order_id);
        $this->assertSame($payer->id, (int) $firstOrder->fresh()->paid_by);
    }

    public function test_an_unlocked_order_waits_for_its_locked_sibling_before_merging(): void
    {
        [$owner, $ownerHeaders, $pin] = $this->startPickup();
        $payer = Customer::factory()->create();
        $payerHeaders = $this->headers($payer, 'pickup');

        $this->asCustomer($payerHeaders)->postJson('/api/customer/table/pin', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'pin' => $pin,
        ])->assertSuccessful();

        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/table/order/draft')->assertCreated();
        $firstOrder = Order::where('customer_id', $owner->id)->sole();

        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $firstOrder->order_public_id])
            ->assertOk();
        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/checkout-hold')
            ->assertOk();

        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/table/order/draft')->assertCreated();
        $secondOrder = Order::where('customer_id', $owner->id)->latest('id')->firstOrFail();

        // The owner freezes O2 while the mate still holds O1.
        $this->asCustomer($ownerHeaders)
            ->postJson('/api/customer/payments/checkout-hold')
            ->assertOk()
            ->assertJsonPath('held.0', $secondOrder->id);

        // Releasing O1 cannot absorb the still-frozen O2.
        $this->asCustomer($payerHeaders)
            ->deleteJson('/api/customer/payments/checkout-hold')
            ->assertOk();
        $this->assertSame(2, Order::where('customer_id', $owner->id)->count());
        $this->assertFalse($firstOrder->fresh()->payment_pending);
        $this->assertTrue($secondOrder->fresh()->payment_pending);

        // Once O2 also unlocks, both are editable and merge backward into O1.
        $this->asCustomer($ownerHeaders)
            ->deleteJson('/api/customer/payments/checkout-hold')
            ->assertOk();

        $this->assertSame(1, Order::where('customer_id', $owner->id)->count());
        $this->assertDatabaseHas('orders', ['id' => $firstOrder->id]);
        $this->assertDatabaseMissing('orders', ['id' => $secondOrder->id]);
        $this->assertSame(
            2,
            CartItem::where('table_scan_session_id', $firstOrder->table_scan_session_id)->sole()->quantity,
        );
    }

    public function test_a_step_one_shared_item_can_still_increment_until_checkout(): void
    {
        [$owner, $ownerHeaders, $pin] = $this->startPickup();
        $mate = Customer::factory()->create();
        $mateHeaders = $this->headers($mate, 'pickup');

        $this->asCustomer($mateHeaders)->postJson('/api/customer/table/pin', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'pin' => $pin,
        ])->assertSuccessful();

        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/table/order/draft')->assertCreated();

        $this->asCustomer($mateHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'notes' => 'mate line',
        ])->assertCreated();
        $this->asCustomer($mateHeaders)->postJson('/api/customer/table/order/draft')->assertCreated();

        $ownerItem = CartItem::whereHas('session', fn ($query) => $query->where('customer_id', $owner->id))->sole();
        $mateOrder = Order::where('customer_id', $mate->id)->sole();

        $this->asCustomer($mateHeaders)
            ->putJson("/api/customer/table/order/update/{$mateOrder->id}", [
                'shared_item' => $ownerItem->id,
            ])
            ->assertOk();

        $this->assertContains($mateOrder->id, array_map('intval', $ownerItem->fresh()->shared_order_ids));

        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->assertSame(2, $ownerItem->fresh()->quantity);
        $this->assertSame(
            1,
            CartItem::where('table_scan_session_id', $ownerItem->table_scan_session_id)->count(),
        );
    }

    public function test_successful_card_payment_confirms_pickup_draft_in_the_same_lifecycle(): void
    {
        [, $headers] = $this->startPickup();
        $stripe = new OffPremiseFakeStripePaymentService;
        $this->app->instance(StripePaymentService::class, $stripe);

        $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();

        $intent = $this->asCustomer($headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();
        $intentId = $intent->json('paymentIntentId');

        $order = Order::query()->sole();
        $this->assertSame(Order::STATUS_DRAFT, $order->status);
        $this->assertFalse($order->payment_received);
        $this->assertNotNull(CartItem::query()->sole()->order_id);

        $stripe->intents[$intentId]['status'] = 'succeeded';
        $this->asCustomer($headers)
            ->getJson("/api/customer/payments/verify?payment_intent={$intentId}")
            ->assertOk()
            ->assertJsonPath('status', 'succeeded');

        $order->refresh();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertTrue($order->payment_received);
        $this->assertNotNull($order->confirmed_at);
        $this->assertNotNull($order->kitchen_released_at);
        $this->assertNotNull(CartItem::query()->sole()->received_at);

        // Older pickup clients used to call the cart-confirm endpoint after
        // successful verification. The order is already finalized, so the
        // replay must be an idempotent success rather than "cart is empty".
        $this->asCustomer($headers)
            ->postJson('/api/customer/table/order/confirmed')
            ->assertOk();

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
        $this->assertTrue($order->payment_received);
    }

    public function test_successful_scheduled_card_payment_confirms_now_but_waits_for_kitchen_window(): void
    {
        $scheduledFor = now()->addMinutes(30);
        [, $headers] = $this->startPickup($scheduledFor);
        $stripe = new OffPremiseFakeStripePaymentService;
        $this->app->instance(StripePaymentService::class, $stripe);

        $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();

        $intent = $this->asCustomer($headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();
        $stripe->intents[$intent->json('paymentIntentId')]['status'] = 'succeeded';
        $this->asCustomer($headers)
            ->getJson('/api/customer/payments/verify?payment_intent='.$intent->json('paymentIntentId'))
            ->assertOk()
            ->assertJsonPath('status', 'succeeded');

        $order = Order::query()->sole();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertTrue($order->payment_received);
        $this->assertNull($order->kitchen_released_at);

        $this->travel(10)->minutes();
        $this->artisan('kitchen-orders:release-scheduled')->assertSuccessful();
        $this->assertNotNull($order->fresh()->kitchen_released_at);
    }

    public function test_cash_draft_is_vendor_visible_and_confirmation_confirms_the_order(): void
    {
        [, $headers] = $this->startPickup();

        $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();
        $this->asCustomer($headers)
            ->postJson('/api/customer/payments/request-cash', ['notes' => 'Pay at pickup'])
            ->assertOk();

        $order = Order::query()->sole();
        $this->assertSame(Order::STATUS_DRAFT, $order->status);
        $this->assertTrue($order->payment_pending);
        $this->assertFalse($order->payment_received);

        $this->app['auth']->forgetGuards();
        $vendorToken = $this->vendor->createToken('vendor-test')->plainTextToken;
        $vendorHeaders = [
            'Authorization' => "Bearer {$vendorToken}",
            'Accept' => 'application/json',
        ];

        $this->withHeaders($vendorHeaders)
            ->getJson("/api/vendor/{$this->vendor->vendor_public_id}/orders")
            ->assertOk()
            ->assertJsonCount(1, 'takeaway')
            ->assertJsonPath('takeaway.0.status', Order::STATUS_DRAFT)
            ->assertJsonPath('takeaway.0.paymentPending', true);

        $this->app['auth']->forgetGuards();
        $this->withHeaders($vendorHeaders)
            ->patchJson("/api/vendor/orders/{$order->id}/ready")
            ->assertConflict();

        $this->app['auth']->forgetGuards();
        $this->withHeaders($vendorHeaders)
            ->patchJson("/api/vendor/orders/{$order->id}/confirm-cash", [
                'paymentNote' => 'Cash received at counter',
            ])
            ->assertOk()
            ->assertJsonPath('status', Order::STATUS_CONFIRMED)
            ->assertJsonPath('paymentReceived', true);

        $order->refresh();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertTrue($order->payment_received);
        $this->assertFalse($order->payment_pending);
        $this->assertNotNull($order->confirmed_at);
        $this->assertNotNull($order->kitchen_released_at);
        $this->assertNotNull(CartItem::query()->sole()->received_at);
    }

    public function test_pickup_cash_request_is_rejected_when_vendor_disables_it(): void
    {
        [, $headers] = $this->startPickup();
        $this->vendor->vendorSetting()->update([
            'accept_on_site' => false,
            'accept_pickup_cash' => true,
        ]);

        $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();

        $this->asCustomer($headers)
            ->postJson('/api/customer/payments/request-cash')
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Cash payment is not available for pickup orders at this restaurant.'
            );

        $order = Order::query()->sole();
        $this->assertFalse($order->payment_pending);
        $this->assertFalse($order->payment_received);
    }

    /** @return array{Customer, array<string, string>, string} */
    public function test_a_declined_payment_unlocks_and_merges_the_temporary_order_back(): void
    {
        [$owner, $ownerHeaders, $pin] = $this->startPickup();
        $stripe = new OffPremiseFakeStripePaymentService;
        $this->app->instance(StripePaymentService::class, $stripe);
        $payer = Customer::factory()->create();
        $payerHeaders = $this->headers($payer, 'pickup');

        $this->asCustomer($payerHeaders)->postJson('/api/customer/table/pin', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'pin' => $pin,
        ])->assertSuccessful();

        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/table/order/draft')->assertCreated();

        $firstOrder = Order::where('customer_id', $owner->id)->sole();
        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $firstOrder->order_public_id])
            ->assertOk();

        // Creating the intent freezes the first order, so the owner's next add
        // has to open a second one.
        $intentId = $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk()
            ->json('paymentIntentId');

        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/table/order/draft')->assertCreated();
        $this->assertSame(2, Order::where('customer_id', $owner->id)->count());

        // The card is declined. Nobody pressed Back, but the boundary the lock
        // created is just as gone, so the two orders have to fold together.
        $stripe->intents[$intentId]['status'] = 'canceled';
        $this->asCustomer($payerHeaders)
            ->getJson("/api/customer/payments/verify?payment_intent={$intentId}")
            ->assertOk();

        $this->assertSame(1, Order::where('customer_id', $owner->id)->count());
        $this->assertFalse($firstOrder->fresh()->payment_pending);
        $this->assertSame(
            2,
            CartItem::where('table_scan_session_id', $firstOrder->table_scan_session_id)->sole()->quantity,
        );
    }

    public function test_leaving_the_payment_step_cancels_a_cash_request_instead_of_stranding_it(): void
    {
        [, $headers] = $this->startPickup();

        $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();

        $this->asCustomer($headers)->postJson('/api/customer/payments/checkout-hold')->assertOk();
        $this->asCustomer($headers)
            ->postJson('/api/customer/payments/request-cash', ['notes' => 'Pay at pickup'])
            ->assertOk();

        $order = Order::query()->sole();
        $this->assertTrue($order->fresh()->payment_pending);
        $this->assertSame('cash_requested', OrderPayment::query()->sole()->status);

        // Back. A cash request carries no Stripe intent, so it used to survive
        // this while the hold release cleared payment_pending anyway — leaving a
        // live collection against an order the guest could edit again.
        $this->asCustomer($headers)->deleteJson('/api/customer/payments/checkout-hold')->assertOk();

        $this->assertSame('canceled', OrderPayment::query()->sole()->status);

        $order = $order->fresh();
        $this->assertFalse($order->payment_pending);
        $this->assertNull($order->checkout_hold_by);
    }

    public function test_a_payer_can_tip_when_every_order_they_settle_belongs_to_someone_else(): void
    {
        [$owner, $ownerHeaders, $pin] = $this->startPickup();
        $stripe = new OffPremiseFakeStripePaymentService;
        $this->app->instance(StripePaymentService::class, $stripe);

        $payer = Customer::factory()->create();
        $payerHeaders = $this->headers($payer, 'pickup');
        $this->asCustomer($payerHeaders)->postJson('/api/customer/table/pin', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'pin' => $pin,
        ])->assertSuccessful();

        foreach ([$ownerHeaders, $payerHeaders] as $headers) {
            $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
                'menu_item_id' => $this->menuItem->id,
                'quantity' => 1,
            ])->assertCreated();
            $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();
        }

        $ownerOrder = Order::where('customer_id', $owner->id)->sole();
        $payerOrder = Order::where('customer_id', $payer->id)->sole();

        // Mutual coverage. Each guest's own order is now claimed by the other,
        // so it leaves their payable set and every order they settle belongs to
        // somebody else — which used to make a tip impossible for both of them.
        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $ownerOrder->order_public_id])
            ->assertOk();
        $this->asCustomer($ownerHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $payerOrder->order_public_id])
            ->assertOk();

        $intentId = $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk()
            ->json('paymentIntentId');

        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/update-intent', [
                'payment_intent_id' => $intentId,
                'tip_amount' => 5,
            ])
            ->assertOk();

        // The tip lands on the order being settled, exactly once.
        $this->assertSame(5.0, (float) $ownerOrder->fresh()->tip_amount);
        $this->assertSame(0.0, (float) $payerOrder->fresh()->tip_amount);
    }

    public function test_a_cash_payer_can_tip_for_an_order_they_do_not_own(): void
    {
        [$owner, $ownerHeaders, $pin] = $this->startPickup();

        $payer = Customer::factory()->create();
        $payerHeaders = $this->headers($payer, 'pickup');
        $this->asCustomer($payerHeaders)->postJson('/api/customer/table/pin', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            'pin' => $pin,
        ])->assertSuccessful();

        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/table/order/draft')->assertCreated();

        $ownerOrder = Order::where('customer_id', $owner->id)->sole();
        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $ownerOrder->order_public_id])
            ->assertOk();

        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/request-cash', ['tip_amount' => 3.5])
            ->assertOk();

        $this->assertSame(3.5, (float) $ownerOrder->fresh()->tip_amount);
    }

    public function test_session_status_names_the_other_off_premise_mode_instead_of_looking_expired(): void
    {
        $customer = Customer::factory()->create();
        $qr = VendorTakeawayQr::create(['vendor_id' => $this->vendor->id, 'qr_token' => 'tk-mode']);

        $this->asCustomer($this->headers($customer, 'takeaway'))
            ->postJson('/api/customer/table/scan', ['token' => $qr->qr_token])
            ->assertCreated();

        // Asking as pickup still reports no pickup session — the two modes stay
        // isolated — but it now names the mode that does hold one, so the client
        // can correct its stored mode instead of discarding a live session.
        $this->asCustomer($this->headers($customer, 'pickup'))
            ->getJson('/api/customer/table/session/status')
            ->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonPath('active_order_mode', 'takeaway');

        // And asking in the right mode is unchanged.
        $this->asCustomer($this->headers($customer, 'takeaway'))
            ->getJson('/api/customer/table/session/status')
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('session.type', 'takeaway');
    }

    public function test_session_status_reports_no_other_mode_when_there_is_none(): void
    {
        $customer = Customer::factory()->create();

        $this->asCustomer($this->headers($customer, 'pickup'))
            ->getJson('/api/customer/table/session/status')
            ->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonPath('active_order_mode', null);
    }

    private function startPickup(?\DateTimeInterface $scheduledFor = null): array
    {
        $customer = Customer::factory()->create();
        $headers = $this->headers($customer, 'pickup');
        $response = $this->asCustomer($headers)->postJson('/api/customer/table/scan', [
            'vendor_public_id' => $this->vendor->vendor_public_id,
            ...($scheduledFor ? ['scheduled_for' => $scheduledFor->format(DATE_ATOM)] : []),
        ])->assertCreated();

        return [$customer, $headers, (string) $response->json('pin')];
    }

    /** @return array<string, string> */
    private function headers(Customer $customer, string $mode): array
    {
        $token = $customer->createToken('test', ['role:customer'])->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
            'X-Order-Mode' => $mode,
        ];
    }

    /** @param array<string, string> $headers */
    private function asCustomer(array $headers): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders($headers);
    }
}

class OffPremiseFakeStripePaymentService extends StripePaymentService
{
    public array $intents = [];

    public function __construct() {}

    public function createPaymentIntent(int $amountMinor, string $currency, string $stripeAccountId, array $metadata): array
    {
        $id = 'pi_off_premise_'.(count($this->intents) + 1);

        return $this->intents[$id] = [
            'id' => $id,
            'client_secret' => $id.'_secret_test',
            'status' => 'requires_payment_method',
            'metadata' => $metadata,
            'payment_method' => null,
        ];
    }

    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        return $this->intents[$paymentIntentId];
    }

    public function updatePaymentIntent(string $paymentIntentId, int $amountMinor, string $currency, array $metadata = []): array
    {
        $payload = $this->intents[$paymentIntentId] ?? [
            'id' => $paymentIntentId,
            'client_secret' => $paymentIntentId.'_secret_test',
            'status' => 'requires_payment_method',
            'payment_method' => null,
        ];

        $payload['amount'] = $amountMinor;
        $payload['currency'] = $currency;
        $payload['metadata'] = $metadata;

        return $this->intents[$paymentIntentId] = $payload;
    }
}
