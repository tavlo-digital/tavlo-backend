import { useState, useEffect } from 'react';
import { Sidebar } from './Sidebar';
import { TopNav } from './TopNav';
import { Dashboard } from './Dashboard';
import { OrdersManagement } from './OrdersManagement';
import { MenuManagement } from './MenuManagement';
import { Analytics } from './Analytics';
import { ReviewsManagement } from './ReviewsManagement';
import { QRCodesManagement } from './QRCodesManagement';
import { Settings } from './Settings';
import { LoyaltyManagement } from './LoyaltyManagement';
import { VendorReservationsCalendar } from './VendorReservationsCalendar';
import { BillingSubscription } from './BillingSubscription';
import { FeatureGate } from './FeatureGate';
import { InventoryOverview } from './InventoryOverview';
import { LockedStateOverlay } from '../vendor-onboarding/LockedStateOverlay';
import { GoLiveModal } from '../vendor-onboarding/GoLiveModal';
import { api } from '../../utils/api';
import { projectId, publicAnonKey } from '../../utils/supabase/info';

const API_BASE = `https://${projectId}.supabase.co/functions/v1/make-server-1dccd8d3`;

type VendorScreen = 
  | 'home'
  | 'orders'
  | 'menu'
  | 'inventory'
  | 'analytics'
  | 'reviews'
  | 'qr-codes'
  | 'settings'
  | 'loyalty'
  | 'reservations'
  | 'billing';

interface VendorDashboardProps {
  vendorId: string;
  vendorStatus?: 'demo' | 'activated' | 'live';
  onActivateClick?: () => void;
  onLockedActionClick?: () => void;
  onGuidedTourClick?: () => void;
  onGoLiveClick?: () => void;
  onNavigate?: (screen: VendorScreen) => void;
  controlledScreen?: VendorScreen;
  initialIsLiveAndDiscoverable?: boolean;
  onVisibilityChange?: (isLive: boolean) => void;
  currentUser?: {
    name: string;
    email: string;
  };
  onLogout?: () => void;
}

export function VendorDashboard({ 
  vendorId, 
  vendorStatus, 
  onActivateClick, 
  onLockedActionClick,
  onGuidedTourClick,
  onGoLiveClick,
  onNavigate,
  controlledScreen,
  initialIsLiveAndDiscoverable,
  onVisibilityChange,
  currentUser,
  onLogout
}: VendorDashboardProps) {
  const [internalScreen, setInternalScreen] = useState<VendorScreen>('home');
  
  // Use controlled screen if provided, otherwise use internal state
  const currentScreen = controlledScreen !== undefined ? controlledScreen : internalScreen;

  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [restaurantSettings, setRestaurantSettings] = useState<any>(null);
  const [subscriptionStatus, setSubscriptionStatus] = useState<'active' | 'past_due' | 'suspended' | 'none'>('active');
  const [vendorPlan, setVendorPlan] = useState<'basic' | 'professional' | 'enterprise'>('professional');
  const [promotionPreFill, setPromotionPreFill] = useState<any>(null);
  const [showGoLiveModal, setShowGoLiveModal] = useState(false);
  const [canGoLive, setCanGoLive] = useState(false);
  const [isLiveAndDiscoverable, setIsLiveAndDiscoverable] = useState(initialIsLiveAndDiscoverable || false);

  // Update isLiveAndDiscoverable when prop changes
  useEffect(() => {
    if (initialIsLiveAndDiscoverable !== undefined) {
      setIsLiveAndDiscoverable(initialIsLiveAndDiscoverable);
    }
  }, [initialIsLiveAndDiscoverable]);

  // Default to 'live' if no vendorStatus provided (normal dashboard use)
  const effectiveVendorStatus = vendorStatus || 'live';
  const isLocked = effectiveVendorStatus !== 'live' && effectiveVendorStatus !== 'activated';
  const isOnboarding = vendorStatus !== undefined && vendorStatus !== 'live'; // Only show onboarding UI when explicitly in onboarding mode

  // Handle screen changes - work with both controlled and uncontrolled mode
  const handleScreenChange = (screen: VendorScreen) => {
    if (controlledScreen !== undefined) {
      // Controlled mode - notify parent
      if (onNavigate) {
        onNavigate(screen);
      }
    } else {
      // Uncontrolled mode - update internal state
      setInternalScreen(screen);
    }
  };

  useEffect(() => {
    async function loadSettings() {
      try {
        const settings = await api.getVendorSettings(vendorId);
        setRestaurantSettings(settings);
      } catch (error) {
        console.error('Failed to load restaurant settings:', error);
      }
    }
    loadSettings();
  }, [vendorId]);

  useEffect(() => {
    async function loadSubscriptionStatus() {
      try {
        const response = await fetch(`${API_BASE}/vendor/${vendorId}/subscription`, {
          headers: { 'Authorization': `Bearer ${publicAnonKey}` }
        });
        const data = await response.json();
        setSubscriptionStatus(data.status || 'active');
        // Also set the plan from subscription data if available
        if (data.plan) {
          setVendorPlan(data.plan);
        }
      } catch (error) {
        console.error('Failed to load subscription status:', error);
      }
    }
    loadSubscriptionStatus();
  }, [vendorId]);

  return (
    <div className="flex h-screen bg-gray-50">
      {/* Sidebar */}
      <Sidebar 
        activeView={currentScreen === 'home' ? 'dashboard' : currentScreen}
        onViewChange={(view) => {
          handleScreenChange(view === 'dashboard' ? 'home' : view as VendorScreen);
        }}
        isOpen={isSidebarOpen}
        onClose={() => setIsSidebarOpen(false)}
        restaurantLogo={restaurantSettings?.logo}
        restaurantName={restaurantSettings?.restaurantName}
        subscriptionStatus={subscriptionStatus}
        vendorStatus={vendorStatus}
      />

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top Navigation */}
        <TopNav 
          onMenuClick={() => setIsSidebarOpen(true)}
          restaurantName="La Bella Cucina"
          vendorId={vendorId}
          vendorStatus={vendorStatus}
          onActivateClick={onActivateClick || (() => {})}
          isLiveAndDiscoverable={isLiveAndDiscoverable}
          currentUser={currentUser}
          onLogout={onLogout}
        />

        {/* Demo: Plan Switcher - Only show during onboarding or when explicitly in demo mode */}
        {isOnboarding && (
          <div className="bg-yellow-50 border-b border-yellow-200 px-6 py-2 flex items-center justify-between">
            <span className="text-xs text-yellow-800">
              <strong>Demo Mode:</strong> Current Plan: <span className="font-semibold capitalize">{vendorPlan}</span>
            </span>
            <div className="flex gap-2">
              <button
                onClick={() => setVendorPlan('basic')}
                className={`px-3 py-1 text-xs rounded ${vendorPlan === 'basic' ? 'bg-yellow-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'}`}
              >
                Basic
              </button>
              <button
                onClick={() => setVendorPlan('professional')}
                className={`px-3 py-1 text-xs rounded ${vendorPlan === 'professional' ? 'bg-yellow-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'}`}
              >
                Professional
              </button>
              <button
                onClick={() => setVendorPlan('enterprise')}
                className={`px-3 py-1 text-xs rounded ${vendorPlan === 'enterprise' ? 'bg-yellow-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'}`}
              >
                Enterprise
              </button>
            </div>
          </div>
        )}

        {/* Content Area */}
        <main className="flex-1 overflow-y-auto">
          {currentScreen === 'home' && (
            <Dashboard 
              vendorId={vendorId} 
              onNavigate={(screen) => handleScreenChange(screen)}
              vendorStatus={vendorStatus}
              onActivateClick={onActivateClick}
              onLockedActionClick={onLockedActionClick}
              isLiveAndDiscoverable={isLiveAndDiscoverable}
            />
          )}
          {currentScreen === 'orders' && (
            <LockedStateOverlay isLocked={isLocked} onActivateClick={onActivateClick}>
              <OrdersManagement vendorId={vendorId} />
            </LockedStateOverlay>
          )}
          {currentScreen === 'menu' && (
            <LockedStateOverlay isLocked={isLocked} onActivateClick={onActivateClick}>
              <MenuManagement vendorId={vendorId} />
            </LockedStateOverlay>
          )}
          {currentScreen === 'inventory' && (
            <LockedStateOverlay isLocked={isLocked} onActivateClick={onActivateClick}>
              <InventoryOverview vendorId={vendorId} />
            </LockedStateOverlay>
          )}
          {currentScreen === 'analytics' && (
            <LockedStateOverlay isLocked={isLocked} onActivateClick={onActivateClick}>
              <Analytics vendorId={vendorId} onNavigate={(screen) => handleScreenChange(screen)} />
            </LockedStateOverlay>
          )}
          {currentScreen === 'reviews' && (
            <LockedStateOverlay isLocked={isLocked} onActivateClick={onActivateClick}>
              <ReviewsManagement vendorId={vendorId} />
            </LockedStateOverlay>
          )}
          {currentScreen === 'billing' && (
            <BillingSubscription vendorId={vendorId} vendorStatus={vendorStatus} />
          )}
          {currentScreen === 'qr-codes' && (
            <LockedStateOverlay isLocked={isLocked} onActivateClick={onActivateClick}>
              <QRCodesManagement vendorId={vendorId} />
            </LockedStateOverlay>
          )}
          {currentScreen === 'settings' && (
            <LockedStateOverlay isLocked={isLocked} onActivateClick={onActivateClick}>
              <Settings 
                vendorId={vendorId} 
                onNavigate={(screen) => handleScreenChange(screen as any)}
                initialIsLiveAndDiscoverable={isLiveAndDiscoverable}
                onVisibilityChange={(isLive) => {
                  setIsLiveAndDiscoverable(isLive);
                  if (onVisibilityChange) {
                    onVisibilityChange(isLive);
                  }
                }}
              />
            </LockedStateOverlay>
          )}
          {currentScreen === 'loyalty' && (
            <FeatureGate feature="loyalty" vendorPlan={vendorPlan}>
              <LockedStateOverlay isLocked={isLocked} onActivateClick={onActivateClick}>
                <LoyaltyManagement vendorId={vendorId} />
              </LockedStateOverlay>
            </FeatureGate>
          )}
          {currentScreen === 'reservations' && (
            <LockedStateOverlay isLocked={isLocked} onActivateClick={onActivateClick}>
              <div className="p-6">
                <div className="mb-6">
                  <h1 className="text-2xl mb-2">Reservations</h1>
                  <p className="text-gray-600">Manage table reservations and bookings</p>
                </div>
                <VendorReservationsCalendar vendorId={vendorId} />
              </div>
            </LockedStateOverlay>
          )}
        </main>
      </div>
    </div>
  );
}