import { Palette, Menu, Package, QrCode, CreditCard, Rocket } from 'lucide-react';
import { GuidedTourNavigationBox } from './GuidedTourNavigationBox';
import { GuidedTourInstructionHints } from './GuidedTourInstructionHints';
import type { TourStep, TourPhase } from './GuidedTourState';
import { TOUR_STEPS, OPTIONAL_STEPS } from './GuidedTourState';

interface GuidedTourManagerV2Props {
  currentStep: TourStep | null;
  currentPhase: TourPhase;
  currentScreen: string;
  onNavigate: (destination: string) => void;
  onAdvanceToInstruction: (step: TourStep) => void;
  onCompleteStep: (step: TourStep) => void;
  onSkipStep: (step: TourStep) => void;
  onEndTour: () => void;
}

const STEP_CONFIG = {
  'appearance': {
    title: 'Customize how your restaurant looks',
    description: 'Upload your logo, cover image, and choose your brand colors to make your restaurant stand out.',
    icon: Palette,
    iconColor: 'text-purple-600',
    iconBgColor: 'bg-purple-100',
    ctaText: 'Go to Appearance',
    targetScreen: 'settings',
    navigationDestination: 'settings'
  },
  'menu': {
    title: 'Add your menu',
    description: 'Add your menu so customers know what you offer. You can add items manually or upload your entire menu using an Excel file.',
    icon: Menu,
    iconColor: 'text-orange-600',
    iconBgColor: 'bg-orange-100',
    ctaText: 'Manage menu',
    targetScreen: 'menu',
    navigationDestination: 'menu'
  },
  'inventory': {
    title: 'Track ingredients and stock',
    description: 'Track ingredients and automatically mark items as unavailable when stock runs out.',
    icon: Package,
    iconColor: 'text-blue-600',
    iconBgColor: 'bg-blue-100',
    ctaText: 'Set up inventory',
    targetScreen: 'inventory',
    navigationDestination: 'inventory'
  },
  'qr-codes': {
    title: 'Generate QR codes for tables',
    description: 'Generate QR codes for tables so customers can scan and order. QR codes activate only when you go live.',
    icon: QrCode,
    iconColor: 'text-indigo-600',
    iconBgColor: 'bg-indigo-100',
    ctaText: 'Manage QR codes',
    targetScreen: 'qr-codes',
    navigationDestination: 'qr-codes'
  },
  'payments': {
    title: 'Enable online payments',
    description: 'Enable online payments if you want customers to pay digitally. You can also accept payments at the restaurant only.',
    icon: CreditCard,
    iconColor: 'text-green-600',
    iconBgColor: 'bg-green-100',
    ctaText: 'Set up payments',
    targetScreen: 'billing',
    navigationDestination: 'billing'
  },
  'go-live': {
    title: 'Make your restaurant live',
    description: 'Enable visibility to make your restaurant discoverable on Tavlo. You can toggle this anytime in Restaurant Profile settings.',
    icon: Rocket,
    iconColor: 'text-red-600',
    iconBgColor: 'bg-red-100',
    ctaText: 'Go to Restaurant Profile',
    targetScreen: 'settings',
    navigationDestination: 'settings'
  }
};

export function GuidedTourManagerV2({
  currentStep,
  currentPhase,
  currentScreen,
  onNavigate,
  onAdvanceToInstruction,
  onCompleteStep,
  onSkipStep,
  onEndTour
}: GuidedTourManagerV2Props) {
  if (!currentStep) return null;

  const config = STEP_CONFIG[currentStep];
  const stepNumber = TOUR_STEPS.indexOf(currentStep) + 1;
  const totalSteps = TOUR_STEPS.length;
  const isOptional = OPTIONAL_STEPS.includes(currentStep);

  // Phase A: Navigation Box
  if (currentPhase === 'navigation') {
    return (
      <GuidedTourNavigationBox
        step={currentStep}
        title={config.title}
        description={config.description}
        icon={config.icon}
        iconColor={config.iconColor}
        iconBgColor={config.iconBgColor}
        ctaText={config.ctaText}
        onNavigate={() => {
          onNavigate(config.navigationDestination);
          // Small delay to ensure navigation completes before phase change
          setTimeout(() => {
            onAdvanceToInstruction(currentStep);
          }, 100);
        }}
        onSkip={() => onSkipStep(currentStep)}
        onEndTour={onEndTour}
        isOptional={isOptional}
        stepNumber={stepNumber}
        totalSteps={totalSteps}
      />
    );
  }

  // Phase B: Instruction Hints
  // Only show hints when on the correct screen
  if (currentPhase === 'instruction' && currentScreen === config.targetScreen) {
    return (
      <GuidedTourInstructionHints
        step={currentStep}
        onComplete={() => onCompleteStep(currentStep)}
        onEndTour={onEndTour}
      />
    );
  }

  return null;
}