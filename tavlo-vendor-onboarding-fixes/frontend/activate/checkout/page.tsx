"use client";
// NEW FILE: app/activate/checkout/page.tsx
// Payment screen — shows order summary and initiates Stripe Checkout

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { useVendorAuth } from "@/lib/vendor-auth";
import { ArrowLeft, Lock, Loader2, CreditCard } from "lucide-react";
import Link from "next/link";
import { vendorToken } from "@/lib/api";

const API_BASE = process.env.NEXT_PUBLIC_BACKEND_URL ?? "";

interface SelectedPlan {
  id: number;
  name: string;
  price: number;
  billing: "monthly" | "yearly";
  currency: string;
}

export default function CheckoutPage() {
  const { user } = useVendorAuth();
  const router = useRouter();
  const [plan, setPlan] = useState<SelectedPlan | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const stored = sessionStorage.getItem("tavlo_selected_plan");
    if (!stored) {
      router.replace("/activate");
      return;
    }
    try {
      setPlan(JSON.parse(stored));
    } catch {
      router.replace("/activate");
    }
  }, [router]);

  const handlePay = async () => {
    if (!plan || !user) return;
    setLoading(true);
    setError(null);

    try {
      // Ask backend to create a Stripe Checkout session and return the URL
      // Wasim: implement POST /api/vendor/{vendorId}/billing/checkout-session
      // returning { checkoutUrl: string }
      const res = await fetch(
        `${API_BASE}/api/vendor/${user.id}/billing/checkout-session`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${vendorToken.get()}`,
          },
          body: JSON.stringify({
            planId: plan.id,
            billingCycle: plan.billing,
            // Stripe will redirect back to these URLs
            successUrl: `${window.location.origin}/activate/success`,
            cancelUrl: `${window.location.origin}/activate/checkout`,
          }),
        }
      );

      if (!res.ok) {
        const data = await res.json();
        throw new Error(data.message || "Payment setup failed");
      }

      const { checkoutUrl } = await res.json();

      if (checkoutUrl) {
        // Redirect to Stripe Checkout hosted page
        // Stripe handles card, EPS, Apple Pay, Google Pay automatically
        window.location.href = checkoutUrl;
      } else {
        // Fallback: backend not yet wired — simulate success for testing
        sessionStorage.setItem("tavlo_activated_plan", JSON.stringify(plan));
        router.push("/activate/success");
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Something went wrong";
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  if (!plan) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Top bar */}
      <div className="border-b border-gray-200 bg-white px-6 py-4 flex items-center gap-4">
        <Link
          href="/activate"
          className="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to plans
        </Link>
      </div>

      <div className="mx-auto max-w-md px-4 py-12">
        <h1 className="text-2xl font-bold text-gray-900 text-center mb-2">
          Complete your subscription
        </h1>
        <p className="text-center text-gray-500 text-sm mb-8">
          Choose how you'd like to pay. Your subscription will activate
          immediately after successful payment.
        </p>

        {/* Order summary */}
        <div className="bg-white rounded-2xl border border-gray-200 p-5 mb-4">
          <p className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">
            Order Summary
          </p>
          <div className="flex items-center justify-between mb-3">
            <div>
              <p className="font-semibold text-gray-900">{plan.name} Plan</p>
              <p className="text-sm text-gray-500">
                Billed {plan.billing}
              </p>
            </div>
            <div className="text-right">
              <p className="font-bold text-gray-900">
                €{plan.price} / {plan.billing === "monthly" ? "month" : "year"}
              </p>
            </div>
          </div>
          <div className="border-t border-gray-100 pt-3 flex items-center justify-between">
            <div>
              <p className="text-sm font-medium text-gray-700">Total due today</p>
              <p className="text-xs text-gray-400">Excluding VAT</p>
            </div>
            <p className="text-xl font-bold text-gray-900">€{plan.price}</p>
          </div>
        </div>

        {/* Payment method info */}
        <div className="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
          <p className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">
            Payment Method
          </p>

          <div className="space-y-3 mb-4">
            {[
              { icon: "💳", label: "Credit / Debit Card" },
              { icon: "🏦", label: "EPS (Austrian bank transfer)" },
              { icon: "📱", label: "Apple Pay / Google Pay" },
            ].map((method) => (
              <div
                key={method.label}
                className="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-700"
              >
                <span>{method.icon}</span>
                <span>{method.label}</span>
              </div>
            ))}
          </div>

          <p className="text-xs text-gray-400 text-center">
            You'll choose your preferred method on the next secure Stripe page.
          </p>
        </div>

        {/* Error */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-xl p-3 mb-4 text-sm text-red-700">
            {error}
          </div>
        )}

        {/* Pay button */}
        <button
          onClick={handlePay}
          disabled={loading}
          className="w-full bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold py-4 rounded-2xl flex items-center justify-center gap-2 transition-colors shadow-sm"
        >
          {loading ? (
            <Loader2 className="w-5 h-5 animate-spin" />
          ) : (
            <Lock className="w-4 h-4" />
          )}
          {loading ? "Redirecting to payment…" : `Pay €${plan.price} & activate`}
        </button>

        <div className="flex items-center justify-center gap-2 mt-4 text-xs text-gray-400">
          <Lock className="w-3.5 h-3.5" />
          <span>
            Secure payment via Stripe. We never store your card details.
          </span>
        </div>
      </div>
    </div>
  );
}
