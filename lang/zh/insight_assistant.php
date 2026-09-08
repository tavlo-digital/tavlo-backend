<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/zh/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => '这个问题我暂时还无法回答。以下是我可以为您解答的一些内容：',
    'not_enough_data' => '目前数据还不足以给出可靠的答案——等本期订单更多时再来看看。',
    'insight_not_active' => '该洞察当前未生效——自您上次查看以来，相关数据可能已发生变化。以下是我可以为您解答的一些内容：',

    'word_bigger' => '更大的客单量',
    'word_not_bigger' => '并非更大的客单量',

    'summary_answer' => '您在:period内共有:orders笔订单，营收:revenue，平均订单价值为:aov。',

    'busiest_quietest_answer' => '您最繁忙的时段是:busiest_day的:busiest_hour，共:busiest_orders笔订单。最清淡的时段是:quietest_day的:quietest_hour，共:quietest_orders笔。',

    'slowest_day_answer' => ':slowest_day是您最清淡的一天，平均:slowest_orders笔订单，而日均为:average笔。:busiest_day是您最繁忙的一天，平均:busiest_orders笔。',

    'payment_failures_answer' => '本期有:rate%的支付尝试失败——共:attempts次尝试中有:failed次失败。',

    'service_speed_turnover_with_baseline_fragment' => '餐桌平均在:current分钟内翻台，而您平时通常为:baseline分钟。',
    'service_speed_turnover_fragment' => '餐桌平均在:current分钟内翻台——目前交易历史还不足以形成对比基准。',
    'service_speed_lunch_dinner_fragment' => '从下单到上菜的中位时间为：午餐:lunch分钟，晚餐:dinner分钟。',

    'retention_answer' => '本期有:new位客人首次下单，其中:returned位（:rate%）在30天内再次光顾。',

    'reviews_answer_pending' => '您有:unanswered条未回复评价，其中:critical条评分为3星或以下。',
    'reviews_answer_clear' => '目前没有未回复的评价。',

    'revenue_concentration_answer' => '在您的:identified位已识别客人中，:count位（您的前五分之一）贡献了:share%的可归因营收。',

    'discounts_answer' => '折扣订单平均含:discounted件商品，全价订单平均含:full件——折扣带来的效果是:effect。本期相对于标价共让利:forgone。',

    'tips_overall_fragment' => '您的总体小费率为:rate%。',
    'tips_speed_fragment' => ':threshold分钟内完成的订单小费率为:fast_rate%，较慢的订单为:slow_rate%。',
    'tips_capture_fragment' => '现金订单中有:cash%记录了小费，而数字支付订单中这一比例为:digital%。',

    'sold_out_answer' => '本期有:count件商品至少缺货一次，其中以:names为首。',

    'menu_items_answer' => '您按销量排名的畅销商品是:top。:decliner',
    'menu_items_decliner_fragment' => ':name较上一期下降了:percent%。',
    'menu_pairing_fragment' => ':slow_name的销量远低于菜单其他商品——可与您的畅销单品:best_name搭配，整体享约:discount%折扣的组合套餐，或推出"购买:best_name即赠:slow_name"活动。',

    'inventory_at_risk_fragment' => '有:count个菜单商品依赖一种已缺货或库存不足的原料，其中以"‌:name"为首。',
    'inventory_slow_movers_fragment' => ':money被占用在几乎不动销的库存中。',
    'inventory_waste_fragment' => '本期有:money被记为报废损耗。',
    'inventory_po_fragment' => '有:count个采购订单需要您关注。',
    'inventory_price_fragment' => '在您最近一次采购订单中，:name的单价上涨了:percent%。',

    'loyalty_visit_fragment' => '会员在加入忠诚度计划前平均每周光顾:before次，加入后为:after次，此对比基于:members位有足够历史记录的会员。',
    'loyalty_redemption_fragment' => '有:eligible位会员符合兑换奖励的条件，其中:rate%的符合条件会员曾经兑换过。',

];
