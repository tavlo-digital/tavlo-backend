import { X, Star, MapPin, Calendar, Clock, TrendingUp, DollarSign, Users, Sparkles, MessageSquare } from 'lucide-react';
import { Button } from '../ui/button';
import { AIReviewSummary } from '../ai/AIComponents';
import { analyzeReviews } from '../../utils/aiHelpers';

interface VendorDetailsModalProps {
  vendor: any;
  isOpen: boolean;
  onClose: () => void;
}

// Mock reviews for the vendor
const generateMockReviews = (vendorName: string, reviewCount: number, avgRating: number) => {
  if (reviewCount === 0) return [];
  
  const reviewTexts = {
    high: [
      'Absolutely fantastic experience! The service was impeccable and the atmosphere was wonderful.',
      'Best restaurant in the area! Everything from the food to the staff was amazing.',
      'Highly recommended! The quality is consistently excellent.',
      'Outstanding! We come here regularly and it never disappoints.',
      'Perfect in every way. The attention to detail is remarkable.'
    ],
    medium: [
      'Good overall experience. The food was tasty but service could be faster.',
      'Nice place with good food. A bit pricey but worth it.',
      'Solid restaurant. Nothing extraordinary but consistently good.',
      'Enjoyed our visit. Would come back again.',
      'Good quality food, pleasant atmosphere.'
    ],
    low: [
      'Disappointing experience. The food was cold and service was slow.',
      'Not worth the price. Quality has declined recently.',
      'Average at best. Expected better based on reviews.',
      'Service needs improvement. Long wait times.',
      'Food was okay but not impressive.'
    ]
  };

  const textPool = avgRating >= 4.5 ? reviewTexts.high : avgRating >= 3.5 ? reviewTexts.medium : reviewTexts.low;
  const reviews = [];
  const count = Math.min(reviewCount, 15);

  for (let i = 0; i < count; i++) {
    const rating = avgRating >= 4.5 ? 5 : avgRating >= 3.5 ? Math.floor(Math.random() * 2) + 4 : Math.floor(Math.random() * 3) + 2;
    reviews.push({
      id: `review_${i}`,
      customerName: `Customer ${i + 1}`,
      rating,
      comment: textPool[i % textPool.length],
      date: new Date(Date.now() - Math.random() * 90 * 24 * 60 * 60 * 1000).toISOString(),
      isGuest: Math.random() > 0.5
    });
  }

  return reviews;
};

export function VendorDetailsModal({ vendor, isOpen, onClose }: VendorDetailsModalProps) {
  if (!isOpen) return null;

  // Generate mock reviews
  const reviews = generateMockReviews(vendor.name, vendor.reviewCount, vendor.rating);
  const hasReviews = reviews.length > 0;

  // Calculate stats
  const ratingDistribution = hasReviews ? [5, 4, 3, 2, 1].map(rating => ({
    rating,
    count: reviews.filter(r => r.rating === rating).length,
    percentage: Math.round((reviews.filter(r => r.rating === rating).length / reviews.length) * 100)
  })) : [];

  return (
    <>
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 animate-fade-in"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="fixed inset-x-0 top-0 bottom-0 md:inset-4 md:top-4 md:bottom-4 md:max-w-4xl md:mx-auto bg-white md:rounded-3xl z-50 flex flex-col overflow-hidden animate-slide-up shadow-2xl">
        {/* Header */}
        <div className="relative px-6 pt-6 pb-4 border-b bg-gradient-to-b from-purple-50 to-white">
          <button 
            onClick={onClose}
            className="absolute top-4 right-4 p-2 hover:bg-white rounded-full transition-colors"
          >
            <X className="w-6 h-6" />
          </button>
          
          <div className="mb-4">
            <h2 className="text-2xl mb-1 pr-10">{vendor.name}</h2>
            <p className="text-gray-600">{vendor.type}</p>
          </div>

          {/* Quick Stats */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-white rounded-xl p-3 border border-gray-200">
              <div className="flex items-center gap-2 text-gray-600 text-sm mb-1">
                <MapPin className="w-4 h-4" />
                <span>Location</span>
              </div>
              <p className="font-semibold">{vendor.city}, {vendor.country}</p>
            </div>

            <div className="bg-white rounded-xl p-3 border border-gray-200">
              <div className="flex items-center gap-2 text-gray-600 text-sm mb-1">
                <Star className="w-4 h-4" />
                <span>Rating</span>
              </div>
              <p className="font-semibold">{vendor.rating > 0 ? vendor.rating.toFixed(1) : 'N/A'}</p>
            </div>

            <div className="bg-white rounded-xl p-3 border border-gray-200">
              <div className="flex items-center gap-2 text-gray-600 text-sm mb-1">
                <DollarSign className="w-4 h-4" />
                <span>Revenue</span>
              </div>
              <p className="font-semibold">€{vendor.monthlyRevenue.toLocaleString()}</p>
            </div>

            <div className="bg-white rounded-xl p-3 border border-gray-200">
              <div className="flex items-center gap-2 text-gray-600 text-sm mb-1">
                <Calendar className="w-4 h-4" />
                <span>Joined</span>
              </div>
              <p className="font-semibold">{new Date(vendor.joinedDate).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}</p>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto px-6 py-6">
          {/* Subscription Info */}
          <div className="mb-6">
            <h3 className="text-lg mb-3">Subscription</h3>
            <div className="bg-gray-50 rounded-xl p-4 border border-gray-200">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-sm text-gray-600 mb-1">Plan</p>
                  <p className="font-semibold">{vendor.subscriptionPlan}</p>
                </div>
                <div>
                  <p className="text-sm text-gray-600 mb-1">Status</p>
                  <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
                    vendor.subscriptionStatus === 'paid' ? 'bg-green-100 text-green-700' :
                    vendor.subscriptionStatus === 'trial' ? 'bg-blue-100 text-blue-700' :
                    'bg-red-100 text-red-700'
                  }`}>
                    {vendor.subscriptionStatus.charAt(0).toUpperCase() + vendor.subscriptionStatus.slice(1)}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Reviews Section */}
          <div className="mb-6">
            <h3 className="text-lg mb-3">Customer Reviews</h3>
            
            {hasReviews ? (
              <>
                {/* Rating Overview */}
                <div className="bg-gray-50 rounded-xl p-4 border border-gray-200 mb-4">
                  <div className="flex items-center gap-6">
                    <div className="text-center">
                      <div className="text-4xl font-semibold mb-1">{vendor.rating.toFixed(1)}</div>
                      <div className="flex gap-0.5 mb-1">
                        {[...Array(5)].map((_, i) => (
                          <Star 
                            key={i} 
                            className={`w-4 h-4 ${i < Math.round(vendor.rating) ? 'fill-orange-500 text-orange-500' : 'text-gray-300'}`}
                          />
                        ))}
                      </div>
                      <div className="text-xs text-gray-600">{vendor.reviewCount} reviews</div>
                    </div>
                    
                    {/* Rating Distribution */}
                    <div className="flex-1 space-y-1.5">
                      {ratingDistribution.map((item) => (
                        <div key={item.rating} className="flex items-center gap-2 text-sm">
                          <div className="flex items-center gap-1 w-12">
                            <span className="text-xs text-gray-600">{item.rating}</span>
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

                {/* AI Review Summary */}
                <div className="bg-gradient-to-br from-purple-50 to-purple-100/50 rounded-2xl p-4 border border-purple-200/50 mb-4">
                  <div className="flex items-center gap-2 mb-3">
                    <Sparkles className="w-5 h-5 text-purple-600" />
                    <h4 className="text-sm font-semibold">AI Review Summary</h4>
                  </div>
                  {(() => {
                    const reviewAnalysis = analyzeReviews(reviews);
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

                {/* Recent Reviews */}
                <div>
                  <h4 className="text-sm font-semibold mb-3">Recent Reviews</h4>
                  <div className="space-y-3">
                    {reviews.slice(0, 5).map((review) => (
                      <div 
                        key={review.id} 
                        className="bg-white border border-gray-200 rounded-xl p-4"
                      >
                        <div className="flex items-start justify-between mb-2">
                          <div>
                            <div className="font-medium text-sm">{review.customerName}</div>
                            <div className="text-xs text-gray-500">
                              {new Date(review.date).toLocaleDateString('en-US', {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric'
                              })}
                            </div>
                          </div>
                          <div className="flex items-center gap-0.5">
                            {[...Array(5)].map((_, i) => (
                              <Star
                                key={i}
                                className={`w-3 h-3 ${
                                  i < review.rating ? 'fill-orange-500 text-orange-500' : 'text-gray-300'
                                }`}
                              />
                            ))}
                          </div>
                        </div>
                        <p className="text-sm text-gray-700">{review.comment}</p>
                      </div>
                    ))}
                  </div>
                </div>
              </>
            ) : (
              <div className="bg-gray-50 rounded-xl p-8 border border-gray-200 text-center">
                <MessageSquare className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                <p className="text-gray-600">No reviews yet</p>
                <p className="text-sm text-gray-500 mt-1">This vendor hasn't received any customer reviews.</p>
              </div>
            )}
          </div>

          {/* Activity Info */}
          <div>
            <h3 className="text-lg mb-3">Activity</h3>
            <div className="bg-gray-50 rounded-xl p-4 border border-gray-200">
              <div className="flex items-center gap-2 text-gray-600 mb-1">
                <Clock className="w-4 h-4" />
                <span className="text-sm">Last Active</span>
              </div>
              <p className="font-semibold">{vendor.lastActive}</p>
            </div>
          </div>
        </div>

        {/* Footer Actions */}
        <div className="border-t bg-white px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
              vendor.status === 'active' ? 'bg-green-100 text-green-700' :
              vendor.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
              'bg-red-100 text-red-700'
            }`}>
              {vendor.status.charAt(0).toUpperCase() + vendor.status.slice(1)}
            </span>
            <span className="text-sm text-gray-600">ID: {vendor.id}</span>
          </div>
          
          <div className="flex gap-2">
            {vendor.status === 'active' && (
              <Button variant="outline" size="sm" className="text-red-600 border-red-600 hover:bg-red-50">
                Suspend
              </Button>
            )}
            {vendor.status === 'pending' && (
              <Button size="sm" className="bg-green-600 hover:bg-green-700 text-white">
                Approve
              </Button>
            )}
            <Button variant="outline" size="sm" onClick={onClose}>
              Close
            </Button>
          </div>
        </div>
      </div>
    </>
  );
}
