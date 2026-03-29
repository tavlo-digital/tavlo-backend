import React, { useState, useEffect } from 'react';
import { CheckCircle, ChevronDown } from 'lucide-react';
import { Toaster } from 'sonner@2.0.3';
import { toast } from 'sonner@2.0.3';
import { api } from './utils/api';
import { LanguageProvider, useLanguage } from './contexts/LanguageContext';
import { AccessibilityProvider, useAccessibility } from './contexts/AccessibilityContext';
import { projectId } from './utils/supabase/info';
import { PlatformApp } from './components/PlatformApp';
import { QRLanding } from './components/QRLanding';
import { QRLandingTestHarness } from './components/QRLandingTestHarness';
import { AuthScreen } from './components/AuthScreen';
import { MenuList } from './components/MenuList';
import { DishDetails } from './components/DishDetails';
import { BasketView } from './components/BasketView';
import { PaymentFlow } from './components/PaymentFlow';
import { OrderTracking } from './components/OrderTracking';
import { ActiveOrdersList } from './components/ActiveOrdersList';
import { ItemReviewForm } from './components/ItemReviewForm';
import { VendorDashboard } from './components/vendor/VendorDashboard';
import { OrderHistory } from './components/OrderHistory';
import { AccountPage } from './components/account/AccountPage';
import { AccountSettings } from './components/AccountSettings';
import { Receipt } from './components/Receipt';
import { FALLBACK_MENU } from './data/fallbackMenu';
import { LogoShowcase } from './components/branding/LogoShowcase';
import { LogoConceptsShowcase } from './components/branding/LogoConceptsShowcase';
import { AdminApp } from './components/admin/AdminApp';
import { AIShowcase } from './components/AIShowcase';
import VendorOnboardingFlow from './pages/VendorOnboardingFlow';
import AdminVendorManagement from './pages/AdminVendorManagement';
import { TavloPlatformPage } from './components/TavloPlatformPage';
import { VendorAuthDemo } from './pages/VendorAuthDemo';
import { JiraCSVGenerator } from './pages/JiraCSVGenerator';

const API_BASE = `https://${projectId}.supabase.co/functions/v1/make-server-1dccd8d3`;

type Screen = 
  | 'platform-home' // New platform homepage
  | 'browse' // Restaurant discovery/browse page
  | 'qr-landing'
  | 'qr-landing-test' // QR Landing test harness
  | 'auth'
  | 'menu'
  | 'basket'
  | 'payment'
  | 'order-tracking'
  | 'active-orders-list'
  | 'review'
  | 'receipt'
  | 'vendor-dashboard'
  | 'order-history'
  | 'account-page'
  | 'account-settings'
  | 'brand-assets'
  | 'ai-showcase' // New AI showcase
  | 'admin-dashboard' // New admin mode
  | 'vendor-onboarding' // Vendor onboarding flow
  | 'admin-vendor-management' // Admin vendor management
  | 'vendor-auth-demo' // Vendor authentication demo
  | 'jira-csv'; // Jira CSV Generator

type ModalState = {
  isOpen: boolean;
  type?: 'restaurant-selection';
};

type Restaurant = {
  id: string;
  name: string;
  cuisineTag: string;
};

function AppContent() {
  const [screen, setScreen] = useState<Screen>('platform-home');
  const [modalState, setModalState] = useState<ModalState>({ isOpen: false });
  const [selectedRestaurant, setSelectedRestaurant] = useState<Restaurant | null>(null);
  const [showMultiUserPaymentModal, setShowMultiUserPaymentModal] = useState(false);
  const { language, setLanguage } = useLanguage();
  const { theme } = useAccessibility();
  const [isModeSwitcherOpen, setIsModeSwitcherOpen] = useState(false);

  const [authMode, setAuthMode] = useState<'signin' | 'register'>('signin');
  const [currentUser, setCurrentUser] = useState<any>(null);
  const [guestId] = useState(() => crypto.randomUUID());
  
  const [restaurant, setRestaurant] = useState<any>(null);
  const [menu, setMenu] = useState<any>(null);
  const [session, setSession] = useState<any>(null);
  const [sessionPin, setSessionPin] = useState<string>(() => {
    // Generate random 4-digit PIN on mount
    return Math.floor(1000 + Math.random() * 9000).toString();
  });
  const [basketItems, setBasketItems] = useState<any[]>([]);
  const [currentDish, setCurrentDish] = useState<any>(null);
  const [currentOrder, setCurrentOrder] = useState<any>(null);
  const [isPayingPendingOrder, setIsPayingPendingOrder] = useState(false); // Track if paying a pending order
  const [pendingOrders, setPendingOrders] = useState<any[]>([]); // Orders awaiting payment
  const [activeOrders, setActiveOrders] = useState<any[]>([]); // Orders being prepared/delivered
  const [orderHistory, setOrderHistory] = useState<any[]>([]); // Completed/paid orders
  const [customerOrders, setCustomerOrders] = useState<any[]>([]); // Customer order history
  const [reviewingItem, setReviewingItem] = useState<any>(null); // Item being reviewed (null = restaurant review)
  const [vendorSettings, setVendorSettings] = useState<any>(null); // Vendor settings
  
  // Customer preference: Show nutrition information (default OFF, persisted in localStorage)
  const [showNutrition, setShowNutrition] = useState<boolean>(() => {
    const saved = localStorage.getItem('tavlo-show-nutrition');
    return saved === 'true'; // Default to false
  });

  // Persist nutrition toggle preference
  useEffect(() => {
    localStorage.setItem('tavlo-show-nutrition', String(showNutrition));
  }, [showNutrition]);
  
  // Takeaway order state
  const [takeawayOrder, setTakeawayOrder] = useState<{
    guestData: { name: string; phone?: string; email?: string } | null;
    pickupData: { pickupTime: string; scheduledFor: 'asap' | 'scheduled'; displayTime: string } | null;
  } | null>(null);

  const restaurantId = 'rest_1';
  const tableNumber = '12';

  // Helper function to check if two basket items are identical
  const areItemsIdentical = (item1: any, item2: any) => {
    // Must be same menu item
    if (item1.menuItemId !== item2.menuItemId && item1.name !== item2.name) {
      return false;
    }

    // Check special instructions
    const instructions1 = (item1.specialInstructions || '').trim();
    const instructions2 = (item2.specialInstructions || '').trim();
    if (instructions1 !== instructions2) {
      return false;
    }

    // Check modifiers - must have same count
    const mods1 = item1.modifiers || [];
    const mods2 = item2.modifiers || [];
    if (mods1.length !== mods2.length) {
      return false;
    }

    // If no modifiers, items are identical
    if (mods1.length === 0) {
      return true;
    }

    // Check each modifier matches
    // Sort both arrays by name to ensure order doesn't matter
    const sorted1 = [...mods1].sort((a, b) => a.name.localeCompare(b.name));
    const sorted2 = [...mods2].sort((a, b) => a.name.localeCompare(b.name));

    for (let i = 0; i < sorted1.length; i++) {
      const mod1 = sorted1[i];
      const mod2 = sorted2[i];
      
      if (mod1.name !== mod2.name || 
          mod1.price !== mod2.price || 
          mod1.type !== mod2.type) {
        return false;
      }
    }

    return true;
  };

  useEffect(() => {
    // Initialize seed data
    initializeData();
  }, []);

  // Check for URL parameters to handle redirects (e.g., from Stripe checkout)
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const mode = params.get('mode');
    
    if (mode === 'vendor-onboarding') {
      setScreen('vendor-onboarding');
    }
  }, []);

  const initializeData = async () => {
    try {
      // First, check if server is reachable
      console.log('🔍 Checking server health...');
      const healthCheck = await fetch(`${API_BASE}/health`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json'
        }
      }).catch((err) => {
        // Silently handle fetch errors - this is expected in Figma Make preview
        console.log('ℹ️ Backend not reachable (expected in preview mode)');
        return null;
      });
      
      if (!healthCheck || !healthCheck.ok) {
        console.log('ℹ️ Running in demo mode with sample data');
        console.log('✅ This is normal in Figma Make preview. The backend will connect once deployed.');
        
        // Use fallback data for development/preview
        setRestaurant({
          id: 'rest_1',
          name: 'La Bella Cucina',
          cuisineTag: 'Italian Fine Dining'
        });
        
        setMenu(FALLBACK_MENU);
        
        setVendorSettings({
          restaurantName: 'La Bella Cucina',
          address: '123 Main Street, Vienna',
          phone: '+43 1 234 5678',
          currency: 'EUR',
          defaultLanguage: 'en',
          supportedLanguages: ['en', 'de', 'it', 'fr', 'ar', 'tr', 'zh', 'ja', 'sr', 'cs', 'es'],
          description: 'Authentic Italian cuisine in the heart of Vienna',
          // Default order limits
          minOrderAmount: 10.5,
          maxOrderAmount: 1000,
          vatRate: 20,
          serviceFeeRate: 0,
          // Loyalty settings (demo mode defaults)
          enableLoyalty: true,
          pointsPerEuro: 0.5,
          minimumRedemption: 150,
          redemptionRate: 0.05, // €0.05 per point (100 points = €5)
          pointsExpiry: 365
        });
        
        return;
      }
      
      console.log('✅ Server is reachable, seeding data...');
      await api.seedData();
      
      console.log('✅ Ensuring demo users exist...');
      await api.createDemoUsers();
      
      console.log('✅ Data seeded, loading restaurant info...');
      
      // Load vendor settings from server
      try {
        const settings = await api.getVendorSettings(restaurantId);
        console.log('✅ Loaded vendor settings:', settings);
        setVendorSettings(settings);
      } catch (error) {
        console.error('Error loading vendor settings:', error);
      }
    } catch (error) {
      console.error('❌ Error initializing data:', error);
      
      // Show user-friendly error message
      alert(
        '⚠️ Failed to Load Data\n\n' +
        'There was an error loading the restaurant data.\n\n' +
        'Error: ' + (error instanceof Error ? error.message : String(error)) + '\n\n' +
        'Check the browser console for more details.'
      );
    }
  };

  // Listen for settings updates
  useEffect(() => {
    const handleSettingsUpdate = async () => {
      try {
        const settings = await api.getVendorSettings(restaurantId);
        setVendorSettings(settings);
      } catch (error) {
        console.error('Error refreshing settings:', error);
      }
    };

    window.addEventListener('settings-updated', handleSettingsUpdate);
    return () => window.removeEventListener('settings-updated', handleSettingsUpdate);
  }, [restaurantId]);

  // Listen for menu updates
  useEffect(() => {
    const handleMenuUpdate = async () => {
      try {
        const menuData = await api.getMenu(restaurantId);
        setMenu(menuData);
        console.log('✅ Menu refreshed after vendor update');
      } catch (error) {
        console.error('Error refreshing menu:', error);
      }
    };

    window.addEventListener('menu-updated', handleMenuUpdate);
    return () => window.removeEventListener('menu-updated', handleMenuUpdate);
  }, [restaurantId]);

  // Listen for user profile updates
  useEffect(() => {
    const handleUserUpdate = (event: any) => {
      const updatedUser = event.detail;
      setCurrentUser(updatedUser);
      console.log('✅ User profile updated:', updatedUser);
    };

    window.addEventListener('user-updated', handleUserUpdate);
    return () => window.removeEventListener('user-updated', handleUserUpdate);
  }, []);

  const handleQRContinue = async (data: any) => {
    // Set the selected language immediately
    setLanguage(data.language);
    
    // Create session
    const sessionData = await api.createSession(
      restaurantId,
      tableNumber,
      data.numPeople,
      data.sharedBasket
    );
    setSession({ ...sessionData, numPeople: data.numPeople, sharedBasket: data.sharedBasket });

    if (data.authChoice === 'guest') {
      // Join as guest and go directly to menu
      await api.joinSession(sessionData.sessionId, undefined, guestId, 'Guest');
      setScreen('menu');
    } else if (data.authChoice === 'signin') {
      setAuthMode('signin');
      setScreen('auth');
    } else if (data.authChoice === 'register') {
      setAuthMode('register');
      setScreen('auth');
    }
  };

  const handleAuthSuccess = async (customer: any, token?: string) => {
    setCurrentUser(customer);
    
    // Join session if exists
    if (session) {
      await api.joinSession(session.sessionId, customer.id);
    }
    
    // Go directly to menu (language already selected on QR landing page)
    setScreen('menu');
  };

  const handleDishClick = (item: any) => {
    setCurrentDish(item);
  };

  const handleAddToBasket = async (item: any, quantity: number, modifiers: any[], specialRequest: string) => {
    // MIGRATION FIX: Ensure item has taxCategory field
    // Infer from vatRate if missing (for backward compatibility)
    let taxCategory = item.taxCategory;
    if (!taxCategory) {
      if (item.vatRate === 20) {
        taxCategory = item.category === 'drinks' ? 'beverage-alcoholic' : 'beverage-non-alcoholic';
      } else {
        taxCategory = 'food';
      }
    }
    
    const basketItem = {
      ...item,
      taxCategory, // Ensure taxCategory is always present
      quantity,
      modifiers,
      specialRequest,
      addedBy: currentUser?.id || guestId
    };

    const newItems = [...basketItems, basketItem];
    setBasketItems(newItems);

    // Update session
    if (session) {
      await api.updateSessionItems(session.sessionId, newItems);
    }

    setCurrentDish(null);
    toast.success('Added to basket');
  };

  const handleCallWaiter = () => {
    const confirmed = window.confirm(`Notify staff to table ${tableNumber}?`);
    if (confirmed) {
      toast.success('Waiter notified');
    }
  };

  const handleSubmitOrder = () => {
    // Validate order amount
    const itemsTotal = basketItems.reduce((sum, item) => {
      const itemTotal = item.price * item.quantity;
      const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
        mSum + (m.price * item.quantity), 0) || 0;
      return sum + itemTotal + modifiersTotal;
    }, 0);

    const minOrderAmount = vendorSettings?.minOrderAmount || 0;
    const maxOrderAmount = vendorSettings?.maxOrderAmount || 10000;

    console.log('=== Navigating to Payment ===');
    console.log('basketItems:', basketItems);
    console.log('itemsTotal:', itemsTotal);
    console.log('minOrderAmount:', minOrderAmount);

    if (itemsTotal < minOrderAmount) {
      toast.error(`Minimum order amount is €${minOrderAmount.toFixed(2)}`);
      return;
    }

    if (itemsTotal > maxOrderAmount) {
      toast.error(`Maximum order amount is €${maxOrderAmount.toFixed(2)}`);
      return;
    }

    // Clear currentOrder to ensure we're creating/updating, not paying
    setCurrentOrder(null);
    setScreen('payment');
  };

  const handlePaymentComplete = async (paymentData: any) => {
    console.log('=== handlePaymentComplete called ===');
    console.log('paymentData:', paymentData);
    console.log('basketItems at start:', basketItems);
    console.log('currentOrder:', currentOrder);
    
    // Handle split payments
    if (paymentData.splitPayment && paymentData.split) {
      console.log('Processing split payment');
      
      // If this is from an existing order being paid
      if (currentOrder?.paymentPending) {
        // Save split payment data to the order
        await api.createSplitPayment(currentOrder.id, paymentData.split);
        
        // Update order with split info
        await api.updateOrder(currentOrder.id, {
          splitPayment: paymentData.split,
          tip: paymentData.split.tip || 0,
        });
        
        // Reload the order
        const updatedOrder = await api.getOrder(currentOrder.id);
        setCurrentOrder(updatedOrder);
        
        toast.success('Split payment configured! Each person can now pay their share.');
        setScreen('order-tracking');
        return;
      } else {
        // Creating new order with split payment
        // Calculate total from basketItems (for split payment we need to calculate it)
        const itemsTotal = basketItems.reduce((sum, item) => {
          const basePrice = item.price * item.quantity;
          const modifiersPrice = item.modifiers?.reduce((mSum: number, m: any) => 
            mSum + (m.price * item.quantity), 0) || 0;
          return sum + basePrice + modifiersPrice;
        }, 0);
        
        const orderData = {
          sessionId: session.sessionId,
          items: basketItems,
          total: itemsTotal, // Calculate total from items for split payments
          paymentMethod: 'split',
          tip: paymentData.split.tip || 0,
          receiptRequested: paymentData.receiptRequested,
          split: paymentData.split,
          customerId: currentUser?.id || guestId,
          numPeople: session?.numPeople || 1,
          tableNumber: session?.tableNumber,
          status: 'received',
          paymentPending: paymentData.split.pendingTotal > 0, // Only pending if there's remaining amount
          paymentReceived: paymentData.split.paidTotal > 0, // Mark as received if some payment made
          loyaltyPointsRedeemed: paymentData.loyaltyPointsRedeemed || 0,
          loyaltyDiscount: paymentData.loyaltyDiscount || 0,
          // Takeaway fields
          orderType: takeawayOrder ? 'takeaway' : 'dine-in',
          pickupTime: takeawayOrder?.pickupData?.pickupTime || null,
          scheduledFor: takeawayOrder?.pickupData?.scheduledFor || null,
          customerName: takeawayOrder?.guestData?.name || null,
          customerPhone: takeawayOrder?.guestData?.phone || null,
          customerEmail: takeawayOrder?.guestData?.email || null
        };
        
        const result = await api.createOrder(orderData);
        
        // Save split payment data
        await api.createSplitPayment(result.orderId, paymentData.split);
        
        // Load the order
        const order = await api.getOrder(result.orderId);
        setCurrentOrder(order);
        
        // Clear basket
        setBasketItems([]);
        
        // Add to pending orders only if there's a pending amount
        if (order.splitPayment.pendingTotal > 0) {
          setPendingOrders(prev => [...prev, order]);
        }
        
        // Add to active orders regardless (kitchen needs to see it)
        setActiveOrders(prev => [...prev, order]);
        
        toast.success('Order created! Each person can now pay their share.');
        setScreen('order-tracking');
        return;
      }
    }
    
    // Only process as payment if currentOrder is set AND has paymentPending flag
    // This means user clicked "Pay Now" from basket view
    if (currentOrder?.paymentPending) {
      console.log('Updating existing pending order');
      // Mark order as paid - DON'T change status (vendor controls that)
      await api.updateOrder(currentOrder.id, { 
        paymentMethod: paymentData.paymentMethod,
        tip: paymentData.tip || 0,
        paymentPending: false,
        paymentReceived: true,
        loyaltyPointsRedeemed: paymentData.loyaltyPointsRedeemed || 0,
        loyaltyDiscount: paymentData.loyaltyDiscount || 0
      });
      
      // Reload the order to get updated data with new status
      const updatedOrder = await api.getOrder(currentOrder.id);
      setCurrentOrder(updatedOrder);
      
      // Calculate and award loyalty points for this payment
      if (currentUser) {
        try {
          // Calculate points earned from this order
          const orderTotal = updatedOrder.items.reduce((sum: number, item: any) => sum + (item.price * item.quantity), 0) + (updatedOrder.tip || 0);
          const pointsEarned = Math.floor(orderTotal); // €1 = 1 point
          
          console.log('Awarding loyalty points for paid order:', { orderTotal, pointsEarned, restaurantId: restaurant?.id, restaurantName: vendorSettings?.restaurantName || restaurant?.name });
          
          // Update restaurantLoyalty array
          const updatedRestaurantLoyalty = [...(currentUser.restaurantLoyalty || [])];
          const restaurantIndex = updatedRestaurantLoyalty.findIndex(
            (rl: any) => rl.restaurantId === restaurant?.id || rl.restaurantName === (vendorSettings?.restaurantName || restaurant?.name)
          );
          
          if (restaurantIndex >= 0) {
            // Update existing restaurant loyalty
            updatedRestaurantLoyalty[restaurantIndex] = {
              ...updatedRestaurantLoyalty[restaurantIndex],
              points: (updatedRestaurantLoyalty[restaurantIndex].points || 0) + pointsEarned
            };
          } else {
            // Create new restaurant loyalty entry
            updatedRestaurantLoyalty.push({
              restaurantId: restaurant?.id || 'unknown',
              restaurantName: vendorSettings?.restaurantName || restaurant?.name || 'Restaurant',
              restaurantLogo: vendorSettings?.logo || restaurant?.logo || undefined,
              points: pointsEarned,
              minimumRedemption: vendorSettings?.minimumRedemption || 100,
              redemptionRate: vendorSettings?.redemptionRate || 0.05,
              transactions: []
            });
          }
          
          // Update customer in database
          await api.updateCustomer(currentUser.id, { restaurantLoyalty: updatedRestaurantLoyalty });
          
          // Update local state
          const updatedUser = { ...currentUser, restaurantLoyalty: updatedRestaurantLoyalty };
          setCurrentUser(updatedUser);
          
          // Emit event to update user in other components
          window.dispatchEvent(new CustomEvent('user-updated', { detail: updatedUser }));
          
          console.log('Loyalty points awarded successfully for paid order:', updatedRestaurantLoyalty);
        } catch (error) {
          console.error('Error awarding loyalty points for paid order:', error);
          // Don't fail the payment if loyalty points fail
        }
      }
      
      // Remove from pending orders
      setPendingOrders(prev => prev.filter(o => o.id !== currentOrder.id));
      
      // Update in active orders with the new status (or add if not present)
      setActiveOrders(prev => {
        const existingIndex = prev.findIndex(o => o.id === currentOrder.id);
        if (existingIndex >= 0) {
          // Update existing order with new status
          const newOrders = [...prev];
          newOrders[existingIndex] = updatedOrder;
          return newOrders;
        } else {
          // Add if not present
          return [...prev, updatedOrder];
        }
      });
      
      toast.success('Payment completed!');
      
      // Add to order history
      setOrderHistory(prev => [...prev, updatedOrder]);
      
      // DON'T navigate here - let the button handler decide
      return;
    }
    
    console.log('Creating new order');
    
    // Check if there's an existing pending order for this session
    const existingPendingOrder = pendingOrders.find(o => o.sessionId === session?.sessionId);
    
    if (existingPendingOrder && (paymentData.paymentMethod === 'cash' || paymentData.payLater)) {
      // Add items to existing pending order
      const updatedItems = [...existingPendingOrder.items, ...basketItems];
      const updatedOrder = { ...existingPendingOrder, items: updatedItems };
      
      // Update the order via API
      await api.updateOrder(existingPendingOrder.id, { items: updatedItems });
      
      // Update pending orders state
      setPendingOrders(prev => prev.map(o => 
        o.id === existingPendingOrder.id ? updatedOrder : o
      ));
      
      setBasketItems([]);
      toast.success('Items added to your order!');
      // DON'T navigate - success screen will handle it
      return;
    }
    
    // Create new order
    // Calculate total from basketItems as fallback if paymentData.total is missing
    const calculatedTotal = basketItems.reduce((sum, item) => {
      const basePrice = item.price * item.quantity;
      const modifiersPrice = item.modifiers?.reduce((mSum: number, m: any) => 
        mSum + (m.price * item.quantity), 0) || 0;
      return sum + basePrice + modifiersPrice;
    }, 0);
    
    console.log('=== Creating Order ===');
    console.log('paymentData.total:', paymentData.total);
    console.log('calculatedTotal from basketItems:', calculatedTotal);
    console.log('basketItems:', basketItems);
    
    const orderData = {
      sessionId: session?.sessionId || null,
      items: basketItems,
      total: paymentData.total || calculatedTotal, // Use total from payment data, fallback to calculated
      paymentMethod: paymentData.paymentMethod,
      tip: paymentData.tip || 0,
      receiptRequested: paymentData.receiptRequested,
      split: paymentData.split,
      customerId: currentUser?.id || guestId, // Include guest ID for tracking
      numPeople: session?.numPeople || 1,
      tableNumber: session?.tableNumber || null,
      status: 'received', // All orders start as "received" regardless of payment
      paymentPending: (paymentData.paymentMethod === 'cash' || paymentData.payLater),
      paymentReceived: !(paymentData.paymentMethod === 'cash' || paymentData.payLater),
      loyaltyPointsRedeemed: paymentData.loyaltyPointsRedeemed || 0,
      loyaltyDiscount: paymentData.loyaltyDiscount || 0,
      // Takeaway fields
      orderType: takeawayOrder ? 'takeaway' : 'dine-in',
      pickupTime: takeawayOrder?.pickupData?.pickupTime || null,
      scheduledFor: takeawayOrder?.pickupData?.scheduledFor || null,
      customerName: takeawayOrder?.guestData?.name || null,
      customerPhone: takeawayOrder?.guestData?.phone || null,
      customerEmail: takeawayOrder?.guestData?.email || null
    };

    try {
      const result = await api.createOrder(orderData);
      
      // Load the order
      const order = await api.getOrder(result.orderId);
      setCurrentOrder(order);
    
    // Calculate and award loyalty points for paid orders (not cash or pay later)
    if (currentUser && !(paymentData.paymentMethod === 'cash' || paymentData.payLater)) {
      try {
        // Calculate points earned from this order
        const orderTotal = order.items.reduce((sum: number, item: any) => sum + (item.price * item.quantity), 0) + (order.tip || 0);
        const pointsEarned = Math.floor(orderTotal); // €1 = 1 point
        
        console.log('Awarding loyalty points:', { orderTotal, pointsEarned, restaurantId: restaurant?.id, restaurantName: vendorSettings?.restaurantName || restaurant?.name });
        
        // Update restaurantLoyalty array
        const updatedRestaurantLoyalty = [...(currentUser.restaurantLoyalty || [])];
        const restaurantIndex = updatedRestaurantLoyalty.findIndex(
          (rl: any) => rl.restaurantId === restaurant?.id || rl.restaurantName === (vendorSettings?.restaurantName || restaurant?.name)
        );
        
        if (restaurantIndex >= 0) {
          // Update existing restaurant loyalty
          updatedRestaurantLoyalty[restaurantIndex] = {
            ...updatedRestaurantLoyalty[restaurantIndex],
            points: (updatedRestaurantLoyalty[restaurantIndex].points || 0) + pointsEarned
          };
        } else {
          // Create new restaurant loyalty entry
          updatedRestaurantLoyalty.push({
            restaurantId: restaurant?.id || 'unknown',
            restaurantName: vendorSettings?.restaurantName || restaurant?.name || 'Restaurant',
            restaurantLogo: vendorSettings?.logo || restaurant?.logo || undefined,
            points: pointsEarned,
            minimumRedemption: vendorSettings?.minimumRedemption || 100,
            redemptionRate: vendorSettings?.redemptionRate || 0.05,
            transactions: []
          });
        }
        
        // Update customer in database
        await api.updateCustomer(currentUser.id, { restaurantLoyalty: updatedRestaurantLoyalty });
        
        // Update local state
        const updatedUser = { ...currentUser, restaurantLoyalty: updatedRestaurantLoyalty };
        setCurrentUser(updatedUser);
        
        // Emit event to update user in other components
        window.dispatchEvent(new CustomEvent('user-updated', { detail: updatedUser }));
        
        console.log('Loyalty points awarded successfully:', updatedRestaurantLoyalty);
      } catch (error) {
        console.error('Error awarding loyalty points:', error);
        // Don't fail the order if loyalty points fail
      }
    }
    
    // Clear basket
    setBasketItems([]);
    
    // Add to pending or active orders based on payment type
    if (paymentData.paymentMethod === 'cash' || paymentData.payLater) {
      // Add to pending orders if paying later or cash
      setPendingOrders(prev => [...prev, { ...order, paymentPending: true, status: 'received' }]);
      
      // ALSO add to active orders - kitchen should see all orders regardless of payment status
      setActiveOrders(prev => [...prev, order]);
      
      toast.success('Order submitted! Payment pending.');
      // DON'T navigate - success screen will handle it
    } else {
      // Paid now - remove from pending and add to active orders
      setPendingOrders(prev => prev.filter(o => o.id !== order.id));
      
      // Add to active orders (being prepared/delivered)
      setActiveOrders(prev => [...prev, order]);
      
      toast.success('Order submitted!');
      
      // Add to order history
      setOrderHistory(prev => [...prev, order]);
      
      // DON'T navigate - success screen will handle it
    }
    } catch (error: any) {
      console.error('Error creating order:', error);
      toast.error(error.message || 'Failed to create order. Please try again.');
      // Don't clear basket or navigate on error - let user retry
    }
  };

  const handleWriteReview = () => {
    setScreen('review');
  };

  const handleReviewSubmit = async (data: any) => {
    try {
      if (currentOrder) {
        await api.createOrderReview(currentOrder.id, {
          ...data,
          customerId: currentUser?.id || guestId
        });
        toast.success('Thanks — your review is submitted for moderation.');
        setScreen('menu');
      }
    } catch (error) {
      console.error('Error submitting review:', error);
      toast.error('Failed to submit review');
    }
  };
  
  const handleTakeawayStart = async (restaurantIdParam: string, guestData: any, pickupData: any) => {
    // Store takeaway data
    setTakeawayOrder({ guestData, pickupData });
    
    // Load restaurant data if not already loaded
    if (!restaurant) {
      try {
        const restaurantData = await api.getRestaurant(restaurantIdParam);
        setRestaurant(restaurantData);
        
        const menuData = await api.getMenu(restaurantIdParam);
        setMenu(menuData);
        
        const settings = await api.getVendorSettings(restaurantIdParam);
        setVendorSettings(settings);
      } catch (error) {
        console.error('Error loading restaurant data:', error);
        toast.error('Failed to load restaurant');
        return;
      }
    }
    
    // Create a virtual session for takeaway (no table number needed)
    const sessionData = await api.createSession(
      restaurantIdParam,
      'TAKEAWAY', // Special table ID for takeaway orders
      2, // Default to 2 people to enable multi-user basket interface
      false // No shared basket for takeaway
    );
    
    setSession({ 
      ...sessionData, 
      numPeople: 2, // Enable multi-user basket interface for takeaway
      sharedBasket: false,
      tableNumber: 'TAKEAWAY'
    });
    
    // Join session as guest or logged-in user
    if (currentUser) {
      await api.joinSession(sessionData.sessionId, currentUser.id);
    } else {
      await api.joinSession(sessionData.sessionId, undefined, guestId, guestData.name);
    }
    
    // Navigate to menu
    setScreen('menu');
    toast.success('🛍️ Takeaway order started! Add items to your basket.');
  };

  // View switching based on URL hash for demo purposes
  useEffect(() => {
    const hash = window.location.hash.slice(1);
    if (hash === 'vendor') {
      setScreen('vendor-dashboard');
    }

    // Listen for order status view event
    const handleViewOrderStatus = (e: any) => {
      const order = e.detail;
      setCurrentOrder(order);
      setScreen('order-tracking');
    };

    window.addEventListener('viewOrderStatus', handleViewOrderStatus);
    return () => window.removeEventListener('viewOrderStatus', handleViewOrderStatus);
  }, []);

  // Load customer orders when navigating to order history
  useEffect(() => {
    const loadCustomerOrders = async () => {
      if (screen === 'order-history' && (currentUser?.id || guestId)) {
        try {
          const customerId = currentUser?.id || guestId;
          console.log('=== Loading customer orders ===');
          console.log('Customer ID:', customerId);
          console.log('Is guest?', !currentUser);
          
          const orderIds = await api.getCustomerOrders(customerId);
          console.log('Order IDs returned from API:', orderIds);
          
          const orderDetails = await Promise.all(
            orderIds.slice(0, 20).map((id: string) => api.getOrder(id))
          );
          console.log('Loaded customer orders:', orderDetails);
          setCustomerOrders(orderDetails);
        } catch (error) {
          console.error('Error loading customer orders:', error);
        }
      }
    };
    loadCustomerOrders();
  }, [screen, currentUser, guestId]);

  return (
    <>
      <Toaster position="top-center" />
      
      {!restaurant && screen !== 'vendor-dashboard' && screen !== 'platform-home' && (
        <div className="min-h-screen flex items-center justify-center">
          <div className="text-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500 mx-auto"></div>
            <p className="mt-4 text-gray-600">Loading restaurant...</p>
          </div>
        </div>
      )}
      
      {screen === 'platform-home' && (
        <TavloPlatformPage 
          onNavigateToBrowse={() => setScreen('browse')} 
          onNavigateToVendorOnboarding={() => setScreen('vendor-onboarding')}
          onNavigateToAccount={() => setScreen('account-page')}
        />
      )}
      
      {screen === 'browse' && (
        <PlatformApp 
          onTakeawayStart={handleTakeawayStart}
          onBackToPlatform={() => setScreen('platform-home')}
        />
      )}
      
      {screen === 'qr-landing' && restaurant && (
        <QRLanding
          restaurantName={vendorSettings?.restaurantName || restaurant.name}
          tableNumber={tableNumber}
          cuisineTag={restaurant.cuisineTag}
          restaurantInfo={{
            address: vendorSettings?.address,
            phone: vendorSettings?.phone,
            logo: vendorSettings?.logo,
            coverPhoto: vendorSettings?.coverPhoto,
            businessHours: vendorSettings?.businessHours,
            rating: 4.8,
            description: vendorSettings?.description
          }}
          vendorSettings={vendorSettings}
          onContinue={handleQRContinue}
        />
      )}

      {screen === 'auth' && (
        <AuthScreen
          mode={authMode}
          onSuccess={handleAuthSuccess}
          onBack={() => setScreen('qr-landing')}
          vendorSettings={vendorSettings}
          requireDataConsent={true}
        />
      )}

      {screen === 'menu' && restaurant && menu && (
        <>
          <MenuList
            restaurantName={vendorSettings?.restaurantName || restaurant.name}
            menu={menu}
            onDishClick={handleDishClick}
            onCallWaiter={handleCallWaiter}
            basketCount={basketItems.length}
            pendingOrdersCount={pendingOrders.length}
            activeOrdersCount={activeOrders.length}
            onViewBasket={() => setScreen('basket')}
            onViewHistory={() => setScreen('order-history')}
            onViewProfile={() => setScreen('account-page')}
            onViewActiveOrders={() => {
              if (activeOrders.length === 1) {
                // Single order - go directly to tracking
                setCurrentOrder(activeOrders[0]);
                setScreen('order-tracking');
              } else if (activeOrders.length > 1) {
                // Multiple orders - show list to select
                setScreen('active-orders-list');
              }
            }}
            onQuickAdd={(item, quantity) => {
              // Quick add to basket without opening dish details
              const newItem = {
                ...item,
                id: crypto.randomUUID(), // Generate unique basket item ID (override menu item id)
                menuItemId: item.id, // Store original menu item ID for comparison
                quantity,
                specialInstructions: '',
                modifiers: [],
                addedBy: currentUser?.id || guestId
              };
              
              // Check if an identical item already exists in basket
              const existingItemIndex = basketItems.findIndex(basketItem => 
                areItemsIdentical(basketItem, newItem)
              );
              
              let newItems;
              if (existingItemIndex !== -1) {
                // Identical item exists - increase its quantity
                newItems = basketItems.map((item, index) => 
                  index === existingItemIndex 
                    ? { ...item, quantity: item.quantity + quantity }
                    : item
                );
                toast.success(`Added ${quantity}x ${item.name} to basket (combined with existing)`);
              } else {
                // New item - add to basket
                newItems = [...basketItems, newItem];
                toast.success(`Added ${quantity}x ${item.name} to basket`);
              }
              
              setBasketItems(newItems);
              
              // Update session if exists
              if (session) {
                api.updateSessionItems(session.sessionId, newItems);
              }
            }}
            vendorSettings={vendorSettings}
            showNutrition={showNutrition}
            onToggleNutrition={() => setShowNutrition(!showNutrition)}
            sessionPin={sessionPin}
          />
        </>
      )}

      {currentDish && (
        <DishDetails
          dish={currentDish}
          onClose={() => setCurrentDish(null)}
          onAddToBasket={(dish, quantity, specialInstructions, modifiers = []) => {
            const newItem = {
              ...dish,
              id: crypto.randomUUID(), // Generate unique basket item ID (override menu item id)
              menuItemId: dish.id, // Store original menu item ID for comparison
              quantity,
              specialInstructions,
              modifiers,
              addedBy: currentUser?.id || guestId
            };
            
            // Check if an identical item already exists in basket
            const existingItemIndex = basketItems.findIndex(basketItem => 
              areItemsIdentical(basketItem, newItem)
            );
            
            let newItems;
            if (existingItemIndex !== -1) {
              // Identical item exists - increase its quantity
              newItems = basketItems.map((item, index) => 
                index === existingItemIndex 
                  ? { ...item, quantity: item.quantity + quantity }
                  : item
              );
              toast.success(`Added to basket (combined with existing)`);
            } else {
              // New item - add to basket
              newItems = [...basketItems, newItem];
              toast.success('Added to basket');
            }
            
            setBasketItems(newItems);
            
            // Update session
            if (session) {
              api.updateSessionItems(session.sessionId, newItems);
            }
          }}
          currencySymbol={vendorSettings?.currency === 'EUR' ? '€' : vendorSettings?.currency === 'USD' ? '$' : vendorSettings?.currency === 'GBP' ? '£' : vendorSettings?.currency === 'CHF' ? 'Fr.' : '€'}
          showNutrition={showNutrition}
        />
      )}

      {screen === 'basket' && (
        <BasketView
          items={basketItems}
          pendingOrders={pendingOrders}
          onBack={() => setScreen('menu')}
          onUpdateQuantity={(itemId, quantity) => {
            if (quantity === 0) {
              setBasketItems(basketItems.filter(item => item.id !== itemId));
            } else {
              setBasketItems(basketItems.map(item => 
                item.id === itemId ? { ...item, quantity } : item
              ));
            }
          }}
          onRemoveItem={(itemId) => {
            setBasketItems(basketItems.filter(item => item.id !== itemId));
          }}
          onCheckout={handleSubmitOrder}
          onPayForUsers={(userIds, paymentType) => {
            // Handle multi-user payment
            // BasketView has demo users with IDs 'user-1' and 'user-2'
            // 'user-1' has first half of items, 'user-2' has second half
            
            // Filter basketItems to only include selected users' items
            let selectedItems: any[] = [];
            if (userIds.includes('user-1')) {
              // Add user-1's items (first half)
              selectedItems = [...selectedItems, ...basketItems.slice(0, Math.ceil(basketItems.length / 2))];
            }
            if (userIds.includes('user-2')) {
              // Add user-2's items (second half)
              selectedItems = [...selectedItems, ...basketItems.slice(Math.ceil(basketItems.length / 2))];
            }
            
            // If somehow no users selected or empty basket, use all items as fallback
            if (selectedItems.length === 0 && basketItems.length > 0) {
              selectedItems = basketItems;
            }
            
            console.log('=== Multi-User Payment ===');
            console.log('Selected user IDs:', userIds);
            console.log('All basket items:', basketItems);
            console.log('Filtered selected items:', selectedItems);
            
            if (paymentType === 'now') {
              // Pay Now: Temporarily set basketItems to only selected items
              const originalBasketItems = [...basketItems];
              setBasketItems(selectedItems);
              
              // Track that we came from multi-user modal so we can return to it
              setShowMultiUserPaymentModal(true);
              
              // Small delay to ensure state updates
              setTimeout(() => {
                handleSubmitOrder();
                // After order is submitted, basketItems will be cleared by handlePaymentComplete
              }, 0);
            } else {
              // Pay Later: Submit order as unpaid with only selected users' items
              const unpaidOrder = {
                id: `order_${Date.now()}`,
                items: selectedItems,
                total: selectedItems.reduce((sum, item) => {
                  const itemTotal = item.price * item.quantity;
                  const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
                    mSum + (m.price * item.quantity), 0) || 0;
                  return sum + itemTotal + modifiersTotal;
                }, 0),
                status: 'Pending Payment',
                paymentStatus: 'unpaid',
                selectedUsers: userIds,
                timestamp: new Date().toISOString()
              };
              
              // Add to pending orders
              setPendingOrders([...pendingOrders, unpaidOrder]);
              
              // Clear basket
              setBasketItems([]);
              
              // Show success message
              toast.success('Order submitted! Pay later at the table.');
              
              // Go back to menu
              setScreen('menu');
            }
          }}
          onPayPendingOrder={(orderId) => {
            // Find the pending order and go to payment
            const pendingOrder = pendingOrders.find(o => o.id === orderId);
            if (pendingOrder) {
              // Validate order amount before allowing payment
              const orderTotal = pendingOrder.items.reduce((sum: number, item: any) => {
                const itemTotal = item.price * item.quantity;
                const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
                  mSum + (m.price * item.quantity), 0) || 0;
                return sum + itemTotal + modifiersTotal;
              }, 0);

              const minOrderAmount = vendorSettings?.minOrderAmount || 0;
              const maxOrderAmount = vendorSettings?.maxOrderAmount || 10000;

              if (orderTotal < minOrderAmount) {
                toast.error(`Cannot pay: Minimum order amount is €${minOrderAmount.toFixed(2)}`);
                return;
              }

              if (orderTotal > maxOrderAmount) {
                toast.error(`Cannot pay: Maximum order amount is €${maxOrderAmount.toFixed(2)}`);
                return;
              }

              setCurrentOrder(pendingOrder);
              setIsPayingPendingOrder(true);
              setScreen('payment');
            }
          }}
          onRemovePendingOrder={(orderId) => {
            setPendingOrders(pendingOrders.filter(o => o.id !== orderId));
          }}
          vendorSettings={vendorSettings}
          takeawayOrder={takeawayOrder}
          session={session}
          sessionPin={sessionPin}
          onViewHistory={() => setScreen('order-history')}
          onCallWaiter={handleCallWaiter}
          showPaymentModal={showMultiUserPaymentModal}
          onClosePaymentModal={() => setShowMultiUserPaymentModal(false)}
        />
      )}

      {screen === 'payment' && (
        <PaymentFlow
          total={currentOrder ? 
            // If it's a split payment with some paid, show only the remaining amount
            (currentOrder.splitPayment && currentOrder.splitPayment.pendingTotal > 0) ?
              currentOrder.splitPayment.pendingTotal :
            // Otherwise calculate total from items
            currentOrder.items.reduce((sum: number, item: any) => {
              const subtotal = sum + (item.price * item.quantity);
              const modifiersPrice = item.modifiers?.reduce((mSum: number, m: any) => 
                mSum + (m.price * item.quantity), 0) || 0;
              return subtotal + modifiersPrice;
            }, 0) : 
            basketItems.reduce((sum, item) => {
              const subtotal = sum + (item.price * item.quantity);
              const modifiersPrice = item.modifiers?.reduce((mSum: number, m: any) => 
                mSum + (m.price * item.quantity), 0) || 0;
              return subtotal + modifiersPrice;
            }, 0)
          } // VAT and service fees already included in item prices
          numPeople={session?.numPeople || 2}
          restaurantName={vendorSettings?.restaurantName || restaurant?.name || 'Restaurant'}
          orderItems={currentOrder?.items || basketItems}
          skipChoice={isPayingPendingOrder} // Skip the choice screen if paying a pending order
          onPaymentComplete={handlePaymentComplete}
          onBack={() => {
            setIsPayingPendingOrder(false);
            setScreen('basket');
            // Don't close the multi-user modal - let it stay open when we go back
            // setShowMultiUserPaymentModal remains true
          }}
          onTrackOrder={() => {
            // currentOrder will be set by onPaymentComplete before this is called
            // Use setTimeout to ensure state update has completed
            setTimeout(() => setScreen('order-tracking'), 0);
          }}
          onViewReceipt={() => {
            // currentOrder will be set by onPaymentComplete before this is called
            // Use setTimeout to ensure state update has completed
            setTimeout(() => setScreen('receipt'), 0);
          }}
          onGoToMenu={() => setScreen('menu')}
          vendorSettings={vendorSettings}
          customerLoyaltyPoints={
            currentUser?.restaurantLoyalty?.find(
              (rl: any) => rl.restaurantId === restaurant?.id || rl.restaurantName === (vendorSettings?.restaurantName || restaurant?.name)
            )?.points || 0
          }
          customerId={currentUser?.id || ''}
          isTakeaway={!!takeawayOrder}
        />
      )}

      {screen === 'order-tracking' && currentOrder && (
        <OrderTracking
          order={currentOrder}
          onCallWaiter={handleCallWaiter}
          onWriteReview={handleWriteReview}
          onOrderMore={() => setScreen('menu')}
          onOrderUpdate={(updatedOrder) => setCurrentOrder(updatedOrder)}
          onBack={() => setScreen('active-orders-list')}
          onViewTracking={() => setScreen('active-orders-list')}
          vendorSettings={vendorSettings}
        />
      )}

      {screen === 'active-orders-list' && (
        <ActiveOrdersList
          orders={activeOrders}
          onBack={() => setScreen('menu')}
          onSelectOrder={(order) => {
            setCurrentOrder(order);
            setScreen('order-tracking');
          }}
          onOrdersRefresh={(updatedOrders) => setActiveOrders(updatedOrders)}
        />
      )}

      {screen === 'review' && currentOrder && (
        <ItemReviewForm
          order={currentOrder}
          onBack={() => {
            // Go back to order tracking if coming from active orders, otherwise to order history
            const isActiveOrder = activeOrders.some(o => o.id === currentOrder.id);
            setScreen(isActiveOrder ? 'order-tracking' : 'order-history');
          }}
          onSubmit={async (reviews) => {
            try {
              const isEditing = currentOrder.reviews && currentOrder.reviews.length > 0;
              
              console.log('=== Submitting reviews ===');
              console.log('Current order ID:', currentOrder.id);
              console.log('Reviews to submit:', reviews);
              
              // Submit each review
              for (const review of reviews) {
                console.log('Submitting review:', review);
                const result = await api.createOrderReview(currentOrder.id, {
                  rating: review.rating,
                  text: review.comment,
                  photos: review.photos || [],
                  customerId: currentUser?.id || guestId,
                  itemId: review.itemId,
                  itemName: review.itemName,
                  customerName: currentUser?.name || 'Guest',
                  isGuest: !currentUser,
                  type: review.type
                });
                console.log('Review submission result:', result);
              }
              
              console.log('=== All reviews submitted, refreshing order data ===');
              
              // Refresh the current order to get updated reviews
              const refreshedOrder = await api.getOrder(currentOrder.id);
              console.log('Refreshed current order:', refreshedOrder);
              console.log('Refreshed order has reviews?', refreshedOrder.reviews);
              setCurrentOrder(refreshedOrder);
              
              // Update the order in activeOrders if it exists there
              setActiveOrders(prev => 
                prev.map(o => o.id === refreshedOrder.id ? refreshedOrder : o)
              );
              
              toast.success(
                isEditing 
                  ? 'Your reviews have been updated!' 
                  : 'Thank you! Your reviews have been published.'
              );
              
              // Refresh order history to show updated reviews
              if (currentUser?.id || guestId) {
                const customerId = currentUser?.id || guestId;
                const orderIds = await api.getCustomerOrders(customerId);
                console.log('Customer order IDs:', orderIds);
                const orderDetails = await Promise.all(
                  orderIds.slice(0, 20).map((id: string) => api.getOrder(id))
                );
                console.log('Refreshed customer orders:', orderDetails);
                setCustomerOrders(orderDetails);
              }
              
              // Go back to order tracking if coming from active orders, otherwise to order history
              const isActiveOrder = activeOrders.some(o => o.id === currentOrder.id);
              setScreen(isActiveOrder ? 'order-tracking' : 'order-history');
            } catch (error) {
              console.error('Error submitting reviews:', error);
              toast.error('Failed to submit reviews');
            }
          }}
        />
      )}

      {screen === 'vendor-dashboard' && (
        <VendorDashboard vendorId={restaurantId} />
      )}

      {screen === 'order-history' && (
        <OrderHistory 
          orders={customerOrders.length > 0 ? customerOrders : orderHistory}
          customerId={currentUser?.id || guestId}
          user={currentUser} // Pass user object for current loyalty points balance
          onBack={() => setScreen('menu')}
          vendorSettings={vendorSettings}
          onWriteReview={(order) => {
            // Set the order as current order for review
            console.log('=== OrderHistory: onWriteReview called ===');
            console.log('Order received:', order);
            console.log('Order has originalOrder?', !!order.originalOrder);
            console.log('originalOrder:', order.originalOrder);
            console.log('originalOrder.reviews:', order.originalOrder?.reviews);
            console.log('order.reviews:', order.reviews);
            
            const orderToSet = order.originalOrder || order;
            console.log('Setting currentOrder to:', orderToSet);
            console.log('Has reviews?', orderToSet.reviews);
            
            setCurrentOrder(orderToSet);
            setScreen('review');
          }}
          onReorder={(order) => {
            // Add all items from the historical order to the basket
            const reorderedItems = order.originalOrder?.items || order.items || [];
            const newBasketItems = reorderedItems.map((item: any) => ({
              id: crypto.randomUUID(),
              ...item,
              addedBy: currentUser?.id || guestId,
              // Make sure to preserve all the item details
              name: item.name,
              price: item.price,
              quantity: item.quantity,
              modifiers: item.modifiers || [],
              specialInstructions: item.specialInstructions || ''
            }));
            
            setBasketItems(prev => [...prev, ...newBasketItems]);
            
            // Update session if exists
            if (session) {
              api.updateSessionItems(session.sessionId, [...basketItems, ...newBasketItems]);
            }
            
            toast.success(`${newBasketItems.length} item${newBasketItems.length !== 1 ? 's' : ''} added to basket`);
            setScreen('basket');
          }}
          session={session}
          sessionPin={sessionPin}
          basketCount={basketItems.length}
          pendingOrdersCount={pendingOrders.length}
          onViewBasket={() => setScreen('basket')}
          onCallWaiter={handleCallWaiter}
        />
      )}

      {screen === 'account-page' && (
        <AccountPage
          user={currentUser || { id: guestId, name: 'Guest User', email: '', loyaltyPoints: 0 }}
          onBack={() => setScreen('menu')}
          onLogout={() => {
            setCurrentUser(null);
            setScreen('qr-landing');
            toast.success('Signed out successfully');
          }}
          onRestaurantClick={(restaurantId) => {
            // Navigate to restaurant (could extend this later)
            toast.info(`Restaurant ${restaurantId} selected`);
          }}
        />
      )}

      {screen === 'account-settings' && (
        <AccountSettings
          user={currentUser}
          onBack={() => setScreen('account-page')}
          onUpdateProfile={(data) => {
            setCurrentUser({ ...currentUser, ...data });
            toast.success('Profile updated successfully');
          }}
          onDeleteAccount={() => {
            setCurrentUser(null);
            setScreen('qr-landing');
            toast.success('Account deleted');
          }}
        />
      )}

      {screen === 'receipt' && currentOrder && (
        <Receipt
          order={{
            ...currentOrder,
            restaurantName: vendorSettings?.restaurantName || restaurant?.name || 'Restaurant Name',
            tableNumber: session?.tableNumber || tableNumber
          }}
          onBack={() => setScreen('order-tracking')}
          vendorSettings={vendorSettings}
        />
      )}

      {screen === 'brand-assets' && (
        <LogoConceptsShowcase />
      )}

      {screen === 'admin-dashboard' && (
        <AdminApp />
      )}

      {screen === 'ai-showcase' && (
        <AIShowcase />
      )}

      {screen === 'vendor-onboarding' && (
        <VendorOnboardingFlow />
      )}

      {screen === 'admin-vendor-management' && (
        <AdminVendorManagement />
      )}

      {screen === 'vendor-auth-demo' && (
        <VendorAuthDemo />
      )}

      {screen === 'jira-csv' && (
        <JiraCSVGenerator />
      )}

      {screen === 'qr-landing-test' && (
        <QRLandingTestHarness />
      )}

      {/* Dev controls - Compact dropdown mode switcher */}
      <div className="fixed bottom-4 left-4 z-50">
        <div className="relative">
          {/* Dropdown button */}
          <button
            onClick={() => setIsModeSwitcherOpen(!isModeSwitcherOpen)}
            className="fixed bottom-4 left-4 z-50 bg-white rounded-lg shadow-lg px-3 py-2 text-sm flex items-center gap-2 hover:bg-gray-50 transition-colors"
          >
            <span>
              {screen === 'platform-home' && '🏠'}
              {screen === 'browse' && '🔍'}
              {screen === 'qr-landing' && '📱'}
              {screen === 'qr-landing-test' && '🧪'}
              {screen === 'vendor-dashboard' && '👨‍💼'}
              {screen === 'brand-assets' && '🎨'}
              {screen === 'admin-dashboard' && '🛠️'}
              {screen === 'ai-showcase' && '🤖'}
            </span>
            <span className="text-gray-700">
              {screen === 'platform-home' && 'Platform'}
              {screen === 'browse' && 'Browse'}
              {screen === 'qr-landing' && 'QR Order'}
              {screen === 'qr-landing-test' && 'QR Test'}
              {screen === 'vendor-dashboard' && 'Vendor'}
              {screen === 'brand-assets' && 'Brand'}
              {screen === 'admin-dashboard' && 'Admin'}
              {screen === 'ai-showcase' && 'AI'}
            </span>
            <ChevronDown className={`w-4 h-4 text-gray-500 transition-transform ${isModeSwitcherOpen ? 'rotate-0' : 'rotate-180'}`} />
          </button>

          {/* Dropdown menu */}
          {isModeSwitcherOpen && (
            <>
              {/* Backdrop to close on outside click */}
              <div 
                className="fixed inset-0 z-10" 
                onClick={() => setIsModeSwitcherOpen(false)}
              />
              
              <div className="absolute bottom-full left-0 mb-1 bg-white rounded-lg shadow-xl border border-gray-200 py-1 min-w-[160px] z-20">
                <button
                  onClick={() => {
                    setScreen('platform-home');
                    window.location.hash = '';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'platform-home' ? 'bg-orange-50 text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    🏠 Platform
                    {screen === 'platform-home' && <CheckCircle className="w-4 h-4 ml-auto" />}
                  </span>
                </button>
                <button
                  onClick={() => {
                    setScreen('qr-landing');
                    window.location.hash = '';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'qr-landing' ? 'bg-orange-50 text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    📱 Customer
                  </span>
                </button>
                <button
                  onClick={() => {
                    setScreen('qr-landing-test');
                    window.location.hash = '';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'qr-landing-test' ? 'bg-blue-50 text-blue-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    🧪 QR Test
                    {screen === 'qr-landing-test' && <CheckCircle className="w-4 h-4 ml-auto" />}
                  </span>
                </button>
                <button
                  onClick={() => {
                    setScreen('vendor-dashboard');
                    window.location.hash = 'vendor';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'vendor-dashboard' ? 'bg-orange-50 text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    👨‍💼 Vendor
                    {screen === 'vendor-dashboard' && <CheckCircle className="w-4 h-4 ml-auto" />}
                  </span>
                </button>
                <button
                  onClick={() => {
                    setScreen('brand-assets');
                    window.location.hash = 'branding';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'brand-assets' ? 'bg-orange-50 text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    🎨 Brand Assets
                    {screen === 'brand-assets' && <CheckCircle className="w-4 h-4 ml-auto" />}
                  </span>
                </button>
                <button
                  onClick={() => {
                    setScreen('admin-dashboard');
                    window.location.hash = 'admin';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'admin-dashboard' ? 'bg-orange-50 text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    🛠️ Admin
                    {screen === 'admin-dashboard' && <CheckCircle className="w-4 h-4 ml-auto" />}
                  </span>
                </button>
                <button
                  onClick={() => {
                    setScreen('ai-showcase');
                    window.location.hash = 'ai';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'ai-showcase' ? 'bg-orange-50 text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    🤖 AI Showcase
                    {screen === 'ai-showcase' && <CheckCircle className="w-4 h-4 ml-auto" />}
                  </span>
                </button>
                <button
                  onClick={() => {
                    setScreen('vendor-onboarding');
                    window.location.hash = 'vendor-onboarding';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'vendor-onboarding' ? 'bg-orange-50 text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    🚀 Vendor Onboarding
                    {screen === 'vendor-onboarding' && <CheckCircle className="w-4 h-4 ml-auto" />}
                  </span>
                </button>
                <button
                  onClick={() => {
                    setScreen('vendor-auth-demo');
                    window.location.hash = 'vendor-auth-demo';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'vendor-auth-demo' ? 'bg-orange-50 text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    🔐 Vendor Auth Demo
                    {screen === 'vendor-auth-demo' && <CheckCircle className="w-4 h-4 ml-auto" />}
                  </span>
                </button>
                <button
                  onClick={() => {
                    setScreen('jira-csv');
                    window.location.hash = 'jira-csv';
                    setIsModeSwitcherOpen(false);
                  }}
                  className={`block w-full text-left px-4 py-2 hover:bg-gray-100 transition-colors ${
                    screen === 'jira-csv' ? 'bg-orange-50 text-orange-600' : 'text-gray-700'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    📊 Jira CSV Generator
                    {screen === 'jira-csv' && <CheckCircle className="w-4 h-4 ml-auto" />}
                  </span>
                </button>
              </div>
            </>
          )}
        </div>
      </div>
    </>
  );
}

export default function App() {
  return (
    <LanguageProvider>
      <AccessibilityProvider>
        <AppContent />
      </AccessibilityProvider>
    </LanguageProvider>
  );
}