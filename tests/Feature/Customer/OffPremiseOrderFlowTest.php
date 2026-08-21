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

    public function test_covered_pickup_order_stays_separate_from_a_new_draft_after_release_and_reselection(): void
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

        // The owner creates the first draft, then another guest selects it in
        // "pay for". That freezes the exact cart row the payer selected.
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)
            ->postJson('/api/customer/table/order/draft')
            ->assertCreated();

        $firstOrder = Order::where('customer_id', $owner->id)->sole();
        $firstItem = CartItem::where('table_scan_session_id', $firstOrder->table_scan_session_id)->sole();
        $singleItemOrderAmount = (float) $firstOrder->amount;

        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $firstOrder->order_public_id])
            ->assertOk();

        $this->assertSame($firstOrder->id, (int) $firstItem->fresh()->order_id);

        // Adding the same menu item now must create a separate line and a
        // separate draft, not change the quantity the payer already selected.
        $this->asCustomer($ownerHeaders)->postJson('/api/customer/cart/items', [
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->asCustomer($ownerHeaders)
            ->postJson('/api/customer/table/order/draft')
            ->assertCreated();

        $ownerOrders = Order::where('customer_id', $owner->id)->orderBy('id')->get();
        $this->assertCount(2, $ownerOrders);
        $secondOrder = $ownerOrders->last();
        $secondItem = CartItem::where('table_scan_session_id', $firstOrder->table_scan_session_id)
            ->whereNull('order_id')
            ->sole();

        $assertSeparateOrders = function () use ($payerHeaders, $owner, $firstOrder, $secondOrder, $firstItem, $secondItem, $singleItemOrderAmount): void {
            $history = $this->asCustomer($payerHeaders)
                ->getJson('/api/customer/table/history')
                ->assertOk();

            $person = collect($history->json('people'))->firstWhere('customer_id', $owner->id);
            $orders = collect($person['orders'])->keyBy('id');

            $this->assertCount(2, $orders);
            $this->assertSame([$firstItem->id], collect($orders[$firstOrder->id]['items'])->pluck('cart_item_id')->all());
            $this->assertSame([$secondItem->id], collect($orders[$secondOrder->id]['items'])->pluck('cart_item_id')->all());
            $this->assertSame($singleItemOrderAmount, (float) $orders[$firstOrder->id]['amount']);
            $this->assertSame($singleItemOrderAmount, (float) $orders[$secondOrder->id]['amount']);
        };

        $assertSeparateOrders();

        // Going back (release), then selecting either or both cards again,
        // must preserve the two order identities and their individual totals.
        $this->asCustomer($payerHeaders)
            ->deleteJson("/api/customer/payments/pay-for/{$firstOrder->order_public_id}")
            ->assertOk();

        $this->assertSame($firstOrder->id, (int) $firstItem->fresh()->order_id);
        $assertSeparateOrders();

        // The payer can select only the newer card. This is the exact
        // multi-window case where the owner has two same-item drafts and the
        // other guest chooses the second order rather than the first.
        $this->asCustomer($payerHeaders)
            ->postJson('/api/customer/payments/pay-for', ['order_id' => $secondOrder->order_public_id])
            ->assertOk();

        $this->assertNull($firstOrder->fresh()->paid_by);
        $this->assertSame($payer->id, (int) $secondOrder->fresh()->paid_by);
        $this->assertSame($secondOrder->id, (int) $secondItem->fresh()->order_id);
        $assertSeparateOrders();

        $this->asCustomer($payerHeaders)
            ->deleteJson("/api/customer/payments/pay-for/{$secondOrder->order_public_id}")
            ->assertOk();

        foreach ([$firstOrder, $secondOrder] as $order) {
            $this->asCustomer($payerHeaders)
                ->postJson('/api/customer/payments/pay-for', ['order_id' => $order->order_public_id])
                ->assertOk();
        }

        $this->assertSame($firstOrder->id, (int) $firstItem->fresh()->order_id);
        $this->assertSame($secondOrder->id, (int) $secondItem->fresh()->order_id);
        $assertSeparateOrders();

        foreach ([$firstOrder, $secondOrder] as $order) {
            $this->asCustomer($payerHeaders)
                ->deleteJson("/api/customer/payments/pay-for/{$order->order_public_id}")
                ->assertOk();
        }

        $this->assertSame($firstOrder->id, (int) $firstItem->fresh()->order_id);
        $this->assertSame($secondOrder->id, (int) $secondItem->fresh()->order_id);
        $assertSeparateOrders();

        // The owner's checkout is session-wide even though the payment URL
        // carries one representative order id. Both own drafts must be part of
        // the same intent and the charge must be their summed amount.
        $this->asCustomer($ownerHeaders)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        $payment = OrderPayment::where('customer_id', $owner->id)->sole();
        $this->assertEqualsCanonicalizing(
            [$firstOrder->id, $secondOrder->id],
            array_map('intval', $payment->order_ids),
        );
        $this->assertEqualsCanonicalizing(
            [$firstOrder->id, $secondOrder->id],
            $payment->orders()->pluck('orders.id')->map(fn ($id) => (int) $id)->all(),
        );
        $this->assertSame(round($singleItemOrderAmount * 2, 2), (float) $payment->amount);
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
}
