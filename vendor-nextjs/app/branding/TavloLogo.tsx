import React from 'react';

interface TavloLogoProps {
  variant?: 'full' | 'icon';
  size?: number;
  colorScheme?: 'black' | 'white' | 'color';
}

export function TavloLogo({ variant = 'full', size = 32, colorScheme = 'color' }: TavloLogoProps) {
  const color = colorScheme === 'white' ? '#ffffff' : colorScheme === 'black' ? '#1a1a1a' : '#f59e0b';
  const textColor = colorScheme === 'black' ? '#ffffff' : '#1a1a1a';

  if (variant === 'icon') {
    return (
      <svg width={size} height={size} viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="32" height="32" rx="8" fill={color} />
        <text x="50%" y="55%" dominantBaseline="middle" textAnchor="middle" fill={textColor} fontSize="18" fontWeight="bold">T</text>
      </svg>
    );
  }

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      <svg width={size} height={size} viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="32" height="32" rx="8" fill={color} />
        <text x="50%" y="55%" dominantBaseline="middle" textAnchor="middle" fill={textColor} fontSize="18" fontWeight="bold">T</text>
      </svg>
      <span style={{ fontSize: size * 0.6, fontWeight: 700, color }}>tavlo</span>
    </div>
  );
}
