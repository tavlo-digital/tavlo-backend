"use client";

import { useVendorAuth, vendorHomeFor } from "@/lib/vendor-auth";
import { useRouter, usePathname, useSearchParams } from "next/navigation";
import { useEffect } from "react";
import { VendorSidebar } from "@/components/vendor-sidebar";
import { VendorHeader } from "@/components/vendor-header";
import { resolveVendorUIStatus } from "@/lib/types";

// Routes that are part of the activation funnel — never show the locked overlay here
const ACTIVATION_ROUTES = ["/activate", "/login", "/register"];

export default function VendorAppLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const { user, initializing } = useVendorAuth();
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const vendorUIStatus = resolveVendorUIStatus(user ?? null);
  const isActivationRoute = ACTIVATION_ROUTES.some((r) => pathname.startsWith(r));

  useEffect(() => {
    if (initializing) return;
    if (!user) {
      const qs = searchParams?.toString();
      const target = pathname + (qs ? `?${qs}` : "");
      const redirect = encodeURIComponent(target || "/dashboard");
      router.replace(`/login?redirect=${redirect}`);
      return;
    }
    if (user.actorType === "team_member") {
      router.replace(vendorHomeFor(user));
      return;
    }
    // New vendor in demo mode (status = pending) — redirect to activate
    // unless they're already in the activation funnel or on dashboard
    if (
      vendorUIStatus === "demo" &&
      !isActivationRoute &&
      pathname !== "/dashboard"
    ) {
      router.replace("/dashboard");
    }
  }, [initializing, user, router, pathname, searchParams, vendorUIStatus, isActivationRoute]);

  if (initializing || !user) {
    return (
      <div className="flex h-screen items-center justify-center bg-gray-50">
        <p className="text-gray-500">Loading…</p>
      </div>
    );
  }

  return (
    <div className="flex h-screen overflow-hidden bg-gray-50">
      <VendorSidebar vendorUIStatus={vendorUIStatus} />
      <div className="flex flex-1 flex-col overflow-hidden">
        <main className="flex-1 overflow-auto">
          <div className="min-h-screen bg-gray-50">
            <VendorHeader vendorUIStatus={vendorUIStatus} />
            <div className="mx-auto max-w-[1800px]">{children}</div>
          </div>
        </main>
      </div>
    </div>
  );
}
