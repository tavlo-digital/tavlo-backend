import { ChevronRight, Star } from 'lucide-react';
import { Button } from '../ui/button';

interface LoyaltyRestaurantCardProps {
  restaurantId: string;
  restaurantName: string;
  restaurantLogo?: string;
  pointsBalance: number;
  minimumRedemption?: number;
  redemptionRate?: number; // e.g., 0.05 means 100 points = €5
  variant?: 'compact' | 'full' | 'redeemable' | 'not-redeemable';
  onClick?: () => void;
  onRedeem?: () => void;
}

export function LoyaltyRestaurantCard({
  restaurantId,
  restaurantName,
  restaurantLogo,
  pointsBalance,
  minimumRedemption = 100,
  redemptionRate = 0.05,
  variant = 'full',
  onClick,
  onRedeem
}: LoyaltyRestaurantCardProps) {
  const pointsToNextReward = minimumRedemption - (pointsBalance % minimumRedemption);
  const progress = (pointsBalance % minimumRedemption) / minimumRedemption * 100;
  const canRedeem = pointsBalance >= minimumRedemption;
  const redeemableAmount = Math.floor(pointsBalance / minimumRedemption) * minimumRedemption * redemptionRate;

  if (variant === 'compact') {
    return (
      <button
        onClick={onClick}
        className="w-full flex items-center gap-3 p-4 bg-white rounded-xl border border-gray-200 hover:border-[#101828] hover:shadow-sm transition-all group"
      >
        {/* Restaurant Logo */}
        <div className="w-12 h-12 shrink-0 bg-gradient-to-br from-[#101828] to-[#101828] rounded-lg flex items-center justify-center overflow-hidden">
          {restaurantLogo ? (
            <img src={restaurantLogo} alt={restaurantName} className="w-full h-full object-cover" />
          ) : (
            <span className="text-lg">🍽️</span>
          )}
        </div>

        {/* Restaurant Info */}
        <div className="flex-1 min-w-0 text-left">
          <div className="text-sm mb-1 truncate">{restaurantName}</div>
          <div className="text-lg">{pointsBalance} points</div>
          
          {/* Progress bar - always shown */}
          <div className="w-full bg-gray-200 rounded-full h-1.5 mt-2">
            <div
              className="bg-[#101828] h-1.5 rounded-full transition-all"
              style={{ width: `${Math.min(progress, 100)}%` }}
            />
          </div>
          <div className="text-xs text-gray-500 mt-1">
            {canRedeem ? (
              <span className="text-green-600 font-medium">Redeem available</span>
            ) : (
              <span>{pointsToNextReward} points to next reward</span>
            )}
          </div>
        </div>

        {/* Chevron */}
        <ChevronRight className="w-5 h-5 text-gray-400 group-hover:text-[#101828] transition-colors shrink-0" />
      </button>
    );
  }

  if (variant === 'redeemable' || variant === 'not-redeemable') {
    return (
      <div className="bg-white rounded-2xl p-6 border border-gray-200">
        {/* Restaurant Header */}
        <div className="flex items-center gap-3 mb-4">
          <div className="w-14 h-14 shrink-0 bg-gradient-to-br from-[#101828] to-[#101828] rounded-xl flex items-center justify-center overflow-hidden">
            {restaurantLogo ? (
              <img src={restaurantLogo} alt={restaurantName} className="w-full h-full object-cover" />
            ) : (
              <span className="text-2xl">🍽️</span>
            )}
          </div>
          <div className="flex-1 min-w-0">
            <h3 className="text-lg truncate">{restaurantName}</h3>
            <p className="text-sm text-gray-500">Loyalty Points</p>
          </div>
        </div>

        {/* Points Balance */}
        <div className="bg-gradient-to-br from-[#101828] to-[#101828] text-white rounded-xl p-5 mb-4">
          <div className="flex items-center justify-between mb-4">
            <div>
              <div className="text-white/80 text-sm mb-1">Your points at this restaurant</div>
              <div className="text-3xl">{pointsBalance}</div>
            </div>
            <div className="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
              <Star className="w-6 h-6 fill-white" />
            </div>
          </div>

          {/* Progress Bar */}
          <div>
            <div className="flex justify-between text-sm mb-2">
              <span className="text-white/80">Next reward</span>
              <span className="text-white">{pointsToNextReward} points away</span>
            </div>
            <div className="w-full bg-white/20 rounded-full h-2">
              <div
                className="bg-white h-2 rounded-full transition-all"
                style={{ width: `${progress}%` }}
              />
            </div>
          </div>
        </div>

        {/* Redeem Button */}
        <Button
          onClick={onRedeem}
          disabled={!canRedeem}
          className="w-full"
          variant={canRedeem ? 'default' : 'outline'}
        >
          {canRedeem 
            ? `Redeem at this restaurant (€${redeemableAmount.toFixed(2)} available)` 
            : 'Not enough points'}
        </Button>
      </div>
    );
  }

  // Full variant (default)
  return (
    <button
      onClick={onClick}
      className="w-full bg-white rounded-2xl p-6 border-2 border-gray-200 hover:border-[#101828] transition-all text-left"
    >
      {/* Restaurant Header */}
      <div className="flex items-center gap-3 mb-4">
        <div className="w-14 h-14 shrink-0 bg-gradient-to-br from-[#101828] to-[#101828] rounded-xl flex items-center justify-center overflow-hidden">
          {restaurantLogo ? (
            <img src={restaurantLogo} alt={restaurantName} className="w-full h-full object-cover" />
          ) : (
            <span className="text-2xl">🍽️</span>
          )}
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="text-lg truncate">{restaurantName}</h3>
          <p className="text-sm text-gray-500">Loyalty Wallet</p>
        </div>
        <ChevronRight className="w-6 h-6 text-gray-400" />
      </div>

      {/* Points Display */}
      <div className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4 mb-3">
        <div className="flex items-baseline gap-2 mb-3">
          <div className="text-3xl text-[#101828]">{pointsBalance}</div>
          <div className="text-sm text-gray-600">points</div>
        </div>

        {/* Progress Bar */}
        <div>
          <div className="flex justify-between text-xs mb-1.5">
            <span className="text-gray-600">Next reward</span>
            <span className="text-gray-900">{pointsToNextReward} away</span>
          </div>
          <div className="w-full bg-gray-300 rounded-full h-2">
            <div
              className="bg-[#101828] h-2 rounded-full transition-all"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>
      </div>

      {/* CTA Status */}
      <div className={`text-sm text-center py-2 px-4 rounded-lg ${
        canRedeem 
          ? 'bg-green-50 text-green-700 font-medium' 
          : 'bg-gray-100 text-gray-600'
      }`}>
        {canRedeem ? `Redeemable here (€${redeemableAmount.toFixed(2)})` : 'Keep earning to unlock rewards'}
      </div>
    </button>
  );
}