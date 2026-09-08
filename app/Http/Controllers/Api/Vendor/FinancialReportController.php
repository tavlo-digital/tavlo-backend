<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Vendor\Concerns\GatesAnalyticsFeature;
use App\Http\Requests\FinancialReport\GetFinancialReportRequest;
use App\Jobs\FinancialReports\GenerateFinancialReportJob;
use App\Models\FinancialReportJob;
use App\Models\Vendor;
use App\Services\FinancialReports\FinancialReportPeriod;
use App\Services\FinancialReports\FinancialReportService;
use App\Services\Reports\BackgroundReportDecision;
use App\Services\Reports\BackgroundReportGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FinancialReportController
 *
 * Mirrors AnalyticsController's shape exactly (vendor resolution, auth
 * guard, route param handling) so this feature slots in next to Analytics
 * without introducing a second convention for the same kind of route.
 */
class FinancialReportController extends Controller
{
    use GatesAnalyticsFeature;

    public function __construct(
        private readonly FinancialReportService $reports,
        private readonly BackgroundReportGate $gate,
    ) {}

    /**
     * GET /api/vendor/{vendorId}/financial-reports
     *
     * Query params:
     *   period - today|week|month|last_month|year|custom  (default: week)
     *   from   - Y-m-d (required when period=custom)
     *   to     - Y-m-d (required when period=custom)
     *
     * A period covering more orders than `config('reports.async_order_threshold')`
     * is NOT built here — see FinancialReportJob's doc comment. The response
     * shape then is `{generationStatus: 'processing', jobId, periodLabel}`
     * instead of the real payload; everything below that threshold is
     * completely unchanged from before this existed.
     *
     * Gated behind the same "Basic Analytics" plan feature Analytics
     * requires (2026-09-07 — previously this route had no plan gating at
     * all, an audit finding; founder decided to reuse Analytics' existing
     * feature rather than add a second one). Checked before any of the
     * above so a vendor without access never triggers the background-job
     * threshold logic either.
     */
    public function index(GetFinancialReportRequest $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        if (! $this->hasAnalyticsAccess($vendor)) {
            return $this->lockedResponse($vendor);
        }

        $periodKey = $request->input('period');
        $from = $request->input('from');
        $to = $request->input('to');

        $period = FinancialReportPeriod::resolve($vendor, $periodKey, $from, $to);

        $orderCount = $this->reports->ordersCountIn($vendor, $period->queryStart(), $period->queryEnd());

        $decision = $this->gate->decide(
            $vendor,
            FinancialReportJob::SOURCE_FINANCIAL_REPORTS,
            $orderCount,
            $period->key,
            $period->key === FinancialReportPeriod::CUSTOM ? $from : null,
            $period->key === FinancialReportPeriod::CUSTOM ? $to : null,
        );

        return match ($decision->outcome) {
            BackgroundReportDecision::RUN_LIVE => response()->json($this->reports->build($vendor, $period)),
            BackgroundReportDecision::REUSE_READY => response()->json($decision->job->resultArray()),
            BackgroundReportDecision::BLOCKED => $this->blockedResponse($decision->blockingJob),
            BackgroundReportDecision::STARTED => $this->startedResponse($decision->job, $period),
        };
    }

    private function startedResponse(FinancialReportJob $job, FinancialReportPeriod $period): JsonResponse
    {
        GenerateFinancialReportJob::dispatch($job->id);

        return response()->json([
            'generationStatus' => 'processing',
            'jobId' => $job->id,
            'periodLabel' => $period->humanLabel(),
        ]);
    }

    private function blockedResponse(FinancialReportJob $blockingJob): JsonResponse
    {
        return response()->json([
            'message' => 'You already have a report being prepared. We\'ll notify you when it\'s ready before you can start another.',
            'code' => 'report_already_processing',
            'blockingJob' => [
                'id' => $blockingJob->id,
                'source' => $blockingJob->source,
                'requestedAt' => $blockingJob->requested_at?->toIso8601String(),
            ],
        ], 409);
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();

        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
