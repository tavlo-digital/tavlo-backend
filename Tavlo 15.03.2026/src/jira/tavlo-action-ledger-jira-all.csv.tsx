Work Item ID,Issue Type,Summary,Description,Parent,Labels,Priority
E-001,Epic,Vendor Onboarding,Journey covering all actions in Vendor Onboarding.,,epic,onboarding,Medium
E-002,Epic,Vendor Subscription,Journey covering all actions in Vendor Subscription.,,epic,subscription,High
E-003,Epic,Customer Ordering,Journey covering all actions in Customer Ordering.,,epic,ordering,High
E-004,Epic,Reviews,Journey covering all actions in Reviews.,,epic,reviews,Low
E-005,Epic,Admin Control,Journey covering all actions in Admin Control.,,epic,admin,Medium
E-006,Epic,Platform Core,Journey covering all actions in Platform Core.,,epic,platform,Medium
A-001,Task,TAV-ADM-ACC-001 Admin reviews vendor registration,"Implements Action Story TAV-ADM-ACC-001.

Trigger:
New vendor completes registration form

System Action:
System flags application for admin review, validates submitted documents

Failure States:
Missing documents, invalid business registration, duplicate restaurant",E-001,"action:tav-adm-acc-001,admin,acc",Medium
A-002,Task,TAV-ADM-ACC-002 Admin approves vendor account,"Implements Action Story TAV-ADM-ACC-002.

Trigger:
Admin clicks Approve on vendor application

System Action:
System activates vendor account, sends welcome email, creates Stripe account link

Failure States:
Email delivery fails, Stripe connection error",E-001,"action:tav-adm-acc-002,admin,acc",High
A-003,Task,TAV-ADM-ACC-003 Admin rejects vendor application,"Implements Action Story TAV-ADM-ACC-003.

Trigger:
Admin clicks Reject with reason

System Action:
System marks application rejected, logs reason, sends notification to vendor

Failure States:
Notification delivery fails",E-001,"action:tav-adm-acc-003,admin,acc",Medium
A-004,Task,TAV-ADM-ACC-004 Admin suspends vendor account,"Implements Action Story TAV-ADM-ACC-004.

Trigger:
Admin initiates suspension due to policy violation

System Action:
System disables vendor dashboard access, hides restaurant from customer app, notifies vendor

Failure States:
Active orders exist, notification fails",E-001,"action:tav-adm-acc-004,admin,acc",High
A-005,Task,TAV-ADM-ACC-005 Admin reactivates suspended vendor,"Implements Action Story TAV-ADM-ACC-005.

Trigger:
Admin lifts suspension after issue resolved

System Action:
System restores vendor access, makes restaurant visible again, sends reactivation notice

Failure States:
Outstanding compliance issues remain",E-001,"action:tav-adm-acc-005,admin,acc",Medium
A-006,Task,TAV-ADM-LEG-001 Admin reviews vendor legal documents,"Implements Action Story TAV-ADM-LEG-001.

Trigger:
Vendor uploads business license, tax certificates, insurance

System Action:
System validates document formats, flags expiration dates, queues for admin review

Failure States:
Invalid file format, expired documents, missing required fields",E-001,"action:tav-adm-leg-001,admin,leg",Medium
A-007,Task,TAV-ADM-LEG-002 Admin flags expired vendor documents,"Implements Action Story TAV-ADM-LEG-002.

Trigger:
System detects document expiring within 30 days

System Action:
System sends warning to vendor, creates admin alert, logs compliance issue

Failure States:
Notification delivery fails",E-001,"action:tav-adm-leg-002,admin,leg",Medium
A-008,Task,TAV-ADM-LEG-003 Admin enforces GDPR data deletion,"Implements Action Story TAV-ADM-LEG-003.

Trigger:
Customer or vendor requests account deletion (right to be forgotten)

System Action:
System anonymizes personal data, archives required transaction records, deletes unnecessary data

Failure States:
Active orders prevent deletion, legal hold on data",E-001,"action:tav-adm-leg-003,admin,leg",High
A-009,Task,TAV-ADM-BIL-001 Admin monitors vendor subscription status,"Implements Action Story TAV-ADM-BIL-001.

Trigger:
Admin views vendor billing dashboard

System Action:
System displays subscription tiers, payment status, upcoming renewals

Failure States:
Billing data sync fails",E-002,"action:tav-adm-bil-001,admin,bil",Medium
A-010,Task,TAV-ADM-BIL-002 Admin handles failed vendor subscription payment,"Implements Action Story TAV-ADM-BIL-002.

Trigger:
Vendor subscription payment fails

System Action:
System retries payment, sends vendor notification, flags account for admin review

Failure States:
All retry attempts fail, vendor unresponsive",E-002,"action:tav-adm-bil-002,admin,bil",High
A-011,Task,TAV-ADM-BIL-003 Admin reviews platform commission transactions,"Implements Action Story TAV-ADM-BIL-003.

Trigger:
Admin accesses financial reports

System Action:
System calculates commission from each order, aggregates by vendor and period

Failure States:
Transaction data incomplete",E-002,"action:tav-adm-bil-003,admin,bil",High
A-012,Task,TAV-ADM-BIL-004 Admin processes vendor payout,"Implements Action Story TAV-ADM-BIL-004.

Trigger:
End of payout period (daily/weekly based on vendor tier)

System Action:
System calculates vendor earnings minus commission, initiates Stripe transfer

Failure States:
Insufficient funds, Stripe account issue, pending disputes",E-002,"action:tav-adm-bil-004,admin,bil",High
A-013,Task,TAV-ADM-BIL-005 Admin handles payment dispute,"Implements Action Story TAV-ADM-BIL-005.

Trigger:
Customer initiates chargeback or dispute

System Action:
System holds vendor payout, notifies admin and vendor, collects evidence

Failure States:
Evidence deadline missed, insufficient documentation",E-002,"action:tav-adm-bil-005,admin,bil",High
A-014,Task,TAV-ADM-ADM-001 Admin reviews flagged customer review,"Implements Action Story TAV-ADM-ADM-001.

Trigger:
Review flagged by AI or vendor as inappropriate

System Action:
System queues review for admin moderation, temporarily hides from public if severe

Failure States:
Insufficient context to make decision",E-005,"action:tav-adm-adm-001,admin,adm",Medium
A-015,Task,TAV-ADM-ADM-002 Admin removes policy-violating review,"Implements Action Story TAV-ADM-ADM-002.

Trigger:
Admin confirms review violates content policy

System Action:
System deletes review, notifies author, logs moderation action

Failure States:
Notification delivery fails",E-005,"action:tav-adm-adm-002,admin,adm",Medium
A-016,Task,TAV-ADM-ADM-003 Admin moderates vendor menu content,"Implements Action Story TAV-ADM-ADM-003.

Trigger:
Vendor uploads menu with potentially problematic content

System Action:
System holds menu update for review, notifies vendor of flagged items

Failure States:
False positive flags, ambiguous content",E-005,"action:tav-adm-adm-003,admin,adm",Medium
A-017,Task,TAV-ADM-ADM-004 Admin handles customer complaint escalation,"Implements Action Story TAV-ADM-ADM-004.

Trigger:
Customer escalates unresolved issue to platform support

System Action:
System creates support ticket, pulls order history, notifies admin

Failure States:
Missing order data, customer contact info invalid",E-005,"action:tav-adm-adm-004,admin,adm",High
A-018,Task,TAV-ADM-ADM-005 Admin issues refund on behalf of vendor,"Implements Action Story TAV-ADM-ADM-005.

Trigger:
Admin determines customer deserves refund, vendor unresponsive

System Action:
System processes refund through Stripe, deducts from vendor balance

Failure States:
Vendor account insufficient balance, refund window expired",E-005,"action:tav-adm-adm-005,admin,adm",High
A-019,Task,TAV-ADM-SYS-001 Admin monitors platform health metrics,"Implements Action Story TAV-ADM-SYS-001.

Trigger:
Admin accesses system dashboard

System Action:
System displays real-time metrics: uptime, API response times, error rates

Failure States:
Monitoring service down",E-006,"action:tav-adm-sys-001,admin,sys",Medium
A-020,Task,TAV-ADM-SYS-002 System auto-scales for high traffic,"Implements Action Story TAV-ADM-SYS-002.

Trigger:
Order volume exceeds normal threshold

System Action:
System provisions additional server capacity, balances load

Failure States:
Scaling limit reached, infrastructure quota exceeded",E-006,"action:tav-adm-sys-002,system,sys",High
A-021,Task,TAV-ADM-SYS-003 Admin investigates payment processing failure,"Implements Action Story TAV-ADM-SYS-003.

Trigger:
Multiple payment failures detected in short period

System Action:
System aggregates failure reasons, checks Stripe status, alerts admin

Failure States:
Insufficient logging data",E-006,"action:tav-adm-sys-003,admin,sys",High
A-022,Task,TAV-ADM-SYS-004 Admin reviews audit logs,"Implements Action Story TAV-ADM-SYS-004.

Trigger:
Admin accesses audit log for compliance or investigation

System Action:
System displays filterable log of all admin actions with timestamps and actors

Failure States:
Log data corrupted or incomplete",E-006,"action:tav-adm-sys-004,admin,sys",Medium
A-023,Task,TAV-ADM-ADM-006 Admin generates platform analytics report,"Implements Action Story TAV-ADM-ADM-006.

Trigger:
Admin requests metrics report (weekly/monthly)

System Action:
System aggregates orders, revenue, customer growth, vendor performance

Failure States:
Data aggregation timeout, incomplete records",E-005,"action:tav-adm-adm-006,admin,adm",Medium
A-024,Task,TAV-ADM-ADM-007 Admin identifies underperforming vendors,"Implements Action Story TAV-ADM-ADM-007.

Trigger:
Admin reviews vendor performance metrics

System Action:
System calculates order volume, customer ratings, complaint rate per vendor

Failure States:
Insufficient data for new vendors",E-005,"action:tav-adm-adm-007,admin,adm",Medium
A-025,Task,TAV-ADM-ADM-008 Admin exports tax compliance report,"Implements Action Story TAV-ADM-ADM-008.

Trigger:
Admin prepares for tax filing or audit

System Action:
System generates report showing total VAT collected, broken down by rate and vendor

Failure States:
VAT calculation errors, missing transaction records",E-005,"action:tav-adm-adm-008,admin,adm",High
A-026,Task,TAV-CUS-ORD-001 Customer scans QR code at table,"Implements Action Story TAV-CUS-ORD-001.

Trigger:
Customer scans table QR code with phone camera

System Action:
System identifies restaurant and table number, loads menu interface

Failure States:
Invalid QR code, restaurant offline, menu not published",E-003,"action:tav-cus-ord-001,customer,ord",High
A-027,Task,TAV-CUS-ORD-002 Customer selects dining mode,"Implements Action Story TAV-CUS-ORD-002.

Trigger:
Customer chooses Dine-in or Takeaway after scanning QR

System Action:
System sets order mode, adjusts menu availability and pricing if needed

Failure States:
Mode not supported by restaurant",E-003,"action:tav-cus-ord-002,customer,ord",Medium
A-028,Task,TAV-CUS-ORD-003 Customer views restaurant info from QR landing,"Implements Action Story TAV-CUS-ORD-003.

Trigger:
Customer taps restaurant info button

System Action:
System displays restaurant details: hours, address, ratings, photos

Failure States:
Incomplete restaurant profile",E-003,"action:tav-cus-ord-003,customer,ord",Low
A-029,Task,TAV-CUS-ORD-004 Customer changes language on QR landing,"Implements Action Story TAV-CUS-ORD-004.

Trigger:
Customer selects language from dropdown (German/English/Arabic)

System Action:
System switches UI and menu to selected language, saves preference

Failure States:
Translation incomplete, language not supported",E-003,"action:tav-cus-ord-004,customer,ord",Medium
A-030,Task,TAV-CUS-ORD-005 Customer enables accessibility features,"Implements Action Story TAV-CUS-ORD-005.

Trigger:
Customer opens accessibility menu, toggles high contrast or large text

System Action:
System applies visual adjustments, saves preference to session

Failure States:
Settings conflict with device preferences",E-003,"action:tav-cus-ord-005,customer,ord",Medium
A-031,Task,TAV-CUS-ORD-006 Customer joins shared basket,"Implements Action Story TAV-CUS-ORD-006.

Trigger:
Customer scans same table QR as friend already ordering

System Action:
System detects existing session, prompts to join or start new

Failure States:
Session full, session locked for checkout",E-003,"action:tav-cus-ord-006,customer,ord",Medium
A-032,Task,TAV-CUS-ORD-007 Customer views special offers on landing,"Implements Action Story TAV-CUS-ORD-007.

Trigger:
Customer lands on menu with active promotions

System Action:
System displays promo banner at top of menu

Failure States:
Promotion expired but not removed",E-003,"action:tav-cus-ord-007,customer,ord",Low
A-033,Task,TAV-CUS-ACC-001 Guest browses menu without account,"Implements Action Story TAV-CUS-ACC-001.

Trigger:
Customer lands on menu without signing in

System Action:
System allows full menu access, prompts for account before checkout

Failure States:
Restaurant requires login",E-003,"action:tav-cus-acc-001,customer,acc",Medium
A-034,Task,TAV-CUS-ACC-002 Customer signs up for account,"Implements Action Story TAV-CUS-ACC-002.

Trigger:
Customer clicks Sign up, enters email and password

System Action:
System creates account, sends verification email, logs in user

Failure States:
Email already exists, weak password, verification email fails",E-003,"action:tav-cus-acc-002,customer,acc",High
A-035,Task,TAV-CUS-ACC-003 Customer signs in to existing account,"Implements Action Story TAV-CUS-ACC-003.

Trigger:
Customer enters credentials and clicks Sign in

System Action:
System authenticates, loads order history and saved preferences

Failure States:
Wrong password, account suspended, email not verified",E-003,"action:tav-cus-acc-003,customer,acc",High
A-036,Task,TAV-CUS-ACC-004 Customer requests password reset,"Implements Action Story TAV-CUS-ACC-004.

Trigger:
Customer clicks Forgot password, enters email

System Action:
System sends password reset link via email

Failure States:
Email not found, email delivery fails",E-003,"action:tav-cus-acc-004,customer,acc",Medium
A-037,Task,TAV-CUS-ACC-005 Customer signs in with Google,"Implements Action Story TAV-CUS-ACC-005.

Trigger:
Customer clicks Continue with Google

System Action:
System redirects to Google, receives auth token, creates or links account

Failure States:
OAuth cancelled, Google account email already used with password login",E-003,"action:tav-cus-acc-005,customer,acc",High
A-038,Task,TAV-CUS-ACC-006 Customer proceeds as guest at checkout,"Implements Action Story TAV-CUS-ACC-006.

Trigger:
Guest clicks Continue as guest at checkout

System Action:
System prompts for name and phone number only, skips loyalty points

Failure States:
Restaurant requires account for orders",E-003,"action:tav-cus-acc-006,customer,acc",Medium
A-039,Task,TAV-CUS-ORD-008 Customer views menu categories,"Implements Action Story TAV-CUS-ORD-008.

Trigger:
Customer lands on menu page

System Action:
System loads categories (Starters, Mains, Desserts, Drinks) with dish counts

Failure States:
No dishes published, all items out of stock",E-003,"action:tav-cus-ord-008,customer,ord",Medium
A-040,Task,TAV-CUS-ORD-009 Customer filters menu by dietary preference,"Implements Action Story TAV-CUS-ORD-009.

Trigger:
Customer selects filter: vegetarian, vegan, gluten-free, etc.

System Action:
System filters menu to show only matching items

Failure States:
No dishes match filter",E-003,"action:tav-cus-ord-009,customer,ord",Medium
A-041,Task,TAV-CUS-ORD-010 Customer searches menu,"Implements Action Story TAV-CUS-ORD-010.

Trigger:
Customer types in search bar

System Action:
System performs real-time search across names, ingredients, descriptions

Failure States:
No matches found",E-003,"action:tav-cus-ord-010,customer,ord",Medium
A-042,Task,TAV-CUS-ORD-011 Customer views dish card on menu,"Implements Action Story TAV-CUS-ORD-011.

Trigger:
Customer scrolls through menu

System Action:
System displays dish cards: photo, name, price, dietary badges, rating

Failure States:
Images fail to load, prices missing",E-003,"action:tav-cus-ord-011,customer,ord",Medium
A-043,Task,TAV-CUS-ORD-012 Customer sees AI-recommended dishes,"Implements Action Story TAV-CUS-ORD-012.

Trigger:
Customer views menu with AI insights enabled

System Action:
System highlights popular dishes, trending items, or personalized suggestions

Failure States:
Insufficient data for recommendations",E-003,"action:tav-cus-ord-012,customer,ord",Low
A-044,Task,TAV-CUS-ORD-013 Customer views AI review summary,"Implements Action Story TAV-CUS-ORD-013.

Trigger:
Customer sees review summary on dish card or detail

System Action:
System shows AI-generated summary: common praise, criticisms, highlights

Failure States:
Insufficient reviews, AI service down",E-003,"action:tav-cus-ord-013,customer,ord",Low
A-045,Task,TAV-CUS-ORD-014 Customer toggles nutrition information display,"Implements Action Story TAV-CUS-ORD-014.

Trigger:
Customer enables Show nutrition info in menu settings

System Action:
System displays calories, allergens, macros on dish cards

Failure States:
Data incomplete for some dishes",E-003,"action:tav-cus-ord-014,customer,ord",Medium
A-046,Task,TAV-CUS-ORD-015 Customer views out-of-stock indicator,"Implements Action Story TAV-CUS-ORD-015.

Trigger:
Dish becomes unavailable (vendor marked out of stock)

System Action:
System grays out dish card, shows Currently unavailable badge

Failure States:
Stock status not updated in real-time",E-003,"action:tav-cus-ord-015,customer,ord",Medium
A-047,Task,TAV-CUS-ORD-016 Customer opens dish detail modal,"Implements Action Story TAV-CUS-ORD-016.

Trigger:
Customer taps dish card

System Action:
System loads modal with large photo, full description, reviews, customization options

Failure States:
Image fails to load",E-003,"action:tav-cus-ord-016,customer,ord",Medium
A-048,Task,TAV-CUS-ORD-017 Customer views dish ingredients,"Implements Action Story TAV-CUS-ORD-017.

Trigger:
Customer expands ingredients section in dish detail

System Action:
System displays all ingredients with allergen highlights

Failure States:
Ingredient data missing",E-003,"action:tav-cus-ord-017,customer,ord",Medium
A-049,Task,TAV-CUS-ORD-018 Customer selects dish size,"Implements Action Story TAV-CUS-ORD-018.

Trigger:
Customer chooses from size options (Small/Regular/Large)

System Action:
System updates price based on selected size

Failure States:
Size out of stock",E-003,"action:tav-cus-ord-018,customer,ord",Medium
A-050,Task,TAV-CUS-ORD-019 Customer adds ingredient modifications,"Implements Action Story TAV-CUS-ORD-019.

Trigger:
Customer selects No onions, Extra cheese, etc.

System Action:
System tracks modifications, adds extra charges if applicable

Failure States:
Modification conflicts with dish preparation",E-003,"action:tav-cus-ord-019,customer,ord",Medium
A-051,Task,TAV-CUS-ORD-020 Customer adds special instructions,"Implements Action Story TAV-CUS-ORD-020.

Trigger:
Customer types in Special requests text field

System Action:
System attaches note to order item (e.g., No spicy, Well done)

Failure States:
Character limit exceeded",E-003,"action:tav-cus-ord-020,customer,ord",Medium
A-052,Task,TAV-CUS-ORD-021 Customer adjusts quantity,"Implements Action Story TAV-CUS-ORD-021.

Trigger:
Customer uses +/- buttons to change quantity

System Action:
System multiplies item price, updates total

Failure States:
Quantity exceeds stock availability",E-003,"action:tav-cus-ord-021,customer,ord",Medium
A-053,Task,TAV-CUS-ORD-022 Customer adds configured dish to basket,"Implements Action Story TAV-CUS-ORD-022.

Trigger:
Customer clicks Add to basket after customization

System Action:
System adds item to basket with all modifications, updates basket count

Failure States:
Item out of stock, price error",E-003,"action:tav-cus-ord-022,customer,ord",High
A-054,Task,TAV-CUS-ORD-023 Customer views dish reviews before adding,"Implements Action Story TAV-CUS-ORD-023.

Trigger:
Customer scrolls to reviews section in dish detail

System Action:
System displays recent reviews with ratings, photos, AI summary

Failure States:
No reviews yet",E-003,"action:tav-cus-ord-023,customer,ord",Low
A-055,Task,TAV-CUS-ORD-024 Customer opens basket,"Implements Action Story TAV-CUS-ORD-024.

Trigger:
Customer taps basket icon

System Action:
System displays all basket items with customizations and prices

Failure States:
Basket empty",E-003,"action:tav-cus-ord-024,customer,ord",High
A-056,Task,TAV-CUS-ORD-025 Customer edits item in basket,"Implements Action Story TAV-CUS-ORD-025.

Trigger:
Customer taps Edit on basket item

System Action:
System reopens dish detail with current modifications pre-selected

Failure States:
Dish no longer available",E-003,"action:tav-cus-ord-025,customer,ord",Medium
A-057,Task,TAV-CUS-ORD-026 Customer removes item from basket,"Implements Action Story TAV-CUS-ORD-026.

Trigger:
Customer taps trash icon or swipes to delete

System Action:
System removes item, recalculates total

Failure States:
Basket becomes empty",E-003,"action:tav-cus-ord-026,customer,ord",Medium
A-058,Task,TAV-CUS-ORD-027 Customer sees basket validation errors,"Implements Action Story TAV-CUS-ORD-027.

Trigger:
Item in basket becomes unavailable while browsing

System Action:
System flags problematic items, prevents checkout

Failure States:
Multiple items unavailable",E-003,"action:tav-cus-ord-027,customer,ord",High
A-059,Task,TAV-CUS-ORD-028 Customer views basket summary,"Implements Action Story TAV-CUS-ORD-028.

Trigger:
Customer reviews basket before checkout

System Action:
System calculates subtotal, estimated tax, service fee, total

Failure States:
Tax calculation error",E-003,"action:tav-cus-ord-028,customer,ord",High
A-060,Task,TAV-CUS-ORD-029 Customer applies promo code,"Implements Action Story TAV-CUS-ORD-029.

Trigger:
Customer enters code in basket promo field

System Action:
System validates code, applies discount, recalculates total

Failure States:
Invalid code, expired, minimum order not met",E-003,"action:tav-cus-ord-029,customer,ord",Medium
A-061,Task,TAV-CUS-PAY-001 Customer proceeds to checkout,"Implements Action Story TAV-CUS-PAY-001.

Trigger:
Customer clicks Checkout from basket

System Action:
System transitions to checkout, verifies restaurant accepting orders

Failure States:
Restaurant closed, kitchen stopped accepting orders",E-003,"action:tav-cus-pay-001,customer,pay",High
A-062,Task,TAV-CUS-PAY-002 Customer selects payment method,"Implements Action Story TAV-CUS-PAY-002.

Trigger:
Customer chooses card payment or cash

System Action:
System prepares appropriate payment flow

Failure States:
Payment method not supported by restaurant",E-003,"action:tav-cus-pay-002,customer,pay",High
A-063,Task,TAV-CUS-PAY-003 Customer enters card details,"Implements Action Story TAV-CUS-PAY-003.

Trigger:
Customer selects card payment, enters Stripe form

System Action:
System loads Stripe Elements, validates card info

Failure States:
Invalid card number, expired card, Stripe unavailable",E-003,"action:tav-cus-pay-003,customer,pay",High
A-064,Task,TAV-CUS-PAY-004 Customer completes card payment,"Implements Action Story TAV-CUS-PAY-004.

Trigger:
Customer clicks Pay now

System Action:
System processes payment via Stripe, creates order on success

Failure States:
Payment declined, insufficient funds, network error",E-003,"action:tav-cus-pay-004,customer,pay",High
A-065,Task,TAV-CUS-PAY-005 Customer selects cash payment,"Implements Action Story TAV-CUS-PAY-005.

Trigger:
Customer chooses Pay with cash at table

System Action:
System creates order without payment processing, notifies staff

Failure States:
Cash payment disabled",E-003,"action:tav-cus-pay-005,customer,pay",High
A-066,Task,TAV-CUS-PAY-006 Customer initiates split bill,"Implements Action Story TAV-CUS-PAY-006.

Trigger:
Customer clicks Split bill in shared basket

System Action:
System calculates each person's items, creates individual payment requests

Failure States:
Cannot split certain items, only one person in session",E-003,"action:tav-cus-pay-006,customer,pay",Medium
A-067,Task,TAV-CUS-PAY-007 Customer pays their split portion,"Implements Action Story TAV-CUS-PAY-007.

Trigger:
Customer clicks Pay my share in split bill

System Action:
System processes payment for individual portion only

Failure States:
Payment fails, amount mismatch",E-003,"action:tav-cus-pay-007,customer,pay",High
A-068,Task,TAV-CUS-PAY-008 Customer views VAT breakdown on receipt,"Implements Action Story TAV-CUS-PAY-008.

Trigger:
Payment completed, customer views receipt

System Action:
System displays itemized receipt with VAT rates (10%, 13%, 20%)

Failure States:
VAT calculation error",E-003,"action:tav-cus-pay-008,customer,pay",High
A-069,Task,TAV-CUS-PAY-009 Customer downloads receipt PDF,"Implements Action Story TAV-CUS-PAY-009.

Trigger:
Customer clicks Download receipt

System Action:
System generates PDF with order details, VAT breakdown, timestamps

Failure States:
PDF generation fails",E-003,"action:tav-cus-pay-009,customer,pay",Medium
A-070,Task,TAV-CUS-PAY-010 Customer retries failed payment,"Implements Action Story TAV-CUS-PAY-010.

Trigger:
Payment declined, customer clicks Try again

System Action:
System allows new payment attempt with same or different method

Failure States:
Order expired, items now out of stock",E-003,"action:tav-cus-pay-010,customer,pay",High
A-071,Task,TAV-CUS-ORD-030 Customer views order confirmation,"Implements Action Story TAV-CUS-ORD-030.

Trigger:
Payment successful

System Action:
System displays order number, estimated time, tracking link

Failure States:
Order creation fails after payment",E-003,"action:tav-cus-ord-030,customer,ord",High
A-072,Task,TAV-CUS-ORD-031 Customer tracks order status,"Implements Action Story TAV-CUS-ORD-031.

Trigger:
Customer opens order tracking screen

System Action:
System displays real-time status: Received, Preparing, Ready, Delivered

Failure States:
Status not updated by kitchen",E-003,"action:tav-cus-ord-031,customer,ord",High
A-073,Task,TAV-CUS-ORD-032 Customer receives order status notification,"Implements Action Story TAV-CUS-ORD-032.

Trigger:
Kitchen updates order to Ready or Out for delivery

System Action:
System sends push notification or SMS

Failure States:
Notification delivery fails, customer disabled notifications",E-003,"action:tav-cus-ord-032,customer,ord",Medium
A-074,Task,TAV-CUS-ORD-033 Customer views estimated preparation time,"Implements Action Story TAV-CUS-ORD-033.

Trigger:
Order placed

System Action:
System calculates ETA based on current kitchen load and dish complexity

Failure States:
Time estimate inaccurate due to kitchen delays",E-003,"action:tav-cus-ord-033,customer,ord",Medium
A-075,Task,TAV-CUS-ORD-034 Customer contacts restaurant about order,"Implements Action Story TAV-CUS-ORD-034.

Trigger:
Customer clicks Contact restaurant from tracking screen

System Action:
System displays phone number or opens messaging interface

Failure States:
Restaurant contact info missing",E-003,"action:tav-cus-ord-034,customer,ord",Medium
A-076,Task,TAV-CUS-ORD-035 Customer cancels order before preparation,"Implements Action Story TAV-CUS-ORD-035.

Trigger:
Customer clicks Cancel order immediately after placing

System Action:
System cancels order, initiates automatic refund if paid

Failure States:
Kitchen already started preparing, cancellation window expired",E-003,"action:tav-cus-ord-035,customer,ord",High
A-077,Task,TAV-CUS-ORD-036 Customer marks order as received,"Implements Action Story TAV-CUS-ORD-036.

Trigger:
Food delivered, customer clicks Order received

System Action:
System marks order complete, prompts for review

Failure States:
Order already marked complete",E-003,"action:tav-cus-ord-036,customer,ord",Medium
A-078,Task,TAV-CUS-ORD-037 Customer views order history,"Implements Action Story TAV-CUS-ORD-037.

Trigger:
Customer opens My Orders from account menu

System Action:
System loads all previous orders with dates, totals, statuses

Failure States:
No order history",E-003,"action:tav-cus-ord-037,customer,ord",Medium
A-079,Task,TAV-CUS-REV-001 Customer opens review form,"Implements Action Story TAV-CUS-REV-001.

Trigger:
Customer clicks Leave a review after order completion

System Action:
System opens review form with order details pre-filled

Failure States:
Order too old to review (>30 days)",E-004,"action:tav-cus-rev-001,customer,rev",Low
A-080,Task,TAV-CUS-REV-002 Customer rates overall experience,"Implements Action Story TAV-CUS-REV-002.

Trigger:
Customer selects star rating (1-5)

System Action:
System records rating, enables text review field

Failure States:
None",E-004,"action:tav-cus-rev-002,customer,rev",Low
A-081,Task,TAV-CUS-REV-003 Customer writes review text,"Implements Action Story TAV-CUS-REV-003.

Trigger:
Customer types in review text box

System Action:
System validates text length, checks for inappropriate content

Failure States:
Text contains profanity or spam patterns",E-004,"action:tav-cus-rev-003,customer,rev",Low
A-082,Task,TAV-CUS-REV-004 Customer uploads review photos,"Implements Action Story TAV-CUS-REV-004.

Trigger:
Customer clicks Add photo, selects images

System Action:
System uploads images, validates format and size

Failure States:
File too large, invalid format",E-004,"action:tav-cus-rev-004,customer,rev",Low
A-083,Task,TAV-CUS-REV-005 Customer submits review,"Implements Action Story TAV-CUS-REV-005.

Trigger:
Customer clicks Submit review

System Action:
System publishes review, updates restaurant rating, awards loyalty points

Failure States:
Duplicate review, content moderation flags issue",E-004,"action:tav-cus-rev-005,customer,rev",Low
A-084,Task,TAV-CUS-REV-006 Customer edits published review,"Implements Action Story TAV-CUS-REV-006.

Trigger:
Customer clicks Edit on their review in order history

System Action:
System loads review form with current content

Failure States:
Edit window expired",E-004,"action:tav-cus-rev-006,customer,rev",Low
A-085,Task,TAV-CUS-REV-007 Customer deletes review,"Implements Action Story TAV-CUS-REV-007.

Trigger:
Customer clicks Delete review with confirmation

System Action:
System removes review, recalculates restaurant rating, revokes loyalty points

Failure States:
Review already deleted",E-004,"action:tav-cus-rev-007,customer,rev",Low
A-086,Task,TAV-CUS-ACC-007 Customer views loyalty points balance,"Implements Action Story TAV-CUS-ACC-007.

Trigger:
Customer opens account page

System Action:
System displays total points, points history, redemption options

Failure States:
Points calculation error",E-003,"action:tav-cus-acc-007,customer,acc",Medium
A-087,Task,TAV-CUS-ACC-008 Customer redeems loyalty points,"Implements Action Story TAV-CUS-ACC-008.

Trigger:
Customer clicks Use points at checkout

System Action:
System converts points to discount, deducts from balance

Failure States:
Insufficient points, redemption disabled for this order",E-003,"action:tav-cus-acc-008,customer,acc",Medium
A-088,Task,TAV-CUS-ACC-009 Customer updates profile information,"Implements Action Story TAV-CUS-ACC-009.

Trigger:
Customer edits name, phone, email in account settings

System Action:
System validates new information, updates account

Failure States:
Email already in use, invalid phone format",E-003,"action:tav-cus-acc-009,customer,acc",Medium
A-089,Task,TAV-CUS-ACC-010 Customer manages saved restaurants,"Implements Action Story TAV-CUS-ACC-010.

Trigger:
Customer favorites or unfavorites restaurant

System Action:
System adds or removes restaurant from saved list

Failure States:
None",E-003,"action:tav-cus-acc-010,customer,acc",Low
A-090,Task,TAV-CUS-ACC-011 Customer deletes account,"Implements Action Story TAV-CUS-ACC-011.

Trigger:
Customer requests account deletion (GDPR right to be forgotten)

System Action:
System anonymizes personal data, retains transaction records per legal requirements

Failure States:
Active orders prevent deletion, legal hold on data",E-003,"action:tav-cus-acc-011,customer,acc",High