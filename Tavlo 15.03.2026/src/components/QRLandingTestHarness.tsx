import { useState } from 'react';
import { QRLanding, SessionState } from './QRLanding';
import { Button } from './ui/button';

/**
 * Test Harness for QR Landing Page States
 * 
 * This component allows you to quickly test all 6 states of the QR landing page:
 * 1. Empty - Initial scan, no session
 * 2. PIN Display - First person created session, show PIN
 * 3. Joinable - Second person, enter PIN to join
 * 4. Abandoned Draft - Unfinished order
 * 5. Cash Pending - Blocked waiting for payment
 * 6. Security Block - Too many scans
 */
export function QRLandingTestHarness() {
  const [currentState, setCurrentState] = useState<SessionState>('empty');
  const [sessionData, setSessionData] = useState<any>({
    sessionId: 'sess_123',
    pin: '4982',
    status: 'Draft',
    hasOrders: false,
    lastOrderNumber: 'ORD-2024-1234',
    lastOrderAmount: 45.80,
    lastOrderStatus: 'completed',
    scansInLastHour: 15
  });

  const handleStateChange = (state: SessionState) => {
    setCurrentState(state);
    
    // Update session data based on state
    if (state === 'pin-display') {
      setSessionData({
        ...sessionData,
        pin: '4982',
        status: 'Draft',
        hasOrders: false
      });
    } else if (state === 'joinable') {
      setSessionData({
        ...sessionData,
        pin: '4982',
        status: 'Draft',
        hasOrders: false
      });
    } else if (state === 'abandoned-draft') {
      setSessionData({
        ...sessionData,
        status: 'Draft',
        hasOrders: false
      });
    } else if (state === 'cash-pending') {
      setSessionData({
        ...sessionData,
        status: 'Active',
        hasCashBalance: true
      });
    } else if (state === 'closed-recent') {
      setSessionData({
        ...sessionData,
        status: 'Closed Online',
        lastOrderNumber: 'ORD-2024-1234',
        lastOrderAmount: 45.80,
        lastOrderStatus: 'completed'
      });
    }
  };

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Test Controls - Sticky Top */}
      <div className="sticky top-0 z-50 bg-white border-b-4 border-blue-600 shadow-lg">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between mb-3">
            <h1 className="text-xl font-bold text-gray-900">🧪 QR Landing Test Harness</h1>
            <div className="px-3 py-1 bg-blue-100 text-blue-900 rounded-lg text-sm font-semibold">
              Current: {currentState.toUpperCase()}
            </div>
          </div>
          
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
            <Button
              onClick={() => handleStateChange('empty')}
              variant={currentState === 'empty' ? 'default' : 'outline'}
              size="sm"
              className="text-xs"
            >
              1. Empty
            </Button>
            <Button
              onClick={() => handleStateChange('pin-display')}
              variant={currentState === 'pin-display' ? 'default' : 'outline'}
              size="sm"
              className="text-xs"
            >
              2. PIN Display
            </Button>
            <Button
              onClick={() => handleStateChange('joinable')}
              variant={currentState === 'joinable' ? 'default' : 'outline'}
              size="sm"
              className="text-xs"
            >
              3. Joinable
            </Button>
            <Button
              onClick={() => handleStateChange('abandoned-draft')}
              variant={currentState === 'abandoned-draft' ? 'default' : 'outline'}
              size="sm"
              className="text-xs"
            >
              4. Abandoned
            </Button>
            <Button
              onClick={() => handleStateChange('cash-pending')}
              variant={currentState === 'cash-pending' ? 'default' : 'outline'}
              size="sm"
              className="text-xs"
            >
              5. Cash Pending
            </Button>
            <Button
              onClick={() => handleStateChange('security-block')}
              variant={currentState === 'security-block' ? 'default' : 'outline'}
              size="sm"
              className="text-xs"
            >
              6. Security Block
            </Button>
          </div>

          {/* State Info */}
          <div className="mt-3 p-3 bg-gray-50 rounded-lg">
            <p className="text-xs text-gray-600 mb-2 font-semibold">Current State Details:</p>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
              <div>
                <span className="text-gray-500">PIN:</span>{' '}
                <span className="font-mono font-bold">{sessionData.pin || 'N/A'}</span>
              </div>
              <div>
                <span className="text-gray-500">Status:</span>{' '}
                <span className="font-semibold">{sessionData.status || 'N/A'}</span>
              </div>
              <div>
                <span className="text-gray-500">Has Orders:</span>{' '}
                <span className="font-semibold">{sessionData.hasOrders ? 'Yes' : 'No'}</span>
              </div>
              <div>
                <span className="text-gray-500">Session ID:</span>{' '}
                <span className="font-mono">{sessionData.sessionId}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* QR Landing Component */}
      <div className="bg-white">
        <QRLanding
          restaurantName="La Bella Cucina"
          tableNumber="12"
          cuisineTag="Italian Fine Dining"
          restaurantInfo={{
            address: "123 Main Street, Vienna",
            phone: "+43 1 234 5678",
            logo: "https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=100&h=100&fit=crop",
            coverPhoto: "https://images.unsplash.com/photo-1722587561829-8a53e1935e20?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080",
            rating: 4.8,
            description: "Authentic Italian cuisine in the heart of Vienna"
          }}
          vendorSettings={{
            restaurantName: "La Bella Cucina",
            primaryColor: "#2d3a2d",
            accentColor: "#d4a574",
            allowGuestOrdering: true,
            currency: "EUR"
          }}
          sessionState={currentState}
          sessionData={sessionData}
          previousOrders={[
            { id: 'ord_1', orderNumber: 'ORD-2024-1234', total: 45.80, status: 'completed' }
          ]}
          // Event handlers with console logs
          onStartNewSession={(data) => {
            console.log('✅ Start New Session:', data);
            alert(`Started new session!\nAuth: ${data.authChoice}\nLanguage: ${data.language}`);
            // Simulate session creation
            setCurrentState('pin-display');
            setSessionData({
              ...sessionData,
              sessionId: `sess_${Date.now()}`,
              pin: Math.floor(1000 + Math.random() * 9000).toString(),
              status: 'Draft'
            });
          }}
          onJoinSession={(pin) => {
            console.log('✅ Join Session with PIN:', pin);
            if (pin === sessionData.pin) {
              alert(`Successfully joined! PIN: ${pin}`);
              // Simulate joining - go to menu
              setCurrentState('empty'); // Reset for demo
            } else {
              alert(`Invalid PIN! Expected: ${sessionData.pin}, Got: ${pin}`);
            }
          }}
          onDeleteDraftAndStartNew={(data) => {
            console.log('✅ Delete Draft & Start New:', data);
            alert(`Deleted draft and starting fresh!\nAuth: ${data.authChoice}`);
            setCurrentState('pin-display');
            setSessionData({
              ...sessionData,
              pin: Math.floor(1000 + Math.random() * 9000).toString()
            });
          }}
          onCallWaiter={() => {
            console.log('🔔 Call Waiter');
            alert('Waiter has been notified! 🔔');
          }}
          onViewPreviousOrders={() => {
            console.log('📋 View Previous Orders');
            alert('Opening order history...');
          }}
          onViewOrderDetails={(orderId) => {
            console.log('📄 View Order Details:', orderId);
            alert(`Opening order details for: ${orderId}`);
          }}
          onContinueToMenu={() => {
            console.log('🍽️ Continue to Menu');
            alert('Navigating to menu...');
          }}
        />
      </div>

      {/* Instructions */}
      <div className="max-w-7xl mx-auto px-4 py-8">
        <div className="bg-blue-50 border-2 border-blue-200 rounded-2xl p-6">
          <h2 className="text-lg font-bold text-blue-900 mb-3">📖 Testing Instructions</h2>
          <div className="space-y-4 text-sm text-blue-800">
            <div>
              <p className="font-semibold mb-1">1️⃣ Empty State</p>
              <p className="text-xs">Initial QR scan. Click "Start Order" to create a session.</p>
            </div>
            <div>
              <p className="font-semibold mb-1">2️⃣ PIN Display State</p>
              <p className="text-xs">First person sees PIN (4982). Copy it and share with others.</p>
            </div>
            <div>
              <p className="font-semibold mb-1">3️⃣ Joinable State</p>
              <p className="text-xs">Second person enters PIN. Type: <code className="bg-white px-2 py-0.5 rounded font-mono font-bold">4982</code></p>
            </div>
            <div>
              <p className="font-semibold mb-1">4️⃣ Abandoned Draft</p>
              <p className="text-xs">Unfinished order. Choose "Start New" or "Enter PIN".</p>
            </div>
            <div>
              <p className="font-semibold mb-1">5️⃣ Cash Pending</p>
              <p className="text-xs">Blocked state. Only action: "Call Waiter".</p>
            </div>
            <div>
              <p className="font-semibold mb-1">6️⃣ Security Block</p>
              <p className="text-xs">Too many scans detected. Abuse prevention triggered.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
