import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Package, Plus, X } from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { toast } from 'sonner';

interface Ingredient {
  ingredientId: string;
  ingredientName: string;
  quantity: number;
  unit: string;
  isCritical: boolean;
}

interface RecipeIngredientsSectionProps {
  ingredients: Ingredient[];
  availableIngredients: any[];
  onAddIngredient: (ingredient: Ingredient) => void;
  onRemoveIngredient: (index: number) => void;
}

export function RecipeIngredientsSection({
  ingredients,
  availableIngredients,
  onAddIngredient,
  onRemoveIngredient
}: RecipeIngredientsSectionProps) {
  const [isAddingIngredient, setIsAddingIngredient] = useState(false);
  const [tempIngredient, setTempIngredient] = useState({
    ingredientId: '',
    ingredientName: '',
    quantity: 0,
    unit: '',
    isCritical: false
  });

  const handleAdd = () => {
    if (!tempIngredient.ingredientId || tempIngredient.quantity <= 0) {
      toast.error('Please select an ingredient and enter a quantity');
      return;
    }
    
    const ingredient = availableIngredients.find(i => i.id === tempIngredient.ingredientId);
    if (!ingredient) return;

    onAddIngredient({
      ingredientId: tempIngredient.ingredientId,
      ingredientName: ingredient.name,
      quantity: tempIngredient.quantity,
      unit: ingredient.unit,
      isCritical: tempIngredient.isCritical
    });

    setTempIngredient({
      ingredientId: '',
      ingredientName: '',
      quantity: 0,
      unit: '',
      isCritical: false
    });
    setIsAddingIngredient(false);
    toast.success('Ingredient added');
  };

  const handleCancel = () => {
    setIsAddingIngredient(false);
    setTempIngredient({
      ingredientId: '',
      ingredientName: '',
      quantity: 0,
      unit: '',
      isCritical: false
    });
  };

  return (
    <div className="space-y-4 border-t pt-6">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="font-semibold text-neutral-900">Recipe & Ingredients</h3>
          <p className="text-xs text-neutral-500 mt-0.5">Link inventory ingredients to track stock and calculate costs</p>
        </div>
      </div>

      {/* Current Ingredients */}
      {ingredients.length > 0 && (
        <div className="space-y-2">
          {ingredients.map((ingredient, index) => (
            <div key={index} className="flex items-center gap-3 p-3 bg-neutral-50 rounded-lg border">
              <Package className="w-4 h-4 text-neutral-500 flex-shrink-0" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium">{ingredient.ingredientName}</p>
                <p className="text-xs text-neutral-500">
                  {ingredient.quantity} {ingredient.unit} per serving
                  {ingredient.isCritical && <span className="ml-2 text-orange-600 font-medium">• Critical</span>}
                </p>
              </div>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => onRemoveIngredient(index)}
                className="text-red-600 hover:text-red-700 hover:bg-red-50"
              >
                <X className="w-4 h-4" />
              </Button>
            </div>
          ))}
        </div>
      )}

      {/* Add Ingredient Form */}
      {isAddingIngredient ? (
        <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div className="col-span-2">
              <Label>Select Ingredient *</Label>
              <Select
                value={tempIngredient.ingredientId}
                onValueChange={(val) => {
                  const ing = availableIngredients.find(i => i.id === val);
                  setTempIngredient({
                    ...tempIngredient,
                    ingredientId: val,
                    ingredientName: ing?.name || '',
                    unit: ing?.unit || ''
                  });
                }}
              >
                <SelectTrigger className="mt-1.5 bg-white">
                  <SelectValue placeholder="Choose from inventory..." />
                </SelectTrigger>
                <SelectContent>
                  {availableIngredients.length === 0 && (
                    <div className="p-4 text-center text-sm text-neutral-500">
                      No ingredients in inventory.<br />Add ingredients first in Inventory tab.
                    </div>
                  )}
                  {availableIngredients.map((ing) => (
                    <SelectItem key={ing.id} value={ing.id}>
                      {ing.name} ({ing.unit})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Quantity per Serving *</Label>
              <Input
                type="number"
                step="0.01"
                placeholder="0.1"
                value={tempIngredient.quantity || ''}
                onChange={(e) => setTempIngredient({...tempIngredient, quantity: parseFloat(e.target.value) || 0})}
                className="mt-1.5 bg-white"
              />
            </div>
            <div>
              <Label>Unit</Label>
              <Input
                value={tempIngredient.unit}
                disabled
                className="mt-1.5 bg-neutral-100 text-neutral-600"
                placeholder="Auto-filled"
              />
            </div>
            <div className="col-span-2">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={tempIngredient.isCritical}
                  onChange={(e) => setTempIngredient({...tempIngredient, isCritical: e.target.checked})}
                  className="w-4 h-4 rounded border-neutral-300"
                />
                <span className="text-sm font-medium">Critical Ingredient</span>
              </label>
              <p className="text-xs text-neutral-500 ml-6">Menu item becomes unavailable when this ingredient is out of stock</p>
            </div>
          </div>
          <div className="flex gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={handleCancel}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button
              type="button"
              size="sm"
              onClick={handleAdd}
              className="flex-1 bg-blue-600 hover:bg-blue-700"
            >
              Add Ingredient
            </Button>
          </div>
        </div>
      ) : (
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setIsAddingIngredient(true)}
          className="w-full"
        >
          <Plus className="w-3 h-3 mr-1" />
          Add Ingredient
        </Button>
      )}

      {ingredients.length === 0 && !isAddingIngredient && (
        <p className="text-sm text-neutral-500 italic text-center py-4">No ingredients linked yet. Add ingredients to enable inventory tracking.</p>
      )}
    </div>
  );
}
