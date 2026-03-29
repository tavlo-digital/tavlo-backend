"use client";

import VendorAppLayout from "@/components/vendor-app-layout";

export default function VendorDashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return <VendorAppLayout>{children}</VendorAppLayout>;
}
