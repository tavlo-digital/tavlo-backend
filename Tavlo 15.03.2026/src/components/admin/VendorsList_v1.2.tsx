import { useState } from 'react';
import { Eye, Ban, MoreVertical, CreditCard, FileText, ShoppingCart, MessageSquare, History } from 'lucide-react';
import { VendorRiskIndicator, VendorRiskType } from './VendorRiskIndicator';
import { VendorPaymentCell, PaymentStatus } from './VendorPaymentCell';
import { VendorSubscriptionCell, SubscriptionPlan, SubscriptionStatus } from './VendorSubscriptionCell';
import { VendorQuickFilters, QuickFilterType } from './VendorQuickFilters';
import { VendorSummaryCounters, VendorCounterType } from './VendorSummaryCounters';
import { VendorExtendedFilters, VendorFilters } from './VendorExtendedFilters';
import { VendorExportButton } from './VendorExportButton';
import { VendorContextBanner } from './VendorContextBanner';
import { VendorSuspendModal, SuspendReason } from './VendorSuspendModal';
import { toast } from 'sonner@2.0.3';

interface Vendor {
  id: string;
  name: string;
  category?: string;
  country?: string;
  city?: string;
  address?: string;
  status: 'Active' | 'Inactive' | 'Pending' | 'Suspended';
  liveStatus?: string;
  subscription: SubscriptionPlan;
  subscriptionStatus: SubscriptionStatus;
  payment: PaymentStatus;
  orders: number;
  revenue: string;
  rating?: number;
  lastActive?: string;
  email?: string;
  phone?: string;
  website?: string;
  vat?: string;
  createdDate?: string;
  riskType: VendorRiskType;
  riskDetails?: string;
  hasPendingChanges?: boolean;
  pendingChangesCount?: number;
  paymentDetails?: {
    lastAttemptDate?: string;
    pspName?: string;
    errorReason?: string;
    daysOverdue?: number;
  };
  daysSinceExpiry?: number;
}

interface DashboardContext {
  source: string;
  description: string;
}

interface VendorsList_v1_2Props {
  appliedFilters?: Record<string, any>;
  dashboardContext?: DashboardContext;
  onNavigateToVendorDetail?: (vendorId: string, tab?: string) => void;
  onExportLogged?: (details: any) => void;
  onAuditAction?: (action: string, details: any) => void;
}

export function VendorsList_v1_2({ 
  appliedFilters = {}, 
  dashboardContext,
  onNavigateToVendorDetail,
  onExportLogged,
  onAuditAction
}: VendorsList_v1_2Props) {
  const [quickFilter, setQuickFilter] = useState<QuickFilterType>(null);
  const [activeCounter, setActiveCounter] = useState<VendorCounterType>(null);
  const [extendedFilters, setExtendedFilters] = useState<VendorFilters>({});
  const [suspendModalVendor, setSuspendModalVendor] = useState<Vendor | null>(null);
  const [moreMenuOpen, setMoreMenuOpen] = useState<string | null>(null);
  const [selectedVendors, setSelectedVendors] = useState<string[]>([]);
  const [showDashboardContext, setShowDashboardContext] = useState(!!dashboardContext);

  // Mock vendor data - would come from API
  const allVendors: Vendor[] = [
    {
      id: 'VID-8492',
      name: 'Bella Italia',
      category: 'Italian Restaurant',
      country: 'Austria',
      city: 'Vienna',
      address: 'Kärntner Straße 12',
      status: 'Active',
      liveStatus: 'Live',
      subscription: 'Premium',
      subscriptionStatus: 'active',
      payment: 'failed',
      orders: 284,
      revenue: '€12,450',
      rating: 4.5,
      lastActive: '2 hours ago',
      email: 'contact@bellaitalia.at',
      phone: '+43 1 234 5678',
      website: 'https://bellaitalia.at',
      vat: 'ATU12345678',
      createdDate: '2024-01-15',
      riskType: 'payment-failure',
      riskDetails: '3 failed payments in last 24h',
      paymentDetails: {
        lastAttemptDate: '2 hours ago',
        pspName: 'Stripe',
        errorReason: 'Card declined - insufficient funds',
        daysOverdue: 0
      }
    },
    {
      id: 'VID-2847',
      name: 'Pizza Express',
      category: 'Pizza Restaurant',
      country: 'Austria',
      city: 'Vienna',
      status: 'Active',
      liveStatus: 'Live',
      subscription: 'Standard',
      subscriptionStatus: 'expired',
      payment: 'overdue',
      orders: 156,
      revenue: '€8,920',
      rating: 4.2,
      lastActive: '1 day ago',
      email: 'info@pizzaexpress.at',
      createdDate: '2024-02-20',
      riskType: 'subscription-expired',
      riskDetails: 'Subscription expired 4 days ago but vendor still active',
      paymentDetails: {
        daysOverdue: 4
      },
      daysSinceExpiry: 4
    },
    {
      id: 'VID-9471',
      name: 'Sakura Sushi',
      category: 'Japanese Restaurant',
      country: 'Austria',
      city: 'Salzburg',
      status: 'Pending',
      liveStatus: 'Not Live',
      subscription: 'Trial',
      subscriptionStatus: 'active',
      payment: 'trial',
      orders: 12,
      revenue: '€890',
      lastActive: '3 hours ago',
      createdDate: '2024-06-01',
      riskType: 'onboarding-stuck',
      riskDetails: 'KYC verification incomplete for 36 hours'
    },
    {
      id: 'VID-1234',
      name: 'Green Bowl Cafe',
      category: 'Health Food',
      country: 'Germany',
      city: 'Munich',
      status: 'Active',
      liveStatus: 'Live',
      subscription: 'Basic',
      subscriptionStatus: 'active',
      payment: 'paid',
      orders: 432,
      revenue: '€18,340',
      rating: 4.8,
      lastActive: '1 hour ago',
      createdDate: '2024-03-10',
      riskType: 'clean'
    },
    {
      id: 'VID-5678',
      name: 'Burger Palace',
      category: 'American Restaurant',
      country: 'Switzerland',
      city: 'Zurich',
      status: 'Active',
      liveStatus: 'Live',
      subscription: 'Premium',
      subscriptionStatus: 'active',
      payment: 'paid',
      orders: 892,
      revenue: '€34,820',
      rating: 4.6,
      lastActive: '30 minutes ago',
      createdDate: '2024-01-05',
      riskType: 'clean'
    }
  ];

  // Calculate summary counters
  const counters = {
    total: allVendors.length,
    active: allVendors.filter(v => v.status === 'Active').length,
    inactive: allVendors.filter(v => v.status === 'Inactive' || v.status === 'Suspended').length,
    live: allVendors.filter(v => v.liveStatus === 'Live').length,
    notLive: allVendors.filter(v => v.liveStatus === 'Not Live').length
  };

  // Apply all filters
  const getFilteredVendors = () => {
    let filtered = allVendors;

    // Apply counter filter
    if (activeCounter === 'active') {
      filtered = filtered.filter(v => v.status === 'Active');
    } else if (activeCounter === 'inactive') {
      filtered = filtered.filter(v => v.status === 'Inactive' || v.status === 'Suspended');
    } else if (activeCounter === 'live') {
      filtered = filtered.filter(v => v.liveStatus === 'Live');
    } else if (activeCounter === 'not-live') {
      filtered = filtered.filter(v => v.liveStatus === 'Not Live');
    }

    // Apply quick filter
    if (quickFilter === 'payment-issues') {
      filtered = filtered.filter(v => v.riskType === 'payment-failure' || v.payment === 'failed' || v.payment === 'overdue');
    } else if (quickFilter === 'subscription-issues') {
      filtered = filtered.filter(v => v.riskType === 'subscription-expired');
    } else if (quickFilter === 'onboarding-stuck') {
      filtered = filtered.filter(v => v.riskType === 'onboarding-stuck');
    } else if (quickFilter === 'high-gmv') {
      filtered = filtered.filter(v => parseFloat(v.revenue.replace(/[€,]/g, '')) > 15000);
    }

    // Apply extended filters
    if (extendedFilters.plan && extendedFilters.plan.length > 0) {
      filtered = filtered.filter(v => extendedFilters.plan!.includes(v.subscription));
    }
    if (extendedFilters.country && extendedFilters.country.length > 0) {
      filtered = filtered.filter(v => v.country && extendedFilters.country!.includes(v.country));
    }
    if (extendedFilters.city && extendedFilters.city.length > 0) {
      filtered = filtered.filter(v => v.city && extendedFilters.city!.includes(v.city));
    }
    if (extendedFilters.liveStatus) {
      filtered = filtered.filter(v => 
        extendedFilters.liveStatus === 'live' 
          ? v.liveStatus === 'Live' 
          : v.liveStatus === 'Not Live'
      );
    }
    if (extendedFilters.subscriptionState && extendedFilters.subscriptionState.length > 0) {
      filtered = filtered.filter(v => 
        extendedFilters.subscriptionState!.some(state => 
          v.subscriptionStatus.toLowerCase() === state.toLowerCase()
        )
      );
    }

    // Apply dashboard filters
    if (appliedFilters.status) {
      filtered = filtered.filter(v => v.status.toLowerCase() === appliedFilters.status.toLowerCase());
    }
    if (appliedFilters.paymentStatus) {
      filtered = filtered.filter(v => v.payment === appliedFilters.paymentStatus);
    }

    return filtered;
  };

  const vendors = getFilteredVendors();

  // Calculate quick filter counts
  const filterCounts = {
    paymentIssues: allVendors.filter(v => v.riskType === 'payment-failure' || v.payment === 'failed' || v.payment === 'overdue').length,
    subscriptionIssues: allVendors.filter(v => v.riskType === 'subscription-expired').length,
    onboardingStuck: allVendors.filter(v => v.riskType === 'onboarding-stuck').length,
    highGmv: allVendors.filter(v => parseFloat(v.revenue.replace(/[€,]/g, '')) > 15000).length,
    flaggedContent: 0
  };

  const handleClearAllFilters = () => {
    setQuickFilter(null);
    setActiveCounter(null);
    setExtendedFilters({});
    setShowDashboardContext(false);
  };

  const handleExportLogged = (exportDetails: any) => {
    if (onExportLogged) {
      onExportLogged(exportDetails);
    }
    if (onAuditAction) {
      onAuditAction('vendor_export', exportDetails);
    }
  };

  const handleSuspendVendor = (reason: SuspendReason, notes?: string) => {
    if (!suspendModalVendor) return;
    
    const auditDetails = {
      vendorId: suspendModalVendor.id,
      vendorName: suspendModalVendor.name,
      reason,
      notes,
      timestamp: new Date().toISOString()
    };

    if (onAuditAction) {
      onAuditAction('vendor_suspended', auditDetails);
    }
    
    toast.success(`${suspendModalVendor.name} suspended`, {
      description: `Reason: ${reason}${notes ? ` - ${notes}` : ''}`
    });
    
    setSuspendModalVendor(null);
  };

  const handleViewVendor = (vendorId: string) => {
    if (onNavigateToVendorDetail) {
      onNavigateToVendorDetail(vendorId, 'overview');
    }
  };

  const handleRiskIndicatorClick = (vendor: Vendor) => {
    const tabMap: Record<VendorRiskType, string> = {
      'payment-failure': 'payments',
      'subscription-expired': 'subscription',
      'onboarding-stuck': 'overview',
      'clean': 'overview'
    };

    if (onNavigateToVendorDetail) {
      onNavigateToVendorDetail(vendor.id, tabMap[vendor.riskType]);
    }
  };

  const handlePaymentClick = (vendor: Vendor) => {
    if (onNavigateToVendorDetail) {
      onNavigateToVendorDetail(vendor.id, 'payments');
    }
  };

  const handleSubscriptionClick = (vendor: Vendor) => {
    if (onNavigateToVendorDetail) {
      onNavigateToVendorDetail(vendor.id, 'subscription');
    }
  };

  const handleMoreAction = (vendor: Vendor, action: string) => {
    const tabs: Record<string, string> = {
      payments: 'payments',
      subscription: 'subscription',
      orders: 'orders',
      reviews: 'reviews',
      audit: 'audit'
    };

    if (onNavigateToVendorDetail && tabs[action]) {
      onNavigateToVendorDetail(vendor.id, tabs[action]);
    }
    setMoreMenuOpen(null);
  };

  return (
    <div className="flex-1 bg-gray-50">
      {/* Context Banner (from Dashboard) */}
      {showDashboardContext && dashboardContext && (
        <VendorContextBanner
          source={dashboardContext.source}
          description={dashboardContext.description}
          onClear={() => {
            setShowDashboardContext(false);
            handleClearAllFilters();
          }}
        />
      )}

      {/* Summary Counters */}
      <VendorSummaryCounters
        counters={counters}
        activeCounter={activeCounter}
        onCounterClick={setActiveCounter}
      />

      {/* Quick Filters */}
      <VendorQuickFilters
        activeFilter={quickFilter}
        onFilterChange={setQuickFilter}
        counts={filterCounts}
      />

      {/* Extended Filters */}
      <VendorExtendedFilters
        filters={extendedFilters}
        onFiltersChange={setExtendedFilters}
        onClearAll={handleClearAllFilters}
      />

      {/* Table Header with Export */}
      <div className="px-6 py-4 bg-white border-b border-gray-200 flex items-center justify-between">
        <div className="flex items-center gap-4">
          <div className="text-sm text-gray-600">
            Showing <span className="font-semibold text-gray-900">{vendors.length}</span> of{' '}
            <span className="font-semibold text-gray-900">{allVendors.length}</span> vendors
          </div>
          {selectedVendors.length > 0 && (
            <div className="flex items-center gap-2">
              <div className="h-4 w-px bg-gray-300" />
              <div className="text-sm font-medium text-purple-700">
                {selectedVendors.length} selected
              </div>
              <button
                onClick={() => setSelectedVendors([])}
                className="text-xs text-gray-600 hover:text-gray-900 underline"
              >
                Clear selection
              </button>
            </div>
          )}
        </div>
        <VendorExportButton
          vendors={vendors}
          selectedVendors={selectedVendors}
          isFiltered={vendors.length < allVendors.length}
          onExportLogged={handleExportLogged}
        />
      </div>

      {/* Vendor Table */}
      <div className="px-6 pb-6">
        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="text-left px-4 py-3 w-12">
                  <input
                    type="checkbox"
                    checked={selectedVendors.length === vendors.length && vendors.length > 0}
                    onChange={(e) => {
                      if (e.target.checked) {
                        setSelectedVendors(vendors.map(v => v.id));
                      } else {
                        setSelectedVendors([]);
                      }
                    }}
                    className="rounded border-gray-300 text-purple-600 focus:ring-purple-600"
                    title="Select all vendors"
                  />
                </th>
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide w-12">
                  Risk
                </th>
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">
                  Vendor
                </th>
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">
                  Status
                </th>
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">
                  Subscription
                </th>
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">
                  Payment
                </th>
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">
                  Orders
                </th>
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">
                  Revenue
                </th>
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide w-32">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {vendors.map((vendor) => (
                <tr key={vendor.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-4 py-3">
                    <input
                      type="checkbox"
                      checked={selectedVendors.includes(vendor.id)}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedVendors([...selectedVendors, vendor.id]);
                        } else {
                          setSelectedVendors(selectedVendors.filter(id => id !== vendor.id));
                        }
                      }}
                      className="rounded border-gray-300 text-purple-600 focus:ring-purple-600"
                      onClick={(e) => e.stopPropagation()}
                    />
                  </td>
                  <td className="px-4 py-3">
                    <VendorRiskIndicator
                      riskType={vendor.riskType}
                      details={vendor.riskDetails}
                      onClick={() => handleRiskIndicatorClick(vendor)}
                    />
                  </td>
                  <td className="px-4 py-3">
                    <div>
                      <div className="font-medium text-gray-900">{vendor.name}</div>
                      <div className="text-xs text-gray-500 font-mono">{vendor.id}</div>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                      vendor.status === 'Active' ? 'bg-green-100 text-green-700' :
                      vendor.status === 'Pending' ? 'bg-yellow-100 text-yellow-700' :
                      vendor.status === 'Suspended' ? 'bg-red-100 text-red-700' :
                      'bg-gray-100 text-gray-700'
                    }`}>
                      {vendor.status}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <VendorSubscriptionCell
                      plan={vendor.subscription}
                      status={vendor.subscriptionStatus}
                      vendorIsActive={vendor.status === 'Active'}
                      daysSinceExpiry={vendor.daysSinceExpiry}
                      onClick={() => handleSubscriptionClick(vendor)}
                    />
                  </td>
                  <td className="px-4 py-3">
                    <VendorPaymentCell
                      status={vendor.payment}
                      details={vendor.paymentDetails}
                      onClick={() => handlePaymentClick(vendor)}
                    />
                  </td>
                  <td className="px-4 py-3">
                    <span className="text-sm text-gray-900">{vendor.orders}</span>
                  </td>
                  <td className="px-4 py-3">
                    <span className="text-sm font-medium text-gray-900">{vendor.revenue}</span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => handleViewVendor(vendor.id)}
                        className="p-1.5 hover:bg-gray-100 rounded transition-colors"
                        title="View vendor details"
                      >
                        <Eye className="w-4 h-4 text-gray-600" />
                      </button>
                      <button
                        onClick={() => setSuspendModalVendor(vendor)}
                        className="p-1.5 hover:bg-red-50 rounded transition-colors"
                        title="Suspend vendor"
                      >
                        <Ban className="w-4 h-4 text-red-600" />
                      </button>
                      <div className="relative">
                        <button
                          onClick={() => setMoreMenuOpen(moreMenuOpen === vendor.id ? null : vendor.id)}
                          className="p-1.5 hover:bg-gray-100 rounded transition-colors"
                          title="More actions"
                        >
                          <MoreVertical className="w-4 h-4 text-gray-600" />
                        </button>
                        {moreMenuOpen === vendor.id && (
                          <>
                            <div 
                              className="fixed inset-0 z-10" 
                              onClick={() => setMoreMenuOpen(null)}
                            />
                            <div className="absolute right-0 top-full mt-1 bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[180px] z-20">
                              <button
                                onClick={() => handleMoreAction(vendor, 'payments')}
                                className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 flex items-center gap-2"
                              >
                                <CreditCard className="w-4 h-4 text-gray-500" />
                                View Payments
                              </button>
                              <button
                                onClick={() => handleMoreAction(vendor, 'subscription')}
                                className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 flex items-center gap-2"
                              >
                                <FileText className="w-4 h-4 text-gray-500" />
                                View Subscription
                              </button>
                              <button
                                onClick={() => handleMoreAction(vendor, 'orders')}
                                className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 flex items-center gap-2"
                              >
                                <ShoppingCart className="w-4 h-4 text-gray-500" />
                                View Orders
                              </button>
                              <button
                                onClick={() => handleMoreAction(vendor, 'reviews')}
                                className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 flex items-center gap-2"
                              >
                                <MessageSquare className="w-4 h-4 text-gray-500" />
                                View Reviews
                              </button>
                              <button
                                onClick={() => handleMoreAction(vendor, 'audit')}
                                className="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 flex items-center gap-2"
                              >
                                <History className="w-4 h-4 text-gray-500" />
                                View Audit Log
                              </button>
                            </div>
                          </>
                        )}
                      </div>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {vendors.length === 0 && (
            <div className="p-12 text-center text-gray-500">
              No vendors match the current filters
            </div>
          )}
        </div>
      </div>

      {/* Suspend Modal */}
      {suspendModalVendor && (
        <VendorSuspendModal
          vendorName={suspendModalVendor.name}
          vendorId={suspendModalVendor.id}
          onConfirm={handleSuspendVendor}
          onCancel={() => setSuspendModalVendor(null)}
        />
      )}
    </div>
  );
}