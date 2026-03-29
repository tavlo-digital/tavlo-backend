# TAVLO Admin System — Boundaries & Philosophy

**Version:** 2.0  
**Last Updated:** December 25, 2024  
**Status:** Production Ready

---

## 🎯 CORE PRINCIPLE

> **"Tavlo enables restaurants and customers. Tavlo does not run restaurants."**

Tavlo is a **software platform and payment facilitator**, not a restaurant operator. The admin system is designed to reflect **oversight, not control**.

---

## 🔒 NON-NEGOTIABLE RULES

### What Tavlo Admin CAN DO:
✅ Observe vendor activity (aggregated data)  
✅ Manage subscriptions and billing  
✅ Approve/suspend vendor accounts  
✅ Moderate customer reviews and complaints  
✅ Enforce platform rules and terms of service  
✅ Access support data for customer service  
✅ Generate compliance reports (VAT, GDPR)  
✅ Configure platform-wide settings  

### What Tavlo Admin CANNOT DO:
❌ Edit restaurant menus or menu items  
❌ Change restaurant prices  
❌ Modify restaurant branding or logos  
❌ Interfere with live customer orders  
❌ Control restaurant opening hours  
❌ Modify individual restaurant settings  
❌ Access customer orders without legal justification  

---

## 📊 DASHBOARD LANGUAGE

All metrics use **neutral, observation-based language**:

| ❌ WRONG | ✅ CORRECT |
|---------|-----------|
| "Monthly Revenue" | "Gross Order Value (via Tavlo)" |
| "Restaurant Earnings" | "Order Volume Processed" |
| "Profit" | "Platform Subscription Revenue" |
| "Control Panel" | "Platform Overview" |
| "Manage Menus" | "View Menu Data (Read-Only)" |

### Required Tooltips:
- **Gross Order Value**: "Total order value processed through Tavlo. This is not vendor profit."
- **Tips Collected**: "Tips go directly to restaurants, not Tavlo."
- **Subscription Pricing**: "Subscription pricing is independent of restaurant menu pricing."
- **Platform Data**: "Aggregated platform data for monitoring purposes."

---

## 🏪 VENDOR MANAGEMENT

### Admin Can SEE (Read-Only):
- Vendor status (setup / active / overdue / suspended)
- Subscription plan and payment status
- Gross Order Value (via Tavlo platform)
- Order counts and trends
- Customer rating (aggregated)
- Risk level (AI-generated for platform health)

### Admin Actions (Limited & Logged):
1. **Suspend Vendor** → Requires reason, logged in audit trail
2. **Reactivate Vendor** → Requires reason, logged in audit trail
3. **Add Internal Note** → Not visible to vendor
4. **View Billing & Compliance Data** → Support-only

### UI Requirements:
- Prominent notice: "Admin cannot edit menus or prices"
- All vendor detail views are **read-only**
- No "Edit Menu" or "Edit Prices" buttons
- "Gross Order Value (via Tavlo)" label with tooltip
- Link to Audit Log for all actions

---

## 👤 CUSTOMER MANAGEMENT

### Privacy-First Design:

#### Default View (SAFE — No Login Required):
- Customer ID
- Account type (guest / registered)
- Order count
- Last activity
- Flagged status

#### Restricted View (Role-Based — Logged in Audit Trail):
- Email (hidden by default, requires toggle)
- Phone number (hidden by default, requires toggle)
- Total spend
- Tips given
- Loyalty points

### UI Requirements:
- **Personal data hidden by default** with 🔒 icon
- Toggle: "Show restricted data (for support only)"
- Warning when toggled: "⚠️ Data access logged"
- Label: "Visible for support purposes only"
- Remove restaurant linkage from list view
- Show restaurant context only inside support tickets

### GDPR Actions (All Logged):
1. **Export Personal Data** → GDPR Right to Access
2. **Anonymize Customer** → GDPR Right to Erasure
3. **Delete Account Permanently** → Requires confirmation

---

## 💳 BILLING & INVOICES

### Vendor Invoices:
- **Tavlo subscription invoices** (€49/month or €490/year)
- VAT breakdown for platform services
- Status tracking (paid / overdue / failed)
- Export VAT reports

### Customer Invoices:
- **Order receipts** (metadata only, view-only)
- No item-level price manipulation
- Clear label: "Tavlo invoices cover platform services, not restaurant sales accounting."

---

## 📦 SUBSCRIPTIONS

### Admin Controls:
- Manage subscription plans (Monthly / Yearly)
- Set pricing (with clear separation from menu pricing)
- Track MRR (Monthly Recurring Revenue)
- Monitor churn and retention
- View overdue accounts

### UI Requirements:
- Tooltip: "Subscription pricing is independent of restaurant menu pricing."
- Admin cannot grant free access without audit trail
- All payment overrides logged with reason

---

## ⭐ REVIEWS & COMPLAINTS (MODERATION ONLY)

### Admin Can:
- Approve or hide reviews
- Remove abusive content
- Resolve customer complaints
- Flag policy violations

### Admin Cannot:
- Edit review content
- Modify star ratings artificially
- Delete reviews (only hide visibility)

### UI Requirements:
- Banner: "Moderation affects visibility only. Original reviews are preserved in audit logs."
- All moderation actions logged with reason

---

## 🤖 AI INSIGHTS (INTERNAL USE ONLY)

### AI Provides:
- Platform risk indicators
- Subscription churn predictions
- Engagement trend analysis
- Revenue optimization suggestions

### AI Does NOT:
- Judge restaurant quality
- Score food quality
- Make customer-facing recommendations

### UI Requirements:
- Disclaimer: "AI insights are internal estimates used for platform operations only."
- All insights explainable and auditable

---

## ⚙️ SYSTEM SETTINGS

### Platform-Wide Controls:
- Supported languages
- Supported currencies
- Date & time formats
- Notification rules
- Business rules (e.g., max order size)
- Feature flags (enable/disable features globally)

### Admin Does NOT Control:
- Individual restaurant settings
- Menu languages per vendor (vendor controls this)
- Restaurant opening hours (vendor controls this)

---

## 🛡️ ADMIN ROLES & PERMISSIONS

### Roles:

1. **Super Admin**
   - Full platform access
   - Vendor approval/suspension
   - Financial data access
   - System configuration
   - Audit log access

2. **Finance Admin**
   - Billing & invoices only
   - Subscription management
   - VAT reports
   - No customer data access

3. **Support Admin**
   - Customer data access (logged)
   - Complaint resolution
   - Vendor support (read-only)
   - No financial data access

4. **Content Admin**
   - Review moderation
   - CMS editing
   - Translation management
   - No customer personal data access

### UI Requirements:
- Permission matrix view
- Read-only indicators where applicable
- Clear role badges on all actions

---

## 📜 AUDIT LOG (MANDATORY)

### All Admin Actions Must Be Logged:

**Required Fields:**
- Admin user (email + role)
- Action taken (e.g., VENDOR_SUSPENDED, CUSTOMER_DATA_ACCESSED)
- Entity affected (vendor ID, customer ID, etc.)
- Timestamp (ISO 8601)
- **Mandatory reason field** (free text, min 10 characters)
- IP address
- Details (before/after state if applicable)

### Severity Levels:
- 🔵 **INFO**: Routine actions (view reports, generate invoices)
- 🟠 **WARNING**: Data access (customer PII viewed, vendor details modified)
- 🔴 **CRITICAL**: Account actions (suspend vendor, delete customer, override payment)

### Retention:
- **7 years** (GDPR compliance, legal defense)
- **Cannot be deleted or modified**
- Exportable for auditors and regulators

### UI Requirements:
- Dedicated "Audit Log" page
- Searchable by admin, action type, date range
- Filter by severity
- Export to CSV/PDF
- Compliance notice: "This audit log is maintained for GDPR compliance, internal accountability, and legal defense."

---

## 🧠 DATA PHILOSOPHY

### Transparency Labels:

Add these labels throughout the admin UI where relevant:

- 🔒 **"Support-only visibility"** → Restricted data
- 📊 **"Aggregated platform data"** → Safe to display
- 🔒 **"Not visible to vendors"** → Internal metrics
- 🔒 **"Internal use only"** → AI insights

### Design Goal:
> If a regulator, vendor, or auditor sees this UI, they should **immediately understand Tavlo's limited, appropriate role**.

---

## ✅ IMPLEMENTATION CHECKLIST

### Dashboard:
- [ ] Replace "Monthly Revenue" with "Gross Order Value (via Tavlo)"
- [ ] Add tooltips to all financial metrics
- [ ] Show tooltips: "Aggregated platform data" on stats
- [ ] Emphasize observation language, not control

### Vendor Management:
- [ ] Add prominent notice: "Admin cannot edit menus or prices"
- [ ] Hide "Edit Menu" buttons
- [ ] Show "Gross Order Value (via Tavlo)" with tooltip
- [ ] Add "Read-Only View" warning on detail pages
- [ ] Add "Audit Log" link per vendor
- [ ] Limit actions to: Suspend, Reactivate, Add Note

### Customer Management:
- [ ] Hide email/phone by default with 🔒 icon
- [ ] Add toggle: "Show restricted data (for support only)"
- [ ] Add warning: "⚠️ Data access logged"
- [ ] Add GDPR action buttons (Export, Anonymize, Delete)
- [ ] Remove restaurant linkage from list view

### Audit Log:
- [ ] Create dedicated Audit Log page
- [ ] Implement logging for all admin actions
- [ ] Add mandatory "Reason" field for critical actions
- [ ] Display severity levels (Info, Warning, Critical)
- [ ] Add filters: admin user, action type, date, severity
- [ ] Add export functionality
- [ ] Add compliance notice

### Billing & Subscriptions:
- [ ] Add label: "Tavlo invoices cover platform services, not restaurant sales accounting."
- [ ] Add tooltip: "Subscription pricing is independent of restaurant menu pricing."
- [ ] Remove silent overrides (all logged)

### Reviews & Moderation:
- [ ] Add banner: "Moderation affects visibility only. Original reviews are preserved in audit logs."
- [ ] Remove "Edit Review" option (only hide/approve)

### AI Insights:
- [ ] Add disclaimer: "AI insights are internal estimates used for platform operations only."
- [ ] Add explainability for all insights

---

## 🎯 FINAL VALIDATION QUESTIONS

Before deploying admin features, ask:

1. **Control Test**: Could this action interfere with restaurant operations?  
   → If YES, remove or make read-only.

2. **Language Test**: Does this metric imply Tavlo controls the restaurant?  
   → If YES, rephrase with neutral language + tooltip.

3. **Audit Test**: Is this action logged with admin user, reason, and timestamp?  
   → If NO, add logging before deployment.

4. **Privacy Test**: Is customer PII visible without justification?  
   → If YES, hide by default and require toggle with logging.

5. **Regulator Test**: If an auditor saw this screen, would they understand Tavlo's role?  
   → If NO, add clarifying labels and disclaimers.

---

## 📚 KEY FILES

- **Components:**
  - `/components/admin/Dashboard.tsx` → Platform overview with proper language
  - `/components/admin/ImprovedVendorManagement.tsx` → Read-only vendor data
  - `/components/admin/ImprovedCustomerManagement.tsx` → GDPR-safe customer data
  - `/components/admin/AuditLog.tsx` → Full audit trail
  - `/components/admin/AdminLayout.tsx` → Navigation structure

- **Documentation:**
  - `/TAVLO_SRS.md` → Complete system requirements
  - `/ADMIN_SYSTEM_BOUNDARIES.md` → This file

---

## 🚀 DEPLOYMENT NOTES

1. All admin actions must be logged before going live
2. Train admin users on boundaries (provide this document)
3. Test audit log retention (7 years)
4. Verify GDPR export functionality
5. Ensure tooltips render correctly on all screens
6. Conduct regulator/auditor walkthrough if possible

---

**Last Reviewed:** December 25, 2024  
**Next Review:** Quarterly or upon regulatory changes
