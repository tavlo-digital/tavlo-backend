<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/ja/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => 'その質問にはまだお答えできません。以下は私がお答えできる内容です：',
    'not_enough_data' => 'まだ信頼できる回答をするのに十分なデータがありません — この期間の注文数が増えたら再度お試しください。',
    'insight_not_active' => 'このインサイトは現在有効ではありません — 前回ご覧になってから数値が変わっている可能性があります。代わりに以下の内容についてお答えできます：',

    'word_bigger' => 'より大きな注文額',
    'word_not_bigger' => 'より大きな注文額ではない',

    'summary_answer' => ':period で :orders 件の注文があり、売上は :revenue、平均注文額は :aov でした。',

    'busiest_quietest_answer' => '最も忙しい時間帯は :busiest_day の :busiest_hour で、注文数は :busiest_orders 件です。最も空いている時間帯は :quietest_day の :quietest_hour で、:quietest_orders 件です。',

    'slowest_day_answer' => ':slowest_day は最も注文が少ない曜日で、1日平均 :average 件に対し平均 :slowest_orders 件です。:busiest_day は最も忙しい曜日で、平均 :busiest_orders 件です。',

    'payment_failures_answer' => 'この期間、決済試行の :rate% が失敗しました — :attempts 件中 :failed 件です。',

    'service_speed_turnover_with_baseline_fragment' => 'テーブルの回転は平均 :current 分で、通常の :baseline 分と比べています。',
    'service_speed_turnover_fragment' => 'テーブルの回転は平均 :current 分です — 比較の基準となる十分な取引履歴がまだありません。',
    'service_speed_lunch_dinner_fragment' => '注文から提供までの中央値は、ランチが :lunch 分、ディナーが :dinner 分です。',

    'retention_answer' => 'この期間に :new 組のお客様が初めて注文し、そのうち :returned 組（:rate%）が30日以内に再来店しました。',

    'reviews_answer_pending' => '未対応のレビューが :unanswered 件あり、そのうち :critical 件が★3以下です。',
    'reviews_answer_clear' => '現在、未対応のレビューはありません。',

    'revenue_concentration_answer' => '識別済みのお客様 :identified 人のうち :count 人（上位5分の1）が、帰属売上の :share% を占めています。',

    'discounts_answer' => '割引注文は平均 :discounted 品、通常価格注文は平均 :full 品でした — 割引の効果は :effect です。この期間、定価に対して :forgone が値引きされました。',

    'tips_overall_fragment' => '全体のチップ率は :rate% です。',
    'tips_speed_fragment' => ':threshold 分以内に提供された注文のチップ率は :fast_rate% で、それより遅い注文では :slow_rate% です。',
    'tips_capture_fragment' => '現金注文の :cash% でチップが記録されているのに対し、デジタル決済では :digital% です。',

    'sold_out_answer' => 'この期間、少なくとも1回は品切れになった商品が :count 件あり、その筆頭は :names です。',

    'menu_items_answer' => '数量ベースの人気商品は :top です。:decliner',
    'menu_items_decliner_fragment' => ':name は前期比 :percent% 減少しています。',
    'menu_pairing_fragment' => ':slow_name はメニューの他の商品に比べて売れ行きがかなり悪くなっています — 一番人気の :best_name と組み合わせて、セットでおよそ :discount% 引きのバンドルにするか、「:best_name を買うと :slow_name がもらえる」オファーを試してみてください。',

    'inventory_at_risk_fragment' => '在庫切れまたは残りわずかな食材に依存しているメニュー商品が :count 件あり、その筆頭は「:name」です。',
    'inventory_slow_movers_fragment' => ':money がほとんど動いていない在庫に滞留しています。',
    'inventory_waste_fragment' => 'この期間、:money が廃棄として計上されました。',
    'inventory_po_fragment' => ':count 件の発注が対応を必要としています。',
    'inventory_price_fragment' => ':name は直近の発注で単価が :percent% 上昇しました。',

    'loyalty_visit_fragment' => '会員はロイヤルティプログラムに参加する前は平均週 :before 回来店していましたが、参加後は :after 回になりました（比較に十分な履歴を持つ :members 名の会員について）。',
    'loyalty_redemption_fragment' => ':eligible 名の会員が特典を利用する資格があり、そのうち :rate% がこれまでに実際に利用したことがあります。',

];
