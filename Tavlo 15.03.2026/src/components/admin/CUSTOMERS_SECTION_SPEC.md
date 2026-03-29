# Admin → Customers Section - GDPR-Compliant Design Specification

## Overview
The Admin → Customers section is a **support-oriented, GDPR-compliant** customer management interface designed for safe customer investigation without violating privacy regulations. This is NOT a CRM system.

---

## Core Principles (Non-Negotiable)

✅ **Customer personal data is hidden by default**  
✅ **Any access to personal data requires explicit intent**  
✅ **All sensitive actions are logged in Audit Log**  
✅ **Support investigates first, destructive actions come last**  
✅ **Vendors can never access this area**

---

## A. CUSTOMER MANAGEMENT — LIST VIEW

### A1. GDPR & Privacy Banner

**Location:** Top of Customers page  
**Component:** `GDPRPrivacyBanner.tsx`

**Default State (Restricted Data Hidden):**
- Blue banner with Shield icon
- Message: "Customer personal data (email, phone) is hidden by default for GDPR compliance"
- Button: "Show Restricted Data" (blue)

**Active State (Restricted Data Visible):**
- Orange/Amber banner with Warning icon
- Message: "Email and phone fields are visible. This access is logged in Audit Trail."
- Timer display: "Auto-hide in 9:42"
- Button: "Hide Restricted Data" (amber)
- Warning badge: "Data access logged • Auto-hide after timeout"

**Behavior:**
1. Click "Show Restricted Data" → Opens confirmation modal
2. Modal requires:
   - Reason selection (required):
     - Customer support request
     - Fraud investigation
     - Legal / GDPR request
   - Confirmation button
3. On confirm:
   - Logs action to Audit Trail with admin ID, timestamp, reason
   - Enables restricted data visibility for 10 minutes (600 seconds)
   - Starts countdown timer
   - Highlights restricted fields in table
4. Auto-hide after 10 minutes:
   - Shows toast: "Restricted data auto-hidden"
   - Returns to default state
   - Requires re-confirmation to re-enable

**Fields Affected:**
- Email (restricted)
- Phone (restricted)

---

### A2. Summary Cards (Actionable)

**5 Cards (Clickable):**

1. **Total Customers** (Users icon)
   - Click → Shows all customers

2. **Total Orders** (ShoppingCart icon)
   - Click → Shows all customers (aggregated stat only)

3. **Flagged Accounts** (AlertCircle icon, red)
   - Click → Filters table to flagged accounts only
   - Active filter: Purple ring around card

4. **GDPR Requests (30d)** (FileText icon, blue)
   - Click → Filters table to customers with active GDPR requests
   - Shows count of requests in last 30 days

5. **High-Activity Customers** (TrendingUp icon, green)
   - Click → Filters to customers with 40+ orders in last 30 days
   - Active filter: Purple ring around card

**Interaction:**
- Cards apply instant filters to customer table
- Active filter shows purple ring + banner below search
- Banner shows "Filter active: [name]" with "Clear filter" button

---

### A3. Search Rules (Privacy-Safe)

**Search Behavior:**
- ✅ Search by **Customer ID only** (e.g., "C-1024")
- ❌ Email and phone are **explicitly NOT searchable**

**UI Elements:**
- Placeholder: "Search: C-1024"
- Helper text below input: "💡 Hint: Search by Customer ID only (paste from order or support ticket)"

**Why:**
- Prevents accidental exposure of personal data
- Forces admins to use specific IDs from support tickets/orders
- Complies with data minimization principle

---

### A4. Customer Table — Required Columns

**Columns (left to right):**

1. **Checkbox** (Select for export)
2. **Risk / Attention Indicator** (Icon-based)
   - 🔴 Flagged account (red AlertCircle)
   - 🟠 Unusual activity (orange AlertTriangle)
   - ⚪ Normal (gray dot)
   - Tooltip explains reason
   - Click → Opens Customer Support Overview
   - Sortable & Filterable

3. **Customer ID** (font-mono, bold)
4. **Account Type** (Badge: Registered/Guest)
5. **Email** (Restricted) ⚠️
   - Hidden: Lock icon + "Hidden" text (gray)
   - Visible: Lock icon (amber) + email text (bold)
6. **Phone** (Restricted) ⚠️
   - Hidden: Lock icon + "Hidden" text (gray)
   - Visible: Lock icon (amber) + phone text (bold)
7. **Orders** (Count)
8. **Total Spend** (Currency)
9. **Last Active** (Relative time)
10. **Actions** (Eye + More menu)

---

### A5. Restricted Data Display Rules

**When Hidden (Default):**
```
Email: 🔒 Hidden
Phone: 🔒 Hidden
```

**When Visible (After Confirmation):**
```
Email: 🔒 john.doe@example.com (amber lock, bold text)
Phone: 🔒 +43 664 1234567 (amber lock, bold text)
Row: Subtle amber background highlight
```

**Tooltip on Lock Icon:**
"GDPR-sensitive data · Access logged"

---

### A6. Actions Column

**Eye Icon:**
- Opens **Customer Support Overview** (detail page)
- NOT a simple modal - full investigation view

**More Menu (MoreVertical icon):**
- View Orders
- View Refunds / Disputes
- View GDPR Requests
- View Customer Activity Log

**Navigation Strategy:**
Admins investigate from Customer Support Overview, NOT from sidebar navigation.

---

### A7. Export Customer Report (With Guardrails)

**Export Button:**
- Label: "Export Selected (3)" OR "Export All Filtered"
- Icon: Download
- Position: Top right, next to search

**Export Modal:**

**Option 1: Aggregated Data Only (Default)**
- ✅ Customer ID, orders, spend, loyalty points
- ❌ No email, no phone
- No confirmation required
- No audit log

**Option 2: Include Personal Data (Restricted)**
- ⚠️ Border: Amber, background: Amber-50
- Requires:
  - Reason (textarea, required)
  - Confirmation button
- Logs to Audit Trail:
  - Admin ID
  - Timestamp
  - Reason
  - Customer count
- Toast: "Personal data export logged to Audit Trail"

**Export Format:**
- Excel (.xlsx)

---

## B. CUSTOMER SUPPORT OVERVIEW (Detail View)

**Access:** Click Eye icon or Risk indicator in customer table

### B1. Header Section

**Elements:**
- Back button (ArrowLeft)
- Title: "Customer Support Overview"
- Customer ID (font-mono, bold)
- Account type badge (Registered/Guest)

---

### B2. Support-Only Visibility Banner (Persistent)

**Appearance:**
- Blue background (blue-50)
- Shield icon
- Cannot be dismissed
- Always visible at top

**Message:**
"Visible for support purposes only. Personal data access is logged in Audit Trail and not visible to vendors. This customer cannot see or access this admin view."

**Purpose:**
- Reminds admin this is internal-only
- Clarifies vendor restrictions
- Emphasizes audit logging

---

### B3. Basic Information Section

**Card with 2-column grid:**

| Field | Value |
|-------|-------|
| Customer ID | C-1024 (font-mono) |
| Account Type | Registered Account |
| Email (Restricted) | Hidden OR john.doe@example.com |
| Phone (Restricted) | Hidden OR +43 664 1234567 |
| Account Created | 2024-01-15 |
| Last Login | 2025-01-06 14:32 |
| Registration Source | QR Code |

**Restricted Field Behavior:**
- Inherits state from parent CustomersList component
- If parent has restrictedDataVisible=true → Shows data
- If parent has restrictedDataVisible=false → Shows "Hidden (enable restricted data access)"

---

### B4. Activity Summary (Support Context)

**3 KPI Cards (Read-Only):**

1. **Total Orders** (ShoppingCart icon)
   - Value: 47
2. **Total Spend** (DollarSign icon)
   - Value: €1,284.50
3. **Loyalty Points** (Gift icon)
   - Value: 1,284

**Purpose:**
Support context for investigation, NOT sales metrics.

---

### B5. Investigation Navigation Tabs

**6 Tabs:**

1. **Orders** (ShoppingCart)
   - Count badge: 47
   - Shows all customer orders with status, vendor, amount
   
2. **Refunds / Disputes** (AlertTriangle)
   - Count badge: 2
   - Shows refund requests, chargebacks, dispute status
   
3. **Reviews / Complaints** (MessageSquare)
   - Count badge: 8
   - Shows customer reviews, ratings, flagged complaints
   
4. **Activity Log** (History)
   - No count
   - Shows login history, orders, refunds, support tickets
   
5. **GDPR Requests** (FileText)
   - Count badge: 0 (or active count)
   - Shows data export, erasure, and portability requests

**Tab Behavior:**
- Purple underline when active
- Purple background (purple-50) when active
- Content area below tabs (currently placeholder)

**Investigation Flow:**
Admin stays in this view to investigate all customer activity without returning to sidebar.

---

## C. GDPR ACTIONS (Strictly Controlled)

**Section:** Red-bordered card at bottom of Customer Support Overview

**Header:**
- Red background (red-50)
- AlertTriangle icon
- Title: "GDPR Actions (Strictly Controlled)"
- Description: "These actions are logged, audited, and may be irreversible. Proceed with caution."

---

### C1. Export Personal Data

**Card:**
- Gray background (gray-50)
- Title: "Export Personal Data"
- Subtitle: "GDPR Right to Access • Export all customer data to JSON/CSV"
- Button: "Export Data" (blue)

**Modal:**
- Title: "Export Personal Data"
- Description: "Customer will receive all personal data in machine-readable format (GDPR Article 20)"
- Reason field (textarea, required)
- Audit info: "Action will be logged to Audit Trail"
- Confirm button: "Export Data" (blue)

**Logs:**
- Admin ID
- Timestamp
- Reason
- Customer ID

---

### C2. Anonymize Customer

**Card:**
- Amber background (amber-50)
- Title: "Anonymize Customer"
- Badge: "SUPER ADMIN ONLY" (red)
- Subtitle: "GDPR Right to Erasure • Remove personal data, retain anonymized orders"
- Button: "Anonymize" (amber, Archive icon)

**Restrictions:**
- ⚠️ Super Admin role only
- ⚠️ Requires reason
- ⚠️ Explicit confirmation

**Modal:**
- Title: "Anonymize Customer Account"
- Badge: "SUPER ADMIN ONLY"
- Description: "Personal data will be removed. Order history will be retained with anonymized references. This action cannot be undone."
- Reason field (textarea, required)
- Audit info box
- Confirm button: "Anonymize Account" (amber)

**Effects:**
- Personal data (email, phone, name) anonymized
- Order history retained with anonymized customer references
- Account becomes non-identifiable
- Irreversible

**Logs:**
- Admin ID
- Timestamp
- Reason
- Customer ID
- Action: "ANONYMIZE"

---

### C3. Delete Account Permanently

**Card:**
- Red background (red-50)
- Red border (border-2, border-red-300)
- Title: "Delete Account Permanently"
- Badge: "SUPER ADMIN ONLY" (red-600 bg, white text)
- Subtitle: "⚠️ IRREVERSIBLE • Permanently delete all data including order history"
- Button: "Delete Forever" (red-600, Trash2 icon)

**Restrictions:**
- ⚠️ Super Admin role only
- ⚠️ Strong confirmation required
- ⚠️ Type "DELETE FOREVER" to confirm
- ⚠️ Reason required

**Modal:**
- Title: "Delete Account Permanently"
- Badge: "SUPER ADMIN ONLY"
- Description (red-50 bg): "⚠️ IRREVERSIBLE: All customer data including order history will be permanently deleted. Use Anonymize instead if order history must be retained for legal/tax purposes."
- Reason field (textarea, required)
- Confirmation field: Type "DELETE FOREVER" (font-mono, red border)
- Audit info box
- Confirm button: "Delete Forever" (red-600, disabled until both fields filled)

**Effects:**
- Account removed
- All personal data deleted
- Order history deleted
- All references removed
- Cannot be undone

**Logs:**
- Admin ID
- Timestamp
- Reason
- Customer ID
- Action: "DELETE_PERMANENT"
- Confirmation text

---

## D. LOGGING & AUDIT REQUIREMENTS

**All Logged Actions:**

1. ✅ Viewing restricted data (email/phone)
   - Admin ID, timestamp, reason, duration

2. ✅ Exporting customer data (with personal data)
   - Admin ID, timestamp, reason, customer count, fields included

3. ✅ Anonymizing customer
   - Admin ID, timestamp, reason, customer ID

4. ✅ Deleting customer permanently
   - Admin ID, timestamp, reason, customer ID, confirmation text

5. ✅ Any role-restricted action
   - Admin ID, timestamp, action, role required

**Audit Log Entry Structure:**
```json
{
  "timestamp": "2025-01-06T15:30:00Z",
  "adminId": "ADM-001",
  "adminName": "Sarah Chen",
  "action": "RESTRICTED_DATA_ACCESS",
  "entityType": "customer",
  "entityId": "C-1024",
  "reason": "Customer support request - payment issue investigation",
  "metadata": {
    "fieldsAccessed": ["email", "phone"],
    "duration": "600s",
    "ipAddress": "192.168.1.100"
  }
}
```

---

## E. CONSTRAINTS (DO NOT VIOLATE)

❌ **DO NOT expose customer data to vendors**  
❌ **DO NOT allow unrestricted exports**  
❌ **DO NOT show destructive actions to support agents** (Super Admin only)  
❌ **DO NOT turn this into a CRM** (support-only, not sales)  
❌ **DO NOT remove GDPR friction** (confirmations are intentional)

✅ **DO require explicit intent for data access**  
✅ **DO log all sensitive actions**  
✅ **DO auto-hide restricted data after timeout**  
✅ **DO provide investigation tools within support view**  
✅ **DO separate support from destructive actions**

---

## F. COMPONENT FILES

**Created Components:**

1. `GDPRPrivacyBanner.tsx` - Privacy banner with timer and reason modal
2. `CustomerRiskIndicator.tsx` - Risk badge (flagged/unusual/normal)
3. `CustomersList.tsx` - Main customer list view with GDPR controls
4. `CustomerSupportOverview.tsx` - Detail view with investigation tabs and GDPR actions
5. `AdminCustomersPage.tsx` - Wrapper component managing view state

**Integration:**
- Added to `AdminApp.tsx` with `customers` page route
- Uses existing `AdminLayout.tsx` for sidebar navigation

---

## G. ROLE-BASED ACCESS

**Support Admin:**
- ✅ View customer list
- ✅ Search by customer ID
- ✅ Access restricted data (with reason)
- ✅ View customer support overview
- ✅ Navigate investigation tabs
- ✅ Export personal data (GDPR Right to Access)
- ❌ Anonymize customer
- ❌ Delete account permanently

**Super Admin:**
- ✅ All Support Admin permissions
- ✅ Anonymize customer (GDPR Right to Erasure)
- ✅ Delete account permanently

---

## H. TIMEOUT & AUTO-HIDE BEHAVIOR

**Restricted Data Visibility Timer:**
- Duration: **10 minutes (600 seconds)**
- Display: Countdown timer in banner (MM:SS format)
- Auto-hide behavior:
  - At 0 seconds → Auto-hide restricted data
  - Show toast: "Restricted data auto-hidden - Data visibility timeout reached"
  - Return banner to default state
  - Restricted fields show "Hidden" again
- Re-enable: Requires new reason confirmation

**Visual Timer:**
```
Timer display: 9:42
Badge: "9:42" (amber background, Clock icon)
Banner: "Auto-hide in 9:42"
```

---

## I. DESIGN ANNOTATIONS

**Color Coding:**

- **Blue** → Privacy/GDPR (default state, informational)
- **Amber/Orange** → Active data access (warning, logged)
- **Red** → Destructive actions (deletion, permanent)
- **Purple** → Active filters, selected state
- **Green** → Registered accounts, positive stats
- **Gray** → Guest accounts, hidden data

**Icon Usage:**

- 🔒 Lock → Restricted data
- 🛡️ Shield → Privacy/GDPR compliance
- ⏱️ Clock → Timer
- ⚠️ AlertTriangle → Warnings, disputes, GDPR actions
- 👁️ Eye → View support overview
- 🔴 AlertCircle → Flagged accounts
- 🟠 AlertTriangle → Unusual activity
- ⚪ CheckCircle → Normal status

---

## J. PRODUCTION CHECKLIST

✅ **Privacy Controls:**
- [ ] Restricted data hidden by default
- [ ] Reason required for data access
- [ ] Auto-hide timer functional (10 min)
- [ ] Lock icons visible on restricted fields

✅ **Audit Logging:**
- [ ] All data access logged
- [ ] Export actions logged
- [ ] GDPR actions logged
- [ ] Admin ID, timestamp, reason captured

✅ **Role Restrictions:**
- [ ] Super Admin only for Anonymize
- [ ] Super Admin only for Delete
- [ ] Support Admin can view + export

✅ **Search Limitations:**
- [ ] Customer ID search only
- [ ] Email/phone NOT searchable
- [ ] Helper text displayed

✅ **Export Guardrails:**
- [ ] Aggregated export (no confirmation)
- [ ] Personal data export (requires reason)
- [ ] Audit log for personal data exports

✅ **GDPR Actions:**
- [ ] Export Personal Data modal
- [ ] Anonymize confirmation (Super Admin)
- [ ] Delete confirmation with "DELETE FOREVER" text
- [ ] All actions logged

---

## K. FINAL NOTE

**This page exists to resolve customer issues safely, not to collect data.**

✅ If support can investigate without violating GDPR → Design is correct  
❌ If not → Design fails, no matter how clean it looks

**Key Success Criteria:**
- Admin can help customers without seeing personal data unnecessarily
- Every data access is intentional and logged
- Destructive actions have strong friction
- Vendors can never access customer personal data
- GDPR compliance is built-in, not bolted-on
