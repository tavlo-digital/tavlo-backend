import { useState, useEffect } from 'react';
import { Clock, CheckCircle, XCircle, ChefHat, FileText, Banknote, DollarSign } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { api } from '../../utils/api';
import { toast } from 'sonner@2.0.3';
import { generateReceipt } from '../../utils/receiptGenerator';

interface OrdersManagementProps {
  vendorId: string;
  vendorSettings?: any;
}

export function OrdersManagement({ vendorId, vendorSettings }: OrdersManagementProps) {
  const [orders, setOrders] = useState<any[]>([]);
  const [filter, setFilter] = useState<'all' | 'received' | 'ready' | 'served'>('all');
  const [orderTypeFilter, setOrderTypeFilter] = useState<'all' | 'dine-in' | 'takeaway'>('all');
  const [paymentFilter, setPaymentFilter] = useState<'all' | 'paid' | 'pending-cash' | 'unpaid'>('all');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadOrders();
  }, []);

  const loadOrders = async () => {
    setLoading(true);
    try {
      const data = await api.getVendorOrders(vendorId);
      setOrders(data);
    } catch (error) {
      console.error('Error loading orders:', error);
      toast.error('Failed to load orders');
    } finally {
      setLoading(false);
    }
  };

  const updateOrderStatus = async (orderId: string, status: string) => {
    try {
      await api.updateOrder(orderId, { status });
      toast.success(`Order updated to ${status}`);
      loadOrders();
    } catch (error) {
      console.error('Error updating order:', error);
      toast.error('Failed to update order');
    }
  };
  
  const markOrderReady = async (orderId: string) => {
    try {
      await api.markOrderReady(orderId);
      toast.success('Order marked as ready for pickup! Customer has been notified.');
      loadOrders();
    } catch (error) {
      console.error('Error marking order ready:', error);
      toast.error('Failed to mark order as ready');
    }
  };
  
  const markOrderPickedUp = async (orderId: string) => {
    try {
      await api.markOrderPickedUp(orderId);
      toast.success('Order marked as picked up!');
      loadOrders();
    } catch (error) {
      console.error('Error marking order picked up:', error);
      toast.error('Failed to mark order as picked up');
    }
  };

  // NEW: Confirm cash payment
  const confirmCashPayment = async (orderId: string, note?: string) => {
    try {
      await api.updateOrder(orderId, { 
        paymentPending: false, 
        paymentReceived: true,
        paymentConfirmedAt: new Date().toISOString(),
        paymentNote: note 
      });
      toast.success('Cash payment confirmed');
      loadOrders();
    } catch (error) {
      console.error('Error confirming cash payment:', error);
      toast.error('Failed to confirm payment');
    }
  };

  // NEW: Get payment status
  const getPaymentStatus = (order: any) => {
    if (!order.paymentPending && order.paymentReceived) return 'paid';
    if (order.paymentPending && order.paymentMethod === 'cash') return 'pending-cash';
    return 'unpaid';
  };

  // NEW: Get payment badge
  const getPaymentBadge = (status: string) => {
    switch (status) {
      case 'paid':
        return (
          <span className="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium border border-green-200">
            ✓ Paid
          </span>
        );
      case 'pending-cash':
        return (
          <span className="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-medium border border-yellow-200">
            ⏳ Pending Cash
          </span>
        );
      case 'unpaid':
        return (
          <span className="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-medium border border-red-200">
            ⚠ Unpaid
          </span>
        );
    }
  };

  const filteredOrders = orders
    .filter(o => filter === 'all' || o.status === filter)
    .filter(o => orderTypeFilter === 'all' || (o.orderType || 'dine-in') === orderTypeFilter)
    .filter(o => {
      if (paymentFilter === 'all') return true;
      return getPaymentStatus(o) === paymentFilter;
    });
  
  // Debug logging
  console.log('🔍 Order Filtering Debug:');
  console.log('  Total orders:', orders.length);
  console.log('  Status filter:', filter);
  console.log('  Order type filter:', orderTypeFilter);
  console.log('  Payment filter:', paymentFilter);
  console.log('  Orders with orderType:', orders.map(o => ({ 
    orderNumber: o.orderNumber, 
    orderType: o.orderType,
    hasOrderType: !!o.orderType 
  })));
  console.log('  Filtered count:', filteredOrders.length);

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'received': return 'bg-blue-100 text-blue-700 border-blue-200';
      case 'ready': return 'bg-purple-100 text-purple-700 border-purple-200';
      case 'served': return 'bg-green-100 text-green-700 border-green-200';
      case 'cancelled': return 'bg-red-100 text-red-700 border-red-200';
      default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
  };

  const getNextStatus = (currentStatus: string) => {
    const statusFlow = {
      'received': 'ready',
      'ready': 'served',
    };
    return statusFlow[currentStatus as keyof typeof statusFlow];
  };

  const getStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
      'received': 'Received',
      'ready': 'Ready',
      'served': 'Served',
      'cancelled': 'Cancelled'
    };
    return labels[status] || status;
  };
  
  const getRemainingTime = (pickupTime: string) => {
    const now = new Date();
    const pickup = new Date(pickupTime);
    const diff = pickup.getTime() - now.getTime();
    const minutes = Math.floor(diff / 1000 / 60);
    
    if (minutes < 0) return 'Overdue!';
    if (minutes === 0) return 'Now!';
    if (minutes < 60) return `${minutes} min`;
    
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  };
  
  const formatPickupTime = (pickupTime: string) => {
    const date = new Date(pickupTime);
    return date.toLocaleTimeString('en-US', { 
      hour: '2-digit', 
      minute: '2-digit',
      hour12: false
    });
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold">Orders Management</h2>
          <p className="text-gray-600">Manage and track all restaurant orders</p>
        </div>
        <Button onClick={loadOrders} variant="outline">
          Refresh Orders
        </Button>
      </div>

      {/* Filter Tabs */}
      <div className="space-y-3">
        {/* Status Filter */}
        <div className="flex gap-2 overflow-x-auto pb-2">
          {[
            { id: 'all', label: 'All Orders' },
            { id: 'received', label: 'Received' },
            { id: 'ready', label: 'Ready' },
            { id: 'served', label: 'Served' },
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setFilter(tab.id as any)}
              className={`
                px-4 py-2 rounded-lg font-medium whitespace-nowrap transition-colors
                ${filter === tab.id
                  ? 'bg-orange-500 text-white'
                  : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'
                }
              `}
            >
              {tab.label}
              <span className="ml-2 text-sm">
                ({tab.id === 'all' ? orders.length : orders.filter(o => o.status === tab.id).length})
              </span>
            </button>
          ))}
        </div>
        
        {/* Order Type Filter */}
        <div className="flex gap-2">
          <span className="text-sm text-gray-600 py-2">Filter by type:</span>
          {[
            { id: 'all', label: 'All', icon: '📋' },
            { id: 'dine-in', label: 'Dine-in', icon: '🍽️' },
            { id: 'takeaway', label: 'Takeaway', icon: '🛍️' },
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setOrderTypeFilter(tab.id as any)}
              className={`
                px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                ${orderTypeFilter === tab.id
                  ? 'bg-blue-500 text-white'
                  : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'
                }
              `}
            >
              {tab.icon} {tab.label}
              <span className="ml-2">
                ({tab.id === 'all' 
                  ? orders.length 
                  : orders.filter(o => (o.orderType || 'dine-in') === tab.id).length
                })
              </span>
            </button>
          ))}
        </div>
        
        {/* Payment Filter */}
        <div className="flex gap-2">
          <span className="text-sm text-gray-600 py-2">Filter by payment:</span>
          {[
            { id: 'all', label: 'All', icon: '💳' },
            { id: 'paid', label: 'Paid', icon: '💰' },
            { id: 'pending-cash', label: 'Pending Cash', icon: '💸' },
            { id: 'unpaid', label: 'Unpaid', icon: '🚫' },
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setPaymentFilter(tab.id as any)}
              className={`
                px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                ${paymentFilter === tab.id
                  ? 'bg-green-500 text-white'
                  : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'
                }
              `}
            >
              {tab.icon} {tab.label}
              <span className="ml-2">
                ({tab.id === 'all' 
                  ? orders.length 
                  : orders.filter(o => getPaymentStatus(o) === tab.id).length
                })
              </span>
            </button>
          ))}
        </div>
      </div>

      {/* Orders Grid */}
      {loading ? (
        <div className="text-center py-12 text-gray-500">Loading orders...</div>
      ) : filteredOrders.length === 0 ? (
        <Card>
          <CardContent className="py-12 text-center text-gray-500">
            No orders found
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-4">
          {filteredOrders.map((order) => {
            const nextStatus = getNextStatus(order.status);
            
            return (
              <Card key={order.id} className="hover:shadow-lg transition-shadow">
                <CardContent className="p-6">
                  <div className="flex flex-col lg:flex-row gap-6">
                    {/* Order Info */}
                    <div className="flex-1 space-y-3">
                      <div className="flex items-start justify-between">
                        <div>
                          <div className="flex items-center gap-3 flex-wrap">
                            <h3 className="text-xl font-semibold">Order #{order.orderNumber}</h3>
                            <span className={`px-3 py-1 rounded-full text-xs font-medium border ${getStatusColor(order.status)}`}>
                              {getStatusLabel(order.status)}
                            </span>
                            {order.orderType === 'takeaway' && (
                              <span className="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                🛍️ TAKEAWAY
                              </span>
                            )}
                          </div>
                          <div className="flex items-center gap-4 mt-2 text-sm text-gray-600 flex-wrap">
                            {order.orderType === 'takeaway' ? (
                              <>
                                <span className="font-medium">👤 {order.customerName || 'Guest'}</span>
                                {order.customerPhone && (
                                  <>
                                    <span>•</span>
                                    <span>📱 {order.customerPhone}</span>
                                  </>
                                )}
                                <span>•</span>
                                <span className="font-medium text-orange-600">
                                  📅 Pickup: {formatPickupTime(order.pickupTime)}
                                  {order.scheduledFor === 'asap' && ' (ASAP)'}
                                </span>
                                {order.pickupStatus === 'pending' && (
                                  <>
                                    <span>•</span>
                                    <span className="text-orange-600 font-medium">
                                      ⏱️ Ready in {getRemainingTime(order.pickupTime)}
                                    </span>
                                  </>
                                )}
                              </>
                            ) : (
                              <>
                                <span>Table {order.tableNumber}</span>
                                <span>•</span>
                                <span>{order.numPeople} guests</span>
                              </>
                            )}
                            <span>•</span>
                            <span>{new Date(order.createdAt).toLocaleString()}</span>
                          </div>
                          {order.orderType === 'takeaway' && order.pickupInstructions && (
                            <div className="mt-2 p-2 bg-blue-50 border border-blue-200 rounded text-sm text-blue-800">
                              📍 Pickup: {order.pickupInstructions}
                            </div>
                          )}
                        </div>
                        <div className="text-right">
                          <div className="text-2xl font-semibold text-orange-600">
                            €{order.total?.toFixed(2)}
                          </div>
                          <div className="text-xs text-gray-500 mt-1">
                            Items: €{order.subtotal?.toFixed(2)}
                          </div>
                          <div className="text-xs text-gray-500">
                            Service: €{order.serviceFee?.toFixed(2)} | VAT: €{order.vatAmount?.toFixed(2)}
                          </div>
                          <div className="text-sm text-gray-500 mt-1">
                            {order.paymentMethod}
                          </div>
                          {getPaymentBadge(getPaymentStatus(order))}
                        </div>
                      </div>

                      {/* Order Items */}
                      <div className="space-y-2">
                        <div className="font-medium text-sm text-gray-700">Items:</div>
                        <div className="space-y-1.5">
                          {order.items?.map((item: any, idx: number) => {
                            const modifiersTotal = item.modifiers?.reduce((sum: number, m: any) => 
                              sum + (m.price * item.quantity), 0) || 0;
                            const itemTotalWithModifiers = (item.price * item.quantity) + modifiersTotal;
                            
                            return (
                              <div key={idx} className="flex justify-between text-sm bg-gray-50 p-2 rounded">
                                <span>
                                  <span className="font-medium">{item.quantity}x</span> {item.name}
                                  {item.modifiers?.length > 0 && (
                                    <span className="text-gray-500 text-xs ml-2">
                                      ({item.modifiers.map((m: any) => `${m.name} +€${m.price.toFixed(2)}`).join(', ')})
                                    </span>
                                  )}
                                </span>
                                <span className="font-medium">€{itemTotalWithModifiers.toFixed(2)}</span>
                              </div>
                            );
                          })}
                        </div>
                      </div>

                      {/* Order Timeline */}
                      {order.timeline && (
                        <div className="flex items-center gap-2 text-xs text-gray-500 pt-2 border-t">
                          {order.timeline.map((event: any, idx: number) => (
                            <div key={idx} className="flex items-center gap-1">
                              {idx > 0 && <span>→</span>}
                              <span className="capitalize">{event.status}</span>
                              <span className="text-gray-400">
                                {new Date(event.timestamp).toLocaleTimeString()}
                              </span>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>

                    {/* Actions */}
                    <div className="flex flex-col gap-2 lg:w-48">
                      {/* Takeaway-specific actions */}
                      {order.orderType === 'takeaway' && order.pickupStatus === 'pending' && (
                        <Button
                          onClick={() => markOrderReady(order.id)}
                          className="w-full bg-green-600 hover:bg-green-700"
                        >
                          ✅ Mark Ready for Pickup
                        </Button>
                      )}
                      
                      {order.orderType === 'takeaway' && order.pickupStatus === 'ready' && (
                        <Button
                          onClick={() => markOrderPickedUp(order.id)}
                          className="w-full bg-blue-600 hover:bg-blue-700"
                        >
                          🎉 Confirm Picked Up
                        </Button>
                      )}
                      
                      {/* Regular status progression for dine-in orders */}
                      {order.orderType !== 'takeaway' && nextStatus && order.status !== 'served' && order.status !== 'cancelled' && (
                        <Button
                          onClick={() => updateOrderStatus(order.id, nextStatus)}
                          className="w-full bg-orange-500 hover:bg-orange-600"
                        >
                          Mark as {getStatusLabel(nextStatus)}
                        </Button>
                      )}
                      
                      {order.status === 'received' && (
                        <Button
                          onClick={() => updateOrderStatus(order.id, 'cancelled')}
                          variant="outline"
                          className="w-full text-red-600 hover:text-red-700 hover:bg-red-50"
                        >
                          Cancel Order
                        </Button>
                      )}

                      {(order.status === 'served' || order.pickupStatus === 'picked-up') && (
                        <Button
                          variant="outline"
                          className="w-full"
                          onClick={() => generateReceipt(order, vendorSettings)}
                        >
                          <FileText className="w-4 h-4 mr-2" />
                          View Receipt
                        </Button>
                      )}
                      
                      {/* Confirm cash payment */}
                      {order.paymentMethod === 'cash' && order.paymentPending && (
                        <Button
                          onClick={() => confirmCashPayment(order.id)}
                          className="w-full bg-yellow-500 hover:bg-yellow-600"
                        >
                          <Banknote className="w-4 h-4 mr-2" />
                          Confirm Cash Payment
                        </Button>
                      )}
                    </div>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}