# Vendors Management

# 1. Purpose of Vendors Management

The **Vendors Management module** is the central administrative interface used by Tavlo administrators to manage restaurants (vendors) on the platform.

Administrators must be able to:

- View all vendors
- Filter vendors by operational status
- Detect payment issues
- Detect subscription problems
- Detect onboarding issues
- Review vendor legal changes
- Manage subscriptions
- Inspect payments
- Suspend vendors
- Export vendor data
- Track vendor activity

This module is the **primary operational control center** for Tavlo vendor lifecycle management.

---

# 2. Vendor Data Model

Each vendor represents a **restaurant or food business using Tavlo**.

Core entity:

```
Vendor
```

Main fields:

```
vendor_id (UUID)
vendor_public_id (VID-XXXX)
restaurant_name
legal_entity_name
business_registration_number
vat_number
phone_number
contact_email
website
country
city
address

status
live_status
risk_level

subscription_plan
subscription_status
subscription_start
subscription_next_billing

payment_status
payment_last_success
payment_failures_last_24h

orders_count
revenue_total

users_used
users_limit

created_at
updated_at
last_activity_at
```

---

# 3. Vendor Status Logic

Three independent status systems exist.

### Vendor Operational Status

```
Active
Pending
Suspended
Inactive
```

Meaning:

Active

Vendor is operational.

Pending

Vendor onboarding incomplete.

Suspended

Admin disabled vendor.

Inactive

Vendor account exists but not operational.

---

### Live Status

Controls customer visibility.

```
Live
Not Live
```

Live

Customers can order.

Not Live

Vendor hidden from customer app.

---

### Payment Status

```
Paid
Trial
Overdue
Failed
```

Meaning:

Paid

Latest subscription payment successful.

Trial

Vendor currently in free trial.

Overdue

Invoice unpaid past due date.

Failed

Recent payment attempts failed.

---

### Risk Level

Visual indicator used for quick admin detection.

Possible values:

```
None
Warning
Critical
```

Examples:

Critical

Repeated payment failure.

Warning

Subscription expired.

---

# 4. Vendors Management Page

This page lists all vendors.

Path:

```
Admin → Vendors Management
```

Layout sections:

```
Top KPIs
Search Bar
Quick Filters
Advanced Filters
Vendor Table
Export Button
```

---

# 5. KPI Counters (Top Cards)

Five cards appear at the top.

```
Total Vendors
Active Vendors
Inactive Vendors
Live Vendors
Not Live Vendors
```

Each card is clickable.

Clicking applies a filter to the table.

Example:

Click **Active Vendors**

Filter applied:

```
vendor.status = Active
```

---

### Card definitions

**Total Vendors**

All vendors in database.

Query:

```
SELECT COUNT(*)
FROM vendors
```

---

**Active Vendors**

```
status = Active
```

---

**Inactive Vendors**

```
status = Inactive
```

---

**Live Vendors**

```
live_status = Live
```

---

**Not Live Vendors**

```
live_status = Not Live
```

---

### Card UI behavior

When clicked:

- card becomes highlighted

• table reloads

• filter indicator appears

Click again → clears filter.

---

# 6. Global Search

Search bar allows searching across:

```
Vendor Name
Vendor ID
Email
Phone
VAT
Address
```

Example query:

```
SELECT *
FROM vendors
WHERE
restaurant_name ILIKE '%query%'
OR vendor_public_id ILIKE '%query%'
OR contact_email ILIKE '%query%'
```

Search is **debounced (300ms)**.

---

# 7. Quick Filters

Quick filters appear below the KPI cards.

Filters:

```
All Vendors
Payment Issues
Subscription Issues
Onboarding Stuck
High GMV Vendors
Flagged Content
```

These are predefined queries.

---

### Payment Issues

Condition:

```
payment_status IN ('Failed','Overdue')
```

---

### Subscription Issues

Condition:

```
subscription_status = Expired
```

---

### Onboarding Stuck

Condition:

```
status = Pending
AND onboarding_time > threshold
```

---

### High GMV Vendors

Condition:

```
revenue_total > configured_threshold
```

Example:

```
> €20,000
```

---

### Flagged Content

Vendor has flagged items or complaints.

```
flagged_reviews > 0
```

---

# 8. Advanced Filters

Expands a full filter panel.

Fields:

```
Subscription Plan
Country
City
Live Status
Subscription Status
```

---

### Subscription Plan

```
Basic
Standard
Premium
Trial
```

---

### Country

Multiselect.

Example:

```
Austria
Germany
Switzerland
```

---

### City

Example:

```
Vienna
Salzburg
Innsbruck
Graz
```

---

### Live Status

```
Live
Not Live
Both
```

---

### Subscription Status

```
Active
Expired
Trial
Overdue
```

---

Filters combine using **AND logic**.

---

# 9. Vendor Table

Displays filtered vendors.

Columns:

```
Checkbox
Risk
Vendor
Status
Subscription
Payment
Orders
Revenue
Actions
```

---

# 10. Column Logic

### Checkbox

Allows bulk actions.

Future features:

```
Bulk suspend
Bulk export
Bulk message
```

---

### Risk Column

Visual risk indicator.

Examples:

Red icon

Critical risk

Orange icon

Warning

Gray dot

No risk

Backend logic:

```
risk_level
```

---

### Vendor Column

Displays:

```
Restaurant Name
Vendor Public ID
```

Example:

```
Bella Italia
VID-8492
```

Clicking vendor name opens:

```
Vendor Details Page
```

---

### Status Column

Values:

```
Active
Pending
Suspended
Inactive
```

Color coded.

---

### Subscription Column

Shows:

```
Plan
Status
```

Example:

```
Premium
Standard (Expired)
Trial
Basic
```

Clicking this field opens:

```
Vendor Details → Subscription Tab
```

---

### Payment Column

Values:

```
Paid
Trial
Overdue
Failed
```

Hover tooltip displays error reason.

Example tooltip:

```
PSP: Stripe
Error: Card declined - insufficient funds
Last attempt: 2 hours ago
```

Clicking opens:

```
Vendor Details → Payments Tab
```

---

### Orders Column

Total order count.

```
orders_count
```

---

### Revenue Column

Total platform revenue generated by vendor.

```
revenue_total
```

Currency based on system region.

---

# 11. Actions Column

Icons:

```
View
Suspend
More menu
```

---

### View

Opens Vendor Details.

---

### Suspend

Triggers suspension modal.

---

### More Menu

Options:

```
View Payments
View Subscription
View Orders
View Reviews
View Audit Log
```

Each opens corresponding tab.

---

# 12. Export Button

Top right of table.

Exports vendor data.

Options:

```
Export All Vendors
Export Selected Vendors
```

Export format:

```
Excel (.xlsx)
```

Fields exported:

```
Vendor ID
Name
Category
Country
City
Address
Status
Live Status
Subscription Plan
Payment Status
GMV
Rating
Contact Email
Phone
Website
VAT
Created At
Last Activity
```

---

# 13. Suspend Vendor Flow

Admin presses suspend icon.

Modal opens.

Fields:

```
Suspension Reason
Additional Notes
```

---

### Suspension Reasons

```
Non-Payment
Fraud
Legal
Manual Admin Decision
```

Descriptions visible.

Example:

```
Non-Payment
Subscription expired or payment failed
```

---

### Suspension Logic

When confirmed:

```
vendor.status = Suspended
vendor.live_status = Not Live
```

Customer side effects:

```
Vendor hidden from ordering
Menu inaccessible
Orders disabled
```

Audit log entry created.

---

# 14. Vendor Details Page

Structure:

Tabs:

```
Overview
Pending Changes
Payments
Subscription
Orders
Reviews
Activity
```

---

# 15. Overview Tab

Shows vendor summary.

Sections:

```
Active Issues
Vendor Summary
Users
Contact & Legal Details
Recent Activity
```

---

### Active Issues

Examples:

```
Payment failures detected (3 in last 24h)
Last successful payment 5 days ago
```

---

### Vendor Summary

Fields:

```
Vendor ID
Status
Subscription
Payment Status
```

---

### Users

Shows:

```
Users used
User limit
```

Example:

```
Used: 7
Allowed: 15
```

---

### Contact & Legal Details

Fields:

```
Business Name
Phone
VAT
Address
City
Country
Email
Website
Legal Entity
```

---

### Recent Activity

Examples:

```
Payment failed
Order completed
Menu updated
```

---

# 16. Pending Changes Tab

Used for vendor submitted legal changes.

Example fields:

```
Restaurant Name
Business Registration Number
VAT
Legal Address
```

Each field shows:

```
Current Value
New Value
```

Admin actions:

```
Approve Changes
Decline Changes
```

---

### Approval Logic

When approved:

```
vendor table updated
change recorded in audit log
```

---

# 17. Payments Tab

Shows payment history.

Grouped by:

```
Year
Month
```

Example entry:

```
Invoice ID
Amount
Status
Date
PDF
```

Statuses:

```
Paid
Unpaid
Failed
```

Admin can download:

```
Invoice PDF
Monthly report
Year report
```

---

# 18. Subscription Tab

Displays subscription details.

Fields:

```
Current Plan
Billing Cycle
Status
Next Billing Date
```

---

### Subscription History

Shows events:

```
Subscription Started
Plan Upgraded
Plan Downgraded
```

---

# 19. Orders Tab

Displays vendor order history.

Data:

```
Order ID
Customer
Amount
Status
Date
```

---

# 20. Reviews Tab

Displays customer reviews.

Fields:

```
Rating
Review Text
Customer
Date
Flag status
```

Admin actions:

```
Flag review
Remove review
Respond
```

---

# 21. Activity Timeline

Chronological log.

Examples:

```
Payment Failed
Menu Updated
Subscription Upgraded
Admin Suspended Vendor
```

Fields:

```
event_type
description
timestamp
actor
```

Actors:

```
Admin
Vendor
System
```

---

# 22. Permissions & Audit Logging

All admin actions logged.

Actions logged:

```
Vendor suspension
Subscription change
Legal info approval
Payment retry
Review moderation
```

Audit record structure:

```
audit_id
admin_id
vendor_id
action
metadata
timestamp
```

---

# 23. Backend API Endpoints

Example endpoints.

---

### Get Vendors

```
GET /admin/vendors
```

Supports filters.

---

### Get Vendor Details

```
GET /admin/vendors/{vendor_id}
```

---

### Suspend Vendor

```
POST /admin/vendors/{vendor_id}/suspend
```

Body:

```
reason
notes
admin_id
```

---

### Approve Legal Changes

```
POST /admin/vendors/{vendor_id}/approve-changes
```

---

### Get Payments

```
GET /admin/vendors/{vendor_id}/payments
```

---

### Export Vendors

```
GET /admin/vendors/export
```

---

# 24. Performance Requirements

Vendor list must support:

```
10,000+ vendors
```

Use:

```
pagination
indexing
caching
```

Recommended indexes:

```
vendor_id
status
payment_status
subscription_plan
country
city
revenue_total
```

---

# 25. Edge Cases

### Payment Failure Loops

Prevent infinite retries.

Retry policy:

```
3 retries in 24h
```

---

### Vendor Deleted

Soft delete only.

```
deleted_at
```

---

### Vendor Suspended With Active Orders

Orders allowed to complete.

New orders disabled.