import { QrCode, ShoppingBag, Calendar, AlertCircle, Clock, Users } from 'lucide-react';
import { Button } from '../ui/button';

interface OrderingOptionsProps {
  onScanQR: () => void;
  onTakeaway: () => void;
  onReserveTable: () => void;
  restaurantStatus?: {
    isOpen: boolean;
    isBusy?: boolean; // High order volume
    slowPrep?: boolean; // Longer prep times
    takeawayLimited?: boolean; // Limited takeaway capacity
    avgPrepTime?: string; // e.g., "15-20 min"
  };
}

export function OrderingOptions({ 
  onScanQR, 
  onTakeaway, 
  onReserveTable,
  restaurantStatus = { isOpen: true }
}: OrderingOptionsProps) {
  const { isOpen, isBusy, slowPrep, takeawayLimited, avgPrepTime } = restaurantStatus;

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8">
      <div className="mb-6">
        <h2 className="text-2xl sm:text-3xl mb-2">How would you like to order?</h2>
        <p className="text-gray-600">Choose your preferred ordering method</p>
      </div>

      {/* Friction Warnings - Honesty > Fake Perfection */}
      {(isBusy || slowPrep || !isOpen) && (
        <div className="mb-6 space-y-3">
          {!isOpen && (
            <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
              <div className="flex items-start gap-3">
                <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-medium text-red-900 mb-1">Currently Closed</h4>
                  <p className="text-sm text-red-700">
                    This restaurant is not accepting orders right now. You can still reserve a table for later or browse the menu.
                  </p>
                </div>
              </div>
            </div>
          )}
          
          {isOpen && isBusy && (
            <div className="bg-orange-50 border-l-4 border-orange-500 p-4 rounded-r-lg">
              <div className="flex items-start gap-3">
                <Users className="w-5 h-5 text-orange-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-medium text-orange-900 mb-1">Busy Right Now</h4>
                  <p className="text-sm text-orange-700">
                    We're experiencing high order volume. Expect longer wait times than usual.
                  </p>
                </div>
              </div>
            </div>
          )}

          {isOpen && slowPrep && avgPrepTime && (
            <div className="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg">
              <div className="flex items-start gap-3">
                <Clock className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div>
                  <h4 className="font-medium text-blue-900 mb-1">Prep Time: {avgPrepTime}</h4>
                  <p className="text-sm text-blue-700">
                    Our dishes are freshly prepared. Please allow extra time for your order.
                  </p>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Scan QR Code */}
        <button
          onClick={onScanQR}
          disabled={!isOpen}
          className={`bg-white rounded-2xl p-6 border-2 transition-all group ${
            isOpen 
              ? 'border-gray-100 hover:border-orange-500 hover:shadow-lg cursor-pointer' 
              : 'border-gray-100 opacity-50 cursor-not-allowed'
          }`}
        >
          <div className={`w-16 h-16 rounded-2xl flex items-center justify-center mb-4 transition-colors ${
            isOpen
              ? 'bg-orange-100 group-hover:bg-orange-500'
              : 'bg-gray-100'
          }`}>
            <QrCode className={`w-8 h-8 transition-colors ${
              isOpen
                ? 'text-orange-600 group-hover:text-white'
                : 'text-gray-400'
            }`} />
          </div>
          <h3 className="text-lg mb-2">Scan QR Code</h3>
          <p className="text-sm text-gray-600 leading-relaxed">
            Order from your table using our QR menu system
          </p>
          {!isOpen && (
            <div className="mt-3 text-xs text-red-600 font-medium">
              Available when open
            </div>
          )}
        </button>

        {/* Takeaway */}
        <button
          onClick={onTakeaway}
          disabled={!isOpen || takeawayLimited}
          className={`bg-white rounded-2xl p-6 border-2 transition-all group relative ${
            isOpen && !takeawayLimited
              ? 'border-gray-100 hover:border-blue-500 hover:shadow-lg cursor-pointer' 
              : 'border-gray-100 opacity-50 cursor-not-allowed'
          }`}
        >
          {avgPrepTime && isOpen && !takeawayLimited && (
            <div className="absolute top-3 right-3 bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded-full font-medium">
              {avgPrepTime}
            </div>
          )}
          <div className={`w-16 h-16 rounded-2xl flex items-center justify-center mb-4 transition-colors ${
            isOpen && !takeawayLimited
              ? 'bg-blue-100 group-hover:bg-blue-500'
              : 'bg-gray-100'
          }`}>
            <ShoppingBag className={`w-8 h-8 transition-colors ${
              isOpen && !takeawayLimited
                ? 'text-blue-600 group-hover:text-white'
                : 'text-gray-400'
            }`} />
          </div>
          <h3 className="text-lg mb-2">Takeaway</h3>
          <p className="text-sm text-gray-600 leading-relaxed">
            Order for pickup and collect at your convenience
          </p>
          {!isOpen && (
            <div className="mt-3 text-xs text-red-600 font-medium">
              Available when open
            </div>
          )}
          {takeawayLimited && isOpen && (
            <div className="mt-3 text-xs text-orange-600 font-medium">
              Limited capacity right now
            </div>
          )}
        </button>

        {/* Reserve Table */}
        <button
          onClick={onReserveTable}
          className="bg-white rounded-2xl p-6 border-2 border-gray-100 hover:border-green-500 hover:shadow-lg transition-all group cursor-pointer"
        >
          <div className="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-green-500 transition-colors">
            <Calendar className="w-8 h-8 text-green-600 group-hover:text-white transition-colors" />
          </div>
          <h3 className="text-lg mb-2">Reserve a Table</h3>
          <p className="text-sm text-gray-600 leading-relaxed">
            Book your table in advance for dine-in
          </p>
          <div className="mt-3 text-xs text-green-600 font-medium">
            Always available
          </div>
        </button>
      </div>

      {/* Primary CTA - One Clear Action */}
      {isOpen && !isBusy && (
        <div className="mt-8 text-center">
          <Button 
            size="lg" 
            onClick={onScanQR}
            className="px-8 py-6 text-lg"
          >
            <QrCode className="w-5 h-5 mr-2" />
            Start Ordering Now
          </Button>
          <p className="text-sm text-gray-500 mt-3">
            Scan QR code at your table or order takeaway
          </p>
        </div>
      )}
    </div>
  );
}