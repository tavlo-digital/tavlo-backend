import { useState } from 'react';
import { ArrowLeft, Store, CreditCard, Repeat, ShoppingCart, MessageSquare, History, AlertCircle } from 'lucide-react';
import { VendorContactInfo } from './VendorContactInfo';
import { VendorPaymentsTab } from './VendorPaymentsTab';
import { VendorChangeReviewPanel, VendorPendingChange } from './VendorChangeReviewPanel';

type VendorDetailTab = 'overview' | 'pending-changes' | 'payments' | 'subscription' | 'orders' | 'reviews' | 'audit';

interface VendorDetailPageProps {
  vendorId: string;
  initialTab?: VendorDetailTab;
  onBack: () => void;
}

export function VendorDetailPage({ vendorId, initialTab = 'overview', onBack }: VendorDetailPageProps) {
  const [activeTab, setActiveTab] = useState<VendorDetailTab>(initialTab);

  // Mock vendor data with contact info
  const vendor = {
    id: vendorId,
    name: 'Bella Italia',
    status: 'Active',
    subscription: 'Premium',
    paymentStatus: 'failed',
    users: {
      current: 7,
      allowed: 15,
      isOverLimit: false
    }
  };

  const contactInfo = {
    businessName: 'Bella Italia GmbH',
    email: 'contact@bellaitalia.at',
    phone: '+43 1 234 5678',
    website: 'https://bellaitalia.at',
    vat: 'ATU12345678',
    legalEntityName: 'Bella Italia Gastronomy GmbH',
    registeredAddress: 'Kärntner Straße 12',
    country: 'Austria',
    city: 'Vienna'
  };

  const handleInvoiceDownloadLogged = (invoiceId: string) => {
    console.log('Invoice downloaded:', invoiceId);
    // Would log to audit log in production
  };

  const tabs = [
    { id: 'overview' as VendorDetailTab, label: 'Overview', icon: Store },
    { id: 'pending-changes' as VendorDetailTab, label: 'Pending Changes', icon: AlertCircle },
    { id: 'payments' as VendorDetailTab, label: 'Payments', icon: CreditCard },
    { id: 'subscription' as VendorDetailTab, label: 'Subscription', icon: Repeat },
    { id: 'orders' as VendorDetailTab, label: 'Orders', icon: ShoppingCart },
    { id: 'reviews' as VendorDetailTab, label: 'Reviews', icon: MessageSquare },
    { id: 'audit' as VendorDetailTab, label: 'Activity', icon: History }
  ];

  const renderTabContent = () => {
    switch (activeTab) {
      case 'overview':
        return (
          <div className="p-6 space-y-6">
            {/* Current Risks Section */}
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
              <h3 className="font-medium text-red-900 mb-2">⚠️ Active Issues</h3>
              <ul className="space-y-2 text-sm text-red-800">
                <li>• Payment failures detected (3 in last 24h)</li>
                <li>• Last successful payment: 5 days ago</li>
              </ul>
            </div>

            {/* Vendor Quick Stats */}
            <div className="grid grid-cols-4 gap-4">
              <div className="bg-white border border-gray-200 rounded-lg p-4">
                <div className="text-sm text-gray-600 mb-1">Vendor ID</div>
                <div className="font-mono text-sm font-medium text-gray-900">{vendor.id}</div>
              </div>
              <div className="bg-white border border-gray-200 rounded-lg p-4">
                <div className="text-sm text-gray-600 mb-1">Status</div>
                <div className="font-medium text-gray-900">{vendor.status}</div>
              </div>
              <div className="bg-white border border-gray-200 rounded-lg p-4">
                <div className="text-sm text-gray-600 mb-1">Subscription</div>
                <div className="font-medium text-gray-900">{vendor.subscription}</div>
              </div>
              <div className="bg-white border border-gray-200 rounded-lg p-4">
                <div className="text-sm text-gray-600 mb-1">Payment Status</div>
                <div className="font-medium text-red-600">{vendor.paymentStatus}</div>
              </div>
            </div>

            {/* Users Summary Card */}
            <div className="bg-white border border-gray-200 rounded-lg p-4">
              <h3 className="font-medium text-gray-900 mb-3">Users</h3>
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-600">Used:</span>
                  <span className="text-sm font-medium text-gray-900">{vendor.users.current}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-600">Allowed:</span>
                  <span className="text-sm font-medium text-gray-900">{vendor.users.allowed}</span>
                </div>
                <div className="pt-3 border-t border-gray-200">
                  <div
                    className={`text-sm font-medium ${
                      vendor.users.isOverLimit ? 'text-orange-600' : 'text-green-600'
                    }`}
                  >
                    {vendor.users.isOverLimit ? '⚠️ Over limit' : '✓ Within limit'}
                  </div>
                  {vendor.users.isOverLimit && (
                    <p className="text-xs text-gray-600 mt-1">
                      Vendor cannot add new users until the limit is increased or users are removed.
                    </p>
                  )}
                </div>
              </div>
            </div>

            {/* Contact & Legal Details */}
            <VendorContactInfo 
              contactInfo={contactInfo} 
              canEdit={false}
            />

            {/* Recent Activity */}
            <div className="bg-white border border-gray-200 rounded-lg p-4">
              <h3 className="font-medium text-gray-900 mb-3">Recent Activity</h3>
              <div className="space-y-2 text-sm">
                <div className="flex items-center justify-between py-2 border-b border-gray-100">
                  <span className="text-gray-600">Payment failed</span>
                  <span className="text-gray-500">2 hours ago</span>
                </div>
                <div className="flex items-center justify-between py-2 border-b border-gray-100">
                  <span className="text-gray-600">Order completed</span>
                  <span className="text-gray-500">5 hours ago</span>
                </div>
                <div className="flex items-center justify-between py-2">
                  <span className="text-gray-600">Menu updated</span>
                  <span className="text-gray-500">1 day ago</span>
                </div>
              </div>
            </div>
          </div>
        );

      case 'pending-changes':
        return (
          <VendorChangeReviewPanel 
            vendorId={vendorId} 
            vendorName={vendor.name}
          />
        );

      case 'payments':
        return (
          <VendorPaymentsTab 
            vendorId={vendorId} 
            vendorName={vendor.name}
            hasFailedPayments={true}
            onInvoiceDownloadLogged={handleInvoiceDownloadLogged}
          />
        );

      case 'subscription':
        return (
          <div className="p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Subscription Details</h2>
            
            {/* Subscription Info */}
            <div className="bg-white border border-gray-200 rounded-lg p-6 mb-6">
              <div className="grid grid-cols-2 gap-6">
                <div>
                  <div className="text-sm text-gray-600 mb-1">Current Plan</div>
                  <div className="text-xl font-semibold text-gray-900">Premium</div>
                </div>
                <div>
                  <div className="text-sm text-gray-600 mb-1">Status</div>
                  <div className="text-xl font-semibold text-green-600">Active</div>
                </div>
                <div>
                  <div className="text-sm text-gray-600 mb-1">Billing Cycle</div>
                  <div className="text-sm font-medium text-gray-900">Monthly</div>
                </div>
                <div>
                  <div className="text-sm text-gray-600 mb-1">Next Billing Date</div>
                  <div className="text-sm font-medium text-gray-900">Dec 15, 2024</div>
                </div>
              </div>
            </div>

            {/* Subscription History */}
            <h3 className="font-medium text-gray-900 mb-3">Subscription History</h3>
            <div className="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100">
              <div className="p-4 flex items-center justify-between">
                <div>
                  <div className="font-medium text-gray-900">Upgraded to Premium</div>
                  <div className="text-sm text-gray-600">From Standard plan</div>
                </div>
                <div className="text-sm text-gray-500">3 months ago</div>
              </div>
              <div className="p-4 flex items-center justify-between">
                <div>
                  <div className="font-medium text-gray-900">Subscription Started</div>
                  <div className="text-sm text-gray-600">Standard plan</div>
                </div>
                <div className="text-sm text-gray-500">8 months ago</div>
              </div>
            </div>
          </div>
        );

      case 'orders':
        return (
          <div className="p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Order History</h2>
            <p className="text-gray-600">Order history for {vendor.name}</p>
          </div>
        );

      case 'reviews':
        return (
          <div className="p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Reviews & Ratings</h2>
            <p className="text-gray-600">Customer reviews for {vendor.name}</p>
          </div>
        );

      case 'audit':
        return (
          <div className="p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Activity Timeline</h2>
            
            {/* Vendor-scoped audit log */}
            <div className="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100">
              <div className="p-4">
                <div className="flex items-start gap-3">
                  <div className="w-2 h-2 bg-red-600 rounded-full mt-2" />
                  <div className="flex-1">
                    <div className="font-medium text-gray-900">Payment Failed</div>
                    <div className="text-sm text-gray-600">Card declined - insufficient funds</div>
                    <div className="text-xs text-gray-500 mt-1">2 hours ago • Admin: System</div>
                  </div>
                </div>
              </div>
              <div className="p-4">
                <div className="flex items-start gap-3">
                  <div className="w-2 h-2 bg-blue-600 rounded-full mt-2" />
                  <div className="flex-1">
                    <div className="font-medium text-gray-900">Menu Updated</div>
                    <div className="text-sm text-gray-600">8 items added, 2 items removed</div>
                    <div className="text-xs text-gray-500 mt-1">1 day ago • Vendor: Self-service</div>
                  </div>
                </div>
              </div>
              <div className="p-4">
                <div className="flex items-start gap-3">
                  <div className="w-2 h-2 bg-purple-600 rounded-full mt-2" />
                  <div className="flex-1">
                    <div className="font-medium text-gray-900">Subscription Upgraded</div>
                    <div className="text-sm text-gray-600">Standard → Premium</div>
                    <div className="text-xs text-gray-500 mt-1">3 months ago • Admin: Sarah Chen</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <div className="flex-1 bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 px-6 py-4">
        <button
          onClick={onBack}
          className="flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-3"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to Vendors
        </button>
        
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">{vendor.name}</h1>
            <p className="text-sm text-gray-600 mt-1">Vendor ID: {vendor.id}</p>
          </div>
          <div className="flex items-center gap-2">
            <span className={`px-3 py-1.5 rounded-lg text-sm font-medium ${
              vendor.status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'
            }`}>
              {vendor.status}
            </span>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="bg-white border-b border-gray-200 px-6">
        <div className="flex gap-1">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            const isActive = activeTab === tab.id;
            
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`px-4 py-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 ${
                  isActive
                    ? 'border-purple-600 text-purple-600'
                    : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300'
                }`}
              >
                <Icon className="w-4 h-4" />
                {tab.label}
              </button>
            );
          })}
        </div>
      </div>

      {/* Tab Content */}
      <div className="flex-1">
        {renderTabContent()}
      </div>
    </div>
  );
}