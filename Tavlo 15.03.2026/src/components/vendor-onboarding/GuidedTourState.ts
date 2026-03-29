// Guided Tour State Management

export type TourStep = 'appearance' | 'menu' | 'inventory' | 'qr-codes' | 'payments' | 'go-live';
export type TourPhase = 'navigation' | 'instruction';
export type StepStatus = 'not_started' | 'in_progress' | 'completed' | 'skipped';

export interface TourState {
  isActive: boolean;
  currentStep: TourStep | null;
  currentPhase: TourPhase;
  stepStatuses: Record<TourStep, StepStatus>;
  completedAt: string | null;
}

export const TOUR_STEPS: TourStep[] = ['appearance', 'menu', 'inventory', 'qr-codes', 'payments', 'go-live'];
export const OPTIONAL_STEPS: TourStep[] = ['inventory', 'payments'];

const STORAGE_KEY = 'tavlo_guided_tour_state';

export function getInitialTourState(): TourState {
  const stored = sessionStorage.getItem(STORAGE_KEY);
  if (stored) {
    try {
      return JSON.parse(stored);
    } catch (e) {
      console.error('Failed to parse tour state:', e);
    }
  }
  
  return {
    isActive: false,
    currentStep: null,
    currentPhase: 'navigation',
    stepStatuses: {
      'appearance': 'not_started',
      'menu': 'not_started',
      'inventory': 'not_started',
      'qr-codes': 'not_started',
      'payments': 'not_started',
      'go-live': 'not_started'
    },
    completedAt: null
  };
}

export function saveTourState(state: TourState): void {
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

export function startTour(): TourState {
  const state: TourState = {
    isActive: true,
    currentStep: 'appearance',
    currentPhase: 'navigation',
    stepStatuses: {
      'appearance': 'not_started',
      'menu': 'not_started',
      'inventory': 'not_started',
      'qr-codes': 'not_started',
      'payments': 'not_started',
      'go-live': 'not_started'
    },
    completedAt: null
  };
  saveTourState(state);
  return state;
}

export function endTour(): TourState {
  const state = getInitialTourState();
  state.isActive = false;
  saveTourState(state);
  return state;
}

export function completeTour(): TourState {
  const state = getInitialTourState();
  state.isActive = false;
  state.completedAt = new Date().toISOString();
  state.currentStep = null;
  saveTourState(state);
  return state;
}

export function advanceToInstructionPhase(step: TourStep): TourState {
  const state = getInitialTourState();
  state.currentPhase = 'instruction';
  state.stepStatuses[step] = 'in_progress';
  saveTourState(state);
  return state;
}

export function completeStep(step: TourStep): TourState {
  const state = getInitialTourState();
  state.stepStatuses[step] = 'completed';
  
  // Find next step
  const currentIndex = TOUR_STEPS.indexOf(step);
  const nextStep = TOUR_STEPS[currentIndex + 1];
  
  if (nextStep) {
    state.currentStep = nextStep;
    state.currentPhase = 'navigation';
  } else {
    // Tour completed
    state.isActive = false;
    state.currentStep = null;
    state.completedAt = new Date().toISOString();
  }
  
  saveTourState(state);
  return state;
}

export function skipStep(step: TourStep): TourState {
  const state = getInitialTourState();
  state.stepStatuses[step] = 'skipped';
  
  // Find next step
  const currentIndex = TOUR_STEPS.indexOf(step);
  const nextStep = TOUR_STEPS[currentIndex + 1];
  
  if (nextStep) {
    state.currentStep = nextStep;
    state.currentPhase = 'navigation';
  } else {
    // Tour completed
    state.isActive = false;
    state.currentStep = null;
    state.completedAt = new Date().toISOString();
  }
  
  saveTourState(state);
  return state;
}

export function resumeTour(): TourState {
  const state = getInitialTourState();
  
  // Find first incomplete step
  const nextIncompleteStep = TOUR_STEPS.find(
    step => state.stepStatuses[step] === 'not_started' || state.stepStatuses[step] === 'in_progress'
  );
  
  if (nextIncompleteStep) {
    state.isActive = true;
    state.currentStep = nextIncompleteStep;
    state.currentPhase = state.stepStatuses[nextIncompleteStep] === 'in_progress' ? 'instruction' : 'navigation';
  } else {
    // All steps complete
    state.currentStep = TOUR_STEPS[0];
    state.currentPhase = 'navigation';
    state.isActive = true;
  }
  
  saveTourState(state);
  return state;
}

export function shouldShowEntryModal(): boolean {
  const stored = sessionStorage.getItem(STORAGE_KEY);
  // If nothing stored, show the modal
  if (!stored) return true;
  
  try {
    const state = JSON.parse(stored);
    // Show if tour never completed and is not currently active
    return !state.completedAt && !state.isActive;
  } catch (e) {
    // If parse fails, show the modal
    return true;
  }
}

export function markModalShown(): void {
  const state = getInitialTourState();
  saveTourState(state);
}