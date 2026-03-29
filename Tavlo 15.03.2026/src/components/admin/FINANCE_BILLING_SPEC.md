# Finance & Billing Overview - Design Specification

## Overview
The **Finance & Billing Overview** is a platform-centric, read-only financial monitoring page designed for:
- 💼 Finance teams
- 📊 Accounting departments
- 🔍 Audit functions
- 👔 Executive oversight

**This page observes and reports. It does not resolve operational issues.**

---

## Core Principles (Mandatory)

✅ **Billing is automatic** - No manual invoice creation  
✅ **Vendors auto-deactivated on payment failure** - System-driven  
✅ **Page monitors, reconciles, and reports** - Read-only by default  
✅ **Vendor-specific actions** → Vendor Detail → Billing tab  
✅ **No operational duplication** with Vendor tab  

---

## 1. Page Purpose & Identity

**Page Title:**
```
Finance & Billing Overview
```

**Subtitle:**
```
Platform-level billing, payments, VAT, and invoice records (read-only)
```

**Purpose Statement:**
This page answers: **"Is Tavlo's money flow healthy and compliant?"**

This page does NOT answer: "Why is Vendor X inactive?" → That belongs in Vendor Detail.

---

## 2. Invoice Scope Notice (Always Visible, Non-Dismissable)

**Location:** Top of page, blue banner with FileText icon

**Content:**
```
Invoice Scope & Platform Billing

• Tavlo invoices cover platform services only (subscriptions, add-ons).
• Vendor invoices are auto-generated and auto-charged.
• Customer invoices are order receipts (read-only metadata).
• Tavlo does not manage restaurant sales accounting.
```

**Design:**
- Blue background (blue-50)
- FileText icon (blue-100 circle)
- Bold key terms
- Cannot be dismissed
- Always visible

**Why:**
Sets correct expectations. Finance teams must understand Tavlo's role as platform facilitator, not restaurant accountant.

---

## 3. Finance-Grade KPI Cards (Platform Only)

**5 Clickable Cards:**

### 1. Billed This Month (€)
- **Icon:** Euro (blue)
- **Value:** €48,750.00
- **Click:** Filters to all current month invoices

### 2. Collected This Month (€)
- **Icon:** CheckCircle (green)
- **Value:** €45,320.00
- **Click:** Filters to paid invoices this month

### 3. Failed Charges (30d)
- **Icon:** XCircle (red)
- **Value:** 3
- **Click:** Filters to failed payment status

### 4. VAT Payable (Current Period)
- **Icon:** ShieldCheck (purple)
- **Value:** €9,740.00
- **Click:** Shows current VAT liability

### 5. Avg Payment Success Rate
- **Icon:** TrendingUp (green)
- **Value:** 93.2%
- **Click:** Shows payment health metric

**Interaction Rules:**
- ✅ Cards are clickable
- ✅ Clicking applies instant filters to invoice table
- ✅ Active card shows purple ring (ring-2 ring-purple-600)
- ✅ No charts inside cards (finance-grade simplicity)
- ✅ No vendor-level data here (platform aggregates only)

**Active Filter Banner:**
When KPI clicked, shows purple banner:
```
Filter active: Failed Charges
[Clear filter]
```

---

## 4. Tabs Structure (Clarified Roles)

### Tab 1: Vendor Invoices
**Purpose:** Auto-generated subscription invoices

**Scope:**
- Platform subscription fees
- Add-on charges
- Auto-charged, auto-generated
- Read-only (reflects PSP state only)

**Count Badge:** Shows total vendor invoices

### Tab 2: Customer Invoices (Receipts)
**Purpose:** Order receipts metadata view

**Scope:**
- Order receipts (read-only metadata)
- No item manipulation
- No financial authority implied
- Full receipts visible in Customer Support Overview

**Warning Banner:**
```
⚠️ Read-Only Metadata

Customer invoices are order receipts. This view shows metadata only. 
No financial authority. Full receipts visible in Customer Support Overview.
```

---

## 5. Vendor Invoices Table — Read-Only by Default

### Columns (Left to Right):

1. **Invoice ID** (mono font, bold)
   - Format: INV-2025-001842

2. **Vendor** (clickable link)
   - Click → Navigates to Vendor Detail → Billing tab
   - Shows ExternalLink icon
   - Purple text (hover: darker purple)
   - Tooltip: "Navigate to Vendor Detail → Billing"

3. **Type**
   - Subscription / Add-on
   - Plain text

4. **Period**
   - Format: "January 2025"
   - Gray text

5. **Net Amount**
   - Format: €299.00
   - Bold, gray-900

6. **VAT**
   - Format: €59.80
   - Regular, gray-600

7. **Gross Total**
   - Format: €358.80
   - Semibold, gray-900

8. **Payment Status** (color-coded badge)
   - 🟢 **Paid** (green-100 bg, green-700 text)
   - 🔴 **Failed** (red-100 bg, red-700 text)
   - 🟡 **Retrying** (yellow-100 bg, yellow-700 text)

9. **PSP Reference**
   - Format: pi_3Q4Rf2abcdef123
   - Mono font, small, gray-500

10. **Issued Date**
    - Format: 2025-01-01
    - Gray-600

11. **Actions** (icon buttons)
    - 👁️ **View** (Eye icon) → Opens invoice PDF
    - 📥 **Download** (Download icon) → Downloads PDF

---

## 6. Manual Actions - Removed/Restricted

### ❌ Removed as Primary Actions:
- "Mark Paid"
- Manual status overrides
- "Send Reminder"
- "Chase Payment"

### ⚠️ If Retained (Edge Cases Only):
- Hide behind "More actions" overflow menu
- **Super Admin only**
- Mandatory reason (textarea)
- Mandatory audit log entry
- Must feel exceptional, not normal

**Philosophy:**
Manual intervention signals system failure. Auto-billing is the norm.

---

## 7. Finance-Grade Filters

### ✅ Allowed Filters:

**Status Filter:**
- All Statuses (default)
- Paid
- Failed
- Retrying

**Type Filter:**
- All Types (default)
- Subscription
- Add-on

**Period Filter:**
- Current Month (default)
- Last Month
- Current Quarter
- Last Quarter
- Current Year

**Country Filter:** (if multi-country)
- For VAT relevance
- Groups by tax jurisdiction

**PSP Filter:** (if multiple PSPs)
- Stripe
- PayPal
- etc.

### ❌ NOT Allowed Filters:
- Vendor operational status (belongs in Vendor tab)
- Onboarding stage
- "Live" / "Not Live" status
- Menu published status

**Why:**
Finance cares about money flow, not operational state.

---

## 8. Exports — Accounting First

### Export Options:

**1. Invoices (Filtered)**
- Excel / CSV format
- Respects current filters
- Includes all invoice fields
- No personal customer data

**2. VAT Report (Monthly/Quarterly)**
- VAT breakdown by rate (20%, 10%)
- Net, VAT, Gross totals
- Country grouping
- Period selection

**3. Revenue Summary by Country**
- Grouped by country
- Tax jurisdiction compliance
- Quarter/year aggregates

**4. PSP Reconciliation File**
- Matches invoices to PSP transactions
- PSP reference cross-check
- Payment confirmation status

### Export Rules:
- ✅ All exports logged in Audit Log
- ✅ Admin ID + timestamp recorded
- ✅ Filter state captured
- ❌ No personal customer data
- ❌ No email addresses
- ❌ No phone numbers

---

## 9. Finance Actions Section (Bottom CTA)

**Replaced large CTA buttons with finance-focused cards:**

### Card 1: Generate Monthly Invoices
- **Icon:** RefreshCw (purple)
- **Title:** Generate Monthly Invoices
- **Subtitle:** Auto-generation confirmation only
- **Button:** "View Schedule" (purple)
- **Behavior:** Shows auto-generation schedule, NOT manual trigger

### Card 2: Export VAT Report
- **Icon:** FileText (green)
- **Title:** Export VAT Report
- **Subtitle:** Monthly/quarterly VAT summary
- **Button:** "Export VAT" (green)
- **Behavior:** Opens period selector → Downloads VAT Excel report

### Card 3: PSP Reconciliation Status
- **Icon:** ShieldCheck (blue)
- **Title:** PSP Reconciliation Status
- **Subtitle:** Read-only system health
- **Status:** 
  - 🟢 Green pulse dot
  - "All systems operational"
- **Behavior:** Read-only health indicator

**Removed:**
- ❌ "Send Reminders" button
- ❌ "Chase Vendors" button
- ❌ Operational actions

---

## 10. Navigation Contract (Critical)

### Click Destinations Annotated:

**Invoice ID (INV-2025-001842):**
→ **Stays in Finance view** (no navigation)

**Vendor Name (Bella Italia):**
→ **Vendor Detail → Billing tab**
- Deep-link to vendor's billing history
- ExternalLink icon indicates navigation
- Tooltip: "Navigate to Vendor Detail → Billing"

**Payment Failure Metric (Failed Charges card):**
→ **Filtered invoice list** (status=failed)

**View/Download Icons:**
→ **Invoice PDF** (modal or download)

**KPI Cards:**
→ **Apply filters** to invoice table (stay in Finance view)

**No Navigation Back:**
- No breadcrumb to dashboard summaries
- No "back to vendor list" behavior
- This page is self-contained

**Philosophy:**
This page observes. It does not resolve. Resolution happens in Vendor Detail → Billing.

---

## 11. Customer Invoices Tab - Read-Only Metadata

### Warning Banner (Always Visible):
```
⚠️ Read-Only Metadata

Customer invoices are order receipts. This view shows metadata only.
No financial authority. Full receipts visible in Customer Support Overview.
```

### Columns:

1. **Order ID** (mono font)
2. **Vendor** (restaurant name)
3. **Date** (order timestamp)
4. **Items** (count)
5. **Subtotal** (€)
6. **VAT** (€)
7. **Total** (€)
8. **Payment** (method, masked)
9. **Status** (completed/refunded)

### No Actions Column
- No view/download buttons
- Metadata view only
- Full receipts in Customer Support Overview (Admin → Customers)

**Why:**
Finance monitors customer transaction volume. They don't manage individual receipts.

---

## 12. Audit & Compliance Rules

### Logged Actions:

**1. Invoice Views**
```json
{
  "action": "INVOICE_VIEWED",
  "invoiceId": "INV-2025-001842",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T15:30:00Z"
}
```

**2. Invoice Downloads**
```json
{
  "action": "INVOICE_DOWNLOADED",
  "invoiceId": "INV-2025-001842",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T15:31:00Z"
}
```

**3. Exports**
```json
{
  "action": "VAT_REPORT_EXPORT",
  "period": "January 2025",
  "filters": {"status": "all", "type": "all"},
  "admin": "ADM-001",
  "timestamp": "2025-01-06T15:32:00Z"
}
```

**4. Manual Overrides (If Any)**
```json
{
  "action": "INVOICE_STATUS_OVERRIDE",
  "invoiceId": "INV-2025-001842",
  "fromStatus": "failed",
  "toStatus": "paid",
  "reason": "Manual bank transfer confirmation",
  "admin": "ADM-001 (Super Admin)",
  "timestamp": "2025-01-06T15:33:00Z"
}
```

### Audit Entry Requirements:
- ✅ Admin user ID
- ✅ Timestamp (ISO format)
- ✅ Action type
- ✅ Entity ID (invoice, vendor, etc.)
- ✅ Reason (if manual action)
- ✅ IP address
- ✅ Session ID

---

## 13. Constraints (Do Not Violate)

### ❌ Do NOT:
- Add charts (finance-grade = tables + numbers)
- Manage vendor lifecycle here (belongs in Vendor tab)
- Duplicate Vendor Detail functionality (no operational actions)
- Expose customer PII (no emails, no phone numbers)
- Allow silent manual changes (all overrides logged)
- Create invoices manually (auto-generation only)
- Send reminders from this page (system-automated)

### ✅ DO:
- Show aggregated platform health
- Provide export tools for accounting software
- Link to operational pages (Vendor Detail)
- Maintain read-only posture by default
- Log all sensitive actions
- Use finance-grade language
- Group by tax jurisdiction

---

## 14. Design Annotations

### Color Coding:

**Status Colors:**
- 🟢 **Green** → Paid, successful, healthy
- 🔴 **Red** → Failed, issues, needs attention
- 🟡 **Yellow** → Retrying, pending, in-process
- 🔵 **Blue** → Informational, compliance, notices
- 🟣 **Purple** → Active filters, selected state

**Icon Usage:**
- 💶 **Euro** → Money metrics
- ✅ **CheckCircle** → Payment success
- ❌ **XCircle** → Payment failure
- 📄 **FileText** → Invoices, reports
- 🔗 **ExternalLink** → Navigation to other pages
- 🔒 **ShieldCheck** → Compliance, VAT, security
- 📊 **TrendingUp** → Success rates, metrics
- 🔄 **RefreshCw** → Auto-generation, scheduled tasks
- ⚠️ **AlertTriangle** → Warnings, read-only notices

### Typography:

**Mono Font:**
- Invoice IDs (INV-2025-001842)
- PSP References (pi_3Q4Rf2abc...)
- Order IDs (ORD-8472)

**Bold:**
- Net amounts
- Gross totals
- KPI values
- Vendor names (in vendor column)

**Semibold:**
- Section headers
- Card titles
- Gross total (in table)

---

## 15. Production Checklist

### ✅ Finance-Grade Requirements:
- [ ] KPI cards show platform aggregates
- [ ] Clicking KPI applies filters
- [ ] Invoice table is read-only by default
- [ ] Vendor name links to Vendor Detail → Billing
- [ ] Manual actions hidden/restricted
- [ ] All exports logged to Audit Trail
- [ ] VAT report export functional
- [ ] PSP reconciliation status visible

### ✅ Compliance:
- [ ] Invoice scope notice always visible
- [ ] Customer invoices marked "read-only metadata"
- [ ] No customer PII exposed
- [ ] All invoice views logged
- [ ] All downloads logged
- [ ] Manual overrides require reason

### ✅ Navigation:
- [ ] Vendor name → Vendor Detail → Billing
- [ ] Invoice ID → Stays in Finance view
- [ ] KPI cards → Apply filters (no navigation)
- [ ] No breadcrumb loops

### ✅ Filters:
- [ ] Status filter (Paid, Failed, Retrying)
- [ ] Type filter (Subscription, Add-on)
- [ ] Period filter (Month, Quarter, Year)
- [ ] No operational filters (Live/Not Live)

### ✅ Exports:
- [ ] Filtered invoices export
- [ ] VAT report export
- [ ] Revenue by country export
- [ ] PSP reconciliation export
- [ ] All exports Excel/CSV format

---

## 16. Language & Wording Rules

### Use Finance-Grade Language:

✅ **DO Use:**
- "Billed This Month" (not "Revenue")
- "Collected This Month" (not "Received")
- "VAT Payable" (not "Tax Owed")
- "Net Amount" (not "Price")
- "Gross Total" (not "Total with Tax")
- "PSP Reference" (not "Payment ID")
- "Failed Charges" (not "Unpaid Invoices")

❌ **DO NOT Use:**
- "Sales" (implies direct selling)
- "Revenue" (ambiguous)
- "Income" (accounting term with different meaning)
- "Pending" (use "Retrying" for payments)
- "Overdue" (vendors auto-deactivate on failure)

### Button Labels:

✅ **Correct:**
- "View Schedule" (not "Generate Now")
- "Export VAT" (not "Download Report")
- "View Invoice" (not "See Details")

❌ **Incorrect:**
- "Chase Payment"
- "Send Reminder"
- "Mark as Paid"
- "Create Invoice"

---

## 17. Austrian VAT Compliance

**VAT Rates (Austria):**
- 20% (standard rate)
- 10% (reduced rate - food)
- 13% (cultural events, some services)

**VAT Report Must Include:**
- Net amount by rate
- VAT amount by rate
- Gross total by rate
- Total VAT payable
- Tax period
- Currency (EUR)

**Format:**
```
VAT Report - January 2025

Rate    Net         VAT         Gross
20%     €29,920     €5,984      €35,904
10%     €12,000     €1,200      €13,200
-------------------------------------------
Total   €41,920     €7,184      €49,104

Total VAT Payable: €7,184
```

---

## 18. Final Note for the Team

**This page exists to answer ONE question:**

> **"Is Tavlo's money flow healthy and compliant?"**

It does NOT answer:
- "Why is Vendor X inactive?" → Vendor Detail
- "How do I contact this vendor?" → Vendor Detail
- "What menu does this restaurant have?" → Vendor Detail
- "Why did this customer dispute?" → Customer Support Overview

**If someone asks operational questions on this page, the design has failed.**

This is a **finance monitoring dashboard**, not an **operational command center**.

---

## 19. Role-Based Access

**Finance Team:**
- ✅ View all invoices
- ✅ Export reports
- ✅ View KPIs
- ❌ No manual overrides

**Accounting:**
- ✅ View all invoices
- ✅ Export VAT reports
- ✅ Reconcile PSP transactions
- ❌ No manual overrides

**Super Admin:**
- ✅ All finance team permissions
- ✅ Manual status overrides (with reason)
- ✅ Access audit log
- ⚠️ Manual actions logged

**Vendors:**
- ❌ No access to this page
- Their invoices visible in Vendor Dashboard → Billing

---

## 20. Success Criteria

**Finance & Billing Overview is successful if:**

✅ Finance team can answer: "What's our monthly billed vs. collected?"  
✅ Accounting can export VAT reports for tax filing  
✅ Executives can see payment success rate at a glance  
✅ Audit team can verify all invoice actions are logged  
✅ No one asks operational questions on this page  

**Finance & Billing Overview has failed if:**

❌ People try to "fix" vendor issues here  
❌ Manual invoice creation is requested  
❌ "Send reminder" button is added back  
❌ Charts clutter the interface  
❌ Customer PII is exposed  

---

**END OF FINANCE & BILLING OVERVIEW SPECIFICATION**
