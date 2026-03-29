import { useState } from 'react';
import { Star } from 'lucide-react';
import { Button } from './ui/button';
import { Textarea } from './ui/textarea';
import { Checkbox } from './ui/checkbox';
import { Label } from './ui/label';

interface ReviewFormProps {
  type: 'order' | 'item';
  itemName?: string;
  onSubmit: (data: any) => void;
  onSkip?: () => void;
}

export function ReviewForm({ type, itemName, onSubmit, onSkip }: ReviewFormProps) {
  const [rating, setRating] = useState(0);
  const [hoveredRating, setHoveredRating] = useState(0);
  const [text, setText] = useState('');
  const [sharePhotos, setSharePhotos] = useState(true);

  const handleSubmit = () => {
    onSubmit({
      rating,
      text,
      photos: [],
      sharePhotos
    });
  };

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-6 space-y-6">
        <div>
          <h2 className="text-2xl">
            {type === 'order' 
              ? 'How was your experience?'
              : `Review ${itemName}`
            }
          </h2>
          {type === 'item' && (
            <p className="text-sm text-gray-600 mt-1">
              Your review helps other customers — thanks!
            </p>
          )}
        </div>

        {/* Star rating */}
        <div className="space-y-2">
          <Label>
            {type === 'order' ? 'Rate your visit' : 'Rate this dish'}
          </Label>
          <div className="flex gap-2">
            {[1, 2, 3, 4, 5].map((star) => (
              <button
                key={star}
                onClick={() => setRating(star)}
                onMouseEnter={() => setHoveredRating(star)}
                onMouseLeave={() => setHoveredRating(0)}
                className="p-1"
              >
                <Star
                  className={`w-10 h-10 ${
                    star <= (hoveredRating || rating)
                      ? 'fill-yellow-400 text-yellow-400'
                      : 'text-gray-300'
                  }`}
                />
              </button>
            ))}
          </div>
        </div>

        {/* Comment */}
        <div className="space-y-2">
          <Label htmlFor="review-text">
            {type === 'order' 
              ? 'Write about your experience (optional)'
              : 'Comment (optional)'
            }
          </Label>
          <Textarea
            id="review-text"
            value={text}
            onChange={(e) => setText(e.target.value)}
            placeholder="Share your thoughts..."
            rows={4}
          />
        </div>

        {/* Photos */}
        <div className="space-y-3">
          <Label>Upload photo(s) (up to 5)</Label>
          <div className="grid grid-cols-3 gap-2">
            {[1, 2, 3].map((i) => (
              <button
                key={i}
                className="aspect-square border-2 border-dashed border-gray-300 rounded-lg hover:border-gray-400 flex items-center justify-center text-gray-400"
              >
                +
              </button>
            ))}
          </div>

          <div className="flex items-center space-x-2">
            <Checkbox
              id="share-photos"
              checked={sharePhotos}
              onCheckedChange={(checked) => setSharePhotos(checked as boolean)}
            />
            <Label htmlFor="share-photos" className="cursor-pointer text-sm">
              Share photos publicly (show on dish page)
            </Label>
          </div>
        </div>

        {/* Actions */}
        <div className="space-y-2">
          <Button
            onClick={handleSubmit}
            disabled={rating === 0}
            className="w-full"
          >
            Submit review
          </Button>
          {onSkip && (
            <Button onClick={onSkip} variant="ghost" className="w-full">
              Skip
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}
