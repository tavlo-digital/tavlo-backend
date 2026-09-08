<?php

/**
 * Every string InsightEngine.php renders, keyed by insight + field (+ branch
 * where the rule chooses between genuinely different sentences, not just
 * different values). Mirrors the English text that used to be hardcoded via
 * sprintf() verbatim — this file is the reference other locales are
 * translated against.
 */
return [

    // Small reusable words / fragments
    'word_order' => 'order',
    'word_orders' => 'orders',
    'word_day' => 'day',
    'word_week' => 'week',
    'word_month' => 'month',
    'word_and' => 'and',
    'word_failed' => 'Failed',
    'word_manual_action' => 'Manual-action',
    'day_mon' => 'Mon',
    'day_tue' => 'Tue',
    'day_wed' => 'Wed',
    'day_thu' => 'Thu',
    'day_fri' => 'Fri',
    'day_sat' => 'Sat',
    'day_sun' => 'Sun',
    'period_daily' => 'last 7 days',
    'period_weekly' => 'last 12 weeks',
    'period_monthly' => 'last 12 months',

    // quietHour
    'quiet_hour_title' => ':day at :hour is your quietest slot',
    'quiet_hour_description' => ':quietest_orders :order_word, against :busiest_orders at your peak (:busiest_day :busiest_hour)',
    'quiet_hour_impact_label' => 'order gap vs peak hour',
    'quiet_hour_what_happening' => 'Across :period, :day at :hour recorded :quietest_orders orders while your busiest hour recorded :busiest_orders.',
    'quiet_hour_why_matters' => 'Staff, rent and utilities are paid the same in a quiet hour as a busy one, so idle capacity costs the same as full capacity.',
    'quiet_hour_suggested_action' => 'Run a time-boxed offer over that window and measure it against this same hour next period.',
    'quiet_hour_action_label' => 'Set up an offer',

    // slowDay
    'slow_day_title' => ':day runs :percent% below your weekly average',
    'slow_day_description' => ':orders orders against a daily average of :average',
    'slow_day_impact_label' => 'revenue gap vs an average day',
    'slow_day_what_happening' => ':day averaged :orders orders while your other days averaged :average over the same window.',
    'slow_day_why_matters' => 'Fixed costs do not fall on a quiet day, so a soft day costs proportionally more margin than the order gap suggests.',
    'slow_day_suggested_action' => 'Trial a :day-only offer and compare against this same baseline.',
    'slow_day_action_label' => 'Set up an offer',

    // paymentFailures
    'payment_failures_title' => ':rate% of payment attempts are failing',
    'payment_failures_description' => ':failed failed attempts in this period',
    'payment_failures_impact_label' => 'value of orders that failed to capture',
    'payment_failures_what_happening' => ':failed of :attempts payment attempts ended in failure this period.',
    'payment_failures_why_matters' => 'A failed payment is a guest who tried to pay you and could not. It is the most directly recoverable revenue on this page.',
    'payment_failures_suggested_action' => 'Check your Stripe connection and card settings, and confirm staff know how to retry a failed checkout at the table.',
    'payment_failures_action_label' => 'Open payment settings',

    // turnoverSlowing
    'turnover_slowing_title' => 'Tables are turning over :extra minutes slower',
    'turnover_slowing_description' => ':current min now against your :samples-:unit average of :baseline min',
    'turnover_slowing_impact_label' => 'longer per table than your :samples-:unit average',
    'turnover_slowing_what_happening' => 'Median table occupancy across :measured completed visits is :current minutes, against a :unit-by-:unit average of :baseline minutes built from your own trading history.',
    'turnover_slowing_why_matters' => 'A table held longer is a table that cannot seat the next party, which caps covers on exactly the nights you are busiest.',
    'turnover_slowing_suggested_action' => 'Check where the time is going — the stage timings above separate kitchen delay from time waiting to pay.',
    'turnover_slowing_action_label' => 'Review menu',

    // serviceGap
    'service_gap_title' => 'Dinner service runs :gap minutes slower than lunch',
    'service_gap_description' => ':dinner min at dinner against :lunch min at lunch, order to served',
    'service_gap_impact_label' => 'longer per order at dinner',
    'service_gap_what_happening' => 'Median time from confirmed order to served is :dinner minutes during dinner and :lunch minutes at lunch.',
    'service_gap_why_matters' => 'Slower service at your busiest hours holds tables when you most need them free, and it is the most common theme in low ratings.',
    'service_gap_suggested_action' => 'Review kitchen staffing for the dinner window, or promote shorter-ticket items during it.',
    'service_gap_action_label' => 'Review menu',

    // firstTimeReturn
    'first_time_return_title' => 'Only :rate% of first-time guests return within 30 days',
    'first_time_return_description' => ':returned of :new new guests came back',
    'first_time_return_impact_label' => 'value of the gap to a 20% return rate',
    'first_time_return_what_happening' => ':new guests placed a first order this period and :returned of them returned inside 30 days.',
    'first_time_return_why_matters' => 'Winning a new guest costs considerably more than bringing an existing one back, so this ratio drives profitability more than volume does.',
    'first_time_return_suggested_action' => 'Set up an automatic follow-up offer a week after a first visit.',
    'first_time_return_action_label' => 'Set up loyalty',

    // unansweredReviews
    'unanswered_reviews_title' => ':unanswered reviews are waiting for a reply',
    'unanswered_reviews_description_critical' => ':critical of them are rated 3 stars or below',
    'unanswered_reviews_description_none_critical' => 'None are critical, but replies still show future guests you are present',
    'unanswered_reviews_impact_label' => 'critical reviews with no reply',
    'unanswered_reviews_what_happening' => 'You have :unanswered unanswered reviews, :critical of which are rated 3 stars or below.',
    'unanswered_reviews_why_matters' => 'Prospective guests read replies as closely as reviews. An unanswered complaint reads as an unresolved one.',
    'unanswered_reviews_suggested_action' => 'Reply to the critical ones first — they carry the most weight with anyone deciding where to eat.',
    'unanswered_reviews_action_label' => 'Open reviews',

    // revenueConcentration
    'revenue_concentration_title' => ':share% of attributed revenue comes from :count guests',
    'revenue_concentration_description' => 'Your top fifth of identified guests carries most of the revenue',
    'revenue_concentration_impact_label' => 'guests carrying the majority of revenue',
    'revenue_concentration_what_happening' => ':count of your :identified identified guests account for :share% of the revenue that could be attributed to an account.',
    'revenue_concentration_why_matters' => 'Losing a handful of these guests costs more than losing a large number of one-time visitors, so they are worth treating differently.',
    'revenue_concentration_suggested_action' => 'Give this group a named tier with a benefit worth returning for.',
    'revenue_concentration_action_label' => 'Set up a tier',

    // discountEffect
    'discount_effect_title' => 'Discounted orders are not producing bigger baskets',
    'discount_effect_description' => ':discounted items on discounted orders against :full on full-price orders',
    'discount_effect_impact_label' => 'given away against list price this period',
    'discount_effect_what_happening' => 'Discounted orders averaged :discounted items and full-price orders averaged :full, while :money was given away against list price.',
    'discount_effect_why_matters' => 'A discount that does not deepen the basket or bring the guest back is margin handed to people who would have ordered anyway.',
    'discount_effect_suggested_action' => 'Narrow discounts to quiet hours or to items you want to move, rather than running them permanently.',
    'discount_effect_action_label' => 'Review discounts',

    // tipsAndSpeed
    'tips_and_speed_title' => 'Faster tables tip better',
    'tips_and_speed_description' => ':fast_rate% tip rate when served inside :threshold min, against :slow_rate% when slower',
    'tips_and_speed_impact_label' => 'higher tip rate on quicker service',
    'tips_and_speed_what_happening' => 'Orders served within :threshold minutes carried a :fast_rate% tip rate across :fast_orders orders. Those served more slowly carried :slow_rate% across :slow_orders.',
    'tips_and_speed_why_matters' => 'Tips are the clearest signal your guests give about service, and unlike a review nearly everyone leaves one. This is the same money your staff take home.',
    'tips_and_speed_suggested_action' => 'Look at what slows the slower half — the stage timings separate kitchen delay from time waiting to pay.',
    'tips_and_speed_action_label' => 'Review menu',

    // cashTipCapture
    'cash_tip_capture_title' => 'Cash orders record tips far less often',
    'cash_tip_capture_description' => ':cash% of cash orders show a tip, against :digital% paid by card or wallet',
    'cash_tip_capture_impact_label' => 'gap in recorded tipping between cash and digital',
    'cash_tip_capture_what_happening' => ':cash_tipped of :cash_orders cash orders carried a recorded tip, against :digital% of the :digital_orders paid digitally.',
    'cash_tip_capture_why_matters' => 'A card tip is captured at checkout, but a cash tip only appears if the guest entered one when requesting to pay or a waiter added it at handover. Tips left on the table and never typed in are invisible here — and so are missing from anything you use to share them out.',
    'cash_tip_capture_suggested_action' => 'Ask staff to enter the tip when confirming a cash payment. If cash guests genuinely tip less, this figure will stay put and you have your answer.',
    'cash_tip_capture_action_label' => 'Open settings',

    // decliningItem
    'declining_item_title' => ':name is down :percent% on last period',
    'declining_item_description' => ':quantity sold this period',
    'declining_item_impact_label' => 'change in revenue vs previous period',
    'declining_item_what_happening' => ':name sold :quantity units this period, :percent% down on revenue against the previous window.',
    'declining_item_why_matters' => 'A sharp fall on a single item usually points at something specific — availability, a price change, or its position on the menu.',
    'declining_item_suggested_action' => 'Check whether it went unavailable, moved down the menu, or changed price during the period.',
    'declining_item_action_label' => 'Open menu',

    // inventoryAtRisk
    'inventory_at_risk_title_one' => '":name" is at risk of running out',
    'inventory_at_risk_title_many' => ':count menu items are at risk of running out',
    'inventory_at_risk_description_one' => '":name" earned :money this period and relies on an ingredient that is out of stock or running low',
    'inventory_at_risk_description_many' => 'These items earned :money this period and share an ingredient that is out of stock or running low',
    'inventory_at_risk_impact_label' => 'revenue this period from at-risk items',
    'inventory_at_risk_what_happening' => ':count menu item(s) sold this period rely on an ingredient currently flagged out of stock or low, led by ":name".',
    'inventory_at_risk_why_matters' => 'A guest ordering something you can no longer make costs you the sale and the goodwill in the same moment.',
    'inventory_at_risk_suggested_action' => 'Restock the flagged ingredients, or mark the affected items unavailable until you do.',
    'inventory_at_risk_action_label' => 'Review inventory',

    // slowMovingStock
    'slow_moving_stock_title' => ':money is tied up in stock that is barely moving',
    'slow_moving_stock_description' => 'Led by :name, :quantity :unit of it on hand with little or no sales behind it this period',
    'slow_moving_stock_impact_label' => 'valued at your own recorded cost per unit',
    'slow_moving_stock_what_happening' => ':count stock item(s) have little or no sales behind them this period despite carrying real cost, led by :name.',
    'slow_moving_stock_why_matters' => 'Cash sitting in stock that is not moving is cash that is not doing anything else for you, and slow stock is also the stock most likely to spoil or expire unused.',
    'slow_moving_stock_suggested_action' => 'Feature it in a special, adjust the standing order, or check whether it should still be on the menu at all.',
    'slow_moving_stock_action_label' => 'Review inventory',

    // foodWaste
    'food_waste_title' => ':money written off as waste this period',
    'food_waste_description' => 'Led by :name, :quantity :unit logged as waste',
    'food_waste_impact_label' => 'valued at your own recorded cost per unit',
    'food_waste_what_happening' => ':count stock item(s) had a waste adjustment logged this period, led by :name, together valued at :money.',
    'food_waste_why_matters' => 'Waste is margin that never reached a plate. A pattern here usually points at over-ordering, poor rotation, or portioning worth a second look.',
    'food_waste_suggested_action' => 'Check whether the top items are being over-ordered or need tighter portion control.',
    'food_waste_action_label' => 'Review inventory',

    // purchaseOrderNeedsAttention
    'purchase_order_title_one' => 'Purchase order :id needs your attention',
    'purchase_order_title_many' => ':count purchase orders need your attention',
    'purchase_order_description_error' => ':supplier: :error',
    'purchase_order_description_failed' => 'Failed order to :supplier did not go through automatically',
    'purchase_order_description_manual' => 'Manual-action order to :supplier did not go through automatically',
    'purchase_order_impact_label' => 'purchase orders stuck failed or needing manual action',
    'purchase_order_what_happening' => ':count purchase order(s) could not be dispatched automatically to the supplier and are waiting on you, led by :id to :supplier.',
    'purchase_order_why_matters' => 'A stuck purchase order is stock that is not actually on its way, even though it looks ordered — the gap only shows up when the shelf runs empty.',
    'purchase_order_suggested_action' => 'Open the purchase order and either retry the dispatch or place it with the supplier directly.',
    'purchase_order_action_label' => 'Review inventory',

    // priceIncrease
    'price_increase_title' => ':name went up :percent% per :unit',
    'price_increase_description' => ':previous → :latest per :unit on your last two purchase orders',
    'price_increase_impact_label' => 'increase on your last recorded order for this item',
    'price_increase_what_happening' => 'Your last purchase order for :name cost :latest per :unit, up from :previous per :unit the order before.',
    'price_increase_why_matters' => 'A supplier price increase erodes margin on every dish that uses this ingredient unless your menu price moves with it.',
    'price_increase_suggested_action' => 'Check whether the menu items built on this ingredient still hold their margin, or whether it is worth shopping the increase against another supplier.',
    'price_increase_action_label' => 'Review inventory',

    // expiryRiskPromotion
    'expiry_risk_fallback_dishes' => 'the dishes using it',
    'expiry_risk_title' => 'Cut the price on :items to sell through :quantity :unit before it\'s written off',
    'expiry_risk_description_linked' => 'Estimated :money of :name is projected to go unused — a ~:discount% price cut on :items would encourage guests to order it now instead of leaving it to spoil',
    'expiry_risk_description_unlinked' => 'Worth :money at your recorded cost, and not linked to any menu item recipe — no dish to discount it through',
    'expiry_risk_impact_label' => 'estimated loss avoidable by selling through the flagged stock instead of writing it off, placeholder shelf-life assumption',
    'expiry_risk_what_happening' => 'This is an ESTIMATE, not a measurement: the Inventory package has no real expiry-date field yet, so this projects forward from an assumed shelf life for :name\'s category and its last recorded delivery, against how fast it is actually being used. On that basis, :quantity :unit (worth :money at cost) is projected to still be on hand past its assumed shelf life and would otherwise be written off as waste.',
    'expiry_risk_why_matters_linked' => 'Every unit sold through :items instead of thrown out converts stock that was about to become a :money loss into revenue — a temporary price cut that moves it before the assumed shelf life passes is cheaper than writing the whole batch off.',
    'expiry_risk_why_matters_unlinked' => 'That stock is written off as waste instead of sold unless something moves it — worth a manual promotion even without a linked recipe. Treat the figure as a planning signal, not a confirmed number, until real expiry-date tracking is added.',
    'expiry_risk_suggested_action_linked' => 'Drop the price on :items by roughly :discount% until the flagged :name clears, then compare what actually sold against this estimate.',
    'expiry_risk_suggested_action_unlinked' => 'Consider a manual promotion or check the item in person — no menu item currently uses it in a recipe.',
    'expiry_risk_action_label' => 'Review inventory',

    // loyaltyVisitImpact
    'loyalty_visit_title_up' => 'Members visit :percent% more often since joining loyalty',
    'loyalty_visit_title_down' => 'Members visit :percent% less often since joining loyalty',
    'loyalty_visit_description' => ':before visits/week on average before joining, :after after, across :members members with enough order history to compare',
    'loyalty_visit_impact_label_up' => 'more frequent visits since joining',
    'loyalty_visit_impact_label_down' => 'less frequent visits since joining',
    'loyalty_visit_what_happening' => 'Comparing each member\'s own order history before and after they joined, average visit pace went from :before to :after visits per week across :members members.',
    'loyalty_visit_why_matters_up' => 'This is the clearest evidence the program is doing its job: guests who joined are coming back more often than they did before, not just spending differently on the visits they already made.',
    'loyalty_visit_why_matters_down' => 'The program is not increasing how often members come back — whatever it is doing, it is not buying more visits. Worth checking whether the reward is worth the trip back for.',
    'loyalty_visit_suggested_action_up' => 'Worth promoting the program more prominently — it is measurably working.',
    'loyalty_visit_suggested_action_down' => 'Consider whether the reward or its threshold needs to change.',
    'loyalty_visit_action_label' => 'View loyalty program',

    // loyaltyStalled
    'loyalty_stalled_title' => ':eligible loyalty members qualify for a reward but rarely redeem it',
    'loyalty_stalled_description_inactive' => ':inactive of them haven\'t ordered in over 30 days — already primed to come back',
    'loyalty_stalled_description_rate' => 'Only :rate% of eligible members have ever redeemed',
    'loyalty_stalled_impact_label' => 'in points sitting unredeemed',
    'loyalty_stalled_what_happening' => ':eligible members hold enough points to redeem a reward, :rate% of them have ever done so, and :inactive have gone quiet for 30+ days despite already qualifying.',
    'loyalty_stalled_why_matters' => 'A reward nobody claims is not pulling anyone back through the door — it is just a liability sitting on your books. The members who have also gone quiet are the clearest target: they already have a reason to return.',
    'loyalty_stalled_suggested_action_inactive' => 'Start with the :inactive members who both qualify and haven\'t ordered recently — they need the least convincing.',
    'loyalty_stalled_suggested_action_default' => 'Remind eligible members their reward is ready next time they order.',
    'loyalty_stalled_action_label' => 'View loyalty program',

    // soldOutFrequency
    'sold_out_title' => ':name went unavailable :count times this period',
    'sold_out_description' => 'Marked unavailable :count separate times this period',
    'sold_out_impact_label' => 'times marked unavailable this period',
    'sold_out_what_happening' => ':name was toggled to unavailable :count times this period, each one a stretch where guests could not order it.',
    'sold_out_why_matters' => 'A dish that keeps going out of stock turns guests away from the same item repeatedly, not once — each round trip is a missed order and a guest who may not check the menu again.',
    'sold_out_suggested_action' => 'Raise the reorder point or standing order quantity for whichever ingredient runs out first, so it stops interrupting service.',
    'sold_out_action_label' => 'Review inventory',

    // highInterestLowConversion
    'high_interest_title' => ':name draws interest but loses the sale',
    'high_interest_description' => 'Opened :views times, ordered :quantity — viewed :ratio more than it sold',
    'high_interest_impact_label' => 'views this period that did not become an order',
    'high_interest_what_happening' => ':name had :views menu detail opens against :quantity actual orders this period (:ratio per order).',
    'high_interest_why_matters' => 'High interest with low conversion usually points at price, description or photo — a guest opened the item and then chose something else.',
    'high_interest_suggested_action' => 'Check the price, description and photo against similar items that convert, then re-check this gap next period.',
    'high_interest_action_label' => 'Open menu',

    // slowSellerPairing
    'slow_seller_pairing_title' => ':name is barely selling — pair it with your best seller',
    'slow_seller_pairing_description' => 'Sold :quantity this period against a :average average across your other active items',
    'slow_seller_pairing_impact_label' => 'below the average active item\'s sales this period',
    'slow_seller_pairing_what_happening' => ':name sold :quantity units this period against a :average average across your other active items — most of the menu is comfortably outselling it.',
    'slow_seller_pairing_why_matters' => 'A slow mover rarely improves by sitting at full price on its own — but discounting it in isolation still asks a guest to choose something nobody else is ordering. Pairing it with a dish that already sells well borrows that demand instead of competing with it.',
    'slow_seller_pairing_suggested_action' => 'Pair :slow_name with your best seller, :best_name — try a bundle at around :discount% off together, or a "buy :best_name, get :slow_name" offer. Both are ready to set up now under Loyalty & Promotions.',
    'slow_seller_pairing_action_label' => 'Set up an offer',

];
