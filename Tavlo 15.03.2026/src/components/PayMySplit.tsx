import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { CreditCard, Banknote, CheckCircle, ArrowLeft, Loader } from 'lucide-react';
import { Button } from './ui/button';
import { useLanguage } from '../contexts/LanguageContext';
import { api } from '../utils/api';

interface PayMySplitProps {
  splitId: string;
  orderId: string;
  amount: number;
  tableNumber: string;
  personNumber: number;
}

export function PayMySplit({ splitId, orderId, amount, tableNumber, personNumber }: PayMySplitProps) {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const [paymentMethod, setPaymentMethod] = useState<'card' | 'cash' | null>(null);
  const [processing, setProcessing] = useState(false);
  const [success, setSuccess] = useState(false);
  const [splitData, setSplitData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Fetch split payment details
    const fetchSplitDetails = async () => {
      try {
        const data = await api.getSplitPayment(splitId);
        setSplitData(data);
        
        // Check if already paid
        if (data.status === 'paid') {
          setSuccess(true);
        }
      } catch (error) {
        console.error('Error fetching split details:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchSplitDetails();
  }, [splitId]);

  const handlePayment = async (method: 'card' | 'cash') => {
    setPaymentMethod(method);
    setProcessing(true);

    try {
      if (method === 'card') {
        // Navigate to payment page with split details
        navigate('/payment', {
          state: {
            amount,
            orderId,
            splitId,
            type: 'split',
            returnUrl: `/pay-split/${splitId}`,
          },
        });
      } else {
        // Mark as cash payment
        await api.markSplitAsPaid(splitId, {
          paymentMethod: 'cash',
          amount,
        });
        
        setSuccess(true);
        setProcessing(false);

        // Redirect after 2 seconds
        setTimeout(() => {
          navigate('/menu');
        }, 2000);
      }
    } catch (error) {
      console.error('Error processing payment:', error);
      setProcessing(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <Loader className="w-8 h-8 animate-spin text-orange-500 mx-auto mb-3" />
          <p className="text-gray-600">{t('loading', 'Loading...')}</p>
        </div>
      </div>
    );
  }

  if (success) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl p-8 max-w-md w-full text-center">
          <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <CheckCircle className="w-12 h-12 text-green-600" />
          </div>
          <h2 className="text-2xl font-bold mb-2">{t('payment_successful', 'Payment Successful!')}</h2>
          <p className="text-gray-600 mb-4">
            {t('split_paid', 'Your split payment has been processed successfully.')}
          </p>
          <div className="bg-gray-50 rounded-xl p-4 mb-6">
            <div className="text-sm text-gray-600 mb-1">{t('amount_paid', 'Amount Paid')}</div>
            <div className="text-3xl font-bold text-green-600">€{amount.toFixed(2)}</div>
          </div>
          <Button
            onClick={() => navigate('/menu')}
            className="w-full"
          >
            {t('back_to_menu', 'Back to Menu')}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b sticky top-0 z-10">
        <div className="max-w-2xl mx-auto px-4 py-4">
          <div className="flex items-center gap-3">
            <button
              onClick={() => navigate(-1)}
              className="p-2 hover:bg-gray-100 rounded-full transition-colors"
            >
              <ArrowLeft className="w-5 h-5" />
            </button>
            <div>
              <h1 className="text-xl font-semibold">{t('pay_your_split', 'Pay Your Split')}</h1>
              <p className="text-sm text-gray-600">
                {t('table', 'Table')} {tableNumber} • {t('person', 'Person')} {personNumber}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-2xl mx-auto px-4 py-6 space-y-6">
        {/* Amount Card */}
        <div className="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-6 text-white shadow-xl">
          <div className="text-sm opacity-90 mb-2">{t('your_share', 'Your Share')}</div>
          <div className="text-5xl font-bold mb-1">€{amount.toFixed(2)}</div>
          <div className="text-sm opacity-75">{t('for_order', 'For Order')} #{orderId.slice(0, 8)}</div>
        </div>

        {/* Payment Method Selection */}
        <div>
          <h2 className="text-lg font-semibold mb-4">{t('select_payment_method', 'Select Payment Method')}</h2>
          
          <div className="space-y-3">
            {/* Card Payment */}
            <button
              onClick={() => handlePayment('card')}
              disabled={processing}
              className="w-full bg-white border-2 border-gray-200 rounded-xl p-6 hover:border-orange-500 hover:bg-orange-50 transition-all text-left disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 bg-orange-100 rounded-full flex items-center justify-center">
                  <CreditCard className="w-7 h-7 text-orange-600" />
                </div>
                <div className="flex-1">
                  <div className="font-semibold text-lg mb-1">{t('pay_with_card', 'Pay with Card')}</div>
                  <div className="text-sm text-gray-600">
                    {t('credit_debit_card', 'Credit or Debit Card')}
                  </div>
                </div>
                <div className="text-orange-600">
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </div>
            </button>

            {/* Cash Payment */}
            <button
              onClick={() => handlePayment('cash')}
              disabled={processing}
              className="w-full bg-white border-2 border-gray-200 rounded-xl p-6 hover:border-green-500 hover:bg-green-50 transition-all text-left disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                  <Banknote className="w-7 h-7 text-green-600" />
                </div>
                <div className="flex-1">
                  <div className="font-semibold text-lg mb-1">{t('pay_with_cash', 'Pay with Cash')}</div>
                  <div className="text-sm text-gray-600">
                    {t('pay_to_waiter', 'Pay cash to the waiter')}
                  </div>
                </div>
                <div className="text-green-600">
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                  </svg>
                </div>
              </div>
            </button>
          </div>
        </div>

        {/* Info */}
        <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <h3 className="font-medium text-blue-900 mb-2">{t('payment_info', 'Payment Information')}</h3>
          <ul className="space-y-1 text-sm text-blue-800">
            <li>• {t('secure_payment', 'Your payment is secure and encrypted')}</li>
            <li>• {t('split_info', 'This is your portion of a split bill')}</li>
            <li>• {t('others_pay_separately', 'Others will pay their portions separately')}</li>
          </ul>
        </div>

        {processing && (
          <div className="bg-orange-50 border border-orange-200 rounded-xl p-4 flex items-center gap-3">
            <Loader className="w-5 h-5 animate-spin text-orange-600" />
            <div className="text-sm text-orange-900">
              {t('processing_payment', 'Processing your payment...')}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
