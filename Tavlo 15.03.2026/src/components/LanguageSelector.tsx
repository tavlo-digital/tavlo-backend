import { useState } from 'react';
import { Button } from './ui/button';
import { Check } from 'lucide-react';

interface LanguageSelectorProps {
  onSelect: (language: string) => void;
}

const languages = [
  { code: 'en', name: 'English' },
  { code: 'de', name: 'Deutsch' },
  { code: 'it', name: 'Italiano' },
  { code: 'ar', name: 'العربية' },
  { code: 'zh', name: '中文' },
  { code: 'tr', name: 'Türkçe' }
];

export function LanguageSelector({ onSelect }: LanguageSelectorProps) {
  const [selected, setSelected] = useState('en');

  const handleSave = () => {
    onSelect(selected);
  };

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-6 space-y-6">
        <h2 className="text-2xl">Choose language</h2>

        <div className="space-y-2">
          {languages.map((lang) => (
            <button
              key={lang.code}
              onClick={() => setSelected(lang.code)}
              className={`w-full p-4 rounded-lg border-2 transition-colors flex items-center justify-between ${
                selected === lang.code
                  ? 'border-orange-500 bg-orange-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <span className="text-lg">{lang.name}</span>
              {selected === lang.code && (
                <Check className="w-5 h-5 text-orange-500" />
              )}
            </button>
          ))}
        </div>

        <Button onClick={handleSave} className="w-full">
          Save & Continue
        </Button>
      </div>
    </div>
  );
}
