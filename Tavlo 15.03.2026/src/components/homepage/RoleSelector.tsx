import { Users, Utensils, ArrowRight } from 'lucide-react';
import { usePlatformLanguage } from '../../contexts/PlatformLanguageContext';
import { getPlatformTranslation } from '../../utils/platformTranslations';

interface RoleSelectorProps {
  onCustomerClick: () => void;
  onRestaurantClick: () => void;
}

export function RoleSelector({ onCustomerClick, onRestaurantClick }: RoleSelectorProps) {
  const { language } = usePlatformLanguage();

  return (
    <section className="py-16 bg-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Section Header */}
        <div className="text-center mb-12">
          <h2 className="text-3xl font-bold text-gray-900 mb-4">
            {language === 'de' ? 'Wählen Sie Ihre Rolle' : language === 'ar' ? 'اختر دورك' : 'Choose Your Path'}
          </h2>
          <p className="text-lg text-gray-600 max-w-2xl mx-auto">
            {language === 'de' 
              ? 'Entdecken Sie, wie TAVLO Ihr kulinarisches Erlebnis verbessert'
              : language === 'ar'
              ? 'اكتشف كيف يعزز TAVLO تجربتك في تناول الطعام'
              : 'Discover how TAVLO enhances your dining experience'
            }
          </p>
        </div>

        {/* Cards */}
        <div className="grid md:grid-cols-2 gap-8 max-w-5xl mx-auto">
          {/* Customer Card */}
          <button
            onClick={onCustomerClick}
            className="group relative bg-gradient-to-br from-emerald-50 to-white border-2 border-emerald-100 rounded-2xl p-8 hover:border-emerald-500 hover:shadow-xl transition-all duration-300 text-left overflow-hidden"
          >
            {/* Decorative Background */}
            <div className="absolute top-0 right-0 w-40 h-40 bg-emerald-100 rounded-full blur-3xl opacity-0 group-hover:opacity-50 transition-opacity duration-500" />
            
            <div className="relative">
              {/* Icon */}
              <div className="w-16 h-16 bg-emerald-500 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                <Users className="w-8 h-8 text-white" />
              </div>

              {/* Content */}
              <h3 className="text-2xl font-bold text-gray-900 mb-3">
                {language === 'de' ? 'Ich bin ein Kunde' : language === 'ar' ? 'أنا عميل' : "I'm a Customer"}
              </h3>
              <p className="text-gray-600 mb-6 leading-relaxed">
                {language === 'de'
                  ? 'Entdecken Sie Restaurants, bestellen Sie nahtlos und genießen Sie Ihr Essen mit Freunden.'
                  : language === 'ar'
                  ? 'اكتشف المطاعم، اطلب بسلاسة، واستمتع بتناول الطعام مع الأصدقاء.'
                  : 'Discover restaurants, order seamlessly, and enjoy dining with friends.'
                }
              </p>

              {/* Features */}
              <ul className="space-y-2 mb-6">
                <li className="flex items-center text-sm text-gray-700">
                  <div className="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-3" />
                  {language === 'de' ? 'QR-Code scannen & bestellen' : language === 'ar' ? 'مسح QR والطلب' : 'Scan QR & order'}
                </li>
                <li className="flex items-center text-sm text-gray-700">
                  <div className="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-3" />
                  {language === 'de' ? 'Geteilte Rechnung' : language === 'ar' ? 'تقسيم الفاتورة' : 'Split bill easily'}
                </li>
                <li className="flex items-center text-sm text-gray-700">
                  <div className="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-3" />
                  {language === 'de' ? 'Treuepunkte sammeln' : language === 'ar' ? 'كسب نقاط الولاء' : 'Earn loyalty points'}
                </li>
              </ul>

              {/* CTA */}
              <div className="flex items-center text-emerald-600 font-semibold group-hover:text-emerald-700">
                {language === 'de' ? 'Mehr erfahren' : language === 'ar' ? 'اعرف المزيد' : 'Learn more'}
                <ArrowRight className="w-5 h-5 ml-2 group-hover:translate-x-2 transition-transform duration-300" />
              </div>
            </div>
          </button>

          {/* Restaurant Card */}
          <button
            onClick={onRestaurantClick}
            className="group relative bg-gradient-to-br from-blue-50 to-white border-2 border-blue-100 rounded-2xl p-8 hover:border-blue-500 hover:shadow-xl transition-all duration-300 text-left overflow-hidden"
          >
            {/* Decorative Background */}
            <div className="absolute top-0 right-0 w-40 h-40 bg-blue-100 rounded-full blur-3xl opacity-0 group-hover:opacity-50 transition-opacity duration-500" />
            
            <div className="relative">
              {/* Icon */}
              <div className="w-16 h-16 bg-blue-500 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                <Utensils className="w-8 h-8 text-white" />
              </div>

              {/* Content */}
              <h3 className="text-2xl font-bold text-gray-900 mb-3">
                {language === 'de' ? 'Ich bin ein Restaurant' : language === 'ar' ? 'أنا مطعم' : 'I\'m a Restaurant'}
              </h3>
              <p className="text-gray-600 mb-6 leading-relaxed">
                {language === 'de'
                  ? 'Digitalisieren Sie Ihr Restaurant mit QR-Bestellungen, Analysen und Treueprogrammen.'
                  : language === 'ar'
                  ? 'قم برقمنة مطعمك بطلبات QR والتحليلات وبرامج الولاء.'
                  : 'Digitize your restaurant with QR ordering, analytics, and loyalty programs.'
                }
              </p>

              {/* Features */}
              <ul className="space-y-2 mb-6">
                <li className="flex items-center text-sm text-gray-700">
                  <div className="w-1.5 h-1.5 bg-blue-500 rounded-full mr-3" />
                  {language === 'de' ? 'QR-Menüs & Bestellungen' : language === 'ar' ? 'قوائم QR والطلبات' : 'QR menus & orders'}
                </li>
                <li className="flex items-center text-sm text-gray-700">
                  <div className="w-1.5 h-1.5 bg-blue-500 rounded-full mr-3" />
                  {language === 'de' ? 'Echtzeit-Analysen' : language === 'ar' ? 'التحليلات في الوقت الفعلي' : 'Real-time analytics'}
                </li>
                <li className="flex items-center text-sm text-gray-700">
                  <div className="w-1.5 h-1.5 bg-blue-500 rounded-full mr-3" />
                  {language === 'de' ? 'Kundentreue-Tools' : language === 'ar' ? 'أدوات ولاء العملاء' : 'Customer loyalty tools'}
                </li>
              </ul>

              {/* CTA */}
              <div className="flex items-center text-blue-600 font-semibold group-hover:text-blue-700">
                {language === 'de' ? 'Mehr erfahren' : language === 'ar' ? 'اعرف المزيد' : 'Learn more'}
                <ArrowRight className="w-5 h-5 ml-2 group-hover:translate-x-2 transition-transform duration-300" />
              </div>
            </div>
          </button>
        </div>
      </div>
    </section>
  );
}