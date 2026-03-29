import { useState } from 'react';
import { Globe } from 'lucide-react';
import { useLanguage } from '../contexts/LanguageContext';
import { SUPPORTED_LANGUAGES } from '../utils/translations';

export function LanguageSwitcher() {
  const { language, setLanguage } = useLanguage();
  const [isOpen, setIsOpen] = useState(false);

  const currentLang = SUPPORTED_LANGUAGES.find(l => l.code === language);

  return (
    <div className="relative">
      {/* Compact language button */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors shadow-sm"
      >
        <span className="text-lg">{currentLang?.flag}</span>
        <span className="text-xs uppercase tracking-wide">{currentLang?.code}</span>
        <Globe className="w-3.5 h-3.5 text-gray-400" />
      </button>

      {/* Dropdown menu */}
      {isOpen && (
        <>
          {/* Backdrop */}
          <div 
            className="fixed inset-0 z-40" 
            onClick={() => setIsOpen(false)}
          />
          
          {/* Menu */}
          <div className="absolute top-full mt-2 right-0 bg-white rounded-xl shadow-2xl border border-gray-200 py-2 min-w-[180px] z-50 max-h-[400px] overflow-y-auto">
            {SUPPORTED_LANGUAGES.map((lang) => (
              <button
                key={lang.code}
                onClick={() => {
                  setLanguage(lang.code);
                  setIsOpen(false);
                }}
                className={`
                  w-full px-4 py-2.5 flex items-center gap-3 hover:bg-orange-50 transition-colors text-left
                  ${language === lang.code ? 'bg-orange-50 text-orange-600' : 'text-gray-700'}
                `}
              >
                <span className="text-xl">{lang.flag}</span>
                <div className="flex-1">
                  <div className={`text-sm ${language === lang.code ? '' : ''}`}>
                    {lang.name}
                  </div>
                </div>
                {language === lang.code && (
                  <div className="w-2 h-2 bg-orange-500 rounded-full" />
                )}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
