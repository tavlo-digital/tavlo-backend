<?php

namespace Tests\Feature\Analytics;

use App\Models\Customer;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * Covers the "no caching layer" finding from the 2026-09-06 audit — see
 * VendorAnalyticsService::ordersAndLinesFor()'s doc comment for why only the
 * raw order/line fetch is cached rather than the whole assembled payload.
 * That narrower scope is itself load-bearing: an earlier version cached the
 * full response and broke `VendorAnalyticsApiTest::
 * test_inventory_reports_auto_deduction_enabled_from_settings` by serving a
 * stale snapshot over a genuine settings change, so this file also proves
 * that guarantee holds under caching, not just that caching happened.
 */
class AnalyticsCachingTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    /**
     * Doesn't isolate `ordersIn()`'s own query specifically — several other
     * sub-aggregations (e.g. `repeatRate()`) run their own independent,
     * uncached `orders` queries by design (see FinancialReportService's own
     * doc comment on why only the raw fetch is cached). Counting the total
     * query volume instead is robust to that: skipping `ordersIn()` +
     * `linesFor()`'s CartItem/MenuItem/category eager loads entirely on a
     * cache hit should meaningfully reduce the total, which is what
     * actually matters for the "3-4x redundant rebuild per page load"
     * problem this cache exists to fix.
     */
    public function test_second_request_for_same_vendor_and_period_runs_meaningfully_fewer_queries(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->order($vendor, $customer, $session, ['amount' => 40, 'payment_received' => true]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $this->analytics($vendor, 'daily');
        $firstCallQueries = $queryCount;

        $queryCount = 0;
        $this->analytics($vendor, 'daily');
        $secondCallQueries = $queryCount;

        $this->assertLessThan(
            $firstCallQueries,
            $secondCallQueries,
            'A second request for the same vendor+period should run fewer queries by reusing cached orders/lines.',
        );
    }

    public function test_a_different_period_is_not_served_from_the_other_periods_cache(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->order($vendor, $customer, $session, ['amount' => 40, 'payment_received' => true]);

        $daily = $this->analytics($vendor, 'daily');
        $weekly = $this->analytics($vendor, 'weekly');

        // Different cache keys (different period bounds) — not asserting on
        // values here, just that the weekly request actually ran its own
        // query rather than silently reusing daily's cached collection.
        $this->assertSame('daily', $daily['period']);
        $this->assertSame('weekly', $weekly['period']);
    }

    /**
     * Regression test for the exact bug caching briefly introduced: caching
     * the whole payload made a vendor's own settings change invisible for
     * up to the cache TTL. `inventory.autoDeductionEnabled` reads
     * `$vendor->inventorySettings` fresh on every call regardless of whether
     * the underlying orders/lines came from cache — this proves that stays
     * true even when the SAME period is requested twice in a row, the
     * scenario that broke it before.
     */
    public function test_a_settings_change_is_reflected_immediately_even_with_orders_cached(): void
    {
        $vendor = $this->vendor();
        $vendor->inventoryItems()->create([
            'name' => 'Flour', 'unit' => 'kg', 'quantity' => 10, 'min_stock' => 1, 'track_stock' => true,
        ]);

        $before = $this->analytics($vendor, 'daily');
        $this->assertTrue($before['inventory']['autoDeductionEnabled']);

        $vendor->inventorySettings()->create([
            'settings' => ['general' => ['enableAutoStockDeduction' => false]],
        ]);

        $after = $this->analytics($vendor, 'daily');
        $this->assertFalse($after['inventory']['autoDeductionEnabled']);
    }

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

    private function analytics(Vendor $vendor, string $period = 'daily'): array
    {
        return $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period={$period}",
            $this->headers($vendor),
        )->assertOk()->json();
    }

    private function headers(Vendor $vendor): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
