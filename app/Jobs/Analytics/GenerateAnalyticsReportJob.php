<?php

namespace App\Jobs\Analytics;

use App\Models\FinancialReportJob;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\VendorAnalyticsService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Analytics' counterpart to GenerateFinancialReportJob — see that class and
 * FinancialReportJob's doc comment for the shared design. Uses the same
 * `financial_report_jobs` table (source='analytics') so "does this vendor
 * already have something processing" is one check across both features.
 */
class GenerateAnalyticsReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Generous relative to the worst measured case (~68s at ~9.9k in-window orders). */
    public int $timeout = 600;

    public function __construct(
        public readonly int $jobId,
    ) {
        $this->onQueue((string) config('services.notifications.queue', 'notifications'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(VendorAnalyticsService $service): void
    {
        $job = FinancialReportJob::find($this->jobId);
        if (! $job) {
            return;
        }

        $vendor = $job->vendor;
        if (! $vendor) {
            return;
        }

        // Resolved once up front (not inside the try below) so both the
        // success and failure notification copy can use the exact same
        // `label` AnalyticsController::index() would have used had the
        // request been cheap enough to run live — AnalyticsPeriod already
        // computes a human label for every key including 'custom', no need
        // to duplicate that mapping here.
        $period = AnalyticsPeriod::resolve(
            $vendor,
            $job->period_key,
            $job->period_from?->toDateString(),
            $job->period_to?->toDateString(),
        );

        try {
            $result = $service->build($vendor, $period);

            $job->update([
                'status' => FinancialReportJob::STATUS_READY,
                'result' => json_encode($result),
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => FinancialReportJob::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            report($e);

            NotificationService::notifyOperationalActor(
                $vendor->id,
                NotificationService::VENDOR,
                $vendor->id,
                'analytics_report_failed',
                "We couldn't finish your Analytics report for {$period->label} — please try again.",
                [
                    'report_source' => FinancialReportJob::SOURCE_ANALYTICS,
                    'report_job_id' => $job->id,
                ],
                silent: false,
            );

            return;
        }

        // Notification is best-effort and deliberately OUTSIDE the try
        // above — see GenerateFinancialReportJob's identical fix
        // (2026-09-08 audit finding): the report already built and
        // persisted successfully above, so a failure here must never flip
        // status back to `failed` and discard an already-correct result.
        try {
            NotificationService::notifyOperationalActor(
                $vendor->id,
                NotificationService::VENDOR,
                $vendor->id,
                'analytics_report_ready',
                "Your Analytics report for {$period->label} is ready.",
                [
                    'report_source' => FinancialReportJob::SOURCE_ANALYTICS,
                    'report_job_id' => $job->id,
                    'report_period_key' => $job->period_key,
                    'report_period_from' => $job->period_from?->toDateString(),
                    'report_period_to' => $job->period_to?->toDateString(),
                ],
                silent: false,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Analytics report generation job failed permanently.', [
            'job_id' => $this->jobId,
            'exception' => $exception,
        ]);

        $job = FinancialReportJob::find($this->jobId);
        if ($job && $job->status !== FinancialReportJob::STATUS_READY) {
            $job->update([
                'status' => FinancialReportJob::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
