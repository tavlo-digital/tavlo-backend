import { Heart, MapPin, ArrowLeft, Euro } from 'lucide-react';
import { RatingStars } from '../shared/RatingStars';
import { Button } from '../ui/button';
import { OpeningHours } from '../shared/OpeningHours';
import { RestaurantBadges } from '../shared/RestaurantBadges';
import { useState } from 'react';

interface RestaurantHeaderProps {
  coverImage: string;
  name: string;
  cuisine: string;
  rating: number;
  reviewCount: number;
  priceLevel: number;
  address: string;
  isFavorite?: boolean;
  onBack: () => void;
  onToggleFavorite: () => void;
  openingHours?: {
    monday: { open: string; close: string; closed?: boolean };
    tuesday: { open: string; close: string; closed?: boolean };
    wednesday: { open: string; close: string; closed?: boolean };
    thursday: { open: string; close: string; closed?: boolean };
    friday: { open: string; close: string; closed?: boolean };
    saturday: { open: string; close: string; closed?: boolean };
    sunday: { open: string; close: string; closed?: boolean };
  };
  features?: {
    loyaltyProgram?: boolean;
    loyaltyRate?: string;
    takeawayAvailable?: boolean;
    fastDelivery?: boolean;
    promotionsActive?: boolean;
    promotionEndsAt?: string; // e.g., "2h" or "45m"
    verified?: boolean;
    acceptsCards?: boolean;
    freeDelivery?: boolean;
  };
  onRatingClick?: () => void;
}

export function RestaurantHeader({
  coverImage,
  name,
  cuisine,
  rating,
  reviewCount,
  priceLevel,
  address,
  isFavorite = false,
  onBack,
  onToggleFavorite,
  openingHours,
  features = {},
  onRatingClick
}: RestaurantHeaderProps) {
  const [isLiked, setIsLiked] = useState(isFavorite);

  const handleFavoriteClick = () => {
    setIsLiked(!isLiked);
    onToggleFavorite();
  };

  return (
    <div className="relative">
      {/* Cover Image */}
      <div className="relative h-64 sm:h-80 md:h-96">
        <img
          src={coverImage}
          alt={name}
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent" />
        
        {/* Back Button */}
        <button
          onClick={onBack}
          className="absolute top-4 left-4 w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center hover:bg-white transition-colors shadow-lg"
        >
          <ArrowLeft className="w-5 h-5" />
        </button>

        {/* Favorite Button */}
        <button
          onClick={handleFavoriteClick}
          className="absolute top-4 right-4 w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center hover:bg-white transition-colors shadow-lg"
        >
          <Heart
            className={`w-5 h-5 transition-colors ${
              isLiked
                ? 'fill-red-500 text-red-500'
                : 'text-gray-600 hover:text-red-500'
            }`}
          />
        </button>
      </div>

      {/* Restaurant Info */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6">
          <div className="flex flex-col gap-4">
            <div className="flex-1">
              <h1 className="text-3xl sm:text-4xl mb-2">{name}</h1>
              
              {/* Cuisine, Price, Opening Hours */}
              <div className="flex items-center gap-3 text-gray-600 mb-3 flex-wrap">
                <span className="text-sm sm:text-base">{cuisine}</span>
                <span className="text-gray-300">•</span>
                <span className="text-sm sm:text-base">
                  {Array(priceLevel).fill('€').join('')}
                </span>
                {openingHours && (
                  <>
                    <span className="text-gray-300">•</span>
                    <OpeningHours hours={openingHours} showInline={true} />
                  </>
                )}
              </div>

              {/* Loyalty info inline */}
              {features.loyaltyProgram && features.loyaltyRate && (
                <div className="mb-3">
                  <span className="inline-flex items-center gap-1.5 text-sm text-purple-700 bg-purple-50 px-3 py-1 rounded-full border border-purple-200">
                    ✨ Earn {features.loyaltyRate} on every order
                  </span>
                </div>
              )}
              
              {/* Rating and Reviews - Clickable */}
              <div className="flex items-center gap-4 flex-wrap">
                <button
                  onClick={onRatingClick}
                  className="flex items-center gap-2 hover:opacity-80 transition group"
                >
                  <RatingStars rating={rating} size="md" />
                  <span className="text-sm text-gray-500 group-hover:text-orange-600 transition">
                    {rating} · {reviewCount} reviews
                  </span>
                </button>
                <div className="flex items-center gap-1 text-sm text-gray-600">
                  <MapPin className="w-4 h-4" />
                  <span>{address}</span>
                </div>
              </div>

              {/* Badges */}
              {features && (
                <RestaurantBadges features={features} className="mt-3" />
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}