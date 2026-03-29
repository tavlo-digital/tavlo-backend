import { TrendingUp, TrendingDown } from 'lucide-react';

interface LoyaltyTransactionRowProps {
  type: 'earned' | 'redeemed';
  points: number;
  description: string;
  date: string;
  restaurantName?: string;
}

export function LoyaltyTransactionRow({
  type,
  points,
  description,
  date,
  restaurantName
}: LoyaltyTransactionRowProps) {
  return (
    <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
      <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
        type === 'earned'
          ? 'bg-green-100'
          : 'bg-red-100'
      }`}>
        {type === 'earned' ? (
          <TrendingUp className="w-5 h-5 text-green-600" />
        ) : (
          <TrendingDown className="w-5 h-5 text-red-600" />
        )}
      </div>
      <div className="flex-1 min-w-0">
        <div className="text-sm mb-0.5">{description}</div>
        {restaurantName && (
          <div className="text-xs text-gray-600 mb-0.5">{restaurantName}</div>
        )}
        <div className="text-xs text-gray-500">{date}</div>
      </div>
      <div className={`text-sm shrink-0 ${
        type === 'earned'
          ? 'text-green-600'
          : 'text-red-600'
      }`}>
        {type === 'earned' ? '+' : '-'}{points} pts
      </div>
    </div>
  );
}
