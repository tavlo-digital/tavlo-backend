import { useState } from 'react';
import { Users, Search, Eye, EyeOff, Shield, Download, Trash2, Lock, AlertCircle, Info } from 'lucide-react';
import { Card } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Badge } from '../ui/badge';

interface Customer {
  id: string;
  accountType: 'guest' | 'registered';
  orderCount: number;
  lastActivity: string;
  flagged: boolean;
  // Restricted fields (hidden by default)
  email?: string;
  phone?: string;
  totalSpend?: number;
  tipsGiven?: number;
  loyaltyPoints?: number;
}

interface ImprovedCustomerManagementProps {
  page?: string;
}

export function ImprovedCustomerManagement({ page }: ImprovedCustomerManagementProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [showRestricted, setShowRestricted] = useState(false);
  const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null);
  const [adminRole] = useState<'super' | 'support' | 'content'>('support'); // Mock role

  // Mock customer data
  const customers: Customer[] = [
    {
      id: 'C-1001',
      accountType: 'registered',
      orderCount: 47,
      lastActivity: '2 hours ago',
      flagged: false,
      email: 'john.doe@email.com',
      phone: '+43 664 123 4567',
      totalSpend: 2847,
      tipsGiven: 142,
      loyaltyPoints: 850
    },
    {
      id: 'C-1002',
      accountType: 'guest',
      orderCount: 3,
      lastActivity: '1 day ago',
      flagged: false,
      email: 'guest_c1002@tavlo.temp',
      phone: undefined,
      totalSpend: 87,
      tipsGiven: 5,
      loyaltyPoints: 0
    },
    {
      id: 'C-1003',
      accountType: 'registered',
      orderCount: 124,
      lastActivity: '15 min ago',
      flagged: false,
      email: 'sarah.m@email.com',
      phone: '+43 676 987 6543',
      totalSpend: 5892,
      tipsGiven: 324,
      loyaltyPoints: 1840
    },
    {
      id: 'C-1004',
      accountType: 'registered',
      orderCount: 8,
      lastActivity: '3 days ago',
      flagged: true,
      email: 'mark.p@email.com',
      phone: '+43 660 555 1234',
      totalSpend: 342,
      tipsGiven: 0,
      loyaltyPoints: 120
    },
  ];

  const handleExportGDPRData = (customerId: string) => {
    alert(`GDPR data export initiated for customer ${customerId}. Download will be ready in 24 hours.`);
  };

  const handleAnonymizeCustomer = (customerId: string) => {
    if (confirm('This will permanently anonymize all personal data. This action cannot be undone. Continue?')) {
      alert(`Customer ${customerId} data anonymization initiated. This will be logged in audit trail.`);
    }
  };

  const handleDeleteAccount = (customerId: string) => {
    if (confirm('This will delete the account and anonymize all data per GDPR. This action cannot be undone. Continue?')) {
      alert(`Customer ${customerId} account deletion initiated. Audit log entry created.`);
    }
  };

  return (
    <div className="p-6 max-w-[1800px] mx-auto">
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-2">
          <div>
            <h1 className="text-2xl mb-1">Customer Management</h1>
            <p className="text-sm text-gray-500">
              Minimized, GDPR-safe customer data • Support-only visibility
            </p>
          </div>
          <Button variant="outline" className="flex items-center gap-2">
            <Download className="w-4 h-4" />
            Export Customer Report
          </Button>
        </div>
      </div>

      {/* GDPR Notice */}
      <Card className="mb-6 p-4 bg-blue-50 border-blue-200">
        <div className="flex items-start gap-3">
          <Shield className="w-5 h-5 text-blue-600 mt-0.5" />
          <div className="flex-1">
            <h3 className="font-medium text-blue-900 mb-1">Privacy & GDPR Compliance</h3>
            <p className="text-sm text-blue-700 mb-2">
              Customer personal data (email, phone) is hidden by default and visible only for support purposes.
              All data access is logged in the Audit Trail. Tavlo complies with GDPR data protection requirements.
            </p>
            <div className="flex items-center gap-4 mt-3">
              <label className="flex items-center gap-2 text-sm text-blue-900 cursor-pointer">
                <input 
                  type="checkbox" 
                  checked={showRestricted}
                  onChange={(e) => setShowRestricted(e.target.checked)}
                  className="rounded"
                />
                <Lock className="w-4 h-4" />
                Show restricted data (for support only)
              </label>
              {showRestricted && (
                <span className="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded">
                  ⚠️ Data access logged
                </span>
              )}
            </div>
          </div>
        </div>
      </Card>

      {/* Stats Overview */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <Card className="p-4">
          <div className="text-2xl mb-1">48,392</div>
          <div className="text-sm text-gray-600">Total Customers</div>
          <div className="text-xs text-gray-500 mt-1">12,384 registered • 36,008 guest</div>
        </Card>

        <Card className="p-4">
          <div className="text-2xl mb-1">89,234</div>
          <div className="text-sm text-gray-600">Total Orders</div>
          <div className="text-xs text-gray-500 mt-1">Platform-wide</div>
        </Card>

        <Card className="p-4">
          <div className="text-2xl mb-1">17</div>
          <div className="text-sm text-gray-600">Flagged Accounts</div>
          <div className="text-xs text-gray-500 mt-1">Requires review</div>
        </Card>

        <Card className="p-4">
          <div className="text-2xl mb-1">24</div>
          <div className="text-sm text-gray-600">GDPR Requests</div>
          <div className="text-xs text-gray-500 mt-1">Last 30 days</div>
        </Card>
      </div>

      {/* Search & Filters */}
      <Card className="mb-6 p-4">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="md:col-span-2">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <Input
                type="text"
                placeholder="Search by customer ID only (email/phone not searchable for privacy)"
                className="pl-10"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
              />
            </div>
          </div>
          <div>
            <select className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
              <option value="all">All Customers</option>
              <option value="registered">Registered Only</option>
              <option value="guest">Guest Only</option>
              <option value="flagged">Flagged Only</option>
            </select>
          </div>
        </div>
      </Card>

      {/* Customers Table */}
      <Card>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Customer ID</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Account Type</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">
                  {showRestricted ? (
                    <>Email <span className="text-red-500">(RESTRICTED)</span></>
                  ) : (
                    <>Email <Lock className="w-3 h-3 inline" /></>
                  )}
                </th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">
                  {showRestricted ? (
                    <>Phone <span className="text-red-500">(RESTRICTED)</span></>
                  ) : (
                    <>Phone <Lock className="w-3 h-3 inline" /></>
                  )}
                </th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Order Count</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Last Activity</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                <th className="text-left px-6 py-3 text-xs text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {customers.map((customer) => (
                <tr key={customer.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4">
                    <div className="font-medium text-gray-900">{customer.id}</div>
                  </td>
                  <td className="px-6 py-4">
                    <Badge variant={customer.accountType === 'registered' ? 'default' : 'outline'}>
                      {customer.accountType}
                    </Badge>
                  </td>
                  <td className="px-6 py-4">
                    {showRestricted ? (
                      <span className="text-sm">{customer.email}</span>
                    ) : (
                      <span className="text-gray-400 text-sm flex items-center gap-1">
                        <EyeOff className="w-3 h-3" />
                        Hidden
                      </span>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    {showRestricted ? (
                      <span className="text-sm">{customer.phone || 'N/A'}</span>
                    ) : (
                      <span className="text-gray-400 text-sm flex items-center gap-1">
                        <EyeOff className="w-3 h-3" />
                        Hidden
                      </span>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <div className="font-medium">{customer.orderCount}</div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm text-gray-600">{customer.lastActivity}</div>
                  </td>
                  <td className="px-6 py-4">
                    {customer.flagged ? (
                      <Badge variant="destructive" className="flex items-center gap-1 w-fit">
                        <AlertCircle className="w-3 h-3" />
                        Flagged
                      </Badge>
                    ) : (
                      <Badge variant="outline" className="text-green-700 border-green-300">
                        Normal
                      </Badge>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <Button 
                      variant="ghost" 
                      size="sm"
                      onClick={() => setSelectedCustomer(customer)}
                    >
                      <Eye className="w-4 h-4" />
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      {/* Customer Detail Modal */}
      {selectedCustomer && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <Card className="max-w-3xl w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b border-gray-200">
              <div className="flex items-center justify-between mb-4">
                <div>
                  <h2 className="text-2xl mb-2">Customer Details</h2>
                  <div className="flex items-center gap-2">
                    <Badge variant={selectedCustomer.accountType === 'registered' ? 'default' : 'outline'}>
                      {selectedCustomer.accountType}
                    </Badge>
                    {selectedCustomer.flagged && (
                      <Badge variant="destructive">Flagged</Badge>
                    )}
                  </div>
                </div>
                <Button variant="ghost" onClick={() => setSelectedCustomer(null)}>
                  ✕
                </Button>
              </div>
              <p className="text-sm text-gray-500">
                Customer ID: {selectedCustomer.id}
              </p>
            </div>

            <div className="p-6 space-y-6">
              {/* Privacy Notice */}
              <Card className="p-4 bg-amber-50 border-amber-200">
                <div className="flex items-start gap-3">
                  <Info className="w-5 h-5 text-amber-600 mt-0.5" />
                  <div>
                    <h3 className="font-medium text-amber-900 mb-1">Visible for Support Purposes Only</h3>
                    <p className="text-sm text-amber-700">
                      This data is accessed for customer support case resolution. Access is logged in Audit Trail.
                      Personal data is not visible to vendors.
                    </p>
                  </div>
                </div>
              </Card>

              {/* Basic Info */}
              <div>
                <h3 className="font-medium mb-4">Basic Information</h3>
                <Card className="p-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <div className="text-sm text-gray-500">Customer ID</div>
                      <div>{selectedCustomer.id}</div>
                    </div>
                    <div>
                      <div className="text-sm text-gray-500">Account Type</div>
                      <div className="capitalize">{selectedCustomer.accountType}</div>
                    </div>
                    {showRestricted && (
                      <>
                        <div>
                          <div className="text-sm text-gray-500 flex items-center gap-1">
                            Email <Lock className="w-3 h-3 text-red-500" />
                          </div>
                          <div>{selectedCustomer.email}</div>
                        </div>
                        <div>
                          <div className="text-sm text-gray-500 flex items-center gap-1">
                            Phone <Lock className="w-3 h-3 text-red-500" />
                          </div>
                          <div>{selectedCustomer.phone || 'Not provided'}</div>
                        </div>
                      </>
                    )}
                  </div>
                </Card>
              </div>

              {/* Activity Summary */}
              {showRestricted && (
                <div>
                  <h3 className="font-medium mb-4">Activity Summary (Support-only)</h3>
                  <div className="grid grid-cols-3 gap-4">
                    <Card className="p-4">
                      <div className="text-2xl mb-1">{selectedCustomer.orderCount}</div>
                      <div className="text-sm text-gray-600">Total Orders</div>
                    </Card>
                    <Card className="p-4">
                      <div className="text-2xl mb-1">€{selectedCustomer.totalSpend}</div>
                      <div className="text-sm text-gray-600">Total Spend</div>
                    </Card>
                    <Card className="p-4">
                      <div className="text-2xl mb-1">{selectedCustomer.loyaltyPoints}</div>
                      <div className="text-sm text-gray-600">Loyalty Points</div>
                    </Card>
                  </div>
                </div>
              )}

              {/* GDPR Actions */}
              <div>
                <h3 className="font-medium mb-4">GDPR Actions (All Logged in Audit Trail)</h3>
                <div className="space-y-3">
                  <Button 
                    variant="outline" 
                    className="w-full justify-start"
                    onClick={() => handleExportGDPRData(selectedCustomer.id)}
                  >
                    <Download className="w-4 h-4 mr-2" />
                    Export Personal Data (GDPR Right to Access)
                  </Button>
                  <Button 
                    variant="outline" 
                    className="w-full justify-start text-orange-600 border-orange-300 hover:bg-orange-50"
                    onClick={() => handleAnonymizeCustomer(selectedCustomer.id)}
                  >
                    <Shield className="w-4 h-4 mr-2" />
                    Anonymize Customer (GDPR Right to Erasure)
                  </Button>
                  <Button 
                    variant="outline" 
                    className="w-full justify-start text-red-600 border-red-300 hover:bg-red-50"
                    onClick={() => handleDeleteAccount(selectedCustomer.id)}
                  >
                    <Trash2 className="w-4 h-4 mr-2" />
                    Delete Account Permanently
                  </Button>
                </div>
                <p className="text-xs text-gray-500 mt-3">
                  All GDPR actions require confirmation and are permanently logged with admin user, timestamp, and reason.
                </p>
              </div>
            </div>
          </Card>
        </div>
      )}
    </div>
  );
}