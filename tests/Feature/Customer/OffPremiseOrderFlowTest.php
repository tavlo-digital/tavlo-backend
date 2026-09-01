<?php

namespace Tests\Feature\Customer;

use App\Http\Controllers\Api\Customer\PaymentController;
use App\Jobs\DeliverOperationalNotification;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Models\VendorTakeawayQr;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
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

    public function test_a_guest_with_no_items_can_still_share_a_mates_item(): void
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

        $ownerItem = CartItem::whereHas('session', fn ($q) => $q->where('customer_id', $owner->id))->sole();
        $ownerOrder = Order::where('customer_id', $owner->id)->whereNull('parent_order_id')->sole();
        $wholeLine = $this->historyLineTotal($ownerHeaders, $ownerItem->id);
        $this->assertSame(0, Order::where('customer_id', $mate->id)->count());

        // The mate has ordered nothing, so has no order to share into. 0 asks
        // for one rather than the tap doing nothing.
        $this->asCustomer($mateHeaders)
            ->putJson('/api/customer/table/order/update/0', ['shared_item' => $ownerItem->id])
            ->assertOk();

        $mateOrder = Order::where('customer_id', $mate->id)->sole();
        $this->assertContains(
            (int) $mateOrder->id,
            array_map('intval', $ownerItem->fresh()->shared_order_ids ?? []),
        );
        // Half of the line, since the two of them now split it.
        $this->assertGreaterThan(0, (float) $mateOrder->amount);
        $this->assertLessThan((float) $ownerOrder->amount, (float) $mateOrder->fresh()->amount + 0.01);

        // The order minted for the share has to reach the mate's own screen,
        // or "Select an order to pay" lists everyone's order but theirs.
        $this->assertContains(
            (int) $mateOrder->id,
            $this->historyOrderIds($mateHeaders),
            'The share order is missing from the sharer own history.',
        );

        // And the owner has to see the line drop to their half, not the
        // untouched full price.
        $this->assertLessThan(
            $wholeLine,
            $this->historyLineTotal($ownerHeaders, $ownerItem->id),
            'The owner line total was not split after the share.',
        );
    }

    public function test_tracking_shows_a_bought_share_and_the_tip_that_was_charged(): void
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

        $ownerItem = CartItem::whereHas('session', fn ($q) => $q->where('customer_id', $owner->id))->sole();
        $this->asCustomer($mateHeaders)
            ->putJson('/api/customer/table/order/update/0', ['shared_item' => $ownerItem->id])
            ->assertOk();

        $mateOrder = Order::where('customer_id', $mate->id)->sole();
        $mateOrder->update(['tip_amount' => 1.08]);

        $tracking = $this->asCustomer($mateHeaders)
            ->getJson("/api/customer/orders/{$mateOrder->order_public_id}/tracking")
            ->assertOk();

        // The mate ordered nothing of their own, so the plate they bought into
        // is the only thing on the page. Dropping it left an empty order.
        $this->assertSame([], $tracking->json('items'));
        $shared = $tracking->json('shared_items');
        $this->assertCount(1, $shared);
        $this->assertSame((int) $ownerItem->id, $shared[0]['cart_item_id']);
        $this->assertFalse($shared[0]['owned_by_me']);
        $this->assertSame($owner->id, $shared[0]['shared_with'][0]['customer_id']);

        // What they were charged, not just the order amount.
        $orderAmount = round((float) $mateOrder->fresh()->amount, 2);
        $tracking
            ->assertJsonPath('total_amount', $orderAmount)
            ->assertJsonPath('tip_amount', 1.08)
            ->assertJsonPath('amount_charged', round($orderAmount + 1.08, 2))
            ->assertJsonPath('totals.total_tips', 1.08)
            ->assertJsonPath('totals.grand_total', round($orderAmount + 1.08, 2));

        // And the fee stays broken out, so the page can say what the food cost
        // and what the fee added. This vendor charges none.
        $this->assertSame(0.0, (float) $tracking->json('totals.service_fee'));
        $this->assertSame(
            round($orderAmount - (float) $tracking->json('totals.service_fee'), 2),
            round((float) $tracking->json('totals.grand_total') - 1.08 - (float) $tracking->json('totals.service_fee'), 2),
        );
    }

    public function test_tracking_keeps_the_items_of_a_draft_that_requested_cash(): void
    {
        [, $headers] = $this->startPickup();

        $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();

        $order = Order::whereNull('parent_order_id')->sole();
        $this->asCustomer($headers)
            ->postJson('/api/customer/payments/request-cash', [])
            ->assertSuccessful();

        $order->refresh();
        $this->assertTrue((bool) $order->payment_pending);
        $this->assertSame(Order::STATUS_DRAFT, $order->status);

        $tracking = $this->asCustomer($headers)
            ->getJson("/api/customer/orders/{$order->order_public_id}/tracking")
            ->assertOk();

        // Requesting cash binds the rows to the order. Looking only for unbound
        // rows emptied the tracking page the moment a guest asked to pay.
        $this->assertCount(1, $tracking->json('items'));
        $tracking
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('payment_method', 'cash')
            ->assertJsonPath('payment_pending', true)
            ->assertJsonPath('payment_received', false);
    }

    public function test_a_cash_request_survives_another_payment_being_released(): void
    {
        [, $headers] = $this->startPickup();

        $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();

        $order = Order::whereNull('parent_order_id')->sole();
        $this->asCustomer($headers)
            ->postJson('/api/customer/payments/request-cash', [])
            ->assertSuccessful();

        $this->assertTrue((bool) $order->fresh()->payment_pending);

        // A card attempt on the same order that never completes. Releasing it
        // must not take the standing cash request down with it, or the waiter
        // loses the order they were asked to collect on.
        $stale = OrderPayment::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'vendor_id' => $order->vendor_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'EUR',
            'status' => 'requires_payment_method',
        ]);

        $controller = app(PaymentController::class);
        $cancel = new \ReflectionMethod($controller, 'cancelAbandonedPayment');
        $cancel->invoke($controller, $stale);

        $order->refresh();
        $this->assertTrue(
            (bool) $order->payment_pending,
            'The standing cash request was cleared by an unrelated release.',
        );
        $this->assertSame('cash', $order->payment_method);
    }

    public function test_a_pickup_cash_request_reaches_the_waiter_with_its_frozen_draft_items(): void
    {
        Queue::fake([DeliverOperationalNotification::class]);
        $waiter = TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Cash Waiter',
            'email' => 'cash-waiter-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'waiter',
            'permissions' => TeamMember::defaultPermissions('waiter'),
            'status' => 'active',
            'joined_at' => now(),
        ]);
        [, $headers] = $this->startPickup();

        $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();

        $order = Order::whereNull('parent_order_id')->sole();
        $item = CartItem::where('table_scan_session_id', $order->table_scan_session_id)->sole();

        $this->asCustomer($headers)
            ->postJson('/api/customer/payments/request-cash')
            ->assertSuccessful();

        // Operational notifications are deferred until the response has been
        // sent. The pickup path used to stop at customer notifications, so the
        // waiter's useOperationalRefresh hook never knew to reload this draft.
        app(DeferredCallbackCollection::class)->invoke();
        $delivery = Queue::pushed(DeliverOperationalNotification::class)
            ->first(fn (DeliverOperationalNotification $job): bool => ($job->payload['metadata']['template'] ?? null) === 'staff.payment_updated'
                && (int) ($job->payload['metadata']['order_id'] ?? 0) === (int) $order->id
            );

        $this->assertNotNull($delivery);
        $this->assertContains('orders', $delivery->payload['metadata']['resources']);
        $delivery->handle();

        $staffNotification = Notification::where('waiter_id', $waiter->id)
            ->where('event', 'payment_updated')
            ->get()
            ->first(fn (Notification $notification): bool => (int) ($notification->metadata['order_id'] ?? 0) === (int) $order->id
            );
        $this->assertNotNull($staffNotification);

        $waiterToken = $waiter->createToken('test', ['role:team_member', 'role:waiter'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $response = $this->getJson("/api/vendor/{$this->vendor->id}/orders", [
            'Authorization' => "Bearer {$waiterToken}",
            'Accept' => 'application/json',
        ])->assertOk();

        $listedOrder = collect($response->json('takeaway'))->firstWhere('id', (string) $order->id);
        $this->assertNotNull($listedOrder);
        $this->assertSame(Order::STATUS_DRAFT, $listedOrder['status']);
        $this->assertTrue($listedOrder['paymentPending']);
        $this->assertSame('cash', $listedOrder['paymentMethod']);
        $this->assertSame((int) $item->id, (int) $listedOrder['items'][0]['cartItemId']);
        $this->assertSame(2, $listedOrder['items'][0]['quantity']);
    }

    public function test_the_stale_payment_reconciler_leaves_a_standing_cash_request_alone(): void
    {
        [, $headers] = $this->startPickup();

        $this->asCustomer($headers)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($headers)->postJson('/api/customer/table/order/draft')->assertCreated();

        $order = Order::whereNull('parent_order_id')->sole();
        $this->asCustomer($headers)
            ->postJson('/api/customer/payments/request-cash', [])
            ->assertSuccessful();
        $this->assertTrue((bool) $order->fresh()->payment_pending);

        // An older card attempt on the same order that Stripe never completed.
        $stale = OrderPayment::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'vendor_id' => $order->vendor_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'EUR',
            'status' => 'requires_payment_method',
            'stripe_payment_intent_id' => 'pi_never_finished',
        ]);
        // created_at is not fillable, and the command only looks at attempts
        // older than ten minutes.
        OrderPayment::whereKey($stale->id)->update(['created_at' => now()->subHour()]);

        $this->app->instance(StripePaymentService::class, new class extends StripePaymentService
        {
            public function __construct() {}

            public function retrievePaymentIntent(string $paymentIntentId): array
            {
                return ['id' => $paymentIntentId, 'status' => 'requires_payment_method'];
            }
        });

        $this->artisan('payments:reconcile-stale')->assertSuccessful();

        $order->refresh();
        $this->assertSame('failed', $stale->fresh()->status);
        // The command ran every minute, so it took the order off the waiter's
        // screen again within a minute of any repair.
        $this->assertTrue(
            (bool) $order->payment_pending,
            'Reconciling a dead card attempt unlocked a standing cash request.',
        );
        $this->assertSame('cash', $order->payment_method);
        $this->assertNotSame('Stripe payment was not completed.', $order->payment_note);
    }

    /** @return array<int, int> */
    private function historyOrderIds(array $headers): array
    {
        $people = $this->asCustomer($headers)
            ->getJson('/api/customer/table/history')
            ->assertOk()
            ->json('people');

        return collect($people)
            ->flatMap(fn (array $person) => array_column($person['orders'] ?? [], 'id'))
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function historyLineTotal(array $headers, int $cartItemId): float
    {
        $people = $this->asCustomer($headers)
            ->getJson('/api/customer/table/history')
            ->assertOk()
            ->json('people');

        $row = collect($people)
            ->flatMap(fn (array $person) => collect($person['orders'] ?? [])
                ->flatMap(fn (array $order) => $order['items'] ?? []))
            ->first(fn (array $item) => (int) ($item['cart_item_id'] ?? 0) === $cartItemId);

        $this->assertNotNull($row, "Cart item {$cartItemId} is missing from the history.");

        return (float) ($row['my_share'] ?? $row['line_total'] ?? 0);
    }

    public function test_unsharing_removes_the_order_that_existed_only_for_the_share(): void
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

        $ownerItem = CartItem::whereHas('session', fn ($q) => $q->where('customer_id', $owner->id))->sole();

        $this->asCustomer($mateHeaders)
            ->putJson('/api/customer/table/order/update/0', ['shared_item' => $ownerItem->id])
            ->assertOk();
        $mateOrder = Order::where('customer_id', $mate->id)->sole();

        $this->asCustomer($mateHeaders)
            ->putJson("/api/customer/table/order/update/{$mateOrder->id}", ['unshared_item' => $ownerItem->id])
            ->assertOk();

        // Otherwise the guest is left with an empty order card they never placed.
        $this->assertDatabaseMissing('orders', ['id' => $mateOrder->id]);
        $this->assertSame(0, Order::where('customer_id', $mate->id)->count());
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
