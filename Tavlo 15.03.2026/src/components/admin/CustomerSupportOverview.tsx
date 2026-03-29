import { useState } from 'react';
import { ArrowLeft, Shield, ShoppingCart, DollarSign, Gift, FileText, MessageSquare, History, AlertTriangle, Trash2, Archive, ExternalLink, Clock, CheckCircle, XCircle, Star } from 'lucide-react';
import { toast } from 'sonner@2.0.3';
import { OrderReceiptModal } from './OrderReceiptModal';

type CustomerTab = 'orders' | 'refunds-disputes' | 'reviews' | 'activity-log' | 'gdpr-requests';

interface CustomerSupportOverviewProps {
  customerId: string;
  onBack: () => void;
  restrictedDataVisible: boolean;
}

export function CustomerSupportOverview({ 
  customerId, 
  onBack,
  restrictedDataVisible 
}: CustomerSupportOverviewProps) {
  const [activeTab, setActiveTab] = useState<CustomerTab>('orders');
  const [showGDPRExportModal, setShowGDPRExportModal] = useState(false);
  const [showAnonymizeModal, setShowAnonymizeModal] = useState(false);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [selectedOrderId, setSelectedOrderId] = useState<string | null>(null);

  // Mock customer data
  const customer = {
    id: customerId,
    accountType: 'registered' as const,
    email: 'john.doe@example.com',
    phone: '+43 664 1234567',
    createdDate: '2024-01-15',
    lastLogin: '2025-01-06 14:32',
    registrationSource: 'QR Code',
    totalOrders: 47,
    totalSpend: '€1,284.50',
    loyaltyPoints: 1284,
    activeGDPRRequests: 0
  };

  // Mock orders data
  const orders = [
    { id: 'ORD-8472', vendor: 'Bella Italia', date: '2025-01-06 13:15', status: 'delivered', amount: '€34.50', items: 3 },
    { id: 'ORD-8401', vendor: 'Sushi Tokyo', date: '2025-01-04 19:30', status: 'delivered', amount: '€52.90', items: 5 },
    { id: 'ORD-8324', vendor: 'Pizza Express', date: '2025-01-02 18:45', status: 'cancelled', amount: '€28.00', items: 2 },
    { id: 'ORD-8256', vendor: 'Burger House', date: '2024-12-30 12:20', status: 'delivered', amount: '€41.20', items: 4 },
    { id: 'ORD-8198', vendor: 'Bella Italia', date: '2024-12-28 20:10', status: 'delivered', amount: '€67.80', items: 6 },
    { id: 'ORD-8142', vendor: 'Thai Garden', date: '2024-12-25 14:00', status: 'delivered', amount: '€45.30', items: 3 },
  ];

  // Mock refunds/disputes data
  const refundsDisputes = [
    {
      id: 'DIS-1024',
      type: 'dispute',
      vendor: 'Pizza Express',
      orderId: 'ORD-8324',
      date: '2025-01-03',
      status: 'open',
      amount: '€28.00',
      reason: 'Card declined but still charged',
      description: 'Payment failed during checkout but amount was deducted from account'
    },
    {
      id: 'REF-2048',
      type: 'refund',
      vendor: 'Sushi Tokyo',
      orderId: 'ORD-7892',
      date: '2024-12-15',
      status: 'approved',
      amount: '€38.50',
      reason: 'Food quality issue',
      description: 'Sushi was not fresh, customer complained immediately after delivery'
    }
  ];

  // Mock reviews data
  const reviews = [
    { id: 'REV-501', vendor: 'Bella Italia', date: '2025-01-06', rating: 5, comment: 'Amazing pizza! Fast delivery and still hot. Will order again.', flagged: false },
    { id: 'REV-502', vendor: 'Sushi Tokyo', date: '2025-01-04', rating: 4, comment: 'Good quality sushi, delivery was a bit slow.', flagged: false },
    { id: 'REV-503', vendor: 'Pizza Express', date: '2025-01-03', rating: 1, comment: 'Terrible experience! Order never arrived but I was charged!', flagged: true },
    { id: 'REV-504', vendor: 'Burger House', date: '2024-12-30', rating: 5, comment: 'Best burgers in Vienna! Perfectly cooked.', flagged: false },
    { id: 'REV-505', vendor: 'Bella Italia', date: '2024-12-28', rating: 4, comment: 'Great pasta, generous portions.', flagged: false },
    { id: 'REV-506', vendor: 'Thai Garden', date: '2024-12-25', rating: 5, comment: 'Excellent pad thai, authentic taste!', flagged: false },
    { id: 'REV-507', vendor: 'Sushi Tokyo', date: '2024-12-20', rating: 3, comment: 'Average sushi, expected better quality for the price.', flagged: false },
    { id: 'REV-508', vendor: 'Pizza Express', date: '2024-12-18', rating: 2, comment: 'Pizza was cold and soggy when it arrived.', flagged: true },
  ];

  // Mock activity log data
  const activityLog = [
    { timestamp: '2025-01-06 14:32', action: 'Login', details: 'Logged in via mobile app', ip: '185.34.xxx.xxx' },
    { timestamp: '2025-01-06 13:15', action: 'Order Placed', details: 'Order ORD-8472 at Bella Italia (€34.50)', ip: '185.34.xxx.xxx' },
    { timestamp: '2025-01-06 13:14', action: 'QR Scan', details: 'Scanned QR code at table 12, Bella Italia', ip: '185.34.xxx.xxx' },
    { timestamp: '2025-01-04 19:30', action: 'Order Placed', details: 'Order ORD-8401 at Sushi Tokyo (€52.90)', ip: '185.34.xxx.xxx' },
    { timestamp: '2025-01-04 19:28', action: 'Login', details: 'Logged in via web browser', ip: '185.34.xxx.xxx' },
    { timestamp: '2025-01-03 10:45', action: 'Dispute Filed', details: 'Dispute DIS-1024 for Order ORD-8324', ip: '185.34.xxx.xxx' },
    { timestamp: '2025-01-02 18:45', action: 'Order Cancelled', details: 'Order ORD-8324 at Pizza Express (€28.00)', ip: '185.34.xxx.xxx' },
    { timestamp: '2024-12-30 12:20', action: 'Order Placed', details: 'Order ORD-8256 at Burger House (€41.20)', ip: '185.34.xxx.xxx' },
    { timestamp: '2024-12-30 12:18', action: 'Login', details: 'Logged in via mobile app', ip: '185.34.xxx.xxx' },
    { timestamp: '2024-12-28 20:10', action: 'Order Placed', details: 'Order ORD-8198 at Bella Italia (€67.80)', ip: '185.34.xxx.xxx' },
  ];

  // Mock GDPR requests data
  const gdprRequests = [
    // Empty for this customer
  ];

  const tabs = [
    { id: 'orders' as CustomerTab, label: 'Orders', icon: ShoppingCart, count: 47 },
    { id: 'refunds-disputes' as CustomerTab, label: 'Refunds / Disputes', icon: AlertTriangle, count: 2 },
    { id: 'reviews' as CustomerTab, label: 'Reviews / Complaints', icon: MessageSquare, count: 8 },
    { id: 'activity-log' as CustomerTab, label: 'Activity Log', icon: History },
    { id: 'gdpr-requests' as CustomerTab, label: 'GDPR Requests', icon: FileText, count: customer.activeGDPRRequests }
  ];

  const handleGDPRExport = (reason: string) => {
    console.log('AUDIT LOG: GDPR personal data export', {
      customerId,
      reason,
      timestamp: new Date().toISOString(),
      admin: 'Current Admin User'
    });
    
    toast.success('GDPR data export initiated', {
      description: 'Customer will receive email with data package'
    });
    setShowGDPRExportModal(false);
  };

  const handleAnonymize = (reason: string) => {
    console.log('AUDIT LOG: Customer anonymization', {
      customerId,
      reason,
      timestamp: new Date().toISOString(),
      admin: 'Current Admin User'
    });
    
    toast.success('Customer anonymized', {
      description: 'Personal data removed, order history retained'
    });
    setShowAnonymizeModal(false);
    onBack();
  };

  const handleDelete = (reason: string) => {
    console.log('AUDIT LOG: Customer account deletion', {
      customerId,
      reason,
      timestamp: new Date().toISOString(),
      admin: 'Current Admin User'
    });
    
    toast.success('Customer account deleted', {
      description: 'All data permanently removed'
    });
    setShowDeleteModal(false);
    onBack();
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <button
            onClick={onBack}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ArrowLeft className="w-5 h-5 text-gray-600" />
          </button>
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">Customer Support Overview</h1>
            <p className="text-sm text-gray-600 mt-1">
              Customer ID: <span className="font-mono font-medium">{customer.id}</span>
              <span className="mx-2">•</span>
              <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                customer.accountType === 'registered'
                  ? 'bg-green-100 text-green-700'
                  : 'bg-gray-100 text-gray-700'
              }`}>
                {customer.accountType === 'registered' ? 'Registered' : 'Guest'}
              </span>
            </p>
          </div>
        </div>
      </div>

      {/* Support-Only Visibility Banner (Persistent) */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg px-6 py-4">
        <div className="flex items-start gap-3">
          <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <Shield className="w-5 h-5 text-blue-600" />
          </div>
          <div>
            <h3 className="font-semibold text-blue-900 mb-1">
              Support-Only View
            </h3>
            <p className="text-sm text-blue-800">
              Visible for support purposes only. Personal data access is logged in Audit Trail and not visible to vendors. This customer cannot see or access this admin view.
            </p>
          </div>
        </div>
      </div>

      {/* Basic Information */}
      <div className="bg-white border border-gray-200 rounded-lg">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="font-semibold text-gray-900">Basic Information</h3>
        </div>
        <div className="px-6 py-4">
          <div className="grid grid-cols-2 gap-x-8 gap-y-4">
            <div>
              <div className="text-xs font-medium text-gray-500 uppercase mb-1">Customer ID</div>
              <div className="text-sm font-mono font-medium text-gray-900">{customer.id}</div>
            </div>
            <div>
              <div className="text-xs font-medium text-gray-500 uppercase mb-1">Account Type</div>
              <div className="text-sm text-gray-900">
                {customer.accountType === 'registered' ? 'Registered Account' : 'Guest Account'}
              </div>
            </div>
            <div>
              <div className="text-xs font-medium text-gray-500 uppercase mb-1">Email (Restricted)</div>
              {restrictedDataVisible ? (
                <div className="text-sm font-medium text-gray-900">{customer.email}</div>
              ) : (
                <div className="text-sm text-gray-400">Hidden (enable restricted data access)</div>
              )}
            </div>
            <div>
              <div className="text-xs font-medium text-gray-500 uppercase mb-1">Phone (Restricted)</div>
              {restrictedDataVisible ? (
                <div className="text-sm font-medium text-gray-900">{customer.phone}</div>
              ) : (
                <div className="text-sm text-gray-400">Hidden (enable restricted data access)</div>
              )}
            </div>
            <div>
              <div className="text-xs font-medium text-gray-500 uppercase mb-1">Account Created</div>
              <div className="text-sm text-gray-900">{customer.createdDate}</div>
            </div>
            <div>
              <div className="text-xs font-medium text-gray-500 uppercase mb-1">Last Login</div>
              <div className="text-sm text-gray-900">{customer.lastLogin}</div>
            </div>
            <div>
              <div className="text-xs font-medium text-gray-500 uppercase mb-1">Registration Source</div>
              <div className="text-sm text-gray-900">{customer.registrationSource}</div>
            </div>
          </div>
        </div>
      </div>

      {/* Activity Summary */}
      <div>
        <h3 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">
          Activity Summary (Support Context)
        </h3>
        <div className="grid grid-cols-3 gap-4">
          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="flex items-center justify-between mb-2">
              <ShoppingCart className="w-5 h-5 text-gray-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {customer.totalOrders}
            </div>
            <div className="text-sm text-gray-600">Total Orders</div>
          </div>

          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="flex items-center justify-between mb-2">
              <DollarSign className="w-5 h-5 text-gray-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {customer.totalSpend}
            </div>
            <div className="text-sm text-gray-600">Total Spend</div>
          </div>

          <div className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="flex items-center justify-between mb-2">
              <Gift className="w-5 h-5 text-gray-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {customer.loyaltyPoints}
            </div>
            <div className="text-sm text-gray-600">Loyalty Points</div>
          </div>
        </div>
      </div>

      {/* Investigation Tabs */}
      <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
        {/* Tab Navigation */}
        <div className="border-b border-gray-200 overflow-x-auto">
          <div className="flex">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
                    activeTab === tab.id
                      ? 'border-purple-600 text-purple-700 bg-purple-50'
                      : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  {tab.label}
                  {tab.count !== undefined && (
                    <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                      activeTab === tab.id
                        ? 'bg-purple-100 text-purple-700'
                        : 'bg-gray-100 text-gray-600'
                    }`}>
                      {tab.count}
                    </span>
                  )}
                </button>
              );
            })}
          </div>
        </div>

        {/* Tab Content */}
        <div className="p-6">
          {activeTab === 'orders' && (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <p className="text-sm text-gray-600">Showing 6 most recent orders (of {customer.totalOrders} total)</p>
              </div>
              
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead className="bg-gray-50 border-b border-gray-200">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Order ID</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Vendor</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Date</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Items</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Amount</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {orders.map((order) => (
                      <tr key={order.id} className="hover:bg-gray-50">
                        <td className="px-4 py-3">
                          <span className="font-mono text-sm font-medium text-gray-900">{order.id}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="text-sm text-gray-900">{order.vendor}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="text-sm text-gray-600">{order.date}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                            order.status === 'delivered' 
                              ? 'bg-green-100 text-green-700'
                              : order.status === 'cancelled'
                              ? 'bg-red-100 text-red-700'
                              : 'bg-yellow-100 text-yellow-700'
                          }`}>
                            {order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                          </span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="text-sm text-gray-900">{order.items}</span>
                        </td>
                        <td className="px-4 py-3">
                          <span className="text-sm font-medium text-gray-900">{order.amount}</span>
                        </td>
                        <td className="px-4 py-3">
                          <button 
                            onClick={() => setSelectedOrderId(order.id)}
                            className="text-purple-600 hover:text-purple-700 text-sm font-medium flex items-center gap-1"
                          >
                            View <ExternalLink className="w-3 h-3" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
          
          {activeTab === 'refunds-disputes' && (
            <div className="space-y-4">
              {refundsDisputes.length === 0 ? (
                <div className="text-center py-12">
                  <AlertTriangle className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                  <p className="text-gray-600 font-medium">No refunds or disputes</p>
                  <p className="text-sm text-gray-500 mt-1">This customer has no active refund requests or disputes</p>
                </div>
              ) : (
                <div className="space-y-4">
                  {refundsDisputes.map((item) => (
                    <div key={item.id} className={`border-2 rounded-lg p-4 ${
                      item.status === 'open' 
                        ? 'border-red-200 bg-red-50' 
                        : 'border-green-200 bg-green-50'
                    }`}>
                      <div className="flex items-start justify-between mb-3">
                        <div className="flex items-center gap-3">
                          <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${
                            item.type === 'dispute' 
                              ? 'bg-red-100' 
                              : 'bg-orange-100'
                          }`}>
                            <AlertTriangle className={`w-5 h-5 ${
                              item.type === 'dispute' 
                                ? 'text-red-600' 
                                : 'text-orange-600'
                            }`} />
                          </div>
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="font-mono font-semibold text-gray-900">{item.id}</span>
                              <span className={`px-2 py-0.5 rounded text-xs font-semibold ${
                                item.type === 'dispute' 
                                  ? 'bg-red-600 text-white' 
                                  : 'bg-orange-600 text-white'
                              }`}>
                                {item.type.toUpperCase()}
                              </span>
                              <span className={`px-2 py-0.5 rounded text-xs font-semibold ${
                                item.status === 'open' 
                                  ? 'bg-red-100 text-red-700' 
                                  : 'bg-green-100 text-green-700'
                              }`}>
                                {item.status.charAt(0).toUpperCase() + item.status.slice(1)}
                              </span>
                            </div>
                            <p className="text-sm text-gray-600 mt-1">{item.vendor} • Order {item.orderId}</p>
                          </div>
                        </div>
                        <div className="text-right">
                          <div className="text-lg font-semibold text-gray-900">{item.amount}</div>
                          <div className="text-xs text-gray-500">{item.date}</div>
                        </div>
                      </div>
                      
                      <div className="space-y-2">
                        <div>
                          <div className="text-xs font-medium text-gray-500 uppercase mb-1">Reason</div>
                          <div className="text-sm font-medium text-gray-900">{item.reason}</div>
                        </div>
                        <div>
                          <div className="text-xs font-medium text-gray-500 uppercase mb-1">Description</div>
                          <div className="text-sm text-gray-700">{item.description}</div>
                        </div>
                      </div>
                      
                      {item.status === 'open' && (
                        <div className="mt-4 pt-4 border-t border-red-200 flex items-center gap-3">
                          <button className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
                            <CheckCircle className="w-4 h-4" />
                            Approve Refund
                          </button>
                          <button className="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
                            <XCircle className="w-4 h-4" />
                            Decline
                          </button>
                          <button className="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
                            <MessageSquare className="w-4 h-4" />
                            Contact Vendor
                          </button>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {activeTab === 'reviews' && (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <p className="text-sm text-gray-600">All customer reviews and ratings</p>
              </div>
              
              <div className="space-y-3">
                {reviews.map((review) => (
                  <div key={review.id} className={`border rounded-lg p-4 ${
                    review.flagged 
                      ? 'border-red-200 bg-red-50' 
                      : 'border-gray-200 bg-white'
                  }`}>
                    <div className="flex items-start justify-between mb-2">
                      <div className="flex items-center gap-3">
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-mono text-sm font-medium text-gray-600">{review.id}</span>
                            {review.flagged && (
                              <span className="px-2 py-0.5 bg-red-600 text-white text-xs font-semibold rounded flex items-center gap-1">
                                <AlertTriangle className="w-3 h-3" />
                                FLAGGED
                              </span>
                            )}
                          </div>
                          <p className="text-sm text-gray-600 mt-0.5">{review.vendor}</p>
                        </div>
                      </div>
                      <div className="text-right">
                        <div className="flex items-center gap-1">
                          {[...Array(5)].map((_, i) => (
                            <Star 
                              key={i} 
                              className={`w-4 h-4 ${
                                i < review.rating 
                                  ? 'fill-yellow-400 text-yellow-400' 
                                  : 'text-gray-300'
                              }`} 
                            />
                          ))}
                        </div>
                        <div className="text-xs text-gray-500 mt-1">{review.date}</div>
                      </div>
                    </div>
                    
                    <p className="text-sm text-gray-900 mt-2">{review.comment}</p>
                    
                    {review.flagged && (
                      <div className="mt-3 pt-3 border-t border-red-200 flex items-center gap-2">
                        <button className="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded transition-colors">
                          Remove Review
                        </button>
                        <button className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-medium rounded transition-colors">
                          Unflag
                        </button>
                        <button className="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-medium rounded transition-colors">
                          Contact Vendor
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {activeTab === 'activity-log' && (
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <p className="text-sm text-gray-600">Recent customer activity (last 10 events)</p>
              </div>
              
              <div className="space-y-2">
                {activityLog.map((activity, index) => (
                  <div key={index} className="flex items-start gap-4 p-3 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
                    <div className="flex-shrink-0">
                      <Clock className="w-5 h-5 text-gray-400" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="font-medium text-sm text-gray-900">{activity.action}</span>
                        <span className="text-xs text-gray-500">{activity.timestamp}</span>
                      </div>
                      <p className="text-sm text-gray-600">{activity.details}</p>
                      <p className="text-xs text-gray-500 mt-1">IP: {activity.ip}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {activeTab === 'gdpr-requests' && (
            <div className="text-center py-12">
              {gdprRequests.length === 0 ? (
                <>
                  <FileText className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                  <p className="text-gray-600 font-medium">No GDPR requests</p>
                  <p className="text-sm text-gray-500 mt-1">This customer has not submitted any GDPR data requests</p>
                </>
              ) : (
                <div className="space-y-4">
                  {gdprRequests.map((request: any) => (
                    <div key={request.id} className="border border-gray-200 rounded-lg p-4 text-left">
                      <div className="flex items-start justify-between">
                        <div>
                          <div className="font-mono font-medium text-gray-900">{request.id}</div>
                          <div className="text-sm text-gray-600 mt-1">{request.type}</div>
                        </div>
                        <span className="px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-700">
                          {request.status}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* GDPR Actions Section (Strictly Controlled) */}
      <div className="bg-white border-2 border-red-200 rounded-lg">
        <div className="px-6 py-4 bg-red-50 border-b border-red-200">
          <h3 className="font-semibold text-red-900 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5" />
            GDPR Actions (Strictly Controlled)
          </h3>
          <p className="text-sm text-red-800 mt-1">
            These actions are logged, audited, and may be irreversible. Proceed with caution.
          </p>
        </div>
        
        <div className="px-6 py-4 space-y-3">
          {/* Export Personal Data */}
          <div className="flex items-center justify-between p-4 bg-gray-50 border border-gray-200 rounded-lg">
            <div>
              <div className="font-medium text-gray-900">Export Personal Data</div>
              <div className="text-sm text-gray-600 mt-1">
                GDPR Right to Access • Export all customer data to JSON/CSV
              </div>
            </div>
            <button
              onClick={() => setShowGDPRExportModal(true)}
              className="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors"
            >
              Export Data
            </button>
          </div>

          {/* Anonymize Customer */}
          <div className="flex items-center justify-between p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <div>
              <div className="font-medium text-gray-900 flex items-center gap-2">
                Anonymize Customer
                <span className="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-semibold rounded">
                  SUPER ADMIN ONLY
                </span>
              </div>
              <div className="text-sm text-gray-600 mt-1">
                GDPR Right to Erasure • Remove personal data, retain anonymized orders
              </div>
            </div>
            <button
              onClick={() => setShowAnonymizeModal(true)}
              className="px-4 py-2 text-sm font-medium text-amber-700 bg-amber-100 border border-amber-300 rounded-lg hover:bg-amber-200 transition-colors flex items-center gap-2"
            >
              <Archive className="w-4 h-4" />
              Anonymize
            </button>
          </div>

          {/* Delete Account Permanently */}
          <div className="flex items-center justify-between p-4 bg-red-50 border-2 border-red-300 rounded-lg">
            <div>
              <div className="font-medium text-red-900 flex items-center gap-2">
                Delete Account Permanently
                <span className="px-2 py-0.5 bg-red-600 text-white text-xs font-semibold rounded">
                  SUPER ADMIN ONLY
                </span>
              </div>
              <div className="text-sm text-red-700 mt-1">
                ⚠️ IRREVERSIBLE • Permanently delete all data including order history
              </div>
            </div>
            <button
              onClick={() => setShowDeleteModal(true)}
              className="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors flex items-center gap-2"
            >
              <Trash2 className="w-4 h-4" />
              Delete Forever
            </button>
          </div>
        </div>
      </div>

      {/* GDPR Modals */}
      {showGDPRExportModal && (
        <GDPRActionModal
          title="Export Personal Data"
          description="Customer will receive all personal data in machine-readable format (GDPR Article 20)"
          actionLabel="Export Data"
          actionClass="bg-blue-600 hover:bg-blue-700"
          onConfirm={handleGDPRExport}
          onClose={() => setShowGDPRExportModal(false)}
        />
      )}

      {showAnonymizeModal && (
        <GDPRActionModal
          title="Anonymize Customer Account"
          description="Personal data will be removed. Order history will be retained with anonymized references. This action cannot be undone."
          actionLabel="Anonymize Account"
          actionClass="bg-amber-600 hover:bg-amber-700"
          requiresSuperAdmin
          onConfirm={handleAnonymize}
          onClose={() => setShowAnonymizeModal(false)}
        />
      )}

      {showDeleteModal && (
        <GDPRActionModal
          title="Delete Account Permanently"
          description="⚠️ IRREVERSIBLE: All customer data including order history will be permanently deleted. Use Anonymize instead if order history must be retained for legal/tax purposes."
          actionLabel="Delete Forever"
          actionClass="bg-red-600 hover:bg-red-700"
          requiresSuperAdmin
          isDestructive
          onConfirm={handleDelete}
          onClose={() => setShowDeleteModal(false)}
        />
      )}

      {/* Order Receipt Modal */}
      {selectedOrderId && (
        <OrderReceiptModal
          orderId={selectedOrderId}
          onClose={() => setSelectedOrderId(null)}
        />
      )}
    </div>
  );
}

// GDPR Action Modal Component
function GDPRActionModal({
  title,
  description,
  actionLabel,
  actionClass,
  requiresSuperAdmin = false,
  isDestructive = false,
  onConfirm,
  onClose
}: {
  title: string;
  description: string;
  actionLabel: string;
  actionClass: string;
  requiresSuperAdmin?: boolean;
  isDestructive?: boolean;
  onConfirm: (reason: string) => void;
  onClose: () => void;
}) {
  const [reason, setReason] = useState('');
  const [confirmText, setConfirmText] = useState('');

  const handleConfirm = () => {
    if (!reason.trim()) {
      toast.error('Please provide a reason');
      return;
    }
    
    if (isDestructive && confirmText !== 'DELETE FOREVER') {
      toast.error('Please type DELETE FOREVER to confirm');
      return;
    }

    onConfirm(reason);
  };

  return (
    <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900">
            {title}
          </h3>
          {requiresSuperAdmin && (
            <span className="inline-block mt-2 px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded">
              SUPER ADMIN ONLY
            </span>
          )}
        </div>
        
        <div className="px-6 py-4 space-y-4">
          <div className={`p-3 rounded-lg border ${
            isDestructive 
              ? 'bg-red-50 border-red-200' 
              : 'bg-blue-50 border-blue-200'
          }`}>
            <p className={`text-sm ${
              isDestructive ? 'text-red-800' : 'text-blue-800'
            }`}>
              {description}
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Reason for Action *
            </label>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Explain the reason for this GDPR action..."
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none"
              rows={3}
              autoFocus
            />
          </div>

          {isDestructive && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Type "DELETE FOREVER" to confirm *
              </label>
              <input
                type="text"
                value={confirmText}
                onChange={(e) => setConfirmText(e.target.value)}
                placeholder="DELETE FOREVER"
                className="w-full px-3 py-2 border border-red-300 rounded-lg text-sm font-mono"
              />
            </div>
          )}

          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p className="text-xs text-gray-700">
              ✓ Action will be logged to Audit Trail<br />
              ✓ Admin ID and timestamp recorded<br />
              ✓ Reason attached to audit log
            </p>
          </div>
        </div>
        
        <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-3">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            onClick={handleConfirm}
            disabled={!reason.trim() || (isDestructive && confirmText !== 'DELETE FOREVER')}
            className={`px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed ${actionClass}`}
          >
            {actionLabel}
          </button>
        </div>
      </div>
    </div>
  );
}