import { createContext, useContext, useState, useEffect, ReactNode } from 'react';

interface AccessibilitySettings {
  largeText: boolean;
  highContrast: boolean;
  simpleLayout: boolean;
}

interface AccessibilityContextType {
  settings: AccessibilitySettings;
  toggleLargeText: () => void;
  toggleHighContrast: () => void;
  toggleSimpleLayout: () => void;
  resetSettings: () => void;
}

const AccessibilityContext = createContext<AccessibilityContextType | undefined>(undefined);

const defaultSettings: AccessibilitySettings = {
  largeText: false,
  highContrast: false,
  simpleLayout: false
};

export function AccessibilityProvider({ children }: { children: ReactNode }) {
  const [settings, setSettings] = useState<AccessibilitySettings>(defaultSettings);

  // Load settings from localStorage on mount
  useEffect(() => {
    try {
      const saved = localStorage.getItem('tavlo-accessibility');
      if (saved) {
        setSettings(JSON.parse(saved));
      }
    } catch (error) {
      console.error('Failed to load accessibility settings:', error);
    }
  }, []);

  // Save to localStorage whenever settings change
  useEffect(() => {
    try {
      localStorage.setItem('tavlo-accessibility', JSON.stringify(settings));
      
      // Apply body classes for global accessibility features
      if (settings.largeText) {
        document.body.classList.add('accessibility-large-text');
      } else {
        document.body.classList.remove('accessibility-large-text');
      }
      
      if (settings.highContrast) {
        document.body.classList.add('accessibility-high-contrast');
      } else {
        document.body.classList.remove('accessibility-high-contrast');
      }
    } catch (error) {
      console.error('Failed to save accessibility settings:', error);
    }
  }, [settings]);

  const toggleLargeText = () => {
    setSettings(prev => ({ ...prev, largeText: !prev.largeText }));
  };

  const toggleHighContrast = () => {
    setSettings(prev => ({ ...prev, highContrast: !prev.highContrast }));
  };

  const toggleSimpleLayout = () => {
    setSettings(prev => ({ ...prev, simpleLayout: !prev.simpleLayout }));
  };

  const resetSettings = () => {
    setSettings(defaultSettings);
  };

  return (
    <AccessibilityContext.Provider
      value={{
        settings,
        toggleLargeText,
        toggleHighContrast,
        toggleSimpleLayout,
        resetSettings
      }}
    >
      {children}
    </AccessibilityContext.Provider>
  );
}

export function useAccessibility() {
  const context = useContext(AccessibilityContext);
  if (!context) {
    throw new Error('useAccessibility must be used within AccessibilityProvider');
  }
  return context;
}
