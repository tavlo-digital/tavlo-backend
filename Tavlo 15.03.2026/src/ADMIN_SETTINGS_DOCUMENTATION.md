# TAVLO Admin System Settings — Complete Documentation

**Date:** December 25, 2024  
**Component:** `/components/admin/SystemSettings.tsx`  
**Status:** ✅ Production Ready

---

## 🎯 CRITICAL PRINCIPLE

**Tavlo is a software platform provider, NOT a restaurant operator.**

These settings control:
- ✅ Platform infrastructure
- ✅ Tavlo subscription billing
- ✅ Compliance & data protection
- ✅ Technical integrations

These settings DO NOT control:
- ❌ Restaurant menus or pricing
- ❌ Customer order behavior
- ❌ Tip amounts or service fees
- ❌ Vendor operational rules

**All restaurant operations belong to vendors and are configured in the vendor dashboard.**

---

## 📋 SETTINGS STRUCTURE

### **Tab 1: General Settings**
**Purpose:** Platform-wide defaults and localization

#### Settings Included:
1. **Default Platform Language**
   - Languages: English, German, Arabic, French
   - Applies to: Admin interface default
   - Users can override: Yes

2. **Supported UI Languages**
   - Multi-select checkboxes
   - Available to: Customers, vendors, admin
   - Currently enabled: English, German, Arabic

3. **Platform Timezone**
   - Options: Europe/Vienna, Europe/Berlin, UTC, Europe/London
   - Used for: Platform operations and reporting
   - Default: Europe/Vienna (CET/CEST)

4. **Date & Time Format**
   - Date formats: DD/MM/YYYY, MM/DD/YYYY, YYYY-MM-DD
   - Time formats: 24-hour, 12-hour
   - Affects: All date/time displays

#### Boundaries:
- ✅ Admin can change platform defaults
- ❌ Cannot force language on individual users
- ❌ Cannot change vendor-specific localization

---

### **Tab 2: Payment Infrastructure**
**Purpose:** Platform-level payment provider configuration

#### Settings Included:
1. **Stripe Configuration**
   - API Mode: Live / Test
   - Publishable Key (masked)
   - Secret Key (masked)
   - Webhook Status: Active / Inactive
   - Link to webhook logs

2. **PayPal Configuration**
   - Enable/Disable toggle
   - Description: "Enable PayPal to allow vendors to accept PayPal payments"

#### ⚠️ VENDOR SETTINGS NOTICE (Prominent Banner):
> **Vendor-Controlled Settings**  
> Payment methods, tipping settings, and service fees are configured by each vendor.  
> These settings only control which providers are available on the platform.

#### 💡 CASH PAYMENT NOTICE (Blue Info Banner):
> **Cash Payment Configuration**  
> Cash payment acceptance is configured by each vendor in their dashboard settings.  
> Tavlo does not control whether vendors accept cash.

#### Boundaries:
- ✅ Admin can enable/disable payment providers platform-wide
- ✅ Admin can manage API keys
- ✅ Admin can monitor webhook health
- ❌ Admin CANNOT force vendors to accept specific payment methods
- ❌ Admin CANNOT configure tip percentages
- ❌ Admin CANNOT set service fees

---

### **Tab 3: Subscriptions & Billing**
**Purpose:** Configure Tavlo subscription plans and billing rules (Tavlo revenue only)

#### Settings Included:
1. **Subscription Plans** (3 plans displayed)
   
   **Basic Plan:**
   - Monthly: €49.00
   - Annual: €490.00
   - Max Orders/Month: 500
   - VAT Rate: 20%
   - Features: QR Code Ordering, Basic Analytics, Email Support

   **Standard Plan:**
   - Monthly: €99.00
   - Annual: €990.00
   - Max Orders/Month: 2,000
   - VAT Rate: 20%
   - Features: All Basic + Advanced Analytics, Priority Support, Custom Branding

   **Premium Plan:** (Marked as Popular)
   - Monthly: €199.00
   - Annual: €1,990.00
   - Max Orders/Month: Unlimited
   - VAT Rate: 20%
   - Features: All Standard + Multi-location, API Access, 24/7 Support

2. **Trial & Grace Period**
   - Trial Duration: 0, 7, 14 (default), 30 days
   - Grace Period: 3, 7 (default), 14 days
   - Auto-suspend after grace period: Yes

3. **Invoice Configuration**
   - Frequency: Monthly (default), Quarterly, Annual
   - Auto-send invoices: Yes (checkbox)
   - Send payment reminders: Yes (checkbox)

#### 💜 SCOPE NOTICE (Purple Banner):
> **Note:** These are Tavlo subscription fees charged to vendors for using the platform.  
> Not related to restaurant menu pricing or customer orders.

#### Boundaries:
- ✅ Admin can edit subscription plan pricing
- ✅ Admin can set trial periods
- ✅ Admin can configure billing frequency
- ✅ Admin can set VAT for platform fees
- ❌ This VAT is for Tavlo service fees ONLY, not restaurant sales tax
- ❌ Admin CANNOT set pricing for vendor menus
- ❌ Admin CANNOT charge customers directly

---

### **Tab 4: Compliance & Data Privacy**
**Purpose:** GDPR compliance and data protection rules

#### Settings Included:
1. **GDPR User Data Management** (3 toggles)
   - User Data Export: Enabled
   - Right to be Forgotten: Enabled
   - Data Portability: Enabled

2. **Data Retention Rules**
   - Order Data: 1, 2, 3, 7 years (default), indefinite
     - Note: "Austrian law requires 7 years for financial records"
   - Guest User Data: 30, 90 (default), 180, 365 days
   - Audit Log: 3, 7 (default), 10 years

3. **Guest Data Anonymization**
   - Auto-anonymize after retention: Yes (checkbox)
   - Remove PII but keep analytics: Yes (checkbox)
   - Explanation: "Guest personal data replaced with anonymized identifiers"

4. **Audit Log Configuration**
   - Log all admin actions: Yes
   - Log customer data access: Yes
   - Log vendor account changes: Yes
   - Require mandatory reason for sensitive actions: Yes

#### Boundaries:
- ✅ Admin must comply with GDPR
- ✅ Admin can set retention policies
- ✅ Admin can export/delete user data
- ❌ Cannot selectively ignore GDPR requests
- ❌ Cannot edit audit logs (immutable)

---

### **Tab 5: Vendor Onboarding Rules**
**Purpose:** Configure how new vendors join the platform

#### Settings Included:
1. **Approval Process** (Radio buttons)
   - **Manual Approval (Recommended):** Admin reviews each application
   - Auto-Approval: Auto-approved after completing steps

2. **Required Onboarding Steps**
   - ✅ Business information (Required, disabled)
   - ✅ Contact details (Required, disabled)
   - ✅ Subscription plan selection (Required, disabled)
   - ☑️ Business registration documents (Optional)
   - ☑️ VAT/Tax identification (Optional)
   - ☐ Food safety certificates (Optional)
   - ☐ Bank account verification (Optional)

3. **Subscription Activation**
   - Require active subscription before going live: Yes
   - Allow trial period without payment: Yes

4. **Country Availability**
   - ✅ Austria (enabled)
   - ✅ Germany (enabled)
   - ☐ Switzerland
   - ☐ Netherlands

5. **Onboarding Notifications**
   - Notify admin on new registration: Yes
   - Send welcome email to approved vendors: Yes
   - Send rejection explanation: Optional

#### Boundaries:
- ✅ Admin can set onboarding requirements
- ✅ Admin can approve/reject vendors
- ✅ Admin can require subscription before activation
- ❌ Admin CANNOT skip compliance checks
- ❌ Admin CANNOT force vendors to accept specific terms beyond platform rules

---

### **Tab 6: Admin Notifications**
**Purpose:** Configure internal admin alerts (platform internal only)

#### Settings Included:
1. **Notification Channels**
   - Email Notifications: admin@tavlo.com (toggle)
   - Slack Notifications: #tavlo-admin (toggle, disabled by default)

2. **System Alerts**
   - ✅ Payment provider downtime
   - ✅ Failed webhook deliveries
   - ✅ Database backup failures
   - ✅ High error rate (>5% of requests)
   - ☐ Slow API response times (>2s average)

3. **Business Events**
   - ✅ New vendor registration
   - ✅ Vendor subscription payment failed
   - ☐ Vendor subscription upgraded/downgraded
   - ☐ Vendor account suspended
   - ✅ Customer complaint filed
   - ☐ Review flagged for moderation

4. **GDPR & Compliance**
   - ✅ User data export request
   - ✅ Account deletion request
   - ✅ Data breach detection

#### 💙 SCOPE NOTICE (Blue Banner):
> **Note:** These are platform internal notifications for admin team only.  
> Vendor and customer notifications are configured separately.

#### Boundaries:
- ✅ Admin can configure internal alerts
- ✅ Admin can set notification channels
- ❌ This does NOT control vendor email settings
- ❌ This does NOT control customer order notifications
- ❌ Vendor notification preferences are vendor-controlled

---

### **Tab 7: Roles & Permissions**
**Purpose:** Role-based access control for admin users

#### Settings Included:
1. **Predefined Roles** (5 roles)

   **Super Admin**
   - Description: Full platform access
   - Users: 2
   - Permissions: All modules (View, Edit, Special actions)

   **Finance Admin**
   - Description: Billing, invoices, subscriptions
   - Users: 3
   - Permissions: View dashboard, vendors; Full access to invoices; View audit logs

   **Support Admin**
   - Description: Customer support, complaints
   - Users: 5
   - Permissions: View all data; No edit access to settings; No delete permissions

   **Compliance Admin**
   - Description: GDPR, audit logs, data protection
   - Users: 2
   - Permissions: View all data; Edit settings; GDPR delete actions; Export audit logs

   **Content Moderator**
   - Description: Review moderation only
   - Users: 3
   - Permissions: View/moderate reviews ONLY; View audit logs; No other access

2. **Permission Matrix** (Per-role table)
   
   Modules covered:
   - Dashboard (View, Edit)
   - Vendors (View, Edit, Suspend)
   - Customers (View, Edit, GDPR Delete)
   - Invoices & Billing (View, Edit, Send)
   - Reviews & Moderation (View, Moderate, Hide)
   - System Settings (View, Edit)
   - Audit Logs (View, Export) — Always read-only
   - Admin User Management (View, Edit, Create)

3. **Permission Notes**
   - 🔒 Audit Logs are always read-only (no role can edit)
   - 🛡️ GDPR actions require Compliance Admin or Super Admin
   - ⚠️ Vendor suspension requires reason and is logged

#### 💜 PRINCIPLE BANNER (Purple Banner):
> **Principle of Least Privilege:** Each role sees only what is necessary for their function.

#### Boundaries:
- ✅ Admin can create custom roles
- ✅ Admin can assign granular permissions
- ✅ All role changes are logged
- ❌ Cannot bypass audit logging
- ❌ Cannot give roles access to vendor operational settings
- ❌ Cannot edit historical audit logs

---

## 🚫 EXPLICITLY REMOVED SETTINGS

These settings **DO NOT appear** in Admin Settings (they are vendor-level):

### ❌ Cash Payment Toggles
- **Why removed:** Each vendor decides if they accept cash
- **Where it belongs:** Vendor Dashboard → Settings → Payment Methods

### ❌ Tip Configuration
- **Why removed:** Tip amounts are set by vendors
- **Where it belongs:** Vendor Dashboard → Settings → Tipping

### ❌ Service Fee Settings
- **Why removed:** Service fees (if any) are vendor-determined
- **Where it belongs:** Vendor Dashboard → Pricing

### ❌ Order Limits and Timeouts
- **Why removed:** Order capacity is vendor operational decision
- **Where it belongs:** Vendor Dashboard → Operations → Order Settings

### ❌ QR Expiration Rules
- **Why removed:** QR code validity is vendor preference
- **Where it belongs:** Vendor Dashboard → QR Code Settings

### Visual Indicator:
When admin tries to access vendor-level settings, display:

```
┌─────────────────────────────────────────────┐
│ ⚠️ Vendor-Level Setting                    │
│                                             │
│ This setting is configured by each vendor  │
│ in their dashboard. Admin cannot change    │
│ vendor operational rules.                  │
│                                             │
│ [View Vendor Dashboard Example] →          │
└─────────────────────────────────────────────┘
```

---

## 🎨 UI/UX FEATURES

### 1. **Unsaved Changes Bar**
- Sticky bottom bar appears when any setting is modified
- Amber warning icon: "You have unsaved changes"
- Actions: Discard | Save Changes
- Prevents accidental navigation loss

### 2. **Boundary Notices**
Three types of notice banners:

**Purple (Platform Governance):**
```
These settings control Tavlo platform infrastructure only.
Restaurant operations are configured by each vendor in their dashboard.
```

**Amber (Vendor Settings Warning):**
```
Payment methods, tipping settings, and service fees are configured by each vendor.
These settings only control which providers are available on the platform.
```

**Blue (Informational):**
```
Cash payment acceptance is configured by each vendor in their dashboard settings.
Tavlo does not control whether vendors accept cash.
```

### 3. **Tab Navigation**
- 7 tabs with icons
- Active tab highlighted in purple
- Horizontal scroll on mobile
- Icons: Globe, CreditCard, FileText, Shield, UserCheck, Bell, Lock

### 4. **Form Patterns**
- Toggles for enable/disable
- Dropdowns for single-select
- Checkboxes for multi-select
- Masked API keys with "eye" reveal button
- Save/Reset buttons per section

### 5. **Permission Matrix Table**
- Visual checkmarks (✓) for granted permissions
- X marks for denied permissions
- Color-coded: Green (allowed), Gray (denied)
- Special actions column (e.g., "Suspend", "GDPR Delete")

---

## 🔐 SECURITY & AUDIT

### All Settings Changes Are Logged:
- **Admin User:** Who made the change
- **Timestamp:** When it occurred
- **Setting:** What was changed
- **Old Value:** Previous value
- **New Value:** New value
- **Reason:** Mandatory for sensitive changes

### Sensitive Actions Requiring Reason:
- Changing subscription pricing
- Modifying data retention periods
- Disabling GDPR features
- Changing admin roles/permissions
- Modifying payment provider keys

### Audit Log Entry Example:
```json
{
  "id": "audit_12345",
  "timestamp": "2024-12-25T14:30:00Z",
  "admin_user": "sarah@tavlo.com",
  "admin_role": "Super Admin",
  "action": "UPDATE_SETTING",
  "module": "Subscriptions & Billing",
  "setting": "basic_plan_monthly_price",
  "old_value": "€49.00",
  "new_value": "€59.00",
  "reason": "Updating pricing for 2025 to reflect increased costs",
  "severity": "warning",
  "ip_address": "192.168.1.100"
}
```

---

## 📊 VALIDATION & CONSTRAINTS

### Data Validation Rules:
1. **Subscription Pricing:**
   - Must be positive number
   - VAT rate: 0-100%
   - Annual price should be ~10x monthly (warning if not)

2. **Retention Periods:**
   - Minimum: 30 days
   - Maximum: 10 years (or indefinite)
   - Warning if below legal requirements (7 years for Austrian financial records)

3. **Trial Periods:**
   - 0-90 days
   - Grace period < Trial period (validation)

4. **API Keys:**
   - Format validation for Stripe keys
   - Test mode keys must start with `pk_test_` or `sk_test_`
   - Live mode keys must start with `pk_live_` or `sk_live_`

5. **Country Availability:**
   - At least 1 country must be enabled
   - Cannot disable if active vendors exist in that country

---

## 🚀 INTEGRATION POINTS

### Settings Affect:
1. **Vendor Onboarding Flow:**
   - Reads approval process setting
   - Checks required onboarding steps
   - Validates country availability

2. **Billing System:**
   - Uses subscription plan pricing
   - Applies VAT from settings
   - Respects invoice frequency

3. **GDPR Workflows:**
   - Export user data based on retention rules
   - Auto-anonymize guest data
   - Respond to deletion requests

4. **Admin Dashboard:**
   - Filters based on role permissions
   - Shows/hides modules based on access
   - Logs all data access

5. **Payment Processing:**
   - Uses configured Stripe/PayPal credentials
   - Routes webhooks to logged endpoints
   - Shows only enabled payment providers to vendors

---

## ✅ DEPLOYMENT CHECKLIST

Before deploying to production:

- [ ] All boundary notices are visible and clear
- [ ] Vendor-level settings are NOT present in admin settings
- [ ] Subscription pricing is set correctly (Basic: €49, Standard: €99, Premium: €199)
- [ ] VAT rate matches Austrian requirements (20%)
- [ ] Data retention meets legal minimums (7 years for financial)
- [ ] GDPR toggles are all enabled
- [ ] Audit logging is enabled for all sensitive actions
- [ ] Payment provider credentials are in secure environment variables
- [ ] Role permissions are validated against least-privilege principle
- [ ] All admin actions trigger audit log entries
- [ ] Unsaved changes warning works correctly
- [ ] Test each role's permission matrix
- [ ] Verify no vendor operational controls are present

---

## 🎓 TRAINING GUIDE FOR ADMIN USERS

### What Admin CAN Do:
✅ Configure platform infrastructure (languages, timezone, payment providers)  
✅ Set Tavlo subscription pricing and billing rules  
✅ Manage GDPR compliance and data retention  
✅ Approve/reject vendor applications  
✅ Configure admin roles and permissions  
✅ View all platform data (with proper role access)

### What Admin CANNOT Do:
❌ Edit vendor menus or change product prices  
❌ Force vendors to accept specific payment methods  
❌ Set tip percentages for vendors  
❌ Control vendor service fees  
❌ Modify customer orders after placement  
❌ Change vendor QR code expiration times  
❌ Bypass GDPR user deletion requests  
❌ Edit audit logs after creation

### Golden Rule:
> **"Tavlo enables restaurants and customers. Tavlo does not run restaurants."**

Every admin action should respect vendor autonomy in their operational decisions.

---

## 📞 SUPPORT & TROUBLESHOOTING

### Common Questions:

**Q: Why can't I set the tip percentage for vendors?**  
A: Tip configuration is a vendor operational decision. Each vendor sets their own tipping options in their dashboard.

**Q: Can I force all vendors to accept cash payments?**  
A: No. Cash acceptance is vendor-configured. Tavlo only provides the platform infrastructure.

**Q: Where do I configure customer order timeout limits?**  
A: Order timeouts are vendor-specific settings in the Vendor Dashboard, not admin settings.

**Q: Can I change a vendor's menu prices?**  
A: No. Menu pricing is exclusively controlled by the vendor. Admin can only observe, not modify.

**Q: Why is the Audit Log read-only?**  
A: Audit logs are immutable for compliance and accountability. No role can edit historical logs.

---

**Last Updated:** December 25, 2024  
**Component Version:** 1.0.0  
**Review Status:** ✅ Production Ready  
**Compliance:** GDPR-compliant, Austrian VAT-compliant
