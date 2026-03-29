import { MenuItemCard } from '../shared/MenuItemCard';
import { toast } from 'sonner@2.0.3';
import { Info, QrCode, ShoppingBag } from 'lucide-react';

interface MenuItem {
  id: string;
  name: string;
  description: string;
  price: number;
  image: string;
  category: string;
}

interface MenuSectionProps {
  menu: MenuItem[];
  currency?: string;
  onItemClick?: (item: MenuItem) => void;
  browseMode?: boolean; // true when in Menu tab (preview only)
  onOrderClick?: () => void;
}

export function MenuSection({ menu, currency = '€', onItemClick, browseMode = false, onOrderClick }: MenuSectionProps) {
  // Group menu items by category
  const categories = Array.from(new Set(menu.map(item => item.category)));

  // Get most popular items (mock - would come from backend)
  const popularItems = menu.slice(0, 3);

  const handleAddToCart = (itemId: string) => {
    if (browseMode) {
      toast.info('Switch to Order tab or scan QR to add items');
      return;
    }
    
    const item = menu.find(i => i.id === itemId);
    if (item) {
      toast.success(`${item.name} added to cart!`);
    }
  };

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8">
      {/* Browse Mode Info Banner */}
      {browseMode && (
        <div className="mb-6 bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
          <div className="flex items-start gap-3">
            <Info className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
              <h4 className="font-medium text-blue-900 mb-1">Viewing menu only</h4>
              <p className="text-sm text-blue-700 mb-3">
                To place an order, switch to the <strong>Order tab</strong> or scan the restaurant's QR code at your table.
              </p>
              <div className="flex gap-2 flex-wrap">
                <button
                  onClick={onOrderClick}
                  className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm"
                >
                  <ShoppingBag className="w-4 h-4" />
                  Go to Order Tab
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Most Popular Items - Only in Browse Mode */}
      {browseMode && popularItems.length > 0 && (
        <div className="mb-8">
          <div className="flex items-center gap-2 mb-4">
            <div className="w-8 h-8 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center">
              <span className="text-lg">🔥</span>
            </div>
            <h2 className="text-2xl">Most Popular</h2>
          </div>
          <p className="text-sm text-gray-600 mb-4">
            What other customers usually order
          </p>
          <div className="space-y-3">
            {popularItems.map((item) => (
              <div key={item.id} className="relative">
                <MenuItemCard
                  id={item.id}
                  image={item.image}
                  name={item.name}
                  description={item.description}
                  price={item.price}
                  currency={currency}
                  onAddToCart={handleAddToCart}
                  onClick={() => onItemClick?.(item)}
                  disabled={browseMode}
                  disabledTooltip="Switch to Order tab to add items"
                  hideAddButton={browseMode}
                />
              </div>
            ))}
          </div>
          <div className="border-t mt-8 mb-6"></div>
        </div>
      )}
      
      <div className="space-y-8">
        {categories.map((category) => {
          const items = menu.filter(item => item.category === category);
          
          return (
            <div key={category}>
              <h2 className="text-2xl sm:text-3xl mb-4 sticky top-0 bg-gray-50 py-2 z-10">
                {category}
              </h2>
              <div className="space-y-3">
                {items.map((item) => (
                  <MenuItemCard
                    key={item.id}
                    id={item.id}
                    image={item.image}
                    name={item.name}
                    description={item.description}
                    price={item.price}
                    currency={currency}
                    onAddToCart={handleAddToCart}
                    onClick={() => onItemClick?.(item)}
                    disabled={browseMode}
                    disabledTooltip="Switch to Order tab to add items"
                    hideAddButton={browseMode}
                  />
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}