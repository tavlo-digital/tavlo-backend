import { useState } from 'react';
import { 
  Shield, 
  AlertTriangle, 
  Info, 
  CheckCircle,
  Clock,
  Ban,
  Zap,
  ExternalLink,
  Lock
} from 'lucide-react';
import { toast } from 'sonner@2.0.3';

interface SubscriptionGovernanceProps {
  isSuperAdmin?: boolean;
}

export function SubscriptionGovernance({ isSuperAdmin = true }: SubscriptionGovernanceProps) {
  const [trialDuration, setTrialDuration] = useState<number>(14);
  const [gracePeriod, setGracePeriod] = useState<number>(7);
  const [showTrialConfirmation, setShowTrialConfirmation] = useState(false);
  const [showGraceConfirmation, setShowGraceConfirmation] = useState(false);
  const [changeReason, setChangeReason] = useState('');
  const [pendingTrialChange, setPendingTrialChange] = useState<number | null>(null);
  const [pendingGraceChange, setPendingGraceChange] = useState<number | null>(null);

  const handleTrialDurationChange = (newValue: number) => {
    if (!isSuperAdmin) {
      toast.error('Access denied', {
        description: 'Only Super Admin can modify subscription governance rules'
      });
      return;
    }
    setPendingTrialChange(newValue);
    setShowTrialConfirmation(true);
  };

  const confirmTrialChange = () => {
    if (!changeReason.trim()) {
      toast.error('Reason required', {
        description: 'Please provide a reason for this change'
      });
      return;
    }

    if (changeReason.trim().length < 10) {
      toast.error('Reason too short', {
        description: 'Please provide a detailed reason (minimum 10 characters)'
      });
      return;
    }

    // Audit log
    console.log('AUDIT LOG: Trial duration changed', {
      setting: 'trialDuration',
      before: trialDuration,
      after: pendingTrialChange,
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString(),
      reason: changeReason
    });

    setTrialDuration(pendingTrialChange!);
    setShowTrialConfirmation(false);
    setPendingTrialChange(null);
    setChangeReason('');

    toast.success('Trial duration updated', {
      description: 'Change logged to audit trail'
    });
  };

  const handleGracePeriodChange = (newValue: number) => {
    if (!isSuperAdmin) {
      toast.error('Access denied', {
        description: 'Only Super Admin can modify subscription governance rules'
      });
      return;
    }
    setPendingGraceChange(newValue);
    setShowGraceConfirmation(true);
  };

  const confirmGraceChange = () => {
    if (!changeReason.trim()) {
      toast.error('Reason required', {
        description: 'Please provide a reason for this change'
      });
      return;
    }

    if (changeReason.trim().length < 10) {
      toast.error('Reason too short', {
        description: 'Please provide a detailed reason (minimum 10 characters)'
      });
      return;
    }

    // Audit log
    console.log('AUDIT LOG: Grace period changed', {
      setting: 'gracePeriod',
      before: gracePeriod,
      after: pendingGraceChange,
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString(),
      reason: changeReason
    });

    setGracePeriod(pendingGraceChange!);
    setShowGraceConfirmation(false);
    setPendingGraceChange(null);
    setChangeReason('');

    toast.success('Grace period updated', {
      description: 'Change logged to audit trail'
    });
  };

  return (
    <div className="p-6 space-y-6">
      {/* Page Header */}
      <div>
        <h2 className="text-lg font-medium mb-2">Subscription Governance</h2>
        <p className="text-sm text-gray-500">
          Platform-wide rules for subscription enforcement and lifecycle
        </p>
      </div>

      {/* Platform Governance Banner (Strengthened) */}
      <div className="bg-purple-50 border-2 border-purple-300 rounded-xl p-4">
        <div className="flex items-start gap-3">
          <Shield className="w-5 h-5 text-purple-600 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-semibold text-purple-900 mb-1">
              Global Enforcement Rules Only
            </h3>
            <p className="text-sm text-purple-800 mb-2">
              <strong>These settings define global subscription enforcement rules.</strong>
              {' '}Subscription plans, features, and pricing are managed in{' '}
              <a href="#" className="underline font-medium">Subscription Management</a>.
              {' '}<strong className="text-purple-900">Vendors are charged automatically based on their selected billing cycle.</strong>
            </p>
            <div className="flex items-center gap-2 mt-2">
              <Lock className="w-4 h-4 text-purple-700" />
              <span className="text-xs font-medium text-purple-900">Super Admin Only</span>
            </div>
          </div>
        </div>
      </div>

      {/* Permission Notice for Non-Super Admin */}
      {!isSuperAdmin && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <Lock className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="font-medium text-amber-900 mb-1">Read-Only Access</h3>
              <p className="text-sm text-amber-800">
                You have read-only access to subscription governance. 
                Only Super Admin can modify enforcement rules.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Auto-Enforcement Summary (New Section) */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start gap-3">
          <Info className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-semibold text-blue-900 mb-2">
              Automated Enforcement Policy
            </h3>
            <ul className="space-y-1.5 text-sm text-blue-800">
              <li className="flex items-start gap-2">
                <CheckCircle className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                <span>Subscriptions are charged automatically on billing cycle date</span>
              </li>
              <li className="flex items-start gap-2">
                <CheckCircle className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                <span>Failed payments trigger grace period automatically</span>
              </li>
              <li className="flex items-start gap-2">
                <CheckCircle className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                <span>Grace period expiry suspends vendor and sets status to Not Live</span>
              </li>
              <li className="flex items-start gap-2">
                <CheckCircle className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                <span>No payment reminders or manual billing actions are sent</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      {/* Navigation Guardrails */}
      <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <h3 className="text-sm font-semibold text-gray-900 mb-3">Related Settings</h3>
        <div className="space-y-2">
          <a 
            href="#" 
            onClick={(e) => {
              e.preventDefault();
              toast.info('Navigation to Subscription Management');
            }}
            className="flex items-center justify-between text-sm text-gray-700 hover:text-purple-600 group"
          >
            <span>Manage plans and pricing</span>
            <ExternalLink className="w-4 h-4 text-gray-400 group-hover:text-purple-600" />
          </a>
          <a 
            href="#" 
            onClick={(e) => {
              e.preventDefault();
              toast.info('Navigation to Vendor Management');
            }}
            className="flex items-center justify-between text-sm text-gray-700 hover:text-purple-600 group"
          >
            <span>View vendor subscriptions</span>
            <ExternalLink className="w-4 h-4 text-gray-400 group-hover:text-purple-600" />
          </a>
        </div>
      </div>

      {/* Trial Period Rules */}
      <div className="border border-gray-200 rounded-xl p-5">
        <div className="flex items-start gap-3 mb-4">
          <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
            <Zap className="w-5 h-5 text-green-600" />
          </div>
          <div className="flex-1">
            <h3 className="font-semibold text-gray-900">Trial Period Configuration</h3>
            <p className="text-sm text-gray-600 mt-0.5">
              Global trial period for new vendor signups
            </p>
          </div>
        </div>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Trial Duration
            </label>
            <select
              value={trialDuration}
              onChange={(e) => handleTrialDurationChange(Number(e.target.value))}
              disabled={!isSuperAdmin}
              className={`w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg text-sm ${
                isSuperAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
              }`}
            >
              <option value={7}>7 days</option>
              <option value={14}>14 days</option>
              <option value={30}>30 days</option>
            </select>
            <p className="text-xs text-gray-500 mt-2">
              Currently set to: <strong>{trialDuration} days</strong>
            </p>
          </div>

          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p className="text-xs text-gray-600 mb-2">
              <strong>Trial applies to:</strong> New vendors only (read-only)
            </p>
            <p className="text-xs text-gray-600">
              <strong>After trial expires:</strong>
            </p>
            <ul className="text-xs text-gray-600 mt-1 ml-4 space-y-0.5">
              <li>• Vendor subscription automatically starts</li>
              <li>• Payment is attempted immediately</li>
              <li>• No reminders are sent</li>
            </ul>
          </div>

          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <AlertTriangle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-amber-800">
                <strong>Platform-Wide Rule:</strong> Trial rules apply globally and cannot be overridden per vendor.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Payment Grace Period Rules */}
      <div className="border border-gray-200 rounded-xl p-5">
        <div className="flex items-start gap-3 mb-4">
          <div className="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
            <Clock className="w-5 h-5 text-orange-600" />
          </div>
          <div className="flex-1">
            <h3 className="font-semibold text-gray-900">Payment Grace Period Configuration</h3>
            <p className="text-sm text-gray-600 mt-0.5">
              Automatic enforcement after failed payments
            </p>
          </div>
        </div>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Grace Period Duration
            </label>
            <select
              value={gracePeriod}
              onChange={(e) => handleGracePeriodChange(Number(e.target.value))}
              disabled={!isSuperAdmin}
              className={`w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg text-sm ${
                isSuperAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
              }`}
            >
              <option value={0}>0 days (immediate suspension)</option>
              <option value={3}>3 days</option>
              <option value={7}>7 days</option>
              <option value={14}>14 days</option>
            </select>
            <p className="text-xs text-gray-500 mt-2">
              Currently set to: <strong>{gracePeriod} days</strong>
            </p>
          </div>

          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p className="text-xs text-gray-600 mb-2">
              <strong>If payment fails:</strong>
            </p>
            <ul className="text-xs text-gray-600 ml-4 space-y-0.5 mb-3">
              <li>• Vendor remains active during grace period</li>
              <li>• Vendor can continue taking orders</li>
              <li>• Grace period countdown begins immediately</li>
            </ul>
            <p className="text-xs text-gray-600 mb-2">
              <strong>After grace period expires:</strong>
            </p>
            <ul className="text-xs text-gray-600 ml-4 space-y-0.5">
              <li>• Vendor is automatically suspended</li>
              <li>• Vendor status set to Not Live</li>
              <li>• No admin action required</li>
            </ul>
          </div>

          <div className="bg-red-50 border border-red-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <Ban className="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-red-800">
                <strong>Automatic Suspension:</strong> Grace period expiration results in immediate, automatic vendor suspension. No manual intervention occurs.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Trial Duration Confirmation Modal */}
      {showTrialConfirmation && (
        <GovernanceChangeModal
          title="Change Trial Duration"
          description={`Change trial period from ${trialDuration} days to ${pendingTrialChange} days?`}
          impactWarning="This affects all future vendor signups. Existing vendors are not affected."
          changeReason={changeReason}
          setChangeReason={setChangeReason}
          onClose={() => {
            setShowTrialConfirmation(false);
            setPendingTrialChange(null);
            setChangeReason('');
          }}
          onConfirm={confirmTrialChange}
        />
      )}

      {/* Grace Period Confirmation Modal */}
      {showGraceConfirmation && (
        <GovernanceChangeModal
          title="Change Grace Period"
          description={`Change grace period from ${gracePeriod} days to ${pendingGraceChange} days?`}
          impactWarning="This affects all future payment failures. Vendors currently in grace period are not affected."
          changeReason={changeReason}
          setChangeReason={setChangeReason}
          onClose={() => {
            setShowGraceConfirmation(false);
            setPendingGraceChange(null);
            setChangeReason('');
          }}
          onConfirm={confirmGraceChange}
        />
      )}
    </div>
  );
}

// Governance Change Confirmation Modal
interface GovernanceChangeModalProps {
  title: string;
  description: string;
  impactWarning: string;
  changeReason: string;
  setChangeReason: (reason: string) => void;
  onClose: () => void;
  onConfirm: () => void;
}

function GovernanceChangeModal({
  title,
  description,
  impactWarning,
  changeReason,
  setChangeReason,
  onClose,
  onConfirm
}: GovernanceChangeModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-lg w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
            <AlertTriangle className="w-6 h-6 text-purple-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
            <p className="text-sm text-gray-600">Platform-wide governance change</p>
          </div>
        </div>

        <div className="space-y-4">
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p className="text-sm text-gray-900 font-medium mb-2">
              {description}
            </p>
            <div className="bg-blue-50 border border-blue-200 rounded p-2 mt-2">
              <div className="flex items-start gap-2">
                <Info className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                <p className="text-xs text-blue-800">{impactWarning}</p>
              </div>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Reason for Change <span className="text-red-500">*</span>
            </label>
            <textarea
              value={changeReason}
              onChange={(e) => setChangeReason(e.target.value)}
              placeholder="Explain why this governance rule is being changed (minimum 10 characters)"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 text-sm resize-none"
              rows={3}
            />
            <p className="text-xs text-gray-500 mt-1">
              {changeReason.length}/10 characters minimum
            </p>
          </div>

          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <AlertTriangle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-amber-800">
                This change will be logged to the audit trail with your admin ID, timestamp, and reason.
              </p>
            </div>
          </div>
        </div>

        <div className="flex gap-3 mt-6">
          <button
            onClick={onClose}
            className="flex-1 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            onClick={onConfirm}
            disabled={changeReason.trim().length < 10}
            className={`flex-1 px-4 py-2 text-sm font-medium rounded-lg ${
              changeReason.trim().length < 10
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                : 'bg-purple-600 text-white hover:bg-purple-700'
            }`}
          >
            Confirm Change
          </button>
        </div>
      </div>
    </div>
  );
}
