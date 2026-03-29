import { useState, useMemo, useEffect } from 'react';
import { Button } from './ui/button';
import { X, User, QrCode, Banknote, CreditCard, Check, Clock, AlertCircle, ChevronRight } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { getTranslatedField } from '../utils/translations';
import QRCode from 'qrcode';

interface SplitBillModalProps {
  total: number;
  numPeople: number;
  orderItems: any[];
  orderId: string;
  tableNumber: string;
  onConfirm: (data: any) => void;
  onClose: () => void;
}

interface PersonSplit {
  personNumber: number;
  amount: number;
  status: 'pending' | 'paying' | 'paid';
  paymentMethod: 'card' | 'cash' | 'qr' | null;
  paidAt?: string;
  qrCode?: string;
  splitId?: string;
}

export function SplitBillModal({ total, numPeople, orderItems, orderId, tableNumber, onConfirm, onClose }: SplitBillModalProps) {
  const { t, language } = useLanguage();
  const [splitType, setSplitType] = useState<'equal' | 'custom'>('equal');
  const [step, setStep] = useState<'select-type' | 'assign-items' | 'payment'>('select-type');
  const [currentPerson, setCurrentPerson] = useState(0);
  const [personSelections, setPersonSelections] = useState<Map<number, Set<string>>>(
    new Map(Array.from({ length: numPeople }, (_, i) => [i, new Set<string>()]))
  );
  const [personSplits, setPersonSplits] = useState<PersonSplit[]>(
    Array.from({ length: numPeople }, (_, i) => ({
      personNumber: i + 1,
      amount: total / numPeople,
      status: 'pending',
      paymentMethod: null,
    }))
  );
  const [showQRModal, setShowQRModal] = useState<number | null>(null);
  const [qrCodeDataUrl, setQrCodeDataUrl] = useState<string>('');

  // Track which items are selected by which persons
  const itemSelectionCounts = useMemo(() => {
    const counts = new Map<string, number>();
    orderItems.forEach(item => {
      const uniqueId = item.id || `${item.name}-${item.price}`;
      let selectedBy = 0;
      personSelections.forEach((selections) => {
        if (selections.has(uniqueId)) {
          selectedBy++;
        }
      });
      counts.set(uniqueId, selectedBy);
    });
    return counts;
  }, [personSelections, orderItems]);

  // Calculate person total based on item selections
  const calculatePersonTotal = (personIndex: number) => {
    const selections = personSelections.get(personIndex);
    if (!selections) return 0;

    let total = 0;
    orderItems.forEach(item => {
      const uniqueId = item.id || `${item.name}-${item.price}`;
      if (selections.has(uniqueId)) {
        const sharedBy = itemSelectionCounts.get(uniqueId) || 1;
        const itemTotal = item.price * item.quantity;
        const modifiersTotal = item.modifiers?.reduce((sum: number, m: any) => sum + (m.price * item.quantity), 0) || 0;
        total += (itemTotal + modifiersTotal) / sharedBy;
      }
    });
    return total;
  };

  // Check if all items are assigned
  const allItemsAssigned = useMemo(() => {
    return orderItems.every(item => {
      const uniqueId = item.id || `${item.name}-${item.price}`;
      const count = itemSelectionCounts.get(uniqueId) || 0;
      return count > 0;
    });
  }, [itemSelectionCounts, orderItems]);

  // Calculate totals
  const paidTotal = useMemo(() => 
    personSplits.filter(p => p.status === 'paid').reduce((sum, p) => sum + p.amount, 0),
    [personSplits]
  );

  const pendingTotal = useMemo(() =>
    personSplits.filter(p => p.status === 'pending').reduce((sum, p) => sum + p.amount, 0),
    [personSplits]
  );

  const totalAssigned = useMemo(() =>
    personSplits.reduce((sum, p) => sum + p.amount, 0),
    [personSplits]
  );

  // Update amounts when item selections change (for custom split)
  useEffect(() => {
    if (splitType === 'custom' && step === 'payment') {
      setPersonSplits(prev => prev.map((p, idx) => 
        p.status === 'paid' ? p : { ...p, amount: calculatePersonTotal(idx) }
      ));
    }
  }, [personSelections, splitType, step]);

  // Update amounts when split type changes
  useEffect(() => {
    if (splitType === 'equal') {
      const equalAmount = total / numPeople;
      setPersonSplits(prev => prev.map(p => 
        p.status === 'paid' ? p : { ...p, amount: equalAmount }
      ));
    }
  }, [splitType, total, numPeople]);

  const handleItemToggle = (itemId: string, personIndex: number) => {
    setPersonSelections(prev => {
      const newSelections = new Map(prev);
      const personSet = new Set(newSelections.get(personIndex));
      
      if (personSet.has(itemId)) {
        personSet.delete(itemId);
      } else {
        personSet.add(itemId);
      }
      
      newSelections.set(personIndex, personSet);
      return newSelections;
    });
  };

  const handleAmountChange = (personIndex: number, newAmount: number) => {
    setPersonSplits(prev => prev.map((p, idx) => 
      idx === personIndex ? { ...p, amount: Math.max(0, newAmount) } : p
    ));
  };

  const handlePayNow = async (personIndex: number) => {
    setPersonSplits(prev => prev.map((p, idx) => 
      idx === personIndex ? { ...p, status: 'paying', paymentMethod: 'card' } : p
    ));
    
    // Simulate successful payment
    setTimeout(() => {
      setPersonSplits(prev => prev.map((p, idx) => 
        idx === personIndex ? { 
          ...p, 
          status: 'paid', 
          paidAt: new Date().toISOString() 
        } : p
      ));
    }, 1000);
  };

  const handleMarkAsCash = (personIndex: number) => {
    setPersonSplits(prev => prev.map((p, idx) => 
      idx === personIndex ? { 
        ...p, 
        status: 'pending', // Keep as pending until waiter confirms cash received
        paymentMethod: 'cash',
        paidAt: new Date().toISOString() 
      } : p
    ));
  };

  const handleGenerateQR = async (personIndex: number) => {
    const split = personSplits[personIndex];
    const splitId = `${orderId}-split-${split.personNumber}-${Date.now()}`;
    
    const qrData = JSON.stringify({
      type: 'split_payment',
      orderId,
      tableNumber,
      splitId,
      amount: split.amount,
      personNumber: split.personNumber,
    });

    try {
      const qrDataUrl = await QRCode.toDataURL(qrData, {
        width: 300,
        margin: 2,
        color: {
          dark: '#000000',
          light: '#FFFFFF',
        },
      });

      setPersonSplits(prev => prev.map((p, idx) => 
        idx === personIndex ? { 
          ...p, 
          qrCode: qrDataUrl,
          splitId,
          status: 'pending'
        } : p
      ));

      setQrCodeDataUrl(qrDataUrl);
      setShowQRModal(personIndex);
    } catch (error) {
      console.error('Error generating QR code:', error);
    }
  };

  const handleProceedToPayment = () => {
    if (splitType === 'custom') {
      // Update amounts based on item selections before moving to payment
      setPersonSplits(prev => prev.map((p, idx) => ({
        ...p,
        amount: calculatePersonTotal(idx)
      })));
    }
    setStep('payment');
  };

  const handleConfirm = () => {
    const overpayment = totalAssigned - total;
    
    onConfirm({
      type: splitType,
      count: numPeople,
      splits: personSplits,
      totalAssigned,
      paidTotal,
      pendingTotal,
      tip: overpayment > 0 ? overpayment : 0,
      selections: splitType === 'custom' ? Object.fromEntries(personSelections) : null,
    });
  };

  const allPaid = personSplits.every(p => p.status === 'paid');
  const allHandled = personSplits.every(p => p.status === 'paid' || (p.status === 'pending' && p.paymentMethod === 'cash' && p.amount > 0) || p.amount === 0);
  const overpayment = totalAssigned - total;

  // STEP 1: Select split type
  if (step === 'select-type') {
    return (
      <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div className="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
          <div className="flex justify-between items-center p-6 border-b sticky top-0 bg-white z-10">
            <div>
              <h2 className="text-xl font-semibold">{t('split_the_bill', 'Split the Bill')}</h2>
              <p className="text-sm text-gray-600">{t('table', 'Table')} {tableNumber}</p>
            </div>
            <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full transition-colors">
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="p-6 space-y-6">
            {/* Total Bill */}
            <div className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200">
              <div className="text-sm text-gray-600">{t('total_bill', 'Total Bill')}</div>
              <div className="text-3xl font-bold">€{total.toFixed(2)}</div>
              <div className="text-sm text-gray-600 mt-1">{numPeople} {t('people', 'people')}</div>
            </div>

            {/* Split Type Selection */}
            <div>
              <div className="text-sm font-medium mb-3">{t('how_split', 'How would you like to split?')}</div>
              <div className="space-y-3">
                <button
                  onClick={() => {
                    setSplitType('equal');
                    handleProceedToPayment();
                  }}
                  className="w-full bg-white border-2 border-gray-200 rounded-xl p-4 hover:border-orange-500 hover:bg-orange-50 transition-all text-left"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <div className="font-semibold text-lg mb-1">{t('equal_split', 'Equal Split')}</div>
                      <div className="text-sm text-gray-600">{t('evenly', 'Everyone pays equally')}</div>
                      <div className="text-xl font-bold text-orange-600 mt-2">€{(total / numPeople).toFixed(2)} {t('per_person', 'per person')}</div>
                    </div>
                    <ChevronRight className="w-6 h-6 text-gray-400" />
                  </div>
                </button>

                <button
                  onClick={() => {
                    setSplitType('custom');
                    setStep('assign-items');
                  }}
                  className="w-full bg-white border-2 border-gray-200 rounded-xl p-4 hover:border-orange-500 hover:bg-orange-50 transition-all text-left"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <div className="font-semibold text-lg mb-1">{t('by_items', 'By Items')}</div>
                      <div className="text-sm text-gray-600">{t('tick_your_orders', 'Each person ticks their items')}</div>
                      <div className="text-sm text-gray-500 mt-2">{t('flexible', 'Flexible - shared items split automatically')}</div>
                    </div>
                    <ChevronRight className="w-6 h-6 text-gray-400" />
                  </div>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // STEP 2: Assign items (only for custom split)
  if (step === 'assign-items' && splitType === 'custom') {
    return (
      <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
          <div className="flex justify-between items-center p-6 border-b sticky top-0 bg-white z-10">
            <div>
              <h2 className="text-xl font-semibold">{t('assign_items', 'Assign Items')}</h2>
              <p className="text-sm text-gray-600">{t('tick_items_instruction', 'Tick the items each person ordered')}</p>
            </div>
            <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full transition-colors">
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="p-6 space-y-6">
            {/* Person Selector */}
            <div>
              <div className="text-sm font-medium mb-3">{t('select_person', 'Select person to assign items')}</div>
              <div className="grid grid-cols-3 gap-2">
                {Array.from({ length: numPeople }).map((_, index) => {
                  const personTotal = calculatePersonTotal(index);
                  return (
                    <button
                      key={index}
                      onClick={() => setCurrentPerson(index)}
                      className={`flex flex-col items-center gap-2 px-3 py-3 border-2 rounded-xl transition-all ${
                        currentPerson === index ? 'border-orange-500 bg-orange-50' : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <User className="w-5 h-5" />
                      <div className="text-center">
                        <div className="text-sm font-medium">{t('person', 'Person')} {index + 1}</div>
                        <div className="text-xs text-gray-600">€{personTotal.toFixed(2)}</div>
                      </div>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Instructions */}
            <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
              <div className="text-sm text-blue-900 mb-2">
                <strong>{t('person', 'Person')} {currentPerson + 1}:</strong> {t('tick_items_help', 'Tick the items you ordered. Shared items will be split automatically.')}
              </div>
              <div className="flex gap-4 text-xs">
                <div className="flex items-center gap-1">
                  <div className="w-4 h-4 border-2 border-orange-500 bg-orange-50 rounded flex items-center justify-center">
                    <Check className="w-3 h-3 text-orange-600" />
                  </div>
                  <span className="text-gray-700">{t('your_items', 'Your items')}</span>
                </div>
                <div className="flex items-center gap-1">
                  <div className="w-4 h-4 border-2 border-gray-300 bg-gray-50 rounded flex items-center justify-center">
                    <Check className="w-3 h-3 text-gray-400" />
                  </div>
                  <span className="text-gray-700">{t('others_items', 'Others\' items')}</span>
                </div>
              </div>
            </div>

            {/* Order Items List */}
            <div>
              <div className="text-sm font-medium mb-3">{t('all_orders', 'All Orders')}</div>
              <div className="space-y-2">
                {orderItems.map((item, idx) => {
                  const uniqueId = item.id || `${item.name}-${item.price}`;
                  const isSelectedByCurrentPerson = personSelections.get(currentPerson)?.has(uniqueId);
                  const sharedBy = itemSelectionCounts.get(uniqueId) || 0;
                  const isSelectedByOthers = sharedBy > 0 && !isSelectedByCurrentPerson;
                  
                  const itemTotal = item.price * item.quantity;
                  const modifiersTotal = item.modifiers?.reduce((sum: number, m: any) => sum + (m.price * item.quantity), 0) || 0;
                  const totalItemCost = itemTotal + modifiersTotal;
                  const splitCost = sharedBy > 0 ? totalItemCost / (sharedBy + (isSelectedByCurrentPerson ? 0 : 1)) : totalItemCost;

                  const translatedName = getTranslatedField(item, 'name', language);

                  return (
                    <label
                      key={idx}
                      className={`flex items-start gap-3 p-4 border-2 rounded-xl cursor-pointer transition-all ${
                        isSelectedByCurrentPerson
                          ? 'border-orange-500 bg-orange-50'
                          : isSelectedByOthers
                          ? 'border-gray-300 bg-gray-50'
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={isSelectedByCurrentPerson || false}
                        onChange={() => handleItemToggle(uniqueId, currentPerson)}
                        className="mt-1 w-5 h-5 rounded border-gray-300"
                      />
                      <div className="flex-1 min-w-0">
                        <div className="flex justify-between gap-2">
                          <div className="flex-1">
                            <div className="font-medium">
                              {item.quantity}x {translatedName}
                            </div>
                            {item.modifiers && item.modifiers.length > 0 && (
                              <div className="text-sm text-gray-600 mt-1">
                                {item.modifiers.map((m: any, i: number) => (
                                  <div key={i}>+ {m.name} {m.price > 0 && `(€${m.price.toFixed(2)})`}</div>
                                ))}
                              </div>
                            )}
                            {sharedBy > 0 && (
                              <div className="text-xs text-blue-600 mt-1">
                                {t('shared_by', 'Shared by')} {sharedBy} {sharedBy === 1 ? t('person', 'person') : t('people', 'people')}
                              </div>
                            )}
                          </div>
                          <div className="text-right">
                            <div className="font-semibold">€{totalItemCost.toFixed(2)}</div>
                            {isSelectedByCurrentPerson && sharedBy > 0 && (
                              <div className="text-sm text-orange-600">€{splitCost.toFixed(2)} {t('each', 'each')}</div>
                            )}
                          </div>
                        </div>
                      </div>
                    </label>
                  );
                })}
              </div>
            </div>

            {/* Summary */}
            <div className="bg-gray-50 rounded-xl p-4 border border-gray-200">
              <div className="flex justify-between items-center mb-2">
                <span className="font-medium">{t('person', 'Person')} {currentPerson + 1} {t('total', 'Total')}</span>
                <span className="text-2xl font-bold text-orange-600">€{calculatePersonTotal(currentPerson).toFixed(2)}</span>
              </div>
              {!allItemsAssigned && (
                <div className="text-sm text-yellow-700 bg-yellow-50 rounded p-2 mt-2">
                  ⚠️ {t('not_all_items_assigned', 'Not all items have been assigned yet')}
                </div>
              )}
            </div>
          </div>

          {/* Footer */}
          <div className="border-t p-6 bg-gray-50 sticky bottom-0">
            <div className="flex gap-3">
              <Button
                onClick={() => setStep('select-type')}
                variant="outline"
                className="flex-1"
              >
                {t('back', 'Back')}
              </Button>
              <Button
                onClick={handleProceedToPayment}
                className="flex-1 bg-orange-500 hover:bg-orange-600"
              >
                {t('continue_to_payment', 'Continue to Payment')}
              </Button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // STEP 3: Payment (for both equal and custom)
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="flex justify-between items-center p-6 border-b sticky top-0 bg-white z-10">
          <div>
            <h2 className="text-xl font-semibold">{t('split_payment', 'Split Payment')}</h2>
            <p className="text-sm text-gray-600">{t('table', 'Table')} {tableNumber}</p>
          </div>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full transition-colors">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 space-y-6">
          {/* Total Bill Summary */}
          <div className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 border border-gray-200">
            <div className="flex justify-between items-start mb-3">
              <div>
                <div className="text-sm text-gray-600">{t('total_bill', 'Total Bill')}</div>
                <div className="text-3xl font-bold">€{total.toFixed(2)}</div>
              </div>
              <div className="text-right">
                <div className="text-sm text-gray-600">{splitType === 'equal' ? t('equal_split', 'Equal Split') : t('by_items', 'By Items')}</div>
                <div className="text-2xl font-semibold">{numPeople} {t('people', 'people')}</div>
              </div>
            </div>
            
            <div className="grid grid-cols-3 gap-3 text-center">
              <div className="bg-green-50 rounded-lg p-2 border border-green-200">
                <div className="text-xs text-green-700">{t('paid', 'Paid')}</div>
                <div className="text-lg font-semibold text-green-700">€{paidTotal.toFixed(2)}</div>
              </div>
              <div className="bg-orange-50 rounded-lg p-2 border border-orange-200">
                <div className="text-xs text-orange-700">{t('pending', 'Pending')}</div>
                <div className="text-lg font-semibold text-orange-700">€{pendingTotal.toFixed(2)}</div>
              </div>
              <div className={`rounded-lg p-2 border ${overpayment > 0 ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'}`}>
                <div className={`text-xs ${overpayment > 0 ? 'text-blue-700' : 'text-gray-600'}`}>
                  {overpayment > 0 ? t('tip', 'Tip') : t('balance', 'Balance')}
                </div>
                <div className={`text-lg font-semibold ${overpayment > 0 ? 'text-blue-700' : 'text-gray-700'}`}>
                  €{Math.abs(totalAssigned - total).toFixed(2)}
                </div>
              </div>
            </div>
          </div>

          {/* Person Splits */}
          <div>
            <div className="flex items-center justify-between mb-3">
              <div className="text-sm font-medium">{t('individual_payments', 'Individual Payments')}</div>
              <div className="text-xs text-gray-600">
                {t('overpay_becomes_tip', 'Overpayment becomes tip')}
              </div>
            </div>

            <div className="space-y-3">
              {personSplits.map((split, index) => (
                <div
                  key={index}
                  className={`border-2 rounded-xl p-4 transition-all ${
                    split.status === 'paid'
                      ? 'border-green-500 bg-green-50'
                      : split.status === 'paying'
                      ? 'border-orange-500 bg-orange-50'
                      : 'border-gray-200 bg-white'
                  }`}
                >
                  <div className="flex items-start justify-between mb-3">
                    <div className="flex items-center gap-3">
                      <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
                        split.status === 'paid' ? 'bg-green-500 text-white' :
                        split.status === 'paying' ? 'bg-orange-500 text-white' :
                        'bg-gray-200 text-gray-700'
                      }`}>
                        {split.status === 'paid' ? (
                          <Check className="w-5 h-5" />
                        ) : (
                          <User className="w-5 h-5" />
                        )}
                      </div>
                      <div>
                        <div className="font-medium">
                          {t('person', 'Person')} {split.personNumber}
                        </div>
                        {split.status === 'paid' && (
                          <div className="flex items-center gap-1 text-xs text-green-700">
                            <Check className="w-3 h-3" />
                            {t('paid', 'Paid')} • {split.paymentMethod === 'cash' ? t('cash', 'Cash') : t('card', 'Card')}
                          </div>
                        )}
                        {split.status === 'paying' && (
                          <div className="flex items-center gap-1 text-xs text-orange-700">
                            <Clock className="w-3 h-3 animate-spin" />
                            {t('processing', 'Processing...')}
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Amount Input/Display */}
                    <div className="text-right">
                      {splitType === 'custom' && split.status !== 'paid' ? (
                        <div className="flex items-center gap-1">
                          <span className="text-sm text-gray-600">€</span>
                          <input
                            type="number"
                            value={split.amount.toFixed(2)}
                            onChange={(e) => handleAmountChange(index, parseFloat(e.target.value) || 0)}
                            className="w-20 px-2 py-1 text-right border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
                            step="0.01"
                            min="0"
                          />
                        </div>
                      ) : (
                        <div className="text-xl font-bold">€{split.amount.toFixed(2)}</div>
                      )}
                    </div>
                  </div>

                  {/* Payment Actions */}
                  {split.status === 'pending' && (
                    <div className="space-y-2">
                      <div className="grid grid-cols-3 gap-2">
                        <button
                          onClick={() => handlePayNow(index)}
                          className="flex flex-col items-center gap-1 p-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors"
                        >
                          <CreditCard className="w-5 h-5" />
                          <span className="text-xs">{t('pay_now', 'Pay Now')}</span>
                        </button>
                        <button
                          onClick={() => handleGenerateQR(index)}
                          className="flex flex-col items-center gap-1 p-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors"
                        >
                          <QrCode className="w-5 h-5" />
                          <span className="text-xs">{t('show_qr', 'Show QR')}</span>
                        </button>
                        <button
                          onClick={() => handleMarkAsCash(index)}
                          className="flex flex-col items-center gap-1 p-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                        >
                          <Banknote className="w-5 h-5" />
                          <span className="text-xs">{t('mark_cash', 'Mark Cash')}</span>
                        </button>
                      </div>
                      <p className="text-xs text-gray-600 text-center">
                        {t('payment_options_help', 'Pay directly, generate QR for another phone, or mark as cash')}
                      </p>
                    </div>
                  )}

                  {split.status === 'paid' && split.paidAt && (
                    <div className="text-xs text-gray-600 mt-2">
                      {t('paid_at', 'Paid at')} {new Date(split.paidAt).toLocaleTimeString()}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>

          {/* Warnings */}
          {totalAssigned < total && (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 flex items-start gap-2">
              <AlertCircle className="w-5 h-5 text-yellow-700 shrink-0 mt-0.5" />
              <div className="text-sm text-yellow-800">
                {t('underpayment_warning', 'Total assigned amounts are less than the bill. Please adjust.')}
                <div className="font-semibold mt-1">
                  {t('missing', 'Missing')}: €{(total - totalAssigned).toFixed(2)}
                </div>
              </div>
            </div>
          )}

          {overpayment > 0.01 && (
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-start gap-2">
              <AlertCircle className="w-5 h-5 text-blue-700 shrink-0 mt-0.5" />
              <div className="text-sm text-blue-800">
                {t('overpayment_tip', 'The total assigned is more than the bill. The extra amount will be added as a tip.')}
                <div className="font-semibold mt-1">
                  {t('tip_amount', 'Tip')}: €{overpayment.toFixed(2)}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="border-t p-6 bg-gray-50 sticky bottom-0">
          <div className="flex gap-3">
            <Button
              onClick={() => splitType === 'custom' ? setStep('assign-items') : setStep('select-type')}
              variant="outline"
              className="flex-1"
            >
              {t('back', 'Back')}
            </Button>
            <Button
              onClick={handleConfirm}
              disabled={!allHandled}
              className={`flex-1 ${allHandled ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400'}`}
            >
              {allHandled ? (
                <>
                  <Check className="w-4 h-4 mr-2" />
                  {t('complete_payment', 'Complete Payment')}
                </>
              ) : (
                t('waiting_for_payments', 'Waiting for Payments...')
              )}
            </Button>
          </div>
        </div>
      </div>

      {/* QR Code Modal */}
      {showQRModal !== null && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-[60] p-4">
          <div className="bg-white rounded-2xl max-w-sm w-full p-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-semibold">
                {t('scan_to_pay', 'Scan to Pay')}
              </h3>
              <button
                onClick={() => setShowQRModal(null)}
                className="p-2 hover:bg-gray-100 rounded-full"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="text-center space-y-4">
              <div className="bg-gray-50 rounded-xl p-4">
                <img
                  src={qrCodeDataUrl}
                  alt="Payment QR Code"
                  className="w-full max-w-[250px] mx-auto"
                />
              </div>

              <div>
                <div className="text-sm text-gray-600 mb-1">
                  {t('person', 'Person')} {personSplits[showQRModal].personNumber} {t('should_pay', 'should pay')}
                </div>
                <div className="text-3xl font-bold text-orange-600">
                  €{personSplits[showQRModal].amount.toFixed(2)}
                </div>
              </div>

              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-blue-900">
                {t('qr_instructions', 'The other person should scan this QR code with their phone camera or QR scanner app to complete their payment.')}
              </div>

              <Button
                onClick={() => setShowQRModal(null)}
                className="w-full"
              >
                {t('close', 'Close')}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}