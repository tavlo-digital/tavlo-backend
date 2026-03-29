import { useState } from 'react';
import { ShoppingBag, Clock, Bell } from 'lucide-react';
import { toast } from 'sonner@2.0.3';

interface BottomSystemBarProps {
  sessionPin?: string;
  basketCount?: number;
  pendingOrdersCount?: number;
  accentColor?: string;
  onViewBasket?: () => void;
  onViewHistory?: () => void;
  onCallWaiter?: () => void;
}

/**
 * Bottom System Bar (Navigation Control Bar)
 * 
 * A centered 4-item navigation bar with the cart as the focal point.
 * 
 * Layout (centered, equal spacing):
 * - LEFT: Session PIN
 * - CENTER-LEFT: History
 * - CENTER: Cart (dominant, elevated)
 * - CENTER-RIGHT: Waiter
 * 
 * Visual hierarchy:
 * - Cart: Dominant center piece (larger, colored, elevated)
 * - PIN/History/Waiter: Supporting navigation items (equal size, neutral)
 */
export function BottomSystemBar({
  sessionPin,
  basketCount = 0,
  pendingOrdersCount = 0,
  accentColor = '#f59e0b',
  onViewBasket,
  onViewHistory,
  onCallWaiter
}: BottomSystemBarProps) {
  const [pinCopied, setPinCopied] = useState(false);

  const handleCopyPin = () => {
    if (!sessionPin) return;

    // Fallback copy method that works in all contexts
    const textArea = document.createElement('textarea');
    textArea.value = sessionPin;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
      document.execCommand('copy');
      setPinCopied(true);
      toast.success('PIN copied to clipboard');
      setTimeout(() => setPinCopied(false), 2000);
    } catch (err) {
      console.error('Failed to copy:', err);
      toast.error('Failed to copy PIN');
    }
    
    textArea.remove();
  };

  const handleCallWaiter = () => {
    if (onCallWaiter) {
      onCallWaiter();
      toast.success('✓ Waiter notified');
    }
  };

  const totalCartItems = basketCount + pendingOrdersCount;

  return (
    <div className="fixed bottom-0 left-0 right-0 z-20 pb-5 px-4 pointer-events-none">
      <div className="max-w-4xl mx-auto pointer-events-auto">
        {/* Floating Navigation Bar */}
        <div 
          className="relative rounded-3xl overflow-hidden"
          style={{
            boxShadow: '0 12px 40px rgba(0, 0, 0, 0.15), 0 4px 12px rgba(0, 0, 0, 0.1), 0 0 0 0.5px rgba(0, 0, 0, 0.05)'
          }}
        >
          {/* Frosted glass background */}
          <div 
            className="absolute inset-0"
            style={{
              background: 'linear-gradient(to bottom, rgba(255, 255, 255, 0.98), rgba(250, 250, 250, 0.98))',
              backdropFilter: 'blur(20px)',
              WebkitBackdropFilter: 'blur(20px)'
            }}
          />
          
          {/* Content Container - 4 Item Grid */}
          <div className="relative grid grid-cols-4 items-center gap-4 px-6 py-5">
            
            {/* ═══ LEFT: Session PIN ═══ */}
            <div className="flex flex-col items-center gap-1.5">
              {sessionPin ? (
                <button
                  onClick={handleCopyPin}
                  className="flex flex-col items-center gap-1 transition-all duration-200 active:scale-95"
                >
                  {/* PIN Display */}
                  <div 
                    className="w-12 h-12 rounded-2xl flex items-center justify-center transition-colors duration-200"
                    style={{
                      backgroundColor: pinCopied ? '#ecfdf5' : 'rgba(0, 0, 0, 0.04)',
                      border: pinCopied ? '1.5px solid #10b981' : '1.5px solid rgba(0, 0, 0, 0.06)'
                    }}
                  >
                    <span 
                      className="font-mono font-bold text-base transition-colors duration-200"
                      style={{ 
                        color: pinCopied ? '#059669' : '#111827',
                        letterSpacing: '0.05em'
                      }}
                    >
                      {sessionPin}
                    </span>
                  </div>
                  
                  {/* Label */}
                  <span 
                    className="text-[10px] font-medium uppercase tracking-wider transition-colors duration-200"
                    style={{ 
                      color: pinCopied ? '#059669' : '#6b7280',
                      letterSpacing: '0.08em'
                    }}
                  >
                    PIN
                  </span>
                </button>
              ) : (
                <div className="w-12 h-12" />
              )}
            </div>

            {/* ═══ CENTER-LEFT: History ═══ */}
            <div className="flex flex-col items-center gap-1.5">
              {onViewHistory && (
                <button 
                  onClick={onViewHistory}
                  className="flex flex-col items-center gap-1 transition-all duration-200 active:scale-95"
                >
                  {/* History Icon */}
                  <div 
                    className="w-12 h-12 rounded-2xl flex items-center justify-center transition-colors duration-200"
                    style={{
                      backgroundColor: 'rgba(0, 0, 0, 0.04)',
                      border: '1px solid rgba(0, 0, 0, 0.06)'
                    }}
                  >
                    <Clock className="w-5 h-5" style={{ color: '#6b7280', strokeWidth: 2 }} />
                  </div>
                  
                  {/* Label */}
                  <span 
                    className="text-[10px] font-medium uppercase tracking-wider"
                    style={{ 
                      color: '#6b7280',
                      letterSpacing: '0.08em'
                    }}
                  >
                    History
                  </span>
                </button>
              )}
            </div>

            {/* ═══ CENTER: Cart (Dominant) ═══ */}
            <div className="flex flex-col items-center gap-1.5 -mt-2">
              {onViewBasket && (
                <button
                  onClick={onViewBasket}
                  className="flex flex-col items-center gap-1 transition-all duration-200 active:scale-95"
                >
                  {/* Cart Button - Elevated and Larger */}
                  <div
                    className="relative w-16 h-16 rounded-2xl flex items-center justify-center transition-all duration-200"
                    style={{
                      backgroundColor: totalCartItems > 0 ? accentColor : '#f3f4f6',
                      boxShadow: totalCartItems > 0 
                        ? `0 8px 24px ${accentColor}40, 0 3px 10px ${accentColor}30, 0 0 0 1px ${accentColor}20`
                        : '0 4px 12px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06)'
                    }}
                  >
                    <ShoppingBag 
                      className="w-7 h-7 transition-transform" 
                      style={{ 
                        color: totalCartItems > 0 ? 'white' : '#6b7280',
                        strokeWidth: 2
                      }}
                    />
                    {/* Badge - High Contrast */}
                    {totalCartItems > 0 && (
                      <span 
                        className="absolute -top-1 -right-1 min-w-[24px] h-[24px] px-1.5 rounded-full flex items-center justify-center font-semibold shadow-lg"
                        style={{
                          backgroundColor: '#111827',
                          color: '#ffffff',
                          fontSize: '12px',
                          border: '2.5px solid white'
                        }}
                      >
                        {totalCartItems}
                      </span>
                    )}
                  </div>
                  
                  {/* Label */}
                  <span 
                    className="text-[10px] font-medium uppercase tracking-wider"
                    style={{ 
                      color: totalCartItems > 0 ? accentColor : '#6b7280',
                      letterSpacing: '0.08em'
                    }}
                  >
                    Cart
                  </span>
                </button>
              )}
            </div>

            {/* ═══ CENTER-RIGHT: Waiter ═══ */}
            <div className="flex flex-col items-center gap-1.5">
              {onCallWaiter && (
                <button
                  onClick={handleCallWaiter}
                  className="flex flex-col items-center gap-1 transition-all duration-200 active:scale-95"
                >
                  {/* Waiter Icon */}
                  <div
                    className="w-12 h-12 rounded-2xl flex items-center justify-center transition-colors duration-200 relative"
                    style={{
                      backgroundColor: 'rgba(251, 191, 36, 0.12)',
                      border: '1.5px solid rgba(251, 191, 36, 0.3)'
                    }}
                  >
                    <Bell 
                      className="w-5 h-5" 
                      style={{ 
                        color: '#d97706',
                        strokeWidth: 2
                      }} 
                    />
                    
                    {/* Subtle glow effect */}
                    <div 
                      className="absolute inset-0 rounded-2xl opacity-0 hover:opacity-100 transition-opacity duration-300"
                      style={{
                        background: 'radial-gradient(circle at center, rgba(251, 191, 36, 0.15) 0%, transparent 70%)'
                      }}
                    />
                  </div>
                  
                  {/* Label */}
                  <span 
                    className="text-[10px] font-medium uppercase tracking-wider"
                    style={{ 
                      color: '#6b7280',
                      letterSpacing: '0.08em'
                    }}
                  >
                    Waiter
                  </span>
                </button>
              )}
            </div>

          </div>
        </div>
      </div>
    </div>
  );
}