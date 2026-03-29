import { useState } from 'react';
import { 
  Shield, 
  Lock, 
  CheckCircle, 
  AlertTriangle, 
  Info, 
  Zap,
  Building2,
  FileCheck,
  Globe,
  Bell,
  ShieldCheck,
  CreditCard,
  GitBranch,
  XCircle
} from 'lucide-react';
import { toast } from 'sonner@2.0.3';

interface VendorOnboardingRulesProps {
  isSuperAdmin?: boolean;
}

export function VendorOnboardingRules({ isSuperAdmin = true }: VendorOnboardingRulesProps) {
  // Optional requirements (required ones are always locked ON)
  const [requireBusinessDocs, setRequireBusinessDocs] = useState(false);
  const [requireFoodSafety, setRequireFoodSafety] = useState(false);
  const [requireBankAccount, setRequireBankAccount] = useState(false);

  // Automated verification
  const [validateVAT, setValidateVAT] = useState(true);
  const [validateEmail, setValidateEmail] = useState(true);
  const [validatePhone, setValidatePhone] = useState(true);
  const [validateSubscriptionPayment, setValidateSubscriptionPayment] = useState(true);

  // Subscription enforcement
  const [allowTrialWithoutPayment, setAllowTrialWithoutPayment] = useState(true);

  // Country availability
  const [availableCountries, setAvailableCountries] = useState<Set<string>>(
    new Set(['AT', 'DE', 'CH', 'IT', 'FR'])
  );

  // Notifications
  const [notifyOnRegistration, setNotifyOnRegistration] = useState(true);
  const [sendProgressEmails, setSendProgressEmails] = useState(true);
  const [sendGoLiveConfirmation, setSendGoLiveConfirmation] = useState(true);
  const [sendRejectionExplanation, setSendRejectionExplanation] = useState(true);

  // Modal states
  const [showRequirementChange, setShowRequirementChange] = useState<{
    type: string;
    value: boolean;
  } | null>(null);
  const [showCountryChange, setShowCountryChange] = useState<{
    country: string;
    adding: boolean;
  } | null>(null);
  const [changeReason, setChangeReason] = useState('');

  const handleRequirementChange = (type: string, newValue: boolean) => {
    if (!isSuperAdmin) {
      toast.error('Access denied', {
        description: 'Only Super Admin can modify onboarding rules'
      });
      return;
    }
    setShowRequirementChange({ type, value: newValue });
  };

  const confirmRequirementChange = () => {
    if (!changeReason.trim() || changeReason.trim().length < 10) {
      toast.error('Reason required', {
        description: 'Please provide a detailed reason (minimum 10 characters)'
      });
      return;
    }

    const { type, value } = showRequirementChange!;

    // Audit log
    console.log('AUDIT LOG: Onboarding requirement changed', {
      setting: type,
      after: value,
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString(),
      reason: changeReason
    });

    if (type === 'businessDocs') setRequireBusinessDocs(value);
    else if (type === 'foodSafety') setRequireFoodSafety(value);
    else if (type === 'bankAccount') setRequireBankAccount(value);

    setShowRequirementChange(null);
    setChangeReason('');

    toast.success('Requirement updated', {
      description: 'Change logged to audit trail'
    });
  };

  const handleCountryToggle = (country: string) => {
    if (!isSuperAdmin) {
      toast.error('Access denied');
      return;
    }
    const adding = !availableCountries.has(country);
    setShowCountryChange({ country, adding });
  };

  const confirmCountryChange = () => {
    if (!changeReason.trim() || changeReason.trim().length < 10) {
      toast.error('Reason required');
      return;
    }

    const { country, adding } = showCountryChange!;

    // Audit log
    console.log('AUDIT LOG: Country availability changed', {
      country,
      action: adding ? 'ADDED' : 'REMOVED',
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString(),
      reason: changeReason
    });

    const newCountries = new Set(availableCountries);
    if (adding) {
      newCountries.add(country);
    } else {
      newCountries.delete(country);
    }
    setAvailableCountries(newCountries);

    setShowCountryChange(null);
    setChangeReason('');

    toast.success('Country availability updated', {
      description: 'Change logged to audit trail'
    });
  };

  const countries = [
    { code: 'AT', name: 'Austria', flag: '🇦🇹' },
    { code: 'DE', name: 'Germany', flag: '🇩🇪' },
    { code: 'CH', name: 'Switzerland', flag: '🇨🇭' },
    { code: 'IT', name: 'Italy', flag: '🇮🇹' },
    { code: 'FR', name: 'France', flag: '🇫🇷' },
    { code: 'NL', name: 'Netherlands', flag: '🇳🇱' },
    { code: 'BE', name: 'Belgium', flag: '🇧🇪' },
    { code: 'ES', name: 'Spain', flag: '🇪🇸' }
  ];

  return (
    <div className="p-6 space-y-6">
      {/* Page Header */}
      <div>
        <h2 className="text-lg font-medium mb-2">Vendor Onboarding Rules</h2>
        <p className="text-sm text-gray-500">
          Configure automated requirements for vendors to join and go live
        </p>
      </div>

      {/* Automation Notice (Locked Banner) */}
      <div className="bg-green-50 border-2 border-green-300 rounded-xl p-4">
        <div className="flex items-start gap-3">
          <Zap className="w-5 h-5 text-green-700 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-semibold text-green-900 mb-1">
              Fully Automated Onboarding
            </h3>
            <p className="text-sm text-green-800">
              <strong>Vendor onboarding is fully automated.</strong>
              {' '}Vendors are approved automatically once all required steps are completed and verified.
              {' '}<strong className="text-green-900">No manual approval or admin review is required at any step.</strong>
            </p>
            <div className="flex items-center gap-2 mt-2">
              <Lock className="w-4 h-4 text-green-700" />
              <span className="text-xs font-medium text-green-900">Super Admin Only</span>
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
                You have read-only access to onboarding rules. 
                Only Super Admin can modify vendor eligibility requirements.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Onboarding State Model (Read-Only) */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start gap-3">
          <GitBranch className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
          <div className="flex-1">
            <h3 className="font-semibold text-blue-900 mb-2">
              Automated Onboarding State Flow
            </h3>
            <div className="space-y-1.5 text-sm text-blue-800">
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 rounded-full bg-gray-400"></div>
                <span><strong>Registered</strong> → Vendor creates account</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 rounded-full bg-yellow-500"></div>
                <span><strong>Requirements Incomplete</strong> → Filling required information</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 rounded-full bg-red-500"></div>
                <span><strong>Verification Failed</strong> → Automatic check failed (e.g., invalid VAT)</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 rounded-full bg-blue-500"></div>
                <span><strong>Ready for Go-Live</strong> → All requirements met, awaiting vendor action</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 rounded-full bg-green-500"></div>
                <span><strong>Live</strong> → Vendor is accepting orders</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 rounded-full bg-orange-500"></div>
                <span><strong>Suspended</strong> → Payment failure or policy violation</span>
              </div>
            </div>
            <p className="text-xs text-blue-700 mt-3">
              All state transitions are automatic based on rule satisfaction. No manual status changes.
            </p>
          </div>
        </div>
      </div>

      {/* Required Onboarding Requirements */}
      <div className="border-t border-gray-200 pt-6">
        <div className="mb-4">
          <h3 className="font-semibold text-gray-900">Required Onboarding Requirements</h3>
          <p className="text-sm text-gray-600 mt-1">
            Define what vendors must complete before going live
          </p>
        </div>

        <div className="space-y-3">
          {/* Required (Locked) */}
          <div className="border border-green-200 rounded-lg p-4 bg-green-50">
            <h4 className="text-sm font-semibold text-green-900 mb-3 flex items-center gap-2">
              <Lock className="w-4 h-4" />
              Required (Always Enabled)
            </h4>
            <div className="space-y-2">
              <div className="flex items-center gap-2 text-sm text-green-900">
                <CheckCircle className="w-4 h-4 text-green-600 flex-shrink-0" />
                <span className="flex-1">Business information (legal name, address, type)</span>
                <span className="text-xs px-2 py-0.5 bg-green-200 text-green-800 rounded-full font-medium">
                  Locked
                </span>
              </div>
              <div className="flex items-center gap-2 text-sm text-green-900">
                <CheckCircle className="w-4 h-4 text-green-600 flex-shrink-0" />
                <span className="flex-1">Contact details (email, phone)</span>
                <span className="text-xs px-2 py-0.5 bg-green-200 text-green-800 rounded-full font-medium">
                  Locked
                </span>
              </div>
              <div className="flex items-center gap-2 text-sm text-green-900">
                <CheckCircle className="w-4 h-4 text-green-600 flex-shrink-0" />
                <span className="flex-1">Subscription plan selection</span>
                <span className="text-xs px-2 py-0.5 bg-green-200 text-green-800 rounded-full font-medium">
                  Locked
                </span>
              </div>
              <div className="flex items-center gap-2 text-sm text-green-900">
                <CheckCircle className="w-4 h-4 text-green-600 flex-shrink-0" />
                <span className="flex-1">VAT / tax identification number</span>
                <span className="text-xs px-2 py-0.5 bg-green-200 text-green-800 rounded-full font-medium">
                  Locked
                </span>
              </div>
            </div>
          </div>

          {/* Optional (Configurable) */}
          <div className="border border-gray-200 rounded-lg p-4">
            <h4 className="text-sm font-semibold text-gray-900 mb-3">
              Optional (Configurable)
            </h4>
            <div className="space-y-3">
              <label className="flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={requireBusinessDocs}
                  onChange={(e) => handleRequirementChange('businessDocs', e.target.checked)}
                  disabled={!isSuperAdmin}
                  className="mt-0.5 rounded border-gray-300"
                />
                <div className="flex-1">
                  <span className="text-sm font-medium text-gray-900">
                    Business registration documents
                  </span>
                  <p className="text-xs text-gray-600 mt-0.5">
                    Require vendors to upload official business registration proof
                  </p>
                </div>
              </label>

              <label className="flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={requireFoodSafety}
                  onChange={(e) => handleRequirementChange('foodSafety', e.target.checked)}
                  disabled={!isSuperAdmin}
                  className="mt-0.5 rounded border-gray-300"
                />
                <div className="flex-1">
                  <span className="text-sm font-medium text-gray-900">
                    Food safety certificates
                  </span>
                  <p className="text-xs text-gray-600 mt-0.5">
                    Require food handling and safety certification documents
                  </p>
                </div>
              </label>

              <label className="flex items-start gap-3">
                <input
                  type="checkbox"
                  checked={requireBankAccount}
                  onChange={(e) => handleRequirementChange('bankAccount', e.target.checked)}
                  disabled={!isSuperAdmin}
                  className="mt-0.5 rounded border-gray-300"
                />
                <div className="flex-1">
                  <span className="text-sm font-medium text-gray-900">
                    Bank account verification (if payouts enabled in future)
                  </span>
                  <p className="text-xs text-gray-600 mt-0.5">
                    Require bank account verification for future payout functionality
                  </p>
                </div>
              </label>
            </div>
          </div>

          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <AlertTriangle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-amber-800">
                <strong>Automatic Enforcement:</strong> Vendors cannot proceed unless all required 
                requirements are completed. System automatically blocks progression.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Automated Verification Rules */}
      <div className="border-t border-gray-200 pt-6">
        <div className="mb-4">
          <h3 className="font-semibold text-gray-900">Automatic Verification</h3>
          <p className="text-sm text-gray-600 mt-1">
            Configure automated validation rules (no manual review)
          </p>
        </div>

        <div className="border border-gray-200 rounded-lg p-4 space-y-3">
          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={validateVAT}
              onChange={(e) => {
                if (!isSuperAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setValidateVAT(e.target.checked);
              }}
              disabled={!isSuperAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Validate VAT number format and country
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Automatically verify VAT number against EU VIES database
              </p>
            </div>
          </label>

          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={validateEmail}
              onChange={(e) => {
                if (!isSuperAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setValidateEmail(e.target.checked);
              }}
              disabled={!isSuperAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Validate email ownership
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Send verification email and require confirmation before proceeding
              </p>
            </div>
          </label>

          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={validatePhone}
              onChange={(e) => {
                if (!isSuperAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setValidatePhone(e.target.checked);
              }}
              disabled={!isSuperAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Validate phone number
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Verify phone number format and send SMS verification code
              </p>
            </div>
          </label>

          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={validateSubscriptionPayment}
              onChange={(e) => {
                if (!isSuperAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setValidateSubscriptionPayment(e.target.checked);
              }}
              disabled={!isSuperAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Validate subscription payment success
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Confirm payment gateway has successfully processed subscription charge
              </p>
            </div>
          </label>

          <div className="bg-red-50 border border-red-200 rounded-lg p-3 mt-3">
            <div className="flex items-start gap-2">
              <XCircle className="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-red-800">
                <strong>Automatic Blocking:</strong> Vendors failing verification are automatically 
                blocked until issues are resolved. No manual review or override.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Subscription Enforcement */}
      <div className="border-t border-gray-200 pt-6">
        <div className="mb-4">
          <h3 className="font-semibold text-gray-900">Subscription Enforcement</h3>
          <p className="text-sm text-gray-600 mt-1">
            Configure subscription requirements for going live
          </p>
        </div>

        <div className="space-y-4">
          {/* Locked requirement */}
          <div className="border border-green-200 rounded-lg p-4 bg-green-50">
            <div className="flex items-center gap-3">
              <CreditCard className="w-5 h-5 text-green-600" />
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-semibold text-green-900">
                    Active subscription required to go live
                  </span>
                  <Lock className="w-4 h-4 text-green-600" />
                  <span className="text-xs px-2 py-0.5 bg-green-200 text-green-800 rounded-full font-medium">
                    Locked ON
                  </span>
                </div>
                <p className="text-xs text-green-800 mt-1">
                  Vendors cannot go live without an active subscription. This rule cannot be disabled.
                </p>
              </div>
            </div>
          </div>

          {/* Optional trial setting */}
          <div className="border border-gray-200 rounded-lg p-4">
            <label className="flex items-start gap-3">
              <input
                type="checkbox"
                checked={allowTrialWithoutPayment}
                onChange={(e) => {
                  if (!isSuperAdmin) {
                    toast.error('Access denied');
                    return;
                  }
                  setAllowTrialWithoutPayment(e.target.checked);
                }}
                disabled={!isSuperAdmin}
                className="mt-0.5 rounded border-gray-300"
              />
              <div className="flex-1">
                <span className="text-sm font-medium text-gray-900">
                  Allow trial without payment
                </span>
                <p className="text-xs text-gray-600 mt-0.5">
                  Linked to trial period settings in Subscription Governance
                </p>
              </div>
            </label>
          </div>

          {/* Behavior explanation */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <Info className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
              <div className="text-xs text-blue-800">
                <p className="mb-1">
                  <strong>Automatic Behavior:</strong>
                </p>
                <ul className="ml-4 space-y-0.5">
                  <li>• Vendors may complete onboarding during trial period</li>
                  <li>• Vendors cannot go live without meeting subscription rules</li>
                  <li>• No manual override or approval available</li>
                  <li>• System enforces automatically based on payment status</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Country Availability */}
      <div className="border-t border-gray-200 pt-6">
        <div className="mb-4">
          <h3 className="font-semibold text-gray-900">Vendor Eligibility by Country</h3>
          <p className="text-sm text-gray-600 mt-1">
            Select which countries vendors can register from
          </p>
        </div>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            {countries.map((country) => (
              <label
                key={country.code}
                className={`flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors ${
                  availableCountries.has(country.code)
                    ? 'border-green-300 bg-green-50'
                    : 'border-gray-200 bg-white hover:bg-gray-50'
                } ${!isSuperAdmin ? 'cursor-not-allowed opacity-60' : ''}`}
              >
                <input
                  type="checkbox"
                  checked={availableCountries.has(country.code)}
                  onChange={() => handleCountryToggle(country.code)}
                  disabled={!isSuperAdmin}
                  className="rounded border-gray-300"
                />
                <span className="text-2xl">{country.flag}</span>
                <span className="text-sm font-medium text-gray-900">{country.name}</span>
              </label>
            ))}
          </div>

          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p className="text-xs text-gray-700 mb-2">
              <strong>Country selection affects:</strong>
            </p>
            <ul className="text-xs text-gray-700 ml-4 space-y-0.5">
              <li>• VAT number validation rules</li>
              <li>• Legal compliance requirements</li>
              <li>• Payment provider availability</li>
              <li>• Tax calculation methods</li>
            </ul>
          </div>

          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <AlertTriangle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-amber-800">
                <strong>Legal Readiness Warning:</strong> Enabling a country implies legal and tax 
                readiness. Ensure compliance requirements are met before enabling new countries.
              </p>
            </div>
          </div>

          <div className="bg-red-50 border border-red-200 rounded-lg p-3">
            <div className="flex items-start gap-2">
              <XCircle className="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-red-800">
                <strong>Automatic Blocking:</strong> Vendors outside selected countries cannot 
                register. Registration form will be unavailable.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Automated Notifications */}
      <div className="border-t border-gray-200 pt-6">
        <div className="mb-4">
          <h3 className="font-semibold text-gray-900">Automated Notifications</h3>
          <p className="text-sm text-gray-600 mt-1">
            Configure system-generated notifications (no human decision emails)
          </p>
        </div>

        <div className="border border-gray-200 rounded-lg p-4 space-y-3">
          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={notifyOnRegistration}
              onChange={(e) => {
                if (!isSuperAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setNotifyOnRegistration(e.target.checked);
              }}
              disabled={!isSuperAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Notify admin team when vendor registers
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Send notification for monitoring purposes only (no action required)
              </p>
            </div>
          </label>

          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={sendProgressEmails}
              onChange={(e) => {
                if (!isSuperAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setSendProgressEmails(e.target.checked);
              }}
              disabled={!isSuperAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Send onboarding progress emails to vendor
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Automated emails showing completion status and next steps
              </p>
            </div>
          </label>

          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={sendGoLiveConfirmation}
              onChange={(e) => {
                if (!isSuperAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setSendGoLiveConfirmation(e.target.checked);
              }}
              disabled={!isSuperAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Send go-live confirmation
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Automated email when vendor successfully goes live
              </p>
            </div>
          </label>

          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={sendRejectionExplanation}
              onChange={(e) => {
                if (!isSuperAdmin) {
                  toast.error('Access denied');
                  return;
                }
                setSendRejectionExplanation(e.target.checked);
              }}
              disabled={!isSuperAdmin}
              className="mt-0.5 rounded border-gray-300"
            />
            <div className="flex-1">
              <span className="text-sm font-medium text-gray-900">
                Send automatic rejection explanation (rule-based)
              </span>
              <p className="text-xs text-gray-600 mt-0.5">
                Explain which automated verification failed (e.g., "VAT validation failed")
              </p>
            </div>
          </label>

          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-3">
            <div className="flex items-start gap-2">
              <Info className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-blue-800">
                <strong>Automation Only:</strong> All notifications are system-generated based on 
                state changes. No emails imply human approval or decision-making.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Requirement Change Modal */}
      {showRequirementChange && (
        <RequirementChangeModal
          type={showRequirementChange.type}
          value={showRequirementChange.value}
          changeReason={changeReason}
          setChangeReason={setChangeReason}
          onClose={() => {
            setShowRequirementChange(null);
            setChangeReason('');
          }}
          onConfirm={confirmRequirementChange}
        />
      )}

      {/* Country Change Modal */}
      {showCountryChange && (
        <CountryChangeModal
          country={countries.find(c => c.code === showCountryChange.country)!}
          adding={showCountryChange.adding}
          changeReason={changeReason}
          setChangeReason={setChangeReason}
          onClose={() => {
            setShowCountryChange(null);
            setChangeReason('');
          }}
          onConfirm={confirmCountryChange}
        />
      )}
    </div>
  );
}

// Requirement Change Modal
interface RequirementChangeModalProps {
  type: string;
  value: boolean;
  changeReason: string;
  setChangeReason: (reason: string) => void;
  onClose: () => void;
  onConfirm: () => void;
}

function RequirementChangeModal({
  type,
  value,
  changeReason,
  setChangeReason,
  onClose,
  onConfirm
}: RequirementChangeModalProps) {
  const typeLabels: Record<string, string> = {
    businessDocs: 'Business Registration Documents',
    foodSafety: 'Food Safety Certificates',
    bankAccount: 'Bank Account Verification'
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-lg w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
            <FileCheck className="w-6 h-6 text-purple-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">
              {value ? 'Add' : 'Remove'} Onboarding Requirement
            </h3>
            <p className="text-sm text-gray-600">{typeLabels[type]}</p>
          </div>
        </div>

        <div className="space-y-4">
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p className="text-sm text-gray-900 font-medium mb-2">
              {value ? 'Require' : 'Make optional'}: {typeLabels[type]}
            </p>
            <div className="bg-blue-50 border border-blue-200 rounded p-2 mt-2">
              <div className="flex items-start gap-2">
                <Info className="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" />
                <p className="text-xs text-blue-800">
                  {value 
                    ? 'Vendors will be blocked from going live until this requirement is satisfied.'
                    : 'Vendors will be able to go live without completing this requirement.'}
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
              placeholder="Explain why this onboarding requirement is being changed (minimum 10 characters)"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600 text-sm resize-none"
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

// Country Change Modal
interface CountryChangeModalProps {
  country: { code: string; name: string; flag: string };
  adding: boolean;
  changeReason: string;
  setChangeReason: (reason: string) => void;
  onClose: () => void;
  onConfirm: () => void;
}

function CountryChangeModal({
  country,
  adding,
  changeReason,
  setChangeReason,
  onClose,
  onConfirm
}: CountryChangeModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-lg w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
            <Globe className="w-6 h-6 text-blue-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">
              {adding ? 'Enable' : 'Disable'} Country
            </h3>
            <p className="text-sm text-gray-600">
              {country.flag} {country.name}
            </p>
          </div>
        </div>

        <div className="space-y-4">
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p className="text-sm text-gray-900 font-medium mb-2">
              {adding ? 'Enable vendor registration from' : 'Disable vendor registration from'} {country.name}
            </p>
            <div className={`${adding ? 'bg-amber-50 border-amber-200' : 'bg-red-50 border-red-200'} border rounded p-2 mt-2`}>
              <div className="flex items-start gap-2">
                <AlertTriangle className={`w-4 h-4 ${adding ? 'text-amber-600' : 'text-red-600'} flex-shrink-0 mt-0.5`} />
                <p className={`text-xs ${adding ? 'text-amber-800' : 'text-red-800'}`}>
                  {adding 
                    ? 'Ensure legal compliance, tax registration, and payment provider support are ready before enabling.'
                    : 'Existing vendors from this country will not be affected, but new registrations will be blocked.'}
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
              placeholder="Explain why this country availability is being changed (minimum 10 characters)"
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
