import { projectId, publicAnonKey } from './supabase/info';

const API_BASE = `https://${projectId}.supabase.co/functions/v1/make-server-1dccd8d3`;

export const api = {
  // Auth
  register: async (email: string, password: string, name?: string, phone?: string) => {
    const response = await fetch(`${API_BASE}/auth/register`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ email, password, name, phone })
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Registration failed');
    }
    
    return response.json();
  },

  login: async (email: string, password: string) => {
    const response = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ email, password })
    });
    
    if (!response.ok) {
      const error = await response.json();
      console.error('Login error:', error);
      throw new Error(error.error || 'Login failed');
    }
    
    return response.json();
  },

  socialLogin: async (provider: 'google' | 'apple' | 'facebook' | 'github') => {
    const response = await fetch(`${API_BASE}/auth/social-login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ provider })
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Social login failed');
    }
    
    return response.json();
  },

  forgotPassword: async (email: string) => {
    const response = await fetch(`${API_BASE}/auth/forgot-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ email })
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Failed to send password reset email');
    }
    
    return response.json();
  },

  // Customers
  getCustomer: async (id: string) => {
    const response = await fetch(`${API_BASE}/customers/${id}`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  updateCustomer: async (id: string, updates: any) => {
    const response = await fetch(`${API_BASE}/customers/${id}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(updates)
    });
    return response.json();
  },

  // Sessions
  createSession: async (restaurantId: string, tableId: string, numPeople: number, sharedBasket: boolean) => {
    const response = await fetch(`${API_BASE}/sessions`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ restaurantId, tableId, numPeople, sharedBasket })
    });
    return response.json();
  },

  joinSession: async (sessionId: string, customerId?: string, guestId?: string, guestName?: string) => {
    const response = await fetch(`${API_BASE}/sessions/${sessionId}/join`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ customerId, guestId, guestName })
    });
    return response.json();
  },

  getSession: async (sessionId: string) => {
    const response = await fetch(`${API_BASE}/sessions/${sessionId}`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  updateSessionItems: async (sessionId: string, items: any[]) => {
    const response = await fetch(`${API_BASE}/sessions/${sessionId}/items`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ items })
    });
    return response.json();
  },

  // Restaurant
  getRestaurant: async (id: string) => {
    const response = await fetch(`${API_BASE}/restaurants/${id}`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  getMenu: async (restaurantId: string) => {
    const response = await fetch(`${API_BASE}/restaurants/${restaurantId}/menu`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  updateMenu: async (restaurantId: string, menuData: any) => {
    const response = await fetch(`${API_BASE}/restaurants/${restaurantId}/menu`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(menuData)
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Menu update failed');
    }
    
    return response.json();
  },

  // Orders
  createOrder: async (data: any) => {
    const response = await fetch(`${API_BASE}/orders`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(data)
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Order creation failed');
    }
    
    return response.json();
  },

  getOrder: async (orderId: string) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  updateOrder: async (orderId: string, updates: any) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(updates)
    });
    return response.json();
  },

  settleCashOrder: async (orderId: string, cashierId: string) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}/settle-cash`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ cashierId })
    });
    return response.json();
  },

  // Split Payments
  createSplitPayment: async (orderId: string, splitData: any) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}/split`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(splitData)
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Split payment creation failed');
    }
    
    return response.json();
  },

  getSplitPayment: async (splitId: string) => {
    const response = await fetch(`${API_BASE}/split-payments/${splitId}`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  markSplitAsPaid: async (splitId: string, paymentData: any) => {
    const response = await fetch(`${API_BASE}/split-payments/${splitId}/pay`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(paymentData)
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Payment processing failed');
    }
    
    return response.json();
  },

  getOrderSplits: async (orderId: string) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}/splits`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  // Reviews
  createOrderReview: async (orderId: string, data: any) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}/review`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(data)
    });
    return response.json();
  },

  getItemReviews: async (itemId: string) => {
    const response = await fetch(`${API_BASE}/items/${itemId}/reviews`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  getCustomerOrders: async (customerId: string) => {
    const response = await fetch(`${API_BASE}/customers/${customerId}/orders`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  // Vendor
  getTopCustomers: async (vendorId: string, period = '6m') => {
    const response = await fetch(`${API_BASE}/vendor/${vendorId}/top-customers?period=${period}`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  getComplaints: async (vendorId: string, filter = 'all') => {
    const response = await fetch(`${API_BASE}/vendor/${vendorId}/complaints?filter=${filter}`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  replyToReview: async (vendorId: string, reviewId: string, replyText: string) => {
    const response = await fetch(`${API_BASE}/vendor/${vendorId}/reviews/${reviewId}/reply`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ reply: replyText })
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Failed to post reply');
    }
    
    return response.json();
  },

  generateInvoice: async (vendorId: string, orderId: string, buyerInfo?: any) => {
    const response = await fetch(`${API_BASE}/vendor/${vendorId}/invoice`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ orderId, buyerInfo })
    });
    return response.json();
  },

  getVendorOrders: async (vendorId: string) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/orders`, {
        headers: {
          'Authorization': `Bearer ${publicAnonKey}`
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch orders');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, returning empty orders list');
      // Return empty array when backend is unavailable
      return [];
    }
  },

  // Settings
  getVendorSettings: async (vendorId: string) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/settings`, {
        headers: {
          'Authorization': `Bearer ${publicAnonKey}`
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch settings');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, using demo vendor settings');
      // Return demo settings when backend is unavailable
      return {
        restaurantName: 'La Bella Cucina',
        address: '123 Main Street, Vienna',
        phone: '+43 1 234 5678',
        email: 'info@labellacucina.at',
        website: 'https://labellacucina.at',
        currency: 'EUR',
        defaultLanguage: 'en',
        supportedLanguages: ['en', 'de', 'it', 'fr', 'ar', 'tr', 'zh', 'ja', 'sr', 'cs', 'es'],
        description: 'Authentic Italian cuisine in the heart of Vienna',
        logo: null,
        coverPhoto: null,
        businessHours: {
          monday: { open: '11:00', close: '22:00', closed: false },
          tuesday: { open: '11:00', close: '22:00', closed: false },
          wednesday: { open: '11:00', close: '22:00', closed: false },
          thursday: { open: '11:00', close: '22:00', closed: false },
          friday: { open: '11:00', close: '23:00', closed: false },
          saturday: { open: '11:00', close: '23:00', closed: false },
          sunday: { open: '12:00', close: '21:00', closed: false }
        },
        minOrderAmount: 10.5,
        maxOrderAmount: 1000,
        vatRate: 20,
        serviceFeeRate: 0,
        acceptApplePay: true,
        acceptGooglePay: true,
        acceptCard: true,
        acceptCash: true,
        acceptCashTakeaway: true,
        enableLoyalty: true,
        pointsPerEuro: 0.5,
        minimumRedemption: 150,
        redemptionRate: 0.05,
        pointsExpiry: 365,
        enableReservations: true,
        maxReservationPartySize: 12,
        reservationSlotDuration: 120,
        advanceBookingDays: 30
      };
    }
  },

  updateVendorSettings: async (vendorId: string, settings: any) => {
    const response = await fetch(`${API_BASE}/vendor/${vendorId}/settings`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(settings)
    });
    return response.json();
  },

  // Update menu item (e.g., availability, price, etc.)
  updateMenuItem: async (vendorId: string, itemId: string, updates: any) => {
    const response = await fetch(`${API_BASE}/vendor/${vendorId}/menu/items/${itemId}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(updates)
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Failed to update menu item');
    }
    
    return response.json();
  },

  // Seed data
  seedData: async (force = false) => {
    const url = force ? `${API_BASE}/seed?force=true` : `${API_BASE}/seed`;
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  // Create demo users
  createDemoUsers: async () => {
    const response = await fetch(`${API_BASE}/create-demo-users`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Failed to create demo users');
    }
    
    return response.json();
  },

  // Reservations
  getAvailableSlots: async (restaurantId: string, date: string, partySize: number) => {
    const response = await fetch(`${API_BASE}/reservations/available-slots`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ restaurantId, date, partySize })
    });
    return response.json();
  },

  createReservation: async (data: any) => {
    const response = await fetch(`${API_BASE}/reservations`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify(data)
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Reservation creation failed');
    }
    
    return response.json();
  },

  getCustomerReservations: async (customerId: string) => {
    const response = await fetch(`${API_BASE}/customers/${customerId}/reservations`, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  getVendorReservations: async (vendorId: string, status?: string, date?: string) => {
    let url = `${API_BASE}/vendor/${vendorId}/reservations`;
    const params = [];
    if (status) params.push(`status=${status}`);
    if (date) params.push(`date=${date}`);
    if (params.length > 0) url += `?${params.join('&')}`;
    
    const response = await fetch(url, {
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  updateReservationStatus: async (reservationId: string, status: string, vendorNote?: string) => {
    const response = await fetch(`${API_BASE}/reservations/${reservationId}/status`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ status, vendorNote })
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Status update failed');
    }
    
    return response.json();
  },

  cancelReservation: async (reservationId: string, customerId: string) => {
    const response = await fetch(`${API_BASE}/reservations/${reservationId}/cancel`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ customerId })
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Cancellation failed');
    }
    
    return response.json();
  },

  // Notifications
  getNotifications: async (recipientType: 'customer' | 'vendor', recipientId: string) => {
    try {
      const response = await fetch(`${API_BASE}/notifications/${recipientType}/${recipientId}`, {
        headers: {
          'Authorization': `Bearer ${publicAnonKey}`
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch notifications');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, returning empty notifications');
      // Return empty array when backend is unavailable
      return [];
    }
  },

  markNotificationAsRead: async (notificationId: string) => {
    const response = await fetch(`${API_BASE}/notifications/${notificationId}/read`, {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    return response.json();
  },

  // Takeaway
  getAvailablePickupSlots: async (restaurantId: string, date: string, prepTime?: number) => {
    const response = await fetch(`${API_BASE}/takeaway/available-slots`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${publicAnonKey}`
      },
      body: JSON.stringify({ restaurantId, date, prepTime })
    });
    return response.json();
  },

  markOrderReady: async (orderId: string) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}/ready`, {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Failed to mark order as ready');
    }
    
    return response.json();
  },

  markOrderPickedUp: async (orderId: string) => {
    const response = await fetch(`${API_BASE}/orders/${orderId}/picked-up`, {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${publicAnonKey}`
      }
    });
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error || 'Failed to mark order as picked up');
    }
    
    return response.json();
  },

  // Inventory Management
  getInventoryItems: async (vendorId: string) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/inventory/items`, {
        headers: {
          'Authorization': `Bearer ${publicAnonKey}`
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch inventory items');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, returning demo inventory items');
      // Return demo data for development
      return [
        {
          id: '1',
          name: 'Tomatoes (Fresh)',
          category: 'Produce',
          currentStock: 15,
          unit: 'kg',
          reorderLevel: 20,
          reorderQuantity: 30,
          supplier: 'Fresh Foods Co',
          costPerUnit: 2.50,
          lastUpdated: new Date().toISOString(),
          status: 'low'
        },
        {
          id: '2',
          name: 'Mozzarella Cheese',
          category: 'Dairy',
          currentStock: 8,
          unit: 'kg',
          reorderLevel: 10,
          reorderQuantity: 20,
          supplier: 'Italian Imports',
          costPerUnit: 8.90,
          lastUpdated: new Date().toISOString(),
          status: 'low'
        },
        {
          id: '3',
          name: 'Olive Oil (Extra Virgin)',
          category: 'Oils',
          currentStock: 25,
          unit: 'liters',
          reorderLevel: 15,
          reorderQuantity: 25,
          supplier: 'Mediterranean Traders',
          costPerUnit: 12.50,
          lastUpdated: new Date().toISOString(),
          status: 'ok'
        },
        {
          id: '4',
          name: 'Pasta (Penne)',
          category: 'Dry Goods',
          currentStock: 0,
          unit: 'kg',
          reorderLevel: 20,
          reorderQuantity: 50,
          supplier: 'Italian Imports',
          costPerUnit: 1.80,
          lastUpdated: new Date(Date.now() - 86400000 * 5).toISOString(),
          status: 'out'
        },
        {
          id: '5',
          name: 'Basil (Fresh)',
          category: 'Herbs',
          currentStock: 5,
          unit: 'bunches',
          reorderLevel: 10,
          reorderQuantity: 15,
          supplier: 'Fresh Foods Co',
          costPerUnit: 1.20,
          lastUpdated: new Date().toISOString(),
          status: 'low'
        },
        {
          id: '6',
          name: 'Chicken Breast',
          category: 'Meat',
          currentStock: 35,
          unit: 'kg',
          reorderLevel: 25,
          reorderQuantity: 40,
          supplier: 'Premium Meats Ltd',
          costPerUnit: 6.50,
          lastUpdated: new Date().toISOString(),
          status: 'ok'
        }
      ];
    }
  },

  // Convenience alias
  getInventory: async (vendorId: string) => {
    return api.getInventoryItems(vendorId);
  },

  addInventoryItem: async (vendorId: string, item: any) => {
    return api.createInventoryItem(vendorId, item);
  },

  createInventoryItem: async (vendorId: string, item: any) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/inventory/items`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${publicAnonKey}`
        },
        body: JSON.stringify(item)
      });
      
      if (!response.ok) {
        throw new Error('Failed to create inventory item');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, item not saved');
      return { success: false, error: 'Backend unavailable' };
    }
  },

  updateInventoryItem: async (vendorId: string, itemId: string, updates: any) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/inventory/items/${itemId}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${publicAnonKey}`
        },
        body: JSON.stringify(updates)
      });
      
      if (!response.ok) {
        throw new Error('Failed to update inventory item');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, item not updated');
      return { success: false, error: 'Backend unavailable' };
    }
  },

  deleteInventoryItem: async (vendorId: string, itemId: string) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/inventory/items/${itemId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${publicAnonKey}`
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to delete inventory item');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, item not deleted');
      return { success: false, error: 'Backend unavailable' };
    }
  },

  getInventorySettings: async (vendorId: string) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/inventory/settings`, {
        headers: {
          'Authorization': `Bearer ${publicAnonKey}`
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch inventory settings');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, returning default inventory settings');
      return null;
    }
  },

  updateInventorySettings: async (vendorId: string, settings: any) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/inventory/settings`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${publicAnonKey}`
        },
        body: JSON.stringify(settings)
      });
      
      if (!response.ok) {
        throw new Error('Failed to update inventory settings');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, settings not saved');
      return { success: false, error: 'Backend unavailable' };
    }
  },

  getVendorSubscription: async (vendorId: string) => {
    try {
      const response = await fetch(`${API_BASE}/vendor/${vendorId}/subscription`, {
        headers: {
          'Authorization': `Bearer ${publicAnonKey}`
        }
      });
      
      if (!response.ok) {
        throw new Error('Failed to fetch subscription');
      }
      
      return response.json();
    } catch (error) {
      console.log('⚠️ Backend unavailable, returning demo subscription');
      return {
        plan: 'professional',
        status: 'active',
        billingCycle: 'monthly'
      };
    }
  }
};