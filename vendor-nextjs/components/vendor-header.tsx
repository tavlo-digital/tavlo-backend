"use client";

import { Search, User } from "lucide-react";
import { useVendorAuth } from "@/lib/vendor-auth";

export function VendorHeader() {
  const { user, logout } = useVendorAuth();

  return (
    <div className="sticky top-0 z-10 border-b border-gray-200 bg-white">
      <div className="mx-auto max-w-[1800px] px-6 py-4">
        <div className="flex items-center justify-between gap-6">
          {/* Search */}
          <div className="max-w-2xl flex-1">
            <div className="relative">
              <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                placeholder="Search orders, menu items…"
                className="w-full rounded-lg border border-gray-300 py-2.5 pr-4 pl-11 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
              />
            </div>
          </div>

          {/* Right side */}
          <div className="flex items-center gap-3">
            <div className="text-right">
              <div className="text-sm font-medium text-gray-900">
                {user?.name}
              </div>
              <div className="text-xs text-gray-500">Vendor</div>
            </div>

            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-purple-600">
              <User className="h-5 w-5 text-white" />
            </div>

            <button
              onClick={logout}
              className="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 transition-colors hover:bg-gray-100"
            >
              Sign out
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
