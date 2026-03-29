import { X, Star, ThumbsUp, User, Sparkles } from 'lucide-react';
import { Button } from './ui/button';
import { AIReviewSummary } from './ai/AIComponents';
import { analyzeReviews } from '../utils/aiHelpers';

interface RestaurantReviewsModalProps {
  isOpen: boolean;
  onClose: () => void;
  restaurantName: string;
  averageRating: number;
  totalReviews: number;
}

// Mock restaurant reviews data
const mockReviews = [
  {
    id: '1',
    customerName: 'Maria S.',
    rating: 5,
    date: '2 days ago',
    comment: 'Absolutely amazing experience! The truffle risotto was divine and the service was impeccable. Will definitely come back!',
    helpful: 24,
    verified: true
  },
  {
    id: '2',
    customerName: 'John D.',
    rating: 5,
    date: '5 days ago',
    comment: 'Best Italian restaurant in the area. Fresh ingredients, authentic recipes, and a cozy atmosphere. Highly recommend the tiramisu!',
    helpful: 18,
    verified: true
  },
  {
    id: '3',
    customerName: 'Sarah K.',
    rating: 4,
    date: '1 week ago',
    comment: 'Great food quality and presentation. The pasta was perfectly cooked. Only minor issue was the wait time during peak hours.',
    helpful: 12,
    verified: true
  },
  {
    id: '4',
    customerName: 'Michael R.',
    rating: 5,
    date: '1 week ago',
    comment: 'Outstanding! Every dish we ordered exceeded expectations. The salmon was cooked to perfection.',
    helpful: 15,
    verified: true
  },
  {
    id: '5',
    customerName: 'Emma L.',
    rating: 5,
    date: '2 weeks ago',
    comment: 'Wonderful dining experience from start to finish. The staff was incredibly friendly and attentive.',
    helpful: 9,
    verified: true
  },
  {
    id: '6',
    customerName: 'David P.',
    rating: 4,
    date: '2 weeks ago',
    comment: 'Solid restaurant with great ambiance. Portions are generous and prices are reasonable for the quality.',
    helpful: 7,
    verified: true
  },
  {
    id: '7',
    customerName: 'Lisa M.',
    rating: 5,
    date: '3 weeks ago',
    comment: 'This place never disappoints! Been coming here for years and the quality remains consistently excellent.',
    helpful: 21,
    verified: true
  }
];

export function RestaurantReviewsModal({ 
  isOpen, 
  onClose, 
  restaurantName,
  averageRating,
  totalReviews 
}: RestaurantReviewsModalProps) {
  if (!isOpen) return null;

  // Analyze reviews with AI
  const reviewAnalysis = analyzeReviews(mockReviews);

  // Calculate rating distribution
  const ratingDistribution = [
    { stars: 5, count: 98, percentage: 82 },
    { stars: 4, count: 15, percentage: 12.5 },
    { stars: 3, count: 5, percentage: 4 },
    { stars: 2, count: 1, percentage: 0.8 },
    { stars: 1, count: 1, percentage: 0.8 }
  ];

  return (
    <>
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 animate-fade-in"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="fixed inset-x-0 top-0 bottom-0 md:inset-4 md:top-4 md:bottom-4 md:max-w-2xl md:mx-auto bg-white md:rounded-3xl z-50 flex flex-col overflow-hidden animate-slide-up shadow-2xl">
        {/* Header */}
        <div className="relative px-6 pt-6 pb-4 border-b bg-gradient-to-b from-orange-50 to-white">
          <button 
            onClick={onClose}
            className="absolute top-4 right-4 p-2 hover:bg-white rounded-full transition-colors"
          >
            <X className="w-6 h-6" />
          </button>
          
          <h2 className="text-2xl mb-2 pr-10">{restaurantName}</h2>
          
          {/* Overall Rating */}
          <div className="flex items-center gap-4 mb-4">
            <div className="text-center">
              <div className="text-4xl mb-1">{averageRating.toFixed(1)}</div>
              <div className="flex gap-0.5 mb-1">
                {[...Array(5)].map((_, i) => (
                  <Star 
                    key={i} 
                    className={`w-4 h-4 ${i < Math.round(averageRating) ? 'fill-orange-500 text-orange-500' : 'text-gray-300'}`}
                  />
                ))}
              </div>
              <div className="text-xs text-gray-600">{totalReviews}+ reviews</div>
            </div>
            
            {/* Rating Distribution */}
            <div className="flex-1 space-y-1.5">
              {ratingDistribution.map((item) => (
                <div key={item.stars} className="flex items-center gap-2 text-sm">
                  <div className="flex items-center gap-1 w-12">
                    <span className="text-xs text-gray-600">{item.stars}</span>
                    <Star className="w-3 h-3 fill-orange-500 text-orange-500" />
                  </div>
                  <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div 
                      className="h-full bg-orange-500 rounded-full transition-all"
                      style={{ width: `${item.percentage}%` }}
                    />
                  </div>
                  <span className="text-xs text-gray-600 w-8 text-right">{item.count}</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Reviews List */}
        <div className="flex-1 overflow-y-auto px-6 py-4">
          {/* AI Summary Section */}
          <div className="mb-6">
            <div className="flex items-center gap-2 mb-3">
              <Sparkles className="w-5 h-5 text-purple-600" />
              <h3 className="text-lg">AI Review Summary</h3>
            </div>
            <AIReviewSummary
              sentiment={reviewAnalysis.sentiment}
              summary={reviewAnalysis.summary}
              positivePoints={reviewAnalysis.positivePoints}
              negativePoints={reviewAnalysis.negativePoints}
              totalReviews={reviewAnalysis.totalReviews}
              confidence={reviewAnalysis.confidence}
            />
          </div>

          <div className="space-y-4">
            {mockReviews.map((review) => (
              <div 
                key={review.id} 
                className="bg-white border border-gray-100 rounded-2xl p-4 hover:shadow-md transition-shadow"
              >
                {/* Review Header */}
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-gradient-to-br from-orange-400 to-orange-600 rounded-full flex items-center justify-center text-white">
                      <User className="w-5 h-5" />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="text-sm">{review.customerName}</span>
                        {review.verified && (
                          <span className="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full flex items-center gap-1">
                            <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                              <path fillRule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                            Verified
                          </span>
                        )}
                      </div>
                      <div className="text-xs text-gray-500">{review.date}</div>
                    </div>
                  </div>
                  
                  {/* Rating */}
                  <div className="flex gap-0.5">
                    {[...Array(5)].map((_, i) => (
                      <Star 
                        key={i} 
                        className={`w-4 h-4 ${i < review.rating ? 'fill-orange-500 text-orange-500' : 'text-gray-300'}`}
                      />
                    ))}
                  </div>
                </div>

                {/* Review Comment */}
                <p className="text-sm text-gray-700 mb-3 leading-relaxed">
                  {review.comment}
                </p>

                {/* Review Actions */}
                <div className="flex items-center gap-4">
                  <button className="flex items-center gap-1.5 text-xs text-gray-600 hover:text-orange-600 transition-colors">
                    <ThumbsUp className="w-4 h-4" />
                    <span>Helpful ({review.helpful})</span>
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Footer */}
        <div className="border-t bg-gray-50 p-4">
          <Button 
            onClick={onClose}
            className="w-full h-12 bg-gray-900 hover:bg-gray-800 rounded-xl"
          >
            Close
          </Button>
        </div>
      </div>
    </>
  );
}