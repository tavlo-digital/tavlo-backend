import { Star } from 'lucide-react';

interface RatingBreakdownProps {
  ratings: {
    5: number;
    4: number;
    3: number;
    2: number;
    1: number;
  };
  totalReviews: number;
  className?: string;
}

export function RatingBreakdown({ ratings, totalReviews, className = '' }: RatingBreakdownProps) {
  const starLevels = [5, 4, 3, 2, 1];

  return (
    <div className={`space-y-2 ${className}`}>
      {starLevels.map((stars) => {
        const count = ratings[stars as keyof typeof ratings] || 0;
        const percentage = totalReviews > 0 ? (count / totalReviews) * 100 : 0;

        return (
          <div key={stars} className="flex items-center gap-3">
            <div className="flex items-center gap-1 w-12">
              <span className="text-sm font-medium">{stars}</span>
              <Star className="w-3 h-3 fill-yellow-400 text-yellow-400" />
            </div>
            <div className="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
              <div
                className="h-full bg-yellow-400 transition-all duration-500"
                style={{ width: `${percentage}%` }}
              />
            </div>
            <span className="text-sm text-gray-600 w-12 text-right">{count}</span>
          </div>
        );
      })}
    </div>
  );
}
