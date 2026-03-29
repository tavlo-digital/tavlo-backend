import { ReactNode } from 'react';
import { Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface FeatureGateProps {
  feature: 'loyalty' | 'promotions' | 'multiLanguage' | 'advancedAnalytics' | 'customAppearance' | 'multiLocation';
  children: ReactNode;
  fallback?: ReactNode;
  vendorPlan?: string; // 'basic' | 'professional' | 'enterprise'
}

// Feature availability by plan
const PLAN_FEATURES = {
  basic: {
    loyalty: false,
    promotions: false,
    multiLanguage: false,
    advancedAnalytics: false,
    customAppearance: false,
    multiLocation: false,
    maxMenuItems: 50,
    maxTables: 10,
  },
  professional: {
    loyalty: true,
    promotions: true,
    multiLanguage: true,
    advancedAnalytics: false,
    customAppearance: true,
    multiLocation: false,
    maxMenuItems: 200,
    maxTables: 30,
  },
  enterprise: {
    loyalty: true,
    promotions: true,
    multiLanguage: true,
    advancedAnalytics: true,
    customAppearance: true,
    multiLocation: true,
    maxMenuItems: Infinity,
    maxTables: Infinity,
  }
};

const FEATURE_NAMES = {
  loyalty: 'Loyalty Program',
  promotions: 'Promotions & Discounts',
  multiLanguage: 'Multi-Language Support',
  advancedAnalytics: 'Advanced Analytics',
  customAppearance: 'Custom Appearance',
  multiLocation: 'Multi-Location Management'
};

const FEATURE_DESCRIPTIONS = {
  loyalty: 'Reward your customers with points and redemptions',
  promotions: 'Create time-based and discount campaigns',
  multiLanguage: 'Serve customers in multiple languages',
  advancedAnalytics: 'Deep insights and predictive analytics',
  customAppearance: 'Fully customize your menu appearance',
  multiLocation: 'Manage multiple restaurant locations'
};

export function FeatureGate({ feature, children, fallback, vendorPlan = 'basic' }: FeatureGateProps) {
  const plan = PLAN_FEATURES[vendorPlan as keyof typeof PLAN_FEATURES] || PLAN_FEATURES.basic;
  const hasAccess = plan[feature];

  if (!hasAccess) {
    if (fallback) {
      return <>{fallback}</>;
    }

    return (
      <div className="max-w-4xl mx-auto p-6">
        <div className="bg-gradient-to-br from-purple-50 to-blue-50 border-2 border-purple-200 rounded-2xl p-8 text-center">
          <div className="w-16 h-16 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
            <Lock className="w-8 h-8 text-white" />
          </div>
          
          <h2 className="text-2xl text-gray-900 mb-2">{FEATURE_NAMES[feature]}</h2>
          <p className="text-gray-600 mb-6">{FEATURE_DESCRIPTIONS[feature]}</p>
          
          <div className="bg-white rounded-xl p-6 mb-6 max-w-2xl mx-auto">
            <div className="text-sm text-gray-500 mb-3">Your current plan:</div>
            <div className="flex items-center justify-center gap-4 mb-4">
              <span className="px-4 py-2 bg-gray-100 text-gray-900 rounded-lg font-semibold capitalize">
                {vendorPlan}
              </span>
              <span className="text-gray-400">→</span>
              <span className="text-gray-500">Feature not included</span>
            </div>
            
            <div className="text-sm text-gray-600 mb-4">
              Upgrade to <strong className="text-purple-600">Professional</strong> or <strong className="text-purple-600">Enterprise</strong> to unlock this feature
            </div>
          </div>

          <div className="flex gap-3 justify-center">
            <Button 
              className="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white px-8"
              onClick={() => {
                // Navigate to subscription page or show upgrade modal
                console.log('Upgrade clicked for feature:', feature);
                alert('Upgrade modal would open here');
              }}
            >
              Upgrade Now
            </Button>
            <Button 
              variant="outline"
              onClick={() => window.history.back()}
            >
              Go Back
            </Button>
          </div>

          {/* Comparison */}
          <div className="mt-8 pt-8 border-t border-purple-200">
            <p className="text-sm text-gray-600 mb-4">Compare Plans:</p>
            <div className="grid grid-cols-3 gap-4 text-xs max-w-2xl mx-auto">
              <div className="bg-white rounded-lg p-3 border border-gray-200">
                <div className="font-semibold text-gray-900 mb-2">Basic</div>
                <div className="text-gray-500">€29/month</div>
                <div className="text-gray-500 mt-2">50 menu items</div>
                <div className="text-gray-500">10 tables</div>
              </div>
              <div className="bg-gradient-to-br from-purple-500 to-blue-500 text-white rounded-lg p-3 shadow-lg">
                <div className="font-semibold mb-2">Professional</div>
                <div>€79/month</div>
                <div className="mt-2">200 menu items</div>
                <div>30 tables</div>
                <div className="mt-2 text-xs">+ All premium features</div>
              </div>
              <div className="bg-gray-900 text-white rounded-lg p-3">
                <div className="font-semibold mb-2">Enterprise</div>
                <div>€199/month</div>
                <div className="mt-2">Unlimited items</div>
                <div>Unlimited tables</div>
                <div className="mt-2 text-xs">+ Multi-location</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}

// Helper hook for feature access checking
export function useFeatureAccess(vendorPlan: string = 'basic') {
  const plan = PLAN_FEATURES[vendorPlan as keyof typeof PLAN_FEATURES] || PLAN_FEATURES.basic;
  
  return {
    hasAccess: (feature: keyof typeof plan) => plan[feature],
    plan: vendorPlan,
    limits: {
      maxMenuItems: plan.maxMenuItems,
      maxTables: plan.maxTables
    }
  };
}
