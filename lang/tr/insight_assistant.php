<?php

/**
 * Every string InsightQueryEngine.php renders, keyed by topic + field —
 * the same convention as lang/tr/insights.php, which this file sits
 * alongside. Translated against lang/en/insight_assistant.php.
 */
return [

    'no_match' => 'Bunu henüz yanıtlayamıyorum. İşte sana anlatabileceğim bazı şeyler:',
    'not_enough_data' => 'Bunu güvenilir şekilde yanıtlamak için henüz yeterli veri yok — bu dönemde daha fazla siparişiniz olduğunda tekrar kontrol edin.',
    'insight_not_active' => 'Bu içgörü şu anda etkin değil — sayıları son gördüğünüzden bu yana değişmiş olabilir. Bunun yerine size anlatabileceğim bazı şeyler:',

    'word_bigger' => 'daha büyük bir sepet',
    'word_not_bigger' => 'daha büyük olmayan bir sepet',

    'summary_answer' => ':period içinde :orders siparişiniz oldu, :revenue gelir ve :aov ortalama sipariş değeriyle.',

    'busiest_quietest_answer' => 'En yoğun zaman diliminiz :busiest_day günü saat :busiest_hour, :busiest_orders siparişle. En sakin zamanınız ise :quietest_day günü saat :quietest_hour, :quietest_orders siparişle.',

    'slowest_day_answer' => ':slowest_day en durgun gününüz, günlük ortalama :average siparişe karşılık ortalama :slowest_orders sipariş alıyorsunuz. :busiest_day ise en yoğun gününüz, ortalama :busiest_orders siparişle.',

    'payment_failures_answer' => 'Bu dönemde ödeme denemelerinin %:rate\'i başarısız oldu — :attempts denemeden :failed tanesi.',

    'service_speed_turnover_with_baseline_fragment' => 'Masalar ortalama :current dakikada boşalıyor, olağan :baseline dakikanıza karşılık.',
    'service_speed_turnover_fragment' => 'Masalar ortalama :current dakikada boşalıyor — karşılaştırma yapılacak yeterli işlem geçmişi henüz yok.',
    'service_speed_lunch_dinner_fragment' => 'Siparişten servise medyan süre öğlen :lunch dakika, akşam ise :dinner dakika.',

    'retention_answer' => 'Bu dönemde :new misafir ilk siparişini verdi ve bunlardan :returned tanesi (%:rate) 30 gün içinde geri döndü.',

    'reviews_answer_pending' => ':unanswered yanıtsız değerlendirmeniz var, bunlardan :critical tanesi 3 yıldız veya altı.',
    'reviews_answer_clear' => 'Şu anda yanıtsız değerlendirme yok.',

    'revenue_concentration_answer' => ':identified tanımlanmış misafirinizden :count tanesi (en üst beşte biriniz) atfedilen gelirin %:share\'ini oluşturuyor.',

    'discounts_answer' => 'İndirimli siparişlerde ortalama :discounted ürün, tam fiyatlı siparişlerde ortalama :full ürün vardı — indirimler :effect yaratıyor. Bu dönemde liste fiyatına göre :forgone tutarında indirim yapıldı.',

    'tips_overall_fragment' => 'Genel bahşiş oranınız %:rate.',
    'tips_speed_fragment' => ':threshold dakika içinde servis edilen siparişlerde bahşiş oranı %:fast_rate, daha yavaş olanlarda ise %:slow_rate.',
    'tips_capture_fragment' => 'Nakit siparişlerin %:cash\'inde kayıtlı bahşiş var, dijital siparişlerde ise bu oran %:digital.',

    'sold_out_answer' => 'Bu dönemde :count ürün en az bir kez tükendi, başında :names geliyor.',

    'menu_items_answer' => 'Miktara göre en çok satan ürünleriniz :top. :decliner',
    'menu_items_decliner_fragment' => ':name önceki döneme göre %:percent düştü.',
    'menu_pairing_fragment' => ':slow_name menünüzün geri kalanının çok gerisinde satıyor — onu en çok satanınız :best_name ile eşleştirin, birlikte yaklaşık %:discount indirimli bir paket olarak ya da "‌:best_name al, :slow_name kazan" teklifiyle.',

    'inventory_at_risk_fragment' => ':count menü ürünü, stoğu tükenen veya azalan bir malzemeye bağlı, başında ":name" var.',
    'inventory_slow_movers_fragment' => ':money, neredeyse hiç hareket etmeyen stokta bağlı durumda.',
    'inventory_waste_fragment' => 'Bu dönemde :money israf olarak kaydedildi.',
    'inventory_po_fragment' => ':count satın alma siparişi dikkatinizi gerektiriyor.',
    'inventory_price_fragment' => ':name son satın alma siparişinizde birim başına %:percent arttı.',

    'loyalty_visit_fragment' => 'Üyeler sadakat programına katılmadan önce ortalama haftada :before kez, katıldıktan sonra ise :after kez ziyaret etti — karşılaştırma için yeterli geçmişi olan :members üye üzerinden.',
    'loyalty_redemption_fragment' => ':eligible üye bir ödülü kullanmaya hak kazandı ve uygun üyelerin %:rate\'i bunu şimdiye kadar en az bir kez kullandı.',

];
