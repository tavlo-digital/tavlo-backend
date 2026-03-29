# 🧪 Testing Guide: Restaurant-Specific Loyalty Points System

## Quick Test (Single Restaurant)

### Step 1: Start QR Order Session
1. Open the app in your browser
2. Click **mode switcher** (top-right corner) → **📱 QR Order**
3. **Look for the TAVLO logo** in the top-left corner of the QR landing page
4. On the QR landing page, click **Continue**
5. Choose **Continue as Guest** (or sign in if you prefer)

### Step 2: Place Your First Order
1. Browse the menu and add items to your basket
   - Example: Add "Margherita Pizza" (€12.50) and "Tiramisu" (€6.50)
   - Total: €19.00 = **19 loyalty points**
2. Click the **basket icon** (bottom-right)
3. Click **Checkout**
4. Choose any payment method (e.g., **Card**)
5. Click **Pay Now** or **Pay Later**
6. Your order is confirmed! 🎉

### Step 3: View Your Loyalty Points
1. Go back to the menu (click **Order More** or navigate back)
2. Click the **profile icon** (top-right, next to basket)
3. You'll see your profile with a loyalty card showing:
   - **19 points** (or the total you spent)
   - Progress bar: "81 points away" from next reward
   - Restaurant name: "La Bella Cucina"

### Step 4: Expand Details
1. Click **Show details** on the loyalty card
2. You'll see:
   - **Stats**: 1 Order, €19 Total Spent, 0 Rewards
   - **Recent Activity**: Your order with "+19 pts"
   - **How it works**: Points rules specific to this restaurant

### Step 5: Place Another Order
1. Go back to menu
2. Add more items (e.g., €85 worth of food)
3. Checkout and pay
4. Return to profile → You now have **104 points**
5. Notice: **1 reward available!** (100 points = €10 discount)

---

## Advanced Test (Multiple Restaurants)

Since the app currently uses one restaurant ("La Bella Cucina" / `rest_1`), here's how to simulate multiple restaurants for testing:

### Option A: Use Browser Console (Quick Test)
1. Open browser **Developer Tools** (F12)
2. Go to **Console** tab
3. Run this code to manually add points from different restaurants:

```javascript
// Simulate having points from multiple restaurants
const currentUser = {
  id: 'customer_1',
  name: 'Test User',
  email: 'test@example.com',
  loyaltyPoints: {
    'rest_1': 245,  // La Bella Cucina
    'rest_2': 300,  // Another Restaurant
    'rest_3': 78    // Third Restaurant
  }
};

// This will show in your profile!
```

### Option B: Check Backend Data
1. Open **Developer Tools** → **Application** tab (Chrome) or **Storage** tab (Firefox)
2. Look for customer data in localStorage or check network requests
3. You'll see loyalty points structured as:
   ```json
   {
     "loyaltyPoints": {
       "rest_1": 245,
       "rest_2": 300
     }
   }
   ```

---

## What to Look For

### ✅ Profile Overview Tab
- **Profile card** (left column):
  - Avatar with first letter of name
  - Email and phone (if logged in)
  - "Member since [year]"
  - Sign Out button

- **Loyalty Summary** (right column):
  - Total points across all restaurants
  - Number of restaurants you've ordered from

- **Restaurant Loyalty Cards**:
  - Each restaurant has its own card with gradient background
  - Current restaurant (from QR scan) shows **yellow ring** + badge
  - Points, progress bar, rewards specific to each restaurant
  - Expandable details with order history

### ✅ Loyalty Points Tab
- Full-screen view of all loyalty cards
- Large summary card showing total points
- Important notice: "Points are restaurant-specific"
- All restaurant cards displayed in full

### ✅ Points Calculation
- **Earning**: 1 point per €1 spent
  - €19.50 order = 19 points (rounded down)
  - €245.99 order = 245 points

- **Redemption**: 100 points = €10 discount
  - Only redeemable at the restaurant where earned
  - Clear message in each card

### ✅ Visual Design
- **TAVLO Brand Colors**: Coral/orange/pink gradient
  - `from-orange-500 via-red-500 to-pink-500`
- **Current Restaurant**: Yellow ring highlight
- **Expandable Cards**: Click to show/hide details
- **Progress Bars**: White bar on gradient background

---

## Testing Checklist

- [ ] Order placement earns points (check profile after order)
- [ ] Points shown in loyalty card match order total
- [ ] Progress bar updates correctly (X points to next reward)
- [ ] Expanding card shows order history
- [ ] "Show details" / "Hide details" button works
- [ ] Restaurant name displays correctly
- [ ] Mode switcher works (same profile on Platform and QR Order)
- [ ] Loyalty Summary shows total across restaurants
- [ ] Yellow ring appears on current restaurant card
- [ ] Guest users see loyalty points too
- [ ] Points persist after signing in/out

---

## Common Issues & Solutions

### ❌ Not seeing loyalty points after order?
- Make sure you **completed payment** (not just submitted order)
- Check that vendor settings have loyalty enabled
- Refresh the profile page

### ❌ Profile looks different from screenshots?
- Clear browser cache
- Hard reload (Ctrl+Shift+R or Cmd+Shift+R)
- Check that you're viewing the correct mode (QR Order vs Platform)

### ❌ Can't see multiple restaurants?
- Currently the app uses one restaurant by default
- Use browser console method above to test multi-restaurant view
- Or wait for future updates with restaurant selection

---

## Developer Notes

### Backend Changes Made:
- ✅ Loyalty points stored as object: `{ 'rest_1': 245, 'rest_2': 300 }`
- ✅ Points tracked per restaurant ID
- ✅ Order includes `restaurantId` and `restaurantName`
- ✅ Redemption validates restaurant-specific balance

### Frontend Changes Made:
- ✅ UserProfile component groups orders by restaurant
- ✅ Creates individual loyalty cards per restaurant
- ✅ Calculates points from order history
- ✅ Shows current restaurant context from QR scan
- ✅ Expandable cards with detailed history
- ✅ TAVLO brand colors throughout

### Data Structure:
```typescript
// Customer object
{
  id: 'customer_1',
  name: 'John Doe',
  email: 'john@example.com',
  loyaltyPoints: {
    'rest_1': 245,  // Points at La Bella Cucina
    'rest_2': 300,  // Points at another restaurant
    'rest_3': 78    // Points at third restaurant
  },
  createdAt: '2024-01-15T10:30:00Z'
}

// Order object
{
  id: 'order_123',
  restaurantId: 'rest_1',
  restaurantName: 'La Bella Cucina',
  customerId: 'customer_1',
  items: [...],
  total: 45.50,
  // Points earned: 45 (rounded down)
}
```

---

Happy Testing! 🎉