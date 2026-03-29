import { Award, Bike, CreditCard, DollarSign, Gift, Shield, Truck, Zap } from 'lucide-react';

interface Badge {
  id: string;
  label: string;
  icon: React.ReactNode;
  color: string;
}

interface RestaurantBadgesProps {
  features: {
    loyaltyProgram?: boolean;
    loyaltyRate?: string; // e.g., "5 pts / €1"
    takeawayAvailable?: boolean;
    fastDelivery?: boolean;
    promotionsActive?: boolean;
    promotionEndsAt?: string; // e.g., "2h" or "45m"
    verified?: boolean;
    acceptsCards?: boolean;
    freeDelivery?: boolean;
  };
  className?: string;
  showExplicitNegatives?: boolean; // Show "No loyalty program" etc.
}

export function RestaurantBadges({ features, className = '', showExplicitNegatives = false }: RestaurantBadgesProps) {
  const badges: Badge[] = [];

  if (features.verified) {
    badges.push({
      id: 'verified',
      label: 'Verified',
      icon: <Shield className="w-3.5 h-3.5" />,
      color: 'bg-blue-50 text-blue-700 border-blue-200'
    });
  }

  if (features.loyaltyProgram) {
    badges.push({
      id: 'loyalty',
      label: features.loyaltyRate || 'Loyalty Rewards',
      icon: <Gift className="w-3.5 h-3.5" />,
      color: 'bg-purple-50 text-purple-700 border-purple-200'
    });
  } else if (showExplicitNegatives) {
    badges.push({
      id: 'no-loyalty',
      label: 'No loyalty program',
      icon: <Gift className="w-3.5 h-3.5" />,
      color: 'bg-gray-50 text-gray-500 border-gray-200'
    });
  }

  if (features.promotionsActive) {
    badges.push({
      id: 'promo',
      label: features.promotionEndsAt ? `Offer ends in ${features.promotionEndsAt}` : 'Active Offers',
      icon: <Zap className="w-3.5 h-3.5" />,
      color: features.promotionEndsAt 
        ? 'bg-red-50 text-red-700 border-red-300 animate-pulse' 
        : 'bg-orange-50 text-orange-700 border-orange-200'
    });
  }

  if (features.takeawayAvailable) {
    badges.push({
      id: 'takeaway',
      label: 'Takeaway',
      icon: <Bike className="w-3.5 h-3.5" />,
      color: 'bg-green-50 text-green-700 border-green-200'
    });
  }

  if (features.fastDelivery) {
    badges.push({
      id: 'fast',
      label: 'Fast Prep',
      icon: <Zap className="w-3.5 h-3.5" />,
      color: 'bg-green-50 text-green-700 border-green-200'
    });
  }

  if (features.freeDelivery) {
    badges.push({
      id: 'free-delivery',
      label: 'Free Delivery',
      icon: <Truck className="w-3.5 h-3.5" />,
      color: 'bg-green-50 text-green-700 border-green-200'
    });
  }

  if (features.acceptsCards) {
    badges.push({
      id: 'cards',
      label: 'Cards Accepted',
      icon: <CreditCard className="w-3.5 h-3.5" />,
      color: 'bg-gray-50 text-gray-700 border-gray-200'
    });
  }

  if (badges.length === 0) {
    return null;
  }

  return (
    <div className={`flex flex-wrap gap-2 ${className}`}>
      {badges.map((badge) => (
        <span
          key={badge.id}
          className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ${badge.color}`}
        >
          {badge.icon}
          {badge.label}
        </span>
      ))}
    </div>
  );
}

// Compact version for cards
export function RestaurantBadgeIcons({ features, className = '' }: RestaurantBadgesProps) {
  const icons = [];

  if (features.loyaltyProgram) {
    icons.push(
      <div key="loyalty" className="w-6 h-6 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center" title="Loyalty Program">
        <Gift className="w-3.5 h-3.5" />
      </div>
    );
  }

  if (features.promotionsActive) {
    icons.push(
      <div key="promo" className="w-6 h-6 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center" title="Active Promotions">
        <Zap className="w-3.5 h-3.5" />
      </div>
    );
  }

  if (features.takeawayAvailable) {
    icons.push(
      <div key="takeaway" className="w-6 h-6 bg-green-100 text-green-600 rounded-full flex items-center justify-center" title="Takeaway Available">
        <Bike className="w-3.5 h-3.5" />
      </div>
    );
  }

  if (features.verified) {
    icons.push(
      <div key="verified" className="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center" title="Verified Restaurant">
        <Shield className="w-3.5 h-3.5" />
      </div>
    );
  }

  if (icons.length === 0) {
    return null;
  }

  return (
    <div className={`flex gap-1.5 ${className}`}>
      {icons}
    </div>
  );
}