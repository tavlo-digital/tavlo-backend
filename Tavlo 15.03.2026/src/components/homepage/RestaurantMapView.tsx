import { useState, useEffect } from 'react';
import { MapPin, Star, Navigation, X } from 'lucide-react';
import { Button } from '../ui/button';

interface Restaurant {
  id: string;
  name: string;
  cuisine: string;
  rating: number;
  reviewCount: number;
  distance: string;
  priceLevel: number;
  avgMainPrice: string;
  image: string;
  lat: number;
  lng: number;
  isOpen?: boolean;
  features?: {
    loyaltyProgram?: boolean;
    takeawayAvailable?: boolean;
    promotionsActive?: boolean;
    verified?: boolean;
  };
}

interface RestaurantMapViewProps {
  restaurants: Restaurant[];
  onRestaurantClick: (restaurantId: string) => void;
  userLocation?: { lat: number; lng: number } | null;
}

export function RestaurantMapView({ 
  restaurants, 
  onRestaurantClick,
  userLocation 
}: RestaurantMapViewProps) {
  const [loadingLocation, setLoadingLocation] = useState(false);
  const [currentUserLocation, setCurrentUserLocation] = useState<{ lat: number; lng: number } | null>(userLocation || null);
  const [selectedRestaurant, setSelectedRestaurant] = useState<Restaurant | null>(null);
  const [mapCenter, setMapCenter] = useState<{ lat: number; lng: number }>({ lat: 48.2082, lng: 16.3738 });
  const [locationError, setLocationError] = useState<string | null>(null);

  // Request user's location
  const requestUserLocation = () => {
    setLoadingLocation(true);
    setLocationError(null);
    
    if ('geolocation' in navigator) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const location = {
            lat: position.coords.latitude,
            lng: position.coords.longitude
          };
          setCurrentUserLocation(location);
          setMapCenter(location);
          setLoadingLocation(false);
          setLocationError(null);
        },
        (error) => {
          console.error('Error getting location:', error);
          setLoadingLocation(false);
          
          // Show user-friendly error message
          let errorMessage = 'Unable to get your location.';
          if (error.code === 1) {
            errorMessage = 'Location access denied. Please enable location permissions.';
          } else if (error.code === 2) {
            errorMessage = 'Location unavailable. Please try again.';
          } else if (error.code === 3) {
            errorMessage = 'Location request timed out. Please try again.';
          }
          setLocationError(errorMessage);
        },
        {
          enableHighAccuracy: false,
          timeout: 10000,
          maximumAge: 300000 // 5 minutes
        }
      );
    } else {
      setLoadingLocation(false);
      setLocationError('Geolocation is not supported by your browser.');
    }
  };

  // Request location on mount if not provided
  useEffect(() => {
    if (!currentUserLocation && !userLocation) {
      // Don't auto-request on mount to avoid permission prompt
      // User can click "My Location" button if they want
    }
  }, []);

  // Update map center when user location changes
  useEffect(() => {
    if (currentUserLocation) {
      setMapCenter(currentUserLocation);
    }
  }, [currentUserLocation]);

  // Calculate map bounds to fit all restaurants
  const getMapBounds = () => {
    if (restaurants.length === 0) return { minLat: 48.1, maxLat: 48.3, minLng: 16.2, maxLng: 16.5 };
    
    const lats = restaurants.map(r => r.lat);
    const lngs = restaurants.map(r => r.lng);
    
    if (currentUserLocation) {
      lats.push(currentUserLocation.lat);
      lngs.push(currentUserLocation.lng);
    }
    
    return {
      minLat: Math.min(...lats),
      maxLat: Math.max(...lats),
      minLng: Math.min(...lngs),
      maxLng: Math.max(...lngs)
    };
  };

  const bounds = getMapBounds();
  const centerLat = (bounds.minLat + bounds.maxLat) / 2;
  const centerLng = (bounds.minLng + bounds.maxLng) / 2;
  
  // Use the calculated center or user's location
  const displayCenter = currentUserLocation || { lat: centerLat, lng: centerLng };

  // Convert lat/lng to pixel position (simplified for display)
  const latLngToPixel = (lat: number, lng: number, containerWidth: number, containerHeight: number) => {
    const latRange = bounds.maxLat - bounds.minLat || 0.1;
    const lngRange = bounds.maxLng - bounds.minLng || 0.1;
    
    // Add padding
    const padding = 0.15;
    const paddedLatRange = latRange * (1 + padding * 2);
    const paddedLngRange = lngRange * (1 + padding * 2);
    
    const x = ((lng - (bounds.minLng - lngRange * padding)) / paddedLngRange) * containerWidth;
    const y = ((bounds.maxLat + latRange * padding - lat) / paddedLatRange) * containerHeight;
    
    return { x, y };
  };

  return (
    <div className="relative w-full h-[600px] rounded-lg overflow-hidden border-2 border-gray-200 bg-gray-100">
      {/* Locate Me Button */}
      <div className="absolute top-4 right-4 z-10">
        <Button
          onClick={requestUserLocation}
          disabled={loadingLocation}
          className="bg-white text-gray-700 hover:bg-gray-50 shadow-lg border-2 border-gray-200"
          size="sm"
        >
          <Navigation className={`w-4 h-4 mr-2 ${loadingLocation ? 'animate-spin' : ''}`} />
          {loadingLocation ? 'Locating...' : 'My Location'}
        </Button>
      </div>

      {/* OpenStreetMap iframe */}
      <div className="absolute inset-0">
        <iframe
          width="100%"
          height="100%"
          frameBorder="0"
          scrolling="no"
          src={`https://www.openstreetmap.org/export/embed.html?bbox=${bounds.minLng},${bounds.minLat},${bounds.maxLng},${bounds.maxLat}&layer=mapnik&marker=${displayCenter.lat},${displayCenter.lng}`}
          style={{ border: 0 }}
          title="Restaurant Map"
        />
      </div>

      {/* Restaurant markers overlay */}
      <div className="absolute inset-0 pointer-events-none">
        <svg className="w-full h-full">
          {/* User location marker */}
          {currentUserLocation && (() => {
            const pos = latLngToPixel(currentUserLocation.lat, currentUserLocation.lng, window.innerWidth, 600);
            return (
              <g>
                <circle cx={pos.x} cy={pos.y} r="8" fill="#3b82f6" stroke="white" strokeWidth="2" className="pointer-events-auto cursor-pointer" />
                <circle cx={pos.x} cy={pos.y} r="12" fill="none" stroke="#3b82f6" strokeWidth="2" opacity="0.3" className="animate-ping" />
              </g>
            );
          })()}

          {/* Restaurant markers */}
          {restaurants.map((restaurant) => {
            const pos = latLngToPixel(restaurant.lat, restaurant.lng, window.innerWidth, 600);
            return (
              <g 
                key={restaurant.id}
                className="pointer-events-auto cursor-pointer hover:opacity-80 transition-opacity"
                onClick={() => setSelectedRestaurant(restaurant)}
              >
                {/* Marker pin */}
                <path
                  d={`M ${pos.x} ${pos.y - 30} C ${pos.x - 8} ${pos.y - 30}, ${pos.x - 15} ${pos.y - 23}, ${pos.x - 15} ${pos.y - 15} C ${pos.x - 15} ${pos.y - 7}, ${pos.x} ${pos.y}, ${pos.x} ${pos.y} C ${pos.x} ${pos.y}, ${pos.x + 15} ${pos.y - 7}, ${pos.x + 15} ${pos.y - 15} C ${pos.x + 15} ${pos.y - 23}, ${pos.x + 8} ${pos.y - 30}, ${pos.x} ${pos.y - 30} Z`}
                  fill="#ef4444"
                  stroke="white"
                  strokeWidth="2"
                />
                <circle cx={pos.x} cy={pos.y - 15} r="5" fill="white" />
              </g>
            );
          })}
        </svg>
      </div>

      {/* Restaurant details popup */}
      {selectedRestaurant && (
        <div className="absolute bottom-4 left-4 right-4 bg-white rounded-lg shadow-2xl p-4 z-20 max-w-md mx-auto pointer-events-auto">
          <button
            onClick={() => setSelectedRestaurant(null)}
            className="absolute top-2 right-2 p-1 hover:bg-gray-100 rounded-full transition-colors"
          >
            <X className="w-4 h-4 text-gray-500" />
          </button>
          
          <div className="flex items-start gap-3">
            <img 
              src={selectedRestaurant.image} 
              alt={selectedRestaurant.name}
              className="w-20 h-20 object-cover rounded-lg"
            />
            <div className="flex-1">
              <h3 className="font-semibold text-base mb-1">{selectedRestaurant.name}</h3>
              <div className="flex items-center gap-1 text-sm text-gray-600 mb-1">
                <Star className="w-3.5 h-3.5 text-yellow-500 fill-yellow-500" />
                <span>{selectedRestaurant.rating}</span>
                <span className="text-gray-400">({selectedRestaurant.reviewCount})</span>
              </div>
              <div className="flex items-center gap-2 text-xs text-gray-500 mb-2">
                <span>{selectedRestaurant.cuisine}</span>
                <span>•</span>
                <span>{selectedRestaurant.avgMainPrice}</span>
                <span>•</span>
                <span>{selectedRestaurant.distance}</span>
              </div>
              
              {/* Features badges */}
              <div className="flex flex-wrap gap-1 mb-2">
                {selectedRestaurant.features?.loyaltyProgram && (
                  <span className="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded">
                    Loyalty
                  </span>
                )}
                {selectedRestaurant.features?.takeawayAvailable && (
                  <span className="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">
                    Takeaway
                  </span>
                )}
                {selectedRestaurant.features?.promotionsActive && (
                  <span className="text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded">
                    Promo
                  </span>
                )}
              </div>

              <Button
                onClick={() => onRestaurantClick(selectedRestaurant.id)}
                size="sm"
                className="w-full text-xs"
              >
                View Details
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Legend */}
      <div className="absolute top-4 left-4 bg-white rounded-lg shadow-lg p-3 z-10 text-xs">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <div className="w-4 h-4 bg-red-500 rounded-full border-2 border-white"></div>
            <span className="text-gray-700">Restaurants</span>
          </div>
          {currentUserLocation && (
            <div className="flex items-center gap-2">
              <div className="w-4 h-4 bg-blue-500 rounded-full border-2 border-white"></div>
              <span className="text-gray-700">You are here</span>
            </div>
          )}
        </div>
      </div>

      {/* Location Error Message */}
      {locationError && (
        <div className="absolute bottom-4 left-4 right-4 bg-red-50 border-2 border-red-200 text-red-700 rounded-lg shadow-lg p-3 z-20 max-w-md mx-auto pointer-events-auto">
          <button
            onClick={() => setLocationError(null)}
            className="absolute top-2 right-2 p-1 hover:bg-red-100 rounded-full transition-colors"
          >
            <X className="w-4 h-4 text-red-500" />
          </button>
          <p className="text-sm pr-6">{locationError}</p>
          <p className="text-xs text-red-600 mt-1">Showing restaurants near Vienna, Austria</p>
        </div>
      )}
    </div>
  );
}