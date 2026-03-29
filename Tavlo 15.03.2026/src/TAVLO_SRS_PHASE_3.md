# TAVLO — Software Requirements Specification  
## PHASE 3 — FULL PLATFORM (Intelligence & Scale)

**Document Version:** 4.0  
**Last Updated:** December 26, 2024  
**Status:** Planned  
**Timeline:** Weeks 21-36  
**Goal:** AI-Powered Intelligence, Scale & Enterprise Features

---

## TABLE OF CONTENTS

1. [Introduction](#1-introduction)
2. [Phase 3 Overview](#2-phase-3-overview)
3. [Customer Features](#3-customer-features)
4. [Vendor Features](#4-vendor-features)
5. [Admin Features](#5-admin-features)
6. [Platform Pages](#6-platform-pages)

---

## 1. INTRODUCTION

### 1.1 Purpose
This document specifies Phase 3 functional requirements for TAVLO. Phase 3 focuses on AI-powered intelligence, enterprise-grade features, delivery integration, white-label solutions, and platform scalability to become a market-leading restaurant ordering platform.

### 1.2 Prerequisites
- Phase 1 and Phase 2 must be complete and stable
- Minimum 100 active vendors using the platform
- Minimum 10,000 monthly active customers
- Proven business model with positive unit economics

### 1.3 Phase 3 Goals
- 🤖 **AI Intelligence:** Personalized recommendations, smart pricing, predictive analytics
- 🚚 **Delivery Integration:** In-house delivery or third-party integration
- 🏢 **Enterprise Features:** Multi-location, white-label, API access
- 🌍 **International Expansion:** Multi-currency, multi-region
- 📊 **Advanced Analytics:** Machine learning insights, business intelligence

---

## 2. PHASE 3 OVERVIEW

### 2.1 Feature Count
- **Customer Features:** 8
- **Vendor Features:** 14
- **Admin Features:** 10
- **Platform Pages:** 9
- **Total:** 41 features

### 2.2 Success Criteria
- [ ] AI recommendations drive 30% of orders
- [ ] Delivery option available in 3+ major cities
- [ ] 10+ enterprise clients (multi-location restaurants)
- [ ] 5+ white-label partnerships
- [ ] Platform supports 1,000+ vendors, 100,000+ monthly orders

---

## 3. CUSTOMER FEATURES

### 3.1 AI & Personalization

#### FR-C3-001: AI-Powered Dish Recommendations
**Priority:** HIGH  
**Description:** ML model recommends dishes based on customer preferences and behavior

**Acceptance Criteria:**
- "Recommended for You" section on restaurant menus
- Shows 3-5 items based on: past orders, dietary preferences, trending items, collaborative filtering (similar users)
- "Why recommended?" tooltip (e.g., "Popular with vegetarians like you")
- Recommendations updated in real-time as customer browses

**Business Rules:**
- Requires logged-in user with order history (min 3 orders)
- ML model trained on aggregated user behavior
- Privacy-preserving (no individual tracking shown to vendor)

**Technical Notes:**
- Use collaborative filtering algorithm:
```python
# Simplified recommendation model
from sklearn.metrics.pairwise import cosine_similarity

# User-item matrix (users × menu items)
user_item_matrix = create_matrix(orders)

# Find similar users
user_similarity = cosine_similarity(user_item_matrix)

# Recommend items ordered by similar users
def recommend(user_id, n=5):
    similar_users = get_top_similar_users(user_id, user_similarity)
    their_orders = get_orders(similar_users)
    my_orders = get_orders(user_id)
    # Recommend items I haven't tried but similar users love
    recommendations = their_orders - my_orders
    return top_n(recommendations, n)
```

#### FR-C3-002: Voice Ordering
**Priority:** MEDIUM  
**Description:** Customer orders using voice commands

**Acceptance Criteria:**
- Microphone icon on menu page
- Tap → voice input starts
- Customer says: "I'll have a margherita pizza and a coke"
- AI transcribes and adds items to basket
- Confirmation: "Added margherita pizza and coke. Anything else?"
- Multi-turn conversation supported

**Business Rules:**
- Requires microphone permission
- Fallback to text if speech recognition fails
- Supported languages: German, English

**Technical Notes:**
- Use Web Speech API or Google Cloud Speech-to-Text
- NLP to extract menu items from speech:
```typescript
const processVoiceOrder = async (transcript: string) => {
  // Use NLP to extract items
  const items = await extractMenuItems(transcript, restaurantId);
  
  // Add to basket
  for (const item of items) {
    await addToBasket(item.menu_item_id, item.quantity);
  }
  
  // Respond
  const response = `Added ${items.map(i => i.name).join(' and ')}. Anything else?`;
  speak(response);
};
```

#### FR-C3-003: AR Menu (Augmented Reality)
**Priority:** LOW  
**Description:** Customer views dishes in 3D using smartphone camera

**Acceptance Criteria:**
- "View in AR" button on dish detail
- Opens camera with 3D model of dish overlayed
- Customer can rotate, zoom 3D model
- "Add to Basket" button in AR view

**Business Rules:**
- Requires ARCore (Android) or ARKit (iOS)
- Vendor must upload 3D models (optional)
- Fallback to 2D image if AR not supported

**Technical Notes:**
- Use Three.js or React Three Fiber for 3D rendering
- AR.js for web AR experience
- Vendor uploads .gltf or .glb 3D models

#### FR-C3-004: Allergen Scanner (Image Recognition)
**Priority:** LOW  
**Description:** Customer scans dish image to detect allergens

**Acceptance Criteria:**
- "Scan for Allergens" button
- Upload photo of dish or scan with camera
- AI analyzes image and detects potential allergens
- Warning shown if allergens detected (e.g., "May contain nuts")

**Business Rules:**
- Not 100% accurate - warning disclaimer required
- Uses customer's dietary preferences for alerts
- Enterprise plan feature for vendors

**Technical Notes:**
- Use computer vision API (Google Cloud Vision or custom model)
- Trained on food image dataset with allergen labels

---

### 3.2 Delivery

#### FR-C3-005: Delivery Option
**Priority:** HIGH  
**Description:** Customer orders food for delivery

**Acceptance Criteria:**
- Order type selection: Dine-in, Takeaway, Delivery
- Delivery address input with autocomplete
- Delivery fee shown (calculated by distance)
- Estimated delivery time shown (based on distance + prep time)
- Real-time driver tracking on map
- Push notifications: order confirmed, driver assigned, out for delivery, delivered

**Business Rules:**
- Delivery available only if vendor enables
- Min order value for delivery (vendor setting)
- Delivery radius (max 10km)
- Delivery fee: vendor-defined or distance-based

**Technical Notes:**
- Integrate with delivery partner API (e.g., Stuart, Uber Direct) or in-house fleet
- Calculate delivery fee:
```typescript
const calculateDeliveryFee = (distanceKm: number, vendor: Vendor) => {
  if (vendor.delivery_fee_type === 'flat') {
    return vendor.delivery_fee;
  } else {
    // Distance-based
    const baseFee = 2.0;
    const perKmFee = 0.50;
    return baseFee + (distanceKm * perKmFee);
  }
};
```

#### FR-C3-006: Track Delivery Driver
**Priority:** MEDIUM  
**Description:** Real-time map showing driver location

**Acceptance Criteria:**
- Order tracking page shows map with driver pin
- Driver location updates every 10 seconds
- ETA countdown (minutes remaining)
- Driver name, photo, vehicle type shown
- "Call Driver" button
- "Report Issue" button

**Business Rules:**
- Only shown after driver assigned
- Requires GPS permission
- Driver location anonymized after delivery

**Technical Notes:**
- WebSocket for real-time location updates
- Use Mapbox or Google Maps for display
```typescript
// Subscribe to driver location
supabase
  .channel(`delivery:${orderId}`)
  .on('postgres_changes',
    { event: 'UPDATE', schema: 'public', table: 'deliveries' },
    (payload) => {
      updateDriverPin(payload.new.driver_lat, payload.new.driver_lng);
      updateETA(payload.new.estimated_arrival);
    }
  )
  .subscribe();
```

#### FR-C3-007: Delivery Ratings
**Priority:** LOW  
**Description:** Customer rates delivery experience separately

**Acceptance Criteria:**
- After delivery, prompt: "Rate your delivery"
- Ratings: food quality (1-5 stars), delivery speed (1-5 stars), driver (1-5 stars)
- Optional text feedback
- Tip driver (optional, post-delivery)

**Business Rules:**
- One rating per delivery order
- Driver rating shared with delivery partner
- Poor ratings flagged for investigation

**Technical Notes:**
- Store in `delivery_ratings`:
```typescript
{
  id: string;
  order_id: string;
  customer_id: string;
  food_rating: number;
  speed_rating: number;
  driver_rating: number;
  feedback_text: string | null;
  tip_amount: number; // post-delivery tip
  created_at: timestamp;
}
```

#### FR-C3-008: Scheduled Delivery
**Priority:** LOW  
**Description:** Customer schedules delivery for future time

**Acceptance Criteria:**
- "Schedule for Later" option at checkout
- Date picker: today to +7 days
- Time picker: available delivery slots
- Order prepared and delivered at scheduled time
- Reminder notification 30 min before

**Business Rules:**
- Min advance notice: 2 hours
- Max advance: 7 days
- Vendor can limit slots per hour
- Prepayment required

**Technical Notes:**
- Store `scheduled_delivery_time` in orders
- Cron job moves scheduled orders to active queue
- Driver assigned 30 min before scheduled time

---

## 4. VENDOR FEATURES

### 4.1 Advanced AI Features

#### FR-V3-001: AI Menu Optimization
**Priority:** HIGH  
**Description:** AI analyzes menu performance and suggests optimizations

**Acceptance Criteria:**
- "Menu Insights" dashboard shows: underperforming items (low sales, low margin), overperforming items (high sales, high margin), optimal pricing suggestions, seasonal recommendations
- AI-generated insights: "Consider raising price of {item} by 10% based on demand"
- "Apply Suggestion" button (vendor can accept/reject)
- Insights updated weekly

**Business Rules:**
- Enterprise plan feature
- Requires min 6 months of sales data
- Vendor always has final decision (AI suggests, never auto-applies)

**Technical Notes:**
- ML model trained on: sales volume, pricing elasticity, seasonal trends, competitor pricing
- Use regression analysis for price optimization:
```python
from sklearn.linear_model import LinearRegression

# Train model: price vs sales volume
model = LinearRegression()
model.fit(historical_prices, sales_volumes)

# Predict optimal price (max revenue)
optimal_price = find_price_that_maximizes(model.predict, revenue_function)
```

#### FR-V3-002: Demand Forecasting
**Priority:** MEDIUM  
**Description:** Predict order volume for upcoming days/weeks

**Acceptance Criteria:**
- "Demand Forecast" chart showing predicted orders for next 7 days
- Breakdown by day of week, hour
- Confidence interval shown (optimistic/pessimistic scenarios)
- Alerts if forecasted demand exceeds capacity
- Staffing recommendations based on forecast

**Business Rules:**
- Enterprise plan feature
- Requires min 3 months of data
- Updated daily

**Technical Notes:**
- Time series forecasting model (ARIMA or Prophet)
```python
from fbprophet import Prophet

# Historical data
df = get_historical_orders(vendor_id)

# Train Prophet model
model = Prophet(yearly_seasonality=True, weekly_seasonality=True)
model.fit(df)

# Forecast next 7 days
future = model.make_future_dataframe(periods=7)
forecast = model.predict(future)
```

#### FR-V3-003: Churn Prediction
**Priority:** LOW  
**Description:** Identify customers at risk of churning

**Acceptance Criteria:**
- "At-Risk Customers" dashboard
- List of customers with churn probability (high/medium/low)
- Suggested actions: "Send 20% off coupon", "Offer free dessert"
- Automated win-back campaigns (Phase 3+)

**Business Rules:**
- Enterprise plan feature
- Privacy-compliant (no individual tracking shown to vendor without consent)
- Opt-out available for customers

**Technical Notes:**
- Churn prediction model (Logistic Regression or Random Forest)
```python
# Features
features = [
  'days_since_last_order',
  'total_orders',
  'avg_order_value',
  'avg_rating_given',
  'loyalty_points_balance'
]

# Train classifier
from sklearn.ensemble import RandomForestClassifier
model = RandomForestClassifier()
model.fit(X_train, y_train)  # y = churned (0/1)

# Predict churn probability
churn_prob = model.predict_proba(customer_features)[:, 1]
```

#### FR-V3-004: Smart Pricing (Dynamic)
**Priority:** MEDIUM  
**Description:** AI adjusts prices in real-time based on demand

**Acceptance Criteria:**
- Enable "Smart Pricing" toggle in settings
- AI adjusts prices within vendor-defined range (e.g., ±20%)
- Price changes based on: time of day, demand level, inventory, weather, competitor pricing
- Vendor sees price change notifications
- Can override AI pricing anytime

**Business Rules:**
- Enterprise plan feature
- Vendor sets min/max price bounds
- Customers see "Dynamic pricing" badge
- Price locked once item added to basket

**Technical Notes:**
- Reinforcement learning model optimizes for revenue
```python
# Simplified dynamic pricing
def calculate_dynamic_price(base_price, demand_level, inventory_level):
    price = base_price
    
    # Increase price if high demand
    if demand_level > 0.8:
        price *= 1.2
    
    # Decrease price if excess inventory (perishable items)
    if inventory_level > 0.9:
        price *= 0.8
    
    return price
```

---

### 4.2 Multi-Location & Enterprise

#### FR-V3-005: Multi-Location Management
**Priority:** HIGH  
**Description:** Manage multiple restaurant locations from single dashboard

**Acceptance Criteria:**
- "Locations" page showing all locations
- Add new location: name, address, separate menu (or inherit from parent), separate settings
- Dashboard switcher: "Viewing: Vienna Location" dropdown
- Aggregated analytics across all locations
- Bulk operations: update menu across all locations

**Business Rules:**
- Enterprise plan feature
- Unlimited locations
- Each location can have different menu or share parent menu

**Technical Notes:**
- Extend vendor model:
```typescript
{
  // ... existing fields
  parent_vendor_id: string | null; // null for parent, UUID for child
  location_name: string; // "Vienna Downtown", "Graz Station"
  inherit_menu: boolean; // if true, uses parent's menu
}
```

#### FR-V3-006: White-Label Solution
**Priority:** HIGH  
**Description:** Custom-branded ordering system for enterprise clients

**Acceptance Criteria:**
- White-label settings: custom domain (e.g., order.restaurant.com), custom logo, custom color scheme, custom email templates, remove TAVLO branding
- Customer sees fully branded experience
- Vendor dashboard still uses TAVLO branding (backend)
- SSL certificate for custom domain

**Business Rules:**
- Enterprise plan feature
- Setup fee: €500 one-time
- Monthly fee: €199 (included in Enterprise plan)

**Technical Notes:**
- Multi-tenancy architecture
- Custom CSS per vendor:
```typescript
const getVendorBranding = async (domain: string) => {
  const vendor = await getVendorByDomain(domain);
  return {
    primaryColor: vendor.white_label_config.primary_color,
    logo: vendor.white_label_config.logo_url,
    favicon: vendor.white_label_config.favicon_url,
    customCSS: vendor.white_label_config.custom_css
  };
};
```

#### FR-V3-007: API Access for POS Integration
**Priority:** MEDIUM  
**Description:** RESTful API for third-party POS systems

**Acceptance Criteria:**
- API documentation (OpenAPI spec)
- Authentication via API keys
- Endpoints: create order, update order status, get menu, update menu item availability, webhook subscriptions
- Rate limiting: 1000 requests/hour
- Sandbox environment for testing

**Business Rules:**
- Enterprise plan feature
- API keys can be revoked
- Webhooks for real-time order notifications

**Technical Notes:**
- RESTful API with JWT authentication
```typescript
// Example API endpoints
POST /api/v1/orders
GET /api/v1/orders/{id}
PUT /api/v1/orders/{id}/status
GET /api/v1/menu
PATCH /api/v1/menu/items/{id}

// Webhook events
order.created
order.updated
order.completed
```

#### FR-V3-008: Franchise Management
**Priority:** LOW  
**Description:** Manage franchise locations with centralized control

**Acceptance Criteria:**
- Franchise owner (master account) manages multiple franchisee accounts
- Centralized menu management (push to all locations)
- Franchisee can customize pricing within bounds set by master
- Aggregated reporting across all franchises
- Revenue sharing rules (franchisee vs franchisor)

**Business Rules:**
- Enterprise plan feature
- Master account has read-only access to franchisee analytics
- Franchisee cannot modify core menu (only pricing)

**Technical Notes:**
- Franchise hierarchy in database
```typescript
{
  master_vendor_id: string;
  franchisee_vendor_id: string;
  revenue_split_percentage: number; // franchisee's share
  menu_control: 'full' | 'pricing_only' | 'none';
}
```

---

### 4.3 Operations & Automation

#### FR-V3-009: Automated Inventory Management
**Priority:** MEDIUM  
**Description:** Auto-track ingredient usage and reorder when low

**Acceptance Criteria:**
- Ingredients linked to menu items with recipes (e.g., "Margherita Pizza uses 200g dough, 100g sauce, 150g cheese")
- Each order automatically deducts ingredient quantities
- Low stock alerts (when stock < reorder level)
- Auto-generate purchase orders to suppliers
- Supplier integration (email or API)

**Business Rules:**
- Enterprise plan feature
- Vendor can enable/disable auto-deduction
- Manual stock adjustments allowed (for shrinkage, waste)

**Technical Notes:**
- On order completion:
```typescript
const deductInventory = async (orderId: string) => {
  const orderItems = await getOrderItems(orderId);
  
  for (const item of orderItems) {
    const recipe = await getRecipe(item.menu_item_id);
    
    for (const ingredient of recipe.ingredients) {
      await supabase
        .from('ingredients')
        .update({
          current_stock: supabase.raw('current_stock - ?', [
            ingredient.quantity_required * item.quantity
          ])
        })
        .eq('id', ingredient.ingredient_id);
    }
  }
  
  // Check for low stock and trigger alerts
  await checkLowStockAlerts(vendorId);
};
```

#### FR-V3-010: Kitchen Display System (Advanced)
**Priority:** MEDIUM  
**Description:** Multi-station KDS with routing and priority

**Acceptance Criteria:**
- Multiple KDS screens for different stations (grill, fryer, salad, dessert)
- Orders auto-routed to appropriate stations based on items
- Priority system: express orders, VIP customers shown first
- Estimated prep time per station
- Bump bar integration (physical buttons to mark items done)

**Business Rules:**
- Enterprise plan feature
- Supports unlimited KDS screens
- Routing rules configurable per vendor

**Technical Notes:**
- Order routing logic:
```typescript
const routeOrder = (order: Order) => {
  const routes = {
    grill: [],
    fryer: [],
    salad: [],
    dessert: []
  };
  
  for (const item of order.items) {
    const station = item.menu_item.station; // configured in menu
    routes[station].push(item);
  }
  
  // Send to respective KDS screens
  for (const [station, items] of Object.entries(routes)) {
    broadcastToKDS(station, { order, items });
  }
};
```

#### FR-V3-011: Automated Marketing Campaigns
**Priority:** LOW  
**Description:** AI-driven email/SMS marketing campaigns

**Acceptance Criteria:**
- Campaign builder: select audience (all customers, regulars, churned), select trigger (customer birthday, X days since last order), compose message (template or custom), set send time
- AI suggests optimal send time per customer
- Track: open rate, click rate, conversion rate
- A/B test campaigns

**Business Rules:**
- Enterprise plan feature
- Customer opt-in required (GDPR)
- Unsubscribe link in all emails

**Technical Notes:**
- Campaign scheduling:
```typescript
const scheduleCampaign = async (campaign: Campaign) => {
  const customers = await getAudience(campaign.audience_rules);
  
  for (const customer of customers) {
    const optimalTime = await predictOptimalSendTime(customer.id);
    
    await scheduleMessage({
      customer_id: customer.id,
      message: campaign.message,
      send_at: optimalTime,
      channel: customer.preferred_channel // email or SMS
    });
  }
};
```

#### FR-V3-012: Labor Cost Tracking
**Priority:** LOW  
**Description:** Track staff hours and calculate labor cost per order

**Acceptance Criteria:**
- Staff clock-in/out via vendor dashboard
- Time tracking: hours worked per day/week
- Labor cost calculation: total wages / total orders = labor cost per order
- Analytics: labor cost %, peak labor hours, overtime alerts

**Business Rules:**
- Enterprise plan feature
- Integrates with payroll systems (Phase 3+)
- Privacy: individual wages not shown in reports (only aggregates)

**Technical Notes:**
- Store in `staff_timeclock`:
```typescript
{
  id: string;
  vendor_id: string;
  staff_id: string;
  clock_in: timestamp;
  clock_out: timestamp | null;
  hours_worked: number;
  hourly_rate: number;
}
```

---

### 4.4 Advanced Reporting

#### FR-V3-013: Custom Report Builder
**Priority:** MEDIUM  
**Description:** Build custom reports with drag-and-drop

**Acceptance Criteria:**
- Report builder interface: select metrics (revenue, orders, avg order value, etc.), select dimensions (date, category, payment method), select filters (date range, order type), select visualization (table, chart, graph)
- Save custom reports
- Schedule reports (email daily/weekly)
- Export to PDF, Excel, CSV

**Business Rules:**
- Enterprise plan feature
- Max 10 saved reports per vendor
- Scheduled reports sent to vendor email

**Technical Notes:**
- Query builder generates SQL dynamically:
```typescript
const buildQuery = (report: Report) => {
  let query = `SELECT ${report.metrics.join(', ')} FROM orders`;
  
  if (report.dimensions.length) {
    query += ` GROUP BY ${report.dimensions.join(', ')}`;
  }
  
  if (report.filters.length) {
    const whereClause = report.filters.map(f => `${f.field} ${f.operator} ${f.value}`).join(' AND ');
    query += ` WHERE ${whereClause}`;
  }
  
  return query;
};
```

#### FR-V3-014: Profitability Analysis
**Priority:** MEDIUM  
**Description:** Detailed P&L statement per item/category

**Acceptance Criteria:**
- P&L dashboard showing: revenue per item, COGS (Cost of Goods Sold) per item, gross profit, gross margin %, operating expenses (labor, overhead), net profit
- Identify most/least profitable items
- Breakeven analysis
- Recommendations: "Discontinue {item} - unprofitable"

**Business Rules:**
- Enterprise plan feature
- Requires vendor to input COGS data
- Updated weekly

**Technical Notes:**
```typescript
// Profitability calculation
const calculateProfitability = async (menuItemId: string) => {
  const sales = await getTotalSales(menuItemId);
  const cogs = await getCOGS(menuItemId); // from recipe costs
  const laborCost = await getLaborCost(menuItemId); // estimated
  
  const grossProfit = sales - cogs;
  const grossMargin = (grossProfit / sales) * 100;
  const netProfit = grossProfit - laborCost;
  
  return { sales, cogs, grossProfit, grossMargin, netProfit };
};
```

---

## 5. ADMIN FEATURES

### 5.1 Platform Intelligence

#### FR-A3-001: Predictive Platform Analytics
**Priority:** HIGH  
**Description:** AI forecasts platform growth and trends

**Acceptance Criteria:**
- Dashboard showing: predicted GMV next 30 days, predicted vendor signups, predicted churn (vendors & customers), growth rate trends
- Scenario planning: "What if we increase fees by 0.5%?"
- Alerts: "Churn rate increasing - action required"

**Business Rules:**
- Super Admin only
- Updated daily
- Historical accuracy tracked

**Technical Notes:**
- Time series forecasting (Prophet, ARIMA)
- Scenario modeling:
```python
def scenario_analysis(base_model, fee_increase):
    # Estimate impact of fee change on vendor churn
    elasticity = -0.5  # 1% fee increase → 0.5% churn increase
    new_churn_rate = base_model.churn_rate * (1 + fee_increase * elasticity)
    
    # Estimate revenue impact
    new_revenue = base_model.revenue * (1 + fee_increase) * (1 - new_churn_rate)
    
    return new_revenue
```

#### FR-A3-002: Fraud Detection
**Priority:** HIGH  
**Description:** AI detects fraudulent orders, reviews, accounts

**Acceptance Criteria:**
- Fraud detection dashboard showing: flagged orders (high risk), flagged reviews (fake), flagged accounts (suspicious behavior)
- Risk score per order (low/medium/high)
- Auto-block high-risk transactions
- Manual review queue for medium risk

**Business Rules:**
- Fraud indicators: rapid order volume, mismatched billing/shipping address (delivery), stolen card (chargeback history), bot-like behavior
- Auto-block if risk score >90%
- Email alert to admin for manual review

**Technical Notes:**
- Fraud detection model (supervised learning)
```python
features = [
  'order_frequency_24h',
  'card_country_mismatch',
  'new_account_age_hours',
  'ip_reputation_score',
  'device_fingerprint_new'
]

from sklearn.ensemble import GradientBoostingClassifier
model = GradientBoostingClassifier()
model.fit(X_train, y_train)  # y = fraudulent (0/1)

fraud_probability = model.predict_proba(order_features)[:, 1]
```

#### FR-A3-003: Sentiment Analysis (Reviews & Feedback)
**Priority:** MEDIUM  
**Description:** AI analyzes sentiment of reviews and support tickets

**Acceptance Criteria:**
- Dashboard showing: sentiment breakdown (positive, neutral, negative), trending complaints, trending compliments, sentiment over time (chart)
- Alerts: "Negative sentiment spike - investigate"
- Drill-down to individual reviews

**Business Rules:**
- Aggregated data (no individual customer identification)
- Updated daily
- Multi-language support

**Technical Notes:**
- Use sentiment analysis API (Google NLP, AWS Comprehend) or custom model
```python
from transformers import pipeline

sentiment_analyzer = pipeline('sentiment-analysis')

def analyze_review_sentiment(review_text):
    result = sentiment_analyzer(review_text)[0]
    return {
        'sentiment': result['label'],  # POSITIVE, NEGATIVE, NEUTRAL
        'score': result['score']  # confidence 0-1
    }
```

---

### 5.2 Platform Operations

#### FR-A3-004: Multi-Region Support
**Priority:** HIGH  
**Description:** Platform operates in multiple countries/regions

**Acceptance Criteria:**
- Region configuration: add new region (country, currency, language, timezone, tax rules, payment gateways)
- Vendor assigned to region
- Region-specific compliance (VAT for EU, sales tax for US, etc.)
- Admin can switch between regions

**Business Rules:**
- Each region isolated (data residency compliance)
- Currency conversion for cross-border analytics
- Region-specific feature flags

**Technical Notes:**
- Multi-tenant database with region partitioning
```typescript
{
  region_code: string; // 'at', 'de', 'us', 'uk'
  region_name: string;
  currency: string; // 'EUR', 'USD', 'GBP'
  timezone: string;
  tax_rules: json; // VAT, sales tax, etc.
  payment_gateway: string; // Stripe account per region
}
```

#### FR-A3-005: Automated Compliance Checks
**Priority:** MEDIUM  
**Description:** AI monitors compliance violations

**Acceptance Criteria:**
- Daily compliance scans: VAT ID validity (Austria), business license verification, menu allergen labeling, invoice format compliance
- Violations flagged for admin review
- Warnings sent to vendors: "Your VAT ID will expire in 30 days"
- Auto-suspend if critical violation unresolved

**Business Rules:**
- Critical violations: invalid VAT ID, expired license
- Non-critical warnings: missing allergen labels
- Grace period before suspension: 7 days

**Technical Notes:**
- Automated VAT ID verification via EU VIES API
```typescript
const verifyVATID = async (vatId: string) => {
  const response = await fetch(`https://ec.europa.eu/taxation_customs/vies/checkVatService`, {
    method: 'POST',
    body: vatId
  });
  
  return response.valid;
};
```

#### FR-A3-006: Platform API (Public)
**Priority:** MEDIUM  
**Description:** Public API for third-party developers

**Acceptance Criteria:**
- Developer portal with API docs (OpenAPI)
- API key management (create, revoke)
- Endpoints: search restaurants, get menu, create order (on behalf of customer with OAuth)
- Rate limiting: 10,000 requests/day (paid plans for more)
- Webhook support

**Business Rules:**
- API access requires registration + approval
- Freemium model: 10k req/day free, paid plans for higher limits
- Use cases: food aggregators, chatbots, voice assistants

**Technical Notes:**
- OAuth 2.0 for customer authorization
- GraphQL option for flexible queries

---

### 5.3 Financial Management

#### FR-A3-007: Automated Payouts to Vendors
**Priority:** HIGH  
**Description:** Scheduled automated payouts via Stripe Connect

**Acceptance Criteria:**
- Payout schedule: daily, weekly, bi-weekly, monthly (configurable per vendor)
- Payout includes: total orders - platform fees - refunds
- Vendor sees upcoming payout amount in dashboard
- Admin dashboard shows: pending payouts, processed payouts, failed payouts (with retry)

**Business Rules:**
- Default payout schedule: weekly
- Min payout amount: €50 (if below, rolls over to next period)
- Failed payouts retried 3 times, then manual review

**Technical Notes:**
- Stripe Connect handles payouts automatically
```typescript
const processPayouts = async () => {
  const vendorsDue = await getVendorsDueForPayout();
  
  for (const vendor of vendorsDue) {
    const amount = await calculatePayoutAmount(vendor.id);
    
    if (amount >= 50) {
      await stripe.payouts.create({
        amount: amount * 100, // cents
        currency: 'eur',
        destination: vendor.stripe_account_id
      });
      
      await logPayout(vendor.id, amount);
    }
  }
};
```

#### FR-A3-008: Revenue Recognition & Accounting
**Priority:** MEDIUM  
**Description:** Automated financial reporting for platform

**Acceptance Criteria:**
- Monthly financial reports: total GMV, platform revenue (fees + subscriptions), COGS (operational costs), EBITDA, net profit
- Accrual accounting (revenue recognized when order completed)
- Export to accounting software (QuickBooks, Xero)
- Audit trail for all transactions

**Business Rules:**
- Compliant with IFRS/GAAP accounting standards
- Monthly close process (automated)
- Deferred revenue for annual subscriptions

**Technical Notes:**
- Integration with accounting APIs
- Revenue recognition on order completion:
```typescript
const recognizeRevenue = async (orderId: string) => {
  const order = await getOrder(orderId);
  
  if (order.status === 'completed') {
    const platformFee = order.total * 0.02 + 0.30;
    
    await recordRevenue({
      order_id: orderId,
      revenue_type: 'transaction_fee',
      amount: platformFee,
      recognized_at: new Date(),
      period: getAccountingPeriod(new Date())
    });
  }
};
```

#### FR-A3-009: Tax Reporting (Multi-Jurisdiction)
**Priority:** MEDIUM  
**Description:** Automated tax reporting for different regions

**Acceptance Criteria:**
- Tax reports per region: Austria (UStVA - VAT return), Germany (USt), US (sales tax per state)
- Export in required format for tax authorities
- Quarterly/annual summaries
- VAT reconciliation (input vs output VAT)

**Business Rules:**
- Tax rules vary by region (configured per region)
- Reports generated automatically at period end
- Admin reviews before submission

**Technical Notes:**
- Tax calculation per region rules
```typescript
const generateVATReport = async (regionCode: string, period: string) => {
  const orders = await getOrdersForPeriod(regionCode, period);
  
  const vatByRate = orders.reduce((acc, order) => {
    for (const item of order.items) {
      const rate = item.vat_rate;
      if (!acc[rate]) acc[rate] = { net: 0, vat: 0, gross: 0 };
      
      const net = item.price / (1 + rate/100);
      const vat = item.price - net;
      
      acc[rate].net += net * item.quantity;
      acc[rate].vat += vat * item.quantity;
      acc[rate].gross += item.price * item.quantity;
    }
    return acc;
  }, {});
  
  return vatByRate;
};
```

#### FR-A3-010: Chargeback Management
**Priority:** MEDIUM  
**Description:** Handle disputed transactions and chargebacks

**Acceptance Criteria:**
- Chargeback notifications from Stripe
- Admin dashboard shows: open disputes, evidence required, resolution status
- Evidence collection: order details, customer communication, delivery proof
- Auto-submit evidence to Stripe
- Track chargeback rate per vendor (flag if >1%)

**Business Rules:**
- Chargeback fee: €15 (charged to vendor if lost)
- Vendors with high chargeback rate suspended
- Evidence must be submitted within 7 days

**Technical Notes:**
- Stripe webhook: `charge.dispute.created`
```typescript
const handleChargeback = async (dispute: Stripe.Dispute) => {
  const order = await getOrderByPaymentIntent(dispute.payment_intent);
  
  // Collect evidence
  const evidence = {
    customer_name: order.customer_name,
    customer_email_address: order.customer_email,
    billing_address: order.billing_address,
    receipt: order.receipt_url,
    customer_signature: order.delivery_signature // if delivery
  };
  
  // Submit evidence
  await stripe.disputes.update(dispute.id, { evidence });
  
  // Notify vendor
  await notifyVendor(order.vendor_id, 'chargeback', dispute);
};
```

---

## 6. PLATFORM PAGES

### 6.1 Developer & Partner Pages

#### FR-P3-001: Developer Portal
**Priority:** MEDIUM  
**Description:** Portal for third-party developers to integrate

**Acceptance Criteria:**
- API documentation (interactive, try-it-now examples)
- SDKs: JavaScript, Python, Ruby
- Tutorials and guides
- API key management
- Sandbox environment
- Developer community forum

**Business Rules:**
- Free tier: 10k API calls/day
- Paid tiers for higher volume
- Approval required for production access

**Technical Notes:**
- Use Swagger/OpenAPI for docs
- Sandbox uses separate database

#### FR-P3-002: Partner Integration Marketplace
**Priority:** LOW  
**Description:** Marketplace of third-party integrations

**Acceptance Criteria:**
- Directory of integrations: POS systems, accounting software, delivery partners, marketing tools
- Each listing shows: description, screenshots, pricing, install button
- One-click install (OAuth flow)
- Vendor can manage installed integrations

**Business Rules:**
- Integrations must be approved by TAVLO
- Revenue share: TAVLO takes 20% of integration fees

**Technical Notes:**
- OAuth 2.0 for integration authorization
- Webhook relay for integration events

#### FR-P3-003: Affiliate Dashboard
**Priority:** LOW  
**Description:** Dashboard for affiliates promoting TAVLO

**Acceptance Criteria:**
- Affiliate signup form
- Unique referral link
- Track: clicks, signups, conversions
- Commission calculator: X% of first 12 months subscription revenue
- Payout requests

**Business Rules:**
- Commission: 20% of first year subscription revenue
- Min payout: €100
- Payment via PayPal or bank transfer

**Technical Notes:**
- Track referrals via cookies + URL params
- Store in `affiliate_referrals`

---

### 6.2 Enterprise & B2B Pages

#### FR-P3-004: Enterprise Solutions Page
**Priority:** MEDIUM  
**Description:** Marketing page for enterprise clients

**Acceptance Criteria:**
- Features: multi-location, white-label, API access, dedicated support
- Case studies from existing enterprise clients
- Contact form for sales inquiries
- Demo booking calendar

**Business Rules:**
- Leads routed to sales team
- Custom pricing on request

**Technical Notes:**
- Static marketing page
- Form submission creates lead in CRM

#### FR-P3-005: Restaurant Chain Solutions
**Priority:** LOW  
**Description:** Dedicated page for restaurant chains

**Acceptance Criteria:**
- Value proposition for chains (consistency, central control, franchisee management)
- ROI calculator
- Testimonials
- Schedule demo button

**Business Rules:**
- Target: chains with 5+ locations
- Custom pricing

**Technical Notes:**
- Interactive ROI calculator

---

### 6.3 Public Engagement

#### FR-P3-006: Community Guidelines
**Priority:** LOW  
**Description:** Guidelines for customer and vendor behavior

**Acceptance Criteria:**
- Rules for reviews (no hate speech, spam, etc.)
- Rules for vendors (no price manipulation, fake reviews)
- Reporting process
- Enforcement actions (warning, suspension, ban)

**Business Rules:**
- Violations tracked per user
- 3 strikes = suspension

**Technical Notes:**
- Static content page

#### FR-P3-007: Sustainability Report
**Priority:** LOW  
**Description:** Platform's environmental impact report

**Acceptance Criteria:**
- Metrics: carbon footprint, eco-friendly packaging adoption, food waste reduction
- Goals for next year
- Vendor sustainability leaderboard

**Business Rules:**
- Published annually
- Highlights vendors with sustainable practices

**Technical Notes:**
- Data collected from vendor surveys

#### FR-P3-008: Investor Relations
**Priority:** LOW  
**Description:** Portal for current and potential investors

**Acceptance Criteria:**
- Company overview
- Financial highlights (if public)
- News and press releases
- Contact investor relations

**Business Rules:**
- Access restricted to accredited investors
- Non-public information requires NDA

**Technical Notes:**
- Gated content (registration required)

#### FR-P3-009: Public API Status Page
**Priority:** MEDIUM  
**Description:** Real-time status of platform APIs and services

**Acceptance Criteria:**
- Current status: operational, degraded, outage
- Historical uptime (99.9%)
- Incident history
- Subscribe to status updates

**Business Rules:**
- Updated in real-time
- Incidents posted within 5 minutes

**Technical Notes:**
- Use StatusPage.io or custom
- Auto-updated via monitoring tools

---

## END OF PHASE 3 SRS

**Total Features:** 41  
**Status:** Planned  
**Overall Phase 1+2+3:** 228 features

---

**Last Updated:** December 26, 2024  
**Document Owner:** TAVLO Product Team  
**Version:** 4.0

---

## APPENDIX: COMPLETE FEATURE SUMMARY

### Total Feature Breakdown
- **Phase 1 (Launch):** 95 features ✅ 95% Complete
- **Phase 2 (Expansion):** 92 features ⏳ 60% Complete
- **Phase 3 (Full Platform):** 41 features 🚧 Planned
- **TOTAL:** 228 features

### Timeline
- **Phase 1:** Weeks 1-12
- **Phase 2:** Weeks 13-20
- **Phase 3:** Weeks 21-36
- **Total:** 36 weeks (9 months)

### Technology Stack
- Frontend: React, TypeScript, Tailwind CSS, shadcn/ui
- Backend: Supabase, Deno, Hono.js, Stripe
- AI/ML: OpenAI GPT-4, TensorFlow, scikit-learn
- Infrastructure: Vercel, Supabase Cloud, AWS

---

**🚀 TAVLO: From QR Code to Full Platform in 36 Weeks**
