import { X, ArrowRight } from 'lucide-react';

interface VendorContextBannerProps {
  source: string;
  description: string;
  onClear: () => void;
}

export function VendorContextBanner({ source, description, onClear }: VendorContextBannerProps) {
  return (
    <div className="bg-purple-50 border-b border-purple-200 px-6 py-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-2 text-sm">
            <span className="font-medium text-purple-900">{description}</span>
            <ArrowRight className="w-4 h-4 text-purple-600" />
            <span className="text-purple-700">Source: {source}</span>
          </div>
        </div>
        <button
          onClick={onClear}
          className="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-purple-700 hover:bg-purple-100 rounded-lg transition-colors"
        >
          <X className="w-4 h-4" />
          Clear filters
        </button>
      </div>
    </div>
  );
}
