# Payment Settings Page - Redesign Documentation

## Overview

The Payment Settings page has been redesigned to make payment behavior explicit, reduce legal ambiguity, and clearly separate payment methods from payment flow.

---

## What Changed

### ✅ **Added: Section 1 - Payment Collection Model (Top Priority)**

**Location:** Top of Payment Settings page  
**Visual:** Blue-highlighted section with radio buttons

**Purpose:** Defines how payments are collected and how orders are confirmed.

**Options:**
1. **Customers pay on-site at the restaurant**
   - Orders are placed via Tavlo and paid directly at the restaurant (cash or external terminal)
   - Best for: Traditional restaurants that handle payments at the table or counter

2. **Customers pay online via Tavlo**
   - Customers pay online during checkout
   - Tavlo handles the payment process
   - Best for: Online ordering, delivery, pre-payment scenarios

**Behavior:**
- Only ONE option can be active at a time
- This choice controls which payment methods are available in subsequent sections

---

### ✅ **Restructured: Section 2 - Available Payment Methods**

**Old Name:** "Accepted Payment Methods"  
**New Name:** "Available Payment Methods"

**Changes:**
- Payment methods now adapt based on the Payment Collection Model chosen above
- Digital wallets (Apple Pay, Google Pay) are now grouped under "Card Payments (Digital Wallets)"
- Credit/Debit Card is labeled as "Card Payments"

**Conditional Display:**
- **If "on-site" selected:** Only shows Cash options
- **If "online" selected:** Only shows digital payment options (Apple Pay, Google Pay, Card)

**Payment Methods:**
- ✓ Card Payments (Digital Wallets)
  - Apple Pay (checkbox)
  - Google Pay (checkbox)
- ✓ Card Payments (Credit / Debit Card)
- ✓ Cash (Dine-in)
- ✓ Cash for Takeaway Orders

---

### ✅ **Enhanced: Section 3 - Cash Handling**

**Added helper text** to both cash options explaining behavior:

**Cash (Dine-in)**
- Helper text: "Customer pays at the restaurant. Order is confirmed immediately."

**Cash for Takeaway Orders**
- Helper text: "Customer pays when picking up the order. Order may require manual confirmation by the restaurant."

---

### ✅ **Added: Section 4 - Customer-Facing Preview**

**Location:** Below payment methods  
**Visual:** Grey box with green checkmarks

**Title:** "What Customers Will See"

**Purpose:** Shows vendors exactly what payment options customers will see based on current settings.

**Example Output:**
```
✓ Dine-in: Pay at restaurant
✓ Takeaway: Cash on pickup available
✓ Online payments: Card, Apple Pay, Google Pay
```

**Behavior:**
- Updates dynamically as checkboxes and radio buttons change
- Shows "No payment methods configured" if nothing is selected

---

### ✅ **Modified: Section 5 - Currency**

**Changes:**
- Currency field is now **read-only** (greyed out)
- Cannot be changed by vendor

**Helper text added:**
> "Currency is defined by the restaurant's country and cannot be changed."

**Why:** Currency is tied to the restaurant's legal jurisdiction and tax requirements, not a payment preference.

---

### ❌ **Removed: Stripe Integration Section**

**What was removed:**
- "Stripe Integration" heading
- "Enable Stripe for online payments" checkbox
- Stripe API key inputs (Public Key, Secret Key)

**Why removed:**
- Per requirements: "Do NOT show Stripe or any PSP name"
- Payments are handled between Tavlo and the PSP
- Vendors don't need to see PSP-level details

**What this means:**
- Payment processing is now abstracted away
- Vendors only choose "online via Tavlo" or "on-site"
- Tavlo handles all PSP integration internally

---

## User Experience Flow

### Scenario 1: Traditional Restaurant (On-Site Payments)

1. Vendor selects: **"Customers pay on-site at the restaurant"**
2. Payment methods shown:
   - ✓ Cash (Dine-in)
   - ✓ Cash for Takeaway Orders
3. Customer preview shows:
   - "Dine-in: Pay at restaurant"
   - "Takeaway: Cash on pickup available"
4. Currency: EUR (read-only)

### Scenario 2: Online Ordering Restaurant

1. Vendor selects: **"Customers pay online via Tavlo"**
2. Payment methods shown:
   - Card Payments (Digital Wallets)
     - ✓ Apple Pay
     - ✓ Google Pay
   - ✓ Card Payments (Credit/Debit)
3. Customer preview shows:
   - "Online payments: Card, Apple Pay, Google Pay"
4. Currency: EUR (read-only)

---

## Technical Changes

### State Changes

**Added to `paymentSettings` state:**
```typescript
paymentCollectionModel: 'on-site' | 'online'  // NEW field
```

**Removed from state:**
```typescript
stripeEnabled: boolean          // REMOVED
stripePublicKey: string         // REMOVED
stripeSecretKey: string         // REMOVED
```

### Function Changes

**`renderPaymentSettings()` completely rewritten:**
- Added `getCustomerFacingPreview()` helper function
- Conditional rendering based on `paymentCollectionModel`
- Removed all Stripe-related UI

---

## Design Principles

1. **Explicit over Implicit**
   - Payment flow is now a conscious choice (radio buttons)
   - No hidden or assumed behavior

2. **Legal Clarity**
   - Clear separation between "on-site" and "online" payments
   - Helps vendors understand their legal obligations

3. **PSP Abstraction**
   - No mention of Stripe or any payment provider
   - Vendors work with Tavlo, not PSPs

4. **Customer-Centric**
   - Preview shows exactly what customers see
   - Reduces vendor confusion about customer experience

5. **Constraint-Based**
   - Currency cannot be changed (tied to country)
   - Payment methods adapt to collection model
   - Prevents invalid configurations

---

## Visual Hierarchy

1. **Blue Box (Top)** - Payment Collection Model (most important decision)
2. **White Sections** - Available payment methods (conditional)
3. **Grey Box** - Customer preview (read-only feedback)
4. **Disabled Field** - Currency (informational)

---

## Testing Checklist

- [ ] Select "on-site" → Only cash options appear
- [ ] Select "online" → Only digital payment options appear
- [ ] Toggle payment methods → Customer preview updates
- [ ] Currency field is greyed out and cannot be changed
- [ ] No mention of "Stripe" anywhere
- [ ] Save button works correctly
- [ ] Settings persist after page reload

---

## Migration Notes

**For existing restaurants:**
- Default `paymentCollectionModel` is `'on-site'`
- All existing payment method checkboxes preserved
- Stripe settings removed from UI (but can remain in backend for backward compatibility)

**For new restaurants:**
- Must choose payment collection model explicitly
- Currency auto-set based on restaurant country
- No Stripe configuration needed

---

## Accessibility

- ✓ All form controls have proper labels
- ✓ Radio buttons use semantic HTML
- ✓ Helper text provides context
- ✓ Disabled fields clearly indicated
- ✓ Preview updates provide feedback

---

**Last Updated:** January 2026  
**Version:** 2.0 (Redesigned)
