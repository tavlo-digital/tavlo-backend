import { useState, useEffect } from 'react';
import { X, Info } from 'lucide-react';
import { Button } from '../ui/button';

interface ContextualHelperProps {
  sectionId: string;
  title: string;
  description: string;
  importance?: string;
  vendorId: string;
}

export function ContextualHelper({ sectionId, title, description, importance, vendorId }: ContextualHelperProps) {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    // Check if this helper has been dismissed before
    const storageKey = `helper-dismissed-${vendorId}-${sectionId}`;
    const isDismissed = localStorage.getItem(storageKey);
    
    if (!isDismissed) {
      setIsVisible(true);
    }
  }, [vendorId, sectionId]);

  const handleDismiss = () => {
    const storageKey = `helper-dismissed-${vendorId}-${sectionId}`;
    localStorage.setItem(storageKey, 'true');
    setIsVisible(false);
  };

  if (!isVisible) return null;

  return (
    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
      <div className="flex items-start gap-3">
        <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
          <Info className="w-4 h-4 text-blue-600" />
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="font-medium text-blue-900 mb-1">{title}</h3>
          <p className="text-sm text-blue-800 mb-2">{description}</p>
          {importance && (
            <p className="text-sm text-blue-700 italic">{importance}</p>
          )}
          <p className="text-xs text-blue-600 mt-2">
            💡 You can complete this later if needed.
          </p>
        </div>
        <button
          onClick={handleDismiss}
          className="flex-shrink-0 p-1 hover:bg-blue-100 rounded"
          aria-label="Dismiss helper"
        >
          <X className="w-4 h-4 text-blue-600" />
        </button>
      </div>
    </div>
  );
}
