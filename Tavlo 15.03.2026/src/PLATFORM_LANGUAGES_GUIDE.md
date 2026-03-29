# 🌍 TAVLO Platform - Multi-Language Support

## Complete Language Support

TAVLO supports **11 languages** across all customer-facing and platform interfaces:

---

## 📋 All Supported Languages

| # | Code | Language | Native Name | Flag | Direction | Status |
|---|------|----------|-------------|------|-----------|--------|
| 1 | `en` | English | English | 🇬🇧 | LTR | ✅ Complete |
| 2 | `de` | German | Deutsch | 🇩🇪 | LTR | ✅ Complete |
| 3 | `it` | Italian | Italiano | 🇮🇹 | LTR | ⚠️ Partial |
| 4 | `fr` | French | Français | 🇫🇷 | LTR | ⚠️ Partial |
| 5 | `ar` | Arabic | العربية | 🇸🇦 | RTL | ✅ Complete |
| 6 | `tr` | Turkish | Türkçe | 🇹🇷 | LTR | ⚠️ Partial |
| 7 | `zh` | Chinese | 中文 | 🇨🇳 | LTR | ⚠️ Partial |
| 8 | `ja` | Japanese | 日本語 | 🇯🇵 | LTR | ⚠️ Partial |
| 9 | `sr` | Serbian | Српски | 🇷🇸 | LTR | ⚠️ Partial |
| 10 | `cs` | Czech | Čeština | 🇨🇿 | LTR | ⚠️ Partial |
| 11 | `es` | Spanish | Español | 🇪🇸 | LTR | ⚠️ Partial |

### Legend:
- ✅ **Complete**: Full translations for both QR Order AND Platform
- ⚠️ **Partial**: QR Order translations exist, Platform translations needed

---

## 📂 Translation Files

### 1. Customer QR Order System
**File:** `/utils/translations.ts`
- **Coverage:** All 11 languages
- **Sections:**
  - Navigation & General UI
  - Menu browsing
  - Dish details & ordering
  - Basket & payment
  - Order tracking
  - Bill splitting
  - Reviews & ratings
  - Authentication

### 2. Vendor Dashboard & Admin Panel
**File:** `/utils/platformTranslations.ts` (NEW!)
- **Coverage:** English, German, Arabic (complete)
- **Sections:**
  - Dashboard & navigation
  - Orders management
  - Reservations
  - Menu management
  - QR code generation
  - Loyalty program
  - Analytics & insights
  - Reviews management
  - Restaurant settings
  - Admin vendor management
  - Invoice generation (Austrian VAT)
  - Multi-language menu translation

---

## 🎯 Current Translation Status

### ✅ **COMPLETE** (English, German, Arabic)

#### Customer Side (QR Order):
- ✅ All menu browsing
- ✅ All dish details
- ✅ Complete ordering flow
- ✅ Payment & bill splitting
- ✅ Order tracking
- ✅ Reviews & ratings

#### Platform Side (Vendor/Admin):
- ✅ Dashboard navigation
- ✅ Orders management
- ✅ Reservations
- ✅ Menu management
- ✅ QR codes
- ✅ Loyalty program
- ✅ Analytics
- ✅ Reviews management
- ✅ Settings
- ✅ Admin panels
- ✅ Invoice generation

---

## 🚀 How to Use Platform Translations

### Import the Helper Functions:

```typescript
import { getPlatformTranslation, translatePlatform } from '../utils/platformTranslations';
```

### Basic Usage:

```typescript
// Simple translation
const dashboardText = getPlatformTranslation('dashboard', 'de'); // "Dashboard"
const ordersText = getPlatformTranslation('orders', 'ar'); // "الطلبات"

// With placeholders
const invoiceText = translatePlatform('invoice_number', 'de', { number: '12345' });
```

### In React Components:

```typescript
import { getPlatformTranslation } from '../utils/platformTranslations';

function VendorSidebar({ language = 'en' }) {
  return (
    <nav>
      <a href="/dashboard">{getPlatformTranslation('dashboard', language)}</a>
      <a href="/orders">{getPlatformTranslation('orders', language)}</a>
      <a href="/menu">{getPlatformTranslation('menu', language)}</a>
      <a href="/analytics">{getPlatformTranslation('analytics', language)}</a>
      <a href="/reviews">{getPlatformTranslation('reviews', language)}</a>
      <a href="/settings">{getPlatformTranslation('settings', language)}</a>
    </nav>
  );
}
```

---

## 📊 Translation Coverage Statistics

### QR Order System (Customer):
- **Total Keys**: ~200
- **Languages**: 11
- **Complete**: All languages

### Platform (Vendor/Admin):
- **Total Keys**: ~250+
- **Languages**: Currently 3 (EN, DE, AR)
- **Needed**: 8 more languages (IT, FR, TR, ZH, JA, SR, CS, ES)

---

## 🔄 Next Steps for Full Coverage

To complete all 11 languages for the platform:

### Priority 1 - European Languages:
1. **Italian** (IT) - Vendor dashboard & admin
2. **French** (FR) - Vendor dashboard & admin
3. **Czech** (CS) - Vendor dashboard & admin
4. **Spanish** (ES) - Vendor dashboard & admin

### Priority 2 - Other Languages:
5. **Turkish** (TR) - Vendor dashboard & admin
6. **Serbian** (SR) - Vendor dashboard & admin
7. **Chinese** (ZH) - Vendor dashboard & admin
8. **Japanese** (JA) - Vendor dashboard & admin

---

## 🌐 Special Language Considerations

### Right-to-Left (RTL) Support:
- **Arabic** (`ar`) requires RTL layout
- Already handled in customer QR system
- Platform components need RTL CSS support

### Example RTL Implementation:
```typescript
const direction = language === 'ar' ? 'rtl' : 'ltr';
<div dir={direction} className={language === 'ar' ? 'font-arabic' : ''}>
  {getPlatformTranslation('dashboard', language)}
</div>
```

---

## 📝 Platform Translation Categories

### 1. Navigation (Sidebar)
```
Dashboard, Overview, Orders, Reservations, Menu, 
QR Codes, Loyalty, Analytics, Reviews, Settings
```

### 2. Orders Management
```
New Orders, In Progress, Ready, Delivered, Cancelled,
Accept Order, Reject Order, Mark as Ready, Print Receipt
```

### 3. Analytics
```
Customer Analytics, Sales Analytics, Top Customers,
Top Dishes, Peak Hours, Daily/Weekly/Monthly Revenue
```

### 4. Reviews Management
```
All Reviews, Positive, Negative, Respond to Review,
AI Review Summary, Sentiment Analysis
```

### 5. Settings
```
Restaurant Settings, Opening Hours, Payment Methods,
VAT Settings, Language Settings, Notifications
```

### 6. Admin Panel
```
Vendor Management, Customer Management, 
Approve/Reject/Suspend Vendor, Subscription Plan
```

### 7. Invoice Generation
```
Generate Invoice, Invoice Number, VAT Amount,
Austrian VAT Rates (10%, 13%, 20%)
```

---

## 🎨 AI Features Translation

### Already Translated:
- ✅ AI Review Summary
- ✅ Sentiment Analysis (POSITIVE, NEGATIVE, MIXED)
- ✅ AI Insights
- ✅ Common Praises
- ✅ Areas for Improvement

### Translation Keys:
```typescript
'ai_review_summary': {
  en: 'AI Review Summary',
  de: 'KI-Bewertungszusammenfassung',
  ar: 'ملخص التقييمات بالذكاء الاصطناعي'
}

'sentiment_analysis': {
  en: 'Sentiment Analysis',
  de: 'Stimmungsanalyse',
  ar: 'تحليل المشاعر'
}

'positive': {
  en: 'POSITIVE',
  de: 'POSITIV',
  ar: 'إيجابي'
}
```

---

## 🚦 Testing Translations

### Test in Vendor Dashboard:
```
1. Set language to German (de):
   - Dashboard → "Dashboard"
   - Bestellungen → "Orders"
   - Bewertungen → "Reviews"

2. Set language to Arabic (ar):
   - لوحة التحكم → "Dashboard"
   - الطلبات → "Orders"
   - التقييمات → "Reviews"
   - Layout should flip to RTL
```

---

## 📖 Example Usage Scenarios

### Scenario 1: Vendor Dashboard in German
```typescript
const language = 'de';

<Sidebar>
  <MenuItem>{getPlatformTranslation('dashboard', language)}</MenuItem>
  {/* Output: "Dashboard" */}
  
  <MenuItem>{getPlatformTranslation('orders', language)}</MenuItem>
  {/* Output: "Bestellungen" */}
  
  <MenuItem>{getPlatformTranslation('analytics', language)}</MenuItem>
  {/* Output: "Analysen" */}
</Sidebar>
```

### Scenario 2: Order Alert in Arabic
```typescript
const language = 'ar';
const notification = getPlatformTranslation('new_order_alert', language);
// Output: "تم استلام طلب جديد"
```

### Scenario 3: Invoice Generation
```typescript
const language = 'de';
const invoiceLabel = getPlatformTranslation('generate_invoice', language);
// Output: "Rechnung erstellen"

const vatLabel = getPlatformTranslation('austrian_vat_20', language);
// Output: "20% MwSt. (Normalsatz)"
```

---

## ✅ Implementation Checklist

For integrating platform translations:

- [ ] Import `platformTranslations.ts` in vendor components
- [ ] Replace hardcoded English strings with `getPlatformTranslation()`
- [ ] Add language selector to vendor dashboard header
- [ ] Store vendor's preferred language in settings
- [ ] Add RTL support for Arabic layout
- [ ] Test all sidebar menu items
- [ ] Test all order management labels
- [ ] Test all analytics headings
- [ ] Test all settings sections
- [ ] Test admin panel labels

---

## 🎯 Quick Reference

### Most Common Translations:

| Key | English | German | Arabic |
|-----|---------|--------|--------|
| `dashboard` | Dashboard | Dashboard | لوحة التحكم |
| `orders` | Orders | Bestellungen | الطلبات |
| `menu` | Menu | Speisekarte | القائمة |
| `analytics` | Analytics | Analysen | التحليلات |
| `reviews` | Reviews | Bewertungen | التقييمات |
| `settings` | Settings | Einstellungen | الإعدادات |
| `save` | Save | Speichern | حفظ |
| `cancel` | Cancel | Abbrechen | إلغاء |
| `edit` | Edit | Bearbeiten | تعديل |
| `delete` | Delete | Löschen | حذف |

---

## 📧 Support & Questions

For translation updates or new language requests:
- Add translations to `/utils/platformTranslations.ts`
- Follow the existing structure
- Include all 11 language codes
- Test with RTL for Arabic

---

**Last Updated:** December 16, 2024  
**Status:** German & Arabic Platform Translations Complete  
**Next:** Complete remaining 8 languages for platform
