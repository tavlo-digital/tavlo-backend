import { useState } from 'react';
import { 
  FileText, 
  Download, 
  Eye, 
  Search,
  Filter,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Euro,
  Calendar,
  TrendingUp,
  ExternalLink,
  RefreshCw,
  ShieldCheck
} from 'lucide-react';
import { toast } from 'sonner@2.0.3';

type InvoiceTab = 'vendor' | 'customer';
type InvoiceStatus = 'all' | 'paid' | 'failed' | 'retrying';
type InvoiceType = 'all' | 'subscription' | 'add-on';

export function FinanceBillingOverview() {
  const [activeTab, setActiveTab] = useState<InvoiceTab>('vendor');
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<InvoiceStatus>('all');
  const [typeFilter, setTypeFilter] = useState<InvoiceType>('all');
  const [periodFilter, setPeriodFilter] = useState('current-month');
  const [activeKPIFilter, setActiveKPIFilter] = useState<string | null>(null);

  // Finance-grade KPI data
  const kpiData = {
    billedThisMonth: 48750.00,
    collectedThisMonth: 45320.00,
    failedCharges30d: 3,
    vatPayable: 9740.00,
    avgPaymentSuccessRate: 93.2
  };

  // Mock vendor invoices data
  const vendorInvoices = [
    {
      id: 'INV-2025-001842',
      vendorId: 'V-1024',
      vendorName: 'Bella Italia',
      invoiceType: 'Subscription',
      period: 'January 2025',
      netAmount: 299.00,
      vat: 59.80,
      grossTotal: 358.80,
      paymentStatus: 'paid' as const,
      pspReference: 'pi_3Q4Rf2abcdef123',
      issuedDate: '2025-01-01',
      paidDate: '2025-01-02',
      country: 'AT'
    },
    {
      id: 'INV-2025-001843',
      vendorId: 'V-2048',
      vendorName: 'Sushi Tokyo',
      invoiceType: 'Subscription',
      period: 'January 2025',
      netAmount: 199.00,
      vat: 39.80,
      grossTotal: 238.80,
      paymentStatus: 'paid' as const,
      pspReference: 'pi_3Q4Rg3cdefgh456',
      issuedDate: '2025-01-01',
      paidDate: '2025-01-01',
      country: 'AT'
    },
    {
      id: 'INV-2025-001844',
      vendorId: 'V-3072',
      vendorName: 'Pizza Express',
      invoiceType: 'Subscription',
      period: 'January 2025',
      netAmount: 99.00,
      vat: 19.80,
      grossTotal: 118.80,
      paymentStatus: 'failed' as const,
      pspReference: 'pi_3Q4Rh4efghi789',
      issuedDate: '2025-01-01',
      paidDate: null,
      country: 'AT'
    },
    {
      id: 'INV-2025-001845',
      vendorId: 'V-4096',
      vendorName: 'Burger House',
      invoiceType: 'Add-on',
      period: 'January 2025',
      netAmount: 49.00,
      vat: 9.80,
      grossTotal: 58.80,
      paymentStatus: 'paid' as const,
      pspReference: 'pi_3Q4Ri5fghij890',
      issuedDate: '2025-01-03',
      paidDate: '2025-01-03',
      country: 'DE'
    },
    {
      id: 'INV-2025-001846',
      vendorId: 'V-5120',
      vendorName: 'Thai Garden',
      invoiceType: 'Subscription',
      period: 'January 2025',
      netAmount: 299.00,
      vat: 59.80,
      grossTotal: 358.80,
      paymentStatus: 'retrying' as const,
      pspReference: 'pi_3Q4Rj6ghijk901',
      issuedDate: '2025-01-01',
      paidDate: null,
      country: 'AT'
    }
  ];

  // Mock customer invoices (order receipts metadata)
  const customerInvoices = [
    {
      id: 'ORD-8472',
      customerId: 'C-1024',
      vendorName: 'Bella Italia',
      orderDate: '2025-01-06 13:15',
      itemCount: 3,
      subtotal: 35.90,
      vat: 5.98,
      total: 35.90,
      paymentMethod: 'Visa •••• 4242',
      status: 'completed' as const
    },
    {
      id: 'ORD-8401',
      customerId: 'C-2048',
      vendorName: 'Sushi Tokyo',
      orderDate: '2025-01-04 19:30',
      itemCount: 5,
      subtotal: 52.90,
      vat: 8.82,
      total: 52.90,
      paymentMethod: 'Mastercard •••• 5555',
      status: 'completed' as const
    },
    {
      id: 'ORD-8324',
      customerId: 'C-3072',
      vendorName: 'Pizza Express',
      orderDate: '2025-01-02 18:45',
      itemCount: 2,
      subtotal: 28.00,
      vat: 4.67,
      total: 28.00,
      paymentMethod: 'Visa •••• 1234',
      status: 'refunded' as const
    }
  ];

  const handleKPIClick = (kpiType: string) => {
    setActiveKPIFilter(kpiType);
    
    // Apply filters based on KPI clicked
    switch (kpiType) {
      case 'failed':
        setStatusFilter('failed');
        setActiveTab('vendor');
        break;
      case 'billed':
      case 'collected':
        setStatusFilter('all');
        setActiveTab('vendor');
        break;
      default:
        break;
    }

    toast.info('Filter applied', {
      description: `Showing ${kpiType} invoices`
    });
  };

  const handleViewInvoice = (invoiceId: string) => {
    console.log('AUDIT LOG: Invoice viewed', {
      invoiceId,
      timestamp: new Date().toISOString(),
      admin: 'Current Admin User'
    });
    
    toast.success('Invoice PDF opened', {
      description: `${invoiceId}.pdf`
    });
  };

  const handleDownloadInvoice = (invoiceId: string) => {
    console.log('AUDIT LOG: Invoice downloaded', {
      invoiceId,
      timestamp: new Date().toISOString(),
      admin: 'Current Admin User'
    });
    
    toast.success('Invoice downloaded', {
      description: `${invoiceId}.pdf`
    });
  };

  const handleViewVendor = (vendorId: string) => {
    toast.info('Navigating to Vendor Detail → Billing tab', {
      description: `Vendor ${vendorId}`
    });
    // In production: navigate to vendor detail page, billing tab
  };

  const handleExportVAT = () => {
    console.log('AUDIT LOG: VAT report export', {
      period: periodFilter,
      timestamp: new Date().toISOString(),
      admin: 'Current Admin User'
    });
    
    toast.success('VAT report exported', {
      description: 'VAT_Report_January_2025.xlsx'
    });
  };

  const handleExportInvoices = () => {
    console.log('AUDIT LOG: Invoices export', {
      filters: { status: statusFilter, type: typeFilter, period: periodFilter },
      timestamp: new Date().toISOString(),
      admin: 'Current Admin User'
    });
    
    toast.success('Invoices exported', {
      description: 'Invoices_Export_2025-01-06.xlsx'
    });
  };

  const handleGenerateMonthlyInvoices = () => {
    toast.info('Monthly invoice generation', {
      description: 'Auto-generation runs on 1st of each month'
    });
  };

  const filteredVendorInvoices = vendorInvoices.filter(invoice => {
    if (statusFilter !== 'all' && invoice.paymentStatus !== statusFilter) return false;
    if (typeFilter !== 'all' && invoice.invoiceType.toLowerCase() !== typeFilter) return false;
    if (searchQuery && !invoice.id.toLowerCase().includes(searchQuery.toLowerCase()) && 
        !invoice.vendorName.toLowerCase().includes(searchQuery.toLowerCase())) return false;
    return true;
  });

  const filteredCustomerInvoices = customerInvoices.filter(invoice => {
    if (searchQuery && !invoice.id.toLowerCase().includes(searchQuery.toLowerCase())) return false;
    return true;
  });

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">Finance & Billing Overview</h1>
        <p className="text-sm text-gray-600 mt-1">
          Platform-level billing, payments, VAT, and invoice records (read-only)
        </p>
      </div>

      {/* Invoice Scope Notice (Always Visible, Non-Dismissable) */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg px-6 py-4">
        <div className="flex items-start gap-3">
          <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <FileText className="w-5 h-5 text-blue-600" />
          </div>
          <div>
            <h3 className="font-semibold text-blue-900 mb-1">
              Invoice Scope & Platform Billing
            </h3>
            <div className="text-sm text-blue-800 space-y-1">
              <p>• <strong>Tavlo invoices</strong> cover platform services only (subscriptions, add-ons).</p>
              <p>• <strong>Vendor invoices</strong> are auto-generated and auto-charged.</p>
              <p>• <strong>Customer invoices</strong> are order receipts (read-only metadata).</p>
              <p>• <strong>Tavlo does not manage restaurant sales accounting.</strong></p>
            </div>
          </div>
        </div>
      </div>

      {/* Finance-Grade KPI Cards (Platform Only) */}
      <div>
        <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">
          Platform Financial Health
        </h2>
        <div className="grid grid-cols-5 gap-4">
          <button
            onClick={() => handleKPIClick('billed')}
            className={`bg-white border rounded-lg p-4 text-left hover:shadow-sm transition-all ${
              activeKPIFilter === 'billed' ? 'ring-2 ring-purple-600 border-purple-200' : 'border-gray-200'
            }`}
          >
            <div className="flex items-center justify-between mb-2">
              <Euro className="w-5 h-5 text-blue-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              €{kpiData.billedThisMonth.toLocaleString('de-AT', { minimumFractionDigits: 2 })}
            </div>
            <div className="text-sm text-gray-600">Billed This Month</div>
          </button>

          <button
            onClick={() => handleKPIClick('collected')}
            className={`bg-white border rounded-lg p-4 text-left hover:shadow-sm transition-all ${
              activeKPIFilter === 'collected' ? 'ring-2 ring-purple-600 border-purple-200' : 'border-gray-200'
            }`}
          >
            <div className="flex items-center justify-between mb-2">
              <CheckCircle className="w-5 h-5 text-green-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              €{kpiData.collectedThisMonth.toLocaleString('de-AT', { minimumFractionDigits: 2 })}
            </div>
            <div className="text-sm text-gray-600">Collected This Month</div>
          </button>

          <button
            onClick={() => handleKPIClick('failed')}
            className={`bg-white border rounded-lg p-4 text-left hover:shadow-sm transition-all ${
              activeKPIFilter === 'failed' ? 'ring-2 ring-purple-600 border-purple-200' : 'border-gray-200'
            }`}
          >
            <div className="flex items-center justify-between mb-2">
              <XCircle className="w-5 h-5 text-red-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {kpiData.failedCharges30d}
            </div>
            <div className="text-sm text-gray-600">Failed Charges (30d)</div>
          </button>

          <button
            onClick={() => handleKPIClick('vat')}
            className="bg-white border border-gray-200 rounded-lg p-4 text-left hover:shadow-sm transition-all"
          >
            <div className="flex items-center justify-between mb-2">
              <ShieldCheck className="w-5 h-5 text-purple-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              €{kpiData.vatPayable.toLocaleString('de-AT', { minimumFractionDigits: 2 })}
            </div>
            <div className="text-sm text-gray-600">VAT Payable (Current)</div>
          </button>

          <button
            onClick={() => handleKPIClick('success-rate')}
            className="bg-white border border-gray-200 rounded-lg p-4 text-left hover:shadow-sm transition-all"
          >
            <div className="flex items-center justify-between mb-2">
              <TrendingUp className="w-5 h-5 text-green-600" />
            </div>
            <div className="text-2xl font-semibold text-gray-900 mb-1">
              {kpiData.avgPaymentSuccessRate}%
            </div>
            <div className="text-sm text-gray-600">Avg Payment Success</div>
          </button>
        </div>
      </div>

      {/* Active Filter Banner */}
      {activeKPIFilter && (
        <div className="bg-purple-50 border border-purple-200 rounded-lg px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="w-2 h-2 bg-purple-600 rounded-full" />
            <span className="text-sm font-medium text-purple-900">
              Filter active: {activeKPIFilter === 'billed' ? 'Billed This Month' : activeKPIFilter === 'collected' ? 'Collected This Month' : 'Failed Charges'}
            </span>
          </div>
          <button
            onClick={() => {
              setActiveKPIFilter(null);
              setStatusFilter('all');
            }}
            className="text-sm text-purple-700 hover:text-purple-900 font-medium"
          >
            Clear filter
          </button>
        </div>
      )}

      {/* Tabs */}
      <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
        {/* Tab Navigation */}
        <div className="border-b border-gray-200">
          <div className="flex">
            <button
              onClick={() => setActiveTab('vendor')}
              className={`flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors ${
                activeTab === 'vendor'
                  ? 'border-purple-600 text-purple-700 bg-purple-50'
                  : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50'
              }`}
            >
              <FileText className="w-4 h-4" />
              Vendor Invoices
              <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                activeTab === 'vendor'
                  ? 'bg-purple-100 text-purple-700'
                  : 'bg-gray-100 text-gray-600'
              }`}>
                {vendorInvoices.length}
              </span>
            </button>
            <button
              onClick={() => setActiveTab('customer')}
              className={`flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors ${
                activeTab === 'customer'
                  ? 'border-purple-600 text-purple-700 bg-purple-50'
                  : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50'
              }`}
            >
              <FileText className="w-4 h-4" />
              Customer Invoices (Receipts)
              <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                activeTab === 'customer'
                  ? 'bg-purple-100 text-purple-700'
                  : 'bg-gray-100 text-gray-600'
              }`}>
                {customerInvoices.length}
              </span>
            </button>
          </div>
        </div>

        {/* Filters & Search Bar */}
        <div className="px-6 py-4 bg-gray-50 border-b border-gray-200">
          <div className="flex items-center gap-4">
            {/* Search */}
            <div className="flex-1 max-w-md">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  type="text"
                  placeholder={activeTab === 'vendor' ? 'Search by Invoice ID or Vendor...' : 'Search by Order ID...'}
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full pl-11 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-transparent text-sm"
                />
              </div>
            </div>

            {activeTab === 'vendor' && (
              <>
                {/* Status Filter */}
                <div className="relative">
                  <Filter className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value as InvoiceStatus)}
                    className="pl-10 pr-8 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-600 appearance-none bg-white"
                  >
                    <option value="all">All Statuses</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                    <option value="retrying">Retrying</option>
                  </select>
                </div>

                {/* Type Filter */}
                <div className="relative">
                  <select
                    value={typeFilter}
                    onChange={(e) => setTypeFilter(e.target.value as InvoiceType)}
                    className="px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-600 appearance-none bg-white"
                  >
                    <option value="all">All Types</option>
                    <option value="subscription">Subscription</option>
                    <option value="add-on">Add-on</option>
                  </select>
                </div>

                {/* Period Filter */}
                <div className="relative">
                  <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <select
                    value={periodFilter}
                    onChange={(e) => setPeriodFilter(e.target.value)}
                    className="pl-10 pr-8 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-600 appearance-none bg-white"
                  >
                    <option value="current-month">Current Month</option>
                    <option value="last-month">Last Month</option>
                    <option value="current-quarter">Current Quarter</option>
                    <option value="last-quarter">Last Quarter</option>
                    <option value="current-year">Current Year</option>
                  </select>
                </div>
              </>
            )}

            {/* Export Button */}
            <button
              onClick={handleExportInvoices}
              className="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2"
            >
              <Download className="w-4 h-4" />
              Export
            </button>
          </div>
        </div>

        {/* Tab Content */}
        <div className="overflow-x-auto">
          {activeTab === 'vendor' && (
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    Invoice ID
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    Vendor
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    Type
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    Period
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    Net Amount
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    VAT
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    Gross Total
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    Status
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    PSP Reference
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    Issued
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {filteredVendorInvoices.map((invoice) => (
                  <tr key={invoice.id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-6 py-4">
                      <span className="font-mono text-sm font-medium text-gray-900">{invoice.id}</span>
                    </td>
                    <td className="px-6 py-4">
                      <button
                        onClick={() => handleViewVendor(invoice.vendorId)}
                        className="text-sm text-purple-600 hover:text-purple-700 font-medium flex items-center gap-1"
                        title="Navigate to Vendor Detail → Billing"
                      >
                        {invoice.vendorName}
                        <ExternalLink className="w-3 h-3" />
                      </button>
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-sm text-gray-900">{invoice.invoiceType}</span>
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-sm text-gray-600">{invoice.period}</span>
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-sm font-medium text-gray-900">€{invoice.netAmount.toFixed(2)}</span>
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-sm text-gray-600">€{invoice.vat.toFixed(2)}</span>
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-sm font-semibold text-gray-900">€{invoice.grossTotal.toFixed(2)}</span>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                        invoice.paymentStatus === 'paid'
                          ? 'bg-green-100 text-green-700'
                          : invoice.paymentStatus === 'failed'
                          ? 'bg-red-100 text-red-700'
                          : 'bg-yellow-100 text-yellow-700'
                      }`}>
                        {invoice.paymentStatus === 'paid' ? 'Paid' : invoice.paymentStatus === 'failed' ? 'Failed' : 'Retrying'}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-xs font-mono text-gray-500">{invoice.pspReference}</span>
                    </td>
                    <td className="px-6 py-4">
                      <span className="text-sm text-gray-600">{invoice.issuedDate}</span>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-2">
                        <button
                          onClick={() => handleViewInvoice(invoice.id)}
                          className="p-1.5 hover:bg-gray-100 rounded transition-colors"
                          title="View invoice PDF"
                        >
                          <Eye className="w-4 h-4 text-gray-600" />
                        </button>
                        <button
                          onClick={() => handleDownloadInvoice(invoice.id)}
                          className="p-1.5 hover:bg-gray-100 rounded transition-colors"
                          title="Download PDF"
                        >
                          <Download className="w-4 h-4 text-gray-600" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {activeTab === 'customer' && (
            <div className="p-6">
              <div className="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-4 flex items-start gap-2">
                <AlertTriangle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-amber-900">Read-Only Metadata</p>
                  <p className="text-sm text-amber-800 mt-1">
                    Customer invoices are order receipts. This view shows metadata only. No financial authority. Full receipts visible in Customer Support Overview.
                  </p>
                </div>
              </div>

              <table className="w-full">
                <thead className="bg-gray-50 border-b border-gray-200">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                      Order ID
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                      Vendor
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                      Date
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                      Items
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                      Subtotal
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                      VAT
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                      Total
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                      Payment
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wide">
                      Status
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {filteredCustomerInvoices.map((invoice) => (
                    <tr key={invoice.id} className="hover:bg-gray-50 transition-colors">
                      <td className="px-6 py-4">
                        <span className="font-mono text-sm font-medium text-gray-900">{invoice.id}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className="text-sm text-gray-900">{invoice.vendorName}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className="text-sm text-gray-600">{invoice.orderDate}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className="text-sm text-gray-900">{invoice.itemCount}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className="text-sm text-gray-900">€{invoice.subtotal.toFixed(2)}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className="text-sm text-gray-600">€{invoice.vat.toFixed(2)}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className="text-sm font-semibold text-gray-900">€{invoice.total.toFixed(2)}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className="text-sm text-gray-600">{invoice.paymentMethod}</span>
                      </td>
                      <td className="px-6 py-4">
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                          invoice.status === 'completed'
                            ? 'bg-green-100 text-green-700'
                            : 'bg-red-100 text-red-700'
                        }`}>
                          {invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Finance Actions Section (Bottom CTA) */}
      <div className="grid grid-cols-3 gap-4">
        <div className="bg-white border border-gray-200 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <RefreshCw className="w-5 h-5 text-purple-600" />
            </div>
            <div className="flex-1">
              <h3 className="font-medium text-gray-900 mb-1">Generate Monthly Invoices</h3>
              <p className="text-sm text-gray-600 mb-3">Auto-generation confirmation only</p>
              <button
                onClick={handleGenerateMonthlyInvoices}
                className="px-4 py-2 text-sm font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors w-full"
              >
                View Schedule
              </button>
            </div>
          </div>
        </div>

        <div className="bg-white border border-gray-200 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <FileText className="w-5 h-5 text-green-600" />
            </div>
            <div className="flex-1">
              <h3 className="font-medium text-gray-900 mb-1">Export VAT Report</h3>
              <p className="text-sm text-gray-600 mb-3">Monthly/quarterly VAT summary</p>
              <button
                onClick={handleExportVAT}
                className="px-4 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors w-full"
              >
                Export VAT
              </button>
            </div>
          </div>
        </div>

        <div className="bg-white border border-gray-200 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <ShieldCheck className="w-5 h-5 text-blue-600" />
            </div>
            <div className="flex-1">
              <h3 className="font-medium text-gray-900 mb-1">PSP Reconciliation Status</h3>
              <p className="text-sm text-gray-600 mb-3">Read-only system health</p>
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse" />
                <span className="text-sm font-medium text-gray-900">All systems operational</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
