import { useState, useEffect } from 'react';
import { Search, Filter, Eye, Ban, AlertCircle } from 'lucide-react';
import { Button } from '../ui/button';

interface Vendor {
  id: string;
  businessName: string;
  email: string;
  country: string;
  status: 'setup' | 'active' | 'suspended';
  subscriptionPlan: string | null;
  subscriptionStatus: 'none' | 'active' | 'past_due' | 'canceled';
  createdAt: string;
  activatedAt: string | null;
  setupProgress: number;
}

interface VendorMonitoringProps {
  onViewVendor: (vendorId: string) => void;
  onSuspendVendor: (vendorId: string) => void;
}

export function VendorMonitoring({ onViewVendor, onSuspendVendor }: VendorMonitoringProps) {
  const [vendors, setVendors] = useState<Vendor[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');

  useEffect(() => {
    loadVendors();
  }, []);

  const loadVendors = async () => {
    setLoading(true);
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Mock data
    const mockVendors: Vendor[] = [
      {
        id: '1',
        businessName: 'Bella Italia',
        email: 'owner@bella-italia.com',
        country: 'Austria',
        status: 'active',
        subscriptionPlan: 'Monthly',
        subscriptionStatus: 'active',
        createdAt: '2025-01-15',
        activatedAt: '2025-01-16',
        setupProgress: 100
      },
      {
        id: '2',
        businessName: 'Sushi Palace',
        email: 'contact@sushi-palace.com',
        country: 'Germany',
        status: 'setup',
        subscriptionPlan: null,
        subscriptionStatus: 'none',
        createdAt: '2025-01-20',
        activatedAt: null,
        setupProgress: 45
      },
      {
        id: '3',
        businessName: 'Le Bistro',
        email: 'info@lebistro.fr',
        country: 'France',
        status: 'active',
        subscriptionPlan: 'Yearly',
        subscriptionStatus: 'active',
        createdAt: '2025-01-10',
        activatedAt: '2025-01-10',
        setupProgress: 100
      }
    ];
    
    setVendors(mockVendors);
    setLoading(false);
  };

  const filteredVendors = vendors.filter(vendor => {
    const matchesSearch = vendor.businessName.toLowerCase().includes(searchQuery.toLowerCase()) ||
                         vendor.email.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesStatus = statusFilter === 'all' || vendor.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  const getStatusBadge = (status: Vendor['status']) => {
    switch (status) {
      case 'active':
        return <span className="px-2 py-1 bg-emerald-100 text-emerald-700 text-xs rounded-full">Active</span>;
      case 'setup':
        return <span className="px-2 py-1 bg-amber-100 text-amber-700 text-xs rounded-full">Setup</span>;
      case 'suspended':
        return <span className="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">Suspended</span>;
    }
  };

  const getSubscriptionBadge = (status: Vendor['subscriptionStatus']) => {
    switch (status) {
      case 'active':
        return <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">Paid</span>;
      case 'past_due':
        return <span className="px-2 py-1 bg-orange-100 text-orange-700 text-xs rounded-full">Past Due</span>;
      case 'canceled':
        return <span className="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">Canceled</span>;
      case 'none':
        return <span className="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full">No subscription</span>;
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6">
          <h1 className="text-3xl text-gray-900 mb-1">Vendor Monitoring</h1>
          <p className="text-gray-600">Monitor vendor onboarding and subscription status</p>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        {/* Info Banner */}
        <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-3">
          <AlertCircle className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
          <div className="flex-1 text-sm text-blue-900">
            <p>
              <strong>Read-only monitoring.</strong> Vendors self-activate through subscription payment. Admin intervention is only needed for exceptions (suspension, document requests).
            </p>
          </div>
        </div>

        {/* Filters */}
        <div className="bg-white rounded-xl shadow-sm p-6 mb-6">
          <div className="flex flex-col sm:flex-row gap-4">
            {/* Search */}
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search vendors..."
                className="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            {/* Status Filter */}
            <div className="sm:w-48">
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="all">All statuses</option>
                <option value="setup">Setup</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
              </select>
            </div>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid sm:grid-cols-3 gap-6 mb-6">
          <div className="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div className="text-3xl text-emerald-600 mb-1">
              {vendors.filter(v => v.status === 'active').length}
            </div>
            <div className="text-sm text-gray-600">Active Vendors</div>
          </div>
          <div className="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div className="text-3xl text-amber-600 mb-1">
              {vendors.filter(v => v.status === 'setup').length}
            </div>
            <div className="text-sm text-gray-600">In Setup</div>
          </div>
          <div className="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
            <div className="text-3xl text-blue-600 mb-1">
              {vendors.filter(v => v.subscriptionStatus === 'active').length}
            </div>
            <div className="text-sm text-gray-600">Paid Subscriptions</div>
          </div>
        </div>

        {/* Vendors Table */}
        <div className="bg-white rounded-xl shadow-sm overflow-hidden">
          {loading ? (
            <div className="p-12 text-center text-gray-500">
              Loading vendors...
            </div>
          ) : filteredVendors.length === 0 ? (
            <div className="p-12 text-center text-gray-500">
              No vendors found
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                      Business
                    </th>
                    <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                      Subscription
                    </th>
                    <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                      Progress
                    </th>
                    <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                      Created
                    </th>
                    <th className="px-6 py-3 text-right text-xs text-gray-600 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {filteredVendors.map((vendor) => (
                    <tr key={vendor.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4">
                        <div>
                          <div className="text-gray-900">{vendor.businessName}</div>
                          <div className="text-sm text-gray-500">{vendor.email}</div>
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        {getStatusBadge(vendor.status)}
                      </td>
                      <td className="px-6 py-4">
                        <div className="space-y-1">
                          {getSubscriptionBadge(vendor.subscriptionStatus)}
                          {vendor.subscriptionPlan && (
                            <div className="text-xs text-gray-500">{vendor.subscriptionPlan}</div>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-2">
                          <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div
                              className="h-full bg-emerald-500"
                              style={{ width: `${vendor.setupProgress}%` }}
                            />
                          </div>
                          <span className="text-xs text-gray-600 w-10">{vendor.setupProgress}%</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-sm text-gray-600">
                        {new Date(vendor.createdAt).toLocaleDateString()}
                      </td>
                      <td className="px-6 py-4 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <Button
                            onClick={() => onViewVendor(vendor.id)}
                            variant="ghost"
                            size="sm"
                            className="text-blue-600 hover:text-blue-700"
                          >
                            <Eye className="w-4 h-4" />
                          </Button>
                          {vendor.status !== 'suspended' && (
                            <Button
                              onClick={() => onSuspendVendor(vendor.id)}
                              variant="ghost"
                              size="sm"
                              className="text-red-600 hover:text-red-700"
                            >
                              <Ban className="w-4 h-4" />
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
