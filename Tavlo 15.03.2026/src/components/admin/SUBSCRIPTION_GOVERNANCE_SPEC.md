# Subscription Governance - Platform-Wide Enforcement Rules Specification

## Overview

The **Subscription Governance** page defines global, platform-wide subscription enforcement rules only.

**Core Philosophy:**
- Vendors are charged automatically (monthly or yearly)
- No manual invoices
- No payment reminders
- No admin intervention in billing
- One source of truth per concept
- This page defines rules, not products

---

## 1. PAGE IDENTITY & COPY

### Page Title
```
Subscription Governance
```

### Subtitle
```
Platform-wide rules for subscription enforcement and lifecycle
```

**Purpose:**
- Clear identity: This is about rules, not products
- Sets governance tone immediately
- Not "Subscriptions & Billing" (too broad)

---

## 2. PLATFORM GOVERNANCE BANNER (STRENGTHENED)

### Enhanced Banner

```
┌─────────────────────────────────────────────────────────┐
│ 🛡️ Global Enforcement Rules Only                       │
│                                                         │
│ These settings define global subscription enforcement  │
│ rules. Subscription plans, features, and pricing are   │
│ managed in Subscription Management. Vendors are        │
│ charged automatically based on their selected billing  │
│ cycle.                                                 │
│                                                         │
│ 🔒 Super Admin Only                                    │
└─────────────────────────────────────────────────────────┘
```

**Visual:**
- Purple background (`bg-purple-50`)
- 2px purple border (`border-2 border-purple-300`)
- Shield icon
- Lock icon with "Super Admin Only"
- **Bold text** for critical statements
- Link to "Subscription Management" (underlined)

**Behavior:**
- Non-dismissable
- Always visible at top of page
- Cannot be hidden or collapsed

---

## 3. REMOVED SECTIONS (DO NOT RENDER)

### ❌ Completely Removed:

1. **Subscription Plans List**
   - No Basic / Standard / Premium plans
   - No plan cards
   - No "Edit" buttons

2. **Plan Pricing**
   - No monthly prices
   - No annual prices
   - No pricing grids

3. **Feature Lists**
   - No "QR Code Ordering"
   - No "Basic Analytics"
   - No feature badges

4. **Order Limits per Plan**
   - No "Max Orders/Month"
   - No limits display

5. **Invoice Frequency**
   - No "monthly / quarterly / annual" dropdown
   - No invoice configuration

6. **Auto-Send Invoices**
   - No "Auto-send invoices on billing date" checkbox

7. **Payment Reminders**
   - No "Send payment reminders for overdue invoices" checkbox

**Rationale:**
- These concepts exist in **Admin → Subscriptions** (Subscription Management)
- One source of truth per concept
- No duplication allowed

---

## 4. TRIAL PERIOD RULES (KEPT)

### Trial Period Configuration

**Visual:**
```
┌─────────────────────────────────────────────────────────┐
│  ⚡ Trial Period Configuration                          │
│     Global trial period for new vendor signups         │
│                                                         │
│  Trial Duration                                        │
│  [ 14 days ▼ ]                                         │
│                                                         │
│  Currently set to: 14 days                             │
│                                                         │
│  Trial applies to: New vendors only (read-only)        │
│                                                         │
│  After trial expires:                                  │
│  • Vendor subscription automatically starts            │
│  • Payment is attempted immediately                    │
│  • No reminders are sent                               │
│                                                         │
│  ⚠️ Platform-Wide Rule: Trial rules apply globally    │
│     and cannot be overridden per vendor.               │
└─────────────────────────────────────────────────────────┘
```

**Fields:**

**Trial Duration (Dropdown):**
- Options:
  - 7 days
  - 14 days
  - 30 days
- Default: 14 days

**Trial Applies To:**
- Read-only label: "New vendors only"
- No toggle or override option

**After Trial Expires:**
- Vendor subscription automatically starts
- Payment is attempted immediately
- No reminders are sent

**Helper Text:**
```
⚠️ Platform-Wide Rule: Trial rules apply globally and 
cannot be overridden per vendor.
```

**Visual:**
- Green icon (⚡ Zap) in circle
- Amber warning banner at bottom
- Gray info box for behavior details

---

## 5. PAYMENT GRACE PERIOD RULES (KEPT)

### Payment Grace Period Configuration

**Visual:**
```
┌─────────────────────────────────────────────────────────┐
│  🕒 Payment Grace Period Configuration                  │
│     Automatic enforcement after failed payments        │
│                                                         │
│  Grace Period Duration                                 │
│  [ 7 days ▼ ]                                          │
│                                                         │
│  Currently set to: 7 days                              │
│                                                         │
│  If payment fails:                                     │
│  • Vendor remains active during grace period           │
│  • Vendor can continue taking orders                   │
│  • Grace period countdown begins immediately           │
│                                                         │
│  After grace period expires:                           │
│  • Vendor is automatically suspended                   │
│  • Vendor status set to Not Live                       │
│  • No admin action required                            │
│                                                         │
│  🚫 Automatic Suspension: Grace period expiration      │
│     results in immediate, automatic vendor suspension. │
│     No manual intervention occurs.                     │
└─────────────────────────────────────────────────────────┘
```

**Fields:**

**Grace Period Duration (Dropdown):**
- Options:
  - 0 days (immediate suspension)
  - 3 days
  - 7 days
  - 14 days
- Default: 7 days

**Behavior - If Payment Fails:**
- Vendor remains active during grace period
- Vendor can continue taking orders
- Grace period countdown begins immediately

**Behavior - After Grace Period Expires:**
- Vendor is automatically suspended
- Vendor status set to Not Live
- No admin action required

**Warning Text:**
```
🚫 Automatic Suspension: Grace period expiration results 
in immediate, automatic vendor suspension. No manual 
intervention occurs.
```

**Visual:**
- Orange icon (🕒 Clock) in circle
- Red warning banner at bottom
- Gray info box for behavior details

---

## 6. AUTO-ENFORCEMENT SUMMARY (NEW SECTION)

### Automated Enforcement Policy

**Visual:**
```
┌─────────────────────────────────────────────────────────┐
│  ℹ️ Automated Enforcement Policy                        │
│                                                         │
│  ✅ Subscriptions are charged automatically on billing │
│     cycle date                                         │
│                                                         │
│  ✅ Failed payments trigger grace period automatically  │
│                                                         │
│  ✅ Grace period expiry suspends vendor and sets       │
│     status to Not Live                                 │
│                                                         │
│  ✅ No payment reminders or manual billing actions     │
│     are sent                                           │
└─────────────────────────────────────────────────────────┘
```

**Purpose:**
- Makes enforcement rules explicit and unambiguous
- Read-only (no actions)
- Blue background for informational tone
- Checkmark icons for each rule

**Content:**
1. "Subscriptions are charged automatically on billing cycle date"
2. "Failed payments trigger grace period automatically"
3. "Grace period expiry suspends vendor and sets status to Not Live"
4. "No payment reminders or manual billing actions are sent"

**Visual:**
- Blue background (`bg-blue-50`)
- Blue border
- Info icon (ℹ️)
- Checkmark icons (✅) for each bullet
- Bold heading: "Automated Enforcement Policy"

---

## 7. AUDIT & CHANGE CONTROL (MANDATORY)

### 7.1 Actions Requiring Confirmation

**Any change to:**
1. Trial duration
2. Grace period duration

**Requires:**
1. Confirmation modal
2. Reason input (mandatory, min 10 characters)
3. Logged to Audit Log

### 7.2 Confirmation Modal

**Modal Structure:**

```
┌───────────────────────────────────────────────────┐
│  ⚠️ Change Trial Duration                         │
│     Platform-wide governance change               │
│                                                   │
│  Change trial period from 14 days to 30 days?    │
│                                                   │
│  ℹ️ This affects all future vendor signups.      │
│     Existing vendors are not affected.           │
│                                                   │
│  Reason for Change *                             │
│  [Extending trial to increase conversion rate    │
│   and allow more time for vendor evaluation]     │
│                                                   │
│  10/10 characters minimum                        │
│                                                   │
│  ⚠️ This change will be logged to the audit      │
│     trail with your admin ID, timestamp, and     │
│     reason.                                      │
│                                                   │
│  [ Cancel ]  [ Confirm Change ]                   │
└───────────────────────────────────────────────────┘
```

**Fields:**

**Title:**
- "Change Trial Duration" or "Change Grace Period"

**Description:**
- "Change trial period from X days to Y days?"
- Shows before and after values

**Impact Warning:**
- "This affects all future vendor signups. Existing vendors are not affected."
- OR "This affects all future payment failures. Vendors currently in grace period are not affected."

**Reason Input:**
- Required field (red asterisk)
- Minimum 10 characters
- Placeholder: "Explain why this governance rule is being changed (minimum 10 characters)"
- Character counter: "10/10 characters minimum"

**Audit Warning:**
- "This change will be logged to the audit trail with your admin ID, timestamp, and reason."

**Buttons:**
- Cancel (gray)
- Confirm Change (purple, disabled if reason < 10 chars)

### 7.3 Validation

**Reason Field:**
- Must not be empty
- Must be at least 10 characters
- Trimmed before validation

**Error Toasts:**
```
❌ Reason required
   Please provide a reason for this change

❌ Reason too short
   Please provide a detailed reason (minimum 10 characters)
```

**Success Toast:**
```
✅ Trial duration updated
   Change logged to audit trail

✅ Grace period updated
   Change logged to audit trail
```

### 7.4 Audit Log Entry

**Structure:**

**Trial Duration Change:**
```json
{
  "action": "GOVERNANCE_RULE_CHANGED",
  "setting": "trialDuration",
  "before": 14,
  "after": 30,
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:00:00Z",
  "reason": "Extending trial to increase conversion rate and allow more time for vendor evaluation"
}
```

**Grace Period Change:**
```json
{
  "action": "GOVERNANCE_RULE_CHANGED",
  "setting": "gracePeriod",
  "before": 7,
  "after": 3,
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:05:00Z",
  "reason": "Reducing grace period to enforce faster payment compliance"
}
```

**Required Fields:**
- `action` - "GOVERNANCE_RULE_CHANGED"
- `setting` - "trialDuration" or "gracePeriod"
- `before` - Previous value (number)
- `after` - New value (number)
- `admin` - Admin user ID
- `timestamp` - ISO 8601 format
- `reason` - Change reason (string, min 10 chars)

---

## 8. PERMISSIONS & VISIBILITY

### 8.1 Super Admin

**Can:**
- ✅ View all settings
- ✅ Edit trial duration
- ✅ Edit grace period
- ✅ Confirm changes with reason
- ✅ Access audit log

### 8.2 Other Roles (Finance Admin, Support Admin, etc.)

**Can:**
- ✅ View all settings (read-only)
- ✅ See current values

**Cannot:**
- ❌ Edit trial duration
- ❌ Edit grace period
- ❌ Make any changes

**Visual Indicators:**
- Dropdown fields disabled (`disabled` attribute)
- Gray background (`bg-gray-50`)
- Cursor not-allowed (`cursor-not-allowed`)
- Read-only banner shown

### 8.3 Read-Only Mode

**For non-Super Admin:**
```
┌─────────────────────────────────────────────────┐
│  🔒 Read-Only Access                            │
│                                                 │
│  You have read-only access to subscription     │
│  governance. Only Super Admin can modify       │
│  enforcement rules.                            │
└─────────────────────────────────────────────────┘
```

**Visual:**
- Amber background
- Lock icon
- Appears below main governance banner

---

## 9. NAVIGATION GUARDRAILS

### Related Settings Section

**Visual:**
```
┌─────────────────────────────────────────────────┐
│  Related Settings                               │
│                                                 │
│  Manage plans and pricing              →       │
│  View vendor subscriptions             →       │
└─────────────────────────────────────────────────┘
```

**Links:**

**1. Manage plans and pricing**
- Destination: Admin → Subscriptions (Subscription Management)
- Purpose: Configure Basic/Standard/Premium plans, pricing, features
- Icon: External link (→)

**2. View vendor subscriptions**
- Destination: Admin → Vendors (Vendor Management)
- Purpose: See which vendors are on which plans
- Icon: External link (→)

**Behavior:**
- Links open in same window
- Hover state: text turns purple
- External link icon turns purple on hover

**Purpose:**
- Clear navigation to where other concepts live
- No duplication of configuration
- One source of truth maintained

---

## 10. CONSTRAINTS (DO NOT VIOLATE)

### ❌ Do NOT:

1. **Reintroduce plan definitions**
   - No Basic / Standard / Premium plan cards
   - No pricing display
   - No feature lists

2. **Add invoice logic**
   - No invoice frequency settings
   - No auto-send invoice toggles
   - No invoice templates

3. **Add reminders**
   - No payment reminder settings
   - No reminder frequency
   - No reminder templates

4. **Allow vendor overrides**
   - No per-vendor trial periods
   - No per-vendor grace periods
   - Global rules only

5. **Allow admin billing actions**
   - No manual invoice generation
   - No manual payment collection
   - No override buttons

### ✅ DO:

1. **Keep trial period rules**
   - Global only
   - Dropdown selector
   - Clear behavior documentation

2. **Keep grace period rules**
   - Global only
   - Dropdown selector
   - Clear behavior documentation

3. **Add auto-enforcement summary**
   - Read-only info block
   - Makes rules explicit

4. **Require confirmation for changes**
   - Confirmation modal
   - Reason input
   - Audit logging

5. **Enforce Super Admin requirement**
   - Check role before changes
   - Show read-only mode for non-Super Admins
   - Display permission notices

6. **Link to other pages**
   - "Manage plans and pricing" → Subscriptions
   - "View vendor subscriptions" → Vendors

---

## 11. PRODUCTION CHECKLIST

### ✅ Platform Governance
- [ ] Banner with strengthened copy
- [ ] Shield icon + Lock icon
- [ ] Non-dismissable
- [ ] Link to Subscription Management

### ✅ Auto-Enforcement Summary
- [ ] Blue info block
- [ ] 4 enforcement rules listed
- [ ] Checkmark icons
- [ ] Read-only (no actions)

### ✅ Trial Period Rules
- [ ] Green icon (Zap)
- [ ] Dropdown: 7 / 14 / 30 days
- [ ] "Trial applies to: New vendors only"
- [ ] Behavior documentation
- [ ] Platform-wide warning

### ✅ Grace Period Rules
- [ ] Orange icon (Clock)
- [ ] Dropdown: 0 / 3 / 7 / 14 days
- [ ] Behavior documentation (if payment fails)
- [ ] Behavior documentation (after expiry)
- [ ] Automatic suspension warning (red)

### ✅ Change Confirmation
- [ ] Modal triggered on dropdown change
- [ ] Before/after values shown
- [ ] Impact warning
- [ ] Reason input (required, min 10 chars)
- [ ] Character counter
- [ ] Audit warning
- [ ] Disabled "Confirm" until valid

### ✅ Audit Logging
- [ ] Trial duration changes logged
- [ ] Grace period changes logged
- [ ] Before/after values
- [ ] Reason included
- [ ] Admin ID and timestamp

### ✅ Permissions
- [ ] Super Admin can edit
- [ ] Other roles read-only
- [ ] Disabled dropdowns for non-Super Admins
- [ ] Read-only banner shown

### ✅ Navigation Guardrails
- [ ] "Related Settings" section
- [ ] Link to Subscription Management
- [ ] Link to Vendor Management
- [ ] External link icons

### ✅ Removed Sections
- [ ] No subscription plans list
- [ ] No pricing
- [ ] No features
- [ ] No invoice settings
- [ ] No reminder settings

---

## 12. SUCCESS CRITERIA

**Subscription Governance is successful if:**

✅ Only Super Admin can modify rules  
✅ Changes require confirmation + reason  
✅ All changes logged to audit trail  
✅ Trial period is global only  
✅ Grace period is global only  
✅ No plan definitions present  
✅ No invoice/reminder settings present  
✅ Auto-enforcement policy is explicit  
✅ Links to other pages work  

**Subscription Governance has failed if:**

❌ Non-Super Admin can modify rules  
❌ Changes happen without confirmation  
❌ Reason not required  
❌ Per-vendor overrides exist  
❌ Plan definitions present  
❌ Invoice settings present  
❌ Reminder settings present  
❌ Manual billing actions allowed  
❌ Audit log not populated  

---

## 13. ONE SOURCE OF TRUTH PER CONCEPT

### Concept Ownership Table

| Concept | Source of Truth | NOT Here |
|---------|----------------|----------|
| Subscription Plans (Basic/Standard/Premium) | **Admin → Subscriptions** | ❌ |
| Plan Pricing (monthly/yearly) | **Admin → Subscriptions** | ❌ |
| Plan Features (QR, Analytics, etc.) | **Admin → Subscriptions** | ❌ |
| Order Limits per Plan | **Admin → Subscriptions** | ❌ |
| Trial Duration (global rule) | **Admin → System Settings → Subscriptions** | ✅ |
| Grace Period (global rule) | **Admin → System Settings → Subscriptions** | ✅ |
| Invoice Templates | **Admin → Finance & Billing** | ❌ |
| Vendor Subscription Status | **Admin → Vendors** | ❌ |
| Payment History | **Admin → Finance & Billing** | ❌ |

**Key Principle:**
- Each concept has ONE authoritative location
- No duplication across admin pages
- Clear navigation between related concepts

---

## 14. ENFORCEMENT WORKFLOW (FOR REFERENCE)

### New Vendor Signup Flow

```
1. Vendor signs up
   ↓
2. Trial starts (duration from Trial Period Rules)
   ↓
3. Trial countdown begins
   ↓
4. Trial expires
   ↓
5. Subscription starts automatically
   ↓
6. Payment attempted immediately
   ↓
   ├─ SUCCESS → Vendor active, subscription charged
   │
   └─ FAILURE → Grace period starts (duration from Grace Period Rules)
              ↓
              Grace period countdown begins
              ↓
              Grace period expires
              ↓
              Vendor automatically suspended
              ↓
              Vendor status set to Not Live
```

**Key Points:**
- No reminders sent
- No admin intervention
- All automatic
- Rules from this page govern the entire flow

---

## 15. VISUAL HIERARCHY

### Page Structure (Top to Bottom)

1. **Page Header**
   - Title: "Subscription Governance"
   - Subtitle: "Platform-wide rules for subscription enforcement and lifecycle"

2. **Platform Governance Banner** (Purple)
   - Shield icon
   - Lock icon
   - Bold text
   - Link to Subscription Management

3. **Read-Only Banner** (Amber, if not Super Admin)
   - Lock icon
   - Permission notice

4. **Auto-Enforcement Summary** (Blue)
   - Info icon
   - 4 enforcement rules
   - Checkmarks

5. **Navigation Guardrails** (Gray)
   - Related Settings
   - 2 external links

6. **Trial Period Rules** (Green icon)
   - Dropdown
   - Behavior docs
   - Warning banner

7. **Grace Period Rules** (Orange icon)
   - Dropdown
   - Behavior docs
   - Warning banner

**Spacing:**
- 24px between sections (`space-y-6`)
- Consistent padding in boxes (`p-4` or `p-5`)
- Clear visual separation

---

**END OF SUBSCRIPTION GOVERNANCE SPECIFICATION**
