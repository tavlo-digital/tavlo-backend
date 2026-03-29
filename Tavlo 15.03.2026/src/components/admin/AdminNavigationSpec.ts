/**
 * TAVLO ADMIN DASHBOARD - NAVIGATION SPECIFICATION
 * 
 * This document defines the exact click behavior and routing logic for every
 * clickable element in the Admin Dashboard.
 * 
 * CORE RULE:
 * Every clickable element MUST route to an existing admin base page with 
 * pre-applied filters or direct entity context.
 * 
 * ALLOWED DESTINATIONS:
 * - Vendors
 * - Customers
 * - Billing & Invoices
 * - Subscriptions
 * - Reviews & Complaints
 * - Audit Log
 * - Insights & Analysis
 * 
 * FORBIDDEN DESTINATIONS:
 * - Another dashboard
 * - Chart-only views
 * - Empty pages
 * - Settings pages
 * 
 * Admins act on LISTS and ENTITY DETAIL PAGES, never summaries.
 */

// ============================================================================
// TYPE DEFINITIONS
// ============================================================================

export type AdminDestination = 
  | 'vendors'
  | 'customers'
  | 'billing'
  | 'subscriptions'
  | 'reviews'
  | 'audit-log'
  | 'ai-insights';

export type VendorStatus = 'active' | 'inactive' | 'pending' | 'suspended';
export type PaymentStatus = 'completed' | 'failed' | 'pending' | 'refunded';
export type OnboardingStatus = 'complete' | 'incomplete' | 'pending-approval';
export type KYCStatus = 'approved' | 'failed' | 'pending';
export type RefundStatus = 'pending-approval' | 'approved' | 'rejected' | 'processed';
export type DisputeStatus = 'open' | 'closed' | 'won' | 'lost';
export type ReviewStatus = 'flagged' | 'approved' | 'rejected';
export type SubscriptionStatus = 'active' | 'expired' | 'cancelled';

export interface NavigationContext {
  destination: AdminDestination;
  filters?: Record<string, any>;
  entityId?: string;
  entityType?: 'vendor' | 'customer' | 'order' | 'payment' | 'subscription' | 'review';
  tab?: string;
  expectedColumns?: string[];
  purpose: string;
}

// ============================================================================
// 1. PLATFORM HEALTH CARDS — ROUTING DEFINITION
// ============================================================================

export const PlatformHealthCardNavigation = {
  /**
   * ACTIVE VENDORS TODAY
   * 
   * Click Target: "Active Vendors Today" KPI card
   * Destination: Vendors page
   * Pre-applied Filters:
   *   - Vendor status: Active
   *   - Activity: At least one order or menu activity today
   * Expected List View Columns:
   *   - Vendor name
   *   - Vendor status
   *   - Orders today
   *   - Last activity timestamp
   * Purpose: Identify which vendors are currently active and operational
   */
  activeVendorsToday: {
    destination: 'vendors' as AdminDestination,
    filters: {
      status: 'active' as VendorStatus,
      hasActivityToday: true,
      dateRange: 'today'
    },
    expectedColumns: [
      'vendorName',
      'vendorStatus',
      'ordersToday',
      'lastActivityTimestamp'
    ],
    purpose: 'Identify which vendors are currently active and operational'
  },

  /**
   * ACTIVE CUSTOMERS TODAY
   * 
   * Click Target: "Active Customers Today" KPI card
   * Destination: Customers page
   * Pre-applied Filters:
   *   - Last activity date: Today
   * Expected List View Columns:
   *   - Customer ID
   *   - Last order ID
   *   - Vendor
   *   - Payment status
   * Purpose: See who is actively using the platform today
   */
  activeCustomersToday: {
    destination: 'customers' as AdminDestination,
    filters: {
      lastActivityDate: 'today',
      hasOrderToday: true
    },
    expectedColumns: [
      'customerId',
      'lastOrderId',
      'vendor',
      'paymentStatus'
    ],
    purpose: 'See who is actively using the platform today'
  },

  /**
   * ORDERS TODAY
   * 
   * Click Target: "Orders Today" KPI card
   * Destination: Billing & Invoices page
   * Pre-applied Filters:
   *   - Order date: Today
   * Expected List View Columns:
   *   - Order ID
   *   - Vendor
   *   - Order amount
   *   - Payment status
   *   - Fulfillment type (dine-in, takeaway, etc.)
   * Purpose: Operational monitoring of today's order flow
   */
  ordersToday: {
    destination: 'billing' as AdminDestination,
    filters: {
      orderDate: 'today',
      entityType: 'orders'
    },
    expectedColumns: [
      'orderId',
      'vendor',
      'orderAmount',
      'paymentStatus',
      'fulfillmentType'
    ],
    purpose: 'Operational monitoring of today\'s order flow'
  },

  /**
   * GMV TODAY
   * 
   * Click Target: "GMV Today" KPI card
   * Destination: Insights & Analysis page
   * Pre-applied Filters:
   *   - Time range: Today
   *   - Metric focus: GMV
   * Expected View:
   *   - GMV broken down by vendor
   *   - Payment method split
   * Purpose: Financial overview only. No row-level actions required here.
   */
  gmvToday: {
    destination: 'ai-insights' as AdminDestination,
    filters: {
      timeRange: 'today',
      metric: 'gmv',
      viewType: 'breakdown'
    },
    expectedColumns: [
      'vendor',
      'gmv',
      'paymentMethod',
      'transactionCount'
    ],
    purpose: 'Financial overview only. No row-level actions required here.'
  },

  /**
   * FAILED PAYMENTS (24H)
   * 
   * Click Target: "Failed Payments (24h)" KPI card
   * Destination: Billing & Invoices page
   * Pre-applied Filters:
   *   - Payment status: Failed
   *   - Time range: Last 24 hours
   * Expected List View Columns:
   *   - Payment ID
   *   - Order ID
   *   - Vendor
   *   - PSP error reason
   *   - Retry status
   * Purpose: Immediate revenue risk resolution
   */
  failedPayments24h: {
    destination: 'billing' as AdminDestination,
    filters: {
      paymentStatus: 'failed' as PaymentStatus,
      timeRange: 'last-24-hours',
      entityType: 'payments'
    },
    expectedColumns: [
      'paymentId',
      'orderId',
      'vendor',
      'pspErrorReason',
      'retryStatus'
    ],
    purpose: 'Immediate revenue risk resolution'
  },

  /**
   * OPEN SUPPORT TICKETS
   * 
   * Click Target: "Open Support Tickets" KPI card
   * Destination: Reviews & Complaints page
   * Pre-applied Filters:
   *   - Ticket status: Open
   * Expected List View Columns:
   *   - Ticket ID
   *   - Category
   *   - Vendor or Customer reference
   *   - Open duration
   * Purpose: Support backlog visibility
   */
  openSupportTickets: {
    destination: 'reviews' as AdminDestination,
    filters: {
      ticketStatus: 'open',
      entityType: 'support-tickets'
    },
    expectedColumns: [
      'ticketId',
      'category',
      'entityReference',
      'openDuration'
    ],
    purpose: 'Support backlog visibility'
  }
};

// ============================================================================
// 2. ALERTS & INCIDENTS — ROUTING DEFINITION
// ============================================================================

/**
 * CORE PRINCIPLE:
 * Alerts never route to dashboards or charts.
 * They always route to filtered lists or entity detail pages.
 */

export const AlertsNavigation = {
  /**
   * PAYMENT FAILURE SPIKE DETECTED
   * 
   * Primary CTA ("View Vendor"):
   *   Destination: Vendors → Vendor Detail Page → Payments tab
   *   Context: Payments filtered to failed + alert timeframe
   * 
   * Secondary Click (alert body):
   *   Destination: Billing & Invoices → Failed payments
   *   Filters: By vendor and alert timeframe
   */
  paymentFailureSpike: {
    primaryAction: {
      destination: 'vendors' as AdminDestination,
      entityId: 'VID-8492', // From alert context
      entityType: 'vendor' as const,
      tab: 'payments',
      filters: {
        paymentStatus: 'failed' as PaymentStatus,
        timeRange: 'alert-timeframe'
      },
      purpose: 'Investigate specific vendor payment issues'
    },
    secondaryAction: {
      destination: 'billing' as AdminDestination,
      filters: {
        paymentStatus: 'failed' as PaymentStatus,
        vendorId: 'VID-8492',
        timeRange: 'alert-timeframe',
        entityType: 'payments'
      },
      expectedColumns: [
        'paymentId',
        'orderId',
        'amount',
        'pspErrorReason',
        'timestamp'
      ],
      purpose: 'Review all failed payments for this vendor'
    }
  },

  /**
   * VENDOR ONBOARDING STUCK
   * 
   * Click Target: "Review Queue" CTA
   * Destination: Vendors page
   * Pre-applied Filters:
   *   - Onboarding status: Incomplete
   *   - Time in onboarding: Greater than defined threshold
   * Expected List View Columns:
   *   - Vendor name
   *   - Missing onboarding step
   *   - Time stuck
   *   - Assigned admin
   * Purpose: Resolve onboarding bottlenecks
   */
  vendorOnboardingStuck: {
    destination: 'vendors' as AdminDestination,
    filters: {
      onboardingStatus: 'incomplete' as OnboardingStatus,
      timeStuckThreshold: '24-hours',
      sortBy: 'timeStuck-desc'
    },
    expectedColumns: [
      'vendorName',
      'missingStep',
      'timeStuck',
      'assignedAdmin'
    ],
    purpose: 'Resolve onboarding bottlenecks'
  },

  /**
   * SUBSCRIPTION EXPIRED BUT ACTIVE
   * 
   * Click Target: "Suspend Access" CTA
   * Destination: Subscriptions page
   * Pre-applied Filters:
   *   - Subscription status: Expired
   *   - Vendor status: Active
   * Expected List View Columns:
   *   - Vendor name
   *   - Subscription plan
   *   - Expiry date
   *   - Revenue at risk indicator
   * Purpose: Prevent revenue leakage
   */
  subscriptionExpiredButActive: {
    destination: 'subscriptions' as AdminDestination,
    filters: {
      subscriptionStatus: 'expired' as SubscriptionStatus,
      vendorStatus: 'active' as VendorStatus,
      sortBy: 'expiryDate-asc'
    },
    expectedColumns: [
      'vendorName',
      'subscriptionPlan',
      'expiryDate',
      'revenueAtRisk'
    ],
    purpose: 'Prevent revenue leakage'
  }
};

// ============================================================================
// 3. ACTION QUEUES — ROUTING DEFINITION
// ============================================================================

/**
 * CORE PRINCIPLE:
 * Action queues always route to queue-based filtered list views, not dashboards.
 */

export const ActionQueuesNavigation = {
  /**
   * VENDORS PENDING APPROVAL
   * 
   * Click Target: Action queue card
   * Destination: Vendors page
   * Pre-applied Filters:
   *   - Approval status: Pending
   */
  vendorsPendingApproval: {
    destination: 'vendors' as AdminDestination,
    filters: {
      approvalStatus: 'pending',
      onboardingStatus: 'pending-approval' as OnboardingStatus,
      sortBy: 'submittedDate-asc'
    },
    expectedColumns: [
      'vendorName',
      'businessType',
      'submittedDate',
      'kycStatus',
      'assignedReviewer'
    ],
    purpose: 'Review and approve pending vendor applications'
  },

  /**
   * KYC VERIFICATION FAILED
   * 
   * Click Target: Action queue card
   * Destination: Vendors page
   * Pre-applied Filters:
   *   - KYC status: Failed
   */
  kycVerificationFailed: {
    destination: 'vendors' as AdminDestination,
    filters: {
      kycStatus: 'failed' as KYCStatus,
      sortBy: 'failureDate-desc'
    },
    expectedColumns: [
      'vendorName',
      'failureReason',
      'failureDate',
      'attemptCount',
      'assignedAdmin'
    ],
    purpose: 'Review and assist with failed KYC verifications'
  },

  /**
   * REFUNDS AWAITING APPROVAL
   * 
   * Click Target: Action queue card
   * Destination: Billing & Invoices page
   * Pre-applied Filters:
   *   - Refund status: Pending approval
   */
  refundsAwaitingApproval: {
    destination: 'billing' as AdminDestination,
    filters: {
      refundStatus: 'pending-approval' as RefundStatus,
      entityType: 'refunds',
      sortBy: 'requestDate-asc'
    },
    expectedColumns: [
      'refundId',
      'orderId',
      'vendor',
      'amount',
      'reason',
      'requestDate'
    ],
    purpose: 'Approve or reject customer refund requests'
  },

  /**
   * OPEN DISPUTES
   * 
   * Click Target: Action queue card
   * Destination: Billing & Invoices page
   * Pre-applied Filters:
   *   - Dispute status: Open
   */
  openDisputes: {
    destination: 'billing' as AdminDestination,
    filters: {
      disputeStatus: 'open' as DisputeStatus,
      entityType: 'disputes',
      sortBy: 'urgency-desc'
    },
    expectedColumns: [
      'disputeId',
      'paymentId',
      'vendor',
      'amount',
      'disputeType',
      'deadline'
    ],
    purpose: 'Manage active chargebacks and payment disputes'
  },

  /**
   * FLAGGED REVIEWS
   * 
   * Click Target: Action queue card
   * Destination: Reviews & Complaints page
   * Pre-applied Filters:
   *   - Review status: Flagged
   */
  flaggedReviews: {
    destination: 'reviews' as AdminDestination,
    filters: {
      reviewStatus: 'flagged' as ReviewStatus,
      entityType: 'reviews',
      sortBy: 'flagDate-desc'
    },
    expectedColumns: [
      'reviewId',
      'vendor',
      'flagReason',
      'flagDate',
      'reviewerCount'
    ],
    purpose: 'Moderate flagged review content'
  },

  /**
   * CONTENT MODERATION NEEDED
   * 
   * Click Target: Action queue card
   * Destination: Vendors page
   * Pre-applied Filters:
   *   - Content flagged: Yes
   */
  contentModerationNeeded: {
    destination: 'vendors' as AdminDestination,
    filters: {
      contentFlagged: true,
      moderationStatus: 'pending',
      sortBy: 'flagDate-desc'
    },
    expectedColumns: [
      'vendorName',
      'contentType',
      'flagReason',
      'flagDate',
      'moderator'
    ],
    purpose: 'Review user-reported menu items or vendor profiles'
  }
};

// ============================================================================
// 4. LAST 24H ACTIVITY FEED — ROUTING DEFINITION
// ============================================================================

/**
 * CORE PRINCIPLE:
 * Each activity row links directly to the relevant entity detail page, never a summary.
 */

export const ActivityFeedNavigation = {
  /**
   * VENDOR CREATED
   * 
   * Click Target: Activity row
   * Destination: Vendor detail page
   */
  vendorCreated: (vendorId: string): NavigationContext => ({
    destination: 'vendors',
    entityId: vendorId,
    entityType: 'vendor',
    purpose: 'View newly created vendor details'
  }),

  /**
   * PAYMENT FAILED
   * 
   * Click Target: Activity row
   * Destination: Payment detail page (within Billing & Invoices)
   */
  paymentFailed: (paymentId: string): NavigationContext => ({
    destination: 'billing',
    entityId: paymentId,
    entityType: 'payment',
    expectedColumns: [
      'paymentId',
      'orderId',
      'amount',
      'pspErrorReason',
      'retryAttempts'
    ],
    purpose: 'Investigate payment failure'
  }),

  /**
   * PAYMENT SUCCESSFUL
   * 
   * Click Target: Activity row
   * Destination: Payment detail page
   */
  paymentSuccessful: (paymentId: string): NavigationContext => ({
    destination: 'billing',
    entityId: paymentId,
    entityType: 'payment',
    purpose: 'View successful payment details'
  }),

  /**
   * VENDOR UNSUBSCRIBED
   * 
   * Click Target: Activity row
   * Destination: Vendor detail page → Subscription tab
   */
  vendorUnsubscribed: (vendorId: string): NavigationContext => ({
    destination: 'vendors',
    entityId: vendorId,
    entityType: 'vendor',
    tab: 'subscription',
    purpose: 'Review vendor subscription change'
  }),

  /**
   * MENU PUBLISHED
   * 
   * Click Target: Activity row
   * Destination: Vendor detail page → Menu tab
   */
  menuPublished: (vendorId: string): NavigationContext => ({
    destination: 'vendors',
    entityId: vendorId,
    entityType: 'vendor',
    tab: 'menu',
    purpose: 'View published menu items'
  }),

  /**
   * PAYMENT REFUNDED
   * 
   * Click Target: Activity row
   * Destination: Refund detail page
   */
  paymentRefunded: (paymentId: string): NavigationContext => ({
    destination: 'billing',
    entityId: paymentId,
    entityType: 'payment',
    tab: 'refund',
    purpose: 'View refund details'
  }),

  /**
   * REVIEW FLAGGED
   * 
   * Click Target: Activity row
   * Destination: Review detail page
   */
  reviewFlagged: (reviewId: string): NavigationContext => ({
    destination: 'reviews',
    entityId: reviewId,
    entityType: 'review',
    purpose: 'Moderate flagged review'
  }),

  /**
   * VENDOR ACTIVATED
   * 
   * Click Target: Activity row
   * Destination: Vendor detail page → Subscription tab
   */
  vendorActivated: (vendorId: string): NavigationContext => ({
    destination: 'vendors',
    entityId: vendorId,
    entityType: 'vendor',
    tab: 'subscription',
    purpose: 'View activated vendor subscription'
  })
};

// ============================================================================
// 5. SYSTEM STATUS INDICATOR — ROUTING DEFINITION
// ============================================================================

/**
 * SYSTEM STATUS INDICATOR
 * 
 * Click Target: System status indicator in top bar
 * Destination: System Status Detail Page (part of ai-insights or dedicated page)
 * Displayed Information:
 *   - PSP availability
 *   - Webhook health
 *   - Notification delivery status
 * Purpose: Platform confidence and incident validation
 */
export const SystemStatusNavigation = {
  destination: 'ai-insights' as AdminDestination,
  filters: {
    view: 'system-status'
  },
  expectedColumns: [
    'service',
    'status',
    'uptime',
    'lastIncident',
    'responseTime'
  ],
  purpose: 'Platform confidence and incident validation'
};

// ============================================================================
// 6. QUICK TRENDS — ROUTING DEFINITION
// ============================================================================

export const QuickTrendsNavigation = {
  /**
   * ORDERS (24H)
   * 
   * Click Target: Orders trend card
   * Destination: Insights & Analysis page
   * Pre-applied Filters:
   *   - Time range: Last 24 hours
   *   - Metric: Order volume
   */
  orders24h: {
    destination: 'ai-insights' as AdminDestination,
    filters: {
      timeRange: 'last-24-hours',
      metric: 'orders',
      viewType: 'trend-analysis'
    },
    purpose: 'Analyze order volume trends'
  },

  /**
   * PAYMENT SUCCESS RATE
   * 
   * Click Target: Payment success rate card
   * Destination: Insights & Analysis page
   * Pre-applied Filters:
   *   - Metric: Payment success rate
   *   - Time range: Last 24 hours
   */
  paymentSuccessRate: {
    destination: 'ai-insights' as AdminDestination,
    filters: {
      metric: 'payment-success-rate',
      timeRange: 'last-24-hours',
      viewType: 'breakdown'
    },
    purpose: 'Analyze payment performance and failure patterns'
  }
};

// ============================================================================
// 7. EXPLICIT ANTI-PATTERNS (MUST BE PREVENTED)
// ============================================================================

/**
 * The following behaviors are NOT ALLOWED and should be prevented:
 * 
 * ❌ Clicking any dashboard element reloads the dashboard
 * ❌ Clicking any element opens a chart-only modal
 * ❌ Clicking any element opens system settings
 * ❌ Clicking any element results in an empty or unfiltered list
 * 
 * ✅ If a click does not result in a filtered list or entity detail, it is INCORRECT.
 */

export const NavigationAntiPatterns = {
  forbidden: [
    'Reload dashboard',
    'Open chart-only modal',
    'Navigate to settings',
    'Show unfiltered list',
    'Open summary view without actions',
    'Display analytics without drill-down capability'
  ],
  required: [
    'Navigate to filtered list view',
    'Navigate to entity detail page',
    'Apply contextual filters based on source',
    'Enable immediate action on destination',
    'Maintain admin operational context'
  ]
};
