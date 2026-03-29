import { useState, useEffect } from 'react';
import { X, Search, Loader2, Info, Database } from 'lucide-react';
import { Button } from '../ui/button';
import { Card, CardContent } from '../ui/card';
import { Badge } from '../ui/badge';
import { toast } from 'sonner@2.0.3';
import { searchFoodDatabase, type FoodSearchResult } from '../../utils/nutritionDatabase';

interface AddIngredientModalProps {
  onClose: () => void;
  onAdd: (data: any) => void;
}

export function AddIngredientModal({ onClose, onAdd }: AddIngredientModalProps) {
  const [saving, setSaving] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<FoodSearchResult[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [showResults, setShowResults] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    category: 'Vegetables',
    unit: 'kg',
    currentStock: 0,
    reorderLevel: 0,
    reorderQuantity: 0,
    supplier: '',
    costPerUnit: 0,
    // Nutrition per 100g/100ml/piece
    nutrition: {
      calories: 0,
      fat: 0,
      protein: 0,
      carbs: 0
    },
    nutritionSource: 'manual' as 'database' | 'manual'
  });

  const categories = ['Vegetables', 'Meat & Poultry', 'Dairy', 'Grains', 'Spices', 'Beverages', 'Other'];
  const units = ['kg', 'g', 'liters', 'ml', 'pieces', 'bunches', 'boxes', 'cans', 'bottles'];

  // Search debouncing
  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchQuery.length >= 2) {
        performSearch();
      } else {
        setSearchResults([]);
        setShowResults(false);
      }
    }, 500);

    return () => clearTimeout(timer);
  }, [searchQuery]);

  const performSearch = async () => {
    setIsSearching(true);
    try {
      const results = await searchFoodDatabase(searchQuery);
      setSearchResults(results);
      setShowResults(true);
    } catch (error) {
      console.error('Search error:', error);
    } finally {
      setIsSearching(false);
    }
  };

  const handleSelectSearchResult = (result: FoodSearchResult) => {
    setFormData({
      ...formData,
      name: result.name,
      nutrition: result.nutrition,
      nutritionSource: 'database'
    });
    setSearchQuery('');
    setShowResults(false);
    toast.success('Nutrition data loaded from database');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!formData.name.trim()) {
      toast.error('Please enter an ingredient name');
      return;
    }

    setSaving(true);
    try {
      await onAdd(formData);
      toast.success('Ingredient added successfully');
      onClose();
    } catch (error) {
      toast.error('Failed to add ingredient');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <div>
            <h2 className="text-xl font-semibold text-gray-900">Add Ingredient</h2>
            <p className="text-sm text-gray-600 mt-1">
              Add a new ingredient to your inventory
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="w-5 h-5 text-gray-500" />
          </button>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6">
          <div className="space-y-6">
            {/* Basic Information */}
            <Card>
              <CardContent className="p-4 space-y-4">
                <h3 className="font-medium text-gray-900">Basic Information</h3>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Ingredient Name *
                  </label>
                  <input
                    type="text"
                    required
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    placeholder="e.g., Tomatoes (Fresh)"
                  />
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Category
                    </label>
                    <select
                      value={formData.category}
                      onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    >
                      {categories.map(cat => (
                        <option key={cat} value={cat}>{cat}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Unit *
                    </label>
                    <select
                      required
                      value={formData.unit}
                      onChange={(e) => setFormData({ ...formData, unit: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    >
                      {units.map(unit => (
                        <option key={unit} value={unit}>{unit}</option>
                      ))}
                    </select>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Stock Levels */}
            <Card>
              <CardContent className="p-4 space-y-4">
                <h3 className="font-medium text-gray-900">Stock Levels</h3>
                
                <div className="grid grid-cols-3 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Current Stock
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={formData.currentStock}
                      onChange={(e) => setFormData({ ...formData, currentStock: parseFloat(e.target.value) || 0 })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Reorder Level
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={formData.reorderLevel}
                      onChange={(e) => setFormData({ ...formData, reorderLevel: parseFloat(e.target.value) || 0 })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Reorder Quantity
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={formData.reorderQuantity}
                      onChange={(e) => setFormData({ ...formData, reorderQuantity: parseFloat(e.target.value) || 0 })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    />
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Supplier & Cost */}
            <Card>
              <CardContent className="p-4 space-y-4">
                <h3 className="font-medium text-gray-900">Supplier & Cost</h3>
                
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Supplier
                    </label>
                    <input
                      type="text"
                      value={formData.supplier}
                      onChange={(e) => setFormData({ ...formData, supplier: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                      placeholder="e.g., Fresh Foods Co"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Cost per Unit (€)
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={formData.costPerUnit}
                      onChange={(e) => setFormData({ ...formData, costPerUnit: parseFloat(e.target.value) || 0 })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    />
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Nutrition */}
            <Card>
              <CardContent className="p-4 space-y-4">
                <div className="flex items-center justify-between">
                  <h3 className="font-medium text-gray-900">Nutrition per 100g / 100ml / piece</h3>
                  {formData.nutritionSource === 'database' && (
                    <Badge variant="default" className="text-xs">
                      <Database className="w-3 h-3 mr-1" />
                      Database
                    </Badge>
                  )}
                  {formData.nutritionSource === 'manual' && (
                    <Badge variant="outline" className="text-xs">
                      Custom
                    </Badge>
                  )}
                </div>

                {/* Search nutrition database */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Search nutrition database
                  </label>
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input
                      type="text"
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      placeholder="Type ingredient name..."
                      className="w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    />
                    {isSearching && (
                      <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-orange-600 animate-spin" />
                    )}
                  </div>

                  {/* Search Results Dropdown */}
                  {showResults && searchResults.length > 0 && (
                    <div className="mt-2 border border-gray-200 rounded-lg bg-white shadow-lg max-h-48 overflow-y-auto">
                      {searchResults.map((result) => (
                        <button
                          key={result.id}
                          type="button"
                          onClick={() => handleSelectSearchResult(result)}
                          className="w-full p-3 hover:bg-orange-50 text-left border-b border-gray-100 last:border-b-0 transition-colors"
                        >
                          <div className="flex items-start justify-between gap-3">
                            <div className="flex-1 min-w-0">
                              <div className="font-medium text-gray-900 text-sm">{result.name}</div>
                              {result.brand && (
                                <div className="text-xs text-gray-500 mt-0.5">{result.brand}</div>
                              )}
                            </div>
                            <Badge variant="outline" className="text-xs flex-shrink-0">
                              Open Food Facts
                            </Badge>
                          </div>
                          <div className="grid grid-cols-4 gap-2 mt-2 text-xs text-gray-600">
                            <div><span className="font-medium">{result.nutrition.calories}</span> kcal</div>
                            <div><span className="font-medium">{result.nutrition.fat}g</span> fat</div>
                            <div><span className="font-medium">{result.nutrition.protein}g</span> protein</div>
                            <div><span className="font-medium">{result.nutrition.carbs}g</span> carbs</div>
                          </div>
                        </button>
                      ))}
                    </div>
                  )}

                  {showResults && searchResults.length === 0 && !isSearching && searchQuery.length >= 2 && (
                    <div className="mt-2 p-3 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
                      No results found. Enter nutrition values manually below.
                    </div>
                  )}
                </div>

                {/* Nutrition fields */}
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Calories (kcal)
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="1"
                      value={formData.nutrition.calories}
                      onChange={(e) => setFormData({ 
                        ...formData, 
                        nutrition: { ...formData.nutrition, calories: parseInt(e.target.value) || 0 },
                        nutritionSource: 'manual'
                      })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Fat (g)
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.1"
                      value={formData.nutrition.fat}
                      onChange={(e) => setFormData({ 
                        ...formData, 
                        nutrition: { ...formData.nutrition, fat: parseFloat(e.target.value) || 0 },
                        nutritionSource: 'manual'
                      })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Protein (g)
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.1"
                      value={formData.nutrition.protein}
                      onChange={(e) => setFormData({ 
                        ...formData, 
                        nutrition: { ...formData.nutrition, protein: parseFloat(e.target.value) || 0 },
                        nutritionSource: 'manual'
                      })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Carbs (g)
                    </label>
                    <input
                      type="number"
                      min="0"
                      step="0.1"
                      value={formData.nutrition.carbs}
                      onChange={(e) => setFormData({ 
                        ...formData, 
                        nutrition: { ...formData.nutrition, carbs: parseFloat(e.target.value) || 0 },
                        nutritionSource: 'manual'
                      })}
                      className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    />
                  </div>
                </div>

                {/* Info tooltip */}
                <div className="flex items-start gap-2 p-2 bg-blue-50 rounded text-xs text-blue-900">
                  <Info className="w-3 h-3 flex-shrink-0 mt-0.5" />
                  <p>Nutrition values are estimated per 100g and stored locally.</p>
                </div>
              </CardContent>
            </Card>
          </div>
        </form>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
          <Button
            variant="outline"
            onClick={onClose}
            disabled={saving}
          >
            Cancel
          </Button>
          <Button
            onClick={handleSubmit}
            disabled={saving}
            className="bg-orange-500 hover:bg-orange-600"
          >
            {saving ? 'Adding...' : 'Add Ingredient'}
          </Button>
        </div>
      </div>
    </div>
  );
}