# TAVLO — Software Requirements Specification  
## PHASE 2 — EXPANSION (Retention & Operations)

**Document Version:** 4.0  
**Last Updated:** December 26, 2024  
**Status:** 60% Complete  
**Timeline:** Weeks 13-20  
**Goal:** Retention, Discovery & Operational Excellence

---

## TABLE OF CONTENTS

1. [Introduction](#1-introduction)
2. [Phase 2 Overview](#2-phase-2-overview)
3. [Customer Features](#3-customer-features)
4. [Vendor Features](#4-vendor-features)
5. [Admin Features](#5-admin-features)
6. [Platform Pages](#6-platform-pages)

---

## 1. INTRODUCTION

### 1.1 Purpose
This document specifies Phase 2 functional requirements for TAVLO. Phase 2 builds on the Phase 1 foundation to add retention features (loyalty, favorites), discovery tools (search, filters, reservations), and operational excellence (promotions, inventory, KDS integration).

### 1.2 Prerequisites
- Phase 1 must be complete and in production
- All Phase 1 features stable and bug-free
- Vendor subscription model operational
- Minimum 10 active vendors using platform

### 1.3 Phase 2 Goals
- 🎯 **Customer Retention:** Loyalty programs, saved restaurants, order history
- 🎯 **Discovery:** Search, advanced filters, restaurant recommendations
- 🎯 **Vendor Operations:** Promotions, inventory management, kitchen display system
- 🎯 **Revenue Growth:** Increased order volume, higher customer LTV
- 🎯 **Platform Intelligence:** Basic AI features, analytics enhancements

---

## 2. PHASE 2 OVERVIEW

### 2.1 Feature Count
- **Customer Features:** 27
- **Vendor Features:** 33
- **Admin Features:** 13
- **Platform Pages:** 19
- **Total:** 92 features

### 2.2 Success Criteria
- [ ] 30% of customers create accounts (vs guest checkout)
- [ ] Loyalty redemption rate: 20%
- [ ] Promotion usage: 40% of orders use a promo
- [ ] Discovery: 25% of orders from platform search (vs direct QR)
- [ ] Vendor retention: 90% after 6 months

---

## 3. CUSTOMER FEATURES

### 3.1 Discovery & Search

#### FR-C2-001: Restaurant Search
**Priority:** HIGH  
**Description:** Customer searches for restaurants by name, cuisine, or location

**Acceptance Criteria:**
- Search bar on homepage and discovery page
- Real-time search results as user types (debounced 300ms)
- Matches: restaurant name, cuisine type, menu items, location (city)
- Search results show: restaurant card with logo, name, cuisine, distance (if geolocation enabled), rating
- "View Menu" button on each result
- Search history saved (logged-in users only)

**Business Rules:**
- Min 2 characters to trigger search
- Case-insensitive, fuzzy matching
- Results sorted by relevance (algorithm: name match > cuisine match > menu item match)
- Max 50 results shown

**Technical Notes:**
- Full-text search using Postgres `tsvector`:
```sql
SELECT * FROM vendors
WHERE 
  to_tsvector('german', restaurant_name || ' ' || cuisine_type || ' ' || description) 
  @@ plainto_tsquery('german', :query)
ORDER BY ts_rank(...) DESC
LIMIT 50;
```
- Use Algolia or Meilisearch for better performance (optional)

#### FR-C2-002: Advanced Filters
**Priority:** MEDIUM  
**Description:** Customer filters restaurants by multiple criteria

**Acceptance Criteria:**
- Filter sidebar/modal with options:
  - **Cuisine:** Italian, Asian, Austrian, Mediterranean, etc. (multi-select)
  - **Price Range:** €, €€, €€€, €€€€ (calculated from avg item price)
  - **Dietary:** Vegetarian, Vegan, Gluten-free, Halal, Kosher
  - **Features:** Dine-in, Takeaway, Delivery (Phase 3), Outdoor seating
  - **Rating:** 4+ stars, 3+ stars, etc.
  - **Distance:** Within 1km, 5km, 10km (requires geolocation)
- "Apply Filters" button
- Active filters shown as chips (removable)
- Filter count badge on filter button

**Business Rules:**
- Filters use AND logic (restaurant must match all selected filters)
- Empty filter results show "No restaurants found - try fewer filters"
- Geolocation permission required for distance filter

**Technical Notes:**
- Build dynamic query based on selected filters
```typescript
let query = supabase.from('vendors').select('*').eq('status', 'active');
if (filters.cuisine.length) query = query.in('cuisine_type', filters.cuisine);
if (filters.rating) query = query.gte('avg_rating', filters.rating);
// ... etc
return query;
```

#### FR-C2-003: Geolocation-Based Search
**Priority:** MEDIUM  
**Description:** Customer finds nearby restaurants using GPS

**Acceptance Criteria:**
- "Near Me" button on discovery page
- Tap → request geolocation permission
- Map view showing nearby restaurants (pins)
- List view with distance shown (e.g., "0.8 km")
- Sort by distance (nearest first)
- Radius slider: 1km, 5km, 10km, 25km

**Business Rules:**
- Geolocation permission optional (can search without)
- Distance calculated as crow flies (not driving distance)
- Coordinates stored temporarily (not saved)

**Technical Notes:**
- Haversine formula for distance calculation:
```sql
SELECT *, (
  6371 * acos(
    cos(radians(:lat)) * cos(radians(vendor_lat)) * 
    cos(radians(vendor_lng) - radians(:lng)) + 
    sin(radians(:lat)) * sin(radians(vendor_lat))
  )
) AS distance
FROM vendors
HAVING distance < :radius
ORDER BY distance;
```
- Use Mapbox or Google Maps API for map display

#### FR-C2-004: Restaurant Recommendations
**Priority:** LOW  
**Description:** AI-powered restaurant suggestions based on customer preferences

**Acceptance Criteria:**
- "Recommended for You" section on homepage (logged-in users)
- Shows 3-5 restaurants based on: past orders (cuisine preferences), saved restaurants, search history, trending restaurants
- "Why recommended?" tooltip (e.g., "Similar to restaurants you liked")
- Refreshed daily

**Business Rules:**
- Only for logged-in users (requires order history)
- Min 3 past orders to show recommendations
- Enterprise plan vendor boost (pay for featured placement)

**Technical Notes:**
- Simple recommendation algorithm Phase 2:
```typescript
// Get user's favorite cuisines from order history
const cuisines = await getUserFavoriteCuisines(userId);
// Find restaurants matching those cuisines
const recommended = await supabase
  .from('vendors')
  .select('*')
  .in('cuisine_type', cuisines)
  .not('id', 'in', userOrderedRestaurants) // exclude already ordered
  .order('avg_rating', { ascending: false })
  .limit(5);
```
- Phase 3: Machine learning model

#### FR-C2-005: Saved Restaurants (Favorites)
**Priority:** MEDIUM  
**Description:** Customer saves favorite restaurants for quick access

**Acceptance Criteria:**
- Heart icon on restaurant cards and profile pages
- Tap heart → save/unsave (toggle)
- "Saved Restaurants" page in account section
- List of saved restaurants with quick actions: "Order Now", "View Menu"
- Remove from saved with swipe gesture (mobile)

**Business Rules:**
- Only available to logged-in users
- Unlimited saved restaurants
- Saved status syncs across devices

**Technical Notes:**
- Store in `saved_restaurants`:
```typescript
{
  id: string;
  customer_id: string;
  vendor_id: string;
  created_at: timestamp;
}
```
- Unique constraint: `(customer_id, vendor_id)`

#### FR-C2-006: Order History (Enhanced)
**Priority:** MEDIUM  
**Description:** Comprehensive order history with insights

**Acceptance Criteria:**
- Order history page shows: all past orders (newest first), filters (date range, restaurant, order type), search by order number
- Each order card: restaurant logo, name, date, items count, total, status, "Reorder" button, "View Receipt", "Leave Review"
- Expandable: shows full item list, customizations
- Statistics: total orders, total spent, favorite restaurant, favorite dish

**Business Rules:**
- Only logged-in users (guest orders not saved)
- Orders retained indefinitely
- "Reorder" adds items to basket (checks availability first)

**Technical Notes:**
- Query: `SELECT * FROM orders WHERE customer_id = :id ORDER BY created_at DESC`
- Pagination: 20 per page
- Statistics computed from aggregates:
```sql
SELECT 
  COUNT(*) as total_orders,
  SUM(total) as total_spent,
  MODE() WITHIN GROUP (ORDER BY vendor_id) as favorite_vendor
FROM orders
WHERE customer_id = :id AND status = 'completed';
```

#### FR-C2-007: Reorder Feature
**Priority:** MEDIUM  
**Description:** Customer reorders previous order with one tap

**Acceptance Criteria:**
- "Reorder" button on order history cards
- Tap → all items added to basket
- If any item unavailable, show warning: "Some items are no longer available"
- Navigate to basket for review
- Confirmation toast: "X items added to basket"

**Business Rules:**
- Only completed orders can be reordered
- Prices may have changed (current prices used, shown with warning)
- Items no longer on menu excluded with notification

**Technical Notes:**
- Fetch order items: `SELECT * FROM order_items WHERE order_id = :id`
- Check availability for each item
- Add available items to basket
```typescript
const reorder = async (orderId: string) => {
  const items = await getOrderItems(orderId);
  const availableItems = [];
  for (const item of items) {
    const menuItem = await getMenuItem(item.menu_item_id);
    if (menuItem && menuItem.available) {
      availableItems.push(item);
    }
  }
  await addItemsToBasket(availableItems);
  if (availableItems.length < items.length) {
    showWarning('Some items no longer available');
  }
};
```

---

### 3.2 Loyalty & Rewards

#### FR-C2-008: Loyalty Points Earning
**Priority:** HIGH  
**Description:** Customer earns points on every order

**Acceptance Criteria:**
- Points earned based on order total: 1 point per €1 spent (configurable by vendor)
- Points shown on order confirmation: "You earned 25 points!"
- Points balance displayed in account section
- Points history: list of earned/redeemed points with dates

**Business Rules:**
- Only logged-in customers earn points (guest orders: no points)
- Points earned after order completed (not on submission)
- Cancelled/refunded orders: points deducted
- Points never expire

**Technical Notes:**
- Store in `loyalty_points`:
```typescript
{
  id: string;
  customer_id: string;
  vendor_id: string;
  order_id: string | null;
  points: number; // positive for earn, negative for redeem
  reason: 'order_completed' | 'redeemed' | 'refund' | 'bonus';
  created_at: timestamp;
}
```
- Current balance: `SUM(points) WHERE customer_id = :id GROUP BY vendor_id`

#### FR-C2-009: Loyalty Points Redemption
**Priority:** HIGH  
**Description:** Customer redeems points for discounts

**Acceptance Criteria:**
- At checkout, show available points: "You have 500 points (= €5 discount)"
- "Use Points" toggle
- Slider to select how many points to redeem (max: points balance or order total)
- Discount applied to order total
- Confirmation: "You saved €5 with loyalty points!"

**Business Rules:**
- Redemption rate: 100 points = €1 discount (configurable)
- Min redemption: 100 points (€1)
- Max redemption: cannot exceed order total
- Points deducted after payment successful

**Technical Notes:**
- Calculate discount: `pointsToRedeem / 100`
- Update order total: `total -= discount`
- Deduct points:
```typescript
await supabase.from('loyalty_points').insert({
  customer_id: userId,
  vendor_id: vendorId,
  order_id: orderId,
  points: -pointsToRedeem,
  reason: 'redeemed'
});
```

#### FR-C2-010: Loyalty Tiers
**Priority:** MEDIUM  
**Description:** Customer progresses through loyalty tiers with benefits

**Acceptance Criteria:**
- Tiers: Bronze (0-499 pts), Silver (500-1999 pts), Gold (2000-4999 pts), Platinum (5000+ pts)
- Each tier offers benefits:
  - Bronze: 1 pt per €1
  - Silver: 1.2 pts per €1 + birthday discount
  - Gold: 1.5 pts per €1 + priority support + early access to promos
  - Platinum: 2 pts per €1 + VIP perks + exclusive menu items
- Tier badge shown in account section
- Progress bar to next tier

**Business Rules:**
- Tier calculated per vendor (not platform-wide)
- Tier benefits apply immediately upon reaching threshold
- Downgrade if points fall below tier minimum (after redemptions)

**Technical Notes:**
- Tiers stored in config:
```typescript
const tiers = [
  { name: 'Bronze', min: 0, multiplier: 1.0 },
  { name: 'Silver', min: 500, multiplier: 1.2 },
  { name: 'Gold', min: 2000, multiplier: 1.5 },
  { name: 'Platinum', min: 5000, multiplier: 2.0 }
];

const getTier = (points: number) => {
  return tiers.reverse().find(tier => points >= tier.min);
};
```

#### FR-C2-011: Referral Program
**Priority:** LOW  
**Description:** Customer earns points by referring friends

**Acceptance Criteria:**
- "Refer a Friend" page in account section
- Shows: unique referral code, referral link (shareable via SMS, email, WhatsApp)
- Rewards: Referrer gets 200 points, referee gets 100 points (on first order)
- Referral history: list of referred friends, status (pending/completed)

**Business Rules:**
- Both referrer and referee must be registered users
- Referee must use referral code at signup
- Points awarded after referee's first completed order
- Self-referral prohibited (tracked by email, phone, IP)

**Technical Notes:**
- Generate unique code: `base64(userId).slice(0, 8).toUpperCase()`
- Store in `referrals`:
```typescript
{
  id: string;
  referrer_id: string;
  referee_id: string;
  referral_code: string;
  status: 'pending' | 'completed';
  created_at: timestamp;
  completed_at: timestamp | null;
}
```
- Award points on first order completion:
```typescript
if (isFirstOrder(userId)) {
  const referral = await getReferralByRefereeId(userId);
  if (referral) {
    await awardPoints(referral.referrer_id, 200, 'referral');
    await awardPoints(userId, 100, 'referral');
    await updateReferralStatus(referral.id, 'completed');
  }
}
```

---

### 3.3 Promotions

#### FR-C2-012: Browse Promotions
**Priority:** MEDIUM  
**Description:** Customer views available promotions and deals

**Acceptance Criteria:**
- "Promotions" tab on restaurant page
- List of active promotions: title, description, discount (percentage or fixed amount), valid until date, terms & conditions
- "Apply" button adds promo to cart
- Expired promos shown as greyed out (historical reference)

**Business Rules:**
- Only active promotions shown (start_date <= today <= end_date)
- Promotions stack only if vendor allows
- One auto-applied promo per order (highest discount)

**Technical Notes:**
- Query:
```sql
SELECT * FROM promotions
WHERE vendor_id = :id
AND start_date <= CURRENT_DATE
AND end_date >= CURRENT_DATE
AND active = true
ORDER BY discount_value DESC;
```

#### FR-C2-013: Apply Promo Code
**Priority:** MEDIUM  
**Description:** Customer enters promo code at checkout

**Acceptance Criteria:**
- "Promo Code" field at checkout
- Text input + "Apply" button
- Valid code → discount applied, success message: "€5 discount applied!"
- Invalid code → error: "Invalid promo code"
- Remove promo button (X icon)
- Discount shown in order summary

**Business Rules:**
- Case-insensitive code matching
- One promo code per order (cannot stack manual codes)
- Code usage tracked (for single-use codes)
- Minimum order value enforced (if promo has min requirement)

**Technical Notes:**
- Validate promo code:
```typescript
const validatePromoCode = async (code: string, vendorId: string, orderTotal: number) => {
  const promo = await supabase
    .from('promotions')
    .select('*')
    .eq('code', code.toUpperCase())
    .eq('vendor_id', vendorId)
    .lte('start_date', new Date())
    .gte('end_date', new Date())
    .eq('active', true)
    .single();
  
  if (!promo) return { valid: false, error: 'Invalid code' };
  if (promo.min_order_value && orderTotal < promo.min_order_value) {
    return { valid: false, error: `Min order €${promo.min_order_value} required` };
  }
  if (promo.max_uses && promo.times_used >= promo.max_uses) {
    return { valid: false, error: 'Promo expired (max uses reached)' };
  }
  
  return { valid: true, promo };
};
```

#### FR-C2-014: Auto-Applied Promotions
**Priority:** LOW  
**Description:** Best available promo automatically applied

**Acceptance Criteria:**
- At checkout, system checks all active promos for vendor
- Highest discount promo auto-applied
- Notification: "Best promo applied: 20% off your order!"
- Customer can remove and manually enter different code

**Business Rules:**
- Auto-apply only if no manual code entered
- Auto-apply checks min order value requirement
- Highest discount = max(percentage_off, fixed_amount)

**Technical Notes:**
```typescript
const autoApplyBestPromo = async (vendorId: string, orderTotal: number) => {
  const promos = await getActivePromos(vendorId, orderTotal);
  const bestPromo = promos.reduce((best, current) => {
    const currentDiscount = current.discount_type === 'percentage' 
      ? orderTotal * (current.discount_value / 100)
      : current.discount_value;
    const bestDiscount = best.discount_type === 'percentage'
      ? orderTotal * (best.discount_value / 100)
      : best.discount_value;
    return currentDiscount > bestDiscount ? current : best;
  });
  return bestPromo;
};
```

#### FR-C2-015: Flash Sales / Limited-Time Offers
**Priority:** LOW  
**Description:** Time-sensitive promotions with countdown

**Acceptance Criteria:**
- Flash sale banner on restaurant page: "⚡ Flash Sale: 30% off for next 2 hours!"
- Countdown timer shows time remaining (HH:MM:SS)
- Promo auto-expires when timer hits 0:00:00
- Push notification when flash sale starts (opted-in users)

**Business Rules:**
- Flash sales: duration 1-24 hours
- Vendor can schedule in advance
- Limited quantity (e.g., first 50 orders)

**Technical Notes:**
- Store in `promotions` with `flash_sale = true` and `end_date`
- Countdown component:
```typescript
const [timeLeft, setTimeLeft] = useState(calculateTimeLeft(promo.end_date));
useEffect(() => {
  const timer = setInterval(() => {
    setTimeLeft(calculateTimeLeft(promo.end_date));
  }, 1000);
  return () => clearInterval(timer);
}, [promo.end_date]);
```

---

### 3.4 Reservations

#### FR-C2-016: Browse Availability
**Priority:** MEDIUM  
**Description:** Customer checks restaurant table availability

**Acceptance Criteria:**
- "Reserve a Table" button on restaurant page
- Date picker: select date (today to +30 days)
- Time picker: select time (in 30-min slots)
- Party size selector: 1-20 people
- "Check Availability" button → shows available time slots or "Fully booked"

**Business Rules:**
- Reservations available only if vendor enables feature
- Slots shown based on restaurant hours and capacity
- Unavailable slots greyed out

**Technical Notes:**
- Query available slots:
```sql
SELECT time_slot FROM available_slots
WHERE vendor_id = :id
AND date = :date
AND capacity >= :party_size
AND time_slot NOT IN (
  SELECT time_slot FROM reservations 
  WHERE date = :date AND status = 'confirmed'
);
```

#### FR-C2-017: Make Reservation
**Priority:** MEDIUM  
**Description:** Customer reserves a table

**Acceptance Criteria:**
- Select available time slot → reservation form
- Form fields: name (prefilled if logged in), phone (required), email (optional), special requests (e.g., high chair, window seat)
- "Confirm Reservation" button
- Confirmation screen: "Reservation confirmed for {date} at {time}", reservation number, "Add to Calendar" button, cancellation policy
- Email confirmation sent

**Business Rules:**
- Phone number required (for no-show tracking)
- Logged-in users: reservation linked to account
- Guest reservations allowed
- Cancellation free up to 2 hours before (configurable)

**Technical Notes:**
- Store in `reservations`:
```typescript
{
  id: string;
  vendor_id: string;
  customer_id: string | null;
  customer_name: string;
  customer_phone: string;
  customer_email: string | null;
  date: date;
  time_slot: string; // "18:30"
  party_size: number;
  special_requests: string | null;
  status: 'pending' | 'confirmed' | 'cancelled' | 'no_show' | 'completed';
  confirmation_code: string; // 6-digit code
  created_at: timestamp;
}
```

#### FR-C2-018: My Reservations
**Priority:** MEDIUM  
**Description:** Customer views and manages upcoming reservations

**Acceptance Criteria:**
- "My Reservations" page in account section
- List of upcoming reservations: restaurant name, date, time, party size, status
- Past reservations shown separately
- Actions: "View Details", "Modify" (if allowed), "Cancel"
- Push notification reminder 1 day before + 2 hours before

**Business Rules:**
- Can modify up to 24 hours before (vendor setting)
- Can cancel up to 2 hours before (free)
- Late cancellation fee: €10 (charged to payment method)
- No-shows tracked (3 no-shows = reservation privileges suspended)

**Technical Notes:**
- Modification creates new reservation + cancels old
- Send reminders via cron job:
```typescript
// Daily job at 9:00 AM
const sendReminders = async () => {
  const tomorrow = addDays(new Date(), 1);
  const reservations = await supabase
    .from('reservations')
    .select('*')
    .eq('date', tomorrow)
    .eq('status', 'confirmed');
  
  for (const res of reservations) {
    await sendEmail({
      to: res.customer_email,
      subject: 'Reservation Reminder',
      body: `Your reservation at ${res.vendor_name} is tomorrow at ${res.time_slot}`
    });
  }
};
```

#### FR-C2-019: Cancel Reservation
**Priority:** MEDIUM  
**Description:** Customer cancels reservation

**Acceptance Criteria:**
- "Cancel" button on reservation detail
- Confirmation modal: "Are you sure? Cancellation policy: {...}"
- Confirm → reservation status = 'cancelled'
- Email confirmation sent to customer and vendor
- Refund of deposit if applicable

**Business Rules:**
- Free cancellation up to 2 hours before
- Cancellation 0-2 hours before: no-show fee (€10)
- No-show (didn't cancel, didn't show): full fee (€20)

**Technical Notes:**
```typescript
const cancelReservation = async (reservationId: string) => {
  const reservation = await getReservation(reservationId);
  const hoursUntil = differenceInHours(reservation.datetime, new Date());
  
  let fee = 0;
  if (hoursUntil < 2) {
    fee = 10; // late cancellation fee
  }
  
  await supabase
    .from('reservations')
    .update({ status: 'cancelled', cancellation_fee: fee })
    .eq('id', reservationId);
  
  if (fee > 0) {
    await chargeCustomer(reservation.customer_id, fee);
  }
  
  await sendCancellationEmail(reservation);
};
```

---

### 3.5 Enhanced Features

#### FR-C2-020: Restaurant Reviews (Extended)
**Priority:** MEDIUM  
**Description:** Enhanced review system with photos and categories

**Acceptance Criteria:**
- Review form includes: overall rating (1-5 stars), food quality rating (1-5 stars), service rating (1-5 stars), ambiance rating (1-5 stars), text review (max 500 chars), photo upload (max 3 photos, 5MB each)
- Submit → status = 'pending' (awaits moderation)
- "Mark as helpful" button on reviews (upvote system)
- Reviews sorted by: most helpful, newest, highest rating

**Business Rules:**
- One review per order
- Photos optional but encouraged (vendor can offer 50 bonus points for photo reviews)
- Reviews editable within 24 hours of posting

**Technical Notes:**
- Extend `reviews` table:
```typescript
{
  // ... existing fields
  food_rating: number | null;
  service_rating: number | null;
  ambiance_rating: number | null;
  photos: string[]; // URLs
  helpful_count: number; // upvotes
}
```

#### FR-C2-021: Item-Specific Reviews
**Priority:** LOW  
**Description:** Customer reviews individual menu items

**Acceptance Criteria:**
- After order completion, "Rate Items" button
- List of ordered items with star rating (1-5)
- Optional text review per item (max 200 chars)
- Item reviews shown on item detail modal
- Average rating shown on menu cards

**Business Rules:**
- One review per item per order
- Item reviews separate from restaurant reviews
- Vendor can respond to item reviews

**Technical Notes:**
- Store in `item_reviews`:
```typescript
{
  id: string;
  customer_id: string;
  vendor_id: string;
  menu_item_id: string;
  order_id: string;
  rating: number;
  review_text: string | null;
  created_at: timestamp;
}
```

#### FR-C2-022: Pre-Order for Pickup
**Priority:** MEDIUM  
**Description:** Customer schedules order for future pickup

**Acceptance Criteria:**
- Takeaway orders: "Order for Later" option
- Date picker: today to +7 days
- Time picker: available pickup slots
- Order submitted, prepared at scheduled time
- Customer receives notification when ready for pickup

**Business Rules:**
- Min advance notice: 2 hours
- Max advance: 7 days
- Prepayment required (no cash option)
- Vendor can limit slots per time period

**Technical Notes:**
- Store `scheduled_pickup_time` in orders table
- Scheduled orders appear in vendor dashboard at appropriate time
- Cron job moves scheduled orders to active queue

#### FR-C2-023: Group Ordering (Enhanced)
**Priority:** LOW  
**Description:** Host invites friends to add items before submitting

**Acceptance Criteria:**
- "Create Group Order" button (dine-in or takeaway)
- Host generates shareable link
- Friends click link → add items to shared basket
- Host sees all participants and their items
- Host finalizes and submits order
- Split bill options available at checkout

**Business Rules:**
- Host is responsible for payment (unless split bill)
- Max 10 participants per group order
- Group order expires after 2 hours if not submitted

**Technical Notes:**
- Similar to shared basket but with explicit participant list
- Store in `group_orders`:
```typescript
{
  id: string;
  host_customer_id: string;
  invite_code: string; // shareable code
  participants: string[]; // customer IDs
  status: 'open' | 'submitted' | 'expired';
  created_at: timestamp;
}
```

#### FR-C2-024: Dietary Preference Profile
**Priority:** LOW  
**Description:** Customer saves dietary preferences for filtering

**Acceptance Criteria:**
- "Dietary Preferences" in account settings
- Checkboxes: Vegetarian, Vegan, Gluten-free, Dairy-free, Nut allergy, Halal, Kosher, etc.
- "Save Preferences" → applies to all restaurant menus
- Menu items not matching preferences shown with warning icon
- Filter toggle: "Show Only My Preferences"

**Business Rules:**
- Preferences saved per customer
- Warning (not blocking) - customer can still order non-matching items
- Preferences used for recommendations

**Technical Notes:**
- Store in `customer_preferences`:
```typescript
{
  customer_id: string;
  dietary_tags: string[]; // ["vegetarian", "gluten_free"]
  allergens: string[]; // ["nuts", "shellfish"]
}
```

#### FR-C2-025: Order Tracking with Map
**Priority:** LOW (for delivery in Phase 3)  
**Description:** Real-time order tracking with delivery driver on map

**Acceptance Criteria:**
- For delivery orders (Phase 3): map showing driver location
- Estimated arrival time (dynamic)
- Driver name and photo
- "Call Driver" button
- Push notifications: order picked up, driver nearby, delivered

**Business Rules:**
- Delivery only (not dine-in/takeaway)
- Requires GPS permission
- Driver location updated every 10 seconds

**Technical Notes:**
- Integration with delivery partner API or in-house driver app
- WebSocket for real-time location updates
- Use Mapbox/Google Maps for display

#### FR-C2-026: Social Sharing
**Priority:** LOW  
**Description:** Customer shares restaurant or order on social media

**Acceptance Criteria:**
- "Share" button on restaurant page and order confirmation
- Share options: Facebook, Instagram, Twitter, WhatsApp, copy link
- Share content includes: restaurant name, logo, "I just ordered from {name} on TAVLO!", link to restaurant page
- Referral code embedded in shared link (for tracking)

**Business Rules:**
- Shared links public (no login required to view)
- Sharing earns 10 loyalty points (to encourage viral growth)
- Social proof shown: "X friends also ordered here"

**Technical Notes:**
- Use Web Share API for native sharing:
```typescript
const share = async () => {
  await navigator.share({
    title: restaurant.name,
    text: `Check out ${restaurant.name} on TAVLO!`,
    url: `https://tavlo.app/r/${restaurant.slug}?ref=${user.referralCode}`
  });
};
```

#### FR-C2-027: Waitlist for Busy Restaurants
**Priority:** LOW  
**Description:** Join virtual waitlist when restaurant at capacity

**Acceptance Criteria:**
- If no tables available, "Join Waitlist" button
- Enter: name, phone, party size
- Receive estimated wait time (e.g., "~30 minutes")
- SMS notification when table ready
- "I'm here" button to confirm arrival

**Business Rules:**
- Waitlist available for dine-in only
- Vendor can enable/disable feature
- If customer doesn't confirm within 10 minutes, skipped to next

**Technical Notes:**
- Store in `waitlist`:
```typescript
{
  id: string;
  vendor_id: string;
  customer_name: string;
  customer_phone: string;
  party_size: number;
  status: 'waiting' | 'ready' | 'seated' | 'skipped';
  estimated_wait_minutes: number;
  created_at: timestamp;
  notified_at: timestamp | null;
}
```

---

## 4. VENDOR FEATURES

### 4.1 Promotions Management

#### FR-V2-001: Create Promotion
**Priority:** HIGH  
**Description:** Vendor creates promotional offers

**Acceptance Criteria:**
- "Create Promotion" button in dashboard
- Form fields: title (e.g., "Happy Hour 50% Off"), description, discount type (percentage / fixed amount), discount value, promo code (optional, auto-generated if empty), start date, end date, days of week (e.g., only Mondays), time range (e.g., 17:00-19:00), min order value, max uses (total), max uses per customer
- "Save" → promo created, status = 'active'
- Preview showing how promo appears to customers

**Business Rules:**
- Min discount: €1 or 5%
- Max discount: €50 or 90%
- Professional plan feature (Basic plan: max 2 active promos)
- Promo code must be unique per vendor

**Technical Notes:**
- Store in `promotions`:
```typescript
{
  id: string;
  vendor_id: string;
  title: string;
  description: string;
  discount_type: 'percentage' | 'fixed';
  discount_value: number;
  code: string | null;
  start_date: date;
  end_date: date;
  days_of_week: number[]; // [1, 2, 3] for Mon, Tue, Wed
  time_start: string | null; // "17:00"
  time_end: string | null; // "19:00"
  min_order_value: number | null;
  max_uses: number | null;
  max_uses_per_customer: number | null;
  times_used: number; // counter
  active: boolean;
  created_at: timestamp;
}
```

#### FR-V2-002: Manage Promotions
**Priority:** MEDIUM  
**Description:** Vendor views and edits active promotions

**Acceptance Criteria:**
- List of all promotions (active, scheduled, expired)
- Each shows: title, code, discount, start-end dates, uses (X / max), status badge
- Actions: "Edit", "Duplicate", "Deactivate", "Delete"
- Filter by status: active, scheduled, expired
- Analytics per promo: total uses, revenue impact, conversion rate

**Business Rules:**
- Cannot delete promo with uses (only deactivate)
- Can edit active promo (changes apply immediately)
- Duplicate creates copy with new code

**Technical Notes:**
- Status computed:
```typescript
const getPromoStatus = (promo) => {
  const now = new Date();
  if (now < promo.start_date) return 'scheduled';
  if (now > promo.end_date) return 'expired';
  if (!promo.active) return 'inactive';
  if (promo.max_uses && promo.times_used >= promo.max_uses) return 'exhausted';
  return 'active';
};
```

#### FR-V2-003: Flash Sale Creation
**Priority:** LOW  
**Description:** Vendor creates time-sensitive flash sales

**Acceptance Criteria:**
- "Create Flash Sale" button
- Quick form: discount (percentage), duration (1-24 hours), start time (now / scheduled)
- "Launch Flash Sale" → promo activated immediately
- Notification sent to followers (Phase 3 feature)
- Auto-deactivates when time expires

**Business Rules:**
- Flash sales: max 24 hours duration
- Max 1 active flash sale at a time
- Enterprise plan feature

**Technical Notes:**
- Same `promotions` table with `flash_sale = true`
- Cron job checks expiry every minute, deactivates if expired

#### FR-V2-004: Promotion Analytics
**Priority:** MEDIUM  
**Description:** Vendor analyzes promotion performance

**Acceptance Criteria:**
- Dashboard showing per promo: total uses, unique customers, revenue with promo, revenue without promo (baseline), conversion rate increase, ROI
- Chart: promo usage over time
- Top performing promos (by revenue impact)

**Business Rules:**
- Professional plan feature
- Data updated daily
- Comparison to non-promo periods

**Technical Notes:**
- Aggregate query:
```sql
SELECT 
  p.title,
  COUNT(o.id) as total_uses,
  COUNT(DISTINCT o.customer_id) as unique_customers,
  SUM(o.total) as revenue_with_promo,
  AVG(o.total) as avg_order_value
FROM promotions p
JOIN orders o ON o.promo_code = p.code
WHERE p.vendor_id = :id
GROUP BY p.id;
```

---

### 4.2 Loyalty Program Management

#### FR-V2-005: Configure Loyalty Program
**Priority:** HIGH  
**Description:** Vendor sets up loyalty program rules

**Acceptance Criteria:**
- Settings page with fields: points earning rate (default: 1 pt per €1, range: 0.5-5), redemption rate (default: 100 pts = €1, range: 50-200), min redemption (default: 100 pts), bonus points for actions (signup, review, referral), tier thresholds (Bronze, Silver, Gold, Platinum)
- "Enable Loyalty Program" toggle
- Preview showing how it appears to customers

**Business Rules:**
- Professional plan feature (Basic plan: no loyalty)
- Cannot disable if customers have points balance (must honor existing points)
- Tier benefits customizable

**Technical Notes:**
- Store in `loyalty_program_config`:
```typescript
{
  vendor_id: string;
  enabled: boolean;
  points_per_euro: number;
  redemption_rate: number; // pts per euro
  min_redemption_points: number;
  bonus_signup: number;
  bonus_review: number;
  bonus_referral: number;
  tiers: Array<{
    name: string;
    min_points: number;
    multiplier: number;
    perks: string[];
  }>;
}
```

#### FR-V2-006: View Customer Loyalty Status
**Priority:** MEDIUM  
**Description:** Vendor sees customer loyalty points and tier

**Acceptance Criteria:**
- Customer detail view shows: points balance, tier, points earned (total), points redeemed, avg order value
- Transaction history: list of point earnings/redemptions
- "Award Bonus Points" button (manual adjustment)

**Business Rules:**
- Vendor cannot deduct points (only award bonus)
- Manual adjustments logged in audit trail
- Bonus points require reason

**Technical Notes:**
- Query customer points:
```sql
SELECT 
  SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as earned,
  SUM(CASE WHEN points < 0 THEN points ELSE 0 END) as redeemed,
  SUM(points) as balance
FROM loyalty_points
WHERE customer_id = :id AND vendor_id = :vendorId;
```

#### FR-V2-007: Loyalty Program Analytics
**Priority:** MEDIUM  
**Description:** Vendor views loyalty program performance

**Acceptance Criteria:**
- Dashboard showing: total active loyalty members, total points issued, total points redeemed, redemption rate (redeemed / issued), avg points balance per customer, tier distribution (Bronze: X, Silver: Y, etc.)
- Chart: loyalty signups over time
- Loyalty impact: revenue from loyalty customers vs non-loyalty

**Business Rules:**
- Professional plan feature
- Data refreshed daily

**Technical Notes:**
- Aggregate queries:
```sql
-- Active members
SELECT COUNT(DISTINCT customer_id) 
FROM loyalty_points 
WHERE vendor_id = :id;

-- Points issued vs redeemed
SELECT 
  SUM(CASE WHEN points > 0 THEN points ELSE 0 END) as issued,
  SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as redeemed
FROM loyalty_points
WHERE vendor_id = :id;
```

---

### 4.3 Reservations Management

#### FR-V2-008: Enable Reservations
**Priority:** MEDIUM  
**Description:** Vendor configures reservation settings

**Acceptance Criteria:**
- "Enable Reservations" toggle in settings
- Configuration fields: table capacity (total seats), time slot duration (15/30/60 min), advance booking window (max days ahead), cancellation policy (free cancel up to X hours before), deposit required (boolean), deposit amount
- "Save Settings" → reservations enabled

**Business Rules:**
- Professional plan feature
- Requires table setup (table numbers, capacities)
- Deposit processed via Stripe

**Technical Notes:**
- Store in `reservation_settings`:
```typescript
{
  vendor_id: string;
  enabled: boolean;
  total_capacity: number;
  slot_duration_minutes: number;
  max_advance_days: number;
  cancellation_hours: number;
  require_deposit: boolean;
  deposit_amount: number;
}
```

#### FR-V2-009: Reservation Calendar
**Priority:** MEDIUM  
**Description:** Vendor views reservations in calendar format

**Acceptance Criteria:**
- Calendar view: day/week/month
- Each reservation shown: time, party size, customer name, status
- Click reservation → detail modal with: customer info, special requests, order history (if available), actions (confirm, cancel, mark no-show)
- Drag-and-drop to reschedule (if allowed)

**Business Rules:**
- Confirmed reservations highlighted
- Pending reservations shown in different color
- Past reservations greyed out

**Technical Notes:**
- Use calendar library: FullCalendar or react-big-calendar
- Fetch reservations:
```sql
SELECT * FROM reservations
WHERE vendor_id = :id
AND date BETWEEN :start AND :end
ORDER BY date, time_slot;
```

#### FR-V2-010: Manage Reservation
**Priority:** MEDIUM  
**Description:** Vendor confirms, modifies, or cancels reservations

**Acceptance Criteria:**
- Reservation detail modal with actions:
  - **Confirm:** status = 'confirmed', email sent to customer
  - **Modify:** change time/table, requires customer notification
  - **Cancel:** with reason (sent to customer)
  - **Mark No-Show:** if customer didn't arrive, charge no-show fee
- Notes field for internal use

**Business Rules:**
- Confirmed reservations cannot be cancelled <2 hours before
- No-show fee: €20 (configurable), charged to customer payment method
- 3 no-shows → customer blacklisted

**Technical Notes:**
```typescript
const markNoShow = async (reservationId: string) => {
  const reservation = await getReservation(reservationId);
  
  await supabase
    .from('reservations')
    .update({ status: 'no_show' })
    .eq('id', reservationId);
  
  // Charge no-show fee
  const fee = await getNoShowFee(reservation.vendor_id);
  if (fee > 0 && reservation.customer_id) {
    await chargeCustomer(reservation.customer_id, fee, 'no_show_fee');
  }
  
  // Update customer no-show count
  await incrementNoShowCount(reservation.customer_id);
};
```

#### FR-V2-011: Waitlist Management
**Priority:** LOW  
**Description:** Vendor manages virtual waitlist

**Acceptance Criteria:**
- Waitlist dashboard showing: current waitlist (ordered by join time), party sizes, estimated wait
- Actions: "Notify Next" (sends SMS to next customer), "Remove from Waitlist", "Seat Now"
- Auto-notification when table becomes available

**Business Rules:**
- Waitlist ordered by join time (FIFO)
- Notified customers have 10 min to confirm or skipped
- Vendor can manually adjust order

**Technical Notes:**
- Query waitlist:
```sql
SELECT * FROM waitlist
WHERE vendor_id = :id
AND status = 'waiting'
ORDER BY created_at ASC;
```

---

### 4.4 Menu Enhancements

#### FR-V2-012: Ingredient Inventory Tracking
**Priority:** MEDIUM  
**Description:** Vendor tracks ingredient stock levels

**Acceptance Criteria:**
- Ingredients page: list of ingredients (name, current stock, unit, reorder level)
- "Add Ingredient" button → form: name, unit (kg, liters, pieces), current stock, reorder level, supplier info
- Low stock alert (when stock < reorder level)
- Link ingredients to menu items (recipe management)
- Auto-mark items unavailable when key ingredient out of stock

**Business Rules:**
- Professional plan feature
- Optional feature (vendor can ignore)
- Stock updates manual (no auto-deduction yet - Phase 3)

**Technical Notes:**
- Store in `ingredients`:
```typescript
{
  id: string;
  vendor_id: string;
  name: string;
  unit: string; // 'kg', 'liters', 'pieces'
  current_stock: number;
  reorder_level: number;
  supplier: string | null;
  last_updated: timestamp;
}
```
- Link to items via `menu_item_ingredients`:
```typescript
{
  menu_item_id: string;
  ingredient_id: string;
  quantity_required: number;
}
```

#### FR-V2-013: Menu Item Variants
**Priority:** LOW  
**Description:** Vendor creates item variants (sizes, options)

**Acceptance Criteria:**
- Item edit form → "Variants" section
- Add variant: name (e.g., "Small", "Medium", "Large"), price adjustment (+ or -)
- Customer sees variants as options (radio buttons)
- Example: Pizza - Small (€8), Medium (+€3 = €11), Large (+€6 = €14)

**Business Rules:**
- Max 5 variants per item
- One variant selected (default marked with asterisk)
- Variant prices relative to base price

**Technical Notes:**
- Store in `menu_item_variants`:
```typescript
{
  id: string;
  menu_item_id: string;
  name: string;
  price_adjustment: number; // positive or negative
  is_default: boolean;
  sort_order: number;
}
```

#### FR-V2-014: Modifiers & Extras
**Priority:** MEDIUM  
**Description:** Vendor configures item modifiers (e.g., "No onions", "Extra cheese")

**Acceptance Criteria:**
- Item edit → "Modifiers" section
- Add modifier group: name (e.g., "Toppings"), type (single-select / multi-select), options (e.g., "Cheese", "Pepperoni"), price per option
- Customer sees modifiers as checkboxes or radio buttons
- Example: "Choose sauce: Tomato (free), BBQ (+€1), Alfredo (+€2)"

**Business Rules:**
- Max 10 modifier groups per item
- Single-select: one option only (radio buttons)
- Multi-select: multiple options (checkboxes)

**Technical Notes:**
- Store in `modifier_groups`:
```typescript
{
  id: string;
  menu_item_id: string;
  name: string;
  type: 'single' | 'multi';
  required: boolean;
  options: Array<{
    name: string;
    price: number;
  }>;
}
```

#### FR-V2-015: Combo Meals / Bundles
**Priority:** LOW  
**Description:** Vendor creates meal combos at discounted price

**Acceptance Criteria:**
- "Create Combo" button
- Select multiple items (e.g., Burger + Fries + Drink)
- Set combo price (less than sum of individual prices)
- Combo shown as single menu item
- Customer can customize combo items (if allowed)

**Business Rules:**
- Min 2 items per combo
- Combo price must be less than individual sum
- Professional plan feature

**Technical Notes:**
- Store in `combo_meals`:
```typescript
{
  id: string;
  vendor_id: string;
  name: string;
  description: string;
  combo_price: number;
  items: Array<{
    menu_item_id: string;
    quantity: number;
    customizable: boolean;
  }>;
  available: boolean;
}
```

---

### 4.5 Operations

#### FR-V2-016: Kitchen Display System (KDS) Integration
**Priority:** HIGH  
**Description:** Orders displayed on kitchen screens in real-time

**Acceptance Criteria:**
- Dedicated KDS view (tablet/monitor mode)
- Orders shown as cards: order number, table, items, time elapsed, priority (normal/urgent)
- Color coding: green (<10 min), yellow (10-20 min), red (>20 min)
- Tap order → mark items prepared, bump to next screen
- Audio alert on new orders

**Business Rules:**
- Professional plan feature
- Multiple KDS screens supported (different stations)
- Auto-refresh every 5 seconds

**Technical Notes:**
- KDS view: simplified UI optimized for kitchen
- Real-time updates via Supabase Realtime
- Fullscreen mode, no menu/navigation
```typescript
// KDS screen component
const KitchenDisplay = () => {
  const orders = useRealtimeOrders(vendorId);
  
  return (
    <div className="grid grid-cols-3 gap-4 p-4">
      {orders.map(order => (
        <OrderCard 
          key={order.id}
          order={order}
          colorCode={getColorCode(order.created_at)}
          onBump={() => markPrepared(order.id)}
        />
      ))}
    </div>
  );
};
```

#### FR-V2-017: Order Preparation Time Tracking
**Priority:** MEDIUM  
**Description:** Track average preparation time per item/category

**Acceptance Criteria:**
- Analytics showing: avg prep time per item, avg prep time per category, slowest items, fastest items
- Prep time = (marked ready timestamp - order submitted timestamp)
- Chart: prep time trend over last 30 days
- Use data to adjust estimated ready times

**Business Rules:**
- Only completed orders counted
- Outliers excluded (>2 hours prep time)
- Data used to optimize kitchen operations

**Technical Notes:**
- Calculate on order completion:
```sql
SELECT 
  menu_item_id,
  AVG(EXTRACT(EPOCH FROM (completed_at - created_at)) / 60) as avg_prep_minutes
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
WHERE o.status = 'completed'
AND o.created_at > NOW() - INTERVAL '30 days'
GROUP BY menu_item_id;
```

#### FR-V2-018: Staff Management (Basic)
**Priority:** MEDIUM  
**Description:** Vendor adds staff users with limited permissions

**Acceptance Criteria:**
- "Staff" page → "Add Staff Member" button
- Form: name, email, role (Kitchen Staff, Server, Manager)
- Permissions per role:
  - Kitchen: view/manage orders, mark status, no access to menu/settings
  - Server: view orders, call waiter requests, no access to analytics
  - Manager: full access except billing
- Staff receives invitation email, sets password

**Business Rules:**
- Professional plan: max 5 staff users
- Enterprise plan: unlimited staff
- Only vendor owner can add/remove staff

**Technical Notes:**
- Store in `vendor_staff`:
```typescript
{
  id: string;
  vendor_id: string;
  email: string;
  role: 'kitchen' | 'server' | 'manager';
  permissions: string[]; // granular permissions
  active: boolean;
  invited_at: timestamp;
  accepted_at: timestamp | null;
}
```
- Use Supabase RLS to enforce permissions

#### FR-V2-019: Order Throttling
**Priority:** MEDIUM  
**Description:** Vendor limits concurrent orders to prevent overwhelm

**Acceptance Criteria:**
- Settings: "Max concurrent orders" (e.g., 10)
- When limit reached, new QR scans show: "We're at capacity. Orders will reopen in ~15 min"
- Countdown timer for customers
- Auto-reopen when orders drop below limit

**Business Rules:**
- Concurrent orders = orders with status 'submitted' or 'preparing'
- Does not apply to scheduled pickup orders
- Vendor can override limit temporarily

**Technical Notes:**
- Check on order submission:
```typescript
const canAcceptOrder = async (vendorId: string) => {
  const { data: settings } = await supabase
    .from('vendor_settings')
    .select('max_concurrent_orders')
    .eq('vendor_id', vendorId)
    .single();
  
  const { count } = await supabase
    .from('orders')
    .select('*', { count: 'exact', head: true })
    .eq('vendor_id', vendorId)
    .in('status', ['submitted', 'preparing']);
  
  return count < settings.max_concurrent_orders;
};
```

#### FR-V2-020: Printer Integration
**Priority:** LOW  
**Description:** Auto-print order tickets to receipt printer

**Acceptance Criteria:**
- Settings: "Auto-print orders" toggle
- Select printer (network printer or USB)
- Print template: order number, table, items, time, customizations
- Test print button

**Business Rules:**
- Thermal printer support (80mm)
- Professional plan feature
- Fallback: manual print from browser

**Technical Notes:**
- Use browser Print API or WebUSB
- Send print job via API to network printer
```typescript
const printOrder = async (order: Order) => {
  const template = generatePrintTemplate(order);
  
  // Option 1: Browser print
  window.print();
  
  // Option 2: Network printer API
  await fetch(`http://${printerIP}/print`, {
    method: 'POST',
    body: template
  });
};
```

---

### 4.6 Advanced Analytics

#### FR-V2-021: Peak Hours Analysis
**Priority:** MEDIUM  
**Description:** Vendor identifies busiest times

**Acceptance Criteria:**
- Chart: orders per hour (heatmap)
- Breakdown by day of week
- Identifies peak hours (e.g., "Busiest: Fri-Sat 18:00-20:00")
- Staffing recommendations based on data

**Business Rules:**
- Data for last 90 days
- Professional plan feature

**Technical Notes:**
```sql
SELECT 
  EXTRACT(DOW FROM created_at) as day_of_week,
  EXTRACT(HOUR FROM created_at) as hour,
  COUNT(*) as order_count
FROM orders
WHERE vendor_id = :id
AND created_at > NOW() - INTERVAL '90 days'
GROUP BY day_of_week, hour
ORDER BY day_of_week, hour;
```

#### FR-V2-022: Customer Insights
**Priority:** MEDIUM  
**Description:** Vendor sees customer behavior patterns

**Acceptance Criteria:**
- Dashboard showing: new customers this month, returning customers, avg orders per customer, customer lifetime value (CLV), churn rate
- Top customers by spend
- Customer segmentation: one-time, occasional, regular, VIP

**Business Rules:**
- Anonymized data (no individual customer details without consent)
- Professional plan feature

**Technical Notes:**
```sql
-- Customer segments
SELECT 
  customer_id,
  COUNT(*) as order_count,
  SUM(total) as total_spent,
  CASE 
    WHEN COUNT(*) = 1 THEN 'one_time'
    WHEN COUNT(*) <= 3 THEN 'occasional'
    WHEN COUNT(*) <= 10 THEN 'regular'
    ELSE 'vip'
  END as segment
FROM orders
WHERE vendor_id = :id
AND customer_id IS NOT NULL
GROUP BY customer_id;
```

#### FR-V2-023: Revenue Forecasting
**Priority:** LOW  
**Description:** Predict future revenue based on trends

**Acceptance Criteria:**
- Chart: projected revenue next 30 days
- Based on: historical data, seasonality, growth rate
- Confidence interval shown (optimistic/pessimistic)
- Alerts if forecast significantly different from actual

**Business Rules:**
- Enterprise plan feature
- Requires min 6 months of data
- Updated weekly

**Technical Notes:**
- Simple linear regression Phase 2:
```typescript
const forecastRevenue = (historicalData: number[]) => {
  // Calculate trend line
  const trend = calculateLinearRegression(historicalData);
  // Project next 30 days
  const forecast = [];
  for (let i = 1; i <= 30; i++) {
    forecast.push(trend.slope * i + trend.intercept);
  }
  return forecast;
};
```
- Phase 3: ML model

#### FR-V2-024: A/B Testing (Menu Items)
**Priority:** LOW  
**Description:** Test different item descriptions/prices

**Acceptance Criteria:**
- Create A/B test: select item, create variant B (different description/price)
- Split traffic: 50/50 or custom ratio
- Track: views, add-to-cart rate, conversion rate
- Declare winner after statistical significance

**Business Rules:**
- Enterprise plan feature
- Max 3 concurrent tests
- Min 100 views per variant for significance

**Technical Notes:**
- Store in `ab_tests`:
```typescript
{
  id: string;
  vendor_id: string;
  menu_item_id: string;
  variant_a: { description: string, price: number },
  variant_b: { description: string, price: number },
  traffic_split: number, // 0-100 for variant A
  status: 'running' | 'completed',
  winner: 'a' | 'b' | null
}
```

---

### 4.7 Vendor Collaboration

#### FR-V2-025: Multi-Location Support (Basic)
**Priority:** MEDIUM  
**Description:** Vendor manages multiple restaurant locations

**Acceptance Criteria:**
- "Add Location" button
- Each location has: name, address, separate menu, separate QR codes, shared vendor account
- Switch between locations in dashboard
- Aggregated analytics across all locations

**Business Rules:**
- Professional plan: max 3 locations
- Enterprise plan: unlimited locations
- Each location can have different settings

**Technical Notes:**
- Extend `vendors` table:
```typescript
{
  // ... existing fields
  is_chain: boolean;
  parent_vendor_id: string | null; // for child locations
}
```
- Location-specific data filtered by `vendor_id`

#### FR-V2-026: Vendor Messaging (Customer Support)
**Priority:** LOW  
**Description:** Direct messaging between vendor and customers

**Acceptance Criteria:**
- "Messages" tab in dashboard
- List of conversations (customer name, last message, unread badge)
- Click conversation → chat interface
- Customer can initiate from order detail page ("Contact Restaurant")
- Real-time messaging (WebSocket)

**Business Rules:**
- Messages retained 90 days
- Customer can block vendor (no spam)
- Profanity filter

**Technical Notes:**
- Store in `messages`:
```typescript
{
  id: string;
  conversation_id: string;
  sender_type: 'vendor' | 'customer';
  sender_id: string;
  message_text: string;
  created_at: timestamp;
  read_at: timestamp | null;
}
```

#### FR-V2-027: Vendor Reviews & Ratings (Public Profile)
**Priority:** MEDIUM  
**Description:** Vendor profile shows aggregated ratings

**Acceptance Criteria:**
- Restaurant page displays: overall rating (1-5 stars), total review count, rating breakdown (5★: X, 4★: Y, etc.), recent reviews (top 3)
- "View All Reviews" link
- Reviews sorted by: most helpful, newest, highest/lowest rating

**Business Rules:**
- Only approved reviews shown
- Min 5 reviews to show rating
- Vendor responses shown below reviews

**Technical Notes:**
- Calculate avg rating:
```sql
SELECT 
  AVG(rating) as avg_rating,
  COUNT(*) as review_count
FROM reviews
WHERE restaurant_id = :id
AND status = 'approved';
```

---

### 4.8 Vendor Monetization

#### FR-V2-028: Upsell Suggestions
**Priority:** LOW  
**Description:** Vendor configures recommended items shown at checkout

**Acceptance Criteria:**
- Settings: "Upsell Items" section
- Select items to recommend (e.g., "Add a drink?")
- Shown at checkout: "You might also like: {item}" with quick add button
- Track upsell conversion rate

**Business Rules:**
- Max 3 upsell items per order
- Professional plan feature
- Can be item-specific (e.g., suggest fries with burger)

**Technical Notes:**
- Store in `upsell_rules`:
```typescript
{
  vendor_id: string;
  trigger_item_id: string | null, // null = show for all orders
  suggested_item_id: string;
  sort_order: number;
}
```

#### FR-V2-029: Dynamic Pricing (Time-Based)
**Priority:** LOW  
**Description:** Vendor sets different prices for different times

**Acceptance Criteria:**
- Item edit → "Dynamic Pricing" toggle
- Set prices per time slot (e.g., Happy Hour: 50% off 17:00-19:00)
- Customer sees appropriate price based on current time
- Pricing schedule shown on item detail

**Business Rules:**
- Enterprise plan feature
- Max 5 pricing rules per item
- Cannot overlap time slots

**Technical Notes:**
- Store in `dynamic_pricing`:
```typescript
{
  menu_item_id: string;
  days_of_week: number[];
  time_start: string;
  time_end: string;
  price_adjustment_type: 'percentage' | 'fixed';
  price_adjustment_value: number;
}
```

#### FR-V2-030: Sponsored Placement (Discovery)
**Priority:** LOW  
**Description:** Vendor pays for featured placement in discovery

**Acceptance Criteria:**
- "Promote Restaurant" button
- Select campaign: duration (7/14/30 days), budget (€50-500)
- Restaurant shown at top of search results and discovery page
- "Sponsored" badge shown
- Campaign analytics: impressions, clicks, orders attributed

**Business Rules:**
- Enterprise plan only
- Min budget: €50 for 7 days
- Max 5 sponsored restaurants per search result page

**Technical Notes:**
- Store in `sponsored_campaigns`:
```typescript
{
  id: string;
  vendor_id: string;
  start_date: date;
  end_date: date;
  budget: number;
  spent: number; // tracks spend
  impressions: number;
  clicks: number;
  orders: number;
  status: 'active' | 'paused' | 'completed';
}
```

---

### 4.9 Additional Features

#### FR-V2-031: Menu Translation (AI-Powered)
**Priority:** MEDIUM  
**Description:** Auto-translate menu items using AI

**Acceptance Criteria:**
- "Auto-Translate Menu" button
- Select target languages (11 languages supported)
- AI translates: item names, descriptions, category names
- Review and edit translations before publishing
- "Publish Translations" button

**Business Rules:**
- Professional plan feature
- Translation cost: €0.01 per item per language
- Manual editing allowed (override AI)

**Technical Notes:**
- Use OpenAI GPT-4 API:
```typescript
const translateItem = async (item: MenuItem, targetLang: string) => {
  const prompt = `Translate this menu item to ${targetLang}:
    Name: ${item.name}
    Description: ${item.description}
    Keep cultural context and appetizing language.`;
  
  const response = await openai.chat.completions.create({
    model: 'gpt-4',
    messages: [{ role: 'user', content: prompt }]
  });
  
  return response.choices[0].message.content;
};
```

#### FR-V2-032: Event Catering Orders
**Priority:** LOW  
**Description:** Accept large catering orders for events

**Acceptance Criteria:**
- "Catering" section in dashboard
- Catering request form for customers: event date, event type (wedding, corporate, etc.), guest count, menu preferences, budget
- Vendor reviews request, creates custom quote
- Customer approves quote → deposit payment → vendor prepares
- Catering orders tracked separately

**Business Rules:**
- Enterprise plan feature
- Min guest count: 20
- Requires advance notice (min 7 days)

**Technical Notes:**
- Store in `catering_orders`:
```typescript
{
  id: string;
  vendor_id: string;
  customer_id: string;
  event_date: date;
  guest_count: number;
  menu_items: Array<{ item_id: string, quantity: number }>;
  total_quote: number;
  deposit_paid: boolean;
  status: 'requested' | 'quoted' | 'approved' | 'completed';
}
```

#### FR-V2-033: Gift Cards
**Priority:** LOW  
**Description:** Vendor sells digital gift cards

**Acceptance Criteria:**
- "Gift Cards" page in dashboard
- Create gift card: amount (€10-200), design template
- Customer purchases gift card → receives code via email
- Redeems at checkout (code applied as credit)
- Track gift card sales and redemptions

**Business Rules:**
- Professional plan feature
- Gift cards never expire
- Refundable within 30 days if unused

**Technical Notes:**
- Store in `gift_cards`:
```typescript
{
  id: string;
  vendor_id: string;
  code: string; // unique 12-char code
  amount: number;
  balance: number;
  purchased_by_customer_id: string | null;
  redeemed_by_customer_id: string | null;
  status: 'active' | 'redeemed' | 'refunded';
  created_at: timestamp;
}
```

---

## 5. ADMIN FEATURES

### 5.1 Advanced Moderation

#### FR-A2-001: AI Content Moderation
**Priority:** MEDIUM  
**Description:** AI automatically flags inappropriate reviews/content

**Acceptance Criteria:**
- All reviews, vendor descriptions, menu items scanned by AI on submission
- AI detects: profanity, hate speech, spam, fake reviews, off-topic content
- Flagged content sent to admin queue for manual review
- AI confidence score shown (low/medium/high)
- Admin can approve or reject

**Business Rules:**
- AI flags, human moderates (no auto-rejection)
- False positives tracked (improve AI accuracy)
- Vendor notified if content flagged

**Technical Notes:**
- Use OpenAI Moderation API:
```typescript
const moderateContent = async (text: string) => {
  const response = await openai.moderations.create({
    input: text
  });
  
  const flagged = response.results[0].flagged;
  const categories = response.results[0].categories;
  
  if (flagged) {
    await flagForReview(text, categories);
  }
};
```

#### FR-A2-002: Bulk Actions
**Priority:** MEDIUM  
**Description:** Admin performs bulk operations

**Acceptance Criteria:**
- Select multiple items (vendors, reviews, orders, etc.) via checkboxes
- Bulk actions: approve, reject, suspend, delete, export
- Confirmation modal showing count: "Approve 15 reviews?"
- Progress bar for long operations
- Success toast: "15 reviews approved"

**Business Rules:**
- Max 100 items per bulk action
- Irreversible actions require confirmation
- Audit log records bulk actions

**Technical Notes:**
- Batch database updates for performance
```typescript
const bulkApproveReviews = async (reviewIds: string[]) => {
  await supabase
    .from('reviews')
    .update({ status: 'approved', moderated_at: new Date() })
    .in('id', reviewIds);
  
  // Log audit event
  await logAuditEvent({
    action: 'bulk_approve_reviews',
    count: reviewIds.length,
    review_ids: reviewIds
  });
};
```

#### FR-A2-003: Automated Spam Detection
**Priority:** LOW  
**Description:** Detect and block spam accounts/reviews

**Acceptance Criteria:**
- Spam indicators: duplicate content, rapid submissions, suspicious patterns
- Auto-flag accounts with spam score >80%
- Admin reviews flagged accounts
- Can ban user (all content deleted, IP blocked)

**Business Rules:**
- Spam score based on: content similarity, submission frequency, email domain, IP reputation
- Banned users cannot create new accounts (email/IP blocked)

**Technical Notes:**
- Calculate spam score:
```typescript
const calculateSpamScore = async (user: User) => {
  let score = 0;
  
  // Check review similarity
  const reviews = await getUserReviews(user.id);
  if (hasDuplicateContent(reviews)) score += 40;
  
  // Check submission frequency
  if (reviews.length > 10 && user.created_at > subDays(new Date(), 1)) score += 30;
  
  // Check email domain
  if (isDisposableEmail(user.email)) score += 20;
  
  // Check IP reputation
  const ipScore = await checkIPReputation(user.last_ip);
  score += ipScore;
  
  return score;
};
```

---

### 5.2 Platform Growth

#### FR-A2-004: Vendor Acquisition Dashboard
**Priority:** MEDIUM  
**Description:** Track vendor acquisition funnel

**Acceptance Criteria:**
- Metrics: signups this month, approvals, rejections, approval rate, time to approval (avg)
- Funnel chart: visits → signups → approvals → active
- Conversion rates at each stage
- Geographic breakdown (city-level)

**Business Rules:**
- Data refreshed daily
- Anonymized data only

**Technical Notes:**
```sql
SELECT 
  COUNT(*) FILTER (WHERE status = 'pending_approval') as pending,
  COUNT(*) FILTER (WHERE status = 'active') as active,
  COUNT(*) FILTER (WHERE status = 'rejected') as rejected,
  AVG(EXTRACT(EPOCH FROM (approved_at - created_at)) / 3600) as avg_approval_hours
FROM vendors
WHERE created_at > DATE_TRUNC('month', CURRENT_DATE);
```

#### FR-A2-005: Customer Growth Metrics
**Priority:** MEDIUM  
**Description:** Track customer acquisition and retention

**Acceptance Criteria:**
- Metrics: new signups this month, active users (30-day), retention rate (30/60/90 day), churn rate
- Chart: daily active users (DAU), monthly active users (MAU)
- Cohort analysis: retention by signup month

**Business Rules:**
- Active user = placed at least 1 order in period
- Retention = users who returned after first order

**Technical Notes:**
```sql
-- Retention cohort
SELECT 
  DATE_TRUNC('month', first_order_date) as cohort_month,
  COUNT(DISTINCT customer_id) as cohort_size,
  COUNT(DISTINCT CASE 
    WHEN last_order_date > first_order_date + INTERVAL '30 days' 
    THEN customer_id 
  END) as retained_30d
FROM (
  SELECT 
    customer_id,
    MIN(created_at) as first_order_date,
    MAX(created_at) as last_order_date
  FROM orders
  GROUP BY customer_id
) cohorts
GROUP BY cohort_month;
```

#### FR-A2-006: Platform Revenue Dashboard
**Priority:** HIGH  
**Description:** Admin tracks platform revenue and fees

**Acceptance Criteria:**
- Metrics: total GMV (Gross Merchandise Value), platform fees collected, subscription MRR, total revenue (fees + subscriptions)
- Breakdown: revenue by vendor, revenue by plan (Basic/Pro/Enterprise)
- Chart: revenue over time (daily/weekly/monthly)
- Payout status: pending, processed

**Business Rules:**
- GMV = total order value across all vendors
- Platform fees = transaction fees (2% + €0.30 per order)
- Subscription MRR = monthly recurring revenue from subscriptions

**Technical Notes:**
```sql
SELECT 
  SUM(o.total) as gmv,
  SUM(o.total * 0.02 + 0.30) as platform_fees,
  (SELECT SUM(
    CASE 
      WHEN plan = 'basic' THEN 29
      WHEN plan = 'professional' THEN 79
      WHEN plan = 'enterprise' THEN 199
    END
  ) FROM vendors WHERE subscription_status = 'active') as subscription_mrr
FROM orders o
WHERE o.created_at >= DATE_TRUNC('month', CURRENT_DATE);
```

#### FR-A2-007: Marketing Campaign Tracking
**Priority:** LOW  
**Description:** Track effectiveness of marketing campaigns

**Acceptance Criteria:**
- Create campaign: name, UTM parameters, budget, goals
- Track: visits, signups, conversions (vendor or customer)
- ROI calculation: (revenue - cost) / cost
- Campaign comparison table

**Business Rules:**
- Campaign data retained 1 year
- Attribution window: 30 days

**Technical Notes:**
- Track via UTM parameters in URL:
```typescript
// Capture UTM params on page load
const captureUTM = () => {
  const params = new URLSearchParams(window.location.search);
  const utmData = {
    source: params.get('utm_source'),
    medium: params.get('utm_medium'),
    campaign: params.get('utm_campaign')
  };
  // Store in cookie for attribution
  setCookie('utm_data', utmData, { maxAge: 30 * 24 * 60 * 60 });
};
```

---

### 5.3 Compliance & Security

#### FR-A2-008: GDPR Data Export
**Priority:** HIGH  
**Description:** Admin exports user data for GDPR requests

**Acceptance Criteria:**
- "Data Export" tool
- Enter user email → fetch all associated data (profile, orders, reviews, loyalty points, etc.)
- Generate ZIP file with JSON exports
- Download link sent to user via email
- Audit log records export

**Business Rules:**
- GDPR compliance (EU regulation)
- Must respond within 30 days of request
- Data includes all PII

**Technical Notes:**
```typescript
const exportUserData = async (email: string) => {
  const customer = await getCustomerByEmail(email);
  const orders = await getCustomerOrders(customer.id);
  const reviews = await getCustomerReviews(customer.id);
  const loyaltyPoints = await getLoyaltyPoints(customer.id);
  
  const dataPackage = {
    profile: customer,
    orders: orders,
    reviews: reviews,
    loyalty_points: loyaltyPoints,
    exported_at: new Date().toISOString()
  };
  
  const zip = await createZip(dataPackage);
  await sendEmail({
    to: email,
    subject: 'Your TAVLO Data Export',
    attachments: [{ filename: 'data.zip', content: zip }]
  });
  
  await logAuditEvent({ action: 'gdpr_export', user_email: email });
};
```

#### FR-A2-009: Data Retention Policies
**Priority:** MEDIUM  
**Description:** Automated data cleanup per retention policies

**Acceptance Criteria:**
- Configurable retention periods per data type:
  - Orders: 7 years (Austrian law)
  - Analytics: 90 days
  - Session data: 30 days
  - Deleted accounts: 30 days (then permanently delete)
- Daily cron job deletes expired data
- Audit log records deletions

**Business Rules:**
- Orders/invoices: 7 years retention (legal requirement)
- User data: delete 30 days after account deletion
- Analytics: aggregate after 90 days, delete granular data

**Technical Notes:**
```typescript
// Daily cron job
const cleanupExpiredData = async () => {
  // Delete old session data
  await supabase
    .from('table_sessions')
    .delete()
    .lt('created_at', subDays(new Date(), 30));
  
  // Delete old analytics events
  await supabase
    .from('analytics_events')
    .delete()
    .lt('created_at', subDays(new Date(), 90));
  
  // Permanently delete accounts marked for deletion
  await supabase
    .from('customers')
    .delete()
    .not('deleted_at', 'is', null)
    .lt('deleted_at', subDays(new Date(), 30));
  
  await logAuditEvent({ action: 'data_retention_cleanup' });
};
```

#### FR-A2-010: Security Dashboard
**Priority:** MEDIUM  
**Description:** Monitor platform security threats

**Acceptance Criteria:**
- Dashboard showing: failed login attempts (last 24h), suspicious activity alerts, blocked IPs, rate limit violations
- Chart: security events over time
- Action buttons: "Block IP", "Investigate User", "Clear Alert"

**Business Rules:**
- Auto-block IP after 10 failed login attempts in 1 hour
- Alerts sent to admin email for critical events
- Security logs retained 1 year

**Technical Notes:**
- Track failed logins:
```typescript
const trackFailedLogin = async (email: string, ip: string) => {
  await supabase.from('security_events').insert({
    event_type: 'failed_login',
    user_email: email,
    ip_address: ip,
    created_at: new Date()
  });
  
  // Check if IP should be blocked
  const recentAttempts = await supabase
    .from('security_events')
    .select('*')
    .eq('ip_address', ip)
    .eq('event_type', 'failed_login')
    .gte('created_at', subHours(new Date(), 1));
  
  if (recentAttempts.length >= 10) {
    await blockIP(ip);
    await sendSecurityAlert(`IP ${ip} blocked after ${recentAttempts.length} failed login attempts`);
  }
};
```

---

### 5.4 Platform Configuration

#### FR-A2-011: Commission Rate Management
**Priority:** HIGH  
**Description:** Admin adjusts platform commission rates

**Acceptance Criteria:**
- "Commission Settings" page
- Fields: base rate (percentage), fixed fee per transaction, vendor tier rates (Basic: X%, Pro: Y%, Enterprise: Z%)
- Changes apply to new transactions only
- Change history logged

**Business Rules:**
- Default: 2% + €0.30 per transaction
- Vendor tier discounts: Pro (-0.5%), Enterprise (-1%)
- Cannot exceed 10% (legal/competitive limits)

**Technical Notes:**
- Store in `platform_settings`:
```typescript
{
  commission_base_rate: number; // 2.0
  commission_fixed_fee: number; // 0.30
  commission_basic: number; // 2.0
  commission_professional: number; // 1.5
  commission_enterprise: number; // 1.0
}
```

#### FR-A2-012: Payment Gateway Configuration
**Priority:** MEDIUM  
**Description:** Admin configures Stripe settings

**Acceptance Criteria:**
- Stripe API keys (live/test mode)
- Webhook endpoints
- Payout schedule (daily/weekly/monthly)
- Test connection button

**Business Rules:**
- Live mode keys require two-factor authentication
- Webhook URL must be HTTPS
- Changes require confirmation

**Technical Notes:**
- Store encrypted in environment variables
- Test connection:
```typescript
const testStripeConnection = async () => {
  try {
    const balance = await stripe.balance.retrieve();
    return { success: true, balance };
  } catch (error) {
    return { success: false, error: error.message };
  }
};
```

#### FR-A2-013: Feature Rollout Management
**Priority:** MEDIUM  
**Description:** Gradual feature rollout to subset of users

**Acceptance Criteria:**
- Feature flags with percentage rollout (0-100%)
- Select target audience: all users, specific vendors, specific plans
- Monitor feature usage and errors
- Quick rollback button

**Business Rules:**
- New features start at 0% (admin-only)
- Gradual increase: 10% → 25% → 50% → 100%
- Auto-rollback if error rate >5%

**Technical Notes:**
- Feature flag system with targeting:
```typescript
const isFeatureEnabled = (featureKey: string, userId: string) => {
  const flag = getFeatureFlag(featureKey);
  
  if (!flag.enabled) return false;
  
  // Check targeting rules
  if (flag.targetVendors && flag.targetVendors.includes(userId)) return true;
  
  // Check percentage rollout
  const hash = hashUserId(userId);
  return (hash % 100) < flag.rolloutPercentage;
};
```

---

## 6. PLATFORM PAGES

### 6.1 Customer-Facing Pages

#### FR-P2-001: Restaurant Discovery Page
**Priority:** HIGH  
**Description:** Browse all restaurants on TAVLO

**Acceptance Criteria:**
- Grid of restaurant cards with infinite scroll
- Filters: cuisine, price range, distance, rating, features (dine-in/takeaway)
- Sort by: distance, rating, newest, popular
- Search bar at top
- Map view toggle

**Business Rules:**
- Only active vendors shown
- Geolocation optional (defaults to city-based)
- SEO-optimized for Google

**Technical Notes:**
- Server-side rendering for SEO
- Pagination or infinite scroll
```typescript
const DiscoveryPage = () => {
  const [filters, setFilters] = useState({});
  const [sort, setSort] = useState('distance');
  
  const { data: restaurants } = useInfiniteQuery(
    ['restaurants', filters, sort],
    ({ pageParam = 0 }) => fetchRestaurants({ filters, sort, page: pageParam })
  );
  
  return (
    <div>
      <FilterSidebar filters={filters} onChange={setFilters} />
      <RestaurantGrid restaurants={restaurants} />
    </div>
  );
};
```

#### FR-P2-002: Cuisine Category Pages
**Priority:** MEDIUM  
**Description:** Dedicated pages per cuisine type

**Acceptance Criteria:**
- URLs: `/cuisine/italian`, `/cuisine/asian`, etc.
- Hero section with cuisine image and description
- List of restaurants in that cuisine
- Popular dishes in that cuisine
- SEO meta tags per cuisine

**Business Rules:**
- Auto-generated pages for each cuisine type
- Updated daily with new restaurants

**Technical Notes:**
- Static page generation (Next.js)
```typescript
export async function getStaticPaths() {
  const cuisines = await getAllCuisines();
  return {
    paths: cuisines.map(c => ({ params: { slug: c.slug } })),
    fallback: false
  };
}
```

#### FR-P2-003: City Landing Pages
**Priority:** MEDIUM  
**Description:** Dedicated pages per city

**Acceptance Criteria:**
- URLs: `/vienna`, `/graz`, etc.
- City hero image
- Top restaurants in city
- Neighborhoods/districts
- Local SEO optimization

**Business Rules:**
- Only cities with 5+ restaurants get pages
- Auto-created when threshold reached

**Technical Notes:**
- Dynamic routing with city slug
- Structured data for local SEO

#### FR-P2-004: Best Restaurants Lists
**Priority:** LOW  
**Description:** Curated lists (Top 10 Pizza in Vienna, etc.)

**Acceptance Criteria:**
- Editorially curated lists
- List page shows: title, description, ranked restaurants
- Click restaurant → view menu
- Social sharing

**Business Rules:**
- Admin creates lists manually
- Updated monthly
- Max 20 lists

**Technical Notes:**
- Store in `curated_lists`:
```typescript
{
  id: string;
  title: string;
  description: string;
  slug: string;
  restaurants: Array<{ vendor_id: string, rank: number, note: string }>;
  published_at: timestamp;
}
```

#### FR-P2-005: Blog / Content Hub
**Priority:** LOW  
**Description:** Content marketing blog

**Acceptance Criteria:**
- Blog listing page with posts
- Post detail pages
- Categories: guides, vendor stories, food trends
- Author profiles
- SEO optimization

**Business Rules:**
- Admin creates posts via CMS
- Publishing scheduled
- Comments disabled (Phase 3)

**Technical Notes:**
- Use headless CMS (Contentful, Sanity)
- Markdown content
- Static generation

---

### 6.2 Vendor-Facing Pages

#### FR-P2-006: Vendor Success Hub
**Priority:** MEDIUM  
**Description:** Resources and guides for vendors

**Acceptance Criteria:**
- Knowledge base articles: Getting Started, Menu Best Practices, Marketing Tips, etc.
- Video tutorials
- FAQ section
- "Contact Support" button

**Business Rules:**
- Content created by admin
- Searchable knowledge base
- Available to all vendors

**Technical Notes:**
- Static content pages
- Search powered by Algolia

#### FR-P2-007: Vendor Community Forum
**Priority:** LOW  
**Description:** Vendor peer support and discussion

**Acceptance Criteria:**
- Forum categories: General, Menu Ideas, Marketing, Technical Support
- Vendors can post topics, reply
- Upvote system
- Admin moderation

**Business Rules:**
- Professional plan feature
- Profanity filter
- Admin can pin important topics

**Technical Notes:**
- Use forum software: Discourse or custom
- SSO integration with vendor accounts

---

### 6.3 Admin-Facing Pages

#### FR-P2-008: Admin Training Portal
**Priority:** LOW  
**Description:** Internal training materials for new admins

**Acceptance Criteria:**
- Training modules: Platform Overview, Vendor Approval Process, Review Moderation, Security
- Video tutorials
- Quizzes
- Completion tracking

**Business Rules:**
- Required for new admin users
- Completion required before full access

**Technical Notes:**
- LMS (Learning Management System) integration or custom

---

### 6.4 Legal & Support Pages

#### FR-P2-009: Help Center
**Priority:** MEDIUM  
**Description:** Customer and vendor support articles

**Acceptance Criteria:**
- Searchable help articles
- Categories: Account, Orders, Payments, Technical
- "Was this helpful?" feedback buttons
- Live chat widget (Phase 3)

**Business Rules:**
- Public access (no login required)
- Multilingual support

**Technical Notes:**
- Headless CMS for content
- Algolia for search

---

## Additional Features (FR-P2-010 to FR-P2-019)

_(Due to length constraints, I'll summarize the remaining platform pages)_

**FR-P2-010:** Affiliate Program Page  
**FR-P2-011:** Partnership Page (for delivery services, POS integrations)  
**FR-P2-012:** API Documentation (for third-party developers)  
**FR-P2-013:** Status Page (platform uptime, incidents)  
**FR-P2-014:** Careers Page  
**FR-P2-015:** Press Kit  
**FR-P2-016:** Investor Relations  
**FR-P2-017:** Sustainability Page (eco-friendly packaging, carbon footprint)  
**FR-P2-018:** Accessibility Statement  
**FR-P2-019:** Trust & Safety Page (community guidelines, reporting)  

---

## END OF PHASE 2 SRS

**Total Features:** 92  
**Status:** 60% Complete  
**Next:** Phase 3 — Full Platform (see TAVLO_SRS_PHASE_3.md)

---

**Last Updated:** December 26, 2024  
**Document Owner:** TAVLO Product Team  
**Version:** 4.0
