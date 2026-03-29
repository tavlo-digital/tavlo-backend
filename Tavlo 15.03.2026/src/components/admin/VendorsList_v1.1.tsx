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
import { Button } from '../ui/button';
import { toast } from 'sonner@2.0.3';

interface Vendor {
  id: string;
  name: string;
  status: 'Active' | 'Inactive' | 'Pending' | 'Suspended';
  subscription: SubscriptionPlan;
  subscriptionStatus: SubscriptionStatus;
  payment: PaymentStatus;
  orders: number;
  revenue: string;
  riskType: VendorRiskType;
  riskDetails?: string;
  paymentDetails?: {
    lastAttemptDate?: string;
    pspName?: string;
    errorReason?: string;
    daysOverdue?: number;
  };
  daysSinceExpiry?: number;
}

interface VendorsList_v1_1Props {
  appliedFilters?: Record<string, any>;
  onNavigateToVendorDetail?: (vendorId: string, tab?: string) => void;
}

export function VendorsList_v1_1({ 
  appliedFilters = {}, 
  onNavigateToVendorDetail 
}: VendorsList_v1_1Props) {
  const [quickFilter, setQuickFilter] = useState<QuickFilterType>(null);
  const [suspendModalVendor, setSuspendModalVendor] = useState<Vendor | null>(null);
  const [moreMenuOpen, setMoreMenuOpen] = useState<string | null>(null);

  // Mock vendor data with risk indicators
  const allVendors: Vendor[] = [
    {
      id: 'VID-8492',
      name: 'Bella Italia',
      status: 'Active',
      subscription: 'Premium',
      subscriptionStatus: 'active',
      payment: 'failed',
      orders: 284,
      revenue: '€12,450',
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
      status: 'Active',
      subscription: 'Standard',
      subscriptionStatus: 'expired',
      payment: 'overdue',
      orders: 156,
      revenue: '€8,920',
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
      status: 'Pending',
      subscription: 'Trial',
      subscriptionStatus: 'active',
      payment: 'trial',
      orders: 12,
      revenue: '€890',
      riskType: 'onboarding-stuck',
      riskDetails: 'KYC verification incomplete for 36 hours'
    },
    {
      id: 'VID-1234',
      name: 'Green Bowl Cafe',
      status: 'Active',
      subscription: 'Basic',
      subscriptionStatus: 'active',
      payment: 'paid',
      orders: 432,
      revenue: '€18,340',
      riskType: 'clean'
    },
    {
      id: 'VID-5678',
      name: 'Burger Palace',
      status: 'Active',
      subscription: 'Premium',
      subscriptionStatus: 'active',
      payment: 'paid',
      orders: 892,
      revenue: '€34,820',
      riskType: 'clean'
    }
  ];

  // Apply quick filters
  const getFilteredVendors = () => {
    let filtered = allVendors;

    // Apply quick filter
    if (quickFilter === 'payment-issues') {
      filtered = filtered.filter(v => v.riskType === 'payment-failure' || v.payment === 'failed' || v.payment === 'overdue');
    } else if (quickFilter === 'subscription-issues') {
      filtered = filtered.filter(v => v.riskType === 'subscription-expired');
    } else if (quickFilter === 'onboarding-stuck') {
      filtered = filtered.filter(v => v.riskType === 'onboarding-stuck');
    } else if (quickFilter === 'high-gmv') {
      filtered = filtered.filter(v => parseFloat(v.revenue.replace(/[€,]/g, '')) > 15000);
    } else if (quickFilter === 'flagged-content') {
      // Would filter for flagged content
      filtered = [];
    }

    // Apply dashboard filters if present
    if (appliedFilters.status) {
      filtered = filtered.filter(v => v.status.toLowerCase() === appliedFilters.status.toLowerCase());
    }
    if (appliedFilters.paymentStatus) {
      filtered = filtered.filter(v => v.payment === appliedFilters.paymentStatus);
    }
    if (appliedFilters.subscriptionStatus) {
      filtered = filtered.filter(v => v.subscriptionStatus === appliedFilters.subscriptionStatus);
    }

    return filtered;
  };

  const vendors = getFilteredVendors();

  // Calculate filter counts
  const filterCounts = {
    paymentIssues: allVendors.filter(v => v.riskType === 'payment-failure' || v.payment === 'failed' || v.payment === 'overdue').length,
    subscriptionIssues: allVendors.filter(v => v.riskType === 'subscription-expired').length,
    onboardingStuck: allVendors.filter(v => v.riskType === 'onboarding-stuck').length,
    highGmv: allVendors.filter(v => parseFloat(v.revenue.replace(/[€,]/g, '')) > 15000).length,
    flaggedContent: 0
  };

  const handleViewVendor = (vendorId: string) => {
    if (onNavigateToVendorDetail) {
      onNavigateToVendorDetail(vendorId, 'overview');
    } else {
      console.log('Navigate to vendor detail:', vendorId);
    }
  };

  const handleRiskIndicatorClick = (vendor: Vendor) => {
    // Route to appropriate tab based on risk type
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

  const handleSuspendVendor = (reason: SuspendReason, notes?: string) => {
    if (!suspendModalVendor) return;
    
    console.log('Suspend vendor:', suspendModalVendor.id, 'Reason:', reason, 'Notes:', notes);
    toast.success(`${suspendModalVendor.name} suspended`, {
      description: `Reason: ${reason}${notes ? ` - ${notes}` : ''}`
    });
    
    // This would be logged to:
    // 1. Vendor activity timeline
    // 2. Global audit log
    
    setSuspendModalVendor(null);
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
      {/* Quick Filters */}
      <VendorQuickFilters
        activeFilter={quickFilter}
        onFilterChange={setQuickFilter}
        counts={filterCounts}
      />

      {/* Applied Filters Notice */}
      {Object.keys(appliedFilters).length > 0 && (
        <div className="bg-purple-50 border-b border-purple-200 px-6 py-2">
          <div className="flex items-center gap-2 text-sm">
            <span className="font-medium text-purple-900">Dashboard Filters Applied:</span>
            <span className="text-purple-700">
              {Object.entries(appliedFilters)
                .map(([key, value]) => `${key}: ${value}`)
                .join(', ')}
            </span>
          </div>
        </div>
      )}

      {/* Vendor Table */}
      <div className="p-6">
        <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">
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
                <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {vendors.map((vendor) => (
                <tr key={vendor.id} className="hover:bg-gray-50 transition-colors">
                  {/* Risk Indicator */}
                  <td className="px-4 py-3">
                    <VendorRiskIndicator
                      riskType={vendor.riskType}
                      details={vendor.riskDetails}
                      onClick={() => handleRiskIndicatorClick(vendor)}
                    />
                  </td>

                  {/* Vendor Name & ID */}
                  <td className="px-4 py-3">
                    <div>
                      <div className="font-medium text-gray-900">{vendor.name}</div>
                      <div className="text-xs text-gray-500 font-mono">{vendor.id}</div>
                    </div>
                  </td>

                  {/* Status */}
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

                  {/* Subscription (clickable) */}
                  <td className="px-4 py-3">
                    <VendorSubscriptionCell
                      plan={vendor.subscription}
                      status={vendor.subscriptionStatus}
                      vendorIsActive={vendor.status === 'Active'}
                      daysSinceExpiry={vendor.daysSinceExpiry}
                      onClick={() => handleSubscriptionClick(vendor)}
                    />
                  </td>

                  {/* Payment (clickable) */}
                  <td className="px-4 py-3">
                    <VendorPaymentCell
                      status={vendor.payment}
                      details={vendor.paymentDetails}
                      onClick={() => handlePaymentClick(vendor)}
                    />
                  </td>

                  {/* Orders */}
                  <td className="px-4 py-3">
                    <span className="text-sm text-gray-900">{vendor.orders}</span>
                  </td>

                  {/* Revenue */}
                  <td className="px-4 py-3">
                    <span className="text-sm font-medium text-gray-900">{vendor.revenue}</span>
                  </td>

                  {/* Actions */}
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                      {/* View */}
                      <button
                        onClick={() => handleViewVendor(vendor.id)}
                        className="p-1.5 hover:bg-gray-100 rounded transition-colors"
                        title="View vendor details"
                      >
                        <Eye className="w-4 h-4 text-gray-600" />
                      </button>

                      {/* Suspend */}
                      <button
                        onClick={() => setSuspendModalVendor(vendor)}
                        className="p-1.5 hover:bg-red-50 rounded transition-colors"
                        title="Suspend vendor"
                      >
                        <Ban className="w-4 h-4 text-red-600" />
                      </button>

                      {/* More menu */}
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