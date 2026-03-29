import { X } from 'lucide-react';
import { AuthForm } from '../AuthForm';
import { usePlatformLanguage } from '../../contexts/PlatformLanguageContext';

interface AuthModalProps {
  isOpen: boolean;
  onClose: () => void;
  mode: 'login' | 'register';
  onSuccess: (user: any) => void;
  vendorSettings?: any;
  requireDataConsent?: boolean;
}

export function AuthModal({ 
  isOpen, 
  onClose, 
  mode, 
  onSuccess, 
  vendorSettings,
  requireDataConsent = false 
}: AuthModalProps) {
  const { language } = usePlatformLanguage();
  
  if (!isOpen) return null;

  // Convert 'login' to 'signin' for AuthForm compatibility
  const authFormMode = mode === 'login' ? 'signin' : 'register';

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl max-w-md w-full relative max-h-[90vh] overflow-y-auto">
        {/* Close Button */}
        <button
          onClick={onClose}
          className="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors z-10"
          aria-label="Close"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Use unified AuthForm */}
        <div className="p-6 sm:p-8">
          <AuthForm
            mode={authFormMode}
            onSuccess={onSuccess}
            onBack={onClose}
            vendorSettings={vendorSettings}
            requireDataConsent={requireDataConsent}
            standalone={false}
            language={language}
          />
        </div>
      </div>
    </div>
  );
}