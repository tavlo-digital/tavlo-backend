import { PromoCard } from '../shared/PromoCard';
import { ChevronLeft, ChevronRight, TrendingUp } from 'lucide-react';
import { useState, useEffect } from 'react';

interface Promo {
  id: string;
  image: string;
  title: string;
  description: string;
  ctaText: string;
  ctaAction: () => void;
}

interface PromoBannerProps {
  promos: Promo[];
  showPopularFallback?: boolean;
}

export function PromoBanner({ promos, showPopularFallback = true }: PromoBannerProps) {
  const [currentIndex, setCurrentIndex] = useState(0);

  useEffect(() => {
    if (promos.length > 0) {
      const interval = setInterval(() => {
        setCurrentIndex((prev) => (prev + 1) % promos.length);
      }, 5000);

      return () => clearInterval(interval);
    }
  }, [promos.length]);

  const goToPrevious = () => {
    setCurrentIndex((prev) => (prev - 1 + promos.length) % promos.length);
  };

  const goToNext = () => {
    setCurrentIndex((prev) => (prev + 1) % promos.length);
  };

  // If no promos, show "Popular near you" section
  if (promos.length === 0) {
    if (!showPopularFallback) return null;
    
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <div className="flex items-center gap-2 mb-4">
          <TrendingUp className="w-5 h-5 text-orange-600" />
          <h2 className="text-xl">Popular near you right now</h2>
        </div>
        {/* This will be handled by the restaurant grid below */}
      </div>
    );
  }

  return (
    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
      {/* Carousel Container */}
      <div className="relative overflow-hidden rounded-3xl">
        <div
          className="flex transition-transform duration-500 ease-out"
          style={{ transform: `translateX(-${currentIndex * 100}%)` }}
        >
          {promos.map((promo) => (
            <div key={promo.id} className="w-full shrink-0">
              <PromoCard
                image={promo.image}
                title={promo.title}
                description={promo.description}
                ctaText={promo.ctaText}
                onCtaClick={promo.ctaAction}
              />
            </div>
          ))}
        </div>

        {/* Navigation Buttons */}
        {promos.length > 1 && (
          <>
            <button
              onClick={goToPrevious}
              className="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center hover:bg-white transition-colors shadow-lg"
              aria-label="Previous slide"
            >
              <ChevronLeft className="w-5 h-5" />
            </button>
            <button
              onClick={goToNext}
              className="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center hover:bg-white transition-colors shadow-lg"
              aria-label="Next slide"
            >
              <ChevronRight className="w-5 h-5" />
            </button>
          </>
        )}
      </div>

      {/* Dots Indicator */}
      {promos.length > 1 && (
        <div className="flex justify-center gap-2 mt-4">
          {promos.map((_, index) => (
            <button
              key={index}
              onClick={() => setCurrentIndex(index)}
              className={`h-2 rounded-full transition-all ${
                index === currentIndex 
                  ? 'w-8 bg-orange-500' 
                  : 'w-2 bg-gray-300 hover:bg-gray-400'
              }`}
              aria-label={`Go to slide ${index + 1}`}
            />
          ))}
        </div>
      )}
    </div>
  );
}