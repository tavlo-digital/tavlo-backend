<?php

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Tavlo's first forward-looking analytics feature: "expected orders this
 * week, based on your last 8 weeks." Everything else in VendorAnalyticsService
 * reports what already happened — this is the one deliberate exception.
 *
 * Deliberately NOT a machine-learning model. Every other "prediction" already
 * shipped in this codebase (InsightEngine, InsightQueryEngine, the inventory
 * days-remaining projection) is a fixed, explainable calculation over
 * measured data rather than a black box — this follows the same rule: a
 * simple 8-week average plus a first/second-half trend comparison, so a
 * vendor can see exactly which weeks produced the number they're looking at.
 *
 * Same order-scoping convention as VendorAnalyticsService::baseQuery()/
 * ordersIn(): confirmed, non-cancelled orders, windowed by created_at.
 * "Order count" mirrors summary.orders (all confirmed+non-cancelled orders,
 * paid or not); revenue mirrors summary.grossRevenue (paid orders only).
 *
 * Honesty gate: a forecast needs 8 full completed weeks of order history to
 * exist at all — a vendor with less sees `available: false` and how many
 * weeks they still need, never a guess built from partial data dressed up as
 * a measured figure. This is the same `available`-gated-null idiom used
 * throughout Retention/Loyalty/service-timing baselines, not the separate
 * `isDemoData` convention (which is for synthetic seeded rows standing in
 * for a missing feature — a thin forecast is real data, just not enough of
 * it yet).
 */
class ForecastService
{
    /** Weeks of completed history required before a forecast is shown at all. */
    public const MIN_WEEKS_REQUIRED = 8;

    /** @var array<int, string> Mon..Sun, ISO weekday order. */
    private const WEEKDAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    public function build(Vendor $vendor): array
    {
        $tz = $vendor->resolveTimezone();
        $now = CarbonImmutable::now($tz);
        $currentWeekStart = $now->startOfWeek();

        $base = [
            'currency' => $vendor->currency,
            'timezone' => $tz,
            'generatedAt' => $now->toISOString(),
            'minWeeksRequired' => self::MIN_WEEKS_REQUIRED,
        ];

        $firstOrderAt = $this->firstConfirmedOrderAt($vendor);

        if ($firstOrderAt === null) {
            return $base + ['available' => false, 'weeksOfHistory' => 0];
        }

        $firstOrderWeekStart = CarbonImmutable::instance($firstOrderAt)->setTimezone($tz)->startOfWeek();
        $weeksOfHistory = max(0, (int) $firstOrderWeekStart->diffInWeeks($currentWeekStart));

        if ($weeksOfHistory < self::MIN_WEEKS_REQUIRED) {
            return $base + ['available' => false, 'weeksOfHistory' => $weeksOfHistory];
        }

        // One query covers the 8 completed weeks plus the in-progress current
        // week, then everything below buckets in PHP — same shape as
        // VendorAnalyticsService::ordersIn() (get(), not aggregate SQL), and
        // cheap here since a forecast window is fixed at 9 weeks of orders.
        $windowStart = $currentWeekStart->subWeeks(self::MIN_WEEKS_REQUIRED);
        $orders = $this->ordersBetween($vendor, $windowStart, $now);

        $weeks = $this->emptyWeekBuckets($windowStart);
        $weekdayOrderTotals = array_fill(0, 7, 0);
        $currentWeekdayIndex = $now->dayOfWeekIso - 1; // 0 = Mon .. 6 = Sun
        $cumulativeThroughTodayByWeek = array_fill(0, self::MIN_WEEKS_REQUIRED, 0);
        $currentWeekOrders = 0;
        $currentWeekRevenue = 0.0;

        foreach ($orders as $order) {
            $local = CarbonImmutable::instance($order->created_at)->setTimezone($tz);
            $weekdayIndex = $local->dayOfWeekIso - 1;

            if ($local->lessThan($currentWeekStart)) {
                $weekIndex = $this->weekIndexFor($weeks, $local);

                if ($weekIndex === null) {
                    continue;
                }

                $weeks[$weekIndex]['orders']++;
                $weekdayOrderTotals[$weekdayIndex]++;

                if ($order->payment_received) {
                    $weeks[$weekIndex]['revenue'] += (float) $order->amount;
                }

                if ($weekdayIndex <= $currentWeekdayIndex) {
                    $cumulativeThroughTodayByWeek[$weekIndex]++;
                }
            } else {
                $currentWeekOrders++;

                if ($order->payment_received) {
                    $currentWeekRevenue += (float) $order->amount;
                }
            }
        }

        $orderCounts = array_column($weeks, 'orders');
        $revenues = array_column($weeks, 'revenue');

        $avgOrders = $this->mean($orderCounts);
        $stdDevOrders = $this->stdDev($orderCounts, $avgOrders);
        $avgRevenue = $this->mean($revenues);

        $earlierAvg = $this->mean(array_slice($orderCounts, 0, 4));
        $recentAvg = $this->mean(array_slice($orderCounts, 4, 4));
        $trend = $this->trendFrom($earlierAvg, $recentAvg);

        $expectedByNow = $this->mean($cumulativeThroughTodayByWeek);

        return $base + [
            'available' => true,
            'weeksOfHistory' => $weeksOfHistory,
            'method' => '8-week average of confirmed orders per calendar week, with a first-half-vs-second-half trend read',
            'history' => array_map(fn (array $week) => [
                'weekStart' => $week['start']->format('Y-m-d'),
                'weekEnd' => $week['end']->format('Y-m-d'),
                'label' => 'W'.$week['start']->isoWeek(),
                'orders' => $week['orders'],
                'revenue' => round($week['revenue'], 2),
            ], $weeks),
            'projection' => [
                'projectedOrders' => (int) round($avgOrders),
                'projectedOrdersLow' => max(0, (int) round($avgOrders - $stdDevOrders)),
                'projectedOrdersHigh' => (int) round($avgOrders + $stdDevOrders),
                'avgOrdersPerWeek' => round($avgOrders, 1),
                'projectedRevenue' => round($avgRevenue, 2),
                'avgRevenuePerWeek' => round($avgRevenue, 2),
                'trend' => $trend,
                'confidence' => $this->confidenceFrom($avgOrders, $stdDevOrders),
            ],
            'currentWeek' => [
                'weekStart' => $currentWeekStart->format('Y-m-d'),
                'weekEnd' => $currentWeekStart->addDays(6)->format('Y-m-d'),
                'daysElapsed' => $currentWeekdayIndex + 1,
                'ordersSoFar' => $currentWeekOrders,
                'revenueSoFar' => round($currentWeekRevenue, 2),
                'expectedByNow' => round($expectedByNow, 1),
                'paceDelta' => round($currentWeekOrders - $expectedByNow, 1),
                'paceStatus' => $this->paceStatusFrom($currentWeekOrders, $expectedByNow),
            ],
            'dayOfWeekPattern' => $this->dayOfWeekPattern($weekdayOrderTotals),
            'note' => 'Simple average-based projection built from your last 8 weeks of confirmed orders. Treat it as a planning reference, not a guarantee.',
        ];
    }

    // ---------------------------------------------------------------- data

    private function firstConfirmedOrderAt(Vendor $vendor): ?CarbonImmutable
    {
        $value = $this->baseQuery($vendor)->min('created_at');

        return $value === null ? null : CarbonImmutable::parse($value, config('app.timezone'));
    }

    /** @return Collection<int, Order> */
    private function ordersBetween(Vendor $vendor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->baseQuery($vendor)
            ->whereBetween('created_at', [
                $from->setTimezone(config('app.timezone')),
                $to->setTimezone(config('app.timezone')),
            ])
            ->get(['id', 'amount', 'payment_received', 'created_at']);
    }

    /** Same convention as VendorAnalyticsService::baseQuery(): confirmed, non-cancelled orders. */
    private function baseQuery(Vendor $vendor)
    {
        return Order::query()
            ->where('vendor_id', $vendor->id)
            ->whereNotNull('confirmed_at')
            ->whereNull('cancelled_at');
    }

    // ------------------------------------------------------------- bucketing

    /** @return array<int, array{start: CarbonImmutable, end: CarbonImmutable, orders: int, revenue: float}> */
    private function emptyWeekBuckets(CarbonImmutable $windowStart): array
    {
        $weeks = [];

        for ($i = 0; $i < self::MIN_WEEKS_REQUIRED; $i++) {
            $start = $windowStart->addWeeks($i);
            $weeks[] = [
                'start' => $start,
                'end' => $start->addDays(6)->endOfDay(),
                'orders' => 0,
                'revenue' => 0.0,
            ];
        }

        return $weeks;
    }

    /** @param array<int, array{start: CarbonImmutable, end: CarbonImmutable}> $weeks */
    private function weekIndexFor(array $weeks, CarbonImmutable $moment): ?int
    {
        foreach ($weeks as $index => $week) {
            if ($moment->betweenIncluded($week['start'], $week['end'])) {
                return $index;
            }
        }

        return null;
    }

    // -------------------------------------------------------------- maths

    /** @param array<int, int|float> $values */
    private function mean(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    /**
     * Sample standard deviation (n-1) — used only to draw a plausible range
     * around the point estimate, not for any statistical test.
     *
     * @param array<int, int|float> $values
     */
    private function stdDev(array $values, float $mean): float
    {
        $n = count($values);

        if ($n < 2) {
            return 0.0;
        }

        $sumSquares = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values));

        return sqrt($sumSquares / ($n - 1));
    }

    /** @return array{direction: string, changePercent: ?float, recentAvgOrders: float, earlierAvgOrders: float} */
    private function trendFrom(float $earlierAvg, float $recentAvg): array
    {
        $changePercent = $earlierAvg > 0.0
            ? round((($recentAvg - $earlierAvg) / $earlierAvg) * 100, 1)
            : null;

        $direction = match (true) {
            $changePercent !== null && $changePercent > 5.0 => 'up',
            $changePercent !== null && $changePercent < -5.0 => 'down',
            $changePercent === null && $recentAvg > $earlierAvg => 'up',
            $changePercent === null && $recentAvg < $earlierAvg => 'down',
            default => 'flat',
        };

        return [
            'direction' => $direction,
            'changePercent' => $changePercent,
            'recentAvgOrders' => round($recentAvg, 1),
            'earlierAvgOrders' => round($earlierAvg, 1),
        ];
    }

    private function confidenceFrom(float $avgOrders, float $stdDevOrders): string
    {
        if ($avgOrders <= 0.0) {
            return 'low';
        }

        $coefficientOfVariation = $stdDevOrders / $avgOrders;

        return match (true) {
            $coefficientOfVariation < 0.15 => 'high',
            $coefficientOfVariation < 0.35 => 'medium',
            default => 'low',
        };
    }

    private function paceStatusFrom(int $ordersSoFar, float $expectedByNow): string
    {
        if ($expectedByNow <= 0.0) {
            return $ordersSoFar > 0 ? 'ahead' : 'on_pace';
        }

        $deltaShare = ($ordersSoFar - $expectedByNow) / $expectedByNow;

        return match (true) {
            $deltaShare > 0.1 => 'ahead',
            $deltaShare < -0.1 => 'behind',
            default => 'on_pace',
        };
    }

    /**
     * @param array<int, int> $weekdayOrderTotals Mon..Sun totals across the 8 history weeks.
     * @return array<int, array{day: string, avgOrders: float, shareOfWeek: float}>
     */
    private function dayOfWeekPattern(array $weekdayOrderTotals): array
    {
        $totalOrders = array_sum($weekdayOrderTotals);

        return array_map(function (int $total, int $index) use ($totalOrders) {
            $avg = $total / self::MIN_WEEKS_REQUIRED;

            return [
                'day' => self::WEEKDAY_LABELS[$index],
                'avgOrders' => round($avg, 1),
                'shareOfWeek' => $totalOrders > 0 ? round(($total / $totalOrders) * 100, 1) : 0.0,
            ];
        }, $weekdayOrderTotals, array_keys($weekdayOrderTotals));
    }
}
