import { AuthForm } from './AuthForm';
import { useLanguage } from '../contexts/LanguageContext';

interface AuthScreenProps {
  mode: 'signin' | 'register';
  onSuccess: (user: any) => void;
  onBack: () => void;
  vendorSettings?: any;
  requireDataConsent?: boolean; // Pass through to AuthForm
}

export function AuthScreen({ mode, onSuccess, onBack, vendorSettings, requireDataConsent }: AuthScreenProps) {
  const { language } = useLanguage();
  
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <AuthForm 
        mode={mode}
        onSuccess={onSuccess}
        onBack={onBack}
        vendorSettings={vendorSettings}
        requireDataConsent={requireDataConsent}
        language={language}
        standalone={true}
      />
    </div>
  );
}