import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useState, useEffect } from 'react';
import { toast } from 'sonner';

interface Supplier {
  id: string;
  name: string;
  supportedIngredients: string[];
  leadTime: number;
  orderingMethod: 'Email' | 'Manual / External';
  status: 'active' | 'inactive';
  email?: string;
  phone?: string;
  orderingUrl?: string;
  orderCutoffTime?: string;
  minimumOrderQty?: number;
  ingredientConfigs?: Array<{
    ingredientId: string;
    ingredientName: string;
    supplyUnit: 'kg' | 'piece' | 'box' | 'liter';
    supplierSku?: string;
  }>;
}

interface SupplierFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (supplier: Omit<Supplier, 'id'> | Supplier) => void;
  supplier?: Supplier | null;
  availableIngredients?: Array<{ id: string; name: string; unit: string }>;
}

export function SupplierFormModal({ 
  isOpen, 
  onClose, 
  onSave, 
  supplier,
  availableIngredients = []
}: SupplierFormModalProps) {
  const [formData, setFormData] = useState<Omit<Supplier, 'id'>>({
    name: '',
    supportedIngredients: [],
    leadTime: 1,
    orderingMethod: 'Email',
    status: 'active',
    email: '',
    phone: '',
    orderingUrl: '',
    orderCutoffTime: '',
    minimumOrderQty: undefined,
    ingredientConfigs: []
  });

  const [selectedIngredients, setSelectedIngredients] = useState<string[]>([]);

  useEffect(() => {
    if (supplier) {
      setFormData({
        name: supplier.name,
        supportedIngredients: supplier.supportedIngredients,
        leadTime: supplier.leadTime,
        orderingMethod: supplier.orderingMethod,
        status: supplier.status,
        email: supplier.email || '',
        phone: supplier.phone || '',
        orderingUrl: supplier.orderingUrl || '',
        orderCutoffTime: supplier.orderCutoffTime || '',
        minimumOrderQty: supplier.minimumOrderQty,
        ingredientConfigs: supplier.ingredientConfigs || []
      });
      setSelectedIngredients(supplier.supportedIngredients);
    } else {
      // Reset form for new supplier
      setFormData({
        name: '',
        supportedIngredients: [],
        leadTime: 1,
        orderingMethod: 'Email',
        status: 'active',
        email: '',
        phone: '',
        orderingUrl: '',
        orderCutoffTime: '',
        minimumOrderQty: undefined,
        ingredientConfigs: []
      });
      setSelectedIngredients([]);
    }
  }, [supplier, isOpen]);

  const handleIngredientToggle = (ingredientName: string) => {
    const newSelection = selectedIngredients.includes(ingredientName)
      ? selectedIngredients.filter(i => i !== ingredientName)
      : [...selectedIngredients, ingredientName];
    
    setSelectedIngredients(newSelection);
    setFormData({ ...formData, supportedIngredients: newSelection });
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    // Validation
    if (!formData.name.trim()) {
      toast.error('Supplier name is required');
      return;
    }

    if (formData.orderingMethod === 'Email' && !formData.email?.trim()) {
      toast.error('Ordering email is required for Email ordering method');
      return;
    }

    if (formData.leadTime < 0) {
      toast.error('Lead time must be a positive number');
      return;
    }

    if (supplier) {
      onSave({ ...formData, id: supplier.id } as Supplier);
    } else {
      onSave(formData);
    }
    
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between z-10">
          <h2 className="text-xl font-semibold">
            {supplier ? 'Edit Supplier' : 'Add Supplier'}
          </h2>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          {/* Basic Information */}
          <div>
            <h3 className="font-semibold text-gray-900 mb-4">Basic Information</h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Supplier Name <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                  placeholder="e.g. Fresh Foods Co"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Ordering Email {formData.orderingMethod === 'Email' && <span className="text-red-500">*</span>}
                  </label>
                  <input
                    type="email"
                    value={formData.email}
                    onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    placeholder="orders@supplier.com"
                    required={formData.orderingMethod === 'Email'}
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Phone <span className="text-gray-400 text-xs">(optional)</span>
                  </label>
                  <input
                    type="tel"
                    value={formData.phone}
                    onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    placeholder="+43 1 234 5678"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Ordering URL <span className="text-gray-400 text-xs">(optional)</span>
                </label>
                <input
                  type="url"
                  value={formData.orderingUrl}
                  onChange={(e) => setFormData({ ...formData, orderingUrl: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                  placeholder="https://supplier.com/order"
                />
              </div>
            </div>
          </div>

          {/* Ordering Configuration */}
          <div>
            <h3 className="font-semibold text-gray-900 mb-4">Ordering Configuration</h3>
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Ordering Method <span className="text-red-500">*</span>
                  </label>
                  <select
                    value={formData.orderingMethod}
                    onChange={(e) => setFormData({ ...formData, orderingMethod: e.target.value as 'Email' | 'Manual / External' })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                  >
                    <option value="Email">Email</option>
                    <option value="Manual / External">Manual / External</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Default Lead Time (days) <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={formData.leadTime}
                    onChange={(e) => setFormData({ ...formData, leadTime: parseInt(e.target.value) || 0 })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    required
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Order Cutoff Time <span className="text-gray-400 text-xs">(optional)</span>
                  </label>
                  <input
                    type="time"
                    value={formData.orderCutoffTime}
                    onChange={(e) => setFormData({ ...formData, orderCutoffTime: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                  />
                  <p className="text-xs text-gray-500 mt-1">Daily deadline for orders</p>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Minimum Order Quantity <span className="text-gray-400 text-xs">(optional)</span>
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={formData.minimumOrderQty || ''}
                    onChange={(e) => setFormData({ ...formData, minimumOrderQty: e.target.value ? parseFloat(e.target.value) : undefined })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    placeholder="e.g. 50"
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Supplied Ingredients */}
          <div>
            <h3 className="font-semibold text-gray-900 mb-2">Supplied Ingredients</h3>
            <p className="text-sm text-gray-600 mb-4">Select which ingredients this supplier provides</p>
            <div className="border border-gray-200 rounded-lg p-4 max-h-64 overflow-y-auto">
              {availableIngredients.length === 0 ? (
                <p className="text-sm text-gray-500 text-center py-4">
                  No ingredients available. Add ingredients in Inventory Management first.
                </p>
              ) : (
                <div className="space-y-2">
                  {availableIngredients.map((ingredient) => (
                    <label
                      key={ingredient.id}
                      className="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer"
                    >
                      <input
                        type="checkbox"
                        checked={selectedIngredients.includes(ingredient.name)}
                        onChange={() => handleIngredientToggle(ingredient.name)}
                        className="w-4 h-4 text-orange-600 rounded focus:ring-orange-500"
                      />
                      <span className="text-sm text-gray-900">{ingredient.name}</span>
                      <span className="text-xs text-gray-500">({ingredient.unit})</span>
                    </label>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Status */}
          <div>
            <h3 className="font-semibold text-gray-900 mb-4">Status</h3>
            <label className="flex items-center gap-3 cursor-pointer">
              <div className="relative">
                <input
                  type="checkbox"
                  checked={formData.status === 'active'}
                  onChange={(e) => setFormData({ ...formData, status: e.target.checked ? 'active' : 'inactive' })}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-600"></div>
              </div>
              <div>
                <p className="font-medium text-sm">
                  {formData.status === 'active' ? 'Active' : 'Inactive'}
                </p>
                <p className="text-xs text-gray-600">
                  {formData.status === 'active' 
                    ? 'This supplier is available for ordering' 
                    : 'This supplier is not available for ordering'}
                </p>
              </div>
            </label>
          </div>

          {/* Footer Actions */}
          <div className="flex justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" className="bg-orange-500 hover:bg-orange-600">
              {supplier ? 'Update Supplier' : 'Add Supplier'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
