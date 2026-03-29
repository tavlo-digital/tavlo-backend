import { ActionStory } from './ActionStoryTemplate';

/**
 * Customer Actions Part 2 (30 actions)
 * Payment Flow, Order Tracking, Reviews, Account Management
 */

export const customerActionsPart2: ActionStory[] = [
  // PAYMENT FLOW (PAY Domain) - 10 actions
  {
    id: 'TAV-CUS-PAY-001',
    name: 'Customer proceeds to checkout',
    trigger: 'Customer clicks "Checkout" from basket',
    preconditions: 'Basket has items, no validation errors',
    systemAction: 'System transitions to checkout, verifies restaurant accepting orders',
    uiUpdates: 'Checkout screen appears with order summary and payment options',
    failureStates: 'Restaurant closed, kitchen stopped accepting orders',
    successOutcome: 'Customer ready to select payment method'
  },
  {
    id: 'TAV-CUS-PAY-002',
    name: 'Customer selects payment method',
    trigger: 'Customer chooses card payment or cash',
    preconditions: 'Restaurant accepts selected payment type',
    systemAction: 'System prepares appropriate payment flow',
    uiUpdates: 'Payment method highlighted, next step button enabled',
    failureStates: 'Payment method not supported by restaurant',
    successOutcome: 'Customer ready to confirm order'
  },
  {
    id: 'TAV-CUS-PAY-003',
    name: 'Customer enters card details',
    trigger: 'Customer selects card payment, enters Stripe form',
    preconditions: 'Stripe integrated and active',
    systemAction: 'System loads Stripe Elements, validates card info',
    uiUpdates: 'Secure card form appears with real-time validation',
    failureStates: 'Invalid card number, expired card, Stripe unavailable',
    successOutcome: 'Card details validated and ready for payment'
  },
  {
    id: 'TAV-CUS-PAY-004',
    name: 'Customer completes card payment',
    trigger: 'Customer clicks "Pay now"',
    preconditions: 'Valid card details entered, order total calculated',
    systemAction: 'System processes payment via Stripe, creates order on success',
    uiUpdates: 'Loading indicator, then success screen with order number',
    failureStates: 'Payment declined, insufficient funds, network error',
    successOutcome: 'Payment successful, order sent to kitchen'
  },
  {
    id: 'TAV-CUS-PAY-005',
    name: 'Customer selects cash payment',
    trigger: 'Customer chooses "Pay with cash at table"',
    preconditions: 'Restaurant allows cash payments',
    systemAction: 'System creates order without payment processing, notifies staff',
    uiUpdates: 'Order confirmed, message shows "Pay server when delivered"',
    failureStates: 'Cash payment disabled',
    successOutcome: 'Order sent to kitchen, customer pays on delivery'
  },
  {
    id: 'TAV-CUS-PAY-006',
    name: 'Customer initiates split bill',
    trigger: 'Customer clicks "Split bill" in shared basket',
    preconditions: 'Multiple people in shared basket session',
    systemAction: 'System calculates each person\'s items, creates individual payment requests',
    uiUpdates: 'Split breakdown shows who owes what, payment links generated',
    failureStates: 'Cannot split certain items, only one person in session',
    successOutcome: 'Each person pays their portion separately'
  },
  {
    id: 'TAV-CUS-PAY-007',
    name: 'Customer pays their split portion',
    trigger: 'Customer clicks "Pay my share" in split bill',
    preconditions: 'Split calculated, customer has items assigned',
    systemAction: 'System processes payment for individual portion only',
    uiUpdates: 'Payment form shows only customer\'s items and total',
    failureStates: 'Payment fails, amount mismatch',
    successOutcome: 'Customer\'s portion paid, system waits for others'
  },
  {
    id: 'TAV-CUS-PAY-008',
    name: 'Customer views VAT breakdown on receipt',
    trigger: 'Payment completed, customer views receipt',
    preconditions: 'Austrian VAT rules applied',
    systemAction: 'System displays itemized receipt with VAT rates (10%, 13%, 20%)',
    uiUpdates: 'Receipt shows subtotal, VAT by rate, total in compliance format',
    failureStates: 'VAT calculation error',
    successOutcome: 'Customer receives tax-compliant receipt'
  },
  {
    id: 'TAV-CUS-PAY-009',
    name: 'Customer downloads receipt PDF',
    trigger: 'Customer clicks "Download receipt"',
    preconditions: 'Order completed and paid',
    systemAction: 'System generates PDF with order details, VAT breakdown, timestamps',
    uiUpdates: 'PDF downloads to device',
    failureStates: 'PDF generation fails',
    successOutcome: 'Customer has digital receipt for records'
  },
  {
    id: 'TAV-CUS-PAY-010',
    name: 'Customer retries failed payment',
    trigger: 'Payment declined, customer clicks "Try again"',
    preconditions: 'Order still active, items available',
    systemAction: 'System allows new payment attempt with same or different method',
    uiUpdates: 'Payment form reappears, error message cleared',
    failureStates: 'Order expired, items now out of stock',
    successOutcome: 'Customer successfully completes payment on retry'
  },

  // ORDER TRACKING (ORD Domain) - 8 actions
  {
    id: 'TAV-CUS-ORD-030',
    name: 'Customer views order confirmation',
    trigger: 'Payment successful',
    preconditions: 'Order created in system',
    systemAction: 'System displays order number, estimated time, tracking link',
    uiUpdates: 'Success screen shows order details and "Track order" button',
    failureStates: 'Order creation fails after payment',
    successOutcome: 'Customer has order confirmation and tracking access'
  },
  {
    id: 'TAV-CUS-ORD-031',
    name: 'Customer tracks order status',
    trigger: 'Customer opens order tracking screen',
    preconditions: 'Order exists with active status',
    systemAction: 'System displays real-time status: Received → Preparing → Ready → Delivered',
    uiUpdates: 'Progress bar updates as kitchen changes status',
    failureStates: 'Status not updated by kitchen',
    successOutcome: 'Customer knows when food will arrive'
  },
  {
    id: 'TAV-CUS-ORD-032',
    name: 'Customer receives order status notification',
    trigger: 'Kitchen updates order to "Ready" or "Out for delivery"',
    preconditions: 'Customer has notifications enabled',
    systemAction: 'System sends push notification or SMS',
    uiUpdates: 'Notification appears on phone, tracking screen auto-updates',
    failureStates: 'Notification delivery fails, customer disabled notifications',
    successOutcome: 'Customer alerted to order progress'
  },
  {
    id: 'TAV-CUS-ORD-033',
    name: 'Customer views estimated preparation time',
    trigger: 'Order placed',
    preconditions: 'Restaurant configured average prep times',
    systemAction: 'System calculates ETA based on current kitchen load and dish complexity',
    uiUpdates: 'Countdown timer shows "Ready in ~15 minutes"',
    failureStates: 'Time estimate inaccurate due to kitchen delays',
    successOutcome: 'Customer has realistic expectation of wait time'
  },
  {
    id: 'TAV-CUS-ORD-034',
    name: 'Customer contacts restaurant about order',
    trigger: 'Customer clicks "Contact restaurant" from tracking screen',
    preconditions: 'Restaurant contact info available',
    systemAction: 'System displays phone number or opens messaging interface',
    uiUpdates: 'Contact modal appears with call button or chat',
    failureStates: 'Restaurant contact info missing',
    successOutcome: 'Customer can communicate with restaurant'
  },
  {
    id: 'TAV-CUS-ORD-035',
    name: 'Customer cancels order before preparation',
    trigger: 'Customer clicks "Cancel order" immediately after placing',
    preconditions: 'Order not yet accepted by kitchen (within 2-minute window)',
    systemAction: 'System cancels order, initiates automatic refund if paid',
    uiUpdates: 'Cancellation confirmation appears, refund notice shown',
    failureStates: 'Kitchen already started preparing, cancellation window expired',
    successOutcome: 'Order cancelled, refund processed'
  },
  {
    id: 'TAV-CUS-ORD-036',
    name: 'Customer marks order as received',
    trigger: 'Food delivered, customer clicks "Order received"',
    preconditions: 'Order status was "Delivered"',
    systemAction: 'System marks order complete, prompts for review',
    uiUpdates: 'Order moves to history, review invitation appears',
    failureStates: 'Order already marked complete',
    successOutcome: 'Order finalized, customer can leave review'
  },
  {
    id: 'TAV-CUS-ORD-037',
    name: 'Customer views order history',
    trigger: 'Customer opens "My Orders" from account menu',
    preconditions: 'Customer has account with past orders',
    systemAction: 'System loads all previous orders with dates, totals, statuses',
    uiUpdates: 'List of past orders appears, newest first',
    failureStates: 'No order history',
    successOutcome: 'Customer can review past orders and reorder'
  },

  // REVIEWS (REV Domain) - 7 actions
  {
    id: 'TAV-CUS-REV-001',
    name: 'Customer opens review form',
    trigger: 'Customer clicks "Leave a review" after order completion',
    preconditions: 'Order marked as received or delivered',
    systemAction: 'System opens review form with order details pre-filled',
    uiUpdates: 'Review modal appears with star rating and text fields',
    failureStates: 'Order too old to review (>30 days)',
    successOutcome: 'Customer ready to submit review'
  },
  {
    id: 'TAV-CUS-REV-002',
    name: 'Customer rates overall experience',
    trigger: 'Customer selects star rating (1-5)',
    preconditions: 'Review form open',
    systemAction: 'System records rating, enables text review field',
    uiUpdates: 'Stars highlight, encouragement text appears for elaboration',
    failureStates: 'None',
    successOutcome: 'Overall rating captured'
  },
  {
    id: 'TAV-CUS-REV-003',
    name: 'Customer writes review text',
    trigger: 'Customer types in review text box',
    preconditions: 'Star rating selected',
    systemAction: 'System validates text length, checks for inappropriate content',
    uiUpdates: 'Character counter updates, AI suggests helpful topics',
    failureStates: 'Text contains profanity or spam patterns',
    successOutcome: 'Detailed review text ready to submit'
  },
  {
    id: 'TAV-CUS-REV-004',
    name: 'Customer uploads review photos',
    trigger: 'Customer clicks "Add photo", selects images',
    preconditions: 'Review form allows photos',
    systemAction: 'System uploads images, validates format and size',
    uiUpdates: 'Photo thumbnails appear in review preview',
    failureStates: 'File too large, invalid format',
    successOutcome: 'Photos attached to review'
  },
  {
    id: 'TAV-CUS-REV-005',
    name: 'Customer submits review',
    trigger: 'Customer clicks "Submit review"',
    preconditions: 'Minimum rating and text provided',
    systemAction: 'System publishes review, updates restaurant rating, awards loyalty points',
    uiUpdates: 'Thank you message appears, loyalty points notification shows',
    failureStates: 'Duplicate review, content moderation flags issue',
    successOutcome: 'Review published, visible to other customers'
  },
  {
    id: 'TAV-CUS-REV-006',
    name: 'Customer edits published review',
    trigger: 'Customer clicks "Edit" on their review in order history',
    preconditions: 'Review exists, within 30-day edit window',
    systemAction: 'System loads review form with current content',
    uiUpdates: 'Review form appears with existing text and rating',
    failureStates: 'Edit window expired',
    successOutcome: 'Customer updates review'
  },
  {
    id: 'TAV-CUS-REV-007',
    name: 'Customer deletes review',
    trigger: 'Customer clicks "Delete review" with confirmation',
    preconditions: 'Customer owns review',
    systemAction: 'System removes review, recalculates restaurant rating, revokes loyalty points',
    uiUpdates: 'Review disappears, loyalty points deducted notification',
    failureStates: 'Review already deleted',
    successOutcome: 'Review removed from platform'
  },

  // ACCOUNT MANAGEMENT (ACC Domain) - 5 actions
  {
    id: 'TAV-CUS-ACC-007',
    name: 'Customer views loyalty points balance',
    trigger: 'Customer opens account page',
    preconditions: 'Customer has account',
    systemAction: 'System displays total points, points history, redemption options',
    uiUpdates: 'Points balance shown with earn/redeem breakdown',
    failureStates: 'Points calculation error',
    successOutcome: 'Customer aware of loyalty rewards'
  },
  {
    id: 'TAV-CUS-ACC-008',
    name: 'Customer redeems loyalty points',
    trigger: 'Customer clicks "Use points" at checkout',
    preconditions: 'Sufficient points for redemption threshold',
    systemAction: 'System converts points to discount, deducts from balance',
    uiUpdates: 'Discount applied to total, points balance updates',
    failureStates: 'Insufficient points, redemption disabled for this order',
    successOutcome: 'Points redeemed for order discount'
  },
  {
    id: 'TAV-CUS-ACC-009',
    name: 'Customer updates profile information',
    trigger: 'Customer edits name, phone, email in account settings',
    preconditions: 'Customer logged in',
    systemAction: 'System validates new information, updates account',
    uiUpdates: 'Success message appears, profile displays updated info',
    failureStates: 'Email already in use, invalid phone format',
    successOutcome: 'Profile information updated'
  },
  {
    id: 'TAV-CUS-ACC-010',
    name: 'Customer manages saved restaurants',
    trigger: 'Customer favorites or unfavorites restaurant',
    preconditions: 'Customer has account',
    systemAction: 'System adds or removes restaurant from saved list',
    uiUpdates: 'Heart icon toggles, saved list updates',
    failureStates: 'None',
    successOutcome: 'Customer can quickly access favorite restaurants'
  },
  {
    id: 'TAV-CUS-ACC-011',
    name: 'Customer deletes account',
    trigger: 'Customer requests account deletion (GDPR right to be forgotten)',
    preconditions: 'No active orders',
    systemAction: 'System anonymizes personal data, retains transaction records per legal requirements',
    uiUpdates: 'Confirmation modal appears, account deleted after final confirmation',
    failureStates: 'Active orders prevent deletion, legal hold on data',
    successOutcome: 'Account deleted, customer logged out'
  }
];
