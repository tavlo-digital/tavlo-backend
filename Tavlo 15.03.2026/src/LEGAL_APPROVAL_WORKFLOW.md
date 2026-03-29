# Legal Information Approval Workflow - Implementation Documentation

## Overview

The Business Info settings page now includes a **comprehensive confirmation and admin approval workflow** for sensitive legal fields. This prevents accidental changes to critical business information and ensures compliance through admin review.

---

## Scope

### **Sensitive Fields (Requiring Approval):**

1. **Restaurant Name** (in Restaurant Profile section)
2. **Business Registration Number** (in Legal Business Information section)
3. **VAT Number** (in Legal Business Information section)
4. **Company Type** (in Legal Business Information section)
5. **Legal Address** (in Legal Business Information section)

### **Non-Sensitive Fields (No Approval Required):**

All other fields in Business Info remain unaffected:
- Description
- Email
- Phone
- Website
- Logo
- Cover Photo
- Business Hours

---

## Workflow Overview

```
Vendor edits legal field
         ↓
    Clicks "Save Changes"
         ↓
┌────────────────────────┐
│ STEP 1: Detect changes │
│ in sensitive fields    │
└────────────────────────┘
         ↓
┌────────────────────────┐
│ STEP 2: Show           │
│ Confirmation Modal     │
│ with before/after      │
└────────────────────────┘
         ↓
  Vendor confirms
         ↓
┌────────────────────────┐
│ STEP 3: Set to         │
│ "Pending" status       │
│ Lock fields            │
│ Show banner            │
└────────────────────────┘
         ↓
  Admin reviews (backend)
         ↓
    ┌─────────┐
    │ Approve │ or │ Reject │
    └─────────┘    └────────┘
         ↓              ↓
┌────────────────┐  ┌─────────────────┐
│ STEP 5:        │  │ STEP 5:         │
│ Approved state │  │ Rejected state  │
│ Green banner   │  │ Red banner      │
│ Unlock fields  │  │ Keep old values │
└────────────────┘  └─────────────────┘
```

---

## STEP 1: Change Detection

### **Implementation:**

**Location:** `handleSave()` function

**Logic:**
```typescript
const sensitiveFields = [
  { field: 'restaurantName', label: 'Restaurant Name', 
    current: originalLegalInfo.restaurantName, new: businessInfo.restaurantName },
  { field: 'businessRegNumber', label: 'Business Registration Number', 
    current: originalLegalInfo.businessRegNumber, new: businessInfo.businessRegNumber },
  { field: 'vatNumber', label: 'VAT Number', 
    current: originalLegalInfo.vatNumber, new: businessInfo.vatNumber },
  { field: 'companyType', label: 'Company Type', 
    current: originalLegalInfo.companyType, new: taxSettings.companyType },
  { field: 'address', label: 'Legal Address', 
    current: originalLegalInfo.address, new: businessInfo.address }
];

const detectedChanges = sensitiveFields.filter(f => f.current !== f.new);
```

### **Behavior:**

1. When vendor clicks "Save Changes", compare current values to original approved values
2. If any sensitive field changed AND not already pending:
   - Store detected changes in `legalChanges` state
   - Show confirmation modal
   - **Stop** the save process (return early)
3. If already pending:
   - Block save with error toast
   - Message: "Cannot save while changes are pending admin approval"
4. If no legal field changes:
   - Proceed with normal save

### **State Used:**

```typescript
const [originalLegalInfo, setOriginalLegalInfo] = useState({
  restaurantName: 'La Bella Vista',
  businessRegNumber: 'FN 123456a',
  vatNumber: 'ATU12345678',
  companyType: 'GmbH',
  address: 'Kärntner Straße 1, 1010 Wien, Austria'
});

const [legalChanges, setLegalChanges] = useState<Array<{
  field: string, 
  label: string, 
  oldValue: string, 
  newValue: string
}>>([]);
```

---

## STEP 2: Confirmation Modal

### **Visual Design:**

```
┌──────────────────────────────────────────┐
│ ⚠️  Confirm Legal Information Change     │ ← Title
├──────────────────────────────────────────┤
│ You are about to change information      │
│ used for invoices and tax compliance.    │ ← Message
│ These changes require Tavlo admin review │
│ and may temporarily affect invoicing.    │
├──────────────────────────────────────────┤
│ Changes to be reviewed:                  │
│                                          │
│ ┌──────────────────────────────────────┐ │
│ │ VAT Number                           │ │
│ │ ┌───────────┐  ┌───────────────────┐│ │
│ │ │ Current   │  │ New Value         ││ │
│ │ │ ATU123... │  │ ATU987...         ││ │ ← Before/After
│ │ └───────────┘  └───────────────────┘│ │
│ └──────────────────────────────────────┘ │
│                                          │
│ ⓘ Important: Current approved values    │
│   remain active until admin approves.   │ ← Info
├──────────────────────────────────────────┤
│           [Cancel]  [Submit for Approval]│ ← Actions
└──────────────────────────────────────────┘
```

### **Implementation:**

**Component:** Dialog from shadcn/ui

**Trigger:** `showConfirmationModal` state

**Content:**

1. **Title:**
   - Text: "Confirm Legal Information Change"
   - Icon: AlertCircle (amber)
   - Font size: text-xl

2. **Message:**
   - Clear warning about consequences
   - No soft language
   - Mentions: invoices, tax compliance, admin review, temporary impact

3. **Change Summary:**
   - Heading: "Changes to be reviewed:"
   - For each changed field:
     - Field label (bold)
     - Two columns: "Current Value" | "New Value"
     - Current value: white background
     - New value: amber background (highlighted)
     - Empty values shown as "(Not set)" in gray italic

4. **Info Box:**
   - Blue background (`bg-blue-50`)
   - Shield icon
   - Text: "Important: Your current approved values will remain active for invoices and customer-facing pages until these changes are approved by Tavlo admin."

5. **Actions:**
   - **Cancel Button:**
     - Variant: outline
     - Action: Close modal, no changes
   - **Submit for Approval Button:**
     - Color: Amber (`bg-amber-600`)
     - Text: "Submit for Approval" (no soft language)
     - Action: Call `handleSubmitForApproval()`

### **Code:**

```tsx
<Dialog open={showConfirmationModal} onOpenChange={setShowConfirmationModal}>
  <DialogContent className="max-w-2xl">
    <DialogHeader>
      <DialogTitle className="flex items-center gap-2 text-xl">
        <AlertCircle className="w-6 h-6 text-amber-600" />
        Confirm Legal Information Change
      </DialogTitle>
      <DialogDescription className="text-base pt-2">
        You are about to change information used for invoices and tax 
        compliance. These changes require Tavlo admin review and may 
        temporarily affect invoicing.
      </DialogDescription>
    </DialogHeader>

    {/* Change summary with before/after comparison */}
    {/* ... */}

    <DialogFooter className="gap-2">
      <Button variant="outline" onClick={() => setShowConfirmationModal(false)}>
        Cancel
      </Button>
      <Button onClick={handleSubmitForApproval} className="bg-amber-600">
        Submit for Approval
      </Button>
    </DialogFooter>
  </DialogContent>
</Dialog>
```

---

## STEP 3: Pending Approval State

### **What Happens:**

After vendor confirms in modal:

1. Save pending values to `pendingLegalInfo` state
2. Set `legalApprovalStatus` to `'pending'`
3. In production: API call to backend to store pending values
4. Show success toast: "Legal information changes submitted for admin approval"
5. UI updates immediately to show pending state

### **Pending State Handler:**

```typescript
const handleSubmitForApproval = async () => {
  setShowConfirmationModal(false);
  setSaving(true);

  try {
    const pendingData = {
      restaurantName: businessInfo.restaurantName,
      businessRegNumber: businessInfo.businessRegNumber,
      vatNumber: businessInfo.vatNumber,
      companyType: taxSettings.companyType,
      address: businessInfo.address
    };

    setPendingLegalInfo(pendingData);
    setLegalApprovalStatus('pending');

    // Backend API call (to be implemented):
    // await api.submitLegalInfoForApproval(vendorId, pendingData);
    
    toast.success('Legal information changes submitted for admin approval');
  } catch (error) {
    console.error('Failed to submit for approval:', error);
    toast.error('Failed to submit changes for approval');
  } finally {
    setSaving(false);
  }
};
```

### **UI Changes:**

#### **1. Yellow Pending Banner (Top of Legal Section):**

```tsx
{legalApprovalStatus === 'pending' && (
  <Alert className="mb-6 bg-yellow-50 border-yellow-300">
    <AlertCircle className="h-4 w-4 text-yellow-700" />
    <AlertDescription className="text-yellow-900">
      <strong>Changes pending Tavlo admin approval.</strong> Your submitted 
      changes are under review. Current approved values remain active for 
      invoices and customer-facing pages.
    </AlertDescription>
  </Alert>
)}
```

**Visual:**
```
┌──────────────────────────────────────────┐
│ ⚠️  Changes pending Tavlo admin approval.│ YELLOW
│     Your submitted changes are under     │
│     review. Current approved values      │
│     remain active...                     │
└──────────────────────────────────────────┘
```

#### **2. "Pending approval" Badge on Each Field:**

**Visual:**
```
Business Registration Number  [Pending approval]
┌────────────────────────────┐
│ FN 123456a (disabled)      │
└────────────────────────────┘
```

**Code:**
```tsx
<label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
  Business Registration Number
  {legalApprovalStatus === 'pending' && (
    <Badge variant="outline" className="bg-yellow-100 text-yellow-800 border-yellow-300">
      Pending approval
    </Badge>
  )}
</label>
```

#### **3. Field Locking (Disabled Inputs):**

All sensitive fields become disabled:

```tsx
<input
  type="text"
  value={businessInfo.businessRegNumber}
  onChange={(e) => setBusinessInfo({...businessInfo, businessRegNumber: e.target.value})}
  disabled={legalApprovalStatus === 'pending'}
  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 
             disabled:bg-gray-100 disabled:cursor-not-allowed"
/>
```

**Disabled state styling:**
- Background: `bg-gray-100`
- Cursor: `cursor-not-allowed`
- Cannot be edited

---

## STEP 4: Visibility Rules

### **While Pending:**

| What | Who Sees What |
|------|---------------|
| **Invoices** | Old (approved) values |
| **Receipts** | Old (approved) values |
| **Customer-facing pages** | Old (approved) values |
| **Vendor dashboard** | New (pending) values shown BUT marked as "Pending" |
| **Field editing** | Locked (cannot edit) |
| **Additional changes** | Blocked (cannot submit new changes) |

### **Implementation Notes:**

- **Backend responsibility:** Backend must maintain two sets of values:
  - `approved_legal_info` - Active for invoices/receipts/customers
  - `pending_legal_info` - Visible only to vendor and admin
- **Frontend displays:** Current input values, but with "Pending" badges
- **Save button behavior:** Blocked with error message if status is 'pending'

### **Blocking Additional Changes:**

In `handleSave()`:
```typescript
if (legalApprovalStatus === 'pending') {
  toast.error('Cannot save while changes are pending admin approval');
  return;
}
```

---

## STEP 5: Post-Approval States

### **STATE A: Approved**

#### **Visual:**
```
┌──────────────────────────────────────────┐
│ ✓  Changes approved!                     │ GREEN
│    Your legal information has been       │
│    updated successfully.                 │
└──────────────────────────────────────────┘
```

#### **Code:**
```tsx
{legalApprovalStatus === 'approved' && (
  <Alert className="mb-6 bg-green-50 border-green-300">
    <CheckCircle className="h-4 w-4 text-green-700" />
    <AlertDescription className="text-green-900">
      <strong>Changes approved!</strong> Your legal information has been 
      updated successfully.
    </AlertDescription>
  </Alert>
)}
```

#### **Behavior:**
- Pending banner disappears
- Green success banner shows briefly
- "Pending approval" badges removed from fields
- Fields become editable again
- New values become active everywhere (invoices, receipts, customer pages)
- `originalLegalInfo` updated to match `pendingLegalInfo`
- `legalApprovalStatus` set to `'approved'` then back to `'none'`

---

### **STATE B: Rejected**

#### **Visual:**
```
┌──────────────────────────────────────────┐
│ ✕  Changes were not approved.            │ RED
│    Your submitted changes have been      │
│    rejected. Original values remain      │
│    active. Please contact support...     │
└──────────────────────────────────────────┘
```

#### **Code:**
```tsx
{legalApprovalStatus === 'rejected' && (
  <Alert className="mb-6 bg-red-50 border-red-300">
    <AlertCircle className="h-4 w-4 text-red-700" />
    <AlertDescription className="text-red-900">
      <strong>Changes were not approved.</strong> Your submitted changes 
      have been rejected. Original values remain active. Please contact 
      support for details.
    </AlertDescription>
  </Alert>
)}
```

#### **Behavior:**
- Pending banner disappears
- Red rejection banner shows
- "Pending approval" badges removed from fields
- Fields become editable again
- **Original values restored** in inputs (rollback)
- Old values remain active everywhere
- Vendor can edit and resubmit if needed
- `legalApprovalStatus` set to `'rejected'`

---

## State Management

### **State Variables:**

```typescript
// Original approved values (source of truth for active data)
const [originalLegalInfo, setOriginalLegalInfo] = useState({
  restaurantName: 'La Bella Vista',
  businessRegNumber: 'FN 123456a',
  vatNumber: 'ATU12345678',
  companyType: 'GmbH',
  address: 'Kärntner Straße 1, 1010 Wien, Austria'
});

// Pending values awaiting approval (null when no pending changes)
const [pendingLegalInfo, setPendingLegalInfo] = useState<any>(null);

// Current approval status
const [legalApprovalStatus, setLegalApprovalStatus] = useState<
  'none' | 'pending' | 'approved' | 'rejected'
>('none');

// Confirmation modal visibility
const [showConfirmationModal, setShowConfirmationModal] = useState(false);

// Changes detected for display in modal
const [legalChanges, setLegalChanges] = useState<Array<{
  field: string, 
  label: string, 
  oldValue: string, 
  newValue: string
}>>([]);
```

### **State Transitions:**

```
'none' 
  ↓ (vendor changes legal field and saves)
'pending'
  ↓ (admin approves)
'approved' → 'none' (after brief display)
  ↓ (admin rejects)
'rejected' → 'none' (vendor can retry)
```

---

## Backend Integration

### **Required API Endpoints:**

#### **1. Submit for Approval:**
```typescript
POST /api/vendor/:vendorId/legal-info/submit-for-approval
Body: {
  restaurantName: string,
  businessRegNumber: string,
  vatNumber: string,
  companyType: string,
  address: string
}
Response: {
  success: boolean,
  pendingId: string,
  message: string
}
```

#### **2. Get Approval Status:**
```typescript
GET /api/vendor/:vendorId/legal-info/approval-status
Response: {
  status: 'none' | 'pending' | 'approved' | 'rejected',
  pendingInfo: {...} | null,
  approvedInfo: {...},
  rejectionReason?: string
}
```

#### **3. Admin Approve/Reject:**
```typescript
POST /api/admin/legal-info/:pendingId/approve
POST /api/admin/legal-info/:pendingId/reject
Body: {
  reason?: string // for rejection
}
```

### **Database Schema Suggestion:**

```sql
-- Main vendor table (approved values)
vendors
  - id
  - restaurant_name (active/approved)
  - business_reg_number (active/approved)
  - vat_number (active/approved)
  - company_type (active/approved)
  - address (active/approved)
  ...

-- Pending legal changes table
legal_info_pending
  - id
  - vendor_id
  - restaurant_name (pending)
  - business_reg_number (pending)
  - vat_number (pending)
  - company_type (pending)
  - address (pending)
  - status ('pending' | 'approved' | 'rejected')
  - submitted_at
  - reviewed_at
  - reviewed_by_admin_id
  - rejection_reason
```

### **Backend Rules:**

1. **One pending request at a time** - Vendor cannot submit new changes while one is pending
2. **Approved values only for invoices** - Always use `vendors` table for invoice generation
3. **Audit trail** - Keep history of all changes and approvals
4. **Admin notification** - Notify admin when new submission arrives
5. **Vendor notification** - Notify vendor when admin approves/rejects

---

## User Experience Flow

### **Scenario 1: Vendor Changes VAT Number**

1. Vendor types new VAT number in field
2. Clicks "Save Changes"
3. Modal appears with before/after comparison
4. Vendor clicks "Submit for Approval"
5. Modal closes
6. Yellow banner appears: "Changes pending admin approval"
7. VAT field shows "Pending approval" badge
8. VAT field becomes disabled
9. Vendor continues working with other settings
10. *(Admin approves in background)*
11. Green banner appears: "Changes approved!"
12. Banner disappears after a few seconds
13. Field becomes editable again
14. New VAT number is now active everywhere

### **Scenario 2: Multiple Fields Changed**

1. Vendor changes:
   - VAT Number: ATU123 → ATU999
   - Business Reg: FN 123a → FN 456b
   - Company Type: GmbH → AG
2. Clicks "Save Changes"
3. Modal shows **all 3 changes** in a list with before/after
4. Vendor reviews all changes
5. Clicks "Submit for Approval"
6. All 3 fields locked with "Pending approval" badges
7. *(Admin approves all)*
8. All 3 fields updated and unlocked

### **Scenario 3: Rejected Changes**

1. Vendor submits invalid VAT number
2. Status = pending
3. *(Admin reviews and rejects)*
4. Red banner appears: "Changes were not approved"
5. Input field reverts to original value
6. Field becomes editable again
7. Vendor can correct and resubmit

### **Scenario 4: Vendor Tries to Change While Pending**

1. Vendor has pending VAT change
2. Tries to change Business Reg Number
3. Clicks "Save Changes"
4. Error toast: "Cannot save while changes are pending admin approval"
5. No modal appears
6. Vendor must wait for admin review

---

## UI States Summary

| Status | Banner | Field Badges | Fields Editable | Save Behavior |
|--------|--------|--------------|-----------------|---------------|
| `none` | None | None | ✅ Yes | Normal save (or trigger modal if changes detected) |
| `pending` | Yellow "Pending" | "Pending approval" | ❌ No (disabled) | Blocked with error |
| `approved` | Green "Approved" (brief) | None | ✅ Yes | Normal save |
| `rejected` | Red "Not approved" | None | ✅ Yes | Normal save (vendor can retry) |

---

## Visual Design Specifications

### **Colors:**

| State | Background | Border | Text | Icon |
|-------|------------|--------|------|------|
| Pending | `bg-yellow-50` | `border-yellow-300` | `text-yellow-900` | `text-yellow-700` |
| Approved | `bg-green-50` | `border-green-300` | `text-green-900` | `text-green-700` |
| Rejected | `bg-red-50` | `border-red-300` | `text-red-900` | `text-red-700` |
| Info | `bg-blue-50` | `border-blue-200` | `text-blue-900` | `text-blue-700` |

### **Badge (Pending Approval):**

```tsx
<Badge variant="outline" className="bg-yellow-100 text-yellow-800 border-yellow-300">
  Pending approval
</Badge>
```

- Background: `bg-yellow-100`
- Text: `text-yellow-800`
- Border: `border-yellow-300`
- Size: Small (default badge size)

### **Modal:**

- Width: `max-w-2xl` (672px)
- Title font: `text-xl`
- Change summary: Gray background (`bg-gray-50`)
- Current value box: White (`bg-white`)
- New value box: Amber highlight (`bg-amber-50` with `border-amber-200`)

---

## Accessibility

### **Keyboard Navigation:**

- Modal can be closed with Escape key
- Tab order: Cancel → Submit for Approval
- Focus trap inside modal
- Fields disabled state is keyboard-accessible (can tab through but not edit)

### **Screen Readers:**

- Alert banners use `Alert` component with proper ARIA roles
- "Pending approval" badge is read with field label
- Modal title and description properly associated
- Disabled fields announced as "disabled" or "unavailable"

### **Visual Indicators:**

- Never rely on color alone
- Icons accompany all banners (AlertCircle, CheckCircle)
- Text clearly states status ("Pending", "Approved", "Rejected")
- Disabled fields have both visual (gray background) and cursor changes

---

## Error Handling

### **Frontend Errors:**

1. **Network error during submission:**
   - Show error toast: "Failed to submit changes for approval"
   - Do not set status to 'pending'
   - Vendor can retry

2. **Already pending:**
   - Block save attempt
   - Show error toast: "Cannot save while changes are pending admin approval"

### **Backend Validation:**

Backend should validate:
- VAT number format (country-specific)
- Business registration number format
- Company type is valid option
- Address is not empty

If validation fails:
- Return 400 error
- Frontend shows error toast with specific message
- Vendor can correct and resubmit

---

## Testing Checklist

### **Functional:**

- [ ] Changing legal field triggers modal (not immediate save)
- [ ] Modal shows correct before/after values
- [ ] Multiple field changes shown in single modal
- [ ] Cancel button closes modal without changes
- [ ] Submit button sets status to pending
- [ ] Pending banner appears after submission
- [ ] Fields become disabled when pending
- [ ] "Pending approval" badges appear on all affected fields
- [ ] Save button blocked when status is pending
- [ ] Error toast shown when trying to save while pending
- [ ] Approved state shows green banner
- [ ] Rejected state shows red banner and reverts values
- [ ] Non-legal fields save normally (no modal)

### **Visual:**

- [ ] Modal is centered and properly sized
- [ ] Change summary is readable with clear before/after
- [ ] Yellow banner has correct styling
- [ ] Green banner has correct styling
- [ ] Red banner has correct styling
- [ ] Badges have correct colors and positioning
- [ ] Disabled fields have gray background
- [ ] Icons display correctly (AlertCircle, CheckCircle)

### **Edge Cases:**

- [ ] Changing then reverting field (no modal if same as original)
- [ ] Changing only non-legal fields (no modal)
- [ ] Network error during submission (proper error handling)
- [ ] Rapid clicking Submit button (prevent double submission)
- [ ] Modal closed accidentally (can trigger again)

---

## Future Enhancements

### **Possible Improvements:**

1. **Partial Approval:**
   - Admin can approve some fields and reject others
   - Vendor sees mixed state (some approved, some rejected)

2. **Rejection Reasons:**
   - Admin provides specific reason for rejection
   - Shown in red banner: "Rejected: Invalid VAT format"

3. **Edit While Pending:**
   - Allow vendor to cancel pending request
   - Edit and resubmit new values

4. **History/Audit Log:**
   - Show all previous change requests
   - Status of each (approved, rejected, pending)
   - Dates and admin who reviewed

5. **Email Notifications:**
   - Vendor receives email when approved/rejected
   - Admin receives email when new submission arrives

6. **Deadline/SLA:**
   - Show estimated review time
   - Escalate if pending too long

---

## Important Notes

### **⚠️ No Actual Approval Logic Implemented:**

The current implementation is **UI/UX only**. The following are **not implemented**:

- Backend API calls for submission
- Admin review interface
- Actual data persistence of pending values
- Status updates from backend
- Automatic state transitions

**What IS implemented:**
- Complete UI for all states (pending, approved, rejected)
- Modal with change summary
- Field locking
- State management (local only)
- Change detection logic

**To make it production-ready:**
1. Implement backend API endpoints (see Backend Integration section)
2. Replace commented API calls with actual fetch/axios calls
3. Poll or subscribe to status updates from backend
4. Build admin review interface
5. Add proper error handling for network issues

### **Backend-Driven State:**

The UI should **reflect** backend state, not manage it:

```typescript
// On component mount or periodic refresh:
const status = await api.getLegalApprovalStatus(vendorId);
setLegalApprovalStatus(status.status);
setPendingLegalInfo(status.pendingInfo);
setOriginalLegalInfo(status.approvedInfo);
```

The frontend does not decide when to approve/reject - only the backend (via admin action) does.

---

**Last Updated:** January 2026  
**Version:** 1.0 (Initial Implementation)
