import image_a41c5ed9cb37da642e00d6ad4d3424dd23759fa0 from 'figma:asset/a41c5ed9cb37da642e00d6ad4d3424dd23759fa0.png';
import { Search, QrCode, Store, ArrowRight } from 'lucide-react';
import { Button } from '../ui/button';
import { usePlatformLanguage } from '../../contexts/PlatformLanguageContext';
import { getPlatformTranslation } from '../../utils/platformTranslations';
import tavloLogo from 'figma:asset/d442f812b641089c191ab222c1e3bb84e36bdccf.png';

interface HeroSectionProps {
  onSearchFocus: () => void;
  onScanQR: () => void;
  onForRestaurants: () => void;
}

export function HeroSection({ onSearchFocus, onScanQR, onForRestaurants }: HeroSectionProps) {
  const { language } = usePlatformLanguage();

  return (
    <div className="relative bg-gradient-to-b from-white via-emerald-50/30 to-white overflow-hidden">
      {/* Background Pattern */}
      <div className="absolute inset-0 opacity-[0.03]">
        <div className="absolute inset-0" style={{
          backgroundImage: `radial-gradient(circle at 2px 2px, #0F5257 1px, transparent 0)`,
          backgroundSize: '48px 48px'
        }}></div>
      </div>

      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 pt-16 pb-24">
        {/* Logo */}
        <div className="flex justify-center mb-6">
          <img src={image_a41c5ed9cb37da642e00d6ad4d3424dd23759fa0} alt="TAVLO" className="h-40 w-auto" />
        </div>

        {/* Hero Content */}
        <div className="text-center max-w-4xl mx-auto">
          <h1 className="text-5xl sm:text-6xl lg:text-7xl mb-6 text-gray-900">
            {getPlatformTranslation('your_restaurant_digitally_connected', language).split(', ')[0]},
            <br />
            <span className="bg-gradient-to-r from-emerald-600 to-teal-600 bg-clip-text text-transparent">
              {getPlatformTranslation('your_restaurant_digitally_connected', language).split(', ')[1]}
            </span>
          </h1>
          
          <p className="text-xl sm:text-2xl text-gray-600 mb-12 max-w-3xl mx-auto leading-relaxed">
            {getPlatformTranslation('discover_restaurants_order_seamlessly', language)}
            <br />
            <span className="text-lg text-gray-500">
              {getPlatformTranslation('in_12_languages_qr_powered', language)}
            </span>
          </p>

          {/* Primary Actions */}
          <div className="flex flex-col sm:flex-row gap-4 justify-center items-center mb-16">
            {/* Search Restaurants */}
            <button
              onClick={onSearchFocus}
              className="group w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-4 rounded-xl transition-all shadow-lg hover:shadow-xl flex items-center gap-3 justify-center"
            >
              <Search className="w-5 h-5" />
              <span className="text-lg">{getPlatformTranslation('find_restaurants', language)}</span>
              <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
            </button>

            {/* Scan QR */}
            <button
              onClick={onScanQR}
              className="group w-full sm:w-auto bg-white hover:bg-gray-50 text-gray-900 px-8 py-4 rounded-xl transition-all border-2 border-gray-200 hover:border-emerald-300 flex items-center gap-3 justify-center shadow-md"
            >
              <QrCode className="w-5 h-5 text-emerald-600" />
              <span className="text-lg">{getPlatformTranslation('scan_qr_code', language)}</span>
            </button>
          </div>

          {/* Stats Bar */}
          <div className="grid grid-cols-3 gap-6 sm:gap-12 max-w-2xl mx-auto py-8 border-t border-b border-gray-200">
            <div>
              <div className="text-3xl sm:text-4xl text-emerald-600 mb-1">12</div>
              <div className="text-sm text-gray-600">{getPlatformTranslation('languages', language)}</div>
            </div>
            <div>
              <div className="text-3xl sm:text-4xl text-emerald-600 mb-1">500+</div>
              <div className="text-sm text-gray-600">{getPlatformTranslation('restaurants', language)}</div>
            </div>
            <div>
              <div className="text-3xl sm:text-4xl text-emerald-600 mb-1">50K+</div>
              <div className="text-sm text-gray-600">{getPlatformTranslation('orders', language)}</div>
            </div>
          </div>
        </div>
      </div>

      {/* For Restaurants Link */}
      <div className="text-center pb-12">
        <button
          onClick={onForRestaurants}
          className="text-gray-600 hover:text-emerald-700 transition-colors inline-flex items-center gap-2 group"
        >
          <Store className="w-4 h-4" />
          <span>{getPlatformTranslation('are_you_restaurant_owner', language)}</span>
          <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
        </button>
      </div>
    </div>
  );
}