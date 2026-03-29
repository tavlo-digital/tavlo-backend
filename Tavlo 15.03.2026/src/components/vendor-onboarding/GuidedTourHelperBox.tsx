import { X, ArrowRight, LucideIcon } from 'lucide-react';
import { Button } from '../ui/button';

interface GuidedTourHelperBoxProps {
  title: string;
  description: string;
  secondaryNote?: string;
  icon: LucideIcon;
  iconColor?: string;
  iconBgColor?: string;
  ctaText: string;
  onCta: () => void;
  onSkip: () => void;
  isOptional?: boolean;
  stepNumber?: number;
  totalSteps?: number;
}

export function GuidedTourHelperBox({
  title,
  description,
  secondaryNote,
  icon: Icon,
  iconColor = 'text-emerald-600',
  iconBgColor = 'bg-emerald-100',
  ctaText,
  onCta,
  onSkip,
  isOptional = false,
  stepNumber,
  totalSteps
}: GuidedTourHelperBoxProps) {
  return (
    <div className="bg-gradient-to-br from-emerald-50 to-teal-50 border-2 border-emerald-200 rounded-xl p-6 shadow-lg relative">
      {/* Close button */}
      <button
        onClick={onSkip}
        className="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
        aria-label="Skip this step"
      >
        <X className="w-5 h-5" />
      </button>

      {/* Progress indicator */}
      {stepNumber && totalSteps && (
        <div className="text-xs text-emerald-700 mb-3 font-medium">
          Step {stepNumber} of {totalSteps}
        </div>
      )}

      {/* Icon */}
      <div className={`inline-flex items-center justify-center w-12 h-12 ${iconBgColor} rounded-lg mb-4`}>
        <Icon className={`w-6 h-6 ${iconColor}`} />
      </div>

      {/* Title */}
      <h3 className="text-lg font-semibold text-gray-900 mb-2">
        {title}
        {isOptional && (
          <span className="text-sm font-normal text-gray-500 ml-2">(Optional)</span>
        )}
      </h3>

      {/* Description */}
      <p className="text-sm text-gray-700 mb-3">
        {description}
      </p>

      {/* Secondary note */}
      {secondaryNote && (
        <p className="text-xs text-gray-600 mb-4 italic">
          {secondaryNote}
        </p>
      )}

      {/* Actions */}
      <div className="flex items-center gap-3">
        <Button
          onClick={onCta}
          className="bg-emerald-600 hover:bg-emerald-700 text-white"
        >
          {ctaText}
          <ArrowRight className="w-4 h-4 ml-2" />
        </Button>
        <button
          onClick={onSkip}
          className="text-sm text-gray-600 hover:text-gray-900"
        >
          Skip
        </button>
      </div>
    </div>
  );
}
