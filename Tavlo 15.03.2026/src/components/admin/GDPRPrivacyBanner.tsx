import { useState, useEffect } from 'react';
import { Shield, Lock, Clock, AlertTriangle } from 'lucide-react';

interface GDPRPrivacyBannerProps {
  restrictedDataVisible: boolean;
  onToggleRestricted: (visible: boolean) => void;
  remainingSeconds?: number;
}

export function GDPRPrivacyBanner({ 
  restrictedDataVisible, 
  onToggleRestricted,
  remainingSeconds = 0
}: GDPRPrivacyBannerProps) {
  const [showReasonModal, setShowReasonModal] = useState(false);
  const [selectedReason, setSelectedReason] = useState<string>('');

  const reasons = [
    { value: 'customer-support', label: 'Customer support request' },
    { value: 'fraud-investigation', label: 'Fraud investigation' },
    { value: 'legal-gdpr', label: 'Legal / GDPR request' }
  ];

  const handleToggle = () => {
    if (!restrictedDataVisible) {
      // Show reason modal before enabling
      setShowReasonModal(true);
    } else {
      // Disable immediately
      onToggleRestricted(false);
    }
  };

  const handleConfirmAccess = () => {
    if (!selectedReason) return;
    
    // Log access reason (would be API call in production)
    console.log('AUDIT LOG: Restricted data access enabled', {
      reason: selectedReason,
      timestamp: new Date().toISOString(),
      admin: 'Current Admin User'
    });

    onToggleRestricted(true);
    setShowReasonModal(false);
    setSelectedReason('');
  };

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  return (
    <>
      {/* Main Banner */}
      <div className={`border rounded-lg transition-colors ${
        restrictedDataVisible 
          ? 'bg-amber-50 border-amber-200' 
          : 'bg-blue-50 border-blue-200'
      }`}>
        <div className="px-6 py-4">
          <div className="flex items-start justify-between gap-6">
            {/* Left: Info */}
            <div className="flex items-start gap-3">
              <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${
                restrictedDataVisible 
                  ? 'bg-amber-100' 
                  : 'bg-blue-100'
              }`}>
                {restrictedDataVisible ? (
                  <AlertTriangle className="w-5 h-5 text-amber-600" />
                ) : (
                  <Shield className="w-5 h-5 text-blue-600" />
                )}
              </div>
              <div>
                <h3 className={`font-semibold mb-1 ${
                  restrictedDataVisible 
                    ? 'text-amber-900' 
                    : 'text-blue-900'
                }`}>
                  {restrictedDataVisible 
                    ? '⚠️ Restricted Data Access Active' 
                    : 'Privacy & GDPR Compliance'}
                </h3>
                <p className={`text-sm ${
                  restrictedDataVisible 
                    ? 'text-amber-800' 
                    : 'text-blue-800'
                }`}>
                  {restrictedDataVisible ? (
                    <>
                      Email and phone fields are visible. This access is logged in Audit Trail.
                      {remainingSeconds > 0 && (
                        <span className="ml-2 font-medium">
                          Auto-hide in {formatTime(remainingSeconds)}
                        </span>
                      )}
                    </>
                  ) : (
                    'Customer personal data (email, phone) is hidden by default for GDPR compliance. Enable only when necessary for customer support, fraud investigation, or legal requests.'
                  )}
                </p>
              </div>
            </div>

            {/* Right: Toggle */}
            <div className="flex items-center gap-3">
              {restrictedDataVisible && (
                <div className="flex items-center gap-2 px-3 py-1.5 bg-amber-100 border border-amber-300 rounded-lg">
                  <Clock className="w-4 h-4 text-amber-700" />
                  <span className="text-sm font-medium text-amber-900">
                    {formatTime(remainingSeconds)}
                  </span>
                </div>
              )}
              
              <button
                onClick={handleToggle}
                className={`px-4 py-2 rounded-lg font-medium text-sm transition-colors flex items-center gap-2 ${
                  restrictedDataVisible
                    ? 'bg-amber-600 hover:bg-amber-700 text-white'
                    : 'bg-blue-600 hover:bg-blue-700 text-white'
                }`}
              >
                <Lock className="w-4 h-4" />
                {restrictedDataVisible ? 'Hide Restricted Data' : 'Show Restricted Data'}
              </button>
            </div>
          </div>
        </div>

        {/* Active Access Warning */}
        {restrictedDataVisible && (
          <div className="px-6 py-3 bg-amber-100 border-t border-amber-200 flex items-center gap-2">
            <div className="w-2 h-2 bg-amber-500 rounded-full animate-pulse" />
            <span className="text-xs font-medium text-amber-900">
              Data access logged • Auto-hide after timeout
            </span>
          </div>
        )}
      </div>

      {/* Reason Selection Modal */}
      {showReasonModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div className="px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">
                Confirm Restricted Data Access
              </h3>
              <p className="text-sm text-gray-600 mt-1">
                This action will be logged in the Audit Trail
              </p>
            </div>
            
            <div className="px-6 py-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Reason for Access *
                </label>
                <div className="space-y-2">
                  {reasons.map((reason) => (
                    <label
                      key={reason.value}
                      className="flex items-center gap-3 px-4 py-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors"
                    >
                      <input
                        type="radio"
                        name="reason"
                        value={reason.value}
                        checked={selectedReason === reason.value}
                        onChange={(e) => setSelectedReason(e.target.value)}
                        className="w-4 h-4 text-purple-600"
                      />
                      <span className="text-sm text-gray-900">{reason.label}</span>
                    </label>
                  ))}
                </div>
              </div>

              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-start gap-2">
                <Shield className="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                <p className="text-xs text-blue-800">
                  Restricted data will auto-hide after 10 minutes. Email and phone fields will be visible and highlighted. This access will be logged with your admin ID and timestamp.
                </p>
              </div>
            </div>
            
            <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-3">
              <button
                onClick={() => {
                  setShowReasonModal(false);
                  setSelectedReason('');
                }}
                className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirmAccess}
                disabled={!selectedReason}
                className="px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 disabled:bg-gray-300 disabled:cursor-not-allowed rounded-lg transition-colors"
              >
                Confirm Access
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
