import { CheckCircle, Rocket, Eye } from 'lucide-react';
import { Button } from '../ui/button';

interface GoLiveConfirmationProps {
  onGoLive: () => void;
  onPreview: () => void;
  restaurantName: string;
}

export function GoLiveConfirmation({ onGoLive, onPreview, restaurantName }: GoLiveConfirmationProps) {
  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full text-center">
        {/* Success Icon */}
        <div className="inline-flex items-center justify-center w-24 h-24 bg-emerald-100 rounded-full mb-6">
          <Rocket className="w-12 h-12 text-emerald-600" />
        </div>

        {/* Headline */}
        <h1 className="text-4xl mb-3 text-gray-900">
          🎉 Your restaurant is ready to go live
        </h1>

        {/* Subtext */}
        <p className="text-lg text-gray-600 mb-8">
          {restaurantName} is fully set up and ready to start accepting orders.
        </p>

        {/* Completion Checklist */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-8 text-left">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4 text-center">
            Activation Complete
          </h3>
          <div className="space-y-3">
            <div className="flex items-center gap-3">
              <CheckCircle className="w-5 h-5 text-emerald-600 flex-shrink-0" />
              <span className="text-gray-700">Subscription active</span>
            </div>
            <div className="flex items-center gap-3">
              <CheckCircle className="w-5 h-5 text-emerald-600 flex-shrink-0" />
              <span className="text-gray-700">Legal info completed</span>
            </div>
            <div className="flex items-center gap-3">
              <CheckCircle className="w-5 h-5 text-emerald-600 flex-shrink-0" />
              <span className="text-gray-700">Menu created</span>
            </div>
            <div className="flex items-center gap-3">
              <CheckCircle className="w-5 h-5 text-emerald-600 flex-shrink-0" />
              <span className="text-gray-700">QR codes generated</span>
            </div>
          </div>
        </div>

        {/* Primary CTA */}
        <Button
          onClick={onGoLive}
          className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-4 text-lg mb-3"
        >
          <Rocket className="w-5 h-5 mr-2" />
          Go live
        </Button>

        {/* Secondary CTA */}
        <button
          onClick={onPreview}
          className="text-sm text-gray-600 hover:text-gray-900 flex items-center justify-center gap-2 mx-auto"
        >
          <Eye className="w-4 h-4" />
          Preview restaurant as customer
        </button>
      </div>
    </div>
  );
}
