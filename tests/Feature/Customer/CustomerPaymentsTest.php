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
            'slug' => 'mains-'.$this->vendor->id,
        ]);

        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Burger',
            'price' => 6,
        ]);

        $this->stripe = new FakeStripePaymentService;
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

    public function test_payment_methods_uses_pickup_cash_setting_for_off_premise_orders(): void
    {
        $this->settings->update([
            'accept_on_site' => true,
            'accept_pickup_cash' => false,
        ]);

        $url = "/api/customer/payment-methods?restaurant_id={$this->vendor->vendor_public_id}";

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('method.on-site', true);

        $this->withHeader('X-Order-Mode', 'pickup')
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('method.on-site', false);

        $this->withHeader('X-Order-Mode', 'takeaway')
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('method.on-site', false);
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

        $wrapped = urlencode('{'.$this->vendor->vendor_public_id.'}');
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
        $this->assertSame([$order->id], json_decode($this->stripe->created[0]['metadata']['order_ids'], true));

        $payment = OrderPayment::where('stripe_payment_intent_id', 'pi_fake_1')->firstOrFail();
        $this->assertSame([$order->id], $payment->order_ids);

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
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $payment->order_ids);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            json_decode($this->stripe->created[0]['metadata']['order_ids'], true),
        );
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $payment->orders->pluck('id')->all(),
        );
    }

    public function test_request_cash_records_every_covered_order_id_and_pivot(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $first = $this->order(['payment_pending' => false]);
        $second = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
            'paid_by' => $this->customer->id,
        ], $tablemate);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/request-cash')
            ->assertOk();

        $payment = OrderPayment::where('status', 'cash_requested')->firstOrFail();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $payment->order_ids);
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            json_decode($payment->metadata['order_ids'], true),
        );
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $payment->orders->pluck('id')->all(),
        );
        $this->assertTrue((bool) $first->fresh()->payment_pending);
        $this->assertTrue((bool) $second->fresh()->payment_pending);
    }

    public function test_payer_without_an_own_order_can_request_cash_for_an_assigned_order(): void
    {
        [$owner, $ownerSession] = $this->tablemate();
        $coveredOrder = $this->order([
            'amount' => 7,
            'table_scan_session_id' => $ownerSession->id,
            'payment_pending' => false,
        ], $owner);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => $coveredOrder->order_public_id,
            ])
            ->assertOk();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/request-cash', [
                'notes' => 'Please bring change.',
            ])
            ->assertOk()
            ->assertJsonPath('amount', 7)
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('state_patch.operation', 'payment.cash_requested');

        $payment = OrderPayment::where('status', 'cash_requested')->firstOrFail();
        $this->assertSame($this->customer->id, (int) $payment->customer_id);
        $this->assertSame($coveredOrder->id, (int) $payment->order_id);
        $this->assertSame([$coveredOrder->id], array_map('intval', $payment->order_ids));
        $this->assertSame('Please bring change.', $payment->metadata['notes']);
        $this->assertTrue((bool) $coveredOrder->fresh()->payment_pending);
        $this->assertSame('cash', $coveredOrder->fresh()->payment_method);
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
                'order_id' => $second->id,
            ])
            ->assertOk()
            ->assertJsonPath('orders_count', 1)
            ->assertJsonPath('orders.0.id', $second->id);

        $this->assertSame($this->customer->id, $second->fresh()->paid_by);
    }

    public function test_pay_for_atomically_removes_the_payers_item_share_and_returns_the_same_patch_as_realtime(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();

        $payerOrder = $this->order([
            'amount' => 9.9,
            'payment_pending' => false,
        ]);
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $payerOrder->id,
            'quantity' => 1,
        ]);

        $targetOrder = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'amount' => 3.3,
            'payment_pending' => false,
        ], $tablemate);
        $targetItem = CartItem::create([
            'table_scan_session_id' => $tablemateSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $targetOrder->id,
            'quantity' => 1,
            'shared_order_ids' => [$payerOrder->id],
        ]);

        [$unrelatedCustomer, $unrelatedSession] = $this->tablemate();
        $unrelatedOrder = $this->order([
            'table_scan_session_id' => $unrelatedSession->id,
            'payment_pending' => false,
        ], $unrelatedCustomer);
        $unrelatedItem = CartItem::create([
            'table_scan_session_id' => $unrelatedSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $unrelatedOrder->id,
            'quantity' => 1,
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => $targetOrder->order_public_id,
            ])
            ->assertOk()
            ->assertJsonPath('state_patch.operation', 'payment.assigned');

        $statePatch = $response->json('state_patch');
        $targetItemPatch = collect($statePatch['items']['upsert'])
            ->firstWhere('cart_item_id', $targetItem->id);
        $targetOrderPatch = collect($statePatch['orders']['upsert'])
            ->firstWhere('id', $targetOrder->id);

        $this->assertNotEmpty($statePatch['id']);
        $this->assertGreaterThan(0, $statePatch['version']);
        $this->assertSame([], $targetItemPatch['shared_order_ids']);
        $this->assertSame($this->customer->id, $targetOrderPatch['paid_by']['id']);
        $this->assertNotContains($unrelatedOrder->id, array_column($statePatch['orders']['upsert'], 'id'));
        $this->assertNotContains($unrelatedItem->id, array_column($statePatch['items']['upsert'], 'cart_item_id'));
        $this->assertSame([], array_map('intval', $targetItem->fresh()->shared_order_ids ?? []));
        $this->assertSame(6.6, (float) $payerOrder->fresh()->amount);
        $this->assertSame(6.6, (float) $targetOrder->fresh()->amount);
        $this->assertSame($this->customer->id, $targetOrder->fresh()->paid_by);

        $assignedNotification = Notification::where('customer_id', $tablemate->id)
            ->where('event', 'order_updated')
            ->get()
            ->first(fn (Notification $notification) => ($notification->metadata['template'] ?? null) === 'payment.assigned');

        $this->assertNotNull($assignedNotification);
        $this->assertSame($statePatch, $assignedNotification->metadata['state_patch']);
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
                'order_id' => $ownOrder->order_public_id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_id');

        $this->assertNull($ownOrder->fresh()->paid_by);
    }

    public function test_assignment_rejects_another_payer_and_release_cancels_own_stale_intent(): void
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
                'order_id' => $targetOrder->order_public_id,
            ])
            ->assertStatus(409);

        $targetOrder->update(['paid_by' => $this->customer->id]);
        $ownOrder = $this->order(['payment_pending' => false]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        // The payer's own abandoned intent (opened the payment step, went
        // back) no longer blocks unchecking: it is canceled and released.
        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/payments/pay-for/{$targetOrder->order_public_id}")
            ->assertOk()
            ->assertJsonPath('released_orders_count', 1);

        $this->assertNull($targetOrder->fresh()->paid_by);
        $this->assertContains('pi_fake_1', $this->stripe->canceled);
        $this->assertDatabaseHas('order_payments', [
            'stripe_payment_intent_id' => 'pi_fake_1',
            'status' => 'canceled',
        ]);
        $this->assertFalse($ownOrder->fresh()->payment_pending);
        $this->assertFalse($targetOrder->fresh()->payment_pending);
        $this->assertSame($otherPayerSession->restaurant_table_id, $this->session->restaurant_table_id);
    }

    public function test_release_is_blocked_while_a_payment_is_processing_or_owned_by_another_payer(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $targetOrder = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => $targetOrder->order_public_id,
            ])
            ->assertOk();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        OrderPayment::where('stripe_payment_intent_id', 'pi_fake_1')
            ->update(['status' => 'processing']);

        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/payments/pay-for/{$targetOrder->order_public_id}")
            ->assertStatus(409);

        $this->assertSame($this->customer->id, $targetOrder->fresh()->paid_by);

        // A payment started by a different customer also blocks the release.
        OrderPayment::where('stripe_payment_intent_id', 'pi_fake_1')
            ->update(['status' => 'requires_payment_method', 'customer_id' => $tablemate->id]);

        $this->withHeaders($this->headers)
            ->deleteJson("/api/customer/payments/pay-for/{$targetOrder->order_public_id}")
            ->assertStatus(409);

        $this->assertSame($this->customer->id, $targetOrder->fresh()->paid_by);
        $this->assertNotContains('pi_fake_1', $this->stripe->canceled);
    }

    public function test_share_while_covered_creates_side_order_payable_by_sharer(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();

        // My order (own burger, €6) is covered by the tablemate.
        $myOrder = $this->order([
            'paid_by' => $tablemate->id,
            'payment_pending' => false,
        ]);
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $myOrder->id,
            'quantity' => 1,
        ]);

        // The tablemate's order with their own burger (€6) that I opt into.
        $mateOrder = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);
        $sharedItem = CartItem::create([
            'table_scan_session_id' => $tablemateSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $mateOrder->id,
            'quantity' => 1,
        ]);

        $this->withHeaders($this->headers)
            ->putJson("/api/customer/table/order/update/{$myOrder->id}", [
                'shared_item' => $sharedItem->id,
            ])
            ->assertOk();

        $sideOrder = Order::where('parent_order_id', $myOrder->id)->first();
        $this->assertNotNull($sideOrder);
        $this->assertSame($this->customer->id, $sideOrder->customer_id);
        $this->assertNull($sideOrder->paid_by);
        // My share attaches to the side order, not to the covered order.
        $this->assertSame([$sideOrder->id], array_map('intval', $sharedItem->fresh()->shared_order_ids));
        $this->assertSame(3.3, (float) $sideOrder->fresh()->amount);
        // The covered order keeps only my own items — the payer's total is untouched.
        $this->assertSame(6.6, (float) $myOrder->fresh()->amount);
        $this->assertSame(3.3, (float) $mateOrder->fresh()->amount);
    }

    public function test_pay_for_moves_existing_opt_in_shares_to_side_order(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();

        $myOrder = $this->order(['payment_pending' => false]);
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $myOrder->id,
            'quantity' => 1,
        ]);

        // Before any coverage, my opt-in share of the tablemate's burger is
        // folded into my own order.
        $mateOrder = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'amount' => 3,
            'payment_pending' => false,
        ], $tablemate);
        $sharedItem = CartItem::create([
            'table_scan_session_id' => $tablemateSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $mateOrder->id,
            'quantity' => 1,
            'shared_order_ids' => [$myOrder->id],
        ]);
        $myOrder->update(['amount' => 9]);

        $mateToken = $tablemate->createToken('test', ['role:customer'])->plainTextToken;
        $mateHeaders = ['Authorization' => "Bearer {$mateToken}", 'Accept' => 'application/json'];

        $response = $this->withHeaders($mateHeaders)
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => $myOrder->order_public_id,
            ])
            ->assertOk()
            ->assertJsonPath('state_patch.operation', 'payment.assigned');

        $sideOrder = Order::where('parent_order_id', $myOrder->id)->first();
        $this->assertNotNull($sideOrder);
        $this->assertSame([$sideOrder->id], array_map('intval', $sharedItem->fresh()->shared_order_ids));
        // Covered order drops back to my own items; my share stays mine.
        $this->assertSame(6.6, (float) $myOrder->fresh()->amount);
        $this->assertSame(3.3, (float) $sideOrder->fresh()->amount);
        $this->assertSame($tablemate->id, $myOrder->fresh()->paid_by);
        $this->assertNull($sideOrder->fresh()->paid_by);

        $statePatch = $response->json('state_patch');
        $sideOrderPatch = collect($statePatch['orders']['upsert'])
            ->firstWhere('id', $sideOrder->id);
        $sharedItemPatch = collect($statePatch['items']['upsert'])
            ->firstWhere('cart_item_id', $sharedItem->id);
        $this->assertNotNull($sideOrderPatch);
        $this->assertSame($myOrder->id, $sideOrderPatch['parent_order_id']);
        $this->assertSame([$sideOrder->id], $sharedItemPatch['shared_order_ids']);

        // The payer is charged for their own share + my own items only
        // (3.30 + 6.60 gross, incl. 10% AT food VAT).
        $this->withHeaders($mateHeaders)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();
        $this->assertSame(990, $this->stripe->created[0]['amountMinor']);
    }

    public function test_release_merges_unpaid_side_order_back_into_main_order(): void
    {
        [$tablemate] = $this->tablemate();

        $myOrder = $this->order([
            'paid_by' => $tablemate->id,
            'payment_pending' => false,
        ]);
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $myOrder->id,
            'quantity' => 1,
        ]);

        $sideOrder = $this->order([
            'parent_order_id' => $myOrder->id,
            'amount' => 3,
            'payment_pending' => false,
        ]);
        $sharedItem = CartItem::create([
            'table_scan_session_id' => TableScanSession::where('customer_id', $tablemate->id)->value('id'),
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'shared_order_ids' => [$sideOrder->id],
        ]);

        $mateToken = $tablemate->createToken('test', ['role:customer'])->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$mateToken}", 'Accept' => 'application/json'])
            ->deleteJson("/api/customer/payments/pay-for/{$myOrder->order_public_id}")
            ->assertOk()
            ->assertJsonPath('released_orders_count', 1)
            ->assertJsonPath('state_patch.operation', 'payment.assignment_released');

        $this->assertNull($myOrder->fresh()->paid_by);
        $this->assertDatabaseMissing('orders', ['id' => $sideOrder->id]);
        $this->assertSame([$myOrder->id], array_map('intval', $sharedItem->fresh()->shared_order_ids));
        // Own burger (6.60 gross) + merged-back share (3.30).
        $this->assertSame(9.9, (float) $myOrder->fresh()->amount);

        $statePatch = $response->json('state_patch');
        $this->assertContains($sideOrder->id, $statePatch['orders']['remove_ids']);
        $this->assertNotNull(collect($statePatch['orders']['upsert'])->firstWhere('id', $myOrder->id));
        $sharedItemPatch = collect($statePatch['items']['upsert'])
            ->firstWhere('cart_item_id', $sharedItem->id);
        $this->assertSame([$myOrder->id], $sharedItemPatch['shared_order_ids']);
    }

    public function test_side_orders_cannot_be_covered_by_pay_for(): void
    {
        [$tablemate] = $this->tablemate();

        $myOrder = $this->order([
            'paid_by' => $tablemate->id,
            'payment_pending' => false,
        ]);
        $sideOrder = $this->order([
            'parent_order_id' => $myOrder->id,
            'amount' => 3,
            'payment_pending' => false,
        ]);

        $mateToken = $tablemate->createToken('test', ['role:customer'])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$mateToken}", 'Accept' => 'application/json'])
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => $sideOrder->order_public_id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_id');

        $this->assertNull($sideOrder->fresh()->paid_by);
    }

    public function test_covered_customer_create_intent_pays_only_their_side_order(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();

        $myOrder = $this->order([
            'paid_by' => $tablemate->id,
            'payment_pending' => false,
        ]);
        CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $myOrder->id,
            'quantity' => 1,
        ]);

        $mateOrder = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'amount' => 3,
            'payment_pending' => false,
        ], $tablemate);
        $sideOrder = $this->order([
            'parent_order_id' => $myOrder->id,
            'amount' => 3,
            'payment_pending' => false,
        ]);
        CartItem::create([
            'table_scan_session_id' => $tablemateSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $mateOrder->id,
            'quantity' => 1,
            'shared_order_ids' => [$sideOrder->id],
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        // Only the side order (my €3.30 gross share) is payable by me — not
        // the covered main order.
        $this->assertSame(330, $this->stripe->created[0]['amountMinor']);
        $coveredIds = OrderPayment::where('stripe_payment_intent_id', $response->json('paymentIntentId'))
            ->first()
            ->orders()
            ->pluck('orders.id')
            ->all();
        $this->assertSame([$sideOrder->id], $coveredIds);
    }

    public function test_unshare_deletes_empty_side_order(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();

        $myOrder = $this->order([
            'paid_by' => $tablemate->id,
            'payment_pending' => false,
        ]);
        $mateOrder = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'amount' => 3,
            'payment_pending' => false,
        ], $tablemate);
        $sideOrder = $this->order([
            'parent_order_id' => $myOrder->id,
            'amount' => 3,
            'payment_pending' => false,
        ]);
        $sharedItem = CartItem::create([
            'table_scan_session_id' => $tablemateSession->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $mateOrder->id,
            'quantity' => 1,
            'shared_order_ids' => [$sideOrder->id],
        ]);

        $this->withHeaders($this->headers)
            ->putJson("/api/customer/table/order/update/{$sideOrder->id}", [
                'unshared_item' => $sharedItem->id,
            ])
            ->assertOk();

        $this->assertSame([], array_map('intval', $sharedItem->fresh()->shared_order_ids ?? []));
        $this->assertDatabaseMissing('orders', ['id' => $sideOrder->id]);
        $this->assertSame(6.6, (float) $mateOrder->fresh()->amount);
    }

    public function test_active_intent_probe_reflects_intent_lifecycle(): void
    {
        $this->order(['payment_pending' => false]);

        // No intent yet.
        $this->withHeaders($this->headers)
            ->getJson('/api/customer/payments/intent')
            ->assertOk()
            ->assertJsonPath('active', false);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        $this->withHeaders($this->headers)
            ->getJson('/api/customer/payments/intent')
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('paymentIntentId', 'pi_fake_1')
            ->assertJsonPath('clientSecret', 'pi_fake_1_secret_test');

        $this->withHeaders($this->headers)
            ->deleteJson('/api/customer/payments/intent')
            ->assertOk()
            ->assertJsonPath('canceled', true);

        $this->withHeaders($this->headers)
            ->getJson('/api/customer/payments/intent')
            ->assertOk()
            ->assertJsonPath('active', false);
    }

    public function test_cancel_intent_cancels_own_intent_resets_pending_and_notifies(): void
    {
        [$tablemate] = $this->tablemate();
        $order = $this->order(['payment_pending' => false]);

        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk()
            ->assertJsonPath('state_patch.operation', 'payment.initiated');

        $this->assertTrue($order->fresh()->payment_pending);

        $createPatch = $createResponse->json('state_patch');
        $pendingOrderPatch = collect($createPatch['orders']['upsert'])
            ->firstWhere('id', $order->id);
        $this->assertTrue($pendingOrderPatch['payment_pending']);
        $this->assertFalse($pendingOrderPatch['payment_received']);

        $initiatedNotification = Notification::where('customer_id', $tablemate->id)
            ->where('event', 'payment_updated')
            ->get()
            ->first(fn (Notification $notification) => ($notification->metadata['template'] ?? null) === 'payment.initiated');
        $this->assertNotNull($initiatedNotification);
        $this->assertSame($createPatch, $initiatedNotification->metadata['state_patch']);

        $cancelResponse = $this->withHeaders($this->headers)
            ->deleteJson('/api/customer/payments/intent')
            ->assertOk()
            ->assertJsonPath('canceled', true)
            ->assertJsonPath('state_patch.operation', 'payment.canceled');

        $this->assertContains('pi_fake_1', $this->stripe->canceled);
        $this->assertDatabaseHas('order_payments', [
            'stripe_payment_intent_id' => 'pi_fake_1',
            'status' => 'canceled',
        ]);
        $this->assertFalse($order->fresh()->payment_pending);

        // The unlock is broadcast to the table.
        $canceledNotification = Notification::where('customer_id', $tablemate->id)
            ->where('event', 'payment_updated')
            ->get()
            ->first(fn (Notification $n) => str_contains((string) $n->getRawOriginal('metadata'), 'payment.canceled'));
        $this->assertNotNull($canceledNotification);

        $cancelPatch = $cancelResponse->json('state_patch');
        $unlockedOrderPatch = collect($cancelPatch['orders']['upsert'])
            ->firstWhere('id', $order->id);
        $this->assertFalse($unlockedOrderPatch['payment_pending']);
        $this->assertFalse($unlockedOrderPatch['payment_received']);
        $this->assertSame($cancelPatch, $canceledNotification->metadata['state_patch']);

        // Idempotent when nothing remains to cancel.
        $this->withHeaders($this->headers)
            ->deleteJson('/api/customer/payments/intent')
            ->assertOk()
            ->assertJsonPath('canceled', false);
    }

    public function test_cancel_intent_is_blocked_while_a_charge_is_processing(): void
    {
        $order = $this->order(['payment_pending' => false]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        OrderPayment::where('stripe_payment_intent_id', 'pi_fake_1')
            ->update(['status' => 'processing']);

        $this->withHeaders($this->headers)
            ->deleteJson('/api/customer/payments/intent')
            ->assertStatus(409);

        $this->assertNotContains('pi_fake_1', $this->stripe->canceled);
        $this->assertDatabaseHas('order_payments', [
            'stripe_payment_intent_id' => 'pi_fake_1',
            'status' => 'processing',
        ]);
        $this->assertTrue($order->fresh()->payment_pending);
    }

    public function test_pay_for_is_locked_while_the_owner_checkout_is_open(): void
    {
        [$tablemate] = $this->tablemate();
        $myOrder = $this->order(['payment_pending' => false]);

        // I open the checkout for my own order.
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        $mateToken = $tablemate->createToken('test', ['role:customer'])->plainTextToken;
        $mateHeaders = ['Authorization' => "Bearer {$mateToken}", 'Accept' => 'application/json'];

        $this->app['auth']->forgetGuards();

        $this->withHeaders($mateHeaders)
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => $myOrder->order_public_id,
            ])
            ->assertStatus(409);

        $this->assertNull($myOrder->fresh()->paid_by);

        // Once I cancel (checkout "back"), the order unlocks.
        $this->app['auth']->forgetGuards();
        $this->withHeaders($this->headers)
            ->deleteJson('/api/customer/payments/intent')
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withHeaders($mateHeaders)
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => $myOrder->order_public_id,
            ])
            ->assertOk();

        $this->assertSame($tablemate->id, $myOrder->fresh()->paid_by);
    }

    public function test_share_and_unshare_are_locked_while_a_checkout_covers_the_order(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();

        $myOrder = $this->order(['payment_pending' => false]);
        $mateOrder = $this->order([
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);

        // The tablemate already shares my first item; my second is unshared.
        $sharedItem = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $myOrder->id,
            'quantity' => 1,
            'shared_order_ids' => [$mateOrder->id],
        ]);
        $unsharedItem = CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $this->menuItem->id,
            'order_id' => $myOrder->id,
            'quantity' => 1,
        ]);

        // I open the checkout — my order (and its amounts) are now locked.
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        $mateToken = $tablemate->createToken('test', ['role:customer'])->plainTextToken;
        $mateHeaders = ['Authorization' => "Bearer {$mateToken}", 'Accept' => 'application/json'];

        $this->app['auth']->forgetGuards();

        $this->withHeaders($mateHeaders)
            ->putJson("/api/customer/table/order/update/{$mateOrder->id}", [
                'unshared_item' => $sharedItem->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'These items are locked while a payment is in progress.');

        $this->withHeaders($mateHeaders)
            ->putJson("/api/customer/table/order/update/{$mateOrder->id}", [
                'shared_item' => $unsharedItem->id,
            ])
            ->assertStatus(409);

        $this->assertSame([$mateOrder->id], array_map('intval', $sharedItem->fresh()->shared_order_ids));
        $this->assertSame([], array_map('intval', $unsharedItem->fresh()->shared_order_ids ?? []));

        // Cancel (back from checkout) → sharing is possible again.
        $this->app['auth']->forgetGuards();
        $this->withHeaders($this->headers)
            ->deleteJson('/api/customer/payments/intent')
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withHeaders($mateHeaders)
            ->putJson("/api/customer/table/order/update/{$mateOrder->id}", [
                'unshared_item' => $sharedItem->id,
            ])
            ->assertOk();

        $this->assertSame([], array_map('intval', $sharedItem->fresh()->shared_order_ids ?? []));
    }

    public function test_create_intent_replaces_stale_intent_when_payable_set_changes(): void
    {
        [$tablemate, $tablemateSession] = $this->tablemate();
        $ownOrder = $this->order(['amount' => 6, 'payment_pending' => false]);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk()
            ->assertJsonPath('paymentIntentId', 'pi_fake_1');

        $coveredOrder = $this->order([
            'amount' => 7,
            'table_scan_session_id' => $tablemateSession->id,
            'payment_pending' => false,
        ], $tablemate);

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => $coveredOrder->order_public_id,
            ])
            ->assertOk();

        // The payable set grew after the first intent — a fresh intent must
        // cover both orders instead of silently reusing the stale one.
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk()
            ->assertJsonPath('paymentIntentId', 'pi_fake_2');

        $this->assertContains('pi_fake_1', $this->stripe->canceled);
        $this->assertSame(1300, $this->stripe->created[1]['amountMinor']);

        $payment = OrderPayment::where('stripe_payment_intent_id', 'pi_fake_2')->firstOrFail();
        $this->assertCount(2, $payment->orders);
    }

    public function test_verify_success_notifies_table_customers_once(): void
    {
        [$tablemate] = $this->tablemate();
        $order = $this->order(['payment_pending' => false]);
        $this->payment($order, 'pi_paid');
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
            ->assertJsonPath('status', 'succeeded');

        $completed = Notification::where('event', 'payment_updated')
            ->whereNotNull('customer_id')
            ->get()
            ->filter(fn (Notification $notification) => ($notification->metadata['template'] ?? null) === 'payment.completed');
        $this->assertCount(2, $completed);
        $this->assertEqualsCanonicalizing(
            [$this->customer->id, $tablemate->id],
            $completed->pluck('customer_id')->all(),
        );
        $this->assertNotEmpty($completed->first()->metadata['order_snapshots'] ?? []);
        $this->assertTrue((bool) ($completed->first()->metadata['order_snapshots'][0]['payment_received'] ?? false));

        // A late webhook for the same intent must not broadcast again.
        $this->stripe->events[] = [
            'type' => 'payment_intent.succeeded',
            'payment_intent' => $this->stripe->intents['pi_paid'],
        ];
        $this->postJson('/api/customer/payments/webhook', [], [
            'Stripe-Signature' => 'valid',
        ])->assertOk();

        $completedAfter = Notification::where('event', 'payment_updated')
            ->whereNotNull('customer_id')
            ->get()
            ->filter(fn (Notification $notification) => ($notification->metadata['template'] ?? null) === 'payment.completed');
        $this->assertCount(2, $completedAfter);
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
                'payment_method_details' => [
                    'provider' => 'stripe',
                    'method' => 'visa',
                    'type' => 'card',
                    'display_name' => 'Visa',
                    'card_brand' => 'visa',
                    'card_last4' => '6537',
                    'masked_card' => '**** **** **** 6537',
                    'wallet_type' => null,
                ],
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

    public function test_receipts_list_returns_only_completed_payments(): void
    {
        $paidOrder = $this->order(['payment_pending' => false]);
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        $this->stripe->events[] = [
            'type' => 'payment_intent.succeeded',
            'payment_intent' => [
                'id' => 'pi_fake_1',
                'client_secret' => null,
                'status' => 'succeeded',
                'metadata' => [
                    'order_id' => (string) $paidOrder->id,
                    'customer_id' => (string) $this->customer->id,
                ],
                'payment_method' => 'pm_card_visa',
                'payment_method_details' => [
                    'provider' => 'stripe',
                    'method' => 'visa',
                    'type' => 'card',
                    'display_name' => 'Visa',
                    'card_brand' => 'visa',
                    'card_last4' => '6537',
                    'masked_card' => '**** **** **** 6537',
                    'wallet_type' => null,
                ],
            ],
        ];
        $this->postJson('/api/customer/payments/webhook', [], [
            'Stripe-Signature' => 'valid',
        ])->assertOk();

        $pendingOrder = $this->order(['payment_pending' => false]);
        $this->payment($pendingOrder, 'pi_still_processing');

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/customer/receipts')
            ->assertOk()
            ->assertJsonPath('receipts_count', 1)
            ->assertJsonPath('receipts.0.payment_intent_id', 'pi_fake_1')
            ->assertJsonPath('receipts.0.payment_provider', 'stripe')
            ->assertJsonPath('receipts.0.payment_method', 'visa')
            ->assertJsonPath('receipts.0.payment_method_details.display_name', 'Visa')
            ->assertJsonPath('receipts.0.payment_method_details.masked_card', '**** **** **** 6537')
            ->assertJsonPath('receipts.0.status', 'succeeded')
            ->assertJsonPath('receipts.0.orders_count', 1)
            ->assertJsonPath('receipts.0.orders.0.order_public_id', $paidOrder->order_public_id);

        $this->assertNotNull($response->json('receipts.0.paid_at'));
    }

    public function test_receipt_show_returns_every_order_paid_by_the_intent(): void
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
                'order_id' => $coveredOrder->order_public_id,
            ])
            ->assertOk();

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/create-intent')
            ->assertOk();

        $this->stripe->events[] = [
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
                'payment_method_details' => [
                    'provider' => 'stripe',
                    'method' => 'google_pay',
                    'type' => 'card',
                    'display_name' => 'Google Pay',
                    'card_brand' => 'visa',
                    'card_last4' => '4242',
                    'masked_card' => '**** **** **** 4242',
                    'wallet_type' => 'google_pay',
                ],
            ],
        ];
        $this->postJson('/api/customer/payments/webhook', [], [
            'Stripe-Signature' => 'valid',
        ])->assertOk();

        $payment = OrderPayment::where('stripe_payment_intent_id', 'pi_fake_1')->firstOrFail();

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/customer/receipts/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('data.receipt.receipt_id', $payment->id)
            ->assertJsonPath('data.payment.status', 'CONFIRMED')
            ->assertJsonPath('data.payment.provider', 'stripe')
            ->assertJsonPath('data.payment.method', 'google_pay')
            ->assertJsonPath('data.payment.method_details.display_name', 'Google Pay')
            ->assertJsonPath('data.payment.method_details.masked_card', '**** **** **** 4242')
            ->assertJsonPath('data.payment.transaction_id', 'pi_fake_1')
            ->assertJsonPath('data.totals.amount_charged', 13)
            ->assertJsonCount(2, 'data.orders');

        $this->assertEqualsCanonicalizing(
            [$ownOrder->order_public_id, $coveredOrder->order_public_id],
            $response->json('data.receipt.order_ids'),
        );
        $this->assertSame(
            $this->customer->id,
            collect($response->json('data.orders'))
                ->firstWhere('order_id', $coveredOrder->order_public_id)['paid_by']['id'],
        );
    }

    public function test_payer_without_an_own_order_can_view_receipt_and_track_the_paid_order(): void
    {
        [$owner, $ownerSession] = $this->tablemate();
        $order = $this->order([
            'amount' => 7,
            'table_scan_session_id' => $ownerSession->id,
            'payment_pending' => false,
        ], $owner);

        $this->postJson('/api/customer/payments/pay-for', [
            'order_id' => $order->order_public_id,
        ], $this->headers)->assertOk();

        $this->postJson('/api/customer/payments/create-intent', [], $this->headers)
            ->assertOk()
            ->assertJsonPath('paymentIntentId', 'pi_fake_1');

        $payment = OrderPayment::where('stripe_payment_intent_id', 'pi_fake_1')->firstOrFail();
        $this->stripe->events[] = [
            'type' => 'payment_intent.succeeded',
            'payment_intent' => [
                'id' => 'pi_fake_1',
                'client_secret' => null,
                'status' => 'succeeded',
                'metadata' => [
                    'order_id' => (string) $payment->order_id,
                    'customer_id' => (string) $this->customer->id,
                ],
                'payment_method' => 'pm_card_visa',
            ],
        ];

        $this->postJson('/api/customer/payments/webhook', [], [
            'Stripe-Signature' => 'valid',
        ])->assertOk();

        $this->assertTrue($order->fresh()->payment_received);
        $this->assertSame($this->customer->id, $order->fresh()->paid_by);

        $this->getJson('/api/customer/receipts', $this->headers)
            ->assertOk()
            ->assertJsonPath('receipts_count', 1)
            ->assertJsonPath('receipts.0.receipt_id', $payment->id)
            ->assertJsonPath('receipts.0.orders.0.order_public_id', $order->order_public_id);

        $this->getJson("/api/customer/receipts/{$payment->id}", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.receipt.order_ids.0', $order->order_public_id);

        $this->getJson("/api/customer/orders/{$order->order_public_id}/receipt", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.receipt.order_id', $order->order_public_id)
            ->assertJsonPath('data.order.paid_by.id', $this->customer->id);

        $this->getJson("/api/customer/orders/{$order->order_public_id}/tracking", $this->headers)
            ->assertOk()
            ->assertJsonPath('order_public_id', $order->order_public_id)
            ->assertJsonPath('can_view_receipt', true)
            ->assertJsonPath('paid_by.id', $this->customer->id);

        $this->getJson("/api/customer/orders/{$order->order_public_id}", $this->headers)
            ->assertOk()
            ->assertJsonPath('order_public_id', $order->order_public_id)
            ->assertJsonPath('can_view_receipt', true)
            ->assertJsonPath('paid_by.id', $this->customer->id);

        $ownerToken = $owner->createToken('owner-history', ['role:customer'])->plainTextToken;
        $ownerHeaders = [
            'Authorization' => "Bearer {$ownerToken}",
            'Accept' => 'application/json',
        ];
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/customer/orders/history', $ownerHeaders)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('history.0.orders.0.order_public_id', $order->order_public_id);

        $this->getJson('/api/customer/receipts', $ownerHeaders)
            ->assertOk()
            ->assertJsonPath('receipts_count', 0);

        $this->getJson("/api/customer/orders/{$order->order_public_id}/receipt", $ownerHeaders)
            ->assertNotFound();

        $this->getJson("/api/customer/orders/{$order->order_public_id}/tracking", $ownerHeaders)
            ->assertOk()
            ->assertJsonPath('order_public_id', $order->order_public_id)
            ->assertJsonPath('can_view_receipt', false);

        $this->getJson("/api/customer/orders/{$order->order_public_id}", $ownerHeaders)
            ->assertOk()
            ->assertJsonPath('order_public_id', $order->order_public_id)
            ->assertJsonPath('can_view_receipt', false);
    }

    public function test_receipt_show_rejects_incomplete_or_foreign_payments(): void
    {
        $order = $this->order(['payment_pending' => false]);
        $processing = $this->payment($order, 'pi_incomplete');

        $this->withHeaders($this->headers)
            ->getJson("/api/customer/receipts/{$processing->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Receipt is only available for completed payments.');

        $other = Customer::factory()->create();
        $foreign = OrderPayment::create([
            'order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'customer_id' => $other->id,
            'table_scan_session_id' => $order->table_scan_session_id,
            'stripe_account_id' => 'acct_test_123',
            'stripe_payment_intent_id' => 'pi_foreign_receipt',
            'amount' => 6,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'paid_at' => now(),
            'metadata' => [],
        ]);

        $this->withHeaders($this->headers)
            ->getJson("/api/customer/receipts/{$foreign->id}")
            ->assertNotFound();
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
                'order_id' => $coveredOrder->order_public_id,
            ])
            ->assertOk();

        $this->withHeaders($this->headers)
            ->getJson('/api/customer/orders/history')
            ->assertOk()
            ->assertJsonPath('pagination.total', 0);

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
            ->deleteJson("/api/customer/payments/pay-for/{$order->order_public_id}")
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

    public function test_assignment_rejects_own_order_and_nonexistent_order(): void
    {
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_id');

        $ownOrder = $this->order(['payment_pending' => false]);
        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => $ownOrder->order_public_id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_id');

        $this->withHeaders($this->headers)
            ->postJson('/api/customer/payments/pay-for', [
                'order_id' => 'ord-nonexistent',
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
            'order_public_id' => 'ord-'.uniqid(),
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

    public array $canceled = [];

    public array $intents = [];

    public array $events = [];

    public bool $rejectWebhook = false;

    public function __construct() {}

    public function createPaymentIntent(int $amountMinor, string $currency, string $stripeAccountId, array $metadata): array
    {
        $id = 'pi_fake_'.(count($this->created) + 1);
        $payload = [
            'id' => $id,
            'client_secret' => $id.'_secret_test',
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
            'client_secret' => $paymentIntentId.'_secret_test',
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

    public function retrievePaymentMethodDetails(string $paymentMethodId): ?array
    {
        return null;
    }

    public function cancelPaymentIntent(string $paymentIntentId): array
    {
        $payload = $this->intents[$paymentIntentId] ?? [
            'id' => $paymentIntentId,
            'client_secret' => null,
            'status' => 'requires_payment_method',
            'metadata' => [],
            'payment_method' => null,
        ];

        $payload['status'] = 'canceled';
        $this->canceled[] = $paymentIntentId;
        $this->intents[$paymentIntentId] = $payload;

        return $payload;
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
