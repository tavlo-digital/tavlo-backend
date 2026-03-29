import { Star, Flame, Leaf, Plus, Minus } from 'lucide-react';
import { ImageWithFallback } from './figma/ImageWithFallback';
import { useLanguage } from '../contexts/LanguageContext';
import { getTranslatedField } from '../utils/translations';
import { useState } from 'react';

interface DishCardProps {
  item: any;
  onClick: () => void;
  onQuickAdd?: (item: any, quantity: number) => void;
  currencySymbol?: string;
  showNutrition?: boolean;
}

const dishImages: Record<string, string> = {
  'Truffle Mushroom Risotto': 'https://images.unsplash.com/photo-1723476654474-77baaeb27012?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxyaXNvdHRvJTIwbXVzaHJvb218ZW58MXx8fHwxNzYzOTE3NzkwfDA&ixlib=rb-4.1.0&q=80&w=1080',
  'Grilled Salmon Fillet': 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxncmlsbGVkJTIwc2FsbW9ufGVufDF8fHx8MTc2MzkyMTQ5OXww&ixlib=rb-4.1.0&q=80&w=1080',
  'Classic Caesar Salad': 'https://images.unsplash.com/photo-1550304943-4f24f54ddde9?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjYWVzYXIlMjBzYWxhZHxlbnwxfHx8fDE3NjM5MDI0MDh8MA&ixlib=rb-4.1.0&q=80&w=1080',
  'Tiramisu': 'https://images.unsplash.com/photo-1714385905983-6f8e06fffae1?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHx0aXJhbWlzdSUyMGRlc3NlcnR8ZW58MXx8fHwxNzYzODQ2OTA2fDA&ixlib=rb-4.1.0&q=80&w=1080',
  'Margherita Pizza': 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxtYXJnaGVyaXRhJTIwcGl6emF8ZW58MXx8fHwxNzYzODkwMDM2fDA&ixlib=rb-4.1.0&q=80&w=1080',
  'Spaghetti Carbonara': 'https://images.unsplash.com/photo-1588013273468-315fd88ea34c?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjYXJib25hcmElMjBwYXN0YXxlbnwxfHx8fDE3NjM4MjgyOTd8MA&ixlib=rb-4.1.0&q=80&w=1080',
  'Bruschetta al Pomodoro': 'https://images.unsplash.com/photo-1558679582-4d81ce75993a?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxicnVzY2hldHRhJTIwdG9tYXRvfGVufDF8fHx8MTc2MzkwMzM0MHww&ixlib=rb-4.1.0&q=80&w=1080',
  'Panna Cotta': 'https://images.unsplash.com/photo-1488477181946-6428a0291777?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxwYW5uYSUyMGNvdHRhfGVufDF8fHx8MTc2MzkwMzM0NHww&ixlib=rb-4.1.0&q=80&w=1080',
  'Caprese Salad': 'https://images.unsplash.com/photo-1595587870672-c79b47875c6a?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjYXByZXNlJTIwc2FsYWR8ZW58MXx8fHwxNzYzOTI4NDk2fDA&ixlib=rb-4.1.0&q=80&w=1080',
  'Prosecco DOC': 'https://images.unsplash.com/photo-1620421381420-e7fa4a041b15?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxwcm9zZWNjbyUyMHdpbmV8ZW58MXx8fHwxNzYzOTI4NDk2fDA&ixlib=rb-4.1.0&q=80&w=1080',
  'default': 'https://images.unsplash.com/photo-1600891964599-f61ba0e24092?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxyZXN0YXVyYW50JTIwZm9vZHxlbnwxfHx8fDE3NjM4NTE5MzN8MA&ixlib=rb-4.1.0&q=80&w=1080'
};

export function DishCard({ item, onClick, onQuickAdd, currencySymbol = '€', showNutrition = false }: DishCardProps) {
  const hasMostOrderedBadge = item.badges?.includes('most-ordered');
  const isVegetarian = item.dietary?.includes('vegetarian') || item.tags?.includes('vegetarian');
  const isVegan = item.dietary?.includes('vegan') || item.tags?.includes('vegan');
  const isSpicy = item.tags?.includes('spicy');

  const { language } = useLanguage();
  
  // Get translated name and description
  const displayName = getTranslatedField(item, 'name', language);
  const displayDescription = getTranslatedField(item, 'description', language);

  const [quantity, setQuantity] = useState(0);

  const handleAddClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    // Just show the quantity controls, don't add to basket yet
    setQuantity(1);
  };

  const handleMinusClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (quantity > 1) {
      setQuantity(quantity - 1);
    } else {
      // If quantity would go to 0, hide the controls
      setQuantity(0);
    }
  };

  const handlePlusClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    setQuantity(quantity + 1);
  };

  const handleApplyQuantity = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (onQuickAdd && quantity > 0) {
      onQuickAdd(item, quantity);
      setQuantity(0); // Reset after adding
    }
  };

  return (
    <div className="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition-all w-full border border-gray-100 hover:border-gray-200 relative">
      {/* Main Content - Clickable */}
      <button
        onClick={onClick}
        className="text-left w-full"
      >
        {/* Image */}
        <div className="relative aspect-[4/3] bg-gray-100 overflow-hidden">
          <ImageWithFallback
            src={dishImages[item.name] || dishImages['default']}
            alt={item.name}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          />
          
          {/* Overlay gradient for better badge visibility */}
          <div className="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent" />
          
          {/* Most Ordered badge */}
          {hasMostOrderedBadge && (
            <div className="absolute top-2 left-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white px-2.5 py-1 rounded-full flex items-center gap-1 text-xs shadow-lg backdrop-blur-sm">
              <Star className="w-3 h-3 fill-white" />
              <span>Popular</span>
            </div>
          )}

          {/* Dietary badges */}
          <div className="absolute top-2 right-2 flex gap-1">
            {isVegan && (
              <div className="bg-green-500 text-white p-1.5 rounded-full shadow-lg backdrop-blur-sm" title="Vegan">
                <Leaf className="w-3 h-3" />
              </div>
            )}
            {!isVegan && isVegetarian && (
              <div className="bg-green-600 text-white p-1.5 rounded-full shadow-lg backdrop-blur-sm" title="Vegetarian">
                <Leaf className="w-3 h-3" />
              </div>
            )}
            {isSpicy && (
              <div className="bg-red-500 text-white p-1.5 rounded-full shadow-lg backdrop-blur-sm" title="Spicy">
                <Flame className="w-3 h-3" />
              </div>
            )}
          </div>

          {/* Rating badge */}
          {item.rating >= 4.5 && (
            <div className="absolute bottom-2 right-2 bg-white/95 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1 shadow-md">
              <Star className="w-3 h-3 fill-orange-500 text-orange-500" />
              <span className="text-xs">{item.rating}</span>
            </div>
          )}
        </div>

        {/* Content */}
        <div className="p-3 space-y-2">
          <h3 className="line-clamp-2 leading-snug min-h-[2.5rem]">{displayName}</h3>

          <div className="flex items-center gap-2 text-xs text-gray-600">
            <div className="flex items-center gap-1">
              <Star className="w-3.5 h-3.5 fill-orange-500 text-orange-500" />
              <span className="text-gray-900">{item.rating}</span>
              <span className="text-gray-400">({item.reviewCount})</span>
            </div>
            {showNutrition && (
              <>
                <span className="text-gray-300">•</span>
                <span>{item.calories} cal / serving</span>
              </>
            )}
          </div>

          <div className="flex items-center justify-between pt-1">
            <div className="text-lg">{currencySymbol}{item.price.toFixed(2)}</div>
            {item.reviewCount > 50 && (
              <span className="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
                {item.reviewCount}+ orders
              </span>
            )}
          </div>
        </div>
      </button>

      {/* Quick Add Button - Floating */}
      {onQuickAdd && quantity === 0 && (
        <button
          onClick={handleAddClick}
          className="absolute top-2 right-2 bg-green-500 hover:bg-green-600 text-white p-2.5 rounded-full shadow-lg backdrop-blur-sm transition-all hover:scale-110 z-10"
          title="Quick add to basket"
        >
          <Plus className="w-4 h-4" />
        </button>
      )}

      {/* Quantity Controls - Shows after first add */}
      {onQuickAdd && quantity > 0 && (
        <div 
          className="absolute top-2 right-2 bg-white/95 backdrop-blur-sm rounded-full shadow-lg flex items-center gap-2 px-2 py-1.5 z-10"
          onClick={(e) => e.stopPropagation()}
        >
          <button
            onClick={handleMinusClick}
            className="text-gray-600 hover:text-gray-900 p-1"
          >
            <Minus className="w-3.5 h-3.5" />
          </button>
          <span className="text-sm min-w-[20px] text-center">{quantity}</span>
          <button
            onClick={handlePlusClick}
            className="text-gray-600 hover:text-gray-900 p-1"
          >
            <Plus className="w-3.5 h-3.5" />
          </button>
          <button
            onClick={handleApplyQuantity}
            className="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded-full text-xs transition-colors ml-1"
          >
            Add
          </button>
        </div>
      )}
    </div>
  );
}