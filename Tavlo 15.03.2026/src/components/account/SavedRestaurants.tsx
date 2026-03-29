import { RestaurantCard } from '../shared/RestaurantCard';
import { Heart } from 'lucide-react';

interface Restaurant {
  id: string;
  image: string;
  name: string;
  cuisine: string;
  rating: number;
  distance: string;
  priceLevel: number;
}

interface SavedRestaurantsProps {
  restaurants: Restaurant[];
  onRestaurantClick: (restaurantId: string) => void;
  onRemoveFavorite: (restaurantId: string) => void;
}

export function SavedRestaurants({ 
  restaurants, 
  onRestaurantClick, 
  onRemoveFavorite 
}: SavedRestaurantsProps) {
  return (
    <div className="bg-white rounded-2xl p-6 border border-gray-100">
      <h2 className="text-2xl mb-4">Saved Restaurants</h2>
      
      {restaurants.length === 0 ? (
        <div className="text-center py-12 text-gray-500">
          <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <Heart className="w-8 h-8 text-gray-400" />
          </div>
          <p>No saved restaurants yet</p>
          <p className="text-sm mt-1">Tap the heart icon on restaurants to save them here</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {restaurants.map((restaurant) => (
            <RestaurantCard
              key={restaurant.id}
              {...restaurant}
              isFavorite={true}
              onClick={() => onRestaurantClick(restaurant.id)}
              onToggleFavorite={onRemoveFavorite}
            />
          ))}
        </div>
      )}
    </div>
  );
}
