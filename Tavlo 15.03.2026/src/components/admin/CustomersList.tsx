import { useState, useEffect } from 'react';
import { Search, Users, ShoppingCart, AlertCircle, FileText, TrendingUp, Eye, MoreVertical, Lock, Download } from 'lucide-react';
import { GDPRPrivacyBanner } from './GDPRPrivacyBanner';
import { CustomerRiskIndicator, CustomerRiskType } from './CustomerRiskIndicator';
import { toast } from 'sonner@2.0.3';

interface Customer {
  id: string;
  accountType: 'registered' | 'guest';
  email: string;
  phone: string;
  totalOrders: number;
  totalSpend: string;
  loyaltyPoints: number;
  createdDate: string;
  lastActive: string;
  riskType: CustomerRiskType;
  riskDetails?: string;
  registrationSource?: string;
}

type FilterType = 'all' | 'flagged' | 'high-activity' | 'gdpr-requests';

interface CustomersListProps {
  onNavigateToCustomer: (customerId: string) => void;
}

export function CustomersList({ onNavigateToCustomer }: CustomersListProps) {
  const [restrictedDataVisible, setRestrictedDataVisible] = useState(false);
  const [remainingSeconds, setRemainingSeconds] = useState(0);
  const [searchQuery, setSearchQuery] = useState('');
  const [activeFilter, setActiveFilter] = useState<FilterType>('all');
  const [showExportModal, setShowExportModal] = useState(false);
  const [selectedCustomers, setSelectedCustomers] = useState<Set<string>>(new Set());

  // Auto-hide timer (10 minutes = 600 seconds)
  useEffect(() => {
    if (restrictedDataVisible) {
      setRemainingSeconds(600); // 10 minutes
      
      const interval = setInterval(() => {
        setRemainingSeconds((prev) => {
          if (prev <= 1) {
            setRestrictedDataVisible(false);
            toast.info('Restricted data auto-hidden', {
              description: 'Data visibility timeout reached'
            });
            return 0;
          }
          return prev - 1;
        });
      }, 1000);

      return () => clearInterval(interval);
    } else {
      setRemainingSeconds(0);
    }
  }, [restrictedDataVisible]);

  // Mock customer data
  const allCustomers: Customer[] = [
    {
      id: 'C-1024',
      accountType: 'registered',
      email: 'john.doe@example.com',
      phone: '+43 664 1234567',
      totalOrders: 47,
      totalSpend: '€1,284.50',
      loyaltyPoints: 1284,
      createdDate: '2024-01-15',
      lastActive: '2 hours ago',
      riskType: 'normal',
      registrationSource: 'QR Code'
    },
    {
      id: 'C-2048',
      accountType: 'registered',
      email: 'maria.schmidt@example.com',
      phone: '+43 664 2345678',
      totalOrders: 89,
      totalSpend: '€3,456.20',
      loyaltyPoints: 3456,
      createdDate: '2023-11-03',
      lastActive: '1 day ago',
      riskType: 'normal',
      registrationSource: 'Mobile App'
    },
    {
      id: 'C-3072',
      accountType: 'registered',
      email: 'suspicious@example.com',
      phone: '+43 664 9999999',
      totalOrders: 3,
      totalSpend: '€450.00',
      loyaltyPoints: 0,
      createdDate: '2025-01-02',
      lastActive: '3 hours ago',
      riskType: 'flagged-account',
      riskDetails: 'Multiple failed payment attempts, dispute filed',
      registrationSource: 'Web'
    },
    {
      id: 'C-4096',
      accountType: 'guest',
      email: 'guest_84729@tavlo.app',
      phone: '+43 664 8472947',
      totalOrders: 1,
      totalSpend: '€28.90',
      loyaltyPoints: 0,
      createdDate: '2025-01-06',
      lastActive: '5 hours ago',
      riskType: 'normal',
      registrationSource: 'QR Code (Guest)'
    },
    {
      id: 'C-5120',
      accountType: 'registered',
      email: 'refund.user@example.com',
      phone: '+43 664 5555555',
      totalOrders: 12,
      totalSpend: '€680.00',
      loyaltyPoints: 340,
      createdDate: '2024-08-20',
      lastActive: '12 hours ago',
      riskType: 'unusual-activity',
      riskDetails: '5 refund requests in last 30 days',
      registrationSource: 'Mobile App'
    }
  ];

  // Summary counters
  const summaryData = {
    totalCustomers: allCustomers.length,
    totalOrders: allCustomers.reduce((sum, c) => sum + c.totalOrders, 0),
    flaggedAccounts: allCustomers.filter(c => c.riskType === 'flagged-account').length,
    gdprRequests: 4, // Mock
    highActivityCustomers: allCustomers.filter(c => c.totalOrders >= 40).length
  };

  const handleFilterChange = (filter: FilterType) => {
    setActiveFilter(filter);
    setSearchQuery(''); // Clear search when filter changes
  };

  const filteredCustomers = allCustomers.filter(customer => {
    // Apply search filter
    if (searchQuery.trim()) {
      return customer.id.toLowerCase().includes(searchQuery.toLowerCase());
    }

    // Apply summary card filters
    switch (activeFilter) {
      case 'flagged':
        return customer.riskType === 'flagged-account';
      case 'high-activity':
        return customer.totalOrders >= 40;
      case 'gdpr-requests':
        // Mock: would filter by actual GDPR request status
        return false;
      default:
        return true;
    }
  });

  const handleToggleCustomer = (customerId: string) => {
    setSelectedCustomers(prev => {
      const next = new Set(prev);
      if (next.has(customerId)) {
        next.delete(customerId);
      } else {
        next.add(customerId);
      }
      return next;
    });
  };

  const handleSelectAll = () => {
    if (selectedCustomers.size === filteredCustomers.length) {
      setSelectedCustomers(new Set());
    } else {
      setSelectedCustomers(new Set(filteredCustomers.map(c => c.id)));
    }
  };

  const handleExport = (includePersonalData: boolean, reason?: string) => {
    if (includePersonalData) {
      console.log('AUDIT LOG: Customer data export with personal data', {
        reason,
        count: selectedCustomers.size > 0 ? selectedCustomers.size : filteredCustomers.length,
        timestamp: new Date().toISOString(),
        admin: 'Current Admin User'
      });
      
      toast.success('Export initiated', {
        description: 'Personal data export logged to Audit Trail'
      });
    } else {
      toast.success('Export initiated', {
        description: 'Aggregated customer data exported'
      });
    }
    
    setShowExportModal(false);
  };

  return (
    <div className="p-6 space-y-6">
      {/* GDPR Privacy Banner */}
      <GDPRPrivacyBanner
        restrictedDataVisible={restrictedDataVisible}
        onToggleRestricted={setRestrictedDataVisible}
        remainingSeconds={remainingSeconds}
      />

      {/* Summary Cards */}
      <div>
        <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">
          Customer Overview
        </h2>
        <div className="grid grid-cols-5 gap-4">
          <button
            onClick={() => handleFilterChange('all')}
            className={`bg-white border rounded-lg p-4 text-left hover:shadow-sm transition-all ${
              activeFilter === 'all' ? 'ring-2 ring-purple-600 border-purple-200' : 'border-gray-200'
            }`}
          >
            <div className="flex items-center justify-between mb-2">
              <Users className="w-5 h-5 text-gray-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {summaryData.totalCustomers}
            </div>
            <div className="text-sm text-gray-600">Total Customers</div>
          </button>

          <button
            onClick={() => handleFilterChange('all')}
            className="bg-white border border-gray-200 rounded-lg p-4 text-left hover:shadow-sm transition-all"
          >
            <div className="flex items-center justify-between mb-2">
              <ShoppingCart className="w-5 h-5 text-gray-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {summaryData.totalOrders}
            </div>
            <div className="text-sm text-gray-600">Total Orders</div>
          </button>

          <button
            onClick={() => handleFilterChange('flagged')}
            className={`bg-white border rounded-lg p-4 text-left hover:shadow-sm transition-all ${
              activeFilter === 'flagged' ? 'ring-2 ring-purple-600 border-purple-200' : 'border-gray-200'
            }`}
          >
            <div className="flex items-center justify-between mb-2">
              <AlertCircle className="w-5 h-5 text-red-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {summaryData.flaggedAccounts}
            </div>
            <div className="text-sm text-gray-600">Flagged Accounts</div>
          </button>

          <button
            onClick={() => handleFilterChange('gdpr-requests')}
            className={`bg-white border rounded-lg p-4 text-left hover:shadow-sm transition-all ${
              activeFilter === 'gdpr-requests' ? 'ring-2 ring-purple-600 border-purple-200' : 'border-gray-200'
            }`}
          >
            <div className="flex items-center justify-between mb-2">
              <FileText className="w-5 h-5 text-blue-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {summaryData.gdprRequests}
            </div>
            <div className="text-sm text-gray-600">GDPR Requests (30d)</div>
          </button>

          <button
            onClick={() => handleFilterChange('high-activity')}
            className={`bg-white border rounded-lg p-4 text-left hover:shadow-sm transition-all ${
              activeFilter === 'high-activity' ? 'ring-2 ring-purple-600 border-purple-200' : 'border-gray-200'
            }`}
          >
            <div className="flex items-center justify-between mb-2">
              <TrendingUp className="w-5 h-5 text-green-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {summaryData.highActivityCustomers}
            </div>
            <div className="text-sm text-gray-600">High-Activity (30d)</div>
          </button>
        </div>
      </div>

      {/* Search & Actions Bar */}
      <div className="flex items-center justify-between gap-4">
        <div className="flex-1 max-w-md">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
            <input
              type="text"
              placeholder="Search: C-1024"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-11 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-transparent text-sm"
            />
          </div>
          <p className="text-xs text-gray-500 mt-1">
            💡 Hint: Search by Customer ID only (paste from order or support ticket)
          </p>
        </div>

        <div className="flex items-center gap-3">
          {selectedCustomers.size > 0 && (
            <span className="text-sm text-gray-600">
              {selectedCustomers.size} selected
            </span>
          )}
          
          <button
            onClick={() => setShowExportModal(true)}
            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2"
          >
            <Download className="w-4 h-4" />
            Export {selectedCustomers.size > 0 ? `Selected (${selectedCustomers.size})` : 'All Filtered'}
          </button>
        </div>
      </div>

      {/* Active Filter Banner */}
      {activeFilter !== 'all' && (
        <div className="bg-purple-50 border border-purple-200 rounded-lg px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="w-2 h-2 bg-purple-600 rounded-full" />
            <span className="text-sm font-medium text-purple-900">
              Filter active: {activeFilter === 'flagged' ? 'Flagged Accounts' : activeFilter === 'high-activity' ? 'High-Activity Customers' : 'GDPR Requests'}
            </span>
          </div>
          <button
            onClick={() => handleFilterChange('all')}
            className="text-sm text-purple-700 hover:text-purple-900 font-medium"
          >
            Clear filter
          </button>
        </div>
      )}

      {/* Customer Table */}
      <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table className="w-full">
          <thead className="bg-gray-50 border-b border-gray-200">
            <tr>
              <th className="px-4 py-3 text-left">
                <input
                  type="checkbox"
                  checked={selectedCustomers.size === filteredCustomers.length && filteredCustomers.length > 0}
                  onChange={handleSelectAll}
                  className="w-4 h-4 text-purple-600 rounded"
                />
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                Risk
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                Customer ID
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                Account Type
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                Email
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                Phone
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                Orders
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                Total Spend
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                Last Active
              </th>
              <th className="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                Actions
              </th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {filteredCustomers.map((customer) => (
              <tr
                key={customer.id}
                className={`hover:bg-gray-50 transition-colors ${
                  restrictedDataVisible && selectedCustomers.has(customer.id) ? 'bg-amber-50' : ''
                }`}
              >
                <td className="px-4 py-3">
                  <input
                    type="checkbox"
                    checked={selectedCustomers.has(customer.id)}
                    onChange={() => handleToggleCustomer(customer.id)}
                    className="w-4 h-4 text-purple-600 rounded"
                  />
                </td>
                <td className="px-4 py-3">
                  <CustomerRiskIndicator
                    riskType={customer.riskType}
                    details={customer.riskDetails}
                    onClick={() => onNavigateToCustomer(customer.id)}
                  />
                </td>
                <td className="px-4 py-3">
                  <div className="font-mono text-sm font-medium text-gray-900">
                    {customer.id}
                  </div>
                </td>
                <td className="px-4 py-3">
                  <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                    customer.accountType === 'registered'
                      ? 'bg-green-100 text-green-700'
                      : 'bg-gray-100 text-gray-700'
                  }`}>
                    {customer.accountType === 'registered' ? 'Registered' : 'Guest'}
                  </span>
                </td>
                <td className="px-4 py-3">
                  {restrictedDataVisible ? (
                    <div className="flex items-center gap-2">
                      <Lock className="w-3 h-3 text-amber-600" />
                      <span className="text-sm text-gray-900 font-medium">
                        {customer.email}
                      </span>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2 text-gray-400">
                      <Lock className="w-3 h-3" />
                      <span className="text-sm">Hidden</span>
                    </div>
                  )}
                </td>
                <td className="px-4 py-3">
                  {restrictedDataVisible ? (
                    <div className="flex items-center gap-2">
                      <Lock className="w-3 h-3 text-amber-600" />
                      <span className="text-sm text-gray-900 font-medium">
                        {customer.phone}
                      </span>
                    </div>
                  ) : (
                    <div className="flex items-center gap-2 text-gray-400">
                      <Lock className="w-3 h-3" />
                      <span className="text-sm">Hidden</span>
                    </div>
                  )}
                </td>
                <td className="px-4 py-3">
                  <span className="text-sm text-gray-900">{customer.totalOrders}</span>
                </td>
                <td className="px-4 py-3">
                  <span className="text-sm font-medium text-gray-900">{customer.totalSpend}</span>
                </td>
                <td className="px-4 py-3">
                  <span className="text-sm text-gray-600">{customer.lastActive}</span>
                </td>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => onNavigateToCustomer(customer.id)}
                      className="p-1.5 hover:bg-gray-100 rounded transition-colors"
                      title="View Customer Support Overview"
                    >
                      <Eye className="w-4 h-4 text-gray-600" />
                    </button>
                    <div className="relative group">
                      <button className="p-1.5 hover:bg-gray-100 rounded transition-colors">
                        <MoreVertical className="w-4 h-4 text-gray-600" />
                      </button>
                      {/* Dropdown menu would go here */}
                    </div>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {filteredCustomers.length === 0 && (
          <div className="py-12 text-center">
            <Users className="w-12 h-12 text-gray-300 mx-auto mb-3" />
            <p className="text-gray-600 font-medium">No customers found</p>
            <p className="text-sm text-gray-500 mt-1">
              {searchQuery ? 'Try a different Customer ID' : 'Adjust your filters'}
            </p>
          </div>
        )}
      </div>

      {/* Export Modal */}
      {showExportModal && (
        <ExportCustomerModal
          selectedCount={selectedCustomers.size}
          totalCount={filteredCustomers.length}
          onExport={handleExport}
          onClose={() => setShowExportModal(false)}
        />
      )}
    </div>
  );
}

// Export Modal Component
function ExportCustomerModal({
  selectedCount,
  totalCount,
  onExport,
  onClose
}: {
  selectedCount: number;
  totalCount: number;
  onExport: (includePersonalData: boolean, reason?: string) => void;
  onClose: () => void;
}) {
  const [includePersonalData, setIncludePersonalData] = useState(false);
  const [reason, setReason] = useState('');

  const handleExport = () => {
    if (includePersonalData && !reason.trim()) {
      toast.error('Please provide a reason for exporting personal data');
      return;
    }
    onExport(includePersonalData, reason);
  };

  return (
    <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div className="px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900">
            Export Customer Report
          </h3>
          <p className="text-sm text-gray-600 mt-1">
            Exporting {selectedCount > 0 ? `${selectedCount} selected` : `${totalCount} filtered`} customers
          </p>
        </div>
        
        <div className="px-6 py-4 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-3">
              Export Options
            </label>
            
            <label className="flex items-start gap-3 px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer mb-2">
              <input
                type="radio"
                name="export-type"
                checked={!includePersonalData}
                onChange={() => setIncludePersonalData(false)}
                className="w-4 h-4 text-purple-600 mt-0.5"
              />
              <div>
                <div className="text-sm font-medium text-gray-900">Aggregated data only (default)</div>
                <div className="text-xs text-gray-600 mt-0.5">Customer ID, orders, spend, loyalty points</div>
              </div>
            </label>

            <label className="flex items-start gap-3 px-4 py-3 border-2 border-amber-300 bg-amber-50 rounded-lg cursor-pointer">
              <input
                type="radio"
                name="export-type"
                checked={includePersonalData}
                onChange={() => setIncludePersonalData(true)}
                className="w-4 h-4 text-purple-600 mt-0.5"
              />
              <div>
                <div className="text-sm font-medium text-amber-900">Include personal data (restricted)</div>
                <div className="text-xs text-amber-800 mt-0.5">Includes email, phone • Requires reason & audit log</div>
              </div>
            </label>
          </div>

          {includePersonalData && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Reason for Personal Data Export *
              </label>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Explain why personal data is needed..."
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none"
                rows={3}
              />
            </div>
          )}

          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-start gap-2">
            <FileText className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
            <p className="text-xs text-blue-800">
              {includePersonalData 
                ? 'This export will be logged to Audit Trail with your admin ID, timestamp, and reason.'
                : 'Export will contain aggregated data only. No GDPR-sensitive information included.'}
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
            onClick={handleExport}
            className="px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors flex items-center gap-2"
          >
            <Download className="w-4 h-4" />
            Export to Excel
          </button>
        </div>
      </div>
    </div>
  );
}
