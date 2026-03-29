"use client";

import { useRouter } from "next/navigation";
import { useVendorAuth } from "@/lib/vendor-auth";
import { Dashboard } from "../../vendor/Dashboard";

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

  return (
    <Dashboard
      vendorId={String(user.id)}
      vendorStatus="live"
      onNavigate={(screen) => {
        const route = SCREEN_ROUTES[screen];
        if (route) router.push(route);
      }}
    />
  );
}
