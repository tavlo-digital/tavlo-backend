import { Menu, Plus, Trash2, ArrowRight, ArrowLeft, CheckCircle } from 'lucide-react';
import { Button } from '../ui/button';
import { useState } from 'react';

interface MenuItem {
  id: string;
  name: string;
  price: number;
  vat: number;
}

interface MenuCategory {
  id: string;
  name: string;
  items: MenuItem[];
}

interface SetupStep3MenuProps {
  onContinue: (data: { categories: MenuCategory[] }) => void;
  onBack: () => void;
  initialData?: { categories: MenuCategory[] };
}

export function SetupStep3Menu({ onContinue, onBack, initialData }: SetupStep3MenuProps) {
  const [categories, setCategories] = useState<MenuCategory[]>(
    initialData?.categories || []
  );
  const [newCategoryName, setNewCategoryName] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  const hasMinimumRequired = () => {
    return categories.length > 0 && categories.some(cat => cat.items.length > 0);
  };

  const addCategory = () => {
    if (!newCategoryName.trim()) {
      setErrors({ category: 'Category name required' });
      return;
    }

    setCategories([
      ...categories,
      {
        id: `cat-${Date.now()}`,
        name: newCategoryName,
        items: []
      }
    ]);
    setNewCategoryName('');
    setErrors({});
  };

  const deleteCategory = (categoryId: string) => {
    setCategories(categories.filter(cat => cat.id !== categoryId));
  };

  const addItem = (categoryId: string) => {
    setCategories(categories.map(cat => {
      if (cat.id === categoryId) {
        return {
          ...cat,
          items: [
            ...cat.items,
            {
              id: `item-${Date.now()}`,
              name: '',
              price: 0,
              vat: 20
            }
          ]
        };
      }
      return cat;
    }));
  };

  const updateItem = (categoryId: string, itemId: string, field: keyof MenuItem, value: string | number) => {
    setCategories(categories.map(cat => {
      if (cat.id === categoryId) {
        return {
          ...cat,
          items: cat.items.map(item => {
            if (item.id === itemId) {
              return { ...item, [field]: value };
            }
            return item;
          })
        };
      }
      return cat;
    }));
  };

  const deleteItem = (categoryId: string, itemId: string) => {
    setCategories(categories.map(cat => {
      if (cat.id === categoryId) {
        return {
          ...cat,
          items: cat.items.filter(item => item.id !== itemId)
        };
      }
      return cat;
    }));
  };

  const validate = () => {
    if (!hasMinimumRequired()) {
      setErrors({ submit: 'At least 1 category with 1 menu item is required' });
      return false;
    }

    // Check if all items have names and prices
    for (const cat of categories) {
      for (const item of cat.items) {
        if (!item.name.trim() || item.price <= 0) {
          setErrors({ submit: 'All menu items must have a name and price' });
          return false;
        }
      }
    }

    return true;
  };

  const handleContinue = () => {
    if (validate()) {
      onContinue({ categories });
    }
  };

  const getTotalItems = () => {
    return categories.reduce((sum, cat) => sum + cat.items.length, 0);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-4xl mx-auto px-4 py-8">
        {/* Progress */}
        <div className="mb-8">
          <p className="text-sm text-gray-500 mb-2">Activation step 3 of 4</p>
          <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
            <div className="h-full bg-emerald-600 rounded-full" style={{ width: '75%' }}></div>
          </div>
        </div>

        {/* Header */}
        <div className="mb-8">
          <div className="inline-flex items-center justify-center w-12 h-12 bg-emerald-100 rounded-lg mb-4">
            <Menu className="w-6 h-6 text-emerald-600" />
          </div>
          <h1 className="text-3xl mb-2 text-gray-900">Create your real menu</h1>
          <p className="text-gray-600 mb-2">
            This replaces the demo menu your customers saw.
          </p>
          <p className="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 inline-block">
            Minimum required to go live: 1 category, 1 item
          </p>
        </div>

        {/* Add Category */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
          <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-4">
            Add Category
          </h3>
          <div className="flex gap-3">
            <input
              type="text"
              value={newCategoryName}
              onChange={(e) => setNewCategoryName(e.target.value)}
              onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addCategory())}
              placeholder="e.g., Starters, Main Courses, Drinks"
              className="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <Button
              type="button"
              onClick={addCategory}
              className="bg-emerald-600 hover:bg-emerald-700 text-white px-6"
            >
              <Plus className="w-4 h-4 mr-2" />
              Add Category
            </Button>
          </div>
          {errors.category && (
            <p className="mt-2 text-sm text-red-600">{errors.category}</p>
          )}
        </div>

        {/* Categories & Items */}
        <div className="space-y-6 mb-6">
          {categories.map((category) => (
            <div key={category.id} className="bg-white rounded-xl border border-gray-200 p-6">
              {/* Category Header */}
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-xl text-gray-900">{category.name}</h3>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => addItem(category.id)}
                    className="text-sm text-emerald-600 hover:text-emerald-700 flex items-center gap-1"
                  >
                    <Plus className="w-4 h-4" />
                    Add item
                  </button>
                  <button
                    type="button"
                    onClick={() => deleteCategory(category.id)}
                    className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>

              {/* Items */}
              {category.items.length === 0 ? (
                <p className="text-sm text-gray-500 py-4 text-center">
                  No items yet. Click "Add item" to get started.
                </p>
              ) : (
                <div className="space-y-3">
                  {category.items.map((item) => (
                    <div key={item.id} className="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                      <div className="flex-1 grid grid-cols-3 gap-3">
                        {/* Name */}
                        <div>
                          <label className="block text-xs text-gray-600 mb-1">Name</label>
                          <input
                            type="text"
                            value={item.name}
                            onChange={(e) => updateItem(category.id, item.id, 'name', e.target.value)}
                            placeholder="Item name"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                          />
                        </div>

                        {/* Price */}
                        <div>
                          <label className="block text-xs text-gray-600 mb-1">Price (€)</label>
                          <input
                            type="number"
                            value={item.price || ''}
                            onChange={(e) => updateItem(category.id, item.id, 'price', parseFloat(e.target.value) || 0)}
                            placeholder="0.00"
                            step="0.01"
                            min="0"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                          />
                        </div>

                        {/* VAT */}
                        <div>
                          <label className="block text-xs text-gray-600 mb-1">VAT (%)</label>
                          <select
                            value={item.vat}
                            onChange={(e) => updateItem(category.id, item.id, 'vat', parseInt(e.target.value))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                          >
                            <option value="10">10%</option>
                            <option value="13">13%</option>
                            <option value="20">20%</option>
                          </select>
                        </div>
                      </div>

                      {/* Delete */}
                      <button
                        type="button"
                        onClick={() => deleteItem(category.id, item.id)}
                        className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-100 rounded-lg mt-5"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>

        {categories.length === 0 && (
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6 text-center">
            <p className="text-sm text-blue-900">
              Add your first category to get started with your menu
            </p>
          </div>
        )}

        {/* Error */}
        {errors.submit && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p className="text-sm text-red-900">{errors.submit}</p>
          </div>
        )}

        {/* Navigation */}
        <div className="flex items-center justify-between">
          <Button
            type="button"
            onClick={onBack}
            className="bg-gray-100 hover:bg-gray-200 text-gray-900 px-6 py-3"
          >
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back
          </Button>
          <Button
            onClick={handleContinue}
            disabled={!hasMinimumRequired()}
            className="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3 disabled:bg-gray-300 disabled:cursor-not-allowed"
          >
            Save & continue
            <ArrowRight className="w-4 h-4 ml-2" />
          </Button>
        </div>
      </div>
    </div>
  );
}