import { useState, useEffect, useRef } from 'react';
import { VendorDashboard } from '../vendor/VendorDashboard';
import { DemoModeBanner } from './DemoModeBanner';
import { LiveModeBanner } from './LiveModeBanner';
import { ActivatedStateBanner } from './ActivatedStateBanner';
import { ActivationChecklist, createDefaultChecklistItems } from './ActivationChecklist';
import { VendorStatusBadge } from './VendorStatusBadge';
import { LockedActionModal } from './LockedActionModal';
import { GuidedTourModal } from './GuidedTourModal';
import { GuidedTourManager, TourStep } from './GuidedTourManager';
import { GoLiveModal } from './GoLiveModal';
import { ResumeGuidedTourCard } from './ResumeGuidedTourCard';

type VendorStatus = 'demo' | 'activated' | 'live';

interface OnboardingDashboardWrapperProps {
  vendorId: string;
  vendorStatus: VendorStatus;
  setupProgress: {
    subscription: boolean;
    legalTax: boolean;
    hasMenu: boolean;
  };
  onActivateClick: () => void;
  onChecklistItemClick: (itemId: string) => void;
}

export function OnboardingDashboardWrapper({
  vendorId,
  vendorStatus,
  setupProgress,
  onActivateClick,
  onChecklistItemClick
}: OnboardingDashboardWrapperProps) {
  const [showChecklist, setShowChecklist] = useState(false);
  const [showLockedModal, setShowLockedModal] = useState(false);
  const [showLiveBanner, setShowLiveBanner] = useState(false);
  const [showGuidedTourModal, setShowGuidedTourModal] = useState(false);
  const [showGoLiveModal, setShowGoLiveModal] = useState(false);
  const [currentTourStep, setCurrentTourStep] = useState<TourStep | null>(null);
  const [currentScreen, setCurrentScreen] = useState<string>('home');
  const [navigationTrigger, setNavigationTrigger] = useState<{ screen: string; timestamp: number } | null>(null);

  // Load tour state from session storage
  useEffect(() => {
    if (vendorStatus === 'activated') {
      const tourActive = sessionStorage.getItem(`vendor-${vendorId}-guided-tour-active`);
      const currentStep = sessionStorage.getItem(`vendor-${vendorId}-guided-tour-step`) as TourStep | null;
      
      if (tourActive === 'true' && currentStep) {
        setCurrentTourStep(currentStep);
      }
    }
  }, [vendorStatus, vendorId]);

  // Show guided tour modal on first visit to activated state
  useEffect(() => {
    if (vendorStatus === 'activated') {
      const hasSeenTourModal = sessionStorage.getItem(`vendor-${vendorId}-guided-tour-seen`);
      if (!hasSeenTourModal) {
        // Show modal after a short delay
        setTimeout(() => {
          setShowGuidedTourModal(true);
        }, 500);
      }
    }
  }, [vendorStatus, vendorId]);

  // Show live banner only on first time going live
  useEffect(() => {
    if (vendorStatus === 'live') {
      const hasSeenLiveBanner = sessionStorage.getItem(`vendor-${vendorId}-live-banner-seen`);
      if (!hasSeenLiveBanner) {
        setShowLiveBanner(true);
        sessionStorage.setItem(`vendor-${vendorId}-live-banner-seen`, 'true');
      }
    }
  }, [vendorStatus, vendorId]);

  const checklistItems = createDefaultChecklistItems(setupProgress);

  const handleActivateClick = () => {
    if (vendorStatus === 'demo') {
      // In demo mode, just go to subscription
      onActivateClick();
    } else {
      // In activated, show checklist
      setShowChecklist(true);
    }
  };

  const handleLockedActionClick = () => {
    setShowLockedModal(true);
  };

  const handleGuidedTourClick = () => {
    setShowGuidedTourModal(true);
  };

  const handleGoLiveClick = () => {
    setShowGoLiveModal(true);
  };

  const handleTourNavigate = (destination: string) => {
    // Map tour destinations to screen names
    const screenMap: Record<string, string> = {
      'appearance': 'settings',
      'menu': 'menu',
      'inventory': 'inventory',
      'qr-codes': 'qr-codes',
      'payments': 'billing'
    };
    
    const targetScreen = screenMap[destination];
    if (targetScreen) {
      setCurrentScreen(targetScreen);
    }
  };

  const handleScreenChange = (screen: string) => {
    setCurrentScreen(screen);
  };

  const handleTourStepComplete = () => {
    // Move to next step
    const steps: TourStep[] = ['appearance', 'menu', 'inventory', 'qr-codes', 'payments'];
    const currentIndex = currentTourStep ? steps.indexOf(currentTourStep) : -1;
    
    if (currentIndex >= 0 && currentIndex < steps.length - 1) {
      const nextStep = steps[currentIndex + 1];
      setCurrentTourStep(nextStep);
      sessionStorage.setItem(`vendor-${vendorId}-guided-tour-step`, nextStep);
    } else {
      // Tour completed
      setCurrentTourStep(null);
      sessionStorage.setItem(`vendor-${vendorId}-guided-tour-active`, 'false');
      sessionStorage.removeItem(`vendor-${vendorId}-guided-tour-step`);
    }
  };

  const handleTourSkipStep = () => {
    handleTourStepComplete();
  };

  const handleResumeGuidedTour = () => {
    // Resume from first step
    sessionStorage.setItem(`vendor-${vendorId}-guided-tour-active`, 'true');
    sessionStorage.setItem(`vendor-${vendorId}-guided-tour-step`, 'appearance');
    setCurrentTourStep('appearance');
  };

  // Check if we should show resume tour card
  const hasSeenTourModal = sessionStorage.getItem(`vendor-${vendorId}-guided-tour-seen`) === 'true';
  const showResumeTourCard = vendorStatus === 'activated' && hasSeenTourModal && !currentTourStep;

  // Check if vendor can go live
  const canGoLive = setupProgress.subscription && setupProgress.legalTax && setupProgress.hasMenu;
  const missingRequirements: string[] = [];
  if (!setupProgress.hasMenu) {
    missingRequirements.push('At least 1 menu item');
  }

  // Determine tour box position based on current step and screen
  const getTourBoxPosition = () => {
    if (!currentTourStep) return 'top-6 left-1/2 transform -translate-x-1/2';
    
    // If we're on the home screen, show at top
    if (currentScreen === 'home') {
      return 'top-6 left-1/2 transform -translate-x-1/2';
    }
    
    // If we've navigated to the relevant screen, show at top
    return 'top-6 left-1/2 transform -translate-x-1/2';
  };

  return (
    <div className="flex flex-col h-screen">
      {/* Live Mode Banner - One-time, dismissible */}
      {showLiveBanner && vendorStatus === 'live' && (
        <LiveModeBanner onDismiss={() => setShowLiveBanner(false)} />
      )}
      
      {/* Activated State Banner - Show when activated but not live */}
      {vendorStatus === 'activated' && (
        <ActivatedStateBanner
          onGoLive={handleGoLiveClick}
          canGoLive={canGoLive}
          missingRequirements={missingRequirements}
        />
      )}
      
      {/* Demo Mode Banner */}
      {vendorStatus === 'demo' && (
        <DemoModeBanner
          status={vendorStatus}
          onActivateClick={handleActivateClick}
        />
      )}

      {/* Main Dashboard */}
      <div className="flex-1 overflow-hidden relative">
        {/* Guided Tour Helper Box - Overlay on dashboard when tour is active */}
        {vendorStatus === 'activated' && currentTourStep && (
          <div className={`absolute ${getTourBoxPosition()} z-40 max-w-xl w-full px-4`}>
            <GuidedTourManager
              currentStep={currentTourStep}
              onNavigate={handleTourNavigate}
              onComplete={handleTourStepComplete}
              onSkipStep={handleTourSkipStep}
            />
          </div>
        )}
        
        <VendorDashboard 
          vendorId={vendorId} 
          vendorStatus={vendorStatus}
          onActivateClick={handleActivateClick}
          onLockedActionClick={handleLockedActionClick}
          onGuidedTourClick={handleGuidedTourClick}
          onGoLiveClick={handleGoLiveClick}
          onNavigate={handleScreenChange}
          controlledScreen={currentScreen as any}
        />
      </div>

      {/* Locked Action Modal - shown when clicking any action in demo mode */}
      <LockedActionModal
        isOpen={showLockedModal}
        onClose={() => setShowLockedModal(false)}
        onActivate={() => {
          setShowLockedModal(false);
          onActivateClick();
        }}
      />

      {/* Activation Checklist */}
      <ActivationChecklist
        isOpen={showChecklist}
        onClose={() => setShowChecklist(false)}
        onItemClick={(itemId) => {
          setShowChecklist(false);
          onChecklistItemClick(itemId);
        }}
        items={checklistItems}
        vendorStatus={vendorStatus}
      />

      {/* Guided Tour Modal */}
      <GuidedTourModal
        isOpen={showGuidedTourModal}
        onClose={() => setShowGuidedTourModal(false)}
        vendorId={vendorId}
        currentStep={currentTourStep}
        setCurrentStep={setCurrentTourStep}
      />

      {/* Go Live Modal */}
      <GoLiveModal
        isOpen={showGoLiveModal}
        onClose={() => setShowGoLiveModal(false)}
        vendorId={vendorId}
        restaurantName="La Bella Cucina"
      />

      {/* Resume Guided Tour Card */}
      {showResumeTourCard && (
        <div className="absolute bottom-6 right-6 z-30 max-w-sm">
          <ResumeGuidedTourCard onResume={handleResumeGuidedTour} />
        </div>
      )}
    </div>
  );
}