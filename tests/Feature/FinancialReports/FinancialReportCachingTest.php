<?php

namespace Tests\Feature\FinancialReports;

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
 * Covers the "no caching layer" finding from the 2026-09-06 audit for
 * FinancialReportService — mirrors AnalyticsCachingTest's coverage of
 * VendorAnalyticsService::ordersAndLinesFor(). Only the raw order/cart-item
 * fetch is cached (`ordersAndCartItemsFor()`); manual expenses, labor
 * shifts, refunds, and subscription invoices stay live/uncached since
 * they're vendor-editable and period-bounded rather than history-bounded.
 */
class FinancialReportCachingTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    public function test_second_request_for_same_vendor_and_period_runs_meaningfully_fewer_queries(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->order($vendor, $customer, $session, ['amount' => 40, 'payment_received' => true]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $this->report($vendor, 'week');
        $firstCallQueries = $queryCount;

        $queryCount = 0;
        $this->report($vendor, 'week');
        $secondCallQueries = $queryCount;

        $this->assertLessThan(
            $firstCallQueries,
            $secondCallQueries,
            'A second request for the same vendor+period should run fewer queries by reusing cached orders/cart items.',
        );
    }

    /**
     * A manual expense is vendor-editable and must show up right away, not
     * after the cache TTL — proves it does even when the SAME period's
     * orders/cart-items are being served from cache.
     */
    public function test_a_manual_expense_is_reflected_immediately_even_with_orders_cached(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->order($vendor, $customer, $session, ['amount' => 40, 'payment_received' => true]);

        $before = $this->report($vendor, 'week');
        $beforeCosts = $before['costs']['totalCosts'];

        $vendor->financialExpenses()->create([
            'name' => 'Monthly rent',
            'category' => 'rent',
            'amount' => 500,
            'occurred_at' => now(),
        ]);

        $after = $this->report($vendor, 'week');

        $this->assertGreaterThan($beforeCosts, $after['costs']['totalCosts']);
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
            'payment_confirmed_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(30),
        ], $attributes));
    }

    private function report(Vendor $vendor, string $period = 'week'): array
    {
        return $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period={$period}",
            $this->headers($vendor),
        )->assertOk()->json();
    }

    private function headers(Vendor $vendor): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
