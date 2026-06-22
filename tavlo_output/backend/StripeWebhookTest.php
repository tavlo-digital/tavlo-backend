<?php

namespace Tests\Feature\Customer;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ISSUE-018: Stripe webhook integration tests.
 * The webhook handler is the most critical financial code path — it marks orders as paid.
 * These tests must pass before any change to PaymentController::webhook().
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret_for_tests_only';

    private function buildStripeEvent(string $type, array $paymentIntentData): array
    {
        return [
            'id'   => 'evt_' . Str::random(16),
            'type' => $type,
            'data' => ['object' => array_merge(['object' => 'payment_intent'], $paymentIntentData)],
        ];
    }

    private function signedWebhookRequest(array $payload): array
    {
        $body      = json_encode($payload);
        $timestamp = time();
        $sig       = hash_hmac('sha256', "{$timestamp}.{$body}", $this->webhookSecret);
        $header    = "t={$timestamp},v1={$sig}";

        return ['body' => $body, 'header' => $header];
    }

    // ── Test 1: Happy path ─────────────────────────────────────────────────
    public function test_payment_succeeded_marks_order_paid(): void
    {
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);

        $vendor  = Vendor::factory()->create();
        $order   = Order::factory()->create([
            'vendor_id'       => $vendor->id,
            'payment_received' => false,
            'payment_pending'  => true,
        ]);
        $payment = OrderPayment::factory()->create([
            'order_id'                  => $order->id,
            'vendor_id'                 => $vendor->id,
            'stripe_payment_intent_id'  => 'pi_test_success_001',
            'status'                    => 'requires_payment_method',
        ]);

        $event = $this->buildStripeEvent('payment_intent.succeeded', [
            'id'       => 'pi_test_success_001',
            'status'   => 'succeeded',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        ['body' => $body, 'header' => $header] = $this->signedWebhookRequest($event);

        $response = $this->postJson('/api/customer/payments/webhook', json_decode($body, true), [
            'Stripe-Signature' => $header,
            'Content-Type'     => 'application/json',
        ]);

        $response->assertOk()->assertJson(['received' => true]);
        $this->assertDatabaseHas('orders', [
            'id'               => $order->id,
            'payment_received' => true,
            'payment_pending'  => false,
        ]);
        $this->assertDatabaseHas('order_payments', [
            'id'     => $payment->id,
            'status' => 'succeeded',
        ]);
    }

    // ── Test 2: Invalid signature → 400 ───────────────────────────────────
    public function test_invalid_signature_returns_400_and_order_unchanged(): void
    {
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);

        $order = Order::factory()->create(['payment_received' => false]);

        $event = $this->buildStripeEvent('payment_intent.succeeded', [
            'id'       => 'pi_bad_sig',
            'status'   => 'succeeded',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $response = $this->postJson('/api/customer/payments/webhook', $event, [
            'Stripe-Signature' => 't=9999999999,v1=invalidsignature',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_received' => false]);
    }

    // ── Test 3: payment_intent.payment_failed ─────────────────────────────
    public function test_payment_failed_updates_payment_row_status(): void
    {
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);

        $order   = Order::factory()->create(['payment_pending' => true, 'payment_received' => false]);
        $payment = OrderPayment::factory()->create([
            'order_id'                 => $order->id,
            'stripe_payment_intent_id' => 'pi_test_failed_001',
            'status'                   => 'requires_payment_method',
        ]);

        $event = $this->buildStripeEvent('payment_intent.payment_failed', [
            'id'     => 'pi_test_failed_001',
            'status' => 'requires_payment_method',
        ]);

        ['body' => $body, 'header' => $header] = $this->signedWebhookRequest($event);

        $response = $this->postJson('/api/customer/payments/webhook', json_decode($body, true), [
            'Stripe-Signature' => $header,
        ]);

        $response->assertOk()->assertJson(['received' => true]);
        $this->assertDatabaseHas('order_payments', [
            'id'     => $payment->id,
            'status' => 'payment_failed',
        ]);
        // Order should NOT be marked as paid
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_received' => false]);
    }

    // ── Test 4: Unknown payment intent → 200 (Stripe expects 200 for unknowns)
    public function test_unknown_payment_intent_returns_200_without_error(): void
    {
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);

        $event = $this->buildStripeEvent('payment_intent.succeeded', [
            'id'       => 'pi_completely_unknown',
            'status'   => 'succeeded',
            'metadata' => ['order_id' => '999999'],
        ]);

        ['body' => $body, 'header' => $header] = $this->signedWebhookRequest($event);

        $response = $this->postJson('/api/customer/payments/webhook', json_decode($body, true), [
            'Stripe-Signature' => $header,
        ]);

        // Must return 200 — Stripe will stop retrying on anything other than 2xx
        $response->assertOk();
    }

    // ── Test 5: Duplicate webhook delivery is idempotent ──────────────────
    public function test_duplicate_webhook_delivery_is_idempotent(): void
    {
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);

        $order = Order::factory()->create([
            'payment_received' => true,   // Already marked paid
            'payment_pending'  => false,
        ]);
        OrderPayment::factory()->create([
            'order_id'                 => $order->id,
            'stripe_payment_intent_id' => 'pi_already_done',
            'status'                   => 'succeeded',
        ]);

        $event = $this->buildStripeEvent('payment_intent.succeeded', [
            'id'       => 'pi_already_done',
            'status'   => 'succeeded',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        ['body' => $body, 'header' => $header] = $this->signedWebhookRequest($event);

        // Fire twice
        $this->postJson('/api/customer/payments/webhook', json_decode($body, true), ['Stripe-Signature' => $header])->assertOk();
        $this->postJson('/api/customer/payments/webhook', json_decode($body, true), ['Stripe-Signature' => $header])->assertOk();

        // Still only one payment row, still paid exactly once
        $this->assertDatabaseCount('order_payments', 1);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_received' => true]);
    }

    // ── Test 6: processing status does NOT mark order paid ────────────────
    public function test_processing_event_does_not_mark_order_paid(): void
    {
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);

        $order = Order::factory()->create(['payment_received' => false, 'payment_pending' => true]);
        OrderPayment::factory()->create([
            'order_id'                 => $order->id,
            'stripe_payment_intent_id' => 'pi_processing',
            'status'                   => 'processing',
        ]);

        $event = $this->buildStripeEvent('payment_intent.processing', [
            'id'       => 'pi_processing',
            'status'   => 'processing',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        ['body' => $body, 'header' => $header] = $this->signedWebhookRequest($event);

        $response = $this->postJson('/api/customer/payments/webhook', json_decode($body, true), [
            'Stripe-Signature' => $header,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_received' => false]);
    }
}
