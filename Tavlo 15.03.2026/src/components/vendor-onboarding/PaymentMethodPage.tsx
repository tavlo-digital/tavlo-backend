import { ArrowLeft, CreditCard, Lock } from 'lucide-react';
import { Button } from '../ui/button';
import { useState } from 'react';

interface PaymentMethodPageProps {
  planName: string;
  planPrice: number;
  currency: string;
  interval: 'month' | 'year';
  onPayment: (paymentMethod: PaymentMethodData) => void;
  onBackToPlans: () => void;
}

interface PaymentMethodData {
  type: 'card' | 'eps' | 'wallet';
  cardNumber?: string;
  expiry?: string;
  cvc?: string;
  billingCountry?: string;
}

export function PaymentMethodPage({
  planName,
  planPrice,
  currency,
  interval,
  onPayment,
  onBackToPlans
}: PaymentMethodPageProps) {
  const [paymentMethod, setPaymentMethod] = useState<'card' | 'eps' | 'wallet'>('card');
  const [formData, setFormData] = useState({
    cardNumber: '',
    expiry: '',
    cvc: '',
    billingCountry: 'Austria'
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [processing, setProcessing] = useState(false);

  const validateForm = () => {
    const newErrors: Record<string, string> = {};

    if (paymentMethod === 'card') {
      if (!formData.cardNumber || formData.cardNumber.replace(/\s/g, '').length !== 16) {
        newErrors.cardNumber = 'Valid card number required';
      }
      if (!formData.expiry || !/^\d{2}\/\d{2}$/.test(formData.expiry)) {
        newErrors.expiry = 'Format: MM/YY';
      }
      if (!formData.cvc || formData.cvc.length !== 3) {
        newErrors.cvc = '3 digits required';
      }
    }

    if (!formData.billingCountry) {
      newErrors.billingCountry = 'Country required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm()) return;

    setProcessing(true);

    // Simulate payment processing
    await new Promise(resolve => setTimeout(resolve, 2000));

    onPayment({
      type: paymentMethod,
      ...formData
    });
  };

  const formatCardNumber = (value: string) => {
    const digits = value.replace(/\D/g, '');
    const groups = digits.match(/.{1,4}/g) || [];
    return groups.join(' ').substring(0, 19);
  };

  const formatExpiry = (value: string) => {
    const digits = value.replace(/\D/g, '');
    if (digits.length >= 2) {
      return digits.substring(0, 2) + '/' + digits.substring(2, 4);
    }
    return digits;
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-2xl mx-auto px-4 py-8">
        {/* Back Button */}
        <div className="mb-8">
          <button
            onClick={onBackToPlans}
            className="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 transition-colors"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Back to plans</span>
          </button>
        </div>

        {/* Header */}
        <div className="text-center mb-8">
          <h1 className="text-3xl mb-2 text-gray-900">Complete your subscription</h1>
          <p className="text-gray-600">
            Choose how you'd like to pay. Your subscription will activate immediately after successful payment.
          </p>
        </div>

        {/* Order Summary */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4">Order Summary</h3>
          <div className="flex items-center justify-between mb-4">
            <div>
              <p className="text-gray-900">{planName} Plan</p>
              <p className="text-sm text-gray-500">Billed {interval}ly</p>
            </div>
            <p className="text-2xl text-gray-900">
              €{planPrice}
              <span className="text-sm text-gray-600"> / {interval}</span>
            </p>
          </div>
          <div className="pt-4 border-t border-gray-200">
            <div className="flex items-center justify-between">
              <p className="text-gray-600">Total due today</p>
              <p className="text-2xl text-gray-900">€{planPrice}</p>
            </div>
            <p className="text-xs text-gray-500 mt-1">Excluding VAT</p>
          </div>
        </div>

        {/* Payment Method Selection */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4">Payment Method</h3>
          
          <div className="space-y-3 mb-6">
            {/* Card */}
            <label className={`flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all ${
              paymentMethod === 'card' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300'
            }`}>
              <input
                type="radio"
                name="paymentMethod"
                value="card"
                checked={paymentMethod === 'card'}
                onChange={() => setPaymentMethod('card')}
                className="w-4 h-4 text-emerald-600"
              />
              <CreditCard className="w-5 h-5 text-gray-600" />
              <span className="text-gray-900">Credit / Debit Card</span>
            </label>

            {/* EPS */}
            <label className={`flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all ${
              paymentMethod === 'eps' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300'
            }`}>
              <input
                type="radio"
                name="paymentMethod"
                value="eps"
                checked={paymentMethod === 'eps'}
                onChange={() => setPaymentMethod('eps')}
                className="w-4 h-4 text-emerald-600"
              />
              <div className="w-5 h-5 bg-gray-200 rounded flex items-center justify-center">
                <span className="text-xs">E</span>
              </div>
              <span className="text-gray-900">EPS</span>
            </label>

            {/* Wallet */}
            <label className={`flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all ${
              paymentMethod === 'wallet' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300'
            }`}>
              <input
                type="radio"
                name="paymentMethod"
                value="wallet"
                checked={paymentMethod === 'wallet'}
                onChange={() => setPaymentMethod('wallet')}
                className="w-4 h-4 text-emerald-600"
              />
              <div className="w-5 h-5 bg-gray-200 rounded flex items-center justify-center">
                <span className="text-xs">W</span>
              </div>
              <span className="text-gray-900">Apple Pay / Google Pay</span>
            </label>
          </div>

          {/* Card Form */}
          {paymentMethod === 'card' && (
            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Card Number */}
              <div>
                <label className="block text-sm text-gray-700 mb-2">Card number</label>
                <input
                  type="text"
                  value={formData.cardNumber}
                  onChange={(e) => setFormData({ ...formData, cardNumber: formatCardNumber(e.target.value) })}
                  placeholder="1234 5678 9012 3456"
                  className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                    errors.cardNumber ? 'border-red-500' : 'border-gray-300'
                  }`}
                />
                {errors.cardNumber && (
                  <p className="mt-1 text-sm text-red-600">{errors.cardNumber}</p>
                )}
              </div>

              {/* Expiry & CVC */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm text-gray-700 mb-2">Expiry</label>
                  <input
                    type="text"
                    value={formData.expiry}
                    onChange={(e) => setFormData({ ...formData, expiry: formatExpiry(e.target.value) })}
                    placeholder="MM/YY"
                    className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                      errors.expiry ? 'border-red-500' : 'border-gray-300'
                    }`}
                  />
                  {errors.expiry && (
                    <p className="mt-1 text-sm text-red-600">{errors.expiry}</p>
                  )}
                </div>

                <div>
                  <label className="block text-sm text-gray-700 mb-2">CVC</label>
                  <input
                    type="text"
                    value={formData.cvc}
                    onChange={(e) => setFormData({ ...formData, cvc: e.target.value.replace(/\D/g, '').substring(0, 3) })}
                    placeholder="123"
                    className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                      errors.cvc ? 'border-red-500' : 'border-gray-300'
                    }`}
                  />
                  {errors.cvc && (
                    <p className="mt-1 text-sm text-red-600">{errors.cvc}</p>
                  )}
                </div>
              </div>

              {/* Billing Country */}
              <div>
                <label className="block text-sm text-gray-700 mb-2">Billing country</label>
                <select
                  value={formData.billingCountry}
                  onChange={(e) => setFormData({ ...formData, billingCountry: e.target.value })}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                >
                  <option value="Austria">Austria</option>
                  <option value="Germany">Germany</option>
                  <option value="Switzerland">Switzerland</option>
                  <option value="Italy">Italy</option>
                  <option value="France">France</option>
                </select>
              </div>

              {/* Submit Button */}
              <Button
                type="submit"
                disabled={processing}
                className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-4"
              >
                <Lock className="w-4 h-4 mr-2" />
                {processing ? 'Processing payment...' : `Pay €${planPrice} & activate`}
              </Button>
            </form>
          )}

          {/* EPS/Wallet Message */}
          {(paymentMethod === 'eps' || paymentMethod === 'wallet') && (
            <div className="text-center py-8">
              <p className="text-gray-600 mb-4">
                You will be redirected to complete your payment
              </p>
              <Button
                onClick={() => onPayment({ type: paymentMethod, billingCountry: formData.billingCountry })}
                disabled={processing}
                className="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-3 px-8"
              >
                <Lock className="w-4 h-4 mr-2" />
                {processing ? 'Processing...' : 'Continue to payment'}
              </Button>
            </div>
          )}
        </div>

        {/* Security Notice */}
        <div className="bg-gray-100 rounded-lg p-4 flex items-start gap-3">
          <Lock className="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
          <div>
            <p className="text-sm text-gray-900 mb-1">Secure payment</p>
            <p className="text-xs text-gray-600">
              Your payment is processed securely via Stripe. We never store your card details.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
