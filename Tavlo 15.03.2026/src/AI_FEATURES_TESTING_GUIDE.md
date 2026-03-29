# TAVLO AI Features - Complete Testing Guide

This guide shows you how to test each AI feature in TAVLO to verify they're working with real data and logic.

---

## 1. AI Menu Assistant (Vendor Dashboard)

**Location:** Vendor Dashboard → Menu Management → "AI Menu Assistant" button (purple sparkle icon)

**How to Test:**
1. Log in as a vendor (or create a demo vendor account)
2. Navigate to "Menu Management" tab
3. Click the purple "AI Menu Assistant" button in the top right
4. The modal should open with AI-powered suggestions

**What to Verify:**
- ✅ Modal opens with "AI Menu Assistant" title
- ✅ Shows 3 tabs: Menu Insights, Pricing Strategy, Content Suggestions
- ✅ **Menu Insights tab** displays:
  - Slow-moving items (items with < 10 orders)
  - Popular dishes (items with > 30 orders)
  - Category gaps (categories with < 3 items)
- ✅ **Pricing Strategy tab** shows:
  - Underpriced items (quality score > 4.5, price below average)
  - Overpriced items (low orders, price above average)
  - Price point opportunities (gaps in price ranges)
- ✅ **Content Suggestions** provides:
  - Items missing descriptions
  - Specific AI-generated description suggestions
  - Each suggestion is editable and actionable

**Behind the Scenes:**
- Uses `analyzeMenuPerformance()` from `/utils/aiHelpers.ts`
- Analyzes real menu data: orders, prices, categories, descriptions
- All suggestions are data-driven and explainable

---

## 2. Smart Discovery Chips (Customer Menu View)

**Location:** Customer app → Restaurant menu → Filter chips below search bar

**How to Test:**
1. Scan a QR code or open a restaurant menu as a customer
2. Look for the AI-powered filter chips below the search bar:
   - 🔥 Most Popular
   - 🌱 Vegetarian
   - ⚡ Quick Dishes

**What to Verify:**
- ✅ Chips are visible and have sparkle icons
- ✅ Clicking "Most Popular" filters to show only items with 15+ orders
- ✅ Clicking "Vegetarian" filters to items with vegetarian/vegan dietary tags
- ✅ Clicking "Quick Dishes" filters to appetizers, salads, and drinks
- ✅ Active chip has purple background
- ✅ Clicking again deactivates the filter
- ✅ Filtered item count updates in real-time

**Behind the Scenes:**
- Located in `/components/MenuList.tsx`
- Uses `aiFilter` state to manage active filter
- Filters work with real menu item data (orders, dietary preferences, categories)

**Test Data Setup:**
To see different results, ensure your menu has:
- Items with varying `orders` count (some > 15, some < 15)
- Items with `dietaryPreference: 'vegetarian'` or `dietary: ['Vegetarian']`
- Items in categories: 'appetizers', 'salads', 'drinks'

---

## 3. AI Review Summary (Customer - Restaurant Reviews)

**Location:** Customer app → Restaurant info/reviews modal → "AI Review Summary" section

**How to Test:**
1. Open a restaurant menu as a customer
2. Click on the restaurant name/rating at the top
3. Scroll to the "AI Review Summary" section (has purple sparkle icon)

**What to Verify:**
- ✅ Shows sentiment badge (Positive/Mixed/Negative)
- ✅ Displays AI-generated summary text
- ✅ Lists 3-5 positive points extracted from reviews
- ✅ Lists 1-3 negative points (if any issues found)
- ✅ Shows confidence score (based on review count)
- ✅ Expandable explanation of how AI analyzed the data

**Behind the Scenes:**
- Uses `analyzeReviews()` from `/utils/aiHelpers.ts`
- Analyzes review text for keywords and sentiment
- Extracts themes: food quality, service, ambiance, value
- Calculates confidence based on review count (>50 = high, 20-50 = medium, <20 = low)

**Test with Different Reviews:**
The analysis changes based on review content:
- Positive keywords: "amazing", "excellent", "perfect", "delicious"
- Negative keywords: "cold", "slow", "wrong", "disappointed"
- Theme detection: looks for "service", "food", "atmosphere", "price" mentions

---

## 4. AI Performance Insights (Vendor Analytics)

**Location:** Vendor Dashboard → Analytics tab → "AI Performance Insights" section

**How to Test:**
1. Log in as a vendor
2. Navigate to the "Analytics" tab
3. Look for the "AI Performance Insights" section at the top
4. Click "View All Insights" button to open the full modal

**What to Verify:**
- ✅ Shows 3-6 insight cards based on real data
- ✅ Insights include:
  - **Peak Hours Opportunity** (based on order time analysis)
  - **Customer Retention** (based on repeat customer rate)
  - **Slow-Moving Items** (items with low orders)
  - **Price Optimization** (items with pricing opportunities)
- ✅ Each insight has:
  - Type badge (recommendation/success/warning)
  - Title and description
  - Specific metric (e.g., "Potential additional revenue: €340/week")
  - Explainable reasoning
  - Optional action button
- ✅ "View All Insights" modal shows:
  - Summary cards (positive, recommendations, warnings)
  - All insights in grid layout
  - "How Our AI Works" explanation section

**Behind the Scenes:**
- Uses two analysis functions:
  - `analyzeOrderPatterns(dailyData)` - analyzes hourly order data
  - `analyzeCustomerRetention(topCustomers)` - analyzes customer behavior
- Real calculations:
  - Peak hour detection (max orders per hour)
  - Revenue opportunity estimation
  - Repeat customer rate calculation
  - Customer lifetime value trends

**Test Different Scenarios:**
- Modify `dailyData` in AnalyticsView to see different peak hours
- Change customer data to see retention insights change
- All metrics are calculated from actual data, not hardcoded

---

## 5. AI Platform Insights (Admin Dashboard)

**Location:** Admin Dashboard → Platform Overview → "AI Platform Insights" section

**How to Test:**
1. Log in as an admin user
2. Open the Admin Dashboard
3. Scroll to "AI Platform Insights" section

**What to Verify:**
- ✅ Shows 3 platform-level insights:
  - **High Risk Vendor Detected** (warning type)
  - **Revenue Optimization** (success type)  
  - **Churn Risk Alert** (recommendation type)
- ✅ Each insight includes:
  - Risk scores or revenue estimates
  - Actionable recommendations
  - Explanation of the analysis
  - Action buttons ("Review vendor", "View details")

**Behind the Scenes:**
- Uses `analyzeVendorRisk()` from `/utils/aiHelpers.ts`
- Analyzes vendor data: complaints, ratings, response times
- Identifies churn risk based on usage patterns
- Calculates revenue opportunities from feature utilization

**Future Enhancement:**
This is currently using mock data for demonstration. To make it fully functional:
1. Pass real vendor data to analysis functions
2. Connect action buttons to vendor details pages
3. Add filtering and sorting of insights

---

## 6. AI Review Summary (Vendor - Reviews Queue)

**Location:** Vendor Dashboard → Reviews tab

**How to Test:**
1. Log in as a vendor
2. Navigate to "Reviews" tab
3. The AI summary appears at the top of the reviews list

**What to Verify:**
- ✅ Shows overall sentiment analysis
- ✅ Highlights common themes (positive and negative)
- ✅ Provides actionable insights for improvement
- ✅ Updates when new reviews are added

**Behind the Scenes:**
- Same analysis as customer review summary
- Uses `analyzeReviews()` function
- Gives vendors insight into customer perception

---

## General Testing Tips

### Testing AI Tooltips and Explanations
Every AI feature has explanation tooltips:
1. Hover over the info icon (ⓘ) next to AI badges
2. Should show:
   - What the AI analyzed
   - Data sources used
   - Confidence level
   - How to interpret the results

### Testing Data Sources
All AI features show their data sources:
- "Based on 245 orders this month"
- "Analyzed 120 customer reviews"
- "Using your last 30 days of sales data"

### Testing Editability
AI suggestions are never forced:
- Menu descriptions can be edited before applying
- Pricing suggestions are just recommendations
- Filters can be toggled on/off
- All insights are optional guidance

### Testing Performance
AI analysis should be fast:
- Menu analysis: < 100ms (runs on client)
- Review sentiment: < 50ms (runs on client)
- Order pattern analysis: < 100ms (runs on client)

No external API calls - all analysis is done locally using the helper functions in `/utils/aiHelpers.ts`.

---

## Troubleshooting

### "No insights available"
- Check that you have menu items with order data
- Ensure reviews exist in the database
- Verify customer data is being loaded

### Filters not working
- Check that menu items have the required fields:
  - `orders` (number)
  - `dietaryPreference` or `dietary` array
  - `category` (string)

### Modal not opening
- Check browser console for errors
- Verify modal state is being set correctly
- Ensure modal component is imported

### Insights seem wrong
- All insights are calculated from real data
- Check the explanation tooltip to understand the calculation
- Verify the source data is accurate

---

## Code Locations

**Core AI Logic:**
- `/utils/aiHelpers.ts` - All AI analysis functions

**Components:**
- `/components/vendor/AIMenuAssistant.tsx` - Menu assistant modal
- `/components/vendor/AIInsightsModal.tsx` - Full insights modal
- `/components/ai/AIComponents.tsx` - Reusable AI UI components
- `/components/MenuList.tsx` - Smart Discovery chips (line 53+)
- `/components/RestaurantReviewsModal.tsx` - Customer review analysis
- `/components/vendor/AnalyticsView.tsx` - Vendor insights (line 60+)

**Analysis Functions:**
- `analyzeMenuPerformance()` - Menu insights
- `analyzeReviews()` - Review sentiment
- `analyzeOrderPatterns()` - Order timing analysis
- `analyzeCustomerRetention()` - Customer behavior
- `analyzeVendorRisk()` - Platform-level risk

---

## Success Criteria

All AI features should:
- ✅ Use real data (no hardcoded results)
- ✅ Provide explanations for every insight
- ✅ Show confidence levels
- ✅ Be editable/dismissible
- ✅ Display data sources
- ✅ Run fast (< 200ms)
- ✅ Feel invisible and helpful, not gimmicky
- ✅ Provide actionable recommendations
- ✅ Never make decisions for the user

---

**Last Updated:** 2024-12-16
**Version:** 1.0.0
