import { useState } from 'react';
import { 
  FileText, 
  Download, 
  Send, 
  Eye, 
  Plus,
  Search,
  Filter,
  CheckCircle,
  Clock,
  XCircle,
  Euro,
  Calendar,
  User,
  Building
} from 'lucide-react';
import { Input } from '../ui/input';

export function InvoiceManagement() {
  const [activeTab, setActiveTab] = useState<'vendor' | 'customer'>('vendor');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');

  const vendorInvoices = [
    {
      id: 'INV-2024-001234',
      vendorId: 'v_001',
      vendorName: 'Bella Italia',
      type: 'Subscription',
      plan: 'Premium',
      amount: 299.00,
      vatAmount: 59.80,
      totalAmount: 358.80,
      status: 'paid',
      issueDate: '2024-06-01',
      dueDate: '2024-06-15',
      paidDate: '2024-06-03',
      period: 'June 2024'
    },
    {
      id: 'INV-2024-001235',
      vendorId: 'v_003',
      vendorName: 'Cafe Noir',
      type: 'Subscription',
      plan: 'Basic',
      amount: 99.00,
      vatAmount: 19.80,
      totalAmount: 118.80,
      status: 'overdue',
      issueDate: '2024-06-01',
      dueDate: '2024-06-15',
      paidDate: null,
      period: 'June 2024'
    },
    {
      id: 'INV-2024-001236',
      vendorId: 'v_004',
      vendorName: 'Burger Palace',
      type: 'One-time Service',
      plan: '-',
      amount: 450.00,
      vatAmount: 90.00,
      totalAmount: 540.00,
      status: 'sent',
      issueDate: '2024-06-08',
      dueDate: '2024-06-22',
      paidDate: null,
      period: '-'
    },
  ];

  // Customer order receipts (metadata only - read-only)
  const customerInvoices = [
    {
      id: 'ORD-2024-104729',
      orderId: 'ORD-104729',
      customerId: 'C-1842',
      customerName: 'John Smith',
      vendorName: 'Bella Italia',
      orderDate: '2024-12-25T18:30:00',
      itemCount: 3,
      subtotal: 45.80,
      tax: 9.16,
      tip: 5.00,
      total: 59.96,
      paymentMethod: 'Card',
      status: 'completed'
    },
    {
      id: 'ORD-2024-104728',
      orderId: 'ORD-104728',
      customerId: 'C-2941',
      customerName: 'Sarah Martinez',
      vendorName: 'Sakura Sushi',
      orderDate: '2024-12-25T17:45:00',
      itemCount: 5,
      subtotal: 72.50,
      tax: 14.50,
      tip: 10.00,
      total: 97.00,
      paymentMethod: 'Card',
      status: 'completed'
    },
    {
      id: 'ORD-2024-104727',
      orderId: 'ORD-104727',
      customerId: 'C-5029',
      customerName: 'Mike Peterson',
      vendorName: 'Burger Palace',
      orderDate: '2024-12-25T16:20:00',
      itemCount: 2,
      subtotal: 28.90,
      tax: 5.78,
      tip: 0,
      total: 34.68,
      paymentMethod: 'Cash',
      status: 'completed'
    },
    {
      id: 'ORD-2024-104726',
      orderId: 'ORD-104726',
      customerId: 'C-1842',
      customerName: 'John Smith',
      vendorName: 'Cafe Noir',
      orderDate: '2024-12-25T14:10:00',
      itemCount: 1,
      subtotal: 12.50,
      tax: 2.50,
      tip: 2.00,
      total: 17.00,
      paymentMethod: 'Card',
      status: 'completed'
    },
  ];

  const getStatusBadge = (status: string) => {
    const styles = {
      paid: 'bg-green-100 text-green-700',
      sent: 'bg-blue-100 text-blue-700',
      draft: 'bg-gray-100 text-gray-700',
      overdue: 'bg-red-100 text-red-700',
      completed: 'bg-green-100 text-green-700',
      refunded: 'bg-orange-100 text-orange-700',
      disputed: 'bg-red-100 text-red-700',
    };
    const statusClass = styles[status as keyof typeof styles] || styles.draft;
    return (
      <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${statusClass}`}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </span>
    );
  };

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl mb-1">Billing & Invoices</h1>
        <p className="text-sm text-gray-500">Manage platform invoices and billing</p>
      </div>

      {/* Important Notice */}
      <div className="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div className="flex items-start gap-3">
          <FileText className="w-5 h-5 text-blue-600 mt-0.5" />
          <div>
            <h3 className="font-medium text-blue-900 mb-1">Invoice Scope Notice</h3>
            <p className="text-sm text-blue-700">
              <strong>Tavlo invoices cover platform services only, not restaurant sales accounting.</strong>
              {' '}Vendor invoices are for Tavlo subscription fees (€49/month or €490/year). 
              Customer invoices are order receipts (metadata view only, no item-level manipulation).
            </p>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-4 border-b border-gray-200">
        <button
          onClick={() => setActiveTab('vendor')}
          className={`pb-3 px-2 border-b-2 transition-colors ${
            activeTab === 'vendor'
              ? 'border-purple-600 text-purple-600'
              : 'border-transparent text-gray-600 hover:text-gray-900'
          }`}
        >
          <div className="flex items-center gap-2">
            <Building className="w-4 h-4" />
            <span className="text-sm font-medium">Vendor Invoices</span>
          </div>
        </button>
        <button
          onClick={() => setActiveTab('customer')}
          className={`pb-3 px-2 border-b-2 transition-colors ${
            activeTab === 'customer'
              ? 'border-purple-600 text-purple-600'
              : 'border-transparent text-gray-600 hover:text-gray-900'
          }`}
        >
          <div className="flex items-center gap-2">
            <User className="w-4 h-4" />
            <span className="text-sm font-medium">Customer Invoices</span>
          </div>
        </button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Total Outstanding</span>
            <Euro className="w-4 h-4 text-red-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">€2,847</div>
          <div className="text-xs text-red-600">3 overdue invoices</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">This Month</span>
            <Calendar className="w-4 h-4 text-blue-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">€45,890</div>
          <div className="text-xs text-green-600">+12% vs last month</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Paid This Week</span>
            <CheckCircle className="w-4 h-4 text-green-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">€12,450</div>
          <div className="text-xs text-gray-500">18 invoices</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Avg Payment Time</span>
            <Clock className="w-4 h-4 text-orange-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">5.2 days</div>
          <div className="text-xs text-gray-500">Target: 7 days</div>
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
                placeholder="Search by invoice number, vendor name..."
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
              <option value="all">All Status</option>
              <option value="draft">Draft</option>
              <option value="sent">Sent</option>
              <option value="paid">Paid</option>
              <option value="overdue">Overdue</option>
            </select>
            <select className="px-4 py-2 border border-gray-200 rounded-lg text-sm">
              <option>All Types</option>
              <option>Subscription</option>
              <option>One-time Service</option>
            </select>
            <select className="px-4 py-2 border border-gray-200 rounded-lg text-sm">
              <option>This Month</option>
              <option>Last Month</option>
              <option>Last 3 Months</option>
              <option>This Year</option>
            </select>
            <button className="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 text-sm">
              <Download className="w-4 h-4" />
              Export
            </button>
          </div>
        </div>
      </div>

      {/* Invoice Table */}
      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        {activeTab === 'customer' && (
          <div className="p-4 bg-amber-50 border-b border-amber-200">
            <div className="flex items-start gap-3">
              <Eye className="w-5 h-5 text-amber-600 mt-0.5" />
              <div>
                <h3 className="text-sm font-medium text-amber-900 mb-1">Read-Only Order Receipts</h3>
                <p className="text-xs text-amber-700">
                  Customer invoices are order receipts (metadata view only). Admin cannot edit items, change prices, or manipulate order details.
                  These are records of customer orders processed through the platform.
                </p>
              </div>
            </div>
          </div>
        )}
        
        <div className="overflow-x-auto">
          {activeTab === 'vendor' ? (
            <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Invoice
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Vendor
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Type
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Period
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Amount
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  VAT (20%)
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Total
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Due Date
                </th>
                <th className="px-6 py-3 text-right text-xs text-gray-600 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {vendorInvoices.map((invoice) => (
                <tr key={invoice.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4">
                    <div>
                      <div className="font-medium text-sm text-gray-900">{invoice.id}</div>
                      <div className="text-xs text-gray-500">
                        Issued: {new Date(invoice.issueDate).toLocaleDateString()}
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <div>
                      <div className="text-sm font-medium">{invoice.vendorName}</div>
                      <div className="text-xs text-gray-500">{invoice.vendorId}</div>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm">{invoice.type}</div>
                    {invoice.plan !== '-' && (
                      <div className="text-xs text-gray-500">{invoice.plan}</div>
                    )}
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm">{invoice.period}</div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm font-medium">€{invoice.amount.toFixed(2)}</div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm text-gray-600">€{invoice.vatAmount.toFixed(2)}</div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm font-semibold">€{invoice.totalAmount.toFixed(2)}</div>
                  </td>
                  <td className="px-6 py-4">
                    {getStatusBadge(invoice.status)}
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm">
                      {new Date(invoice.dueDate).toLocaleDateString()}
                    </div>
                    {invoice.status === 'overdue' && (
                      <div className="text-xs text-red-600 mt-0.5">
                        {Math.ceil((new Date().getTime() - new Date(invoice.dueDate).getTime()) / (1000 * 60 * 60 * 24))} days overdue
                      </div>
                    )}
                    {invoice.paidDate && (
                      <div className="text-xs text-green-600 mt-0.5">
                        Paid: {new Date(invoice.paidDate).toLocaleDateString()}
                      </div>
                    )}
                  </td>
                  <td className="px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button className="p-1.5 hover:bg-gray-100 rounded-lg" title="View">
                        <Eye className="w-4 h-4 text-gray-600" />
                      </button>
                      <button className="p-1.5 hover:bg-gray-100 rounded-lg" title="Download PDF">
                        <Download className="w-4 h-4 text-gray-600" />
                      </button>
                      {invoice.status !== 'paid' && (
                        <button className="p-1.5 hover:bg-blue-100 rounded-lg" title="Send">
                          <Send className="w-4 h-4 text-blue-600" />
                        </button>
                      )}
                      {invoice.status === 'sent' || invoice.status === 'overdue' ? (
                        <button className="px-3 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700">
                          Mark Paid
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          ) : (
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Order Receipt
                    <div className="text-[10px] text-gray-400 normal-case mt-0.5">(Read-Only)</div>
                  </th>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Customer
                  </th>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Restaurant
                  </th>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Order Date
                  </th>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Items
                  </th>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Subtotal
                  </th>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Tax
                  </th>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Tip
                  </th>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Total
                  </th>
                  <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                    Payment
                  </th>
                  <th className="px-6 py-3 text-right text-xs text-gray-600 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {customerInvoices.map((order) => (
                  <tr key={order.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4">
                      <div>
                        <div className="font-medium text-sm text-gray-900">{order.orderId}</div>
                        <div className="text-xs text-gray-500">{order.id}</div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div>
                        <div className="text-sm font-medium">{order.customerName}</div>
                        <div className="text-xs text-gray-500">{order.customerId}</div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm">{order.vendorName}</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm">
                        {new Date(order.orderDate).toLocaleDateString()}
                      </div>
                      <div className="text-xs text-gray-500">
                        {new Date(order.orderDate).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm">{order.itemCount} items</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm">€{order.subtotal.toFixed(2)}</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm text-gray-600">€{order.tax.toFixed(2)}</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm text-gray-600">
                        {order.tip > 0 ? `€${order.tip.toFixed(2)}` : '-'}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="text-sm font-semibold">€{order.total.toFixed(2)}</div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-2">
                        <span className={`text-xs px-2 py-1 rounded-full ${
                          order.paymentMethod === 'Card' 
                            ? 'bg-blue-100 text-blue-700' 
                            : 'bg-gray-100 text-gray-700'
                        }`}>
                          {order.paymentMethod}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="flex items-center justify-end gap-2">
                        <button className="p-1.5 hover:bg-gray-100 rounded-lg" title="View Receipt (Read-Only)">
                          <Eye className="w-4 h-4 text-gray-600" />
                        </button>
                        <button className="p-1.5 hover:bg-gray-100 rounded-lg" title="Download PDF">
                          <Download className="w-4 h-4 text-gray-600" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {/* Quick Actions */}
      <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <button className="p-4 bg-purple-600 text-white rounded-xl hover:bg-purple-700 text-left">
          <div className="text-sm mb-1">Send Overdue Reminders</div>
          <div className="text-2xl">3 invoices</div>
        </button>
        <button className="p-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-left">
          <div className="text-sm mb-1">Generate Monthly Invoices</div>
          <div className="text-lg">All vendors</div>
        </button>
        <button className="p-4 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 text-left">
          <div className="text-sm mb-1">Export VAT Report</div>
          <div className="text-lg">This quarter</div>
        </button>
      </div>
    </div>
  );
}