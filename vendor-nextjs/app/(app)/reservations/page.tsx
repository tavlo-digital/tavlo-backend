"use client";

import { useVendorAuth } from "@/lib/vendor-auth";
import { VendorReservationsCalendar } from "../../vendor/VendorReservationsCalendar";

export default function VendorReservationsPage() {
  const { user } = useVendorAuth();

  if (!user) return null;

  return <VendorReservationsCalendar vendorId={String(user.id)} />;
}
