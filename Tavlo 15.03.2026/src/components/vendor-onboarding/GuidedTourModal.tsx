import { Rocket, X } from 'lucide-react';
import { Button } from '../ui/button';
import { TourStep } from './GuidedTourManager';

interface GuidedTourModalProps {
  isOpen: boolean;
  onClose: () => void;
  vendorId: string;
  currentStep: TourStep | null;
  setCurrentStep: (step: TourStep | null) => void;
}

export function GuidedTourModal({ isOpen, onClose, vendorId, currentStep, setCurrentStep }: GuidedTourModalProps) {
  const handleStartTour = () => {
    sessionStorage.setItem(`vendor-${vendorId}-guided-tour-seen`, 'true');
    sessionStorage.setItem(`vendor-${vendorId}-guided-tour-active`, 'true');
    sessionStorage.setItem(`vendor-${vendorId}-guided-tour-step`, 'appearance');
    setCurrentStep('appearance');
    onClose();
  };

  const handleSkip = () => {
    sessionStorage.setItem(`vendor-${vendorId}-guided-tour-seen`, 'true');
    sessionStorage.setItem(`vendor-${vendorId}-guided-tour-active`, 'false');
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-md w-full p-6 relative">
        {/* Close button */}
        <button
          onClick={handleSkip}
          className="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Icon */}
        <div className="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
          <Rocket className="w-8 h-8 text-emerald-600" />
        </div>

        {/* Title */}
        <h2 className="text-2xl mb-2 text-gray-900">
          Let's set up your restaurant
        </h2>

        {/* Description */}
        <p className="text-gray-600 mb-6">
          We'll guide you through the main areas. You can do this now or later.
        </p>

        {/* Buttons */}
        <div className="space-y-3">
          <Button
            onClick={handleStartTour}
            className="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3"
          >
            Start guided tour
          </Button>
          <button
            onClick={handleSkip}
            className="w-full text-gray-600 hover:text-gray-900 py-2"
          >
            Skip for now
          </button>
        </div>
      </div>
    </div>
  );
}