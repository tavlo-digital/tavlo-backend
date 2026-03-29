import { useState } from 'react';
import { CheckCircle2, Circle, Lock, ChevronRight, X, Building2, Menu, CreditCard, FileText, QrCode } from 'lucide-react';
import { Button } from '../ui/button';
import { Card } from '../ui/card';

interface ChecklistItem {
  id: string;
  title: string;
  description: string;
  required: boolean;
  completed: boolean;
  icon: any;
}

interface ActivationChecklistProps {
  isOpen: boolean;
  onClose: () => void;
  onItemClick: (itemId: string) => void;
  items: ChecklistItem[];
  vendorStatus: 'demo' | 'activated' | 'live';
}

export function ActivationChecklist({ 
  isOpen, 
  onClose, 
  onItemClick, 
  items,
  vendorStatus 
}: ActivationChecklistProps) {
  if (!isOpen) return null;

  const requiredItems = items.filter(item => item.required);
  const optionalItems = items.filter(item => !item.required);
  const completedRequired = requiredItems.filter(item => item.completed).length;
  const totalRequired = requiredItems.length;
  const progressPercent = (completedRequired / totalRequired) * 100;

  return (
    <>
      {/* Overlay */}
      <div 
        className="fixed inset-0 bg-black/50 z-40"
        onClick={onClose}
      />

      {/* Checklist Panel */}
      <div className="fixed right-0 top-0 bottom-0 w-full max-w-2xl bg-white shadow-2xl z-50 flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-2xl text-gray-900 mb-1">Activation Checklist</h2>
            <p className="text-sm text-gray-600">
              Complete the required steps to activate your restaurant
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="w-5 h-5 text-gray-500" />
          </button>
        </div>

        {/* Progress */}
        <div className="p-6 border-b border-gray-200 bg-gray-50">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-700">Setup Progress</span>
            <span className="text-sm text-gray-900">
              {completedRequired} of {totalRequired} required
            </span>
          </div>
          <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
            <div 
              className="h-full bg-emerald-500 transition-all duration-500"
              style={{ width: `${progressPercent}%` }}
            />
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          {/* Required Items */}
          <div>
            <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-3">
              Required Steps
            </h3>
            <div className="space-y-2">
              {requiredItems.map((item) => (
                <ChecklistItemCard
                  key={item.id}
                  item={item}
                  onClick={() => onItemClick(item.id)}
                />
              ))}
            </div>
          </div>

          {/* Optional Items */}
          {optionalItems.length > 0 && (
            <div>
              <h3 className="text-sm uppercase tracking-wider text-gray-500 mb-3">
                Optional Steps
              </h3>
              <div className="space-y-2">
                {optionalItems.map((item) => (
                  <ChecklistItemCard
                    key={item.id}
                    item={item}
                    onClick={() => onItemClick(item.id)}
                  />
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="p-6 border-t border-gray-200 bg-gray-50">
          {completedRequired === totalRequired ? (
            <div className="text-center">
              <div className="inline-flex items-center justify-center w-12 h-12 bg-emerald-100 rounded-full mb-3">
                <CheckCircle2 className="w-6 h-6 text-emerald-600" />
              </div>
              <p className="text-sm text-gray-600 mb-4">
                All required steps completed! Your restaurant is ready to go live.
              </p>
              <Button className="w-full bg-emerald-600 hover:bg-emerald-700 text-white">
                Go Live Now
              </Button>
            </div>
          ) : (
            <p className="text-sm text-gray-600 text-center">
              Complete all required steps to activate your restaurant
            </p>
          )}
        </div>
      </div>
    </>
  );
}

function ChecklistItemCard({ item, onClick }: { item: ChecklistItem; onClick: () => void }) {
  const Icon = item.icon;
  
  return (
    <button
      onClick={onClick}
      className={`w-full text-left p-4 rounded-lg border-2 transition-all hover:shadow-md ${
        item.completed 
          ? 'bg-emerald-50 border-emerald-200' 
          : 'bg-white border-gray-200 hover:border-gray-300'
      }`}
    >
      <div className="flex items-start gap-4">
        {/* Icon */}
        <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${
          item.completed ? 'bg-emerald-100' : 'bg-gray-100'
        }`}>
          <Icon className={`w-5 h-5 ${
            item.completed ? 'text-emerald-600' : 'text-gray-600'
          }`} />
        </div>

        {/* Content */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            <h4 className={`font-medium ${
              item.completed ? 'text-emerald-900' : 'text-gray-900'
            }`}>
              {item.title}
            </h4>
            {item.required && !item.completed && (
              <span className="text-xs px-2 py-0.5 bg-orange-100 text-orange-700 rounded">
                Required
              </span>
            )}
          </div>
          <p className="text-sm text-gray-600">
            {item.description}
          </p>
        </div>

        {/* Status */}
        <div className="shrink-0">
          {item.completed ? (
            <CheckCircle2 className="w-6 h-6 text-emerald-600" />
          ) : (
            <ChevronRight className="w-6 h-6 text-gray-400" />
          )}
        </div>
      </div>
    </button>
  );
}

// Helper function to create default checklist items
export function createDefaultChecklistItems(setupProgress: any): ChecklistItem[] {
  return [
    {
      id: 'subscription',
      title: 'Subscription',
      description: 'Subscribe to unlock all features',
      required: true,
      completed: setupProgress?.subscription || false,
      icon: CreditCard
    },
    {
      id: 'legal-tax',
      title: 'Legal & Business Info',
      description: 'VAT number and business registration (required after subscription)',
      required: true,
      completed: setupProgress?.legalTax || false,
      icon: FileText
    },
    {
      id: 'menu-setup',
      title: 'Menu Setup',
      description: 'At least 1 category and 1 item (required to go live)',
      required: true,
      completed: setupProgress?.menu || false,
      icon: Menu
    },
    {
      id: 'tables-qr',
      title: 'Tables & QR Codes',
      description: 'Optional – Add tables and generate QR codes',
      required: false,
      completed: setupProgress?.tablesQR || false,
      icon: QrCode
    },
    {
      id: 'restaurant-basics',
      title: 'Payment Methods',
      description: 'Optional – Set up online payments (default: pay at restaurant)',
      required: false,
      completed: setupProgress?.restaurantBasics || false,
      icon: Building2
    }
  ];
}