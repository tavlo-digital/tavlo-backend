import { useState } from 'react';
import { Menu, Bell, User, LogOut, Key, ChevronDown, X, AlertCircle, CheckCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { SimpleNotificationBadge } from './SimpleNotificationBadge';
import { VendorStatusBadge } from '../vendor-onboarding/VendorStatusBadge';
import { TavloLogo } from '../branding/TavloLogo';

interface TopNavProps {
  onMenuClick: () => void;
  restaurantName: string;
  vendorId?: string;
  vendorStatus?: 'demo' | 'activated' | 'live';
  onActivateClick: () => void;
  isLiveAndDiscoverable?: boolean;
  currentUser?: {
    name: string;
    email: string;
  };
  onLogout?: () => void;
}

export function TopNav({
  vendorId,
  vendorStatus,
  onActivateClick,
  isLiveAndDiscoverable,
  currentUser = { name: 'Maria Schmidt', email: 'maria@labellacucina.com' },
  onLogout
}: TopNavProps) {
  const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
  const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);
  const [isChangePasswordOpen, setIsChangePasswordOpen] = useState(false);
  const [passwordForm, setPasswordForm] = useState({
    currentPassword: '',
    newPassword: '',
    confirmPassword: ''
  });
  const [passwordErrors, setPasswordErrors] = useState<Record<string, string>>({});
  const [passwordSuccess, setPasswordSuccess] = useState(false);
  const [passwordLoading, setPasswordLoading] = useState(false);

  // Mock notifications data
  const notifications = [
    {
      id: 1,
      type: 'order',
      title: 'New order #1842',
      message: 'Table 7 - €45.80',
      time: '2 minutes ago',
      unread: true
    },
    {
      id: 2,
      type: 'review',
      title: 'New 5-star review',
      message: 'Sarah M. loved your Margherita Pizza',
      time: '1 hour ago',
      unread: true
    },
    {
      id: 3,
      type: 'inventory',
      title: 'Low stock alert',
      message: 'Mozzarella cheese running low (3 units left)',
      time: '3 hours ago',
      unread: true
    },
    {
      id: 4,
      type: 'order',
      title: 'Order #1841 completed',
      message: 'Table 12 - Payment received',
      time: '5 hours ago',
      unread: false
    },
    {
      id: 5,
      type: 'system',
      title: 'Menu update published',
      message: 'Your summer menu is now live',
      time: 'Yesterday',
      unread: false
    },
    {
      id: 6,
      type: 'review',
      title: 'New review response',
      message: 'Customer replied to your response',
      time: '2 days ago',
      unread: false
    }
  ];

  const unreadCount = notifications.filter(n => n.unread).length;

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case 'order':
        return '🛒';
      case 'review':
        return '⭐';
      case 'inventory':
        return '📦';
      case 'system':
        return '🔔';
      default:
        return '📌';
    }
  };

  // Get user initials
  const getUserInitials = (name: string) => {
    const parts = name.split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
  };

  const handleOpenChangePassword = () => {
    setIsUserMenuOpen(false);
    setIsChangePasswordOpen(true);
    setPasswordForm({ currentPassword: '', newPassword: '', confirmPassword: '' });
    setPasswordErrors({});
    setPasswordSuccess(false);
  };

  const handleLogout = () => {
    setIsUserMenuOpen(false);
    if (onLogout) {
      onLogout();
    } else {
      // Default logout behavior - redirect to sign in
      console.log('Logging out...');
      // In production, this would call the backend and redirect
      alert('Logout successful. Redirecting to sign in...');
    }
  };

  const validatePasswordForm = () => {
    const errors: Record<string, string> = {};

    if (!passwordForm.currentPassword) {
      errors.currentPassword = 'Current password is required';
    }

    if (!passwordForm.newPassword) {
      errors.newPassword = 'New password is required';
    } else if (passwordForm.newPassword.length < 8) {
      errors.newPassword = 'Password must be at least 8 characters';
    }

    if (!passwordForm.confirmPassword) {
      errors.confirmPassword = 'Please confirm your password';
    } else if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      errors.confirmPassword = 'Passwords do not match';
    }

    setPasswordErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!validatePasswordForm()) return;

    setPasswordLoading(true);
    setPasswordErrors({});

    try {
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 1000));

      // Simulate validation - in production, check with backend
      // Mock: incorrect current password check (10% chance)
      if (Math.random() < 0.1) {
        setPasswordErrors({ currentPassword: 'Current password is incorrect' });
        setPasswordLoading(false);
        return;
      }

      // Success
      setPasswordSuccess(true);
      setPasswordLoading(false);

      // Auto-close modal after 2 seconds
      setTimeout(() => {
        setIsChangePasswordOpen(false);
        setPasswordSuccess(false);
        setPasswordForm({ currentPassword: '', newPassword: '', confirmPassword: '' });
      }, 2000);
    } catch (error) {
      setPasswordErrors({ submit: 'Failed to update password. Please try again.' });
      setPasswordLoading(false);
    }
  };

  return (
    <>
      <div className="bg-white border-b border-gray-200 px-4 py-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <TavloLogo variant="full" size={32} colorScheme="black" />
            {vendorStatus && <VendorStatusBadge status={vendorStatus} isLiveAndDiscoverable={isLiveAndDiscoverable} />}
          </div>

          <div className="flex items-center gap-4">
            {/* Notifications */}
            <div className="relative">
              <button
                onClick={() => setIsNotificationsOpen(!isNotificationsOpen)}
                className="p-2 hover:bg-gray-100 rounded-lg relative"
              >
                <Bell className="w-5 h-5 text-gray-600" />
                <SimpleNotificationBadge count={unreadCount} />
              </button>

              {/* Notifications Dropdown */}
              {isNotificationsOpen && (
                <>
                  <div 
                    className="fixed inset-0 z-10"
                    onClick={() => setIsNotificationsOpen(false)}
                  />
                  <div className="absolute right-0 top-full mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-20">
                    {/* Notifications Header */}
                    <div className="px-4 py-3 border-b border-gray-200">
                      <div className="font-medium text-gray-900">Notifications</div>
                      <div className="text-sm text-gray-500 mt-0.5">{unreadCount} unread</div>
                    </div>

                    {/* Notifications List */}
                    <div className="py-1">
                      {notifications.map(n => (
                        <div
                          key={n.id}
                          className={`px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3 ${
                            n.unread ? 'bg-gray-50' : ''
                          }`}
                        >
                          <div className="w-5 h-5 rounded-full bg-gray-100 flex items-center justify-center font-medium text-sm">
                            {getNotificationIcon(n.type)}
                          </div>
                          <div className="flex-1">
                            <div className="font-medium">{n.title}</div>
                            <div className="text-gray-500">{n.message}</div>
                            <div className="text-xs text-gray-500 mt-0.5">{n.time}</div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </>
              )}
            </div>

            {/* User Menu */}
            <div className="relative">
              <button
                onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                className="flex items-center gap-2 p-2 hover:bg-gray-100 rounded-lg transition-colors"
              >
                <div className="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-medium text-sm">
                  {getUserInitials(currentUser.name)}
                </div>
                <ChevronDown className={`w-4 h-4 text-gray-600 transition-transform ${isUserMenuOpen ? 'rotate-180' : ''}`} />
              </button>

              {/* User Dropdown */}
              {isUserMenuOpen && (
                <>
                  <div 
                    className="fixed inset-0 z-10"
                    onClick={() => setIsUserMenuOpen(false)}
                  />
                  <div className="absolute right-0 top-full mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-20">
                    {/* User Identity */}
                    <div className="px-4 py-3 border-b border-gray-200">
                      <div className="font-medium text-gray-900">{currentUser.name}</div>
                      <div className="text-sm text-gray-500 mt-0.5">{currentUser.email}</div>
                    </div>

                    {/* Account Actions */}
                    <div className="py-1">
                      <button
                        onClick={handleOpenChangePassword}
                        className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                      >
                        <Key className="w-4 h-4 text-gray-400" />
                        Change password
                      </button>
                      <button
                        onClick={handleLogout}
                        className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
                      >
                        <LogOut className="w-4 h-4 text-gray-400" />
                        Log out
                      </button>
                    </div>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Change Password Modal */}
      {isChangePasswordOpen && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div 
            className="absolute inset-0"
            onClick={() => !passwordLoading && setIsChangePasswordOpen(false)}
          />
          <div className="relative bg-white rounded-lg max-w-md w-full p-6">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-semibold text-gray-900">Change password</h2>
              <button
                onClick={() => setIsChangePasswordOpen(false)}
                disabled={passwordLoading}
                className="p-1 hover:bg-gray-100 rounded transition-colors disabled:opacity-50"
              >
                <X className="w-5 h-5 text-gray-400" />
              </button>
            </div>

            {passwordSuccess ? (
              <div className="py-8">
                <div className="flex flex-col items-center gap-4">
                  <div className="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
                    <CheckCircle className="w-8 h-8 text-green-600" />
                  </div>
                  <div className="text-center">
                    <p className="text-lg font-medium text-gray-900">Password updated successfully</p>
                    <p className="text-sm text-gray-600 mt-1">You can continue using the dashboard</p>
                  </div>
                </div>
              </div>
            ) : (
              <form onSubmit={handleChangePassword} className="space-y-4">
                {/* Current Password */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Current password
                  </label>
                  <input
                    type="password"
                    value={passwordForm.currentPassword}
                    onChange={(e) => setPasswordForm({ ...passwordForm, currentPassword: e.target.value })}
                    className={`w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                      passwordErrors.currentPassword ? 'border-red-500' : 'border-gray-300'
                    }`}
                    placeholder="Enter current password"
                    disabled={passwordLoading}
                  />
                  {passwordErrors.currentPassword && (
                    <p className="mt-1 text-sm text-red-600">{passwordErrors.currentPassword}</p>
                  )}
                </div>

                {/* New Password */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    New password
                  </label>
                  <input
                    type="password"
                    value={passwordForm.newPassword}
                    onChange={(e) => setPasswordForm({ ...passwordForm, newPassword: e.target.value })}
                    className={`w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                      passwordErrors.newPassword ? 'border-red-500' : 'border-gray-300'
                    }`}
                    placeholder="Minimum 8 characters"
                    disabled={passwordLoading}
                  />
                  {passwordErrors.newPassword && (
                    <p className="mt-1 text-sm text-red-600">{passwordErrors.newPassword}</p>
                  )}
                </div>

                {/* Confirm Password */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Confirm new password
                  </label>
                  <input
                    type="password"
                    value={passwordForm.confirmPassword}
                    onChange={(e) => setPasswordForm({ ...passwordForm, confirmPassword: e.target.value })}
                    className={`w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 ${
                      passwordErrors.confirmPassword ? 'border-red-500' : 'border-gray-300'
                    }`}
                    placeholder="Re-enter new password"
                    disabled={passwordLoading}
                  />
                  {passwordErrors.confirmPassword && (
                    <p className="mt-1 text-sm text-red-600">{passwordErrors.confirmPassword}</p>
                  )}
                </div>

                {/* Password Helper */}
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                  <p className="text-xs text-blue-800">
                    <strong>Password requirements:</strong> Minimum 8 characters
                  </p>
                </div>

                {passwordErrors.submit && (
                  <div className="p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
                    <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <p className="text-sm text-red-600">{passwordErrors.submit}</p>
                  </div>
                )}

                {/* Actions */}
                <div className="flex gap-3 pt-2">
                  <button
                    type="button"
                    onClick={() => setIsChangePasswordOpen(false)}
                    disabled={passwordLoading}
                    className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-50"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    disabled={passwordLoading}
                    className="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors disabled:opacity-50"
                  >
                    {passwordLoading ? 'Updating...' : 'Update password'}
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}
    </>
  );
}