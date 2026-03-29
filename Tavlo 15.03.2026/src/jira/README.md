# TAVLO Jira Documentation Files

This folder contains all Jira-compatible CSV files for importing TAVLO action stories into Jira.

## Main Files (Recommended for Import)

### **Complete Action Story CSVs**
1. **`TAVLO-ADMIN-ACTIONS-100-STORIES.csv`** ✅ **USE THIS**
   - 100 Admin action stories (10 epics + 100 stories)
   - Condensed descriptions optimized for CSV format
   - Complete coverage: Dashboard, Vendors, Customers, Finance, Moderation, Analytics, Subscriptions, Audit, Settings, Support

2. **`TAVLO-VENDOR-ACTIONS-COMPLETE-100.csv`** ✅ **USE THIS**
   - 85 Vendor action stories
   - Complete vendor journey from onboarding to operations

3. **`TAVLO-CUSTOMER-ACTIONS-COMPLETE-90.csv`** ✅ **USE THIS**
   - 89 Customer action stories  
   - Complete customer journey from discovery to loyalty

## Archive / Legacy Files

### Admin Files (Older Versions - DO NOT USE)
- `TAVLO-ADMIN-ACTIONS-COMPLETE-100.csv` - Older version with only 61 stories (72 rows total)
- `TAVLO-ADMIN-ACTIONS-COMPLETE-FINAL.csv` - Truncated version (incomplete)

### Customer Files (Legacy)
- `customer-actions-jira-complete.csv` - Older format
- `customer-actions-jira-import.csv` - Older format
- `tavlo-complete-customer-actions-jira.csv` - Older format
- `CUSTOMER_ACTIONS_FINAL_90+.csv` - Duplicate

### Mixed Content Files
- `tavlo-action-ledger-jira-import.csv` - Mixed vendor/customer/admin actions
- `action_stories.csv` - Earlier version

## File Naming Convention

**Format:** `TAVLO-[ACTOR]-ACTIONS-[COUNT]-STORIES.csv`

**Examples:**
- `TAVLO-ADMIN-ACTIONS-100-STORIES.csv` → 100 admin stories
- `TAVLO-VENDOR-ACTIONS-COMPLETE-100.csv` → 85 vendor stories  
- `TAVLO-CUSTOMER-ACTIONS-COMPLETE-90.csv` → 89 customer stories

## Story ID Format

**Format:** `TAV-[ACTOR]-[DOMAIN]-[NUMBER]`

**Examples:**
- `TAV-ADM-DAS-001` → Admin Dashboard Story 001
- `TAV-VEN-ONB-001` → Vendor Onboarding Story 001
- `TAV-CUS-ORD-001` → Customer Ordering Story 001

## Total Platform Coverage

- **Admin Stories:** 100 stories across 10 epics
- **Vendor Stories:** 85 stories across 9 epics
- **Customer Stories:** 89 stories across 10 epics
- **TOTAL:** 274 action stories covering the complete TAVLO platform

## Import Instructions

1. Use the **3 main files** listed above (marked with ✅)
2. Import each CSV separately into Jira
3. Epics will be created first, then stories will be linked to their parent epics
4. All stories include:
   - Work Item ID (unique identifier)
   - Summary (action name)
   - Issue Type (Epic or Story)
   - Priority (High/Medium/Low)
   - Parent (epic link for stories)
   - Labels (domain tags)
   - Description (flow, success criteria, impact)

## Documentation

For detailed journey narratives, see the `/documentation` folder:
- Vendor Journey: Marco's restaurant transformation
- Customer Journey: Sarah's ordering experience
- Admin System: Platform governance and operations

---

**Last Updated:** January 2026  
**Platform:** TAVLO - QR Restaurant Ordering Platform  
**Coverage:** 274 action stories across customer, vendor, and admin domains
