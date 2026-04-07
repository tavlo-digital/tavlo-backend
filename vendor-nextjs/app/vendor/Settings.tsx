import { useState, useEffect } from 'react';
import { 
  Building2, 
  CreditCard, 
  Receipt, 
  Globe, 
  Bell, 
  Users, 
  Palette, 
  Shield, 
  Download,
  Upload,
  Save,
  QrCode,
  Clock,
  Star,
  Percent,
  Mail,
  Phone,
  MapPin,
  Gift,
  Trash2,
  Key,
  Package,
  Settings as SettingsIcon,
  Lock,
  Eye,
  AlertCircle,
  CheckCircle,
  Info,
  Pencil,
  X,
  Check,
  ToggleLeft,
  ToggleRight
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { InventorySettingsTab } from './InventorySettingsTab';
import { TeamAccess } from './TeamAccess';
import { TaxRulesDisplay, CountrySelector } from './TaxRulesDisplay';
import type { Country } from '@/lib/taxRules';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface SettingsProps {
  vendorId: string;
  onNavigate?: (screen: string) => void;
  onVisibilityChange?: (isLive: boolean) => void;
  initialIsLiveAndDiscoverable?: boolean;
}

export function Settings({ vendorId, onNavigate, onVisibilityChange, initialIsLiveAndDiscoverable }: SettingsProps) {
  const [activeTab, setActiveTab] = useState('business');
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);

  // Business Information State
  const [businessInfo, setBusinessInfo] = useState({
    restaurantName: 'La Bella Vista',
    description: 'Authentic Italian cuisine in the heart of Vienna',
    businessRegNumber: 'FN 123456a',
    vatNumber: 'ATU12345678',
    email: 'info@labellavista.at',
    phone: '+43 1 234 5678',
    website: 'https://www.labellavista.at',
    address: 'Kärntner Straße 1, 1010 Wien, Austria',
    logo: '',
    coverPhoto: '',
    isLiveAndDiscoverable: initialIsLiveAndDiscoverable || false
  });

  // Update when prop changes
  useEffect(() => {
    if (initialIsLiveAndDiscoverable !== undefined) {
      setBusinessInfo(prev => ({
        ...prev,
        isLiveAndDiscoverable: initialIsLiveAndDiscoverable
      }));
    }
  }, [initialIsLiveAndDiscoverable]);

  useEffect(() => {
    if (activeTab === 'tables') {
      loadTableList();
    }
  }, [activeTab]);

  // Legal Information Approval Workflow State
  const [originalLegalInfo, setOriginalLegalInfo] = useState({
    restaurantName: 'La Bella Vista',
    businessRegNumber: 'FN 123456a',
    vatNumber: 'ATU12345678',
    companyType: 'GmbH',
    address: 'Kärntner Straße 1, 1010 Wien, Austria'
  });

  const [pendingLegalInfo, setPendingLegalInfo] = useState<any>(null);
  const [legalApprovalStatus, setLegalApprovalStatus] = useState<'none' | 'pending' | 'approved' | 'rejected'>('none');
  const [showConfirmationModal, setShowConfirmationModal] = useState(false);
  const [legalChanges, setLegalChanges] = useState<Array<{field: string, label: string, oldValue: string, newValue: string}>>([]);

  // Business Hours State
  const [businessHours, setBusinessHours] = useState({
    monday: { open: '11:00', close: '22:00', closed: false },
    tuesday: { open: '11:00', close: '22:00', closed: false },
    wednesday: { open: '11:00', close: '22:00', closed: false },
    thursday: { open: '11:00', close: '22:00', closed: false },
    friday: { open: '11:00', close: '23:00', closed: false },
    saturday: { open: '11:00', close: '23:00', closed: false },
    sunday: { open: '12:00', close: '21:00', closed: false }
  });

  // Payment Settings State
  const [paymentSettings, setPaymentSettings] = useState({
    paymentCollectionModel: 'on-site' as 'on-site' | 'online', // NEW: How customers pay
    acceptApplePay: true,
    acceptGooglePay: true,
    acceptCard: true,
    acceptCash: true,
    acceptCashTakeaway: true,  // New setting for takeaway cash payment
    acceptBankTransfer: false,
    acceptVisa: true,
    acceptMastercard: true,
    acceptAmex: false,
    currency: 'EUR',
    stripeEnabled: false,
    stripeAccountId: '',
    stripeOnboardingComplete: false,
    stripePublicKey: '',
    stripeSecretKey: ''
  });

  // Tax & Compliance State
  const [taxSettings, setTaxSettings] = useState({
    country: 'AT' as Country,
    vatRate: 13, // Legacy - will be replaced by category-based system
    serviceFeeRate: 5,
    invoicePrefix: 'LBV',
    nextInvoiceNumber: 1001,
    autoGenerateReceipts: true,
    companyType: 'GmbH',
    firstInvoiceIssued: false // Locks invoice numbering after first invoice
  });

  // Live table list state
  const [tableList, setTableList] = useState<{ id: string; number: number; name: string; isActive: boolean }[]>([]);
  const [tableListLoading, setTableListLoading] = useState(false);
  const [editingTableId, setEditingTableId] = useState<string | null>(null);
  const [editingTableName, setEditingTableName] = useState('');
  const [syncingTables, setSyncingTables] = useState(false);

  const loadTableList = () => {
    if (!vendorId) return;
    setTableListLoading(true);
    api.getTables(vendorId)
      .then((data: any) => setTableList(Array.isArray(data) ? data : []))
      .catch(() => toast.error('Failed to load tables'))
      .finally(() => setTableListLoading(false));
  };

  const handleSyncTables = async () => {
    const count = tableSettings.numberOfTables;
    if (!count || count < 1) return;
    setSyncingTables(true);
    try {
      await api.syncTables(vendorId, count);
      toast.success(`Tables synced to ${count}`);
      loadTableList();
    } catch {
      toast.error('Failed to sync tables');
    } finally {
      setSyncingTables(false);
    }
  };

  const handleRenameTable = async (tableId: string) => {
    if (!editingTableName.trim()) return;
    try {
      await api.updateTable(vendorId, tableId, { name: editingTableName.trim() });
      setTableList(prev => prev.map(t => t.id === tableId ? { ...t, name: editingTableName.trim() } : t));
      setEditingTableId(null);
      toast.success('Table renamed');
    } catch {
      toast.error('Failed to rename table');
    }
  };

  const handleToggleTableActive = async (tableId: string, current: boolean) => {
    try {
      await api.updateTable(vendorId, tableId, { is_active: !current });
      setTableList(prev => prev.map(t => t.id === tableId ? { ...t, isActive: !current } : t));
      toast.success(current ? 'Table deactivated' : 'Table activated');
    } catch {
      toast.error('Failed to update table');
    }
  };

  // Table Management State
  const [tableSettings, setTableSettings] = useState({
    numberOfTables: 20,
    tablePrefix: 'T',
    enableSharedBasket: true,
    maxGuestsPerTable: 10,
    // Reservation settings
    totalTables: 20,
    maxTableCapacity: 6,
    enableReservations: true
  });

  // Ordering Settings State
  const [orderSettings, setOrderSettings] = useState({
    autoAcceptOrders: false,
    estimatedPrepTime: 20,
    maxOrdersPerSlot: 50,
    allowGuestOrdering: true,
    requirePhoneNumber: false,
    minOrderAmount: 0,
    maxOrderAmount: 1000
  });

  // Notification Settings State
  const [notificationSettings, setNotificationSettings] = useState({
    emailNewOrder: true,
    emailReview: true,
    smsNewOrder: false,
    pushNewOrder: true,
    pushOrderReady: true,
    notificationEmail: 'notifications@labellavista.at'
  });

  // Review Settings State
  const [reviewSettings, setReviewSettings] = useState({
    enableReviews: true,
    enableMenuReviews: false,
    allowAnonymousReviews: false
  });

  // Language Settings State
  const [languageSettings, setLanguageSettings] = useState({
    defaultLanguage: 'de',
    supportedLanguages: ['en', 'de', 'it', 'ar', 'zh', 'tr'],
    dateFormat: 'DD.MM.YYYY',
    timeFormat: '24h'
  });

  // Loyalty Settings State
  const [loyaltySettings, setLoyaltySettings] = useState({
    enableLoyalty: true,
    pointsPerEuro: 1,
    minimumRedemption: 100,
    redemptionRate: 0.05, // How much € each point is worth when redeemed (0.05 = 100 points = €5)
    pointsExpiry: 365,
    enableTiers: false
  });

  // Privacy Settings State
  const [privacySettings, setPrivacySettings] = useState({
    dataRetentionDays: 365,
    allowDataExport: true,
    gdprCompliant: true,
    showInTopCustomers: true
  });

  // Appearance Settings State
  const [appearanceSettings, setAppearanceSettings] = useState({
    menuTheme: 'classic', // 'classic' | 'modern' | 'minimal' | 'vibrant'
    primaryColor: '#1a1a1a',
    accentColor: '#f59e0b',
    menuLayout: 'grid', // 'grid' | 'list'
    backgroundImage: '' // URL to background image
  });

  const tabs = [
    { id: 'business', label: 'Business Info', icon: Building2 },
    { id: 'payment', label: 'Payment', icon: CreditCard },
    { id: 'tax', label: 'Tax & Receipts', icon: Receipt },
    { id: 'tables', label: 'Tables & QR', icon: QrCode },
    { id: 'ordering', label: 'Ordering', icon: Clock },
    { id: 'inventory', label: 'Inventory', icon: Package },
    { id: 'team', label: 'Team & Access', icon: Users },
    { id: 'notifications', label: 'Notifications', icon: Bell },
    { id: 'reviews', label: 'Reviews', icon: Star },
    { id: 'language', label: 'Language', icon: Globe },
    { id: 'appearance', label: 'Appearance', icon: Palette },
    { id: 'privacy', label: 'Data Visibility & Exports', icon: Shield }
  ];

  // Load settings on mount
  useEffect(() => {
    loadSettings();
  }, [vendorId]);

  const loadSettings = async () => {
    try {
      setLoading(true);
      const settings = await api.getVendorSettings(vendorId) as any;
      
      // Update all state with loaded settings
      setBusinessInfo({
        restaurantName: settings.restaurantName,
        description: settings.description,
        businessRegNumber: settings.businessRegNumber,
        vatNumber: settings.vatNumber,
        email: settings.email,
        phone: settings.phone,
        website: settings.website && !/^https?:\/\//i.test(settings.website) ? `https://${settings.website}` : (settings.website || ''),
        address: settings.address,
        logo: settings.logo || '',
        coverPhoto: settings.coverPhoto || '',
        isLiveAndDiscoverable: settings.isLiveAndDiscoverable || false
      });
      
      setBusinessHours(settings.businessHours);
      
      setPaymentSettings({
        paymentCollectionModel: settings.paymentCollectionModel || 'on-site',
        acceptApplePay: settings.acceptApplePay,
        acceptGooglePay: settings.acceptGooglePay,
        acceptCard: settings.acceptCard,
        acceptCash: settings.acceptCash,
        acceptCashTakeaway: settings.acceptCashTakeaway !== undefined ? settings.acceptCashTakeaway : true,
        acceptBankTransfer: settings.acceptBankTransfer || false,
        acceptVisa: settings.acceptVisa !== false,
        acceptMastercard: settings.acceptMastercard !== false,
        acceptAmex: settings.acceptAmex || false,
        currency: settings.currency,
        stripeEnabled: settings.stripeEnabled,
        stripeAccountId: settings.stripeAccountId || '',
        stripeOnboardingComplete: settings.stripeOnboardingComplete || false,
        stripePublicKey: '',
        stripeSecretKey: ''
      });
      
      setTaxSettings({
        country: (settings.country || 'AT') as Country,
        vatRate: settings.vatRate,
        serviceFeeRate: settings.serviceFeeRate,
        invoicePrefix: settings.invoicePrefix,
        nextInvoiceNumber: settings.nextInvoiceNumber,
        autoGenerateReceipts: settings.autoGenerateReceipts,
        companyType: settings.companyType,
        firstInvoiceIssued: settings.firstInvoiceIssued || false
      });
      
      setTableSettings({
        numberOfTables: settings.numberOfTables,
        tablePrefix: settings.tablePrefix,
        enableSharedBasket: settings.enableSharedBasket,
        maxGuestsPerTable: settings.maxGuestsPerTable,
        totalTables: settings.totalTables || 20,
        maxTableCapacity: settings.maxTableCapacity || 6,
        enableReservations: settings.enableReservations !== false
      });
      
      setOrderSettings({
        autoAcceptOrders: settings.autoAcceptOrders,
        estimatedPrepTime: settings.estimatedPrepTime,
        maxOrdersPerSlot: settings.maxOrdersPerSlot,
        allowGuestOrdering: settings.allowGuestOrdering,
        requirePhoneNumber: settings.requirePhoneNumber,
        minOrderAmount: settings.minOrderAmount,
        maxOrderAmount: settings.maxOrderAmount
      });
      
      setNotificationSettings({
        emailNewOrder: settings.emailNewOrder,
        emailReview: settings.emailReview,
        smsNewOrder: settings.smsNewOrder,
        pushNewOrder: settings.pushNewOrder,
        pushOrderReady: settings.pushOrderReady,
        notificationEmail: settings.notificationEmail
      });
      
      setReviewSettings({
        enableReviews: settings.enableReviews !== undefined ? settings.enableReviews : true,
        enableMenuReviews: settings.enableMenuReviews !== undefined ? settings.enableMenuReviews : false,
        allowAnonymousReviews: settings.allowAnonymousReviews !== undefined ? settings.allowAnonymousReviews : false
      });
      
      setLanguageSettings({
        defaultLanguage: settings.defaultLanguage,
        supportedLanguages: settings.supportedLanguages,
        dateFormat: settings.dateFormat,
        timeFormat: settings.timeFormat
      });
      
      setLoyaltySettings({
        enableLoyalty: settings.enableLoyalty,
        pointsPerEuro: settings.pointsPerEuro,
        minimumRedemption: settings.minimumRedemption,
        redemptionRate: settings.redemptionRate || 0.05,
        pointsExpiry: settings.pointsExpiry,
        enableTiers: settings.enableTiers
      });
      
      setPrivacySettings({
        dataRetentionDays: settings.dataRetentionDays,
        allowDataExport: settings.allowDataExport,
        gdprCompliant: settings.gdprCompliant,
        showInTopCustomers: settings.showInTopCustomers
      });
      
      setAppearanceSettings({
        menuTheme: settings.menuTheme || 'classic',
        primaryColor: settings.primaryColor || '#1a1a1a',
        accentColor: settings.accentColor || '#f59e0b',
        menuLayout: settings.menuLayout || 'grid',
        backgroundImage: settings.backgroundImage || ''
      });
    } catch (error) {
      console.error('Failed to load settings:', error);
      toast.error('Failed to load settings');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    // STEP 1: Check for changes in sensitive legal fields
    const sensitiveFields = [
      { field: 'restaurantName', label: 'Restaurant Name', current: originalLegalInfo.restaurantName, new: businessInfo.restaurantName },
      { field: 'businessRegNumber', label: 'Business Registration Number', current: originalLegalInfo.businessRegNumber, new: businessInfo.businessRegNumber },
      { field: 'vatNumber', label: 'VAT Number', current: originalLegalInfo.vatNumber, new: businessInfo.vatNumber },
      { field: 'companyType', label: 'Company Type', current: originalLegalInfo.companyType, new: taxSettings.companyType },
      { field: 'address', label: 'Legal Address', current: originalLegalInfo.address, new: businessInfo.address }
    ];

    const detectedChanges = sensitiveFields.filter(f => f.current !== f.new);

    // If legal fields changed and not already pending, show confirmation modal
    if (detectedChanges.length > 0 && legalApprovalStatus !== 'pending') {
      setLegalChanges(detectedChanges.map(c => ({
        field: c.field,
        label: c.label,
        oldValue: c.current,
        newValue: c.new
      })));
      setShowConfirmationModal(true);
      return; // Stop save, wait for confirmation
    }

    // If already pending, block save
    if (legalApprovalStatus === 'pending') {
      toast.error('Cannot save while changes are pending admin approval');
      return;
    }

    // Normal save for non-legal fields or when no legal changes detected
    setSaving(true);
    try {
      // Normalize website URL before saving
      const normalizedWebsite = businessInfo.website && !/^https?:\/\//i.test(businessInfo.website)
        ? `https://${businessInfo.website}`
        : businessInfo.website;

      // Combine all settings into one object
      const allSettings = {
        ...businessInfo,
        website: normalizedWebsite,
        businessHours,
        ...paymentSettings,
        ...taxSettings,
        ...tableSettings,
        ...orderSettings,
        ...notificationSettings,
        ...reviewSettings,
        ...languageSettings,
        ...loyaltySettings,
        ...privacySettings,
        ...appearanceSettings
      };
      
      await api.updateVendorSettings(vendorId, allSettings);
      toast.success('Settings saved successfully');
      
      // Trigger settings refresh event for the app
      window.dispatchEvent(new CustomEvent('settings-updated'));
    } catch (error) {
      console.error('Failed to save settings:', error);
      toast.error('Failed to save settings');
    } finally {
      setSaving(false);
    }
  };

  // STEP 2 & 3: Handle confirmation and submit for approval
  const handleSubmitForApproval = async () => {
    setShowConfirmationModal(false);
    setSaving(true);

    try {
      const pendingData = {
        restaurantName: businessInfo.restaurantName,
        legalEntityName: businessInfo.businessRegNumber, // maps to legal_entity_name via backend
        businessRegistrationNumber: businessInfo.businessRegNumber,
        vatNumber: businessInfo.vatNumber,
        address: businessInfo.address,
      };

      await api.submitLegalInfoForApproval(vendorId, pendingData);

      setPendingLegalInfo(pendingData);
      setLegalApprovalStatus('pending');

      toast.success('Legal information changes submitted for admin approval');
    } catch (error) {
      console.error('Failed to submit for approval:', error);
      toast.error('Failed to submit changes for approval');
    } finally {
      setSaving(false);
    }
  };

  const handleResetMenuWithTranslations = async () => {
    if (!confirm('This will reset your menu to the default items with all translations. Custom menu items will be lost. Continue?')) {
      return;
    }
    
    setSaving(true);
    try {
      await api.seedData(true); // Force reseed
      toast.success('Menu reset successfully with translations! Refreshing page...');
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    } catch (error) {
      console.error('Failed to reset menu:', error);
      toast.error('Failed to reset menu');
    } finally {
      setSaving(false);
    }
  };

  const handleExportData = async () => {
    try {
      const data = await api.exportVendorData(vendorId);
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `vendor-${vendorId}-data.json`;
      a.click();
      URL.revokeObjectURL(url);
      toast.success('Data exported successfully.');
    } catch (error) {
      console.error('Failed to export data:', error);
      toast.error('Failed to export data.');
    }
  };

  const handleGenerateQRCodes = () => {
    toast.success('QR codes generated successfully');
  };

  const renderBusinessInfo = () => (
    <div className="space-y-8">
      {/* SECTION 1: Legal Business Identity (NEW STRUCTURE) */}
      <Card className="border-2">
        <CardHeader className="bg-gray-50 border-b">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center">
              <Lock className="w-5 h-5 text-gray-700" />
            </div>
            <div>
              <CardTitle className="text-lg">Legal Business Information</CardTitle>
              <CardDescription className="text-sm mt-1">
                This information is used for invoices, tax compliance, and legal identification.
              </CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent className="pt-6">
          {/* STEP 3: Pending Approval Banner */}
          {legalApprovalStatus === 'pending' && (
            <Alert className="mb-6 bg-yellow-50 border-yellow-300">
              <AlertCircle className="h-4 w-4 text-yellow-700" />
              <AlertDescription className="text-yellow-900">
                <strong>Changes pending Tavlo admin approval.</strong> Your submitted changes are under review. Current approved values remain active for invoices and customer-facing pages.
              </AlertDescription>
            </Alert>
          )}

          {/* STEP 5: Rejected State Banner */}
          {legalApprovalStatus === 'rejected' && (
            <Alert className="mb-6 bg-red-50 border-red-300">
              <AlertCircle className="h-4 w-4 text-red-700" />
              <AlertDescription className="text-red-900">
                <strong>Changes were not approved.</strong> Your submitted changes have been rejected. Original values remain active. Please contact support for details.
              </AlertDescription>
            </Alert>
          )}

          {/* STEP 5: Approved State Indicator (subtle) */}
          {legalApprovalStatus === 'approved' && (
            <Alert className="mb-6 bg-green-50 border-green-300">
              <CheckCircle className="h-4 w-4 text-green-700" />
              <AlertDescription className="text-green-900">
                <strong>Changes approved!</strong> Your legal information has been updated successfully.
              </AlertDescription>
            </Alert>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                Business Registration Number
                {legalApprovalStatus === 'pending' && (
                  <Badge variant="outline" className="bg-yellow-100 text-yellow-800 border-yellow-300">
                    Pending approval
                  </Badge>
                )}
              </label>
              <input
                type="text"
                value={businessInfo.businessRegNumber}
                onChange={(e) => setBusinessInfo({...businessInfo, businessRegNumber: e.target.value})}
                disabled={legalApprovalStatus === 'pending'}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 disabled:bg-gray-100 disabled:cursor-not-allowed"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                VAT Number
                {legalApprovalStatus === 'pending' && (
                  <Badge variant="outline" className="bg-yellow-100 text-yellow-800 border-yellow-300">
                    Pending approval
                  </Badge>
                )}
              </label>
              <input
                type="text"
                value={businessInfo.vatNumber}
                onChange={(e) => setBusinessInfo({...businessInfo, vatNumber: e.target.value})}
                disabled={legalApprovalStatus === 'pending'}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 disabled:bg-gray-100 disabled:cursor-not-allowed"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                Company Type
                {legalApprovalStatus === 'pending' && (
                  <Badge variant="outline" className="bg-yellow-100 text-yellow-800 border-yellow-300">
                    Pending approval
                  </Badge>
                )}
              </label>
              <select
                value={taxSettings.companyType}
                onChange={(e) => setTaxSettings({...taxSettings, companyType: e.target.value})}
                disabled={legalApprovalStatus === 'pending'}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 disabled:bg-gray-100 disabled:cursor-not-allowed"
              >
                <option value="GmbH">GmbH</option>
                <option value="AG">AG</option>
                <option value="OG">OG</option>
                <option value="KG">KG</option>
                <option value="Einzelunternehmen">Einzelunternehmen</option>
              </select>
            </div>
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                <MapPin className="w-4 h-4" />
                Legal Address
                {legalApprovalStatus === 'pending' && (
                  <Badge variant="outline" className="bg-yellow-100 text-yellow-800 border-yellow-300">
                    Pending approval
                  </Badge>
                )}
              </label>
              <textarea
                value={businessInfo.address}
                onChange={(e) => setBusinessInfo({...businessInfo, address: e.target.value})}
                disabled={legalApprovalStatus === 'pending'}
                rows={2}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 disabled:bg-gray-100 disabled:cursor-not-allowed"
              />
            </div>
          </div>
          
          <div className="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <div className="flex items-start gap-2 text-sm text-amber-900">
              <Shield className="w-4 h-4 flex-shrink-0 mt-0.5" />
              <div className="space-y-1">
                <p>
                  <strong>Note:</strong> Any changes to legal information require Tavlo admin approval for verification.
                </p>
                <p>
                  Changes to legal information may affect invoices and tax compliance.
                </p>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* SECTION 2: Public Restaurant Profile (RESTRUCTURE) */}
      <Card className="border-2">
        <CardHeader className="bg-blue-50 border-b">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-blue-200 rounded-lg flex items-center justify-center">
              <Eye className="w-5 h-5 text-blue-700" />
            </div>
            <div>
              <CardTitle className="text-lg">Restaurant Profile (Visible to Customers)</CardTitle>
              <CardDescription className="text-sm mt-1">
                This information is shown on your Tavlo page and digital menu.
              </CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent className="pt-6">
          <div className="space-y-4">
            {/* Visibility Toggle */}
            <div className="bg-gradient-to-r from-green-50 to-blue-50 border-2 border-green-200 rounded-lg p-4 mb-4">
              <div className="flex items-start justify-between">
                <div className="flex items-start gap-3 flex-1">
                  <div className="bg-green-100 rounded-lg p-2 mt-0.5">
                    <Globe className="w-5 h-5 text-green-700" />
                  </div>
                  <div className="flex-1">
                    <h3 className="font-semibold text-gray-900 mb-1">Restaurant Visibility</h3>
                    <p className="text-sm text-gray-600 mb-3">
                      Control whether your restaurant is discoverable on Tavlo's platform
                    </p>
                    <div className="flex items-center gap-2">
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={businessInfo.isLiveAndDiscoverable || false}
                          onChange={(e) => {
                            const newValue = e.target.checked;
                            setBusinessInfo({...businessInfo, isLiveAndDiscoverable: newValue});
                            if (onVisibilityChange) {
                              onVisibilityChange(newValue);
                            }
                            toast.success(
                              newValue 
                                ? 'Your restaurant is now live and discoverable!' 
                                : 'Your restaurant is now hidden from discovery'
                            );
                          }}
                          className="sr-only peer"
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-green-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        <span className="ml-3 text-sm font-medium text-gray-900">
                          {businessInfo.isLiveAndDiscoverable ? 'Live & Discoverable' : 'Hidden'}
                        </span>
                      </label>
                    </div>
                    {businessInfo.isLiveAndDiscoverable && (
                      <p className="text-xs text-green-700 mt-2 flex items-center gap-1">
                        <CheckCircle className="w-3 h-3" />
                        Customers can find and order from your restaurant
                      </p>
                    )}
                    {!businessInfo.isLiveAndDiscoverable && (
                      <p className="text-xs text-gray-600 mt-2 flex items-center gap-1">
                        <Info className="w-3 h-3" />
                        Only accessible via direct QR code scans
                      </p>
                    )}
                  </div>
                </div>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                Restaurant Name
                {legalApprovalStatus === 'pending' && (
                  <Badge variant="outline" className="bg-yellow-100 text-yellow-800 border-yellow-300">
                    Pending approval
                  </Badge>
                )}
              </label>
              <input
                type="text"
                value={businessInfo.restaurantName}
                onChange={(e) => setBusinessInfo({...businessInfo, restaurantName: e.target.value})}
                disabled={legalApprovalStatus === 'pending'}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 disabled:bg-gray-100 disabled:cursor-not-allowed"
              />
              <p className="text-xs text-gray-500 mt-1">
                Note: Restaurant name can be changed once, then any modification needs Tavlo admin's approval.
              </p>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Description</label>
              <textarea
                value={businessInfo.description}
                onChange={(e) => setBusinessInfo({...businessInfo, description: e.target.value})}
                rows={3}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
                placeholder="Tell customers about your restaurant..."
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                  <Mail className="w-4 h-4" />
                  Email
                </label>
                <input
                  type="email"
                  value={businessInfo.email}
                  onChange={(e) => setBusinessInfo({...businessInfo, email: e.target.value})}
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                  <Phone className="w-4 h-4" />
                  Phone
                </label>
                <input
                  type="tel"
                  value={businessInfo.phone}
                  onChange={(e) => setBusinessInfo({...businessInfo, phone: e.target.value})}
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                <Globe className="w-4 h-4" />
                Website
              </label>
              <input
                type="url"
                value={businessInfo.website}
                onChange={(e) => setBusinessInfo({...businessInfo, website: e.target.value})}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
                placeholder="https://..."
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* SECTION 3: Branding (CONTEXTUALIZED) */}
      <Card className="border-2">
        <CardHeader className="bg-purple-50 border-b">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-purple-200 rounded-lg flex items-center justify-center">
              <Palette className="w-5 h-5 text-purple-700" />
            </div>
            <div>
              <CardTitle className="text-lg">Branding & Appearance</CardTitle>
              <CardDescription className="text-sm mt-1">
                These images are shown on your restaurant page, QR menu, and ordering experience.
              </CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent className="pt-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Logo</label>
              <div className="border-2 border-dashed rounded-lg p-6 text-center relative overflow-hidden hover:border-gray-400 transition-colors">
                {businessInfo.logo ? (
                  <div className="relative">
                    <img src={businessInfo.logo} alt="Logo" className="max-h-32 mx-auto mb-2" />
                    <button
                      onClick={() => setBusinessInfo({...businessInfo, logo: ''})}
                      className="text-sm text-red-600 hover:underline"
                    >
                      Remove
                    </button>
                  </div>
                ) : (
                  <>
                    <Upload className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                    <p className="text-sm text-gray-500">Click to upload logo</p>
                    <p className="text-xs text-gray-400 mt-1">PNG, JPG up to 5MB</p>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={async (e) => {
                        const file = e.target.files?.[0];
                        if (file) {
                          try {
                            const result = await api.uploadLogo(vendorId, file);
                            setBusinessInfo({...businessInfo, logo: result.logoUrl});
                            toast.success('Logo uploaded successfully');
                          } catch {
                            toast.error('Failed to upload logo');
                          }
                        }
                      }}
                      className="absolute inset-0 opacity-0 cursor-pointer"
                    />
                  </>
                )}
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Cover Photo</label>
              <div className="border-2 border-dashed rounded-lg p-6 text-center relative overflow-hidden hover:border-gray-400 transition-colors">
                {businessInfo.coverPhoto ? (
                  <div className="relative">
                    <img src={businessInfo.coverPhoto} alt="Cover" className="max-h-32 mx-auto mb-2" />
                    <button
                      onClick={() => setBusinessInfo({...businessInfo, coverPhoto: ''})}
                      className="text-sm text-red-600 hover:underline"
                    >
                      Remove
                    </button>
                  </div>
                ) : (
                  <>
                    <Upload className="w-8 h-8 mx-auto mb-2 text-gray-400" />
                    <p className="text-sm text-gray-500">Click to upload cover</p>
                    <p className="text-xs text-gray-400 mt-1">PNG, JPG up to 10MB</p>
                    <input
                      type="file"
                      accept="image/*"
                      onChange={async (e) => {
                        const file = e.target.files?.[0];
                        if (file) {
                          try {
                            const result = await api.uploadCoverPhoto(vendorId, file);
                            setBusinessInfo({...businessInfo, coverPhoto: result.coverPhotoUrl});
                            toast.success('Cover photo uploaded successfully');
                          } catch {
                            toast.error('Failed to upload cover photo');
                          }
                        }
                      }}
                      className="absolute inset-0 opacity-0 cursor-pointer"
                    />
                  </>
                )}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* SECTION 4: Business Hours (OPERATIONAL CLARITY) */}
      <Card className="border-2">
        <CardHeader className="bg-green-50 border-b">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-green-200 rounded-lg flex items-center justify-center">
              <Clock className="w-5 h-5 text-green-700" />
            </div>
            <div>
              <CardTitle className="text-lg">Business Hours</CardTitle>
              <CardDescription className="text-sm mt-1">
                Business hours control when customers can place orders and make reservations.
              </CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent className="pt-6">
          <div className="space-y-3">
            {Object.entries(businessHours).map(([day, hours]) => (
              <div key={day} className="flex items-center gap-4">
                <div className="w-28 capitalize font-medium text-gray-700">{day}</div>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={!hours.closed}
                    onChange={(e) => setBusinessHours({
                      ...businessHours,
                      [day]: { ...hours, closed: !e.target.checked }
                    })}
                    className="rounded"
                  />
                  <span className="text-sm">Open</span>
                </label>
                {!hours.closed && (
                  <>
                    <input
                      type="time"
                      value={hours.open}
                      onChange={(e) => setBusinessHours({
                        ...businessHours,
                        [day]: { ...hours, open: e.target.value }
                      })}
                      className="px-3 py-1 border rounded-lg text-sm"
                    />
                    <span className="text-gray-500">to</span>
                    <input
                      type="time"
                      value={hours.close}
                      onChange={(e) => setBusinessHours({
                        ...businessHours,
                        [day]: { ...hours, close: e.target.value }
                      })}
                      className="px-3 py-1 border rounded-lg text-sm"
                    />
                  </>
                )}
                {hours.closed && <span className="text-gray-400 text-sm">Closed</span>}
              </div>
            ))}
          </div>
          
          <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <p className="text-sm text-blue-900">
              <strong>Note:</strong> Outside these hours, ordering may be disabled depending on your Ordering settings.
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  );

  const renderPaymentSettings = () => {
    // Helper function to build customer-facing preview
    const getCustomerFacingPreview = () => {
      const preview = [];
      
      if (paymentSettings.paymentCollectionModel === 'on-site') {
        if (paymentSettings.acceptCash) {
          preview.push('Dine-in: Pay at restaurant');
        }
        if (paymentSettings.acceptCashTakeaway) {
          preview.push('Takeaway: Cash on pickup available');
        }
      } else {
        // Online payments
        const methods = [];
        if (paymentSettings.acceptCard) methods.push('Card');
        if (paymentSettings.acceptApplePay) methods.push('Apple Pay');
        if (paymentSettings.acceptGooglePay) methods.push('Google Pay');
        if (methods.length > 0) {
          preview.push(`Online payments: ${methods.join(', ')}`);
        }
      }
      
      return preview.length > 0 ? preview : ['No payment methods configured'];
    };

    return (
      <div className="space-y-8">
        {/* Section 1: Payment Collection Model - TOP PRIORITY */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-2">How Customers Pay</h3>
          <p className="text-sm text-gray-700 mb-4">
            This setting defines how payments are collected and how orders are confirmed.
          </p>
          
          <div className="space-y-3">
            <label className="flex items-start gap-3 p-4 border-2 rounded-lg cursor-pointer hover:bg-white transition-colors bg-white">
              <input
                type="radio"
                name="paymentCollectionModel"
                checked={paymentSettings.paymentCollectionModel === 'on-site'}
                onChange={() => setPaymentSettings({...paymentSettings, paymentCollectionModel: 'on-site'})}
                className="mt-1"
              />
              <div className="flex-1">
                <div className="font-medium text-gray-900">Customers pay on-site at the restaurant</div>
                <div className="text-sm text-gray-600 mt-1">
                  Orders are placed via Tavlo and paid directly at the restaurant (cash or external terminal).
                </div>
              </div>
            </label>

            <label className="flex items-start gap-3 p-4 border-2 rounded-lg cursor-pointer hover:bg-white transition-colors bg-white">
              <input
                type="radio"
                name="paymentCollectionModel"
                checked={paymentSettings.paymentCollectionModel === 'online'}
                onChange={() => setPaymentSettings({...paymentSettings, paymentCollectionModel: 'online'})}
                className="mt-1"
              />
              <div className="flex-1">
                <div className="font-medium text-gray-900">Customers pay online via Tavlo</div>
                <div className="text-sm text-gray-600 mt-1">
                  Customers pay online during checkout. Tavlo handles the payment process.
                </div>
              </div>
            </label>
          </div>
        </div>

        {/* Section 2: Available Payment Methods */}
        <div>
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Available Payment Methods</h3>
          
          <div className="space-y-4">
            {/* Card Payments Group */}
            {paymentSettings.paymentCollectionModel === 'online' && (
              <div className="border rounded-lg p-4">
                <div className="font-medium text-gray-900 mb-3">Card Payments (Digital Wallets)</div>
                <div className="space-y-3 pl-4">
                  <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                    <input
                      type="checkbox"
                      checked={paymentSettings.acceptApplePay}
                      onChange={(e) => setPaymentSettings({...paymentSettings, acceptApplePay: e.target.checked})}
                      className="rounded"
                    />
                    <CreditCard className="w-5 h-5 text-gray-600" />
                    <span>Apple Pay</span>
                  </label>
                  
                  <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                    <input
                      type="checkbox"
                      checked={paymentSettings.acceptGooglePay}
                      onChange={(e) => setPaymentSettings({...paymentSettings, acceptGooglePay: e.target.checked})}
                      className="rounded"
                    />
                    <CreditCard className="w-5 h-5 text-gray-600" />
                    <span>Google Pay</span>
                  </label>
                </div>
              </div>
            )}

            {/* Credit/Debit Card */}
            {paymentSettings.paymentCollectionModel === 'online' && (
              <div className="border rounded-lg p-4">
                <label className="flex items-center gap-3 cursor-pointer hover:bg-gray-50 mb-3">
                  <input
                    type="checkbox"
                    checked={paymentSettings.acceptCard}
                    onChange={(e) => setPaymentSettings({...paymentSettings, acceptCard: e.target.checked})}
                    className="rounded"
                  />
                  <CreditCard className="w-5 h-5 text-gray-600" />
                  <div className="flex-1">
                    <span className="font-medium">Card Payments</span>
                    <div className="text-sm text-gray-600">Credit / Debit Card</div>
                  </div>
                </label>
                {paymentSettings.acceptCard && (
                  <div className="space-y-2 pl-8 border-l-2 border-gray-200 ml-4">
                    <p className="text-xs text-gray-500 mb-2">Accepted card types:</p>
                    {[
                      { key: 'acceptVisa', label: 'Visa' },
                      { key: 'acceptMastercard', label: 'Mastercard' },
                      { key: 'acceptAmex', label: 'American Express' },
                    ].map(({ key, label }) => (
                      <label key={key} className="flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={paymentSettings[key as keyof typeof paymentSettings] as boolean}
                          onChange={(e) => setPaymentSettings({...paymentSettings, [key]: e.target.checked})}
                          className="rounded"
                        />
                        <span className="text-sm">{label}</span>
                      </label>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* Bank Transfer */}
            {paymentSettings.paymentCollectionModel === 'online' && (
              <label className="flex items-center gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                <input
                  type="checkbox"
                  checked={paymentSettings.acceptBankTransfer}
                  onChange={(e) => setPaymentSettings({...paymentSettings, acceptBankTransfer: e.target.checked})}
                  className="rounded"
                />
                <span className="text-lg">🏦</span>
                <div className="flex-1">
                  <span className="font-medium">Bank Transfer (SEPA)</span>
                  <div className="text-sm text-gray-600">SEPA / eps bank transfer</div>
                </div>
              </label>
            )}

            {/* Section 3: Cash Handling */}
            {paymentSettings.paymentCollectionModel === 'on-site' && (
              <>
                <label className="flex items-start gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                  <input
                    type="checkbox"
                    checked={paymentSettings.acceptCash}
                    onChange={(e) => setPaymentSettings({...paymentSettings, acceptCash: e.target.checked})}
                    className="rounded mt-1"
                  />
                  <span className="text-lg">💵</span>
                  <div className="flex-1">
                    <div className="font-medium text-gray-900">Cash (Dine-in)</div>
                    <div className="text-sm text-gray-600 mt-1">
                      Customer pays at the restaurant. Order is confirmed immediately.
                    </div>
                  </div>
                </label>
                
                <label className="flex items-start gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                  <input
                    type="checkbox"
                    checked={paymentSettings.acceptCashTakeaway}
                    onChange={(e) => setPaymentSettings({...paymentSettings, acceptCashTakeaway: e.target.checked})}
                    className="rounded mt-1"
                  />
                  <span className="text-lg">🛍️</span>
                  <div className="flex-1">
                    <div className="font-medium text-gray-900">Cash for Takeaway Orders</div>
                    <div className="text-sm text-gray-600 mt-1">
                      Customer pays when picking up the order. Order may require manual confirmation by the restaurant.
                    </div>
                  </div>
                </label>
              </>
            )}
          </div>
        </div>

        {/* Section 4: Customer-Facing Preview */}
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
          <h4 className="font-semibold text-gray-900 mb-3">What Customers Will See</h4>
          <div className="space-y-2">
            {getCustomerFacingPreview().map((item, index) => (
              <div key={index} className="flex items-start gap-2 text-sm text-gray-700">
                <span className="text-green-600 mt-0.5">✓</span>
                <span>{item}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Section 5: Currency (Read-only) */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Currency</label>
          <div className="relative">
            <select
              value={paymentSettings.currency}
              disabled
              className="w-full px-3 py-2 border rounded-lg bg-gray-100 text-gray-700 cursor-not-allowed"
            >
              <option value="EUR">EUR (€)</option>
              <option value="USD">USD ($)</option>
              <option value="GBP">GBP (£)</option>
              <option value="CHF">CHF (Fr.)</option>
            </select>
          </div>
          <p className="text-xs text-gray-500 mt-2">
            Currency is defined by the restaurant's country and cannot be changed.
          </p>
        </div>

        {/* Section 6: Stripe Connect */}
        {paymentSettings.paymentCollectionModel === 'online' && (
          <div className="border-2 border-indigo-200 rounded-lg p-6 bg-indigo-50">
            <h3 className="text-lg font-semibold text-gray-900 mb-1">Stripe Connect</h3>
            <p className="text-sm text-gray-600 mb-4">
              Connect your Stripe account to accept online payments. Payouts are transferred directly to your bank.
            </p>
            {paymentSettings.stripeOnboardingComplete ? (
              <div className="flex items-center gap-3">
                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-green-100 text-green-800 text-sm font-medium">
                  <CheckCircle className="w-4 h-4" /> Connected & Active
                </span>
                <span className="text-sm text-gray-500">Account: {paymentSettings.stripeAccountId}</span>
              </div>
            ) : paymentSettings.stripeAccountId ? (
              <div className="space-y-3">
                <div className="flex items-center gap-2 text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-2 text-sm">
                  <AlertCircle className="w-4 h-4 shrink-0" />
                  Stripe account created but onboarding not complete.
                </div>
                <button
                  onClick={async () => {
                    try {
                      const result = await api.getStripeOnboardingLink(
                        vendorId,
                        `${window.location.origin}/vendor/settings?stripe=refresh`,
                        `${window.location.origin}/vendor/settings?stripe=complete`
                      ) as { onboardingUrl: string };
                      window.location.href = result.onboardingUrl;
                    } catch {
                      toast.error('Failed to get Stripe onboarding link. Please try again.');
                    }
                  }}
                  className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"
                >
                  Continue Onboarding
                </button>
              </div>
            ) : (
              <button
                onClick={async () => {
                  try {
                    await api.connectStripe(vendorId);
                    const result = await api.getStripeOnboardingLink(
                      vendorId,
                      `${window.location.origin}/vendor/settings?stripe=refresh`,
                      `${window.location.origin}/vendor/settings?stripe=complete`
                    ) as { onboardingUrl: string };
                    window.location.href = result.onboardingUrl;
                  } catch {
                    toast.error('Failed to connect Stripe. Please try again.');
                  }
                }}
                className="px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 flex items-center gap-2"
              >
                <CreditCard className="w-4 h-4" />
                Connect with Stripe
              </button>
            )}
          </div>
        )}
      </div>
    );
  };

  const renderTaxSettings = () => {
    // Helper to get country name
    const getCountryName = () => {
      return taxSettings.country === 'AT' ? 'Austrian' : 'German';
    };

    return (
      <div className="space-y-8">
        {/* Section 1: Tax System Information (Read-only) */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-2">Applied Tax System</h3>
          <p className="text-base text-gray-900 mb-3">
            This restaurant operates under <strong>{getCountryName()} VAT law</strong>
          </p>
          <p className="text-sm text-gray-700">
            VAT rules are automatically applied by Tavlo based on the restaurant's registered country.
          </p>
        </div>

        {/* Section 2: VAT Categories (Read-only, improved clarity) */}
        <div>
          <h3 className="text-lg font-semibold text-gray-900 mb-4">VAT Categories</h3>
          <p className="text-sm text-gray-600 mb-4">
            Legally defined – cannot be edited
          </p>
          
          <TaxRulesDisplay country={taxSettings.country} showUsageCount={true} />
        </div>

        {/* Section 3: Service Fee (clarified responsibility) */}
        <div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">Optional Service Fee</h3>
          <p className="text-sm text-gray-600 mb-4">
            Service fees must be clearly shown to customers. Tax treatment depends on local regulations. You are responsible for correct usage.
          </p>
          
          <div className="max-w-md">
            <label className="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
              <Percent className="w-4 h-4" />
              Service Fee Rate (%)
            </label>
            <input
              type="number"
              value={taxSettings.serviceFeeRate}
              onChange={(e) => setTaxSettings({...taxSettings, serviceFeeRate: parseFloat(e.target.value)})}
              min="0"
              max="100"
              step="0.1"
              className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
            />
          </div>
        </div>

        {/* Section 4: Receipt vs Invoice (NEW) */}
        <div>
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Receipt & Invoice Handling</h3>
          
          <label className="flex items-start gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
            <input
              type="checkbox"
              checked={taxSettings.autoGenerateReceipts}
              onChange={(e) => setTaxSettings({...taxSettings, autoGenerateReceipts: e.target.checked})}
              className="rounded mt-1"
            />
            <div className="flex-1">
              <div className="font-medium text-gray-900">Automatically generate customer receipt for all orders</div>
              <div className="text-sm text-gray-600 mt-1">
                Standard receipts are issued by default. VAT invoices may be required upon request depending on local law.
              </div>
            </div>
          </label>
        </div>

        {/* Section 5: Invoice Numbering (restricted) */}
        <div>
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Invoice Numbering</h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Invoice Prefix</label>
              <input
                type="text"
                value={taxSettings.invoicePrefix}
                onChange={(e) => setTaxSettings({...taxSettings, invoicePrefix: e.target.value})}
                disabled={taxSettings.firstInvoiceIssued}
                maxLength={5}
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 ${
                  taxSettings.firstInvoiceIssued ? 'bg-gray-100 cursor-not-allowed' : ''
                }`}
              />
              <p className="text-xs text-gray-500 mt-1">
                Example: {taxSettings.invoicePrefix}-{taxSettings.nextInvoiceNumber}
              </p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Next Invoice Number</label>
              <input
                type="number"
                value={taxSettings.nextInvoiceNumber}
                onChange={(e) => setTaxSettings({...taxSettings, nextInvoiceNumber: parseInt(e.target.value)})}
                disabled={taxSettings.firstInvoiceIssued}
                min="1"
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 ${
                  taxSettings.firstInvoiceIssued ? 'bg-gray-100 cursor-not-allowed' : ''
                }`}
              />
            </div>
          </div>

          {taxSettings.firstInvoiceIssued && (
            <div className="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
              <p className="text-sm text-yellow-900">
                <strong>Locked:</strong> Invoice numbering cannot be changed after the first invoice is issued.
              </p>
            </div>
          )}

          <p className="text-sm text-gray-600 mt-3">
            Invoice numbering must be sequential and compliant with local regulations.
          </p>
        </div>

        {/* Section 6: Compliance Notice (NEW) */}
        <div className="bg-gray-50 border border-gray-300 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <Shield className="w-5 h-5 text-gray-600 flex-shrink-0 mt-0.5" />
            <div>
              <h4 className="font-medium text-gray-900 mb-2">Compliance Notice</h4>
              <p className="text-sm text-gray-700">
                Tavlo provides digital receipts and tax breakdowns. Local fiscal requirements for cash registers or certified systems may still apply depending on your country.
              </p>
            </div>
          </div>
        </div>
      </div>
    );
  };

  const renderTableSettings = () => (
    <div className="space-y-8">
      {/* Section: Table Configuration */}
      <div>
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Table Configuration</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Number of Tables</label>
            <input
              type="number"
              value={tableSettings.numberOfTables}
              onChange={(e) => setTableSettings({...tableSettings, numberOfTables: parseInt(e.target.value)})}
              min="1"
              max="999"
              className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
            />
            <p className="text-xs text-gray-600 mt-2">
              Changing this value will automatically create or deactivate table QR codes. Existing orders are not affected.
            </p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Table Prefix</label>
            <input
              type="text"
              value={tableSettings.tablePrefix}
              onChange={(e) => setTableSettings({...tableSettings, tablePrefix: e.target.value})}
              maxLength={3}
              className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
            />
            <p className="text-xs text-gray-600 mt-2">
              Example: {tableSettings.tablePrefix}1, {tableSettings.tablePrefix}2, {tableSettings.tablePrefix}3 …
            </p>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Max Guests per Table</label>
            <input
              type="number"
              value={tableSettings.maxGuestsPerTable}
              onChange={(e) => setTableSettings({...tableSettings, maxGuestsPerTable: parseInt(e.target.value)})}
              min="1"
              max="50"
              className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
            />
          </div>
        </div>
      </div>

      {/* Section: Order Behavior */}
      <div>
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Order Behavior</h3>
        <label className="flex items-start gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
          <input
            type="checkbox"
            checked={tableSettings.enableSharedBasket}
            onChange={(e) => setTableSettings({...tableSettings, enableSharedBasket: e.target.checked})}
            className="rounded mt-1"
          />
          <div className="flex-1">
            <div className="font-medium text-gray-900">Enable Shared Basket</div>
            <div className="text-sm text-gray-600 mt-1">
              Allows multiple devices at the same table to add items to one shared order. Recommended for group dining.
            </div>
          </div>
        </label>
      </div>

      {/* Section: Reservation Settings */}
      <div>
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Reservation Settings</h3>
        <div className="space-y-4">
          <label className="flex items-start gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
            <input
              type="checkbox"
              checked={tableSettings.enableReservations}
              onChange={(e) => setTableSettings({...tableSettings, enableReservations: e.target.checked})}
              className="rounded mt-1"
            />
            <div className="flex-1">
              <div className="font-medium text-gray-900">Enable Table Reservations</div>
              <div className="text-sm text-gray-600 mt-1">Allow customers to book tables in advance</div>
            </div>
          </label>

          {tableSettings.enableReservations && (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 ml-6 pl-4 border-l-2">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Total Tables for Reservations</label>
                <input
                  type="number"
                  value={tableSettings.totalTables}
                  onChange={(e) => setTableSettings({...tableSettings, totalTables: parseInt(e.target.value)})}
                  min="1"
                  max="999"
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
                />
                <p className="text-xs text-gray-500 mt-1">Maximum tables available for reservations</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Max Capacity per Table</label>
                <input
                  type="number"
                  value={tableSettings.maxTableCapacity}
                  onChange={(e) => setTableSettings({...tableSettings, maxTableCapacity: parseInt(e.target.value)})}
                  min="1"
                  max="20"
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
                />
                <p className="text-xs text-gray-500 mt-1">Maximum guests per table for capacity calculations</p>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Section: Live Table List */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold text-gray-900">Tables</h3>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={loadTableList} disabled={tableListLoading}>
              {tableListLoading ? 'Loading…' : 'Refresh'}
            </Button>
            <Button size="sm" onClick={handleSyncTables} disabled={syncingTables} className="bg-gray-900 text-white hover:bg-gray-700">
              {syncingTables ? 'Syncing…' : `Sync to ${tableSettings.numberOfTables} tables`}
            </Button>
          </div>
        </div>
        {tableList.length === 0 ? (
          <div className="text-sm text-gray-500 py-4 text-center border rounded-lg">
            No tables yet. Set a count above and click "Sync".
          </div>
        ) : (
          <div className="border rounded-lg divide-y overflow-hidden">
            {tableList.map(table => (
              <div key={table.id} className={`flex items-center gap-3 px-4 py-3 ${table.isActive ? 'bg-white' : 'bg-gray-50 opacity-60'}`}>
                <span className="w-8 text-sm font-medium text-gray-500">#{table.number}</span>
                {editingTableId === table.id ? (
                  <>
                    <input
                      autoFocus
                      value={editingTableName}
                      onChange={e => setEditingTableName(e.target.value)}
                      onKeyDown={e => { if (e.key === 'Enter') handleRenameTable(table.id); if (e.key === 'Escape') setEditingTableId(null); }}
                      className="flex-1 px-2 py-1 border rounded text-sm focus:ring-2 focus:ring-gray-900"
                    />
                    <button onClick={() => handleRenameTable(table.id)} className="p-1 text-green-600 hover:text-green-700"><Check className="w-4 h-4" /></button>
                    <button onClick={() => setEditingTableId(null)} className="p-1 text-gray-400 hover:text-gray-600"><X className="w-4 h-4" /></button>
                  </>
                ) : (
                  <>
                    <span className="flex-1 text-sm text-gray-800">{table.name}</span>
                    <button onClick={() => { setEditingTableId(table.id); setEditingTableName(table.name); }} className="p-1 text-gray-400 hover:text-gray-700">
                      <Pencil className="w-3.5 h-3.5" />
                    </button>
                    <button onClick={() => handleToggleTableActive(table.id, table.isActive)} className={table.isActive ? 'text-green-600' : 'text-gray-400'}>
                      {table.isActive ? <ToggleRight className="w-5 h-5" /> : <ToggleLeft className="w-5 h-5" />}
                    </button>
                  </>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Section: QR Code Management Link (NEW) */}
      <div className="bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-lg p-6">
        <div className="flex items-start gap-4">
          <div className="flex-shrink-0 w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center">
            <QrCode className="w-6 h-6 text-white" />
          </div>
          <div className="flex-1">
            <h4 className="text-lg font-semibold text-gray-900 mb-2">QR Code Management</h4>
            <p className="text-sm text-gray-700 mb-4">
              View, print, refresh, and manage individual table QR codes.
            </p>
            <Button 
              onClick={() => onNavigate?.('qr-codes')}
              className="bg-blue-600 hover:bg-blue-700 text-white"
            >
              <QrCode className="w-4 h-4 mr-2" />
              Go to QR Code Management
            </Button>
          </div>
        </div>
      </div>
    </div>
  );

  const renderOrderSettings = () => (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg mb-4">Order Management</h3>
        <label className="flex items-center gap-3 mb-4">
          <input
            type="checkbox"
            checked={orderSettings.autoAcceptOrders}
            onChange={(e) => setOrderSettings({...orderSettings, autoAcceptOrders: e.target.checked})}
            className="rounded"
          />
          <div>
            <div>Auto-accept orders</div>
            <div className="text-sm text-gray-500">Orders will be automatically accepted without manual confirmation</div>
          </div>
        </label>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm text-gray-600 mb-2 flex items-center gap-2">
            <Clock className="w-4 h-4" />
            Estimated Prep Time (minutes)
          </label>
          <input
            type="number"
            value={orderSettings.estimatedPrepTime}
            onChange={(e) => setOrderSettings({...orderSettings, estimatedPrepTime: parseInt(e.target.value)})}
            min="5"
            max="120"
            className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
          />
        </div>
        <div>
          <label className="block text-sm text-gray-600 mb-2">Max Orders per Time Slot</label>
          <input
            type="number"
            value={orderSettings.maxOrdersPerSlot}
            onChange={(e) => setOrderSettings({...orderSettings, maxOrdersPerSlot: parseInt(e.target.value)})}
            min="1"
            className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
          />
        </div>
      </div>

      <div>
        <h3 className="text-lg mb-4">Customer Options</h3>
        <div className="space-y-3">
          <label className="flex items-center gap-3">
            <input
              type="checkbox"
              checked={orderSettings.allowGuestOrdering}
              onChange={(e) => setOrderSettings({...orderSettings, allowGuestOrdering: e.target.checked})}
              className="rounded"
            />
            <div>
              <div>Allow guest ordering</div>
              <div className="text-sm text-gray-500">Customers can order without creating an account</div>
            </div>
          </label>
          <label className="flex items-center gap-3">
            <input
              type="checkbox"
              checked={orderSettings.requirePhoneNumber}
              onChange={(e) => setOrderSettings({...orderSettings, requirePhoneNumber: e.target.checked})}
              className="rounded"
            />
            <div>
              <div>Require phone number</div>
              <div className="text-sm text-gray-500">Ask for contact number when placing orders</div>
            </div>
          </label>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm text-gray-600 mb-2">Minimum Order Amount (€)</label>
          <input
            type="number"
            value={orderSettings.minOrderAmount}
            onChange={(e) => setOrderSettings({...orderSettings, minOrderAmount: parseFloat(e.target.value)})}
            min="0"
            step="0.5"
            className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
          />
        </div>
        <div>
          <label className="block text-sm text-gray-600 mb-2">Maximum Order Amount (€)</label>
          <input
            type="number"
            value={orderSettings.maxOrderAmount}
            onChange={(e) => setOrderSettings({...orderSettings, maxOrderAmount: parseFloat(e.target.value)})}
            min="0"
            step="10"
            className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
          />
        </div>
      </div>
    </div>
  );

  const renderNotificationSettings = () => (
    <div className="space-y-6">
      {/* Helper Text */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <p className="text-sm text-blue-900">
          <strong>Global Notification Preferences</strong> — These settings control where notifications are sent.
        </p>
      </div>

      <div>
        <h3 className="text-lg mb-4">Email Notifications</h3>
        <div className="mb-4">
          <label className="block text-sm text-gray-600 mb-2">Notification Email Address</label>
          <input
            type="email"
            value={notificationSettings.notificationEmail}
            onChange={(e) => setNotificationSettings({...notificationSettings, notificationEmail: e.target.value})}
            className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
          />
          <p className="text-xs text-gray-500 mt-1">
            This email is used for all Tavlo notifications, including inventory alerts.
          </p>
        </div>
        
        <div className="space-y-3">
          <label className="flex items-center gap-3 p-3 border rounded-lg">
            <input
              type="checkbox"
              checked={notificationSettings.emailNewOrder}
              onChange={(e) => setNotificationSettings({...notificationSettings, emailNewOrder: e.target.checked})}
              className="rounded"
            />
            <div>
              <div>New order received</div>
              <div className="text-sm text-gray-500">Get notified when a customer places an order</div>
            </div>
          </label>
          <label className="flex items-center gap-3 p-3 border rounded-lg">
            <input
              type="checkbox"
              checked={notificationSettings.emailReview}
              onChange={(e) => setNotificationSettings({...notificationSettings, emailReview: e.target.checked})}
              className="rounded"
            />
            <div>
              <div>New review posted</div>
              <div className="text-sm text-gray-500">Get notified when customers leave reviews</div>
            </div>
          </label>
        </div>
      </div>

      <div>
        <h3 className="text-lg mb-4">SMS Notifications</h3>
        <p className="text-sm text-gray-600 mb-3">
          SMS availability applies globally. Additional charges may apply.
        </p>
        <label className="flex items-center gap-3 p-3 border rounded-lg">
          <input
            type="checkbox"
            checked={notificationSettings.smsNewOrder}
            onChange={(e) => setNotificationSettings({...notificationSettings, smsNewOrder: e.target.checked})}
            className="rounded"
          />
          <div>
            <div>New order via SMS</div>
            <div className="text-sm text-gray-500">Receive text messages for new orders (charges may apply)</div>
          </div>
        </label>
      </div>

      <div>
        <h3 className="text-lg mb-4">Push Notifications</h3>
        <p className="text-sm text-gray-600 mb-3">
          Push notifications apply across all features.
        </p>
        <div className="space-y-3">
          <label className="flex items-center gap-3 p-3 border rounded-lg">
            <input
              type="checkbox"
              checked={notificationSettings.pushNewOrder}
              onChange={(e) => setNotificationSettings({...notificationSettings, pushNewOrder: e.target.checked})}
              className="rounded"
            />
            <div>
              <div>New order</div>
              <div className="text-sm text-gray-500">Browser push notifications for new orders</div>
            </div>
          </label>
          <label className="flex items-center gap-3 p-3 border rounded-lg">
            <input
              type="checkbox"
              checked={notificationSettings.pushOrderReady}
              onChange={(e) => setNotificationSettings({...notificationSettings, pushOrderReady: e.target.checked})}
              className="rounded"
            />
            <div>
              <div>Order status updates</div>
              <div className="text-sm text-gray-500">Notifications when orders are ready to serve</div>
            </div>
          </label>
        </div>
      </div>
    </div>
  );

  const renderReviewSettings = () => (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg mb-4">Review Management</h3>
        <div className="space-y-3">
          <label className="flex items-center gap-3 p-3 border rounded-lg">
            <input
              type="checkbox"
              checked={reviewSettings.enableReviews}
              onChange={(e) => setReviewSettings({...reviewSettings, enableReviews: e.target.checked})}
              className="rounded"
            />
            <div>
              <div>Enable Restaurant Reviews</div>
              <div className="text-sm text-gray-500">Allow customers to rate and review the restaurant</div>
            </div>
          </label>
          
          {reviewSettings.enableReviews && (
            <>
              <label className="flex items-center gap-3 p-3 border rounded-lg">
                <input
                  type="checkbox"
                  checked={reviewSettings.enableMenuReviews}
                  onChange={(e) => setReviewSettings({...reviewSettings, enableMenuReviews: e.target.checked})}
                  className="rounded"
                />
                <div>
                  <div>Enable menu articles reviews</div>
                  <div className="text-sm text-gray-500">Allow customers to rate and review each article of their order</div>
                </div>
              </label>
              <label className="flex items-center gap-3 p-3 border rounded-lg">
                <input
                  type="checkbox"
                  checked={reviewSettings.allowAnonymousReviews}
                  onChange={(e) => setReviewSettings({...reviewSettings, allowAnonymousReviews: e.target.checked})}
                  className="rounded"
                />
                <div>
                  <div>Allow anonymous reviews</div>
                  <div className="text-sm text-gray-500">Allow unlogged guests to rate and review the restaurant and their orders</div>
                </div>
              </label>
            </>
          )}
        </div>
      </div>
    </div>
  );

  const renderLanguageSettings = () => (
    <div className="space-y-6">
      {/* SECTION 1: System Language */}
      <div>
        <h3 className="text-lg mb-2">System Language (Vendor Dashboard)</h3>
        <p className="text-sm text-gray-600 mb-4">
          This language is used for the vendor dashboard and internal system messages.
        </p>
        <div>
          <label className="block text-sm text-gray-600 mb-2">System Language</label>
          <select
            value={languageSettings.defaultLanguage}
            onChange={(e) => setLanguageSettings({...languageSettings, defaultLanguage: e.target.value})}
            className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
          >
            <option value="en">English</option>
            <option value="de">Deutsch (German)</option>
            <option value="it">Italiano (Italian)</option>
            <option value="fr">Français (French)</option>
            <option value="ar">العربية (Arabic)</option>
            <option value="tr">Türkçe (Turkish)</option>
            <option value="zh">中文 (Chinese)</option>
            <option value="ja">日本語 (Japanese)</option>
            <option value="sr">Српски (Serbian)</option>
            <option value="cs">Čeština (Czech)</option>
            <option value="es">Español (Spanish)</option>
          </select>
        </div>
      </div>

      {/* SECTION 2: Customer Menu Languages */}
      <div>
        <h3 className="text-lg mb-2">Customer Menu Languages</h3>
        <p className="text-sm text-gray-600 mb-4">
          These languages are available to customers viewing your menu. The menu language is selected automatically based on the customer's device, with fallback rules.
        </p>
        <div className="space-y-2 mb-4">
          {[
            { code: 'en', name: 'English' },
            { code: 'de', name: 'Deutsch (German)' },
            { code: 'it', name: 'Italiano (Italian)' },
            { code: 'fr', name: 'Français (French)' },
            { code: 'ar', name: 'العربية (Arabic)' },
            { code: 'tr', name: 'Türkçe (Turkish)' },
            { code: 'zh', name: '中文 (Chinese)' },
            { code: 'ja', name: '日本語 (Japanese)' },
            { code: 'sr', name: 'Српски (Serbian)' },
            { code: 'cs', name: 'Čeština (Czech)' },
            { code: 'es', name: 'Español (Spanish)' }
          ].map((lang) => (
            <label key={lang.code} className="flex items-center gap-3 p-3 border rounded-lg">
              <input
                type="checkbox"
                checked={languageSettings.supportedLanguages.includes(lang.code)}
                onChange={(e) => {
                  if (e.target.checked) {
                    setLanguageSettings({
                      ...languageSettings,
                      supportedLanguages: [...languageSettings.supportedLanguages, lang.code]
                    });
                  } else {
                    setLanguageSettings({
                      ...languageSettings,
                      supportedLanguages: languageSettings.supportedLanguages.filter(l => l !== lang.code)
                    });
                  }
                }}
                className="rounded"
              />
              <span>{lang.name}</span>
            </label>
          ))}
        </div>

        {/* Language Selection Behavior Info */}
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
          <p className="text-sm text-gray-700 mb-2">
            <strong>Language Selection Behavior:</strong>
          </p>
          <ul className="text-sm text-gray-600 space-y-1 ml-4 list-disc">
            <li>Customer menu language is selected based on device language.</li>
            <li>If unavailable, the default menu language is used.</li>
            <li>If a translation is missing, the default language content is shown.</li>
          </ul>
        </div>
      </div>

      {/* SECTION 3: Menu Translation Management */}
      <div className="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-200 rounded-lg p-4">
        <h4 className="font-semibold text-orange-900 mb-2 flex items-center gap-2">
          <Globe className="w-5 h-5" />
          Menu Translation Management
        </h4>
        <p className="text-sm text-orange-800 mb-3">
          Resetting menu translations will restore default menu text and regenerate translations for all enabled customer menu languages.
        </p>
        <Button
          onClick={handleResetMenuWithTranslations}
          disabled={saving}
          variant="outline"
          className="bg-white border-orange-300 hover:bg-orange-50 text-orange-900"
        >
          <Download className="w-4 h-4 mr-2" />
          Reset Menu with Translations
        </Button>
      </div>

      {/* SECTION 4: Local Formatting */}
      <div>
        <h3 className="text-lg mb-2">Local Formatting</h3>
        <p className="text-sm text-gray-600 mb-4">
          These formats are used across the vendor dashboard, customer receipts, and system messages.
        </p>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm text-gray-600 mb-2">Date Format</label>
            <select
              value={languageSettings.dateFormat}
              onChange={(e) => setLanguageSettings({...languageSettings, dateFormat: e.target.value})}
              className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
            >
              <option value="DD.MM.YYYY">DD.MM.YYYY (28.11.2025)</option>
              <option value="MM/DD/YYYY">MM/DD/YYYY (11/28/2025)</option>
              <option value="YYYY-MM-DD">YYYY-MM-DD (2025-11-28)</option>
            </select>
          </div>
          <div>
            <label className="block text-sm text-gray-600 mb-2">Time Format</label>
            <select
              value={languageSettings.timeFormat}
              onChange={(e) => setLanguageSettings({...languageSettings, timeFormat: e.target.value})}
              className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
            >
              <option value="24h">24-hour (15:30)</option>
              <option value="12h">12-hour (3:30 PM)</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  );

  const renderLoyaltySettings = () => (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg mb-4">Loyalty Program</h3>
        <label className="flex items-center gap-3 mb-4">
          <input
            type="checkbox"
            checked={loyaltySettings.enableLoyalty}
            onChange={(e) => setLoyaltySettings({...loyaltySettings, enableLoyalty: e.target.checked})}
            className="rounded"
          />
          <div>
            <div>Enable loyalty program</div>
            <div className="text-sm text-gray-500">Reward customers with points for their purchases</div>
          </div>
        </label>
      </div>

      {loyaltySettings.enableLoyalty && (
        <>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm text-gray-600 mb-2">Points per Euro Spent</label>
              <input
                type="number"
                value={loyaltySettings.pointsPerEuro}
                onChange={(e) => setLoyaltySettings({...loyaltySettings, pointsPerEuro: parseFloat(e.target.value)})}
                min="0.1"
                step="0.1"
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
              />
              <p className="text-xs text-gray-500 mt-1">A €20 order earns {loyaltySettings.pointsPerEuro * 20} points</p>
            </div>
            <div>
              <label className="block text-sm text-gray-600 mb-2">Minimum Points for Redemption</label>
              <input
                type="number"
                value={loyaltySettings.minimumRedemption}
                onChange={(e) => setLoyaltySettings({...loyaltySettings, minimumRedemption: parseInt(e.target.value)})}
                min="1"
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
              />
              <p className="text-xs text-gray-500 mt-1">
                Worth €{(loyaltySettings.minimumRedemption * loyaltySettings.redemptionRate).toFixed(2)} discount
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm text-gray-600 mb-2">Point Value (€ per point)</label>
              <input
                type="number"
                value={loyaltySettings.redemptionRate}
                onChange={(e) => setLoyaltySettings({...loyaltySettings, redemptionRate: parseFloat(e.target.value)})}
                min="0.001"
                step="0.001"
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
              />
              <p className="text-xs text-gray-500 mt-1">
                {loyaltySettings.minimumRedemption} points = €{(loyaltySettings.minimumRedemption * loyaltySettings.redemptionRate).toFixed(2)} discount
              </p>
            </div>
            <div>
              <label className="block text-sm text-gray-600 mb-2">Points Expiry (days)</label>
              <input
                type="number"
                value={loyaltySettings.pointsExpiry}
                onChange={(e) => setLoyaltySettings({...loyaltySettings, pointsExpiry: parseInt(e.target.value)})}
                min="0"
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900"
              />
              <p className="text-xs text-gray-500 mt-1">Set to 0 for no expiry</p>
            </div>
          </div>

          <div>
            <label className="flex items-center gap-3">
              <input
                type="checkbox"
                checked={loyaltySettings.enableTiers}
                onChange={(e) => setLoyaltySettings({...loyaltySettings, enableTiers: e.target.checked})}
                className="rounded"
              />
              <div>
                <div>Enable loyalty tiers</div>
                <div className="text-sm text-gray-500">Bronze, Silver, Gold tiers with increasing benefits</div>
              </div>
            </label>
          </div>
        </>
      )}
    </div>
  );

  const renderAppearanceSettings = () => {
    const themeGradients = {
      classic: 'from-gray-700 to-gray-900',
      modern: 'from-blue-500 to-purple-600',
      minimal: 'from-gray-200 to-gray-400',
      vibrant: 'from-orange-400 to-pink-500'
    };

    return (
      <div className="space-y-6">
        <div>
          <h3 className="text-lg mb-4">Menu Theme</h3>
          <p className="text-sm text-gray-600 mb-4">Choose a theme that matches your restaurant's style</p>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {(['classic', 'modern', 'minimal', 'vibrant'] as const).map((theme) => (
              <div
                key={theme}
                onClick={() => setAppearanceSettings({...appearanceSettings, menuTheme: theme})}
                className={`border-2 rounded-lg p-4 text-center cursor-pointer transition-all ${
                  appearanceSettings.menuTheme === theme 
                    ? 'border-gray-900 ring-2 ring-gray-900 ring-offset-2' 
                    : 'border-gray-200 hover:border-gray-400'
                }`}
              >
                <div className={`w-full h-20 bg-gradient-to-br ${themeGradients[theme]} rounded mb-3 shadow-sm`}></div>
                <div className={`text-sm capitalize ${appearanceSettings.menuTheme === theme ? 'font-semibold' : ''}`}>
                  {theme}
                </div>
              </div>
            ))}
          </div>
        </div>

        <div>
          <h3 className="text-lg mb-4">Brand Colors</h3>
          <p className="text-sm text-gray-600 mb-4">Customize colors to match your brand identity</p>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm text-gray-600 mb-2">Primary Color</label>
              <div className="flex gap-2">
                <input
                  type="color"
                  value={appearanceSettings.primaryColor}
                  onChange={(e) => setAppearanceSettings({...appearanceSettings, primaryColor: e.target.value})}
                  className="w-12 h-10 rounded border cursor-pointer"
                />
                <input
                  type="text"
                  value={appearanceSettings.primaryColor}
                  onChange={(e) => setAppearanceSettings({...appearanceSettings, primaryColor: e.target.value})}
                  className="flex-1 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 font-mono text-sm"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm text-gray-600 mb-2">Accent Color</label>
              <div className="flex gap-2">
                <input
                  type="color"
                  value={appearanceSettings.accentColor}
                  onChange={(e) => setAppearanceSettings({...appearanceSettings, accentColor: e.target.value})}
                  className="w-12 h-10 rounded border cursor-pointer"
                />
                <input
                  type="text"
                  value={appearanceSettings.accentColor}
                  onChange={(e) => setAppearanceSettings({...appearanceSettings, accentColor: e.target.value})}
                  className="flex-1 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-gray-900 font-mono text-sm"
                />
              </div>
            </div>
          </div>
        </div>

        <div>
          <h3 className="text-lg mb-4">Menu Layout</h3>
          <p className="text-sm text-gray-600 mb-4">Choose how menu items are displayed to customers</p>
          <div className="space-y-3">
            <label className="flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors" style={{
              borderColor: appearanceSettings.menuLayout === 'grid' ? appearanceSettings.primaryColor : '#e5e7eb'
            }}>
              <input 
                type="radio" 
                name="layout" 
                value="grid" 
                checked={appearanceSettings.menuLayout === 'grid'}
                onChange={(e) => setAppearanceSettings({...appearanceSettings, menuLayout: e.target.value as 'grid' | 'list'})}
                className="rounded-full" 
              />
              <div>
                <div className={appearanceSettings.menuLayout === 'grid' ? 'font-semibold' : ''}>Grid Layout</div>
                <div className="text-sm text-gray-500">Display items in a card grid (better for images)</div>
              </div>
            </label>
            <label className="flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors" style={{
              borderColor: appearanceSettings.menuLayout === 'list' ? appearanceSettings.primaryColor : '#e5e7eb'
            }}>
              <input 
                type="radio" 
                name="layout" 
                value="list" 
                checked={appearanceSettings.menuLayout === 'list'}
                onChange={(e) => setAppearanceSettings({...appearanceSettings, menuLayout: e.target.value as 'grid' | 'list'})}
                className="rounded-full" 
              />
              <div>
                <div className={appearanceSettings.menuLayout === 'list' ? 'font-semibold' : ''}>List Layout</div>
                <div className="text-sm text-gray-500">Display items in a vertical list (better for text)</div>
              </div>
            </label>
          </div>
        </div>

        {/* Background Image */}
        <div>
          <h3 className="text-lg mb-4">Background Image (Optional)</h3>
          <p className="text-sm text-gray-600 mb-3">Upload a background image for your menu</p>
          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
            <p className="text-xs text-amber-900">
              <strong>Guidelines:</strong> Images should be subtle and readable. Tavlo may disable backgrounds that reduce usability.
            </p>
          </div>
          
          <div className="space-y-4">
            {/* Upload Button */}
            <div>
              <input
                type="file"
                accept="image/*"
                id="background-image-upload"
                className="hidden"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) {
                    // In production, upload to Supabase Storage
                    const reader = new FileReader();
                    reader.onloadend = () => {
                      setAppearanceSettings({
                        ...appearanceSettings,
                        backgroundImage: reader.result as string
                      });
                    };
                    reader.readAsDataURL(file);
                    toast.success('Background image uploaded');
                  }
                }}
              />
              <label
                htmlFor="background-image-upload"
                className="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg cursor-pointer transition-colors"
              >
                <Upload className="w-4 h-4" />
                Choose Image
              </label>
              {appearanceSettings.backgroundImage && (
                <button
                  onClick={() => {
                    setAppearanceSettings({ ...appearanceSettings, backgroundImage: '' });
                    toast.success('Background image removed');
                  }}
                  className="ml-3 text-sm text-red-600 hover:text-red-700"
                >
                  Remove
                </button>
              )}
            </div>

            {/* Preview */}
            {appearanceSettings.backgroundImage && (
              <div>
                <p className="text-sm text-gray-600 mb-2">Preview:</p>
                <div className="grid md:grid-cols-2 gap-4">
                  {/* Customer menu view preview */}
                  <div>
                    <p className="text-xs text-gray-500 mb-2">Customer Menu View</p>
                    <div
                      className="relative h-48 rounded-lg overflow-hidden border border-gray-300"
                      style={{
                        backgroundImage: `url(${appearanceSettings.backgroundImage})`,
                        backgroundSize: 'cover',
                        backgroundPosition: 'center'
                      }}
                    >
                      <div className="absolute inset-0 bg-black/20 backdrop-blur-sm">
                        <div className="h-full flex items-center justify-center">
                          <div className="bg-white/90 backdrop-blur-md rounded-lg p-4 shadow-lg">
                            <p className="text-sm text-gray-900">Menu items appear here</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* QR ordering screen preview */}
                  <div>
                    <p className="text-xs text-gray-500 mb-2">QR Ordering Screen</p>
                    <div
                      className="relative h-48 rounded-lg overflow-hidden border border-gray-300"
                      style={{
                        backgroundImage: `url(${appearanceSettings.backgroundImage})`,
                        backgroundSize: 'cover',
                        backgroundPosition: 'center'
                      }}
                    >
                      <div className="absolute inset-0 bg-white/70">
                        <div className="h-full p-4">
                          <div className="bg-white rounded-lg shadow p-3 mb-2">
                            <div className="h-2 bg-gray-200 rounded mb-1"></div>
                            <div className="h-2 bg-gray-200 rounded w-2/3"></div>
                          </div>
                          <div className="bg-white rounded-lg shadow p-3">
                            <div className="h-2 bg-gray-200 rounded mb-1"></div>
                            <div className="h-2 bg-gray-200 rounded w-3/4"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Live Theme Preview */}
        <div>
          <h3 className="text-lg mb-4">Live Preview</h3>
          <p className="text-sm text-gray-600 mb-4">See how your menu will look to customers</p>
          
          <div className="border-2 rounded-xl overflow-hidden shadow-lg">
            {/* Preview Header */}
            <div 
              className="p-4 text-white"
              style={{
                background: appearanceSettings.menuTheme === 'modern' 
                  ? `linear-gradient(135deg, ${appearanceSettings.primaryColor}, ${appearanceSettings.accentColor})`
                  : appearanceSettings.menuTheme === 'vibrant'
                  ? `linear-gradient(to right, ${appearanceSettings.primaryColor}, ${appearanceSettings.accentColor})`
                  : appearanceSettings.menuTheme === 'minimal'
                  ? '#ffffff'
                  : appearanceSettings.primaryColor
              }}
            >
              <div className="flex items-center gap-3">
                <div 
                  className="w-12 h-12 rounded-lg flex items-center justify-center"
                  style={{
                    background: appearanceSettings.menuTheme === 'minimal' 
                      ? '#f5f5f5' 
                      : 'rgba(255,255,255,0.2)'
                  }}
                >
                  <span className="text-xl">🍽️</span>
                </div>
                <div>
                  <div 
                    className="font-semibold"
                    style={{ 
                      color: appearanceSettings.menuTheme === 'minimal' 
                        ? appearanceSettings.primaryColor 
                        : '#ffffff' 
                    }}
                  >
                    Your Restaurant
                  </div>
                  <div 
                    className="text-sm opacity-80"
                    style={{ 
                      color: appearanceSettings.menuTheme === 'minimal' 
                        ? '#666666' 
                        : '#ffffff' 
                    }}
                  >
                    Sample Menu Preview
                  </div>
                </div>
              </div>
            </div>

            {/* Preview Content */}
            <div 
              className="p-4"
              style={{
                background: appearanceSettings.menuTheme === 'vibrant' 
                  ? '#fef3c7' 
                  : appearanceSettings.menuTheme === 'modern'
                  ? '#f8fafc'
                  : '#ffffff'
              }}
            >
              {/* Sample Dishes */}
              <div className={`${appearanceSettings.menuLayout === 'grid' ? 'grid grid-cols-2 gap-3' : 'space-y-3'}`}>
                {/* Dish 1 */}
                <div 
                  className="overflow-hidden"
                  style={{
                    background: '#ffffff',
                    border: appearanceSettings.menuTheme === 'minimal' 
                      ? '1px solid #f0f0f0' 
                      : appearanceSettings.menuTheme === 'vibrant'
                      ? `2px solid ${appearanceSettings.accentColor}`
                      : '1px solid #e5e7eb',
                    borderRadius: appearanceSettings.menuTheme === 'vibrant' 
                      ? '1.5rem' 
                      : appearanceSettings.menuTheme === 'modern'
                      ? '1rem'
                      : appearanceSettings.menuTheme === 'minimal'
                      ? '0.25rem'
                      : '0.5rem',
                    boxShadow: appearanceSettings.menuTheme === 'modern'
                      ? '0 10px 15px -3px rgb(0 0 0 / 0.1)'
                      : appearanceSettings.menuTheme === 'minimal'
                      ? 'none'
                      : '0 1px 3px 0 rgb(0 0 0 / 0.1)'
                  }}
                >
                  <div className="h-24 bg-gradient-to-br from-orange-400 to-orange-600"></div>
                  <div className="p-3">
                    <div className="font-semibold text-sm mb-1" style={{ color: appearanceSettings.primaryColor }}>
                      Pasta Carbonara
                    </div>
                    <div className="text-xs text-gray-500 mb-2">Classic Italian pasta</div>
                    <div 
                      className="text-sm font-semibold"
                      style={{ color: appearanceSettings.accentColor }}
                    >
                      €12.50
                    </div>
                  </div>
                </div>

                {/* Dish 2 */}
                <div 
                  className="overflow-hidden"
                  style={{
                    background: '#ffffff',
                    border: appearanceSettings.menuTheme === 'minimal' 
                      ? '1px solid #f0f0f0' 
                      : appearanceSettings.menuTheme === 'vibrant'
                      ? `2px solid ${appearanceSettings.accentColor}`
                      : '1px solid #e5e7eb',
                    borderRadius: appearanceSettings.menuTheme === 'vibrant' 
                      ? '1.5rem' 
                      : appearanceSettings.menuTheme === 'modern'
                      ? '1rem'
                      : appearanceSettings.menuTheme === 'minimal'
                      ? '0.25rem'
                      : '0.5rem',
                    boxShadow: appearanceSettings.menuTheme === 'modern'
                      ? '0 10px 15px -3px rgb(0 0 0 / 0.1)'
                      : appearanceSettings.menuTheme === 'minimal'
                      ? 'none'
                      : '0 1px 3px 0 rgb(0 0 0 / 0.1)'
                  }}
                >
                  <div className="h-24 bg-gradient-to-br from-green-400 to-green-600"></div>
                  <div className="p-3">
                    <div className="font-semibold text-sm mb-1" style={{ color: appearanceSettings.primaryColor }}>
                      Caesar Salad
                    </div>
                    <div className="text-xs text-gray-500 mb-2">Fresh & healthy</div>
                    <div 
                      className="text-sm font-semibold"
                      style={{ color: appearanceSettings.accentColor }}
                    >
                      €8.50
                    </div>
                  </div>
                </div>
              </div>

              {/* Sample Button */}
              <button
                className="w-full mt-4 py-3 rounded-lg font-semibold text-white transition-opacity hover:opacity-90"
                style={{
                  background: appearanceSettings.menuTheme === 'modern'
                    ? `linear-gradient(to right, ${appearanceSettings.primaryColor}, ${appearanceSettings.accentColor})`
                    : appearanceSettings.primaryColor,
                  borderRadius: appearanceSettings.menuTheme === 'vibrant' 
                    ? '1.5rem' 
                    : appearanceSettings.menuTheme === 'modern'
                    ? '1rem'
                    : '0.5rem'
                }}
              >
                View Full Menu
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  };

  const renderPrivacySettings = () => (
    <div className="space-y-8">
      {/* Section 1: Data Visibility in Analytics */}
      <div>
        <h3 className="text-lg font-semibold mb-1">Customer Data Visibility</h3>
        <div className="space-y-3 mt-4">
          <label className="flex items-start gap-3 p-4 border rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
            <input
              type="checkbox"
              checked={privacySettings.showInTopCustomers}
              onChange={(e) => setPrivacySettings({...privacySettings, showInTopCustomers: e.target.checked})}
              className="rounded mt-0.5"
            />
            <div>
              <div className="font-medium">Show in top customers analytics</div>
              <div className="text-sm text-gray-600 mt-1">
                Customer data is shown in analytics only where customer consent applies. This setting affects visibility only, not data collection.
              </div>
            </div>
          </label>
        </div>
      </div>

      {/* Section 2: Operational Data Export */}
      <div>
        <h3 className="text-lg font-semibold mb-1">Export Restaurant Data</h3>
        <p className="text-sm text-gray-600 mb-4 mt-2">
          Export your restaurant's operational data including orders, menus, and analytics. Data subject to legal retention may be excluded.
        </p>
        <Button onClick={handleExportData} variant="outline" className="mt-2">
          <Download className="w-4 h-4 mr-2" />
          Export All Data
        </Button>
      </div>

      {/* Section 3: Compliance Information (READ-ONLY) */}
      <div className="bg-gray-50 border border-gray-200 rounded-lg p-5">
        <h3 className="text-base font-semibold mb-2">Data Protection & Compliance</h3>
        <p className="text-sm text-gray-700 leading-relaxed">
          Tavlo processes customer data in accordance with applicable data protection laws, including GDPR. Legal data retention and data subject rights are handled at platform level.
        </p>
      </div>
    </div>
  );

  const renderContent = () => {
    switch (activeTab) {
      case 'business':
        return renderBusinessInfo();
      case 'payment':
        return renderPaymentSettings();
      case 'tax':
        return renderTaxSettings();
      case 'tables':
        return renderTableSettings();
      case 'ordering':
        return renderOrderSettings();
      case 'inventory':
        return <InventorySettingsTab vendorId={vendorId} globalSmsEnabled={notificationSettings.smsNewOrder} />;
      case 'team':
        return <TeamAccess vendorId={vendorId} />;
      case 'notifications':
        return renderNotificationSettings();
      case 'reviews':
        return renderReviewSettings();
      case 'language':
        return renderLanguageSettings();
      case 'appearance':
        return renderAppearanceSettings();
      case 'privacy':
        return renderPrivacySettings();
      default:
        return renderBusinessInfo();
    }
  };

  return (
    <div className="p-6">
      <div className="mb-6">
        <h1 className="text-2xl mb-2">Settings</h1>
        <p className="text-gray-600">Manage your restaurant configuration and preferences</p>
      </div>

      <div className="flex flex-col lg:flex-row gap-6">
        {/* Sidebar Navigation */}
        <div className="lg:w-64 flex-shrink-0">
          <div className="bg-white rounded-lg border overflow-hidden">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`w-full flex items-center gap-3 px-4 py-3 text-left transition-colors ${
                    activeTab === tab.id
                      ? 'bg-gray-900 text-white'
                      : 'hover:bg-gray-50'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  <span className="text-sm">{tab.label}</span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Main Content */}
        <div className="flex-1">
          <Card className="overflow-visible">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                {tabs.find(t => t.id === activeTab)?.icon && 
                  (() => {
                    const Icon = tabs.find(t => t.id === activeTab)!.icon;
                    return <Icon className="w-5 h-5" />;
                  })()
                }
                {tabs.find(t => t.id === activeTab)?.label}
              </CardTitle>
            </CardHeader>
            <CardContent className="overflow-visible">
              {renderContent()}
              
              {/* Save Button */}
              <div className="flex items-center gap-3 mt-8 pt-6 border-t">
                <Button onClick={handleSave} disabled={saving} className="min-w-32">
                  {saving ? (
                    <>
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin mr-2"></div>
                      Saving...
                    </>
                  ) : (
                    <>
                      <Save className="w-4 h-4 mr-2" />
                      Save Changes
                    </>
                  )}
                </Button>
                <Button variant="outline">
                  Cancel
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* STEP 2: Confirmation Modal */}
      <Dialog open={showConfirmationModal} onOpenChange={setShowConfirmationModal}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-xl">
              <AlertCircle className="w-6 h-6 text-amber-600" />
              Confirm Legal Information Change
            </DialogTitle>
            <DialogDescription className="text-base pt-2">
              You are about to change information used for invoices and tax compliance. These changes require Tavlo admin review and may temporarily affect invoicing.
            </DialogDescription>
          </DialogHeader>

          {/* Change Summary */}
          <div className="my-6">
            <h4 className="font-semibold text-sm text-gray-700 mb-3">Changes to be reviewed:</h4>
            <div className="space-y-3 bg-gray-50 p-4 rounded-lg border">
              {legalChanges.map((change, index) => (
                <div key={index} className="pb-3 border-b last:border-b-0 last:pb-0">
                  <div className="font-medium text-sm text-gray-900 mb-2">{change.label}</div>
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <div className="text-xs text-gray-500 mb-1">Current Value</div>
                      <div className="bg-white p-2 rounded border text-gray-700">
                        {change.oldValue || <span className="text-gray-400 italic">Not set</span>}
                      </div>
                    </div>
                    <div>
                      <div className="text-xs text-gray-500 mb-1">New Value</div>
                      <div className="bg-amber-50 p-2 rounded border border-amber-200 text-gray-900 font-medium">
                        {change.newValue || <span className="text-gray-400 italic">Not set</span>}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
            <p className="text-sm text-blue-900">
              <strong>Important:</strong> Your current approved values will remain active for invoices and customer-facing pages until these changes are approved by Tavlo admin.
            </p>
          </div>

          <DialogFooter className="gap-2">
            <Button
              variant="outline"
              onClick={() => setShowConfirmationModal(false)}
            >
              Cancel
            </Button>
            <Button
              onClick={handleSubmitForApproval}
              className="bg-amber-600 hover:bg-amber-700"
            >
              Submit for Approval
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
