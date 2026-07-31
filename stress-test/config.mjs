// ─── Stress Test Configuration ────────────────────────────────────────────
// Values prefixed with DISCOVER_ are placeholders replaced by setup.mjs
// after reading the server's .env and database.

const config = {
  apiBaseUrl: process.env.API_BASE_URL || 'https://phplaravel-1613226-6348836.cloudwaysapps.com',

  // Vendor credentials (seeder defaults — all use password "password")
  vendors: {
    restaurantA: {
      publicId: 'VID-8492',    // Bella Italia — has full menu seeded
      email: 'contact@bellaitalia.at',
      password: 'password',
    },
    restaurantB: {
      publicId: 'VID-5678',    // Burger Palace — may need menu seeding
      email: 'hello@burgerpalace.at',
      password: 'password',
    },
  },

  // Staff credentials (created directly in DB via artisan tinker)
  staff: {
    staffPassword: 'Password123!',
    restaurantA: {
      waiters: [
        'stress-waiter-a-1@tavlo-test.local',
        'stress-waiter-a-2@tavlo-test.local',
        'stress-waiter-a-3@tavlo-test.local',
      ],
      kitchen: ['stress-kitchen-a-1@tavlo-test.local'],
    },
    restaurantB: {
      waiters: [
        'stress-waiter-b-1@tavlo-test.local',
        'stress-waiter-b-2@tavlo-test.local',
        'stress-waiter-b-3@tavlo-test.local',
      ],
      kitchen: ['stress-kitchen-b-1@tavlo-test.local'],
    },
  },

  // Test timing
  tierDurationMs: 15 * 60 * 1000,    // 15 minutes per tier
  cooldownMs: 2 * 60 * 1000,         // 2 minutes between tiers

  // Customer behavior
  minItemsPerCustomer: 1,
  maxItemsPerCustomer: 4,
  minCustomersPerTable: 2,
  maxCustomersPerTable: 5,
  minActionDelayMs: 2000,
  maxActionDelayMs: 15000,
  tableStaggerMs: 10000,              // stagger table starts by up to 10s

  // Payment distribution (must sum to 1.0)
  paymentDistribution: {
    card: 0.40,
    cash: 0.30,
    payForOthers: 0.20,
    getCovered: 0.10,
  },

  // Waiter/Kitchen polling
  waiterPollIntervalMs: [5000, 10000],   // random between min/max
  kitchenPollIntervalMs: [3000, 8000],
  kitchenCookTimeMs: [5000, 15000],       // simulated cook time (shorter for stress test)

  // Order notes pool
  notePool: [
    'No onions please',
    'Extra spicy',
    'Gluten free if possible',
    'Well done',
    'No salt',
    'Allergic to nuts',
    'Extra sauce on the side',
    'Make it mild',
    'No cheese',
    'Extra napkins please',
    'Birthday celebration',
    'No ice in drinks',
    'Vegan option if available',
    'Light on the oil',
    'Add extra garlic',
    'No cilantro',
    'Dressing on the side',
    'Medium rare',
    'Lactose intolerant',
    'Extra crispy',
  ],

  // Tiers configuration
  tiers: [
    { name: 'Tier 1', actors: 10,  restaurant: 'restaurantA', tables: 3,  desc: '3 tables, ~3 customers each' },
    { name: 'Tier 2', actors: 15,  restaurant: 'restaurantA', tables: 3,  desc: '3 tables, 5 customers each' },
    { name: 'Tier 3', actors: 20,  restaurant: 'restaurantB', tables: 6,  desc: '6 tables, ~3 customers each' },
    { name: 'Tier 4', actors: 30,  restaurant: 'restaurantB', tables: 10, desc: '10 tables active' },
    { name: 'Tier 5', actors: 50,  restaurant: 'both',        tables: 20, desc: 'Both restaurants, 20 tables total' },
  ],
};

export default config;
