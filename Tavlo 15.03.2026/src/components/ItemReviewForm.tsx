import { useState, useEffect } from 'react';
import { Star, ArrowLeft, Send, Upload, X } from 'lucide-react';
import { Button } from './ui/button';
import { Textarea } from './ui/textarea';

interface ItemReviewFormProps {
  order: any;
  onBack: () => void;
  onSubmit: (reviews: Array<{itemId?: string, itemName?: string, rating: number, comment: string, photos?: string[], type: 'item' | 'restaurant'}>) => void;
}

export function ItemReviewForm({ order, onBack, onSubmit }: ItemReviewFormProps) {
  const [itemReviews, setItemReviews] = useState<Record<string, {rating: number, comment: string, photos: string[]}>>({});
  const [restaurantRating, setRestaurantRating] = useState(0);
  const [restaurantComment, setRestaurantComment] = useState('');

  useEffect(() => {
    console.log('ItemReviewForm loaded with order:', order);
    
    // Load existing reviews if they exist
    if (order.reviews && order.reviews.length > 0) {
      console.log('Found existing reviews:', order.reviews);
      const existingReviews: Record<string, {rating: number, comment: string, photos: string[]}> = {};
      
      order.reviews.forEach((review: any) => {
        if (review.itemName) {
          // Item review
          existingReviews[review.itemName] = {
            rating: review.rating,
            comment: review.text || '',
            photos: review.photos || []
          };
        } else if (review.type === 'restaurant') {
          // Restaurant review
          setRestaurantRating(review.rating);
          setRestaurantComment(review.text || '');
        }
      });
      
      console.log('Loaded item reviews:', existingReviews);
      setItemReviews(existingReviews);
    } else {
      console.log('No existing reviews found');
    }
  }, [order]);

  const handleRatingChange = (itemName: string, rating: number) => {
    setItemReviews(prev => ({
      ...prev,
      [itemName]: {
        rating,
        comment: prev[itemName]?.comment || '',
        photos: prev[itemName]?.photos || []
      }
    }));
  };

  const handleCommentChange = (itemName: string, comment: string) => {
    setItemReviews(prev => ({
      ...prev,
      [itemName]: {
        rating: prev[itemName]?.rating || 0,
        comment,
        photos: prev[itemName]?.photos || []
      }
    }));
  };

  const handlePhotoAdd = (itemName: string, photoUrl: string) => {
    setItemReviews(prev => ({
      ...prev,
      [itemName]: {
        rating: prev[itemName]?.rating || 0,
        comment: prev[itemName]?.comment || '',
        photos: [...(prev[itemName]?.photos || []), photoUrl]
      }
    }));
  };

  const handlePhotoRemove = (itemName: string, photoIndex: number) => {
    setItemReviews(prev => ({
      ...prev,
      [itemName]: {
        ...prev[itemName],
        photos: prev[itemName].photos.filter((_, i) => i !== photoIndex)
      }
    }));
  };

  const handleFileUpload = async (itemName: string, file: File) => {
    // Convert to base64 for demo - in production, upload to storage
    const reader = new FileReader();
    reader.onloadend = () => {
      handlePhotoAdd(itemName, reader.result as string);
    };
    reader.readAsDataURL(file);
  };

  const handleSubmit = () => {
    const reviews = Object.entries(itemReviews)
      .filter(([_, review]) => review.rating > 0)
      .map(([itemName, review]) => ({
        itemId: itemName.toLowerCase().replace(/\s+/g, '_'),
        itemName,
        rating: review.rating,
        comment: review.comment,
        photos: review.photos,
        type: 'item' as 'item'
      }));

    if (restaurantRating > 0) {
      reviews.push({
        rating: restaurantRating,
        comment: restaurantComment,
        type: 'restaurant' as 'restaurant'
      });
    }

    if (reviews.length === 0) {
      alert('Please rate at least one item or the restaurant');
      return;
    }

    onSubmit(reviews);
  };

  return (
    <div className="min-h-screen bg-gray-50 pb-6">
      {/* Header */}
      <div className="bg-white border-b sticky top-0 z-10">
        <div className="max-w-4xl mx-auto p-4 flex items-center gap-3">
          <button onClick={onBack} className="p-2 -ml-2 hover:bg-gray-100 rounded-full">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <h1 className="text-xl">Bewertung schreiben</h1>
        </div>
      </div>

      <div className="max-w-4xl mx-auto p-4 space-y-4">
        {/* Restaurant Info */}
        <div className="bg-white rounded-2xl p-5">
          <div className="flex items-start justify-between mb-4">
            <div>
              <h3 className="text-lg">La Bella Vista</h3>
              <div className="text-sm text-gray-500 mt-1">
                {new Date(order.createdAt || Date.now()).toLocaleDateString('de-DE')}
              </div>
            </div>
            {order.reviews && order.reviews.length > 0 && (
              <div className="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm">
                Editing review
              </div>
            )}
          </div>

          {/* Restaurant Overall Rating */}
          <div className="border-t pt-4">
            <p className="text-gray-600 mb-3">How was your overall experience?</p>
            <div className="flex items-center gap-2 mb-3">
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  key={star}
                  onClick={() => setRestaurantRating(star)}
                  className="p-0.5 hover:scale-110 transition-transform"
                >
                  <Star
                    className={`w-8 h-8 ${
                      star <= restaurantRating
                        ? 'fill-yellow-400 text-yellow-400'
                        : 'text-gray-300'
                    }`}
                  />
                </button>
              ))}
              {restaurantRating > 0 && (
                <span className="text-sm text-gray-600 ml-2">
                  {restaurantRating} {restaurantRating === 1 ? 'star' : 'stars'}
                </span>
              )}
            </div>
            {restaurantRating > 0 && (
              <Textarea
                placeholder="Tell us about your experience (optional)..."
                value={restaurantComment}
                onChange={(e) => setRestaurantComment(e.target.value)}
                rows={2}
                className="text-sm"
              />
            )}
          </div>
        </div>

        {/* Review Each Item */}
        <div className="bg-white rounded-2xl p-5 space-y-6">
          <p className="text-gray-600">Rate each item you ordered:</p>
          
          {order.items.map((item: any, index: number) => {
            const itemName = item.name;
            const review = itemReviews[itemName] || { rating: 0, comment: '', photos: [] };

            return (
              <div key={index} className="border-b last:border-b-0 pb-6 last:pb-0">
                {/* Item Name */}
                <div className="mb-3">
                  <div className="font-medium">{item.quantity}x {item.name}</div>
                  {item.modifiers && item.modifiers.length > 0 && (
                    <div className="text-sm text-gray-500 mt-1">
                      {item.modifiers.map((m: any) => m.name).join(', ')}
                    </div>
                  )}
                </div>

                {/* Star Rating */}
                <div className="flex items-center gap-2 mb-3">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <button
                      key={star}
                      onClick={() => handleRatingChange(itemName, star)}
                      className="p-0.5 hover:scale-110 transition-transform"
                    >
                      <Star
                        className={`w-8 h-8 ${
                          star <= review.rating
                            ? 'fill-yellow-400 text-yellow-400'
                            : 'text-gray-300'
                        }`}
                      />
                    </button>
                  ))}
                  {review.rating > 0 && (
                    <span className="text-sm text-gray-600 ml-2">
                      {review.rating} {review.rating === 1 ? 'star' : 'stars'}
                    </span>
                  )}
                </div>

                {/* Comment Field */}
                {review.rating > 0 && (
                  <Textarea
                    placeholder="Write your review (optional)..."
                    value={review.comment}
                    onChange={(e) => handleCommentChange(itemName, e.target.value)}
                    rows={2}
                    className="text-sm"
                  />
                )}

                {/* Photo Upload */}
                {review.rating > 0 && (
                  <div className="mt-3">
                    <label className="text-sm text-gray-600 mb-2 block">Add photos (optional)</label>
                    <div className="flex items-start gap-2 flex-wrap">
                      {/* Uploaded Photos */}
                      {review.photos.map((photo, i) => (
                        <div key={i} className="relative w-20 h-20">
                          <img
                            src={photo}
                            alt={`Review photo ${i + 1}`}
                            className="w-full h-full object-cover rounded-lg"
                          />
                          <button
                            onClick={() => handlePhotoRemove(itemName, i)}
                            className="absolute -top-2 -right-2 p-1 bg-red-500 text-white rounded-full shadow-lg"
                          >
                            <X className="w-3 h-3" />
                          </button>
                        </div>
                      ))}
                      
                      {/* Upload Button (max 5 photos) */}
                      {review.photos.length < 5 && (
                        <label
                          htmlFor={`photo-upload-${itemName}`}
                          className="w-20 h-20 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center cursor-pointer hover:border-gray-400 hover:bg-gray-50 transition-colors"
                        >
                          <Upload className="w-6 h-6 text-gray-400" />
                          <input
                            type="file"
                            accept="image/*"
                            onChange={(e) => {
                              const file = e.target.files?.[0];
                              if (file) {
                                handleFileUpload(itemName, file);
                                e.target.value = ''; // Reset input
                              }
                            }}
                            className="hidden"
                            id={`photo-upload-${itemName}`}
                          />
                        </label>
                      )}
                    </div>
                    {review.photos.length >= 5 && (
                      <p className="text-xs text-gray-500 mt-2">Maximum 5 photos per item</p>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Submit Button */}
        <Button
          onClick={handleSubmit}
          className="w-full"
          disabled={Object.values(itemReviews).every(r => r.rating === 0) && restaurantRating === 0}
        >
          <Send className="w-4 h-4 mr-2" />
          Submit Reviews
        </Button>
      </div>
    </div>
  );
}