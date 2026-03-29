"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  ShoppingCart,
  UtensilsCrossed,
  CreditCard,
  Settings,
  QrCode,
  BarChart3,
  Star,
  Package,
  Gift,
  Calendar,
} from "lucide-react";
import AppLogoIcon from "@/components/app-logo-icon";

interface NavItem {
  title: string;
  href: string;
  icon: React.ElementType;
}

const navItems: NavItem[] = [
  { title: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { title: "Orders", href: "/orders", icon: ShoppingCart },
  { title: "Reservations", href: "/reservations", icon: Calendar },
  { title: "Menu Management", href: "/menu", icon: UtensilsCrossed },
  { title: "Inventory", href: "/inventory", icon: Package },
  { title: "QR Codes", href: "/qr-codes", icon: QrCode },
  { title: "Loyalty & Promotions", href: "/loyalty", icon: Gift },
  { title: "Analytics", href: "/analytics", icon: BarChart3 },
  { title: "Reviews", href: "/reviews", icon: Star },
  { title: "Billing & Subscription", href: "/billing", icon: CreditCard },
  { title: "Settings", href: "/settings", icon: Settings },
];

function isActive(href: string, currentPath: string) {
  if (href === "/dashboard")
    return currentPath === "/dashboard";
  return currentPath.startsWith(href);
}

export function VendorSidebar() {
  const pathname = usePathname();

  return (
    <aside className="flex w-64 flex-col border-r border-gray-200 bg-white">
      {/* Header */}
      <div className="flex h-16 items-center border-b border-gray-200 px-4">
        <Link href="/dashboard" className="flex items-center gap-2">
          <AppLogoIcon className="h-8 w-8 fill-current text-gray-900" />
          <span className="font-semibold text-gray-900">Tavlo</span>
          <span className="rounded bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">
            VENDOR
          </span>
        </Link>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto py-4">
        {navItems.map((item) => {
          const active = isActive(item.href, pathname);
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`relative flex w-full items-center gap-3 px-4 py-2.5 text-sm transition-colors ${
                active
                  ? "border-r-2 border-purple-600 bg-purple-50 text-purple-700"
                  : "text-gray-700 hover:bg-gray-50"
              }`}
            >
              <item.icon className="h-5 w-5 shrink-0" />
              <span className="flex-1 text-left">{item.title}</span>
            </Link>
          );
        })}
      </nav>
    </aside>
  );
}
