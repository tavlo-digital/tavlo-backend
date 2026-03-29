# Legal Approval Workflow - Visual Guide

## Complete User Journey

### **Starting State: No Pending Changes**

```
┌─────────────────────────────────────────────────────────┐
│ 🔒 Legal Business Information                           │
│ For invoices, tax, legal ID                             │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Business Registration Number    VAT Number             │
│ ┌─────────────┐                ┌─────────────┐         │
│ │ FN 123456a  │                │ ATU12345678 │         │ ← Fields editable
│ └─────────────┘                └─────────────┘         │
│                                                         │
│ Company Type                                            │
│ ┌─────────────┐                                         │
│ │ GmbH ▼      │                                         │
│ └─────────────┘                                         │
│                                                         │
│ 📍 Legal Address                                        │
│ ┌───────────────────────────────────────────────────┐   │
│ │ Kärntner Straße 1, 1010 Wien, Austria           │   │
│ └───────────────────────────────────────────────────┘   │
│                                                         │
│ ⚠️  Note: Any changes require admin approval           │
│    May affect invoices and tax compliance              │
└─────────────────────────────────────────────────────────┘
```

---

### **Step 1: Vendor Edits Field**

```
Vendor changes VAT Number from ATU12345678 to ATU99999999
                        ↓
┌─────────────────────────────────────────────────────────┐
│ VAT Number                                              │
│ ┌─────────────┐                                         │
│ │ ATU99999999 │  ← Changed (not saved yet)              │
│ └─────────────┘                                         │
└─────────────────────────────────────────────────────────┘

Vendor clicks [Save Changes] button
                        ↓
        Change detected in sensitive field
                        ↓
            Modal appears (Step 2)
```

---

### **Step 2: Confirmation Modal Appears**

```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃ ⚠️  Confirm Legal Information Change                  ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃                                                        ┃
┃ You are about to change information used for          ┃
┃ invoices and tax compliance. These changes require    ┃
┃ Tavlo admin review and may temporarily affect         ┃
┃ invoicing.                                            ┃
┃                                                        ┃
┃ Changes to be reviewed:                                ┃
┃                                                        ┃
┃ ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓   ┃
┃ ┃ VAT Number                                      ┃   ┃
┃ ┃                                                 ┃   ┃
┃ ┃ Current Value       New Value                  ┃   ┃
┃ ┃ ┌──────────────┐   ┌──────────────────────┐    ┃   ┃
┃ ┃ │ ATU12345678  │   │ ATU99999999          │    ┃   ┃
┃ ┃ │              │   │ (highlighted amber)  │    ┃   ┃
┃ ┃ └──────────────┘   └──────────────────────┘    ┃   ┃
┃ ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛   ┃
┃                                                        ┃
┃ ℹ️  Important: Your current approved values will      ┃
┃    remain active for invoices and customer-facing     ┃
┃    pages until these changes are approved.            ┃
┃                                                        ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃                    [Cancel]  [Submit for Approval]    ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
                              ↓
                 Vendor clicks "Submit for Approval"
                              ↓
                  Modal closes, status = 'pending'
```

---

### **Step 3: Pending State (Immediately After Submission)**

```
┌─────────────────────────────────────────────────────────┐
│ 🔒 Legal Business Information                           │
│ For invoices, tax, legal ID                             │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ⚠️  Changes pending Tavlo admin approval.               │ ← YELLOW BANNER
│    Your submitted changes are under review. Current    │
│    approved values remain active for invoices...       │
│                                                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Business Registration Number  [Pending approval]       │ ← BADGE
│ ┌─────────────┐                                         │
│ │ FN 123456a  │  (grayed out, disabled)                │ ← LOCKED
│ └─────────────┘                                         │
│                                                         │
│ VAT Number  [Pending approval]                         │
│ ┌─────────────┐                                         │
│ │ ATU99999999 │  (grayed out, disabled)                │ ← LOCKED
│ └─────────────┘                                         │
│                                                         │
│ Company Type  [Pending approval]                       │
│ ┌─────────────┐                                         │
│ │ GmbH ▼      │  (grayed out, disabled)                │ ← LOCKED
│ └─────────────┘                                         │
│                                                         │
│ 📍 Legal Address  [Pending approval]                    │
│ ┌───────────────────────────────────────────────────┐   │
│ │ Kärntner Straße 1... (grayed out, disabled)      │   │ ← LOCKED
│ └───────────────────────────────────────────────────┘   │
│                                                         │
│ ⚠️  Note: Any changes require admin approval           │
└─────────────────────────────────────────────────────────┘

Key visual indicators:
✓ Yellow banner at top
✓ "Pending approval" badges on all affected fields
✓ Fields grayed out (bg-gray-100)
✓ Fields disabled (cannot type)
✓ Cursor shows "not-allowed" on hover
```

---

### **Step 3a: Vendor Tries to Save Again (Blocked)**

```
Vendor tries to change another field and clicks "Save Changes"
                              ↓
┌─────────────────────────────────────────────────────────┐
│ 🔴 Cannot save while changes are pending admin approval │ ← ERROR TOAST
└─────────────────────────────────────────────────────────┘

No modal appears. Save is blocked.
Vendor must wait for admin to review existing changes.
```

---

### **Step 4: Admin Reviews (Backend)**

```
                     ADMIN DASHBOARD
┌───────────────────────────────────────────────────────┐
│ Pending Legal Information Changes                    │
├───────────────────────────────────────────────────────┤
│                                                       │
│ Restaurant: La Bella Vista                           │
│ Submitted: 2026-01-04 10:30 AM                       │
│                                                       │
│ Changes requested:                                    │
│ • VAT Number: ATU12345678 → ATU99999999              │
│                                                       │
│              [Reject]        [Approve]               │
└───────────────────────────────────────────────────────┘
                              ↓
              Admin clicks "Approve" or "Reject"
                              ↓
            Backend updates status in database
                              ↓
       Vendor dashboard reflects new state (Step 5)
```

---

### **Step 5a: APPROVED State**

```
┌─────────────────────────────────────────────────────────┐
│ 🔒 Legal Business Information                           │
│ For invoices, tax, legal ID                             │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ✓  Changes approved!                                    │ ← GREEN BANNER
│    Your legal information has been updated             │
│    successfully.                                       │
│                                                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Business Registration Number                           │ ← No badge
│ ┌─────────────┐                                         │
│ │ FN 123456a  │                                         │ ← UNLOCKED
│ └─────────────┘                                         │
│                                                         │
│ VAT Number                                             │
│ ┌─────────────┐                                         │
│ │ ATU99999999 │  ← NEW VALUE NOW ACTIVE                │ ← UNLOCKED
│ └─────────────┘                                         │
│                                                         │
│ Company Type                                            │
│ ┌─────────────┐                                         │
│ │ GmbH ▼      │                                         │ ← UNLOCKED
│ └─────────────┘                                         │
│                                                         │
│ 📍 Legal Address                                        │
│ ┌───────────────────────────────────────────────────┐   │
│ │ Kärntner Straße 1, 1010 Wien, Austria           │   │ ← UNLOCKED
│ └───────────────────────────────────────────────────┘   │
│                                                         │
│ ⚠️  Note: Any changes require admin approval           │
└─────────────────────────────────────────────────────────┘

After a few seconds, green banner disappears automatically.
Status returns to 'none'.
Vendor can now edit and make new changes.
New VAT number is now used in invoices, receipts, and customer pages.
```

---

### **Step 5b: REJECTED State**

```
┌─────────────────────────────────────────────────────────┐
│ 🔒 Legal Business Information                           │
│ For invoices, tax, legal ID                             │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ✕  Changes were not approved.                          │ ← RED BANNER
│    Your submitted changes have been rejected.          │
│    Original values remain active. Please contact       │
│    support for details.                                │
│                                                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Business Registration Number                           │ ← No badge
│ ┌─────────────┐                                         │
│ │ FN 123456a  │                                         │ ← UNLOCKED
│ └─────────────┘                                         │
│                                                         │
│ VAT Number                                             │
│ ┌─────────────┐                                         │
│ │ ATU12345678 │  ← REVERTED TO ORIGINAL VALUE          │ ← UNLOCKED
│ └─────────────┘                                         │
│                                                         │
│ Company Type                                            │
│ ┌─────────────┐                                         │
│ │ GmbH ▼      │                                         │ ← UNLOCKED
│ └─────────────┘                                         │
│                                                         │
│ 📍 Legal Address                                        │
│ ┌───────────────────────────────────────────────────┐   │
│ │ Kärntner Straße 1, 1010 Wien, Austria           │   │ ← UNLOCKED
│ └───────────────────────────────────────────────────┘   │
│                                                         │
│ ⚠️  Note: Any changes require admin approval           │
└─────────────────────────────────────────────────────────┘

Vendor's submitted value (ATU99999999) is discarded.
Original approved value (ATU12345678) is restored in the input.
Fields become editable again.
Vendor can correct the issue and resubmit.
```

---

## Multiple Field Changes

### **Modal with Multiple Changes**

```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃ ⚠️  Confirm Legal Information Change                  ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃                                                        ┃
┃ You are about to change information used for...       ┃
┃                                                        ┃
┃ Changes to be reviewed:                                ┃
┃                                                        ┃
┃ ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓   ┃
┃ ┃ VAT Number                                      ┃   ┃
┃ ┃ Current: ATU12345678  →  New: ATU99999999      ┃   ┃
┃ ┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫   ┃
┃ ┃ Business Registration Number                   ┃   ┃
┃ ┃ Current: FN 123456a   →  New: FN 789xyz        ┃   ┃
┃ ┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫   ┃
┃ ┃ Company Type                                    ┃   ┃
┃ ┃ Current: GmbH         →  New: AG               ┃   ┃
┃ ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛   ┃
┃                                                        ┃
┃ ℹ️  Important: Current values remain active...        ┃
┃                                                        ┃
┣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┫
┃                    [Cancel]  [Submit for Approval]    ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

All 3 changes submitted as a single request.
All 3 fields get "Pending approval" badges.
All 3 fields are locked.
Admin reviews all changes together (approve/reject all at once).
```

---

## Badge Placement Examples

### **Text Input with Badge**

```
Business Registration Number  [Pending approval]
┌────────────────────────────────────────┐
│ FN 123456a                             │  (disabled)
└────────────────────────────────────────┘
```

### **Dropdown with Badge**

```
Company Type  [Pending approval]
┌────────────────────────────────────────┐
│ GmbH                                ▼  │  (disabled)
└────────────────────────────────────────┘
```

### **Textarea with Badge**

```
📍 Legal Address  [Pending approval]
┌────────────────────────────────────────┐
│ Kärntner Straße 1, 1010 Wien, Austria │  (disabled)
│                                        │
└────────────────────────────────────────┘
```

---

## Color Specifications

### **Pending State (Yellow)**

```
Banner:
  Background: bg-yellow-50    (#fefce8)
  Border:     border-yellow-300 (#fde047)
  Text:       text-yellow-900   (#713f12)
  Icon:       text-yellow-700   (#a16207)

Badge:
  Background: bg-yellow-100    (#fef9c3)
  Border:     border-yellow-300 (#fde047)
  Text:       text-yellow-800   (#854d0e)
```

### **Approved State (Green)**

```
Banner:
  Background: bg-green-50     (#f0fdf4)
  Border:     border-green-300  (#86efac)
  Text:       text-green-900    (#14532d)
  Icon:       text-green-700    (#15803d)
```

### **Rejected State (Red)**

```
Banner:
  Background: bg-red-50       (#fef2f2)
  Border:     border-red-300    (#fca5a5)
  Text:       text-red-900      (#7f1d1d)
  Icon:       text-red-700      (#b91c1c)
```

### **Info State (Blue)**

```
Box:
  Background: bg-blue-50      (#eff6ff)
  Border:     border-blue-200   (#bfdbfe)
  Text:       text-blue-900     (#1e3a8a)
```

### **Disabled Fields**

```
Input:
  Background: bg-gray-100     (#f3f4f6)
  Cursor:     cursor-not-allowed
  Border:     border (unchanged)
  Text:       text-gray-700 (slightly faded)
```

---

## Responsive Behavior

### **Desktop (≥ 768px)**

```
┌──────────────────────────────────────────────────────┐
│ ⚠️  Changes pending Tavlo admin approval...          │ ← Full width banner
├──────────────────────────────────────────────────────┤
│                                                      │
│ Business Reg #  [Pending]     VAT Number  [Pending] │ ← 2 columns
│ ┌──────────────┐              ┌──────────────┐      │
│ │ FN 123456a   │              │ ATU12345678  │      │
│ └──────────────┘              └──────────────┘      │
│                                                      │
│ Company Type  [Pending]                              │ ← 1 column
│ ┌──────────────┐                                     │
│ │ GmbH ▼       │                                     │
│ └──────────────┘                                     │
│                                                      │
│ 📍 Legal Address  [Pending]                          │ ← Full width
│ ┌───────────────────────────────────────────────┐    │
│ │ Kärntner Straße 1, 1010 Wien, Austria        │    │
│ └───────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────┘
```

### **Mobile (< 768px)**

```
┌──────────────────────────┐
│ ⚠️  Changes pending      │ ← Full width
│    Tavlo admin approval  │
├──────────────────────────┤
│ Business Reg #           │
│ [Pending]                │ ← Badge wraps
│ ┌──────────────────────┐ │ ← Full width
│ │ FN 123456a           │ │
│ └──────────────────────┘ │
│                          │
│ VAT Number  [Pending]    │
│ ┌──────────────────────┐ │
│ │ ATU12345678          │ │
│ └──────────────────────┘ │
│                          │
│ Company Type  [Pending]  │
│ ┌──────────────────────┐ │
│ │ GmbH ▼               │ │
│ └──────────────────────┘ │
│                          │
│ 📍 Legal Address         │
│ [Pending]                │
│ ┌──────────────────────┐ │
│ │ Kärntner Straße 1... │ │
│ └──────────────────────┘ │
└──────────────────────────┘
```

---

## Icon Usage

| State | Icon | Size | Color | Placement |
|-------|------|------|-------|-----------|
| Pending Banner | `<AlertCircle />` | h-4 w-4 | text-yellow-700 | Left of banner text |
| Approved Banner | `<CheckCircle />` | h-4 w-4 | text-green-700 | Left of banner text |
| Rejected Banner | `<AlertCircle />` | h-4 w-4 | text-red-700 | Left of banner text |
| Modal Title | `<AlertCircle />` | w-6 h-6 | text-amber-600 | Left of title text |

---

## Interaction States

### **Save Button States**

```
Normal (no pending changes):
┌─────────────────┐
│ 💾 Save Changes │  ← Enabled, clickable
└─────────────────┘

While saving:
┌─────────────────┐
│ ⟳  Saving...    │  ← Disabled, spinner
└─────────────────┘

When pending:
┌─────────────────┐
│ 💾 Save Changes │  ← Enabled but will show error if clicked
└─────────────────┘
  ↓ (vendor clicks)
┌────────────────────────────────────────┐
│ 🔴 Cannot save while changes pending   │  ← Error toast
└────────────────────────────────────────┘
```

---

## Confirmation Modal - Detailed Layout

```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                                                       ┃
┃  ⚠️  Confirm Legal Information Change                ┃ ← Title (text-xl)
┃                                                       ┃
┃  You are about to change information used for        ┃ ← Description
┃  invoices and tax compliance. These changes require  ┃   (text-base)
┃  Tavlo admin review and may temporarily affect       ┃
┃  invoicing.                                          ┃
┃                                                       ┃
┃━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┃
┃                                                       ┃
┃  Changes to be reviewed:                              ┃ ← Heading (font-semibold)
┃                                                       ┃
┃  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  ┃
┃  ┃                                                 ┃  ┃ ← bg-gray-50
┃  ┃  VAT Number                                     ┃  ┃   (change container)
┃  ┃  ───────────────────────────────────────────── ┃  ┃
┃  ┃                                                 ┃  ┃
┃  ┃  Current Value          New Value              ┃  ┃
┃  ┃  ┌──────────────┐      ┌─────────────────────┐┃  ┃
┃  ┃  │ ATU12345678  │      │ ATU99999999         │┃  ┃
┃  ┃  │ (white bg)   │      │ (amber bg)          │┃  ┃
┃  ┃  └──────────────┘      └─────────────────────┘┃  ┃
┃  ┃                                                 ┃  ┃
┃  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  ┃
┃                                                       ┃
┃  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓  ┃
┃  ┃  ℹ️  Important: Your current approved values   ┃  ┃ ← bg-blue-50
┃  ┃     will remain active for invoices and        ┃  ┃   (info box)
┃  ┃     customer-facing pages until these changes  ┃  ┃
┃  ┃     are approved by Tavlo admin.               ┃  ┃
┃  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛  ┃
┃                                                       ┃
┃━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┃
┃                                                       ┃
┃                          ┌────────┐  ┌──────────────┐┃ ← Footer
┃                          │ Cancel │  │ Submit for   │┃   (DialogFooter)
┃                          │        │  │ Approval     │┃
┃                          └────────┘  └──────────────┘┃
┃                          (outline)   (amber bg)      ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

Modal width: max-w-2xl (672px)
Padding: Standard DialogContent padding
Backdrop: Semi-transparent dark overlay
Can close: ESC key or clicking backdrop
```

---

## State Transition Diagram

```
                     ┌────────┐
                     │  NONE  │
                     │ (ready)│
                     └────┬───┘
                          │
              Vendor changes legal field
              and clicks "Save Changes"
                          │
                          ↓
                  ┌───────────────┐
                  │ Show Modal    │
                  │ (confirmation)│
                  └───────┬───────┘
                          │
                 Vendor clicks
               "Submit for Approval"
                          │
                          ↓
                   ┌──────────┐
                   │ PENDING  │
                   │ (locked) │
                   └─────┬────┘
                         │
                 Admin reviews
                         │
           ┌─────────────┴─────────────┐
           │                           │
      Admin approves              Admin rejects
           │                           │
           ↓                           ↓
    ┌────────────┐             ┌──────────────┐
    │  APPROVED  │             │   REJECTED   │
    │ (unlocked) │             │  (reverted)  │
    └──────┬─────┘             └──────┬───────┘
           │                           │
     After brief display          After display
           │                           │
           └─────────────┬─────────────┘
                         ↓
                    ┌────────┐
                    │  NONE  │
                    │ (ready)│
                    └────────┘
```

---

**Last Updated:** January 2026  
**Version:** 1.0
