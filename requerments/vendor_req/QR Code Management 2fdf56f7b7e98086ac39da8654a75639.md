# QR Code Management

## 1. Purpose of This Page

The **QR Code Management** page allows restaurant vendors to:

- Generate and manage QR codes for each table
- Monitor real-time table status via QR usage
- Print, download, or refresh QR codes
- Control customer ordering entry points

This page is **operationally critical**. If QR codes break or are replaced incorrectly, ordering stops.

---

## 2. System Relationship (Critical Context)

### 2.1 Dependency on Settings Page

This page is tightly coupled with:

**Settings → Tables & QR**

Changes in Settings directly affect QR behavior:

| Setting | Impact on QR Page |
| --- | --- |
| Number of Tables | Defines how many QR codes exist |
| Table Prefix | Affects table naming (e.g., T1, T2…) |
| Shared Basket | Impacts order session logic per QR |
| Reservations Enabled | Affects table availability logic |
| Max Guests per Table | Limits session size |

### 2.2 Navigation Link

- From Settings → "Go to QR Code Management"
- From QR page → banner shows current configuration summary

---

## 3. Page Structure Overview

### 3.1 Top Configuration Banner

Displays current system behavior:

- Shared basket: Enabled / Disabled
- Reservations: Enabled / Disabled
- Max guests per table: X

**Purpose:**

Prevents confusion. Vendor sees current logic without navigating away.

---

### 3.2 Action Buttons (Top Right)

### 1. Regenerate All QR Codes (Danger Action)

- Color: Red
- Function: Replaces ALL QR codes with new ones

**Impact:**

- All old QR codes stop working instantly
- Customers scanning old codes will fail
- Requires physical replacement of all printed QRs

**Confirmation Modal Includes:**

- Warning message (critical)
- Checkbox: "I understand..."
- Disabled confirm button until checked

**Backend Logic:**

- Generate new unique tokens for all tables
- Invalidate all previous tokens in DB
- Update mapping: table_id → new_qr_token

---

### 2. Print All QR Codes

- Color: Blue
- Function: Opens print layout (PDF-ready)

**Print Layout Rules:**

- Grid format (multiple tables per page)
- Each card includes:
    - Restaurant name
    - Table name
    - QR code
    - "Scan to Order"
    - Generation date

**Frontend Requirement:**

- Print CSS optimized
- No UI elements visible (clean layout)

---

### 3.3 Summary Cards

| Card | Description |
| --- | --- |
| Total Tables | From Settings |
| Active QR Codes | Should match table count unless error |
| Restaurant | Name + internal ID |

---

### 3.4 Info Box ("How QR Codes Work")

Purpose:

- Educates vendor
- Reduces support tickets

Content:

- Each table has unique QR
- QR links to ordering session
- Can refresh individually
- Can print or download

---

## 4. Table QR Cards (Core Component)

Each table is represented as a **card**.

---

### 4.1 Table Header

Contains:

- Table Name (e.g., Table 1)
- Status indicator (color dot)
- Last scan info
- Generation date

---

### 4.2 Status Indicator Logic

| Color | Meaning | Backend Condition |
| --- | --- | --- |
| 🟢 Green | Idle / Available | No active order session |
| 🟡 Yellow | Order in progress | Active session, not paid |
| 🔴 Red | Waiting for payment | Order completed, unpaid |

**Important:**

This is NOT cosmetic. It reflects real order state.

---

### 4.3 QR Code Display

- Unique per table
- Encodes URL with:
    - restaurant_id
    - table_id
    - secure token

Example:

```
tavlo.app/order?rid=123&tid=5&token=abc123
```

---

### 4.4 Last Scan Information

Displays:

- "Never scanned" OR
- "Last scanned X min/hours/days ago"

**Backend Requirement:**

- Track scan timestamp per table
- Update on each QR access

---

### 4.5 Advanced Section (Expandable)

Contains actions:

### 1. Refresh

- Regenerates QR for this table only
- Invalidates old QR

Use case:

- QR damaged
- Security concern

---

### 2. Print

- Prints only this table’s QR
- Opens print dialog

---

### 3. Download

- Downloads QR as image (PNG or PDF)

---

### 4. Copy Link

- Copies QR URL (not image)

Use case:

- Send via WhatsApp
- Embed elsewhere

---

## 5. Backend Architecture

### 5.1 Data Model

### Table Entity

```
table_id
restaurant_id
name
status (derived)
qr_token
qr_created_at
last_scanned_at
```

---

### Order Session Entity

```
session_id
table_id
status (active, completed, unpaid)
created_at
closed_at
```

---

### 5.2 QR Token Logic

- Must be unique and secure
- Recommended: UUID or hashed token

When refreshing:

- Old token → invalid
- New token → stored

---

### 5.3 Status Calculation (Critical)

Do NOT store status statically.

Compute dynamically:

```
IF no active session → GREEN
IF active session AND not paid → YELLOW
IF session completed AND not paid → RED
```

---

### 5.4 Scan Tracking

On QR scan:

- Validate token
- Log timestamp
- Update `last_scanned_at`

---

### 5.5 Bulk Operations

### Regenerate All

- Transaction-based
- Must not partially update

### Print All

- Generate printable HTML or PDF server-side OR client-rendered

---

## 6. Frontend Architecture

### 6.1 Component Structure

- QRManagementPage
    - HeaderBanner
    - ActionButtons
    - SummaryCards
    - InfoBox
    - TableGrid
        - TableCard
            - QRDisplay
            - StatusIndicator
            - ActionsDropdown

---

### 6.2 State Management

Must handle:

- Table list
- Status updates (real-time or polling)
- QR refresh updates

---

### 6.3 Real-Time Consideration

Recommended:

- Poll every 10–30 seconds OR
- Use WebSocket for live updates

Reason:

- Table status must reflect reality

---

### 6.4 Print Handling

- Use separate print layout route or CSS
- Remove sidebar and UI clutter
- Ensure proper spacing for physical printing

---

## 7. Edge Cases (You Must Handle These)

### 7.1 QR Scanned After Regeneration

- Old QR → invalid
- Show:
    
    "This QR code is no longer valid"
    

---

### 7.2 Table Deleted in Settings

- QR must disappear
- Orders remain unaffected
- Table QR Code cannot be deleted if there is an active session.

---

### 7.3 Table Count Increased

- New QR codes auto-generated

---

### 7.4 Table Count Reduced

- Extra tables disabled or removed
- Existing sessions must not break

---

### 7.5 No Internet / Offline

- QR scan fails gracefully
- Show retry message

---

## 8. Security Considerations

- QR tokens must not be guessable
- Expired tokens must be rejected
- Rate-limit QR access to prevent abuse

---

## 8. Take Away End-to-End Flow

1. Vendor prints takeaway QR
2. Places it at the entrance / online
3. Customer scans
4. Ordering session starts (no table)
5. Customer selects items
6. Inputs personal data (if not logged in)
7. Selects pickup time
8. Pays immediately or later (based on the settings)
9. Order sent to the kitchen

---

# 9. Takeaway QR Code

## 9.1 Purpose of This Component

The **Takeaway QR Code** is a separate entry point into the ordering system.

Unlike table QR codes:

- It is **not linked to any table**
- It is used for **off-table ordering**
- It supports **pickup flow instead of dine-in flow**

This is a **revenue extension tool**, not just a utility.

---

## 9.2 Position in the System

### 9.2.1 Relationship to Table QR Codes

| Feature | Table QR | Takeaway QR |
| --- | --- | --- |
| Linked to table | Yes | No |
| Session tied to table | Yes | No |
| Status tracking (green/yellow/red) | Yes | No |
| Shared basket | Yes | Not applicable |
| Use case | Dine-in | Pickup / remote order |

---

### 9.2.2 Dependency on Settings

Indirect dependency:

- Menu availability → must be active
- Payment settings → required for checkout
- Ordering settings → affects flow behavior

**No dependency on:**

- Table count
- Table prefix
- Table status

---

## 9.3 Component Structure

### 9.3.1 Section Header

- Title: **Takeaway QR Code**
- Label: **Special**

Purpose:

- Clearly differentiates from standard table QR system

---

### 9.3.2 QR Code Display (Main Element)

- Large, centered QR code
- Represents **global takeaway entry point**

**Encoded Data:**

```
tavlo.app/takeaway?rid=restaurant_id&token=secure_token
```

**Backend Requirement:**

- Single token per restaurant (not per table)
- Must be secure and non-guessable

---

### 9.3.3 Status Information (Below QR)

- Label: **TAKEAWAY ORDERS**
- Subtext:
    - "Not linked to any table"
    - "Generated on [date]"

Purpose:

- Prevents confusion with table-based system

---

## 9.4 Right Information Panel

### 9.4.1 "What is this QR code for?"

Explains:

- This QR enables takeaway ordering
- No table interaction required
- Can be placed:
    - Entrance
    - Counter
    - Flyers
    - Website

---

### 9.4.2 Usage Tips Section

Bullet points:

- Print and display physically
- Use in marketing materials
- Customers select pickup time
- No table number required

**Purpose:**

Reduces onboarding friction for vendor

---

## 9.5 Action Buttons

### 9.5.1 Print Takeaway QR Code

- Primary (highlighted button)

**Function:**

- Opens print dialog with formatted layout

**Requirements:**

- Clean printable design
- Large QR
- Branding visible
- "Scan to Order" CTA

---

### 9.5.2 Download

- Downloads QR as file (PNG or PDF)

Use cases:

- Share digitally
- Send to designer
- Upload to website

---

### 9.5.3 Copy Link

- Copies underlying URL

Use cases:

- Social media
- Google Maps link
- WhatsApp sharing

---

### 9.5.4 Refresh

- Regenerates QR for the takeaway only
- Invalidates old QR

Use case:

- QR damaged
- Security concern

---

## 9.6. Backend Architecture

### 9.6.1 Data Model

### Takeaway QR Entity

```
restaurant_id
qr_token
created_at
last_regenerated_at
```

---

### 9.6.2 Order Model (Takeaway)

```
order_id
restaurant_id
type = "takeaway"
customer_name
customer_phone
pickup_time
status (pending, preparing, ready, completed)
payment_status
created_at
```

---

### 9.6.3 Token Logic

- One active token per restaurant
- Regeneration replaces token (if implemented later)
- Old links become invalid

---

### 9.6.4 Scan Behavior

On scan:

- Validate token
- Start takeaway session
- No table assignment

---

## 9.7. Frontend Behavior

### 9.7.1 Component Isolation

- Must be visually distinct from table QR section
- Use color coding (purple used correctly)

---

### 9.7.2 Responsiveness

- QR must scale properly on mobile and desktop
- Buttons must remain accessible

---

### 9.7.3 Print Layout

Separate from table print:

- Larger QR
- Single QR per page (recommended)

---

## 9.8. Key Differences in Logic (Critical)

### 9.8.1 No Table State

- No green/yellow/red logic
- No session tied to physical location

---

### 9.8.2 No Shared Basket

- Each customer session is independent

---

### 9.8.3 Payment Flow is Mandatory

For takeaway:

- Payment might need to happen before order confirmation. This depends on the vendor's payment settings. Settings → Payment → Available Payment Methods → Cash for Takeaway Orders
- No “pay at table” concept

---

## 9.9. Edge Cases

### 9.9.1 QR Shared Publicly

- High traffic possible
- Backend must scale

---

### 9.9.2 Invalid Token

- Show:
    
    "This ordering link is no longer active"
    

---

### 9.9.3 Restaurant Closed

- Show:
    
    "Ordering is currently unavailable."
    

---

### 9.9.4 No Pickup Slots Available

- Prevent checkout
- Show the next available time

---

## 9.10. Security Considerations

- The token must not be predictable
- Rate-limit requests
- Protect against spam orders

---

## 9.11. Improvement later in time

- Bulk select + print specific tables
- Search table (for large restaurants)
- Analytics per table (scan frequency)
- Custom QR branding (logo inside QR)
- Analytics:
    - Number of scans
    - Conversion rate (scan → order)

---

## 9.12. Dine-in End-to-End Flow

1. Vendor sets tables in Settings
2. The system generates a QR per table
3. QR printed and placed physically
4. Customer scans QR
5. Session created
6. Orders placed
7. Table status updates:
    - Green → Yellow → Red → Green again
8. Vendor monitors via QR page
9. QR refreshed if needed