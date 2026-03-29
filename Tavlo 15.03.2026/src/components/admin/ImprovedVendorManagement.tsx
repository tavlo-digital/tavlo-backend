import { useState } from 'react';
import { Store, Search, Filter, Eye, FileText, AlertTriangle, TrendingUp, DollarSign, ShoppingCart, Star, ExternalLink, Shield, Ban, CheckCircle, Clock, Info } from 'lucide-react';
import { Card } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Badge } from '../ui/badge';

interface Vendor {
  id: string;
  name: string;
  status: 'setup' | 'active' | 'overdue' | 'suspended';
  subscription: {
    plan: string;
    status: string;
    nextBilling: string;
  };
  grossOrderValue: number;
  orderCount: number;
  rating: number;
  reviewCount: number;
  joinedDate: string;
  lastActivity: string;
  riskLevel: 'low' | 'medium' | 'high';
}

export function ImprovedVendorManagement() {
  const [selectedStatus, setSelectedStatus] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedVendor, setSelectedVendor] = useState<Vendor | null>(null);

  // Mock vendor data
  const vendors: Vendor[] = [
    {
      id: 'V-1001',
      name: 'La Bella Vista',
      status: 'active',
      subscription: { plan: 'Premium', status: 'active', nextBilling: '2025-01-15' },
      grossOrderValue: 48920,
      orderCount: 1247,
      rating: 3.2,
      reviewCount: 89,
      joinedDate: '2023-08-15',
      lastActivity: '2 hours ago',
      riskLevel: 'high'
    },
    {
      id: 'V-1002',
      name: 'Sakura Sushi',
      status: 'active',
      subscription: { plan: 'Standard', status: 'active', nextBilling: '2025-01-12' },
      grossOrderValue: 32450,
      orderCount: 892,
      rating: 4.7,
      reviewCount: 124,
      joinedDate: '2024-01-10',
      lastActivity: '15 min ago',
      riskLevel: 'low'
    },
    {
      id: 'V-1003',
      name: 'Burger Palace',
      status: 'overdue',
      subscription: { plan: 'Standard', status: 'past_due', nextBilling: '2024-12-20' },
      grossOrderValue: 22180,
      orderCount: 634,
      rating: 4.1,
      reviewCount: 67,
      joinedDate: '2024-03-22',
      lastActivity: '1 day ago',
      riskLevel: 'medium'
    },
    {
      id: 'V-1004',
      name: 'Green Bowl Cafe',
      status: 'setup',
      subscription: { plan: 'Basic', status: 'pending', nextBilling: 'N/A' },
      grossOrderValue: 0,
      orderCount: 0,
      rating: 0,
      reviewCount: 0,
      joinedDate: '2024-12-24',
      lastActivity: '3 hours ago',
      riskLevel: 'low'
    },
    {
      id: 'V-1005',
      name: 'Pizza Express',
      status: 'suspended',
      subscription: { plan: 'Standard', status: 'suspended', nextBilling: 'N/A' },
      grossOrderValue: 15670,
      orderCount: 421,
      rating: 3.8,
      reviewCount: 45,
      joinedDate: '2024-02-18',
      lastActivity: '5 days ago',
      riskLevel: 'high'
    },
  ];

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'active':
        return <Badge className="bg-green-100 text-green-700 border-green-200">🟢 Active</Badge>;
      case 'overdue':
        return <Badge className="bg-orange-100 text-orange-700 border-orange-200">🟡 Overdue</Badge>;
      case 'suspended':
        return <Badge className="bg-red-100 text-red-700 border-red-200">🔴 Suspended</Badge>;
      case 'setup':
        return <Badge className="bg-blue-100 text-blue-700 border-blue-200">⚙️ Setup</Badge>;
      default:
        return <Badge>{status}</Badge>;
    }
  };

  const getRiskBadge = (risk: string) => {
    switch (risk) {
      case 'high':
        return <Badge variant="destructive">High Risk</Badge>;
      case 'medium':
        return <Badge variant="outline" className="border-orange-400 text-orange-700">Medium Risk</Badge>;
      case 'low':
        return <Badge variant="outline" className="border-green-400 text-green-700">Low Risk</Badge>;
      default:
        return null;
    }
  };

  return (
    <div className="p-6 max-w-[1800px] mx-auto">
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-2">
          <div>
            <h1 className="text-2xl mb-1">Vendor Management</h1>
            <p className="text-sm text-gray-500">
              Observe and monitor restaurant activity • Admin cannot edit menus or prices
            </p>
          </div>
          <Button variant="outline" className="flex items-center gap-2">
            <FileText className="w-4 h-4" />
            Export Vendor Report
          </Button>
        </div>
      </div>

      {/* Important Notice */}
      <Card className="mb-6 p-4 bg-blue-50 border-blue-200">
        <div className="flex items-start gap-3">
          <Info className="w-5 h-5 text-blue-600 mt-0.5" />
          <div className="flex-1">
            <h3 className="font-medium text-blue-900 mb-1">Admin Role & Boundaries</h3>
            <p className="text-sm text-blue-700">
              Tavlo admin can <strong>observe</strong> vendor activity, manage subscriptions, and enforce platform rules. 
              Admin <strong>cannot</strong> edit restaurant menus, modify prices, change branding, or interfere with live orders. 
              All actions are logged in the Audit Trail.
            </p>
          </div>
        </div>
      </Card>

      {/* Stats Overview */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <Card className="p-4">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
              <Store className="w-5 h-5" />
            </div>
            <div>
              <div className="text-2xl">1,247</div>
              <div className="text-sm text-gray-600">Total Vendors</div>
            </div>
          </div>
          <div className="text-xs text-gray-500">Aggregated platform data</div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center">
              <DollarSign className="w-5 h-5" />
            </div>
            <div>
              <div className="text-2xl">€2.4M</div>
              <div className="text-sm text-gray-600">Gross Order Value</div>
            </div>
          </div>
          <div className="text-xs text-gray-500">Via Tavlo platform (not vendor profit)</div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
              <ShoppingCart className="w-5 h-5" />
            </div>
            <div>
              <div className="text-2xl">89,234</div>
              <div className="text-sm text-gray-600">Total Orders</div>
            </div>
          </div>
          <div className="text-xs text-gray-500">Platform-wide order volume</div>
        </Card>

        <Card className="p-4">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-orange-100 text-orange-600 rounded-lg flex items-center justify-center">
              <AlertTriangle className="w-5 h-5" />
            </div>
            <div>
              <div className="text-2xl">17</div>
              <div className="text-sm text-gray-600">Requires Review</div>
            </div>
          </div>
          <div className="text-xs text-gray-500">High-risk vendors need attention</div>
        </Card>
      </div>

      {/* Filters */}
      <Card className="mb-6 p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="md:col-span-2">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <Input
                type="text"
                placeholder="Search vendors by name or ID..."
                className="pl-10"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </div>
          </div>
          <div>
            <select
              className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm"
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
            >
              <option value="all">All Status</option>
              <option value="active">Active</option>
              <option value="setup">Setup Mode</option>
              <option value="overdue">Overdue</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
          <Button variant="outline" className="flex items-center gap-2">
            <Filter className="w-4 h-4" />
            More Filters
          </Button>
        </div>
      </Card>

      {/* Vendors Table */}
      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Subscription</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">
                  Gross Order Value
                  <div className="text-xs text-gray-400 normal-case mt-0.5">(via Tavlo)</div>
                </th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Orders</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Rating</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Risk Level</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {vendors.map((vendor) => (
                <tr key={vendor.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4">
                    <div>
                      <div className="font-medium text-gray-900">{vendor.name}</div>
                      <div className="text-sm text-gray-500">ID: {vendor.id}</div>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    {getStatusBadge(vendor.status)}
                  </td>
                  <td className="px-6 py-4">
                    <div>
                      <div className="text-sm">{vendor.subscription.plan}</div>
                      <div className="text-xs text-gray-500">{vendor.subscription.status}</div>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="font-medium">€{vendor.grossOrderValue.toLocaleString()}</div>
                    <div className="text-xs text-gray-500">Support-only visibility</div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="font-medium">{vendor.orderCount.toLocaleString()}</div>
                  </td>
                  <td className="px-6 py-4">
                    {vendor.rating > 0 ? (
                      <div className="flex items-center gap-1">
                        <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
                        <span className="font-medium">{vendor.rating}</span>
                        <span className="text-xs text-gray-500">({vendor.reviewCount})</span>
                      </div>
                    ) : (
                      <span className="text-gray-400 text-sm">No ratings</span>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    {getRiskBadge(vendor.riskLevel)}
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-2">
                      <Button 
                        variant="ghost" 
                        size="sm"
                        onClick={() => setSelectedVendor(vendor)}
                      >
                        <Eye className="w-4 h-4" />
                      </Button>
                      <Button variant="ghost" size="sm">
                        <FileText className="w-4 h-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      {/* Vendor Detail Modal */}
      {selectedVendor && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <Card className="max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b border-gray-200">
              <div className="flex items-center justify-between mb-4">
                <div>
                  <h2 className="text-2xl mb-2">{selectedVendor.name}</h2>
                  <div className="flex items-center gap-2">
                    {getStatusBadge(selectedVendor.status)}
                    {getRiskBadge(selectedVendor.riskLevel)}
                  </div>
                </div>
                <Button variant="ghost" onClick={() => setSelectedVendor(null)}>
                  ✕
                </Button>
              </div>
              <p className="text-sm text-gray-500">
                Vendor ID: {selectedVendor.id} • Joined: {selectedVendor.joinedDate} • Last active: {selectedVendor.lastActivity}
              </p>
            </div>

            <div className="p-6 space-y-6">
              {/* Admin Notice */}
              <Card className="p-4 bg-amber-50 border-amber-200">
                <div className="flex items-start gap-3">
                  <Shield className="w-5 h-5 text-amber-600 mt-0.5" />
                  <div>
                    <h3 className="font-medium text-amber-900 mb-1">Read-Only View</h3>
                    <p className="text-sm text-amber-700">
                      This is observation-only data. Admin cannot modify restaurant menus, prices, or branding.
                      Only suspension/reactivation and internal notes are permitted.
                    </p>
                  </div>
                </div>
              </Card>

              {/* Activity Metrics */}
              <div>
                <h3 className="font-medium mb-4">Activity Metrics (Aggregated)</h3>
                <div className="grid grid-cols-3 gap-4">
                  <Card className="p-4">
                    <div className="text-2xl mb-1">€{selectedVendor.grossOrderValue.toLocaleString()}</div>
                    <div className="text-sm text-gray-600">Gross Order Value (via Tavlo)</div>
                    <div className="text-xs text-gray-500 mt-1">Not vendor profit</div>
                  </Card>
                  <Card className="p-4">
                    <div className="text-2xl mb-1">{selectedVendor.orderCount}</div>
                    <div className="text-sm text-gray-600">Total Orders</div>
                    <div className="text-xs text-gray-500 mt-1">Platform processed</div>
                  </Card>
                  <Card className="p-4">
                    <div className="text-2xl mb-1">
                      {selectedVendor.rating > 0 ? selectedVendor.rating : 'N/A'}
                    </div>
                    <div className="text-sm text-gray-600">Average Rating</div>
                    <div className="text-xs text-gray-500 mt-1">{selectedVendor.reviewCount} reviews</div>
                  </Card>
                </div>
              </div>

              {/* Subscription Info */}
              <div>
                <h3 className="font-medium mb-4">Subscription</h3>
                <Card className="p-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <div className="text-sm text-gray-500">Plan</div>
                      <div>{selectedVendor.subscription.plan}</div>
                    </div>
                    <div>
                      <div className="text-sm text-gray-500">Status</div>
                      <div>{selectedVendor.subscription.status}</div>
                    </div>
                    <div>
                      <div className="text-sm text-gray-500">Next Billing</div>
                      <div>{selectedVendor.subscription.nextBilling}</div>
                    </div>
                  </div>
                </Card>
              </div>

              {/* Admin Actions */}
              <div>
                <h3 className="font-medium mb-4">Admin Actions (Logged in Audit Trail)</h3>
                <div className="flex gap-3">
                  {selectedVendor.status !== 'suspended' && (
                    <Button variant="destructive" className="flex items-center gap-2">
                      <Ban className="w-4 h-4" />
                      Suspend Vendor
                    </Button>
                  )}
                  {selectedVendor.status === 'suspended' && (
                    <Button variant="default" className="flex items-center gap-2 bg-green-600 hover:bg-green-700">
                      <CheckCircle className="w-4 h-4" />
                      Reactivate Vendor
                    </Button>
                  )}
                  <Button variant="outline" className="flex items-center gap-2">
                    <FileText className="w-4 h-4" />
                    Add Internal Note
                  </Button>
                  <Button variant="outline" className="flex items-center gap-2">
                    <ExternalLink className="w-4 h-4" />
                    View Audit Log
                  </Button>
                </div>
              </div>
            </div>
          </Card>
        </div>
      )}
    </div>
  );
}
