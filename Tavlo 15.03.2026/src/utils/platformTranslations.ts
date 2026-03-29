// Platform translations for Vendor Dashboard and Admin Panel
// Supports all 11 languages: English, Deutsch, Italian, French, Arabic, Turkish, Chinese, Japanese, Serbian, Czech, Spanish

export type PlatformLanguageCode =
  | "en"
  | "de"
  | "it"
  | "fr"
  | "ar"
  | "tr"
  | "zh"
  | "ja"
  | "sr"
  | "cs"
  | "es";

export const PLATFORM_TRANSLATIONS: Record<
  string,
  Partial<Record<PlatformLanguageCode, string>>
> = {
  // ========================================
  // VENDOR DASHBOARD - SIDEBAR & NAVIGATION
  // ========================================
  dashboard: {
    en: "Dashboard",
    de: "Dashboard",
    ar: "لوحة التحكم",
  },
  overview: {
    en: "Overview",
    de: "Übersicht",
    ar: "نظرة عامة",
  },
  orders: {
    en: "Orders",
    de: "Bestellungen",
    ar: "الطلبات",
  },
  reservations: {
    en: "Reservations",
    de: "Reservierungen",
    ar: "الحجوزات",
  },
  menu: {
    en: "Menu",
    de: "Speisekarte",
    ar: "القائمة",
  },
  qr_codes: {
    en: "QR Codes",
    de: "QR-Codes",
    ar: "رموز QR",
  },
  loyalty: {
    en: "Loyalty",
    de: "Treueprogramm",
    ar: "برنامج الولاء",
  },
  analytics: {
    en: "Analytics",
    de: "Analysen",
    ar: "التحليلات",
  },
  reviews: {
    en: "Reviews",
    de: "Bewertungen",
    ar: "التقييمات",
  },
  settings: {
    en: "Settings",
    de: "Einstellungen",
    ar: "الإعدادات",
  },

  // ========================================
  // ADMIN DASHBOARD - SIDEBAR
  // ========================================
  admin_dashboard: {
    en: "Admin Dashboard",
    de: "Admin-Dashboard",
    ar: "لوحة تحكم المسؤول",
  },
  vendors: {
    en: "Vendors",
    de: "Anbieter",
    ar: "الموردون",
  },
  customers: {
    en: "Customers",
    de: "Kunden",
    ar: "العملاء",
  },
  platform_settings: {
    en: "Platform Settings",
    de: "Plattform-Einstellungen",
    ar: "إعدادات المنصة",
  },
  reports: {
    en: "Reports",
    de: "Berichte",
    ar: "التقارير",
  },

  // ========================================
  // OVERVIEW / DASHBOARD STATS
  // ========================================
  today_revenue: {
    en: "Today's Revenue",
    de: "Heutiger Umsatz",
    ar: "إيرادات اليوم",
  },
  total_orders: {
    en: "Total Orders",
    de: "Gesamtbestellungen",
    ar: "إجمالي الطلبات",
  },
  active_tables: {
    en: "Active Tables",
    de: "Aktive Tische",
    ar: "الطاولات النشطة",
  },
  pending_orders: {
    en: "Pending Orders",
    de: "Ausstehende Bestellungen",
    ar: "الطلبات المعلقة",
  },
  completed_orders: {
    en: "Completed Orders",
    de: "Abgeschlossene Bestellungen",
    ar: "الطلبات المكتملة",
  },
  average_order_value: {
    en: "Average Order Value",
    de: "Durchschnittlicher Bestellwert",
    ar: "متوسط قيمة الطلب",
  },
  customer_satisfaction: {
    en: "Customer Satisfaction",
    de: "Kundenzufriedenheit",
    ar: "رضا العملاء",
  },
  revenue_trend: {
    en: "Revenue Trend",
    de: "Umsatztrend",
    ar: "اتجاه الإيرادات",
  },
  orders_trend: {
    en: "Orders Trend",
    de: "Bestellungstrend",
    ar: "اتجاه الطلبات",
  },

  // ========================================
  // ORDERS MANAGEMENT
  // ========================================
  orders_management: {
    en: "Orders Management",
    de: "Bestellungsverwaltung",
    ar: "إدارة الطلبات",
  },
  new_orders: {
    en: "New Orders",
    de: "Neue Bestellungen",
    ar: "طلبات جديدة",
  },
  in_progress: {
    en: "In Progress",
    de: "In Bearbeitung",
    ar: "قيد التنفيذ",
  },
  ready: {
    en: "Ready",
    de: "Bereit",
    ar: "جاهز",
  },
  delivered: {
    en: "Delivered",
    de: "Geliefert",
    ar: "تم التوصيل",
  },
  cancelled: {
    en: "Cancelled",
    de: "Storniert",
    ar: "ملغى",
  },
  order_status: {
    en: "Order Status",
    de: "Bestellstatus",
    ar: "حالة الطلب",
  },
  table_number: {
    en: "Table Number",
    de: "Tischnummer",
    ar: "رقم الطاولة",
  },
  order_time: {
    en: "Order Time",
    de: "Bestellzeit",
    ar: "وقت الطلب",
  },
  order_amount: {
    en: "Order Amount",
    de: "Bestellbetrag",
    ar: "مبلغ الطلب",
  },
  accept_order: {
    en: "Accept Order",
    de: "Bestellung annehmen",
    ar: "قبول الطلب",
  },
  reject_order: {
    en: "Reject Order",
    de: "Bestellung ablehnen",
    ar: "رفض الطلب",
  },
  mark_as_ready: {
    en: "Mark as Ready",
    de: "Als bereit markieren",
    ar: "وضع علامة كجاهز",
  },
  mark_as_delivered: {
    en: "Mark as Delivered",
    de: "Als geliefert markieren",
    ar: "وضع علامة كمُسلّم",
  },
  view_order_details: {
    en: "View Order Details",
    de: "Bestelldetails anzeigen",
    ar: "عرض تفاصيل الطلب",
  },
  print_receipt: {
    en: "Print Receipt",
    de: "Beleg drucken",
    ar: "طباعة الإيصال",
  },
  print_kitchen_ticket: {
    en: "Print Kitchen Ticket",
    de: "Küchenbeleg drucken",
    ar: "طباعة تذكرة المطبخ",
  },

  // ========================================
  // RESERVATIONS
  // ========================================
  reservations_management: {
    en: "Reservations Management",
    de: "Reservierungsverwaltung",
    ar: "إدارة الحجوزات",
  },
  upcoming_reservations: {
    en: "Upcoming Reservations",
    de: "Bevorstehende Reservierungen",
    ar: "الحجوزات القادمة",
  },
  past_reservations: {
    en: "Past Reservations",
    de: "Vergangene Reservierungen",
    ar: "الحجوزات السابقة",
  },
  reservation_date: {
    en: "Reservation Date",
    de: "Reservierungsdatum",
    ar: "تاريخ الحجز",
  },
  reservation_time: {
    en: "Reservation Time",
    de: "Reservierungszeit",
    ar: "وقت الحجز",
  },
  party_size: {
    en: "Party Size",
    de: "Gruppengröße",
    ar: "عدد الأشخاص",
  },
  customer_name: {
    en: "Customer Name",
    de: "Kundenname",
    ar: "اسم العميل",
  },
  customer_phone: {
    en: "Customer Phone",
    de: "Kundentelefon",
    ar: "هاتف العميل",
  },
  confirm_reservation: {
    en: "Confirm Reservation",
    de: "Reservierung bestätigen",
    ar: "تأكيد الحجز",
  },
  cancel_reservation: {
    en: "Cancel Reservation",
    de: "Reservierung stornieren",
    ar: "إلغاء الحجز",
  },
  special_requests: {
    en: "Special Requests",
    de: "Sonderwünsche",
    ar: "طلبات خاصة",
  },

  // ========================================
  // MENU MANAGEMENT
  // ========================================
  menu_management: {
    en: "Menu Management",
    de: "Menüverwaltung",
    ar: "إدارة القائمة",
  },
  add_new_item: {
    en: "Add New Item",
    de: "Neues Gericht hinzufügen",
    ar: "إضافة صنف جديد",
  },
  edit_item: {
    en: "Edit Item",
    de: "Gericht bearbeiten",
    ar: "تعديل الصنف",
  },
  delete_item: {
    en: "Delete Item",
    de: "Gericht löschen",
    ar: "حذف الصنف",
  },
  item_name: {
    en: "Item Name",
    de: "Gerichtname",
    ar: "اسم الصنف",
  },
  description: {
    en: "Description",
    de: "Beschreibung",
    ar: "الوصف",
  },
  price: {
    en: "Price",
    de: "Preis",
    ar: "السعر",
  },
  category: {
    en: "Category",
    de: "Kategorie",
    ar: "الفئة",
  },
  available: {
    en: "Available",
    de: "Verfügbar",
    ar: "متاح",
  },
  out_of_stock: {
    en: "Out of Stock",
    de: "Nicht vorrätig",
    ar: "غير متوفر",
  },
  mark_unavailable: {
    en: "Mark Unavailable",
    de: "Als nicht verfügbar markieren",
    ar: "وضع علامة كغير متاح",
  },
  mark_available: {
    en: "Mark Available",
    de: "Als verfügbar markieren",
    ar: "وضع علامة كمتاح",
  },
  upload_image: {
    en: "Upload Image",
    de: "Bild hochladen",
    ar: "تحميل صورة",
  },
  dietary_info: {
    en: "Dietary Info",
    de: "Ernährungsinfo",
    ar: "معلومات النظام الغذائي",
  },
  allergens: {
    en: "Allergens",
    de: "Allergene",
    ar: "مسببات الحساسية",
  },
  modifiers: {
    en: "Modifiers",
    de: "Modifikatoren",
    ar: "التعديلات",
  },
  add_modifier_group: {
    en: "Add Modifier Group",
    de: "Modifikatorgruppe hinzufügen",
    ar: "إضافة مجموعة تعديلات",
  },

  // ========================================
  // QR CODES
  // ========================================
  qr_code_management: {
    en: "QR Code Management",
    de: "QR-Code-Verwaltung",
    ar: "إدارة رموز QR",
  },
  generate_qr_codes: {
    en: "Generate QR Codes",
    de: "QR-Codes generieren",
    ar: "إنشاء رموز QR",
  },
  download_qr: {
    en: "Download QR",
    de: "QR herunterladen",
    ar: "تنزيل QR",
  },
  print_qr: {
    en: "Print QR",
    de: "QR drucken",
    ar: "طباعة QR",
  },
  table_qr_codes: {
    en: "Table QR Codes",
    de: "Tisch-QR-Codes",
    ar: "رموز QR للطاولات",
  },
  download_all: {
    en: "Download All",
    de: "Alle herunterladen",
    ar: "تنزيل الكل",
  },
  bulk_print: {
    en: "Bulk Print",
    de: "Massenausdruck",
    ar: "طباعة جماعية",
  },

  // ========================================
  // LOYALTY PROGRAM
  // ========================================
  loyalty_program: {
    en: "Loyalty Program",
    de: "Treueprogramm",
    ar: "برنامج الولاء",
  },
  points_settings: {
    en: "Points Settings",
    de: "Punkte-Einstellungen",
    ar: "إعدادات النقاط",
  },
  rewards: {
    en: "Rewards",
    de: "Belohnungen",
    ar: "المكافآت",
  },
  points_per_euro: {
    en: "Points per Euro",
    de: "Punkte pro Euro",
    ar: "نقاط لكل يورو",
  },
  add_reward: {
    en: "Add Reward",
    de: "Belohnung hinzufügen",
    ar: "إضافة مكافأة",
  },
  reward_name: {
    en: "Reward Name",
    de: "Belohnungsname",
    ar: "اسم المكافأة",
  },
  points_required: {
    en: "Points Required",
    de: "Erforderliche Punkte",
    ar: "النقاط المطلوبة",
  },
  total_members: {
    en: "Total Members",
    de: "Gesamtmitglieder",
    ar: "إجمالي الأعضاء",
  },
  active_members: {
    en: "Active Members",
    de: "Aktive Mitglieder",
    ar: "الأعضاء النشطون",
  },
  points_distributed: {
    en: "Points Distributed",
    de: "Verteilte Punkte",
    ar: "النقاط الموزعة",
  },

  // ========================================
  // ANALYTICS
  // ========================================
  analytics_dashboard: {
    en: "Analytics Dashboard",
    de: "Analyse-Dashboard",
    ar: "لوحة التحليلات",
  },
  customer_analytics: {
    en: "Customer Analytics",
    de: "Kundenanalysen",
    ar: "تحليلات العملاء",
  },
  sales_analytics: {
    en: "Sales Analytics",
    de: "Verkaufsanalysen",
    ar: "تحليلات المبيعات",
  },
  top_customers: {
    en: "Top Customers",
    de: "Top-Kunden",
    ar: "أفضل العملاء",
  },
  top_dishes: {
    en: "Top Dishes",
    de: "Top-Gerichte",
    ar: "الأطباق الأكثر طلباً",
  },
  peak_hours: {
    en: "Peak Hours",
    de: "Stoßzeiten",
    ar: "ساعات الذروة",
  },
  daily_revenue: {
    en: "Daily Revenue",
    de: "Täglicher Umsatz",
    ar: "الإيرادات اليومية",
  },
  weekly_revenue: {
    en: "Weekly Revenue",
    de: "Wöchentlicher Umsatz",
    ar: "الإيرادات الأسبوعية",
  },
  monthly_revenue: {
    en: "Monthly Revenue",
    de: "Monatlicher Umsatz",
    ar: "الإيرادات الشهرية",
  },
  total_spent: {
    en: "Total Spent",
    de: "Gesamtausgaben",
    ar: "إجمالي الإنفاق",
  },
  visit_frequency: {
    en: "Visit Frequency",
    de: "Besuchshäufigkeit",
    ar: "تكرار الزيارات",
  },
  last_visit: {
    en: "Last Visit",
    de: "Letzter Besuch",
    ar: "آخر زيارة",
  },
  insights: {
    en: "Insights",
    de: "Erkenntnisse",
    ar: "الرؤى",
  },
  ai_insights: {
    en: "AI Insights",
    de: "KI-Erkenntnisse",
    ar: "رؤى الذكاء الاصطناعي",
  },

  // ========================================
  // REVIEWS MANAGEMENT
  // ========================================
  reviews_management: {
    en: "Reviews Management",
    de: "Bewertungsverwaltung",
    ar: "إدارة التقييمات",
  },
  all_reviews: {
    en: "All Reviews",
    de: "Alle Bewertungen",
    ar: "جميع التقييمات",
  },
  positive_reviews: {
    en: "Positive Reviews",
    de: "Positive Bewertungen",
    ar: "التقييمات الإيجابية",
  },
  negative_reviews: {
    en: "Negative Reviews",
    de: "Negative Bewertungen",
    ar: "التقييمات السلبية",
  },
  pending_response: {
    en: "Pending Response",
    de: "Antwort ausstehend",
    ar: "في انتظار الرد",
  },
  respond_to_review: {
    en: "Respond to Review",
    de: "Auf Bewertung antworten",
    ar: "الرد على التقييم",
  },
  your_response: {
    en: "Your Response",
    de: "Ihre Antwort",
    ar: "ردك",
  },
  submit_response: {
    en: "Submit Response",
    de: "Antwort absenden",
    ar: "إرسال الرد",
  },
  ai_review_summary: {
    en: "AI Review Summary",
    de: "KI-Bewertungszusammenfassung",
    ar: "ملخص التقييمات بالذكاء الاصطناعي",
  },
  sentiment_analysis: {
    en: "Sentiment Analysis",
    de: "Stimmungsanalyse",
    ar: "تحليل المشاعر",
  },
  positive: {
    en: "POSITIVE",
    de: "POSITIV",
    ar: "إيجابي",
  },
  negative: {
    en: "NEGATIVE",
    de: "NEGATIV",
    ar: "سلبي",
  },
  mixed: {
    en: "MIXED",
    de: "GEMISCHT",
    ar: "مختلط",
  },
  what_people_say: {
    en: "What People Say",
    de: "Was die Leute sagen",
    ar: "ما يقوله الناس",
  },
  common_praises: {
    en: "Common Praises",
    de: "Häufiges Lob",
    ar: "الثناءات الشائعة",
  },
  areas_for_improvement: {
    en: "Areas for Improvement",
    de: "Verbesserungsbereiche",
    ar: "مجالات التحسين",
  },
  verified_order: {
    en: "Verified Order",
    de: "Verifizierte Bestellung",
    ar: "طلب موثق",
  },
  helpful: {
    en: "Helpful",
    de: "Hilfreich",
    ar: "مفيد",
  },
  mark_as_helpful: {
    en: "Mark as Helpful",
    de: "Als hilfreich markieren",
    ar: "وضع علامة كمفيد",
  },
  report_review: {
    en: "Report Review",
    de: "Bewertung melden",
    ar: "الإبلاغ عن التقييم",
  },

  // ========================================
  // SETTINGS
  // ========================================
  restaurant_settings: {
    en: "Restaurant Settings",
    de: "Restaurant-Einstellungen",
    ar: "إعدادات المطعم",
  },
  general_settings: {
    en: "General Settings",
    de: "Allgemeine Einstellungen",
    ar: "الإعدادات العامة",
  },
  restaurant_name: {
    en: "Restaurant Name",
    de: "Restaurantname",
    ar: "اسم المطعم",
  },
  restaurant_description: {
    en: "Restaurant Description",
    de: "Restaurantbeschreibung",
    ar: "وصف المطعم",
  },
  address: {
    en: "Address",
    de: "Adresse",
    ar: "العنوان",
  },
  phone_number: {
    en: "Phone Number",
    de: "Telefonnummer",
    ar: "رقم الهاتف",
  },
  email_address: {
    en: "Email Address",
    de: "E-Mail-Adresse",
    ar: "البريد الإلكتروني",
  },
  website: {
    en: "Website",
    de: "Webseite",
    ar: "الموقع الإلكتروني",
  },
  opening_hours: {
    en: "Opening Hours",
    de: "Öffnungszeiten",
    ar: "ساعات العمل",
  },
  payment_methods: {
    en: "Payment Methods",
    de: "Zahlungsmethoden",
    ar: "طرق الدفع",
  },
  service_fee: {
    en: "Service Fee",
    de: "Servicegebühr",
    ar: "رسوم الخدمة",
  },
  tax_settings: {
    en: "Tax Settings",
    de: "Steuereinstellungen",
    ar: "إعدادات الضريبة",
  },
  vat_number: {
    en: "VAT Number",
    de: "USt-IdNr.",
    ar: "رقم ضريبة القيمة المضافة",
  },
  vat_rate: {
    en: "VAT Rate",
    de: "MwSt.-Satz",
    ar: "نسبة ضريبة القيمة المضافة",
  },
  currency: {
    en: "Currency",
    de: "Währung",
    ar: "العملة",
  },
  language_settings: {
    en: "Language Settings",
    de: "Spracheinstellungen",
    ar: "إعدادات اللغة",
  },
  default_language: {
    en: "Default Language",
    de: "Standardsprache",
    ar: "اللغة الافتراضية",
  },
  supported_languages: {
    en: "Supported Languages",
    de: "Unterstützte Sprachen",
    ar: "اللغات المدعومة",
  },
  notification_settings: {
    en: "Notification Settings",
    de: "Benachrichtigungseinstellungen",
    ar: "إعدادات الإشعارات",
  },
  email_notifications: {
    en: "Email Notifications",
    de: "E-Mail-Benachrichtigungen",
    ar: "إشعارات البريد الإلكتروني",
  },
  push_notifications: {
    en: "Push Notifications",
    de: "Push-Benachrichtigungen",
    ar: "الإشعارات الفورية",
  },
  sms_notifications: {
    en: "SMS Notifications",
    de: "SMS-Benachrichtigungen",
    ar: "إشعارات الرسائل القصيرة",
  },

  // ========================================
  // ADMIN PANEL - VENDORS
  // ========================================
  vendor_management: {
    en: "Vendor Management",
    de: "Anbieterverwaltung",
    ar: "إدارة الموردين",
  },
  active_vendors: {
    en: "Active Vendors",
    de: "Aktive Anbieter",
    ar: "الموردون النشطون",
  },
  pending_approval: {
    en: "Pending Approval",
    de: "Genehmigung ausstehend",
    ar: "في انتظار الموافقة",
  },
  suspended_vendors: {
    en: "Suspended Vendors",
    de: "Gesperrte Anbieter",
    ar: "الموردون المعلقون",
  },
  vendor_name: {
    en: "Vendor Name",
    de: "Anbietername",
    ar: "اسم المورد",
  },
  subscription_plan: {
    en: "Subscription Plan",
    de: "Abonnementplan",
    ar: "خطة الاشتراك",
  },
  subscription_status: {
    en: "Subscription Status",
    de: "Abonnementstatus",
    ar: "حالة الاشتراك",
  },
  joined_date: {
    en: "Joined Date",
    de: "Beitrittsdatum",
    ar: "تاريخ الانضمام",
  },
  total_revenue: {
    en: "Total Revenue",
    de: "Gesamtumsatz",
    ar: "إجمالي الإيرادات",
  },
  commission_rate: {
    en: "Commission Rate",
    de: "Provisionssatz",
    ar: "نسبة العمولة",
  },
  approve_vendor: {
    en: "Approve Vendor",
    de: "Anbieter genehmigen",
    ar: "الموافقة على المورد",
  },
  reject_vendor: {
    en: "Reject Vendor",
    de: "Anbieter ablehnen",
    ar: "رفض المورد",
  },
  suspend_vendor: {
    en: "Suspend Vendor",
    de: "Anbieter sperren",
    ar: "تعليق المورد",
  },
  activate_vendor: {
    en: "Activate Vendor",
    de: "Anbieter aktivieren",
    ar: "تفعيل المورد",
  },
  view_vendor_details: {
    en: "View Vendor Details",
    de: "Anbieterdetails anzeigen",
    ar: "عرض تفاصيل المورد",
  },

  // ========================================
  // ADMIN PANEL - CUSTOMERS
  // ========================================
  customer_management: {
    en: "Customer Management",
    de: "Kundenverwaltung",
    ar: "إدارة العملاء",
  },
  total_customers: {
    en: "Total Customers",
    de: "Gesamtkunden",
    ar: "إجمالي العملاء",
  },
  registered_users: {
    en: "Registered Users",
    de: "Registrierte Benutzer",
    ar: "المستخدمون المسجلون",
  },
  guest_users: {
    en: "Guest Users",
    de: "Gastbenutzer",
    ar: "المستخدمون الضيوف",
  },
  customer_details: {
    en: "Customer Details",
    de: "Kundendetails",
    ar: "تفاصيل العميل",
  },
  order_history: {
    en: "Order History",
    de: "Bestellverlauf",
    ar: "سجل الطلبات",
  },
  loyalty_points: {
    en: "Loyalty Points",
    de: "Treuepunkte",
    ar: "نقاط الولاء",
  },
  lifetime_value: {
    en: "Lifetime Value",
    de: "Lifetime-Wert",
    ar: "القيمة الدائمة",
  },

  // ========================================
  // COMMON ACTIONS
  // ========================================
  save: {
    en: "Save",
    de: "Speichern",
    ar: "حفظ",
  },
  cancel: {
    en: "Cancel",
    de: "Abbrechen",
    ar: "إلغاء",
  },
  edit: {
    en: "Edit",
    de: "Bearbeiten",
    ar: "تعديل",
  },
  delete: {
    en: "Delete",
    de: "Löschen",
    ar: "حذف",
  },
  add: {
    en: "Add",
    de: "Hinzufügen",
    ar: "إضافة",
  },
  update: {
    en: "Update",
    de: "Aktualisieren",
    ar: "تحديث",
  },
  confirm: {
    en: "Confirm",
    de: "Bestätigen",
    ar: "تأكيد",
  },
  close: {
    en: "Close",
    de: "Schließen",
    ar: "إغلاق",
  },
  search: {
    en: "Search",
    de: "Suchen",
    ar: "بحث",
  },
  filter: {
    en: "Filter",
    de: "Filtern",
    ar: "تصفية",
  },
  export: {
    en: "Export",
    de: "Exportieren",
    ar: "تصدير",
  },
  print: {
    en: "Print",
    de: "Drucken",
    ar: "طباعة",
  },
  download: {
    en: "Download",
    de: "Herunterladen",
    ar: "تنزيل",
  },
  upload: {
    en: "Upload",
    de: "Hochladen",
    ar: "تحميل",
  },
  view: {
    en: "View",
    de: "Ansehen",
    ar: "عرض",
  },
  view_all: {
    en: "View All",
    de: "Alle anzeigen",
    ar: "عرض الكل",
  },
  refresh: {
    en: "Refresh",
    de: "Aktualisieren",
    ar: "تحديث",
  },
  loading: {
    en: "Loading...",
    de: "Lädt...",
    ar: "جارٍ التحميل...",
  },
  no_data: {
    en: "No data available",
    de: "Keine Daten verfügbar",
    ar: "لا توجد بيانات",
  },
  error: {
    en: "Error",
    de: "Fehler",
    ar: "خطأ",
  },
  success: {
    en: "Success",
    de: "Erfolg",
    ar: "نجح",
  },

  // ========================================
  // TIME & DATE
  // ========================================
  today: {
    en: "Today",
    de: "Heute",
    ar: "اليوم",
  },
  yesterday: {
    en: "Yesterday",
    de: "Gestern",
    ar: "أمس",
  },
  this_week: {
    en: "This Week",
    de: "Diese Woche",
    ar: "هذا الأسبوع",
  },
  this_month: {
    en: "This Month",
    de: "Diesen Monat",
    ar: "هذا الشهر",
  },
  last_7_days: {
    en: "Last 7 Days",
    de: "Letzte 7 Tage",
    ar: "آخر 7 أيام",
  },
  last_30_days: {
    en: "Last 30 Days",
    de: "Letzte 30 Tage",
    ar: "آخر 30 يومًا",
  },
  custom_range: {
    en: "Custom Range",
    de: "Benutzerdefinierter Bereich",
    ar: "نطاق مخصص",
  },
  from: {
    en: "From",
    de: "Von",
    ar: "من",
  },
  to: {
    en: "To",
    de: "Bis",
    ar: "إلى",
  },

  // ========================================
  // INVOICE GENERATION (Austrian VAT)
  // ========================================
  generate_invoice: {
    en: "Generate Invoice",
    de: "Rechnung erstellen",
    ar: "إنشاء فاتورة",
  },
  invoice_number: {
    en: "Invoice Number",
    de: "Rechnungsnummer",
    ar: "رقم الفاتورة",
  },
  invoice_date: {
    en: "Invoice Date",
    de: "Rechnungsdatum",
    ar: "تاريخ الفاتورة",
  },
  due_date: {
    en: "Due Date",
    de: "Fälligkeitsdatum",
    ar: "تاريخ الاستحقاق",
  },
  billing_address: {
    en: "Billing Address",
    de: "Rechnungsadresse",
    ar: "عنوان الفوترة",
  },
  item_description: {
    en: "Item Description",
    de: "Artikelbeschreibung",
    ar: "وصف الصنف",
  },
  quantity: {
    en: "Quantity",
    de: "Menge",
    ar: "الكمية",
  },
  unit_price: {
    en: "Unit Price",
    de: "Stückpreis",
    ar: "سعر الوحدة",
  },
  net_amount: {
    en: "Net Amount",
    de: "Nettobetrag",
    ar: "المبلغ الصافي",
  },
  vat_amount: {
    en: "VAT Amount",
    de: "MwSt.-Betrag",
    ar: "مبلغ ضريبة القيمة المضافة",
  },
  gross_amount: {
    en: "Gross Amount",
    de: "Bruttobetrag",
    ar: "المبلغ الإجمالي",
  },
  total_amount: {
    en: "Total Amount",
    de: "Gesamtbetrag",
    ar: "المبلغ الإجمالي",
  },
  payment_status: {
    en: "Payment Status",
    de: "Zahlungsstatus",
    ar: "حالة الدفع",
  },
  paid: {
    en: "Paid",
    de: "Bezahlt",
    ar: "مدفوع",
  },
  unpaid: {
    en: "Unpaid",
    de: "Unbezahlt",
    ar: "غير مدفوع",
  },
  partially_paid: {
    en: "Partially Paid",
    de: "Teilweise bezahlt",
    ar: "مدفوع جزئياً",
  },
  austrian_vat_10: {
    en: "10% VAT (reduced rate)",
    de: "10% MwSt. (ermäßigter Satz)",
    ar: "10% ضريبة (سعر مخفض)",
  },
  austrian_vat_13: {
    en: "13% VAT (intermediate rate)",
    de: "13% MwSt. (Zwischensatz)",
    ar: "13% ضريبة (سعر وسط)",
  },
  austrian_vat_20: {
    en: "20% VAT (standard rate)",
    de: "20% MwSt. (Normalsatz)",
    ar: "20% ضريبة (سعر قياسي)",
  },

  // ========================================
  // NOTIFICATIONS & ALERTS
  // ========================================
  notification: {
    en: "Notification",
    de: "Benachrichtigung",
    ar: "إشعار",
  },
  notifications: {
    en: "Notifications",
    de: "Benachrichtigungen",
    ar: "الإشعارات",
  },
  new_order_alert: {
    en: "New order received",
    de: "Neue Bestellung erhalten",
    ar: "تم استلام طلب جديد",
  },
  order_completed_alert: {
    en: "Order completed",
    de: "Bestellung abgeschlossen",
    ar: "اكتمل الطلب",
  },
  new_review_alert: {
    en: "New review received",
    de: "Neue Bewertung erhalten",
    ar: "تم استلام تقييم جديد",
  },
  new_reservation_alert: {
    en: "New reservation",
    de: "Neue Reservierung",
    ar: "حجز جديد",
  },
  mark_all_read: {
    en: "Mark all as read",
    de: "Alle als gelesen markieren",
    ar: "وضع علامة على الكل كمقروء",
  },
  clear_all: {
    en: "Clear all",
    de: "Alle löschen",
    ar: "مسح الكل",
  },

  // ========================================
  // MULTI-LANGUAGE MENU TRANSLATION
  // ========================================
  menu_translations: {
    en: "Menu Translations",
    de: "Menü-Übersetzungen",
    ar: "ترجمات القائمة",
  },
  translate_menu: {
    en: "Translate Menu",
    de: "Menü übersetzen",
    ar: "ترجمة القائمة",
  },
  ai_auto_translate: {
    en: "AI Auto-Translate",
    de: "KI-Automatische Übersetzung",
    ar: "ترجمة تلقائية بالذكاء الاصطناعي",
  },
  translate_all_items: {
    en: "Translate All Items",
    de: "Alle Artikel übersetzen",
    ar: "ترجمة جميع الأصناف",
  },
  translation_progress: {
    en: "Translation Progress",
    de: "Übersetzungsfortschritt",
    ar: "تقدم الترجمة",
  },
  translate_to: {
    en: "Translate to",
    de: "Übersetzen nach",
    ar: "ترجمة إلى",
  },
  original_text: {
    en: "Original Text",
    de: "Originaltext",
    ar: "النص الأصلي",
  },
  translated_text: {
    en: "Translated Text",
    de: "Übersetzter Text",
    ar: "النص المترجم",
  },
  auto_translate_with_ai: {
    en: "Auto-translate with AI",
    de: "Automatisch mit KI übersetzen",
    ar: "ترجمة تلقائية بالذكاء الاصطناعي",
  },
  manual_translation: {
    en: "Manual Translation",
    de: "Manuelle Übersetzung",
    ar: "ترجمة يدوية",
  },
  translation_quality: {
    en: "Translation Quality",
    de: "Übersetzungsqualität",
    ar: "جودة الترجمة",
  },
  review_translation: {
    en: "Review Translation",
    de: "Übersetzung überprüfen",
    ar: "مراجعة الترجمة",
  },
  approve_translation: {
    en: "Approve Translation",
    de: "Übersetzung genehmigen",
    ar: "الموافقة على الترجمة",
  },

  // ========================================
  // ACCOUNT & PROFILE
  // ========================================
  my_account: {
    en: "My Account",
    de: "Mein Konto",
    ar: "حسابي",
  },
  profile: {
    en: "Profile",
    de: "Profil",
    ar: "الملف الشخصي",
  },
  change_password: {
    en: "Change Password",
    de: "Passwort ändern",
    ar: "تغيير كلمة المرور",
  },
  logout: {
    en: "Logout",
    de: "Abmelden",
    ar: "تسجيل الخروج",
  },
  current_password: {
    en: "Current Password",
    de: "Aktuelles Passwort",
    ar: "كلمة المرور الحالية",
  },
  new_password: {
    en: "New Password",
    de: "Neues Passwort",
    ar: "كلمة المرور الجديدة",
  },
  confirm_password: {
    en: "Confirm Password",
    de: "Passwort bestätigen",
    ar: "تأكيد كلمة المرور",
  },

  // ========================================
  // HELP & SUPPORT
  // ========================================
  help: {
    en: "Help",
    de: "Hilfe",
    ar: "مساعدة",
  },
  support: {
    en: "Support",
    de: "Support",
    ar: "الدعم",
  },
  contact_support: {
    en: "Contact Support",
    de: "Support kontaktieren",
    ar: "اتصل بالدعم",
  },
  documentation: {
    en: "Documentation",
    de: "Dokumentation",
    ar: "الوثائق",
  },
  faq: {
    en: "FAQ",
    de: "FAQ",
    ar: "الأسئلة الشائعة",
  },
  video_tutorials: {
    en: "Video Tutorials",
    de: "Video-Tutorials",
    ar: "دروس الفيديو",
  },

  // ========================================
  // PLATFORM HOMEPAGE (PUBLIC)
  // ========================================
  your_restaurant_digitally_connected: {
    en: "Infrastructure For Modern Restaurants",
    de: "Infrastruktur für moderne Restaurants",
    ar: "البنية التحتية للمطاعم الحديثة",
  },
  discover_restaurants_order_seamlessly: {
    en: "Ordering, payments, reservations, and loyalty in one Platform.",
    de: "Bestellungen, Zahlungen, Reservierungen und Kundenbindung in einziger Platform.",
    ar: ".الطلبات، المدفوعات، الحجوزات، والولاء متصلة في منصة واحدة",
  },
  in_12_languages_qr_powered: {
    en: "In 12 languages. Shared table ordering. Built for modern dining.",
    de: "In 12 Sprachen. Gemeinsame Tischbestellung. Für modernes Essen gebaut.",
    ar: "بـ 12 لغة. الطلب المشترك على الطاولة. مصمم لتجربة طعام عصرية",
  },
  find_restaurants: {
    en: "Find Restaurants",
    de: "Restaurants finden",
    ar: "البحث عن مطاعم",
  },
  scan_qr_code: {
    en: "Scan QR Code",
    de: "QR-Code scannen",
    ar: "مسح رمز QR",
  },
  languages: {
    en: "Languages",
    de: "Sprachen",
    ar: "اللغات",
  },
  restaurants: {
    en: "Restaurants",
    de: "Restaurants",
    ar: "المطاعم",
  },
  orders: {
    en: "Orders",
    de: "Bestellungen",
    ar: "الطلبات",
  },
  are_you_restaurant_owner: {
    en: "Are you a restaurant owner?",
    de: "Sind Sie Restaurantbesitzer?",
    ar: "هل أنت صاحب مطعم؟",
  },
  back_to_home: {
    en: "Back to Home",
    de: "Zurück zur Startseite",
    ar: "العودة إلى الصفحة الرئيسية",
  },
  open_now: {
    en: "Open Now",
    de: "Jetzt geöffnet",
    ar: "مفتوح الآن",
  },
  min_rating: {
    en: "Min Rating",
    de: "Mindestbewertung",
    ar: "الحد الأدنى للتقييم",
  },
  max_distance: {
    en: "Max Distance",
    de: "Maximale Entfernung",
    ar: "أقصى مسافة",
  },
  takeaway_only: {
    en: "Takeaway Only",
    de: "Nur Mitnahme",
    ar: "طلبات خارجية فقط",
  },
  more_filters: {
    en: "More Filters",
    de: "Weitere Filter",
    ar: "المزيد من الفلاتر",
  },
  price_level: {
    en: "Price Level",
    de: "Preisniveau",
    ar: "مستوى السعر",
  },
  cuisine_type: {
    en: "Cuisine Type",
    de: "Küchenart",
    ar: "نوع المطبخ",
  },
  dietary_options: {
    en: "Dietary Options",
    de: "Ernährungsoptionen",
    ar: "خيارات النظام الغذائي",
  },
  apply_filters: {
    en: "Apply Filters",
    de: "Filter anwenden",
    ar: "تطبيق الفلاتر",
  },
  clear_filters: {
    en: "Clear Filters",
    de: "Filter löschen",
    ar: "مسح الفلاتر",
  },
  budget_friendly: {
    en: "Budget-friendly",
    de: "Preisgünstig",
    ar: "اقتصادي",
  },
  mid_range: {
    en: "Mid-range",
    de: "Mittelpreisig",
    ar: "متوسط السعر",
  },
  fine_dining: {
    en: "Fine dining",
    de: "Gehobene Küche",
    ar: "طعام فاخر",
  },
  best_for_lunch: {
    en: "Best for lunch",
    de: "Ideal zum Mittagessen",
    ar: "الأفضل للغداء",
  },
  best_for_takeaway: {
    en: "Best for takeaway",
    de: "Ideal für Mitnahme",
    ar: "الأفضل للطلبات الخارجية",
  },
  fast_service: {
    en: "Fast service",
    de: "Schneller Service",
    ar: "خدمة سريعة",
  },
  date_friendly: {
    en: "Date-friendly",
    de: "Ideal für Dates",
    ar: "مناسب للمواعدة",
  },
  loyalty_program_available: {
    en: "Loyalty Program",
    de: "Treueprogramm",
    ar: "برنامج الولاء",
  },
  verified_restaurant: {
    en: "Verified",
    de: "Verifiziert",
    ar: "موثق",
  },
  highly_rated: {
    en: "Highly rated",
    de: "Hoch bewertet",
    ar: "تقييم عالي",
  },
  popular_choice: {
    en: "Popular choice",
    de: "Beliebte Wahl",
    ar: "خيار شائع",
  },
  opens_at: {
    en: "Opens at",
    de: "Öffnet um",
    ar: "يفتح عند",
  },
  closes_soon: {
    en: "Closes soon",
    de: "Schließt bald",
    ar: "يغلق قريباً",
  },

  // ========================================
  // RESTAURANT PAGE - TABS & NAVIGATION
  // ========================================
  order: {
    en: "Order",
    de: "Bestellen",
    ar: "طلب",
  },
  location: {
    en: "Location",
    de: "Standort",
    ar: "الموقع",
  },
  about: {
    en: "About",
    de: "Über uns",
    ar: "حول",
  },
  back: {
    en: "Back",
    de: "Zurück",
    ar: "رجوع",
  },

  // ========================================
  // RESTAURANT PAGE - ORDER TAB
  // ========================================
  how_would_you_like_to_order: {
    en: "How would you like to order?",
    de: "Wie möchten Sie bestellen?",
    ar: "كيف تريد الطلب؟",
  },
  scan_qr_at_table: {
    en: "Scan QR at Table",
    de: "QR-Code am Tisch scannen",
    ar: "مسح QR على الطاولة",
  },
  scan_qr_at_table_desc: {
    en: "Order and pay from your table",
    de: "Von Ihrem Tisch bestellen und bezahlen",
    ar: "اطلب وادفع من طاولتك",
  },
  order_for_takeaway: {
    en: "Order for Takeaway",
    de: "Für Mitnahme bestellen",
    ar: "طلب للاستلام",
  },
  order_for_takeaway_desc: {
    en: "Pick up your order at the restaurant",
    de: "Holen Sie Ihre Bestellung im Restaurant ab",
    ar: "استلم طلبك من المطعم",
  },
  make_a_reservation: {
    en: "Make a Reservation",
    de: "Reservierung vornehmen",
    ar: "إجراء حجز",
  },
  make_a_reservation_desc: {
    en: "Reserve a table for dining in",
    de: "Reservieren Sie einen Tisch zum Essen",
    ar: "احجز طاولة لتناول الطعام",
  },
  coming_soon: {
    en: "Coming Soon",
    de: "Demnächst",
    ar: "قريباً",
  },

  // ========================================
  // RESTAURANT PAGE - MENU TAB
  // ========================================
  full_menu: {
    en: "Full Menu",
    de: "Vollständiges Menü",
    ar: "القائمة الكاملة",
  },
  starters: {
    en: "Starters",
    de: "Vorspeisen",
    ar: "المقبلات",
  },
  mains: {
    en: "Mains",
    de: "Hauptgerichte",
    ar: "الأطباق الرئيسية",
  },
  desserts: {
    en: "Desserts",
    de: "Desserts",
    ar: "الحلويات",
  },
  drinks: {
    en: "Drinks",
    de: "Getränke",
    ar: "المشروبات",
  },

  // ========================================
  // RESTAURANT PAGE - REVIEWS TAB
  // ========================================
  customer_reviews: {
    en: "Customer Reviews",
    de: "Kundenbewertungen",
    ar: "تقييمات العملاء",
  },
  based_on_reviews: {
    en: "Based on {count} reviews",
    de: "Basierend auf {count} Bewertungen",
    ar: "بناءً على {count} تقييم",
  },
  write_a_review: {
    en: "Write a Review",
    de: "Bewertung schreiben",
    ar: "كتابة تقييم",
  },
  verified_order: {
    en: "Verified Order",
    de: "Verifizierte Bestellung",
    ar: "طلب موثق",
  },
  dine_in: {
    en: "Dine-in",
    de: "Im Restaurant",
    ar: "تناول الطعام بالداخل",
  },
  takeaway: {
    en: "Takeaway",
    de: "Mitnahme",
    ar: "طلب خارجي",
  },
  delivery: {
    en: "Delivery",
    de: "Lieferung",
    ar: "توصيل",
  },
  helpful: {
    en: "Helpful",
    de: "Hilfreich",
    ar: "مفيد",
  },
  people_found_helpful: {
    en: "{count} people found this helpful",
    de: "{count} Personen fanden dies hilfreich",
    ar: "{count} أشخاص وجدوا هذا مفيداً",
  },

  // ========================================
  // RESTAURANT PAGE - LOCATION TAB
  // ========================================
  location_hours: {
    en: "Location & Hours",
    de: "Standort & Öffnungszeiten",
    ar: "الموقع والساعات",
  },
  opening_hours: {
    en: "Opening Hours",
    de: "Öffnungszeiten",
    ar: "ساعات العمل",
  },
  contact_information: {
    en: "Contact Information",
    de: "Kontaktinformationen",
    ar: "معلومات الاتصال",
  },
  get_directions: {
    en: "Get Directions",
    de: "Wegbeschreibung",
    ar: "الحصول على الاتجاهات",
  },
  call_restaurant: {
    en: "Call Restaurant",
    de: "Restaurant anrufen",
    ar: "الاتصال بالمطعم",
  },
  visit_website: {
    en: "Visit Website",
    de: "Webseite besuchen",
    ar: "زيارة الموقع",
  },
  monday: {
    en: "Monday",
    de: "Montag",
    ar: "الاثنين",
  },
  tuesday: {
    en: "Tuesday",
    de: "Dienstag",
    ar: "الثلاثاء",
  },
  wednesday: {
    en: "Wednesday",
    de: "Mittwoch",
    ar: "الأربعاء",
  },
  thursday: {
    en: "Thursday",
    de: "Donnerstag",
    ar: "الخميس",
  },
  friday: {
    en: "Friday",
    de: "Freitag",
    ar: "الجمعة",
  },
  saturday: {
    en: "Saturday",
    de: "Samstag",
    ar: "السبت",
  },
  sunday: {
    en: "Sunday",
    de: "Sonntag",
    ar: "الأحد",
  },
  closed: {
    en: "Closed",
    de: "Geschlossen",
    ar: "مغلق",
  },

  // ========================================
  // RESTAURANT PAGE - ABOUT TAB
  // ========================================
  about_restaurant: {
    en: "About this Restaurant",
    de: "Über dieses Restaurant",
    ar: "عن هذا المطعم",
  },
  restaurant_features: {
    en: "Restaurant Features",
    de: "Restaurant-Merkmale",
    ar: "ميزات المطعم",
  },
  accepts_card_payments: {
    en: "Accepts card payments",
    de: "Akzeptiert Kartenzahlungen",
    ar: "يقبل الدفع بالبطاقة",
  },
  free_delivery_available: {
    en: "Free delivery available",
    de: "Kostenlose Lieferung verfügbar",
    ar: "التوصيل المجاني متاح",
  },
  fast_delivery: {
    en: "Fast delivery",
    de: "Schnelle Lieferung",
    ar: "توصيل سريع",
  },

  // ========================================
  // MODALS - RESERVATION
  // ========================================
  reserve_a_table: {
    en: "Reserve a Table",
    de: "Tisch reservieren",
    ar: "حجز طاولة",
  },
  select_date: {
    en: "Select Date",
    de: "Datum wählen",
    ar: "اختر التاريخ",
  },
  select_time: {
    en: "Select Time",
    de: "Uhrzeit wählen",
    ar: "اختر الوقت",
  },
  number_of_guests: {
    en: "Number of Guests",
    de: "Anzahl der Gäste",
    ar: "عدد الضيوف",
  },
  guests: {
    en: "guests",
    de: "Gäste",
    ar: "ضيوف",
  },
  your_name: {
    en: "Your Name",
    de: "Ihr Name",
    ar: "اسمك",
  },
  your_email: {
    en: "Your Email",
    de: "Ihre E-Mail",
    ar: "بريدك الإلكتروني",
  },
  your_phone: {
    en: "Your Phone",
    de: "Ihre Telefonnummer",
    ar: "رقم هاتفك",
  },
  special_requests: {
    en: "Special Requests (optional)",
    de: "Sonderwünsche (optional)",
    ar: "طلبات خاصة (اختياري)",
  },
  confirm_reservation: {
    en: "Confirm Reservation",
    de: "Reservierung bestätigen",
    ar: "تأكيد الحجز",
  },

  // ========================================
  // MODALS - TAKEAWAY
  // ========================================
  order_for_pickup: {
    en: "Order for Pickup",
    de: "Zur Abholung bestellen",
    ar: "الطلب للاستلام",
  },
  when_pickup: {
    en: "When would you like to pick up?",
    de: "Wann möchten Sie abholen?",
    ar: "متى تريد الاستلام؟",
  },
  as_soon_as_possible: {
    en: "As soon as possible",
    de: "So schnell wie möglich",
    ar: "في أقرب وقت ممكن",
  },
  schedule_for_later: {
    en: "Schedule for later",
    de: "Für später planen",
    ar: "جدولة لاحقاً",
  },
  estimated_ready_time: {
    en: "Estimated ready time",
    de: "Voraussichtliche Bereitschaftszeit",
    ar: "الوقت المقدر للجاهزية",
  },
  pickup_time: {
    en: "Pickup Time",
    de: "Abholzeit",
    ar: "وقت الاستلام",
  },
  continue_to_menu: {
    en: "Continue to Menu",
    de: "Weiter zum Menü",
    ar: "المتابعة إلى القائمة",
  },
  guest_information: {
    en: "Guest Information",
    de: "Gast-Informationen",
    ar: "معلومات الضيف",
  },
  optional: {
    en: "Optional",
    de: "Optional",
    ar: "اختياري",
  },
  continue: {
    en: "Continue",
    de: "Weiter",
    ar: "متابعة",
  },
};

// Helper function to get platform translation
export function getPlatformTranslation(
  key: string,
  lang: PlatformLanguageCode = "en",
): string {
  return (
    PLATFORM_TRANSLATIONS[key]?.[lang] ||
    PLATFORM_TRANSLATIONS[key]?.en ||
    key
  );
}

// Helper function to translate with placeholders
export function translatePlatform(
  key: string,
  lang: PlatformLanguageCode = "en",
  params?: Record<string, string>,
): string {
  let translation = getPlatformTranslation(key, lang);

  if (params) {
    Object.keys(params).forEach((param) => {
      translation = translation.replace(
        `{${param}}`,
        params[param],
      );
    });
  }

  return translation;
}