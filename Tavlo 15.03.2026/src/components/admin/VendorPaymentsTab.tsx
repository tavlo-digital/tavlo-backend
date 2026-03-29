import { useState } from 'react';
import { Download, Filter as FilterIcon, FileText, Calendar } from 'lucide-react';
import { toast } from 'sonner@2.0.3';

interface PaymentFilters {
  dateRange: 'all' | 'month' | 'year' | 'custom';
  invoiceStatus: 'all' | 'paid' | 'unpaid' | 'overdue';
  paymentMethod: 'all' | 'stripe' | 'paypal';
}

interface Invoice {
  id: string;
  period: string;
  amount: number;
  status: 'paid' | 'unpaid' | 'overdue';
  paidDate?: string;
  dueDate?: string;
  paymentMethod?: string;
}

interface VendorPaymentsTabProps {
  vendorId: string;
  vendorName: string;
  hasFailedPayments?: boolean;
  onInvoiceDownloadLogged: (invoiceId: string) => void;
}

export function VendorPaymentsTab({ 
  vendorId, 
  vendorName, 
  hasFailedPayments = false,
  onInvoiceDownloadLogged
}: VendorPaymentsTabProps) {
  const [filters, setFilters] = useState<PaymentFilters>({
    dateRange: 'all',
    invoiceStatus: 'all',
    paymentMethod: 'all'
  });
  const [showFilters, setShowFilters] = useState(false);
  const [selectedPeriod, setSelectedPeriod] = useState<'month' | 'year' | null>(null);

  // Mock invoice data
  const invoices: Invoice[] = [
    {
      id: 'INV-2024-12',
      period: 'December 2024',
      amount: 149.99,
      status: 'unpaid',
      dueDate: 'Dec 15, 2024',
      paymentMethod: 'stripe'
    },
    {
      id: 'INV-2024-11',
      period: 'November 2024',
      amount: 149.99,
      status: 'paid',
      paidDate: 'Nov 5, 2024',
      paymentMethod: 'stripe'
    },
    {
      id: 'INV-2024-10',
      period: 'October 2024',
      amount: 149.99,
      status: 'paid',
      paidDate: 'Oct 3, 2024',
      paymentMethod: 'stripe'
    },
    {
      id: 'INV-2024-09',
      period: 'September 2024',
      amount: 99.99,
      status: 'paid',
      paidDate: 'Sep 4, 2024',
      paymentMethod: 'stripe'
    }
  ];

  // Group invoices by year and month
  const groupedInvoices = invoices.reduce((acc, invoice) => {
    const [_, year, month] = invoice.id.split('-');
    const yearKey = `Year ${year}`;
    
    if (!acc[yearKey]) {
      acc[yearKey] = {
        totalAmount: 0,
        paidCount: 0,
        unpaidCount: 0,
        months: {}
      };
    }
    
    const monthKey = invoice.period;
    if (!acc[yearKey].months[monthKey]) {
      acc[yearKey].months[monthKey] = [];
    }
    
    acc[yearKey].months[monthKey].push(invoice);
    acc[yearKey].totalAmount += invoice.amount;
    
    if (invoice.status === 'paid') {
      acc[yearKey].paidCount++;
    } else {
      acc[yearKey].unpaidCount++;
    }
    
    return acc;
  }, {} as Record<string, { totalAmount: number; paidCount: number; unpaidCount: number; months: Record<string, Invoice[]> }>);

  const handleDownloadInvoice = (invoiceId: string) => {
    // Log download action
    onInvoiceDownloadLogged(invoiceId);
    
    toast.success('Invoice downloaded', {
      description: `Invoice ${invoiceId}`
    });
    
    // In production: Generate and download PDF
    console.log('Download invoice:', invoiceId);
  };

  const handleDownloadPeriod = (period: 'month' | 'year', identifier: string) => {
    const invoicesToDownload = period === 'month' 
      ? invoices.filter(inv => inv.period === identifier)
      : invoices.filter(inv => inv.id.includes(identifier));
    
    invoicesToDownload.forEach(inv => onInvoiceDownloadLogged(inv.id));
    
    toast.success(`Downloaded ${invoicesToDownload.length} invoices`, {
      description: `Period: ${identifier}`
    });
    
    console.log('Download period:', period, identifier, invoicesToDownload);
  };

  const getFilteredInvoices = () => {
    return invoices.filter(invoice => {
      if (filters.invoiceStatus !== 'all' && invoice.status !== filters.invoiceStatus) {
        return false;
      }
      if (filters.paymentMethod !== 'all' && invoice.paymentMethod !== filters.paymentMethod) {
        return false;
      }
      return true;
    });
  };

  const filteredInvoices = getFilteredInvoices();

  return (
    <div className="p-6">
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-semibold text-gray-900">Payment History</h2>
        
        <button
          onClick={() => setShowFilters(!showFilters)}
          className={`flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm font-medium transition-colors ${
            showFilters
              ? 'bg-purple-50 border-purple-600 text-purple-700'
              : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
          }`}
        >
          <FilterIcon className="w-4 h-4" />
          Filters
        </button>
      </div>
      
      {/* Failed Payments Alert */}
      {hasFailedPayments && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
          <h3 className="font-medium text-red-900 mb-2">🔴 Failed Payments</h3>
          <p className="text-sm text-red-800">3 payment failures in the last 24 hours</p>
        </div>
      )}

      {/* Filters */}
      {showFilters && (
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
          <div className="grid grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">
                Date Range
              </label>
              <select
                value={filters.dateRange}
                onChange={(e) => setFilters({ ...filters, dateRange: e.target.value as any })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-600"
              >
                <option value="all">All Time</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
                <option value="custom">Custom Range</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">
                Invoice Status
              </label>
              <select
                value={filters.invoiceStatus}
                onChange={(e) => setFilters({ ...filters, invoiceStatus: e.target.value as any })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-600"
              >
                <option value="all">All Statuses</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
                <option value="overdue">Overdue</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">
                Payment Method
              </label>
              <select
                value={filters.paymentMethod}
                onChange={(e) => setFilters({ ...filters, paymentMethod: e.target.value as any })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-600"
              >
                <option value="all">All Methods</option>
                <option value="stripe">Stripe</option>
                <option value="paypal">PayPal</option>
              </select>
            </div>
          </div>
        </div>
      )}

      {/* Invoice Grouping */}
      <div className="space-y-6">
        {Object.entries(groupedInvoices).map(([year, yearData]) => (
          <div key={year} className="bg-white border border-gray-200 rounded-lg overflow-hidden">
            {/* Year Header */}
            <div className="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <Calendar className="w-4 h-4 text-gray-600" />
                <span className="font-semibold text-gray-900">{year}</span>
                <span className="text-sm text-gray-600">
                  {yearData.paidCount} paid, {yearData.unpaidCount} unpaid
                </span>
                <span className="text-sm font-medium text-gray-900">
                  Total: €{yearData.totalAmount.toFixed(2)}
                </span>
              </div>
              <button
                onClick={() => handleDownloadPeriod('year', year.split(' ')[1])}
                className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-green-700 hover:bg-green-50 rounded-lg transition-colors"
              >
                <Download className="w-4 h-4" />
                Download Year
              </button>
            </div>

            {/* Monthly Invoices */}
            <div className="divide-y divide-gray-100">
              {Object.entries(yearData.months).map(([month, monthInvoices]) => {
                const monthTotal = monthInvoices.reduce((sum, inv) => sum + inv.amount, 0);
                const monthPaid = monthInvoices.filter(inv => inv.status === 'paid').length;
                
                return (
                  <div key={month} className="p-4">
                    <div className="flex items-center justify-between mb-3">
                      <div className="flex items-center gap-2">
                        <span className="font-medium text-gray-900">{month}</span>
                        <span className="text-sm text-gray-600">
                          {monthPaid}/{monthInvoices.length} paid
                        </span>
                        <span className="text-sm font-medium text-gray-900">
                          €{monthTotal.toFixed(2)}
                        </span>
                      </div>
                      <button
                        onClick={() => handleDownloadPeriod('month', month)}
                        className="flex items-center gap-1.5 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100 rounded transition-colors"
                      >
                        <Download className="w-3.5 h-3.5" />
                        Download Month
                      </button>
                    </div>

                    {/* Invoice Table */}
                    <div className="bg-gray-50 rounded-lg overflow-hidden">
                      <table className="w-full">
                        <thead className="bg-gray-100 border-b border-gray-200">
                          <tr>
                            <th className="text-left px-3 py-2 text-xs font-medium text-gray-600 uppercase">Invoice ID</th>
                            <th className="text-left px-3 py-2 text-xs font-medium text-gray-600 uppercase">Amount</th>
                            <th className="text-left px-3 py-2 text-xs font-medium text-gray-600 uppercase">Status</th>
                            <th className="text-left px-3 py-2 text-xs font-medium text-gray-600 uppercase">Date</th>
                            <th className="text-left px-3 py-2 text-xs font-medium text-gray-600 uppercase">Actions</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                          {monthInvoices.map((invoice) => (
                            <tr key={invoice.id} className="hover:bg-gray-50">
                              <td className="px-3 py-2 font-mono text-sm">{invoice.id}</td>
                              <td className="px-3 py-2 text-sm font-medium">€{invoice.amount.toFixed(2)}</td>
                              <td className="px-3 py-2">
                                <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                  invoice.status === 'paid' 
                                    ? 'bg-green-100 text-green-700'
                                    : invoice.status === 'overdue'
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-yellow-100 text-yellow-700'
                                }`}>
                                  {invoice.status}
                                </span>
                              </td>
                              <td className="px-3 py-2 text-sm text-gray-600">
                                {invoice.status === 'paid' ? invoice.paidDate : `Due: ${invoice.dueDate}`}
                              </td>
                              <td className="px-3 py-2">
                                <button
                                  onClick={() => handleDownloadInvoice(invoice.id)}
                                  className="flex items-center gap-1 px-2 py-1 text-xs font-medium text-purple-700 hover:bg-purple-50 rounded transition-colors"
                                >
                                  <FileText className="w-3.5 h-3.5" />
                                  PDF
                                </button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        ))}
      </div>

      {filteredInvoices.length === 0 && (
        <div className="text-center py-12 text-gray-500">
          No invoices match the current filters
        </div>
      )}
    </div>
  );
}
