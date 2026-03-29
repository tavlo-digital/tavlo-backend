# 🔧 Platform Translation Integration Examples

## How to Integrate Translations into Vendor & Admin Components

---

## 1️⃣ Update Vendor Sidebar (Navigation)

### Before:
```typescript
// /components/vendor/Sidebar.tsx
const menuItems = [
  { id: 'overview', label: 'Overview', icon: Home },
  { id: 'orders', label: 'Orders', icon: ShoppingBag },
  { id: 'menu', label: 'Menu', icon: Menu },
  { id: 'analytics', label: 'Analytics', icon: BarChart3 },
  { id: 'reviews', label: 'Reviews', icon: ChefHat },
  { id: 'settings', label: 'Settings', icon: Settings },
];
```

### After (with translations):
```typescript
// /components/vendor/Sidebar.tsx
import { getPlatformTranslation } from '../../utils/platformTranslations';

interface SidebarProps {
  activeView: string;
  onViewChange: (view: string) => void;
  language?: 'en' | 'de' | 'ar'; // Add language prop
}

export function Sidebar({ activeView, onViewChange, language = 'en' }: SidebarProps) {
  const menuItems = [
    { id: 'overview', label: getPlatformTranslation('overview', language), icon: Home },
    { id: 'orders', label: getPlatformTranslation('orders', language), icon: ShoppingBag },
    { id: 'menu', label: getPlatformTranslation('menu', language), icon: Menu },
    { id: 'analytics', label: getPlatformTranslation('analytics', language), icon: BarChart3 },
    { id: 'reviews', label: getPlatformTranslation('reviews', language), icon: ChefHat },
    { id: 'settings', label: getPlatformTranslation('settings', language), icon: Settings },
  ];

  return (
    <nav dir={language === 'ar' ? 'rtl' : 'ltr'}>
      {menuItems.map(item => (
        <button key={item.id} onClick={() => onViewChange(item.id)}>
          <item.icon />
          <span>{item.label}</span>
        </button>
      ))}
    </nav>
  );
}
```

---

## 2️⃣ Update Orders Management

### Before:
```typescript
// /components/vendor/OrdersManagement.tsx
<div className="tabs">
  <button>New Orders</button>
  <button>In Progress</button>
  <button>Ready</button>
  <button>Delivered</button>
</div>
```

### After (with translations):
```typescript
// /components/vendor/OrdersManagement.tsx
import { getPlatformTranslation } from '../../utils/platformTranslations';

export function OrdersManagement({ language = 'en' }) {
  return (
    <div className="tabs" dir={language === 'ar' ? 'rtl' : 'ltr'}>
      <button>{getPlatformTranslation('new_orders', language)}</button>
      <button>{getPlatformTranslation('in_progress', language)}</button>
      <button>{getPlatformTranslation('ready', language)}</button>
      <button>{getPlatformTranslation('delivered', language)}</button>
    </div>
  );
}
```

**Output:**
- English: "New Orders", "In Progress", "Ready", "Delivered"
- German: "Neue Bestellungen", "In Bearbeitung", "Bereit", "Geliefert"
- Arabic: "طلبات جديدة", "قيد التنفيذ", "جاهز", "تم التوصيل"

---

## 3️⃣ Update Analytics Dashboard

### Before:
```typescript
// /components/vendor/AnalyticsView.tsx
<div className="header">
  <h2>Customer Analytics</h2>
  <p>Insights about your customers and sales</p>
</div>

<div className="stats">
  <StatCard title="Total Revenue" value="€12,345" />
  <StatCard title="Total Orders" value="456" />
  <StatCard title="Top Customers" value="23" />
</div>
```

### After (with translations):
```typescript
// /components/vendor/AnalyticsView.tsx
import { getPlatformTranslation } from '../../utils/platformTranslations';

export function AnalyticsView({ language = 'en' }) {
  return (
    <div dir={language === 'ar' ? 'rtl' : 'ltr'}>
      <div className="header">
        <h2>{getPlatformTranslation('customer_analytics', language)}</h2>
      </div>

      <div className="stats">
        <StatCard 
          title={getPlatformTranslation('total_revenue', language)} 
          value="€12,345" 
        />
        <StatCard 
          title={getPlatformTranslation('total_orders', language)} 
          value="456" 
        />
        <StatCard 
          title={getPlatformTranslation('top_customers', language)} 
          value="23" 
        />
      </div>
    </div>
  );
}
```

**Output:**
- English: "Customer Analytics", "Total Revenue", "Total Orders"
- German: "Kundenanalysen", "Gesamtumsatz", "Gesamtbestellungen"
- Arabic: "تحليلات العملاء", "إجمالي الإيرادات", "إجمالي الطلبات"

---

## 4️⃣ Update Reviews Management

### Before:
```typescript
// /components/vendor/ReviewsManagement.tsx
<TabsList>
  <TabsTrigger value="all">All Reviews</TabsTrigger>
  <TabsTrigger value="positive">Positive</TabsTrigger>
  <TabsTrigger value="negative">Negative</TabsTrigger>
</TabsList>

<h3>AI Review Summary</h3>
<div className="sentiment">POSITIVE</div>
```

### After (with translations):
```typescript
// /components/vendor/ReviewsManagement.tsx
import { getPlatformTranslation } from '../../utils/platformTranslations';

export function ReviewsManagement({ language = 'en' }) {
  return (
    <div dir={language === 'ar' ? 'rtl' : 'ltr'}>
      <TabsList>
        <TabsTrigger value="all">
          {getPlatformTranslation('all_reviews', language)}
        </TabsTrigger>
        <TabsTrigger value="positive">
          {getPlatformTranslation('positive_reviews', language)}
        </TabsTrigger>
        <TabsTrigger value="negative">
          {getPlatformTranslation('negative_reviews', language)}
        </TabsTrigger>
      </TabsList>

      <h3>{getPlatformTranslation('ai_review_summary', language)}</h3>
      <div className="sentiment">
        {getPlatformTranslation('positive', language)}
      </div>
    </div>
  );
}
```

**Output:**
- English: "All Reviews", "Positive Reviews", "AI Review Summary", "POSITIVE"
- German: "Alle Bewertungen", "Positive Bewertungen", "KI-Bewertungszusammenfassung", "POSITIV"
- Arabic: "جميع التقييمات", "التقييمات الإيجابية", "ملخص التقييمات بالذكاء الاصطناعي", "إيجابي"

---

## 5️⃣ Update Settings Page

### Before:
```typescript
// /components/vendor/Settings.tsx
<form>
  <label>Restaurant Name</label>
  <input type="text" />
  
  <label>Address</label>
  <input type="text" />
  
  <label>Phone Number</label>
  <input type="tel" />
  
  <button type="submit">Save Changes</button>
  <button type="button">Cancel</button>
</form>
```

### After (with translations):
```typescript
// /components/vendor/Settings.tsx
import { getPlatformTranslation } from '../../utils/platformTranslations';

export function Settings({ language = 'en' }) {
  return (
    <form dir={language === 'ar' ? 'rtl' : 'ltr'}>
      <label>{getPlatformTranslation('restaurant_name', language)}</label>
      <input type="text" />
      
      <label>{getPlatformTranslation('address', language)}</label>
      <input type="text" />
      
      <label>{getPlatformTranslation('phone_number', language)}</label>
      <input type="tel" />
      
      <button type="submit">
        {getPlatformTranslation('save', language)}
      </button>
      <button type="button">
        {getPlatformTranslation('cancel', language)}
      </button>
    </form>
  );
}
```

**Output:**
- English: "Restaurant Name", "Address", "Phone Number", "Save", "Cancel"
- German: "Restaurantname", "Adresse", "Telefonnummer", "Speichern", "Abbrechen"
- Arabic: "اسم المطعم", "العنوان", "رقم الهاتف", "حفظ", "إلغاء"

---

## 6️⃣ Add Language Selector to Header

### New Component:
```typescript
// /components/vendor/LanguageSelector.tsx
import { getPlatformTranslation } from '../../utils/platformTranslations';

const LANGUAGES = [
  { code: 'en', name: 'English', flag: '🇬🇧' },
  { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
  { code: 'ar', name: 'العربية', flag: '🇸🇦' },
];

interface LanguageSelectorProps {
  currentLanguage: string;
  onLanguageChange: (lang: string) => void;
}

export function LanguageSelector({ currentLanguage, onLanguageChange }: LanguageSelectorProps) {
  return (
    <div className="language-selector">
      <select 
        value={currentLanguage} 
        onChange={(e) => onLanguageChange(e.target.value)}
      >
        {LANGUAGES.map(lang => (
          <option key={lang.code} value={lang.code}>
            {lang.flag} {lang.name}
          </option>
        ))}
      </select>
    </div>
  );
}
```

### Usage in Vendor Dashboard:
```typescript
// /components/vendor/VendorDashboard.tsx
import { useState } from 'react';
import { LanguageSelector } from './LanguageSelector';

export function VendorDashboard() {
  const [language, setLanguage] = useState<'en' | 'de' | 'ar'>('en');

  return (
    <div className="vendor-dashboard">
      <header>
        <h1>TAVLO Vendor</h1>
        <LanguageSelector 
          currentLanguage={language} 
          onLanguageChange={setLanguage} 
        />
      </header>

      <Sidebar language={language} />
      <OrdersManagement language={language} />
      <AnalyticsView language={language} />
      <ReviewsManagement language={language} />
      <Settings language={language} />
    </div>
  );
}
```

---

## 7️⃣ Update Admin Vendor List

### Before:
```typescript
// /components/admin/VendorsList.tsx
<thead>
  <tr>
    <th>Vendor Name</th>
    <th>Subscription</th>
    <th>Status</th>
    <th>Total Revenue</th>
    <th>Actions</th>
  </tr>
</thead>
```

### After (with translations):
```typescript
// /components/admin/VendorsList.tsx
import { getPlatformTranslation } from '../../utils/platformTranslations';

export function VendorsList({ language = 'en' }) {
  return (
    <table dir={language === 'ar' ? 'rtl' : 'ltr'}>
      <thead>
        <tr>
          <th>{getPlatformTranslation('vendor_name', language)}</th>
          <th>{getPlatformTranslation('subscription_plan', language)}</th>
          <th>{getPlatformTranslation('subscription_status', language)}</th>
          <th>{getPlatformTranslation('total_revenue', language)}</th>
          <th>{getPlatformTranslation('view', language)}</th>
        </tr>
      </thead>
    </table>
  );
}
```

---

## 8️⃣ Invoice Generation with Austrian VAT

### Implementation:
```typescript
// /components/vendor/InvoiceGenerator.tsx
import { getPlatformTranslation } from '../../utils/platformTranslations';

export function InvoiceGenerator({ language = 'en' }) {
  return (
    <div className="invoice" dir={language === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{getPlatformTranslation('generate_invoice', language)}</h1>
      
      <div className="invoice-header">
        <p>{getPlatformTranslation('invoice_number', language)}: INV-2024-001</p>
        <p>{getPlatformTranslation('invoice_date', language)}: 16.12.2024</p>
      </div>

      <table>
        <thead>
          <tr>
            <th>{getPlatformTranslation('item_description', language)}</th>
            <th>{getPlatformTranslation('quantity', language)}</th>
            <th>{getPlatformTranslation('unit_price', language)}</th>
            <th>{getPlatformTranslation('net_amount', language)}</th>
          </tr>
        </thead>
      </table>

      <div className="totals">
        <p>{getPlatformTranslation('net_amount', language)}: €100.00</p>
        <p>{getPlatformTranslation('austrian_vat_20', language)}: €20.00</p>
        <p>{getPlatformTranslation('gross_amount', language)}: €120.00</p>
      </div>
    </div>
  );
}
```

**Output (German):**
- "Rechnung erstellen"
- "Rechnungsnummer: INV-2024-001"
- "Nettobetrag: €100.00"
- "20% MwSt. (Normalsatz): €20.00"
- "Bruttobetrag: €120.00"

**Output (Arabic):**
- "إنشاء فاتورة"
- "رقم الفاتورة: INV-2024-001"
- "المبلغ الصافي: €100.00"
- "20% ضريبة (سعر قياسي): €20.00"
- "المبلغ الإجمالي: €120.00"

---

## 9️⃣ Complete Integration Example

### Full Vendor Dashboard with Language Support:

```typescript
// /components/vendor/VendorDashboard.tsx
import { useState, useEffect } from 'react';
import { getPlatformTranslation } from '../../utils/platformTranslations';
import { Sidebar } from './Sidebar';
import { OverviewView } from './OverviewView';
import { OrdersManagement } from './OrdersManagement';
import { AnalyticsView } from './AnalyticsView';
import { ReviewsManagement } from './ReviewsManagement';
import { Settings } from './Settings';
import { LanguageSelector } from './LanguageSelector';

type Language = 'en' | 'de' | 'ar';

export function VendorDashboard() {
  const [activeView, setActiveView] = useState('overview');
  const [language, setLanguage] = useState<Language>('en');

  // Load saved language preference
  useEffect(() => {
    const savedLang = localStorage.getItem('vendorLanguage') as Language;
    if (savedLang) setLanguage(savedLang);
  }, []);

  // Save language preference
  const handleLanguageChange = (lang: string) => {
    setLanguage(lang as Language);
    localStorage.setItem('vendorLanguage', lang);
    
    // Update document direction for RTL
    if (lang === 'ar') {
      document.documentElement.dir = 'rtl';
    } else {
      document.documentElement.dir = 'ltr';
    }
  };

  return (
    <div className="vendor-dashboard" dir={language === 'ar' ? 'rtl' : 'ltr'}>
      {/* Header */}
      <header className="dashboard-header">
        <h1>TAVLO</h1>
        <LanguageSelector 
          currentLanguage={language} 
          onLanguageChange={handleLanguageChange} 
        />
      </header>

      {/* Sidebar */}
      <Sidebar 
        activeView={activeView} 
        onViewChange={setActiveView} 
        language={language}
      />

      {/* Main Content */}
      <main className="dashboard-content">
        {activeView === 'overview' && <OverviewView language={language} />}
        {activeView === 'orders' && <OrdersManagement language={language} />}
        {activeView === 'analytics' && <AnalyticsView language={language} />}
        {activeView === 'reviews' && <ReviewsManagement language={language} />}
        {activeView === 'settings' && <Settings language={language} />}
      </main>
    </div>
  );
}
```

---

## 🎨 RTL Styling for Arabic

### Add to your CSS:

```css
/* globals.css */
[dir="rtl"] {
  text-align: right;
}

[dir="rtl"] .sidebar {
  left: auto;
  right: 0;
}

[dir="rtl"] .dashboard-content {
  margin-right: 250px;
  margin-left: 0;
}

[dir="rtl"] .icon {
  margin-left: 8px;
  margin-right: 0;
}

/* Arabic font optimization */
[dir="rtl"] {
  font-family: 'Cairo', 'Tajawal', 'Noto Sans Arabic', sans-serif;
}
```

---

## ✅ Integration Checklist

- [ ] Import `getPlatformTranslation` in all vendor components
- [ ] Add `language` prop to all components
- [ ] Replace hardcoded strings with translation keys
- [ ] Add language selector to header
- [ ] Save language preference to localStorage
- [ ] Add RTL support for Arabic
- [ ] Test all views in German
- [ ] Test all views in Arabic
- [ ] Test RTL layout
- [ ] Update all forms
- [ ] Update all buttons
- [ ] Update all notifications

---

## 🚀 Quick Start

1. **Import translation helper:**
   ```typescript
   import { getPlatformTranslation } from '../../utils/platformTranslations';
   ```

2. **Add language prop:**
   ```typescript
   export function MyComponent({ language = 'en' }) {
   ```

3. **Replace strings:**
   ```typescript
   // Before: <h1>Dashboard</h1>
   // After:
   <h1>{getPlatformTranslation('dashboard', language)}</h1>
   ```

4. **Add RTL support:**
   ```typescript
   <div dir={language === 'ar' ? 'rtl' : 'ltr'}>
   ```

---

**Ready to use!** All platform components can now support English, German, and Arabic. 🎉
