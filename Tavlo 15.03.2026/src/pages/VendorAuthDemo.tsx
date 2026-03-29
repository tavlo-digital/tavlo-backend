import { useState } from 'react';
import { VendorSignIn } from '../components/vendor-auth/VendorSignIn';
import { AcceptInvitation } from '../components/vendor-auth/AcceptInvitation';
import { VendorRegistration } from '../components/vendor-onboarding/VendorRegistration';
import { Button } from '../components/ui/button';
import { ArrowLeft } from 'lucide-react';

type AuthView = 'menu' | 'signin' | 'join' | 'accept-invite';

export function VendorAuthDemo() {
  const [view, setView] = useState<AuthView>('menu');
  const [mockInviteToken] = useState('mock-invite-token-12345');

  const handleAuthSuccess = (data: any) => {
    console.log('Auth success:', data);
    alert(`Success! Email: ${data.email}\nToken: ${data.token}`);
    setView('menu');
  };

  const handleRegistrationComplete = (data: any) => {
    console.log('Registration complete:', data);
    // Simulate successful registration - in production this would create the vendor account
    // For demo purposes, just show success and return to menu
    setView('menu');
  };

  const handleForgotPassword = () => {
    alert('Forgot password flow would go here');
  };

  // Menu view
  if (view === 'menu') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
        <div className="max-w-2xl w-full bg-white rounded-2xl shadow-xl p-8">
          <div className="text-center mb-8">
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Vendor Authentication Demo</h1>
            <p className="text-gray-600">Test the three authentication entry paths</p>
          </div>

          <div className="space-y-4">
            <div className="border border-gray-200 rounded-lg p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-2">1. Join Tavlo (New Restaurant)</h2>
              <p className="text-gray-600 mb-4">
                New restaurant owners create an account with business name, country, email, and password.
              </p>
              <Button
                onClick={() => setView('join')}
                className="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700"
              >
                View Join Tavlo Screen
              </Button>
            </div>

            <div className="border border-gray-200 rounded-lg p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-2">2. Sign In (Existing Users)</h2>
              <p className="text-gray-600 mb-4">
                Owners, managers, and staff who already have accounts sign in with email and password.
              </p>
              <Button
                onClick={() => setView('signin')}
                className="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700"
              >
                View Sign In Screen
              </Button>
            </div>

            <div className="border border-gray-200 rounded-lg p-6">
              <h2 className="text-xl font-semibold text-gray-900 mb-2">3. Accept Invitation (Invited Users)</h2>
              <p className="text-gray-600 mb-4">
                Team members invited by restaurant owners set their password and join the restaurant.
              </p>
              <Button
                onClick={() => setView('accept-invite')}
                className="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700"
              >
                View Accept Invitation Screen
              </Button>
            </div>
          </div>

          <div className="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <h3 className="font-medium text-blue-900 mb-2">Key Features:</h3>
            <ul className="text-sm text-blue-800 space-y-1">
              <li>✓ Clear separation of three entry paths</li>
              <li>✓ No overlap between flows</li>
              <li>✓ Proper navigation links between screens</li>
              <li>✓ Accept Invitation includes expired/used states</li>
              <li>✓ Consistent branding across all screens</li>
            </ul>
          </div>
        </div>
      </div>
    );
  }

  // Back button for all auth screens
  const BackButton = () => (
    <button
      onClick={() => setView('menu')}
      className="fixed top-4 left-4 flex items-center gap-2 px-4 py-2 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow z-50"
    >
      <ArrowLeft className="w-4 h-4" />
      <span className="text-sm font-medium">Back to Menu</span>
    </button>
  );

  // Show selected auth screen
  return (
    <>
      <BackButton />
      
      {view === 'join' && (
        <VendorRegistration
          onComplete={handleRegistrationComplete}
          onNavigateToSignIn={() => setView('signin')}
        />
      )}

      {view === 'signin' && (
        <VendorSignIn
          onSuccess={handleAuthSuccess}
          onForgotPassword={handleForgotPassword}
          onNavigateToJoin={() => setView('join')}
        />
      )}

      {view === 'accept-invite' && (
        <AcceptInvitation
          inviteToken={mockInviteToken}
          onSuccess={handleAuthSuccess}
          onNavigateToSignIn={() => setView('signin')}
        />
      )}
    </>
  );
}