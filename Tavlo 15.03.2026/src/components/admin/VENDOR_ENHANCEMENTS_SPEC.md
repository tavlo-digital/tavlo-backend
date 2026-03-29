# Tavlo Admin → Vendors - Operational Enhancements Specification

## Overview

This document specifies the comprehensive enhancements to the Admin → Vendors page, transforming it from a simple list view into a complete operational command center. These enhancements eliminate the need for spreadsheets, external tools, or manual lookups.

**Core Principle:** If admins still need external tools after these enhancements, the design is incomplete.

---

## A. Vendor Management Page (List View) — Enhancements

### A1. Vendor Summary Counters

**Position:** Directly below filter bar, above vendor table

#### Counters Display:

| Counter | Icon | Color | Description | Filter Applied |
|---------|------|-------|-------------|---------------|
| Total Vendors | `Store` | Gray | All vendors in system | None (shows all) |
| Active Vendors | `CheckCircle` | Green | Active subscription | `status: Active` |
| Inactive Vendors | `XCircle` | Red | Suspended or inactive | `status: Inactive OR Suspended` |
| Live Vendors | `Globe` | Blue | Menu published + accepting orders | `liveStatus: Live` |
| Not Live Vendors | `GlobeLock` | Orange | Menu unpublished OR orders disabled | `liveStatus: Not Live` |

#### Behavior:

- **Clickable:** Each counter acts as a filter toggle
- **Visual State:** Active counter highlighted with ring border
- **Combinable:** Cannot combine counters (exclusive selection)
- **Clearable:** Click active counter again to clear filter

#### Purpose:
Provide immediate system-wide visibility without returning to dashboard. Admins can instantly see platform health metrics.

---

### A2. Extended Filters (Advanced Filtering)

**Position:** Below summary counters, collapsible panel

#### Filter Categories:

##### 1. Subscription Plan Filter
- Basic
- Standard
- Premium
- Trial

**Type:** Multi-select checkboxes  
**Behavior:** OR logic (show vendors with ANY selected plan)

---

##### 2. Country Filter
- Austria
- Germany
- Switzerland
- *(expandable list)*

**Type:** Multi-select checkboxes  
**Behavior:** 
- OR logic for countries
- Affects City filter options

---

##### 3. City Filter (Dependent on Country)
- Vienna, Salzburg, Innsbruck (if Austria selected)
- Munich, Berlin, Hamburg (if Germany selected)
- Zurich, Geneva, Basel (if Switzerland selected)

**Type:** Multi-select checkboxes  
**Behavior:** 
- Dependent on Country filter
- Shows only cities from selected countries
- If no country selected, shows all cities

---

##### 4. Live Status Filter
- Live (menu published + accepting orders)
- Not Live (menu unpublished OR orders disabled)
- Both (default)

**Type:** Radio buttons  
**Behavior:** Mutually exclusive

---

##### 5. Subscription State Filter
- Active
- Expired
- Trial
- Overdue

**Type:** Multi-select checkboxes  
**Behavior:** OR logic

---

#### Filter Controls:

**Toggle Button:**
```
[Filter Icon] Advanced Filters (3)
```
- Badge shows active filter count
- Purple highlight when panel open
- Persists state across sessions

**Active Filter Chips:**
- Display above table when filters applied
- Each chip removable individually
- Color-coded by category:
  - Plan: Blue
  - Country: Green
  - City: Teal
  - Live Status: Purple
  - Subscription: Orange

**Clear All Action:**
- Always visible when filters active
- Resets ALL filters (counters, quick, extended, dashboard)

---

### A3. Export Function (Excel)

**Position:** Top-right of table, next to filter controls

#### Export Button:
```
[Download Icon] Export
```
- Green background (`bg-green-600`)
- Opens export options menu

#### Export Options:

##### 1. Export Selected
**Condition:** Only shown if vendors selected  
**Label:** `Export Selected (5)`  
**Description:** Only export selected vendors

##### 2. Export All
**Label:** `Export All (42)`  
**Description:** 
- If filtered: "Respects current filters"
- If unfiltered: "Export all vendors in system"

#### Export Data Fields:

| Field | Source | Format |
|-------|--------|--------|
| Vendor ID | `vendor.id` | String |
| Vendor Name | `vendor.name` | String |
| Category/Type | `vendor.category` | String |
| Country | `vendor.country` | String |
| City | `vendor.city` | String |
| Address | `vendor.address` | String |
| Status | `vendor.status` | Active/Inactive/Pending/Suspended |
| Live Status | `vendor.liveStatus` | Live/Not Live |
| Subscription Plan | `vendor.subscription` | Basic/Standard/Premium/Trial |
| Subscription State | `vendor.subscriptionStatus` | Active/Expired/Trial/Overdue |
| Payment Status | `vendor.payment` | Paid/Trial/Overdue/Failed |
| Total GMV | `vendor.revenue` | Currency |
| Rating | `vendor.rating` | Number (1-5) |
| Last Active | `vendor.lastActive` | Timestamp |
| Contact Email | `vendor.email` | String |
| Phone | `vendor.phone` | String |
| Website | `vendor.website` | URL |
| VAT Number | `vendor.vat` | String |
| Created Date | `vendor.createdDate` | Date |

#### Export Behavior:

**Large Export Warning:**
- If count > 100: Show confirmation dialog
- Message: "You are about to export {count} vendors. Continue?"
- Prevents accidental large exports

**Export Logging:**
- Log to Audit Log with:
  - Admin user
  - Timestamp
  - Export type (selected/filtered/all)
  - Record count
  - Applied filters (if any)

**File Format:**
- Excel (.xlsx)
- Includes column headers
- Auto-sized columns
- Freeze top row

---

### A4. Context Banner (Dashboard → Vendor Page)

**Position:** Top of page, above summary counters

**Trigger:** When navigating from Admin Dashboard with filters

#### Example Banner:
```
Showing vendors with failed payments in the last 24h
→ Source: Dashboard → Failed Payments (24h)
[Clear filters]
```

#### Banner Elements:

1. **Description:** Filter reason in plain language
2. **Source:** Navigation path from dashboard
3. **Clear Action:** Button to remove all dashboard filters

#### Behavior:

- Appears automatically when `dashboardContext` provided
- Purple background (`bg-purple-50`)
- Dismissible via "Clear filters" button
- Disappears when any filter manually changed

#### Purpose:
Maintain context when drilling down from dashboard. Admins know exactly why they're seeing this filtered view.

---

## B. Vendor Detail Page — Missing Sections & Enhancements

### B1. Vendor Overview → Contact & Legal Details

**Position:** Overview tab, after quick stats, before recent activity

#### Component: `VendorContactInfo`

##### Fields Displayed:

| Field | Icon | Required | Features |
|-------|------|----------|----------|
| Business Name | `Building2` | Yes | — |
| Contact Email | `Mail` | Yes | Copyable |
| Phone Number | `Phone` | Yes | Copyable |
| Website | `Globe` | No | Clickable link |
| VAT Number | `FileText` | No | Copyable |
| Legal Entity Name | `Building2` | No | — |
| Registered Address | `MapPin` | Yes | Full width |
| City | `MapPin` | Yes | — |
| Country | `MapPin` | Yes | — |

##### Field Behaviors:

**Missing Required Fields:**
- Show warning icon (`AlertCircle`)
- Display: "Missing required field" in orange
- Highlight in overview summary

**Copyable Fields:**
- Clipboard icon button
- Toast confirmation on copy
- Email, Phone, VAT

**Clickable Links:**
- Website opens in new tab
- External link indicator

**Edit Permission:**
- `canEdit` prop controls edit button
- Role-based access control
- Edit action logs to audit trail

#### Purpose:
Eliminate need to check external CRM, email, or vendor database. All contact/legal info in one place.

---

### B2. Vendor Detail → Payments Tab — Invoice Grouping & Download

**Position:** Payments tab (replaces basic payment history)

#### Component: `VendorPaymentsTab`

##### Features:

**1. Invoice Grouping:**

**By Year:**
```
Year 2024                    4 paid, 1 unpaid    Total: €599.96
└─ December 2024            Download Month ↓
   └─ INV-2024-12  €149.99  Unpaid    Due: Dec 15    [PDF]
└─ November 2024            Download Month ↓
   └─ INV-2024-11  €149.99  Paid      Nov 5          [PDF]
```

**Hierarchy:**
- Year Header → Download Year action
- Month Sections → Download Month action
- Individual Invoices → Download PDF action

---

**2. Download Options:**

| Action | Scope | Format | Logging |
|--------|-------|--------|---------|
| Download Invoice | Single invoice | PDF | Logs invoice ID |
| Download Month | All invoices in month | ZIP of PDFs | Logs month + count |
| Download Year | All invoices in year | ZIP of PDFs | Logs year + count |

**Download Logging:**
- Log to Audit Log for traceability
- Includes: Admin user, timestamp, invoice IDs
- Purpose: Finance audit trail, dispute resolution

---

**3. Invoice Table (per month):**

| Column | Data | Format |
|--------|------|--------|
| Invoice ID | `INV-2024-12` | Monospace font |
| Amount | €149.99 | Currency |
| Status | Paid/Unpaid/Overdue | Color-coded badge |
| Date | Paid: Nov 5 / Due: Dec 15 | Conditional |
| Actions | [PDF] button | Download link |

**Status Colors:**
- Paid: Green (`bg-green-100 text-green-700`)
- Unpaid: Yellow (`bg-yellow-100 text-yellow-700`)
- Overdue: Red (`bg-red-100 text-red-700`)

---

**4. Period Summaries:**

**Year Summary:**
- Total invoiced amount
- Paid count / Unpaid count
- Download all button

**Month Summary:**
- Total for month
- Paid/unpaid ratio
- Download month button

#### Purpose:
Support finance workflows, accounting reconciliation, and vendor dispute resolution without external tools.

---

### B3. Vendor Detail → Payments Tab — Filtering

**Position:** Top of Payments tab, collapsible filter bar

#### Filter Options:

##### 1. Date Range
- All Time (default)
- This Month
- This Year
- Custom Range

**Type:** Dropdown select

---

##### 2. Invoice Status
- All Statuses (default)
- Paid
- Unpaid
- Overdue

**Type:** Dropdown select

---

##### 3. Payment Method / PSP
- All Methods (default)
- Stripe
- PayPal

**Type:** Dropdown select

---

#### Filter Behavior:

**Scope:** Filters apply only within vendor context (not global)

**Visual Feedback:**
- Purple highlight when filter active
- Filter count badge
- Applied filters shown below filter bar

**Performance:**
- Client-side filtering for < 100 invoices
- Server-side filtering for > 100 invoices

#### Purpose:
Quick access to specific payment periods or problem invoices for investigation.

---

## C. Consistency & Logging Rules

### Export Actions

**Logged Data:**
```json
{
  "action": "vendor_export",
  "timestamp": "2024-12-07T14:30:00Z",
  "admin": {
    "id": "ADM-001",
    "name": "Sarah Chen"
  },
  "details": {
    "exportType": "filtered",
    "recordCount": 42,
    "appliedFilters": {
      "plan": ["Premium"],
      "country": ["Austria"],
      "paymentStatus": ["failed"]
    }
  }
}
```

---

### Vendor Data Edits

**Logged Data:**
```json
{
  "action": "vendor_data_edit",
  "timestamp": "2024-12-07T14:35:00Z",
  "admin": {
    "id": "ADM-001",
    "name": "Sarah Chen"
  },
  "vendorId": "VID-8492",
  "field": "email",
  "before": "old@bellaitalia.at",
  "after": "contact@bellaitalia.at",
  "reason": "Vendor requested email update"
}
```

---

### Invoice Downloads

**Logged Data:**
```json
{
  "action": "invoice_download",
  "timestamp": "2024-12-07T14:40:00Z",
  "admin": {
    "id": "ADM-001",
    "name": "Sarah Chen"
  },
  "vendorId": "VID-8492",
  "invoiceIds": ["INV-2024-11", "INV-2024-12"],
  "downloadType": "month",
  "period": "December 2024"
}
```

---

## D. Constraints (Must Not Be Violated)

### ❌ Forbidden

1. **No analytics charts** on vendor list
2. **No dashboard-style views** replacing list
3. **No exports without permission checks**
4. **No destructive actions without logging**
5. **No 4+ click workflows** from dashboard to action
6. **No hidden risk signals**

### ✅ Required

1. **All exports logged** to Audit Log
2. **All edits logged** with before/after values
3. **Invoice downloads traced** for compliance
4. **Filters combinable** for complex queries
5. **Empty states clear** and actionable
6. **Performance maintained** with 1000+ vendors

---

## E. Implementation Files

### New Components

1. **`VendorSummaryCounters.tsx`** - Clickable summary counters
2. **`VendorExtendedFilters.tsx`** - Advanced filtering panel
3. **`VendorExportButton.tsx`** - Excel export with logging
4. **`VendorContextBanner.tsx`** - Dashboard context indicator
5. **`VendorContactInfo.tsx`** - Contact & legal details display
6. **`VendorPaymentsTab.tsx`** - Enhanced payments with invoices

### Enhanced Components

1. **`VendorsList_v1.2.tsx`** - Integrated all new features
2. **`VendorDetailPage.tsx`** - Added contact info, enhanced payments

### Integration

- **`AdminApp.tsx`** - Handles dashboard context and audit logging
- **`AdminNavigationService.ts`** - Routes dashboard→vendor with context

---

## F. Desktop-First Design Requirements

### Minimum Resolution
- **1440px width minimum**
- **900px height minimum**
- No mobile responsive breakpoints needed

### Grid Layouts
- Summary counters: 5-column grid
- Extended filters: 4-column grid
- Contact info: 2-column grid

### Typography
- Counter values: `text-2xl`
- Section headers: `text-lg font-semibold`
- Table text: `text-sm`

---

## G. Testing Checklist

### Vendor List

- [ ] Summary counters filter correctly
- [ ] Extended filters combine properly
- [ ] Export includes all specified fields
- [ ] Export logs to audit trail
- [ ] Context banner displays when from dashboard
- [ ] Clear filters resets all state
- [ ] Active filter chips removable
- [ ] Empty states show clear messaging

### Vendor Detail

- [ ] Contact info displays all fields
- [ ] Missing required fields highlighted
- [ ] Copyable fields copy to clipboard
- [ ] Website link opens in new tab
- [ ] Invoice grouping displays by year/month
- [ ] Download invoice generates PDF
- [ ] Download month/year creates ZIP
- [ ] Invoice downloads logged to audit
- [ ] Payment filters work within vendor scope
- [ ] Failed payments alert shows when applicable

### Audit Logging

- [ ] Export action logged with filter context
- [ ] Edit action logged with before/after
- [ ] Invoice download logged with IDs
- [ ] Suspension logged with reason
- [ ] All logs include admin user + timestamp

---

## H. Final Validation

**Question:** Can an admin complete the following without leaving the Vendor page?

1. ✅ Find all Austrian Premium vendors with failed payments
2. ✅ Export their contact details for outreach
3. ✅ View a specific vendor's legal entity and VAT
4. ✅ Download all invoices from Q4 2024
5. ✅ Filter to unpaid invoices only
6. ✅ Suspend a vendor with documented reason

**If ANY answer is "No", the design is incomplete.**

---

## Conclusion

These enhancements transform the Vendor Management page from a simple list into a complete operational command center. Admins can now:

- **Filter** with surgical precision (6 filter categories + counters)
- **Export** for external workflows (Excel with full vendor data)
- **View** complete vendor context (contact, legal, payments, invoices)
- **Download** financial documents (invoices by period)
- **Audit** all actions (comprehensive logging)

**Zero external tools required. Zero manual lookups. Zero spreadsheet workarounds.**

The Vendor page is where problems get fixed. Period.
