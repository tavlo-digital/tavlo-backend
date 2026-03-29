# Orders Management

# 1. Purpose of the Orders Management System

The Orders Management system is the **operational control center for restaurant orders**.

It allows vendors to:

- Receive and monitor incoming orders
- Manage order preparation
- Coordinate kitchen workflow
- Track table sessions
- Handle split payments
- Control course timing
- Batch orders intelligently
- Serve or cancel orders

The system is designed to solve common problems in QR ordering environments:

- Order flooding
- Fragmented table orders
- Incorrect course timing
- Kitchen coordination issues

**Note**: Orders do not go directly to the kitchen if the user is not logged in and the order is not paid for upfront (online). Otherwise (if pay later or cash), the waiter needs to confirm the order. This is to prevent a ghost ordering.

---

# 2. Page Layout Overview

The Orders Management page consists of the following sections:

1. Page Header
2. Status Tabs
3. Order Filters
4. Table Session Containers
5. Kitchen Summary Panel
6. Order Cards
7. Table Session Control Actions
8. Order Action Buttons

Each component is explained in detail below.

---

# 3. Page Header

Located at the top of the page.

Elements include:

**Title**

Orders Management

**Subtitle**

Manage and track all restaurant orders

---

## Refresh Orders Button

Button:

Refresh Orders

Purpose:

Manually reload the latest orders from the server.

Backend action:

GET /vendor/orders

Used when:

- Staff wants to immediately see new orders
- Network delays occur
- Manual update is required

---

# 4. Order Status Tabs

Located under the page header.

Tabs allow filtering orders by operational status.

Tabs include:

All Orders

Received

Ready

Served

Example:

All Orders (56)

Received (43)

Ready (3)

Served (5)

Each tab displays the number of orders in that status.

Selecting a tab filters the displayed orders accordingly.

---

# 5. Order Filters

Additional filters allow vendors to refine visible orders.

## Filter by Order Type

Options:

All

Dine-in

Takeaway

Example:

All (56)

Dine-in (52)

Takeaway (4)

---

## Filter by Payment Status

Options:

All

Paid

Pending Cash

Unpaid

Example:

All (56)

Paid (35)

Pending Cash (9)

Unpaid (12)

These filters allow staff to quickly identify unpaid or cash orders.

---

# 6. Table Session Grouping

Orders are grouped by **Table Session**.

A Table Session represents a group of orders placed by guests sitting at the same table during the same visit.

This solves the issue where multiple guests at the same table place separate orders and pay individually.

Example:

TABLE 8

3 Orders • €112.10 Total • Session Active

Within this session there may be multiple independent orders.

Example:

Order #9001

Order #9002

Order #9003

Each order may belong to a different guest and payment method.

---

# 7. Table Session Header Information

Each table session displays key information.

Elements include:

Table Number

Example: TABLE 8

Number of Orders

Example: 3 Orders

Total Session Value

Example: €112.10 Total

Session Status

Example: Session Active

Session Duration

Example: Session Active • 18 min

Session duration shows how long the table has been active since the first order was placed.

---

# 8. Batch Order Consolidation

To prevent the kitchen from receiving fragmented orders, the system uses **Order Batching**.

When the first order arrives from a table, a short batching window begins.

Example:

Batch in Progress • Releasing in 62s

During this window additional orders from the same table are grouped together.

Once the timer expires, the orders are released to the kitchen as one batch.

---

## Batch Controls

Inside the table session header, staff can control batching.

Available actions:

Release to Kitchen Now

Immediately sends the batch to the kitchen.

Fire Next Course

Triggers the next course in the course sequencing system.

Close Table Session

Closes the current dining session for the table.

---

# 9. Course Management System

The platform includes a simplified course control system to ensure dishes are prepared in the correct order.

Courses follow a fixed sequence:

Drinks

Appetizers

Mains

Desserts

The course indicator shows which stage the table is currently in.

Example:

Courses

Drinks ✓

Appetizers ✓

Mains ⏳

Desserts ○

Meaning:

Drinks completed

Appetizers completed

Mains currently active

Desserts waiting

---

## Fire Next Course

**Button:**

Fire Next Course

**Purpose:**

Release the next course of items to the kitchen.

**Example workflow:**

Drinks and appetizers are prepared first.

**Once served, staff presses:**

Fire Next Course

This releases mains to the kitchen.

---

# 10. Kitchen Summary Panel

Each table session contains a **Kitchen Summary** section.

Example:

TABLE 8 — KITCHEN SUMMARY

2x Beer

2x Bruschetta

2x Caesar Salad

1x Wine

1x Garlic Bread

2x Coca Cola

Purpose:

Aggregate items across all orders belonging to the table session.

This allows the kitchen to see the total preparation workload without needing to manually combine items from multiple orders.

Important:

Kitchen Summary is **only a preparation overview**.

It does not affect billing or individual orders.

---

# 11. Individual Orders (Billing Detail)

Below the Kitchen Summary the system displays the individual orders.

These are the **actual billing records**.

Example:

Order #9001

Table 8 • 6 guests • Timestamp

Items:

2x Beer

1x Bruschetta

1x Caesar Salad

Each order contains:

Order ID

Table number

Guest count

Order timestamp

Item list

Item quantities

Course labels (Drinks, Appetizers, Mains, Desserts)

---

# 12. Order Financial Details

Each order displays:

Order total

Example: €45.50

Breakdown:

Items subtotal

Service fee

VAT

Example:

Items: €38.20

Service: €3.82

VAT: €3.48

---

# 13. Payment Status Indicators

Payment status appears beside the order total.

Possible statuses include:

Paid

Pending Cash

Unpaid

Example:

Paid (green)

Pending Cash (yellow)

Unpaid (red)

These indicators help waiters track which orders require payment.

---

# 14. Order Action Buttons

Each order contains operational buttons.

## Mark as Ready

Button:

Mark as Ready

Purpose:

Indicates that the kitchen has finished preparing the order.

Order status changes:

Received → Ready

---

## Mark Ready for Pickup

Used for takeaway orders.

Button:

Mark Ready for Pickup

Indicates the order is ready for the customer to collect.

---

## Mark as Served

Used for dine-in orders.

Order status changes:

Ready → Served

---

## Confirm Cash Payment

Used when the customer pays cash.

Button:

Confirm Cash Payment

Payment status changes:

Pending Cash → Paid

---

## Cancel Order

Cancels the order.

Possible reasons:

Customer cancellation

Kitchen issue

Unavailable item

Order status becomes:

Cancelled

---

# 15. Order Status Lifecycle

Orders progress through the following states:

Received

Ready

Served

Alternative states:

Cancelled

Pending Cash

---

# 16. Session Closure

A table session can end when:

All orders are paid for

or

A staff member presses:

Close Table Session

Once closed, new orders from the table will create a **new session**.

---

# 17. Role Permissions

Vendor Manager

Full access:

- Manage orders
- Release batches
- Fire courses
- Cancel orders
- Close sessions
- Confirm payments

---

Vendor Kitchen

Kitchen staff can:

- View orders
- View kitchen summary
- Mark orders ready

Kitchen staff cannot:

- Confirm payments
- Close sessions

---

Vendor Waiter

Waiters can:

- Mark orders served
- Confirm cash payments
- Fire next course
- Close sessions