import { Home, ShoppingBag, Menu, QrCode, Gift, BarChart3, Settings, X, ChefHat, Calendar, CreditCard, Package } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useState, useEffect } from 'react';

interface SidebarProps {
  activeView: string;
  onViewChange: (view: string) => void;
  isOpen: boolean;
  onClose: () => void;
  restaurantLogo?: string;
  restaurantName?: string;
  subscriptionStatus?: 'active' | 'past_due' | 'suspended' | 'none';
  vendorStatus?: 'demo' | 'activated' | 'live';
}

export function Sidebar({ activeView, onViewChange, isOpen, onClose, restaurantLogo, restaurantName, subscriptionStatus = 'active', vendorStatus = 'live' }: SidebarProps) {
  const menuItems = [
    { id: 'dashboard', label: 'Dashboard', icon: Home },
    { id: 'orders', label: 'Orders', icon: ShoppingBag },
    { id: 'reservations', label: 'Reservations', icon: Calendar },
    { id: 'menu', label: 'Menu Management', icon: Menu },
    { id: 'inventory', label: 'Inventory', icon: Package },
    { id: 'qr-codes', label: 'QR Codes', icon: QrCode },
    { id: 'loyalty', label: 'Loyalty', icon: Gift },
    { id: 'analytics', label: 'Analytics', icon: BarChart3 },
    { id: 'reviews', label: 'Reviews', icon: ChefHat },
    { 
      id: 'billing', 
      label: vendorStatus === 'live' ? 'Billing & Subscription' : 'Subscription & Activation',
      icon: CreditCard, 
      hasStatus: true 
    },
    { id: 'settings', label: 'Settings', icon: Settings },
  ];

  const getStatusColor = () => {
    switch (subscriptionStatus) {
      case 'active':
        return 'bg-green-500';
      case 'past_due':
        return 'bg-yellow-500';
      case 'suspended':
        return 'bg-red-500';
      default:
        return 'bg-gray-400';
    }
  };

  const getVendorStatusColor = () => {
    switch (vendorStatus) {
      case 'demo':
        return 'bg-blue-500';
      case 'activated':
        return 'bg-orange-500';
      case 'live':
        return 'bg-green-500';
      default:
        return 'bg-gray-400';
    }
  };

  return (
    <>
      {/* Mobile Overlay */}
      {isOpen && (
        <div 
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={onClose}
        />
      )}

      {/* Sidebar */}
      <aside 
        className={`
          fixed lg:static inset-y-0 left-0 z-50
          w-64 bg-white border-r border-gray-200
          transform transition-transform duration-300 ease-in-out
          ${isOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}
        `}
      >
        <div className="h-full flex flex-col">
          {/* Logo/Header */}
          <div className="h-16 flex items-center justify-between px-6 border-b border-gray-200">
            <div className="flex items-center gap-2">
              {restaurantLogo ? (
                <div className="w-8 h-8 rounded-lg overflow-hidden bg-gray-100">
                  <img 
                    src={restaurantLogo} 
                    alt="Restaurant Logo" 
                    className="w-full h-full object-cover"
                  />
                </div>
              ) : (
                <div className="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center">
                  <ChefHat className="w-5 h-5 text-white" />
                </div>
              )}
              <span className="font-semibold">{restaurantName || 'Vendor Portal'}</span>
            </div>
            <button 
              onClick={onClose}
              className="lg:hidden p-2 hover:bg-gray-100 rounded"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Navigation */}
          <nav className="flex-1 overflow-y-auto py-4">
            <div className="space-y-1 px-3">
              {menuItems.map((item) => {
                const Icon = item.icon;
                const isActive = activeView === item.id;
                // Billing is unlocked for 'activated' and 'live' statuses (when vendor has subscribed)
                const isBillingLocked = item.id === 'billing' && vendorStatus === 'demo';
                
                return (
                  <div key={item.id} className="relative group">
                    <button
                      onClick={() => {
                        onViewChange(item.id);
                        onClose();
                      }}
                      disabled={isBillingLocked}
                      className={`
                        w-full flex items-center gap-3 px-3 py-2.5 rounded-lg
                        transition-colors text-left relative
                        ${isBillingLocked 
                          ? 'text-gray-400 cursor-not-allowed opacity-60' 
                          : isActive 
                            ? 'bg-orange-50 text-orange-600 font-medium' 
                            : 'text-gray-700 hover:bg-gray-100'
                        }
                      `}
                    >
                      <Icon className={`w-5 h-5 ${isBillingLocked ? 'text-gray-400' : isActive ? 'text-orange-600' : 'text-gray-500'}`} />
                      <span className="flex-1">{item.label}</span>
                      {item.hasStatus && !isBillingLocked && (
                        <span className={`w-2 h-2 rounded-full ${getStatusColor()} flex-shrink-0`} title={subscriptionStatus} />
                      )}
                    </button>
                    
                    {/* Tooltip for locked billing */}
                    {isBillingLocked && (
                      <div className="hidden group-hover:block absolute left-full ml-2 top-1/2 -translate-y-1/2 z-50 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg pointer-events-none">
                        <p className="font-medium mb-1">Available After Activation</p>
                        <p className="text-gray-300">
                          Complete your setup and activate your account to access billing management.
                        </p>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </nav>

          {/* Footer */}
          <div className="p-4 border-t border-gray-200">
            <div className="text-xs text-gray-500">
              {restaurantName || 'Bella Cucina Restaurant'}
            </div>
          </div>
        </div>
      </aside>
    </>
  );
}