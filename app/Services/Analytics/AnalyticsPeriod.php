<?php

namespace App\Services\Analytics;

use App\Models\Vendor;
use Carbon\CarbonImmutable;

/**
 * Resolves the reporting window for the analytics page.
 *
 * All boundaries are computed in the vendor's local timezone (derived from
 * Vendor::resolveTimezone()) and only converted to the application timezone
 * when they are handed to the query builder. Without this, a venue trading
 * past midnight has its late-night orders bucketed into the wrong day.
 */
final class AnalyticsPeriod
{
    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';
    public const TODAY = 'today';
    public const CUSTOM = 'custom';

    /** A custom range longer than this would draw a trend chart with far more buckets than are useful to scan. */
    public const MAX_CUSTOM_DAYS = 366;

    /** @var array<int, array{label: string, start: CarbonImmutable, end: CarbonImmutable}> */
    public readonly array $buckets;

    /** Bucket unit for the rolling baseline: day, week or month. */
    public readonly string $unit;

    /** How many completed buckets the baseline looks back over, at most. */
    public readonly int $baselineLookback;

    private function __construct(
        public readonly string $key,
        public readonly string $timezone,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly CarbonImmutable $previousStart,
        public readonly CarbonImmutable $previousEnd,
        array $buckets,
        public readonly string $label,
        public readonly string $comparisonLabel,
        string $unit,
        int $baselineLookback,
        public readonly bool $customRangeTruncated = false,
    ) {
        $this->buckets = $buckets;
        $this->unit = $unit;
        $this->baselineLookback = $baselineLookback;
    }

    public static function resolve(Vendor $vendor, ?string $period, ?string $from = null, ?string $to = null): self
    {
        $key = match ($period) {
            self::DAILY => self::DAILY,
            self::MONTHLY => self::MONTHLY,
            self::TODAY => self::TODAY,
            self::CUSTOM => self::CUSTOM,
            default => self::WEEKLY,
        };

        $tz = $vendor->resolveTimezone();
        $now = CarbonImmutable::now($tz);

        return match ($key) {
            // Baseline lookback: enough completed buckets for a stable average
            // without reaching so far back that a venue's older, different self
            // dominates the comparison.
            self::DAILY => self::build(
                $key, $tz, $now->subDays(6)->startOfDay(), $now->endOfDay(),
                'day', 7, 'Last 7 days', 28, 'vs previous 7 days',
            ),
            self::MONTHLY => self::build(
                $key, $tz, $now->subMonths(11)->startOfMonth(), $now->endOfMonth(),
                'month', 12, 'Last 12 months', 12, 'vs previous 12 months',
            ),
            // A single-bucket case of the same 'day' unit DAILY already uses —
            // the baseline still looks back 7 completed days, same as DAILY's,
            // so "today" and "last 7 days" never disagree on what a normal
            // day's service timing looks like.
            self::TODAY => self::build(
                $key, $tz, $now->startOfDay(), $now->endOfDay(),
                'day', 1, 'Today', 7, 'vs yesterday',
            ),
            self::CUSTOM => self::buildCustom($tz, $now, $from, $to),
            default => self::build(
                $key, $tz, $now->subWeeks(11)->startOfWeek(), $now->endOfWeek(),
                'week', 12, 'Last 12 weeks', 12, 'vs previous 12 weeks',
            ),
        };
    }

    private static function build(
        string $key,
        string $tz,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $unit,
        int $count,
        string $label,
        int $baselineLookback,
        string $comparisonLabel,
    ): self {
        $buckets = [];
        $cursor = $start;

        for ($i = 0; $i < $count; $i++) {
            $next = match ($unit) {
                'day' => $cursor->addDay(),
                'month' => $cursor->addMonth(),
                default => $cursor->addWeek(),
            };

            $buckets[] = [
                'label' => match ($unit) {
                    'day' => $cursor->format('D'),
                    'month' => $cursor->format('M'),
                    default => 'W'.$cursor->isoWeek(),
                },
                'start' => $cursor,
                'end' => $next->subSecond(),
            ];

            $cursor = $next;
        }

        return new self(
            $key, $tz, $start, $end,
            ...self::previousWindow($start, $end),
            buckets: $buckets,
            label: $label,
            comparisonLabel: $comparisonLabel,
            unit: $unit,
            baselineLookback: $baselineLookback,
        );
    }

    /**
     * A vendor-picked date range — the one case here where the bucket count
     * isn't fixed in advance, since the range itself can be any length.
     * Buckets iterate forward from the actual start/end at whichever
     * granularity fits the range (day/week/month), the same "pick a
     * granularity by range length" idea FinancialReportPeriod::bucketUnit()
     * already uses. No rolling baseline is offered for a custom range
     * (baselineLookback: 0) — an arbitrary vendor-picked window has no
     * natural "one unit back" to average over the way a fixed weekly/monthly
     * tab does; service timings simply show the existing "no baseline yet"
     * state, the same honest fallback a brand-new venue sees.
     */
    private static function buildCustom(string $tz, CarbonImmutable $now, ?string $from, ?string $to): self
    {
        $isValidDate = fn (?string $d): bool => $d !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;

        $start = $isValidDate($from)
            ? CarbonImmutable::createFromFormat('Y-m-d', $from, $tz)->startOfDay()
            : $now->subDays(6)->startOfDay();
        $end = $isValidDate($to)
            ? CarbonImmutable::createFromFormat('Y-m-d', $to, $tz)->endOfDay()
            : $now->endOfDay();

        // Never a future window — a start or end date after today would draw
        // a trend chart out past "now" with nothing but empty trailing
        // buckets. Both bounds are clamped before they're ever compared to
        // each other: clamping only `end` (as this used to) still left an
        // inverted start > end range whenever `from` alone was in the
        // future, since nothing then pulled `start` back down to match.
        $todayEnd = $now->endOfDay();
        if ($start->greaterThan($todayEnd)) {
            $start = $now->startOfDay();
        }
        if ($end->greaterThan($todayEnd)) {
            $end = $todayEnd;
        }

        if ($end->lessThan($start)) {
            $end = $start->endOfDay();
        }

        // Surfaced to the vendor as `range.truncated` (see build()'s caller,
        // VendorAnalyticsService::build()) — a vendor-picked range longer than
        // this is silently shortened rather than rejected, and without this
        // flag the only visible trace was a range label that quietly didn't
        // match what they'd typed (2026-09-07 audit finding).
        $truncated = $start->diffInDays($end) > self::MAX_CUSTOM_DAYS;
        if ($truncated) {
            $end = $start->addDays(self::MAX_CUSTOM_DAYS)->endOfDay();
        }

        // $end is an endOfDay, so Carbon 3's float diffInDays() reports an
        // N-day inclusive span as (N-1).9999…; the "+ 1" below assumes the
        // whole-number (N-1) Carbon 2 returned. Without the cast a 31-day
        // range measures 31.9999 and falls through the `<= 31` arm into
        // weekly buckets, so the chart silently loses daily granularity at
        // the boundary (same story at the 182-day `week`/`month` arm).
        $days = (int) $start->diffInDays($end) + 1;
        $unit = match (true) {
            $days <= 31 => 'day',
            $days <= 182 => 'week',
            default => 'month',
        };

        $buckets = [];
        $cursor = match ($unit) {
            'week' => $start->startOfWeek(),
            'month' => $start->startOfMonth(),
            default => $start,
        };

        for ($guard = 0; $cursor->lessThanOrEqualTo($end) && $guard < 400; $guard++) {
            $next = match ($unit) {
                'month' => $cursor->addMonth(),
                'week' => $cursor->addWeek(),
                default => $cursor->addDay(),
            };

            $buckets[] = [
                'label' => match ($unit) {
                    'month' => $cursor->format('M'),
                    'week' => 'W'.$cursor->isoWeek(),
                    default => $cursor->format('D j'),
                },
                'start' => $cursor,
                'end' => $next->subSecond(),
            ];

            $cursor = $next;
        }

        return new self(
            self::CUSTOM, $tz, $start, $end,
            ...self::previousWindow($start, $end),
            buckets: $buckets,
            label: $start->format('j M Y').' – '.$end->format('j M Y'),
            comparisonLabel: 'vs previous period',
            unit: $unit,
            baselineLookback: 0,
            customRangeTruncated: $truncated,
        );
    }

    /**
     * The comparison window is the same length, immediately preceding —
     * generic across every period key, since it only needs the actual
     * start/end instants, not which key produced them.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function previousWindow(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $lengthSeconds = $end->getTimestamp() - $start->getTimestamp();
        $previousEnd = $start->subSecond();
        $previousStart = $previousEnd->subSeconds($lengthSeconds);

        return [$previousStart, $previousEnd];
    }

    /** Query bounds, converted into the application timezone the DB stores. */
    public function queryStart(): CarbonImmutable
    {
        return $this->start->setTimezone(config('app.timezone'));
    }

    public function queryEnd(): CarbonImmutable
    {
        return $this->end->setTimezone(config('app.timezone'));
    }

    public function previousQueryStart(): CarbonImmutable
    {
        return $this->previousStart->setTimezone(config('app.timezone'));
    }

    public function previousQueryEnd(): CarbonImmutable
    {
        return $this->previousEnd->setTimezone(config('app.timezone'));
    }

    /** Index of the bucket a given instant falls into, or null if outside the window. */
    public function bucketIndexFor(\DateTimeInterface $moment): ?int
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($this->timezone);

        foreach ($this->buckets as $i => $bucket) {
            if ($local->betweenIncluded($bucket['start'], $bucket['end'])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Completed buckets immediately before the current window, oldest first.
     *
     * These form the rolling baseline: rather than comparing against a single
     * previous window — where one unusual week makes the next look good — a
     * metric is compared against the venue's own average across these. A new
     * venue simply has fewer of them, and none at all in its first period.
     * `baselineLookback: 0` (CUSTOM) correctly returns no buckets at all.
     *
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable, label: string}>
     */
    public function baselineBuckets(): array
    {
        $buckets = [];
        $cursor = $this->start;

        for ($i = 0; $i < $this->baselineLookback; $i++) {
            $bucketEnd = $cursor->subSecond();

            $cursor = match ($this->unit) {
                'day' => $cursor->subDay(),
                'month' => $cursor->subMonth(),
                default => $cursor->subWeek(),
            };

            $buckets[] = [
                'start' => $cursor,
                'end' => $bucketEnd,
                'label' => match ($this->unit) {
                    'day' => $cursor->format('D j M'),
                    'month' => $cursor->format('M Y'),
                    default => 'W'.$cursor->isoWeek(),
                },
            ];
        }

        return array_reverse($buckets);
    }

    /** Earliest instant the baseline reaches back to, in app timezone. */
    public function baselineQueryStart(): CarbonImmutable
    {
        $buckets = $this->baselineBuckets();

        return ($buckets === [] ? $this->start : $buckets[0]['start'])
            ->setTimezone(config('app.timezone'));
    }

    /** Human label for the baseline, e.g. "4-week average". */
    public function baselineUnitLabel(): string
    {
        return match ($this->unit) {
            'day' => 'day',
            'month' => 'month',
            default => 'week',
        };
    }

    /** @return array<int, string> */
    public function labels(): array
    {
        return array_column($this->buckets, 'label');
    }

    public function rangeLabel(): string
    {
        return match ($this->key) {
            self::MONTHLY => $this->start->format('M Y').' – '.$this->end->format('M Y'),
            self::TODAY => $this->start->format('j M Y'),
            default => $this->start->format('j M').' – '.$this->end->format('j M Y'),
        };
    }
}
