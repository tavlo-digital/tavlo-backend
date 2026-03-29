import { LucideIcon } from 'lucide-react';

type EventType = 'payment' | 'vendor' | 'content' | 'review' | 'system';

interface ActivityFeedItemProps {
  type: EventType;
  icon: LucideIcon;
  title: string;
  description: string;
  timestamp: string;
  entityId?: string;
  onClick?: () => void;
}

export function ActivityFeedItem_v1_1({
  type,
  icon: Icon,
  title,
  description,
  timestamp,
  entityId,
  onClick,
}: ActivityFeedItemProps) {
  // Color-code by category
  const typeColors = {
    payment: 'bg-green-100 text-green-600',
    vendor: 'bg-purple-100 text-purple-600',
    content: 'bg-orange-100 text-orange-600',
    review: 'bg-pink-100 text-pink-600',
    system: 'bg-gray-100 text-gray-600',
  };

  return (
    <button
      onClick={onClick}
      disabled={!onClick}
      className={`w-full text-left p-3 hover:bg-gray-50 transition-colors border-l-2 ${
        onClick ? 'cursor-pointer' : 'cursor-default'
      } border-gray-200`}
    >
      <div className="flex items-start gap-3">
        <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${typeColors[type]}`}>
          <Icon className="w-4 h-4" />
        </div>
        
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2 mb-1">
            <h4 className="font-medium text-sm text-gray-900">{title}</h4>
            <span className="text-xs text-gray-500 flex-shrink-0">{timestamp}</span>
          </div>
          
          <p className="text-sm text-gray-600 mb-1">{description}</p>
          
          {entityId && (
            <span className="text-xs text-gray-500 font-mono">
              ID: {entityId}
            </span>
          )}
        </div>
      </div>
    </button>
  );
}
