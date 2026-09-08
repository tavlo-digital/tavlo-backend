<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\FormatsInsightText;

/**
 * Derives insights from the analytics payload.
 *
 * Every rule here is deterministic and reads only figures that were measured
 * from live orders. Where an insight quotes a number it is an observed gap,
 * never a projected gain — a projection would be a guess presented as data.
 *
 * Rules stay silent unless their minimum sample is met, so a quiet vendor sees
 * an empty list rather than confident nonsense drawn from a handful of orders.
 *
 * See also InsightQueryEngine, which answers a vendor's own free-text
 * questions against this same payload — it shares this class's formatting
 * helpers (FormatsInsightText) so a figure reads identically whether it
 * appears in a generated card here or in an answer there.
 */
class InsightEngine
{
    use FormatsInsightText;

    private const MIN_ORDERS = 25;

    public function derive(array $a): array
    {
        $orders = $a['summary']['orders']['value'] ?? 0;

        if (! ($a['hasData'] ?? false) || $orders < self::MIN_ORDERS) {
            return [];
        }

        $insights = array_filter([
            $this->quietHour($a),
            $this->slowDay($a),
            $this->paymentFailures($a),
            $this->serviceGap($a),
            $this->turnoverSlowing($a),
            $this->firstTimeReturn($a),
            $this->unansweredReviews($a),
            $this->revenueConcentration($a),
            $this->discountEffect($a),
            $this->tipsAndSpeed($a),
            $this->cashTipCapture($a),
            $this->decliningItem($a),
            $this->inventoryAtRisk($a),
            $this->slowMovingStock($a),
            $this->foodWaste($a),
            $this->purchaseOrderNeedsAttention($a),
            $this->priceIncrease($a),
            $this->expiryRiskPromotion($a),
            $this->loyaltyVisitImpact($a),
            $this->loyaltyStalled($a),
            $this->soldOutFrequency($a),
            $this->slowSellerPairing($a),
        ]);

        // Unlike every other rule above, this one can surface several items
        // at once (up to 5), so it returns a list rather than a single
        // nullable insight and gets merged in rather than filtered.
        $insights = array_merge($insights, $this->highInterestLowConversion($a));

        usort($insights, fn ($x, $y) => $x['priority'] <=> $y['priority']);

        return array_values($insights);
    }

    private function quietHour(array $a): ?array
    {
        $busiest = $a['peak']['busiest'] ?? null;
        $quietest = $a['peak']['quietest'] ?? null;

        if (! $busiest || ! $quietest || $busiest['orders'] < 5) {
            return null;
        }

        if ($quietest['orders'] >= $busiest['orders'] * 0.35) {
            return null;
        }

        $quietestDay = $this->dayLabel($quietest['day']);
        $quietestHour = sprintf('%02d:00', $quietest['hour']);
        $busiestDay = $this->dayLabel($busiest['day']);
        $busiestHour = sprintf('%02d:00', $busiest['hour']);
        $orderWord = __('insights.word_'.($quietest['orders'] === 1 ? 'order' : 'orders'));

        return [
            'id' => 'quiet-hour',
            'category' => 'operational',
            'priority' => 2,
            'title' => __('insights.quiet_hour_title', ['day' => $quietestDay, 'hour' => $quietestHour]),
            'description' => __('insights.quiet_hour_description', [
                'quietest_orders' => $quietest['orders'], 'order_word' => $orderWord,
                'busiest_orders' => $busiest['orders'], 'busiest_day' => $busiestDay, 'busiest_hour' => $busiestHour,
            ]),
            'impact' => [
                'value' => $busiest['orders'] - $quietest['orders'],
                'unit' => 'orders',
                'label' => __('insights.quiet_hour_impact_label'),
            ],
            'confidence' => $this->confidence((int) $a['summary']['orders']['value']),
            'whatHappening' => __('insights.quiet_hour_what_happening', [
                'period' => $this->periodPhrase($a['period'] ?? 'weekly'), 'day' => $quietestDay, 'hour' => $quietestHour,
                'quietest_orders' => $quietest['orders'], 'busiest_orders' => $busiest['orders'],
            ]),
            'whyMatters' => __('insights.quiet_hour_why_matters'),
            'suggestedAction' => __('insights.quiet_hour_suggested_action'),
            'actionScreen' => 'loyalty',
            'actionLabel' => __('insights.quiet_hour_action_label'),
        ];
    }

    private function slowDay(array $a): ?array
    {
        $days = collect($a['peak']['days'] ?? [])->filter(fn ($d) => $d['orders'] > 0);

        if ($days->count() < 5) {
            return null;
        }

        $average = $days->avg('orders');
        $slowest = $days->sortBy('orders')->first();

        if ($average <= 0 || $slowest['orders'] > $average * 0.75) {
            return null;
        }

        $gapPercent = round(((($average - $slowest['orders']) / $average) * 100), 0);
        $aov = $a['summary']['avgOrderValue']['value'] ?? 0;

        $day = $this->dayLabel($slowest['day']);

        return [
            'id' => 'slow-day',
            'category' => 'revenue',
            'priority' => 3,
            'title' => __('insights.slow_day_title', ['day' => $day, 'percent' => $gapPercent]),
            'description' => __('insights.slow_day_description', ['orders' => $slowest['orders'], 'average' => round($average)]),
            'impact' => [
                'value' => round(($average - $slowest['orders']) * $aov, 2),
                'unit' => 'currency',
                'label' => __('insights.slow_day_impact_label'),
            ],
            'confidence' => $this->confidence((int) $a['summary']['orders']['value']),
            'whatHappening' => __('insights.slow_day_what_happening', [
                'day' => $day, 'orders' => $slowest['orders'], 'average' => round($average),
            ]),
            'whyMatters' => __('insights.slow_day_why_matters'),
            'suggestedAction' => __('insights.slow_day_suggested_action', ['day' => $day]),
            'actionScreen' => 'loyalty',
            'actionLabel' => __('insights.slow_day_action_label'),
        ];
    }

    private function paymentFailures(array $a): ?array
    {
        $rate = $a['payments']['failureRate'] ?? null;
        $failed = $a['payments']['failedCount'] ?? 0;

        if ($rate === null || $rate < 3 || $failed < 3) {
            return null;
        }

        $aov = $a['summary']['avgOrderValue']['value'] ?? 0;

        return [
            'id' => 'payment-failures',
            'category' => 'operational',
            'priority' => 1,
            'title' => __('insights.payment_failures_title', ['rate' => $rate]),
            'description' => __('insights.payment_failures_description', ['failed' => $failed]),
            'impact' => [
                'value' => round($failed * $aov, 2),
                'unit' => 'currency',
                'label' => __('insights.payment_failures_impact_label'),
            ],
            'confidence' => $this->confidence((int) ($a['payments']['attempts'] ?? 0)),
            'whatHappening' => __('insights.payment_failures_what_happening', [
                'failed' => $failed, 'attempts' => $a['payments']['attempts'] ?? 0,
            ]),
            'whyMatters' => __('insights.payment_failures_why_matters'),
            'suggestedAction' => __('insights.payment_failures_suggested_action'),
            'actionScreen' => 'settings',
            'actionLabel' => __('insights.payment_failures_action_label'),
        ];
    }

    private function turnoverSlowing(array $a): ?array
    {
        $turnover = $a['service']['tableTurnoverMinutes'] ?? null;

        if (! is_array($turnover) || ($turnover['status'] ?? null) !== 'slower') {
            return null;
        }

        $current = $turnover['value'];
        $baseline = $turnover['baseline'];
        $samples = (int) ($turnover['baselineSamples'] ?? 0);
        $unit = $a['service']['baselineUnit'] ?? 'week';
        $measured = (int) ($a['service']['tableVisitsMeasured'] ?? 0);

        if ($current === null || $baseline === null || $measured < 20) {
            return null;
        }

        $extra = round($current - $baseline, 1);
        $unitWord = $this->unitWord($unit);

        return [
            'id' => 'turnover-slowing',
            'category' => 'operational',
            'priority' => 3,
            'title' => __('insights.turnover_slowing_title', ['extra' => $extra]),
            'description' => __('insights.turnover_slowing_description', [
                'current' => $current, 'samples' => $samples, 'unit' => $unitWord, 'baseline' => $baseline,
            ]),
            'impact' => [
                'value' => $extra,
                'unit' => 'minutes',
                'label' => __('insights.turnover_slowing_impact_label', ['samples' => $samples, 'unit' => $unitWord]),
            ],
            'confidence' => $this->confidence($measured),
            'whatHappening' => __('insights.turnover_slowing_what_happening', [
                'measured' => $measured, 'current' => $current, 'unit' => $unitWord, 'baseline' => $baseline,
            ]),
            'whyMatters' => __('insights.turnover_slowing_why_matters'),
            'suggestedAction' => __('insights.turnover_slowing_suggested_action'),
            'actionScreen' => 'menu',
            'actionLabel' => __('insights.turnover_slowing_action_label'),
        ];
    }

    private function serviceGap(array $a): ?array
    {
        $lunch = $a['service']['lunchMinutes']['value'] ?? null;
        $dinner = $a['service']['dinnerMinutes']['value'] ?? null;

        if ($lunch === null || $dinner === null || $lunch <= 0) {
            return null;
        }

        $gap = $dinner - $lunch;

        if ($gap < 10) {
            return null;
        }

        return [
            'id' => 'service-gap',
            'category' => 'operational',
            'priority' => 4,
            'title' => __('insights.service_gap_title', ['gap' => round($gap, 1)]),
            'description' => __('insights.service_gap_description', ['dinner' => $dinner, 'lunch' => $lunch]),
            'impact' => [
                'value' => round($gap, 1),
                'unit' => 'minutes',
                'label' => __('insights.service_gap_impact_label'),
            ],
            'confidence' => $this->confidence((int) $a['summary']['orders']['value']),
            'whatHappening' => __('insights.service_gap_what_happening', ['dinner' => $dinner, 'lunch' => $lunch]),
            'whyMatters' => __('insights.service_gap_why_matters'),
            'suggestedAction' => __('insights.service_gap_suggested_action'),
            'actionScreen' => 'menu',
            'actionLabel' => __('insights.service_gap_action_label'),
        ];
    }

    private function firstTimeReturn(array $a): ?array
    {
        if (! ($a['retention']['available'] ?? false)) {
            return null;
        }

        $new = $a['retention']['newCustomers'];
        $rate = $a['retention']['return30Rate'];

        if ($new < 20 || $rate >= 20) {
            return null;
        }

        $aov = $a['summary']['avgOrderValue']['value'] ?? 0;
        $shortfall = max(0, (int) round($new * 0.20) - $a['retention']['returnedWithin30']);

        return [
            'id' => 'first-time-return',
            'category' => 'retention',
            'priority' => 2,
            'title' => __('insights.first_time_return_title', ['rate' => $rate]),
            'description' => __('insights.first_time_return_description', [
                'returned' => $a['retention']['returnedWithin30'], 'new' => $new,
            ]),
            'impact' => [
                'value' => round($shortfall * $aov, 2),
                'unit' => 'currency',
                'label' => __('insights.first_time_return_impact_label'),
            ],
            'confidence' => $this->confidence($new),
            'whatHappening' => __('insights.first_time_return_what_happening', [
                'new' => $new, 'returned' => $a['retention']['returnedWithin30'],
            ]),
            'whyMatters' => __('insights.first_time_return_why_matters'),
            'suggestedAction' => __('insights.first_time_return_suggested_action'),
            'actionScreen' => 'loyalty',
            'actionLabel' => __('insights.first_time_return_action_label'),
        ];
    }

    private function unansweredReviews(array $a): ?array
    {
        $unanswered = $a['reviews']['unanswered'] ?? 0;
        $critical = $a['reviews']['unansweredCritical'] ?? 0;

        if ($unanswered < 3) {
            return null;
        }

        return [
            'id' => 'unanswered-reviews',
            'category' => 'reputation',
            'priority' => $critical > 0 ? 1 : 5,
            'title' => __('insights.unanswered_reviews_title', ['unanswered' => $unanswered]),
            'description' => $critical > 0
                ? __('insights.unanswered_reviews_description_critical', ['critical' => $critical])
                : __('insights.unanswered_reviews_description_none_critical'),
            'impact' => [
                'value' => $critical,
                'unit' => 'count',
                'label' => __('insights.unanswered_reviews_impact_label'),
            ],
            'confidence' => 'high',
            'whatHappening' => __('insights.unanswered_reviews_what_happening', [
                'unanswered' => $unanswered, 'critical' => $critical,
            ]),
            'whyMatters' => __('insights.unanswered_reviews_why_matters'),
            'suggestedAction' => __('insights.unanswered_reviews_suggested_action'),
            'actionScreen' => 'reviews',
            'actionLabel' => __('insights.unanswered_reviews_action_label'),
        ];
    }

    private function revenueConcentration(array $a): ?array
    {
        if (! ($a['customers']['available'] ?? false)) {
            return null;
        }

        $share = $a['customers']['topQuintileShare'] ?? null;
        $count = $a['customers']['topQuintileCount'] ?? 0;

        if ($share === null || $share < 50 || $count < 5) {
            return null;
        }

        return [
            'id' => 'revenue-concentration',
            'category' => 'retention',
            'priority' => 6,
            'title' => __('insights.revenue_concentration_title', ['share' => $share, 'count' => $count]),
            'description' => __('insights.revenue_concentration_description'),
            'impact' => [
                'value' => $count,
                'unit' => 'count',
                'label' => __('insights.revenue_concentration_impact_label'),
            ],
            'confidence' => $this->confidence((int) $a['customers']['identifiedCustomers']),
            'whatHappening' => __('insights.revenue_concentration_what_happening', [
                'count' => $count, 'identified' => $a['customers']['identifiedCustomers'], 'share' => $share,
            ]),
            'whyMatters' => __('insights.revenue_concentration_why_matters'),
            'suggestedAction' => __('insights.revenue_concentration_suggested_action'),
            'actionScreen' => 'loyalty',
            'actionLabel' => __('insights.revenue_concentration_action_label'),
        ];
    }

    private function discountEffect(array $a): ?array
    {
        if (! ($a['discounts']['available'] ?? false)) {
            return null;
        }

        $discounted = $a['discounts']['discountedAvgItems'] ?? null;
        $full = $a['discounts']['fullPriceAvgItems'] ?? null;
        $forgone = $a['discounts']['revenueForgone'] ?? 0;

        if ($discounted === null || $full === null || $forgone <= 0) {
            return null;
        }

        if ($discounted > $full) {
            return null;
        }

        return [
            'id' => 'discount-effect',
            'category' => 'revenue',
            'priority' => 4,
            'title' => __('insights.discount_effect_title'),
            'description' => __('insights.discount_effect_description', ['discounted' => $discounted, 'full' => $full]),
            'impact' => [
                'value' => $forgone,
                'unit' => 'currency',
                'label' => __('insights.discount_effect_impact_label'),
            ],
            'confidence' => $this->confidence((int) $a['discounts']['discountedOrders']),
            'whatHappening' => __('insights.discount_effect_what_happening', [
                'discounted' => $discounted, 'full' => $full, 'money' => $this->money($forgone, $a['currency']),
            ]),
            'whyMatters' => __('insights.discount_effect_why_matters'),
            'suggestedAction' => __('insights.discount_effect_suggested_action'),
            'actionScreen' => 'menu',
            'actionLabel' => __('insights.discount_effect_action_label'),
        ];
    }

    /**
     * Faster service coinciding with better tips. Reported as the correlation
     * it is, not as a proven cause — but it is measured, and it is the kind of
     * thing a floor manager can act on tonight.
     */
    private function tipsAndSpeed(array $a): ?array
    {
        $rows = $a['tips']['byServiceSpeed'] ?? [];

        if (count($rows) < 2) {
            return null;
        }

        [$fast, $slow] = $rows;

        if ($fast['tipRate'] === null || $slow['tipRate'] === null) {
            return null;
        }

        $gap = round($fast['tipRate'] - $slow['tipRate'], 1);

        if ($gap < 1.0) {
            return null;
        }

        return [
            'id' => 'tips-and-speed',
            'category' => 'operational',
            'priority' => 5,
            'title' => __('insights.tips_and_speed_title'),
            'description' => __('insights.tips_and_speed_description', [
                'fast_rate' => $fast['tipRate'], 'threshold' => $fast['thresholdMinutes'], 'slow_rate' => $slow['tipRate'],
            ]),
            'impact' => [
                'value' => $gap,
                'unit' => 'percent',
                'label' => __('insights.tips_and_speed_impact_label'),
            ],
            'confidence' => $this->confidence((int) ($fast['orders'] + $slow['orders'])),
            'whatHappening' => __('insights.tips_and_speed_what_happening', [
                'threshold' => $fast['thresholdMinutes'], 'fast_rate' => $fast['tipRate'], 'fast_orders' => $fast['orders'],
                'slow_rate' => $slow['tipRate'], 'slow_orders' => $slow['orders'],
            ]),
            'whyMatters' => __('insights.tips_and_speed_why_matters'),
            'suggestedAction' => __('insights.tips_and_speed_suggested_action'),
            'actionScreen' => 'menu',
            'actionLabel' => __('insights.tips_and_speed_action_label'),
        ];
    }

    /**
     * Cash tips recorded far less often than digital ones.
     *
     * Stated as the ambiguity it is. A digital tip is captured by the checkout;
     * a cash tip only exists in the data if the guest entered one when
     * requesting cash payment or the waiter entered it at handover. A wide gap
     * is therefore either a genuine difference in behaviour or a habit of not
     * typing them in — and only the vendor can tell which.
     */
    private function cashTipCapture(array $a): ?array
    {
        $capture = $a['tips']['cashCapture'] ?? null;

        if (! is_array($capture) || ! ($capture['comparable'] ?? false)) {
            return null;
        }

        $gap = $capture['gap'];

        if ($gap === null || $gap < 20 || $capture['cashOrders'] < 15 || $capture['digitalOrders'] < 15) {
            return null;
        }

        return [
            'id' => 'cash-tip-capture',
            'category' => 'operational',
            'priority' => 4,
            'title' => __('insights.cash_tip_capture_title'),
            'description' => __('insights.cash_tip_capture_description', [
                'cash' => $capture['cashParticipation'], 'digital' => $capture['digitalParticipation'],
            ]),
            'impact' => [
                'value' => $gap,
                'unit' => 'percent',
                'label' => __('insights.cash_tip_capture_impact_label'),
            ],
            'confidence' => $this->confidence((int) ($capture['cashOrders'] + $capture['digitalOrders'])),
            'whatHappening' => __('insights.cash_tip_capture_what_happening', [
                'cash_tipped' => $capture['cashTipped'], 'cash_orders' => $capture['cashOrders'],
                'digital' => $capture['digitalParticipation'], 'digital_orders' => $capture['digitalOrders'],
            ]),
            'whyMatters' => __('insights.cash_tip_capture_why_matters'),
            'suggestedAction' => __('insights.cash_tip_capture_suggested_action'),
            'actionScreen' => 'settings',
            'actionLabel' => __('insights.cash_tip_capture_action_label'),
        ];
    }

    private function decliningItem(array $a): ?array
    {
        $declining = collect($a['menu'] ?? [])
            ->filter(fn ($i) => $i['deltaPercent'] !== null && $i['deltaPercent'] <= -20 && $i['quantity'] >= 10)
            ->sortBy('deltaPercent')
            ->first();

        if (! $declining) {
            return null;
        }

        $percent = abs($declining['deltaPercent']);

        return [
            'id' => 'declining-item',
            'category' => 'revenue',
            'priority' => 7,
            'title' => __('insights.declining_item_title', ['name' => $declining['name'], 'percent' => $percent]),
            'description' => __('insights.declining_item_description', ['quantity' => $declining['quantity']]),
            'impact' => [
                'value' => $declining['deltaPercent'],
                'unit' => 'percent',
                'label' => __('insights.declining_item_impact_label'),
            ],
            'confidence' => $this->confidence((int) $declining['quantity']),
            'whatHappening' => __('insights.declining_item_what_happening', [
                'name' => $declining['name'], 'quantity' => $declining['quantity'], 'percent' => $percent,
            ]),
            'whyMatters' => __('insights.declining_item_why_matters'),
            'suggestedAction' => __('insights.declining_item_suggested_action'),
            'actionScreen' => 'menu',
            'actionLabel' => __('insights.declining_item_action_label'),
        ];
    }

    /**
     * Menu items sold this period whose recipe touches an ingredient that is
     * now out of stock or running low. Reads the Inventory package's own
     * quantity field, which is only as fresh as the vendor's last manual
     * count — so this flags a risk worth checking, not a certainty.
     */
    private function inventoryAtRisk(array $a): ?array
    {
        $inventory = $a['inventory'] ?? null;

        if (! is_array($inventory) || ! ($inventory['available'] ?? false)) {
            return null;
        }

        $atRisk = $inventory['atRiskMenuItems'] ?? [];
        $impact = $inventory['revenueAtRisk'] ?? 0;

        if (empty($atRisk) || $impact < 20) {
            return null;
        }

        $count = count($atRisk);
        $top = $atRisk[0];

        $money = $this->money($impact, $a['currency']);

        return [
            'id' => 'inventory-at-risk',
            'category' => 'operational',
            'priority' => 2,
            'title' => $count === 1
                ? __('insights.inventory_at_risk_title_one', ['name' => $top['name']])
                : __('insights.inventory_at_risk_title_many', ['count' => $count]),
            'description' => $count === 1
                ? __('insights.inventory_at_risk_description_one', ['name' => $top['name'], 'money' => $money])
                : __('insights.inventory_at_risk_description_many', ['money' => $money]),
            'impact' => [
                'value' => $impact,
                'unit' => 'currency',
                'label' => __('insights.inventory_at_risk_impact_label'),
            ],
            'confidence' => $count >= 3 ? 'high' : 'medium',
            'whatHappening' => __('insights.inventory_at_risk_what_happening', ['count' => $count, 'name' => $top['name']]),
            'whyMatters' => __('insights.inventory_at_risk_why_matters'),
            'suggestedAction' => __('insights.inventory_at_risk_suggested_action'),
            'actionScreen' => 'inventory',
            'actionLabel' => __('insights.inventory_at_risk_action_label'),
        ];
    }

    /**
     * Money sitting in stock that is barely moving, valued at the vendor's
     * own recorded cost per unit. Only fires when there is real cost data to
     * value it with and a meaningful amount tied up.
     */
    private function slowMovingStock(array $a): ?array
    {
        $inventory = $a['inventory'] ?? null;

        if (! is_array($inventory) || ! ($inventory['available'] ?? false)) {
            return null;
        }

        $tiedUp = $inventory['tiedUpCapital'] ?? 0;
        $movers = $inventory['slowMovers'] ?? [];

        if (empty($movers) || $tiedUp < 50) {
            return null;
        }

        $top = $movers[0];

        return [
            'id' => 'slow-moving-stock',
            'category' => 'operational',
            'priority' => 6,
            'title' => __('insights.slow_moving_stock_title', ['money' => $this->money($tiedUp, $a['currency'])]),
            'description' => __('insights.slow_moving_stock_description', [
                'name' => $top['name'], 'quantity' => $top['quantity'], 'unit' => $top['unit'],
            ]),
            'impact' => [
                'value' => $tiedUp,
                'unit' => 'currency',
                'label' => __('insights.slow_moving_stock_impact_label'),
            ],
            'confidence' => count($movers) >= 3 ? 'high' : 'medium',
            'whatHappening' => __('insights.slow_moving_stock_what_happening', [
                'count' => count($movers), 'name' => $top['name'],
            ]),
            'whyMatters' => __('insights.slow_moving_stock_why_matters'),
            'suggestedAction' => __('insights.slow_moving_stock_suggested_action'),
            'actionScreen' => 'inventory',
            'actionLabel' => __('insights.slow_moving_stock_action_label'),
        ];
    }

    /**
     * Waste recorded through the "Waste" adjustment on the Inventory page,
     * valued at the vendor's own recorded cost per unit. Reads real
     * InventoryStockMovement rows, not an estimate — if nobody logs a waste
     * adjustment this period, this stays silent rather than guessing at a
     * figure.
     */
    private function foodWaste(array $a): ?array
    {
        $inventory = $a['inventory'] ?? null;

        if (! is_array($inventory) || ! ($inventory['available'] ?? false)) {
            return null;
        }

        $waste = $inventory['waste'] ?? null;
        $totalValue = $waste['totalValue'] ?? 0;
        $items = $waste['items'] ?? [];

        if (empty($items) || $totalValue < 20) {
            return null;
        }

        $top = $items[0];

        $money = $this->money($totalValue, $a['currency']);

        return [
            'id' => 'food-waste',
            'category' => 'operational',
            'priority' => 5,
            'title' => __('insights.food_waste_title', ['money' => $money]),
            'description' => __('insights.food_waste_description', [
                'name' => $top['name'], 'quantity' => $top['quantity'], 'unit' => $top['unit'],
            ]),
            'impact' => [
                'value' => $totalValue,
                'unit' => 'currency',
                'label' => __('insights.food_waste_impact_label'),
            ],
            'confidence' => count($items) >= 3 ? 'high' : 'medium',
            'whatHappening' => __('insights.food_waste_what_happening', [
                'count' => count($items), 'name' => $top['name'], 'money' => $money,
            ]),
            'whyMatters' => __('insights.food_waste_why_matters'),
            'suggestedAction' => __('insights.food_waste_suggested_action'),
            'actionScreen' => 'inventory',
            'actionLabel' => __('insights.food_waste_action_label'),
        ];
    }

    /**
     * Purchase orders stuck failed or needing manual action — real dispatch
     * outcomes from the Inventory package's supplier-ordering flow, not a
     * projection. A pending-but-not-stuck order is not flagged; only ones the
     * vendor actually needs to act on.
     */
    private function purchaseOrderNeedsAttention(array $a): ?array
    {
        $inventory = $a['inventory'] ?? null;

        if (! is_array($inventory) || ! ($inventory['available'] ?? false)) {
            return null;
        }

        $pos = $inventory['purchaseOrders'] ?? null;
        $count = $pos['needsAttentionCount'] ?? 0;
        $needsAttention = $pos['needsAttention'] ?? [];

        if ($count < 1 || empty($needsAttention)) {
            return null;
        }

        $top = $needsAttention[0];

        return [
            'id' => 'purchase-order-needs-attention',
            'category' => 'operational',
            'priority' => 2,
            'title' => $count === 1
                ? __('insights.purchase_order_title_one', ['id' => $top['purchaseOrderPublicId']])
                : __('insights.purchase_order_title_many', ['count' => $count]),
            'description' => $top['dispatchError']
                ? __('insights.purchase_order_description_error', ['supplier' => $top['supplierName'], 'error' => $top['dispatchError']])
                : __('insights.purchase_order_description_'.($top['status'] === 'failed' ? 'failed' : 'manual'), ['supplier' => $top['supplierName']]),
            'impact' => [
                'value' => $count,
                'unit' => 'count',
                'label' => __('insights.purchase_order_impact_label'),
            ],
            'confidence' => 'high',
            'whatHappening' => __('insights.purchase_order_what_happening', [
                'count' => $count, 'id' => $top['purchaseOrderPublicId'], 'supplier' => $top['supplierName'],
            ]),
            'whyMatters' => __('insights.purchase_order_why_matters'),
            'suggestedAction' => __('insights.purchase_order_suggested_action'),
            'actionScreen' => 'inventory',
            'actionLabel' => __('insights.purchase_order_action_label'),
        ];
    }

    /**
     * A notable jump in what a supplier charges per unit, read from real
     * purchase-order history — not a warning about volatility in general,
     * just the most recent recorded change against the one before it.
     */
    private function priceIncrease(array $a): ?array
    {
        $inventory = $a['inventory'] ?? null;

        if (! is_array($inventory) || ! ($inventory['available'] ?? false)) {
            return null;
        }

        $changes = collect($inventory['priceChanges'] ?? [])
            ->filter(fn ($row) => ($row['changePercent'] ?? null) !== null && $row['changePercent'] >= 10);

        if ($changes->isEmpty()) {
            return null;
        }

        $top = $changes->sortByDesc('changePercent')->first();

        $previous = $this->money($top['previousCost'], $a['currency']);
        $latest = $this->money($top['latestCost'], $a['currency']);

        return [
            'id' => 'price-increase',
            'category' => 'operational',
            'priority' => 5,
            'title' => __('insights.price_increase_title', ['name' => $top['name'], 'percent' => $top['changePercent'], 'unit' => $top['unit']]),
            'description' => __('insights.price_increase_description', ['previous' => $previous, 'latest' => $latest, 'unit' => $top['unit']]),
            'impact' => [
                'value' => $top['changePercent'],
                'unit' => 'percent',
                'label' => __('insights.price_increase_impact_label'),
            ],
            'confidence' => 'high',
            'whatHappening' => __('insights.price_increase_what_happening', [
                'name' => $top['name'], 'latest' => $latest, 'previous' => $previous, 'unit' => $top['unit'],
            ]),
            'whyMatters' => __('insights.price_increase_why_matters'),
            'suggestedAction' => __('insights.price_increase_suggested_action'),
            'actionScreen' => 'inventory',
            'actionLabel' => __('insights.price_increase_action_label'),
        ];
    }

    /**
     * Stock the placeholder expiry-risk estimate flags as likely to sit past
     * its assumed shelf life at the current consumption pace — paired with
     * the specific menu items that use it, so the action is concrete: feature
     * or discount those dishes to move the stock before it is written off as
     * waste. The estimate itself is a placeholder (see
     * VendorAnalyticsService::inventoryExpiryRiskPlaceholder) since there is
     * no real expiry-date field yet — this rule inherits that caveat and
     * states it plainly rather than presenting the figure as measured.
     */
    private function expiryRiskPromotion(array $a): ?array
    {
        $inventory = $a['inventory'] ?? null;

        if (! is_array($inventory) || ! ($inventory['available'] ?? false)) {
            return null;
        }

        $expiryRisk = $inventory['expiryRisk'] ?? null;
        $items = $expiryRisk['items'] ?? [];
        $totalValue = $expiryRisk['totalValue'] ?? 0;

        if (empty($items) || $totalValue < 20) {
            return null;
        }

        $top = $items[0];
        $menuItems = $top['suggestedMenuItems'] ?? [];

        // A rough, clearly-labeled suggestion, not a pricing formula backed by
        // real data — the more imminent the assumed shelf life, the deeper the
        // cut needed to actually move the stock in time. A vendor's own margin
        // and judgement should set the real number; this just points a direction.
        $suggestedDiscountPercent = match (true) {
            $top['daysUntilAssumedExpiry'] <= 2 => 30,
            $top['daysUntilAssumedExpiry'] <= 5 => 20,
            default => 10,
        };

        $menuItemsList = $menuItems ? implode(' '.__('insights.word_and').' ', array_slice($menuItems, 0, 2)) : null;
        $money = $this->money($top['value'], $a['currency']);
        $items = $menuItemsList ?? __('insights.expiry_risk_fallback_dishes');

        return [
            'id' => 'expiry-risk-promotion',
            'category' => 'operational',
            'priority' => 3,
            'title' => __('insights.expiry_risk_title', [
                'items' => $items, 'quantity' => $top['quantity'], 'unit' => $top['unit'],
            ]),
            'description' => $menuItemsList
                ? __('insights.expiry_risk_description_linked', [
                    'money' => $money, 'name' => $top['name'], 'discount' => $suggestedDiscountPercent, 'items' => $menuItemsList,
                ])
                : __('insights.expiry_risk_description_unlinked', ['money' => $money]),
            'impact' => [
                'value' => $totalValue,
                'unit' => 'currency',
                'label' => __('insights.expiry_risk_impact_label'),
            ],
            'confidence' => 'low',
            'whatHappening' => __('insights.expiry_risk_what_happening', [
                'name' => $top['name'], 'quantity' => $top['quantity'], 'unit' => $top['unit'], 'money' => $money,
            ]),
            'whyMatters' => $menuItemsList
                ? __('insights.expiry_risk_why_matters_linked', ['items' => $menuItemsList, 'money' => $money])
                : __('insights.expiry_risk_why_matters_unlinked'),
            'suggestedAction' => $menuItemsList
                ? __('insights.expiry_risk_suggested_action_linked', [
                    'items' => $menuItemsList, 'discount' => $suggestedDiscountPercent, 'name' => $top['name'],
                ])
                : __('insights.expiry_risk_suggested_action_unlinked'),
            'actionScreen' => 'inventory',
            'actionLabel' => __('insights.expiry_risk_action_label'),
        ];
    }

    /**
     * Compares each loyalty member's own visit pace before and after they
     * joined — the closest reading this data can give of whether the program
     * actually changes behaviour, since it is each person measured against
     * themselves rather than against a different group of people. Fires
     * either direction: a real increase is the strongest evidence the
     * program works, and a flat or falling pace is worth knowing too.
     */
    private function loyaltyVisitImpact(array $a): ?array
    {
        $loyalty = $a['loyalty'] ?? null;

        if (! is_array($loyalty) || ! ($loyalty['available'] ?? false)) {
            return null;
        }

        $beforeAfter = $loyalty['visitFrequency']['beforeAfterJoining'] ?? null;

        if (! is_array($beforeAfter) || ! ($beforeAfter['available'] ?? false)) {
            return null;
        }

        $before = $beforeAfter['avgVisitsPerWeekBefore'] ?? null;
        $after = $beforeAfter['avgVisitsPerWeekAfter'] ?? null;
        $members = $beforeAfter['membersAnalyzed'] ?? 0;

        if ($before === null || $after === null || $before <= 0) {
            return null;
        }

        $changePercent = round((($after - $before) / $before) * 100, 1);

        if (abs($changePercent) < 10) {
            return null;
        }

        $positive = $changePercent > 0;

        return [
            'id' => 'loyalty-visit-impact',
            'category' => 'retention',
            'priority' => 3,
            'title' => $positive
                ? __('insights.loyalty_visit_title_up', ['percent' => $changePercent])
                : __('insights.loyalty_visit_title_down', ['percent' => abs($changePercent)]),
            'description' => __('insights.loyalty_visit_description', ['before' => $before, 'after' => $after, 'members' => $members]),
            'impact' => [
                'value' => $changePercent,
                'unit' => 'percent',
                'label' => __('insights.loyalty_visit_impact_label_'.($positive ? 'up' : 'down')),
            ],
            'confidence' => $this->confidence($members),
            'whatHappening' => __('insights.loyalty_visit_what_happening', ['before' => $before, 'after' => $after, 'members' => $members]),
            'whyMatters' => __('insights.loyalty_visit_why_matters_'.($positive ? 'up' : 'down')),
            'suggestedAction' => __('insights.loyalty_visit_suggested_action_'.($positive ? 'up' : 'down')),
            'actionScreen' => 'loyalty',
            'actionLabel' => __('insights.loyalty_visit_action_label'),
        ];
    }

    /**
     * Members who have earned enough points to redeem but rarely do. Only
     * fires once there is real transaction history — most vendors will not
     * meet the sample gate yet, since nothing currently awards a point
     * automatically (see VendorAnalyticsService::loyalty).
     */
    private function loyaltyStalled(array $a): ?array
    {
        $loyalty = $a['loyalty'] ?? null;

        if (! is_array($loyalty) || ! ($loyalty['available'] ?? false)) {
            return null;
        }

        $eligible = $loyalty['eligibleToRedeem'] ?? 0;
        $rate = $loyalty['redemptionRate'] ?? null;
        $inactive = $loyalty['eligibleInactiveCount'] ?? 0;

        if ($eligible < 5 || $rate === null || $rate >= 30) {
            return null;
        }

        return [
            'id' => 'loyalty-redemption-stalled',
            'category' => 'retention',
            'priority' => 4,
            'title' => __('insights.loyalty_stalled_title', ['eligible' => $eligible]),
            'description' => $inactive > 0
                ? __('insights.loyalty_stalled_description_inactive', ['inactive' => $inactive])
                : __('insights.loyalty_stalled_description_rate', ['rate' => $rate]),
            'impact' => [
                'value' => $loyalty['outstandingLiability'],
                'unit' => 'currency',
                'label' => __('insights.loyalty_stalled_impact_label'),
            ],
            'confidence' => $this->confidence($eligible),
            'whatHappening' => __('insights.loyalty_stalled_what_happening', [
                'eligible' => $eligible, 'rate' => $rate, 'inactive' => $inactive,
            ]),
            'whyMatters' => __('insights.loyalty_stalled_why_matters'),
            'suggestedAction' => $inactive > 0
                ? __('insights.loyalty_stalled_suggested_action_inactive', ['inactive' => $inactive])
                : __('insights.loyalty_stalled_suggested_action_default'),
            'actionScreen' => 'loyalty',
            'actionLabel' => __('insights.loyalty_stalled_action_label'),
        ];
    }

    /**
     * How often a menu item's availability flipped off this period, read
     * from VendorActivity (MenuItemController writes a row on every toggle
     * — see VendorAnalyticsService::soldOutFrequency()). MenuItem.available
     * isn't part of the versioned-field list, so without this the toggle
     * leaves no trace anywhere else a vendor could see it add up.
     */
    private function soldOutFrequency(array $a): ?array
    {
        $events = $a['soldOut'] ?? [];

        if (empty($events)) {
            return null;
        }

        $top = $events[0];

        if ($top['count'] < 2) {
            return null;
        }

        return [
            'id' => 'sold-out-frequency',
            'category' => 'operational',
            'priority' => 5,
            'title' => __('insights.sold_out_title', ['name' => $top['name'], 'count' => $top['count']]),
            'description' => __('insights.sold_out_description', ['count' => $top['count']]),
            'impact' => [
                'value' => $top['count'],
                'unit' => 'count',
                'label' => __('insights.sold_out_impact_label'),
            ],
            'confidence' => $top['count'] >= 5 ? 'high' : ($top['count'] >= 3 ? 'medium' : 'low'),
            'whatHappening' => __('insights.sold_out_what_happening', ['name' => $top['name'], 'count' => $top['count']]),
            'whyMatters' => __('insights.sold_out_why_matters'),
            'suggestedAction' => __('insights.sold_out_suggested_action'),
            'actionScreen' => 'inventory',
            'actionLabel' => __('insights.sold_out_action_label'),
        ];
    }

    /**
     * Compares real item-detail views (MenuItemView, written on every open
     * of a dish's detail screen — see RestaurantController::menuItem())
     * against actual orders for the same item, to surface dishes that draw
     * attention but lose the guest before they order. The gap itself is the
     * whole signal: no revenue projection here, since there's no measured
     * conversion rate to project one from — only an observed shortfall.
     *
     * React Query caches the detail endpoint for 60s client-side, so a
     * guest re-opening the same item within a minute is served from cache
     * and never reaches the server. That makes this an undercount of real
     * interest, not an inflated one — safe to compare items to each other,
     * not to read as an absolute open-through rate. Confidence stays low
     * unconditionally for that reason, regardless of sample size.
     *
     * Unlike the rest of this file's rules, this one can fire for more than
     * one item at a time — every dish clearing the bar is worth a look, not
     * just the single worst offender — so it returns a list (0 to 5 items,
     * worst ratio first) instead of at most one insight.
     */
    private function highInterestLowConversion(array $a): array
    {
        return collect($a['menu'] ?? [])
            ->filter(fn ($row) => ($row['views'] ?? 0) >= 10 && $row['quantity'] > 0)
            ->map(fn ($row) => $row + ['ratio' => $row['views'] / $row['quantity']])
            ->filter(fn ($row) => $row['ratio'] >= 3)
            ->sortByDesc('ratio')
            ->take(5)
            ->map(function ($item) {
                $gap = $item['views'] - $item['quantity'];

                $ratio = $this->ratioLabel($item['ratio']);

                return [
                    'id' => 'high-interest-low-conversion-'.$item['productUid'],
                    'category' => 'revenue',
                    'priority' => 7,
                    'title' => __('insights.high_interest_title', ['name' => $item['name']]),
                    'description' => __('insights.high_interest_description', [
                        'views' => $item['views'], 'quantity' => $item['quantity'], 'ratio' => $ratio,
                    ]),
                    'impact' => [
                        'value' => $gap,
                        'unit' => 'count',
                        'label' => __('insights.high_interest_impact_label'),
                    ],
                    'confidence' => 'low',
                    'whatHappening' => __('insights.high_interest_what_happening', [
                        'name' => $item['name'], 'views' => $item['views'], 'quantity' => $item['quantity'], 'ratio' => $ratio,
                    ]),
                    'whyMatters' => __('insights.high_interest_why_matters'),
                    'suggestedAction' => __('insights.high_interest_suggested_action'),
                    'actionScreen' => 'menu',
                    'actionLabel' => __('insights.high_interest_action_label'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * A menu item selling far below the rest of the active menu, paired with
     * the vendor's own current best seller. Pairing a slow mover with a
     * proven favorite — a combo/bundle at a modest discount, or a "buy the
     * best seller, get this one at a discount" BOGO — tends to move more of
     * a genuinely slow item than discounting it in isolation, since it rides
     * demand that already exists instead of asking a guest to pick something
     * nobody else is ordering. Both mechanics are real promotion types on
     * the Loyalty & Promotions page, so the suggestion names something the
     * vendor can act on immediately rather than a generic "discount it."
     *
     * Kept in sync by hand with InsightQueryEngine's own copy of this same
     * selection logic (see its slowSellerPairingSuggestion()), the same way
     * every other rule in this file has its own independent counterpart
     * there rather than a shared helper — so a vendor who asks "what should
     * I do" gets the identical suggestion the card already showed them.
     */
    private function slowSellerPairing(array $a): ?array
    {
        $menu = collect($a['menu'] ?? [])->filter(fn ($i) => $i['available']);

        // A short menu has no real "underperformer" to single out — nearly
        // everything on a 4-5 item menu is already a top seller by definition.
        if ($menu->count() < 6) {
            return null;
        }

        $sold = $menu->filter(fn ($i) => $i['quantity'] > 0);

        if ($sold->count() < 4) {
            return null;
        }

        $bestSeller = $sold->sortByDesc('quantity')->first();
        $average = $sold->avg('quantity');

        if ($average <= 0 || $bestSeller['quantity'] < 10) {
            return null;
        }

        // "Very low selling" — sold at least once (zero sales is an
        // availability or menu-placement question, not a promotion one) but
        // far under the menu's own average, and not the best seller itself.
        $slowSeller = $sold
            ->reject(fn ($i) => $i['productUid'] === $bestSeller['productUid'])
            ->filter(fn ($i) => $i['quantity'] <= $average * 0.25)
            ->sortBy('quantity')
            ->first();

        if (! $slowSeller) {
            return null;
        }

        $gapPercent = (int) round((1 - ($slowSeller['quantity'] / $average)) * 100);

        // Rougher the item's showing against the menu average, the bigger
        // the nudge suggested — a direction from real data, not a pricing
        // formula (same reasoning as expiryRiskPromotion's own tiered
        // percentage).
        $suggestedDiscountPercent = match (true) {
            $slowSeller['quantity'] <= $average * 0.1 => 25,
            $slowSeller['quantity'] <= $average * 0.2 => 15,
            default => 10,
        };

        return [
            'id' => 'slow-seller-pairing-'.$slowSeller['productUid'],
            'category' => 'revenue',
            'priority' => 6,
            'title' => __('insights.slow_seller_pairing_title', ['name' => $slowSeller['name']]),
            'description' => __('insights.slow_seller_pairing_description', [
                'quantity' => $slowSeller['quantity'], 'average' => round($average),
            ]),
            'impact' => [
                'value' => $gapPercent,
                'unit' => 'percent',
                'label' => __('insights.slow_seller_pairing_impact_label'),
            ],
            'confidence' => $this->confidence((int) $bestSeller['quantity']),
            'whatHappening' => __('insights.slow_seller_pairing_what_happening', [
                'name' => $slowSeller['name'], 'quantity' => $slowSeller['quantity'], 'average' => round($average),
            ]),
            'whyMatters' => __('insights.slow_seller_pairing_why_matters'),
            'suggestedAction' => __('insights.slow_seller_pairing_suggested_action', [
                'slow_name' => $slowSeller['name'], 'best_name' => $bestSeller['name'], 'discount' => $suggestedDiscountPercent,
            ]),
            'actionScreen' => 'loyalty',
            'actionLabel' => __('insights.slow_seller_pairing_action_label'),
        ];
    }
}
