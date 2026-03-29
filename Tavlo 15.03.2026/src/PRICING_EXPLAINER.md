# Pricing & Tax System Explained

## How VAT and Service Fees Work

### The Inclusive Pricing Model

This system uses **inclusive pricing**, which means:
- Menu prices shown to customers **already include** VAT and service fees
- When you change VAT rate in settings, the **breakdown changes**, not the customer price
- This is standard practice in Europe and required by Austrian law

### Example with Default Settings (13% VAT, 5% Service Fee):

**Menu shows:** Margherita Pizza - €14.50

**This €14.50 breaks down to:**
1. Net amount (food): €12.21
2. Service fee (5%): €0.61
3. VAT (13%): €1.68
4. **Total: €14.50** ✅

### What Happens When You Change VAT Rate?

**Scenario 1: Change VAT from 13% to 20%**

**Menu still shows:** Margherita Pizza - €14.50 (unchanged)

**New breakdown:**
1. Net amount (food): €11.97
2. Service fee (5%): €0.60
3. VAT (20%): €2.51
4. **Total: €14.50** ✅

The customer pays the same €14.50, but now more goes to VAT and less is your net revenue.

**Scenario 2: Change Service Fee from 5% to 10%**

**Menu still shows:** Margherita Pizza - €14.50 (unchanged)

**New breakdown:**
1. Net amount (food): €11.65
2. Service fee (10%): €1.16
3. VAT (13%): €1.68
4. **Total: €14.50** ✅

---

## Where Customers See the Breakdown

### 1. **Receipt Page** (`/components/Receipt.tsx`)

After payment, customers can view their receipt which shows:

```
Tax & Fee Breakdown
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Net amount (food & beverages)    €12.21
Service fee (5%)                  €0.61
VAT (13%)                         €1.68
───────────────────────────────────────
Subtotal (incl. VAT & fees)      €14.50
Tip (gratuity)                    €2.00
═══════════════════════════════════════
Total Amount                     €16.50
```

This breakdown **uses the exact VAT and service fee rates from your settings**.

### 2. **Vendor Invoice** (for business customers)

When you generate an invoice for a business customer, it includes:
- Full company details
- **Invoice number** with your custom prefix (e.g., "LBV-00123")
- Detailed tax breakdown
- VAT ID for both parties
- Compliant with Austrian § 11 UStG

---

## Invoice Prefix Setting

### What is it?

The **invoice prefix** is a short code (usually 2-4 letters) that appears before your invoice numbers.

**Examples:**
- `INV-00001, INV-00002, INV-00003...` (default)
- `LBV-00001, LBV-00002, LBV-00003...` (La Bella Vista)
- `BC-00001, BC-00002, BC-00003...` (Bella Cucina)

### Why use a custom prefix?

1. **Branding** - Use your restaurant's initials
2. **Organization** - Different prefixes for different locations
3. **Accounting** - Easier to identify invoices in your bookkeeping
4. **Professional** - Shows attention to detail

### How to set it:

1. Go to Settings → Tax & Receipts
2. Find "Invoice Prefix"
3. Enter your preferred code (e.g., "LBV")
4. Set "Next Invoice Number" to your starting number
5. Click Save

**From then on:**
- Invoice #1 = `LBV-00001`
- Invoice #2 = `LBV-00002`
- etc.

The system automatically increments the number each time you generate an invoice.

---

## The Math Behind It

### Forward Calculation (Menu → Receipt)

When creating menu prices that include VAT and service fees:

```javascript
// Settings
const vatRate = 13;        // 13%
const serviceFeeRate = 5;  // 5%

// You want to charge €12 net for a dish
const netPrice = 12.00;

// Add service fee
const withServiceFee = netPrice * (1 + serviceFeeRate/100);
// = 12.00 * 1.05 = €12.60

// Add VAT
const grossPrice = withServiceFee * (1 + vatRate/100);
// = 12.60 * 1.13 = €14.24

// Menu shows: €14.24
```

### Reverse Calculation (Receipt → Breakdown)

When displaying breakdown from gross price:

```javascript
// Customer paid (gross)
const grossTotal = 14.24;

// Settings
const vatRate = 13;
const serviceFeeRate = 5;

// Calculate multiplier
const multiplier = (1 + serviceFeeRate/100) * (1 + vatRate/100);
// = 1.05 * 1.13 = 1.1865

// Extract net amount
const netAmount = grossTotal / multiplier;
// = 14.24 / 1.1865 = €12.00

// Calculate fees
const serviceFee = netAmount * (serviceFeeRate/100);
// = 12.00 * 0.05 = €0.60

const vatAmount = (netAmount + serviceFee) * (vatRate/100);
// = 12.60 * 0.13 = €1.64

// Verify
const total = netAmount + serviceFee + vatAmount;
// = 12.00 + 0.60 + 1.64 = €14.24 ✅
```

This reverse calculation happens **automatically** when orders are created, using your current settings.

---

## Testing the System

### To see VAT changes in action:

1. **Create a test order:**
   - Add items to basket
   - Complete payment
   - View receipt

2. **Note the breakdown:**
   - Check the VAT percentage shown
   - Check the service fee percentage

3. **Change settings:**
   - Go to Settings → Tax & Receipts
   - Change VAT from 13% to 20%
   - Change Service Fee from 5% to 8%
   - Click Save

4. **Create another test order:**
   - Add same items
   - Complete payment
   - View receipt

5. **Compare:**
   - Menu prices are the same
   - **Breakdown percentages changed**
   - Net amount changed
   - VAT amount changed
   - Service fee amount changed
   - **Total stayed the same**

---

## Austrian Tax Compliance

### Why this matters:

Austrian law (§ 11 UStG) requires receipts to show:
- ✅ Business registration number
- ✅ VAT ID (UID)
- ✅ Date and time
- ✅ Items purchased
- ✅ VAT breakdown
- ✅ Total amount

This system provides all required information automatically.

### For restaurants:

- **Food**: Usually 13% VAT (reduced rate)
- **Drinks (non-alcoholic)**: 13% VAT
- **Alcohol**: 20% VAT (standard rate)
- **Service**: Can vary, typically 5-10%

**Note:** This system uses a single VAT rate for simplicity. For restaurants serving both food and alcohol, you may need separate categories with different VAT rates.

---

## Currency Support

The system supports multiple currencies:
- **EUR (€)** - Euro (default)
- **USD ($)** - US Dollar
- **GBP (£)** - British Pound
- **CHF (Fr.)** - Swiss Franc

When you change currency in settings:
- All price displays update to show the new symbol
- Receipts use the correct currency
- Invoices show the correct currency
- Formatting adjusts per currency convention

**Example:**
- EUR: €14.50
- USD: $14.50
- GBP: £14.50
- CHF: Fr. 14.50

---

## Common Questions

### Q: Why doesn't changing VAT change menu prices?

**A:** Because prices are **inclusive**. The menu shows what customers pay. VAT only affects how that money is divided between you and the tax authority.

### Q: How do I change actual menu prices?

**A:** Go to Menu Management and edit item prices directly. This is separate from tax settings.

### Q: What happens to old receipts when I change VAT?

**A:** Old receipts are preserved with the VAT rate that was active when the order was created. Only new orders use the new rate.

### Q: Can I have different VAT rates for different items?

**A:** Not in the current version. The system applies one VAT rate to all items. This is sufficient for most restaurants but can be enhanced if needed.

### Q: Do I need to change menu prices when VAT laws change?

**A:** If you want to maintain the same net revenue, yes. If VAT increases from 13% to 15%, you'd need to increase menu prices by ~2% to maintain the same net amount.

---

## Summary

✅ **Menu prices** = What customers see and pay (includes everything)
✅ **VAT & Service Fee settings** = How that money is divided (for receipts/accounting)
✅ **Invoice prefix** = Your custom code for professional invoices (e.g., "LBV-00001")
✅ **Currency** = Symbol displayed (€, $, £, Fr.)
✅ **Receipts** = Show complete breakdown using your current settings
✅ **Invoices** = Sequential numbering with your custom prefix

**The system automatically handles all calculations** - you just set your rates and everything else is computed correctly!
