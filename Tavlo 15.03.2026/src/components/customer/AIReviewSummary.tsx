import { useState } from 'react';
import { ThumbsUp, ThumbsDown, Star, Sparkles, TrendingUp } from 'lucide-react';
import { AIBadge, AITooltip } from '../ai/AIComponents';

interface ReviewSummary {
  totalReviews: number;
  avgRating: number;
  sentiment: {
    positive: number;
    neutral: number;
    negative: number;
  };
  topPros: string[];
  topCons: string[];
  commonPhrases: string[];
  confidence: number;
}

interface AIReviewSummaryProps {
  itemName: string;
  itemType: 'dish' | 'restaurant';
}

export function AIReviewSummary({ itemName, itemType }: AIReviewSummaryProps) {
  const [showAllReviews, setShowAllReviews] = useState(false);

  // AI-generated summary data
  const summary: ReviewSummary = {
    totalReviews: 124,
    avgRating: 4.8,
    sentiment: {
      positive: 89,
      neutral: 8,
      negative: 3
    },
    topPros: [
      'Fresh ingredients',
      'Perfect portion size',
      'Quick service',
      'Great value for money'
    ],
    topCons: [
      'Can be too salty sometimes',
      'Wish it came with more toppings'
    ],
    commonPhrases: [
      '"Absolutely delicious"',
      '"Best pizza in Vienna"',
      '"Always consistent"',
      '"Fresh and hot"'
    ],
    confidence: 94
  };

  const recentReviews = [
    {
      id: '1',
      author: 'Sarah M.',
      rating: 5,
      date: '2 days ago',
      text: 'Absolutely delicious! The crust was perfect and the toppings were fresh. Best pizza I\'ve had in Vienna.',
      helpful: 12
    },
    {
      id: '2',
      author: 'John D.',
      rating: 5,
      date: '5 days ago',
      text: 'Always consistent quality. This is my go-to order every week.',
      helpful: 8
    },
    {
      id: '3',
      author: 'Mike P.',
      rating: 4,
      date: '1 week ago',
      text: 'Great pizza but a bit too salty for my taste. Still good though!',
      helpful: 5
    }
  ];

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-xl font-medium">Customer Reviews</h2>
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-1">
            <Star className="w-5 h-5 text-yellow-400 fill-yellow-400" />
            <span className="text-xl font-semibold">{summary.avgRating}</span>
          </div>
          <span className="text-sm text-gray-500">({summary.totalReviews} reviews)</span>
        </div>
      </div>

      {/* AI Summary Section */}
      <div className="mb-6 p-5 bg-purple-50 border border-purple-200 rounded-xl">
        <div className="flex items-start gap-3 mb-4">
          <Sparkles className="w-5 h-5 text-purple-600 shrink-0 mt-0.5" />
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-2">
              <h3 className="font-medium text-purple-900">What People Say</h3>
              <AIBadge />
              <AITooltip
                title="AI Review Summary"
                explanation="We analyze all reviews to highlight common themes, pros, and cons"
                confidence={summary.confidence}
                dataSource={`Based on ${summary.totalReviews} verified orders`}
              />
            </div>

            {/* Sentiment Bar */}
            <div className="mb-4">
              <div className="flex items-center gap-2 text-sm mb-2">
                <span className="text-gray-700">Customer sentiment:</span>
                <span className="text-green-600 font-medium">{summary.sentiment.positive}% positive</span>
              </div>
              <div className="h-2 bg-gray-200 rounded-full overflow-hidden flex">
                <div 
                  className="bg-green-500"
                  style={{ width: `${summary.sentiment.positive}%` }}
                />
                <div 
                  className="bg-gray-400"
                  style={{ width: `${summary.sentiment.neutral}%` }}
                />
                <div 
                  className="bg-red-500"
                  style={{ width: `${summary.sentiment.negative}%` }}
                />
              </div>
            </div>

            {/* Pros */}
            <div className="mb-4">
              <div className="flex items-center gap-2 mb-2">
                <ThumbsUp className="w-4 h-4 text-green-600" />
                <span className="text-sm font-medium text-gray-900">What customers love:</span>
              </div>
              <ul className="space-y-1.5">
                {summary.topPros.map((pro, index) => (
                  <li key={index} className="flex items-start gap-2 text-sm text-gray-700">
                    <span className="text-green-600 mt-0.5">•</span>
                    {pro}
                  </li>
                ))}
              </ul>
            </div>

            {/* Cons (if any) */}
            {summary.topCons.length > 0 && (
              <div className="mb-4">
                <div className="flex items-center gap-2 mb-2">
                  <ThumbsDown className="w-4 h-4 text-orange-600" />
                  <span className="text-sm font-medium text-gray-900">Some mention:</span>
                </div>
                <ul className="space-y-1.5">
                  {summary.topCons.map((con, index) => (
                    <li key={index} className="flex items-start gap-2 text-sm text-gray-700">
                      <span className="text-orange-600 mt-0.5">•</span>
                      {con}
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {/* Common Phrases */}
            <div>
              <span className="text-sm font-medium text-gray-900 block mb-2">Common phrases:</span>
              <div className="flex flex-wrap gap-2">
                {summary.commonPhrases.map((phrase, index) => (
                  <span
                    key={index}
                    className="px-3 py-1.5 bg-white border border-purple-200 text-purple-700 rounded-full text-sm"
                  >
                    {phrase}
                  </span>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Individual Reviews */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h3 className="font-medium">Recent Reviews</h3>
          <button
            onClick={() => setShowAllReviews(!showAllReviews)}
            className="text-sm text-purple-600 hover:text-purple-700 font-medium"
          >
            {showAllReviews ? 'Show less' : `View all ${summary.totalReviews} reviews`}
          </button>
        </div>

        <div className="space-y-4">
          {recentReviews.slice(0, showAllReviews ? undefined : 3).map((review) => (
            <div key={review.id} className="p-4 border border-gray-200 rounded-lg">
              <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center text-white font-medium">
                    {review.author[0]}
                  </div>
                  <div>
                    <div className="font-medium">{review.author}</div>
                    <div className="text-xs text-gray-500">{review.date}</div>
                  </div>
                </div>
                <div className="flex items-center gap-1">
                  {[...Array(5)].map((_, i) => (
                    <Star
                      key={i}
                      className={`w-4 h-4 ${
                        i < review.rating
                          ? 'text-yellow-400 fill-yellow-400'
                          : 'text-gray-300'
                      }`}
                    />
                  ))}
                </div>
              </div>
              <p className="text-sm text-gray-700 mb-2">{review.text}</p>
              <button className="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <ThumbsUp className="w-3 h-3" />
                Helpful ({review.helpful})
              </button>
            </div>
          ))}
        </div>
      </div>

      {/* Rating Breakdown */}
      <div className="mt-6 pt-6 border-t border-gray-200">
        <h3 className="font-medium mb-4">Rating Breakdown</h3>
        <div className="space-y-2">
          {[5, 4, 3, 2, 1].map((stars) => {
            const percentage = stars === 5 ? 74 : stars === 4 ? 18 : stars === 3 ? 5 : stars === 2 ? 2 : 1;
            return (
              <div key={stars} className="flex items-center gap-3">
                <div className="flex items-center gap-1 w-16">
                  <span className="text-sm">{stars}</span>
                  <Star className="w-3 h-3 text-yellow-400 fill-yellow-400" />
                </div>
                <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-yellow-400"
                    style={{ width: `${percentage}%` }}
                  />
                </div>
                <span className="text-sm text-gray-600 w-12 text-right">{percentage}%</span>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
