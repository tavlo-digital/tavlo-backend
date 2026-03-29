import { Star, TrendingUp, TrendingDown, Gift, ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { LoyaltyRestaurantCard } from './LoyaltyRestaurantCard';
import { LoyaltyTransactionRow } from './LoyaltyTransactionRow';

interface LoyaltyTransaction {
  id: string;
  type: 'earned' | 'redeemed';
  points: number;
  description: string;
  date: string;
  restaurantId: string;
}

interface RestaurantLoyalty {
  restaurantId: string;
  restaurantName: string;
  restaurantLogo?: string;
  points: number;
  minimumRedemption?: number;
  redemptionRate?: number;
  transactions: LoyaltyTransaction[];
}

interface LoyaltyPointsCardProps {
  restaurantLoyalties: RestaurantLoyalty[];
}

export function LoyaltyPointsCard({ restaurantLoyalties }: LoyaltyPointsCardProps) {
  const [selectedRestaurant, setSelectedRestaurant] = useState<string | null>(null);

  const selectedLoyalty = restaurantLoyalties.find(r => r.restaurantId === selectedRestaurant);

  // Detail View - Restaurant Specific
  if (selectedRestaurant && selectedLoyalty) {
    const pointsToNextReward = (selectedLoyalty.minimumRedemption || 100) - (selectedLoyalty.points % (selectedLoyalty.minimumRedemption || 100));
    const progress = (selectedLoyalty.points % (selectedLoyalty.minimumRedemption || 100)) / (selectedLoyalty.minimumRedemption || 100) * 100;

    return (
      <div className="space-y-4">
        {/* Back Button */}
        <button
          onClick={() => setSelectedRestaurant(null)}
          className="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to all restaurants
        </button>

        {/* Current Points Card */}
        <div className="bg-gradient-to-br from-[#101828] to-[#101828] text-white rounded-2xl p-6">
          {/* Restaurant Header */}
          <div className="flex items-center gap-3 mb-6">
            <div className="w-12 h-12 shrink-0 bg-white/20 rounded-xl flex items-center justify-center overflow-hidden">
              {selectedLoyalty.restaurantLogo ? (
                <img src={selectedLoyalty.restaurantLogo} alt={selectedLoyalty.restaurantName} className="w-full h-full object-cover" />
              ) : (
                <span className="text-xl">🍽️</span>
              )}
            </div>
            <div>
              <div className="text-white/80 text-sm">Your points at</div>
              <div className="text-lg">{selectedLoyalty.restaurantName}</div>
            </div>
          </div>

          <div className="flex items-center justify-between mb-6">
            <div>
              <div className="text-white/80 text-sm mb-1">Points Balance</div>
              <div className="text-4xl">{selectedLoyalty.points}</div>
            </div>
            <div className="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center">
              <Star className="w-8 h-8 fill-white" />
            </div>
          </div>

          {/* Progress to Next Reward */}
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

        {/* Transactions History - Filtered to this restaurant */}
        <div className="bg-white rounded-2xl p-6 border border-gray-100">
          <h3 className="text-xl mb-4">Points History</h3>
          <p className="text-sm text-gray-600 mb-4">Transactions at {selectedLoyalty.restaurantName}</p>
          
          {selectedLoyalty.transactions.length === 0 ? (
            <div className="text-center py-8 text-gray-500">
              <Gift className="w-12 h-12 mx-auto mb-2 text-gray-300" />
              <p>No transactions yet at this restaurant</p>
            </div>
          ) : (
            <div className="space-y-3 max-h-[400px] overflow-y-auto">
              {selectedLoyalty.transactions.map((transaction) => (
                <LoyaltyTransactionRow
                  key={transaction.id}
                  type={transaction.type}
                  points={transaction.points}
                  description={transaction.description}
                  date={transaction.date}
                />
              ))}
            </div>
          )}
        </div>
      </div>
    );
  }

  // Default View - Restaurant List
  return (
    <div className="space-y-4">
      <div className="bg-white rounded-2xl p-6 border border-gray-100">
        <h3 className="text-xl mb-2">Your Loyalty Wallets</h3>
        <p className="text-sm text-gray-600 mb-6">
          Points are earned and redeemed per restaurant
        </p>

        {restaurantLoyalties.length === 0 ? (
          <div className="text-center py-12 text-gray-500">
            <Gift className="w-16 h-16 mx-auto mb-3 text-gray-300" />
            <p className="text-lg mb-1">No loyalty programs yet</p>
            <p className="text-sm">Start ordering to earn points!</p>
          </div>
        ) : (
          <div className="space-y-3">
            {restaurantLoyalties.map((loyalty) => (
              <LoyaltyRestaurantCard
                key={loyalty.restaurantId}
                restaurantId={loyalty.restaurantId}
                restaurantName={loyalty.restaurantName}
                restaurantLogo={loyalty.restaurantLogo}
                pointsBalance={loyalty.points}
                minimumRedemption={loyalty.minimumRedemption || 100}
                redemptionRate={loyalty.redemptionRate || 0.05}
                variant="full"
                onClick={() => setSelectedRestaurant(loyalty.restaurantId)}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}