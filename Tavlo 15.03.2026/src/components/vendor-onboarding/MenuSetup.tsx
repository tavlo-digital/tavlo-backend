import { useState } from 'react';
import { Plus, Trash2, FolderPlus, Upload, Tag } from 'lucide-react';
import { Button } from '../ui/button';

interface MenuItem {
  id: string;
  name: string;
  description: string;
  price: number;
  vatRate: number;
  imageUrl?: string;
  category: string;
}

interface MenuCategory {
  id: string;
  name: string;
  items: MenuItem[];
}

interface MenuSetupProps {
  initialData?: MenuCategory[];
  onSave: (categories: MenuCategory[]) => void;
  onSkip?: () => void;
}

const VAT_RATES = [
  { value: 10, label: '10% (Reduced)' },
  { value: 13, label: '13% (Standard)' },
  { value: 20, label: '20% (Full)' }
];

export function MenuSetup({ initialData, onSave, onSkip }: MenuSetupProps) {
  const [categories, setCategories] = useState<MenuCategory[]>(
    initialData || [{ id: '1', name: 'Main Courses', items: [] }]
  );
  const [activeCategory, setActiveCategory] = useState(0);
  const [showAddItem, setShowAddItem] = useState(false);

  const [newItem, setNewItem] = useState<Partial<MenuItem>>({
    name: '',
    description: '',
    price: 0,
    vatRate: 13,
    category: categories[0]?.id
  });

  const addCategory = () => {
    const newCategory: MenuCategory = {
      id: Date.now().toString(),
      name: 'New Category',
      items: []
    };
    setCategories([...categories, newCategory]);
    setActiveCategory(categories.length);
  };

  const deleteCategory = (index: number) => {
    if (categories.length === 1) return; // Keep at least one category
    setCategories(categories.filter((_, i) => i !== index));
    if (activeCategory >= index && activeCategory > 0) {
      setActiveCategory(activeCategory - 1);
    }
  };

  const updateCategoryName = (index: number, name: string) => {
    const updated = [...categories];
    updated[index].name = name;
    setCategories(updated);
  };

  const addItem = () => {
    if (!newItem.name || !newItem.price) return;

    const item: MenuItem = {
      id: Date.now().toString(),
      name: newItem.name,
      description: newItem.description || '',
      price: newItem.price,
      vatRate: newItem.vatRate || 13,
      category: categories[activeCategory].id
    };

    const updated = [...categories];
    updated[activeCategory].items.push(item);
    setCategories(updated);

    setNewItem({
      name: '',
      description: '',
      price: 0,
      vatRate: 13,
      category: categories[activeCategory].id
    });
    setShowAddItem(false);
  };

  const deleteItem = (categoryIndex: number, itemId: string) => {
    const updated = [...categories];
    updated[categoryIndex].items = updated[categoryIndex].items.filter(
      item => item.id !== itemId
    );
    setCategories(updated);
  };

  const handleSave = () => {
    onSave(categories);
  };

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-7xl mx-auto px-4 sm:px-6">
        <div className="bg-white rounded-2xl shadow-sm">
          {/* Header */}
          <div className="p-8 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <h1 className="text-2xl text-gray-900 mb-1">Menu Setup</h1>
                <p className="text-gray-600">Organize your menu with categories and items</p>
              </div>
              <div className="px-3 py-1 bg-amber-100 text-amber-800 text-sm rounded-full">
                Draft
              </div>
            </div>
          </div>

          <div className="flex">
            {/* Sidebar - Categories */}
            <div className="w-64 border-r border-gray-200 p-4">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm text-gray-700">Categories</h3>
                <button
                  onClick={addCategory}
                  className="p-1 hover:bg-gray-100 rounded"
                  title="Add category"
                >
                  <FolderPlus className="w-4 h-4 text-gray-600" />
                </button>
              </div>

              <div className="space-y-1">
                {categories.map((category, index) => (
                  <div
                    key={category.id}
                    className={`group flex items-center gap-2 p-3 rounded-lg cursor-pointer transition-colors ${
                      activeCategory === index
                        ? 'bg-emerald-50 text-emerald-900'
                        : 'hover:bg-gray-100 text-gray-700'
                    }`}
                  >
                    <input
                      type="text"
                      value={category.name}
                      onChange={(e) => updateCategoryName(index, e.target.value)}
                      onClick={() => setActiveCategory(index)}
                      className="flex-1 bg-transparent border-none outline-none text-sm"
                    />
                    <span className="text-xs text-gray-500">
                      {category.items.length}
                    </span>
                    {categories.length > 1 && (
                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          deleteCategory(index);
                        }}
                        className="opacity-0 group-hover:opacity-100 p-1 hover:bg-red-100 rounded"
                      >
                        <Trash2 className="w-3 h-3 text-red-600" />
                      </button>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* Main Content - Items */}
            <div className="flex-1 p-8">
              <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl text-gray-900">
                  {categories[activeCategory]?.name}
                </h2>
                <Button
                  onClick={() => setShowAddItem(true)}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white"
                >
                  <Plus className="w-4 h-4 mr-2" />
                  Add item
                </Button>
              </div>

              {/* Add Item Form */}
              {showAddItem && (
                <div className="mb-6 p-6 bg-gray-50 rounded-xl border border-gray-200">
                  <h3 className="text-lg text-gray-900 mb-4">New Item</h3>
                  <div className="grid sm:grid-cols-2 gap-4 mb-4">
                    <div>
                      <label className="block text-sm text-gray-700 mb-2">Item Name *</label>
                      <input
                        type="text"
                        value={newItem.name}
                        onChange={(e) => setNewItem({ ...newItem, name: e.target.value })}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                        placeholder="e.g., Margherita Pizza"
                      />
                    </div>
                    <div>
                      <label className="block text-sm text-gray-700 mb-2">Price (EUR) *</label>
                      <input
                        type="number"
                        step="0.01"
                        value={newItem.price}
                        onChange={(e) => setNewItem({ ...newItem, price: parseFloat(e.target.value) })}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                        placeholder="12.50"
                      />
                    </div>
                  </div>
                  <div className="mb-4">
                    <label className="block text-sm text-gray-700 mb-2">Description</label>
                    <textarea
                      value={newItem.description}
                      onChange={(e) => setNewItem({ ...newItem, description: e.target.value })}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                      rows={2}
                      placeholder="Brief description of the item"
                    />
                  </div>
                  <div className="mb-4">
                    <label className="block text-sm text-gray-700 mb-2">VAT Rate</label>
                    <select
                      value={newItem.vatRate}
                      onChange={(e) => setNewItem({ ...newItem, vatRate: parseInt(e.target.value) })}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500"
                    >
                      {VAT_RATES.map(rate => (
                        <option key={rate.value} value={rate.value}>{rate.label}</option>
                      ))}
                    </select>
                  </div>
                  <div className="flex gap-3">
                    <Button onClick={addItem} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                      Add item
                    </Button>
                    <Button
                      onClick={() => setShowAddItem(false)}
                      variant="ghost"
                      className="text-gray-600"
                    >
                      Cancel
                    </Button>
                  </div>
                </div>
              )}

              {/* Items List */}
              <div className="space-y-3">
                {categories[activeCategory]?.items.length === 0 && !showAddItem && (
                  <div className="text-center py-12 text-gray-500">
                    <Tag className="w-12 h-12 mx-auto mb-3 text-gray-400" />
                    <p>No items yet. Add your first item to get started.</p>
                  </div>
                )}

                {categories[activeCategory]?.items.map((item) => (
                  <div
                    key={item.id}
                    className="flex items-center justify-between p-4 bg-white border border-gray-200 rounded-lg hover:border-gray-300 transition-colors"
                  >
                    <div className="flex-1">
                      <h4 className="text-gray-900 mb-1">{item.name}</h4>
                      {item.description && (
                        <p className="text-sm text-gray-600 mb-2">{item.description}</p>
                      )}
                      <div className="flex items-center gap-4 text-sm">
                        <span className="text-gray-900">€{item.price.toFixed(2)}</span>
                        <span className="text-gray-500">VAT: {item.vatRate}%</span>
                      </div>
                    </div>
                    <button
                      onClick={() => deleteItem(activeCategory, item.id)}
                      className="p-2 hover:bg-red-50 rounded-lg transition-colors"
                    >
                      <Trash2 className="w-4 h-4 text-red-600" />
                    </button>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Footer Actions */}
          <div className="p-8 border-t border-gray-200 flex items-center justify-between">
            {onSkip && (
              <Button
                onClick={onSkip}
                variant="ghost"
                className="text-gray-600"
              >
                Skip for now
              </Button>
            )}
            <div className="flex gap-3 ml-auto">
              <Button
                onClick={handleSave}
                className="bg-emerald-600 hover:bg-emerald-700 text-white px-8"
              >
                Save & continue
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
