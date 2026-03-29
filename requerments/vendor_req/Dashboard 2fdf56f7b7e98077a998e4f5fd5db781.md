# Dashboard

The Dashboard is a real-time operational control center. All displayed data is dynamic and reflects the current state of the system.

Every section appears conditionally and updates automatically based on backend state changes.

---

# 1. “Need Attention” Section

## Purpose

Highlights items that require operator action or awareness.

## Visibility Logic

- This section is **only visible if at least one issue exists**.
- If no issues exist:
    - Section is hidden **or**
    - Displays a neutral state message such as:
        
        “All clear” / “No pending issues”
        

## Severity Levels

Each attention item is color-coded by urgency:

- 🔴 Red – Critical
    
    Immediate action required. Affects operations or revenue.
    
- 🟡 Yellow – Warning
    
    Non-blocking issue but requires review.
    
- (Optional future state) 🟢 Informational
    
    Minor alerts or system notices.
    

## What Can Trigger an Attention Item

Examples of triggers (dynamic, not fixed):

- Payment failures
- Stuck or unpaid sessions
- Overdue tables
- Technical integration errors
- Menu sync issues
- Device offline
- Abandoned high-value carts
- Staff action required

## Behavior

- Items disappear automatically when resolved.
- Severity can escalate if unresolved for a defined time.
- Clicking an item redirects to the relevant detail page.

---

# 2. “Live Now” Section

## Purpose

Displays real-time active operational metrics.

## Core Characteristics

- All numbers are dynamic.
- Values change continuously based on active system state.
- These are not fixed KPIs; they reflect current activity only.

## Example Metrics (Dynamic)

- Active tables / sessions
- Total guests currently dining
- Orders in progress
- Revenue generated today
- Open carts
- Average order value (live)
- Conversion rate

## Update Logic

- Refreshes automatically.
- Data updates when:
    - A new session starts
    - An order is placed
    - A payment is completed
    - A session closes
    - A cart is abandoned

## Zero State

If there is no activity:

- Values display 0.
- System should not show errors.
- UI remains stable and neutral.

---

# 3. Session State Logic

Sessions have different statuses that affect dashboard visibility and behavior.

## Possible Session States

- Draft
- Active
- Payment Pending
- Completed
- Cancelled
- Expired

## Effects on Dashboard

- Payment pending may trigger warning.
- Expired sessions may auto-close.
- Completed sessions affect revenue metrics.

---

# 4. Revenue & Financial Indicators

## Revenue Display

- Reflects completed payments only.
- Updates instantly when payment confirmed.
- Can include filters:
    - Today
    - This week

## What Can Change

- Refunds reduce revenue.
- Cancellations remove pending revenue.
- Split payments update totals accordingly.

---

# 5. Operational Performance Metrics

These values change based on user behavior and restaurant activity.

Examples:

- Average time per table
- Time from order to payment
- Orders per hour
- Peak usage times

These metrics:

- Are calculated continuously.
- Depend on historical + real-time data.

---

# 6. Real-Time Behavior Rules

The dashboard must:

1. Never show stale static data.
2. Reflect backend changes immediately or near real-time.
3. Remove resolved issues automatically.
4. Avoid showing empty sections unless useful.
5. Clearly differentiate between:
    - Zero activity
    - System failure
    - No data yet

---

# 7. Color Logic & Visual Hierarchy

## Red

Used only when:

- Revenue loss risk
- Payment issues
- System malfunction
- Urgent operational risk

## Yellow

Used when:

- Action recommended
- Non-critical delays
- Warnings

## Neutral / Default

Used when:

- Everything operating normally
- Informational metrics only

Color must communicate priority, not decoration.

---

# 8. Most Ordered Today

## Purpose

Shows which items are performing best today. This is a performance indicator, not a static list.

## Calculation Logic

- Based only on **today’s completed orders**.
- Ranked by:
    - Quantity sold (default), or
    - Revenue generated (optional toggle).

## What Changes the Ranking

- New orders placed.
- Order cancellations.
- Refunds.
- Time filter change (today vs custom range).
- Menu changes (item disabled, renamed, deleted).

## Display Structure

Each item shows:

- Item name
- Quantity sold
- Revenue generated (optional)
- % change vs previous period (optional future feature)

## Zero State

If no orders today:

- Section displays: “No orders yet today”
- No placeholder fake data.

## Edge Cases

- If two items have same quantity, rank by revenue.
- If item is removed from menu but sold earlier, still appears in ranking.
- If partial refund occurs, adjust revenue but not quantity unless fully canceled.

## Behavior

- Updates live.
- Reflects only finalized transactions.
- Does not include draft carts or unpaid sessions.

---

# 9. Recent Orders

## Purpose

Displays latest activity in chronological order. This is a live operational feed.

## Sorting Logic

- Sorted by most recent action:
    - Order placed
    - Payment completed
    - Order updated

## What Appears

Each row can include:

- Order ID
- Table / Session ID
- Total amount
- Status (Draft, Active, Paid, Cancelled)
- Timestamp
- Payment method (optional)

## What Changes the Feed

- New order placed.
- Order modified.
- Payment completed.
- Order cancelled.
- Refund issued.

## Live Behavior

- New orders appear at top instantly.
- Status updates modify the existing entry.
- Cancelled or refunded orders remain visible but clearly marked.

## Status Color Logic

- Draft – Neutral
- Active – Default
- Paid – Green
- Cancelled – Grey or muted
- Payment Failed – Red

## Zero State

If no orders exist:

- Display “No recent orders”
- Do not show sample data.

---

# 10. System Philosophy

The dashboard is not static reporting.

It is a live operational control layer.

It answers:

- What is happening right now?
- Is anything wrong?
- Do I need to act?
- How is revenue performing?

If there is nothing requiring action, the UI should communicate operational stability clearly and quietly.