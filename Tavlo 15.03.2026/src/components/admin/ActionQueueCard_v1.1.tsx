import { LucideIcon, ChevronRight, AlertCircle } from 'lucide-react';

type Priority = 'high' | 'medium' | 'low';

interface ActionQueueCardProps {
  title: string;
  count: number;
  description: string;
  icon: LucideIcon;
  onClick: () => void;
  priority?: Priority; // New: Priority indicator
  oldestItem?: string; // New: "2d 4h" aging information
}

export function ActionQueueCard_v1_1({
  title,
  count,
  description,
  icon: Icon,
  onClick,
  priority = 'medium',
  oldestItem,
}: ActionQueueCardProps) {
  const isUrgent = priority === 'high' || count > 5;

  const priorityColors = {
    high: 'text-red-600',
    medium: 'text-orange-600',
    low: 'text-gray-600',
  };

  const priorityLabels = {
    high: 'High',
    medium: 'Medium',
    low: 'Low',
  };

  return (
    <button
      onClick={onClick}
      className={`w-full bg-white border rounded-lg p-4 hover:shadow-sm transition-all text-left group ${
        isUrgent && count > 5 ? 'border-orange-200 bg-orange-50/50' : 'border-gray-200'
      }`}
    >
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-3 flex-1">
          <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${
            isUrgent && count > 5 ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-gray-600'
          }`}>
            <Icon className="w-5 h-5" />
          </div>
          
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-1">
              <h3 className="font-medium text-gray-900">{title}</h3>
              {count > 0 && (
                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                  count > 5 ? 'bg-orange-600 text-white' : 'bg-gray-600 text-white'
                }`}>
                  {count}
                </span>
              )}
            </div>
            <p className="text-sm text-gray-600">{description}</p>
          </div>
        </div>
        
        <ChevronRight className="w-5 h-5 text-gray-400 group-hover:text-gray-600 group-hover:translate-x-1 transition-all flex-shrink-0" />
      </div>
      
      {/* Metadata Row */}
      <div className="flex items-center gap-4 text-xs">
        <div className="flex items-center gap-1">
          <AlertCircle className={`w-3 h-3 ${priorityColors[priority]}`} />
          <span className={`${priorityColors[priority]} font-medium`}>
            {priorityLabels[priority]} priority
          </span>
        </div>
        {oldestItem && count > 0 && (
          <span className="text-gray-500">
            Oldest: {oldestItem}
          </span>
        )}
      </div>
    </button>
  );
}
