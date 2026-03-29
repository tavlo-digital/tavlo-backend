# QR System Redesign - Visual Guide

## Page Layouts

### SETTINGS → TABLES & QR

```
┌─────────────────────────────────────────────────────────────┐
│                     SETTINGS: TABLES & QR                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ TABLE CONFIGURATION                                    │ │
│  ├───────────────────────────────────────────────────────┤ │
│  │                                                       │ │
│  │  Number of Tables: [____20____]                      │ │
│  │  ℹ️ Changing this value will automatically create    │ │
│  │  or deactivate table QR codes. Existing orders are   │ │
│  │  not affected.                                        │ │
│  │                                                       │ │
│  │  Table Prefix: [__T__]                               │ │
│  │  Example: T1, T2, T3 …                               │ │
│  │                                                       │ │
│  │  Max Guests per Table: [____10____]                  │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ ORDER BEHAVIOR                                         │ │
│  ├───────────────────────────────────────────────────────┤ │
│  │                                                       │ │
│  │  ┌─────────────────────────────────────────────────┐ │ │
│  │  │ ☑ Enable Shared Basket                         │ │ │
│  │  │                                                 │ │ │
│  │  │   Allows multiple devices at the same table to │ │ │
│  │  │   add items to one shared order. Recommended   │ │ │
│  │  │   for group dining.                             │ │ │
│  │  └─────────────────────────────────────────────────┘ │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ RESERVATION SETTINGS                                   │ │
│  ├───────────────────────────────────────────────────────┤ │
│  │                                                       │ │
│  │  ┌─────────────────────────────────────────────────┐ │ │
│  │  │ ☑ Enable Table Reservations                    │ │ │
│  │  │   Allow customers to book tables in advance    │ │ │
│  │  └─────────────────────────────────────────────────┘ │ │
│  │                                                       │ │
│  │  [Conditional fields when enabled]                   │ │
│  │  ┃ Total Tables: [__20__]                            │ │
│  │  ┃ Max Capacity: [__6__]                             │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓ │
│  ┃ 🔵 QR CODE MANAGEMENT                              ┃ │
│  ┃                                                     ┃ │
│  ┃ 📱  View, print, refresh, and manage individual    ┃ │
│  ┃     table QR codes.                                 ┃ │
│  ┃                                                     ┃ │
│  ┃ [Go to QR Code Management →]                       ┃ │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛ │
│                                                             │
│  [Cancel]                                 [Save Settings]  │
└─────────────────────────────────────────────────────────────┘
```

---

### QR CODES → QR CODE MANAGEMENT

```
┌─────────────────────────────────────────────────────────────┐
│                    QR CODE MANAGEMENT                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓ │
│  ┃ ℹ️  QR Behavior is defined in Settings → Tables & QR┃ │
│  ┃                                                     ┃ │
│  ┃ Shared basket: [Enabled]                           ┃ │
│  ┃ Reservations: [Disabled]                           ┃ │
│  ┃ Max guests per table: [10]                         ┃ │
│  ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛ │
│                                                             │
│  [QR Code Management]                                      │
│  Manage QR codes for all your restaurant tables            │
│                                                             │
│  [Regenerate All QR Codes]  [Print All QR Codes]          │
│                                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                │
│  │ Total    │  │ Active   │  │ Never    │                │
│  │ Tables   │  │ QR Codes │  │ Scanned  │                │
│  │   20     │  │   18     │  │    4     │                │
│  └──────────┘  └──────────┘  └──────────┘                │
│                                                             │
│  [Individual Table Cards with QR codes...]                 │
│  ┌────────────────────────────────────┐                   │
│  │ Table 1          🟢 Idle           │                   │
│  │ [QR Code]                          │                   │
│  │ ✓ Scanned today                    │                   │
│  │ [Print] [Download] [Refresh] [📋]  │                   │
│  └────────────────────────────────────┘                   │
│                                                             │
│  [More table cards...]                                     │
└─────────────────────────────────────────────────────────────┘
```

---

## Section Breakdown

### Settings Page - Section Hierarchy

```
1. TABLE CONFIGURATION (Configuration)
   └─ Number of Tables ⚙️
      └─ Helper text (explains auto-create/deactivate)
   └─ Table Prefix ⚙️
      └─ Live preview
   └─ Max Guests per Table ⚙️

2. ORDER BEHAVIOR (Configuration)
   └─ Enable Shared Basket ☑️
      └─ Enhanced helper text

3. RESERVATION SETTINGS (Configuration)
   └─ Enable Table Reservations ☑️
      └─ Conditional fields

4. QR CODE MANAGEMENT LINK (Navigation)
   └─ Description
   └─ Navigation button → Goes to QR Management
```

**Key:** ⚙️ = Setting | ☑️ = Toggle | 🔗 = Navigation

---

### QR Management Page - Section Hierarchy

```
1. CONTEXT BANNER (Read-only info from Settings)
   └─ Link reference to Settings
   └─ Shared basket status
   └─ Reservations status
   └─ Max guests value

2. HEADER (Operations)
   └─ Title and description
   └─ [Regenerate All] button (destructive)
   └─ [Print All] button (bulk action)

3. STATS CARDS (Analytics)
   └─ Total Tables
   └─ Active QR Codes
   └─ Never Scanned

4. TABLE CARDS (Individual operations)
   └─ Per-table QR code
   └─ Usage health
   └─ Actions: Print, Download, Refresh, Copy
```

---

## Visual States

### Settings: QR Code Management Link

**Normal State:**
```
┌────────────────────────────────────────┐
│ 🔵 QR CODE MANAGEMENT                 │
│                                        │
│ 📱  View, print, refresh, and manage  │
│     individual table QR codes.        │
│                                        │
│ [Go to QR Code Management →]          │
└────────────────────────────────────────┘
```

**Hover State:**
```
┌────────────────────────────────────────┐
│ 🔵 QR CODE MANAGEMENT                 │
│                                        │
│ 📱  View, print, refresh, and manage  │
│     individual table QR codes.        │
│                                        │
│ [Go to QR Code Management → ]  ⬅ darker
└────────────────────────────────────────┘
```

---

### QR Management: Context Banner

**State 1: Shared Basket Enabled**
```
┌────────────────────────────────────────────┐
│ ℹ️  QR Behavior is defined in Settings    │
│                                            │
│ Shared basket: [Enabled]  ← Green badge   │
│ Reservations: [Disabled]  ← Gray badge    │
│ Max guests per table: [10] ← Gray badge   │
└────────────────────────────────────────────┘
```

**State 2: All Features Disabled**
```
┌────────────────────────────────────────────┐
│ ℹ️  QR Behavior is defined in Settings    │
│                                            │
│ Shared basket: [Disabled]  ← Gray badge   │
│ Reservations: [Disabled]  ← Gray badge    │
│ Max guests per table: [10] ← Gray badge   │
└────────────────────────────────────────────┘
```

---

## Color Coding

### Settings Page

| Element | Color | Purpose |
|---------|-------|---------|
| Section headers | `text-gray-900` | High contrast |
| Helper text | `text-gray-600` | Supporting info |
| Input fields | `border-gray-300` | Standard inputs |
| Bordered cards | `border-gray-200` | Grouped options |
| QR Management Link | `from-blue-50 to-indigo-50` | Call to action |
| QR Link border | `border-blue-200` | Emphasis |
| Navigation button | `bg-blue-600` | Primary action |

---

### QR Management Page

| Element | Color | Purpose |
|---------|-------|---------|
| Context banner bg | `bg-blue-50` | Information |
| Context banner border | `border-blue-200` | Information |
| Info icon | `text-blue-600` | Information |
| Enabled badge | Green | Active status |
| Disabled badge | Gray | Inactive status |
| Stats cards | White | Standard content |
| Table cards | White | Standard content |

---

## Typography Scale

### Settings Page

```
Page Title: Not shown (part of main Settings tabs)
Section Headers: text-lg font-semibold (18px, 600)
Labels: text-sm font-medium (14px, 500)
Helper Text: text-xs text-gray-600 (12px, gray)
Button Text: text-sm (14px, standard)
Navigation Card Title: text-lg font-semibold (18px, 600)
Navigation Card Body: text-sm (14px, standard)
```

---

### QR Management Page

```
Page Title: text-2xl font-semibold (24px, 600)
Context Banner Header: text-sm font-medium (14px, 500)
Context Banner Items: text-sm (14px, standard)
Badge Text: text-xs (12px, standard)
Table Numbers: text-2xl font-semibold (24px, 600)
Card Labels: text-sm (14px, standard)
```

---

## Spacing & Layout

### Settings Page

```
Section spacing: space-y-8 (2rem / 32px)
Within sections: space-y-4 (1rem / 16px)
Grid gaps: gap-4 (1rem / 16px)
Card padding: p-4 (1rem / 16px)
QR Link padding: p-6 (1.5rem / 24px)
```

---

### QR Management Page

```
Main container: space-y-6 (1.5rem / 24px)
Context banner padding: p-4 (1rem / 16px)
Stats grid gaps: gap-4 (1rem / 16px)
Card padding: p-6 (1.5rem / 24px)
```

---

## Icons Used

### Settings Page
- `<QrCode />` - QR Code Management section

### QR Management Page
- `<Info />` - Context banner
- `<QrCode />` - Throughout cards
- `<Printer />` - Print actions
- `<Download />` - Download actions
- `<RefreshCw />` - Refresh actions
- `<Copy />` / `<Check />` - Copy link actions

---

## Responsive Behavior

### Settings Page

**Desktop (> 768px):**
- Grid: 2 columns for table configuration
- Full width for QR Management Link
- Side-by-side for Save/Cancel buttons

**Tablet (768px - 1024px):**
- Grid: 2 columns (maintained)
- Slightly reduced padding

**Mobile (< 768px):**
- Grid: 1 column (stacked)
- Full width buttons
- Reduced padding

---

### QR Management Page

**Desktop (> 768px):**
- Context banner: Horizontal layout
- Stats: 3 columns
- Table cards: Grid layout

**Tablet (768px - 1024px):**
- Context banner: May wrap
- Stats: 3 columns (maintained)
- Table cards: 2 columns

**Mobile (< 768px):**
- Context banner: Vertical stack
- Stats: 1 column
- Table cards: 1 column

---

## User Flow Diagram

```
User Opens Settings
        ↓
   [Tables & QR Tab]
        ↓
Configure Table Settings
   - Number of Tables
   - Table Prefix
   - Max Guests
        ↓
Configure Order Behavior
   - Shared Basket
        ↓
Configure Reservations
   - Enable/Disable
        ↓
See "QR Code Management Link"
        ↓
Click [Go to QR Code Management]
        ↓
Navigate to QR Management Page
        ↓
See Context Banner
   (Shows settings just configured)
        ↓
Manage Individual QR Codes
   - Print
   - Download
   - Refresh
   - Copy Link
        ↓
Changes in Settings auto-reflect here
```

---

## Before vs After Comparison

### Settings Page

**Before:**
```
[Number of Tables] [____20____]
[Table Prefix] [__T__]
Example: T1, T2, ...

☑ Enable Shared Basket
Allow multiple guests at the same table...

[QR Code Management]
[Regenerate All QR Codes] ← CONFUSING!
Generate QR codes for all tables...
```

**After:**
```
[Number of Tables] [____20____]
ℹ️ Changing this value will automatically...

[Table Prefix] [__T__]
Example: T1, T2, T3 …

┌─────────────────────────────┐
│ ☑ Enable Shared Basket     │
│ ...Recommended for group    │
│ dining.                     │
└─────────────────────────────┘

┏━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃ 🔵 QR CODE MANAGEMENT    ┃
┃ [Go to QR Management →]  ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  ↑ CLEAR NAVIGATION
```

---

### QR Management Page

**Before:**
```
[QR Code Management]
Manage QR codes for all your restaurant tables

[Regenerate All] [Print All]

[Stats Cards...]
[Table Cards...]
```

**After:**
```
┏━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃ ℹ️ QR Behavior defined in ┃
┃ Settings → Tables & QR    ┃
┃ Shared: [✓] Reserv: [✗]  ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━┛
  ↑ NEW CONTEXT

[QR Code Management]
Manage QR codes for all your restaurant tables

[Regenerate All] [Print All]

[Stats Cards...]
[Table Cards...]
```

---

## Edge Cases & States

### Settings: Number of Tables Changed

**Scenario:** User changes from 20 to 25 tables

**What happens:**
1. Settings page: Helper text reminds "will automatically create or deactivate"
2. User saves settings
3. Backend creates 5 new QR codes (Table 21-25)
4. QR Management: Stats update (Total Tables: 25)
5. QR Management: Context banner remains same (no behavior change)

---

### Settings: Shared Basket Toggled

**Scenario:** User disables Shared Basket

**What happens:**
1. Settings page: Checkbox unchecked
2. User saves settings
3. QR Management: Context banner updates
   - Before: `Shared basket: [Enabled]`
   - After: `Shared basket: [Disabled]`
4. QR code behavior changes (no longer allows shared baskets)

---

### QR Management: All QR Codes Never Scanned

**Scenario:** New restaurant, no scans yet

**Context banner:** Shows settings normally
**Stats card:** "Never Scanned: 20" (all tables)
**Table cards:** All show "Never scanned" status
**Action:** Vendor can investigate why (QR placement, visibility)

---

## Accessibility

### Settings Page
- ✓ All inputs have labels
- ✓ Helper text associated with inputs
- ✓ Navigation button has descriptive text
- ✓ Focus states on all interactive elements
- ✓ Keyboard navigation supported

### QR Management Page
- ✓ Context banner has semantic info icon
- ✓ Badges have descriptive text (not just color)
- ✓ All buttons have accessible names
- ✓ Table cards have proper hierarchy
- ✓ Screen reader friendly structure

---

**Last Updated:** January 2026
