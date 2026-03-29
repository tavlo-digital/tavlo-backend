import { useState } from 'react';
import { RotateCcw, ChevronRight, ArrowLeft, FileText, Star, Users, MapPin, Check } from 'lucide-react';
import { Button } from '../ui/button';
import { generateReceipt } from '../../utils/receiptGenerator';

interface Order {
  id: string;
  restaurantId: string;
  restaurantName: string;
  restaurantLogo?: string;
  date: string;
  amount: number;
  items: Array<{ 
    name: string; 
    quantity: number;
    price?: number;
    modifiers?: Array<{ name: string; price: number }>;
  }>;
  status: string;
  tip?: number;
  subtotal?: number;
  serviceFee?: number;
  vatAmount?: number;
  vatPercent?: number;
  vatBreakdowns?: Array<{ rate: number; vatAmount: number }>;
  orderNumber?: string;
  numPeople?: number;
  orderType?: 'dine-in' | 'takeaway';
  tableNumber?: string | number;
  reviews?: Array<{ rating: number; comment: string }>;
}

interface OrderHistoryListProps {
  orders: Order[];
  onReorder: (orderId: string) => void;
  onViewOrder: (orderId: string) => void;
  onRestaurantClick?: (restaurantId: string) => void;
  onWriteReview?: (order: Order) => void;
  vendorSettings?: any;
}

// Restaurant card component for the list view
function RestaurantOrderCard({ 
  restaurantId,
  restaurantName, 
  restaurantLogo,
  orderCount,
  totalSpent,
  lastOrderDate,
  onClick 
}: {
  restaurantId: string;
  restaurantName: string;
  restaurantLogo?: string;
  orderCount: number;
  totalSpent: number;
  lastOrderDate: string;
  onClick: () => void;
}) {
  return (
    <div
      onClick={onClick}
      className="p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors cursor-pointer"
    >
      <div className="flex items-center gap-3">
        {/* Restaurant Logo */}
        <div className="w-12 h-12 shrink-0 bg-gray-200 rounded-xl flex items-center justify-center overflow-hidden">
          {restaurantLogo ? (
            <img src={restaurantLogo} alt={restaurantName} className="w-full h-full object-cover" />
          ) : (
            <span className="text-xl">🍽️</span>
          )}
        </div>

        {/* Restaurant Info */}
        <div className="flex-1 min-w-0">
          <h3 className="text-base truncate">{restaurantName}</h3>
          <div className="flex items-center gap-2 text-sm text-gray-500 mt-1">
            <span>{orderCount} {orderCount === 1 ? 'order' : 'orders'}</span>
            <span>•</span>
            <span>Last: {lastOrderDate}</span>
          </div>
        </div>

        {/* Total Spent */}
        <div className="text-right shrink-0">
          <div className="text-sm text-gray-500">Total spent</div>
          <div className="text-lg">€{totalSpent.toFixed(2)}</div>
        </div>

        {/* Arrow */}
        <ChevronRight className="w-5 h-5 text-gray-400 shrink-0" />
      </div>
    </div>
  );
}

export function OrderHistoryList({ orders, onReorder, onViewOrder, onRestaurantClick, onWriteReview, vendorSettings }: OrderHistoryListProps) {
  const [selectedRestaurantId, setSelectedRestaurantId] = useState<string | null>(null);
  const [selectedOrderId, setSelectedOrderId] = useState<string | null>(null);

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

  // Group orders by restaurant
  const restaurantGroups = orders.reduce((acc, order) => {
    const restaurantId = order.restaurantId || 'unknown';
    if (!acc[restaurantId]) {
      acc[restaurantId] = {
        restaurantId,
        restaurantName: order.restaurantName,
        restaurantLogo: order.restaurantLogo,
        orders: [],
        totalSpent: 0,
      };
    }
    acc[restaurantId].orders.push(order);
    acc[restaurantId].totalSpent += order.amount;
    return acc;
  }, {} as Record<string, {
    restaurantId: string;
    restaurantName: string;
    restaurantLogo?: string;
    orders: Order[];
    totalSpent: number;
  }>);

  const restaurantList = Object.values(restaurantGroups);

  // LEVEL 3: Detailed Order View
  if (selectedOrderId && selectedRestaurantId) {
    const selectedRestaurant = restaurantGroups[selectedRestaurantId];
    const selectedOrder = selectedRestaurant?.orders.find(o => o.id === selectedOrderId);

    if (!selectedOrder) {
      setSelectedOrderId(null);
      return null;
    }

    // Calculate order breakdown
    const subtotal = selectedOrder.subtotal || 0;
    const serviceFee = selectedOrder.serviceFee || 0;
    const vatAmount = selectedOrder.vatAmount || 0;
    const vatPercent = selectedOrder.vatPercent || 13;
    const vatBreakdowns = selectedOrder.vatBreakdowns || [];
    const tip = selectedOrder.tip || 0;

    // Calculate gross total from items
    const itemsGrossTotal = selectedOrder.items.reduce((sum, item) => {
      const itemTotal = (item.price || 0) * item.quantity;
      const modifiersTotal = item.modifiers?.reduce((mSum, m) => 
        mSum + (m.price * item.quantity), 0) || 0;
      return sum + itemTotal + modifiersTotal;
    }, 0);

    // Check if order has been reviewed
    const hasReview = selectedOrder.reviews && selectedOrder.reviews.length > 0;

    return (
      <div className="space-y-4">
        {/* Back Button */}
        <button
          onClick={() => setSelectedOrderId(null)}
          className="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to {selectedRestaurant.restaurantName} orders
        </button>

        {/* Order Header Card */}
        <div className="bg-gradient-to-br from-[#101828] to-[#101828] text-white rounded-2xl p-6">
          <div className="flex items-center justify-between mb-4">
            <div>
              <div className="text-white/80 text-sm">Order #{selectedOrder.orderNumber || selectedOrder.id.slice(0, 8)}</div>
              <div className="text-lg mt-1">{selectedOrder.restaurantName}</div>
              <div className="text-sm text-white/70 mt-1">{selectedOrder.date}</div>
            </div>
            <div className={`px-3 py-1 rounded-full text-sm ${
              selectedOrder.status === 'completed'
                ? 'bg-green-500/20 text-green-200'
                : 'bg-orange-500/20 text-orange-200'
            }`}>
              {selectedOrder.status}
            </div>
          </div>

          {/* Order Info */}
          <div className="grid grid-cols-2 gap-4 pt-4 border-t border-white/20">
            {selectedOrder.orderType && (
              <div>
                <div className="text-white/80 text-sm">Type</div>
                <div className="text-base mt-1 capitalize">{selectedOrder.orderType.replace('-', ' ')}</div>
              </div>
            )}
            {selectedOrder.tableNumber && (
              <div>
                <div className="text-white/80 text-sm">Table</div>
                <div className="text-base mt-1">#{selectedOrder.tableNumber}</div>
              </div>
            )}
            {selectedOrder.numPeople && (
              <div>
                <div className="text-white/80 text-sm">People</div>
                <div className="text-base mt-1">{selectedOrder.numPeople}</div>
              </div>
            )}
          </div>
        </div>

        {/* Order Items */}
        <div className="bg-white rounded-2xl p-6 border border-gray-100">
          <h3 className="text-lg mb-4">Order Items</h3>
          <div className="space-y-3">
            {selectedOrder.items.map((item, index) => {
              const modifiersTotal = item.modifiers?.reduce((mSum, m) => 
                mSum + (m.price * item.quantity), 0) || 0;
              const itemTotalWithModifiers = ((item.price || 0) * item.quantity) + modifiersTotal;
              
              return (
                <div key={index} className="text-sm">
                  <div className="flex justify-between">
                    <div>
                      <div>
                        {item.quantity}x {item.name}
                      </div>
                      {item.modifiers && item.modifiers.length > 0 && (
                        <div className="text-xs text-gray-600 ml-4 mt-1">
                          {item.modifiers.map((m, idx) => (
                            <div key={idx}>+ {m.name} (+{currency}{m.price.toFixed(2)})</div>
                          ))}
                        </div>
                      )}
                    </div>
                    <div>{currency}{itemTotalWithModifiers.toFixed(2)}</div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Tax & Fee Breakdown */}
          <div className="border-t mt-4 pt-4 space-y-2 text-sm bg-gray-50 rounded-lg p-4">
            <div className="text-xs text-center mb-2 text-gray-600">Tax & Fee Breakdown</div>
            
            <div className="flex justify-between text-gray-600">
              <span>Net amount (food & beverages)</span>
              <span>{currency}{subtotal.toFixed(2)}</span>
            </div>
            
            {serviceFee > 0 && (
              <div className="flex justify-between text-gray-600">
                <span>Service fee ({((serviceFee / subtotal) * 100).toFixed(0)}%)</span>
                <span>{currency}{serviceFee.toFixed(2)}</span>
              </div>
            )}
            
            {/* Display VAT breakdown by rate if available */}
            {vatBreakdowns && vatBreakdowns.length > 0 ? (
              vatBreakdowns.map((breakdown, idx) => (
                <div key={idx} className="flex justify-between text-gray-600">
                  <span>VAT ({breakdown.rate}%)</span>
                  <span>{currency}{breakdown.vatAmount.toFixed(2)}</span>
                </div>
              ))
            ) : vatAmount > 0 && (
              <div className="flex justify-between text-gray-600">
                <span>VAT ({vatPercent.toFixed(1)}%)</span>
                <span>{currency}{vatAmount.toFixed(2)}</span>
              </div>
            )}
            
            <div className="flex justify-between border-t border-gray-300 pt-2">
              <span>Subtotal (inc. VAT & fees)</span>
              <span>{currency}{itemsGrossTotal.toFixed(2)}</span>
            </div>
            
            {tip > 0 && (
              <div className="flex justify-between text-gray-600">
                <span>Tip (gratuity)</span>
                <span>{currency}{tip.toFixed(2)}</span>
              </div>
            )}
            
            <div className="flex justify-between border-t-2 border-gray-400 pt-2 font-medium text-base">
              <span>Total Amount</span>
              <span>{currency}{(itemsGrossTotal + tip).toFixed(2)}</span>
            </div>
          </div>
        </div>

        {/* Review Display (if exists) */}
        {hasReview && (
          <div className="bg-white rounded-2xl p-6 border border-gray-100">
            <h3 className="text-lg mb-3">Your Review</h3>
            <div className="flex items-center gap-2 mb-2">
              <div className="flex items-center gap-1">
                {[...Array(5)].map((_, i) => (
                  <Star
                    key={i}
                    className={`w-4 h-4 ${
                      i < (selectedOrder.reviews?.[0]?.rating || 0)
                        ? 'fill-yellow-500 text-yellow-500'
                        : 'fill-gray-200 text-gray-200'
                    }`}
                  />
                ))}
              </div>
              <span className="text-sm text-gray-600">
                {selectedOrder.reviews?.[0]?.rating}/5
              </span>
            </div>
            {selectedOrder.reviews?.[0]?.comment && (
              <p className="text-sm text-gray-700">{selectedOrder.reviews[0].comment}</p>
            )}
          </div>
        )}

        {/* Action Buttons */}
        <div className="space-y-2">
          <div className="grid grid-cols-2 gap-2">
            <Button
              variant="outline"
              onClick={() => generateReceipt(selectedOrder, vendorSettings)}
            >
              <FileText className="w-4 h-4 mr-2" />
              Download Bill
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                onReorder(selectedOrder.id);
                if (onRestaurantClick) {
                  onRestaurantClick(selectedOrder.restaurantId);
                }
              }}
            >
              <RotateCcw className="w-4 h-4 mr-2" />
              Reorder
            </Button>
          </div>

          {onRestaurantClick && (
            <Button
              variant="outline"
              className="w-full"
              onClick={() => onRestaurantClick(selectedOrder.restaurantId)}
            >
              <MapPin className="w-4 h-4 mr-2" />
              Go to Restaurant Page
            </Button>
          )}

          {onWriteReview && (
            <Button
              className="w-full bg-[#101828] hover:bg-[#101828]/90 text-white"
              onClick={() => onWriteReview(selectedOrder)}
            >
              <Star className="w-4 h-4 mr-2" />
              {hasReview ? 'Edit Review' : 'Write a Review'}
            </Button>
          )}
        </div>
      </div>
    );
  }

  // LEVEL 2: Restaurant-Specific Order List
  if (selectedRestaurantId) {
    const selectedRestaurant = restaurantGroups[selectedRestaurantId];
    
    if (!selectedRestaurant) {
      setSelectedRestaurantId(null);
      return null;
    }

    const restaurantOrders = selectedRestaurant.orders.sort((a, b) => 
      new Date(b.date).getTime() - new Date(a.date).getTime()
    );

    return (
      <div className="space-y-4">
        {/* Back Button */}
        <button
          onClick={() => setSelectedRestaurantId(null)}
          className="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to all restaurants
        </button>

        {/* Restaurant Header Card */}
        <div className="bg-gradient-to-br from-[#101828] to-[#101828] text-white rounded-2xl p-6">
          <div className="flex items-center gap-3 mb-4">
            <div className="w-12 h-12 shrink-0 bg-white/20 rounded-xl flex items-center justify-center overflow-hidden">
              {selectedRestaurant.restaurantLogo ? (
                <img 
                  src={selectedRestaurant.restaurantLogo} 
                  alt={selectedRestaurant.restaurantName} 
                  className="w-full h-full object-cover" 
                />
              ) : (
                <span className="text-xl">🍽️</span>
              )}
            </div>
            <div>
              <div className="text-white/80 text-sm">Order history at</div>
              <div className="text-lg">{selectedRestaurant.restaurantName}</div>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4 pt-4 border-t border-white/20">
            <div>
              <div className="text-white/80 text-sm">Total Orders</div>
              <div className="text-2xl mt-1">{restaurantOrders.length}</div>
            </div>
            <div>
              <div className="text-white/80 text-sm">Total Spent</div>
              <div className="text-2xl mt-1">€{selectedRestaurant.totalSpent.toFixed(2)}</div>
            </div>
          </div>
        </div>

        {/* Order List */}
        <div className="bg-white rounded-2xl p-6 border border-gray-100">
          <h3 className="text-xl mb-4">Your Orders</h3>
          
          <div className="space-y-3">
            {restaurantOrders.map((order) => (
              <div
                key={order.id}
                onClick={() => setSelectedOrderId(order.id)}
                className="p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors cursor-pointer"
              >
                <div className="flex items-start justify-between mb-2">
                  <div className="flex-1">
                    <p className="text-sm text-gray-500">{order.date}</p>
                    {order.orderType && (
                      <p className="text-xs text-gray-400 mt-1 capitalize">
                        {order.orderType.replace('-', ' ')}
                        {order.tableNumber && ` • Table ${order.tableNumber}`}
                      </p>
                    )}
                  </div>
                  <div className="text-right">
                    <div className="text-lg">€{order.amount.toFixed(2)}</div>
                    <div className={`text-xs px-2 py-1 rounded-full inline-block mt-1 ${
                      order.status === 'completed'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-orange-100 text-orange-700'
                    }`}>
                      {order.status}
                    </div>
                  </div>
                </div>

                <div className="text-sm text-gray-600 mb-3">
                  {order.items.slice(0, 2).map((item, idx) => (
                    <span key={idx}>
                      {item.quantity}x {item.name}
                      {idx < Math.min(order.items.length - 1, 1) && ', '}
                    </span>
                  ))}
                  {order.items.length > 2 && (
                    <span className="text-gray-500"> +{order.items.length - 2} more</span>
                  )}
                </div>

                <div className="flex items-center justify-end">
                  <ChevronRight className="w-5 h-5 text-gray-400" />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  // LEVEL 1: Restaurant List (Default View)
  return (
    <div className="bg-white rounded-2xl p-6 border border-gray-100">
      <h2 className="text-2xl mb-2">Order History</h2>
      <p className="text-sm text-gray-600 mb-6">
        View your order history by restaurant
      </p>
      
      {restaurantList.length === 0 ? (
        <div className="text-center py-12 text-gray-500">
          <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <FileText className="w-8 h-8 text-gray-400" />
          </div>
          <p>No orders yet</p>
          <p className="text-sm mt-1">Your order history will appear here</p>
        </div>
      ) : (
        <div className="space-y-3">
          {restaurantList.map((restaurant) => {
            // Sort orders by date to get the most recent
            const sortedOrders = restaurant.orders.sort((a, b) => 
              new Date(b.date).getTime() - new Date(a.date).getTime()
            );
            const lastOrder = sortedOrders[0];

            return (
              <RestaurantOrderCard
                key={restaurant.restaurantId}
                restaurantId={restaurant.restaurantId}
                restaurantName={restaurant.restaurantName}
                restaurantLogo={restaurant.restaurantLogo}
                orderCount={restaurant.orders.length}
                totalSpent={restaurant.totalSpent}
                lastOrderDate={lastOrder.date}
                onClick={() => setSelectedRestaurantId(restaurant.restaurantId)}
              />
            );
          })}
        </div>
      )}
    </div>
  );
}