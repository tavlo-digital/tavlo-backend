"use client";
// NEW FILE: app/activate/page.tsx
// Plans selection screen — Step 1 of activation funnel

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { useVendorAuth } from "@/lib/vendor-auth";
import { Check, Star, ArrowLeft, Loader2 } from "lucide-react";
import Link from "next/link";
import type { SubscriptionPlan } from "@/lib/types";
import { vendorToken } from "@/lib/api";

const API_BASE = process.env.NEXT_PUBLIC_BACKEND_URL ?? "";

export default function ActivatePage() {
  const { user } = useVendorAuth();
  const router = useRouter();
  const [billing, setBilling] = useState<"monthly" | "yearly">("monthly");
  const [plans, setPlans] = useState<SubscriptionPlan[]>([]);
  const [loading, setLoading] = useState(true);
  const [selecting, setSelecting] = useState<number | null>(null);

  useEffect(() => {
    async function loadPlans() {
      try {
        const res = await fetch(`${API_BASE}/api/vendor/billing/plans`, {
          headers: { Authorization: `Bearer ${vendorToken.get()}` },
        });
        const json = await res.json();
        setPlans(json.data ?? []);
      } catch {
        // fallback to empty — handled in render
      } finally {
        setLoading(false);
      }
    }
    loadPlans();
  }, []);

  const handleSelectPlan = (plan: SubscriptionPlan) => {
    setSelecting(plan.id);
    // Store selected plan in sessionStorage for the checkout screen
    sessionStorage.setItem("tavlo_selected_plan", JSON.stringify({
      id: plan.id,
      name: plan.name,
      price: billing === "monthly" ? plan.monthlyPrice : plan.yearlyPrice,
      billing,
      currency: plan.currency,
    }));
    router.push("/activate/checkout");
  };

  const yearlySaving = (plan: SubscriptionPlan) =>
    Math.round(plan.monthlyPrice * 12 - plan.yearlyPrice);

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Top bar */}
      <div className="border-b border-gray-200 bg-white px-6 py-4 flex items-center gap-4">
        <Link href="/dashboard" className="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 transition-colors">
          <ArrowLeft className="w-4 h-4" />
          Back to demo
        </Link>
      </div>

      <div className="mx-auto max-w-5xl px-4 py-12">
        {/* Header */}
        <div className="text-center mb-10">
          <div className="inline-flex items-center justify-center w-14 h-14 bg-emerald-100 rounded-2xl mb-4">
            <svg className="w-7 h-7 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </div>
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Unlock your restaurant on Tavlo</h1>
          <p className="text-gray-500 max-w-md mx-auto">
            Choose a plan to activate real data, QR codes, and live ordering
          </p>
        </div>

        {/* Billing toggle */}
        <div className="flex items-center justify-center gap-3 mb-10">
          <button
            onClick={() => setBilling("monthly")}
            className={`px-5 py-2 rounded-full text-sm font-medium transition-all ${
              billing === "monthly"
                ? "bg-emerald-600 text-white shadow-sm"
                : "text-gray-600 hover:text-gray-900"
            }`}
          >
            Monthly
          </button>
          <button
            onClick={() => setBilling("yearly")}
            className={`px-5 py-2 rounded-full text-sm font-medium transition-all flex items-center gap-2 ${
              billing === "yearly"
                ? "bg-emerald-600 text-white shadow-sm"
                : "text-gray-600 hover:text-gray-900"
            }`}
          >
            Yearly
            <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${
              billing === "yearly" ? "bg-white/20 text-white" : "bg-emerald-100 text-emerald-700"
            }`}>
              Save up to 17%
            </span>
          </button>
        </div>

        {/* Plans grid */}
        {loading ? (
          <div className="flex justify-center py-24">
            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {plans.map((plan) => (
              <div
                key={plan.id}
                className={`relative rounded-2xl bg-white border-2 flex flex-col transition-all ${
                  plan.isPopular
                    ? "border-emerald-500 shadow-lg shadow-emerald-100"
                    : "border-gray-200 hover:border-gray-300"
                }`}
              >
                {plan.isPopular && (
                  <div className="absolute -top-3.5 left-0 right-0 flex justify-center">
                    <span className="inline-flex items-center gap-1 bg-emerald-600 text-white text-xs font-semibold px-3 py-1 rounded-full">
                      <Star className="w-3 h-3 fill-white" />
                      Most Popular
                    </span>
                  </div>
                )}

                <div className="p-6 flex-1">
                  <h2 className="text-xl font-bold text-gray-900 mb-1">{plan.name}</h2>
                  <p className="text-sm text-gray-500 mb-5 min-h-[40px]">{plan.description}</p>

                  <div className="mb-2">
                    <span className="text-4xl font-bold text-gray-900">
                      €{billing === "monthly" ? plan.monthlyPrice : Math.round(plan.yearlyPrice / 12)}
                    </span>
                    <span className="text-gray-500 text-sm ml-1">/ month</span>
                  </div>
                  {billing === "yearly" && (
                    <p className="text-xs text-emerald-600 font-medium mb-5">
                      €{plan.yearlyPrice}/year · Save €{yearlySaving(plan)}/year
                    </p>
                  )}
                  {billing === "monthly" && <div className="mb-5" />}

                  {/* Features — show first 5 direct features */}
                  <div className="space-y-2.5">
                    {plan.features
                      .filter((f) => !f.isInherited)
                      .slice(0, 5)
                      .map((f) => (
                        <div key={f.name} className="flex items-start gap-2.5">
                          <div className="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <Check className="w-2.5 h-2.5 text-emerald-600" />
                          </div>
                          <span className="text-sm text-gray-700">{f.name}</span>
                        </div>
                      ))}
                    {plan.features.filter((f) => !f.isInherited).length > 5 && (
                      <p className="text-xs text-gray-400 pl-6">
                        + {plan.features.filter((f) => !f.isInherited).length - 5} more features
                      </p>
                    )}
                  </div>
                </div>

                <div className="p-6 pt-0">
                  <button
                    onClick={() => handleSelectPlan(plan)}
                    disabled={selecting === plan.id}
                    className={`w-full py-3 rounded-xl font-semibold text-sm transition-all flex items-center justify-center gap-2 ${
                      plan.isPopular
                        ? "bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm"
                        : "bg-gray-900 hover:bg-gray-800 text-white"
                    } disabled:opacity-60`}
                  >
                    {selecting === plan.id ? (
                      <Loader2 className="w-4 h-4 animate-spin" />
                    ) : null}
                    Subscribe
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}

        <p className="text-center text-xs text-gray-400 mt-8">
          All prices exclude VAT. Cancel anytime. Secure payment via Stripe.
        </p>
      </div>
    </div>
  );
}
