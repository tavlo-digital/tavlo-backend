import { useState, useEffect } from 'react';
import { VendorDashboard } from '../vendor/VendorDashboard';
import { GuidedTourEntryModal } from './GuidedTourEntryModal';
import { GuidedTourManagerV2 } from './GuidedTourManagerV2';
import { GuidedTourCompletionModal } from './GuidedTourCompletionModal';
import { ResumeGuidedTourCard } from './ResumeGuidedTourCard';
import { GoLiveModal } from './GoLiveModal';
import {
  getInitialTourState,
  startTour,
  endTour,
  completeTour,
  advanceToInstructionPhase,
  completeStep,
  skipStep,
  resumeTour,
  shouldShowEntryModal,
  markModalShown,
  type TourState
} from './GuidedTourState';

interface SetupProgress {
  hasMenu: boolean;
  hasLogo: boolean;
  hasCover: boolean;
  hasQRCodes: boolean;
  subscription: boolean;
  legalTax: boolean;
  isLiveAndDiscoverable: boolean;
}

interface OnboardingDashboardWrapperV2Props {
  vendorId: string;
  vendorStatus: 'demo' | 'activated' | 'live';
  setupProgress: SetupProgress;
  onActivateClick?: () => void;
  onChecklistItemClick?: (item: string) => void;
  onVisibilityChange?: (isLive: boolean) => void;
}

export function OnboardingDashboardWrapperV2({
  vendorId,
  vendorStatus,
  setupProgress,
  onActivateClick,
  onChecklistItemClick,
  onVisibilityChange
}: OnboardingDashboardWrapperV2Props) {
  const [tourState, setTourState] = useState<TourState>(getInitialTourState());
  const [showEntryModal, setShowEntryModal] = useState(false);
  const [showCompletionModal, setShowCompletionModal] = useState(false);
  const [showGoLiveModal, setShowGoLiveModal] = useState(false);
  const [currentScreen, setCurrentScreen] = useState<string>('home');
  const [hasCheckedEntryModal, setHasCheckedEntryModal] = useState(false);

  // Show entry modal on first visit when status becomes 'activated'
  useEffect(() => {
    if (vendorStatus === 'activated' && !hasCheckedEntryModal) {
      const shouldShow = shouldShowEntryModal();
      console.log('🎭 Entry modal check:', { vendorStatus, hasCheckedEntryModal, shouldShow });
      if (shouldShow) {
        const timer = setTimeout(() => {
          console.log('✨ Showing entry modal');
          setShowEntryModal(true);
          setHasCheckedEntryModal(true);
        }, 500);
        return () => clearTimeout(timer);
      } else {
        console.log('❌ Not showing entry modal - already shown or tour active');
        setHasCheckedEntryModal(true);
      }
    }
  }, [vendorStatus, hasCheckedEntryModal]);

  // Handle tour state changes - show completion modal when tour completes
  useEffect(() => {
    const state = getInitialTourState();
    setTourState(state);
    
    // Check if tour just completed
    if (state.completedAt && !state.isActive && !showCompletionModal) {
      // Check if completion was recent (within last 2 seconds)
      const completedTime = new Date(state.completedAt).getTime();
      const now = new Date().getTime();
      if (now - completedTime < 2000) {
        setShowCompletionModal(true);
      }
    }
  }, []);

  const handleStartTour = () => {
    const newState = startTour();
    setTourState(newState);
    setShowEntryModal(false);
    markModalShown();
  };

  const handleSkipTour = () => {
    setShowEntryModal(false);
    markModalShown();
  };

  const handleResumeTour = () => {
    const newState = resumeTour();
    setTourState(newState);
  };

  const handleEndTour = () => {
    const newState = endTour();
    setTourState(newState);
  };

  const handleNavigate = (destination: string) => {
    setCurrentScreen(destination);
  };

  const handleAdvanceToInstruction = (step: string) => {
    const newState = advanceToInstructionPhase(step as any);
    setTourState(newState);
  };

  const handleCompleteStep = (step: string) => {
    const newState = completeStep(step as any);
    setTourState(newState);
    
    // Check if tour is complete
    if (newState.completedAt && !newState.isActive) {
      setShowCompletionModal(true);
    }
  };

  const handleSkipStep = (step: string) => {
    const newState = skipStep(step as any);
    setTourState(newState);
    
    // Check if tour is complete
    if (newState.completedAt && !newState.isActive) {
      setShowCompletionModal(true);
    }
  };

  const handleScreenChange = (screen: string) => {
    setCurrentScreen(screen);
  };

  const handleActivateClick = () => {
    if (onActivateClick) {
      onActivateClick();
    }
  };

  const handleGoLiveClick = () => {
    setShowGoLiveModal(true);
  };

  const handleCompletionGoToDashboard = () => {
    setShowCompletionModal(false);
    setCurrentScreen('home');
  };

  const handleCompletionClose = () => {
    setShowCompletionModal(false);
  };

  // Check if vendor can go live
  const canGoLive = setupProgress.subscription && setupProgress.legalTax && setupProgress.hasMenu;

  // Determine if we should show resume card
  const showResumeCard = !tourState.isActive && 
                         !tourState.completedAt && 
                         currentScreen === 'home' &&
                         Object.values(tourState.stepStatuses).some(status => 
                           status === 'not_started' || status === 'in_progress'
                         );

  return (
    <div className="relative min-h-screen">
      {/* Entry Modal */}
      {showEntryModal && (
        <GuidedTourEntryModal
          onStart={handleStartTour}
          onSkip={handleSkipTour}
        />
      )}

      {/* Completion Modal */}
      {showCompletionModal && (
        <GuidedTourCompletionModal
          onGoToDashboard={handleCompletionGoToDashboard}
          onClose={handleCompletionClose}
        />
      )}

      {/* Go Live Modal */}
      {showGoLiveModal && (
        <GoLiveModal
          onClose={() => setShowGoLiveModal(false)}
          onConfirm={() => {
            setShowGoLiveModal(false);
            // Handle go live logic
          }}
          canGoLive={canGoLive}
          missingRequirements={!setupProgress.hasMenu ? ['At least 1 menu item'] : []}
        />
      )}

      {/* Vendor Dashboard */}
      <VendorDashboard 
        vendorId={vendorId} 
        vendorStatus={vendorStatus}
        onActivateClick={handleActivateClick}
        onGuidedTourClick={handleResumeTour}
        onGoLiveClick={handleGoLiveClick}
        onNavigate={handleScreenChange}
        controlledScreen={currentScreen as any}
        initialIsLiveAndDiscoverable={setupProgress.isLiveAndDiscoverable}
        onVisibilityChange={onVisibilityChange}
      />

      {/* Guided Tour Manager - Overlays on top */}
      {tourState.isActive && (
        <GuidedTourManagerV2
          currentStep={tourState.currentStep}
          currentPhase={tourState.currentPhase}
          currentScreen={currentScreen}
          onNavigate={handleNavigate}
          onAdvanceToInstruction={handleAdvanceToInstruction}
          onCompleteStep={handleCompleteStep}
          onSkipStep={handleSkipStep}
          onEndTour={handleEndTour}
        />
      )}

      {/* Resume Tour Card - Shows in Dashboard when tour not active but not completed */}
      {showResumeCard && currentScreen === 'home' && (
        <div className="fixed bottom-6 right-6 z-40 max-w-sm">
          <ResumeGuidedTourCard onResume={handleResumeTour} />
        </div>
      )}
    </div>
  );
}