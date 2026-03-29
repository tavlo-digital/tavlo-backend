import { useState, useEffect } from 'react';
import { VendorRegistration } from '../components/vendor-onboarding/VendorRegistration';
import { OnboardingDashboardWrapperV2 } from '../components/vendor-onboarding/OnboardingDashboardWrapperV2';
import { SubscriptionSelectionPage } from '../components/vendor-onboarding/SubscriptionSelectionPage';
import { PaymentMethodPage } from '../components/vendor-onboarding/PaymentMethodPage';
import { PaymentLoading, PaymentFailed, PaymentSuccess } from '../components/vendor-onboarding/PaymentProcessingStates';
import { ActivationStarted } from '../components/vendor-onboarding/ActivationStarted';
import { SetupStep1Legal } from '../components/vendor-onboarding/SetupStep1Legal';

type VendorStatus = 'demo' | 'activated' | 'live';

type OnboardingView = 
  | 'registration'
  | 'demo-dashboard'
  | 'subscription-selection'
  | 'payment-method'
  | 'payment-processing'
  | 'payment-failed'
  | 'payment-success'
  | 'activation-started'
  | 'setup-step1-legal'
  | 'activated-dashboard'
  | 'live-dashboard';

interface VendorData {
  id: string;
  status: VendorStatus;
  businessName: string;
  country: string;
  email: string;
  setupProgress: {
    subscription: boolean;
    legalTax: boolean;
    hasMenu: boolean;
    hasLogo: boolean;
    hasCover: boolean;
    hasQRCodes: boolean;
    isLiveAndDiscoverable: boolean;
  };
  selectedPlan?: {
    id: string;
    name: string;
    price: number;
    interval: 'month' | 'year';
  };
  legalData?: any;
  paymentError?: string;
  guidedTour?: {
    hasSeenModal: boolean;
    isActive: boolean;
    currentStep: 'appearance' | 'menu' | 'inventory' | 'qr-codes' | 'payments' | null;
  };
}

export default function VendorOnboardingFlow() {
  const [currentView, setCurrentView] = useState<OnboardingView>('registration');
  const [vendorData, setVendorData] = useState<VendorData>({
    id: 'vendor_' + Date.now(),
    status: 'demo',
    businessName: '',
    country: '',
    email: '',
    setupProgress: {
      subscription: false,
      legalTax: false,
      hasMenu: false,
      hasLogo: false,
      hasCover: false,
      hasQRCodes: false,
      isLiveAndDiscoverable: false
    }
  });

  // Determine vendor status based on setup progress
  const determineVendorStatus = (progress: VendorData['setupProgress']): VendorStatus => {
    if (!progress.subscription) return 'demo';
    
    const allRequired = progress.subscription && 
                        progress.legalTax;
    
    return allRequired ? 'live' : 'activated';
  };

  // Handle registration
  const handleRegistration = (data: { businessName: string; country: string; email: string; password: string }) => {
    setVendorData({
      ...vendorData,
      businessName: data.businessName,
      country: data.country,
      email: data.email
    });
    setCurrentView('demo-dashboard');
  };

  // Handle activate click from demo dashboard
  const handleActivateClick = () => {
    setCurrentView('subscription-selection');
  };

  // Handle plan selection
  const handlePlanSelection = (planId: string, interval: 'month' | 'year') => {
    const plans = {
      basic: { name: 'Basic', monthly: 99, yearly: 990 },
      standard: { name: 'Standard', monthly: 199, yearly: 1990 },
      premium: { name: 'Premium', monthly: 299, yearly: 2990 }
    };

    const plan = plans[planId as keyof typeof plans];
    const price = interval === 'month' ? plan.monthly : plan.yearly;

    setVendorData({
      ...vendorData,
      selectedPlan: {
        id: planId,
        name: plan.name,
        price,
        interval
      }
    });
    setCurrentView('payment-method');
  };

  // Handle payment submission
  const handlePaymentSubmit = async (paymentMethod: any) => {
    setCurrentView('payment-processing');

    // Simulate payment processing
    await new Promise(resolve => setTimeout(resolve, 2000));

    // Simulate success/failure (90% success rate)
    const success = Math.random() > 0.1;

    if (success) {
      // Clear any existing tour state to ensure fresh start
      sessionStorage.removeItem('tavlo_guided_tour_state');
      console.log('💳 Payment successful - cleared tour state for fresh start');
      
      setVendorData(prevData => ({
        ...prevData,
        setupProgress: {
          ...prevData.setupProgress,
          subscription: true
        },
        status: 'activated'
      }));
      setCurrentView('payment-success');
    } else {
      setVendorData(prevData => ({
        ...prevData,
        paymentError: 'Card declined by issuer. Please try another payment method.'
      }));
      setCurrentView('payment-failed');
    }
  };

  // Handle payment retry
  const handlePaymentRetry = () => {
    setCurrentView('payment-method');
  };

  // Handle payment change method
  const handleChangePaymentMethod = () => {
    setCurrentView('payment-method');
  };

  // Handle back to plans from payment
  const handleBackToPlans = () => {
    setCurrentView('subscription-selection');
  };

  // Handle continue from payment success
  const handleContinueFromPaymentSuccess = () => {
    // Go directly to legal setup - first required step after subscription
    setCurrentView('setup-step1-legal');
  };

  // Handle Step 1 - Legal
  const handleLegalDataComplete = (data: any) => {
    setVendorData(prevData => ({
      ...prevData,
      legalData: data,
      setupProgress: {
        ...prevData.setupProgress,
        legalTax: true
      },
      status: determineVendorStatus({
        ...prevData.setupProgress,
        legalTax: true
      })
    }));
    setCurrentView('activated-dashboard');
  };

  // Handle checklist item click
  const handleChecklistItemClick = (itemId: string) => {
    switch (itemId) {
      case 'subscription':
        setCurrentView('subscription-selection');
        break;
      case 'legal-tax':
        setCurrentView('setup-step1-legal');
        break;
    }
  };

  // Handle go to live dashboard
  const handleGoToLiveDashboard = () => {
    setCurrentView('live-dashboard');
  };

  // Handle visibility change
  const handleVisibilityChange = (isLive: boolean) => {
    setVendorData(prevData => ({
      ...prevData,
      setupProgress: {
        ...prevData.setupProgress,
        isLiveAndDiscoverable: isLive
      }
    }));
  };

  console.log('🎯 Current View:', currentView, 'Vendor Status:', vendorData.status, 'Subscription:', vendorData.setupProgress.subscription);

  // Render based on current view
  switch (currentView) {
    case 'registration':
      return <VendorRegistration onComplete={handleRegistration} />;

    case 'demo-dashboard':
      return (
        <OnboardingDashboardWrapperV2
          vendorId={vendorData.id}
          vendorStatus={vendorData.status}
          setupProgress={vendorData.setupProgress}
          onActivateClick={handleActivateClick}
          onChecklistItemClick={handleChecklistItemClick}
          onVisibilityChange={handleVisibilityChange}
        />
      );

    case 'subscription-selection':
      return (
        <SubscriptionSelectionPage
          onSelectPlan={handlePlanSelection}
          onBackToDemo={() => setCurrentView('demo-dashboard')}
        />
      );

    case 'payment-method':
      return (
        <PaymentMethodPage
          planName={vendorData.selectedPlan?.name || 'Standard'}
          planPrice={vendorData.selectedPlan?.price || 199}
          currency="EUR"
          interval={vendorData.selectedPlan?.interval || 'month'}
          onPayment={handlePaymentSubmit}
          onBackToPlans={handleBackToPlans}
        />
      );

    case 'payment-processing':
      return <PaymentLoading />;

    case 'payment-failed':
      return (
        <PaymentFailed
          error={vendorData.paymentError}
          onRetry={handlePaymentRetry}
          onChangeMethod={handleChangePaymentMethod}
          onBackToPlans={handleBackToPlans}
        />
      );

    case 'payment-success':
      return (
        <PaymentSuccess
          planName={vendorData.selectedPlan?.name || 'Standard'}
          onContinueSetup={handleContinueFromPaymentSuccess}
        />
      );

    case 'activation-started':
      return (
        <ActivationStarted
          onStartActivation={() => setCurrentView('setup-step1-legal')}
          onBackToDashboard={() => setCurrentView('activated-dashboard')}
        />
      );

    case 'setup-step1-legal':
      return (
        <SetupStep1Legal
          onContinue={handleLegalDataComplete}
          initialData={vendorData.legalData}
        />
      );

    case 'activated-dashboard':
      console.log('🟢 Rendering activated dashboard with status:', vendorData.status);
      return (
        <OnboardingDashboardWrapperV2
          vendorId={vendorData.id}
          vendorStatus="activated"
          setupProgress={vendorData.setupProgress}
          onActivateClick={handleActivateClick}
          onChecklistItemClick={handleChecklistItemClick}
          onVisibilityChange={handleVisibilityChange}
        />
      );

    case 'live-dashboard':
      return (
        <OnboardingDashboardWrapperV2
          vendorId={vendorData.id}
          vendorStatus="live"
          setupProgress={vendorData.setupProgress}
          onActivateClick={handleActivateClick}
          onChecklistItemClick={handleChecklistItemClick}
          onVisibilityChange={handleVisibilityChange}
        />
      );

    default:
      return <div>Invalid view</div>;
  }
}