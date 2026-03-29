import { Play } from 'lucide-react';

interface ResumeGuidedTourCardProps {
  onResume: () => void;
}

export function ResumeGuidedTourCard({ onResume }: ResumeGuidedTourCardProps) {
  return (
    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between">
      <div className="flex items-center gap-3">
        <div className="bg-blue-100 rounded-lg p-2">
          <Play className="w-5 h-5 text-blue-600" />
        </div>
        <div>
          <h3 className="font-semibold text-gray-900 text-sm">Resume guided tour</h3>
          <p className="text-xs text-gray-600">Continue setting up your restaurant</p>
        </div>
      </div>
      <button
        onClick={onResume}
        className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
      >
        Resume
      </button>
    </div>
  );
}