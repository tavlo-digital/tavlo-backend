"use client";
// NEW FILE: app/activate/success/page.tsx
// Shown after successful Stripe payment

import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { CheckCircle, Loader2 } from "lucide-react";

export default function ActivationSuccessPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [planName, setPlanName] = useState<string>("Standard");

  useEffect(() => {
    // Try to get plan name from sessionStorage (set before Stripe redirect)
    const stored = sessionStorage.getItem("tavlo_activated_plan");
    if (stored) {
      try {
        const plan = JSON.parse(stored);
        setPlanName(plan.name);
      } catch {}
    }
    // sessionStorage cleanup
    sessionStorage.removeItem("tavlo_selected_plan");
  }, []);

  const handleContinue = () => {
    router.push("/activate/legal");
  };

  return (
    <div className="min-h-screen bg-emerald-50 flex items-center justify-center px-4">
      <div className="max-w-sm w-full text-center">
        {/* Success icon */}
        <div className="flex justify-center mb-6">
          <div className="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center">
            <CheckCircle className="w-10 h-10 text-emerald-600" />
          </div>
        </div>

        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          Subscription activated
        </h1>
        <p className="text-gray-500 mb-8">
          Your {planName} plan is active. Let's finish setting up your restaurant.
        </p>

        {/* Next steps */}
        <div className="bg-white rounded-2xl border border-gray-200 p-5 mb-8 text-left">
          <p className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">
            Next Steps
          </p>
          <div className="flex items-center gap-3">
            <div className="w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
              <span className="text-xs font-bold text-emerald-700">1</span>
            </div>
            <span className="text-sm font-medium text-gray-700">
              Add legal &amp; tax information
            </span>
          </div>
        </div>

        <button
          onClick={handleContinue}
          className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3.5 rounded-xl transition-colors"
        >
          Continue setup
        </button>
        <p className="text-xs text-gray-400 mt-3">
          This will take approximately 5–10 minutes
        </p>
      </div>
    </div>
  );
}
