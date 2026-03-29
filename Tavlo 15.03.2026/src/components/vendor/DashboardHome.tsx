import { useState, useEffect } from 'react';
import { TrendingUp, TrendingDown, DollarSign, ShoppingBag, Users, Star, Sparkles } from "lucide-react";
import { Card } from "../ui/card";
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import { api } from '../../utils/api';
import { AIInsightCard } from '../ai/AIComponents';
import { Button } from '../ui/button';
import { AIInsightsModal } from './AIInsightsModal';

const chartData = [
  { date: "Mon", orders: 24 },
  { date: "Tue", orders: 32 },
  { date: "Wed", orders: 28 },
  { date: "Thu", orders: 45 },
  { date: "Fri", orders: 52 },
  { date: "Sat", orders: 68 },
  { date: "Sun", orders: 48 },
];

const topItems = [
  { name: "Margherita Pizza", orders: 45, revenue: "€675.00" },
  { name: "Caesar Salad", orders: 32, revenue: "€416.00" },
  { name: "Tiramisu", orders: 28, revenue: "€252.00" },
  { name: "Pasta Carbonara", orders: 25, revenue: "€475.00" },
];

interface DashboardHomeProps {
  vendorId: string;
}

export function DashboardHome({ vendorId }: DashboardHomeProps) {
  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showInsightsModal, setShowInsightsModal] = useState(false);

  // All insights data for the modal
  const allInsights = [
    {
      type: 'recommendation' as const,
      title: 'Menu Positioning Opportunity',
      description: 'Your Caesar Salad gets high views but low orders. Moving it higher in the menu could increase orders by 15%.',
      metric: 'Potential additional revenue: €120/month',
      action: {
        label: 'Reorder menu',
        onClick: () => {}
      },
      explanation: 'Based on view patterns and similar restaurant data'
    },
    {
      type: 'success' as const,
      title: 'Top Performer',
      description: 'Margherita Pizza is your bestseller! Consider featuring it more prominently or creating variations.',
      metric: '45 orders this week (+18% vs last week)',
      explanation: 'Analysis of order trends over 30 days'
    },
    {
      type: 'warning' as const,
      title: 'Peak Hour Staffing',
      description: 'Friday and Saturday evenings (7-9pm) show slower order processing times. Consider additional staff during these hours.',
      metric: 'Average wait time: 18 mins (target: 12 mins)',
      explanation: 'Based on order fulfillment data from last 2 weeks'
    },
    {
      type: 'recommendation' as const,
      title: 'Price Optimization',
      description: 'Your Tiramisu is priced 20% below market average. A small price increase could boost revenue without impacting orders.',
      metric: 'Potential additional revenue: €80/month',
      explanation: 'Compared to 15 similar restaurants in your area'
    }
  ];

  useEffect(() => {
    loadOrders();
  }, []);

  const loadOrders = async () => {
    try {
      const data = await api.getVendorOrders(vendorId);
      setOrders(data);
    } catch (error) {
      console.error('Error loading orders:', error);
    } finally {
      setLoading(false);
    }
  };

  // Calculate stats from real data
  const todayOrders = orders.filter(o => {
    const orderDate = new Date(o.createdAt);
    const today = new Date();
    return orderDate.toDateString() === today.toDateString();
  });

  const todayRevenue = todayOrders.reduce((sum, o) => sum + (o.total || 0), 0);
  const avgOrderValue = todayOrders.length > 0 ? todayRevenue / todayOrders.length : 0;

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div>
        <h2>Dashboard</h2>
        <p className="text-neutral-600 mt-1">Welcome back! Here's what's happening today.</p>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="p-4 md:p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-neutral-600">Today's Orders</p>
              <h3 className="mt-2">{todayOrders.length}</h3>
              <div className="flex items-center gap-1 mt-2">
                <TrendingUp className="h-4 w-4 text-[--color-success]" />
                <span className="text-sm text-[--color-success]">+12%</span>
                <span className="text-xs text-neutral-500">vs yesterday</span>
              </div>
            </div>
            <div className="h-12 w-12 bg-[--color-primary-light] rounded-lg flex items-center justify-center">
              <ShoppingBag className="h-6 w-6 text-[--color-primary]" />
            </div>
          </div>
        </Card>

        <Card className="p-4 md:p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-neutral-600">Revenue</p>
              <h3 className="mt-2">€{todayRevenue.toFixed(2)}</h3>
              <div className="flex items-center gap-1 mt-2">
                <TrendingUp className="h-4 w-4 text-[--color-success]" />
                <span className="text-sm text-[--color-success]">+8%</span>
                <span className="text-xs text-neutral-500">vs yesterday</span>
              </div>
            </div>
            <div className="h-12 w-12 bg-[--color-success-light] rounded-lg flex items-center justify-center">
              <DollarSign className="h-6 w-6 text-[--color-success]" />
            </div>
          </div>
        </Card>

        <Card className="p-4 md:p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-neutral-600">Avg. Order Value</p>
              <h3 className="mt-2">€{avgOrderValue.toFixed(2)}</h3>
              <div className="flex items-center gap-1 mt-2">
                <TrendingDown className="h-4 w-4 text-[--color-error]" />
                <span className="text-sm text-[--color-error]">-3%</span>
                <span className="text-xs text-neutral-500">vs yesterday</span>
              </div>
            </div>
            <div className="h-12 w-12 bg-[--color-warning-light] rounded-lg flex items-center justify-center">
              <DollarSign className="h-6 w-6 text-[--color-warning]" />
            </div>
          </div>
        </Card>

        <Card className="p-4 md:p-6">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-neutral-600">Customer Rating</p>
              <h3 className="mt-2">4.8</h3>
              <div className="flex items-center gap-1 mt-2">
                <Star className="h-4 w-4 text-[--color-warning] fill-current" />
                <span className="text-sm text-neutral-600">156 reviews</span>
              </div>
            </div>
            <div className="h-12 w-12 bg-[--color-warning-light] rounded-lg flex items-center justify-center">
              <Users className="h-6 w-6 text-[--color-warning]" />
            </div>
          </div>
        </Card>
      </div>

      {/* AI Insights Section */}
      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-purple-600" />
            <h3>AI Insights</h3>
          </div>
          <Button variant="outline" size="sm" className="text-purple-600 border-purple-600 hover:bg-purple-50" onClick={() => setShowInsightsModal(true)}>
            View All Insights
          </Button>
        </div>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <AIInsightCard
            type="recommendation"
            title="Menu Positioning Opportunity"
            description="Your Caesar Salad gets high views but low orders. Moving it higher in the menu could increase orders by 15%."
            metric="Potential additional revenue: €120/month"
            action={{
              label: 'Reorder menu',
              onClick: () => {}
            }}
            explanation="Based on view patterns and similar restaurant data"
          />
          
          <AIInsightCard
            type="success"
            title="Top Performer"
            description="Margherita Pizza is your bestseller! Consider featuring it more prominently or creating variations."
            metric="45 orders this week (+18% vs last week)"
            explanation="Analysis of order trends over 30 days"
          />
        </div>
      </div>

      {/* Chart and Top Items */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Orders Chart */}
        <Card className="p-4 md:p-6 lg:col-span-2">
          <h3 className="mb-4">Orders This Week</h3>
          <div className="w-full" style={{ height: '256px' }}>
            <ResponsiveContainer width="100%" height={256}>
              <AreaChart data={chartData}>
                <defs>
                  <linearGradient id="colorOrders" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#1A73E8" stopOpacity={0.2}/>
                    <stop offset="95%" stopColor="#1A73E8" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="#E5E5E5" />
                <XAxis 
                  dataKey="date" 
                  tick={{ fontSize: 12 }}
                  stroke="#A3A3A3"
                />
                <YAxis 
                  tick={{ fontSize: 12 }}
                  stroke="#A3A3A3"
                />
                <Tooltip 
                  contentStyle={{ 
                    backgroundColor: "white", 
                    border: "1px solid #E5E5E5",
                    borderRadius: "8px"
                  }}
                />
                <Area 
                  type="monotone" 
                  dataKey="orders" 
                  stroke="#1A73E8" 
                  strokeWidth={2}
                  fillOpacity={1} 
                  fill="url(#colorOrders)" 
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </Card>

        {/* Top Items */}
        <Card className="p-4 md:p-6">
          <h3 className="mb-4">Most Ordered Items</h3>
          <div className="space-y-4">
            {topItems.map((item, idx) => (
              <div key={idx} className="flex items-center justify-between">
                <div className="flex-1 min-w-0">
                  <p className="text-sm text-neutral-900 truncate">{item.name}</p>
                  <p className="text-xs text-neutral-600">{item.orders} orders</p>
                </div>
                <p className="text-sm text-neutral-900 ml-2">{item.revenue}</p>
              </div>
            ))}
          </div>
        </Card>
      </div>

      {/* Recent Activity */}
      <Card className="p-4 md:p-6">
        <h3 className="mb-4">Recent Activity</h3>
        <div className="space-y-4">
          {loading ? (
            <div className="text-center py-8 text-gray-500">Loading...</div>
          ) : orders.length === 0 ? (
            <div className="text-center py-8 text-gray-500">No recent activity</div>
          ) : (
            orders.slice(0, 4).map((order) => {
              const statusMap: Record<string, { color: string; text: string }> = {
                received: { color: 'bg-[--color-warning]', text: `New order #${order.orderNumber} from Table ${order.tableNumber}` },
                preparing: { color: 'bg-[--color-primary]', text: `Order #${order.orderNumber} is being prepared` },
                ready: { color: 'bg-[--color-primary]', text: `Order #${order.orderNumber} marked as ready` },
                completed: { color: 'bg-[--color-success]', text: `Payment received for order #${order.orderNumber}` },
              };
              
              const status = statusMap[order.status] || statusMap.received;
              const timeAgo = Math.floor((Date.now() - new Date(order.createdAt).getTime()) / 60000);
              
              return (
                <div key={order.id} className="flex items-start gap-3 pb-4 border-b border-neutral-200 last:border-0 last:pb-0">
                  <div className={`h-2 w-2 rounded-full mt-1.5 shrink-0 ${status.color}`}></div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm text-neutral-900">{status.text}</p>
                    <p className="text-xs text-neutral-500 mt-0.5">
                      {timeAgo < 1 ? 'Just now' : `${timeAgo} mins ago`}
                    </p>
                  </div>
                </div>
              );
            })
          )}
        </div>
      </Card>

      {/* AI Insights Modal */}
      <AIInsightsModal
        isOpen={showInsightsModal}
        onClose={() => setShowInsightsModal(false)}
        insights={allInsights}
      />
    </div>
  );
}