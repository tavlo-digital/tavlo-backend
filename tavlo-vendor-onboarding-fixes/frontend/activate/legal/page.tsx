"use client";
// NEW FILE: app/activate/legal/page.tsx
// Legal & tax details — required step after subscription activation

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useVendorAuth } from "@/lib/vendor-auth";
import { Loader2, FileText, ChevronDown } from "lucide-react";
import { vendorToken } from "@/lib/api";

const API_BASE = process.env.NEXT_PUBLIC_BACKEND_URL ?? "";

const COUNTRIES = [
  { code: "AT", label: "Austria" },
  { code: "DE", label: "Germany" },
  { code: "CH", label: "Switzerland" },
  { code: "JO", label: "Jordan" },
  { code: "SA", label: "Saudi Arabia" },
  { code: "OTHER", label: "Other" },
];

const LANGUAGES = [
  { code: "de", label: "German" },
  { code: "en", label: "English" },
  { code: "ar", label: "Arabic" },
  { code: "fr", label: "French" },
];

interface FormState {
  legalEntityName: string;
  vatNumber: string;
  address: string;
  country: string;
  receiptLanguage: string;
}

interface FormErrors {
  legalEntityName?: string;
  vatNumber?: string;
  address?: string;
  country?: string;
  receiptLanguage?: string;
  submit?: string;
}

export default function LegalDetailsPage() {
  const { user } = useVendorAuth();
  const router = useRouter();

  const [form, setForm] = useState<FormState>({
    legalEntityName: "",
    vatNumber: "",
    address: "",
    country: "AT",
    receiptLanguage: "de",
  });

  const [errors, setErrors] = useState<FormErrors>({});
  const [submitting, setSubmitting] = useState(false);

  const set = (key: keyof FormState) => (
    e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>
  ) => {
    setForm((prev) => ({ ...prev, [key]: e.target.value }));
    setErrors((prev) => ({ ...prev, [key]: undefined }));
  };

  const validate = (): boolean => {
    const newErrors: FormErrors = {};
    if (!form.legalEntityName.trim())
      newErrors.legalEntityName = "Legal business name is required";
    if (!form.vatNumber.trim())
      newErrors.vatNumber = "VAT / tax number is required";
    if (!form.address.trim())
      newErrors.address = "Registered address is required";
    if (!form.country) newErrors.country = "Country is required";
    if (!form.receiptLanguage)
      newErrors.receiptLanguage = "Receipt language is required";
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async () => {
    if (!validate() || !user) return;
    setSubmitting(true);

    try {
      const res = await fetch(
        `${API_BASE}/api/vendor/${user.id}/legal-info`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${vendorToken.get()}`,
          },
          body: JSON.stringify({
            legalEntityName: form.legalEntityName,
            vatNumber: form.vatNumber,
            address: form.address,
            country: form.country,
            // Pass receipt language as vendor notes until a dedicated field is added
            vendorNotes: `Receipt language: ${form.receiptLanguage}`,
          }),
        }
      );

      if (!res.ok) {
        const data = await res.json();
        throw new Error(data.message || "Failed to save legal details");
      }

      // Clear activation session data
      sessionStorage.removeItem("tavlo_activated_plan");

      // Go back to dashboard — status is now 'activated'
      router.push("/dashboard");
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Something went wrong";
      setErrors((prev) => ({ ...prev, submit: msg }));
    } finally {
      setSubmitting(false);
    }
  };

  const inputClass = (error?: string) =>
    `w-full rounded-xl border ${
      error ? "border-red-400 focus:ring-red-400" : "border-gray-200 focus:ring-emerald-500"
    } px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:border-transparent bg-white transition`;

  return (
    <div className="min-h-screen bg-gray-50 pb-20">
      {/* Progress bar */}
      <div className="h-1 bg-emerald-500 w-full" />

      <div className="mx-auto max-w-lg px-4 py-10">
        {/* Step label */}
        <p className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">
          Activation step 1 of 1
        </p>

        {/* Icon */}
        <div className="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center mb-5">
          <FileText className="w-5 h-5 text-emerald-600" />
        </div>

        <h1 className="text-2xl font-bold text-gray-900 mb-1">
          Legal &amp; tax details{" "}
          <span className="text-gray-400 font-normal">(required to go live)</span>
        </h1>
        <p className="text-sm text-gray-500 mb-8">
          Tavlo uses this information to generate compliant invoices and receipts.
        </p>

        <div className="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
          {/* Legal business name */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Legal business name <span className="text-red-500">*</span>
            </label>
            <input
              value={form.legalEntityName}
              onChange={set("legalEntityName")}
              placeholder="e.g., Tavlo Restaurant GmbH"
              className={inputClass(errors.legalEntityName)}
            />
            <p className="text-xs text-gray-400 mt-1">
              As registered with your tax authority
            </p>
            {errors.legalEntityName && (
              <p className="text-xs text-red-500 mt-1">{errors.legalEntityName}</p>
            )}
          </div>

          {/* VAT / Tax number */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              VAT / Tax number <span className="text-red-500">*</span>
            </label>
            <input
              value={form.vatNumber}
              onChange={set("vatNumber")}
              placeholder="e.g., ATU12345678"
              className={inputClass(errors.vatNumber)}
            />
            <p className="text-xs text-gray-400 mt-1">
              Your official tax identification number
            </p>
            {errors.vatNumber && (
              <p className="text-xs text-red-500 mt-1">{errors.vatNumber}</p>
            )}
          </div>

          {/* Registered address */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Registered address <span className="text-red-500">*</span>
            </label>
            <textarea
              value={form.address}
              onChange={set("address")}
              rows={3}
              placeholder="Street, Number, Postal Code, City"
              className={inputClass(errors.address) + " resize-none"}
            />
            <p className="text-xs text-gray-400 mt-1">
              Full registered business address
            </p>
            {errors.address && (
              <p className="text-xs text-red-500 mt-1">{errors.address}</p>
            )}
          </div>

          {/* Country */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Country <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <select
                value={form.country}
                onChange={set("country")}
                className={inputClass(errors.country) + " appearance-none pr-10"}
              >
                {COUNTRIES.map((c) => (
                  <option key={c.code} value={c.code}>
                    {c.label}
                  </option>
                ))}
              </select>
              <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
            </div>
            <p className="text-xs text-gray-400 mt-1">
              Country of business registration
            </p>
          </div>

          {/* Receipt language */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Receipt language <span className="text-red-500">*</span>
            </label>
            <div className="relative">
              <select
                value={form.receiptLanguage}
                onChange={set("receiptLanguage")}
                className={inputClass(errors.receiptLanguage) + " appearance-none pr-10"}
              >
                {LANGUAGES.map((l) => (
                  <option key={l.code} value={l.code}>
                    {l.label}
                  </option>
                ))}
              </select>
              <ChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
            </div>
            <p className="text-xs text-gray-400 mt-1">
              Language for receipts and invoices
            </p>
          </div>
        </div>

        {/* Why we need this */}
        <div className="mt-4 bg-blue-50 border border-blue-100 rounded-xl p-4">
          <p className="text-sm text-blue-800">
            <span className="font-semibold">Why do we need this?</span>{" "}
            This information is required for tax compliance and will appear on
            all customer receipts and invoices generated by Tavlo.
          </p>
        </div>

        {/* Submit error */}
        {errors.submit && (
          <div className="mt-4 bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-700">
            {errors.submit}
          </div>
        )}

        {/* Footer */}
        <div className="mt-8 flex items-center justify-between">
          <p className="text-xs text-gray-400">
            <span className="text-red-500">*</span> Required fields
          </p>
          <button
            onClick={handleSubmit}
            disabled={submitting}
            className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold px-6 py-3 rounded-xl transition-colors"
          >
            {submitting ? (
              <Loader2 className="w-4 h-4 animate-spin" />
            ) : null}
            Save &amp; continue
            {!submitting && <span>→</span>}
          </button>
        </div>
      </div>
    </div>
  );
}
