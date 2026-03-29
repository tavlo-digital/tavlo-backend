/**
 * Tavlo Tax Rules System
 * 
 * Vendors classify products. Tavlo applies the law.
 * 
 * VAT rates are never manually editable. They are automatically applied
 * based on the restaurant's country and the item's tax category.
 */

export type TaxCategory = 'food' | 'beverage-non-alcoholic' | 'beverage-alcoholic';
export type Country = 'AT' | 'DE';

export interface TaxRule {
  category: TaxCategory;
  categoryLabel: string;
  categoryIcon: string;
  vatRate: number;
  description: string;
}

export interface CountryTaxRules {
  country: Country;
  countryName: string;
  countryFlag: string;
  rules: TaxRule[];
}

/**
 * Official VAT rates by country and tax category
 * Based on Austrian and German tax law
 */
export const TAX_RULES: Record<Country, CountryTaxRules> = {
  AT: {
    country: 'AT',
    countryName: 'Austria',
    countryFlag: '🇦🇹',
    rules: [
      {
        category: 'food',
        categoryLabel: 'Food (Prepared Meals)',
        categoryIcon: '🍽',
        vatRate: 10,
        description: 'Reduced rate for food products and prepared meals'
      },
      {
        category: 'beverage-non-alcoholic',
        categoryLabel: 'Non-Alcoholic Beverages',
        categoryIcon: '🥤',
        vatRate: 20,
        description: 'Standard rate for non-alcoholic drinks'
      },
      {
        category: 'beverage-alcoholic',
        categoryLabel: 'Alcoholic Beverages',
        categoryIcon: '🍺',
        vatRate: 20,
        description: 'Standard rate for alcoholic drinks'
      }
    ]
  },
  DE: {
    country: 'DE',
    countryName: 'Germany',
    countryFlag: '🇩🇪',
    rules: [
      {
        category: 'food',
        categoryLabel: 'Food (Prepared Meals)',
        categoryIcon: '🍽',
        vatRate: 7,
        description: 'Reduced rate for food products'
      },
      {
        category: 'beverage-non-alcoholic',
        categoryLabel: 'Non-Alcoholic Beverages',
        categoryIcon: '🥤',
        vatRate: 19,
        description: 'Standard rate for non-alcoholic drinks'
      },
      {
        category: 'beverage-alcoholic',
        categoryLabel: 'Alcoholic Beverages',
        categoryIcon: '🍺',
        vatRate: 19,
        description: 'Standard rate for alcoholic drinks'
      }
    ]
  }
};

/**
 * Get VAT rate for a specific country and tax category
 */
export function getVATRate(country: Country, taxCategory: TaxCategory): number {
  const countryRules = TAX_RULES[country];
  const rule = countryRules.rules.find(r => r.category === taxCategory);
  return rule?.vatRate || 0;
}

/**
 * Get tax rule details for a specific country and category
 */
export function getTaxRule(country: Country, taxCategory: TaxCategory): TaxRule | undefined {
  const countryRules = TAX_RULES[country];
  return countryRules.rules.find(r => r.category === taxCategory);
}

/**
 * Get all tax rules for a country
 */
export function getCountryTaxRules(country: Country): CountryTaxRules {
  return TAX_RULES[country];
}

/**
 * Get all available countries
 */
export function getAvailableCountries(): CountryTaxRules[] {
  return Object.values(TAX_RULES);
}

/**
 * Calculate VAT amount from net price
 */
export function calculateVAT(netPrice: number, country: Country, taxCategory: TaxCategory): number {
  const vatRate = getVATRate(country, taxCategory);
  return (netPrice * vatRate) / 100;
}

/**
 * Calculate gross price (including VAT)
 */
export function calculateGrossPrice(netPrice: number, country: Country, taxCategory: TaxCategory): number {
  return netPrice + calculateVAT(netPrice, country, taxCategory);
}

/**
 * Calculate net price from gross price
 */
export function calculateNetPrice(grossPrice: number, country: Country, taxCategory: TaxCategory): number {
  const vatRate = getVATRate(country, taxCategory);
  return grossPrice / (1 + vatRate / 100);
}

/**
 * Format VAT display string
 */
export function formatVATDisplay(country: Country, taxCategory: TaxCategory): string {
  const rule = getTaxRule(country, taxCategory);
  const countryRules = getCountryTaxRules(country);
  
  if (!rule) return '';
  
  return `${rule.vatRate}% (${countryRules.countryName} – ${rule.categoryLabel})`;
}

/**
 * Tax category options for dropdown selection
 */
export const TAX_CATEGORY_OPTIONS = [
  {
    value: 'food' as TaxCategory,
    label: 'Food',
    icon: '🍽',
    description: 'Prepared meals, main dishes, appetizers, desserts'
  },
  {
    value: 'beverage-non-alcoholic' as TaxCategory,
    label: 'Beverage (Non-Alcoholic)',
    icon: '🥤',
    description: 'Soft drinks, juices, water, coffee, tea'
  },
  {
    value: 'beverage-alcoholic' as TaxCategory,
    label: 'Beverage (Alcoholic)',
    icon: '🍺',
    description: 'Beer, wine, spirits, cocktails'
  }
];

/**
 * Get tax category option by value
 */
export function getTaxCategoryOption(category: TaxCategory) {
  return TAX_CATEGORY_OPTIONS.find(opt => opt.value === category);
}

/**
 * Validate if combo/menu items require VAT split (Germany)
 */
export function requiresVATSplit(country: Country): boolean {
  return country === 'DE';
}
