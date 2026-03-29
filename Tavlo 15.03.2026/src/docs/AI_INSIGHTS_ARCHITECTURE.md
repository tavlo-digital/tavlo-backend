# AI Platform Insights Architecture

## Overview

The AI Platform Insights module provides subscription health monitoring and revenue intelligence for Tavlo's admin panel. It strictly focuses on **platform data only** and never analyzes restaurant operations.

## Architecture

```
┌─────────────────────────────────────────┐
│  AIAdminInsights.tsx (Frontend)         │
│  - Displays AI insights                 │
│  - 4 tabs: Health, Risk, Revenue, Alerts│
│  - Loads data on mount                   │
└────────────────┬────────────────────────┘
                 │
                 │ fetches from
                 ▼
┌─────────────────────────────────────────┐
│  aiInsightsService.ts (Service Layer)   │
│  - Fetch functions for each data type   │
│  - Falls back to mock data              │
│  - Would call backend API in production │
└────────────────┬────────────────────────┘
                 │
                 │ calls (in production)
                 ▼
┌─────────────────────────────────────────┐
│  Backend API (Not yet implemented)      │
│  - /ai/subscription-risks               │
│  - /ai/revenue-opportunities            │
│  - /ai/system-alerts                    │
│  - /ai/platform-health                  │
└─────────────────────────────────────────┘
```

## Current State (Prototype)

### Frontend Component
**Location:** `/components/admin/AIAdminInsights.tsx`

- Uses React hooks (`useState`, `useEffect`)
- Fetches data from service layer on mount
- Shows loading state while data loads
- Displays 4 tabs with different insight types

### Service Layer
**Location:** `/services/aiInsightsService.ts`

**Current behavior:**
- Attempts to fetch from backend API
- Falls back to mock data when API unavailable
- Mock data provides realistic examples

**Mock data includes:**
- 3 subscription risk examples
- 3 revenue opportunity examples
- 4 system alert examples
- Platform health metrics

## Data Sources (Platform Only)

The AI analyzes ONLY these Tavlo platform data points:

### Subscription Health
- ✅ Subscription status (active, trial, suspended)
- ✅ Payment history (success, failed, overdue)
- ✅ Plan type and limits
- ✅ Feature usage percentage

### Platform Engagement
- ✅ Login frequency
- ✅ Feature adoption rate
- ✅ Platform activity logs
- ✅ Time on platform

### Support & Operations
- ✅ Support ticket volume
- ✅ Response times
- ✅ Issue categories

### System Metrics
- ✅ API performance
- ✅ Webhook status
- ✅ Payment provider health
- ✅ Account anomalies

### ❌ NEVER ANALYZED
- ❌ Restaurant menus
- ❌ Order volumes
- ❌ Pricing strategies
- ❌ Customer ratings
- ❌ Tips/gratuities
- ❌ Restaurant performance

## Production Implementation

To implement real AI insights in production:

### Step 1: Build AI Pipeline

Choose one or more approaches:

#### A. Rules-Based System (Simple)
```typescript
// Example: Calculate subscription risk score
function calculateRiskScore(vendor: Vendor): number {
  let score = 0;
  
  // Payment factors (40 points)
  if (vendor.paymentOverdueDays > 30) score += 40;
  else if (vendor.paymentOverdueDays > 14) score += 25;
  else if (vendor.paymentOverdueDays > 7) score += 15;
  
  if (vendor.failedPayments > 3) score += 20;
  else if (vendor.failedPayments > 1) score += 10;
  
  // Engagement factors (30 points)
  if (vendor.daysSinceLogin > 14) score += 30;
  else if (vendor.daysSinceLogin > 7) score += 15;
  
  if (vendor.featureUsagePercent < 20) score += 20;
  else if (vendor.featureUsagePercent < 40) score += 10;
  
  // Support factors (20 points)
  if (vendor.supportTicketsLastMonth > 5) score += 20;
  else if (vendor.supportTicketsLastMonth > 3) score += 10;
  
  // Trial factors (10 points)
  if (vendor.daysUntilTrialEnds < 5 && vendor.daysUntilTrialEnds > 0) {
    score += 10;
  }
  
  return Math.min(score, 100);
}
```

#### B. Machine Learning Model (Advanced)
- Train churn prediction model on historical data
- Features: payment history, engagement metrics, support volume
- Use libraries like TensorFlow.js or call external ML service
- Update predictions daily/hourly

#### C. Third-Party Services
- **Stripe Radar**: Fraud detection
- **ChartMogul**: Subscription analytics
- **ProfitWell**: Churn prediction
- **Segment**: Customer data platform

### Step 2: Store Insights in Database

Use Supabase KV store with structured keys:

```typescript
// Example key structure
await kv.set(`ai:subscription-risk:${vendorId}`, {
  vendorId,
  vendorName,
  riskLevel: 'high',
  riskScore: 78,
  factors: [...],
  calculatedAt: new Date().toISOString()
});

await kv.set(`ai:platform-health:latest`, {
  activeSubscriptions: 347,
  churnRisk: 2.8,
  // ...
  updatedAt: new Date().toISOString()
});
```

### Step 3: Create Backend API Routes

Add to `/supabase/functions/server/index.tsx`:

```typescript
import { Hono } from 'npm:hono';
import * as kv from './kv_store.tsx';

app.get('/make-server-1dccd8d3/ai/subscription-risks', async (c) => {
  try {
    // Fetch all subscription risk entries
    const risks = await kv.getByPrefix('ai:subscription-risk:');
    
    // Sort by risk score (highest first)
    const sorted = risks.sort((a, b) => b.riskScore - a.riskScore);
    
    return c.json(sorted);
  } catch (error) {
    console.error('Error fetching subscription risks:', error);
    return c.json({ error: 'Failed to fetch insights' }, 500);
  }
});

app.get('/make-server-1dccd8d3/ai/revenue-opportunities', async (c) => {
  try {
    const opportunities = await kv.getByPrefix('ai:revenue-opportunity:');
    
    // Sort by estimated MRR (highest first)
    const sorted = opportunities.sort((a, b) => b.estimatedMRR - a.estimatedMRR);
    
    return c.json(sorted);
  } catch (error) {
    console.error('Error fetching revenue opportunities:', error);
    return c.json({ error: 'Failed to fetch insights' }, 500);
  }
});

app.get('/make-server-1dccd8d3/ai/system-alerts', async (c) => {
  try {
    const alerts = await kv.getByPrefix('ai:system-alert:');
    
    // Sort by severity and recency
    const sorted = alerts.sort((a, b) => {
      const severityOrder = { high: 3, medium: 2, low: 1 };
      return severityOrder[b.severity] - severityOrder[a.severity];
    });
    
    return c.json(sorted);
  } catch (error) {
    console.error('Error fetching system alerts:', error);
    return c.json({ error: 'Failed to fetch insights' }, 500);
  }
});

app.get('/make-server-1dccd8d3/ai/platform-health', async (c) => {
  try {
    const health = await kv.get('ai:platform-health:latest');
    
    if (!health) {
      return c.json({ error: 'Health metrics not available' }, 404);
    }
    
    return c.json(health);
  } catch (error) {
    console.error('Error fetching platform health:', error);
    return c.json({ error: 'Failed to fetch health metrics' }, 500);
  }
});
```

### Step 4: Schedule AI Calculations

Create a scheduled function to recalculate insights:

```typescript
// Run every hour
app.post('/make-server-1dccd8d3/ai/recalculate', async (c) => {
  // Admin authentication required
  const token = c.req.header('Authorization')?.split(' ')[1];
  // ... verify admin token
  
  try {
    // Fetch all vendors
    const vendors = await kv.getByPrefix('vendor:');
    
    // Calculate subscription risks
    for (const vendor of vendors) {
      const risk = await calculateVendorRisk(vendor);
      await kv.set(`ai:subscription-risk:${vendor.id}`, risk);
    }
    
    // Calculate revenue opportunities
    const opportunities = await calculateRevenueOpportunities(vendors);
    for (const opp of opportunities) {
      await kv.set(`ai:revenue-opportunity:${opp.vendorId}`, opp);
    }
    
    // Detect system alerts
    const alerts = await detectSystemAlerts(vendors);
    for (const alert of alerts) {
      await kv.set(`ai:system-alert:${alert.id}`, alert);
    }
    
    // Calculate platform health
    const health = await calculatePlatformHealth(vendors);
    await kv.set('ai:platform-health:latest', health);
    
    return c.json({ success: true, timestamp: new Date().toISOString() });
  } catch (error) {
    console.error('Error recalculating AI insights:', error);
    return c.json({ error: 'Recalculation failed' }, 500);
  }
});
```

### Step 5: Set Up Monitoring

Add logging and error tracking:

```typescript
// Log all AI calculations
console.log('[AI] Subscription risk calculated', {
  vendorId,
  riskLevel,
  riskScore,
  factors: factors.map(f => f.label)
});

// Track accuracy over time
await kv.set(`ai:prediction-log:${vendorId}:${date}`, {
  prediction: 'churn',
  confidence: 85,
  actual: null, // Updated later when outcome is known
});
```

## Compliance & Legal

### AI Disclaimer
All AI insights pages display:
> "AI insights are advisory and based on Tavlo platform data only. Tavlo does not analyze or manage restaurant operations, menus, pricing, tips, or customer behavior. All suggestions relate to subscription health, platform usage, and revenue protection."

### Data Transparency
Every AI suggestion shows:
- **Data Sources**: What data was analyzed
- **Confidence Level**: Percentage (e.g., 85%)
- **Why Flagged**: Explanation of the reasoning

### Audit Trail
All AI-triggered actions should be logged:
```typescript
await kv.set(`audit:ai-action:${actionId}`, {
  triggeredBy: 'ai-insights',
  suggestion: 'contact-vendor-churn-risk',
  vendorId,
  adminUserId,
  actionTaken: 'email-sent',
  timestamp: new Date().toISOString()
});
```

## Testing Strategy

### Unit Tests
- Test risk calculation logic
- Test opportunity detection
- Test alert thresholds

### Integration Tests
- Test API endpoints
- Test KV store operations
- Test scheduled recalculations

### Accuracy Monitoring
- Track churn predictions vs actual churn
- Track upgrade suggestions vs actual upgrades
- Adjust thresholds based on results

## Performance Considerations

### Caching
- Cache platform health metrics (refresh every hour)
- Cache individual risk scores (refresh daily)
- Cache alerts (refresh every 30 minutes)

### Pagination
- Limit results to top N risks/opportunities
- Add pagination for large datasets

### Async Processing
- Calculate insights in background jobs
- Don't block API requests with heavy computations

## Future Enhancements

1. **Predictive Accuracy Tracking**: Compare AI predictions to actual outcomes
2. **Custom Risk Thresholds**: Allow admins to adjust sensitivity
3. **Webhook Notifications**: Alert admins when high-risk vendors detected
4. **Export Reports**: Download AI insights as CSV/PDF
5. **Historical Trends**: Show risk score changes over time
6. **A/B Testing**: Test different intervention strategies
