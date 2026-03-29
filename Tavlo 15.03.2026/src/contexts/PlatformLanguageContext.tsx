import { createContext, useContext, useState, ReactNode, useEffect } from 'react';

export type PlatformLanguage = 'en' | 'de' | 'ar';

interface PlatformLanguageContextType {
  language: PlatformLanguage;
  setLanguage: (lang: PlatformLanguage) => void;
}

const PlatformLanguageContext = createContext<PlatformLanguageContextType | undefined>(undefined);

export function PlatformLanguageProvider({ children }: { children: ReactNode }) {
  const [language, setLanguage] = useState<PlatformLanguage>('en');

  // Update document direction when language changes
  useEffect(() => {
    if (language === 'ar') {
      document.documentElement.dir = 'rtl';
      document.documentElement.lang = 'ar';
    } else {
      document.documentElement.dir = 'ltr';
      document.documentElement.lang = language;
    }
  }, [language]);

  return (
    <PlatformLanguageContext.Provider value={{ language, setLanguage }}>
      {children}
    </PlatformLanguageContext.Provider>
  );
}

export function usePlatformLanguage() {
  const context = useContext(PlatformLanguageContext);
  if (!context) {
    throw new Error('usePlatformLanguage must be used within PlatformLanguageProvider');
  }
  return context;
}
