<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/es/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => 'Todavía no sé responder a eso. Estas son algunas cosas sobre las que puedo informarte:',
    'not_enough_data' => 'Aún no hay suficientes datos para responder de forma fiable — vuelve a comprobarlo cuando tengas más pedidos en este período.',
    'insight_not_active' => 'Este insight no está activo actualmente — sus cifras pueden haber cambiado desde la última vez que lo viste. En su lugar, aquí tienes algunas cosas sobre las que puedo informarte:',

    'word_bigger' => 'una cesta más grande',
    'word_not_bigger' => 'no una cesta más grande',

    'summary_answer' => 'Tuviste :orders pedidos en :period, con :revenue de ingresos y un valor medio de pedido de :aov.',

    'busiest_quietest_answer' => 'Tu franja más activa es :busiest_day a las :busiest_hour con :busiest_orders pedidos. La más tranquila es :quietest_day a las :quietest_hour con :quietest_orders.',

    'slowest_day_answer' => ':slowest_day es tu día más flojo, con una media de :slowest_orders pedidos frente a una media diaria de :average. :busiest_day es el más fuerte, con una media de :busiest_orders.',

    'payment_failures_answer' => 'El :rate% de los intentos de pago fallaron en este período — :failed de :attempts intentos.',

    'service_speed_turnover_with_baseline_fragment' => 'Las mesas se liberan en una media de :current minutos, frente a tus habituales :baseline minutos.',
    'service_speed_turnover_fragment' => 'Las mesas se liberan en una media de :current minutos — aún no hay suficiente historial para establecer una referencia de comparación.',
    'service_speed_lunch_dinner_fragment' => 'El tiempo medio desde el pedido hasta que se sirve es de :lunch minutos en almuerzo y :dinner minutos en cena.',

    'retention_answer' => ':new clientes hicieron un primer pedido en este período, y :returned de ellos (:rate%) volvieron en un plazo de 30 días.',

    'reviews_answer_pending' => 'Tienes :unanswered reseñas sin responder, de las cuales :critical tienen 3 estrellas o menos.',
    'reviews_answer_clear' => 'No hay reseñas sin responder por ahora.',

    'revenue_concentration_answer' => ':count de tus :identified clientes identificados (tu quinto superior) representan el :share% de los ingresos atribuidos.',

    'discounts_answer' => 'Los pedidos con descuento tenían una media de :discounted artículos, y los de precio completo :full — los descuentos están produciendo :effect. Se concedieron :forgone respecto al precio de lista en este período.',

    'tips_overall_fragment' => 'Tu tasa de propinas general es del :rate%.',
    'tips_speed_fragment' => 'Los pedidos servidos en menos de :threshold minutos tienen una tasa de propinas del :fast_rate%, frente al :slow_rate% de los más lentos.',
    'tips_capture_fragment' => 'El :cash% de los pedidos en efectivo muestran una propina registrada, frente al :digital% de los digitales.',

    'sold_out_answer' => ':count artículo(s) estuvieron no disponibles al menos una vez en este período, encabezados por :names.',

    'menu_items_answer' => 'Tus artículos más vendidos por cantidad son :top. :decliner',
    'menu_items_decliner_fragment' => ':name ha bajado un :percent% respecto al período anterior.',
    'menu_pairing_fragment' => ':slow_name se vende mucho menos que el resto de tu menú — combínalo con tu producto más vendido, :best_name, como un paquete con alrededor de :discount% de descuento juntos, o con una oferta de "compra :best_name, llévate :slow_name".',

    'inventory_at_risk_fragment' => ':count artículo(s) del menú dependen de un ingrediente agotado o escaso, encabezados por ":name".',
    'inventory_slow_movers_fragment' => ':money están inmovilizados en existencias que apenas se mueven.',
    'inventory_waste_fragment' => 'Se registraron :money como mermas en este período.',
    'inventory_po_fragment' => ':count pedido(s) de compra necesitan tu atención.',
    'inventory_price_fragment' => ':name subió un :percent% por unidad en tu último pedido de compra.',

    'loyalty_visit_fragment' => 'Los miembros visitaban en promedio :before veces/semana antes de unirse al programa de fidelidad, :after después, según :members miembros con historial suficiente para comparar.',
    'loyalty_redemption_fragment' => ':eligible miembros pueden canjear una recompensa, y el :rate% de los miembros elegibles lo ha hecho alguna vez.',

];
