import { Store, CheckCircle, XCircle, Globe, GlobeLock } from 'lucide-react';

export type VendorCounterType = 
  | 'total' 
  | 'active' 
  | 'inactive' 
  | 'live' 
  | 'not-live'
  | null;

interface VendorSummaryCountersProps {
  counters: {
    total: number;
    active: number;
    inactive: number;
    live: number;
    notLive: number;
  };
  activeCounter: VendorCounterType;
  onCounterClick: (counter: VendorCounterType) => void;
}

export function VendorSummaryCounters({ 
  counters, 
  activeCounter, 
  onCounterClick 
}: VendorSummaryCountersProps) {
  const counterItems = [
    {
      id: 'total' as VendorCounterType,
      label: 'Total Vendors',
      value: counters.total,
      icon: Store,
      color: 'gray',
      description: 'All vendors in system'
    },
    {
      id: 'active' as VendorCounterType,
      label: 'Active Vendors',
      value: counters.active,
      icon: CheckCircle,
      color: 'green',
      description: 'Vendors with active subscription'
    },
    {
      id: 'inactive' as VendorCounterType,
      label: 'Inactive Vendors',
      value: counters.inactive,
      icon: XCircle,
      color: 'red',
      description: 'Suspended or inactive vendors'
    },
    {
      id: 'live' as VendorCounterType,
      label: 'Live Vendors',
      value: counters.live,
      icon: Globe,
      color: 'blue',
      description: 'Menu published + accepting orders'
    },
    {
      id: 'not-live' as VendorCounterType,
      label: 'Not Live Vendors',
      value: counters.notLive,
      icon: GlobeLock,
      color: 'orange',
      description: 'Menu unpublished OR orders disabled'
    }
  ];

  return (
    <div className="bg-white border-b border-gray-200 px-6 py-4">
      <div className="grid grid-cols-5 gap-4">
        {counterItems.map((counter) => {
          const Icon = counter.icon;
          const isActive = activeCounter === counter.id;

          const colorClasses = {
            gray: {
              bg: isActive ? 'bg-gray-100' : 'bg-gray-50',
              border: isActive ? 'border-gray-900' : 'border-gray-200',
              text: 'text-gray-900',
              icon: 'text-gray-600',
              hover: 'hover:bg-gray-100 hover:border-gray-300'
            },
            green: {
              bg: isActive ? 'bg-green-100' : 'bg-green-50',
              border: isActive ? 'border-green-600' : 'border-green-200',
              text: 'text-green-900',
              icon: 'text-green-600',
              hover: 'hover:bg-green-100 hover:border-green-300'
            },
            red: {
              bg: isActive ? 'bg-red-100' : 'bg-red-50',
              border: isActive ? 'border-red-600' : 'border-red-200',
              text: 'text-red-900',
              icon: 'text-red-600',
              hover: 'hover:bg-red-100 hover:border-red-300'
            },
            blue: {
              bg: isActive ? 'bg-blue-100' : 'bg-blue-50',
              border: isActive ? 'border-blue-600' : 'border-blue-200',
              text: 'text-blue-900',
              icon: 'text-blue-600',
              hover: 'hover:bg-blue-100 hover:border-blue-300'
            },
            orange: {
              bg: isActive ? 'bg-orange-100' : 'bg-orange-50',
              border: isActive ? 'border-orange-600' : 'border-orange-200',
              text: 'text-orange-900',
              icon: 'text-orange-600',
              hover: 'hover:bg-orange-100 hover:border-orange-300'
            }
          };

          const colors = colorClasses[counter.color as keyof typeof colorClasses];

          return (
            <button
              key={counter.id}
              onClick={() => onCounterClick(isActive ? null : counter.id)}
              className={`relative p-4 rounded-lg border-2 transition-all ${colors.bg} ${colors.border} ${colors.hover} ${
                isActive ? 'ring-2 ring-offset-2 ring-' + counter.color + '-600' : ''
              }`}
              title={counter.description}
            >
              <div className="flex items-start justify-between mb-2">
                <Icon className={`w-5 h-5 ${colors.icon}`} />
                {isActive && (
                  <div className={`w-2 h-2 rounded-full ${colors.icon.replace('text-', 'bg-')}`} />
                )}
              </div>
              <div className={`text-2xl font-bold ${colors.text} mb-1`}>
                {counter.value.toLocaleString()}
              </div>
              <div className={`text-xs font-medium ${colors.text} opacity-70`}>
                {counter.label}
              </div>
            </button>
          );
        })}
      </div>
      
      {activeCounter && (
        <div className="mt-3 text-xs text-gray-600">
          Filter applied. Click counter again to clear, or select another counter to switch filter.
        </div>
      )}
    </div>
  );
}
