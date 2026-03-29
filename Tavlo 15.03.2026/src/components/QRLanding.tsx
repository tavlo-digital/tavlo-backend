import { useState, useRef, useEffect } from 'react';
import { Star, Globe, Bell, AlertTriangle, Copy, Check, Lock, CheckCircle } from 'lucide-react';
import { SUPPORTED_LANGUAGES, LanguageCode, getTranslation } from '../utils/translations';
import { useAccessibility } from '../contexts/AccessibilityContext';
import { Button } from './ui/button';
import { AccessibilityMenu } from './AccessibilityMenu';

// Session state types
export type SessionState = 
  | 'empty'                // Initial scan - no session
  | 'pin-display'          // First person - show PIN after session created
  | 'joinable'             // Second person - enter PIN to join Draft
  | 'abandoned-draft'      // Draft with no orders
  | 'cash-pending'         // Cash payment pending
  | 'closed-recent'        // Recently closed
  | 'security-block';      // Too many scans

interface SessionData {
  sessionId?: string;
  pin?: string;
  status?: 'Draft' | 'Active' | 'Closed Cash' | 'Closed Online';
  hasCashBalance?: boolean;
  hasOrders?: boolean;
  lastOrderNumber?: string;
  lastOrderAmount?: number;
  lastOrderStatus?: string;
  scansInLastHour?: number;
  inactivityWarning?: boolean;
  timeoutMinutesRemaining?: number;
}

interface QRLandingProps {
  restaurantName: string;
  tableNumber: string;
  cuisineTag: string;
  restaurantInfo?: {
    address?: string;
    phone?: string;
    logo?: string;
    coverPhoto?: string;
    businessHours?: any;
    rating?: number;
    description?: string;
  };
  vendorSettings?: any;
  // Legacy prop for backward compatibility
  onContinue?: (data: { numPeople: number; sharedBasket: boolean; authChoice: 'guest' | 'signin' | 'register' | null; language: LanguageCode }) => void;
  // New state-aware props
  sessionState?: SessionState;
  sessionData?: SessionData;
  previousOrders?: any[];
  onStartNewSession?: (data: { authChoice: 'guest' | 'signin' | 'register' | null; language: LanguageCode }) => void;
  onJoinSession?: (pin: string) => void;
  onDeleteDraftAndStartNew?: (data: { authChoice: 'guest' | 'signin' | 'register' | null; language: LanguageCode }) => void;
  onCallWaiter?: () => void;
  onViewPreviousOrders?: () => void;
  onViewOrderDetails?: (orderId: string) => void;
  onContinueToMenu?: () => void; // Navigate from PIN display to menu
}

export function QRLanding({ 
  restaurantName, 
  tableNumber, 
  cuisineTag, 
  restaurantInfo, 
  vendorSettings,
  onContinue,
  sessionState = 'empty',
  sessionData,
  previousOrders,
  onStartNewSession,
  onJoinSession,
  onDeleteDraftAndStartNew,
  onCallWaiter,
  onViewPreviousOrders,
  onViewOrderDetails,
  onContinueToMenu
}: QRLandingProps) {
  const { settings: accessibilitySettings } = useAccessibility();
  const [selectedLanguage, setSelectedLanguage] = useState<LanguageCode>('en');
  const [isLangDropdownOpen, setIsLangDropdownOpen] = useState(false);
  const [showAccessibilityMenu, setShowAccessibilityMenu] = useState(false);
  const [showAuthModal, setShowAuthModal] = useState(false);
  const [showDeleteDraftModal, setShowDeleteDraftModal] = useState(false);
  const [pinCopied, setPinCopied] = useState(false);
  
  // PIN input state
  const [pin, setPin] = useState(['', '', '', '']);
  const [pinError, setPinError] = useState('');
  const [shakeError, setShakeError] = useState(false);
  const pinInputRefs = [
    useRef<HTMLInputElement>(null),
    useRef<HTMLInputElement>(null),
    useRef<HTMLInputElement>(null),
    useRef<HTMLInputElement>(null)
  ];

  // Theme colors - warm, elegant restaurant palette
  const themeColors = {
    primary: vendorSettings?.primaryColor || '#2d3a2d', // Dark olive
    accent: vendorSettings?.accentColor || '#d4a574', // Warm gold
    charcoal: '#1a1a1a',
    warmBeige: '#f8f6f0',
    darkOverlay: 'rgba(0, 0, 0, 0.65)'
  };

  const t = (key: string, fallback?: string) => getTranslation(key, selectedLanguage, fallback);
  const currentLang = SUPPORTED_LANGUAGES.find(l => l.code === selectedLanguage);
  const defaultCover = 'https://images.unsplash.com/photo-1722587561829-8a53e1935e20?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpdGFsaWFuJTIwcmVzdGF1cmFudCUyMGludGVyaW9yfGVufDF8fHx8MTc2NDQzMjUyN3ww&ixlib=rb-4.1.0&q=80&w=1080';

  // Wrap handlers for backward compatibility
  const handleStartNewSessionInternal = onStartNewSession || ((data) => {
    onContinue?.({ numPeople: 2, sharedBasket: true, authChoice: data.authChoice, language: data.language });
  });
  const handleJoinSessionInternal = onJoinSession || (() => {});
  const handleDeleteDraftAndStartNewInternal = onDeleteDraftAndStartNew || ((data) => {
    onContinue?.({ numPeople: 2, sharedBasket: true, authChoice: data.authChoice, language: data.language });
  });
  const handleCallWaiterInternal = onCallWaiter || (() => console.log('Call waiter'));
  const handleContinueToMenuInternal = onContinueToMenu || (() => console.log('Continue to menu'));

  // Copy PIN to clipboard
  const handleCopyPin = () => {
    if (sessionData?.pin) {
      // Try modern Clipboard API first
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(sessionData.pin)
          .then(() => {
            setPinCopied(true);
            setTimeout(() => setPinCopied(false), 2000);
          })
          .catch((err) => {
            console.log('Clipboard API failed, using fallback:', err);
            fallbackCopyTextToClipboard(sessionData.pin);
          });
      } else {
        // Fallback for older browsers or restricted contexts
        fallbackCopyTextToClipboard(sessionData.pin);
      }
    }
  };

  // Fallback copy method using deprecated execCommand
  const fallbackCopyTextToClipboard = (text: string) => {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.width = '2em';
    textArea.style.height = '2em';
    textArea.style.padding = '0';
    textArea.style.border = 'none';
    textArea.style.outline = 'none';
    textArea.style.boxShadow = 'none';
    textArea.style.background = 'transparent';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      const successful = document.execCommand('copy');
      if (successful) {
        setPinCopied(true);
        setTimeout(() => setPinCopied(false), 2000);
      } else {
        console.warn('Fallback copy failed');
      }
    } catch (err) {
      console.error('Fallback copy error:', err);
    }

    document.body.removeChild(textArea);
  };

  // Handle PIN input
  const handlePinInput = (index: number, value: string) => {
    if (!/^\d*$/.test(value)) return;
    
    const newPin = [...pin];
    newPin[index] = value.slice(-1);
    setPin(newPin);
    setPinError('');
    setShakeError(false);

    // Auto-focus next input
    if (value && index < 3) {
      pinInputRefs[index + 1].current?.focus();
    }

    // Auto-submit when all 4 digits entered
    if (index === 3 && value && newPin.every(d => d)) {
      handleJoinSession(newPin.join(''));
    }
  };

  const handlePinKeyDown = (index: number, e: React.KeyboardEvent) => {
    if (e.key === 'Backspace' && !pin[index] && index > 0) {
      pinInputRefs[index - 1].current?.focus();
    }
  };

  const handleJoinSession = (pinCode: string) => {
    if (pinCode.length !== 4) {
      setPinError('Please enter all 4 digits');
      setShakeError(true);
      setTimeout(() => setShakeError(false), 500);
      return;
    }
    handleJoinSessionInternal(pinCode);
  };

  const handleStartOrder = (choice: 'guest' | 'signin' | 'register') => {
    handleStartNewSessionInternal({ authChoice: choice, language: selectedLanguage });
    setShowAuthModal(false);
  };

  const handleDeleteAndStart = (choice: 'guest' | 'signin' | 'register') => {
    handleDeleteDraftAndStartNewInternal({ authChoice: choice, language: selectedLanguage });
    setShowDeleteDraftModal(false);
  };

  // Render immersive hero
  const renderHero = () => (
    <div className="relative h-[45vh] min-h-[340px] overflow-hidden">
      {/* Cover Image */}
      <div 
        className="absolute inset-0 bg-cover bg-center"
        style={{ backgroundImage: `url(${restaurantInfo?.coverPhoto || defaultCover})` }}
      />
      
      {/* Dark Gradient Overlay */}
      <div 
        className="absolute inset-0" 
        style={{ background: `linear-gradient(to bottom, ${themeColors.darkOverlay} 0%, rgba(0,0,0,0.5) 50%, rgba(0,0,0,0.7) 100%)` }}
      />
      
      {/* Content Overlay */}
      <div className="relative h-full flex flex-col justify-between p-5 text-white">
        {/* Top: Language Selector */}
        <div className="flex justify-between items-start">
          <div className="relative">
            <button
              onClick={() => setIsLangDropdownOpen(!isLangDropdownOpen)}
              className="flex items-center gap-2 px-3 py-2 bg-white/15 backdrop-blur-md rounded-xl hover:bg-white/25 transition-all border border-white/20"
            >
              <Globe className="w-4 h-4" />
              <span className="text-sm font-medium">{currentLang?.flag} {currentLang?.code.toUpperCase()}</span>
            </button>

            {isLangDropdownOpen && (
              <>
                <div className="fixed inset-0 z-40" onClick={() => setIsLangDropdownOpen(false)} />
                <div className="absolute top-full mt-2 left-0 bg-white rounded-2xl shadow-2xl border border-gray-200 py-2 z-50 min-w-[200px]">
                  {SUPPORTED_LANGUAGES.map((lang) => (
                    <button
                      key={lang.code}
                      onClick={() => {
                        setSelectedLanguage(lang.code);
                        setIsLangDropdownOpen(false);
                      }}
                      className={`w-full px-4 py-3 flex items-center gap-3 transition-colors text-left ${
                        selectedLanguage === lang.code ? 'bg-gray-100' : 'hover:bg-gray-50'
                      }`}
                    >
                      <span className="text-xl">{lang.flag}</span>
                      <span className="text-sm text-gray-900 flex-1 font-medium">{lang.name}</span>
                      {selectedLanguage === lang.code && (
                        <div className="w-2 h-2 rounded-full" style={{ backgroundColor: themeColors.accent }} />
                      )}
                    </button>
                  ))}
                </div>
              </>
            )}
          </div>
        </div>

        {/* Bottom: Restaurant Info */}
        <div className="space-y-4">
          <div>
            <h1 className="text-4xl font-bold mb-2 drop-shadow-lg tracking-tight">{restaurantName}</h1>
            <div className="flex items-center gap-2 text-sm text-white/90">
              {restaurantInfo?.rating && (
                <>
                  <div className="flex items-center gap-1 bg-white/15 backdrop-blur-sm px-2 py-1 rounded-lg">
                    <Star className="w-4 h-4 fill-yellow-400 text-yellow-400" />
                    <span className="font-semibold">{restaurantInfo.rating.toFixed(1)}</span>
                  </div>
                  <span className="text-white/50">•</span>
                </>
              )}
              <span className="font-medium">{cuisineTag}</span>
            </div>
          </div>

          {/* Table Badge */}
          <div 
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-full backdrop-blur-md border-2"
            style={{ 
              backgroundColor: `${themeColors.accent}25`,
              borderColor: `${themeColors.accent}80`
            }}
          >
            <div className="w-2 h-2 rounded-full animate-pulse" style={{ backgroundColor: themeColors.accent }} />
            <span className="text-base font-bold">Table {tableNumber}</span>
          </div>
        </div>
      </div>
    </div>
  );

  // STATE A: PIN DISPLAY - First person created session
  const renderPinDisplayState = () => (
    <div className="flex-1 px-5 py-8 flex flex-col justify-center animate-in fade-in slide-in-from-bottom duration-500">
      <div className="max-w-md mx-auto w-full space-y-6">
        {/* Main Card */}
        <div 
          className="bg-white rounded-[32px] shadow-2xl p-8 text-center"
          style={{ borderTop: `5px solid ${themeColors.accent}` }}
        >
          <div className="mb-6">
            <div className="text-5xl mb-4">🍽️</div>
            <h2 className="text-3xl font-bold mb-2" style={{ color: themeColors.charcoal }}>
              You're all set to order
            </h2>
            <p className="text-gray-600 text-base leading-relaxed">
              Share this table with others using the PIN below
            </p>
          </div>

          {/* PIN DISPLAY - CRITICAL */}
          <div className="mb-8">
            <p className="text-xs uppercase tracking-wider font-semibold text-gray-500 mb-4">
              Your Table PIN
            </p>
            
            {/* Large PIN Container */}
            <div className="flex justify-center gap-3 mb-4">
              {sessionData?.pin?.split('').map((digit, index) => (
                <div
                  key={index}
                  className="w-16 h-20 rounded-2xl flex items-center justify-center text-4xl font-bold shadow-lg animate-in zoom-in"
                  style={{ 
                    backgroundColor: themeColors.accent,
                    color: 'white',
                    animationDelay: `${index * 100}ms`
                  }}
                >
                  {digit}
                </div>
              )) || (
                // Fallback if no PIN
                ['•', '•', '•', '•'].map((char, index) => (
                  <div
                    key={index}
                    className="w-16 h-20 rounded-2xl flex items-center justify-center text-4xl font-bold shadow-lg"
                    style={{ backgroundColor: '#e5e7eb', color: '#9ca3af' }}
                  >
                    {char}
                  </div>
                ))
              )}
            </div>

            {/* Copy Button */}
            <button
              onClick={handleCopyPin}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors text-sm font-medium text-gray-700"
            >
              {pinCopied ? (
                <>
                  <Check className="w-4 h-4 text-green-600" />
                  <span className="text-green-600">Copied!</span>
                </>
              ) : (
                <>
                  <Copy className="w-4 h-4" />
                  <span>Copy PIN</span>
                </>
              )}
            </button>
          </div>

          {/* Helper Text */}
          <div className="bg-blue-50 rounded-2xl p-4 mb-6">
            <p className="text-sm text-blue-900 leading-relaxed">
              💡 Anyone at your table can scan the QR and enter this PIN to join
            </p>
          </div>

          {/* Primary Action */}
          <Button
            onClick={handleContinueToMenuInternal}
            size="lg"
            className="w-full h-14 text-lg rounded-2xl font-bold shadow-lg"
            style={{ backgroundColor: themeColors.primary, color: 'white' }}
          >
            Start Ordering
          </Button>
        </div>

        {/* Secondary Note */}
        <div className="text-center px-4">
          <p className="text-sm text-gray-500 leading-relaxed">
            You can start ordering now. Others can join anytime.
          </p>
        </div>
      </div>
    </div>
  );

  // STATE B: PIN INPUT - Second person joining Draft session
  const renderJoinableState = () => (
    <div className="flex-1 px-5 py-8 flex flex-col justify-center animate-in fade-in slide-in-from-bottom duration-500">
      <div className="max-w-md mx-auto w-full space-y-6">
        {/* Main Card */}
        <div 
          className="bg-white rounded-[32px] shadow-2xl p-8"
          style={{ borderTop: `5px solid ${themeColors.accent}` }}
        >
          <div className="text-center mb-8">
            <div 
              className="w-20 h-20 rounded-full mx-auto mb-5 flex items-center justify-center"
              style={{ backgroundColor: `${themeColors.primary}15` }}
            >
              <Lock className="w-10 h-10" style={{ color: themeColors.primary }} />
            </div>
            <h2 className="text-3xl font-bold mb-3" style={{ color: themeColors.charcoal }}>
              Someone already started ordering
            </h2>
            <p className="text-gray-600 text-base leading-relaxed">
              Enter the 4-digit PIN to join your table
            </p>
          </div>

          {/* PIN INPUT - CRITICAL */}
          <div className="mb-8">
            <div className={`flex gap-3 justify-center mb-4 ${shakeError ? 'animate-shake' : ''}`}>
              {[0, 1, 2, 3].map((index) => (
                <input
                  key={index}
                  ref={pinInputRefs[index]}
                  type="text"
                  inputMode="numeric"
                  maxLength={1}
                  value={pin[index]}
                  onChange={(e) => handlePinInput(index, e.target.value)}
                  onKeyDown={(e) => handlePinKeyDown(index, e)}
                  className={`w-16 h-20 text-center text-3xl font-bold border-3 rounded-2xl focus:outline-none transition-all ${
                    pinError 
                      ? 'border-red-400 bg-red-50 text-red-600' 
                      : 'border-gray-300 text-gray-900'
                  }`}
                  style={!pinError ? { 
                    boxShadow: `0 0 0 4px ${themeColors.accent}30`
                  } : {}}
                />
              ))}
            </div>
            {pinError && (
              <p className="text-sm text-red-600 text-center font-medium animate-in fade-in">
                {pinError}
              </p>
            )}
          </div>

          {/* Primary Action */}
          <Button
            onClick={() => handleJoinSession(pin.join(''))}
            disabled={pin.some(d => !d)}
            size="lg"
            className="w-full h-14 text-lg rounded-2xl font-bold shadow-lg disabled:opacity-40 disabled:cursor-not-allowed transition-all"
            style={{ backgroundColor: themeColors.primary, color: 'white' }}
          >
            Join Table
          </Button>
        </div>

        {/* Secondary Action - Start New (if Draft only) */}
        {sessionData?.status === 'Draft' && !sessionData?.hasOrders && (
          <div className="text-center">
            <button
              onClick={() => setShowDeleteDraftModal(true)}
              className="text-sm font-medium text-gray-600 hover:text-gray-900 underline underline-offset-4 transition-colors"
            >
              Start New Order Instead
            </button>
          </div>
        )}

        {/* Info Card */}
        <div className="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-5 border border-gray-200">
          <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">When you join</p>
          <ul className="space-y-2.5">
            <li className="flex items-center gap-3 text-sm text-gray-700">
              <div className="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                <CheckCircle className="w-4 h-4 text-green-600" />
              </div>
              <span>Add items to the shared cart</span>
            </li>
            <li className="flex items-center gap-3 text-sm text-gray-700">
              <div className="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                <CheckCircle className="w-4 h-4 text-green-600" />
              </div>
              <span>See live updates from your group</span>
            </li>
            <li className="flex items-center gap-3 text-sm text-gray-700">
              <div className="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                <CheckCircle className="w-4 h-4 text-green-600" />
              </div>
              <span>Pay separately or split the bill</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  );

  // STATE: Empty Table - Initial welcome
  const renderEmptyState = () => (
    <div className="flex-1 px-5 py-8 flex flex-col justify-center animate-in fade-in slide-in-from-bottom duration-500">
      <div className="max-w-md mx-auto w-full space-y-6">
        {/* Main Card */}
        <div 
          className="bg-white rounded-[32px] shadow-2xl p-8"
          style={{ borderTop: `5px solid ${themeColors.accent}` }}
        >
          <div className="text-center mb-8">
            <div className="text-6xl mb-5">🍽️</div>
            <h2 className="text-3xl font-bold mb-3" style={{ color: themeColors.charcoal }}>
              Ready to order?
            </h2>
            <p className="text-gray-600 text-lg leading-relaxed">
              Start ordering for this table
            </p>
          </div>

          {/* Primary CTA */}
          <Button
            onClick={() => setShowAuthModal(true)}
            size="lg"
            className="w-full h-14 text-lg rounded-2xl font-bold shadow-lg"
            style={{ backgroundColor: themeColors.primary, color: 'white' }}
          >
            Start Order
          </Button>

          {/* Quick guest option */}
          {vendorSettings?.allowGuestOrdering !== false && (
            <button
              onClick={() => handleStartOrder('guest')}
              className="w-full mt-4 text-center py-3.5 text-sm font-semibold rounded-2xl hover:bg-gray-50 transition-all"
              style={{ color: themeColors.primary }}
            >
              Continue as Guest
            </button>
          )}
        </div>

        {/* Info Text */}
        <div className="text-center px-4">
          <p className="text-sm text-gray-500 leading-relaxed">
            Others at your table can join by scanning the QR code
          </p>
        </div>
      </div>
    </div>
  );

  // Abandoned Draft State
  const renderAbandonedDraftState = () => (
    <div className="flex-1 px-5 py-8 flex flex-col justify-center">
      <div className="max-w-md mx-auto w-full space-y-5">
        <div className="bg-amber-50 border-2 border-amber-300 rounded-2xl p-5">
          <div className="flex items-start gap-3">
            <AlertTriangle className="w-6 h-6 text-amber-600 shrink-0 mt-1" />
            <div>
              <h3 className="font-bold text-amber-900 mb-1 text-lg">Unfinished order</h3>
              <p className="text-sm text-amber-800 leading-relaxed">
                This table has an order in progress with no items sent yet.
              </p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-[32px] shadow-2xl p-7" style={{ borderTop: `5px solid ${themeColors.accent}` }}>
          <h3 className="text-2xl font-bold mb-2" style={{ color: themeColors.charcoal }}>
            Start Fresh
          </h3>
          <p className="text-gray-600 mb-6 text-sm">Begin a new order</p>
          <Button
            onClick={() => setShowDeleteDraftModal(true)}
            size="lg"
            className="w-full h-14 text-lg rounded-2xl font-bold shadow-lg"
            style={{ backgroundColor: themeColors.primary, color: 'white' }}
          >
            Start New Order
          </Button>
        </div>

        <div className="bg-white rounded-2xl shadow-lg p-6">
          <h3 className="text-lg font-semibold mb-3" style={{ color: themeColors.charcoal }}>
            Or enter PIN to join
          </h3>
          <div className="flex gap-2 mb-3">
            {[0, 1, 2, 3].map((index) => (
              <input
                key={index}
                ref={pinInputRefs[index]}
                type="text"
                inputMode="numeric"
                maxLength={1}
                value={pin[index]}
                onChange={(e) => handlePinInput(index, e.target.value)}
                onKeyDown={(e) => handlePinKeyDown(index, e)}
                className="flex-1 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-lg focus:outline-none transition-all"
              />
            ))}
          </div>
          <Button
            onClick={() => handleJoinSession(pin.join(''))}
            disabled={pin.some(d => !d)}
            variant="outline"
            size="lg"
            className="w-full rounded-xl font-semibold"
          >
            Join with PIN
          </Button>
        </div>
      </div>
    </div>
  );

  // Main render
  const renderContent = () => {
    switch (sessionState) {
      case 'empty':
        return renderEmptyState();
      case 'pin-display':
        return renderPinDisplayState();
      case 'joinable':
        return renderJoinableState();
      case 'abandoned-draft':
        return renderAbandonedDraftState();
      default:
        return renderEmptyState();
    }
  };

  return (
    <div className="min-h-screen flex flex-col" style={{ backgroundColor: themeColors.warmBeige }}>
      {renderHero()}
      {renderContent()}

      {/* Sticky Bottom: Call Waiter */}
      <div className="sticky bottom-0 left-0 right-0 bg-white border-t-2 border-gray-200 shadow-2xl z-40 p-4">
        <div className="max-w-md mx-auto">
          <Button
            onClick={handleCallWaiterInternal}
            variant="outline"
            size="lg"
            className="w-full h-13 rounded-2xl font-bold gap-2 border-2"
            style={{ borderColor: themeColors.primary, color: themeColors.primary }}
          >
            <Bell className="w-5 h-5" />
            Call the Waiter
          </Button>
          
          <div className="text-center mt-3">
            <p className="text-xs text-gray-500">
              Powered by <span className="font-bold text-gray-700">TAVLO</span>
            </p>
          </div>
        </div>
      </div>

      {/* Auth Modal - Bottom Sheet */}
      {showAuthModal && (
        <div className="fixed inset-0 bg-black/60 flex items-end justify-center z-50 animate-in fade-in">
          <div className="bg-white rounded-t-[32px] w-full max-w-md p-6 pb-8 animate-in slide-in-from-bottom duration-300">
            <div className="w-12 h-1.5 bg-gray-300 rounded-full mx-auto mb-6" />
            
            <h3 className="text-2xl font-bold mb-3" style={{ color: themeColors.charcoal }}>
              How would you like to continue?
            </h3>
            <p className="text-gray-600 mb-6 text-sm leading-relaxed">
              Sign in to save your order history and earn rewards
            </p>

            <div className="space-y-3">
              {vendorSettings?.allowGuestOrdering !== false && (
                <Button
                  onClick={() => handleStartOrder('guest')}
                  variant="outline"
                  size="lg"
                  className="w-full h-13 rounded-2xl font-semibold border-2"
                >
                  Continue as Guest
                </Button>
              )}
              <Button
                onClick={() => handleStartOrder('signin')}
                variant="outline"
                size="lg"
                className="w-full h-13 rounded-2xl font-semibold border-2"
              >
                Sign In
              </Button>
              <Button
                onClick={() => handleStartOrder('register')}
                size="lg"
                className="w-full h-13 rounded-2xl font-semibold"
                style={{ backgroundColor: themeColors.primary, color: 'white' }}
              >
                Create Account
              </Button>
            </div>

            <button
              onClick={() => setShowAuthModal(false)}
              className="w-full mt-5 text-center py-3 text-sm text-gray-500 hover:text-gray-700 font-medium"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {/* Delete Draft Modal */}
      {showDeleteDraftModal && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center p-5 z-50 animate-in fade-in">
          <div className="bg-white rounded-[28px] w-full max-w-md p-7 animate-in zoom-in duration-200">
            <div className="flex items-start gap-4 mb-6">
              <div className="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <AlertTriangle className="w-6 h-6 text-red-600" />
              </div>
              <div>
                <h3 className="text-xl font-bold mb-2 text-gray-900">
                  Delete unfinished order?
                </h3>
                <p className="text-sm text-gray-600 leading-relaxed">
                  This will remove the existing session and start fresh.
                </p>
              </div>
            </div>

            <div className="space-y-3">
              {vendorSettings?.allowGuestOrdering !== false && (
                <Button
                  onClick={() => handleDeleteAndStart('guest')}
                  size="lg"
                  className="w-full h-12 rounded-2xl font-semibold bg-red-600 hover:bg-red-700"
                >
                  Yes, Delete & Continue as Guest
                </Button>
              )}
              <Button
                onClick={() => handleDeleteAndStart('signin')}
                size="lg"
                className="w-full h-12 rounded-2xl font-semibold bg-red-600 hover:bg-red-700"
              >
                Yes, Delete & Sign In
              </Button>
              <Button
                onClick={() => setShowDeleteDraftModal(false)}
                variant="outline"
                size="lg"
                className="w-full h-12 rounded-2xl font-semibold border-2"
              >
                Cancel
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Accessibility Menu */}
      {showAccessibilityMenu && (
        <AccessibilityMenu onClose={() => setShowAccessibilityMenu(false)} />
      )}

      <style>{`
        @keyframes shake {
          0%, 100% { transform: translateX(0); }
          25% { transform: translateX(-10px); }
          75% { transform: translateX(10px); }
        }
        .animate-shake {
          animation: shake 0.4s ease-in-out;
        }
      `}</style>
    </div>
  );
}