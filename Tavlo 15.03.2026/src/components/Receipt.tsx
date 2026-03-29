import { Button } from './ui/button';
import { ArrowLeft, Download, Printer } from 'lucide-react';

interface ReceiptProps {
  order: any;
  onBack: () => void;
  vendorSettings?: any;
}

/**
 * Country-Aware Restaurant Receipt Component
 * 
 * CRITICAL: This receipt must pass a tax audit in Austria or Germany.
 * If any VAT base is not explicitly shown, the design is wrong.
 * 
 * Supports:
 * - Austria (AT): UStG §11 - VAT on net, service fee separate
 * - Germany (DE): UStG §14 - VAT on net, service fee non-taxable
 */
export function Receipt({ order, onBack, vendorSettings }: ReceiptProps) {
  // Get vendor country (default to Austria)
  const country = vendorSettings?.country || 'AT';
  
  // Extract order data
  const subtotal = order.subtotal || 0; // Net amount before fees
  const serviceFee = order.serviceFee || 0; // Service fee
  const vatBreakdowns = order.vatBreakdowns || []; // Detailed VAT breakdown by rate
  const tip = order.tip || 0;
  const total = order.total || 0;
  
  // Calculate items total (gross amount of items only)
  const itemsGrossTotal = order.items.reduce((sum: number, item: any) => {
    const itemTotal = item.price * item.quantity;
    const modifiersTotal = item.modifiers?.reduce((mSum: number, m: any) => 
      mSum + (m.price * item.quantity), 0) || 0;
    return sum + itemTotal + modifiersTotal;
  }, 0);
  
  // Format number with German decimal comma
  const formatAmount = (amount: number): string => {
    return amount.toFixed(2).replace('.', ',');
  };
  
  // Format date in German format
  const formatDate = (date: Date) => {
    return date.toLocaleDateString('de', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  };
  
  // Format time
  const formatTime = (date: Date) => {
    return date.toLocaleTimeString('de', {
      hour: '2-digit',
      minute: '2-digit'
    });
  };
  
  // Get payment method in German
  const getPaymentMethodLabel = (method: string) => {
    const methods: Record<string, string> = {
      'card': 'Karte',
      'cash': 'Bar',
      'paypal': 'PayPal',
      'apple-pay': 'Apple Pay',
      'google-pay': 'Google Pay'
    };
    return methods[method] || method;
  };
  
  // Country-specific configuration
  const countryConfig = {
    AT: {
      vatIdLabel: 'UID',
      receiptLabel: 'Rechnung Nr.',
      footerLegal: 'MwSt gemäß §11 UStG (Österreich)',
      footerNote: 'Servicegebühr ist keine MwSt',
      serviceFeeLabel: (rate: number) => `Servicegebühr (${rate}% auf Netto)`,
      vatLabel: (rate: number) => `MwSt ${rate}%`,
      netLabel: (rate: number) => `Netto ${rate}% MwSt`
    },
    DE: {
      vatIdLabel: 'USt-IdNr.',
      receiptLabel: 'Rechnung Nr.',
      footerLegal: 'MwSt gemäß §14 UStG (Deutschland)',
      footerNote: null,
      serviceFeeLabel: () => 'Servicegebühr (nicht steuerbar)',
      vatLabel: (rate: number) => `MwSt ${rate}%`,
      netLabel: (rate: number) => `Netto ${rate}% MwSt`
    }
  };
  
  const config = countryConfig[country as keyof typeof countryConfig] || countryConfig.AT;
  
  const handlePrint = () => {
    window.print();
  };

  const handleDownload = () => {
    // In a real app, this would generate a PDF
    alert('Receipt download functionality would be implemented here');
  };
  
  const orderDate = new Date(order.createdAt || Date.now());

  return (
    <div className="min-h-screen bg-gray-100 p-4">
      <div className="max-w-md mx-auto">
        {/* Back button - hide when printing */}
        <Button 
          onClick={onBack} 
          variant="ghost" 
          className="mb-4 print:hidden"
        >
          <ArrowLeft className="w-4 h-4 mr-2" />
          Zurück
        </Button>

        {/* Receipt - Thermal Printer Style */}
        <div className="bg-white mx-auto print:shadow-none" style={{ maxWidth: '360px', fontFamily: 'monospace' }}>
          <div className="p-6">
            {/* Header - Restaurant Info (Centered) */}
            <div className="text-center mb-4 text-sm leading-tight">
              <div className="font-bold mb-1">{order.restaurantName || 'Restaurant Name'}</div>
              <div>Hauptstraße 123</div>
              <div>1010 Wien</div>
              <div>{config.vatIdLabel}: ATU12345678</div>
              <div>Tel: +43 1 234 5678</div>
            </div>
            
            {/* Separator */}
            <div className="border-t border-black my-3"></div>
            
            {/* Order Info (Left-aligned) */}
            <div className="text-sm space-y-0.5 mb-3">
              <div>{config.receiptLabel} {order.id?.slice(0, 8) || order.orderId?.slice(0, 8)}</div>
              <div className="flex justify-between">
                <span>Datum: {formatDate(orderDate)}</span>
                <span>Uhrzeit: {formatTime(orderDate)}</span>
              </div>
              {order.tableNumber && (
                <div>Tisch: {order.tableNumber}</div>
              )}
              <div>Zahlungsart: {getPaymentMethodLabel(order.paymentMethod || 'card')}</div>
            </div>
            
            {/* Separator */}
            <div className="border-t border-black my-3"></div>
            
            {/* Items */}
            <div className="text-sm space-y-1 mb-3">
              {order.items.map((item: any, index: number) => {
                const itemTotal = item.price * item.quantity;
                
                return (
                  <div key={index}>
                    <div className="flex justify-between">
                      <span>{item.quantity}x {item.name}</span>
                      <span className="ml-2">{formatAmount(itemTotal)} €</span>
                    </div>
                    {item.modifiers && item.modifiers.length > 0 && (
                      <div className="pl-4">
                        {item.modifiers.map((modifier: any, mIndex: number) => (
                          <div key={mIndex} className="flex justify-between text-xs">
                            <span>  + {modifier.name}</span>
                            <span className="ml-2">{formatAmount(modifier.price * item.quantity)} €</span>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
            
            {/* Separator with dashes */}
            <div className="text-center text-sm my-2" style={{ letterSpacing: '-0.05em' }}>
              --------------------------------------
            </div>
            
            {/* Items Total (SUMME) */}
            <div className="flex justify-between text-sm font-bold mb-3">
              <span>SUMME</span>
              <span>{formatAmount(itemsGrossTotal)} €</span>
            </div>
            
            {/* Separator */}
            <div className="border-t border-black my-3"></div>
            
            {/* Tax & Fee Breakdown - COUNTRY-SPECIFIC */}
            <div className="text-sm space-y-1 mb-3">
              {/* VAT Breakdown - Show Net and VAT for each rate */}
              {vatBreakdowns.length > 0 ? (
                <>
                  {vatBreakdowns.map((breakdown: any, index: number) => (
                    <div key={index} className="space-y-0.5">
                      <div className="flex justify-between">
                        <span>{config.netLabel(breakdown.rate)}</span>
                        <span>{formatAmount(breakdown.netAmount)} €</span>
                      </div>
                      <div className="flex justify-between">
                        <span>{config.vatLabel(breakdown.rate)}</span>
                        <span>{formatAmount(breakdown.vatAmount)} €</span>
                      </div>
                      {index < vatBreakdowns.length - 1 && <div className="h-1"></div>}
                    </div>
                  ))}
                </>
              ) : null}
              
              {/* Service Fee - Country-specific handling */}
              {serviceFee > 0 && (
                <>
                  <div className="h-1"></div>
                  <div className="flex justify-between">
                    <span>
                      {country === 'AT' 
                        ? config.serviceFeeLabel(vendorSettings?.serviceFeeRate || 23)
                        : config.serviceFeeLabel()
                      }
                    </span>
                    <span>{formatAmount(serviceFee)} €</span>
                  </div>
                </>
              )}
            </div>
            
            {/* Separator with dashes */}
            <div className="text-center text-sm my-2" style={{ letterSpacing: '-0.05em' }}>
              --------------------------------------
            </div>
            
            {/* Subtotal (before tip) */}
            <div className="flex justify-between text-sm mb-2">
              <span>Zwischensumme</span>
              <span>{formatAmount(total - tip)} €</span>
            </div>
            
            {/* Tip (if applicable) */}
            {tip > 0 && (
              <div className="flex justify-between text-sm mb-2">
                <span>Trinkgeld</span>
                <span>{formatAmount(tip)} €</span>
              </div>
            )}
            
            {/* Loyalty Discount (if applicable) */}
            {order.loyaltyPointsRedeemed && order.loyaltyPointsRedeemed > 0 && (
              <div className="flex justify-between text-sm mb-2">
                <span>Treuerabatt ({order.loyaltyPointsRedeemed} Punkte)</span>
                <span>-{formatAmount(order.loyaltyDiscount || 0)} €</span>
              </div>
            )}
            
            {/* Separator */}
            <div className="border-t-2 border-black my-2"></div>
            
            {/* Total Amount */}
            <div className="flex justify-between text-base font-bold mb-3">
              <span>Gesamtbetrag</span>
              <span>{formatAmount(total)} €</span>
            </div>
            
            {/* Optional summary line */}
            <div className="text-xs text-center mb-3">
              inkl. MwSt und Servicegebühr
            </div>
            
            {/* Separator */}
            <div className="border-t border-black my-3"></div>
            
            {/* Footer - Legal Text */}
            <div className="text-xs text-center space-y-1">
              <div>Preise in EUR</div>
              <div>{config.footerLegal}</div>
              {config.footerNote && <div>{config.footerNote}</div>}
              <div className="mt-2 opacity-60">Powered by Tavlo</div>
            </div>
          </div>
        </div>

        {/* Action buttons - hide when printing */}
        <div className="mt-6 grid grid-cols-2 gap-4 print:hidden">
          <Button 
            onClick={handlePrint}
            variant="outline"
            className="w-full"
          >
            <Printer className="w-4 h-4 mr-2" />
            Drucken
          </Button>
          <Button 
            onClick={handleDownload}
            variant="outline"
            className="w-full"
          >
            <Download className="w-4 h-4 mr-2" />
            PDF Download
          </Button>
        </div>
      </div>

      <style>{`
        @media print {
          body {
            background: white;
          }
          .print\\:hidden {
            display: none !important;
          }
          .print\\:shadow-none {
            box-shadow: none !important;
          }
          @page {
            margin: 0.5cm;
          }
        }
      `}</style>
    </div>
  );
}
