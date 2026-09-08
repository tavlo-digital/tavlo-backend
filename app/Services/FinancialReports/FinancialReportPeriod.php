<?php

namespace App\Services\FinancialReports;

use App\Models\Vendor;
use Carbon\CarbonImmutable;

/**
 * Resolves the calendar window for a Financial Reports request.
 *
 * Unlike AnalyticsPeriod (rolling trend windows for the operational
 * dashboard), this resolves fixed CALENDAR periods — today, this week,
 * this/last calendar month, this calendar year, or a vendor-picked custom
 * range — because a bookkeeping report needs to line up with how an
 * accountant or tax authority thinks about periods, not a rolling
 * "last N days" trend window.
 *
 * All boundaries are computed in the vendor's own timezone
 * (Vendor::resolveTimezone()) and only converted to the application
 * timezone when handed to the query builder, for the same reason
 * AnalyticsPeriod does it: a venue trading past midnight must not have its
 * late-night orders bucketed into the wrong calendar day.
 */
final class FinancialReportPeriod
{
    public const TODAY = 'today';

    public const WEEK = 'week';

    public const MONTH = 'month';

    public const LAST_MONTH = 'last_month';

    public const YEAR = 'year';

    public const CUSTOM = 'custom';

    public const KEYS = [self::TODAY, self::WEEK, self::MONTH, self::LAST_MONTH, self::YEAR, self::CUSTOM];

    /** Widest custom range a vendor may request, to bound query cost. */
    public const MAX_CUSTOM_DAYS = 731;

    private function __construct(
        public readonly string $key,
        public readonly string $timezone,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly CarbonImmutable $previousStart,
        public readonly CarbonImmutable $previousEnd,
        public readonly bool $customRangeTruncated = false,
    ) {}

    public static function resolve(Vendor $vendor, ?string $period, ?string $from, ?string $to): self
    {
        $key = in_array($period, self::KEYS, true) ? $period : self::WEEK;
        $tz = $vendor->resolveTimezone();
        $now = CarbonImmutable::now($tz);

        [$start, $end, $customRangeTruncated] = match ($key) {
            self::TODAY => [$now->startOfDay(), $now->endOfDay(), false],
            self::WEEK => [$now->startOfWeek(), self::clampFutureEnd($now->endOfWeek(), $now), false],
            self::MONTH => [$now->startOfMonth(), self::clampFutureEnd($now->endOfMonth(), $now), false],
            self::LAST_MONTH => [
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
                false,
            ],
            self::YEAR => [$now->startOfYear(), self::clampFutureEnd($now->endOfYear(), $now), false],
            self::CUSTOM => self::resolveCustom($tz, $now, $from, $to),
        };

        // The comparison window is the same length, immediately preceding —
        // lets the "at a glance" cards show a vs-previous-period delta.
        $lengthSeconds = $end->getTimestamp() - $start->getTimestamp();
        $previousEnd = $start->subSecond();
        $previousStart = $previousEnd->subSeconds($lengthSeconds);

        return new self($key, $tz, $start, $end, $previousStart, $previousEnd, $customRangeTruncated);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool}
     */
    private static function resolveCustom(string $tz, CarbonImmutable $now, ?string $from, ?string $to): array
    {
        // GetFinancialReportRequest already guarantees both are present,
        // Y-m-d, and $to >= $from when period=custom — this is a defensive
        // fallback only, not the primary validation path. It does NOT guard
        // against a wholly future range (e.g. both dates next year), which
        // is why $start is run through clampFutureStart below, not just
        // $end through clampFutureEnd — clamping only $end used to leave an
        // inverted start > end range whenever $from alone was in the future.
        $start = $from ? CarbonImmutable::createFromFormat('Y-m-d', $from, $tz)->startOfDay() : $now->startOfWeek();
        $end = $to ? CarbonImmutable::createFromFormat('Y-m-d', $to, $tz)->endOfDay() : $now->endOfWeek();

        $start = self::clampFutureStart($start, $now);
        $end = self::clampFutureEnd($end, $now);

        if ($end->lessThan($start)) {
            $end = $start->endOfDay();
        }

        // Surfaced to the vendor as `range.truncated` via toArray() — a
        // vendor-picked range longer than this is silently shortened rather
        // than rejected, and without this flag the only visible trace was
        // a `from`/`to` that quietly didn't match what they'd typed
        // (2026-09-07 audit finding).
        $truncated = $start->diffInDays($end) > self::MAX_CUSTOM_DAYS;
        if ($truncated) {
            $end = $start->addDays(self::MAX_CUSTOM_DAYS)->endOfDay();
        }

        return [$start, self::clampFutureEnd($end, $now), $truncated];
    }

    /**
     * "This week"/"this month"/"this year" (and a vendor-picked custom
     * range) previously always used the FULL calendar boundary as `end` —
     * e.g. "this month" on Sep 5th queried through Sep 30th, even though
     * the rest of the month hasn't happened yet. The query itself was
     * never wrong (no orders exist in the future), but every chart that
     * fills gaps across [range.from, range.to] — Revenue Trend, Cash Flow
     * — rendered dozens of empty "future" bars, which is what actually
     * surfaced this: those charts used to be built from sparse,
     * activity-only data that could never include a future day, so this
     * bug was invisible until gap-filling made every day/month in range
     * concrete. Clamping to the end of *today* (not the exact current
     * second) keeps "today" itself fully in range, consistent with how
     * the TODAY period is already `$now->endOfDay()`.
     */
    private static function clampFutureEnd(CarbonImmutable $periodEnd, CarbonImmutable $now): CarbonImmutable
    {
        $todayEnd = $now->endOfDay();

        return $periodEnd->greaterThan($todayEnd) ? $todayEnd : $periodEnd;
    }

    /** Symmetric to clampFutureEnd() — a custom range whose `from` alone lands in the future collapses to today rather than staying ahead of a `to` that clampFutureEnd has already pulled back. */
    private static function clampFutureStart(CarbonImmutable $periodStart, CarbonImmutable $now): CarbonImmutable
    {
        $todayEnd = $now->endOfDay();

        return $periodStart->greaterThan($todayEnd) ? $now->startOfDay() : $periodStart;
    }

    /**
     * The Cash Flow Statement is always calendar-month granular (see
     * FinancialReportService::cashFlowStatement()'s doc comment) — but
     * without this, a vendor viewing "Today" or "This week" would get a
     * cash flow row scoped to just that narrow window, where lumpy
     * monthly costs (rent, a supplier settlement, a subscription renewal)
     * mostly show as zero simply because they didn't happen on that exact
     * day. That defeats the entire point of a monthly cash flow view, so
     * this expands to the full calendar month(s) containing the selected
     * period regardless of how narrow that selection is — decoupled from
     * every other section of the report, which still reflects exactly
     * what the vendor picked. Clamped with the same clampFutureEnd() used
     * for `end` above, so it never reaches into the future either.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function cashFlowRange(): array
    {
        return [
            $this->start->startOfMonth(),
            self::clampFutureEnd($this->end->endOfMonth(), CarbonImmutable::now($this->timezone)),
        ];
    }

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

    /** Bucket granularity for the trend chart + period breakdown table. */
    public function bucketUnit(): string
    {
        $days = $this->start->diffInDays($this->end) + 1;

        return match (true) {
            $days <= 62 => 'day',
            $days <= 370 => 'week',
            default => 'month',
        };
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'from' => $this->start->toDateString(),
            'to' => $this->end->toDateString(),
            'truncated' => $this->customRangeTruncated,
            'maxCustomDays' => self::MAX_CUSTOM_DAYS,
        ];
    }

    public function previousToArray(): array
    {
        return [
            'from' => $this->previousStart->toDateString(),
            'to' => $this->previousEnd->toDateString(),
        ];
    }

    /**
     * A short vendor-facing label — used by the background-report flow's
     * "preparing your report for X" / notification copy (see
     * FinancialReportJob's doc comment), where there's no dashboard UI
     * already rendering the period picker's own label.
     */
    public function humanLabel(): string
    {
        return match ($this->key) {
            self::TODAY => 'Today',
            self::WEEK => 'This Week',
            self::MONTH => 'This Month',
            self::LAST_MONTH => 'Last Month',
            self::YEAR => 'This Year',
            self::CUSTOM => "{$this->start->toDateString()} – {$this->end->toDateString()}",
            default => 'the selected period',
        };
    }
}
