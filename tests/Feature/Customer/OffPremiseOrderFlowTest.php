<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
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

        $this->asCustomer($this->headers($customer, 'takeaway'))
            ->postJson('/api/customer/table/scan', ['token' => $qr->qr_token])
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
        $id = 'pi_off_premise';

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
