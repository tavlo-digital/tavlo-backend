import { useState, useEffect, useRef } from 'react';
import { 
  Search, ShoppingBag, Clock, History, Phone, User, X, 
  ChevronDown, ChevronUp, Star, Sparkles, TrendingUp, 
  ChevronRight, MapPin, ThumbsUp, Leaf, Zap, SlidersHorizontal, Eye, Info, MoreVertical
} from 'lucide-react';
import { getTranslatedField, getTranslation } from '../utils/translations';
import { isRestaurantOpen, getNextOpeningTime, getMinutesUntilClose, getCurrentDayHours } from '../utils/businessHours';
import { AISuggestionChip } from './ai/AIComponents';
import { useLanguage } from '../contexts/LanguageContext';
import { useAccessibility } from '../contexts/AccessibilityContext';
import { Input } from './ui/input';
import { DishCard } from './DishCard';
import { FilterSheet } from './FilterSheet';
import { RestaurantReviewsModal } from './RestaurantReviewsModal';
import { LanguageSwitcher } from './LanguageSwitcher';
import { AccessibilityMenu } from './AccessibilityMenu';
import { BottomSystemBar } from './BottomSystemBar';
import { getThemeStyles, applyThemeToElement } from '../utils/themeUtils';

interface MenuListProps {
  restaurantName: string;
  menu: any;
  onDishClick: (item: any) => void;
  onCallWaiter: () => void;
  basketCount?: number;
  pendingOrdersCount?: number;
  activeOrdersCount?: number;
  onViewBasket?: () => void;
  onViewHistory?: () => void;
  onViewActiveOrders?: () => void;
  onViewProfile?: () => void;
  onQuickAdd?: (item: any, quantity: number) => void;
  vendorSettings?: any;
  showNutrition?: boolean;
  onToggleNutrition?: () => void;
  sessionPin?: string; // 4-digit session PIN
}

export function MenuList({ restaurantName, menu, onDishClick, onCallWaiter, basketCount = 0, pendingOrdersCount = 0, activeOrdersCount = 0, onViewBasket, onViewHistory, onViewActiveOrders, onViewProfile, onQuickAdd, vendorSettings, showNutrition, onToggleNutrition, sessionPin }: MenuListProps) {
  const { t, language } = useLanguage();
  const { settings: accessibilitySettings } = useAccessibility();
  const [showAccessibilityMenu, setShowAccessibilityMenu] = useState(false);
  
  // Get theme colors from vendor settings or use defaults
  const themeColors = {
    primary: vendorSettings?.primaryColor || '#1a1a1a',
    accent: vendorSettings?.accentColor || '#f59e0b',
    theme: vendorSettings?.menuTheme || 'classic'
  };
  
  // Get theme styles
  const themeStyles = getThemeStyles({
    menuTheme: themeColors.theme as any,
    primaryColor: themeColors.primary,
    accentColor: themeColors.accent,
    menuLayout: vendorSettings?.menuLayout || 'grid'
  });
  
  // Get currency symbol from settings
  const getCurrencySymbol = () => {
    if (!vendorSettings) return '€';
    switch (vendorSettings.currency) {
      case 'EUR': return '€';
      case 'USD': return '$';
      case 'GBP': return '£';
      case 'CHF': return 'Fr.';
      default: return '€';
    }
  };
  const [searchQuery, setSearchQuery] = useState('');
  const [activeCategory, setActiveCategory] = useState('all');
  const [filterOpen, setFilterOpen] = useState(false);
  const [showRestaurantInfo, setShowRestaurantInfo] = useState(false);
  const [showReviewsModal, setShowReviewsModal] = useState(false);
  const [showOverflowMenu, setShowOverflowMenu] = useState(false);
  const [filters, setFilters] = useState({
    dietary: [] as string[],
    allergens: [] as string[]
  });
  const [aiFilter, setAiFilter] = useState<'popular' | 'vegetarian' | 'quick' | null>(null);

  const filteredItems = menu?.items?.filter((item: any) => {
    // Search filter
    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      const translatedName = getTranslatedField(item, 'name', language);
      const translatedDesc = getTranslatedField(item, 'description', language);
      
      if (!translatedName.toLowerCase().includes(query) &&
          !translatedDesc?.toLowerCase().includes(query)) {
        return false;
      }
    }

    // AI Filter
    if (aiFilter === 'popular') {
      if ((item.orders || 0) < 15 && !item.specialTags?.includes('recommended')) {
        return false;
      }
    } else if (aiFilter === 'vegetarian') {
      const isVeg = item.dietaryPreference === 'vegetarian' || 
                    item.dietaryPreference === 'vegan' ||
                    item.dietary?.includes('Vegetarian') ||
                    item.dietary?.includes('Vegan');
      if (!isVeg) return false;
    } else if (aiFilter === 'quick') {
      const isQuick = item.category === 'appetizers' || 
                      item.category === 'salads' || 
                      item.category === 'drinks';
      if (!isQuick) return false;
    }

    // Category filter
    if (activeCategory !== 'all' && item.category !== activeCategory) {
      return false;
    }

    // Dietary filter
    if (filters.dietary.length > 0) {
      const hasMatchingDietary = filters.dietary.some(diet => 
        item.dietary?.includes(diet) || item.tags?.includes(diet)
      );
      if (!hasMatchingDietary) {
        return false;
      }
    }

    // Allergen filter (exclude items with these allergens)
    if (filters.allergens.length > 0) {
      const hasExcludedAllergen = filters.allergens.some(allergen =>
        item.allergens?.includes(allergen)
      );
      if (hasExcludedAllergen) {
        return false;
      }
    }

    return true;
  }) || [];

  const handleApplyFilters = (newFilters: { dietary: string[]; allergens: string[] }) => {
    setFilters(newFilters);
  };

  const categories = [
    { id: 'all', label: t('all'), icon: '🍽️' },
    { id: 'recommended', label: t('recommended'), icon: '⭐' },
    { id: 'appetizers', label: t('appetizers'), icon: '🥗' },
    { id: 'mains', label: t('mains'), icon: '🍝' },
    { id: 'salads', label: t('salads'), icon: '🥬' },
    { id: 'desserts', label: t('desserts'), icon: '🍰' },
    { id: 'drinks', label: t('drinks'), icon: '🍷' }
  ];

  // Get featured/recommended items
  const featuredItems = menu?.items?.filter((item: any) => 
    item.badges?.includes('most-ordered') || item.category === 'recommended'
  ).slice(0, 2) || [];

  // Check restaurant hours
  const isOpen = isRestaurantOpen(vendorSettings?.businessHours);
  const minutesUntilClose = getMinutesUntilClose(vendorSettings?.businessHours);
  const nextOpening = getNextOpeningTime(vendorSettings?.businessHours);
  const closingSoon = minutesUntilClose !== null && minutesUntilClose <= 30;

  return (
    <div className="min-h-screen bg-gray-50 pb-24">
      {/* Enhanced Header with Restaurant Info */}
      <div className="bg-gradient-to-b from-white to-gray-50 border-b sticky top-0 z-20 shadow-sm">
        <div className="max-w-4xl mx-auto">
          {/* Restaurant Header */}
          <div className="px-4 pt-4 pb-3">
            <div className="flex items-start gap-3 mb-3">
              {/* Restaurant Logo */}
              <div className="shrink-0">
                {vendorSettings?.logo ? (
                  <img 
                    src={vendorSettings.logo} 
                    alt={restaurantName}
                    className="h-14 w-14 object-cover rounded-xl border border-gray-200"
                  />
                ) : (
                  <div className="h-14 w-14 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl flex items-center justify-center">
                    <span className="text-2xl">🍽️</span>
                  </div>
                )}
              </div>
              
              {/* Restaurant Info */}
              <div className="flex-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                  {/* Mobile: Simple layout - Name + Overflow */}
                  {/* Desktop: Full layout - Name + Rating + Actions */}
                  <div className="flex-1 min-w-0">
                    <h1 className="text-xl truncate mb-1">{restaurantName}</h1>
                    
                    {/* Rating & Dish Count - Desktop Only */}
                    <button
                      onClick={() => setShowReviewsModal(true)}
                      className="hidden md:flex items-center gap-2 text-sm text-gray-600 mb-1 hover:text-orange-600 transition-colors group"
                    >
                      <div className="flex items-center gap-1">
                        <Star className="w-4 h-4 fill-orange-500 text-orange-500 group-hover:scale-110 transition-transform" />
                        <span className="text-gray-900 group-hover:text-orange-600">4.8</span>
                        <span className="text-gray-400">(120+)</span>
                      </div>
                      <span className="text-gray-400">•</span>
                      <span>{menu?.items?.length || 0} {t('dishes')}</span>
                    </button>
                  </div>
                  
                  {/* Right Side Controls */}
                  <div className="flex items-start gap-2">
                    {/* Desktop: Show Accessibility + Language + Profile */}
                    {/* Mobile: Show Overflow Menu Only */}
                    <div className="flex items-center gap-2">
                      {/* Accessibility - Desktop Only */}
                      <button 
                        onClick={() => setShowAccessibilityMenu(true)}
                        className="hidden md:block p-2 hover:bg-gray-100 rounded-lg transition-colors shrink-0"
                        style={
                          (accessibilitySettings.largeText || accessibilitySettings.highContrast || accessibilitySettings.simpleLayout) 
                            ? { backgroundColor: `${themeColors.accent}20`, color: themeColors.accent }
                            : {}
                        }
                        title="Accessibility Options"
                      >
                        <Eye className="w-5 h-5" />
                      </button>

                      {/* Language Switcher - Desktop Only */}
                      <div className="hidden md:block">
                        <LanguageSwitcher />
                      </div>
                      
                      {/* Profile Button - Desktop Only */}
                      <button 
                        onClick={onViewProfile}
                        className="hidden md:block p-2 hover:bg-gray-100 rounded-lg transition-colors shrink-0"
                      >
                        <User className="w-5 h-5 text-gray-700" />
                      </button>

                      {/* Overflow Menu Button - Mobile Only */}
                      <div className="md:hidden relative">
                        <button 
                          onClick={() => setShowOverflowMenu(!showOverflowMenu)}
                          className="p-2 hover:bg-gray-100 rounded-lg transition-colors shrink-0"
                        >
                          <MoreVertical className="w-5 h-5 text-gray-700" />
                        </button>

                        {/* Overflow Menu Dropdown */}
                        {showOverflowMenu && (
                          <>
                            {/* Backdrop */}
                            <div 
                              className="fixed inset-0 z-30" 
                              onClick={() => setShowOverflowMenu(false)}
                            />
                            {/* Menu */}
                            <div className="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden z-40">
                              <button
                                onClick={() => {
                                  onViewProfile();
                                  setShowOverflowMenu(false);
                                }}
                                className="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors flex items-center gap-3"
                              >
                                <User className="w-5 h-5 text-gray-700" />
                                <span className="text-sm text-gray-900">Profile & Account</span>
                              </button>
                              <div className="border-t border-gray-100" />
                              <div className="px-4 py-3">
                                <div className="text-xs text-gray-500 mb-2">Language</div>
                                <LanguageSwitcher />
                              </div>
                            </div>
                          </>
                        )}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Quick Info Button */}
                <button
                  onClick={() => setShowRestaurantInfo(!showRestaurantInfo)}
                  className="flex items-center gap-1 text-sm transition-colors"
                  style={{ color: themeColors.accent }}
                >
                  <span>{t('view_restaurant_info')}</span>
                  <ChevronRight className={`w-4 h-4 transition-transform ${showRestaurantInfo ? 'rotate-90' : ''}`} />
                </button>
              </div>
            </div>

            {/* Expandable Restaurant Info */}
            {showRestaurantInfo && (
              <div className="bg-gradient-to-br from-orange-50 to-orange-100/50 rounded-xl p-4 mt-3 space-y-3 border border-orange-200/50">
                {/* Rating & Dish Count - Mobile Only (inside restaurant info) */}
                <button
                  onClick={() => setShowReviewsModal(true)}
                  className="md:hidden flex items-center gap-2 text-sm text-gray-600 hover:text-orange-600 transition-colors group w-full"
                >
                  <div className="flex items-center gap-1">
                    <Star className="w-4 h-4 fill-orange-500 text-orange-500 group-hover:scale-110 transition-transform" />
                    <span className="text-gray-900 group-hover:text-orange-600">4.8</span>
                    <span className="text-gray-400">(120+)</span>
                  </div>
                  <span className="text-gray-400">•</span>
                  <span>{menu?.items?.length || 0} {t('dishes')}</span>
                </button>
                
                {vendorSettings?.description && (
                  <p className="text-sm text-gray-700">{vendorSettings.description}</p>
                )}
                
                <div className="grid grid-cols-1 gap-2 text-sm">
                  {vendorSettings?.address && (
                    <div className="flex items-start gap-2">
                      <MapPin className="w-4 h-4 text-orange-600 mt-0.5 shrink-0" />
                      <span className="text-gray-700">{vendorSettings.address}</span>
                    </div>
                  )}
                  
                  {vendorSettings?.phone && (
                    <div className="flex items-center gap-2">
                      <Phone className="w-4 h-4 text-orange-600 shrink-0" />
                      <a href={`tel:${vendorSettings.phone}`} className="text-gray-700 hover:text-orange-600">
                        {vendorSettings.phone}
                      </a>
                    </div>
                  )}
                  
                  <div className="flex items-center gap-2">
                    <Clock className="w-4 h-4 text-orange-600 shrink-0" />
                    <span className="text-gray-700">Today: {getCurrentDayHours(vendorSettings)}</span>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Search Bar */}
          <div className="px-4 pb-3">
            {/* Layer 2: Search + Filter (full width on mobile) */}
            <div className="flex gap-2">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <Input
                  type="text"
                  placeholder={t('search_dishes')}
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="pl-10 bg-white border-gray-200 h-11 rounded-xl"
                />
              </div>
              <button 
                className="p-3 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors relative" 
                onClick={() => setFilterOpen(true)}
              >
                <SlidersHorizontal className="w-5 h-5 text-gray-700" />
                {(filters.dietary.length + filters.allergens.length) > 0 && (
                  <span className="absolute -top-1 -right-1 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center" style={{ backgroundColor: themeColors.accent }}>
                    {filters.dietary.length + filters.allergens.length}
                  </span>
                )}
              </button>
              {/* Nutrition Toggle - Desktop Only */}
              {onToggleNutrition && (
                <div className="group relative hidden md:block">
                  <button
                    onClick={onToggleNutrition}
                    className={`flex items-center gap-1.5 px-3 h-11 rounded-xl text-sm transition-colors ${
                      showNutrition 
                        ? 'bg-gray-900 text-white' 
                        : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'
                    }`}
                  >
                    <span className="text-xs">🥗</span>
                    <span>Nutrition info</span>
                    <Info className="w-3.5 h-3.5 opacity-50" />
                  </button>
                  <div className="absolute right-0 top-full mt-2 hidden group-hover:block w-56 bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-lg z-10">
                    When enabled, calories are shown on dishes.<br />
                    Tap a dish to see full nutritional details.
                    <div className="absolute bottom-full right-6 -mb-1 border-4 border-transparent border-b-gray-900"></div>
                  </div>
                </div>
              )}
            </div>

            {/* Layer 3: Secondary Chip Row - Mobile Only (Nutrition + Accessibility grouped) */}
            {onToggleNutrition && (
              <div className="md:hidden mt-2 flex gap-2 overflow-x-auto scrollbar-hide">
                {/* Accessibility + Nutrition grouped container */}
                <div className="flex items-center gap-2 bg-gray-100/50 rounded-xl p-1">
                  {/* Accessibility Icon */}
                  <button 
                    onClick={() => setShowAccessibilityMenu(true)}
                    className={`flex items-center gap-1.5 px-3 h-9 rounded-lg text-sm transition-colors ${
                      (accessibilitySettings.largeText || accessibilitySettings.highContrast || accessibilitySettings.simpleLayout)
                        ? 'text-white'
                        : 'bg-white text-gray-700 hover:bg-gray-50'
                    }`}
                    style={
                      (accessibilitySettings.largeText || accessibilitySettings.highContrast || accessibilitySettings.simpleLayout)
                        ? { backgroundColor: themeColors.accent }
                        : {}
                    }
                    title="Accessibility"
                  >
                    <Eye className="w-4 h-4" />
                    <span className="text-xs">Accessibility</span>
                  </button>

                  {/* Subtle divider */}
                  <div className="w-px h-6 bg-gray-300"></div>

                  {/* Nutrition Toggle */}
                  <button
                    onClick={onToggleNutrition}
                    className={`flex items-center gap-1.5 px-3 h-9 rounded-lg text-sm transition-colors ${
                      showNutrition 
                        ? 'bg-gray-900 text-white' 
                        : 'bg-white text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    <span className="text-xs">🥗</span>
                    <span className="text-xs">Nutrition info</span>
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* Category tabs */}
          <div className="flex gap-2 overflow-x-auto scrollbar-hide px-4 pb-3">
            {categories.map((cat: any) => (
              <button
                key={cat.id}
                onClick={() => setActiveCategory(cat.id)}
                className={`px-4 py-2.5 rounded-xl whitespace-nowrap transition-all flex items-center gap-2 ${
                  activeCategory === cat.id
                    ? 'bg-gray-900 text-white shadow-md scale-105'
                    : 'bg-white text-gray-700 border border-gray-200 hover:border-gray-300 hover:shadow-sm'
                }`}
              >
                <span className="text-base">{cat.icon}</span>
                <span className="text-sm">{cat.label}</span>
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-4xl mx-auto px-4">
        {/* Active Order Banner */}
        {activeOrdersCount > 0 && (
          <div className="mt-4">
            <button
              onClick={onViewActiveOrders}
              className="w-full text-white p-4 rounded-2xl shadow-lg flex items-center justify-between transition-all hover:shadow-xl"
              style={{
                background: `linear-gradient(to right, ${themeColors.accent}, ${themeColors.accent}dd)`
              }}
            >
              <div className="flex items-center gap-3">
                <div className="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm">
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div className="text-left">
                  <div className="text-sm opacity-90 mb-0.5">
                    {activeOrdersCount === 1 
                      ? t('your_order_being_prepared', 'Your order is being prepared') 
                      : `${activeOrdersCount} ${t('orders_in_progress', 'orders in progress')}`}
                  </div>
                  <div className="flex items-center gap-1">
                    <span>{t('track_order_status', 'Track Order Status')}</span>
                    <Sparkles className="w-4 h-4" />
                  </div>
                </div>
              </div>
              <ChevronRight className="w-6 h-6" />
            </button>
          </div>
        )}

        {/* Business Hours Status Banner */}
        {!isOpen && vendorSettings?.businessHours && (
          <div className="mt-4">
            <div className="bg-gradient-to-br from-gray-100 to-gray-200 border border-gray-300 p-4 rounded-2xl shadow-sm">
              <div className="flex items-start gap-3">
                <div className="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center shrink-0">
                  <Clock className="w-6 h-6 text-gray-700" />
                </div>
                <div className="flex-1">
                  <h3 className="text-gray-900 mb-1">
                    {t('restaurant_closed', 'Restaurant Currently Closed')}
                  </h3>
                  <p className="text-sm text-gray-600 mb-2">
                    {t('currently_closed', 'We\'re currently closed. Please visit us during our opening hours.')}
                  </p>
                  {nextOpening && (
                    <p className="text-sm text-gray-800">
                      {t('next_opening', 'Next opening: {time}').replace('{time}', nextOpening)}
                    </p>
                  )}
                  <p className="text-xs text-gray-500 mt-2">
                    {t('browse_menu_only', 'You can browse the menu, but ordering is not available at this time.')}
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}
        
        {/* Closing Soon Warning */}
        {isOpen && closingSoon && vendorSettings?.businessHours && (
          <div className="mt-4">
            <div className="bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-300 p-4 rounded-2xl shadow-sm">
              <div className="flex items-start gap-3">
                <div className="w-12 h-12 bg-orange-200 rounded-full flex items-center justify-center shrink-0">
                  <Clock className="w-6 h-6 text-orange-700" />
                </div>
                <div className="flex-1">
                  <h3 className="text-orange-900 mb-1">
                    {t('closes_soon', 'Closing soon ({minutes} min)').replace('{minutes}', minutesUntilClose?.toString() || '')}
                  </h3>
                  <p className="text-sm text-orange-700">
                    {t('browse_menu_only', 'You can browse the menu, but ordering is not available at this time.')}
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}
        
        {/* AI Smart Discovery - Suggestion Chips */}
        {activeCategory === 'all' && !searchQuery && (
          <div className="mt-4">
            <div className="flex items-center gap-2 mb-3">
              <Sparkles className="w-5 h-5 text-purple-600" />
              <span className="text-sm text-gray-600">Quick Discovery</span>
            </div>
            <div className="flex flex-wrap gap-2">
              <AISuggestionChip
                label="Most Popular"
                icon={<ThumbsUp className="w-3.5 h-3.5" />}
                active={aiFilter === 'popular'}
                onClick={() => {
                  setAiFilter(aiFilter === 'popular' ? null : 'popular');
                  setActiveCategory('all');
                }}
              />
              <AISuggestionChip
                label="Vegetarian"
                icon={<Leaf className="w-3.5 h-3.5" />}
                active={aiFilter === 'vegetarian'}
                onClick={() => {
                  setAiFilter(aiFilter === 'vegetarian' ? null : 'vegetarian');
                  setActiveCategory('all');
                }}
              />
              <AISuggestionChip
                label="Quick Dishes"
                icon={<Zap className="w-3.5 h-3.5" />}
                active={aiFilter === 'quick'}
                onClick={() => {
                  setAiFilter(aiFilter === 'quick' ? null : 'quick');
                  setActiveCategory('all');
                }}
              />
            </div>
          </div>
        )}
        
        {/* Featured Items Section */}
        {featuredItems.length > 0 && activeCategory === 'all' && !searchQuery && (
          <div className="mt-4">
            <div className="flex items-center gap-2 mb-3">
              <Sparkles className="w-5 h-5 text-orange-600" />
              <h2 className="text-lg">{t('popular_right_now', 'Popular Right Now')}</h2>
            </div>
            <div className="grid grid-cols-1 gap-3 mb-4">
              {featuredItems.map((item: any) => (
                <button
                  key={item.id}
                  onClick={() => onDishClick(item)}
                  className="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all text-left flex gap-3 p-3 border border-gray-100"
                >
                  <div className="relative w-24 h-24 bg-gray-100 rounded-xl overflow-hidden shrink-0">
                    <img
                      src={item.image || 'https://images.unsplash.com/photo-1600891964599-f61ba0e24092?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxyZXN0YXVyYW50JTIwZm9vZHxlbnwxfHx8fDE3NjM4NTE5MzN8MA&ixlib=rb-4.1.0&q=80&w=1080'}
                      alt={item.name}
                      className="w-full h-full object-cover"
                    />
                    {item.badges?.includes('most-ordered') && (
                      <div className="absolute top-1.5 left-1.5 bg-orange-500 text-white px-2 py-0.5 rounded-full text-xs flex items-center gap-1">
                        <Star className="w-3 h-3 fill-white" />
                        <span>Hot</span>
                      </div>
                    )}
                  </div>
                  <div className="flex-1 min-w-0 flex flex-col justify-between py-0.5">
                    <div>
                      <h3 className="line-clamp-1 mb-1">{item.name}</h3>
                      <div className="flex items-center gap-2 text-sm text-gray-600">
                        <div className="flex items-center gap-1">
                          <Star className="w-3.5 h-3.5 fill-orange-500 text-orange-500" />
                          <span>{item.rating}</span>
                        </div>
                        <span>•</span>
                        <span>{item.calories} cal</span>
                      </div>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-lg">{getCurrencySymbol()}{item.price.toFixed(2)}</span>
                      <ChevronRight className="w-5 h-5 text-gray-400" />
                    </div>
                  </div>
                </button>
              ))}
            </div>
          </div>
        )}

        {/* All Dishes Section */}
        <div className="mt-4">
          {activeCategory !== 'all' && (
            <div className="flex items-center justify-between mb-3">
              <h2 className="text-lg">
                {categories.find(c => c.id === activeCategory)?.label}
              </h2>
            </div>
          )}
          
          {filteredItems.length === 0 ? (
            <div className="text-center py-16">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <Search className="w-8 h-8 text-gray-400" />
              </div>
              <h3 className="text-lg text-gray-900 mb-1">No dishes found</h3>
              <p className="text-sm text-gray-500">Try adjusting your search or filters</p>
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-3 pb-4">
              {filteredItems.map((item: any) => (
                <DishCard
                  key={item.id}
                  item={item}
                  onClick={() => onDishClick(item)}
                  onQuickAdd={onQuickAdd}
                  currencySymbol={getCurrencySymbol()}
                  showNutrition={showNutrition}
                />
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Bottom System Bar */}
      <BottomSystemBar
        sessionPin={sessionPin}
        basketCount={basketCount}
        pendingOrdersCount={pendingOrdersCount}
        accentColor={themeColors.accent}
        onViewBasket={onViewBasket}
        onViewHistory={onViewHistory}
        onCallWaiter={onCallWaiter}
      />

      {/* Filter Sheet */}
      <FilterSheet
        isOpen={filterOpen}
        onClose={() => setFilterOpen(false)}
        filters={filters}
        onApplyFilters={handleApplyFilters}
      />

      {/* Restaurant Reviews Modal */}
      <RestaurantReviewsModal
        isOpen={showReviewsModal}
        onClose={() => setShowReviewsModal(false)}
        restaurantName={restaurantName}
        averageRating={4.8}
        totalReviews={120}
      />

      {/* Accessibility Menu */}
      {showAccessibilityMenu && (
        <AccessibilityMenu onClose={() => setShowAccessibilityMenu(false)} />
      )}
    </div>
  );
}