import { X, Download, Printer } from 'lucide-react';
import { toast } from 'sonner@2.0.3';

interface OrderReceiptModalProps {
  orderId: string;
  onClose: () => void;
}

export function OrderReceiptModal({ orderId, onClose }: OrderReceiptModalProps) {
  // Mock receipt data - in production this would come from API
  const receipt = {
    orderId: orderId,
    vendor: {
      name: 'Bella Italia',
      address: 'Mariahilfer Straße 42, 1070 Wien',
      phone: '+43 1 523 4567',
      uid: 'ATU12345678'
    },
    orderDate: '2025-01-06',
    orderTime: '13:15',
    tableNumber: '12',
    items: [
      { name: 'Margherita Pizza', quantity: 1, price: 12.90 },
      { name: 'Caesar Salad', quantity: 1, price: 8.50 },
      { name: 'Tiramisu', quantity: 1, price: 6.90 },
      { name: 'Coca Cola 0.5L', quantity: 2, price: 3.80 }
    ],
    subtotal: 35.90,
    vatBreakdown: [
      { rate: '20%', net: 29.92, vat: 5.98, gross: 35.90 },
      { rate: '10%', net: 0.00, vat: 0.00, gross: 0.00 }
    ],
    total: 35.90,
    paymentMethod: 'Visa •••• 4242',
    transactionId: 'TXN-847291038475'
  };

  const handleDownload = () => {
    toast.success('Receipt downloaded', {
      description: `${receipt.orderId}.pdf`
    });
  };

  const handlePrint = () => {
    window.print();
    toast.success('Receipt sent to printer');
  };

  return (
    <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h3 className="text-lg font-semibold text-gray-900">Order Receipt</h3>
          <div className="flex items-center gap-2">
            <button
              onClick={handlePrint}
              className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
              title="Print receipt"
            >
              <Printer className="w-5 h-5 text-gray-600" />
            </button>
            <button
              onClick={handleDownload}
              className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
              title="Download PDF"
            >
              <Download className="w-5 h-5 text-gray-600" />
            </button>
            <button
              onClick={onClose}
              className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <X className="w-5 h-5 text-gray-600" />
            </button>
          </div>
        </div>

        {/* Receipt Content */}
        <div className="flex-1 overflow-y-auto">
          <div className="px-8 py-6 bg-white" style={{ fontFamily: 'monospace' }}>
            {/* Vendor Header */}
            <div className="text-center mb-6 pb-4 border-b-2 border-dashed border-gray-300">
              <h2 className="text-2xl font-bold text-gray-900 mb-2">{receipt.vendor.name}</h2>
              <p className="text-sm text-gray-600">{receipt.vendor.address}</p>
              <p className="text-sm text-gray-600">Tel: {receipt.vendor.phone}</p>
              <p className="text-sm text-gray-600 mt-1">UID: {receipt.vendor.uid}</p>
            </div>

            {/* Order Info */}
            <div className="mb-6 space-y-1">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Order #:</span>
                <span className="font-semibold text-gray-900">{receipt.orderId}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Date:</span>
                <span className="text-gray-900">{receipt.orderDate} {receipt.orderTime}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Table:</span>
                <span className="text-gray-900">{receipt.tableNumber}</span>
              </div>
            </div>

            {/* Items */}
            <div className="mb-6 pb-4 border-b-2 border-dashed border-gray-300">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-300">
                    <th className="text-left py-2 text-gray-600 font-semibold">Item</th>
                    <th className="text-center py-2 text-gray-600 font-semibold">Qty</th>
                    <th className="text-right py-2 text-gray-600 font-semibold">Price</th>
                  </tr>
                </thead>
                <tbody>
                  {receipt.items.map((item, index) => (
                    <tr key={index} className="border-b border-gray-100">
                      <td className="py-2 text-gray-900">{item.name}</td>
                      <td className="text-center py-2 text-gray-900">{item.quantity}</td>
                      <td className="text-right py-2 text-gray-900">€{item.price.toFixed(2)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Subtotal */}
            <div className="mb-4 space-y-1">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Subtotal:</span>
                <span className="text-gray-900">€{receipt.subtotal.toFixed(2)}</span>
              </div>
            </div>

            {/* Austrian VAT Breakdown */}
            <div className="mb-4 pb-4 border-b-2 border-gray-300 space-y-1">
              <p className="text-xs text-gray-500 uppercase mb-2">VAT Breakdown (Austrian Tax Law)</p>
              {receipt.vatBreakdown.map((vat, index) => (
                vat.gross > 0 && (
                  <div key={index} className="space-y-0.5">
                    <div className="flex justify-between text-xs text-gray-600">
                      <span>Net ({vat.rate}):</span>
                      <span>€{vat.net.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between text-xs text-gray-600">
                      <span>VAT ({vat.rate}):</span>
                      <span>€{vat.vat.toFixed(2)}</span>
                    </div>
                  </div>
                )
              ))}
            </div>

            {/* Total */}
            <div className="mb-6">
              <div className="flex justify-between items-center">
                <span className="text-lg font-bold text-gray-900">TOTAL:</span>
                <span className="text-2xl font-bold text-gray-900">€{receipt.total.toFixed(2)}</span>
              </div>
            </div>

            {/* Payment Info */}
            <div className="mb-6 pb-4 border-b-2 border-dashed border-gray-300 space-y-1">
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Payment Method:</span>
                <span className="text-gray-900">{receipt.paymentMethod}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Transaction ID:</span>
                <span className="text-gray-900 font-mono text-xs">{receipt.transactionId}</span>
              </div>
            </div>

            {/* Footer */}
            <div className="text-center space-y-2">
              <p className="text-sm text-gray-900 font-semibold">Thank you for your order!</p>
              <p className="text-xs text-gray-500">Powered by TAVLO</p>
              <div className="mt-4 pt-4 border-t border-gray-200">
                <p className="text-xs text-gray-400">
                  This is a digital receipt. Keep for your records.
                </p>
              </div>
            </div>

            {/* QR Code Placeholder */}
            <div className="mt-6 flex justify-center">
              <div className="w-24 h-24 bg-gray-100 border border-gray-300 rounded flex items-center justify-center">
                <span className="text-xs text-gray-400">QR Code</span>
              </div>
            </div>
          </div>
        </div>

        {/* Footer Note */}
        <div className="px-6 py-3 bg-blue-50 border-t border-blue-200">
          <p className="text-xs text-blue-800">
            ℹ️ This is the exact receipt the customer received after payment. Customer reference: {receipt.orderId}
          </p>
        </div>
      </div>
    </div>
  );
}
