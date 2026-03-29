import { CheckCircle, ArrowRight, Home } from 'lucide-react';
import { Button } from '../ui/button';

interface ActivationStartedProps {
  onStartActivation: () => void;
  onBackToDashboard: () => void;
}

export function ActivationStarted({ onStartActivation, onBackToDashboard }: ActivationStartedProps) {
  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full text-center">
        {/* Success Icon */}
        <div className="inline-flex items-center justify-center w-20 h-20 bg-emerald-100 rounded-full mb-6">
          <CheckCircle className="w-10 h-10 text-emerald-600" />
        </div>

        {/* Headline */}
        <h1 className="text-3xl mb-3 text-gray-900">
          Your subscription is active
        </h1>

        {/* Subtext */}
        <p className="text-lg text-gray-600 mb-8">
          Complete the next steps to make your restaurant live and start accepting orders.
        </p>

        {/* Checklist Preview */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-8 text-left">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4 text-center">
            What's next
          </h3>
          <div className="space-y-3">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full border-2 border-gray-300 flex items-center justify-center flex-shrink-0">
                <span className="text-sm text-gray-500">1</span>
              </div>
              <span className="text-gray-700">Legal & tax info</span>
            </div>
          </div>
        </div>

        {/* Primary CTA */}
        <Button
          onClick={onStartActivation}
          className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-4 text-lg mb-3"
        >
          Start activation
          <ArrowRight className="w-5 h-5 ml-2" />
        </Button>

        {/* Secondary CTA */}
        <button
          onClick={onBackToDashboard}
          className="text-sm text-gray-600 hover:text-gray-900"
        >
          <Home className="w-4 h-4 inline mr-1" />
          Back to dashboard
        </button>
      </div>
    </div>
  );
}