import { AlertCircle, AlertTriangle, Info, X, CheckCircle, Clock, UserCircle } from 'lucide-react';
import { Button } from '../ui/button';

type Severity = 'critical' | 'warning' | 'info';

interface AlertCardProps {
  severity: Severity;
  title: string; // What happened
  affectedEntity: string; // Affected system/vendor
  impact?: string; // Impact if unresolved
  timestamp: string;
  openFor: string; // New: "8 min" or "2 hours"
  assignedTo?: string; // New: Admin name or undefined for "Unassigned"
  actionLabel?: string;
  onAction?: () => void;
  onDismiss?: () => void;
  onResolve?: () => void;
}

export function AlertCard_v1_1({
  severity,
  title,
  affectedEntity,
  impact,
  timestamp,
  openFor,
  assignedTo,
  actionLabel,
  onAction,
  onDismiss,
  onResolve,
}: AlertCardProps) {
  const severityConfig = {
    critical: {
      icon: AlertCircle,
      bgColor: 'bg-red-50',
      borderColor: 'border-red-200',
      badgeColor: 'bg-red-600 text-white',
      iconColor: 'text-red-600',
    },
    warning: {
      icon: AlertTriangle,
      bgColor: 'bg-orange-50',
      borderColor: 'border-orange-200',
      badgeColor: 'bg-orange-600 text-white',
      iconColor: 'text-orange-600',
    },
    info: {
      icon: Info,
      bgColor: 'bg-blue-50',
      borderColor: 'border-blue-200',
      badgeColor: 'bg-blue-600 text-white',
      iconColor: 'text-blue-600',
    },
  };

  const config = severityConfig[severity];
  const Icon = config.icon;

  return (
    <div className={`${config.bgColor} border ${config.borderColor} rounded-lg p-4`}>
      <div className="flex items-start gap-3">
        <div className={`mt-0.5 ${config.iconColor}`}>
          <Icon className="w-5 h-5" />
        </div>
        
        <div className="flex-1 min-w-0">
          {/* Header Row */}
          <div className="flex items-start justify-between gap-3 mb-2">
            <div className="flex items-center gap-2 flex-wrap">
              <h3 className="font-medium text-gray-900">{title}</h3>
              <span className={`px-2 py-0.5 rounded text-xs font-medium ${config.badgeColor} uppercase`}>
                {severity}
              </span>
            </div>
            
            {onDismiss && (
              <button
                onClick={onDismiss}
                className="text-gray-400 hover:text-gray-600 transition-colors flex-shrink-0"
                aria-label="Dismiss alert"
              >
                <X className="w-4 h-4" />
              </button>
            )}
          </div>
          
          {/* Alert Details */}
          <div className="text-sm space-y-1 mb-3">
            <div className="text-gray-700">
              <span className="font-medium">Affected:</span> {affectedEntity}
            </div>
            {impact && (
              <div className="text-gray-600">
                <span className="font-medium">Impact:</span> {impact}
              </div>
            )}
          </div>
          
          {/* Metadata Row */}
          <div className="flex items-center gap-4 text-xs text-gray-500 mb-3">
            <div className="flex items-center gap-1">
              <Clock className="w-3 h-3" />
              <span>Open for: {openFor}</span>
            </div>
            <div className="flex items-center gap-1">
              <UserCircle className="w-3 h-3" />
              <span>{assignedTo || 'Unassigned'}</span>
            </div>
            <span>{timestamp}</span>
          </div>
          
          {/* Actions */}
          <div className="flex items-center gap-2">
            {onResolve && (
              <Button
                size="sm"
                variant="outline"
                onClick={onResolve}
                className="text-xs h-7"
              >
                <CheckCircle className="w-3 h-3 mr-1" />
                Mark Resolved
              </Button>
            )}
            
            {onAction && actionLabel && (
              <Button
                size="sm"
                onClick={onAction}
                className="text-xs h-7 bg-gray-900 hover:bg-gray-800"
              >
                {actionLabel}
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
