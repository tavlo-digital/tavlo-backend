import { useState } from 'react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { api } from '../utils/api';
import { getTranslation } from '../utils/translations';
import { getPlatformTranslation } from '../utils/platformTranslations';
import { Mail, Apple } from 'lucide-react';

interface AuthFormProps {
  mode: 'signin' | 'register';
  onSuccess: (customer: any, token?: string) => void;
  onBack: () => void;
  vendorSettings?: any; // Add vendor settings to check requirePhoneNumber
  requireDataConsent?: boolean; // Optional prop to show data consent (only for QR ordering)
  standalone?: boolean; // If true, renders with full-screen wrapper; if false, renders as card only
  language?: string; // Pass language from parent context
}

export function AuthForm({ mode, onSuccess, onBack, vendorSettings, requireDataConsent, standalone = true, language = 'en' }: AuthFormProps) {
  // Create translation function that works with both QR ordering and platform translations
  const t = (key: string, fallback?: string) => {
    // Try getTranslation first (QR ordering), fallback to getPlatformTranslation (discovery/platform)
    const qrTranslation = getTranslation(key, language as any, '');
    if (qrTranslation && qrTranslation !== key) {
      return qrTranslation;
    }
    const platformTranslation = getPlatformTranslation(key, language as any, '');
    if (platformTranslation && platformTranslation !== key) {
      return platformTranslation;
    }
    return fallback || key;
  };
  
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [creatingDemoUsers, setCreatingDemoUsers] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [socialLoading, setSocialLoading] = useState<string | null>(null);
  const [dataConsentAccepted, setDataConsentAccepted] = useState(false);
  
  // Check if phone number is required from vendor settings
  const phoneRequired = vendorSettings?.requirePhoneNumber === true;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setSuccessMessage('');
    
    // Validate phone number if required
    if (mode === 'register' && phoneRequired && !phone.trim()) {
      setError(t('phone_required_error', 'Phone number is required'));
      return;
    }
    
    // Validate data consent for registration
    if (mode === 'register' && requireDataConsent && !dataConsentAccepted) {
      setError(t('data_consent_required', 'You must agree to data collection to create an account'));
      return;
    }
    
    setLoading(true);

    try {
      if (mode === 'register') {
        const result = await api.register(email, password, name, phone);
        
        // Check if email confirmation is required
        if (result.confirmationRequired) {
          setSuccessMessage(
            t('email_confirmation_sent', 
              '✅ Account created! Please check your email to confirm your account before signing in.')
          );
          setLoading(false);
          return;
        }
        
        onSuccess(result.customer);
      } else {
        const result = await api.login(email, password);
        onSuccess(result.customer, result.token);
      }
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleSocialLogin = async (provider: 'google' | 'apple' | 'facebook' | 'github') => {
    setError('');
    setSocialLoading(provider);
    
    try {
      const result = await api.socialLogin(provider);
      
      if (result.url) {
        // Redirect to OAuth provider
        window.location.href = result.url;
      } else if (result.customer) {
        // Already authenticated
        onSuccess(result.customer, result.token);
      }
    } catch (err: any) {
      setError(err.message);
      setSocialLoading(null);
    }
  };

  const handleCreateDemoUsers = async () => {
    setCreatingDemoUsers(true);
    setError('');
    setSuccessMessage('');
    try {
      const result = await api.createDemoUsers();
      setSuccessMessage(`✅ ${result.message || 'Demo users created!'} You can now log in.`);
    } catch (err: any) {
      setError(`Error creating demo users: ${err.message}`);
    } finally {
      setCreatingDemoUsers(false);
    }
  };

  const formContent = (
    <>
      <div>
        <h2 className="text-2xl">{mode === 'register' ? t('create_account', 'Create Account') : t('sign_in', 'Sign In')}</h2>
        <p className="text-sm text-gray-600 mt-1">
          {mode === 'register' 
            ? t('register_to_collect_points', 'Register to collect loyalty points and unlock exclusive rewards')
            : t('welcome_back_sign_in', 'Welcome back! Sign in to continue')
          }
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4">
        {mode === 'register' && (
          <>
            <div className="space-y-2">
              <Label htmlFor="name">{t('full_name_optional', 'Full Name (optional)')}</Label>
              <Input
                id="name"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="John Doe"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="phone">
                {phoneRequired 
                  ? t('phone_required', 'Phone Number *') 
                  : t('phone_optional', 'Phone Number (optional)')}
              </Label>
              <Input
                id="phone"
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="+43 123 456 7890"
                required={phoneRequired}
              />
            </div>
          </>
        )}

        <div className="space-y-2">
          <Label htmlFor="email">{t('email', 'Email')}</Label>
          <Input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="your@email.com"
            required
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="password">{t('password', 'Password')}</Label>
          <Input
            id="password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="••••••••"
            required
            minLength={6}
          />
          {mode === 'register' && (
            <p className="text-xs text-gray-500">{t('minimum_6_characters', 'Minimum 6 characters')}</p>
          )}
        </div>

        {/* Data Collection Consent (only for registration) */}
        {mode === 'register' && requireDataConsent && (
          <div className="space-y-2">
            <label className="flex items-start gap-3 cursor-pointer group">
              <input
                type="checkbox"
                checked={dataConsentAccepted}
                onChange={(e) => setDataConsentAccepted(e.target.checked)}
                className="mt-1 w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500 cursor-pointer"
                required
              />
              <span className="text-sm text-gray-700 leading-snug">
                {t('data_consent_text', 'I agree to allow TAVLO to collect and process my data (name, email, phone number, order history) to provide personalized service, loyalty rewards, and improve the platform experience. You can request deletion of your data at any time.')}
              </span>
            </label>
            <p className="text-xs text-gray-500 ml-7">
              {t('data_consent_note', 'Required to create an account. See our Privacy Policy for more details.')}
            </p>
          </div>
        )}

        {error && (
          <div className="text-sm text-red-600 bg-red-50 p-3 rounded">
            {error}
          </div>
        )}

        {successMessage && (
          <div className="text-sm text-green-600 bg-green-50 p-3 rounded">
            {successMessage}
          </div>
        )}

        {mode === 'signin' && (
          <div className="text-xs bg-blue-50 border border-blue-200 p-3 rounded">
            <div className="text-blue-900 mb-1">🔑 <strong>Demo Accounts:</strong></div>
            <div className="text-blue-700 space-y-1">
              <div>• demo@bellacucina.com / demo123 (350 points)</div>
              <div>• vip@bellacucina.com / vip123 (850 points)</div>
              <div>• test@example.com / test123 (0 points)</div>
            </div>
            <div className="mt-2 pt-2 border-t border-blue-200 text-blue-800 text-xs">
              <strong>⚠️ First time?</strong> Click "Setup Demo Users" below before logging in!
            </div>
          </div>
        )}

        <Button type="submit" className="w-full" disabled={loading}>
          {loading ? 'Loading...' : mode === 'register' ? t('register', 'Register') : t('sign_in', 'Sign In')}
        </Button>
      </form>

      {/* Social Login Buttons */}
      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-gray-300"></div>
        </div>
        <div className="relative flex justify-center text-sm">
          <span className="px-2 bg-white text-gray-500">
            {t('or_continue_with', 'Or continue with')}
          </span>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3">
        {/* Google */}
        <Button
          type="button"
          variant="outline"
          onClick={() => handleSocialLogin('google')}
          disabled={socialLoading !== null}
          className="w-full"
        >
          {socialLoading === 'google' ? (
            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-900"></div>
          ) : (
            <>
              <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24">
                <path
                  fill="#4285F4"
                  d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                />
                <path
                  fill="#34A853"
                  d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                />
                <path
                  fill="#FBBC05"
                  d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                />
                <path
                  fill="#EA4335"
                  d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                />
              </svg>
              Google
            </>
          )}
        </Button>

        {/* Apple */}
        <Button
          type="button"
          variant="outline"
          onClick={() => handleSocialLogin('apple')}
          disabled={socialLoading !== null}
          className="w-full"
        >
          {socialLoading === 'apple' ? (
            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-900"></div>
          ) : (
            <>
              <Apple className="w-5 h-5 mr-2" />
              Apple
            </>
          )}
        </Button>

        {/* Facebook */}
        <Button
          type="button"
          variant="outline"
          onClick={() => handleSocialLogin('facebook')}
          disabled={socialLoading !== null}
          className="w-full"
        >
          {socialLoading === 'facebook' ? (
            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-900"></div>
          ) : (
            <>
              <svg className="w-5 h-5 mr-2" fill="#1877F2" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              Facebook
            </>
          )}
        </Button>

        {/* GitHub */}
        <Button
          type="button"
          variant="outline"
          onClick={() => handleSocialLogin('github')}
          disabled={socialLoading !== null}
          className="w-full"
        >
          {socialLoading === 'github' ? (
            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-900"></div>
          ) : (
            <>
              <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path fillRule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clipRule="evenodd"/>
              </svg>
              GitHub
            </>
          )}
        </Button>
      </div>

      <button
        onClick={onBack}
        className="text-sm text-gray-600 hover:underline w-full text-center"
      >
        {t('back', 'Back')}
      </button>

      {mode === 'signin' && (
        <div className="pt-4 border-t">
          <p className="text-xs text-gray-600 mb-2 text-center">
            <strong>⚠️ First time here?</strong> Click this button to create demo users:
          </p>
          <Button
            onClick={handleCreateDemoUsers}
            variant="outline"
            className="w-full border-orange-300 hover:bg-orange-50"
            disabled={creatingDemoUsers}
          >
            {creatingDemoUsers ? '⏳ Creating demo users...' : '🔧 Setup Demo Users (Click First!)'}
          </Button>
        </div>
      )}
    </>
  );

  return standalone ? (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full bg-white rounded-lg shadow-lg p-6 space-y-6">
        {formContent}
      </div>
    </div>
  ) : (
    <div className="space-y-6">
      {formContent}
    </div>
  );
}
