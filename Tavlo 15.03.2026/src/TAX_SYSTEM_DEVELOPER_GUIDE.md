# Tavlo Tax System - Developer Guide

## Quick Reference

### Core Utilities (`/utils/taxRules.ts`)

```typescript
import { 
  getVATRate, 
  formatVATDisplay,
  getTaxRule,
  calculateVAT,
  TAX_CATEGORY_OPTIONS,
  type TaxCategory,
  type Country
} from '@/utils/taxRules';

// Get VAT rate for a specific country and category
const rate = getVATRate('AT', 'food'); // Returns: 10

// Format VAT display string
const display = formatVATDisplay('AT', 'food'); 
// Returns: "10% (Austria – Food)"

// Get full tax rule details
const rule = getTaxRule('AT', 'food');
// Returns: { category: 'food', categoryLabel: '...', vatRate: 10, ... }

// Calculate VAT amount
const vat = calculateVAT(100, 'AT', 'food'); // Returns: 10
```

---

## Component Usage

### 1. TaxRulesDisplay

Shows read-only VAT rules for a country.

```typescript
import { TaxRulesDisplay } from '@/components/vendor/TaxRulesDisplay';

// Full display with info cards
<TaxRulesDisplay country="AT" />

// Compact display
<TaxRulesDisplay country="AT" compact />
```

### 2. CountrySelector

Allows country selection (typically in settings).

```typescript
import { CountrySelector } from '@/components/vendor/TaxRulesDisplay';

<CountrySelector
  selectedCountry={country}
  onCountryChange={(newCountry) => setCountry(newCountry)}
  disabled={false} // Set to true after initial setup
/>
```

### 3. ReceiptPreview

Shows VAT breakdown for transparency.

```typescript
import { ReceiptPreview } from '@/components/vendor/ReceiptPreview';

const items = [
  { name: 'Schnitzel', price: 15.90, taxCategory: 'food', quantity: 1 },
  { name: 'Cola', price: 2.50, taxCategory: 'beverage-non-alcoholic', quantity: 2 }
];

<ReceiptPreview 
  items={items} 
  country="AT"
  title="Receipt Preview" 
/>
```

### 4. VATSplitInput (Germany)

For combo items requiring VAT split.

```typescript
import { VATSplitInput } from '@/components/vendor/ReceiptPreview';

<VATSplitInput
  foodPrice={7.50}
  beveragePrice={2.50}
  onFoodPriceChange={setFoodPrice}
  onBeveragePriceChange={setBeveragePrice}
  totalPrice={10.00}
  country="DE"
/>
```

---

## Data Models

### Vendor Settings
```typescript
interface VendorSettings {
  // ... existing fields
  country: 'AT' | 'DE';
  // Legacy field - will be deprecated
  vatRate: number; 
}
```

### Menu Category
```typescript
interface MenuCategory {
  id: string;
  name: string;
  icon: string;
  defaultTaxCategory: 'food' | 'beverage-non-alcoholic' | 'beverage-alcoholic';
}
```

### Menu Item
```typescript
interface MenuItem {
  // ... existing fields
  category: string; // References MenuCategory.id
  taxCategory: 'food' | 'beverage-non-alcoholic' | 'beverage-alcoholic';
  vatRate: number; // Legacy - calculated from taxCategory + country
  
  // For combo items (Germany)
  isCombo?: boolean;
  comboFoodPrice?: number;
  comboBeveragePrice?: number;
}
```

---

## Tax Category Dropdown

Use in forms for item or category classification:

```typescript
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TAX_CATEGORY_OPTIONS, type TaxCategory } from '@/utils/taxRules';

<Select 
  value={taxCategory} 
  onValueChange={(value) => setTaxCategory(value as TaxCategory)}
>
  <SelectTrigger>
    <SelectValue />
  </SelectTrigger>
  <SelectContent>
    {TAX_CATEGORY_OPTIONS.map(option => (
      <SelectItem key={option.value} value={option.value}>
        <div className="flex items-start gap-2">
          <span className="text-lg">{option.icon}</span>
          <div>
            <div className="font-medium">{option.label}</div>
            <div className="text-xs text-gray-500">{option.description}</div>
          </div>
        </div>
      </SelectItem>
    ))}
  </SelectContent>
</Select>
```

---

## Calculating VAT for Orders

```typescript
import { getVATRate } from '@/utils/taxRules';

function calculateOrderVAT(items: MenuItem[], country: Country) {
  const breakdown = items.reduce((acc, item) => {
    const vatRate = getVATRate(country, item.taxCategory);
    const grossPrice = item.price * (item.quantity || 1);
    const netPrice = grossPrice / (1 + vatRate / 100);
    const vatAmount = grossPrice - netPrice;
    
    const key = `${item.taxCategory}-${vatRate}`;
    if (!acc[key]) {
      acc[key] = { taxCategory: item.taxCategory, vatRate, total: 0 };
    }
    acc[key].total += vatAmount;
    
    return acc;
  }, {});
  
  return breakdown;
}
```

---

## Backend Integration

### Saving Menu Category
```typescript
const newCategory = {
  id: 'appetizers',
  name: 'Appetizers',
  icon: '🍽️',
  defaultTaxCategory: 'food' as TaxCategory
};

await api.updateMenu(restaurantId, {
  ...menu,
  categories: [...menu.categories, newCategory]
});
```

### Saving Menu Item
```typescript
const newItem = {
  id: 'item_1',
  name: 'Schnitzel',
  category: 'mains',
  price: 15.90,
  taxCategory: 'food' as TaxCategory,
  // Legacy - can be calculated on backend
  vatRate: getVATRate(vendorCountry, 'food'),
  // ... other fields
};

await api.addMenuItem(restaurantId, newItem);
```

### Retrieving VAT for Invoice
```typescript
// On backend or frontend
import { getVATRate } from './utils/taxRules';

const vendorCountry = vendor.settings.country; // 'AT' or 'DE'
const item = orderItems[0];
const vatRate = getVATRate(vendorCountry, item.taxCategory);

// Calculate for invoice
const netAmount = item.price / (1 + vatRate / 100);
const vatAmount = item.price - netAmount;
```

---

## Migration Script

For existing restaurants without tax categories:

```typescript
async function migrateToTaxCategories(restaurantId: string) {
  const menu = await api.getMenu(restaurantId);
  const vendor = await api.getVendor(restaurantId);
  
  // Set default country
  if (!vendor.settings.country) {
    await api.updateVendorSettings(restaurantId, {
      ...vendor.settings,
      country: 'AT' // Default to Austria
    });
  }
  
  // Migrate categories
  const updatedCategories = menu.categories.map(cat => {
    // Infer tax category from category name
    const lowerName = cat.name.toLowerCase();
    let taxCategory: TaxCategory = 'food';
    
    if (lowerName.includes('drink') || lowerName.includes('beverage')) {
      taxCategory = 'beverage-non-alcoholic';
    } else if (lowerName.includes('wine') || lowerName.includes('beer') || 
               lowerName.includes('alcohol') || lowerName.includes('cocktail')) {
      taxCategory = 'beverage-alcoholic';
    }
    
    return {
      ...cat,
      defaultTaxCategory: taxCategory,
      icon: getTaxCategoryOption(taxCategory)?.icon || cat.icon
    };
  });
  
  // Migrate items
  const updatedItems = menu.items.map(item => {
    const category = updatedCategories.find(c => c.id === item.category);
    const taxCategory = category?.defaultTaxCategory || 'food';
    
    return {
      ...item,
      taxCategory
    };
  });
  
  // Save migrated menu
  await api.updateMenu(restaurantId, {
    ...menu,
    categories: updatedCategories,
    items: updatedItems
  });
}
```

---

## Testing

### Unit Tests

```typescript
import { getVATRate, calculateVAT, formatVATDisplay } from '@/utils/taxRules';

describe('Tax Rules', () => {
  it('should return correct VAT rate for Austria food', () => {
    expect(getVATRate('AT', 'food')).toBe(10);
  });
  
  it('should return correct VAT rate for Germany food', () => {
    expect(getVATRate('DE', 'food')).toBe(7);
  });
  
  it('should calculate VAT correctly', () => {
    const vat = calculateVAT(100, 'AT', 'food');
    expect(vat).toBe(10);
  });
  
  it('should format VAT display correctly', () => {
    const display = formatVATDisplay('AT', 'food');
    expect(display).toBe('10% (Austria – Food)');
  });
});
```

### Integration Tests

```typescript
describe('Menu Item Tax Category', () => {
  it('should inherit tax category from menu category', async () => {
    // Create category with default tax category
    const category = {
      id: 'mains',
      name: 'Main Dishes',
      defaultTaxCategory: 'food'
    };
    
    // Create item in that category
    const item = {
      name: 'Schnitzel',
      category: 'mains',
      price: 15.90
    };
    
    // Tax category should be inherited
    expect(item.taxCategory).toBe(category.defaultTaxCategory);
  });
});
```

---

## Common Patterns

### Pattern 1: Display VAT Rate in Item Card
```typescript
function ItemCard({ item, country }: { item: MenuItem, country: Country }) {
  const vatDisplay = formatVATDisplay(country, item.taxCategory);
  
  return (
    <div className="item-card">
      <h3>{item.name}</h3>
      <p>€{item.price.toFixed(2)}</p>
      <Badge variant="outline">{vatDisplay}</Badge>
    </div>
  );
}
```

### Pattern 2: Validate Tax Category Selection
```typescript
function validateMenuItem(item: MenuItem, category: MenuCategory): string[] {
  const errors: string[] = [];
  
  if (!item.taxCategory) {
    errors.push('Tax category is required');
  }
  
  // Warn if different from category default
  if (item.taxCategory !== category.defaultTaxCategory) {
    console.warn('Item tax category differs from category default');
  }
  
  return errors;
}
```

### Pattern 3: Calculate Total with VAT Breakdown
```typescript
function calculateOrderTotal(items: MenuItem[], country: Country) {
  let netTotal = 0;
  const vatBreakdown: Record<string, number> = {};
  
  items.forEach(item => {
    const vatRate = getVATRate(country, item.taxCategory);
    const grossPrice = item.price * (item.quantity || 1);
    const netPrice = grossPrice / (1 + vatRate / 100);
    const vatAmount = grossPrice - netPrice;
    
    netTotal += netPrice;
    
    const key = `${item.taxCategory}-${vatRate}`;
    vatBreakdown[key] = (vatBreakdown[key] || 0) + vatAmount;
  });
  
  const totalVAT = Object.values(vatBreakdown).reduce((sum, amt) => sum + amt, 0);
  
  return {
    netTotal,
    vatBreakdown,
    totalVAT,
    grandTotal: netTotal + totalVAT
  };
}
```

---

## Troubleshooting

### Issue: VAT rate showing as undefined
**Cause:** Invalid country or tax category
**Solution:**
```typescript
const country: Country = vendorSettings.country || 'AT';
const taxCategory: TaxCategory = item.taxCategory || 'food';
const vatRate = getVATRate(country, taxCategory);
```

### Issue: Tax category not inheriting from menu category
**Cause:** Menu category doesn't have defaultTaxCategory
**Solution:** Ensure all categories have defaultTaxCategory set during creation/migration

### Issue: German VAT split validation failing
**Cause:** Floating point precision issues
**Solution:**
```typescript
const isValid = Math.abs((foodPrice + beveragePrice) - totalPrice) < 0.01;
```

---

## Best Practices

1. **Always validate tax category exists** before using it
2. **Use the Country type** instead of strings for type safety
3. **Calculate VAT on the backend** for authoritative calculations
4. **Store both taxCategory and vatRate** for historical accuracy
5. **Log tax calculations** for audit trail
6. **Use formatVATDisplay()** for consistent UI display
7. **Test with both countries** (AT and DE) in your test suite
8. **Handle legacy data gracefully** with defaults

---

## API Reference

### `getVATRate(country, taxCategory): number`
Returns the VAT rate percentage for a given country and tax category.

### `calculateVAT(netPrice, country, taxCategory): number`
Calculates the VAT amount from a net price.

### `calculateGrossPrice(netPrice, country, taxCategory): number`
Calculates the gross price (net + VAT).

### `calculateNetPrice(grossPrice, country, taxCategory): number`
Extracts the net price from a gross price.

### `formatVATDisplay(country, taxCategory): string`
Returns a formatted string like "10% (Austria – Food)".

### `getTaxRule(country, taxCategory): TaxRule | undefined`
Returns the full tax rule object with all metadata.

### `getCountryTaxRules(country): CountryTaxRules`
Returns all tax rules for a specific country.

### `TAX_CATEGORY_OPTIONS: TaxCategoryOption[]`
Array of tax category options for dropdowns with icons and descriptions.

---

## Constants

```typescript
// Tax Categories
type TaxCategory = 'food' | 'beverage-non-alcoholic' | 'beverage-alcoholic';

// Countries
type Country = 'AT' | 'DE';

// VAT Rates
Austria (AT):
  - Food: 10%
  - Beverage (Non-Alcoholic): 20%
  - Beverage (Alcoholic): 20%

Germany (DE):
  - Food: 7%
  - Beverage (Non-Alcoholic): 19%
  - Beverage (Alcoholic): 19%
```

---

**For questions or issues, refer to:**
- `/TAX_SYSTEM_REDESIGN_COMPLETE.md` - Full implementation documentation
- `/TAX_SYSTEM_VISUAL_GUIDE.md` - UI/UX visual reference
- `/utils/taxRules.ts` - Source code with inline documentation
