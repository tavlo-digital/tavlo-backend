<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/cs/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => 'Na to zatím neumím odpovědět. Tady je pár věcí, o kterých vám mohu říct:',
    'not_enough_data' => 'Na spolehlivou odpověď zatím není dost dat — zkuste to znovu, až budete mít v tomto období více objednávek.',
    'insight_not_active' => 'Tento insight momentálně není aktivní — jeho čísla se od doby, kdy jste ho naposledy viděli, mohla změnit. Tady je místo toho pár věcí, o kterých vám mohu říct:',

    'word_bigger' => 'větší košík',
    'word_not_bigger' => 'ne větší košík',

    'summary_answer' => 'Měli jste :orders objednávek za :period, s tržbou :revenue a průměrnou hodnotou objednávky :aov.',

    'busiest_quietest_answer' => 'Váš nejrušnější čas je :busiest_day v :busiest_hour s :busiest_orders objednávkami. Nejklidnější je :quietest_day v :quietest_hour s :quietest_orders.',

    'slowest_day_answer' => ':slowest_day je váš nejslabší den, v průměru :slowest_orders objednávek oproti dennímu průměru :average. :busiest_day je nejsilnější, v průměru :busiest_orders.',

    'payment_failures_answer' => ':rate % pokusů o platbu se v tomto období nezdařilo — :failed z :attempts pokusů.',

    'service_speed_turnover_with_baseline_fragment' => 'Stoly se v průměru uvolňují za :current minut, oproti obvyklým :baseline minutám.',
    'service_speed_turnover_fragment' => 'Stoly se v průměru uvolňují za :current minut — zatím není dost historie provozu pro srovnávací hodnotu.',
    'service_speed_lunch_dinner_fragment' => 'Medián doby od objednávky po podání je :lunch minut při obědě a :dinner minut při večeři.',

    'retention_answer' => ':new hostů zadalo první objednávku v tomto období a :returned z nich (:rate %) se vrátilo do 30 dnů.',

    'reviews_answer_pending' => 'Máte :unanswered nezodpovězených recenzí, z toho :critical s hodnocením 3 hvězdičky nebo méně.',
    'reviews_answer_clear' => 'Momentálně žádné nezodpovězené recenze.',

    'revenue_concentration_answer' => ':count z vašich :identified identifikovaných hostů (vaše nejlepší pětina) tvoří :share % přiřazených tržeb.',

    'discounts_answer' => 'Objednávky se slevou měly v průměru :discounted položek, objednávky za plnou cenu :full — slevy přinášejí :effect. V tomto období bylo oproti ceníkové ceně poskytnuto :forgone.',

    'tips_overall_fragment' => 'Vaše celková míra spropitného je :rate %.',
    'tips_speed_fragment' => 'Objednávky podané do :threshold minut mají míru spropitného :fast_rate %, oproti :slow_rate % u pomalejších.',
    'tips_capture_fragment' => 'U :cash % hotovostních objednávek je zaznamenáno spropitné, oproti :digital % u digitálních plateb.',

    'sold_out_answer' => ':count položek bylo v tomto období alespoň jednou nedostupných, v čele s :names.',

    'menu_items_answer' => 'Vaše nejprodávanější položky podle množství jsou :top. :decliner',
    'menu_items_decliner_fragment' => ':name klesla o :percent % oproti předchozímu období.',
    'menu_pairing_fragment' => ':slow_name se prodává výrazně hůř než zbytek vašeho menu — spárujte ji se svým nejprodávanějším artiklem :best_name, jako balíček se slevou kolem :discount % dohromady, nebo jako nabídku „kup :best_name, získej :slow_name".',

    'inventory_at_risk_fragment' => ':count položek menu závisí na surovině, která došla nebo dochází, v čele s ":name".',
    'inventory_slow_movers_fragment' => ':money je vázáno v zásobách, které se sotva hýbou.',
    'inventory_waste_fragment' => ':money bylo v tomto období odepsáno jako odpad.',
    'inventory_po_fragment' => ':count objednávek u dodavatele vyžaduje vaši pozornost.',
    'inventory_price_fragment' => ':name zdražila o :percent % za jednotku ve vaší poslední objednávce u dodavatele.',

    'loyalty_visit_fragment' => 'Členové navštěvovali v průměru :before krát/týden před vstupem do věrnostního programu, :after po vstupu, na základě :members členů s dostatečnou historií pro srovnání.',
    'loyalty_redemption_fragment' => ':eligible členů má nárok uplatnit odměnu, a :rate % způsobilých členů to někdy udělalo.',

];
