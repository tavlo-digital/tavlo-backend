<?php

namespace Tests\Feature\Customer;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Notification;
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
        $this->postJson('/api/customer/payments/create-intent')
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

    public function test_create_intent_rejects_when_customer_has_no_unpaid_orders(): void
    {
        $other = Customer::factory()->create();
        $this->order(customer: $other);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have no unpaid orders to pay for.');
    }

    public function test_create_intent_requires_active_table_session(): void
    {
        $this->order();
        $this->session->update(['status' => 'closed']);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertStatus(422)
            ->assertJsonPath('message', 'No active table session found.');
    }

    public function test_create_intent_rejects_already_paid_order(): void
    {
        $this->order(['payment_received' => true, 'payment_pending' => false]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have no unpaid orders to pay for.');
    }

    public function test_create_intent_rejects_vendor_without_stripe_account(): void
    {
        $this->settings->update(['stripe_account_id' => null]);
        $this->order();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
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
            ->postJson('/api/customer/payments/create-intent');

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

    public function test_create_intent_covers_all_unpaid_session_orders(): void
    {
        $first = $this->order();
        $second = $this->order();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk()
            ->assertJsonPath('paymentIntentId', 'pi_fake_1');

        $this->assertSame(1200, $this->stripe->created[0]['amountMinor']);
        $payment = OrderPayment::where('stripe_payment_intent_id', 'pi_fake_1')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $payment->orders->pluck('id')->all(),
        );
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
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk()
            ->assertJsonPath('clientSecret', 'pi_fake_1_secret_test');

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/update-intent', [
                'payment_intent_id' => 'pi_fake_1_secret_test',
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

    public function test_update_intent_rejects_another_customers_payment_intent(): void
    {
        $other = Customer::factory()->create();
        $order = $this->order();
        OrderPayment::create([
            'order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'customer_id' => $other->id,
            'table_scan_session_id' => $order->table_scan_session_id,
            'stripe_account_id' => 'acct_test_123',
            'stripe_payment_intent_id' => 'pi_foreign',
            'amount' => 6,
            'currency' => 'EUR',
            'status' => 'processing',
            'metadata' => [],
        ]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/update-intent', [
                'payment_intent_id' => 'pi_foreign',
                'tip_amount' => 5.00,
            ])
            ->assertNotFound();
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

    public function test_customer_can_idempotently_assign_a_tablemate_order(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $first = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);
        $second = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_pending' => false,
        ], $tablemate);

        foreach (range(1, 2) as $attempt) {
            $this->withHeaders($this->headers)
                ->postJson('/api/customer/payments/pay-for', [
                    'customer_id' => $tablemate->id,
                    'order_id' => $first->order_public_id,
                ])
                ->assertOk()
                ->assertJsonPath('paid_by.id', $this->customer->id)
                ->assertJsonPath('orders_count', 1);
        }

        $this->assertSame($this->customer->id, $first->fresh()->paid_by);
        $this->assertNull($second->fresh()->paid_by);

        $history = $this->withHeaders($this->headers)
            ->getJson('/api/customer/table/history')
            ->assertOk();

        $tablematePayload = collect($history->json('people'))
            ->firstWhere('customer_id', $tablemate->id);
        $assignedOrder = collect($tablematePayload['orders'])->firstWhere('id', $first->id);
        $this->assertSame($this->customer->id, $assignedOrder['paid_by']['id']);
    }

    public function test_customer_can_assign_a_single_tablemate_order(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $first = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);
        $second = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'status' => Order::STATUS_IN_PROGRESS,
            'payment_pending' => false,
        ], $tablemate);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $tablemate->id,
                'order_id' => $first->order_public_id,
            ])
            ->assertOk()
            ->assertJsonPath('paid_by.id', $this->customer->id)
            ->assertJsonPath('orders_count', 1)
            ->assertJsonPath('orders.0.id', $first->id);

        $this->assertSame($this->customer->id, $first->fresh()->paid_by);
        $this->assertNull($second->fresh()->paid_by);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $tablemate->id,
                'order_id' => $second->id,
            ])
            ->assertOk()
            ->assertJsonPath('orders_count', 1)
            ->assertJsonPath('orders.0.id', $second->id);

        $this->assertSame($this->customer->id, $second->fresh()->paid_by);
    }

    public function test_single_order_assignment_rejects_ineligible_order(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);
        $ownOrder = $this->order(['payment_pending' => false]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $tablemate->id,
                'order_id' => $ownOrder->order_public_id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_id');

        $this->assertNull($ownOrder->fresh()->paid_by);
    }

    public function test_assignment_rejects_another_payer_and_release_is_atomic_while_payment_is_active(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        [$otherPayer, $otherPayerSession] = $this->tablemate();
        $targetOrder = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
            'paid_by' => $otherPayer->id,
        ], $tablemate);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $tablemate->id,
                'order_id' => $targetOrder->order_public_id,
            ])
            ->assertStatus(409);

        $targetOrder->update(['paid_by' => $this->customer->id]);
        $ownOrder = $this->order(['payment_pending' => false]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/payments/pay-for/{$tablemate->id}")
            ->assertStatus(409);

        $this->assertSame($this->customer->id, $targetOrder->fresh()->paid_by);
        $this->assertSame($otherPayerSession->restaurant_table_id, $this->session->restaurant_table_id);
    }

    public function test_combined_intent_and_webhook_update_every_covered_order(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $ownOrder = $this->order(['amount' => 6, 'payment_pending' => false]);
        $coveredOrder = $this->order([
            'amount' => 7,
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $tablemate->id,
                'order_id' => $coveredOrder->order_public_id,
            ])
            ->assertOk();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk()
            ->assertJsonPath('paymentIntentId', 'pi_fake_1');

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk()
            ->assertJsonPath('paymentIntentId', 'pi_fake_1');

        $this->assertCount(1, $this->stripe->created);
        $this->assertSame(1300, $this->stripe->created[0]['amountMinor']);
        $payment = OrderPayment::where('stripe_payment_intent_id', 'pi_fake_1')->firstOrFail();
        $this->assertCount(2, $payment->orders);

        $event = [
            'type' => 'payment_intent.succeeded',
            'payment_intent' => [
                'id' => 'pi_fake_1',
                'client_secret' => null,
                'status' => 'succeeded',
                'metadata' => [
                    'order_id' => (string) $ownOrder->id,
                    'customer_id' => (string) $this->customer->id,
                ],
                'payment_method' => 'pm_card_visa',
            ],
        ];
        $this->stripe->events[] = $event;

        $this->postJson('/api/customer/payments/webhook', [], [
            'Stripe-Signature' => 'valid',
        ])->assertOk();

        $this->assertTrue($ownOrder->fresh()->payment_received);
        $this->assertTrue($coveredOrder->fresh()->payment_received);
        $this->assertSame($this->customer->id, $coveredOrder->fresh()->paid_by);
    }

    public function test_assigned_only_payment_allows_intent_but_rejects_positive_tip(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $coveredOrder = $this->order([
            'amount' => 7,
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $tablemate->id,
                'order_id' => $coveredOrder->order_public_id,
            ])
            ->assertOk();

        $this->withHeaders($this->headers)
            ->getJson('/api/customer/orders/history')
            ->assertOk()
            ->assertJsonPath('summary.orders_count', 0);

        $tablemateToken = $tablemate->createToken('history', ['role:customer'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/customer/orders/history', [
            'Authorization' => "Bearer {$tablemateToken}",
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('history.0.orders.0.paid_by.id', $this->customer->id);

        $this->app['auth']->forgetGuards();
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/update-intent', [
                'payment_intent_id' => 'pi_fake_1',
                'tip_amount' => 2,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A tip cannot be added when paying only for another customer’s orders.');
    }

    public function test_payer_can_release_assignment_before_payment(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $order = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $tablemate->id,
                'order_id' => $order->order_public_id,
            ])
            ->assertOk();

        $assignedNotifications = Notification::where('event', 'order_updated')
            ->whereNotNull('customer_id')
            ->get()
            ->filter(fn (Notification $notification) => ($notification->metadata['template'] ?? null) === 'payment.assigned');
        $this->assertCount(2, $assignedNotifications);
        $this->assertEqualsCanonicalizing(
            [$this->customer->id, $tablemate->id],
            $assignedNotifications->pluck('customer_id')->all(),
        );

        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/payments/pay-for/{$tablemate->id}")
            ->assertOk()
            ->assertJsonPath('released_orders_count', 1);

        $this->assertNull($order->fresh()->paid_by);

        $releasedNotifications = Notification::where('event', 'order_updated')
            ->whereNotNull('customer_id')
            ->get()
            ->filter(fn (Notification $notification) => ($notification->metadata['template'] ?? null) === 'payment.assignment_released');
        $this->assertCount(2, $releasedNotifications);
        $this->assertEqualsCanonicalizing(
            [$this->customer->id, $tablemate->id],
            $releasedNotifications->pluck('customer_id')->all(),
        );
    }

    public function test_assignment_rejects_self_customer_outside_table_and_no_eligible_orders(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', ['customer_id' => $this->customer->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_id');

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $this->customer->id,
                'order_id' => 'ord-any',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('customer_id');

        $outsideCustomer = Customer::factory()->create();
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $outsideCustomer->id,
                'order_id' => 'ord-any',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The selected customer is not active at your table.');

        [$tablemate] = $this->tablemate();
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'customer_id' => $tablemate->id,
                'order_id' => 'ord-any',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_id');
    }

    public function test_owner_cannot_create_intent_after_order_is_assigned(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $order = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
            'paid_by' => $this->customer->id,
        ], $tablemate);
        $token = $tablemate->createToken('test', ['role:customer'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->postJson('/api/customer/payments/create-intent')
            ->assertStatus(409)
            ->assertJsonPath('message', 'This order is assigned to another payer.');
    }

    private function tablemate(): array
    {
        $customer = Customer::factory()->create();
        $session = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->session->restaurant_table_id,
            'customer_id' => $customer->id,
            'pin' => TableScanSession::generateUniquePin(),
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        return [$customer, $session];
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
