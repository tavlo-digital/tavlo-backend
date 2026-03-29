import { Building2, ArrowRight, ArrowLeft } from 'lucide-react';
import { Button } from '../ui/button';
import { useState } from 'react';

interface SetupStep2BasicsProps {
  onContinue: (data: RestaurantBasics) => void;
  onBack: () => void;
  initialData?: Partial<RestaurantBasics>;
}

interface RestaurantBasics {
  publicName: string;
  openingHours: {
    monday: { open: string; close: string; closed: boolean };
    tuesday: { open: string; close: string; closed: boolean };
    wednesday: { open: string; close: string; closed: boolean };
    thursday: { open: string; close: string; closed: boolean };
    friday: { open: string; close: string; closed: boolean };
    saturday: { open: string; close: string; closed: boolean };
    sunday: { open: string; close: string; closed: boolean };
  };
  currency: string;
  timezone: string;
}

const TIMEZONES = [
  'Europe/Vienna',
  'Europe/Berlin',
  'Europe/Zurich',
  'Europe/Rome',
  'Europe/Paris'
];

const DEFAULT_HOURS = {
  open: '09:00',
  close: '22:00',
  closed: false
};

export function SetupStep2Basics({ onContinue, onBack, initialData }: SetupStep2BasicsProps) {
  const [formData, setFormData] = useState<RestaurantBasics>({
    publicName: initialData?.publicName || '',
    openingHours: initialData?.openingHours || {
      monday: { ...DEFAULT_HOURS },
      tuesday: { ...DEFAULT_HOURS },
      wednesday: { ...DEFAULT_HOURS },
      thursday: { ...DEFAULT_HOURS },
      friday: { ...DEFAULT_HOURS },
      saturday: { ...DEFAULT_HOURS },
      sunday: { ...DEFAULT_HOURS }
    },
    currency: initialData?.currency || 'EUR',
    timezone: initialData?.timezone || 'Europe/Vienna'
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.publicName.trim()) {
      newErrors.publicName = 'Restaurant name is required';
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

  const updateHours = (day: keyof typeof formData.openingHours, field: 'open' | 'close' | 'closed', value: string | boolean) => {
    setFormData({
      ...formData,
      openingHours: {
        ...formData.openingHours,
        [day]: {
          ...formData.openingHours[day],
          [field]: value
        }
      }
    });
  };

  const applyToAllDays = () => {
    const mondayHours = formData.openingHours.monday;
    const newHours = Object.keys(formData.openingHours).reduce((acc, day) => ({
      ...acc,
      [day]: { ...mondayHours }
    }), {} as typeof formData.openingHours);
    
    setFormData({ ...formData, openingHours: newHours });
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-2xl mx-auto px-4 py-8">
        {/* Progress */}
        <div className="mb-8">
          <p className="text-sm text-gray-500 mb-2">Activation step 2 of 4</p>
          <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
            <div className="h-full bg-emerald-600 rounded-full" style={{ width: '50%' }}></div>
          </div>
        </div>

        {/* Header */}
        <div className="mb-8">
          <div className="inline-flex items-center justify-center w-12 h-12 bg-emerald-100 rounded-lg mb-4">
            <Building2 className="w-6 h-6 text-emerald-600" />
          </div>
          <h1 className="text-3xl mb-2 text-gray-900">Restaurant Basics</h1>
          <p className="text-gray-600">
            Set up your restaurant's public information and operating hours.
          </p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
            {/* Public Name */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Public restaurant name <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.publicName}
                onChange={(e) => setFormData({ ...formData, publicName: e.target.value })}
                placeholder="e.g., Tavlo Restaurant"
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                  errors.publicName ? 'border-red-500' : 'border-gray-300'
                }`}
              />
              {errors.publicName && (
                <p className="mt-1 text-sm text-red-600">{errors.publicName}</p>
              )}
              <p className="mt-1 text-xs text-gray-500">
                This name will be shown to customers
              </p>
            </div>

            {/* Opening Hours */}
            <div>
              <div className="flex items-center justify-between mb-3">
                <label className="block text-sm text-gray-700">
                  Opening hours
                </label>
                <button
                  type="button"
                  onClick={applyToAllDays}
                  className="text-sm text-emerald-600 hover:text-emerald-700"
                >
                  Copy Monday to all days
                </button>
              </div>
              
              <div className="space-y-3">
                {Object.entries(formData.openingHours).map(([day, hours]) => (
                  <div key={day} className="flex items-center gap-3">
                    <div className="w-24">
                      <p className="text-sm text-gray-700 capitalize">{day}</p>
                    </div>
                    
                    <label className="flex items-center">
                      <input
                        type="checkbox"
                        checked={hours.closed}
                        onChange={(e) => updateHours(day as keyof typeof formData.openingHours, 'closed', e.target.checked)}
                        className="w-4 h-4 text-emerald-600 rounded"
                      />
                      <span className="ml-2 text-sm text-gray-600">Closed</span>
                    </label>

                    {!hours.closed && (
                      <>
                        <input
                          type="time"
                          value={hours.open}
                          onChange={(e) => updateHours(day as keyof typeof formData.openingHours, 'open', e.target.value)}
                          className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        <span className="text-gray-500">to</span>
                        <input
                          type="time"
                          value={hours.close}
                          onChange={(e) => updateHours(day as keyof typeof formData.openingHours, 'close', e.target.value)}
                          className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                      </>
                    )}
                  </div>
                ))}
              </div>
              <p className="mt-2 text-xs text-gray-500">
                Customers will see these hours on your menu
              </p>
            </div>

            {/* Currency - Locked */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Currency
              </label>
              <div className="px-4 py-3 bg-gray-50 border border-gray-300 rounded-lg text-gray-500">
                EUR (€) - Euro
              </div>
              <p className="mt-1 text-xs text-gray-500">
                Based on your country selection (locked)
              </p>
            </div>

            {/* Timezone - Locked */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Timezone
              </label>
              <select
                value={formData.timezone}
                onChange={(e) => setFormData({ ...formData, timezone: e.target.value })}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
              >
                {TIMEZONES.map(tz => (
                  <option key={tz} value={tz}>{tz}</option>
                ))}
              </select>
              <p className="mt-1 text-xs text-gray-500">
                Used for order timestamps and analytics
              </p>
            </div>
          </div>

          {/* Navigation */}
          <div className="flex items-center justify-between">
            <Button
              type="button"
              onClick={onBack}
              className="bg-gray-100 hover:bg-gray-200 text-gray-900 px-6 py-3"
            >
              <ArrowLeft className="w-4 h-4 mr-2" />
              Back
            </Button>
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