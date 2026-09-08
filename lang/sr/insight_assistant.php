<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/sr/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => 'Na to još uvek ne mogu da odgovorim. Evo nekoliko stvari o kojima mogu da vam kažem:',
    'not_enough_data' => 'Trenutno nema dovoljno podataka za pouzdan odgovor — proverite ponovo kada budete imali više porudžbina u ovom periodu.',
    'insight_not_active' => 'Ovaj uvid trenutno nije aktivan — njegovi brojevi su se možda promenili od kada ste ga poslednji put videli. Evo umesto toga nekoliko stvari o kojima mogu da vam kažem:',

    'word_bigger' => 'veću korpu',
    'word_not_bigger' => 'ne veću korpu',

    'summary_answer' => 'Imali ste :orders porudžbina :period, sa prihodom od :revenue i prosečnom vrednošću porudžbine od :aov.',

    'busiest_quietest_answer' => 'Vaš najprometniji termin je :busiest_day u :busiest_hour sa :busiest_orders porudžbina. Najmirniji je :quietest_day u :quietest_hour sa :quietest_orders.',

    'slowest_day_answer' => ':slowest_day je vaš najslabiji dan, sa prosekom od :slowest_orders porudžbina u odnosu na dnevni prosek od :average. :busiest_day je najprometniji, sa prosekom od :busiest_orders.',

    'payment_failures_answer' => ':rate% pokušaja plaćanja nije uspelo u ovom periodu — :failed od :attempts pokušaja.',

    'service_speed_turnover_with_baseline_fragment' => 'Stolovi se oslobađaju u proseku za :current minuta, u odnosu na vaših uobičajenih :baseline minuta.',
    'service_speed_turnover_fragment' => 'Stolovi se oslobađaju u proseku za :current minuta — još uvek nema dovoljno istorije prometa za poređenje.',
    'service_speed_lunch_dinner_fragment' => 'Medijalno vreme od porudžbine do usluživanja je :lunch minuta za ručak i :dinner minuta za večeru.',

    'retention_answer' => ':new gostiju je izvršilo prvu porudžbinu u ovom periodu, a :returned od njih (:rate%) se vratilo u roku od 30 dana.',

    'reviews_answer_pending' => 'Imate :unanswered neodgovorenih recenzija, od kojih je :critical ocenjeno sa 3 zvezdice ili manje.',
    'reviews_answer_clear' => 'Trenutno nema neodgovorenih recenzija.',

    'revenue_concentration_answer' => ':count od vaših :identified identifikovanih gostiju (vaša najbolja petina) čini :share% pripisanog prihoda.',

    'discounts_answer' => 'Porudžbine sa popustom su u proseku imale :discounted artikala, a porudžbine po punoj ceni :full — popusti proizvode :effect. U ovom periodu je :forgone ustupljeno u odnosu na cenovnik.',

    'tips_overall_fragment' => 'Vaša ukupna stopa napojnice je :rate%.',
    'tips_speed_fragment' => 'Porudžbine usluženih u roku od :threshold minuta nose stopu napojnice od :fast_rate%, u odnosu na :slow_rate% za sporije.',
    'tips_capture_fragment' => ':cash% porudžbina plaćenih gotovinom ima zabeleženu napojnicu, u odnosu na :digital% digitalnih.',

    'sold_out_answer' => ':count artikal(a) je bilo nedostupno bar jednom u ovom periodu, predvođeno sa :names.',

    'menu_items_answer' => 'Vaši najprodavaniji artikli po količini su :top. :decliner',
    'menu_items_decliner_fragment' => ':name je u padu za :percent% u odnosu na prethodni period.',
    'menu_pairing_fragment' => ':slow_name se prodaje mnogo slabije od ostatka vašeg menija — kombinujte ga sa svojim najprodavanijim artiklom :best_name, kao paket sa oko :discount% popusta zajedno, ili kao ponudu „kupi :best_name, dobij :slow_name".',

    'inventory_at_risk_fragment' => ':count artikal(a) menija zavisi od sastojka koji je nedostupan ili pri kraju, predvođeno sa ":name".',
    'inventory_slow_movers_fragment' => ':money je vezano u zalihama koje se jedva kreću.',
    'inventory_waste_fragment' => ':money je otpisano kao otpad u ovom periodu.',
    'inventory_po_fragment' => ':count porudžbenica(a) dobavljaču zahteva vašu pažnju.',
    'inventory_price_fragment' => ':name je poskupeo za :percent% po jedinici u vašoj poslednjoj porudžbenici.',

    'loyalty_visit_fragment' => 'Članovi su u proseku dolazili :before puta/nedeljno pre učlanjenja u program lojalnosti, a :after posle, na osnovu :members članova sa dovoljno istorije za poređenje.',
    'loyalty_redemption_fragment' => ':eligible članova ima pravo da iskoristi nagradu, a :rate% podobnih članova je to ikada i učinilo.',

];
