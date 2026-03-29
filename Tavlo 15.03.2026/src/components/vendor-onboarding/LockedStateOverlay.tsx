import { Lock, AlertCircle } from 'lucide-react';
import { Button } from '../ui/button';

interface LockedStateOverlayProps {
  isLocked: boolean;
  children: React.ReactNode;
  onActivateClick?: () => void;
}

export function LockedStateOverlay({ isLocked, children, onActivateClick }: LockedStateOverlayProps) {
  if (!isLocked) {
    return <>{children}</>;
  }

  return (
    <div className="relative">
      {/* Grayed out content */}
      <div className="pointer-events-none opacity-40 select-none">
        {children}
      </div>

      {/* Overlay message */}
      <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
        <div className="bg-white border-2 border-blue-200 rounded-xl shadow-lg p-6 max-w-md pointer-events-auto">
          <div className="text-center">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
              <Lock className="w-8 h-8 text-blue-600" />
            </div>
            <h3 className="text-xl mb-2 text-gray-900">
              Complete activation to access this feature
            </h3>
            <p className="text-sm text-gray-600 mb-5">
              This is a preview with dummy data. Complete your restaurant setup to unlock full functionality.
            </p>
            {onActivateClick && (
              <Button
                onClick={onActivateClick}
                className="bg-blue-600 hover:bg-blue-700 text-white"
              >
                Complete setup
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
