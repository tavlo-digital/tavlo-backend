import { useState } from 'react';
import { ChevronDown, Globe, CreditCard, Award, Shield, FileText } from 'lucide-react';
import { RestaurantBadges } from '../shared/RestaurantBadges';

interface AboutSectionProps {
  name: string;
  description: string;
  features?: {
    loyaltyProgram?: boolean;
    loyaltyRate?: string;
    takeawayAvailable?: boolean;
    fastDelivery?: boolean;
    promotionsActive?: boolean;
    verified?: boolean;
    acceptsCards?: boolean;
    freeDelivery?: boolean;
  };
  website?: string;
  vatNumber?: string;
  paymentMethods?: string[];
  reviewCount: number;
  yearsExperience?: number;
}

export function AboutSection({
  name,
  description,
  features,
  website,
  vatNumber,
  paymentMethods = ['Visa', 'Mastercard', 'Cash', 'Amex'],
  reviewCount,
  yearsExperience = 10
}: AboutSectionProps) {
  const [showVat, setShowVat] = useState(false);

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8 space-y-6">
      {/* Description - Short & Sweet */}
      <div className="bg-white rounded-2xl p-6 sm:p-8 border border-gray-100">
        <h2 className="text-2xl mb-4">About {name}</h2>
        <p className="text-gray-700 leading-relaxed">
          {description}
        </p>
      </div>

      {/* Key Stats */}
      <div className="grid grid-cols-2 gap-4">
        <div className="bg-white rounded-xl p-6 border border-gray-100 text-center">
          <div className="text-3xl mb-2">{yearsExperience}+</div>
          <div className="text-sm text-gray-600">Years Experience</div>
        </div>
        <div className="bg-white rounded-xl p-6 border border-gray-100 text-center">
          <div className="text-3xl mb-2">{reviewCount}</div>
          <div className="text-sm text-gray-600">Happy Customers</div>
        </div>
      </div>

      {/* Badges */}
      {features && (
        <div className="bg-white rounded-2xl p-6 border border-gray-100">
          <h3 className="text-lg mb-4 flex items-center gap-2">
            <Award className="w-5 h-5 text-orange-600" />
            Features
          </h3>
          <RestaurantBadges features={features} showExplicitNegatives={true} />
        </div>
      )}

      {/* Payment Methods - Icon Based */}
      <div className="bg-white rounded-2xl p-6 border border-gray-100">
        <h3 className="text-lg mb-4 flex items-center gap-2">
          <CreditCard className="w-5 h-5 text-blue-600" />
          Payment Methods
        </h3>
        <div className="flex flex-wrap gap-3">
          {paymentMethods.map((method) => (
            <div
              key={method}
              className="px-4 py-2 bg-gray-50 rounded-lg border border-gray-200 text-sm font-medium"
            >
              {method}
            </div>
          ))}
        </div>
      </div>

      {/* Website */}
      {website && (
        <div className="bg-white rounded-2xl p-6 border border-gray-100">
          <h3 className="text-lg mb-4 flex items-center gap-2">
            <Globe className="w-5 h-5 text-green-600" />
            Website
          </h3>
          <a
            href={website}
            target="_blank"
            rel="noopener noreferrer"
            className="text-orange-600 hover:text-orange-700 hover:underline"
          >
            {website}
          </a>
        </div>
      )}

      {/* VAT Number - Collapsed by Default */}
      {vatNumber && (
        <div className="bg-gray-50 rounded-xl border border-gray-200">
          <button
            onClick={() => setShowVat(!showVat)}
            className="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-100 transition rounded-xl"
          >
            <div className="flex items-center gap-2">
              <FileText className="w-5 h-5 text-gray-500" />
              <span className="text-sm font-medium text-gray-700">Business Information</span>
            </div>
            <ChevronDown
              className={`w-5 h-5 text-gray-500 transition-transform ${
                showVat ? 'rotate-180' : ''
              }`}
            />
          </button>
          
          {showVat && (
            <div className="px-6 pb-4 pt-2 border-t border-gray-200">
              <div className="text-sm text-gray-600 mb-1">VAT Number</div>
              <div className="text-sm font-mono bg-white px-3 py-2 rounded border border-gray-200">
                {vatNumber}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Compliance Footer */}
      <div className="text-center text-xs text-gray-500 pt-4">
        <Shield className="w-4 h-4 inline-block mr-1" />
        This restaurant is registered and compliant with Austrian VAT regulations
      </div>
    </div>
  );
}
