import { useState } from 'react';
import { 
  Settings, 
  Globe, 
  CreditCard, 
  FileText, 
  Shield, 
  Users, 
  Bell,
  Lock,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Info,
  Eye,
  Save,
  Plus,
  Trash2,
  Edit,
  Key,
  Activity,
  Clock,
  DollarSign,
  Zap,
  Building,
  UserCheck,
  Database
} from 'lucide-react';
import { Input } from '../ui/input';
import { toast } from 'sonner@2.0.3';
import { EnhancedRolesPermissions } from './EnhancedRolesPermissions';
import { EnhancedPaymentInfrastructure } from './EnhancedPaymentInfrastructure';
import { SubscriptionGovernance } from './SubscriptionGovernance';
import { ComplianceDataPrivacy } from './ComplianceDataPrivacy';
import { VendorOnboardingRules } from './VendorOnboardingRules';

type TabType = 'general' | 'payments' | 'subscriptions' | 'compliance' | 'onboarding' | 'notifications' | 'roles';

export function SystemSettings() {
  const [activeTab, setActiveTab] = useState<TabType>('general');
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  const tabs = [
    { id: 'general' as TabType, label: 'General', icon: Globe },
    { id: 'payments' as TabType, label: 'Payment Infrastructure', icon: CreditCard },
    { id: 'subscriptions' as TabType, label: 'Subscriptions & Billing', icon: FileText },
    { id: 'compliance' as TabType, label: 'Compliance & Privacy', icon: Shield },
    { id: 'onboarding' as TabType, label: 'Vendor Onboarding', icon: UserCheck },
    { id: 'notifications' as TabType, label: 'Admin Notifications', icon: Bell },
    { id: 'roles' as TabType, label: 'Roles & Permissions', icon: Lock },
  ];

  const handleSave = () => {
    toast.success('Settings saved successfully');
    setHasUnsavedChanges(false);
  };

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-2xl mb-1">System Settings</h1>
        <p className="text-sm text-gray-500">Configure platform-level settings and governance</p>
      </div>

      {/* Platform Governance Notice */}
      <div className="mb-6 bg-purple-50 border border-purple-200 rounded-xl p-4">
        <div className="flex items-start gap-3">
          <Settings className="w-5 h-5 text-purple-600 mt-0.5" />
          <div>
            <h3 className="font-medium text-purple-900 mb-1">Platform Governance Only</h3>
            <p className="text-sm text-purple-700">
              <strong>These settings control Tavlo platform infrastructure only.</strong>
              {' '}Restaurant operations (menus, prices, tips, order rules) are configured by each vendor in their dashboard.
              Admin cannot change vendor operational settings.
            </p>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-gray-200 mb-6 overflow-x-auto">
        {tabs.map((tab) => {
          const Icon = tab.icon;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`pb-3 px-4 border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 ${
                activeTab === tab.id
                  ? 'border-purple-600 text-purple-600'
                  : 'border-transparent text-gray-600 hover:text-gray-900'
              }`}
            >
              <Icon className="w-4 h-4" />
              <span className="text-sm font-medium">{tab.label}</span>
            </button>
          );
        })}
      </div>

      {/* Tab Content */}
      <div className="bg-white rounded-xl border border-gray-200">
        {activeTab === 'general' && <GeneralSettings onChange={() => setHasUnsavedChanges(true)} />}
        {activeTab === 'payments' && <EnhancedPaymentInfrastructure isSuperAdmin={true} />}
        {activeTab === 'subscriptions' && <SubscriptionGovernance isSuperAdmin={true} />}
        {activeTab === 'compliance' && <ComplianceDataPrivacy isComplianceAdmin={true} />}
        {activeTab === 'onboarding' && <VendorOnboardingRules isSuperAdmin={true} />}
        {activeTab === 'notifications' && <NotificationSettings onChange={() => setHasUnsavedChanges(true)} />}
        {activeTab === 'roles' && <EnhancedRolesPermissions currentUserRole="super_admin" />}
      </div>

      {/* Save Bar */}
      {hasUnsavedChanges && (
        <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg p-4 z-50">
          <div className="max-w-7xl mx-auto flex items-center justify-between">
            <div className="flex items-center gap-2 text-amber-600">
              <AlertTriangle className="w-5 h-5" />
              <span className="text-sm font-medium">You have unsaved changes</span>
            </div>
            <div className="flex gap-3">
              <button 
                onClick={() => setHasUnsavedChanges(false)}
                className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                Discard
              </button>
              <button 
                onClick={handleSave}
                className="px-4 py-2 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center gap-2"
              >
                <Save className="w-4 h-4" />
                Save Changes
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// General Settings Tab
function GeneralSettings({ onChange }: { onChange: () => void }) {
  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="text-lg font-medium mb-4">General Platform Settings</h2>
        <p className="text-sm text-gray-500 mb-6">Configure platform-wide defaults and localization</p>
      </div>

      {/* Platform Language */}
      <div className="border-b border-gray-200 pb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Default Platform Language
        </label>
        <p className="text-xs text-gray-500 mb-3">
          Default language for admin interface. Users can override in their settings.
        </p>
        <select 
          className="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg"
          onChange={onChange}
        >
          <option value="en">English</option>
          <option value="de">German (Deutsch)</option>
          <option value="ar">Arabic (العربية)</option>
          <option value="fr">French (Français)</option>
        </select>
      </div>

      {/* Supported Languages */}
      <div className="border-b border-gray-200 pb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Supported UI Languages
        </label>
        <p className="text-xs text-gray-500 mb-3">
          Languages available to all users (customers, vendors, admin)
        </p>
        <div className="space-y-2">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">English</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">German (Deutsch)</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Arabic (العربية)</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">French (Français)</span>
          </label>
        </div>
      </div>

      {/* Timezone */}
      <div className="border-b border-gray-200 pb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Platform Timezone
        </label>
        <p className="text-xs text-gray-500 mb-3">
          Default timezone for platform operations and reporting
        </p>
        <select 
          className="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg"
          onChange={onChange}
        >
          <option value="Europe/Vienna">Europe/Vienna (CET/CEST)</option>
          <option value="Europe/Berlin">Europe/Berlin (CET/CEST)</option>
          <option value="UTC">UTC</option>
          <option value="Europe/London">Europe/London (GMT/BST)</option>
        </select>
      </div>

      {/* Date/Time Format */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Date & Time Format
        </label>
        <p className="text-xs text-gray-500 mb-3">
          Display format for dates and times across the platform
        </p>
        <div className="grid grid-cols-2 gap-4 max-w-2xl">
          <div>
            <label className="block text-xs text-gray-600 mb-2">Date Format</label>
            <select 
              className="w-full px-4 py-2 border border-gray-300 rounded-lg"
              onChange={onChange}
            >
              <option value="DD/MM/YYYY">DD/MM/YYYY (25/12/2024)</option>
              <option value="MM/DD/YYYY">MM/DD/YYYY (12/25/2024)</option>
              <option value="YYYY-MM-DD">YYYY-MM-DD (2024-12-25)</option>
            </select>
          </div>
          <div>
            <label className="block text-xs text-gray-600 mb-2">Time Format</label>
            <select 
              className="w-full px-4 py-2 border border-gray-300 rounded-lg"
              onChange={onChange}
            >
              <option value="24h">24-hour (18:30)</option>
              <option value="12h">12-hour (6:30 PM)</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  );
}

// Payment Settings Tab
function PaymentSettings({ onChange }: { onChange: () => void }) {
  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="text-lg font-medium mb-4">Payment Infrastructure</h2>
        <p className="text-sm text-gray-500 mb-2">Configure platform-level payment providers</p>
      </div>

      {/* Vendor Settings Notice */}
      <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
        <div className="flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 text-amber-600 mt-0.5" />
          <div>
            <h3 className="text-sm font-medium text-amber-900 mb-1">Vendor-Controlled Settings</h3>
            <p className="text-xs text-amber-700">
              Payment methods, tipping settings, and service fees are configured by each vendor.
              These settings only control which providers are available on the platform.
            </p>
          </div>
        </div>
      </div>

      {/* Stripe */}
      <div className="border border-gray-200 rounded-lg p-5">
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
              <CreditCard className="w-5 h-5 text-purple-600" />
            </div>
            <div>
              <h3 className="font-medium">Stripe</h3>
              <p className="text-xs text-gray-500">Card payments & digital wallets</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <span className="flex items-center gap-1.5 text-xs px-2.5 py-1 bg-green-100 text-green-700 rounded-full">
              <CheckCircle className="w-3.5 h-3.5" />
              Active
            </span>
          </div>
        </div>

        <div className="space-y-3">
          <div>
            <label className="block text-xs text-gray-600 mb-1.5">API Mode</label>
            <select className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" onChange={onChange}>
              <option value="live">Live Mode</option>
              <option value="test">Test Mode</option>
            </select>
          </div>

          <div>
            <label className="block text-xs text-gray-600 mb-1.5">Publishable Key</label>
            <div className="flex gap-2">
              <Input type="password" value="pk_live_••••••••••••••••" className="flex-1 text-sm" readOnly />
              <button className="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                <Eye className="w-4 h-4 text-gray-600" />
              </button>
            </div>
          </div>

          <div>
            <label className="block text-xs text-gray-600 mb-1.5">Secret Key</label>
            <div className="flex gap-2">
              <Input type="password" value="sk_live_••••••••••••••••" className="flex-1 text-sm" readOnly />
              <button className="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                <Eye className="w-4 h-4 text-gray-600" />
              </button>
            </div>
          </div>

          <div className="pt-3 border-t border-gray-200">
            <div className="flex items-center justify-between text-xs">
              <span className="text-gray-600">Webhook Status</span>
              <span className="flex items-center gap-1.5 text-green-600">
                <Activity className="w-3.5 h-3.5" />
                Receiving events
              </span>
            </div>
            <div className="mt-2">
              <button className="text-xs text-purple-600 hover:text-purple-700">View webhook logs →</button>
            </div>
          </div>
        </div>
      </div>

      {/* PayPal */}
      <div className="border border-gray-200 rounded-lg p-5">
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
              <DollarSign className="w-5 h-5 text-blue-600" />
            </div>
            <div>
              <h3 className="font-medium">PayPal</h3>
              <p className="text-xs text-gray-500">PayPal & Venmo payments</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <label className="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" className="sr-only peer" onChange={onChange} />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
          </div>
        </div>

        <div className="text-sm text-gray-500">
          Enable PayPal to allow vendors to accept PayPal payments
        </div>
      </div>

      {/* Cash Payments Notice */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start gap-3">
          <Info className="w-5 h-5 text-blue-600 mt-0.5" />
          <div>
            <h3 className="text-sm font-medium text-blue-900 mb-1">Cash Payment Configuration</h3>
            <p className="text-xs text-blue-700">
              Cash payment acceptance is configured by each vendor in their dashboard settings.
              Tavlo does not control whether vendors accept cash.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

// Subscription Settings Tab
function SubscriptionSettings({ onChange }: { onChange: () => void }) {
  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="text-lg font-medium mb-4">Subscriptions & Billing</h2>
        <p className="text-sm text-gray-500 mb-2">Configure Tavlo subscription plans and billing rules</p>
        <div className="bg-purple-50 border border-purple-200 rounded-lg p-3 text-xs text-purple-700">
          <strong>Note:</strong> These are Tavlo subscription fees charged to vendors for using the platform. 
          Not related to restaurant menu pricing or customer orders.
        </div>
      </div>

      {/* Subscription Plans */}
      <div>
        <h3 className="font-medium mb-4">Subscription Plans</h3>
        <div className="space-y-4">
          {/* Basic Plan */}
          <div className="border border-gray-200 rounded-lg p-5">
            <div className="flex items-start justify-between mb-4">
              <div>
                <h4 className="font-medium">Basic Plan</h4>
                <p className="text-xs text-gray-500">For small restaurants getting started</p>
              </div>
              <button className="text-sm text-purple-600 hover:text-purple-700">
                <Edit className="w-4 h-4" />
              </button>
            </div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <span className="text-xs text-gray-500">Monthly Price</span>
                <div className="font-medium">€49.00</div>
              </div>
              <div>
                <span className="text-xs text-gray-500">Annual Price</span>
                <div className="font-medium">€490.00</div>
              </div>
              <div>
                <span className="text-xs text-gray-500">Max Orders/Month</span>
                <div className="font-medium">500</div>
              </div>
              <div>
                <span className="text-xs text-gray-500">VAT Rate</span>
                <div className="font-medium">20%</div>
              </div>
            </div>
            <div className="mt-3 pt-3 border-t border-gray-200">
              <span className="text-xs text-gray-500">Features:</span>
              <div className="mt-2 flex flex-wrap gap-2">
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">QR Code Ordering</span>
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">Basic Analytics</span>
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">Email Support</span>
              </div>
            </div>
          </div>

          {/* Standard Plan */}
          <div className="border border-gray-200 rounded-lg p-5">
            <div className="flex items-start justify-between mb-4">
              <div>
                <h4 className="font-medium">Standard Plan</h4>
                <p className="text-xs text-gray-500">For growing restaurants</p>
              </div>
              <button className="text-sm text-purple-600 hover:text-purple-700">
                <Edit className="w-4 h-4" />
              </button>
            </div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <span className="text-xs text-gray-500">Monthly Price</span>
                <div className="font-medium">€99.00</div>
              </div>
              <div>
                <span className="text-xs text-gray-500">Annual Price</span>
                <div className="font-medium">€990.00</div>
              </div>
              <div>
                <span className="text-xs text-gray-500">Max Orders/Month</span>
                <div className="font-medium">2,000</div>
              </div>
              <div>
                <span className="text-xs text-gray-500">VAT Rate</span>
                <div className="font-medium">20%</div>
              </div>
            </div>
            <div className="mt-3 pt-3 border-t border-gray-200">
              <span className="text-xs text-gray-500">Features:</span>
              <div className="mt-2 flex flex-wrap gap-2">
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">All Basic Features</span>
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">Advanced Analytics</span>
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">Priority Support</span>
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">Custom Branding</span>
              </div>
            </div>
          </div>

          {/* Premium Plan */}
          <div className="border border-gray-200 rounded-lg p-5 bg-gradient-to-br from-purple-50 to-white">
            <div className="flex items-start justify-between mb-4">
              <div>
                <h4 className="font-medium flex items-center gap-2">
                  Premium Plan
                  <span className="text-xs px-2 py-0.5 bg-purple-600 text-white rounded-full">Popular</span>
                </h4>
                <p className="text-xs text-gray-500">For established restaurants</p>
              </div>
              <button className="text-sm text-purple-600 hover:text-purple-700">
                <Edit className="w-4 h-4" />
              </button>
            </div>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div>
                <span className="text-xs text-gray-500">Monthly Price</span>
                <div className="font-medium">€199.00</div>
              </div>
              <div>
                <span className="text-xs text-gray-500">Annual Price</span>
                <div className="font-medium">€1,990.00</div>
              </div>
              <div>
                <span className="text-xs text-gray-500">Max Orders/Month</span>
                <div className="font-medium">Unlimited</div>
              </div>
              <div>
                <span className="text-xs text-gray-500">VAT Rate</span>
                <div className="font-medium">20%</div>
              </div>
            </div>
            <div className="mt-3 pt-3 border-t border-gray-200">
              <span className="text-xs text-gray-500">Features:</span>
              <div className="mt-2 flex flex-wrap gap-2">
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">All Standard Features</span>
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">Multi-location</span>
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">API Access</span>
                <span className="text-xs px-2 py-1 bg-gray-100 rounded">24/7 Support</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Trial & Grace Period */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Trial & Grace Period Configuration</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Trial Period Duration
            </label>
            <select className="w-full px-4 py-2 border border-gray-300 rounded-lg" onChange={onChange} defaultValue="14">
              <option value="0">No trial</option>
              <option value="7">7 days</option>
              <option value="14">14 days</option>
              <option value="30">30 days</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Payment Grace Period
            </label>
            <select className="w-full px-4 py-2 border border-gray-300 rounded-lg" onChange={onChange} defaultValue="7">
              <option value="3">3 days</option>
              <option value="7">7 days</option>
              <option value="14">14 days</option>
            </select>
          </div>
        </div>
        <p className="text-xs text-gray-500 mt-3">
          After grace period expires, vendor account is automatically suspended until payment is received
        </p>
      </div>

      {/* Invoice Settings */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Invoice Configuration</h3>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Invoice Frequency
            </label>
            <select className="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg" onChange={onChange} defaultValue="monthly">
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="annual">Annual</option>
            </select>
          </div>
          <div>
            <label className="flex items-center gap-2">
              <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
              <span className="text-sm">Auto-send invoices on billing date</span>
            </label>
          </div>
          <div>
            <label className="flex items-center gap-2">
              <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
              <span className="text-sm">Send payment reminders for overdue invoices</span>
            </label>
          </div>
        </div>
      </div>
    </div>
  );
}

// Compliance Settings Tab
function ComplianceSettings({ onChange }: { onChange: () => void }) {
  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="text-lg font-medium mb-4">Compliance & Data Privacy</h2>
        <p className="text-sm text-gray-500 mb-2">Configure GDPR compliance and data protection rules</p>
      </div>

      {/* GDPR Tools */}
      <div>
        <h3 className="font-medium mb-4">GDPR User Data Management</h3>
        <div className="space-y-3">
          <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
            <div>
              <div className="font-medium text-sm">User Data Export</div>
              <div className="text-xs text-gray-500">Allow users to request full data export</div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" defaultChecked className="sr-only peer" onChange={onChange} />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
          </div>
          <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
            <div>
              <div className="font-medium text-sm">Right to be Forgotten</div>
              <div className="text-xs text-gray-500">Allow users to request account deletion</div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" defaultChecked className="sr-only peer" onChange={onChange} />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
          </div>
          <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
            <div>
              <div className="font-medium text-sm">Data Portability</div>
              <div className="text-xs text-gray-500">Export data in machine-readable format</div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" defaultChecked className="sr-only peer" onChange={onChange} />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
          </div>
        </div>
      </div>

      {/* Data Retention */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Data Retention Rules</h3>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Order Data Retention
            </label>
            <select className="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg" onChange={onChange} defaultValue="7">
              <option value="1">1 year</option>
              <option value="2">2 years</option>
              <option value="3">3 years</option>
              <option value="7">7 years (recommended for tax compliance)</option>
              <option value="indefinite">Indefinite</option>
            </select>
            <p className="text-xs text-gray-500 mt-2">Austrian law requires 7 years for financial records</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Guest User Data Retention
            </label>
            <select className="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg" onChange={onChange} defaultValue="90">
              <option value="30">30 days</option>
              <option value="90">90 days</option>
              <option value="180">180 days</option>
              <option value="365">1 year</option>
            </select>
            <p className="text-xs text-gray-500 mt-2">Guest orders without registered accounts</p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Audit Log Retention
            </label>
            <select className="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg" onChange={onChange} defaultValue="7">
              <option value="3">3 years</option>
              <option value="7">7 years</option>
              <option value="10">10 years</option>
            </select>
          </div>
        </div>
      </div>

      {/* Guest Data Anonymization */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Guest Data Anonymization</h3>
        <div className="space-y-3">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Auto-anonymize guest orders after retention period</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Remove PII but keep aggregated analytics data</span>
          </label>
          <p className="text-xs text-gray-500">
            When enabled, guest personal data (name, phone, email) is replaced with anonymized identifiers 
            after the retention period while preserving order statistics for platform analytics.
          </p>
        </div>
      </div>

      {/* Audit Log Configuration */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Audit Log Configuration</h3>
        <div className="space-y-3">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Log all admin actions</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Log customer data access</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Log vendor account changes</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Require mandatory reason for sensitive actions</span>
          </label>
        </div>
      </div>
    </div>
  );
}

// Onboarding Settings Tab
function OnboardingSettings({ onChange }: { onChange: () => void }) {
  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="text-lg font-medium mb-4">Vendor Onboarding Rules</h2>
        <p className="text-sm text-gray-500 mb-2">Configure how new vendors join the platform</p>
      </div>

      {/* Approval Process */}
      <div>
        <h3 className="font-medium mb-4">Approval Process</h3>
        <div className="space-y-3">
          <label className="flex items-start gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
            <input type="radio" name="approval" defaultChecked className="mt-0.5" onChange={onChange} />
            <div>
              <div className="font-medium text-sm">Manual Approval (Recommended)</div>
              <div className="text-xs text-gray-500">Admin reviews and approves each vendor application</div>
            </div>
          </label>
          <label className="flex items-start gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
            <input type="radio" name="approval" className="mt-0.5" onChange={onChange} />
            <div>
              <div className="font-medium text-sm">Auto-Approval</div>
              <div className="text-xs text-gray-500">Vendors are auto-approved after completing onboarding steps</div>
            </div>
          </label>
        </div>
      </div>

      {/* Required Onboarding Steps */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Required Onboarding Steps</h3>
        <div className="space-y-2">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked disabled className="rounded border-gray-300" />
            <span className="text-sm">Business information (name, address, type)</span>
            <span className="text-xs text-gray-400 ml-auto">Required</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked disabled className="rounded border-gray-300" />
            <span className="text-sm">Contact details (email, phone)</span>
            <span className="text-xs text-gray-400 ml-auto">Required</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked disabled className="rounded border-gray-300" />
            <span className="text-sm">Subscription plan selection</span>
            <span className="text-xs text-gray-400 ml-auto">Required</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Business registration documents</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">VAT/Tax identification number</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Food safety certificates</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Bank account verification</span>
          </label>
        </div>
      </div>

      {/* Subscription Requirement */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Subscription Activation</h3>
        <div className="space-y-3">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Require active subscription before vendor can go live</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Allow trial period without payment</span>
          </label>
          <p className="text-xs text-gray-500">
            When enabled, vendors must have an active paid subscription (or active trial) before they can publish their menu and accept orders.
          </p>
        </div>
      </div>

      {/* Country Availability */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Country Availability</h3>
        <p className="text-xs text-gray-500 mb-3">Select which countries vendors can register from</p>
        <div className="grid grid-cols-2 gap-2">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Austria</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Germany</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Switzerland</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Netherlands</span>
          </label>
        </div>
      </div>

      {/* Notification Settings */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Onboarding Notifications</h3>
        <div className="space-y-2">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Notify admin team when new vendor registers</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Send welcome email to approved vendors</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Send rejection explanation for denied applications</span>
          </label>
        </div>
      </div>
    </div>
  );
}

// Notification Settings Tab
function NotificationSettings({ onChange }: { onChange: () => void }) {
  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="text-lg font-medium mb-4">Admin Notifications</h2>
        <p className="text-sm text-gray-500 mb-2">Configure internal admin alerts and notifications</p>
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700">
          <strong>Note:</strong> These are platform internal notifications for admin team only. 
          Vendor and customer notifications are configured separately.
        </div>
      </div>

      {/* Notification Channels */}
      <div>
        <h3 className="font-medium mb-4">Notification Channels</h3>
        <div className="space-y-3">
          <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
            <div className="flex items-center gap-3">
              <Bell className="w-5 h-5 text-gray-400" />
              <div>
                <div className="font-medium text-sm">Email Notifications</div>
                <div className="text-xs text-gray-500">admin@tavlo.com</div>
              </div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" defaultChecked className="sr-only peer" onChange={onChange} />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
          </div>
          <div className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
            <div className="flex items-center gap-3">
              <Zap className="w-5 h-5 text-gray-400" />
              <div>
                <div className="font-medium text-sm">Slack Notifications</div>
                <div className="text-xs text-gray-500">#tavlo-admin channel</div>
              </div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" className="sr-only peer" onChange={onChange} />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
            </label>
          </div>
        </div>
      </div>

      {/* System Alerts */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">System Alerts</h3>
        <div className="space-y-2">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Payment provider downtime</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Failed webhook deliveries</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Database backup failures</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">High error rate (&gt;5% of requests)</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Slow API response times (&gt;2s average)</span>
          </label>
        </div>
      </div>

      {/* Business Events */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">Business Events</h3>
        <div className="space-y-2">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">New vendor registration</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Vendor subscription payment failed</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Vendor subscription upgraded/downgraded</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Vendor account suspended</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Customer complaint filed</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Review flagged for moderation</span>
          </label>
        </div>
      </div>

      {/* GDPR Requests */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-4">GDPR & Compliance</h3>
        <div className="space-y-2">
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">User data export request</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Account deletion request</span>
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" defaultChecked className="rounded border-gray-300" onChange={onChange} />
            <span className="text-sm">Data breach detection</span>
          </label>
        </div>
      </div>
    </div>
  );
}

// Roles & Permissions Settings Tab
function RolesPermissionsSettings({ onChange }: { onChange: () => void }) {
  const [selectedRole, setSelectedRole] = useState<string | null>('super_admin');

  const roles = [
    {
      id: 'super_admin',
      name: 'Super Admin',
      description: 'Full platform access',
      userCount: 2,
      color: 'purple'
    },
    {
      id: 'finance_admin',
      name: 'Finance Admin',
      description: 'Billing, invoices, subscriptions',
      userCount: 3,
      color: 'blue'
    },
    {
      id: 'support_admin',
      name: 'Support Admin',
      description: 'Customer support, complaints',
      userCount: 5,
      color: 'green'
    },
    {
      id: 'compliance_admin',
      name: 'Compliance Admin',
      description: 'GDPR, audit logs, data protection',
      userCount: 2,
      color: 'orange'
    },
    {
      id: 'content_admin',
      name: 'Content Moderator',
      description: 'Review moderation only',
      userCount: 3,
      color: 'pink'
    },
  ];

  const permissions = {
    super_admin: {
      dashboard: { view: true, edit: true },
      vendors: { view: true, edit: true, suspend: true },
      customers: { view: true, edit: true, delete: true },
      invoices: { view: true, edit: true, send: true },
      reviews: { view: true, moderate: true, delete: true },
      settings: { view: true, edit: true },
      audit_logs: { view: true, export: true },
      admin_users: { view: true, edit: true, create: true },
    },
    finance_admin: {
      dashboard: { view: true, edit: false },
      vendors: { view: true, edit: false, suspend: false },
      customers: { view: false, edit: false, delete: false },
      invoices: { view: true, edit: true, send: true },
      reviews: { view: false, moderate: false, delete: false },
      settings: { view: true, edit: false },
      audit_logs: { view: true, export: true },
      admin_users: { view: false, edit: false, create: false },
    },
    support_admin: {
      dashboard: { view: true, edit: false },
      vendors: { view: true, edit: false, suspend: false },
      customers: { view: true, edit: false, delete: false },
      invoices: { view: true, edit: false, send: false },
      reviews: { view: true, moderate: false, delete: false },
      settings: { view: false, edit: false },
      audit_logs: { view: true, export: false },
      admin_users: { view: false, edit: false, create: false },
    },
    compliance_admin: {
      dashboard: { view: true, edit: false },
      vendors: { view: true, edit: false, suspend: true },
      customers: { view: true, edit: false, delete: true },
      invoices: { view: true, edit: false, send: false },
      reviews: { view: true, moderate: false, delete: false },
      settings: { view: true, edit: true },
      audit_logs: { view: true, export: true },
      admin_users: { view: false, edit: false, create: false },
    },
    content_admin: {
      dashboard: { view: false, edit: false },
      vendors: { view: false, edit: false, suspend: false },
      customers: { view: false, edit: false, delete: false },
      invoices: { view: false, edit: false, send: false },
      reviews: { view: true, moderate: true, delete: true },
      settings: { view: false, edit: false },
      audit_logs: { view: true, export: false },
      admin_users: { view: false, edit: false, create: false },
    },
  };

  return (
    <div className="p-6 space-y-6">
      <div>
        <h2 className="text-lg font-medium mb-4">Admin Roles & Permissions</h2>
        <p className="text-sm text-gray-500 mb-2">Define role-based access control for admin users</p>
        <div className="bg-purple-50 border border-purple-200 rounded-lg p-3 text-xs text-purple-700">
          <strong>Principle of Least Privilege:</strong> Each role sees only what is necessary for their function.
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Roles List */}
        <div className="lg:col-span-1">
          <h3 className="font-medium mb-3 text-sm">Admin Roles</h3>
          <div className="space-y-2">
            {roles.map((role) => (
              <button
                key={role.id}
                onClick={() => setSelectedRole(role.id)}
                className={`w-full text-left p-3 rounded-lg border transition-colors ${
                  selectedRole === role.id
                    ? 'border-purple-300 bg-purple-50'
                    : 'border-gray-200 hover:bg-gray-50'
                }`}
              >
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="font-medium text-sm">{role.name}</div>
                    <div className="text-xs text-gray-500 mt-0.5">{role.description}</div>
                  </div>
                  <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full ml-2">
                    {role.userCount}
                  </span>
                </div>
              </button>
            ))}
            <button className="w-full p-3 border-2 border-dashed border-gray-300 rounded-lg hover:border-purple-400 hover:bg-purple-50 text-sm text-gray-600 hover:text-purple-600 flex items-center justify-center gap-2">
              <Plus className="w-4 h-4" />
              Create Custom Role
            </button>
          </div>
        </div>

        {/* Permission Matrix */}
        <div className="lg:col-span-2">
          <h3 className="font-medium mb-3 text-sm">
            Permissions for {roles.find(r => r.id === selectedRole)?.name}
          </h3>
          <div className="border border-gray-200 rounded-lg overflow-hidden">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-600">Module</th>
                  <th className="px-4 py-3 text-center text-xs font-medium text-gray-600">View</th>
                  <th className="px-4 py-3 text-center text-xs font-medium text-gray-600">Edit</th>
                  <th className="px-4 py-3 text-center text-xs font-medium text-gray-600">Special</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                <tr>
                  <td className="px-4 py-3 text-sm">Dashboard</td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.dashboard.view ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.dashboard.edit ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center text-xs text-gray-400">-</td>
                </tr>
                <tr>
                  <td className="px-4 py-3 text-sm">Vendors</td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.vendors.view ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.vendors.edit ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.vendors.suspend ? (
                      <span className="text-xs text-green-600">Suspend</span>
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-3 text-sm">Customers</td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.customers.view ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.customers.edit ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.customers.delete ? (
                      <span className="text-xs text-green-600">GDPR Delete</span>
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-3 text-sm">Invoices & Billing</td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.invoices.view ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.invoices.edit ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.invoices.send ? (
                      <span className="text-xs text-green-600">Send</span>
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-3 text-sm">Reviews & Moderation</td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.reviews.view ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.reviews.moderate ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.reviews.delete ? (
                      <span className="text-xs text-green-600">Hide</span>
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-3 text-sm">System Settings</td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.settings.view ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.settings.edit ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center text-xs text-gray-400">-</td>
                </tr>
                <tr>
                  <td className="px-4 py-3 text-sm">Audit Logs</td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.audit_logs.view ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center text-xs text-gray-400">Read-Only</td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.audit_logs.export ? (
                      <span className="text-xs text-green-600">Export</span>
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                </tr>
                <tr>
                  <td className="px-4 py-3 text-sm">Admin User Management</td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.admin_users.view ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.admin_users.edit ? (
                      <CheckCircle className="w-4 h-4 text-green-600 mx-auto" />
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-center">
                    {permissions[selectedRole as keyof typeof permissions]?.admin_users.create ? (
                      <span className="text-xs text-green-600">Create</span>
                    ) : (
                      <XCircle className="w-4 h-4 text-gray-300 mx-auto" />
                    )}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div className="mt-4 flex gap-3">
            <button className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm flex items-center gap-2">
              <Save className="w-4 h-4" />
              Save Permissions
            </button>
            <button className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
              Reset to Default
            </button>
          </div>
        </div>
      </div>

      {/* Read-Only Indicators */}
      <div className="border-t border-gray-200 pt-6">
        <h3 className="font-medium mb-3 text-sm">Permission Notes</h3>
        <div className="space-y-2 text-sm text-gray-600">
          <div className="flex items-start gap-2">
            <Lock className="w-4 h-4 text-gray-400 mt-0.5" />
            <span className="text-xs">
              <strong>Audit Logs</strong> are always read-only. No role can edit or delete historical logs.
            </span>
          </div>
          <div className="flex items-start gap-2">
            <Shield className="w-4 h-4 text-gray-400 mt-0.5" />
            <span className="text-xs">
              <strong>GDPR actions</strong> (delete user data) require Compliance Admin or Super Admin role.
            </span>
          </div>
          <div className="flex items-start gap-2">
            <AlertTriangle className="w-4 h-4 text-gray-400 mt-0.5" />
            <span className="text-xs">
              <strong>Vendor suspension</strong> requires reason and is logged in audit trail.
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
