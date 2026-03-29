import { useState } from 'react';
import { X, Filter } from 'lucide-react';

export interface VendorFilters {
  plan?: string[];
  country?: string[];
  city?: string[];
  liveStatus?: 'live' | 'not-live';
  subscriptionState?: string[];
}

interface VendorExtendedFiltersProps {
  filters: VendorFilters;
  onFiltersChange: (filters: VendorFilters) => void;
  onClearAll: () => void;
}

export function VendorExtendedFilters({ 
  filters, 
  onFiltersChange, 
  onClearAll 
}: VendorExtendedFiltersProps) {
  const [showFilters, setShowFilters] = useState(false);

  // Mock data - would come from API in production
  const countries = ['Austria', 'Germany', 'Switzerland'];
  const citiesByCountry: Record<string, string[]> = {
    'Austria': ['Vienna', 'Salzburg', 'Innsbruck', 'Graz'],
    'Germany': ['Munich', 'Berlin', 'Hamburg', 'Frankfurt'],
    'Switzerland': ['Zurich', 'Geneva', 'Basel', 'Bern']
  };

  const plans = ['Basic', 'Standard', 'Premium', 'Trial'];
  const subscriptionStates = ['Active', 'Expired', 'Trial', 'Overdue'];

  const toggleFilter = (category: keyof VendorFilters, value: string) => {
    const current = filters[category] as string[] || [];
    const updated = current.includes(value)
      ? current.filter(v => v !== value)
      : [...current, value];
    
    onFiltersChange({
      ...filters,
      [category]: updated.length > 0 ? updated : undefined
    });
  };

  const setLiveStatus = (status: 'live' | 'not-live' | undefined) => {
    onFiltersChange({
      ...filters,
      liveStatus: status
    });
  };

  const getAvailableCities = () => {
    if (!filters.country || filters.country.length === 0) {
      return Object.values(citiesByCountry).flat();
    }
    return filters.country.flatMap(country => citiesByCountry[country] || []);
  };

  const getActiveFilterCount = () => {
    let count = 0;
    if (filters.plan?.length) count += filters.plan.length;
    if (filters.country?.length) count += filters.country.length;
    if (filters.city?.length) count += filters.city.length;
    if (filters.liveStatus) count += 1;
    if (filters.subscriptionState?.length) count += filters.subscriptionState.length;
    return count;
  };

  const activeFilterCount = getActiveFilterCount();

  return (
    <div className="bg-white border-b border-gray-200 px-6 py-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <button
            onClick={() => setShowFilters(!showFilters)}
            className={`flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-all ${
              showFilters
                ? 'bg-purple-50 border-purple-600 text-purple-700'
                : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
            }`}
          >
            <Filter className="w-4 h-4" />
            <span className="text-sm font-medium">Advanced Filters</span>
            {activeFilterCount > 0 && (
              <span className="ml-1 px-1.5 py-0.5 bg-purple-600 text-white rounded-full text-xs font-semibold">
                {activeFilterCount}
              </span>
            )}
          </button>

          {activeFilterCount > 0 && (
            <button
              onClick={onClearAll}
              className="text-sm text-gray-600 hover:text-gray-900 underline"
            >
              Clear all filters
            </button>
          )}
        </div>

        {/* Active Filter Chips */}
        {activeFilterCount > 0 && (
          <div className="flex items-center gap-2 flex-wrap">
            {filters.plan?.map(plan => (
              <div key={plan} className="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 border border-blue-200 rounded text-xs font-medium text-blue-700">
                Plan: {plan}
                <button onClick={() => toggleFilter('plan', plan)} className="hover:bg-blue-100 rounded p-0.5">
                  <X className="w-3 h-3" />
                </button>
              </div>
            ))}
            {filters.country?.map(country => (
              <div key={country} className="inline-flex items-center gap-1 px-2 py-1 bg-green-50 border border-green-200 rounded text-xs font-medium text-green-700">
                Country: {country}
                <button onClick={() => toggleFilter('country', country)} className="hover:bg-green-100 rounded p-0.5">
                  <X className="w-3 h-3" />
                </button>
              </div>
            ))}
            {filters.city?.map(city => (
              <div key={city} className="inline-flex items-center gap-1 px-2 py-1 bg-teal-50 border border-teal-200 rounded text-xs font-medium text-teal-700">
                City: {city}
                <button onClick={() => toggleFilter('city', city)} className="hover:bg-teal-100 rounded p-0.5">
                  <X className="w-3 h-3" />
                </button>
              </div>
            ))}
            {filters.liveStatus && (
              <div className="inline-flex items-center gap-1 px-2 py-1 bg-purple-50 border border-purple-200 rounded text-xs font-medium text-purple-700">
                Live: {filters.liveStatus === 'live' ? 'Yes' : 'No'}
                <button onClick={() => setLiveStatus(undefined)} className="hover:bg-purple-100 rounded p-0.5">
                  <X className="w-3 h-3" />
                </button>
              </div>
            )}
            {filters.subscriptionState?.map(state => (
              <div key={state} className="inline-flex items-center gap-1 px-2 py-1 bg-orange-50 border border-orange-200 rounded text-xs font-medium text-orange-700">
                Subscription: {state}
                <button onClick={() => toggleFilter('subscriptionState', state)} className="hover:bg-orange-100 rounded p-0.5">
                  <X className="w-3 h-3" />
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Filter Panel */}
      {showFilters && (
        <div className="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
          <div className="grid grid-cols-4 gap-6">
            {/* Plan Filter */}
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">
                Subscription Plan
              </label>
              <div className="space-y-2">
                {plans.map(plan => (
                  <label key={plan} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                    <input
                      type="checkbox"
                      checked={filters.plan?.includes(plan) || false}
                      onChange={() => toggleFilter('plan', plan)}
                      className="rounded border-gray-300 text-purple-600 focus:ring-purple-600"
                    />
                    <span className="text-gray-900">{plan}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Country Filter */}
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">
                Country
              </label>
              <div className="space-y-2">
                {countries.map(country => (
                  <label key={country} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                    <input
                      type="checkbox"
                      checked={filters.country?.includes(country) || false}
                      onChange={() => toggleFilter('country', country)}
                      className="rounded border-gray-300 text-purple-600 focus:ring-purple-600"
                    />
                    <span className="text-gray-900">{country}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* City Filter (dependent on country) */}
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">
                City
                {filters.country && filters.country.length > 0 && (
                  <span className="ml-1 text-xs font-normal text-gray-500">
                    (filtered by country)
                  </span>
                )}
              </label>
              <div className="space-y-2 max-h-32 overflow-y-auto">
                {getAvailableCities().map(city => (
                  <label key={city} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                    <input
                      type="checkbox"
                      checked={filters.city?.includes(city) || false}
                      onChange={() => toggleFilter('city', city)}
                      className="rounded border-gray-300 text-purple-600 focus:ring-purple-600"
                    />
                    <span className="text-gray-900">{city}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Live Status & Subscription State */}
            <div className="space-y-4">
              {/* Live Status */}
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">
                  Live Status
                </label>
                <div className="space-y-2">
                  <label className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                    <input
                      type="radio"
                      checked={filters.liveStatus === 'live'}
                      onChange={() => setLiveStatus('live')}
                      className="border-gray-300 text-purple-600 focus:ring-purple-600"
                    />
                    <span className="text-gray-900">Live</span>
                  </label>
                  <label className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                    <input
                      type="radio"
                      checked={filters.liveStatus === 'not-live'}
                      onChange={() => setLiveStatus('not-live')}
                      className="border-gray-300 text-purple-600 focus:ring-purple-600"
                    />
                    <span className="text-gray-900">Not Live</span>
                  </label>
                  <label className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                    <input
                      type="radio"
                      checked={!filters.liveStatus}
                      onChange={() => setLiveStatus(undefined)}
                      className="border-gray-300 text-purple-600 focus:ring-purple-600"
                    />
                    <span className="text-gray-900">Both</span>
                  </label>
                </div>
              </div>

              {/* Subscription State */}
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">
                  Subscription
                </label>
                <div className="space-y-2">
                  {subscriptionStates.map(state => (
                    <label key={state} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                      <input
                        type="checkbox"
                        checked={filters.subscriptionState?.includes(state) || false}
                        onChange={() => toggleFilter('subscriptionState', state)}
                        className="rounded border-gray-300 text-purple-600 focus:ring-purple-600"
                      />
                      <span className="text-gray-900">{state}</span>
                    </label>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
