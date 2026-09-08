<?php

namespace App\Services\Reports;

use RuntimeException;

/**
 * Thrown by FinancialReportService/VendorAnalyticsService before attempting
 * to hydrate an order/cart-item collection larger than
 * `config('reports.max_order_count')` — a deliberate, catchable failure
 * instead of letting PHP hit an uncatchable out-of-memory fatal error partway
 * through the build (2026-09-08 audit finding). A live (synchronous) request
 * is already gated well below this ceiling by
 * `config('reports.async_order_threshold')`, so in practice this is only
 * ever thrown from the background-job path — GenerateFinancialReportJob and
 * GenerateAnalyticsReportJob's existing `catch (Throwable $e)` handles it the
 * same as any other failure: the job is marked `failed` with this exception's
 * message, and the vendor is notified rather than left with a job stuck at
 * `processing` forever.
 */
class ReportPeriodTooLargeException extends RuntimeException
{
    public function __construct(int $orderCount, int $maxOrderCount)
    {
        parent::__construct(
            "This period has {$orderCount} orders, which is more than can be safely generated right now ".
            "(limit: {$maxOrderCount}). Please choose a shorter date range."
        );
    }
}
