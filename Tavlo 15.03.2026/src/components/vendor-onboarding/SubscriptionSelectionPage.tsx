import { ArrowLeft, Lock, Check, CreditCard } from 'lucide-react';
import { Button } from '../ui/button';
import { useState } from 'react';

interface Plan {
  id: string;
  name: string;
  monthlyPrice: number;
  yearlyPrice: number;
  currency: string;
  popular?: boolean;
  features: string[];
}

const PLANS: Plan[] = [
  {
    id: 'basic',
    name: 'Basic',
    monthlyPrice: 99,
    yearlyPrice: 990,
    currency: 'EUR',
    features: [
      'Up to 50 menu items',
      'Up to 10 tables',
      'Basic analytics',
      'QR ordering',
      'Email support'
    ]
  },
  {
    id: 'standard',
    name: 'Standard',
    monthlyPrice: 199,
    yearlyPrice: 1990,
    currency: 'EUR',
    popular: true,
    features: [
      'Up to 150 menu items',
      'Up to 30 tables',
      'Advanced analytics',
      'QR ordering',
      'Multi-language support',
      'Priority support',
      'Loyalty program'
    ]
  },
  {
    id: 'premium',
    name: 'Premium',
    monthlyPrice: 299,
    yearlyPrice: 2990,
    currency: 'EUR',
    features: [
      'Unlimited menu items',
      'Unlimited tables',
      'Full analytics suite',
      'White-label options',
      'Dedicated account manager',
      'Custom integrations',
      'API access'
    ]
  }
];

interface SubscriptionSelectionPageProps {
  onSelectPlan: (planId: string, interval: 'month' | 'year') => void;
  onBackToDemo: () => void;
}

export function SubscriptionSelectionPage({ onSelectPlan, onBackToDemo }: SubscriptionSelectionPageProps) {
  const [billingInterval, setBillingInterval] = useState<'month' | 'year'>('month');

  const calculateSavings = (monthlyPrice: number, yearlyPrice: number) => {
    const yearlyIfMonthly = monthlyPrice * 12;
    const savings = Math.round(((yearlyIfMonthly - yearlyPrice) / yearlyIfMonthly) * 100);
    return savings;
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 py-8">
        {/* Back Button */}
        <div className="mb-8">
          <button
            onClick={onBackToDemo}
            className="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Back to demo</span>
          </button>
        </div>

        {/* Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl mb-4">
            <Lock className="w-8 h-8 text-white" />
          </div>
          <h1 className="text-4xl mb-3 text-gray-900">Unlock your restaurant on Tavlo</h1>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto mb-6">
            Choose a plan to activate real data, QR codes, and live ordering
          </p>

          {/* Billing Toggle */}
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
                Save up to 17%
              </span>
            </button>
          </div>
        </div>

        {/* Pricing Cards - Three Column Layout */}
        <div className="grid lg:grid-cols-3 gap-6 mb-12">
          {PLANS.map((plan) => {
            const price = billingInterval === 'month' ? plan.monthlyPrice : plan.yearlyPrice;
            const savings = calculateSavings(plan.monthlyPrice, plan.yearlyPrice);

            return (
              <div
                key={plan.id}
                className={`bg-white rounded-xl border-2 transition-all ${
                  plan.popular
                    ? 'border-emerald-500 shadow-lg scale-105 relative'
                    : 'border-gray-200 shadow-sm hover:shadow-md'
                }`}
              >
                {/* Popular Badge */}
                {plan.popular && (
                  <div className="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-emerald-500 to-teal-600 text-white text-sm rounded-full shadow-md">
                    Most Popular
                  </div>
                )}

                <div className="p-6">
                  {/* Plan Name */}
                  <h3 className="text-2xl text-gray-900 mb-4">{plan.name}</h3>

                  {/* Price */}
                  <div className="mb-6">
                    <div className="flex items-baseline gap-2 mb-1">
                      <span className="text-4xl text-gray-900">€{price}</span>
                      <span className="text-gray-600">/ {billingInterval}</span>
                    </div>
                    {billingInterval === 'year' && (
                      <p className="text-sm text-emerald-600">
                        Save {savings}% compared to monthly
                      </p>
                    )}
                  </div>

                  {/* Features */}
                  <ul className="space-y-3 mb-8">
                    {plan.features.map((feature, index) => (
                      <li key={index} className="flex items-start gap-3">
                        <Check className="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" />
                        <span className="text-gray-700">{feature}</span>
                      </li>
                    ))}
                  </ul>

                  {/* CTA */}
                  <Button
                    onClick={() => onSelectPlan(plan.id, billingInterval)}
                    className={`w-full py-3 ${
                      plan.popular
                        ? 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white'
                        : 'bg-gray-900 hover:bg-gray-800 text-white'
                    }`}
                  >
                    <CreditCard className="w-4 h-4 mr-2" />
                    Subscribe
                  </Button>
                </div>
              </div>
            );
          })}
        </div>

        {/* Footer */}
        <div className="max-w-3xl mx-auto">
          {/* Trust Badges */}
          <div className="grid md:grid-cols-2 gap-4 mb-6">
            <div className="bg-white rounded-lg p-4 border border-gray-200 flex items-center gap-3">
              <div className="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <Check className="w-5 h-5 text-emerald-600" />
              </div>
              <div>
                <p className="text-sm text-gray-900">Secure payment via Stripe</p>
                <p className="text-xs text-gray-500">Your card details are never stored</p>
              </div>
            </div>

            <div className="bg-white rounded-lg p-4 border border-gray-200 flex items-center gap-3">
              <div className="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <Check className="w-5 h-5 text-emerald-600" />
              </div>
              <div>
                <p className="text-sm text-gray-900">Cancel anytime</p>
                <p className="text-xs text-gray-500">No long-term commitment required</p>
              </div>
            </div>
          </div>

          {/* Legal */}
          <p className="text-center text-sm text-gray-600">
            By subscribing, you agree to Tavlo's{' '}
            <a href="/terms" target="_blank" className="text-emerald-600 hover:text-emerald-700 underline">
              Terms & Conditions
            </a>
            {' '}and{' '}
            <a href="/privacy" target="_blank" className="text-emerald-600 hover:text-emerald-700 underline">
              Privacy Policy
            </a>
          </p>
        </div>
      </div>
    </div>
  );
}
