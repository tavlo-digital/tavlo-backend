import { LucideIcon } from 'lucide-react';

interface KPICardProps {
  label: string;
  value: string | number;
  icon: LucideIcon;
  variant?: 'default' | 'critical' | 'warning';
  onClick?: () => void;
  subtitle?: string;
  comparison?: string; // New: "vs yesterday" or "7-day avg"
}

export function KPICard_v1_1({ 
  label, 
  value, 
  icon: Icon, 
  variant = 'default', 
  onClick, 
  subtitle,
  comparison 
}: KPICardProps) {
  const variantStyles = {
    default: 'border-gray-200 hover:border-gray-300 hover:shadow-sm',
    critical: 'border-red-300 bg-red-50 hover:border-red-400 hover:shadow-sm',
    warning: 'border-orange-300 bg-orange-50 hover:border-orange-400 hover:shadow-sm',
  };

  const iconStyles = {
    default: 'bg-gray-100 text-gray-600',
    critical: 'bg-red-100 text-red-600',
    warning: 'bg-orange-100 text-orange-600',
  };

  const valueStyles = {
    default: 'text-gray-900',
    critical: 'text-red-600',
    warning: 'text-orange-600',
  };

  return (
    <button
      onClick={onClick}
      className={`w-full bg-white border rounded-lg p-4 transition-all text-left ${variantStyles[variant]} ${onClick ? 'cursor-pointer' : 'cursor-default'}`}
    >
      <div className="flex items-start justify-between mb-3">
        <div className={`w-9 h-9 rounded-lg flex items-center justify-center ${iconStyles[variant]}`}>
          <Icon className="w-5 h-5" />
        </div>
      </div>
      <div className={`text-2xl font-semibold mb-1 ${valueStyles[variant]}`}>
        {value}
      </div>
      <div className="text-sm text-gray-600">
        {label}
      </div>
      {subtitle && (
        <div className="text-xs text-gray-500 mt-2">
          {subtitle}
        </div>
      )}
      {comparison && (
        <div className="text-xs text-gray-400 mt-1">
          {comparison}
        </div>
      )}
    </button>
  );
}
