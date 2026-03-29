import { Rocket, X, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface GoLiveModalProps {
  isOpen: boolean;
  onClose: () => void;
  vendorId: string;
  restaurantName?: string;
  onSuccess?: () => void;
}

export function GoLiveModal({ isOpen, onClose, vendorId, restaurantName = 'Your restaurant', onSuccess }: GoLiveModalProps) {
  const handleGoLive = () => {
    sessionStorage.setItem(`vendor-${vendorId}-status`, 'live');
    
    if (onSuccess) {
      onSuccess();
    }
    
    onClose();
    window.location.reload();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-md w-full p-6 relative">
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-gray-400 hover:text-gray-600"
        >
          <X className="w-5 h-5" />
        </button>

        <div className="inline-flex items-center justify-center w-16 h-16 bg-emerald-100 rounded-full mb-4">
          <Rocket className="w-8 h-8 text-emerald-600" />
        </div>

        <h2 className="text-2xl mb-2 text-gray-900">
          Go live with your restaurant?
        </h2>

        <p className="text-gray-700 mb-4">
          <strong>{restaurantName}</strong> will become visible to customers and start accepting orders.
        </p>

        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
          <div className="flex gap-3">
            <AlertCircle className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div className="text-sm text-blue-900">
              <p className="mb-2">
                <strong>What happens when you go live:</strong>
              </p>
              <ul className="list-disc list-inside space-y-1 text-blue-800">
                <li>Your restaurant appears in Tavlo discovery</li>
                <li>QR codes become active for table ordering</li>
                <li>Customers can browse menu and place orders</li>
                <li>Invoices are generated automatically</li>
              </ul>
            </div>
          </div>
        </div>

        <div className="flex gap-3">
          <Button
            onClick={onClose}
            variant="outline"
            className="flex-1"
          >
            Cancel
          </Button>
          <Button
            onClick={handleGoLive}
            className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white"
          >
            <Rocket className="w-4 h-4 mr-2" />
            Go live
          </Button>
        </div>
      </div>
    </div>
  );
}
