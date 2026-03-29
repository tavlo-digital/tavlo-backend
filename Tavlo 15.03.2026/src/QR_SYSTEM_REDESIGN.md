# QR Configuration System Redesign - Documentation

## Overview

The QR configuration system has been redesigned to enforce **strict separation** between:
1. **Settings → Tables & QR** - Global configuration (rules, behavior)
2. **QR Codes → QR Code Management** - Operational control (view, print, refresh individual QRs)

This separation creates clarity, reduces operational risk, and establishes proper authority boundaries.

---

## Design Principle

> **Settings define RULES. QR Management applies ACTIONS.**

The system follows a strict **unidirectional flow**:
```
Settings (Global Rules) → Backend → QR Management (Operations)
```

Settings changes automatically affect QR Management behavior and display, but the UI remains clearly separated.

---

## PART 1: SETTINGS → TABLES & QR (GLOBAL CONFIGURATION)

### Purpose

This page defines **global QR behavior and table structure**. It must NOT contain operational or destructive actions.

---

### Section 1: Table Configuration

**Improved fields:**

#### **Number of Tables**
- **Input:** Number field (1-999)
- **Helper text (NEW):**
  > "Changing this value will automatically create or deactivate table QR codes. Existing orders are not affected."

**Why this matters:**
- Makes it clear what happens when you change table count
- Reassures vendors that existing orders are safe
- Prevents confusion about QR code lifecycle

---

#### **Table Prefix**
- **Input:** Text field (max 3 characters)
- **Live preview (NEW):**
  > "Example: T1, T2, T3 …"

**Old preview:** `T1, T2, ...`  
**New preview:** `T1, T2, T3 …` (shows 3 examples for clarity)

**Why:**
- More explicit about the pattern
- Helps vendors understand the naming convention

---

#### **Max Guests per Table**
- **Input:** Number field (1-50)
- **No changes** - kept as is

---

### Section 2: Order Behavior

#### **Enable Shared Basket**
- **Type:** Checkbox in bordered card (enhanced visual)
- **Improved helper text:**
  > "Allows multiple devices at the same table to add items to one shared order. Recommended for group dining."

**Old text:** "Allow multiple guests at the same table to add items to a shared order"  
**New text:** Adds "Recommended for group dining" to provide guidance

**Visual changes:**
- Now in a bordered, hoverable card
- Better visual hierarchy
- More prominent (this is an important setting)

---

### Section 3: Reservation Settings

**No functional changes** - kept existing:
- Enable Table Reservations (checkbox)
- Total Tables for Reservations (conditional field)
- Max Capacity per Table (conditional field)

**Visual changes:**
- Checkbox now in bordered card for consistency
- Conditional fields have left border to show hierarchy

---

### Section 4: QR Code Management Link (NEW - MANDATORY)

**New section** at the bottom of the page.

**Visual design:**
- Gradient blue background (`bg-gradient-to-br from-blue-50 to-indigo-50`)
- Blue border (`border-2 border-blue-200`)
- QR icon in blue badge
- Prominent call-to-action

**Content:**

**Title:** "QR Code Management"

**Description:**
> "View, print, refresh, and manage individual table QR codes."

**Button:**
- Text: "Go to QR Code Management"
- Action: Navigates to QR Codes page
- Color: Blue primary button

**Purpose:**
- Clearly separates configuration from operations
- Provides explicit navigation path
- Makes it clear where operational actions happen

---

### ❌ **Removed from Settings**

**What was removed:**
1. ✂️ **"Regenerate All QR Codes" button** - This is an operational action, not a setting
2. ✂️ **Print/download actions** - These belong in QR Management

**Why removed:**
- Settings should define rules only
- Operational actions create confusion and risk
- Follows principle: "Settings = Rules, QR Management = Actions"

---

## PART 2: QR CODES → QR CODE MANAGEMENT (OPERATIONAL CONTROL)

### Purpose

This page **manages individual QR codes** based on rules defined in Settings.

---

### Section 1: Top Context Banner (NEW - MANDATORY)

**New banner** at the very top of the QR Management page.

**Visual design:**
- Light blue background (`bg-blue-50`)
- Blue border (`border-blue-200`)
- Info icon
- Read-only (informational, not interactive)

**Content:**

**Header text:**
> "QR Behavior is defined in Settings → Tables & QR"

**Dynamic summary showing:**
1. **Shared basket:** Enabled / Disabled (badge)
2. **Reservations:** Enabled / Disabled (badge)
3. **Max guests per table:** X (badge)

**Example:**
```
ℹ️ QR Behavior is defined in Settings → Tables & QR

Shared basket: [Enabled]  Reservations: [Disabled]  Max guests per table: [10]
```

**Purpose:**
- Provides context about current QR behavior
- Shows read-only settings from Settings page
- Reminds vendors where to change these values
- Creates clear link between the two pages

**Data source:**
- In production: Pulled from Settings via API or shared context
- Currently: Mock data (`qrSettings` object)

---

### Section 2: QR Overview Cards

**No changes** - everything remains the same:
- ✓ Stats cards (Total Tables, Active QR Codes, Never Scanned)
- ✓ Individual table cards with:
  - Table number
  - QR code preview
  - Usage health status
  - Table status (idle/active/waiting-payment)
  - Actions: Print, Download, Refresh, Copy Link
  - Advanced toggle for technical details
- ✓ "Regenerate All QR Codes" modal
- ✓ "Print All QR Codes" functionality

**The only addition** is the context banner at the top.

---

## Backend Linking Instructions

### Architecture

The system treats QR Settings and QR Management as:
- **UI-separated** (different pages)
- **Backend-connected** (data flows between them)

### Rules

1. **Settings defines:**
   - Table count
   - Table prefix
   - Shared basket behavior
   - Reservation settings
   - Max guests per table

2. **QR Management reads these values as:**
   - Read-only context (shown in banner)
   - Behavior rules (affects how QR codes function)

3. **QR regeneration and lifecycle actions:**
   - Do NOT exist in Settings
   - Only exist in QR Management

### Data Flow

```
User changes Settings
    ↓
Settings saved to backend
    ↓
QR Management fetches updated settings
    ↓
Context banner updates
    ↓
QR behavior adjusts automatically
```

### Implementation Notes

**Settings → QR Management communication:**

Option 1 (Recommended): **Shared Context/State**
```typescript
// Create a Settings Context
const SettingsContext = createContext();

// Settings page updates context
updateSettings(newSettings);

// QR Management reads from context
const { sharedBasket, reservations, maxGuests } = useSettings();
```

Option 2: **API Fetch**
```typescript
// QR Management fetches on mount
useEffect(() => {
  fetchSettings().then(settings => {
    setQrSettings(settings);
  });
}, []);
```

Option 3: **Real-time Updates**
```typescript
// Subscribe to settings changes
useEffect(() => {
  const unsubscribe = subscribeToSettings((settings) => {
    setQrSettings(settings);
  });
  return unsubscribe;
}, []);
```

**Current implementation:**
- Mock data in `qrSettings` object
- Replace with actual API call or context in production

---

## Navigation Changes

### Settings Component

**Added:**
```typescript
interface SettingsProps {
  vendorId: string;
  onNavigate?: (screen: string) => void;  // NEW
}
```

**Button action:**
```typescript
<Button onClick={() => onNavigate?.('qr-codes')}>
  Go to QR Code Management
</Button>
```

### VendorDashboard Component

**Updated:**
```typescript
{currentScreen === 'settings' && (
  <Settings 
    vendorId={vendorId} 
    onNavigate={(screen) => setCurrentScreen(screen as any)}  // NEW
  />
)}
```

---

## Visual Comparison

### Before

**Settings → Tables & QR:**
```
[Table Configuration]
  - Number of Tables
  - Table Prefix
  - Max Guests per Table

[Enable Shared Basket] ☑

[Reservation Settings]
  - Enable Reservations ☑
  - Fields...

[QR Code Management]  👈 CONFUSING PLACEMENT
  [Regenerate All QR Codes] 👈 DESTRUCTIVE ACTION IN SETTINGS
  Text: "Generate QR codes for all tables..."
```

**QR Codes → QR Code Management:**
```
[Header: QR Code Management]
[Stats cards...]
[Individual table cards...]
```

---

### After

**Settings → Tables & QR:**
```
[Table Configuration]
  - Number of Tables
    Helper: "Changing this value will automatically create or deactivate table QR codes..."
  - Table Prefix
    Live preview: "Example: T1, T2, T3 …"
  - Max Guests per Table

[Order Behavior] 👈 RENAMED SECTION
  [Enhanced card: Enable Shared Basket] ☑
  Helper: "...Recommended for group dining."

[Reservation Settings]
  [Enhanced card: Enable Reservations] ☑
  - Fields...

[QR Code Management Link] 👈 NEW NAVIGATION SECTION
  🎯 Blue highlighted card
  "View, print, refresh, and manage individual table QR codes."
  [Go to QR Code Management] 👈 CLEAR NAVIGATION
```

**QR Codes → QR Code Management:**
```
[Context Banner] 👈 NEW
  ℹ️ "QR Behavior is defined in Settings → Tables & QR"
  Shared basket: [Enabled]
  Reservations: [Disabled]
  Max guests per table: [10]

[Header: QR Code Management]
[Stats cards...]
[Individual table cards...]
```

---

## Constraints (All Met)

✅ **Do NOT add per-table configuration in Settings** - Settings remain global only  
✅ **Do NOT duplicate QR actions across pages** - Actions only in QR Management  
✅ **Do NOT introduce advanced automation or edge logic** - Simple, explicit, safe  
✅ **Keep the system simple, explicit, and safe** - Clear separation achieved  

---

## Benefits of This Redesign

### 1. **Clarity**
- Settings = Rules
- QR Management = Operations
- No confusion about where to do what

### 2. **Authority Separation**
- Configuration requires thought (Settings)
- Operations are safe and repeatable (QR Management)
- Reduces accidental changes

### 3. **Reduced Operational Risk**
- No destructive actions in Settings
- "Regenerate All" stays in controlled environment with confirmation modal
- Helper text explains impact

### 4. **Better User Flow**
- Natural progression: Configure → Operate
- Clear navigation between pages
- Context banner provides constant reference

### 5. **Scalability**
- Easy to add more settings without cluttering QR Management
- Easy to add more operations without cluttering Settings
- Clean separation allows independent evolution

---

## Testing Checklist

### Settings Page
- [ ] Number of Tables shows helper text
- [ ] Table Prefix shows live preview with 3 examples
- [ ] Shared Basket checkbox is in bordered card
- [ ] Shared Basket helper text mentions "Recommended for group dining"
- [ ] Reservation settings work as before
- [ ] QR Code Management Link section is visible
- [ ] "Go to QR Code Management" button navigates to QR page
- [ ] No "Regenerate All QR Codes" button present

### QR Management Page
- [ ] Context banner appears at top
- [ ] Context banner shows correct shared basket status
- [ ] Context banner shows correct reservations status
- [ ] Context banner shows correct max guests value
- [ ] All existing functionality works (print, download, refresh)
- [ ] "Regenerate All QR Codes" button still present (unchanged)
- [ ] Individual table cards work as before

### Navigation
- [ ] Can navigate from Settings to QR Management
- [ ] Navigation preserves vendorId
- [ ] Can navigate back to Settings via sidebar

### Backend Integration (Future)
- [ ] Context banner pulls real settings data
- [ ] Changing settings in Settings page updates banner in real-time
- [ ] QR behavior adjusts based on settings

---

## File Changes Summary

### Modified Files

**1. `/components/vendor/Settings.tsx`**
- Added `onNavigate` prop to interface
- Rewrote `renderTableSettings()` function
- Removed "Regenerate All QR Codes" button
- Added "QR Code Management Link" section
- Improved helper text for Number of Tables
- Enhanced Shared Basket checkbox
- Updated Table Prefix preview

**2. `/components/vendor/VendorDashboard.tsx`**
- Added `onNavigate` prop to `<Settings />` component

**3. `/components/vendor/QRCodesManagement.tsx`**
- Added `qrSettings` mock data
- Added top context banner with settings summary

### No Changes To
- QR Management functionality (print, download, refresh)
- Table card displays
- Stats cards
- Modal interactions
- Any other vendor dashboard pages

---

## Future Enhancements

### Phase 1 (Current)
✅ Visual separation  
✅ Navigation link  
✅ Context banner  

### Phase 2 (Next)
- [ ] Real-time settings updates in context banner
- [ ] API integration for settings fetch
- [ ] Settings context provider

### Phase 3 (Future)
- [ ] Per-table overrides (advanced feature, gated)
- [ ] QR code analytics in Settings
- [ ] Bulk operations in QR Management

---

**Last Updated:** January 2026  
**Version:** 2.0 (Redesigned)
