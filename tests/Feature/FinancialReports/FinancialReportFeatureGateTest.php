<?php

namespace Tests\Feature\FinancialReports;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * Financial Reports (and Financial Expenses, built on top of it) previously
 * had no plan gating at all — a 2026-09-07 audit finding. Founder's decision
 * was to gate both behind the SAME "Basic Analytics" feature Analytics
 * already requires, rather than a second, separately-priced one — see
 * GatesAnalyticsFeature. Mirrors AnalyticsFeatureGateTest's coverage shape.
 */
class FinancialReportFeatureGateTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    public function test_index_is_locked_for_a_vendor_with_no_subscription_at_all(): void
    {
        $vendor = Vendor::factory()->create(['country' => 'AT']);

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertTrue($body['locked']);
        $this->assertEquals('Basic Analytics', $body['requiredFeature']);
        $this->assertArrayNotHasKey('summary', $body);
    }

    public function test_index_is_locked_for_a_vendor_whose_current_plan_lacks_the_feature(): void
    {
        // The realistic case: not a vendor with zero subscription, but one
        // actively subscribed to a real plan the admin simply hasn't
        // attached "Basic Analytics" to.
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
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertTrue($body['locked']);
    }

    public function test_index_returns_the_full_payload_once_the_plan_includes_the_feature(): void
    {
        $vendor = $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));

        $body = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports",
            $this->headers($vendor),
        )->assertOk()->json();

        $this->assertArrayNotHasKey('locked', $body);
        $this->assertArrayHasKey('summary', $body);
    }

    public function test_a_locked_vendor_never_triggers_the_background_job_threshold(): void
    {
        // A locked vendor requesting a huge period must not create a
        // FinancialReportJob row — the gate check runs before any of that
        // logic, so this proves it isn't merely masking the real payload
        // after doing the (expensive) threshold work anyway.
        config(['reports.async_order_threshold' => 0]);
        $vendor = Vendor::factory()->create(['country' => 'AT']);

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period=year",
            $this->headers($vendor),
        )->assertOk()->assertJson(['locked' => true]);

        $this->assertSame(0, \App\Models\FinancialReportJob::count());
    }

    public function test_financial_expense_writes_are_forbidden_without_the_feature(): void
    {
        $vendor = Vendor::factory()->create(['country' => 'AT']);
        $headers = $this->headers($vendor);

        $this->postJson("/api/vendor/{$vendor->vendor_public_id}/financial-expenses", [
            'name' => 'Rent', 'category' => 'rent', 'amount' => 100, 'occurredAt' => now()->toDateString(),
        ], $headers)->assertStatus(403);
    }

    public function test_financial_expense_writes_succeed_once_the_plan_includes_the_feature(): void
    {
        $vendor = $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
        $headers = $this->headers($vendor);

        $create = $this->postJson("/api/vendor/{$vendor->vendor_public_id}/financial-expenses", [
            'name' => 'Rent', 'category' => 'rent', 'amount' => 100, 'occurredAt' => now()->toDateString(),
        ], $headers)->assertCreated()->json();

        $this->putJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-expenses/{$create['data']['id']}",
            ['amount' => 150],
            $headers,
        )->assertOk();

        $this->deleteJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-expenses/{$create['data']['id']}",
            [],
            $headers,
        )->assertOk();
    }

    private function headers(Vendor $vendor): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
