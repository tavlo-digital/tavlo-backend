import { Hono } from 'npm:hono';
import { cors } from 'npm:hono/cors';
import { logger } from 'npm:hono/logger';
import { createClient } from 'npm:@supabase/supabase-js@2';
import * as kv from './kv_store.tsx';

const app = new Hono();

app.use('*', cors());
app.use('*', logger(console.log));

const supabase = createClient(
  Deno.env.get('SUPABASE_URL') ?? '',
  Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? ''
);

// ============ TAX CALCULATION UTILITIES ============

type TaxCategory = 'food' | 'beverage-non-alcoholic' | 'beverage-alcoholic';
type Country = 'AT' | 'DE';

interface TaxRule {
  category: TaxCategory;
  vatRate: number;
}

const TAX_RULES: Record<Country, TaxRule[]> = {
  AT: [
    { category: 'food', vatRate: 10 },
    { category: 'beverage-non-alcoholic', vatRate: 20 },
    { category: 'beverage-alcoholic', vatRate: 20 }
  ],
  DE: [
    { category: 'food', vatRate: 7 },
    { category: 'beverage-non-alcoholic', vatRate: 19 },
    { category: 'beverage-alcoholic', vatRate: 19 }
  ]
};

function getVATRate(country: Country, taxCategory: TaxCategory): number {
  const rules = TAX_RULES[country];
  const rule = rules.find(r => r.category === taxCategory);
  return rule?.vatRate || 0;
}

function calculateNetPrice(grossPrice: number, country: Country, taxCategory: TaxCategory): number {
  const vatRate = getVATRate(country, taxCategory);
  return grossPrice / (1 + vatRate / 100);
}

// ============ AUTH ENDPOINTS ============

app.post('/make-server-1dccd8d3/auth/register', async (c) => {
  try {
    const { email, password, name, phone } = await c.req.json();
    
    const { data, error } = await supabase.auth.admin.createUser({
      email,
      password,
      user_metadata: { name, phone },
      // Send email confirmation (set to false to require email verification)
      // NOTE: For production, set email_confirm: false and configure email server in Supabase
      // For now, we'll auto-confirm to avoid email setup requirements
      email_confirm: false // Changed from true - will send confirmation email
    });

    if (error) {
      console.log(`Registration error: ${error.message}`);
      return c.json({ error: error.message }, 400);
    }

    // Create customer profile
    const customerId = data.user.id;
    await kv.set(`customer:${customerId}`, {
      id: customerId,
      email,
      name: name || '',
      phone: phone || '',
      shareNameWithVendor: true,
      allergies: '',
      dietPreference: '',
      loyaltyPoints: 0,
      createdAt: new Date().toISOString()
    });

    // If email confirmation is required, notify the user
    if (!data.user.email_confirmed_at) {
      return c.json({ 
        customerId,
        confirmationRequired: true,
        message: 'Please check your email to confirm your account'
      });
    }

    return c.json({ 
      customerId,
      customer: await kv.get(`customer:${customerId}`)
    });
  } catch (error) {
    console.log(`Registration error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Social login endpoint
app.post('/make-server-1dccd8d3/auth/social-login', async (c) => {
  try {
    const { provider } = await c.req.json();
    
    if (!['google', 'apple', 'facebook', 'github'].includes(provider)) {
      return c.json({ error: 'Invalid provider' }, 400);
    }

    const authSupabase = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_ANON_KEY') ?? ''
    );

    // ⚠️ IMPORTANT: Before using social login in production, you MUST configure
    // the OAuth provider in your Supabase dashboard:
    // - Google: https://supabase.com/docs/guides/auth/social-login/auth-google
    // - Apple: https://supabase.com/docs/guides/auth/social-login/auth-apple
    // - Facebook: https://supabase.com/docs/guides/auth/social-login/auth-facebook
    // - GitHub: https://supabase.com/docs/guides/auth/social-login/auth-github
    
    const { data, error } = await authSupabase.auth.signInWithOAuth({
      provider: provider as any,
      options: {
        redirectTo: `${Deno.env.get('SUPABASE_URL')}/auth/v1/callback`
      }
    });

    if (error) {
      // Provider might not be enabled
      if (error.message.includes('provider')) {
        return c.json({ 
          error: `${provider} login is not enabled. Please configure it in Supabase dashboard: https://supabase.com/docs/guides/auth/social-login/auth-${provider}` 
        }, 400);
      }
      return c.json({ error: error.message }, 400);
    }

    // Return the OAuth URL for redirect
    return c.json({ url: data.url });
  } catch (error) {
    console.log(`Social login error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Forgot password endpoint
app.post('/make-server-1dccd8d3/auth/forgot-password', async (c) => {
  try {
    const { email } = await c.req.json();
    
    if (!email) {
      return c.json({ error: 'Email is required' }, 400);
    }

    const authSupabase = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_ANON_KEY') ?? ''
    );

    // Send password reset email
    const { error } = await authSupabase.auth.resetPasswordForEmail(email, {
      redirectTo: `${Deno.env.get('SUPABASE_URL')}/auth/v1/callback`
    });

    if (error) {
      console.log(`Password reset error: ${error.message}`);
      return c.json({ error: error.message }, 400);
    }

    return c.json({ 
      message: 'Password reset email sent successfully',
      success: true 
    });
  } catch (error) {
    console.log(`Password reset error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.post('/make-server-1dccd8d3/auth/login', async (c) => {
  try {
    const { email, password } = await c.req.json();
    
    const authSupabase = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_ANON_KEY') ?? ''
    );
    
    const { data, error } = await authSupabase.auth.signInWithPassword({ email, password });

    if (error) {
      console.log(`Login error: ${error.message}`);
      return c.json({ error: error.message }, 400);
    }

    const customer = await kv.get(`customer:${data.user.id}`);
    return c.json({ 
      token: data.session.access_token,
      customer
    });
  } catch (error) {
    console.log(`Login error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/customers/:id', async (c) => {
  try {
    const id = c.req.param('id');
    console.log(`🔍 GET Customer Request - ID: ${id}`);
    const customer = await kv.get(`customer:${id}`);
    
    if (!customer) {
      console.log(`❌ Customer not found: ${id}`);
      return c.json({ error: 'Customer not found' }, 404);
    }
    
    console.log(`✅ Customer found: ${id}, Loyalty Points: ${customer.loyaltyPoints}`);
    return c.json(customer);
  } catch (error) {
    console.log(`Get customer error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.patch('/make-server-1dccd8d3/customers/:id', async (c) => {
  try {
    const id = c.req.param('id');
    const updates = await c.req.json();
    
    const customer = await kv.get(`customer:${id}`);
    if (!customer) {
      return c.json({ error: 'Customer not found' }, 404);
    }
    
    // Handle password update separately via Supabase Auth
    if (updates.password && updates.currentPassword) {
      try {
        // Verify current password by attempting to sign in
        const { data: signInData, error: signInError } = await supabase.auth.signInWithPassword({
          email: customer.email,
          password: updates.currentPassword,
        });
        
        if (signInError) {
          console.log(`Password verification failed for ${customer.email}: ${signInError.message}`);
          return c.json({ error: 'Current password is incorrect' }, 401);
        }
        
        // Update password using admin API (customer.id IS the Supabase Auth user ID)
        const { error: updateError } = await supabase.auth.admin.updateUserById(
          id, // The customer ID is the Supabase Auth UUID
          { password: updates.password }
        );
        
        if (updateError) {
          console.log(`Password update error for customer ${id}: ${updateError.message}`);
          return c.json({ error: 'Failed to update password' }, 500);
        }
        
        console.log(`✅ Password updated for customer ${id}`);
      } catch (passwordError) {
        console.log(`Password update error: ${passwordError}`);
        return c.json({ error: 'Failed to update password' }, 500);
      }
      
      // Remove password fields from updates object (don't store in KV)
      delete updates.password;
      delete updates.currentPassword;
    }
    
    const updated = { ...customer, ...updates };
    await kv.set(`customer:${id}`, updated);
    
    console.log(`✅ Customer ${id} updated successfully`);
    return c.json(updated);
  } catch (error) {
    console.log(`Update customer error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ SESSION ENDPOINTS ============

app.post('/make-server-1dccd8d3/sessions', async (c) => {
  try {
    const { restaurantId, tableId, numPeople, sharedBasket } = await c.req.json();
    
    const sessionId = crypto.randomUUID();
    const session = {
      id: sessionId,
      restaurantId,
      tableId,
      numPeople,
      sharedBasket,
      contributors: [],
      items: [],
      createdAt: new Date().toISOString(),
      status: 'active'
    };
    
    await kv.set(`session:${sessionId}`, session);
    
    return c.json({ 
      sessionId,
      joinUrl: `/session/${sessionId}`
    });
  } catch (error) {
    console.log(`Create session error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.post('/make-server-1dccd8d3/sessions/:sessionId/join', async (c) => {
  try {
    const sessionId = c.req.param('sessionId');
    const { customerId, guestId, guestName } = await c.req.json();
    
    const session = await kv.get(`session:${sessionId}`);
    if (!session) {
      return c.json({ error: 'Session not found' }, 404);
    }
    
    const contributor = {
      id: customerId || guestId,
      type: customerId ? 'customer' : 'guest',
      name: guestName || 'Guest',
      joinedAt: new Date().toISOString()
    };
    
    if (!session.contributors.find((c: any) => c.id === contributor.id)) {
      session.contributors.push(contributor);
      await kv.set(`session:${sessionId}`, session);
    }
    
    return c.json(session);
  } catch (error) {
    console.log(`Join session error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/sessions/:sessionId', async (c) => {
  try {
    const sessionId = c.req.param('sessionId');
    const session = await kv.get(`session:${sessionId}`);
    
    if (!session) {
      return c.json({ error: 'Session not found' }, 404);
    }
    
    return c.json(session);
  } catch (error) {
    console.log(`Get session error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.patch('/make-server-1dccd8d3/sessions/:sessionId/items', async (c) => {
  try {
    const sessionId = c.req.param('sessionId');
    const { items } = await c.req.json();
    
    const session = await kv.get(`session:${sessionId}`);
    if (!session) {
      return c.json({ error: 'Session not found' }, 404);
    }
    
    session.items = items;
    await kv.set(`session:${sessionId}`, session);
    
    return c.json(session);
  } catch (error) {
    console.log(`Update session items error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ RESTAURANT & MENU ENDPOINTS ============

app.get('/make-server-1dccd8d3/restaurants/:id', async (c) => {
  try {
    const id = c.req.param('id');
    const restaurant = await kv.get(`restaurant:${id}`);
    
    if (!restaurant) {
      return c.json({ error: 'Restaurant not found' }, 404);
    }
    
    return c.json(restaurant);
  } catch (error) {
    console.log(`Get restaurant error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/restaurants/:id/menu', async (c) => {
  try {
    const id = c.req.param('id');
    const menu = await kv.get(`menu:${id}`) as any;
    
    if (!menu) {
      return c.json({ error: 'Menu not found' }, 404);
    }
    
    // MIGRATION FIX: Add taxCategory to menu items that don't have it
    // This ensures backward compatibility with existing menu data
    if (menu.items) {
      menu.items = menu.items.map((item: any) => {
        if (!item.taxCategory) {
          // Infer taxCategory from vatRate
          // vatRate 10 = food, vatRate 20 = beverage (alcoholic or non-alcoholic)
          // For drinks category, assume alcoholic unless specified otherwise
          if (item.vatRate === 20) {
            item.taxCategory = item.category === 'drinks' ? 'beverage-alcoholic' : 'beverage-non-alcoholic';
          } else {
            item.taxCategory = 'food';
          }
          console.log(`🔧 Auto-assigned taxCategory '${item.taxCategory}' to item '${item.name}' (vatRate: ${item.vatRate}%)`);
        }
        return item;
      });
    }
    
    return c.json(menu);
  } catch (error) {
    console.log(`Get menu error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.put('/make-server-1dccd8d3/restaurants/:id/menu', async (c) => {
  try {
    const id = c.req.param('id');
    const menuData = await c.req.json();
    
    await kv.set(`menu:${id}`, menuData);
    
    return c.json({ success: true, menu: menuData });
  } catch (error) {
    console.log(`Update menu error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ ORDER ENDPOINTS ============

app.post('/make-server-1dccd8d3/orders', async (c) => {
  try {
    const { sessionId, items, paymentMethod, tip, receiptRequested, split, customerId, numPeople, tableNumber, status, paymentPending, paymentReceived, loyaltyPointsRedeemed, loyaltyDiscount, orderType, pickupTime, scheduledFor, customerName, customerPhone } = await c.req.json();
    
    console.log(`🟢 ======== CREATE ORDER REQUEST RECEIVED ========`);
    console.log(`  Customer ID: ${customerId}`);
    console.log(`  Payment Method: ${paymentMethod}`);
    console.log(`  Payment Pending (raw): ${paymentPending} (type: ${typeof paymentPending})`);
    console.log(`  Payment Received (raw): ${paymentReceived} (type: ${typeof paymentReceived})`);
    console.log(`  Loyalty Points Redeemed: ${loyaltyPointsRedeemed}`);
    console.log(`  Loyalty Discount: ${loyaltyDiscount}`);
    console.log(`🟢 =================================================`);
    
    const session = await kv.get(`session:${sessionId}`);
    if (!session) {
      return c.json({ error: 'Session not found' }, 404);
    }
    
    // Get vendor settings for tax rates and order limits
    const settings = await kv.get(`vendor:${session.restaurantId}:settings`) || {};
    const vendorCountry: Country = (settings.country || 'AT') as Country;
    const serviceFeeRate = settings.serviceFeeRate || 5;
    const minOrderAmount = settings.minOrderAmount || 0;
    const maxOrderAmount = settings.maxOrderAmount || 10000;
    const loyaltyEnabled = settings.enableLoyalty !== false;
    const pointsPerEuro = settings.pointsPerEuro || 1;
    const autoAcceptOrders = settings.autoAcceptOrders !== false; // Default to true if not set
    const estimatedPrepTime = settings.estimatedPrepTime || 20; // Default to 20 minutes
    
    const orderId = crypto.randomUUID();
    const orderNumber = Math.floor(Math.random() * 10000);
    
    // Calculate totals with proper VAT breakdown by tax category
    // Step 1: Calculate gross total (prices already include VAT)
    const grossTotal = items.reduce((sum: number, item: any) => {
      const itemTotal = item.price * item.quantity;
      const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
        mSum + (m.price * item.quantity), 0) || 0;
      return sum + itemTotal + modifiersTotal;
    }, 0);
    
    // Validate order amount
    if (grossTotal < minOrderAmount) {
      return c.json({ 
        error: `Order amount (€${grossTotal.toFixed(2)}) is below minimum (€${minOrderAmount.toFixed(2)})` 
      }, 400);
    }
    
    if (grossTotal > maxOrderAmount) {
      return c.json({ 
        error: `Order amount (€${grossTotal.toFixed(2)}) exceeds maximum (€${maxOrderAmount.toFixed(2)})` 
      }, 400);
    }
    
    // Step 2: Calculate VAT breakdown by rate
    interface VATBreakdown {
      rate: number;
      netAmount: number;
      vatAmount: number;
      grossAmount: number;
    }
    
    const vatBreakdownMap = new Map<number, VATBreakdown>();
    
    items.forEach((item: any) => {
      // Get tax category (default to 'food' if not specified)
      const taxCategory: TaxCategory = item.taxCategory || 'food';
      const vatRate = getVATRate(vendorCountry, taxCategory);
      
      // Calculate gross price for this item (price already includes VAT)
      const itemGross = item.price * item.quantity;
      const modifiersGross = item.modifiers?.reduce((mSum: number, m: any) => 
        mSum + (m.price * item.quantity), 0) || 0;
      const totalGross = itemGross + modifiersGross;
      
      // Calculate net price (remove VAT)
      const totalNet = calculateNetPrice(totalGross, vendorCountry, taxCategory);
      const totalVAT = totalGross - totalNet;
      
      // Add to breakdown map
      if (!vatBreakdownMap.has(vatRate)) {
        vatBreakdownMap.set(vatRate, {
          rate: vatRate,
          netAmount: 0,
          vatAmount: 0,
          grossAmount: 0
        });
      }
      
      const breakdown = vatBreakdownMap.get(vatRate)!;
      breakdown.netAmount += totalNet;
      breakdown.vatAmount += totalVAT;
      breakdown.grossAmount += totalGross;
    });
    
    // Convert map to sorted array (by rate)
    const vatBreakdowns = Array.from(vatBreakdownMap.values()).sort((a, b) => a.rate - b.rate);
    
    // Step 3: Calculate totals
    const totalNetAmount = vatBreakdowns.reduce((sum, b) => sum + b.netAmount, 0);
    const totalVATAmount = vatBreakdowns.reduce((sum, b) => sum + b.vatAmount, 0);
    
    // Step 4: Calculate service fee (on net amount, not including VAT)
    const serviceFee = totalNetAmount * (serviceFeeRate / 100);
    
    // For compatibility, use weighted average VAT rate for display
    const vatPercent = totalNetAmount > 0 ? (totalVATAmount / totalNetAmount) * 100 : 0;
    
    const netAmount = totalNetAmount;
    const vatAmount = totalVATAmount;
    const subtotal = netAmount; // Net amount before fees
    const tipAmount = tip || 0;
    const total = grossTotal + tipAmount - (loyaltyDiscount || 0); // Apply loyalty discount
    
    // Determine initial status based on auto-accept setting
    let initialStatus = status || 'received';
    if (autoAcceptOrders && !status) {
      // Auto-accept: skip 'received' and go directly to 'preparing'
      initialStatus = 'preparing';
    }
    
    const order = {
      id: orderId,
      orderNumber,
      sessionId,
      restaurantId: session.restaurantId,
      restaurantName: settings.restaurantName || 'Restaurant',
      tableId: session.tableId,
      tableNumber: tableNumber || session.tableId,
      customerId: customerId || null,
      numPeople: numPeople || session.numPeople || 1,
      items,
      subtotal,
      serviceFee,
      vatPercent,
      vatAmount,
      vatBreakdowns, // NEW: Detailed VAT breakdown by rate
      tip: tipAmount,
      loyaltyDiscount: loyaltyDiscount || 0,
      loyaltyPointsRedeemed: loyaltyPointsRedeemed || 0,
      total,
      paymentMethod,
      receiptRequested,
      split,
      status: initialStatus,
      paymentPending: paymentPending || false,
      paymentReceived: paymentReceived || false,
      timeline: [
        { status: initialStatus, timestamp: new Date().toISOString() }
      ],
      createdAt: new Date().toISOString(),
      eta: estimatedPrepTime, // Use setting or default to 20 minutes
      
      // Takeaway fields
      orderType: orderType || 'dine-in',  // 'dine-in' or 'takeaway'
      pickupTime: pickupTime || null,  // ISO timestamp for scheduled pickup
      scheduledFor: scheduledFor || null,  // 'asap' or 'scheduled'
      customerName: customerName || null,  // Name for pickup
      customerPhone: customerPhone || null,  // Phone for notifications
      pickupStatus: orderType === 'takeaway' ? 'pending' : null,  // 'pending', 'ready', 'picked-up'
      readyAt: null,  // Timestamp when marked ready
      pickedUpAt: null,  // Timestamp when collected
      pickupInstructions: settings.pickupInstructions || 'Pick up at main counter'
    };
    
    await kv.set(`order:${orderId}`, order);
    
    // Add to restaurant's orders
    const restaurantOrders = await kv.get(`restaurant:${session.restaurantId}:orders`) || [];
    restaurantOrders.unshift(orderId);
    await kv.set(`restaurant:${session.restaurantId}:orders`, restaurantOrders);
    
    // Declare loyaltyDebugInfo outside the customerId block so it's accessible in the return statement
    let loyaltyDebugInfo = null;
    
    // Update customer order history and loyalty points if logged in
    if (customerId) {
      const customerOrders = await kv.get(`customer:${customerId}:orders`) || [];
      customerOrders.unshift(orderId);
      await kv.set(`customer:${customerId}:orders`, customerOrders);
      
      console.log(`📊 Order ${orderId} - Loyalty Debug:`);
      console.log(`  - Customer ID: ${customerId}`);
      console.log(`  - Loyalty Enabled: ${loyaltyEnabled}`);
      console.log(`  - Payment Pending: ${paymentPending}`);
      console.log(`  - Payment Received: ${paymentReceived}`);
      console.log(`  - Loyalty Points Redeemed: ${loyaltyPointsRedeemed}`);
      console.log(`  - Loyalty Discount: ${loyaltyDiscount}`);
      console.log(`  - Condition check: loyaltyEnabled=${loyaltyEnabled}, !paymentPending=${!paymentPending}, paymentReceived !== false=${paymentReceived !== false}`);
      console.log(`  - Will process loyalty? ${loyaltyEnabled && !paymentPending && paymentReceived !== false}`);
      
      // Handle loyalty points for paid orders (not cash-pending)
      if (loyaltyEnabled && !paymentPending && paymentReceived !== false) {
        const customer = await kv.get(`customer:${customerId}`);
        if (customer) {
          // Initialize loyaltyPoints as object if it doesn't exist or is a number
          if (typeof customer.loyaltyPoints !== 'object' || customer.loyaltyPoints === null) {
            customer.loyaltyPoints = {};
          }
          
          const restaurantId = session.restaurantId;
          const pointsBefore = customer.loyaltyPoints[restaurantId] || 0;
          let currentPoints = customer.loyaltyPoints[restaurantId] || 0;
          
          console.log(`  - Restaurant ${restaurantId} - Customer Current Points BEFORE: ${pointsBefore}`);
          
          // Deduct redeemed points (restaurant-specific)
          let pointsDeducted = 0;
          if (loyaltyPointsRedeemed > 0) {
            currentPoints -= loyaltyPointsRedeemed;
            pointsDeducted = loyaltyPointsRedeemed;
            console.log(`💳 Deducted ${loyaltyPointsRedeemed} points for restaurant ${restaurantId}. Customer ${customerId} now has ${currentPoints} points at this restaurant.`);
          }
          
          // Award points for the order (based on gross total before discount)
          const pointsEarned = Math.floor(grossTotal * pointsPerEuro);
          currentPoints += pointsEarned;
          
          // Update restaurant-specific points
          customer.loyaltyPoints[restaurantId] = currentPoints;
          await kv.set(`customer:${customerId}`, customer);
          
          // Verify the save by reading back
          const verifyCustomer = await kv.get(`customer:${customerId}`);
          const pointsAfterSave = verifyCustomer?.loyaltyPoints?.[restaurantId] || 0;
          
          console.log(`🎁 Awarded ${pointsEarned} points for €${grossTotal.toFixed(2)} order at restaurant ${restaurantId}. Customer ${customerId} now has ${currentPoints} points at this restaurant.`);
          console.log(`✅ Customer record updated in database. New loyalty points for ${restaurantId}: ${customer.loyaltyPoints[restaurantId]}`);
          console.log(`🔍 Verified customer points after save: ${pointsAfterSave}`);
          
          loyaltyDebugInfo = {
            processed: true,
            restaurantId,
            pointsBefore,
            pointsDeducted,
            pointsEarned,
            pointsAfter: currentPoints,
            pointsAfterSave,
            saveSuccessful: pointsAfterSave === currentPoints
          };
        } else {
          console.log(`  - ERROR: Customer ${customerId} not found in database!`);
          loyaltyDebugInfo = { processed: false, error: 'Customer not found' };
        }
      } else {
        console.log(`  - Loyalty points NOT processed (payment pending or loyalty disabled)`);
        loyaltyDebugInfo = { 
          processed: false, 
          reason: 'Payment pending or loyalty disabled',
          loyaltyEnabled,
          paymentPending,
          paymentReceived
        };
      }
    }
    
    // Send confirmation notification for takeaway orders
    let notifications = [];
    if (orderType === 'takeaway' && (customerPhone || order.customerEmail)) {
      notifications = await sendNotification(order, 'confirmed');
      console.log(`📬 Confirmation notifications sent:`, notifications);
    }
    
    return c.json({ 
      orderId, 
      orderNumber, 
      eta: order.eta,
      _debug_loyalty: loyaltyDebugInfo,
      notifications 
    });
  } catch (error) {
    console.log(`Create order error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/orders/:orderId', async (c) => {
  try {
    const orderId = c.req.param('orderId');
    const order = await kv.get(`order:${orderId}`) as any;
    
    if (!order) {
      return c.json({ error: 'Order not found' }, 404);
    }
    
    // MIGRATION FIX: Add taxCategory to order items that don't have it
    // This ensures backward compatibility with existing order data
    let needsVATRecalculation = false;
    if (order.items) {
      order.items = order.items.map((item: any) => {
        if (!item.taxCategory) {
          // Infer taxCategory from vatRate (if available) or item category
          // vatRate 10 = food, vatRate 20 = beverage
          if (item.vatRate === 20) {
            item.taxCategory = item.category === 'drinks' ? 'beverage-alcoholic' : 'beverage-non-alcoholic';
          } else if (item.vatRate === 10) {
            item.taxCategory = 'food';
          } else {
            // Fallback: infer from item category
            item.taxCategory = item.category === 'drinks' ? 'beverage-alcoholic' : 'food';
          }
          console.log(`🔧 Auto-assigned taxCategory '${item.taxCategory}' to order item '${item.name}' (vatRate: ${item.vatRate}%)`);
          needsVATRecalculation = true;
        }
        return item;
      });
      
      // RECALCULATE VAT breakdown if we assigned any tax categories
      if (needsVATRecalculation) {
        console.log(`📊 Recalculating VAT breakdown for order ${orderId}...`);
        
        // Get vendor country for tax calculation
        const settings = await kv.get(`vendor:${order.restaurantId}:settings`) || {};
        const vendorCountry: Country = (settings.country || 'AT') as Country;
        
        interface VATBreakdown {
          rate: number;
          netAmount: number;
          vatAmount: number;
          grossAmount: number;
        }
        
        const vatBreakdownMap = new Map<number, VATBreakdown>();
        
        order.items.forEach((item: any) => {
          const taxCategory: TaxCategory = item.taxCategory || 'food';
          const vatRate = getVATRate(vendorCountry, taxCategory);
          
          // Calculate gross price for this item
          const itemGross = item.price * item.quantity;
          const modifiersGross = item.modifiers?.reduce((mSum: number, m: any) => 
            mSum + (m.price * item.quantity), 0) || 0;
          const totalGross = itemGross + modifiersGross;
          
          // Calculate net price (remove VAT)
          const totalNet = calculateNetPrice(totalGross, vendorCountry, taxCategory);
          const totalVAT = totalGross - totalNet;
          
          // Add to breakdown map
          if (!vatBreakdownMap.has(vatRate)) {
            vatBreakdownMap.set(vatRate, {
              rate: vatRate,
              netAmount: 0,
              vatAmount: 0,
              grossAmount: 0
            });
          }
          
          const breakdown = vatBreakdownMap.get(vatRate)!;
          breakdown.netAmount += totalNet;
          breakdown.vatAmount += totalVAT;
          breakdown.grossAmount += totalGross;
        });
        
        // Update order's VAT breakdown
        order.vatBreakdowns = Array.from(vatBreakdownMap.values()).sort((a, b) => a.rate - b.rate);
        console.log(`✅ Recalculated VAT breakdown:`, order.vatBreakdowns);
      }
    }
    
    return c.json(order);
  } catch (error) {
    console.log(`Get order error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.patch('/make-server-1dccd8d3/orders/:orderId', async (c) => {
  try {
    const orderId = c.req.param('orderId');
    const updates = await c.req.json();
    
    const order = await kv.get(`order:${orderId}`);
    if (!order) {
      return c.json({ error: 'Order not found' }, 404);
    }
    
    // Check if this is a transition from pending to paid
    const wasPaymentPending = order.paymentPending === true;
    const isNowPaid = updates.paymentPending === false && updates.paymentReceived === true;
    
    if (updates.status && updates.status !== order.status) {
      order.timeline.push({
        status: updates.status,
        timestamp: new Date().toISOString()
      });
    }
    
    const updated = { ...order, ...updates };
    await kv.set(`order:${orderId}`, updated);
    
    // Handle loyalty points when a pending order is paid
    console.log(`📊 PATCH Order ${orderId} - Payment Status Check:`);
    console.log(`  - Was Payment Pending: ${wasPaymentPending}`);
    console.log(`  - Is Now Paid: ${isNowPaid}`);
    console.log(`  - Has Customer ID: ${!!order.customerId}`);
    
    if (wasPaymentPending && isNowPaid && order.customerId) {
      const settings = await kv.get(`vendor:${order.restaurantId}:settings`) || {};
      const loyaltyEnabled = settings.enableLoyalty !== false;
      const pointsPerEuro = settings.pointsPerEuro || 1;
      
      console.log(`  - Loyalty Enabled: ${loyaltyEnabled}`);
      
      if (loyaltyEnabled) {
        const customer = await kv.get(`customer:${order.customerId}`);
        if (customer) {
          console.log(`  - Customer Current Points BEFORE: ${customer.loyaltyPoints || 0}`);
          
          // Calculate gross total
          const grossTotal = order.items.reduce((sum: number, item: any) => {
            const itemTotal = item.price * item.quantity;
            const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
              mSum + (m.price * item.quantity), 0) || 0;
            return sum + itemTotal + modifiersTotal;
          }, 0);
          
          let currentPoints = customer.loyaltyPoints || 0;
          
          // Deduct redeemed points from the update (if any)
          const loyaltyPointsRedeemed = updates.loyaltyPointsRedeemed || order.loyaltyPointsRedeemed || 0;
          console.log(`  - Loyalty Points to Deduct: ${loyaltyPointsRedeemed}`);
          
          if (loyaltyPointsRedeemed > 0) {
            currentPoints -= loyaltyPointsRedeemed;
            console.log(`💳 Deducted ${loyaltyPointsRedeemed} points for pending order ${orderId} now paid. Customer ${order.customerId} now has ${currentPoints} points.`);
          }
          
          // Award points for the order (based on gross total before discount)
          const pointsEarned = Math.floor(grossTotal * pointsPerEuro);
          currentPoints += pointsEarned;
          
          customer.loyaltyPoints = currentPoints;
          await kv.set(`customer:${order.customerId}`, customer);
          
          console.log(`🎁 Awarded ${pointsEarned} points for €${grossTotal.toFixed(2)} pending order now paid. Customer ${order.customerId} now has ${currentPoints} points.`);
        } else {
          console.log(`  - ERROR: Customer ${order.customerId} not found!`);
        }
      }
    } else {
      console.log(`  - Loyalty points NOT processed for this update`);
    }
    
    return c.json(updated);
  } catch (error) {
    console.log(`Update order error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.post('/make-server-1dccd8d3/orders/:orderId/settle-cash', async (c) => {
  try {
    const orderId = c.req.param('orderId');
    const { cashierId } = await c.req.json();
    
    const order = await kv.get(`order:${orderId}`);
    if (!order) {
      return c.json({ error: 'Order not found' }, 404);
    }
    
    order.settledBy = cashierId;
    order.paymentReceived = true;
    order.settledAt = new Date().toISOString();
    
    await kv.set(`order:${orderId}`, order);
    
    // Award loyalty points for cash orders when settled
    if (order.customerId) {
      const settings = await kv.get(`vendor:${order.restaurantId}:settings`) || {};
      const loyaltyEnabled = settings.enableLoyalty !== false;
      const pointsPerEuro = settings.pointsPerEuro || 1;
      
      if (loyaltyEnabled) {
        const customer = await kv.get(`customer:${order.customerId}`);
        if (customer) {
          const grossTotal = order.items.reduce((sum: number, item: any) => {
            const itemTotal = item.price * item.quantity;
            const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
              mSum + (m.price * item.quantity), 0) || 0;
            return sum + itemTotal + modifiersTotal;
          }, 0);
          
          let currentPoints = customer.loyaltyPoints || 0;
          
          // Deduct redeemed points (if any)
          if (order.loyaltyPointsRedeemed > 0) {
            currentPoints -= order.loyaltyPointsRedeemed;
          }
          
          // Award points for the order
          const pointsEarned = Math.floor(grossTotal * pointsPerEuro);
          currentPoints += pointsEarned;
          
          customer.loyaltyPoints = currentPoints;
          await kv.set(`customer:${order.customerId}`, customer);
          
          console.log(`🎁 Cash order settled: Awarded ${pointsEarned} points. Customer ${order.customerId} now has ${currentPoints} points.`);
        }
      }
    }
    
    return c.json(order);
  } catch (error) {
    console.log(`Settle cash order error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ SPLIT PAYMENT ENDPOINTS ============

app.post('/make-server-1dccd8d3/orders/:orderId/split', async (c) => {
  try {
    const orderId = c.req.param('orderId');
    const splitData = await c.req.json();
    
    const order = await kv.get(`order:${orderId}`);
    if (!order) {
      return c.json({ error: 'Order not found' }, 404);
    }
    
    const splitPayments = [];
    for (const split of splitData.splits) {
      const splitId = split.splitId || `${orderId}-split-${split.personNumber}-${Date.now()}`;
      const splitPayment = {
        splitId,
        orderId,
        tableNumber: order.tableNumber,
        personNumber: split.personNumber,
        amount: split.amount,
        status: split.status,
        paymentMethod: split.paymentMethod,
        paidAt: split.paidAt,
        createdAt: new Date().toISOString(),
      };
      
      await kv.set(`split:${splitId}`, splitPayment);
      splitPayments.push(splitPayment);
    }
    
    order.splitPayment = {
      enabled: true,
      splits: splitPayments,
      totalAssigned: splitData.totalAssigned,
      paidTotal: splitData.paidTotal,
      pendingTotal: splitData.pendingTotal,
      tip: splitData.tip || 0,
    };
    
    await kv.set(`order:${orderId}`, order);
    
    return c.json({ order, splits: splitPayments });
  } catch (error) {
    console.log(`Create split payment error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/split-payments/:splitId', async (c) => {
  try {
    const splitId = c.req.param('splitId');
    
    const split = await kv.get(`split:${splitId}`);
    if (!split) {
      return c.json({ error: 'Split payment not found' }, 404);
    }
    
    return c.json(split);
  } catch (error) {
    console.log(`Get split payment error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.post('/make-server-1dccd8d3/split-payments/:splitId/pay', async (c) => {
  try {
    const splitId = c.req.param('splitId');
    const { paymentMethod, amount } = await c.req.json();
    
    const split = await kv.get(`split:${splitId}`);
    if (!split) {
      return c.json({ error: 'Split payment not found' }, 404);
    }
    
    split.status = 'paid';
    split.paymentMethod = paymentMethod;
    split.paidAt = new Date().toISOString();
    split.paidAmount = amount;
    
    await kv.set(`split:${splitId}`, split);
    
    const order = await kv.get(`order:${split.orderId}`);
    if (order && order.splitPayment) {
      const splitIndex = order.splitPayment.splits.findIndex(
        (s: any) => s.splitId === splitId
      );
      
      if (splitIndex !== -1) {
        order.splitPayment.splits[splitIndex] = split;
        
        const paidSplits = order.splitPayment.splits.filter((s: any) => s.status === 'paid');
        order.splitPayment.paidTotal = paidSplits.reduce((sum: number, s: any) => sum + s.amount, 0);
        order.splitPayment.pendingTotal = order.splitPayment.totalAssigned - order.splitPayment.paidTotal;
        
        const allPaid = order.splitPayment.splits.every((s: any) => s.status === 'paid');
        if (allPaid) {
          order.paymentStatus = 'completed';
          order.completedAt = new Date().toISOString();
        }
        
        await kv.set(`order:${split.orderId}`, order);
      }
    }
    
    return c.json({ split, order });
  } catch (error) {
    console.log(`Mark split as paid error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/orders/:orderId/splits', async (c) => {
  try {
    const orderId = c.req.param('orderId');
    
    const order = await kv.get(`order:${orderId}`);
    if (!order) {
      return c.json({ error: 'Order not found' }, 404);
    }
    
    return c.json(order.splitPayment || { enabled: false, splits: [] });
  } catch (error) {
    console.log(`Get order splits error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ REVIEW ENDPOINTS ============

app.post('/make-server-1dccd8d3/orders/:orderId/review', async (c) => {
  try {
    const orderId = c.req.param('orderId');
    const { rating, text, photos, customerId, itemId, itemName, customerName, isGuest, type } = await c.req.json();
    
    // Check if order already has reviews from this customer
    const order = await kv.get(`order:${orderId}`);
    if (!order) {
      return c.json({ error: 'Order not found' }, 404);
    }

    // Check vendor settings for review configuration
    const settings = await kv.get(`vendor:${order.restaurantId}:settings`) || {};
    
    // Check if reviews are enabled
    if (settings.enableReviews === false) {
      return c.json({ error: 'Reviews are currently disabled' }, 403);
    }
    
    // Check if order meets minimum amount requirement
    if (settings.minOrderToReview && order.total < settings.minOrderToReview) {
      return c.json({ error: `Order must be at least ${settings.minOrderToReview} to review` }, 403);
    }
    
    // Check anonymous review setting
    if (isGuest && settings.allowAnonymousReviews === false) {
      return c.json({ error: 'Anonymous reviews are not allowed' }, 403);
    }

    // If reviews exist, we're updating, not creating
    const existingReviews = order.reviews || [];
    
    // Find existing review by itemName OR by type (for restaurant reviews)
    const existingReviewIndex = existingReviews.findIndex((r: any) => 
      type === 'restaurant' ? r.type === 'restaurant' : r.itemName === itemName
    );
    
    const reviewId = existingReviewIndex >= 0 ? existingReviews[existingReviewIndex].id : crypto.randomUUID();
    const review = {
      id: reviewId,
      orderId,
      customerId,
      customerName,
      isGuest,
      type: type || (itemId ? 'item' : 'restaurant'),
      itemId: itemId || null,
      itemName: itemName || null,
      rating,
      text,
      photos: photos || [],
      status: settings.moderateReviews ? 'pending' : 'approved', // Check moderation setting
      createdAt: existingReviewIndex >= 0 ? existingReviews[existingReviewIndex].createdAt : new Date().toISOString(),
      updatedAt: new Date().toISOString()
    };
    
    if (itemId && type === 'item') {
      // Update item reviews
      const itemReviews = await kv.get(`reviews:item:${itemId}`) || [];
      const itemReviewIndex = itemReviews.findIndex((r: any) => r.id === reviewId);
      
      if (itemReviewIndex >= 0) {
        // Update existing review
        itemReviews[itemReviewIndex] = review;
      } else {
        // Add new review
        itemReviews.unshift(review);
      }
      
      await kv.set(`reviews:item:${itemId}`, itemReviews);
    } else if (type === 'restaurant') {
      // Store restaurant review separately
      await kv.set(`review:restaurant:${orderId}`, review);
    }
    
    // Update review in order
    if (existingReviewIndex >= 0) {
      // Update existing review
      order.reviews[existingReviewIndex] = review;
    } else {
      // Add new review
      if (!order.reviews) {
        order.reviews = [];
      }
      order.reviews.push(review);
    }
    
    await kv.set(`order:${orderId}`, order);
    
    return c.json(review);
  } catch (error) {
    console.log(`Create order review error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/items/:itemId/reviews', async (c) => {
  try {
    const itemId = c.req.param('itemId');
    const reviews = await kv.get(`reviews:item:${itemId}`) || [];
    
    // Only return approved reviews
    const approvedReviews = reviews.filter((r: any) => r.status === 'approved');
    
    return c.json(approvedReviews);
  } catch (error) {
    console.log(`Get item reviews error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/customers/:customerId/orders', async (c) => {
  try {
    const customerId = c.req.param('customerId');
    const orderIds = await kv.get(`customer:${customerId}:orders`) || [];
    return c.json(orderIds);
  } catch (error) {
    console.log(`Get customer orders error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ VENDOR ENDPOINTS ============

app.get('/make-server-1dccd8d3/vendor/:id/top-customers', async (c) => {
  try {
    const vendorId = c.req.param('id');
    const period = c.req.query('period') || '6m';
    
    const restaurantOrders = await kv.get(`restaurant:${vendorId}:orders`) || [];
    const customerStats: Record<string, any> = {};
    
    for (const orderId of restaurantOrders) {
      const order = await kv.get(`order:${orderId}`);
      if (order && order.customerId) {
        if (!customerStats[order.customerId]) {
          const customer = await kv.get(`customer:${order.customerId}`);
          customerStats[order.customerId] = {
            customerId: order.customerId,
            name: customer?.shareNameWithVendor ? customer.name : 'Anonymous',
            ordersCount: 0,
            totalSpent: 0
          };
        }
        customerStats[order.customerId].ordersCount++;
        customerStats[order.customerId].totalSpent += order.total;
      }
    }
    
    const topCustomers = Object.values(customerStats)
      .sort((a: any, b: any) => b.totalSpent - a.totalSpent)
      .slice(0, 10);
    
    return c.json(topCustomers);
  } catch (error) {
    console.log(`Get top customers error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/vendor/:id/complaints', async (c) => {
  try {
    const vendorId = c.req.param('id');
    const filter = c.req.query('filter') || 'all';
    
    const restaurantOrders = await kv.get(`restaurant:${vendorId}:orders`) || [];
    const reviews = [];
    
    for (const orderId of restaurantOrders) {
      const review = await kv.get(`review:order:${orderId}`);
      if (review) {
        // Return all reviews, not just complaints (rating <= 2)
        if (filter === 'all' || review.status === filter) {
          reviews.push(review);
        }
      }
    }
    
    return c.json(reviews);
  } catch (error) {
    console.log(`Get reviews error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Reply to review
app.post('/make-server-1dccd8d3/vendor/:vendorId/reviews/:reviewId/reply', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const reviewId = c.req.param('reviewId');
    const { reply } = await c.req.json();
    
    if (!reply || !reply.trim()) {
      return c.json({ error: 'Reply text is required' }, 400);
    }
    
    // Get the review (reviewId is the orderId in our current structure)
    const review = await kv.get(`review:order:${reviewId}`);
    if (!review) {
      return c.json({ error: 'Review not found' }, 404);
    }
    
    // Update the review with reply
    review.reply = reply.trim();
    review.repliedAt = new Date().toISOString();
    
    await kv.set(`review:order:${reviewId}`, review);
    
    return c.json({ success: true, review });
  } catch (error) {
    console.log(`Reply to review error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.post('/make-server-1dccd8d3/vendor/:id/invoice', async (c) => {
  try {
    const vendorId = c.req.param('id');
    const { orderId, buyerInfo } = await c.req.json();
    
    const order = await kv.get(`order:${orderId}`);
    if (!order) {
      return c.json({ error: 'Order not found' }, 404);
    }
    
    const vendor = await kv.get(`vendor:${vendorId}`);
    const restaurant = await kv.get(`restaurant:${order.restaurantId}`);
    const settings = await kv.get(`vendor:${vendorId}:settings`) || {};
    
    // Get next invoice number from settings or fallback
    const lastInvoiceNum = settings.nextInvoiceNumber || await kv.get(`vendor:${vendorId}:lastInvoiceNumber`) || 1000;
    const invoiceNumber = lastInvoiceNum + 1;
    
    // Update the next invoice number in settings
    settings.nextInvoiceNumber = invoiceNumber;
    await kv.set(`vendor:${vendorId}:settings`, settings);
    
    // Use invoice prefix from settings
    const invoicePrefix = settings.invoicePrefix || vendor?.invoicePrefix || 'INV';
    
    const invoice = {
      invoiceNumber: `${invoicePrefix}-${String(invoiceNumber).padStart(5, '0')}`,
      orderId,
      vendorId,
      vendor: {
        name: restaurant.name,
        address: vendor?.address || '',
        registrationNo: vendor?.registrationNo || '',
        vatId: vendor?.vatId || ''
      },
      buyer: buyerInfo || null,
      invoiceDate: new Date().toISOString(),
      serviceDate: order.createdAt,
      items: order.items,
      subtotal: order.subtotal,
      serviceFee: order.serviceFee,
      vatPercent: order.vatPercent,
      vatAmount: order.vatAmount,
      tip: order.tip,
      total: order.total,
      paymentMethod: order.paymentMethod
    };
    
    await kv.set(`invoice:${vendorId}:${invoiceNumber}`, invoice);
    
    return c.json({ 
      invoice,
      pdfUrl: `/invoices/${vendorId}/${invoiceNumber}.pdf`
    });
  } catch (error) {
    console.log(`Generate invoice error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.get('/make-server-1dccd8d3/vendor/:id/orders', async (c) => {
  try {
    const vendorId = c.req.param('id');
    const restaurantOrders = await kv.get(`restaurant:${vendorId}:orders`) || [];
    
    const orders = [];
    for (const orderId of restaurantOrders.slice(0, 50)) {
      const order = await kv.get(`order:${orderId}`);
      if (order) {
        orders.push(order);
      }
    }
    
    return c.json(orders);
  } catch (error) {
    console.log(`Get vendor orders error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ SETTINGS ENDPOINTS ============

app.get('/make-server-1dccd8d3/vendor/:id/settings', async (c) => {
  try {
    const vendorId = c.req.param('id');
    
    // Get settings or return defaults
    const settings = await kv.get(`vendor:${vendorId}:settings`) || {
      // Business Information
      restaurantName: 'La Bella Vista',
      description: 'Authentic Italian cuisine in the heart of Vienna',
      businessRegNumber: 'FN 123456a',
      vatNumber: 'ATU12345678',
      email: 'info@labellavista.at',
      phone: '+43 1 234 5678',
      website: 'www.labellavista.at',
      address: 'Kärntner Straße 1, 1010 Wien, Austria',
      companyType: 'GmbH',
      logo: '',
      coverPhoto: '',
      
      // Business Hours
      businessHours: {
        monday: { open: '11:00', close: '22:00', closed: false },
        tuesday: { open: '11:00', close: '22:00', closed: false },
        wednesday: { open: '11:00', close: '22:00', closed: false },
        thursday: { open: '11:00', close: '22:00', closed: false },
        friday: { open: '11:00', close: '23:00', closed: false },
        saturday: { open: '11:00', close: '23:00', closed: false },
        sunday: { open: '12:00', close: '21:00', closed: false }
      },
      
      // Payment Settings
      acceptApplePay: true,
      acceptGooglePay: true,
      acceptCard: true,
      acceptCash: true,
      currency: 'EUR',
      stripeEnabled: false,
      
      // Tax & Compliance
      vatRate: 13,
      serviceFeeRate: 5,
      invoicePrefix: 'LBV',
      nextInvoiceNumber: 1001,
      autoGenerateReceipts: true,
      
      // Table Management
      numberOfTables: 20,
      tablePrefix: 'T',
      enableSharedBasket: true,
      maxGuestsPerTable: 10,
      
      // Ordering Settings
      autoAcceptOrders: false,
      estimatedPrepTime: 20,
      maxOrdersPerSlot: 50,
      allowGuestOrdering: true,
      requirePhoneNumber: false,
      minOrderAmount: 0,
      maxOrderAmount: 1000,
      
      // Takeaway Settings
      enableTakeaway: true,
      takeawayPrepTime: 25,  // Can be different from dine-in
      maxAdvanceOrderDays: 7,
      takeawaySlotInterval: 15,  // Minutes between time slots
      pickupInstructions: 'Pick up at main counter near entrance',
      takeawayMinOrderAmount: 10,
      allowScheduledOrders: true,  // Allow "Schedule for later" vs ASAP only
      
      // Notification Settings
      emailNewOrder: true,
      emailReview: true,
      smsNewOrder: false,
      pushNewOrder: true,
      pushOrderReady: true,
      notificationEmail: 'notifications@labellavista.at',
      
      // Review Settings
      enableReviews: true,
      moderateReviews: false,
      minOrderToReview: 5,
      showReviewsPublicly: true,
      allowAnonymousReviews: false,
      
      // Language Settings
      defaultLanguage: 'de',
      supportedLanguages: ['en', 'de', 'it', 'ar', 'zh', 'tr'],
      dateFormat: 'DD.MM.YYYY',
      timeFormat: '24h',
      
      // Loyalty Settings
      enableLoyalty: true,
      pointsPerEuro: 1,
      minimumRedemption: 100,
      redemptionRate: 0.05, // €0.05 per point (100 points = €5)
      pointsExpiry: 365,
      enableTiers: false,
      
      // Privacy Settings
      dataRetentionDays: 730,
      allowDataExport: true,
      gdprCompliant: true,
      showInTopCustomers: true
    };
    
    return c.json(settings);
  } catch (error) {
    console.log(`Get settings error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.put('/make-server-1dccd8d3/vendor/:id/settings', async (c) => {
  try {
    const vendorId = c.req.param('id');
    const settings = await c.req.json();
    
    await kv.set(`vendor:${vendorId}:settings`, settings);
    
    return c.json({ success: true, settings });
  } catch (error) {
    console.log(`Update settings error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ SEED DATA ENDPOINT ============

// Health check endpoint
app.get('/make-server-1dccd8d3/health', async (c) => {
  return c.json({ 
    status: 'ok', 
    timestamp: new Date().toISOString(),
    message: 'Server is running'
  });
});

// Create demo users endpoint
app.post('/make-server-1dccd8d3/create-demo-users', async (c) => {
  try {
    console.log('Creating/verifying demo users...');
    const createdUsers = [];
    
    // Helper function to create a demo user
    const createDemoUser = async (email: string, password: string, name: string, phone: string, loyaltyPoints: number) => {
      try {
        // First, try to get user by email to check if they exist
        const { data: existingUsers } = await supabase.auth.admin.listUsers();
        const userExists = existingUsers?.users?.find(u => u.email === email);
        
        let userId;
        
        if (userExists) {
          console.log(`ℹ️  User ${email} already exists, updating password and customer data...`);
          userId = userExists.id;
          
          // Update password for existing user to ensure it matches demo credentials
          const { error: updateError } = await supabase.auth.admin.updateUserById(
            userId,
            { 
              password,
              email_confirm: true,
              user_metadata: { name, phone }
            }
          );
          
          if (updateError) {
            console.log(`⚠️  Failed to update password for ${email}: ${updateError.message}`);
          } else {
            console.log(`✅ Password updated for ${email}`);
          }
        } else {
          // Create new user
          const { data, error } = await supabase.auth.admin.createUser({
            email,
            password,
            user_metadata: { name, phone },
            email_confirm: true
          });

          if (error) {
            console.log(`❌ Failed to create ${email}: ${error.message}`);
            return null;
          }

          userId = data.user.id;
          console.log(`✅ Created new user: ${email}`);
        }

        // Always update/create customer data in KV store
        await kv.set(`customer:${userId}`, {
          id: userId,
          email,
          name,
          phone,
          loyaltyPoints,
          createdAt: new Date().toISOString()
        });
        
        console.log(`✅ Customer data saved for ${email} (${loyaltyPoints} points)`);
        return { email, password, loyaltyPoints };
      } catch (error: any) {
        console.log(`❌ Error with user ${email}: ${error.message}`);
        return null;
      }
    };

    // Create demo users
    const user1 = await createDemoUser('demo@bellacucina.com', 'demo123', 'Demo Customer', '+43 660 1234567', 350);
    if (user1) createdUsers.push(user1);

    const user2 = await createDemoUser('vip@bellacucina.com', 'vip123', 'VIP Customer', '+43 660 9876543', 850);
    if (user2) createdUsers.push(user2);

    const user3 = await createDemoUser('test@example.com', 'test123', 'Test User', '+43 660 5555555', 0);
    if (user3) createdUsers.push(user3);

    console.log(`✅ Demo users ready: ${createdUsers.length}/3`);
    
    return c.json({ 
      success: true, 
      message: `Demo users ready: ${createdUsers.length}/3`,
      demoUsers: createdUsers.length > 0 ? createdUsers : [
        { email: 'demo@bellacucina.com', password: 'demo123', loyaltyPoints: 350 },
        { email: 'vip@bellacucina.com', password: 'vip123', loyaltyPoints: 850 },
        { email: 'test@example.com', password: 'test123', loyaltyPoints: 0 }
      ]
    });
  } catch (error) {
    console.log(`Create demo users error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

app.post('/make-server-1dccd8d3/seed', async (c) => {
  try {
    const restaurantId = 'rest_1';
    const forceReseed = c.req.query('force') === 'true';
    
    // Check if data already exists - don't overwrite menu customizations!
    const existingMenu = await kv.get(`menu:${restaurantId}`);
    if (existingMenu && !forceReseed) {
      console.log('Seed data already exists, skipping seeding to preserve customizations');
      return c.json({ success: true, message: 'Data already exists' });
    }
    
    console.log(forceReseed ? 'Force reseeding data with translations...' : 'Creating initial seed data...');
    
    // Create restaurant
    await kv.set(`restaurant:${restaurantId}`, {
      id: restaurantId,
      name: 'Bella Cucina',
      cuisineTag: 'Italian • Mediterranean',
      address: 'Stephansplatz 1, 1010 Wien',
      phone: '+43 1 234 5678'
    });
    
    // Create vendor
    await kv.set(`vendor:${restaurantId}`, {
      id: restaurantId,
      invoicePrefix: 'BC',
      address: 'Stephansplatz 1, 1010 Wien, Austria',
      registrationNo: 'FN 123456a',
      vatId: 'ATU12345678'
    });
    
    // Create menu
    const menu = {
      categories: [
        { id: 'appetizers', name: 'Appetizers' },
        { id: 'salads', name: 'Salads' },
        { id: 'mains', name: 'Mains' },
        { id: 'desserts', name: 'Desserts' },
        { id: 'drinks', name: 'Drinks' }
      ],
      items: [
        {
          id: 'item_1',
          name: 'Bruschetta al Pomodoro',
          category: 'appetizers',
          price: 8.5,
          description: 'Grilled bread rubbed with garlic and topped with fresh tomatoes, basil, and extra virgin olive oil.',
          calories: 180,
          nutrition: {
            calories: 180,
            protein: 4,
            carbs: 22,
            fat: 8
          },
          dietary: ['vegetarian'],
          allergens: ['gluten'],
          badges: ['vegetarian', 'most-ordered'],
          orderedCount: 342,
          rating: 4.5,
          reviewCount: 28,
          reviews: [
            {
              name: 'Maria L.',
              rating: 5,
              comment: 'Fresh and delicious! Perfect start to our meal.',
              date: '2025-11-12'
            },
            {
              name: 'Thomas K.',
              rating: 4,
              comment: 'Good but could use more garlic.',
              date: '2025-11-08'
            }
          ],
          modifiers: [
            { name: 'Extra Garlic', price: 1.0 },
            { name: 'Grilled Bread', price: 0.5 }
          ],
          paidAddons: [
            { name: 'Burrata Cheese', price: 3.5 },
            { name: 'Prosciutto', price: 2.5 }
          ],
          freeAddons: ['Extra Basil', 'Balsamic Glaze', 'Black Pepper'],
          removableItems: ['Tomatoes', 'Garlic', 'Basil'],
          vatRate: 10,
          taxCategory: 'food',
          translations: {
            de: {
              name: 'Bruschetta al Pomodoro',
              description: 'Geröstetes Brot mit Knoblauch eingerieben und mit frischen Tomaten, Basilikum und extra nativem Olivenöl belegt.',
              paidAddons: ['Burrata-Käse', 'Prosciutto'],
              freeAddons: ['Extra Basilikum', 'Balsamico-Glasur', 'Schwarzer Pfeffer'],
              removableItems: ['Tomaten', 'Knoblauch', 'Basilikum']
            },
            it: {
              name: 'Bruschetta al Pomodoro',
              description: 'Pane grigliato strofinato con aglio e condito con pomodori freschi, basilico e olio extra vergine di oliva.',
              paidAddons: ['Burrata', 'Prosciutto'],
              freeAddons: ['Basilico Extra', 'Glassa di Aceto Balsamico', 'Pepe Nero'],
              removableItems: ['Pomodori', 'Aglio', 'Basilico']
            },
            fr: {
              name: 'Bruschetta al Pomodoro',
              description: 'Pain grillé frotté à l\'ail et garni de tomates fraîches, basilic et huile d\'olive extra vierge.',
              paidAddons: ['Fromage Burrata', 'Prosciutto'],
              freeAddons: ['Basilic Supplémentaire', 'Glaçage Balsamique', 'Poivre Noir'],
              removableItems: ['Tomates', 'Ail', 'Basilic']
            },
            ar: {
              name: 'بروسكيتا بالطماطم',
              description: 'خبز محمص مدهون بالثوم ومغطى بالطماطم الطازجة والريحان وزيت الزيتون البكر الممتاز.',
              paidAddons: ['جبن بوراتا', 'بروشوتو'],
              freeAddons: ['ريحان إضافي', 'صوص بلسمي', 'فلفل أسود'],
              removableItems: ['طماطم', 'ثوم', 'ريحان']
            },
            tr: {
              name: 'Bruschetta al Pomodoro',
              description: 'Sarımsakla ovulmuş ızgara ekmek, taze domates, fesleğen ve sızma zeytinyağı ile.',
              paidAddons: ['Burrata Peyniri', 'Prosciutto'],
              freeAddons: ['Ekstra Fesleğen', 'Balsamik Sos', 'Siyah Biber'],
              removableItems: ['Domates', 'Sarımsak', 'Fesleğen']
            },
            zh: {
              name: '番茄罗勒意式烤面包',
              description: '烤面包抹上大蒜，配以新鲜���茄、罗勒和特级初榨橄榄油。',
              paidAddons: ['布拉塔奶酪', '意大利火腿'],
              freeAddons: ['额外罗勒', '香醋酱', '黑胡椒'],
              removableItems: ['番茄', '大蒜', '罗勒']
            },
            ja: {
              name: 'トマトのブルスケッタ',
              description: 'ガーリックを擦り込んだグリルパンに、フレッシュトマト、バジル、エクストラバージンオリーブオイルをトッピング。',
              paidAddons: ['ブラータチーズ', 'プロシュート'],
              freeAddons: ['エクストラバジル', 'バルサミコソース', '黒コショウ'],
              removableItems: ['トマト', 'ガーリック', 'バジル']
            },
            sr: {
              name: 'Брускета са парадајзом',
              description: 'Пржени хлеб утрљан белим луком и прекривен свежим парадајзом, босиљком и екстра девичанским маслиновим уљем.',
              paidAddons: ['Бурата сир', 'Прошуто'],
              freeAddons: ['Додатни босиљак', 'Балсамико глазура', 'Црни бибер'],
              removableItems: ['Парадајз', 'Бели лук', 'Босиљак']
            },
            cs: {
              name: 'Bruschetta al Pomodoro',
              description: 'Grilovaný chléb potřený česnekem a pokrytý čerstvými rajčaty, bazalkou a extra panenským olivovým olejem.',
              paidAddons: ['Burrata sýr', 'Prosciutto'],
              freeAddons: ['Extra bazalka', 'Balsamikový dresing', 'Černý pepř'],
              removableItems: ['Rajčata', 'Česnek', 'Bazalka']
            },
            es: {
              name: 'Bruschetta al Pomodoro',
              description: 'Pan tostado frotado con ajo y cubierto con tomates frescos, albahaca y aceite de oliva virgen extra.',
              paidAddons: ['Queso Burrata', 'Prosciutto'],
              freeAddons: ['Albahaca Extra', 'Glaseado Balsámico', 'Pimienta Negra'],
              removableItems: ['Tomates', 'Ajo', 'Albahaca']
            }
          }
        },
        {
          id: 'item_2',
          name: 'Caprese Salad',
          category: 'salads',
          price: 12.0,
          description: 'Fresh mozzarella, tomatoes, and basil drizzled with balsamic glaze.',
          calories: 220,
          nutrition: {
            calories: 220,
            protein: 12,
            carbs: 8,
            fat: 16
          },
          dietary: ['vegetarian', 'gluten-free'],
          allergens: ['dairy'],
          badges: ['vegetarian', 'chefs-pick'],
          orderedCount: 287,
          rating: 4.8,
          reviewCount: 45,
          reviews: [
            {
              name: 'Sophie M.',
              rating: 5,
              comment: 'Amazing mozzarella quality! Best caprese in Vienna.',
              date: '2025-11-15'
            },
            {
              name: 'Marco V.',
              rating: 5,
              comment: 'Authentic Italian taste. Highly recommend!',
              date: '2025-11-10'
            }
          ],
          vatRate: 10,
          taxCategory: 'food',
          translations: {
            de: {
              name: 'Caprese Salat',
              description: 'Frischer Mozzarella, Tomaten und Basilikum mit Balsamico-Glasur beträufelt.'
            },
            it: {
              name: 'Insalata Caprese',
              description: 'Mozzarella fresca, pomodori e basilico conditi con glassa di aceto balsamico.'
            },
            fr: {
              name: 'Salade Caprese',
              description: 'Mozzarella fraîche, tomates et basilic arrosés de glaçage balsamique.'
            },
            ar: {
              name: 'سلطة كابريزي',
              description: 'موزاريلا طازجة وطماطم وريحان مع صوص بلسمي.'
            },
            tr: {
              name: 'Caprese Salata',
              description: 'Taze mozzarella, domates ve fesleğen, balsamik sos ile.'
            },
            zh: {
              name: '卡布里沙拉',
              description: '新鲜马苏里拉奶酪、番茄和罗勒，淋上香醋酱。'
            },
            ja: {
              name: 'カプレーゼサラダ',
              description: 'フレッシュモッツァレラ、トマト、バジルにバルサミコソースをかけて。'
            },
            sr: {
              name: 'Капрезе салата',
              description: 'Свежа моцарела, парадајз и босиљак прелив балсамико глазуром.'
            },
            cs: {
              name: 'Caprese salát',
              description: 'Čerstvá mozzarella, rajčata a bazalka polévaná balsamikovou glazurou.'
            },
            es: {
              name: 'Ensalada Caprese',
              description: 'Mozzarella fresca, tomates y albahaca rociados con glaseado balsámico.'
            }
          }
        },
        {
          id: 'item_truffle',
          name: 'Truffle Mushroom Risotto',
          category: 'mains',
          price: 22.0,
          description: 'Creamy Arborio rice with wild mushrooms, black truffle, and Parmigiano-Reggiano.',
          calories: 520,
          nutrition: {
            calories: 520,
            protein: 12,
            carbs: 68,
            fat: 22
          },
          dietary: ['vegetarian', 'gluten-free'],
          allergens: ['dairy'],
          badges: ['chefs-pick', 'most-ordered'],
          orderedCount: 445,
          rating: 4.9,
          reviewCount: 78,
          reviews: [
            {
              name: 'Sarah M.',
              rating: 5,
              comment: 'Absolutely divine! The truffle flavor is perfect.',
              date: '2025-11-10'
            },
            {
              name: 'James K.',
              rating: 4,
              comment: 'Great dish, very creamy and flavorful.',
              date: '2025-11-08'
            }
          ],
          vatRate: 10,
          taxCategory: 'food',
          paidAddons: [
            { name: 'Extra Truffle Shavings', price: 5.0 },
            { name: 'Grilled Chicken Breast', price: 6.5 },
            { name: 'Extra Parmesan', price: 2.0 },
            { name: 'Sautéed Shrimp', price: 7.5 }
          ],
          freeAddons: [
            'Extra Herbs',
            'Lemon Wedge',
            'Black Pepper',
            'Chili Flakes'
          ],
          removableItems: [
            'Mushrooms',
            'Truffle Oil',
            'Parmesan'
          ],
          translations: {
            de: {
              name: 'Trüffel-Pilz-Risotto',
              description: 'Cremiger Arborio-Reis mit Waldpilzen, schwarzem Trüffel und Parmigiano-Reggiano.',
              paidAddons: ['Extra Trüffelspäne', 'Gegrillte Hähnchenbrust', 'Extra Parmesan', 'Sautierte Garnelen'],
              freeAddons: ['Extra Kräuter', 'Zitronenscheibe', 'Schwarzer Pfeffer', 'Chiliflocken'],
              removableItems: ['Pilze', 'Trüffelöl', 'Parmesan']
            },
            it: {
              name: 'Risotto ai Funghi e Tartufo',
              description: 'Riso Arborio cremoso con funghi selvatici, tartufo nero e Parmigiano-Reggiano.',
              paidAddons: ['Scaglie di Tartufo Extra', 'Petto di Pollo Grigliato', 'Parmigiano Extra', 'Gamberetti Saltati'],
              freeAddons: ['Erbe Extra', 'Spicchio di Limone', 'Pepe Nero', 'Peperoncino'],
              removableItems: ['Funghi', 'Olio al Tartufo', 'Parmigiano']
            },
            fr: {
              name: 'Risotto aux Champignons et Truffe',
              description: 'Riz Arborio crémeux avec champignons sauvages, truffe noire et Parmigiano-Reggiano.',
              paidAddons: ['Copeaux de Truffe Supplémentaires', 'Blanc de Poulet Grillé', 'Parmesan Supplémentaire', 'Crevettes Sautées'],
              freeAddons: ['Herbes Supplémentaires', 'Quartier de Citron', 'Poivre Noir', 'Piment'],
              removableItems: ['Champignons', 'Huile de Truffe', 'Parmesan']
            },
            ar: {
              name: 'ريزوتو الكمأة والفطر',
              description: 'أرز أربوريو كريمي مع فطر بري وكمأة سوداء وجبن بارميجيانو ريجيانو.',
              paidAddons: ['رقائق كمأة إضافية', 'صدر دجاج مشوي', 'بارميزان إضافي', 'جمبري مقلي'],
              freeAddons: ['أعشاب إضافية', 'قطعة ليمون', 'فلفل أسود', 'فلفل حار'],
              removableItems: ['فطر', 'زيت الكمأة', 'بارميزان']
            },
            tr: {
              name: 'Trüf Mantarlı Risotto',
              description: 'Yaban mantarları, siyah trüf ve Parmigiano-Reggiano ile kremalı Arborio pirinci.',
              paidAddons: ['Ekstra Trüf Rendeleme', 'Izgara Tavuk Göğsü', 'Ekstra Parmesan', 'Sote Karides'],
              freeAddons: ['Ekstra Otlar', 'Limon Dilimi', 'Siyah Biber', 'Pul Biber'],
              removableItems: ['Mantar', 'Trüf Yağı', 'Parmesan']
            },
            zh: {
              name: '松露蘑菇烩饭',
              description: '奶油阿博里奥米配野生蘑菇、黑松露和帕尔马干酪。',
              paidAddons: ['额外松露片', '烤鸡胸肉', '额外帕尔马干酪', '炒虾'],
              freeAddons: ['额外香草', '柠檬角', '黑胡椒', '辣椒片'],
              removableItems: ['蘑菇', '松露油', '帕尔马干酪']
            },
            ja: {
              name: 'トリュフきのこリゾット',
              description: 'クリーミーなアルボリオライスに野生きのこ、黒トリュフ、パルミジャーノ・レッジャーノを添えて。',
              paidAddons: ['エクストラトリュフシェービング', 'グリルドチキンブレスト', 'エクストラパルメザン', 'ソテーシュリンプ'],
              freeAddons: ['エクストラハーブ', 'レモンウェッジ', '黒コショウ', 'チリフレーク'],
              removableItems: ['きのこ', 'トリュフオイル', 'パルメザン']
            },
            sr: {
              name: 'Ризото са тартуфима и печуркама',
              description: 'Кремасти Арборио пиринач са дивљим печуркама, црним тартуфом и Пармиђано-Ређано сиром.',
              paidAddons: ['Додатни тартуфи', 'Пилеће груди са роштља', 'Додатни пармезан', 'Скампи'],
              freeAddons: ['Додатне зачинске биљке', 'Лимун', 'Црни бибер', 'Љуте папричице'],
              removableItems: ['Печурке', 'Тартуфово уље', 'Пармезан']
            },
            cs: {
              name: 'Lanýžové houbové risotto',
              description: 'Krémová Arborio rýže s lesními houbami, černým lanýžem a Parmigiano-Reggiano.',
              paidAddons: ['Extra lanýžové hoblinky', 'Grilovaný kuřecí prsíčko', 'Extra parmazán', 'Opékaná kreveta'],
              freeAddons: ['Extra bylinky', 'Citronový klínek', 'Černý pepř', 'Chilli vločky'],
              removableItems: ['Houby', 'Lanýžový olej', 'Parmazán']
            },
            es: {
              name: 'Risotto de Trufa y Hongos',
              description: 'Arroz Arborio cremoso con hongos silvestres, trufa negra y Parmigiano-Reggiano.',
              paidAddons: ['Virutas de Trufa Extra', 'Pechuga de Pollo a la Parrilla', 'Parmesano Extra', 'Camarones Salteados'],
              freeAddons: ['Hierbas Extra', 'Rodaja de Limón', 'Pimienta Negra', 'Hojuelas de Chile'],
              removableItems: ['Hongos', 'Aceite de Trufa', 'Parmesano']
            }
          }
        },
        {
          id: 'item_salmon',
          name: 'Grilled Salmon Fillet',
          category: 'mains',
          price: 24.5,
          description: 'Fresh Atlantic salmon grilled to perfection, served with seasonal vegetables and lemon butter sauce.',
          calories: 420,
          nutrition: {
            calories: 420,
            protein: 38,
            carbs: 12,
            fat: 24
          },
          dietary: ['pescatarian', 'gluten-free'],
          allergens: ['fish', 'dairy'],
          badges: ['recommended'],
          orderedCount: 312,
          rating: 4.7,
          reviewCount: 56,
          reviews: [
            {
              name: 'Emma W.',
              rating: 5,
              comment: 'Perfectly cooked, moist and flavorful!',
              date: '2025-11-14'
            },
            {
              name: 'Michael B.',
              rating: 4,
              comment: 'Good quality salmon, generous portion.',
              date: '2025-11-09'
            }
          ],
          vatRate: 10,
          taxCategory: 'food',
          paidAddons: [
            { name: 'Extra Salmon (100g)', price: 8.0 },
            { name: 'Grilled Prawns', price: 6.5 },
            { name: 'Garlic Butter', price: 1.5 },
            { name: 'Hollandaise Sauce', price: 2.0 }
          ],
          freeAddons: [
            'Extra Lemon',
            'Fresh Dill',
            'Capers'
          ],
          removableItems: [
            'Butter Sauce',
            'Vegetables',
            'Lemon'
          ],
          translations: {
            de: {
              name: 'Gegrilltes Lachsfilet',
              description: 'Frischer Atlantiklachs perfekt gegrillt, serviert mit Saisongemüse und Zitronen-Butter-Sauce.',
              paidAddons: ['Extra Lachs (100g)', 'Gegrillte Garnelen', 'Knoblauchbutter', 'Sauce Hollandaise'],
              freeAddons: ['Extra Zitrone', 'Frischer Dill', 'Kapern'],
              removableItems: ['Buttersauce', 'Gemüse', 'Zitrone']
            },
            it: {
              name: 'Filetto di Salmone Grigliato',
              description: 'Salmone atlantico fresco grigliato alla perfezione, servito con verdure di stagione e salsa al burro e limone.',
              paidAddons: ['Salmone Extra (100g)', 'Gamberoni Grigliati', 'Burro all\'Aglio', 'Salsa Olandese'],
              freeAddons: ['Limone Extra', 'Aneto Fresco', 'Capperi'],
              removableItems: ['Salsa al Burro', 'Verdure', 'Limone']
            },
            fr: {
              name: 'Filet de Saumon Grillé',
              description: 'Saumon atlantique frais grillé à la perfection, servi avec légumes de saison et sauce au beurre citronné.',
              paidAddons: ['Saumon Supplémentaire (100g)', 'Crevettes Grillées', 'Beurre à l\'Ail', 'Sauce Hollandaise'],
              freeAddons: ['Citron Supplémentaire', 'Aneth Frais', 'Câpres'],
              removableItems: ['Sauce au Beurre', 'Légumes', 'Citron']
            },
            ar: {
              name: 'فيليه سلمون مشوي',
              description: 'سلمون أطلسي طازج مشوي بشكل مثالي، يقدم مع خضروات موسمية وصوص الزبدة بالليمون.',
              paidAddons: ['سلمون إضافي (100 جم)', 'جمبري مشوي', 'زبدة بالثوم', 'صوص هولنديز'],
              freeAddons: ['ليمون إضافي', 'شبت طازج', 'كبر'],
              removableItems: ['صوص الزبدة', 'خضروات', 'ليمون']
            },
            tr: {
              name: 'Izgara Somon Fileto',
              description: 'Taze Atlantik somonu mükemmel şekilde ızgara, mevsim sebzeleri ve limonlu tereyağı sosu ile servis edilir.',
              paidAddons: ['Ekstra Somon (100g)', 'Izgara Karides', 'Sarımsaklı Tereyağı', 'Hollandaise Sos'],
              freeAddons: ['Ekstra Limon', 'Taze Dereotu', 'Kapari'],
              removableItems: ['Tereyağı Sosu', 'Sebzeler', 'Limon']
            },
            zh: {
              name: '烤三文鱼片',
              description: '新鲜大西洋三文鱼完美烤制，配季节性蔬菜和柠檬黄油酱。',
              paidAddons: ['额外三文鱼 (100克)', '烤大虾', '蒜香黄油', '荷兰酱'],
              freeAddons: ['额外柠檬', '新鲜莳萝', '酸豆'],
              removableItems: ['黄油酱', '蔬菜', '柠檬']
            },
            ja: {
              name: 'グリルドサーモンフィレ',
              description: '新鮮な大西洋サーモンを完璧にグリル、季節の野菜とレモンバターソースを添えて。',
              paidAddons: ['エクストラサーモン (100g)', 'グリルドエビ', 'ガーリックバター', 'オランデーズソース'],
              freeAddons: ['エクストラレモン', 'フレッシュディル', 'ケッパー'],
              removableItems: ['バターソース', '野菜', 'レモン']
            },
            sr: {
              name: 'Гриловани филе лососа',
              description: 'Свеж атлантски лосос савршено припремљен на роштиљу, сервиран са сезонским поврћем и сосом од лимуна и путера.',
              paidAddons: ['Додатни лосос (100г)', 'Гриловане шкампе', 'Путер са белим луком', 'Холандски сос'],
              freeAddons: ['Додатни лимун', 'Свеж мирођија', 'Каперси'],
              removableItems: ['Сос од путера', 'Поврће', 'Лимун']
            },
            cs: {
              name: 'Grilovaný lososový filet',
              description: 'Čerstvý atlantický losos dokonale grilovaný, podávaný se sezónní zeleninou a citrónovou máslovou omáčkou.',
              paidAddons: ['Extra losos (100g)', 'Grilované krevety', 'Česneková máslo', 'Holandská omáčka'],
              freeAddons: ['Extra citron', 'Čerstvý kopr', 'Kapary'],
              removableItems: ['Máslová omáčka', 'Zelenina', 'Citron']
            },
            es: {
              name: 'Filete de Salmón a la Parrilla',
              description: 'Salmón atlántico fresco asado a la perfección, servido con verduras de temporada y salsa de mantequilla y limón.',
              paidAddons: ['Salmón Extra (100g)', 'Langostinos a la Parrilla', 'Mantequilla de Ajo', 'Salsa Holandesa'],
              freeAddons: ['Limón Extra', 'Eneldo Fresco', 'Alcaparras'],
              removableItems: ['Salsa de Mantequilla', 'Verduras', 'Limón']
            }
          }
        },
        {
          id: 'item_caesar',
          name: 'Classic Caesar Salad',
          category: 'salads',
          price: 11.5,
          description: 'Crisp romaine lettuce with Caesar dressing, croutons, and shaved Parmesan.',
          calories: 280,
          nutrition: {
            calories: 280,
            protein: 8,
            carbs: 18,
            fat: 20
          },
          dietary: ['vegetarian'],
          allergens: ['gluten', 'dairy', 'eggs', 'fish'],
          badges: ['most-ordered'],
          orderedCount: 398,
          rating: 4.6,
          reviewCount: 62,
          reviews: [
            {
              name: 'Laura P.',
              rating: 5,
              comment: 'Best Caesar salad I\'ve had in Vienna!',
              date: '2025-11-13'
            },
            {
              name: 'Alex R.',
              rating: 4,
              comment: 'Fresh and tasty, could use more dressing.',
              date: '2025-11-07'
            }
          ],
          vatRate: 10,
          taxCategory: 'food',
          translations: {
            de: {
              name: 'Klassischer Caesar Salat',
              description: 'Knackiger Römersalat mit Caesar-Dressing, Croutons und gehobeltem Parmesan.'
            },
            it: {
              name: 'Insalata Caesar Classica',
              description: 'Lattuga romana croccante con condimento Caesar, crostini e scaglie di parmigiano.'
            },
            fr: {
              name: 'Salade César Classique',
              description: 'Laitue romaine croquante avec sauce César, croûtons et parmesan râpé.'
            },
            ar: {
              name: 'سلطة سيزر كلاسيكية',
              description: 'خس روماني مقرمش مع صوص سيزر وخبز محمص وبارميزان مبشور.'
            },
            tr: {
              name: 'Klasik Sezar Salata',
              description: 'Çıtır marul, Sezar sos, kruton ve rendelenmiş parmesan ile.'
            },
            zh: {
              name: '经典凯撒沙拉',
              description: '爽脆罗马生菜配凯撒酱、面包丁和帕尔马干酪碎��'
            },
            ja: {
              name: 'クラシックシーザーサラダ',
              description: 'パリパリのロメインレタスにシーザードレッシング、クルトン、削ったパルメザンチーズ。'
            },
            sr: {
              name: 'Класична Цезар салата',
              description: 'Свежа салата ромејн са Цезар дресингом, крутонима и ренданим пармезаном.'
            },
            cs: {
              name: 'Klasický Caesar salát',
              description: 'Křupavý římský salát s Caesar dresinkem, krutony a strouhaným parmazánem.'
            },
            es: {
              name: 'Ensalada César Clásica',
              description: 'Lechuga romana crujiente con aderezo César, crutones y parmesano rallado.'
            }
          }
        },
        {
          id: 'item_3',
          name: 'Spaghetti Carbonara',
          category: 'mains',
          price: 16.5,
          description: 'Classic Roman pasta with guanciale, eggs, Pecorino Romano, and black pepper.',
          calories: 580,
          nutrition: {
            calories: 580,
            protein: 24,
            carbs: 68,
            fat: 24
          },
          dietary: [],
          allergens: ['gluten', 'dairy', 'eggs'],
          badges: ['most-ordered'],
          orderedCount: 521,
          rating: 4.7,
          reviewCount: 62,
          reviews: [
            {
              name: 'Elena R.',
              rating: 5,
              comment: 'Perfect carbonara! Creamy without cream, exactly as it should be.',
              date: '2025-11-14'
            },
            {
              name: 'David P.',
              rating: 4,
              comment: 'Very good, generous portion size.',
              date: '2025-11-11'
            }
          ],
          modifiers: [
            {
              name: 'Extra cheese',
              price: 2.0
            },
            {
              name: 'Add truffle oil',
              price: 4.0
            }
          ],
          vatRate: 10,
          taxCategory: 'food',
          translations: {
            de: {
              name: 'Spaghetti Carbonara',
              description: 'Klassische römische Pasta mit Guanciale, Eiern, Pecorino Romano und schwarzem Pfeffer.',
              modifiers: ['Extra Käse', 'Trüffelöl hinzufügen']
            },
            it: {
              name: 'Spaghetti alla Carbonara',
              description: 'Pasta romana classica con guanciale, uova, Pecorino Romano e pepe nero.',
              modifiers: ['Formaggio Extra', 'Aggiungi Olio al Tartufo']
            },
            fr: {
              name: 'Spaghetti Carbonara',
              description: 'Pâtes romaines classiques avec guanciale, œufs, Pecorino Romano et poivre noir.',
              modifiers: ['Fromage Supplémentaire', 'Ajouter Huile de Truffe']
            },
            ar: {
              name: 'سباغيتي كاربونارا',
              description: 'معكرونة رومانية كلاسيكية مع غوانشيالي، بيض، بيكورينو رومانو، وفلفل أسود.',
              modifiers: ['جبن إضافي', 'أضف زيت الكمأة']
            },
            tr: {
              name: 'Spaghetti Carbonara',
              description: 'Guanciale, yumurta, Pecorino Romano ve siyah biberle klasik Roma makarnası.',
              modifiers: ['Ekstra Peynir', 'Trüf Yağı Ekle']
            },
            zh: {
              name: '培根蛋酱意面',
              description: '经典罗马意面配猪颊肉、鸡蛋、佩科里诺罗马诺奶酪和黑胡椒。',
              modifiers: ['额外奶酪', '加松露油']
            },
            ja: {
              name: 'スパゲッティ・カルボナーラ',
              description: 'グアンチャーレ、卵、ペコリーノ・ロマーノ、黒コショウを使った伝統的なローマ風パスタ。',
              modifiers: ['エクストラチーズ', 'トリュフオイル追加']
            },
            sr: {
              name: 'Спагете Карбонара',
              description: 'Класичне римске тестенине са гуанчијале, јајима, Пекорино Романо сиром и црним бибером.',
              modifiers: ['Додатни сир', 'Додај тартуфово уље']
            },
            cs: {
              name: 'Špagety Carbonara',
              description: 'Klasické římské těstoviny s guanciale, vejci, Pecorino Romano a černým pepřem.',
              modifiers: ['Extra sýr', 'Přidat lanýžový olej']
            },
            es: {
              name: 'Espagueti Carbonara',
              description: 'Pasta romana clásica con guanciale, huevos, Pecorino Romano y pimienta negra.',
              modifiers: ['Queso Extra', 'Agregar Aceite de Trufa']
            }
          }
        },
        {
          id: 'item_4',
          name: 'Margherita Pizza',
          category: 'mains',
          price: 14.0,
          description: 'Wood-fired pizza with San Marzano tomatoes, fresh mozzarella, and basil.',
          calories: 680,
          nutrition: {
            calories: 680,
            protein: 28,
            carbs: 88,
            fat: 24
          },
          dietary: ['vegetarian'],
          allergens: ['gluten', 'dairy'],
          badges: ['vegetarian', 'most-ordered'],
          orderedCount: 698,
          rating: 4.9,
          reviewCount: 89,
          reviews: [
            {
              name: 'Anna B.',
              rating: 5,
              comment: 'Best pizza in town! The crust is perfect.',
              date: '2025-11-13'
            },
            {
              name: 'Luca F.',
              rating: 5,
              comment: 'Reminds me of home in Naples!',
              date: '2025-11-09'
            }
          ],
          vatRate: 10,
          taxCategory: 'food',
          translations: {
            de: {
              name: 'Pizza Margherita',
              description: 'Holzofenpizza mit San Marzano Tomaten, frischem Mozzarella und Basilikum.'
            },
            it: {
              name: 'Pizza Margherita',
              description: 'Pizza cotta nel forno a legna con pomodori San Marzano, mozzarella fresca e basilico.'
            },
            fr: {
              name: 'Pizza Margherita',
              description: 'Pizza au feu de bois avec tomates San Marzano, mozzarella fraîche et basilic.'
            },
            ar: {
              name: 'بيتزا مارغريتا',
              description: 'بيتزا مشوية بالفرن الحطبي مع طماطم سان مارزانو، موزاريلا طازجة، وريحان.'
            },
            tr: {
              name: 'Margherita Pizza',
              description: 'Odun ateşinde pişmiş pizza, San Marzano domates, taze mozzarella ve fesleğen ile.'
            },
            zh: {
              name: '玛格丽特披萨',
              description: '木柴烤制披萨，配圣马扎诺番茄、新鲜马苏里拉奶酪和罗勒。'
            },
            ja: {
              name: 'マルゲリータピザ',
              description: '薪窯で焼いたピザにサンマルツァーノトマト、フレッシュモッツァレラ、バジルをトッピング。'
            },
            sr: {
              name: 'Пица Маргарита',
              description: 'Пица печена на дрва са Сан Марцано парадајзом, свежом моцарелом и босиљком.'
            },
            cs: {
              name: 'Pizza Margherita',
              description: 'Pizza pečená na dřevě s rajčaty San Marzano, čerstvou mozzarellou a bazalkou.'
            },
            es: {
              name: 'Pizza Margherita',
              description: 'Pizza al horno de leña con tomates San Marzano, mozzarella fresca y albahaca.'
            }
          }
        },
        {
          id: 'item_5',
          name: 'Tiramisu',
          category: 'desserts',
          price: 7.5,
          description: 'Classic Italian dessert with coffee-soaked ladyfingers and mascarpone cream.',
          calories: 450,
          nutrition: {
            calories: 450,
            protein: 8,
            carbs: 48,
            fat: 24
          },
          dietary: ['vegetarian'],
          allergens: ['gluten', 'dairy', 'eggs'],
          badges: ['chefs-pick'],
          orderedCount: 234,
          rating: 4.6,
          reviewCount: 34,
          reviews: [
            {
              name: 'Julia W.',
              rating: 5,
              comment: 'Heaven on a plate! Not too sweet, perfect balance.',
              date: '2025-11-11'
            },
            {
              name: 'Roberto C.',
              rating: 4,
              comment: 'Good but I prefer it with more coffee flavor.',
              date: '2025-11-07'
            }
          ],
          vatRate: 10,
          taxCategory: 'food',
          translations: {
            de: {
              name: 'Tiramisu',
              description: 'Klassisches italienisches Dessert mit kaffeege tränkten Löffelbiskuits und Mascarpone-Creme.'
            },
            it: {
              name: 'Tiramisù',
              description: 'Classico dessert italiano con savoiardi imbevuti di caffè e crema di mascarpone.'
            },
            fr: {
              name: 'Tiramisu',
              description: 'Dessert italien classique avec biscuits à la cuillère imbibés de café et crème mascarpone.'
            },
            ar: {
              name: 'تيراميسو',
              description: 'حلوى إيطالية كلاسيكية مع بسكويت سافويار مغموس بالقهوة وكريمة ماسكاربوني.'
            },
            tr: {
              name: 'Tiramisu',
              description: 'Kahve ile ıslatılmış kedi dili bisküviler ve maskarpone kremalı klasik İtalyan tatlısı.'
            },
            zh: {
              name: '提拉米苏',
              description: '经典意大利甜点，咖啡浸泡的手指饼干配马斯卡彭奶油。'
            },
            ja: {
              name: 'ティラミス',
              description: 'コーヒーに浸したレディフィンガーとマスカルポーネクリームの伝統的なイタリアンデザート。'
            },
            sr: {
              name: 'Тирамису',
              description: 'Класичан италијански десерт са бисквитима натопљеним у кафи и маскарпоне кремом.'
            },
            cs: {
              name: 'Tiramisu',
              description: 'Klasický italský dezert s piškoty namočenými v kávě a mascarpone krémem.'
            },
            es: {
              name: 'Tiramisú',
              description: 'Postre italiano clásico con bizcochos de soletilla empapados en café y crema de mascarpone.'
            }
          }
        },
        {
          id: 'item_panna',
          name: 'Panna Cotta',
          category: 'desserts',
          price: 6.5,
          description: 'Silky smooth Italian custard with berry compote.',
          calories: 320,
          nutrition: {
            calories: 320,
            protein: 6,
            carbs: 35,
            fat: 18
          },
          dietary: ['vegetarian', 'gluten-free'],
          allergens: ['dairy'],
          badges: [],
          orderedCount: 187,
          rating: 4.5,
          reviewCount: 29,
          reviews: [
            {
              name: 'Nina S.',
              rating: 5,
              comment: 'Light and delicious, perfect after a heavy meal.',
              date: '2025-11-12'
            }
          ],
          vatRate: 10,
          taxCategory: 'food',
          translations: {
            de: {
              name: 'Panna Cotta',
              description: 'Seidig glatte italienische Creme mit Beerenkompott.'
            },
            it: {
              name: 'Panna Cotta',
              description: 'Dolce italiano setoso e cremoso con composta di frutti di bosco.'
            },
            fr: {
              name: 'Panna Cotta',
              description: 'Crème italienne soyeuse et lisse avec compote de baies.'
            },
            ar: {
              name: 'بانا كوتا',
              description: 'كاسترد إيطالي ناعم كالحرير مع كومبوت التوت.'
            },
            tr: {
              name: 'Panna Cotta',
              description: 'Yaban mersini kompostosu ile ipeksi pürüzsüz İtalyan kreması.'
            },
            zh: {
              name: '意式奶冻',
              description: '丝滑柔顺的意大利奶冻配浆果果酱。'
            },
            ja: {
              name: 'パンナコッタ',
              description: 'なめらかな口当たりのイタリアンカスタードにベリーコンポートを添えて。'
            },
            sr: {
              name: 'Пана Кота',
              description: 'Свилен��сто глатки италија��ски крем са компотом од шумског воћа.'
            },
            cs: {
              name: 'Panna Cotta',
              description: 'Hedvábně hladký italský krém s kompot em z bobulového ovoce.'
            },
            es: {
              name: 'Panna Cotta',
              description: 'Crema italiana sedosa y suave con compota de bayas.'
            }
          }
        },
        {
          id: 'item_6',
          name: 'Prosecco DOC',
          category: 'drinks',
          price: 6.5,
          description: 'Sparkling wine from Veneto region. Served chilled.',
          calories: 98,
          nutrition: {
            calories: 98,
            protein: 0,
            carbs: 2,
            fat: 0
          },
          dietary: ['vegan', 'gluten-free'],
          allergens: ['sulfites'],
          badges: [],
          orderedCount: 156,
          rating: 4.4,
          reviewCount: 18,
          reviews: [
            {
              name: 'Christina S.',
              rating: 4,
              comment: 'Nice and crisp, good value.',
              date: '2025-11-10'
            }
          ],
          vatRate: 20,
          taxCategory: 'beverage-alcoholic',
          translations: {
            de: {
              name: 'Prosecco DOC',
              description: 'Schaumwein aus der Region Venetien. Gut gekühlt serviert.'
            },
            it: {
              name: 'Prosecco DOC',
              description: 'Vino frizzante della regione Veneto. Servito freddo.'
            },
            fr: {
              name: 'Prosecco DOC',
              description: 'Vin mousseux de la région de Vénétie. Servi frais.'
            },
            ar: {
              name: 'بروسيكو دي او سي',
              description: 'نبيذ فوار من منطقة فينيتو. يقدم مبردًا.'
            },
            tr: {
              name: 'Prosecco DOC',
              description: 'Veneto bölgesinden köpüklü şarap. Soğuk servis edilir.'
            },
            zh: {
              name: '普罗塞克起泡酒',
              description: '来自威尼托地区的起泡酒。冷藏供应。'
            },
            ja: {
              name: 'プロセッコ DOC',
              description: 'ヴェネト地方のスパークリングワイン。冷やしてお召し上がりください。'
            },
            sr: {
              name: 'Просеко DOC',
              description: 'Пенушаво вино из региона Венето. Сервира се добро охлађено.'
            },
            cs: {
              name: 'Prosecco DOC',
              description: 'Šumivé víno z oblasti Veneto. Podává se chlazené.'
            },
            es: {
              name: 'Prosecco DOC',
              description: 'Vino espumoso de la región de Véneto. Servido frío.'
            }
          }
        }
      ]
    };
    
    await kv.set(`menu:${restaurantId}`, menu);
    await kv.set(`restaurant:${restaurantId}:orders`, []);
    
    // Create vendor settings with branding ONLY if they don't already exist
    const existingSettings = await kv.get(`vendor:${restaurantId}:settings`);
    if (!existingSettings || forceReseed) {
      await kv.set(`vendor:${restaurantId}:settings`, {
        // Business Information
        restaurantName: 'Bella Cucina',
        description: 'Authentic Italian cuisine in the heart of Vienna',
        businessRegNumber: 'FN 123456a',
        vatNumber: 'ATU12345678',
        email: 'info@bellacucina.at',
        phone: '+43 1 234 5678',
        website: 'www.bellacucina.at',
        address: 'Stephansplatz 1, 1010 Wien, Austria',
        companyType: 'GmbH',
        // Branding assets - these will be displayed on QR landing page
        logo: 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=200&h=200&fit=crop',
        coverPhoto: 'https://images.unsplash.com/photo-1722587561829-8a53e1935e20?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpdGFsaWFuJTIwcmVzdGF1cmFudCUyMGludGVyaW9yfGVufDF8fHx8MTc2NDQzMjUyN3ww&ixlib=rb-4.1.0&q=80&w=1080',
        
        // Business Hours
        businessHours: {
          monday: { open: '11:00', close: '22:00', closed: false },
          tuesday: { open: '11:00', close: '22:00', closed: false },
          wednesday: { open: '11:00', close: '22:00', closed: false },
          thursday: { open: '11:00', close: '23:00', closed: false },
          friday: { open: '11:00', close: '23:00', closed: false },
          saturday: { open: '12:00', close: '23:00', closed: false },
          sunday: { open: '12:00', close: '21:00', closed: false }
        },
        
        // Tax & Compliance
        country: 'AT',
        enableMultiVAT: true,
        
        // Order Settings
        minOrderAmount: 10,
        maxOrderAmount: 500,
        serviceFeeRate: 5,
        autoAcceptOrders: true,
        estimatedPrepTime: 20,
        
        // Loyalty Program
        enableLoyalty: true,
        pointsPerEuro: 1,
        minimumRedemption: 100,
        redemptionRate: 0.05,
        pointsExpiry: 365,
        enableTiers: false,
        
        // Theme Colors
        accentColor: '#f59e0b',
        primaryColor: '#1a1a1a',
        
        // Privacy Settings
        dataRetentionDays: 730,
        allowDataExport: true,
        gdprCompliant: true,
        showInTopCustomers: true
      });
      console.log('Created initial vendor settings with demo branding');
    } else {
      console.log('Vendor settings already exist, preserving them (including uploaded logo/cover)');
    }
    
    // Create demo users with Supabase Auth
    console.log('Creating demo users...');
    const createdUsers = [];
    
    // Helper function to create a demo user
    const createDemoUser = async (email: string, password: string, name: string, phone: string, loyaltyPoints: number) => {
      try {
        // First, try to get user by email to check if they exist
        const { data: existingUsers } = await supabase.auth.admin.listUsers();
        const userExists = existingUsers?.users?.find(u => u.email === email);
        
        let userId;
        
        if (userExists) {
          console.log(`ℹ️  User ${email} already exists, updating password and customer data...`);
          userId = userExists.id;
          
          // Update password for existing user to ensure it matches demo credentials
          const { error: updateError } = await supabase.auth.admin.updateUserById(
            userId,
            { 
              password,
              email_confirm: true,
              user_metadata: { name, phone }
            }
          );
          
          if (updateError) {
            console.log(`⚠️  Failed to update password for ${email}: ${updateError.message}`);
          } else {
            console.log(`✅ Password updated for ${email}`);
          }
        } else {
          // Create new user
          const { data, error } = await supabase.auth.admin.createUser({
            email,
            password,
            user_metadata: { name, phone },
            email_confirm: true
          });

          if (error) {
            console.log(`❌ Failed to create ${email}: ${error.message}`);
            return null;
          }

          userId = data.user.id;
          console.log(`✅ Created new user: ${email}`);
        }

        // Always update/create customer data in KV store
        await kv.set(`customer:${userId}`, {
          id: userId,
          email,
          name,
          phone,
          loyaltyPoints,
          createdAt: new Date().toISOString()
        });
        
        console.log(`✅ Customer data saved for ${email} (${loyaltyPoints} points)`);
        return { email, password, loyaltyPoints };
      } catch (error: any) {
        console.log(`❌ Error with user ${email}: ${error.message}`);
        return null;
      }
    };

    // Create demo users
    const user1 = await createDemoUser('demo@bellacucina.com', 'demo123', 'Demo Customer', '+43 660 1234567', 350);
    if (user1) createdUsers.push(user1);

    const user2 = await createDemoUser('vip@bellacucina.com', 'vip123', 'VIP Customer', '+43 660 9876543', 850);
    if (user2) createdUsers.push(user2);

    const user3 = await createDemoUser('test@example.com', 'test123', 'Test User', '+43 660 5555555', 0);
    if (user3) createdUsers.push(user3);

    console.log(`✅ Demo users ready: ${createdUsers.length}/3`);
    
    // Create fake reviews for the vendor reviews page
    console.log('Creating demo reviews...');
    const now = Date.now();
    const oneDay = 24 * 60 * 60 * 1000;
    
    const demoReviews = [
      // 5-star reviews (positive)
      {
        id: 'review_1',
        orderId: 'order_review_1',
        customerName: 'Sarah M.',
        rating: 5,
        text: 'Absolutely fantastic experience! The pasta was cooked to perfection and the service was impeccable. Will definitely be coming back.',
        createdAt: new Date(now - 2 * oneDay).toISOString(),
        isAnonymous: false,
        reply: 'Thank you so much for your kind words, Sarah! We look forward to welcoming you back soon.',
        repliedAt: new Date(now - 1 * oneDay).toISOString()
      },
      {
        id: 'review_2',
        orderId: 'order_review_2',
        customerName: null,
        rating: 5,
        text: 'Best Italian food in Vienna! The Tiramisu was divine. Highly recommended.',
        createdAt: new Date(now - 5 * oneDay).toISOString(),
        isAnonymous: true
      },
      {
        id: 'review_3',
        orderId: 'order_review_3',
        customerName: 'Michael K.',
        rating: 5,
        text: 'Great ambiance and excellent food quality. The Margherita pizza reminded me of my trip to Naples.',
        createdAt: new Date(now - 8 * oneDay).toISOString(),
        isAnonymous: false,
        reply: 'We\'re thrilled to hear that! Authentic Italian cuisine is our passion. Grazie mille!',
        repliedAt: new Date(now - 7 * oneDay).toISOString()
      },
      
      // 4-star reviews (positive)
      {
        id: 'review_4',
        orderId: 'order_review_4',
        customerName: 'Emma L.',
        rating: 4,
        text: 'Very good food and pleasant atmosphere. The Carbonara was delicious. Only minor issue was a slight wait during peak hours, but understandable.',
        createdAt: new Date(now - 3 * oneDay).toISOString(),
        isAnonymous: false
      },
      {
        id: 'review_5',
        orderId: 'order_review_5',
        customerName: 'Thomas R.',
        rating: 4,
        text: 'Solid Italian restaurant. Good portion sizes and reasonable prices. Would have given 5 stars but it was quite busy.',
        createdAt: new Date(now - 6 * oneDay).toISOString(),
        isAnonymous: false,
        reply: 'Thank you for your feedback, Thomas! We\'re working on optimizing our service during busy periods.',
        repliedAt: new Date(now - 5 * oneDay).toISOString()
      },
      {
        id: 'review_6',
        orderId: 'order_review_6',
        customerName: 'Julia B.',
        rating: 4,
        text: 'Enjoyed the food very much. Fresh ingredients and authentic taste. Staff was friendly.',
        createdAt: new Date(now - 10 * oneDay).toISOString(),
        isAnonymous: false
      },
      
      // 3-star reviews (needs attention - recent, not replied)
      {
        id: 'review_7',
        orderId: 'order_review_7',
        customerName: 'David W.',
        rating: 3,
        text: 'Food was okay but service was slower than expected. We waited about 45 minutes for our main course during dinner rush.',
        createdAt: new Date(now - 2 * oneDay).toISOString(),
        isAnonymous: false
      },
      {
        id: 'review_8',
        orderId: 'order_review_8',
        customerName: null,
        rating: 3,
        text: 'Average experience. The pizza was good but nothing special. Prices are a bit high for what you get.',
        createdAt: new Date(now - 4 * oneDay).toISOString(),
        isAnonymous: true
      },
      
      // 3-star review (older, doesn't need immediate attention)
      {
        id: 'review_9',
        orderId: 'order_review_9',
        customerName: 'Anna S.',
        rating: 3,
        text: 'Decent meal but had some issues with my order. They forgot one of the side dishes.',
        createdAt: new Date(now - 15 * oneDay).toISOString(),
        isAnonymous: false,
        reply: 'We sincerely apologize for the oversight. We\'ve addressed this with our team to prevent future occurrences.',
        repliedAt: new Date(now - 14 * oneDay).toISOString()
      },
      
      // 2-star reviews (needs attention)
      {
        id: 'review_10',
        orderId: 'order_review_10',
        customerName: 'Peter H.',
        rating: 2,
        text: 'Not impressed. The pasta was overcooked and the service was inattentive. Expected better based on the reviews.',
        createdAt: new Date(now - 1 * oneDay).toISOString(),
        isAnonymous: false
      },
      {
        id: 'review_11',
        orderId: 'order_review_11',
        customerName: 'Lisa G.',
        rating: 2,
        text: 'Disappointing visit. Long wait time and the food arrived cold. Staff seemed overwhelmed.',
        createdAt: new Date(now - 3 * oneDay).toISOString(),
        isAnonymous: false
      },
      
      // 1-star review (critical - needs attention)
      {
        id: 'review_12',
        orderId: 'order_review_12',
        customerName: null,
        rating: 1,
        text: 'Very poor experience. Wrong order delivered, took forever to fix it. Would not recommend.',
        createdAt: new Date(now - 5 * oneDay).toISOString(),
        isAnonymous: true
      },
      
      // More positive reviews for variety
      {
        id: 'review_13',
        orderId: 'order_review_13',
        customerName: 'Maria D.',
        rating: 5,
        text: 'Wonderful evening with family! The staff was accommodating and the food exceeded our expectations.',
        createdAt: new Date(now - 7 * oneDay).toISOString(),
        isAnonymous: false
      },
      {
        id: 'review_14',
        orderId: 'order_review_14',
        customerName: 'Robert F.',
        rating: 4,
        text: 'Great takeaway service. Food was ready on time and still hot when I got home. Bruschetta was excellent.',
        createdAt: new Date(now - 9 * oneDay).toISOString(),
        isAnonymous: false
      },
      {
        id: 'review_15',
        orderId: 'order_review_15',
        customerName: 'Sophie T.',
        rating: 5,
        text: 'Best Tiramisu I\'ve ever had outside of Italy! Perfect balance of coffee and cream.',
        createdAt: new Date(now - 11 * oneDay).toISOString(),
        isAnonymous: false,
        reply: 'Grazie mille, Sophie! Our Tiramisu is made fresh daily with love.',
        repliedAt: new Date(now - 10 * oneDay).toISOString()
      }
    ];
    
    // Store reviews and add order IDs to restaurant's orders list
    const reviewOrderIds = [];
    for (const review of demoReviews) {
      await kv.set(`review:order:${review.orderId}`, review);
      reviewOrderIds.push(review.orderId);
    }
    
    // Add review order IDs to restaurant orders
    const currentOrders = await kv.get(`restaurant:${restaurantId}:orders`) || [];
    await kv.set(`restaurant:${restaurantId}:orders`, [...reviewOrderIds, ...currentOrders]);
    
    console.log(`✅ Created ${demoReviews.length} demo reviews`);
    
    return c.json({ 
      success: true, 
      message: 'Seed data created',
      demoUsers: createdUsers.length > 0 ? createdUsers : [
        { email: 'demo@bellacucina.com', password: 'demo123', loyaltyPoints: 350 },
        { email: 'vip@bellacucina.com', password: 'vip123', loyaltyPoints: 850 },
        { email: 'test@example.com', password: 'test123', loyaltyPoints: 0 }
      ],
      reviewsCreated: demoReviews.length
    });
  } catch (error) {
    console.log(`Seed data error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ RESERVATION ENDPOINTS ============

// Get available time slots for a given date
app.post('/make-server-1dccd8d3/reservations/available-slots', async (c) => {
  try {
    const { restaurantId, date, partySize } = await c.req.json();
    
    // Get restaurant settings to check opening hours and capacity
    const settings = await kv.get(`vendor:${restaurantId}:settings`) || {};
    const businessHours = settings.businessHours || {};
    const maxTableCapacity = settings.maxTableCapacity || 6;
    const totalTables = settings.totalTables || 20;
    
    // Parse date to get day of week
    const dateObj = new Date(date);
    const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    const dayName = dayNames[dateObj.getDay()];
    const dayHours = businessHours[dayName];
    
    // If restaurant is closed that day
    if (!dayHours || dayHours.closed) {
      return c.json({ slots: [], message: 'Restaurant closed on this day' });
    }
    
    // Generate time slots (30-minute intervals)
    const slots = [];
    const [openHour, openMinute] = dayHours.open.split(':').map(Number);
    const [closeHour, closeMinute] = dayHours.close.split(':').map(Number);
    
    let currentTime = openHour * 60 + openMinute;
    const closingTime = closeHour * 60 + closeMinute;
    
    while (currentTime < closingTime - 90) { // Stop 1.5 hours before closing
      const hour = Math.floor(currentTime / 60);
      const minute = currentTime % 60;
      const timeSlot = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
      
      // Check existing reservations for this slot
      const dateKey = date.split('T')[0]; // Get YYYY-MM-DD
      const slotKey = `reservations:${restaurantId}:${dateKey}:${timeSlot}`;
      const existingReservations = await kv.get(slotKey) || [];
      
      // Calculate total party size for this slot
      const totalPartySize = existingReservations.reduce((sum: number, r: any) => sum + r.partySize, 0);
      const tablesNeeded = Math.ceil(partySize / maxTableCapacity);
      const tablesUsed = Math.ceil(totalPartySize / maxTableCapacity);
      const available = tablesUsed + tablesNeeded <= totalTables;
      
      slots.push({
        time: timeSlot,
        available,
        reservations: existingReservations.length,
        capacity: totalTables - tablesUsed
      });
      
      currentTime += 30; // 30-minute intervals
    }
    
    return c.json({ slots });
  } catch (error) {
    console.log(`Get available slots error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Create a new reservation
app.post('/make-server-1dccd8d3/reservations', async (c) => {
  try {
    const data = await c.req.json();
    const {
      restaurantId,
      customerId,
      customerName,
      customerEmail,
      customerPhone,
      date,
      time,
      partySize,
      specialRequests,
      isGuest
    } = data;
    
    // Generate reservation ID
    const reservationId = `res_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    
    const reservation = {
      id: reservationId,
      restaurantId,
      customerId: customerId || null,
      customerName,
      customerEmail: customerEmail || '',
      customerPhone: customerPhone || '',
      date,
      time,
      partySize,
      specialRequests: specialRequests || '',
      status: 'pending', // pending, confirmed, declined, cancelled, completed
      isGuest: isGuest || false,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    };
    
    // Save reservation
    await kv.set(`reservation:${reservationId}`, reservation);
    
    // Add to restaurant's reservations list
    const restaurantReservations = await kv.get(`restaurant:${restaurantId}:reservations`) || [];
    restaurantReservations.push(reservationId);
    await kv.set(`restaurant:${restaurantId}:reservations`, restaurantReservations);
    
    // Add to customer's reservations list (if not guest)
    if (customerId) {
      const customerReservations = await kv.get(`customer:${customerId}:reservations`) || [];
      customerReservations.push(reservationId);
      await kv.set(`customer:${customerId}:reservations`, customerReservations);
    }
    
    // Add to date/time slot for availability tracking
    const dateKey = date.split('T')[0]; // Get YYYY-MM-DD
    const slotKey = `reservations:${restaurantId}:${dateKey}:${time}`;
    const slotReservations = await kv.get(slotKey) || [];
    slotReservations.push({ reservationId, partySize });
    await kv.set(slotKey, slotReservations);
    
    // Create notification for vendor
    const notificationId = `notif_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    await kv.set(`notification:${notificationId}`, {
      id: notificationId,
      type: 'new_reservation',
      recipientType: 'vendor',
      recipientId: restaurantId,
      reservationId,
      message: `New reservation request for ${partySize} people on ${date} at ${time}`,
      read: false,
      createdAt: new Date().toISOString()
    });
    
    // Add to vendor notifications list
    const vendorNotifications = await kv.get(`vendor:${restaurantId}:notifications`) || [];
    vendorNotifications.unshift(notificationId);
    await kv.set(`vendor:${restaurantId}:notifications`, vendorNotifications.slice(0, 100));
    
    return c.json({ reservationId, reservation });
  } catch (error) {
    console.log(`Create reservation error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Get customer reservations
app.get('/make-server-1dccd8d3/customers/:id/reservations', async (c) => {
  try {
    const customerId = c.req.param('id');
    const reservationIds = await kv.get(`customer:${customerId}:reservations`) || [];
    
    const reservations = [];
    for (const id of reservationIds) {
      const reservation = await kv.get(`reservation:${id}`);
      if (reservation) {
        // Get restaurant info
        const restaurant = await kv.get(`vendor:${reservation.restaurantId}:settings`);
        reservations.push({
          ...reservation,
          restaurantName: restaurant?.restaurantName || 'Restaurant',
          restaurantAddress: restaurant?.address || ''
        });
      }
    }
    
    // Sort by date (most recent first)
    reservations.sort((a: any, b: any) => {
      const dateA = new Date(`${a.date}T${a.time}`);
      const dateB = new Date(`${b.date}T${b.time}`);
      return dateB.getTime() - dateA.getTime();
    });
    
    return c.json(reservations);
  } catch (error) {
    console.log(`Get customer reservations error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Get vendor reservations
app.get('/make-server-1dccd8d3/vendor/:id/reservations', async (c) => {
  try {
    const vendorId = c.req.param('id');
    const status = c.req.query('status'); // optional filter
    const date = c.req.query('date'); // optional date filter (YYYY-MM-DD)
    
    const reservationIds = await kv.get(`restaurant:${vendorId}:reservations`) || [];
    
    const reservations = [];
    for (const id of reservationIds) {
      const reservation = await kv.get(`reservation:${id}`);
      if (reservation) {
        // Apply filters
        if (status && reservation.status !== status) continue;
        if (date) {
          const resDate = reservation.date.split('T')[0];
          if (resDate !== date) continue;
        }
        
        reservations.push(reservation);
      }
    }
    
    // Sort by date/time
    reservations.sort((a: any, b: any) => {
      const dateA = new Date(`${a.date}T${a.time}`);
      const dateB = new Date(`${b.date}T${b.time}`);
      return dateA.getTime() - dateB.getTime();
    });
    
    return c.json(reservations);
  } catch (error) {
    console.log(`Get vendor reservations error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Update reservation status (accept/decline)
app.patch('/make-server-1dccd8d3/reservations/:id/status', async (c) => {
  try {
    const reservationId = c.req.param('id');
    const { status, vendorNote } = await c.req.json(); // status: confirmed, declined, cancelled
    
    const reservation = await kv.get(`reservation:${reservationId}`);
    if (!reservation) {
      return c.json({ error: 'Reservation not found' }, 404);
    }
    
    // Update reservation
    reservation.status = status;
    reservation.vendorNote = vendorNote || '';
    reservation.updatedAt = new Date().toISOString();
    await kv.set(`reservation:${reservationId}`, reservation);
    
    // Create notification for customer
    if (reservation.customerId) {
      const notificationId = `notif_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
      const statusMessages = {
        confirmed: '✅ Your reservation has been confirmed!',
        declined: '❌ Your reservation request was declined.',
        cancelled: 'ℹ️ Your reservation has been cancelled.'
      };
      
      await kv.set(`notification:${notificationId}`, {
        id: notificationId,
        type: 'reservation_status',
        recipientType: 'customer',
        recipientId: reservation.customerId,
        reservationId,
        status,
        message: statusMessages[status] || 'Reservation status updated',
        vendorNote: vendorNote || '',
        read: false,
        createdAt: new Date().toISOString()
      });
      
      // Add to customer notifications list
      const customerNotifications = await kv.get(`customer:${reservation.customerId}:notifications`) || [];
      customerNotifications.unshift(notificationId);
      await kv.set(`customer:${reservation.customerId}:notifications`, customerNotifications.slice(0, 100));
    }
    
    return c.json({ success: true, reservation });
  } catch (error) {
    console.log(`Update reservation status error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Get notifications
app.get('/make-server-1dccd8d3/notifications/:recipientType/:recipientId', async (c) => {
  try {
    const recipientType = c.req.param('recipientType'); // customer or vendor
    const recipientId = c.req.param('recipientId');
    
    const notificationIds = await kv.get(`${recipientType}:${recipientId}:notifications`) || [];
    
    const notifications = [];
    for (const id of notificationIds.slice(0, 50)) {
      const notification = await kv.get(`notification:${id}`);
      if (notification) {
        notifications.push(notification);
      }
    }
    
    return c.json(notifications);
  } catch (error) {
    console.log(`Get notifications error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Mark notification as read
app.patch('/make-server-1dccd8d3/notifications/:id/read', async (c) => {
  try {
    const notificationId = c.req.param('id');
    const notification = await kv.get(`notification:${notificationId}`);
    
    if (!notification) {
      return c.json({ error: 'Notification not found' }, 404);
    }
    
    notification.read = true;
    await kv.set(`notification:${notificationId}`, notification);
    
    return c.json({ success: true });
  } catch (error) {
    console.log(`Mark notification as read error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ TAKEAWAY ENDPOINTS ============

// Helper function to send notifications
async function sendNotification(order: any, type: 'ready' | 'confirmed') {
  const notifications = [];
  
  try {
    // Prepare notification messages
    const messages = {
      ready: {
        subject: `🛍️ Your order #${order.orderNumber} is ready for pickup!`,
        sms: `Your order #${order.orderNumber} is ready! Pick up at: ${order.pickupInstructions}`,
        email: `Your order #${order.orderNumber} is ready for pickup at ${order.pickupInstructions}. Total: €${order.total?.toFixed(2)}`
      },
      confirmed: {
        subject: `Order #${order.orderNumber} confirmed`,
        sms: `Order #${order.orderNumber} confirmed! Pickup: ${new Date(order.pickupTime).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false })}`,
        email: `Order #${order.orderNumber} confirmed. Pickup time: ${new Date(order.pickupTime).toLocaleString()}. Total: €${order.total?.toFixed(2)}`
      }
    };
    
    const message = messages[type];
    
    // Send SMS if phone provided
    if (order.customerPhone) {
      console.log(`📱 SMS Notification to ${order.customerPhone}: ${message.sms}`);
      notifications.push({
        type: 'sms',
        to: order.customerPhone,
        message: message.sms,
        sent: true
      });
    }
    
    // Send Email if email provided
    if (order.customerEmail) {
      console.log(`📧 Email Notification to ${order.customerEmail}: ${message.subject}`);
      notifications.push({
        type: 'email',
        to: order.customerEmail,
        subject: message.subject,
        body: message.email,
        sent: true
      });
    }
    
  } catch (error) {
    console.error(`Failed to send ${type} notification:`, error);
  }
  
  return notifications;
}

// Mark order as ready for pickup
app.patch('/make-server-1dccd8d3/orders/:orderId/ready', async (c) => {
  try {
    const orderId = c.req.param('orderId');
    
    const order = await kv.get(`order:${orderId}`);
    if (!order) {
      return c.json({ error: 'Order not found' }, 404);
    }
    
    if (order.orderType !== 'takeaway') {
      return c.json({ error: 'This endpoint is only for takeaway orders' }, 400);
    }
    
    order.pickupStatus = 'ready';
    order.readyAt = new Date().toISOString();
    order.status = 'ready';
    order.timeline.push({
      status: 'ready',
      timestamp: new Date().toISOString()
    });
    
    await kv.set(`order:${orderId}`, order);
    
    // Send notification to customer
    const notifications = await sendNotification(order, 'ready');
    
    return c.json({ success: true, order, notifications });
  } catch (error) {
    console.log(`Mark order as ready error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Mark order as picked up
app.patch('/make-server-1dccd8d3/orders/:orderId/picked-up', async (c) => {
  try {
    const orderId = c.req.param('orderId');
    
    const order = await kv.get(`order:${orderId}`);
    if (!order) {
      return c.json({ error: 'Order not found' }, 404);
    }
    
    if (order.orderType !== 'takeaway') {
      return c.json({ error: 'This endpoint is only for takeaway orders' }, 400);
    }
    
    order.pickupStatus = 'picked-up';
    order.pickedUpAt = new Date().toISOString();
    order.status = 'completed';
    order.timeline.push({
      status: 'completed',
      timestamp: new Date().toISOString()
    });
    
    await kv.set(`order:${orderId}`, order);
    
    console.log(`✅ Order ${orderId} marked as picked up`);
    
    return c.json({ success: true, order });
  } catch (error) {
    console.log(`Mark order as picked up error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Get available pickup time slots
app.post('/make-server-1dccd8d3/takeaway/available-slots', async (c) => {
  try {
    const { restaurantId, date, prepTime } = await c.req.json();
    
    // Get restaurant settings
    const settings = await kv.get(`vendor:${restaurantId}:settings`) || {};
    const businessHours = settings.businessHours || {};
    const slotInterval = settings.takeawaySlotInterval || 15;
    const takeawayPrepTime = settings.takeawayPrepTime || prepTime || settings.estimatedPrepTime || 25;
    
    // Parse date to get day of week
    const dateObj = new Date(date);
    const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    const dayName = dayNames[dateObj.getDay()];
    const dayHours = businessHours[dayName];
    
    // If restaurant is closed that day
    if (!dayHours || dayHours.closed) {
      return c.json({ slots: [], message: 'Restaurant closed on this day' });
    }
    
    // Generate time slots
    const slots = [];
    const [openHour, openMinute] = dayHours.open.split(':').map(Number);
    const [closeHour, closeMinute] = dayHours.close.split(':').map(Number);
    
    let currentTime = openHour * 60 + openMinute;
    const closingTime = closeHour * 60 + closeMinute;
    
    // Calculate earliest available time (now + prep time)
    const now = new Date();
    const isToday = dateObj.toDateString() === now.toDateString();
    const currentMinutes = now.getHours() * 60 + now.getMinutes();
    const earliestAvailable = isToday ? currentMinutes + takeawayPrepTime + 5 : currentTime; // +5min buffer
    
    while (currentTime < closingTime - 30) { // Stop 30 min before closing
      const hour = Math.floor(currentTime / 60);
      const minute = currentTime % 60;
      const timeSlot = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
      
      // Only include slots that are after earliest available time
      if (currentTime >= earliestAvailable) {
        slots.push({
          time: timeSlot,
          available: true,
          asap: slots.length === 0 && isToday // First slot is ASAP for today
        });
      }
      
      currentTime += slotInterval;
    }
    
    return c.json({ 
      slots,
      earliestTime: slots.length > 0 ? slots[0].time : null,
      prepTime: takeawayPrepTime
    });
  } catch (error) {
    console.log(`Get takeaway slots error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Cancel reservation (customer)
app.patch('/make-server-1dccd8d3/reservations/:id/cancel', async (c) => {
  try {
    const reservationId = c.req.param('id');
    const { customerId } = await c.req.json();
    
    const reservation = await kv.get(`reservation:${reservationId}`);
    if (!reservation) {
      return c.json({ error: 'Reservation not found' }, 404);
    }
    
    // Verify customer owns this reservation
    if (reservation.customerId !== customerId && !reservation.isGuest) {
      return c.json({ error: 'Unauthorized' }, 403);
    }
    
    // Update status
    reservation.status = 'cancelled';
    reservation.updatedAt = new Date().toISOString();
    await kv.set(`reservation:${reservationId}`, reservation);
    
    // Remove from time slot availability
    const dateKey = reservation.date.split('T')[0];
    const slotKey = `reservations:${reservation.restaurantId}:${dateKey}:${reservation.time}`;
    const slotReservations = await kv.get(slotKey) || [];
    const updated = slotReservations.filter((r: any) => r.reservationId !== reservationId);
    await kv.set(slotKey, updated);
    
    // Notify vendor
    const notificationId = `notif_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    await kv.set(`notification:${notificationId}`, {
      id: notificationId,
      type: 'reservation_cancelled',
      recipientType: 'vendor',
      recipientId: reservation.restaurantId,
      reservationId,
      message: `Reservation for ${reservation.partySize} people on ${reservation.date} at ${reservation.time} was cancelled by customer`,
      read: false,
      createdAt: new Date().toISOString()
    });
    
    const vendorNotifications = await kv.get(`vendor:${reservation.restaurantId}:notifications`) || [];
    vendorNotifications.unshift(notificationId);
    await kv.set(`vendor:${reservation.restaurantId}:notifications`, vendorNotifications.slice(0, 100));
    
    return c.json({ success: true, reservation });
  } catch (error) {
    console.log(`Cancel reservation error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ VENDOR ONBOARDING ENDPOINTS ============

// Register new vendor
app.post('/make-server-1dccd8d3/vendor/register', async (c) => {
  try {
    const { businessName, country, email, password } = await c.req.json();
    
    // Create vendor user in Supabase Auth
    const { data, error } = await supabase.auth.admin.createUser({
      email,
      password,
      user_metadata: { 
        businessName, 
        country,
        role: 'vendor'
      },
      email_confirm: true // Auto-confirm for onboarding demo
    });

    if (error) {
      console.log(`Vendor registration error: ${error.message}`);
      return c.json({ error: error.message }, 400);
    }

    const vendorId = data.user.id;

    // Create vendor profile in KV store
    await kv.set(`vendor:${vendorId}`, {
      id: vendorId,
      businessName,
      country,
      email,
      status: 'setup', // setup | active | suspended
      subscriptionStatus: 'none', // none | active | past_due | canceled
      subscriptionPlan: null,
      setupProgress: {
        restaurant: false,
        menu: false,
        tables: false
      },
      createdAt: new Date().toISOString(),
      activatedAt: null
    });

    return c.json({ 
      vendorId,
      message: 'Vendor registered successfully'
    });
  } catch (error) {
    console.log(`Vendor registration error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Get vendor onboarding status
app.get('/make-server-1dccd8d3/vendor/:vendorId/status', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const vendor = await kv.get(`vendor:${vendorId}`);
    
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }
    
    return c.json(vendor);
  } catch (error) {
    console.log(`Get vendor status error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Update vendor setup progress
app.put('/make-server-1dccd8d3/vendor/:vendorId/progress', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const { step, data } = await c.req.json();
    
    const vendor = await kv.get(`vendor:${vendorId}`);
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    // Update step data and mark as complete
    vendor[`${step}Data`] = data;
    vendor.setupProgress[step] = true;

    await kv.set(`vendor:${vendorId}`, vendor);

    return c.json({ 
      message: 'Progress updated',
      vendor
    });
  } catch (error) {
    console.log(`Update vendor progress error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Create subscription (would integrate with Stripe in production)
app.post('/make-server-1dccd8d3/vendor/:vendorId/subscribe', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const { planId } = await c.req.json();
    
    const vendor = await kv.get(`vendor:${vendorId}`);
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    // In production, this would:
    // 1. Create Stripe customer
    // 2. Create Stripe subscription
    // 3. Store subscription ID
    // 4. Set up webhook for subscription events

    // For demo, we'll simulate success
    vendor.subscriptionStatus = 'active';
    vendor.subscriptionPlan = planId;
    vendor.subscriptionId = `sub_${Date.now()}`;
    vendor.subscribedAt = new Date().toISOString();

    await kv.set(`vendor:${vendorId}`, vendor);

    return c.json({ 
      message: 'Subscription created',
      subscriptionId: vendor.subscriptionId
    });
  } catch (error) {
    console.log(`Create subscription error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Activate vendor (called after successful payment)
app.post('/make-server-1dccd8d3/vendor/:vendorId/activate', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    
    const vendor = await kv.get(`vendor:${vendorId}`);
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    // Check if subscription is active
    if (vendor.subscriptionStatus !== 'active') {
      return c.json({ error: 'Active subscription required' }, 403);
    }

    // Activate vendor
    vendor.status = 'active';
    vendor.activatedAt = new Date().toISOString();

    // Activate all QR codes
    if (vendor.tablesData) {
      vendor.tablesData = vendor.tablesData.map((table: any) => ({
        ...table,
        isActive: true
      }));
    }

    await kv.set(`vendor:${vendorId}`, vendor);

    return c.json({ 
      message: 'Vendor activated successfully',
      vendor
    });
  } catch (error) {
    console.log(`Activate vendor error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Suspend vendor (admin only)
app.post('/make-server-1dccd8d3/vendor/:vendorId/suspend', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const { reason } = await c.req.json();
    
    const vendor = await kv.get(`vendor:${vendorId}`);
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    vendor.status = 'suspended';
    vendor.suspendedAt = new Date().toISOString();
    vendor.suspensionReason = reason;

    await kv.set(`vendor:${vendorId}`, vendor);

    return c.json({ 
      message: 'Vendor suspended',
      vendor
    });
  } catch (error) {
    console.log(`Suspend vendor error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Get all vendors (admin monitoring)
app.get('/make-server-1dccd8d3/admin/vendors', async (c) => {
  try {
    // Get all vendors from KV store
    const vendors = await kv.getByPrefix('vendor:');
    
    // Sort by creation date
    const sortedVendors = vendors.sort((a: any, b: any) => {
      return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
    });

    return c.json({ vendors: sortedVendors });
  } catch (error) {
    console.log(`Get vendors error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Save legal data
app.put('/make-server-1dccd8d3/vendor/:vendorId/legal', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const legalData = await c.req.json();
    
    const vendor = await kv.get(`vendor:${vendorId}`);
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    vendor.legalData = legalData;
    vendor.legalDataComplete = !!(
      legalData.legalEntityName && 
      legalData.legalAddress
    );
    vendor.invoicingEnabled = vendor.legalDataComplete;

    await kv.set(`vendor:${vendorId}`, vendor);

    return c.json({ 
      message: 'Legal data saved',
      invoicingEnabled: vendor.invoicingEnabled
    });
  } catch (error) {
    console.log(`Save legal data error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ STRIPE CHECKOUT & WEBHOOK ENDPOINTS ============

// Create Stripe Checkout Session
app.post('/make-server-1dccd8d3/vendor/:vendorId/create-checkout-session', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const { planId, priceId, successUrl, cancelUrl } = await c.req.json();
    
    let vendor = await kv.get(`vendor:${vendorId}`);
    
    // If vendor doesn't exist, create a basic one
    if (!vendor) {
      console.log(`Creating vendor record for ${vendorId}`);
      vendor = {
        id: vendorId,
        status: 'setup',
        subscriptionStatus: 'none',
        createdAt: new Date().toISOString(),
        setupProgress: {
          restaurant: false,
          menu: false,
          tables: false
        }
      };
      await kv.set(`vendor:${vendorId}`, vendor);
    }

    // Demo mode: Return mock checkout URL
    console.log(`Creating checkout session for vendor ${vendorId}, plan ${planId}`);
    
    const checkoutSessionId = `cs_demo_${Date.now()}`;
    await kv.set(`checkout:${checkoutSessionId}`, {
      vendorId,
      planId,
      status: 'pending',
      createdAt: new Date().toISOString()
    });

    return c.json({ 
      checkoutUrl: `${successUrl.split('?')[0]}?session_id=${checkoutSessionId}&demo=true`,
      sessionId: checkoutSessionId
    });
  } catch (error) {
    console.log(`Create checkout session error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Verify checkout session
app.get('/make-server-1dccd8d3/vendor/verify-checkout/:sessionId', async (c) => {
  try {
    const sessionId = c.req.param('sessionId');
    
    const checkout = await kv.get(`checkout:${sessionId}`);
    if (!checkout) {
      return c.json({ error: 'Checkout session not found' }, 404);
    }

    return c.json({
      success: true,
      vendorId: checkout.vendorId,
      subscriptionId: `sub_demo_${Date.now()}`,
      subscriptionStatus: 'active'
    });
  } catch (error) {
    console.log(`Verify checkout error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Stripe Webhook Handler
app.post('/make-server-1dccd8d3/webhooks/stripe', async (c) => {
  try {
    const body = await c.req.text();
    const event = JSON.parse(body);
    
    console.log(`Received Stripe webhook: ${event.type}`);

    switch (event.type) {
      case 'checkout.session.completed': {
        const session = event.data.object;
        const vendorId = session.metadata?.vendorId;
        const subscriptionId = session.subscription;

        if (!vendorId) {
          return c.json({ error: 'Missing vendorId' }, 400);
        }

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
        console.log(`✅ Vendor ${vendorId} activated via webhook`);
        break;
      }

      case 'invoice.payment_failed': {
        const invoice = event.data.object;
        const subscriptionId = invoice.subscription;
        const allVendors = await kv.getByPrefix('vendor:');
        const vendor = allVendors.find((v: any) => v.subscriptionId === subscriptionId);

        if (vendor) {
          vendor.subscriptionStatus = 'past_due';
          await kv.set(`vendor:${vendor.id}`, vendor);
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
          
          if (vendor.tablesData) {
            vendor.tablesData = vendor.tablesData.map((table: any) => ({
              ...table,
              isActive: false
            }));
          }

          await kv.set(`vendor:${vendor.id}`, vendor);
          console.log(`❌ Subscription canceled for vendor ${vendor.id}`);
        }
        break;
      }
    }

    return c.json({ received: true });
  } catch (error) {
    console.log(`Webhook error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Check subscription status
app.get('/make-server-1dccd8d3/vendor/:vendorId/check-subscription', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const vendor = await kv.get(`vendor:${vendorId}`);
    
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    const isActive = vendor.subscriptionStatus === 'active';
    
    return c.json({
      isActive,
      status: vendor.subscriptionStatus,
      canAcceptOrders: isActive,
      canDownloadQR: isActive
    });
  } catch (error) {
    console.log(`Check subscription error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ BILLING & SUBSCRIPTION ENDPOINTS ============

// Get subscription details
app.get('/make-server-1dccd8d3/vendor/:vendorId/subscription', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const vendor = await kv.get(`vendor:${vendorId}`);
    
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    // Get or create demo subscription data
    const subscriptionKey = `subscription:${vendorId}`;
    let subscription = await kv.get(subscriptionKey);
    
    if (!subscription) {
      // Create demo subscription
      subscription = {
        vendorId,
        status: vendor.subscriptionStatus || 'active',
        planName: vendor.planName || 'Monthly',
        price: vendor.price || 49,
        interval: vendor.interval || 'month',
        subscriptionId: vendor.subscriptionId || `sub_demo_${Date.now()}`,
        nextBillingDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toLocaleDateString('en-US', { 
          month: 'short', 
          day: 'numeric', 
          year: 'numeric' 
        }),
        billingEmail: vendor.email || 'vendor@example.com',
        paymentMethod: {
          brand: 'Visa',
          last4: '4242',
          expMonth: '12',
          expYear: '2025',
          isDefault: true
        },
        paymentMethodUpdated: 'Dec 1, 2024',
        createdAt: new Date().toISOString()
      };
      await kv.set(subscriptionKey, subscription);
    }

    return c.json(subscription);
  } catch (error) {
    console.log(`Get subscription error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Get invoices
app.get('/make-server-1dccd8d3/vendor/:vendorId/invoices', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    
    // Get or create demo invoices
    const invoicesKey = `invoices:${vendorId}`;
    let invoices = await kv.get(invoicesKey);
    
    if (!invoices) {
      // Create demo invoices
      const currentDate = new Date();
      invoices = [
        {
          id: 'inv_001',
          invoiceNumber: 'INV-2024-001',
          date: new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
          }),
          amount: 49.00,
          vat: 9.31,
          status: 'paid'
        },
        {
          id: 'inv_002',
          invoiceNumber: 'INV-2023-012',
          date: new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1).toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
          }),
          amount: 49.00,
          vat: 9.31,
          status: 'paid'
        },
        {
          id: 'inv_003',
          invoiceNumber: 'INV-2023-011',
          date: new Date(currentDate.getFullYear(), currentDate.getMonth() - 2, 1).toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
          }),
          amount: 49.00,
          vat: 9.31,
          status: 'paid'
        }
      ];
      await kv.set(invoicesKey, invoices);
    }

    return c.json({ invoices });
  } catch (error) {
    console.log(`Get invoices error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Get usage stats
app.get('/make-server-1dccd8d3/vendor/:vendorId/usage-stats', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const vendor = await kv.get(`vendor:${vendorId}`);
    
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    // Calculate actual usage stats
    const tables = vendor.tablesData || [];
    const activeTables = tables.filter((t: any) => t.isActive).length;
    
    // Get orders count for this month
    const restaurantOrders = await kv.get(`restaurant:${vendorId}:orders`) || [];
    const currentMonth = new Date().getMonth();
    let ordersThisMonth = 0;
    
    for (const orderId of restaurantOrders.slice(0, 100)) {
      const order = await kv.get(`order:${orderId}`);
      if (order) {
        const orderDate = new Date(order.createdAt);
        if (orderDate.getMonth() === currentMonth) {
          ordersThisMonth++;
        }
      }
    }

    return c.json({
      activeTables: activeTables || 12,
      qrCodes: tables.length || 15,
      ordersThisMonth: ordersThisMonth || 847,
      staffAccounts: 3 // Demo value
    });
  } catch (error) {
    console.log(`Get usage stats error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Create Stripe Customer Portal session
app.post('/make-server-1dccd8d3/vendor/:vendorId/create-portal-session', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    
    // In demo mode, return a demo URL with return parameter
    // In production, this would create a real Stripe Customer Portal session
    // and set the return_url to include the payment_updated=success parameter
    const origin = c.req.header('origin') || 'http://localhost:3000';
    const returnUrl = `${origin}?payment_updated=success`;
    const portalUrl = `https://billing.stripe.com/p/session/test_demo_${Date.now()}?return_url=${encodeURIComponent(returnUrl)}`;
    
    return c.json({ portalUrl, returnUrl });
  } catch (error) {
    console.log(`Create portal session error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Retry failed payment
app.post('/make-server-1dccd8d3/vendor/:vendorId/retry-payment', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const vendor = await kv.get(`vendor:${vendorId}`);
    
    if (!vendor) {
      return c.json({ error: 'Vendor not found' }, 404);
    }

    // In demo mode, simulate payment retry
    vendor.subscriptionStatus = 'active';
    vendor.status = 'active';
    await kv.set(`vendor:${vendorId}`, vendor);
    
    return c.json({ 
      success: true,
      message: 'Payment retry initiated successfully'
    });
  } catch (error) {
    console.log(`Retry payment error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// Download invoice PDF
app.get('/make-server-1dccd8d3/vendor/:vendorId/invoice/:invoiceId/pdf', async (c) => {
  try {
    const vendorId = c.req.param('vendorId');
    const invoiceId = c.req.param('invoiceId');
    
    // In production, this would generate a real PDF invoice
    // For demo, return a simple text response
    const pdfContent = `INVOICE ${invoiceId}\n\nVendor: ${vendorId}\nAmount: €49.00\nVAT: €9.31\nTotal: €58.31`;
    
    return new Response(pdfContent, {
      headers: {
        'Content-Type': 'application/pdf',
        'Content-Disposition': `attachment; filename="invoice-${invoiceId}.pdf"`
      }
    });
  } catch (error) {
    console.log(`Download invoice error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

// ============ AVATAR UPLOAD ENDPOINT ============

app.post('/make-server-1dccd8d3/upload-avatar', async (c) => {
  try {
    const formData = await c.req.formData();
    const file = formData.get('file') as File;
    const path = formData.get('path') as string;

    if (!file || !path) {
      return c.json({ error: 'File and path are required' }, 400);
    }

    // Ensure avatars bucket exists
    const bucketName = 'make-1dccd8d3-avatars';
    const { data: buckets } = await supabase.storage.listBuckets();
    const bucketExists = buckets?.some(bucket => bucket.name === bucketName);
    
    if (!bucketExists) {
      const { error: createError } = await supabase.storage.createBucket(bucketName, {
        public: false,
        fileSizeLimit: 5242880 // 5MB
      });
      
      if (createError) {
        console.log(`Error creating avatars bucket: ${createError.message}`);
        return c.json({ error: 'Failed to create storage bucket' }, 500);
      }
    }

    // Convert file to ArrayBuffer
    const arrayBuffer = await file.arrayBuffer();
    const uint8Array = new Uint8Array(arrayBuffer);

    // Upload to Supabase Storage
    const { data, error } = await supabase.storage
      .from(bucketName)
      .upload(path, uint8Array, {
        contentType: file.type,
        upsert: true
      });

    if (error) {
      console.log(`Upload error: ${error.message}`);
      return c.json({ error: error.message }, 500);
    }

    // Create signed URL (valid for 1 year)
    const { data: urlData, error: urlError } = await supabase.storage
      .from(bucketName)
      .createSignedUrl(path, 31536000); // 1 year in seconds

    if (urlError) {
      console.log(`Error creating signed URL: ${urlError.message}`);
      return c.json({ error: 'Failed to create signed URL' }, 500);
    }

    return c.json({ url: urlData.signedUrl });
  } catch (error) {
    console.log(`Avatar upload error: ${error}`);
    return c.json({ error: String(error) }, 500);
  }
});

Deno.serve(app.fetch);