import { useState, useEffect } from 'react';
import { Sidebar } from './vendor/Sidebar';
import { TopNav } from './vendor/TopNav';
import { DashboardHome } from './vendor/DashboardHome';
import { OrdersManagement } from './vendor/OrdersManagement';
import { AnalyticsView } from './vendor/AnalyticsView';
import { LoyaltyManagement } from './vendor/LoyaltyManagement';
import { LoyaltyAndPromotions } from './vendor/LoyaltyAndPromotions';
import { MenuManagement } from './vendor/MenuManagement';
import { ReviewsManagement } from './vendor/ReviewsManagement';
import { QRCodesManagement } from './vendor/QRCodesManagement';
import { Settings } from './vendor/Settings';
import { FeatureGate } from './vendor/FeatureGate';
import { InventoryOverview } from './vendor/InventoryOverview';
import { Users, MessageSquare, FileText, Package, Star, Settings as SettingsIcon } from 'lucide-react';
import { Button } from './ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './ui/card';
import { api } from '../utils/api';
import { toast } from 'sonner@2.0.3';

interface VendorDashboardProps {
  vendorId: string;
}

export function VendorDashboard({ vendorId }: VendorDashboardProps) {
  const [dashboardView, setDashboardView] = useState('dashboard');
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [vendorSettings, setVendorSettings] = useState<any>(null);
  const [vendorPlan, setVendorPlan] = useState('professional'); // 'basic' | 'professional' | 'enterprise'

  // Legacy views (customers, reviews, analytics, settings) - keep existing functionality
  const [orders, setOrders] = useState<any[]>([]);
  const [topCustomers, setTopCustomers] = useState<any[]>([]);
  const [complaints, setComplaints] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  // Load vendor settings on mount
  useEffect(() => {
    const loadSettings = async () => {
      try {
        const settings = await api.getVendorSettings(vendorId);
        setVendorSettings(settings);
      } catch (error) {
        console.error('Error loading vendor settings:', error);
      }
    };
    loadSettings();
  }, [vendorId]);

  useEffect(() => {
    if (['customers', 'analytics'].includes(dashboardView)) {
      loadData();
    }
  }, [dashboardView]);

  const loadData = async () => {
    setLoading(true);
    try {
      if (dashboardView === 'customers') {
        const data = await api.getTopCustomers(vendorId);
        setTopCustomers(data);
      } else if (dashboardView === 'analytics') {
        const ordersData = await api.getVendorOrders(vendorId);
        setOrders(ordersData);
      }
    } catch (error) {
      console.error('Error loading data:', error);
      toast.error('Failed to load data');
    } finally {
      setLoading(false);
    }
  };

  const generateInvoice = async (orderId: string) => {
    try {
      const result = await api.generateInvoice(vendorId, orderId);
      toast.success(`Invoice ${result.invoice.invoiceNumber} generated`);
    } catch (error) {
      console.error('Error generating invoice:', error);
      toast.error('Failed to generate invoice');
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex">
      {/* Sidebar */}
      <Sidebar
        activeView={dashboardView}
        onViewChange={setDashboardView}
        isOpen={sidebarOpen}
        onClose={() => setSidebarOpen(false)}
        restaurantName={vendorSettings?.restaurantName}
      />

      {/* Main Content */}
      <div className="flex-1 flex flex-col min-w-0">
        <TopNav
          onMenuClick={() => setSidebarOpen(true)}
          restaurantName={vendorSettings?.restaurantName || "Bella Cucina"}
        />

        <main className="flex-1 overflow-auto">
          {console.log('🎯 Current dashboardView:', dashboardView)}
          
          {/* New Dashboard Views */}
          {dashboardView === 'dashboard' && <DashboardHome vendorId={vendorId} />}
          {dashboardView === 'orders' && <OrdersManagement vendorId={vendorId} vendorSettings={vendorSettings} />}
          {dashboardView === 'analytics' && <AnalyticsView vendorId={vendorId} />}
          {dashboardView === 'loyalty' && (
            <FeatureGate feature="loyalty" vendorPlan={vendorPlan}>
              <LoyaltyAndPromotions />
            </FeatureGate>
          )}
          {dashboardView === 'menu' && <MenuManagement vendorId={vendorId} />}
          {dashboardView === 'inventory' && (
            <>
              {console.log('✅ Rendering InventoryOverview with vendorId:', vendorId)}
              <InventoryOverview 
                vendorId={vendorId} 
                onNavigateToSuppliers={() => setDashboardView('settings')}
              />
            </>
          )}
          {dashboardView === 'reviews' && <ReviewsManagement vendorId={vendorId} />}
          {dashboardView === 'qr-codes' && <QRCodesManagement vendorId={vendorId} />}
          {dashboardView === 'settings' && <Settings vendorId={vendorId} />}

          {/* Legacy Views */}
          {dashboardView === 'customers' && (
            <div className="p-6 space-y-6">
              <div>
                <h2 className="text-2xl font-semibold">Top Customers</h2>
                <p className="text-gray-600">Your most valuable customers</p>
              </div>
              
              <Card>
                <CardContent className="p-0">
                  {loading ? (
                    <div className="text-center py-12 text-gray-500">Loading...</div>
                  ) : topCustomers.length === 0 ? (
                    <div className="text-center py-12 text-gray-500">No customer data yet</div>
                  ) : (
                    <div className="divide-y">
                      {topCustomers.map((customer, idx) => (
                        <div key={customer.customerId} className="p-6 flex items-center justify-between hover:bg-gray-50">
                          <div className="flex items-center gap-4">
                            <div className="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center font-semibold text-orange-600">
                              #{idx + 1}
                            </div>
                            <div>
                              <div className="font-medium">{customer.name}</div>
                              <div className="text-sm text-gray-600">
                                {customer.ordersCount} orders
                              </div>
                            </div>
                          </div>
                          <div className="text-right">
                            <div className="font-semibold text-green-600">
                              €{customer.totalSpent.toFixed(2)}
                            </div>
                            <div className="text-xs text-gray-500">Total spent</div>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          )}


        </main>
      </div>
    </div>
  );
}