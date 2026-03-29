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
};
