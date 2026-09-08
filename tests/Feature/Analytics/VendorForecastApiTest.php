<?php

namespace Tests\Feature\Analytics;

use App\Models\Customer;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * Covers App\Services\Analytics\ForecastService via GET /analytics/forecast —
 * the honesty gate on insufficient history, the 8-week average/range/trend
 * maths, order-scoping (drafts/cancellations excluded, same as every other
 * analytics endpoint), and access control.
 */
class VendorForecastApiTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_forecast_is_unavailable_with_less_than_eight_weeks_of_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:00:00', 'Europe/Vienna'));

        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        // First order only 3 weeks ago — well short of the 8-week minimum.
        $this->order($vendor, $customer, $session, [
            'amount' => 20,
            'payment_received' => true,
            'created_at' => now()->subWeeks(3),
            'confirmed_at' => now()->subWeeks(3),
        ]);

        $body = $this->forecast($vendor);

        $this->assertFalse($body['available']);
        $this->assertEquals(3, $body['weeksOfHistory']);
        $this->assertEquals(8, $body['minWeeksRequired']);
        $this->assertArrayNotHasKey('projection', $body);
    }

    public function test_forecast_is_unavailable_with_zero_orders_ever(): void
    {
        $vendor = $this->vendor();

        $body = $this->forecast($vendor);

        $this->assertFalse($body['available']);
        $this->assertEquals(0, $body['weeksOfHistory']);
    }

    public function test_forecast_averages_eight_flat_weeks_and_reads_current_week_pace(): void
    {
        // A Wednesday, so the current week is 3 days in (Mon/Tue/Wed elapsed).
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:00:00', 'Europe/Vienna'));

        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $currentWeekStart = now()->startOfWeek();
        $windowStart = $currentWeekStart->copy()->subWeeks(8);

        // Exactly 10 orders on the Wednesday of each of the 8 completed
        // weeks — a flat, zero-variance history so the projection, range,
        // and confidence are all exactly predictable.
        for ($week = 0; $week < 8; $week++) {
            $orderDay = $windowStart->copy()->addWeeks($week)->addDays(2); // Wednesday
            for ($i = 0; $i < 10; $i++) {
                $this->order($vendor, $customer, $session, [
                    'amount' => 20,
                    'payment_received' => true,
                    'created_at' => $orderDay->copy()->addMinutes($i),
                    'confirmed_at' => $orderDay->copy()->addMinutes($i),
                ]);
            }
        }

        // Current (partial) week: 4 orders so far, on Monday.
        for ($i = 0; $i < 4; $i++) {
            $this->order($vendor, $customer, $session, [
                'amount' => 15,
                'payment_received' => true,
                'created_at' => $currentWeekStart->copy()->addMinutes($i),
                'confirmed_at' => $currentWeekStart->copy()->addMinutes($i),
            ]);
        }

        $body = $this->forecast($vendor);

        $this->assertTrue($body['available']);
        $this->assertEquals(8, $body['weeksOfHistory']);
        $this->assertCount(8, $body['history']);
        $this->assertEquals(10, $body['history'][0]['orders']);
        $this->assertEquals(200.0, $body['history'][0]['revenue']);

        // Flat history: average = 10, stddev = 0, so the range collapses to
        // a single point and confidence is the highest tier.
        $this->assertEquals(10, $body['projection']['projectedOrders']);
        $this->assertEquals(10, $body['projection']['projectedOrdersLow']);
        $this->assertEquals(10, $body['projection']['projectedOrdersHigh']);
        $this->assertEquals(10.0, $body['projection']['avgOrdersPerWeek']);
        $this->assertEquals(200.0, $body['projection']['avgRevenuePerWeek']);
        $this->assertEquals('high', $body['projection']['confidence']);
        $this->assertEquals('flat', $body['projection']['trend']['direction']);
        $this->assertEquals(0.0, $body['projection']['trend']['changePercent']);

        // Every historical week's 10 orders landed on the Wednesday, within
        // the 8 weeks' "through Wednesday" window, so expectedByNow = 10.
        // Only 4 real orders landed in the current week so far.
        $this->assertEquals(3, $body['currentWeek']['daysElapsed']);
        $this->assertEquals(4, $body['currentWeek']['ordersSoFar']);
        $this->assertEquals(10.0, $body['currentWeek']['expectedByNow']);
        $this->assertEquals(-6.0, $body['currentWeek']['paceDelta']);
        $this->assertEquals('behind', $body['currentWeek']['paceStatus']);

        $this->assertCount(7, $body['dayOfWeekPattern']);
        $wednesday = collect($body['dayOfWeekPattern'])->firstWhere('day', 'Wed');
        $this->assertEquals(10.0, $wednesday['avgOrders']);
        $this->assertEquals(100.0, $wednesday['shareOfWeek']);
    }

    public function test_forecast_excludes_drafts_and_cancellations_from_weekly_counts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-17 12:00:00', 'Europe/Vienna'));

        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);

        $currentWeekStart = now()->startOfWeek();
        $windowStart = $currentWeekStart->copy()->subWeeks(8);

        for ($week = 0; $week < 8; $week++) {
            $orderDay = $windowStart->copy()->addWeeks($week)->addDays(1);

            $this->order($vendor, $customer, $session, [
                'amount' => 20, 'payment_received' => true,
                'created_at' => $orderDay, 'confirmed_at' => $orderDay,
            ]);

            // A draft (never confirmed) and a cancellation, same week — must
            // not inflate the weekly order count.
            $this->order($vendor, $customer, $session, [
                'amount' => 500, 'confirmed_at' => null, 'payment_received' => false,
                'created_at' => $orderDay,
            ]);
            $this->order($vendor, $customer, $session, [
                'amount' => 500, 'cancelled_at' => $orderDay, 'payment_received' => false,
                'created_at' => $orderDay, 'confirmed_at' => $orderDay,
            ]);
        }

        $body = $this->forecast($vendor);

        $this->assertTrue($body['available']);
        foreach ($body['history'] as $week) {
            $this->assertEquals(1, $week['orders']);
        }
        $this->assertEquals(1, $body['projection']['projectedOrders']);
    }

    public function test_team_member_token_is_forbidden(): void
    {
        $vendor = $this->vendor();
        $staff = TeamMember::create([
            'vendor_id' => $vendor->id,
            'name' => 'Kitchen Staff',
            'email' => 'kitchen-forecast@example.com',
            'password' => bcrypt('password'),
            'role' => 'kitchen',
            'status' => 'active',
        ]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/forecast",
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
        )->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $vendor = $this->vendor();

        $this->getJson("/api/vendor/{$vendor->vendor_public_id}/analytics/forecast")
            ->assertStatus(401);
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

    private function order(Vendor $vendor, ?Customer $customer, TableScanSession $session, array $attributes = []): \App\Models\Order
    {
        return \App\Models\Order::factory()->create(array_merge([
            'vendor_id' => $vendor->id,
            'customer_id' => $customer?->id,
            'table_scan_session_id' => $session->id,
            'status' => \App\Models\Order::STATUS_SERVED,
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

    private function forecast(Vendor $vendor): array
    {
        return $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics/forecast",
            $this->headers($vendor),
        )->assertOk()->json();
    }

    private function headers(Vendor $vendor): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
