# Billing & Subscription

**Purpose:**

This page allows the restaurant/vendor to manage their Tavlo subscription, billing details, invoices, and payment methods. It also gives visibility into current usage and available upgrade options. The page must be simple enough for non-technical restaurant managers while still providing clear system logic for backend services.

This section must be accessible to:

- **Vendor Manager (default access)**
- **Other team members** only if permission is granted in
    
    **Settings → Team & Access**
    

Permissions should be controlled via **Role-Based Access Control (RBAC)**.

Example permissions:

| Permission | Description |
| --- | --- |
| View Billing | Can see subscription and invoices |
| Manage Billing | Can update payment method and billing email |
| Upgrade Plan | Can change plan |
| Cancel Subscription | Can cancel subscription |

If the user lacks permissions, the page must show **read-only information** or hide sensitive actions.

---

# 1. Page Structure Overview

The page is divided into the following functional blocks:

1. **Subscription Overview**
2. **Usage This Month**
3. **Upgrade Your Plan**
4. **Payment Method**
5. **Invoices & Documents**
6. **Cancel Subscription**
7. **Secure Payments Notice**

Each section communicates with the **Billing Service**, which is typically connected to a payment processor such as **Stripe**.

---

# 2. Subscription Overview

## Purpose

Displays the current subscription plan, billing cycle, next billing date, and subscription status.

## Displayed Information

| Field | Description |
| --- | --- |
| Current Plan | Name of active plan (e.g., Monthly) |
| Billing Cycle | Price per billing period |
| Subscription Status | Active / Paused / Past Due / Cancelled |
| Next Billing Date | Date the next payment will be charged |
| Subscription ID | Internal billing identifier |

Example:

```
Current Plan: Monthly
Billing Cycle: €49 / month
Next Billing Date: Jan 22, 2026
Subscription ID: sub_176550652666
Status: Active
```

---

## Status Badge

### Active

Subscription is paid and operational.

System behavior:

- QR ordering enabled
- Orders processed normally
- No restrictions

### Past Due

Payment failed or invoice unpaid.

System behavior:

- Orders may still function for grace period
- Warning banner may appear
- Admin alerts triggered

### Paused

Subscription temporarily paused by system or admin.

System behavior:

- QR ordering disabled
- Orders blocked
- Restaurant is not visible in tavlo discovery

### Cancelled

Subscription terminated.

System behavior:

- Vendor account remains
- Ordering disabled
- Data preserved for legal retention

---

## Buttons

### Upgrade Plan

Allows the vendor to change the subscription tier.

**Frontend behavior**

- Opens upgrade modal
- Shows available plans
- Displays price comparison

**Backend process**

```
POST /billing/upgrade-plan
```

Steps:

1. Validate user permission
2. Retrieve plan list
3. Initiate payment confirmation
4. Update subscription

---

### Change Billing Cycle

Switch between:

- Monthly
- Yearly

If yearly is selected:

- Apply discount (example: Save 17%)

Backend endpoint:

```
PATCH /billing/cycle
```

---

### Update Payment Method

Opens payment method update form.

Process:

1. User enters card details
2. Payment gateway tokenizes card
3. Tavlo stores only payment token

Security:

- Card numbers never stored in Tavlo database
- PCI compliance handled by Stripe

---

## Informational Notes

Two information messages appear:

### Payment Failure Notice

"If your payment fails, orders and QR codes will be temporarily paused until payment is resolved."

Purpose:

Warn the vendor that unpaid invoices affect ordering.

---

### Subscription Visibility Notice

"Subscription ID is visible to Tavlo admin for support and compliance purposes."

Used for support troubleshooting.

---

# 3. Usage This Month

This panel gives vendors visibility into platform usage.

Displayed metrics:

| Metric | Meaning |
| --- | --- |
| Active Tables | Number of tables currently enabled |
| Orders | Total orders processed this month |
| Staff Accounts | Number of employee accounts |
| QR Codes | Number of QR codes generated |

Example display:

```
Active Tables: Unlimited
Orders: Unlimited
Staff Accounts: Unlimited
QR Codes: 15
```

---

## Feature Limits

If Tavlo introduces tier limits, this section must show:

Example:

```
Orders: 540 / 1000
Staff Accounts: 8 / 10
```

Visual indicator:

- Green: under limit
- Orange: near limit
- Red: limit reached

---

## Backend Calculation

Metrics must be aggregated from:

| Metric | Source |
| --- | --- |
| Tables | Tables database |
| Orders | Orders database |
| Staff | Team members table |
| QR Codes | QR code table |

Refresh frequency:

- Real-time or cached every **5 minutes**

---

# 4. Upgrade Your Plan

Purpose:

Encourage vendors to upgrade to higher tiers.

Plans displayed:

| Plan | Price | Billing |
| --- | --- | --- |
| Monthly | €49 | Monthly |
| Yearly | €490 | Yearly |

Yearly shows discount:

```
Save 17%
```

---

## Plan Card Components

Each plan contains:

- Plan name
- Price
- Billing description
- Current plan indicator
- Upgrade button

---

## Current Plan Indicator

Example badge:

```
Current
```

System behavior:

- Upgrade button disabled
- Grey highlight

---

## Upgrade to Yearly Button

Triggers upgrade flow.

Backend process:

```
POST /billing/upgrade
```

Steps:

1. Validate subscription
2. Calculate prorated price
3. Redirect to payment gateway
4. Update subscription record

---

# 5. Payment Method

Displays the vendor’s saved payment method.

Example display:

```
Visa ending in 4242
Expires 12/2025
Billing email: vendor@example.com
Last updated: Dec 1, 2024
```

---

## Card Display

Only partial card numbers are shown.

Example:

```
Visa •••• 4242
```

Security requirement:

Never expose full card number.

---

## Default Payment Method Badge

Green label:

```
Default
```

Meaning:

Primary card used for billing.

---

## Update Payment Method Button

Opens payment modal.

Steps:

1. Collect card information
2. Send to payment processor
3. Update payment token

Backend endpoint:

```
POST /billing/payment-method
```

---

# 6. Invoices & Documents

This section shows billing history.

Table structure:

| Column | Description |
| --- | --- |
| Invoice ID | Unique invoice identifier |
| Date | Invoice issue date |
| Amount | Total charged |
| VAT | VAT amount |
| Status | Payment state |
| Action | Download invoice |

---

## Invoice Status

### Paid

Invoice successfully paid.

### Pending

Payment initiated but not yet confirmed.

### Failed

Payment attempt unsuccessful.

### Refunded

Payment refunded.

---

## Download Button

Icon: download symbol.

Action:

Download invoice PDF.

Backend:

```
GET /billing/invoice/{invoice_id}
```

File format:

PDF

---

## View All in Portal

Redirects to full billing portal.

Usually a Stripe-hosted billing page.

Purpose:

Allows:

- Payment updates
- Invoice downloads
- Billing history

---

# 7. Cancel Subscription

This section allows vendors to terminate their Tavlo subscription.

Warning message:

"Canceling your subscription will deactivate your restaurant and pause all Tavlo services."

---

## Cancel Subscription Button

Red button to highlight risk.

When clicked:

Open confirmation modal.

---

## Cancellation Confirmation Modal

User must confirm.

Example:

```
Are you sure you want to cancel your subscription?
Your QR ordering will stop working immediately.
```

Buttons:

- Confirm Cancel
- Keep Subscription

---

## Backend Process

```
POST /billing/cancel
```

Steps:

1. Validate permission
2. Mark subscription status = CANCELLED
3. Stop recurring billing
4. Disable ordering system
5. Notify Tavlo admin

---

# 8. Secure Payments Notice

Displayed at bottom right.

Text:

```
Secure Payments
All payments are processed securely through Stripe.
We never store your card details.
```

Purpose:

Reassure vendors about payment security.

---

# 9. System Events & Notifications

Events triggered by billing actions:

| Event | Trigger |
| --- | --- |
| Payment Success | Invoice paid |
| Payment Failed | Card declined |
| Subscription Upgraded | Plan change |
| Subscription Cancelled | User cancellation |

Notifications may be sent via:

- Email
- Admin alerts
- Dashboard banners

---

# 10. Backend Architecture

Core services required:

| Service | Responsibility |
| --- | --- |
| Billing Service | Subscription management |
| Payment Gateway | Payment processing |
| Invoice Service | Invoice generation |
| Usage Service | Usage tracking |

---

## Database Entities

Main tables:

### Subscriptions

```
subscription_id
vendor_id
plan
billing_cycle
status
next_billing_date
created_at
updated_at
```

---

### Payment Methods

```
payment_method_id
vendor_id
card_brand
last4
expiry
token
is_default
```

---

### Invoices

```
invoice_id
vendor_id
amount
vat
status
issued_date
pdf_url
```

---

### Usage Metrics

```
vendor_id
tables
orders
staff_accounts
qr_codes
last_updated
```

---

# 11. Security Requirements

Critical rules:

- No credit card storage in Tavlo database
- Payment tokens handled by Stripe
- Access protected via role permissions
- Billing endpoints require authentication

---

# 12. Error Handling

Examples:

### Payment Failed

Display message:

```
Your payment could not be processed.
Please update your payment method.
```

---

### Billing System Unavailable

Display:

```
Billing services are temporarily unavailable.
Please try again later.
```

---

# 13. Edge Cases

Developers must handle:

| Case | Behavior |
| --- | --- |
| Card expired | Prompt update |
| Failed renewal | Retry payment |
| Subscription cancelled | Disable ordering |
| Duplicate payments | Automatic refund |

---

# 14. UX Principles

The billing page must:

- Be transparent about costs
- Prevent accidental cancellations
- Show clear invoice history
- Provide simple upgrade flow

Restaurant owners must be able to understand everything **without technical knowledge**.