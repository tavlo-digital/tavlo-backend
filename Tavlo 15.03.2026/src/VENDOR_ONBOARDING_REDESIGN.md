# Vendor Onboarding Flow - Redesign Documentation

## Overview
The vendor onboarding flow has been redesigned to maximize signup speed, show immediate value, and delay friction until after perceived value demonstration. Vendors can now access the dashboard immediately in demo mode and complete activation at their own pace.

## Design Principles

1. **Speed First**: 30-second signup with minimal fields
2. **Value First**: Immediate access to dashboard in demo mode
3. **Friction Later**: Delay payment and complex forms until value is shown
4. **Clear States**: Visual indicators for demo, active-incomplete, and live status
5. **Checklist-Based**: Replace step wizard with flexible checklist approach

## User Flow

### 1. Registration (30 seconds)
- **Fields**: Business name, Country, Email, Password
- **Removed**: Address, Opening hours, Menu, VAT, Tables, Payment details
- **Outcome**: Immediate redirect to dashboard in demo mode

### 2. Dashboard - Demo Mode
- **Banner**: Persistent top banner showing "Your restaurant is not live"
- **CTA**: "Activate restaurant" button
- **Dashboard**: Full dashboard visible with demo data
- **Interactions**: All actions locked with lock icon and tooltip
- **Navigation**: Can browse all features but cannot perform actions

### 3. Activation Checklist
Opens when user clicks "Activate restaurant" button.

#### Required Steps:
1. **Restaurant Basics**
   - Restaurant name
   - Legal business name
   - Address
   - Country, Currency, Timezone (auto-populated)
   
2. **Menu** (Minimum Setup)
   - At least 1 category
   - At least 1 item with name, price, VAT
   - No photos/modifiers/allergens required initially
   
3. **Subscription** (System Unlock)
   - Monthly or Yearly plan selection
   - Stripe payment (unlocks the system)
   - Note: Customer payments configured separately later
   
4. **Legal & Tax Information**
   - Legal business name
   - VAT/Tax number
   - Registered address
   - Receipt language
   
#### Optional Steps:
5. **Tables & QR Codes**
   - Add table numbers
   - Generate QR codes
   - Can skip for takeaway-only restaurants

## Vendor Status States

### Demo Mode
- **Condition**: No subscription paid
- **Banner**: Amber background - "Your restaurant is not live"
- **Badge**: "Demo Mode" with lock icon
- **Dashboard**: Visible with demo data
- **Actions**: All locked

### Active - Incomplete
- **Condition**: Subscription paid but not all required steps completed
- **Banner**: Blue background - "Setup incomplete"
- **Badge**: "Setup Incomplete" with alert icon
- **Dashboard**: Fully functional
- **Features**: Some locked (e.g., online payments if PSP not configured)

### Live
- **Condition**: All required steps completed
- **Banner**: No banner shown
- **Badge**: "Live" with checkmark icon
- **Dashboard**: Fully functional
- **Features**: All unlocked

## Payment Separation

### Subscription Payment (Required for Activation)
- **Purpose**: Unlock TAVLO system
- **Method**: Simple Stripe card payment
- **Timing**: Required to move from Demo to Active status

### Customer Payments (Optional for Go-Live)
- **Purpose**: Accept online customer payments
- **Configuration**: Separate in Settings → Payment Settings
- **Options**: Can go live with "Pay at restaurant" only
- **Timing**: Not required for initial activation

## Component Structure

### New Components
- `DemoModeBanner.tsx` - Persistent banner showing vendor status
- `ActivationChecklist.tsx` - Checklist-based activation panel
- `VendorStatusBadge.tsx` - Status badge (Demo/Active-Incomplete/Live)
- `LockedFeatureOverlay.tsx` - Overlay for locked features
- `OnboardingDashboardWrapper.tsx` - Wrapper combining dashboard with onboarding state

### Updated Components
- `VendorOnboardingFlow.tsx` - Main flow controller
- `VendorRegistration.tsx` - Streamlined signup form

### Existing Components (Reused)
- `RestaurantSetup.tsx` - Restaurant basics form
- `MenuSetup.tsx` - Minimal menu setup
- `TablesQRSetup.tsx` - Tables and QR code generation
- `SubscriptionGate.tsx` - Subscription plan selection
- `LegalDataForm.tsx` - Legal and tax information

## Features NOT Included in Onboarding

The following features remain post-activation:
- Appearance customization
- Advanced analytics
- Inventory management
- Nutrition information
- Reviews management
- API access

## Implementation Notes

### Status Determination Logic
```typescript
const determineVendorStatus = (progress) => {
  const hasSubscription = progress.subscription;
  const requiredComplete = progress.restaurantBasics && 
                          progress.menu && 
                          progress.subscription && 
                          progress.legalTax;
  
  if (!hasSubscription) return 'demo';
  if (!requiredComplete) return 'active-incomplete';
  return 'live';
};
```

### Setup Progress Tracking
```typescript
setupProgress: {
  restaurantBasics: boolean;
  menu: boolean;
  subscription: boolean;
  legalTax: boolean;
  tablesQR: boolean; // optional
}
```

## User Experience Benefits

1. **Faster Time to Value**: Vendors see the dashboard in 30 seconds
2. **Lower Abandonment**: No long forms before seeing value
3. **Flexible Completion**: Complete setup steps in any order
4. **Clear Progress**: Visual progress bar and checklist status
5. **Reduced Confusion**: Clear separation between subscription and customer payments
6. **Better Conversion**: Pay after seeing value, not before

## Technical Integration Points

### API Endpoints
- `POST /vendor/register` - Create vendor account
- `GET /vendor/:id/check-subscription` - Check subscription status
- `POST /vendor/:id/create-checkout` - Create Stripe checkout session
- `GET /vendor/verify-checkout/:sessionId` - Verify payment completion

### State Management
- Vendor status: 'demo' | 'active-incomplete' | 'live'
- Setup progress: Individual boolean flags for each step
- Dynamic status calculation based on progress

### Storage
- Vendor data stored incrementally as steps are completed
- Subscription data stored after successful payment
- All data persists across sessions

## Future Enhancements

1. **Progress Persistence**: Save checklist state to backend
2. **Smart Recommendations**: Suggest next steps based on restaurant type
3. **Video Tutorials**: Inline help for each setup step
4. **Template Menus**: Pre-built menu templates for common cuisines
5. **Batch Import**: CSV/Excel import for large menus
6. **QR Code Customization**: Branded QR codes (post-activation)
