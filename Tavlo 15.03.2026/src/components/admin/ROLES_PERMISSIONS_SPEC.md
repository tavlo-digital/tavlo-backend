# Enhanced Roles & Permissions - Full Lifecycle Management Specification

## Overview

The **Enhanced Roles & Permissions** system provides comprehensive role-based access control (RBAC) with full lifecycle management, explicit permission assignment, and secure admin credential handling.

**Core Philosophy:**
- Super Admin oversees everything
- Principle of least privilege by default
- Permissions are explicit, not implied
- All actions logged in Audit Log
- Security-first approach

---

## Core Principles (NON-NEGOTIABLE)

### 1. Super Admin Authority
**Only Super Admin can:**
- ✅ Create roles
- ✅ Edit roles
- ✅ Delete roles
- ✅ Create admin users
- ✅ Reset admin passwords
- ✅ Assign roles to admin users

**All other roles:**
- ❌ Cannot create/edit/delete roles
- ❌ Cannot create admin users
- ❌ Cannot reset passwords
- ✅ Can view their own permissions (read-only)

### 2. Principle of Least Privilege
- No permissions granted by default
- Each role sees only what is necessary
- Permissions must be explicitly selected
- Opt-in permission model

### 3. Audit Everything
**Logged actions:**
- Role creation
- Role deletion
- Role permission changes
- Admin user creation
- Role reassignment
- Password resets

**Audit entries include:**
- Admin user ID
- Timestamp
- Action type
- Target role or user
- Permission diff (before/after)

### 4. Security-First
- Super Admin cannot view existing passwords
- Temporary passwords shown only once
- Password reset forces change on first login
- System roles (Super Admin) cannot be edited/deleted

---

## A. ROLES & PERMISSIONS — LEFT PANEL (ROLES LIST)

### A1. Create Custom Role Button

**Visual:**
```
┌─────────────────────────────────────┐
│  +  Create Custom Role              │  (Dashed border)
└─────────────────────────────────────┘
```

**Behavior:**

**For Super Admin:**
- ✅ Enabled
- ✅ Purple hover effect
- ✅ Click → Opens Create Role modal

**For Non-Super Admin:**
- ❌ Disabled (gray, cursor-not-allowed)
- ❌ Tooltip: "Only Super Admin can create roles"
- Below button text: "🔒 Only Super Admin can create roles"

**Console Log:**
```json
{
  "action": "CREATE_ROLE_MODAL_OPENED",
  "admin": "Current Admin User",
  "timestamp": "2025-01-06T16:00:00Z"
}
```

---

### A2. Existing Roles — Edit & Delete Controls

**Role Card Structure:**
```
┌───────────────────────────────────────┐
│ Finance Admin              [3]        │ (Badge: user count)
│ Billing, invoices, subscriptions      │
│                                       │
│ ─────────────────────────────────────│
│   [Edit]       [Delete]               │ (Only for Super Admin)
└───────────────────────────────────────┘
```

**For Each Role:**

**Super Admin Role (System Role):**
```
┌───────────────────────────────────────┐
│ Super Admin 🔒           [2]          │
│ Full platform access - system role    │
│                                       │
│ ─────────────────────────────────────│
│ 🔒 System role (cannot edit/delete)  │
└───────────────────────────────────────┘
```

- **Lock icon** next to name
- **No Edit/Delete buttons**
- **Gray text:** "System role (cannot edit/delete)"
- **Tooltip on role card:** "System role - protected from changes"

**Custom Roles (For Super Admin):**
- ✅ **Edit icon button** (purple hover)
- ✅ **Delete icon button** (red hover)
- Both buttons in footer of role card

**Custom Roles (For Non-Super Admin):**
- ❌ No Edit/Delete buttons shown
- Read-only view

---

### A3. Delete Behavior

**Delete Click Flow:**
```
1. Super Admin clicks "Delete" on Finance Admin role
2. System checks: role.userCount > 0?
3a. If YES → Error toast:
   "Cannot delete role: 3 admin users are assigned to this role.
    Reassign or delete users first."
3b. If NO → Show Delete Confirmation Modal
```

**Delete Confirmation Modal:**
```
┌─────────────────────────────────────────┐
│  ⚠️  Delete Role                        │
│      This action cannot be undone        │
│                                         │
│  Are you sure you want to delete the    │
│  Finance Admin role?                    │
│                                         │
│  [ Cancel ]  [ Delete Role ]            │
└─────────────────────────────────────────┘
```

**On Confirmation:**
```json
// Audit log entry
{
  "action": "ROLE_DELETED",
  "roleId": "finance_admin",
  "roleName": "Finance Admin",
  "admin": "ADM-001 (Super Admin)",
  "timestamp": "2025-01-06T16:05:00Z",
  "userCountAtDeletion": 0
}
```

**Toast:**
```
✅ Role deleted
   Action logged to audit trail
```

---

## B. CREATE / EDIT ROLE MODAL

### B1. Modal Header

**Create Mode:**
```
Create Custom Role                     [X]
```

**Edit Mode:**
```
Edit Role: Finance Admin               [X]
```

---

### B2. Role Information Section

**Inputs:**

**1. Role Name (Required)**
```
Role Name *
[e.g., Operations Manager              ]
```

**Validation:**
- Required field
- Must be unique
- Error if duplicate: "Role name already exists"

**2. Role Description (Optional)**
```
Description (Internal)
[Describe the purpose and scope of this role
 (2 rows, textarea)                    ]
```

**Helper text:**
- "(Internal)" - not shown to assigned users
- Gray placeholder text

---

### B3. Permission Assignment (Critical)

**Section Header:**
```
PERMISSION ASSIGNMENT

ℹ️  Permissions are opt-in. No permission is selected by default.
    Select only what is necessary for this role (Principle of Least Privilege).
```

**Blue info banner** (always visible)

---

### B4. Permission Matrix (Editable)

**Structure:**
```
┌───────────────────────────────────────────────────────────────┐
│ Module               │ View  │ Edit  │ Special Permissions    │
├───────────────────────────────────────────────────────────────┤
│ Dashboard            │  ☐    │  ☐    │ -                      │
│ Vendors              │  ☐    │  ☐    │ ☐suspend  ☐approve     │
│ Customers            │  ☐    │  ☐    │ ☐gdpr-export  ☐gdpr-del│
│ Finance & Billing    │  ☐    │  ☐    │ ☐export  ☐vat-report   │
│ Subscriptions        │  ☐    │  ☐    │ ☐create-plan           │
│ Reviews & Moderation │  ☐    │  ☐    │ ☐moderate  ☐delete     │
│ System Settings      │  ☐    │  ☐    │ -                      │
│ Audit Log            │  ☐    │  ☐    │ ☐export                │
│ Admin User Management│  ☐    │  ☐    │ ☐create ☐reset-pw ☐assign│
└───────────────────────────────────────────────────────────────┘
```

**9 Modules:**
1. **Dashboard** - View/edit analytics
2. **Vendors** - View/edit vendors, special: suspend, approve
3. **Customers** - View/edit customers, special: GDPR export/delete
4. **Finance & Billing** - View/edit invoices, special: export, VAT report
5. **Subscriptions** - View/edit plans, special: create plan
6. **Reviews & Moderation** - View/edit reviews, special: moderate, delete
7. **System Settings** - View/edit platform config
8. **Audit Log** - View logs, special: export
9. **Admin User Management** - View/edit admins, special: create, reset password, assign role

**Permission Types:**

**View:**
- Basic read access
- Can see module in sidebar
- Can view lists and details

**Edit:**
- Modify existing data
- Create new entries (if applicable)
- Update settings

**Special:**
- Module-specific actions
- Example: Vendors → Suspend
- Example: Customers → GDPR Export
- Example: Admin Users → Reset Password

---

### B5. Permission Rules

**Opt-In Model:**
- ✅ All checkboxes unchecked by default
- ✅ Admin must explicitly select each permission
- ✅ No implied permissions

**Restrictions:**
- ❌ Cannot replicate full Super Admin permissions
- ❌ Audit Log "edit" permission always disabled (logs are immutable)
- ⚠️ Creating role with all permissions shows warning:
  ```
  ⚠️ This role has full platform access.
     Consider using Super Admin role instead.
  ```

**Visual States:**
- **Unchecked (default):** Empty checkbox
- **Checked:** Purple checkbox with checkmark
- **Disabled:** Gray checkbox (only for Audit Log → Edit)

---

### B6. Save Rules

**Validation:**
```
1. Role name not empty
2. Role name unique
3. At least one permission selected (warning if none)
```

**Warning if no permissions:**
```
⚠️ No permissions selected
   This role will have no access to any modules.
   Continue anyway?

   [ Go Back ]  [ Create Role ]
```

**On Save (Create):**
```json
{
  "action": "ROLE_CREATED",
  "roleId": "role_1704553200",
  "roleName": "Operations Manager",
  "admin": "ADM-001 (Super Admin)",
  "timestamp": "2025-01-06T16:10:00Z",
  "permissions": { ... }
}
```

**On Save (Edit):**
```json
{
  "action": "ROLE_EDITED",
  "roleId": "finance_admin",
  "roleName": "Finance Admin",
  "admin": "ADM-001 (Super Admin)",
  "timestamp": "2025-01-06T16:10:00Z",
  "permissionsDiff": {
    "before": {
      "finance": { "view": true, "edit": false }
    },
    "after": {
      "finance": { "view": true, "edit": true }
    }
  }
}
```

**Toast:**
```
✅ Role created / Role updated
   Changes logged to audit trail
```

**Immediate Effect:**
- Existing admin users with this role inherit updated permissions
- No cache delay
- Active sessions reflect changes immediately

---

## C. ADMIN USER MANAGEMENT (EXTENSION)

### C1. Admin Users Table

**Location:** Right panel, below permission matrix

**Header:**
```
Admin Users in this Role              [+ Create Admin User]
                                      (Super Admin only)
```

**Table Columns:**
1. **Username** (bold)
2. **Email**
3. **Role** (badge)
4. **Last Login** (date/time or "Never")
5. **Status** (Active/Disabled badge)
6. **Actions** (Super Admin only)

**Table Row Example:**
```
┌──────────────────────────────────────────────────────────────────┐
│ Username      │ Email         │ Role    │ Last Login │ Status │ Actions│
├──────────────────────────────────────────────────────────────────┤
│ finance.maria │ maria@tavlo   │[Finance]│ 2025-01-05 │ Active │ Reset  │
│               │       .com    │ Admin   │    16:20   │        │Password│
└──────────────────────────────────────────────────────────────────┘
```

**Filtering:**
- When role selected in left panel → Show only users with that role
- "Super Admin" selected → Show Super Admin users only

**For Non-Super Admin:**
- ❌ No "Create Admin User" button
- ❌ No "Actions" column
- ✅ Can view table (read-only)

---

### C2. Create Admin User (Super Admin Only)

**Modal Fields:**

**1. Username** (required, unique)
```
Username *
[operations.john                       ]
```

**2. Email** (required, unique)
```
Email *
[john@tavlo.com                        ]
```

**3. Assigned Role** (required, dropdown)
```
Assigned Role *
[Select role ▼                         ]
Options:
- Super Admin
- Finance Admin
- Support Admin
- Operations Manager (custom roles)
```

**4. Status**
```
Status
[Active ▼                              ]
Options:
- Active (default)
- Disabled
```

**On Create:**
```json
{
  "action": "ADMIN_USER_CREATED",
  "userId": "user_004",
  "username": "operations.john",
  "email": "john@tavlo.com",
  "roleId": "operations_manager",
  "admin": "ADM-001 (Super Admin)",
  "timestamp": "2025-01-06T16:15:00Z",
  "tempPasswordGenerated": true
}
```

**Temporary Password Generated:**
- System generates secure temp password
- Shown once in modal (same flow as password reset)
- User must change on first login

---

### C3. Password Handling (Security-First)

**Password Reset Flow:**

**1. Super Admin clicks "Reset Password" on user row**

**2. Password Reset Modal opens:**
```
┌─────────────────────────────────────────┐
│  🔑  Reset Password                     │
│       finance.maria                      │
│                                         │
│  ⚠️  Security Notice                    │
│  This password will only be shown once. │
│  Provide it securely to the admin user. │
│  They must change it on first login.    │
│                                         │
│  Temporary Password                     │
│  [Tavlo3f8x9a@2025      ] [📋 Copy]    │
│                                         │
│  [ Cancel ]  [ Confirm Reset ]          │
└─────────────────────────────────────────┘
```

**3. Copy Password:**
- Click 📋 Copy button
- Copies to clipboard
- Button changes to ✅ Copied (2 seconds)
- Toast: "Password copied to clipboard"

**4. Confirm Reset:**
```json
{
  "action": "PASSWORD_RESET",
  "userId": "user_002",
  "username": "finance.maria",
  "admin": "ADM-001 (Super Admin)",
  "timestamp": "2025-01-06T16:20:00Z",
  "tempPasswordProvided": true,
  "forceChangeOnLogin": true
}
```

**Toast:**
```
✅ Password reset complete
   User must change password on next login
```

**Password Requirements:**
- Minimum 12 characters
- Must include: uppercase, lowercase, number, special char
- Format: `Tavlo[random]@2025`

**Security Rules:**
- ❌ Super Admin cannot view existing passwords
- ❌ No "show password" option
- ✅ Temp password shown only once
- ✅ Must be provided securely (not via email/slack)
- ✅ Force change on first login

---

## D. RIGHT PANEL — PERMISSIONS VISIBILITY (READ-ONLY)

**Header:**
```
Permissions for Finance Admin          [Edit Permissions]
                                       (Super Admin only)
```

**Permission Matrix (Read-Only):**
```
┌───────────────────────────────────────────────────────────────┐
│ Module               │ View  │ Edit  │ Special Permissions    │
├───────────────────────────────────────────────────────────────┤
│ Dashboard            │  ✅   │  ❌   │ -                      │
│ Vendors              │  ✅   │  ❌   │ -                      │
│ Finance & Billing    │  ✅   │  ✅   │ export, vat-report     │
│ Audit Log            │  ✅   │  ❌   │ export                 │
└───────────────────────────────────────────────────────────────┘
```

**Visual Indicators:**
- ✅ Green checkmark (CheckCircle icon) - Permission granted
- ❌ Gray X (XCircle icon) - Permission not granted
- **Special permissions:** Blue badges (if granted), "-" if none

**Behavior:**
- Updates dynamically when selecting role from left panel
- Non-editable (read-only table)
- Click "Edit Permissions" (Super Admin only) → Opens Edit Role modal

**Purpose:**
- Clarity for all admins
- Quick reference for what role can do
- Used for role comparison

---

## E. UNSAVED CHANGES & SAFETY

### E1. Unsaved Changes Banner

**Trigger:** When admin edits permissions in modal but hasn't saved

**Banner (Top of page):**
```
┌─────────────────────────────────────────────────────────┐
│ ⚠️ You have unsaved changes    [Discard] [Save Changes] │
└─────────────────────────────────────────────────────────┘
```

**Behavior:**
- Amber background
- Sticky position (always visible)
- Persists until Save or Discard
- Confirmation modal if navigating away:
  ```
  ⚠️ You have unsaved changes.
     Do you want to save before leaving?

     [ Discard Changes ]  [ Save & Leave ]  [ Stay ]
  ```

---

### E2. Reset to Default (Super Admin Only)

**Location:** Bottom of Create/Edit Role modal

**Button:**
```
[ Reset to Default ]
```

**Behavior:**
- Unchecks all permissions
- Resets to blank slate
- Confirmation:
  ```
  Reset all permissions to default (none selected)?
  [ Cancel ]  [ Reset ]
  ```

**Only available to Super Admin**

---

## F. AUDIT & COMPLIANCE (MANDATORY)

### F1. Logged Actions

**1. Role Creation**
```json
{
  "action": "ROLE_CREATED",
  "roleId": "operations_manager",
  "roleName": "Operations Manager",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:00:00Z",
  "permissions": { "dashboard": { "view": true, "edit": false }, ... }
}
```

**2. Role Deletion**
```json
{
  "action": "ROLE_DELETED",
  "roleId": "old_role",
  "roleName": "Deprecated Role",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:05:00Z",
  "userCountAtDeletion": 0
}
```

**3. Role Permission Changes**
```json
{
  "action": "ROLE_PERMISSIONS_UPDATED",
  "roleId": "finance_admin",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:10:00Z",
  "changes": {
    "finance": {
      "before": { "view": true, "edit": false },
      "after": { "view": true, "edit": true }
    }
  }
}
```

**4. Admin User Creation**
```json
{
  "action": "ADMIN_USER_CREATED",
  "userId": "user_005",
  "username": "new.admin",
  "email": "admin@tavlo.com",
  "roleId": "support_admin",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:15:00Z"
}
```

**5. Role Reassignment**
```json
{
  "action": "USER_ROLE_CHANGED",
  "userId": "user_003",
  "username": "john.doe",
  "fromRoleId": "support_admin",
  "toRoleId": "finance_admin",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:20:00Z"
}
```

**6. Password Resets**
```json
{
  "action": "PASSWORD_RESET",
  "userId": "user_002",
  "username": "finance.maria",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:25:00Z",
  "tempPasswordProvided": true,
  "forceChangeOnLogin": true
}
```

---

### F2. Audit Entry Requirements

**Every audit entry includes:**
- ✅ `action` - Action type (enum)
- ✅ `admin` - Admin user ID who performed action
- ✅ `timestamp` - ISO 8601 format
- ✅ `targetId` - Role ID or User ID affected
- ✅ `ipAddress` - IP of admin session
- ✅ `sessionId` - Session identifier
- ✅ `details` - Action-specific metadata

**Audit logs are:**
- ❌ Read-only (even for Super Admin)
- ✅ Exportable (Super Admin + Compliance roles)
- ✅ Immutable (cannot be deleted)
- ✅ Stored permanently

**Access:**
- Super Admin: View all + Export
- Compliance Admin: View all + Export
- Other roles: View logs related to their actions only

---

## G. CONSTRAINTS (DO NOT VIOLATE)

### ❌ Do NOT:

1. **Allow role creation by non-Super Admin**
   - UI enforced (button disabled)
   - API enforced (403 error)

2. **Allow deleting Super Admin role**
   - System role protection
   - Cannot be deleted or edited

3. **Allow viewing admin passwords**
   - No "show password" feature
   - Only reset with temp password

4. **Allow silent permission escalation**
   - All permission changes logged
   - Confirmation modal for role edits

5. **Allow editing Audit Log permissions beyond read/export**
   - Audit logs are immutable
   - "Edit" permission always disabled

6. **Allow role deletion with assigned users**
   - Block deletion if userCount > 0
   - Must reassign or delete users first

---

### ✅ DO:

1. **Enforce Super Admin requirements**
   - Check user role before showing actions
   - Show tooltips explaining restrictions

2. **Log everything**
   - Role CRUD operations
   - Permission changes
   - User management
   - Password resets

3. **Use principle of least privilege**
   - No default permissions
   - Opt-in permission model
   - Only grant what's necessary

4. **Secure password handling**
   - Generate strong temp passwords
   - Show only once
   - Force change on first login

5. **Validate all inputs**
   - Unique role names
   - Valid email addresses
   - At least one permission (warn if none)

---

## H. PERMISSION INHERITANCE & HIERARCHY

**No Inheritance Between Roles:**
- Unlike Subscription plans, roles do NOT inherit permissions
- Each role is independent
- Finance Admin doesn't automatically include Support Admin permissions

**Why:**
- Admin access is security-critical
- Explicit is safer than implicit
- Prevents unintended permission leaks

**Visual Clarity:**
- Each role's permissions clearly labeled
- No "inherited from" badges (unlike subscription features)
- Admin sees exactly what each role can do

---

## I. TYPICAL ROLE CONFIGURATIONS

### 1. Super Admin (System Role)
```
✅ All modules: View + Edit
✅ All special permissions
✅ Admin user management
✅ Role management
```

**User Count:** 2 (keep minimal)

---

### 2. Finance Admin
```
✅ Dashboard: View only
✅ Vendors: View only
✅ Finance & Billing: View + Edit + Export + VAT Report
✅ Subscriptions: View only
✅ Audit Log: View + Export
❌ No admin user management
```

**Purpose:** Financial operations, VAT reporting, invoice management

---

### 3. Support Admin
```
✅ Dashboard: View only
✅ Vendors: View only
✅ Customers: View only (no GDPR actions)
✅ Finance: View only
✅ Reviews: View only
✅ Audit Log: View only
❌ No system settings access
❌ No admin user management
```

**Purpose:** Customer support, vendor assistance, general troubleshooting

---

### 4. Compliance Admin
```
✅ Dashboard: View only
✅ Vendors: View + Suspend
✅ Customers: View + GDPR Export + GDPR Delete
✅ System Settings: View + Edit
✅ Audit Log: View + Export
❌ No financial operations
```

**Purpose:** Data protection, GDPR compliance, vendor suspension for violations

---

### 5. Content Moderator
```
✅ Reviews & Moderation: View + Moderate + Delete
✅ Audit Log: View only
❌ No access to other modules
```

**Purpose:** Review moderation only (narrow scope)

---

## J. PRODUCTION CHECKLIST

### ✅ Roles List
- [ ] "Create Custom Role" button (Super Admin only)
- [ ] Edit/Delete icons (Super Admin only, not for system roles)
- [ ] Lock icon for Super Admin role
- [ ] User count badges
- [ ] Tooltips for disabled actions

### ✅ Create/Edit Role Modal
- [ ] Role name + description fields
- [ ] Permission matrix (9 modules)
- [ ] View/Edit/Special checkboxes
- [ ] Blue info banner (principle of least privilege)
- [ ] Save validation (unique name, required fields)
- [ ] Audit logging on save

### ✅ Delete Role
- [ ] Confirmation modal
- [ ] Check for assigned users
- [ ] Error if users assigned
- [ ] Success toast + audit log

### ✅ Permission Matrix (Read-Only)
- [ ] Displays selected role's permissions
- [ ] Green checkmarks / Gray X icons
- [ ] Special permissions as blue badges
- [ ] Updates when role selected

### ✅ Admin Users Table
- [ ] Filtered by selected role
- [ ] Create Admin User button (Super Admin)
- [ ] Reset Password action (Super Admin)
- [ ] Status badges (Active/Disabled)

### ✅ Password Reset
- [ ] Generate secure temp password
- [ ] Show only once
- [ ] Copy to clipboard button
- [ ] Force change on first login
- [ ] Audit log entry

### ✅ Unsaved Changes
- [ ] Banner appears when editing
- [ ] Discard/Save buttons
- [ ] Confirmation if navigating away

### ✅ Audit Logging
- [ ] Role CRUD operations
- [ ] Permission changes (before/after)
- [ ] Admin user creation
- [ ] Password resets
- [ ] All logs immutable

---

## K. SUCCESS CRITERIA

**Enhanced Roles & Permissions is successful if:**

✅ Only Super Admin can create/edit/delete roles  
✅ Permissions are explicit (opt-in model)  
✅ All actions logged to audit trail  
✅ Passwords secure (temp, one-time, forced change)  
✅ System roles (Super Admin) protected  
✅ Cannot delete role with assigned users  
✅ Non-Super Admins cannot escalate privileges  

**Enhanced Roles & Permissions has failed if:**

❌ Non-Super Admin can create roles  
❌ Permissions granted by default  
❌ Actions not logged  
❌ Passwords viewable by admins  
❌ Super Admin role editable/deletable  
❌ Silent permission changes  
❌ Role deleted with active users  

---

**END OF ENHANCED ROLES & PERMISSIONS SPECIFICATION**
