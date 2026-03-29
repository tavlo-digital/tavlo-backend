"use client";

import { useVendorAuth } from "@/lib/vendor-auth";
import { InventoryOverview } from "../../vendor/InventoryOverview";

export default function VendorInventoryPage() {
  const { user } = useVendorAuth();

  if (!user) return null;

  return <InventoryOverview vendorId={String(user.id)} />;
}
