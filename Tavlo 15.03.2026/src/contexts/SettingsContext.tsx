import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { api } from '../utils/api';

interface VendorSettings {
  // Business Information
  restaurantName: string;
  description: string;
  businessRegNumber: string;
  vatNumber: string;
  email: string;
  phone: string;
  website: string;
  address: string;
  companyType: string;
  
  // Business Hours
  businessHours: {
    [key: string]: {
      open: string;
      close: string;
      closed: boolean;
    };
  };
  
  // Payment Settings
  acceptApplePay: boolean;
  acceptGooglePay: boolean;
  acceptCard: boolean;
  acceptCash: boolean;
  currency: string;
  stripeEnabled: boolean;
  
  // Tax & Compliance
  vatRate: number;
  serviceFeeRate: number;
  invoicePrefix: string;
  nextInvoiceNumber: number;
  autoGenerateReceipts: boolean;
  
  // Table Management
  numberOfTables: number;
  tablePrefix: string;
  enableSharedBasket: boolean;
  maxGuestsPerTable: number;
  
  // Ordering Settings
  autoAcceptOrders: boolean;
  estimatedPrepTime: number;
  maxOrdersPerSlot: number;
  allowGuestOrdering: boolean;
  requirePhoneNumber: boolean;
  minOrderAmount: number;
  maxOrderAmount: number;
  
  // Notification Settings
  emailNewOrder: boolean;
  emailReview: boolean;
  smsNewOrder: boolean;
  pushNewOrder: boolean;
  pushOrderReady: boolean;
  notificationEmail: string;
  
  // Review Settings
  enableReviews: boolean;
  moderateReviews: boolean;
  minOrderToReview: number;
  showReviewsPublicly: boolean;
  allowAnonymousReviews: boolean;
  
  // Language Settings
  defaultLanguage: string;
  supportedLanguages: string[];
  dateFormat: string;
  timeFormat: string;
  
  // Loyalty Settings
  enableLoyalty: boolean;
  pointsPerEuro: number;
  minimumRedemption: number;
  pointsExpiry: number;
  enableTiers: boolean;
  
  // Privacy Settings
  dataRetentionDays: number;
  allowDataExport: boolean;
  gdprCompliant: boolean;
  showInTopCustomers: boolean;
}

interface SettingsContextType {
  settings: VendorSettings | null;
  loading: boolean;
  refreshSettings: () => Promise<void>;
  getCurrencySymbol: () => string;
  formatPrice: (amount: number) => string;
  isPaymentMethodEnabled: (method: string) => boolean;
}

const SettingsContext = createContext<SettingsContextType | undefined>(undefined);

export function SettingsProvider({ children, vendorId }: { children: ReactNode; vendorId: string }) {
  const [settings, setSettings] = useState<VendorSettings | null>(null);
  const [loading, setLoading] = useState(true);

  const loadSettings = async () => {
    try {
      setLoading(true);
      const data = await api.getVendorSettings(vendorId);
      setSettings(data);
    } catch (error) {
      console.error('Failed to load settings:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (vendorId) {
      loadSettings();
    }
  }, [vendorId]);

  const getCurrencySymbol = () => {
    if (!settings) return '€';
    switch (settings.currency) {
      case 'EUR': return '€';
      case 'USD': return '$';
      case 'GBP': return '£';
      case 'CHF': return 'Fr.';
      default: return '€';
    }
  };

  const formatPrice = (amount: number) => {
    if (!settings) return `€${amount.toFixed(2)}`;
    const symbol = getCurrencySymbol();
    
    // Different formatting based on currency
    if (settings.currency === 'EUR') {
      return `€${amount.toFixed(2)}`;
    } else if (settings.currency === 'USD' || settings.currency === 'GBP') {
      return `${symbol}${amount.toFixed(2)}`;
    } else if (settings.currency === 'CHF') {
      return `${symbol} ${amount.toFixed(2)}`;
    }
    
    return `${symbol}${amount.toFixed(2)}`;
  };

  const isPaymentMethodEnabled = (method: string) => {
    if (!settings) return true; // Default to enabled if settings not loaded
    
    switch (method.toLowerCase()) {
      case 'apple-pay':
      case 'applepay':
        return settings.acceptApplePay;
      case 'google-pay':
      case 'googlepay':
        return settings.acceptGooglePay;
      case 'card':
      case 'credit-card':
      case 'debit-card':
        return settings.acceptCard;
      case 'cash':
        return settings.acceptCash;
      default:
        return true;
    }
  };

  return (
    <SettingsContext.Provider
      value={{
        settings,
        loading,
        refreshSettings: loadSettings,
        getCurrencySymbol,
        formatPrice,
        isPaymentMethodEnabled
      }}
    >
      {children}
    </SettingsContext.Provider>
  );
}

export function useSettings() {
  const context = useContext(SettingsContext);
  if (context === undefined) {
    throw new Error('useSettings must be used within a SettingsProvider');
  }
  return context;
}
