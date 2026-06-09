"use client";
// Replaces: app/(app)/dashboard/page.tsx

import { useRouter } from "next/navigation";
import { useVendorAuth } from "@/lib/vendor-auth";
import { Dashboard } from "../../vendor/Dashboard";
import { resolveVendorUIStatus } from "@/lib/types";

const SCREEN_ROUTES: Record<string, string> = {
  orders: "/orders",
  menu: "/menu",
  billing: "/billing",
  analytics: "/analytics",
  loyalty: "/loyalty",
  settings: "/settings",
  inventory: "/inventory",
  "qr-codes": "/qr-codes",
};

export default function VendorDashboardPage() {
  const { user } = useVendorAuth();
  const router = useRouter();

  if (!user) return null;

  const vendorUIStatus = resolveVendorUIStatus(user);

  return (
    <Dashboard
      vendorId={String(user.id)}
      vendorStatus={vendorUIStatus}
      isLiveAndDiscoverable={user.isLiveAndDiscoverable ?? false}
      onActivateClick={() => router.push("/activate")}
      onNavigate={(screen) => {
        // In demo mode, redirect to activate instead of the locked page
        if (vendorUIStatus === "demo") {
          router.push("/activate");
          return;
        }
        const route = SCREEN_ROUTES[screen];
        if (route) router.push(route);
      }}
    />
  );
}
