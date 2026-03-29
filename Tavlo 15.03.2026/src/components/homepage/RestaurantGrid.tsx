import { RestaurantCard } from '../shared/RestaurantCard';
import { MapPin, Star, Zap } from 'lucide-react';

interface Restaurant {
  id: string;
  image: string;
  name: string;
  cuisine: string;
  rating: number;
  reviewCount?: number;
  distance: string;
  priceLevel: number;
  avgMainPrice?: string;
  isFavorite?: boolean;
  bestFor?: string[];
  features?: {
    loyaltyProgram?: boolean;
    takeawayAvailable?: boolean;
    promotionsActive?: boolean;
    verified?: boolean;
  };
  isOpen?: boolean;
  opensAt?: string;
  updatedRecently?: boolean;
  whyChoose?: string;
  trustLabel?: string;
}

interface RestaurantGridProps {
  restaurants: Restaurant[];
  onRestaurantClick: (restaurantId: string) => void;
  onToggleFavorite: (restaurantId: string) => void;
}

export function RestaurantGrid({ 
  restaurants, 
  onRestaurantClick, 
  onToggleFavorite 
}: RestaurantGridProps) {
  // Determine intent-driven title based on filters/context
  const getSectionTitle = () => {
    const openRestaurants = restaurants.filter(r => r.isOpen);
    const highRated = restaurants.filter(r => r.rating >= 4.5);
    const takeawayAvailable = restaurants.filter(r => r.features?.takeawayAvailable);

    // Priority: Open > Rating > Takeaway
    if (openRestaurants.length === restaurants.length && restaurants.length > 0) {
      return {
        icon: <MapPin className="w-6 h-6 text-orange-600" />,
        title: 'Open near you',
        subtitle: `${restaurants.length} restaurants ready to serve`
      };
    } else if (highRated.length >= restaurants.length * 0.7 && restaurants.length > 0) {
      return {
        icon: <Star className="w-6 h-6 text-yellow-500" />,
        title: 'Best rated nearby',
        subtitle: `${restaurants.length} top-rated options`
      };
    } else if (takeawayAvailable.length >= restaurants.length * 0.7 && restaurants.length > 0) {
      return {
        icon: <Zap className="w-6 h-6 text-green-600" />,
        title: 'Fast takeaway close to you',
        subtitle: `${restaurants.length} restaurants with quick service`
      };
    }

    // Default
    return {
      icon: <MapPin className="w-6 h-6 text-orange-600" />,
      title: 'Restaurants near you',
      subtitle: `${restaurants.length} available now`
    };
  };

  const sectionInfo = getSectionTitle();

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <div className="mb-6 flex items-start gap-3">
        {sectionInfo.icon}
        <div>
          <h2 className="text-2xl sm:text-3xl mb-1">{sectionInfo.title}</h2>
          <p className="text-gray-500">{sectionInfo.subtitle}</p>
        </div>
      </div>

      {restaurants.length === 0 ? (
        <div className="text-center py-16">
          <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <span className="text-4xl">🍽️</span>
          </div>
          <h3 className="text-xl text-gray-600 mb-2">No restaurants found</h3>
          <p className="text-gray-500">Try adjusting your filters or search query</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
          {restaurants.map((restaurant) => (
            <RestaurantCard
              key={restaurant.id}
              {...restaurant}
              onClick={() => onRestaurantClick(restaurant.id)}
              onToggleFavorite={onToggleFavorite}
            />
          ))}
        </div>
      )}
    </div>
  );
}