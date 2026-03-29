import { useState } from 'react';
import { X, Type, Contrast, LayoutGrid, Eye, RotateCcw } from 'lucide-react';
import { useAccessibility } from '../contexts/AccessibilityContext';
import { useLanguage } from '../contexts/LanguageContext';
import { Button } from './ui/button';
import { Switch } from './ui/switch';

interface AccessibilityMenuProps {
  onClose: () => void;
}

export function AccessibilityMenu({ onClose }: AccessibilityMenuProps) {
  const { settings, toggleLargeText, toggleHighContrast, toggleSimpleLayout, resetSettings } = useAccessibility();
  const { t } = useLanguage();

  return (
    <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Eye className="w-5 h-5" />
            <h2 className="text-lg font-semibold">Accessibility</h2>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
          <p className="text-sm text-gray-600">
            Customize your viewing experience for easier navigation and better readability.
          </p>

          {/* Large Text */}
          <div className="flex items-start justify-between gap-4">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                <Type className="w-5 h-5 text-gray-700" />
                <h3 className="font-semibold">Large Text</h3>
              </div>
              <p className="text-sm text-gray-600">
                Increase text size throughout the menu for easier reading
              </p>
            </div>
            <Switch
              checked={settings.largeText}
              onCheckedChange={toggleLargeText}
            />
          </div>

          {/* High Contrast */}
          <div className="flex items-start justify-between gap-4">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                <Contrast className="w-5 h-5 text-gray-700" />
                <h3 className="font-semibold">High Contrast</h3>
              </div>
              <p className="text-sm text-gray-600">
                Increase contrast for better visibility and reduced eye strain
              </p>
            </div>
            <Switch
              checked={settings.highContrast}
              onCheckedChange={toggleHighContrast}
            />
          </div>

          {/* Simple Layout */}
          <div className="flex items-start justify-between gap-4">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                <LayoutGrid className="w-5 h-5 text-gray-700" />
                <h3 className="font-semibold">Simple Layout</h3>
              </div>
              <p className="text-sm text-gray-600">
                Simplified interface with larger buttons and clearer spacing
              </p>
            </div>
            <Switch
              checked={settings.simpleLayout}
              onCheckedChange={toggleSimpleLayout}
            />
          </div>

          {/* Preview */}
          {(settings.largeText || settings.highContrast || settings.simpleLayout) && (
            <div className={`p-4 rounded-lg border-2 ${settings.highContrast ? 'bg-black text-white border-white' : 'bg-gray-50 border-gray-200'}`}>
              <p className={`${settings.largeText ? 'text-lg' : 'text-base'} mb-2`}>
                Preview
              </p>
              <p className={`${settings.largeText ? 'text-base' : 'text-sm'} ${settings.highContrast ? 'text-gray-200' : 'text-gray-600'}`}>
                This is how your menu will look with the current settings.
              </p>
            </div>
          )}

          {/* Reset Button */}
          {(settings.largeText || settings.highContrast || settings.simpleLayout) && (
            <Button
              onClick={resetSettings}
              variant="outline"
              className="w-full"
            >
              <RotateCcw className="w-4 h-4 mr-2" />
              Reset to Default
            </Button>
          )}
        </div>

        {/* Footer */}
        <div className="border-t px-6 py-4 bg-gray-50 rounded-b-2xl">
          <Button onClick={onClose} className="w-full">
            Done
          </Button>
        </div>
      </div>
    </div>
  );
}
