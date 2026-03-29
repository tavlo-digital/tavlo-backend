import { ArrowLeft, Trash2, Plus, Minus, User, Users, Check, Clock } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Button } from './ui/button';
import { useLanguage } from '../contexts/LanguageContext';
import { getTranslatedField, getTranslatedModifier } from '../utils/translations';
import { getVATRate, calculateNetPrice, type Country, type TaxCategory } from '../utils/taxRules';
import { BottomSystemBar } from './BottomSystemBar';

interface BasketUser {
  id: string;
  name: string;
  items: any[];
  isCurrentUser: boolean;
}

interface BasketViewProps {
  items: any[];
  pendingOrders?: any[];
  onBack: () => void;
  onUpdateQuantity: (itemId: string, quantity: number) => void;
  onRemoveItem: (itemId: string) => void;
  onCheckout: () => void;
  onPayPendingOrder?: (orderId: string) => void;
  onRemovePendingOrder?: (orderId: string) => void;
  vendorSettings?: any;
  takeawayOrder?: {
    guestData: { name: string; phone?: string; email?: string } | null;
    pickupData: { pickupTime: string; scheduledFor: 'asap' | 'scheduled'; displayTime: string } | null;
  } | null;
  onChangeTakeawayTime?: () => void;
  session?: any;
  sessionPin?: string;
  onViewHistory?: () => void;
  onCallWaiter?: () => void;
  // New props for multi-user baskets
  users?: BasketUser[];
  currentUserId?: string;
  onPayForUsers?: (userIds: string[], paymentType: 'now' | 'later') => void;
  // Props to control payment modal from parent
  showPaymentModal?: boolean;
  onClosePaymentModal?: () => void;
}

export function BasketView({ 
  items, 
  pendingOrders = [], 
  onBack, 
  onUpdateQuantity, 
  onRemoveItem, 
  onCheckout,
  onPayPendingOrder,
  onRemovePendingOrder,
  vendorSettings,
  takeawayOrder,
  onChangeTakeawayTime,
  session,
  sessionPin,
  onViewHistory,
  onCallWaiter,
  users,
  currentUserId,
  onPayForUsers,
  showPaymentModal: showPaymentModalProp = false,
  onClosePaymentModal
}: BasketViewProps) {
  const { t, language } = useLanguage();

  // DEMO MODE: Create mock users if not provided but session exists
  // Don't create multiple users for takeaway orders (single person only)
  const demoUsers: BasketUser[] | undefined = !users && session?.numPeople && session.numPeople > 1 && !takeawayOrder ? [
    {
      id: 'user-1',
      name: 'You',
      items: items.slice(0, Math.ceil(items.length / 2)),
      isCurrentUser: true
    },
    {
      id: 'user-2',
      name: 'Guest 2',
      items: items.slice(Math.ceil(items.length / 2)),
      isCurrentUser: false
    }
  ] : undefined;

  const activeUsers = users || demoUsers;
  const activeCurrentUserId = currentUserId || 'user-1';

  // Multi-user basket state
  const [selectedUserId, setSelectedUserId] = useState<string | null>(activeCurrentUserId || null);
  const [showPaymentModal, setShowPaymentModal] = useState(showPaymentModalProp);
  const [selectedUsersToPay, setSelectedUsersToPay] = useState<string[]>([activeCurrentUserId || '']);
  
  // Item assignment state for sharing
  const [itemAssignments, setItemAssignments] = useState<Map<string, Set<string>>>(new Map());

  // Sync modal state with prop
  useEffect(() => {
    setShowPaymentModal(showPaymentModalProp);
  }, [showPaymentModalProp]);

  // Initialize item assignments when modal opens
  useEffect(() => {
    if (showPaymentModal && activeUsers && activeUsers.length > 0) {
      const initialAssignments = new Map<string, Set<string>>();
      
      activeUsers.forEach(user => {
        user.items.forEach((item: any) => {
          const itemKey = `${user.id}-${item.id}-${item.name}`;
          if (!initialAssignments.has(itemKey)) {
            initialAssignments.set(itemKey, new Set());
          }
          // Automatically assign items to the user who added them
          initialAssignments.get(itemKey)!.add(user.id);
        });
      });
      
      setItemAssignments(initialAssignments);
    } else if (!showPaymentModal) {
      // Reset assignments when modal closes
      setItemAssignments(new Map());
    }
  }, [showPaymentModal]); // Only depend on showPaymentModal to avoid infinite loop

  // Get vendor's country (default to Austria)
  const vendorCountry: Country = (vendorSettings?.country || 'AT') as Country;

  // Determine if multi-user mode is active
  const isMultiUserMode = activeUsers && activeUsers.length > 1;
  
  // Get current viewing user's items
  const displayItems = isMultiUserMode && selectedUserId 
    ? (activeUsers.find(u => u.id === selectedUserId)?.items || [])
    : items;

  // Get current user
  const currentUser = activeUsers?.find(u => u.id === activeCurrentUserId);
  const selectedUser = activeUsers?.find(u => u.id === selectedUserId);
  
  // Calculate totals for display items
  const calculateTotalsForItems = (itemsList: any[]) => {
    const itemsTotal = itemsList.reduce((sum, item) => {
      const itemTotal = item.price * item.quantity;
      const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
        mSum + (m.price * item.quantity), 0) || 0;
      return sum + itemTotal + modifiersTotal;
    }, 0);
    
    interface VATBreakdown {
      rate: number;
      netAmount: number;
      vatAmount: number;
      grossAmount: number;
    }
    
    const vatBreakdownMap = new Map<number, VATBreakdown>();
    
    itemsList.forEach(item => {
      let taxCategory: TaxCategory = item.taxCategory;
      if (!taxCategory) {
        if (item.vatRate === 20) {
          taxCategory = item.category === 'drinks' ? 'beverage-alcoholic' : 'beverage-non-alcoholic';
        } else {
          taxCategory = 'food';
        }
      }
      const vatRate = getVATRate(vendorCountry, taxCategory);
      
      const itemGross = item.price * item.quantity;
      const modifiersGross = item.modifiers?.reduce((mSum: number, m: any) => 
        mSum + (m.price * item.quantity), 0) || 0;
      const totalGross = itemGross + modifiersGross;
      
      const totalNet = calculateNetPrice(totalGross, vendorCountry, taxCategory);
      const totalVAT = totalGross - totalNet;
      
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
    
    const vatBreakdowns = Array.from(vatBreakdownMap.values()).sort((a, b) => a.rate - b.rate);
    const totalNetAmount = vatBreakdowns.reduce((sum, b) => sum + b.netAmount, 0);
    const totalVATAmount = vatBreakdowns.reduce((sum, b) => sum + b.vatAmount, 0);
    const serviceFeeRate = (vendorSettings?.serviceFeeRate || 0) / 100;
    const serviceFee = totalNetAmount * serviceFeeRate;
    
    return {
      itemsTotal,
      vatBreakdowns,
      netAmount: totalNetAmount,
      vatAmount: totalVATAmount,
      serviceFee
    };
  };

  const { itemsTotal, vatBreakdowns, netAmount, vatAmount, serviceFee } = calculateTotalsForItems(displayItems);

  // Toggle user selection for payment
  const toggleUserSelection = (userId: string) => {
    setSelectedUsersToPay(prev => {
      if (prev.includes(userId)) {
        return prev.filter(id => id !== userId);
      }
      return [...prev, userId];
    });
  };

  // Handle payment
  const handlePayment = (paymentType: 'now' | 'later') => {
    if (selectedUsersToPay.length === 0) return;
    
    setShowPaymentModal(false);
    if (onClosePaymentModal) onClosePaymentModal();
    
    if (paymentType === 'now') {
      // Pay Now: Navigate to payment flow
      if (onPayForUsers) {
        onPayForUsers(selectedUsersToPay, 'now');
      } else {
        // Fallback to regular checkout
        onCheckout();
      }
    } else {
      // Pay Later: Submit order as unpaid
      if (onPayForUsers) {
        onPayForUsers(selectedUsersToPay, 'later');
      } else {
        // Fallback to regular checkout
        onCheckout();
      }
    }
  };

  // Toggle item assignment for sharing
  const toggleItemAssignment = (itemKey: string, userId: string) => {
    setItemAssignments(prev => {
      const newMap = new Map(prev);
      const participants = newMap.get(itemKey) || new Set();
      
      if (participants.has(userId)) {
        participants.delete(userId);
      } else {
        participants.add(userId);
      }
      
      newMap.set(itemKey, participants);
      return newMap;
    });
  };

  // Calculate total for selected persons based on item assignments
  const calculateAssignmentTotal = () => {
    if (!activeUsers) return 0;
    
    let total = 0;
    
    // Get all items from selected users
    const selectedUsersItems: { user: any, item: any, itemKey: string }[] = [];
    selectedUsersToPay.forEach(userId => {
      const user = activeUsers.find(u => u.id === userId);
      if (user) {
        user.items.forEach((item: any) => {
          const itemKey = `${user.id}-${item.id}-${item.name}`;
          selectedUsersItems.push({ user, item, itemKey });
        });
      }
    });
    
    // Calculate total based on assignments
    selectedUsersItems.forEach(({ item, itemKey }) => {
      const itemTotal = item.price * item.quantity;
      const modifiersTotal = item.modifiers?.reduce((sum: number, m: any) => sum + (m.price * item.quantity), 0) || 0;
      const totalItemCost = itemTotal + modifiersTotal;
      
      const participants = itemAssignments.get(itemKey);
      const participantCount = participants?.size || 1;
      const splitCost = totalItemCost / participantCount;
      
      // Add split cost for each selected user who is assigned to this item
      selectedUsersToPay.forEach(userId => {
        if (participants?.has(userId)) {
          total += splitCost;
        }
      });
    });
    
    return total;
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col pb-32">
      {/* Header */}
      <div className="bg-white border-b sticky top-0 z-10">
        <div className="max-w-4xl mx-auto p-4 flex items-center gap-3">
          <button onClick={onBack} className="p-2 -ml-2 hover:bg-gray-100 rounded-full">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <h1 className="text-xl">{t('your_order', 'Your Order')}</h1>
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 max-w-4xl mx-auto w-full p-4 space-y-6 mb-6">
        {/* Multi-User Basket Switcher */}
        {isMultiUserMode && (
          <div className="bg-white rounded-2xl p-4 shadow-sm border border-gray-200">
            <div className="flex items-center gap-2 mb-3">
              <Users className="w-5 h-5 text-gray-600" />
              <h3 className="font-semibold text-gray-900">View Basket</h3>
            </div>
            
            <div className="grid grid-cols-2 gap-2">
              {activeUsers.map((user) => {
                const isSelected = selectedUserId === user.id;
                const userItemsTotal = calculateTotalsForItems(user.items).itemsTotal;
                
                return (
                  <button
                    key={user.id}
                    onClick={() => setSelectedUserId(user.id)}
                    className={`
                      p-4 rounded-xl border-2 transition-all text-left
                      ${isSelected 
                        ? 'border-gray-900 bg-gray-50' 
                        : 'border-gray-200 bg-white hover:border-gray-300'
                      }
                    `}
                  >
                    <div className="flex items-center gap-3 mb-2">
                      <div className={`
                        w-10 h-10 rounded-full flex items-center justify-center
                        ${isSelected ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600'}
                      `}>
                        <User className="w-5 h-5" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className={`font-medium truncate ${isSelected ? 'text-gray-900' : 'text-gray-700'}`}>
                          {user.name}
                        </div>
                        {user.isCurrentUser && (
                          <div className="text-xs text-blue-600 font-medium">You</div>
                        )}
                      </div>
                    </div>
                    
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-gray-600">
                        {user.items.length} {user.items.length === 1 ? 'item' : 'items'}
                      </span>
                      <span className="font-semibold text-gray-900">
                        €{userItemsTotal.toFixed(2)}
                      </span>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {/* Viewing User Info */}
        {isMultiUserMode && selectedUser && (
          <div className={`
            rounded-2xl p-4 border-2
            ${selectedUser.isCurrentUser 
              ? 'bg-blue-50 border-blue-200' 
              : 'bg-orange-50 border-orange-200'
            }
          `}>
            <div className="flex items-center gap-2">
              <User className={`w-5 h-5 ${selectedUser.isCurrentUser ? 'text-blue-600' : 'text-orange-600'}`} />
              <span className="font-medium text-gray-900">
                {selectedUser.isCurrentUser ? 'Your Basket' : `${selectedUser.name}'s Basket`}
              </span>
            </div>
          </div>
        )}

        {/* Current Basket Section */}
        {displayItems.length > 0 && (
          <h2 className="text-lg px-1 font-semibold">
            {isMultiUserMode && selectedUser 
              ? (selectedUser.isCurrentUser ? 'Your Items' : `${selectedUser.name}'s Items`)
              : t('current_basket', 'Current Basket')
            }
          </h2>
        )}
        
        {displayItems.length === 0 ? (
          <div className="text-center py-12 text-gray-500">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <p>{isMultiUserMode && selectedUser ? `${selectedUser.name}'s basket is empty` : t('your_basket_is_empty', 'Your basket is empty')}</p>
          </div>
        ) : (
          <div className="space-y-4">
            {/* Items */}
            <div className="bg-white rounded-2xl divide-y">
              {displayItems.map((item) => {
                const itemBasePrice = item.price * item.quantity;
                const itemModifiersTotal = item.modifiers?.reduce((sum: number, m: any) => 
                  sum + (m.price * item.quantity), 0) || 0;
                const itemTotal = itemBasePrice + itemModifiersTotal;
                const translatedName = getTranslatedField(item, 'name', language);
                
                // Only allow editing if viewing current user's basket
                const canEdit = !isMultiUserMode || (selectedUser?.isCurrentUser ?? false);
                
                return (
                  <div key={item.id} className="p-4">
                    <div className="flex gap-4">
                      <div className="flex-1">
                        <h3 className="mb-1 font-medium">{translatedName}</h3>
                        
                        {/* Display modifiers/add-ons */}
                        {item.modifiers && item.modifiers.length > 0 && (
                          <div className="ml-4 mt-2 mb-3 space-y-1">
                            {item.modifiers.map((modifier: any, idx: number) => {
                              let icon = '';
                              let textColor = 'text-gray-600';
                              let prefix = '';
                              
                              if (modifier.type === 'removal') {
                                icon = '🚫';
                                textColor = 'text-red-600';
                                prefix = 'No ';
                              } else if (modifier.type === 'free-addon') {
                                icon = '🎁';
                                textColor = 'text-green-600';
                              } else if (modifier.type === 'paid-addon') {
                                icon = '✨';
                                textColor = 'text-blue-600';
                              } else {
                                icon = '🎯';
                                textColor = 'text-orange-600';
                              }
                              
                              const translatedModifierName = getTranslatedModifier(modifier.name, modifier.type, item, language);
                              
                              return (
                                <div key={idx} className="flex items-start justify-between text-sm gap-2">
                                  <div className="flex items-start gap-1.5 flex-1">
                                    <span className="text-xs mt-0.5">{icon}</span>
                                    <span className={textColor}>
                                      {prefix}{translatedModifierName}
                                      {modifier.type === 'free-addon' && <span className="text-xs text-gray-500 ml-1">(Free)</span>}
                                    </span>
                                  </div>
                                  {modifier.price > 0 && (
                                    <span className={`${textColor} tabular-nums`}>
                                      +€{(modifier.price * item.quantity).toFixed(2)}
                                    </span>
                                  )}
                                </div>
                              );
                            })}
                          </div>
                        )}
                        
                        {item.specialInstructions && (
                          <div className="ml-4 mt-2 mb-3 text-sm text-gray-600 italic flex items-start gap-1.5">
                            <span className="text-xs mt-0.5">💬</span>
                            <span>"{item.specialInstructions}"</span>
                          </div>
                        )}
                        
                        {canEdit && (
                          <div className="flex items-center gap-3 mt-3">
                            <button
                              onClick={() => onUpdateQuantity(item.id, Math.max(0, item.quantity - 1))}
                              className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                            >
                              <Minus className="w-4 h-4" />
                            </button>
                            <span className="w-8 text-center tabular-nums font-medium">{item.quantity}</span>
                            <button
                              onClick={() => onUpdateQuantity(item.id, item.quantity + 1)}
                              className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors"
                            >
                              <Plus className="w-4 h-4" />
                            </button>
                          </div>
                        )}
                        
                        {!canEdit && (
                          <div className="text-sm text-gray-500 mt-2">
                            Qty: {item.quantity}
                          </div>
                        )}
                      </div>
                      <div className="flex flex-col items-end justify-between">
                        {canEdit && (
                          <button
                            onClick={() => onRemoveItem(item.id)}
                            className="p-2 hover:bg-gray-100 rounded-full transition-colors"
                          >
                            <Trash2 className="w-4 h-4 text-red-500" />
                          </button>
                        )}
                        <span className="tabular-nums font-semibold text-gray-900">€{itemTotal.toFixed(2)}</span>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Summary */}
            <div className="bg-white rounded-2xl p-4 space-y-2">
              <div className="flex justify-between text-gray-600 text-sm">
                <span>{t('net_amount', 'Net amount (excl. VAT)')}</span>
                <span className="tabular-nums">€{netAmount.toFixed(2)}</span>
              </div>
              {serviceFee > 0 && (
                <div className="flex justify-between text-gray-600 text-sm">
                  <span>{t('service_charge', 'Service Charge')} ({vendorSettings?.serviceFeeRate || 0}%)</span>
                  <span className="tabular-nums">€{serviceFee.toFixed(2)}</span>
                </div>
              )}
              {vatBreakdowns.map((breakdown, idx) => (
                <div key={idx} className="flex justify-between text-gray-600 text-sm">
                  <span>{t('vat_included', 'VAT')} {breakdown.rate}% {t('already_included', '(already included)')}</span>
                  <span className="tabular-nums">€{breakdown.vatAmount.toFixed(2)}</span>
                </div>
              ))}
              <div className="flex justify-between text-lg font-semibold border-t pt-2 mt-2">
                <span>{t('subtotal_incl_vat', 'Subtotal (incl. VAT)')}</span>
                <span className="tabular-nums">€{itemsTotal.toFixed(2)}</span>
              </div>
            </div>
          </div>
        )}

        {/* Pending Orders Section */}
        {pendingOrders && pendingOrders.length > 0 && (
          <div className="space-y-4">
            <h2 className="text-lg px-1 font-semibold">{t('pending_orders', 'Orders Awaiting Payment')}</h2>
            {pendingOrders.map((order) => {
              const orderTotal = order.items?.reduce((sum: number, item: any) => {
                const itemTotal = item.price * item.quantity;
                const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
                  mSum + (m.price * item.quantity), 0) || 0;
                return sum + itemTotal + modifiersTotal;
              }, 0) || 0;

              return (
                <div key={order.id} className="bg-amber-50 border-2 border-amber-200 rounded-2xl p-4">
                  <div className="flex items-start gap-3 mb-3">
                    <div className="w-10 h-10 bg-amber-500 rounded-full flex items-center justify-center shrink-0">
                      <Clock className="w-5 h-5 text-white" />
                    </div>
                    <div className="flex-1">
                      <div className="flex items-center justify-between mb-1">
                        <h3 className="font-semibold text-amber-900">Order #{order.id.slice(-4)}</h3>
                        <span className="text-sm font-semibold text-amber-700">
                          €{orderTotal.toFixed(2)}
                        </span>
                      </div>
                      <p className="text-sm text-amber-700">
                        {order.items?.length || 0} {(order.items?.length || 0) === 1 ? 'item' : 'items'} • Payment pending
                      </p>
                    </div>
                  </div>

                  {/* Order Items Summary */}
                  <div className="bg-white rounded-xl p-3 mb-3 space-y-2">
                    {order.items?.slice(0, 3).map((item: any, idx: number) => (
                      <div key={idx} className="flex justify-between text-sm">
                        <span className="text-gray-700">
                          {item.quantity}x {item.name}
                        </span>
                        <span className="text-gray-900 font-medium tabular-nums">
                          €{(item.price * item.quantity).toFixed(2)}
                        </span>
                      </div>
                    ))}
                    {(order.items?.length || 0) > 3 && (
                      <div className="text-xs text-gray-500 text-center pt-1">
                        +{(order.items?.length || 0) - 3} more {(order.items?.length || 0) - 3 === 1 ? 'item' : 'items'}
                      </div>
                    )}
                  </div>

                  {/* Action Buttons */}
                  <div className="flex gap-2">
                    <Button
                      onClick={() => onPayPendingOrder?.(order.id)}
                      className="flex-1 bg-amber-600 hover:bg-amber-700 text-white"
                    >
                      Pay Now
                    </Button>
                    {onRemovePendingOrder && (
                      <Button
                        onClick={() => onRemovePendingOrder(order.id)}
                        variant="outline"
                        className="border-amber-300 text-amber-700 hover:bg-amber-100"
                      >
                        Cancel
                      </Button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Checkout/Payment button */}
      {displayItems.length > 0 && (!isMultiUserMode || (currentUser && selectedUser?.isCurrentUser)) && (
        <div className="border-t p-4 bg-white shadow-lg">
          <div className="max-w-4xl mx-auto space-y-3">
            {isMultiUserMode ? (
              <Button 
                onClick={() => setShowPaymentModal(true)}
                className="w-full bg-gray-900 hover:bg-gray-800 h-14 text-lg rounded-2xl font-semibold"
              >
                Proceed to Payment • €{itemsTotal.toFixed(2)}
              </Button>
            ) : (
              <Button 
                onClick={onCheckout}
                className="w-full bg-gray-900 hover:bg-gray-800 h-14 text-lg rounded-2xl font-semibold"
              >
                {t('proceed_to_checkout', 'Proceed to checkout')} • €{itemsTotal.toFixed(2)}
              </Button>
            )}
          </div>
        </div>
      )}

      {/* Payment Modal - Multi-User Selection */}
      {showPaymentModal && isMultiUserMode && (
        <>
          <div 
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 animate-fade-in"
            onClick={() => {
              setShowPaymentModal(false);
              if (onClosePaymentModal) onClosePaymentModal();
            }}
          />
          
          <div className="fixed inset-x-0 bottom-0 bg-white rounded-t-3xl z-50 max-h-[85vh] overflow-hidden flex flex-col animate-slide-up shadow-2xl">
            {/* Header */}
            <div className="p-6 border-b">
              <div className="w-12 h-1.5 bg-gray-300 rounded-full mx-auto mb-4" />
              <h2 className="text-2xl font-bold text-gray-900">Assign Items</h2>
              <p className="text-sm text-gray-600 mt-1">
                Tick the items you ordered. If you shared an item, everyone who shared it should tick it – we'll split the cost automatically.
              </p>
            </div>

            {/* Scrollable Content */}
            <div className="flex-1 overflow-y-auto p-6 space-y-6">
              {/* User Selection */}
              <div>
                <h3 className="font-semibold text-gray-900 mb-3">Select person to assign items</h3>
                <div className="grid grid-cols-2 gap-3">
                  {activeUsers?.map((user) => {
                    const isSelected = selectedUsersToPay.includes(user.id);
                    const userTotal = calculateTotalsForItems(user.items).itemsTotal;
                    
                    return (
                      <button
                        key={user.id}
                        onClick={() => toggleUserSelection(user.id)}
                        className={`
                          relative p-4 rounded-2xl border-2 transition-all
                          ${isSelected 
                            ? 'border-orange-500 bg-orange-50' 
                            : 'border-gray-200 bg-white hover:border-gray-300'
                          }
                        `}
                      >
                        {isSelected && (
                          <div className="absolute top-2 right-2 w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center">
                            <Check className="w-4 h-4 text-white" strokeWidth={3} />
                          </div>
                        )}
                        
                        <div className="flex flex-col items-center gap-2">
                          <div className={`
                            w-12 h-12 rounded-full flex items-center justify-center
                            ${isSelected ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600'}
                          `}>
                            <User className="w-6 h-6" />
                          </div>
                          <div className="text-center">
                            <div className="font-semibold text-gray-900">{user.name}</div>
                            <div className="text-sm text-orange-600 font-semibold mt-1">
                              €{userTotal.toFixed(2)}
                            </div>
                          </div>
                        </div>
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Info Box */}
              {selectedUsersToPay.length > 0 && (
                <div className="bg-blue-50 border border-blue-200 rounded-2xl p-4">
                  <div className="flex items-start gap-3">
                    <div className="text-xl">💡</div>
                    <div className="flex-1">
                      <p className="text-sm text-blue-900 font-medium mb-1">
                        {selectedUsersToPay.length === 1 ? 'Person 1' : `${selectedUsersToPay.length} People`}: Tick the items you ordered.
                      </p>
                      <p className="text-xs text-blue-700">
                        Shared items will be split automatically.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Order Items List */}
              {selectedUsersToPay.length > 0 && (() => {
                // Collect all items with metadata
                const allItemsWithMeta: { 
                  user: any, 
                  item: any, 
                  itemKey: string,
                  isOwnItem: boolean 
                }[] = [];
                
                // First add items from selected users (own items)
                selectedUsersToPay.forEach(userId => {
                  const user = activeUsers?.find(u => u.id === userId);
                  if (user) {
                    user.items.forEach((item: any) => {
                      const itemKey = `${user.id}-${item.id}-${item.name}`;
                      allItemsWithMeta.push({ 
                        user, 
                        item, 
                        itemKey, 
                        isOwnItem: true 
                      });
                    });
                  }
                });
                
                // Then add items from other users (can be shared)
                activeUsers?.forEach(user => {
                  if (!selectedUsersToPay.includes(user.id)) {
                    user.items.forEach((item: any) => {
                      const itemKey = `${user.id}-${item.id}-${item.name}`;
                      allItemsWithMeta.push({ 
                        user, 
                        item, 
                        itemKey, 
                        isOwnItem: false 
                      });
                    });
                  }
                });

                return (
                  <div>
                    <h3 className="font-semibold text-gray-900 mb-3">All Orders</h3>
                    <div className="space-y-2">
                      {allItemsWithMeta.map(({ user, item, itemKey, isOwnItem }, idx) => {
                        const itemTotal = item.price * item.quantity;
                        const modifiersTotal = item.modifiers?.reduce((sum: number, m: any) => sum + (m.price * item.quantity), 0) || 0;
                        const totalItemCost = itemTotal + modifiersTotal;
                        const participants = itemAssignments.get(itemKey);
                        const isAnySelected = selectedUsersToPay.some(userId => participants?.has(userId));
                        const participantCount = participants?.size || 1;
                        const splitCost = totalItemCost / participantCount;

                        return (
                          <div
                            key={idx}
                            className={`
                              flex items-start gap-3 p-4 border-2 rounded-xl transition-all
                              ${isOwnItem || isAnySelected
                                ? 'border-orange-500 bg-orange-50'
                                : 'border-gray-200 bg-white'
                              }
                            `}
                          >
                            {isOwnItem ? (
                              <div className="w-5 h-5 rounded border-2 border-orange-500 bg-orange-500 flex items-center justify-center shrink-0 mt-0.5">
                                <Check className="w-3.5 h-3.5 text-white" strokeWidth={3} />
                              </div>
                            ) : (
                              <input
                                type="checkbox"
                                checked={isAnySelected}
                                onChange={() => {
                                  // Toggle assignment for all selected users
                                  selectedUsersToPay.forEach(userId => {
                                    toggleItemAssignment(itemKey, userId);
                                  });
                                }}
                                className="w-5 h-5 rounded border-2 border-gray-300 shrink-0 mt-0.5 cursor-pointer"
                              />
                            )}
                            
                            <div className="flex-1 min-w-0">
                              <div className="flex justify-between gap-2">
                                <div className="flex-1">
                                  <div className="font-medium text-gray-900">
                                    {item.quantity}x {item.name}
                                  </div>
                                  
                                  {/* Subtext for shared items */}
                                  {participantCount > 1 && (
                                    <div className="mt-1">
                                      <div className="text-xs text-blue-600 font-medium">
                                        Shared by {participantCount} {participantCount === 1 ? 'Person' : 'People'}
                                      </div>
                                      <div className="text-sm font-semibold text-orange-600">
                                        €{splitCost.toFixed(2)} each
                                      </div>
                                    </div>
                                  )}
                                  
                                  {item.modifiers && item.modifiers.length > 0 && (
                                    <div className="text-xs text-gray-600 mt-1">
                                      {item.modifiers.map((m: any, i: number) => (
                                        <div key={i}>+ {m.name} {m.price > 0 && `(€${m.price.toFixed(2)})`}</div>
                                      ))}
                                    </div>
                                  )}
                                </div>
                                <div className="text-right">
                                  <div className="font-semibold text-gray-900">€{totalItemCost.toFixed(2)}</div>
                                </div>
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                );
              })()}

              {/* Total */}
              {selectedUsersToPay.length > 0 && (
                <div className="bg-gray-50 rounded-2xl p-4">
                  <div className="flex justify-between items-center">
                    <span className="text-lg font-semibold text-gray-900">
                      {selectedUsersToPay.length === 1 ? 'Person 1 Total' : 'Total for Selected'}
                    </span>
                    <span className="text-2xl font-bold text-orange-600">
                      €{calculateAssignmentTotal().toFixed(2)}
                    </span>
                  </div>
                </div>
              )}
            </div>

            {/* Footer Actions */}
            <div className="border-t p-4 bg-white space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <Button
                  onClick={() => handlePayment('later')}
                  variant="outline"
                  className="h-14 text-base rounded-2xl border-2 border-gray-900 font-semibold"
                  disabled={selectedUsersToPay.length === 0}
                >
                  Pay Later
                </Button>
                <Button
                  onClick={() => handlePayment('now')}
                  className="h-14 text-base rounded-2xl bg-orange-600 hover:bg-orange-700 font-semibold"
                  disabled={selectedUsersToPay.length === 0}
                >
                  Pay Now
                </Button>
              </div>
              
              <Button
                onClick={() => {
                  setShowPaymentModal(false);
                  if (onClosePaymentModal) onClosePaymentModal();
                }}
                variant="ghost"
                className="w-full text-gray-600"
              >
                Back
              </Button>
            </div>
          </div>
        </>
      )}
      
      {/* Bottom System Bar */}
      <BottomSystemBar
        sessionPin={sessionPin}
        basketCount={currentUser?.items.length || items.length}
        pendingOrdersCount={pendingOrders.length}
        accentColor={vendorSettings?.accentColor || '#f59e0b'}
        onViewBasket={onBack}
        onViewHistory={onViewHistory}
        onCallWaiter={onCallWaiter}
      />
    </div>
  );
}