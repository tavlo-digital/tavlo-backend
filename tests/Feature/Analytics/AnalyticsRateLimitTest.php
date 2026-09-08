<?php

namespace Tests\Feature\Analytics;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * Analytics/Financial-Reports read endpoints rebuild their full payload from
 * scratch on every call (no caching layer) — see the "analytics" limiter in
 * AppServiceProvider::configureRateLimiting(). This just proves the throttle
 * is actually wired to the routes, not that the limiter closure itself works
 * (Laravel's own tests cover RateLimiter/ThrottleRequests).
 */
class AnalyticsRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    protected function tearDown(): void
    {
        RateLimiter::clear('analytics');
        parent::tearDown();
    }

    public function test_analytics_index_is_throttled_per_vendor(): void
    {
        $vendor = $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
        $token = $vendor->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

        for ($i = 0; $i < 30; $i++) {
            $this->getJson("/api/vendor/{$vendor->vendor_public_id}/analytics", $headers)->assertOk();
        }

        $this->getJson("/api/vendor/{$vendor->vendor_public_id}/analytics", $headers)
            ->assertStatus(429);
    }

    public function test_analytics_throttle_bucket_is_per_vendor_not_shared(): void
    {
        $vendorA = $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
        $vendorB = $this->withAnalyticsAccess(Vendor::factory()->create(['country' => 'AT']));
        $headersA = ['Authorization' => 'Bearer ' . $vendorA->createToken('test')->plainTextToken, 'Accept' => 'application/json'];
        $headersB = ['Authorization' => 'Bearer ' . $vendorB->createToken('test')->plainTextToken, 'Accept' => 'application/json'];

        for ($i = 0; $i < 30; $i++) {
            $this->getJson("/api/vendor/{$vendorA->vendor_public_id}/analytics", $headersA)->assertOk();
        }
        $this->getJson("/api/vendor/{$vendorA->vendor_public_id}/analytics", $headersA)->assertStatus(429);

        // Sanctum's guard caches the resolved user for the lifetime of the
        // guard instance, which — only in this single-process test — outlives
        // one simulated request. A real request always boots a fresh app, so
        // this reset has no production equivalent; it just undoes a
        // test-harness artifact so vendor B's token resolves fresh.
        Auth::forgetGuards();

        // Vendor B's own bucket is untouched by vendor A exhausting theirs.
        $this->getJson("/api/vendor/{$vendorB->vendor_public_id}/analytics", $headersB)->assertOk();
    }

    // test_financial_reports_shares_the_same_analytics_bucket_as_analytics_index
    // lives in tests/Feature/FinancialReports/ instead — it exercises the
    // /financial-reports route, which this branch (feat/analytics) doesn't
    // have; feat/financial-report depends on this branch, not the reverse.
}
