import { RatingStars } from '../shared/RatingStars';
import { RatingBreakdown } from '../shared/RatingBreakdown';
import { Button } from '../ui/button';
import { User, ThumbsUp, CheckCircle, ShoppingBag, UtensilsCrossed, Sparkles } from 'lucide-react';
import { analyzeReviews } from '../../utils/aiHelpers';
import { AIReviewSummary } from '../ai/AIComponents';

interface Review {
  id: string;
  customerName: string;
  rating: number;
  text: string;
  date: string;
  photos?: string[];
  verified?: boolean; // Verified order
  orderType?: 'dine-in' | 'takeaway';
  helpful?: number; // Number of helpful votes
}

interface ReviewsSectionProps {
  reviews: Review[];
  averageRating: number;
  totalReviews: number;
  isLoggedIn: boolean;
  onAddReview: () => void;
}

export function ReviewsSection({
  reviews,
  averageRating,
  totalReviews,
  isLoggedIn,
  onAddReview
}: ReviewsSectionProps) {
  // Calculate rating breakdown
  const ratingCounts = {
    5: reviews.filter(r => Math.floor(r.rating) === 5).length,
    4: reviews.filter(r => Math.floor(r.rating) === 4).length,
    3: reviews.filter(r => Math.floor(r.rating) === 3).length,
    2: reviews.filter(r => Math.floor(r.rating) === 2).length,
    1: reviews.filter(r => Math.floor(r.rating) === 1).length,
  };

  // Sort reviews - pinned (helpful) first, then by date
  const sortedReviews = [...reviews].sort((a, b) => {
    if (a.helpful && !b.helpful) return -1;
    if (!a.helpful && b.helpful) return 1;
    return 0;
  });

  const pinnedReviews = sortedReviews.filter(r => r.helpful && r.helpful > 5).slice(0, 2);
  const otherReviews = sortedReviews.filter(r => !r.helpful || r.helpful <= 5);

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8">
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Rating Summary */}
        <div className="lg:col-span-1">
          <div className="bg-white rounded-2xl p-6 border border-gray-100 sticky top-4">
            <div className="text-center mb-6">
              <div className="text-5xl mb-2">{averageRating.toFixed(1)}</div>
              <RatingStars rating={averageRating} size="lg" showNumber={false} className="justify-center mb-2" />
              <p className="text-gray-600 text-sm">{totalReviews} reviews</p>
            </div>

            {/* Rating Breakdown */}
            <RatingBreakdown
              ratings={ratingCounts}
              totalReviews={totalReviews}
              className="mb-6"
            />

            {/* Add Review Button */}
            {isLoggedIn ? (
              <Button onClick={onAddReview} className="w-full">
                Write a Review
              </Button>
            ) : (
              <Button onClick={onAddReview} variant="outline" className="w-full">
                Login to Review
              </Button>
            )}
          </div>
        </div>

        {/* Reviews List */}
        <div className="lg:col-span-2 space-y-4">
          {reviews.length === 0 ? (
            <div className="bg-white rounded-2xl p-12 border border-gray-100 text-center">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <User className="w-8 h-8 text-gray-400" />
              </div>
              <h3 className="text-lg mb-2">No reviews yet</h3>
              <p className="text-gray-500 mb-4">
                Be the first to share your experience with this restaurant!
              </p>
              {isLoggedIn && (
                <Button onClick={onAddReview} size="lg">
                  Write First Review
                </Button>
              )}
            </div>
          ) : (
            <>
              {/* AI Review Summary - Always shows first when reviews exist */}
              <div className="bg-gradient-to-br from-purple-50 to-purple-100/50 rounded-2xl p-6 border border-purple-200/50">
                <div className="flex items-center gap-2 mb-4">
                  <Sparkles className="w-5 h-5 text-purple-600" />
                  <h3 className="text-lg font-semibold text-purple-900">AI Review Summary</h3>
                </div>
                {(() => {
                  const reviewAnalysis = analyzeReviews(reviews.map(r => ({
                    id: r.id,
                    customerName: r.customerName,
                    rating: r.rating,
                    comment: r.text,
                    date: r.date
                  })));
                  
                  return (
                    <AIReviewSummary
                      sentiment={reviewAnalysis.sentiment}
                      summary={reviewAnalysis.summary}
                      positivePoints={reviewAnalysis.positivePoints}
                      negativePoints={reviewAnalysis.negativePoints}
                      totalReviews={reviewAnalysis.totalReviews}
                      confidence={reviewAnalysis.confidence}
                    />
                  );
                })()}
              </div>

              {/* Pinned/Most Helpful Reviews */}
              {pinnedReviews.length > 0 && (
                <div className="space-y-4 mb-6">
                  <h3 className="text-lg flex items-center gap-2 text-gray-700">
                    <ThumbsUp className="w-5 h-5 text-orange-600" />
                    Most Helpful Reviews
                  </h3>
                  {pinnedReviews.map((review) => (
                    <ReviewCard key={review.id} review={review} isPinned />
                  ))}
                </div>
              )}

              {/* Other Reviews */}
              {otherReviews.length > 0 && pinnedReviews.length > 0 && (
                <h3 className="text-lg text-gray-700 mt-8 mb-4">All Reviews</h3>
              )}
              {otherReviews.map((review) => (
                <ReviewCard key={review.id} review={review} />
              ))}
            </>
          )}
        </div>
      </div>
    </div>
  );
}

// Separate Review Card Component
function ReviewCard({ review, isPinned = false }: { review: Review; isPinned?: boolean }) {
  return (
    <div className={`bg-white rounded-2xl p-6 border ${
      isPinned ? 'border-orange-200 bg-orange-50/30' : 'border-gray-100'
    }`}>
      {isPinned && (
        <div className="flex items-center gap-2 mb-3 text-orange-600 text-sm">
          <ThumbsUp className="w-4 h-4" />
          <span className="font-medium">Most helpful ({review.helpful} people found this helpful)</span>
        </div>
      )}
      
      <div className="flex items-start gap-4">
        <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center shrink-0">
          <User className="w-6 h-6 text-gray-400" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between mb-2 flex-wrap gap-2">
            <div className="flex items-center gap-2">
              <h4 className="text-base">{review.customerName}</h4>
              {/* Review Badges */}
              {review.verified && (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-green-50 text-green-700 text-xs rounded-full border border-green-200">
                  <CheckCircle className="w-3 h-3" />
                  Verified Order
                </span>
              )}
              {review.orderType === 'takeaway' && (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 text-xs rounded-full border border-blue-200">
                  <ShoppingBag className="w-3 h-3" />
                  Takeaway
                </span>
              )}
              {review.orderType === 'dine-in' && (
                <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-50 text-purple-700 text-xs rounded-full border border-purple-200">
                  <UtensilsCrossed className="w-3 h-3" />
                  Dine-in
                </span>
              )}
            </div>
            <span className="text-sm text-gray-500">{review.date}</span>
          </div>
          <RatingStars rating={review.rating} size="sm" showNumber={false} className="mb-3" />
          <p className="text-gray-700 leading-relaxed">{review.text}</p>
          
          {/* Review Photos */}
          {review.photos && review.photos.length > 0 && (
            <div className="flex gap-2 mt-4 flex-wrap">
              {review.photos.map((photo, index) => (
                <img
                  key={index}
                  src={photo}
                  alt={`Review photo ${index + 1}`}
                  className="w-20 h-20 rounded-lg object-cover"
                />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}