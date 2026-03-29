import { Star, ChevronRight, Building2 } from 'lucide-react';
import { Button } from './ui/button';
import { useState } from 'react';

interface ReviewSelectionProps {
  order: any;
  onBack: () => void;
  onSelectReview: (type: 'restaurant' | 'item', item?: any) => void;
}

export function ReviewSelection({ order, onBack, onSelectReview }: ReviewSelectionProps) {
  return (
    <div className="min-h-screen bg-gray-50 pb-6">
      {/* Header */}
      <div className="bg-white border-b sticky top-0 z-10">
        <div className="max-w-4xl mx-auto p-4">
          <h1 className="text-xl">Write a Review</h1>
        </div>
      </div>

      <div className="max-w-4xl mx-auto p-4 space-y-4">
        <p className="text-gray-600">What would you like to review?</p>

        {/* Review Restaurant Option */}
        <button
          onClick={() => onSelectReview('restaurant')}
          className="w-full bg-white rounded-2xl p-5 flex items-center justify-between hover:bg-gray-50 transition-colors"
        >
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
              <Building2 className="w-6 h-6 text-gray-600" />
            </div>
            <div className="text-left">
              <div className="font-medium">Review the Restaurant</div>
              <div className="text-sm text-gray-500">Overall experience</div>
            </div>
          </div>
          <ChevronRight className="w-5 h-5 text-gray-400" />
        </button>

        {/* Divider */}
        <div className="text-center text-sm text-gray-500 py-2">or review individual items</div>

        {/* Individual Items */}
        <div className="space-y-3">
          {order.items.map((item: any, index: number) => (
            <button
              key={index}
              onClick={() => onSelectReview('item', item)}
              className="w-full bg-white rounded-2xl p-5 flex items-center justify-between hover:bg-gray-50 transition-colors"
            >
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                  <span className="text-lg">{item.quantity}x</span>
                </div>
                <div className="text-left">
                  <div className="font-medium">{item.name}</div>
                  {item.modifiers && item.modifiers.length > 0 && (
                    <div className="text-sm text-gray-500">
                      {item.modifiers.map((m: any) => m.name).join(', ')}
                    </div>
                  )}
                </div>
              </div>
              <ChevronRight className="w-5 h-5 text-gray-400" />
            </button>
          ))}
        </div>

        {/* Back Button */}
        <Button onClick={onBack} variant="outline" className="w-full mt-6">
          Cancel
        </Button>
      </div>
    </div>
  );
}
