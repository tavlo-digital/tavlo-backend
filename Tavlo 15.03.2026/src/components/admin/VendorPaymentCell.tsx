import { CreditCard } from 'lucide-react';

export type PaymentStatus = 'paid' | 'trial' | 'overdue' | 'failed';

interface PaymentDetails {
  lastAttemptDate?: string;
  pspName?: string;
  errorReason?: string;
  daysOverdue?: number;
}

interface VendorPaymentCellProps {
  status: PaymentStatus;
  details?: PaymentDetails;
  onClick: () => void;
}

export function VendorPaymentCell({ status, details, onClick }: VendorPaymentCellProps) {
  const statusConfig = {
    paid: {
      label: 'Paid',
      color: 'text-green-700',
      bgColor: 'bg-green-50',
      borderColor: 'border-green-200'
    },
    trial: {
      label: 'Trial',
      color: 'text-blue-700',
      bgColor: 'bg-blue-50',
      borderColor: 'border-blue-200'
    },
    overdue: {
      label: 'Overdue',
      color: 'text-orange-700',
      bgColor: 'bg-orange-50',
      borderColor: 'border-orange-200'
    },
    failed: {
      label: 'Failed',
      color: 'text-red-700',
      bgColor: 'bg-red-50',
      borderColor: 'border-red-200'
    }
  };

  const config = statusConfig[status];

  const buildTooltip = () => {
    if (!details) return null;
    
    const lines = [];
    if (details.lastAttemptDate) lines.push(`Last attempt: ${details.lastAttemptDate}`);
    if (details.pspName) lines.push(`PSP: ${details.pspName}`);
    if (details.errorReason) lines.push(`Error: ${details.errorReason}`);
    if (details.daysOverdue) lines.push(`${details.daysOverdue} days overdue`);
    
    return lines;
  };

  const tooltipLines = buildTooltip();

  return (
    <div className="relative group">
      <button
        onClick={onClick}
        className={`px-3 py-1.5 rounded-lg border ${config.borderColor} ${config.bgColor} ${config.color} text-sm font-medium hover:ring-2 hover:ring-offset-1 hover:ring-${config.color.replace('text-', '')} transition-all inline-flex items-center gap-1.5`}
      >
        <CreditCard className="w-3.5 h-3.5" />
        {config.label}
      </button>
      
      {/* Tooltip */}
      {tooltipLines && tooltipLines.length > 0 && (
        <div className="absolute left-0 bottom-full mb-2 hidden group-hover:block z-50 pointer-events-none">
          <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
            {tooltipLines.map((line, index) => (
              <div key={index} className={index > 0 ? 'mt-1' : ''}>
                {line}
              </div>
            ))}
            <div className="absolute top-full left-6 -mt-1">
              <div className="border-4 border-transparent border-t-gray-900" />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
