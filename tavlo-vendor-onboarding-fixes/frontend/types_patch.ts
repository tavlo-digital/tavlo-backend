// ─────────────────────────────────────────────────────────────────────────────
// In lib/types.ts — replace the Vendor interface with this version.
// Adds status, liveStatus, and isLiveAndDiscoverable fields.
// ─────────────────────────────────────────────────────────────────────────────

export interface Vendor {
  id: number;
  vendorId?: string;
  vendorPublicId?: string;
  vendor_public_id: string;
  actorType?: 'vendor' | 'team_member';
  role?: 'manager' | 'kitchen' | 'waiter';
  name: string;
  restaurantName?: string | null;
  country: string;
  phone?: string | null;
  email: string;
  permissions?: string[];
  created_at: string;
  // Onboarding status — populated by formatVendorUser() on the backend
  status?: 'pending' | 'active' | 'suspended' | 'deactivated';
  liveStatus?: 'not-live' | 'online' | 'offline';
  isLiveAndDiscoverable?: boolean;
}

// Maps backend vendor status to the 3-state UI model used in the onboarding flow
export type VendorUIStatus = 'demo' | 'activated' | 'live';

export function resolveVendorUIStatus(vendor: Vendor | null): VendorUIStatus {
  if (!vendor) return 'demo';
  if (vendor.isLiveAndDiscoverable) return 'live';
  if (vendor.status === 'active') return 'activated';
  return 'demo';
}

export interface SubscriptionPlan {
  id: number;
  name: string;
  description: string;
  monthlyPrice: number;
  yearlyPrice: number;
  currency: string;
  isPopular: boolean;
  maxUsers: number;
  features: PlanFeature[];
}

export interface PlanFeature {
  name: string;
  description: string;
  category: string;
  isInherited: boolean;
}

export interface AuthResponse<T> {
  user: T;
  token: string;
}
