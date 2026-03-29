import { useState, useEffect } from 'react';
import { Copy, Check } from 'lucide-react';
import { toast } from 'sonner@2.0.3';

interface SessionPinIndicatorProps {
  pin: string;
  accentColor?: string;
  showPulseOnMount?: boolean;
}

/**
 * Session PIN Indicator
 * 
 * Displays a fixed, always-visible 4-digit PIN in the top-right corner
 * for table/session identification. Allows tap-to-copy functionality.
 */
export function SessionPinIndicator({ 
  pin, 
  accentColor = '#f59e0b',
  showPulseOnMount = true 
}: SessionPinIndicatorProps) {
  const [copied, setCopied] = useState(false);
  const [showPulse, setShowPulse] = useState(showPulseOnMount);

  // Disable pulse animation after 2 seconds
  useEffect(() => {
    if (showPulseOnMount) {
      const timer = setTimeout(() => setShowPulse(false), 2000);
      return () => clearTimeout(timer);
    }
  }, [showPulseOnMount]);

  // Copy PIN to clipboard
  const handleCopyPin = () => {
    if (!pin) return;

    // Try modern Clipboard API first
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(pin)
        .then(() => {
          setCopied(true);
          toast.success('PIN copied to clipboard');
          setTimeout(() => setCopied(false), 2000);
        })
        .catch(() => {
          fallbackCopyTextToClipboard(pin);
        });
    } else {
      fallbackCopyTextToClipboard(pin);
    }
  };

  // Fallback copy method using execCommand
  const fallbackCopyTextToClipboard = (text: string) => {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.width = '2em';
    textArea.style.height = '2em';
    textArea.style.padding = '0';
    textArea.style.border = 'none';
    textArea.style.outline = 'none';
    textArea.style.boxShadow = 'none';
    textArea.style.background = 'transparent';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      const successful = document.execCommand('copy');
      if (successful) {
        setCopied(true);
        toast.success('PIN copied to clipboard');
        setTimeout(() => setCopied(false), 2000);
      }
    } catch (err) {
      console.error('Copy failed:', err);
    }

    document.body.removeChild(textArea);
  };

  if (!pin || pin.length !== 4) return null;

  return (
    <button
      onClick={handleCopyPin}
      className={`
        relative inline-flex items-center gap-2 
        px-3 py-1.5 
        rounded-full 
        bg-white
        border-2
        shadow-sm
        transition-all duration-200
        hover:shadow-md
        active:scale-95
        ${showPulse ? 'animate-pulse-subtle' : ''}
      `}
      style={{ 
        borderColor: copied ? '#10b981' : `${accentColor}40`,
        backgroundColor: copied ? '#f0fdf4' : 'white'
      }}
      title="Tap to copy PIN"
      aria-label={`Table PIN ${pin}. Tap to copy.`}
    >
      {/* PIN Label */}
      <span 
        className="text-xs font-semibold uppercase tracking-wider"
        style={{ color: copied ? '#10b981' : '#6b7280' }}
      >
        PIN
      </span>
      
      {/* Separator */}
      <div 
        className="w-0.5 h-4 rounded-full"
        style={{ backgroundColor: copied ? '#10b981' : '#d1d5db' }}
      />
      
      {/* PIN Digits - Monospaced */}
      <span 
        className="text-base font-bold tracking-widest"
        style={{ 
          fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
          color: copied ? '#10b981' : '#1f2937'
        }}
      >
        {pin}
      </span>
      
      {/* Copy Icon */}
      <div className="ml-0.5">
        {copied ? (
          <Check className="w-3.5 h-3.5 text-green-600" strokeWidth={3} />
        ) : (
          <Copy className="w-3.5 h-3.5 text-gray-400" strokeWidth={2} />
        )}
      </div>

      {/* Pulse Ring - First Load Only */}
      {showPulse && (
        <span 
          className="absolute inset-0 rounded-full animate-ping opacity-75"
          style={{ 
            border: `2px solid ${accentColor}`,
            animationDuration: '2s',
            animationIterationCount: '3'
          }}
        />
      )}

      <style jsx>{`
        @keyframes pulse-subtle {
          0%, 100% {
            transform: scale(1);
            opacity: 1;
          }
          50% {
            transform: scale(1.03);
            opacity: 0.95;
          }
        }
        .animate-pulse-subtle {
          animation: pulse-subtle 1.5s ease-in-out 2;
        }
      `}</style>
    </button>
  );
}
