import { Package, TrendingUp, TrendingDown, AlertCircle, Edit2, X, ChevronRight, HelpCircle, History, ShoppingCart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { useState } from 'react';
import { PurchaseOrderModal } from './PurchaseOrderModal';

interface InventoryItem {
  id: string;
  name: string;
  category: string;
  currentStock: number;
  unit: string;
  reorderLevel: number;
  reorderQuantity: number;
  supplier?: string;
  costPerUnit: number;
  lastUpdated: string;
  status: 'ok' | 'low' | 'out';
  criticalImpact?: boolean;
}

interface IngredientDetailModalProps {
  ingredient: InventoryItem;
  onClose: () => void;
  onUpdate: (data: any) => void;
  onDelete: () => void;
}

type AdjustmentType = 'waste' | 'delivery' | 'correction' | '';

export function IngredientDetailModal({ ingredient, onClose, onUpdate, onDelete }: IngredientDetailModalProps) {
  const [adjusting, setAdjusting] = useState(false);
  const [adjustmentAmount, setAdjustmentAmount] = useState(0);
  const [adjustmentType, setAdjustmentType] = useState<AdjustmentType>('');
  const [adjustmentReason, setAdjustmentReason] = useState('');
  const [showPurchaseOrderModal, setShowPurchaseOrderModal] = useState(false);

  // Mock supplier data - in real app, this would be fetched based on ingredient
  const mockSuppliers = [
    {
      id: '1',
      name: 'Fresh Foods Co',
      leadTime: 1,
      orderingMethod: 'Email' as const,
      email: 'orders@freshfoods.com'
    },
    {
      id: '2',
      name: 'Italian Imports',
      leadTime: 2,
      orderingMethod: 'Email' as const,
      email: 'orders@italianimports.at'
    }
  ];

  // Filter suppliers based on the ingredient name
  // In real app, this would check which suppliers support this specific ingredient
  const availableSuppliers = ingredient.name === 'Tomatoes' || ingredient.name === 'Lettuce'
    ? [mockSuppliers[0]]  // Fresh Foods Co
    : ingredient.name === 'Mozzarella' || ingredient.name === 'Parmesan'
    ? [mockSuppliers[1]]  // Italian Imports
    : mockSuppliers;  // Both suppliers

  // Only show active suppliers
  const activeSuppliers = availableSuppliers.filter(s => s);

  const activityLog = [
    { date: '2024-01-29', type: 'delivery', source: 'Supplier Delivery', amount: 50, note: 'Delivery from Fresh Foods Co', user: 'System' },
    { date: '2024-01-28', type: 'order', source: 'Order', amount: -15, note: 'Order #1234 - Margherita Pizza', user: 'Auto' },
    { date: '2024-01-27', type: 'manual', source: 'Manual', amount: -5, note: 'Waste - Spoilage', user: 'Manager' },
    { date: '2024-01-25', type: 'import', source: 'Excel Import', amount: 25, note: 'Bulk import update', user: 'System' },
  ];

  const affectedMenuItems = [
    { id: '1', name: 'Margherita Pizza', quantity: 0.1, unit: 'kg', isCritical: true },
    { id: '2', name: 'Caprese Salad', quantity: 0.15, unit: 'kg', isCritical: false },
    { id: '3', name: 'Pasta Pomodoro', quantity: 0.2, unit: 'kg', isCritical: true },
  ];

  const handleAdjustStock = async () => {
    if (adjustmentAmount === 0) {
      toast.error('Please enter an adjustment amount');
      return;
    }

    if (!adjustmentType) {
      toast.error('Please select an adjustment type');
      return;
    }

    try {
      await onUpdate({
        currentStock: ingredient.currentStock + adjustmentAmount,
        adjustmentType,
        adjustmentReason
      });
      toast.success('Stock adjusted successfully');
      setAdjusting(false);
      setAdjustmentAmount(0);
      setAdjustmentType('');
      setAdjustmentReason('');
    } catch (error) {
      toast.error('Failed to adjust stock');
    }
  };

  const getStatusInfo = (status: string) => {
    switch (status) {
      case 'ok':
        return {
          badge: <Badge className="bg-green-600 hover:bg-green-600 text-white border-0 font-medium text-sm px-3 py-1">In Stock</Badge>,
          comparison: null
        };
      case 'low':
        const belowReorder = ingredient.reorderLevel - ingredient.currentStock;
        return {
          badge: <Badge className="bg-amber-500 hover:bg-amber-500 text-white border-0 font-medium text-sm px-3 py-1">Low Stock</Badge>,
          comparison: (
            <div className="flex items-center gap-2 mt-2 text-sm">
              <TrendingDown className="h-4 w-4 text-amber-500" />
              <span className="text-amber-700">
                <strong>{belowReorder} {ingredient.unit}</strong> below reorder level
              </span>
            </div>
          )
        };
      case 'out':
        return {
          badge: <Badge className="bg-red-600 hover:bg-red-600 text-white border-0 font-medium text-sm px-3 py-1">Out of Stock</Badge>,
          comparison: (
            <div className="flex items-center gap-2 mt-2 text-sm">
              <AlertCircle className="h-4 w-4 text-red-500" />
              <span className="text-red-700">
                Restock <strong>{ingredient.reorderQuantity} {ingredient.unit}</strong> immediately
              </span>
            </div>
          )
        };
      default:
        return { badge: null, comparison: null };
    }
  };

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'delivery':
        return <TrendingUp className="w-4 h-4 text-green-600" />;
      case 'order':
        return <Package className="w-4 h-4 text-blue-600" />;
      case 'manual':
        return <Edit2 className="w-4 h-4 text-orange-600" />;
      case 'import':
        return <History className="w-4 h-4 text-purple-600" />;
      default:
        return <History className="w-4 h-4 text-gray-600" />;
    }
  };

  const statusInfo = getStatusInfo(ingredient.status);
  const criticalItems = affectedMenuItems.filter(i => i.isCritical);

  const handleCreatePurchaseOrder = () => {
    if (activeSuppliers.length === 0) {
      toast.error('No active supplier linked to this ingredient.');
      return;
    }
    setShowPurchaseOrderModal(true);
  };

  const handleConfirmPurchaseOrder = (orderData: any) => {
    console.log('Purchase Order Created:', orderData);
    
    // Show confirmation based on ordering method
    if (orderData.orderingMethod === 'Email') {
      toast.success('Purchase order will be sent via email.', {
        description: `Order for ${orderData.quantity} ${orderData.unit} of ${orderData.ingredientName} to ${orderData.supplierName}`
      });
    } else {
      toast.success('Marked as ordered externally.', {
        description: `Remember to contact ${orderData.supplierName} to complete the order`
      });
    }
    
    setShowPurchaseOrderModal(false);
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
          <div className="flex-1">
            <div className="flex items-center gap-3 mb-2">
              <h2 className="text-2xl font-bold text-gray-900">{ingredient.name}</h2>
              {statusInfo.badge}
              {ingredient.criticalImpact && (
                <Badge className="bg-orange-100 text-orange-800 hover:bg-orange-100 border border-orange-200">
                  Critical Impact
                </Badge>
              )}
            </div>
            <div className="flex items-center gap-4 text-sm text-gray-600">
              <span>{ingredient.category}</span>
              <span>•</span>
              <span>{ingredient.supplier || 'No supplier'}</span>
              <span>•</span>
              <span>Last updated {new Date(ingredient.lastUpdated).toLocaleDateString()}</span>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-200 rounded-lg transition-colors ml-4"
          >
            <X className="w-5 h-5 text-gray-500" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* Ingredient Summary - Visually Dominant */}
          <Card className="border-2 border-orange-200 shadow-sm">
            <CardContent className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <Package className="w-5 h-5 text-orange-500" />
                Ingredient Summary
              </h3>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div className="relative">
                  <div className="flex items-center gap-1 mb-2">
                    <p className="text-xs uppercase tracking-wide text-gray-500">Current Stock</p>
                    <div className="relative group/tooltip">
                      <HelpCircle className="w-3.5 h-3.5 text-gray-400 cursor-help" />
                      <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover/tooltip:block z-50 pointer-events-none">
                        <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-xl">
                          How much you have right now
                          <div className="absolute top-full left-1/2 -translate-x-1/2">
                            <div className="border-[5px] border-transparent border-t-gray-900"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <p className="text-3xl font-bold text-gray-900">
                    {ingredient.currentStock}
                  </p>
                  <p className="text-sm text-gray-600 mt-1">{ingredient.unit}</p>
                </div>
                <div className="relative">
                  <div className="flex items-center gap-1 mb-2">
                    <p className="text-xs uppercase tracking-wide text-gray-500">Reorder Level</p>
                    <div className="relative group/tooltip">
                      <HelpCircle className="w-3.5 h-3.5 text-gray-400 cursor-help" />
                      <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover/tooltip:block z-50 pointer-events-none">
                        <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-xl" style={{ width: '200px' }}>
                          When stock hits this point, it's time to order more
                          <div className="absolute top-full left-1/2 -translate-x-1/2">
                            <div className="border-[5px] border-transparent border-t-gray-900"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <p className="text-3xl font-bold text-gray-900">
                    {ingredient.reorderLevel}
                  </p>
                  <p className="text-sm text-gray-600 mt-1">{ingredient.unit}</p>
                </div>
                <div className="relative">
                  <div className="flex items-center gap-1 mb-2">
                    <p className="text-xs uppercase tracking-wide text-gray-500">Reorder Quantity</p>
                    <div className="relative group/tooltip">
                      <HelpCircle className="w-3.5 h-3.5 text-gray-400 cursor-help" />
                      <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover/tooltip:block z-50 pointer-events-none">
                        <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-xl" style={{ width: '200px' }}>
                          How much to order each time you restock
                          <div className="absolute top-full left-1/2 -translate-x-1/2">
                            <div className="border-[5px] border-transparent border-t-gray-900"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <p className="text-3xl font-bold text-gray-900">
                    {ingredient.reorderQuantity}
                  </p>
                  <p className="text-sm text-gray-600 mt-1">{ingredient.unit}</p>
                </div>
                <div className="relative">
                  <div className="flex items-center gap-1 mb-2">
                    <p className="text-xs uppercase tracking-wide text-gray-500">Cost per Unit</p>
                    <div className="relative group/tooltip">
                      <HelpCircle className="w-3.5 h-3.5 text-gray-400 cursor-help" />
                      <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 hidden group-hover/tooltip:block z-50 pointer-events-none">
                        <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-xl" style={{ width: '200px' }}>
                          Price per {ingredient.unit}. Total shows current stock value
                          <div className="absolute top-full left-1/2 -translate-x-1/2">
                            <div className="border-[5px] border-transparent border-t-gray-900"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <p className="text-3xl font-bold text-gray-900">
                    €{ingredient.costPerUnit.toFixed(2)}
                  </p>
                  <p className="text-sm text-gray-600 mt-1">Total: €{(ingredient.currentStock * ingredient.costPerUnit).toFixed(2)}</p>
                </div>
              </div>
              
              {/* Stock Comparison */}
              {statusInfo.comparison && (
                <div className="mt-6 pt-6 border-t border-gray-200">
                  {statusInfo.comparison}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Adjust Stock */}
          <Card>
            <CardContent className="p-6">
              <h3 className="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <Edit2 className="w-5 h-5 text-gray-600" />
                Adjust Stock
              </h3>
              {adjusting ? (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Adjustment Type <span className="text-red-500">*</span>
                      </label>
                      <select
                        value={adjustmentType}
                        onChange={(e) => setAdjustmentType(e.target.value as AdjustmentType)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                      >
                        <option value="">Select type...</option>
                        <option value="waste">Waste</option>
                        <option value="delivery">Delivery</option>
                        <option value="correction">Correction</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Adjustment Amount ({ingredient.unit}) <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        value={adjustmentAmount || ''}
                        onChange={(e) => setAdjustmentAmount(parseFloat(e.target.value) || 0)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                        placeholder="e.g., -5 or +50"
                      />
                      {adjustmentAmount !== 0 && (
                        <p className="text-xs text-gray-600 mt-1 font-medium">
                          New stock: {ingredient.currentStock + adjustmentAmount} {ingredient.unit}
                        </p>
                      )}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Reason (optional)
                      </label>
                      <input
                        type="text"
                        value={adjustmentReason}
                        onChange={(e) => setAdjustmentReason(e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                        placeholder="e.g., Spoiled batch"
                      />
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      onClick={handleAdjustStock}
                      disabled={!adjustmentType || adjustmentAmount === 0}
                      className="bg-orange-500 hover:bg-orange-600 disabled:bg-gray-300 disabled:cursor-not-allowed"
                    >
                      Confirm Adjustment
                    </Button>
                    <Button
                      variant="outline"
                      onClick={() => {
                        setAdjusting(false);
                        setAdjustmentAmount(0);
                        setAdjustmentType('');
                        setAdjustmentReason('');
                      }}
                    >
                      Cancel
                    </Button>
                  </div>
                </div>
              ) : (
                <Button
                  onClick={() => setAdjusting(true)}
                  className="bg-orange-500 hover:bg-orange-600"
                >
                  <Edit2 className="w-4 h-4 mr-2" />
                  Adjust Stock
                </Button>
              )}
            </CardContent>
          </Card>

          {/* Affected Menu Items */}
          <Card>
            <CardContent className="p-6">
              <h3 className="font-semibold text-gray-900 mb-2">Affected Menu Items</h3>
              <p className="text-sm text-gray-600 mb-4">
                If this ingredient runs out, these items will be auto-unavailable.
              </p>
              <div className="space-y-2">
                {affectedMenuItems.map(item => (
                  <div key={item.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div className="flex items-center gap-3">
                      <Package className="w-4 h-4 text-gray-600" />
                      <div>
                        <p className="font-medium text-gray-900">{item.name}</p>
                        <p className="text-sm text-gray-600">
                          Uses {item.quantity} {item.unit} per serving
                        </p>
                      </div>
                    </div>
                    {item.isCritical && (
                      <Badge className="bg-red-600 hover:bg-red-600 text-white border-0 font-medium">
                        Critical
                      </Badge>
                    )}
                  </div>
                ))}
              </div>
              {ingredient.status === 'out' && criticalItems.length > 0 && (
                <div className="mt-4 p-4 border-2 border-red-200 bg-red-50 rounded-lg">
                  <div className="flex gap-3">
                    <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <div className="text-sm">
                      <p className="font-semibold text-red-900 mb-1">Active Menu Impact</p>
                      <p className="text-red-800">
                        <strong>{criticalItems.length} menu item{criticalItems.length > 1 ? 's' : ''}</strong> {criticalItems.length > 1 ? 'are' : 'is'} currently unavailable due to this stock shortage.
                      </p>
                    </div>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Stock Activity Log */}
          <Card>
            <CardContent className="p-6">
              <h3 className="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <History className="w-5 h-5 text-gray-600" />
                Stock Activity Log
              </h3>
              <div className="space-y-3">
                {activityLog.map((activity, index) => (
                  <div key={index} className="flex items-start gap-3 pb-3 border-b border-gray-100 last:border-0 last:pb-0">
                    <div className="w-9 h-9 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                      {getActivityIcon(activity.type)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-4">
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-0.5">
                            <span className={`font-semibold ${activity.amount > 0 ? 'text-green-700' : 'text-red-700'}`}>
                              {activity.amount > 0 ? '+' : ''}{activity.amount} {ingredient.unit}
                            </span>
                            <Badge variant="secondary" className="text-xs">
                              {activity.source}
                            </Badge>
                          </div>
                          <p className="text-sm text-gray-700">{activity.note}</p>
                        </div>
                        <div className="text-right flex-shrink-0">
                          <p className="text-sm font-medium text-gray-900">{new Date(activity.date).toLocaleDateString()}</p>
                          <p className="text-xs text-gray-500">{activity.user}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Footer Actions */}
        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between bg-gray-50">
          <Button 
            variant="outline" 
            onClick={onDelete}
            className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
          >
            Delete Ingredient
          </Button>
          <div className="flex gap-2">
            <Button variant="outline" onClick={onClose}>
              Close
            </Button>
            <Button className="bg-blue-600 hover:bg-blue-700" onClick={handleCreatePurchaseOrder}>
              <ShoppingCart className="w-4 h-4 mr-2" />
              Create Purchase Order
            </Button>
          </div>
        </div>
      </div>

      {/* Purchase Order Modal */}
      <PurchaseOrderModal
        isOpen={showPurchaseOrderModal}
        onClose={() => setShowPurchaseOrderModal(false)}
        onConfirm={handleConfirmPurchaseOrder}
        suppliers={activeSuppliers}
        ingredient={ingredient}
        preselectedSupplier={activeSuppliers.length === 1 ? activeSuppliers[0] : undefined}
      />
    </div>
  );
}