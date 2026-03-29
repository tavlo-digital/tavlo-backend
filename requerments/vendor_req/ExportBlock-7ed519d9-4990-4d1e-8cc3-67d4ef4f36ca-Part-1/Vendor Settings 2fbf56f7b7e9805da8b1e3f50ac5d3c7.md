# Vendor Settings

# 1. Business Info

 * Important and needed for every vendor

## Legal Business Information

- **Business Registration Number:** FN 123456a
- **VAT Number:** ATU12345678
- **Company Type** (dropdown list)**: GmbH, AG, OG, KG, Einzelunternehmen**
- L**egal Address:**
    - Street name: Meißauergasse
    - Building number: 4A/3
    - Postal code: 1220
    - City: Vienna
    - Country: Austria

---

## **Restaurant Profile (Visible to Customers)**

- **Restaurant visibility** in tavlo discovery page: options: hidden or visible
- **Restaurant name**: La Bella Vista (Note: Restaurant name can be changed once, then any modification needs Tavlo admin's approval.)
- **Restaurant description**: Authentic Italian cuisine in the heart of Vienna
- **Email:**
- **Phone:**
- **Website**

## Beanding & Appearance **(Visible to Customers)**

- **Upload one Logo**
- **Upload one cover photo**

## **Business Hours** **(Visible to Customers)**

![image.png](Vendor%20Settings/image.png)

---

# 2. Payment

- Vendor Multi-selects if:
    1. **Cash (dine-in) accepted**. Needs confirmation from waiter
    2. **Cash (take away) accepted.** So the customer does not pay when placing an order
    3. **Card accepted**
        1. Visa Card
        2. Master Card
        3. American Express
        4. Apple Pay
        5. Google Pay
    4. **Bank transfer (SEPA, eps) accepted**
- If the payment method is cash, the waiter needs to confirm it. Tavlo cannot confirm it
- If the payment is a card or bank transfer, tavlo will use Stripe to do the payment.
- Tavlo is never the Merchant of Record (MoR)
- Tavlo is acting as a Marketplace, connecting customers and vendors, leaving payment and tax obligations to the vendors themselves. (Tavlo does not hold the money at all; it goes directly from the customer’s account to the vendor’s)
- **Stripe-owned pricing** model will be used:
    1. Customers use Tavlo to pay for food at restaurants
    2. Tavlo channels payments directly from customers to restaurant accounts
    3. Tavlo does not hold or touch the money

### How to connect to **Stripe-owned pricing?**

For the simplest self-service restaurant onboarding:

1. Use Stripe Express accounts - they provide the easiest onboarding experience while still allowing customization:
    
    `stripe accounts create --type "express"`
    
2. In tavlo, create a simple registration form collecting only essential information:
    - Restaurant name
    - Email address
    - Phone number
    - Business type (individual/company)
3. After form submission, create the Express account and generate an account link:
    
    `stripe account-links create \
      --account "acct_123" \
      --refresh-url "https://yourapp.com/refresh" \
      --return-url "https://yourapp.com/return" \
      --type "account_onboarding"`
    
4. Redirect the restaurant to the Stripe-hosted onboarding flow where they'll complete:
    - Identity verification
    - Bank account connection
    - Terms acceptance
5. The onboarding flow is optimized for mobile and typically takes under 10 minutes to complete.
6. When they return to tavlo, check their account capabilities status to verify they can process payments.

Express accounts handle all the complexity of verification, compliance, and banking setup without requiring tavlo’s assistance. Restaurant owners can complete everything themselves through Stripe's optimized flow.
[https://docs.stripe.com/connect/saas#stripe-owned-pricing-model](https://docs.stripe.com/connect/saas#stripe-owned-pricing-model)

---

# 3. Tax & Receipts

- Vendor cannot change the tax percentage
- Vendor only sees the taxes applied to food and drinks
- Vendor can add **service fees** as a percentage
- vendor can choose the invoice prefix and invoice number. For example
    - **Invoice Prefix**: INV
    - **Next Invoice Number**: 2026
    - So the invoice number will be on the first day of the month for the first order: INV-202600001
    - Then the second invoice: INV-202600002
    - The third: INV-202600003, etc.

---

# 4. Table & QR

- **Number of Tables**: 24
- **Table Prefix**: T
- **Max. Guests per Table**: 10
- **Enable Table Reservation (On/OFF)**. If On, customers can reserve a table using tavlo website.
- **Total tables for reservations**: 20. From the 24 tables, only 20 can be reserved.

---

# 5. Ordering

- **Auto Accept Orders (ON/OFF)**.
    - If deactivated, the waiter needs to confirm every order before it goes to the kitchen (regardless of whether to pay now, split, or pay later)
    - In case the kitchen has no screen,  “Auto Accept Orders” is deactivated.
    - If active, all orders will be directly sent to the kitchen. (Kitchen screen is needed in this case)
- **Estimated Preparation Time (minutes)**: 20 min
- **Max Orders Per Time Slot**: 50 orders
    - This is to limit how many orders the kitchen accepts within the same preparation window to prevent kitchen overload.
    - If within 20 min (Estimated Prep. time), fewer than 50 orders are submitted, nothing happens
    - . If within 20 min more than 50 orders are submitted, a UX shows the customer “Kitchen is currently at capacity. The prep. time might take a bit longer”.
- **Allow guest ordering (ON/OFF)**
    - If active, unregistered guests can order —> Risks: Ghost ordering, difficult to flag the user
    - If deactivated, only registered users can order —> Risks: Older people will have difficulties. Also, some people do not want to register on another website. This issue will be mostly at the beginning of tavlo.
- **Minimum Order Amount**: 10 Euros (can be zero/no min. order amount required)
- **Maximum Order Amount**: 500 Euro —> This is to limit the ghost ordering (can be high 99999)

---

# 6. Inventory (phase 2 or 3)

## General Tab

- **Enable inventory tracking (ON/OFF)**
    - If activated, Tavlo will track all the inventory processes from ingredients, stock levels, reordering, inventory notifications, warnings, insights, nutrition, etc.
    - if deactivated, all the inventory aspects will not work.
- **Enable automatic stock deduction (ON/OFF)**
    - Active: when someone orders a chicken burger, which has 200 grams of chicken breast, then the stock level of chicken breast will automatically decrease by 200 grams. This requires recipes to be configured for each menu item in the Menu Management.
    - Deactivated: Tracking will not be possible, stock levels will not be accurate
- **Allow Negative stock  (ON/OFF)**
    - This is valid only if “enable automatic stock deduction” is ON
    - Deactivated: stock levels cannot be negative. recommended and default value. **Example**:
        1. Chicken breast stock level is 1 kg
        2. Chicken Burger needs 200 g of chicken breast. Chicken breast is a critical ingredient (check Menu Management)
        3. 3 orders with chicken breast. Automatic stock deduction is activated.
        4. The chicken breast level now is 400 grams
        5. Another 2 people ordered chicken breast. Current stock level is 0 grams
        6. Warning to the management that chicken breast is out of stock
        7. The customer sees that the chicken burger is sold out/unavailable. The customer cannot order it anymore
    - Active: stock levels can be negative. Not recommended. **Example**:
        - Same as the above example
        1. The customer still sees that the chicken burger is available. The customer can order it 
        2. Stock level now is -200 grams
        3. The stock level can be adjusted manually (the vendor quickly went and bought one kilogram of chicken breast)

## Automation Tab (phase 3/ extra paid feature)

- **Enable AI stock prediction (ON/OFF)**
    - Use machine learning to predict future stock needs based on historical usage
- **Enable low-stock alerts (ON/OFF)**
    - Automatically notify the vendor when ingredients drop below reorder levels
    - Reorder levels: when the stock of an item reaches the reorder level, the vendor will be notified to take action. E.g., if chicken breast stock drops below 1 kg, the vendor will be notified
- **Enable auto-generated purchase orders (ON/OFF)**
    - Automatically create purchase orders when stock falls below reorder levels
    - The PO will be prepared and needs confirmation from the management to be sent to the supplier of the item

## **Availability Rules Tab**

- Control when menu items become unavailable based on stock levels
- **Auto-mark unavailable when a critical ingredient is out (ON/OFF)**
    - Automatically mark menu items as unavailable when a critical ingredient runs out
    - This can replace “**Allow Negative stock**” toggle
- **Auto-mark unavailable when all ingredients are out (ON/OFF)**
    - Only mark items unavailable when ALL ingredients are depleted

## Suppliers **Tab**

- In this tab, the vendor can add and edit the suppliers of the goods
- Vendor clicks on the **Add Supplier** button
- **Add Supplier** opens up a new window
    - **Basic Information**
        - **Supplier Name:** Fresh Foods Co
        - **Ordering Email:** orders@freshfoods.com
        - **Phone:** +43 1 234 5678
        - **Ordering URL:** freshfoods.com/order
    - **Ordering Configuration**
        - **Ordering Method:** Email or manual/phone call
        - **Default Lead Time (days):**
        - **Order Cutoff Time:**
        - **Minimum Order Quantity:**
    - Supplied Ingredients
        - [ ]  Tomatoes
        - [ ]  Lettuce
        - [ ]  Mazarella
        - [ ]  Chicken breast
        - [ ]  Chicken thighs
        - [ ]  Beef
    - **Supplier Status**
        - Active or not active: if this supplier is available for ordering
- Vendor can edit/remove suppliers’ information

## **Notification Tab**

- **Dashboard alerts (ON/OFF)**
    - Show inventory alerts inside the vendor dashboard
- **Email alerts (ON/OFF)**
    - Send inventory alerts to the global notification email
- **Alert Frequency (choose one option)**
    - [ ]  Immediate
    - [ ]  Daily summary
        
        **Sent at**: 09:00
        
    - [ ]  Weekly summary
        
        **Sent on**: Monday at 09:00
        
    
    ---
    

# 7. Team & Access

- Here, the vendor manages who can access the restaurant and what they can do
- The number of users is limited to the subscription plan
- The owner has all the permissions.
- The owner cannot be deleted or edited
- The owner can invite, edit, and delete users
- The owner assigns the permissions of each user
- in Onboarding day, only the owner is in the team
- Vendor invites users via email
    - **Email address:** John@billaitalia.com
    - The user is added to the list
        
        ![image.png](Vendor%20Settings/image%201.png)
        
- Owner can:
    1. **Edit John’s permissions**
        - Some roles are predefined (Kitchen, Waiter, Manager)
        - If one of the predefined roles is clicked, some permissions are automatically enabled
        
        ![image.png](Vendor%20Settings/image%202.png)
        
    2. **Disable him**
        1. John cannot log in to his account anymore and will be logged out
    3. **Resend the invite**
        1. Only if the invitation has not been accepted yet
    4. **Delete him**
        1. John's account will be deleted. The number of users will be reduced
- John receives the invitation
- John clicks on the invitation link
- John is directed to tavlo signup page
    
    ![image.png](Vendor%20Settings/image%203.png)
    
- Email address cannot be changed
- John sets a **password** and **confirms** it
- John can now see what the owner has permitted him to do/view
- Predefined roles are:
    - Owner
    
    [Owner Role - Vendor](https://www.notion.so/Owner-Role-Vendor-2fcf56f7b7e980ea87e1ef423ddbc307?pvs=21)
    
    - Manager
    
    [Manager Role - Vendor](https://www.notion.so/Manager-Role-Vendor-2fcf56f7b7e98073969dca850bb69fa6?pvs=21)
    
    - Waiter
    
    [Waiter Role - Vendor](https://www.notion.so/Waiter-Role-Vendor-2fcf56f7b7e980b8a138e6be4170de3f?pvs=21)
    
    - Kitchen
    
    [Kitchen Role - Vendor](https://www.notion.so/Kitchen-Role-Vendor-2fcf56f7b7e980e7a84eeeaa8b11cc3f?pvs=21)
    

---

# 8. Notifications

- **Notification Email Address**
    - This email is used for all Tavlo notifications, including inventory alerts.

**Email Notifications**

- [ ]  **New order received**
    
    Get notified when a customer places an order
    
- [ ]  **New review posted**
    
    Get notified when customers leave reviews
    

**Push Notifications**

- [ ]  **New order received**
    
    Browser push notifications for new orders
    
- [ ]  **Order status updates**
    
    Notifications when orders are ready to serve
    

---

# 9. Reviews

- **Enable Restaurant Reviews**
    - [ ]  **Enable Restaurant Reviews**
        
        Allow customers to rate and review the restaurant
        
        - [ ]  **Enable menu articles reviews**
            
            Allow customers to rate and review each article of their order
            
        - [ ]  **Allow anonymous reviews**
            
            Allow unlogged guests to rate and review the restaurant and their orders
            

---

# 10. Language

- **System Language**. (English or German), Later can be done in different languages
    - This language is used for the vendor dashboard and internal system messages.
    - This is not related to the customer of the restaurant
- Customer Menu Languages
- Multi-select languages. For phase one, English, and German is needed.
- later we can do that for the other languages
- [ ]  English
- [ ]  German
- [ ]  Arabic
- [ ]  Spanish
- [ ]  Chinese
- [ ]  ……..
- **Local Formatting:**
    - **Date: (dropdown list)**
        - DD.MM.YYYY
        - MM/DD/YYYY
        - YYYY-MM-DD
    - **Time format**
        - 24-hour
        - 12 hours (am/pm)

---

# 9. Appearance

- This is to make every vendor unique and personalized
- The customer sees different Menu theme and backgrounds for each restaurant, even though using the same platform (tavlo)

![image.png](Vendor%20Settings/image%204.png)

---

# 9. Data Visibility & Exports

- here the vendor configures how to handle some data and operational information
- **Show in top customers analytics (ON/OFF):**
    - Customer data is shown in analytics only where customer consent applies. This setting affects visibility only, not data collection.
- **Export All Data**
    - This will be an Excel sheet to download all the vendors’ information
    - Export the restaurant's operational data, including orders, menus, and analytics. Data subject to legal retention may be excluded (customers’ emails, phone numbers, specific customers’ order history, etc.)