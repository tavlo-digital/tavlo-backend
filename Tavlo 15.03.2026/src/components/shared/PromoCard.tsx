import { ArrowRight } from 'lucide-react';
import { Button } from '../ui/button';

interface PromoCardProps {
  image: string;
  title: string;
  description: string;
  ctaText: string;
  onCtaClick: () => void;
}

export function PromoCard({ image, title, description, ctaText, onCtaClick }: PromoCardProps) {
  return (
    <div className="relative h-[300px] sm:h-[400px] rounded-3xl overflow-hidden group">
      {/* Background Image */}
      <img
        src={image}
        alt={title}
        className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
      />
      
      {/* Gradient Overlay */}
      <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent" />
      
      {/* Content */}
      <div className="absolute inset-0 flex flex-col justify-end p-6 sm:p-8 md:p-10">
        <h2 className="text-white text-3xl sm:text-4xl md:text-5xl mb-3 max-w-2xl">
          {title}
        </h2>
        <p className="text-white/90 text-base sm:text-lg mb-6 max-w-xl">
          {description}
        </p>
        <div>
          <Button
            onClick={onCtaClick}
            size="lg"
            className="bg-white text-gray-900 hover:bg-gray-100 gap-2"
          >
            {ctaText}
            <ArrowRight className="w-4 h-4" />
          </Button>
        </div>
      </div>
    </div>
  );
}
