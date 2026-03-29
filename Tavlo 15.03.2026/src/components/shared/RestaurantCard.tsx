import { MapPin, TrendingUp, Eye, Star } from 'lucide-react';
import { RatingStars } from './RatingStars';
import { RestaurantBadgeIcons } from './RestaurantBadges';
import { useState } from 'react';

interface RestaurantCardProps {
  id: string;
  image: string;
  name: string;
  cuisine: string;
  rating: number;
  reviewCount?: number; // Added for "4.6 · 120+ reviews"
  distance: string;
  priceLevel?: number;
  avgMainPrice?: string; // e.g., "€12–15"
  isFavorite?: boolean;
  onToggleFavorite?: (id: string) => void;
  onClick?: () => void;
  bestFor?: string[]; // e.g., ["Best for lunch", "Fast service"]
  features?: {
    loyaltyProgram?: boolean;
    takeawayAvailable?: boolean;
    promotionsActive?: boolean;
    verified?: boolean;
  };
  isOpen?: boolean;
  opensAt?: string; // e.g., "11:00"
  updatedRecently?: boolean; // Show "Updated today" badge
  whyChoose?: string; // Decision helper: "People love the tacos", max 6 words
  trustLabel?: string; // "Highly rated" or "Popular choice"
}

export function RestaurantCard({
  id,
  image,
  name,
  cuisine,
  rating,
  reviewCount = 0,
  distance,
  priceLevel = 2,
  avgMainPrice,
  onClick,
  bestFor = [],
  features,
  isOpen = true,
  opensAt,
  updatedRecently = false,
  whyChoose,
  trustLabel
}: RestaurantCardProps) {
  const [isHovered, setIsHovered] = useState(false);

  // Contextual tags (max 2)
  const getContextualTags = () => {
    const hour = new Date().getHours();
    const tags = [];

    if (bestFor.length > 0) {
      // Time-based context
      if (hour >= 11 && hour < 15) {
        // Lunch time
        if (bestFor.includes('Best for lunch')) tags.push('Best for lunch');
        else if (bestFor.includes('Fast service')) tags.push('Fast service');
      } else if (hour >= 18 && hour < 22) {
        // Dinner time
        if (bestFor.includes('Date-friendly')) tags.push('Date-friendly');
        else if (bestFor.includes('Best for dinner')) tags.push('Best for dinner');
      } else if (hour >= 22 || hour < 6) {
        // Late hours
        if (bestFor.includes('Fast service')) tags.push('Fast service');
        else if (bestFor.includes('Best for takeaway')) tags.push('Best for takeaway');
      }

      // Add first available tag if none matched
      if (tags.length === 0 && bestFor.length > 0) {
        tags.push(bestFor[0]);
      }

      // Add second tag if available
      if (tags.length === 1 && bestFor.length > 1) {
        const secondTag = bestFor.find(t => t !== tags[0]);
        if (secondTag) tags.push(secondTag);
      }
    }

    return tags.slice(0, 2); // Max 2
  };

  const contextualTags = getContextualTags();

  return (
    <div
      onClick={onClick}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      className="group bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-300 cursor-pointer border border-gray-100 relative"
    >
      {/* Image */}
      <div className="relative h-48 overflow-hidden">
        <img
          src={image}
          alt={name}
          className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
        />
        
        {/* Status Badge - Subtle */}
        {!isOpen && opensAt && (
          <div className="absolute top-3 left-3 bg-gray-900/80 backdrop-blur-sm text-white text-xs px-2.5 py-1 rounded-lg font-medium">
            Opens at {opensAt}
          </div>
        )}
        
        {updatedRecently && (
          <div className="absolute top-3 left-3 bg-gray-900/60 backdrop-blur-sm text-white text-xs px-2.5 py-1 rounded-lg font-medium flex items-center gap-1">
            <TrendingUp className="w-3 h-3" />
            Updated today
          </div>
        )}

        {/* Contextual Tags - Max 2, reduced saturation */}
        {contextualTags.length > 0 && (
          <div className="absolute bottom-3 left-3 right-3 flex flex-wrap gap-1.5">
            {contextualTags.map((tag) => (
              <span
                key={tag}
                className="bg-white/90 backdrop-blur-sm text-gray-700 text-xs px-2.5 py-1 rounded-lg font-medium shadow-sm border border-gray-200"
              >
                {tag}
              </span>
            ))}
          </div>
        )}

        {/* Hover CTA */}
        {isHovered && (
          <div className="absolute inset-0 bg-black/40 flex items-center justify-center transition-opacity">
            <button className="bg-white text-gray-900 px-6 py-3 rounded-full font-medium hover:bg-gray-100 transition-colors shadow-lg flex items-center gap-2">
              <Eye className="w-4 h-4" />
              View menu
            </button>
          </div>
        )}
      </div>

      {/* Content */}
      <div className="p-4 space-y-3">
        {/* Name & Price - HIGH CONTRAST */}
        <div className="flex items-start justify-between gap-2">
          <h3 className="text-lg font-semibold text-gray-900 line-clamp-1 group-hover:text-orange-600 transition-colors">
            {name}
          </h3>
          {/* Price anchoring - HIGH CONTRAST */}
          {avgMainPrice && (
            <div className="shrink-0 text-right">
              <div className="text-xs text-gray-500">Avg. main</div>
              <div className="text-base font-bold text-gray-900">{avgMainPrice}</div>
            </div>
          )}
        </div>

        {/* Rating & Reviews - Trust Signal */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <div className="flex items-center gap-1">
              <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
              <span className="font-semibold text-gray-900">{rating.toFixed(1)}</span>
            </div>
            <span className="text-sm text-gray-500">·</span>
            <span className="text-sm text-gray-600">{reviewCount}+ reviews</span>
          </div>
          
          {/* Distance - Reduced weight */}
          <div className="flex items-center gap-1 text-xs text-gray-500">
            <MapPin className="w-3 h-3" />
            <span>{distance}</span>
          </div>
        </div>

        {/* Trust Label */}
        {trustLabel && (
          <div className="text-xs text-orange-600 font-medium">
            ✓ {trustLabel}
          </div>
        )}

        {/* Why Choose - Decision Helper */}
        {whyChoose && (
          <div className="text-sm text-gray-700 italic">
            "{whyChoose}"
          </div>
        )}

        {/* Cuisine - Reduced weight */}
        <div className="text-sm text-gray-500">{cuisine}</div>

        {/* Feature Icons - Reduced saturation */}
        {features && (
          <div className="opacity-70">
            <RestaurantBadgeIcons features={features} />
          </div>
        )}
      </div>
    </div>
  );
}