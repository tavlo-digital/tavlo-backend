# Backend Integration Analysis - Restaurant Page

## Current Problem ❌

The **RestaurantPage** component (`/components/restaurant/RestaurantPage.tsx`) is displaying **HARDCODED MOCK DATA** instead of fetching real-time data from the backend vendor settings.

### What the Screenshot Shows (Expected to be Dynamic):

1. **Restaurant Name**: "Bella Italia" → Should come from `settings.restaurantName`
2. **Cuisine Type**: "Italian Fine Dining" → Should come from backend
3. **Price Level**: "€€" → Should be calculated or stored
4. **Opening Hours**: "Open now - Closes at 23:00" → Should come from `settings.businessHours`
5. **Loyalty Points**: "Earn 5 pts / €1" → Should come from `settings.pointsPerEuro`
6. **Rating**: "4.8" with "234 reviews" → Should come from aggregated reviews
7. **Address**: "Stephansplatz 12, 1010 Vienna, Austria" → Should come from `settings.address`
8. **Prep Time**: "15-20 min" → Should come from `settings.estimatedPrepTime`
9. **Badges**: Verified, Takeaway, Cards Accepted → Should come from settings
10. **Promotions**: "Offer ends in 2h" → Should come from backend promotions system
11. **Menu Items**: All menu data → Should come from `api.getMenu(restaurantId)`

---

## Backend Data Structure

### Available from `/vendor/:id/settings`:

```typescript
{
  // Business Information
  restaurantName: string,
  description: string,
  businessRegNumber: string,
  vatNumber: string,
  email: string,
  phone: string,
  website: string,
  address: string,
  logo: string,
  coverPhoto: string,
  
  // Business Hours
  businessHours: {
    monday: { open: string, close: string, closed: boolean },
    tuesday: { open: string, close: string, closed: boolean },
    // ... all 7 days
  },
  
  // Payment Settings
  acceptApplePay: boolean,
  acceptGooglePay: boolean,
  acceptCard: boolean,
  acceptCash: boolean,
  
  // Ordering Settings
  estimatedPrepTime: number,  // in minutes
  
  // Loyalty Settings
  enableLoyalty: boolean,
  pointsPerEuro: number,       // e.g., 5 points per €1
  
  // And many more...
}
```

### Available from `/restaurants/:id`:

```typescript
{
  id: string,
  name: string,
  cuisineTag: string,
  address: string,
  phone: string
}
```

### Available from `/restaurants/:id/menu`:

```typescript
{
  categories: Array<{ id: string, name: string }>,
  items: Array<{
    id: string,
    name: string,
    category: string,
    price: number,
    description: string,
    // ... full menu item data with translations
  }>
}
```

---

## Current Code Issues

### ❌ RestaurantPage.tsx (Lines 24-59):

```typescript
// Mock restaurant data
const MOCK_RESTAURANT = {
  id: 'rest_1',
  name: 'Bella Italia',  // HARDCODED
  cuisine: 'Italian Fine Dining',  // HARDCODED
  rating: 4.8,  // HARDCODED
  reviewCount: 234,  // HARDCODED
  priceLevel: 2,  // HARDCODED
  address: 'Stephansplatz 12, 1010 Vienna, Austria',  // HARDCODED
  phone: '+43 1 234 5678',  // HARDCODED
  hours: 'Mon-Thu: 11:00 - 22:00\\nFri-Sat: 11:00 - 23:00\\nSun: 12:00 - 21:00',  // HARDCODED
  coverImage: 'https://images.unsplash.com/photo-1662197480393-2a82030b7b83?w=1200',  // HARDCODED
  description: 'Experience authentic Italian cuisine in the heart of Vienna...',  // HARDCODED
  openingHours: {
    monday: { open: '11:00', close: '22:00' },  // HARDCODED
    // ... all hardcoded
  },
  features: {
    loyaltyProgram: true,  // HARDCODED
    loyaltyRate: '5 pts / €1',  // HARDCODED - should come from settings.pointsPerEuro
    takeawayAvailable: true,  // HARDCODED
    promotionsActive: true,  // HARDCODED
    promotionEndsAt: '2h',  // HARDCODED
    verified: true,  // HARDCODED
    acceptsCards: true,  // HARDCODED
  }
}
```

### ❌ MOCK_MENU (Lines 61-143):
All menu items are hardcoded instead of fetched from backend.

---

## What Needs to be Fixed

### 1. **Fetch Restaurant Data on Mount**
```typescript
useEffect(() => {
  const loadRestaurantData = async () => {
    try {
      setLoading(true);
      
      // Fetch restaurant basic info
      const restaurant = await api.getRestaurant(restaurantId);
      
      // Fetch vendor settings (contains most of the data)
      const settings = await api.getVendorSettings(restaurantId);
      
      // Fetch menu
      const menu = await api.getMenu(restaurantId);
      
      setRestaurantData({ restaurant, settings, menu });
    } catch (error) {
      console.error('Failed to load restaurant data:', error);
    } finally {
      setLoading(false);
    }
  };
  
  loadRestaurantData();
}, [restaurantId]);
```

### 2. **Update RestaurantHeader Component**
Pass dynamic data from settings:
```typescript
<RestaurantHeader
  coverImage={restaurantData.settings.coverPhoto || defaultCoverImage}
  name={restaurantData.settings.restaurantName}
  cuisine={restaurantData.restaurant.cuisineTag}
  address={restaurantData.settings.address}
  openingHours={restaurantData.settings.businessHours}
  // ...
/>
```

### 3. **Calculate "Open Now" Status Dynamically**
```typescript
const isOpenNow = () => {
  const now = new Date();
  const dayName = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][now.getDay()];
  const hours = restaurantData.settings.businessHours[dayName];
  
  if (hours.closed) return false;
  
  const currentTime = now.getHours() * 60 + now.getMinutes();
  const [openHour, openMin] = hours.open.split(':').map(Number);
  const [closeHour, closeMin] = hours.close.split(':').map(Number);
  
  const openTime = openHour * 60 + openMin;
  const closeTime = closeHour * 60 + closeMin;
  
  return currentTime >= openTime && currentTime < closeTime;
};
```

### 4. **Display Loyalty Points from Settings**
```typescript
const loyaltyRate = restaurantData.settings.enableLoyalty 
  ? `${restaurantData.settings.pointsPerEuro} pts / €1`
  : null;
```

### 5. **Show Prep Time from Settings**
```typescript
const prepTime = restaurantData.settings.estimatedPrepTime;
const prepTimeDisplay = `${prepTime-5}-${prepTime} min`; // e.g., "15-20 min"
```

### 6. **Load Menu from Backend**
```typescript
<MenuSection 
  menu={restaurantData.menu.items} 
  categories={restaurantData.menu.categories}
  currency={restaurantData.settings.currency || '€'}
/>
```

### 7. **Payment Methods Badges**
```typescript
const paymentMethods = [];
if (settings.acceptCard) paymentMethods.push('Card');
if (settings.acceptApplePay) paymentMethods.push('Apple Pay');
if (settings.acceptGooglePay) paymentMethods.push('Google Pay');
if (settings.acceptCash) paymentMethods.push('Cash');
```

---

## Components That Need Updates

### 1. `/components/restaurant/RestaurantPage.tsx`
- Remove `MOCK_RESTAURANT` constant
- Add `useState` for restaurant data
- Add `useEffect` to fetch data
- Add loading state
- Pass dynamic data to child components

### 2. `/components/restaurant/RestaurantHeader.tsx`
- Ensure it receives and displays dynamic data
- Calculate "Open now" status dynamically
- Show loyalty rate from settings

### 3. `/components/restaurant/MenuSection.tsx`
- Should work with backend menu structure
- Handle menu categories from backend

### 4. `/components/restaurant/OrderingOptions.tsx`
- Use `settings.estimatedPrepTime` for prep time display
- Check `settings.takeawayAvailable` or similar

### 5. `/components/restaurant/AboutSection.tsx`
- Use `settings.description`
- Use `settings.website`
- Use `settings.phone`

---

## Expected Data Flow

```
1. User clicks restaurant card on HomePage
   ↓
2. PlatformApp navigates to RestaurantPage with restaurantId
   ↓
3. RestaurantPage.useEffect() fires:
   - api.getRestaurant(restaurantId) → Basic info
   - api.getVendorSettings(restaurantId) → All settings
   - api.getMenu(restaurantId) → Full menu
   ↓
4. RestaurantPage renders with REAL DATA:
   - RestaurantHeader shows settings.restaurantName
   - Opening hours from settings.businessHours
   - Loyalty from settings.pointsPerEuro
   - Menu from api.getMenu()
   ↓
5. Vendor updates settings in dashboard
   ↓
6. Next time user visits, they see UPDATED DATA
```

---

## Testing Checklist

After fixing, verify these scenarios:

- [ ] Restaurant name displays from vendor settings
- [ ] Opening hours display correctly and "Open now" status is accurate
- [ ] Loyalty rate shows correct value from `settings.pointsPerEuro`
- [ ] Prep time displays `settings.estimatedPrepTime`
- [ ] Menu items load from backend (not hardcoded)
- [ ] Address displays from settings
- [ ] Payment method badges reflect settings
- [ ] Vendor updates settings → Restaurant page reflects changes immediately
- [ ] Menu updates from vendor dashboard → Customer sees new menu

---

## Priority: CRITICAL 🔴

**Why**: The entire restaurant page is showing fake data. Vendor settings changes have ZERO effect on what customers see. This breaks the core promise of the system.

**Impact**: 
- Vendors cannot manage their restaurant information
- Menu changes don't appear to customers
- Opening hours are wrong
- Loyalty program settings are ignored
- Price changes don't reflect

**Fix Required**: Complete refactor of RestaurantPage to use backend data.

---

## ✅ FIXED - Implementation Complete

### What Was Changed:

1. **Added Backend Data Fetching** (`/components/restaurant/RestaurantPage.tsx`)
   - Added `useState` for loading, restaurantData, settings, and menu
   - Added `useEffect` to fetch data on component mount
   - Fetches 3 API endpoints in parallel: `getRestaurant`, `getVendorSettings`, `getMenu`

2. **Loading & Error States**
   - Shows spinner while loading
   - Shows error message with "Go Back" button if data fails to load

3. **Dynamic Data Display**
   - Restaurant name: `settings.restaurantName`
   - Address: `settings.address`
   - Phone: `settings.phone`
   - Opening hours: `settings.businessHours`
   - Loyalty rate: `settings.pointsPerEuro`
   - Prep time: `settings.estimatedPrepTime`
   - Menu items: `menu.items` from backend
   - Currency: `settings.currency`
   - Description: `settings.description`
   - Website: `settings.website`
   - VAT Number: `settings.vatNumber`

4. **Features from Settings**
   ```typescript
   const features = {
     loyaltyProgram: settings.enableLoyalty,
     loyaltyRate: `${settings.pointsPerEuro} pts / €1`,
     acceptsCards: settings.acceptCard,
     // ... etc
   };
   ```

### What Now Updates from Vendor Dashboard:

✅ **Restaurant Name** - Vendor updates in Settings → Customers see new name  
✅ **Address** - Vendor updates → Customers see new address  
✅ **Phone** - Vendor updates → Customers see new phone  
✅ **Opening Hours** - Vendor updates → "Open now" status updates  
✅ **Loyalty Points** - Vendor changes pointsPerEuro → Badge updates  
✅ **Prep Time** - Vendor changes estimatedPrepTime → "15-20 min" updates  
✅ **Menu** - Vendor updates menu → Customers see new menu items  
✅ **Description** - Vendor updates → About section updates  
✅ **Website** - Vendor updates → About section updates  
✅ **Payment Methods** - Vendor enables/disables payment methods → Updates in checkout & About tab  

### Payment Methods Integration:

✅ **PaymentFlow Component** - Already had `isPaymentMethodEnabled()` logic  
✅ **AboutSection Component** - Now receives dynamic `paymentMethods` array  
✅ **RestaurantPage** - Builds payment methods from settings:
```typescript
const paymentMethods = [];
if (settings.acceptCard) paymentMethods.push('Card');
if (settings.acceptApplePay) paymentMethods.push('Apple Pay');
if (settings.acceptGooglePay) paymentMethods.push('Google Pay');
if (settings.acceptCash) paymentMethods.push('Cash');
```

### How Payment Methods Update:

1. **Vendor Dashboard** → Settings → Payment tab → Toggle "Accept Apple Pay" OFF
2. **Backend** → Saves `acceptApplePay: false` to `vendor:rest_1:settings`
3. **Customer Side:**
   - **Restaurant About Tab** → "Apple Pay" removed from payment methods list
   - **Checkout Flow** → "Apple Pay" button hidden (already working)
   - **Split Bill Flow** → "Apple Pay" option not shown (already working)

### Still Using Mock Data (TODO):

⚠️ **Rating & Reviews** - Not aggregated from backend yet  
⚠️ **Price Level** - Not calculated/stored in backend yet  
⚠️ **Location Coordinates** - Not in settings yet  
⚠️ **Promotions** - System not implemented yet  
⚠️ **Hours formatted string** - Using hardcoded format

### Testing Results:

1. Visit restaurant page → Backend data loads ✅
2. Update restaurant name in vendor settings → Name updates on page ✅
3. Change loyalty points (1 → 5) → Badge shows "5 pts / €1" ✅
4. Update estimated prep time (20 → 30) → Shows "25-30 min" ✅
5. Update menu in vendor dashboard → New menu appears ✅

---

## Remaining Work:

1. **Add Promotions System**
   - Create promotions table in backend
   - Add promotion management to vendor dashboard
   - Display active promotions on restaurant page

2. **Aggregate Reviews**
   - Calculate average rating from order reviews
   - Count total reviews
   - Display real reviews instead of MOCK_REVIEWS

3. **Add "Takeaway Available" Setting**
   - Add toggle to vendor settings
   - Use in features object

4. **Add "Verified" Badge Logic**
   - Determine verification criteria
   - Store in vendor profile

5. **Calculate Price Level**
   - Average menu item prices
   - Categorize as €, €€, or €€€
   - Store or calculate dynamically