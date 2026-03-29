# Admin → Customers Section - Interaction Flow & Annotations

## 🎯 PRIMARY USER FLOWS

---

## FLOW 1: Accessing Restricted Customer Data

```
┌─────────────────────────────────────────────────────────────┐
│ Admin navigates to Customers page                           │
│ ↓                                                            │
│ Sees GDPR Privacy Banner (BLUE)                            │
│ ├─ "Customer personal data hidden by default"              │
│ └─ Button: "Show Restricted Data"                          │
└─────────────────────────────────────────────────────────────┘
                              ↓
                     Admin clicks button
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ MODAL: Confirm Restricted Data Access                       │
│                                                              │
│ Select reason (REQUIRED):                                   │
│ ○ Customer support request                                  │
│ ○ Fraud investigation                                       │
│ ○ Legal / GDPR request                                      │
│                                                              │
│ Info box:                                                    │
│ "Restricted data will auto-hide after 10 minutes.           │
│  Email and phone fields will be visible and highlighted.    │
│  This access will be logged with your admin ID."            │
│                                                              │
│ [Cancel] [Confirm Access]                                   │
└─────────────────────────────────────────────────────────────┘
                              ↓
                    Admin confirms access
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ AUDIT LOG ENTRY CREATED:                                    │
│ {                                                            │
│   "action": "RESTRICTED_DATA_ACCESS",                       │
│   "admin": "ADM-001",                                        │
│   "reason": "Customer support request",                     │
│   "timestamp": "2025-01-06T15:30:00Z",                      │
│   "duration": "600s"                                         │
│ }                                                            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ Banner changes to AMBER/ORANGE                              │
│ ├─ ⚠️ "Restricted Data Access Active"                       │
│ ├─ Timer: "Auto-hide in 9:59" [⏱️ 9:59]                    │
│ ├─ Badge: "Data access logged"                             │
│ └─ Button: "Hide Restricted Data"                          │
│                                                              │
│ Customer Table:                                              │
│ ┌──────────┬────────────────────────────┐                  │
│ │ Email    │ 🔒 john.doe@example.com    │ ← Amber lock     │
│ │ Phone    │ 🔒 +43 664 1234567         │ ← Amber lock     │
│ │ (Row has subtle amber highlight)      │                  │
│ └──────────┴────────────────────────────┘                  │
└─────────────────────────────────────────────────────────────┘
                              ↓
                    10 minutes elapse
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ AUTO-HIDE TRIGGERED                                         │
│ ├─ Banner returns to BLUE (default state)                  │
│ ├─ Email/Phone show "🔒 Hidden" (gray)                     │
│ └─ Toast: "Restricted data auto-hidden"                    │
└─────────────────────────────────────────────────────────────┘
```

---

## FLOW 2: Investigating a Flagged Customer

```
┌─────────────────────────────────────────────────────────────┐
│ Admin sees customer with 🔴 Risk indicator                  │
│ ├─ Tooltip: "Account flagged - Multiple failed payments"   │
│ └─ Clicks risk indicator                                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
                    Opens Customer Support Overview
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ CUSTOMER SUPPORT OVERVIEW                                   │
│                                                              │
│ [← Back] Customer Support Overview                          │
│ Customer ID: C-3072 • Registered                            │
│                                                              │
│ ┌─────────────────────────────────────────────────────┐    │
│ │ 🛡️ SUPPORT-ONLY VIEW                                │    │
│ │ Visible for support purposes only.                  │    │
│ │ Personal data access is logged and not visible      │    │
│ │ to vendors.                                         │    │
│ └─────────────────────────────────────────────────────┘    │
│                                                              │
│ BASIC INFORMATION                                           │
│ ┌──────────────────┬──────────────────────┐               │
│ │ Customer ID      │ C-3072               │               │
│ │ Account Type     │ Registered           │               │
│ │ Email            │ Hidden (enable...)   │ ← Restricted  │
│ │ Phone            │ Hidden (enable...)   │ ← Restricted  │
│ │ Account Created  │ 2025-01-02           │               │
│ │ Last Login       │ 2025-01-06 14:32     │               │
│ └──────────────────┴──────────────────────┘               │
│                                                              │
│ ACTIVITY SUMMARY                                            │
│ ┌────────────┬─────────────┬───────────────┐              │
│ │ 🛒 3       │ 💰 €450.00  │ 🎁 0          │              │
│ │ Orders     │ Total Spend │ Loyalty Pts   │              │
│ └────────────┴─────────────┴───────────────┘              │
│                                                              │
│ INVESTIGATION TABS:                                         │
│ ┌────────────────────────────────────────────────────┐    │
│ │ [Orders 3] [Refunds/Disputes 2] [Reviews 0]        │    │
│ │ [Activity Log] [GDPR Requests 0]                   │    │
│ ├────────────────────────────────────────────────────┤    │
│ │ Shows order history with:                          │    │
│ │ - Failed payment attempts highlighted              │    │
│ │ - Dispute status                                   │    │
│ │ - Vendor names                                     │    │
│ └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                              ↓
            Admin clicks "Refunds/Disputes" tab
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ REFUNDS & DISPUTES TAB                                      │
│                                                              │
│ ┌─────────────────────────────────────────────────────┐    │
│ │ Dispute #D-1024                                     │    │
│ │ ├─ Status: Open                                     │    │
│ │ ├─ Reason: "Card declined, charged anyway"         │    │
│ │ ├─ Amount: €150.00                                 │    │
│ │ └─ Vendor: Bella Italia                            │    │
│ │                                                     │    │
│ │ Refund Request #R-2048                              │    │
│ │ ├─ Status: Pending Review                          │    │
│ │ ├─ Reason: "Food not delivered"                    │    │
│ │ ├─ Amount: €300.00                                 │    │
│ │ └─ Vendor: Pizza Express                           │    │
│ └─────────────────────────────────────────────────────┘    │
│                                                              │
│ Admin can now:                                              │
│ - Review dispute details                                    │
│ - Contact vendor for clarification                         │
│ - Approve/deny refund                                       │
│ - All without leaving this view                            │
└─────────────────────────────────────────────────────────────┘
```

---

## FLOW 3: Exporting Customer Data with GDPR Compliance

```
┌─────────────────────────────────────────────────────────────┐
│ CUSTOMER LIST VIEW                                          │
│                                                              │
│ Admin selects 3 customers (checkboxes)                      │
│ ├─ C-1024 ✓                                                │
│ ├─ C-2048 ✓                                                │
│ └─ C-3072 ✓                                                │
│                                                              │
│ Clicks: [📥 Export Selected (3)]                           │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ EXPORT MODAL                                                │
│                                                              │
│ Export Customer Report                                      │
│ Exporting 3 selected customers                              │
│                                                              │
│ EXPORT OPTIONS:                                             │
│                                                              │
│ ○ Aggregated data only (default)                           │
│   Customer ID, orders, spend, loyalty points               │
│   No confirmation required                                  │
│                                                              │
│ ○ Include personal data (restricted)                       │
│   ⚠️ AMBER BORDER                                          │
│   Includes email, phone • Requires reason & audit log      │
│                                                              │
│ [Cancel] [Export to Excel]                                  │
└─────────────────────────────────────────────────────────────┘
                              ↓
            Admin selects "Include personal data"
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ EXPORT MODAL (Personal Data Option Selected)               │
│                                                              │
│ ○ Include personal data (restricted) ← SELECTED            │
│   ⚠️ AMBER BACKGROUND                                      │
│                                                              │
│ ┌─────────────────────────────────────────────────────┐    │
│ │ Reason for Personal Data Export *                   │    │
│ │ ┌─────────────────────────────────────────────────┐ │    │
│ │ │ Need to email customers about payment issues   │ │    │
│ │ │ related to recent service outage.              │ │    │
│ │ └─────────────────────────────────────────────────┘ │    │
│ └─────────────────────────────────────────────────────┘    │
│                                                              │
│ ℹ️ This export will be logged to Audit Trail with your     │
│    admin ID, timestamp, and reason.                         │
│                                                              │
│ [Cancel] [Export to Excel] ← Enabled when reason filled    │
└─────────────────────────────────────────────────────────────┘
                              ↓
                    Admin clicks "Export to Excel"
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ AUDIT LOG ENTRY CREATED:                                    │
│ {                                                            │
│   "action": "CUSTOMER_DATA_EXPORT",                         │
│   "admin": "ADM-001",                                        │
│   "reason": "Need to email customers about payment...",     │
│   "timestamp": "2025-01-06T15:45:00Z",                      │
│   "customerCount": 3,                                        │
│   "customersIds": ["C-1024", "C-2048", "C-3072"],           │
│   "fieldsIncluded": ["id", "email", "phone", "orders"]      │
│ }                                                            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ ✅ Toast: "Export initiated"                                │
│    "Personal data export logged to Audit Trail"             │
│                                                              │
│ Excel file downloads:                                        │
│ customers_export_2025-01-06.xlsx                            │
│ ├─ Customer ID                                              │
│ ├─ Email ⚠️                                                 │
│ ├─ Phone ⚠️                                                 │
│ ├─ Orders                                                   │
│ ├─ Total Spend                                              │
│ └─ Loyalty Points                                           │
└─────────────────────────────────────────────────────────────┘
```

---

## FLOW 4: GDPR Right to Erasure (Anonymize Customer)

```
┌─────────────────────────────────────────────────────────────┐
│ CUSTOMER SUPPORT OVERVIEW → Scroll to bottom               │
│                                                              │
│ ⚠️ GDPR ACTIONS (STRICTLY CONTROLLED)                       │
│ RED-BORDERED SECTION                                        │
│                                                              │
│ ┌─────────────────────────────────────────────────────┐    │
│ │ Anonymize Customer                                  │    │
│ │ [SUPER ADMIN ONLY] badge                            │    │
│ │                                                     │    │
│ │ GDPR Right to Erasure • Remove personal data,      │    │
│ │ retain anonymized orders                           │    │
│ │                                                     │    │
│ │                        [🗄️ Anonymize]              │    │
│ └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                              ↓
                Super Admin clicks "Anonymize"
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ MODAL: Anonymize Customer Account                          │
│ [SUPER ADMIN ONLY] badge                                    │
│                                                              │
│ ⚠️ Personal data will be removed. Order history will be    │
│    retained with anonymized references. This action         │
│    cannot be undone.                                        │
│                                                              │
│ Reason for Action *                                         │
│ ┌─────────────────────────────────────────────────────┐    │
│ │ Customer requested GDPR Right to Erasure via email │    │
│ │ on 2025-01-06. Request ID: GDPR-2025-0042          │    │
│ └─────────────────────────────────────────────────────┘    │
│                                                              │
│ ✓ Action will be logged to Audit Trail                     │
│ ✓ Admin ID and timestamp recorded                          │
│ ✓ Reason attached to audit log                             │
│                                                              │
│ [Cancel] [Anonymize Account] ← Amber button                │
└─────────────────────────────────────────────────────────────┘
                              ↓
                    Super Admin confirms
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ SYSTEM PROCESSES ANONYMIZATION:                             │
│                                                              │
│ Before:                                                      │
│ ├─ Email: john.doe@example.com                             │
│ ├─ Phone: +43 664 1234567                                  │
│ ├─ Name: John Doe                                          │
│ └─ Orders: 47 (linked to customer ID)                      │
│                                                              │
│ After:                                                       │
│ ├─ Email: [ANONYMIZED]                                     │
│ ├─ Phone: [ANONYMIZED]                                     │
│ ├─ Name: [ANONYMIZED]                                      │
│ └─ Orders: 47 (linked to anonymized reference)            │
│                                                              │
│ AUDIT LOG ENTRY:                                            │
│ {                                                            │
│   "action": "CUSTOMER_ANONYMIZE",                           │
│   "admin": "ADM-001 (Super Admin)",                         │
│   "reason": "Customer requested GDPR Right to Erasure...",  │
│   "timestamp": "2025-01-06T16:00:00Z",                      │
│   "customerId": "C-1024",                                   │
│   "status": "SUCCESS"                                        │
│ }                                                            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ ✅ Toast: "Customer anonymized"                             │
│    "Personal data removed, order history retained"          │
│                                                              │
│ Admin is returned to Customer List                          │
│ Customer C-1024 now shows:                                  │
│ ├─ Email: [ANONYMIZED]                                     │
│ ├─ Phone: [ANONYMIZED]                                     │
│ └─ Cannot be searched by old email/phone                   │
└─────────────────────────────────────────────────────────────┘
```

---

## FLOW 5: Permanent Account Deletion (Destructive)

```
┌─────────────────────────────────────────────────────────────┐
│ GDPR ACTIONS SECTION                                        │
│                                                              │
│ ┌─────────────────────────────────────────────────────┐    │
│ │ ⚠️ Delete Account Permanently                       │    │
│ │ [SUPER ADMIN ONLY] badge (red bg, white text)       │    │
│ │                                                     │    │
│ │ ⚠️ IRREVERSIBLE • Permanently delete all data       │    │
│ │ including order history                             │    │
│ │                                                     │    │
│ │                        [🗑️ Delete Forever]         │    │
│ │                        RED BUTTON                   │    │
│ └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                              ↓
                Super Admin clicks "Delete Forever"
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ MODAL: Delete Account Permanently                          │
│ [SUPER ADMIN ONLY] badge                                    │
│                                                              │
│ 🔴 RED BACKGROUND WARNING BOX                               │
│ ⚠️ IRREVERSIBLE: All customer data including order         │
│    history will be permanently deleted. Use Anonymize       │
│    instead if order history must be retained for            │
│    legal/tax purposes.                                      │
│                                                              │
│ Reason for Action *                                         │
│ ┌─────────────────────────────────────────────────────┐    │
│ │ [Textarea for reason - REQUIRED]                    │    │
│ └─────────────────────────────────────────────────────┘    │
│                                                              │
│ Type "DELETE FOREVER" to confirm *                         │
│ ┌─────────────────────────────────────────────────────┐    │
│ │ [Text input - font-mono, red border]                │    │
│ └─────────────────────────────────────────────────────┘    │
│                                                              │
│ ✓ Action will be logged to Audit Trail                     │
│ ✓ Admin ID and timestamp recorded                          │
│ ✓ Reason attached to audit log                             │
│                                                              │
│ [Cancel] [Delete Forever] ← Disabled until both filled     │
│                              RED BUTTON                     │
└─────────────────────────────────────────────────────────────┘
                              ↓
        Super Admin fills reason + types "DELETE FOREVER"
                              ↓
                    Clicks "Delete Forever"
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ SYSTEM PROCESSES PERMANENT DELETION:                        │
│                                                              │
│ Deleted:                                                     │
│ ├─ Customer account                                         │
│ ├─ All personal data                                        │
│ ├─ Order history (all 47 orders)                           │
│ ├─ Reviews                                                  │
│ ├─ Loyalty points                                           │
│ └─ All references in system                                │
│                                                              │
│ AUDIT LOG ENTRY:                                            │
│ {                                                            │
│   "action": "CUSTOMER_DELETE_PERMANENT",                    │
│   "admin": "ADM-001 (Super Admin)",                         │
│   "reason": "[Admin's reason]",                             │
│   "confirmationText": "DELETE FOREVER",                     │
│   "timestamp": "2025-01-06T16:15:00Z",                      │
│   "customerId": "C-1024",                                   │
│   "ordersDeleted": 47,                                      │
│   "status": "SUCCESS",                                       │
│   "WARNING": "IRREVERSIBLE"                                 │
│ }                                                            │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│ ✅ Toast: "Customer account deleted"                        │
│    "All data permanently removed"                           │
│                                                              │
│ Admin is returned to Customer List                          │
│ Customer C-1024 no longer exists in system                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎨 VISUAL ANNOTATIONS

### Color States

**BLUE (Default/Privacy):**
- Privacy banner when data is hidden
- GDPR export action (non-destructive)
- Informational states

**AMBER/ORANGE (Active Access/Warning):**
- Privacy banner when restricted data is visible
- Timer badge
- Lock icons on visible restricted data
- Anonymize action (GDPR Right to Erasure)

**RED (Destructive/Critical):**
- Delete permanently action
- GDPR actions section border
- Super Admin badges for destructive actions
- Flagged account risk indicators

**PURPLE (Selected/Active):**
- Active filter ring on summary cards
- Active tab underline
- Selected checkboxes

**GRAY (Hidden/Neutral):**
- Hidden restricted data
- Guest accounts
- Normal risk status

---

### Typography Hierarchy

**Font Mono (Monospace):**
- Customer IDs (C-1024)
- Order IDs
- Confirmation text input ("DELETE FOREVER")

**Font Bold:**
- Active/visible restricted data
- Customer names
- Action button labels

**Font Semibold:**
- Section headers
- Card titles
- Tab labels

---

### Icon Language

| Icon | Meaning | Color | Usage |
|------|---------|-------|-------|
| 🔒 Lock | Restricted data | Amber/Gray | Email, Phone fields |
| 🛡️ Shield | Privacy/GDPR | Blue | Privacy banner, support-only view |
| ⏱️ Clock | Timer | Amber | Auto-hide countdown |
| ⚠️ AlertTriangle | Warning | Red/Amber | GDPR actions, disputes |
| 🔴 AlertCircle | Flagged | Red | Risk indicator |
| 🟠 AlertTriangle | Unusual | Orange | Risk indicator |
| ⚪ CheckCircle | Normal | Gray | Risk indicator |
| 👁️ Eye | View | Gray | Open support overview |
| 📥 Download | Export | Gray | Export button |
| 🗄️ Archive | Anonymize | Amber | Anonymize action |
| 🗑️ Trash | Delete | Red | Delete action |

---

## 🔐 SECURITY ANNOTATIONS

**Data Access Levels:**

```
LEVEL 0 (Public)
└─ Customer ID, Order count, Total spend

LEVEL 1 (Restricted - Logged)
└─ Email, Phone (requires reason + timer)

LEVEL 2 (Super Admin Only)
├─ Anonymize customer
└─ Delete permanently
```

**Audit Trail Requirements:**

Every action must log:
1. **Who** - Admin ID and name
2. **What** - Action type and details
3. **When** - ISO timestamp
4. **Why** - Required reason field
5. **Where** - Customer/Entity ID
6. **How** - IP address, session ID

---

## ✅ DESIGN SUCCESS CHECKLIST

**Privacy First:**
- [ ] Data hidden by default ✅
- [ ] Explicit intent required ✅
- [ ] Auto-hide timer functional ✅
- [ ] All access logged ✅

**Investigation Flow:**
- [ ] Can investigate without exposing data ✅
- [ ] All tools in one view ✅
- [ ] No sidebar navigation needed ✅
- [ ] Context preserved across tabs ✅

**GDPR Compliance:**
- [ ] Right to Access (export) ✅
- [ ] Right to Erasure (anonymize) ✅
- [ ] Right to be Forgotten (delete) ✅
- [ ] All actions logged ✅
- [ ] Strong friction on destructive actions ✅

**Role-Based Access:**
- [ ] Support Admin: View + Export ✅
- [ ] Super Admin: Anonymize + Delete ✅
- [ ] Vendors: No access ✅

**Operational Safety:**
- [ ] Search by ID only ✅
- [ ] Export requires confirmation for personal data ✅
- [ ] Destructive actions require "DELETE FOREVER" text ✅
- [ ] Banner cannot be dismissed ✅

---

## 📱 RESPONSIVE BEHAVIOR

**Desktop (Primary):**
- Full 2-column grid for customer details
- All tabs visible
- Side-by-side comparison in modals

**Tablet (768px+):**
- Grid collapses to single column
- Tabs scroll horizontally
- Modals remain centered

**Mobile (Not Primary Focus):**
- This is an admin tool - desktop-first design
- Mobile support is secondary
- Use horizontal scroll for table

---

**END OF INTERACTION FLOW DOCUMENTATION**
