import { useState } from 'react';
import { X, Leaf, Milk, Wheat, Egg, Fish, Nut, ChevronRight, Sparkles } from 'lucide-react';
import { Button } from './ui/button';

interface FilterSheetProps {
  isOpen: boolean;
  onClose: () => void;
  filters: {
    dietary: string[];
    allergens: string[];
  };
  onApplyFilters: (filters: { dietary: string[]; allergens: string[] }) => void;
}

const dietaryOptions = [
  { id: 'vegetarian', label: 'Vegetarian', icon: '🥗', description: 'No meat or fish', color: 'bg-green-50 border-green-200 text-green-700' },
  { id: 'vegan', label: 'Vegan', icon: '🌱', description: 'Plant-based only', color: 'bg-green-50 border-green-200 text-green-700' },
  { id: 'pescatarian', label: 'Pescatarian', icon: '🐟', description: 'Fish but no meat', color: 'bg-blue-50 border-blue-200 text-blue-700' },
  { id: 'gluten-free', label: 'Gluten-Free', icon: '🌾', description: 'No gluten', color: 'bg-amber-50 border-amber-200 text-amber-700' }
];

const allergenOptions = [
  { id: 'dairy', label: 'Dairy', icon: '🥛', description: 'Milk & dairy products' },
  { id: 'gluten', label: 'Gluten', icon: '🌾', description: 'Wheat, barley, rye' },
  { id: 'eggs', label: 'Eggs', icon: '🥚', description: 'Eggs & egg products' },
  { id: 'fish', label: 'Fish', icon: '🐟', description: 'Fish & seafood' },
  { id: 'nuts', label: 'Nuts', icon: '🥜', description: 'Tree nuts & peanuts' },
  { id: 'shellfish', label: 'Shellfish', icon: '🦐', description: 'Crustaceans & mollusks' },
  { id: 'soy', label: 'Soy', icon: '🫘', description: 'Soy products' }
];

export function FilterSheet({ isOpen, onClose, filters, onApplyFilters }: FilterSheetProps) {
  const [selectedDietary, setSelectedDietary] = useState<string[]>(filters.dietary);
  const [selectedAllergens, setSelectedAllergens] = useState<string[]>(filters.allergens);

  const handleDietaryToggle = (id: string) => {
    setSelectedDietary(prev => 
      prev.includes(id) 
        ? prev.filter(item => item !== id)
        : [...prev, id]
    );
  };

  const handleAllergenToggle = (id: string) => {
    setSelectedAllergens(prev => 
      prev.includes(id) 
        ? prev.filter(item => item !== id)
        : [...prev, id]
    );
  };

  const handleClearAll = () => {
    setSelectedDietary([]);
    setSelectedAllergens([]);
  };

  const handleApply = () => {
    onApplyFilters({
      dietary: selectedDietary,
      allergens: selectedAllergens
    });
    onClose();
  };

  if (!isOpen) return null;

  const totalFilters = selectedDietary.length + selectedAllergens.length;

  return (
    <>
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 animate-fade-in"
        onClick={onClose}
      />

      {/* Sheet */}
      <div className="fixed bottom-0 left-0 right-0 bg-white rounded-t-3xl z-50 max-h-[85vh] overflow-hidden flex flex-col animate-slide-up shadow-2xl">
        {/* Header */}
        <div className="relative px-6 pt-6 pb-4">
          <div className="flex items-center justify-between mb-2">
            <div>
              <h2 className="text-2xl">Filters</h2>
              {totalFilters > 0 && (
                <p className="text-sm text-gray-600 mt-1">
                  {totalFilters} filter{totalFilters !== 1 ? 's' : ''} selected
                </p>
              )}
            </div>
            <button 
              onClick={onClose}
              className="p-2 hover:bg-gray-100 rounded-full transition-colors"
            >
              <X className="w-6 h-6" />
            </button>
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto px-6 pb-6 space-y-6">
          {/* Dietary Preferences */}
          <div>
            <div className="flex items-center gap-2 mb-4">
              <Sparkles className="w-5 h-5 text-green-600" />
              <h3 className="text-lg">Dietary Preferences</h3>
            </div>
            <div className="grid grid-cols-1 gap-3">
              {dietaryOptions.map(option => {
                const isSelected = selectedDietary.includes(option.id);
                return (
                  <button
                    key={option.id}
                    onClick={() => handleDietaryToggle(option.id)}
                    className={`
                      relative p-4 rounded-xl border-2 transition-all text-left
                      ${isSelected 
                        ? 'border-green-500 bg-green-50 shadow-md scale-[1.02]' 
                        : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm'
                      }
                    `}
                  >
                    <div className="flex items-center gap-3">
                      <div className="text-2xl">{option.icon}</div>
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-0.5">
                          <span className={isSelected ? 'text-green-900' : 'text-gray-900'}>
                            {option.label}
                          </span>
                          {isSelected && (
                            <div className="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                              <svg className="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                              </svg>
                            </div>
                          )}
                        </div>
                        <p className="text-sm text-gray-600">{option.description}</p>
                      </div>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Allergens */}
          <div>
            <div className="flex items-center gap-2 mb-4">
              <svg className="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <h3 className="text-lg">Exclude Allergens</h3>
            </div>
            <p className="text-sm text-gray-600 mb-3">
              Select allergens to exclude from your search
            </p>
            <div className="grid grid-cols-2 gap-2">
              {allergenOptions.map(option => {
                const isSelected = selectedAllergens.includes(option.id);
                return (
                  <button
                    key={option.id}
                    onClick={() => handleAllergenToggle(option.id)}
                    className={`
                      relative p-3 rounded-xl border-2 transition-all text-left
                      ${isSelected 
                        ? 'border-red-500 bg-red-50 shadow-md' 
                        : 'border-gray-200 bg-white hover:border-gray-300'
                      }
                    `}
                  >
                    <div className="flex flex-col gap-1">
                      <div className="flex items-center gap-2">
                        <span className="text-xl">{option.icon}</span>
                        {isSelected && (
                          <div className="w-4 h-4 bg-red-500 rounded-full flex items-center justify-center ml-auto">
                            <X className="w-3 h-3 text-white" strokeWidth={3} />
                          </div>
                        )}
                      </div>
                      <div className={`text-sm ${isSelected ? 'text-red-900' : 'text-gray-900'}`}>
                        {option.label}
                      </div>
                      <div className="text-xs text-gray-500 line-clamp-1">
                        {option.description}
                      </div>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="border-t bg-gray-50 p-4 flex gap-3">
          <Button 
            onClick={handleClearAll} 
            variant="outline"
            className="flex-1 h-12 rounded-xl border-2 hover:bg-white"
            disabled={totalFilters === 0}
          >
            Clear All
          </Button>
          <Button 
            onClick={handleApply}
            className="flex-1 h-12 bg-gray-900 hover:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all"
          >
            <span>Apply Filters</span>
            {totalFilters > 0 && (
              <span className="ml-2 bg-white text-gray-900 rounded-full px-2.5 py-0.5 text-sm min-w-[24px] text-center">
                {totalFilters}
              </span>
            )}
          </Button>
        </div>
      </div>
    </>
  );
}