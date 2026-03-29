import { AlertCircle, Rocket } from 'lucide-react';
import { Button } from '../ui/button';

interface ActivatedStateBannerProps {
  onGoLive: () => void;
  canGoLive: boolean;
  missingRequirements?: string[];
}

export function ActivatedStateBanner({ onGoLive, canGoLive, missingRequirements = [] }: ActivatedStateBannerProps) {
  return (
    <div className="bg-amber-50 border-b border-amber-200">
      <div className="max-w-7xl mx-auto px-4 py-4">
        <div className="flex items-start gap-4">
          <div className="flex-shrink-0">
            <AlertCircle className="w-6 h-6 text-amber-600 mt-0.5" />
          </div>
          
          <div className="flex-1 min-w-0">
            <h3 className="text-base font-semibold text-amber-900 mb-1">
              Your restaurant is not live yet
            </h3>
            <p className="text-sm text-amber-800">
              Configure your restaurant when ready. Customers cannot see or order from your restaurant yet.
            </p>
            
            {!canGoLive && missingRequirements.length > 0 && (
              <div className="mt-2">
                <p className="text-xs text-amber-700 mb-1">To go live, you need:</p>
                <ul className="text-xs text-amber-700 list-disc list-inside space-y-0.5">
                  {missingRequirements.map((req, idx) => (
                    <li key={idx}>{req}</li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          <div className="flex-shrink-0">
            <Button
              onClick={onGoLive}
              disabled={!canGoLive}
              className="bg-emerald-600 hover:bg-emerald-700 text-white disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Rocket className="w-4 h-4 mr-2" />
              Go live
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
