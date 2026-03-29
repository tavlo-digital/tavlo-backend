import { Lock, X, Sparkles } from 'lucide-react';
import { Button } from '../ui/button';

interface LockedActionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onActivate: () => void;
}

export function LockedActionModal({ isOpen, onClose, onActivate }: LockedActionModalProps) {
  if (!isOpen) return null;

  return (
    <>
      {/* Overlay */}
      <div 
        className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        onClick={onClose}
      >
        {/* Modal */}
        <div 
          className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 relative"
          onClick={(e) => e.stopPropagation()}
        >
          {/* Close Button */}
          <button
            onClick={onClose}
            className="absolute top-4 right-4 p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="w-5 h-5 text-gray-500" />
          </button>

          {/* Content */}
          <div className="text-center">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-amber-100 rounded-full mb-4">
              <Lock className="w-8 h-8 text-amber-600" />
            </div>
            
            <h3 className="text-2xl mb-2 text-gray-900">This is a demo restaurant</h3>
            
            <p className="text-gray-600 mb-6">
              Subscribe to start using real data and go live with your restaurant
            </p>

            {/* Benefits */}
            <div className="bg-emerald-50 rounded-xl p-4 mb-6 text-left">
              <div className="flex items-center gap-2 mb-3">
                <Sparkles className="w-5 h-5 text-emerald-600" />
                <span className="font-medium text-emerald-900">Unlock with subscription:</span>
              </div>
              <ul className="space-y-2 text-sm text-emerald-800">
                <li className="flex items-center gap-2">
                  <span className="w-1.5 h-1.5 bg-emerald-600 rounded-full"></span>
                  <span>Real-time order management</span>
                </li>
                <li className="flex items-center gap-2">
                  <span className="w-1.5 h-1.5 bg-emerald-600 rounded-full"></span>
                  <span>QR codes for your tables</span>
                </li>
                <li className="flex items-center gap-2">
                  <span className="w-1.5 h-1.5 bg-emerald-600 rounded-full"></span>
                  <span>Live analytics & reporting</span>
                </li>
                <li className="flex items-center gap-2">
                  <span className="w-1.5 h-1.5 bg-emerald-600 rounded-full"></span>
                  <span>Customer ordering system</span>
                </li>
              </ul>
            </div>

            {/* CTA */}
            <Button
              onClick={onActivate}
              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-3"
            >
              Activate my restaurant
            </Button>

            <button
              onClick={onClose}
              className="w-full mt-3 text-sm text-gray-600 hover:text-gray-900"
            >
              Continue exploring demo
            </button>
          </div>
        </div>
      </div>
    </>
  );
}
