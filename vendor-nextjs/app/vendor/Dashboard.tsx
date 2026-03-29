import { api } from '@/lib/api';
import { useState, useEffect } from 'react';
import { 
  AlertTriangle,
  Clock,
  CreditCard,
  Package,
  Users,
  DollarSign,
  ShoppingCart,
  Star,
  TrendingUp,
  ChefHat,
  AlertCircle,
  CheckCircle,
  ArrowRight,
  Sparkles,
  Activity,
  Flame,
  Info
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { GuidedEnablementPanel } from '../vendor-onboarding/GuidedEnablementPanel';

interface DashboardProps {
  vendorId: string;
  onNavigate: (screen: 'orders' | 'menu' | 'billing' | 'analytics' | 'loyalty' | 'settings' | 'inventory' | 'qr-codes') => void;
  vendorStatus?: 'demo' | 'activated' | 'live';
  onActivateClick?: () => void;
  onLockedActionClick?: () => void;
  isLiveAndDiscoverable?: boolean;
}

type AlertSeverity = 'critical' | 'warning';
type KitchenLoad = 'low' | 'medium' | 'high';

interface Alert {
  id: string;
  severity: 'critical' | 'warning';
  icon: any;
  message: string;
  timeReference: string;
  action: string;
  navigateTo: 'orders' | 'menu' | 'billing' | 'analytics' | 'loyalty';
}

interface LiveStatus {
  openTables: number;
  totalTables: number;
  activeOrders: number;
  tablesWaitingToPay: number;
  kitchenLoad: KitchenLoad;
  nextPeakWindow: string;
  minutesUntilPeak: number;
}

interface RevenueAtRisk {
  total: number;
  unpaidTables: number;
  soldOutItems: number;
  failedPayments: number;
}

interface TodayKPI {
  ordersToday: number;
  ordersYesterday: number;
  revenueToday: number;
  revenueYesterday: number;
  avgOrderValue: number;
  avgOrderValueYesterday: number;
  avgGuestsPerTable: number;
  avgGuestsPerTableYesterday: number;
  customerRating: number;
  customerRatingYesterday: number;
}

interface AIInsightForDashboard {
  message: string;
  confidence: 'high';
  cta: string;
  affectsToday: boolean;
}

interface RecentOrder {
  id: string;
  time: string;
  table: string;
  items: number;
  amount: number;
  status: 'preparing' | 'served' | 'paid';
}

interface TopItem {
  name: string;
  orders: number;
  status: 'available' | 'low-stock' | 'sold-out';
}

export function Dashboard({ vendorId, onNavigate, vendorStatus = 'live', onActivateClick, onLockedActionClick, isLiveAndDiscoverable }: DashboardProps) {
  const [currentTime, setCurrentTime] = useState(new Date());
  const [showKitchenLoadTooltip, setShowKitchenLoadTooltip] = useState(false);
  const [dashboardData, setDashboardData] = useState<Record<string, any> | null>(null);

  // Fetch live dashboard data
  useEffect(() => {
    if (vendorId && vendorStatus !== 'demo') {
      api.getDashboard(vendorId as string)
        .then((data) => setDashboardData(data as Record<string, any>))
        .catch(() => { /* keep mock data on error */ });
    }
  }, [vendorId, vendorStatus]);

  const isLocked = vendorStatus === 'demo';

  // Update time every minute for real-time feel
  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentTime(new Date());
    }, 60000);
    return () => clearInterval(interval);
  }, []);

  // Event-driven alerts - only show when threshold crossed
  const apiAlerts: Alert[] = (dashboardData?.alerts ?? []).map((a: any) => ({
    id: a.id,
    severity: a.severity === 'danger' ? 'critical' : 'warning',
    icon: a.navigateTo === 'inventory' ? Package : Clock,
    message: a.message,
    timeReference: '',
    action: 'View',
    navigateTo: a.navigateTo as Alert['navigateTo'],
  }));

  const alerts: Alert[] = isLocked ? [] : (apiAlerts.length > 0 ? apiAlerts : []);

  const live = dashboardData?.liveStatus ?? {};
  const liveStatus: LiveStatus = {
    openTables: live.openTables ?? 0,
    totalTables: live.openTables ?? 0,
    activeOrders: live.activeOrders ?? 0,
    tablesWaitingToPay: live.tablesWaitingToPay ?? 0,
    kitchenLoad: 'medium',
    nextPeakWindow: 'Dinner rush',
    minutesUntilPeak: 25
  };

  const risk = dashboardData?.revenueAtRisk ?? {};
  const revenueAtRisk: RevenueAtRisk = {
    total: risk.total ?? 0,
    unpaidTables: risk.unpaidTables ?? 0,
    soldOutItems: 0,
    failedPayments: 0
  };

  const kpis = dashboardData?.todayKPIs ?? {};
  const todayKPIs: TodayKPI = {
    ordersToday: kpis.ordersToday ?? 0,
    ordersYesterday: kpis.ordersYesterday ?? 0,
    revenueToday: kpis.revenueToday ?? 0,
    revenueYesterday: kpis.revenueYesterday ?? 0,
    avgOrderValue: kpis.avgOrderValue ?? 0,
    avgOrderValueYesterday: kpis.avgOrderValueYesterday ?? 0,
    avgGuestsPerTable: 0,
    avgGuestsPerTableYesterday: 0,
    customerRating: kpis.customerRating ?? 0,
    customerRatingYesterday: kpis.customerRatingYesterday ?? 0
  };

  const aiInsight = null as AIInsightForDashboard | null;

  const recentOrders: RecentOrder[] = (dashboardData?.recentOrders ?? []).map((o: any) => ({
    id: o.orderNumber ?? o.id,
    time: o.createdAt ? new Date(o.createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '',
    table: o.tableNumber ? `Table ${o.tableNumber}` : (o.orderType === 'takeaway' ? 'Takeaway' : '–'),
    items: 0,
    amount: o.amount ?? 0,
    status: o.status === 'delivered' || o.status === 'served' ? 'served'
          : o.paymentReceived ? 'paid'
          : 'preparing',
  }));

  const topItems: TopItem[] = (dashboardData?.topItems ?? []).map((i: any) => ({
    name: i.name,
    orders: i.orderedCount ?? 0,
    status: 'available',
  }));

  const getSeverityColor = (severity: AlertSeverity) => {
    switch (severity) {
      case 'critical': return 'bg-red-50 border-red-200 text-red-900';
      case 'warning': return 'bg-amber-50 border-amber-200 text-amber-900';
    }
  };

  const getSeverityIconColor = (severity: AlertSeverity) => {
    switch (severity) {
      case 'critical': return 'text-red-600';
      case 'warning': return 'text-amber-600';
    }
  };

  const getKitchenLoadColor = (load: KitchenLoad) => {
    switch (load) {
      case 'low': return 'bg-emerald-100 text-emerald-700';
      case 'medium': return 'bg-amber-100 text-amber-700';
      case 'high': return 'bg-red-100 text-red-700';
    }
  };

  const getStatusColor = (status: RecentOrder['status']) => {
    switch (status) {
      case 'preparing': return 'bg-blue-100 text-blue-700';
      case 'served': return 'bg-amber-100 text-amber-700';
      case 'paid': return 'bg-emerald-100 text-emerald-700';
    }
  };

  const getItemStatusBadge = (status: TopItem['status']) => {
    switch (status) {
      case 'available': return null;
      case 'low-stock': return (
        <span className="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs">
          Low stock
        </span>
      );
      case 'sold-out': return (
        <span className="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">
          Sold out
        </span>
      );
    }
  };

  const calculateChange = (current: number, previous: number) => {
    const change = ((current - previous) / previous) * 100;
    return {
      value: Math.abs(change).toFixed(1),
      isPositive: change >= 0
    };
  };

  // Handle any action click - if locked, show modal
  const handleActionClick = (action: () => void) => {
    if (isLocked && onLockedActionClick) {
      onLockedActionClick();
    } else {
      action();
    }
  };

  return (
    <div className="p-6 max-w-[1600px] mx-auto space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl mb-1">Dashboard {isLocked && <span className="text-base text-amber-600">(Demo Data)</span>}</h1>
          <p className="text-gray-600">
            {currentTime.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })} · 
            {currentTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
          </p>
        </div>
        {/* Live Status Indicator - Only show when truly live and discoverable */}
        {!isLocked && (
          <div className="flex items-center gap-2">
            {isLiveAndDiscoverable ? (
              <>
                <div className="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                <span className="text-sm font-medium text-emerald-700">Live & Discoverable</span>
              </>
            ) : (
              <>
                <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                <span className="text-sm text-gray-600">Activated · Not live</span>
              </>
            )}
          </div>
        )}
      </div>

      {/* Guided Enablement Panel - Only show in demo mode */}
      {vendorStatus === 'demo' && (
        <GuidedEnablementPanel
          onNavigate={onNavigate as (view: string) => void}
          setupProgress={{
            appearance: false,
            menu: false,
            inventory: false,
            tablesQR: false,
            payments: false
          }}
        />
      )}

      {/* Only show operational dashboard if not demo */}
      {vendorStatus !== 'demo' && (
        <>
          {/* 1. Needs Attention - CRITICAL SECTION */}
          <div>
            <div className="mb-4">
              <h2 className="text-xl flex items-center gap-2 mb-1">
                Needs Attention
              </h2>
              <p className="text-sm text-gray-500">
                Live operational issues that may impact service or revenue
              </p>
            </div>

            {alerts.length > 0 ? (
              <div className="space-y-3">
                {alerts.slice(0, 5).map((alert) => {
                  const Icon = alert.icon;
                  return (
                    <Card 
                      key={alert.id} 
                      className={`border-l-4 hover:shadow-md transition-shadow cursor-pointer ${getSeverityColor(alert.severity)}`}
                      onClick={() => onNavigate(alert.navigateTo)}
                    >
                      <CardContent className="p-4">
                        <div className="flex items-center justify-between gap-4">
                          <div className="flex items-center gap-3 flex-1 min-w-0">
                            <Icon className={`w-5 h-5 flex-shrink-0 ${getSeverityIconColor(alert.severity)}`} />
                            <div className="flex-1 min-w-0">
                              <div className="font-medium">{alert.message}</div>
                              <div className="text-sm opacity-60 mt-0.5">{alert.timeReference}</div>
                            </div>
                          </div>
                          <Button 
                            variant="outline" 
                            size="sm"
                            className="flex-shrink-0"
                            onClick={(e) => {
                              e.stopPropagation();
                              onNavigate(alert.navigateTo);
                            }}
                          >
                            {alert.action}
                            <ArrowRight className="w-4 h-4 ml-1" />
                          </Button>
                        </div>
                      </CardContent>
                    </Card>
                  );
                })}
              </div>
            ) : (
              <Card className="border-emerald-200 bg-emerald-50/30">
                <CardContent className="p-6">
                  <div className="flex items-center gap-3">
                    <CheckCircle className="w-6 h-6 text-emerald-600 flex-shrink-0" />
                    <div>
                      <div className="font-medium text-emerald-900 mb-0.5">All clear</div>
                      <div className="text-sm text-emerald-700/80">No immediate issues detected</div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>

          {/* 2. Live Operations Snapshot */}
          <Card className="border-2 border-blue-200 bg-blue-50/30">
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Activity className="w-5 h-5 text-blue-600" />
                Live Now
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                {/* Open Tables */}
                <div className="text-center p-4 bg-white rounded-lg">
                  <div className="text-sm text-gray-600 mb-1">Open Tables</div>
                  <div className="text-3xl font-bold">
                    {liveStatus.openTables}<span className="text-lg text-gray-400">/{liveStatus.totalTables}</span>
                  </div>
                </div>

                {/* Active Orders */}
                <div className="text-center p-4 bg-white rounded-lg">
                  <div className="text-sm text-gray-600 mb-1">Active Orders</div>
                  <div className="text-3xl font-bold">{liveStatus.activeOrders}</div>
                </div>

                {/* Waiting to Pay */}
                <div className="text-center p-4 bg-white rounded-lg">
                  <div className="text-sm text-gray-600 mb-1">Waiting to Pay</div>
                  <div className={`text-3xl font-bold ${liveStatus.tablesWaitingToPay > 0 ? 'text-amber-600' : ''}`}>
                    {liveStatus.tablesWaitingToPay}
                  </div>
                </div>

                {/* Kitchen Load */}
                <div className="text-center p-4 bg-white rounded-lg relative">
                  <div className="text-sm text-gray-600 mb-1 flex items-center justify-center gap-1">
                    Kitchen Load
                    <div className="relative group">
                      <Info className="w-3 h-3 text-gray-400 cursor-help" />
                      <div className="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 hidden group-hover:block w-48 bg-gray-900 text-white text-xs rounded px-3 py-2 shadow-lg z-10">
                        Based on active orders compared to your average kitchen capacity.
                      </div>
                    </div>
                  </div>
                  <div className="mt-2">
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${getKitchenLoadColor(liveStatus.kitchenLoad)}`}>
                      {liveStatus.kitchenLoad.charAt(0).toUpperCase() + liveStatus.kitchenLoad.slice(1)}
                    </span>
                  </div>
                </div>

                {/* Next Peak */}
                <div className="col-span-2 p-4 bg-white rounded-lg flex items-center justify-center gap-3">
                  <Flame className="w-6 h-6 text-orange-500" />
                  <div>
                    <div className="text-sm text-gray-600">{liveStatus.nextPeakWindow}</div>
                    <div className="text-2xl font-bold">in {liveStatus.minutesUntilPeak} min</div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* 3. Revenue at Risk */}
            <Card className="border-amber-200">
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg">
                  <DollarSign className="w-5 h-5 text-amber-600" />
                  Revenue at Risk Today
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="mb-4">
                  <div className="text-4xl font-bold text-amber-600">€{revenueAtRisk.total}</div>
                </div>
                <div className="space-y-2 text-sm">
                  <div 
                    className="flex items-center justify-between p-2 bg-gray-50 rounded cursor-pointer hover:bg-gray-100 transition-colors"
                    onClick={() => onNavigate('orders')}
                  >
                    <span className="text-gray-600">Unpaid tables</span>
                    <span className="font-medium">€{revenueAtRisk.unpaidTables}</span>
                  </div>
                  <div 
                    className="flex items-center justify-between p-2 bg-gray-50 rounded cursor-pointer hover:bg-gray-100 transition-colors"
                    onClick={() => onNavigate('menu')}
                  >
                    <span className="text-gray-600">Sold-out items</span>
                    <span className="font-medium">€{revenueAtRisk.soldOutItems}</span>
                  </div>
                  <div 
                    className="flex items-center justify-between p-2 bg-gray-50 rounded cursor-pointer hover:bg-gray-100 transition-colors"
                    onClick={() => onNavigate('billing')}
                  >
                    <span className="text-gray-600">Failed payments</span>
                    <span className="font-medium">€{revenueAtRisk.failedPayments}</span>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* 4. Today's KPIs (compact) */}
            <div className="lg:col-span-2 grid grid-cols-2 md:grid-cols-3 gap-4">
              {/* Orders Today */}
              <Card>
                <CardContent className="p-3">
                  <div className="flex items-center gap-2 mb-1">
                    <ShoppingCart className="w-4 h-4 text-gray-400" />
                    <div className="text-xs text-gray-600">Orders Today</div>
                  </div>
                  <div className="text-xl font-bold mb-1">{todayKPIs.ordersToday}</div>
                  <div className={`text-[10px] ${calculateChange(todayKPIs.ordersToday, todayKPIs.ordersYesterday).isPositive ? 'text-emerald-600/70' : 'text-red-600/70'}`}>
                    {calculateChange(todayKPIs.ordersToday, todayKPIs.ordersYesterday).isPositive ? '+' : ''}
                    {calculateChange(todayKPIs.ordersToday, todayKPIs.ordersYesterday).value}% vs yesterday
                  </div>
                </CardContent>
              </Card>

              {/* Revenue Today */}
              <Card>
                <CardContent className="p-3">
                  <div className="flex items-center gap-2 mb-1">
                    <DollarSign className="w-4 h-4 text-gray-400" />
                    <div className="text-xs text-gray-600">Revenue Today</div>
                  </div>
                  <div className="text-xl font-bold mb-1">€{todayKPIs.revenueToday.toLocaleString()}</div>
                  <div className={`text-[10px] ${calculateChange(todayKPIs.revenueToday, todayKPIs.revenueYesterday).isPositive ? 'text-emerald-600/70' : 'text-red-600/70'}`}>
                    {calculateChange(todayKPIs.revenueToday, todayKPIs.revenueYesterday).isPositive ? '+' : ''}
                    {calculateChange(todayKPIs.revenueToday, todayKPIs.revenueYesterday).value}% vs yesterday
                  </div>
                </CardContent>
              </Card>

              {/* Avg Order Value */}
              <Card>
                <CardContent className="p-3">
                  <div className="flex items-center gap-2 mb-1">
                    <TrendingUp className="w-4 h-4 text-gray-400" />
                    <div className="text-xs text-gray-600">Avg Order Value</div>
                  </div>
                  <div className="text-xl font-bold mb-1">€{todayKPIs.avgOrderValue}</div>
                  <div className={`text-[10px] ${calculateChange(todayKPIs.avgOrderValue, todayKPIs.avgOrderValueYesterday).isPositive ? 'text-emerald-600/70' : 'text-red-600/70'}`}>
                    {calculateChange(todayKPIs.avgOrderValue, todayKPIs.avgOrderValueYesterday).isPositive ? '+' : ''}
                    {calculateChange(todayKPIs.avgOrderValue, todayKPIs.avgOrderValueYesterday).value}% vs yesterday
                  </div>
                </CardContent>
              </Card>

              {/* Avg Guests per Table */}
              <Card>
                <CardContent className="p-3">
                  <div className="flex items-center gap-2 mb-1">
                    <Users className="w-4 h-4 text-gray-400" />
                    <div className="text-xs text-gray-600">Avg Guests/Table</div>
                  </div>
                  <div className="text-xl font-bold mb-1">{todayKPIs.avgGuestsPerTable}</div>
                  <div className={`text-[10px] ${calculateChange(todayKPIs.avgGuestsPerTable, todayKPIs.avgGuestsPerTableYesterday).isPositive ? 'text-emerald-600/70' : 'text-red-600/70'}`}>
                    {calculateChange(todayKPIs.avgGuestsPerTable, todayKPIs.avgGuestsPerTableYesterday).isPositive ? '+' : ''}
                    {calculateChange(todayKPIs.avgGuestsPerTable, todayKPIs.avgGuestsPerTableYesterday).value}% vs yesterday
                  </div>
                </CardContent>
              </Card>

              {/* Customer Rating */}
              <Card>
                <CardContent className="p-3">
                  <div className="flex items-center gap-2 mb-1">
                    <Star className="w-4 h-4 text-gray-400" />
                    <div className="text-xs text-gray-600">Customer Rating</div>
                  </div>
                  <div className="text-xl font-bold mb-1">{todayKPIs.customerRating}</div>
                  <div className={`text-[10px] ${calculateChange(todayKPIs.customerRating, todayKPIs.customerRatingYesterday).isPositive ? 'text-emerald-600/70' : 'text-red-600/70'}`}>
                    {calculateChange(todayKPIs.customerRating, todayKPIs.customerRatingYesterday).isPositive ? '+' : ''}
                    {calculateChange(todayKPIs.customerRating, todayKPIs.customerRatingYesterday).value}% vs yesterday
                  </div>
                </CardContent>
              </Card>

              {/* AI Insight Slot (Only ONE) */}
              {aiInsight && aiInsight.affectsToday && (
                <Card className="bg-gradient-to-br from-purple-50 to-pink-50 border-purple-200">
                  <CardContent className="p-4">
                    <div className="flex items-center gap-2 mb-2">
                      <Sparkles className="w-4 h-4 text-purple-600" />
                      <span className="px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full text-xs">
                        High confidence
                      </span>
                    </div>
                    <div className="text-sm text-gray-900 mb-3">{aiInsight.message}</div>
                    <Button 
                      size="sm" 
                      className="bg-purple-600 hover:bg-purple-700 text-white w-full"
                      onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        onNavigate('loyalty');
                      }}
                    >
                      {aiInsight.cta}
                      <ArrowRight className="w-3 h-3 ml-1" />
                    </Button>
                  </CardContent>
                </Card>
              )}
            </div>
          </div>

          {/* 5. Bottom Section - Context & Activity */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Most Ordered Items Today */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <ChefHat className="w-5 h-5" />
                  Most Ordered Today
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {topItems.map((item, index) => (
                    <div key={item.name} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                      <div className="flex items-center gap-3 flex-1">
                        <div className="text-lg font-bold text-gray-400 w-6">{index + 1}</div>
                        <div className="flex-1">
                          <div className="font-medium">{item.name}</div>
                          <div className="text-sm text-gray-600">{item.orders} orders</div>
                        </div>
                      </div>
                      {getItemStatusBadge(item.status)}
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>

            {/* Recent Activity */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Activity className="w-5 h-5" />
                  Recent Orders
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {recentOrders.map((order) => (
                    <div key={order.id} className="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-1">
                          <span className="font-medium text-sm">{order.table}</span>
                          <span className="text-xs text-gray-500">{order.time}</span>
                        </div>
                        <div className="text-sm text-gray-600">{order.items} items · €{order.amount}</div>
                      </div>
                      <span className={`px-2 py-1 rounded text-xs font-medium ${getStatusColor(order.status)}`}>
                        {order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                      </span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>
        </>
      )}
    </div>
  );
}