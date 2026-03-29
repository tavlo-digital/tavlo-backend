import { RatingStars } from '../shared/RatingStars';
import { Button } from '../ui/button';
import { Edit2, Trash2, MessageSquare } from 'lucide-react';

interface Review {
  id: string;
  restaurantName: string;
  rating: number;
  text: string;
  date: string;
  photos?: string[];
}

interface UserReviewsListProps {
  reviews: Review[];
  onEdit: (reviewId: string) => void;
  onDelete: (reviewId: string) => void;
}

export function UserReviewsList({ reviews, onEdit, onDelete }: UserReviewsListProps) {
  return (
    <div className="bg-white rounded-2xl p-6 border border-gray-100">
      <h2 className="text-2xl mb-4">My Reviews</h2>
      
      {reviews.length === 0 ? (
        <div className="text-center py-12 text-gray-500">
          <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <MessageSquare className="w-8 h-8 text-gray-400" />
          </div>
          <p>No reviews yet</p>
          <p className="text-sm mt-1">Share your dining experiences with others</p>
        </div>
      ) : (
        <div className="space-y-4">
          {reviews.map((review) => (
            <div key={review.id} className="p-4 bg-gray-50 rounded-xl">
              <div className="flex items-start justify-between mb-3">
                <div className="flex-1">
                  <h3 className="text-base mb-1">{review.restaurantName}</h3>
                  <div className="flex items-center gap-3 mb-2">
                    <RatingStars rating={review.rating} size="sm" showNumber={false} />
                    <span className="text-sm text-gray-500">{review.date}</span>
                  </div>
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onEdit(review.id)}
                    className="p-2 h-auto"
                  >
                    <Edit2 className="w-4 h-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onDelete(review.id)}
                    className="p-2 h-auto text-red-600 hover:text-red-700 hover:bg-red-50"
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                </div>
              </div>

              <p className="text-sm text-gray-700 leading-relaxed mb-3">
                {review.text}
              </p>

              {review.photos && review.photos.length > 0 && (
                <div className="flex gap-2 flex-wrap">
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
          ))}
        </div>
      )}
    </div>
  );
}
