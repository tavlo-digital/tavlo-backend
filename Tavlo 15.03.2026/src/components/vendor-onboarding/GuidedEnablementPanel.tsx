import { useState } from 'react';
import { CheckCircle, ChevronRight, X, Palette, UtensilsCrossed, Package, QrCode, CreditCard } from 'lucide-react';
import { Button } from '../ui/button';

interface SetupStep {
  id: string;
  title: string;
  description: string;
  icon: any;
  targetView: string;
  helperText?: string;
  optional?: boolean;
  completed?: boolean;
}

interface GuidedEnablementPanelProps {
  onNavigate: (view: string) => void;
  setupProgress: {
    appearance?: boolean;
    menu?: boolean;
    inventory?: boolean;
    tablesQR?: boolean;
    payments?: boolean;
  };
}

export function GuidedEnablementPanel({ onNavigate, setupProgress }: GuidedEnablementPanelProps) {
  const [skippedSteps, setSkippedSteps] = useState<string[]>([]);

  const steps: SetupStep[] = [
    {
      id: 'appearance',
      title: 'Appearance & Branding',
      description: 'Customize your restaurant\'s look with logo, colors, and theme',
      icon: Palette,
      targetView: 'settings',
      completed: setupProgress.appearance,
      optional: false
    },
    {
      id: 'menu',
      title: 'Menu Management',
      description: 'Add your menu items or upload from Excel',
      icon: UtensilsCrossed,
      targetView: 'menu',
      helperText: 'You can also bulk upload menu items using our Excel template',
      completed: setupProgress.menu,
      optional: false
    },
    {
      id: 'inventory',
      title: 'Inventory',
      description: 'Track stock levels and ingredient usage',
      icon: Package,
      targetView: 'inventory',
      completed: setupProgress.inventory,
      optional: true
    },
    {
      id: 'tables-qr',
      title: 'Tables & QR Codes',
      description: 'Generate QR codes for table ordering',
      icon: QrCode,
      targetView: 'qr-codes',
      completed: setupProgress.tablesQR,
      optional: false
    },
    {
      id: 'payments',
      title: 'Online Payments',
      description: 'Enable card payments for online orders',
      icon: CreditCard,
      targetView: 'settings',
      completed: setupProgress.payments,
      optional: true
    }
  ];

  const handleSkip = (stepId: string) => {
    setSkippedSteps([...skippedSteps, stepId]);
  };

  const visibleSteps = steps.filter(step => !skippedSteps.includes(step.id));

  return (
    <div className="bg-white rounded-xl border border-gray-200 p-6">
      <h2 className="text-xl mb-1">Set up your restaurant (recommended)</h2>
      <p className="text-sm text-gray-600 mb-6">
        Configure these settings to make the most of Tavlo. You can complete them now or come back later.
      </p>

      <div className="space-y-3">
        {visibleSteps.map((step) => {
          const Icon = step.icon;
          
          return (
            <div 
              key={step.id}
              className={`border rounded-lg p-4 transition-all ${
                step.completed 
                  ? 'border-emerald-200 bg-emerald-50' 
                  : 'border-gray-200 hover:border-orange-200 hover:bg-orange-50/30'
              }`}
            >
              <div className="flex items-start gap-4">
                {/* Icon */}
                <div className={`w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ${
                  step.completed 
                    ? 'bg-emerald-100' 
                    : 'bg-gray-100'
                }`}>
                  {step.completed ? (
                    <CheckCircle className="w-5 h-5 text-emerald-600" />
                  ) : (
                    <Icon className="w-5 h-5 text-gray-600" />
                  )}
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-start justify-between gap-2 mb-1">
                    <h3 className="font-medium text-gray-900">
                      {step.title}
                      {step.optional && (
                        <span className="ml-2 text-xs text-gray-500 font-normal">(Optional)</span>
                      )}
                    </h3>
                  </div>
                  <p className="text-sm text-gray-600 mb-3">
                    {step.description}
                  </p>
                  {step.helperText && !step.completed && (
                    <div className="bg-blue-50 border border-blue-200 rounded p-2 mb-3">
                      <p className="text-xs text-blue-900">
                        💡 {step.helperText}
                      </p>
                    </div>
                  )}

                  {/* Actions */}
                  {!step.completed && (
                    <div className="flex items-center gap-2">
                      <Button
                        onClick={() => onNavigate(step.targetView)}
                        className="bg-orange-600 hover:bg-orange-700 text-white text-sm h-8"
                      >
                        {step.id === 'appearance' && 'Customize appearance'}
                        {step.id === 'menu' && 'Add menu items'}
                        {step.id === 'inventory' && 'Set up inventory'}
                        {step.id === 'tables-qr' && 'Generate QR codes'}
                        {step.id === 'payments' && 'Configure payments'}
                        <ChevronRight className="w-4 h-4 ml-1" />
                      </Button>
                      {step.optional && (
                        <button
                          onClick={() => handleSkip(step.id)}
                          className="text-sm text-gray-600 hover:text-gray-900"
                        >
                          Skip for now
                        </button>
                      )}
                    </div>
                  )}
                  {step.completed && (
                    <div className="flex items-center gap-2">
                      <span className="text-sm text-emerald-700 font-medium">✓ Completed</span>
                      <button
                        onClick={() => onNavigate(step.targetView)}
                        className="text-sm text-gray-600 hover:text-gray-900"
                      >
                        Edit
                      </button>
                    </div>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {skippedSteps.length > 0 && (
        <button
          onClick={() => setSkippedSteps([])}
          className="mt-4 text-sm text-gray-600 hover:text-gray-900"
        >
          Show {skippedSteps.length} skipped step{skippedSteps.length > 1 ? 's' : ''}
        </button>
      )}
    </div>
  );
}
