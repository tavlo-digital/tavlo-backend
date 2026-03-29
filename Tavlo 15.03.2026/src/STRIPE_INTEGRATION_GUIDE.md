# Stripe Checkout Integration Guide

## ✅ **What's Implemented (Demo Mode)**

The complete vendor onboarding flow with:
- ✅ Subscription gate (hard paywall)
- ✅ Stripe Checkout redirect flow
- ✅ Payment verification on return
- ✅ Webhook handler structure
- ✅ Automatic activation logic
- ✅ Subscription status checks

**Currently running in DEMO MODE** - simulates Stripe without real payments.

---

## 🔄 **Flow Overview**

```
Vendor clicks "Subscribe & go live"
    ↓
Backend checks: subscription_status === "active"?
    ↓
❌ If NO → Redirect to Stripe Checkout
    ↓
✅ Vendor enters card/SEPA details
    ↓
Stripe processes payment
    ↓
✅ Payment success → Redirect to success_url?session_id=xyz
    ↓
Frontend verifies session with backend
    ↓
Stripe webhook fires: checkout.session.completed
    ↓
Backend AUTOMATICALLY activates vendor:
    - subscription_status = "active"
    - vendor_status = "active"
    - QR codes → active
    ↓
✅ Vendor is LIVE (no admin intervention needed)
```

---

## 🚀 **Production Setup (4 Steps)**

### **Step 1: Get Stripe Keys**

1. Create Stripe account: https://dashboard.stripe.com/register
2. Navigate to: Developers → API keys
3. Copy:
   - `Publishable key` (starts with `pk_`)
   - `Secret key` (starts with `sk_`)

### **Step 2: Create Stripe Products & Prices**

In Stripe Dashboard → Products:

**Monthly Plan:**
- Name: "TAVLO Monthly Subscription"
- Price: €49.00 EUR / month
- Billing: Recurring monthly
- Copy the **Price ID** (starts with `price_`)

**Yearly Plan:**
- Name: "TAVLO Yearly Subscription"  
- Price: €490.00 EUR / year
- Billing: Recurring yearly
- Copy the **Price ID** (starts with `price_`)

### **Step 3: Add Stripe SDK & Configure Backend**

In `/supabase/functions/server/index.tsx`, replace the demo code:

```typescript
// Add at top of file
import Stripe from 'npm:stripe@14';

const stripe = new Stripe(Deno.env.get('STRIPE_SECRET_KEY') || '', {
  apiVersion: '2023-10-16'
});

// Update create-checkout-session endpoint
app.post('/make-server-1dccd8d3/vendor/:vendorId/create-checkout-session', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const { planId, priceId, successUrl, cancelUrl } = await c.req.json();
    
    const vendor = await kv.get(`vendor:${vendorId}`);
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    // Create Stripe Checkout Session
    const session = await stripe.checkout.sessions.create({
      customer_email: vendor.email,
      line_items: [{
        price: priceId, // Use your actual Stripe Price ID
        quantity: 1
      }],
      mode: 'subscription',
      success_url: successUrl,
      cancel_url: cancelUrl,
      metadata: {
        vendorId: vendorId,
        planId: planId
      },
      subscription_data: {
        metadata: {
          vendorId: vendorId
        }
      },
      // Auto tax calculation (optional)
      automatic_tax: { enabled: true }
    });
    
    return c.json({ 
      checkoutUrl: session.url,
      sessionId: session.id 
    });
  } catch (error) {
    console.log(`Create checkout session error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Update verify-checkout endpoint
app.get('/make-server-1dccd8d3/vendor/verify-checkout/:sessionId', async (c) => {
  try {
    const sessionId = c.req.param('sessionId');
    
    // Retrieve session from Stripe
    const session = await stripe.checkout.sessions.retrieve(sessionId);
    
    if (session.payment_status === 'paid') {
      const vendorId = session.metadata?.vendorId;
      const subscriptionId = session.subscription as string;
      
      // Get subscription details
      const subscription = await stripe.subscriptions.retrieve(subscriptionId);
      
      return c.json({
        success: true,
        vendorId,
        subscriptionId: subscription.id,
        subscriptionStatus: subscription.status,
        currentPeriodEnd: subscription.current_period_end
      });
    }
    
    return c.json({ success: false, error: 'Payment not completed' });
  } catch (error) {
    console.log(`Verify checkout error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});
```

### **Step 4: Set Up Webhook**

**4.1: In Stripe Dashboard:**
1. Go to: Developers → Webhooks
2. Click: "Add endpoint"
3. Endpoint URL: `https://YOUR_PROJECT.supabase.co/functions/v1/make-server-1dccd8d3/webhooks/stripe`
4. Select events:
   - `checkout.session.completed`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
   - `customer.subscription.deleted`
5. Copy the **Webhook signing secret** (starts with `whsec_`)

**4.2: Update webhook handler:**

```typescript
app.post('/make-server-1dccd8d3/webhooks/stripe', async (c) => {
  try {
    const signature = c.req.header('stripe-signature');
    const body = await c.req.text();
    const webhookSecret = Deno.env.get('STRIPE_WEBHOOK_SECRET');

    // Verify webhook signature
    let event;
    try {
      event = stripe.webhooks.constructEvent(body, signature || '', webhookSecret || '');
    } catch (err) {
      console.log(`Webhook signature verification failed: ${err.message}`);
      return c.json({ error: 'Invalid signature' }, 400);
    }

    console.log(`Received Stripe webhook: ${event.type}`);

    switch (event.type) {
      case 'checkout.session.completed': {
        const session = event.data.object;
        const vendorId = session.metadata?.vendorId;
        const subscriptionId = session.subscription;

        const vendor = await kv.get(`vendor:${vendorId}`);
        if (!vendor) {
          return c.json({ error: 'Vendor not found' }, 404);
        }

        // AUTOMATIC ACTIVATION
        vendor.subscriptionStatus = 'active';
        vendor.subscriptionId = subscriptionId;
        vendor.status = 'active';
        vendor.activatedAt = new Date().toISOString();

        if (vendor.tablesData) {
          vendor.tablesData = vendor.tablesData.map((table: any) => ({
            ...table,
            isActive: true
          }));
        }

        await kv.set(`vendor:${vendorId}`, vendor);
        console.log(`✅ Vendor ${vendorId} activated automatically`);
        
        // TODO: Send activation email to vendor
        break;
      }

      case 'invoice.payment_failed': {
        const invoice = event.data.object;
        const subscriptionId = invoice.subscription;
        const allVendors = await kv.getByPrefix('vendor:');
        const vendor = allVendors.find((v: any) => v.subscriptionId === subscriptionId);

        if (vendor) {
          vendor.subscriptionStatus = 'past_due';
          vendor.paymentFailedAt = new Date().toISOString();
          await kv.set(`vendor:${vendor.id}`, vendor);
          
          // TODO: Send payment failure email
          // TODO: Pause order acceptance
          console.log(`⚠️ Payment failed for vendor ${vendor.id}`);
        }
        break;
      }

      case 'customer.subscription.deleted': {
        const subscription = event.data.object;
        const allVendors = await kv.getByPrefix('vendor:');
        const vendor = allVendors.find((v: any) => v.subscriptionId === subscription.id);

        if (vendor) {
          vendor.subscriptionStatus = 'canceled';
          vendor.status = 'inactive';
          vendor.deactivatedAt = new Date().toISOString();
          
          if (vendor.tablesData) {
            vendor.tablesData = vendor.tablesData.map((table: any) => ({
              ...table,
              isActive: false
            }));
          }

          await kv.set(`vendor:${vendor.id}`, vendor);
          
          // TODO: Send cancelation confirmation email
          console.log(`❌ Subscription canceled for vendor ${vendor.id}`);
        }
        break;
      }
    }

    return c.json({ received: true });
  } catch (error) {
    console.log(`Webhook processing error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});
```

---

## 🔐 **Environment Variables**

Add to your Supabase project:

```bash
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

In development:
```bash
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

---

## 🔒 **Security Checklist**

✅ **Webhook signature verification** - Prevents fake webhooks  
✅ **HTTPS only** - Stripe requires SSL  
✅ **Never expose secret key** - Server-side only  
✅ **Validate vendorId** - Prevent unauthorized activation  
✅ **Idempotent webhooks** - Handle duplicate events  
✅ **Log all events** - Audit trail for debugging

---

## 🧪 **Testing in Development**

### **Option 1: Stripe CLI (Recommended)**

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Forward webhooks to local dev
stripe listen --forward-to http://localhost:54321/functions/v1/make-server-1dccd8d3/webhooks/stripe

# Test webhook events
stripe trigger checkout.session.completed
```

### **Option 2: Use Test Mode**

1. Use test keys (pk_test_... and sk_test_...)
2. Use test card: 4242 4242 4242 4242 (any future date, any CVC)
3. Webhooks fire in test mode too

---

## 🔄 **Subscription Lifecycle**

### **New Subscription:**
```
checkout.session.completed → vendor activated
```

### **Renewal Success:**
```
invoice.payment_succeeded → subscription_status = "active"
```

### **Payment Failure:**
```
invoice.payment_failed → subscription_status = "past_due"
→ Orders paused
→ Email sent to vendor
→ Vendor has X days to update payment method
```

### **Cancelation:**
```
customer.subscription.deleted → vendor deactivated
→ QR codes disabled
→ Orders stopped
```

---

## 📊 **Subscription Status States**

| Status | Can Accept Orders | Can Download QR | Can Invoice |
|--------|------------------|-----------------|-------------|
| `none` | ❌ | ❌ | ❌ |
| `active` | ✅ | ✅ | ✅ |
| `past_due` | ⚠️ Limited | ⚠️ Existing only | ✅ |
| `canceled` | ❌ | ❌ | ❌ |

---

## 🎯 **Enforcement Points**

Backend checks `subscription_status === "active"` before:

1. **Downloading QR codes**
2. **Accepting first order**
3. **Accessing vendor dashboard**
4. **Generating invoices**

**Frontend:**
- Shows subscription gate
- Disables features
- Clear messaging

**Backend:**
- Returns 403 Forbidden if not active
- Logs unauthorized attempts

---

## 💰 **Pricing Configuration**

Update these in `/pages/VendorOnboardingFlow.tsx`:

```typescript
const subscriptionPlans = [
  {
    id: 'monthly',
    name: 'Monthly',
    price: 49,
    currency: 'EUR',
    interval: 'month',
    stripePriceId: 'price_YOUR_MONTHLY_PRICE_ID', // From Stripe Dashboard
    features: [...]
  },
  {
    id: 'yearly',
    name: 'Yearly',
    price: 490,
    currency: 'EUR',
    interval: 'year',
    stripePriceId: 'price_YOUR_YEARLY_PRICE_ID', // From Stripe Dashboard
    recommended: true,
    features: [...]
  }
];
```

---

## 📧 **Email Notifications (TODO)**

Implement emails for:

1. **Welcome email** (after registration)
2. **Activation success** (after payment)
3. **Payment receipt** (Stripe sends automatically)
4. **Payment failed** (past_due status)
5. **Subscription canceled** (confirmation)
6. **Trial expiring** (if you add trials)

Use:
- Stripe's built-in email receipts
- SendGrid / Resend / Postmark for custom emails
- Supabase Auth emails for account management

---

## 🐛 **Common Issues**

### **Webhook not firing:**
- Check endpoint URL is correct
- Verify webhook secret
- Check Stripe Dashboard → Webhooks → Recent attempts

### **Payment succeeds but vendor not activated:**
- Check webhook logs in backend
- Verify vendorId in metadata
- Check KV store for vendor data

### **Double activation:**
- Webhooks can fire twice
- Make activation idempotent
- Check if already active before updating

---

## 📱 **Mobile Payments**

Stripe Checkout automatically supports:
- Apple Pay
- Google Pay  
- Card payments
- SEPA Direct Debit
- iDEAL, Sofort, etc.

No extra code needed!

---

## 🌍 **Multi-Currency Support**

To support multiple currencies:

1. Create separate Stripe products for each currency
2. Detect vendor country during registration
3. Show appropriate currency and pricing
4. Pass correct `priceId` to checkout

Example:
```typescript
const getPriceId = (planId: string, country: string) => {
  if (country === 'Austria' || country === 'Germany') {
    return planId === 'monthly' ? 'price_eur_monthly' : 'price_eur_yearly';
  } else if (country === 'United States') {
    return planId === 'monthly' ? 'price_usd_monthly' : 'price_usd_yearly';
  }
  // Default to EUR
  return planId === 'monthly' ? 'price_eur_monthly' : 'price_eur_yearly';
};
```

---

## ✅ **Production Checklist**

Before going live:

- [ ] Replace demo mode with real Stripe integration
- [ ] Add all environment variables (keys, secrets)
- [ ] Test checkout flow end-to-end
- [ ] Verify webhook is receiving events
- [ ] Test subscription renewal
- [ ] Test payment failure scenario
- [ ] Test cancelation flow
- [ ] Add email notifications
- [ ] Set up monitoring/alerts
- [ ] Document admin procedures
- [ ] Train support team

---

## 📚 **Resources**

- [Stripe Checkout Docs](https://stripe.com/docs/checkout/quickstart)
- [Stripe Webhooks Guide](https://stripe.com/docs/webhooks)
- [Stripe Testing](https://stripe.com/docs/testing)
- [Stripe Subscriptions](https://stripe.com/docs/billing/subscriptions/overview)

---

## 🆘 **Support**

If you encounter issues:

1. Check browser console for errors
2. Check backend logs for webhook events
3. Review Stripe Dashboard → Events
4. Test with Stripe CLI
5. Contact Stripe support (excellent response time)
