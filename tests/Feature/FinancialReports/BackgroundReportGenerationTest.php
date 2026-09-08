<?php

namespace Tests\Feature\FinancialReports;

use App\Jobs\Analytics\GenerateAnalyticsReportJob;
use App\Jobs\FinancialReports\GenerateFinancialReportJob;
use App\Models\Customer;
use App\Models\FinancialReportJob;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\Analytics\VendorAnalyticsService;
use App\Services\FinancialReports\FinancialReportService;
use App\Services\NotificationService;
use App\Services\Reports\BackgroundReportGate;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Analytics\Concerns\GrantsAnalyticsFeature;
use Tests\TestCase;

/**
 * The "prepare this in the background" flow for Financial Reports/Analytics
 * (2026-09-07) — see FinancialReportJob's doc comment for the full design.
 * The order-count threshold is overridden down to a small number in every
 * test here so "a large period" doesn't require seeding thousands of real
 * orders just to exercise the branch — the logic being tested is the
 * threshold/gate/job wiring, not FinancialReportService's own aggregation
 * math (already covered by VendorFinancialReportsApiTest et al).
 */
class BackgroundReportGenerationTest extends TestCase
{
    use RefreshDatabase;
    use GrantsAnalyticsFeature;

    protected function setUp(): void
    {
        parent::setUp();
        config(['reports.async_order_threshold' => 3]);
    }

    public function test_a_period_at_or_below_the_threshold_still_runs_live(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 2);

        $body = $this->financialReport($vendor, 'week');

        $this->assertArrayNotHasKey('generationStatus', $body);
        $this->assertArrayHasKey('summary', $body);
        $this->assertSame(0, FinancialReportJob::count());
    }

    public function test_a_period_over_the_threshold_returns_processing_and_creates_a_job(): void
    {
        // QUEUE_CONNECTION=sync in testing runs a dispatched job inline
        // immediately — without faking it here, the report would already be
        // 'ready' by the time this method's assertions run, since there'd
        // be no real queue to actually wait on.
        Queue::fake([GenerateFinancialReportJob::class]);

        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 4);

        $body = $this->financialReport($vendor, 'week');

        $this->assertSame('processing', $body['generationStatus']);
        $this->assertIsInt($body['jobId']);
        $this->assertNotEmpty($body['periodLabel']);

        $this->assertDatabaseHas('financial_report_jobs', [
            'id' => $body['jobId'],
            'vendor_id' => $vendor->id,
            'source' => FinancialReportJob::SOURCE_FINANCIAL_REPORTS,
            'status' => FinancialReportJob::STATUS_PROCESSING,
        ]);
    }

    public function test_running_the_job_builds_and_stores_the_result_and_notifies_the_vendor(): void
    {
        Queue::fake();

        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 4, amount: 25);

        $body = $this->financialReport($vendor, 'week');
        $job = FinancialReportJob::findOrFail($body['jobId']);

        (new GenerateFinancialReportJob($job->id))->handle(app(FinancialReportService::class));

        $job->refresh();
        $this->assertSame(FinancialReportJob::STATUS_READY, $job->status);
        $this->assertNotNull($job->completed_at);
        $result = $job->resultArray();
        $this->assertSame(4, $result['summary']['totalOrders']['value']);
        $this->assertEquals(100.0, $result['summary']['grossRevenue']['value']);

        // NotificationService::notifyOperationalActor() enqueues the actual
        // delivery job via Laravel's defer() — only flushed when the app
        // terminates, which a direct ->handle() call here never triggers on
        // its own. Same pattern VendorPusherRealtimeTest uses to execute it
        // deterministically in a test.
        $this->invokeDeferredCallbacks();
        Queue::assertPushed(\App\Jobs\DeliverOperationalNotification::class, function ($delivery) {
            return $delivery->payload['event'] === 'financial_report_ready'
                && $delivery->payload['vendor_id'] === $delivery->payload['actor_id'];
        });
        $delivery = Queue::pushed(\App\Jobs\DeliverOperationalNotification::class)->sole();
        $delivery->handle();

        $this->assertDatabaseHas('notifications', [
            'vendor_id' => $vendor->id,
            'event' => 'financial_report_ready',
        ]);
    }

    public function test_revisiting_a_ready_period_returns_the_stored_result_without_rebuilding(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 4);

        $body = $this->financialReport($vendor, 'week');
        $job = FinancialReportJob::findOrFail($body['jobId']);
        (new GenerateFinancialReportJob($job->id))->handle(app(FinancialReportService::class));

        // A brand new order after the job completed — if the revisit below
        // rebuilt live instead of reusing the stored result, this would show
        // up and the assertion below would fail, proving reuse actually
        // happened rather than just coincidentally matching.
        $this->order($vendor, $customer, $session, ['amount' => 999, 'payment_received' => true]);

        $revisit = $this->financialReport($vendor, 'week');

        $this->assertArrayNotHasKey('generationStatus', $revisit);
        $this->assertSame(4, $revisit['summary']['totalOrders']['value']);
    }

    public function test_a_second_large_request_is_blocked_while_one_is_processing(): void
    {
        Queue::fake([GenerateFinancialReportJob::class]);

        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 4);

        $this->financialReport($vendor, 'week');
        $this->assertSame(1, FinancialReportJob::where('status', FinancialReportJob::STATUS_PROCESSING)->count());

        $token = $vendor->createToken('test')->plainTextToken;
        $response = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period=month",
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
        );

        $response->assertStatus(409);
        $this->assertSame('report_already_processing', $response->json('code'));
        // Still only one job row — the blocked request didn't create a second.
        $this->assertSame(1, FinancialReportJob::count());
    }

    /**
     * 2026-09-08 audit finding: decide()'s BLOCKED check was a plain
     * `status = 'processing'` lookup with no age limit — a queue worker
     * that crashes/restarts mid-job (a deploy, an OOM) never gets to run
     * either handle()'s own catch or failed(), leaving the row stuck
     * `processing` forever and permanently blocking the vendor from ever
     * running another large report on either page. Simulates that exact
     * scenario directly (no real queue worker to actually kill in a test
     * process) by inserting a `processing` row whose `requested_at` is
     * older than BackgroundReportGate::STALE_PROCESSING_MINUTES, then
     * confirming a new request is NOT blocked by it and the stale row gets
     * marked `failed` instead of staying stuck.
     */
    public function test_a_stale_processing_job_is_treated_as_abandoned_and_unblocks_the_vendor(): void
    {
        Queue::fake([GenerateFinancialReportJob::class]);

        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 4);

        $stale = FinancialReportJob::create([
            'vendor_id' => $vendor->id,
            'source' => FinancialReportJob::SOURCE_FINANCIAL_REPORTS,
            'period_key' => 'month',
            'status' => FinancialReportJob::STATUS_PROCESSING,
            'requested_at' => now()->subMinutes(25),
        ]);

        $body = $this->financialReport($vendor, 'week');

        $this->assertSame('processing', $body['generationStatus']);
        $this->assertNotSame($stale->id, $body['jobId']);

        $stale->refresh();
        $this->assertSame(FinancialReportJob::STATUS_FAILED, $stale->status);
        $this->assertNotNull($stale->completed_at);
        $this->assertStringContainsString('Abandoned', $stale->error_message);
    }

    /** Boundary check for the fix above — a genuinely recent `processing` row must still block exactly as before. */
    public function test_a_recent_processing_job_still_blocks_as_before(): void
    {
        Queue::fake([GenerateFinancialReportJob::class]);

        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 4);

        $recent = FinancialReportJob::create([
            'vendor_id' => $vendor->id,
            'source' => FinancialReportJob::SOURCE_FINANCIAL_REPORTS,
            'period_key' => 'month',
            'status' => FinancialReportJob::STATUS_PROCESSING,
            'requested_at' => now()->subMinutes(5),
        ]);

        $token = $vendor->createToken('test')->plainTextToken;
        $response = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period=week",
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
        );

        $response->assertStatus(409);
        $this->assertSame('report_already_processing', $response->json('code'));
        $recent->refresh();
        $this->assertSame(FinancialReportJob::STATUS_PROCESSING, $recent->status);
    }

    /**
     * 2026-09-07 audit finding: the check-then-create in decide() wasn't
     * atomic, so two near-simultaneous big requests for the same vendor
     * could both read "nothing processing yet" and both start a job —
     * defeating the one-at-a-time cap. Fixed with a `Cache::lock()` around
     * the whole decision. A real two-process race can't be reproduced in a
     * single synchronous test process, so this proves the mechanism the fix
     * relies on instead: with the SAME lock key already held (simulating a
     * concurrent decide() call mid-decision), a second decide() call must
     * actually wait on it rather than read straight through — confirmed by
     * it timing out, not by it silently succeeding.
     */
    public function test_decide_is_serialized_by_a_per_vendor_lock(): void
    {
        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 4);

        $externalLock = Cache::lock("report-gate:vendor:{$vendor->id}", 10);
        $this->assertTrue($externalLock->get());

        $threw = false;
        try {
            app(BackgroundReportGate::class)->decide(
                $vendor, FinancialReportJob::SOURCE_FINANCIAL_REPORTS, 4, 'week', null, null,
            );
        } catch (LockTimeoutException) {
            $threw = true;
        } finally {
            $externalLock->release();
        }

        $this->assertTrue($threw, 'decide() should have blocked on the held lock and timed out.');
        // Must not have created a job row despite never reaching its own
        // create() — proves it never ran the body at all while the lock was
        // held, not that it ran and merely errored afterward.
        $this->assertSame(0, FinancialReportJob::count());
    }

    public function test_a_small_request_is_not_blocked_by_an_in_progress_big_job(): void
    {
        Queue::fake([GenerateFinancialReportJob::class]);

        $vendor = $this->vendor();
        [$customer, $session] = $this->context($vendor);
        // Dated in last month specifically, not "now" — every other fixture
        // helper in this file confirms orders "now" so they'd land inside
        // EVERY built-in period (today/week/month/year all include "now"),
        // making it impossible to construct a period that's genuinely
        // smaller than another. Backdating these is what actually lets
        // 'today' (0 orders) differ from 'last_month' (4 orders) below.
        $lastMonth = now()->subMonthNoOverflow();
        for ($i = 0; $i < 4; $i++) {
            $this->order($vendor, $customer, $session, [
                'amount' => 25, 'payment_received' => true,
                'confirmed_at' => $lastMonth, 'payment_confirmed_at' => $lastMonth, 'created_at' => $lastMonth,
            ]);
        }
        $this->financialReport($vendor, 'last_month'); // starts a processing job

        $small = $this->financialReport($vendor, 'today');

        $this->assertArrayNotHasKey('generationStatus', $small);
        $this->assertArrayHasKey('summary', $small);
    }

    public function test_blocking_applies_across_both_financial_reports_and_analytics(): void
    {
        Queue::fake([GenerateFinancialReportJob::class, GenerateAnalyticsReportJob::class]);

        $vendor = $this->vendor(); // already granted "Basic Analytics" access — see vendor()
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 4);

        // Start a big Analytics job...
        $token = $vendor->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
        $analyticsBody = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/analytics?period=monthly",
            $headers,
        )->assertOk()->json();
        $this->assertSame('processing', $analyticsBody['generationStatus']);

        // ...then a big Financial Reports request must be blocked by it,
        // even though it's a different feature and a different period.
        $response = $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period=week",
            $headers,
        );
        $response->assertStatus(409);
        $this->assertSame('report_already_processing', $response->json('code'));
    }

    public function test_running_the_analytics_job_builds_and_stores_the_result(): void
    {
        Queue::fake();

        $vendor = $this->vendor(); // already granted "Basic Analytics" access — see vendor()
        [$customer, $session] = $this->context($vendor);
        $this->orders($vendor, $customer, $session, 4);

        $token = $vendor->createToken('test')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
        $body = $this->getJson("/api/vendor/{$vendor->vendor_public_id}/analytics?period=monthly", $headers)
            ->assertOk()->json();

        $job = FinancialReportJob::findOrFail($body['jobId']);
        $this->assertSame(FinancialReportJob::SOURCE_ANALYTICS, $job->source);

        (new GenerateAnalyticsReportJob($job->id))->handle(app(VendorAnalyticsService::class));

        $job->refresh();
        $this->assertSame(FinancialReportJob::STATUS_READY, $job->status);
        $this->assertNotNull($job->resultArray());

        $this->invokeDeferredCallbacks();
        Queue::assertPushed(\App\Jobs\DeliverOperationalNotification::class, function ($delivery) {
            return $delivery->payload['event'] === 'analytics_report_ready';
        });
    }

    // ---------------------------------------------------------------- fixtures

    private function vendor(): Vendor
    {
        // Every test here exercises the background-job wiring, not the
        // plan-gate itself — granted by default so the 2026-09-07 gating fix
        // (Financial Reports now requires the same feature Analytics does)
        // doesn't turn every one of these into a locked-response assertion.
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

    private function orders(Vendor $vendor, ?Customer $customer, TableScanSession $session, int $count, float $amount = 25): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->order($vendor, $customer, $session, ['amount' => $amount, 'payment_received' => true]);
        }
    }

    private function financialReport(Vendor $vendor, string $period = 'week'): array
    {
        $token = $vendor->createToken('test')->plainTextToken;

        return $this->getJson(
            "/api/vendor/{$vendor->vendor_public_id}/financial-reports?period={$period}",
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
        )->assertOk()->json();
    }

    private function invokeDeferredCallbacks(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }
}
