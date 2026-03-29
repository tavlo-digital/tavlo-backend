import { ActionStory } from './ActionStoryTemplate';

/**
 * Customer Actions Part 1 (35 actions)
 * QR Landing, Authentication, Menu Browsing, Dish Customization, Basket Management
 */

export const customerActionsPart1: ActionStory[] = [
  // QR LANDING (ORD Domain) - 7 actions
  {
    id: 'TAV-CUS-ORD-001',
    name: 'Customer scans QR code at table',
    trigger: 'Customer scans table QR code with phone camera',
    preconditions: 'Restaurant has active Tavlo account, table QR exists',
    systemAction: 'System identifies restaurant and table number, loads menu interface',
    uiUpdates: 'Customer sees restaurant name, table number, welcome message, menu preview',
    failureStates: 'Invalid QR code, restaurant offline, menu not published',
    successOutcome: 'Customer lands on restaurant menu for their specific table'
  },
  {
    id: 'TAV-CUS-ORD-002',
    name: 'Customer selects dining mode',
    trigger: 'Customer chooses "Dine-in" or "Takeaway" after scanning QR',
    preconditions: 'Restaurant supports both modes',
    systemAction: 'System sets order mode, adjusts menu availability and pricing if needed',
    uiUpdates: 'Mode indicator appears, menu filters to available items',
    failureStates: 'Mode not supported by restaurant',
    successOutcome: 'Customer proceeds with correct ordering mode'
  },
  {
    id: 'TAV-CUS-ORD-003',
    name: 'Customer views restaurant info from QR landing',
    trigger: 'Customer taps restaurant info button',
    preconditions: 'Restaurant profile complete',
    systemAction: 'System displays restaurant details: hours, address, ratings, photos',
    uiUpdates: 'Modal shows restaurant information with close option',
    failureStates: 'Incomplete restaurant profile',
    successOutcome: 'Customer informed about restaurant before ordering'
  },
  {
    id: 'TAV-CUS-ORD-004',
    name: 'Customer changes language on QR landing',
    trigger: 'Customer selects language from dropdown (German/English/Arabic)',
    preconditions: 'Menu has translations for selected language',
    systemAction: 'System switches UI and menu to selected language, saves preference',
    uiUpdates: 'All text updates to chosen language',
    failureStates: 'Translation incomplete, language not supported',
    successOutcome: 'Customer browses menu in preferred language'
  },
  {
    id: 'TAV-CUS-ORD-005',
    name: 'Customer enables accessibility features',
    trigger: 'Customer opens accessibility menu, toggles high contrast or large text',
    preconditions: 'Accessibility menu available',
    systemAction: 'System applies visual adjustments, saves preference to session',
    uiUpdates: 'Menu rerenders with accessibility settings active',
    failureStates: 'Settings conflict with device preferences',
    successOutcome: 'Customer has improved readability'
  },
  {
    id: 'TAV-CUS-ORD-006',
    name: 'Customer joins shared basket',
    trigger: 'Customer scans same table QR as friend already ordering',
    preconditions: 'Table has active shared basket session',
    systemAction: 'System detects existing session, prompts to join or start new',
    uiUpdates: 'Customer sees option to join friend\'s basket with participant count',
    failureStates: 'Session full, session locked for checkout',
    successOutcome: 'Customer joins shared ordering session'
  },
  {
    id: 'TAV-CUS-ORD-007',
    name: 'Customer views special offers on landing',
    trigger: 'Customer lands on menu with active promotions',
    preconditions: 'Restaurant has configured promotions',
    systemAction: 'System displays promo banner at top of menu',
    uiUpdates: 'Customer sees discount badge, happy hour notice, or special deal',
    failureStates: 'Promotion expired but not removed',
    successOutcome: 'Customer aware of current deals'
  },

  // AUTHENTICATION (ACC Domain) - 6 actions
  {
    id: 'TAV-CUS-ACC-001',
    name: 'Guest browses menu without account',
    trigger: 'Customer lands on menu without signing in',
    preconditions: 'Restaurant allows guest ordering',
    systemAction: 'System allows full menu access, prompts for account before checkout',
    uiUpdates: 'Menu fully functional, "Sign in for loyalty points" banner appears',
    failureStates: 'Restaurant requires login',
    successOutcome: 'Guest can browse and add items to basket'
  },
  {
    id: 'TAV-CUS-ACC-002',
    name: 'Customer signs up for account',
    trigger: 'Customer clicks "Sign up", enters email and password',
    preconditions: 'Email not already registered',
    systemAction: 'System creates account, sends verification email, logs in user',
    uiUpdates: 'Welcome message appears, loyalty points shown, order history enabled',
    failureStates: 'Email already exists, weak password, verification email fails',
    successOutcome: 'Customer has active account with loyalty benefits'
  },
  {
    id: 'TAV-CUS-ACC-003',
    name: 'Customer signs in to existing account',
    trigger: 'Customer enters credentials and clicks "Sign in"',
    preconditions: 'Account exists and verified',
    systemAction: 'System authenticates, loads order history and saved preferences',
    uiUpdates: 'Customer sees personalized greeting, loyalty points balance, past orders',
    failureStates: 'Wrong password, account suspended, email not verified',
    successOutcome: 'Customer logged in with full account features'
  },
  {
    id: 'TAV-CUS-ACC-004',
    name: 'Customer requests password reset',
    trigger: 'Customer clicks "Forgot password", enters email',
    preconditions: 'Email associated with existing account',
    systemAction: 'System sends password reset link via email',
    uiUpdates: 'Customer sees confirmation to check email',
    failureStates: 'Email not found, email delivery fails',
    successOutcome: 'Customer receives reset link'
  },
  {
    id: 'TAV-CUS-ACC-005',
    name: 'Customer signs in with Google',
    trigger: 'Customer clicks "Continue with Google"',
    preconditions: 'OAuth configured, customer has Google account',
    systemAction: 'System redirects to Google, receives auth token, creates or links account',
    uiUpdates: 'Google consent screen appears, then customer returns logged in',
    failureStates: 'OAuth cancelled, Google account email already used with password login',
    successOutcome: 'Customer authenticated via Google'
  },
  {
    id: 'TAV-CUS-ACC-006',
    name: 'Customer proceeds as guest at checkout',
    trigger: 'Guest clicks "Continue as guest" at checkout',
    preconditions: 'Restaurant allows guest checkout',
    systemAction: 'System prompts for name and phone number only, skips loyalty points',
    uiUpdates: 'Simplified form appears, no password required',
    failureStates: 'Restaurant requires account for orders',
    successOutcome: 'Guest can complete order without registration'
  },

  // MENU BROWSING (ORD Domain) - 8 actions
  {
    id: 'TAV-CUS-ORD-008',
    name: 'Customer views menu categories',
    trigger: 'Customer lands on menu page',
    preconditions: 'Restaurant has published menu with categories',
    systemAction: 'System loads categories (Starters, Mains, Desserts, Drinks) with dish counts',
    uiUpdates: 'Customer sees category tabs or list with item counts',
    failureStates: 'No dishes published, all items out of stock',
    successOutcome: 'Customer can navigate menu by category'
  },
  {
    id: 'TAV-CUS-ORD-009',
    name: 'Customer filters menu by dietary preference',
    trigger: 'Customer selects filter: vegetarian, vegan, gluten-free, etc.',
    preconditions: 'Dishes tagged with dietary attributes',
    systemAction: 'System filters menu to show only matching items',
    uiUpdates: 'Menu updates, filter badge shows active filters, count updates',
    failureStates: 'No dishes match filter',
    successOutcome: 'Customer sees only compatible dishes'
  },
  {
    id: 'TAV-CUS-ORD-010',
    name: 'Customer searches menu',
    trigger: 'Customer types in search bar',
    preconditions: 'Menu has searchable dish names and descriptions',
    systemAction: 'System performs real-time search across names, ingredients, descriptions',
    uiUpdates: 'Results update as customer types, highlights matching text',
    failureStates: 'No matches found',
    successOutcome: 'Customer finds specific dish quickly'
  },
  {
    id: 'TAV-CUS-ORD-011',
    name: 'Customer views dish card on menu',
    trigger: 'Customer scrolls through menu',
    preconditions: 'Dishes configured with photos and basic info',
    systemAction: 'System displays dish cards: photo, name, price, dietary badges, rating',
    uiUpdates: 'Customer sees grid or list of appetizing dish cards',
    failureStates: 'Images fail to load, prices missing',
    successOutcome: 'Customer browses visually appealing menu'
  },
  {
    id: 'TAV-CUS-ORD-012',
    name: 'Customer sees AI-recommended dishes',
    trigger: 'Customer views menu with AI insights enabled',
    preconditions: 'Restaurant has AI features active, sufficient order history',
    systemAction: 'System highlights popular dishes, trending items, or personalized suggestions',
    uiUpdates: 'Special badges appear: "Most ordered", "Trending", "You might like"',
    failureStates: 'Insufficient data for recommendations',
    successOutcome: 'Customer discovers popular or relevant dishes'
  },
  {
    id: 'TAV-CUS-ORD-013',
    name: 'Customer views AI review summary',
    trigger: 'Customer sees review summary on dish card or detail',
    preconditions: 'Dish has multiple reviews, AI analysis complete',
    systemAction: 'System shows AI-generated summary: common praise, criticisms, highlights',
    uiUpdates: 'Customer sees concise bullets instead of reading all reviews',
    failureStates: 'Insufficient reviews, AI service down',
    successOutcome: 'Customer quickly understands dish reputation'
  },
  {
    id: 'TAV-CUS-ORD-014',
    name: 'Customer toggles nutrition information display',
    trigger: 'Customer enables "Show nutrition info" in menu settings',
    preconditions: 'Restaurant provided nutrition data',
    systemAction: 'System displays calories, allergens, macros on dish cards',
    uiUpdates: 'Nutrition badges appear below each dish',
    failureStates: 'Data incomplete for some dishes',
    successOutcome: 'Customer makes informed health-conscious choices'
  },
  {
    id: 'TAV-CUS-ORD-015',
    name: 'Customer views out-of-stock indicator',
    trigger: 'Dish becomes unavailable (vendor marked out of stock)',
    preconditions: 'Inventory tracking enabled',
    systemAction: 'System grays out dish card, shows "Currently unavailable" badge',
    uiUpdates: 'Customer cannot add to basket, sees alternative suggestions',
    failureStates: 'Stock status not updated in real-time',
    successOutcome: 'Customer aware of availability before selecting'
  },

  // DISH CUSTOMIZATION (ORD Domain) - 8 actions
  {
    id: 'TAV-CUS-ORD-016',
    name: 'Customer opens dish detail modal',
    trigger: 'Customer taps dish card',
    preconditions: 'Dish configured with full details',
    systemAction: 'System loads modal with large photo, full description, reviews, customization options',
    uiUpdates: 'Full-screen modal appears with dish information',
    failureStates: 'Image fails to load',
    successOutcome: 'Customer sees comprehensive dish information'
  },
  {
    id: 'TAV-CUS-ORD-017',
    name: 'Customer views dish ingredients',
    trigger: 'Customer expands ingredients section in dish detail',
    preconditions: 'Restaurant configured ingredient list',
    systemAction: 'System displays all ingredients with allergen highlights',
    uiUpdates: 'Expandable list shows ingredients, allergens in bold or red',
    failureStates: 'Ingredient data missing',
    successOutcome: 'Customer verifies allergens and ingredients'
  },
  {
    id: 'TAV-CUS-ORD-018',
    name: 'Customer selects dish size',
    trigger: 'Customer chooses from size options (Small/Regular/Large)',
    preconditions: 'Dish configured with size variants',
    systemAction: 'System updates price based on selected size',
    uiUpdates: 'Size selector highlights choice, price updates',
    failureStates: 'Size out of stock',
    successOutcome: 'Customer selects preferred portion size'
  },
  {
    id: 'TAV-CUS-ORD-019',
    name: 'Customer adds ingredient modifications',
    trigger: 'Customer selects "No onions", "Extra cheese", etc.',
    preconditions: 'Dish allows customization',
    systemAction: 'System tracks modifications, adds extra charges if applicable',
    uiUpdates: 'Checkboxes or toggles update, price adjusts for paid additions',
    failureStates: 'Modification conflicts with dish preparation',
    successOutcome: 'Customer personalizes dish to preferences'
  },
  {
    id: 'TAV-CUS-ORD-020',
    name: 'Customer adds special instructions',
    trigger: 'Customer types in "Special requests" text field',
    preconditions: 'Restaurant accepts special instructions',
    systemAction: 'System attaches note to order item (e.g., "No spicy", "Well done")',
    uiUpdates: 'Text field shows character count, preview of note',
    failureStates: 'Character limit exceeded',
    successOutcome: 'Kitchen receives customer\'s specific requests'
  },
  {
    id: 'TAV-CUS-ORD-021',
    name: 'Customer adjusts quantity',
    trigger: 'Customer uses +/- buttons to change quantity',
    preconditions: 'Customer in dish detail or basket',
    systemAction: 'System multiplies item price, updates total',
    uiUpdates: 'Quantity counter updates, subtotal recalculates',
    failureStates: 'Quantity exceeds stock availability',
    successOutcome: 'Customer orders multiple of same item'
  },
  {
    id: 'TAV-CUS-ORD-022',
    name: 'Customer adds configured dish to basket',
    trigger: 'Customer clicks "Add to basket" after customization',
    preconditions: 'Dish configured with valid price',
    systemAction: 'System adds item to basket with all modifications, updates basket count',
    uiUpdates: 'Basket icon shows badge with item count, success animation plays',
    failureStates: 'Item out of stock, price error',
    successOutcome: 'Item added to basket, customer can continue browsing'
  },
  {
    id: 'TAV-CUS-ORD-023',
    name: 'Customer views dish reviews before adding',
    trigger: 'Customer scrolls to reviews section in dish detail',
    preconditions: 'Dish has customer reviews',
    systemAction: 'System displays recent reviews with ratings, photos, AI summary',
    uiUpdates: 'Customer sees star ratings, review text, helpful vote counts',
    failureStates: 'No reviews yet',
    successOutcome: 'Customer makes informed decision based on reviews'
  },

  // BASKET MANAGEMENT (ORD Domain) - 6 actions
  {
    id: 'TAV-CUS-ORD-024',
    name: 'Customer opens basket',
    trigger: 'Customer taps basket icon',
    preconditions: 'At least one item in basket',
    systemAction: 'System displays all basket items with customizations and prices',
    uiUpdates: 'Basket sheet slides up showing itemized list and total',
    failureStates: 'Basket empty',
    successOutcome: 'Customer reviews order before checkout'
  },
  {
    id: 'TAV-CUS-ORD-025',
    name: 'Customer edits item in basket',
    trigger: 'Customer taps "Edit" on basket item',
    preconditions: 'Item exists in basket',
    systemAction: 'System reopens dish detail with current modifications pre-selected',
    uiUpdates: 'Dish modal shows, "Update" button replaces "Add to basket"',
    failureStates: 'Dish no longer available',
    successOutcome: 'Customer modifies order item'
  },
  {
    id: 'TAV-CUS-ORD-026',
    name: 'Customer removes item from basket',
    trigger: 'Customer taps trash icon or swipes to delete',
    preconditions: 'Item in basket',
    systemAction: 'System removes item, recalculates total',
    uiUpdates: 'Item disappears with animation, total updates, basket count decreases',
    failureStates: 'Basket becomes empty',
    successOutcome: 'Unwanted item removed from order'
  },
  {
    id: 'TAV-CUS-ORD-027',
    name: 'Customer sees basket validation errors',
    trigger: 'Item in basket becomes unavailable while browsing',
    preconditions: 'Stock changed or restaurant closed',
    systemAction: 'System flags problematic items, prevents checkout',
    uiUpdates: 'Error badge appears on basket, affected items highlighted in red',
    failureStates: 'Multiple items unavailable',
    successOutcome: 'Customer removes or replaces unavailable items'
  },
  {
    id: 'TAV-CUS-ORD-028',
    name: 'Customer views basket summary',
    trigger: 'Customer reviews basket before checkout',
    preconditions: 'Items in basket',
    systemAction: 'System calculates subtotal, estimated tax, service fee, total',
    uiUpdates: 'Breakdown shows: items subtotal, +tax, +fees, = total',
    failureStates: 'Tax calculation error',
    successOutcome: 'Customer understands exact charges before ordering'
  },
  {
    id: 'TAV-CUS-ORD-029',
    name: 'Customer applies promo code',
    trigger: 'Customer enters code in basket promo field',
    preconditions: 'Restaurant has active promotions',
    systemAction: 'System validates code, applies discount, recalculates total',
    uiUpdates: 'Discount line appears in summary, total updates, success message shows',
    failureStates: 'Invalid code, expired, minimum order not met',
    successOutcome: 'Discount applied to order'
  }
];
