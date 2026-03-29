import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Badge } from '../ui/badge';
import { Receipt, CheckCircle } from 'lucide-react';
import { getVATRate, type Country, type TaxCategory } from '../../utils/taxRules';

interface ReceiptItem {
  name: string;
  price: number;
  taxCategory: TaxCategory;
  quantity?: number;
}

interface ReceiptPreviewProps {
  items: ReceiptItem[];
  country: Country;
  title?: string;
}

/**
 * Receipt Preview Component
 * Shows VAT breakdown to build trust in tax compliance
 */
export function ReceiptPreview({ items, country, title = "Receipt Preview" }: ReceiptPreviewProps) {
  // Group items by tax category and calculate totals
  const breakdown = items.reduce((acc, item) => {
    const qty = item.quantity || 1;
    const itemTotal = item.price * qty;
    const vatRate = getVATRate(country, item.taxCategory);
    const netAmount = itemTotal / (1 + vatRate / 100);
    const vatAmount = itemTotal - netAmount;

    const key = `${item.taxCategory}-${vatRate}`;
    
    if (!acc[key]) {
      acc[key] = {
        taxCategory: item.taxCategory,
        vatRate,
        netAmount: 0,
        vatAmount: 0,
        grossAmount: 0,
        categoryLabel: getCategoryLabel(item.taxCategory)
      };
    }

    acc[key].netAmount += netAmount;
    acc[key].vatAmount += vatAmount;
    acc[key].grossAmount += itemTotal;

    return acc;
  }, {} as Record<string, {
    taxCategory: TaxCategory;
    vatRate: number;
    netAmount: number;
    vatAmount: number;
    grossAmount: number;
    categoryLabel: string;
  }>);

  const totals = Object.values(breakdown);
  const grandTotal = totals.reduce((sum, t) => sum + t.grossAmount, 0);
  const totalVAT = totals.reduce((sum, t) => sum + t.vatAmount, 0);
  const totalNet = totals.reduce((sum, t) => sum + t.netAmount, 0);

  if (items.length === 0) {
    return (
      <Card className="border-dashed">
        <CardHeader>
          <CardTitle className="text-base flex items-center gap-2">
            <Receipt className="w-4 h-4" />
            {title}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-gray-500 text-center py-4">
            Add items to see VAT breakdown
          </p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="border-2 border-gray-200 bg-gradient-to-br from-white to-gray-50">
      <CardHeader className="pb-3">
        <CardTitle className="text-base flex items-center gap-2">
          <Receipt className="w-4 h-4" />
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Item List */}
        <div className="space-y-2 text-sm">
          {items.map((item, idx) => {
            const qty = item.quantity || 1;
            const total = item.price * qty;
            return (
              <div key={idx} className="flex justify-between text-gray-600">
                <span>
                  {qty > 1 && `${qty}× `}
                  {item.name}
                </span>
                <span className="font-mono">€{total.toFixed(2)}</span>
              </div>
            );
          })}
        </div>

        <div className="border-t pt-3 space-y-2">
          <div className="flex justify-between text-sm">
            <span className="text-gray-600">Net Total</span>
            <span className="font-mono">€{totalNet.toFixed(2)}</span>
          </div>

          {/* VAT Breakdown by Category */}
          {totals.map((t, idx) => (
            <div key={idx} className="flex justify-between text-sm">
              <span className="text-gray-600">
                incl. VAT {t.vatRate}% ({t.categoryLabel})
              </span>
              <span className="font-mono text-green-700">€{t.vatAmount.toFixed(2)}</span>
            </div>
          ))}

          <div className="border-t pt-2 flex justify-between">
            <span>Total</span>
            <span className="font-mono">€{grandTotal.toFixed(2)}</span>
          </div>
        </div>

        {/* Compliance Badge */}
        <div className="pt-2 border-t">
          <Badge variant="outline" className="w-full justify-center gap-1.5 py-1.5 bg-green-50 border-green-200 text-green-700">
            <CheckCircle className="w-3 h-3" />
            Tax-compliant for {country === 'AT' ? 'Austria' : 'Germany'}
          </Badge>
        </div>
      </CardContent>
    </Card>
  );
}

function getCategoryLabel(category: TaxCategory): string {
  switch (category) {
    case 'food':
      return 'Food';
    case 'beverage-non-alcoholic':
      return 'Drinks';
    case 'beverage-alcoholic':
      return 'Alcohol';
    default:
      return 'Other';
  }
}

interface VATSplitInputProps {
  foodPrice: number;
  beveragePrice: number;
  onFoodPriceChange: (price: number) => void;
  onBeveragePriceChange: (price: number) => void;
  totalPrice: number;
  country: Country;
}

/**
 * VAT Split Input for Combo/Menu Items (Germany requirement)
 * Forces vendors to split food and beverage components
 */
export function VATSplitInput({
  foodPrice,
  beveragePrice,
  onFoodPriceChange,
  onBeveragePriceChange,
  totalPrice,
  country
}: VATSplitInputProps) {
  const currentTotal = foodPrice + beveragePrice;
  const isValid = Math.abs(currentTotal - totalPrice) < 0.01;

  return (
    <div className="space-y-3 p-4 border-2 border-amber-200 rounded-lg bg-amber-50/50">
      <div className="flex items-start gap-2">
        <div className="text-2xl">⚖️</div>
        <div className="flex-1">
          <h4 className="text-sm mb-1">VAT Split Required</h4>
          <p className="text-xs text-gray-600">
            German tax law requires VAT to be split for food and drinks sold together.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <label className="block text-xs text-gray-600 mb-1 flex items-center gap-1">
            🍽 Food Component
          </label>
          <div className="relative">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">€</span>
            <input
              type="number"
              value={foodPrice}
              onChange={(e) => onFoodPriceChange(parseFloat(e.target.value) || 0)}
              step="0.01"
              min="0"
              max={totalPrice}
              className="w-full pl-7 pr-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-amber-500"
            />
          </div>
          <div className="text-xs text-gray-500 mt-1">
            VAT: {getVATRate(country, 'food')}%
          </div>
        </div>

        <div>
          <label className="block text-xs text-gray-600 mb-1 flex items-center gap-1">
            🥤 Beverage Component
          </label>
          <div className="relative">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">€</span>
            <input
              type="number"
              value={beveragePrice}
              onChange={(e) => onBeveragePriceChange(parseFloat(e.target.value) || 0)}
              step="0.01"
              min="0"
              max={totalPrice}
              className="w-full pl-7 pr-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-amber-500"
            />
          </div>
          <div className="text-xs text-gray-500 mt-1">
            VAT: {getVATRate(country, 'beverage-non-alcoholic')}%
          </div>
        </div>
      </div>

      <div className={`text-xs px-3 py-2 rounded-lg ${
        isValid 
          ? 'bg-green-100 text-green-800' 
          : 'bg-red-100 text-red-800'
      }`}>
        {isValid ? (
          <span className="flex items-center gap-1">
            <CheckCircle className="w-3 h-3" />
            Split matches total price: €{totalPrice.toFixed(2)}
          </span>
        ) : (
          <span>
            ⚠️ Split total (€{currentTotal.toFixed(2)}) must equal item price (€{totalPrice.toFixed(2)})
          </span>
        )}
      </div>
    </div>
  );
}
