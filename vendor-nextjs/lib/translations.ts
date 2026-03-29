// Multi-language support for the restaurant ordering system

export const SUPPORTED_LANGUAGES = [
  { code: 'en', name: 'English', flag: '🇬🇧', dir: 'ltr' },
  { code: 'de', name: 'Deutsch', flag: '🇩🇪', dir: 'ltr' },
  { code: 'it', name: 'Italiano', flag: '🇮🇹', dir: 'ltr' },
  { code: 'fr', name: 'Français', flag: '🇫🇷', dir: 'ltr' },
  { code: 'ar', name: 'العربية', flag: '🇸🇦', dir: 'rtl' },
  { code: 'tr', name: 'Türkçe', flag: '🇹🇷', dir: 'ltr' },
  { code: 'zh', name: '中文', flag: '🇨🇳', dir: 'ltr' },
  { code: 'ja', name: '日本語', flag: '🇯🇵', dir: 'ltr' },
  { code: 'sr', name: 'Српски', flag: '🇷🇸', dir: 'ltr' },
  { code: 'cs', name: 'Čeština', flag: '🇨🇿', dir: 'ltr' },
  { code: 'es', name: 'Español', flag: '🇪🇸', dir: 'ltr' }
] as const;

export type LanguageCode = typeof SUPPORTED_LANGUAGES[number]['code'];

// UI translations for static text
export const UI_TRANSLATIONS: Record<string, Partial<Record<LanguageCode, string>>> = {
  // Navigation & General
  'menu': {
    en: 'Menu',
    de: 'Speisekarte',
    it: 'Menu',
    fr: 'Menu',
    ar: 'القائمة',
    tr: 'Menü',
    zh: '菜单',
    ja: 'メニュー',
    sr: 'Мени',
    cs: 'Menu',
    es: 'Menú'
  },
  'basket': {
    en: 'Basket',
    de: 'Warenkorb',
    it: 'Carrello',
    fr: 'Panier',
    ar: 'السلة',
    tr: 'Sepet',
    zh: '购物车',
    ja: 'カート',
    sr: 'Корпа',
    cs: 'Košík',
    es: 'Cesta'
  },
  'orders': {
    en: 'Orders',
    de: 'Bestellungen',
    it: 'Ordini',
    fr: 'Commandes',
    ar: 'الطلبات',
    tr: 'Siparişler',
    zh: '订单',
    ja: '注文',
    sr: 'Поруџбине',
    cs: 'Objednávky',
    es: 'Pedidos'
  },
  'profile': {
    en: 'Profile',
    de: 'Profil',
    it: 'Profilo',
    fr: 'Profil',
    ar: 'الملف الشخصي',
    tr: 'Profil',
    zh: '个人资料',
    ja: 'プロフィール',
    sr: 'Профил',
    cs: 'Profil',
    es: 'Perfil'
  },

  // Menu Page
  'search_menu': {
    en: 'Search menu...',
    de: 'Speisekarte durchsuchen...',
    it: 'Cerca nel menu...',
    fr: 'Rechercher dans le menu...',
    ar: 'البحث في القائمة...',
    tr: 'Menüde ara...',
    zh: '搜索菜单...',
    ja: 'メニューを検索...',
    sr: 'Претражи мени...',
    cs: 'Hledat v menu...',
    es: 'Buscar en el menú...'
  },
  'search_dishes': {
    en: 'Search dishes...',
    de: 'Gerichte suchen...',
    ar: 'البحث عن الأطباق...'
  },
  'view_restaurant_info': {
    en: 'View restaurant info',
    de: 'Restaurantinformationen ansehen',
    ar: 'عرض معلومات المطعم'
  },
  'all_items': {
    en: 'All Items',
    de: 'Alle Gerichte',
    it: 'Tutti i Piatti',
    fr: 'Tous les Plats',
    ar: 'جميع الأطباق',
    tr: 'Tüm Ürünler',
    zh: '所有菜品',
    ja: 'すべてのアイテム',
    sr: 'Све ставке',
    cs: 'Všechny položky',
    es: 'Todos los Artículos'
  },

  // Dish Details
  'add_to_order': {
    en: 'Add to order',
    de: 'Zur Bestellung hinzufügen',
    it: 'Aggiungi all\'ordine',
    fr: 'Ajouter à la commande',
    ar: 'أضف إلى الطلب',
    tr: 'Siparişe ekle',
    zh: '加入订单',
    ja: '注文に追加',
    sr: 'Додај у поруџбину',
    cs: 'Přidat k objednávce',
    es: 'Añadir al pedido'
  },
  'quantity': {
    en: 'Quantity',
    de: 'Menge',
    it: 'Quantità',
    fr: 'Quantité',
    ar: 'الكمية',
    tr: 'Miktar',
    zh: '数量',
    ja: '数量',
    sr: 'Количина',
    cs: 'Množství',
    es: 'Cantidad'
  },
  'special_instructions': {
    en: 'Special instructions (optional)',
    de: 'Besondere Wünsche (optional)',
    it: 'Istruzioni speciali (opzionale)',
    fr: 'Instructions spéciales (facultatif)',
    ar: 'تعليمات خاصة (اختياري)',
    tr: 'Özel talimatlar (isteğe bağlı)',
    zh: '特殊说明（可选）',
    ja: '特別な指示（任意）',
    sr: 'Посебне инструкције (опционо)',
    cs: 'Speciální pokyny (volitelné)',
    es: 'Instrucciones especiales (opcional)'
  },
  'choose_options': {
    en: 'Choose Your Options',
    de: 'Wählen Sie Ihre Optionen',
    it: 'Scegli le Tue Opzioni',
    fr: 'Choisissez Vos Options',
    ar: 'اختر خياراتك',
    tr: 'Seçeneklerinizi Seçin',
    zh: '选择您的选项',
    ja: 'オプションを選択',
    sr: 'Изаберите Опције',
    cs: 'Vyberte Možnosti',
    es: 'Elige Tus Opciones'
  },
  'extra_addons': {
    en: 'Extra Add-ons',
    de: 'Zusätzliche Extras',
    it: 'Extra Aggiuntivi',
    fr: 'Suppléments',
    ar: 'إضافات إضافية',
    tr: 'Ekstra Eklemeler',
    zh: '额外附加',
    ja: '追加オプション',
    sr: 'Додатни додаци',
    cs: 'Extra Doplňky',
    es: 'Extras Adicionales'
  },
  'free_addons': {
    en: 'Free Add-ons',
    de: 'Kostenlose Extras',
    it: 'Aggiunte Gratuite',
    fr: 'Suppléments Gratuits',
    ar: 'إضافات مجانية',
    tr: 'Ücretsiz Eklemeler',
    zh: '免费附加',
    ja: '無料オプション',
    sr: 'Бесплатни додаци',
    cs: 'Zdarma Doplňky',
    es: 'Extras Gratis'
  },
  'remove_ingredients': {
    en: 'Remove Ingredients',
    de: 'Zutaten entfernen',
    it: 'Rimuovi Ingredienti',
    fr: 'Retirer Ingrédients',
    ar: 'إزالة المكونات',
    tr: 'Malzemeleri Çıkar',
    zh: '移除配料',
    ja: '材料を除去',
    sr: 'Уклоните састојке',
    cs: 'Odebrat Ingredience',
    es: 'Quitar Ingredientes'
  },

  // Basket
  'your_basket': {
    en: 'Your Basket',
    de: 'Ihr Warenkorb',
    it: 'Il Tuo Carrello',
    fr: 'Votre Panier',
    ar: 'سلتك',
    tr: 'Sepetiniz',
    zh: '您的购物车',
    ja: 'あなたのカート',
    sr: 'Ваша Корпа',
    cs: 'Váš Košík',
    es: 'Tu Cesta'
  },
  'empty_basket': {
    en: 'Your basket is empty',
    de: 'Ihr Warenkorb ist leer',
    it: 'Il tuo carrello è vuoto',
    fr: 'Votre panier est vide',
    ar: 'سلتك فارغة',
    tr: 'Sepetiniz boş',
    zh: '您的购物车是空的',
    ja: 'カートが空です',
    sr: 'Ваша корпа је празна',
    cs: 'Váš košík je prázdný',
    es: 'Tu cesta está vacía'
  },
  'subtotal': {
    en: 'Subtotal',
    de: 'Zwischensumme',
    it: 'Subtotale',
    fr: 'Sous-total',
    ar: 'المجموع الفرعي',
    tr: 'Ara Toplam',
    zh: '小计',
    ja: '小計',
    sr: 'Међузбир',
    cs: 'Mezisoučet',
    es: 'Subtotal'
  },
  'service_fee': {
    en: 'Service Fee',
    de: 'Servicegebühr',
    it: 'Spese di Servizio',
    fr: 'Frais de Service',
    ar: 'رسوم الخدمة',
    tr: 'Servis Ücreti',
    zh: '服务费',
    ja: 'サービス料',
    sr: 'Услужна Накнада',
    cs: 'Servisní Poplatek',
    es: 'Cargo por Servicio'
  },
  'total': {
    en: 'Total',
    de: 'Gesamt',
    it: 'Totale',
    fr: 'Total',
    ar: 'المجموع',
    tr: 'Toplam',
    zh: '总计',
    ja: '合計',
    sr: 'Укупно',
    cs: 'Celkem',
    es: 'Total'
  },
  'proceed_to_payment': {
    en: 'Proceed to Payment',
    de: 'Zur Zahlung',
    it: 'Procedi al Pagamento',
    fr: 'Procéder au Paiement',
    ar: 'المتابعة إلى الدفع',
    tr: 'Ödemeye Geç',
    zh: '前往付款',
    ja: '支払いへ進む',
    sr: 'Настави ка плаћању',
    cs: 'Pokračovat k Platbě',
    es: 'Proceder al Pago'
  },

  // Payment
  'payment': {
    en: 'Payment',
    de: 'Zahlung',
    it: 'Pagamento',
    fr: 'Paiement',
    ar: 'الدفع',
    tr: 'Ödeme',
    zh: '付款',
    ja: '支払い',
    sr: 'Плаћање',
    cs: 'Platba',
    es: 'Pago'
  },
  'order_summary': {
    en: 'Order Summary',
    de: 'Bestellübersicht',
    it: 'Riepilogo Ordine',
    fr: 'Résumé de Commande',
    ar: 'ملخص الطلب',
    tr: 'Sipariş Özeti',
    zh: '订单摘要',
    ja: '注文概要',
    sr: 'Преглед Поруџбине',
    cs: 'Přehled Objednávky',
    es: 'Resumen del Pedido'
  },
  'select_payment_method': {
    en: 'Select Payment Method',
    de: 'Zahlungsmethode auswählen',
    it: 'Seleziona Metodo di Pagamento',
    fr: 'Sélectionner le Mode de Paiement',
    ar: 'اختر طريقة الدفع',
    tr: 'Ödeme Yöntemi Seçin',
    zh: '选择付款方式',
    ja: '支払い方法を選択',
    sr: 'Изаберите Начин Плаћања',
    cs: 'Vyberte Způsob Platby',
    es: 'Seleccionar Método de Pago'
  },

  // Dietary Tags
  'vegetarian': {
    en: 'Vegetarian',
    de: 'Vegetarisch',
    it: 'Vegetariano',
    fr: 'Végétarien',
    ar: 'نباتي',
    tr: 'Vejetaryen',
    zh: '素食',
    ja: 'ベジタリアン',
    sr: 'Вегетаријанско',
    cs: 'Vegetariánské',
    es: 'Vegetariano'
  },
  'vegan': {
    en: 'Vegan',
    de: 'Vegan',
    it: 'Vegano',
    fr: 'Végétalien',
    ar: 'نباتي صرف',
    tr: 'Vegan',
    zh: '纯素',
    ja: 'ヴィーガン',
    sr: 'Веган',
    cs: 'Veganské',
    es: 'Vegano'
  },
  'gluten-free': {
    en: 'Gluten-Free',
    de: 'Glutenfrei',
    it: 'Senza Glutine',
    fr: 'Sans Gluten',
    ar: 'خالي من الغلوتين',
    tr: 'Glütensiz',
    zh: '无麸质',
    ja: 'グルテンフリー',
    sr: 'Без глутена',
    cs: 'Bezlepkové',
    es: 'Sin Gluten'
  },
  'spicy': {
    en: 'Spicy',
    de: 'Scharf',
    it: 'Piccante',
    fr: 'Épicé',
    ar: 'حار',
    tr: 'Acı',
    zh: '辣',
    ja: '辛い',
    sr: 'Љуто',
    cs: 'Pikantní',
    es: 'Picante'
  },

  // QR Landing Page
  'welcome_to': {
    en: 'Welcome to',
    de: 'Willkommen bei',
    ar: 'مرحباً بك في'
  },
  'authentic_cuisine': {
    en: 'Authentic Italian cuisine in the heart of Vienna',
    de: 'Authentische italienische Küche im Herzen von Wien',
    ar: 'مطبخ إيطالي أصيل في قلب فيينا'
  },
  'address': {
    en: 'Address',
    de: 'Adresse',
    ar: 'العنوان'
  },
  'phone': {
    en: 'Phone',
    de: 'Telefon',
    ar: 'الهاتف'
  },
  'table_detected': {
    en: 'Table {number} detected',
    de: 'Tisch {number} erkannt',
    ar: 'تم اكتشاف الطاولة {number}'
  },
  'choose_language': {
    en: 'Choose language',
    de: 'Sprache wählen',
    ar: 'اختر اللغة'
  },
  'how_many_people': {
    en: 'How many people are at this table?',
    de: 'Wie viele Personen sind an diesem Tisch?',
    ar: 'كم عدد الأشخاص على هذه الطاولة؟'
  },
  'people': {
    en: 'people',
    de: 'Personen',
    ar: 'أشخاص'
  },
  'order_together': {
    en: 'Order together (shared basket)',
    de: 'Zusammen bestellen (gemeinsamer Warenkorb)',
    ar: 'اطلب معًا (سلة مشتركة)'
  },
  'start_ordering': {
    en: 'Start ordering',
    de: 'Bestellung beginnen',
    ar: 'ابدأ الطلب'
  },
  'continue_as_guest': {
    en: 'Continue as Guest',
    de: 'Als Gast fortfahren',
    ar: 'المتابعة كضيف'
  },
  'sign_in': {
    en: 'Sign in',
    de: 'Anmelden',
    ar: 'تسجيل الدخول'
  },
  'register': {
    en: 'Register',
    de: 'Registrieren',
    ar: 'تسجيل'
  },

  // Auth Screen
  'email': {
    en: 'Email',
    de: 'E-Mail',
    ar: 'البريد الإلكتروني'
  },
  'password': {
    en: 'Password',
    de: 'Passwort',
    ar: 'كلمة المرور'
  },
  'full_name': {
    en: 'Full Name',
    de: 'Vollständiger Name',
    ar: 'الاسم الكامل'
  },
  'phone_number': {
    en: 'Phone Number',
    de: 'Telefonnummer',
    ar: 'رقم الهاتف'
  },
  'create_account': {
    en: 'Create Account',
    de: 'Konto erstellen',
    ar: 'إنشاء حساب'
  },
  'already_have_account': {
    en: 'Already have an account?',
    de: 'Haben Sie bereits ein Konto?',
    ar: 'هل لديك حساب بالفعل؟'
  },
  'dont_have_account': {
    en: 'Don\'t have an account?',
    de: 'Noch kein Konto?',
    ar: 'ليس لديك حساب؟'
  },

  // Auth Modal
  'want_extra_benefits': {
    en: 'Want extra benefits?',
    de: 'Zusätzliche Vorteile gewünscht?',
    ar: 'هل تريد مزايا إضافية؟'
  },
  'auth_benefits_description': {
    en: 'Sign in or register to collect points, see past orders, and write reviews.',
    de: 'Melden Sie sich an oder registrieren Sie sich, um Punkte zu sammeln, vergangene Bestellungen einzusehen und Bewertungen zu schreiben.',
    ar: 'سجل الدخول أو أنشئ حسابًا لجمع النقاط ورؤية الطلبات السابقة وكتابة التقييمات.'
  },
  'register_to_collect_points': {
    en: 'Register to collect points and track orders',
    de: 'Registrieren Sie sich, um Punkte zu sammeln und Bestellungen zu verfolgen',
    ar: 'سجل لجمع النقاط وتتبع الطلبات'
  },
  'welcome_back_sign_in': {
    en: 'Welcome back! Sign in to continue',
    de: 'Willkommen zurück! Melden Sie sich an, um fortzufahren',
    ar: 'مرحبًا بعودتك! سجل الدخول للمتابعة'
  },
  'full_name_optional': {
    en: 'Full Name (optional)',
    de: 'Vollständiger Name (optional)',
    ar: 'الاسم الكامل (اختياري)'
  },
  'phone_optional': {
    en: 'Phone (optional)',
    de: 'Telefon (optional)',
    ar: 'الهاتف (اختياري)'
  },
  'phone_required': {
    en: 'Phone Number *',
    de: 'Telefonnummer *',
    ar: 'رقم الهاتف *'
  },
  'phone_required_error': {
    en: 'Phone number is required',
    de: 'Telefonnummer ist erforderlich',
    ar: 'رقم الهاتف مطلوب'
  },
  'minimum_6_characters': {
    en: 'Minimum 6 characters',
    de: 'Mindestens 6 Zeichen',
    ar: 'الحد الأدنى 6 أحرف'
  },

  // Menu Page
  'all': {
    en: 'All',
    de: 'Alle',
    ar: 'الكل'
  },
  'recommended': {
    en: 'Recommended',
    de: 'Empfohlen',
    ar: 'موصى به'
  },
  'appetizers': {
    en: 'Appetizers',
    de: 'Vorspeisen',
    ar: 'المقبلات'
  },
  'mains': {
    en: 'Mains',
    de: 'Hauptgerichte',
    ar: 'الأطباق الرئيسية'
  },
  'salads': {
    en: 'Salads',
    de: 'Salate',
    ar: 'السلطات'
  },
  'desserts': {
    en: 'Desserts',
    de: 'Desserts',
    ar: 'الحلويات'
  },
  'drinks': {
    en: 'Drinks',
    de: 'Getränke',
    ar: 'المشروبات'
  },
  'popular_right_now': {
    en: 'Popular Right Now',
    de: 'Gerade beliebt',
    ar: 'مشهورة الآن'
  },
  'cal': {
    en: 'cal',
    de: 'kcal',
    ar: 'سعرة'
  },
  'dishes': {
    en: 'dishes',
    de: 'Gerichte',
    ar: 'أطباق'
  },

  // Dish Details
  'calories': {
    en: 'Calories',
    de: 'Kalorien',
    ar: 'السعرات الحرارية'
  },
  'protein': {
    en: 'Protein',
    de: 'Eiweiß',
    ar: 'البروتين'
  },
  'carbs': {
    en: 'Carbs',
    de: 'Kohlenhydrate',
    ar: 'الكربوهيدرات'
  },
  'fat': {
    en: 'Fat',
    de: 'Fett',
    ar: 'الدهون'
  },
  'allergens': {
    en: 'Allergens',
    de: 'Allergene',
    ar: 'مسببات الحساسية'
  },
  'reviews': {
    en: 'Reviews',
    de: 'Bewertungen',
    ar: 'التقييمات'
  },
  'review': {
    en: 'review',
    de: 'Bewertung',
    ar: 'تقييم'
  },

  // Badges
  'most-ordered': {
    en: 'Most Ordered',
    de: 'Am meisten bestellt',
    ar: 'الأكثر طلباً'
  },
  'chefs-pick': {
    en: 'Chef\'s Pick',
    de: 'Empfehlung des Küchenchefs',
    ar: 'اختيار الشيف'
  },

  // Payment Methods
  'apple_pay': {
    en: 'Apple Pay',
    de: 'Apple Pay',
    ar: 'آبل باي'
  },
  'google_pay': {
    en: 'Google Pay',
    de: 'Google Pay',
    ar: 'جوجل باي'
  },
  'credit_card': {
    en: 'Credit Card',
    de: 'Kreditkarte',
    ar: 'بطاقة ائتمان'
  },
  'debit_card': {
    en: 'Debit Card',
    de: 'Debitkarte',
    ar: 'بطاقة الخصم'
  },
  'cash': {
    en: 'Cash',
    de: 'Bargeld',
    ar: 'نقداً'
  },

  // Order Tracking
  'track_order_status': {
    en: 'Track Order Status',
    de: 'Bestellstatus verfolgen',
    ar: 'تتبع حالة الطلب'
  },
  'your_order_being_prepared': {
    en: 'Your order is being prepared',
    de: 'Ihre Bestellung wird vorbereitet',
    ar: 'يتم تحضير طلبك'
  },
  'orders_in_progress': {
    en: 'orders in progress',
    de: 'Bestellungen in Bearbeitung',
    ar: 'طلبات قيد التنفيذ'
  },
  'active_orders': {
    en: 'Active Orders',
    de: 'Aktive Bestellungen',
    ar: 'الطلبات النشطة'
  },
  'no_active_orders': {
    en: 'No active orders',
    de: 'Keine aktiven Bestellungen',
    ar: 'لا توجد طلبات نشطة'
  },
  'no_active_orders_desc': {
    en: 'You don\'t have any orders in progress',
    de: 'Sie haben keine Bestellungen in Bearbeitung',
    ar: 'ليس لديك أي طلبات قيد التنفيذ'
  },

  // Split Bill
  'split_the_bill': {
    en: 'Split the Bill',
    de: 'Rechnung teilen',
    ar: 'تقسيم الفاتورة'
  },
  'total_bill_items_only': {
    en: 'Total Bill (items only)',
    de: 'Gesamtrechnung (nur Artikel)',
    ar: 'إجمالي الفاتورة (الأصناف فقط)'
  },
  'people_at_table': {
    en: 'people at the table',
    de: 'Personen am Tisch',
    ar: 'أشخاص على الطاولة'
  },
  'equal_split': {
    en: 'Equal Split',
    de: 'Gleichmäßig aufteilen',
    ar: 'تقسيم متساوي'
  },
  'evenly': {
    en: '50/50 or evenly',
    de: '50/50 oder gleichmäßig',
    ar: '50/50 أو بالتساوي'
  },
  'tick_your_orders': {
    en: 'Tick your orders',
    de: 'Wählen Sie Ihre Bestellungen',
    ar: 'حدد طلباتك'
  },
  'confirm_split': {
    en: 'Confirm Split',
    de: 'Aufteilung bestätigen',
    ar: 'تأكيد التقسيم'
  },
  'cancel': {
    en: 'Cancel',
    de: 'Abbrechen',
    ar: 'إلغاء'
  },
  'how_split': {
    en: 'How would you like to split?',
    de: 'Wie möchten Sie aufteilen?',
    ar: 'كيف تريد التقسيم؟'
  },
  'select_whos_paying': {
    en: 'Select who\'s paying on this device',
    de: 'Wählen Sie aus, wer auf diesem Gerät zahlt',
    ar: 'اختر من يدفع على هذا الجهاز'
  },
  'person': {
    en: 'Person',
    de: 'Person',
    ar: 'شخص'
  },
  'tick_items_instruction': {
    en: 'Tick the items you ordered. If you shared an item, everyone who shared it should tick it - we\'ll split the cost automatically.',
    de: 'Markieren Sie die Artikel, die Sie bestellt haben. Wenn Sie einen Artikel geteilt haben, sollten alle, die ihn geteilt haben, ihn markieren - wir teilen die Kosten automatisch auf.',
    ar: 'حدد الأصناف التي طلبتها. إذا شاركت صنفًا، فيجب على كل من شاركه أن يحدده - سنقسم التكلفة تلقائيًا.'
  },
  'your_items': {
    en: 'Your items',
    de: 'Ihre Artikel',
    ar: 'أصنافك'
  },
  'others_items': {
    en: 'Others\' items',
    de: 'Artikel anderer',
    ar: 'أصناف الآخرين'
  },
  'unclaimed': {
    en: 'Unclaimed',
    de: 'Nicht zugeordnet',
    ar: 'غير محددة'
  },
  'all_orders_tick_yours': {
    en: 'All orders (tick yours):',
    de: 'Alle Bestellungen (wählen Sie Ihre):',
    ar: 'جميع الطلبات (حدد طلباتك):'
  },
  'shared_by': {
    en: 'Shared by',
    de: 'Geteilt von',
    ar: 'مشترك مع'
  },
  'already_claimed': {
    en: 'Already claimed by others',
    de: 'Bereits von anderen beansprucht',
    ar: 'محددة بالفعل من قبل الآخرين'
  },
  'summary': {
    en: 'Summary',
    de: 'Zusammenfassung',
    ar: 'الملخص'
  },
  'total_matches': {
    en: '✓ Total matches!',
    de: '✓ Summe stimmt überein!',
    ar: '✓ الإجمالي متطابق!'
  },
  'total_mismatch': {
    en: '⚠ Total mismatch',
    de: '⚠ Summe stimmt nicht überein',
    ar: '⚠ الإجمالي غير متطابق'
  },
  'items_not_assigned': {
    en: 'Some items haven\'t been assigned yet',
    de: 'Einige Artikel wurden noch nicht zugeordnet',
    ar: 'بعض الأصناف لم يتم تحديدها بعد'
  },
  'assign_all_items': {
    en: 'Please assign all items before continuing',
    de: 'Bitte ordnen Sie alle Artikel zu, bevor Sie fortfahren',
    ar: 'يرجى تحديد جميع الأصناف قبل المتابعة'
  },

  // Payment Page
  'submit_your_order': {
    en: 'Submit your order',
    de: 'Bestellung absenden',
    ar: 'إرسال طلبك'
  },
  'how_would_you_like_to_pay': {
    en: 'How would you like to pay?',
    de: 'Wie möchten Sie bezahlen?',
    ar: 'كيف تريد الدفع؟'
  },
  'pay_now': {
    en: 'Pay now',
    de: 'Jetzt bezahlen',
    ar: 'ادفع الآن'
  },
  'split_bill': {
    en: 'Split bill',
    de: 'Rechnung teilen',
    ar: 'تقسيم الفاتورة'
  },
  'pay_later_cash': {
    en: 'Pay later (Cash)',
    de: 'Später bezahlen (Bar)',
    ar: 'الدفع لاحقاً (نقداً)'
  },
  'back_to_basket': {
    en: 'Back to basket',
    de: 'Zurück zum Warenkorb',
    ar: 'العودة إلى السلة'
  },
  'proceed_to_checkout': {
    en: 'Proceed to checkout',
    de: 'Zur Kasse',
    ar: 'المتابعة للدفع'
  },
  'pending_payment': {
    en: 'Pending Payment',
    de: 'Zahlung ausstehend',
    ar: 'الدفع معلق'
  },
  'payment_pending': {
    en: 'PAYMENT PENDING',
    de: 'ZAHLUNG AUSSTEHEND',
    ar: 'الدفع معلق'
  },
  'order_number': {
    en: 'Order #',
    de: 'Bestellung Nr.',
    ar: 'الطلب رقم'
  },
  'item': {
    en: 'item',
    de: 'Artikel',
    ar: 'صنف'
  },
  'items': {
    en: 'items',
    de: 'Artikel',
    ar: 'أصناف'
  },
  'pay_now_button': {
    en: 'Pay Now',
    de: 'Jetzt bezahlen',
    ar: 'ادفع الآن'
  },
  'view_status': {
    en: 'View Status',
    de: 'Status ansehen',
    ar: 'عرض الحالة'
  },
  'your_basket_is_empty': {
    en: 'Your basket is empty',
    de: 'Ihr Warenkorb ist leer',
    ar: 'سلتك فارغة'
  },
  'your_order': {
    en: 'Your Order',
    de: 'Ihre Bestellung',
    ar: 'طلبك'
  },
  'add_items_from_menu': {
    en: 'Add some items from the menu',
    de: 'Fügen Sie Artikel aus der Speisekarte hinzu',
    ar: 'أضف بعض الأصناف من القائمة'
  },

  // Badge labels
  'hot': {
    en: 'Hot',
    de: 'Beliebt',
    ar: 'ساخن'
  },
  'popular': {
    en: 'Popular',
    de: 'Beliebt',
    ar: 'شائع'
  },
  'new': {
    en: 'New',
    de: 'Neu',
    ar: 'جديد'
  },

  // New translations
  'current_basket': {
    en: 'Current Basket',
    de: 'Aktueller Warenkorb',
    ar: 'السلة الحالية'
  },
  'net_amount': {
    en: 'Net amount (excl. VAT)',
    de: 'Nettobetrag (ohne MwSt.)',
    ar: 'المبلغ الصافي (بدون ضريبة)'
  },
  'vat_included': {
    en: 'VAT',
    de: 'MwSt.',
    ar: 'ضريبة القيمة المضافة'
  },
  'already_included': {
    en: '(already included)',
    de: '(bereits enthalten)',
    ar: '(مشمولة بالفعل)'
  },
  'subtotal_incl_vat': {
    en: 'Subtotal (incl. VAT)',
    de: 'Zwischensumme (inkl. MwSt.)',
    ar: 'المجموع الفرعي (شامل الضريبة)'
  },
  'service_charge': {
    en: 'Service Charge',
    de: 'Servicepauschale',
    ar: 'رسوم الخدمة'
  },
  'history': {
    en: 'History',
    de: 'Verlauf',
    ar: 'التاريخ'
  },
  'choose_payment_method': {
    en: 'Choose payment method',
    de: 'Zahlungsmethode wählen',
    ar: 'اختر طريقة الدفع'
  },
  'card': {
    en: 'Card',
    de: 'Karte',
    ar: 'بطاقة'
  },
  'remaining_to_pay': {
    en: 'Remaining to pay',
    de: 'Noch zu zahlen',
    ar: 'المتبقي للدفع'
  },
  'pending_cash': {
    en: 'Pending (Cash)',
    de: 'Ausstehend (Bar)',
    ar: 'معلق (نقداً)'
  },
  'waiting_cash_collection': {
    en: 'Waiting for cash collection by waiter',
    de: 'Warten auf Bargeldabholung durch Kellner',
    ar: 'في انتظار جمع النقود من قبل النادل'
  },
  'or': {
    en: 'or',
    de: 'oder',
    ar: 'أو'
  },
  'minimum_order_warning': {
    en: 'Minimum order amount is €{amount}',
    de: 'Mindestbestellwert ist €{amount}',
    ar: 'الحد الأدنى لمبلغ الطلب هو €{amount}'
  },
  'maximum_order_warning': {
    en: 'Maximum order amount is €{amount}',
    de: 'Maximaler Bestellwert ist €{amount}',
    ar: 'الحد الأقصى لمبلغ الطلب هو €{amount}'
  },
  'assign_items': {
    en: 'Assign Items',
    de: 'Artikel zuweisen',
    ar: 'تعيين الأصناف'
  },
  'select_person': {
    en: 'Select person to assign items',
    de: 'Person auswählen, um Artikel zuzuweisen',
    ar: 'حدد الشخص لتعيين الأصناف'
  },
  'tick_items_help': {
    en: 'Tick the items you ordered. Shared items will be split automatically.',
    de: 'Markieren Sie die von Ihnen bestellten Artikel. Geteilte Artikel werden automatisch aufgeteilt.',
    ar: 'حدد الأصناف التي طلبتها. سيتم تقسيم الأصناف المشتركة تلقائياً.'
  },
  'all_orders': {
    en: 'All Orders',
    de: 'Alle Bestellungen',
    ar: 'جميع الطلبات'
  },
  'each': {
    en: 'each',
    de: 'jeweils',
    ar: 'لكل'
  },
  'not_all_items_assigned': {
    en: 'Not all items have been assigned yet',
    de: 'Noch nicht alle Artikel wurden zugewiesen',
    ar: 'لم يتم تعيين جميع الأصناف بعد'
  },
  'continue_to_payment': {
    en: 'Continue to Payment',
    de: 'Weiter zur Zahlung',
    ar: 'المتابعة إلى الدفع'
  },
  'split_payment': {
    en: 'Split Payment',
    de: 'Geteilte Zahlung',
    ar: 'دفع مقسم'
  },
  'per_person': {
    en: 'per person',
    de: 'pro Person',
    ar: 'لكل شخص'
  },
  'table': {
    en: 'Table',
    de: 'Tisch',
    ar: 'الطاولة'
  },
  'total_bill': {
    en: 'Total Bill',
    de: 'Gesamtrechnung',
    ar: 'إجمالي الفاتورة'
  },
  'close': {
    en: 'Close',
    de: 'Schließen',
    ar: 'إغلاق'
  },
  'add_a_tip': {
    en: 'Add a tip (optional)',
    de: 'Trinkgeld hinzufügen (optional)',
    ar: 'إضافة إكرامية (اختياري)'
  },
  'need_receipt': {
    en: 'Need receipt?',
    de: 'Beleg benötigt?',
    ar: 'هل تحتاج إلى إيصال؟'
  },
  'by_items': {
    en: 'By Items',
    de: 'Nach Artikeln',
    ar: 'حسب الأصناف'
  },
  'each_person_pays': {
    en: 'Each person pays',
    de: 'Jede Person zahlt',
    ar: 'يدفع كل شخص'
  },
  'order_confirmed': {
    en: 'Order confirmed',
    de: 'Bestellung bestätigt',
    ar: 'تم تأكيد الطلب'
  },
  'preparing': {
    en: 'Preparing',
    de: 'Wird vorbereitet',
    ar: 'قيد التحضير'
  },
  'estimated_ready_in': {
    en: 'Estimated ready in',
    de: 'Voraussichtlich fertig in',
    ar: 'الوقت المقدر للجاهزية'
  },
  'minutes': {
    en: 'minutes',
    de: 'Minuten',
    ar: 'دقائق'
  },
  'track_order': {
    en: 'Track order',
    de: 'Bestellung verfolgen',
    ar: 'تتبع الطلب'
  },
  'view_receipt': {
    en: 'View receipt',
    de: 'Beleg ansehen',
    ar: 'عرض الإيصال'
  },
  'go_to_menu': {
    en: 'Go to menu',
    de: 'Zur Speisekarte',
    ar: 'الذهاب إلى القائمة'
  },
  'card_payment': {
    en: 'Card Payment',
    de: 'Kartenzahlung',
    ar: 'الدفع بالبطاقة'
  },
  'enter_card_details': {
    en: 'Enter card details',
    de: 'Kartendaten eingeben',
    ar: 'أدخل تفاصيل البطاقة'
  },
  'card_number': {
    en: 'Card number',
    de: 'Kartennummer',
    ar: 'رقم البطاقة'
  },
  'expiry_date': {
    en: 'Expiry date',
    de: 'Ablaufdatum',
    ar: 'تاريخ الانتهاء'
  },
  'cvv': {
    en: 'CVV',
    de: 'CVV',
    ar: 'رمز CVV'
  },
  'cardholder_name': {
    en: 'Cardholder name',
    de: 'Karteninhaber',
    ar: 'اسم حامل البطاقة'
  },
  'pay_amount': {
    en: 'Pay €{amount}',
    de: '€{amount} bezahlen',
    ar: 'ادفع €{amount}'
  },
  'back': {
    en: 'Back',
    de: 'Zurück',
    ar: 'رجوع'
  },
  'continue': {
    en: 'Continue',
    de: 'Weiter',
    ar: 'متابعة'
  },
  'custom': {
    en: 'Custom',
    de: 'Individuell',
    ar: 'مخصص'
  },
  'pick_what_you_ordered': {
    en: 'Pick what you ordered',
    de: 'Wählen Sie, was Sie bestellt haben',
    ar: 'اختر ما طلبته'
  },
  'order_progress': {
    en: 'Order Progress',
    de: 'Bestellfortschritt',
    ar: 'تقدم الطلب'
  },
  'hide_timeline': {
    en: 'Hide Timeline',
    de: 'Zeitachse ausblenden',
    ar: 'إخفاء المخطط الزمني'
  },
  'show_timeline': {
    en: 'Show Timeline',
    de: 'Zeitachse anzeigen',
    ar: 'عرض المخطط الزمني'
  },
  'order_timeline': {
    en: 'Order Timeline',
    de: 'Bestell-Zeitachse',
    ar: 'المخطط الزمني للطلب'
  },
  'received': {
    en: 'Received',
    de: 'Empfangen',
    ar: 'تم الاستلام'
  },
  'in_kitchen': {
    en: 'In kitchen',
    de: 'In der Küche',
    ar: 'في المطبخ'
  },
  'ready': {
    en: 'Ready',
    de: 'Fertig',
    ar: 'جاهز'
  },
  'on_its_way': {
    en: 'On its way',
    de: 'Unterwegs',
    ar: 'في الطريق'
  },
  'served': {
    en: 'Served',
    de: 'Serviert',
    ar: 'تم التقديم'
  },
  'order_items': {
    en: 'Order Items',
    de: 'Bestellte Artikel',
    ar: 'أصناف الطلب'
  },
  'tax_fee_breakdown': {
    en: 'Tax & Fee Breakdown',
    de: 'Steuer- & Gebührenaufschlüsselung',
    ar: 'تفاصيل الضرائب والرسوم'
  },
  'net_amount_food_beverages': {
    en: 'Net amount (food & beverages)',
    de: 'Nettobetrag (Speisen & Getränke)',
    ar: 'المبلغ الصافي (الطعام والمشروبات)'
  },
  'service_fee_percentage': {
    en: 'Service fee ({percentage}%)',
    de: 'Servicegebühr ({percentage}%)',
    ar: 'رسوم الخدمة ({percentage}٪)'
  },
  'vat_percentage': {
    en: 'VAT ({percentage}%)',
    de: 'MwSt. ({percentage}%)',
    ar: 'ضريبة القيمة المضافة ({percentage}٪)'
  },
  'subtotal_inc_vat_fees': {
    en: 'Subtotal (inc. VAT & fees)',
    de: 'Zwischensumme (inkl. MwSt. & Gebühren)',
    ar: 'المجموع الفرعي (شامل الضريبة والرسوم)'
  },
  'tip_gratuity': {
    en: 'Tip (gratuity)',
    de: 'Trinkgeld',
    ar: 'إكرامية'
  },
  'total_amount': {
    en: 'Total Amount',
    de: 'Gesamtbetrag',
    ar: 'المبلغ الإجمالي'
  },
  'call_waiter': {
    en: 'Call Waiter',
    de: 'Kellner rufen',
    ar: 'استدعاء النادل'
  },
  'order_more': {
    en: 'Order More',
    de: 'Mehr bestellen',
    ar: 'اطلب المزيد'
  },
  'receipt': {
    en: 'Receipt',
    de: 'Beleg',
    ar: 'إيصال'
  },
  'order_received': {
    en: 'Order Received',
    de: 'Bestellung eingegangen',
    ar: 'تم استلام الطلب'
  },
  'view_details': {
    en: 'View Details',
    de: 'Details ansehen',
    ar: 'عرض التفاصيل'
  },
  'orders_this_month': {
    en: 'orders this month',
    de: 'Bestellungen diesen Monat',
    ar: 'طلبات هذا الشهر'
  },
  'nutritional_information': {
    en: 'Nutritional Information',
    de: 'Nährwertangaben',
    ar: 'المعلومات الغذائية'
  },
  'customer_reviews': {
    en: 'Customer Reviews',
    de: 'Kundenbewertungen',
    ar: 'تقييمات العملاء'
  },
  'amazing': {
    en: 'amazing',
    de: 'fantastisch',
    ar: 'رائع'
  },
  'add_to_order_price': {
    en: 'Add to order • €{price}',
    de: 'Zur Bestellung hinzufügen • €{price}',
    ar: 'أضف إلى الطلب • €{price}'
  },
  'write_review': {
    en: 'Write a Review',
    de: 'Bewertung schreiben',
    ar: 'كتابة تقييم'
  },
  'settle_with_waiter': {
    en: 'Please settle with waiter or pay now.',
    de: 'Bitte zahlen Sie beim Kellner oder jetzt.',
    ar: 'يرجى الدفع مع النادل أو الدفع الآن.'
  },
  'more': {
    en: 'more',
    de: 'weitere',
    ar: 'المزيد'
  },
  'just_now': {
    en: 'Just now',
    de: 'Gerade eben',
    ar: 'للتو'
  },
  
  // New split payment translations
  'paid': {
    en: 'Paid',
    de: 'Bezahlt',
    ar: 'مدفوع'
  },
  'pending': {
    en: 'Pending',
    de: 'Ausstehend',
    ar: 'في الانتظار'
  },
  'tip': {
    en: 'Tip',
    de: 'Trinkgeld',
    ar: 'إكرامية'
  },
  'balance': {
    en: 'Balance',
    de: 'Saldo',
    ar: 'الرصيد'
  },
  'custom_split': {
    en: 'Custom Split',
    de: 'Individuelle Aufteilung',
    ar: 'تقسيم مخصص'
  },
  'custom_amounts': {
    en: 'Custom amounts per person',
    de: 'Individuelle Beträge pro Person',
    ar: 'مبالغ مخصصة لكل شخص'
  },
  'flexible': {
    en: 'Flexible',
    de: 'Flexibel',
    ar: 'مرن'
  },
  'individual_payments': {
    en: 'Individual Payments',
    de: 'Einzelzahlungen',
    ar: 'الدفعات الفردية'
  },
  'overpay_becomes_tip': {
    en: 'Overpayment becomes tip',
    de: 'Überzahlung wird Trinkgeld',
    ar: 'الدفع الزائد يصبح إكرامية'
  },
  'show_qr': {
    en: 'Show QR',
    de: 'QR anzeigen',
    ar: 'عرض QR'
  },
  'mark_cash': {
    en: 'Mark Cash',
    de: 'Bar markieren',
    ar: 'تحديد نقدي'
  },
  'payment_options_help': {
    en: 'Pay directly, generate QR for another phone, or mark as cash',
    de: 'Direkt bezahlen, QR für anderes Telefon generieren oder als bar markieren',
    ar: 'ادفع مباشرة، أو أنشئ QR لهاتف آخر، أو حدد نقدي'
  },
  'paid_at': {
    en: 'Paid at',
    de: 'Bezahlt um',
    ar: 'تم الدفع في'
  },
  'underpayment_warning': {
    en: 'Total assigned amounts are less than the bill. Please adjust.',
    de: 'Die zugewiesenen Beträge sind geringer als die Rechnung. Bitte anpassen.',
    ar: 'المبالغ المخصصة أقل من الفاتورة. يرجى التعديل.'
  },
  'missing': {
    en: 'Missing',
    de: 'Fehlend',
    ar: 'مفقود'
  },
  'overpayment_tip': {
    en: 'The total assigned is more than the bill. The extra amount will be added as a tip.',
    de: 'Die zugewiesene Summe ist höher als die Rechnung. Der Überschuss wird als Trinkgeld hinzugefügt.',
    ar: 'المبلغ المخصص أكثر من الفاتورة. سيتم إضافة المبلغ الإضافي كإكرامية.'
  },
  'tip_amount': {
    en: 'Tip',
    de: 'Trinkgeld',
    ar: 'إكرامية'
  },
  'complete_payment': {
    en: 'Complete Payment',
    de: 'Zahlung abschließen',
    ar: 'إتمام الدفع'
  },
  'waiting_for_payments': {
    en: 'Waiting for Payments...',
    de: 'Warten auf Zahlungen...',
    ar: 'في انتظار الدفعات...'
  },
  'scan_to_pay': {
    en: 'Scan to Pay',
    de: 'Scannen zum Bezahlen',
    ar: 'امسح للدفع'
  },
  'should_pay': {
    en: 'should pay',
    de: 'soll bezahlen',
    ar: 'يجب أن يدفع'
  },
  'qr_instructions': {
    en: 'The other person should scan this QR code with their phone camera or QR scanner app to complete their payment.',
    de: 'Die andere Person sollte diesen QR-Code mit ihrer Handykamera oder QR-Scanner-App scannen, um die Zahlung abzuschließen.',
    ar: 'يجب على الشخص الآخر مسح رمز QR هذا باستخدام كاميرا هاتفه أو تطبيق ماسح QR لإتمام الدفع.'
  },
  'pay_your_split': {
    en: 'Pay Your Split',
    de: 'Deinen Anteil bezahlen',
    ar: 'ادفع حصتك'
  },
  'your_share': {
    en: 'Your Share',
    de: 'Dein Anteil',
    ar: 'حصتك'
  },
  'for_order': {
    en: 'For Order',
    de: 'Für Bestellung',
    ar: 'للطلب'
  },
  'pay_with_card': {
    en: 'Pay with Card',
    de: 'Mit Karte bezahlen',
    ar: 'الدفع بالبطاقة'
  },
  'credit_debit_card': {
    en: 'Credit or Debit Card',
    de: 'Kredit- oder Debitkarte',
    ar: 'بطاقة ائتمان أو خصم'
  },
  'pay_with_cash': {
    en: 'Pay with Cash',
    de: 'Bar bezahlen',
    ar: 'الدفع نقداً'
  },
  'pay_to_waiter': {
    en: 'Pay cash to the waiter',
    de: 'Bar beim Kellner bezahlen',
    ar: 'ادفع نقداً للنادل'
  },
  'payment_info': {
    en: 'Payment Information',
    de: 'Zahlungsinformationen',
    ar: 'معلومات الدفع'
  },
  'secure_payment': {
    en: 'Your payment is secure and encrypted',
    de: 'Ihre Zahlung ist sicher und verschlüsselt',
    ar: 'دفعتك آمنة ومشفرة'
  },
  'split_info': {
    en: 'This is your portion of a split bill',
    de: 'Dies ist Ihr Anteil einer geteilten Rechnung',
    ar: 'هذا هو جزءك من الفاتورة المقسمة'
  },
  'others_pay_separately': {
    en: 'Others will pay their portions separately',
    de: 'Andere zahlen ihre Anteile separat',
    ar: 'سيدفع الآخرون حصصهم بشكل منفصل'
  },
  'processing_payment': {
    en: 'Processing your payment...',
    de: 'Ihre Zahlung wird verarbeitet...',
    ar: 'جارٍ معالجة دفعتك...'
  },
  'payment_successful': {
    en: 'Payment Successful!',
    de: 'Zahlung erfolgreich!',
    ar: 'تمت الدفعة بنجاح!'
  },
  'split_paid': {
    en: 'Your split payment has been processed successfully.',
    de: 'Ihre Teilzahlung wurde erfolgreich verarbeitet.',
    ar: 'تمت معالجة دفعتك المقسمة بنجاح.'
  },
  'amount_paid': {
    en: 'Amount Paid',
    de: 'Gezahlter Betrag',
    ar: 'المبلغ المدفوع'
  },
  'back_to_menu': {
    en: 'Back to Menu',
    de: 'Zurück zum Menü',
    ar: 'العودة إلى القائمة'
  },
  'processing': {
    en: 'Processing...',
    de: 'Verarbeitung...',
    ar: 'جارٍ المعالجة...'
  },
  
  // Business Hours
  'restaurant_closed': {
    en: 'Restaurant Currently Closed',
    de: 'Restaurant derzeit geschlossen',
    it: 'Ristorante attualmente chiuso',
    fr: 'Restaurant actuellement fermé',
    ar: 'المطعم مغلق حالياً',
    tr: 'Restoran şu anda kapalı',
    zh: '餐厅目前已关闭',
    ja: 'レストランは現在閉店中',
    sr: 'Ресторан тренутно затворен',
    cs: 'Restaurace momentálně zavřeno',
    es: 'Restaurante cerrado actualmente'
  },
  'closed_today': {
    en: 'Closed today',
    de: 'Heute geschlossen',
    it: 'Chiuso oggi',
    fr: 'Fermé aujourd\'hui',
    ar: 'مغلق اليوم',
    tr: 'Bugün kapalı',
    zh: '今天关闭',
    ja: '本日休業',
    sr: 'Затворено данас',
    cs: 'Dnes zavřeno',
    es: 'Cerrado hoy'
  },
  'opens_at': {
    en: 'Opens at {time}',
    de: 'Öffnet um {time}',
    it: 'Apre alle {time}',
    fr: 'Ouvre à {time}',
    ar: 'يفتح في {time}',
    tr: '{time} saatinde açılır',
    zh: '{time} 营业',
    ja: '{time} 開店',
    sr: 'Отвара у {time}',
    cs: 'Otevírá v {time}',
    es: 'Abre a las {time}'
  },
  'closes_soon': {
    en: 'Closing soon ({minutes} min)',
    de: 'Schließt bald ({minutes} Min.)',
    it: 'Chiude presto ({minutes} min)',
    fr: 'Ferme bientôt ({minutes} min)',
    ar: 'يغلق قريباً ({minutes} دقيقة)',
    tr: 'Yakında kapanıyor ({minutes} dk)',
    zh: '即将关闭 ({minutes} 分钟)',
    ja: 'まもなく閉店 ({minutes} 分)',
    sr: 'Затвара ускоро ({minutes} мин)',
    cs: 'Brzy zavírá ({minutes} min)',
    es: 'Cierra pronto ({minutes} min)'
  },
  'currently_closed': {
    en: 'We\'re currently closed. Please visit us during our opening hours.',
    de: 'Wir haben derzeit geschlossen. Bitte besuchen Sie uns während unserer Öffnungszeiten.',
    it: 'Siamo attualmente chiusi. Visitaci durante gli orari di apertura.',
    fr: 'Nous sommes actuellement fermés. Veuillez nous rendre visite pendant nos heures d\'ouverture.',
    ar: 'نحن مغلقون حالياً. يرجى زيارتنا خلال ساعات العمل.',
    tr: 'Şu anda kapalıyız. Lütfen çalışma saatlerimizde bizi ziyaret edin.',
    zh: '我们目前已关闭。请在营业时间内访问我们。',
    ja: '現在閉店中です。営業時間中にお越しください。',
    sr: 'Тренутно смо затворени. Молимо посетите нас током радног времена.',
    cs: 'Momentálně máme zavřeno. Prosím navštivte nás během otevírací doby.',
    es: 'Actualmente estamos cerrados. Por favor visítenos durante nuestro horario de apertura.'
  },
  'next_opening': {
    en: 'Next opening: {time}',
    de: 'Nächste Öffnung: {time}',
    it: 'Prossima apertura: {time}',
    fr: 'Prochaine ouverture: {time}',
    ar: 'الافتتاح التالي: {time}',
    tr: 'Sonraki açılış: {time}',
    zh: '下次营业: {time}',
    ja: '次の営業: {time}',
    sr: 'Следеће отварање: {time}',
    cs: 'Další otevření: {time}',
    es: 'Próxima apertura: {time}'
  },
  'browse_menu_only': {
    en: 'You can browse the menu, but ordering is not available at this time.',
    de: 'Sie können die Speisekarte durchsehen, aber Bestellungen sind derzeit nicht möglich.',
    it: 'Puoi sfogliare il menu, ma gli ordini non sono disponibili in questo momento.',
    fr: 'Vous pouvez parcourir le menu, mais les commandes ne sont pas disponibles pour le moment.',
    ar: 'يمكنك تصفح القائمة، لكن الطلب غير متاح في الوقت الحالي.',
    tr: 'Menüye göz atabilirsiniz, ancak sipariş şu anda mevcut değil.',
    zh: '您可以浏览菜单，但目前无法下单。',
    ja: 'メニューは閲覧できますが、現在注文はできません。',
    sr: 'Можете прегледати мени, али наручивање тренутно није доступно.',
    cs: 'Můžete procházet menu, ale objednávání není v tuto chvíli dostupné.',
    es: 'Puedes navegar el menú, pero el pedido no está disponible en este momento.'
  },
  'cannot_order_closed': {
    en: 'Cannot place order while restaurant is closed',
    de: 'Bestellung nicht möglich, während Restaurant geschlossen ist',
    it: 'Impossibile effettuare ordini mentre il ristorante è chiuso',
    fr: 'Impossible de passer commande pendant la fermeture du restaurant',
    ar: 'لا يمكن تقديم الطلب أثناء إغلاق المطعم',
    tr: 'Restoran kapalıyken sipariş veremezsiniz',
    zh: '餐厅关闭时无法下单',
    ja: 'レストラン閉店中は注文できません',
    sr: 'Није могуће наручити док је ресторан затворен',
    cs: 'Nelze objednat, když je restaurace zavřená',
    es: 'No se puede realizar el pedido mientras el restaurante está cerrado'
  },
  'open_now': {
    en: 'Open now',
    de: 'Jetzt geöffnet',
    it: 'Aperto ora',
    fr: 'Ouvert maintenant',
    ar: 'مفتوح الآن',
    tr: 'Şu anda açık',
    zh: '现在营业',
    ja: '営業中',
    sr: 'Отворено сада',
    cs: 'Nyní otevřeno',
    es: 'Abierto ahora'
  },
  'today_at': {
    en: 'Today at {time}',
    de: 'Heute um {time}',
    it: 'Oggi alle {time}',
    fr: 'Aujourd\'hui à {time}',
    ar: 'اليوم في {time}',
    tr: 'Bugün saat {time}',
    zh: '今天 {time}',
    ja: '今日 {time}',
    sr: 'Данас у {time}',
    cs: 'Dnes v {time}',
    es: 'Hoy a las {time}'
  },
  'tomorrow_at': {
    en: 'Tomorrow at {time}',
    de: 'Morgen um {time}',
    it: 'Domani alle {time}',
    fr: 'Demain à {time}',
    ar: 'غداً في {time}',
    tr: 'Yarın saat {time}',
    zh: '明天 {time}',
    ja: '明日 {time}',
    sr: 'Сутра у {time}',
    cs: 'Zítra v {time}',
    es: 'Mañana a las {time}'
  },
  'waiter': {
    en: 'Waiter',
    de: 'Kellner',
    it: 'Cameriere',
    fr: 'Serveur',
    ar: 'النادل',
    tr: 'Garson',
    zh: '服务员',
    ja: 'ウェイター',
    sr: 'Конобар',
    cs: 'Číšník',
    es: 'Camarero'
  },
  
  // Loyalty Points
  'loyalty_rewards': {
    en: 'Loyalty Rewards',
    de: 'Treuepunkte',
    ar: 'مكافآت الولاء'
  },
  'earn_points_with_this_order': {
    en: 'Earn points with this order!',
    de: 'Verdienen Sie Punkte mit dieser Bestellung!',
    ar: 'اكسب نقاطاً مع هذا الطلب!'
  },
  'login_to_earn_specific_points': {
    en: 'Log in before payment to earn {points} loyalty points from this €{amount} order',
    de: 'Melden Sie sich vor der Zahlung an, um {points} Treuepunkte von dieser €{amount} Bestellung zu verdienen',
    ar: 'سجل الدخول قبل الدفع لكسب {points} نقطة ولاء من هذا الطلب بقيمة €{amount}'
  },
  'point_per_euro': {
    en: 'point per euro',
    de: 'Punkt pro Euro',
    ar: 'نقطة لكل يورو'
  },
  'youll_earn_points': {
    en: "You'll earn {points} points from this order!",
    de: 'Sie werden {points} Punkte von dieser Bestellung verdienen!',
    ar: 'ستكسب {points} نقطة من هذا الطلب!'
  },
  'points_needed_to_redeem': {
    en: 'You need {points} more points to redeem',
    de: 'Sie benötigen noch {points} Punkte zum Einlösen',
    ar: 'تحتاج إلى {points} نقطة إضافية للاسترداد'
  }
};

// Helper function to get translation
export function getTranslation(key: string, language: LanguageCode, fallback?: string): string {
  return UI_TRANSLATIONS[key]?.[language] || fallback || UI_TRANSLATIONS[key]?.['en'] || key;
}

// Helper to get translated field from an object
export function getTranslatedField(obj: any, field: string, language: string): string {
  if (!obj) return '';
  
  // If object has translations
  if (obj.translations && obj.translations[language] && obj.translations[language][field]) {
    return obj.translations[language][field];
  }
  
  // Fallback to English (default field)
  return obj[field] || '';
}

// Helper to get translated modifier name by matching it in the menu item's modifier arrays
export function getTranslatedModifier(modifierName: string, modifierType: string, menuItem: any, language: string): string {
  if (!menuItem || !menuItem.translations || !menuItem.translations[language]) {
    return modifierName;
  }
  
  const translations = menuItem.translations[language];
  
  // Find the index of the modifier in the English array
  let englishArray: string[] = [];
  let translatedArray: string[] = [];
  
  if (modifierType === 'paid-addon' && menuItem.paidAddons) {
    englishArray = menuItem.paidAddons.map((a: any) => typeof a === 'string' ? a : a.name);
    translatedArray = translations.paidAddons || [];
  } else if (modifierType === 'free-addon' && menuItem.freeAddons) {
    englishArray = menuItem.freeAddons;
    translatedArray = translations.freeAddons || [];
  } else if (modifierType === 'removal' && menuItem.removableItems) {
    englishArray = menuItem.removableItems;
    translatedArray = translations.removableItems || [];
  }
  
  // Find the index and return the translated name
  const index = englishArray.findIndex(name => name === modifierName);
  if (index !== -1 && translatedArray[index]) {
    return translatedArray[index];
  }
  
  return modifierName; // Fallback to English
}