# Subscription Management

# Admin – Subscription Management

**Purpose:**

This page allows administrators to create, manage, and monitor all subscription plans used by vendors on the platform. It controls pricing, enabled features, limits, billing status, and plan hierarchy. The page is also used to monitor subscription revenue and vendor billing health.

The system follows a **feature-based SaaS subscription model** with **hierarchical plan inheritance**. Higher plans inherit features from lower plans and can add additional capabilities.

---

# 1. Page Structure Overview

The Subscription Management page is divided into **five main sections**:

1. **Top Metrics Dashboard**
2. **Plan Navigation Tabs**
3. **Subscription Plans Overview**
4. **Create/Edit Plan Modal**
5. **Vendor Subscription Lists (Active / Overdue)**

Each section has both **frontend behavior** and **backend logic**.

---

# 2. Top Metrics Dashboard

Displayed at the top of the page are four key subscription KPIs.

## 2.1 Total MRR

**Definition:**

Total Monthly Recurring Revenue generated from all active subscriptions.

**Displayed Information**

- Current MRR value
- Percentage change vs last month

Example:

```
Total MRR
€204,911
+15.2% vs last month
```

**Backend Logic**

MRR is calculated as:

```
MRR = Sum of all active subscription monthly prices
```

If a subscription is yearly:

```
Monthly Equivalent = Yearly Price / 12
```

**Required Database Data**

- subscription.plan_price
- subscription.billing_cycle
- subscription.status

**Filters**

Only include:

```
status = ACTIVE
```

---

## 2.2 Active Subscriptions

Displays:

```
Total number of active subscriptions
```

Example:

```
1,089 active subscriptions
Across 3 plans
```

**Backend Calculation**

```
COUNT(subscriptions WHERE status = ACTIVE)
```

Also grouped by:

```
plan_id
```

---

## 2.3 Churn Rate

Shows how many subscriptions were canceled during a period.

Example:

```
2.3% churn
-0.5% improvement
```

**Formula**

```
Churn Rate =
Canceled subscriptions during period
-------------------------------------
Total subscriptions at start of period
```

---

## 2.4 Overdue Revenue

Shows unpaid subscription invoices.

Example:

```
€2,847
4 subscriptions
```

**Backend Logic**

```
SUM(invoice.amount_due WHERE status = overdue)
COUNT(subscriptions WHERE overdue)
```

---

# 3. Plan Navigation Tabs

Three tabs exist below the metrics:

### 3.1 Plans

Displays all subscription plans available.

Example:

```
Plans (3)
```

### 3.2 Active

Shows all vendors currently subscribed.

### 3.3 Overdue

Shows vendors whose subscription payments failed.

---

# 4. Subscription Plans Overview

This section shows all plans as **cards**.

Example plans:

```
Basic
Standard
Premium
```

Each plan card contains:

### Plan Name

Example:

```
Basic
```

### Monthly Price

Example:

```
€99 / month
```

### Yearly Price

Example:

```
€950 / year
Save €238/year
```

### Active Subscribers

Example:

```
260 active subscriptions
```

### MRR Contribution

Example:

```
MRR: €25,740
```

Calculated as:

```
subscribers * monthly_price
```

---

## Feature Summary Section

Each plan shows:

```
Includes X features
```

Example:

```
Includes 9 features
```

Clicking this opens the **feature editor**.

---

## Plan Actions

Two buttons exist on each plan card.

### Edit Plan

Opens the edit modal.

### View Subscribers

Shows all vendors using the plan.

---

# 5. Create Plan Button

Located top-right.

Button:

```
+ Create Plan
```

Opens the **Create Plan modal**.

---

# 6. Create Plan Modal

This modal allows admins to configure a new subscription plan.

Sections:

1. Plan Name
2. Pricing
3. Limits
4. Plan Inheritance
5. Features
6. Plan Label

---

# 6.1 Plan Name

Field:

```
Plan Name
```

Example:

```
Premium
```

Stored in:

```
subscription_plans.name
```

---

# 6.2 Pricing

Two fields exist.

### Monthly Price

Example:

```
Monthly Price (€)
199
```

### Yearly Price

Example:

```
Yearly Price (€)
1910
```

Displayed note:

```
Yearly billing typically offers a discount (~20%)
```

---

## Backend Pricing Logic

Plan pricing fields:

```
monthly_price
yearly_price
currency
```

Billing cycles supported:

```
monthly
yearly
```

---

# 6.3 Limits

Controls usage limits for vendors.

Example:

```
Maximum users
```

Description:

```
Maximum number of staff accounts allowed for this plan
```

Stored as:

```
plan.max_users
```

---

# 6.4 Plan Inheritance (Hierarchical Plans)

Admins can create plans that **inherit features from another plan**.

Dropdown:

```
Inherit From
```

Options:

```
None (start from scratch)
Basic
Standard
```

---

## Example Hierarchy

```
Basic
  ↓
Standard
  ↓
Premium
```

Premium inherits all Standard features.

Standard inherits Basic features.

---

## Backend Behavior

When a plan inherits another plan:

```
child_plan.features = parent_plan.features + additional_features
```

Inherited features are **locked** and cannot be removed.

Example UI message:

```
This plan inherits all features from Basic.
Inherited features cannot be removed.
```

---

# 7. Feature Management

Plans are **feature-based**.

Features are grouped into categories.

Categories include:

1. Menu & Content
2. Ordering & Payments
3. Analytics
4. Customer Engagement
5. Support & Admin
6. Integrations

---

# 7.1 Menu & Content Features

Features include:

Basic Menu Management

Create and manage menu items.

Menu Categories

Organize items into categories.

Menu Item Images

Upload photos.

Unlimited Menu Items

No item limit.

Item Modifiers & Options

Extras, sizes, customizations.

---

## Dependency Logic

Some features require others.

Example:

```
Menu Categories
Requires: Basic Menu Management
```

If the required feature is disabled:

```
dependent feature cannot be selected
```

---

# 7.2 Ordering & Payments

Features include:

QR Code Ordering

Customers order via QR.

Table Management

Assign orders to tables.

Card Payments

Stripe payment processing.

Cash Payment Option

Allow cash orders.

Split Bill

Divide payments.

Tipping

Customer tips.

---

## Dependency Example

```
Split Bill
Requires: Card Payments
```

---

# 7.3 Analytics

Features include:

Basic Analytics

Sales overview.

Advanced Analytics

Detailed reports.

Export Reports

CSV / Excel download.

Real-Time Dashboard

Live monitoring.

---

## Dependency

```
Advanced Analytics
Requires Basic Analytics
```

---

# 7.4 Customer Engagement

Features:

Multi-language support

Example languages:

```
German
English
Arabic
```

Loyalty Program

Points and rewards.

Customer Reviews

Ratings system.

Email Marketing

Send promotions.

---

# 7.5 Support & Admin

Features:

Email Support

48 hour response.

Priority Support

24 hour response.

Dedicated Account Manager

Multi-user Access

Multiple vendor staff accounts.

---

# 7.6 Integrations

Features:

API Access

External integrations.

Webhooks

Event notifications.

White Label

Custom domain and branding.

POS Integration

Connect POS systems.

---

# 8. Most Popular Label

Admin can mark a plan as:

```
Most Popular
```

This highlights the plan in the UI.

Used mainly for marketing.

Stored as:

```
plan.is_popular
```

---

# 9. Edit Plan Modal

Similar to the Create Plan modal but pre-filled.

Shows message if inheritance exists:

```
This plan inherits all features from Basic.
Inherited features are locked.
```

Locked features cannot be modified.

---

# 10. Active Subscriptions Tab

Shows vendors currently subscribed.

Table columns:

```
Vendor
Plan
Status
Start Date
Next Billing
MRR
Auto Renew
Actions
```

---

## Vendor

Example:

```
Bella Italia
V-1024
```

Vendor ID is displayed under the name.

---

## Plan

Example:

```
Premium
```

---

## Status

Example:

```
Active
```

Possible values:

```
Active
Canceled
Paused
Trial
```

---

## Start Date

Date subscription began.

Example:

```
15.1.2024
```

---

## Next Billing

Example:

```
15.7.2025
```

---

## MRR

Example:

```
€299
```

---

## Auto Renew

Values:

```
Yes
No
```

---

## Actions

Link:

```
View in Vendor Detail
```

Navigates to vendor profile page.

---

# 11. Overdue Subscriptions Tab

Shows vendors with unpaid invoices.

Columns:

```
Vendor
Plan
Last Billing
Days Overdue
Amount Due
Actions
```

---

## Days Overdue

Example:

```
31 days
```

---

## Amount Due

Example:

```
€118.80
```

---

# 12. Billing Failure Flow

If a payment fails:

1. Subscription marked **overdue**
2. Vendor notified
3. Retry payment attempts
4. After threshold reached:

```
subscription suspended
```

---

# 13. Required Backend Entities

Key database tables:

```
subscription_plans
plan_features
features
vendors
subscriptions
invoices
payments
```

---

# Example Plan Table

```
plans
id
name
monthly_price
yearly_price
max_users
parent_plan_id
is_popular
created_at
```

---

# Plan Features Table

```
plan_features
id
plan_id
feature_id
is_inherited
```

---

# Features Table

```
features
id
name
category
description
required_feature_id
```

---

# Subscriptions Table

```
subscriptions
id
vendor_id
plan_id
status
start_date
next_billing_date
billing_cycle
auto_renew
```

---

# Invoices Table

```
invoices
id
subscription_id
amount
status
due_date
paid_at
```

---

# 14. Security & Permissions

Only **Admin users** can:

- create plans
- edit plans
- assign features
- modify pricing
- view billing data

---

# 15. Performance Considerations

Large platforms may have:

```
10k+ vendors
```

Required optimizations:

- indexed queries
- cached metrics
- aggregated billing tables

---

# 16. Edge Cases

Developers must handle:

### Vendor Downgrade

Remove features safely.

### Feature Dependencies

Prevent invalid plan configuration.

### Payment Failure

Suspend vendor features.

### Plan Deletion

Cannot delete if active subscriptions exist.

---

# Complete Backend Architecture – Subscription System

This defines the **full backend architecture for the Tavlo subscription system**, including:

- subscription plans
- features
- feature inheritance
- vendor subscriptions
- billing
- invoices
- payments
- upgrades & downgrades
- feature entitlement logic
- Stripe integration
- usage limits

This design supports **scalable SaaS infrastructure** and avoids future rewrites.

---

# 1. System Architecture Overview

The subscription system has **five core components**.

1. **Plan Management**
2. **Feature Access Control**
3. **Vendor Subscriptions**
4. **Billing & Invoices**
5. **Payment Processing**

Simplified architecture:

```
Admin
  │
Subscription Plans
  │
Plan Features
  │
Vendor Subscriptions
  │
Invoices
  │
Payments (Stripe)
```

---

# 2. Core Database Entities

Main database tables:

```
plans
features
plan_features
vendors
subscriptions
subscription_events
invoices
payments
feature_usage
```

---

# 3. Plans Table

Stores all subscription plans.

Example:

```
Basic
Standard
Premium
```

### Table: plans

```
plans
-----------------------------
id (uuid)
name
description
monthly_price
yearly_price
currency
max_users
parent_plan_id
is_popular
is_active
created_at
updated_at
```

### parent_plan_id

Used for **hierarchical plans**.

Example:

```
Basic
  id = 1

Standard
  parent_plan_id = 1

Premium
  parent_plan_id = 2
```

This allows **feature inheritance**.

---

# 4. Features Table

Defines every feature available on the platform.

### Table: features

```
features
----------------------------
id
name
description
category
required_feature_id
created_at
```

Example records:

```
Basic Menu Management
Menu Categories
Menu Item Images
QR Code Ordering
Table Management
Card Payments
Split Bill
Basic Analytics
Advanced Analytics
API Access
POS Integration
```

---

# 5. Plan Features Table

Defines which features belong to which plan.

### Table: plan_features

```
plan_features
--------------------------------
id
plan_id
feature_id
is_inherited
created_at
```

Example:

```
Basic Plan
Basic Menu Management
Menu Categories
Menu Item Images
QR Ordering
Card Payments
```

```
Standard Plan
inherits Basic
+ Unlimited Menu Items
+ Analytics
```

---

# 6. Feature Dependency Logic

Some features depend on others.

Example:

```
Menu Categories
requires Basic Menu Management
```

Stored in:

```
required_feature_id
```

Logic:

```
if feature.requires
   ensure required feature exists
```

---

# 7. Vendors Table

Stores restaurants/businesses using the platform.

### Table: vendors

```
vendors
-----------------------------
id
vendor_code
name
email
phone
status
created_at
```

Example:

```
Bella Italia
Burger Palace
Taco House
```

---

# 8. Subscriptions Table

Defines which vendor uses which plan.

### Table: subscriptions

```
subscriptions
---------------------------------
id
vendor_id
plan_id
status
billing_cycle
start_date
next_billing_date
auto_renew
created_at
updated_at
```

### Status options

```
trial
active
paused
canceled
overdue
suspended
```

---

# 9. Subscription Events Table

Tracks subscription changes.

### Table: subscription_events

```
subscription_events
-----------------------------------
id
subscription_id
event_type
previous_plan_id
new_plan_id
created_at
metadata
```

Example events:

```
subscription_created
plan_upgrade
plan_downgrade
payment_failed
subscription_canceled
```

This enables **audit logging and analytics**.

---

# 10. Invoices Table

Invoices generated every billing cycle.

### Table: invoices

```
invoices
--------------------------------
id
subscription_id
invoice_number
amount
currency
status
billing_period_start
billing_period_end
due_date
paid_at
created_at
```

### Invoice Status

```
pending
paid
failed
overdue
void
```

---

# 11. Payments Table

Stores payment attempts.

### Table: payments

```
payments
--------------------------------
id
invoice_id
payment_provider
provider_transaction_id
amount
currency
status
payment_method
created_at
```

### Status

```
success
failed
pending
refunded
```

---

# 12. Feature Usage Table

Tracks usage limits.

Example:

```
number of staff users
API calls
menu items
```

### Table: feature_usage

```
feature_usage
--------------------------------
id
vendor_id
feature_id
usage_value
period_start
period_end
created_at
```

---

# 13. Feature Entitlement Logic

When a vendor logs in, the backend must determine:

```
What features does this vendor have access to?
```

Process:

```
1. Get vendor subscription
2. Get plan
3. Get plan_features
4. Include inherited features
```

Example result:

```
Vendor: Bella Italia

Features:

Basic Menu Management
Menu Categories
Menu Item Images
QR Code Ordering
Card Payments
Split Bill
Analytics
```

---

# 14. Plan Inheritance Algorithm

When loading plan features:

```
function getPlanFeatures(plan_id):

   features = plan_features(plan_id)

   if parent_plan exists
       features += getPlanFeatures(parent_plan_id)

   return unique(features)
```

This allows **unlimited hierarchy depth**.

---

# 15. Subscription Creation Flow

When vendor subscribes.

```
Vendor selects plan
↓
Subscription record created
↓
Invoice generated
↓
Payment attempted
↓
Subscription activated
```

---

# 16. Billing Cycle Flow

Monthly example.

```
1 month before next billing

System runs billing job
↓
Generate invoice
↓
Attempt payment
↓
Success → next billing date updated
↓
Failure → mark overdue
```

---

# 17. Payment Retry Logic

Example retry schedule:

```
Day 0 — payment attempt
Day 3 — retry
Day 7 — retry
Day 14 — retry
```

After final failure:

```
subscription status = suspended
```

---

# 18. Subscription Upgrade Logic

Example:

```
Basic → Standard
```

Process:

```
1. Calculate price difference
2. Create prorated invoice
3. Charge vendor
4. Update plan
```

---

# 19. Subscription Downgrade Logic

Example:

```
Premium → Standard
```

Process:

```
Change applied next billing cycle
```

Avoids refund complexity.

---

# 20. Stripe Integration Architecture

Stripe is recommended for payments.

Stripe objects:

```
Customer
Subscription
PaymentIntent
Invoice
```

Mapping:

```
vendors → stripe_customer_id
subscriptions → stripe_subscription_id
payments → stripe_payment_id
```

---

# 21. Stripe Webhooks

Required webhooks:

```
invoice.payment_succeeded
invoice.payment_failed
customer.subscription.updated
customer.subscription.deleted
```

Webhook updates local database.

---

# 22. Admin Subscription Management APIs

Backend endpoints.

### Plans

```
GET /admin/plans
POST /admin/plans
PUT /admin/plans/{id}
DELETE /admin/plans/{id}
```

---

### Features

```
GET /admin/features
POST /admin/features
```

---

### Subscriptions

```
GET /admin/subscriptions
GET /admin/subscriptions/active
GET /admin/subscriptions/overdue
```

---

### Billing

```
GET /admin/invoices
GET /admin/payments
```

---

# 23. Performance Considerations

When platform grows.

Example scale:

```
50k vendors
100k subscriptions
1M invoices
```

Important optimizations:

### Indexing

```
subscriptions.vendor_id
subscriptions.plan_id
invoices.subscription_id
payments.invoice_id
```

---

### Aggregated Metrics

For fast dashboard:

```
subscription_metrics
```

Pre-calculated values:

```
MRR
Active subscriptions
Churn
Overdue revenue
```

Updated by background job.

---

# 24. Background Jobs

Recommended workers.

### Billing Worker

Runs daily.

```
generate invoices
retry payments
update billing cycles
```

---

### Metrics Worker

Calculates:

```
MRR
churn
plan revenue
```

---

### Cleanup Worker

Handles:

```
cancel expired trials
remove unpaid subscriptions
```

---

# 25. Security & Permissions

Only **Admin roles** can:

```
create plans
edit features
change pricing
view billing
```

Vendors can only:

```
view their subscription
upgrade
cancel
update payment
```

---

# 26. Edge Cases

Developers must handle:

### Vendor Cancels

```
status = canceled
access until billing end
```

---

### Vendor Downgrade

Remove features safely.

---

### Failed Payments

```
status = overdue
```

Then eventually:

```
status = suspended
```

---

### Plan Deletion

Blocked if:

```
active subscriptions exist
```

---

# 27. Future Scalability

System supports:

```
Add-ons
Usage-based pricing
Marketplace revenue share
Enterprise plans
Custom contracts
```

---