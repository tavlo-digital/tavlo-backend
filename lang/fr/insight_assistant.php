<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/fr/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => "Je ne sais pas encore répondre à cela. Voici quelques sujets sur lesquels je peux vous renseigner :",
    'not_enough_data' => "Il n'y a pas encore assez de données pour répondre de façon fiable — revenez une fois que vous aurez plus de commandes sur cette période.",
    'insight_not_active' => "Cet insight n'est plus actif actuellement — ses chiffres ont peut-être changé depuis la dernière fois. Voici plutôt quelques sujets sur lesquels je peux vous renseigner :",

    'word_bigger' => 'un panier plus grand',
    'word_not_bigger' => 'pas un panier plus grand',

    'summary_answer' => 'Vous avez eu :orders commandes :period, pour :revenue de chiffre d\'affaires et une valeur moyenne de commande de :aov.',

    'busiest_quietest_answer' => 'Votre créneau le plus chargé est :busiest_day à :busiest_hour avec :busiest_orders commandes. Le plus calme est :quietest_day à :quietest_hour avec :quietest_orders.',

    'slowest_day_answer' => ':slowest_day est votre jour le plus faible, avec une moyenne de :slowest_orders commandes contre une moyenne quotidienne de :average. :busiest_day est votre meilleur jour, avec une moyenne de :busiest_orders.',

    'payment_failures_answer' => ':rate % des tentatives de paiement ont échoué sur cette période — :failed sur :attempts tentatives.',

    'service_speed_turnover_with_baseline_fragment' => 'Les tables se libèrent en moyenne en :current minutes, contre :baseline minutes habituellement.',
    'service_speed_turnover_fragment' => 'Les tables se libèrent en moyenne en :current minutes — pas encore assez d\'historique pour établir une référence de comparaison.',
    'service_speed_lunch_dinner_fragment' => 'Le temps médian entre la commande et le service est de :lunch minutes le midi et :dinner minutes le soir.',

    'retention_answer' => ':new clients ont passé une première commande sur cette période, et :returned d\'entre eux (:rate %) sont revenus dans les 30 jours.',

    'reviews_answer_pending' => 'Vous avez :unanswered avis sans réponse, dont :critical notés 3 étoiles ou moins.',
    'reviews_answer_clear' => 'Aucun avis sans réponse pour le moment.',

    'revenue_concentration_answer' => ':count de vos :identified clients identifiés (votre premier cinquième) représentent :share % du chiffre d\'affaires attribué.',

    'discounts_answer' => 'Les commandes avec réduction comptaient en moyenne :discounted articles, celles à prix plein :full — les réductions produisent :effect. :forgone ont été accordés par rapport au prix catalogue sur cette période.',

    'tips_overall_fragment' => 'Votre taux de pourboire global est de :rate %.',
    'tips_speed_fragment' => 'Les commandes servies en moins de :threshold minutes ont un taux de pourboire de :fast_rate %, contre :slow_rate % pour les plus lentes.',
    'tips_capture_fragment' => ':cash % des commandes en espèces affichent un pourboire enregistré, contre :digital % pour les paiements numériques.',

    'sold_out_answer' => ':count article(s) ont été indisponibles au moins une fois sur cette période, en tête :names.',

    'menu_items_answer' => 'Vos meilleures ventes en quantité sont :top. :decliner',
    'menu_items_decliner_fragment' => ':name est en baisse de :percent % par rapport à la période précédente.',
    'menu_pairing_fragment' => ':slow_name se vend bien moins bien que le reste de votre menu — associez-le à votre best-seller :best_name, sous forme de forfait avec environ :discount % de réduction ensemble, ou avec une offre « achetez :best_name, obtenez :slow_name ».',

    'inventory_at_risk_fragment' => ':count article(s) du menu dépendent d\'un ingrédient en rupture ou presque épuisé, en tête ":name".',
    'inventory_slow_movers_fragment' => ':money sont immobilisés dans un stock qui bouge à peine.',
    'inventory_waste_fragment' => ':money ont été comptabilisés comme pertes sur cette période.',
    'inventory_po_fragment' => ':count commande(s) fournisseur nécessitent votre attention.',
    'inventory_price_fragment' => ':name a augmenté de :percent % par unité sur votre dernière commande fournisseur.',

    'loyalty_visit_fragment' => 'Les membres visitaient en moyenne :before fois/semaine avant d\'adhérer au programme de fidélité, :after fois après, sur :members membres avec un historique suffisant pour comparer.',
    'loyalty_redemption_fragment' => ':eligible membres peuvent utiliser une récompense, et :rate % des membres éligibles l\'ont déjà fait.',

];
