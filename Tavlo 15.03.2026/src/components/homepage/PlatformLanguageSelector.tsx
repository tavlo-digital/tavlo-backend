import { usePlatformLanguage, PlatformLanguage } from '../../contexts/PlatformLanguageContext';
import { Globe } from 'lucide-react';

const LANGUAGES: { code: PlatformLanguage; name: string; flag: string }[] = [
  { code: 'en', name: 'English', flag: '🇬🇧' },
  { code: 'de', name: 'Deutsch', flag: '🇩🇪' },
  { code: 'ar', name: 'العربية', flag: '🇸🇦' },
];

export function PlatformLanguageSelector() {
  const { language, setLanguage } = usePlatformLanguage();

  return (
    <div className="relative inline-block z-50">
      <select
        value={language}
        onChange={(e) => setLanguage(e.target.value as PlatformLanguage)}
        className="appearance-none bg-white border border-gray-200 rounded-lg px-3 py-2 pr-8 text-sm hover:border-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent cursor-pointer transition-colors relative z-50"
        style={{ direction: 'ltr' }} // Keep selector LTR even in RTL mode
      >
        {LANGUAGES.map((lang) => (
          <option key={lang.code} value={lang.code}>
            {lang.flag} {lang.name}
          </option>
        ))}
      </select>
      <Globe className="absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none z-0" />
    </div>
  );
}