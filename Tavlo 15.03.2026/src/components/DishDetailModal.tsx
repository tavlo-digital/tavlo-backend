import { useState } from 'react';
import { X, Star, Minus, Plus } from 'lucide-react';
import { Button } from './ui/button';
import { Textarea } from './ui/textarea';
import { Checkbox } from './ui/checkbox';
import { Label } from './ui/label';
import { useLanguage } from '../contexts/LanguageContext';
import { getTranslatedField, getTranslation } from '../utils/translations';

interface DishDetailModalProps {
  item: any;
  onClose: () => void;
  onAddToBasket: (item: any, quantity: number, modifiers: any[], specialRequest: string) => void;
}

export function DishDetailModal({ item, onClose, onAddToBasket }: DishDetailModalProps) {
  const [quantity, setQuantity] = useState(1);
  const [selectedModifiers, setSelectedModifiers] = useState<any[]>([]);
  const [selectedPaidAddons, setSelectedPaidAddons] = useState<any[]>([]);
  const [selectedFreeAddons, setSelectedFreeAddons] = useState<string[]>([]);
  const [selectedRemovals, setSelectedRemovals] = useState<string[]>([]);
  const [specialRequest, setSpecialRequest] = useState('');
  const [activeTab, setActiveTab] = useState<'details' | 'photos'>('details');

  const { language } = useLanguage();
  
  // Get translated content
  const displayName = getTranslatedField(item, 'name', language);
  const displayDescription = getTranslatedField(item, 'description', language);
  
  // Helper to get translated add-on name
  const getTranslatedAddon = (addonName: string, addonType: 'paidAddons' | 'freeAddons' | 'removableItems' | 'modifiers'): string => {
    if (!item.translations?.[language]?.[addonType]) {
      return addonName;
    }
    
    const translatedArray = item.translations[language][addonType];
    const originalArray = item[addonType];
    
    // Find index in original array
    const index = originalArray?.findIndex((addon: any) => 
      typeof addon === 'string' ? addon === addonName : addon.name === addonName
    );
    
    if (index !== -1 && translatedArray[index]) {
      return translatedArray[index];
    }
    
    return addonName;
  };

  const toggleModifier = (modifier: any) => {
    setSelectedModifiers(prev => {
      const exists = prev.find(m => m.name === modifier.name);
      if (exists) {
        return prev.filter(m => m.name !== modifier.name);
      }
      return [...prev, modifier];
    });
  };

  const togglePaidAddon = (addon: any) => {
    setSelectedPaidAddons(prev => {
      const exists = prev.find(a => a.name === addon.name);
      if (exists) {
        return prev.filter(a => a.name !== addon.name);
      }
      return [...prev, addon];
    });
  };

  const toggleFreeAddon = (addon: string) => {
    setSelectedFreeAddons(prev => {
      if (prev.includes(addon)) {
        return prev.filter(a => a !== addon);
      }
      return [...prev, addon];
    });
  };

  const toggleRemoval = (item: string) => {
    setSelectedRemovals(prev => {
      if (prev.includes(item)) {
        return prev.filter(i => i !== item);
      }
      return [...prev, item];
    });
  };

  const calculateTotal = () => {
    const basePrice = item.price * quantity;
    const modifiersPrice = selectedModifiers.reduce((sum, m) => sum + (m.price * quantity), 0);
    const paidAddonsPrice = selectedPaidAddons.reduce((sum, a) => sum + (a.price * quantity), 0);
    return basePrice + modifiersPrice + paidAddonsPrice;
  };

  const handleAddToBasket = () => {
    const allModifiers = [
      ...selectedModifiers,
      ...selectedPaidAddons.map(a => ({ ...a, type: 'paid-addon' })),
      ...selectedFreeAddons.map(a => ({ name: a, price: 0, type: 'free-addon' })),
      ...selectedRemovals.map(i => ({ name: i, price: 0, type: 'removal' }))
    ];
    onAddToBasket(item, quantity, allModifiers, specialRequest);
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-end md:items-center justify-center z-50">
      <div className="bg-white w-full md:max-w-2xl md:rounded-lg max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-white border-b p-4 flex items-center justify-between">
          <div className="flex-1">
            <h2 className="text-xl">{displayName}</h2>
            <div className="text-sm text-gray-600 mt-1">
              €{item.price.toFixed(2)} • {item.calories} kcal
              {item.allergens?.length > 0 && (
                <span> • {item.allergens.join(', ')}</span>
              )}
            </div>
          </div>
          <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex border-b">
          <button
            onClick={() => setActiveTab('details')}
            className={`flex-1 py-3 ${
              activeTab === 'details'
                ? 'border-b-2 border-orange-500 text-orange-500'
                : 'text-gray-600'
            }`}
          >
            Details
          </button>
          <button
            onClick={() => setActiveTab('photos')}
            className={`flex-1 py-3 ${
              activeTab === 'photos'
                ? 'border-b-2 border-orange-500 text-orange-500'
                : 'text-gray-600'
            }`}
          >
            Photos
          </button>
        </div>

        <div className="p-4 space-y-6">
          {activeTab === 'details' ? (
            <>
              {/* Description */}
              <div>
                <p className="text-gray-700">{displayDescription}</p>
              </div>

              {/* Nutrition */}
              <div>
                <h3 className="mb-2">Nutrition per serving</h3>
                <div className="grid grid-cols-4 gap-3 bg-gray-50 p-3 rounded-lg">
                  <div className="text-center">
                    <div className="text-sm text-gray-600">Calories</div>
                    <div>{item.calories}</div>
                  </div>
                  <div className="text-center">
                    <div className="text-sm text-gray-600">Protein</div>
                    <div>{item.protein}g</div>
                  </div>
                  <div className="text-center">
                    <div className="text-sm text-gray-600">Carbs</div>
                    <div>{item.carbs}g</div>
                  </div>
                  <div className="text-center">
                    <div className="text-sm text-gray-600">Fat</div>
                    <div>{item.fat}g</div>
                  </div>
                </div>
              </div>

              {/* Social proof */}
              <div className="bg-blue-50 p-3 rounded-lg">
                <div className="text-sm">
                  Ordered {item.orderedCount} times
                </div>
                <div className="flex items-center gap-1 mt-1">
                  <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
                  <span className="text-sm">
                    {item.rating}/5 from {item.reviewCount} reviews
                  </span>
                </div>
              </div>

              {/* Modifiers */}
              {item.modifiers && item.modifiers.length > 0 && (
                <div>
                  <h3 className="mb-3">Choose options</h3>
                  <div className="space-y-2">
                    {item.modifiers.map((modifier: any) => (
                      <div key={modifier.name} className="flex items-center space-x-2">
                        <Checkbox
                          id={modifier.name}
                          checked={selectedModifiers.some(m => m.name === modifier.name)}
                          onCheckedChange={() => toggleModifier(modifier)}
                        />
                        <Label htmlFor={modifier.name} className="flex-1 cursor-pointer">
                          {getTranslatedAddon(modifier.name, 'modifiers')} (+€{modifier.price.toFixed(2)})
                        </Label>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Paid Add-ons */}
              {item.paidAddons && item.paidAddons.length > 0 && (
                <div>
                  <h3 className="mb-3">Extra Add-ons (Additional Cost)</h3>
                  <div className="space-y-2">
                    {item.paidAddons.map((addon: any) => (
                      <div key={addon.name} className="flex items-center space-x-2">
                        <Checkbox
                          id={`paid-${addon.name}`}
                          checked={selectedPaidAddons.some(a => a.name === addon.name)}
                          onCheckedChange={() => togglePaidAddon(addon)}
                        />
                        <Label htmlFor={`paid-${addon.name}`} className="flex-1 cursor-pointer">
                          {getTranslatedAddon(addon.name, 'paidAddons')} (+€{addon.price.toFixed(2)})
                        </Label>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Free Add-ons */}
              {item.freeAddons && item.freeAddons.length > 0 && (
                <div>
                  <h3 className="mb-3">Free Add-ons</h3>
                  <div className="space-y-2">
                    {item.freeAddons.map((addon: string) => (
                      <div key={addon} className="flex items-center space-x-2">
                        <Checkbox
                          id={`free-${addon}`}
                          checked={selectedFreeAddons.includes(addon)}
                          onCheckedChange={() => toggleFreeAddon(addon)}
                        />
                        <Label htmlFor={`free-${addon}`} className="flex-1 cursor-pointer">
                          {getTranslatedAddon(addon, 'freeAddons')} (Free)
                        </Label>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Removable Items */}
              {item.removableItems && item.removableItems.length > 0 && (
                <div>
                  <h3 className="mb-3">Remove Items (if you don't want them)</h3>
                  <div className="space-y-2">
                    {item.removableItems.map((remItem: string) => (
                      <div key={remItem} className="flex items-center space-x-2">
                        <Checkbox
                          id={`remove-${remItem}`}
                          checked={selectedRemovals.includes(remItem)}
                          onCheckedChange={() => toggleRemoval(remItem)}
                        />
                        <Label htmlFor={`remove-${remItem}`} className="flex-1 cursor-pointer">
                          No {getTranslatedAddon(remItem, 'removableItems')}
                        </Label>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Special request */}
              <div>
                <Label htmlFor="special-request">Special request (optional)</Label>
                <Textarea
                  id="special-request"
                  value={specialRequest}
                  onChange={(e) => setSpecialRequest(e.target.value)}
                  placeholder="Add note for the kitchen (e.g. 'no onion')"
                  className="mt-2"
                  rows={3}
                />
              </div>
            </>
          ) : (
            <div className="space-y-3">
              <div className="text-sm text-gray-600">
                Customer and restaurant photos will appear here
              </div>
              <div className="grid grid-cols-2 gap-3">
                {[1, 2, 3, 4].map((i) => (
                  <div key={i} className="aspect-square bg-gray-100 rounded-lg" />
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="sticky bottom-0 bg-white border-t p-4">
          <div className="flex items-center gap-4 mb-4">
            <div className="flex items-center gap-3 bg-gray-100 rounded-lg p-2">
              <button
                onClick={() => setQuantity(Math.max(1, quantity - 1))}
                className="p-2 hover:bg-white rounded"
              >
                <Minus className="w-4 h-4" />
              </button>
              <span className="w-8 text-center">{quantity}</span>
              <button
                onClick={() => setQuantity(quantity + 1)}
                className="p-2 hover:bg-white rounded"
              >
                <Plus className="w-4 h-4" />
              </button>
            </div>

            <Button onClick={handleAddToBasket} className="flex-1" size="lg">
              Add to order • €{calculateTotal().toFixed(2)}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}