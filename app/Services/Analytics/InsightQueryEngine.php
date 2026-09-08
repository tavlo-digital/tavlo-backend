<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Concerns\FormatsInsightText;
use Illuminate\Support\Str;

/**
 * Answers a vendor's own question against the same analytics payload
 * InsightEngine reads — the queryable half of the assistant, alongside
 * InsightEngine's generated cards.
 *
 * Two ways in:
 *  - A follow-up on a specific generated card (insightId given): re-derives
 *    that card via InsightEngine and answers from its own text, so "why does
 *    that matter" / "what should I do" always agrees word-for-word with the
 *    card the vendor is looking at.
 *  - A free-text topic question (no insightId, or an insightId that no
 *    longer fires): matched against a fixed set of topics by keyword, each
 *    backed by a handler that reads the payload the same way an InsightEngine
 *    rule does.
 *
 * Deliberately not an LLM integration: every answer here is a fixed sentence
 * built from measured figures, matched by a fixed keyword list. That keeps
 * this consistent with InsightEngine's own rule — never present a guess as
 * data — and keeps a vendor's business figures from ever being sent to a
 * third-party model. It also means the assistant only ever "knows" what the
 * topic handlers below explicitly cover; an unmatched question gets an
 * honest "can't answer that yet" rather than an invented answer.
 *
 * A topic handler answers a direct factual question ("what's my quietest
 * hour") whenever there is enough sample to measure it. That is a lower bar
 * than the matching InsightEngine rule, which additionally gates on the
 * figure being notable enough to surface unprompted (e.g. quietHour() only
 * fires as a card when the gap is wide) — a vendor who asks outright wants
 * the number either way, not just the alarming case.
 */
class InsightQueryEngine
{
    use FormatsInsightText;

    private const MIN_ORDERS = 25;

    /**
     * Ordered narrow-to-broad: a question naming a specific topic (tips,
     * loyalty, inventory...) should match that topic before it falls through
     * to the generic orders/revenue catch-all, so summary() is listed last.
     *
     * @var array<int, array{id: string, keywords: array<int, string>, handler: string, example: string}>
     */
    private array $topics;

    public function __construct()
    {
        $this->topics = [
            [
                'id' => 'busiest-quietest',
                'keywords' => ['busiest', 'quietest', 'quiet hour', 'quiet time', 'peak hour', 'peak time', 'rush', 'best time', 'worst time'],
                'handler' => 'busiestQuietest',
                'example' => 'What is my busiest hour?',
            ],
            [
                'id' => 'slowest-day',
                'keywords' => ['slow day', 'slowest day', 'quietest day', 'which day', 'best day', 'worst day'],
                'handler' => 'slowestDay',
                'example' => 'Which day of the week is slowest?',
            ],
            [
                'id' => 'payment-failures',
                'keywords' => ['payment fail', 'payments fail', 'declined', 'failed payment', 'checkout fail', 'card decline'],
                'handler' => 'paymentFailures',
                'example' => 'How many payments are failing?',
            ],
            [
                'id' => 'service-speed',
                'keywords' => ['table turnover', 'turn tables', 'service speed', 'how long', 'lunch vs dinner', 'service time', 'served'],
                'handler' => 'serviceSpeed',
                'example' => 'How fast are tables turning over?',
            ],
            [
                'id' => 'retention',
                'keywords' => ['return rate', 'repeat customer', 'come back', 'retention', 'new customer', 'first-time guest', 'first time guest'],
                'handler' => 'retention',
                'example' => 'How many first-time guests come back?',
            ],
            [
                'id' => 'reviews',
                'keywords' => ['review', 'rating', 'unanswered'],
                'handler' => 'reviews',
                'example' => 'Do I have unanswered reviews?',
            ],
            [
                'id' => 'revenue-concentration',
                'keywords' => ['top customer', 'best customer', 'biggest spender', 'revenue concentration', 'regulars'],
                'handler' => 'revenueConcentration',
                'example' => 'How much of my revenue comes from my top customers?',
            ],
            [
                'id' => 'discounts',
                'keywords' => ['discount', 'promo', 'coupon', 'voucher'],
                'handler' => 'discounts',
                'example' => 'Are my discounts working?',
            ],
            [
                'id' => 'tips',
                'keywords' => ['tip', 'tipping', 'gratuity'],
                'handler' => 'tips',
                'example' => 'How are my tips trending?',
            ],
            [
                'id' => 'sold-out',
                'keywords' => ['sold out', 'went unavailable', '86', 'marked unavailable'],
                'handler' => 'soldOut',
                'example' => 'Which items keep going unavailable?',
            ],
            [
                'id' => 'menu-items',
                'keywords' => [
                    // 'best and worst' catches this topic's own example question
                    // ("What are my best and worst selling items?"), which
                    // otherwise matched none of its topic's keywords — a
                    // pre-existing gap noticed while adding the pairing keywords below.
                    'best seller', 'bestseller', 'top item', 'top seller', 'worst item', 'best and worst', 'declining', 'popular dish', 'popular item', 'menu item',
                    'underperform', 'not selling', 'slow seller', 'slow mover', 'boost sales', 'sell more', 'pair', 'combo idea', 'promotion idea',
                ],
                'handler' => 'menuItems',
                'example' => 'What are my best and worst selling items?',
            ],
            [
                'id' => 'inventory',
                'keywords' => ['inventory', 'stock', 'out of stock', 'waste', 'purchase order', 'ingredient price', 'expiring', 'expiry'],
                'handler' => 'inventoryRisk',
                'example' => 'Is any stock at risk of running out?',
            ],
            [
                'id' => 'loyalty',
                'keywords' => ['loyalty', 'reward', 'points', 'redeem'],
                'handler' => 'loyalty',
                'example' => 'Is my loyalty program working?',
            ],
            [
                'id' => 'summary',
                'keywords' => ['revenue', 'sales', 'orders', 'order value', 'aov', 'how much did i make', 'how am i doing'],
                'handler' => 'summary',
                'example' => 'How many orders did I get this period?',
            ],
        ];
    }

    /**
     * @return array{
     *   question: string, insightId: ?string, matched: bool, topic: ?string,
     *   answer: string, data: ?array, confidence: ?string, suggestedQuestions: array<int, string>
     * }
     */
    public function ask(array $a, string $question, ?string $insightId = null): array
    {
        $question = trim($question);

        if ($insightId !== null) {
            $followUp = $this->answerFollowUp($a, $question, $insightId);

            if ($followUp !== null) {
                return array_merge($followUp, [
                    'question' => $question,
                    'insightId' => $insightId,
                    'suggestedQuestions' => $this->suggestedQuestions($a),
                ]);
            }

            return [
                'question' => $question,
                'insightId' => $insightId,
                'matched' => false,
                'topic' => null,
                'answer' => __('insight_assistant.insight_not_active'),
                'data' => null,
                'confidence' => null,
                'suggestedQuestions' => $this->suggestedQuestions($a),
            ];
        }

        $normalized = Str::lower($question);
        $matchedTopic = null;

        foreach ($this->topics as $topic) {
            if (Str::contains($normalized, $topic['keywords'], ignoreCase: true)) {
                $matchedTopic = $topic;
                break;
            }
        }

        if ($matchedTopic === null) {
            return [
                'question' => $question,
                'insightId' => null,
                'matched' => false,
                'topic' => null,
                'answer' => __('insight_assistant.no_match'),
                'data' => null,
                'confidence' => null,
                'suggestedQuestions' => $this->suggestedQuestions($a),
            ];
        }

        $result = $this->{$matchedTopic['handler']}($a);

        if ($result === null) {
            return [
                'question' => $question,
                'insightId' => null,
                'matched' => false,
                'topic' => $matchedTopic['id'],
                'answer' => __('insight_assistant.not_enough_data'),
                'data' => null,
                'confidence' => null,
                'suggestedQuestions' => $this->suggestedQuestions($a),
            ];
        }

        return [
            'question' => $question,
            'insightId' => null,
            'matched' => true,
            'topic' => $matchedTopic['id'],
            'answer' => $result['answer'],
            'data' => $result['data'],
            'confidence' => $result['confidence'],
            'suggestedQuestions' => $this->suggestedQuestions($a),
        ];
    }

    /**
     * The example questions currently worth asking — only topics whose
     * handler actually returns data for this payload, so a brand-new vendor
     * is not prompted to ask about figures that do not exist yet.
     *
     * @return array<int, string>
     */
    public function suggestedQuestions(array $a, int $limit = 6): array
    {
        $available = [];

        foreach ($this->topics as $topic) {
            if ($this->{$topic['handler']}($a) !== null) {
                $available[] = $topic['example'];
            }

            if (count($available) >= $limit) {
                break;
            }
        }

        return $available;
    }

    // ----------------------------------------------------------------
    // Follow-up on a generated card
    // ----------------------------------------------------------------

    /** @return array{matched: bool, topic: ?string, answer: string, data: ?array, confidence: ?string}|null */
    private function answerFollowUp(array $a, string $question, string $insightId): ?array
    {
        $insight = collect((new InsightEngine)->derive($a))->firstWhere('id', $insightId);

        if (! $insight) {
            return null;
        }

        $normalized = Str::lower($question);

        $field = match (true) {
            Str::contains($normalized, ['why', 'matter'], ignoreCase: true) => 'whyMatters',
            Str::contains($normalized, ['what should', 'what can i do', 'how do i', 'how can i', 'fix', 'action'], ignoreCase: true) => 'suggestedAction',
            default => 'whatHappening',
        };

        return [
            'matched' => true,
            'topic' => 'insight-followup',
            'answer' => $insight[$field],
            'data' => [
                'insightId' => $insight['id'],
                'title' => $insight['title'],
                'whatHappening' => $insight['whatHappening'],
                'whyMatters' => $insight['whyMatters'],
                'suggestedAction' => $insight['suggestedAction'],
                'impact' => $insight['impact'],
            ],
            'confidence' => $insight['confidence'],
        ];
    }

    // ----------------------------------------------------------------
    // Topic handlers — each ?array{answer: string, data: array, confidence: string}
    // ----------------------------------------------------------------

    private function summary(array $a): ?array
    {
        if (! ($a['hasData'] ?? false)) {
            return null;
        }

        $orders = (int) ($a['summary']['orders']['value'] ?? 0);

        if ($orders < 1) {
            return null;
        }

        $revenue = $a['summary']['grossRevenue']['value'] ?? 0;
        $aov = $a['summary']['avgOrderValue']['value'] ?? 0;
        $currency = $a['currency'] ?? 'EUR';

        return [
            'answer' => __('insight_assistant.summary_answer', [
                'orders' => $orders,
                'revenue' => $this->money((float) $revenue, $currency),
                'aov' => $this->money((float) $aov, $currency),
                'period' => $this->periodPhrase($a['period'] ?? 'weekly'),
            ]),
            'data' => ['orders' => $orders, 'revenue' => $revenue, 'avgOrderValue' => $aov],
            'confidence' => $this->confidence($orders),
        ];
    }

    private function busiestQuietest(array $a): ?array
    {
        $busiest = $a['peak']['busiest'] ?? null;
        $quietest = $a['peak']['quietest'] ?? null;

        if (! $busiest || ! $quietest || $busiest['orders'] < 5) {
            return null;
        }

        return [
            'answer' => __('insight_assistant.busiest_quietest_answer', [
                'busiest_day' => $this->dayLabel($busiest['day']),
                'busiest_hour' => sprintf('%02d:00', $busiest['hour']),
                'busiest_orders' => $busiest['orders'],
                'quietest_day' => $this->dayLabel($quietest['day']),
                'quietest_hour' => sprintf('%02d:00', $quietest['hour']),
                'quietest_orders' => $quietest['orders'],
            ]),
            'data' => ['busiest' => $busiest, 'quietest' => $quietest],
            'confidence' => $this->confidence((int) ($a['summary']['orders']['value'] ?? 0)),
        ];
    }

    private function slowestDay(array $a): ?array
    {
        $days = collect($a['peak']['days'] ?? [])->filter(fn ($d) => $d['orders'] > 0);

        if ($days->count() < 5) {
            return null;
        }

        $average = $days->avg('orders');
        $slowest = $days->sortBy('orders')->first();
        $busiest = $days->sortByDesc('orders')->first();

        if ($average <= 0) {
            return null;
        }

        return [
            'answer' => __('insight_assistant.slowest_day_answer', [
                'slowest_day' => $this->dayLabel($slowest['day']),
                'slowest_orders' => $slowest['orders'],
                'busiest_day' => $this->dayLabel($busiest['day']),
                'busiest_orders' => $busiest['orders'],
                'average' => round($average, 1),
            ]),
            'data' => ['slowest' => $slowest, 'busiest' => $busiest, 'average' => round($average, 1)],
            'confidence' => $this->confidence((int) ($a['summary']['orders']['value'] ?? 0)),
        ];
    }

    private function paymentFailures(array $a): ?array
    {
        $rate = $a['payments']['failureRate'] ?? null;
        $attempts = $a['payments']['attempts'] ?? 0;
        $failed = $a['payments']['failedCount'] ?? 0;

        if ($rate === null || $attempts < 1) {
            return null;
        }

        return [
            'answer' => __('insight_assistant.payment_failures_answer', [
                'rate' => $rate, 'failed' => $failed, 'attempts' => $attempts,
            ]),
            'data' => ['rate' => $rate, 'failed' => $failed, 'attempts' => $attempts],
            'confidence' => $this->confidence((int) $attempts),
        ];
    }

    private function serviceSpeed(array $a): ?array
    {
        $turnover = $a['service']['tableTurnoverMinutes'] ?? null;
        $lunch = $a['service']['lunchMinutes']['value'] ?? null;
        $dinner = $a['service']['dinnerMinutes']['value'] ?? null;

        $hasTurnover = is_array($turnover) && $turnover['value'] !== null;
        $hasLunchDinner = $lunch !== null && $dinner !== null;

        if (! $hasTurnover && ! $hasLunchDinner) {
            return null;
        }

        $parts = [];
        $data = [];

        if ($hasTurnover) {
            $parts[] = ($turnover['baseline'] ?? null) !== null
                ? __('insight_assistant.service_speed_turnover_with_baseline_fragment', [
                    'current' => $turnover['value'], 'baseline' => $turnover['baseline'],
                ])
                : __('insight_assistant.service_speed_turnover_fragment', ['current' => $turnover['value']]);
            $data['turnover'] = $turnover;
        }

        if ($hasLunchDinner) {
            $parts[] = __('insight_assistant.service_speed_lunch_dinner_fragment', [
                'lunch' => $lunch, 'dinner' => $dinner,
            ]);
            $data['lunchMinutes'] = $lunch;
            $data['dinnerMinutes'] = $dinner;
        }

        return [
            'answer' => implode(' ', $parts),
            'data' => $data,
            'confidence' => $this->confidence((int) ($a['service']['tableVisitsMeasured'] ?? $a['summary']['orders']['value'] ?? 0)),
        ];
    }

    private function retention(array $a): ?array
    {
        if (! ($a['retention']['available'] ?? false)) {
            return null;
        }

        $new = $a['retention']['newCustomers'];
        $rate = $a['retention']['return30Rate'];
        $returned = $a['retention']['returnedWithin30'];

        if ($new < 1) {
            return null;
        }

        return [
            'answer' => __('insight_assistant.retention_answer', [
                'returned' => $returned, 'new' => $new, 'rate' => $rate,
            ]),
            'data' => ['newCustomers' => $new, 'returnedWithin30' => $returned, 'return30Rate' => $rate],
            'confidence' => $this->confidence($new),
        ];
    }

    private function reviews(array $a): ?array
    {
        if (! isset($a['reviews'])) {
            return null;
        }

        $unanswered = $a['reviews']['unanswered'] ?? 0;
        $critical = $a['reviews']['unansweredCritical'] ?? 0;
        $count = $a['reviews']['count'] ?? null;

        return [
            'answer' => $unanswered > 0
                ? __('insight_assistant.reviews_answer_pending', ['unanswered' => $unanswered, 'critical' => $critical])
                : __('insight_assistant.reviews_answer_clear'),
            'data' => ['unanswered' => $unanswered, 'unansweredCritical' => $critical, 'reviewsThisPeriod' => $count],
            'confidence' => 'high',
        ];
    }

    private function revenueConcentration(array $a): ?array
    {
        if (! ($a['customers']['available'] ?? false)) {
            return null;
        }

        $share = $a['customers']['topQuintileShare'] ?? null;
        $count = $a['customers']['topQuintileCount'] ?? 0;
        $identified = $a['customers']['identifiedCustomers'] ?? 0;

        if ($share === null || $count < 1) {
            return null;
        }

        return [
            'answer' => __('insight_assistant.revenue_concentration_answer', [
                'share' => $share, 'count' => $count, 'identified' => $identified,
            ]),
            'data' => ['topQuintileShare' => $share, 'topQuintileCount' => $count, 'identifiedCustomers' => $identified],
            'confidence' => $this->confidence((int) $identified),
        ];
    }

    private function discounts(array $a): ?array
    {
        if (! ($a['discounts']['available'] ?? false)) {
            return null;
        }

        $discounted = $a['discounts']['discountedAvgItems'] ?? null;
        $full = $a['discounts']['fullPriceAvgItems'] ?? null;
        $forgone = $a['discounts']['revenueForgone'] ?? 0;
        $orders = $a['discounts']['discountedOrders'] ?? 0;

        if ($discounted === null || $full === null || $orders < 1) {
            return null;
        }

        $currency = $a['currency'] ?? 'EUR';
        $biggerBasket = $discounted > $full;

        return [
            'answer' => __('insight_assistant.discounts_answer', [
                'discounted' => $discounted, 'full' => $full,
                'forgone' => $this->money((float) $forgone, $currency),
                'effect' => $biggerBasket ? __('insight_assistant.word_bigger') : __('insight_assistant.word_not_bigger'),
            ]),
            'data' => ['discountedAvgItems' => $discounted, 'fullPriceAvgItems' => $full, 'revenueForgone' => $forgone, 'discountedOrders' => $orders],
            'confidence' => $this->confidence((int) $orders),
        ];
    }

    private function tips(array $a): ?array
    {
        if (! ($a['tips']['available'] ?? false)) {
            return null;
        }

        $rows = $a['tips']['byServiceSpeed'] ?? [];
        $capture = $a['tips']['cashCapture'] ?? null;
        $overall = $a['tips']['tipRate']['value'] ?? null;

        $hasSpeedRows = count($rows) >= 2 && $rows[0]['tipRate'] !== null && $rows[1]['tipRate'] !== null;
        $hasCapture = is_array($capture) && ($capture['comparable'] ?? false) && $capture['gap'] !== null;

        if (! $hasSpeedRows && ! $hasCapture && $overall === null) {
            return null;
        }

        $parts = [];
        $data = [];

        if ($overall !== null) {
            $parts[] = __('insight_assistant.tips_overall_fragment', ['rate' => $overall]);
            $data['averageRate'] = $overall;
        }

        if ($hasSpeedRows) {
            [$fast, $slow] = $rows;
            $parts[] = __('insight_assistant.tips_speed_fragment', [
                'fast_rate' => $fast['tipRate'], 'threshold' => $fast['thresholdMinutes'], 'slow_rate' => $slow['tipRate'],
            ]);
            $data['byServiceSpeed'] = $rows;
        }

        if ($hasCapture) {
            $parts[] = __('insight_assistant.tips_capture_fragment', [
                'cash' => $capture['cashParticipation'], 'digital' => $capture['digitalParticipation'],
            ]);
            $data['cashCapture'] = $capture;
        }

        if ($parts === []) {
            return null;
        }

        return [
            'answer' => implode(' ', $parts),
            'data' => $data,
            'confidence' => $this->confidence((int) ($a['summary']['orders']['value'] ?? 0)),
        ];
    }

    private function soldOut(array $a): ?array
    {
        $events = $a['soldOut'] ?? [];

        if (empty($events)) {
            return null;
        }

        $top3 = array_slice($events, 0, 3);

        return [
            'answer' => __('insight_assistant.sold_out_answer', [
                'count' => count($events),
                'names' => implode(', ', array_column($top3, 'name')),
            ]),
            'data' => ['events' => $top3, 'total' => count($events)],
            'confidence' => ($top3[0]['count'] ?? 0) >= 3 ? 'high' : 'medium',
        ];
    }

    private function menuItems(array $a): ?array
    {
        $menu = collect($a['menu'] ?? [])->filter(fn ($i) => $i['quantity'] > 0);

        if ($menu->count() < 1) {
            return null;
        }

        $topSellers = $menu->sortByDesc('quantity')->take(3)->values();
        $decliner = $menu->filter(fn ($i) => $i['deltaPercent'] !== null && $i['deltaPercent'] < 0)
            ->sortBy('deltaPercent')
            ->first();
        $pairing = $this->slowSellerPairingSuggestion($a);

        $answer = trim(__('insight_assistant.menu_items_answer', [
            'top' => implode(', ', $topSellers->pluck('name')->all()),
            'decliner' => $decliner
                ? __('insight_assistant.menu_items_decliner_fragment', ['name' => $decliner['name'], 'percent' => abs($decliner['deltaPercent'])])
                : '',
        ]));

        if ($pairing !== null) {
            $answer .= ' '.$pairing['fragment'];
        }

        return [
            'answer' => $answer,
            'data' => array_filter([
                'topSellers' => $topSellers->all(),
                'decliner' => $decliner,
                'pairingSuggestion' => $pairing['data'] ?? null,
            ], fn ($v) => $v !== null),
            'confidence' => $this->confidence((int) $menu->sum('quantity')),
        ];
    }

    /**
     * Same selection logic as InsightEngine::slowSellerPairing() — see that
     * method's doc comment for why this is a hand-kept duplicate rather than
     * a shared helper, matching every other rule in these two classes.
     * Returns null silently (never surfaced as "not enough data") since this
     * is an enrichment of the menu-items answer, not its own topic — a
     * vendor asking about best/worst sellers should still get that answer
     * even when no pairing candidate happens to qualify.
     */
    private function slowSellerPairingSuggestion(array $a): ?array
    {
        $menu = collect($a['menu'] ?? [])->filter(fn ($i) => $i['available']);

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

        $slowSeller = $sold
            ->reject(fn ($i) => $i['productUid'] === $bestSeller['productUid'])
            ->filter(fn ($i) => $i['quantity'] <= $average * 0.25)
            ->sortBy('quantity')
            ->first();

        if (! $slowSeller) {
            return null;
        }

        $suggestedDiscountPercent = match (true) {
            $slowSeller['quantity'] <= $average * 0.1 => 25,
            $slowSeller['quantity'] <= $average * 0.2 => 15,
            default => 10,
        };

        return [
            'fragment' => __('insight_assistant.menu_pairing_fragment', [
                'slow_name' => $slowSeller['name'], 'best_name' => $bestSeller['name'], 'discount' => $suggestedDiscountPercent,
            ]),
            'data' => [
                'slowSeller' => $slowSeller['name'],
                'bestSeller' => $bestSeller['name'],
                'suggestedDiscountPercent' => $suggestedDiscountPercent,
            ],
        ];
    }

    private function inventoryRisk(array $a): ?array
    {
        $inventory = $a['inventory'] ?? null;

        if (! is_array($inventory) || ! ($inventory['available'] ?? false)) {
            return null;
        }

        $atRisk = $inventory['atRiskMenuItems'] ?? [];
        $slowMovers = $inventory['slowMovers'] ?? [];
        $waste = $inventory['waste']['items'] ?? [];
        $needsAttention = $inventory['purchaseOrders']['needsAttention'] ?? [];
        $priceChanges = collect($inventory['priceChanges'] ?? [])->filter(fn ($r) => ($r['changePercent'] ?? null) !== null && $r['changePercent'] >= 10);

        if (empty($atRisk) && empty($slowMovers) && empty($waste) && empty($needsAttention) && $priceChanges->isEmpty()) {
            return null;
        }

        $currency = $a['currency'] ?? 'EUR';
        $parts = [];

        if (! empty($atRisk)) {
            $parts[] = __('insight_assistant.inventory_at_risk_fragment', [
                'count' => count($atRisk), 'name' => $atRisk[0]['name'],
            ]);
        }

        if (! empty($slowMovers)) {
            $parts[] = __('insight_assistant.inventory_slow_movers_fragment', [
                'money' => $this->money((float) ($inventory['tiedUpCapital'] ?? 0), $currency),
            ]);
        }

        if (! empty($waste)) {
            $parts[] = __('insight_assistant.inventory_waste_fragment', [
                'money' => $this->money((float) ($inventory['waste']['totalValue'] ?? 0), $currency),
            ]);
        }

        if (! empty($needsAttention)) {
            $parts[] = __('insight_assistant.inventory_po_fragment', ['count' => count($needsAttention)]);
        }

        if ($priceChanges->isNotEmpty()) {
            $top = $priceChanges->sortByDesc('changePercent')->first();
            $parts[] = __('insight_assistant.inventory_price_fragment', ['name' => $top['name'], 'percent' => $top['changePercent']]);
        }

        return [
            'answer' => implode(' ', $parts),
            'data' => [
                'atRiskMenuItems' => $atRisk, 'slowMovers' => $slowMovers, 'wasteItems' => $waste,
                'purchaseOrdersNeedingAttention' => $needsAttention, 'priceIncreases' => $priceChanges->values()->all(),
            ],
            'confidence' => 'medium',
        ];
    }

    private function loyalty(array $a): ?array
    {
        $loyalty = $a['loyalty'] ?? null;

        if (! is_array($loyalty) || ! ($loyalty['available'] ?? false)) {
            return null;
        }

        $beforeAfter = $loyalty['visitFrequency']['beforeAfterJoining'] ?? null;
        $eligible = $loyalty['eligibleToRedeem'] ?? 0;
        $rate = $loyalty['redemptionRate'] ?? null;

        $hasVisitImpact = is_array($beforeAfter) && ($beforeAfter['available'] ?? false)
            && ($beforeAfter['avgVisitsPerWeekBefore'] ?? null) !== null
            && ($beforeAfter['avgVisitsPerWeekAfter'] ?? null) !== null;
        $hasRedemption = $eligible >= 1 && $rate !== null;

        if (! $hasVisitImpact && ! $hasRedemption) {
            return null;
        }

        $parts = [];
        $data = [];

        if ($hasVisitImpact) {
            $before = $beforeAfter['avgVisitsPerWeekBefore'];
            $after = $beforeAfter['avgVisitsPerWeekAfter'];
            $changePercent = $before > 0 ? round((($after - $before) / $before) * 100, 1) : null;

            $parts[] = __('insight_assistant.loyalty_visit_fragment', [
                'before' => $before, 'after' => $after,
                'members' => $beforeAfter['membersAnalyzed'] ?? 0,
            ]);
            $data['visitFrequency'] = ['before' => $before, 'after' => $after, 'changePercent' => $changePercent];
        }

        if ($hasRedemption) {
            $parts[] = __('insight_assistant.loyalty_redemption_fragment', ['eligible' => $eligible, 'rate' => $rate]);
            $data['redemption'] = ['eligibleToRedeem' => $eligible, 'redemptionRate' => $rate];
        }

        return [
            'answer' => implode(' ', $parts),
            'data' => $data,
            'confidence' => $this->confidence((int) $eligible),
        ];
    }
}
