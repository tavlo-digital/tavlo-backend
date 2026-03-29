# 🌍 Translation Quick Reference Card

## 11 Supported Languages

```
🇬🇧 en - English       🇹🇷 tr - Türkçe       🇷🇸 sr - Српски
🇩🇪 de - Deutsch       🇨🇳 zh - 中文          🇨🇿 cs - Čeština
🇮🇹 it - Italiano      🇯🇵 ja - 日本語        🇪🇸 es - Español
🇫🇷 fr - Français      🇸🇦 ar - العربية (RTL)
```

---

## ⚡ Quick Usage

### Import:
```typescript
import { getPlatformTranslation } from '../utils/platformTranslations';
```

### Use:
```typescript
<h1>{getPlatformTranslation('dashboard', 'de')}</h1>
// Output: "Dashboard"

<h1>{getPlatformTranslation('orders', 'ar')}</h1>
// Output: "الطلبات"
```

### RTL:
```typescript
<div dir={language === 'ar' ? 'rtl' : 'ltr'}>
```

---

## 📋 Most Common Translations

| Key | English | Deutsch | العربية |
|-----|---------|---------|--------|
| dashboard | Dashboard | Dashboard | لوحة التحكم |
| orders | Orders | Bestellungen | الطلبات |
| menu | Menu | Speisekarte | القائمة |
| analytics | Analytics | Analysen | التحليلات |
| reviews | Reviews | Bewertungen | التقييمات |
| settings | Settings | Einstellungen | الإعدادات |
| save | Save | Speichern | حفظ |
| cancel | Cancel | Abbrechen | إلغاء |
| edit | Edit | Bearbeiten | تعديل |
| delete | Delete | Löschen | حذف |
| new_orders | New Orders | Neue Bestellungen | طلبات جديدة |
| in_progress | In Progress | In Bearbeitung | قيد التنفيذ |
| ready | Ready | Bereit | جاهز |
| delivered | Delivered | Geliefert | تم التوصيل |
| top_customers | Top Customers | Top-Kunden | أفضل العملاء |
| total_revenue | Total Revenue | Gesamtumsatz | إجمالي الإيرادات |
| ai_review_summary | AI Review Summary | KI-Bewertungszusammenfassung | ملخص التقييمات بالذكاء الاصطناعي |
| positive | POSITIVE | POSITIV | إيجابي |
| generate_invoice | Generate Invoice | Rechnung erstellen | إنشاء فاتورة |
| vat_amount | VAT Amount | MwSt.-Betrag | مبلغ ضريبة القيمة المضافة |

---

## 🎯 By Category

### Navigation
```typescript
getPlatformTranslation('dashboard', lang)
getPlatformTranslation('orders', lang)
getPlatformTranslation('reservations', lang)
getPlatformTranslation('menu', lang)
getPlatformTranslation('analytics', lang)
getPlatformTranslation('reviews', lang)
getPlatformTranslation('settings', lang)
```

### Orders
```typescript
getPlatformTranslation('new_orders', lang)
getPlatformTranslation('in_progress', lang)
getPlatformTranslation('ready', lang)
getPlatformTranslation('delivered', lang)
getPlatformTranslation('accept_order', lang)
getPlatformTranslation('mark_as_ready', lang)
```

### Analytics
```typescript
getPlatformTranslation('customer_analytics', lang)
getPlatformTranslation('top_customers', lang)
getPlatformTranslation('total_revenue', lang)
getPlatformTranslation('daily_revenue', lang)
```

### Reviews
```typescript
getPlatformTranslation('reviews_management', lang)
getPlatformTranslation('ai_review_summary', lang)
getPlatformTranslation('positive', lang)
getPlatformTranslation('negative', lang)
```

### Actions
```typescript
getPlatformTranslation('save', lang)
getPlatformTranslation('cancel', lang)
getPlatformTranslation('edit', lang)
getPlatformTranslation('delete', lang)
```

---

## 📊 Translation Status

| Component | EN | DE | AR | Others |
|-----------|----|----|----|----|
| QR Order | ✅ | ✅ | ✅ | ✅ All 11 |
| Vendor Dashboard | ✅ | ✅ | ✅ | ⏳ Needed |
| Admin Panel | ✅ | ✅ | ✅ | ⏳ Needed |
| AI Features | ✅ | ✅ | ✅ | ⏳ Needed |

---

## 📁 Files

**QR Order:** `/utils/translations.ts` (11 languages)  
**Platform:** `/utils/platformTranslations.ts` (EN, DE, AR)

---

## 🔧 Integration Template

```typescript
import { getPlatformTranslation } from '../utils/platformTranslations';

export function MyComponent({ language = 'en' }) {
  return (
    <div dir={language === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{getPlatformTranslation('dashboard', language)}</h1>
      <button>{getPlatformTranslation('save', language)}</button>
      <button>{getPlatformTranslation('cancel', language)}</button>
    </div>
  );
}
```

---

**Last Updated:** December 16, 2024  
**Status:** DE & AR Complete for Platform ✅
