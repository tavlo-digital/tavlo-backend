import { FileText, ArrowRight } from 'lucide-react';
import { Button } from '../ui/button';
import { useState } from 'react';

interface SetupStep1LegalProps {
  onContinue: (data: LegalData) => void;
  initialData?: Partial<LegalData>;
}

interface LegalData {
  legalBusinessName: string;
  vatNumber: string;
  registeredAddress: string;
  country: string;
  receiptLanguage: string;
}

const COUNTRIES = [
  'Austria', 'Germany', 'Switzerland', 'Italy', 'France', 'Spain'
];

const LANGUAGES = [
  { code: 'de', name: 'German' },
  { code: 'en', name: 'English' },
  { code: 'fr', name: 'French' },
  { code: 'it', name: 'Italian' }
];

export function SetupStep1Legal({ onContinue, initialData }: SetupStep1LegalProps) {
  const [formData, setFormData] = useState<LegalData>({
    legalBusinessName: initialData?.legalBusinessName || '',
    vatNumber: initialData?.vatNumber || '',
    registeredAddress: initialData?.registeredAddress || '',
    country: initialData?.country || 'Austria',
    receiptLanguage: initialData?.receiptLanguage || 'de'
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.legalBusinessName.trim()) {
      newErrors.legalBusinessName = 'Legal business name is required';
    }
    if (!formData.vatNumber.trim()) {
      newErrors.vatNumber = 'VAT/Tax number is required';
    }
    if (!formData.registeredAddress.trim()) {
      newErrors.registeredAddress = 'Registered address is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (validate()) {
      onContinue(formData);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-2xl mx-auto px-4 py-8">
        {/* Progress */}
        <div className="mb-8">
          <p className="text-sm text-gray-500 mb-2">Activation step 1 of 1</p>
          <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
            <div className="h-full bg-emerald-600 rounded-full" style={{ width: '100%' }}></div>
          </div>
        </div>

        {/* Header */}
        <div className="mb-8">
          <div className="inline-flex items-center justify-center w-12 h-12 bg-emerald-100 rounded-lg mb-4">
            <FileText className="w-6 h-6 text-emerald-600" />
          </div>
          <h1 className="text-3xl mb-2 text-gray-900">Legal & tax details (required to go live)</h1>
          <p className="text-gray-600">
            Tavlo uses this information to generate compliant invoices and receipts.
          </p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            {/* Legal Business Name */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Legal business name <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.legalBusinessName}
                onChange={(e) => setFormData({ ...formData, legalBusinessName: e.target.value })}
                placeholder="e.g., Tavlo Restaurant GmbH"
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                  errors.legalBusinessName ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.legalBusinessName && (
                <p className="mt-1 text-sm text-red-600">{errors.legalBusinessName}</p>
              )}
              <p className="mt-1 text-xs text-gray-500">
                As registered with your tax authority
              </p>
            </div>

            {/* VAT Number */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                VAT / Tax number <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.vatNumber}
                onChange={(e) => setFormData({ ...formData, vatNumber: e.target.value })}
                placeholder="e.g., ATU12345678"
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                  errors.vatNumber ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.vatNumber && (
                <p className="mt-1 text-sm text-red-600">{errors.vatNumber}</p>
              )}
              <p className="mt-1 text-xs text-gray-500">
                Your official tax identification number
              </p>
            </div>

            {/* Registered Address */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Registered address <span className="text-red-500">*</span>
              </label>
              <textarea
                value={formData.registeredAddress}
                onChange={(e) => setFormData({ ...formData, registeredAddress: e.target.value })}
                placeholder="Street, Number, Postal Code, City"
                rows={3}
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                  errors.registeredAddress ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.registeredAddress && (
                <p className="mt-1 text-sm text-red-600">{errors.registeredAddress}</p>
              )}
              <p className="mt-1 text-xs text-gray-500">
                Full registered business address
              </p>
            </div>

            {/* Country */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Country <span className="text-red-500">*</span>
              </label>
              <select
                value={formData.country}
                onChange={(e) => setFormData({ ...formData, country: e.target.value })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                {COUNTRIES.map(country => (
                  <option key={country} value={country}>{country}</option>
                ))}
              </select>
              <p className="mt-1 text-xs text-gray-500">
                Country of business registration
              </p>
            </div>

            {/* Receipt Language */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Receipt language <span className="text-red-500">*</span>
              </label>
              <select
                value={formData.receiptLanguage}
                onChange={(e) => setFormData({ ...formData, receiptLanguage: e.target.value })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                {LANGUAGES.map(lang => (
                  <option key={lang.code} value={lang.code}>{lang.name}</option>
                ))}
              </select>
              <p className="mt-1 text-xs text-gray-500">
                Language for receipts and invoices
              </p>
            </div>
          </div>

          {/* Info Box */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <p className="text-sm text-blue-900">
              <strong>Why do we need this?</strong> This information is required for tax compliance 
              and will appear on all customer receipts and invoices generated by Tavlo.
            </p>
          </div>

          {/* Submit */}
          <div className="flex items-center justify-between">
            <p className="text-sm text-gray-500">
              <span className="text-red-500">*</span> Required fields
            </p>
            <Button
              type="submit"
              className="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-3"
            >
              Save & continue
              <ArrowRight className="w-4 h-4 ml-2" />
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}