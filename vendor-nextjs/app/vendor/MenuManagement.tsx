'use client';

import { useState, useEffect, useCallback } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { 
  Plus, 
  Edit2, 
  Trash2, 
  Search, 
  Filter,
  Star,
  DollarSign,
  UtensilsCrossed,
  Percent,
  Upload,
  X,
  Sparkles,
  Info,
  ChevronDown,
  ChevronUp,
  ToggleLeft,
  ToggleRight,
  Download,
  FileSpreadsheet,
} from 'lucide-react';
import { api } from '@/lib/api';
import type { MenuCategory, MenuItem, PaidAddon, MenuItemIngredient, InventoryItem, Allergen, SpecialTag } from '@/lib/types';
import { toast } from 'sonner';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { ImageWithFallback } from '@/components/figma/ImageWithFallback';
import { MenuTranslationsEditor } from './MenuTranslationsEditor';
import { AIMenuAssistant } from './AIMenuAssistant';
import { RecipeIngredientsSection } from './RecipeIngredientsSection';
import { getTaxCategoryOption, formatVATDisplay, TAX_CATEGORY_OPTIONS, type TaxCategory, type Country } from '@/lib/taxRules';
import { calculateDishNutrition, type IngredientWithNutrition } from '@/lib/nutritionDatabase';

// ----------------------------------------------------------------
// Default image for items without a photo
// ----------------------------------------------------------------
const DEFAULT_IMAGE = 'https://images.unsplash.com/photo-1600891964599-f61ba0e24092?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxyZXN0YXVyYW50JTIwZm9vZHxlbnwxfHx8fDE3NjM4NTE5MzN8MA&ixlib=rb-4.1.0&q=80&w=1080';

// ----------------------------------------------------------------
// Form state type
// ----------------------------------------------------------------
interface ItemFormData {
  name: string;
  categoryId: number | '';
  price: number;
  description: string;
  imageUrl: string;
  available: boolean;
  calories: number;
  fat: number;
  carbs: number;
  protein: number;
  taxCategory: TaxCategory;
  dietaryPreference: string;
  allergies: string[];
  specialTags: string[];
  discountPercent: number;
  hasDiscount: boolean;
  paidAddons: PaidAddon[];
  freeAddons: string[];
  removableItems: string[];
  translations: Record<string, Record<string, string>>;
  ingredients: MenuItemIngredient[];
}

const EMPTY_FORM: ItemFormData = {
  name: '',
  categoryId: '',
  price: 0,
  description: '',
  imageUrl: '',
  available: true,
  calories: 0,
  fat: 0,
  carbs: 0,
  protein: 0,
  taxCategory: 'food',
  dietaryPreference: 'none',
  allergies: [],
  specialTags: [],
  discountPercent: 0,
  hasDiscount: false,
  paidAddons: [],
  freeAddons: [],
  removableItems: [],
  translations: {},
  ingredients: [],
};



// ----------------------------------------------------------------
// Props
// ----------------------------------------------------------------
interface MenuManagementProps {
  vendorId: string;
}

export function MenuManagement({ vendorId }: MenuManagementProps) {
  // Data
  const [categories, setCategories] = useState<MenuCategory[]>([]);
  const [items, setItems] = useState<MenuItem[]>([]);
  const [availableIngredients, setAvailableIngredients] = useState<InventoryItem[]>([]);
  const [allergens, setAllergens] = useState<Allergen[]>([]);
  const [specialTags, setSpecialTags] = useState<SpecialTag[]>([]);
  const [loading, setLoading] = useState(true);

  // Filters
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');

  // Dialogs
  const [editingItem, setEditingItem] = useState<MenuItem | null>(null);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [isAddDialogOpen, setIsAddDialogOpen] = useState(false);
  const [isAddCategoryDialogOpen, setIsAddCategoryDialogOpen] = useState(false);
  const [isAIAssistantOpen, setIsAIAssistantOpen] = useState(false);
  const [isUploadDialogOpen, setIsUploadDialogOpen] = useState(false);
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [saving, setSaving] = useState(false);

  // Category form
  const [newCategoryName, setNewCategoryName] = useState('');
  const [newCategoryTaxCategory, setNewCategoryTaxCategory] = useState<TaxCategory>('food');

  // Item form
  const [formData, setFormData] = useState<ItemFormData>({ ...EMPTY_FORM });

  // Nutrition UI
  const [isManualNutritionOverride, setIsManualNutritionOverride] = useState(false);
  const [showNutritionBreakdown, setShowNutritionBreakdown] = useState(false);

  const vendorCountry: Country = 'AT'; // from vendor settings

  // ================================================================
  // Data loading
  // ================================================================
  const loadCategories = useCallback(async () => {
    try {
      const res = await api.getCategories() as { data: MenuCategory[] };
      setCategories(res.data);
    } catch (err) {
      console.error('Failed to load categories:', err);
      toast.error('Failed to load categories');
    }
  }, []);

  const loadItems = useCallback(async () => {
    try {
      const res = await api.getMenuItems() as { data: MenuItem[] };
      setItems(res.data);
    } catch (err) {
      console.error('Failed to load menu items:', err);
      toast.error('Failed to load menu items');
    }
  }, []);

  const loadInventory = useCallback(async () => {
    try {
      const data = await api.getInventory(vendorId) as InventoryItem[];
      setAvailableIngredients(data);
    } catch (err) {
      console.error('Failed to load inventory:', err);
    }
  }, [vendorId]);

  const loadAllergens = useCallback(async () => {
    try {
      const res = await api.getAllergens() as { data: Allergen[] };
      setAllergens(res.data);
    } catch (err) {
      console.error('Failed to load allergens:', err);
    }
  }, []);

  const loadSpecialTags = useCallback(async () => {
    try {
      const res = await api.getSpecialTags() as { data: SpecialTag[] };
      setSpecialTags(res.data);
    } catch (err) {
      console.error('Failed to load special tags:', err);
    }
  }, []);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      await Promise.all([loadCategories(), loadItems(), loadInventory(), loadAllergens(), loadSpecialTags()]);
      setLoading(false);
    };
    load();
  }, [loadCategories, loadItems, loadInventory, loadAllergens, loadSpecialTags]);

  // ================================================================
  // Filtered items
  // ================================================================
  const filteredItems = items.filter((item) => {
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      if (!item.name.toLowerCase().includes(q) && !(item.description || '').toLowerCase().includes(q)) {
        return false;
      }
    }
    if (selectedCategory !== 'all' && String(item.categoryId) !== selectedCategory) {
      return false;
    }
    return true;
  });

  // ================================================================
  // Nutrition from ingredients
  // ================================================================
  const getCalculatedNutrition = () => {
    if (formData.ingredients.length === 0) {
      return { calories: 0, fat: 0, carbs: 0, protein: 0 };
    }
    const ingredientsWithNutrition: IngredientWithNutrition[] = formData.ingredients
      .map((ing) => {
        const full = availableIngredients.find((ai) => String(ai.id) === ing.ingredientId);
        if (!full?.nutrition) return null;
        return {
          ingredientId: ing.ingredientId,
          ingredientName: ing.ingredientName,
          quantity: ing.quantity,
          unit: ing.unit as 'g' | 'ml' | 'piece',
          nutrition: full.nutrition as { calories: number; fat: number; carbs: number; protein: number },
        };
      })
      .filter((x): x is IngredientWithNutrition => x !== null);
    return calculateDishNutrition(ingredientsWithNutrition);
  };

  // ================================================================
  // Category CRUD
  // ================================================================
  const handleAddCategory = async () => {
    if (!newCategoryName.trim()) {
      toast.error('Please enter a category name');
      return;
    }
    try {
      const res = await api.createCategory({
        name: newCategoryName,
        defaultTaxCategory: newCategoryTaxCategory,
      }) as { data: MenuCategory };
      setCategories((prev) => [...prev, res.data]);
      toast.success(`Category "${res.data.name}" created`);
      setNewCategoryName('');
      setNewCategoryTaxCategory('food');
      setIsAddCategoryDialogOpen(false);
    } catch (err: unknown) {
      const e = err as { data?: { message?: string } };
      toast.error(e.data?.message || 'Failed to create category');
    }
  };

  const handleDeleteCategory = async (catId: number) => {
    try {
      await api.deleteCategory(catId);
      setCategories((prev) => prev.filter((c) => c.id !== catId));
      toast.success('Category deleted');
    } catch (err: unknown) {
      const e = err as { data?: { message?: string } };
      toast.error(e.data?.message || 'Failed to delete category');
    }
  };

  // ================================================================
  // Item CRUD
  // ================================================================
  const resetForm = (defaults?: Partial<ItemFormData>) => {
    setFormData({ ...EMPTY_FORM, categoryId: categories[0]?.id ?? '', ...defaults });
    setIsManualNutritionOverride(false);
    setShowNutritionBreakdown(false);
  };

  const handleOpenAddDialog = () => {
    resetForm();
    setIsAddDialogOpen(true);
  };

  const handleEditItem = (item: MenuItem) => {
    setEditingItem(item);
    setFormData({
      name: item.name,
      categoryId: item.categoryId,
      price: item.price,
      description: item.description || '',
      imageUrl: item.imageUrl || '',
      available: item.available,
      calories: item.calories,
      fat: item.fat,
      carbs: item.carbs,
      protein: item.protein,
      taxCategory: (item.taxCategory || 'food') as TaxCategory,
      dietaryPreference: item.dietaryPreference || 'none',
      allergies: item.allergies || [],
      specialTags: item.specialTags || [],
      discountPercent: item.discountPercent || 0,
      hasDiscount: item.hasDiscount || false,
      paidAddons: item.paidAddons || [],
      freeAddons: item.freeAddons || [],
      removableItems: item.removableItems || [],
      translations: item.translations || {},
      ingredients: item.ingredients || [],
    });
    setIsEditDialogOpen(true);
  };

  const buildPayload = (): Record<string, unknown> => {
    const nutrition = isManualNutritionOverride
      ? { calories: formData.calories, fat: formData.fat, carbs: formData.carbs, protein: formData.protein }
      : getCalculatedNutrition();

    return {
      categoryId: formData.categoryId,
      name: formData.name,
      description: formData.description || null,
      price: formData.price,
      imageUrl: formData.imageUrl || null,
      available: formData.available,
      calories: nutrition.calories,
      fat: nutrition.fat,
      carbs: nutrition.carbs,
      protein: nutrition.protein,
      taxCategory: formData.taxCategory,
      dietaryPreference: formData.dietaryPreference === 'none' ? null : formData.dietaryPreference,
      allergies: formData.allergies,
      specialTags: formData.specialTags,
      hasDiscount: formData.hasDiscount,
      discountPercent: formData.discountPercent,
      paidAddons: formData.paidAddons.filter((a) => a.name.trim()),
      freeAddons: formData.freeAddons.filter((a) => a.trim()),
      removableItems: formData.removableItems.filter((a) => a.trim()),
      translations: formData.translations,
      ingredients: formData.ingredients,
    };
  };

  const handleAddItem = async () => {
    if (!formData.name || !formData.categoryId || formData.price <= 0) {
      toast.error('Please fill in all required fields (name, category, price)');
      return;
    }
    setSaving(true);
    try {
      const res = await api.createMenuItem(buildPayload()) as { data: MenuItem };
      setItems((prev) => [...prev, res.data]);
      // Update category item counts
      setCategories((prev) =>
        prev.map((c) => c.id === res.data.categoryId ? { ...c, itemCount: c.itemCount + 1 } : c)
      );
      toast.success(`"${res.data.name}" added to menu`);
      setIsAddDialogOpen(false);
    } catch (err: unknown) {
      const e = err as { data?: { message?: string; errors?: Record<string, string[]> } };
      if (e.data?.errors) {
        const first = Object.values(e.data.errors).flat()[0];
        toast.error(first || 'Validation error');
      } else {
        toast.error(e.data?.message || 'Failed to add item');
      }
    } finally {
      setSaving(false);
    }
  };

  const handleSaveItem = async () => {
    if (!editingItem) return;
    if (!formData.name || !formData.categoryId || formData.price <= 0) {
      toast.error('Please fill in all required fields');
      return;
    }
    setSaving(true);
    try {
      const res = await api.updateMenuItem(editingItem.id, buildPayload()) as { data: MenuItem };
      setItems((prev) => prev.map((i) => (i.id === editingItem.id ? res.data : i)));
      toast.success(`"${res.data.name}" updated`);
      setIsEditDialogOpen(false);
      setEditingItem(null);
    } catch (err: unknown) {
      const e = err as { data?: { message?: string; errors?: Record<string, string[]> } };
      if (e.data?.errors) {
        toast.error(Object.values(e.data.errors).flat()[0] || 'Validation error');
      } else {
        toast.error(e.data?.message || 'Failed to update item');
      }
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteItem = async (item: MenuItem) => {
    try {
      await api.deleteMenuItem(item.id);
      setItems((prev) => prev.filter((i) => i.id !== item.id));
      setCategories((prev) =>
        prev.map((c) => c.id === item.categoryId ? { ...c, itemCount: Math.max(0, c.itemCount - 1) } : c)
      );
      toast.success(`"${item.name}" deleted`);
    } catch (err: unknown) {
      const e = err as { data?: { message?: string } };
      toast.error(e.data?.message || 'Failed to delete item');
    }
  };

  const handleToggleAvailability = async (item: MenuItem) => {
    // Optimistic update
    const prev = item.available;
    setItems((list) => list.map((i) => (i.id === item.id ? { ...i, available: !prev } : i)));
    toast.success(prev ? 'Item marked as sold out' : 'Item marked as available');
    try {
      await api.toggleMenuItemAvailability(item.id);
    } catch {
      // Revert
      setItems((list) => list.map((i) => (i.id === item.id ? { ...i, available: prev } : i)));
      toast.error('Failed to update availability');
    }
  };

  // ================================================================
  // Addon handlers
  // ================================================================
  const handleAddPaidAddon = () => setFormData({ ...formData, paidAddons: [...formData.paidAddons, { name: '', price: 0 }] });
  const handleRemovePaidAddon = (idx: number) => setFormData({ ...formData, paidAddons: formData.paidAddons.filter((_, i) => i !== idx) });
  const handleUpdatePaidAddon = (idx: number, field: 'name' | 'price', value: string | number) => {
    const updated = [...formData.paidAddons];
    updated[idx] = { ...updated[idx], [field]: value };
    setFormData({ ...formData, paidAddons: updated });
  };
  const handleAddFreeAddon = () => setFormData({ ...formData, freeAddons: [...formData.freeAddons, ''] });
  const handleRemoveFreeAddon = (idx: number) => setFormData({ ...formData, freeAddons: formData.freeAddons.filter((_, i) => i !== idx) });
  const handleUpdateFreeAddon = (idx: number, value: string) => {
    const updated = [...formData.freeAddons];
    updated[idx] = value;
    setFormData({ ...formData, freeAddons: updated });
  };
  const handleAddRemovableItem = () => setFormData({ ...formData, removableItems: [...formData.removableItems, ''] });
  const handleRemoveRemovableItem = (idx: number) => setFormData({ ...formData, removableItems: formData.removableItems.filter((_, i) => i !== idx) });
  const handleUpdateRemovableItem = (idx: number, value: string) => {
    const updated = [...formData.removableItems];
    updated[idx] = value;
    setFormData({ ...formData, removableItems: updated });
  };

  // Ingredient handlers
  const handleAddIngredient = (ingredient: MenuItemIngredient) => {
    setFormData({ ...formData, ingredients: [...formData.ingredients, ingredient] });
  };
  const handleRemoveIngredient = (idx: number) => {
    setFormData({ ...formData, ingredients: formData.ingredients.filter((_, i) => i !== idx) });
    toast.success('Ingredient removed');
  };

  // Image upload
  const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) { toast.error('Please select an image file'); return; }
    if (file.size > 5 * 1024 * 1024) { toast.error('Image must be under 5MB'); return; }
    const reader = new FileReader();
    reader.onloadend = () => setFormData({ ...formData, imageUrl: reader.result as string });
    reader.readAsDataURL(file);
  };

  // CSV template download
  const downloadTemplate = () => {
    const template = [
      ['Category', 'Item Name', 'Description', 'Price (€)', 'Calories', 'Fat (g)', 'Carbs (g)', 'Protein (g)', 'Dietary Preference', 'Allergies (comma-separated)', 'Special Tags (comma-separated)', 'Discount %', 'Image URL'],
      ['Appetizers', 'Bruschetta', 'Toasted bread with tomatoes', '7.50', '180', '8', '22', '4', 'Vegetarian', 'Gluten', 'chefs-pick', '0', ''],
    ];
    const csv = template.map((row) => row.map((c) => `"${c}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'menu_template.csv';
    a.click();
    URL.revokeObjectURL(url);
  };

  // CSV upload (placeholder — Coming Soon)
  const handleFileUpload = async () => {
    toast.info('Bulk upload is coming soon');
  };

  // Helper: get category default tax
  const getDefaultTaxCategoryForCategory = (catId: number | ''): TaxCategory | undefined => {
    if (!catId) return undefined;
    return categories.find((c) => c.id === Number(catId))?.defaultTaxCategory as TaxCategory | undefined;
  };

  // ================================================================
  // Computed stats
  // ================================================================
  const avgPrice = items.length ? items.reduce((s, i) => s + i.price, 0) / items.length : 0;
  const avgRating = items.length ? items.reduce((s, i) => s + (i.rating || 0), 0) / items.length : 0;

  // ================================================================
  // Shared item form JSX (used by both add & edit dialogs)
  // ================================================================
  const renderItemForm = () => (
    <div className="space-y-6 py-4">
      {/* Basic Information */}
      <div className="space-y-4">
        <h3 className="font-semibold text-neutral-900">Basic Information</h3>
        <div className="grid grid-cols-2 gap-4">
          <div className="col-span-2">
            <Label htmlFor="item-name">Item Name *</Label>
            <Input
              id="item-name"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              className="mt-1.5"
            />
          </div>

          <div>
            <Label>Category *</Label>
            <Select
              value={formData.categoryId ? String(formData.categoryId) : ''}
              onValueChange={(val) => setFormData({ ...formData, categoryId: Number(val) })}
            >
              <SelectTrigger className="mt-1.5"><SelectValue placeholder="Select category" /></SelectTrigger>
              <SelectContent>
                {categories.map((cat) => (
                  <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div>
            <Label htmlFor="item-price">Price (€) *</Label>
            <Input
              id="item-price"
              type="number"
              step="0.01"
              value={formData.price || ''}
              onChange={(e) => setFormData({ ...formData, price: parseFloat(e.target.value) || 0 })}
              className="mt-1.5"
            />
          </div>

          <div>
            <Label>Availability</Label>
            <div className="mt-2 flex items-center gap-3">
              <label className="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  checked={formData.available}
                  onChange={(e) => setFormData({ ...formData, available: e.target.checked })}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600" />
              </label>
              <span className="text-sm text-gray-700">{formData.available ? 'Available' : 'Sold Out'}</span>
            </div>
          </div>

          <div className="col-span-2">
            <Label htmlFor="item-desc">Description</Label>
            <Textarea
              id="item-desc"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              rows={3}
              className="mt-1.5"
            />
          </div>

          <div className="col-span-2">
            <Label>Dish Image</Label>
            {!formData.imageUrl ? (
              <div className="mt-1.5">
                <label className="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-neutral-300 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors">
                  <Upload className="w-10 h-10 text-neutral-400 mb-3" />
                  <p className="mb-2 text-sm text-neutral-600"><span className="font-semibold">Click to upload</span> or drag and drop</p>
                  <p className="text-xs text-neutral-500">PNG, JPG, GIF up to 5MB</p>
                  <input type="file" className="hidden" accept="image/*" onChange={handleImageUpload} />
                </label>
              </div>
            ) : (
              <div className="mt-1.5 relative w-full h-40 bg-neutral-100 rounded-md overflow-hidden group">
                <ImageWithFallback src={formData.imageUrl} alt="Preview" className="w-full h-full object-cover" />
                <div className="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition-all flex items-center justify-center">
                  <Button type="button" variant="destructive" size="sm" onClick={() => setFormData({ ...formData, imageUrl: '' })} className="opacity-0 group-hover:opacity-100 transition-opacity">
                    <X className="w-4 h-4 mr-1" /> Remove Image
                  </Button>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Nutrition */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <h3 className="font-semibold text-neutral-900">
            Nutrition {isManualNutritionOverride ? '(Manual Override)' : '(Auto-Calculated)'}
          </h3>
          <Badge variant={isManualNutritionOverride ? 'destructive' : 'default'} className="text-xs">
            {isManualNutritionOverride ? 'Manual' : 'Auto-calculated'}
          </Badge>
        </div>

        {isManualNutritionOverride && (
          <div className="p-3 bg-yellow-100 border border-yellow-300 rounded-lg flex items-start gap-2">
            <Info className="w-4 h-4 text-yellow-700 flex-shrink-0 mt-0.5" />
            <p className="text-xs text-yellow-900"><strong>Manual override active.</strong> Ingredient changes will not update nutrition automatically.</p>
          </div>
        )}

        <div className="grid grid-cols-4 gap-4">
          {(['calories', 'fat', 'carbs', 'protein'] as const).map((field) => (
            <div key={field}>
              <Label>{field === 'calories' ? 'Calories (kcal)' : `${field.charAt(0).toUpperCase() + field.slice(1)} (g)`}</Label>
              {isManualNutritionOverride ? (
                <Input
                  type="number"
                  step={field === 'calories' ? '1' : '0.1'}
                  value={formData[field]}
                  onChange={(e) => setFormData({ ...formData, [field]: parseFloat(e.target.value) || 0 })}
                  className="mt-1.5"
                />
              ) : (
                <div className="mt-1.5 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-900 font-medium">
                  {getCalculatedNutrition()[field]}
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Nutrition breakdown */}
        {!isManualNutritionOverride && formData.ingredients.length > 0 && (
          <div>
            <button type="button" onClick={() => setShowNutritionBreakdown(!showNutritionBreakdown)} className="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-700 font-medium">
              {showNutritionBreakdown ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
              View nutrition breakdown by ingredient
            </button>
            {showNutritionBreakdown && (
              <div className="mt-3 space-y-2">
                {formData.ingredients.map((ingredient, index) => {
                  const full = availableIngredients.find((ai) => String(ai.id) === ingredient.ingredientId);
                  if (!full?.nutrition) return null;
                  let multiplier = 1;
                  if (ingredient.unit === 'g' || ingredient.unit === 'ml') multiplier = ingredient.quantity / 100;
                  else if (ingredient.unit === 'pieces' || ingredient.unit === 'piece') multiplier = ingredient.quantity;
                  return (
                    <div key={index} className="p-3 bg-white border border-gray-200 rounded-lg">
                      <div className="font-medium text-sm text-gray-900">{ingredient.ingredientName} <span className="text-xs text-gray-500">({ingredient.quantity}{ingredient.unit})</span></div>
                      <div className="grid grid-cols-4 gap-2 text-xs mt-1">
                        <div>Cal: <span className="font-medium">{Math.round(full.nutrition.calories * multiplier)}</span></div>
                        <div>Fat: <span className="font-medium">{(full.nutrition.fat * multiplier).toFixed(1)}g</span></div>
                        <div>Protein: <span className="font-medium">{(full.nutrition.protein * multiplier).toFixed(1)}g</span></div>
                        <div>Carbs: <span className="font-medium">{(full.nutrition.carbs * multiplier).toFixed(1)}g</span></div>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        )}

        <div className="pt-3 border-t border-gray-200">
          <button type="button" onClick={() => setIsManualNutritionOverride(!isManualNutritionOverride)} className="flex items-center justify-between w-full p-3 hover:bg-gray-50 rounded-lg transition-colors group">
            <div className="flex items-center gap-2">
              {isManualNutritionOverride ? <ToggleRight className="w-5 h-5 text-yellow-600" /> : <ToggleLeft className="w-5 h-5 text-gray-400 group-hover:text-gray-600" />}
              <span className="text-sm font-medium text-gray-900">Override nutrition manually</span>
            </div>
            <span className="text-xs text-gray-500">{isManualNutritionOverride ? 'Enabled' : 'Disabled'}</span>
          </button>
        </div>

        <div className="flex items-start gap-2 p-2 bg-blue-50 rounded text-xs text-blue-900">
          <Info className="w-3 h-3 flex-shrink-0 mt-0.5" />
          <p>Nutrition is calculated from standard ingredient data per 100g. Values are estimates.</p>
        </div>
      </div>

      {/* Dietary Information */}
      <div className="space-y-4">
        <h3 className="font-semibold text-neutral-900">Dietary Information</h3>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <Label>Dietary Preference</Label>
            <Select value={formData.dietaryPreference} onValueChange={(val) => setFormData({ ...formData, dietaryPreference: val })}>
              <SelectTrigger className="mt-1.5"><SelectValue placeholder="Select" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="none">None</SelectItem>
                <SelectItem value="vegetarian">Vegetarian</SelectItem>
                <SelectItem value="vegan">Vegan</SelectItem>
                <SelectItem value="pescetarian">Pescetarian</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label>Common Allergens</Label>
            <Select onValueChange={(val) => { if (!formData.allergies.includes(val)) setFormData({ ...formData, allergies: [...formData.allergies, val] }); }}>
              <SelectTrigger className="mt-1.5"><SelectValue placeholder="Add allergen" /></SelectTrigger>
              <SelectContent>
                {allergens.map((a) => <SelectItem key={a.id} value={a.name}>{a.icon ? `${a.icon} ` : ''}{a.name}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          {formData.allergies.length > 0 && (
            <div className="col-span-2">
              <Label>Selected Allergens</Label>
              <div className="flex flex-wrap gap-2 mt-2">
                {formData.allergies.map((a) => (
                  <Badge key={a} variant="secondary" className="cursor-pointer hover:bg-red-100" onClick={() => setFormData({ ...formData, allergies: formData.allergies.filter((x) => x !== a) })}>
                    {a} ×
                  </Badge>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Special Tags */}
      <div className="space-y-4">
        <h3 className="font-semibold text-neutral-900">Special Tags</h3>
        <div className="flex flex-wrap gap-2">
          {specialTags.map((tag) => {
            const sel = formData.specialTags.includes(tag.slug);
            return (
              <Button key={tag.id} type="button" variant={sel ? 'default' : 'outline'} size="sm"
                onClick={() => setFormData({ ...formData, specialTags: sel ? formData.specialTags.filter((t) => t !== tag.slug) : [...formData.specialTags, tag.slug] })}
                className={sel ? 'bg-blue-600 hover:bg-blue-700' : ''}
              >
                {tag.icon ? `${tag.icon} ` : ''}{tag.label}
              </Button>
            );
          })}
        </div>
      </div>

      {/* Discount */}
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <h3 className="font-semibold text-neutral-900">Special Discount</h3>
          <Button type="button" variant="outline" size="sm" onClick={() => setFormData({ ...formData, hasDiscount: !formData.hasDiscount })}>
            {formData.hasDiscount ? 'Remove Discount' : 'Add Discount'}
          </Button>
        </div>
        {formData.hasDiscount && (
          <div className="grid grid-cols-3 gap-4">
            <div>
              <Label>Discount %</Label>
              <div className="relative mt-1.5">
                <Input type="number" step="1" min="0" max="100" value={formData.discountPercent} onChange={(e) => setFormData({ ...formData, discountPercent: parseFloat(e.target.value) || 0 })} className="pr-8" />
                <Percent className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" />
              </div>
            </div>
            <div>
              <Label>Original Price</Label>
              <div className="mt-1.5 p-2 bg-neutral-50 rounded-md border"><p className="text-sm font-medium">€{formData.price.toFixed(2)}</p></div>
            </div>
            <div>
              <Label>Discounted Price</Label>
              <div className="mt-1.5 p-2 bg-green-50 rounded-md border border-green-200">
                <p className="text-sm font-semibold text-green-700">€{(formData.price * (1 - formData.discountPercent / 100)).toFixed(2)}</p>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Add-ons & Modifiers */}
      <div className="space-y-4 border-t pt-6">
        <h3 className="font-semibold text-neutral-900">Customization Options</h3>

        {/* Paid Add-ons */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <Label>Paid Add-ons (Extra Charge) — max 10</Label>
            <Button type="button" variant="outline" size="sm" onClick={handleAddPaidAddon} disabled={formData.paidAddons.length >= 10}>
              <Plus className="w-3 h-3 mr-1" /> Add
            </Button>
          </div>
          {formData.paidAddons.map((addon, i) => (
            <div key={i} className="flex gap-2">
              <Input placeholder="e.g., Extra Cheese" value={addon.name} onChange={(e) => handleUpdatePaidAddon(i, 'name', e.target.value)} className="flex-1" />
              <Input type="number" step="0.01" placeholder="€0.00" value={addon.price || ''} onChange={(e) => handleUpdatePaidAddon(i, 'price', parseFloat(e.target.value) || 0)} className="w-28" />
              <Button type="button" variant="ghost" size="sm" onClick={() => handleRemovePaidAddon(i)}><X className="w-4 h-4" /></Button>
            </div>
          ))}
          {formData.paidAddons.length === 0 && <p className="text-sm text-neutral-500 italic">No paid add-ons yet</p>}
        </div>

        {/* Free Add-ons */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <Label>Free Add-ons (No Charge) — max 10</Label>
            <Button type="button" variant="outline" size="sm" onClick={handleAddFreeAddon} disabled={formData.freeAddons.length >= 10}>
              <Plus className="w-3 h-3 mr-1" /> Add
            </Button>
          </div>
          {formData.freeAddons.map((addon, i) => (
            <div key={i} className="flex gap-2">
              <Input placeholder="e.g., Extra Ketchup" value={addon} onChange={(e) => handleUpdateFreeAddon(i, e.target.value)} className="flex-1" />
              <Button type="button" variant="ghost" size="sm" onClick={() => handleRemoveFreeAddon(i)}><X className="w-4 h-4" /></Button>
            </div>
          ))}
          {formData.freeAddons.length === 0 && <p className="text-sm text-neutral-500 italic">No free add-ons yet</p>}
        </div>

        {/* Removable Items */}
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <Label>Removable Items — max 10</Label>
            <Button type="button" variant="outline" size="sm" onClick={handleAddRemovableItem} disabled={formData.removableItems.length >= 10}>
              <Plus className="w-3 h-3 mr-1" /> Add
            </Button>
          </div>
          {formData.removableItems.map((item, i) => (
            <div key={i} className="flex gap-2">
              <Input placeholder="e.g., Onions" value={item} onChange={(e) => handleUpdateRemovableItem(i, e.target.value)} className="flex-1" />
              <Button type="button" variant="ghost" size="sm" onClick={() => handleRemoveRemovableItem(i)}><X className="w-4 h-4" /></Button>
            </div>
          ))}
          {formData.removableItems.length === 0 && <p className="text-sm text-neutral-500 italic">No removable items yet</p>}
        </div>
      </div>

      {/* Recipe & Ingredients */}
      <RecipeIngredientsSection
        ingredients={formData.ingredients}
        availableIngredients={availableIngredients}
        onAddIngredient={handleAddIngredient}
        onRemoveIngredient={handleRemoveIngredient}
      />

      {/* Tax Information */}
      <div className="space-y-4">
        <h3 className="font-semibold text-neutral-900">Tax Information</h3>
        <div>
          <Label>Tax Category</Label>
          <Select value={formData.taxCategory} onValueChange={(value) => setFormData({ ...formData, taxCategory: value as TaxCategory })}>
            <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
            <SelectContent>
              {TAX_CATEGORY_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  <div className="flex items-start gap-2">
                    <span className="text-lg">{opt.icon}</span>
                    <div className="flex-1">
                      <div className="font-medium">{opt.label}</div>
                      <div className="text-xs text-gray-500">{opt.description}</div>
                    </div>
                  </div>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <div className="mt-2 p-3 bg-gray-50 border rounded-lg">
            <div className="text-xs text-gray-600 mb-1">Applied VAT Rate</div>
            <div className="text-sm font-medium">{formatVATDisplay(vendorCountry, formData.taxCategory)}</div>
          </div>
          {formData.categoryId && formData.taxCategory !== getDefaultTaxCategoryForCategory(formData.categoryId) && (
            <div className="mt-2 flex items-start gap-2 text-xs text-amber-700 bg-amber-50 p-2 rounded">
              <span>⚠️</span>
              <span>Changing this may affect tax compliance. Most items should use the category default.</span>
            </div>
          )}
        </div>
      </div>

      {/* Multi-Language Translations (edit only) */}
      {editingItem && (
        <div className="border-t pt-6">
          <MenuTranslationsEditor
            item={{ ...editingItem, ...formData }}
            onChange={(translations: Record<string, Record<string, string>>) => setFormData({ ...formData, translations })}
          />
        </div>
      )}
    </div>
  );

  // ================================================================
  // Render
  // ================================================================
  return (
    <div className="p-6 space-y-6 max-w-[1400px] mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-semibold text-neutral-900">Menu Management</h2>
          <p className="text-neutral-600 mt-1">Manage your menu items, pricing, and availability</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" className="border-blue-600 text-blue-600 hover:bg-blue-50" onClick={() => setIsUploadDialogOpen(true)}>
            <FileSpreadsheet className="w-4 h-4 mr-2" /> Bulk Upload
          </Button>
          <Button variant="outline" className="border-purple-600 text-purple-600 hover:bg-purple-50" onClick={() => setIsAIAssistantOpen(true)}>
            <Sparkles className="w-4 h-4 mr-2" /> AI Menu Assistant
          </Button>
          <Button variant="outline" onClick={() => setIsAddCategoryDialogOpen(true)}>
            <Plus className="w-4 h-4 mr-2" /> Add Category
          </Button>
          <Button className="bg-blue-600 hover:bg-blue-700" onClick={handleOpenAddDialog}>
            <Plus className="w-4 h-4 mr-2" /> Add New Item
          </Button>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Total Items</p>
                <p className="text-2xl font-semibold mt-1">{items.length}</p>
              </div>
              <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center"><UtensilsCrossed className="w-6 h-6 text-blue-600" /></div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Categories</p>
                <p className="text-2xl font-semibold mt-1">{categories.length}</p>
              </div>
              <div className="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center"><Filter className="w-6 h-6 text-green-600" /></div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Avg. Price</p>
                <p className="text-2xl font-semibold mt-1">€{avgPrice.toFixed(2)}</p>
              </div>
              <div className="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center"><DollarSign className="w-6 h-6 text-orange-600" /></div>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-neutral-600">Avg. Rating</p>
                <p className="text-2xl font-semibold mt-1">
                  {avgRating.toFixed(1)}
                  <Star className="w-4 h-4 inline ml-1 text-yellow-500 fill-yellow-500" />
                </p>
              </div>
              <div className="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center"><Star className="w-6 h-6 text-yellow-600" /></div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-neutral-400" />
              <Input type="text" placeholder="Search menu items..." value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} className="pl-10" />
            </div>
            <Select value={selectedCategory} onValueChange={setSelectedCategory}>
              <SelectTrigger className="w-full md:w-[200px]"><SelectValue placeholder="All Categories" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Categories</SelectItem>
                {categories.map((cat) => (
                  <SelectItem key={cat.id} value={String(cat.id)}>{cat.name}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Menu Items */}
      {loading ? (
        <Card><CardContent className="py-12 text-center text-neutral-500">Loading menu...</CardContent></Card>
      ) : filteredItems.length === 0 ? (
        <Card><CardContent className="py-12 text-center text-neutral-500">No menu items found</CardContent></Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredItems.map((item) => (
            <Card key={item.id} className="overflow-hidden hover:shadow-lg transition-shadow">
              <div className="relative h-48">
                <ImageWithFallback src={item.imageUrl || DEFAULT_IMAGE} alt={item.name} className="w-full h-full object-cover" />
                {!item.available && (
                  <div className="absolute inset-0 bg-black/70 flex items-center justify-center backdrop-blur-sm">
                    <div className="bg-red-500 text-white px-4 py-2 rounded-lg font-semibold text-lg shadow-lg">SOLD OUT</div>
                  </div>
                )}
                <div className="absolute top-2 left-2 flex flex-wrap gap-1">
                  {item.dietaryPreference && item.dietaryPreference !== 'none' && (
                    <Badge className="text-xs bg-blue-600 text-white">{item.dietaryPreference.charAt(0).toUpperCase() + item.dietaryPreference.slice(1)}</Badge>
                  )}
                  {item.specialTags?.map((slug) => {
                    const tag = specialTags.find((t) => t.slug === slug);
                    return (
                      <Badge key={slug} className="text-xs bg-purple-600 text-white">{tag ? `${tag.icon || ''} ${tag.label}` : slug}</Badge>
                    );
                  })}
                </div>
              </div>

              <CardContent className="p-4">
                <div className="flex items-start justify-between mb-2">
                  <div className="flex-1">
                    <h3 className="font-semibold text-neutral-900">{item.name}</h3>
                    <p className="text-xs text-neutral-500 mt-1">{item.categoryName}</p>
                  </div>
                  <div className="text-right">
                    {item.hasDiscount && item.discountedPrice ? (
                      <div>
                        <p className="text-xs text-neutral-500 line-through">€{item.price.toFixed(2)}</p>
                        <p className="font-semibold text-green-600">€{item.discountedPrice.toFixed(2)}</p>
                        <Badge variant="secondary" className="text-xs mt-1">-{item.discountPercent}%</Badge>
                      </div>
                    ) : (
                      <p className="font-semibold text-neutral-900">€{item.price.toFixed(2)}</p>
                    )}
                    {item.rating > 0 && (
                      <div className="flex items-center gap-1 text-xs text-neutral-600 mt-1">
                        <Star className="w-3 h-3 fill-yellow-400 text-yellow-400" />
                        <span>{item.rating.toFixed(1)}</span>
                        <span className="text-neutral-400">({item.reviewCount})</span>
                      </div>
                    )}
                  </div>
                </div>

                <p className="text-sm text-neutral-600 line-clamp-2 mb-3">{item.description}</p>

                {/* Nutrition */}
                {(item.calories > 0 || item.fat > 0 || item.carbs > 0 || item.protein > 0) && (
                  <div className="grid grid-cols-4 gap-2 mb-3 p-2 bg-neutral-50 rounded-md">
                    {item.calories > 0 && <div className="text-center"><p className="text-xs text-neutral-500">Cal</p><p className="text-xs font-semibold">{item.calories}</p></div>}
                    {item.fat > 0 && <div className="text-center"><p className="text-xs text-neutral-500">Fat</p><p className="text-xs font-semibold">{item.fat}g</p></div>}
                    {item.carbs > 0 && <div className="text-center"><p className="text-xs text-neutral-500">Carbs</p><p className="text-xs font-semibold">{item.carbs}g</p></div>}
                    {item.protein > 0 && <div className="text-center"><p className="text-xs text-neutral-500">Protein</p><p className="text-xs font-semibold">{item.protein}g</p></div>}
                  </div>
                )}

                {/* Allergens */}
                {item.allergies?.length > 0 && (
                  <div className="mb-3">
                    <p className="text-xs text-neutral-500 mb-1">Allergens:</p>
                    <div className="flex flex-wrap gap-1">
                      {item.allergies.map((a) => <Badge key={a} variant="secondary" className="text-xs">{a}</Badge>)}
                    </div>
                  </div>
                )}

                {/* Customization indicator */}
                {((item.paidAddons?.length > 0) || (item.freeAddons?.length > 0) || (item.removableItems?.length > 0)) && (
                  <div className="mb-3 p-2 bg-blue-50 rounded-md border border-blue-100">
                    <p className="text-xs font-semibold text-blue-900 mb-1">🔧 Customizable</p>
                    <div className="text-xs text-blue-700 space-y-0.5">
                      {item.paidAddons?.length > 0 && <p>• {item.paidAddons.length} paid add-on(s)</p>}
                      {item.freeAddons?.length > 0 && <p>• {item.freeAddons.length} free add-on(s)</p>}
                      {item.removableItems?.length > 0 && <p>• {item.removableItems.length} removable item(s)</p>}
                    </div>
                  </div>
                )}

                <div className="flex items-center justify-between text-xs text-neutral-500 mb-3 pt-2 border-t">
                  <span>Ordered {item.orderedCount}x</span>
                  <span>VAT: {item.vatRate}%</span>
                </div>

                {/* Availability Toggle */}
                <div className="flex items-center justify-between p-2 bg-gray-50 rounded-md mb-3">
                  <div className="flex items-center gap-2">
                    <div className={`w-2 h-2 rounded-full ${item.available ? 'bg-green-500' : 'bg-red-500'}`} />
                    <span className="text-sm font-medium text-gray-700">{item.available ? 'Available' : 'Sold Out'}</span>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" checked={item.available} onChange={() => handleToggleAvailability(item)} className="sr-only peer" />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600" />
                  </label>
                </div>

                <div className="flex gap-2 pt-3 border-t">
                  <Button variant="outline" size="sm" className="flex-1" onClick={() => handleEditItem(item)}>
                    <Edit2 className="w-3 h-3 mr-1" /> Edit
                  </Button>
                  <Button variant="outline" size="sm" className="text-red-600 hover:text-red-700 hover:bg-red-50" onClick={() => handleDeleteItem(item)}>
                    <Trash2 className="w-3 h-3" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Edit Dialog */}
      <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Edit Menu Item</DialogTitle>
            <DialogDescription>Update the details of this menu item</DialogDescription>
          </DialogHeader>
          {editingItem && renderItemForm()}
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>Cancel</Button>
            <Button className="bg-blue-600 hover:bg-blue-700" onClick={handleSaveItem} disabled={saving}>
              {saving ? 'Saving...' : 'Save Changes'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Add Dialog */}
      <Dialog open={isAddDialogOpen} onOpenChange={setIsAddDialogOpen}>
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Add New Menu Item</DialogTitle>
            <DialogDescription>Add a new menu item to your restaurant</DialogDescription>
          </DialogHeader>
          {renderItemForm()}
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsAddDialogOpen(false)}>Cancel</Button>
            <Button className="bg-blue-600 hover:bg-blue-700" onClick={handleAddItem} disabled={saving}>
              {saving ? 'Adding...' : 'Add Item'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Add Category Dialog */}
      <Dialog open={isAddCategoryDialogOpen} onOpenChange={setIsAddCategoryDialogOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Add New Category</DialogTitle>
            <DialogDescription>Create a new category for your menu items</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div>
              <Label htmlFor="categoryName">Category Name</Label>
              <Input id="categoryName" value={newCategoryName} onChange={(e) => setNewCategoryName(e.target.value)} placeholder="e.g., Burgers, Pasta, Sides" className="mt-1.5" />
            </div>
            <div>
              <Label>Tax Category (Default)</Label>
              <Select value={newCategoryTaxCategory} onValueChange={(v) => setNewCategoryTaxCategory(v as TaxCategory)}>
                <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {TAX_CATEGORY_OPTIONS.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      <span className="flex items-center gap-2"><span>{opt.icon}</span><span>{opt.label}</span></span>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-xs text-gray-500 mt-1.5">
                All items in this category will inherit this tax classification. Tax categories determine VAT automatically based on your country.
              </p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setIsAddCategoryDialogOpen(false); setNewCategoryName(''); setNewCategoryTaxCategory('food'); }}>Cancel</Button>
            <Button className="bg-blue-600 hover:bg-blue-700" onClick={handleAddCategory}>Add Category</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* CSV Upload Dialog */}
      <Dialog open={isUploadDialogOpen} onOpenChange={setIsUploadDialogOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Bulk Menu Upload</DialogTitle>
            <DialogDescription>Upload a CSV or Excel file to quickly add multiple menu items at once</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
              <div className="flex items-start gap-3">
                <FileSpreadsheet className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                <div className="flex-1">
                  <h4 className="font-medium text-blue-900 mb-1">First time uploading?</h4>
                  <p className="text-sm text-blue-800 mb-3">Download our template file to see the correct format.</p>
                  <Button onClick={downloadTemplate} variant="outline" size="sm" className="bg-white border-blue-300 text-blue-700 hover:bg-blue-50">
                    <Download className="w-4 h-4 mr-2" /> Download CSV Template
                  </Button>
                </div>
              </div>
            </div>
            <div>
              <Label className="text-sm font-medium text-gray-700 mb-2 block">Upload your menu file</Label>
              <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-400 transition-colors">
                <input type="file" accept=".csv,.xlsx,.xls" onChange={(e) => setUploadFile(e.target.files?.[0] || null)} className="hidden" id="menu-upload" />
                <label htmlFor="menu-upload" className="cursor-pointer flex flex-col items-center">
                  <Upload className="w-10 h-10 text-gray-400 mb-2" />
                  <p className="text-sm font-medium text-gray-700 mb-1">Click to upload or drag and drop</p>
                  <p className="text-xs text-gray-500">CSV or Excel files only</p>
                </label>
              </div>
              {uploadFile && (
                <div className="mt-3 flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded">
                  <FileSpreadsheet className="w-4 h-4 text-green-600" />
                  <span className="text-sm text-green-800 flex-1">{uploadFile.name}</span>
                  <button onClick={() => setUploadFile(null)} className="text-green-600 hover:text-green-800"><X className="w-4 h-4" /></button>
                </div>
              )}
            </div>
            <div className="p-3 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800">
              <p className="font-medium mb-1">⚠️ Coming Soon</p>
              <p>Bulk upload is not yet available. Please add items individually for now.</p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setIsUploadDialogOpen(false); setUploadFile(null); }}>Cancel</Button>
            <Button onClick={handleFileUpload} disabled={!uploadFile || isUploading} className="bg-blue-600 hover:bg-blue-700">
              {isUploading ? 'Uploading...' : <><Upload className="w-4 h-4 mr-2" /> Upload Menu</>}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* AI Menu Assistant */}
      <AIMenuAssistant
        isOpen={isAIAssistantOpen}
        onClose={() => setIsAIAssistantOpen(false)}
        menuItems={items}
        onApplySuggestion={(suggestion: { title: string }) => {
          toast.success(`Suggestion noted: ${suggestion.title}`);
          setIsAIAssistantOpen(false);
        }}
      />
    </div>
  );
}
