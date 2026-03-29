import { useState } from 'react';
import { Building2, MapPin, FileText, CheckCircle2, AlertCircle } from 'lucide-react';
import { Button } from '../ui/button';

interface LegalData {
  legalEntityName: string;
  legalAddress: string;
  vatNumber: string;
  vatValidated: boolean;
}

interface LegalDataFormProps {
  initialData?: Partial<LegalData>;
  onSave: (data: LegalData) => void;
  onSkip?: () => void;
  isRequired?: boolean;
}

export function LegalDataForm({
  initialData,
  onSave,
  onSkip,
  isRequired = false
}: LegalDataFormProps) {
  const [formData, setFormData] = useState<LegalData>({
    legalEntityName: initialData?.legalEntityName || '',
    legalAddress: initialData?.legalAddress || '',
    vatNumber: initialData?.vatNumber || '',
    vatValidated: initialData?.vatValidated || false
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [validating, setValidating] = useState(false);

  const validateVAT = async () => {
    if (!formData.vatNumber.trim()) return;

    setValidating(true);
    // Simulate VAT validation API call
    await new Promise(resolve => setTimeout(resolve, 1500));
    
    // Mock validation result
    const isValid = formData.vatNumber.startsWith('ATU');
    setFormData({ ...formData, vatValidated: isValid });
    setValidating(false);

    if (!isValid) {
      setErrors({ ...errors, vatNumber: 'VAT number could not be validated' });
    } else {
      const newErrors = { ...errors };
      delete newErrors.vatNumber;
      setErrors(newErrors);
    }
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};
    
    if (isRequired) {
      if (!formData.legalEntityName.trim()) {
        newErrors.legalEntityName = 'Legal entity name is required';
      }
      if (!formData.legalAddress.trim()) {
        newErrors.legalAddress = 'Legal address is required';
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (validate()) {
      onSave(formData);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-3xl mx-auto px-4 sm:px-6">
        <div className="bg-white rounded-2xl shadow-sm p-8">
          {/* Header */}
          <div className="mb-8">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                <FileText className="w-6 h-6 text-blue-600" />
              </div>
              <div>
                <h1 className="text-2xl text-gray-900">Legal Information</h1>
                <p className="text-gray-600">
                  {isRequired 
                    ? 'Required for invoice generation'
                    : 'You can complete this later'
                  }
                </p>
              </div>
            </div>

            {/* Info Banner */}
            <div className="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-3">
              <AlertCircle className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
              <div className="flex-1 text-sm text-blue-900">
                <p className="mb-1">
                  <strong>Orders are enabled</strong> — You can start accepting orders immediately.
                </p>
                <p>
                  <strong>Invoices are disabled</strong> until legal data is provided and validated.
                </p>
              </div>
            </div>
          </div>

          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Legal Entity Name */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Legal Entity Name {isRequired && '*'}
              </label>
              <div className="relative">
                <Building2 className="absolute left-3 top-3 w-5 h-5 text-gray-400" />
                <input
                  type="text"
                  value={formData.legalEntityName}
                  onChange={(e) => setFormData({ ...formData, legalEntityName: e.target.value })}
                  className={`w-full pl-11 pr-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                    errors.legalEntityName ? 'border-red-500' : 'border-gray-300'
                  }`}
                  placeholder="Official registered company name"
                />
              </div>
              {errors.legalEntityName && (
                <p className="mt-1 text-sm text-red-600">{errors.legalEntityName}</p>
              )}
            </div>

            {/* Legal Address */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Legal Address {isRequired && '*'}
              </label>
              <div className="relative">
                <MapPin className="absolute left-3 top-3 w-5 h-5 text-gray-400" />
                <textarea
                  value={formData.legalAddress}
                  onChange={(e) => setFormData({ ...formData, legalAddress: e.target.value })}
                  className={`w-full pl-11 pr-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                    errors.legalAddress ? 'border-red-500' : 'border-gray-300'
                  }`}
                  rows={3}
                  placeholder="Full legal address as registered"
                />
              </div>
              {errors.legalAddress && (
                <p className="mt-1 text-sm text-red-600">{errors.legalAddress}</p>
              )}
            </div>

            {/* VAT Number */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                VAT Number (Optional)
              </label>
              <div className="flex gap-3">
                <div className="flex-1 relative">
                  <input
                    type="text"
                    value={formData.vatNumber}
                    onChange={(e) => {
                      setFormData({ ...formData, vatNumber: e.target.value, vatValidated: false });
                      const newErrors = { ...errors };
                      delete newErrors.vatNumber;
                      setErrors(newErrors);
                    }}
                    className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                      errors.vatNumber ? 'border-red-500' : 
                      formData.vatValidated ? 'border-green-500' : 
                      'border-gray-300'
                    }`}
                    placeholder="e.g., ATU12345678"
                  />
                  {formData.vatValidated && (
                    <CheckCircle2 className="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-green-600" />
                  )}
                </div>
                <Button
                  type="button"
                  onClick={validateVAT}
                  disabled={!formData.vatNumber.trim() || validating || formData.vatValidated}
                  variant="outline"
                  className="px-6"
                >
                  {validating ? 'Validating...' : formData.vatValidated ? 'Validated' : 'Validate'}
                </Button>
              </div>
              {errors.vatNumber && (
                <p className="mt-1 text-sm text-red-600">{errors.vatNumber}</p>
              )}
              {formData.vatValidated && (
                <p className="mt-1 text-sm text-green-600 flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4" />
                  VAT number validated successfully
                </p>
              )}
              <p className="mt-1 text-xs text-gray-500">
                VAT number is optional but recommended for EU businesses
              </p>
            </div>

            {/* Actions */}
            <div className="flex items-center justify-between pt-6 border-t border-gray-200">
              {onSkip && !isRequired && (
                <Button
                  type="button"
                  variant="ghost"
                  onClick={onSkip}
                  className="text-gray-600"
                >
                  Complete this later
                </Button>
              )}
              <div className="flex gap-3 ml-auto">
                <Button
                  type="submit"
                  className="bg-blue-600 hover:bg-blue-700 text-white px-8"
                >
                  Save legal data
                </Button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
