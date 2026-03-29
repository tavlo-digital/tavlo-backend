# 🍝 **Vendor Journey: Marco's Restaurant Transformation - PART 3**

## **Edge Cases, Advanced Workflows & Problem Solving - Weeks 5-6**

**Continuing from Parts 1 & 2...**

---

### **🍽️ Phase 20: Menu Management - Advanced Operations (Week 5, Monday, February 3)**

Marco needs to make several menu updates based on customer feedback and seasonal changes.

#### **Scenario 1: Scheduling Menu Updates**

**TAV-VEN-MEN-012**: Marco wants to launch a Valentine's Day special menu on February 14:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          SCHEDULE MENU UPDATE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Plan menu changes to publish automatically at a future 
date and time.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

SCHEDULE NAME: [Valentine's Day Special Menu        ]

PUBLISH DATE: [February 14, 2025]
PUBLISH TIME: [00:01] AM

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CHANGES TO APPLY:

✅ Add New Dishes (3):
   • Lobster Ravioli with Rose Sauce - €28.00
   • Beef Tenderloin for Two - €65.00
   • Chocolate Lava Cake - €9.50

✅ Update Existing Dishes (2):
   • Tiramisu → Add "Valentine's Special" badge
   • House Wine → Champagne substitute option

✅ Create New Category:
   • ❤️ Valentine's Specials (display order: 1)

✅ Hide Dishes (temporary):
   • Standard desserts (redirect to Valentine's options)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

AUTOMATIC REVERT:

☑ Automatically revert menu after event
Revert Date: [February 15, 2025]
Revert Time: [23:59] PM

This will:
• Remove Valentine's category
• Restore hidden dishes
• Remove special badges

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PREVIEW SCHEDULED MENU:

[👁️ Preview Valentine's Menu]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

NOTIFICATIONS:

☑ Send me a confirmation email when menu publishes
☑ Notify staff of menu changes
☐ Send push notification to loyalty customers

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Cancel]  [Schedule Update]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco schedules the update → Confirmation:

```
✅ MENU UPDATE SCHEDULED

Your Valentine's Day Special Menu will automatically 
publish on February 14 at 12:01 AM and revert on 
February 15 at 11:59 PM.

📅 Status: Pending (10 days until publish)

[View Scheduled Updates] [Edit Schedule] [Cancel Schedule]
```

**CUSTOMER IMPACT**: ✅ Valentine's menu appears automatically on Feb 14, reverts Feb 15, customers see special offerings  
**ADMIN IMPACT**: None

---

#### **Scenario 2: Setting Dish Availability Schedules**

**TAV-VEN-MEN-005**: Marco wants to make "Breakfast Pizza" available only during brunch hours:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          EDIT DISH: BREAKFAST PIZZA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

... (basic info) ...

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

AVAILABILITY SCHEDULE:

Availability Type:
○ All Day (always available during open hours)
● Specific Hours (time-restricted)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DAYS AVAILABLE:

☑ Monday
☑ Tuesday
☑ Wednesday
☑ Thursday
☑ Friday
☑ Saturday
☑ Sunday

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

TIME WINDOWS:

Brunch Service:
From: [10:00] AM  To: [14:00] (2:00 PM)

[+ Add Another Time Window]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CUSTOMER DISPLAY:

When unavailable, show:
● "Available during brunch hours (10 AM - 2 PM)"
○ "Currently unavailable"
○ Hide dish completely

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PREVIEW:

Outside brunch hours (e.g., 3:00 PM):
┌─────────────────────────────────────────────────┐
│ 🍕 Breakfast Pizza                 €11.50       │
│ Eggs, bacon, mozzarella on crispy crust        │
│                                                  │
│ ⏰ Available during brunch hours (10 AM - 2 PM) │
│ [Notify Me When Available]                      │
└─────────────────────────────────────────────────┘

During brunch hours (e.g., 11:00 AM):
┌─────────────────────────────────────────────────┐
│ 🍕 Breakfast Pizza                 €11.50       │
│ Eggs, bacon, mozzarella on crispy crust        │
│                                                  │
│ ✅ Available now                                │
│ [Add to Basket]                                 │
└─────────────────────────────────────────────────┘

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Save Availability]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

**CUSTOMER IMPACT**: ✅ Dish appears/disappears from customer menu based on schedule automatically, clear messaging  
**ADMIN IMPACT**: None

---

#### **Scenario 3: Editing Dish Details**

**TAV-VEN-MEN-007**: Customer feedback says Carbonara photo doesn't match the actual dish. Marco updates it:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          EDIT DISH: CARBONARA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CURRENT PHOTO:

┌─────────────────────────────────────┐
│  [Current Carbonara Photo]           │
│  📷 Uploaded: Jan 6, 2025            │
│  Size: 2.3 MB                        │
└─────────────────────────────────────┘

[🔄 Replace Photo] [➕ Add More Photos] [🗑️ Remove Photo]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DISH NAME: [Carbonara                             ]

PRICE: [€13.50]

⚠️ WARNING: Price change detected
Current price: €13.50
Previous price: €13.50 (no change)

If you change the price, customers with this item in 
their basket will be notified.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DESCRIPTION:
┌─────────────────────────────────────────────────┐
│ Traditional Roman pasta with guanciale, eggs,   │
│ Pecorino Romano, and black pepper. Authentic   │
│ recipe with no cream - just like in Rome!       │
│                                                  │
│ 187/500 characters                              │
└─────────────────────────────────────────────────┘

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DIETARY TAGS:
☐ Vegetarian (removed - contains guanciale)
☐ Vegan
☐ Gluten-Free
...

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CHANGE LOG:

Feb 3, 2025 15:23 - Photo updated by Marco Rossi
Feb 3, 2025 15:25 - Description updated by Marco Rossi
Jan 6, 2025 11:30 - Dish created by Marco Rossi

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Save Changes]  [Cancel]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco uploads new professional photo taken this morning → Saves

**CUSTOMER IMPACT**: ✅ Updated dish information visible to customers immediately, accurate photos improve trust  
**ADMIN IMPACT**: None

---

#### **Scenario 4: Deleting a Dish**

**TAV-VEN-MEN-008**: Affogato isn't selling well (only 8 orders in 4 weeks). Marco decides to remove it:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          DELETE DISH: AFFOGATO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

⚠️ WARNING: This action cannot be undone!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DISH INFORMATION:

Name: Affogato
Price: €5.50
Category: Desserts
Status: Active

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

IMPACT ANALYSIS:

📊 Sales History:
   • Total orders (all time): 8
   • Revenue generated: €44.00
   • Last ordered: 12 days ago

👥 Customer Impact:
   • 3 customers have favorited this dish
   • 0 customers currently have this in their basket
   • 2 active reservations have pre-ordered this dish

📝 Reviews:
   • 2 reviews (avg 4.0 stars)
   • Reviews will be preserved but hidden

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

WHAT WILL HAPPEN:

✅ Dish removed from customer menu immediately
✅ Favorited customers notified of removal
⚠️ 2 pre-orders will need alternative (contact customers)
✅ Historical data preserved for reporting
✅ Reviews archived (not deleted)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

ALTERNATIVE ACTIONS:

Instead of deleting, you can:
• Mark as "Out of Stock" (temporary)
• Set as "Unavailable" but keep in system
• Move to "Hidden" category

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

TO CONFIRM DELETION, TYPE: DELETE

[                                     ]

              [Cancel]  [Delete Dish]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco types "DELETE" → Confirms

→ **Notifications sent**:
- 📧 3 customers who favorited: "Affogato has been removed from Trattoria Bella Vista menu"
- 📧 2 customers with pre-orders: "Please contact restaurant to select alternative dessert"

**CUSTOMER IMPACT**: ✅ Dish removed from customer menu, basket validation if in cart, favorites removed, pre-order customers contacted  
**ADMIN IMPACT**: None

---

#### **Scenario 5: Reordering Menu Items**

**TAV-VEN-MEN-009**: Based on popularity data, Marco wants to reorder dishes to highlight best-sellers:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          REORDER MENU ITEMS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Drag and drop to reorder categories and dishes.
Changes apply to customer menu immediately.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CATEGORIES:

[☰] 🍕 Pizza (5 dishes)          ← Most popular, move to top
[☰] 🍝 Pasta (4 dishes)
[☰] 🥗 Starters (3 dishes)
[☰] 🥩 Mains (5 dishes)
[☰] 🍰 Desserts (4 dishes)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DISHES IN "PIZZA" CATEGORY:

[☰] Margherita Pizza (189 orders) ⭐ Best Seller
[☰] Quattro Stagioni (87 orders)
[☰] Diavola (76 orders)
[☰] Prosciutto e Funghi (54 orders)
[☰] Vegetariana (43 orders)

💡 Tip: Put best-sellers at the top to increase 
   visibility and orders.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PREVIEW ORDER:

┌─────────────────────────────────────────────────┐
│  CUSTOMER MENU PREVIEW:                          │
│                                                  │
│  [Pizza] [Pasta] [Starters] [Mains] [Desserts] │
│                                                  │
│  🍕 Pizza                                        │
│  • Margherita Pizza                  €13.50     │
│  • Quattro Stagioni                  €14.50     │
│  ...                                             │
└─────────────────────────────────────────────────┘

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Cancel]  [Save Order]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco drags Pizza category to top → Saves

**CUSTOMER IMPACT**: ✅ Customer menu shows dishes in new order immediately, best-sellers more visible  
**ADMIN IMPACT**: None

---

#### **Scenario 6: Publishing Menu Changes in Draft Mode**

**TAV-VEN-MEN-010**: Marco has made 8 changes over the past hour (new photos, updated descriptions, price adjustments). He wants to review everything before publishing:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          UNPUBLISHED MENU CHANGES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

You have 8 unpublished changes in draft mode.

⚠️ Customers are still seeing the old menu until you 
   publish these changes.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PENDING CHANGES:

1. 📷 Carbonara - Photo updated
2. ✏️ Carbonara - Description updated
3. 💰 Margherita Pizza - Price changed (€12.50 → €13.50)
4. 🗑️ Affogato - Dish deleted
5. 📊 Menu reordered - Pizza category moved to top
6. 📷 Tiramisu - Photo updated
7. ✏️ Bruschetta - Description updated
8. ⏰ Breakfast Pizza - Availability schedule set

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CUSTOMER IMPACT ANALYSIS:

📊 Affected Dishes: 6
💰 Price Changes: 1 dish (+€1.00)
🗑️ Removed Dishes: 1 dish
👥 Active Baskets: 3 customers may need basket validation

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PUBLISH OPTIONS:

● Publish all changes now (recommended)
○ Publish individual changes (select below)
○ Save as draft and publish later
○ Discard all changes

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

NOTIFICATIONS:

☑ Validate customer baskets (update prices automatically)
☑ Notify staff of menu changes
☐ Send notification to loyalty members (major changes only)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Preview Changes]  [Publish All]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco clicks **"Preview Changes"** → Reviews customer-facing menu → Looks good!

Marco clicks **"Publish All"** → Confirmation:

```
✅ ALL CHANGES PUBLISHED SUCCESSFULLY!

Your menu updates are now live and visible to customers.

• 3 customer baskets updated with new prices
• Staff notified via dashboard
• Menu version: 2.8 (published Feb 3, 15:47)

[View Live Menu] [View Change History]
```

**CUSTOMER IMPACT**: ✅ All menu changes published atomically, no partial updates, customers see consistent menu, basket validation runs  
**ADMIN IMPACT**: None

---

### **🔧 Phase 21: Order Problems & Solutions (Week 5, Tuesday-Thursday)**

#### **Scenario 1: Modifying an Order**

**TAV-VEN-ORD-006**: Customer at Table 7 calls waiter: "Can we add one more Margherita Pizza to our order? We're hungrier than we thought!"

Anna (waiter) opens order on her device:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          ORDER #10487 - TABLE 7
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Status: 🔴 PREPARING (Kitchen working on it)
Time: 6:42 PM (8 minutes ago)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CURRENT ITEMS:

2x Bruschetta                               €17.00
1x Carbonara                                €13.50
1x House Wine (Bottle)                      €18.00

Subtotal:                                   €48.50
Service (10%):                               €4.85
Tax:                                         €5.34
────────────────────────────────────────────────────
Total Paid:                                 €58.69

Payment: Visa ****4532 ✅ PAID

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CUSTOMER REQUEST:

"Can we add one more Margherita Pizza?"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Modify Order]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Anna taps **"Modify Order"**:

```
┌─────────────────────────────────────────────────┐
│  MODIFY ORDER #10487                             │
│                                                  │
│  ⚠️ This order is already being prepared in the │
│     kitchen. Modifications will create a new    │
│     kitchen ticket for additional items.        │
│                                                  │
│  CURRENT ITEMS:                                  │
│  • 2x Bruschetta                                │
│  • 1x Carbonara                                 │
│  • 1x House Wine (Bottle)                       │
│                                                  │
│  ACTIONS:                                        │
│  [+ Add Items]                                  │
│  [− Remove Items]                               │
│  [✏️ Update Quantities]                         │
│  [📝 Add Special Instructions]                  │
│                                                  │
└─────────────────────────────────────────────────┘
```

Anna taps **"+ Add Items"** → Selects **Margherita Pizza (Medium)** → Add

```
┌─────────────────────────────────────────────────┐
│  MODIFIED ORDER SUMMARY                          │
│                                                  │
│  ORIGINAL ORDER:                €48.50          │
│  • 2x Bruschetta                                │
│  • 1x Carbonara                                 │
│  • 1x House Wine                                │
│                                                  │
│  + ADDED ITEMS:                 €13.50          │
│  • 1x Margherita Pizza (Medium)                 │
│                                                  │
│  ─────────────────────────────────────────────  │
│                                                  │
│  NEW ORDER TOTAL:               €62.00          │
│  Service (10%):                  €6.20          │
│  Tax:                            €6.82          │
│  ─────────────────────────────────────────────  │
│  NEW TOTAL:                     €75.02          │
│                                                  │
│  ADDITIONAL PAYMENT REQUIRED:                   │
│  €16.33                                         │
│                                                  │
│  ─────────────────────────────────────────────  │
│                                                  │
│  HOW TO COLLECT PAYMENT:                         │
│  ● Send payment request to customer's phone     │
│  ○ Process payment at table (card reader)       │
│  ○ Add to final bill                            │
│                                                  │
│            [Cancel]  [Confirm Changes]          │
└─────────────────────────────────────────────────┘
```

Anna selects **"Send payment request to customer's phone"** → Confirms

→ **Customer receives notification** on phone (they're still logged into TAVLO from original order):

```
📱 NOTIFICATION:

Trattoria Bella Vista
Order #10487 Modified - Table 7

Added:
+ 1x Margherita Pizza (Medium) - €13.50

Additional payment required: €16.33

[Pay Now] [View Order]
```

Customer taps **"Pay Now"** → Enters card details → Payment successful

→ **Kitchen receives new ticket**:
```
🔔 KITCHEN DISPLAY - ADDITIONAL ITEM

ORDER #10487-A (Table 7)
─────────────────────────────
+ 1x Margherita Pizza (Medium)

⚠️ RUSH - Customer already eating
Prep time: 7 min
─────────────────────────────
```

**CUSTOMER IMPACT**: ✅ Order modified seamlessly, customer sees updated order in their tracking, additional payment processed easily  
**ADMIN IMPACT**: ✅ Admin can see order modification history for dispute resolution

---

#### **Scenario 2: Canceling an Order with Refund**

**TAV-VEN-ORD-007**: 15 minutes after placing order, customer at Table 12 has emergency and must leave immediately. Sofia (manager) needs to cancel the order:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          ORDER #10489 - TABLE 12
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Status: 🔴 PREPARING
Time: 7:05 PM (15 minutes ago)

Items:
• 1x Grilled Salmon - €22.00
• 1x Caprese Salad - €9.00

Total Paid: €37.03 (Mastercard ****5678)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Cancel Order]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Sofia taps **"Cancel Order"**:

```
┌─────────────────────────────────────────────────┐
│  ⚠️ CANCEL ORDER #10489                         │
│                                                  │
│  This order is currently being prepared in the  │
│  kitchen. Cancellation will trigger an automatic│
│  refund to the customer.                        │
│                                                  │
│  ─────────────────────────────────────────────  │
│                                                  │
│  REFUND DETAILS:                                 │
│                                                  │
│  Original Payment:         €37.03               │
│  Refund Amount:            €37.03 (100%)        │
│  Refund Method:            Mastercard ****5678  │
│  Processing Time:          3-5 business days    │
│                                                  │
│  ─────────────────────────────────────────────  │
│                                                  │
│  CANCELLATION REASON (required):                 │
│                                                  │
│  ● Customer requested                           │
│  ○ Out of stock (ingredients unavailable)       │
│  ○ Technical issue                              │
│  ○ Kitchen too busy (long wait time)            │
│  ○ Other                                        │
│                                                  │
│  Additional Notes:                               │
│  ┌───────────────────────────────────────────┐  │
│  │ Customer had family emergency, must leave │  │
│  │ immediately.                               │  │
│  └───────────────────────────────────────────┘  │
│                                                  │
│  ─────────────────────────────────────────────  │
│                                                  │
│  ☑ Notify customer of cancellation and refund   │
│  ☑ Remove from kitchen queue                    │
│  ☑ Update inventory (return ingredients)        │
│                                                  │
│            [Go Back]  [Confirm Cancellation]    │
└─────────────────────────────────────────────────┘
```

Sofia confirms cancellation

→ **Automatic refund initiated** via Stripe:
```
✅ ORDER CANCELLED & REFUND PROCESSED

Order #10489 has been cancelled.

Refund Details:
• Amount: €37.03
• Method: Mastercard ****5678
• Status: Processing (3-5 business days)
• Stripe Refund ID: re_abc123xyz

Kitchen notified to stop preparation.
```

→ **Customer notification** (Email + SMS):
```
From: orders@tavlo.com
To: customer@email.com
Subject: Order Cancelled - Refund Processed

Dear Customer,

Your order #10489 at Trattoria Bella Vista has been 
cancelled at your request.

Order Details:
• 1x Grilled Salmon
• 1x Caprese Salad
• Total: €37.03

REFUND PROCESSED:
We've initiated a full refund of €37.03 to your 
Mastercard ending in 5678. Please allow 3-5 business 
days for the refund to appear on your statement.

We hope to serve you again soon!

Best regards,
Trattoria Bella Vista Team
```

**CUSTOMER IMPACT**: ✅ Customer receives immediate cancellation notification and automatic refund, disappointed but informed  
**ADMIN IMPACT**: ✅ Admin monitors cancellation rates, can review patterns for vendor performance

---

#### **Scenario 3: Managing Delivery Orders**

**TAV-VEN-ORD-011**: Marco has partnered with Uber Eats. First delivery order arrives:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          NEW DELIVERY ORDER
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🔔 Order #10502 - DELIVERY via Uber Eats

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CUSTOMER INFO:

Name: Anna Müller
Phone: +43 664 XXX XXXX (masked by Uber Eats)
Delivery Address: 
  Mariahilfer Straße 123, 1060 Wien
  Apartment 4B, 2nd floor
  Buzzer: "Müller"

Special Delivery Instructions:
"Please ring buzzer twice. No contact delivery - 
 leave at door."

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

ORDER ITEMS:

2x Margherita Pizza (Medium)                €27.00
1x Tiramisu                                  €7.50
1x Sparkling Water                           €3.00

Subtotal:                                   €37.50
Delivery Fee (Uber Eats):                    €4.90
Service Fee (Uber Eats):                     €2.50
Tax:                                         €4.49
────────────────────────────────────────────────────
Customer Paid:                              €49.39

Your Revenue (after Uber Eats 30% commission):
€26.25 (70% of €37.50)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

ESTIMATED PREP TIME: 20 minutes
ESTIMATED DELIVERY TIME: 45 minutes

Driver Status: ⏳ Waiting for driver assignment

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Accept Order]  [Reject Order]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco accepts → Kitchen prepares order

20 minutes later:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          ORDER #10502 - READY FOR PICKUP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Status: 🟢 READY

Driver Status: 🚗 Driver assigned - Miguel (5 min away)
Driver ETA: 7:35 PM

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PACKAGING CHECKLIST:

☑ 2x Pizza boxes (sealed)
☑ 1x Tiramisu container
☑ 1x Sparkling Water bottle
☑ Napkins, utensils
☑ Receipt included

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Mark as Picked Up]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Driver Miguel arrives → Marco hands over order → Taps **"Mark as Picked Up"**

→ **Delivery tracking** updates automatically via Uber Eats API:
```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          ORDER #10502 - IN TRANSIT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Status: 🚗 OUT FOR DELIVERY

Driver: Miguel
Estimated Delivery: 7:55 PM

📍 Live Tracking: [View on Uber Eats]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

20 minutes later - Order delivered:

```
✅ ORDER DELIVERED SUCCESSFULLY

Order #10502 completed at 7:52 PM

Customer can now rate and review the order.

Delivery Performance:
• Prep time: 18 min (target: 20 min) ✅
• Total delivery time: 42 min (estimated: 45 min) ✅
```

**CUSTOMER IMPACT**: ✅ Customers receive delivery orders on time with accurate tracking  
**ADMIN IMPACT**: ✅ Admin monitors delivery partner integrations and performance

---

### **📅 Phase 22: Reservation Management Workflows (Week 5, Friday)**

#### **Scenario 1: Accepting a Reservation**

**TAV-VEN-TAB-003**: Saturday morning, Marco checks pending reservations:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          PENDING RESERVATIONS (3)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

┌─────────────────────────────────────────────────┐
│ 📅 NEW RESERVATION REQUEST                      │
│                                                  │
│ Name: Thomas Weber                               │
│ Party Size: 8 people                             │
│ Date: Saturday, Feb 8, 2025                      │
│ Time: 7:30 PM                                    │
│ Phone: +43 699 123 4567                          │
│ Email: t.weber@email.com                         │
│                                                  │
│ Special Requests:                                │
│ "Birthday celebration - need space for cake and │
│  decorations. Prefer quieter area if possible."  │
│                                                  │
│ Requested: 2 hours ago                           │
│                                                  │
│ AVAILABILITY CHECK:                              │
│ ✅ Table 19 (Private Room, 8 seats) - Available │
│ ✅ Table 20 (Private Room, 10 seats) - Available│
│                                                  │
│            [Reject]  [Accept & Assign Table]    │
└─────────────────────────────────────────────────┘
```

Marco taps **"Accept & Assign Table"**:

```
┌─────────────────────────────────────────────────┐
│  ACCEPT RESERVATION                              │
│                                                  │
│  Customer: Thomas Weber (8 people)               │
│  Date/Time: Sat, Feb 8 at 7:30 PM                │
│                                                  │
│  ASSIGN TABLE:                                   │
│                                                  │
│  Recommended Tables:                             │
│  ● Table 19 (Private Room, 8 seats) ⭐ Best fit │
│  ○ Table 20 (Private Room, 10 seats)            │
│  ○ Combine Tables 9+10 (6+6 seats)              │
│                                                  │
│  Special Setup:                                  │
│  ☑ Reserve table for birthday setup (30 min)    │
│  ☑ Prepare birthday decorations                 │
│  ☑ Chill champagne                               │
│                                                  │
│  Internal Notes:                                 │
│  ┌───────────────────────────────────────────┐  │
│  │ Large birthday group. Set up Table 19 at  │  │
│  │ 7:00 PM. Prepare space for cake.          │  │
│  └───────────────────────────────────────────┘  │
│                                                  │
│  Confirmation Message to Customer:               │
│  ┌───────────────────────────────────────────┐  │
│  │ Hi Thomas! Your reservation for 8 is      │  │
│  │ confirmed for Sat, Feb 8 at 7:30 PM.      │  │
│  │ We've reserved our private room (Table 19)│  │
│  │ for your birthday celebration. Looking    │  │
│  │ forward to hosting you!                   │  │
│  │ - Marco & Team                            │  │
│  └───────────────────────────────────────────┘  │
│                                                  │
│  ☑ Send SMS confirmation                         │
│  ☑ Send email confirmation with calendar invite  │
│  ☑ Send reminder 4 hours before                  │
│                                                  │
│            [Cancel]  [Confirm Reservation]      │
└─────────────────────────────────────────────────┘
```

Marco confirms

→ **Customer receives**:

📧 **Email**:
```
From: reservations@trattoriabellavista.at
To: t.weber@email.com
Subject: ✅ Reservation Confirmed - Feb 8 at 7:30 PM

Dear Thomas,

Your reservation is confirmed!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📅 Date: Saturday, February 8, 2025
🕐 Time: 7:30 PM
👥 Party Size: 8 people
🏠 Table: Private Room (Table 19)
📋 Booking Reference: #RES-1247

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

We've reserved our private room for your birthday 
celebration. The space is perfect for decorations 
and cake!

[Add to Calendar] [View on Map] [Modify Reservation]

See you soon!
Marco & the Bella Vista Team

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Cancellation Policy: Free cancellation up to 4 hours 
before your reservation.
```

📱 **SMS**:
```
Trattoria Bella Vista: Your reservation for 8 is 
confirmed! Sat, Feb 8 at 7:30 PM, Private Room 
(Table 19). Ref: #RES-1247. See you soon!
```

**CUSTOMER IMPACT**: ✅ Customer receives immediate confirmation with table number, can plan visit confidently  
**ADMIN IMPACT**: None

---

#### **Scenario 2: Modifying a Reservation**

**TAV-VEN-TAB-004**: Thomas calls: "Hi, two more friends want to join! Can we change from 8 to 10 people?"

Sofia opens reservation:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          RESERVATION #RES-1247
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Customer: Thomas Weber
Current: 8 people, Sat Feb 8 at 7:30 PM
Table: Table 19 (Private Room, 8 seats)
Status: ✅ Confirmed

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Modify Reservation]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Sofia taps **"Modify Reservation"**:

```
┌─────────────────────────────────────────────────┐
│  MODIFY RESERVATION #RES-1247                    │
│                                                  │
│  What would you like to change?                  │
│                                                  │
│  [Change Date/Time]                              │
│  [✓ Change Party Size]                          │
│  [Change Contact Info]                           │
│  [Add Special Requests]                          │
│                                                  │
│  ─────────────────────────────────────────────  │
│                                                  │
│  PARTY SIZE:                                     │
│                                                  │
│  Current: 8 people                               │
│  New: [10] people (+2)                          │
│                                                  │
│  ⚠️ CAPACITY CHECK:                              │
│  Current table (Table 19) capacity: 8 seats     │
│  New party size: 10 people                       │
│                                                  │
│  ❌ Table 19 is too small for 10 people          │
│                                                  │
│  ALTERNATIVE TABLES:                             │
│  ● Table 20 (Private Room, 10 seats) ✅ Fits    │
│  ○ Combine T9+T10 (Indoor, 6+6 seats) ✅ Fits   │
│                                                  │
│  Recommended: Switch to Table 20 (Private Room)  │
│                                                  │
│  Internal Note:                                  │
│  ┌───────────────────────────────────────────┐  │
│  │ Customer added 2 more guests. Switched to │  │
│  │ larger private room (Table 20).           │  │
│  └───────────────────────────────────────────┘  │
│                                                  │
│  ☑ Notify customer of table change               │
│                                                  │
│            [Cancel]  [Confirm Changes]          │
└─────────────────────────────────────────────────┘
```

Sofia confirms changes

→ **Customer notification**:
```
📧 Reservation Updated - #RES-1247

Hi Thomas,

We've updated your reservation to accommodate 10 
people!

Updated Details:
👥 Party Size: 8 → 10 people
🏠 Table: Table 19 → Table 20 (Larger Private Room)
📅 Date/Time: Sat, Feb 8 at 7:30 PM (unchanged)

Everything is ready for your celebration!

Best regards,
Trattoria Bella Vista
```

**CUSTOMER IMPACT**: ✅ Customer sees updated reservation details, receives confirmation of changes  
**ADMIN IMPACT**: None

---

#### **Scenario 3: Canceling a Reservation**

**TAV-VEN-TAB-005**: Another customer with reservation for 4 people calls with bad news - they all got sick with flu and can't come. Marco must cancel:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          RESERVATION #RES-1251
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Customer: Anna Schmidt
Party: 4 people
Date/Time: Tonight (Feb 7) at 8:00 PM
Table: Table 5 (Window, 4 seats)
Status: ✅ Confirmed

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Cancel Reservation]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco taps **"Cancel Reservation"**:

```
┌─────────────────────────────────────────────────┐
│  CANCEL RESERVATION #RES-1251                    │
│                                                  │
│  Customer: Anna Schmidt (4 people)               │
│  Tonight at 8:00 PM - Table 5                    │
│                                                  │
│  CANCELLATION REASON (required):                 │
│                                                  │
│  ● Customer requested (illness/emergency)       │
│  ○ Restaurant closure                            │
│  ○ Overbooking                                   │
│  ○ No-show                                       │
│  ○ Customer no-show (arrived >15 min late)      │
│  ○ Other                                        │
│                                                  │
│  Notes to Customer:                              │
│  ┌───────────────────────────────────────────┐  │
│  │ We're sorry to hear you're unwell. Get    │  │
│  │ well soon! We look forward to serving you │  │
│  │ when you're feeling better.               │  │
│  └───────────────────────────────────────────┘  │
│                                                  │
│  ─────────────────────────────────────────────  │
│                                                  │
│  IMPACT:                                         │
│  • Table 5 released (now available for booking) │
│  • Waitlist: 2 parties waiting for 4-person     │
│    tables tonight                                │
│                                                  │
│  ☐ No-show fee (€0.00 - waived due to illness)  │
│  ☑ Notify customer of cancellation               │
│  ☑ Offer next available tables from waitlist    │
│                                                  │
│            [Go Back]  [Confirm Cancellation]    │
└─────────────────────────────────────────────────┘
```

Marco confirms

→ **Customer notification**:
```
📧 Reservation Cancelled - #RES-1251

Dear Anna,

Your reservation for tonight (Feb 7 at 8:00 PM) has 
been cancelled as requested.

We're sorry to hear you're unwell. Get well soon! 
We look forward to serving you when you're feeling 
better.

No cancellation fee has been charged.

Feel free to book again anytime!

Best regards,
Marco & the Bella Vista Team
```

→ **Waitlist notification** (to 2 parties waiting for 4-person tables):
```
📱 SMS to waitlist customers:

Trattoria Bella Vista: A table for 4 just became 
available tonight at 8:00 PM! Interested? Reply YES 
within 10 minutes to claim it. First come, first served!
```

**CUSTOMER IMPACT**: ✅ Customer receives cancellation notification, may be disappointed but informed, can make alternative plans  
**ADMIN IMPACT**: ✅ Admin monitors cancellation rates, can identify problematic vendors

---

### **📱 Phase 23: QR Code Advanced Features (Week 5, Saturday)**

#### **Scenario 1: Customizing QR Code Design**

**TAV-VEN-QR-002**: Marco wants to create special QR codes for Valentine's Day with heart-themed design:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          QR CODE DESIGN CUSTOMIZATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Create custom-branded QR codes for special events.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DESIGN NAME: [Valentine's Day 2025              ]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

LOGO/CENTER IMAGE:

Current: [Bella Vista Logo]
[Upload New Image] (max 200x200px, transparent PNG)

☑ Embed logo in QR center
Opacity: [●━━━━━━━━━] 70%

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

COLORS:

QR Code Pattern:
Primary Color:   [#E91E63] 💗 (Hot Pink)
Secondary Color: [#C2185B] 💓 (Deep Pink)

Background:      [#FFFFFF] ⚪ (White)

Gradient Style:
○ Solid color
● Linear gradient (top to bottom)
○ Radial gradient (center to edges)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

FRAME & BORDER:

Frame Style:
○ None
○ Square border
● Rounded corners with text
○ Heart-shaped border ❤️

Border Text: [❤️ Valentine's Special - Scan to Order]

Font: [Dancing Script ▼] (romantic script)
Text Color: [#E91E63] 💗

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PATTERN STYLE:

QR Pattern:
○ Standard squares
● Rounded dots
○ Hearts ❤️ (experimental)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PREVIEW:

┌───────────────────────────────────────┐
│                                        │
│   ❤️ Valentine's Special - Scan to   │
│         Order                          │
│                                        │
│   ████████████████████████             │
│   ██💗💗██💗💗██💗💗██💗💗██             │
│   ██💗💗████💗💗██████💗💗██             │
│   ██💗💗💗💗██💗💗💗💗██💗💗██             │
│   ████████████████████████             │
│        [Bella Vista Logo]              │
│                                        │
│   tavlo.com/r/trattoria-bella/table-X  │
│                                        │
└───────────────────────────────────────┘

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

TEST SCAN:

☑ QR code is scannable (error correction: 25%)
⚠️ High customization may reduce scan reliability
   Recommended: Test on multiple devices

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

APPLY TO:

☑ All tables (1-20)
○ Specific tables only

ACTIVE DATES:
From: [Feb 10, 2025] To: [Feb 15, 2025]

☑ Automatically revert to standard design after Feb 15

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Save Design]  [Generate QR Codes]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco generates Valentine's QR codes → Prints them

**CUSTOMER IMPACT**: ✅ Branded QR codes enhance professional image, clear call-to-action encourages scanning  
**ADMIN IMPACT**: None

---

#### **Scenario 2: QR Code Analytics**

**TAV-VEN-QR-004**: Marco wants to see which tables are most popular:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          QR CODE ANALYTICS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📅 Last 30 Days

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

OVERVIEW:

Total QR Scans:           1,847
Unique Customers:         1,124
Conversion to Orders:     68.3% (1,262 orders)
Abandoned After Scan:     31.7% (585 customers)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PERFORMANCE BY TABLE:

┌─────────────────────────────────────────────────┐
│ Table | Location  | Scans | Orders | Conv Rate │
├─────────────────────────────────────────────────┤
│  T3   | Window    |  147  |  112   | 76.2% 🏆 │
│  T4   | Window    |  134  |  98    | 73.1%    │
│  T12  | Indoor    |  98   |  87    | 88.8% 🥇 │
│  T15  | Outdoor   |  87   |  45    | 51.7% ⚠️ │
│  T5   | Window    |  76   |  56    | 73.7%    │
│  ...  |           |       |        |          │
│  T18  | Outdoor   |  23   |  12    | 52.2% ⚠️ │
└─────────────────────────────────────────────────┘

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

INSIGHTS:

✅ Window tables (T1-T4) have highest conversion 
   (avg 74.5%)
   → Great customer experience, keep QR codes 
     prominently displayed

⚠️ Outdoor tables (T15-T18) have low conversion 
   (avg 52.0%)
   → Possible issues: QR codes faded by weather? 
     Poor WiFi signal outdoor?
   → Recommendation: Replace outdoor QR codes, check 
     WiFi coverage

🏆 Table 12 has best conversion (88.8%)!
   → What's special about T12? Quiet corner, popular 
     for couples

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

SCAN TIMES HEATMAP:

Peak Scan Hours:
Mon-Thu: 7:00-9:00 PM (avg 42 scans/hour)
Fri:     7:00-10:00 PM (avg 67 scans/hour)
Sat:     1:00-3:00 PM (28 scans/hr), 7:00-10:00 PM (71 scans/hr)

Slowest: Mon-Thu 2:00-5:00 PM (avg 4 scans/hour)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DEVICE BREAKDOWN:

📱 Mobile (iOS):      58.3% (1,077 scans)
📱 Mobile (Android):  36.2% (669 scans)
💻 Tablet:             4.8% (89 scans)
🖥️ Desktop:            0.7% (12 scans)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

ABANDONMENT ANALYSIS:

Why customers scan but don't order:
• Menu browsing only (62%) - just looking
• Price concerns (18%) - basket abandonment
• Technical issues (12%) - payment failures
• Changed mind (8%) - decided to leave

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Export Report (CSV)]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco sees outdoor QR codes have issues → Orders weatherproof QR code replacements

**CUSTOMER IMPACT**: ✅ Analytics help vendor optimize QR placement for better customer experience  
**ADMIN IMPACT**: ✅ Admin can see QR usage across platform for feature validation

---

#### **Scenario 3: Promotional QR Codes**

**TAV-VEN-QR-005**: Marco creates promotional QR for Instagram campaign:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
          CREATE PROMOTIONAL QR CODE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Promotional QR codes are different from table QR codes.
Use them for marketing campaigns on social media, flyers,
or advertisements.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CAMPAIGN NAME: [Instagram Winter Promo 2025      ]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

DESTINATION:

When scanned, customers are directed to:

● Landing page with auto-applied promo code
  → Special offer page + WINTER20 code applied
  
○ Direct to menu (general)
○ Specific dish (e.g., Valentine's special)
○ Reservation page

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

PROMO CODE AUTO-APPLY:

☑ Automatically apply promo code when scanned

Promo Code: [WINTER20 ▼]
            (20% off all orders, valid until Feb 28)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

LANDING PAGE MESSAGE:

┌─────────────────────────────────────────────────┐
│ 🎉 Special Instagram Offer!                     │
│                                                  │
│ Get 20% OFF your order with code WINTER20       │
│ (automatically applied)                          │
│                                                  │
│ Valid for dine-in or takeaway until Feb 28.     │
│                                                  │
│ [Browse Menu] [Reserve Table]                   │
└─────────────────────────────────────────────────┘

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

TRACKING:

☑ Track scans separately from table QR codes
☑ Track conversion rate (scans → orders)
☑ Track promo code usage from this campaign

Campaign URL:
tavlo.com/p/trattoria-bella/winter-instagram-2025

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

QR CODE DESIGN:

Use: ● Standard Bella Vista design
     ○ Custom design for this campaign

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

              [Generate Promotional QR]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

Marco generates → Downloads QR → Posts on Instagram:

**Instagram Post**:
```
📸 [Beautiful food photo]

❄️ WINTER SPECIAL! ❄️

Get 20% OFF your order at Trattoria Bella Vista!

👉 Scan the QR code in our bio or comments
👉 Code WINTER20 automatically applied
👉 Valid until Feb 28

See you soon! 🍕🍝

[Promotional QR Code Image]

#Vienna #Italian #RestaurantWien #FoodieVienna
```

Marco can track campaign performance:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PROMOTIONAL QR ANALYTICS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Campaign: Instagram Winter Promo 2025

Scans: 347 (since Feb 3)
Orders: 189 (54.5% conversion)
Revenue: €5,234.80
Discount Given: €1,046.96 (WINTER20 promo)
Net Revenue: €4,187.84

ROI: 4.0x (good campaign!)

Source Breakdown:
• Instagram (direct): 78%
• Instagram Stories: 15%
• Website: 7%
```

**CUSTOMER IMPACT**: ✅ Customers discover restaurant via promotional QR, get instant discount, easier onboarding  
**ADMIN IMPACT**: ✅ Admin can see promotional QR effectiveness across platform

---

**[Continuing in next message due to length...]**

Would you like me to continue with the remaining phases covering:
- **Phase 24**: Reviews (flagging, analytics)
- **Phase 25**: Promotions (promo codes, time-based, analytics)
- **Phase 26**: Advanced Analytics (dish performance, peak hours, exports)
- **Phase 27**: Billing Administration
- **Phase 28**: Settings & Staff

Let me know if you want me to complete Part 3! 🚀