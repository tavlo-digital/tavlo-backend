# TAVLO Improvements Implementation Guide

## Status: IN PROGRESS

This document outlines the 7 major improvements requested for the TAVLO system.

---

## ✅ 1. ADMIN NAVIGATION CLEANUP (COMPLETED)

### Changes Made:
- **Removed** "Admin Roles" from left admin navigation (`/components/admin/AdminLayout.tsx`)
- **Removed** `admin-roles` case from AdminApp page types (`/components/admin/AdminApp.tsx`)
- Roles & Permissions now ONLY accessible via: **System Settings → Roles & Permissions** tab

### Files Modified:
- `/components/admin/AdminLayout.tsx` - Removed from menuItems array
- `/components/admin/AdminApp.tsx` - Removed from type and switch case

---

## ✅ 2. VENDOR ONBOARDING FLOW (PARTIALLY COMPLETED)

### Changes Made:
- **Updated** SubscriptionGate heading to: "Choose a subscription to activate your restaurant on Tavlo"
- **Updated** ActivationSuccess heading to: "Subscription Active! Payment confirmed. Let's set up your restaurant..."

### Still TODO:
1. **Registration Flow**:
   - After registration, vendor status = "Registered – Setup Incomplete"
   - Immediately check if subscription exists
   - If NO subscription → Show SubscriptionGate (blocking screen)
   - If YES subscription → Redirect to Setup Checklist

2. **Subscription Enforcement**:
   - Lock ALL vendor features until subscription paid
   - Only allow "Choose Plan" button
   - After payment success → Auto-redirect to Setup Checklist

3. **Setup Checklist** (OnboardingDashboard):
   - Business info
   - Menu
   - Tables & QR
   - Payment methods
   - Appearance

4. **Status Changes**:
   - Registration → "Registered – Setup Incomplete"
   - After subscription → "Active – Setup in Progress"
   - After all checklist complete → "Active – Live"
   - **NO admin approval required**

### Files to Modify:
- `/pages/VendorOnboardingFlow.tsx` - Main flow logic
- `/components/vendor-onboarding/OnboardingDashboard.tsx` - Setup checklist
- `/components/vendor-onboarding/SubscriptionGate.tsx` - ✅ Already updated

---

## 3. LOYALTY & PROMOTIONS (NOT STARTED)

### Required Changes:
1. **Merge into single section**: `Vendor → Loyalty & Promotions`
2. **Remove duplicates** across vendor settings
3. **Add Promotions Management**:
   - Add/edit/pause promotions
   - Promotion types:
     - Happy hour (time-based)
     - Weekend specials
     - Item-based discounts
   - Start & end date/time
   - Customer-visible in app

### Files to Create/Modify:
- Create: `/components/vendor/LoyaltyAndPromotions.tsx` - New combined section
- Modify: Vendor settings navigation to add new section
- Remove: Any duplicate loyalty sections

---

## 4. VENDOR APPEARANCE CUSTOMIZATION (NOT STARTED)

### Required Changes:
Add to `Vendor Settings → Appearance`:
- **Background image upload** (optional)
- **Preview** showing:
  - Customer menu view
  - QR ordering screen
- **Guidelines text**: "Images should be subtle and readable. Tavlo may disable backgrounds that reduce usability."

### Implementation:
- No admin control unless guideline violation
- Store image in Supabase Storage
- Add preview component

### Files to Modify:
- Vendor appearance settings component
- Add image upload UI
- Add preview component

---

## 5. CASH PAYMENT CONFIRMATION FLOW (NOT STARTED)

### Required Changes:
**When customer selects "Pay by Cash":**
1. Order status → "Pending Cash Payment"
2. **Vendor sees**:
   - Highlighted unpaid order
   - Button: "Confirm Cash Received"
3. **After vendor confirms**:
   - Order status → "Paid"
   - Receipt generated
   - Confirmation requires:
     - One-click confirmation
     - Optional note field

**Important**: Admin does NOT confirm payments

### Files to Modify:
- `/components/KitchenDisplay.tsx` - Add cash confirmation button
- `/components/OrderManagement.tsx` - Show pending cash orders differently
- Order status enum - Add "Pending Cash Payment"
- Backend: Add cash confirmation endpoint

---

## 6. ORDER PAYMENT STATUS CLARITY (NOT STARTED)

### Required Changes:
In **Vendor Orders Management**, show clear visual states:
- 🔴 **Unpaid** - Online payment not completed
- 🟡 **Pending Cash** - Waiting for vendor confirmation
- 🟢 **Paid** - Payment received (online or cash confirmed)

**Rule**: Orders remain incomplete until:
- Online payment succeeds OR
- Vendor confirms cash payment

**No automatic completion for unpaid orders**

### Files to Modify:
- `/components/OrderManagement.tsx` - Add payment status badges
- `/components/KitchenDisplay.tsx` - Visual indicators
- Order list views - Status filtering

---

## 7. SUBSCRIPTION-BASED FEATURE ACTIVATION (NOT STARTED)

### Required Changes:

#### Admin Side (`Admin → Subscriptions & Plans`):
Each plan defines enabled features:
- ✓ Loyalty
- ✓ Promotions
- ✓ Multi-language
- ✓ Advanced analytics
- ✓ Custom appearance

Admin can:
- Activate/deactivate features per plan
- Features are subscription-level controls

#### Vendor Side:
**Hide locked features** with upgrade prompt:
> "Upgrade your plan to unlock this feature."

**Show locked state**:
- Grayed out menu items
- Lock icon
- Click → Show upgrade modal

### Implementation:
```typescript
interface SubscriptionPlan {
  id: string;
  name: string;
  price: number;
  features: {
    loyalty: boolean;
    promotions: boolean;
    multiLanguage: boolean;
    advancedAnalytics: boolean;
    customAppearance: boolean;
    multiLocation: boolean;
  };
}
```

### Files to Create/Modify:
- `/components/admin/SubscriptionPlans.tsx` - Feature toggles per plan
- `/components/vendor/FeatureGate.tsx` - Feature lock component
- Vendor navigation - Conditional rendering based on plan
- All vendor feature areas - Wrap with FeatureGate

---

## Implementation Priority

1. ✅ **DONE**: Admin navigation cleanup
2. ✅ **PARTIAL**: Vendor onboarding (messages updated, flow needs completion)
3. **HIGH**: Subscription-based feature activation (affects UX significantly)
4. **HIGH**: Cash payment confirmation (core functionality)
5. **MEDIUM**: Order payment status clarity (improves UX)
6. **MEDIUM**: Loyalty & Promotions merge (reduces confusion)
7. **LOW**: Vendor appearance customization (nice-to-have)

---

## Next Steps

1. Complete vendor onboarding flow logic
2. Implement subscription-based feature gating
3. Add cash payment confirmation
4. Improve order status visuals
5. Merge loyalty/promotions
6. Add appearance customization

---

## Technical Notes

- All changes maintain platform/vendor boundary
- Admin cannot control restaurant operations
- Features are subscription-based, not manually toggled
- Vendor has full autonomy within their subscription limits
- No approval workflows unless compliance issue

---

*Last Updated: December 25, 2024*
