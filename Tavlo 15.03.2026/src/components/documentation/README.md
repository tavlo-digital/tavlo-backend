# Tavlo Action Story Documentation

This directory contains comprehensive documentation of all Tavlo platform actions in the Action Story format, ready for export to Jira as development tasks.

## Overview

**Total Action Stories:** 92
- **Platform Actions:** 27 (Admin & System operations)
- **Customer Actions Part 1:** 35 (QR Landing → Basket Management)
- **Customer Actions Part 2:** 30 (Payment Flow → Account Management)

## File Structure

```
/components/documentation/
├── ActionStoryTemplate.tsx     # Action Story data structure definition
├── PlatformActions.tsx         # 27 platform/admin actions
├── CustomerActions_Part1.tsx   # 35 customer actions (early flow)
├── CustomerActions_Part2.tsx   # 30 customer actions (late flow)
└── README.md                   # This file

/utils/
└── generateJiraCSV.ts          # CSV generator with validation

/pages/
└── JiraCSVGenerator.tsx        # UI for CSV download
```

## Action Story Format

Each action follows this structure:

### ID Format: `TAV-[ACTOR]-[DOMAIN]-[NUMBER]`

**Actors:**
- `ADM` - Admin
- `VEN` - Vendor
- `CUS` - Customer
- `SYS` - System

**Domains:**
- `ACC` - Account/Access
- `LEG` - Legal/Compliance
- `BIL` - Billing
- `MEN` - Menu
- `INV` - Inventory
- `ORD` - Ordering
- `PAY` - Payment
- `REV` - Reviews
- `ADM` - Admin Operations
- `SYS` - System Operations

### Six Required Sections

1. **Trigger** - What initiates this action
2. **Pre-conditions** - What must be true before action can occur
3. **System Action** - What the system does
4. **UI Updates** - What the user sees
5. **Failure States** - What can go wrong
6. **Success Outcome** - Final state when successful

## Action Breakdown

### Platform Actions (27)

#### Vendor Onboarding (ACC Domain) - 5 actions
- `TAV-ADM-ACC-001` - Admin reviews vendor registration
- `TAV-ADM-ACC-002` - Admin approves vendor account
- `TAV-ADM-ACC-003` - Admin rejects vendor application
- `TAV-ADM-ACC-004` - Admin suspends vendor account
- `TAV-ADM-ACC-005` - Admin reactivates suspended vendor

#### Legal & Compliance (LEG Domain) - 3 actions
- `TAV-ADM-LEG-001` - Admin reviews vendor legal documents
- `TAV-ADM-LEG-002` - Admin flags expired vendor documents
- `TAV-ADM-LEG-003` - Admin enforces GDPR data deletion

#### Billing & Payments (BIL Domain) - 5 actions
- `TAV-ADM-BIL-001` - Admin monitors vendor subscription status
- `TAV-ADM-BIL-002` - Admin handles failed vendor subscription payment
- `TAV-ADM-BIL-003` - Admin reviews platform commission transactions
- `TAV-ADM-BIL-004` - Admin processes vendor payout
- `TAV-ADM-BIL-005` - Admin handles payment dispute

#### Content Moderation (ADM Domain) - 3 actions
- `TAV-ADM-ADM-001` - Admin reviews flagged customer review
- `TAV-ADM-ADM-002` - Admin removes policy-violating review
- `TAV-ADM-ADM-003` - Admin moderates vendor menu content

#### Customer Support (ADM Domain) - 2 actions
- `TAV-ADM-ADM-004` - Admin handles customer complaint escalation
- `TAV-ADM-ADM-005` - Admin issues refund on behalf of vendor

#### System Monitoring (SYS Domain) - 4 actions
- `TAV-ADM-SYS-001` - Admin monitors platform health metrics
- `TAV-ADM-SYS-002` - System auto-scales for high traffic
- `TAV-ADM-SYS-003` - Admin investigates payment processing failure
- `TAV-ADM-SYS-004` - Admin reviews audit logs

#### Analytics & Reporting (ADM Domain) - 3 actions
- `TAV-ADM-ADM-006` - Admin generates platform analytics report
- `TAV-ADM-ADM-007` - Admin identifies underperforming vendors
- `TAV-ADM-ADM-008` - Admin exports tax compliance report

### Customer Actions Part 1 (35)

#### QR Landing (ORD Domain) - 7 actions
- `TAV-CUS-ORD-001` to `TAV-CUS-ORD-007` - QR scanning, mode selection, language, accessibility, shared basket, promotions

#### Authentication (ACC Domain) - 6 actions
- `TAV-CUS-ACC-001` to `TAV-CUS-ACC-006` - Guest browsing, sign up, sign in, password reset, OAuth, guest checkout

#### Menu Browsing (ORD Domain) - 8 actions
- `TAV-CUS-ORD-008` to `TAV-CUS-ORD-015` - Categories, filters, search, AI recommendations, nutrition, availability

#### Dish Customization (ORD Domain) - 8 actions
- `TAV-CUS-ORD-016` to `TAV-CUS-ORD-023` - Dish details, ingredients, sizes, modifications, instructions, reviews

#### Basket Management (ORD Domain) - 6 actions
- `TAV-CUS-ORD-024` to `TAV-CUS-ORD-029` - View basket, edit items, remove items, validation, summary, promo codes

### Customer Actions Part 2 (30)

#### Payment Flow (PAY Domain) - 10 actions
- `TAV-CUS-PAY-001` to `TAV-CUS-PAY-010` - Checkout, payment methods, card entry, cash, split bill, VAT receipts, retries

#### Order Tracking (ORD Domain) - 8 actions
- `TAV-CUS-ORD-030` to `TAV-CUS-ORD-037` - Confirmation, status tracking, notifications, ETA, contact, cancellation, order history

#### Reviews (REV Domain) - 7 actions
- `TAV-CUS-REV-001` to `TAV-CUS-REV-007` - Review form, ratings, text, photos, submission, editing, deletion

#### Account Management (ACC Domain) - 5 actions
- `TAV-CUS-ACC-007` to `TAV-CUS-ACC-011` - Loyalty points, redemption, profile updates, saved restaurants, account deletion

## Jira Export

### CSV Format

The generated CSV includes these columns:
- **Issue Type**: Task (all stories)
- **Summary**: `TAV-XXX-YYY-000 Action Name`
- **Description**: Plain text with Trigger, System Action, Failure States
- **Project Key**: TAVLO
- **Epic Link**: Auto-mapped from domain code
- **Labels**: `action:tav-xxx-yyy-000,initiator,domain`
- **Priority**: High (payments/orders), Medium (setup), Low (reviews/content)

### Epic Mapping

| Domain Code | Epic Name |
|-------------|-----------|
| ACC | Vendor Onboarding |
| LEG | Vendor Onboarding |
| BIL | Vendor Subscription |
| MEN | Menu Management |
| INV | Inventory |
| ORD | Customer Ordering |
| PAY | Customer Ordering |
| REV | Reviews |
| ADM | Admin Control |
| SYS | Platform Core |

### Priority Logic

- **High**: Actions involving money, orders, payments, access control
- **Medium**: Setup, management, automation tasks
- **Low**: Reviews, content, non-critical features

## Usage

### Accessing the Generator

1. Run the Tavlo application
2. Open the mode switcher (bottom left corner)
3. Select "📊 Jira CSV Generator"
4. Click "Download CSV File" or "Copy to Clipboard"

### Importing to Jira

1. Download the CSV file
2. In Jira, go to your TAVLO project
3. Navigate to **Issues → Import issues from CSV**
4. Upload the file
5. Verify column mappings auto-detect correctly
6. Review and import all 92 tasks

## Validation

The CSV generator performs validation before export:
- ✅ Valid Action ID format (`TAV-[ACTOR]-[DOMAIN]-[NUMBER]`)
- ✅ No duplicate Action IDs
- ✅ No empty summaries
- ✅ No empty descriptions
- ✅ All required fields present

If validation fails, errors are displayed instead of generating the CSV.

## Development Notes

### Adding New Actions

1. Open the appropriate file:
   - Platform/Admin actions → `PlatformActions.tsx`
   - Customer early flow → `CustomerActions_Part1.tsx`
   - Customer late flow → `CustomerActions_Part2.tsx`

2. Add new action following the `ActionStory` interface:
   ```typescript
   {
     id: 'TAV-CUS-ORD-XXX',
     name: 'Customer does something',
     trigger: 'What starts this action',
     preconditions: 'What must be true',
     systemAction: 'What the system does',
     uiUpdates: 'What user sees',
     failureStates: 'What can go wrong',
     successOutcome: 'Final successful state'
   }
   ```

3. The CSV generator will automatically include it in the next export.

### Modifying Epic Mappings

Edit the `getEpicFromDomain` function in `/utils/generateJiraCSV.ts`

### Changing Priority Logic

Edit the `getPriority` function in `/utils/generateJiraCSV.ts`

## License

Part of the Tavlo platform documentation. Internal use only.
