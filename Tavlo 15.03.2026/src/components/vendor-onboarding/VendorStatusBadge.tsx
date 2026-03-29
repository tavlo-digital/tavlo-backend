import { AlertCircle, Lock, CheckCircle, Circle } from 'lucide-react';

interface VendorStatusBadgeProps {
  status: 'demo' | 'activated' | 'live';
  isLiveAndDiscoverable?: boolean;
}

export function VendorStatusBadge({ status, isLiveAndDiscoverable = false }: VendorStatusBadgeProps) {
  // Determine actual live status: only truly live if status is 'activated' or 'live' AND isLiveAndDiscoverable is true
  const isActuallyLive = (status === 'activated' || status === 'live') && isLiveAndDiscoverable;
  
  const statusConfig = {
    demo: {
      icon: Lock,
      text: 'Demo mode – Not live',
      bgColor: 'bg-amber-100',
      textColor: 'text-amber-700',
      iconColor: 'text-amber-600',
      showDot: false
    },
    activated: {
      icon: AlertCircle,
      text: isLiveAndDiscoverable ? 'Live & Discoverable' : 'Activated · Not live',
      bgColor: isLiveAndDiscoverable ? 'bg-emerald-100' : 'bg-blue-100',
      textColor: isLiveAndDiscoverable ? 'text-emerald-700' : 'text-blue-700',
      iconColor: isLiveAndDiscoverable ? 'text-emerald-600' : 'text-blue-600',
      showDot: isLiveAndDiscoverable
    },
    live: {
      icon: CheckCircle,
      text: isLiveAndDiscoverable ? 'Live & Discoverable' : 'Activated · Not live',
      bgColor: isLiveAndDiscoverable ? 'bg-emerald-100' : 'bg-blue-100',
      textColor: isLiveAndDiscoverable ? 'text-emerald-700' : 'text-blue-700',
      iconColor: isLiveAndDiscoverable ? 'text-emerald-600' : 'text-blue-600',
      showDot: isLiveAndDiscoverable
    }
  };

  const config = statusConfig[status];
  const Icon = config.icon;

  return (
    <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full ${config.bgColor}`}>
      {config.showDot ? (
        <div className="relative">
          <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
          <div className="absolute inset-0 w-2 h-2 bg-green-400 rounded-full animate-ping"></div>
        </div>
      ) : (
        <Icon className={`w-4 h-4 ${config.iconColor}`} />
      )}
      <span className={`text-sm font-medium ${config.textColor}`}>
        {config.text}
      </span>
    </div>
  );
}