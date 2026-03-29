# 🧪 Vendor Onboarding Testing Guide

## Quick Start (2 minutes)

### **Step 1: Access the Onboarding Flow**

Look at the **top-right corner** of the screen. You'll see a dropdown button showing the current view mode.

1. Click the dropdown button
2. Select **"🚀 Vendor Onboarding"**

That's it! You're now in the vendor onboarding flow.

---

## What to Test

### **1. Vendor Registration (Entry Point)**

**What you'll see:**
- Clean sign-up form
- Business name, country, email, password fields
- No payment or VAT required

**Try this:**
- Business Name: "Bella Italia Vienna"
- Country: Austria
- Email: test@bellaitalia.com
- Password: password123

**Expected result:** Account created, redirected to onboarding dashboard

---

### **2. Onboarding Dashboard (Progress Tracker)**

**What you'll see:**
- Progress bar showing 0% completion
- 3 setup steps:
  1. Restaurant Profile
  2. Menu Setup  
  3. Tables & QR Codes
- "Setup mode" status indicator
- Warning: "Orders and invoices are disabled until you activate your subscription"

**What to notice:**
- ❌ Cannot go live yet (no payment)
- ✅ Can click any step to start setup
- ✅ Steps can be completed in any order

---

### **3. Restaurant Setup (Step 1)**

**Click "Restaurant Profile"**

**What you'll fill:**
- Restaurant name
- Address
- Currency (EUR, USD, GBP, CHF)
- Opening hours for each day

**Try this:**
- Name: Bella Italia Vienna
- Address: Stephansplatz 1, 1010 Wien
- Currency: EUR
- Hours: 09:00 - 22:00 (Monday-Sunday)

**Expected result:** Progress bar now shows 33% complete

---

### **4. Menu Setup (Step 2)**

**Click "Menu Setup"**

**What you can do:**
- Create categories (e.g., "Pasta", "Pizza", "Desserts")
- Add menu items with:
  - Name and description
  - Price
  - VAT rate (10%, 13%, 20%)
- See "Draft" badge
- Preview your menu

**Try this:**
1. Create category "Pasta"
2. Add item:
   - Name: "Spaghetti Carbonara"
   - Price: 12.50
   - VAT: 13%
   - Description: "Classic Roman pasta"
3. Add more items if you want

**Expected result:** Progress bar now shows 66% complete

---

### **5. Tables & QR Setup (Step 3)**

**Click "Tables & QR Codes"**

**What you'll see:**
- Warning: "QR codes are currently inactive"
- Add table form
- QR code previews with "PREVIEW" watermark

**Try this:**
1. Add table "1"
2. Add table "2"  
3. Add table "A1"
4. Try clicking "Download QR" → Should be blocked

**Expected result:**
- Progress bar shows 100% complete
- Warning: "Cannot download QR codes until activated"
- Big green "Activate restaurant" button appears

---

### **6. Subscription Gate (THE PAYWALL) 🔒**

**Click "Activate restaurant"**

**What you'll see:**
- Two pricing plans:
  - **Monthly:** €49/month
  - **Yearly:** €490/year (17% savings, recommended)
- Feature list
- "Subscribe & go live" button

**What to notice:**
- ❌ No way to bypass this screen
- ✅ Clear pricing
- ✅ No admin contact needed

**Try this:**
- Click "Subscribe & go live" on either plan
- Watch the payment simulation (2 seconds)

**Expected result:** Redirected to activation success

---

### **7. Activation Success Screen 🎉**

**What you'll see:**
- Celebration animation
- "You're Live!" message
- 3 action buttons:
  1. Download QR Codes
  2. View Live Menu
  3. Open Orders Dashboard

**What changed:**
- ✅ Vendor status now "Active"
- ✅ QR codes are now downloadable
- ✅ Can accept orders

**Try this:**
- Click all three buttons to see what happens

---

### **8. Legal Data Form (Progressive Disclosure)**

**Access from:** Dashboard after activation

**What you'll fill:**
- Legal entity name
- Legal address  
- VAT number (optional)
- Click "Validate" to check VAT number

**Important notice:**
- ✅ Orders are enabled immediately
- ❌ Invoices are disabled until this is complete

**Try this:**
- Legal Entity Name: "Bella Italia GmbH"
- Address: "Stephansplatz 1, 1010 Wien, Austria"
- VAT: "ATU12345678" (mock validation)
- Click "Validate" → Should show green checkmark

---

## Admin Monitoring

### **How to Access:**

1. Click mode switcher dropdown (top-right)
2. Select **"🛠️ Vendor Management"**

**What you'll see:**
- Table of all vendors
- Status filters (setup / active / suspended)
- Search functionality
- Vendor stats

**What you can do:**
- 👁️ View vendor details
- 🚫 Suspend vendor (admin-only action)
- 📊 Monitor setup progress
- 💳 Check subscription status

**Important:**
- ❌ Admin CANNOT manually activate vendors
- ❌ Admin CANNOT manage payments
- ✅ Admin CAN suspend vendors
- ✅ Admin CAN view all data (read-only)

---

## Key Testing Points

### ✅ **What WORKS:**

1. **Setup without payment** - All steps accessible
2. **Progress tracking** - Visual feedback at each step
3. **Hard paywall** - Cannot activate without subscription
4. **Auto-activation** - Immediate after payment
5. **QR code gating** - Blocked until paid
6. **Progressive disclosure** - Legal data optional initially
7. **Admin monitoring** - Non-blocking, read-only

### ❌ **What's BLOCKED:**

1. **Going live** - Without subscription
2. **Downloading QR codes** - Until vendor is active
3. **Accepting orders** - Until subscription paid
4. **Generating invoices** - Until legal data complete
5. **Bypassing paywall** - No exceptions

---

## State Machine (For Testing Flow)

```
START
  ↓
Register → Setup Mode (status: setup, subscription: none)
  ↓
Complete Steps (any order, all optional)
  ↓
Click "Activate" → PAYWALL (hard stop)
  ↓
Subscribe → Payment Processing
  ↓
Auto-Activation → Live (status: active, subscription: active)
  ↓
Accept Orders → ✅ Enabled
  ↓
(Optional) Add Legal Data → Invoicing ✅ Enabled
```

---

## Testing Checklist

- [ ] Can register new vendor
- [ ] Can access all setup steps without payment
- [ ] Progress bar updates correctly
- [ ] Cannot download QR codes before payment
- [ ] Subscription paywall blocks activation
- [ ] Payment simulation works
- [ ] Vendor auto-activates after payment
- [ ] QR codes become active after payment
- [ ] Legal data form is optional
- [ ] Admin can view all vendors
- [ ] Admin cannot manually activate vendors

---

## Screenshots to Look For

1. **Registration:** Simple 4-field form
2. **Dashboard:** Progress bar + 3 step cards + status badge
3. **Setup Steps:** Clean forms with save/skip options
4. **Paywall:** 2 pricing cards, clear features
5. **Success:** Celebration screen with 3 CTAs
6. **Admin:** Table view with filters

---

## Common Questions

**Q: Can I skip the subscription?**  
A: No. The system enforces payment before going live.

**Q: Can admin activate a vendor manually?**  
A: No. Activation is automatic after payment.

**Q: Can I accept orders without legal data?**  
A: Yes! Legal data is only required for invoicing.

**Q: What happens if payment fails?**  
A: Vendor stays in "setup" mode. Can retry payment.

**Q: Can I edit my menu after going live?**  
A: Yes! Return to "Menu Setup" anytime.

---

## Next Steps After Testing

1. Check `/VENDOR_ONBOARDING_DOCS.md` for full documentation
2. Review backend API endpoints in `/supabase/functions/server/index.tsx`
3. Explore component files in `/components/vendor-onboarding/`
4. Read production integration notes for Stripe setup

---

## Support

If something doesn't work:
1. Check browser console for errors
2. Verify backend routes are accessible
3. Review state changes in React DevTools
4. Check documentation files for expected behavior
