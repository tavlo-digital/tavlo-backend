import { AlertCircle, AlertTriangle, CheckCircle } from 'lucide-react';

export type CustomerRiskType = 
  | 'flagged-account'
  | 'unusual-activity'
  | 'normal';

interface CustomerRiskIndicatorProps {
  riskType: CustomerRiskType;
  details?: string;
  onClick: () => void;
}

export function CustomerRiskIndicator({ riskType, details, onClick }: CustomerRiskIndicatorProps) {
  const riskConfig = {
    'flagged-account': {
      icon: AlertCircle,
      color: 'text-red-600',
      bgColor: 'bg-red-50',
      borderColor: 'border-red-200',
      label: '🔴 Flagged account',
      tooltip: details || 'Account flagged for review'
    },
    'unusual-activity': {
      icon: AlertTriangle,
      color: 'text-orange-600',
      bgColor: 'bg-orange-50',
      borderColor: 'border-orange-200',
      label: '🟠 Unusual activity',
      tooltip: details || 'Unusual refunds, disputes, or activity pattern'
    },
    'normal': {
      icon: CheckCircle,
      color: 'text-gray-400',
      bgColor: 'bg-white',
      borderColor: 'border-gray-100',
      label: '⚪ Normal',
      tooltip: 'No active issues'
    }
  };

  const config = riskConfig[riskType];
  const Icon = config.icon;

  if (riskType === 'normal') {
    return (
      <div className="w-8 h-8 flex items-center justify-center">
        <div className="w-2 h-2 rounded-full bg-gray-200" />
      </div>
    );
  }

  return (
    <div className="relative group">
      <button
        onClick={onClick}
        className={`w-8 h-8 rounded-lg border ${config.borderColor} ${config.bgColor} flex items-center justify-center hover:ring-2 hover:ring-offset-1 transition-all`}
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
