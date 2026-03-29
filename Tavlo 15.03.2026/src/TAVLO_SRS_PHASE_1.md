# TAVLO — Software Requirements Specification  
## PHASE 1 — LAUNCH (Core Product)

**Document Version:** 4.0  
**Last Updated:** December 26, 2024  
**Status:** Production Development  
**Timeline:** Weeks 1-12  
**Goal:** Sellable & Usable Core Platform

---

## TABLE OF CONTENTS

1. [Introduction](#1-introduction)
2. [Platform Philosophy](#2-platform-philosophy)
3. [Phase 1 Overview](#3-phase-1-overview)
4. [Customer Features](#4-customer-features)
5. [Vendor Features](#5-vendor-features)
6. [Admin Features](#6-admin-features)
7. [Platform Pages](#7-platform-pages)
8. [Technical Architecture](#8-technical-architecture)
9. [Data Models](#9-data-models)

---

## 1. INTRODUCTION

### 1.1 Purpose
This document specifies Phase 1 functional requirements for TAVLO, a QR-based restaurant ordering platform. Phase 1 focuses on core ordering, payments, and vendor onboarding to create a sellable, production-ready product.

### 1.2 Scope
TAVLO Phase 1 enables:
- **Customers** to scan QR codes, order collaboratively, split bills, and pay
- **Vendors** to onboard, subscribe, manage menus, receive orders, and generate VAT-compliant invoices
- **Admins** to approve vendors, monitor platform health, and enforce rules

### 1.3 Definitions

| Term | Definition |
|------|------------|
| **Vendor** | Restaurant operator using TAVLO |
| **Customer** | End user scanning QR and ordering |
| **Table Session** | Active ordering session tied to a specific table QR code |
| **Shared Basket** | Real-time collaborative cart where multiple customers see identical items |
| **Split Bill** | Feature allowing automated payment division |
| **Dine-in** | Order type where customer eats at restaurant table |
| **Takeaway** | Order type where customer picks up food |
| **Guest** | Customer who orders without creating account |

---

## 2. PLATFORM PHILOSOPHY

### 2.1 What TAVLO IS
✅ **Platform Enabler** — Provides infrastructure for digital ordering  
✅ **Payment Processor** — Handles secure transactions via Stripe  
✅ **Analytics Provider** — Delivers business intelligence tools  
✅ **Compliance Partner** — Ensures Austrian VAT/tax compliance  

### 2.2 What TAVLO IS NOT
❌ **Restaurant Operator** — Never controls vendor operations  
❌ **Price Controller** — Vendors set their own prices  
❌ **Menu Manager** — Vendors manage their own menus  
❌ **Order Modifier** — Cannot interfere with customer orders  

### 2.3 Admin System Boundaries
- **CAN:** Approve/reject vendors, suspend for violations, moderate reviews, audit transactions
- **CANNOT:** Edit vendor menus, change pricing, cancel customer orders, access customer payment details

---

## 3. PHASE 1 OVERVIEW

### 3.1 Goals
- ✅ Production-ready core ordering system
- ✅ Subscription-based vendor monetization
- ✅ Austrian VAT-compliant invoice generation
- ✅ Real-time shared basket collaboration
- ✅ Automated split bill functionality

### 3.2 Success Criteria
- [ ] Customer can complete order from QR scan to payment in <3 minutes
- [ ] Shared basket updates across devices in <500ms
- [ ] 100% Austrian VAT compliance on all invoices
- [ ] Vendor can onboard and go live in <15 minutes
- [ ] Admin can approve/reject vendors within 24 hours

### 3.3 Phase 1 Feature Count
- **Customer Features:** 29
- **Vendor Features:** 38
- **Admin Features:** 20
- **Platform Pages:** 8
- **Total:** 95 features

---

## 4. CUSTOMER FEATURES

### 4.1 Entry & Authentication

#### FR-C1-001: QR Code Scanning
**Priority:** CRITICAL  
**Description:** Customer scans QR code at table to start ordering

**Acceptance Criteria:**
- QR format: `https://tavlo.app/r/{restaurantId}/t/{tableNumber}` for dine-in
- QR format: `https://tavlo.app/r/{restaurantId}/takeaway` for takeaway
- Invalid QR shows error: "This QR code is invalid. Please contact staff."
- Valid QR redirects to menu immediately
- URL routing validates `restaurantId` exists

**Business Rules:**
- `restaurantId` must exist and vendor must be active
- `tableNumber` optional (null = takeaway mode)
- QR codes never expire (tied to restaurant ID)

**Technical Notes:**
- Use URL routing, not database lookup for speed
- Validate `restaurantId` via `GET /api/restaurants/{id}/status`
- Store `table_session_id` in localStorage
- Track session start timestamp

#### FR-C1-002: Order Type Selection
**Priority:** CRITICAL  
**Description:** Customer chooses dine-in or takeaway mode

**Acceptance Criteria:**
- Dine-in: Shows table number, enables "Call Waiter", allows cash payment
- Takeaway: Shows pickup time selector, requires prepayment (unless `acceptCashTakeaway = true`)
- UI displays: "🍽️ Dine-in at Table 5" or "🛍️ Takeaway Order"
- Selection persists throughout session

**Business Rules:**
- Takeaway cash only available if `vendor.settings.acceptCashTakeaway = true`
- Dine-in always available during restaurant open hours
- Takeaway requires pickup time selection

**Technical Notes:**
- Store in session: `order_type = 'dine_in' | 'takeaway'`
- Check vendor settings before displaying cash option
- Pickup time calculated based on kitchen capacity

#### FR-C1-003: Guest Checkout (Default)
**Priority:** CRITICAL  
**Description:** Customer can order without creating account

**Acceptance Criteria:**
- No login/signup required to browse menu or add items
- At checkout, optional fields: name, phone, email
- Guest orders stored with `customer_id = null`
- Session identified by `table_session_id`
- Guest data not retained after order completion (GDPR-compliant)

**Business Rules:**
- Name max length: 50 characters
- Phone validation: E.164 format preferred but lenient
- Email validation: RFC 5322 compliant
- Phone required only if `vendor.settings.requirePhoneNumber = true`

**Technical Notes:**
- No authentication token required
- `table_session_id` stored in localStorage
- Session expires after 3 hours of inactivity
- PII not subject to GDPR retention (transactional only)

#### FR-C1-004: Optional Account Creation
**Priority:** LOW  
**Description:** Customer can create account for order history and loyalty

**Acceptance Criteria:**
- "Create Account" link shown in guest checkout
- Signup form collects: name (required), email (required), password (min 8 chars), phone (optional)
- Email confirmation required (double-opt-in)
- After confirmation, customer can log in
- Account gives access to order history, saved restaurants, loyalty points

**Business Rules:**
- Email must be unique across all customers
- Password requirements: min 8 chars, 1 uppercase, 1 number
- Account inactive until email confirmed
- GDPR consent checkbox required

**Technical Notes:**
```typescript
// Supabase Auth
const { data, error } = await supabase.auth.signUp({
  email: email,
  password: password,
  options: {
    data: { name: name, phone: phone }
  }
});

// Store in customers table
{
  id: string; // UUID from Supabase Auth
  email: string;
  name: string;
  phone: string | null;
  created_at: timestamp;
  email_confirmed: boolean;
}
```

#### FR-C1-005: Customer Login
**Priority:** LOW  
**Description:** Registered customers can log in to access account features

**Acceptance Criteria:**
- Login form: email + password
- "Forgot password?" link triggers password reset email
- Successful login redirects to previous page or home
- Login persists across browser sessions
- Invalid credentials show: "Invalid email or password"

**Business Rules:**
- Max 5 failed login attempts per 15 minutes (rate limiting)
- Account must have `email_confirmed = true` to log in
- Password reset link expires after 1 hour

**Technical Notes:**
- Use Supabase Auth: `supabase.auth.signInWithPassword()`
- Store JWT token in HTTP-only cookie
- Refresh token automatically before expiry

---

### 4.2 Menu Browsing

#### FR-C1-006: Menu Display
**Priority:** CRITICAL  
**Description:** Customer views restaurant menu with categories and items

**Acceptance Criteria:**
- Menu items grouped by category (Starters, Mains, Desserts, Drinks, etc.)
- Each item shows: image (fallback if missing), name (translated), description (translated), price (formatted), allergen icons
- Sticky category headers on scroll
- "Out of stock" items shown with 50% opacity + "Unavailable" label
- Categories collapsible/expandable
- Smooth scroll to category when tapping category filter

**Business Rules:**
- Only show items where `available = true` OR show unavailable with visual indicator
- Categories sorted by `sort_order` field
- Items within category sorted by `sort_order`
- Empty categories not displayed

**Technical Notes:**
- Fetch menu via `GET /api/restaurants/{restaurantId}/menu`
- Cache menu client-side for 5 minutes
- Language priority: 1) Customer selected, 2) Browser locale, 3) German, 4) English
```typescript
{
  categories: Array<{
    id: string;
    name: string;
    sort_order: number;
    items: Array<MenuItem>;
  }>;
}
```

#### FR-C1-007: Item Detail Modal
**Priority:** HIGH  
**Description:** Customer taps item to see full details and add to basket

**Acceptance Criteria:**
- Modal displays: full-size image, name, description, price, allergens (icons + text), add-ons (optional), customization notes field (max 200 chars), quantity selector
- "Add to Basket (€X.XX)" button shows updated price with add-ons
- If item unavailable, button shows "Currently Unavailable" (disabled)
- Closing modal does not add item unless button pressed

**Business Rules:**
- Add-ons increase price shown in real-time
- Min quantity: 1, max quantity: 20
- Customization notes optional
- Modal accessible via keyboard (ESC to close)

**Technical Notes:**
- Item data from `menu_items` table
- Allergens stored as JSON array: `["nuts", "dairy", "gluten"]`
- Add-ons stored as JSON array:
```typescript
addons: Array<{
  name: string;
  price: number;
  selected?: boolean;
}>;
```

#### FR-C1-008: Search Functionality
**Priority:** MEDIUM  
**Description:** Customer can search menu items by name or description

**Acceptance Criteria:**
- Search bar at top of menu
- Real-time search results as user types (debounced 300ms)
- Matches item name, description, category
- Highlights matching text in results
- "No results found" shown if no matches
- Clear button (X) to reset search

**Business Rules:**
- Min 2 characters to trigger search
- Case-insensitive matching
- Search across translations (customer's language only)

**Technical Notes:**
- Client-side fuzzy search using Fuse.js
- Search index: `name + description + category`
- Highlight matches with `<mark>` tag

#### FR-C1-009: Category Filtering
**Priority:** MEDIUM  
**Description:** Customer can filter menu by category

**Acceptance Criteria:**
- Horizontal scrollable category chips at top
- Tap category → scroll to that section
- Active category highlighted
- "All" option to reset filter

**Business Rules:**
- Default: "All" selected
- Category chip count shows number of items in category
- Empty categories hidden

**Technical Notes:**
- Use Intersection Observer API to detect visible category
- Update active chip on scroll
- Smooth scroll behavior: `scrollIntoView({ behavior: 'smooth' })`

#### FR-C1-010: Dietary Filters
**Priority:** LOW  
**Description:** Customer can filter menu by dietary preferences

**Acceptance Criteria:**
- Filter button opens modal with options: Vegetarian, Vegan, Gluten-free, Dairy-free, Nut-free
- Multi-select filters (can select multiple)
- "Apply Filters" button
- Active filters shown as chips below search bar
- Tap chip to remove filter
- Filtered items count shown

**Business Rules:**
- Filters use AND logic (item must match all selected filters)
- Filters persist during session
- No filters = show all items

**Technical Notes:**
- Store filters in component state
- Filter logic:
```typescript
items.filter(item => {
  return filters.every(filter => item.dietary_tags.includes(filter));
});
```

---

### 4.3 Shared Basket (Core Feature)

#### FR-C1-011: Real-Time Basket Collaboration
**Priority:** CRITICAL ⭐  
**Description:** Multiple customers at same table share one basket in real-time

**Acceptance Criteria:**
- Customer A scans QR → sees empty basket
- Customer B scans same QR → sees same basket
- Customer A adds "Pasta" → Customer B sees "Pasta" within 500ms
- Customer B adds "Pizza" → Customer A sees "Pizza" within 500ms
- Each item shows: name, price, quantity, "Added by: You / Guest 2", timestamp
- Quantity changes propagate instantly
- Item removal propagates instantly
- Connection loss shows "Reconnecting..." banner

**Business Rules:**
- Max items per basket: 100 (prevent abuse)
- Max quantity per item: 20
- Basket expires after 3 hours of inactivity
- Conflicting edits: last write wins (with toast notification)

**Technical Notes:**
- Use Supabase Realtime subscriptions on `basket_items` table
- Filter by `table_session_id`
- Optimistic updates with rollback on failure
```typescript
{
  id: string;
  table_session_id: string;
  menu_item_id: string;
  quantity: number;
  added_by_customer_id: string | null;
  added_by_name: string; // "Guest 1", "Anna", etc.
  customization_notes: string | null;
  addons: Array<{name: string, price: number}>;
  created_at: timestamp;
}
```

#### FR-C1-012: Basket State Management
**Priority:** CRITICAL  
**Description:** Handle concurrent edits without data loss

**Acceptance Criteria:**
- Two customers edit quantity simultaneously → both changes persist (last write wins with toast)
- Customer removes item while another increases quantity → removal wins, toast shown
- Basket survives page refresh
- Basket survives network disconnection (offline-first)
- Reconnection syncs changes automatically

**Business Rules:**
- Optimistic UI updates immediately
- Server authoritative on conflicts
- Offline changes queued and synced on reconnect

**Technical Notes:**
- Use Postgres row-level security: `table_session_id = current_session_id`
- WebSocket reconnection with exponential backoff
- Conflict resolution: show toast "Basket updated by [name]" + re-sync
```typescript
// Realtime subscription
supabase
  .channel(`basket:${table_session_id}`)
  .on('postgres_changes', 
    { event: '*', schema: 'public', table: 'basket_items' },
    handleBasketChange
  )
  .subscribe();
```

#### FR-C1-013: Basket View
**Priority:** HIGH  
**Description:** Customer views and manages basket items

**Acceptance Criteria:**
- Basket icon shows badge with total item count
- Tap icon → slide-up sheet showing items
- Each item displays: image thumbnail, name, price, quantity controls (+/-), remove button (trash icon), added by indicator, customization notes
- Subtotal per person shown (if split bill active)
- Total with VAT breakdown (if vendor setting enabled)
- "Proceed to Checkout" button (disabled if basket empty)

**Business Rules:**
- Empty basket shows "Your basket is empty" with "Browse Menu" link
- Items grouped by person if split bill active
- Running total updates in real-time

**Technical Notes:**
- Bottom sheet component with drag-to-dismiss
- Quantity controls update via optimistic UI
- VAT calculation:
```typescript
const vatRate = item.vat_rate; // 10, 13, or 20
const priceExclVat = item.price / (1 + vatRate/100);
const vatAmount = item.price - priceExclVat;
```

#### FR-C1-014: Item Quantity Controls
**Priority:** HIGH  
**Description:** Customer adjusts item quantity in basket

**Acceptance Criteria:**
- "+/-" buttons next to each item
- Tap "-" when quantity = 1 → shows confirmation: "Remove item?"
- Quantity updates propagate to all connected devices
- Max quantity 20 (button disabled)
- Loading spinner during update

**Business Rules:**
- Min quantity: 1 (cannot go below, must remove instead)
- Max quantity: 20 per line item
- Multiple line items of same menu item allowed (different customizations)

**Technical Notes:**
- Debounce rapid clicks (500ms)
- Optimistic update with rollback on error
```typescript
const updateQuantity = async (itemId: string, delta: number) => {
  const newQty = currentQty + delta;
  if (newQty < 1) {
    confirmRemove(itemId);
    return;
  }
  // Optimistic update
  updateLocal(itemId, newQty);
  // Server update
  const { error } = await supabase
    .from('basket_items')
    .update({ quantity: newQty })
    .eq('id', itemId);
  if (error) rollback();
};
```

---

### 4.4 Checkout & Payment

#### FR-C1-015: Split Bill Options
**Priority:** CRITICAL ⭐  
**Description:** Customers can split payment responsibility

**Acceptance Criteria:**
- Option 1: **Split Equally** — divide total by number of people, each person pays equal share, show "Each person pays: €18.40"
- Option 2: **Split by Items** — each person selects items they're paying for, running total per person shown, unpaid items highlighted in red, cannot submit until all items assigned
- UI shows payment status: ✅ "Anna: Paid €23.50", ⏳ "You: €18.40 (unpaid)", ⏳ "Guest 3: €15.00 (unpaid)"
- Real-time updates when someone pays their share

**Business Rules:**
- Split only available for dine-in (not takeaway)
- At least 2 people required for split
- Each person pays their own tip (not split)
- Takeaway orders: full payment required upfront (no split)

**Technical Notes:**
- Store split in `split_payments` table:
```typescript
{
  id: string;
  order_id: string;
  customer_id: string;
  amount: number;
  status: 'pending' | 'paid' | 'failed';
  payment_method: 'card' | 'apple' | 'google' | 'cash';
  paid_at: timestamp | null;
}
```

#### FR-C1-016: Split Bill UI Flow
**Priority:** HIGH  
**Description:** User-friendly split bill interface

**Acceptance Criteria:**
- From basket, tap "Split Bill" button → modal opens
- Modal shows split options (equal / by items)
- **Equal Split:** Shows calculation "€55.20 ÷ 3 people = €18.40 per person", "Confirm Split" button
- **By Items:** Drag-and-drop items to person avatars, each person color-coded (blue, green, orange, purple), running subtotals per person, "Finalize Split" enabled when all items assigned
- After split confirmed, each customer sees "Your Share: €18.40", "Pay My Share" button, other customers' payment status updated in real-time

**Business Rules:**
- Number of people defaults to value from entry flow
- Can edit number of people before split
- Split locked once first payment made

**Technical Notes:**
- Use Supabase Realtime to broadcast payment status
- Subscribe to `split_payments` filtered by `order_id`
- Color palette: `#3B82F6, #10B981, #F59E0B, #8B5CF6`
```typescript
// Realtime payment status
supabase
  .channel(`split:${order_id}`)
  .on('postgres_changes',
    { event: 'UPDATE', schema: 'public', table: 'split_payments' },
    handlePaymentUpdate
  )
  .subscribe();
```

#### FR-C1-017: Payment Methods
**Priority:** CRITICAL  
**Description:** Customer pays via card, Apple Pay, Google Pay, or cash

**Acceptance Criteria:**
- Payment methods shown based on vendor settings:
  - 💳 Card (if `acceptCard = true`)
  - 🍎 Apple Pay (if `acceptApplePay = true`)
  - 🟢 Google Pay (if `acceptGooglePay = true`)
  - 💵 Cash (if `acceptCash = true` for dine-in OR `acceptCashTakeaway = true` for takeaway)
- Disabled methods shown greyed with tooltip: "Not available"
- **Card:** Stripe-hosted checkout, collects card number/expiry/CVV/name, 3D Secure (SCA) if required
- **Apple Pay / Google Pay:** Native payment sheet, one-tap payment
- **Cash:** "Pay Later" flag set, order submitted without payment, vendor must confirm payment received

**Business Rules:**
- **Takeaway:** Cash only available if `vendor.settings.acceptCashTakeaway = true`, prepayment required to prevent no-shows
- **Dine-in:** All methods available per vendor settings, cash = pay at table (order submitted immediately)
- Card processing via Stripe ensures PCI DSS compliance

**Technical Notes:**
- Stripe integration: use Stripe Checkout (hosted page)
- Webhook: `payment_intent.succeeded` → mark order as paid
```typescript
{
  id: string;
  order_id: string;
  amount: number;
  payment_method: 'card' | 'apple' | 'google' | 'cash';
  status: 'pending' | 'succeeded' | 'failed';
  stripe_payment_intent_id: string | null;
  paid_at: timestamp | null;
}
```

#### FR-C1-018: Tip Selection
**Priority:** MEDIUM  
**Description:** Customer adds optional tip

**Acceptance Criteria:**
- Tip options: 0%, 5%, 10%, 15%
- Custom tip: text input (€ amount)
- Tip shown separately in order summary
- Tip included in final payment amount
- Tip recorded separately from order total

**Business Rules:**
- Tip min: €0, max: €100
- Tip is optional (0% default)
- In split bill: each person tips individually (not split)

**Technical Notes:**
- Store in `payments.tip_amount`
- Invoice shows: Subtotal + Tip = Total Paid
```typescript
{
  subtotal: number;
  tip_amount: number;
  total: number; // subtotal + tip
}
```

#### FR-C1-019: Payment Processing
**Priority:** CRITICAL  
**Description:** Process payment securely and update order status

**Acceptance Criteria:**
- Card payment: redirect to Stripe Checkout → complete payment → redirect back to app
- Apple/Google Pay: show native payment sheet → process → show confirmation
- Cash: mark order as "unpaid" → submit to vendor → vendor confirms when received
- Success: show "Order Confirmed" screen with order number
- Failure: show error message with retry option

**Business Rules:**
- Card/Apple/Google Pay: order submitted only after payment succeeds
- Cash: order submitted immediately, marked as "payment pending"
- Failed payment: basket remains intact, customer can retry

**Technical Notes:**
- Stripe Checkout session creation:
```typescript
const session = await stripe.checkout.sessions.create({
  payment_method_types: ['card'],
  line_items: basketItems.map(item => ({
    price_data: {
      currency: 'eur',
      product_data: { name: item.name },
      unit_amount: item.price * 100, // cents
    },
    quantity: item.quantity,
  })),
  mode: 'payment',
  success_url: `${baseUrl}/order/{ORDER_ID}/success`,
  cancel_url: `${baseUrl}/order/{ORDER_ID}/cancel`,
});
```

---

### 4.5 Post-Order

#### FR-C1-020: Order Confirmation
**Priority:** HIGH  
**Description:** Customer sees order confirmation after successful payment

**Acceptance Criteria:**
- Confirmation screen displays: "Order Confirmed" header, order number (e.g., "#12345"), estimated ready time ("Ready in ~15 min"), payment status (Paid / Cash), total amount, "Track Order" button, "Download Receipt" button
- If takeaway: show pickup time and address
- If dine-in: show table number

**Business Rules:**
- Order number unique per vendor
- Estimated time based on vendor's average preparation time
- Receipt available immediately

**Technical Notes:**
- Order number format: `#{vendor_short_code}{sequential_number}`
- Store confirmation in localStorage for offline access
```typescript
{
  order_id: string;
  order_number: string;
  status: 'submitted' | 'preparing' | 'ready' | 'completed';
  estimated_ready_at: timestamp;
  payment_status: 'paid' | 'pending' | 'failed';
}
```

#### FR-C1-021: Order Status Tracking
**Priority:** HIGH  
**Description:** Customer sees real-time order status updates

**Acceptance Criteria:**
- Status flow: Submitted → Preparing → Ready → Completed
- UI shows: status icon + text, progress bar, estimated time countdown, "Call Waiter" button (dine-in only)
- Status updates in real-time (no manual refresh needed)
- Push notification when status changes (if enabled)

**Business Rules:**
- Vendor controls status updates
- Estimated time adjusts based on kitchen capacity
- Customer cannot modify status

**Technical Notes:**
- Subscribe to `orders` table via Realtime
- Filter: `order_id = currentOrderId`
```typescript
supabase
  .channel(`order:${order_id}`)
  .on('postgres_changes',
    { event: 'UPDATE', schema: 'public', table: 'orders' },
    handleStatusUpdate
  )
  .subscribe();

// Status enum
status: 'submitted' | 'preparing' | 'ready' | 'completed' | 'cancelled';
```

#### FR-C1-022: Call Waiter Button
**Priority:** MEDIUM  
**Description:** Customer requests waiter assistance (dine-in only)

**Acceptance Criteria:**
- Button shown on order tracking page (dine-in only)
- Tap → confirmation modal: "Request waiter assistance?"
- Confirm → notification sent to vendor dashboard
- Vendor sees: "Table 5 needs assistance" (with timestamp)
- Toast shown to customer: "Waiter notified! Someone will be with you shortly."

**Business Rules:**
- Rate limit: 1 request per 2 minutes (prevent spam)
- Only available for dine-in orders
- Vendor can acknowledge request

**Technical Notes:**
- Create record in `waiter_requests` table:
```typescript
{
  id: string;
  restaurant_id: string;
  table_number: string;
  order_id: string | null;
  status: 'pending' | 'acknowledged' | 'resolved';
  created_at: timestamp;
}
```
- Vendor dashboard subscribes via Realtime

#### FR-C1-023: Receipt Generation (VAT-Compliant)
**Priority:** CRITICAL  
**Description:** Generate Austrian VAT-compliant invoice

**Acceptance Criteria:**
- PDF generated within 5 seconds of payment
- Downloadable via "Download Receipt" button
- Sent to customer email (if provided)
- Invoice contains (Austrian legal requirements):
  - Invoice number (unique, sequential)
  - Date & time
  - Vendor name, address, VAT ID (UID)
  - Customer name (or "Guest")
  - Itemized list with: name, quantity, unit price (incl VAT), VAT rate (10%/13%/20%), line total
  - Subtotal per VAT rate
  - VAT amount per rate
  - Total including VAT
  - Tip (if any)
  - Grand total
  - Payment method
  - "Paid" or "Unpaid" status

**Business Rules:**
- Invoice number must be unique and sequential (no gaps)
- Must comply with § 11 UStG (Austrian VAT law)
- Stored for 7 years (Austrian legal requirement)
- Cannot be modified after generation

**Technical Notes:**
- Use library: `jsPDF` or server-side PDF generation
- Store in Supabase Storage: `invoices/{year}/{month}/{invoice_id}.pdf`
- Invoice number format: `{year}-{vendor_id}-{sequential}`
- Example: `2024-V123-00542`
```typescript
// VAT calculation per rate
const groupByVatRate = items.reduce((acc, item) => {
  const rate = item.vat_rate;
  if (!acc[rate]) acc[rate] = { subtotal: 0, vat: 0 };
  const priceExclVat = item.price / (1 + rate/100);
  const vatAmount = item.price - priceExclVat;
  acc[rate].subtotal += priceExclVat * item.quantity;
  acc[rate].vat += vatAmount * item.quantity;
  return acc;
}, {});
```

#### FR-C1-024: Leave Review (Post-Order)
**Priority:** LOW  
**Description:** Customer can leave restaurant review after order completion

**Acceptance Criteria:**
- "Leave Review" button shown on order confirmation (after order completed)
- Review form: rating (1-5 stars), text review (max 500 chars), optional food quality rating, optional service rating, submit button
- Success: "Thank you for your review!" toast
- Review appears in restaurant profile after admin moderation

**Business Rules:**
- One review per order
- Review visible only after admin approval
- Customer can edit review within 24 hours

**Technical Notes:**
- Store in `reviews` table:
```typescript
{
  id: string;
  restaurant_id: string;
  customer_id: string | null;
  order_id: string;
  rating: number; // 1-5
  review_text: string;
  food_rating: number | null;
  service_rating: number | null;
  status: 'pending' | 'approved' | 'rejected';
  created_at: timestamp;
}
```

#### FR-C1-025: Order History
**Priority:** LOW  
**Description:** Logged-in customers view past orders

**Acceptance Criteria:**
- List of past orders, sorted by date (newest first)
- Each order shows: restaurant name + logo, date & time, total amount, status (completed / cancelled), "View Details" link
- Detail view shows: full itemized list, payment method, receipt download link, "Reorder" button, "Leave Review" button (if not reviewed)

**Business Rules:**
- Only available to logged-in customers
- Guest orders not saved in history
- Pagination: 20 orders per page

**Technical Notes:**
- Query: `SELECT * FROM orders WHERE customer_id = :customerId ORDER BY created_at DESC`
- Infinite scroll or "Load More" button
- Cache recent orders client-side

---

### 4.6 Additional Features

#### FR-C1-026: Language Selector
**Priority:** HIGH  
**Description:** Customer can change interface language

**Acceptance Criteria:**
- Language selector in top navigation
- Supported languages: German (de), English (en), French (fr), Italian (it), Spanish (es), Arabic (ar), Turkish (tr), Croatian (hr), Serbian (sr), Polish (pl), Romanian (ro)
- Selection persists across sessions
- UI and menu items translated immediately
- Flag icons shown for each language

**Business Rules:**
- Default language: browser locale → German → English
- Menu items show vendor-provided translations
- Missing translations fall back to default language

**Technical Notes:**
- Use React Context for language state
- i18n library: `react-i18next`
- Store selection in localStorage
```typescript
{
  currentLanguage: string; // 'de', 'en', 'ar', etc.
  fallbackLanguage: 'de';
}
```

#### FR-C1-027: Accessibility Features
**Priority:** MEDIUM  
**Description:** Platform accessible to users with disabilities

**Acceptance Criteria:**
- Keyboard navigation (Tab, Enter, Esc)
- Screen reader support (ARIA labels)
- High contrast mode option
- Font size adjustments (small, medium, large)
- Focus indicators visible
- Color-blind friendly palette

**Business Rules:**
- WCAG 2.1 Level AA compliance
- All interactive elements keyboard accessible
- Images have alt text

**Technical Notes:**
- Use semantic HTML (`<nav>`, `<main>`, `<button>`)
- ARIA attributes: `aria-label`, `aria-describedby`, `role`
- Focus trap in modals
- Skip to content link

#### FR-C1-028: Responsive Design
**Priority:** HIGH  
**Description:** Platform works on all device sizes

**Acceptance Criteria:**
- Mobile-first design (primary use case)
- Tablet layout adjustments
- Desktop layout for larger screens
- Touch-friendly tap targets (min 44x44px)
- No horizontal scroll on any screen size

**Business Rules:**
- Breakpoints: Mobile (<768px), Tablet (768-1024px), Desktop (>1024px)
- Primary focus: mobile experience
- Desktop uses same components with layout adjustments

**Technical Notes:**
- Tailwind CSS breakpoints: `sm:`, `md:`, `lg:`, `xl:`
- Mobile viewport: `<meta name="viewport" content="width=device-width, initial-scale=1">`
- Use `clamp()` for fluid typography

#### FR-C1-029: Offline Support (Basic)
**Priority:** LOW  
**Description:** Basic functionality works offline

**Acceptance Criteria:**
- Menu cached for offline viewing
- Basket persists during connection loss
- "Offline" banner shown when disconnected
- Queued actions sync when reconnected
- Cannot submit order while offline

**Business Rules:**
- Menu cache expires after 24 hours
- Basket syncs on reconnect
- Payment requires connection

**Technical Notes:**
- Service Worker for offline caching
- IndexedDB for basket persistence
- Network status detection: `navigator.onLine`

---

## 5. VENDOR FEATURES

### 5.1 Registration & Onboarding

#### FR-V1-001: Vendor Registration
**Priority:** CRITICAL  
**Description:** Restaurant owner creates vendor account

**Acceptance Criteria:**
- Registration form collects: restaurant name (required), owner name (required), email (required, unique), phone (required), password (min 8 chars), address (street, city, postal code, country), VAT ID (UID) — required for Austrian businesses, business type (dropdown: Sole Proprietor / GmbH / OG / KG / AG)
- Submit → account created with `status = 'pending_approval'`
- Email sent to admin: "New vendor registration: {restaurant_name}"
- Vendor sees: "Thank you for registering! Your account is pending approval. We'll notify you within 24 hours."

**Business Rules:**
- VAT ID validation: Austrian UID format `ATU12345678` (9 digits after ATU)
- Email must be unique across vendors
- Phone validation: E.164 format

**Technical Notes:**
- Store in `vendors` table:
```typescript
{
  id: string;
  restaurant_name: string;
  owner_name: string;
  email: string;
  phone: string;
  address_street: string;
  address_city: string;
  address_postal_code: string;
  address_country: string;
  vat_id: string; // UID
  business_type: string;
  status: 'pending_approval' | 'active' | 'suspended';
  created_at: timestamp;
  approved_at: timestamp | null;
  approved_by_admin_id: string | null;
}
```

#### FR-V1-002: Subscription Selection (Onboarding)
**Priority:** CRITICAL  
**Description:** New vendor selects subscription plan

**Acceptance Criteria:**
- After registration approval, vendor prompted to select plan
- Three plans displayed:
  - **Basic** (€29/mo): 50 menu items, 10 tables, basic analytics
  - **Professional** (€79/mo): 200 menu items, 30 tables, loyalty & promotions, AI menu assistant
  - **Enterprise** (€199/mo): Unlimited items/tables, multi-location, AI insights, white-label, priority support
- Plan comparison table shows all features
- "Start Free Trial" (14 days) or "Subscribe Now"
- Stripe Checkout for payment

**Business Rules:**
- Free trial available once per vendor
- Trial expires after 14 days → vendor must subscribe or account suspended
- Can change plans anytime (proration handled by Stripe)

**Technical Notes:**
- Stripe Price IDs: `price_basic`, `price_professional`, `price_enterprise`
- Create Stripe Customer on registration
```typescript
const customer = await stripe.customers.create({
  email: vendor.email,
  name: vendor.restaurant_name,
  metadata: { vendor_id: vendor.id }
});
```

#### FR-V1-003: Subscription Checkout (Stripe)
**Priority:** CRITICAL  
**Description:** Vendor completes subscription payment via Stripe

**Acceptance Criteria:**
- Tap "Subscribe Now" → redirect to Stripe Checkout
- Stripe Checkout collects: payment method (card), billing details
- Success → redirect back to TAVLO dashboard
- Vendor status updated to `subscription_active = true`
- Email confirmation sent
- Access to dashboard granted

**Business Rules:**
- Subscription billed monthly
- First charge immediate (no trial if not selected)
- Failed payment → 3-day grace period → suspension

**Technical Notes:**
- Stripe Checkout session:
```typescript
const session = await stripe.checkout.sessions.create({
  customer: stripeCustomerId,
  mode: 'subscription',
  line_items: [{ price: selectedPriceId, quantity: 1 }],
  success_url: `${baseUrl}/vendor/onboarding/success`,
  cancel_url: `${baseUrl}/vendor/onboarding/plans`,
});
```
- Webhook: `checkout.session.completed` → activate subscription

#### FR-V1-004: Onboarding Checklist
**Priority:** HIGH  
**Description:** Guide vendor through initial setup steps

**Acceptance Criteria:**
- Checklist displayed on dashboard until completed:
  - ✅ Subscription active
  - ⏳ Restaurant profile (logo, description, hours)
  - ⏳ Add menu items (min 5)
  - ⏳ Generate QR codes (min 1 table)
  - ⏳ Test order
- Each item links to relevant setup page
- Progress bar shows completion percentage
- "Mark Complete" dismisses checklist

**Business Rules:**
- Checklist dismissible but returns if critical steps incomplete
- Can skip checklist and complete later
- Analytics track completion rate

**Technical Notes:**
- Store in `vendor_onboarding_state`:
```typescript
{
  vendor_id: string;
  checklist_complete: boolean;
  profile_complete: boolean;
  menu_complete: boolean;
  qr_generated: boolean;
  test_order_complete: boolean;
}
```

#### FR-V1-005: Restaurant Profile Setup
**Priority:** HIGH  
**Description:** Vendor completes restaurant profile

**Acceptance Criteria:**
- Profile form fields: restaurant name (editable), logo upload (max 2MB, JPG/PNG), cover photo (max 5MB, JPG/PNG), description (max 500 chars), cuisine type (dropdown: Italian, Austrian, Asian, etc.), public phone, public email, website URL (optional), business hours (per day of week)
- "Save Changes" button
- Changes reflected on customer-facing pages immediately
- Preview button shows customer view

**Business Rules:**
- Business hours required for operation
- Logo recommended but optional
- Cover photo enhances discovery (Phase 2)

**Technical Notes:**
- Images uploaded to Supabase Storage: `restaurant-assets/{vendor_id}/`
- Generate signed URLs for public access
- Business hours stored as JSON:
```json
{
  "monday": {"open": "11:00", "close": "22:00"},
  "tuesday": {"open": "11:00", "close": "22:00"},
  ...
  "sunday": {"open": null, "close": null} // closed
}
```

---

### 5.2 Menu Management

#### FR-V1-006: Menu Item Creation
**Priority:** CRITICAL  
**Description:** Vendor creates menu items

**Acceptance Criteria:**
- "Add Item" button → form with fields: name (required, max 100 chars), description (required, max 300 chars), price (required, EUR, min €0.10), VAT rate (dropdown: 10% / 13% / 20%), category (dropdown or create new), image upload (optional, max 5MB), allergens (multi-select: nuts, dairy, gluten, eggs, etc.), add-ons (name + price per add-on), available (checkbox, default: true)
- "Save Item" → item added to menu
- Item visible to customers immediately
- Success toast: "Item added successfully"

**Business Rules:**
- Price includes VAT (Austrian standard)
- VAT rate selection:
  - 10% VAT: Non-alcoholic beverages
  - 13% VAT: Most food items
  - 20% VAT: Alcoholic beverages
- Image optimized to 800x800px on upload
- Categories can be created on-the-fly

**Technical Notes:**
- Store in `menu_items` table:
```typescript
{
  id: string;
  vendor_id: string;
  name: string;
  description: string;
  price: number; // includes VAT, in euros
  vat_rate: number; // 10, 13, or 20
  category_id: string;
  image_url: string | null;
  allergens: string[]; // ["nuts", "dairy"]
  addons: Array<{name: string, price: number}>;
  available: boolean;
  sort_order: number;
  created_at: timestamp;
  updated_at: timestamp;
}
```

#### FR-V1-007: Menu Item Editing
**Priority:** HIGH  
**Description:** Vendor edits existing menu items

**Acceptance Criteria:**
- Click item in menu list → edit form (same fields as creation)
- Change any field → "Save Changes"
- Changes reflected immediately on customer-facing menu
- "Delete Item" button (with confirmation modal)
- Warning if item in active orders: "Cannot delete - item in active orders"

**Business Rules:**
- Cannot delete item if part of active order
- Can mark as unavailable instead
- Price changes affect new orders only (not existing)

**Technical Notes:**
- Update query: `UPDATE menu_items SET ... WHERE id = :id AND vendor_id = :vendorId`
- Soft delete: set `deleted_at` timestamp instead of hard delete
- Active orders check before delete:
```sql
SELECT COUNT(*) FROM order_items 
WHERE menu_item_id = :itemId 
AND order_status IN ('submitted', 'preparing');
```

#### FR-V1-008: Menu Categories Management
**Priority:** MEDIUM  
**Description:** Vendor organizes menu into categories

**Acceptance Criteria:**
- Categories page shows list: Starters, Mains, Desserts, Drinks (default)
- "Add Category" button → modal with name field
- Drag-and-drop to reorder categories
- Edit category name
- Delete category (if empty or move items to another category)

**Business Rules:**
- Cannot delete category with items (must reassign first)
- Default categories cannot be deleted
- Categories sorted by `sort_order`

**Technical Notes:**
- Categories stored in `menu_categories`:
```typescript
{
  id: string;
  vendor_id: string;
  name: string;
  sort_order: number;
  is_default: boolean;
  created_at: timestamp;
}
```
- Drag-and-drop updates `sort_order` via batch update

#### FR-V1-009: Item Availability Toggle
**Priority:** HIGH  
**Description:** Vendor marks items as unavailable (sold out)

**Acceptance Criteria:**
- Toggle switch next to each item: "Available"
- When toggled off: item shown greyed in vendor menu, shown as "Unavailable" on customer menu, cannot be added to basket
- When toggled on: item becomes available again
- Bulk toggle: select multiple items → "Mark Unavailable"

**Business Rules:**
- Unavailable items remain in menu (not deleted)
- Items in baskets before toggle remain (with warning)
- Analytics track availability changes

**Technical Notes:**
- Update: `UPDATE menu_items SET available = false WHERE id = :id`
- Customer menu query: `SELECT * FROM menu_items WHERE vendor_id = :id AND (available = true OR show_unavailable = true)`

#### FR-V1-010: Bulk Menu Upload (CSV)
**Priority:** LOW  
**Description:** Vendor uploads menu via CSV file

**Acceptance Criteria:**
- "Import Menu" button → file upload
- CSV format: name, description, price, vat_rate, category, allergens (comma-separated)
- Download CSV template link
- Preview import before confirming
- Error handling for invalid rows
- Success: "X items imported successfully"

**Business Rules:**
- CSV max 500 rows
- Duplicate names create separate items
- Invalid rows skipped with error report

**Technical Notes:**
- Parse CSV client-side using `papaparse`
- Validate each row before database insert
- Batch insert for performance

#### FR-V1-011: Menu Translations (Basic)
**Priority:** MEDIUM  
**Description:** Vendor provides menu translations

**Acceptance Criteria:**
- Edit item → "Translations" tab
- Fields for each supported language: name, description
- If translation missing, fallback to default language
- "Auto-translate" button uses AI (Phase 2 feature)

**Business Rules:**
- Default language: vendor's language (usually German)
- Fallback: German → English → default
- Translations optional

**Technical Notes:**
- Store in `menu_item_translations`:
```typescript
{
  menu_item_id: string;
  language_code: string; // 'de', 'en', 'ar', etc.
  name: string;
  description: string;
}
```

---

### 5.3 Order Management

#### FR-V1-012: Orders Dashboard
**Priority:** CRITICAL  
**Description:** Vendor views and manages incoming orders

**Acceptance Criteria:**
- Real-time order list, grouped by status: New (auto-refresh), Preparing, Ready, Completed
- Each order card shows: order number, table number / "Takeaway", order time, estimated ready time, item count, total amount, payment status (Paid / Cash / Pending), customer name (if provided)
- Tap order → detail view
- Audio/visual notification for new orders
- Filter by: date, order type (dine-in/takeaway), payment status

**Business Rules:**
- Orders sorted by time (oldest first within each status)
- Auto-refresh every 5 seconds
- New orders highlighted for 30 seconds

**Technical Notes:**
- Subscribe to `orders` table via Realtime:
```typescript
supabase
  .channel(`orders:${vendor_id}`)
  .on('postgres_changes',
    { event: 'INSERT', schema: 'public', table: 'orders' },
    handleNewOrder
  )
  .subscribe();
```
- Play notification sound on new order
- Show browser notification if tab not active

#### FR-V1-013: Order Detail View
**Priority:** HIGH  
**Description:** Vendor views complete order details

**Acceptance Criteria:**
- Order detail modal shows: order number, customer name/phone, table number / pickup time, order time, items list (name, quantity, price, customizations, add-ons), subtotal, VAT breakdown, tip, total, payment method, payment status
- Action buttons: "Accept Order" (if new), "Mark Preparing", "Mark Ready", "Mark Completed", "Print Receipt", "Cancel Order" (with reason)

**Business Rules:**
- Status can only move forward (cannot go back)
- Cancel requires reason (shown to customer)
- Print receipt triggers browser print dialog

**Technical Notes:**
- Order detail query joins: `orders`, `order_items`, `menu_items`, `payments`
- Status update triggers Realtime broadcast to customer
```typescript
const updateStatus = async (orderId: string, newStatus: string) => {
  const { error } = await supabase
    .from('orders')
    .update({ status: newStatus, updated_at: new Date() })
    .eq('id', orderId);
  // Customer receives update via Realtime
};
```

#### FR-V1-014: Order Status Updates
**Priority:** CRITICAL  
**Description:** Vendor updates order status through workflow

**Acceptance Criteria:**
- New order → "Accept" button → status: "preparing"
- Preparing → "Mark Ready" → status: "ready", customer notified
- Ready → "Mark Completed" → status: "completed", order archived
- Each status change shows confirmation toast
- Estimated time editable at each stage

**Business Rules:**
- Cannot skip statuses (must go: submitted → preparing → ready → completed)
- Status changes logged with timestamp + vendor user
- Customer receives real-time status updates

**Technical Notes:**
- Status transitions enforced in database:
```sql
CREATE TYPE order_status AS ENUM (
  'submitted', 'preparing', 'ready', 'completed', 'cancelled'
);
```
- Audit log:
```typescript
{
  order_id: string;
  old_status: string;
  new_status: string;
  changed_by: string;
  changed_at: timestamp;
}
```

#### FR-V1-015: Cash Payment Confirmation
**Priority:** HIGH  
**Description:** Vendor confirms when cash payment received

**Acceptance Criteria:**
- Orders with `payment_method = 'cash'` show "Payment Pending" badge
- "Confirm Cash Received" button on order detail
- Tap button → confirmation modal: "Confirm €25.50 cash received?"
- Confirm → `payment_status = 'paid'`, customer notified
- Order moves to appropriate status

**Business Rules:**
- Cash confirmation required before marking order complete
- Vendor can mark unpaid if customer leaves without paying
- Payment status filters show unpaid cash orders

**Technical Notes:**
- Update payment record:
```typescript
await supabase
  .from('payments')
  .update({ status: 'succeeded', paid_at: new Date() })
  .eq('order_id', orderId)
  .eq('payment_method', 'cash');
```
- Send customer notification via email/SMS

#### FR-V1-016: Order Filters
**Priority:** MEDIUM  
**Description:** Vendor filters orders by various criteria

**Acceptance Criteria:**
- Filter dropdowns: date range (today, yesterday, last 7 days, last 30 days, custom), order type (all, dine-in, takeaway), payment status (all, paid, pending, failed), payment method (all, card, cash, Apple Pay, Google Pay)
- Active filters shown as chips
- "Clear All Filters" button
- Filter state persists across page refreshes

**Business Rules:**
- Default filter: today's orders
- Filters use AND logic (all must match)
- Filtered count shown in UI

**Technical Notes:**
- Build dynamic SQL query based on filters
- Store filter state in URL query params for sharing
```typescript
const buildQuery = (filters) => {
  let query = supabase.from('orders').select('*');
  if (filters.date) query = query.gte('created_at', filters.date);
  if (filters.type) query = query.eq('order_type', filters.type);
  if (filters.paymentStatus) query = query.eq('payment_status', filters.paymentStatus);
  return query;
};
```

#### FR-V1-017: Order Search
**Priority:** MEDIUM  
**Description:** Vendor searches orders by customer name, order number, or phone

**Acceptance Criteria:**
- Search bar at top of orders list
- Real-time search (debounced 300ms)
- Matches: order number, customer name, customer phone, table number
- Highlights matching text in results
- "No results found" if no matches

**Business Rules:**
- Min 2 characters to trigger search
- Case-insensitive
- Search across all orders (not just filtered)

**Technical Notes:**
- Full-text search using Postgres:
```sql
SELECT * FROM orders
WHERE 
  order_number ILIKE '%{query}%' OR
  customer_name ILIKE '%{query}%' OR
  customer_phone ILIKE '%{query}%' OR
  table_number::text ILIKE '%{query}%';
```

#### FR-V1-018: Print Order Ticket
**Priority:** MEDIUM  
**Description:** Vendor prints order ticket for kitchen

**Acceptance Criteria:**
- "Print" button on order detail
- Opens browser print dialog
- Print layout optimized for receipt printer (80mm)
- Shows: order number, table/takeaway, time, items (quantity + name + customizations), special instructions, total

**Business Rules:**
- Print format: plain text, high contrast
- Auto-print option in settings
- Print history logged

**Technical Notes:**
- Use CSS `@media print` for print styles
- Monospace font for alignment
- Option to send to thermal printer via WebUSB API (Phase 2)

---

### 5.4 QR Code Management

#### FR-V1-019: Table QR Code Generation
**Priority:** CRITICAL  
**Description:** Vendor generates QR codes for dine-in tables

**Acceptance Criteria:**
- "QR Codes" page → "Add Table" button
- Modal: table number/name (required), number of seats (optional), notes (optional)
- "Generate QR" → creates QR code
- QR format: `https://tavlo.app/r/{vendor_id}/t/{table_number}`
- Display options: download PNG, download PDF (for printing), view full-screen
- Bulk generation: enter range (e.g., Tables 1-10)

**Business Rules:**
- Table numbers must be unique per vendor
- QR codes never expire
- Can regenerate QR if lost (same URL)

**Technical Notes:**
- Use library: `qrcode.react` for generation
- Store in `tables`:
```typescript
{
  id: string;
  vendor_id: string;
  table_number: string;
  seats: number | null;
  qr_code_url: string;
  notes: string | null;
  active: boolean;
  created_at: timestamp;
}
```

#### FR-V1-020: Takeaway QR Code
**Priority:** HIGH  
**Description:** Vendor generates takeaway QR code

**Acceptance Criteria:**
- Single takeaway QR per vendor
- QR format: `https://tavlo.app/r/{vendor_id}/takeaway`
- Display options: download PNG, download PDF, share link
- QR can be placed: in-store, on website, on social media

**Business Rules:**
- One takeaway QR per vendor
- Same QR for all takeaway orders
- Customers select pickup time during checkout

**Technical Notes:**
- Generated on vendor registration
- Stored in vendor settings
- Can be regenerated if needed

#### FR-V1-021: QR Code Customization
**Priority:** LOW  
**Description:** Vendor customizes QR code appearance

**Acceptance Criteria:**
- Customization options: logo in center (upload), color scheme, border style, text label (e.g., "Table 5")
- Preview live updates
- "Reset to Default" button
- Apply to: single QR, all table QRs, takeaway QR

**Business Rules:**
- Logo max 100KB
- QR must remain scannable (test after customization)
- Professional plan feature (Phase 1 basic only)

**Technical Notes:**
- QR customization via `qrcode-generator` with options
- Test scannability programmatically before saving

#### FR-V1-022: QR Analytics (Basic)
**Priority:** LOW  
**Description:** Vendor sees QR code scan statistics

**Acceptance Criteria:**
- Each QR shows: total scans, scans today, last scan time
- Table QRs: conversion rate (scans → orders)
- Takeaway QR: conversion rate
- No personal data shown (GDPR-compliant)

**Business Rules:**
- Anonymous scan tracking only
- Data retained 90 days
- Aggregated statistics only

**Technical Notes:**
- Track scan event:
```typescript
{
  table_id: string;
  scanned_at: timestamp;
  order_created: boolean;
  // No customer_id (anonymous)
}
```

---

### 5.5 Analytics & Reporting

#### FR-V1-023: Dashboard Overview
**Priority:** HIGH  
**Description:** Vendor sees key metrics on dashboard home

**Acceptance Criteria:**
- Metrics cards: today's revenue, today's orders, pending orders (count), average order value
- Chart: revenue over last 7 days (line chart)
- Chart: top-selling items (bar chart, top 5)
- Quick links: view orders, manage menu, settings

**Business Rules:**
- Data updates in real-time
- Revenue shown includes VAT
- Metrics reflect vendor's timezone

**Technical Notes:**
- Aggregate queries for metrics
```sql
-- Today's revenue
SELECT SUM(total) FROM orders 
WHERE vendor_id = :id 
AND DATE(created_at) = CURRENT_DATE
AND status != 'cancelled';

-- Top-selling items
SELECT menu_item_id, SUM(quantity) as total_qty
FROM order_items
WHERE vendor_id = :id
GROUP BY menu_item_id
ORDER BY total_qty DESC
LIMIT 5;
```

#### FR-V1-024: Revenue Report
**Priority:** MEDIUM  
**Description:** Vendor views detailed revenue breakdown

**Acceptance Criteria:**
- Date range selector (today, week, month, custom)
- Metrics: total revenue, total orders, average order value, revenue by payment method
- Chart: daily revenue (bar chart)
- VAT breakdown by rate (10%, 13%, 20%)
- Export CSV button

**Business Rules:**
- Revenue includes VAT
- Cancelled orders excluded
- Refunds deducted (Phase 2)

**Technical Notes:**
- VAT breakdown query:
```sql
SELECT 
  vat_rate,
  SUM(quantity * price) as subtotal,
  SUM(quantity * price * vat_rate / (100 + vat_rate)) as vat_amount
FROM order_items
WHERE vendor_id = :id
AND created_at BETWEEN :start AND :end
GROUP BY vat_rate;
```

#### FR-V1-025: Popular Items Report
**Priority:** MEDIUM  
**Description:** Vendor sees which items sell best

**Acceptance Criteria:**
- Table showing: item name, category, quantity sold, revenue, percentage of total
- Sort by: quantity, revenue
- Date range filter
- Chart: top 10 items (horizontal bar chart)

**Business Rules:**
- Includes only completed orders
- Items sorted by quantity by default
- Unavailable items included (historical data)

**Technical Notes:**
- Query with aggregation:
```sql
SELECT 
  mi.name,
  mi.category,
  SUM(oi.quantity) as qty_sold,
  SUM(oi.quantity * oi.price) as revenue
FROM order_items oi
JOIN menu_items mi ON oi.menu_item_id = mi.id
WHERE mi.vendor_id = :id
AND oi.created_at BETWEEN :start AND :end
GROUP BY mi.id
ORDER BY qty_sold DESC;
```

#### FR-V1-026: Order Volume Report
**Priority:** LOW  
**Description:** Vendor sees order trends over time

**Acceptance Criteria:**
- Line chart: orders per day/week/month
- Breakdown: dine-in vs takeaway
- Peak hours analysis (hour-by-hour)
- Average preparation time

**Business Rules:**
- Data shown for last 90 days max
- Professional plan feature
- Hourly breakdown for current week only

**Technical Notes:**
- Timeseries aggregation:
```sql
SELECT 
  DATE_TRUNC('day', created_at) as date,
  COUNT(*) as order_count,
  AVG(EXTRACT(EPOCH FROM (completed_at - created_at))/60) as avg_prep_time
FROM orders
WHERE vendor_id = :id
GROUP BY date
ORDER BY date;
```

---

### 5.6 Reviews Management

#### FR-V1-027: View Reviews
**Priority:** MEDIUM  
**Description:** Vendor views customer reviews

**Acceptance Criteria:**
- List of all reviews (approved, pending, rejected)
- Each review shows: rating (stars), customer name, review text, date, order number (link), status badge
- Filter by: rating (1-5 stars), status, date range
- Sort by: date, rating

**Business Rules:**
- Only approved reviews shown to customers
- Vendor sees all reviews (including rejected with reason)
- Cannot delete reviews (only admin can)

**Technical Notes:**
- Query:
```sql
SELECT * FROM reviews
WHERE restaurant_id = :id
ORDER BY created_at DESC;
```

#### FR-V1-028: Respond to Reviews
**Priority:** MEDIUM  
**Description:** Vendor can respond to customer reviews

**Acceptance Criteria:**
- "Respond" button on each review
- Modal: text area for response (max 500 chars)
- Submit → response shown below review
- Customer receives notification
- Can edit response within 24 hours

**Business Rules:**
- One response per review
- Response requires approval (reviewed review only)
- Professional tone enforced (AI suggestion Phase 2)

**Technical Notes:**
- Store in `review_responses`:
```typescript
{
  id: string;
  review_id: string;
  vendor_id: string;
  response_text: string;
  created_at: timestamp;
  edited: boolean;
}
```

#### FR-V1-029: Review Summary
**Priority:** LOW  
**Description:** Vendor sees aggregated review statistics

**Acceptance Criteria:**
- Overall rating (average of all approved reviews)
- Rating distribution (bar chart: 5★, 4★, 3★, 2★, 1★)
- Total review count
- Recent trends (improving / declining)

**Business Rules:**
- Only approved reviews counted
- Min 5 reviews to show statistics
- Updated daily

**Technical Notes:**
- Aggregate query:
```sql
SELECT 
  AVG(rating) as avg_rating,
  COUNT(*) as total_reviews,
  COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
  COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
  -- ... etc
FROM reviews
WHERE restaurant_id = :id
AND status = 'approved';
```

---

### 5.7 Settings

#### FR-V1-030: Business Information
**Priority:** HIGH  
**Description:** Vendor manages restaurant business details

**Acceptance Criteria:**
- Form fields: restaurant name, address, phone, email, website, VAT ID (read-only), business hours, cuisine type, description
- "Save Changes" button
- Validation errors shown inline
- Success toast on save

**Business Rules:**
- VAT ID cannot be changed (contact admin)
- Business hours required
- Changes reflected immediately on customer pages

**Technical Notes:**
- Update `vendors` table
- Validate VAT ID format on backend
- Business hours JSON format (same as registration)

#### FR-V1-031: Payment Settings
**Priority:** CRITICAL  
**Description:** Vendor configures accepted payment methods

**Acceptance Criteria:**
- Checkboxes: Accept Card Payments (Stripe), Accept Apple Pay, Accept Google Pay, Accept Cash (Dine-in), Accept Cash (Takeaway)
- Stripe Connect button (if not connected)
- Bank account info for payouts (Stripe handles)
- Payout schedule (daily, weekly, monthly)

**Business Rules:**
- At least one payment method required
- Stripe Connect required for card/Apple/Google Pay
- Cash always available unless explicitly disabled

**Technical Notes:**
- Store in `vendor_settings`:
```typescript
{
  vendor_id: string;
  accept_card: boolean;
  accept_apple_pay: boolean;
  accept_google_pay: boolean;
  accept_cash: boolean;
  accept_cash_takeaway: boolean;
  stripe_account_id: string | null;
}
```

#### FR-V1-032: Stripe Connect Integration
**Priority:** CRITICAL  
**Description:** Vendor connects Stripe account for payouts

**Acceptance Criteria:**
- "Connect Stripe" button → redirects to Stripe Connect OAuth
- Vendor authorizes TAVLO to process payments
- Success → returns to TAVLO settings
- Shows: "Connected" status, account ID, "Disconnect" button
- Payouts go directly to vendor's bank account

**Business Rules:**
- Stripe Connect required to accept card payments
- TAVLO takes platform fee (e.g., 2% + €0.30 per transaction)
- Vendor receives 98% of payment

**Technical Notes:**
- Stripe Connect Express accounts
```typescript
const accountLink = await stripe.accountLinks.create({
  account: stripeAccountId,
  refresh_url: `${baseUrl}/vendor/settings/stripe/refresh`,
  return_url: `${baseUrl}/vendor/settings/stripe/success`,
  type: 'account_onboarding',
});
// Redirect to accountLink.url
```

#### FR-V1-033: VAT Settings
**Priority:** HIGH  
**Description:** Vendor configures VAT display and rates

**Acceptance Criteria:**
- Toggle: "Show VAT breakdown on receipts" (default: ON)
- Toggle: "Prices include VAT" (default: ON, read-only in Austria)
- Default VAT rates shown (10%, 13%, 20%)
- Info text explaining Austrian VAT requirements

**Business Rules:**
- VAT breakdown required by Austrian law
- Prices must include VAT (no option to exclude)
- Cannot override standard VAT rates

**Technical Notes:**
- Store in `vendor_settings`:
```typescript
{
  show_vat_breakdown: boolean; // default: true
  vat_included_in_prices: boolean; // always true for Austria
}
```

#### FR-V1-034: Notification Settings
**Priority:** MEDIUM  
**Description:** Vendor configures notification preferences

**Acceptance Criteria:**
- Checkboxes: Email on new order, SMS on new order, Email on payment received, Email on review received, Browser notifications (push)
- Test notification button
- Notification sound selector
- Quiet hours (disable during specific times)

**Business Rules:**
- Email notifications always enabled (cannot fully disable)
- SMS requires phone number
- Push notifications require browser permission

**Technical Notes:**
- Store in `vendor_notification_settings`:
```typescript
{
  vendor_id: string;
  email_new_order: boolean;
  sms_new_order: boolean;
  email_payment: boolean;
  email_review: boolean;
  push_enabled: boolean;
  notification_sound: string;
  quiet_hours_start: string | null; // "22:00"
  quiet_hours_end: string | null; // "08:00"
}
```

#### FR-V1-035: Appearance Settings
**Priority:** LOW  
**Description:** Vendor customizes customer-facing branding

**Acceptance Criteria:**
- Color picker: primary color (used for buttons, links)
- Logo upload (used in menu header)
- Cover image upload (used in restaurant page)
- Preview button shows customer view
- "Reset to Default" button

**Business Rules:**
- Professional plan feature (Basic plan uses default branding)
- Colors must meet WCAG AA contrast requirements
- Logo max 2MB, cover max 5MB

**Technical Notes:**
- Store in `vendor_branding`:
```typescript
{
  vendor_id: string;
  primary_color: string; // hex code
  logo_url: string | null;
  cover_image_url: string | null;
}
```
- Apply CSS custom properties on customer pages

#### FR-V1-036: Language & Localization
**Priority:** MEDIUM  
**Description:** Vendor sets default language and region settings

**Acceptance Criteria:**
- Dropdown: Default language (German, English, etc.)
- Dropdown: Currency (EUR, USD, GBP, etc.)
- Dropdown: Timezone (for analytics)
- Dropdown: Date format (DD/MM/YYYY, MM/DD/YYYY)

**Business Rules:**
- Default language used for vendor dashboard
- Currency affects display only (all payments in EUR via Stripe)
- Timezone affects order timestamps and analytics

**Technical Notes:**
- Store in `vendor_settings`:
```typescript
{
  default_language: string; // 'de', 'en', etc.
  currency: string; // 'EUR'
  timezone: string; // 'Europe/Vienna'
  date_format: string; // 'DD/MM/YYYY'
}
```

#### FR-V1-037: Account Security
**Priority:** HIGH  
**Description:** Vendor manages account security settings

**Acceptance Criteria:**
- Change password form (current password, new password, confirm)
- Two-factor authentication toggle (SMS or authenticator app)
- Active sessions list (device, location, last active)
- "Log out all devices" button
- Download account data (GDPR)

**Business Rules:**
- Password change requires current password
- 2FA recommended but optional
- Account data export includes all vendor data

**Technical Notes:**
- Use Supabase Auth for password change
- 2FA via TOTP (Time-based One-Time Password)
- GDPR export: generate ZIP with all data

#### FR-V1-038: Subscription Management
**Priority:** HIGH  
**Description:** Vendor manages subscription and billing

**Acceptance Criteria:**
- Current plan displayed (Basic / Professional / Enterprise)
- Plan features comparison
- "Upgrade Plan" / "Change Plan" button → redirects to Stripe billing portal
- Billing history (invoices, payment method)
- Cancel subscription button (with retention flow)

**Business Rules:**
- Plan changes take effect immediately (prorated)
- Cancellation: access until period end, then suspended
- Failed payment: 3-day grace period, then suspension

**Technical Notes:**
- Stripe Customer Portal for self-service billing:
```typescript
const session = await stripe.billingPortal.sessions.create({
  customer: stripeCustomerId,
  return_url: `${baseUrl}/vendor/settings/subscription`,
});
// Redirect to session.url
```

---

## 6. ADMIN FEATURES

### 6.1 Vendor Management

#### FR-A1-001: Vendor Approval/Rejection
**Priority:** HIGH  
**Description:** Admin reviews pending vendor registrations

**Acceptance Criteria:**
- Dashboard shows list of vendors with `status = 'pending_approval'`
- Each vendor card shows: restaurant name, owner name, email, phone, address, VAT ID (UID), business type, registration date
- "Approve" button → status = 'active', email sent to vendor ("Your account has been approved")
- "Reject" button → modal: "Reason for rejection" (required) → status = 'rejected', email sent with reason
- Approved vendor can log in and access dashboard

**Business Rules:**
- Must verify VAT ID is valid (manual check or API integration)
- Check for duplicate VAT IDs (prevent fraud)
- Admin can leave internal notes (not visible to vendor)

**Technical Notes:**
- Update: `UPDATE vendors SET status = 'active', approved_at = NOW(), approved_by_admin_id = :adminId WHERE id = :vendorId`
- Email trigger: Supabase Edge Function
```typescript
// Admin approval action
const approveVendor = async (vendorId: string, adminId: string) => {
  const { error } = await supabase
    .from('vendors')
    .update({ 
      status: 'active', 
      approved_at: new Date(),
      approved_by_admin_id: adminId 
    })
    .eq('id', vendorId);
  
  // Send email via Edge Function
  await sendEmail({
    to: vendor.email,
    subject: 'Your TAVLO account has been approved!',
    template: 'vendor_approved',
    data: { restaurant_name: vendor.restaurant_name }
  });
};
```

#### FR-A1-002: Vendor Suspension
**Priority:** MEDIUM  
**Description:** Admin suspends vendor for violations

**Acceptance Criteria:**
- "Suspend Vendor" button on vendor detail page
- Modal: "Reason for suspension" (required)
- Confirm → status = 'suspended', vendor cannot log in
- Vendor's QR codes stop working (show: "This restaurant is temporarily unavailable")
- Email sent to vendor with reason

**Business Rules:**
- Grace period: 3 days after payment failure before suspension
- Data retained 30 days after suspension, then soft-deleted
- Reactivation available anytime (admin can reverse)

**Technical Notes:**
- Update: `UPDATE vendors SET status = 'suspended', suspended_at = NOW()`
- Customer-facing QR error: "Restaurant temporarily unavailable"
- Vendor dashboard blocks vendor access on QR scan

#### FR-A1-003: User & Vendor Management
**Priority:** MEDIUM  
**Description:** Admin views all users and vendors

**Acceptance Criteria:**
- "Users" page: list of customers with filters (date, active/inactive)
- "Vendors" page: list of vendors with filters (status, active/inactive)
- Search by name, email, phone
- Click user/vendor → detail view with: profile info, order history, activity log
- "Delete User/Vendor" button (with confirmation + reason)

**Business Rules:**
- Soft delete: set `deleted_at` timestamp
- GDPR: User deletion must remove all PII (keep order data anonymized)
- Admin cannot access customer payment details (PCI compliance)

**Technical Notes:**
- Soft delete: `UPDATE customers SET deleted_at = NOW() WHERE id = :id`
- Anonymize on delete:
```sql
UPDATE orders SET 
  customer_name = 'Deleted User',
  customer_email = NULL,
  customer_phone = NULL
WHERE customer_id = :id;
```

#### FR-A1-004: Vendor Details Modal
**Priority:** LOW  
**Description:** Admin views comprehensive vendor information

**Acceptance Criteria:**
- Modal shows: basic info (name, address, VAT ID), owner details (name, email, phone), subscription plan, registration date, approval date, total orders, total revenue, recent activity
- Tabs: Overview, Orders, Reviews, Settings, Audit Log
- Quick actions: Edit, Suspend, Send Message

**Business Rules:**
- Admin can view but not modify vendor menu/prices
- Can see aggregate revenue (not individual payments)
- Cannot access Stripe payout details

**Technical Notes:**
- Aggregate queries for metrics
- Activity log from audit table

---

### 6.2 Oversight & Monitoring

#### FR-A1-005: Platform Dashboard
**Priority:** HIGH  
**Description:** Admin views overall platform health metrics

**Acceptance Criteria:**
- Metrics cards: total vendors (active), total customers, today's orders, today's revenue, active subscriptions
- Charts: orders over time (line chart), revenue over time, vendor signups over time
- Recent activity feed (vendor registrations, new orders, reviews)
- Alerts: failed payments, suspended vendors, flagged reviews

**Business Rules:**
- Revenue shown is platform revenue (fees), not total transaction volume
- Real-time updates every 30 seconds
- Data accessible to admins only (role-based access)

**Technical Notes:**
- Aggregate queries cached for performance
- Real-time updates via Supabase Realtime
```sql
-- Platform metrics
SELECT 
  (SELECT COUNT(*) FROM vendors WHERE status = 'active') as active_vendors,
  (SELECT COUNT(*) FROM customers) as total_customers,
  (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURRENT_DATE) as today_orders,
  (SELECT SUM(platform_fee) FROM payments WHERE DATE(created_at) = CURRENT_DATE) as today_revenue;
```

#### FR-A1-006: Subscription Overview
**Priority:** MEDIUM  
**Description:** Admin monitors vendor subscriptions

**Acceptance Criteria:**
- List of all vendors with subscription details: vendor name, plan (Basic/Pro/Enterprise), status (active/cancelled/expired), MRR (Monthly Recurring Revenue), next billing date
- Filter by: plan, status
- Metrics: total MRR, churn rate, new subscriptions this month
- "View in Stripe" link for each subscription

**Business Rules:**
- Admin cannot modify subscriptions (vendor self-service via Stripe)
- Can view subscription history
- Cancellations tracked for analytics

**Technical Notes:**
- Sync from Stripe via webhooks:
```typescript
// Stripe webhook: customer.subscription.updated
{
  vendor_id: string;
  stripe_subscription_id: string;
  plan: 'basic' | 'professional' | 'enterprise';
  status: 'active' | 'canceled' | 'past_due';
  current_period_end: timestamp;
  mrr: number;
}
```

#### FR-A1-007: Invoice Management
**Priority:** MEDIUM  
**Description:** Admin views and manages invoices

**Acceptance Criteria:**
- List of all invoices (customer orders + vendor subscriptions)
- Each shows: invoice number, vendor/customer, date, amount, VAT amount, status (paid/unpaid)
- Search by invoice number, vendor, customer
- Download invoice PDF
- "Mark as Paid" for manual corrections (admin only)

**Business Rules:**
- Invoices immutable once generated (Austrian legal requirement)
- Admin can regenerate only if error (with audit trail)
- Invoices stored 7 years (legal requirement)

**Technical Notes:**
- Query from `invoices` table
- PDF stored in Supabase Storage
- Audit log any manual changes

---

### 6.3 Moderation

#### FR-A1-008: Review Moderation
**Priority:** HIGH  
**Description:** Admin moderates customer reviews

**Acceptance Criteria:**
- List of reviews with `status = 'pending'`
- Each review shows: restaurant name, customer name, rating, text, date, order number (link)
- "Approve" button → status = 'approved', visible on restaurant page
- "Reject" button → modal: "Reason for rejection" (shown to vendor, not customer) → status = 'rejected'
- Bulk actions: select multiple, approve/reject all

**Business Rules:**
- All reviews require approval before display
- Rejection reasons: spam, inappropriate language, off-topic, fake review
- Vendor notified of approval/rejection

**Technical Notes:**
- Update: `UPDATE reviews SET status = 'approved', moderated_by_admin_id = :adminId, moderated_at = NOW()`
- Email notification to vendor

#### FR-A1-009: Flagged Content Management
**Priority:** MEDIUM  
**Description:** Admin reviews content flagged by users

**Acceptance Criteria:**
- List of flagged content: reviews, menu items (offensive names/images)
- Each flag shows: content type, flagged by (user), reason, date, content preview
- Actions: "Approve" (no violation), "Remove Content", "Warn Vendor", "Suspend Vendor"
- Admin can leave resolution notes

**Business Rules:**
- 3 strikes → vendor suspension (automated)
- Flagged content hidden pending review
- False flags tracked (prevent abuse)

**Technical Notes:**
- Store in `flagged_content`:
```typescript
{
  id: string;
  content_type: 'review' | 'menu_item' | 'vendor_profile';
  content_id: string;
  flagged_by_user_id: string;
  reason: string;
  status: 'pending' | 'approved' | 'removed';
  admin_notes: string | null;
  resolved_at: timestamp | null;
}
```

#### FR-A1-010: Complaint Handling
**Priority:** MEDIUM  
**Description:** Admin manages customer complaints

**Acceptance Criteria:**
- List of complaints submitted by customers
- Each shows: customer name, vendor name, order number, complaint text, date, status (new/in progress/resolved)
- Admin can: contact customer, contact vendor, request refund, close complaint
- Resolution notes required before closing

**Business Rules:**
- Complaints auto-flagged if contain keywords: "refund", "sick", "food poisoning"
- Vendor must respond within 48 hours
- Unresolved complaints escalated to senior admin

**Technical Notes:**
- Store in `complaints`:
```typescript
{
  id: string;
  customer_id: string;
  vendor_id: string;
  order_id: string;
  complaint_text: string;
  status: 'new' | 'in_progress' | 'resolved' | 'escalated';
  admin_notes: string | null;
  resolved_at: timestamp | null;
}
```

---

### 6.4 System Administration

#### FR-A1-011: Platform Settings
**Priority:** MEDIUM  
**Description:** Admin configures global platform settings

**Acceptance Criteria:**
- Settings categories: General (platform name, support email), Payment (Stripe keys, platform fee percentage), Features (enable/disable feature flags), Limits (max menu items per plan, max tables per plan)
- Each setting has description and current value
- "Save Changes" button
- Change history logged

**Business Rules:**
- Critical settings require two-factor authentication
- Changes take effect immediately
- Audit trail for compliance

**Technical Notes:**
- Store in `platform_settings`:
```typescript
{
  key: string;
  value: string;
  data_type: 'string' | 'number' | 'boolean' | 'json';
  description: string;
  updated_at: timestamp;
  updated_by_admin_id: string;
}
```

#### FR-A1-012: Admin Roles & Permissions
**Priority:** HIGH  
**Description:** Manage admin users and their permissions

**Acceptance Criteria:**
- Roles: Super Admin (full access), Vendor Manager (vendor approval, suspension), Moderator (review moderation only), Support (read-only)
- "Add Admin" button → form: email, role
- List of admins with role badges
- "Edit Permissions" button → granular permissions checklist
- Activity log per admin

**Business Rules:**
- At least one Super Admin required
- Cannot delete own account
- Role changes require Super Admin approval

**Technical Notes:**
- Store in `admin_users`:
```typescript
{
  id: string;
  email: string;
  role: 'super_admin' | 'vendor_manager' | 'moderator' | 'support';
  permissions: string[]; // granular permissions
  created_at: timestamp;
  last_login_at: timestamp | null;
}
```

#### FR-A1-013: Audit Log
**Priority:** HIGH  
**Description:** Track all admin actions for compliance

**Acceptance Criteria:**
- Searchable log of all admin actions
- Each entry shows: admin user, action type (approve vendor, suspend vendor, moderate review, etc.), target (vendor/customer/review ID), timestamp, IP address
- Filter by: admin, action type, date range, target
- Export to CSV
- Cannot be modified or deleted (immutable)

**Business Rules:**
- All admin actions automatically logged
- Logs retained indefinitely (compliance)
- Super Admin can view all logs

**Technical Notes:**
- Store in `audit_log`:
```typescript
{
  id: string;
  admin_id: string;
  action_type: string; // 'approve_vendor', 'suspend_vendor', etc.
  target_type: string; // 'vendor', 'customer', 'review'
  target_id: string;
  old_value: json | null;
  new_value: json | null;
  ip_address: string;
  created_at: timestamp;
}
```
- Append-only table (no UPDATE or DELETE allowed)

#### FR-A1-014: Feature Flags
**Priority:** MEDIUM  
**Description:** Admin toggles features on/off globally

**Acceptance Criteria:**
- List of feature flags with toggle switches: Vendor Onboarding, Customer Reviews, AI Features (Phase 2), Loyalty Program (Phase 2), Multi-Language
- Each flag shows: enabled/disabled status, description, affected users
- Changes take effect immediately
- Rollback button (revert last change)

**Business Rules:**
- Critical features cannot be disabled (e.g., payments)
- Disabling feature notifies affected users
- Feature flags logged in audit trail

**Technical Notes:**
- Store in `feature_flags`:
```typescript
{
  key: string;
  enabled: boolean;
  description: string;
  rollout_percentage: number; // 0-100 for gradual rollout
  updated_at: timestamp;
}
```
- Check flag: `const enabled = await getFeatureFlag('loyalty_program');`

#### FR-A1-015: System Health Monitoring
**Priority:** HIGH  
**Description:** Monitor platform infrastructure and performance

**Acceptance Criteria:**
- Dashboard shows: API response time (avg), database query performance, Supabase Realtime status, Stripe API status, error rate (last 24h), active users (real-time)
- Alerts: API downtime, high error rate (>5%), database slow queries, Stripe webhook failures
- Incident log (historical outages)

**Business Rules:**
- Alerts sent to admin email/SMS
- Auto-escalation if unresolved >1 hour
- Status page for customers (public)

**Technical Notes:**
- Integrate with monitoring services: Sentry (errors), Uptime Robot (uptime)
- Health check endpoint: `GET /api/health`
```typescript
{
  status: 'healthy' | 'degraded' | 'down',
  services: {
    database: 'up' | 'down',
    realtime: 'up' | 'down',
    stripe: 'up' | 'down'
  },
  response_time_ms: number,
  timestamp: timestamp
}
```

#### FR-A1-016: Email & Notification Templates
**Priority:** LOW  
**Description:** Admin manages email and notification templates

**Acceptance Criteria:**
- List of templates: Vendor Approved, Vendor Rejected, Subscription Cancelled, Order Confirmation, Payment Received, etc.
- Edit template: subject, body (HTML + plain text), variables (e.g., `{{restaurant_name}}`)
- Preview with sample data
- Send test email
- Version history (revert changes)

**Business Rules:**
- Templates support multiple languages
- Variables auto-populated from database
- Cannot delete system templates (only edit)

**Technical Notes:**
- Store in `email_templates`:
```typescript
{
  id: string;
  template_key: string; // 'vendor_approved'
  language: string; // 'de', 'en', etc.
  subject: string;
  body_html: string;
  body_text: string;
  variables: string[]; // ['restaurant_name', 'approval_date']
  updated_at: timestamp;
}
```
- Use templating engine: Handlebars or Mustache

#### FR-A1-017: Data Export & Backup
**Priority:** MEDIUM  
**Description:** Admin exports platform data for backup/analysis

**Acceptance Criteria:**
- Export options: All Vendors, All Customers, All Orders, All Reviews, All Invoices
- Format options: CSV, JSON, SQL dump
- Date range selector
- "Export" button → generates file → download link
- Scheduled backups (daily, weekly)

**Business Rules:**
- Exports include all data (no filtering by vendor)
- Personal data exports require justification (GDPR)
- Backups stored in separate region (disaster recovery)

**Technical Notes:**
- Use Supabase database export tools
- Store backups in AWS S3 or similar
- Encryption at rest required

#### FR-A1-018: Analytics Dashboard
**Priority:** LOW  
**Description:** Admin views platform-wide analytics

**Acceptance Criteria:**
- Charts: Total orders over time, revenue over time, vendor signups over time, customer registrations over time
- Metrics: Average order value, orders per vendor, conversion rate (QR scans → orders)
- Breakdown: orders by payment method, orders by order type (dine-in/takeaway)
- Export to PDF/CSV

**Business Rules:**
- Data aggregated (no individual customer/vendor details)
- Analytics refreshed daily
- Historical data retained indefinitely

**Technical Notes:**
- Use analytics library: Recharts for visualizations
- Pre-aggregate daily metrics for performance
```sql
CREATE TABLE daily_platform_metrics (
  date DATE PRIMARY KEY,
  total_orders INT,
  total_revenue DECIMAL,
  new_vendors INT,
  new_customers INT,
  avg_order_value DECIMAL
);
```

#### FR-A1-019: Support Ticket System (Basic)
**Priority:** LOW  
**Description:** Admin manages vendor/customer support requests

**Acceptance Criteria:**
- List of tickets: ticket number, requester (vendor/customer), subject, status (new/in progress/resolved), priority (low/medium/high), assigned to (admin)
- Click ticket → detail view with conversation thread
- Admin can: reply, change status, assign to another admin, close ticket
- Email notifications on new tickets

**Business Rules:**
- Tickets auto-assigned based on round-robin
- Response SLA: 24 hours for medium priority, 4 hours for high
- Resolved tickets archived after 30 days

**Technical Notes:**
- Store in `support_tickets`:
```typescript
{
  id: string;
  ticket_number: string; // 'T-12345'
  requester_id: string;
  requester_type: 'vendor' | 'customer';
  subject: string;
  status: 'new' | 'in_progress' | 'resolved' | 'closed';
  priority: 'low' | 'medium' | 'high';
  assigned_to_admin_id: string | null;
  created_at: timestamp;
  resolved_at: timestamp | null;
}
```

#### FR-A1-020: Vendor AI Insights (Admin View)
**Priority:** LOW  
**Description:** Admin reviews AI-generated insights across all vendors

**Acceptance Criteria:**
- Dashboard shows AI insights summary: total insights generated, insights by category (menu optimization, pricing suggestions, operational tips)
- List of vendors with recent insights
- Click vendor → view their AI insights
- Admin can flag inaccurate insights (feedback for AI improvement)

**Business Rules:**
- Enterprise plan feature only
- Insights updated weekly
- Admin cannot modify insights (read-only)

**Technical Notes:**
- Aggregate from `ai_insights` table
- Filter: `GROUP BY vendor_id, insight_category`

---

## 7. PLATFORM PAGES

### 7.1 Public-Facing Pages

#### FR-P1-001: Homepage (Marketing)
**Priority:** HIGH  
**Description:** Public landing page to attract restaurants and customers

**Acceptance Criteria:**
- Hero section: headline ("Digital ordering for modern restaurants"), subheadline, "Get Started" CTA (for vendors), "Find Restaurants" (for customers)
- Features section: Shared basket, split bill, VAT compliance, multi-language (with icons)
- Pricing section: 3-column plan comparison
- Testimonials: quotes from vendors (Phase 2)
- Footer: links (About, Pricing, Contact, Privacy, Terms)

**Business Rules:**
- Mobile-responsive design
- Fast load time (<3 seconds)
- SEO optimized (meta tags, Open Graph)

**Technical Notes:**
- Static page (Next.js or similar SSG)
- Hosted on same domain as app
- Analytics tracking (Google Analytics or similar)

#### FR-P1-002: Restaurant Discovery (Basic)
**Priority:** MEDIUM  
**Description:** Customers browse restaurants using TAVLO

**Acceptance Criteria:**
- Grid of restaurant cards: logo, name, cuisine type, rating (if available), "View Menu" link
- Search bar: search by name
- Filter: cuisine type, location (Phase 2)
- Click restaurant → redirect to restaurant page

**Business Rules:**
- Only show active vendors (`status = 'active'`)
- Sort by: newest, highest rated (Phase 2)
- No registration required to browse

**Technical Notes:**
- Query: `SELECT * FROM vendors WHERE status = 'active'`
- Server-side rendering for SEO
- Infinite scroll or pagination

#### FR-P1-003: Restaurant Profile Page
**Priority:** MEDIUM  
**Description:** Public restaurant page with menu and info

**Acceptance Criteria:**
- Header: restaurant name, logo, cover photo, cuisine type, rating, address, phone, website, hours
- Tabs: Menu, About, Reviews (Phase 2)
- Menu tab: full menu (same as customer QR experience)
- About tab: description, photos, amenities
- "Order Takeaway" button (if enabled)

**Business Rules:**
- Public URL: `https://tavlo.app/r/{vendor_slug}`
- SEO-friendly slug (e.g., "pizza-luigi-vienna")
- No QR scan required to browse menu

**Technical Notes:**
- Server-side rendering
- Open Graph meta tags for social sharing
```html
<meta property="og:title" content="{restaurant_name}" />
<meta property="og:image" content="{logo_url}" />
<meta property="og:description" content="{description}" />
```

#### FR-P1-004: About Page
**Priority:** LOW  
**Description:** Information about TAVLO platform

**Acceptance Criteria:**
- Sections: Our Mission, How It Works, Why TAVLO?, Team (Phase 2), Contact
- How It Works: customer flow (scan QR → order → pay), vendor flow (onboard → manage → get paid)
- Contact form: name, email, message, "Send" button

**Business Rules:**
- Contact form submissions emailed to support@tavlo.app
- Form requires reCAPTCHA (prevent spam)

**Technical Notes:**
- Static content (markdown or CMS)
- Contact form via API endpoint

#### FR-P1-005: Pricing Page
**Priority:** MEDIUM  
**Description:** Public page showing subscription pricing

**Acceptance Criteria:**
- Three-column plan comparison: Basic (€29/mo), Professional (€79/mo), Enterprise (€199/mo)
- Each column shows: price, features list, "Start Free Trial" CTA
- FAQ section: common questions about pricing, billing, cancellation
- "Contact Sales" link for custom enterprise pricing

**Business Rules:**
- Prices shown exclude VAT (added at checkout)
- Free trial: 14 days, no credit card required
- Enterprise: custom pricing on request

**Technical Notes:**
- Static page with dynamic pricing (fetched from Stripe)
- A/B testing for pricing (Phase 2)

#### FR-P1-006: Privacy Policy
**Priority:** HIGH  
**Description:** GDPR-compliant privacy policy

**Acceptance Criteria:**
- Sections: Data Collection, Data Usage, Data Sharing, Data Retention, User Rights, Cookies, Contact
- Explains: what data collected (emails, orders, analytics), how used (order fulfillment, analytics), retention period (7 years for invoices, 90 days for analytics)
- User rights: access, rectification, deletion, portability
- Last updated date shown

**Business Rules:**
- GDPR compliance mandatory (EU regulation)
- Updated whenever data practices change
- Versioned (users notified of changes)

**Technical Notes:**
- Static page (markdown)
- Linked in footer of all pages
- Acceptance logged on account creation

#### FR-P1-007: Terms of Service
**Priority:** HIGH  
**Description:** Legal terms for using TAVLO

**Acceptance Criteria:**
- Sections: Acceptance, Service Description, User Responsibilities, Vendor Obligations, Payment Terms, Liability, Termination, Governing Law
- Vendor obligations: accurate menu, timely order fulfillment, compliance with laws
- Payment terms: subscription billing, platform fees, refund policy
- Governing law: Austrian law

**Business Rules:**
- Acceptance required on signup
- Violations may result in suspension
- Updated with version history

**Technical Notes:**
- Static page (markdown)
- Acceptance: `agreed_to_terms_version` field in users table

#### FR-P1-008: Contact Page
**Priority:** LOW  
**Description:** Contact form for support inquiries

**Acceptance Criteria:**
- Form fields: name (required), email (required), subject (dropdown: General, Technical Support, Billing, Partnership), message (required)
- "Send" button → email sent to support team → success toast
- Response time: "We'll respond within 24 hours"

**Business Rules:**
- Form requires reCAPTCHA
- Auto-response email sent to user
- Tickets created in support system (FR-A1-019)

**Technical Notes:**
- Form submission via API endpoint
- Email sent via Supabase Edge Function
```typescript
await sendEmail({
  to: 'support@tavlo.app',
  subject: `Contact Form: ${subject}`,
  body: `From: ${name} (${email})\n\n${message}`
});
```

---

## 8. TECHNICAL ARCHITECTURE

### 8.1 Technology Stack

**Frontend:**
- React 18+ with TypeScript
- Tailwind CSS v4.0 (no config file)
- shadcn/ui components
- Motion (Framer Motion) for animations
- Recharts for analytics charts
- React Hook Form for form handling
- Zustand for state management

**Backend:**
- Supabase (PostgreSQL, Realtime, Storage, Auth)
- Deno Edge Functions (Hono.js framework)
- Stripe for payments and subscriptions
- Stripe Connect for vendor payouts

**External Services:**
- OpenAI GPT-4 (AI features - Phase 2+)
- Email/SMS notifications
- Unsplash API (stock images)

**Hosting:**
- Vercel (frontend)
- Supabase Cloud (backend)

---

### 8.2 Key Architectural Decisions

**Real-Time Collaboration:**
- Supabase Realtime (WebSocket-based) for shared basket
- Optimistic UI updates with server reconciliation
- Conflict resolution: last-write-wins with toast notifications

**Payment Processing:**
- Stripe Checkout (hosted) for PCI compliance
- Stripe Connect Express for vendor payouts
- Platform fee: 2% + €0.30 per transaction

**Austrian VAT Compliance:**
- Prices always include VAT
- Invoice generation with § 11 UStG requirements
- 7-year invoice retention

**Multi-Language Support:**
- React Context + i18next
- 11 languages supported
- Vendor-provided menu translations
- AI translations (Phase 2)

---

## 9. DATA MODELS

### 9.1 Core Tables

#### `vendors`
```typescript
{
  id: string; // UUID
  restaurant_name: string;
  owner_name: string;
  email: string; // unique
  phone: string;
  address_street: string;
  address_city: string;
  address_postal_code: string;
  address_country: string;
  vat_id: string; // Austrian UID
  business_type: string;
  status: 'pending_approval' | 'active' | 'suspended';
  subscription_status: 'trial' | 'active' | 'past_due' | 'cancelled';
  subscription_plan: 'basic' | 'professional' | 'enterprise';
  stripe_customer_id: string | null;
  stripe_account_id: string | null; // Stripe Connect
  created_at: timestamp;
  approved_at: timestamp | null;
  approved_by_admin_id: string | null;
}
```

#### `customers`
```typescript
{
  id: string; // UUID from Supabase Auth
  email: string; // unique
  name: string;
  phone: string | null;
  email_confirmed: boolean;
  created_at: timestamp;
  last_login_at: timestamp | null;
}
```

#### `menu_items`
```typescript
{
  id: string;
  vendor_id: string; // FK to vendors
  name: string;
  description: string;
  price: number; // includes VAT, in euros
  vat_rate: number; // 10, 13, or 20
  category_id: string; // FK to menu_categories
  image_url: string | null;
  allergens: string[]; // ["nuts", "dairy"]
  addons: Array<{name: string, price: number}>;
  available: boolean;
  sort_order: number;
  created_at: timestamp;
  updated_at: timestamp;
  deleted_at: timestamp | null; // soft delete
}
```

#### `orders`
```typescript
{
  id: string;
  order_number: string; // unique per vendor
  vendor_id: string;
  customer_id: string | null; // null for guest orders
  customer_name: string;
  customer_phone: string | null;
  customer_email: string | null;
  order_type: 'dine_in' | 'takeaway';
  table_number: string | null;
  status: 'submitted' | 'preparing' | 'ready' | 'completed' | 'cancelled';
  subtotal: number;
  tip: number;
  total: number;
  payment_status: 'pending' | 'paid' | 'failed';
  created_at: timestamp;
  estimated_ready_at: timestamp;
  completed_at: timestamp | null;
}
```

#### `order_items`
```typescript
{
  id: string;
  order_id: string;
  menu_item_id: string;
  name: string; // snapshot at order time
  price: number; // snapshot
  quantity: number;
  customization_notes: string | null;
  addons: Array<{name: string, price: number}>;
  created_at: timestamp;
}
```

#### `payments`
```typescript
{
  id: string;
  order_id: string;
  amount: number;
  tip_amount: number;
  payment_method: 'card' | 'apple' | 'google' | 'cash';
  status: 'pending' | 'succeeded' | 'failed';
  stripe_payment_intent_id: string | null;
  paid_at: timestamp | null;
  created_at: timestamp;
}
```

#### `basket_items`
```typescript
{
  id: string;
  table_session_id: string;
  menu_item_id: string;
  quantity: number;
  added_by_customer_id: string | null;
  added_by_name: string; // "Guest 1", "Anna"
  customization_notes: string | null;
  addons: Array<{name: string, price: number}>;
  created_at: timestamp;
}
```

---

## END OF PHASE 1 SRS

**Total Features:** 95  
**Status:** ✅ Production-Ready  
**Next:** Phase 2 — Expansion (see TAVLO_SRS_PHASE_2.md)

---

**Last Updated:** December 26, 2024  
**Document Owner:** TAVLO Product Team  
**Version:** 4.0
