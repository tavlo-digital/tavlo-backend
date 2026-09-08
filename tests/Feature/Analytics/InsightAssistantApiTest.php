<?php

namespace Tests\Feature\Analytics;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * HTTP-level coverage (routing, auth, validation) for the two new assistant
 * endpoints. The answer logic itself is covered directly against a crafted
 * payload in InsightQueryEngineTest — this file only proves the endpoints
 * are wired correctly and reach that engine with real trading data.
 */
class InsightAssistantApiTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    public function test_ask_answers_a_matched_question_from_real_orders(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, ['amount' => 40, 'payment_received' => true]);
        $this->order($vendor, $customer, $session, ['amount' => 60, 'payment_received' => true]);

        $response = $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights/ask",
            ['question' => 'How many orders did I get?', 'period' => 'daily'],
            $this->headers($vendor),
        );

        $response->assertOk()->assertJson([
            'period' => 'daily',
            'currency' => 'EUR',
            'matched' => true,
            'topic' => 'summary',
            'insightId' => null,
        ]);
        $this->assertSame(2, $response->json('data.orders'));
    }

    public function test_ask_falls_back_gracefully_for_an_unanswerable_question(): void
    {
        $vendor = $this->vendor();

        $response = $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights/ask",
            ['question' => 'zzz not a real question zzz'],
            $this->headers($vendor),
        );

        $response->assertOk()->assertJson(['matched' => false, 'topic' => null]);
        $this->assertNotEmpty($response->json('suggestedQuestions'));
    }

    public function test_ask_falls_back_when_the_insight_id_is_not_currently_active(): void
    {
        $vendor = $this->vendor();

        $response = $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights/ask",
            ['question' => 'why?', 'insightId' => 'not-a-real-id'],
            $this->headers($vendor),
        );

        $response->assertOk()->assertJson(['matched' => false, 'insightId' => 'not-a-real-id']);
    }

    public function test_ask_follows_up_on_a_real_active_insight(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // 25 settled payment attempts (InsightEngine::MIN_ORDERS), 5 of them
        // failed — clears both InsightEngine::paymentFailures()'s gate and
        // the overall derive() sample gate.
        for ($i = 0; $i < 20; $i++) {
            $order = $this->order($vendor, $customer, $session, ['amount' => 20, 'payment_received' => true]);
            $this->payment($vendor, $order, 'succeeded');
        }
        for ($i = 0; $i < 5; $i++) {
            $order = $this->order($vendor, $customer, $session, ['amount' => 20, 'payment_received' => false]);
            $this->payment($vendor, $order, 'failed');
        }

        $insights = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights?period=daily",
            $this->headers($vendor),
        )->assertOk()->json('insights');

        $paymentInsight = collect($insights)->firstWhere('id', 'payment-failures');
        $this->assertNotNull($paymentInsight, 'precondition: payment-failures insight must be active for this data');

        $response = $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights/ask",
            ['question' => 'What should I do about it?', 'insightId' => 'payment-failures', 'period' => 'daily'],
            $this->headers($vendor),
        );

        $response->assertOk()->assertJson([
            'matched' => true,
            'topic' => 'insight-followup',
            'insightId' => 'payment-failures',
            'answer' => $paymentInsight['suggestedAction'],
        ]);
    }

    public function test_ask_requires_authentication(): void
    {
        $vendor = $this->vendor();

        $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights/ask",
            ['question' => 'How am I doing?'],
        )->assertUnauthorized();
    }

    public function test_ask_rejects_a_request_for_another_vendors_data(): void
    {
        $vendor = $this->vendor();
        $other = $this->vendor();

        $this->postJson(
            "/api/vendor/{$other->vendor_public_id}/analytics/insights/ask",
            ['question' => 'How am I doing?'],
            $this->headers($vendor),
        )->assertForbidden();
    }

    public function test_ask_validates_the_question_field(): void
    {
        $vendor = $this->vendor();

        $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights/ask",
            [],
            $this->headers($vendor),
        )->assertUnprocessable()->assertJsonValidationErrors('question');
    }

    public function test_suggested_questions_endpoint_returns_currently_answerable_topics(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $this->order($vendor, $customer, $session, ['amount' => 40, 'payment_received' => true]);

        $response = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights/suggested-questions?period=daily",
            $this->headers($vendor),
        );

        $response->assertOk()->assertJson(['period' => 'daily', 'currency' => 'EUR']);
        $this->assertNotEmpty($response->json('questions'));
    }

    // ---------------------------------------------------------------- helpers

    private function vendor(): Vendor
    {
        return $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
    }

    /** @return array{0: Customer, 1: TableScanSession} */
    private function context(Vendor $vendor): array
    {
        $customer = Customer::factory()->create();
        $table = $vendor->restaurantTables()->create([
            'number' => 1,
            'name' => 'Table 1',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);

        $session = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '9001',
            'type' => 'dine_in',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        return [$customer, $session];
    }

    private function order(Vendor $vendor, ?Customer $customer, TableScanSession $session, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'vendor_id' => $vendor->id,
            'customer_id' => $customer?->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_SERVED,
            'order_type' => 'dine_in',
            'amount' => 10,
            'vat_amount' => 0,
            'service_fee' => 0,
            'tip_amount' => 0,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'payment_received' => false,
            'confirmed_at' => now()->subMinutes(30),
            'in_progress_at' => now()->subMinutes(28),
            'served_at' => now()->subMinutes(10),
            'payment_confirmed_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(30),
        ], $attributes));
    }

    private function payment(Vendor $vendor, Order $order, string $status): OrderPayment
    {
        return OrderPayment::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'stripe_account_id' => 'acct_test',
            'stripe_payment_intent_id' => 'pi_'.Str::random(20),
            'amount' => $order->amount,
            'currency' => 'EUR',
            'status' => $status,
            'payment_method' => 'card',
            'paid_at' => $status === 'succeeded' ? now()->subMinutes(5) : null,
            'failed_at' => $status === 'failed' ? now()->subMinutes(5) : null,
        ]);
    }

    private function headers(Vendor $vendor): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
