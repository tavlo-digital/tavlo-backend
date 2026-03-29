import { useState } from 'react';
import { HomePage } from './homepage/HomePage';
import { RestaurantPage } from './restaurant/RestaurantPage';
import { AccountPage } from './account/AccountPage';
import { AuthModal } from './shared/AuthModal';
import { toast } from 'sonner@2.0.3';
import { PlatformLanguageProvider } from '../contexts/PlatformLanguageContext';
import { getPlatformTranslation } from '../utils/platformTranslations';

type Screen = 'home' | 'restaurant' | 'account' | 'qr-order';

interface PlatformAppProps {
  onTakeawayStart?: (restaurantId: string, guestData: any, pickupData: any) => void;
  onBackToPlatform?: () => void; // Navigate back to platform marketing page
}

export function PlatformApp({ onTakeawayStart, onBackToPlatform }: PlatformAppProps) {
  const [screen, setScreen] = useState<Screen>('home');
  const [user, setUser] = useState<any>(null);
  const [showAuthModal, setShowAuthModal] = useState(false);
  const [authMode, setAuthMode] = useState<'login' | 'register'>('login');
  const [selectedRestaurantId, setSelectedRestaurantId] = useState<string | null>(null);
  
  // Takeaway order state
  const [takeawayOrder, setTakeawayOrder] = useState<{
    guestData: { name: string; phone?: string; email?: string } | null;
    pickupData: { pickupTime: string; scheduledFor: 'asap' | 'scheduled'; displayTime: string } | null;
  } | null>(null);

  const handleLogin = () => {
    setAuthMode('login');
    setShowAuthModal(true);
  };

  const handleRegister = () => {
    setAuthMode('register');
    setShowAuthModal(true);
  };

  const handleAuthSuccess = (userData: any) => {
    setUser(userData);
    setShowAuthModal(false);
  };

  const handleLogout = () => {
    setUser(null);
    setScreen('home');
    toast.success('Logged out successfully');
  };

  const handleRestaurantClick = (restaurantId: string) => {
    setSelectedRestaurantId(restaurantId);
    setScreen('restaurant');
  };

  const handleScanQR = () => {
    // Navigate to the existing QR ordering flow
    setScreen('qr-order');
  };
  
  const handleTakeawayConfirm = (guestData: any, pickupData: any) => {
    console.log('Takeaway order confirmed:', { guestData, pickupData });
    setTakeawayOrder({ guestData, pickupData });
    // TODO: Navigate to menu selection flow
    toast.success('Takeaway order started! Add items to your basket.');
    if (onTakeawayStart) {
      onTakeawayStart(selectedRestaurantId!, guestData, pickupData);
    }
  };

  const handleBackToHome = () => {
    setScreen('home');
    setSelectedRestaurantId(null);
  };

  return (
    <PlatformLanguageProvider>
      {screen === 'home' && (
        <HomePage
          user={user}
          onLoginClick={handleLogin}
          onRegisterClick={handleRegister}
          onProfileClick={() => setScreen('account')}
          onRestaurantClick={handleRestaurantClick}
          onBackToPlatform={onBackToPlatform}
        />
      )}

      {screen === 'restaurant' && selectedRestaurantId && (
        <RestaurantPage
          restaurantId={selectedRestaurantId}
          user={user}
          onBack={handleBackToHome}
          onScanQR={handleScanQR}
          onLoginRequired={handleLogin}
          onTakeawayConfirm={handleTakeawayConfirm}
          takeawayOrder={takeawayOrder}
        />
      )}

      {screen === 'account' && user && (
        <AccountPage
          user={user}
          onBack={handleBackToHome}
          onLogout={handleLogout}
          onRestaurantClick={handleRestaurantClick}
        />
      )}

      {screen === 'qr-order' && (
        <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
          <div className="text-center max-w-md">
            <div className="w-24 h-24 bg-gradient-to-br from-orange-500 to-red-500 rounded-3xl flex items-center justify-center mx-auto mb-6">
              <span className="text-5xl">📱</span>
            </div>
            <h2 className="text-2xl sm:text-3xl mb-4">QR Ordering System</h2>
            <p className="text-gray-600 mb-8">
              This connects to your existing restaurant ordering interface. 
              When customers scan a QR code at their table, they'll be redirected 
              to the full ordering experience you've already built.
            </p>
            <button
              onClick={handleBackToHome}
              className="text-orange-600 hover:text-orange-700"
            >
              ← Back to Platform
            </button>
          </div>
        </div>
      )}

      {/* Auth Modal */}
      <AuthModal
        isOpen={showAuthModal}
        onClose={() => setShowAuthModal(false)}
        mode={authMode}
        onSuccess={handleAuthSuccess}
      />
    </PlatformLanguageProvider>
  );
}