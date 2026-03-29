import { useState } from 'react';
import { Search, Filter, Eye, Ban, CheckCircle, AlertTriangle, TrendingUp, DollarSign, Users, Star, ChevronLeft, ChevronRight, Download, MapPin, MoreVertical, Calendar } from 'lucide-react';
import { Input } from '../ui/input';
import { AIRiskIndicator, AITooltip } from '../ai/AIComponents';
import { VendorDetailsModal } from './VendorDetailsModal';

interface Vendor {
  id: string;
  name: string;
  type: string;
  city: string;
  country: string;
  status: 'active' | 'pending' | 'suspended';
  subscriptionPlan: string;
  subscriptionStatus: 'paid' | 'overdue' | 'trial';
  grossOrderValue: number; // Changed from monthlyRevenue
  rating: number;
  reviewCount: number;
  joinedDate: string;
  lastActive: string;
}

export function VendorsList() {
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedVendor, setSelectedVendor] = useState<Vendor | null>(null);
  const [showDetailsModal, setShowDetailsModal] = useState(false);
  const itemsPerPage = 15;

  const vendors: Vendor[] = [
    {
      id: 'v_001',
      name: 'Bella Italia',
      type: 'Italian Restaurant',
      city: 'Vienna',
      country: 'Austria',
      status: 'active',
      subscriptionPlan: 'Premium',
      subscriptionStatus: 'paid',
      grossOrderValue: 8450,
      rating: 4.8,
      reviewCount: 234,
      joinedDate: '2024-01-15',
      lastActive: '2 min ago'
    },
    {
      id: 'v_002',
      name: 'Sakura Sushi',
      type: 'Japanese Restaurant',
      city: 'Salzburg',
      country: 'Austria',
      status: 'pending',
      subscriptionPlan: 'Standard',
      subscriptionStatus: 'trial',
      grossOrderValue: 0,
      rating: 0,
      reviewCount: 0,
      joinedDate: '2024-06-10',
      lastActive: '5 hours ago'
    },
    {
      id: 'v_003',
      name: 'Cafe Noir',
      type: 'Café',
      city: 'Graz',
      country: 'Austria',
      status: 'active',
      subscriptionPlan: 'Basic',
      subscriptionStatus: 'overdue',
      grossOrderValue: 2340,
      rating: 4.5,
      reviewCount: 89,
      joinedDate: '2024-03-22',
      lastActive: '1 day ago'
    },
    {
      id: 'v_004',
      name: 'Burger Palace',
      type: 'Fast Food',
      city: 'Linz',
      country: 'Austria',
      status: 'active',
      subscriptionPlan: 'Standard',
      subscriptionStatus: 'paid',
      grossOrderValue: 5680,
      rating: 4.2,
      reviewCount: 156,
      joinedDate: '2024-02-08',
      lastActive: '1 hour ago'
    },
    {
      id: 'v_005',
      name: 'Green Bowl',
      type: 'Healthy Café',
      city: 'Innsbruck',
      country: 'Austria',
      status: 'suspended',
      subscriptionPlan: 'Basic',
      subscriptionStatus: 'overdue',
      grossOrderValue: 1240,
      rating: 3.8,
      reviewCount: 42,
      joinedDate: '2024-04-12',
      lastActive: '3 days ago'
    },
    {
      id: 'v_006',
      name: 'Taco House',
      type: 'Mexican Restaurant',
      city: 'Vienna',
      country: 'Austria',
      status: 'active',
      subscriptionPlan: 'Premium',
      subscriptionStatus: 'paid',
      grossOrderValue: 9870,
      rating: 4.6,
      reviewCount: 298,
      joinedDate: '2023-11-20',
      lastActive: '30 min ago'
    },
  ];

  const getStatusBadge = (status: string) => {
    const styles = {
      active: 'bg-green-100 text-green-700',
      pending: 'bg-yellow-100 text-yellow-700',
      suspended: 'bg-red-100 text-red-700',
    };
    return styles[status as keyof typeof styles] || styles.active;
  };

  const getSubscriptionBadge = (status: string) => {
    const styles = {
      paid: 'bg-green-100 text-green-700',
      overdue: 'bg-red-100 text-red-700',
      trial: 'bg-blue-100 text-blue-700',
    };
    return styles[status as keyof typeof styles] || styles.paid;
  };

  const getPlanColor = (plan: string) => {
    const colors = {
      Premium: 'text-purple-600',
      Standard: 'text-blue-600',
      Basic: 'text-gray-600',
    };
    return colors[plan as keyof typeof colors] || colors.Basic;
  };

  const totalPages = Math.ceil(vendors.length / itemsPerPage);

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl mb-1">Vendor Management</h1>
        <p className="text-sm text-gray-500">Manage all vendors, approvals, and subscriptions</p>
      </div>

      {/* Filters & Actions */}
      <div className="bg-white rounded-xl border border-gray-200 mb-6">
        <div className="p-4">
          <div className="flex flex-col lg:flex-row gap-4">
            {/* Search */}
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <Input
                  type="text"
                  placeholder="Search by vendor name, city, or type..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-10"
                />
              </div>
            </div>

            {/* Filters */}
            <div className="flex gap-3">
              <select 
                className="px-4 py-2 border border-gray-200 rounded-lg text-sm"
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
              >
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="suspended">Suspended</option>
              </select>

              <select className="px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option>All Plans</option>
                <option>Premium</option>
                <option>Standard</option>
                <option>Basic</option>
              </select>

              <select className="px-4 py-2 border border-gray-200 rounded-lg text-sm">
                <option>All Countries</option>
                <option>Austria</option>
                <option>Germany</option>
                <option>Switzerland</option>
              </select>

              <button className="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 text-sm">
                <Filter className="w-4 h-4" />
                More
              </button>

              <button className="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 text-sm">
                <Download className="w-4 h-4" />
                Export
              </button>
            </div>
          </div>
        </div>

        {/* Stats Bar */}
        <div className="border-t border-gray-200 p-4 flex items-center gap-6 text-sm">
          <div>
            <span className="text-gray-500">Total:</span>{' '}
            <span className="font-semibold">1,247</span>
          </div>
          <div className="flex items-center gap-1.5">
            <div className="w-2 h-2 bg-green-500 rounded-full"></div>
            <span className="text-gray-500">Active:</span>{' '}
            <span className="font-semibold">1,089</span>
          </div>
          <div className="flex items-center gap-1.5">
            <div className="w-2 h-2 bg-yellow-500 rounded-full"></div>
            <span className="text-gray-500">Pending:</span>{' '}
            <span className="font-semibold">5</span>
          </div>
          <div className="flex items-center gap-1.5">
            <div className="w-2 h-2 bg-red-500 rounded-full"></div>
            <span className="text-gray-500">Suspended:</span>{' '}
            <span className="font-semibold">3</span>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  <input type="checkbox" className="rounded border-gray-300" />
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Vendor
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Location
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Subscription
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Payment
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider group relative cursor-help">
                  <div className="flex items-center gap-1">
                    Gross Order Value
                    <span className="text-[10px] text-gray-400">(via Tavlo)</span>
                  </div>
                  <div className="hidden group-hover:block absolute z-10 w-64 px-3 py-2 text-xs text-white bg-gray-900 rounded-lg -bottom-16 left-0 normal-case">
                    Total order value processed through Tavlo. This is not vendor profit.
                  </div>
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Rating
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Last Active
                </th>
                <th className="px-6 py-3 text-right text-xs text-gray-600 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {vendors.map((vendor) => (
                <tr key={vendor.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4">
                    <input type="checkbox" className="rounded border-gray-300" />
                  </td>
                  <td className="px-6 py-4">
                    <div>
                      <div className="font-medium text-gray-900">{vendor.name}</div>
                      <div className="text-sm text-gray-500">{vendor.type}</div>
                      <div className="text-xs text-gray-400 mt-0.5">ID: {vendor.id}</div>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-1.5 text-sm text-gray-700">
                      <MapPin className="w-3.5 h-3.5 text-gray-400" />
                      <span>{vendor.city}, {vendor.country}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${getStatusBadge(vendor.status)}`}>
                      {vendor.status.charAt(0).toUpperCase() + vendor.status.slice(1)}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    <div className={`text-sm font-medium ${getPlanColor(vendor.subscriptionPlan)}`}>
                      {vendor.subscriptionPlan}
                    </div>
                    <div className="text-xs text-gray-500">
                      <Calendar className="w-3 h-3 inline mr-1" />
                      Since {new Date(vendor.joinedDate).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${getSubscriptionBadge(vendor.subscriptionStatus)}`}>
                      {vendor.subscriptionStatus.charAt(0).toUpperCase() + vendor.subscriptionStatus.slice(1)}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-1.5">
                      <DollarSign className="w-4 h-4 text-gray-400" />
                      <span className="text-sm font-medium">€{vendor.grossOrderValue.toLocaleString()}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    {vendor.rating > 0 ? (
                      <div>
                        <div className="flex items-center gap-1">
                          <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
                          <span className="text-sm font-medium">{vendor.rating}</span>
                        </div>
                        <div className="text-xs text-gray-500">{vendor.reviewCount} reviews</div>
                      </div>
                    ) : (
                      <span className="text-sm text-gray-400">No reviews</span>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <span className="text-sm text-gray-600">{vendor.lastActive}</span>
                  </td>
                  <td className="px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button className="p-1.5 hover:bg-gray-100 rounded-lg" title="View details" onClick={() => { setSelectedVendor(vendor); setShowDetailsModal(true); }}>
                        <Eye className="w-4 h-4 text-gray-600" />
                      </button>
                      {vendor.status === 'pending' && (
                        <button className="p-1.5 hover:bg-green-100 rounded-lg" title="Approve">
                          <CheckCircle className="w-4 h-4 text-green-600" />
                        </button>
                      )}
                      {vendor.status === 'active' && (
                        <button className="p-1.5 hover:bg-red-100 rounded-lg" title="Suspend">
                          <Ban className="w-4 h-4 text-red-600" />
                        </button>
                      )}
                      <button className="p-1.5 hover:bg-gray-100 rounded-lg">
                        <MoreVertical className="w-4 h-4 text-gray-600" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="border-t border-gray-200 px-6 py-4 flex items-center justify-between">
          <div className="text-sm text-gray-500">
            Showing {((currentPage - 1) * itemsPerPage) + 1} to {Math.min(currentPage * itemsPerPage, vendors.length)} of {vendors.length} vendors
          </div>
          <div className="flex items-center gap-2">
            <button 
              className="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage(p => p - 1)}
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            {[...Array(totalPages)].map((_, i) => (
              <button
                key={i}
                className={`px-3 py-1.5 rounded-lg text-sm ${
                  currentPage === i + 1
                    ? 'bg-purple-600 text-white'
                    : 'border border-gray-200 hover:bg-gray-50'
                }`}
                onClick={() => setCurrentPage(i + 1)}
              >
                {i + 1}
              </button>
            ))}
            <button 
              className="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              disabled={currentPage === totalPages}
              onClick={() => setCurrentPage(p => p + 1)}
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Bulk Actions */}
      <div className="mt-4 flex items-center gap-3">
        <span className="text-sm text-gray-600">Bulk Actions:</span>
        <button className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
          Approve Selected
        </button>
        <button className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
          Suspend Selected
        </button>
        <button className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
          Export Selected
        </button>
      </div>

      {/* Vendor Details Modal */}
      {showDetailsModal && selectedVendor && (
        <VendorDetailsModal 
          vendor={selectedVendor} 
          isOpen={showDetailsModal}
          onClose={() => setShowDetailsModal(false)} 
        />
      )}
    </div>
  );
}