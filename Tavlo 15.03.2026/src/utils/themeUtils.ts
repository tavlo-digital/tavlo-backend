export interface ThemeSettings {
  menuTheme: 'classic' | 'modern' | 'minimal' | 'vibrant';
  primaryColor: string;
  accentColor: string;
  menuLayout: 'grid' | 'list';
}

export const defaultTheme: ThemeSettings = {
  menuTheme: 'classic',
  primaryColor: '#1a1a1a',
  accentColor: '#f59e0b',
  menuLayout: 'grid'
};

export function getThemeStyles(theme: ThemeSettings) {
  const { menuTheme, primaryColor, accentColor } = theme;
  
  const baseStyles = {
    primaryColor,
    accentColor
  };

  switch (menuTheme) {
    case 'classic':
      return {
        ...baseStyles,
        backgroundColor: '#ffffff',
        textColor: '#1a1a1a',
        cardBackground: '#ffffff',
        cardBorder: '#e5e7eb',
        headerBackground: primaryColor,
        headerText: '#ffffff',
        buttonStyle: 'solid',
        borderRadius: '0.5rem',
        fontFamily: 'system-ui'
      };
    
    case 'modern':
      return {
        ...baseStyles,
        backgroundColor: '#f8fafc',
        textColor: '#0f172a',
        cardBackground: '#ffffff',
        cardBorder: 'transparent',
        headerBackground: `linear-gradient(135deg, ${primaryColor}, ${accentColor})`,
        headerText: '#ffffff',
        buttonStyle: 'gradient',
        borderRadius: '1rem',
        fontFamily: 'system-ui',
        shadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)'
      };
    
    case 'minimal':
      return {
        ...baseStyles,
        backgroundColor: '#ffffff',
        textColor: '#333333',
        cardBackground: '#fafafa',
        cardBorder: '#f0f0f0',
        headerBackground: '#ffffff',
        headerText: primaryColor,
        buttonStyle: 'outline',
        borderRadius: '0.25rem',
        fontFamily: 'system-ui',
        minimalBorders: true
      };
    
    case 'vibrant':
      return {
        ...baseStyles,
        backgroundColor: '#fef3c7',
        textColor: '#78350f',
        cardBackground: '#ffffff',
        cardBorder: accentColor,
        headerBackground: `linear-gradient(to right, ${primaryColor}, ${accentColor})`,
        headerText: '#ffffff',
        buttonStyle: 'solid',
        borderRadius: '1.5rem',
        fontFamily: 'system-ui',
        vibrantAccents: true
      };
    
    default:
      return baseStyles;
  }
}

export function applyThemeToElement(element: HTMLElement, theme: ThemeSettings) {
  const styles = getThemeStyles(theme);
  
  // Apply CSS custom properties
  element.style.setProperty('--theme-primary', styles.primaryColor);
  element.style.setProperty('--theme-accent', styles.accentColor);
  
  if (styles.backgroundColor) {
    element.style.setProperty('--theme-background', styles.backgroundColor);
  }
  
  if (styles.textColor) {
    element.style.setProperty('--theme-text', styles.textColor);
  }
  
  if (styles.cardBackground) {
    element.style.setProperty('--theme-card-bg', styles.cardBackground);
  }
  
  if (styles.borderRadius) {
    element.style.setProperty('--theme-radius', styles.borderRadius);
  }
}

export function getButtonClassName(theme: ThemeSettings, variant: 'primary' | 'secondary' = 'primary'): string {
  const styles = getThemeStyles(theme);
  
  const baseClasses = 'px-4 py-2 rounded transition-all duration-200';
  
  if (variant === 'primary') {
    switch (styles.buttonStyle) {
      case 'gradient':
        return `${baseClasses} bg-gradient-to-r text-white font-medium shadow-lg hover:shadow-xl`;
      case 'outline':
        return `${baseClasses} border-2 bg-transparent font-medium hover:bg-gray-50`;
      default:
        return `${baseClasses} text-white font-medium shadow-sm hover:opacity-90`;
    }
  } else {
    return `${baseClasses} bg-gray-100 text-gray-900 hover:bg-gray-200`;
  }
}

export function getCardClassName(theme: ThemeSettings): string {
  const styles = getThemeStyles(theme);
  
  const baseClasses = 'bg-white overflow-hidden transition-all duration-200';
  
  if (styles.minimalBorders) {
    return `${baseClasses} border hover:shadow-md`;
  } else if (styles.vibrantAccents) {
    return `${baseClasses} border-2 shadow-sm hover:shadow-lg`;
  } else if (styles.shadow) {
    return `${baseClasses} shadow-lg hover:shadow-xl`;
  } else {
    return `${baseClasses} border shadow-sm hover:shadow-md`;
  }
}
