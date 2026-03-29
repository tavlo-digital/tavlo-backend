import { useState } from 'react';
import { 
  Search, 
  User,
  Store,
  Users,
  ShoppingCart,
  DollarSign,
  CreditCard,
  AlertCircle,
  UserCheck,
  FileCheck,
  AlertTriangle,
  RefreshCw,
  Flag,
  FileText,
  ShieldAlert,
  QrCode,
  CheckCircle,
  XCircle,
  TrendingUp,
  TrendingDown,
  PackageX,
  Wifi,
  Database,
  Clock
} from 'lucide-react';
import { KPICard } from './KPICard';
import { AlertCard } from './AlertCard';
import { ActionQueueCard } from './ActionQueueCard';
import { ActivityFeedItem } from './ActivityFeedItem';

export function AdminDashboard() {
  const [searchQuery, setSearchQuery] = useState('');

  // Mock data - Platform Health KPIs
  const kpis = {
    activeVendorsToday: { value: 142, total: 247, onClick: () => console.log('Navigate to vendors') },
    activeCustomersToday: { value: 1284, onClick: () => console.log('Navigate to customers') },
    ordersToday: { value: 3482, trend: '+12%', onClick: () => console.log('Navigate to orders') },
    gmvToday: { value: '€48,240', onClick: () => console.log('Navigate to payments') },
    failedPayments24h: { value: 7, variant: 'warning' as const, onClick: () => console.log('Navigate to failed payments') },
    openSupportTickets: { value: 3, variant: 'default' as const, onClick: () => console.log('Navigate to support') },
  };

  // Mock data - Critical Alerts
  const alerts = [
    {
      severity: 'critical' as const,
      title: 'Payment Failure Spike Detected',
      description: '12 payment failures in last hour for vendor "Bella Italia" (VID-8492). PSP error code 51 (Insufficient funds).',
      timestamp: '8 minutes ago',
      actionLabel: 'View Vendor',
      onAction: () => console.log('Navigate to vendor'),
      onResolve: () => console.log('Mark as resolved'),
    },
    {
      severity: 'warning' as const,
      title: 'Vendor Onboarding Stuck',
      description: '3 vendors have been in onboarding state for >48 hours. Legal forms not completed.',
      timestamp: '2 hours ago',
      actionLabel: 'Review Vendors',
      onAction: () => console.log('Navigate to onboarding queue'),
      onDismiss: () => console.log('Dismiss alert'),
    },
    {
      severity: 'critical' as const,
      title: 'Subscription Expired But Active',
      description: 'Vendor "Pizza Express" (VID-2847) subscription expired 5 days ago but QR codes still active.',
      timestamp: '4 hours ago',
      actionLabel: 'Suspend Access',
      onAction: () => console.log('Suspend vendor'),
      onResolve: () => console.log('Resolve'),
    },
    {
      severity: 'warning' as const,
      title: 'PSP Webhook Failures',
      description: 'Stripe webhook endpoint returned 500 errors for 8 events. Payment confirmations may be delayed.',
      timestamp: '6 hours ago',
      actionLabel: 'Check Logs',
      onAction: () => console.log('View system logs'),
      onDismiss: () => console.log('Dismiss'),
    },
  ];

  // Mock data - Action Queues
  const actionQueues = [
    {
      title: 'Vendors Pending Approval',
      count: 5,
      description: 'KYC verification completed, awaiting final approval',
      icon: UserCheck,
      onClick: () => console.log('Navigate to vendor approval queue'),
    },
    {
      title: 'KYC Verification Failed',
      count: 3,
      description: 'Identity documents rejected or incomplete',
      icon: ShieldAlert,
      onClick: () => console.log('Navigate to KYC queue'),
      variant: 'urgent' as const,
    },
    {
      title: 'Refunds Awaiting Approval',
      count: 8,
      description: 'Customer refund requests pending review',
      icon: RefreshCw,
      onClick: () => console.log('Navigate to refunds queue'),
    },
    {
      title: 'Open Disputes',
      count: 2,
      description: 'Active chargebacks and payment disputes',
      icon: AlertTriangle,
      onClick: () => console.log('Navigate to disputes queue'),
      variant: 'urgent' as const,
    },
    {
      title: 'Flagged Reviews',
      count: 6,
      description: 'Reviews flagged for inappropriate content',
      icon: Flag,
      onClick: () => console.log('Navigate to content moderation'),
    },
    {
      title: 'Content Moderation Needed',
      count: 4,
      description: 'User-reported menu items or vendor profiles',
      icon: FileText,
      onClick: () => console.log('Navigate to moderation queue'),
    },
  ];

  // Mock data - Activity Feed (Last 24h)
  const activityFeed = [
    {
      type: 'vendor' as const,
      icon: Store,
      title: 'Vendor Created',
      description: '"Sakura Sushi" registered and completed onboarding',
      timestamp: '12 min ago',
      entityId: 'VID-9482',
      onClick: () => console.log('View vendor details'),
    },
    {
      type: 'payment' as const,
      icon: XCircle,
      title: 'Payment Failed',
      description: 'Order #ORD-48293 payment declined (Card expired)',
      timestamp: '28 min ago',
      entityId: 'PAY-3829',
      onClick: () => console.log('View payment'),
    },
    {
      type: 'order' as const,
      icon: CheckCircle,
      title: 'Order Completed',
      description: 'Order #ORD-48291 delivered and marked complete',
      timestamp: '35 min ago',
      entityId: 'ORD-48291',
      onClick: () => console.log('View order'),
    },
    {
      type: 'vendor' as const,
      icon: PackageX,
      title: 'Vendor Unsubscribed',
      description: '"Green Bowl Cafe" downgraded from Premium to Basic',
      timestamp: '1 hour ago',
      entityId: 'VID-2847',
      onClick: () => console.log('View vendor'),
    },
    {
      type: 'menu' as const,
      icon: FileCheck,
      title: 'Menu Published',
      description: '"Bella Italia" published 8 new menu items with translations',
      timestamp: '2 hours ago',
      entityId: 'VID-8492',
      onClick: () => console.log('View menu'),
    },
    {
      type: 'payment' as const,
      icon: RefreshCw,
      title: 'Payment Refunded',
      description: 'Refund processed for Order #ORD-48102 (€34.50)',
      timestamp: '3 hours ago',
      entityId: 'PAY-3801',
      onClick: () => console.log('View refund'),
    },
    {
      type: 'review' as const,
      icon: Flag,
      title: 'Review Flagged',
      description: 'Review for "Pizza Express" flagged for inappropriate language',
      timestamp: '4 hours ago',
      entityId: 'REV-1829',
      onClick: () => console.log('Review moderation'),
    },
    {
      type: 'vendor' as const,
      icon: CheckCircle,
      title: 'Vendor Activated',
      description: '"Coffee Corner" subscription activated (Standard Plan)',
      timestamp: '5 hours ago',
      entityId: 'VID-9471',
      onClick: () => console.log('View vendor'),
    },
    {
      type: 'system' as const,
      icon: Wifi,
      title: 'PSP Webhook Received',
      description: 'Stripe payment_intent.succeeded webhook processed',
      timestamp: '6 hours ago',
      entityId: 'WHK-8291',
    },
    {
      type: 'menu' as const,
      icon: QrCode,
      title: 'QR Code Deactivated',
      description: '"Burger Palace" deactivated table QR codes (Temporary closure)',
      timestamp: '8 hours ago',
      entityId: 'VID-7364',
      onClick: () => console.log('View QR codes'),
    },
  ];

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Top Bar */}
      <div className="bg-white border-b border-gray-200 sticky top-0 z-10">
        <div className="max-w-[1800px] mx-auto px-6 py-4">
          <div className="flex items-center justify-between gap-6">
            {/* Global Search */}
            <div className="flex-1 max-w-2xl">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  type="text"
                  placeholder="Search vendors, orders, payments, subscriptions, QR codes..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full pl-11 pr-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 focus:border-transparent"
                />
              </div>
              <div className="text-xs text-gray-500 mt-1 ml-11">
                Search by: Vendor name/ID, Order ID, Payment ID, Subscription ID, QR ID
              </div>
            </div>

            {/* Admin Profile */}
            <div className="flex items-center gap-3">
              <div className="text-right">
                <div className="text-sm font-medium text-gray-900">Admin User</div>
                <div className="text-xs text-gray-500">Ops Admin</div>
              </div>
              <div className="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center">
                <User className="w-5 h-5 text-white" />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-[1800px] mx-auto px-6 py-6">
        {/* Page Header */}
        <div className="mb-6">
          <h1 className="text-2xl font-semibold text-gray-900 mb-1">Platform Operations</h1>
          <p className="text-sm text-gray-600">Real-time monitoring and action center</p>
        </div>

        {/* Platform Health Snapshot */}
        <div className="mb-6">
          <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Platform Health</h2>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <KPICard
              label="Active Vendors Today"
              value={kpis.activeVendorsToday.value}
              icon={Store}
              onClick={kpis.activeVendorsToday.onClick}
              subtitle={`of ${kpis.activeVendorsToday.total} total`}
            />
            <KPICard
              label="Active Customers Today"
              value={kpis.activeCustomersToday.value}
              icon={Users}
              onClick={kpis.activeCustomersToday.onClick}
            />
            <KPICard
              label="Orders Today"
              value={kpis.ordersToday.value}
              icon={ShoppingCart}
              onClick={kpis.ordersToday.onClick}
              subtitle={kpis.ordersToday.trend}
            />
            <KPICard
              label="GMV Today"
              value={kpis.gmvToday.value}
              icon={DollarSign}
              onClick={kpis.gmvToday.onClick}
            />
            <KPICard
              label="Failed Payments (24h)"
              value={kpis.failedPayments24h.value}
              icon={CreditCard}
              variant={kpis.failedPayments24h.variant}
              onClick={kpis.failedPayments24h.onClick}
            />
            <KPICard
              label="Open Support Tickets"
              value={kpis.openSupportTickets.value}
              icon={AlertCircle}
              onClick={kpis.openSupportTickets.onClick}
            />
          </div>
        </div>

        {/* Alerts & Incidents - PRIMARY SECTION */}
        <div className="mb-6">
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide">Alerts & Incidents</h2>
            <button className="text-sm text-gray-600 hover:text-gray-900">
              View All ({alerts.length})
            </button>
          </div>
          <div className="space-y-3">
            {alerts.map((alert, index) => (
              <AlertCard key={index} {...alert} />
            ))}
          </div>
        </div>

        {/* Two Column Layout: Action Queues + Activity Feed */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          {/* Admin Action Queues */}
          <div>
            <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Action Queues</h2>
            <div className="space-y-3">
              {actionQueues.map((queue, index) => (
                <ActionQueueCard key={index} {...queue} />
              ))}
            </div>
          </div>

          {/* Last 24h Activity Feed */}
          <div>
            <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Last 24h Activity</h2>
            <div className="bg-white border border-gray-200 rounded-lg overflow-hidden max-h-[800px] overflow-y-auto">
              <div className="divide-y divide-gray-100">
                {activityFeed.map((activity, index) => (
                  <ActivityFeedItem key={index} {...activity} />
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Minimal Trend Indicators */}
        <div className="mb-6">
          <h2 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Quick Trends</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button
              onClick={() => console.log('Navigate to analytics')}
              className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-all text-left group"
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-700">Orders (24h)</span>
                <TrendingUp className="w-4 h-4 text-green-600" />
              </div>
              <div className="text-2xl font-semibold text-gray-900 mb-1">3,482</div>
              <div className="text-xs text-green-600">+12% vs yesterday</div>
            </button>

            <button
              onClick={() => console.log('Navigate to analytics')}
              className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-all text-left group"
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-700">Payment Success Rate</span>
                <CheckCircle className="w-4 h-4 text-green-600" />
              </div>
              <div className="text-2xl font-semibold text-gray-900 mb-1">97.8%</div>
              <div className="text-xs text-gray-600">7 failed / 318 total</div>
            </button>

            <button
              onClick={() => console.log('Navigate to analytics')}
              className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-all text-left group"
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-sm font-medium text-gray-700">Avg Response Time</span>
                <Clock className="w-4 h-4 text-blue-600" />
              </div>
              <div className="text-2xl font-semibold text-gray-900 mb-1">180ms</div>
              <div className="text-xs text-gray-600">Platform API latency</div>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
