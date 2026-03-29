import { useState, useEffect } from 'react';
import { Building2, Mail, Lock, AlertCircle, CheckCircle, X } from 'lucide-react';
import { Button } from '../ui/button';

interface AcceptInvitationProps {
  inviteToken?: string; // From URL parameter
  onSuccess: (data: { email: string; token: string }) => void;
  onNavigateToSignIn?: () => void;
}

type InviteStatus = 'loading' | 'valid' | 'expired' | 'used' | 'invalid';

interface InviteData {
  email: string;
  restaurantName: string;
  inviterName: string;
}

export function AcceptInvitation({ inviteToken, onSuccess, onNavigateToSignIn }: AcceptInvitationProps) {
  const [inviteStatus, setInviteStatus] = useState<InviteStatus>('loading');
  const [inviteData, setInviteData] = useState<InviteData>({
    email: '',
    restaurantName: '',
    inviterName: ''
  });
  
  const [formData, setFormData] = useState({
    password: '',
    confirmPassword: ''
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);
  const [passwordStrength, setPasswordStrength] = useState<'weak' | 'medium' | 'strong' | null>(null);

  // Validate invite token on mount
  useEffect(() => {
    const validateInvite = async () => {
      if (!inviteToken) {
        setInviteStatus('invalid');
        return;
      }

      try {
        // Simulate API call to validate invite
        await new Promise(resolve => setTimeout(resolve, 800));
        
        // Mock validation - in real implementation, call backend
        // This is where you'd determine if the invite is valid, expired, or already used
        
        // For demo purposes, simulate a valid invite
        setInviteData({
          email: 'thomas@labellacucina.com',
          restaurantName: 'La Bella Cucina',
          inviterName: 'Maria Schmidt'
        });
        setInviteStatus('valid');
        
        // Uncomment to test different states:
        // setInviteStatus('expired');
        // setInviteStatus('used');
      } catch (error) {
        setInviteStatus('invalid');
      }
    };

    validateInvite();
  }, [inviteToken]);

  // Check password strength
  useEffect(() => {
    if (!formData.password) {
      setPasswordStrength(null);
      return;
    }

    if (formData.password.length < 8) {
      setPasswordStrength('weak');
    } else if (formData.password.length < 12) {
      setPasswordStrength('medium');
    } else {
      setPasswordStrength('strong');
    }
  }, [formData.password]);

  const validate = () => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.password) {
      newErrors.password = 'Password is required';
    } else if (formData.password.length < 8) {
      newErrors.password = 'Password must be at least 8 characters';
    }
    
    if (!formData.confirmPassword) {
      newErrors.confirmPassword = 'Please confirm your password';
    } else if (formData.password !== formData.confirmPassword) {
      newErrors.confirmPassword = 'Passwords do not match';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validate()) return;
    
    setLoading(true);
    setErrors({});
    
    try {
      // Simulate API call to accept invitation
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      // Mock successful acceptance
      // In real implementation, this would call the backend API
      onSuccess({ 
        email: inviteData.email, 
        token: 'mock-token-' + Date.now() 
      });
    } catch (error) {
      setErrors({ submit: 'Failed to accept invitation. Please try again.' });
    } finally {
      setLoading(false);
    }
  };

  // Loading state
  if (inviteStatus === 'loading') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
          <div className="text-center">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl mb-4">
              <div className="w-8 h-8 border-4 border-white border-t-transparent rounded-full animate-spin"></div>
            </div>
            <h2 className="text-xl text-gray-900">Validating invitation...</h2>
          </div>
        </div>
      </div>
    );
  }

  // Expired state
  if (inviteStatus === 'expired' || inviteStatus === 'invalid') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
          <div className="text-center mb-6">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-2xl mb-4">
              <X className="w-8 h-8 text-red-600" />
            </div>
            <h1 className="text-2xl font-semibold mb-2 text-gray-900">Invitation expired</h1>
            <p className="text-gray-600">
              This invitation link is no longer valid.<br />
              Please ask the restaurant owner to resend the invite.
            </p>
          </div>

          <Button
            onClick={onNavigateToSignIn}
            className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-3"
          >
            Go to Sign in
          </Button>
        </div>
      </div>
    );
  }

  // Already used state
  if (inviteStatus === 'used') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
          <div className="text-center mb-6">
            <div className="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-2xl mb-4">
              <CheckCircle className="w-8 h-8 text-blue-600" />
            </div>
            <h1 className="text-2xl font-semibold mb-2 text-gray-900">Invitation already used</h1>
            <p className="text-gray-600">
              This invitation has already been accepted.<br />
              Please sign in to access the restaurant.
            </p>
          </div>

          <Button
            onClick={onNavigateToSignIn}
            className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-3"
          >
            Sign in
          </Button>
        </div>
      </div>
    );
  }

  // Valid invitation - show form
  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center p-4">
      <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8">
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl mb-4">
            <Building2 className="w-8 h-8 text-white" />
          </div>
          <h1 className="text-3xl mb-2 text-gray-900">You've been invited to join Tavlo</h1>
          <p className="text-gray-600">You've been invited to join <span className="font-medium">{inviteData.restaurantName}</span></p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Email (pre-filled, disabled) */}
          <div>
            <label className="block text-sm text-gray-700 mb-2">
              Email address
            </label>
            <div className="relative">
              <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
              <input
                type="email"
                value={inviteData.email}
                disabled
                className="w-full pl-11 pr-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600"
              />
            </div>
          </div>

          {/* Set Password */}
          <div>
            <label className="block text-sm text-gray-700 mb-2">
              Set password
            </label>
            <div className="relative">
              <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
              <input
                type="password"
                value={formData.password}
                onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                className={`w-full pl-11 pr-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                  errors.password ? 'border-red-500' : 'border-gray-300'
                }`}
                placeholder="Minimum 8 characters"
              />
            </div>
            {errors.password && (
              <p className="mt-1 text-sm text-red-600">{errors.password}</p>
            )}
            {!errors.password && passwordStrength && (
              <p className="mt-1 text-xs text-gray-500">
                Password strength: <span className={`font-medium ${
                  passwordStrength === 'weak' ? 'text-red-600' :
                  passwordStrength === 'medium' ? 'text-yellow-600' :
                  'text-green-600'
                }`}>{passwordStrength}</span>
              </p>
            )}
          </div>

          {/* Confirm Password */}
          <div>
            <label className="block text-sm text-gray-700 mb-2">
              Confirm password
            </label>
            <div className="relative">
              <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
              <input
                type="password"
                value={formData.confirmPassword}
                onChange={(e) => setFormData({ ...formData, confirmPassword: e.target.value })}
                className={`w-full pl-11 pr-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                  errors.confirmPassword ? 'border-red-500' : 'border-gray-300'
                }`}
                placeholder="Re-enter your password"
              />
            </div>
            {errors.confirmPassword && (
              <p className="mt-1 text-sm text-red-600">{errors.confirmPassword}</p>
            )}
          </div>

          {/* Password Helper */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
            <p className="text-xs text-blue-800">
              <strong>Password requirements:</strong> Minimum 8 characters
            </p>
          </div>

          {errors.submit && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
              <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-600">{errors.submit}</p>
            </div>
          )}

          <Button
            type="submit"
            disabled={loading}
            className="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white py-3"
          >
            {loading ? 'Accepting...' : 'Accept & Join'}
          </Button>
        </form>

        <div className="mt-6 pt-6 border-t border-gray-200 text-center">
          <p className="text-sm text-gray-600">
            Already have an account?{' '}
            <button
              type="button"
              onClick={onNavigateToSignIn}
              className="text-emerald-600 hover:text-emerald-700 font-medium"
            >
              Sign in
            </button>
          </p>
        </div>
      </div>
    </div>
  );
}
