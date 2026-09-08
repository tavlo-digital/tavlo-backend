<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/nl/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => 'Dat kan ik nog niet beantwoorden. Dit zijn een paar dingen waar ik je wel over kan vertellen:',
    'not_enough_data' => 'Er zijn nog niet genoeg gegevens voor een betrouwbaar antwoord — kom terug zodra je meer bestellingen hebt in deze periode.',
    'insight_not_active' => 'Dit inzicht is momenteel niet actief — de cijfers kunnen zijn veranderd sinds je het voor het laatst zag. Dit zijn in plaats daarvan een paar dingen waar ik je over kan vertellen:',

    'word_bigger' => 'een groter mandje',
    'word_not_bigger' => 'geen groter mandje',

    'summary_answer' => 'Je had :orders bestellingen in :period, voor :revenue omzet en een gemiddelde bestelwaarde van :aov.',

    'busiest_quietest_answer' => 'Je drukste moment is :busiest_day om :busiest_hour met :busiest_orders bestellingen. Je rustigste is :quietest_day om :quietest_hour met :quietest_orders.',

    'slowest_day_answer' => ':slowest_day is je zwakste dag, met gemiddeld :slowest_orders bestellingen tegenover een dagelijks gemiddelde van :average. :busiest_day is je sterkste, met gemiddeld :busiest_orders.',

    'payment_failures_answer' => ':rate% van de betaalpogingen mislukte deze periode — :failed van de :attempts pogingen.',

    'service_speed_turnover_with_baseline_fragment' => 'Tafels komen gemiddeld na :current minuten weer vrij, tegenover je gebruikelijke :baseline minuten.',
    'service_speed_turnover_fragment' => 'Tafels komen gemiddeld na :current minuten weer vrij — nog niet genoeg handelsgeschiedenis voor een vergelijkingsbasis.',
    'service_speed_lunch_dinner_fragment' => 'De mediane tijd van bestelling tot serveren is :lunch minuten bij de lunch en :dinner minuten bij het diner.',

    'retention_answer' => ':new gasten plaatsten deze periode een eerste bestelling en :returned daarvan (:rate%) kwamen binnen 30 dagen terug.',

    'reviews_answer_pending' => 'Je hebt :unanswered onbeantwoorde recensies, waarvan :critical met 3 sterren of minder.',
    'reviews_answer_clear' => 'Op dit moment geen onbeantwoorde recensies.',

    'revenue_concentration_answer' => ':count van je :identified geïdentificeerde gasten (je beste vijfde) is goed voor :share% van de toegewezen omzet.',

    'discounts_answer' => 'Bestellingen met korting bevatten gemiddeld :discounted items, bestellingen tegen volle prijs :full — kortingen leveren :effect op. Deze periode is :forgone weggegeven ten opzichte van de winkelprijs.',

    'tips_overall_fragment' => 'Je algehele fooipercentage is :rate%.',
    'tips_speed_fragment' => 'Bestellingen die binnen :threshold minuten zijn geserveerd, hebben een fooipercentage van :fast_rate%, tegenover :slow_rate% voor tragere.',
    'tips_capture_fragment' => 'Bij :cash% van de contante bestellingen is een fooi geregistreerd, tegenover :digital% van de digitale.',

    'sold_out_answer' => ':count item(s) waren deze periode minstens één keer niet beschikbaar, aangevoerd door :names.',

    'menu_items_answer' => 'Je bestsellers naar aantal zijn :top. :decliner',
    'menu_items_decliner_fragment' => ':name daalt :percent% ten opzichte van de vorige periode.',
    'menu_pairing_fragment' => ':slow_name verkoopt veel slechter dan de rest van je menu — combineer het met je bestseller :best_name, als bundel met samen ongeveer :discount% korting, of als aanbieding "koop :best_name, krijg :slow_name".',

    'inventory_at_risk_fragment' => ':count menu-item(s) zijn afhankelijk van een ingrediënt dat op is of bijna op is, aangevoerd door ":name".',
    'inventory_slow_movers_fragment' => ':money zit vast in voorraad die nauwelijks beweegt.',
    'inventory_waste_fragment' => ':money is deze periode afgeschreven als verspilling.',
    'inventory_po_fragment' => ':count inkooporder(s) hebben je aandacht nodig.',
    'inventory_price_fragment' => ':name is bij je laatste inkooporder :percent% per eenheid duurder geworden.',

    'loyalty_visit_fragment' => 'Leden kwamen gemiddeld :before keer/week langs voordat ze zich aansloten bij het loyaliteitsprogramma, :after keer daarna, over :members leden met voldoende geschiedenis om te vergelijken.',
    'loyalty_redemption_fragment' => ':eligible leden komen in aanmerking om een beloning in te wisselen, en :rate% van de in aanmerking komende leden heeft dat ooit gedaan.',

];
