const API_BASE = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

const VENDOR_TOKEN_KEY = "vendor_token";

// ----------------------------------------------------------------
// Token helpers (client-side only)
// ----------------------------------------------------------------
export const vendorToken = {
  get: () =>
    typeof window !== "undefined"
      ? localStorage.getItem(VENDOR_TOKEN_KEY)
      : null,
  set: (token: string) => localStorage.setItem(VENDOR_TOKEN_KEY, token),
  clear: () => localStorage.removeItem(VENDOR_TOKEN_KEY),
};

// ----------------------------------------------------------------
// Fetch wrapper
// ----------------------------------------------------------------
interface ApiOptions extends Omit<RequestInit, "body"> {
  body?: Record<string, unknown>;
}

async function apiFetch<T = unknown>(
  path: string,
  token: string | null,
  options: ApiOptions = {}
): Promise<T> {
  const { body, headers: extraHeaders, ...rest } = options;

  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
    ...((extraHeaders as Record<string, string>) ?? {}),
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}/api${path}`, {
    headers,
    body: body ? JSON.stringify(body) : undefined,
    ...rest,
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    const error: Error & { status?: number; data?: unknown } = new Error(
      data.message || `API error ${res.status}`
    );
    error.status = res.status;
    error.data = data;
    throw error;
  }

  return res.json();
}

async function apiFetchForm<T = unknown>(
  path: string,
  token: string | null,
  formData: FormData,
  method = "POST"
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
  };
  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}/api${path}`, {
    method,
    headers,
    body: formData,
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    const error: Error & { status?: number; data?: unknown } = new Error(
      data.message || `API error ${res.status}`
    );
    error.status = res.status;
    error.data = data;
    throw error;
  }

  return res.json();
}

export function createVendorApi() {
  return {
    get: <T = unknown>(path: string) =>
      apiFetch<T>(path, vendorToken.get()),
    post: <T = unknown>(path: string, body?: Record<string, unknown>) =>
      apiFetch<T>(path, vendorToken.get(), { method: "POST", body }),
    put: <T = unknown>(path: string, body?: Record<string, unknown>) =>
      apiFetch<T>(path, vendorToken.get(), { method: "PUT", body }),
    patch: <T = unknown>(path: string, body?: Record<string, unknown>) =>
      apiFetch<T>(path, vendorToken.get(), { method: "PATCH", body }),
    delete: <T = unknown>(path: string) =>
      apiFetch<T>(path, vendorToken.get(), { method: "DELETE" }),
  };
}

// ----------------------------------------------------------------
// Convenience API object used by vendor components
// ----------------------------------------------------------------
const vendorApi = createVendorApi();

export const api = {
  // Menu Categories
  getCategories: () =>
    vendorApi.get(`/vendor/menu/categories`),
  createCategory: (data: { name: string; defaultTaxCategory?: string }) =>
    vendorApi.post(`/vendor/menu/categories`, data as unknown as Record<string, unknown>),
  updateCategory: (categoryId: number, data: Record<string, unknown>) =>
    vendorApi.patch(`/vendor/menu/categories/${categoryId}`, data),
  deleteCategory: (categoryId: number) =>
    vendorApi.delete(`/vendor/menu/categories/${categoryId}`),

  // Menu Items
  getMenuItems: (params?: { category_id?: number; search?: string; available?: boolean }) => {
    const query = new URLSearchParams();
    if (params?.category_id) query.set('category_id', String(params.category_id));
    if (params?.search) query.set('search', params.search);
    if (params?.available !== undefined) query.set('available', String(params.available));
    const qs = query.toString();
    return vendorApi.get(`/vendor/menu/items${qs ? `?${qs}` : ''}`);
  },
  createMenuItem: (data: Record<string, unknown>) =>
    vendorApi.post(`/vendor/menu/items`, data),
  getMenuItem: (itemId: number) =>
    vendorApi.get(`/vendor/menu/items/${itemId}`),
  updateMenuItem: (itemId: number, data: Record<string, unknown>) =>
    vendorApi.patch(`/vendor/menu/items/${itemId}`, data),
  deleteMenuItem: (itemId: number) =>
    vendorApi.delete(`/vendor/menu/items/${itemId}`),
  toggleMenuItemAvailability: (itemId: number) =>
    vendorApi.patch(`/vendor/menu/items/${itemId}/toggle`),

  // Allergens & Special Tags (lookups)
  getAllergens: () =>
    vendorApi.get(`/vendor/allergens`),
  getSpecialTags: () =>
    vendorApi.get(`/vendor/special-tags`),

  // Legacy menu endpoints (kept for backward compat)
  getMenu: (restaurantId: string) =>
    vendorApi.get(`/restaurants/${restaurantId}/menu`),
  updateMenu: (restaurantId: string, menuData: unknown) =>
    vendorApi.put(`/restaurants/${restaurantId}/menu`, menuData as Record<string, unknown>),

  // Inventory
  getInventory: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/inventory/items`),
  addInventoryItem: (vendorId: string, item: unknown) =>
    vendorApi.post(`/vendor/${vendorId}/inventory/items`, item as Record<string, unknown>),
  updateInventoryItem: (vendorId: string, itemId: string, updates: unknown) =>
    vendorApi.patch(`/vendor/${vendorId}/inventory/items/${itemId}`, updates as Record<string, unknown>),
  deleteInventoryItem: (vendorId: string, itemId: string) =>
    vendorApi.delete(`/vendor/${vendorId}/inventory/items/${itemId}`),
  getInventorySettings: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/inventory/settings`),
  updateInventorySettings: (vendorId: string, settings: unknown) =>
    vendorApi.put(`/vendor/${vendorId}/inventory/settings`, settings as Record<string, unknown>),

  // Orders
  getVendorOrders: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/orders`),
  updateOrder: (orderId: string, updates: unknown) =>
    vendorApi.patch(`/orders/${orderId}`, updates as Record<string, unknown>),
  markOrderReady: (orderId: string) =>
    vendorApi.patch(`/orders/${orderId}/ready`),
  markOrderPickedUp: (orderId: string) =>
    vendorApi.patch(`/orders/${orderId}/picked-up`),

  // Vendor
  getVendorSettings: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/settings`),
  updateVendorSettings: (vendorId: string, settings: unknown) =>
    vendorApi.put(`/vendor/${vendorId}/settings`, settings as Record<string, unknown>),
  getVendorSubscription: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/subscription`),
  submitLegalInfoForApproval: (vendorId: string, data: unknown) =>
    vendorApi.post(`/vendor/${vendorId}/legal-info`, data as Record<string, unknown>),
  getLegalChangeStatus: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/legal-info/status`),
  uploadLogo: (vendorId: string, file: File) => {
    const form = new FormData();
    form.append("logo", file);
    return apiFetchForm<{ logoUrl: string }>(
      `/vendor/${vendorId}/settings/logo`,
      vendorToken.get(),
      form
    );
  },
  uploadCoverPhoto: (vendorId: string, file: File) => {
    const form = new FormData();
    form.append("cover", file);
    return apiFetchForm<{ coverPhotoUrl: string }>(
      `/vendor/${vendorId}/settings/cover-photo`,
      vendorToken.get(),
      form
    );
  },
  exportVendorData: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/settings/export`),
  // Stripe Connect
  connectStripe: (vendorId: string) =>
    vendorApi.post(`/vendor/${vendorId}/stripe/connect`),
  getStripeOnboardingLink: (vendorId: string, refreshUrl: string, returnUrl: string) =>
    vendorApi.post(`/vendor/${vendorId}/stripe/onboarding-link`, { refreshUrl, returnUrl }),
  getStripeStatus: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/stripe/status`),

  // Analytics / Customers
  getTopCustomers: (vendorId: string, period = '6m') =>
    vendorApi.get(`/vendor/${vendorId}/top-customers?period=${period}`),
  getComplaints: (vendorId: string, filter = 'all') =>
    vendorApi.get(`/vendor/${vendorId}/complaints?filter=${filter}`),
  replyToReview: (vendorId: string, reviewId: string, replyText: string) =>
    vendorApi.post(`/vendor/${vendorId}/reviews/${reviewId}/reply`, { reply: replyText }),

  // Reservations
  getVendorReservations: (vendorId: string, status?: string, date?: string) => {
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (date) params.set('date', date);
    const qs = params.toString();
    return vendorApi.get(`/vendor/${vendorId}/reservations${qs ? `?${qs}` : ''}`);
  },
  updateReservationStatus: (reservationId: string, status: string, vendorNote?: string) =>
    vendorApi.patch(`/reservations/${reservationId}/status`, { status, vendorNote } as Record<string, unknown>),

  // Seed
  seedData: (force = false) =>
    vendorApi.post(force ? '/seed?force=true' : '/seed'),

  // Dashboard
  getDashboard: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/dashboard`),

  // Tables / QR
  getTables: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/tables`),
  createTable: (vendorId: string, data: { number: number; name?: string }) =>
    vendorApi.post(`/vendor/${vendorId}/tables`, data as unknown as Record<string, unknown>),
  updateTable: (vendorId: string, tableId: string, data: { name?: string; is_active?: boolean }) =>
    vendorApi.patch(`/vendor/${vendorId}/tables/${tableId}`, data as unknown as Record<string, unknown>),
  deleteTable: (vendorId: string, tableId: string) =>
    vendorApi.delete(`/vendor/${vendorId}/tables/${tableId}`),
  regenerateAllQR: (vendorId: string) =>
    vendorApi.post(`/vendor/${vendorId}/tables/regenerate-all`),
  refreshTableQR: (vendorId: string, tableId: string) =>
    vendorApi.post(`/vendor/${vendorId}/tables/${tableId}/refresh-qr`),
  getTakeawayQR: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/tables/takeaway-qr`),
  syncTables: (vendorId: string, count: number) =>
    vendorApi.post(`/vendor/${vendorId}/tables/sync`, { count }),

  // Team
  getTeamMembers: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/team`),
  inviteTeamMember: (vendorId: string, data: { name: string; email: string; role: string; permissions?: string[] }) =>
    vendorApi.post(`/vendor/${vendorId}/team/invite`, data as unknown as Record<string, unknown>),
  updateTeamMember: (vendorId: string, memberId: string, data: { name?: string; role?: string; permissions?: string[]; status?: string }) =>
    vendorApi.patch(`/vendor/${vendorId}/team/${memberId}`, data as unknown as Record<string, unknown>),
  deleteTeamMember: (vendorId: string, memberId: string) =>
    vendorApi.delete(`/vendor/${vendorId}/team/${memberId}`),

  // Analytics
  getAnalytics: (vendorId: string, period = 'weekly') =>
    vendorApi.get(`/vendor/${vendorId}/analytics?period=${period}`),

  // Extended order actions
  markOrderServed: (orderId: string) =>
    vendorApi.patch(`/orders/${orderId}/served`),
  cancelOrder: (orderId: string, reason?: string) =>
    vendorApi.patch(`/orders/${orderId}/cancel`, reason ? { reason } : undefined),

  // Billing & Subscription
  getBillingDetails: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/billing`),
  getBillingInvoices: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/billing/invoices`),
  downloadInvoice: (vendorId: string, invoiceId: string) =>
    vendorApi.get<{ url: string }>(`/vendor/${vendorId}/billing/invoices/${invoiceId}/download`),
  getBillingUsage: (vendorId: string) =>
    vendorApi.get(`/vendor/${vendorId}/billing/usage`),
  upgradePlan: (vendorId: string, planId: number) =>
    vendorApi.post(`/vendor/${vendorId}/billing/upgrade`, { planId }),
  changeBillingCycle: (vendorId: string, cycle: 'monthly' | 'yearly') =>
    vendorApi.patch(`/vendor/${vendorId}/billing/cycle`, { cycle }),
  updatePaymentMethod: (vendorId: string, data: Record<string, unknown>) =>
    vendorApi.post(`/vendor/${vendorId}/billing/payment-method`, data),
  cancelSubscription: (vendorId: string) =>
    vendorApi.post(`/vendor/${vendorId}/billing/cancel`),
  getBillingPortalUrl: (vendorId: string) =>
    vendorApi.post<{ url: string | null; message?: string }>(`/vendor/${vendorId}/billing/portal`),
};
