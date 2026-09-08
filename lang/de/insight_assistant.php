<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/de/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => "Das kann ich noch nicht beantworten. Hier sind ein paar Dinge, über die ich dir Auskunft geben kann:",
    'not_enough_data' => "Dafür gibt es noch nicht genug Daten für eine verlässliche Antwort — schau später noch einmal vorbei, sobald du mehr Bestellungen in diesem Zeitraum hast.",
    'insight_not_active' => "Dieser Insight ist derzeit nicht aktiv — seine Zahlen könnten sich geändert haben, seit du ihn zuletzt gesehen hast. Hier sind stattdessen ein paar Dinge, über die ich dir Auskunft geben kann:",

    'word_bigger' => 'einen größeren Warenkorb',
    'word_not_bigger' => 'keinen größeren Warenkorb',

    'summary_answer' => 'Du hattest :orders Bestellungen :period, mit :revenue Umsatz und einem durchschnittlichen Bestellwert von :aov.',

    'busiest_quietest_answer' => 'Deine geschäftigste Zeit ist :busiest_day um :busiest_hour mit :busiest_orders Bestellungen. Am ruhigsten ist es :quietest_day um :quietest_hour mit :quietest_orders.',

    'slowest_day_answer' => ':slowest_day ist dein schwächster Tag mit durchschnittlich :slowest_orders Bestellungen gegenüber einem Tagesdurchschnitt von :average. :busiest_day ist dein stärkster mit durchschnittlich :busiest_orders.',

    'payment_failures_answer' => ':rate% der Zahlungsversuche sind in diesem Zeitraum fehlgeschlagen — :failed von :attempts Versuchen.',

    'service_speed_turnover_with_baseline_fragment' => 'Tische werden im Schnitt in :current Minuten neu belegt, gegenüber sonst üblichen :baseline Minuten.',
    'service_speed_turnover_fragment' => 'Tische werden im Schnitt in :current Minuten neu belegt — noch nicht genug Handelsverlauf für einen Vergleichswert.',
    'service_speed_lunch_dinner_fragment' => 'Die mediane Zeit von Bestellung bis Servieren beträgt :lunch Minuten mittags und :dinner Minuten abends.',

    'retention_answer' => ':new Gäste haben in diesem Zeitraum zum ersten Mal bestellt, und :returned davon (:rate%) sind innerhalb von 30 Tagen zurückgekehrt.',

    'reviews_answer_pending' => 'Du hast :unanswered unbeantwortete Bewertungen, davon :critical mit 3 Sternen oder weniger.',
    'reviews_answer_clear' => 'Aktuell keine unbeantworteten Bewertungen.',

    'revenue_concentration_answer' => ':count deiner :identified identifizierten Gäste (dein oberstes Fünftel) machen :share% des zugeordneten Umsatzes aus.',

    'discounts_answer' => 'Rabattierte Bestellungen hatten im Schnitt :discounted Artikel, Bestellungen zum vollen Preis :full — Rabatte führen zu :effect. In diesem Zeitraum wurden :forgone gegenüber dem Listenpreis erlassen.',

    'tips_overall_fragment' => 'Deine durchschnittliche Trinkgeldquote liegt bei :rate%.',
    'tips_speed_fragment' => 'Bestellungen, die innerhalb von :threshold Minuten serviert werden, erhalten eine Trinkgeldquote von :fast_rate%, gegenüber :slow_rate% bei langsameren.',
    'tips_capture_fragment' => 'Bei :cash% der Barzahlungen ist ein Trinkgeld erfasst, gegenüber :digital% bei digitalen Zahlungen.',

    'sold_out_answer' => ':count Artikel waren in diesem Zeitraum mindestens einmal nicht verfügbar, angeführt von :names.',

    'menu_items_answer' => 'Deine meistverkauften Artikel nach Menge sind :top. :decliner',
    'menu_items_decliner_fragment' => ':name liegt :percent% unter dem vorherigen Zeitraum.',
    'menu_pairing_fragment' => ':slow_name verkauft sich deutlich schlechter als der Rest deines Menüs — kombiniere es mit deinem Bestseller :best_name, als Bundle mit rund :discount% Rabatt zusammen, oder als „Kaufe :best_name, erhalte :slow_name“-Angebot.',

    'inventory_at_risk_fragment' => ':count Menüartikel hängen von einer Zutat ab, die nicht vorrätig ist oder zur Neige geht, angeführt von „:name“.',
    'inventory_slow_movers_fragment' => ':money stecken in Lagerbestand, der sich kaum bewegt.',
    'inventory_waste_fragment' => ':money wurden in diesem Zeitraum als Abfall abgeschrieben.',
    'inventory_po_fragment' => ':count Bestellung(en) bei Lieferanten benötigen deine Aufmerksamkeit.',
    'inventory_price_fragment' => ':name ist bei deiner letzten Bestellung um :percent% pro Einheit teurer geworden.',

    'loyalty_visit_fragment' => 'Mitglieder besuchten dich vor dem Beitritt zum Treueprogramm im Schnitt :before Mal/Woche, danach :after Mal — über :members Mitglieder mit ausreichend Verlauf zum Vergleich.',
    'loyalty_redemption_fragment' => ':eligible Mitglieder können eine Prämie einlösen, und :rate% der berechtigten Mitglieder haben das jemals getan.',

];
