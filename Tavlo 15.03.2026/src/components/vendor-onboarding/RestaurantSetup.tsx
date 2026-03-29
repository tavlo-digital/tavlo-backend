import { useState } from 'react';
import { Building2, MapPin, Clock, DollarSign } from 'lucide-react';
import { Button } from '../ui/button';

interface RestaurantData {
  name: string;
  address: string;
  currency: string;
  openingHours: {
    [key: string]: { open: string; close: string; closed: boolean };
  };
}

interface RestaurantSetupProps {
  initialData?: Partial<RestaurantData>;
  onSave: (data: RestaurantData) => void;
  onSkip?: () => void;
}

const CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF'];
const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

export function RestaurantSetup({ initialData, onSave, onSkip }: RestaurantSetupProps) {
  const [formData, setFormData] = useState<RestaurantData>({
    name: initialData?.name || '',
    address: initialData?.address || '',
    currency: initialData?.currency || 'EUR',
    openingHours: initialData?.openingHours || DAYS.reduce((acc, day) => ({
      ...acc,
      [day]: { open: '09:00', close: '22:00', closed: false }
    }), {})
  });

  const [errors, setErrors] = useState<Record<string, string>>({});

  const validate = () => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.name.trim()) {
      newErrors.name = 'Restaurant name is required';
    }
    if (!formData.address.trim()) {
      newErrors.address = 'Address is required';
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

  const toggleDayClosed = (day: string) => {
    setFormData({
      ...formData,
      openingHours: {
        ...formData.openingHours,
        [day]: {
          ...formData.openingHours[day],
          closed: !formData.openingHours[day].closed
        }
      }
    });
  };

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-4xl mx-auto px-4 sm:px-6">
        <div className="bg-white rounded-2xl shadow-sm p-8">
          {/* Header */}
          <div className="mb-8">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                <Building2 className="w-6 h-6 text-emerald-600" />
              </div>
              <div>
                <h1 className="text-2xl text-gray-900">Restaurant Profile</h1>
                <p className="text-gray-600">Basic information about your restaurant</p>
              </div>
            </div>
          </div>

          <form onSubmit={handleSubmit} className="space-y-8">
            {/* Restaurant Name */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Restaurant Name *
              </label>
              <input
                type="text"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                className={`w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                  errors.name ? 'border-red-500' : 'border-gray-300'
                }`}
                placeholder="e.g., Bella Italia"
              />
              {errors.name && (
                <p className="mt-1 text-sm text-red-600">{errors.name}</p>
              )}
            </div>

            {/* Address */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Address *
              </label>
              <div className="relative">
                <MapPin className="absolute left-3 top-3 w-5 h-5 text-gray-400" />
                <textarea
                  value={formData.address}
                  onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                  className={`w-full pl-11 pr-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                    errors.address ? 'border-red-500' : 'border-gray-300'
                  }`}
                  rows={2}
                  placeholder="Street, City, Postal Code"
                />
              </div>
              {errors.address && (
                <p className="mt-1 text-sm text-red-600">{errors.address}</p>
              )}
            </div>

            {/* Currency */}
            <div>
              <label className="block text-sm text-gray-700 mb-2">
                Currency
              </label>
              <div className="relative">
                <DollarSign className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                <select
                  value={formData.currency}
                  onChange={(e) => setFormData({ ...formData, currency: e.target.value })}
                  className="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 appearance-none bg-white"
                >
                  {CURRENCIES.map(curr => (
                    <option key={curr} value={curr}>{curr}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* Opening Hours */}
            <div>
              <label className="block text-sm text-gray-700 mb-3">
                <Clock className="inline w-4 h-4 mr-2" />
                Opening Hours
              </label>
              <div className="space-y-3 border border-gray-200 rounded-lg p-4">
                {DAYS.map(day => (
                  <div key={day} className="flex items-center gap-4">
                    <div className="w-28 text-sm text-gray-700">{day}</div>
                    <input
                      type="checkbox"
                      checked={!formData.openingHours[day].closed}
                      onChange={() => toggleDayClosed(day)}
                      className="w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500"
                    />
                    <div className="flex items-center gap-2 flex-1">
                      <input
                        type="time"
                        value={formData.openingHours[day].open}
                        disabled={formData.openingHours[day].closed}
                        onChange={(e) => setFormData({
                          ...formData,
                          openingHours: {
                            ...formData.openingHours,
                            [day]: { ...formData.openingHours[day], open: e.target.value }
                          }
                        })}
                        className="px-3 py-2 border border-gray-300 rounded-lg text-sm disabled:bg-gray-100 disabled:text-gray-400"
                      />
                      <span className="text-gray-500">-</span>
                      <input
                        type="time"
                        value={formData.openingHours[day].close}
                        disabled={formData.openingHours[day].closed}
                        onChange={(e) => setFormData({
                          ...formData,
                          openingHours: {
                            ...formData.openingHours,
                            [day]: { ...formData.openingHours[day], close: e.target.value }
                          }
                        })}
                        className="px-3 py-2 border border-gray-300 rounded-lg text-sm disabled:bg-gray-100 disabled:text-gray-400"
                      />
                      {formData.openingHours[day].closed && (
                        <span className="text-sm text-gray-500">Closed</span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Actions */}
            <div className="flex items-center justify-between pt-6 border-t border-gray-200">
              {onSkip && (
                <Button
                  type="button"
                  variant="ghost"
                  onClick={onSkip}
                  className="text-gray-600"
                >
                  Skip for now
                </Button>
              )}
              <div className="flex gap-3 ml-auto">
                <Button
                  type="submit"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white px-8"
                >
                  Save & continue
                </Button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
