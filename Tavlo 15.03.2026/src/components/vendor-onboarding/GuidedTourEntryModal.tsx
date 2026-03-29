import { X } from 'lucide-react';

interface GuidedTourEntryModalProps {
  onStart: () => void;
  onSkip: () => void;
}

export function GuidedTourEntryModal({ onStart, onSkip }: GuidedTourEntryModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div className="flex items-start justify-between mb-4">
          <div>
            <h2 className="text-2xl font-semibold text-gray-900">Set up your restaurant</h2>
          </div>
          <button
            onClick={onSkip}
            className="text-gray-400 hover:text-gray-600 transition-colors"
            aria-label="Close"
          >
            <X className="w-5 h-5" />
          </button>
        </div>
        
        <p className="text-gray-600 mb-6">
          We'll guide you through the main areas. You can do this now or later.
        </p>
        
        <div className="flex flex-col sm:flex-row gap-3">
          <button
            onClick={onStart}
            className="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors"
          >
            Start guided tour
          </button>
          <button
            onClick={onSkip}
            className="flex-1 bg-gray-100 text-gray-700 px-6 py-3 rounded-lg font-medium hover:bg-gray-200 transition-colors"
          >
            Skip for now
          </button>
        </div>
      </div>
    </div>
  );
}
