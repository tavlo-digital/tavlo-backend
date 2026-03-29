import { CheckCircle, AlertCircle, Clock } from 'lucide-react';

type SystemStatus = 'operational' | 'psp-degraded' | 'notifications-delayed';

interface SystemStatusIndicatorProps {
  status: SystemStatus;
  onClick: () => void;
}

export function SystemStatusIndicator({ status, onClick }: SystemStatusIndicatorProps) {
  const statusConfig = {
    operational: {
      icon: CheckCircle,
      color: 'text-green-600',
      bgColor: 'bg-green-50',
      label: 'All systems operational',
    },
    'psp-degraded': {
      icon: AlertCircle,
      color: 'text-orange-600',
      bgColor: 'bg-orange-50',
      label: 'PSP degraded',
    },
    'notifications-delayed': {
      icon: Clock,
      color: 'text-yellow-600',
      bgColor: 'bg-yellow-50',
      label: 'Notifications delayed',
    },
  };

  const config = statusConfig[status];
  const Icon = config.icon;

  return (
    <button
      onClick={onClick}
      className={`flex items-center gap-2 px-3 py-1.5 rounded-lg ${config.bgColor} hover:opacity-80 transition-opacity`}
    >
      <Icon className={`w-4 h-4 ${config.color}`} />
      <span className={`text-sm font-medium ${config.color}`}>
        {config.label}
      </span>
    </button>
  );
}
