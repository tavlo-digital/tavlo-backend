import { AlertCircle } from 'lucide-react';

export type AdminLanguage = {
    code: string;
    name: string;
    nativeName?: string | null;
    flag?: string | null;
    direction?: 'ltr' | 'rtl';
};

export function languageDirection(language?: AdminLanguage): 'ltr' | 'rtl' {
    return language?.direction ?? 'ltr';
}

export default function AdminLanguageTabs({
    languages,
    activeLanguage,
    onLanguageChange,
    values,
}: {
    languages: AdminLanguage[];
    activeLanguage: string;
    onLanguageChange: (language: string) => void;
    values: Record<string, string | undefined>;
}) {
    return (
        <div className="flex h-auto w-full flex-wrap justify-start gap-1 rounded-lg bg-gray-100 p-1">
            {languages.map((language) => {
                const missing = !values[language.code]?.trim();

                return (
                    <button
                        key={language.code}
                        type="button"
                        onClick={() => onLanguageChange(language.code)}
                        aria-pressed={activeLanguage === language.code}
                        title={language.name}
                        className={`inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                            activeLanguage === language.code
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-600 hover:bg-white/70 hover:text-gray-900'
                        }`}
                    >
                        <span aria-hidden="true">{language.flag ?? '🌐'}</span>
                        <span>{language.code.toUpperCase()}</span>
                        {missing && (
                            <AlertCircle
                                className="h-3 w-3 text-amber-500"
                                aria-label="Translation missing"
                            />
                        )}
                    </button>
                );
            })}
        </div>
    );
}
