"use client";

import { useState } from "react";
import { useVendorAuth } from "@/lib/vendor-auth";
import Link from "next/link";
import AppLogoIcon from "@/components/app-logo-icon";

export default function VendorRegisterPage() {
  const { register, loading, errors } = useVendorAuth();
  const [form, setForm] = useState({
    name: "",
    country: "",
    phone: "",
    email: "",
    password: "",
    password_confirmation: "",
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await register(form);
    } catch {
      // errors displayed via state
    }
  };

  const fieldError = (key: keyof typeof form) =>
    errors[key] ? (
      <p className="mt-1 text-xs text-destructive">{errors[key][0]}</p>
    ) : null;

  return (
    <div className="flex min-h-dvh flex-col items-center justify-center bg-background p-6 md:p-10">
      <div className="w-full max-w-md">
        <div className="flex flex-col gap-8">
          <Link href="/" className="flex flex-col items-center gap-2 font-medium">
            <AppLogoIcon className="size-10" />
          </Link>

          <div className="flex flex-col items-center gap-2 text-center">
            <h1 className="text-2xl font-semibold tracking-tight">Become a vendor</h1>
            <p className="text-sm text-muted-foreground">
              Enter your details below to create your account
            </p>
          </div>

          <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
              <div className="grid gap-1.5">
                <label htmlFor="name" className="text-sm font-medium">
                  Business / Full name
                </label>
                <input
                  id="name"
                  name="name"
                  autoComplete="name"
                  autoFocus
                  placeholder="Your business or full name"
                  value={form.name}
                  onChange={handleChange}
                  required
                  className="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20"
                />
                {fieldError("name")}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="grid gap-1.5">
                  <label htmlFor="country" className="text-sm font-medium">
                    Country
                  </label>
                  <input
                    id="country"
                    name="country"
                    autoComplete="country-name"
                    placeholder="Country"
                    value={form.country}
                    onChange={handleChange}
                    required
                    className="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20"
                  />
                  {fieldError("country")}
                </div>

                <div className="grid gap-1.5">
                  <label htmlFor="phone" className="text-sm font-medium">
                    Phone
                  </label>
                  <input
                    id="phone"
                    name="phone"
                    type="tel"
                    autoComplete="tel"
                    placeholder="+1 (555) 000-0000"
                    value={form.phone}
                    onChange={handleChange}
                    required
                    className="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20"
                  />
                  {fieldError("phone")}
                </div>
              </div>

              <div className="grid gap-1.5">
                <label htmlFor="email" className="text-sm font-medium">
                  Email address
                </label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  placeholder="email@example.com"
                  value={form.email}
                  onChange={handleChange}
                  required
                  className="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20"
                />
                {fieldError("email")}
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="grid gap-1.5">
                  <label htmlFor="password" className="text-sm font-medium">
                    Password
                  </label>
                  <input
                    id="password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    placeholder="Password"
                    value={form.password}
                    onChange={handleChange}
                    required
                    className="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20"
                  />
                  {fieldError("password")}
                </div>

                <div className="grid gap-1.5">
                  <label htmlFor="password_confirmation" className="text-sm font-medium">
                    Confirm password
                  </label>
                  <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    placeholder="Confirm"
                    value={form.password_confirmation}
                    onChange={handleChange}
                    required
                    className="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20"
                  />
                </div>
              </div>

              <button
                type="submit"
                disabled={loading}
                className="mt-1 inline-flex h-10 w-full items-center justify-center rounded-lg bg-purple-600 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:opacity-50"
              >
                {loading ? "Creating account…" : "Create account"}
              </button>
            </form>
          </div>

          <p className="text-center text-sm text-muted-foreground">
            Already have an account?{" "}
            <Link
              href="/login"
              className="font-medium text-purple-600 hover:text-purple-700 hover:underline"
            >
              Sign in
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
