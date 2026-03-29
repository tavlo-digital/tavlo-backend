"use client";

import { useState, useEffect } from "react";
import { useVendorAuth } from "@/lib/vendor-auth";
import { useRouter } from "next/navigation";
import Link from "next/link";
import AppLogoIcon from "@/components/app-logo-icon";

export default function VendorLoginPage() {
  const { user, login, loading, errors } = useVendorAuth();
  const router = useRouter();
  const [form, setForm] = useState({ email: "", password: "" });

  useEffect(() => {
    if (!loading && user) {
      router.replace("/dashboard");
    }
  }, [user, loading, router]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await login(form);
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
      <div className="w-full max-w-sm">
        <div className="flex flex-col gap-8">
          <Link href="/" className="flex flex-col items-center gap-2 font-medium">
            <AppLogoIcon className="size-10" />
          </Link>

          <div className="flex flex-col items-center gap-2 text-center">
            <h1 className="text-2xl font-semibold tracking-tight">Welcome back</h1>
            <p className="text-sm text-muted-foreground">
              Sign in to your vendor account
            </p>
          </div>

          <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
            <form onSubmit={handleSubmit} className="flex flex-col gap-5">
              <div className="grid gap-1.5">
                <label htmlFor="email" className="text-sm font-medium">
                  Email address
                </label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  autoFocus
                  placeholder="email@example.com"
                  value={form.email}
                  onChange={handleChange}
                  required
                  className="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20"
                />
                {fieldError("email")}
              </div>

              <div className="grid gap-1.5">
                <label htmlFor="password" className="text-sm font-medium">
                  Password
                </label>
                <input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  placeholder="Password"
                  value={form.password}
                  onChange={handleChange}
                  required
                  className="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20"
                />
                {fieldError("password")}
              </div>

              <button
                type="submit"
                disabled={loading}
                className="mt-1 inline-flex h-10 w-full items-center justify-center rounded-lg bg-purple-600 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:opacity-50"
              >
                {loading ? "Signing in…" : "Sign in"}
              </button>
            </form>
          </div>

          <p className="text-center text-sm text-muted-foreground">
            Don&apos;t have an account?{" "}
            <Link
              href="/register"
              className="font-medium text-purple-600 hover:text-purple-700 hover:underline"
            >
              Create one
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
