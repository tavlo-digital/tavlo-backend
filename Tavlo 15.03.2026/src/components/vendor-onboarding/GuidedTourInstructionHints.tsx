import { useState } from 'react';
import { X, ChevronRight } from 'lucide-react';
import type { TourStep } from './GuidedTourState';

interface Hint {
  text: string;
  description?: string;
}

interface GuidedTourInstructionHintsProps {
  step: TourStep;
  onComplete: () => void;
  onEndTour: () => void;
}

const STEP_HINTS: Record<TourStep, Hint[]> = {
  'appearance': [
    { text: 'Upload your logo', description: 'Add your restaurant logo to build brand recognition' },
    { text: 'Upload a cover image', description: 'Choose an attractive cover image for your menu' },
    { text: 'Choose brand colors', description: 'Select colors that match your restaurant\'s identity' }
  ],
  'menu': [
    { text: 'Add items manually', description: 'Click "Add Item" to create menu items one by one' },
    { text: 'Or upload your menu using Excel', description: 'Use bulk upload for faster setup' }
  ],
  'inventory': [
    { text: 'Add ingredients', description: 'Track ingredients used in your menu items' },
    { text: 'Set stock levels', description: 'Items become unavailable when ingredients run out' }
  ],
  'qr-codes': [
    { text: 'Generate QR codes', description: 'Create unique QR codes for each table' },
    { text: 'QR codes activate only after go live', description: 'Codes will work once your restaurant is live' }
  ],
  'payments': [
    { text: 'Online payments are optional', description: 'Enable Stripe for digital payments' },
    { text: 'Pay-at-restaurant works without setup', description: 'Customers can always pay in person' }
  ],
  'go-live': [
    { text: 'Ready to go live?', description: 'Enable visibility to make your restaurant discoverable on Tavlo' },
    { text: 'You can toggle this anytime', description: 'Control your restaurant\'s visibility in Settings → Restaurant Profile' }
  ]
};

export function GuidedTourInstructionHints({ step, onComplete, onEndTour }: GuidedTourInstructionHintsProps) {
  const [currentHintIndex, setCurrentHintIndex] = useState(0);
  const hints = STEP_HINTS[step] || [];
  const currentHint = hints[currentHintIndex];
  const isLastHint = currentHintIndex === hints.length - 1;

  if (!currentHint) return null;

  const handleNext = () => {
    if (isLastHint) {
      onComplete();
    } else {
      setCurrentHintIndex(currentHintIndex + 1);
    }
  };

  return (
    <div className="fixed top-6 right-6 bg-white rounded-lg shadow-xl border border-gray-200 p-4 max-w-sm z-50 animate-in slide-in-from-top duration-300">
      <div className="flex items-start justify-between mb-3">
        <div className="flex-1">
          <div className="flex items-center gap-2 mb-1">
            <div className="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded">
              Step {currentHintIndex + 1} of {hints.length}
            </div>
          </div>
          <h3 className="font-semibold text-gray-900">{currentHint.text}</h3>
          {currentHint.description && (
            <p className="text-sm text-gray-600 mt-1">{currentHint.description}</p>
          )}
        </div>
        <button
          onClick={onEndTour}
          className="text-gray-400 hover:text-gray-600 transition-colors ml-2"
          aria-label="End tour"
        >
          <X className="w-4 h-4" />
        </button>
      </div>
      
      <div className="flex items-center justify-between mt-4">
        <button
          onClick={onEndTour}
          className="text-sm text-gray-500 hover:text-gray-700 transition-colors"
        >
          End tour
        </button>
        <button
          onClick={handleNext}
          className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors flex items-center gap-1"
        >
          {isLastHint ? 'Complete' : 'Next'}
          <ChevronRight className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}