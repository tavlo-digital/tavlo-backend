# 🛠️ TAVLO Super Admin Dashboard

## Overview

The TAVLO Super Admin Dashboard is a comprehensive control panel for managing the entire platform. Built for internal TAVLO staff to manage vendors, customers, billing, subscriptions, and system-wide settings.

---

## 🚀 **How to Access**

Click the **mode switcher** (top-right corner) → **🛠️ Admin**

Or navigate directly with the hash: `#admin`

---

## 📋 **Features Implemented**

### ✅ **1. Dashboard (Overview)**
**Path:** Dashboard

**Features:**
- **8 Key Metrics Cards:**
  - Total Vendors (with breakdown: active/pending/suspended)
  - Total Customers (registered vs guest)
  - Active Subscriptions
  - Monthly Recurring Revenue (MRR)
  - Orders today / this month
  - Payment mix (card vs cash %)
  - Tips collected
  - Pending actions

- **Charts & Analytics:**
  - Vendor growth (line chart - 6 months)
  - Revenue by source (pie chart - subscriptions, fees, ads)
  - Order volume & revenue (bar chart - 7 days)

- **Action Items:**
  - Pending vendor approvals (5 vendors)
  - Recent complaints (12 open)
  - Quick action buttons

**UX:**
- Color-coded status indicators
- Trend arrows (up/down)
- Click-through to detailed views
- Real-time metrics

---

### ✅ **2. Vendor Management**

#### **Vendor List**
**Path:** Vendors → All Vendors

**Features:**
- **Advanced Table:**
  - Vendor name & type
  - Location (city, country)
  - Status badges (active/pending/suspended)
  - Subscription plan (Premium/Standard/Basic)
  - Payment status (paid/overdue/trial)
  - Monthly revenue
  - Rating & review count
  - Last active timestamp

- **Filters:**
  - Status filter
  - Plan filter
  - Country filter
  - Search by name/city/type
  - Export to CSV

- **Bulk Actions:**
  - Approve selected
  - Suspend selected
  - Export selected

- **Stats Bar:**
  - Total vendors
  - Active count
  - Pending count
  - Suspended count

**Actions:**
- View details
- Approve (pending vendors)
- Suspend (active vendors)
- More options menu

---

#### **Vendor Approval Page**
**Path:** Vendors → Pending Approval → [Select vendor]

**Features:**
- **4 Tab System:**
  1. **Business Details:** Contact info, registration, VAT, banking
  2. **Documents:** All uploaded PDFs (view/download)
  3. **Menu Preview:** Categories, items, images, allergens
  4. **Verification:** Automated checks with status

- **Requested Plan:**
  - Plan details
  - Features included
  - Option to change plan

- **Admin Actions:**
  - ✅ Approve & Activate
  - ❌ Reject Application (with reason)
  - Request Changes
  - Activate Trial
  - Contact Vendor
  - Admin Notes (internal)

**Verification Checklist:**
- Business registration verified ✓
- VAT number validated ✓
- Banking details confirmed ✓
- Documents reviewed ✓
- Menu quality check ✓
- Background check (pending)

---

### ✅ **3. Invoice Management**
**Path:** Billing & Invoices → Vendor Invoices

**Features:**
- **Dual Tabs:**
  - Vendor Invoices (subscriptions + one-time)
  - Customer Invoices (order receipts)

- **Stats Cards:**
  - Total outstanding (€2,847)
  - This month (€45,890)
  - Paid this week (€12,450)
  - Avg payment time (5.2 days)

- **Invoice Table:**
  - Invoice number & date
  - Vendor name & ID
  - Type (Subscription/One-time)
  - Period (e.g., "June 2024")
  - Amount breakdown (subtotal, VAT, total)
  - Status badges (Draft/Sent/Paid/Overdue)
  - Due date (with overdue warnings)
  - Actions (View/Download/Send/Mark Paid)

- **Filters:**
  - Status filter
  - Type filter
  - Date range
  - Export to PDF/CSV

- **Quick Actions:**
  - Send overdue reminders (3 invoices)
  - Generate monthly invoices (all vendors)
  - Export VAT report (this quarter)

**VAT Compliance:**
- Austrian VAT (20%) calculated
- Full breakdown shown
- Invoice preview
- PDF download
- Resend functionality

---

### ✅ **4. System Settings**
**Path:** System Settings

**Features:**
- **5 Tab System:**

#### **General:**
- **Languages:**
  - Default language selector
  - 11 supported languages (multi-select)
  - Checkboxes for: EN, DE, IT, FR, AR, TR, ZH, JA, SR, CS, ES

- **Currencies & Formats:**
  - Default currency (EUR/USD/GBP/CHF)
  - Time zone (Europe/Vienna)
  - Date format (DD.MM.YYYY)
  - Time format (24h/12h)

#### **Payments:**
- **Payment Providers:**
  - Stripe (toggle + API keys)
  - PayPal (toggle + credentials)
  - Cash on delivery (toggle)

- **Tip Configuration:**
  - Suggested percentages [5, 10, 15, 20]
  - Maximum tip percentage (50%)

#### **Taxes & Fees:**
- **VAT Rates by Country:**
  - Austria: 20%
  - Germany: 19%
  - Switzerland: 7.7%
  - Italy: 22%

- **Service Fee:**
  - Rate: 2.5%
  - Apply to: Subtotal/Total

#### **Notifications:**
- Email notifications (toggle)
- SMS notifications (toggle)
- Push notifications (toggle)

#### **Business Rules:**
- **Order Limits:**
  - Minimum: €10
  - Maximum: €1,000

- **Timeouts & Expiry:**
  - Order timeout: 30 minutes
  - QR code expiry: 365 days

- **Moderation:**
  - Review moderation required (toggle)
  - Auto-approve vendors (toggle)

---

### 🚧 **Placeholder Sections** (Coming Soon)

#### **Customer Management**
- All customers database
- Flagged customers moderation
- Customer profiles
- Activity tracking

#### **Subscription Management**
- Create/edit plans
- Active subscriptions
- Overdue tracking
- Upgrade/downgrade flows

#### **Reviews & Complaints**
- Review moderation queue
- Complaint resolution
- Internal notes
- Actions (hide/delete/warn)

#### **Content & CMS**
- Homepage editor
- Promotions management
- Static pages (About, Terms, Privacy)
- Translations interface
- WYSIWYG editor

#### **Admin Roles**
- Super Admin
- Finance Admin
- Support Admin
- Content Admin
- Role permissions matrix

---

## 🎨 **Design System**

### **Layout:**
- **Left Sidebar:**
  - Collapsible navigation
  - TAVLO logo with "ADMIN" badge
  - Badge counts (pending items)
  - Nested menu items

- **Top Bar:**
  - Global search
  - Notifications bell (with unread count)
  - Admin profile dropdown

### **Colors:**
- **Primary:** Purple (`#8b5cf6`) - Main actions
- **Success:** Green - Active, Paid, Approved
- **Warning:** Yellow - Pending, Trial
- **Danger:** Red - Suspended, Overdue, Rejected
- **Neutral:** Gray - Base UI

### **Components:**
- **Tables:** Dense, sortable, filterable, paginated
- **Badges:** Color-coded status indicators
- **Cards:** Stats widgets with icons
- **Charts:** Recharts library (Line, Bar, Pie)
- **Modals:** Confirmation dialogs
- **Tabs:** Horizontal navigation
- **Filters:** Dropdowns + search

### **Typography:**
- Clean, professional
- No marketing tone
- Direct admin language
- "Approve vendor" not "Approve this amazing vendor"

---

## 🔐 **Security & Permissions**

### **Role Types:**
1. **Super Admin:** Full access to everything
2. **Finance Admin:** Billing, invoices, subscriptions
3. **Support Admin:** Customer support, complaints
4. **Content Admin:** CMS, moderation, translations

### **Audit Logs:**
- Every admin action logged
- User ID, timestamp, action type
- Searchable history

### **Confirmations:**
- Destructive actions require confirmation
- Inline tooltips for complex settings
- Unsaved changes warning

---

## 📊 **Data Model**

### **Vendor:**
```typescript
{
  id: string;
  name: string;
  type: string; // 'restaurant' | 'cafe' | 'bar'
  city: string;
  country: string;
  status: 'active' | 'pending' | 'suspended';
  subscriptionPlan: 'Premium' | 'Standard' | 'Basic';
  subscriptionStatus: 'paid' | 'overdue' | 'trial';
  monthlyRevenue: number;
  rating: number;
  reviewCount: number;
  joinedDate: string;
  lastActive: string;
}
```

### **Invoice:**
```typescript
{
  id: string; // 'INV-2024-001234'
  vendorId: string;
  vendorName: string;
  type: 'Subscription' | 'One-time Service';
  plan: string;
  amount: number;
  vatAmount: number;
  totalAmount: number;
  status: 'draft' | 'sent' | 'paid' | 'overdue';
  issueDate: string;
  dueDate: string;
  paidDate: string | null;
  period: string;
}
```

---

## 🎯 **Admin Workflows**

### **1. Approve New Vendor:**
1. Dashboard → "5 vendors waiting"
2. Click "Review pending vendors"
3. Select vendor from list
4. Review 4 tabs (Details, Documents, Menu, Verification)
5. Add admin notes
6. Click "Approve & Activate"
7. Confirmation → Vendor activated
8. Auto-send welcome email

### **2. Generate Monthly Invoices:**
1. Billing → Vendor Invoices
2. Click "Generate monthly invoices"
3. System creates invoices for all active vendors
4. Invoices marked as "Draft"
5. Review & edit if needed
6. Click "Send" → Email to vendors
7. Track payment status

### **3. Handle Complaint:**
1. Dashboard → "12 open complaints"
2. Select complaint
3. View details (customer, vendor, issue)
4. Add internal notes
5. Contact customer/vendor
6. Mark as "Resolved"
7. Log resolution action

---

## 🚀 **Performance**

- **Tables:** Pagination (15 items/page)
- **Charts:** Optimized rendering with recharts
- **Search:** Client-side filtering for speed
- **Export:** CSV/PDF generation
- **Real-time:** Auto-refresh for metrics

---

## 📱 **Responsive**

- **Desktop-first** design (1440px+)
- Tablet support (768px+)
- Mobile view (collapsible sidebar)
- Touch-friendly controls

---

## 🧪 **Testing the Admin Dashboard**

### **Quick Test:**
1. Click mode switcher → **🛠️ Admin**
2. Explore Dashboard metrics
3. Navigate to Vendors → All Vendors
4. Click "View" on any vendor
5. Go to Billing → See invoice table
6. Check System Settings tabs
7. Test search, filters, pagination

### **Full Test:**
1. Approve a pending vendor
2. Create a manual invoice
3. Change system settings (watch "unsaved changes")
4. Export data to CSV
5. Test bulk actions
6. Navigate through all sidebar items

---

## 💡 **Pro Tips**

- **Keyboard Shortcuts:** Coming soon
- **Bulk Actions:** Select multiple rows for efficiency
- **Quick Search:** Cmd/Ctrl+K for global search
- **Notifications:** Click bell to see all alerts
- **Export:** Always available for tables
- **Filters:** Combine multiple filters for precision

---

## 🔄 **Future Enhancements**

- [ ] Advanced analytics dashboard
- [ ] Real-time notifications (WebSocket)
- [ ] Email template editor
- [ ] Automated workflows
- [ ] API key management
- [ ] Webhook configuration
- [ ] Audit log viewer
- [ ] Data export scheduler
- [ ] Custom reports builder
- [ ] Mobile app for admins

---

## 📞 **Support**

For admin dashboard issues:
- **Internal:** Contact dev team
- **Documentation:** See `/docs/admin`
- **Training:** Video tutorials available

---

**Built with:** React + TypeScript + Tailwind CSS + Recharts  
**Design:** Professional SaaS dashboard  
**Brand:** TAVLO Admin Panel  

---

🎉 **"I can run the entire Tavlo business from here."**
