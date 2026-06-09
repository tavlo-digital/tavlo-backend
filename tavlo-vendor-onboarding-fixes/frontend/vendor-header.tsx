"use client";

import { Search, Bell, ChevronDown } from "lucide-react";
import { useVendorAuth } from "@/lib/vendor-auth";
import { VendorStatusBadge } from "@/app/vendor-onboarding/VendorStatusBadge";
import type { VendorUIStatus } from "@/lib/types";
import AppLogoIcon from "@/components/app-logo-icon";
import Link from "next/link";

interface VendorHeaderProps {
  vendorUIStatus?: VendorUIStatus;
}

export function VendorHeader({ vendorUIStatus = "live" }: VendorHeaderProps) {
  const { user, logout } = useVendorAuth();

  return (
    <div className="sticky top-0 z-10 border-b border-gray-200 bg-white">
      <div className="mx-auto max-w-[1800px] px-6 py-3">
        <div className="flex items-center justify-between gap-6">
          {/* Left: Logo + status badge */}
          <div className="flex items-center gap-4">
            <Link href="/dashboard" className="flex items-center gap-2">
              <AppLogoIcon className="h-7 w-7 fill-current text-gray-900" />
              <span className="font-semibold text-gray-900 hidden md:block">tavlo</span>
            </Link>
            {/* Demo / Activated / Live badge — matches Figma top nav */}
            <VendorStatusBadge
              status={vendorUIStatus}
              isLiveAndDiscoverable={vendorUIStatus === "live"}
            />
          </div>

          {/* Center: Search */}
          <div className="max-w-xl flex-1 hidden md:block">
            <div className="relative">
              <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                placeholder="Search orders, menu items…"
                className="w-full rounded-lg border border-gray-200 py-2 pr-4 pl-10 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none bg-gray-50"
              />
            </div>
          </div>

          {/* Right: notifications + user */}
          <div className="flex items-center gap-3">
            <button className="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
              <Bell className="h-5 w-5" />
              <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
            </button>

            <div className="flex items-center gap-2 pl-2 border-l border-gray-200">
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-purple-600 text-white text-sm font-semibold">
                {user?.name?.charAt(0).toUpperCase() ?? "V"}
              </div>
              <div className="hidden md:block text-right">
                <div className="text-sm font-medium text-gray-900 leading-tight">
                  {user?.name}
                </div>
                <div className="text-xs text-gray-500">
                  {user?.restaurantName || "Vendor"}
                </div>
              </div>
              <button
                onClick={logout}
                className="ml-1 text-xs text-gray-400 hover:text-gray-700 transition-colors hidden md:block"
              >
                Sign out
              </button>
            </div>
          </div>
        </div>

        {/* Demo mode banner — shown below header when status is demo */}
        {vendorUIStatus === "demo" && (
          <div className="mt-2 -mx-6 px-6 py-2 bg-amber-50 border-t border-amber-100 flex items-center justify-between">
            <p className="text-xs text-amber-700 font-medium">
              <span className="font-semibold">Demo Mode:</span> You're exploring Tavlo with sample data.
              Real orders and payments are disabled.
            </p>
            <Link
              href="/activate"
              className="text-xs font-semibold text-amber-700 underline underline-offset-2 hover:text-amber-900 whitespace-nowrap ml-4"
            >
              Activate →
            </Link>
          </div>
        )}

        {/* Activated but not live banner */}
        {vendorUIStatus === "activated" && (
          <div className="mt-2 -mx-6 px-6 py-2 bg-blue-50 border-t border-blue-100 flex items-center justify-between">
            <p className="text-xs text-blue-700 font-medium">
              <span className="font-semibold">Activated · Not live yet.</span> Complete your restaurant setup to go live on Tavlo.
            </p>
            <Link
              href="/settings"
              className="text-xs font-semibold text-blue-700 underline underline-offset-2 hover:text-blue-900 whitespace-nowrap ml-4"
            >
              Complete setup →
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}
