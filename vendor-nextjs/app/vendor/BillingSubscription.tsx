import { useState, useEffect } from 'react';
import { 
  CreditCard, 
  Download, 
  AlertCircle, 
  CheckCircle, 
  XCircle,
  Calendar,
  FileText,
  TrendingUp,
  Shield,
  RefreshCw,
  ExternalLink,
  Users,
  QrCode,
  ShoppingBag,
  Building2,
  Mail,
  ChevronRight,
  Info,
  X
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { projectId, publicAnonKey } from '@/lib/supabase/info';
import { BillingLockedBanner } from '../vendor-onboarding/BillingLockedBanner';

const API_BASE = `https://${projectId}.supabase.co/functions/v1/make-server-1dccd8d3`;

interface BillingSubscriptionProps {
  vendorId: string;
  vendorStatus?: 'demo' | 'activated' | 'live';
}

type SubscriptionStatus = 'active' | 'past_due' | 'suspended' | 'none';

interface Invoice {
  id: string;
  invoiceNumber: string;
  date: string;
  amount: number;
  vat: number;
  status: 'paid' | 'failed' | 'pending';
  downloadUrl?: string;
}

export function BillingSubscription({ vendorId, vendorStatus }: BillingSubscriptionProps) {
  const [loading, setLoading] = useState(true);
  const [subscriptionData, setSubscriptionData] = useState<any>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [usageStats, setUsageStats] = useState<any>(null);
  const [showSuccessBanner, setShowSuccessBanner] = useState(false);
  const [showCancelModal, setShowCancelModal] = useState(false);

  useEffect(() => {
    loadBillingData();
    
    // 4️⃣ Check for return from Stripe portal
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('payment_updated') === 'success') {
      setShowSuccessBanner(true);
      // Clean URL
      window.history.replaceState({}, '', window.location.pathname);
    }
  }, [vendorId]);

  const loadBillingData = async () => {
    try {
      setLoading(true);
      
      // Load subscription data
      const subResponse = await fetch(`${API_BASE}/vendor/${vendorId}/subscription`, {
        headers: { 'Authorization': `Bearer ${publicAnonKey}` }
      });
      const subData = await subResponse.json();
      setSubscriptionData(subData);

      // Load invoices
      const invResponse = await fetch(`${API_BASE}/vendor/${vendorId}/invoices`, {
        headers: { 'Authorization': `Bearer ${publicAnonKey}` }
      });
      const invData = await invResponse.json();
      setInvoices(invData.invoices || []);

      // Load usage stats
      const usageResponse = await fetch(`${API_BASE}/vendor/${vendorId}/usage-stats`, {
        headers: { 'Authorization': `Bearer ${publicAnonKey}` }
      });
      const usageData = await usageResponse.json();
      setUsageStats(usageData);

    } catch (error) {
      console.error('Error loading billing data:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleUpdatePaymentMethod = async () => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/create-portal-session`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${publicAnonKey}` 
        }
      });
      const data = await response.json();
      
      if (data.portalUrl) {
        window.open(data.portalUrl, '_blank');
      }
    } catch (error) {
      console.error('Error creating portal session:', error);
      alert('Failed to open billing portal. Please try again.');
    }
  };

  const handleRetryPayment = async () => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/retry-payment`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${publicAnonKey}` 
        }
      });
      const data = await response.json();
      
      if (data.success) {
        alert('Payment retry initiated. Please check your email for confirmation.');
        loadBillingData();
      } else {
        alert(data.error || 'Failed to retry payment');
      }
    } catch (error) {
      console.error('Error retrying payment:', error);
      alert('Failed to retry payment. Please try again.');
    }
  };

  const handleDownloadInvoice = async (invoiceId: string) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/invoice/${invoiceId}/pdf`, {
        headers: { 'Authorization': `Bearer ${publicAnonKey}` }
      });
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `invoice-${invoiceId}.pdf`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Error downloading invoice:', error);
      alert('Failed to download invoice. Please try again.');
    }
  };

  const getStatusConfig = (status: SubscriptionStatus) => {
    switch (status) {
      case 'active':
        return {
          color: 'green',
          bg: 'bg-green-50',
          text: 'text-green-700',
          border: 'border-green-200',
          icon: CheckCircle,
          label: 'Active'
        };
      case 'past_due':
        return {
          color: 'yellow',
          bg: 'bg-yellow-50',
          text: 'text-yellow-700',
          border: 'border-yellow-200',
          icon: AlertCircle,
          label: 'Action Required'
        };
      case 'suspended':
        return {
          color: 'red',
          bg: 'bg-red-50',
          text: 'text-red-700',
          border: 'border-red-200',
          icon: XCircle,
          label: 'Suspended'
        };
      default:
        return {
          color: 'gray',
          bg: 'bg-gray-50',
          text: 'text-gray-700',
          border: 'border-gray-200',
          icon: AlertCircle,
          label: 'Not Subscribed'
        };
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-600"></div>
      </div>
    );
  }

  const status = subscriptionData?.status || 'none';
  const statusConfig = getStatusConfig(status);
  const StatusIcon = statusConfig.icon;
  const isSuspended = status === 'suspended' || status === 'past_due';

  return (
    <div className="min-h-full bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl mb-2">Billing & Subscription</h1>
          <p className="text-gray-600">Manage your TAVLO subscription and billing information</p>
        </div>

        {/* Locked Banner - Show during onboarding */}
        {vendorStatus && <BillingLockedBanner vendorStatus={vendorStatus} />}

        {/* Suspension Banner */}
        {isSuspended && (
          <div className={`${statusConfig.bg} border ${statusConfig.border} rounded-lg p-6 mb-6`}>
            <div className="flex items-start gap-4">
              <AlertCircle className={`w-6 h-6 ${statusConfig.text} flex-shrink-0 mt-0.5`} />
              <div className="flex-1">
                <h3 className={`text-lg ${statusConfig.text} mb-2`}>
                  {status === 'past_due' ? 'Payment Required' : 'Subscription Suspended'}
                </h3>
                <p className="text-gray-700 mb-4">
                  {status === 'past_due' 
                    ? 'Your payment is overdue. Orders are paused until payment is completed.'
                    : 'Your subscription has been suspended. Please contact support or reactivate to resume service.'}
                </p>
                <div className="flex gap-3">
                  {status === 'past_due' && (
                    <Button 
                      onClick={handleRetryPayment}
                      className="bg-orange-600 hover:bg-orange-700 text-white"
                    >
                      <RefreshCw className="w-4 h-4 mr-2" />
                      Retry Payment
                    </Button>
                  )}
                  <Button 
                    onClick={handleUpdatePaymentMethod}
                    variant="outline"
                  >
                    Update Payment Method
                  </Button>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Success Banner */}
        {showSuccessBanner && (
          <div className="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
            <div className="flex items-start gap-4">
              <CheckCircle className="w-6 h-6 text-green-600 flex-shrink-0 mt-0.5" />
              <div className="flex-1">
                <h3 className="text-lg text-green-600 mb-2">Payment Updated Successfully</h3>
                <p className="text-gray-700 mb-4">
                  Your payment method has been updated successfully. You can now proceed with your billing.
                </p>
                <div className="flex gap-3">
                  <Button 
                    onClick={() => setShowSuccessBanner(false)}
                    variant="outline"
                  >
                    <X className="w-4 h-4 mr-2" />
                    Close
                  </Button>
                </div>
              </div>
            </div>
          </div>
        )}

        <div className="grid lg:grid-cols-3 gap-6">
          {/* Left Column - Main Content */}
          <div className="lg:col-span-2 space-y-6">
            {/* 1️⃣ Subscription Overview */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-center justify-between mb-2">
                <h2 className="text-xl">Subscription Overview</h2>
                <div className={`flex items-center gap-2 px-3 py-1.5 rounded-full ${statusConfig.bg} ${statusConfig.border} border`}>
                  <StatusIcon className={`w-4 h-4 ${statusConfig.text}`} />
                  <span className={`text-sm ${statusConfig.text}`}>{statusConfig.label}</span>
                </div>
              </div>

              <p className="text-sm text-gray-600 mb-6">
                Your subscription controls access to Tavlo features. Cancelling your subscription will deactivate your restaurant on Tavlo.
              </p>

              <div className="grid sm:grid-cols-2 gap-6 mb-6">
                <div>
                  <div className="text-sm text-gray-600 mb-1">Current Plan</div>
                  <div className="text-2xl">{subscriptionData?.planName || 'Monthly'}</div>
                </div>
                <div>
                  <div className="text-sm text-gray-600 mb-1">Billing Cycle</div>
                  <div className="text-2xl">
                    €{subscriptionData?.price || 49}
                    <span className="text-lg text-gray-600">/{subscriptionData?.interval || 'month'}</span>
                  </div>
                </div>
                <div>
                  <div className="text-sm text-gray-600 mb-1">Next Billing Date</div>
                  <div className="flex items-center gap-2">
                    <Calendar className="w-4 h-4 text-gray-400" />
                    <span>{subscriptionData?.nextBillingDate || 'Jan 23, 2024'}</span>
                  </div>
                </div>
                <div>
                  <div className="text-sm text-gray-600 mb-1">Subscription ID</div>
                  <div className="text-sm font-mono text-gray-600">
                    {subscriptionData?.subscriptionId || 'sub_demo_123'}
                  </div>
                </div>
              </div>

              <div className="flex flex-wrap gap-3 pt-4 border-t border-gray-200">
                <Button 
                  className="bg-orange-600 hover:bg-orange-700 text-white"
                  onClick={() => alert('Upgrade flow coming soon')}
                >
                  <TrendingUp className="w-4 h-4 mr-2" />
                  Upgrade Plan
                </Button>
                <Button 
                  variant="outline"
                  onClick={() => alert('Change cycle flow coming soon')}
                >
                  Change Billing Cycle
                </Button>
                <Button 
                  variant="outline"
                  onClick={handleUpdatePaymentMethod}
                >
                  <CreditCard className="w-4 h-4 mr-2" />
                  Update Payment Method
                </Button>
              </div>

              {/* 1️⃣ Payment Consequence Clarity */}
              <div className="mt-4 pt-4 border-t border-gray-200">
                <div className="flex items-start gap-2 text-sm text-gray-600">
                  <Info className="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                  <p>If your payment fails, orders and QR codes will be temporarily paused until payment is resolved.</p>
                </div>
              </div>

              {/* 5️⃣ Admin Visibility Transparency */}
              <div className="mt-3 flex items-start gap-2 text-xs text-gray-500">
                <Info className="w-3 h-3 text-gray-400 flex-shrink-0 mt-0.5" />
                <p>Subscription ID is visible to TAVLO admin for support and compliance purposes.</p>
              </div>
            </div>

            {/* 2️⃣ Payment Method */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h2 className="text-xl mb-6">Payment Method</h2>

              <div className="flex items-start gap-4 p-4 bg-gray-50 rounded-lg mb-4">
                <div className="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center flex-shrink-0">
                  <CreditCard className="w-6 h-6 text-white" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="font-medium">
                      {subscriptionData?.paymentMethod?.brand || 'Card'} ending in {subscriptionData?.paymentMethod?.last4 || '****'}
                    </span>
                    {subscriptionData?.paymentMethod?.isDefault && (
                      <span className="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Default</span>
                    )}
                  </div>
                  <div className="text-sm text-gray-600">
                    Expires {subscriptionData?.paymentMethod?.expMonth || '12'}/{subscriptionData?.paymentMethod?.expYear || '2025'}
                  </div>
                </div>
              </div>

              <div className="grid sm:grid-cols-2 gap-4 mb-4">
                <div>
                  <div className="text-sm text-gray-600 mb-1">Billing Email</div>
                  <div className="flex items-center gap-2">
                    <Mail className="w-4 h-4 text-gray-400" />
                    <span className="text-sm">{subscriptionData?.billingEmail || 'vendor@example.com'}</span>
                  </div>
                </div>
                <div>
                  <div className="text-sm text-gray-600 mb-1">Last Updated</div>
                  <div className="text-sm">{subscriptionData?.paymentMethodUpdated || 'Dec 1, 2024'}</div>
                </div>
              </div>

              <Button 
                variant="outline" 
                onClick={handleUpdatePaymentMethod}
                className="w-full sm:w-auto"
              >
                <ExternalLink className="w-4 h-4 mr-2" />
                Update Payment Method
              </Button>
            </div>

            {/* 3️⃣ Invoices & Documents */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl">Invoices & Documents</h2>
                <Button 
                  variant="outline" 
                  size="sm"
                  onClick={handleUpdatePaymentMethod}
                >
                  <ExternalLink className="w-4 h-4 mr-2" />
                  View All in Portal
                </Button>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-gray-200">
                      <th className="text-left py-3 px-2 text-sm text-gray-600">Invoice</th>
                      <th className="text-left py-3 px-2 text-sm text-gray-600">Date</th>
                      <th className="text-left py-3 px-2 text-sm text-gray-600">Amount</th>
                      <th className="text-left py-3 px-2 text-sm text-gray-600">
                        <div className="flex items-center gap-1 group relative">
                          VAT
                          <Info className="w-3 h-3 text-gray-400 cursor-help" />
                          <div className="hidden group-hover:block absolute left-0 top-full mt-1 z-10 w-64 p-2 bg-gray-900 text-white text-xs rounded shadow-lg">
                            VAT shown applies to TAVLO subscription services only, not customer order VAT.
                          </div>
                        </div>
                      </th>
                      <th className="text-left py-3 px-2 text-sm text-gray-600">Status</th>
                      <th className="text-right py-3 px-2 text-sm text-gray-600">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {invoices.length === 0 ? (
                      <tr>
                        <td colSpan={6} className="text-center py-8 text-gray-500">
                          <FileText className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                          No invoices yet
                        </td>
                      </tr>
                    ) : (
                      invoices.map((invoice) => (
                        <tr key={invoice.id} className="border-b border-gray-100">
                          <td className="py-4 px-2">
                            <div className="font-medium text-sm">{invoice.invoiceNumber}</div>
                          </td>
                          <td className="py-4 px-2 text-sm text-gray-600">{invoice.date}</td>
                          <td className="py-4 px-2 text-sm">€{invoice.amount.toFixed(2)}</td>
                          <td className="py-4 px-2 text-sm text-gray-600">€{invoice.vat.toFixed(2)}</td>
                          <td className="py-4 px-2">
                            <span className={`inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full ${
                              invoice.status === 'paid' 
                                ? 'bg-green-100 text-green-700'
                                : invoice.status === 'failed'
                                ? 'bg-red-100 text-red-700'
                                : 'bg-yellow-100 text-yellow-700'
                            }`}>
                              {invoice.status === 'paid' ? <CheckCircle className="w-3 h-3" /> : <XCircle className="w-3 h-3" />}
                              {invoice.status}
                            </span>
                          </td>
                          <td className="py-4 px-2 text-right">
                            <Button 
                              variant="ghost" 
                              size="sm"
                              onClick={() => handleDownloadInvoice(invoice.id)}
                            >
                              <Download className="w-4 h-4" />
                            </Button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          {/* Right Column - Sidebar */}
          <div className="space-y-6">
            {/* 4️⃣ Usage & Plan Limits */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h2 className="text-lg mb-4">Usage This Month</h2>

              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                      <QrCode className="w-5 h-5 text-blue-600" />
                    </div>
                    <div>
                      <div className="text-sm text-gray-600">Active Tables</div>
                      <div className="text-xl">{usageStats?.activeTables || 12}</div>
                    </div>
                  </div>
                  <span className="text-xs text-green-600">Unlimited</span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                      <ShoppingBag className="w-5 h-5 text-purple-600" />
                    </div>
                    <div>
                      <div className="text-sm text-gray-600">Orders</div>
                      <div className="text-xl">{usageStats?.ordersThisMonth || 847}</div>
                    </div>
                  </div>
                  <span className="text-xs text-green-600">Unlimited</span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                      <Users className="w-5 h-5 text-orange-600" />
                    </div>
                    <div>
                      <div className="text-sm text-gray-600">Staff Accounts</div>
                      <div className="text-xl">{usageStats?.staffAccounts || 3}</div>
                    </div>
                  </div>
                  <span className="text-xs text-green-600">Unlimited</span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                      <Building2 className="w-5 h-5 text-green-600" />
                    </div>
                    <div>
                      <div className="text-sm text-gray-600">QR Codes</div>
                      <div className="text-xl">{usageStats?.qrCodes || 15}</div>
                    </div>
                  </div>
                  <span className="text-xs text-green-600">Unlimited</span>
                </div>
              </div>

              <div className="mt-6 pt-6 border-t border-gray-200">
                <div className="text-sm text-gray-600 mb-3">All features included in your plan</div>
                <div className="flex items-center gap-2 text-sm text-green-600">
                  <CheckCircle className="w-4 h-4" />
                  <span>No usage limits</span>
                </div>

                {/* 2️⃣ Usage Disclaimer */}
                <div className="mt-4 text-xs text-gray-500">
                  Fair usage applies. Excessive or abusive usage may be reviewed.
                </div>
              </div>
            </div>

            {/* 5️⃣ Plan Comparison */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h2 className="text-lg mb-4">Upgrade Your Plan</h2>

              <div className="space-y-3 mb-4">
                <div className="p-4 border-2 border-orange-200 bg-orange-50 rounded-lg">
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-medium">Monthly</span>
                    <span className="text-xs bg-orange-600 text-white px-2 py-1 rounded">Current</span>
                  </div>
                  <div className="text-2xl mb-1">€49<span className="text-sm text-gray-600">/mo</span></div>
                  <div className="text-xs text-gray-600">Pay monthly, cancel anytime</div>
                </div>

                <div className="p-4 border-2 border-gray-200 rounded-lg hover:border-orange-300 transition-colors cursor-pointer">
                  <div className="flex items-center justify-between mb-2">
                    <span className="font-medium">Yearly</span>
                    <span className="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Save 17%</span>
                  </div>
                  <div className="text-2xl mb-1">€490<span className="text-sm text-gray-600">/yr</span></div>
                  <div className="text-xs text-gray-600">€40.83/mo when billed annually</div>
                </div>
              </div>

              <Button 
                className="w-full bg-orange-600 hover:bg-orange-700 text-white"
                onClick={() => alert('Upgrade to yearly plan')}
              >
                Upgrade to Yearly
                <ChevronRight className="w-4 h-4 ml-2" />
              </Button>

              <div className="mt-4 pt-4 border-t border-gray-200">
                <Button 
                  variant="ghost" 
                  className="w-full text-sm"
                  onClick={() => alert('Contact support')}
                >
                  Contact Support
                </Button>
              </div>
            </div>

            {/* Security Note */}
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <Shield className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h3 className="text-sm text-blue-900 mb-1">Secure Payments</h3>
                  <p className="text-xs text-blue-700">
                    All payments are processed securely through Stripe. We never store your card details.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Cancel Subscription Section */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mt-6">
          <h2 className="text-xl mb-2">Cancel Subscription</h2>
          <p className="text-sm text-gray-600 mb-6">
            Canceling your subscription will deactivate your restaurant and pause all Tavlo services. Certain data may be retained for legal and accounting purposes.
          </p>
          <Button 
            variant="outline" 
            className="border-red-300 text-red-600 hover:bg-red-50"
            onClick={() => setShowCancelModal(true)}
          >
            <XCircle className="w-4 h-4 mr-2" />
            Cancel Subscription
          </Button>
        </div>
      </div>

      {/* Cancel Subscription Confirmation Modal */}
      {showCancelModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-lg max-w-lg w-full p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-xl">Cancel Tavlo Subscription</h3>
              <button 
                onClick={() => setShowCancelModal(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="mb-6">
              <p className="text-sm text-gray-600 mb-4">
                Canceling your subscription will have the following effects:
              </p>
              
              <ul className="space-y-3 text-sm text-gray-700">
                <li className="flex items-start gap-2">
                  <XCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                  <span>Menu will be unpublished</span>
                </li>
                <li className="flex items-start gap-2">
                  <XCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                  <span>QR codes will stop working</span>
                </li>
                <li className="flex items-start gap-2">
                  <XCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                  <span>Orders and reservations will be disabled</span>
                </li>
                <li className="flex items-start gap-2">
                  <XCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                  <span>Dashboard access becomes read-only</span>
                </li>
                <li className="flex items-start gap-2">
                  <AlertCircle className="w-4 h-4 text-orange-500 flex-shrink-0 mt-0.5" />
                  <span>Billing stops at the end of the current period</span>
                </li>
                <li className="flex items-start gap-2">
                  <Info className="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
                  <span>Data is retained according to legal requirements</span>
                </li>
              </ul>
            </div>

            <div className="flex gap-3">
              <Button 
                variant="outline"
                onClick={() => setShowCancelModal(false)}
                className="flex-1"
              >
                Keep Subscription
              </Button>
              <Button 
                className="flex-1 bg-red-600 hover:bg-red-700 text-white"
                onClick={async () => {
                  try {
                    // TODO: Implement actual cancellation API call
                    await fetch(`${API_BASE}/vendor/${vendorId}/cancel-subscription`, {
                      method: 'POST',
                      headers: { 'Authorization': `Bearer ${publicAnonKey}` }
                    });
                    alert('Subscription cancellation initiated. You will receive a confirmation email.');
                    setShowCancelModal(false);
                    loadBillingData();
                  } catch (error) {
                    console.error('Error canceling subscription:', error);
                    alert('Failed to cancel subscription. Please try again or contact support.');
                  }
                }}
              >
                Confirm Cancellation
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}