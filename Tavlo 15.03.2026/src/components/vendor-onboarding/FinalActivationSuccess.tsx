import { CheckCircle, Rocket } from 'lucide-react';
import { Button } from '../ui/button';

interface FinalActivationSuccessProps {
  onGoToDashboard: () => void;
  restaurantName: string;
}

export function FinalActivationSuccess({ onGoToDashboard, restaurantName }: FinalActivationSuccessProps) {
  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full text-center">
        {/* Success Animation */}
        <div className="inline-flex items-center justify-center w-24 h-24 bg-emerald-100 rounded-full mb-6">
          <Rocket className="w-12 h-12 text-emerald-600" />
        </div>

        {/* Main Message */}
        <h1 className="text-4xl mb-3 text-gray-900">
          🎉 Your restaurant is ready
        </h1>
        <p className="text-xl text-gray-600 mb-8">
          {restaurantName} is now live on Tavlo!
        </p>

        {/* Completion Checklist */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-8 text-left">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4 text-center">
            Setup Complete
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
              <span className="text-gray-700">Restaurant basics configured</span>
            </div>
            <div className="flex items-center gap-3">
              <CheckCircle className="w-5 h-5 text-emerald-600 flex-shrink-0" />
              <span className="text-gray-700">Menu added</span>
            </div>
            <div className="flex items-center gap-3">
              <CheckCircle className="w-5 h-5 text-emerald-600 flex-shrink-0" />
              <span className="text-gray-700">QR codes ready</span>
            </div>
          </div>
        </div>

        {/* What's Next */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8 text-left">
          <p className="text-sm text-blue-900 mb-2">
            <strong>What you can do now:</strong>
          </p>
          <ul className="text-sm text-blue-800 space-y-1 ml-4">
            <li className="list-disc">View and manage orders in real-time</li>
            <li className="list-disc">Download QR codes for your tables</li>
            <li className="list-disc">Monitor analytics and revenue</li>
            <li className="list-disc">Customize your menu anytime</li>
          </ul>
        </div>

        {/* CTA */}
        <Button
          onClick={onGoToDashboard}
          className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-4 text-lg"
        >
          <Rocket className="w-5 h-5 mr-2" />
          Go to dashboard
        </Button>

        {/* Welcome Message */}
        <p className="text-sm text-gray-600 mt-6">
          Welcome to Tavlo! We're excited to have you on board.
        </p>
      </div>
    </div>
  );
}
