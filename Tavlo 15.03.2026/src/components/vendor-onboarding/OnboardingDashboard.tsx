import { CheckCircle2, Circle, Lock, AlertCircle } from 'lucide-react';
import { Button } from '../ui/button';

interface OnboardingStep {
  id: string;
  title: string;
  description: string;
  completed: boolean;
}

interface OnboardingDashboardProps {
  steps: OnboardingStep[];
  currentStep: number;
  isActive: boolean;
  onStepClick: (stepIndex: number) => void;
  onActivate: () => void;
}

export function OnboardingDashboard({
  steps,
  currentStep,
  isActive,
  onStepClick,
  onActivate
}: OnboardingDashboardProps) {
  const completedSteps = steps.filter(s => s.completed).length;
  const progress = (completedSteps / steps.length) * 100;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h1 className="text-3xl text-gray-900">Welcome to TAVLO</h1>
              <p className="text-gray-600 mt-1">Complete your restaurant setup</p>
            </div>
            <div className="flex items-center gap-3">
              {!isActive && (
                <div className="flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 rounded-lg">
                  <AlertCircle className="w-4 h-4 text-amber-600" />
                  <span className="text-sm text-amber-900">
                    {completedSteps === 0 ? 'Registered – Setup Incomplete' : 'Active – Setup in Progress'}
                  </span>
                </div>
              )}
              {isActive && (
                <div className="flex items-center gap-2 px-4 py-2 bg-emerald-50 border border-emerald-200 rounded-lg">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                  <span className="text-sm text-emerald-900">Active – Live</span>
                </div>
              )}
            </div>
          </div>

          {/* Progress Bar */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm text-gray-600">Setup progress</span>
              <span className="text-sm text-gray-900">{completedSteps} of {steps.length} completed</span>
            </div>
            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div
                className="h-full bg-gradient-to-r from-emerald-500 to-teal-600 transition-all duration-500"
                style={{ width: `${progress}%` }}
              />
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        {/* Status Banner */}
        {!isActive && (
          <div className="mb-8 p-6 bg-blue-50 border border-blue-200 rounded-xl">
            <div className="flex items-start gap-4">
              <Lock className="w-6 h-6 text-blue-600 mt-0.5 flex-shrink-0" />
              <div className="flex-1">
                <h3 className="text-lg text-blue-900 mb-1">Not live yet</h3>
                <p className="text-blue-800">
                  Orders and invoices are disabled until you activate your subscription.
                  Complete the setup steps below, then activate your restaurant to start accepting orders.
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Onboarding Steps */}
        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
          {steps.map((step, index) => (
            <button
              key={step.id}
              onClick={() => onStepClick(index)}
              className={`p-6 bg-white border-2 rounded-xl text-left transition-all hover:shadow-md ${
                currentStep === index
                  ? 'border-emerald-500 shadow-lg'
                  : step.completed
                  ? 'border-emerald-200'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <div className="flex items-start gap-4 mb-3">
                <div className="flex-shrink-0">
                  {step.completed ? (
                    <CheckCircle2 className="w-6 h-6 text-emerald-600" />
                  ) : (
                    <Circle className="w-6 h-6 text-gray-400" />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="text-xs text-gray-500">Step {index + 1}</span>
                    {step.completed && (
                      <span className="text-xs px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded">
                        Complete
                      </span>
                    )}
                  </div>
                  <h3 className="text-lg text-gray-900 mb-1">{step.title}</h3>
                  <p className="text-sm text-gray-600">{step.description}</p>
                </div>
              </div>
            </button>
          ))}
        </div>

        {/* Activation Button */}
        {completedSteps === steps.length && !isActive && (
          <div className="p-8 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl text-white text-center">
            <h2 className="text-2xl mb-3">You're almost live!</h2>
            <p className="text-emerald-50 mb-6 max-w-2xl mx-auto">
              All setup steps are complete. Activate your subscription to go live and start accepting orders.
            </p>
            <Button
              onClick={onActivate}
              className="bg-white text-emerald-700 hover:bg-emerald-50 px-8 py-3 text-lg"
            >
              Activate restaurant
            </Button>
          </div>
        )}

        {isActive && (
          <div className="p-8 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl text-white text-center">
            <CheckCircle2 className="w-16 h-16 mx-auto mb-4" />
            <h2 className="text-2xl mb-3">Your restaurant is live! 🎉</h2>
            <p className="text-emerald-50 mb-6 max-w-2xl mx-auto">
              You can now accept orders, download QR codes, and manage your restaurant.
            </p>
            <div className="flex gap-3 justify-center">
              <Button className="bg-white text-emerald-700 hover:bg-emerald-50">
                Download QR codes
              </Button>
              <Button variant="outline" className="border-white text-white hover:bg-white/10">
                View live menu
              </Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}