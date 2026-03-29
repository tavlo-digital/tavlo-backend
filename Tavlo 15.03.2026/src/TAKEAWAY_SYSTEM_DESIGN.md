# Takeaway/Pickup System - Design Document

## 📱 References & Inspiration

### Industry Leaders:
1. **Uber Eats** - "ASAP" or "Schedule" with 15-min intervals
2. **DoorDash** - Clean pickup time selection, shows "Ready in X min"
3. **Grubhub** - Time slots respect restaurant hours and prep time
4. **Just Eat** - Clear distinction between delivery and collection
5. **Chipotle** - Real-time prep time based on order volume
6. **McDonald's App** - Simple time picker with unavailable slots grayed out

### Best Practices:
- ✅ ASAP option (default) = Current time + Prep time
- ✅ Schedule for later (up to 7 days)
- ✅ Time slots in 15-minute intervals
- ✅ Respect restaurant closing hours
- ✅ Show exact pickup time before checkout
- ✅ SMS/Push notification when ready
- ✅ Separate order status flow for pickup
- ✅ Clear visual distinction in vendor dashboard

---

## 🎯 System Requirements

### Customer Side:
1. **Order Type Selection**
   - Toggle: "Dine-in" vs "Takeaway"
   - Show pickup time options
   - Display estimated ready time

2. **Time Selection**
   - ASAP (earliest = current time + prep time)
   - Schedule for later (time picker)
   - 15-minute interval slots
   - Disable past times and slots after closing

3. **Order Flow**
   - Select takeaway → Choose time → Add items → Checkout
   - Show pickup time in basket summary
   - Confirm pickup time at payment

4. **Order Tracking**
   - Status: "Received" → "Preparing" → "Ready for Pickup" → "Completed"
   - Notification when ready
   - Show pickup instructions (counter, window, etc.)

### Vendor Side:
1. **Settings**
   - Enable/disable takeaway
   - Set minimum prep time for takeaway
   - Set max advance order time (default 7 days)
   - Add pickup instructions

2. **Order Display**
   - **Visual Badge**: "🛍️ TAKEAWAY" vs "🍽️ DINE-IN"
   - **Pickup Time**: Prominent display
   - **Time Remaining**: "Ready in 12 min"
   - **Filter**: Show only takeaway orders

3. **Order Management**
   - Mark as "Ready for Pickup"
   - Customer notification triggered
   - Mark as "Picked Up" when collected
   - Handle no-shows (auto-cancel after 30 min)

---

## 🗄️ Data Structure

### Order Object:
```typescript
{
  id: string,
  orderType: 'dine-in' | 'takeaway',
  pickupTime: string | null,  // ISO timestamp (only for takeaway)
  scheduledFor: string | null, // "ASAP" or ISO timestamp
  pickupInstructions: string,  // "Counter 2" or "Drive-thru window"
  status: 'received' | 'preparing' | 'ready' | 'picked-up' | 'completed',
  readyNotificationSent: boolean,
  customerName: string,
  customerPhone: string,
  items: [...],
  // ... rest of order fields
}
```

### Vendor Settings:
```typescript
{
  // Takeaway Settings
  enableTakeaway: boolean,
  takeawayPrepTime: number,  // Minutes (can be different from dine-in)
  maxAdvanceOrderDays: number,  // Default: 7
  pickupInstructions: string,  // "Pick up at main counter"
  takeawayMinOrderAmount: number,  // Optional minimum
}
```

---

## 🎨 UI/UX Flow

### 1. Order Type Selection (RestaurantPage)
```
┌─────────────────────────────────────────┐
│  How would you like to order?           │
├─────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐    │
│  │ 🍽️ Dine-in   │  │ 🛍️ Takeaway  │    │
│  │              │  │              │    │
│  │ Scan QR code │  │ Choose pickup│    │
│  │ at table     │  │ time         │    │
│  └──────────────┘  └──────────────┘    │
│                                         │
│  📍 Reserve Table                       │
└─────────────────────────────────────────┘
```

### 2. Takeaway Time Selection Modal
```
┌─────────────────────────────────────────┐
│  🛍️ Choose Pickup Time                  │
├─────────────────────────────────────────┤
│  ⚡ ASAP                                 │
│  Ready in ~25 minutes (14:35)           │
│  ┌────────────────────────────────┐    │
│  │         SELECT          ✓      │    │
│  └────────────────────────────────┘    │
│                                         │
│  📅 Schedule for Later                  │
│  ┌────────────────────────────────┐    │
│  │  Today, Dec 13                 │    │
│  │  ▼ 15:00  ▼ 15:15  ▼ 15:30    │    │
│  │  ▼ 15:45  ▼ 16:00  ▼ 16:15    │    │
│  │                                │    │
│  │  Tomorrow, Dec 14              │    │
│  │  ▼ 12:00  ▼ 12:15  ▼ 12:30    │    │
│  └────────────────────────────────┘    │
│                                         │
│  ℹ️ Pickup at: Main Counter             │
│                                         │
│  [ Continue to Menu ]                   │
└─────────────────────────────────────────┘
```

### 3. Basket with Pickup Time
```
┌─────────────────────────────────────────┐
│  🛍️ Takeaway Order                      │
│  📅 Pickup: Today at 14:35 (ASAP)       │
│  📍 Main Counter                        │
│  [Change Time]                          │
├─────────────────────────────────────────┤
│  Pasta Carbonara  x1       €14.50       │
│  Pizza Margherita x2       €24.00       │
│                                         │
│  Subtotal                  €38.50       │
│  Service Fee              €1.93         │
│  VAT (13%)                €5.26         │
│                                         │
│  Total                    €45.69        │
│                                         │
│  [ Proceed to Checkout ]                │
└─────────────────────────────────────────┘
```

### 4. Order Tracking (Customer)
```
┌─────────────────────────────────────────┐
│  Order #5847                            │
│  🛍️ Takeaway                            │
├─────────────────────────────────────────┤
│  ● Received            ✓                │
│  ● Preparing           ← You are here   │
│  ○ Ready for Pickup    ~10 min          │
│  ○ Picked Up                            │
├─────────────────────────────────────────┤
│  📅 Pickup Time: 14:35                  │
│  📍 Main Counter                        │
│  ☎️ Call restaurant                     │
└─────────────────────────────────────────┘
```

### 5. Vendor Order Card
```
┌─────────────────────────────────────────┐
│  #5847  🛍️ TAKEAWAY  [Ready in 8 min]  │
├─────────────────────────────────────────┤
│  📅 PICKUP: 14:35 (ASAP)                │
│  👤 John Smith                          │
│  ☎️  +43 660 123 4567                   │
│                                         │
│  🍝 Pasta Carbonara  x1                 │
│  🍕 Pizza Margherita x2                 │
│                                         │
│  💶 Total: €45.69  ✅ Paid              │
│                                         │
│  Status: ● Preparing                    │
│  [Mark as Ready for Pickup]             │
└─────────────────────────────────────────┘
```

---

## 🔄 Order Status Flow

### Dine-in Flow:
```
Received → Preparing → Served → Completed
```

### Takeaway Flow:
```
Received → Preparing → Ready for Pickup → Picked Up → Completed
                              ↓
                     [Send Notification]
                              ↓
                     [Customer arrives]
                              ↓
                     [Vendor confirms pickup]
```

### Status Actions:
| Status | Vendor Action | Customer View | Notification |
|--------|--------------|---------------|--------------|
| Received | Auto | "Order received" | Order confirmation |
| Preparing | Auto/Manual | "Being prepared" | - |
| Ready | Manual | "Ready! Come pick up" | SMS/Push |
| Picked Up | Manual | "Enjoy your meal!" | - |
| Completed | Auto (after pickup) | "Thank you!" | Review request |

---

## 🔔 Notifications

### When Order is Ready:
**SMS:**
```
🛍️ Your order #5847 from Bella Italia is ready for pickup!
📍 Pick up at: Main Counter
⏰ Please collect within 30 minutes.
```

**Push Notification:**
```
✅ Order Ready!
Your takeaway from Bella Italia is ready for pickup at the main counter.
```

**Email:**
```
Subject: Your order is ready for pickup! 🛍️

Hi John,

Great news! Your order #5847 from Bella Italia is ready.

Pickup Details:
- Location: Main Counter
- Time: 14:35 (now)
- Order: Pasta Carbonara, Pizza Margherita x2

Please collect within 30 minutes to ensure freshness.

[View Order Details]
```

---

## ⚙️ Business Rules

### Time Calculation:
```typescript
// ASAP pickup time
const asapTime = currentTime + prepTime + bufferTime(5min)

// Example: Current: 14:00, Prep: 25min, Buffer: 5min
// Result: 14:30

// Scheduled pickup
const scheduledTime = selectedTime
// Must be: scheduledTime >= asapTime
```

### Time Slots:
- Generate slots from opening to closing time
- Interval: 15 minutes (configurable)
- Disable slots before ASAP time
- Disable slots within 30min of closing
- Max advance: 7 days (configurable)

### Validation:
```typescript
if (pickupTime < currentTime + prepTime) {
  error("Pickup time too soon. Earliest: " + asapTime)
}

if (pickupTime > restaurantClosingTime) {
  error("Restaurant will be closed at that time")
}

if (pickupTime > currentTime + maxAdvanceDays) {
  error("Cannot schedule more than 7 days in advance")
}
```

### Auto-Cancel:
- If not picked up within 30 minutes of ready time
- Send notification: "Order #5847 was not collected and has been cancelled"
- Refund if paid online
- Mark as "no-show" for analytics

---

## 📊 Analytics & Metrics

### Vendor Dashboard:
- **Takeaway vs Dine-in Ratio**
- **Average Pickup Time Accuracy** (scheduled vs actual)
- **No-show Rate**
- **Peak Takeaway Hours**
- **ASAP vs Scheduled Orders**

### Reports:
```
Takeaway Orders This Week: 127
├─ ASAP: 89 (70%)
├─ Scheduled: 38 (30%)
└─ No-shows: 3 (2.4%)

Average Prep Time: 23 minutes
On-time Rate: 94%
```

---

## 🔧 Technical Implementation

### Database Schema:
```typescript
// Add to existing order object
{
  orderType: 'dine-in' | 'takeaway',
  pickupTime: string | null,  // ISO 8601
  scheduledFor: 'asap' | 'scheduled',
  pickupStatus: 'pending' | 'ready' | 'picked-up',
  readyAt: string | null,  // When marked ready
  pickedUpAt: string | null,  // When collected
  pickupInstructions: string
}
```

### API Endpoints:
```typescript
// Get available pickup times
GET /restaurants/:id/takeaway/available-slots
Query: ?date=2024-12-13&prepTime=25
Response: { slots: ['14:30', '14:45', '15:00', ...] }

// Create takeaway order
POST /orders
Body: {
  orderType: 'takeaway',
  pickupTime: '2024-12-13T14:30:00Z',
  scheduledFor: 'asap',
  // ... rest of order data
}

// Mark order as ready
PATCH /orders/:id/ready
Response: { notificationSent: true }

// Mark order as picked up
PATCH /orders/:id/picked-up
```

### Vendor Settings Update:
```typescript
{
  // Add to existing settings
  enableTakeaway: true,
  takeawayPrepTime: 25,  // Can differ from dine-in prep time
  maxAdvanceOrderDays: 7,
  takeawaySlotInterval: 15,  // minutes
  pickupInstructions: "Pick up at main counter near entrance",
  takeawayMinOrderAmount: 10,  // Optional minimum
  allowScheduledOrders: true,  // Can disable and only allow ASAP
}
```

---

## 📱 Mobile Considerations

### Geolocation:
- Show "You're nearby!" when customer is within 500m
- "Arrived?" button when within 100m
- Auto-notify vendor

### QR Code Pickup:
- Generate QR code for order
- Vendor scans to confirm pickup
- Reduces fraud and errors

---

## 🚀 Implementation Priority

### Phase 1 (MVP):
1. ✅ Add takeaway toggle to vendor settings
2. ✅ Create TakeawayModal for time selection
3. ✅ ASAP pickup only (no scheduling)
4. ✅ Update order creation with pickupTime
5. ✅ Show pickup time in vendor orders
6. ✅ Basic "Ready for Pickup" status

### Phase 2 (Enhanced):
1. Schedule for later (time slots)
2. SMS notification when ready
3. Customer pickup tracking
4. Auto-cancel no-shows
5. Pickup instructions

### Phase 3 (Advanced):
1. Dynamic prep time based on order volume
2. QR code pickup confirmation
3. Geolocation features
4. Analytics dashboard
5. Customer pickup history

---

## 💡 Additional Features (Future)

1. **Curbside Pickup**
   - "I'm here" button
   - Car description field
   - Staff brings order outside

2. **Locker System**
   - Integration with smart lockers
   - PIN code for retrieval
   - Temperature-controlled compartments

3. **Loyalty Integration**
   - Extra points for takeaway orders
   - "Skip the line" for VIP members

4. **Group Orders**
   - Multiple people add to one takeaway order
   - Split payment for pickup orders

5. **Recurring Orders**
   - "Order again" quick button
   - Save favorite takeaway times
   - Weekly scheduled orders

---

## ✅ Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Takeaway adoption | >30% of orders | Order type ratio |
| On-time ready rate | >90% | Ready time vs scheduled time |
| No-show rate | <5% | Unpicked orders / total takeaway |
| Customer satisfaction | >4.5/5 | Post-pickup survey |
| Avg prep accuracy | ±5 min | Actual vs estimated |

---

This comprehensive system ensures a smooth takeaway experience for both customers and vendors, with clear timing, notifications, and order management! 🎯
