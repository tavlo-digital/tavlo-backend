# Settings Integration Map

This document outlines how vendor settings from the Settings page are connected to various parts of the QR-based restaurant ordering system.

## ✅ Fully Integrated Settings

### 1. **Payment Settings** → Payment Flow
**Location:** `/components/PaymentFlow.tsx`

- `acceptApplePay` - Controls visibility of Apple Pay button
- `acceptGooglePay` - Controls visibility of Google Pay button  
- `acceptCard` - Controls visibility of Credit/Debit Card button
- `acceptCash` - Controls visibility of Cash payment option

**How it works:**
- Settings passed via `vendorSettings` prop
- `isPaymentMethodEnabled()` function checks each method
- Payment buttons conditionally rendered based on settings
- Disabled methods are completely hidden from customers

---

### 2. **Currency Settings** → All Price Displays
**Locations:** 
- `/components/DishCard.tsx` - Menu item cards
- `/components/MenuList.tsx` - Menu page
- More components can be updated similarly

**Settings:**
- `currency` - Controls which currency symbol is displayed (EUR, USD, GBP, CHF)

**How it works:**
- `getCurrencySymbol()` function converts currency code to symbol
- €, $, £, Fr. displayed based on setting
- Currency symbol passed to child components via props
- Consistent formatting across all price displays

---

### 3. **Tax & Compliance Settings** → Order Calculations
**Location:** `/supabase/functions/server/index.tsx` - Order creation endpoint

**Settings:**
- `vatRate` - VAT percentage (default: 13% for Austrian food)
- `serviceFeeRate` - Service fee percentage (default: 5%)

**How it works:**
- Settings loaded when order is created
- Reverse calculation: `netAmount = grossTotal / ((1 + serviceFee/100) * (1 + vat/100))`
- VAT and service fees properly broken down for receipts
- Compliant with Austrian tax law requirements

**Formula:**
```javascript
const settings = await kv.get(`vendor:${restaurantId}:settings`);
const vatRate = settings.vatRate || 13;
const serviceFeeRate = settings.serviceFeeRate || 5;
const multiplier = (1 + serviceFeeRate/100) * (1 + vatRate/100);
const netAmount = grossTotal / multiplier;
```

---

### 4. **Review Settings** → Review System
**Location:** `/supabase/functions/server/index.tsx` - Review creation endpoint

**Settings:**
- `enableReviews` - Master switch for review system
- `moderateReviews` - Reviews require approval before publishing
- `minOrderToReview` - Minimum order amount to submit reviews
- `allowAnonymousReviews` - Allow guest reviews without account
- `showReviewsPublicly` - Display reviews on menu (to be implemented)

**How it works:**
- Settings checked when review is submitted
- Returns 403 error if reviews disabled
- Validates order amount against minimum requirement
- Sets review status to 'pending' or 'approved' based on moderation setting
- Blocks anonymous reviews if disabled

**Validation checks:**
```javascript
if (settings.enableReviews === false) {
  return c.json({ error: 'Reviews are currently disabled' }, 403);
}

if (order.total < settings.minOrderToReview) {
  return c.json({ error: 'Order minimum not met' }, 403);
}

review.status = settings.moderateReviews ? 'pending' : 'approved';
```

---

### 5. **Settings Persistence** → Backend Storage
**Locations:**
- `/supabase/functions/server/index.tsx` - Settings endpoints
- `/utils/api.ts` - API methods

**Endpoints:**
- `GET /vendor/:id/settings` - Load vendor settings
- `PUT /vendor/:id/settings` - Save vendor settings

**How it works:**
- Settings stored in key-value store: `vendor:{vendorId}:settings`
- Default values provided if no settings exist
- All settings saved as single JSON object
- Real-time updates via event system

---

### 6. **Settings Context** → Frontend State Management
**Location:** `/contexts/SettingsContext.tsx`

**Features:**
- Centralized settings state management
- Automatic loading on restaurant initialization
- Helper functions for common operations:
  - `getCurrencySymbol()` - Get symbol for currency
  - `formatPrice(amount)` - Format price with currency
  - `isPaymentMethodEnabled(method)` - Check if payment method active
- Event listener for settings updates
- Refreshes when settings saved

**Usage:**
```javascript
// In App.tsx
const [vendorSettings, setVendorSettings] = useState(null);

// Load on init
const settings = await api.getVendorSettings(restaurantId);
setVendorSettings(settings);

// Listen for updates
window.addEventListener('settings-updated', handleRefresh);

// Pass to components
<PaymentFlow vendorSettings={vendorSettings} />
<MenuList vendorSettings={vendorSettings} />
```

---

## 🚧 Ready for Integration (Infrastructure in Place)

### 7. **Business Hours** → Order Availability
**Setting:** `businessHours` - Operating hours for each day

**To implement:**
- Check current day and time against business hours
- Display "Closed" message when outside hours
- Optionally disable ordering during closed hours
- Show next opening time

---

### 8. **Table Settings** → Session Management
**Settings:**
- `enableSharedBasket` - Allow multiple guests to share basket
- `maxGuestsPerTable` - Limit number of people per table

**To implement:**
- Validate number of people against `maxGuestsPerTable`
- Show/hide shared basket option based on `enableSharedBasket`
- Update QR landing page to respect settings

---

### 9. **Ordering Settings** → Order Flow
**Settings:**
- `autoAcceptOrders` - Auto-accept vs manual confirmation
- `estimatedPrepTime` - Default prep time for orders
- `allowGuestOrdering` - Enable/disable guest checkout
- `requirePhoneNumber` - Make phone number mandatory
- `minOrderAmount` - Minimum order value
- `maxOrderAmount` - Maximum order value

**To implement:**
- Validate order total against min/max amounts
- Update order status to 'preparing' if auto-accept enabled
- Hide guest option if `allowGuestOrdering` is false
- Make phone field required based on setting
- Use `estimatedPrepTime` for ETA calculation

---

### 10. **Language Settings** → Localization
**Settings:**
- `defaultLanguage` - Default language code
- `supportedLanguages` - Array of enabled languages
- `dateFormat` - Date formatting (DD.MM.YYYY, MM/DD/YYYY, etc.)
- `timeFormat` - 12h vs 24h time

**To implement:**
- Filter language selector to show only supported languages
- Set default language on app load
- Format all dates/times according to settings
- Show/hide languages in selector

---

### 11. **Loyalty Settings** → Loyalty Program
**Settings:**
- `enableLoyalty` - Master switch for loyalty
- `pointsPerEuro` - Points earned per euro spent
- `minimumRedemption` - Min points to redeem
- `pointsExpiry` - Days until points expire
- `enableTiers` - Bronze/Silver/Gold tiers

**To implement:**
- Calculate and award loyalty points on order completion
- Show loyalty points in user profile
- Allow redemption if above minimum
- Implement tier system if enabled
- Track expiry dates

---

### 12. **Notification Settings** → Backend Notifications
**Settings:**
- `emailNewOrder` - Email on new order
- `emailReview` - Email on new review
- `smsNewOrder` - SMS on new order
- `notificationEmail` - Email address for notifications

**To implement:**
- Trigger email/SMS based on settings
- Use configured email address
- Queue notifications for processing
- Respect user preferences

---

### 13. **Privacy Settings** → Data Management
**Settings:**
- `dataRetentionDays` - Days before auto-deletion
- `gdprCompliant` - GDPR compliance toggle
- `showInTopCustomers` - Display analytics
- `allowDataExport` - Enable data export

**To implement:**
- Scheduled job to delete old data
- Data export functionality
- Respect privacy in analytics
- GDPR-compliant data handling

---

### 14. **Company Information** → Receipts & Invoices
**Settings:**
- `restaurantName` - Business name
- `businessRegNumber` - Registration number
- `vatNumber` - VAT ID
- `address` - Business address
- `phone`, `email`, `website` - Contact info
- `companyType` - GmbH, AG, etc.

**To implement:**
- Include in generated receipts
- Display on invoices
- Show in footer of customer-facing pages
- Use in legal documents

---

## 🔄 Settings Update Flow

```
┌─────────────────┐
│  Vendor opens   │
│ Settings Page   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ GET /vendor/:id │◄─────── Loads current settings
│   /settings     │         from KV store
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Vendor modifies │
│    settings     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Clicks "Save"  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ PUT /vendor/:id │◄─────── Saves all settings
│   /settings     │         to KV store
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│    Dispatch     │◄─────── Event: 'settings-updated'
│  custom event   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  App listens &  │◄─────── Reloads settings from API
│    refreshes    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  All components │◄─────── Receive updated settings
│ get new settings│         via props/context
└─────────────────┘
```

---

## 📊 Settings Impact Summary

| Setting Category | Components Affected | Backend Impact | Customer Impact |
|-----------------|-------------------|----------------|-----------------|
| Payment Methods | PaymentFlow | None | Changes available payment options |
| Currency | DishCard, MenuList, BasketView | None | Changes currency symbol shown |
| Tax Rates | None | Order calculation | Proper tax breakdown in receipts |
| Reviews | ReviewForm | Review validation | Can/cannot submit reviews |
| Business Hours | QRLanding, Menu | None | Ordering availability |
| Table Settings | QRLanding | Session validation | Shared basket availability |
| Ordering | BasketView, PaymentFlow | Order validation | Min/max amounts, guest checkout |
| Loyalty | UserProfile | Points calculation | Points earned/redeemed |
| Language | All components | None | Available languages |
| Notifications | None | Email/SMS triggers | Vendor gets notified |
| Privacy | All | Data retention | Data handling |
| Company Info | Receipt, Invoice | Invoice generation | Receipt details |

---

## 🎯 Quick Implementation Checklist

### Already Implemented ✅
- [x] Settings backend endpoints (GET/PUT)
- [x] Settings persistence in KV store
- [x] Settings loading in App.tsx
- [x] Payment method filtering in PaymentFlow
- [x] Currency display in MenuList/DishCard
- [x] Tax calculation using settings
- [x] Review validation using settings
- [x] Settings update event system

### Priority Integrations 🔥
- [ ] Business hours check before ordering
- [ ] Order amount validation (min/max)
- [ ] Guest ordering toggle
- [ ] Shared basket setting respect
- [ ] Auto-accept orders toggle
- [ ] Phone number requirement
- [ ] Loyalty points calculation

### Medium Priority 📋
- [ ] Language filtering based on supported languages
- [ ] Date/time formatting using settings
- [ ] Notification triggers
- [ ] Company info in receipts
- [ ] Review display based on showReviewsPublicly

### Low Priority 📝
- [ ] Data retention automation
- [ ] Privacy export functionality
- [ ] Loyalty tier system
- [ ] Theme/appearance settings

---

## 🔗 Key Files Reference

**Settings Management:**
- `/components/vendor/Settings.tsx` - Settings UI
- `/contexts/SettingsContext.tsx` - Settings context (created but not used yet)
- `/supabase/functions/server/index.tsx` - Settings endpoints (lines 642-745)
- `/utils/api.ts` - Settings API methods

**Integration Points:**
- `/components/PaymentFlow.tsx` - Payment methods
- `/components/DishCard.tsx` - Currency display
- `/components/MenuList.tsx` - Currency helper
- `/App.tsx` - Settings loading & distribution

**Backend Validation:**
- Order creation: Lines 255-334
- Review creation: Lines 410-500
- Tax calculation: Lines 267-286

---

## 💡 Best Practices

1. **Always provide defaults** - If settings aren't loaded, use sensible defaults
2. **Real-time updates** - Use event system to propagate changes immediately
3. **Validation** - Check settings in backend for security
4. **Type safety** - Define proper interfaces for settings
5. **Documentation** - Comment why each setting exists
6. **User feedback** - Toast notifications when settings saved
7. **Error handling** - Graceful degradation if settings fail to load

---

**Last Updated:** Current build
**Status:** Core functionality integrated, additional features ready for implementation
