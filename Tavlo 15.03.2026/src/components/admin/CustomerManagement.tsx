import { useState } from 'react';
import { 
  Search, 
  Filter, 
  Download, 
  Eye, 
  Flag, 
  Ban, 
  AlertTriangle,
  User,
  Mail,
  Phone,
  Calendar,
  ShoppingBag,
  Star,
  Gift,
  TrendingUp,
  MapPin,
  ChevronLeft,
  ChevronRight,
  MessageSquare
} from 'lucide-react';
import { Input } from '../ui/input';

interface Customer {
  id: string;
  name: string;
  email: string;
  phone: string;
  status: 'guest' | 'registered';
  totalOrders: number;
  totalSpend: number;
  tipsGiven: number;
  loyaltyPoints: number;
  lastActivity: string;
  joinedDate: string;
  isFlagged: boolean;
  flagReason?: string;
  avgRating: number;
  favoriteRestaurant: string;
}

interface CustomerManagementProps {
  page?: string;
}

export function CustomerManagement({ page }: CustomerManagementProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [showFlaggedOnly, setShowFlaggedOnly] = useState(false);
  const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 15;

  const customers: Customer[] = [
    {
      id: 'c_001',
      name: 'John Smith',
      email: 'john.smith@email.com',
      phone: '+43 660 123 4567',
      status: 'registered',
      totalOrders: 47,
      totalSpend: 1247.50,
      tipsGiven: 89.40,
      loyaltyPoints: 623,
      lastActivity: '2 hours ago',
      joinedDate: '2024-01-15',
      isFlagged: false,
      avgRating: 4.8,
      favoriteRestaurant: 'Bella Italia'
    },
    {
      id: 'c_002',
      name: 'Sarah Johnson',
      email: 'sarah.j@email.com',
      phone: '+43 660 234 5678',
      status: 'registered',
      totalOrders: 89,
      totalSpend: 2834.90,
      tipsGiven: 245.60,
      loyaltyPoints: 1417,
      lastActivity: '1 day ago',
      joinedDate: '2023-11-20',
      isFlagged: false,
      avgRating: 4.9,
      favoriteRestaurant: 'Sakura Sushi'
    },
    {
      id: 'c_003',
      name: 'Mike Peterson',
      email: 'mike.p@email.com',
      phone: '+43 660 345 6789',
      status: 'registered',
      totalOrders: 12,
      totalSpend: 389.20,
      tipsGiven: 12.50,
      loyaltyPoints: 194,
      lastActivity: '3 days ago',
      joinedDate: '2024-04-10',
      isFlagged: true,
      flagReason: 'Multiple complaints about order quality',
      avgRating: 2.3,
      favoriteRestaurant: 'Burger Palace'
    },
    {
      id: 'g_004',
      name: 'Guest User',
      email: '',
      phone: '',
      status: 'guest',
      totalOrders: 3,
      totalSpend: 87.40,
      tipsGiven: 5.20,
      loyaltyPoints: 0,
      lastActivity: '1 week ago',
      joinedDate: '2024-06-01',
      isFlagged: false,
      avgRating: 4.5,
      favoriteRestaurant: 'Taco House'
    },
    {
      id: 'c_005',
      name: 'Emma Wilson',
      email: 'emma.w@email.com',
      phone: '+43 660 456 7890',
      status: 'registered',
      totalOrders: 156,
      totalSpend: 4892.30,
      tipsGiven: 478.90,
      loyaltyPoints: 2446,
      lastActivity: '30 min ago',
      joinedDate: '2023-08-05',
      isFlagged: false,
      avgRating: 5.0,
      favoriteRestaurant: 'Bella Italia'
    },
  ];

  const filteredCustomers = customers.filter(customer => {
    const matchesSearch = 
      customer.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      customer.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
      customer.id.toLowerCase().includes(searchQuery.toLowerCase());
    
    const matchesStatus = statusFilter === 'all' || customer.status === statusFilter;
    const matchesFlagged = !showFlaggedOnly || customer.isFlagged;

    return matchesSearch && matchesStatus && matchesFlagged;
  });

  const totalPages = Math.ceil(filteredCustomers.length / itemsPerPage);
  const paginatedCustomers = filteredCustomers.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage
  );

  if (selectedCustomer) {
    return <CustomerProfile customer={selectedCustomer} onBack={() => setSelectedCustomer(null)} />;
  }

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl mb-1">Customer Management</h1>
        <p className="text-sm text-gray-500">View and manage all customer accounts</p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Total Customers</span>
            <User className="w-4 h-4 text-blue-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">48,392</div>
          <div className="text-xs text-green-600">+8.4% vs last month</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Registered</span>
            <Mail className="w-4 h-4 text-green-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">12,384</div>
          <div className="text-xs text-gray-500">25.6% of total</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Flagged Accounts</span>
            <Flag className="w-4 h-4 text-red-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">23</div>
          <div className="text-xs text-red-600">Requires attention</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Avg Spend</span>
            <TrendingUp className="w-4 h-4 text-purple-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">€67.80</div>
          <div className="text-xs text-green-600">+12% this month</div>
        </div>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl border border-gray-200 p-4 mb-6">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <Input
                type="text"
                placeholder="Search by name, email, or customer ID..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-10"
              />
            </div>
          </div>
          <div className="flex gap-3">
            <select 
              className="px-4 py-2 border border-gray-200 rounded-lg text-sm"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="all">All Customers</option>
              <option value="registered">Registered Only</option>
              <option value="guest">Guest Only</option>
            </select>
            <label className="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer text-sm">
              <input 
                type="checkbox" 
                checked={showFlaggedOnly}
                onChange={(e) => setShowFlaggedOnly(e.target.checked)}
                className="rounded"
              />
              Flagged Only
            </label>
            <button className="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 text-sm">
              <Download className="w-4 h-4" />
              Export
            </button>
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
                  Customer
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Contact
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Orders
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Total Spend
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Tips Given
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Loyalty Points
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Last Activity
                </th>
                <th className="px-6 py-3 text-right text-xs text-gray-600 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {paginatedCustomers.map((customer) => (
                <tr key={customer.id} className={`hover:bg-gray-50 ${customer.isFlagged ? 'bg-red-50' : ''}`}>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-3">
                      {customer.isFlagged && (
                        <Flag className="w-4 h-4 text-red-500 shrink-0" />
                      )}
                      <div>
                        <div className="font-medium text-gray-900">{customer.name}</div>
                        <div className="text-xs text-gray-500">ID: {customer.id}</div>
                        {customer.favoriteRestaurant && (
                          <div className="text-xs text-purple-600 mt-0.5">
                            ♥ {customer.favoriteRestaurant}
                          </div>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    {customer.status === 'registered' ? (
                      <div className="text-sm">
                        <div className="flex items-center gap-1.5 text-gray-700">
                          <Mail className="w-3.5 h-3.5 text-gray-400" />
                          {customer.email}
                        </div>
                        <div className="flex items-center gap-1.5 text-gray-600 mt-1">
                          <Phone className="w-3.5 h-3.5 text-gray-400" />
                          {customer.phone}
                        </div>
                      </div>
                    ) : (
                      <span className="text-sm text-gray-400">Guest user</span>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
                      customer.status === 'registered' 
                        ? 'bg-green-100 text-green-700' 
                        : 'bg-gray-100 text-gray-700'
                    }`}>
                      {customer.status === 'registered' ? 'Registered' : 'Guest'}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-1.5">
                      <ShoppingBag className="w-4 h-4 text-gray-400" />
                      <span className="text-sm font-medium">{customer.totalOrders}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <span className="text-sm font-medium">€{customer.totalSpend.toFixed(2)}</span>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-1.5">
                      <Gift className="w-4 h-4 text-orange-400" />
                      <span className="text-sm">€{customer.tipsGiven.toFixed(2)}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-1.5">
                      <Star className="w-4 h-4 text-yellow-400 fill-yellow-400" />
                      <span className="text-sm font-medium">{customer.loyaltyPoints}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <span className="text-sm text-gray-600">{customer.lastActivity}</span>
                  </td>
                  <td className="px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button 
                        onClick={() => setSelectedCustomer(customer)}
                        className="p-1.5 hover:bg-gray-100 rounded-lg"
                        title="View profile"
                      >
                        <Eye className="w-4 h-4 text-gray-600" />
                      </button>
                      {!customer.isFlagged ? (
                        <button className="p-1.5 hover:bg-red-100 rounded-lg" title="Flag account">
                          <Flag className="w-4 h-4 text-red-600" />
                        </button>
                      ) : (
                        <button className="p-1.5 hover:bg-red-100 rounded-lg" title="Restrict account">
                          <Ban className="w-4 h-4 text-red-600" />
                        </button>
                      )}
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
            Showing {((currentPage - 1) * itemsPerPage) + 1} to {Math.min(currentPage * itemsPerPage, filteredCustomers.length)} of {filteredCustomers.length} customers
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
    </div>
  );
}

function CustomerProfile({ customer, onBack }: { customer: Customer; onBack: () => void }) {
  return (
    <div className="p-6">
      {/* Header */}
      <button onClick={onBack} className="flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-6">
        <ChevronLeft className="w-4 h-4" />
        Back to customers
      </button>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Info */}
        <div className="lg:col-span-2 space-y-6">
          {/* Profile Card */}
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <div className="flex items-start justify-between mb-6">
              <div className="flex items-start gap-4">
                <div className="w-16 h-16 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                  <User className="w-8 h-8 text-white" />
                </div>
                <div>
                  <h1 className="text-2xl mb-1">{customer.name}</h1>
                  <div className="flex items-center gap-2 mb-2">
                    <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                      customer.status === 'registered' 
                        ? 'bg-green-100 text-green-700' 
                        : 'bg-gray-100 text-gray-700'
                    }`}>
                      {customer.status === 'registered' ? 'Registered' : 'Guest'}
                    </span>
                    {customer.isFlagged && (
                      <span className="px-2.5 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium">
                        Flagged
                      </span>
                    )}
                  </div>
                  <div className="text-sm text-gray-500">Customer ID: {customer.id}</div>
                </div>
              </div>
            </div>

            {customer.isFlagged && (
              <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg flex items-start gap-3">
                <AlertTriangle className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                <div>
                  <div className="font-medium text-red-900 mb-1">Account Flagged</div>
                  <div className="text-sm text-red-700">{customer.flagReason}</div>
                </div>
              </div>
            )}

            <div className="grid grid-cols-2 gap-6">
              {customer.email && (
                <div>
                  <div className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                    <Mail className="w-4 h-4" />
                    Email
                  </div>
                  <div className="text-sm font-medium">{customer.email}</div>
                </div>
              )}
              {customer.phone && (
                <div>
                  <div className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                    <Phone className="w-4 h-4" />
                    Phone
                  </div>
                  <div className="text-sm font-medium">{customer.phone}</div>
                </div>
              )}
              <div>
                <div className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                  <Calendar className="w-4 h-4" />
                  Joined
                </div>
                <div className="text-sm font-medium">
                  {new Date(customer.joinedDate).toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                  })}
                </div>
              </div>
              <div>
                <div className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                  <Star className="w-4 h-4" />
                  Avg Rating Given
                </div>
                <div className="text-sm font-medium">{customer.avgRating.toFixed(1)} / 5.0</div>
              </div>
            </div>
          </div>

          {/* Stats */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-white rounded-xl border border-gray-200 p-4 text-center">
              <div className="text-2xl font-semibold text-blue-600 mb-1">{customer.totalOrders}</div>
              <div className="text-sm text-gray-600">Total Orders</div>
            </div>
            <div className="bg-white rounded-xl border border-gray-200 p-4 text-center">
              <div className="text-2xl font-semibold text-green-600 mb-1">€{customer.totalSpend.toFixed(0)}</div>
              <div className="text-sm text-gray-600">Total Spend</div>
            </div>
            <div className="bg-white rounded-xl border border-gray-200 p-4 text-center">
              <div className="text-2xl font-semibold text-orange-600 mb-1">€{customer.tipsGiven.toFixed(0)}</div>
              <div className="text-sm text-gray-600">Tips Given</div>
            </div>
            <div className="bg-white rounded-xl border border-gray-200 p-4 text-center">
              <div className="text-2xl font-semibold text-purple-600 mb-1">{customer.loyaltyPoints}</div>
              <div className="text-sm text-gray-600">Loyalty Points</div>
            </div>
          </div>

          {/* Recent Activity */}
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <h2 className="text-lg mb-4">Recent Activity</h2>
            <div className="text-sm text-gray-500">Activity log coming soon...</div>
          </div>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Actions */}
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <h3 className="font-medium mb-4">Actions</h3>
            <div className="space-y-2">
              <button className="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                Send Email
              </button>
              <button className="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                Send SMS
              </button>
              {!customer.isFlagged ? (
                <button className="w-full py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                  Flag Account
                </button>
              ) : (
                <button className="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                  Remove Flag
                </button>
              )}
              <button className="w-full py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                View Order History
              </button>
              <button className="w-full py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                View Reviews
              </button>
            </div>
          </div>

          {/* Admin Notes */}
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <h3 className="font-medium mb-4">Admin Notes</h3>
            <textarea
              className="w-full p-3 border border-gray-200 rounded-lg text-sm resize-none"
              rows={6}
              placeholder="Add internal notes..."
            />
            <button className="w-full mt-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
              Save Notes
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}