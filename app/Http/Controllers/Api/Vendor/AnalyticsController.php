<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Vendor\Concerns\GatesAnalyticsFeature;
use App\Http\Requests\Analytics\AskInsightRequest;
use App\Http\Requests\Analytics\GetAnalyticsRequest;
use App\Jobs\Analytics\GenerateAnalyticsReportJob;
use App\Models\FinancialReportJob;
use App\Models\Order;
use App\Models\Vendor;
use App\Services\Analytics\AnalyticsPeriod;
use App\Services\Analytics\ForecastService;
use App\Services\Analytics\InsightEngine;
use App\Services\Analytics\InsightQueryEngine;
use App\Services\Analytics\VendorAnalyticsService;
use App\Services\LocaleService;
use App\Services\Reports\BackgroundReportDecision;
use App\Services\Reports\BackgroundReportGate;
use App\Services\Reports\ReportPeriodTooLargeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AnalyticsController extends Controller
{
    use GatesAnalyticsFeature;

    public function __construct(
        private readonly VendorAnalyticsService $analytics,
        private readonly InsightEngine $insights,
        private readonly InsightQueryEngine $insightQuery,
        private readonly ForecastService $forecast,
        private readonly LocaleService $locale,
        private readonly BackgroundReportGate $gate,
    ) {}

    /**
     * GET /api/vendor/{vendorId}/analytics?period=daily|weekly|monthly
     *
     * A period covering more orders than `config('reports.async_order_threshold')`
     * is NOT built here — see FinancialReportJob's doc comment. The response
     * shape then is `{generationStatus: 'processing', jobId, periodLabel}`
     * instead of the real payload; everything below that threshold is
     * completely unchanged from before this existed. Only this main
     * dashboard endpoint is gated this way — insights/ask/suggested-
     * questions/forecast stay live, since they're supplementary and a
     * vendor is unlikely to reach for them before the main dashboard has
     * loaded at all.
     */
    public function index(GetAnalyticsRequest $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        App::setLocale($this->locale->dashboardLanguage($vendor));

        if (! $this->hasAnalyticsAccess($vendor)) {
            return $this->lockedResponse($vendor, ['preview' => $this->todaySnapshot($vendor)]);
        }

        $validated = $request->validated();
        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        $period = AnalyticsPeriod::resolve($vendor, $validated['period'] ?? null, $from, $to);

        $orderCount = $this->analytics->ordersCountIn($vendor, $period->queryStart(), $period->queryEnd());

        $decision = $this->gate->decide(
            $vendor,
            FinancialReportJob::SOURCE_ANALYTICS,
            $orderCount,
            $period->key,
            $period->key === AnalyticsPeriod::CUSTOM ? $from : null,
            $period->key === AnalyticsPeriod::CUSTOM ? $to : null,
        );

        return match ($decision->outcome) {
            BackgroundReportDecision::RUN_LIVE => response()->json($this->analytics->build($vendor, $period)),
            BackgroundReportDecision::REUSE_READY => response()->json($decision->job->resultArray()),
            BackgroundReportDecision::BLOCKED => $this->blockedResponse($decision->blockingJob),
            BackgroundReportDecision::STARTED => $this->startedResponse($decision->job, $period),
        };
    }

    private function startedResponse(FinancialReportJob $job, AnalyticsPeriod $period): JsonResponse
    {
        GenerateAnalyticsReportJob::dispatch($job->id);

        return response()->json([
            'generationStatus' => 'processing',
            'jobId' => $job->id,
            'periodLabel' => $period->label,
        ]);
    }

    /**
     * insights()/askInsights()/suggestedQuestions() call
     * VendorAnalyticsService::build() directly, with no order-count gate of
     * their own (unlike index() above) — see
     * ReportPeriodTooLargeException's doc comment. A clean 422 here replaces
     * what would otherwise be an uncaught exception surfacing as a generic
     * 500 (2026-09-08 audit finding).
     */
    private function periodTooLargeResponse(ReportPeriodTooLargeException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'code' => 'period_too_large',
        ], 422);
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

    /**
     * GET /api/vendor/{vendorId}/analytics/insights?period=daily|weekly|monthly
     *
     * Kept separate so the main dashboard renders without waiting on it, and so
     * the rules can be tightened without touching the metrics contract.
     */
    public function insights(GetAnalyticsRequest $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        App::setLocale($this->locale->dashboardLanguage($vendor));

        $validated = $request->validated();

        $period = AnalyticsPeriod::resolve(
            $vendor,
            $validated['period'] ?? null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        if (! $this->hasAnalyticsAccess($vendor)) {
            return $this->lockedResponse($vendor, ['period' => $period->key, 'insights' => []]);
        }

        try {
            $payload = $this->analytics->build($vendor, $period);
        } catch (ReportPeriodTooLargeException $e) {
            return $this->periodTooLargeResponse($e);
        }

        return response()->json([
            'period' => $period->key,
            'currency' => $vendor->currency,
            'insights' => $this->insights->derive($payload),
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/analytics/insights/ask
     *
     * The queryable half of the assistant — a vendor asks a free-text
     * question (optionally a follow-up on a specific card via insightId)
     * and gets a deterministic answer built from the same payload the cards
     * above are built from. See InsightQueryEngine for why this stays
     * rule-based rather than calling out to an LLM.
     */
    public function askInsights(AskInsightRequest $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        App::setLocale($this->locale->dashboardLanguage($vendor));

        $validated = $request->validated();

        $period = AnalyticsPeriod::resolve(
            $vendor,
            $validated['period'] ?? null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        if (! $this->hasAnalyticsAccess($vendor)) {
            return $this->lockedResponse($vendor, [
                'period' => $period->key,
                'question' => $validated['question'],
                'insightId' => $validated['insightId'] ?? null,
                'matched' => false,
                'topic' => null,
                'answer' => '',
                'data' => null,
                'confidence' => null,
                'suggestedQuestions' => [],
            ]);
        }

        try {
            $payload = $this->analytics->build($vendor, $period);
        } catch (ReportPeriodTooLargeException $e) {
            return $this->periodTooLargeResponse($e);
        }

        $result = $this->insightQuery->ask($payload, $validated['question'], $validated['insightId'] ?? null);

        return response()->json(array_merge([
            'period' => $period->key,
            'currency' => $vendor->currency,
        ], $result));
    }

    /**
     * GET /api/vendor/{vendorId}/analytics/insights/suggested-questions?period=daily|weekly|monthly
     *
     * Example questions worth asking right now, scoped to what this vendor
     * actually has enough data to answer — the empty-state prompt for the
     * assistant's question box.
     */
    public function suggestedQuestions(GetAnalyticsRequest $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        App::setLocale($this->locale->dashboardLanguage($vendor));

        $validated = $request->validated();

        $period = AnalyticsPeriod::resolve(
            $vendor,
            $validated['period'] ?? null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        if (! $this->hasAnalyticsAccess($vendor)) {
            return $this->lockedResponse($vendor, ['period' => $period->key, 'questions' => []]);
        }

        try {
            $payload = $this->analytics->build($vendor, $period);
        } catch (ReportPeriodTooLargeException $e) {
            return $this->periodTooLargeResponse($e);
        }

        return response()->json([
            'period' => $period->key,
            'currency' => $vendor->currency,
            'questions' => $this->insightQuery->suggestedQuestions($payload),
        ]);
    }

    /**
     * GET /api/vendor/{vendorId}/analytics/forecast
     *
     * "Expected orders this week, based on your last 8 weeks" — Tavlo's first
     * forward-looking analytics feature, everything else on this dashboard
     * looks backward. No period parameter: the lookback is always the fixed
     * last 8 completed weeks, and the projection is always for the
     * in-progress current week. See ForecastService for the calculation.
     */
    public function forecast(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        App::setLocale($this->locale->dashboardLanguage($vendor));

        if (! $this->hasAnalyticsAccess($vendor)) {
            return $this->lockedResponse($vendor, [
                'timezone' => $vendor->resolveTimezone(),
                'generatedAt' => now()->toISOString(),
                'minWeeksRequired' => ForecastService::MIN_WEEKS_REQUIRED,
                'available' => false,
                'weeksOfHistory' => 0,
            ]);
        }

        return response()->json($this->forecast->build($vendor));
    }

    // ----------------------------------------------------------------

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

    /**
     * A deliberately cheap, self-contained "today" snapshot for the locked
     * reduced-preview shown when hasAnalyticsAccess() is false — NOT
     * VendorAnalyticsService::build(), which computes the full ~20-section
     * payload regardless of what's actually needed here. Mirrors the same
     * order-scoping rules documented on that class (drafts and cancellations
     * excluded from the count; revenue counts paid orders only) so these
     * numbers agree with the real dashboard once the vendor unlocks it.
     */
    private function todaySnapshot(Vendor $vendor): array
    {
        $period = AnalyticsPeriod::resolve($vendor, AnalyticsPeriod::TODAY);

        $orders = Order::query()
            ->where('vendor_id', $vendor->id)
            ->whereNotNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->whereBetween('created_at', [$period->queryStart(), $period->queryEnd()])
            ->get(['amount', 'payment_received']);

        return [
            'ordersToday' => $orders->count(),
            'grossRevenueToday' => round((float) $orders->where('payment_received', true)->sum('amount'), 2),
        ];
    }
}
