import { Phone, Plus, ArrowRight } from 'lucide-react';
import { Button } from './ui/button';

interface SharedBasketProps {
  session: any;
  items: any[];
  currentUser: any;
  onCallWaiter: () => void;
  onAddMore: () => void;
  onSubmitOrder: () => void;
}

export function SharedBasket({
  session,
  items,
  currentUser,
  onCallWaiter,
  onAddMore,
  onSubmitOrder
}: SharedBasketProps) {
  const calculateTotals = () => {
    // Prices INCLUDE VAT and service fee already
    // Calculate gross total (what customer actually pays)
    const grossTotal = items.reduce((sum, item) => {
      const itemTotal = item.price * item.quantity;
      const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
        mSum + (m.price * item.quantity), 0) || 0;
      return sum + itemTotal + modifiersTotal;
    }, 0);
    
    // Reverse calculate breakdown for receipt: grossTotal = netAmount * 1.05 * 1.13
    const netAmount = grossTotal / 1.1865;
    const serviceFee = netAmount * 0.05;
    const vatPercent = 13;
    const vatAmount = (netAmount + serviceFee) * (vatPercent / 100);

    return { 
      subtotal: netAmount, 
      serviceFee, 
      vatPercent, 
      vatAmount, 
      total: grossTotal 
    };
  };

  const totals = calculateTotals();

  const getContributorName = (contributorId: string) => {
    const contributor = session?.contributors?.find((c: any) => c.id === contributorId);
    return contributor?.name || 'Unknown';
  };

  return (
    <div className="min-h-screen bg-gray-50 pb-32">
      {/* Header */}
      <div className="bg-white border-b p-4">
        <h1 className="text-xl">Shared Order — Table {session?.tableId}</h1>
        {session?.sharedBasket && session?.contributors?.length > 1 && (
          <p className="text-sm text-gray-600 mt-1">
            You are ordering with {session.contributors
              .filter((c: any) => c.id !== currentUser?.id)
              .map((c: any) => c.name)
              .join(', ')}
          </p>
        )}
      </div>

      <div className="max-w-2xl mx-auto p-4 space-y-4">
        {items.length === 0 ? (
          <div className="bg-white rounded-lg p-12 text-center">
            <p className="text-gray-500">Your basket is empty</p>
            <Button onClick={onAddMore} className="mt-4">
              Browse menu
            </Button>
          </div>
        ) : (
          <>
            {/* Items */}
            <div className="bg-white rounded-lg divide-y">
              {items.map((item, index) => {
                const modifiersTotal = item.modifiers?.reduce((sum: number, m: any) => 
                  sum + (m.price * item.quantity), 0) || 0;
                const itemTotalWithModifiers = (item.price * item.quantity) + modifiersTotal;
                
                return (
                  <div key={index} className="p-4 space-y-2">
                    <div className="flex justify-between items-start">
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <span>{item.quantity}x</span>
                          <span>{item.name}</span>
                        </div>
                        {item.modifiers && item.modifiers.length > 0 && (
                          <div className="text-sm text-gray-600 mt-1">
                            {item.modifiers.map((m: any) => `+ ${m.name} (+€${m.price.toFixed(2)})`).join(', ')}
                          </div>
                        )}
                        {item.specialRequest && (
                          <div className="text-sm text-gray-600 mt-1 italic">
                            Note: {item.specialRequest}
                          </div>
                        )}
                        {session?.sharedBasket && (
                          <div className="text-sm text-gray-500 mt-1">
                            Added by {getContributorName(item.addedBy)}
                          </div>
                        )}
                      </div>
                      <div className="text-right">
                        <div>€{itemTotalWithModifiers.toFixed(2)}</div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Price summary */}
            <div className="bg-white rounded-lg p-4 space-y-2">
              <div className="flex justify-between">
                <span className="font-medium">Total</span>
                <span className="font-medium">€{totals.total.toFixed(2)}</span>
              </div>
              <div className="text-xs text-gray-500 space-y-1 pt-2 border-t">
                <div className="flex justify-between">
                  <span>Net amount</span>
                  <span>€{totals.subtotal.toFixed(2)}</span>
                </div>
                <div className="flex justify-between">
                  <span>Service fee (5%)</span>
                  <span>€{totals.serviceFee.toFixed(2)}</span>
                </div>
                <div className="flex justify-between">
                  <span>VAT ({totals.vatPercent}%)</span>
                  <span>€{totals.vatAmount.toFixed(2)}</span>
                </div>
              </div>
            </div>

            {session?.sharedBasket && (
              <div className="bg-blue-50 p-3 rounded-lg text-sm text-gray-700">
                Only you can edit items you added. Others can add more items.
              </div>
            )}
          </>
        )}
      </div>

      {/* Fixed bottom buttons */}
      <div className="fixed bottom-0 left-0 right-0 bg-white border-t p-4">
        <div className="max-w-2xl mx-auto grid grid-cols-3 gap-2">
          <Button variant="outline" onClick={onCallWaiter}>
            <Phone className="w-4 h-4 mr-2" />
            Call waiter
          </Button>
          <Button variant="outline" onClick={onAddMore}>
            <Plus className="w-4 h-4 mr-2" />
            Add more
          </Button>
          <Button onClick={onSubmitOrder} disabled={items.length === 0}>
            Submit order
            <ArrowRight className="w-4 h-4 ml-2" />
          </Button>
        </div>
      </div>
    </div>
  );
}
