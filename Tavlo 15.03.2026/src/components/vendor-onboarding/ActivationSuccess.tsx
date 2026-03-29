import { CheckCircle, ArrowRight } from 'lucide-react';
import { Button } from '../ui/button';

interface ActivationSuccessProps {
  onContinueSetup: () => void;
}

export function ActivationSuccess({ onContinueSetup }: ActivationSuccessProps) {
  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full text-center">
        {/* Success Icon */}
        <div className="inline-flex items-center justify-center w-24 h-24 bg-emerald-100 rounded-full mb-6">
          <CheckCircle className="w-12 h-12 text-emerald-600" />
        </div>

        {/* Headline */}
        <h1 className="text-4xl mb-3 text-gray-900">
          Subscription activated 🎉
        </h1>

        {/* Description */}
        <p className="text-xl text-gray-600 mb-8">
          Let's set up your restaurant and go live.
        </p>

        {/* What's Next */}
        <div className="bg-white rounded-2xl shadow-xl p-6 mb-8 text-left">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4">
            What's next
          </h3>
          <div className="space-y-3">
            <div className="flex items-start gap-3">
              <div className="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                <span className="text-emerald-600 text-sm">1</span>
              </div>
              <div>
                <p className="text-gray-900">Add legal & business information</p>
                <p className="text-sm text-gray-500">VAT number and business details</p>
              </div>
            </div>
            
            <div className="flex items-start gap-3">
              <div className="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                <span className="text-emerald-600 text-sm">2</span>
              </div>
              <div>
                <p className="text-gray-900">Create your menu</p>
                <p className="text-sm text-gray-500">Add at least one category and item</p>
              </div>
            </div>
            
            <div className="flex items-start gap-3">
              <div className="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                <span className="text-emerald-600 text-sm">3</span>
              </div>
              <div>
                <p className="text-gray-900">Go live!</p>
                <p className="text-sm text-gray-500">Start accepting orders from customers</p>
              </div>
            </div>
          </div>
        </div>

        {/* CTA */}
        <Button
          onClick={onContinueSetup}
          className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-4 text-lg"
        >
          Continue setup
          <ArrowRight className="w-5 h-5 ml-2" />
        </Button>

        {/* Time estimate */}
        <p className="text-sm text-gray-500 mt-4">
          Setup takes approximately 5-10 minutes
        </p>
      </div>
    </div>
  );
}
