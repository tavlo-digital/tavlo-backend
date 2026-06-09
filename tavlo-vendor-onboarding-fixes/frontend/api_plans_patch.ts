// ─────────────────────────────────────────────────────────────────────────────
// Add this method to the api object in lib/api.ts
// Place it near the other billing methods
// ─────────────────────────────────────────────────────────────────────────────

  // Returns all active subscription plans with features
  getSubscriptionPlans: () =>
    vendorApi.get<{ data: import('./types').SubscriptionPlan[] }>('/vendor/billing/plans'),
