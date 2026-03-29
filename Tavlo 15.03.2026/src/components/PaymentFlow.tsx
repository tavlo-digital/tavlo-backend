import { useState } from 'react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { Checkbox } from './ui/checkbox';
import { CreditCard, Smartphone, Banknote, Check, Award, Gift } from 'lucide-react';
import { SplitBillModal } from './SplitBillModal';
import { ReviewModal } from './ReviewModal';
import { useLanguage } from '../contexts/LanguageContext';

interface PaymentFlowProps {
  total: number;
  numPeople?: number;
  restaurantName?: string;
  orderItems?: any[];
  onPaymentComplete: (paymentData: any) => void;
  onBack: () => void;
  onTrackOrder?: () => void;
  onViewReceipt?: () => void;
  onGoToMenu?: () => void;
  vendorSettings?: any; // Vendor settings to control payment methods
  skipChoice?: boolean; // Skip the choice screen and go directly to payment
  customerLoyaltyPoints?: number; // Customer's current loyalty points balance
  customerId?: string; // Customer ID for loyalty tracking
  isTakeaway?: boolean; // Whether this is a takeaway order (disables cash to prevent no-shows)
}

export function PaymentFlow({ total, numPeople = 2, restaurantName = 'Restaurant', orderItems = [], onPaymentComplete, onBack, onTrackOrder, onViewReceipt, onGoToMenu, vendorSettings, skipChoice = false, customerLoyaltyPoints = 0, customerId = '', isTakeaway = false }: PaymentFlowProps) {
  const { t } = useLanguage();
  
  // Helper function to check if payment method is enabled
  const isPaymentMethodEnabled = (method: string) => {
    if (!vendorSettings) return true; // Default to enabled if settings not loaded
    
    switch (method) {
      case 'apple':
        return vendorSettings.acceptApplePay !== false;
      case 'google':
        return vendorSettings.acceptGooglePay !== false;
      case 'card':
        return vendorSettings.acceptCard !== false;
      case 'cash':
        // For dine-in: check acceptCash
        // For takeaway: check acceptCashTakeaway
        if (isTakeaway) {
          const cashTakeawayEnabled = vendorSettings.acceptCashTakeaway !== false;
          console.log('💵 Cash takeaway check:', {
            isTakeaway,
            acceptCashTakeaway: vendorSettings.acceptCashTakeaway,
            enabled: cashTakeawayEnabled
          });
          return cashTakeawayEnabled;
        }
        return vendorSettings.acceptCash !== false;
      default:
        return true;
    }
  };
  const [step, setStep] = useState<'choice' | 'payment' | 'card-form' | 'success'>('payment');
  const [paymentMethod, setPaymentMethod] = useState<'card' | 'apple' | 'google' | 'cash' | null>(null);
  const [tipPercent, setTipPercent] = useState(0);
  const [customTip, setCustomTip] = useState('');
  const [needReceipt, setNeedReceipt] = useState(false);
  const [splitBill, setSplitBill] = useState(false);
  const [splitData, setSplitData] = useState<any>(null);
  const [orderId, setOrderId] = useState('');
  const [showSplitModal, setShowSplitModal] = useState(false);
  const [showReviewModal, setShowReviewModal] = useState(false);
  const [paymentData, setPaymentData] = useState<any>(null); // Store payment data until confirmed
  
  // Loyalty points state
  const [pointsToRedeem, setPointsToRedeem] = useState(0);
  
  // Card form state
  const [cardNumber, setCardNumber] = useState('');
  const [cardName, setCardName] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvv, setCardCvv] = useState('');
  const [cardErrors, setCardErrors] = useState<any>({});

  // Loyalty settings from vendor
  const loyaltyEnabled = vendorSettings?.enableLoyalty !== false;
  const pointsPerEuro = vendorSettings?.pointsPerEuro || 1;
  const minimumRedemption = vendorSettings?.minimumRedemption || 100;
  const pointValueInEuros = vendorSettings?.redemptionRate || 0.05; // Default: 100 points = €5, so 1 point = €0.05
  
  // Calculate available redemption
  const maxRedeemablePoints = Math.floor(customerLoyaltyPoints / minimumRedemption) * minimumRedemption;
  const maxDiscountAmount = maxRedeemablePoints * pointValueInEuros;
  
  // Ensure discount doesn't exceed order total
  const actualDiscountAmount = Math.min(pointsToRedeem * pointValueInEuros, total);
  
  // Calculate potential points earning from this order
  const potentialPointsEarning = Math.floor(total * pointsPerEuro);

  const calculateTip = () => {
    if (customTip) {
      return parseFloat(customTip) || 0;
    }
    return total * (tipPercent / 100);
  };

  // Calculate total after loyalty discount
  const totalAfterDiscount = Math.max(0, total - actualDiscountAmount);
  const finalTotal = totalAfterDiscount + calculateTip();

  const handlePaymentChoice = (choice: 'now' | 'split' | 'cash') => {
    if (choice === 'now') {
      setStep('payment');
    } else if (choice === 'split') {
      setShowSplitModal(true);
    } else {
      // Cash payment - mark as pay later
      setPaymentData({
        paymentMethod: 'cash',
        tip: calculateTip(),
        receiptRequested: needReceipt,
        split: null,
        payLater: true
      });
      setStep('success');
    }
  };

  const handleSplitConfirm = (split: any) => {
    setSplitBill(true);
    setSplitData(split);
    setShowSplitModal(false);
    
    // Call onPaymentComplete with split data
    const paymentData = {
      paymentMethod: 'split',
      tip: split.tip || 0,
      receiptRequested: needReceipt,
      split: split,
      splitPayment: true
    };
    
    onPaymentComplete(paymentData);
    
    // If payment is already handled (all paid or all marked as cash), go directly to tracking
    // Don't show the payment choice screen
    if (onTrackOrder) {
      setTimeout(() => {
        onTrackOrder();
      }, 100); // Small delay to ensure state updates
    }
  };

  const handleReviewSubmit = (reviewData: any) => {
    console.log('Review submitted:', reviewData);
    setShowReviewModal(false);
    // Here you would normally send the review to your API
  };

  const handlePayNow = () => {
    // Simulate payment processing
    const mockOrderId = Math.floor(Math.random() * 10000);
    setOrderId(String(mockOrderId));
    
    const paymentDataToSubmit = {
      paymentMethod,
      total, // Pass the total that was calculated and passed as prop
      tip: calculateTip(),
      receiptRequested: needReceipt,
      split: splitBill ? splitData : null,
      loyaltyPointsRedeemed: pointsToRedeem,
      loyaltyDiscount: actualDiscountAmount,
      customerId: customerId || null
    };
    
    console.log('=== PaymentFlow: Submitting Payment ===');
    console.log('Total:', total);
    console.log('Points to redeem:', pointsToRedeem);
    console.log('Discount amount:', actualDiscountAmount);
    console.log('Full payment data:', paymentDataToSubmit);
    
    setPaymentData(paymentDataToSubmit);
    
    // Call onPaymentComplete immediately to create the order
    onPaymentComplete(paymentDataToSubmit);
    
    setStep('success');
  };

  const handleCardFormSubmit = () => {
    // Validate card form
    const errors: any = {};
    
    // Card number validation (must be 16 digits)
    const cardNumberClean = cardNumber.replace(/\s/g, '');
    if (!cardNumberClean) {
      errors.cardNumber = 'Card number is required';
    } else if (!/^\d{16}$/.test(cardNumberClean)) {
      errors.cardNumber = 'Card number must be 16 digits';
    }
    
    // Cardholder name validation
    if (!cardName.trim()) {
      errors.cardName = 'Cardholder name is required';
    } else if (cardName.trim().length < 3) {
      errors.cardName = 'Name must be at least 3 characters';
    }
    
    // Expiry validation (MM/YY format)
    if (!cardExpiry) {
      errors.cardExpiry = 'Expiry date is required';
    } else if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
      errors.cardExpiry = 'Must be in MM/YY format';
    } else {
      const [month, year] = cardExpiry.split('/').map(Number);
      const currentYear = new Date().getFullYear() % 100;
      const currentMonth = new Date().getMonth() + 1;
      
      if (month < 1 || month > 12) {
        errors.cardExpiry = 'Invalid month';
      } else if (year < currentYear || (year === currentYear && month < currentMonth)) {
        errors.cardExpiry = 'Card has expired';
      }
    }
    
    // CVV validation (3 or 4 digits)
    if (!cardCvv) {
      errors.cardCvv = 'CVV is required';
    } else if (!/^\d{3,4}$/.test(cardCvv)) {
      errors.cardCvv = 'CVV must be 3 or 4 digits';
    }
    
    setCardErrors(errors);

    if (Object.keys(errors).length === 0) {
      // Card validation passed - proceed with payment
      handlePayNow();
    }
  };

  const formatCardNumber = (value: string) => {
    const cleaned = value.replace(/\D/g, '');
    const limited = cleaned.slice(0, 16);
    const formatted = limited.match(/.{1,4}/g)?.join(' ') || limited;
    return formatted;
  };

  const formatExpiry = (value: string) => {
    const cleaned = value.replace(/\D/g, '');
    if (cleaned.length >= 2) {
      return cleaned.slice(0, 2) + '/' + cleaned.slice(2, 4);
    }
    return cleaned;
  };

  let content = null;

  if (step === 'choice' && !skipChoice) {
    content = (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-6 space-y-6">
          <h2 className="text-2xl">{t('submit_your_order', 'Submit your order')}</h2>
          <p className="text-gray-600">{t('how_would_you_like_to_pay', 'How would you like to pay?')}</p>

          <div className="space-y-3">
            <Button
              onClick={() => handlePaymentChoice('now')}
              className="w-full justify-start"
              size="lg"
              variant="outline"
            >
              {t('pay_now', 'Pay now')}
            </Button>

            <Button
              onClick={() => handlePaymentChoice('split')}
              className="w-full justify-start"
              size="lg"
              variant="outline"
            >
              {t('split_bill', 'Split bill')}
            </Button>

            <Button
              onClick={() => handlePaymentChoice('cash')}
              className="w-full justify-start"
              size="lg"
              variant="outline"
              disabled={isTakeaway}
            >
              {t('pay_later_cash', 'Pay later (Cash)')}
            </Button>
            
            {isTakeaway && (
              <div className="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                <span className="text-lg">ℹ️</span>
                <div>
                  <div className="font-medium">Cash payment unavailable for takeaway</div>
                  <div className="text-xs text-amber-600 mt-1">
                    To prevent no-shows, takeaway orders require prepayment via card, Apple Pay, or Google Pay.
                  </div>
                </div>
              </div>
            )}
          </div>

          <Button onClick={onBack} variant="ghost" className="w-full">
            Back
          </Button>
        </div>
      </div>
    );
  }

  if (step === 'payment' || skipChoice) {
    content = (
      <div className="min-h-screen bg-gray-50 p-4">
        <div className="max-w-2xl mx-auto space-y-6">
          <div className="bg-white rounded-lg p-6 space-y-6">
            <h2 className="text-2xl">{t('payment', 'Payment')}</h2>

            {/* Payment method */}
            <div className="space-y-3">
              <Label>{t('choose_payment_method', 'Choose payment method')}</Label>
              <div className="grid grid-cols-2 gap-3">
                {isPaymentMethodEnabled('apple') && (
                  <button
                    onClick={() => setPaymentMethod('apple')}
                    className={`p-4 border-2 rounded-lg flex flex-col items-center gap-2 ${
                      paymentMethod === 'apple' ? 'border-orange-500 bg-orange-50' : 'border-gray-200'
                    }`}
                  >
                    <Smartphone className="w-6 h-6" />
                    <span>{t('apple_pay', 'Apple Pay')}</span>
                  </button>
                )}

                {isPaymentMethodEnabled('google') && (
                  <button
                    onClick={() => setPaymentMethod('google')}
                    className={`p-4 border-2 rounded-lg flex flex-col items-center gap-2 ${
                      paymentMethod === 'google' ? 'border-orange-500 bg-orange-50' : 'border-gray-200'
                    }`}
                  >
                    <Smartphone className="w-6 h-6" />
                    <span>{t('google_pay', 'Google Pay')}</span>
                  </button>
                )}

                {isPaymentMethodEnabled('card') && (
                  <button
                    onClick={() => setPaymentMethod('card')}
                    className={`p-4 border-2 rounded-lg flex flex-col items-center gap-2 ${
                      paymentMethod === 'card' ? 'border-orange-500 bg-orange-50' : 'border-gray-200'
                    }`}
                  >
                    <CreditCard className="w-6 h-6" />
                    <span>{t('card', 'Card')}</span>
                  </button>
                )}

                {isPaymentMethodEnabled('cash') && (
                  <button
                    onClick={() => setPaymentMethod('cash')}
                    className={`p-4 border-2 rounded-lg flex flex-col items-center gap-2 ${
                      paymentMethod === 'cash' ? 'border-orange-500 bg-orange-50' : 'border-gray-200'
                    }`}
                  >
                    <Banknote className="w-6 h-4" />
                    <span>{t('cash', 'Cash')}</span>
                  </button>
                )}
              </div>
              
              {isTakeaway && !isPaymentMethodEnabled('cash') && (
                <div className="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2 mt-2">
                  <span className="text-lg">ℹ️</span>
                  <div>
                    <div className="font-medium">Cash payment unavailable for takeaway</div>
                    <div className="text-xs text-amber-600 mt-1">
                      Prepayment required to prevent no-shows.
                    </div>
                  </div>
                </div>
              )}
            </div>

            {/* Tip */}
            <div className="space-y-3">
              <Label>{t('add_a_tip', 'Add a tip (optional)')}</Label>
              <div className="grid grid-cols-5 gap-2">
                {[0, 5, 10, 15].map((percent) => (
                  <button
                    key={percent}
                    onClick={() => {
                      setTipPercent(percent);
                      setCustomTip('');
                    }}
                    className={`p-3 border-2 rounded-lg ${
                      tipPercent === percent && !customTip
                        ? 'border-orange-500 bg-orange-50'
                        : 'border-gray-200'
                    }`}
                  >
                    {percent}%
                  </button>
                ))}
                <Input
                  type="number"
                  placeholder="€"
                  value={customTip}
                  onChange={(e) => {
                    setCustomTip(e.target.value);
                    setTipPercent(0);
                  }}
                  className="text-center"
                />
              </div>
              {calculateTip() > 0 && (
                <div className="text-sm text-gray-600">
                  Tip: €{calculateTip().toFixed(2)}
                </div>
              )}
            </div>

            {/* Loyalty Points Redemption */}
            {loyaltyEnabled && customerId && (
              <div className="space-y-3 bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-gray-200 rounded-lg p-4">
                {/* Restaurant Context */}
                <div className="mb-3">
                  <div className="text-xs text-gray-500 mb-1">Loyalty at</div>
                  <div className="text-sm font-medium text-gray-900">{restaurantName}</div>
                </div>

                <div className="flex items-center gap-2 mb-2">
                  <Award className="w-5 h-5 text-[#101828]" />
                  <Label className="text-gray-900">Your points at this restaurant</Label>
                </div>
                
                <div className="flex items-center justify-between text-sm bg-white rounded-lg p-3">
                  <span className="text-gray-600">Points Balance</span>
                  <span className="text-[#101828] font-medium text-lg">{customerLoyaltyPoints || 0} {t('points', 'points')}</span>
                </div>

                {maxRedeemablePoints > 0 ? (
                  <>
                    <div className="text-sm text-gray-600 bg-white rounded-lg p-3">
                      <div className="flex items-center gap-1 mb-1">
                        <Gift className="w-4 h-4 text-[#101828]" />
                        <span>Redeemable here: <strong>{maxRedeemablePoints} {t('points', 'points')}</strong></span>
                      </div>
                      <div className="text-xs text-gray-500">
                        = up to €{maxDiscountAmount.toFixed(2)} discount
                      </div>
                    </div>

                    <div className="space-y-2">
                      <Label className="text-sm">{t('points_to_use', 'Points to use')}</Label>
                      <div className="flex gap-2">
                        {[minimumRedemption, minimumRedemption * 2, minimumRedemption * 3].map((points) => {
                          if (points <= maxRedeemablePoints && points * pointValueInEuros <= total) {
                            return (
                              <button
                                key={points}
                                onClick={() => setPointsToRedeem(points)}
                                className={`flex-1 p-3 border-2 rounded-lg text-center ${
                                  pointsToRedeem === points
                                    ? 'border-[#101828] bg-gray-100'
                                    : 'border-gray-200 bg-white'
                                }`}
                              >
                                <div className="text-sm">{points}</div>
                                <div className="text-xs text-gray-600">€{(points * pointValueInEuros).toFixed(2)}</div>
                              </button>
                            );
                          }
                          return null;
                        })}
                        <button
                          onClick={() => setPointsToRedeem(0)}
                          className={`flex-1 p-3 border-2 rounded-lg text-center ${
                            pointsToRedeem === 0
                              ? 'border-[#101828] bg-gray-100'
                              : 'border-gray-200 bg-white'
                          }`}
                        >
                          <div className="text-sm">{t('none', 'None')}</div>
                        </button>
                      </div>
                    </div>

                    {pointsToRedeem > 0 && (
                      <div className="bg-green-50 border border-green-200 rounded-lg p-3 text-sm">
                        <div className="flex items-center gap-2 text-green-800">
                          <Check className="w-4 h-4" />
                          <span>{t('discount_applied', 'Discount applied')}: <strong>-€{actualDiscountAmount.toFixed(2)}</strong></span>
                        </div>
                        <div className="text-xs text-green-700 mt-1">
                          {t('points_will_be_deducted', `${pointsToRedeem} points will be deducted from your balance`)}
                        </div>
                      </div>
                    )}
                    
                    {/* Show points they'll earn from this order */}
                    {potentialPointsEarning > 0 && (
                      <div className="text-sm text-gray-600 bg-white rounded p-2 border-t border-orange-100 mt-2">
                        <div className="text-xs text-gray-500">
                          {t('youll_earn_points', `You'll earn ${potentialPointsEarning} points from this order!`).replace('{points}', String(potentialPointsEarning))}
                        </div>
                      </div>
                    )}
                  </>
                ) : (
                  <>
                    <div className="text-sm text-gray-600 bg-white rounded p-2">
                      {t('minimum_points_required', `Minimum ${minimumRedemption} points required to redeem`)}
                    </div>
                    
                    {/* Show how many more points they need */}
                    {customerLoyaltyPoints > 0 && customerLoyaltyPoints < minimumRedemption && (
                      <div className="text-xs text-gray-500 bg-white rounded p-2">
                        {t('points_needed_to_redeem', `You need ${minimumRedemption - customerLoyaltyPoints} more points to redeem`).replace('{points}', String(minimumRedemption - customerLoyaltyPoints))}
                      </div>
                    )}
                    
                    {/* Show points they'll earn from this order */}
                    {potentialPointsEarning > 0 && (
                      <div className="text-sm text-gray-600 bg-white rounded p-2 border-t border-orange-100 mt-2">
                        <div className="text-xs text-gray-500">
                          {t('youll_earn_points', `You'll earn ${potentialPointsEarning} points from this order!`).replace('{points}', String(potentialPointsEarning))}
                        </div>
                      </div>
                    )}
                  </>
                )}
              </div>
            )}

            {/* Show info if loyalty enabled but user not logged in */}
            {loyaltyEnabled && !customerId && (
              <div className="space-y-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-lg p-4">
                <div className="flex items-center gap-2 mb-2">
                  <Award className="w-5 h-5 text-blue-600" />
                  <Label className="text-blue-900">{t('loyalty_rewards', 'Loyalty Rewards')}</Label>
                </div>
                
                <div className="text-sm text-blue-800 bg-white rounded p-3">
                  <div className="flex items-center gap-2 mb-2">
                    <Gift className="w-4 h-4 text-blue-600" />
                    <span className="font-medium">{t('earn_points_with_this_order', 'Earn points with this order!')}</span>
                  </div>
                  <div className="text-xs text-gray-600">
                    {t('login_to_earn_specific_points', `Log in before payment to earn ${potentialPointsEarning} loyalty points from this €${total.toFixed(2)} order`)
                      .replace('{points}', String(potentialPointsEarning))
                      .replace('{amount}', total.toFixed(2))}
                  </div>
                  <div className="text-xs text-gray-500 mt-1">
                    ({pointsPerEuro} {t('point_per_euro', 'point per euro')} • {minimumRedemption} points = €{(minimumRedemption * pointValueInEuros).toFixed(2)} discount)
                  </div>
                </div>
              </div>
            )}

            {/* Receipt */}
            <div className="flex items-center space-x-2">
              <Checkbox
                id="receipt"
                checked={needReceipt}
                onCheckedChange={(checked) => setNeedReceipt(checked as boolean)}
              />
              <Label htmlFor="receipt" className="cursor-pointer">
                {t('need_receipt', 'Need receipt?')}
              </Label>
            </div>

            {/* Total */}
            <div className="border-t pt-4">
              {splitBill && splitData && (
                <div className="mb-4 p-4 bg-blue-50 rounded-xl">
                  <div className="text-sm text-gray-600 mb-2">{t('split_bill', 'Split Payment')}</div>
                  {splitData.type === 'equal' ? (
                    <div>
                      <div className="text-lg">
                        {t('each_person_pays', 'Each person pays')}: <span className="text-blue-700">€{splitData.amountPerPerson.toFixed(2)}</span>
                      </div>
                      <div className="text-sm text-gray-600 mt-1">
                        {splitData.count} × €{splitData.amountPerPerson.toFixed(2)}
                      </div>
                    </div>
                  ) : (
                    <div className="space-y-1">
                      {splitData.amounts.map((amount: number, index: number) => (
                        <div key={index} className="flex justify-between text-sm">
                          <span className="text-gray-600">{t('person', 'Person')} {index + 1}</span>
                          <span>€{amount.toFixed(2)}</span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
              
              {/* Show breakdown if loyalty points used */}
              {pointsToRedeem > 0 && (
                <div className="space-y-2 mb-4">
                  <div className="flex justify-between text-sm text-gray-600">
                    <span>{t('order_total', 'Order Total')}</span>
                    <span>€{total.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between text-sm text-green-600">
                    <span>{t('loyalty_discount', 'Loyalty Discount')}</span>
                    <span>-€{actualDiscountAmount.toFixed(2)}</span>
                  </div>
                  {calculateTip() > 0 && (
                    <div className="flex justify-between text-sm text-gray-600">
                      <span>{t('tip', 'Tip')}</span>
                      <span>€{calculateTip().toFixed(2)}</span>
                    </div>
                  )}
                </div>
              )}
              
              <div className="flex justify-between text-xl">
                <span>{t('total', 'Total')}</span>
                <span>€{finalTotal.toFixed(2)}</span>
              </div>
            </div>

            <Button
              onClick={() => {
                if (paymentMethod === 'card') {
                  setStep('card-form');
                } else {
                  handlePayNow();
                }
              }}
              disabled={!paymentMethod}
              className="w-full"
              size="lg"
            >
              {paymentMethod === 'card' ? t('continue', 'Continue') : t('pay_amount', `Pay €${finalTotal.toFixed(2)}`).replace('{amount}', finalTotal.toFixed(2))}
            </Button>

            {paymentMethod === 'cash' && (
              <div className="text-sm text-gray-600 bg-gray-50 p-3 rounded">
                If you choose cash, we'll mark the order as 'pay later (cash)'. You can add items until final payment.
              </div>
            )}
          </div>

          <Button onClick={() => setStep('choice')} variant="ghost" className="w-full">
            Back
          </Button>
        </div>
      </div>
    );
  }

  if (step === 'card-form') {
    content = (
      <div className="min-h-screen bg-gray-50 p-4">
        <div className="max-w-2xl mx-auto space-y-6">
          <div className="bg-white rounded-lg p-6 space-y-6">
            <h2 className="text-2xl">{t('card_payment', 'Card Payment')}</h2>

            {/* Card number */}
            <div className="space-y-3">
              <Label>{t('card_number', 'Card number')}</Label>
              <Input
                type="text"
                value={cardNumber}
                onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
                className="w-full"
                placeholder="1234 5678 9012 3456"
              />
              {cardErrors.cardNumber && (
                <div className="text-sm text-red-500">{cardErrors.cardNumber}</div>
              )}
            </div>

            {/* Card name */}
            <div className="space-y-3">
              <Label>{t('cardholder_name', 'Cardholder name')}</Label>
              <Input
                type="text"
                value={cardName}
                onChange={(e) => setCardName(e.target.value)}
                className="w-full"
                placeholder="John Doe"
              />
              {cardErrors.cardName && (
                <div className="text-sm text-red-500">{cardErrors.cardName}</div>
              )}
            </div>

            {/* Card expiry */}
            <div className="space-y-3">
              <Label>{t('expiry_date', 'Expiry date')}</Label>
              <Input
                type="text"
                value={cardExpiry}
                onChange={(e) => setCardExpiry(formatExpiry(e.target.value))}
                className="w-full"
                placeholder="MM/YY"
              />
              {cardErrors.cardExpiry && (
                <div className="text-sm text-red-500">{cardErrors.cardExpiry}</div>
              )}
            </div>

            {/* Card CVV */}
            <div className="space-y-3">
              <Label>{t('cvv', 'CVV')}</Label>
              <Input
                type="text"
                value={cardCvv}
                onChange={(e) => setCardCvv(e.target.value)}
                className="w-full"
                placeholder="123"
              />
              {cardErrors.cardCvv && (
                <div className="text-sm text-red-500">{cardErrors.cardCvv}</div>
              )}
            </div>

            {/* Total */}
            <div className="border-t pt-4">
              {splitBill && splitData && (
                <div className="mb-4 p-4 bg-blue-50 rounded-xl">
                  <div className="text-sm text-gray-600 mb-2">{t('split_bill', 'Split Payment')}</div>
                  {splitData.type === 'equal' ? (
                    <div>
                      <div className="text-lg">
                        {t('each_person_pays', 'Each person pays')}: <span className="text-blue-700">€{splitData.amountPerPerson.toFixed(2)}</span>
                      </div>
                      <div className="text-sm text-gray-600 mt-1">
                        {splitData.count} × €{splitData.amountPerPerson.toFixed(2)}
                      </div>
                    </div>
                  ) : (
                    <div className="space-y-1">
                      {splitData.amounts.map((amount: number, index: number) => (
                        <div key={index} className="flex justify-between text-sm">
                          <span className="text-gray-600">{t('person', 'Person')} {index + 1}</span>
                          <span>€{amount.toFixed(2)}</span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
              
              <div className="flex justify-between text-xl">
                <span>{t('total', 'Total')}</span>
                <span>€{finalTotal.toFixed(2)}</span>
              </div>
            </div>

            <Button
              onClick={handleCardFormSubmit}
              className="w-full"
              size="lg"
            >
              {t('pay_amount', 'Pay €{amount}').replace('{amount}', finalTotal.toFixed(2))}
            </Button>
          </div>

          <Button onClick={() => setStep('payment')} variant="ghost" className="w-full">
            {t('back', 'Back')}
          </Button>
        </div>
      </div>
    );
  }

  if (step === 'success') {
    content = (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-6 space-y-6 text-center">
          <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
            <Check className="w-8 h-8 text-green-600" />
          </div>

          <div>
            <h2 className="text-2xl">{t('order_confirmed', 'Order confirmed')} ✅</h2>
            <p className="text-gray-600 mt-2">
              {t('order_number', 'Order #')}{orderId} • {t('preparing', 'Preparing')}
            </p>
          </div>

          <div className="bg-blue-50 p-4 rounded-lg">
            <div className="text-sm text-gray-600">{t('estimated_ready_in', 'Estimated ready in')}</div>
            <div className="text-2xl">20 {t('minutes', 'minutes')}</div>
          </div>

          <div className="space-y-2">
            <Button 
              className="w-full" 
              onClick={() => {
                if (onTrackOrder) {
                  onTrackOrder();
                }
              }}
            >
              {t('track_order', 'Track order')}
            </Button>
            <Button 
              variant="outline" 
              className="w-full" 
              onClick={() => {
                if (onViewReceipt) {
                  onViewReceipt();
                }
              }}
            >
              {t('view_receipt', 'View receipt')}
            </Button>
            <Button 
              variant="outline" 
              className="w-full" 
              onClick={() => {
                if (onGoToMenu) {
                  onGoToMenu();
                }
              }}
            >
              {t('go_to_menu', 'Go to menu')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <>
      {content}
      
      {/* Split Bill Modal */}
      {showSplitModal && (
        <SplitBillModal
          total={total}
          numPeople={numPeople}
          orderItems={orderItems}
          orderId={orderId || `temp-${Date.now()}`}
          tableNumber={vendorSettings?.tableNumber || 'Unknown'}
          onConfirm={handleSplitConfirm}
          onClose={() => setShowSplitModal(false)}
        />
      )}

      {/* Review Modal */}
      {showReviewModal && (
        <ReviewModal
          restaurantName={restaurantName}
          orderItems={orderItems}
          onSubmit={handleReviewSubmit}
          onClose={() => setShowReviewModal(false)}
        />
      )}
    </>
  );
}