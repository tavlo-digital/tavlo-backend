# Vendor Onboarding Rules - Fully Automated Configuration Specification

## Overview

The **Vendor Onboarding Rules** page defines eligibility rules, required data, and automatic enforcement for vendor registration and go-live.

**Core Philosophy:**
- Vendor onboarding is 100% automated
- No manual approval or review queues
- Vendors go live only when rules are satisfied
- Admins define requirements, not decisions
- System enforces everything automatically

---

## 1. PAGE IDENTITY & FRAMING

### Page Title
```
Vendor Onboarding Rules
```

### Subtitle
```
Configure automated requirements for vendors to join and go live
```

**Purpose:**
- Rules-based configuration (not approval workflow)
- Automation emphasized
- No human intervention required

---

## 2. REMOVE MANUAL APPROVAL CONCEPT (MANDATORY)

### ❌ COMPLETELY REMOVED:

**Old (Manual Approval) Design:**
```
❌ Approval Process

   ⚪ Manual Approval (Recommended)
      Admin reviews and approves each vendor application

   ⚪ Auto-Approval
      Vendors are auto-approved after completing onboarding steps
```

**Why This Was Removed:**
- Implies human decision-making
- Creates expectation of approval queues
- Suggests admin can reject vendors manually
- Conflicts with automation principle

---

### ✅ REPLACED WITH:

**Locked Info Block (Automation Notice):**

```
┌─────────────────────────────────────────────────────────┐
│ ⚡ Fully Automated Onboarding                          │
│                                                         │
│ Vendor onboarding is fully automated. Vendors are      │
│ approved automatically once all required steps are     │
│ completed and verified. No manual approval or admin    │
│ review is required at any step.                        │
│                                                         │
│ 🔒 Super Admin Only                                    │
└─────────────────────────────────────────────────────────┘
```

**Visual:**
- Green background (`bg-green-50`)
- 2px green border (`border-2 border-green-300`)
- Zap icon (⚡) for automation
- Lock icon (🔒) for Super Admin requirement
- **Bold text** for critical statements:
  - "fully automated"
  - "No manual approval or admin review is required"

**Copy Rules:**
1. Use "fully automated" (absolute statement)
2. Use "No manual approval" (explicit negation)
3. Use "automatically once all required steps are completed"

---

## 3. REQUIRED ONBOARDING REQUIREMENTS (RULES ENGINE)

### Section Title
```
Required Onboarding Requirements
```

### Subtitle
```
Define what vendors must complete before going live
```

**Key Change:**
- Language changed from "steps" to "requirements"
- Emphasizes rule-based, not sequential process

---

### 3.1 Required (Locked, Cannot Be Unchecked)

**Visual:**
```
┌─────────────────────────────────────────────────────────┐
│ 🔒 Required (Always Enabled)                           │
│                                                         │
│ ✅ Business information (legal name, address, type)    │
│    [Locked]                                            │
│                                                         │
│ ✅ Contact details (email, phone)                      │
│    [Locked]                                            │
│                                                         │
│ ✅ Subscription plan selection                         │
│    [Locked]                                            │
│                                                         │
│ ✅ VAT / tax identification number                     │
│    [Locked]                                            │
└─────────────────────────────────────────────────────────┘
```

**Items (Always Required):**
1. **Business information** (legal name, address, type)
2. **Contact details** (email, phone)
3. **Subscription plan selection**
4. **VAT / tax identification number**

**Visual Elements:**
- Green background (`bg-green-50`)
- Green border
- Lock icon in header
- Checkmark icons (✅) - not checkboxes
- "Locked" badges on each item
- No interaction possible

**State:**
- Cannot be unchecked
- Always enforced
- System-level requirements

---

### 3.2 Optional (Admin-Configurable)

**Visual:**
```
┌─────────────────────────────────────────────────────────┐
│ Optional (Configurable)                                │
│                                                         │
│ ☐ Business registration documents                      │
│   Require vendors to upload official business          │
│   registration proof                                   │
│                                                         │
│ ☐ Food safety certificates                             │
│   Require food handling and safety certification       │
│   documents                                            │
│                                                         │
│ ☐ Bank account verification (if payouts enabled)       │
│   Require bank account verification for future payout  │
│   functionality                                        │
└─────────────────────────────────────────────────────────┘
```

**Items (Configurable):**
1. **Business registration documents** (default: OFF)
2. **Food safety certificates** (default: OFF)
3. **Bank account verification** (default: OFF, future-oriented)

**Visual Elements:**
- Gray border
- Standard checkboxes (can be toggled)
- Helper text for each option
- Describes what is required

**Behavior:**
- Super Admin can enable/disable
- Triggers confirmation modal
- Logged to audit trail

---

### Helper Text (Amber Warning):

```
⚠️ Automatic Enforcement: Vendors cannot proceed unless 
all required requirements are completed. System 
automatically blocks progression.
```

**Purpose:**
- Makes enforcement explicit
- No manual intervention implied
- System-driven blocking

---

## 4. AUTOMATED VERIFICATION RULES (NEW SECTION)

### Section Title
```
Automatic Verification
```

### Subtitle
```
Configure automated validation rules (no manual review)
```

**Purpose:**
- Define what the system validates automatically
- No manual fallback or override

---

### Verification Rules (Toggles)

**Visual:**
```
☑️ Validate VAT number format and country
   Automatically verify VAT number against EU VIES database

☑️ Validate email ownership
   Send verification email and require confirmation before proceeding

☑️ Validate phone number
   Verify phone number format and send SMS verification code

☑️ Validate subscription payment success
   Confirm payment gateway has successfully processed subscription charge
```

**Items:**
1. **Validate VAT number format and country**
   - Checks against EU VIES database
   - Automatic validation
   - Default: ON

2. **Validate email ownership**
   - Sends verification email
   - Requires confirmation
   - Default: ON

3. **Validate phone number**
   - Format validation
   - SMS verification code
   - Default: ON

4. **Validate subscription payment success**
   - Payment gateway confirmation
   - Checks actual charge status
   - Default: ON

**Helper Text (Red Warning):**
```
🚫 Automatic Blocking: Vendors failing verification are 
automatically blocked until issues are resolved. No manual 
review or override.
```

**Behavior:**
- System validates automatically
- Failure blocks progression
- No manual override
- No admin intervention

---

## 5. SUBSCRIPTION ENFORCEMENT (CLARIFY & LOCK)

### Section Title
```
Subscription Enforcement
```

### Subtitle
```
Configure subscription requirements for going live
```

---

### 5.1 Active Subscription Required (Locked ON)

**Visual:**
```
┌─────────────────────────────────────────────────────────┐
│ 💳 Active subscription required to go live             │
│    🔒 [Locked ON]                                      │
│                                                         │
│    Vendors cannot go live without an active            │
│    subscription. This rule cannot be disabled.         │
└─────────────────────────────────────────────────────────┘
```

**State:**
- ✅ Always enabled (locked ON)
- Green background
- Lock icon
- "Locked ON" badge
- Cannot be disabled

**Purpose:**
- Ensures revenue from all live vendors
- Platform sustainability
- Non-negotiable business rule

---

### 5.2 Allow Trial Without Payment (Optional)

**Visual:**
```
☐ Allow trial without payment
   Linked to trial period settings in Subscription Governance
```

**Behavior:**
- Checkbox (can be toggled)
- Links to Subscription Governance page
- If enabled: Trial period from governance applies
- If disabled: Payment required immediately

**Purpose:**
- Flexibility for trial strategy
- Tied to global governance rules
- Optional business policy

---

### Behavior Explanation (Read-Only, Blue Box):

```
ℹ️ Automatic Behavior:

• Vendors may complete onboarding during trial period
• Vendors cannot go live without meeting subscription rules
• No manual override or approval available
• System enforces automatically based on payment status
```

**Purpose:**
- Makes automation clear
- No manual override
- System-driven enforcement
- Read-only (informational)

---

## 6. COUNTRY AVAILABILITY (ELIGIBILITY FILTER)

### Section Title
```
Vendor Eligibility by Country
```

### Subtitle
```
Select which countries vendors can register from
```

---

### Country Selection (Checkboxes)

**Visual:**
```
☑️ 🇦🇹 Austria
☑️ 🇩🇪 Germany
☑️ 🇨🇭 Switzerland
☑️ 🇮🇹 Italy
☑️ 🇫🇷 France
☐ 🇳🇱 Netherlands
☐ 🇧🇪 Belgium
☐ 🇪🇸 Spain
```

**Default Countries Enabled:**
- Austria (AT)
- Germany (DE)
- Switzerland (CH)
- Italy (IT)
- France (FR)

**Behavior:**
- Super Admin can enable/disable
- Triggers confirmation modal
- Logged to audit trail

**Visual States:**
- Enabled: Green background, green border
- Disabled: White background, gray border

---

### Country Selection Affects (Gray Info Box):

```
Country selection affects:

• VAT number validation rules
• Legal compliance requirements
• Payment provider availability
• Tax calculation methods
```

**Purpose:**
- Educational
- Makes dependencies clear
- Helps admins make informed decisions

---

### Legal Readiness Warning (Amber Warning):

```
⚠️ Legal Readiness Warning: Enabling a country implies 
legal and tax readiness. Ensure compliance requirements 
are met before enabling new countries.
```

**Purpose:**
- Emphasizes legal responsibility
- Warns against premature enabling
- Compliance-first mindset

---

### Automatic Blocking (Red Warning):

```
🚫 Automatic Blocking: Vendors outside selected countries 
cannot register. Registration form will be unavailable.
```

**Behavior:**
- System blocks registration automatically
- No error handling needed
- Form doesn't appear for excluded countries

---

## 7. ONBOARDING STATUS LOGIC (NEW, READ-ONLY)

### Section Title
```
Automated Onboarding State Flow
```

**Purpose:**
- Visual representation of onboarding states
- Educational reference
- Makes automation clear

---

### State Model (Diagram/Bullets):

```
┌─────────────────────────────────────────────────────────┐
│ 🌳 Automated Onboarding State Flow                     │
│                                                         │
│ ⚪ Registered                                          │
│    → Vendor creates account                            │
│                                                         │
│ 🟡 Requirements Incomplete                             │
│    → Filling required information                      │
│                                                         │
│ 🔴 Verification Failed                                 │
│    → Automatic check failed (e.g., invalid VAT)        │
│                                                         │
│ 🔵 Ready for Go-Live                                   │
│    → All requirements met, awaiting vendor action      │
│                                                         │
│ 🟢 Live                                                │
│    → Vendor is accepting orders                        │
│                                                         │
│ 🟠 Suspended                                           │
│    → Payment failure or policy violation               │
│                                                         │
│ All state transitions are automatic based on rule      │
│ satisfaction. No manual status changes.                │
└─────────────────────────────────────────────────────────┘
```

**States:**

1. **Registered** (Gray ⚪)
   - Vendor creates account
   - Initial state

2. **Requirements Incomplete** (Yellow 🟡)
   - Filling required information
   - Work in progress

3. **Verification Failed** (Red 🔴)
   - Automatic check failed
   - Example: Invalid VAT number
   - Blocked until resolved

4. **Ready for Go-Live** (Blue 🔵)
   - All requirements met
   - Awaiting vendor action (publish menu)

5. **Live** (Green 🟢)
   - Vendor is accepting orders
   - Active state

6. **Suspended** (Orange 🟠)
   - Payment failure
   - Policy violation
   - Temporarily blocked

**Visual:**
- Blue background
- GitBranch icon
- Color-coded state dots
- Clear transitions
- Read-only (no interaction)

**Copy (Bottom):**
```
All state transitions are automatic based on rule 
satisfaction. No manual status changes.
```

**Purpose:**
- Makes state flow transparent
- Educational reference
- Emphasizes automation

---

## 8. NOTIFICATIONS (AUTOMATION-ONLY)

### Section Title
```
Automated Notifications
```

### Subtitle
```
Configure system-generated notifications (no human decision emails)
```

**Key Principle:**
- All notifications are system-generated
- No language implying human decisions
- Automation-only

---

### Notification Toggles

**Visual:**
```
☑️ Notify admin team when vendor registers
   Send notification for monitoring purposes only (no action required)

☑️ Send onboarding progress emails to vendor
   Automated emails showing completion status and next steps

☑️ Send go-live confirmation
   Automated email when vendor successfully goes live

☑️ Send automatic rejection explanation (rule-based)
   Explain which automated verification failed (e.g., "VAT validation failed")
```

**Items:**

1. **Notify admin team when vendor registers**
   - For monitoring only
   - No action required
   - FYI notification

2. **Send onboarding progress emails to vendor**
   - Automated progress tracking
   - Shows completion status
   - Next steps guidance

3. **Send go-live confirmation**
   - Automated success email
   - Triggered by state change
   - Congratulatory tone

4. **Send automatic rejection explanation (rule-based)**
   - Explains which rule failed
   - Example: "VAT validation failed"
   - System-generated explanation
   - No human decision implied

**Helper Text (Blue Info Box):**
```
ℹ️ Automation Only: All notifications are system-generated 
based on state changes. No emails imply human approval or 
decision-making.
```

**Purpose:**
- Makes automation clear
- No approval emails
- No "we're reviewing your application"
- Rule-based explanations only

---

## 9. PERMISSIONS & SAFETY

### Role-Based Access

**Super Admin:**
- ✅ View all settings
- ✅ Edit required/optional requirements
- ✅ Enable/disable verification rules
- ✅ Modify country availability
- ✅ Configure notifications

**Other Roles:**
- ✅ View all settings (read-only)
- ❌ Cannot modify anything

**Read-Only Banner (For Non-Super Admin):**
```
🔒 Read-Only Access

You have read-only access to onboarding rules. 
Only Super Admin can modify vendor eligibility requirements.
```

---

### Change Confirmation Required

**Actions requiring confirmation:**
1. Enable/disable optional requirements
2. Change country availability
3. Any modification to rules

**Confirmation Modal Structure:**
```
┌───────────────────────────────────────────────────┐
│  📋 Add Onboarding Requirement                    │
│     Business Registration Documents               │
│                                                   │
│  Require: Business Registration Documents        │
│                                                   │
│  ℹ️ Vendors will be blocked from going live      │
│     until this requirement is satisfied.          │
│                                                   │
│  Reason for Change *                             │
│  [Increasing quality control by requiring        │
│   official business documentation]               │
│                                                   │
│  50/10 characters minimum                        │
│                                                   │
│  🛡️ This change will be logged to the audit     │
│     trail with your admin ID, timestamp, and     │
│     reason.                                      │
│                                                   │
│  [ Cancel ]  [ Confirm Change ]                   │
└───────────────────────────────────────────────────┘
```

**Validation:**
- Reason required (min 10 characters)
- Shows impact warning
- Audit trail notice
- Disabled "Confirm" until valid

---

### Audit Log Entry

**Requirement Change:**
```json
{
  "action": "ONBOARDING_REQUIREMENT_CHANGED",
  "setting": "requireBusinessDocs",
  "after": true,
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:00:00Z",
  "reason": "Increasing quality control by requiring official business documentation"
}
```

**Country Availability Change:**
```json
{
  "action": "COUNTRY_AVAILABILITY_CHANGED",
  "country": "NL",
  "countryAction": "ADDED",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:05:00Z",
  "reason": "Expanding to Netherlands after legal compliance verification"
}
```

---

## 10. REMOVE ILLEGAL OR MISLEADING UI (DO NOT RENDER)

### ❌ COMPLETELY REMOVED:

1. **Approval Buttons**
   - No "Approve Vendor" button
   - No "Reject Vendor" button
   - No manual decision actions

2. **Review Queues**
   - No "Pending Approval" list
   - No "Awaiting Review" status
   - No approval queue view

3. **Manual Status Changes**
   - No "Set to Live" button
   - No "Suspend Vendor" button
   - No manual state override

4. **Approval Language**
   - No "Admin reviews and approves"
   - No "Pending admin approval"
   - No "Requires manual verification"

**Why These Were Removed:**
- All vendor state changes are system-driven
- No human decisions in onboarding flow
- Automation is absolute
- No manual override capability

---

## 11. PRODUCTION CHECKLIST

### ✅ Automation Notice
- [ ] Green banner with Zap icon
- [ ] "Fully automated" language
- [ ] "No manual approval" explicit
- [ ] Lock icon for Super Admin only

### ✅ Required Requirements (Locked)
- [ ] Green background
- [ ] Lock icon in header
- [ ] Checkmarks (not checkboxes)
- [ ] "Locked" badges
- [ ] 4 items always required

### ✅ Optional Requirements
- [ ] Gray border
- [ ] Standard checkboxes
- [ ] Helper text
- [ ] 3 configurable items

### ✅ Automated Verification
- [ ] 4 verification rules
- [ ] Checkboxes for each
- [ ] Helper text explaining automation
- [ ] Red warning for automatic blocking

### ✅ Subscription Enforcement
- [ ] Active subscription locked ON
- [ ] Green background, lock icon
- [ ] Trial toggle optional
- [ ] Behavior explanation (blue box)

### ✅ Country Availability
- [ ] 8 countries listed
- [ ] Flags displayed
- [ ] Green/white visual states
- [ ] 3 info/warning boxes

### ✅ Onboarding State Flow
- [ ] Blue background
- [ ] GitBranch icon
- [ ] 6 states with color dots
- [ ] "No manual status changes" text

### ✅ Automated Notifications
- [ ] 4 notification toggles
- [ ] "Automation only" helper text
- [ ] No approval language

### ✅ Change Confirmation
- [ ] Modal for requirement changes
- [ ] Modal for country changes
- [ ] Reason input (min 10 chars)
- [ ] Impact warnings
- [ ] Audit trail notice

### ✅ Removed Elements
- [ ] No approval radio buttons
- [ ] No review queues
- [ ] No manual status buttons
- [ ] No approval language

---

## 12. SUCCESS CRITERIA

**Vendor Onboarding Rules is successful if:**

✅ No manual approval UI exists  
✅ All requirements clearly locked or optional  
✅ Automation is emphasized throughout  
✅ Verification rules are automatic  
✅ State flow is transparent  
✅ Country blocking is automatic  
✅ Notifications don't imply human decisions  
✅ Changes require confirmation + reason  
✅ All changes logged to audit trail  
✅ Only Super Admin can modify  

**Vendor Onboarding Rules has failed if:**

❌ Manual approval buttons exist  
❌ Approval queues are mentioned  
❌ Admin can manually approve/reject  
❌ State changes are manual  
❌ Notifications imply human review  
❌ Changes happen without confirmation  
❌ Audit log not populated  
❌ Non-Super Admin can modify  
❌ Language suggests manual intervention  

---

## 13. VISUAL HIERARCHY

### Page Structure (Top to Bottom)

1. **Page Header**
   - Title: "Vendor Onboarding Rules"
   - Subtitle: Automation emphasis

2. **Automation Notice** (Green)
   - Zap icon
   - "Fully automated" bold
   - Lock icon for Super Admin

3. **Read-Only Banner** (Amber, if not Super Admin)
   - Lock icon
   - Permission notice

4. **Onboarding State Flow** (Blue)
   - GitBranch icon
   - 6 color-coded states
   - "No manual status changes"

5. **Required Onboarding Requirements**
   - Locked requirements (green box)
   - Optional requirements (gray box)
   - Automatic enforcement warning (amber)

6. **Automated Verification Rules**
   - 4 verification toggles
   - Helper text
   - Automatic blocking warning (red)

7. **Subscription Enforcement**
   - Active subscription locked (green)
   - Trial toggle optional
   - Behavior explanation (blue)

8. **Country Availability**
   - 8 country checkboxes with flags
   - Visual states (green/white)
   - 3 info/warning boxes

9. **Automated Notifications**
   - 4 notification toggles
   - Automation-only helper text

**Spacing:**
- 24px between sections (`space-y-6`)
- Consistent padding (`p-4` or `p-5`)
- Clear visual separation

---

## 14. COPY TONE & LANGUAGE

### Rules-Based Language

**Use:**
- "Fully automated"
- "Automatic enforcement"
- "System-driven"
- "Rule-based"
- "Cannot proceed unless"
- "Automatically blocked"
- "No manual override"

**Avoid:**
- "Admin approval"
- "Manual review"
- "Pending approval"
- "Awaiting review"
- "Approve/reject"
- "Under consideration"

### Example Good Copy:

```
✅ "Vendors are approved automatically once all required 
   steps are completed and verified."

✅ "Vendors failing verification are automatically blocked 
   until issues are resolved."

✅ "All state transitions are automatic based on rule 
   satisfaction."
```

### Example Bad Copy:

```
❌ "Vendors are reviewed and approved by admin"
❌ "Admin can manually approve vendors"
❌ "Pending manual review"
```

---

## 15. ONBOARDING AUTOMATION WORKFLOW (FOR REFERENCE)

### End-to-End Automated Flow

```
1. Vendor clicks "Register"
   ↓
2. Country check (automatic)
   ├─ PASS → Continue
   └─ FAIL → Registration form blocked

3. Vendor fills required information
   ↓
4. Email verification (automatic)
   ├─ PASS → Continue
   └─ FAIL → Blocked until verified

5. Phone verification (automatic)
   ├─ PASS → Continue
   └─ FAIL → Blocked until verified

6. VAT number validation (automatic)
   ├─ PASS → Continue
   └─ FAIL → Blocked, explanation sent

7. Subscription selection & payment (automatic)
   ├─ PASS → Continue
   └─ FAIL → Grace period OR blocked

8. Optional requirements (if enabled)
   ├─ COMPLETE → Continue
   └─ INCOMPLETE → Blocked

9. All requirements satisfied
   ↓
10. State: Ready for Go-Live (automatic)
    ↓
11. Vendor publishes menu
    ↓
12. State: Live (automatic)
    ↓
13. Go-live confirmation email sent (automatic)
```

**Key Points:**
- No admin intervention at any step
- All checks are automatic
- Failures block progression
- State changes are system-driven
- Notifications are rule-triggered

---

## 16. STATE TRANSITION RULES (TECHNICAL)

### State Transition Logic

**Registered → Requirements Incomplete:**
- Automatic on account creation
- No admin action

**Requirements Incomplete → Verification Failed:**
- Automatic when validation fails
- Examples: Invalid VAT, email bounce
- System-triggered

**Requirements Incomplete → Ready for Go-Live:**
- Automatic when all requirements met
- All validations passed
- Subscription active

**Verification Failed → Requirements Incomplete:**
- Automatic when vendor fixes issue
- Re-validation triggered

**Ready for Go-Live → Live:**
- Triggered by vendor publishing menu
- No admin approval

**Live → Suspended:**
- Automatic on payment failure
- Automatic on policy violation
- Grace period from Subscription Governance

**Suspended → Live:**
- Automatic when payment succeeds
- Automatic when violation resolved

**No Manual Transitions:**
- Admin cannot force state changes
- All transitions are rule-based
- System enforces automatically

---

**END OF VENDOR ONBOARDING RULES SPECIFICATION**
