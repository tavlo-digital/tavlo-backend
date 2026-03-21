# Customers Management

## 1. Purpose of Customer Management

The **Customer Management section** allows platform administrators to monitor, investigate, and manage customer accounts across the platform.

This section is primarily used for:

- Customer support
- Fraud detection
- Dispute resolution
- GDPR compliance
- Operational analytics

Administrators can:

- View all customers
- Investigate suspicious activity
- Review orders and disputes
- Export data when legally required
- Anonymize or delete accounts under GDPR rules

The system is designed with a **privacy-by-default approach**, meaning personal data is hidden unless explicitly accessed for legitimate reasons.

---

# 2. Page Layout Overview

The page contains the following sections:

```
Admin Sidebar
Top Search Bar
Privacy & GDPR Banner
Customer Overview Metrics
Search Field
Customer Table
Export Tools
Customer Detail Page (when opened)
```

---

# 3. Left Sidebar Navigation

Customer Management is accessed from the Admin sidebar.

Sidebar structure:

```
Overview
Vendors Management
Customers Management
Finance & Billing Overview
Subscriptions Management
Reviews & Complaints
Insights & Analysis
System Settings
Audit Log
```

When **Customers Management** is selected, the page loads the customer overview dashboard.

---

# 4. Global Search Bar

Top search field:

```
Search vendors, customers, orders...
```

This search performs a **global lookup** across multiple entities.

Possible results:

- Customer ID
- Order ID
- Vendor name
- Email (only if restricted data access is enabled)

---

# 5. Privacy & GDPR Compliance Banner

Displayed at the top of the page.

Purpose:

Explain that **personal data is hidden by default**.

Example message:

```
Customer personal data (email, phone) is hidden by default for GDPR compliance.
Enable only when necessary for customer support, fraud investigation, or legal requests.
```

Button:

```
Show Restricted Data
```

---

# 6. Restricted Data Access System

### Why it exists

European GDPR law requires that access to personal data is **limited and auditable**.

Therefore:

- Email and phone numbers are hidden by default
- Admins must justify access
- Only a specific admin with the permission to access this data can see the customer’s data (e.g., Support Admin can see, but not the Finance Admin)
- Access is logged in the audit trail
- Access automatically expires

---

## Access Process

When clicking **Show Restricted Data**, a confirmation dialog appears.

### Dialog: Confirm Restricted Data Access

Admin must select a reason:

```
Customer support request
Fraud investigation
Legal / GDPR request
```

System rules:

- Admin must choose one reason
- Access is logged with timestamp
- Admin ID is recorded

Once confirmed:

- Email and phone fields become visible
- A warning banner appears
- Access auto-expires after 10 minutes

---

## Restricted Data Active Banner

Once enabled, the page shows:

```
Restricted Data Access Active
Email and phone fields are visible
Access logged in Audit Trail
Auto-hide in [timer]
```

Buttons:

```
Hide Restricted Data
```

Timer automatically revokes access.

---

# 7. Customer Overview Metrics

Below the banner are summary cards showing key metrics.

Each card is clickable and acts as a filter.

### Total Customers

Shows total number of customer accounts.

Includes:

- registered users
- guest users

Clicking this resets filters.

---

### Total Orders

Total orders made by all customers.

Used as a general activity metric.

---

### Flagged Accounts

Number of accounts flagged by the system for risk.

Examples:

- multiple payment failures
- refund abuse
- suspicious behavior

Clicking this filters the customer list.

```
Filter = flagged accounts only
```

---

### GDPR Requests (30d)

Number of GDPR actions requested in the last 30 days.

Examples:

- data export requests
- account deletion requests

Clicking filters the list to customers with GDPR requests.

---

### High Activity (30d)

Customers with unusually high activity.

Possible triggers:

- many orders in short time
- high spending
- unusual usage patterns

Clicking filters the table.

---

# 8. Customer Search Field

Search box below the overview cards.

Input example:

```
Search: C-1024
```

Search behavior:

Primary lookup:

```
Customer ID
```

This is intentionally limited to avoid exposing personal data.

Hint displayed:

```
Search by Customer ID only (paste from order or support ticket)
```

---

# 9. Customer Table

Main list displaying all customers.

Columns:

```
Select checkbox
Risk
Customer ID
Account Type
Email
Phone
Orders
Total Spend
Last Active
Actions
```

---

# 10. Risk Indicators

The **Risk column** shows icons indicating potential issues.

Possible indicators:

### Red Warning

```
Multiple failed payments
Dispute filed
```

Tooltip example:

```
Multiple failed payment attempts, dispute filed
```

---

### Orange Warning

Example reason:

```
High refund activity
```

Tooltip example:

```
5 refund requests in last 30 days
```

---

### Grey Dot

Normal account.

---

# 11. Account Type

Shows whether the customer has a registered account.

Possible values:

```
Registered
Guest
```

Guest accounts are created when a customer orders without registering.

---

# 12. Email and Phone Columns

By default:

```
Hidden
Hidden
```

Displayed with lock icons.

When restricted data access is enabled:

- email becomes visible
- phone number becomes visible

Lock icon remains to indicate protected data.

---

# 13. Orders Column

Shows number of orders placed.

Example:

```
47 orders
```

Used to quickly identify high-value customers.

---

# 14. Total Spend

Displays total lifetime spending.

Example:

```
€1,284.50
```

---

# 15. Last Active

Shows when the customer last used the platform.

Examples:

```
2 hours ago
1 day ago
5 hours ago
```

---

# 16. Actions Column

Each customer row contains actions.

Icons:

```
View (eye icon)
More options
```

View opens the **Customer Support Overview page**.

---

# 17. Export All Filtered

Top right button.

```
Export All Filtered
```

Exports the currently filtered customer list.

Example:

If flagged accounts filter is active:

Export includes only flagged accounts.

Or only the selected customers

---

# 18. Export Customer Report Dialog

When exporting, the admin chooses export type.

Options:

### Aggregated Data Only (default)

Contains:

```
Customer ID
Orders
Total spend
Loyalty points
```

No personal data included.

---

### Include Personal Data (Restricted)

Includes:

```
Email
Phone
```

Requirements:

- Admin must provide reason
- Action logged in audit log

Text field:

```
Reason for Personal Data Export
```

---

# 19. Customer Support Overview Page

Opening a customer displays detailed information.

Header:

```
Customer Support Overview
Customer ID
Account type
```

Banner:

```
Support-Only View
Visible only to admins.
Customer cannot access this view.
```

---

# 20. Basic Customer Information

Shows:

```
Customer ID
Account created date
Registration source
Last login
Account type
Email (restricted)
Phone (restricted)
```

---

# 21. Activity Summary

Quick metrics about the customer.

Cards include:

```
Total Orders
Total Spend
Loyalty Points
```

---

# 22. Tabs in Customer Profile

The profile page contains several tabs.

```
Orders
Refunds / Disputes
Reviews / Complaints
Activity Log
GDPR Requests
```

---

# 23. Orders Tab

Displays recent orders.

Columns:

```
Order ID
Vendor
Date
Status
Items
Amount
Action
```

Action button:

```
View
```

Opening an order shows the receipt.

---

# 24. Order Receipt View

Shows full order receipt exactly as seen by customer.

Includes:

```
Vendor name
Address
Items ordered
Prices
VAT breakdown
Payment method
Transaction ID
QR code
```

Used for:

- customer support
- payment investigation

---

# 25. Refunds / Disputes Tab

Shows disputes raised by customer.

Example case:

```
Card declined but still charged
Payment deducted but order failed
```

Admin actions:

```
Approve refund
Decline
Contact vendor
```

---

# 26. Reviews / Complaints Tab

Displays reviews written by the customer.

Admins can:

```
Remove review
Unflag review
Contact vendor
```

Flagged reviews appear highlighted.

---

# 27. Activity Log

Shows timeline of customer activity.

Examples:

```
Login
Order placed
QR code scan
Order cancelled
Dispute filed
```

Each event includes:

```
timestamp
device type
IP address
```

---

# 28. GDPR Requests Tab

Shows customer GDPR requests.

Examples:

```
Data export
Account deletion
```

If none exist:

```
No GDPR requests
```

---

# 29. GDPR Actions Section

Located at bottom of customer page.

Highly restricted actions.

Three options:

```
Export Personal Data
Anonymize Customer
Delete Account Permanently
```

---

# 30. Export Personal Data

Used when a customer requests their data.

Complies with **GDPR Article 20 – Data Portability**.

Admin must provide reason.

Export format:

```
JSON
CSV
```

Includes:

- orders
- payments
- reviews
- account data

---

# 31. Anonymize Customer

Used for **Right to Erasure**.

Behavior:

```
Remove personal data
Keep order history
Replace identity with anonymous ID
```

Example:

```
email → deleted
phone → deleted
customer name → anonymous
```

Orders remain for tax and accounting purposes.

---

# 32. Delete Account Permanently

Highest level action.

Only available to:

```
SUPER ADMIN
```

Effect:

```
Delete all customer data
Delete order history
Delete account
```

Confirmation required:

```
Type DELETE FOREVER
```

---

# 33. Audit Logging

Every sensitive action must be logged.

Examples:

```
restricted data access
personal data export
anonymization
account deletion
refund approval
```

Log entries include:

```
admin ID
timestamp
action
reason
affected customer
```

---

# 34. Backend Data Model (Recommended)

Key database tables:

```
customers
customer_profiles
customer_activity
orders
payments
refunds
reviews
gdpr_requests
audit_logs
```

---

### Customers

```
customer_id
account_type
created_at
last_login
risk_score
```

---

### Customer Profiles

```
customer_id
email
phone
hashed identifiers
```

Sensitive fields encrypted.

---

### Activity Logs

```
activity_id
customer_id
event_type
timestamp
ip_address
device
```

---

### Audit Logs

```
audit_id
admin_id
action_type
target_id
reason
timestamp
```

---

# 35. Security Rules

The system must enforce:

### Default Privacy

Personal data hidden unless authorized.

---

### Access Expiration

Restricted data access automatically expires.

---

### Encryption

Sensitive fields encrypted in database.

---

### Role Permissions

Actions restricted by role:

```
Admin
Support
Super Admin
```

---

# 36. Frontend Behavior Rules

Frontend must enforce:

- disabled buttons without required input
- confirmation dialogs for destructive actions
- warning banners for GDPR actions
- clear visibility of restricted data state

---

# 37. Performance Considerations

Customer tables must support:

```
pagination
server-side filtering
indexed search by customer_id
```

**Avoid loading large datasets at once.**

---

# 38. Compliance Principles

This module is designed around:

```
GDPR
data minimization
auditability
privacy by default
```