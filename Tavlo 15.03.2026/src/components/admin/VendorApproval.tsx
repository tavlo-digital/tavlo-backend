import { useState } from 'react';
import { 
  ArrowLeft, 
  CheckCircle, 
  XCircle, 
  FileText, 
  MapPin, 
  Phone, 
  Mail, 
  Globe,
  Building,
  CreditCard,
  Upload,
  Eye,
  Download,
  AlertCircle,
  Clock
} from 'lucide-react';

interface VendorApprovalProps {
  onBack: () => void;
  onApprove: () => void;
  onReject: () => void;
}

export function VendorApproval({ onBack, onApprove, onReject }: VendorApprovalProps) {
  const [activeTab, setActiveTab] = useState<'details' | 'documents' | 'menu' | 'verification'>('details');
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [rejectReason, setRejectReason] = useState('');

  const vendor = {
    id: 'v_002',
    name: 'Sakura Sushi',
    type: 'Japanese Restaurant',
    submittedDate: '2024-06-10T14:30:00',
    contact: {
      email: 'info@sakurasushi.at',
      phone: '+43 123 456 789',
      website: 'www.sakurasushi.at',
      address: 'Mariahilfer Straße 45, 1060 Vienna, Austria'
    },
    business: {
      registrationNumber: 'FN 1234567a',
      vatNumber: 'ATU12345678',
      taxId: 'AT-1234567',
      businessType: 'Limited Liability Company (GmbH)',
      foundedYear: 2020,
      employeeCount: '15-20'
    },
    banking: {
      accountHolder: 'Sakura Sushi GmbH',
      iban: 'AT48 1234 5123 4567 8901',
      bic: 'BKAUATWW',
      bankName: 'Bank Austria'
    },
    subscription: {
      requestedPlan: 'Standard',
      billingCycle: 'Monthly',
      features: ['QR Ordering', 'Menu Management', 'Basic Analytics', 'Payment Processing']
    },
    documents: [
      { name: 'Business Registration Certificate', status: 'uploaded', type: 'PDF', size: '2.4 MB', uploadedDate: '2024-06-10' },
      { name: 'Tax Certificate', status: 'uploaded', type: 'PDF', size: '1.8 MB', uploadedDate: '2024-06-10' },
      { name: 'VAT Registration', status: 'uploaded', type: 'PDF', size: '1.2 MB', uploadedDate: '2024-06-10' },
      { name: 'Bank Confirmation Letter', status: 'uploaded', type: 'PDF', size: '950 KB', uploadedDate: '2024-06-10' },
      { name: 'ID Card (Owner)', status: 'uploaded', type: 'PDF', size: '1.5 MB', uploadedDate: '2024-06-10' }
    ],
    menu: {
      categories: 8,
      items: 45,
      avgPrice: '€14.50',
      priceRange: '€8 - €28',
      languages: ['English', 'German'],
      hasImages: true,
      hasAllergens: true
    }
  };

  const tabs = [
    { id: 'details', label: 'Business Details', icon: Building },
    { id: 'documents', label: 'Documents', icon: FileText },
    { id: 'menu', label: 'Menu Preview', icon: Globe },
    { id: 'verification', label: 'Verification', icon: CheckCircle }
  ];

  const handleApprove = () => {
    // Show confirmation
    if (confirm('Approve this vendor application? They will be activated immediately and can start accepting orders.')) {
      onApprove();
    }
  };

  const handleReject = () => {
    setShowRejectModal(true);
  };

  const submitRejection = () => {
    if (!rejectReason.trim()) {
      alert('Please provide a reason for rejection');
      return;
    }
    // Process rejection
    onReject();
  };

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <button 
          onClick={onBack}
          className="flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-4"
        >
          <ArrowLeft className="w-4 h-4" />
          Back to vendor list
        </button>
        
        <div className="flex items-start justify-between">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <h1 className="text-2xl">{vendor.name}</h1>
              <span className="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm">
                Pending Approval
              </span>
            </div>
            <p className="text-sm text-gray-500">
              Submitted on {new Date(vendor.submittedDate).toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              })}
            </p>
          </div>

          <div className="flex items-center gap-3">
            <button
              onClick={handleReject}
              className="flex items-center gap-2 px-6 py-2.5 border border-red-600 text-red-600 rounded-lg hover:bg-red-50"
            >
              <XCircle className="w-4 h-4" />
              Reject Application
            </button>
            <button
              onClick={handleApprove}
              className="flex items-center gap-2 px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700"
            >
              <CheckCircle className="w-4 h-4" />
              Approve & Activate
            </button>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 mb-6">
        <div className="flex gap-6">
          {tabs.map((tab) => {
            const Icon = tab.icon;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id as any)}
                className={`flex items-center gap-2 pb-3 border-b-2 transition-colors ${
                  activeTab === tab.id
                    ? 'border-purple-600 text-purple-600'
                    : 'border-transparent text-gray-600 hover:text-gray-900'
                }`}
              >
                <Icon className="w-4 h-4" />
                <span className="text-sm font-medium">{tab.label}</span>
              </button>
            );
          })}
        </div>
      </div>

      {/* Tab Content */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {activeTab === 'details' && (
            <>
              {/* Contact Information */}
              <div className="bg-white rounded-xl border border-gray-200 p-6">
                <h2 className="text-lg mb-4">Contact Information</h2>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <div className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                      <Mail className="w-4 h-4" />
                      Email
                    </div>
                    <div className="text-sm font-medium">{vendor.contact.email}</div>
                  </div>
                  <div>
                    <div className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                      <Phone className="w-4 h-4" />
                      Phone
                    </div>
                    <div className="text-sm font-medium">{vendor.contact.phone}</div>
                  </div>
                  <div>
                    <div className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                      <Globe className="w-4 h-4" />
                      Website
                    </div>
                    <div className="text-sm font-medium text-purple-600">{vendor.contact.website}</div>
                  </div>
                  <div>
                    <div className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                      <MapPin className="w-4 h-4" />
                      Address
                    </div>
                    <div className="text-sm font-medium">{vendor.contact.address}</div>
                  </div>
                </div>
              </div>

              {/* Business Details */}
              <div className="bg-white rounded-xl border border-gray-200 p-6">
                <h2 className="text-lg mb-4">Business Details</h2>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <div className="text-sm text-gray-500 mb-1">Registration Number</div>
                    <div className="text-sm font-medium">{vendor.business.registrationNumber}</div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500 mb-1">VAT Number</div>
                    <div className="text-sm font-medium flex items-center gap-2">
                      {vendor.business.vatNumber}
                      <span className="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded">Verified</span>
                    </div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500 mb-1">Tax ID</div>
                    <div className="text-sm font-medium">{vendor.business.taxId}</div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500 mb-1">Business Type</div>
                    <div className="text-sm font-medium">{vendor.business.businessType}</div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500 mb-1">Founded Year</div>
                    <div className="text-sm font-medium">{vendor.business.foundedYear}</div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500 mb-1">Employee Count</div>
                    <div className="text-sm font-medium">{vendor.business.employeeCount}</div>
                  </div>
                </div>
              </div>

              {/* Banking Information */}
              <div className="bg-white rounded-xl border border-gray-200 p-6">
                <div className="flex items-center gap-2 mb-4">
                  <CreditCard className="w-5 h-5 text-gray-600" />
                  <h2 className="text-lg">Banking Information</h2>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="md:col-span-2">
                    <div className="text-sm text-gray-500 mb-1">Account Holder</div>
                    <div className="text-sm font-medium">{vendor.banking.accountHolder}</div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500 mb-1">IBAN</div>
                    <div className="text-sm font-mono bg-gray-50 px-3 py-2 rounded border">{vendor.banking.iban}</div>
                  </div>
                  <div>
                    <div className="text-sm text-gray-500 mb-1">BIC/SWIFT</div>
                    <div className="text-sm font-mono bg-gray-50 px-3 py-2 rounded border">{vendor.banking.bic}</div>
                  </div>
                  <div className="md:col-span-2">
                    <div className="text-sm text-gray-500 mb-1">Bank Name</div>
                    <div className="text-sm font-medium">{vendor.banking.bankName}</div>
                  </div>
                </div>
              </div>
            </>
          )}

          {activeTab === 'documents' && (
            <div className="bg-white rounded-xl border border-gray-200 p-6">
              <h2 className="text-lg mb-4">Uploaded Documents</h2>
              <div className="space-y-3">
                {vendor.documents.map((doc, index) => (
                  <div key={index} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                        <FileText className="w-5 h-5 text-red-600" />
                      </div>
                      <div>
                        <div className="text-sm font-medium">{doc.name}</div>
                        <div className="text-xs text-gray-500">
                          {doc.type} • {doc.size} • Uploaded {doc.uploadedDate}
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <button className="p-2 hover:bg-gray-200 rounded-lg">
                        <Eye className="w-4 h-4 text-gray-600" />
                      </button>
                      <button className="p-2 hover:bg-gray-200 rounded-lg">
                        <Download className="w-4 h-4 text-gray-600" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {activeTab === 'menu' && (
            <div className="bg-white rounded-xl border border-gray-200 p-6">
              <h2 className="text-lg mb-4">Menu Overview</h2>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div className="text-center p-4 bg-purple-50 rounded-lg">
                  <div className="text-2xl font-semibold text-purple-600">{vendor.menu.categories}</div>
                  <div className="text-sm text-gray-600">Categories</div>
                </div>
                <div className="text-center p-4 bg-blue-50 rounded-lg">
                  <div className="text-2xl font-semibold text-blue-600">{vendor.menu.items}</div>
                  <div className="text-sm text-gray-600">Menu Items</div>
                </div>
                <div className="text-center p-4 bg-green-50 rounded-lg">
                  <div className="text-2xl font-semibold text-green-600">{vendor.menu.avgPrice}</div>
                  <div className="text-sm text-gray-600">Avg Price</div>
                </div>
                <div className="text-center p-4 bg-orange-50 rounded-lg">
                  <div className="text-sm font-medium text-orange-600">{vendor.menu.priceRange}</div>
                  <div className="text-sm text-gray-600">Price Range</div>
                </div>
              </div>

              <div className="space-y-3">
                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <span className="text-sm text-gray-600">Images uploaded</span>
                  <span className="text-sm font-medium text-green-600">✓ Yes ({vendor.menu.items} items)</span>
                </div>
                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <span className="text-sm text-gray-600">Allergen information</span>
                  <span className="text-sm font-medium text-green-600">✓ Complete</span>
                </div>
                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <span className="text-sm text-gray-600">Translations</span>
                  <span className="text-sm font-medium">{vendor.menu.languages.join(', ')}</span>
                </div>
              </div>

              <div className="mt-6">
                <button className="w-full py-3 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 flex items-center justify-center gap-2">
                  <Eye className="w-4 h-4" />
                  Preview Full Menu
                </button>
              </div>
            </div>
          )}

          {activeTab === 'verification' && (
            <div className="bg-white rounded-xl border border-gray-200 p-6">
              <h2 className="text-lg mb-4">Verification Checklist</h2>
              <div className="space-y-4">
                {[
                  { item: 'Business registration verified', status: 'complete', note: 'Checked against Austrian business register' },
                  { item: 'VAT number validated', status: 'complete', note: 'Verified via EU VIES system' },
                  { item: 'Banking details confirmed', status: 'complete', note: 'IBAN validation passed' },
                  { item: 'Documents reviewed', status: 'complete', note: 'All 5 documents approved' },
                  { item: 'Menu quality check', status: 'complete', note: 'Images and descriptions meet standards' },
                  { item: 'Background check', status: 'pending', note: 'Awaiting final clearance' }
                ].map((check, index) => (
                  <div key={index} className="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
                    {check.status === 'complete' ? (
                      <div className="w-5 h-5 bg-green-500 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                        <CheckCircle className="w-3 h-3 text-white" />
                      </div>
                    ) : (
                      <div className="w-5 h-5 bg-yellow-500 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                        <Clock className="w-3 h-3 text-white" />
                      </div>
                    )}
                    <div className="flex-1">
                      <div className="text-sm font-medium">{check.item}</div>
                      <div className="text-xs text-gray-500 mt-0.5">{check.note}</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Requested Plan */}
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <h3 className="font-medium mb-4">Requested Plan</h3>
            <div className="mb-4">
              <div className="text-2xl font-semibold text-purple-600 mb-1">
                {vendor.subscription.requestedPlan}
              </div>
              <div className="text-sm text-gray-500">{vendor.subscription.billingCycle}</div>
            </div>
            <div className="space-y-2 mb-4">
              {vendor.subscription.features.map((feature, index) => (
                <div key={index} className="flex items-center gap-2 text-sm">
                  <CheckCircle className="w-4 h-4 text-green-600" />
                  <span>{feature}</span>
                </div>
              ))}
            </div>
            <button className="w-full py-2 border border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50 text-sm">
              Change Plan
            </button>
          </div>

          {/* Admin Notes */}
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <h3 className="font-medium mb-4">Admin Notes</h3>
            <textarea
              className="w-full p-3 border border-gray-200 rounded-lg text-sm resize-none"
              rows={6}
              placeholder="Add internal notes about this application..."
            />
            <button className="w-full mt-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm">
              Save Notes
            </button>
          </div>

          {/* Actions */}
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <h3 className="font-medium mb-4">Quick Actions</h3>
            <div className="space-y-2">
              <button className="w-full py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                Request Changes
              </button>
              <button className="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                Activate Trial
              </button>
              <button className="w-full py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                Contact Vendor
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Reject Modal */}
      {showRejectModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl max-w-lg w-full p-6">
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                <XCircle className="w-5 h-5 text-red-600" />
              </div>
              <h2 className="text-xl">Reject Application</h2>
            </div>
            <p className="text-sm text-gray-600 mb-4">
              Please provide a detailed reason for rejection. This will be sent to the vendor.
            </p>
            <textarea
              className="w-full p-3 border border-gray-200 rounded-lg text-sm resize-none mb-4"
              rows={6}
              placeholder="Enter rejection reason..."
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
            />
            <div className="flex gap-3">
              <button
                onClick={() => setShowRejectModal(false)}
                className="flex-1 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                onClick={submitRejection}
                className="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
              >
                Confirm Rejection
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
