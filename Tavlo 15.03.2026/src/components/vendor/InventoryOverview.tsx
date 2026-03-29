import { useState, useEffect } from 'react';
import { Package, AlertTriangle, TrendingDown, Search, Plus, Download, Upload, Edit2, ShoppingCart, ChefHat, Filter, X, AlertCircle } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import { ExcelImportModal } from './ExcelImportModal';
import { IngredientDetailModal } from './IngredientDetailModal';
import { AddIngredientModal } from './AddIngredientModal';
import { api } from '../../utils/api';

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
  criticalImpact?: boolean; // Affects menu availability
}

interface InventoryOverviewProps {
  vendorId: string;
  onNavigateToSuppliers?: () => void;
}

const CATEGORIES = ['All', 'Vegetables', 'Meat & Poultry', 'Dairy', 'Grains', 'Spices', 'Beverages', 'Other'];
const STOCK_FILTERS = ['All', 'In Stock', 'Low Stock', 'Out of Stock', 'Critical Impact'] as const;

export function InventoryOverview({ vendorId, onNavigateToSuppliers }: InventoryOverviewProps) {
  const [inventory, setInventory] = useState<InventoryItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [stockFilter, setStockFilter] = useState<typeof STOCK_FILTERS[number]>('All');
  const [showExcelImport, setShowExcelImport] = useState(false);
  const [showAddModal, setShowAddModal] = useState(false);
  const [selectedIngredient, setSelectedIngredient] = useState<InventoryItem | null>(null);
  const [showDetailModal, setShowDetailModal] = useState(false);

  useEffect(() => {
    loadInventory();
  }, [vendorId]);

  async function loadInventory() {
    try {
      setLoading(true);
      const data = await api.getInventory(vendorId);
      setInventory(data);
    } catch (error) {
      console.error('Failed to load inventory:', error);
      // Use demo data if API fails
      setInventory(getDemoInventory());
    } finally {
      setLoading(false);
    }
  }

  function getDemoInventory(): InventoryItem[] {
    return [
      {
        id: '1',
        name: 'Tomatoes',
        category: 'Vegetables',
        currentStock: 50,
        unit: 'kg',
        reorderLevel: 20,
        reorderQuantity: 50,
        supplier: 'Fresh Farm Co.',
        costPerUnit: 2.5,
        lastUpdated: new Date().toISOString(),
        status: 'ok'
      },
      {
        id: '2',
        name: 'Chicken Breast',
        category: 'Meat & Poultry',
        currentStock: 15,
        unit: 'kg',
        reorderLevel: 20,
        reorderQuantity: 30,
        supplier: 'Premium Meats Ltd.',
        costPerUnit: 8.5,
        lastUpdated: new Date().toISOString(),
        status: 'low'
      },
      {
        id: '3',
        name: 'Mozzarella',
        category: 'Dairy',
        currentStock: 0,
        unit: 'kg',
        reorderLevel: 10,
        reorderQuantity: 25,
        supplier: 'Dairy Delights',
        costPerUnit: 6.0,
        lastUpdated: new Date().toISOString(),
        status: 'out'
      },
      {
        id: '4',
        name: 'Basil',
        category: 'Spices',
        currentStock: 5,
        unit: 'kg',
        reorderLevel: 3,
        reorderQuantity: 5,
        supplier: 'Herb Garden',
        costPerUnit: 12.0,
        lastUpdated: new Date().toISOString(),
        status: 'ok'
      },
      {
        id: '5',
        name: 'Olive Oil',
        category: 'Other',
        currentStock: 25,
        unit: 'liters',
        reorderLevel: 10,
        reorderQuantity: 20,
        supplier: 'Mediterranean Imports',
        costPerUnit: 15.0,
        lastUpdated: new Date().toISOString(),
        status: 'ok'
      },
      {
        id: '6',
        name: 'Pasta',
        category: 'Grains',
        currentStock: 8,
        unit: 'kg',
        reorderLevel: 15,
        reorderQuantity: 30,
        supplier: 'Italian Goods Co.',
        costPerUnit: 3.5,
        lastUpdated: new Date().toISOString(),
        status: 'low'
      },
      {
        id: '7',
        name: 'Parmesan',
        category: 'Dairy',
        currentStock: 12,
        unit: 'kg',
        reorderLevel: 5,
        reorderQuantity: 10,
        supplier: 'Dairy Delights',
        costPerUnit: 18.0,
        lastUpdated: new Date().toISOString(),
        status: 'ok'
      },
      {
        id: '8',
        name: 'Red Wine',
        category: 'Beverages',
        currentStock: 0,
        unit: 'bottles',
        reorderLevel: 12,
        reorderQuantity: 24,
        supplier: 'Wine Cellar Direct',
        costPerUnit: 12.0,
        lastUpdated: new Date().toISOString(),
        status: 'out'
      }
    ];
  }

  // Filter inventory
  const filteredInventory = inventory.filter(item => {
    const matchesSearch = item.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                         item.supplier?.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesCategory = selectedCategory === 'All' || item.category === selectedCategory;
    const matchesStock = stockFilter === 'All' ||
                        (stockFilter === 'In Stock' && item.status === 'ok') ||
                        (stockFilter === 'Low Stock' && item.status === 'low') ||
                        (stockFilter === 'Out of Stock' && item.status === 'out') ||
                        (stockFilter === 'Critical Impact' && item.criticalImpact);
    
    return matchesSearch && matchesCategory && matchesStock;
  });

  // Calculate stats
  const stats = {
    totalItems: inventory.length,
    lowStock: inventory.filter(i => i.status === 'low').length,
    outOfStock: inventory.filter(i => i.status === 'out').length,
    totalValue: inventory.reduce((sum, item) => sum + (item.currentStock * item.costPerUnit), 0)
  };

  const handleExcelImport = async (data: any[]) => {
    // This would normally call the API
    console.log('Importing Excel data:', data);
    await loadInventory();
  };

  const handleAddIngredient = async (data: any) => {
    try {
      await api.addInventoryItem(vendorId, data);
      await loadInventory();
    } catch (error) {
      console.error('Failed to add ingredient:', error);
    }
  };

  const handleUpdateIngredient = async (id: string, data: any) => {
    try {
      await api.updateInventoryItem(vendorId, id, data);
      await loadInventory();
    } catch (error) {
      console.error('Failed to update ingredient:', error);
    }
  };

  const handleDeleteIngredient = async (id: string) => {
    if (!confirm('Are you sure you want to delete this ingredient?')) return;
    
    try {
      await api.deleteInventoryItem(vendorId, id);
      await loadInventory();
    } catch (error) {
      console.error('Failed to delete ingredient:', error);
    }
  };

  const exportToExcel = () => {
    // Simple CSV export
    const headers = ['Name', 'Category', 'Current Stock', 'Unit', 'Reorder Level', 'Supplier', 'Cost Per Unit', 'Status'];
    const rows = filteredInventory.map(item => [
      item.name,
      item.category,
      item.currentStock,
      item.unit,
      item.reorderLevel,
      item.supplier || '',
      item.costPerUnit,
      item.status
    ]);
    
    const csv = [headers, ...rows].map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `inventory-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl mb-2">Inventory Management</h1>
        <p className="text-gray-600">Track and manage your ingredient stock levels</p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card>
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <CardTitle className="text-sm font-medium text-gray-600">Total Items</CardTitle>
              <Package className="h-4 w-4 text-gray-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.totalItems}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <CardTitle className="text-sm font-medium text-gray-600">Low Stock</CardTitle>
              <AlertTriangle className="h-4 w-4 text-yellow-500" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-yellow-600">{stats.lowStock}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <CardTitle className="text-sm font-medium text-gray-600">Out of Stock</CardTitle>
              <TrendingDown className="h-4 w-4 text-red-500" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-red-600">{stats.outOfStock}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-3">
            <div className="flex items-center justify-between">
              <CardTitle className="text-sm font-medium text-gray-600">Total Value</CardTitle>
              <ShoppingCart className="h-4 w-4 text-gray-400" />
            </div>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">€{stats.totalValue.toFixed(2)}</div>
          </CardContent>
        </Card>
      </div>

      {/* Filters and Actions */}
      <Card>
        <CardContent className="p-6 space-y-4">
          {/* Search and Action Buttons */}
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                placeholder="Search ingredients or suppliers..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
              />
            </div>
            <div className="flex gap-2">
              <Button
                onClick={() => setShowAddModal(true)}
                className="bg-orange-500 hover:bg-orange-600 text-white"
              >
                <Plus className="h-4 w-4 mr-2" />
                Add Item
              </Button>
              <Button
                onClick={() => setShowExcelImport(true)}
                variant="outline"
              >
                <Upload className="h-4 w-4 mr-2" />
                Import
              </Button>
              <Button
                onClick={exportToExcel}
                variant="outline"
              >
                <Download className="h-4 w-4 mr-2" />
                Export
              </Button>
            </div>
          </div>

          {/* Category Tabs */}
          <div className="flex flex-wrap gap-2">
            {CATEGORIES.map(category => (
              <button
                key={category}
                onClick={() => setSelectedCategory(category)}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                  selectedCategory === category
                    ? 'bg-orange-500 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                }`}
              >
                {category}
              </button>
            ))}
          </div>

          {/* Stock Status Filter */}
          <div className="flex items-center gap-2">
            <Filter className="h-4 w-4 text-gray-400" />
            <span className="text-sm text-gray-600">Filter by status:</span>
            {STOCK_FILTERS.map(filter => (
              <button
                key={filter}
                onClick={() => setStockFilter(filter)}
                className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${
                  stockFilter === filter
                    ? 'bg-orange-500 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                }`}
              >
                {filter}
              </button>
            ))}
          </div>

          {/* Active Filters Display */}
          {(searchQuery || selectedCategory !== 'All' || stockFilter !== 'All') && (
            <div className="flex items-center gap-2 pt-2 border-t">
              <span className="text-sm text-gray-600">Active filters:</span>
              {searchQuery && (
                <Badge variant="secondary" className="gap-1">
                  Search: {searchQuery}
                  <X
                    className="h-3 w-3 cursor-pointer"
                    onClick={() => setSearchQuery('')}
                  />
                </Badge>
              )}
              {selectedCategory !== 'All' && (
                <Badge variant="secondary" className="gap-1">
                  Category: {selectedCategory}
                  <X
                    className="h-3 w-3 cursor-pointer"
                    onClick={() => setSelectedCategory('All')}
                  />
                </Badge>
              )}
              {stockFilter !== 'All' && (
                <Badge variant="secondary" className="gap-1">
                  Status: {stockFilter}
                  <X
                    className="h-3 w-3 cursor-pointer"
                    onClick={() => setStockFilter('All')}
                  />
                </Badge>
              )}
              <button
                onClick={() => {
                  setSearchQuery('');
                  setSelectedCategory('All');
                  setStockFilter('All');
                }}
                className="text-sm text-orange-600 hover:text-orange-700 ml-auto"
              >
                Clear all
              </button>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Inventory Table */}
      <Card>
        <CardContent className="p-0">
          {loading ? (
            <div className="text-center py-12 text-gray-500">Loading inventory...</div>
          ) : filteredInventory.length === 0 ? (
            <div className="text-center py-16 px-4">
              <Upload className="h-16 w-16 text-gray-300 mx-auto mb-4" />
              {inventory.length === 0 ? (
                <>
                  <h3 className="text-lg font-semibold text-gray-900 mb-2">No inventory items yet</h3>
                  <p className="text-gray-600 mb-6 max-w-md mx-auto">
                    Upload your ingredient list from Excel to get started in minutes, or add items individually.
                  </p>
                  <div className="flex gap-3 justify-center">
                    <Button
                      onClick={() => setShowExcelImport(true)}
                      className="bg-orange-500 hover:bg-orange-600 text-white"
                    >
                      <Upload className="h-4 w-4 mr-2" />
                      Import from Excel
                    </Button>
                    <Button
                      onClick={() => setShowAddModal(true)}
                      variant="outline"
                    >
                      <Plus className="h-4 w-4 mr-2" />
                      Add Item
                    </Button>
                  </div>
                </>
              ) : (
                <>
                  <Package className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                  <h3 className="text-lg font-semibold text-gray-900 mb-2">No items match your filters</h3>
                  <p className="text-gray-600 mb-4">Try adjusting your search or filters to find what you're looking for.</p>
                  <Button
                    onClick={() => {
                      setSearchQuery('');
                      setSelectedCategory('All');
                      setStockFilter('All');
                    }}
                    variant="outline"
                  >
                    Clear all filters
                  </Button>
                </>
              )}
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50 border-b">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Ingredient
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Category
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Stock Level
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Reorder At
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Supplier
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Cost/Unit
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {filteredInventory.map((item) => (
                    <tr
                      key={item.id}
                      className={`cursor-pointer transition-colors ${
                        item.status === 'out' 
                          ? 'bg-red-50/50 hover:bg-red-50' 
                          : item.status === 'low'
                          ? 'bg-amber-50/30 hover:bg-amber-50/50'
                          : 'hover:bg-gray-50'
                      }`}
                      onClick={() => {
                        setSelectedIngredient(item);
                        setShowDetailModal(true);
                      }}
                    >
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center gap-3">
                          <ChefHat className="h-5 w-5 text-gray-400 flex-shrink-0" />
                          <div>
                            <div className="font-medium text-gray-900">{item.name}</div>
                            {item.criticalImpact && (
                              <div className="flex items-center gap-1 mt-0.5">
                                <AlertCircle className="h-3 w-3 text-orange-500" />
                                <span className="text-xs text-orange-600">Critical</span>
                              </div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="text-sm text-gray-600">{item.category}</span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm">
                          <div className="font-semibold text-gray-900">
                            {item.currentStock} {item.unit}
                          </div>
                          {item.status !== 'ok' && (
                            <div className="text-xs text-gray-500 mt-0.5">
                              {item.status === 'out' ? 'Out of stock' : `${item.reorderLevel - item.currentStock} below reorder`}
                            </div>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="text-sm text-gray-600">
                          {item.reorderLevel} {item.unit}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="text-sm text-gray-600">{item.supplier || '-'}</span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="text-sm font-medium text-gray-900">
                          €{item.costPerUnit.toFixed(2)}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {item.status === 'ok' ? (
                          <Badge className="bg-green-600 hover:bg-green-600 text-white border-0 font-medium">
                            In Stock
                          </Badge>
                        ) : item.status === 'low' ? (
                          <Badge className="bg-amber-500 hover:bg-amber-500 text-white border-0 font-medium">
                            Low Stock
                          </Badge>
                        ) : (
                          <Badge className="bg-red-600 hover:bg-red-600 text-white border-0 font-medium">
                            Out of Stock
                          </Badge>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
                          <Button
                            size="sm"
                            variant="ghost"
                            className="hover:bg-white/50"
                            onClick={() => {
                              setSelectedIngredient(item);
                              setShowDetailModal(true);
                            }}
                          >
                            <Edit2 className="h-4 w-4" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Results Count */}
      <div className="text-sm text-gray-600 text-center">
        Showing {filteredInventory.length} of {inventory.length} items
      </div>

      {/* Modals */}
      {showExcelImport && (
        <ExcelImportModal
          onClose={() => setShowExcelImport(false)}
          onImport={handleExcelImport}
        />
      )}

      {showAddModal && (
        <AddIngredientModal
          onClose={() => setShowAddModal(false)}
          onAdd={handleAddIngredient}
        />
      )}

      {showDetailModal && selectedIngredient && (
        <IngredientDetailModal
          ingredient={selectedIngredient}
          onClose={() => {
            setShowDetailModal(false);
            setSelectedIngredient(null);
          }}
          onUpdate={(data) => handleUpdateIngredient(selectedIngredient.id, data)}
          onDelete={() => handleDeleteIngredient(selectedIngredient.id)}
        />
      )}
    </div>
  );
}