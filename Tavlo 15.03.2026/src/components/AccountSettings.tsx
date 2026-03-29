import { useState } from 'react';
import { ArrowLeft, User, Mail, Phone, Lock, Bell, CreditCard, Trash2 } from 'lucide-react';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { Switch } from './ui/switch';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './ui/card';
import { toast } from 'sonner@2.0.3';

interface AccountSettingsProps {
  user: any;
  onBack: () => void;
  onUpdateProfile: (data: any) => void;
  onDeleteAccount?: () => void;
}

export function AccountSettings({ user, onBack, onUpdateProfile, onDeleteAccount }: AccountSettingsProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [formData, setFormData] = useState({
    name: user?.name || '',
    email: user?.email || '',
    phone: user?.phone || '',
  });
  
  const [notifications, setNotifications] = useState({
    orderUpdates: true,
    promotions: true,
    newsletter: false,
  });

  const [privacySettings, setPrivacySettings] = useState({
    shareNameWithVendor: user?.shareNameWithVendor ?? true,
    showInTopCustomers: true,
  });

  const [paymentMethods, setPaymentMethods] = useState<any[]>([
    // Example: { id: '1', type: 'card', last4: '4242', brand: 'Visa' }
  ]);

  const [showAddPayment, setShowAddPayment] = useState(false);
  const [newPaymentMethod, setNewPaymentMethod] = useState({
    cardNumber: '',
    cardName: '',
    expiryDate: '',
    cvv: '',
  });

  const handleSave = () => {
    onUpdateProfile({ ...formData, ...privacySettings });
    setIsEditing(false);
    toast.success('Profile updated successfully');
  };

  const handleAddPaymentMethod = () => {
    // Validate card number (basic validation)
    if (newPaymentMethod.cardNumber.length < 16) {
      toast.error('Invalid card number');
      return;
    }

    const newMethod = {
      id: Date.now().toString(),
      type: 'card',
      last4: newPaymentMethod.cardNumber.slice(-4),
      brand: getCardBrand(newPaymentMethod.cardNumber),
      cardName: newPaymentMethod.cardName,
    };

    setPaymentMethods([...paymentMethods, newMethod]);
    setShowAddPayment(false);
    setNewPaymentMethod({ cardNumber: '', cardName: '', expiryDate: '', cvv: '' });
    toast.success('Payment method added');
  };

  const handleRemovePaymentMethod = (id: string) => {
    if (window.confirm('Remove this payment method?')) {
      setPaymentMethods(paymentMethods.filter(pm => pm.id !== id));
      toast.success('Payment method removed');
    }
  };

  const getCardBrand = (cardNumber: string) => {
    const firstDigit = cardNumber[0];
    if (firstDigit === '4') return 'Visa';
    if (firstDigit === '5') return 'Mastercard';
    if (firstDigit === '3') return 'Amex';
    return 'Card';
  };

  const formatCardNumber = (value: string) => {
    const v = value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
    const matches = v.match(/\d{4,16}/g);
    const match = (matches && matches[0]) || '';
    const parts = [];

    for (let i = 0, len = match.length; i < len; i += 4) {
      parts.push(match.substring(i, i + 4));
    }

    if (parts.length) {
      return parts.join(' ');
    } else {
      return value;
    }
  };

  const handleDeleteAccount = () => {
    if (window.confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
      if (window.confirm('All your data, including order history and loyalty points, will be permanently deleted. Continue?')) {
        onDeleteAccount?.();
        toast.success('Account deleted');
      }
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b sticky top-0 z-10">
        <div className="p-4 flex items-center gap-3">
          <button onClick={onBack} className="p-2 hover:bg-gray-100 rounded-full">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <h1 className="text-xl">Account Settings</h1>
        </div>
      </div>

      <div className="p-4 space-y-4">
        {/* Profile Information */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="flex items-center gap-2">
                  <User className="w-5 h-5" />
                  Profile Information
                </CardTitle>
                <CardDescription>Manage your personal details</CardDescription>
              </div>
              {!isEditing && (
                <Button variant="outline" size="sm" onClick={() => setIsEditing(true)}>
                  Edit
                </Button>
              )}
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {isEditing ? (
              <>
                <div className="space-y-2">
                  <Label htmlFor="name">Full Name</Label>
                  <Input
                    id="name"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    placeholder="Enter your name"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="email">Email</Label>
                  <div className="relative">
                    <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <Input
                      id="email"
                      type="email"
                      className="pl-10"
                      value={formData.email}
                      onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                      placeholder="Enter your email"
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="phone">Phone Number</Label>
                  <div className="relative">
                    <Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <Input
                      id="phone"
                      type="tel"
                      className="pl-10"
                      value={formData.phone}
                      onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                      placeholder="Enter your phone number"
                    />
                  </div>
                </div>

                <div className="flex gap-2 pt-2">
                  <Button onClick={handleSave} className="flex-1">
                    Save Changes
                  </Button>
                  <Button 
                    variant="outline" 
                    onClick={() => {
                      setIsEditing(false);
                      setFormData({
                        name: user?.name || '',
                        email: user?.email || '',
                        phone: user?.phone || '',
                      });
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              </>
            ) : (
              <div className="space-y-3">
                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                  <User className="w-5 h-5 text-gray-400" />
                  <div>
                    <div className="text-sm text-gray-500">Name</div>
                    <div>{formData.name || 'Not set'}</div>
                  </div>
                </div>

                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                  <Mail className="w-5 h-5 text-gray-400" />
                  <div>
                    <div className="text-sm text-gray-500">Email</div>
                    <div>{formData.email || 'Not set'}</div>
                  </div>
                </div>

                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                  <Phone className="w-5 h-5 text-gray-400" />
                  <div>
                    <div className="text-sm text-gray-500">Phone</div>
                    <div>{formData.phone || 'Not set'}</div>
                  </div>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Security */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Lock className="w-5 h-5" />
              Security
            </CardTitle>
            <CardDescription>Manage your password and security settings</CardDescription>
          </CardHeader>
          <CardContent>
            <Button variant="outline" className="w-full justify-start">
              Change Password
            </Button>
          </CardContent>
        </Card>

        {/* Notifications */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Bell className="w-5 h-5" />
              Notifications
            </CardTitle>
            <CardDescription>Choose what updates you want to receive</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <div className="font-medium">Order Updates</div>
                <div className="text-sm text-gray-500">Get notified about your order status</div>
              </div>
              <Switch
                checked={notifications.orderUpdates}
                onCheckedChange={(checked) => 
                  setNotifications({ ...notifications, orderUpdates: checked })
                }
              />
            </div>

            <div className="flex items-center justify-between">
              <div>
                <div className="font-medium">Promotions</div>
                <div className="text-sm text-gray-500">Receive special offers and deals</div>
              </div>
              <Switch
                checked={notifications.promotions}
                onCheckedChange={(checked) => 
                  setNotifications({ ...notifications, promotions: checked })
                }
              />
            </div>

            <div className="flex items-center justify-between">
              <div>
                <div className="font-medium">Newsletter</div>
                <div className="text-sm text-gray-500">Get the latest news and updates</div>
              </div>
              <Switch
                checked={notifications.newsletter}
                onCheckedChange={(checked) => 
                  setNotifications({ ...notifications, newsletter: checked })
                }
              />
            </div>
          </CardContent>
        </Card>

        {/* Payment Methods */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CreditCard className="w-5 h-5" />
              Payment Methods
            </CardTitle>
            <CardDescription>Manage your saved payment methods</CardDescription>
          </CardHeader>
          <CardContent>
            {paymentMethods.length > 0 ? (
              paymentMethods.map(pm => (
                <div key={pm.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <div className="flex items-center gap-3">
                    <CreditCard className="w-5 h-5 text-gray-400" />
                    <div>
                      <div className="text-sm text-gray-500">Card</div>
                      <div>{pm.brand} ending in {pm.last4}</div>
                    </div>
                  </div>
                  <Button 
                    variant="outline" 
                    size="sm" 
                    onClick={() => handleRemovePaymentMethod(pm.id)}
                  >
                    Remove
                  </Button>
                </div>
              ))
            ) : (
              <div className="text-sm text-gray-500 text-center py-4">
                No saved payment methods
              </div>
            )}
            {showAddPayment ? (
              <div className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="cardNumber">Card Number</Label>
                  <Input
                    id="cardNumber"
                    value={newPaymentMethod.cardNumber}
                    onChange={(e) => setNewPaymentMethod({ ...newPaymentMethod, cardNumber: formatCardNumber(e.target.value) })}
                    placeholder="Enter your card number"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="cardName">Card Name</Label>
                  <Input
                    id="cardName"
                    value={newPaymentMethod.cardName}
                    onChange={(e) => setNewPaymentMethod({ ...newPaymentMethod, cardName: e.target.value })}
                    placeholder="Enter your card name"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="expiryDate">Expiry Date</Label>
                  <Input
                    id="expiryDate"
                    value={newPaymentMethod.expiryDate}
                    onChange={(e) => setNewPaymentMethod({ ...newPaymentMethod, expiryDate: e.target.value })}
                    placeholder="MM/YY"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="cvv">CVV</Label>
                  <Input
                    id="cvv"
                    value={newPaymentMethod.cvv}
                    onChange={(e) => setNewPaymentMethod({ ...newPaymentMethod, cvv: e.target.value })}
                    placeholder="Enter your CVV"
                  />
                </div>

                <div className="flex gap-2 pt-2">
                  <Button onClick={handleAddPaymentMethod} className="flex-1">
                    Add Payment Method
                  </Button>
                  <Button 
                    variant="outline" 
                    onClick={() => setShowAddPayment(false)}
                  >
                    Cancel
                  </Button>
                </div>
              </div>
            ) : (
              <Button variant="outline" className="w-full" onClick={() => setShowAddPayment(true)}>
                Add Payment Method
              </Button>
            )}
          </CardContent>
        </Card>

        {/* Privacy */}
        <Card>
          <CardHeader>
            <CardTitle>Privacy</CardTitle>
            <CardDescription>Control your data and privacy preferences</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <div className="font-medium">Share Name with Vendor</div>
                <div className="text-sm text-gray-500">
                  Allow restaurants to see your name with your orders
                </div>
              </div>
              <Switch
                checked={privacySettings.shareNameWithVendor}
                onCheckedChange={(checked) => {
                  setPrivacySettings({ ...privacySettings, shareNameWithVendor: checked });
                  onUpdateProfile({ shareNameWithVendor: checked });
                  toast.success(checked ? 'Vendor can now see your name' : 'Your name is now hidden from vendors');
                }}
              />
            </div>

            <div className="flex items-center justify-between">
              <div>
                <div className="font-medium">Show in Top Customers</div>
                <div className="text-sm text-gray-500">
                  Allow restaurant to display you in their top customers list
                </div>
              </div>
              <Switch
                checked={privacySettings.showInTopCustomers}
                onCheckedChange={(checked) => 
                  setPrivacySettings({ ...privacySettings, showInTopCustomers: checked })
                }
              />
            </div>
            
            <Button variant="outline" className="w-full justify-start">
              Download My Data
            </Button>
          </CardContent>
        </Card>

        {/* Danger Zone */}
        <Card className="border-red-200">
          <CardHeader>
            <CardTitle className="text-red-600 flex items-center gap-2">
              <Trash2 className="w-5 h-5" />
              Danger Zone
            </CardTitle>
            <CardDescription>Irreversible actions</CardDescription>
          </CardHeader>
          <CardContent>
            <Button 
              variant="destructive" 
              className="w-full"
              onClick={handleDeleteAccount}
            >
              Delete Account
            </Button>
            <p className="text-xs text-gray-500 mt-2 text-center">
              This will permanently delete your account and all associated data
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}