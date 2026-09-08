<?php

namespace App\Services\Analytics\Concerns;

/**
 * Text-formatting helpers shared by InsightEngine (generated insight cards)
 * and InsightQueryEngine (answers to a vendor's own questions) — split out
 * so the query engine renders numbers and day/period labels exactly the way
 * the cards already do, rather than drifting into a second, slightly
 * different vocabulary for the same figures.
 */
trait FormatsInsightText
{
    /**
     * Mirrors the frontend's money() formatter (symbol + value, always 2
     * decimals) — insight text is read on its own, often out of context of
     * any currency-labelled figure nearby, so a bare "1,254.87" reads as
     * ambiguous rather than obviously a money figure. Never rounds to whole
     * units for large amounts: an insight sentence quoting "€1,254" next to
     * a dashboard figure of "€1,254.87" for the same value would read as a
     * disagreement between the two, even though nothing is actually wrong.
     */
    private function money(float $value, string $currency): string
    {
        $symbol = match (strtoupper($currency)) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            default => strtoupper($currency).' ',
        };

        return $symbol.number_format($value, 2);
    }

    private function confidence(int $sample): string
    {
        return match (true) {
            $sample >= 200 => 'high',
            $sample >= 50 => 'medium',
            default => 'low',
        };
    }

    /**
     * Peak-time day fields (see VendorAnalyticsService::peak()) are always one
     * of the fixed English abbreviations Mon..Sun, never Carbon-localized —
     * translated the same way PeakTimes.tsx translates them on the frontend.
     */
    private function dayLabel(string $abbr): string
    {
        $key = ['Mon' => 'day_mon', 'Tue' => 'day_tue', 'Wed' => 'day_wed', 'Thu' => 'day_thu', 'Fri' => 'day_fri', 'Sat' => 'day_sat', 'Sun' => 'day_sun'][$abbr] ?? null;

        return $key ? __('insights.'.$key) : $abbr;
    }

    /** service.baselineUnit is always 'day'|'week'|'month' — translated as its own word so it reads naturally inside a sentence. */
    private function unitWord(string $unit): string
    {
        return __('insights.word_'.($unit === 'day' ? 'day' : ($unit === 'month' ? 'month' : 'week')));
    }

    /**
     * A lowercase phrase for "Across {period}, ..." — built from the period
     * key rather than reusing AnalyticsPeriod::$label, since that field is a
     * fixed English literal and the period key is all quietHour() needs.
     */
    private function periodPhrase(string $periodKey): string
    {
        return __('insights.period_'.($periodKey === 'daily' ? 'daily' : ($periodKey === 'monthly' ? 'monthly' : 'weekly')));
    }

    private function ratioLabel(float $ratio): string
    {
        return round($ratio, 1).'×';
    }
}
