import { 
  AdminDestination, 
  NavigationContext,
  PlatformHealthCardNavigation,
  AlertsNavigation,
  ActionQueuesNavigation,
  ActivityFeedNavigation,
  SystemStatusNavigation,
  QuickTrendsNavigation
} from './AdminNavigationSpec';

/**
 * TAVLO ADMIN NAVIGATION SERVICE
 * 
 * This service handles all navigation logic for the Admin Dashboard.
 * It implements the navigation specification and ensures all clicks
 * route to the correct destination with proper filters.
 */

export interface AdminPageState {
  page: AdminDestination;
  filters?: Record<string, any>;
  entityId?: string;
  entityType?: string;
  tab?: string;
  context?: {
    source: string;
    description: string;
  };
}

export class AdminNavigationService {
  private onNavigateCallback: (state: AdminPageState) => void;

  constructor(onNavigate: (state: AdminPageState) => void) {
    this.onNavigateCallback = onNavigate;
  }

  /**
   * Navigate to a destination with context
   */
  private navigate(context: NavigationContext | { destination: AdminDestination; filters?: any; entityId?: string; entityType?: string; tab?: string; context?: { source: string; description: string } }) {
    this.onNavigateCallback({
      page: context.destination,
      filters: context.filters,
      entityId: context.entityId,
      entityType: context.entityType,
      tab: context.tab,
      context: context.context
    });
  }

  // ============================================================================
  // PLATFORM HEALTH CARDS
  // ============================================================================

  navigateToActiveVendorsToday() {
    this.navigate(PlatformHealthCardNavigation.activeVendorsToday);
  }

  navigateToActiveCustomersToday() {
    this.navigate(PlatformHealthCardNavigation.activeCustomersToday);
  }

  navigateToOrdersToday() {
    this.navigate(PlatformHealthCardNavigation.ordersToday);
  }

  navigateToGMVToday() {
    this.navigate(PlatformHealthCardNavigation.gmvToday);
  }

  navigateToFailedPayments24h() {
    this.navigate({
      ...PlatformHealthCardNavigation.failedPayments24h,
      context: {
        source: 'Dashboard → Failed Payments (24h)',
        description: 'Showing vendors with failed payments in the last 24 hours'
      }
    });
  }

  navigateToOpenSupportTickets() {
    this.navigate(PlatformHealthCardNavigation.openSupportTickets);
  }

  // ============================================================================
  // ALERTS & INCIDENTS
  // ============================================================================

  navigateToPaymentFailureSpike(vendorId: string, isPrimary: boolean = true) {
    if (isPrimary) {
      // Primary CTA: Go to vendor detail → payments tab
      this.navigate({
        ...AlertsNavigation.paymentFailureSpike.primaryAction,
        entityId: vendorId
      });
    } else {
      // Secondary: Go to failed payments filtered by vendor
      this.navigate({
        ...AlertsNavigation.paymentFailureSpike.secondaryAction,
        filters: {
          ...AlertsNavigation.paymentFailureSpike.secondaryAction.filters,
          vendorId
        }
      });
    }
  }

  navigateToVendorOnboardingStuck() {
    this.navigate(AlertsNavigation.vendorOnboardingStuck);
  }

  navigateToSubscriptionExpiredButActive() {
    this.navigate(AlertsNavigation.subscriptionExpiredButActive);
  }

  // ============================================================================
  // ACTION QUEUES
  // ============================================================================

  navigateToVendorsPendingApproval() {
    this.navigate(ActionQueuesNavigation.vendorsPendingApproval);
  }

  navigateToKYCVerificationFailed() {
    this.navigate(ActionQueuesNavigation.kycVerificationFailed);
  }

  navigateToRefundsAwaitingApproval() {
    this.navigate(ActionQueuesNavigation.refundsAwaitingApproval);
  }

  navigateToOpenDisputes() {
    this.navigate(ActionQueuesNavigation.openDisputes);
  }

  navigateToFlaggedReviews() {
    this.navigate(ActionQueuesNavigation.flaggedReviews);
  }

  navigateToContentModerationNeeded() {
    this.navigate(ActionQueuesNavigation.contentModerationNeeded);
  }

  // ============================================================================
  // ACTIVITY FEED
  // ============================================================================

  navigateToVendorCreated(vendorId: string) {
    this.navigate(ActivityFeedNavigation.vendorCreated(vendorId));
  }

  navigateToPaymentFailed(paymentId: string) {
    this.navigate(ActivityFeedNavigation.paymentFailed(paymentId));
  }

  navigateToPaymentSuccessful(paymentId: string) {
    this.navigate(ActivityFeedNavigation.paymentSuccessful(paymentId));
  }

  navigateToVendorUnsubscribed(vendorId: string) {
    this.navigate(ActivityFeedNavigation.vendorUnsubscribed(vendorId));
  }

  navigateToMenuPublished(vendorId: string) {
    this.navigate(ActivityFeedNavigation.menuPublished(vendorId));
  }

  navigateToPaymentRefunded(paymentId: string) {
    this.navigate(ActivityFeedNavigation.paymentRefunded(paymentId));
  }

  navigateToReviewFlagged(reviewId: string) {
    this.navigate(ActivityFeedNavigation.reviewFlagged(reviewId));
  }

  navigateToVendorActivated(vendorId: string) {
    this.navigate(ActivityFeedNavigation.vendorActivated(vendorId));
  }

  // ============================================================================
  // SYSTEM STATUS
  // ============================================================================

  navigateToSystemStatus() {
    this.navigate(SystemStatusNavigation);
  }

  // ============================================================================
  // QUICK TRENDS
  // ============================================================================

  navigateToOrders24hTrend() {
    this.navigate(QuickTrendsNavigation.orders24h);
  }

  navigateToPaymentSuccessRateTrend() {
    this.navigate(QuickTrendsNavigation.paymentSuccessRate);
  }

  // ============================================================================
  // SEARCH RESULTS
  // ============================================================================

  navigateToSearchResult(result: { type: string; id: string }) {
    const typeMap: Record<string, AdminDestination> = {
      vendor: 'vendors',
      order: 'billing',
      payment: 'billing',
      subscription: 'subscriptions',
      qr: 'vendors' // QR codes are associated with vendors
    };

    this.navigate({
      destination: typeMap[result.type] || 'vendors',
      entityId: result.id,
      entityType: result.type
    });
  }
}

/**
 * Helper function to create navigation service instance
 */
export function createAdminNavigationService(
  onNavigate: (state: AdminPageState) => void
): AdminNavigationService {
  return new AdminNavigationService(onNavigate);
}