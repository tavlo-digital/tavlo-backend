# Tavlo Admin Dashboard - Navigation Specification & Implementation

## Overview

This document provides a complete reference for all clickable elements in the Tavlo Admin Dashboard, defining exact destinations, filters, and expected views for every interaction.

**Core Principle:** Every clickable element MUST route to an existing admin base page with pre-applied filters or direct entity context. Admins act on LISTS and ENTITY DETAIL PAGES, never summaries.

---

## Allowed Destinations

All navigation must route to one of these pages:

1. **Vendors** (`vendors`)
2. **Customers** (`customers`)
3. **Billing & Invoices** (`billing`)
4. **Subscriptions** (`subscriptions`)
5. **Reviews & Complaints** (`reviews`)
6. **Audit Log** (`audit-log`)
7. **Insights & Analysis** (`ai-insights`)

---

## Forbidden Navigation Patterns

❌ **NEVER** route to:
- Another dashboard or summary view
- Chart-only modal windows
- Empty or unfiltered list pages
- System settings pages
- Views without actionable content

✅ **ALWAYS** route to:
- Filtered list views with actionable items
- Entity detail pages with full context
- Views that enable immediate admin action

---

## 1. Platform Health Cards

### Active Vendors Today
**Click Target:** KPI card showing active vendor count  
**Destination:** `vendors`  
**Pre-applied Filters:**
- `status`: `active`
- `hasActivityToday`: `true`
- `dateRange`: `today`

**Expected Columns:**
- Vendor name
- Vendor status
- Orders today
- Last activity timestamp

**Purpose:** Identify which vendors are currently active and operational

---

### Active Customers Today
**Click Target:** KPI card showing active customer count  
**Destination:** `customers`  
**Pre-applied Filters:**
- `lastActivityDate`: `today`
- `hasOrderToday`: `true`

**Expected Columns:**
- Customer ID
- Last order ID
- Vendor
- Payment status

**Purpose:** See who is actively using the platform today

---

### Orders Today
**Click Target:** KPI card showing today's order count  
**Destination:** `billing`  
**Pre-applied Filters:**
- `orderDate`: `today`
- `entityType`: `orders`

**Expected Columns:**
- Order ID
- Vendor
- Order amount
- Payment status
- Fulfillment type (dine-in, takeaway, etc.)

**Purpose:** Operational monitoring of today's order flow

---

### GMV Today
**Click Target:** KPI card showing today's gross merchandise value  
**Destination:** `ai-insights`  
**Pre-applied Filters:**
- `timeRange`: `today`
- `metric`: `gmv`
- `viewType`: `breakdown`

**Expected View:**
- GMV broken down by vendor
- Payment method split
- Transaction count

**Purpose:** Financial overview only (read-only analytics)

---

### Failed Payments (24h)
**Click Target:** KPI card showing failed payment count (⚠️ Direct Action Card)  
**Destination:** `billing`  
**Pre-applied Filters:**
- `paymentStatus`: `failed`
- `timeRange`: `last-24-hours`
- `entityType`: `payments`

**Expected Columns:**
- Payment ID
- Order ID
- Vendor
- **PSP error reason** (critical for diagnosis)
- Retry status

**Purpose:** Immediate revenue risk resolution

---

### Open Support Tickets
**Click Target:** KPI card showing open ticket count  
**Destination:** `reviews`  
**Pre-applied Filters:**
- `ticketStatus`: `open`
- `entityType`: `support-tickets`

**Expected Columns:**
- Ticket ID
- Category
- Vendor or Customer reference
- Open duration

**Purpose:** Support backlog visibility

---

## 2. Alerts & Incidents

> **Rule:** Alerts NEVER route to dashboards or charts. They ALWAYS route to filtered lists or entity detail pages.

### Payment Failure Spike Detected
**Alert Type:** Critical  
**Primary CTA:** "View Vendor" button

**Primary Action:**
- **Destination:** `vendors` → Vendor Detail Page → Payments tab
- **Entity ID:** Extracted from alert (e.g., `VID-8492`)
- **Tab:** `payments`
- **Tab Filters:**
  - `paymentStatus`: `failed`
  - `timeRange`: `alert-timeframe`

**Secondary Action** (clicking alert body):
- **Destination:** `billing`
- **Filters:**
  - `paymentStatus`: `failed`
  - `vendorId`: from alert context
  - `timeRange`: `alert-timeframe`
  - `entityType`: `payments`

**Purpose:** Investigate specific vendor payment issues with immediate context

---

### Vendor Onboarding Stuck
**Alert Type:** Warning  
**Primary CTA:** "Review Queue" button

**Destination:** `vendors`  
**Pre-applied Filters:**
- `onboardingStatus`: `incomplete`
- `timeStuckThreshold`: `24-hours`
- `sortBy`: `timeStuck-desc`

**Expected Columns:**
- Vendor name
- Missing onboarding step
- Time stuck
- Assigned admin

**Purpose:** Resolve onboarding bottlenecks

---

### Subscription Expired But Active
**Alert Type:** Critical  
**Primary CTA:** "Suspend Access" button

**Destination:** `subscriptions`  
**Pre-applied Filters:**
- `subscriptionStatus`: `expired`
- `vendorStatus`: `active`
- `sortBy`: `expiryDate-asc`

**Expected Columns:**
- Vendor name
- Subscription plan
- Expiry date
- **Revenue at risk indicator**

**Purpose:** Prevent revenue leakage from unmonetized service usage

---

## 3. Action Queues

> **Rule:** Action queues ALWAYS route to queue-based filtered list views, NOT dashboards.

### Vendors Pending Approval
**Click Target:** Entire action queue card  
**Destination:** `vendors`  
**Pre-applied Filters:**
- `approvalStatus`: `pending`
- `onboardingStatus`: `pending-approval`
- `sortBy`: `submittedDate-asc`

**Expected Columns:**
- Vendor name
- Business type
- Submitted date
- KYC status
- Assigned reviewer

---

### KYC Verification Failed
**Click Target:** Entire action queue card  
**Destination:** `vendors`  
**Pre-applied Filters:**
- `kycStatus`: `failed`
- `sortBy`: `failureDate-desc`

**Expected Columns:**
- Vendor name
- Failure reason
- Failure date
- Attempt count
- Assigned admin

---

### Refunds Awaiting Approval
**Click Target:** Entire action queue card  
**Destination:** `billing`  
**Pre-applied Filters:**
- `refundStatus`: `pending-approval`
- `entityType`: `refunds`
- `sortBy`: `requestDate-asc`

**Expected Columns:**
- Refund ID
- Order ID
- Vendor
- Amount
- Reason
- Request date

---

### Open Disputes
**Click Target:** Entire action queue card  
**Destination:** `billing`  
**Pre-applied Filters:**
- `disputeStatus`: `open`
- `entityType`: `disputes`
- `sortBy`: `urgency-desc`

**Expected Columns:**
- Dispute ID
- Payment ID
- Vendor
- Amount
- Dispute type
- Deadline (time-sensitive!)

---

### Flagged Reviews
**Click Target:** Entire action queue card  
**Destination:** `reviews`  
**Pre-applied Filters:**
- `reviewStatus`: `flagged`
- `entityType`: `reviews`
- `sortBy`: `flagDate-desc`

**Expected Columns:**
- Review ID
- Vendor
- Flag reason
- Flag date
- Reviewer count

---

### Content Moderation Needed
**Click Target:** Entire action queue card  
**Destination:** `vendors`  
**Pre-applied Filters:**
- `contentFlagged`: `true`
- `moderationStatus`: `pending`
- `sortBy`: `flagDate-desc`

**Expected Columns:**
- Vendor name
- Content type (menu item, profile, image)
- Flag reason
- Flag date
- Assigned moderator

---

## 4. Last 24h Activity Feed

> **Rule:** Each activity row links directly to the relevant entity detail page, NEVER a summary.

### Activity Routing Pattern
Every activity type routes based on this logic:

| Activity Type | Destination | Entity Type | Additional Context |
|--------------|-------------|-------------|-------------------|
| Vendor Created | `vendors` | `vendor` | Vendor detail page |
| Payment Failed | `billing` | `payment` | Payment detail with PSP error |
| Payment Successful | `billing` | `payment` | Payment detail |
| Vendor Unsubscribed | `vendors` | `vendor` | Vendor detail → Subscription tab |
| Menu Published | `vendors` | `vendor` | Vendor detail → Menu tab |
| Payment Refunded | `billing` | `payment` | Payment detail → Refund tab |
| Review Flagged | `reviews` | `review` | Review detail for moderation |
| Vendor Activated | `vendors` | `vendor` | Vendor detail → Subscription tab |

**Purpose:** Fast context switching and investigation with full entity context

---

## 5. System Status Indicator

**Click Target:** System status indicator in top bar (e.g., "All systems operational")  
**Destination:** `ai-insights` with system status view  
**Filters:**
- `view`: `system-status`

**Displayed Information:**
- PSP availability and response times
- Webhook health and delivery status
- Notification delivery status
- Recent incidents and SLA metrics

**Purpose:** Platform confidence and incident validation

---

## 6. Quick Trends

### Orders (24h)
**Click Target:** Orders trend card  
**Destination:** `ai-insights`  
**Filters:**
- `timeRange`: `last-24-hours`
- `metric`: `orders`
- `viewType`: `trend-analysis`

**Purpose:** Analyze order volume trends (read-only)

---

### Payment Success Rate
**Click Target:** Payment success rate card  
**Destination:** `ai-insights`  
**Filters:**
- `metric`: `payment-success-rate`
- `timeRange`: `last-24-hours`
- `viewType`: `breakdown`

**Purpose:** Analyze payment performance and failure patterns (read-only)

---

## 7. Global Search

### Search Input Behavior
**Trigger:** User types query and presses Enter  
**Example queries:**
- `vendor: Bella Italia`
- `order: #ORD-48293`
- `payment: pi_3M8yK2`
- `qr: QR-982`

### Search Results Page
**Destination:** Dedicated `AdminSearchResults` component  
**Display:** Results grouped by entity type:
- Vendors
- Orders
- Payments
- Subscriptions
- QR Codes

**Click Result:** Navigate to appropriate detail page based on entity type

---

## Implementation Files

### Core Files
1. **`AdminNavigationSpec.ts`** - Complete navigation specification with TypeScript types
2. **`AdminNavigationService.ts`** - Navigation service with all routing methods
3. **`AdminDashboard_v1.1.tsx`** - Dashboard implementation using navigation service
4. **`AdminSearchResults.tsx`** - Unified search results page

### Component Files (v1.1)
- `KPICard_v1.1.tsx` - Enhanced KPI cards with comparison data
- `AlertCard_v1.1.tsx` - Alert cards with metadata (open duration, assignment)
- `ActionQueueCard_v1.1.tsx` - Action queue cards with priority and aging
- `ActivityFeedItem_v1.1.tsx` - Color-coded activity feed items
- `SystemStatusIndicator.tsx` - System status display component

---

## Usage Example

```typescript
import { createAdminNavigationService } from './AdminNavigationService';

// In your component
const navigationService = createAdminNavigationService((state) => {
  // Handle navigation
  setCurrentPage(state.page);
  setPageFilters(state.filters || {});
});

// Use in KPI card
<KPICard_v1_1
  label="Failed Payments (24h)"
  value={7}
  icon={CreditCard}
  variant="warning"
  onClick={() => navigationService.navigateToFailedPayments24h()}
/>

// Use in alert
<AlertCard_v1_1
  severity="critical"
  title="Payment Failure Spike Detected"
  affectedEntity='Vendor "Bella Italia" (VID-8492)'
  onAction={() => navigationService.navigateToPaymentFailureSpike('VID-8492', true)}
/>
```

---

## Testing Checklist

When implementing or testing navigation:

- [ ] Click navigates to correct destination page
- [ ] Filters are correctly applied on destination
- [ ] Entity ID/context is preserved when applicable
- [ ] Expected columns are visible in list view
- [ ] No navigation routes back to dashboard
- [ ] No empty or unfiltered lists are displayed
- [ ] Toast notifications show navigation context
- [ ] Navigation state is logged to console for debugging

---

## Anti-Patterns to Avoid

### ❌ INCORRECT
```typescript
// Don't reload dashboard
onClick={() => window.location.reload()}

// Don't show chart-only modal
onClick={() => setShowChartModal(true)}

// Don't navigate to settings
onClick={() => navigateTo('settings')}

// Don't show unfiltered list
onClick={() => navigateTo('vendors', {})}
```

### ✅ CORRECT
```typescript
// Navigate to filtered list
onClick={() => navigationService.navigateToFailedPayments24h()}
// Results in: /billing with filters: { paymentStatus: 'failed', timeRange: 'last-24-hours' }

// Navigate to entity detail
onClick={() => navigationService.navigateToVendorCreated('VID-9482')}
// Results in: /vendors/VID-9482
```

---

## Maintenance Notes

1. **When adding new KPIs:** Define navigation destination and filters in `AdminNavigationSpec.ts`
2. **When adding new alerts:** Specify primary and secondary actions with proper filters
3. **When adding new queues:** Always route to filtered list, never summary
4. **When adding new activities:** Route to entity detail with appropriate tab/context

All navigation changes must be documented in this file and implemented in the navigation service.
