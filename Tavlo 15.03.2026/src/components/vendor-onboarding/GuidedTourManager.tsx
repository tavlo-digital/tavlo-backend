import { useState, useEffect } from 'react';
import { Palette, Menu, Package, QrCode, CreditCard } from 'lucide-react';
import { GuidedTourHelperBox } from './GuidedTourHelperBox';

export type TourStep = 'appearance' | 'menu' | 'inventory' | 'qr-codes' | 'payments';

interface GuidedTourManagerProps {
  currentStep: TourStep | null;
  onNavigate: (destination: string) => void;
  onComplete: () => void;
  onSkipStep: () => void;
}

const TOUR_STEPS: TourStep[] = ['appearance', 'menu', 'inventory', 'qr-codes', 'payments'];

export function GuidedTourManager({
  currentStep,
  onNavigate,
  onComplete,
  onSkipStep
}: GuidedTourManagerProps) {
  if (!currentStep) return null;

  const currentStepIndex = TOUR_STEPS.indexOf(currentStep);
  const stepNumber = currentStepIndex + 1;
  const totalSteps = TOUR_STEPS.length;

  const handleNext = () => {
    if (currentStepIndex === TOUR_STEPS.length - 1) {
      // Last step
      onComplete();
    } else {
      onSkipStep();
    }
  };

  // Step 1: Appearance & Branding
  if (currentStep === 'appearance') {
    return (
      <div className="mb-6">
        <GuidedTourHelperBox
          title="Customize how your restaurant looks"
          description="Upload your logo, cover image, and choose your brand colors to make your restaurant stand out."
          icon={Palette}
          iconColor="text-purple-600"
          iconBgColor="bg-purple-100"
          ctaText="Go to Appearance"
          onCta={() => onNavigate('appearance')}
          onSkip={handleNext}
          stepNumber={stepNumber}
          totalSteps={totalSteps}
        />
      </div>
    );
  }

  // Step 2: Menu Management
  if (currentStep === 'menu') {
    return (
      <div className="mb-6">
        <GuidedTourHelperBox
          title="Add your menu"
          description="Add your menu so customers know what you offer. You can add items manually or upload your entire menu using an Excel file."
          secondaryNote="You can edit this anytime."
          icon={Menu}
          iconColor="text-orange-600"
          iconBgColor="bg-orange-100"
          ctaText="Manage menu"
          onCta={() => onNavigate('menu')}
          onSkip={handleNext}
          stepNumber={stepNumber}
          totalSteps={totalSteps}
        />
      </div>
    );
  }

  // Step 3: Inventory (Optional)
  if (currentStep === 'inventory') {
    return (
      <div className="mb-6">
        <GuidedTourHelperBox
          title="Track ingredients and stock"
          description="Track ingredients and automatically mark items as unavailable when stock runs out."
          icon={Package}
          iconColor="text-blue-600"
          iconBgColor="bg-blue-100"
          ctaText="Set up inventory"
          onCta={() => onNavigate('inventory')}
          onSkip={handleNext}
          isOptional={true}
          stepNumber={stepNumber}
          totalSteps={totalSteps}
        />
      </div>
    );
  }

  // Step 4: Tables & QR Codes
  if (currentStep === 'qr-codes') {
    return (
      <div className="mb-6">
        <GuidedTourHelperBox
          title="Generate QR codes for tables"
          description="Generate QR codes for tables so customers can scan and order. QR codes activate only when you go live."
          icon={QrCode}
          iconColor="text-indigo-600"
          iconBgColor="bg-indigo-100"
          ctaText="Manage QR codes"
          onCta={() => onNavigate('qr-codes')}
          onSkip={handleNext}
          stepNumber={stepNumber}
          totalSteps={totalSteps}
        />
      </div>
    );
  }

  // Step 5: Payments (Optional)
  if (currentStep === 'payments') {
    return (
      <div className="mb-6">
        <GuidedTourHelperBox
          title="Enable online payments"
          description="Enable online payments if you want customers to pay digitally. You can also accept payments at the restaurant only."
          icon={CreditCard}
          iconColor="text-green-600"
          iconBgColor="bg-green-100"
          ctaText="Set up payments"
          onCta={() => onNavigate('payments')}
          onSkip={handleNext}
          isOptional={true}
          stepNumber={stepNumber}
          totalSteps={totalSteps}
        />
      </div>
    );
  }

  return null;
}