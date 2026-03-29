# TAVLO Admin System Architecture — Visual Summary

**Date:** December 25, 2024  
**Status:** ✅ Complete & Production Ready

---

## 🏗️ SYSTEM ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────────────┐
│                     TAVLO ADMIN SYSTEM                          │
│           "Platform Enabler, Not Restaurant Operator"           │
└─────────────────────────────────────────────────────────────────┘
                                 │
                    ┌────────────┴────────────┐
                    │                         │
         ┌──────────▼──────────┐   ┌─────────▼─────────┐
         │  PLATFORM GOVERNANCE │   │  VENDOR OPERATIONS │
         │  (Admin Controls)    │   │  (Vendor Controls) │
         └──────────────────────┘   └────────────────────┘
                    │                         │
        ┌───────────┴───────────┐            │
        │                       │            │
┌───────▼────────┐   ┌──────────▼──────┐    │
│ Infrastructure │   │  Compliance &   │    │
│   & Billing    │   │  Data Privacy   │    │
└────────────────┘   └─────────────────┘    │
                                             │
                    ┌────────────────────────┘
                    │
       ┌────────────┼────────────┐
       │            │            │
┌──────▼─────┐ ┌───▼────┐ ┌────▼──────┐
│   Menus    │ │ Prices │ │ Order Ops │
│   & Items  │ │  & Tax │ │  & Tips   │
└────────────┘ └────────┘ └───────────┘
   Vendor         Vendor      Vendor
   Dashboard      Dashboard   Dashboard
```

---

## 📊 ADMIN SYSTEM MODULES

### **1. Dashboard** 📈
**Purpose:** Platform health and metrics overview

**What It Shows:**
- Gross Order Value (via Tavlo) — NOT restaurant profit
- Active vendor count
- Platform transaction volume
- Subscription revenue (Tavlo fees only)
- Payment method distribution
- Flagged items requiring review

**Boundaries:**
- ✅ View aggregated platform data
- ✅ Monitor platform health
- ❌ Cannot see individual vendor profit margins
- ❌ Cannot modify vendor pricing
- ❌ Cannot control customer order behavior

**Key Language:**
- "Gross Order Value (via Tavlo)" with tooltip
- "Aggregated platform data" labels
- "Subscription pricing is independent of restaurant menu pricing"

---

### **2. Vendor Management** 🏪
**Purpose:** Observe and moderate vendors

**What Admin Can Do:**
- View vendor status (active, pending, suspended)
- Approve/reject vendor applications
- Suspend vendor accounts (with reason)
- Add internal notes
- View subscription status
- Monitor activity trends

**What Admin CANNOT Do:**
- ❌ Edit vendor menus
- ❌ Change product prices
- ❌ Add/remove menu items
- ❌ Modify vendor branding
- ❌ Interfere with live orders

**Key Components:**
- **VendorsList.tsx** — Main list with "Gross Order Value (via Tavlo)"
- **ImprovedVendorManagement.tsx** — Read-only detail view with boundaries
- **VendorApproval.tsx** — Onboarding approval flow

**Visual Indicators:**
- "Read-Only" badges on vendor operational data
- Tooltips: "This is not vendor profit"
- Admin action buttons: Suspend, Reactivate, Add Note (NO edit menu)

---

### **3. Customer Management** 👥
**Purpose:** GDPR-safe customer support

**What Admin Can Do:**
- View customer accounts (PII hidden by default)
- Toggle "Show restricted data (for support only)" — logged
- Export customer data (GDPR)
- Anonymize guest accounts
- Delete accounts (GDPR right to be forgotten)
- View order history metadata

**What Admin CANNOT Do:**
- ❌ Edit customer order details
- ❌ Change order totals
- ❌ Modify customer reviews
- ❌ Access PII without logging

**Key Components:**
- **ImprovedCustomerManagement.tsx** — Privacy-first design

**Visual Indicators:**
- PII fields blurred by default
- Lock icons on sensitive data
- "Data access logged" warnings
- GDPR action buttons (Export, Anonymize, Delete)

---

### **4. Billing & Invoices** 💰
**Purpose:** Platform invoicing (Tavlo fees only)

**Two Types of Invoices:**

#### **A. Vendor Invoices (Tavlo Subscription Fees)**
- Subscription fees: €49 (Basic), €99 (Standard), €199 (Premium)
- VAT: 20% (Austrian rate)
- Payment status: Paid, Sent, Overdue
- Actions: Send, Mark Paid, Download PDF

#### **B. Customer Invoices (Order Receipts — Read-Only)**
- Order ID, Date, Restaurant, Item Count
- Subtotal, Tax, Tip, Total
- Payment method (Card, Cash)
- Actions: View (read-only), Download PDF ONLY

**Key Component:**
- **InvoiceManagement.tsx** with tabs

**Visual Indicators:**
- **Invoice Scope Notice Banner:** "Tavlo invoices cover platform services only, not restaurant sales accounting"
- **Customer Tab Warning:** "Admin cannot edit items, change prices, or manipulate order details"
- "(Read-Only)" label on customer invoice columns

**Boundaries:**
- ✅ Admin can send Tavlo subscription invoices
- ✅ Admin can view customer order receipts (metadata)
- ❌ Admin CANNOT edit order line items
- ❌ Admin CANNOT change customer order totals
- ❌ These invoices are NOT restaurant sales accounting

---

### **5. Reviews & Complaints** 💬
**Purpose:** Content moderation (visibility only)

**What Admin Can Do:**
- View all reviews and ratings
- Hide abusive content
- Approve pending reviews
- Add moderation notes
- View customer complaints
- Assign complaints to support staff

**What Admin CANNOT Do:**
- ❌ Edit review text
- ❌ Modify star ratings
- ❌ Delete reviews (only hide)
- ❌ Change customer complaint content

**Key Component:**
- **ModerationManagement.tsx**

**Visual Indicators:**
- **Moderation Policy Banner:** "Moderation affects visibility only. Original reviews are preserved in audit logs"
- Actions: "Hide" (not "Delete")
- "Original content" preserved label

---

### **6. Subscriptions** 💳
**Purpose:** Manage Tavlo subscription plans

**What Admin Can Do:**
- View all vendor subscriptions
- See payment status (paid, overdue, trial)
- Monitor subscription health
- View plan distribution
- Send payment reminders

**What Admin CANNOT Do:**
- ❌ Force vendors to upgrade
- ❌ Change vendor pricing (menu)
- ❌ Modify vendor features beyond subscription tier

**Key Component:**
- **SubscriptionManagement.tsx**

**Visual Indicators:**
- Plan badges (Basic, Standard, Premium)
- Payment status indicators
- Overdue warnings

---

### **7. System Settings** ⚙️
**Purpose:** Platform governance only

**7 Tabs:**

#### **Tab 1: General**
- Platform language, timezone, date/time formats
- ✅ Platform-level defaults
- ❌ No vendor operation controls

#### **Tab 2: Payment Infrastructure**
- Stripe, PayPal configuration
- **Banner:** "Payment methods, tipping settings, and service fees are configured by each vendor"
- ❌ NO cash toggles, NO tip config, NO service fees

#### **Tab 3: Subscriptions & Billing**
- Tavlo subscription pricing (€49/€99/€199)
- Trial periods, grace periods
- **Banner:** "These are Tavlo subscription fees, not restaurant menu pricing"

#### **Tab 4: Compliance & Privacy**
- GDPR tools, data retention, audit logs
- 7-year retention (Austrian law)
- Auto-anonymization rules

#### **Tab 5: Vendor Onboarding**
- Manual vs auto-approval
- Required onboarding steps
- Country availability

#### **Tab 6: Admin Notifications**
- Internal admin alerts only
- **Banner:** "Vendor and customer notifications are configured separately"

#### **Tab 7: Roles & Permissions**
- 5 roles: Super Admin, Finance, Support, Compliance, Content Moderator
- Permission matrix (View, Edit, Special)
- **Banner:** "Principle of Least Privilege"

**Key Component:**
- **SystemSettings.tsx** — Complete redesign

**Explicitly REMOVED:**
- ❌ Cash payment toggles
- ❌ Tip configuration
- ❌ Service fee settings
- ❌ Order limits/timeouts
- ❌ QR expiration rules

All above are **vendor-level settings**.

---

### **8. Audit Log** 📜
**Purpose:** Immutable accountability trail

**What Gets Logged:**
- All admin actions (user, timestamp, action, reason)
- Customer data access (who, when, why)
- Vendor account changes (status, suspension)
- Review moderation (hide, approve)
- Settings changes (old value, new value)
- GDPR actions (export, delete)

**Features:**
- Search by admin user, action type, date range
- Filter by severity (Info, Warning, Critical)
- Export for compliance audits
- 7-year retention (Austrian compliance)

**Key Component:**
- **AuditLog.tsx**

**Boundaries:**
- ✅ View all logs
- ✅ Export logs
- ✅ Filter and search
- ❌ CANNOT edit logs (immutable)
- ❌ CANNOT delete logs

**Visual Indicators:**
- Color-coded severity (blue/amber/red)
- Mandatory reason fields
- "Immutable record" notice

---

### **9. AI Insights** ✨
**Purpose:** AI-powered platform analytics

**Features:**
- Anomaly detection (fraud, abuse)
- Revenue forecasting (Tavlo subscription revenue)
- Vendor risk scoring
- Customer behavior insights
- Review sentiment analysis

**Key Component:**
- **AIAdminInsights.tsx**

**Boundaries:**
- ✅ Platform-level insights
- ✅ Risk indicators
- ❌ Not for manipulating vendor operations

---

## 🎨 DESIGN SYSTEM — COLOR-CODED NOTICES

### **Purple Banner** 💜
**Usage:** Platform governance statements  
**Example:** "These settings control Tavlo platform infrastructure only. Restaurant operations are configured by each vendor in their dashboard."

### **Amber Banner** 🟡
**Usage:** Vendor boundary warnings  
**Example:** "Payment methods, tipping settings, and service fees are configured by each vendor. Admin cannot change vendor operational rules."

### **Blue Banner** 💙
**Usage:** Informational context  
**Example:** "Tavlo invoices cover platform services only, not restaurant sales accounting."

### **Red Banner** 🔴
**Usage:** Critical compliance notices  
**Example:** "GDPR data deletion is permanent and cannot be undone."

### **Green Banner** 🟢
**Usage:** Success confirmations  
**Example:** "Vendor approved successfully and notified via email."

---

## 🔒 ROLE-BASED ACCESS CONTROL

```
┌─────────────────────────────────────────────────────────────┐
│                    ADMIN ROLE MATRIX                        │
└─────────────────────────────────────────────────────────────┘

                 │ Super │Finance│Support│Compliance│Content
                 │ Admin │ Admin │ Admin │  Admin   │  Mod
─────────────────┼───────┼───────┼───────┼──────────┼────────
Dashboard        │   ✅   │   ✅   │   ✅   │    ✅     │   ❌
Vendors (View)   │   ✅   │   ✅   │   ✅   │    ✅     │   ❌
Vendors (Edit)   │   ✅   │   ❌   │   ❌   │    ❌     │   ❌
Vendors (Suspend)│   ✅   │   ❌   │   ❌   │    ✅     │   ❌
Customers (View) │   ✅   │   ❌   │   ✅   │    ✅     │   ❌
Customers (GDPR) │   ✅   │   ❌   │   ❌   │    ✅     │   ❌
Invoices (View)  │   ✅   │   ✅   │   ✅   │    ✅     │   ❌
Invoices (Edit)  │   ✅   │   ✅   │   ❌   │    ❌     │   ❌
Reviews (View)   │   ✅   │   ❌   │   ✅   │    ✅     │   ✅
Reviews (Moderate)│  ✅   │   ❌   │   ❌   │    ❌     │   ✅
Settings (View)  │   ✅   │   ✅   │   ❌   │    ✅     │   ❌
Settings (Edit)  │   ✅   │   ❌   │   ❌   │    ✅     │   ❌
Audit Log (View) │   ✅   │   ✅   │   ✅   │    ✅     │   ✅
Audit Log (Export)│  ✅   │   ✅   │   ❌   │    ✅     │   ❌
```

**Key Principle:** Each role sees only what is necessary for their function.

---

## 🚫 CLEAR BOUNDARIES — WHAT ADMIN CANNOT DO

### **Menu & Pricing** ❌
- Edit vendor menu items
- Change product names or descriptions
- Modify product prices
- Add/remove menu categories
- Set product availability

**Why:** Restaurant menu is vendor's intellectual property and operational control.

### **Order Management** ❌
- Modify customer orders after placement
- Change order totals
- Add/remove items from orders
- Override vendor order acceptance

**Why:** Orders are contracts between customer and vendor. Tavlo is the platform, not a party to the transaction.

### **Tipping & Fees** ❌
- Set tip percentages
- Configure service fees
- Change gratuity amounts
- Force vendors to accept tips

**Why:** Tipping is vendor operational policy, not platform rule.

### **Payment Methods** ❌
- Force vendors to accept cash
- Require specific payment types
- Set payment limits
- Control vendor payment settings

**Why:** Payment acceptance is vendor business decision (within available platform providers).

### **Operational Rules** ❌
- Set order preparation times
- Configure delivery zones
- Set opening hours
- Control QR code expiration
- Set order capacity limits

**Why:** These are vendor operational settings specific to each restaurant's workflow.

### **Customer Behavior** ❌
- Force customers to tip
- Require minimum order amounts
- Control order frequency
- Manipulate customer reviews

**Why:** Customer autonomy and fair marketplace principles.

---

## ✅ WHAT ADMIN CAN DO

### **Platform Governance** ✅
- Configure payment provider integrations (Stripe, PayPal)
- Set platform language and localization
- Manage subscription plans and pricing
- Configure GDPR compliance rules
- Set data retention policies

### **Vendor Lifecycle** ✅
- Approve/reject vendor applications
- Suspend vendors for platform violations
- View vendor subscription status
- Send subscription invoices
- Monitor vendor activity

### **Compliance & Safety** ✅
- Moderate abusive reviews (hide, not edit)
- Handle customer complaints
- Export user data (GDPR)
- Delete accounts (GDPR)
- Generate audit reports

### **Platform Monitoring** ✅
- View aggregated platform metrics
- Monitor payment provider health
- Track subscription revenue
- Analyze platform usage trends
- Detect fraud and abuse

---

## 📁 FILE STRUCTURE

```
/components/admin/
├── AdminApp.tsx                    # Main router
├── AdminLayout.tsx                 # Navigation & shell
├── Dashboard.tsx                   # ✅ Updated with "Gross Order Value"
├── VendorsList.tsx                 # ✅ Updated with tooltips
├── ImprovedVendorManagement.tsx    # ✅ NEW: Boundary-safe view
├── ImprovedCustomerManagement.tsx  # ✅ NEW: GDPR-safe view
├── InvoiceManagement.tsx           # ✅ Updated with scope banners
├── SubscriptionManagement.tsx      # Existing
├── ModerationManagement.tsx        # ✅ Updated with policy banner
├── SystemSettings.tsx              # ✅ NEW: Complete redesign
├── AuditLog.tsx                    # ✅ NEW: Immutable trail
├── AIAdminInsights.tsx             # Existing
└── VendorApproval.tsx              # Existing

/
├── ADMIN_SYSTEM_BOUNDARIES.md          # ✅ Boundary documentation
├── ADMIN_SETTINGS_DOCUMENTATION.md     # ✅ Settings guide
├── ADMIN_UPDATES_COMPLETE.md           # ✅ Completion summary
└── ADMIN_ARCHITECTURE_SUMMARY.md       # ✅ This file
```

---

## 🎯 CORE PHILOSOPHY

```
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║         "Tavlo enables restaurants and customers.            ║
║              Tavlo does not run restaurants."                ║
║                                                               ║
║  Every admin screen communicates clear boundaries between    ║
║  platform governance and vendor operations.                  ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
```

### **Platform Enabler Principles:**

1. **Observe, Not Control**
   - Admin can SEE vendor data
   - Admin CANNOT change vendor operations

2. **Transparency Always**
   - Clear labels on all metrics
   - Tooltips explaining Tavlo's role
   - Boundaries communicated upfront

3. **Privacy First**
   - PII hidden by default
   - Data access logged
   - GDPR compliance built-in

4. **Audit Everything**
   - All admin actions logged
   - Mandatory reasons for sensitive actions
   - Immutable audit trail

5. **Regulatory Friendly**
   - Language suitable for auditors
   - Austrian VAT compliance
   - GDPR-ready design

6. **Role-Based Access**
   - Least privilege principle
   - Each role sees only what's necessary
   - No blanket admin access

---

## 🚀 DEPLOYMENT CHECKLIST

### **Pre-Deployment Validation:**

- [ ] All "Gross Order Value (via Tavlo)" labels in place
- [ ] Tooltips present on all ambiguous metrics
- [ ] Boundary notices on every module
- [ ] Customer PII hidden by default
- [ ] Audit logging enabled for all actions
- [ ] GDPR actions functional (export, delete)
- [ ] System Settings has NO vendor operational controls
- [ ] Subscription pricing set correctly (€49/€99/€199)
- [ ] VAT rate 20% (Austrian compliance)
- [ ] Data retention 7 years (financial records)
- [ ] All removed settings documented
- [ ] Role permissions validated
- [ ] Unsaved changes warning works
- [ ] API keys masked properly
- [ ] Webhook status indicators functional

### **Post-Deployment Testing:**

- [ ] Test each admin role's access level
- [ ] Verify audit log entries are created
- [ ] Confirm GDPR export generates correct data
- [ ] Test vendor suspension flow with reason
- [ ] Validate customer data anonymization
- [ ] Check invoice scope banners visible
- [ ] Confirm moderation policy banner shows
- [ ] Test unsaved changes warning
- [ ] Verify payment provider toggles work
- [ ] Validate subscription plan editing

---

## 📞 SUPPORT & TRAINING

### **Admin Onboarding Guide:**

**Week 1: Understanding Boundaries**
- Review "Tavlo as Platform Enabler" philosophy
- Learn what admin CAN and CANNOT do
- Understand vendor autonomy principles

**Week 2: Core Functions**
- Dashboard metrics interpretation
- Vendor approval process
- Customer support with GDPR compliance

**Week 3: Advanced Features**
- System settings configuration
- Audit log analysis
- Role-based access management

**Week 4: Compliance & Reporting**
- GDPR request handling
- Audit trail exports
- Compliance reporting

### **Common Mistakes to Avoid:**

❌ **Mistake:** "Can I change this vendor's menu prices?"  
✅ **Correct:** "Menu pricing is vendor-controlled. I can only observe."

❌ **Mistake:** "Let me edit this customer's review."  
✅ **Correct:** "I can only hide abusive reviews, not edit them."

❌ **Mistake:** "I'll force all vendors to accept cash."  
✅ **Correct:** "Cash acceptance is a vendor business decision."

❌ **Mistake:** "I need to see all customer emails without reason."  
✅ **Correct:** "PII access requires support reason and is logged."

---

## 🎓 REGULATORY COMPLIANCE

### **GDPR Compliance:**
- ✅ Right to access (export user data)
- ✅ Right to be forgotten (delete accounts)
- ✅ Data portability (machine-readable export)
- ✅ Privacy by design (PII hidden by default)
- ✅ Consent management (logged access)
- ✅ Data retention policies (7 years financial, 90 days guest)

### **Austrian Tax Law:**
- ✅ 7-year retention for financial records
- ✅ 20% VAT on Tavlo subscription fees
- ✅ Proper invoice generation with VAT breakdown
- ✅ Audit trail for tax compliance

### **Payment Compliance:**
- ✅ PCI-DSS (Stripe handles card data)
- ✅ Webhook security (signed requests)
- ✅ API key protection (masked in UI)
- ✅ Payment provider health monitoring

---

**Last Updated:** December 25, 2024  
**Architecture Version:** 2.0.0  
**Status:** ✅ Production Ready  
**Reviewed By:** Platform Architecture Team  
**Compliance:** GDPR ✅ | Austrian VAT ✅ | PCI-DSS ✅
