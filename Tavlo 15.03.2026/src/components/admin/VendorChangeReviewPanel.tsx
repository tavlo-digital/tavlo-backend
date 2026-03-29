import { useState } from 'react';
import { AlertTriangle, Check, X, Info } from 'lucide-react';
import { toast } from 'sonner@2.0.3';

export interface VendorChangeField {
  field: string;
  label: string;
  currentValue: string;
  newValue: string;
  impact?: string;
}

export interface VendorPendingChange {
  id: string;
  vendorId: string;
  vendorName: string;
  changeType: 'legal' | 'business' | 'contact';
  submittedAt: string;
  submittedBy: string;
  status: 'pending' | 'approved' | 'declined';
  fields: VendorChangeField[];
  notes?: string;
  urgency: 'high' | 'normal';
  impactWarning?: string;
}

interface VendorChangeReviewPanelProps {
  vendorId: string;
  vendorName: string;
  onApprove?: (changeId: string, approvalNotes?: string) => void;
  onDecline?: (changeId: string, declineReason: string) => void;
}

export function VendorChangeReviewPanel({ 
  vendorId,
  vendorName,
  onApprove: onApproveProp, 
  onDecline: onDeclineProp 
}: VendorChangeReviewPanelProps) {
  const [expandedChange, setExpandedChange] = useState<string | null>(null);
  const [declineModalOpen, setDeclineModalOpen] = useState<string | null>(null);
  const [declineReason, setDeclineReason] = useState('');
  const [approvalNotes, setApprovalNotes] = useState('');

  // Mock pending changes - would come from API based on vendorId
  const changes: VendorPendingChange[] = vendorId === 'VID-8492' ? [
    {
      id: 'CHG-001',
      vendorId: 'VID-8492',
      vendorName: 'Bella Italia',
      changeType: 'legal',
      submittedAt: '2 hours ago',
      submittedBy: 'Vendor Owner (Mario Rossi)',
      status: 'pending',
      urgency: 'high',
      impactWarning: 'Your current approved value will remain active on invoices and customer-facing pages until these changes are approved by Tavlo admin.',
      fields: [
        {
          field: 'restaurantName',
          label: 'Restaurant Name',
          currentValue: 'La Bella Vista',
          newValue: 'La Bella Vistag',
          impact: 'Will update restaurant name on all invoices, receipts, and customer-facing pages'
        },
        {
          field: 'businessRegistration',
          label: 'Business Registration Number',
          currentValue: 'FN 1234563',
          newValue: 'FN 123456at',
          impact: 'Must match official business registry - will affect tax compliance'
        },
        {
          field: 'vatNumber',
          label: 'VAT Number',
          currentValue: 'ATU12345678',
          newValue: 'ATU12345678t',
          impact: 'Critical for Austrian VAT compliance - invoices may be rejected if incorrect'
        },
        {
          field: 'legalAddress',
          label: 'Legal Address',
          currentValue: 'Kärntner Straße 1, 1010 Wien, Austria',
          newValue: 'Kärntner Straße 1, 1010 Wien, Agustria',
          impact: 'Must match official business registry'
        }
      ],
      notes: 'We recently updated our legal entity name and registration details with the Austrian authorities. Please approve these changes to ensure our invoices are compliant.'
    }
  ] : [];

  // Set first change as expanded if available
  if (expandedChange === null && changes.length > 0) {
    setTimeout(() => setExpandedChange(changes[0].id), 0);
  }

  const handleApprove = (changeId: string) => {
    if (onApproveProp) {
      onApproveProp(changeId, approvalNotes);
    }
    setApprovalNotes('');
    toast.success('Change approved', {
      description: 'Vendor information has been updated'
    });
  };

  const handleDecline = (changeId: string) => {
    if (!declineReason.trim()) {
      toast.error('Please provide a reason for declining');
      return;
    }
    if (onDeclineProp) {
      onDeclineProp(changeId, declineReason);
    }
    setDeclineModalOpen(null);
    setDeclineReason('');
    toast.success('Change declined', {
      description: 'Vendor has been notified'
    });
  };

  if (changes.length === 0) {
    return (
      <div className="bg-white rounded-lg border border-gray-200 p-12 text-center">
        <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <Check className="w-8 h-8 text-green-600" />
        </div>
        <h3 className="text-lg font-semibold text-gray-900 mb-2">
          No Pending Changes
        </h3>
        <p className="text-gray-600">
          All vendor change requests have been reviewed
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {changes.map((change) => (
        <div 
          key={change.id}
          className="bg-white rounded-lg border border-gray-200 overflow-hidden"
        >
          {/* Change Header */}
          <button
            onClick={() => setExpandedChange(expandedChange === change.id ? null : change.id)}
            className="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
          >
            <div className="flex items-center gap-4">
              {change.urgency === 'high' && (
                <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                  <AlertTriangle className="w-5 h-5 text-orange-600" />
                </div>
              )}
              <div className="text-left">
                <div className="font-semibold text-gray-900">
                  {change.changeType === 'legal' && 'Legal Information Changes'}
                  {change.changeType === 'business' && 'Business Details Changes'}
                  {change.changeType === 'contact' && 'Contact Information Changes'}
                </div>
                <div className="text-sm text-gray-600">
                  Submitted {change.submittedAt} by {change.submittedBy}
                </div>
              </div>
            </div>
            <div className="flex items-center gap-4">
              <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                change.urgency === 'high'
                  ? 'bg-orange-100 text-orange-700'
                  : 'bg-gray-100 text-gray-700'
              }`}>
                {change.urgency === 'high' ? 'Urgent Review' : 'Normal Priority'}
              </span>
            </div>
          </button>

          {/* Change Details (Expanded) */}
          {expandedChange === change.id && (
            <div className="border-t border-gray-200">
              {/* Impact Warning */}
              {change.impactWarning && (
                <div className="px-6 py-4 bg-amber-50 border-b border-amber-100 flex items-start gap-3">
                  <Info className="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" />
                  <div className="text-sm text-amber-800">
                    <span className="font-medium">Impact:</span> {change.impactWarning}
                  </div>
                </div>
              )}

              {/* Field Changes */}
              <div className="px-6 py-4 space-y-4">
                {change.fields.map((field) => (
                  <div key={field.field} className="grid grid-cols-2 gap-4">
                    <div>
                      <div className="text-xs font-medium text-gray-500 uppercase mb-1">
                        {field.label}
                      </div>
                      <div className="text-sm text-gray-900 bg-gray-50 px-3 py-2 rounded border border-gray-200">
                        <span className="text-gray-500">Current: </span>
                        {field.currentValue}
                      </div>
                    </div>
                    <div>
                      <div className="text-xs font-medium text-gray-500 uppercase mb-1">
                        New Value
                      </div>
                      <div className="text-sm text-gray-900 bg-blue-50 px-3 py-2 rounded border border-blue-200 font-medium">
                        {field.newValue}
                      </div>
                    </div>
                    {field.impact && (
                      <div className="col-span-2 text-xs text-gray-600 pl-3 border-l-2 border-amber-300 bg-amber-50 px-3 py-2 rounded">
                        <span className="font-medium">Impact:</span> {field.impact}
                      </div>
                    )}
                  </div>
                ))}
              </div>

              {/* Vendor Notes */}
              {change.notes && (
                <div className="px-6 py-4 bg-gray-50 border-t border-gray-200">
                  <div className="text-xs font-medium text-gray-500 uppercase mb-2">
                    Vendor Notes
                  </div>
                  <div className="text-sm text-gray-700">
                    {change.notes}
                  </div>
                </div>
              )}

              {/* Approval Notes */}
              <div className="px-6 py-4 border-t border-gray-200 space-y-3">
                <div>
                  <label className="block text-xs font-medium text-gray-700 mb-2">
                    Admin Notes (Optional)
                  </label>
                  <textarea
                    value={approvalNotes}
                    onChange={(e) => setApprovalNotes(e.target.value)}
                    placeholder="Add any notes about this approval..."
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none"
                    rows={2}
                  />
                </div>
              </div>

              {/* Action Buttons */}
              <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                <div className="text-xs text-gray-500">
                  Changes will be logged to audit trail
                </div>
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => setDeclineModalOpen(change.id)}
                    className="px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 border border-red-200 rounded-lg transition-colors flex items-center gap-2"
                  >
                    <X className="w-4 h-4" />
                    Decline Changes
                  </button>
                  <button
                    onClick={() => handleApprove(change.id)}
                    className="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors flex items-center gap-2"
                  >
                    <Check className="w-4 h-4" />
                    Approve Changes
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      ))}

      {/* Decline Modal */}
      {declineModalOpen && (
        <>
          <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-lg shadow-xl max-w-md w-full">
              <div className="px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">
                  Decline Change Request
                </h3>
                <p className="text-sm text-gray-600 mt-1">
                  Please provide a reason for declining this change
                </p>
              </div>
              
              <div className="px-6 py-4 space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Decline Reason *
                  </label>
                  <textarea
                    value={declineReason}
                    onChange={(e) => setDeclineReason(e.target.value)}
                    placeholder="Explain why these changes cannot be approved..."
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm resize-none"
                    rows={4}
                    autoFocus
                  />
                </div>
                
                <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                  <Info className="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" />
                  <p className="text-xs text-amber-800">
                    The vendor will be notified with your reason. This action will be logged to the audit trail.
                  </p>
                </div>
              </div>
              
              <div className="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-3">
                <button
                  onClick={() => {
                    setDeclineModalOpen(null);
                    setDeclineReason('');
                  }}
                  className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                  Cancel
                </button>
                <button
                  onClick={() => handleDecline(declineModalOpen)}
                  disabled={!declineReason.trim()}
                  className="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed rounded-lg transition-colors"
                >
                  Decline Changes
                </button>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}