import { X, LucideIcon } from 'lucide-react';
import type { TourStep } from './GuidedTourState';

interface GuidedTourNavigationBoxProps {
  step: TourStep;
  title: string;
  description: string;
  icon: LucideIcon;
  iconColor: string;
  iconBgColor: string;
  ctaText: string;
  onNavigate: () => void;
  onSkip: () => void;
  onEndTour: () => void;
  isOptional?: boolean;
  stepNumber: number;
  totalSteps: number;
}

export function GuidedTourNavigationBox({
  step,
  title,
  description,
  icon: Icon,
  iconColor,
  iconBgColor,
  ctaText,
  onNavigate,
  onSkip,
  onEndTour,
  isOptional = false,
  stepNumber,
  totalSteps
}: GuidedTourNavigationBoxProps) {
  return (
    <div className="fixed top-6 left-1/2 transform -translate-x-1/2 bg-white rounded-lg shadow-xl border border-gray-200 p-6 max-w-lg w-full z-50 animate-in slide-in-from-top duration-300">
      <div className="flex items-start gap-4">
        <div className={`${iconBgColor} rounded-lg p-3 flex-shrink-0`}>
          <Icon className={`w-6 h-6 ${iconColor}`} />
        </div>
        
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between mb-2">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                <span className="text-xs font-semibold text-gray-500">
                  Step {stepNumber} of {totalSteps}
                </span>
                {isOptional && (
                  <span className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                    Optional
                  </span>
                )}
              </div>
              <h3 className="text-lg font-semibold text-gray-900 mb-1">{title}</h3>
              <p className="text-sm text-gray-600">{description}</p>
            </div>
            <button
              onClick={onEndTour}
              className="text-gray-400 hover:text-gray-600 transition-colors ml-2"
              aria-label="End tour"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
          
          <div className="flex items-center gap-3 mt-4">
            <button
              onClick={onNavigate}
              className="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
            >
              {ctaText}
            </button>
            <button
              onClick={onSkip}
              className="text-sm text-gray-600 hover:text-gray-800 transition-colors"
            >
              Skip step
            </button>
            <button
              onClick={onEndTour}
              className="text-sm text-gray-500 hover:text-gray-700 transition-colors ml-auto"
            >
              End tour
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
