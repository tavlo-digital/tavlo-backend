import { ActionStory } from './ActionStoryTemplate';

/**
 * Platform Actions - Admin & System (27 actions)
 * Platform manages vendors, billing, compliance, and system operations
 */

export const platformActions: ActionStory[] = [
  // VENDOR ONBOARDING (ACC Domain)
  {
    id: 'TAV-ADM-ACC-001',
    name: 'Admin reviews vendor registration',
    trigger: 'New vendor completes registration form',
    preconditions: 'Vendor submitted all required business information',
    systemAction: 'System flags application for admin review, validates submitted documents',
    uiUpdates: 'Admin sees pending approval in dashboard, vendor status shows "Under Review"',
    failureStates: 'Missing documents, invalid business registration, duplicate restaurant',
    successOutcome: 'Vendor application queued for admin decision'
  },
  {
    id: 'TAV-ADM-ACC-002',
    name: 'Admin approves vendor account',
    trigger: 'Admin clicks "Approve" on vendor application',
    preconditions: 'All documents verified, no policy violations',
    systemAction: 'System activates vendor account, sends welcome email, creates Stripe account link',
    uiUpdates: 'Vendor receives email with onboarding next steps, admin sees approved status',
    failureStates: 'Email delivery fails, Stripe connection error',
    successOutcome: 'Vendor account active, onboarding flow unlocked'
  },
  {
    id: 'TAV-ADM-ACC-003',
    name: 'Admin rejects vendor application',
    trigger: 'Admin clicks "Reject" with reason',
    preconditions: 'Policy violation or incomplete information detected',
    systemAction: 'System marks application rejected, logs reason, sends notification to vendor',
    uiUpdates: 'Vendor sees rejection notice with specific reasons, admin sees closed application',
    failureStates: 'Notification delivery fails',
    successOutcome: 'Vendor notified of rejection, can reapply after addressing issues'
  },
  {
    id: 'TAV-ADM-ACC-004',
    name: 'Admin suspends vendor account',
    trigger: 'Admin initiates suspension due to policy violation',
    preconditions: 'Active vendor account, valid suspension reason documented',
    systemAction: 'System disables vendor dashboard access, hides restaurant from customer app, notifies vendor',
    uiUpdates: 'Vendor sees suspension notice on login, customers cannot access restaurant',
    failureStates: 'Active orders exist, notification fails',
    successOutcome: 'Vendor suspended, no new orders accepted, existing orders completed'
  },
  {
    id: 'TAV-ADM-ACC-005',
    name: 'Admin reactivates suspended vendor',
    trigger: 'Admin lifts suspension after issue resolved',
    preconditions: 'Vendor was suspended, compliance issues addressed',
    systemAction: 'System restores vendor access, makes restaurant visible again, sends reactivation notice',
    uiUpdates: 'Vendor regains dashboard access, restaurant appears in customer searches',
    failureStates: 'Outstanding compliance issues remain',
    successOutcome: 'Vendor fully operational, accepting orders'
  },

  // LEGAL & COMPLIANCE (LEG Domain)
  {
    id: 'TAV-ADM-LEG-001',
    name: 'Admin reviews vendor legal documents',
    trigger: 'Vendor uploads business license, tax certificates, insurance',
    preconditions: 'Vendor in onboarding, documents submitted',
    systemAction: 'System validates document formats, flags expiration dates, queues for admin review',
    uiUpdates: 'Admin sees document review queue, vendor sees "Under Review" status',
    failureStates: 'Invalid file format, expired documents, missing required fields',
    successOutcome: 'Documents validated and archived for compliance'
  },
  {
    id: 'TAV-ADM-LEG-002',
    name: 'Admin flags expired vendor documents',
    trigger: 'System detects document expiring within 30 days',
    preconditions: 'Vendor has active account with tracked documents',
    systemAction: 'System sends warning to vendor, creates admin alert, logs compliance issue',
    uiUpdates: 'Admin sees compliance alert, vendor receives renewal reminder',
    failureStates: 'Notification delivery fails',
    successOutcome: 'Vendor notified to renew before expiration'
  },
  {
    id: 'TAV-ADM-LEG-003',
    name: 'Admin enforces GDPR data deletion',
    trigger: 'Customer or vendor requests account deletion (right to be forgotten)',
    preconditions: 'Valid deletion request received',
    systemAction: 'System anonymizes personal data, archives required transaction records, deletes unnecessary data',
    uiUpdates: 'Admin sees deletion confirmation, requestor receives confirmation email',
    failureStates: 'Active orders prevent deletion, legal hold on data',
    successOutcome: 'Personal data deleted, legal records retained per regulations'
  },

  // BILLING & PAYMENTS (BIL Domain)
  {
    id: 'TAV-ADM-BIL-001',
    name: 'Admin monitors vendor subscription status',
    trigger: 'Admin views vendor billing dashboard',
    preconditions: 'Vendors have active subscriptions',
    systemAction: 'System displays subscription tiers, payment status, upcoming renewals',
    uiUpdates: 'Admin sees real-time subscription health, overdue payments highlighted',
    failureStates: 'Billing data sync fails',
    successOutcome: 'Admin has visibility into all vendor subscriptions'
  },
  {
    id: 'TAV-ADM-BIL-002',
    name: 'Admin handles failed vendor subscription payment',
    trigger: 'Vendor subscription payment fails',
    preconditions: 'Payment method declined or expired',
    systemAction: 'System retries payment, sends vendor notification, flags account for admin review',
    uiUpdates: 'Vendor sees payment failure notice, admin sees flagged account',
    failureStates: 'All retry attempts fail, vendor unresponsive',
    successOutcome: 'Vendor updates payment method and subscription continues'
  },
  {
    id: 'TAV-ADM-BIL-003',
    name: 'Admin reviews platform commission transactions',
    trigger: 'Admin accesses financial reports',
    preconditions: 'Customer orders processed through platform',
    systemAction: 'System calculates commission from each order, aggregates by vendor and period',
    uiUpdates: 'Admin sees commission breakdown, Stripe payout schedule',
    failureStates: 'Transaction data incomplete',
    successOutcome: 'Accurate financial reporting for platform revenue'
  },
  {
    id: 'TAV-ADM-BIL-004',
    name: 'Admin processes vendor payout',
    trigger: 'End of payout period (daily/weekly based on vendor tier)',
    preconditions: 'Orders completed, refund period passed',
    systemAction: 'System calculates vendor earnings minus commission, initiates Stripe transfer',
    uiUpdates: 'Vendor sees payout in dashboard, admin sees transfer confirmation',
    failureStates: 'Insufficient funds, Stripe account issue, pending disputes',
    successOutcome: 'Vendor receives payment for completed orders'
  },
  {
    id: 'TAV-ADM-BIL-005',
    name: 'Admin handles payment dispute',
    trigger: 'Customer initiates chargeback or dispute',
    preconditions: 'Payment processed through Stripe',
    systemAction: 'System holds vendor payout, notifies admin and vendor, collects evidence',
    uiUpdates: 'Admin sees dispute details, vendor receives notice to provide evidence',
    failureStates: 'Evidence deadline missed, insufficient documentation',
    successOutcome: 'Dispute resolved with decision, funds released or refunded'
  },

  // CONTENT MODERATION (ADM Domain)
  {
    id: 'TAV-ADM-ADM-001',
    name: 'Admin reviews flagged customer review',
    trigger: 'Review flagged by AI or vendor as inappropriate',
    preconditions: 'Review published, flag submitted with reason',
    systemAction: 'System queues review for admin moderation, temporarily hides from public if severe',
    uiUpdates: 'Admin sees moderation queue with review content and flag reason',
    failureStates: 'Insufficient context to make decision',
    successOutcome: 'Admin approves or removes review based on platform policies'
  },
  {
    id: 'TAV-ADM-ADM-002',
    name: 'Admin removes policy-violating review',
    trigger: 'Admin confirms review violates content policy',
    preconditions: 'Review contains hate speech, spam, or false information',
    systemAction: 'System deletes review, notifies author, logs moderation action',
    uiUpdates: 'Review disappears from restaurant page, author receives policy violation notice',
    failureStates: 'Notification delivery fails',
    successOutcome: 'Violating content removed, user warned'
  },
  {
    id: 'TAV-ADM-ADM-003',
    name: 'Admin moderates vendor menu content',
    trigger: 'Vendor uploads menu with potentially problematic content',
    preconditions: 'AI flags dish names or descriptions as inappropriate',
    systemAction: 'System holds menu update for review, notifies vendor of flagged items',
    uiUpdates: 'Admin sees flagged menu items, vendor sees pending approval status',
    failureStates: 'False positive flags, ambiguous content',
    successOutcome: 'Menu approved or vendor requested to revise content'
  },

  // CUSTOMER SUPPORT (ADM Domain)
  {
    id: 'TAV-ADM-ADM-004',
    name: 'Admin handles customer complaint escalation',
    trigger: 'Customer escalates unresolved issue to platform support',
    preconditions: 'Customer contacted vendor first, issue remains unresolved',
    systemAction: 'System creates support ticket, pulls order history, notifies admin',
    uiUpdates: 'Admin sees ticket with full context, customer receives confirmation',
    failureStates: 'Missing order data, customer contact info invalid',
    successOutcome: 'Admin mediates between customer and vendor to resolve issue'
  },
  {
    id: 'TAV-ADM-ADM-005',
    name: 'Admin issues refund on behalf of vendor',
    trigger: 'Admin determines customer deserves refund, vendor unresponsive',
    preconditions: 'Valid complaint, vendor failed to respond within SLA',
    systemAction: 'System processes refund through Stripe, deducts from vendor balance',
    uiUpdates: 'Customer receives refund notification, vendor sees deduction with explanation',
    failureStates: 'Vendor account insufficient balance, refund window expired',
    successOutcome: 'Customer refunded, vendor account adjusted, case closed'
  },

  // SYSTEM MONITORING (SYS Domain)
  {
    id: 'TAV-ADM-SYS-001',
    name: 'Admin monitors platform health metrics',
    trigger: 'Admin accesses system dashboard',
    preconditions: 'Platform operational',
    systemAction: 'System displays real-time metrics: uptime, API response times, error rates',
    uiUpdates: 'Admin sees health indicators, alerts for anomalies',
    failureStates: 'Monitoring service down',
    successOutcome: 'Admin has visibility into platform performance'
  },
  {
    id: 'TAV-ADM-SYS-002',
    name: 'System auto-scales for high traffic',
    trigger: 'Order volume exceeds normal threshold',
    preconditions: 'Infrastructure supports auto-scaling',
    systemAction: 'System provisions additional server capacity, balances load',
    uiUpdates: 'Admin sees scaling event logged, no customer-facing changes',
    failureStates: 'Scaling limit reached, infrastructure quota exceeded',
    successOutcome: 'Platform handles traffic spike without degradation'
  },
  {
    id: 'TAV-ADM-SYS-003',
    name: 'Admin investigates payment processing failure',
    trigger: 'Multiple payment failures detected in short period',
    preconditions: 'Stripe integration active',
    systemAction: 'System aggregates failure reasons, checks Stripe status, alerts admin',
    uiUpdates: 'Admin sees failure dashboard with root cause analysis',
    failureStates: 'Insufficient logging data',
    successOutcome: 'Admin identifies issue (Stripe outage, configuration error) and communicates status'
  },
  {
    id: 'TAV-ADM-SYS-004',
    name: 'Admin reviews audit logs',
    trigger: 'Admin accesses audit log for compliance or investigation',
    preconditions: 'All admin actions logged',
    systemAction: 'System displays filterable log of all admin actions with timestamps and actors',
    uiUpdates: 'Admin sees comprehensive activity trail',
    failureStates: 'Log data corrupted or incomplete',
    successOutcome: 'Admin can trace all platform administrative actions for accountability'
  },

  // ANALYTICS & REPORTING (ADM Domain)
  {
    id: 'TAV-ADM-ADM-006',
    name: 'Admin generates platform analytics report',
    trigger: 'Admin requests metrics report (weekly/monthly)',
    preconditions: 'Sufficient transaction data exists',
    systemAction: 'System aggregates orders, revenue, customer growth, vendor performance',
    uiUpdates: 'Admin sees visual dashboard with exportable data',
    failureStates: 'Data aggregation timeout, incomplete records',
    successOutcome: 'Comprehensive report for business intelligence'
  },
  {
    id: 'TAV-ADM-ADM-007',
    name: 'Admin identifies underperforming vendors',
    trigger: 'Admin reviews vendor performance metrics',
    preconditions: 'Vendors have been active for minimum evaluation period',
    systemAction: 'System calculates order volume, customer ratings, complaint rate per vendor',
    uiUpdates: 'Admin sees ranked list with red flags for intervention',
    failureStates: 'Insufficient data for new vendors',
    successOutcome: 'Admin can provide support or warnings to struggling vendors'
  },
  {
    id: 'TAV-ADM-ADM-008',
    name: 'Admin exports tax compliance report',
    trigger: 'Admin prepares for tax filing or audit',
    preconditions: 'VAT properly tracked on all orders',
    systemAction: 'System generates report showing total VAT collected, broken down by rate and vendor',
    uiUpdates: 'Admin downloads CSV/PDF with all required tax information',
    failureStates: 'VAT calculation errors, missing transaction records',
    successOutcome: 'Tax-ready report for Austrian compliance requirements'
  }
];
