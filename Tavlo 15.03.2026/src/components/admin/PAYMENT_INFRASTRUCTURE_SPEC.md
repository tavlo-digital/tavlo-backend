# Enhanced Payment Infrastructure - Platform Control & Monitoring Specification

## Overview

The **Enhanced Payment Infrastructure** page governs which payment providers exist on Tavlo, how they are connected, and whether they are globally available.

**Core Philosophy:**
- This page controls infrastructure, not transactions
- Vendor payment behavior is vendor-controlled
- All changes have platform-wide blast radius
- Actions hard to perform, easy to audit
- Super Admin only

---

## 1. PLATFORM GOVERNANCE BANNER (STRENGTHENED)

### Enhanced Banner

```
┌─────────────────────────────────────────────────────────┐
│ 🛡️ Platform Infrastructure Only                        │
│                                                         │
│ These settings control Tavlo's payment infrastructure  │
│ only. Vendors configure payment methods, tipping, and  │
│ service fees in their own dashboards. Changes here     │
│ affect the entire platform.                            │
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

**Behavior:**
- Non-dismissable
- Always visible at top of page
- Cannot be hidden or collapsed

---

## 2. PAGE SCOPE CLARIFICATION

### Two-Column Info Block

**Left Column - What This Page Does (Green):**
```
✅ What This Page Does

• Enables or disables payment providers globally
• Stores and validates PSP credentials
• Monitors webhook and provider health
• Shows platform-wide payment impact
```

**Right Column - What This Page Does NOT Do (Red):**
```
❌ What This Page Does NOT Do

• Configure vendor fees or payment rules
• Control cash acceptance
• Manage refunds or retries
• Resolve individual payment issues
```

**Visual:**
- Green background for "does"
- Red background for "does not"
- Checkmark and X icons
- Bullet lists for clarity

**Purpose:**
- Clear separation of concerns
- Sets expectations immediately
- Prevents misuse of page

---

## 3. PAYMENT PROVIDER CARDS

### Provider Card Structure

```
┌─────────────────────────────────────────────────────────────┐
│  [Logo] Stripe                    [Active] [🟢 Live (Prod)] │
│         Card payments & digital wallets                      │
│                                                              │
│  ⚠️ Webhook Degraded                                        │
│  3 failed events in last 24h                                │
│                                                              │
│  ┌─ Credentials & Environment ──┐  ┌─ Vendor Impact ─────┐│
│  │ Environment: [Live ▼]         │  │  1,043              ││
│  │ ⚠️ Production mode - real     │  │  Vendors using      ││
│  │    payments processed         │  │                     ││
│  │                               │  │  2,847              ││
│  │ Publishable Key:              │  │  Payments (24h)     ││
│  │ [pk_live_•••••] [🔄]         │  │                     ││
│  │                               │  │  [Deactivate]       ││
│  │ Secret Key:                   │  │                     ││
│  │ [sk_live_•••••] [🔄]         │  │                     ││
│  │                               │  └─────────────────────┘│
│  │ Webhook Health: Healthy       │                         │
│  │ Last Event: 2025-01-06 16:30  │                         │
│  │ Failed (24h): 0               │                         │
│  │ [View webhook logs →]         │                         │
│  └───────────────────────────────┘                         │
└─────────────────────────────────────────────────────────────┘
```

### 3.1 Provider Header

**Display:**
- **Provider logo** (Lucide icon in colored circle)
- **Provider name** (large, bold)
- **Description** (gray, smaller)
- **Status badge** (right-aligned):
  - 🔴 **Disabled** - Gray badge
  - ⚡ **Configured** - Blue badge (keys saved, not active)
  - ✅ **Active** - Green badge (available to vendors)
- **Environment badge** (right-aligned):
  - 🟢 **Live (Production)** - Green with 2px border
  - 🟡 **Test (Sandbox)** - Yellow with 2px border

**Environment badge is visually prominent:**
- Larger text
- 2px border
- Emoji indicator
- Always visible

---

## 4. ENVIRONMENT SAFETY CONTROLS (MANDATORY)

### 4.1 Environment Selector

**Visual:**
```
Environment
[Live Environment (Production) ▼]

⚠️ Production mode - real payments processed
```

**Options:**
- Test Environment (Sandbox)
- Live Environment (Production)

**Behavior:**
- Dropdown selector
- Currently selected environment shown
- Warning text if Live selected

---

### 4.2 Environment Switch Modal (CRITICAL)

**Triggered when:**
- Admin changes environment dropdown
- **Must confirm before change applies**

**Modal Structure:**

**Switching to Live:**
```
┌───────────────────────────────────────────────────┐
│  ⚠️  Switch to Live Environment?                  │
│      Stripe                                       │
│                                                   │
│  ⚠️ This affects real payments platform-wide     │
│                                                   │
│  Switching to live mode will process real        │
│  transactions for all 1,043 vendors using Stripe.│
│                                                   │
│  Type "SWITCH TO LIVE" to confirm:               │
│  [SWITCH TO LIVE                           ]      │
│                                                   │
│  [ Cancel ]  [ Switch Environment ]               │
└───────────────────────────────────────────────────┘
```

**Switching to Test:**
```
┌───────────────────────────────────────────────────┐
│  ℹ️  Switch to Test Environment?                  │
│      Stripe                                       │
│                                                   │
│  This will switch the provider to test/sandbox   │
│  mode. No confirmation text required.            │
│                                                   │
│  [ Cancel ]  [ Switch Environment ]               │
└───────────────────────────────────────────────────┘
```

**Rules:**
1. **Switching to Live requires typing:** `SWITCH TO LIVE`
2. **Switching to Test:** No confirmation text required
3. **Action logged in Audit Log** (every switch)
4. **Modal shows:**
   - Red background if going live
   - Number of affected vendors
   - Platform-wide warning
5. **No silent switching allowed**

**Audit Log Entry:**
```json
{
  "action": "ENVIRONMENT_SWITCHED",
  "provider": "stripe",
  "from": "test",
  "to": "live",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:00:00Z",
  "vendorsAffected": 1043,
  "reason": "Production deployment"
}
```

---

## 5. CREDENTIAL MANAGEMENT (SECURE BY DESIGN)

### 5.1 Credential Display

**Publishable Key:**
```
Publishable Key
[pk_live_••••••••••••••••] [🔄]

Keys cannot be viewed after save. Only replacement allowed.
```

**Secret Key:**
```
Secret Key
[sk_live_••••••••••••••••] [🔄]

Fully masked. Never stored in plain text.
```

### 5.2 Security Rules

**Keys are NEVER fully visible:**
- ❌ No "show password" icon
- ❌ No "copy" button (after save)
- ❌ No plaintext display

**Only "Replace key" is allowed:**
- 🔄 Replace button triggers modal
- Must enter new key
- Must provide reason for change
- Old key permanently replaced

### 5.3 Replace Key Modal

```
┌───────────────────────────────────────────────────┐
│  🔑 Replace Publishable Key                       │
│      Stripe                                       │
│                                                   │
│  New Publishable Key:                            │
│  [pk_live_new_key_here                    ]      │
│                                                   │
│  Reason for Change:                              │
│  [Rotating credentials quarterly          ]      │
│                                                   │
│  ⚠️ This will immediately affect all platform    │
│     payments using this provider.                │
│                                                   │
│  [ Cancel ]  [ Replace Key ]                     │
└───────────────────────────────────────────────────┘
```

**Validation:**
- New key format validated
- Reason required (min 10 characters)
- Confirmation modal shown
- Action logged

**Audit Log:**
```json
{
  "action": "CREDENTIAL_REPLACED",
  "provider": "stripe",
  "credentialType": "publishableKey",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:05:00Z",
  "reason": "Rotating credentials quarterly"
}
```

---

## 6. PROVIDER ACTIVATION FLOW (STATE-BASED)

### 6.1 Provider States

**1. Disabled**
- No credentials configured
- Gray badge
- Cannot activate
- Action: "Configure credentials first"

**2. Configured**
- Credentials saved
- Webhook verified
- Blue badge (⚡ Configured)
- Can activate
- Action: "Activate for All Vendors" button

**3. Active**
- Available to all vendors
- Green badge (✅ Active)
- Vendors can enable in their settings
- Action: "Deactivate" button

### 6.2 Activation Flow

**Step 1: Click "Activate Provider"**

**Step 2: Pre-Activation Checks**
```
✅ Credentials configured
✅ Environment: live
✅ Webhook status: healthy
```

**If webhook is down:**
```
❌ Cannot activate
   Webhook is down. Fix connectivity first.
```

**Step 3: Activation Confirmation Modal**
```
┌───────────────────────────────────────────────────┐
│  ✅ Activate Provider?                            │
│      Stripe                                       │
│                                                   │
│  ℹ️ Platform-Wide Activation                     │
│                                                   │
│  This will make Stripe available to all vendors  │
│  on the platform. Vendors can then choose to     │
│  enable it in their payment settings.            │
│                                                   │
│  Readiness Checklist:                            │
│  ✅ Credentials configured                       │
│  ✅ Environment: live                            │
│  ✅ Webhook status: healthy                      │
│                                                   │
│  [ Cancel ]  [ Activate for All Vendors ]        │
└───────────────────────────────────────────────────┘
```

**Step 4: Success**
```
✅ Provider activated
   Stripe is now available to all vendors
```

**Audit Log:**
```json
{
  "action": "PROVIDER_ACTIVATED",
  "provider": "stripe",
  "environment": "live",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:10:00Z",
  "webhookHealth": "healthy"
}
```

### 6.3 Deactivation Flow

**Deactivation Rules:**
1. **Cannot deactivate if vendors are using it**
2. Must have 0 vendors using provider
3. Confirmation modal required

**If vendors using:**
```
❌ Cannot deactivate
   1,043 vendors are using this provider
```

**If no vendors using:**
```
Deactivate Provider button enabled
```

**Deactivation Confirmation:**
```
┌───────────────────────────────────────────────────┐
│  ⚠️ Deactivate Provider?                          │
│      PayPal                                       │
│                                                   │
│  This will make PayPal unavailable for new vendor│
│  signups. No vendors currently using this.       │
│                                                   │
│  [ Cancel ]  [ Deactivate Provider ]             │
└───────────────────────────────────────────────────┘
```

---

## 7. WEBHOOK HEALTH (FIRST-CLASS SIGNAL)

### 7.1 Webhook Health Panel

**Display:**
```
Webhook Health

Status: Healthy
Last Event Received: 2025-01-06 16:30:22
Failed Events (24h): 0

[View webhook logs →]
```

**Status Indicators:**

**🟢 Healthy:**
- Green icon + text
- Last event recent (< 5 min)
- 0 failed events

**🟡 Degraded:**
- Amber/yellow icon + text
- Some failed events (1-10)
- Last event within 1 hour

**🔴 Down:**
- Red icon + text
- No events in > 1 hour
- High failed event count (> 10)

### 7.2 Webhook Degraded Alert

**If webhook is degraded or down:**
```
┌─────────────────────────────────────────────────┐
│  ⚠️ Webhook Degraded                            │
│  3 failed events in last 24h. This may affect   │
│  payment confirmations.                         │
└─────────────────────────────────────────────────┘
```

**Visual:**
- Amber background (degraded) or Red background (down)
- Appears at top of provider card
- Cannot be dismissed
- Triggers platform alert

**Trigger:**
- If health !== 'healthy'
- Show warning badge on provider card
- Admins must investigate

### 7.3 Webhook Logs Link

**Button:**
```
[View webhook logs →]
```

**Action:**
- Opens detailed log view (separate page)
- Shows last 100 webhook events
- Includes:
  - Timestamp
  - Event type
  - Status (success/failed)
  - Retry attempts
  - Error messages

---

## 8. VENDOR IMPACT PREVIEW (READ-ONLY)

### 8.1 Impact Summary Card

**Display:**
```
┌───────────────────────────┐
│ 📈 Vendor Impact          │
│                           │
│     1,043                 │
│ Vendors using provider    │
│                           │
│     1,043                 │
│ Active subscriptions      │
│                           │
│     2,847                 │
│ Payments (last 24h)       │
└───────────────────────────┘
```

**Purpose:**
- Shows blast radius before changes
- Prevents accidental disruption
- Read-only (no actions)

**Metrics:**
1. **Vendors using this provider**
   - Count of vendors with provider enabled
2. **Active subscriptions**
   - Total subscriptions using this provider
3. **Payments (last 24h)**
   - Transaction count in last 24 hours

**Visual:**
- Gray background box
- Large numbers (bold)
- Small descriptive text
- TrendingUp icon in header

---

## 9. CASH PAYMENTS SECTION (CLARIFIED)

### Cash Payment Info Block

```
┌─────────────────────────────────────────────────┐
│  ℹ️ Cash Payment Configuration                  │
│                                                 │
│  Cash acceptance is configured by vendors.      │
│  Tavlo does not enable or disable cash         │
│  payments. Each vendor decides whether to       │
│  accept cash in their dashboard settings.      │
└─────────────────────────────────────────────────┘
```

**Visual:**
- Blue background
- Info icon
- Bold text: "Cash acceptance is configured by vendors"

**Behavior:**
- ❌ No toggles
- ❌ No controls
- ℹ️ Info block only (read-only)

**Purpose:**
- Clear scope boundary
- Vendors control cash acceptance
- Tavlo does not interfere

---

## 10. AUDIT & CHANGE LOGGING (MANDATORY)

### 10.1 Logged Actions

**All actions logged:**
1. Environment switches (test ↔ live)
2. Credential updates (replace key)
3. Provider activation
4. Provider deactivation
5. Webhook configuration changes

### 10.2 Audit Log Entry Structure

**Required fields:**
- `action` - Action type (enum)
- `provider` - Provider ID (stripe, paypal, etc.)
- `admin` - Admin user ID
- `timestamp` - ISO 8601 format
- `environment` - Current environment
- `reason` - Required for credential changes

**Optional fields:**
- `vendorsAffected` - Count of impacted vendors
- `webhookHealth` - Webhook status at time of action
- `credentialType` - Type of credential changed

### 10.3 Example Audit Logs

**Environment Switch:**
```json
{
  "action": "ENVIRONMENT_SWITCHED",
  "provider": "stripe",
  "from": "test",
  "to": "live",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:00:00Z",
  "vendorsAffected": 1043,
  "reason": "Production deployment"
}
```

**Credential Replacement:**
```json
{
  "action": "CREDENTIAL_REPLACED",
  "provider": "stripe",
  "credentialType": "publishableKey",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:05:00Z",
  "reason": "Quarterly credential rotation"
}
```

**Provider Activation:**
```json
{
  "action": "PROVIDER_ACTIVATED",
  "provider": "paypal",
  "environment": "test",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:10:00Z",
  "webhookHealth": "healthy"
}
```

**Provider Deactivation:**
```json
{
  "action": "PROVIDER_DEACTIVATED",
  "provider": "paypal",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:15:00Z",
  "vendorsUsing": 0
}
```

---

## 11. PERMISSION RULES

### 11.1 Super Admin

**Can:**
- ✅ View credentials (masked)
- ✅ Change environments
- ✅ Replace credentials
- ✅ Activate providers
- ✅ Deactivate providers
- ✅ View webhook logs
- ✅ Access all settings

### 11.2 Other Roles (Finance Admin, Support Admin, etc.)

**Can:**
- ✅ View provider status (read-only)
- ✅ View provider health (read-only)
- ✅ View vendor impact (read-only)

**Cannot:**
- ❌ View credentials
- ❌ Change environments
- ❌ Replace credentials
- ❌ Activate/deactivate providers
- ❌ Modify any settings

**Visual Indicators:**
- 🔒 Lock icon on disabled fields
- Gray/disabled buttons
- "Super Admin Only" notices

### 11.3 Read-Only Mode

**For non-Super Admin:**
```
┌─────────────────────────────────────────────────┐
│  🔒 Read-Only Access                            │
│                                                 │
│  You have read-only access to payment          │
│  infrastructure. Only Super Admin can modify   │
│  credentials or activate providers.            │
└─────────────────────────────────────────────────┘
```

---

## 12. CONSTRAINTS (DO NOT VIOLATE)

### ❌ Do NOT:

1. **Add vendor-level controls**
   - No per-vendor payment settings
   - No vendor fee configuration
   - No vendor-specific toggles

2. **Add manual payment actions**
   - No refund buttons
   - No retry payment options
   - No transaction management

3. **Allow silent production changes**
   - Every change requires confirmation
   - Live environment switch requires typing confirmation
   - No auto-save without confirmation

4. **Expose keys**
   - No plaintext key display
   - No copy buttons (after save)
   - No "show password" toggles

5. **Allow this page to resolve payment issues**
   - This page observes and governs
   - Does not fix individual transactions
   - Redirect to appropriate tools

### ✅ DO:

1. **Enforce Super Admin requirements**
   - Check role before every action
   - Show read-only mode for non-Super Admins
   - Display permission notices

2. **Log everything**
   - Every environment switch
   - Every credential change
   - Every activation/deactivation

3. **Show blast radius**
   - Vendor impact always visible
   - Cannot deactivate with active vendors
   - Warning modals show affected counts

4. **Make production changes hard**
   - Confirmation modals
   - Typing "SWITCH TO LIVE" required
   - Multiple steps for critical actions

5. **Prioritize webhook health**
   - Always visible
   - Cannot activate with webhook down
   - Alerts when degraded

---

## 13. PRODUCTION CHECKLIST

### ✅ Platform Governance
- [ ] Strengthened banner with bold text
- [ ] Shield icon + Lock icon
- [ ] Non-dismissable
- [ ] "Changes affect entire platform" warning

### ✅ Scope Clarification
- [ ] Two-column info block (does/does not)
- [ ] Green for "does", red for "does not"
- [ ] Clear bullet points

### ✅ Provider Cards
- [ ] Status badges (Disabled/Configured/Active)
- [ ] Environment badges (Live/Test with 2px border)
- [ ] Logo + name + description

### ✅ Environment Controls
- [ ] Dropdown selector
- [ ] Confirmation modal for switches
- [ ] "SWITCH TO LIVE" typing requirement
- [ ] Audit logging

### ✅ Credentials
- [ ] Masked display (no plaintext)
- [ ] Replace button only
- [ ] Reason required for changes
- [ ] Audit logging

### ✅ Provider Activation
- [ ] State-based (Disabled → Configured → Active)
- [ ] Pre-activation checks (webhook, credentials)
- [ ] Confirmation modal
- [ ] Cannot activate with webhook down

### ✅ Webhook Health
- [ ] Status indicator (Healthy/Degraded/Down)
- [ ] Last event timestamp
- [ ] Failed events count (24h)
- [ ] Alert banner if degraded/down
- [ ] "View logs" link

### ✅ Vendor Impact
- [ ] Vendors using count
- [ ] Active subscriptions
- [ ] Payments last 24h
- [ ] Read-only display

### ✅ Cash Payments
- [ ] Info block only
- [ ] No toggles or controls
- [ ] Clear vendor ownership

### ✅ Audit Logging
- [ ] All actions logged
- [ ] Required fields present
- [ ] Timestamps in ISO 8601
- [ ] Reason field for credential changes

### ✅ Permissions
- [ ] Super Admin can modify
- [ ] Other roles read-only
- [ ] Read-only banner for non-Super Admins
- [ ] Disabled buttons for non-Super Admins

---

## 14. SUCCESS CRITERIA

**Enhanced Payment Infrastructure is successful if:**

✅ Only Super Admin can modify payment infrastructure  
✅ Environment switches require confirmation  
✅ Production changes require typing "SWITCH TO LIVE"  
✅ All actions logged to audit trail  
✅ Keys never visible in plaintext  
✅ Webhook health prominently displayed  
✅ Vendor impact always visible  
✅ Cannot deactivate provider with active vendors  
✅ Cash payments clearly out of scope  

**Enhanced Payment Infrastructure has failed if:**

❌ Non-Super Admin can modify settings  
❌ Environment switches happen silently  
❌ Keys visible or copyable  
❌ Actions not logged  
❌ Vendor impact hidden  
❌ Production changes too easy  
❌ Scope confusion (vendor vs platform)  

---

**END OF ENHANCED PAYMENT INFRASTRUCTURE SPECIFICATION**
