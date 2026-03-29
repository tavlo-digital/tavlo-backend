"use client";

import { useVendorAuth } from "@/lib/vendor-auth";
import { LoyaltyManagement } from "../../vendor/LoyaltyManagement";

export default function VendorLoyaltyPage() {
  const { user } = useVendorAuth();

  if (!user) return null;

  return <LoyaltyManagement vendorId={String(user.id)} />;
}
