import { useState, useRef, useEffect } from 'react';
import { 
  Search, 
  User,
  Store,
  Users,
  ShoppingCart,
  DollarSign,
  CreditCard,
  AlertCircle,
  UserCheck,
  FileCheck,
  AlertTriangle,
  RefreshCw,
  Flag,
  FileText,
  ShieldAlert,
  QrCode,
  CheckCircle,
  XCircle,
  TrendingUp,
  PackageX,
  Clock
} from 'lucide-react';
import { KPICard_v1_1 } from './KPICard_v1.1';
import { AlertCard_v1_1 } from './AlertCard_v1.1';
import { ActionQueueCard_v1_1 } from './ActionQueueCard_v1.1';
import { ActivityFeedItem_v1_1 } from './ActivityFeedItem_v1.1';
import { SystemStatusIndicator } from './SystemStatusIndicator';
import { AdminSearchResults } from './AdminSearchResults';
import { VendorPendingChangesCard } from './VendorPendingChangesCard';
import { createAdminNavigationService, AdminPageState } from './AdminNavigationService';

type ActivityFilter = 'all' | 'payments' | 'vendors' | 'content';

interface AdminDashboard_v1_1Props {
  onNavigate?: (state: AdminPageState) => void;
}

export function AdminDashboard_v1_1({ onNavigate }: AdminDashboard_v1_1Props) {
  const [searchQuery, setSearchQuery] = useState('');
  const [showSearchDropdown, setShowSearchDropdown] = useState(false);
  const [showSearchResults, setShowSearchResults] = useState(false);
  const [activityFilter, setActivityFilter] = useState<ActivityFilter>('all');
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Create navigation service
  const navigationService = createAdminNavigationService(
    onNavigate || ((state) => console.log('Navigate to:', state))
  );

  // Recent searches (mock data)
  const recentSearches = [
    'vendor: Bella Italia',
    'order: #ORD-48293',
    'payment: pi_3M8yK2',
    'VID-8492',
  ];

  // Close search dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (searchInputRef.current && !searchInputRef.current.contains(event.target as Node)) {
        setShowSearchDropdown(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSearch = (query: string) => {
    if (query.trim()) {
      setShowSearchResults(true);
      setShowSearchDropdown(false);
    }
  };

  // Mock search results
  const searchResults = [
    { type: 'vendor' as const, id: 'VID-8492', title: 'Bella Italia', subtitle: 'Italian Restaurant • Vienna', metadata: 'Active' },
    { type: 'order' as const, id: 'ORD-48293', title: 'Order #48293', subtitle: 'Bella Italia • Table 12', metadata: '€34.50' },
  ];

  if (showSearchResults) {
    return (
      <AdminSearchResults
        query={searchQuery}
        results={searchResults}
        onBack={() => {
          setShowSearchResults(false);
          setSearchQuery('');
        }}
        onSelectResult={(result) => {
          console.log('Navigate to:', result);
        }}
      />
    );
  }

  // Mock data - Platform Health KPIs
  const kpis = {
    activeVendorsToday: { 
      value: 142, 
      total: 247, 
      comparison: 'vs yesterday: +3',
      onClick: () => navigationService.navigateToActiveVendorsToday() 
    },
    activeCustomersToday: { 
      value: 1284,
      comparison: '7-day avg: 1,156',
      onClick: () => navigationService.navigateToActiveCustomersToday() 
    },
    ordersToday: { 
      value: 3482, 
      comparison: 'vs yesterday: +12%',
      onClick: () => navigationService.navigateToOrdersToday() 
    },
    gmvToday: { 
      value: '€48,240',
      comparison: '7-day avg: €42,150',
      onClick: () => navigationService.navigateToGMVToday() 
    },
    failedPayments24h: { 
      value: 7, 
      variant: 'warning' as const,
      comparison: 'vs yesterday: +2',
      onClick: () => navigationService.navigateToFailedPayments24h() 
    },
    openSupportTickets: { 
      value: 3, 
      variant: 'default' as const,
      comparison: '7-day avg: 5',
      onClick: () => navigationService.navigateToOpenSupportTickets() 
    },
  };

  // Mock data - Critical Alerts with new metadata
  const alerts = [
    {
      severity: 'critical' as const,
      title: 'Payment Failure Spike Detected',
      affectedEntity: 'Vendor "Bella Italia" (VID-8492)',
      impact: 'Revenue loss, customer churn',
      timestamp: '8 minutes ago',
      openFor: '8 min',
      assignedTo: 'Sarah Chen',
      actionLabel: 'View Vendor',
      onAction: () => navigationService.navigateToPaymentFailureSpike('VID-8492', true),
      onResolve: () => console.log('Mark as resolved'),
    },
    {
      severity: 'warning' as const,
      title: 'Vendor Onboarding Stuck',
      affectedEntity: '3 vendors (legal forms incomplete)',
      impact: 'Delayed go-live, lost subscription revenue',
      timestamp: '2 hours ago',
      openFor: '2h 15m',
      assignedTo: undefined,
      actionLabel: 'Review Queue',
      onAction: () => navigationService.navigateToVendorOnboardingStuck(),
      onDismiss: () => console.log('Dismiss alert'),
    },
    {
      severity: 'critical' as const,
      title: 'Subscription Expired But Active',
      affectedEntity: 'Vendor "Pizza Express" (VID-2847)',
      impact: 'Unmonetized service usage',
      timestamp: '4 hours ago',
      openFor: '4h 32m',
      assignedTo: 'Mike Johnson',
      actionLabel: 'Suspend Access',
      onAction: () => navigationService.navigateToSubscriptionExpiredButActive(),
      onResolve: () => console.log('Resolve'),
    },
  ];

  // Mock data - Action Queues with priority and aging
  const actionQueues = [
    {
      title: 'Vendors Pending Approval',
      count: 5,
      description: 'KYC verification completed, awaiting final approval',
      icon: UserCheck,
      priority: 'medium' as const,
      oldestItem: '18h',
      onClick: () => navigationService.navigateToVendorsPendingApproval(),
    },
    {
      title: 'KYC Verification Failed',
      count: 3,
      description: 'Identity documents rejected or incomplete',
      icon: ShieldAlert,
      priority: 'high' as const,
      oldestItem: '2d 4h',
      onClick: () => navigationService.navigateToKYCVerificationFailed(),
    },
    {
      title: 'Refunds Awaiting Approval',
      count: 8,
      description: 'Customer refund requests pending review',
      icon: RefreshCw,
      priority: 'medium' as const,
      oldestItem: '12h',
      onClick: () => navigationService.navigateToRefundsAwaitingApproval(),
    },
    {
      title: 'Open Disputes',
      count: 2,
      description: 'Active chargebacks and payment disputes',
      icon: AlertTriangle,
      priority: 'high' as const,
      oldestItem: '1d 8h',
      onClick: () => navigationService.navigateToOpenDisputes(),
    },
    {
      title: 'Flagged Reviews',
      count: 6,
      description: 'Reviews flagged for inappropriate content',
      icon: Flag,
      priority: 'low' as const,
      oldestItem: '6h',
      onClick: () => navigationService.navigateToFlaggedReviews(),
    },
    {
      title: 'Content Moderation Needed',
      count: 0,
      description: 'User-reported menu items or vendor profiles',
      icon: FileText,
      priority: 'low' as const,
      onClick: () => navigationService.navigateToContentModerationNeeded(),
    },
  ];

  // Mock data - Activity Feed with color-coded categories
  const allActivities = [
    {
      type: 'vendor' as const,
      icon: Store,
      title: 'Vendor Created',
      description: '"Sakura Sushi" registered and completed onboarding',
      timestamp: '12 min ago',
      entityId: 'VID-9482',
      onClick: () => console.log('View vendor details'),
    },
    {
      type: 'payment' as const,
      icon: XCircle,
      title: 'Payment Failed',
      description: 'Order #ORD-48293 payment declined (Card expired)',
      timestamp: '28 min ago',
      entityId: 'PAY-3829',
      onClick: () => console.log('View payment'),
    },
    {
      type: 'payment' as const,
      icon: CheckCircle,
      title: 'Payment Successful',
      description: 'Order #ORD-48291 payment confirmed (€42.50)',
      timestamp: '35 min ago',
      entityId: 'PAY-3828',
      onClick: () => console.log('View payment'),
    },
    {
      type: 'vendor' as const,
      icon: PackageX,
      title: 'Vendor Unsubscribed',
      description: '"Green Bowl Cafe" downgraded from Premium to Basic',
      timestamp: '1 hour ago',
      entityId: 'VID-2847',
      onClick: () => console.log('View vendor'),
    },
    {
      type: 'content' as const,
      icon: FileCheck,
      title: 'Menu Published',
      description: '"Bella Italia" published 8 new menu items with translations',
      timestamp: '2 hours ago',
      entityId: 'VID-8492',
      onClick: () => console.log('View menu'),
    },
    {
      type: 'payment' as const,
      icon: RefreshCw,
      title: 'Payment Refunded',
      description: 'Refund processed for Order #ORD-48102 (€34.50)',
      timestamp: '3 hours ago',
      entityId: 'PAY-3801',
      onClick: () => console.log('View refund'),
    },
    {
      type: 'review' as const,
      icon: Flag,
      title: 'Review Flagged',
      description: 'Review for "Pizza Express" flagged for inappropriate language',
      timestamp: '4 hours ago',
      entityId: 'REV-1829',
      onClick: () => console.log('Review moderation'),
    },
    {
      type: 'vendor' as const,
      icon: CheckCircle,
      title: 'Vendor Activated',
      description: '"Coffee Corner" subscription activated (Standard Plan)',
      timestamp: '5 hours ago',
      entityId: 'VID-9471',
      onClick: () => console.log('View vendor'),
    },
  ];

  // Filter activities based on selected filter
  const filteredActivities = activityFilter === 'all' 
    ? allActivities 
    : allActivities.filter(activity => {
        if (activityFilter === 'payments') return activity.type === 'payment';
        if (activityFilter === 'vendors') return activity.type === 'vendor';
        if (activityFilter === 'content') return activity.type === 'content' || activity.type === 'review';
        return true;
      });

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Top Bar */}
      <div className="bg-white border-b border-gray-200 sticky top-0 z-10">
        <div className="max-w-[1800px] mx-auto px-6 py-4">
          <div className="flex items-center justify-between gap-6">
            {/* Global Search with inline helper */}
            <div className="flex-1 max-w-2xl" ref={searchInputRef}>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  type="text"
                  placeholder="vendor: Enery | order: #1234 | payment: pi_ | qr: QR-982"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  onFocus={() => setShowSearchDropdown(true)}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      handleSearch(searchQuery);
                    }
                  }}
                  className="w-full pl-11 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-transparent text-sm"
                />
                
                {/* Recent Searches Dropdown */}
                {showSearchDropdown && recentSearches.length > 0 && (
                  <div className="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20">
                    <div className="p-2">
                      <div className="text-xs text-gray-500 px-2 py-1 mb-1">Recent searches</div>
                      {recentSearches.map((search, index) => (
                        <button
                          key={index}
                          onClick={() => {
                            setSearchQuery(search);
                            handleSearch(search);
                          }}
                          className="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 rounded"
                        >
                          {search}
                        </button>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>

            {/* System Status & Admin Profile */}
            <div className="flex items-center gap-3">
              <SystemStatusIndicator
                status="operational"
                onClick={() => console.log('Navigate to system status page')}
              />
              
              <div className="text-right">
                <div className="text-sm font-medium text-gray-900">Admin User</div>
                <div className="text-xs text-gray-500">Ops Admin</div>
              </div>
              <div className="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center">
                <User className="w-5 h-5 text-white" />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-[1800px] mx-auto px-6 py-6">
        {/* Page Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-semibold text-gray-900 mb-1">Platform Operations</h1>
          <p className="text-sm text-gray-600">Real-time monitoring and action center</p>
        </div>

        {/* Platform Health Snapshot */}
        <div className="mb-6">
          <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Platform Health</h2>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <KPICard_v1_1
              label="Active Vendors Today"
              value={kpis.activeVendorsToday.value}
              icon={Store}
              onClick={kpis.activeVendorsToday.onClick}
              subtitle={`of ${kpis.activeVendorsToday.total} total`}
              comparison={kpis.activeVendorsToday.comparison}
            />
            <KPICard_v1_1
              label="Active Customers Today"
              value={kpis.activeCustomersToday.value}
              icon={Users}
              onClick={kpis.activeCustomersToday.onClick}
              comparison={kpis.activeCustomersToday.comparison}
            />
            <KPICard_v1_1
              label="Orders Today"
              value={kpis.ordersToday.value}
              icon={ShoppingCart}
              onClick={kpis.ordersToday.onClick}
              comparison={kpis.ordersToday.comparison}
            />
            <KPICard_v1_1
              label="GMV Today"
              value={kpis.gmvToday.value}
              icon={DollarSign}
              onClick={kpis.gmvToday.onClick}
              comparison={kpis.gmvToday.comparison}
            />
            <KPICard_v1_1
              label="Failed Payments (24h)"
              value={kpis.failedPayments24h.value}
              icon={CreditCard}
              variant={kpis.failedPayments24h.variant}
              onClick={kpis.failedPayments24h.onClick}
              comparison={kpis.failedPayments24h.comparison}
            />
            <KPICard_v1_1
              label="Open Support Tickets"
              value={kpis.openSupportTickets.value}
              icon={AlertCircle}
              onClick={kpis.openSupportTickets.onClick}
              comparison={kpis.openSupportTickets.comparison}
            />
          </div>
        </div>

        {/* Alerts & Incidents - PRIMARY SECTION */}
        <div className="mb-6">
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide">Alerts & Incidents</h2>
            <button className="text-sm text-gray-600 hover:text-gray-900">
              View All ({alerts.length})
            </button>
          </div>
          
          {alerts.length === 0 ? (
            <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
              <CheckCircle className="w-8 h-8 text-green-600 mx-auto mb-2" />
              <p className="text-green-900 font-medium">No active incidents</p>
              <p className="text-sm text-green-700 mt-1">All systems are running smoothly</p>
            </div>
          ) : (
            <div className="space-y-3">
              {alerts.map((alert, index) => (
                <AlertCard_v1_1 key={index} {...alert} />
              ))}
            </div>
          )}
        </div>

        {/* Two Column Layout: Action Queues + Activity Feed */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          {/* Admin Action Queues */}
          <div>
            <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Action Queues</h2>
            
            {/* Pending Vendor Changes - Priority Card */}
            <div className="mb-3">
              <VendorPendingChangesCard 
                onNavigate={(vendorId) => {
                  if (onNavigate) {
                    onNavigate({
                      page: 'vendor-detail',
                      entityType: 'vendor',
                      entityId: vendorId,
                      tab: 'pending-changes'
                    });
                  }
                }}
              />
            </div>
            
            {actionQueues.every(q => q.count === 0) ? (
              <div className="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                <CheckCircle className="w-8 h-8 text-green-600 mx-auto mb-2" />
                <p className="text-green-900 font-medium">All clear</p>
                <p className="text-sm text-green-700 mt-1">No pending actions required</p>
              </div>
            ) : (
              <div className="space-y-3">
                {actionQueues.map((queue, index) => (
                  <ActionQueueCard_v1_1 key={index} {...queue} />
                ))}
              </div>
            )}
          </div>

          {/* Last 24h Activity Feed */}
          <div>
            <div className="flex items-center justify-between mb-3">
              <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide">Last 24h Activity</h2>
              
              {/* Quick Filters */}
              <div className="flex items-center gap-2">
                {(['all', 'payments', 'vendors', 'content'] as ActivityFilter[]).map((filter) => (
                  <button
                    key={filter}
                    onClick={() => setActivityFilter(filter)}
                    className={`px-2 py-1 text-xs rounded transition-colors ${
                      activityFilter === filter
                        ? 'bg-purple-600 text-white'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    }`}
                  >
                    {filter.charAt(0).toUpperCase() + filter.slice(1)}
                  </button>
                ))}
              </div>
            </div>
            
            <div className="bg-white border border-gray-200 rounded-lg overflow-hidden max-h-[800px] overflow-y-auto">
              <div className="divide-y divide-gray-100">
                {filteredActivities.map((activity, index) => (
                  <ActivityFeedItem_v1_1 key={index} {...activity} />
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Quick Trends - Restricted */}
        <div className="mb-6">
          <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Quick Trends</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <button
              onClick={() => console.log('Navigate to analytics')}
              className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-all text-left group"
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-700">Orders (24h)</span>
                <TrendingUp className="w-4 h-4 text-green-600" />
              </div>
              <div className="text-2xl font-semibold text-gray-900 mb-1">3,482</div>
              <div className="text-xs text-green-600">+12% vs yesterday</div>
            </button>

            <button
              onClick={() => console.log('Navigate to analytics')}
              className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-all text-left group"
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-700">Payment Success Rate</span>
                <CheckCircle className="w-4 h-4 text-green-600" />
              </div>
              <div className="text-2xl font-semibold text-gray-900 mb-1">97.8%</div>
              <div className="text-xs text-gray-600">7 failed / 318 total</div>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}