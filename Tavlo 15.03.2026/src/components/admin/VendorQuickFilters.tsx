import { AlertCircle, AlertTriangle, Clock, Star, Flag } from 'lucide-react';

export type QuickFilterType = 
  | 'payment-issues' 
  | 'subscription-issues' 
  | 'onboarding-stuck' 
  | 'high-gmv' 
  | 'flagged-content'
  | null;

interface VendorQuickFiltersProps {
  activeFilter: QuickFilterType;
  onFilterChange: (filter: QuickFilterType) => void;
  counts?: {
    paymentIssues?: number;
    subscriptionIssues?: number;
    onboardingStuck?: number;
    highGmv?: number;
    flaggedContent?: number;
  };
}

export function VendorQuickFilters({ 
  activeFilter, 
  onFilterChange, 
  counts = {} 
}: VendorQuickFiltersProps) {
  const filters = [
    {
      id: 'payment-issues' as QuickFilterType,
      icon: AlertCircle,
      label: '🔴 Payment Issues',
      color: 'red',
      count: counts.paymentIssues
    },
    {
      id: 'subscription-issues' as QuickFilterType,
      icon: AlertTriangle,
      label: '🟠 Subscription Issues',
      color: 'orange',
      count: counts.subscriptionIssues
    },
    {
      id: 'onboarding-stuck' as QuickFilterType,
      icon: Clock,
      label: '🟡 Onboarding Stuck',
      color: 'yellow',
      count: counts.onboardingStuck
    },
    {
      id: 'high-gmv' as QuickFilterType,
      icon: Star,
      label: '⭐ High GMV Vendors',
      color: 'purple',
      count: counts.highGmv
    },
    {
      id: 'flagged-content' as QuickFilterType,
      icon: Flag,
      label: '⚠️ Flagged Content',
      color: 'pink',
      count: counts.flaggedContent
    }
  ];

  return (
    <div className="bg-white border-b border-gray-200 px-6 py-3">
      <div className="flex items-center gap-2">
        <span className="text-sm font-medium text-gray-700 mr-2">Quick Filters:</span>
        
        {/* Clear all filter */}
        <button
          onClick={() => onFilterChange(null)}
          className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${
            activeFilter === null
              ? 'bg-gray-900 text-white'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
          }`}
        >
          All Vendors
        </button>

        {filters.map((filter) => {
          const Icon = filter.icon;
          const isActive = activeFilter === filter.id;
          
          const colorClasses = {
            red: isActive 
              ? 'bg-red-600 text-white border-red-600' 
              : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100',
            orange: isActive 
              ? 'bg-orange-600 text-white border-orange-600' 
              : 'bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100',
            yellow: isActive 
              ? 'bg-yellow-600 text-white border-yellow-600' 
              : 'bg-yellow-50 text-yellow-700 border-yellow-200 hover:bg-yellow-100',
            purple: isActive 
              ? 'bg-purple-600 text-white border-purple-600' 
              : 'bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100',
            pink: isActive 
              ? 'bg-pink-600 text-white border-pink-600' 
              : 'bg-pink-50 text-pink-700 border-pink-200 hover:bg-pink-100'
          };

          return (
            <button
              key={filter.id}
              onClick={() => onFilterChange(isActive ? null : filter.id)}
              className={`px-3 py-1.5 rounded-lg border text-sm font-medium transition-all inline-flex items-center gap-1.5 ${
                colorClasses[filter.color as keyof typeof colorClasses]
              }`}
            >
              <Icon className="w-4 h-4" />
              <span>{filter.label}</span>
              {filter.count !== undefined && filter.count > 0 && (
                <span className={`ml-1 px-1.5 py-0.5 rounded-full text-xs font-semibold ${
                  isActive ? 'bg-white/20' : 'bg-black/10'
                }`}>
                  {filter.count}
                </span>
              )}
            </button>
          );
        })}
      </div>
      
      {activeFilter && (
        <div className="mt-2 text-xs text-gray-600">
          Showing filtered results. Click filter again or "All Vendors" to clear.
        </div>
      )}
    </div>
  );
}
