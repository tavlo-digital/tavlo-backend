import logoImage from 'figma:asset/a41c5ed9cb37da642e00d6ad4d3424dd23759fa0.png';

interface TavloLogoProps {
  variant?: 'full' | 'icon' | 'wordmark';
  theme?: 'light' | 'dark';
  colorScheme?: 'emerald' | 'terracotta' | 'black' | 'white';
  size?: number;
}

export function TavloLogo({ 
  variant = 'full', 
  theme = 'light',
  colorScheme = 'black',
  size = 120 
}: TavloLogoProps) {
  
  // For icon variant, we'll crop to just the T+checkmark symbol
  // For full/wordmark, we'll show the complete logo
  
  const imageStyle: React.CSSProperties = {
    height: size,
    width: 'auto',
    objectFit: 'contain',
  };

  // Apply color filter based on colorScheme
  const getFilter = () => {
    if (colorScheme === 'white') {
      return 'brightness(0) invert(1)';
    }
    if (colorScheme === 'emerald') {
      if (theme === 'dark') {
        // #1A8B94 in filter format
        return 'brightness(0) saturate(100%) invert(44%) sepia(73%) saturate(429%) hue-rotate(134deg) brightness(96%) contrast(89%)';
      }
      // #0F5257 in filter format
      return 'brightness(0) saturate(100%) invert(24%) sepia(18%) saturate(1826%) hue-rotate(139deg) brightness(95%) contrast(94%)';
    }
    if (colorScheme === 'terracotta') {
      if (theme === 'dark') {
        // #D97B5F in filter format
        return 'brightness(0) saturate(100%) invert(68%) sepia(36%) saturate(761%) hue-rotate(327deg) brightness(92%) contrast(86%)';
      }
      // #B85C3F in filter format
      return 'brightness(0) saturate(100%) invert(39%) sepia(37%) saturate(838%) hue-rotate(334deg) brightness(91%) contrast(85%)';
    }
    // Default black
    return 'none';
  };

  if (variant === 'icon') {
    // For icon, show just the symbol part with cropping
    return (
      <div style={{ 
        width: size, 
        height: size, 
        overflow: 'hidden',
        position: 'relative'
      }}>
        <img 
          src={logoImage} 
          alt="TAVLO"
          style={{
            position: 'absolute',
            top: 0,
            left: 0,
            height: size * 1.8,
            width: 'auto',
            objectFit: 'contain',
            filter: getFilter()
          }}
        />
      </div>
    );
  }

  // Full logo or wordmark - show complete logo
  return (
    <img 
      src={logoImage} 
      alt="TAVLO"
      style={{
        ...imageStyle,
        filter: getFilter()
      }}
    />
  );
}
