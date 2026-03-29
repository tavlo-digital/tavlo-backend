"use client";

import { useVendorAuth } from "@/lib/vendor-auth";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { VendorSidebar } from "@/components/vendor-sidebar";
import { VendorHeader } from "@/components/vendor-header";

export default function VendorAppLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const { user, loading } = useVendorAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) {
      router.push("/login");
    }
  }, [loading, user, router]);

  if (loading) {
    return (
      <div className="flex h-screen items-center justify-center bg-gray-50">
        <p className="text-gray-500">Loading…</p>
      </div>
    );
  }

  if (!user) return null;

  return (
    <div className="flex h-screen overflow-hidden bg-gray-50">
      <VendorSidebar />
      <div className="flex flex-1 flex-col overflow-hidden">
        <main className="flex-1 overflow-auto">
          <div className="min-h-screen bg-gray-50">
            <VendorHeader />
            <div className="mx-auto max-w-[1800px]">{children}</div>
          </div>
        </main>
      </div>
    </div>
  );
}
