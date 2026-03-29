"use client";

import { useVendorAuth } from "@/lib/vendor-auth";
import { BillingSubscription } from "../../vendor/BillingSubscription";

export default function VendorBillingPage() {
  const { user } = useVendorAuth();

  if (!user) return null;

  return <BillingSubscription vendorId={String(user.id)} vendorStatus="live" />;
}
