import { useState } from 'react';
import { Gift, Plus, Pause, Play, Trash2, Edit, Calendar, Clock, Tag, TrendingUp, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';

interface Promotion {
  id: string;
  name: string;
  type: 'happy-hour' | 'weekend-special' | 'item-discount';
  description: string;
  discountPercent?: number;
  discountAmount?: number;
  startDate: string;
  endDate: string;
  startTime?: string;
  endTime?: string;
  daysOfWeek?: string[];
  itemIds?: string[];
  isActive: boolean;
  createdAt: string;
}

interface LoyaltyManagementProps {
  vendorId: string;
}

export function LoyaltyManagement({ vendorId }: LoyaltyManagementProps) {
  // Loyalty settings
  const [loyaltySettings, setLoyaltySettings] = useState({
    enabled: true,
    pointsPerEuro: 10,
    minimumRedemption: 100,
    pointValue: 0.01, // 1 point = €0.01
  });

  // Mock promotions
  const [promotions, setPromotions] = useState<Promotion[]>([
    {
      id: 'promo_1',
      name: 'Happy Hour - 20% Off Drinks',
      type: 'happy-hour',
      description: 'Get 20% off all beverages',
      discountPercent: 20,
      startDate: '2024-12-01',
      endDate: '2025-01-31',
      startTime: '16:00',
      endTime: '18:00',
      daysOfWeek: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
      isActive: true,
      createdAt: '2024-12-20T10:00:00Z'
    },
    {
      id: 'promo_2',
      name: 'Weekend Brunch Special',
      type: 'weekend-special',
      description: '15% off brunch menu items',
      discountPercent: 15,
      startDate: '2024-12-01',
      endDate: '2025-02-28',
      startTime: '10:00',
      endTime: '14:00',
      daysOfWeek: ['Saturday', 'Sunday'],
      isActive: true,
      createdAt: '2024-12-18T10:00:00Z'
    },
    {
      id: 'promo_3',
      name: 'Pizza Monday - €2 Off',
      type: 'item-discount',
      description: 'All pizzas €2 off on Mondays',
      discountAmount: 2,
      startDate: '2024-12-01',
      endDate: '2025-03-31',
      daysOfWeek: ['Monday'],
      isActive: false,
      createdAt: '2024-12-15T10:00:00Z'
    }
  ]);

  const [showPromotionForm, setShowPromotionForm] = useState(false);
  const [editingPromotion, setEditingPromotion] = useState<Promotion | null>(null);

  // Form state
  const [formData, setFormData] = useState({
    name: '',
    type: 'happy-hour' as Promotion['type'],
    description: '',
    discountType: 'percent' as 'percent' | 'fixed',
    discountValue: '',
    startDate: '',
    endDate: '',
    startTime: '',
    endTime: '',
    daysOfWeek: [] as string[],
    isActive: true,
  });

  // Open form for editing
  const handleEditPromotion = (promo: Promotion) => {
    setEditingPromotion(promo);
    setFormData({
      name: promo.name,
      type: promo.type,
      description: promo.description,
      discountType: promo.discountPercent ? 'percent' : 'fixed',
      discountValue: String(promo.discountPercent || promo.discountAmount || ''),
      startDate: promo.startDate,
      endDate: promo.endDate,
      startTime: promo.startTime || '',
      endTime: promo.endTime || '',
      daysOfWeek: promo.daysOfWeek || [],
      isActive: promo.isActive,
    });
    setShowPromotionForm(true);
  };

  // Reset form
  const resetForm = () => {
    setFormData({
      name: '',
      type: 'happy-hour',
      description: '',
      discountType: 'percent',
      discountValue: '',
      startDate: '',
      endDate: '',
      startTime: '',
      endTime: '',
      daysOfWeek: [],
      isActive: true,
    });
    setEditingPromotion(null);
    setShowPromotionForm(false);
  };

  // Save promotion
  const handleSavePromotion = () => {
    // Validation
    if (!formData.name.trim()) {
      toast.error('Please enter a promotion name');
      return;
    }
    if (!formData.description.trim()) {
      toast.error('Please enter a description');
      return;
    }
    if (!formData.discountValue || parseFloat(formData.discountValue) <= 0) {
      toast.error('Please enter a valid discount value');
      return;
    }
    if (!formData.startDate || !formData.endDate) {
      toast.error('Please select start and end dates');
      return;
    }

    const newPromotion: Promotion = {
      id: editingPromotion?.id || `promo_${Date.now()}`,
      name: formData.name,
      type: formData.type,
      description: formData.description,
      discountPercent: formData.discountType === 'percent' ? parseFloat(formData.discountValue) : undefined,
      discountAmount: formData.discountType === 'fixed' ? parseFloat(formData.discountValue) : undefined,
      startDate: formData.startDate,
      endDate: formData.endDate,
      startTime: formData.startTime || undefined,
      endTime: formData.endTime || undefined,
      daysOfWeek: formData.daysOfWeek.length > 0 ? formData.daysOfWeek : undefined,
      isActive: formData.isActive,
      createdAt: editingPromotion?.createdAt || new Date().toISOString(),
    };

    if (editingPromotion) {
      setPromotions(promotions.map(p => p.id === editingPromotion.id ? newPromotion : p));
      toast.success('Promotion updated successfully');
    } else {
      setPromotions([...promotions, newPromotion]);
      toast.success('Promotion created successfully');
    }

    resetForm();
  };

  // Toggle day of week
  const toggleDayOfWeek = (day: string) => {
    setFormData({
      ...formData,
      daysOfWeek: formData.daysOfWeek.includes(day)
        ? formData.daysOfWeek.filter(d => d !== day)
        : [...formData.daysOfWeek, day]
    });
  };

  const weekDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

  const handleSaveLoyalty = () => {
    toast.success('Loyalty settings saved');
    console.log('Loyalty settings:', loyaltySettings);
  };

  const handleTogglePromotion = (id: string) => {
    setPromotions(promotions.map(p => 
      p.id === id ? { ...p, isActive: !p.isActive } : p
    ));
    const promo = promotions.find(p => p.id === id);
    toast.success(promo?.isActive ? 'Promotion paused' : 'Promotion activated');
  };

  const handleDeletePromotion = (id: string) => {
    if (confirm('Delete this promotion?')) {
      setPromotions(promotions.filter(p => p.id !== id));
      toast.success('Promotion deleted');
    }
  };

  const getPromotionTypeLabel = (type: string) => {
    switch (type) {
      case 'happy-hour': return 'Happy Hour';
      case 'weekend-special': return 'Weekend Special';
      case 'item-discount': return 'Item Discount';
      default: return type;
    }
  };

  return (
    <div className="max-w-6xl mx-auto p-6">
      <div className="mb-6">
        <h1 className="text-3xl text-gray-900 mb-2">Loyalty & Promotions</h1>
        <p className="text-gray-600">Manage rewards and promotional campaigns</p>
      </div>

      {/* Loyalty Settings Banner */}
      <div className="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-xl p-6 mb-6">
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 bg-emerald-500 rounded-lg flex items-center justify-center">
              <Gift className="w-6 h-6 text-white" />
            </div>
            <div>
              <h2 className="text-xl text-gray-900">Loyalty Points System</h2>
              <p className="text-sm text-gray-600">Configure how customers earn and redeem points</p>
            </div>
          </div>
          <label className="flex items-center gap-2 cursor-pointer bg-white px-4 py-2 rounded-lg border border-emerald-300">
            <input
              type="checkbox"
              checked={loyaltySettings.enabled}
              onChange={(e) => setLoyaltySettings({ ...loyaltySettings, enabled: e.target.checked })}
              className="w-5 h-5 text-emerald-600 rounded"
            />
            <span className="text-sm font-medium text-gray-700">
              {loyaltySettings.enabled ? 'Enabled' : 'Disabled'}
            </span>
          </label>
        </div>

        {loyaltySettings.enabled && (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {/* Points per €1 spent */}
            <div className="bg-white rounded-lg p-4 border border-emerald-200">
              <label className="block text-xs text-gray-600 mb-2">Points per €1 spent</label>
              <input
                type="number"
                value={loyaltySettings.pointsPerEuro}
                onChange={(e) => setLoyaltySettings({ ...loyaltySettings, pointsPerEuro: parseFloat(e.target.value) || 0 })}
                className="w-full text-2xl font-semibold text-emerald-600 bg-transparent border-0 p-0 focus:outline-none focus:ring-0"
                min="0"
                step="1"
              />
              <p className="text-xs text-gray-500 mt-1">
                Customers earn {loyaltySettings.pointsPerEuro} points for every €1 spent
              </p>
            </div>

            {/* Points needed for €1 discount */}
            <div className="bg-white rounded-lg p-4 border border-emerald-200">
              <label className="block text-xs text-gray-600 mb-2">Points needed for €1 discount</label>
              <input
                type="number"
                value={loyaltySettings.minimumRedemption}
                onChange={(e) => setLoyaltySettings({ ...loyaltySettings, minimumRedemption: parseFloat(e.target.value) || 0 })}
                className="w-full text-2xl font-semibold text-emerald-600 bg-transparent border-0 p-0 focus:outline-none focus:ring-0"
                min="0"
                step="10"
              />
              <p className="text-xs text-gray-500 mt-1">
                {loyaltySettings.minimumRedemption} points = €{((loyaltySettings.minimumRedemption * loyaltySettings.pointValue) || 0).toFixed(2)} discount
              </p>
            </div>

            {/* Point Value */}
            <div className="bg-white rounded-lg p-4 border border-emerald-200">
              <label className="block text-xs text-gray-600 mb-2">Point Value (€ per point)</label>
              <input
                type="number"
                value={loyaltySettings.pointValue}
                onChange={(e) => setLoyaltySettings({ ...loyaltySettings, pointValue: parseFloat(e.target.value) || 0 })}
                className="w-full text-2xl font-semibold text-emerald-600 bg-transparent border-0 p-0 focus:outline-none focus:ring-0"
                min="0"
                step="0.01"
              />
              <p className="text-xs text-gray-500 mt-1">
                1 point = €{loyaltySettings.pointValue.toFixed(2)}
              </p>
            </div>

            {/* Save Button */}
            <div className="flex items-end">
              <Button
                onClick={handleSaveLoyalty}
                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                Save Changes
              </Button>
            </div>
          </div>
        )}
      </div>

      {/* Stats Cards */}
      {loyaltySettings.enabled && (
        <div className="grid grid-cols-3 gap-4 mb-6">
          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm text-gray-600">Total Points Issued</span>
              <TrendingUp className="w-4 h-4 text-emerald-600" />
            </div>
            <div className="text-3xl text-gray-900">127,450</div>
            <p className="text-xs text-gray-500 mt-1">To 342 customers</p>
          </div>

          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm text-gray-600">Points Redeemed</span>
              <Gift className="w-4 h-4 text-orange-600" />
            </div>
            <div className="text-3xl text-gray-900">45,230</div>
            <p className="text-xs text-gray-500 mt-1">€{(45230 * loyaltySettings.pointValue).toFixed(2)} in discounts</p>
          </div>

          <div className="bg-white rounded-xl border border-gray-200 p-6">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm text-gray-600">Active Balance</span>
              <TrendingUp className="w-4 h-4 text-blue-600" />
            </div>
            <div className="text-3xl text-gray-900">82,220</div>
            <p className="text-xs text-gray-500 mt-1">Unredeemed points</p>
          </div>
        </div>
      )}

      {/* Promotions Section */}
      <div className="mb-4">
        <h2 className="text-2xl text-gray-900 mb-2">Active Promotions</h2>
        <p className="text-gray-600">Create and manage promotional campaigns</p>
      </div>

      <div className="space-y-4">
        {/* Add Promotion Button */}
        <div className="flex justify-end">
          <Button
            onClick={() => {
              setEditingPromotion(null);
              setShowPromotionForm(true);
            }}
            className="bg-emerald-600 hover:bg-emerald-700 text-white"
          >
            <Plus className="w-4 h-4 mr-2" />
            Add Promotion
          </Button>
        </div>

        {/* Promotions List */}
        <div className="space-y-4">
          {promotions.map((promo) => (
            <div
              key={promo.id}
              className={`bg-white rounded-xl border-2 p-6 transition-all ${
                promo.isActive ? 'border-emerald-200' : 'border-gray-200'
              }`}
            >
              <div className="flex items-start justify-between mb-3">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <h3 className="text-lg text-gray-900">{promo.name}</h3>
                    <span className={`px-2 py-1 rounded text-xs ${
                      promo.isActive
                        ? 'bg-emerald-100 text-emerald-700'
                        : 'bg-gray-100 text-gray-700'
                    }`}>
                      {promo.isActive ? 'Active' : 'Paused'}
                    </span>
                    <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
                      {getPromotionTypeLabel(promo.type)}
                    </span>
                  </div>
                  <p className="text-gray-600">{promo.description}</p>
                </div>
                <div className="flex gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleTogglePromotion(promo.id)}
                  >
                    {promo.isActive ? (
                      <><Pause className="w-4 h-4 mr-1" /> Pause</>
                    ) : (
                      <><Play className="w-4 h-4 mr-1" /> Activate</>
                    )}
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleEditPromotion(promo)}
                  >
                    <Edit className="w-4 h-4" />
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => handleDeletePromotion(promo.id)}
                    className="text-red-600 hover:bg-red-50"
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                </div>
              </div>

              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <div className="text-gray-500 mb-1">Discount</div>
                  <div className="text-gray-900">
                    {promo.discountPercent && `${promo.discountPercent}%`}
                    {promo.discountAmount && `€${promo.discountAmount}`}
                  </div>
                </div>
                <div>
                  <div className="text-gray-500 mb-1">
                    <Calendar className="w-3 h-3 inline mr-1" />
                    Duration
                  </div>
                  <div className="text-gray-900">
                    {new Date(promo.startDate).toLocaleDateString()} - {new Date(promo.endDate).toLocaleDateString()}
                  </div>
                </div>
                {promo.startTime && promo.endTime && (
                  <div>
                    <div className="text-gray-500 mb-1">
                      <Clock className="w-3 h-3 inline mr-1" />
                      Time
                    </div>
                    <div className="text-gray-900">
                      {promo.startTime} - {promo.endTime}
                    </div>
                  </div>
                )}
                {promo.daysOfWeek && (
                  <div>
                    <div className="text-gray-500 mb-1">Days</div>
                    <div className="text-gray-900">
                      {promo.daysOfWeek.map(d => d.substring(0, 3)).join(', ')}
                    </div>
                  </div>
                )}
              </div>
            </div>
          ))}

          {promotions.length === 0 && (
            <div className="text-center py-12 bg-white rounded-xl border border-gray-200">
              <Tag className="w-12 h-12 text-gray-400 mx-auto mb-3" />
              <p className="text-gray-600 mb-4">No promotions yet</p>
              <Button
                onClick={() => setShowPromotionForm(true)}
                className="bg-emerald-600 hover:bg-emerald-700 text-white"
              >
                Create your first promotion
              </Button>
            </div>
          )}
        </div>

        {/* Promotion Form Modal (placeholder) */}
        {showPromotionForm && (
          <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
              {/* Header */}
              <div className="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between rounded-t-xl">
                <h2 className="text-2xl text-gray-900">
                  {editingPromotion ? 'Edit Promotion' : 'Add New Promotion'}
                </h2>
                <button
                  onClick={resetForm}
                  className="text-gray-400 hover:text-gray-600"
                >
                  <X className="w-6 h-6" />
                </button>
              </div>

              {/* Form Content */}
              <div className="p-6 space-y-6">
                {/* Basic Info */}
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm text-gray-700 mb-2">
                      Promotion Name <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                      placeholder="e.g., Happy Hour - 20% Off Drinks"
                      className="w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-2.5"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm text-gray-700 mb-2">
                        Promotion Type <span className="text-red-500">*</span>
                      </label>
                      <select
                        value={formData.type}
                        onChange={(e) => setFormData({ ...formData, type: e.target.value as Promotion['type'] })}
                        className="w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-2.5"
                      >
                        <option value="happy-hour">Happy Hour</option>
                        <option value="weekend-special">Weekend Special</option>
                        <option value="item-discount">Item Discount</option>
                      </select>
                    </div>

                    <div>
                      <label className="flex items-center gap-2 text-sm text-gray-700 mb-2">
                        <input
                          type="checkbox"
                          checked={formData.isActive}
                          onChange={(e) => setFormData({ ...formData, isActive: e.target.checked })}
                          className="w-4 h-4 text-emerald-600 rounded"
                        />
                        Activate immediately
                      </label>
                      <div className={`px-4 py-2.5 rounded-lg border ${formData.isActive ? 'bg-emerald-50 border-emerald-200' : 'bg-gray-50 border-gray-200'}`}>
                        <span className={`text-sm ${formData.isActive ? 'text-emerald-700' : 'text-gray-600'}`}>
                          {formData.isActive ? '✓ Will be active after saving' : 'Will be paused after saving'}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm text-gray-700 mb-2">
                      Description <span className="text-red-500">*</span>
                    </label>
                    <textarea
                      value={formData.description}
                      onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                      placeholder="Describe what customers get with this promotion..."
                      className="w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-2.5"
                      rows={3}
                    />
                  </div>
                </div>

                {/* Discount Settings */}
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg text-gray-900 mb-4">Discount Settings</h3>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm text-gray-700 mb-2">
                        Discount Type <span className="text-red-500">*</span>
                      </label>
                      <select
                        value={formData.discountType}
                        onChange={(e) => setFormData({ ...formData, discountType: e.target.value as 'percent' | 'fixed' })}
                        className="w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-2.5"
                      >
                        <option value="percent">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (€)</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-sm text-gray-700 mb-2">
                        Discount Value <span className="text-red-500">*</span>
                      </label>
                      <div className="relative">
                        <input
                          type="number"
                          value={formData.discountValue}
                          onChange={(e) => setFormData({ ...formData, discountValue: e.target.value })}
                          placeholder={formData.discountType === 'percent' ? '20' : '5.00'}
                          className="w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-2.5 pr-10"
                          min="0"
                          step={formData.discountType === 'percent' ? '1' : '0.01'}
                        />
                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">
                          {formData.discountType === 'percent' ? '%' : '€'}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Date & Time Settings */}
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg text-gray-900 mb-4">Schedule</h3>
                  <div className="grid grid-cols-2 gap-4 mb-4">
                    <div>
                      <label className="block text-sm text-gray-700 mb-2">
                        Start Date <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="date"
                        value={formData.startDate}
                        onChange={(e) => setFormData({ ...formData, startDate: e.target.value })}
                        className="w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-2.5"
                      />
                    </div>

                    <div>
                      <label className="block text-sm text-gray-700 mb-2">
                        End Date <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="date"
                        value={formData.endDate}
                        onChange={(e) => setFormData({ ...formData, endDate: e.target.value })}
                        className="w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-2.5"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm text-gray-700 mb-2">
                        Start Time (optional)
                      </label>
                      <input
                        type="time"
                        value={formData.startTime}
                        onChange={(e) => setFormData({ ...formData, startTime: e.target.value })}
                        className="w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-2.5"
                      />
                    </div>

                    <div>
                      <label className="block text-sm text-gray-700 mb-2">
                        End Time (optional)
                      </label>
                      <input
                        type="time"
                        value={formData.endTime}
                        onChange={(e) => setFormData({ ...formData, endTime: e.target.value })}
                        className="w-full text-sm text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 px-4 py-2.5"
                      />
                    </div>
                  </div>
                </div>

                {/* Days of Week */}
                <div className="border-t border-gray-200 pt-6">
                  <h3 className="text-lg text-gray-900 mb-2">Active Days (optional)</h3>
                  <p className="text-sm text-gray-500 mb-4">Leave empty to apply promotion every day</p>
                  <div className="flex flex-wrap gap-2">
                    {weekDays.map(day => (
                      <button
                        key={day}
                        type="button"
                        onClick={() => toggleDayOfWeek(day)}
                        className={`px-4 py-2 rounded-lg text-sm transition-all ${
                          formData.daysOfWeek.includes(day)
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                      >
                        {day}
                      </button>
                    ))}
                  </div>
                  {formData.daysOfWeek.length > 0 && (
                    <p className="text-xs text-gray-500 mt-3">
                      Active on: {formData.daysOfWeek.join(', ')}
                    </p>
                  )}
                </div>
              </div>

              {/* Footer */}
              <div className="sticky bottom-0 bg-gray-50 border-t border-gray-200 px-6 py-4 flex gap-3 rounded-b-xl">
                <Button
                  onClick={handleSavePromotion}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white flex-1"
                >
                  {editingPromotion ? 'Update Promotion' : 'Create Promotion'}
                </Button>
                <Button
                  variant="outline"
                  onClick={resetForm}
                  className="px-6"
                >
                  Cancel
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}