<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/it/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => "Non so ancora rispondere a questo. Ecco alcune cose su cui posso darti informazioni:",
    'not_enough_data' => "Non ci sono ancora abbastanza dati per rispondere in modo affidabile — riprova quando avrai più ordini in questo periodo.",
    'insight_not_active' => "Questo insight non è attualmente attivo — i suoi numeri potrebbero essere cambiati dall'ultima volta che l'hai visto. Ecco invece alcune cose su cui posso darti informazioni:",

    'word_bigger' => 'un carrello più grande',
    'word_not_bigger' => 'non un carrello più grande',

    'summary_answer' => 'Hai avuto :orders ordini :period, per :revenue di fatturato e un valore medio ordine di :aov.',

    'busiest_quietest_answer' => 'Il tuo momento più intenso è :busiest_day alle :busiest_hour con :busiest_orders ordini. Il più tranquillo è :quietest_day alle :quietest_hour con :quietest_orders.',

    'slowest_day_answer' => ':slowest_day è il tuo giorno più debole, con una media di :slowest_orders ordini contro una media giornaliera di :average. :busiest_day è il più forte, con una media di :busiest_orders.',

    'payment_failures_answer' => 'Il :rate% dei tentativi di pagamento è fallito in questo periodo — :failed su :attempts tentativi.',

    'service_speed_turnover_with_baseline_fragment' => 'I tavoli si liberano in media in :current minuti, contro i tuoi consueti :baseline minuti.',
    'service_speed_turnover_fragment' => 'I tavoli si liberano in media in :current minuti — non c\'è ancora abbastanza storico per un confronto.',
    'service_speed_lunch_dinner_fragment' => 'Il tempo mediano dall\'ordine al servizio è di :lunch minuti a pranzo e :dinner minuti a cena.',

    'retention_answer' => ':new clienti hanno effettuato un primo ordine in questo periodo e :returned di loro (:rate%) sono tornati entro 30 giorni.',

    'reviews_answer_pending' => 'Hai :unanswered recensioni senza risposta, di cui :critical con valutazione pari o inferiore a 3 stelle.',
    'reviews_answer_clear' => 'Nessuna recensione senza risposta al momento.',

    'revenue_concentration_answer' => ':count dei tuoi :identified clienti identificati (il tuo quinto migliore) genera il :share% del fatturato attribuito.',

    'discounts_answer' => 'Gli ordini scontati contavano in media :discounted articoli, quelli a prezzo pieno :full — gli sconti stanno producendo :effect. In questo periodo sono stati concessi :forgone rispetto al prezzo di listino.',

    'tips_overall_fragment' => 'La tua percentuale media di mance è del :rate%.',
    'tips_speed_fragment' => 'Gli ordini serviti entro :threshold minuti hanno una percentuale di mance del :fast_rate%, contro il :slow_rate% di quelli più lenti.',
    'tips_capture_fragment' => 'Il :cash% degli ordini in contanti mostra una mancia registrata, contro il :digital% di quelli digitali.',

    'sold_out_answer' => ':count articoli sono risultati non disponibili almeno una volta in questo periodo, in testa :names.',

    'menu_items_answer' => 'I tuoi articoli più venduti per quantità sono :top. :decliner',
    'menu_items_decliner_fragment' => ':name è in calo del :percent% rispetto al periodo precedente.',
    'menu_pairing_fragment' => ':slow_name vende molto meno del resto del tuo menu — abbinalo al tuo best seller :best_name, come bundle con circa il :discount% di sconto insieme, oppure con un\'offerta "compra :best_name, ricevi :slow_name".',

    'inventory_at_risk_fragment' => ':count articoli del menu dipendono da un ingrediente esaurito o in esaurimento, in testa ":name".',
    'inventory_slow_movers_fragment' => ':money sono immobilizzati in scorte che si muovono a malapena.',
    'inventory_waste_fragment' => ':money sono stati registrati come sprechi in questo periodo.',
    'inventory_po_fragment' => ':count ordine/i di acquisto richiedono la tua attenzione.',
    'inventory_price_fragment' => ':name è aumentato del :percent% per unità nel tuo ultimo ordine di acquisto.',

    'loyalty_visit_fragment' => 'I membri visitavano in media :before volte/settimana prima di iscriversi al programma fedeltà, :after dopo, su :members membri con storico sufficiente per il confronto.',
    'loyalty_redemption_fragment' => ':eligible membri hanno diritto a riscattare un premio, e il :rate% dei membri idonei lo ha fatto almeno una volta.',

];
