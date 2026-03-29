"use client";

import { MenuManagement } from "../../vendor/MenuManagement";
import { useVendorAuth } from "@/lib/vendor-auth";

export default function MenuPage() {
  const { user } = useVendorAuth();

  if (!user) return null;

  return <MenuManagement vendorId={String(user.id)} />;
}
