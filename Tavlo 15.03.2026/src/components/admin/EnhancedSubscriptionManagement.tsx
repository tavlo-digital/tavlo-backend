import { useState } from 'react';
import { 
  Plus, 
  Edit, 
  Check, 
  X,
  DollarSign,
  Users,
  TrendingUp,
  AlertCircle,
  Star,
  Lock,
  Info,
  ExternalLink
} from 'lucide-react';
import { Input } from '../ui/input';
import { toast } from 'sonner@2.0.3';

// Global feature definitions (single source of truth)
interface TavloFeature {
  id: string;
  name: string;
  description: string;
  category: 'menu-content' | 'ordering-payments' | 'analytics' | 'customer-engagement' | 'support-admin' | 'integrations';
  dependencies?: string[]; // Feature IDs this feature requires
}

const TAVLO_FEATURES: TavloFeature[] = [
  // Menu & Content
  { id: 'menu-basic', name: 'Basic Menu Management', description: 'Create and manage menu items', category: 'menu-content' },
  { id: 'menu-categories', name: 'Menu Categories', description: 'Organize items by category', category: 'menu-content', dependencies: ['menu-basic'] },
  { id: 'menu-images', name: 'Menu Item Images', description: 'Upload photos for menu items', category: 'menu-content' },
  { id: 'menu-unlimited', name: 'Unlimited Menu Items', description: 'No limit on menu items', category: 'menu-content', dependencies: ['menu-basic'] },
  { id: 'menu-modifiers', name: 'Item Modifiers & Options', description: 'Size, extras, customizations', category: 'menu-content', dependencies: ['menu-basic'] },
  
  // Ordering & Payments
  { id: 'qr-ordering', name: 'QR Code Ordering', description: 'Customer QR-based ordering', category: 'ordering-payments' },
  { id: 'table-management', name: 'Table Management', description: 'Assign orders to tables', category: 'ordering-payments', dependencies: ['qr-ordering'] },
  { id: 'payment-card', name: 'Card Payments', description: 'Stripe card processing', category: 'ordering-payments' },
  { id: 'payment-cash', name: 'Cash Payment Option', description: 'Mark orders as cash paid', category: 'ordering-payments' },
  { id: 'split-bill', name: 'Split Bill', description: 'Divide order among customers', category: 'ordering-payments', dependencies: ['payment-card'] },
  { id: 'tips', name: 'Tipping', description: 'Customer tips at checkout', category: 'ordering-payments', dependencies: ['payment-card'] },
  
  // Analytics
  { id: 'analytics-basic', name: 'Basic Analytics', description: 'Sales overview & trends', category: 'analytics' },
  { id: 'analytics-advanced', name: 'Advanced Analytics', description: 'Detailed reports & insights', category: 'analytics', dependencies: ['analytics-basic'] },
  { id: 'analytics-export', name: 'Export Reports', description: 'Download CSV/Excel reports', category: 'analytics', dependencies: ['analytics-basic'] },
  { id: 'analytics-realtime', name: 'Real-Time Dashboard', description: 'Live order monitoring', category: 'analytics', dependencies: ['analytics-advanced'] },
  
  // Customer Engagement
  { id: 'multilanguage', name: 'Multi-Language Support', description: 'German, English, Arabic', category: 'customer-engagement' },
  { id: 'loyalty', name: 'Loyalty Program', description: 'Points & rewards system', category: 'customer-engagement' },
  { id: 'reviews', name: 'Customer Reviews', description: 'Order feedback & ratings', category: 'customer-engagement' },
  { id: 'marketing-email', name: 'Email Marketing', description: 'Send promotions to customers', category: 'customer-engagement' },
  
  // Support & Admin
  { id: 'support-email', name: 'Email Support', description: 'Support via email (48h)', category: 'support-admin' },
  { id: 'support-priority', name: 'Priority Support', description: 'Faster response (24h)', category: 'support-admin' },
  { id: 'support-dedicated', name: 'Dedicated Account Manager', description: 'Personal account rep', category: 'support-admin', dependencies: ['support-priority'] },
  { id: 'admin-users', name: 'Multi-User Access', description: 'Multiple vendor admin accounts', category: 'support-admin' },
  
  // Integrations
  { id: 'api-access', name: 'API Access', description: 'REST API for integrations', category: 'integrations' },
  { id: 'webhooks', name: 'Webhooks', description: 'Real-time event notifications', category: 'integrations', dependencies: ['api-access'] },
  { id: 'whitelabel', name: 'White-Label Options', description: 'Custom branding & domain', category: 'integrations' },
  { id: 'pos-integration', name: 'POS Integration', description: 'Connect to existing POS', category: 'integrations', dependencies: ['api-access'] }
];

interface SubscriptionPlan {
  id: string;
  name: string;
  monthlyPrice: number;
  yearlyPrice: number;
  featureIds: string[]; // References to TAVLO_FEATURES
  isPopular: boolean;
  activeSubscriptions: number;
  mrr: number;
  basePlan?: string; // 'basic' | 'standard' for hierarchy
  maxUsers: number; // Maximum staff accounts allowed
}

interface EnhancedSubscriptionManagementProps {
  page?: string;
  onNavigateToVendors?: (filters: { plan: string }) => void;
}

export function EnhancedSubscriptionManagement({ page, onNavigateToVendors }: EnhancedSubscriptionManagementProps) {
  const [activeTab, setActiveTab] = useState<'plans' | 'active' | 'overdue'>('plans');
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingPlan, setEditingPlan] = useState<SubscriptionPlan | null>(null);

  // Enhanced plans with feature-based structure
  const [plans, setPlans] = useState<SubscriptionPlan[]>([
    {
      id: 'plan_basic',
      name: 'Basic',
      monthlyPrice: 99,
      yearlyPrice: 950, // ~20% discount
      featureIds: [
        'menu-basic',
        'menu-categories',
        'menu-images',
        'qr-ordering',
        'table-management',
        'payment-card',
        'payment-cash',
        'analytics-basic',
        'support-email'
      ],
      isPopular: false,
      activeSubscriptions: 260,
      mrr: 25740,
      maxUsers: 5
    },
    {
      id: 'plan_standard',
      name: 'Standard',
      monthlyPrice: 199,
      yearlyPrice: 1910, // ~20% discount
      featureIds: [
        // Inherits all Basic features (enforced in UI)
        'menu-basic',
        'menu-categories',
        'menu-images',
        'qr-ordering',
        'table-management',
        'payment-card',
        'payment-cash',
        'analytics-basic',
        'support-email',
        // Additional Standard features
        'menu-modifiers',
        'tips',
        'analytics-advanced',
        'analytics-export',
        'multilanguage',
        'loyalty',
        'reviews',
        'support-priority',
        'admin-users'
      ],
      isPopular: true,
      activeSubscriptions: 687,
      mrr: 136713,
      basePlan: 'basic',
      maxUsers: 10
    },
    {
      id: 'plan_premium',
      name: 'Premium',
      monthlyPrice: 299,
      yearlyPrice: 2870, // ~20% discount
      featureIds: [
        // Inherits all Standard features (enforced in UI)
        'menu-basic',
        'menu-categories',
        'menu-images',
        'qr-ordering',
        'table-management',
        'payment-card',
        'payment-cash',
        'analytics-basic',
        'support-email',
        'menu-modifiers',
        'tips',
        'analytics-advanced',
        'analytics-export',
        'multilanguage',
        'loyalty',
        'reviews',
        'support-priority',
        'admin-users',
        // Additional Premium features
        'menu-unlimited',
        'split-bill',
        'analytics-realtime',
        'marketing-email',
        'support-dedicated',
        'api-access',
        'webhooks',
        'whitelabel',
        'pos-integration'
      ],
      isPopular: false,
      activeSubscriptions: 142,
      mrr: 42458,
      basePlan: 'standard',
      maxUsers: 15
    }
  ]);

  const activeSubscriptions = [
    {
      id: 'sub_001',
      vendorId: 'V-1024',
      vendorName: 'Bella Italia',
      plan: 'Premium',
      status: 'active',
      startDate: '2024-01-15',
      nextBillingDate: '2025-07-15',
      mrr: 299,
      autoRenew: true
    },
    {
      id: 'sub_002',
      vendorId: 'V-2048',
      vendorName: 'Burger Palace',
      plan: 'Standard',
      status: 'active',
      startDate: '2024-02-08',
      nextBillingDate: '2025-07-08',
      mrr: 199,
      autoRenew: true
    },
    {
      id: 'sub_003',
      vendorId: 'V-3072',
      vendorName: 'Taco House',
      plan: 'Premium',
      status: 'active',
      startDate: '2023-11-20',
      nextBillingDate: '2025-06-20',
      mrr: 299,
      autoRenew: true
    }
  ];

  const overdueSubscriptions = [
    {
      id: 'sub_004',
      vendorId: 'V-4096',
      vendorName: 'Cafe Noir',
      plan: 'Basic',
      status: 'overdue',
      lastBillingDate: '2024-05-15',
      daysOverdue: 31,
      amountDue: 118.80,
      autoRenew: false
    }
  ];

  const handleViewSubscribers = (planName: string) => {
    // Navigate to Vendors page with plan filter applied
    console.log('NAVIGATION CONTRACT: Subscriptions → Vendors', {
      destination: 'Admin → Vendors',
      filter: { plan: planName },
      source: 'Subscription Management'
    });

    if (onNavigateToVendors) {
      onNavigateToVendors({ plan: planName });
    }

    toast.info('Navigating to Vendors', {
      description: `Filter applied: ${planName} subscribers`
    });

    // In production: navigate('/admin/vendors', { state: { filters: { plan: planName } } })
  };

  const handleEditPlan = (plan: SubscriptionPlan) => {
    setEditingPlan(plan);
  };

  const handleSavePlan = (updatedPlan: SubscriptionPlan) => {
    // Audit logging
    console.log('AUDIT LOG: Plan edited', {
      planId: updatedPlan.id,
      admin: 'Current Admin User',
      timestamp: new Date().toISOString(),
      changes: {
        monthlyPrice: updatedPlan.monthlyPrice,
        yearlyPrice: updatedPlan.yearlyPrice,
        features: updatedPlan.featureIds
      }
    });

    // Update plan
    setPlans(plans.map(p => p.id === updatedPlan.id ? updatedPlan : p));
    setEditingPlan(null);

    toast.success('Plan updated', {
      description: 'All changes logged to audit trail'
    });
  };

  const handleCreatePlan = (newPlan: SubscriptionPlan) => {
    // Audit logging
    console.log('AUDIT LOG: Plan created', {
      planId: newPlan.id,
      admin: 'Current Admin User',
      timestamp: new Date().toISOString(),
      details: {
        monthlyPrice: newPlan.monthlyPrice,
        yearlyPrice: newPlan.yearlyPrice,
        features: newPlan.featureIds
      }
    });

    // Add new plan
    setPlans([...plans, newPlan]);
    setShowCreateModal(false);

    toast.success('Plan created', {
      description: 'New plan added to the system'
    });
  };

  const getFeaturesByPlan = (planName: string) => {
    const plan = plans.find(p => p.name.toLowerCase() === planName.toLowerCase());
    if (!plan) return [];
    return TAVLO_FEATURES.filter(f => plan.featureIds.includes(f.id));
  };

  const getBasePlanFeatures = (basePlan?: string): string[] => {
    if (!basePlan) return [];
    
    const basePlanObj = plans.find(p => p.name.toLowerCase() === basePlan);
    return basePlanObj?.featureIds || [];
  };

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl mb-1">Subscription Management</h1>
            <p className="text-sm text-gray-500">Feature-based plans with hierarchical inheritance</p>
          </div>
          {activeTab === 'plans' && (
            <button 
              onClick={() => setShowCreateModal(true)}
              className="flex items-center gap-2 px-6 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
            >
              <Plus className="w-4 h-4" />
              Create Plan
            </button>
          )}
        </div>
      </div>

      {/* Stats (Keep existing KPI cards) */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Total MRR</span>
            <DollarSign className="w-4 h-4 text-green-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">€204,911</div>
          <div className="text-xs text-green-600">+15.2% vs last month</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Active Subs</span>
            <Users className="w-4 h-4 text-blue-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">1,089</div>
          <div className="text-xs text-gray-500">Across 3 plans</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Churn Rate</span>
            <TrendingUp className="w-4 h-4 text-orange-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">2.3%</div>
          <div className="text-xs text-green-600">-0.5% improvement</div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Overdue</span>
            <AlertCircle className="w-4 h-4 text-red-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">€2,847</div>
          <div className="text-xs text-red-600">4 subscriptions</div>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 mb-6">
        <div className="flex gap-6">
          {[
            { id: 'plans', label: 'Plans', count: plans.length },
            { id: 'active', label: 'Active', count: activeSubscriptions.length },
            { id: 'overdue', label: 'Overdue', count: overdueSubscriptions.length }
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`flex items-center gap-2 pb-3 border-b-2 transition-colors ${
                activeTab === tab.id
                  ? 'border-purple-600 text-purple-600'
                  : 'border-transparent text-gray-600 hover:text-gray-900'
              }`}
            >
              <span className="text-sm font-medium">{tab.label}</span>
              <span className="px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-xs">
                {tab.count}
              </span>
            </button>
          ))}
        </div>
      </div>

      {/* Enhanced Plans View */}
      {activeTab === 'plans' && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {plans.map((plan) => (
            <div 
              key={plan.id}
              className={`bg-white rounded-xl border-2 p-6 relative ${
                plan.isPopular ? 'border-purple-500' : 'border-gray-200'
              }`}
            >
              {plan.isPopular && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                  <span className="px-3 py-1 bg-purple-500 text-white text-xs font-medium rounded-full flex items-center gap-1">
                    <Star className="w-3 h-3 fill-white" />
                    Most Popular
                  </span>
                </div>
              )}

              <div className="text-center mb-6">
                <h3 className="text-xl font-semibold mb-3">{plan.name}</h3>
                
                {/* Monthly Pricing */}
                <div className="mb-2">
                  <div className="flex items-baseline justify-center gap-1">
                    <span className="text-3xl font-bold">€{plan.monthlyPrice}</span>
                    <span className="text-gray-500">/month</span>
                  </div>
                </div>

                {/* Yearly Pricing */}
                <div className="mb-3">
                  <div className="flex items-baseline justify-center gap-1">
                    <span className="text-xl font-semibold text-green-700">€{plan.yearlyPrice}</span>
                    <span className="text-sm text-gray-500">/year</span>
                  </div>
                  <div className="text-xs text-green-600">
                    Save €{(plan.monthlyPrice * 12 - plan.yearlyPrice).toFixed(0)}/year
                  </div>
                </div>

                <div className="text-sm text-gray-500 mb-1">
                  {plan.activeSubscriptions} active subscriptions
                </div>
                <div className="text-sm text-green-600 font-medium">
                  MRR: €{plan.mrr.toLocaleString()}
                </div>
              </div>

              {/* Feature Summary (Clickable) */}
              <div className="mb-6">
                <button
                  onClick={() => handleEditPlan(plan)}
                  className="w-full p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors text-left"
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="text-sm font-medium text-blue-900">
                        Includes {plan.featureIds.length} features
                      </div>
                      <div className="text-xs text-blue-700 mt-0.5">
                        Click to view and edit features
                      </div>
                    </div>
                    <ExternalLink className="w-4 h-4 text-blue-600" />
                  </div>
                </button>

                {/* Quick Feature Preview */}
                <div className="mt-3 space-y-1.5">
                  {getFeaturesByPlan(plan.name).slice(0, 3).map((feature) => (
                    <div key={feature.id} className="flex items-start gap-2 text-xs">
                      <Check className="w-3 h-3 text-green-600 shrink-0 mt-0.5" />
                      <span className="text-gray-700">{feature.name}</span>
                    </div>
                  ))}
                  {plan.featureIds.length > 3 && (
                    <div className="text-xs text-gray-500 pl-5">
                      + {plan.featureIds.length - 3} more features
                    </div>
                  )}
                </div>
              </div>

              <div className="pt-4 border-t border-gray-200 space-y-2">
                <button 
                  onClick={() => handleEditPlan(plan)}
                  className="w-full flex items-center justify-center gap-2 py-2 border border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50 text-sm"
                >
                  <Edit className="w-4 h-4" />
                  Edit Plan
                </button>
                <button 
                  onClick={() => handleViewSubscribers(plan.name)}
                  className="w-full flex items-center justify-center gap-2 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm"
                  title="Navigate to Vendors page with plan filter"
                >
                  View Subscribers
                  <ExternalLink className="w-3 h-3" />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Active Subscriptions View (Unchanged) */}
      {activeTab === 'active' && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Vendor
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Plan
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Start Date
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Next Billing
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  MRR
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Auto-Renew
                </th>
                <th className="px-6 py-3 text-right text-xs text-gray-600 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {activeSubscriptions.map((sub) => (
                <tr key={sub.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4">
                    <div>
                      <div className="font-medium">{sub.vendorName}</div>
                      <div className="text-xs text-gray-500">{sub.vendorId}</div>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                      sub.plan === 'Premium' ? 'bg-purple-100 text-purple-700' :
                      sub.plan === 'Standard' ? 'bg-blue-100 text-blue-700' :
                      'bg-gray-100 text-gray-700'
                    }`}>
                      {sub.plan}
                    </span>
                  </td>
                  <td className="px-6 py-4">
                    <span className="px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                      Active
                    </span>
                  </td>
                  <td className="px-6 py-4 text-sm">
                    {new Date(sub.startDate).toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4 text-sm">
                    {new Date(sub.nextBillingDate).toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4 text-sm font-medium">
                    €{sub.mrr}
                  </td>
                  <td className="px-6 py-4">
                    {sub.autoRenew ? (
                      <span className="text-green-600 text-sm">Yes</span>
                    ) : (
                      <span className="text-gray-500 text-sm">No</span>
                    )}
                  </td>
                  <td className="px-6 py-4 text-right">
                    <button className="text-purple-600 hover:text-purple-700 text-sm font-medium">
                      View in Vendor Detail
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Overdue View (Unchanged) */}
      {activeTab === 'overdue' && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Vendor
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Plan
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Last Billing
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Days Overdue
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Amount Due
                </th>
                <th className="px-6 py-3 text-right text-xs text-gray-600 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {overdueSubscriptions.map((sub) => (
                <tr key={sub.id} className="hover:bg-gray-50 bg-red-50">
                  <td className="px-6 py-4">
                    <div>
                      <div className="font-medium">{sub.vendorName}</div>
                      <div className="text-xs text-gray-500">{sub.vendorId}</div>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <span className="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">
                      {sub.plan}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-sm">
                    {new Date(sub.lastBillingDate).toLocaleDateString()}
                  </td>
                  <td className="px-6 py-4">
                    <span className="text-sm font-medium text-red-600">
                      {sub.daysOverdue} days
                    </span>
                  </td>
                  <td className="px-6 py-4 text-sm font-medium">
                    €{sub.amountDue.toFixed(2)}
                  </td>
                  <td className="px-6 py-4 text-right">
                    <button className="text-purple-600 hover:text-purple-700 text-sm font-medium">
                      View in Vendor Detail
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Enhanced Edit Plan Modal with Feature Selector */}
      {editingPlan && (
        <EditPlanModal
          plan={editingPlan}
          allPlans={plans}
          onClose={() => setEditingPlan(null)}
          onSave={handleSavePlan}
        />
      )}

      {/* Create Plan Modal */}
      {showCreateModal && (
        <CreatePlanModal
          allPlans={plans}
          onClose={() => setShowCreateModal(false)}
          onSave={handleCreatePlan}
        />
      )}
    </div>
  );
}

// Feature-Based Edit Plan Modal Component
interface EditPlanModalProps {
  plan: SubscriptionPlan;
  allPlans: SubscriptionPlan[];
  onClose: () => void;
  onSave: (plan: SubscriptionPlan) => void;
}

function EditPlanModal({ plan, allPlans, onClose, onSave }: EditPlanModalProps) {
  const [formData, setFormData] = useState({
    name: plan.name,
    monthlyPrice: plan.monthlyPrice,
    yearlyPrice: plan.yearlyPrice,
    maxUsers: plan.maxUsers || 5,
    featureIds: [...plan.featureIds],
    isPopular: plan.isPopular
  });

  // Get base plan features (locked/inherited)
  const getBasePlanFeatures = (): string[] => {
    if (!plan.basePlan) return [];
    
    const basePlanObj = allPlans.find(p => p.name.toLowerCase() === plan.basePlan);
    return basePlanObj?.featureIds || [];
  };

  const basePlanFeatures = getBasePlanFeatures();

  const isFeatureInherited = (featureId: string): boolean => {
    return basePlanFeatures.includes(featureId);
  };

  const handleFeatureToggle = (featureId: string) => {
    // Cannot remove inherited features
    if (isFeatureInherited(featureId)) {
      toast.error('Cannot remove inherited feature', {
        description: `Included from ${plan.basePlan} plan`
      });
      return;
    }

    // Check dependencies
    const feature = TAVLO_FEATURES.find(f => f.id === featureId);
    if (feature?.dependencies) {
      const missingDeps = feature.dependencies.filter(dep => !formData.featureIds.includes(dep));
      if (missingDeps.length > 0 && !formData.featureIds.includes(featureId)) {
        // Auto-select dependencies
        const depNames = missingDeps.map(dep => TAVLO_FEATURES.find(f => f.id === dep)?.name).join(', ');
        toast.info('Auto-selecting dependencies', {
          description: `Requires: ${depNames}`
        });
        setFormData({
          ...formData,
          featureIds: [...formData.featureIds, ...missingDeps, featureId]
        });
        return;
      }
    }

    if (formData.featureIds.includes(featureId)) {
      setFormData({
        ...formData,
        featureIds: formData.featureIds.filter(id => id !== featureId)
      });
    } else {
      setFormData({
        ...formData,
        featureIds: [...formData.featureIds, featureId]
      });
    }
  };

  const handleSave = () => {
    // Validation
    if (!formData.monthlyPrice || !formData.yearlyPrice) {
      toast.error('Please enter both monthly and yearly prices');
      return;
    }

    if (formData.featureIds.length === 0) {
      toast.error('Please select at least one feature');
      return;
    }

    const updatedPlan: SubscriptionPlan = {
      ...plan,
      name: formData.name,
      monthlyPrice: formData.monthlyPrice,
      yearlyPrice: formData.yearlyPrice,
      featureIds: formData.featureIds,
      isPopular: formData.isPopular,
      maxUsers: formData.maxUsers
    };

    onSave(updatedPlan);
  };

  // Group features by category
  const featuresByCategory = TAVLO_FEATURES.reduce((acc, feature) => {
    if (!acc[feature.category]) {
      acc[feature.category] = [];
    }
    acc[feature.category].push(feature);
    return acc;
  }, {} as Record<string, TavloFeature[]>);

  const categoryLabels: Record<string, string> = {
    'menu-content': 'Menu & Content',
    'ordering-payments': 'Ordering & Payments',
    'analytics': 'Analytics',
    'customer-engagement': 'Customer Engagement',
    'support-admin': 'Support & Admin',
    'integrations': 'Integrations'
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-4xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl font-semibold">Edit Plan: {plan.name}</h2>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Hierarchy Notice */}
        {plan.basePlan && (
          <div className="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
            <Info className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-medium text-blue-900">Hierarchical Plan</p>
              <p className="text-sm text-blue-800 mt-1">
                This plan inherits all features from <strong>{plan.basePlan}</strong>. 
                Inherited features are locked and cannot be removed.
              </p>
            </div>
          </div>
        )}

        <div className="space-y-6">
          {/* Pricing Section */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
              Pricing
            </h3>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Monthly Price (€)</label>
                <Input 
                  type="number" 
                  value={formData.monthlyPrice}
                  onChange={(e) => setFormData({ ...formData, monthlyPrice: parseFloat(e.target.value) })}
                  placeholder="99"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Yearly Price (€)</label>
                <Input 
                  type="number" 
                  value={formData.yearlyPrice}
                  onChange={(e) => setFormData({ ...formData, yearlyPrice: parseFloat(e.target.value) })}
                  placeholder="950"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Yearly billing typically offers a discount (~20%)
                </p>
              </div>
            </div>
          </div>

          {/* Limits Section */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
              Limits
            </h3>
            <div>
              <label className="block text-sm font-medium mb-2">Maximum users</label>
              <Input 
                type="number" 
                min="1"
                value={formData.maxUsers}
                onChange={(e) => setFormData({ ...formData, maxUsers: parseInt(e.target.value) || 1 })}
                placeholder="5"
              />
              <p className="text-xs text-gray-500 mt-1">
                Maximum number of staff accounts allowed for this plan.
              </p>
            </div>
          </div>

          {/* Feature Selector */}
          <div>
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">
                Features
              </h3>
              <span className="text-sm text-gray-600">
                {formData.featureIds.length} features selected
              </span>
            </div>

            <div className="space-y-4">
              {Object.entries(featuresByCategory).map(([category, features]) => (
                <div key={category} className="border border-gray-200 rounded-lg p-4">
                  <h4 className="text-sm font-semibold text-gray-900 mb-3">
                    {categoryLabels[category]}
                  </h4>
                  <div className="space-y-2">
                    {features.map((feature) => {
                      const isInherited = isFeatureInherited(feature.id);
                      const isSelected = formData.featureIds.includes(feature.id);

                      return (
                        <div
                          key={feature.id}
                          className={`flex items-start gap-3 p-2 rounded-lg ${
                            isInherited ? 'bg-gray-50' : 'hover:bg-gray-50'
                          }`}
                        >
                          <input
                            type="checkbox"
                            checked={isSelected}
                            disabled={isInherited}
                            onChange={() => handleFeatureToggle(feature.id)}
                            className="mt-1 rounded"
                          />
                          <div className="flex-1">
                            <div className="flex items-center gap-2">
                              <span className={`text-sm font-medium ${
                                isInherited ? 'text-gray-600' : 'text-gray-900'
                              }`}>
                                {feature.name}
                              </span>
                              {isInherited && (
                                <span className="flex items-center gap-1 px-2 py-0.5 bg-gray-200 text-gray-700 rounded text-xs">
                                  <Lock className="w-3 h-3" />
                                  Included from {plan.basePlan}
                                </span>
                              )}
                            </div>
                            <p className="text-xs text-gray-500 mt-0.5">
                              {feature.description}
                            </p>
                            {feature.dependencies && feature.dependencies.length > 0 && (
                              <p className="text-xs text-blue-600 mt-1">
                                Requires: {feature.dependencies.map(dep => 
                                  TAVLO_FEATURES.find(f => f.id === dep)?.name
                                ).join(', ')}
                              </p>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Most Popular Toggle */}
          <label className="flex items-center gap-2">
            <input 
              type="checkbox" 
              checked={formData.isPopular}
              onChange={(e) => setFormData({ ...formData, isPopular: e.target.checked })}
              className="rounded"
            />
            <span className="text-sm font-medium">Mark as "Most Popular"</span>
          </label>
        </div>

        {/* Actions */}
        <div className="flex gap-3 mt-6 pt-6 border-t border-gray-200">
          <button
            onClick={onClose}
            className="flex-1 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium"
          >
            Cancel
          </button>
          <button
            onClick={handleSave}
            className="flex-1 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium"
          >
            Update Plan
          </button>
        </div>
      </div>
    </div>
  );
}

// Create Plan Modal Component
interface CreatePlanModalProps {
  allPlans: SubscriptionPlan[];
  onClose: () => void;
  onSave: (plan: SubscriptionPlan) => void;
}

function CreatePlanModal({ allPlans, onClose, onSave }: CreatePlanModalProps) {
  const [formData, setFormData] = useState({
    name: '',
    monthlyPrice: 0,
    yearlyPrice: 0,
    maxUsers: 5,
    featureIds: [] as string[],
    isPopular: false,
    basePlan: '' as 'basic' | 'standard' | ''
  });

  // Get base plan features (locked/inherited)
  const getBasePlanFeatures = (): string[] => {
    if (!formData.basePlan) return [];
    
    const basePlanObj = allPlans.find(p => p.name.toLowerCase() === formData.basePlan);
    return basePlanObj?.featureIds || [];
  };

  const basePlanFeatures = getBasePlanFeatures();

  const isFeatureInherited = (featureId: string): boolean => {
    return basePlanFeatures.includes(featureId);
  };

  const handleFeatureToggle = (featureId: string) => {
    // Cannot remove inherited features
    if (isFeatureInherited(featureId)) {
      toast.error('Cannot remove inherited feature', {
        description: `Included from ${formData.basePlan} plan`
      });
      return;
    }

    // Check dependencies
    const feature = TAVLO_FEATURES.find(f => f.id === featureId);
    if (feature?.dependencies) {
      const missingDeps = feature.dependencies.filter(dep => !formData.featureIds.includes(dep));
      if (missingDeps.length > 0 && !formData.featureIds.includes(featureId)) {
        // Auto-select dependencies
        const depNames = missingDeps.map(dep => TAVLO_FEATURES.find(f => f.id === dep)?.name).join(', ');
        toast.info('Auto-selecting dependencies', {
          description: `Requires: ${depNames}`
        });
        setFormData({
          ...formData,
          featureIds: [...formData.featureIds, ...missingDeps, featureId]
        });
        return;
      }
    }

    if (formData.featureIds.includes(featureId)) {
      setFormData({
        ...formData,
        featureIds: formData.featureIds.filter(id => id !== featureId)
      });
    } else {
      setFormData({
        ...formData,
        featureIds: [...formData.featureIds, featureId]
      });
    }
  };

  const handleSave = () => {
    // Validation
    if (!formData.name) {
      toast.error('Please enter a plan name');
      return;
    }

    if (!formData.monthlyPrice || !formData.yearlyPrice) {
      toast.error('Please enter both monthly and yearly prices');
      return;
    }

    if (formData.featureIds.length === 0) {
      toast.error('Please select at least one feature');
      return;
    }

    const newPlan: SubscriptionPlan = {
      id: `plan_${formData.name.toLowerCase().replace(/\s+/g, '_')}`,
      name: formData.name,
      monthlyPrice: formData.monthlyPrice,
      yearlyPrice: formData.yearlyPrice,
      featureIds: formData.featureIds,
      isPopular: formData.isPopular,
      maxUsers: formData.maxUsers,
      basePlan: formData.basePlan,
      activeSubscriptions: 0,
      mrr: 0
    };

    onSave(newPlan);
  };

  // Group features by category
  const featuresByCategory = TAVLO_FEATURES.reduce((acc, feature) => {
    if (!acc[feature.category]) {
      acc[feature.category] = [];
    }
    acc[feature.category].push(feature);
    return acc;
  }, {} as Record<string, TavloFeature[]>);

  const categoryLabels: Record<string, string> = {
    'menu-content': 'Menu & Content',
    'ordering-payments': 'Ordering & Payments',
    'analytics': 'Analytics',
    'customer-engagement': 'Customer Engagement',
    'support-admin': 'Support & Admin',
    'integrations': 'Integrations'
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-4xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-xl font-semibold">Create New Plan</h2>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Hierarchy Notice */}
        {formData.basePlan && (
          <div className="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
            <Info className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-medium text-blue-900">Hierarchical Plan</p>
              <p className="text-sm text-blue-800 mt-1">
                This plan inherits all features from <strong>{formData.basePlan}</strong>. 
                Inherited features are locked and cannot be removed.
              </p>
            </div>
          </div>
        )}

        <div className="space-y-6">
          {/* Plan Name */}
          <div>
            <label className="block text-sm font-medium mb-2">Plan Name</label>
            <Input 
              type="text" 
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="Premium"
            />
          </div>

          {/* Pricing Section */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
              Pricing
            </h3>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-2">Monthly Price (€)</label>
                <Input 
                  type="number" 
                  value={formData.monthlyPrice}
                  onChange={(e) => setFormData({ ...formData, monthlyPrice: parseFloat(e.target.value) })}
                  placeholder="99"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-2">Yearly Price (€)</label>
                <Input 
                  type="number" 
                  value={formData.yearlyPrice}
                  onChange={(e) => setFormData({ ...formData, yearlyPrice: parseFloat(e.target.value) })}
                  placeholder="950"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Yearly billing typically offers a discount (~20%)
                </p>
              </div>
            </div>
          </div>

          {/* Limits Section */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
              Limits
            </h3>
            <div>
              <label className="block text-sm font-medium mb-2">Maximum users</label>
              <Input 
                type="number" 
                min="1"
                value={formData.maxUsers}
                onChange={(e) => setFormData({ ...formData, maxUsers: parseInt(e.target.value) || 1 })}
                placeholder="5"
              />
              <p className="text-xs text-gray-500 mt-1">
                Maximum number of staff accounts allowed for this plan.
              </p>
            </div>
          </div>

          {/* Base Plan Selection */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">
              Base Plan (Optional)
            </h3>
            <div>
              <label className="block text-sm font-medium mb-2">Inherit from</label>
              <select
                value={formData.basePlan}
                onChange={(e) => setFormData({ ...formData, basePlan: e.target.value as 'basic' | 'standard' | '' })}
                className="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              >
                <option value="">None (Start from scratch)</option>
                <option value="basic">Basic</option>
                <option value="standard">Standard</option>
              </select>
              <p className="text-xs text-gray-500 mt-1">
                Select a base plan to inherit features from.
              </p>
            </div>
          </div>

          {/* Feature Selector */}
          <div>
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wide">
                Features
              </h3>
              <span className="text-sm text-gray-600">
                {formData.featureIds.length} features selected
              </span>
            </div>

            <div className="space-y-4">
              {Object.entries(featuresByCategory).map(([category, features]) => (
                <div key={category} className="border border-gray-200 rounded-lg p-4">
                  <h4 className="text-sm font-semibold text-gray-900 mb-3">
                    {categoryLabels[category]}
                  </h4>
                  <div className="space-y-2">
                    {features.map((feature) => {
                      const isInherited = isFeatureInherited(feature.id);
                      const isSelected = formData.featureIds.includes(feature.id);

                      return (
                        <div
                          key={feature.id}
                          className={`flex items-start gap-3 p-2 rounded-lg ${
                            isInherited ? 'bg-gray-50' : 'hover:bg-gray-50'
                          }`}
                        >
                          <input
                            type="checkbox"
                            checked={isSelected}
                            disabled={isInherited}
                            onChange={() => handleFeatureToggle(feature.id)}
                            className="mt-1 rounded"
                          />
                          <div className="flex-1">
                            <div className="flex items-center gap-2">
                              <span className={`text-sm font-medium ${
                                isInherited ? 'text-gray-600' : 'text-gray-900'
                              }`}>
                                {feature.name}
                              </span>
                              {isInherited && (
                                <span className="flex items-center gap-1 px-2 py-0.5 bg-gray-200 text-gray-700 rounded text-xs">
                                  <Lock className="w-3 h-3" />
                                  Included from {formData.basePlan}
                                </span>
                              )}
                            </div>
                            <p className="text-xs text-gray-500 mt-0.5">
                              {feature.description}
                            </p>
                            {feature.dependencies && feature.dependencies.length > 0 && (
                              <p className="text-xs text-blue-600 mt-1">
                                Requires: {feature.dependencies.map(dep => 
                                  TAVLO_FEATURES.find(f => f.id === dep)?.name
                                ).join(', ')}
                              </p>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Most Popular Toggle */}
          <label className="flex items-center gap-2">
            <input 
              type="checkbox" 
              checked={formData.isPopular}
              onChange={(e) => setFormData({ ...formData, isPopular: e.target.checked })}
              className="rounded"
            />
            <span className="text-sm font-medium">Mark as "Most Popular"</span>
          </label>
        </div>

        {/* Actions */}
        <div className="flex gap-3 mt-6 pt-6 border-t border-gray-200">
          <button
            onClick={onClose}
            className="flex-1 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium"
          >
            Cancel
          </button>
          <button
            onClick={handleSave}
            className="flex-1 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium"
          >
            Create Plan
          </button>
        </div>
      </div>
    </div>
  );
}