import { useEffect, useState } from 'react';
import { ArrowLeft, Clock, ChefHat, Package, RefreshCw } from 'lucide-react';
import { Button } from './ui/button';
import { api } from '../utils/api';
import { useLanguage } from '../contexts/LanguageContext';

interface ActiveOrdersListProps {
  orders: any[];
  onBack: () => void;
  onSelectOrder: (order: any) => void;
  onOrdersRefresh?: (orders: any[]) => void;
}

export function ActiveOrdersList({ orders, onBack, onSelectOrder, onOrdersRefresh }: ActiveOrdersListProps) {
  const { t } = useLanguage();
  const [currentOrders, setCurrentOrders] = useState(orders);
  const [isRefreshing, setIsRefreshing] = useState(false);
  
  // Sync with prop changes
  useEffect(() => {
    setCurrentOrders(orders);
  }, [orders]);
  
  // Auto-refresh every 5 seconds
  useEffect(() => {
    if (!currentOrders.length) return;
    
    const refreshOrders = async () => {
      try {
        // Fetch updated order data for each order
        const updatedOrders = await Promise.all(
          currentOrders.map(async (order) => {
            try {
              return await api.getOrder(order.id);
            } catch (error) {
              console.error(`Error fetching order ${order.id}:`, error);
              return order; // Return original if fetch fails
            }
          })
        );
        
        setCurrentOrders(updatedOrders);
        if (onOrdersRefresh) {
          onOrdersRefresh(updatedOrders);
        }
      } catch (error) {
        console.error('Error refreshing orders:', error);
      }
    };
    
    const intervalId = setInterval(refreshOrders, 5000);
    return () => clearInterval(intervalId);
  }, [currentOrders.map(o => o.id).join(','), onOrdersRefresh]);
  
  const handleManualRefresh = async () => {
    if (!currentOrders.length) return;
    
    setIsRefreshing(true);
    try {
      const updatedOrders = await Promise.all(
        currentOrders.map(async (order) => {
          try {
            return await api.getOrder(order.id);
          } catch (error) {
            console.error(`Error fetching order ${order.id}:`, error);
            return order; // Return original if fetch fails
          }
        })
      );
      
      setCurrentOrders(updatedOrders);
      if (onOrdersRefresh) {
        onOrdersRefresh(updatedOrders);
      }
    } catch (error) {
      console.error('Error refreshing orders:', error);
    } finally {
      setIsRefreshing(false);
    }
  };
  
  const getStatusInfo = (status: string) => {
    switch (status) {
      case 'received':
        return { label: t('order_received', 'Order Received'), color: 'bg-blue-500', icon: Package };
      case 'ready':
        return { label: t('ready', 'Ready'), color: 'bg-green-500', icon: Package };
      case 'served':
        return { label: t('served', 'Served'), color: 'bg-gray-500', icon: Package };
      default:
        return { label: status, color: 'bg-gray-500', icon: Package };
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col">
      {/* Header */}
      <div className="bg-white border-b sticky top-0 z-10">
        <div className="max-w-4xl mx-auto p-4 flex items-center gap-3">
          <button onClick={onBack} className="p-2 -ml-2 hover:bg-gray-100 rounded-full">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <h1 className="text-xl flex-1">{t('active_orders', 'Active Orders')}</h1>
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

      {/* Content */}
      <div className="flex-1 max-w-4xl mx-auto w-full p-4 space-y-4">
        {currentOrders.length === 0 ? (
          <div className="text-center py-12 text-gray-500">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <Clock className="w-8 h-8 text-gray-400" />
            </div>
            <p>{t('no_active_orders', 'No active orders')}</p>
            <p className="text-sm mt-1">{t('no_active_orders_desc', 'Your orders will appear here')}</p>
          </div>
        ) : (
          <div className="space-y-4">
            {currentOrders.map((order) => {
              const statusInfo = getStatusInfo(order.status);
              const StatusIcon = statusInfo.icon;
              
              const itemsTotal = order.items?.reduce((sum: number, item: any) => {
                const itemTotal = sum + (item.price * item.quantity);
                const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
                  mSum + (m.price * item.quantity), 0) || 0;
                return itemTotal + modifiersTotal;
              }, 0) || 0;
              
              const total = itemsTotal + (order.tip || 0);

              return (
                <div
                  key={order.id}
                  onClick={() => onSelectOrder(order)}
                  className="bg-white rounded-2xl p-4 shadow-sm border-2 border-gray-100 hover:border-orange-300 cursor-pointer transition-all"
                >
                  {/* Order header */}
                  <div className="flex items-start justify-between mb-3">
                    <div>
                      <div className="flex items-center gap-2 mb-1">
                        <span className={`${statusInfo.color} text-white text-xs px-3 py-1 rounded-full flex items-center gap-1`}>
                          <StatusIcon className="w-3 h-3" />
                          {statusInfo.label}
                        </span>
                      </div>
                      <div className="text-sm text-gray-600">
                        {t('order_number', 'Order #')}{order.id.slice(0, 8)}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-lg">€{total.toFixed(2)}</div>
                      <div className="text-xs text-gray-500">
                        {order.items?.length || 0} {(order.items?.length || 0) !== 1 ? t('items', 'items') : t('item', 'item')}
                      </div>
                    </div>
                  </div>

                  {/* Order items preview */}
                  <div className="bg-gray-50 rounded-lg p-3 mb-3 space-y-1">
                    {order.items?.slice(0, 3).map((item: any, idx: number) => (
                      <div key={idx} className="flex justify-between text-sm">
                        <span className="text-gray-700">{item.quantity}x {item.name}</span>
                        <span className="text-gray-600">€{(item.price * item.quantity).toFixed(2)}</span>
                      </div>
                    ))}
                    {(order.items?.length || 0) > 3 && (
                      <div className="text-xs text-gray-500 text-center pt-1">
                        +{order.items.length - 3} {t('more', 'more')} {order.items.length - 3 !== 1 ? t('items', 'items') : t('item', 'item')}
                      </div>
                    )}
                  </div>

                  {/* Action */}
                  <div className="flex items-center justify-between">
                    <div className="text-sm text-gray-500">
                      {order.createdAt ? new Date(order.createdAt).toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit' 
                      }) : t('just_now', 'Just now')}
                    </div>
                    <div className="text-sm text-orange-600 flex items-center gap-1">
                      {t('view_details', 'View Details')}
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                      </svg>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}