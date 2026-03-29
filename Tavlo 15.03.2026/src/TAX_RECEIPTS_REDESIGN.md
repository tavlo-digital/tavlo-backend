# Tax & Receipts Settings Page - Redesign Documentation

## Overview

The Tax & Receipts settings page has been redesigned to make tax behavior legally clear, remove country selection, and clearly distinguish receipts from invoices.

---

## What Changed

### ✅ **Section 1: Tax System Information (REWORKED)**

**Old Design:**
- Country selector dropdown (AT/DE)
- Editable by vendor
- Could switch between countries

**New Design:**
- **Read-only information block** titled "Applied Tax System"
- Shows: "This restaurant operates under [Austrian/German] VAT law"
- Helper text: "VAT rules are automatically applied by Tavlo based on the restaurant's registered country."
- **No country selector** - country is fixed based on restaurant registration

**Visual:**
- Blue-highlighted section (`bg-blue-50`)
- Bold country name
- Informational, not interactive

**Why:**
- Country is tied to legal jurisdiction, not a preference
- Prevents vendors from accidentally switching to incorrect tax regime
- Reduces legal liability

---

### ✅ **Section 2: VAT Categories (READ-ONLY, Improved Clarity)**

**Changes:**
- Added label: "Legally defined – cannot be edited"
- Added usage count per card: "Used by X menu items"
- Cards remain read-only (Shield icon indicates this)

**VAT Cards:**
1. **Food (Prepared Meals)**
   - Shows VAT rate (e.g., 13% for Austria)
   - Usage count: "Used by 12 menu items"

2. **Non-Alcoholic Beverages**
   - Shows VAT rate (e.g., 13% for Austria)
   - Usage count: "Used by 8 menu items"

3. **Alcoholic Beverages**
   - Shows VAT rate (e.g., 20% for Austria)
   - Usage count: "Used by 5 menu items"

**Technical:**
- `showUsageCount={true}` prop passed to `TaxRulesDisplay`
- Counts are currently mock data (TODO: connect to real menu items)

**Why:**
- Shows vendors which categories are in use
- Helps understand impact of each tax category
- Reinforces that these are legally defined (not editable)

---

### ✅ **Section 3: Service Fee (Clarified Responsibility)**

**Changes:**
- Label changed to: "Optional Service Fee"
- Added helper text: "Service fees must be clearly shown to customers. Tax treatment depends on local regulations. You are responsible for correct usage."
- Input field unchanged (% rate)

**Why:**
- Makes it clear service fee is optional
- Clarifies vendor responsibility for correct usage
- Mentions tax treatment varies by local regulations
- Reduces liability for Tavlo

---

### ✅ **Section 4: Receipt vs Invoice (NEW - Critical)**

**New section** titled "Receipt & Invoice Handling"

**Option:**
- ☑ Automatically generate customer receipt for all orders

**Info text:**
> "Standard receipts are issued by default. VAT invoices may be required upon request depending on local law."

**Behavior:**
- Checkbox controls `autoGenerateReceipts` setting
- Default: checked (enabled)
- Simple, no over-engineering

**Why:**
- Distinguishes receipts from invoices (important legal distinction)
- Receipts are automatic, invoices are on-request
- Clarifies default behavior
- Mentions VAT invoice requirement varies by local law

---

### ✅ **Section 5: Invoice Numbering (Restricted)**

**Fields kept:**
- Invoice Prefix
- Next Invoice Number

**New behavior:**
- **Editable only before first invoice is issued**
- After first invoice: fields become **read-only** (greyed out, disabled)
- Yellow warning box appears when locked

**State:**
```typescript
firstInvoiceIssued: boolean  // NEW field
```

**Warning text when locked:**
> "Locked: Invoice numbering cannot be changed after the first invoice is issued."

**Helper text:**
> "Invoice numbering must be sequential and compliant with local regulations."

**Why:**
- Legal requirement: invoice numbering must be sequential
- Prevents gaps or duplicate numbers
- Once first invoice issued, sequence is locked
- Prevents legal compliance violations

---

### ✅ **Section 6: Compliance Notice (NEW - Minimal)**

**New info box** at bottom of page

**Icon:** Shield icon (`<Shield />`)

**Title:** "Compliance Notice"

**Text:**
> "Tavlo provides digital receipts and tax breakdowns. Local fiscal requirements for cash registers or certified systems may still apply depending on your country."

**Visual:**
- Grey background (`bg-gray-50`)
- Border (`border-gray-300`)
- Shield icon in grey
- Non-intrusive, informational

**Why:**
- Clarifies Tavlo provides receipts and tax breakdowns
- Mentions local fiscal requirements may still apply
- Does NOT reference RKSV, KassenSichV, or hardware (as requested)
- Protects Tavlo from liability
- Informs vendors of potential additional requirements

---

## Visual Layout

```
┌─────────────────────────────────────────────────────────────┐
│                   TAX & RECEIPTS SETTINGS                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  │
│  ┃ 🔵 APPLIED TAX SYSTEM                              ┃  │
│  ┃                                                     ┃  │
│  ┃ This restaurant operates under Austrian VAT law    ┃  │
│  ┃                                                     ┃  │
│  ┃ VAT rules are automatically applied by Tavlo       ┃  │
│  ┃ based on the restaurant's registered country.      ┃  │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ VAT CATEGORIES                                     │    │
│  │ Legally defined – cannot be edited                │    │
│  ├───────────────────────────────────────────────────┤    │
│  │                                                   │    │
│  │  ┌────────┐  ┌────────┐  ┌────────┐             │    │
│  │  │ Food   │  │ Non-   │  │ Alcohol│             │    │
│  │  │ 13% VAT│  │ Alc    │  │ 20% VAT│             │    │
│  │  │        │  │ 13% VAT│  │        │             │    │
│  │  │ Used by│  │        │  │ Used by│             │    │
│  │  │ 12 menu│  │ Used by│  │ 5 menu │             │    │
│  │  │ items  │  │ 8 menu │  │ items  │             │    │
│  │  │        │  │ items  │  │        │             │    │
│  │  └────────┘  └────────┘  └────────┘             │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ OPTIONAL SERVICE FEE                               │    │
│  ├───────────────────────────────────────────────────┤    │
│  │ Service fees must be clearly shown to customers.  │    │
│  │ Tax treatment depends on local regulations.       │    │
│  │ You are responsible for correct usage.            │    │
│  │                                                   │    │
│  │ Service Fee Rate (%): [____5____]                │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ RECEIPT & INVOICE HANDLING                         │    │
│  ├───────────────────────────────────────────────────┤    │
│  │ ☑ Automatically generate customer receipt for     │    │
│  │   all orders                                       │    │
│  │                                                   │    │
│  │   Standard receipts are issued by default. VAT    │    │
│  │   invoices may be required upon request depending │    │
│  │   on local law.                                    │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ INVOICE NUMBERING                                  │    │
│  ├───────────────────────────────────────────────────┤    │
│  │ Invoice Prefix: [_LBV_]  Next Invoice #: [_1001_]│    │
│  │ Example: LBV-1001                                  │    │
│  │                                                   │    │
│  │ [If firstInvoiceIssued = true, fields greyed out]│    │
│  │ ⚠️ Locked: Invoice numbering cannot be changed   │    │
│  │   after the first invoice is issued.              │    │
│  │                                                   │    │
│  │ Invoice numbering must be sequential and          │    │
│  │ compliant with local regulations.                 │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ 🛡️ COMPLIANCE NOTICE                              │    │
│  ├───────────────────────────────────────────────────┤    │
│  │ Tavlo provides digital receipts and tax           │    │
│  │ breakdowns. Local fiscal requirements for cash    │    │
│  │ registers or certified systems may still apply    │    │
│  │ depending on your country.                         │    │
│  └───────────────────────────────────────────────────┘    │
│                                                             │
│  [Cancel]                                 [Save Settings]  │
└─────────────────────────────────────────────────────────────┘
```

---

## Technical Changes

### State Changes

**Added to `taxSettings` state:**
```typescript
firstInvoiceIssued: boolean  // NEW field, default: false
```

**When `firstInvoiceIssued` becomes `true`:**
- Invoice Prefix input becomes disabled
- Next Invoice Number input becomes disabled
- Yellow warning box appears

### Props Changes

**`TaxRulesDisplay` component:**
```typescript
interface TaxRulesDisplayProps {
  country: Country;
  compact?: boolean;
  showUsageCount?: boolean;  // NEW prop
}
```

**`TaxRuleCard` component:**
```typescript
interface TaxRuleCardProps {
  rule: TaxRule;
  country: Country;
  showUsageCount?: boolean;  // NEW prop
}
```

### Component Updates

**Settings.tsx:**
- Removed `<CountrySelector />` usage
- Completely rewrote `renderTaxSettings()` function
- Added `getCountryName()` helper
- Added conditional rendering for locked invoice fields

**TaxRulesDisplay.tsx:**
- Added `showUsageCount` prop support
- Added mock usage counts (TODO: connect to real menu data)
- Added "Used by X menu items" display in card footer

---

## Removed Features

### ❌ Country Selector

**What was removed:**
```tsx
<CountrySelector
  selectedCountry={taxSettings.country}
  onCountryChange={(country) => setTaxSettings({...taxSettings, country})}
  disabled={false}
/>
```

**Why removed:**
- Country is tied to restaurant's legal registration
- Should not be changed casually
- Fixed based on restaurant setup
- Prevents tax jurisdiction errors

**What replaced it:**
- Read-only text: "This restaurant operates under [Country] VAT law"
- No interaction, purely informational

---

## Design Principles

1. **Read-Only Tax Rules**
   - VAT rates cannot be edited by vendors
   - System-managed, legally compliant

2. **Legal Clarity**
   - Clear distinction between receipts and invoices
   - Explicit responsibility notices

3. **Constraint-Based**
   - Country cannot be changed
   - Invoice numbering locked after first use
   - Prevents legal compliance violations

4. **Vendor Education**
   - Helper text explains responsibilities
   - Compliance notice informs of local requirements
   - No over-engineering

5. **Professional Layout**
   - Clean, organized sections
   - Clear visual hierarchy
   - Non-intrusive warnings

---

## User Experience Flow

### Scenario 1: New Restaurant Setup

1. Vendor sees country auto-detected: "Austrian VAT law"
2. Views VAT categories with usage counts (shows 0 for new restaurant)
3. Optionally sets service fee (default 5%)
4. Enables auto-receipt generation (default checked)
5. Sets invoice prefix and starting number (e.g., "LBV-1001")
6. Saves settings
7. First invoice issued → numbering locks

### Scenario 2: Existing Restaurant

1. Vendor sees country: "German VAT law" (cannot change)
2. Views VAT categories showing actual usage:
   - Food: Used by 25 menu items
   - Non-Alcoholic: Used by 12 menu items
   - Alcoholic: Used by 8 menu items
3. Service fee already set (e.g., 10%)
4. Auto-receipts enabled
5. Invoice numbering **greyed out** with yellow warning
6. Reads compliance notice at bottom

---

## Testing Checklist

- [ ] Country displays correctly (AT or DE)
- [ ] No country selector present
- [ ] VAT categories show usage counts
- [ ] Service fee input works
- [ ] Receipt checkbox toggles correctly
- [ ] Invoice fields editable when `firstInvoiceIssued = false`
- [ ] Invoice fields locked when `firstInvoiceIssued = true`
- [ ] Yellow warning appears when invoice numbering locked
- [ ] Compliance notice displays at bottom
- [ ] Save button works correctly
- [ ] Settings persist after page reload

---

## Migration Notes

**For existing restaurants:**
- Country value preserved from existing settings
- `firstInvoiceIssued` defaults to `false` (can be set to `true` if invoices already exist)
- All other settings preserved unchanged

**For new restaurants:**
- Country auto-set based on restaurant registration
- `firstInvoiceIssued = false` (editable until first invoice)
- Default invoice prefix: "LBV"
- Default starting number: 1001

---

## Accessibility

- ✓ All form controls have proper labels
- ✓ Disabled fields clearly indicated
- ✓ Helper text provides context
- ✓ Color is not the only indicator (text + icons)
- ✓ Locked state has text warning, not just visual
- ✓ Info boxes use semantic structure

---

## Legal Compliance

### What Tavlo Guarantees:
✓ Correct VAT rates based on country  
✓ Proper tax breakdown on receipts  
✓ Sequential invoice numbering  
✓ Digital receipt generation  

### What Vendors Are Responsible For:
⚠️ Correct product categorization (food vs alcohol, etc.)  
⚠️ Service fee usage and disclosure  
⚠️ VAT invoice issuance upon customer request  
⚠️ Local fiscal hardware requirements (if applicable)  

### Compliance Notice Protects:
- Tavlo from liability for local cash register laws
- Informs vendors of potential additional requirements
- Does not create false sense of full compliance

---

**Last Updated:** January 2026  
**Version:** 2.0 (Redesigned)
