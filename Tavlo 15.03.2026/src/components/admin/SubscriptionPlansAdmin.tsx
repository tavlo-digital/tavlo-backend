import { useState } from 'react';
import { Check, X, Edit, Save, Plus } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { toast } from 'sonner@2.0.3';

interface SubscriptionPlan {
  id: string;
  name: string;
  price: number;
  interval: 'month' | 'year';
  stripePriceId?: string;
  features: {
    loyalty: boolean;
    promotions: boolean;
    multiLanguage: boolean;
    advancedAnalytics: boolean;
    customAppearance: boolean;
    multiLocation: boolean;
    maxMenuItems: number;
    maxTables: number;
  };
  description: string;
  popular?: boolean;
}

export function SubscriptionPlansAdmin() {
  const [plans, setPlans] = useState<SubscriptionPlan[]>([
    {
      id: 'basic',
      name: 'Basic',
      price: 29,
      interval: 'month',
      stripePriceId: 'price_basic_monthly',
      description: 'Perfect for small cafes and pop-ups',
      features: {
        loyalty: false,
        promotions: false,
        multiLanguage: false,
        advancedAnalytics: false,
        customAppearance: false,
        multiLocation: false,
        maxMenuItems: 50,
        maxTables: 10,
      }
    },
    {
      id: 'professional',
      name: 'Professional',
      price: 79,
      interval: 'month',
      stripePriceId: 'price_professional_monthly',
      description: 'For established restaurants',
      popular: true,
      features: {
        loyalty: true,
        promotions: true,
        multiLanguage: true,
        advancedAnalytics: false,
        customAppearance: true,
        multiLocation: false,
        maxMenuItems: 200,
        maxTables: 30,
      }
    },
    {
      id: 'enterprise',
      name: 'Enterprise',
      price: 199,
      interval: 'month',
      stripePriceId: 'price_enterprise_monthly',
      description: 'For restaurant groups and chains',
      features: {
        loyalty: true,
        promotions: true,
        multiLanguage: true,
        advancedAnalytics: true,
        customAppearance: true,
        multiLocation: true,
        maxMenuItems: 999999,
        maxTables: 999999,
      }
    }
  ]);

  const [editingPlan, setEditingPlan] = useState<string | null>(null);

  const featureLabels = {
    loyalty: 'Loyalty Program',
    promotions: 'Promotions & Discounts',
    multiLanguage: 'Multi-Language Support',
    advancedAnalytics: 'Advanced Analytics',
    customAppearance: 'Custom Appearance',
    multiLocation: 'Multi-Location Management',
    maxMenuItems: 'Max Menu Items',
    maxTables: 'Max Tables'
  };

  const toggleFeature = (planId: string, feature: keyof SubscriptionPlan['features']) => {
    setPlans(plans.map(plan => {
      if (plan.id === planId) {
        return {
          ...plan,
          features: {
            ...plan.features,
            [feature]: typeof plan.features[feature] === 'boolean' 
              ? !plan.features[feature] 
              : plan.features[feature]
          }
        };
      }
      return plan;
    }));
  };

  const updateLimit = (planId: string, field: 'maxMenuItems' | 'maxTables', value: number) => {
    setPlans(plans.map(plan => {
      if (plan.id === planId) {
        return {
          ...plan,
          features: {
            ...plan.features,
            [field]: value
          }
        };
      }
      return plan;
    }));
  };

  const updatePrice = (planId: string, price: number) => {
    setPlans(plans.map(plan => 
      plan.id === planId ? { ...plan, price } : plan
    ));
  };

  const savePlan = (planId: string) => {
    setEditingPlan(null);
    toast.success('Plan configuration saved');
    console.log('Save plan to backend:', plans.find(p => p.id === planId));
  };

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="mb-6">
        <h1 className="text-3xl text-gray-900 mb-2">Subscription Plans</h1>
        <p className="text-gray-600">Configure features and pricing for each subscription tier</p>
      </div>

      {/* Platform Boundaries Notice */}
      <div className="bg-purple-50 border border-purple-200 rounded-xl p-4 mb-6">
        <h3 className="font-semibold text-purple-900 mb-2">⚠️ Platform Boundaries</h3>
        <p className="text-sm text-purple-800">
          As the platform operator, you configure <strong>what features are available</strong> in each plan. 
          Vendors control their own menu items, pricing, and promotions within these limits.
          Tavlo does not control restaurant operations—only the platform capabilities.
        </p>
      </div>

      {/* Plans Grid */}
      <div className="grid md:grid-cols-3 gap-6">
        {plans.map((plan) => {
          const isEditing = editingPlan === plan.id;

          return (
            <Card key={plan.id} className={`${plan.popular ? 'ring-2 ring-purple-500' : ''} relative`}>
              {plan.popular && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-gradient-to-r from-purple-600 to-blue-600 text-white text-xs rounded-full">
                  Most Popular
                </div>
              )}
              
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="text-xl">{plan.name}</CardTitle>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => isEditing ? savePlan(plan.id) : setEditingPlan(plan.id)}
                  >
                    {isEditing ? <Save className="w-4 h-4" /> : <Edit className="w-4 h-4" />}
                  </Button>
                </div>
                <p className="text-sm text-gray-600">{plan.description}</p>
              </CardHeader>

              <CardContent>
                {/* Pricing */}
                <div className="mb-6 pb-6 border-b">
                  <div className="flex items-baseline gap-2">
                    <span className="text-3xl">€</span>
                    {isEditing ? (
                      <input
                        type="number"
                        value={plan.price}
                        onChange={(e) => updatePrice(plan.id, parseFloat(e.target.value))}
                        className="text-3xl w-20 border border-gray-300 rounded px-2 py-1"
                      />
                    ) : (
                      <span className="text-3xl">{plan.price}</span>
                    )}
                    <span className="text-gray-600">/month</span>
                  </div>
                  {plan.stripePriceId && (
                    <div className="text-xs text-gray-500 mt-1">Stripe: {plan.stripePriceId}</div>
                  )}
                </div>

                {/* Features */}
                <div className="space-y-3">
                  <div className="font-semibold text-sm text-gray-700 mb-2">Features:</div>
                  
                  {/* Boolean Features */}
                  {(Object.keys(plan.features).filter(key => 
                    typeof plan.features[key as keyof typeof plan.features] === 'boolean'
                  ) as Array<keyof typeof plan.features>).map((feature) => (
                    <div key={feature} className="flex items-center justify-between">
                      <span className="text-sm text-gray-700">
                        {featureLabels[feature]}
                      </span>
                      {isEditing ? (
                        <button
                          onClick={() => toggleFeature(plan.id, feature)}
                          className={`w-10 h-6 rounded-full transition-colors ${
                            plan.features[feature] ? 'bg-green-500' : 'bg-gray-300'
                          }`}
                        >
                          <div className={`w-4 h-4 bg-white rounded-full transition-transform ${
                            plan.features[feature] ? 'translate-x-5' : 'translate-x-1'
                          }`} />
                        </button>
                      ) : (
                        plan.features[feature] ? (
                          <Check className="w-5 h-5 text-green-600" />
                        ) : (
                          <X className="w-5 h-5 text-gray-400" />
                        )
                      )}
                    </div>
                  ))}

                  {/* Numeric Limits */}
                  <div className="pt-3 border-t space-y-3">
                    <div>
                      <label className="text-sm text-gray-700 block mb-1">Max Menu Items:</label>
                      {isEditing ? (
                        <input
                          type="number"
                          value={plan.features.maxMenuItems === 999999 ? '' : plan.features.maxMenuItems}
                          placeholder="Unlimited"
                          onChange={(e) => updateLimit(plan.id, 'maxMenuItems', e.target.value ? parseInt(e.target.value) : 999999)}
                          className="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                        />
                      ) : (
                        <div className="text-sm font-medium">
                          {plan.features.maxMenuItems === 999999 ? '∞ Unlimited' : plan.features.maxMenuItems}
                        </div>
                      )}
                    </div>

                    <div>
                      <label className="text-sm text-gray-700 block mb-1">Max Tables:</label>
                      {isEditing ? (
                        <input
                          type="number"
                          value={plan.features.maxTables === 999999 ? '' : plan.features.maxTables}
                          placeholder="Unlimited"
                          onChange={(e) => updateLimit(plan.id, 'maxTables', e.target.value ? parseInt(e.target.value) : 999999)}
                          className="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                        />
                      ) : (
                        <div className="text-sm font-medium">
                          {plan.features.maxTables === 999999 ? '∞ Unlimited' : plan.features.maxTables}
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                {isEditing && (
                  <Button
                    onClick={() => savePlan(plan.id)}
                    className="w-full mt-4 bg-green-600 hover:bg-green-700"
                  >
                    Save Changes
                  </Button>
                )}
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Active Subscriptions Summary */}
      <div className="mt-8 grid md:grid-cols-3 gap-4">
        <Card>
          <CardContent className="p-6">
            <div className="text-sm text-gray-600 mb-1">Basic Plan</div>
            <div className="text-2xl">127 vendors</div>
            <div className="text-xs text-gray-500 mt-1">€3,683/month MRR</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <div className="text-sm text-gray-600 mb-1">Professional Plan</div>
            <div className="text-2xl">43 vendors</div>
            <div className="text-xs text-gray-500 mt-1">€3,397/month MRR</div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <div className="text-sm text-gray-600 mb-1">Enterprise Plan</div>
            <div className="text-2xl">8 vendors</div>
            <div className="text-xs text-gray-500 mt-1">€1,592/month MRR</div>
          </CardContent>
        </Card>
      </div>

      {/* Action Log */}
      <Card className="mt-6">
        <CardHeader>
          <CardTitle>Recent Plan Changes</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-3 text-sm">
            <div className="flex justify-between items-center py-2 border-b">
              <div>
                <span className="font-medium">Professional plan</span> - Enabled "Promotions" feature
              </div>
              <span className="text-gray-500">2 days ago</span>
            </div>
            <div className="flex justify-between items-center py-2 border-b">
              <div>
                <span className="font-medium">Basic plan</span> - Increased max tables from 5 to 10
              </div>
              <span className="text-gray-500">1 week ago</span>
            </div>
            <div className="flex justify-between items-center py-2">
              <div>
                <span className="font-medium">Enterprise plan</span> - Price adjusted to €199/month
              </div>
              <span className="text-gray-500">2 weeks ago</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
