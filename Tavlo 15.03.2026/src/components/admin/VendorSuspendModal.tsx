import { useState } from 'react';
import { X, AlertTriangle } from 'lucide-react';
import { Button } from '../ui/button';

export type SuspendReason = 'non-payment' | 'fraud' | 'legal' | 'manual-admin';

interface VendorSuspendModalProps {
  vendorName: string;
  vendorId: string;
  onConfirm: (reason: SuspendReason, notes?: string) => void;
  onCancel: () => void;
}

export function VendorSuspendModal({ 
  vendorName, 
  vendorId, 
  onConfirm, 
  onCancel 
}: VendorSuspendModalProps) {
  const [selectedReason, setSelectedReason] = useState<SuspendReason | null>(null);
  const [notes, setNotes] = useState('');

  const reasons: { value: SuspendReason; label: string; description: string }[] = [
    {
      value: 'non-payment',
      label: 'Non-Payment',
      description: 'Subscription expired or payment failed'
    },
    {
      value: 'fraud',
      label: 'Fraud',
      description: 'Suspected fraudulent activity'
    },
    {
      value: 'legal',
      label: 'Legal',
      description: 'Legal compliance or regulatory issue'
    },
    {
      value: 'manual-admin',
      label: 'Manual Admin Decision',
      description: 'Other administrative reason'
    }
  ];

  const handleConfirm = () => {
    if (!selectedReason) return;
    onConfirm(selectedReason, notes || undefined);
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
        {/* Header */}
        <div className="flex items-start justify-between p-6 border-b border-gray-200">
          <div className="flex items-start gap-3">
            <div className="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
              <AlertTriangle className="w-5 h-5 text-red-600" />
            </div>
            <div>
              <h2 className="text-lg font-semibold text-gray-900">Suspend Vendor</h2>
              <p className="text-sm text-gray-600 mt-1">
                {vendorName} <span className="text-gray-400">({vendorId})</span>
              </p>
            </div>
          </div>
          <button
            onClick={onCancel}
            className="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6">
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-900 mb-3">
              Suspension Reason <span className="text-red-600">*</span>
            </label>
            <div className="space-y-2">
              {reasons.map((reason) => (
                <button
                  key={reason.value}
                  onClick={() => setSelectedReason(reason.value)}
                  className={`w-full text-left p-3 rounded-lg border-2 transition-all ${
                    selectedReason === reason.value
                      ? 'border-red-600 bg-red-50'
                      : 'border-gray-200 hover:border-gray-300 bg-white'
                  }`}
                >
                  <div className="flex items-start gap-3">
                    <div className={`w-4 h-4 rounded-full border-2 flex-shrink-0 mt-0.5 ${
                      selectedReason === reason.value
                        ? 'border-red-600 bg-red-600'
                        : 'border-gray-300'
                    }`}>
                      {selectedReason === reason.value && (
                        <div className="w-full h-full rounded-full bg-white scale-[0.4]" />
                      )}
                    </div>
                    <div className="flex-1">
                      <div className="font-medium text-gray-900">{reason.label}</div>
                      <div className="text-sm text-gray-600 mt-0.5">{reason.description}</div>
                    </div>
                  </div>
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-900 mb-2">
              Additional Notes <span className="text-gray-500">(Optional)</span>
            </label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="Enter any additional context or notes..."
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 focus:border-transparent text-sm"
            />
          </div>

          {/* Audit notice */}
          <div className="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
            <p className="text-xs text-gray-600">
              <strong>Audit Log:</strong> This action will be recorded in the vendor activity timeline and global audit log.
            </p>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg">
          <Button
            variant="outline"
            onClick={onCancel}
          >
            Cancel
          </Button>
          <Button
            onClick={handleConfirm}
            disabled={!selectedReason}
            className="bg-red-600 hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
          >
            Suspend Vendor
          </Button>
        </div>
      </div>
    </div>
  );
}
