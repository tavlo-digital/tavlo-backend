import { CheckCircle } from 'lucide-react';

interface GuidedTourCompletionModalProps {
  onGoToDashboard: () => void;
  onClose: () => void;
}

export function GuidedTourCompletionModal({ onGoToDashboard, onClose }: GuidedTourCompletionModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div className="flex flex-col items-center text-center">
          <div className="bg-green-100 rounded-full p-3 mb-4">
            <CheckCircle className="w-12 h-12 text-green-600" />
          </div>
          
          <h2 className="text-2xl font-semibold text-gray-900 mb-2">You're all set!</h2>
          
          <p className="text-gray-600 mb-6">
            You can continue configuring your restaurant or go live when ready.
          </p>
          
          <div className="flex flex-col sm:flex-row gap-3 w-full">
            <button
              onClick={onGoToDashboard}
              className="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors"
            >
              Go to dashboard
            </button>
            <button
              onClick={onClose}
              className="flex-1 bg-gray-100 text-gray-700 px-6 py-3 rounded-lg font-medium hover:bg-gray-200 transition-colors"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
