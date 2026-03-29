"use client";

import { useVendorAuth } from "@/lib/vendor-auth";

export default function VendorDashboardPage() {
  const { user } = useVendorAuth();

  return (
    <div className="px-6 py-8">
      <div className="mb-8">
        <h1 className="text-2xl font-semibold text-gray-900">Dashboard</h1>
        <p className="mt-1 text-sm text-gray-500">
          Welcome back, {user?.name}
        </p>
      </div>
    </div>
  );
}
