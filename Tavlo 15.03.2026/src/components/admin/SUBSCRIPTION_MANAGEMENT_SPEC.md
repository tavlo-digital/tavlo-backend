# Enhanced Subscription Management - Feature-Based Plans Specification

## Overview

The **Enhanced Subscription Management** system supports feature-based plan configuration with hierarchical inheritance, monthly/yearly pricing, and tight integration with Vendor Management.

**Core Philosophy:**
- Plans are hierarchical (Standard includes Basic, Premium includes Standard)
- Features defined once globally (single source of truth)
- Plans reference features (no duplication)
- Monthly + yearly pricing (vendor chooses billing cycle)
- Vendor list is single source of truth for subscribers

---

## A. SUBSCRIPTION MANAGEMENT — PLANS OVERVIEW PAGE

### A1. Enhanced Plan Cards

Each plan card displays:

#### Pricing Display
```
Basic

€99 /month
€950 /year
Save €238/year

260 active subscriptions
MRR: €25,740
```

**Structure:**
- **Monthly price** - Primary pricing (large, bold)
- **Yearly price** - Secondary pricing (green, shows savings)
- **Savings calculation** - (Monthly × 12) - Yearly
- **Active subscriptions count** - Total vendors on this plan
- **MRR contribution** - Monthly Recurring Revenue from this plan

#### Feature Summary (Clickable)
```
┌─────────────────────────────────────────┐
│ Includes 9 features                     │
│ Click to view and edit features     →  │
└─────────────────────────────────────────┘

✓ Basic Menu Management
✓ Menu Categories
✓ Menu Item Images
+ 6 more features
```

**Behavior:**
- Blue background (clickable affordance)
- Shows total feature count
- Displays first 3 features
- "Click to view and edit features" text
- ExternalLink icon
- **Click → Opens Edit Plan modal with full feature selector**

#### Most Popular Badge
```
┌─────────────────┐
│ ⭐ Most Popular │  (Purple badge, top-center)
└─────────────────┘
```

**Behavior:**
- Manual toggle (checkbox in Edit Plan modal)
- Only one plan should have this badge
- Purple border on card when popular

#### Action Buttons

**1. Edit Plan**
- Opens Edit Plan modal
- Shows feature selector with hierarchy enforcement

**2. View Subscribers** (Navigation Contract)
- **Destination:** Admin → Vendors page
- **Filter applied:** `plan = [Plan Name]`
- **Toast shown:** "Navigating to Vendors: Filter applied: Premium subscribers"
- **Icon:** ExternalLink (indicates navigation)
- **Console log:** Navigation contract details

---

### A2. "View Subscribers" Navigation Contract (Critical)

**Click Flow:**
```
Admin → Subscriptions → Premium Plan → "View Subscribers"
↓
Admin → Vendors (with filter: plan=Premium applied)
```

**Implementation:**
```typescript
const handleViewSubscribers = (planName: string) => {
  console.log('NAVIGATION CONTRACT: Subscriptions → Vendors', {
    destination: 'Admin → Vendors',
    filter: { plan: planName },
    source: 'Subscription Management'
  });

  // Navigate to vendors page with filter
  navigate('/admin/vendors', { 
    state: { filters: { plan: planName } } 
  });

  toast.info('Navigating to Vendors', {
    description: `Filter applied: ${planName} subscribers`
  });
};
```

**Why This Matters:**
- ✅ Single source of truth (Vendor list)
- ✅ No duplicate subscriber management
- ✅ Avoids data sync issues
- ✅ Vendor Detail → Subscription tab for vendor-specific actions

**No New List Page:**
Subscriber lists belong in Vendor Management, not Subscription Management.

---

## B. EDIT PLAN MODAL — FEATURE-BASED CONFIGURATION

### B1. Pricing Structure (Required Change)

**Old (Static):**
```
Price: €299
Billing Cycle: [Monthly ▼]
```

**New (Dual Pricing):**
```
Monthly Price (€):  [99     ]
Yearly Price (€):   [950    ]

ℹ️ Yearly billing typically offers a discount (~20%)
```

**Billing cycle selector REMOVED.**
Billing cycle is chosen by vendor during subscription signup, not defined by plan.

**Validation:**
- Both fields required
- Yearly should be < Monthly × 12 (discount expected)
- Warning if yearly ≥ monthly × 12: "No discount offered"

---

### B2. Feature Assignment (Critical)

**Old (Free-Text):**
```
Features:
[Textarea: Enter one feature per line]
```

**New (Feature Selector UI):**

#### Global Feature Definitions
```typescript
interface TavloFeature {
  id: string;
  name: string;
  description: string;
  category: 'menu-content' | 'ordering-payments' | 'analytics' | 
            'customer-engagement' | 'support-admin' | 'integrations';
  dependencies?: string[]; // Optional feature dependencies
}
```

**Single source of truth:**
```typescript
const TAVLO_FEATURES: TavloFeature[] = [
  {
    id: 'menu-basic',
    name: 'Basic Menu Management',
    description: 'Create and manage menu items',
    category: 'menu-content'
  },
  {
    id: 'analytics-realtime',
    name: 'Real-Time Dashboard',
    description: 'Live order monitoring',
    category: 'analytics',
    dependencies: ['analytics-advanced'] // Requires Advanced Analytics
  },
  // ... 28 total features
];
```

#### Feature Selector UI Structure

**Grouped by Category:**
```
┌── Menu & Content ────────────────────────────┐
│ ☑ Basic Menu Management                     │
│   Create and manage menu items              │
│                                              │
│ ☑ Menu Categories                           │
│   Organize items by category                │
│   🔒 Included from basic                    │
│                                              │
│ ☐ Unlimited Menu Items                      │
│   No limit on menu items                    │
└──────────────────────────────────────────────┘

┌── Ordering & Payments ───────────────────────┐
│ ☑ QR Code Ordering                          │
│   Customer QR-based ordering                │
│   🔒 Included from basic                    │
│                                              │
│ ☑ Table Management                          │
│   Assign orders to tables                   │
│   Requires: QR Code Ordering                │
└──────────────────────────────────────────────┘
```

**Each Feature Row:**
- ☑/☐ Checkbox (disabled if inherited)
- **Feature name** (bold)
- Feature description (gray, smaller)
- 🔒 "Included from [base plan]" badge (if inherited)
- Dependency notice (if applicable): "Requires: Analytics Core"

---

### B3. Hierarchy Enforcement Rules (MUST BE ENFORCED)

#### Visual Hierarchy Notice (Top of Modal)
```
ℹ️ Hierarchical Plan

This plan inherits all features from standard. 
Inherited features are locked and cannot be removed.
```

**Only shown if `plan.basePlan` exists.**

#### Plan Hierarchy:

**1. Basic Plan**
- No base plan
- Admin can select any features
- These become the foundation for Standard

**2. Standard Plan**
- Base plan: `basic`
- All Basic features **auto-selected** and **locked** (disabled checkbox)
- Additional features can be added
- Cannot remove Basic features

**3. Premium Plan**
- Base plan: `standard`
- All Standard features **auto-selected** and **locked**
- All Basic features **inherited** and **locked**
- Additional premium-only features can be added
- Cannot remove Standard or Basic features

#### Enforcement Logic

**When editing Standard plan:**
```typescript
const basePlanFeatures = allPlans.find(p => p.name === 'Basic').featureIds;

// These features show as:
// ☑ QR Code Ordering  🔒 Included from basic
// (Checkbox disabled, cannot uncheck)
```

**When admin tries to remove inherited feature:**
```typescript
if (isFeatureInherited(featureId)) {
  toast.error('Cannot remove inherited feature', {
    description: 'Included from basic plan'
  });
  return; // Block action
}
```

**Visual Feedback:**
- Inherited features: Gray background
- Checkbox: Disabled state
- 🔒 Lock icon + "Included from [plan]" badge
- Tooltip on hover: "This feature is inherited from the Basic plan and cannot be removed"

---

### B4. Feature Dependency Guardrails

**Auto-Select Dependencies:**
```typescript
// Example: Admin selects "Real-Time Dashboard"
// This feature requires "Advanced Analytics"

if (feature.dependencies && !allDepsSelected) {
  toast.info('Auto-selecting dependencies', {
    description: 'Requires: Advanced Analytics'
  });
  
  // Auto-add dependency + selected feature
  setFeatureIds([...featureIds, ...dependencies, featureId]);
}
```

**Dependency Display:**
```
☐ Real-Time Dashboard
  Live order monitoring
  Requires: Advanced Analytics
```

**Prevents:**
- Selecting features without prerequisites
- Breaking feature functionality
- Configuration errors

---

### B5. Feature Count Display

**Dynamic counter (top-right of modal):**
```
Features
9 features selected
```

Updates in real-time as features are toggled.

---

## C. PLAN CREATION FLOW

**+ Create Plan Button** (top-right)

### Create Plan Modal Fields:

**1. Plan Name**
```
Plan Name: [Custom Pro     ]
```

**2. Base Plan (Optional)**
```
Base Plan: [Choose base plan ▼]
Options:
- None (start from scratch)
- Basic
- Standard
```

**Behavior:**
- If "Basic" selected → Auto-selects all Basic features (locked)
- If "Standard" selected → Auto-selects all Standard + Basic features (locked)
- If "None" → Empty feature selector

**3. Pricing**
```
Monthly Price (€): [399    ]
Yearly Price (€):  [3830   ]
```

**4. Feature Selector**
Same UI as Edit Plan, but respects base plan selection.

**5. Most Popular**
```
☐ Mark as "Most Popular"
```

---

## D. SUBSCRIPTION METRICS — NO CHANGE

**Keep existing KPI cards:**

### Card 1: Total MRR
```
€204,911
+15.2% vs last month
```

### Card 2: Active Subscriptions
```
1,089
Across 3 plans
```

### Card 3: Churn Rate
```
2.3%
-0.5% improvement
```

### Card 4: Overdue
```
€2,847
4 subscriptions
```

**No charts, no vendor-level actions here.**

---

## E. DATA & NAVIGATION INTEGRATION

### E1. Vendor Management Integration

**From Vendor List:**
```
┌─────────────────────────────────────────────┐
│ Vendor Name    │ Plan      │ Status         │
├─────────────────────────────────────────────┤
│ Bella Italia   │ Premium → │ Active         │
└─────────────────────────────────────────────┘
```

**Plan name is clickable:**
- **Click → Vendor Detail → Subscription tab**

**From Subscription Management:**
```
View Subscribers button → Admin → Vendors (filtered)
```

**Bidirectional Navigation:**
- Subscriptions → Vendors (via "View Subscribers")
- Vendors → Vendor Detail → Subscription tab (vendor-specific actions)

---

### E2. Audit & Safety

**All plan changes logged:**
```json
{
  "action": "PLAN_EDITED",
  "planId": "plan_premium",
  "admin": "ADM-001",
  "timestamp": "2025-01-06T16:00:00Z",
  "changes": {
    "monthlyPrice": { "from": 299, "to": 319 },
    "yearlyPrice": { "from": 2870, "to": 3060 },
    "featuresAdded": ["pos-integration"],
    "featuresRemoved": []
  }
}
```

**Safety Confirmation:**
```
⚠️ Active Subscription Warning

This plan has 142 active subscriptions. 
Changing features may affect vendor access.

Changes will apply to:
- New subscriptions immediately
- Existing subscriptions at next billing cycle

[ Cancel ]  [ Confirm Change ]
```

**Prevents:**
- Silent breaking changes
- Immediate disruption to active vendors
- Unaudited plan modifications

---

## F. CONSTRAINTS (DO NOT VIOLATE)

### ❌ Do NOT:

1. **Duplicate feature definitions per plan**
   - Features defined once in `TAVLO_FEATURES`
   - Plans reference feature IDs only

2. **Allow removing inherited features**
   - UI enforces hierarchy
   - Checkboxes disabled for inherited features

3. **Mix billing logic into Vendor tab**
   - Vendor tab: operational status
   - Subscription tab: billing/features
   - Separate concerns

4. **Allow per-vendor pricing here**
   - This page: plan-level pricing
   - Vendor-specific discounts: Vendor Detail → Billing

5. **Allow silent plan changes**
   - All changes logged
   - Confirmation modal for plans with active subscribers

---

## G. FEATURE CATEGORIES & COMPLETE LIST

### Menu & Content (5 features)
1. ✅ Basic Menu Management
2. ✅ Menu Categories
3. ✅ Menu Item Images
4. ✅ Unlimited Menu Items
5. ✅ Item Modifiers & Options

### Ordering & Payments (6 features)
1. ✅ QR Code Ordering
2. ✅ Table Management
3. ✅ Card Payments
4. ✅ Cash Payment Option
5. ✅ Split Bill
6. ✅ Tipping

### Analytics (4 features)
1. ✅ Basic Analytics
2. ✅ Advanced Analytics
3. ✅ Export Reports
4. ✅ Real-Time Dashboard

### Customer Engagement (4 features)
1. ✅ Multi-Language Support
2. ✅ Loyalty Program
3. ✅ Customer Reviews
4. ✅ Email Marketing

### Support & Admin (4 features)
1. ✅ Email Support
2. ✅ Priority Support
3. ✅ Dedicated Account Manager
4. ✅ Multi-User Access

### Integrations (5 features)
1. ✅ API Access
2. ✅ Webhooks
3. ✅ White-Label Options
4. ✅ POS Integration

**Total: 28 features**

---

## H. TYPICAL PLAN CONFIGURATIONS

### Basic Plan (9 features)
```
Menu & Content:
- Basic Menu Management
- Menu Categories
- Menu Item Images

Ordering & Payments:
- QR Code Ordering
- Table Management
- Card Payments
- Cash Payment Option

Analytics:
- Basic Analytics

Support:
- Email Support
```

**Pricing:**
- Monthly: €99
- Yearly: €950 (save €238/year)

---

### Standard Plan (18 features = 9 Basic + 9 additional)
```
Inherits all Basic features +

Menu & Content:
- Item Modifiers & Options

Ordering & Payments:
- Tipping

Analytics:
- Advanced Analytics
- Export Reports

Customer Engagement:
- Multi-Language Support
- Loyalty Program
- Customer Reviews

Support:
- Priority Support
- Multi-User Access
```

**Pricing:**
- Monthly: €199
- Yearly: €1,910 (save €478/year)

---

### Premium Plan (28 features = 18 Standard + 10 additional)
```
Inherits all Standard features +

Menu & Content:
- Unlimited Menu Items

Ordering & Payments:
- Split Bill

Analytics:
- Real-Time Dashboard

Customer Engagement:
- Email Marketing

Support:
- Dedicated Account Manager

Integrations:
- API Access
- Webhooks
- White-Label Options
- POS Integration
```

**Pricing:**
- Monthly: €299
- Yearly: €2,870 (save €718/year)

---

## I. PRODUCTION CHECKLIST

### ✅ Plan Cards
- [ ] Display monthly + yearly pricing
- [ ] Show savings calculation
- [ ] Active subscriptions count
- [ ] MRR contribution
- [ ] Feature count summary (clickable)
- [ ] "View Subscribers" navigates to Vendors with filter
- [ ] "Most Popular" badge (manual toggle)

### ✅ Edit Plan Modal
- [ ] Monthly + yearly price fields
- [ ] No billing cycle selector
- [ ] Feature selector grouped by category
- [ ] Inherited features locked and labeled
- [ ] Dependency auto-selection
- [ ] Feature count display
- [ ] Hierarchy notice (if applicable)
- [ ] Save triggers audit log

### ✅ Feature Selector
- [ ] 28 features defined in `TAVLO_FEATURES`
- [ ] 6 categories
- [ ] Checkbox + name + description
- [ ] Lock icon for inherited features
- [ ] Dependency notices
- [ ] Real-time count update

### ✅ Hierarchy Enforcement
- [ ] Basic features locked in Standard
- [ ] Standard + Basic locked in Premium
- [ ] Cannot remove inherited features
- [ ] Toast error if attempt to remove
- [ ] Visual disabled state

### ✅ Navigation
- [ ] "View Subscribers" → Vendors with plan filter
- [ ] Console log navigation contract
- [ ] Toast notification with filter description
- [ ] ExternalLink icon on button

### ✅ Audit
- [ ] Plan edits logged with before/after
- [ ] Admin ID + timestamp
- [ ] Feature changes tracked
- [ ] Price changes tracked

---

## J. NAVIGATION CONTRACT SUMMARY

| Action | Source | Destination | State/Filters |
|--------|--------|-------------|---------------|
| View Subscribers | Subscriptions → Plan Card | Vendors List | `{ plan: "Premium" }` |
| Edit Plan | Subscriptions → Plan Card | Edit Plan Modal | (stay in page) |
| Plan Name (in Vendor List) | Vendors → Row | Vendor Detail → Subscription Tab | `{ vendorId, tab: 'subscription' }` |
| Active Subscriptions Row | Subscriptions → Active Tab | Vendor Detail → Subscription Tab | `{ vendorId }` |

---

## K. LANGUAGE & WORDING RULES

### ✅ DO Use:
- "Includes X features" (plan cards)
- "Click to view and edit features" (clickable hint)
- "Included from basic" (inheritance label)
- "Requires: [Feature Name]" (dependency notice)
- "Yearly billing typically offers a discount" (helper text)
- "View Subscribers" (navigation button)
- "This plan inherits all features from..." (hierarchy notice)

### ❌ DO NOT Use:
- "Plan limits" (use "Features" instead)
- "Upgrade/Downgrade" on this page (vendor-specific, belongs in Vendor Detail)
- "Billing cycle" (removed from plan definition)
- "Static feature list" (must be feature selector)

---

## L. SUCCESS CRITERIA

**Enhanced Subscription Management is successful if:**

✅ Admin can create plans with feature-based configuration  
✅ Hierarchy is enforced (Standard includes Basic, Premium includes Standard)  
✅ Monthly + yearly pricing clearly displayed  
✅ "View Subscribers" navigates to Vendors with filter applied  
✅ No duplicate feature definitions across plans  
✅ All plan edits logged to audit trail  
✅ Inherited features cannot be removed  
✅ Dependencies auto-selected  

**Enhanced Subscription Management has failed if:**

❌ Features duplicated per plan (not referencing global definitions)  
❌ Admin can remove inherited features  
❌ Billing cycle selector still in plan definition  
❌ "View Subscribers" creates duplicate subscriber list  
❌ No audit logging on plan changes  
❌ Plan changes silently break active subscriptions  

---

## M. ROLE-BASED ACCESS

**Admin:**
- ✅ View all plans
- ✅ Edit plan pricing
- ✅ Edit plan features
- ✅ Toggle "Most Popular"
- ✅ View subscribers (redirects to Vendors)
- ❌ Cannot change active subscriptions here

**Super Admin:**
- ✅ All Admin permissions
- ✅ Create new plans
- ✅ Delete plans (with confirmation)
- ⚠️ All actions logged

**Vendors:**
- ❌ No access to this page
- Their subscription visible in Vendor Dashboard

---

**END OF ENHANCED SUBSCRIPTION MANAGEMENT SPECIFICATION**
