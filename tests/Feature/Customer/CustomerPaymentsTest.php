<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Models\VendorSetting;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use UnexpectedValueException;

class CustomerPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Vendor $vendor;
    private VendorSetting $settings;
    private TableScanSession $session;
    private MenuItem $menuItem;
    private array $headers;
    private FakeStripePaymentService $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->vendor = Vendor::factory()->create();
        $this->settings = VendorSetting::create([
            'vendor_id' => $this->vendor->id,
            'stripe_enabled' => true,
            'stripe_account_id' => 'acct_test_123',
            'stripe_onboarding_complete' => true,
        ]);

        $token = $this->customer->createToken('test', ['role:customer'])->plainTextToken;
        $this->headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        $table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 1,
            'name' => 'Table 1',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $this->session = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $this->customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains-' . $this->vendor->id,
        ]);

        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Burger',
            'price' => 6,
        ]);

        $this->stripe = new FakeStripePaymentService();
        $this->app->instance(StripePaymentService::class, $this->stripe);
    }

    public function test_create_intent_requires_authentication(): void
    {
        $this->postJson('/api/customer/payments/create-intent', [
            'order_id' => 'ord-test',
            'customer_id' => '1',
        ])
            ->assertUnauthorized();
    }

    public function test_payment_methods_returns_on_site_and_connected_stripe(): void
    {
        $this->settings->update([
            'accept_on_site' => true,
            'stripe_enabled' => true,
            'stripe_account_id' => 'acct_test_123',
            'stripe_onboarding_complete' => true,
        ]);

        $this->getJson("/api/customer/payment-methods?restaurant_id={$this->vendor->vendor_public_id}")
            ->assertOk()
            ->assertJsonPath('method.on-site', true)
            ->assertJsonPath('method.stripe', true);
    }

    public function test_payment_methods_accepts_vendor_public_id_alias_and_wrapped_identifier(): void
    {
        $this->settings->update([
            'accept_on_site' => true,
            'stripe_enabled' => true,
            'stripe_account_id' => 'acct_test_123',
            'stripe_onboarding_complete' => true,
        ]);

        $this->getJson("/api/customer/payment-methods?vendor_public_id={$this->vendor->vendor_public_id}")
            ->assertOk()
            ->assertJsonPath('method.stripe', true);

        $wrapped = urlencode('{' . $this->vendor->vendor_public_id . '}');
        $this->getJson("/api/customer/payment-methods?restaurant_id={$wrapped}")
            ->assertOk()
            ->assertJsonPath('method.on-site', true);
    }

    public function test_payment_methods_resolves_restaurant_slug(): void
    {
        $this->settings->update([
            'accept_on_site' => false,
            'stripe_enabled' => true,
            'stripe_account_id' => 'acct_test_123',
            'stripe_onboarding_complete' => true,
        ]);

        $this->getJson("/api/customer/payment-methods?restaurant_id={$this->vendor->slug}")
            ->assertOk()
            ->assertJsonPath('method.on-site', false)
            ->assertJsonPath('method.stripe', true);
    }

    public function test_payment_methods_hides_stripe_when_disabled_or_unconnected(): void
    {
        $this->settings->update([
            'accept_on_site' => false,
            'stripe_enabled' => false,
            'stripe_account_id' => 'acct_test_123',
            'stripe_onboarding_complete' => true,
        ]);

        $this->getJson("/api/customer/payment-methods?restaurant_id={$this->vendor->id}")
            ->assertOk()
            ->assertJsonPath('method.on-site', false)
            ->assertJsonPath('method.stripe', false);

        $this->settings->update([
            'stripe_enabled' => true,
            'stripe_account_id' => null,
            'stripe_onboarding_complete' => true,
        ]);

        $this->getJson("/api/customer/payment-methods?restaurant_id={$this->vendor->id}")
            ->assertOk()
            ->assertJsonPath('method.stripe', false);
    }

    public function test_payment_methods_requires_restaurant_id(): void
    {
        $this->getJson('/api/customer/payment-methods')
            ->assertStatus(422)
            ->assertJsonValidationErrors('restaurant_id');
    }

    public function test_payment_methods_returns_clean_404_for_unknown_restaurant(): void
    {
        $this->getJson('/api/customer/payment-methods?restaurant_id=missing-vendor')
            ->assertNotFound()
            ->assertJsonPath('message', 'Restaurant not found.');
    }

    public function test_create_intent_rejects_another_customers_order(): void
    {
        $other = Customer::factory()->create();
        $order = $this->order(customer: $other);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent', [
                'order_id' => $order->order_public_id,
                'customer_id' => (string) $this->customer->id,
            ])
            ->assertNotFound();
    }

    public function test_create_intent_rejects_mismatched_customer_id(): void
    {
        $order = $this->order();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent', [
                'order_id' => $order->order_public_id,
                'customer_id' => '9999',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_create_intent_rejects_already_paid_order(): void
    {
        $order = $this->order(['payment_received' => true, 'payment_pending' => false]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent', [
                'order_id' => $order->order_public_id,
                'customer_id' => (string) $this->customer->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Order is already paid.');
    }

    public function test_create_intent_rejects_vendor_without_stripe_account(): void
    {
        $this->settings->update(['stripe_account_id' => null]);
        $order = $this->order();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent', [
                'order_id' => $order->order_public_id,
                'customer_id' => (string) $this->customer->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Stripe payments are not enabled for this restaurant.');
    }

    public function test_create_intent_creates_stripe_intent_and_order_payment(): void
    {
        $order = $this->order();
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 2,
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

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent', [
                'order_id' => $order->order_public_id,
                'customer_id' => (string) $this->customer->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('clientSecret', 'pi_fake_1_secret_test')
            ->assertJsonPath('paymentIntentId', 'pi_fake_1');

        $this->assertSame(1650, $this->stripe->created[0]['amountMinor']);
        $this->assertSame('acct_test_123', $this->stripe->created[0]['stripeAccountId']);
        $this->assertSame('dine_in', $this->stripe->created[0]['metadata']['payment_for']);
        $this->assertSame((string) $this->session->id, $this->stripe->created[0]['metadata']['table_session_id']);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'stripe_payment_intent_id' => 'pi_fake_1',
            'amount' => 16.50,
            'currency' => 'EUR',
            'status' => 'requires_payment_method',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'amount' => 16.50,
            'payment_method' => 'stripe',
            'transaction_id' => 'pi_fake_1',
            'payment_pending' => true,
            'payment_received' => false,
        ]);
    }

    public function test_create_intent_accepts_numeric_order_id(): void
    {
        $order = $this->order();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent', [
                'order_id' => $order->id,
                'customer_id' => $this->customer->id,
            ])
            ->assertOk()
            ->assertJsonPath('paymentIntentId', 'pi_fake_1');
    }

    public function test_update_intent_adds_tip_and_updates_payable_amount(): void
    {
        $order = $this->order();
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $order->id,
            'quantity' => 2,
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent', [
                'order_id' => $order->order_public_id,
                'customer_id' => $this->customer->id,
            ])
            ->assertOk()
            ->assertJsonPath('clientSecret', 'pi_fake_1_secret_test');

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/update-intent', [
                'payment_intent_id' => 'pi_fake_1_secret_test',
                'order_id' => $order->order_public_id,
                'customer_id' => $this->customer->id,
                'tip_amount' => 5.00,
            ]);

        $response->assertOk()
            ->assertJsonPath('clientSecret', 'pi_fake_1_secret_test')
            ->assertJsonPath('paymentIntentId', 'pi_fake_1');

        $this->assertSame(1820, $this->stripe->updated[0]['amountMinor']);
        $this->assertSame('5.00', $this->stripe->updated[0]['metadata']['tip_amount']);
        $this->assertSame('13.20', $this->stripe->updated[0]['metadata']['base_amount']);
        $this->assertSame('18.20', $this->stripe->updated[0]['metadata']['payable_amount']);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'amount' => 13.20,
            'tip_amount' => 5,
            'transaction_id' => 'pi_fake_1',
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'stripe_payment_intent_id' => 'pi_fake_1',
            'amount' => 18.20,
            'currency' => 'EUR',
        ]);
    }

    public function test_update_intent_rejects_mismatched_customer_id(): void
    {
        $order = $this->order();
        $this->payment($order, 'pi_existing');

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/update-intent', [
                'payment_intent_id' => 'pi_existing',
                'order_id' => $order->order_public_id,
                'customer_id' => 9999,
                'tip_amount' => 5.00,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');
    }

    public function test_verify_marks_order_paid_when_payment_intent_succeeded(): void
    {
        $order = $this->order();
        $payment = $this->payment($order, 'pi_paid');
        $this->stripe->intents['pi_paid'] = [
            'id' => 'pi_paid',
            'client_secret' => null,
            'status' => 'succeeded',
            'metadata' => ['order_id' => (string) $order->id, 'customer_id' => (string) $this->customer->id],
            'payment_method' => 'pm_card_visa',
        ];

        $this->withHeaders($this->headers)
            ->getJson('/api/customer/payments/verify?payment_intent=pi_paid')
            ->assertOk()
            ->assertJsonPath('status', 'succeeded')
            ->assertJsonPath('orderStatus', 'paid');

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'status' => 'succeeded',
            'payment_method' => 'pm_card_visa',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_pending' => false,
            'payment_received' => true,
            'transaction_id' => 'pi_paid',
        ]);
    }

    public function test_verify_returns_pending_for_processing_payment_intent(): void
    {
        $order = $this->order();
        $this->payment($order, 'pi_processing');
        $this->stripe->intents['pi_processing'] = [
            'id' => 'pi_processing',
            'client_secret' => null,
            'status' => 'processing',
            'metadata' => ['order_id' => (string) $order->id, 'customer_id' => (string) $this->customer->id],
            'payment_method' => null,
        ];

        $this->withHeaders($this->headers)
            ->getJson('/api/customer/payments/verify?payment_intent=pi_processing')
            ->assertOk()
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('orderStatus', 'pending');
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->stripe->rejectWebhook = true;

        $this->postJson('/api/customer/payments/webhook', ['id' => 'evt_bad'], [
            'Stripe-Signature' => 'bad',
        ])->assertStatus(400);
    }

    public function test_webhook_idempotently_marks_order_paid(): void
    {
        $order = $this->order();
        $this->payment($order, 'pi_webhook');
        $event = [
            'type' => 'payment_intent.succeeded',
            'payment_intent' => [
                'id' => 'pi_webhook',
                'client_secret' => null,
                'status' => 'succeeded',
                'metadata' => ['order_id' => (string) $order->id],
                'payment_method' => 'pm_card_visa',
            ],
        ];
        $this->stripe->events[] = $event;

        $this->postJson('/api/customer/payments/webhook', ['id' => 'evt_1'], [
            'Stripe-Signature' => 'valid',
        ])->assertOk()->assertJsonPath('received', true);

        $this->stripe->events[] = $event;
        $this->postJson('/api/customer/payments/webhook', ['id' => 'evt_1'], [
            'Stripe-Signature' => 'valid',
        ])->assertOk()->assertJsonPath('received', true);

        $this->assertDatabaseHas('order_payments', [
            'stripe_payment_intent_id' => 'pi_webhook',
            'status' => 'succeeded',
        ]);
        $this->assertTrue($order->fresh()->payment_received);
    }

    public function test_webhook_returns_200_for_unknown_payment_intent(): void
    {
        $this->stripe->events[] = [
            'type' => 'payment_intent.succeeded',
            'payment_intent' => [
                'id' => 'pi_unknown_xyz',
                'client_secret' => null,
                'status' => 'succeeded',
                'metadata' => [],
                'payment_method' => 'pm_card_visa',
            ],
        ];

        $this->postJson('/api/customer/payments/webhook', ['id' => 'evt_unknown'], [
            'Stripe-Signature' => 'valid',
        ])
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonPath('ignored', true);
    }

    public function test_webhook_logs_every_delivery_attempt(): void
    {
        $this->stripe->rejectWebhook = true;

        $this->postJson('/api/customer/payments/webhook', ['id' => 'evt_bad'], [
            'Stripe-Signature' => 'bad',
        ])->assertStatus(400);

        $this->assertDatabaseHas('stripe_webhook_logs', [
            'outcome' => 'signature_invalid',
            'http_status' => 400,
        ]);
    }

    public function test_webhook_marks_failed_payment(): void
    {
        $order = $this->order();
        $this->payment($order, 'pi_failed');
        $this->stripe->events[] = [
            'type' => 'payment_intent.payment_failed',
            'payment_intent' => [
                'id' => 'pi_failed',
                'client_secret' => null,
                'status' => 'requires_payment_method',
                'metadata' => ['order_id' => (string) $order->id],
                'payment_method' => null,
            ],
        ];

        $this->postJson('/api/customer/payments/webhook', ['id' => 'evt_failed'], [
            'Stripe-Signature' => 'valid',
        ])->assertOk();

        $this->assertDatabaseHas('order_payments', [
            'stripe_payment_intent_id' => 'pi_failed',
            'status' => 'failed',
        ]);
        $this->assertFalse($order->fresh()->payment_pending);
        $this->assertFalse($order->fresh()->payment_received);
    }

    private function order(array $attributes = [], ?Customer $customer = null): Order
    {
        return Order::create(array_merge([
            'order_public_id' => 'ord-' . uniqid(),
            'customer_id' => ($customer ?? $this->customer)->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $customer ? null : $this->session->id,
            'status' => 'confirmed',
            'amount' => 6,
            'currency' => 'EUR',
            'order_type' => 'dine-in',
            'payment_pending' => true,
            'payment_received' => false,
        ], $attributes));
    }

    private function payment(Order $order, string $paymentIntentId): OrderPayment
    {
        return OrderPayment::create([
            'order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'customer_id' => $this->customer->id,
            'table_scan_session_id' => $order->table_scan_session_id,
            'stripe_account_id' => 'acct_test_123',
            'stripe_payment_intent_id' => $paymentIntentId,
            'amount' => (float) $order->amount,
            'currency' => $order->currency,
            'status' => 'processing',
            'metadata' => [
                'order_id' => (string) $order->id,
                'customer_id' => (string) $this->customer->id,
            ],
        ]);
    }
}

class FakeStripePaymentService extends StripePaymentService
{
    public array $created = [];
    public array $updated = [];
    public array $intents = [];
    public array $events = [];
    public bool $rejectWebhook = false;

    public function __construct()
    {
    }

    public function createPaymentIntent(int $amountMinor, string $currency, string $stripeAccountId, array $metadata): array
    {
        $id = 'pi_fake_' . (count($this->created) + 1);
        $payload = [
            'id' => $id,
            'client_secret' => $id . '_secret_test',
            'status' => 'requires_payment_method',
            'metadata' => $metadata,
            'payment_method' => null,
        ];

        $this->created[] = compact('amountMinor', 'currency', 'stripeAccountId', 'metadata');
        $this->intents[$id] = $payload;

        return $payload;
    }

    public function updatePaymentIntent(string $paymentIntentId, int $amountMinor, string $currency, array $metadata = []): array
    {
        $payload = $this->intents[$paymentIntentId] ?? [
            'id' => $paymentIntentId,
            'client_secret' => $paymentIntentId . '_secret_test',
            'status' => 'requires_payment_method',
            'metadata' => [],
            'payment_method' => null,
        ];

        $payload['metadata'] = $metadata;

        $this->updated[] = compact('paymentIntentId', 'amountMinor', 'currency', 'metadata');
        $this->intents[$paymentIntentId] = $payload;

        return $payload;
    }

    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        return $this->intents[$paymentIntentId] ?? [
            'id' => $paymentIntentId,
            'client_secret' => null,
            'status' => 'requires_payment_method',
            'metadata' => [],
            'payment_method' => null,
        ];
    }

    public function parseWebhookEvent(string $payload, ?string $signature): array
    {
        if ($this->rejectWebhook) {
            throw new UnexpectedValueException('Invalid signature');
        }

        return array_shift($this->events) ?? [
            'type' => 'payment_intent.processing',
            'payment_intent' => [
                'id' => 'pi_missing',
                'client_secret' => null,
                'status' => 'processing',
                'metadata' => [],
                'payment_method' => null,
            ],
        ];
    }
}
