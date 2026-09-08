<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/ar/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => 'لا يمكنني الإجابة عن ذلك بعد. إليك بعض الأمور التي يمكنني إخبارك بها:',
    'not_enough_data' => 'لا توجد بيانات كافية بعد للإجابة بشكل موثوق — تحقق مرة أخرى عندما يتوفر لديك المزيد من الطلبات في هذه الفترة.',
    'insight_not_active' => 'هذه الرؤية غير نشطة حاليًا — قد تكون أرقامها قد تغيّرت منذ آخر مرة رأيتها فيها. إليك بدلاً من ذلك بعض الأمور التي يمكنني إخبارك بها:',

    'word_bigger' => 'سلة أكبر',
    'word_not_bigger' => 'ليست سلة أكبر',

    'summary_answer' => 'حصلت على :orders طلب خلال :period، بإيرادات قدرها :revenue ومتوسط قيمة طلب :aov.',

    'busiest_quietest_answer' => 'أكثر أوقاتك ازدحامًا هو :busiest_day الساعة :busiest_hour بعدد :busiest_orders طلب. وأهدأ وقت لديك هو :quietest_day الساعة :quietest_hour بعدد :quietest_orders.',

    'slowest_day_answer' => ':slowest_day هو أضعف أيامك، بمتوسط :slowest_orders طلب مقابل متوسط يومي قدره :average. أما :busiest_day فهو الأكثر ازدحامًا بمتوسط :busiest_orders.',

    'payment_failures_answer' => 'فشلت :rate٪ من محاولات الدفع خلال هذه الفترة — :failed من أصل :attempts محاولة.',

    'service_speed_turnover_with_baseline_fragment' => 'تُخلى الطاولات في المتوسط خلال :current دقيقة، مقابل :baseline دقيقة المعتادة لديك.',
    'service_speed_turnover_fragment' => 'تُخلى الطاولات في المتوسط خلال :current دقيقة — لا يوجد سجل تداول كافٍ بعد لوضع مرجع للمقارنة.',
    'service_speed_lunch_dinner_fragment' => 'الوقت الوسيط من الطلب إلى التقديم هو :lunch دقيقة في الغداء و:dinner دقيقة في العشاء.',

    'retention_answer' => 'قام :new ضيفًا بأول طلب لهم خلال هذه الفترة، وعاد :returned منهم (:rate٪) خلال 30 يومًا.',

    'reviews_answer_pending' => 'لديك :unanswered تقييم بلا رد، منها :critical بتقييم 3 نجوم أو أقل.',
    'reviews_answer_clear' => 'لا توجد تقييمات بلا رد حاليًا.',

    'revenue_concentration_answer' => 'يمثّل :count من ضيوفك المحدَّدين البالغ عددهم :identified (خُمسك الأعلى) نسبة :share٪ من الإيرادات المنسوبة.',

    'discounts_answer' => 'بلغ متوسط الطلبات المخفَّضة :discounted عنصرًا، والطلبات بالسعر الكامل :full — الخصومات تُنتج :effect. تم التنازل عن :forgone مقابل السعر المعلن خلال هذه الفترة.',

    'tips_overall_fragment' => 'معدل الإكرامية الإجمالي لديك هو :rate٪.',
    'tips_speed_fragment' => 'الطلبات المُقدَّمة خلال :threshold دقيقة تحمل معدل إكرامية :fast_rate٪، مقابل :slow_rate٪ للطلبات الأبطأ.',
    'tips_capture_fragment' => ':cash٪ من الطلبات النقدية تُظهر إكرامية مسجَّلة، مقابل :digital٪ للطلبات الرقمية.',

    'sold_out_answer' => 'أصبح :count عنصر(عناصر) غير متاح مرة واحدة على الأقل خلال هذه الفترة، وعلى رأسها :names.',

    'menu_items_answer' => 'أفضل عناصرك مبيعًا من حيث الكمية هي :top. :decliner',
    'menu_items_decliner_fragment' => 'انخفض :name بنسبة :percent٪ عن الفترة السابقة.',
    'menu_pairing_fragment' => ':slow_name يُباع بشكل أقل بكثير من باقي قائمتك — اقرنه بأفضل عنصر مبيعًا لديك :best_name، كحزمة بخصم حوالي :discount٪ معًا، أو كعرض "اشترِ :best_name واحصل على :slow_name".',

    'inventory_at_risk_fragment' => 'يعتمد :count عنصر(عناصر) من القائمة على مكوّن نفد أو أوشك على النفاد، وعلى رأسها ":name".',
    'inventory_slow_movers_fragment' => ':money مجمَّدة في مخزون بالكاد يتحرك.',
    'inventory_waste_fragment' => 'تم شطب :money كهدر خلال هذه الفترة.',
    'inventory_po_fragment' => 'يحتاج :count أمر (أوامر) شراء إلى انتباهك.',
    'inventory_price_fragment' => 'ارتفع سعر :name بنسبة :percent٪ لكل وحدة في آخر أمر شراء لك.',

    'loyalty_visit_fragment' => 'كان الأعضاء يزورون بمعدل :before مرة/أسبوع قبل الانضمام إلى برنامج الولاء، و:after بعد الانضمام، عبر :members عضوًا لديهم سجل كافٍ للمقارنة.',
    'loyalty_redemption_fragment' => 'يستحق :eligible عضوًا استبدال مكافأة، وقد فعل ذلك :rate٪ من الأعضاء المستحقين في وقت ما.',

];
