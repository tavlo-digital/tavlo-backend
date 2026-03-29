import { useState, useEffect } from 'react';
import { X, Plus, Minus, Star, Check, ShoppingBag, Sparkles, Info, ChevronDown } from 'lucide-react';
import { Button } from './ui/button';
import { ImageWithFallback } from './figma/ImageWithFallback';
import { api } from '../utils/api';
import { getReviewerName } from '../utils/guestNumber';
import { useLanguage } from '../contexts/LanguageContext';
import { getTranslatedField } from '../utils/translations';
import { analyzeReviews } from '../utils/aiHelpers';
import { AIReviewSummary } from './ai/AIComponents';

interface DishDetailsProps {
  dish: any;
  onClose: () => void;
  onAddToBasket: (dish: any, quantity: number, specialInstructions: string, modifiers?: any[]) => void;
  currencySymbol?: string;
  showNutrition?: boolean;
}

const dishImages: Record<string, string> = {
  'Truffle Mushroom Risotto': 'https://images.unsplash.com/photo-1476124369491-0674a3d82fab?w=800&h=600&fit=crop&q=80',
  'Grilled Salmon Fillet': 'https://images.unsplash.com/photo-1485921325833-c519f76c4927?w=800&h=600&fit=crop&q=80',
  'Classic Caesar Salad': 'https://images.unsplash.com/photo-1546793665-c74683f339c1?w=800&h=600&fit=crop&q=80',
  'Tiramisu': 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?w=800&h=600&fit=crop&q=80',
  'Margherita Pizza': 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=800&h=600&fit=crop&q=80',
  'Spaghetti Carbonara': 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=800&h=600&fit=crop&q=80',
  'Bruschetta al Pomodoro': 'https://images.unsplash.com/photo-1572695157366-5e585ab2b69f?w=800&h=600&fit=crop&q=80',
  'Panna Cotta': 'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=800&h=600&fit=crop&q=80',
  'Caprese Salad': 'https://images.unsplash.com/photo-1608897013039-887f21d8c804?w=800&h=600&fit=crop&q=80',
  'Prosecco DOC': 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&h=600&fit=crop&q=80',
  'default': 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&h=600&fit=crop&q=80'
};

export function DishDetails({ dish, onClose, onAddToBasket, currencySymbol = '€', showNutrition = true }: DishDetailsProps) {
  const { t, language } = useLanguage();
  const [quantity, setQuantity] = useState(1);
  const [specialInstructions, setSpecialInstructions] = useState('');
  const [reviews, setReviews] = useState<any[]>([]);
  const [loadingReviews, setLoadingReviews] = useState(false);
  
  // Add-ons state
  const [selectedModifiers, setSelectedModifiers] = useState<any[]>([]);
  const [selectedPaidAddons, setSelectedPaidAddons] = useState<any[]>([]);
  const [selectedFreeAddons, setSelectedFreeAddons] = useState<string[]>([]);
  const [selectedRemovals, setSelectedRemovals] = useState<string[]>([]);

  // Expansion states - Add-ons expanded by default
  const [expandedSections, setExpandedSections] = useState({
    addons: true,
    removeIngredients: false,
    nutritionAllergens: false,
    reviews: false
  });

  useEffect(() => {
    // Fetch reviews for this item
    const fetchReviews = async () => {
      setLoadingReviews(true);
      try {
        const itemId = dish.name.toLowerCase().replace(/\s+/g, '_');
        const itemReviews = await api.getItemReviews(itemId);
        console.log('Fetched reviews for', itemId, ':', itemReviews);
        setReviews(itemReviews);
      } catch (error) {
        console.error('Error fetching reviews:', error);
      } finally {
        setLoadingReviews(false);
      }
    };

    fetchReviews();
  }, [dish.name]);

  const toggleSection = (section: keyof typeof expandedSections) => {
    setExpandedSections(prev => ({
      ...prev,
      [section]: !prev[section]
    }));
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
    const basePrice = dish.price * quantity;
    const modifiersPrice = selectedModifiers.reduce((sum, m) => sum + (m.price * quantity), 0);
    const paidAddonsPrice = selectedPaidAddons.reduce((sum, a) => sum + (a.price * quantity), 0);
    return basePrice + modifiersPrice + paidAddonsPrice;
  };

  const handleAddToBasket = () => {
    const allModifiers = [
      ...selectedModifiers.map(m => ({ ...m, type: 'modifier' })),
      ...selectedPaidAddons.map(a => ({ ...a, type: 'paid-addon' })),
      ...selectedFreeAddons.map(a => ({ name: a, price: 0, type: 'free-addon' })),
      ...selectedRemovals.map(i => ({ name: i, price: 0, type: 'removal' }))
    ];
    
    onAddToBasket(dish, quantity, specialInstructions, allModifiers);
    onClose();
  };

  // Get translated content
  const displayName = getTranslatedField(dish, 'name', language);
  const displayDescription = getTranslatedField(dish, 'description', language);

  // Helper to get translated add-on name
  const getTranslatedAddonName = (addonName: string, addonType: 'paidAddons' | 'freeAddons' | 'removableItems', index: number) => {
    if (!dish.translations || !dish.translations[language]) {
      return addonName;
    }
    const translated = dish.translations[language][addonType]?.[index];
    return translated || addonName;
  };

  // Check if there are any add-ons to display
  const hasAddons = (dish.modifiers?.length > 0) || (dish.paidAddons?.length > 0) || (dish.freeAddons?.length > 0);
  const hasRemovableItems = dish.removableItems?.length > 0;
  const hasNutritionOrAllergens = (showNutrition && dish.nutrition) || (dish.allergens?.length > 0);
  const hasReviews = reviews && reviews.length > 0;

  return (
    <>
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 animate-fade-in"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="fixed inset-x-0 bottom-0 bg-white rounded-t-3xl z-50 max-h-[90vh] overflow-hidden flex flex-col animate-slide-up shadow-2xl">
        {/* Close button */}
        <button 
          onClick={onClose}
          className="absolute top-4 right-4 p-2 bg-white/90 backdrop-blur-sm rounded-full shadow-lg z-10 hover:bg-white transition-colors"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Scrollable content */}
        <div className="flex-1 overflow-y-auto">
          {/* Image */}
          <div className="relative aspect-[16/10] bg-gray-100">
            <ImageWithFallback
              src={dishImages[dish.name] || dishImages['default']}
              alt={dish.name}
              className="w-full h-full object-cover"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent" />
          </div>

          {/* Content */}
          <div className="p-6 space-y-4">
            {/* Header */}
            <div>
              <div className="flex items-start justify-between mb-2">
                <h2 className="text-2xl flex-1 pr-4">{displayName}</h2>
                <div className="text-2xl shrink-0">{currencySymbol}{dish.price.toFixed(2)}</div>
              </div>
              <p className="text-gray-600 mb-3 leading-relaxed">{displayDescription}</p>
              
              <div className="flex items-center gap-3 text-sm">
                <div className="flex items-center gap-1 text-orange-500">
                  <Star className="w-4 h-4 fill-orange-500" />
                  <span className="text-gray-900">{dish.rating}</span>
                  <span className="text-gray-400">({dish.reviewCount})</span>
                </div>
                {dish.orderedCount && (
                  <>
                    <span className="text-gray-400">•</span>
                    <span className="text-gray-600">{dish.orderedCount} orders this month</span>
                  </>
                )}
              </div>
            </div>

            {/* Dietary tags */}
            {dish.dietary?.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {dish.dietary.map((tag: string) => (
                  <span key={tag} className="px-3 py-1.5 bg-green-50 text-green-700 rounded-full text-sm capitalize border border-green-200">
                    {tag}
                  </span>
                ))}
              </div>
            )}

            {/* ═══════════════════════════════════════ */}
            {/* EXPANDABLE SECTION: Add-ons */}
            {/* ═══════════════════════════════════════ */}
            {hasAddons && (
              <div className="bg-white rounded-2xl border-2 border-gray-200 overflow-hidden">
                {/* Header - Always Visible */}
                <button
                  onClick={() => toggleSection('addons')}
                  className="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <span className="text-xl">✨</span>
                    <span className="font-semibold text-gray-900">Add-ons</span>
                    {(selectedModifiers.length > 0 || selectedPaidAddons.length > 0 || selectedFreeAddons.length > 0) && (
                      <span className="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded-full font-medium">
                        {selectedModifiers.length + selectedPaidAddons.length + selectedFreeAddons.length}
                      </span>
                    )}
                  </div>
                  <ChevronDown 
                    className={`w-5 h-5 text-gray-400 transition-transform duration-200 ${
                      expandedSections.addons ? 'rotate-180' : ''
                    }`}
                  />
                </button>

                {/* Content - Expandable */}
                {expandedSections.addons && (
                  <div className="px-5 pb-4 space-y-4 border-t border-gray-100">
                    {/* Modifiers/Options */}
                    {dish.modifiers && dish.modifiers.length > 0 && (
                      <div className="pt-4">
                        <h4 className="text-sm font-medium text-gray-700 mb-3">Choose Your Options</h4>
                        <div className="space-y-2">
                          {dish.modifiers.map((modifier: any) => {
                            const isSelected = selectedModifiers.some(m => m.name === modifier.name);
                            return (
                              <button
                                key={modifier.name}
                                onClick={() => toggleModifier(modifier)}
                                className={`
                                  w-full p-3 rounded-xl border-2 transition-all text-left flex items-center justify-between
                                  ${isSelected 
                                    ? 'border-orange-500 bg-orange-50' 
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'
                                  }
                                `}
                              >
                                <div className="flex items-center gap-3 flex-1">
                                  <div className={`
                                    w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all
                                    ${isSelected ? 'border-orange-500 bg-orange-500' : 'border-gray-300'}
                                  `}>
                                    {isSelected && <Check className="w-3 h-3 text-white" strokeWidth={3} />}
                                  </div>
                                  <span className={`text-sm ${isSelected ? 'text-gray-900 font-medium' : 'text-gray-700'}`}>
                                    {modifier.name}
                                  </span>
                                </div>
                                <span className={`text-sm font-medium ${isSelected ? 'text-orange-600' : 'text-gray-600'}`}>
                                  +{currencySymbol}{modifier.price.toFixed(2)}
                                </span>
                              </button>
                            );
                          })}
                        </div>
                      </div>
                    )}

                    {/* Paid Add-ons */}
                    {dish.paidAddons && dish.paidAddons.length > 0 && (
                      <div className="pt-2">
                        <h4 className="text-sm font-medium text-gray-700 mb-3">Extra Add-ons</h4>
                        <div className="space-y-2">
                          {dish.paidAddons.map((addon: any, index: number) => {
                            const isSelected = selectedPaidAddons.some(a => a.name === addon.name);
                            const translatedName = getTranslatedAddonName(addon.name, 'paidAddons', index);
                            return (
                              <button
                                key={addon.name}
                                onClick={() => togglePaidAddon(addon)}
                                className={`
                                  w-full p-3 rounded-xl border-2 transition-all text-left flex items-center justify-between
                                  ${isSelected 
                                    ? 'border-blue-500 bg-blue-50' 
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'
                                  }
                                `}
                              >
                                <div className="flex items-center gap-3">
                                  <div className={`
                                    w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all
                                    ${isSelected ? 'border-blue-500 bg-blue-500' : 'border-gray-300'}
                                  `}>
                                    {isSelected && <Check className="w-3 h-3 text-white" strokeWidth={3} />}
                                  </div>
                                  <span className={`text-sm ${isSelected ? 'text-gray-900 font-medium' : 'text-gray-700'}`}>
                                    {translatedName}
                                  </span>
                                </div>
                                <span className={`text-sm font-medium ${isSelected ? 'text-blue-600' : 'text-gray-600'}`}>
                                  +{currencySymbol}{addon.price.toFixed(2)}
                                </span>
                              </button>
                            );
                          })}
                        </div>
                      </div>
                    )}

                    {/* Free Add-ons */}
                    {dish.freeAddons && dish.freeAddons.length > 0 && (
                      <div className="pt-2">
                        <h4 className="text-sm font-medium text-gray-700 mb-3">Free Add-ons</h4>
                        <div className="grid grid-cols-2 gap-2">
                          {dish.freeAddons.map((addon: string, index: number) => {
                            const isSelected = selectedFreeAddons.includes(addon);
                            const translatedName = getTranslatedAddonName(addon, 'freeAddons', index);
                            return (
                              <button
                                key={addon}
                                onClick={() => toggleFreeAddon(addon)}
                                className={`
                                  p-3 rounded-xl border-2 transition-all text-left flex items-center gap-2
                                  ${isSelected 
                                    ? 'border-green-500 bg-green-50' 
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'
                                  }
                                `}
                              >
                                <div className={`
                                  w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all
                                  ${isSelected ? 'border-green-500 bg-green-500' : 'border-gray-300'}
                                `}>
                                  {isSelected && <Check className="w-3 h-3 text-white" strokeWidth={3} />}
                                </div>
                                <span className={`text-sm ${isSelected ? 'text-gray-900 font-medium' : 'text-gray-700'}`}>
                                  {translatedName}
                                </span>
                              </button>
                            );
                          })}
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}

            {/* ═══════════════════════════════════════ */}
            {/* EXPANDABLE SECTION: Remove Ingredients */}
            {/* ═══════════════════════════════════════ */}
            {hasRemovableItems && (
              <div className="bg-white rounded-2xl border-2 border-gray-200 overflow-hidden">
                {/* Header - Always Visible */}
                <button
                  onClick={() => toggleSection('removeIngredients')}
                  className="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <span className="text-xl">🚫</span>
                    <span className="font-semibold text-gray-900">Remove Ingredients</span>
                    {selectedRemovals.length > 0 && (
                      <span className="px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full font-medium">
                        {selectedRemovals.length}
                      </span>
                    )}
                  </div>
                  <ChevronDown 
                    className={`w-5 h-5 text-gray-400 transition-transform duration-200 ${
                      expandedSections.removeIngredients ? 'rotate-180' : ''
                    }`}
                  />
                </button>

                {/* Content - Expandable */}
                {expandedSections.removeIngredients && (
                  <div className="px-5 pb-4 pt-4 border-t border-gray-100">
                    <div className="grid grid-cols-2 gap-2">
                      {dish.removableItems.map((item: string, index: number) => {
                        const isSelected = selectedRemovals.includes(item);
                        const translatedName = getTranslatedAddonName(item, 'removableItems', index);
                        return (
                          <button
                            key={item}
                            onClick={() => toggleRemoval(item)}
                            className={`
                              p-3 rounded-xl border-2 transition-all text-left flex items-center gap-2
                              ${isSelected 
                                ? 'border-red-500 bg-red-50' 
                                : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'
                              }
                            `}
                          >
                            <div className={`
                              w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition-all
                              ${isSelected ? 'border-red-500 bg-red-500' : 'border-gray-300'}
                            `}>
                              {isSelected && <X className="w-3 h-3 text-white" strokeWidth={3} />}
                            </div>
                            <span className={`text-sm ${isSelected ? 'text-gray-900 font-medium' : 'text-gray-700'}`}>
                              No {translatedName}
                            </span>
                          </button>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* ═══════════════════════════════════════ */}
            {/* EXPANDABLE SECTION: Nutrition & Allergens */}
            {/* ═══════════════════════════════════════ */}
            {hasNutritionOrAllergens && (
              <div className="bg-white rounded-2xl border-2 border-gray-200 overflow-hidden">
                {/* Header - Always Visible */}
                <button
                  onClick={() => toggleSection('nutritionAllergens')}
                  className="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <span className="text-xl">🍔</span>
                    <span className="font-semibold text-gray-900">Nutrition & Allergens</span>
                  </div>
                  <ChevronDown 
                    className={`w-5 h-5 text-gray-400 transition-transform duration-200 ${
                      expandedSections.nutritionAllergens ? 'rotate-180' : ''
                    }`}
                  />
                </button>

                {/* Content - Expandable */}
                {expandedSections.nutritionAllergens && (
                  <div className="px-5 pb-4 space-y-4 border-t border-gray-100">
                    {/* Nutritional Information */}
                    {showNutrition && dish.nutrition && (
                      <div className="pt-4">
                        <div className="flex items-center gap-2 mb-3">
                          <h4 className="text-sm font-medium text-gray-700">{t('nutritional_information', 'Nutritional Information')}</h4>
                          <div className="group relative">
                            <Info className="w-4 h-4 text-gray-400 cursor-help" />
                            <div className="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 hidden group-hover:block w-64 bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-lg z-10">
                              Estimated values based on standard ingredients per serving.
                              <div className="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
                            </div>
                          </div>
                        </div>
                        <div className="grid grid-cols-4 gap-3 bg-gray-50 rounded-xl p-4">
                          <div className="text-center">
                            <div className="text-gray-900 mb-1 font-semibold">{dish.nutrition.calories || dish.calories}</div>
                            <div className="text-xs text-gray-600">{t('calories', 'Calories')}</div>
                          </div>
                          <div className="text-center">
                            <div className="text-gray-900 mb-1 font-semibold">{dish.nutrition.protein}g</div>
                            <div className="text-xs text-gray-600">{t('protein', 'Protein')}</div>
                          </div>
                          <div className="text-center">
                            <div className="text-gray-900 mb-1 font-semibold">{dish.nutrition.carbs}g</div>
                            <div className="text-xs text-gray-600">{t('carbs', 'Carbs')}</div>
                          </div>
                          <div className="text-center">
                            <div className="text-gray-900 mb-1 font-semibold">{dish.nutrition.fat}g</div>
                            <div className="text-xs text-gray-600">{t('fat', 'Fat')}</div>
                          </div>
                        </div>
                        <p className="text-xs text-gray-500 mt-2">
                          Nutrition values are based on the standard recipe.
                        </p>
                      </div>
                    )}

                    {/* Allergens */}
                    {dish.allergens?.length > 0 && (
                      <div>
                        <h4 className="text-sm font-medium text-gray-700 mb-3">{t('allergens', 'Allergens')}</h4>
                        <div className="flex flex-wrap gap-2">
                          {dish.allergens.map((allergen: string) => (
                            <span key={allergen} className="px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-sm capitalize border border-red-200 font-medium">
                              {allergen}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}

            {/* ═══════════════════════════════════════ */}
            {/* EXPANDABLE SECTION: Reviews */}
            {/* ═══════════════════════════════════════ */}
            {hasReviews && (
              <div className="bg-white rounded-2xl border-2 border-gray-200 overflow-hidden">
                {/* Header - Always Visible */}
                <button
                  onClick={() => toggleSection('reviews')}
                  className="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center gap-3">
                    <span className="text-xl">⭐</span>
                    <span className="font-semibold text-gray-900">Reviews</span>
                    <span className="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full font-medium">
                      {reviews.length}
                    </span>
                  </div>
                  <ChevronDown 
                    className={`w-5 h-5 text-gray-400 transition-transform duration-200 ${
                      expandedSections.reviews ? 'rotate-180' : ''
                    }`}
                  />
                </button>

                {/* Content - Expandable */}
                {expandedSections.reviews && (
                  <div className="px-5 pb-4 space-y-4 border-t border-gray-100">
                    {/* AI Review Summary */}
                    <div className="pt-4 bg-gradient-to-br from-purple-50 to-purple-100/50 rounded-2xl p-4 border border-purple-200/50">
                      <div className="flex items-center gap-2 mb-3">
                        <Sparkles className="w-5 h-5 text-purple-600" />
                        <h4 className="text-sm font-semibold text-purple-900">AI Review Summary</h4>
                      </div>
                      {(() => {
                        const reviewAnalysis = analyzeReviews(reviews.map(r => ({
                          id: r.id,
                          customerName: r.customerName || 'Anonymous',
                          rating: r.rating,
                          comment: r.text,
                          date: r.createdAt
                        })));
                        
                        return (
                          <AIReviewSummary
                            sentiment={reviewAnalysis.sentiment}
                            summary={reviewAnalysis.summary}
                            positivePoints={reviewAnalysis.positivePoints}
                            negativePoints={reviewAnalysis.negativePoints}
                            totalReviews={reviewAnalysis.totalReviews}
                            confidence={reviewAnalysis.confidence}
                          />
                        );
                      })()}
                    </div>

                    {/* Individual Reviews */}
                    <div className="space-y-4">
                      {reviews.slice(0, 5).map((review: any, index: number) => (
                        <div key={index} className="border-b pb-4 last:border-0 last:pb-0">
                          <div className="flex items-start justify-between mb-2">
                            <div>
                              <div className="font-medium text-gray-900">{getReviewerName(review.customerName, review.isGuest, review.customerId)}</div>
                              <div className="text-sm text-gray-500">
                                {new Date(review.createdAt).toLocaleDateString('de-DE')}
                              </div>
                            </div>
                            <div className="flex items-center gap-1 text-orange-500">
                              {[...Array(5)].map((_, i) => (
                                <Star
                                  key={i}
                                  className={`w-4 h-4 ${
                                    i < review.rating ? 'fill-orange-500' : 'fill-gray-200 text-gray-200'
                                  }`}
                                />
                              ))}
                            </div>
                          </div>
                          {review.text && <p className="text-gray-700 text-sm leading-relaxed">{review.text}</p>}
                          {review.photos && review.photos.length > 0 && (
                            <div className="flex gap-2 mt-2 overflow-x-auto">
                              {review.photos.slice(0, 3).map((photo: string, photoIndex: number) => (
                                <img
                                  key={photoIndex}
                                  src={photo}
                                  alt={`Review photo ${photoIndex + 1}`}
                                  className="w-16 h-16 object-cover rounded-lg"
                                />
                              ))}
                              {review.photos.length > 3 && (
                                <div className="w-16 h-16 bg-gray-100 rounded-lg flex items-center justify-center text-sm text-gray-600">
                                  +{review.photos.length - 3}
                                </div>
                              )}
                            </div>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Special Instructions - NOT EXPANDABLE */}
            <div>
              <label className="block mb-2 font-medium text-gray-900">{t('special_instructions', 'Special instructions (optional)')}</label>
              <textarea
                value={specialInstructions}
                onChange={(e) => setSpecialInstructions(e.target.value)}
                placeholder={t('special_instructions', 'e.g., No onions, extra spicy, well done...')}
                className="w-full px-4 py-3 border-2 border-gray-200 rounded-xl resize-none focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all"
                rows={3}
              />
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="border-t bg-white p-4 shadow-2xl">
          <div className="flex items-center justify-between mb-4">
            <span className="text-lg font-semibold">{t('quantity', 'Quantity')}</span>
            <div className="flex items-center gap-3">
              <button
                onClick={() => setQuantity(Math.max(1, quantity - 1))}
                className="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors active:scale-95"
              >
                <Minus className="w-4 h-4" />
              </button>
              <span className="text-xl font-bold w-8 text-center">{quantity}</span>
              <button
                onClick={() => setQuantity(quantity + 1)}
                className="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors active:scale-95"
              >
                <Plus className="w-4 h-4" />
              </button>
            </div>
          </div>
          
          <Button 
            onClick={handleAddToBasket}
            className="w-full h-14 bg-gray-900 hover:bg-gray-800 text-lg rounded-2xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2"
          >
            <ShoppingBag className="w-5 h-5" />
            <span>Add to order</span>
            <span>•</span>
            <span>{currencySymbol}{calculateTotal().toFixed(2)}</span>
          </Button>
        </div>
      </div>
    </>
  );
}
