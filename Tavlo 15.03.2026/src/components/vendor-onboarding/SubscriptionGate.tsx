import { CreditCard, Check, Shield, Lock, ArrowLeft, X, Mail } from 'lucide-react';
import { Button } from '../ui/button';
import { useState } from 'react';
import { projectId, publicAnonKey } from '../../utils/supabase/info';

const API_BASE = `https://${projectId}.supabase.co/functions/v1/make-server-1dccd8d3`;

interface SubscriptionPlan {
  id: string;
  name: string;
  price: number | 'custom';
  currency: string;
  interval: 'month' | 'year';
  features: string[];
  recommended?: boolean;
  stripePriceId?: string;
  target?: string;
  yearlyPrice?: number;
  ctaText?: string;
  ctaVariant?: 'primary' | 'secondary' | 'contact';
}

interface SubscriptionGateProps {
  vendorId: string;
  plans: SubscriptionPlan[];
  onSubscribe: (planId: string, checkoutUrl: string) => void;
  onBackToDemo?: () => void;
}

// Default 3-tier plans
const DEFAULT_PLANS: SubscriptionPlan[] = [
  {
    id: 'basic',
    name: 'Basic',
    price: 29,
    currency: 'EUR',
    interval: 'month',
    yearlyPrice: 290,
    target: 'Small cafés, takeaway-only, early-stage restaurants',
    ctaText: 'Subscribe & unlock',
    ctaVariant: 'secondary',
    features: [
      'QR code menu',
      'Unlimited menu items',
      'Multi-language menus',
      'Pay-at-restaurant orders',
      'Basic order management',
      'Email support'
    ]
  },
  {
    id: 'professional',
    name: 'Professional',
    price: 49,
    currency: 'EUR',
    interval: 'month',
    yearlyPrice: 490,
    recommended: true,
    target: 'Full-service restaurants',
    ctaText: 'Subscribe & go live',
    ctaVariant: 'primary',
    features: [
      'Everything in Basic',
      'QR code ordering',
      'Real-time order management',
      'Analytics dashboard',
      'Online payment support',
      'Table & QR management',
      'Priority email support'
    ]
  },
  {
    id: 'enterprise',
    name: 'Enterprise',
    price: 'custom',
    currency: 'EUR',
    interval: 'month',
    target: 'Chains, franchises, hotels',
    ctaText: 'Contact sales',
    ctaVariant: 'contact',
    features: [
      'Everything in Professional',
      'Advanced analytics',
      'Custom branding',
      'API access',
      'Multi-location management',
      'Dedicated account manager',
      'SLA & onboarding support'
    ]
  }
];

export function SubscriptionGate({ vendorId, plans = DEFAULT_PLANS, onSubscribe, onBackToDemo }: SubscriptionGateProps) {
  const [loading, setLoading] = useState(false);
  const [selectedPlan, setSelectedPlan] = useState<string | null>(null);
  const [billingInterval, setBillingInterval] = useState<'month' | 'year'>('month');
  const [showComparison, setShowComparison] = useState(false);
  const [showContactSales, setShowContactSales] = useState(false);

  const handleSubscribe = async (plan: SubscriptionPlan) => {
    // Handle contact sales separately
    if (plan.ctaVariant === 'contact') {
      setShowContactSales(true);
      return;
    }

    setLoading(true);
    setSelectedPlan(plan.id);

    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/create-checkout-session`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${publicAnonKey}`
        },
        body: JSON.stringify({
          planId: plan.id,
          interval: billingInterval,
          priceId: plan.stripePriceId || `price_${plan.id}_${billingInterval}`,
          successUrl: `${window.location.origin}?mode=vendor-onboarding&session_id={CHECKOUT_SESSION_ID}`,
          cancelUrl: `${window.location.origin}?mode=vendor-onboarding&canceled=true`
        })
      });

      if (!response.ok) {
        const errorText = await response.text();
        console.error('Checkout API error:', response.status, errorText);
        throw new Error(`API returned ${response.status}: ${errorText}`);
      }

      const data = await response.json();
      console.log('Checkout session created:', data);

      if (data.checkoutUrl) {
        console.log('Redirecting to Stripe checkout:', data.checkoutUrl);
        onSubscribe(plan.id, data.checkoutUrl);
        window.location.href = data.checkoutUrl;
      } else {
        console.error('No checkoutUrl in response:', data);
        throw new Error(data.error || 'Failed to create checkout session');
      }
    } catch (error) {
      console.error('Checkout error:', error);
      alert(`Failed to start checkout: ${error instanceof Error ? error.message : 'Unknown error'}`);
      setLoading(false);
      setSelectedPlan(null);
    }
  };

  const getPlanPrice = (plan: SubscriptionPlan) => {
    if (plan.price === 'custom') return 'Custom pricing';
    if (billingInterval === 'year' && plan.yearlyPrice) {
      return `${plan.yearlyPrice} ${plan.currency} / year`;
    }
    return `${plan.price} ${plan.currency} / month`;
  };

  const getSavingsText = (plan: SubscriptionPlan) => {
    if (billingInterval === 'year' && plan.yearlyPrice && typeof plan.price === 'number') {
      const monthlyCost = plan.price * 12;
      const savings = Math.round(((monthlyCost - plan.yearlyPrice) / monthlyCost) * 100);
      return `Save ${savings}% (2 months free)`;
    }
    return null;
  };

  return (
    <>
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 py-12 px-4">
        <div className="max-w-7xl mx-auto">
          {/* Back Button */}
          {onBackToDemo && (
            <div className="mb-8">
              <button
                onClick={onBackToDemo}
                className="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors"
              >
                <ArrowLeft className="w-4 h-4" />
                <span>Back to demo</span>
              </button>
            </div>
          )}

          {/* Header */}
          <div className="text-center mb-12">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl mb-4">
              <Lock className="w-8 h-8 text-white" />
            </div>
            <h1 className="text-4xl mb-3 text-gray-900">Unlock your restaurant on Tavlo</h1>
            <p className="text-xl text-gray-600 max-w-2xl mx-auto">
              Choose a plan to activate real data, QR codes, and live ordering
            </p>
          </div>

          {/* Billing Interval Toggle */}
          <div className="flex justify-center mb-8">
            <div className="inline-flex items-center bg-white rounded-lg p-1 shadow-sm border border-gray-200">
              <button
                onClick={() => setBillingInterval('month')}
                className={`px-6 py-2 rounded-md text-sm transition-all ${
                  billingInterval === 'month'
                    ? 'bg-emerald-600 text-white'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                Monthly
              </button>
              <button
                onClick={() => setBillingInterval('year')}
                className={`px-6 py-2 rounded-md text-sm transition-all ${
                  billingInterval === 'year'
                    ? 'bg-emerald-600 text-white'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                Yearly
                <span className="ml-2 text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded">
                  Save 17%
                </span>
              </button>
            </div>
          </div>

          {/* Pricing Cards - 3 Tiers */}
          <div className="grid lg:grid-cols-3 gap-8 mb-8">
            {plans.map((plan) => (
              <div
                key={plan.id}
                className={`bg-white rounded-2xl shadow-xl p-8 relative flex flex-col ${
                  plan.recommended ? 'ring-4 ring-emerald-500 scale-105' : ''
                }`}
              >
                {plan.recommended && (
                  <div className="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-sm rounded-full">
                    Recommended
                  </div>
                )}

                <div className="text-center mb-6">
                  <h3 className="text-2xl text-gray-900 mb-2">{plan.name}</h3>
                  {plan.target && (
                    <p className="text-sm text-gray-500 mb-4">{plan.target}</p>
                  )}
                  
                  <div className="mb-2">
                    {plan.price === 'custom' ? (
                      <div className="text-3xl text-gray-900">Custom pricing</div>
                    ) : (
                      <>
                        <div className="flex items-baseline justify-center gap-2">
                          <span className="text-5xl text-gray-900">
                            {billingInterval === 'year' && plan.yearlyPrice 
                              ? plan.yearlyPrice 
                              : plan.price}
                          </span>
                          <span className="text-xl text-gray-600">{plan.currency}</span>
                        </div>
                        <p className="text-gray-600 mt-1">
                          per {billingInterval}
                        </p>
                      </>
                    )}
                  </div>
                  
                  {billingInterval === 'year' && getSavingsText(plan) && (
                    <p className="text-sm text-emerald-600">
                      {getSavingsText(plan)}
                    </p>
                  )}
                </div>

                <ul className="space-y-3 mb-8 flex-1">
                  {plan.features.map((feature, index) => (
                    <li key={index} className="flex items-start gap-3">
                      <Check className="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" />
                      <span className="text-gray-700 text-sm">{feature}</span>
                    </li>
                  ))}
                </ul>

                <div>
                  <Button
                    onClick={() => handleSubscribe(plan)}
                    disabled={loading && selectedPlan === plan.id}
                    className={`w-full py-4 ${
                      plan.ctaVariant === 'primary'
                        ? 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white'
                        : plan.ctaVariant === 'contact'
                        ? 'bg-gray-900 hover:bg-gray-800 text-white'
                        : 'bg-gray-100 hover:bg-gray-200 text-gray-900'
                    }`}
                  >
                    {plan.ctaVariant === 'contact' ? (
                      <>
                        <Mail className="w-5 h-5 mr-2" />
                        {plan.ctaText || 'Contact sales'}
                      </>
                    ) : (
                      <>
                        <CreditCard className="w-5 h-5 mr-2" />
                        {loading && selectedPlan === plan.id 
                          ? 'Redirecting...' 
                          : plan.ctaText || 'Subscribe & unlock'}
                      </>
                    )}
                  </Button>
                  
                  {plan.ctaVariant !== 'contact' && (
                    <p className="text-xs text-gray-500 text-center mt-3">
                      After subscribing, you'll complete a short setup to add your legal info and menu before going live.
                    </p>
                  )}
                </div>
              </div>
            ))}
          </div>

          {/* Compare Plans Link */}
          <div className="text-center mb-8">
            <button
              onClick={() => setShowComparison(!showComparison)}
              className="text-emerald-600 hover:text-emerald-700 text-sm underline"
            >
              Compare plans
            </button>
          </div>

          {/* Plan Comparison Table */}
          {showComparison && (
            <div className="bg-white rounded-2xl shadow-xl p-8 mb-8">
              <div className="flex items-center justify-between mb-6">
                <h3 className="text-2xl text-gray-900">Feature Comparison</h3>
                <button
                  onClick={() => setShowComparison(false)}
                  className="p-2 hover:bg-gray-100 rounded-lg"
                >
                  <X className="w-5 h-5 text-gray-500" />
                </button>
              </div>
              
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-gray-200">
                      <th className="text-left py-3 px-4 text-gray-900">Feature</th>
                      <th className="text-center py-3 px-4 text-gray-900">Basic</th>
                      <th className="text-center py-3 px-4 text-gray-900">Professional</th>
                      <th className="text-center py-3 px-4 text-gray-900">Enterprise</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    <tr>
                      <td className="py-3 px-4 text-gray-700">QR code menu</td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                    </tr>
                    <tr>
                      <td className="py-3 px-4 text-gray-700">QR code ordering</td>
                      <td className="text-center py-3 px-4 text-gray-400">—</td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                    </tr>
                    <tr>
                      <td className="py-3 px-4 text-gray-700">Real-time order management</td>
                      <td className="text-center py-3 px-4 text-gray-400">Basic</td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                    </tr>
                    <tr>
                      <td className="py-3 px-4 text-gray-700">Analytics dashboard</td>
                      <td className="text-center py-3 px-4 text-gray-400">—</td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                      <td className="text-center py-3 px-4 text-gray-400">Advanced</td>
                    </tr>
                    <tr>
                      <td className="py-3 px-4 text-gray-700">Online payment support</td>
                      <td className="text-center py-3 px-4 text-gray-400">—</td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                    </tr>
                    <tr>
                      <td className="py-3 px-4 text-gray-700">API access</td>
                      <td className="text-center py-3 px-4 text-gray-400">—</td>
                      <td className="text-center py-3 px-4 text-gray-400">—</td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                    </tr>
                    <tr>
                      <td className="py-3 px-4 text-gray-700">Custom branding</td>
                      <td className="text-center py-3 px-4 text-gray-400">—</td>
                      <td className="text-center py-3 px-4 text-gray-400">—</td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                    </tr>
                    <tr>
                      <td className="py-3 px-4 text-gray-700">Dedicated account manager</td>
                      <td className="text-center py-3 px-4 text-gray-400">—</td>
                      <td className="text-center py-3 px-4 text-gray-400">—</td>
                      <td className="text-center py-3 px-4"><Check className="w-5 h-5 text-emerald-600 mx-auto" /></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Trust & Risk Reduction */}
          <div className="grid md:grid-cols-2 gap-6 mb-8">
            <div className="bg-white/80 backdrop-blur-sm rounded-xl p-6 border border-gray-200">
              <div className="flex items-center gap-3 mb-3">
                <Shield className="w-5 h-5 text-emerald-600" />
                <h4 className="text-gray-900">Secure payment via Stripe</h4>
              </div>
              <p className="text-sm text-gray-600">
                We never store your card details.
              </p>
            </div>
            
            <div className="bg-white/80 backdrop-blur-sm rounded-xl p-6 border border-gray-200">
              <div className="flex items-center gap-3 mb-3">
                <Check className="w-5 h-5 text-emerald-600" />
                <h4 className="text-gray-900">Cancel anytime</h4>
              </div>
              <p className="text-sm text-gray-600">
                No long-term commitment. No hidden fees.
              </p>
            </div>
          </div>

          {/* All prices exclude VAT notice */}
          <div className="text-center mb-8">
            <p className="text-sm text-gray-500">
              All prices exclude VAT.
            </p>
          </div>

          {/* Legal Footer */}
          <div className="text-center text-sm text-gray-600">
            <p>
              By subscribing, you agree to Tavlo's{' '}
              <a 
                href="/terms" 
                target="_blank" 
                className="text-emerald-600 hover:text-emerald-700 underline"
              >
                Terms & Conditions
              </a>
              {' '}and{' '}
              <a 
                href="/privacy" 
                target="_blank" 
                className="text-emerald-600 hover:text-emerald-700 underline"
              >
                Privacy Policy
              </a>
            </p>
          </div>
        </div>
      </div>

      {/* Contact Sales Modal */}
      {showContactSales && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div 
            className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 relative"
            onClick={(e) => e.stopPropagation()}
          >
            <button
              onClick={() => setShowContactSales(false)}
              className="absolute top-4 right-4 p-2 hover:bg-gray-100 rounded-lg"
            >
              <X className="w-5 h-5 text-gray-500" />
            </button>

            <div className="text-center mb-6">
              <div className="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
                <Mail className="w-8 h-8 text-emerald-600" />
              </div>
              <h3 className="text-2xl mb-2 text-gray-900">Contact Sales</h3>
              <p className="text-gray-600">
                Get in touch with our sales team to discuss Enterprise features and custom pricing
              </p>
            </div>

            <div className="space-y-4">
              <div className="bg-gray-50 rounded-lg p-4">
                <p className="text-sm text-gray-700 mb-2">
                  <strong>Email:</strong>{' '}
                  <a href="mailto:sales@tavlo.com" className="text-emerald-600 hover:text-emerald-700">
                    sales@tavlo.com
                  </a>
                </p>
                <p className="text-sm text-gray-700">
                  <strong>Response time:</strong> Within 24 hours
                </p>
              </div>

              <Button
                onClick={() => {
                  window.location.href = 'mailto:sales@tavlo.com?subject=Enterprise Plan Inquiry';
                }}
                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                Send email
              </Button>

              <button
                onClick={() => setShowContactSales(false)}
                className="w-full text-sm text-gray-600 hover:text-gray-900"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
