# TAVLO Vendor Onboarding & Activation Flow

## Overview

This is a production-realistic, self-service vendor onboarding system with subscription-based activation. Vendors can explore and set up their restaurant before paying, but cannot go live or accept orders without an active subscription.

## Core Principle

✅ **Vendors can explore and set up before paying**  
✅ **Vendors cannot go live or accept orders without an active subscription**  
✅ **Payment enforcement is system-driven, not admin-driven**  
✅ **Admin intervenes only for exceptions, not normal onboarding**

---

## Flow Architecture

### 1. Vendor Registration (Entry Point)
**File:** `/components/vendor-onboarding/VendorRegistration.tsx`

**Fields:**
- Business name
- Country
- Email
- Password

**UX:**
- Clean, minimal design
- No VAT, documents, or payment required
- < 2 minutes to complete
- Instant account creation

**Backend:** `POST /make-server-1dccd8d3/vendor/register`

---

### 2. Onboarding Dashboard (Progress Tracker)
**File:** `/components/vendor-onboarding/OnboardingDashboard.tsx`

**Shows:**
- Progress bar (0-100%)
- Setup steps checklist:
  1. Restaurant profile
  2. Menu setup
  3. Tables & QR codes
- Status labels: "Setup mode" / "Not live yet"
- Disabled state explanation

**Key Features:**
- Visual progress tracking
- Non-blocking navigation (can skip steps)
- Clear activation call-to-action when ready

---

### 3. Restaurant Setup (Step 1)
**File:** `/components/vendor-onboarding/RestaurantSetup.tsx`

**Fields:**
- Restaurant name
- Address
- Currency (EUR, USD, GBP, CHF)
- Opening hours per day

**UX:**
- Simple form layout
- Can be completed later
- Saved to KV store: `vendor.restaurantData`

**Backend:** `PUT /make-server-1dccd8d3/vendor/:vendorId/progress`

---

### 4. Menu Setup (Step 2)
**File:** `/components/vendor-onboarding/MenuSetup.tsx`

**Features:**
- Create categories
- Add menu items
- Set prices and VAT rates (10%, 13%, 20%)
- Upload images (future)
- Live preview with "Draft" badge

**UX:**
- Sidebar category navigation
- Inline editing
- Quick item addition
- Visual organization

**Backend:** `PUT /make-server-1dccd8d3/vendor/:vendorId/progress`

---

### 5. Tables & QR Setup (Step 3)
**File:** `/components/vendor-onboarding/TablesQRSetup.tsx`

**Features:**
- Add tables (any numbering system)
- Generate QR codes
- Preview QR codes (watermarked when inactive)
- Cannot download active QR codes until subscribed

**Status Indicators:**
- Inactive: Shows "PREVIEW" watermark
- Active: Full QR code download enabled

**Backend:** `PUT /make-server-1dccd8d3/vendor/:vendorId/progress`

---

### 6. Subscription Gate (Critical Paywall) 🔒
**File:** `/components/vendor-onboarding/SubscriptionGate.tsx`

**Triggered When:**
- Vendor clicks "Activate restaurant"
- Vendor clicks "Download live QR"

**Shows:**
- Plan options (Monthly / Yearly)
- Clear pricing with VAT awareness
- Feature comparison
- What's included

**Plans:**
- **Monthly:** €49/month
- **Yearly:** €490/year (17% savings)

**UX Rules:**
- Hard stop (no bypass)
- Clear value framing
- No admin contact required
- Stripe Checkout redirect

**Backend:** `POST /make-server-1dccd8d3/vendor/:vendorId/subscribe`

---

### 7. Stripe Checkout (External Flow)

**Integration:**
In production, this would:
1. Create Stripe customer
2. Create Stripe subscription
3. Redirect to Stripe Checkout
4. Handle webhook for payment confirmation
5. Auto-activate vendor on success

**Demo Mode:**
- Simulates payment success after 2 seconds
- Updates vendor status immediately

---

### 8. Automatic Activation (Post-Payment) ✅
**File:** `/components/vendor-onboarding/ActivationSuccess.tsx`

**System Actions:**
- Vendor status → `active`
- Restaurant → Live
- QR codes → Active (isActive: true)
- Orders → Enabled

**Shows:**
- Success celebration
- Next steps:
  1. Download QR codes
  2. View live menu
  3. Open orders dashboard

**Backend:** `POST /make-server-1dccd8d3/vendor/:vendorId/activate`

---

### 9. Legal & VAT Data (Progressive Disclosure)
**File:** `/components/vendor-onboarding/LegalDataForm.tsx`

**Fields:**
- Legal entity name
- Legal address
- VAT number (optional)
- VAT validation status

**System Rules:**
- ✅ Orders allowed immediately after activation
- ❌ Invoices blocked until legal data complete
- VAT number validation (EU VIES system in production)

**UX:**
- Not required for initial activation
- Can be completed later
- Clear explanations of why it's needed

**Backend:** `PUT /make-server-1dccd8d3/vendor/:vendorId/legal`

---

### 10. Admin Monitoring (Non-Blocking)
**File:** `/components/admin/VendorMonitoring.tsx`

**Features:**
- View all vendors
- Filter by status (setup / active / suspended)
- Search by name or email
- Monitor setup progress
- View subscription status

**Admin Actions:**
- View vendor details (read-only)
- Suspend vendor (exception only)
- Request documents
- View audit log

**Important:**
- ❌ Admin does NOT activate vendors
- ❌ Admin does NOT manage payments
- ✅ Admin only handles exceptions

**Backend:** `GET /make-server-1dccd8d3/admin/vendors`

---

## Backend API Endpoints

### Vendor Registration
```
POST /make-server-1dccd8d3/vendor/register
Body: { businessName, country, email, password }
Response: { vendorId }
```

### Get Vendor Status
```
GET /make-server-1dccd8d3/vendor/:vendorId/status
Response: { vendor object with all data }
```

### Update Setup Progress
```
PUT /make-server-1dccd8d3/vendor/:vendorId/progress
Body: { step: 'restaurant' | 'menu' | 'tables', data: {...} }
Response: { message, vendor }
```

### Create Subscription
```
POST /make-server-1dccd8d3/vendor/:vendorId/subscribe
Body: { planId: 'monthly' | 'yearly' }
Response: { subscriptionId }
```

### Activate Vendor
```
POST /make-server-1dccd8d3/vendor/:vendorId/activate
Response: { message, vendor }
```

### Suspend Vendor (Admin)
```
POST /make-server-1dccd8d3/vendor/:vendorId/suspend
Body: { reason: string }
Response: { message, vendor }
```

### Get All Vendors (Admin)
```
GET /make-server-1dccd8d3/admin/vendors
Response: { vendors: [...] }
```

### Save Legal Data
```
PUT /make-server-1dccd8d3/vendor/:vendorId/legal
Body: { legalEntityName, legalAddress, vatNumber }
Response: { message, invoicingEnabled }
```

---

## Data Model

### Vendor Object (KV Store)
```typescript
{
  id: string;
  businessName: string;
  country: string;
  email: string;
  status: 'setup' | 'active' | 'suspended';
  subscriptionStatus: 'none' | 'active' | 'past_due' | 'canceled';
  subscriptionPlan: 'monthly' | 'yearly' | null;
  subscriptionId?: string;
  setupProgress: {
    restaurant: boolean;
    menu: boolean;
    tables: boolean;
  };
  restaurantData?: {
    name: string;
    address: string;
    currency: string;
    openingHours: {...};
  };
  menuData?: MenuCategory[];
  tablesData?: Table[];
  legalData?: {
    legalEntityName: string;
    legalAddress: string;
    vatNumber: string;
    vatValidated: boolean;
  };
  legalDataComplete?: boolean;
  invoicingEnabled?: boolean;
  createdAt: string;
  activatedAt?: string;
  subscribedAt?: string;
  suspendedAt?: string;
  suspensionReason?: string;
}
```

---

## Status State Machine

```
Registration → Setup Mode (status: 'setup', subscriptionStatus: 'none')
    ↓
Complete setup steps (restaurant, menu, tables)
    ↓
Click "Activate restaurant" → Subscription Gate
    ↓
Subscribe (Stripe) → subscriptionStatus: 'active'
    ↓
Auto-activation → status: 'active'
    ↓
Live & Accepting Orders ✅
    ↓ (optional)
Add legal data → invoicingEnabled: true
```

---

## UX Copy Tone

**Clear, Neutral, Professional**

✅ Good Examples:
- "You're almost live"
- "Subscription required to activate"
- "You can complete this later"
- "Invoices are disabled until legal data is provided"

❌ Avoid:
- "Amazing! You're a rockstar!"
- "Join thousands of restaurants!"
- "Limited time offer!"
- "Act now!"

---

## Production Integration Notes

### Stripe Integration
1. Create Stripe customer on vendor registration
2. Use Stripe Checkout for subscription payment
3. Set up webhooks for:
   - `checkout.session.completed`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
   - `customer.subscription.deleted`
4. Handle subscription lifecycle events

### VAT Validation
1. Integrate EU VIES API for VAT number validation
2. Cache validation results
3. Re-validate periodically (monthly)

### QR Code Generation
1. Generate actual QR codes using `qrcode` library
2. Embed restaurant and table data
3. Serve via signed URLs
4. Watermark inactive QR codes

### Email Notifications
1. Welcome email after registration
2. Setup progress reminders
3. Subscription confirmation
4. Payment receipts
5. Activation success

---

## Security Considerations

1. **Authentication:** All vendor endpoints require valid JWT token
2. **Authorization:** Vendors can only access their own data
3. **Admin Routes:** Require admin role verification
4. **Payment Webhooks:** Verify Stripe signature
5. **VAT Validation:** Rate limit to prevent abuse

---

## Testing Checklist

- [ ] Vendor can register
- [ ] Vendor can complete setup without payment
- [ ] Vendor is blocked from downloading QR codes without subscription
- [ ] Vendor is blocked from going live without subscription
- [ ] Subscription payment activates vendor automatically
- [ ] QR codes become active after payment
- [ ] Legal data is optional for orders
- [ ] Legal data is required for invoices
- [ ] Admin can view all vendors
- [ ] Admin can suspend vendors
- [ ] Admin cannot manually activate vendors

---

## Files Created

### Components
- `/components/vendor-onboarding/VendorRegistration.tsx`
- `/components/vendor-onboarding/OnboardingDashboard.tsx`
- `/components/vendor-onboarding/RestaurantSetup.tsx`
- `/components/vendor-onboarding/MenuSetup.tsx`
- `/components/vendor-onboarding/TablesQRSetup.tsx`
- `/components/vendor-onboarding/SubscriptionGate.tsx`
- `/components/vendor-onboarding/ActivationSuccess.tsx`
- `/components/vendor-onboarding/LegalDataForm.tsx`
- `/components/admin/VendorMonitoring.tsx`

### Pages
- `/pages/VendorOnboardingFlow.tsx` - Main orchestration
- `/pages/AdminVendorManagement.tsx` - Admin monitoring

### Backend
- `/supabase/functions/server/index.tsx` - 8 new endpoints added

---

## Next Steps

1. **Integrate with App.tsx routing**
2. **Connect to real Stripe account**
3. **Implement actual QR code generation**
4. **Add email notifications**
5. **Set up Stripe webhooks**
6. **Implement VAT validation**
7. **Add vendor dashboard post-activation**
8. **Create invoice generation system**

---

## Demo Usage

To test the onboarding flow:

1. Navigate to `/vendor-onboarding` in your app
2. Register with demo credentials
3. Complete setup steps (all optional)
4. Click "Activate restaurant"
5. Choose a subscription plan
6. Payment simulation (2 seconds)
7. View activation success screen

Admin monitoring:
1. Navigate to `/admin/vendors`
2. View all registered vendors
3. Monitor setup progress
4. Filter and search vendors
