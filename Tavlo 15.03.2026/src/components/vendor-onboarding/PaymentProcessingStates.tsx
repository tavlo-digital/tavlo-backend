import { Loader2, XCircle, AlertCircle } from 'lucide-react';
import { Button } from '../ui/button';

interface PaymentLoadingProps {
  message?: string;
}

export function PaymentLoading({ message = 'Processing payment securely...' }: PaymentLoadingProps) {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="text-center max-w-md">
        <Loader2 className="w-16 h-16 text-emerald-600 animate-spin mx-auto mb-6" />
        <h2 className="text-2xl text-gray-900 mb-2">Processing Payment</h2>
        <p className="text-gray-600">{message}</p>
        <p className="text-sm text-gray-500 mt-4">Please do not close this window</p>
      </div>
    </div>
  );
}

interface PaymentFailedProps {
  error?: string;
  onRetry: () => void;
  onChangeMethod: () => void;
  onBackToPlans: () => void;
}

export function PaymentFailed({ 
  error,
  onRetry, 
  onChangeMethod, 
  onBackToPlans 
}: PaymentFailedProps) {
  const possibleReasons = [
    'Insufficient funds',
    'Card declined by issuer',
    'Authentication failed',
    'Invalid card details',
    'Network error during transaction'
  ];

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full">
        {/* Error Icon */}
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
            <XCircle className="w-8 h-8 text-red-600" />
          </div>
          <h2 className="text-2xl text-gray-900 mb-2">Payment failed</h2>
          <p className="text-gray-600">
            Your payment could not be completed. No charges were made.
          </p>
        </div>

        {/* Reassurance Block */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <p className="text-sm text-blue-900 mb-1">
            <strong>Your restaurant is still in demo mode.</strong>
          </p>
          <p className="text-sm text-blue-800">
            No changes were made and no charges were applied.
          </p>
        </div>

        {/* Error Details */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div className="flex items-start gap-3">
              <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
              <div>
                <p className="text-sm text-red-900 mb-1">Error details:</p>
                <p className="text-sm text-red-700">{error}</p>
              </div>
            </div>
          </div>
        )}

        {/* Possible Reasons */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4">
            Possible reasons
          </h3>
          <ul className="space-y-2">
            {possibleReasons.map((reason, index) => (
              <li key={index} className="flex items-center gap-3 text-sm text-gray-700">
                <span className="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                {reason}
              </li>
            ))}
          </ul>
        </div>

        {/* Actions */}
        <div className="space-y-3">
          <Button
            onClick={onRetry}
            className="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3"
          >
            Retry payment
          </Button>
          
          <Button
            onClick={onChangeMethod}
            className="w-full bg-gray-100 hover:bg-gray-200 text-gray-900 py-3"
          >
            Change payment method
          </Button>

          <button
            onClick={onBackToPlans}
            className="w-full text-sm text-gray-600 hover:text-gray-900 py-2"
          >
            Back to plans
          </button>
        </div>

        {/* Helper Note */}
        <div className="mt-6 text-center">
          <p className="text-sm text-gray-600 mb-2">
            You can retry anytime. Your demo access remains available.
          </p>
          <p className="text-sm text-gray-600">
            Need help?{' '}
            <a href="mailto:support@tavlo.com" className="text-emerald-600 hover:text-emerald-700">
              Contact support
            </a>
          </p>
        </div>
      </div>
    </div>
  );
}

interface PaymentSuccessProps {
  planName: string;
  onContinueSetup: () => void;
}

export function PaymentSuccess({ planName, onContinueSetup }: PaymentSuccessProps) {
  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full text-center">
        {/* Success Animation */}
        <div className="inline-flex items-center justify-center w-20 h-20 bg-emerald-100 rounded-full mb-6">
          <div className="w-16 h-16 bg-emerald-500 rounded-full flex items-center justify-center">
            <svg className="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>

        {/* Success Message */}
        <h2 className="text-3xl mb-2 text-gray-900">Subscription activated</h2>
        <p className="text-xl text-gray-600 mb-8">
          Your {planName} plan is active. Let's finish setting up your restaurant.
        </p>

        {/* What's Next Box */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-8 text-left">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4 text-center">
            Next steps
          </h3>
          <div className="space-y-3">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                <span className="text-sm text-emerald-700">1</span>
              </div>
              <p className="text-gray-700">Add legal & tax information</p>
            </div>
          </div>
        </div>

        {/* CTA */}
        <Button
          onClick={onContinueSetup}
          className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-4 text-lg"
        >
          Continue setup
        </Button>

        <p className="text-sm text-gray-500 mt-4">
          This will take approximately 5-10 minutes
        </p>
      </div>
    </div>
  );
}