interface LogoVariantProps {
  variant?: 'full' | 'icon' | 'wordmark';
  theme?: 'light' | 'dark';
  colorScheme?: 'emerald' | 'terracotta';
  size?: number;
  concept: 'concept1' | 'concept2' | 'concept3' | 'concept4' | 'concept5' | 'concept6' | 'concept7';
}

export function TavloLogoVariant({ 
  variant = 'full', 
  theme = 'light',
  colorScheme = 'emerald',
  size = 120,
  concept = 'concept1'
}: LogoVariantProps) {
  
  // Color schemes
  const colors = {
    emerald: {
      light: '#0F5257',
      dark: '#1A8B94'
    },
    terracotta: {
      light: '#B85C3F',
      dark: '#D97B5F'
    }
  };

  const primaryColor = colors[colorScheme][theme];
  const iconSize = variant === 'full' ? size * 0.35 : size;

  // CONCEPT 1: Platform Line with Connection Point (Original)
  const Concept1Icon = () => (
    <svg 
      width={iconSize} 
      height={iconSize} 
      viewBox="0 0 48 48" 
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect x="4" y="4" width="40" height="40" rx="10" fill={primaryColor} />
      <line x1="14" y1="28" x2="34" y2="28" stroke="white" strokeWidth="2.5" strokeLinecap="round" />
      <circle cx="24" cy="20" r="2.5" fill="white" />
      <line x1="24" y1="22.5" x2="24" y2="28" stroke="white" strokeWidth="2.5" strokeLinecap="round" />
    </svg>
  );

  // CONCEPT 2: Abstract Table with Legs
  const Concept2Icon = () => (
    <svg 
      width={iconSize} 
      height={iconSize} 
      viewBox="0 0 48 48" 
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect x="4" y="4" width="40" height="40" rx="10" fill={primaryColor} />
      {/* Table surface */}
      <rect x="12" y="18" width="24" height="3" rx="1.5" fill="white" />
      {/* Table legs */}
      <rect x="14" y="21" width="2" height="9" rx="1" fill="white" />
      <rect x="32" y="21" width="2" height="9" rx="1" fill="white" />
      {/* Connection point */}
      <circle cx="24" cy="13" r="2" fill="white" />
    </svg>
  );

  // CONCEPT 3: Interlocking Circles (Connection/Hub)
  const Concept3Icon = () => (
    <svg 
      width={iconSize} 
      height={iconSize} 
      viewBox="0 0 48 48" 
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect x="4" y="4" width="40" height="40" rx="10" fill={primaryColor} />
      {/* Two interlocking circles representing connection */}
      <circle cx="19" cy="24" r="6" stroke="white" strokeWidth="2.5" fill="none" />
      <circle cx="29" cy="24" r="6" stroke="white" strokeWidth="2.5" fill="none" />
      {/* Center dot */}
      <circle cx="24" cy="24" r="2" fill="white" />
    </svg>
  );

  // CONCEPT 4: Grid/Network Pattern
  const Concept4Icon = () => (
    <svg 
      width={iconSize} 
      height={iconSize} 
      viewBox="0 0 48 48" 
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect x="4" y="4" width="40" height="40" rx="10" fill={primaryColor} />
      {/* Grid representing network/platform */}
      <line x1="16" y1="16" x2="32" y2="16" stroke="white" strokeWidth="2" strokeLinecap="round" />
      <line x1="16" y1="24" x2="32" y2="24" stroke="white" strokeWidth="2.5" strokeLinecap="round" />
      <line x1="16" y1="32" x2="32" y2="32" stroke="white" strokeWidth="2" strokeLinecap="round" />
      {/* Vertical accent */}
      <circle cx="24" cy="24" r="2.5" fill="white" />
    </svg>
  );

  // CONCEPT 5: Rounded Square Frame with Platform
  const Concept5Icon = () => (
    <svg 
      width={iconSize} 
      height={iconSize} 
      viewBox="0 0 48 48" 
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect x="4" y="4" width="40" height="40" rx="10" fill={primaryColor} />
      {/* Frame/window representing platform interface */}
      <rect x="14" y="14" width="20" height="20" rx="3" stroke="white" strokeWidth="2.5" fill="none" />
      {/* Platform line inside */}
      <line x1="18" y1="28" x2="30" y2="28" stroke="white" strokeWidth="2" strokeLinecap="round" />
    </svg>
  );

  // CONCEPT 6: Letter T Abstraction (TAVLO/Table)
  const Concept6Icon = () => (
    <svg 
      width={iconSize} 
      height={iconSize} 
      viewBox="0 0 48 48" 
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect x="4" y="4" width="40" height="40" rx="10" fill={primaryColor} />
      {/* Modern T shape */}
      <rect x="16" y="16" width="16" height="3" rx="1.5" fill="white" />
      <rect x="22.5" y="16" width="3" height="16" rx="1.5" fill="white" />
      {/* Connection dots */}
      <circle cx="19" cy="17.5" r="1.5" fill="white" />
      <circle cx="29" cy="17.5" r="1.5" fill="white" />
    </svg>
  );

  // CONCEPT 7: Minimalist Dots & Line (Ultra Simple)
  const Concept7Icon = () => (
    <svg 
      width={iconSize} 
      height={iconSize} 
      viewBox="0 0 48 48" 
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect x="4" y="4" width="40" height="40" rx="10" fill={primaryColor} />
      {/* Three dots representing people/connections */}
      <circle cx="16" cy="20" r="2.5" fill="white" />
      <circle cx="24" cy="20" r="2.5" fill="white" />
      <circle cx="32" cy="20" r="2.5" fill="white" />
      {/* Shared platform beneath */}
      <rect x="14" y="26" width="20" height="3" rx="1.5" fill="white" />
    </svg>
  );

  // Render the appropriate concept
  const renderIcon = () => {
    switch (concept) {
      case 'concept1': return <Concept1Icon />;
      case 'concept2': return <Concept2Icon />;
      case 'concept3': return <Concept3Icon />;
      case 'concept4': return <Concept4Icon />;
      case 'concept5': return <Concept5Icon />;
      case 'concept6': return <Concept6Icon />;
      case 'concept7': return <Concept7Icon />;
      default: return <Concept1Icon />;
    }
  };

  // Wordmark component
  const Wordmark = ({ standalone = false }: { standalone?: boolean }) => (
    <div 
      className="flex items-center"
      style={{ 
        color: primaryColor,
        fontSize: standalone ? size * 0.4 : size * 0.25,
        fontFamily: 'system-ui, -apple-system, sans-serif',
        fontWeight: 500,
        letterSpacing: '0.05em',
        lineHeight: 1
      }}
    >
      tavlo
    </div>
  );

  if (variant === 'icon') {
    return renderIcon();
  }

  if (variant === 'wordmark') {
    return <Wordmark standalone />;
  }

  // Full logo: icon + wordmark
  return (
    <div className="flex items-center gap-3">
      {renderIcon()}
      <Wordmark />
    </div>
  );
}
