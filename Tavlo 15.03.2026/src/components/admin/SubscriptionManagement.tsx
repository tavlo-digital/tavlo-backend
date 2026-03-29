import { useState } from 'react';
import { 
  Plus, 
  Edit, 
  Trash2, 
  Check, 
  X,
  CreditCard,
  TrendingUp,
  AlertCircle,
  Calendar,
  DollarSign,
  Users,
  Star,
  Zap
} from 'lucide-react';
import { Input } from '../ui/input';
import { toast } from 'sonner@2.0.3';

interface SubscriptionPlan {
  id: string;
  name: string;
  price: number;
  billingCycle: 'monthly' | 'yearly';
  features: string[];
  limits: {
    maxMenuItems: number;
    maxTables: number;
    maxOrders: number;
  };
  isPopular: boolean;
  activeSubscriptions: number;
  mrr: number;
}

interface SubscriptionManagementProps {
  page?: string;
}

export function SubscriptionManagement({ page }: SubscriptionManagementProps) {
  const [activeTab, setActiveTab] = useState<'plans' | 'features' | 'active' | 'overdue'>('plans');
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingPlan, setEditingPlan] = useState<SubscriptionPlan | null>(null);

  const plans: SubscriptionPlan[] = [
    {
      id: 'plan_basic',
      name: 'Basic',
      price: 99,
      billingCycle: 'monthly',
      features: [
        'Up to 50 menu items',
        '10 tables',
        'Basic analytics',
        'QR ordering',
        'Email support'
      ],
      limits: {
        maxMenuItems: 50,
        maxTables: 10,
        maxOrders: 500
      },
      isPopular: false,
      activeSubscriptions: 260,
      mrr: 25740
    },
    {
      id: 'plan_standard',
      name: 'Standard',
      price: 199,
      billingCycle: 'monthly',
      features: [
        'Up to 150 menu items',
        '30 tables',
        'Advanced analytics',
        'QR ordering',
        'Multi-language support',
        'Priority support',
        'Loyalty program'
      ],
      limits: {
        maxMenuItems: 150,
        maxTables: 30,
        maxOrders: 2000
      },
      isPopular: true,
      activeSubscriptions: 687,
      mrr: 136713
    },
    {
      id: 'plan_premium',
      name: 'Premium',
      price: 299,
      billingCycle: 'monthly',
      features: [
        'Unlimited menu items',
        'Unlimited tables',
        'Full analytics suite',
        'QR ordering',
        'Multi-language support',
        'White-label options',
        'Dedicated account manager',
        'Custom integrations',
        'API access'
      ],
      limits: {
        maxMenuItems: -1,
        maxTables: -1,
        maxOrders: -1
      },
      isPopular: false,
      activeSubscriptions: 142,
      mrr: 42458
    }
  ];

  const activeSubscriptions = [
    {
      id: 'sub_001',
      vendorId: 'v_001',
      vendorName: 'Bella Italia',
      plan: 'Premium',
      status: 'active',
      startDate: '2024-01-15',
      nextBillingDate: '2024-07-15',
      mrr: 299,
      autoRenew: true
    },
    {
      id: 'sub_002',
      vendorId: 'v_004',
      vendorName: 'Burger Palace',
      plan: 'Standard',
      status: 'active',
      startDate: '2024-02-08',
      nextBillingDate: '2024-07-08',
      mrr: 199,
      autoRenew: true
    },
    {
      id: 'sub_003',
      vendorId: 'v_006',
      vendorName: 'Taco House',
      plan: 'Premium',
      status: 'active',
      startDate: '2023-11-20',
      nextBillingDate: '2024-06-20',
      mrr: 299,
      autoRenew: true
    }
  ];

  const overdueSubscriptions = [
    {
      id: 'sub_004',
      vendorId: 'v_003',
      vendorName: 'Cafe Noir',
      plan: 'Basic',
      status: 'overdue',
      lastBillingDate: '2024-05-15',
      daysOverdue: 31,
      amountDue: 118.80,
      autoRenew: false
    }
  ];

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl mb-1">Subscription Management</h1>
            <p className="text-sm text-gray-500">Manage plans, pricing, and vendor subscriptions</p>
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

      {/* Stats */}
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

      {/* Plans View */}
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
                <h3 className="text-xl font-semibold mb-2">{plan.name}</h3>
                <div className="flex items-baseline justify-center gap-1 mb-1">
                  <span className="text-3xl font-bold">€{plan.price}</span>
                  <span className="text-gray-500">/{plan.billingCycle === 'monthly' ? 'mo' : 'yr'}</span>
                </div>
                <div className="text-sm text-gray-500">{plan.activeSubscriptions} active subscriptions</div>
                <div className="text-sm text-green-600 font-medium">MRR: €{plan.mrr.toLocaleString()}</div>
              </div>

              <div className="space-y-3 mb-6">
                {plan.features.map((feature, index) => (
                  <div key={index} className="flex items-start gap-2 text-sm">
                    <Check className="w-4 h-4 text-green-600 shrink-0 mt-0.5" />
                    <span className="text-gray-700">{feature}</span>
                  </div>
                ))}
              </div>

              <div className="pt-4 border-t border-gray-200 space-y-2">
                <button 
                  onClick={() => setEditingPlan(plan)}
                  className="w-full flex items-center justify-center gap-2 py-2 border border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50 text-sm"
                >
                  <Edit className="w-4 h-4" />
                  Edit Plan
                </button>
                <button className="w-full flex items-center justify-center gap-2 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
                  View Subscribers
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Active Subscriptions View */}
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
                    <div className="flex items-center justify-end gap-2">
                      <button className="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50">
                        Upgrade
                      </button>
                      <button className="px-3 py-1.5 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50">
                        Cancel
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Overdue View */}
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
                    <div className="flex items-center justify-end gap-2">
                      <button className="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Send Reminder
                      </button>
                      <button className="px-3 py-1.5 text-xs border border-red-300 text-red-600 rounded-lg hover:bg-red-50">
                        Suspend
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Create/Edit Plan Modal */}
      {(showCreateModal || editingPlan) && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
            <h2 className="text-xl mb-6">
              {editingPlan ? 'Edit Plan' : 'Create New Plan'}
            </h2>

            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-2">Plan Name</label>
                  <Input 
                    type="text" 
                    defaultValue={editingPlan?.name} 
                    placeholder="e.g., Premium"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-2">Price (€)</label>
                  <Input 
                    type="number" 
                    defaultValue={editingPlan?.price} 
                    placeholder="299"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Billing Cycle</label>
                <select 
                  className="w-full px-4 py-2 border border-gray-200 rounded-lg"
                  defaultValue={editingPlan?.billingCycle || 'monthly'}
                >
                  <option value="monthly">Monthly</option>
                  <option value="yearly">Yearly</option>
                </select>
              </div>

              <div className="grid grid-cols-3 gap-4">
                <div>
                  <label className="block text-sm font-medium mb-2">Max Menu Items</label>
                  <Input 
                    type="number" 
                    defaultValue={editingPlan?.limits.maxMenuItems} 
                    placeholder="-1 for unlimited"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-2">Max Tables</label>
                  <Input 
                    type="number" 
                    defaultValue={editingPlan?.limits.maxTables} 
                    placeholder="-1 for unlimited"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-2">Max Orders/mo</label>
                  <Input 
                    type="number" 
                    defaultValue={editingPlan?.limits.maxOrders} 
                    placeholder="-1 for unlimited"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Features</label>
                <textarea
                  className="w-full p-3 border border-gray-200 rounded-lg text-sm resize-none"
                  rows={6}
                  placeholder="Enter one feature per line"
                  defaultValue={editingPlan?.features.join('\n')}
                />
              </div>

              <label className="flex items-center gap-2">
                <input 
                  type="checkbox" 
                  defaultChecked={editingPlan?.isPopular}
                  className="rounded"
                />
                <span className="text-sm">Mark as "Most Popular"</span>
              </label>
            </div>

            <div className="flex gap-3 mt-6">
              <button
                onClick={() => {
                  setShowCreateModal(false);
                  setEditingPlan(null);
                }}
                className="flex-1 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  toast.success(editingPlan ? 'Plan updated!' : 'Plan created!');
                  setShowCreateModal(false);
                  setEditingPlan(null);
                }}
                className="flex-1 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
              >
                {editingPlan ? 'Update Plan' : 'Create Plan'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}