import { Lock } from 'lucide-react';

interface LockedFeatureOverlayProps {
  message?: string;
  onClick?: () => void;
}

export function LockedFeatureOverlay({ 
  message = 'Available after activation',
  onClick 
}: LockedFeatureOverlayProps) {
  return (
    <div 
      className="absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center z-10 cursor-pointer group"
      onClick={onClick}
    >
      <div className="text-center p-6">
        <div className="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-3 group-hover:bg-gray-200 transition-colors">
          <Lock className="w-8 h-8 text-gray-600" />
        </div>
        <p className="text-sm text-gray-700 font-medium">
          {message}
        </p>
        <p className="text-xs text-gray-500 mt-1">
          Click to view activation steps
        </p>
      </div>
    </div>
  );
}

interface LockedButtonProps {
  onClick?: () => void;
  children: React.ReactNode;
  className?: string;
}

export function LockedButton({ onClick, children, className = '' }: LockedButtonProps) {
  return (
    <button
      onClick={onClick}
      disabled
      className={`relative ${className} opacity-50 cursor-not-allowed`}
      title="Available after activation"
    >
      {children}
      <Lock className="w-4 h-4 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-gray-500" />
    </button>
  );
}
