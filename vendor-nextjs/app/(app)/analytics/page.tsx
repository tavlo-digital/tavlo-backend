"use client";

import { useRouter } from "next/navigation";
import { useVendorAuth } from "@/lib/vendor-auth";
import { Analytics } from "../../vendor/Analytics";

export default function VendorAnalyticsPage() {
  const { user } = useVendorAuth();
  const router = useRouter();

  if (!user) return null;

  return (
    <Analytics
      vendorId={String(user.id)}
      onNavigate={(screen) => router.push(`/${screen}`)}
    />
  );
}
