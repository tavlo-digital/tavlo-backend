<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/en/insights.php, which this file sits
 * alongside. Reference for other locales to translate against.
 */
return [

    'no_match' => "I can't answer that one yet. Here are some things I can tell you about:",
    'not_enough_data' => "There isn't enough data yet to answer that reliably — check back once you have more orders in this period.",
    'insight_not_active' => "That insight isn't currently active — its numbers may have changed since you last saw it. Here are some things I can tell you about instead:",

    'word_bigger' => 'a bigger basket',
    'word_not_bigger' => 'not a bigger basket',

    'summary_answer' => 'You had :orders orders across :period, for :revenue in revenue and an average order value of :aov.',

    'busiest_quietest_answer' => 'Your busiest slot is :busiest_day at :busiest_hour with :busiest_orders orders. Your quietest is :quietest_day at :quietest_hour with :quietest_orders.',

    'slowest_day_answer' => ':slowest_day is your slowest day, averaging :slowest_orders orders against a daily average of :average. :busiest_day is your busiest, averaging :busiest_orders.',

    'payment_failures_answer' => ':rate% of payment attempts failed this period — :failed of :attempts attempts.',

    'service_speed_turnover_with_baseline_fragment' => 'Tables are turning over in :current minutes on average, against your usual :baseline minutes.',
    'service_speed_turnover_fragment' => 'Tables are turning over in :current minutes on average — not enough trading history yet for a baseline to compare against.',
    'service_speed_lunch_dinner_fragment' => 'Median time from order to served is :lunch minutes at lunch and :dinner minutes at dinner.',

    'retention_answer' => ':new guests placed a first order this period and :returned of them (:rate%) returned within 30 days.',

    'reviews_answer_pending' => 'You have :unanswered unanswered reviews, :critical of which are rated 3 stars or below.',
    'reviews_answer_clear' => 'No unanswered reviews right now.',

    'revenue_concentration_answer' => ':count of your :identified identified guests (your top fifth) account for :share% of attributed revenue.',

    'discounts_answer' => 'Discounted orders averaged :discounted items and full-price orders averaged :full — discounting is producing :effect. :forgone was given away against list price this period.',

    'tips_overall_fragment' => 'Your overall tip rate is :rate%.',
    'tips_speed_fragment' => 'Orders served within :threshold minutes carry a :fast_rate% tip rate, against :slow_rate% for slower ones.',
    'tips_capture_fragment' => ':cash% of cash orders show a recorded tip, against :digital% of digital ones.',

    'sold_out_answer' => ':count item(s) went unavailable at least once this period, led by :names.',

    'menu_items_answer' => 'Your top sellers by quantity are :top. :decliner',
    'menu_items_decliner_fragment' => ':name is down :percent% on the previous period.',
    'menu_pairing_fragment' => ':slow_name is selling far below the rest of your menu — pair it with your best seller, :best_name, as a bundle around :discount% off together, or a "buy :best_name, get :slow_name" offer.',

    'inventory_at_risk_fragment' => ':count menu item(s) rely on an ingredient that is out of stock or running low, led by ":name".',
    'inventory_slow_movers_fragment' => ':money is tied up in stock that is barely moving.',
    'inventory_waste_fragment' => ':money was written off as waste this period.',
    'inventory_po_fragment' => ':count purchase order(s) need your attention.',
    'inventory_price_fragment' => ':name went up :percent% per unit on your last purchase order.',

    'loyalty_visit_fragment' => 'Members visited :before times/week on average before joining loyalty, :after after, across :members members with enough history to compare.',
    'loyalty_redemption_fragment' => ':eligible members qualify to redeem a reward, and :rate% of eligible members have ever done so.',

];
