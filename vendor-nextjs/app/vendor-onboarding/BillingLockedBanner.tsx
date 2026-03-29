import { Info, Lock, CheckCircle } from 'lucide-react';

interface BillingLockedBannerProps {
  vendorStatus: 'demo' | 'activated' | 'live';
}

export function BillingLockedBanner({ vendorStatus }: BillingLockedBannerProps) {
  // Don't show banner if vendor is activated or live (has subscription)
  if (vendorStatus === 'live' || vendorStatus === 'activated') {
    return null;
  }

  return (
    <div className="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
      <div className="flex items-start gap-4">
        <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
          <Lock className="w-6 h-6 text-blue-600" />
        </div>
        <div className="flex-1">
          <h3 className="text-lg text-blue-900 mb-2">
            Billing Management Available After Activation
          </h3>
          <p className="text-sm text-blue-800 mb-4">
            You'll be able to manage your billing, view invoices, and update payment methods once you complete the activation process and go live. Your subscription starts when you activate your account.
          </p>
          
          <div className="space-y-2 text-sm text-blue-700">
            <div className="flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-blue-600" />
              <span>Complete your restaurant setup</span>
            </div>
            <div className="flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-blue-600" />
              <span>Add your menu items</span>
            </div>
            <div className="flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-blue-600" />
              <span>Activate your account to start billing</span>
            </div>
          </div>

          <div className="mt-4 pt-4 border-t border-blue-200">
            <div className="flex items-start gap-2 text-xs text-blue-700">
              <Info className="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" />
              <p>
                During the demo phase, you can explore the platform features without any charges. Billing begins only after you activate your subscription and go live.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
