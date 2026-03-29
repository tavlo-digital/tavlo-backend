import { Rocket, X } from 'lucide-react';

interface LiveModeBannerProps {
  onDismiss?: () => void;
}

export function LiveModeBanner({ onDismiss }: LiveModeBannerProps) {
  return (
    <div className="bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-4 py-3">
      <div className="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <Rocket className="w-5 h-5 flex-shrink-0" />
          <div>
            <p className="text-sm">
              <strong>🎉 Your restaurant is now live!</strong>
            </p>
            <p className="text-sm opacity-90">
              Customers can now scan your QR codes and place orders.
            </p>
          </div>
        </div>
        {onDismiss && (
          <button
            onClick={onDismiss}
            className="p-1 hover:bg-white/20 rounded transition-colors"
            aria-label="Dismiss"
          >
            <X className="w-5 h-5" />
          </button>
        )}
      </div>
    </div>
  );
}