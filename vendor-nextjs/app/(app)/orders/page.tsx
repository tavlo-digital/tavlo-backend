"use client";

import { useVendorAuth } from "@/lib/vendor-auth";
import { OrdersManagement } from "../../vendor/OrdersManagement";

export default function VendorOrdersPage() {
  const { user } = useVendorAuth();

  if (!user) return null;

  return <OrdersManagement vendorId={String(user.id)} />;
}
