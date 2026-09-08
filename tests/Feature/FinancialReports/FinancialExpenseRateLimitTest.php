<?php

namespace Tests\Feature\FinancialReports;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * Financial expense create/update/delete/upload-attachment were previously
 * unthrottled entirely (2026-09-07 audit finding) — see the
 * "financial-expenses" limiter in AppServiceProvider::configureRateLimiting().
 * This just proves the throttle is actually wired to the routes, not that the
 * limiter closure itself works (Laravel's own tests cover RateLimiter/
 * ThrottleRequests).
 */
class FinancialExpenseRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    protected function tearDown(): void
    {
        RateLimiter::clear('financial-expenses');
        RateLimiter::clear('analytics');
        parent::tearDown();
    }

    public function test_financial_expense_store_is_throttled_per_vendor(): void
    {
        $vendor = $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
        $token = $vendor->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        for ($i = 0; $i < 20; $i++) {
            $this->postJson("/api/vendor/{$vendor->vendor_public_id}/financial-expenses", [
                'name' => "Expense {$i}",
                'category' => 'rent',
                'amount' => 100,
                'occurredAt' => now()->toDateString(),
            ], $headers)->assertCreated();
        }

        $this->postJson("/api/vendor/{$vendor->vendor_public_id}/financial-expenses", [
            'name' => 'One too many',
            'category' => 'rent',
            'amount' => 100,
            'occurredAt' => now()->toDateString(),
        ], $headers)->assertStatus(429);
    }

    public function test_financial_expense_bucket_is_independent_of_the_analytics_bucket(): void
    {
        $vendor = $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
        $token = $vendor->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        for ($i = 0; $i < 20; $i++) {
            $this->postJson("/api/vendor/{$vendor->vendor_public_id}/financial-expenses", [
                'name' => "Expense {$i}",
                'category' => 'rent',
                'amount' => 100,
                'occurredAt' => now()->toDateString(),
            ], $headers)->assertCreated();
        }
        $this->postJson("/api/vendor/{$vendor->vendor_public_id}/financial-expenses", [
            'name' => 'One too many',
            'category' => 'rent',
            'amount' => 100,
            'occurredAt' => now()->toDateString(),
        ], $headers)->assertStatus(429);

        // Exhausting the write bucket must not also block the unrelated read
        // ("analytics") bucket the Financial Reports index route uses.
        $this->getJson("/api/vendor/{$vendor->vendor_public_id}/financial-reports", $headers)
            ->assertOk();
    }

    /**
     * Inverse of the test above: financial-reports.index runs through the
     * `throttle:analytics` group in routes/api/vendor.php (not its own
     * bucket), so exhausting analytics — moved here from
     * Tests\Feature\Analytics\AnalyticsRateLimitTest since it exercises the
     * financial-reports route, which feat/analytics alone doesn't have —
     * blocks this route too.
     */
    public function test_financial_reports_shares_the_same_analytics_bucket_as_analytics_index(): void
    {
        $vendor = $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
        $token = $vendor->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        for ($i = 0; $i < 30; $i++) {
            $this->getJson("/api/vendor/{$vendor->vendor_public_id}/analytics", $headers)->assertOk();
        }

        $this->getJson("/api/vendor/{$vendor->vendor_public_id}/financial-reports", $headers)
            ->assertStatus(429);
    }
}
