import { useState } from 'react';
import { Button } from './ui/button';
import { Textarea } from './ui/textarea';
import { Label } from './ui/label';
import { X, Star, Upload, Image as ImageIcon } from 'lucide-react';
import { Input } from './ui/input';

interface ReviewModalProps {
  restaurantName: string;
  orderItems: any[];
  onSubmit: (reviewData: any) => void;
  onClose: () => void;
}

export function ReviewModal({ restaurantName, orderItems, onSubmit, onClose }: ReviewModalProps) {
  const [step, setStep] = useState<'overall' | 'items'>('overall');
  
  // Overall review
  const [overallRating, setOverallRating] = useState(0);
  const [overallRatingInput, setOverallRatingInput] = useState('');
  const [overallReview, setOverallReview] = useState('');
  const [overallPhotos, setOverallPhotos] = useState<File[]>([]);
  
  // Item reviews
  const [itemReviews, setItemReviews] = useState<{[key: string]: {
    rating: number;
    ratingInput: string;
    comment: string;
    photos: File[];
  }}>({});

  const handleStarClick = (rating: number, isOverall: boolean, itemId?: string) => {
    if (isOverall) {
      setOverallRating(rating);
      setOverallRatingInput(rating.toString());
    } else if (itemId) {
      setItemReviews({
        ...itemReviews,
        [itemId]: {
          ...itemReviews[itemId],
          rating,
          ratingInput: rating.toString()
        }
      });
    }
  };

  const handleRatingInputChange = (value: string, isOverall: boolean, itemId?: string) => {
    const numValue = parseFloat(value);
    if (value === '' || (numValue >= 0 && numValue <= 5)) {
      if (isOverall) {
        setOverallRatingInput(value);
        setOverallRating(numValue || 0);
      } else if (itemId) {
        setItemReviews({
          ...itemReviews,
          [itemId]: {
            ...itemReviews[itemId],
            rating: numValue || 0,
            ratingInput: value
          }
        });
      }
    }
  };

  const handlePhotoUpload = (files: FileList | null, isOverall: boolean, itemId?: string) => {
    if (!files) return;
    
    const newPhotos = Array.from(files);
    
    if (isOverall) {
      setOverallPhotos([...overallPhotos, ...newPhotos]);
    } else if (itemId) {
      const existing = itemReviews[itemId]?.photos || [];
      setItemReviews({
        ...itemReviews,
        [itemId]: {
          ...itemReviews[itemId],
          photos: [...existing, ...newPhotos]
        }
      });
    }
  };

  const removePhoto = (index: number, isOverall: boolean, itemId?: string) => {
    if (isOverall) {
      setOverallPhotos(overallPhotos.filter((_, i) => i !== index));
    } else if (itemId) {
      const existing = itemReviews[itemId]?.photos || [];
      setItemReviews({
        ...itemReviews,
        [itemId]: {
          ...itemReviews[itemId],
          photos: existing.filter((_, i) => i !== index)
        }
      });
    }
  };

  const handleItemCommentChange = (itemId: string, comment: string) => {
    setItemReviews({
      ...itemReviews,
      [itemId]: {
        ...itemReviews[itemId],
        comment
      }
    });
  };

  const canProceedToItems = overallRating > 0;

  const handleSubmit = () => {
    const reviewData = {
      overall: {
        rating: overallRating,
        comment: overallReview,
        photos: overallPhotos
      },
      items: itemReviews
    };
    onSubmit(reviewData);
  };

  const renderStarInput = (rating: number, ratingInput: string, onStarClick: (r: number) => void, onInputChange: (v: string) => void) => (
    <div className="space-y-3">
      {/* Star buttons */}
      <div className="flex items-center gap-2">
        {[1, 2, 3, 4, 5].map((star) => (
          <button
            key={star}
            onClick={() => onStarClick(star)}
            className="transition-transform hover:scale-110"
          >
            <Star
              className={`w-8 h-8 ${
                star <= Math.floor(rating) ? 'fill-orange-500 text-orange-500' : 'text-gray-300'
              }`}
            />
          </button>
        ))}
      </div>
      
      {/* Precise rating input */}
      <div className="flex items-center gap-2">
        <Label className="text-sm text-gray-600">Or enter precise rating:</Label>
        <Input
          type="number"
          step="0.1"
          min="0"
          max="5"
          placeholder="e.g. 4.7"
          value={ratingInput}
          onChange={(e) => onInputChange(e.target.value)}
          className="w-24"
        />
        <span className="text-sm text-gray-600">/ 5.0</span>
      </div>
      
      {rating > 0 && (
        <div className="text-sm text-gray-600">
          Your rating: <span className="text-orange-600">{rating.toFixed(1)} stars</span>
        </div>
      )}
    </div>
  );

  const renderPhotoUpload = (photos: File[], onUpload: (files: FileList | null) => void, onRemove: (index: number) => void) => (
    <div className="space-y-3">
      <Label>Upload Photos (Optional)</Label>
      
      {/* Photo previews */}
      {photos.length > 0 && (
        <div className="grid grid-cols-3 gap-2">
          {photos.map((photo, index) => (
            <div key={index} className="relative aspect-square rounded-lg overflow-hidden bg-gray-100">
              <img
                src={URL.createObjectURL(photo)}
                alt={`Preview ${index + 1}`}
                className="w-full h-full object-cover"
              />
              <button
                onClick={() => onRemove(index)}
                className="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1"
              >
                <X className="w-3 h-3" />
              </button>
            </div>
          ))}
        </div>
      )}
      
      {/* Upload button */}
      <label className="border-2 border-dashed border-gray-300 rounded-xl p-4 flex flex-col items-center justify-center cursor-pointer hover:border-orange-500 hover:bg-orange-50 transition-colors">
        <Upload className="w-6 h-6 text-gray-400 mb-2" />
        <span className="text-sm text-gray-600">Click to upload photos</span>
        <input
          type="file"
          accept="image/*"
          multiple
          onChange={(e) => onUpload(e.target.files)}
          className="hidden"
        />
      </label>
    </div>
  );

  if (step === 'overall') {
    return (
      <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div className="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
          {/* Header */}
          <div className="sticky top-0 bg-white border-b p-4 flex items-center justify-between">
            <h2 className="text-xl">Write a Review</h2>
            <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full">
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="p-6 space-y-6">
            {/* Restaurant name */}
            <div>
              <h3 className="text-lg mb-1">{restaurantName}</h3>
              <p className="text-sm text-gray-600">How was your overall experience?</p>
            </div>

            {/* Rating */}
            <div className="space-y-2">
              <Label>
                Rating <span className="text-red-500">*</span>
              </Label>
              {renderStarInput(
                overallRating,
                overallRatingInput,
                (r) => handleStarClick(r, true),
                (v) => handleRatingInputChange(v, true)
              )}
            </div>

            {/* Review text */}
            <div className="space-y-2">
              <Label>Your Review (Optional)</Label>
              <Textarea
                placeholder="Share your experience..."
                value={overallReview}
                onChange={(e) => setOverallReview(e.target.value)}
                rows={4}
              />
            </div>

            {/* Photos */}
            {renderPhotoUpload(
              overallPhotos,
              (files) => handlePhotoUpload(files, true),
              (index) => removePhoto(index, true)
            )}

            {/* Actions */}
            <div className="space-y-2">
              <Button
                onClick={() => setStep('items')}
                disabled={!canProceedToItems}
                className="w-full"
                size="lg"
              >
                {orderItems.length > 0 ? 'Next: Rate Items' : 'Submit Review'}
              </Button>
              {!canProceedToItems && (
                <p className="text-sm text-red-500 text-center">Overall rating is required</p>
              )}
              <Button onClick={onClose} variant="outline" className="w-full">
                Cancel
              </Button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (step === 'items') {
    return (
      <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div className="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
          {/* Header */}
          <div className="sticky top-0 bg-white border-b p-4 flex items-center justify-between">
            <h2 className="text-xl">Rate Your Items (Optional)</h2>
            <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full">
              <X className="w-5 h-5" />
            </button>
          </div>

          <div className="p-6 space-y-6">
            <p className="text-sm text-gray-600">
              You can rate each item individually. This is optional.
            </p>

            {/* Item reviews */}
            {orderItems.map((item) => {
              const itemReview = itemReviews[item.id] || { rating: 0, ratingInput: '', comment: '', photos: [] };
              
              return (
                <div key={item.id} className="border rounded-xl p-4 space-y-4">
                  <div className="flex items-start gap-3">
                    <ImageIcon className="w-12 h-12 text-gray-400 bg-gray-100 rounded p-2" />
                    <div className="flex-1">
                      <h4>{item.name}</h4>
                      <p className="text-sm text-gray-600">€{item.price.toFixed(2)}</p>
                    </div>
                  </div>

                  {/* Rating */}
                  {renderStarInput(
                    itemReview.rating,
                    itemReview.ratingInput,
                    (r) => handleStarClick(r, false, item.id),
                    (v) => handleRatingInputChange(v, false, item.id)
                  )}

                  {/* Comment */}
                  <div className="space-y-2">
                    <Label className="text-sm">Comment (Optional)</Label>
                    <Textarea
                      placeholder="What did you think of this item?"
                      value={itemReview.comment}
                      onChange={(e) => handleItemCommentChange(item.id, e.target.value)}
                      rows={2}
                    />
                  </div>

                  {/* Photos */}
                  {renderPhotoUpload(
                    itemReview.photos,
                    (files) => handlePhotoUpload(files, false, item.id),
                    (index) => removePhoto(index, false, item.id)
                  )}
                </div>
              );
            })}

            {/* Actions */}
            <div className="space-y-2">
              <Button onClick={handleSubmit} className="w-full" size="lg">
                Submit Review
              </Button>
              <Button onClick={() => setStep('overall')} variant="outline" className="w-full">
                Back
              </Button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return null;
}
