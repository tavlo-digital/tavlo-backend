import { X, Calendar, Package, Truck } from 'lucide-react';
import { Button } from '../ui/button';
import { useState, useEffect } from 'react';
import { toast } from 'sonner@2.0.3';

interface Supplier {
  id: string;
  name: string;
  leadTime: number;
  orderingMethod: 'Email' | 'Manual / External';
  email?: string;
}

interface Ingredient {
  id: string;
  name: string;
  reorderQuantity: number;
  unit: string;
}

interface PurchaseOrderModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (orderData: any) => void;
  suppliers: Supplier[];
  ingredient: Ingredient;
  preselectedSupplier?: Supplier;
}

export function PurchaseOrderModal({
  isOpen,
  onClose,
  onConfirm,
  suppliers,
  ingredient,
  preselectedSupplier
}: PurchaseOrderModalProps) {
  const [selectedSupplier, setSelectedSupplier] = useState<Supplier | null>(null);
  const [quantity, setQuantity] = useState(ingredient.reorderQuantity);
  const [notes, setNotes] = useState('');
  const [estimatedDeliveryDate, setEstimatedDeliveryDate] = useState('');

  useEffect(() => {
    if (preselectedSupplier) {
      setSelectedSupplier(preselectedSupplier);
      calculateDeliveryDate(preselectedSupplier.leadTime);
    } else if (suppliers.length === 1) {
      setSelectedSupplier(suppliers[0]);
      calculateDeliveryDate(suppliers[0].leadTime);
    }
  }, [preselectedSupplier, suppliers]);

  useEffect(() => {
    setQuantity(ingredient.reorderQuantity);
  }, [ingredient]);

  const calculateDeliveryDate = (leadTimeDays: number) => {
    const deliveryDate = new Date();
    deliveryDate.setDate(deliveryDate.getDate() + leadTimeDays);
    setEstimatedDeliveryDate(deliveryDate.toISOString().split('T')[0]);
  };

  const handleSupplierChange = (supplierId: string) => {
    const supplier = suppliers.find(s => s.id === supplierId);
    if (supplier) {
      setSelectedSupplier(supplier);
      calculateDeliveryDate(supplier.leadTime);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!selectedSupplier) {
      toast.error('Please select a supplier');
      return;
    }

    if (quantity <= 0) {
      toast.error('Quantity must be greater than 0');
      return;
    }

    const orderData = {
      supplierId: selectedSupplier.id,
      supplierName: selectedSupplier.name,
      ingredientId: ingredient.id,
      ingredientName: ingredient.name,
      quantity,
      unit: ingredient.unit,
      estimatedDeliveryDate,
      notes,
      orderingMethod: selectedSupplier.orderingMethod,
      supplierEmail: selectedSupplier.email
    };

    onConfirm(orderData);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between z-10">
          <div>
            <h2 className="text-xl font-semibold">Create Purchase Order</h2>
            <p className="text-sm text-gray-600 mt-1">Order ingredients from your supplier</p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          {/* Supplier Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Supplier <span className="text-red-500">*</span>
            </label>
            {suppliers.length === 0 ? (
              <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-700">
                No active supplier linked to this ingredient. Please add a supplier in Settings → Inventory → Suppliers.
              </div>
            ) : suppliers.length === 1 || preselectedSupplier ? (
              <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div className="flex items-center gap-3">
                  <Package className="w-5 h-5 text-gray-600" />
                  <div>
                    <p className="font-medium text-gray-900">{selectedSupplier?.name}</p>
                    <p className="text-sm text-gray-600">
                      Lead time: {selectedSupplier?.leadTime} day{selectedSupplier?.leadTime !== 1 ? 's' : ''}
                    </p>
                  </div>
                </div>
              </div>
            ) : (
              <select
                value={selectedSupplier?.id || ''}
                onChange={(e) => handleSupplierChange(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                required
              >
                <option value="">Select a supplier...</option>
                {suppliers.map((supplier) => (
                  <option key={supplier.id} value={supplier.id}>
                    {supplier.name} (Lead time: {supplier.leadTime} day{supplier.leadTime !== 1 ? 's' : ''})
                  </option>
                ))}
              </select>
            )}
          </div>

          {/* Ingredient Details */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 className="font-medium text-blue-900 mb-3">Ingredient</h3>
            <div className="space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-blue-700">Name:</span>
                <span className="font-medium text-blue-900">{ingredient.name}</span>
              </div>
              <div className="flex justify-between text-sm">
                <span className="text-blue-700">Suggested reorder quantity:</span>
                <span className="font-medium text-blue-900">{ingredient.reorderQuantity} {ingredient.unit}</span>
              </div>
            </div>
          </div>

          {/* Quantity */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Quantity <span className="text-red-500">*</span>
            </label>
            <div className="flex gap-2">
              <input
                type="number"
                min="0"
                step="0.01"
                value={quantity}
                onChange={(e) => setQuantity(parseFloat(e.target.value) || 0)}
                className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                required
              />
              <div className="px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700 font-medium min-w-[80px] flex items-center justify-center">
                {ingredient.unit}
              </div>
            </div>
          </div>

          {/* Estimated Delivery Date */}
          {selectedSupplier && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Estimated Delivery Date
              </label>
              <div className="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg">
                <Calendar className="w-5 h-5 text-gray-600" />
                <input
                  type="date"
                  value={estimatedDeliveryDate}
                  onChange={(e) => setEstimatedDeliveryDate(e.target.value)}
                  className="flex-1 bg-transparent focus:outline-none"
                />
              </div>
              <p className="text-xs text-gray-500 mt-1">
                Based on {selectedSupplier.leadTime} day lead time
              </p>
            </div>
          )}

          {/* Notes */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Notes <span className="text-gray-400 text-xs">(optional)</span>
            </label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 resize-none"
              placeholder="Add any special instructions or notes..."
            />
          </div>

          {/* Ordering Method Info */}
          {selectedSupplier && (
            <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
              <div className="flex items-start gap-3">
                <Truck className="w-5 h-5 text-amber-600 mt-0.5" />
                <div>
                  <p className="text-sm font-medium text-amber-900">
                    {selectedSupplier.orderingMethod === 'Email' 
                      ? 'Email Ordering' 
                      : 'Manual / External Ordering'}
                  </p>
                  <p className="text-sm text-amber-700 mt-1">
                    {selectedSupplier.orderingMethod === 'Email'
                      ? `Purchase order details will be sent to ${selectedSupplier.email || 'the supplier email'}.`
                      : 'This purchase order will be marked as ordered externally. You\'ll need to contact the supplier manually.'}
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Footer Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button 
              type="submit" 
              className="bg-blue-600 hover:bg-blue-700"
              disabled={suppliers.length === 0}
            >
              Create Purchase Order
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
