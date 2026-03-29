import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Shield, Info, CheckCircle } from 'lucide-react';
import { 
  getCountryTaxRules, 
  type Country,
  type TaxRule 
} from '@/lib/taxRules';

interface TaxRulesDisplayProps {
  country: Country;
  compact?: boolean;
  showUsageCount?: boolean;
}

/**
 * Displays country-specific VAT rules in a read-only, authoritative format
 * Reinforces that VAT is system-managed, not vendor-editable
 */
export function TaxRulesDisplay({ country, compact = false, showUsageCount = false }: TaxRulesDisplayProps) {
  const countryRules = getCountryTaxRules(country);

  if (compact) {
    return (
      <div className="space-y-2">
        {countryRules.rules.map((rule) => (
          <div 
            key={rule.category}
            className="flex items-center justify-between p-3 border rounded-lg bg-gray-50"
          >
            <div className="flex items-center gap-2">
              <span className="text-xl">{rule.categoryIcon}</span>
              <div>
                <div className="text-sm">{rule.categoryLabel}</div>
                <div className="text-xs text-gray-500">
                  {rule.description}
                </div>
              </div>
            </div>
            <Badge variant="secondary" className="text-sm font-mono">
              {rule.vatRate}% VAT
            </Badge>
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-start gap-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <Info className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
        <div className="text-sm text-blue-900">
          <p className="mb-1">
            <strong>VAT rates are defined by local tax law and automatically applied by Tavlo.</strong>
          </p>
          <p className="text-blue-700">
            You classify your items by tax category, and Tavlo ensures legal compliance.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {countryRules.rules.map((rule) => (
          <TaxRuleCard key={rule.category} rule={rule} country={country} showUsageCount={showUsageCount} />
        ))}
      </div>

      <div className="flex items-center gap-2 text-sm text-gray-600 mt-4">
        <CheckCircle className="w-4 h-4 text-green-600" />
        <span>Loaded from Tavlo Tax Rules Database</span>
      </div>
    </div>
  );
}

interface TaxRuleCardProps {
  rule: TaxRule;
  country: Country;
  showUsageCount?: boolean;
}

function TaxRuleCard({ rule, country, showUsageCount = false }: TaxRuleCardProps) {
  // TODO: This would come from actual menu items in production
  const mockUsageCount = rule.category === 'food' ? 12 : rule.category === 'beverage-non-alcoholic' ? 8 : 5;
  return (
    <Card className="relative overflow-hidden border-2">
      <div className="absolute top-0 right-0 w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 transform rotate-45 translate-x-10 -translate-y-10" />
      
      <CardHeader className="pb-3">
        <div className="flex items-center gap-2 mb-2">
          <span className="text-3xl">{rule.categoryIcon}</span>
          <Shield className="w-4 h-4 text-gray-400 ml-auto" />
        </div>
        <CardTitle className="text-base">{rule.categoryLabel}</CardTitle>
        <CardDescription className="text-xs">
          {rule.description}
        </CardDescription>
      </CardHeader>
      
      <CardContent>
        <div className="flex items-baseline gap-1">
          <span className="text-3xl font-mono">{rule.vatRate}</span>
          <span className="text-lg text-gray-500">% VAT</span>
        </div>
        <div className="text-xs text-gray-500 mt-1">
          Country-based rate
        </div>
        {showUsageCount && (
          <div className="text-xs text-gray-600 mt-2 pt-2 border-t">
            Used by <strong>{mockUsageCount}</strong> menu items
          </div>
        )}
      </CardContent>
    </Card>
  );
}

interface CountrySelectorProps {
  selectedCountry: Country;
  onCountryChange: (country: Country) => void;
  disabled?: boolean;
}

/**
 * Country selector for tax settings
 * Shows available countries with flags
 */
export function CountrySelector({ 
  selectedCountry, 
  onCountryChange,
  disabled = false 
}: CountrySelectorProps) {
  const countries: Array<{ code: Country; name: string; flag: string }> = [
    { code: 'AT', name: 'Austria', flag: '🇦🇹' },
    { code: 'DE', name: 'Germany', flag: '🇩🇪' }
  ];

  return (
    <div className="space-y-2">
      <label className="block text-sm text-gray-600">
        Restaurant Country
      </label>
      <div className="grid grid-cols-2 gap-3">
        {countries.map((country) => (
          <button
            key={country.code}
            type="button"
            onClick={() => !disabled && onCountryChange(country.code)}
            disabled={disabled}
            className={`
              flex items-center gap-3 p-4 border-2 rounded-lg transition-all
              ${selectedCountry === country.code 
                ? 'border-gray-900 bg-gray-50 shadow-sm' 
                : 'border-gray-200 hover:border-gray-400'
              }
              ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
            `}
          >
            <span className="text-3xl">{country.flag}</span>
            <div className="text-left">
              <div className="font-medium">{country.name}</div>
              <div className="text-xs text-gray-500">
                {country.code === selectedCountry && '✓ Selected'}
              </div>
            </div>
          </button>
        ))}
      </div>
      {disabled && (
        <p className="text-xs text-gray-500 flex items-center gap-1.5">
          <Info className="w-3 h-3" />
          Country cannot be changed after initial setup. Contact support if you need assistance.
        </p>
      )}
    </div>
  );
}
