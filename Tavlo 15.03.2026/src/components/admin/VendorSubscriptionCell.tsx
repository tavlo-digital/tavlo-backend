import { Repeat, AlertTriangle } from 'lucide-react';

export type SubscriptionPlan = 'Basic' | 'Standard' | 'Premium' | 'Trial';
export type SubscriptionStatus = 'active' | 'expired' | 'cancelled';

interface VendorSubscriptionCellProps {
  plan: SubscriptionPlan;
  status: SubscriptionStatus;
  vendorIsActive: boolean;
  daysSinceExpiry?: number;
  onClick: () => void;
}

export function VendorSubscriptionCell({ 
  plan, 
  status, 
  vendorIsActive, 
  daysSinceExpiry, 
  onClick 
}: VendorSubscriptionCellProps) {
  // Revenue risk: expired subscription but vendor still active
  const hasRevenueRisk = status === 'expired' && vendorIsActive;

  const statusConfig = {
    active: {
      color: 'text-green-700',
      bgColor: 'bg-green-50',
      borderColor: 'border-green-200'
    },
    expired: hasRevenueRisk ? {
      color: 'text-red-700',
      bgColor: 'bg-red-50',
      borderColor: 'border-red-200'
    } : {
      color: 'text-gray-600',
      bgColor: 'bg-gray-50',
      borderColor: 'border-gray-200'
    },
    cancelled: {
      color: 'text-gray-600',
      bgColor: 'bg-gray-50',
      borderColor: 'border-gray-200'
    }
  };

  const config = statusConfig[status];

  return (
    <div className="relative group">
      <button
        onClick={onClick}
        className={`px-3 py-1.5 rounded-lg border ${config.borderColor} ${config.bgColor} ${config.color} text-sm font-medium hover:ring-2 hover:ring-offset-1 transition-all inline-flex items-center gap-1.5`}
      >
        <Repeat className="w-3.5 h-3.5" />
        <span>{plan}</span>
        {hasRevenueRisk && (
          <>
            <span className="mx-1 text-gray-400">·</span>
            <span className="inline-flex items-center gap-1">
              <AlertTriangle className="w-3 h-3" />
              Expired · Active
            </span>
          </>
        )}
      </button>
      
      {/* Tooltip */}
      {(hasRevenueRisk || daysSinceExpiry) && (
        <div className="absolute left-0 bottom-full mb-2 hidden group-hover:block z-50 pointer-events-none">
          <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
            {hasRevenueRisk && <div className="font-semibold text-red-300">⚠️ Revenue Risk</div>}
            {daysSinceExpiry && (
              <div className="mt-1">
                Expired {daysSinceExpiry} day{daysSinceExpiry !== 1 ? 's' : ''} ago
              </div>
            )}
            <div className="absolute top-full left-6 -mt-1">
              <div className="border-4 border-transparent border-t-gray-900" />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
