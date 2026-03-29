import { useState } from 'react';
import { 
  Shield, 
  Lock, 
  CheckCircle, 
  AlertTriangle, 
  Info, 
  FileText,
  Database,
  Clock,
  Eye,
  Trash2,
  Download,
  Users
} from 'lucide-react';
import { toast } from 'sonner@2.0.3';

type RequestHandlingMode = 'automatic' | 'manual';
type DeletionMethod = 'full' | 'anonymization';
type ExportFormat = 'json' | 'csv';
type DeliveryMethod = 'download' | 'email';

interface ComplianceDataPrivacyProps {
  isComplianceAdmin?: boolean;
}

export function ComplianceDataPrivacy({ isComplianceAdmin = true }: ComplianceDataPrivacyProps) {
  // GDPR handling configuration
  const [accessRequestMode, setAccessRequestMode] = useState<RequestHandlingMode>('automatic');
  const [deletionMethod, setDeletionMethod] = useState<DeletionMethod>('anonymization');
  const [exportFormat, setExportFormat] = useState<ExportFormat>('json');
  const [deliveryMethod, setDeliveryMethod] = useState<DeliveryMethod>('download');

  // Data retention
  const [orderDataRetention, setOrderDataRetention] = useState<number>(7); // years
  const [guestDataRetention, setGuestDataRetention] = useState<number>(90); // days
  const [auditLogRetention, setAuditLogRetention] = useState<number>(7); // years

  // Anonymization rules
  const [autoAnonymizeGuests, setAutoAnonymizeGuests] = useState(true);
  const [preserveAggregatedAnalytics, setPreserveAggregatedAnalytics] = useState(true);

  // Optional audit logging
  const [logVendorChanges, setLogVendorChanges] = useState(true);
  const [logSystemConfig, setLogSystemConfig] = useState(false);

  // Modal states
  const [showRetentionChange, setShowRetentionChange] = useState<{
    type: 'order' | 'guest' | 'audit';
    newValue: number;
  } | null>(null);
  const [showAnonymizationChange, setShowAnonymizationChange] = useState(false);
  const [changeReason, setChangeReason] = useState('');

  const handleRetentionChange = (type: 'order' | 'guest' | 'audit', newValue: number) => {
    if (!isComplianceAdmin) {
      toast.error('Access denied', {
        description: 'Only Compliance Admin or Super Admin can modify compliance settings'
      });
      return;
    }

    // Validate legal minimums
    if (type === 'order' && newValue < 7) {
      toast.error('Legal minimum not met', {
        description: 'Order and financial data must be retained for at least 7 years'
      });
      return;
    }

    setShowRetentionChange({ type, newValue });
  };

  const confirmRetentionChange = () => {
    if (!changeReason.trim() || changeReason.trim().length < 10) {
      toast.error('Reason required', {
        description: 'Please provide a detailed reason (minimum 10 characters)'
      });
      return;
    }

    const { type, newValue } = showRetentionChange!;
    const oldValue = type === 'order' ? orderDataRetention : 
                     type === 'guest' ? guestDataRetention : 
                     auditLogRetention;

    // Audit log
    console.log('AUDIT LOG: Data retention policy changed', {
      setting: `${type}DataRetention`,
      before: oldValue,
      after: newValue,
      admin: 'Current Compliance Admin',
      timestamp: new Date().toISOString(),
      reason: changeReason
    });

    if (type === 'order') setOrderDataRetention(newValue);
    else if (type === 'guest') setGuestDataRetention(newValue);
    else setAuditLogRetention(newValue);

    setShowRetentionChange(null);
    setChangeReason('');

    toast.success('Retention policy updated', {
      description: 'Change logged to audit trail'
    });
  };

  return (
    <div className="p-6 space-y-6">
      {/* Page Header */}
      <div>
        <h2 className="text-lg font-medium mb-2">Compliance & Data Privacy</h2>
        <p className="text-sm text-gray-500">
          Configure how Tavlo fulfills mandatory GDPR and data protection obligations
        </p>
      </div>

      {/* Mandatory Compliance Notice (Legal Banner) */}
      <div className="bg-blue-50 border-2 border-blue-300 rounded-xl p-4">
        <div className="flex items-start gap-3">
          <Shield className="w-5 h-5 text-blue-700 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-semibold text-blue-900 mb-1">
              Legal Compliance Requirements
            </h3>
            <p className="text-sm text-blue-800">
              <strong>Tavlo operates under GDPR and applicable EU data protection laws.</strong>
              {' '}User rights such as data access, deletion requests, and data portability 
              <strong className="text-blue-900"> cannot be disabled</strong>.
              {' '}Settings on this page define <strong className="text-blue-900">how requests are handled</strong>, 
              not whether they exist.
            </p>
          </div>
        </div>
      </div>

      {/* Permission Notice for Non-Compliance Admin */}
      {!isComplianceAdmin && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <Lock className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="font-medium text-amber-900 mb-1">Read-Only Access</h3>
              <p className="text-sm text-amber-800">
                You have read-only access to compliance settings. 
                Only Compliance Admin or Super Admin can modify GDPR handling configurations.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Role & Permission Enforcement (Read-Only Block) */}
      <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <h3 className="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <Users className="w-4 h-4" />
          Who Can Perform GDPR Actions
        </h3>
        <div className="space-y-2">
          <div className="flex items-center gap-2 text-sm">
            <CheckCircle className="w-4 h-4 text-green-600" />
            <span className="text-gray-700">
              <strong>Compliance Admin:</strong> Full access to GDPR handling and compliance settings
            </span>
          </div>
          <div className="flex items-center gap-2 text-sm">
            <CheckCircle className="w-4 h-4 text-green-600" />
            <span className="text-gray-700">
              <strong>Super Admin:</strong> Full access to all compliance and privacy settings
            </span>
          </div>
          <div className="flex items-center gap-2 text-sm">
            <Lock className="w-4 h-4 text-gray-400" />
            <span className="text-gray-600">
              <strong>All other roles:</strong> View only, no execution rights
            </span>
          </div>
        </div>
      </div>

      {/* GDPR User Rights — Handling Configuration */}
      <div className="border-t border-gray-200 pt-6">
        <div className="mb-4">
          <h3 className="font-semibold text-gray-900">GDPR User Rights Handling</h3>
          <p className="text-sm text-gray-600 mt-1">
            Configure how Tavlo processes mandatory GDPR rights. These rights cannot be disabled.
          </p>
        </div>

        <div className="space-y-4">
          {/* Right of Access (GDPR Art. 15) */}
          <div className="border border-gray-200 rounded-xl p-5 bg-white">
            <div className="flex items-start gap-3 mb-4">
              <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <Eye className="w-5 h-5 text-blue-600" />
              </div>
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <h4 className="font-semibold text-gray-900">Right of Access</h4>
                  <Lock className="w-4 h-4 text-green-600" title="Mandatory by GDPR Art. 15" />
                  <span className="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-medium">
                    Always Enabled
                  </span>
                </div>
                <p className="text-sm text-gray-600">GDPR Article 15</p>
              </div>
            </div>

            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Request Handling Mode
                </label>
                <select
                  value={accessRequestMode}
                  onChange={(e) => setAccessRequestMode(e.target.value as RequestHandlingMode)}
                  disabled={!isComplianceAdmin}
                  className={`w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm ${
                    isComplianceAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
                  }`}
                >
                  <option value="automatic">Automatic export</option>
                  <option value="manual">Manual review (Support / Compliance Admin)</option>
                </select>
              </div>

              <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <div className="flex items-start gap-2">
                  <Clock className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                  <div className="text-xs text-blue-800">
                    <strong>SLA Requirement:</strong> Response required within 30 days
                  </div>
                </div>
              </div>

              <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                <p className="text-xs text-gray-700">
                  <strong>Legal Obligation:</strong> Users have the legal right to request a copy 
                  of their personal data. Tavlo must respond within 30 days as required by GDPR.
                </p>
              </div>
            </div>
          </div>

          {/* Right to Erasure (GDPR Art. 17) */}
          <div className="border border-gray-200 rounded-xl p-5 bg-white">
            <div className="flex items-start gap-3 mb-4">
              <div className="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                <Trash2 className="w-5 h-5 text-red-600" />
              </div>
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <h4 className="font-semibold text-gray-900">Right to Erasure</h4>
                  <Lock className="w-4 h-4 text-green-600" title="Mandatory by GDPR Art. 17" />
                  <span className="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-medium">
                    Always Available
                  </span>
                </div>
                <p className="text-sm text-gray-600">GDPR Article 17 ("Right to be Forgotten")</p>
              </div>
            </div>

            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Deletion Method
                </label>
                <select
                  value={deletionMethod}
                  onChange={(e) => setDeletionMethod(e.target.value as DeletionMethod)}
                  disabled={!isComplianceAdmin}
                  className={`w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm ${
                    isComplianceAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
                  }`}
                >
                  <option value="full">Full deletion where legally permitted</option>
                  <option value="anonymization">Anonymization where legal retention applies</option>
                </select>
              </div>

              <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <div className="flex items-start gap-2">
                  <AlertTriangle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                  <div className="text-xs text-amber-800">
                    <strong>Legal Retention Disclaimer:</strong> Financial and tax records are 
                    retained as required by law (minimum 7 years). These records will be anonymized 
                    but cannot be fully deleted.
                  </div>
                </div>
              </div>

              <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                <p className="text-xs text-gray-700">
                  <strong>Important:</strong> Some data cannot be deleted due to legal obligations 
                  (tax, accounting, fraud prevention) and will be anonymized instead.
                </p>
              </div>
            </div>
          </div>

          {/* Data Portability (GDPR Art. 20) */}
          <div className="border border-gray-200 rounded-xl p-5 bg-white">
            <div className="flex items-start gap-3 mb-4">
              <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <Download className="w-5 h-5 text-purple-600" />
              </div>
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <h4 className="font-semibold text-gray-900">Data Portability</h4>
                  <Lock className="w-4 h-4 text-green-600" title="Mandatory by GDPR Art. 20" />
                  <span className="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-medium">
                    Always Enabled
                  </span>
                </div>
                <p className="text-sm text-gray-600">GDPR Article 20</p>
              </div>
            </div>

            <div className="space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Export Format
                </label>
                <select
                  value={exportFormat}
                  onChange={(e) => setExportFormat(e.target.value as ExportFormat)}
                  disabled={!isComplianceAdmin}
                  className={`w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm ${
                    isComplianceAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
                  }`}
                >
                  <option value="json">JSON (structured, machine-readable)</option>
                  <option value="csv">CSV (spreadsheet compatible)</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Delivery Method
                </label>
                <select
                  value={deliveryMethod}
                  onChange={(e) => setDeliveryMethod(e.target.value as DeliveryMethod)}
                  disabled={!isComplianceAdmin}
                  className={`w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm ${
                    isComplianceAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
                  }`}
                >
                  <option value="download">Secure download link (expires in 7 days)</option>
                  <option value="email">Encrypted email attachment</option>
                </select>
              </div>

              <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                <p className="text-xs text-gray-700">
                  <strong>Legal Requirement:</strong> Data must be provided in a structured, 
                  machine-readable format that allows users to transmit it to another service.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Data Retention Policies */}
      <div className="border-t border-gray-200 pt-6">
        <div className="mb-4">
          <h3 className="font-semibold text-gray-900">Data Retention Policies</h3>
          <p className="text-sm text-gray-600 mt-1">
            Configure retention periods for different data types (legal minimums enforced)
          </p>
        </div>

        <div className="space-y-4">
          {/* Order & Financial Data Retention */}
          <div className="border border-gray-200 rounded-lg p-4">
            <div className="flex items-start gap-3 mb-3">
              <Database className="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
              <div className="flex-1">
                <label className="block text-sm font-medium text-gray-900 mb-1">
                  Order & Financial Data Retention
                </label>
                <select
                  value={orderDataRetention}
                  onChange={(e) => handleRetentionChange('order', Number(e.target.value))}
                  disabled={!isComplianceAdmin}
                  className={`w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm ${
                    isComplianceAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
                  }`}
                >
                  <option value={7}>7 years (legal minimum)</option>
                  <option value={10}>10 years</option>
                  <option value={15}>15 years</option>
                </select>
                <p className="text-xs text-amber-700 mt-2 flex items-center gap-1">
                  <Lock className="w-3 h-3" />
                  Minimum retention required for tax and accounting compliance
                </p>
              </div>
            </div>
          </div>

          {/* Guest User Data Retention */}
          <div className="border border-gray-200 rounded-lg p-4">
            <div className="flex items-start gap-3 mb-3">
              <Users className="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
              <div className="flex-1">
                <label className="block text-sm font-medium text-gray-900 mb-1">
                  Guest User Data Retention
                </label>
                <select
                  value={guestDataRetention}
                  onChange={(e) => handleRetentionChange('guest', Number(e.target.value))}
                  disabled={!isComplianceAdmin}
                  className={`w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm ${
                    isComplianceAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
                  }`}
                >
                  <option value={30}>30 days</option>
                  <option value={60}>60 days</option>
                  <option value={90}>90 days</option>
                  <option value={180}>180 days</option>
                </select>
                <p className="text-xs text-gray-600 mt-2">
                  Applies to non-registered users only (guest checkouts)
                </p>
              </div>
            </div>
          </div>

          {/* Audit Log Retention */}
          <div className="border border-gray-200 rounded-lg p-4">
            <div className="flex items-start gap-3 mb-3">
              <FileText className="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
              <div className="flex-1">
                <label className="block text-sm font-medium text-gray-900 mb-1">
                  Audit Log Retention
                </label>
                <select
                  value={auditLogRetention}
                  onChange={(e) => handleRetentionChange('audit', Number(e.target.value))}
                  disabled={!isComplianceAdmin}
                  className={`w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg text-sm ${
                    isComplianceAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
                  }`}
                >
                  <option value={3}>3 years</option>
                  <option value={7}>7 years (recommended)</option>
                  <option value={10}>10 years</option>
                </select>
                <p className="text-xs text-blue-700 mt-2 flex items-center gap-1">
                  <Info className="w-3 h-3" />
                  Audit logs are required for security and legal accountability
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Automatic Anonymization Rules */}
      <div className="border-t border-gray-200 pt-6">
        <div className="mb-4">
          <h3 className="font-semibold text-gray-900">Data Anonymization</h3>
          <p className="text-sm text-gray-600 mt-1">
            Configure automatic anonymization to protect privacy while preserving analytics
          </p>
        </div>

        <div className="border border-gray-200 rounded-lg p-4 space-y-3">
          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={autoAnonymizeGuests}
              onChange={(e) => {
                if (!isComplianceAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setAutoAnonymizeGuests(e.target.checked);
              }}
              disabled={!isComplianceAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Auto-anonymize guest users after retention period
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Automatically anonymize guest user data when retention period expires
              </p>
            </div>
          </label>

          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={preserveAggregatedAnalytics}
              onChange={(e) => {
                if (!isComplianceAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setPreserveAggregatedAnalytics(e.target.checked);
              }}
              disabled={!isComplianceAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Remove personal identifiers while keeping aggregated analytics
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Replace names, emails, and phone numbers with anonymized IDs while preserving order statistics
              </p>
            </div>
          </label>

          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-3">
            <p className="text-xs text-blue-800">
              <strong>Privacy Benefit:</strong> Anonymization preserves platform analytics 
              (order volumes, revenue trends) while protecting personal data.
            </p>
          </div>
        </div>
      </div>

      {/* Audit & Accountability */}
      <div className="border-t border-gray-200 pt-6">
        <div className="mb-4">
          <h3 className="font-semibold text-gray-900">Audit & Accountability</h3>
          <p className="text-sm text-gray-600 mt-1">
            Configure audit logging for compliance and security
          </p>
        </div>

        <div className="space-y-4">
          {/* Mandatory Logging (Locked ON) */}
          <div className="border border-green-200 rounded-lg p-4 bg-green-50">
            <h4 className="text-sm font-semibold text-green-900 mb-3 flex items-center gap-2">
              <Lock className="w-4 h-4" />
              Mandatory Logging (Always Enabled)
            </h4>
            <div className="space-y-2">
              <div className="flex items-center gap-2 text-sm">
                <CheckCircle className="w-4 h-4 text-green-600" />
                <span className="text-green-900">Log all GDPR actions (access, deletion, portability requests)</span>
              </div>
              <div className="flex items-center gap-2 text-sm">
                <CheckCircle className="w-4 h-4 text-green-600" />
                <span className="text-green-900">Log admin data access to personal data</span>
              </div>
              <div className="flex items-center gap-2 text-sm">
                <CheckCircle className="w-4 h-4 text-green-600" />
                <span className="text-green-900">Log role & permission changes</span>
              </div>
              <div className="flex items-center gap-2 text-sm">
                <CheckCircle className="w-4 h-4 text-green-600" />
                <span className="text-green-900">Log deletions and anonymization actions</span>
              </div>
            </div>
            <p className="text-xs text-green-800 mt-3">
              <strong>Required for GDPR accountability (Article 5.2)</strong> - These logs cannot be disabled
            </p>
          </div>

          {/* Optional Logging (Configurable) */}
          <div className="border border-gray-200 rounded-lg p-4">
            <h4 className="text-sm font-semibold text-gray-900 mb-3">
              Optional Logging (Configurable)
            </h4>
            <div className="space-y-3">
              <label className="flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={logVendorChanges}
                  onChange={(e) => {
                    if (!isComplianceAdmin) {
                      toast.error('Access denied');
                      return;
                    }
                    setLogVendorChanges(e.target.checked);
                  }}
                  disabled={!isComplianceAdmin}
                  className="mt-0.5 rounded border-gray-300"
                />
                <div className="flex-1">
                  <span className="text-sm font-medium text-gray-900">
                    Log vendor account changes
                  </span>
                  <p className="text-xs text-gray-600 mt-0.5">
                    Track changes to vendor profiles, settings, and status
                  </p>
                </div>
              </label>

              <label className="flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={logSystemConfig}
                  onChange={(e) => {
                    if (!isComplianceAdmin) {
                      toast.error('Access denied');
                      return;
                    }
                    setLogSystemConfig(e.target.checked);
                  }}
                  disabled={!isComplianceAdmin}
                  className="mt-0.5 rounded border-gray-300"
                />
                <div className="flex-1">
                  <span className="text-sm font-medium text-gray-900">
                    Log low-risk system configuration changes
                  </span>
                  <p className="text-xs text-gray-600 mt-0.5">
                    Track non-sensitive platform setting modifications
                  </p>
                </div>
              </label>
            </div>
          </div>
        </div>
      </div>

      {/* Retention Change Confirmation Modal */}
      {showRetentionChange && (
        <RetentionChangeModal
          type={showRetentionChange.type}
          currentValue={
            showRetentionChange.type === 'order' ? orderDataRetention :
            showRetentionChange.type === 'guest' ? guestDataRetention :
            auditLogRetention
          }
          newValue={showRetentionChange.newValue}
          changeReason={changeReason}
          setChangeReason={setChangeReason}
          onClose={() => {
            setShowRetentionChange(null);
            setChangeReason('');
          }}
          onConfirm={confirmRetentionChange}
        />
      )}
    </div>
  );
}

// Retention Change Confirmation Modal
interface RetentionChangeModalProps {
  type: 'order' | 'guest' | 'audit';
  currentValue: number;
  newValue: number;
  changeReason: string;
  setChangeReason: (reason: string) => void;
  onClose: () => void;
  onConfirm: () => void;
}

function RetentionChangeModal({
  type,
  currentValue,
  newValue,
  changeReason,
  setChangeReason,
  onClose,
  onConfirm
}: RetentionChangeModalProps) {
  const typeLabels = {
    order: 'Order & Financial Data',
    guest: 'Guest User Data',
    audit: 'Audit Log'
  };

  const typeUnits = {
    order: 'years',
    guest: 'days',
    audit: 'years'
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-lg w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <AlertTriangle className="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">
              Change Data Retention Policy
            </h3>
            <p className="text-sm text-gray-600">{typeLabels[type]}</p>
          </div>
        </div>

        <div className="space-y-4">
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p className="text-sm text-gray-900 font-medium mb-2">
              Change retention from {currentValue} {typeUnits[type]} to {newValue} {typeUnits[type]}?
            </p>
            <div className="bg-blue-50 border border-blue-200 rounded p-2 mt-2">
              <div className="flex items-start gap-2">
                <Info className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                <p className="text-xs text-blue-800">
                  {type === 'order' && 'This affects financial record retention. Legal minimum of 7 years will be enforced.'}
                  {type === 'guest' && 'This affects how long guest user data is stored before anonymization.'}
                  {type === 'audit' && 'This affects security and compliance audit trail retention.'}
                </p>
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
              placeholder="Explain why this data retention policy is being changed (minimum 10 characters)"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 text-sm resize-none"
              rows={3}
            />
            <p className="text-xs text-gray-500 mt-1">
              {changeReason.length}/10 characters minimum
            </p>
          </div>

          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <Shield className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-amber-800">
                This change will be logged to the compliance audit trail with your admin ID, timestamp, and reason.
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
                : 'bg-blue-600 text-white hover:bg-blue-700'
            }`}
          >
            Confirm Change
          </button>
        </div>
      </div>
    </div>
  );
}
