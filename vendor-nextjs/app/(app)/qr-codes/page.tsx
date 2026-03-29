"use client";

import { useVendorAuth } from "@/lib/vendor-auth";
import { QRCodesManagement } from "../../vendor/QRCodesManagement";

export default function VendorQRCodesPage() {
  const { user } = useVendorAuth();

  if (!user) return null;

  return <QRCodesManagement vendorId={String(user.id)} />;
}
