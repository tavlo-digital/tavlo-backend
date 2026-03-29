import { AlertCircle, AlertTriangle, Clock, CheckCircle } from 'lucide-react';

export type VendorRiskType = 
  | 'payment-failure' 
  | 'subscription-expired' 
  | 'onboarding-stuck'
  | 'pending-changes'
  | 'clean';

interface VendorRiskIndicatorProps {
  riskType: VendorRiskType;
  details?: string;
  onClick: () => void;
}

export function VendorRiskIndicator({ riskType, details, onClick }: VendorRiskIndicatorProps) {
  const riskConfig = {
    'payment-failure': {
      icon: AlertCircle,
      color: 'text-red-600',
      bgColor: 'bg-red-50',
      borderColor: 'border-red-200',
      label: '🔴 Payment failures in last 24h',
      tooltip: details || 'Payment failures detected'
    },
    'subscription-expired': {
      icon: AlertTriangle,
      color: 'text-orange-600',
      bgColor: 'bg-orange-50',
      borderColor: 'border-orange-200',
      label: '🟠 Subscription expired or overdue',
      tooltip: details || 'Subscription expired but vendor active'
    },
    'onboarding-stuck': {
      icon: Clock,
      color: 'text-yellow-600',
      bgColor: 'bg-yellow-50',
      borderColor: 'border-yellow-200',
      label: '🟡 Onboarding incomplete or stuck',
      tooltip: details || 'Onboarding incomplete for >24h'
    },
    'pending-changes': {
      icon: AlertCircle,
      color: 'text-orange-600',
      bgColor: 'bg-orange-50',
      borderColor: 'border-orange-200',
      label: '🟠 Pending changes awaiting review',
      tooltip: details || 'Vendor has pending changes awaiting review'
    },
    'clean': {
      icon: CheckCircle,
      color: 'text-gray-400',
      bgColor: 'bg-white',
      borderColor: 'border-gray-100',
      label: '⚪ No active issues',
      tooltip: 'No active issues'
    }
  };

  const config = riskConfig[riskType];
  const Icon = config.icon;

  if (riskType === 'clean') {
    return (
      <div className="w-8 h-8 flex items-center justify-center">
        <div className={`w-2 h-2 rounded-full ${config.color === 'text-gray-400' ? 'bg-gray-200' : ''}`} />
      </div>
    );
  }

  return (
    <div className="relative group">
      <button
        onClick={onClick}
        className={`w-8 h-8 rounded-lg border ${config.borderColor} ${config.bgColor} flex items-center justify-center hover:ring-2 hover:ring-offset-1 hover:ring-${config.color.replace('text-', '')} transition-all`}
        aria-label={config.label}
      >
        <Icon className={`w-4 h-4 ${config.color}`} />
      </button>
      
      {/* Tooltip */}
      <div className="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 hidden group-hover:block z-50 pointer-events-none">
        <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
          {config.tooltip}
          <div className="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
            <div className="border-4 border-transparent border-t-gray-900" />
          </div>
        </div>
      </div>
    </div>
  );
}