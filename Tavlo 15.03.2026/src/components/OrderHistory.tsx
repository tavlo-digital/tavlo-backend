import { ArrowLeft, Star, RotateCcw, FileText, Users } from 'lucide-react';
import { Button } from './ui/button';
import { generateReceipt } from '../utils/receiptGenerator';
import { BottomSystemBar } from './BottomSystemBar';

interface OrderHistoryProps {
  onBack: () => void;
  onReorder: (order: any) => void;
  onViewOrder?: (order: any) => void;
  onWriteReview?: (order: any) => void;
  customerId?: string;
  orders?: any[];
  vendorSettings?: any;
  user?: any; // Add user object to get current loyalty points
  session?: any; // Session for PIN
  sessionPin?: string; // Add sessionPin prop for bottom bar
  basketCount?: number;
  pendingOrdersCount?: number;
  onViewBasket?: () => void;
  onCallWaiter?: () => void;
}

export function OrderHistory({ onBack, onReorder, onViewOrder, onWriteReview, customerId, orders = [], vendorSettings, user, session, sessionPin, basketCount, pendingOrdersCount, onViewBasket, onCallWaiter }: OrderHistoryProps) {
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
  
  // Calculate total loyalty points across all restaurants
  const loyaltyPoints = user?.restaurantLoyalty?.reduce((total: number, rl: any) => total + (rl.points || 0), 0) || 0;
  
  // Use provided orders or fallback to mock data
  const orderHistory = orders.length > 0 ? orders.map(order => {
    // Calculate correct total including tip
    const itemsTotal = order.items.reduce((sum: number, item: any) => {
      const itemTotal = item.price * item.quantity;
      const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
        mSum + (m.price * item.quantity), 0) || 0;
      return sum + itemTotal + modifiersTotal;
    }, 0);
    
    const totalWithTip = itemsTotal + (order.tip || 0);
    
    return {
      id: order.id,
      restaurantName: 'La Bella Vista',
      date: new Date(order.createdAt || Date.now()).toISOString().split('T')[0],
      pointsEarned: Math.floor(totalWithTip),
      items: order.items.map((item: any) => ({
        quantity: item.quantity,
        name: item.name
      })),
      total: totalWithTip,
      tip: order.tip || 0,
      loyaltyPointsRedeemed: order.loyaltyPointsRedeemed || 0,
      loyaltyDiscount: order.loyaltyDiscount || 0,
      numPeople: order.numPeople || 1,
      receiptRequested: order.receiptRequested || false,
      paymentMethod: order.paymentMethod,
      status: order.status, // Preserve actual order status
      originalOrder: order // Keep reference to full order for receipt
    };
  }) : [];

  return (
    <div className="min-h-screen bg-gray-50 pb-6">
      {/* Header */}
      <div className="bg-white border-b sticky top-0 z-10">
        <div className="max-w-4xl mx-auto p-4 flex items-center gap-3">
          <button onClick={onBack} className="p-2 -ml-2 hover:bg-gray-100 rounded-full">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <h1 className="text-xl">Order History</h1>
        </div>
      </div>

      <div className="max-w-4xl mx-auto p-4 space-y-4">
        {/* Loyalty Points Card */}
        <div className="bg-gradient-to-br from-gray-800 to-gray-900 text-white rounded-2xl p-6 flex items-center justify-between">
          <div>
            <div className="text-gray-300 text-sm">Total Loyalty Points</div>
            <div className="text-3xl mt-1">{loyaltyPoints} points</div>
          </div>
          <div className="w-14 h-14 bg-white/10 rounded-full flex items-center justify-center">
            <Star className="w-7 h-7 fill-white" />
          </div>
        </div>

        {/* Order History List */}
        <div className="space-y-4">
          {orderHistory.map((order) => (
            <div 
              key={order.id} 
              className="bg-white rounded-2xl p-5 space-y-4 shadow-sm"
            >
              {/* Header */}
              <div className="flex items-start justify-between">
                <div>
                  <h3 className="text-lg">{order.restaurantName}</h3>
                  <div className="text-sm text-gray-500 mt-1">{order.date}</div>
                </div>
                <div className="flex flex-col items-end gap-2">
                  <div className="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                    +{order.pointsEarned} pts
                  </div>
                  {order.loyaltyPointsRedeemed > 0 && (
                    <div className="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm">
                      -{order.loyaltyPointsRedeemed} pts
                    </div>
                  )}
                  {order.status === 'served' ? (
                    <div className="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs">
                      Serviert
                    </div>
                  ) : (
                    <div className="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs">
                      In Bearbeitung
                    </div>
                  )}
                </div>
              </div>

              {/* Items */}
              <div className="space-y-2 border-t pt-4">
                {order.items.map((item, index) => (
                  <div key={index} className="text-gray-700">
                    {item.quantity}x {item.name}
                  </div>
                ))}
              </div>

              {/* Review Stars (if reviewed) */}
              {order.originalOrder?.reviews && order.originalOrder.reviews.length > 0 && (
                <div className="border-t pt-4">
                  <div className="flex items-center gap-2 text-sm text-gray-600">
                    <span>Your review:</span>
                    <div className="flex items-center gap-1 text-yellow-500">
                      {[...Array(5)].map((_, i) => (
                        <Star
                          key={i}
                          className={`w-4 h-4 ${
                            i < Math.round(order.originalOrder.reviews.reduce((sum: number, r: any) => sum + r.rating, 0) / order.originalOrder.reviews.length)
                              ? 'fill-yellow-500'
                              : 'fill-gray-200 text-gray-200'
                          }`}
                        />
                      ))}
                    </div>
                    <span className="text-gray-500">
                      ({order.originalOrder.reviews.length} {order.originalOrder.reviews.length === 1 ? 'item' : 'items'} reviewed)
                    </span>
                  </div>
                </div>
              )}

              {/* Order details */}
              <div className="flex items-center gap-4 text-sm text-gray-600 border-t pt-4">
                <div className="flex items-center gap-1">
                  <Users className="w-4 h-4" />
                  <span>{order.numPeople} {order.numPeople === 1 ? 'Person' : 'Personen'}</span>
                </div>
                {order.receiptRequested && (
                  <div className="flex items-center gap-1">
                    <FileText className="w-4 h-4" />
                    <span>Rechnung angefordert</span>
                  </div>
                )}
              </div>

              {/* Total and tip */}
              <div className="border-t pt-4 space-y-2">
                {order.tip > 0 && (
                  <div className="flex justify-between text-sm text-gray-600">
                    <span>Trinkgeld</span>
                    <span>{currency}{order.tip.toFixed(2)}</span>
                  </div>
                )}
                {(order.loyaltyPointsRedeemed > 0 || order.loyaltyDiscount > 0) && (
                  <div className="flex justify-between text-sm text-green-600">
                    <span>Loyalty Discount ({order.loyaltyPointsRedeemed} points)</span>
                    <span>-{currency}{(order.loyaltyDiscount || 0).toFixed(2)}</span>
                  </div>
                )}
                <div className="flex items-center justify-between">
                  <span className="text-gray-600">Gesamt</span>
                  <span className="text-lg">{currency}{order.total.toFixed(2)}</span>
                </div>
              </div>

              {/* Action buttons */}
              <div className={`grid gap-3 ${onWriteReview && order.status === 'served' ? 'grid-cols-1 sm:grid-cols-3' : 'grid-cols-2'}`}>
                <Button 
                  onClick={() => generateReceipt(order.originalOrder || order, vendorSettings)} 
                  variant="outline"
                >
                  <FileText className="w-4 h-4 mr-2" />
                  Rechnung
                </Button>
                <Button 
                  onClick={() => onReorder(order)} 
                  variant="outline"
                >
                  <RotateCcw className="w-4 h-4 mr-2" />
                  Erneut bestellen
                </Button>
                {onWriteReview && order.status === 'served' && (
                  <Button 
                    onClick={() => onWriteReview(order)} 
                    variant="outline"
                  >
                    <Star className="w-4 h-4 mr-2" />
                    {order.originalOrder?.reviews && order.originalOrder.reviews.length > 0 
                      ? 'Bewertung bearbeiten' 
                      : 'Bewertung schreiben'}
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>

        {orderHistory.length === 0 && (
          <div className="text-center py-12 text-gray-500">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <RotateCcw className="w-8 h-8 text-gray-400" />
            </div>
            <p>No order history yet</p>
            <p className="text-sm mt-1">Your past orders will appear here</p>
          </div>
        )}
      </div>

      {/* Bottom System Bar */}
      <BottomSystemBar
        sessionPin={sessionPin}
        basketCount={basketCount || 0}
        pendingOrdersCount={pendingOrdersCount || 0}
        accentColor={vendorSettings?.accentColor || '#f59e0b'}
        onViewBasket={onViewBasket}
        onViewHistory={onBack}
        onCallWaiter={onCallWaiter}
      />
    </div>
  );
}