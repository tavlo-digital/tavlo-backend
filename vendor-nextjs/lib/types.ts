export interface Vendor {
  id: number;
  name: string;
  country: string;
  phone: string;
  email: string;
  created_at: string;
}

export interface AuthResponse<T> {
  user: T;
  token: string;
}

export interface ValidationErrors {
  message: string;
  errors: Record<string, string[]>;
}

// ----------------------------------------------------------------
// Menu
// ----------------------------------------------------------------

export interface MenuCategory {
  id: number;
  name: string;
  slug: string;
  defaultTaxCategory: string;
  sortOrder: number;
  isActive: boolean;
  itemCount: number;
}

export interface PaidAddon {
  name: string;
  price: number;
}

export interface MenuItemIngredient {
  ingredientId: string;
  ingredientName: string;
  quantity: number;
  unit: string;
  isCritical: boolean;
}

export interface MenuItem {
  id: number;
  categoryId: number;
  categoryName: string;
  name: string;
  description: string | null;
  price: number;
  imageUrl: string | null;
  available: boolean;
  calories: number;
  fat: number;
  carbs: number;
  protein: number;
  vatRate: number;
  taxCategory: string;
  dietaryPreference: string | null;
  allergies: string[];
  specialTags: string[];
  hasDiscount: boolean;
  discountPercent: number;
  discountedPrice: number | null;
  paidAddons: PaidAddon[];
  freeAddons: string[];
  removableItems: string[];
  translations: Record<string, Record<string, string>>;
  ingredients: MenuItemIngredient[];
  rating: number;
  reviewCount: number;
  orderedCount: number;
  sortOrder: number;
}

// ----------------------------------------------------------------
// Lookups
// ----------------------------------------------------------------

export interface Allergen {
  id: number;
  name: string;
  icon: string | null;
}

export interface SpecialTag {
  id: number;
  slug: string;
  label: string;
  icon: string | null;
}

export interface InventoryItem {
  id: string;
  name: string;
  category: string | null;
  quantity: number;
  unit: string;
  minStock: number;
  costPerUnit: number;
  supplier: string | null;
  isCritical: boolean;
  autoReorder: boolean;
  nutrition: Record<string, number> | null;
}
