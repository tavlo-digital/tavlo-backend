# Tavlo Admin → Vendors (Vendor Management) - Complete Specification

## Overview

This document defines the Admin → Vendors page as an **operations control list**, NOT a directory. Every element is designed to surface operational risks and enable rapid resolution of issues identified on the Admin Dashboard.

**Core Navigation Principle:**  
`Dashboard → Vendor list (filtered) → Vendor detail → Action` in **maximum 3 clicks**

---

## 1. Vendor Table Structure

### 1.1 Risk / Attention Indicator Column

**Position:** First column (before vendor name)  
**Purpose:** Instantly show whether a vendor has active alerts or operational risk

#### Indicator States

| State | Icon | Color | Meaning | Click Behavior |
|-------|------|-------|---------|----------------|
| 🔴 Payment Failure | `AlertCircle` | Red | Payment failures in last 24h | → Vendor Detail → Payments tab (filtered to failed) |
| 🟠 Subscription Expired | `AlertTriangle` | Orange | Subscription expired but vendor active | → Vendor Detail → Subscription tab |
| 🟡 Onboarding Stuck | `Clock` | Yellow | Onboarding incomplete >24h | → Vendor Detail → Overview/Onboarding section |
| ⚪ Clean | `CheckCircle` | Gray | No active issues | Not clickable (or → Overview) |

#### Hover Tooltip
- Shows detailed risk description
- Example: "3 failed payments in last 24h"
- Example: "Subscription expired 4 days ago but vendor still active"
- Example: "KYC verification incomplete for 36 hours"

#### Visual Linkage
This column creates a direct visual connection between:
- Dashboard "Alerts & Incidents" section
- Dashboard "Failed Payments" KPI
- Vendor list operational status

---

### 1.2 Payment Column (Actionable)

**Current States:** Paid / Trial / Overdue / Failed  
**Enhancement:** Make entire badge clickable

#### Hover Tooltip Content
```
Last attempt: 2 hours ago
PSP: Stripe
Error: Card declined - insufficient funds
Days overdue: 0
```

#### Click Behavior
**Destination:** Vendor Detail → Payments tab  
**Pre-applied Filters:**
- Failed or overdue payments (if applicable)
- Sorted by most recent first

#### Purpose
Directly resolves:
- Dashboard KPI: "Failed Payments (24h)"
- Dashboard Alert: "Payment Failure Spike Detected"

---

### 1.3 Subscription Column (Revenue Risk Visibility)

**Current States:** Basic / Standard / Premium / Trial  
**Enhancement:** Visual warning for revenue risk

#### Revenue Risk Display
When `subscriptionStatus === 'expired' && vendorStatus === 'active'`:

```
Premium · ⚠️ Expired · Active
```

High-contrast warning with alert icon.

#### Hover Tooltip
```
⚠️ Revenue Risk
Expired 4 days ago
```

#### Click Behavior
**Destination:** Vendor Detail → Subscription tab  
**Shows:**
- Subscription details
- Renewal and enforcement actions
- Billing history

#### Purpose
Directly resolves:
- Dashboard Alert: "Subscription Expired But Active"

---

### 1.4 Vendor Row Actions

#### View (Eye Icon)
**Click Behavior:** → Vendor Detail → Overview tab

**Overview Tab Summarizes:**
- Current risks (highlighted section)
- Payment status
- Subscription state
- Recent activity timeline

---

#### Suspend Vendor (Ban Icon)
**Click Behavior:** Opens confirmation modal

**Modal Requirements:**
1. **Forced Reason Selection** (radio buttons):
   - Non-payment
   - Fraud
   - Legal
   - Manual admin decision

2. **Optional Notes:** Text area for additional context

3. **Audit Notice:** 
   ```
   This action will be recorded in:
   - Vendor activity timeline
   - Global audit log
   ```

4. **Cannot proceed without reason**

**Logging Behavior:**
- Writes to vendor activity timeline
- Writes to global audit log with:
  - Admin user
  - Timestamp
  - Reason
  - Notes
  - Affected alerts (if applicable)

---

#### More Actions Menu (⋯ Icon)
**Explicit Actions:**
- View Payments → Vendor Detail → Payments tab
- View Subscription → Vendor Detail → Subscription tab
- View Orders → Vendor Detail → Orders tab
- View Reviews → Vendor Detail → Reviews tab
- View Audit Log → Vendor Detail → Audit/Activity tab

**Purpose:** Admins should never need sidebar navigation for vendor-specific work.

---

## 2. Quick Filters (Dashboard-Linked)

**Position:** Above vendor table, below page header

### Filter Chips

| Filter | Icon | Color | Description | Applied Filters |
|--------|------|-------|-------------|-----------------|
| 🔴 Payment Issues | `AlertCircle` | Red | Payment failures or overdue | `riskType: payment-failure OR payment: failed OR overdue` |
| 🟠 Subscription Issues | `AlertTriangle` | Orange | Expired subscriptions on active vendors | `riskType: subscription-expired` |
| 🟡 Onboarding Stuck | `Clock` | Yellow | Incomplete onboarding >24h | `riskType: onboarding-stuck` |
| ⭐ High GMV Vendors | `Star` | Purple | Revenue >€15,000 | `revenue > 15000` |
| ⚠️ Flagged Content | `Flag` | Pink | Vendors with flagged content | `contentFlagged: true` |

### Behavior
- Clicking a chip applies predefined filters instantly
- Show count badge on each filter (e.g., "Payment Issues (7)")
- Chips act as shortcuts, NOT replacements for advanced filters
- Multiple chips can be active (OR logic)
- "All Vendors" chip clears all filters

### Visual Feedback
When filter active:
```
Showing filtered results. Click filter again or "All Vendors" to clear.
```

---

## 3. Dashboard → Vendor Tab Routing Mappings

### 3.1 From Dashboard KPI: "Active Vendors Today"
**Destination:** Vendors page  
**Applied Filters:**
```typescript
{
  status: 'active',
  hasActivityToday: true,
  dateRange: 'today'
}
```
**Expected Columns:**
- Vendor name
- Orders today
- Last activity timestamp

---

### 3.2 From Dashboard KPI: "Failed Payments (24h)"
**Destination:** Vendors page  
**Applied Filters:**
```typescript
{
  paymentStatus: 'failed',
  timeRange: 'last-24-hours'
}
```
**Expected Columns:**
- Vendor name
- Payment status (highlighted as failed)
- PSP error reason
- Last payment attempt

**Visual:** Risk indicator column shows 🔴 for all affected vendors

---

### 3.3 From Dashboard Alert: "Payment Failure Spike Detected"
**Primary CTA ("View Vendor"):**
- **Destination:** Vendor Detail Page (VID-8492)
- **Tab:** Payments
- **Filters on Payments tab:**
  ```typescript
  {
    paymentStatus: 'failed',
    timeRange: 'alert-timeframe'
  }
  ```

**Secondary (Alert Body Click):**
- **Destination:** Vendors page
- **Filters:**
  ```typescript
  {
    vendorIds: ['VID-8492'], // from alert
    riskType: 'payment-failure'
  }
  ```
- **Visual:** Affected vendor(s) highlighted with 🔴 risk indicator

---

### 3.4 From Dashboard Alert: "Vendor Onboarding Stuck"
**Destination:** Vendors page  
**Applied Filters:**
```typescript
{
  status: 'pending',
  onboardingStatus: 'incomplete',
  timeStuckThreshold: '24-hours',
  sortBy: 'timeStuck-desc'
}
```
**Expected Columns:**
- Vendor name
- Missing onboarding step
- Time stuck
- Assigned admin

**Visual:** Risk indicator shows 🟡 for all affected vendors

---

### 3.5 From Dashboard Alert: "Subscription Expired But Active"
**Destination:** Vendors page  
**Applied Filters:**
```typescript
{
  subscriptionStatus: 'expired',
  vendorStatus: 'active',
  sortBy: 'expiryDate-asc'
}
```
**Expected Columns:**
- Vendor name
- Subscription plan (with "Expired · Active" warning)
- Days since expiry
- Revenue at risk

**Visual:** Risk indicator shows 🟠 for all affected vendors

---

### 3.6 From Dashboard Queue: "Flagged Reviews"
**Destination:** Vendors page  
**Applied Filters:**
```typescript
{
  hasFlaggedReviews: true,
  reviewStatus: 'flagged'
}
```
**Expected Columns:**
- Vendor name
- Review count
- Flagged review count
- Last flag date

**Visual:** Rating column shows warning badge

---

## 4. Vendor Detail Page Structure

### Required Tabs (All Must Exist)

If any tab is missing, **dashboard promises are broken**.

#### 4.1 Overview Tab
**Content:**
- **Current Risks** (highlighted section)
  - Active payment issues
  - Subscription status
  - Onboarding blockers
- **Vendor Information**
  - ID, status, location, contact
- **Quick Stats**
  - Orders, revenue, rating
- **Recent Activity Timeline**
  - Last 10 events
  - Linked to full audit log

**Purpose:** Dashboard and alert context landing page

---

#### 4.2 Payments Tab
**Content:**
- Payment history table
- PSP error details for failed payments
- Retry status
- Refund history

**Pre-filtering from Dashboard:**
- When routed from "Failed Payments" alert
- Filter shows: `paymentStatus: failed`
- Sort: Most recent first

**Actions:**
- Retry payment
- Issue refund
- Contact vendor

---

#### 4.3 Subscription Tab
**Content:**
- Current plan details
- Billing cycle
- Next billing date
- Subscription history
- Upgrade/downgrade actions
- Suspension/enforcement controls

**Revenue Risk Warning:**
When expired but active:
```
⚠️ Revenue Risk Alert
This vendor's subscription expired 4 days ago but they are still active.
[Suspend Access] [Send Renewal Notice]
```

---

#### 4.4 Orders Tab
**Content:**
- Order history
- Order status distribution
- Revenue breakdown
- Fulfillment metrics

---

#### 4.5 Reviews Tab
**Content:**
- Customer reviews
- Rating trends
- Flagged reviews (highlighted)
- Moderation actions

**Pre-filtering from Dashboard:**
- When routed from "Flagged Reviews" queue
- Filter shows: `reviewStatus: flagged`

---

#### 4.6 Activity / Audit Tab
**Content:** Vendor-scoped audit log

**Events Logged:**
- Payment attempts (success/failure)
- Subscription changes
- Menu updates
- Admin actions (suspensions, approvals)
- System events

**Metadata for Each Event:**
- Actor (Admin user, Vendor self-service, System)
- Timestamp
- Action description
- Related entity IDs

**Example Entry:**
```
🔴 Payment Failed
Card declined - insufficient funds
2 hours ago • System • Payment: PAY-3829
```

---

## 5. Audit & Accountability

### Every Action Must Log To:

1. **Vendor Activity Timeline** (vendor-scoped)
   - Visible in Vendor Detail → Activity tab
   - Shows vendor-specific events

2. **Global Audit Log**
   - Visible in Admin → Audit Log page
   - Shows all admin actions across platform

### Required Metadata for All Actions:
- **Actor:** Admin user ID and name
- **Timestamp:** ISO 8601 format
- **Action type:** (suspend, approve, refund, etc.)
- **Entity IDs:** Vendor, payment, subscription, etc.
- **Reason:** (if applicable - e.g., suspension reason)
- **Notes:** (if provided)
- **Related alerts:** Link to alert that triggered action

### Example Audit Entry:
```json
{
  "id": "AUDIT-48293",
  "timestamp": "2024-12-07T14:23:45Z",
  "actor": {
    "type": "admin",
    "id": "ADM-001",
    "name": "Sarah Chen"
  },
  "action": "vendor_suspended",
  "entityType": "vendor",
  "entityId": "VID-8492",
  "metadata": {
    "reason": "non-payment",
    "notes": "Multiple failed payment attempts",
    "relatedAlert": "ALERT-1234"
  }
}
```

---

## 6. Constraints (Must Not Be Violated)

### ❌ Forbidden
- Turn this page into analytics
- Add charts or graphs
- Hide risk signals behind modals
- Increase clicks beyond 3 from dashboard to resolution
- Allow destructive actions without logging
- Allow destructive actions without mandatory reasons

### ✅ Required
- All risk indicators visible at a glance
- Maximum 3 clicks: Dashboard → List → Detail → Action
- All actions logged with full context
- Mandatory reasons for suspensions
- Direct linkage to dashboard alerts
- Vendor detail tabs support all promised navigation

---

## 7. Implementation Files

### Core Components
- **`VendorsList_v1.1.tsx`** - Enhanced vendor list with risk indicators
- **`VendorDetailPage.tsx`** - Vendor detail with required tabs
- **`VendorRiskIndicator.tsx`** - Risk indicator component (🔴🟠🟡⚪)
- **`VendorPaymentCell.tsx`** - Clickable payment status with tooltip
- **`VendorSubscriptionCell.tsx`** - Subscription with revenue risk warning
- **`VendorQuickFilters.tsx`** - Dashboard-linked filter chips
- **`VendorSuspendModal.tsx`** - Suspension modal with mandatory reason

### Navigation Integration
- **`AdminNavigationService.ts`** - Handles all routing from dashboard
- **`AdminApp.tsx`** - Manages page state and filter context

---

## 8. Visual Annotations for Figma

### Annotation Checklist

For every clickable element, document:

- [ ] Click target (what user clicks)
- [ ] Destination page/tab
- [ ] Applied filters (if any)
- [ ] Expected state/columns on destination
- [ ] Purpose (what problem it solves)

### Example Annotation

**Element:** Risk Indicator (🔴 Payment Failure)

```
CLICK TARGET: Risk indicator icon/badge
DESTINATION: Vendor Detail → Payments tab
FILTERS: { paymentStatus: 'failed', timeRange: 'last-24h' }
EXPECTED VIEW:
- Payment history table
- Failed payments highlighted
- PSP error reasons visible
- Retry options available
PURPOSE: Resolve payment failures surfaced on dashboard
DASHBOARD LINK: "Failed Payments (24h)" KPI
```

---

## 9. Testing Scenarios

### Scenario 1: Payment Failure Alert Resolution
1. **Dashboard:** Alert "Payment Failure Spike Detected" for VID-8492
2. **Click:** "View Vendor" CTA on alert
3. **Expected:** Vendor Detail (VID-8492) → Payments tab
4. **Verify:** Failed payments filtered and visible
5. **Action:** Admin reviews PSP error, contacts vendor
6. **Log:** Action logged to vendor timeline and global audit

**Total clicks:** 1 (alert → detail)

---

### Scenario 2: Revenue Risk via Vendor List
1. **Dashboard:** Click "Subscription Expired But Active" alert
2. **Expected:** Vendors page with filter applied
3. **Verify:** Only expired-but-active vendors shown
4. **Verify:** Risk indicator shows 🟠 for all
5. **Click:** Subscription cell for VID-2847
6. **Expected:** Vendor Detail (VID-2847) → Subscription tab
7. **Action:** Admin suspends access
8. **Verify:** Suspension modal requires reason
9. **Log:** Action logged with reason and admin

**Total clicks:** 3 (dashboard → list → detail → action modal)

---

### Scenario 3: Quick Filter Usage
1. **Vendors page:** Click 🔴 "Payment Issues" filter
2. **Expected:** Table filters to payment-failure vendors
3. **Verify:** Risk indicator column shows 🔴 for all
4. **Verify:** Payment column shows "Failed" or "Overdue"
5. **Click:** Payment cell for any vendor
6. **Expected:** Vendor Detail → Payments tab
7. **Verify:** Failed payments pre-filtered

**Total clicks:** 3 (filter → list → detail)

---

## 10. Maintenance Guidelines

### When Adding New Risk Types
1. Add risk type to `VendorRiskType` enum
2. Add configuration to `VendorRiskIndicator` component
3. Add corresponding quick filter
4. Document dashboard linkage
5. Update Figma annotations

### When Adding New Actions
1. Implement action handler
2. Add audit log entry
3. Add vendor timeline entry
4. Add to "More Actions" menu if applicable
5. Document in this spec

### When Modifying Vendor Detail Tabs
1. Ensure tab supports all dashboard navigation promises
2. Update navigation service if filters change
3. Update vendor detail tab list in this spec
4. Verify 3-click rule not broken

---

## Conclusion

The Vendor Management page is the **primary execution layer** for resolving issues surfaced on the Admin Dashboard. Every design decision prioritizes:

1. **Operational clarity** - Risk signals visible immediately
2. **Speed to action** - Maximum 3 clicks to resolution
3. **Accountability** - All actions logged with context
4. **Dashboard linkage** - Direct navigation from alerts and KPIs

This is NOT a vendor directory. This is a vendor operations control center.
