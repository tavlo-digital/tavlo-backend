<?php

namespace Tests\Feature\Analytics;

use App\Models\Customer;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * Covers AnalyticsController's plan-gate: every route requires the vendor's
 * *current* plan to carry the "Basic Analytics" feature (checked live
 * against plan_features — never a hardcoded plan name/tier, since which
 * plan(s) carry it is admin-configurable). A vendor without it gets a
 * reduced "locked" response instead of the full payload; the main dashboard
 * endpoint additionally carries a small, real "today" preview so the
 * frontend can show a couple of honest numbers behind the upgrade prompt.
 */
class AnalyticsFeatureGateTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    public function test_index_is_locked_for_a_vendor_with_no_subscription_at_all(): void
    {
        $vendor = $this->vendorWithoutAccess();

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertTrue($body['locked']);
        $this->assertEquals('Basic Analytics', $body['requiredFeature']);
        $this->assertArrayHasKey('preview', $body);
        $this->assertArrayNotHasKey('summary', $body);
    }

    public function test_index_is_locked_for_a_vendor_whose_current_plan_lacks_the_feature(): void
    {
        // The realistic case this is actually for: not a vendor with zero
        // subscription, but one actively subscribed to a real plan that the
        // admin simply hasn't attached "Basic Analytics" to.
        $vendor = Vendor::factory()->create(['country' => 'AT']);
        $plan = SubscriptionPlan::create([
            'name' => 'Starter (no analytics)',
            'monthly_price' => 49,
            'yearly_price' => 470,
            'max_users' => 1,
        ]);
        Subscription::create([
            'vendor_id' => $vendor->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'start_date' => now(),
            'next_billing_date' => now()->addMonth(),
        ]);

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertTrue($body['locked']);
    }

    public function test_index_preview_reports_todays_real_orders_and_revenue_while_locked(): void
    {
        $vendor = $this->vendorWithoutAccess();
        [$customer, $session] = $this->context($vendor);

        // Counted: paid, confirmed, not cancelled, today.
        $this->order($vendor, $customer, $session, [
            'amount' => 40, 'payment_received' => true, 'created_at' => now(),
        ]);
        // Counted in ordersToday but not revenue: confirmed and unpaid.
        $this->order($vendor, $customer, $session, [
            'amount' => 25, 'payment_received' => false, 'created_at' => now(),
        ]);
        // Excluded: a draft (never confirmed).
        $this->order($vendor, $customer, $session, [
            'amount' => 500, 'confirmed_at' => null, 'payment_received' => false, 'created_at' => now(),
        ]);
        // Excluded: outside today.
        $this->order($vendor, $customer, $session, [
            'amount' => 999, 'payment_received' => true, 'created_at' => now()->subDays(3),
        ]);

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertEquals(2, $body['preview']['ordersToday']);
        $this->assertEquals(40.0, $body['preview']['grossRevenueToday']);
    }

    public function test_index_returns_the_full_payload_once_the_plan_includes_the_feature(): void
    {
        $vendor = $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertArrayNotHasKey('locked', $body);
        $this->assertArrayHasKey('summary', $body);
    }

    public function test_insights_ask_suggested_questions_and_forecast_are_all_locked_the_same_way(): void
    {
        $vendor = $this->vendorWithoutAccess();
        $headers = $this->headers($vendor);

        $insights = $this->getJson("/api/vendor/{$vendor->vendor_public_id}/analytics/insights", $headers)
            ->assertOk()->json();
        $this->assertTrue($insights['locked']);
        $this->assertSame([], $insights['insights']);

        $ask = $this->postJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/insights/ask",
            ['question' => 'How am I doing?'],
            $headers,
        )->assertOk()->json();
        $this->assertTrue($ask['locked']);
        $this->assertFalse($ask['matched']);

        $suggested = $this->getJson("/api/vendor/{$vendor->vendor_public_id}/analytics/insights/suggested-questions", $headers)
            ->assertOk()->json();
        $this->assertTrue($suggested['locked']);
        $this->assertSame([], $suggested['questions']);

        $forecast = $this->getJson("/api/vendor/{$vendor->vendor_public_id}/analytics/forecast", $headers)
            ->assertOk()->json();
        $this->assertTrue($forecast['locked']);
        $this->assertFalse($forecast['available']);
    }

    // -------------------------------------------------------------- helpers

    private function vendorWithoutAccess(): Vendor
    {
        // Guard against the trait's plan/feature rows existing from another
        // test in the same run and this vendor accidentally inheriting them
        // — they shouldn't, since nothing links this vendor to that plan,
        // but a vendor genuinely has no subscription row at all here.
        return Vendor::factory()->create(['country' => 'AT']);
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

    private function order(Vendor $vendor, Customer $customer, TableScanSession $session, array $attributes = [])
    {
        return \App\Models\Order::factory()->create(array_merge([
            'vendor_id' => $vendor->id,
            'customer_id' => $customer->id,
            'table_scan_session_id' => $session->id,
            'status' => 'served',
            'order_type' => 'dine_in',
            'amount' => 10,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'payment_received' => false,
            'confirmed_at' => now()->subMinutes(30),
            'created_at' => now()->subMinutes(30),
        ], $attributes));
    }

    private function headers(Vendor $vendor): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
