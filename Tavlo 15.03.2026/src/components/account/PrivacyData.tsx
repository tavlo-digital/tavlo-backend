import { useState } from 'react';
import { 
  Shield, 
  Download, 
  Trash2, 
  Info, 
  CheckCircle, 
  Clock,
  FileText,
  Eye,
  Lock,
  AlertTriangle,
  User,
  ShieldCheck
} from 'lucide-react';
import { toast } from 'sonner@2.0.3';

interface PrivacyDataProps {
  user: any;
}

type DataRequestType = 'access' | 'deletion';
type RequestStatus = 'pending' | 'processing' | 'ready' | 'completed';

interface DataRequest {
  id: string;
  type: DataRequestType;
  dateSubmitted: string;
  status: RequestStatus;
  downloadUrl?: string;
}

export function PrivacyData({ user }: PrivacyDataProps) {
  // Mock data requests history
  const [dataRequests, setDataRequests] = useState<DataRequest[]>([
    {
      id: 'req_001',
      type: 'access',
      dateSubmitted: 'Nov 15, 2024',
      status: 'completed',
      downloadUrl: '/downloads/user-data-export.zip'
    }
  ]);

  // Modal states
  const [showAccessModal, setShowAccessModal] = useState(false);
  const [showDeletionModal, setShowDeletionModal] = useState(false);
  const [deletionPassword, setDeletionPassword] = useState('');
  const [deletionConfirmed, setDeletionConfirmed] = useState(false);

  const handleRequestDataAccess = () => {
    // Create new access request
    const newRequest: DataRequest = {
      id: `req_${Date.now()}`,
      type: 'access',
      dateSubmitted: new Date().toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
      }),
      status: 'pending'
    };

    setDataRequests([newRequest, ...dataRequests]);
    setShowAccessModal(false);

    // Log to audit trail
    console.log('AUDIT LOG: Data access request created', {
      userId: user.id,
      requestId: newRequest.id,
      timestamp: new Date().toISOString(),
      type: 'DATA_ACCESS_REQUEST'
    });

    toast.success('Data access request submitted', {
      description: 'We will process your request within 30 days. You will receive an email notification when your data is ready for download.'
    });
  };

  const handleRequestDeletion = () => {
    if (!deletionPassword || deletionPassword.length < 3) {
      toast.error('Password required', {
        description: 'Please enter your password to confirm deletion'
      });
      return;
    }

    if (!deletionConfirmed) {
      toast.error('Confirmation required', {
        description: 'Please confirm you understand this action cannot be undone'
      });
      return;
    }

    // Create deletion request
    const newRequest: DataRequest = {
      id: `req_${Date.now()}`,
      type: 'deletion',
      dateSubmitted: new Date().toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
      }),
      status: 'pending'
    };

    setDataRequests([newRequest, ...dataRequests]);
    setShowDeletionModal(false);
    setDeletionPassword('');
    setDeletionConfirmed(false);

    // Log to audit trail
    console.log('AUDIT LOG: Account deletion request created', {
      userId: user.id,
      requestId: newRequest.id,
      timestamp: new Date().toISOString(),
      type: 'ACCOUNT_DELETION_REQUEST'
    });

    toast.success('Account deletion request submitted', {
      description: 'Your request is being processed. You will receive updates via email.'
    });
  };

  const getStatusColor = (status: RequestStatus) => {
    switch (status) {
      case 'pending': return 'text-yellow-700 bg-yellow-100';
      case 'processing': return 'text-blue-700 bg-blue-100';
      case 'ready': return 'text-green-700 bg-green-100';
      case 'completed': return 'text-gray-700 bg-gray-100';
      default: return 'text-gray-700 bg-gray-100';
    }
  };

  const getStatusLabel = (status: RequestStatus) => {
    switch (status) {
      case 'pending': return 'Pending';
      case 'processing': return 'Processing';
      case 'ready': return 'Ready for Download';
      case 'completed': return 'Completed';
      default: return 'Unknown';
    }
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* GDPR Compliance Info Banner */}
      <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <div className="flex items-start gap-3">
          <Shield className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-semibold text-blue-900 mb-1">
              Your Privacy Rights
            </h3>
            <p className="text-sm text-blue-800">
              Tavlo respects your privacy and complies with GDPR. 
              Here you can request access to your data or request account deletion. 
              <strong className="block mt-1">Restaurants never see your email or phone number.</strong>
            </p>
          </div>
        </div>
      </div>

      {/* Data Transparency Section */}
      <div className="bg-white rounded-2xl border border-gray-200 p-6">
        <div className="flex items-center gap-2 mb-4">
          <Eye className="w-5 h-5 text-gray-600" />
          <h2 className="text-xl font-semibold text-gray-900">Your Personal Data</h2>
        </div>

        <div className="space-y-4">
          {/* What data Tavlo stores */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 mb-2">
              What data Tavlo stores:
            </h3>
            <ul className="space-y-1.5 text-sm text-gray-700">
              <li className="flex items-center gap-2">
                <div className="w-1.5 h-1.5 rounded-full bg-orange-500"></div>
                Profile information (name, email, phone)
              </li>
              <li className="flex items-center gap-2">
                <div className="w-1.5 h-1.5 rounded-full bg-orange-500"></div>
                Order history
              </li>
              <li className="flex items-center gap-2">
                <div className="w-1.5 h-1.5 rounded-full bg-orange-500"></div>
                Loyalty activity
              </li>
              <li className="flex items-center gap-2">
                <div className="w-1.5 h-1.5 rounded-full bg-orange-500"></div>
                Reviews
              </li>
            </ul>
          </div>

          {/* Who can access it */}
          <div className="pt-4 border-t border-gray-200">
            <h3 className="text-sm font-semibold text-gray-900 mb-2">
              Who can access it:
            </h3>
            <ul className="space-y-1.5 text-sm text-gray-700">
              <li className="flex items-center gap-2">
                <CheckCircle className="w-4 h-4 text-green-600 flex-shrink-0" />
                Tavlo support (for service and legal reasons)
              </li>
              <li className="flex items-center gap-2">
                <Lock className="w-4 h-4 text-red-600 flex-shrink-0" />
                <span>
                  <strong>Vendors do not see personal contact details</strong>
                  {' '}(email, phone)
                </span>
              </li>
            </ul>
          </div>

          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3 mt-4">
            <p className="text-xs text-gray-600">
              <strong>Privacy Protection:</strong> When you place an order, restaurants only see 
              your first name and order details. Your email and phone number remain private and 
              are used only for order notifications and support.
            </p>
          </div>
        </div>
      </div>

      {/* Right of Access (GDPR Art. 15) */}
      <div className="bg-white rounded-2xl border border-gray-200 p-6">
        <div className="flex items-start gap-3 mb-4">
          <div className="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <Download className="w-5 h-5 text-purple-600" />
          </div>
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-1">
              <h2 className="text-xl font-semibold text-gray-900">Right of Access</h2>
              <span className="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full font-medium">
                GDPR Art. 15
              </span>
            </div>
            <p className="text-sm text-gray-600">
              Request a copy of your personal data stored by Tavlo
            </p>
          </div>
        </div>

        <div className="space-y-4">
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <h3 className="text-sm font-semibold text-blue-900 mb-2">
              What's included in your data export:
            </h3>
            <ul className="space-y-1 text-sm text-blue-800">
              <li className="flex items-center gap-2">
                <CheckCircle className="w-4 h-4 flex-shrink-0" />
                Profile information and account details
              </li>
              <li className="flex items-center gap-2">
                <CheckCircle className="w-4 h-4 flex-shrink-0" />
                Complete order history
              </li>
              <li className="flex items-center gap-2">
                <CheckCircle className="w-4 h-4 flex-shrink-0" />
                Loyalty points transactions
              </li>
              <li className="flex items-center gap-2">
                <CheckCircle className="w-4 h-4 flex-shrink-0" />
                Reviews and ratings
              </li>
              <li className="flex items-center gap-2">
                <CheckCircle className="w-4 h-4 flex-shrink-0" />
                Saved restaurants and preferences
              </li>
            </ul>
          </div>

          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <Info className="w-4 h-4 text-gray-600 flex-shrink-0 mt-0.5" />
              <div className="text-xs text-gray-700">
                <p className="mb-1">
                  <strong>Data Portability (GDPR Art. 20):</strong>
                </p>
                <p>
                  Your data export is provided in a structured, machine-readable format (JSON/ZIP) 
                  so it can be reused or transferred to another service.
                </p>
              </div>
            </div>
          </div>

          <button
            onClick={() => setShowAccessModal(true)}
            className="w-full sm:w-auto px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium flex items-center justify-center gap-2"
          >
            <Download className="w-5 h-5" />
            Download My Personal Data
          </button>

          <p className="text-xs text-gray-500">
            This is a legal request. Tavlo will respond within 30 days as required by GDPR.
          </p>
        </div>
      </div>

      {/* Right to Erasure (GDPR Art. 17) */}
      <div className="bg-white rounded-2xl border border-red-200 p-6">
        <div className="flex items-start gap-3 mb-4">
          <div className="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <Trash2 className="w-5 h-5 text-red-600" />
          </div>
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-1">
              <h2 className="text-xl font-semibold text-gray-900">Right to Erasure</h2>
              <span className="text-xs px-2 py-0.5 bg-red-100 text-red-700 rounded-full font-medium">
                GDPR Art. 17
              </span>
            </div>
            <p className="text-sm text-gray-600">
              Request permanent deletion of your account and personal data
            </p>
          </div>
        </div>

        <div className="space-y-4">
          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <AlertTriangle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
              <div>
                <h3 className="text-sm font-semibold text-amber-900 mb-1">
                  Important Information
                </h3>
                <p className="text-sm text-amber-800 mb-2">
                  Account deletion is permanent and cannot be undone. Please understand what will happen:
                </p>
                <ul className="space-y-1 text-sm text-amber-800">
                  <li className="flex items-start gap-2">
                    <span className="font-bold">✓</span>
                    <span>Your profile (name, email, phone) will be permanently deleted</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="font-bold">✓</span>
                    <span>Your reviews and saved restaurants will be removed</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="font-bold">✓</span>
                    <span>Your loyalty points will be forfeited</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="font-bold">⚠</span>
                    <span>
                      <strong>Completed orders will be anonymized</strong> but retained for legal and 
                      tax compliance (required by law for 7 years)
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <button
            onClick={() => setShowDeletionModal(true)}
            className="w-full sm:w-auto px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium flex items-center justify-center gap-2"
          >
            <Trash2 className="w-5 h-5" />
            Request Account Deletion
          </button>

          <p className="text-xs text-gray-500">
            This action creates a deletion request. Your account will be scheduled for deletion 
            after verification. You will receive email confirmation.
          </p>
        </div>
      </div>

      {/* Request History & Status */}
      {dataRequests.length > 0 && (
        <div className="bg-white rounded-2xl border border-gray-200 p-6">
          <div className="flex items-center gap-2 mb-4">
            <FileText className="w-5 h-5 text-gray-600" />
            <h2 className="text-xl font-semibold text-gray-900">Your Data Requests</h2>
          </div>

          <div className="space-y-3">
            {dataRequests.map((request) => (
              <div
                key={request.id}
                className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex items-start gap-3 flex-1">
                    <div className={`w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ${
                      request.type === 'access' 
                        ? 'bg-purple-100' 
                        : 'bg-red-100'
                    }`}>
                      {request.type === 'access' ? (
                        <Download className={`w-5 h-5 ${
                          request.type === 'access' ? 'text-purple-600' : 'text-red-600'
                        }`} />
                      ) : (
                        <Trash2 className="w-5 h-5 text-red-600" />
                      )}
                    </div>
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <h3 className="font-semibold text-gray-900">
                          {request.type === 'access' ? 'Data Access Request' : 'Account Deletion Request'}
                        </h3>
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${getStatusColor(request.status)}`}>
                          {getStatusLabel(request.status)}
                        </span>
                      </div>
                      <p className="text-sm text-gray-600">
                        Submitted on {request.dateSubmitted}
                      </p>
                      {request.status === 'ready' && request.type === 'access' && (
                        <button
                          onClick={() => {
                            toast.success('Download started');
                            // In production, this would trigger actual download
                          }}
                          className="mt-2 text-sm text-purple-600 hover:text-purple-700 font-medium flex items-center gap-1"
                        >
                          <Download className="w-4 h-4" />
                          Download your data
                        </button>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-1 text-gray-400">
                    {request.status === 'pending' && <Clock className="w-5 h-5" />}
                    {request.status === 'ready' && <CheckCircle className="w-5 h-5 text-green-600" />}
                    {request.status === 'completed' && <CheckCircle className="w-5 h-5 text-gray-400" />}
                  </div>
                </div>
              </div>
            ))}
          </div>

          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-4">
            <div className="flex items-start gap-2">
              <Info className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-blue-800">
                <strong>Transparency & Accountability:</strong> All GDPR requests are tracked 
                and logged for legal compliance. You will receive email notifications at each 
                step of the process.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Security Section (Optional but Recommended) */}
      <div className="bg-white rounded-2xl border border-gray-200 p-6">
        <div className="flex items-center gap-2 mb-4">
          <ShieldCheck className="w-5 h-5 text-gray-600" />
          <h2 className="text-xl font-semibold text-gray-900">Security & Sessions</h2>
        </div>

        <div className="space-y-4">
          <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
            <div>
              <h3 className="text-sm font-medium text-gray-900">Last Login</h3>
              <p className="text-sm text-gray-600">Today at 10:24 AM</p>
            </div>
            <Clock className="w-5 h-5 text-gray-400" />
          </div>

          <button
            onClick={() => {
              if (confirm('This will sign you out from all devices. Continue?')) {
                toast.success('Signed out from all devices');
              }
            }}
            className="w-full sm:w-auto px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium text-sm"
          >
            Sign Out from All Devices
          </button>
        </div>
      </div>

      {/* Data Access Request Modal */}
      {showAccessModal && (
        <DataAccessModal
          onClose={() => setShowAccessModal(false)}
          onConfirm={handleRequestDataAccess}
        />
      )}

      {/* Account Deletion Modal */}
      {showDeletionModal && (
        <DeletionModal
          password={deletionPassword}
          setPassword={setDeletionPassword}
          confirmed={deletionConfirmed}
          setConfirmed={setDeletionConfirmed}
          onClose={() => {
            setShowDeletionModal(false);
            setDeletionPassword('');
            setDeletionConfirmed(false);
          }}
          onConfirm={handleRequestDeletion}
        />
      )}
    </div>
  );
}

// Data Access Request Modal
interface DataAccessModalProps {
  onClose: () => void;
  onConfirm: () => void;
}

function DataAccessModal({ onClose, onConfirm }: DataAccessModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-lg w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
            <Download className="w-6 h-6 text-purple-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">
              Request Data Access
            </h3>
            <p className="text-sm text-gray-600">GDPR Article 15</p>
          </div>
        </div>

        <div className="space-y-4">
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <h4 className="text-sm font-semibold text-blue-900 mb-2">
              What happens next:
            </h4>
            <ul className="space-y-1.5 text-sm text-blue-800">
              <li className="flex items-start gap-2">
                <span className="font-bold">1.</span>
                <span>Your request will be created and logged</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="font-bold">2.</span>
                <span>We will compile all your personal data</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="font-bold">3.</span>
                <span>You will receive an email when your data is ready (within 30 days)</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="font-bold">4.</span>
                <span>Download will be available in machine-readable format (JSON/ZIP)</span>
              </li>
            </ul>
          </div>

          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <Info className="w-4 h-4 text-gray-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-gray-700">
                <strong>Format:</strong> Your data will be provided in JSON format inside a ZIP archive. 
                This format is structured and machine-readable, allowing you to transfer it to another 
                service (Data Portability - GDPR Article 20).
              </p>
            </div>
          </div>

          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <Clock className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-amber-800">
                <strong>Processing Time:</strong> Tavlo is legally required to respond within 30 days. 
                Most requests are completed within 7-14 days.
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
            className="flex-1 px-4 py-2 text-sm font-medium bg-purple-600 text-white rounded-lg hover:bg-purple-700"
          >
            Submit Request
          </button>
        </div>
      </div>
    </div>
  );
}

// Account Deletion Modal
interface DeletionModalProps {
  password: string;
  setPassword: (password: string) => void;
  confirmed: boolean;
  setConfirmed: (confirmed: boolean) => void;
  onClose: () => void;
  onConfirm: () => void;
}

function DeletionModal({ 
  password, 
  setPassword, 
  confirmed, 
  setConfirmed, 
  onClose, 
  onConfirm 
}: DeletionModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
            <Trash2 className="w-6 h-6 text-red-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">
              Request Account Deletion
            </h3>
            <p className="text-sm text-gray-600">GDPR Article 17</p>
          </div>
        </div>

        <div className="space-y-4">
          <div className="bg-red-50 border-2 border-red-300 rounded-lg p-4">
            <div className="flex items-start gap-2">
              <AlertTriangle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
              <div>
                <h4 className="text-sm font-bold text-red-900 mb-2">
                  ⚠️ This action cannot be undone
                </h4>
                <p className="text-sm text-red-800 mb-3">
                  Before you proceed, please understand what will happen:
                </p>
              </div>
            </div>
          </div>

          <div className="space-y-3 text-sm">
            <div>
              <h4 className="font-semibold text-gray-900 mb-2">What will be deleted:</h4>
              <ul className="space-y-1 text-gray-700">
                <li className="flex items-start gap-2">
                  <Trash2 className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                  Your profile (name, email, phone number)
                </li>
                <li className="flex items-start gap-2">
                  <Trash2 className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                  All reviews and ratings
                </li>
                <li className="flex items-start gap-2">
                  <Trash2 className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                  Saved restaurants and preferences
                </li>
                <li className="flex items-start gap-2">
                  <Trash2 className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                  Loyalty points (forfeited)
                </li>
              </ul>
            </div>

            <div className="pt-3 border-t border-gray-200">
              <h4 className="font-semibold text-gray-900 mb-2">What will be anonymized:</h4>
              <ul className="space-y-1 text-gray-700">
                <li className="flex items-start gap-2">
                  <Lock className="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" />
                  <span>
                    <strong>Completed order history</strong> - Personal identifiers removed, 
                    but order records retained for legal and tax compliance (required by law for 7 years)
                  </span>
                </li>
              </ul>
            </div>
          </div>

          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <Info className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-amber-800">
                <strong>Legal Retention:</strong> EU law requires businesses to retain financial 
                records for tax purposes. Your completed orders will be anonymized (personal data 
                removed) but order details will be kept for 7 years as required by Austrian tax law.
              </p>
            </div>
          </div>

          <div className="space-y-3 pt-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Enter your password to confirm <span className="text-red-500">*</span>
              </label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter your password"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-sm"
              />
            </div>

            <label className="flex items-start gap-3 cursor-pointer">
              <input
                type="checkbox"
                checked={confirmed}
                onChange={(e) => setConfirmed(e.target.checked)}
                className="mt-0.5 rounded border-gray-300"
              />
              <span className="text-sm text-gray-900">
                <strong>I understand this action cannot be undone</strong> and that my account 
                will be permanently deleted
              </span>
            </label>
          </div>

          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <Clock className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-blue-800">
                <strong>Processing:</strong> Your deletion request will be processed within 7 days. 
                You will receive email confirmation when completed. During this period, your account 
                will be deactivated.
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
            disabled={!password || !confirmed}
            className={`flex-1 px-4 py-2 text-sm font-medium rounded-lg ${
              password && confirmed
                ? 'bg-red-600 text-white hover:bg-red-700'
                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
            }`}
          >
            Request Deletion
          </button>
        </div>
      </div>
    </div>
  );
}
