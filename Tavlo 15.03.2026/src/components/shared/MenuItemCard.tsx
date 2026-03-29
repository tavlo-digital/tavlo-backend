import { Plus } from 'lucide-react';
import { Button } from '../ui/button';

interface MenuItemCardProps {
  id: string;
  image: string;
  name: string;
  description: string;
  price: number;
  currency?: string;
  onAddToCart?: (id: string) => void;
  onClick?: () => void;
  disabled?: boolean;
  disabledTooltip?: string;
  hideAddButton?: boolean;  // New prop to hide the + button
}

export function MenuItemCard({
  id,
  image,
  name,
  description,
  price,
  currency = '€',
  onAddToCart,
  onClick,
  disabled = false,
  disabledTooltip,
  hideAddButton = false  // Default to false
}: MenuItemCardProps) {
  const handleAddClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!disabled) {
      onAddToCart?.(id);
    }
  };

  // Fallback image if none provided
  const displayImage = image || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400';

  return (
    <div
      onClick={onClick}
      className={`bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow border border-gray-100 ${
        disabled && !hideAddButton ? 'opacity-60' : ''
      } ${onClick && !disabled ? 'cursor-pointer' : ''}`}
    >
      <div className="flex gap-4 p-4">
        {/* Content */}
        <div className="flex-1 min-w-0">
          <h3 className="text-base sm:text-lg line-clamp-1 mb-1">{name}</h3>
          <p className="text-sm text-gray-500 line-clamp-2 mb-3">{description}</p>
          <div className="flex items-center justify-between">
            <span className="text-lg text-orange-600">
              {currency}{price.toFixed(2)}
            </span>
            {!hideAddButton && (
              <div className="relative group">
                <Button
                  size="sm"
                  onClick={handleAddClick}
                  disabled={disabled}
                  className="rounded-full w-8 h-8 p-0"
                  title={disabled ? disabledTooltip : 'Add to cart'}
                >
                  <Plus className="w-4 h-4" />
                </Button>
                {disabled && disabledTooltip && (
                  <div className="absolute bottom-full right-0 mb-2 hidden group-hover:block w-48 bg-gray-900 text-white text-xs rounded-lg py-2 px-3 z-10">
                    {disabledTooltip}
                    <div className="absolute bottom-0 right-4 transform translate-y-1/2 rotate-45 w-2 h-2 bg-gray-900"></div>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Image */}
        <div className="w-24 h-24 sm:w-28 sm:h-28 rounded-xl overflow-hidden shrink-0">
          <img
            src={displayImage}
            alt={name}
            className="w-full h-full object-cover"
          />
        </div>
      </div>
    </div>
  );
}