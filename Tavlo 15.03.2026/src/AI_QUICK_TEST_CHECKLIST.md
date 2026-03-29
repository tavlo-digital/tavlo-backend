# AI Features - Quick Test Checklist

## ✅ Before You Start
- [ ] Have test vendor account ready
- [ ] Have test customer access
- [ ] Menu has items with varying `orders` count
- [ ] Menu has items with dietary preferences set
- [ ] Some reviews/complaints exist in the system

---

## 🎯 Quick Tests (5 minutes)

### 1️⃣ AI Menu Assistant
**Path:** Vendor → Menu Management → Purple sparkle button
- [ ] Modal opens
- [ ] Shows slow-moving items
- [ ] Shows popular items
- [ ] Pricing suggestions appear
- [ ] Can edit AI descriptions

### 2️⃣ Smart Discovery Chips  
**Path:** Customer → Restaurant Menu → Below search bar
- [ ] See 3 filter chips (Popular, Vegetarian, Quick)
- [ ] Click "Most Popular" → filters work + purple active state
- [ ] Click "Vegetarian" → filters work + purple active state
- [ ] Click "Quick Dishes" → filters work + purple active state
- [ ] Can toggle filters on/off

### 3️⃣ AI Review Summary (Customer)
**Path:** Customer → Restaurant Name → Reviews Modal
- [ ] See "AI Review Summary" section
- [ ] Shows sentiment badge
- [ ] Lists positive points
- [ ] Lists negative points (if any)
- [ ] Shows confidence score

### 4️⃣ AI Review Summary (Vendor)
**Path:** Vendor → Reviews Tab → AI Review Summary card
- [ ] See purple "AI Review Summary" card
- [ ] Shows sentiment analysis
- [ ] Lists positive and negative points
- [ ] Shows confidence level
- [ ] Updates based on actual review data

### 5️⃣ AI Performance Insights (Dashboard)
**Path:** Vendor → Dashboard (Home) → AI Insights section
- [ ] See 2 insight cards on dashboard
- [ ] Click "View All Insights"
- [ ] Modal opens with 4 total insights
- [ ] Shows summary stats (positive, recommendations, warnings)
- [ ] Explains how AI works

### 6️⃣ AI Performance Insights (Analytics)
**Path:** Vendor → Analytics Tab → Top section
- [ ] See insight cards
- [ ] Click "View All Insights"
- [ ] Modal opens with all insights
- [ ] Shows summary stats
- [ ] Explains how AI works

### 7️⃣ Admin Platform Insights
**Path:** Admin → Dashboard → AI Insights section
- [ ] See 3 platform insights
- [ ] Shows risk scores
- [ ] Action buttons present
- [ ] Explanations visible

---

## 🔍 Deep Tests (15 minutes)

### AI Menu Assistant Deep Dive
- [ ] Check each tab (Menu Insights, Pricing, Content)
- [ ] Verify numbers match actual menu data
- [ ] Test "Apply Suggestion" buttons
- [ ] Edit AI-generated description
- [ ] Check all tooltips work

### Smart Discovery Deep Dive
- [ ] Test with empty results (no vegetarian items)
- [ ] Test with all items matching
- [ ] Combine with search filter
- [ ] Combine with category filter
- [ ] Check item count updates

### Review Analysis Deep Dive
- [ ] Add new review → check if summary updates
- [ ] Test with all 5-star reviews
- [ ] Test with mixed ratings
- [ ] Test with all negative reviews
- [ ] Verify theme extraction (food, service, etc.)

### Analytics Insights Deep Dive
- [ ] Check peak hours match chart data
- [ ] Verify customer retention calculation
- [ ] Test "View All Insights" modal
- [ ] Check if insights update with new data
- [ ] Verify all explanation tooltips

---

## 🐛 Common Issues to Check

### Not Working?
- [ ] Check browser console for errors
- [ ] Verify menu items have `orders` field
- [ ] Verify reviews exist
- [ ] Check modal backdrop is clickable to close
- [ ] Ensure AI helper functions are imported

### Wrong Data?
- [ ] Verify source data is correct
- [ ] Check calculation logic in `/utils/aiHelpers.ts`
- [ ] Look at explanation tooltips
- [ ] Check confidence scores

### Performance Issues?
- [ ] All AI runs client-side (should be instant)
- [ ] No API calls for AI analysis
- [ ] Check for console warnings

---

## 📊 Expected Results

**Menu Assistant:**
- Slow items: < 10 orders
- Popular items: > 30 orders
- Category gaps: < 3 items

**Smart Discovery:**
- Popular: ≥ 15 orders
- Vegetarian: has dietary tag
- Quick: appetizers/salads/drinks

**Review Sentiment:**
- Positive: > 70% positive keywords
- Negative: > 30% negative keywords
- Mixed: everything else

**Performance Insights:**
- Peak hours: highest order count
- Retention: % repeat customers
- LTV: average spend per customer

---

## 🎨 UI Elements to Verify

Every AI feature should have:
- [ ] Purple sparkle icon (Sparkles component)
- [ ] "AI" badge or indicator
- [ ] Explanation tooltip (ⓘ icon)
- [ ] Confidence score shown
- [ ] Data source mentioned
- [ ] Editable/dismissible
- [ ] Action buttons (optional)

---

## 🚀 Success = All Checked!

When everything works:
- AI feels helpful, not pushy
- All insights are explainable
- Performance is instant
- Users stay in control
- Data sources are clear
- Confidence is transparent