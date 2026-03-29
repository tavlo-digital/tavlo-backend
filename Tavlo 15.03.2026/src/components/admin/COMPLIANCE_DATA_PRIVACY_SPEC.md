# Compliance & Data Privacy - Legal Compliance Configuration Specification

## Overview

The **Compliance & Data Privacy** page defines how Tavlo fulfills mandatory GDPR obligations.

**Core Legal Philosophy:**
- GDPR user rights are mandatory by law
- Admins configure process and handling, not permission
- Legal minimums override admin choices
- All GDPR and data access actions are logged
- Compliance Admin or Super Admin only

---

## 1. PAGE IDENTITY & LEGAL FRAMING

### Page Title
```
Compliance & Data Privacy
```

### Subtitle
```
Configure how Tavlo fulfills mandatory GDPR and data protection obligations
```

**Purpose:**
- Legal compliance framing (not feature toggles)
- Clear GDPR focus
- Configuration, not permission granting

---

## 2. MANDATORY COMPLIANCE NOTICE (TOP BANNER)

### Legal Notice Banner

```
┌─────────────────────────────────────────────────────────┐
│ 🛡️ Legal Compliance Requirements                       │
│                                                         │
│ Tavlo operates under GDPR and applicable EU data       │
│ protection laws. User rights such as data access,      │
│ deletion requests, and data portability cannot be      │
│ disabled. Settings on this page define how requests    │
│ are handled, not whether they exist.                   │
└─────────────────────────────────────────────────────────┘
```

**Visual:**
- Blue background (`bg-blue-50`)
- 2px blue border (`border-2 border-blue-300`)
- Shield icon
- **Bold text** for critical legal statements:
  - "Tavlo operates under GDPR..."
  - "cannot be disabled"
  - "how requests are handled"

**Behavior:**
- Non-dismissable
- Always visible at top of page
- Cannot be hidden or collapsed

**Copy Rules:**
1. Use "operate under GDPR" (not "comply with")
2. Use "cannot be disabled" (absolute statement)
3. Use "how requests are handled, not whether they exist"

---

## 3. GDPR USER RIGHTS — HANDLING CONFIGURATION (NOT TOGGLES)

### Section Title
```
GDPR User Rights Handling
```

### Subtitle
```
Configure how Tavlo processes mandatory GDPR rights. 
These rights cannot be disabled.
```

**Key Principle:**
- ❌ **NO TOGGLES** to enable/disable rights
- ✅ **CONFIGURATION ONLY** for how they're handled

---

### 3.1 Right of Access (GDPR Art. 15)

**Card Structure:**

```
┌─────────────────────────────────────────────────────────┐
│  👁️ Right of Access  🔒 [Always Enabled]               │
│     GDPR Article 15                                     │
│                                                         │
│  Request Handling Mode                                 │
│  [ Automatic export ▼ ]                                │
│                                                         │
│  🕒 SLA Requirement: Response required within 30 days  │
│                                                         │
│  Legal Obligation: Users have the legal right to      │
│  request a copy of their personal data. Tavlo must    │
│  respond within 30 days as required by GDPR.          │
└─────────────────────────────────────────────────────────┘
```

**Visual Elements:**

**Icon & Badge:**
- Eye icon (👁️) in blue circle
- Lock icon (🔒) - always present
- Green badge: "Always Enabled"

**Request Handling Mode (Dropdown):**
- Options:
  - Automatic export
  - Manual review (Support / Compliance Admin)
- Default: Automatic export

**SLA Reminder (Blue Box):**
- Clock icon
- "Response required within 30 days"
- Blue background

**Helper Text (Gray Box):**
```
Legal Obligation: Users have the legal right to request 
a copy of their personal data. Tavlo must respond within 
30 days as required by GDPR.
```

**State:**
- ✅ Always enabled (locked indicator)
- Cannot be disabled
- Only handling mode is configurable

---

### 3.2 Right to Erasure (GDPR Art. 17)

**Card Structure:**

```
┌─────────────────────────────────────────────────────────┐
│  🗑️ Right to Erasure  🔒 [Always Available]           │
│     GDPR Article 17 ("Right to be Forgotten")          │
│                                                         │
│  Deletion Method                                       │
│  [ Anonymization where legal retention applies ▼ ]    │
│                                                         │
│  ⚠️ Legal Retention Disclaimer: Financial and tax      │
│     records are retained as required by law (minimum   │
│     7 years). These records will be anonymized but     │
│     cannot be fully deleted.                           │
│                                                         │
│  Important: Some data cannot be deleted due to legal  │
│  obligations (tax, accounting, fraud prevention) and  │
│  will be anonymized instead.                          │
└─────────────────────────────────────────────────────────┘
```

**Visual Elements:**

**Icon & Badge:**
- Trash icon (🗑️) in red circle
- Lock icon (🔒) - always present
- Green badge: "Always Available"

**Deletion Method (Dropdown):**
- Options:
  - Full deletion where legally permitted
  - Anonymization where legal retention applies
- Default: Anonymization

**Legal Retention Disclaimer (Amber Box):**
- Warning icon
- Amber background
- Bold "Legal Retention Disclaimer"
- Explanation of 7-year retention

**Helper Text (Gray Box):**
```
Important: Some data cannot be deleted due to legal 
obligations (tax, accounting, fraud prevention) and 
will be anonymized instead.
```

**State:**
- ✅ Always available (locked indicator)
- Cannot be disabled
- Only deletion method is configurable

---

### 3.3 Data Portability (GDPR Art. 20)

**Card Structure:**

```
┌─────────────────────────────────────────────────────────┐
│  📥 Data Portability  🔒 [Always Enabled]              │
│     GDPR Article 20                                     │
│                                                         │
│  Export Format                                         │
│  [ JSON (structured, machine-readable) ▼ ]            │
│                                                         │
│  Delivery Method                                       │
│  [ Secure download link (expires in 7 days) ▼ ]       │
│                                                         │
│  Legal Requirement: Data must be provided in a        │
│  structured, machine-readable format that allows      │
│  users to transmit it to another service.             │
└─────────────────────────────────────────────────────────┘
```

**Visual Elements:**

**Icon & Badge:**
- Download icon (📥) in purple circle
- Lock icon (🔒) - always present
- Green badge: "Always Enabled"

**Export Format (Dropdown):**
- Options:
  - JSON (structured, machine-readable)
  - CSV (spreadsheet compatible)
- Default: JSON

**Delivery Method (Dropdown):**
- Options:
  - Secure download link (expires in 7 days)
  - Encrypted email attachment
- Default: Secure download link

**Helper Text (Gray Box):**
```
Legal Requirement: Data must be provided in a structured, 
machine-readable format that allows users to transmit it 
to another service.
```

**State:**
- ✅ Always enabled (locked indicator)
- Cannot be disabled
- Only format and delivery configurable

---

## 4. DATA RETENTION POLICIES (LEGAL MINIMUMS ENFORCED)

### Section Title
```
Data Retention Policies
```

### Subtitle
```
Configure retention periods for different data types 
(legal minimums enforced)
```

---

### 4.1 Order & Financial Data Retention

**Field:**
```
Order & Financial Data Retention

[ 7 years (legal minimum) ▼ ]

🔒 Minimum retention required for tax and accounting compliance
```

**Options:**
- 7 years (legal minimum) ← Default
- 10 years
- 15 years

**Rules:**
- ❌ **Cannot set lower than 7 years** (legal minimum)
- Admin selection blocked if < 7 years
- Error message if attempted:
  ```
  ❌ Legal minimum not met
     Order and financial data must be retained for at least 7 years
  ```

**Visual:**
- Database icon
- Lock icon on helper text
- Amber text color for warning

**Helper Text:**
```
🔒 Minimum retention required for tax and accounting compliance
```

---

### 4.2 Guest User Data Retention

**Field:**
```
Guest User Data Retention

[ 90 days ▼ ]

Applies to non-registered users only (guest checkouts)
```

**Options:**
- 30 days
- 60 days
- 90 days ← Default
- 180 days

**Rules:**
- No legal minimum
- Admin can choose any option
- Applies only to guest users (not registered accounts)

**Visual:**
- Users icon
- Gray helper text

**Helper Text:**
```
Applies to non-registered users only (guest checkouts)
```

---

### 4.3 Audit Log Retention

**Field:**
```
Audit Log Retention

[ 7 years (recommended) ▼ ]

ℹ️ Audit logs are required for security and legal accountability
```

**Options:**
- 3 years
- 7 years (recommended) ← Default
- 10 years

**Rules:**
- No hard minimum
- 7 years recommended
- Required for GDPR accountability

**Visual:**
- File icon
- Info icon (ℹ️) on helper text
- Blue text color

**Helper Text:**
```
ℹ️ Audit logs are required for security and legal accountability
```

---

## 5. AUTOMATIC ANONYMIZATION RULES

### Section Title
```
Data Anonymization
```

### Subtitle
```
Configure automatic anonymization to protect privacy 
while preserving analytics
```

**Important:** These toggles ARE allowed because they define **how data is processed**, not whether rights exist.

---

### 5.1 Auto-Anonymize Guest Users

**Checkbox:**
```
☑️ Auto-anonymize guest users after retention period

Automatically anonymize guest user data when retention period expires
```

**Behavior:**
- When enabled: Guest data auto-anonymized after retention period
- When disabled: Manual anonymization required

**This toggle is legal** because:
- It's about process automation, not disabling rights
- Anonymization is a valid GDPR compliance method
- Does not prevent data subject rights

---

### 5.2 Remove Personal Identifiers

**Checkbox:**
```
☑️ Remove personal identifiers while keeping aggregated analytics

Replace names, emails, and phone numbers with anonymized IDs 
while preserving order statistics
```

**Behavior:**
- When enabled: PII replaced with anonymous IDs, analytics preserved
- When disabled: Full deletion without analytics preservation

**This toggle is legal** because:
- It's about data processing method
- Both options are GDPR-compliant
- Admin chooses privacy vs. analytics trade-off

---

### Helper Text (Blue Box):
```
Privacy Benefit: Anonymization preserves platform analytics 
(order volumes, revenue trends) while protecting personal data.
```

---

## 6. AUDIT & ACCOUNTABILITY (MANDATORY LOGGING)

### Section Title
```
Audit & Accountability
```

### Subtitle
```
Configure audit logging for compliance and security
```

---

### 6.1 Mandatory Logging (Locked ON)

**Visual:**
```
┌─────────────────────────────────────────────────────────┐
│ 🔒 Mandatory Logging (Always Enabled)                  │
│                                                         │
│ ✅ Log all GDPR actions (access, deletion, portability)│
│ ✅ Log admin data access to personal data              │
│ ✅ Log role & permission changes                       │
│ ✅ Log deletions and anonymization actions             │
│                                                         │
│ Required for GDPR accountability (Article 5.2) -       │
│ These logs cannot be disabled                          │
└─────────────────────────────────────────────────────────┘
```

**Visual Elements:**
- Green background (`bg-green-50`)
- Green border
- Lock icon in header
- Checkmark icons (✅) for each item
- Bold helper text

**Items (All Locked ON):**
1. Log all GDPR actions (access, deletion, portability requests)
2. Log admin data access to personal data
3. Log role & permission changes
4. Log deletions and anonymization actions

**Helper Text:**
```
Required for GDPR accountability (Article 5.2) - 
These logs cannot be disabled
```

**State:**
- ✅ All items always enabled
- No toggles or checkboxes
- Lock icons indicate mandatory status
- Cannot be modified

---

### 6.2 Optional Logging (Configurable)

**Visual:**
```
Optional Logging (Configurable)

☑️ Log vendor account changes
   Track changes to vendor profiles, settings, and status

☐ Log low-risk system configuration changes
   Track non-sensitive platform setting modifications
```

**Items (Checkboxes Allowed):**

**1. Log vendor account changes**
- Default: Enabled
- Tracks vendor profile/setting changes
- Optional but recommended

**2. Log low-risk system configuration changes**
- Default: Disabled
- Tracks non-sensitive platform changes
- Optional (can be noisy)

**Visual:**
- Gray border
- Standard checkboxes (can be toggled)
- Helper text for each option

**These toggles are legal** because:
- They're not GDPR-required logging
- Admin can choose based on organizational needs
- Do not affect data subject rights

---

## 7. ROLE & PERMISSION ENFORCEMENT

### Read-Only Block

**Visual:**
```
┌─────────────────────────────────────────────────────────┐
│ 👥 Who Can Perform GDPR Actions                        │
│                                                         │
│ ✅ Compliance Admin: Full access to GDPR handling and  │
│    compliance settings                                 │
│                                                         │
│ ✅ Super Admin: Full access to all compliance and      │
│    privacy settings                                    │
│                                                         │
│ 🔒 All other roles: View only, no execution rights     │
└─────────────────────────────────────────────────────────┘
```

**Purpose:**
- Makes role requirements explicit
- Read-only (no configuration)
- Educational reference

**Roles:**

**Compliance Admin:**
- ✅ Full access to GDPR handling
- ✅ Full access to compliance settings
- Checkmark icon (green)

**Super Admin:**
- ✅ Full access to all settings
- Checkmark icon (green)

**All Other Roles:**
- 🔒 View only
- ❌ No execution rights
- Lock icon (gray)

**Visual:**
- Gray background
- Users icon in header
- Checkmarks for allowed roles
- Lock for denied roles

---

## 8. CHANGE CONTROL & CONFIRMATION

### Actions Requiring Confirmation

**Any change to:**
1. Retention periods (order/guest/audit)
2. Anonymization rules
3. Request handling mode

**Requires:**
1. Confirmation modal
2. Mandatory reason input (min 10 characters)
3. Logged to Audit Log (before/after values)

---

### Confirmation Modal

**Modal Structure:**

```
┌───────────────────────────────────────────────────┐
│  ⚠️ Change Data Retention Policy                  │
│     Order & Financial Data                        │
│                                                   │
│  Change retention from 7 years to 10 years?      │
│                                                   │
│  ℹ️ This affects financial record retention.     │
│     Legal minimum of 7 years will be enforced.   │
│                                                   │
│  Reason for Change *                             │
│  [Aligning with industry best practices for      │
│   extended financial record retention]           │
│                                                   │
│  45/10 characters minimum                        │
│                                                   │
│  🛡️ This change will be logged to the compliance │
│     audit trail with your admin ID, timestamp,   │
│     and reason.                                  │
│                                                   │
│  [ Cancel ]  [ Confirm Change ]                   │
└───────────────────────────────────────────────────┘
```

**Fields:**

**Title:**
- "Change Data Retention Policy"
- Specific data type shown (Order & Financial Data / Guest User Data / Audit Log)

**Description:**
- "Change retention from X to Y?"
- Shows before and after values

**Impact Info (Blue Box):**
- Info icon
- Specific impact for the data type
- Examples:
  - Order: "Legal minimum of 7 years will be enforced"
  - Guest: "Affects how long guest user data is stored before anonymization"
  - Audit: "Affects security and compliance audit trail retention"

**Reason Input:**
- Required field (red asterisk)
- Minimum 10 characters
- Placeholder: "Explain why this data retention policy is being changed (minimum 10 characters)"
- Character counter: "X/10 characters minimum"

**Audit Warning (Amber Box):**
- Shield icon
- "This change will be logged to the compliance audit trail with your admin ID, timestamp, and reason."

**Buttons:**
- Cancel (gray)
- Confirm Change (blue, disabled if reason < 10 chars)

---

### Validation

**Reason Field:**
- Must not be empty
- Must be at least 10 characters
- Trimmed before validation

**Error Toasts:**
```
❌ Reason required
   Please provide a detailed reason (minimum 10 characters)
```

**Success Toast:**
```
✅ Retention policy updated
   Change logged to audit trail
```

---

### Audit Log Entry

**Structure:**

**Retention Policy Change:**
```json
{
  "action": "COMPLIANCE_POLICY_CHANGED",
  "setting": "orderDataRetention",
  "before": 7,
  "after": 10,
  "admin": "ADM-001",
  "adminRole": "Compliance Admin",
  "timestamp": "2025-01-06T16:00:00Z",
  "reason": "Aligning with industry best practices for extended financial record retention"
}
```

**Required Fields:**
- `action` - "COMPLIANCE_POLICY_CHANGED"
- `setting` - Setting name (orderDataRetention, guestDataRetention, etc.)
- `before` - Previous value
- `after` - New value
- `admin` - Admin user ID
- `adminRole` - Admin role
- `timestamp` - ISO 8601 format
- `reason` - Change reason (string, min 10 chars)

---

## 9. REMOVED ILLEGAL UI ELEMENTS (DO NOT RENDER)

### ❌ Completely Removed:

1. **Toggles to enable/disable GDPR rights**
   - No on/off switches for Right of Access
   - No on/off switches for Right to Erasure
   - No on/off switches for Data Portability

2. **Any UI implying GDPR is optional**
   - No "Enable GDPR compliance" checkbox
   - No "Activate user rights" toggle
   - No optional framing for mandatory rights

3. **"Off" states for legal obligations**
   - No disabled states for GDPR rights
   - No "Not available" options
   - No "Coming soon" for mandatory features

**What Was Removed from Original Design:**

**Old (Illegal) Design:**
```
❌ User Data Export                    [Toggle]
❌ Right to be Forgotten                [Toggle]
❌ Data Portability                     [Toggle]
```

**New (Legal) Design:**
```
✅ Right of Access        🔒 [Always Enabled]
   Configure: Request handling mode

✅ Right to Erasure       🔒 [Always Available]
   Configure: Deletion method

✅ Data Portability       🔒 [Always Enabled]
   Configure: Export format, delivery method
```

---

## 10. LEGAL COMPLIANCE CHECKLIST

### ✅ GDPR Requirements Met

- [ ] Right of Access (Art. 15) - always enabled, 30-day SLA
- [ ] Right to Erasure (Art. 17) - always available, legal retention respected
- [ ] Data Portability (Art. 20) - always enabled, machine-readable format
- [ ] Legal minimum retention enforced (7 years for financial data)
- [ ] Audit logging mandatory for GDPR actions (Art. 5.2)
- [ ] No toggles to disable mandatory rights
- [ ] Configuration defines "how", not "whether"

### ✅ Admin Experience

- [ ] Legal notice banner (non-dismissable)
- [ ] Lock icons on mandatory rights
- [ ] "Always Enabled" / "Always Available" badges
- [ ] Helper text explains legal obligations
- [ ] SLA reminders visible
- [ ] Legal minimums enforced in dropdowns
- [ ] Confirmation modals for changes
- [ ] Audit logging automatic

### ✅ Permission Controls

- [ ] Compliance Admin can modify
- [ ] Super Admin can modify
- [ ] Other roles view-only
- [ ] Role enforcement documented
- [ ] Read-only banner for non-authorized users

### ✅ Audit Trail

- [ ] All retention changes logged
- [ ] All anonymization changes logged
- [ ] Reason required (min 10 chars)
- [ ] Before/after values captured
- [ ] Admin ID and timestamp recorded

---

## 11. SUCCESS CRITERIA

**Compliance & Data Privacy is successful if:**

✅ GDPR rights cannot be disabled  
✅ Legal minimums enforced automatically  
✅ Lock icons indicate mandatory status  
✅ "Always Enabled" badges present  
✅ Helper text explains legal obligations  
✅ Changes require confirmation + reason  
✅ All changes logged to audit trail  
✅ Only Compliance Admin / Super Admin can modify  
✅ No toggles for mandatory rights  

**Compliance & Data Privacy has failed if:**

❌ GDPR rights can be toggled off  
❌ Legal minimums can be violated  
❌ Rights appear optional  
❌ No lock indicators  
❌ Changes happen without confirmation  
❌ Audit log not populated  
❌ Non-authorized roles can modify  
❌ Legal obligations not explained  

---

## 12. VISUAL HIERARCHY

### Page Structure (Top to Bottom)

1. **Page Header**
   - Title: "Compliance & Data Privacy"
   - Subtitle: Configuration focus

2. **Mandatory Compliance Notice** (Blue)
   - Shield icon
   - Legal framing
   - Bold critical statements
   - Non-dismissable

3. **Read-Only Banner** (Amber, if not Compliance Admin)
   - Lock icon
   - Permission notice

4. **Role & Permission Enforcement** (Gray)
   - Who can perform GDPR actions
   - Read-only reference

5. **GDPR User Rights Handling**
   - Right of Access card
   - Right to Erasure card
   - Data Portability card
   - All with lock icons and "Always" badges

6. **Data Retention Policies**
   - Order & Financial (7 year minimum)
   - Guest User Data
   - Audit Log Retention

7. **Data Anonymization**
   - Auto-anonymize toggle
   - Preserve analytics toggle
   - Privacy benefit explanation

8. **Audit & Accountability**
   - Mandatory logging (green, locked)
   - Optional logging (gray, configurable)

**Spacing:**
- 24px between sections (`space-y-6`)
- Consistent padding in cards (`p-4` or `p-5`)
- Clear visual separation

---

## 13. COPY TONE & LANGUAGE

### Legal, Declarative Tone

**Use:**
- "Mandatory by law"
- "Cannot be disabled"
- "Required for GDPR accountability"
- "Legal minimum enforced"
- "Always enabled"
- "Legal obligation"

**Avoid:**
- "Enable GDPR"
- "Activate user rights"
- "Turn on compliance"
- "Optional feature"
- "Coming soon"

### Example Good Copy:

```
✅ "Users have the legal right to request a copy of their 
   personal data. Tavlo must respond within 30 days as 
   required by GDPR."

✅ "Minimum retention required for tax and accounting 
   compliance"

✅ "Required for GDPR accountability (Article 5.2) - 
   These logs cannot be disabled"
```

### Example Bad Copy:

```
❌ "Allow users to export their data"
❌ "Enable right to be forgotten feature"
❌ "Turn on GDPR compliance"
❌ "Optional audit logging"
```

---

## 14. GDPR ARTICLE REFERENCES

### Article Mapping

| GDPR Article | Right | Implementation |
|--------------|-------|----------------|
| **Art. 15** | Right of Access | Always enabled, 30-day SLA, automatic or manual handling |
| **Art. 17** | Right to Erasure | Always available, deletion or anonymization, legal retention respected |
| **Art. 20** | Data Portability | Always enabled, JSON/CSV export, secure delivery |
| **Art. 5.2** | Accountability | Mandatory audit logging, cannot be disabled |

**References in UI:**
- Show article numbers (e.g., "GDPR Article 15")
- Helps legal/compliance teams
- Educational for admins

---

## 15. PRODUCTION DEPLOYMENT NOTES

### Pre-Launch Validation

**Legal Review:**
- [ ] Legal team reviewed all copy
- [ ] GDPR requirements verified
- [ ] Legal minimums confirmed (7 years for financial data)
- [ ] Article references accurate

**Technical Validation:**
- [ ] Cannot disable GDPR rights in code
- [ ] Legal minimums enforced server-side
- [ ] Audit logging cannot be bypassed
- [ ] All changes require confirmation

**Role Testing:**
- [ ] Compliance Admin can modify
- [ ] Super Admin can modify
- [ ] Other roles are read-only
- [ ] Permission checks work

**Audit Trail Testing:**
- [ ] All changes logged
- [ ] Before/after values captured
- [ ] Reason required and stored
- [ ] Timestamps accurate

---

**END OF COMPLIANCE & DATA PRIVACY SPECIFICATION**
