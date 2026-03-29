import { useState, useEffect } from 'react';
import { Check, Phone, FileText, Star, Plus, RefreshCw, ArrowLeft, List } from 'lucide-react';
import { Button } from './ui/button';
import { generateReceipt } from '../utils/receiptGenerator';
import { api } from '../utils/api';
import { useLanguage } from '../contexts/LanguageContext';

interface OrderTrackingProps {
  order: any;
  onCallWaiter: () => void;
  onWriteReview: () => void;
  onOrderMore?: () => void;
  onOrderUpdate?: (updatedOrder: any) => void;
  onBack?: () => void;
  onViewTracking?: () => void;
  vendorSettings?: any;
}

export function OrderTracking({ order, onCallWaiter, onWriteReview, onOrderMore, onOrderUpdate, onBack, onViewTracking, vendorSettings }: OrderTrackingProps) {
  const { t } = useLanguage();
  const [currentOrder, setCurrentOrder] = useState(order);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [showTimeline, setShowTimeline] = useState(true);
  
  // Sync with prop changes
  useEffect(() => {
    setCurrentOrder(order);
  }, [order]);
  
  const statuses = ['received', 'ready', 'served'];
  // If order is paid but not yet served, show it as completed up to received
  const orderStatus = currentOrder.status === 'paid' ? 'received' : currentOrder.status;
  const currentStatusIndex = statuses.indexOf(orderStatus);
  
  // Auto-refresh order status every 5 seconds
  useEffect(() => {
    const refreshOrder = async () => {
      try {
        const updatedOrder = await api.getOrder(currentOrder.id);
        setCurrentOrder(updatedOrder);
        if (onOrderUpdate) {
          onOrderUpdate(updatedOrder);
        }
      } catch (error) {
        console.error('Error refreshing order:', error);
      }
    };
    
    const intervalId = setInterval(refreshOrder, 5000);
    return () => clearInterval(intervalId);
  }, [currentOrder.id, onOrderUpdate]);
  
  const handleManualRefresh = async () => {
    setIsRefreshing(true);
    try {
      const updatedOrder = await api.getOrder(currentOrder.id);
      setCurrentOrder(updatedOrder);
      if (onOrderUpdate) {
        onOrderUpdate(updatedOrder);
      }
    } catch (error) {
      console.error('Error refreshing order:', error);
    } finally {
      setIsRefreshing(false);
    }
  };
  
  // Use the breakdown already calculated in the order
  // This respects the vendor's VAT and service fee settings
  const subtotal = currentOrder.subtotal || 0; // Net amount before fees
  const serviceFee = currentOrder.serviceFee || 0; // Service fee
  const vatPercent = currentOrder.vatPercent || 13; // VAT percentage from settings (legacy, weighted average)
  const vatAmount = currentOrder.vatAmount || 0; // Total VAT amount
  const vatBreakdowns = currentOrder.vatBreakdowns || []; // NEW: Detailed VAT breakdown by rate
  const tip = currentOrder.tip || 0;
  
  // Calculate gross total from items (for display)
  const itemsGrossTotal = currentOrder.items?.reduce((sum: number, item: any) => {
    const itemTotal = item.price * item.quantity;
    const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
      mSum + (m.price * item.quantity), 0) || 0;
    return sum + itemTotal + modifiersTotal;
  }, 0) || 0;
  
  // Get currency symbol from settings
  const getCurrencySymbol = () => {
    if (!vendorSettings) return '€';
    switch (vendorSettings.currency) {
      case 'EUR': return '€';
      case 'USD': return '$';
      case 'GBP': return '£';
      case 'CHF': return 'Fr.';
      default: return '€';
    }
  };
  
  const currency = getCurrencySymbol();

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col pb-20">
      {/* Header - Sticky */}
      <div className="bg-white border-b sticky top-0 z-10">
        <div className="max-w-2xl mx-auto p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              {onBack && (
                <button onClick={onBack} className="p-2 -ml-2 hover:bg-gray-100 rounded-full">
                  <ArrowLeft className="w-5 h-5" />
                </button>
              )}
              <div>
                <h1 className="text-xl">{t('order_number', 'Order #')}{currentOrder.orderNumber}</h1>
                <p className="text-sm text-gray-600">
                  {t(currentOrder.status, currentOrder.status)}
                </p>
              </div>
            </div>
            <Button 
              variant="outline" 
              size="sm"
              onClick={handleManualRefresh}
              disabled={isRefreshing}
            >
              <RefreshCw className={`w-4 h-4 ${isRefreshing ? 'animate-spin' : ''}`} />
            </Button>
          </div>
        </div>
      </div>

      {/* Scrollable Content */}
      <div className="flex-1 overflow-y-auto">
        <div className="max-w-2xl mx-auto p-4 space-y-4">
          {/* Takeaway Order Banner */}
          {currentOrder.orderType === 'takeaway' && (
            <div className="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl p-6 shadow-lg">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                  <span className="text-2xl">🛍️</span>
                </div>
                <div>
                  <h3 className="text-lg font-medium">Takeaway Order</h3>
                  <p className="text-sm text-blue-100">
                    {currentOrder.customerName ? `For: ${currentOrder.customerName}` : 'Guest Order'}
                  </p>
                </div>
              </div>
              
              <div className="space-y-3 bg-white/10 rounded-lg p-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-blue-100">Pickup Time</span>
                  <span className="font-medium text-lg">
                    {new Date(currentOrder.pickupTime).toLocaleTimeString('en-US', { 
                      hour: '2-digit', 
                      minute: '2-digit',
                      hour12: false
                    })}
                    {currentOrder.scheduledFor === 'asap' && ' (ASAP)'}
                  </span>
                </div>
                
                {currentOrder.pickupInstructions && (
                  <div className="pt-3 border-t border-white/20">
                    <p className="text-sm text-blue-100 mb-1">📍 Pickup Location</p>
                    <p className="font-medium">{currentOrder.pickupInstructions}</p>
                  </div>
                )}
                
                {/* Pickup Status */}
                <div className="pt-3 border-t border-white/20">
                  {currentOrder.pickupStatus === 'pending' && (
                    <div className="flex items-center gap-2">
                      <div className="animate-pulse w-2 h-2 bg-yellow-400 rounded-full"></div>
                      <span className="text-sm">⏱️ Preparing your order...</span>
                    </div>
                  )}
                  {currentOrder.pickupStatus === 'ready' && (
                    <div className="flex items-center gap-2 bg-green-500/20 rounded-lg p-3 border border-green-400">
                      <span className="text-2xl">✅</span>
                      <div>
                        <p className="font-medium text-lg">Order is Ready!</p>
                        <p className="text-sm text-green-100">Please come collect your order</p>
                        {currentOrder.readyAt && (
                          <p className="text-xs text-green-100 mt-1">
                            Ready since {new Date(currentOrder.readyAt).toLocaleTimeString()}
                          </p>
                        )}
                      </div>
                    </div>
                  )}
                  {currentOrder.pickupStatus === 'picked-up' && (
                    <div className="flex items-center gap-2">
                      <span className="text-2xl">🎉</span>
                      <div>
                        <p className="font-medium">Order Completed</p>
                        <p className="text-sm text-blue-100">
                          Picked up at {new Date(currentOrder.pickedUpAt).toLocaleTimeString()}
                        </p>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}
          
          {/* Quick Status Overview */}
          <div className="bg-white rounded-xl p-4 shadow-sm">
            <div className="flex items-center justify-between mb-3">
              <h2 className="text-sm font-medium">{t('order_progress', 'Order Progress')}</h2>
              <button
                onClick={() => setShowTimeline(!showTimeline)}
                className="text-sm text-orange-600 hover:text-orange-700"
              >
                {showTimeline ? t('hide_timeline', 'Hide Timeline') : t('show_timeline', 'Show Timeline')}
              </button>
            </div>
            
            {/* Progress Bar */}
            <div className="relative">
              <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                <div 
                  className="h-full bg-green-500 transition-all duration-500"
                  style={{ width: `${((currentStatusIndex + 1) / statuses.length) * 100}%` }}
                />
              </div>
              <div className="flex justify-between mt-2">
                {statuses.map((status, index) => (
                  <div key={status} className="flex flex-col items-center" style={{ width: `${100 / statuses.length}%` }}>
                    <div
                      className={`w-6 h-6 rounded-full flex items-center justify-center text-xs ${
                        index <= currentStatusIndex
                          ? 'bg-green-500 text-white'
                          : 'bg-gray-200 text-gray-400'
                      }`}
                    >
                      {index <= currentStatusIndex && <Check className="w-3 h-3" />}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Timeline (Collapsible) */}
          {showTimeline && (
            <div className="bg-white rounded-xl p-4 shadow-sm">
              <h2 className="mb-4 text-sm font-medium">{t('order_timeline', 'Order Timeline')}</h2>
              <div className="space-y-4">
                {statuses.map((status, index) => {
                  const isCompleted = index <= currentStatusIndex;
                  const isCurrent = index === currentStatusIndex;
                  const timelineItem = currentOrder.timeline?.find((t: any) => t.status === status);

                  return (
                    <div key={status} className="flex gap-3">
                      <div className="flex flex-col items-center">
                        <div
                          className={`w-8 h-8 rounded-full flex items-center justify-center ${
                            isCompleted
                              ? 'bg-green-500 text-white'
                              : 'bg-gray-200 text-gray-400'
                          }`}
                        >
                          {isCompleted && <Check className="w-4 h-4" />}
                        </div>
                        {index < statuses.length - 1 && (
                          <div
                            className={`w-0.5 h-8 ${
                              isCompleted ? 'bg-green-500' : 'bg-gray-200'
                            }`}
                          />
                        )}
                      </div>

                      <div className="flex-1 pb-2">
                        <div className={`text-sm ${isCurrent ? 'text-orange-600 font-medium' : ''}`}>
                          {t(status, status)}
                        </div>
                        {timelineItem && (
                          <div className="text-xs text-gray-500 mt-0.5">
                            {new Date(timelineItem.timestamp).toLocaleTimeString()}
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Order items */}
          <div className="bg-white rounded-xl p-4 shadow-sm">
            <h2 className="mb-3 text-sm font-medium">{t('order_items', 'Order Items')}</h2>
            <div className="space-y-2">
              {currentOrder.items?.map((item: any, index: number) => {
                const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
                  mSum + (m.price * item.quantity), 0) || 0;
                const itemTotalWithModifiers = (item.price * item.quantity) + modifiersTotal;
                
                return (
                  <div key={index} className="text-sm">
                    <div className="flex justify-between">
                      <div>
                        <div>
                          {item.quantity}x {item.name}
                        </div>
                        {item.modifiers?.length > 0 && (
                          <div className="text-xs text-gray-600 ml-4 mt-1">
                            {item.modifiers.map((m: any) => `+ ${m.name} (+${currency}${m.price.toFixed(2)})`).join(', ')}
                          </div>
                        )}
                      </div>
                      <div>{currency}{itemTotalWithModifiers.toFixed(2)}</div>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Detailed Tax & Fee Breakdown */}
            <div className="border-t mt-3 pt-3 space-y-2 text-sm bg-gray-50 rounded-lg p-3">
              <div className="text-xs text-center mb-2 text-gray-600">{t('tax_fee_breakdown', 'Tax & Fee Breakdown')}</div>
              
              <div className="flex justify-between text-gray-600">
                <span>{t('net_amount_food_beverages', 'Net amount (food & beverages)')}</span>
                <span>{currency}{subtotal.toFixed(2)}</span>
              </div>
              
              {serviceFee > 0 && (
                <div className="flex justify-between text-gray-600">
                  <span>{t('service_fee_percentage', 'Service fee ({percentage}%)').replace('{percentage}', ((serviceFee / subtotal) * 100).toFixed(0))}</span>
                  <span>{currency}{serviceFee.toFixed(2)}</span>
                </div>
              )}
              
              {/* Display VAT breakdown by rate if available, otherwise show single VAT */}
              {vatBreakdowns && vatBreakdowns.length > 0 ? (
                vatBreakdowns.map((breakdown: any, idx: number) => (
                  <div key={idx} className="flex justify-between text-gray-600">
                    <span>{t('vat_percentage', 'VAT ({percentage}%)').replace('{percentage}', breakdown.rate.toString())}</span>
                    <span>{currency}{breakdown.vatAmount.toFixed(2)}</span>
                  </div>
                ))
              ) : (
                <div className="flex justify-between text-gray-600">
                  <span>{t('vat_percentage', 'VAT ({percentage}%)').replace('{percentage}', vatPercent.toFixed(1))}</span>
                  <span>{currency}{vatAmount.toFixed(2)}</span>
                </div>
              )}
              
              <div className="flex justify-between border-t border-gray-300 pt-2">
                <span>{t('subtotal_inc_vat_fees', 'Subtotal (inc. VAT & fees)')}</span>
                <span>{currency}{itemsGrossTotal.toFixed(2)}</span>
              </div>
              
              {tip > 0 && (
                <div className="flex justify-between text-gray-600">
                  <span>{t('tip_gratuity', 'Tip (gratuity)')}</span>
                  <span>{currency}{tip.toFixed(2)}</span>
                </div>
              )}
              
              <div className="flex justify-between border-t-2 border-gray-400 pt-2 font-medium">
                <span>{t('total_amount', 'Total Amount')}</span>
                <span>{currency}{(itemsGrossTotal + tip).toFixed(2)}</span>
              </div>
            </div>
          </div>

          {/* Payment info */}
          {(currentOrder.paymentPending || (currentOrder.paymentMethod === 'cash' && !currentOrder.paymentReceived)) && (
            <div className="bg-orange-50 border border-orange-200 rounded-xl p-4">
              {currentOrder.splitPayment && currentOrder.splitPayment.pendingTotal > 0 ? (
                <>
                  <p className="text-sm text-orange-800">
                    <strong>{t('payment_pending', 'Payment pending.')}</strong>
                  </p>
                  <div className="mt-2 space-y-1">
                    <div className="flex justify-between text-sm">
                      <span>{t('paid', 'Paid')}</span>
                      <span className="text-green-700 font-semibold">{currency}{currentOrder.splitPayment.paidTotal.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span>{t('pending_cash', 'Pending (Cash)')}</span>
                      <span className="text-orange-700 font-semibold">{currency}{currentOrder.splitPayment.pendingTotal.toFixed(2)}</span>
                    </div>
                  </div>
                  <p className="text-xs text-orange-700 mt-2">
                    {t('settle_with_waiter', 'Please settle with waiter.')}
                  </p>
                </>
              ) : (
                <p className="text-sm text-orange-800">
                  <strong>{t('payment_pending', 'Payment pending.')}</strong> {t('settle_with_waiter', 'Please settle with waiter or pay now.')}
                </p>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Sticky Action Buttons */}
      <div className="fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg z-20">
        <div className="max-w-2xl mx-auto p-4 space-y-2">
          {/* Primary Actions Row */}
          <div className="grid grid-cols-2 gap-2">
            {onViewTracking && (
              <Button onClick={onViewTracking} variant="outline" size="sm">
                <List className="w-4 h-4 mr-1" />
                {t('all_orders', 'All Orders')}
              </Button>
            )}
            <Button onClick={onCallWaiter} variant="outline" size="sm">
              <Phone className="w-4 h-4 mr-1" />
              {t('call_waiter', 'Call Waiter')}
            </Button>
            {onOrderMore && (
              <Button onClick={onOrderMore} variant="outline" size="sm">
                <Plus className="w-4 h-4 mr-1" />
                {t('order_more', 'Order More')}
              </Button>
            )}
            <Button variant="outline" size="sm" onClick={() => generateReceipt(currentOrder, vendorSettings)}>
              <FileText className="w-4 h-4 mr-1" />
              {t('receipt', 'Receipt')}
            </Button>
          </div>

          {/* Review Button (Only when served) */}
          {currentOrder.status === 'served' && (
            <Button onClick={onWriteReview} className="w-full bg-orange-500 hover:bg-orange-600" size="sm">
              <Star className="w-4 h-4 mr-2" />
              {t('write_review', 'Write a Review')}
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}