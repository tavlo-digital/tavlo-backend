<?php

namespace App\Jobs\FinancialReports;

use App\Models\FinancialReportJob;
use App\Services\FinancialReports\FinancialReportPeriod;
use App\Services\FinancialReports\FinancialReportService;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs FinancialReportService::build() for a period too large to compute
 * inside one HTTP request (see FinancialReportController::index()'s
 * threshold check and FinancialReportJob's own doc comment), then records
 * the result and notifies the vendor through the existing notification
 * system — the same one order/table events already use, not a new channel.
 *
 * Re-resolves the period from `period_key`/`period_from`/`period_to` rather
 * than trusting anything computed at request time, so this reproduces
 * exactly what FinancialReportController::index() would have returned had
 * the request been cheap enough to run live.
 */
class GenerateFinancialReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Generous relative to the worst measured case (~65s at 16.5k orders) — a report that genuinely needs longer than this signals something wrong, not just a big vendor. */
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

    public function handle(FinancialReportService $service): void
    {
        $job = FinancialReportJob::find($this->jobId);
        if (! $job) {
            return;
        }

        $vendor = $job->vendor;
        if (! $vendor) {
            // Vendor deleted between request and job execution — nothing
            // sensible to notify, and the job row will cascade-delete with
            // the vendor anyway.
            return;
        }

        // Resolved once up front (not inside the try below) so both the
        // success and failure notification copy can use the exact same
        // label FinancialReportController::index() would have used had the
        // request been cheap enough to run live.
        $period = FinancialReportPeriod::resolve(
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
                'financial_report_failed',
                "We couldn't finish your Financial Report for {$period->humanLabel()} — please try again.",
                [
                    'report_source' => FinancialReportJob::SOURCE_FINANCIAL_REPORTS,
                    'report_job_id' => $job->id,
                ],
                silent: false,
            );

            return;
        }

        // Notification is best-effort and deliberately OUTSIDE the try
        // above: the report itself already built successfully and is
        // already persisted as `ready` at this point, so a failure here
        // (a DB hiccup writing the notification row, a bug in an unrelated
        // service) must never flip the job's status back to `failed` and
        // discard an already-correct, already-expensive-to-recompute result
        // (2026-09-08 audit finding — this previously shared the try/catch
        // above, so a notification failure both mislabeled a successful
        // report as failed AND made a retry recompute it from scratch for
        // no reason).
        try {
            NotificationService::notifyOperationalActor(
                $vendor->id,
                NotificationService::VENDOR,
                $vendor->id,
                'financial_report_ready',
                "Your Financial Report for {$period->humanLabel()} is ready.",
                [
                    'report_source' => FinancialReportJob::SOURCE_FINANCIAL_REPORTS,
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
        Log::error('Financial report generation job failed permanently.', [
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
