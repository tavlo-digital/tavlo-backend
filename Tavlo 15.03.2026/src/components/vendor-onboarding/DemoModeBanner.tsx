import { Lock, AlertCircle, CheckCircle } from 'lucide-react';
import { Button } from '../ui/button';

interface DemoModeBannerProps {
  status: 'demo' | 'activated' | 'live';
  onActivateClick: () => void;
}

export function DemoModeBanner({ status, onActivateClick }: DemoModeBannerProps) {
  if (status === 'live') return null;

  const bannerConfig = {
    demo: {
      icon: Lock,
      iconEmoji: '🔒',
      text: 'Your restaurant is not live',
      subtext: "You're viewing Tavlo in demo mode. Complete setup and activate your subscription to start accepting orders.",
      buttonText: 'Activate restaurant',
      secondaryButtonText: 'View setup checklist',
      bgColor: 'bg-amber-50',
      borderColor: 'border-amber-200',
      textColor: 'text-amber-900',
      subtextColor: 'text-amber-700',
      buttonColor: 'bg-emerald-600 hover:bg-emerald-700'
    },
    'activated': {
      icon: AlertCircle,
      iconEmoji: '⚠️',
      text: 'Activation required to go live',
      subtext: 'Customers cannot place orders until setup is completed.',
      reassurance: 'Your restaurant is not visible to customers yet.',
      buttonText: 'Complete setup',
      bgColor: 'bg-blue-50',
      borderColor: 'border-blue-200',
      textColor: 'text-blue-900',
      subtextColor: 'text-blue-700',
      reassuranceColor: 'text-blue-600',
      buttonColor: 'bg-blue-600 hover:bg-blue-700'
    }
  };

  const config = bannerConfig[status];
  const Icon = config.icon;

  return (
    <div className={`${config.bgColor} border-b ${config.borderColor} px-6 py-4`}>
      <div className="max-w-7xl mx-auto">
        <div className="flex items-center justify-between gap-4 flex-wrap">
          <div className="flex items-center gap-3">
            <span className="text-2xl">{config.iconEmoji}</span>
            <div>
              <div className={`font-medium ${config.textColor}`}>
                {config.text}
              </div>
              <div className={`text-sm ${config.subtextColor}`}>
                {config.subtext}
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Button
              onClick={onActivateClick}
              className={`${config.buttonColor} text-white shrink-0`}
            >
              {config.buttonText}
            </Button>
          </div>
        </div>
        
        {/* Reassurance text */}
        {config.reassurance && (
          <div className={`mt-3 text-sm italic ${config.reassuranceColor} ml-11`}>
            {config.reassurance}
          </div>
        )}
      </div>
    </div>
  );
}