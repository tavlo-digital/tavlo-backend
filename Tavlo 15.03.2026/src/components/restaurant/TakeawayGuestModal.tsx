import { useState } from 'react';
import { X, User, Mail, Phone, LogIn, UserPlus } from 'lucide-react';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';

interface TakeawayGuestModalProps {
  isOpen: boolean;
  onClose: () => void;
  onContinueAsGuest: (guestData: { name: string; phone?: string; email?: string }) => void;
  onLogin: () => void;
  onRegister: () => void;
  restaurantName: string;
}

export function TakeawayGuestModal({
  isOpen,
  onClose,
  onContinueAsGuest,
  onLogin,
  onRegister,
  restaurantName
}: TakeawayGuestModalProps) {
  const [selectedOption, setSelectedOption] = useState<'choice' | 'guest' | null>(null);
  const [guestName, setGuestName] = useState('');
  const [guestPhone, setGuestPhone] = useState('');
  const [guestEmail, setGuestEmail] = useState('');
  const [errors, setErrors] = useState<any>({});

  const validateGuestForm = () => {
    const newErrors: any = {};
    
    if (!guestName.trim()) {
      newErrors.name = 'Name is required';
    }
    
    // Phone is optional, but validate if provided
    if (guestPhone && !/^\+?[\d\s-()]+$/.test(guestPhone)) {
      newErrors.phone = 'Invalid phone number';
    }
    
    // Email is optional, but validate if provided
    if (guestEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail)) {
      newErrors.email = 'Invalid email address';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleContinueAsGuest = () => {
    if (validateGuestForm()) {
      onContinueAsGuest({
        name: guestName,
        phone: guestPhone || undefined,
        email: guestEmail || undefined
      });
      handleClose();
    }
  };

  const handleClose = () => {
    setSelectedOption(null);
    setGuestName('');
    setGuestPhone('');
    setGuestEmail('');
    setErrors({});
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl max-w-md w-full">
        {/* Header */}
        <div className="border-b px-6 py-4 flex items-center justify-between">
          <div>
            <h2 className="text-xl">Order Takeaway</h2>
            <p className="text-sm text-gray-600 mt-1">{restaurantName}</p>
          </div>
          <button
            onClick={handleClose}
            className="p-2 hover:bg-gray-100 rounded-full transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6">
          {selectedOption === null && (
            <div className="space-y-4">
              <p className="text-gray-600 mb-6">
                How would you like to proceed with your order?
              </p>

              {/* Login Option */}
              <button
                onClick={() => {
                  handleClose();
                  onLogin();
                }}
                className="w-full p-4 border-2 border-gray-200 rounded-xl hover:border-orange-500 hover:bg-orange-50 transition-all text-left group"
              >
                <div className="flex items-start gap-4">
                  <div className="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center group-hover:bg-orange-500 transition-colors">
                    <LogIn className="w-6 h-6 text-orange-600 group-hover:text-white transition-colors" />
                  </div>
                  <div className="flex-1">
                    <h3 className="font-medium mb-1">Login to Your Account</h3>
                    <p className="text-sm text-gray-600">
                      Access your order history and loyalty points
                    </p>
                  </div>
                </div>
              </button>

              {/* Register Option */}
              <button
                onClick={() => {
                  handleClose();
                  onRegister();
                }}
                className="w-full p-4 border-2 border-gray-200 rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-all text-left group"
              >
                <div className="flex items-start gap-4">
                  <div className="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-500 transition-colors">
                    <UserPlus className="w-6 h-6 text-blue-600 group-hover:text-white transition-colors" />
                  </div>
                  <div className="flex-1">
                    <h3 className="font-medium mb-1">Create an Account</h3>
                    <p className="text-sm text-gray-600">
                      Join now and earn loyalty points on every order
                    </p>
                  </div>
                </div>
              </button>

              {/* Guest Option */}
              <button
                onClick={() => setSelectedOption('guest')}
                className="w-full p-4 border-2 border-gray-200 rounded-xl hover:border-green-500 hover:bg-green-50 transition-all text-left group"
              >
                <div className="flex items-start gap-4">
                  <div className="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center group-hover:bg-green-500 transition-colors">
                    <User className="w-6 h-6 text-green-600 group-hover:text-white transition-colors" />
                  </div>
                  <div className="flex-1">
                    <h3 className="font-medium mb-1">Continue as Guest</h3>
                    <p className="text-sm text-gray-600">
                      Quick checkout without creating an account
                    </p>
                  </div>
                </div>
              </button>

              <div className="mt-6 p-4 bg-gray-50 rounded-xl">
                <p className="text-sm text-gray-600 text-center">
                  💡 <strong>Tip:</strong> Create an account to earn loyalty points and track your orders!
                </p>
              </div>
            </div>
          )}

          {selectedOption === 'guest' && (
            <div className="space-y-4">
              <div className="mb-4">
                <button
                  onClick={() => setSelectedOption(null)}
                  className="text-sm text-gray-600 hover:text-gray-900 flex items-center gap-1"
                >
                  ← Back to options
                </button>
              </div>

              <div>
                <h3 className="text-lg mb-4">Guest Information</h3>
                <p className="text-sm text-gray-600 mb-6">
                  We'll use this information for your pickup order
                </p>
              </div>

              {/* Name Field (Required) */}
              <div>
                <Label htmlFor="guest-name" className="flex items-center gap-2">
                  <User className="w-4 h-4" />
                  Your Name *
                </Label>
                <Input
                  id="guest-name"
                  value={guestName}
                  onChange={(e) => {
                    setGuestName(e.target.value);
                    if (errors.name) {
                      setErrors({ ...errors, name: undefined });
                    }
                  }}
                  placeholder="John Doe"
                  className={errors.name ? 'border-red-500' : ''}
                />
                {errors.name && (
                  <p className="text-sm text-red-600 mt-1">{errors.name}</p>
                )}
              </div>

              {/* Phone Field (Optional) */}
              <div>
                <Label htmlFor="guest-phone" className="flex items-center gap-2">
                  <Phone className="w-4 h-4" />
                  Phone Number (Optional)
                </Label>
                <Input
                  id="guest-phone"
                  type="tel"
                  value={guestPhone}
                  onChange={(e) => {
                    setGuestPhone(e.target.value);
                    if (errors.phone) {
                      setErrors({ ...errors, phone: undefined });
                    }
                  }}
                  placeholder="+43 660 123 4567"
                  className={errors.phone ? 'border-red-500' : ''}
                />
                {errors.phone && (
                  <p className="text-sm text-red-600 mt-1">{errors.phone}</p>
                )}
                <p className="text-xs text-gray-500 mt-1">
                  We'll send you updates about your order
                </p>
              </div>

              {/* Email Field (Optional) */}
              <div>
                <Label htmlFor="guest-email" className="flex items-center gap-2">
                  <Mail className="w-4 h-4" />
                  Email Address (Optional)
                </Label>
                <Input
                  id="guest-email"
                  type="email"
                  value={guestEmail}
                  onChange={(e) => {
                    setGuestEmail(e.target.value);
                    if (errors.email) {
                      setErrors({ ...errors, email: undefined });
                    }
                  }}
                  placeholder="john@example.com"
                  className={errors.email ? 'border-red-500' : ''}
                />
                {errors.email && (
                  <p className="text-sm text-red-600 mt-1">{errors.email}</p>
                )}
                <p className="text-xs text-gray-500 mt-1">
                  Receive your order receipt via email
                </p>
              </div>

              <div className="pt-4 space-y-3">
                <Button
                  onClick={handleContinueAsGuest}
                  className="w-full bg-green-600 hover:bg-green-700"
                >
                  Continue as Guest
                </Button>
                
                <p className="text-xs text-gray-500 text-center">
                  * Required field
                </p>
              </div>

              <div className="mt-4 p-4 bg-orange-50 border border-orange-200 rounded-xl">
                <p className="text-sm text-orange-800">
                  📱 <strong>Why provide contact info?</strong><br/>
                  We'll notify you when your order is ready for pickup
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
