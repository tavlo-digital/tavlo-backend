import { Users, DollarSign, TrendingUp, AlertTriangle, Sparkles, Store, CreditCard, ShoppingCart, Wallet, Gift, AlertCircle, Calendar, Filter, TrendingDown, MessageSquare, ArrowUpRight } from 'lucide-react';
import { Button } from '../ui/button';
import { Card } from '../ui/card';
import { AIInsightCard } from '../ai/AIComponents';
import { analyzeVendorRisk } from '../../utils/aiHelpers';
import { toast } from 'sonner@2.0.3';
import { LineChart, Line, BarChart, Bar, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';

export function Dashboard() {
  // Mock data
  const stats = [
    {
      label: 'Total Vendors',
      value: '1,247',
      change: '+12%',
      trend: 'up',
      details: '142 active • 5 pending • 3 suspended',
      icon: Store,
      color: 'purple',
      tooltip: 'Aggregated platform data'
    },
    {
      label: 'Total Customers',
      value: '48,392',
      change: '+8.4%',
      trend: 'up',
      details: '12,384 registered • 35,008 guest orders',
      icon: Users,
      color: 'blue',
      tooltip: 'Aggregated platform data'
    },
    {
      label: 'Active Subscriptions',
      value: '1,089',
      change: '-2.3%',
      trend: 'down',
      details: '142 Premium • 687 Standard • 260 Basic',
      icon: CreditCard,
      color: 'green',
      tooltip: 'Subscription pricing is independent of restaurant menu pricing'
    },
    {
      label: 'Gross Order Value (via Tavlo)',
      value: '€124,580',
      change: '+15.2%',
      trend: 'up',
      details: 'Target: €150,000',
      icon: DollarSign,
      color: 'emerald',
      tooltip: 'Total order value processed through Tavlo. This is not vendor profit.'
    },
    {
      label: 'Orders Today',
      value: '3,482',
      change: '+22%',
      trend: 'up',
      details: 'This month: 89,234',
      icon: ShoppingCart,
      color: 'orange',
      tooltip: 'Platform-wide order volume'
    },
    {
      label: 'Payment Mix',
      value: '72% Card',
      change: '28% Cash',
      trend: 'neutral',
      details: 'Cash orders: 974 today',
      icon: Wallet,
      color: 'indigo',
      tooltip: 'Payment method distribution across platform'
    },
    {
      label: 'Tips Collected',
      value: '€12,847',
      change: '+18%',
      trend: 'up',
      details: 'Avg tip: €3.68',
      icon: Gift,
      color: 'pink',
      tooltip: 'Tips go directly to restaurants, not Tavlo'
    },
    {
      label: 'Pending Actions',
      value: '17',
      change: '5 urgent',
      trend: 'warning',
      details: '5 vendors • 12 complaints',
      icon: AlertCircle,
      color: 'red',
      tooltip: 'Actions requiring admin review'
    },
  ];

  const vendorGrowthData = [
    { month: 'Jan', vendors: 980, active: 870 },
    { month: 'Feb', vendors: 1020, active: 910 },
    { month: 'Mar', vendors: 1065, active: 945 },
    { month: 'Apr', vendors: 1110, active: 982 },
    { month: 'May', vendors: 1168, active: 1034 },
    { month: 'Jun', vendors: 1247, active: 1089 },
  ];

  const revenueSourceData = [
    { name: 'Subscriptions', value: 89450, color: '#8b5cf6' },
    { name: 'Transaction Fees', value: 24680, color: '#3b82f6' },
    { name: 'Premium Features', value: 8250, color: '#10b981' },
    { name: 'Advertisements', value: 2200, color: '#f59e0b' },
  ];

  const orderVolumeData = [
    { day: 'Mon', orders: 2847, revenue: 48920 },
    { day: 'Tue', orders: 3124, revenue: 52340 },
    { day: 'Wed', orders: 2956, revenue: 49870 },
    { day: 'Thu', orders: 3345, revenue: 58230 },
    { day: 'Fri', orders: 4128, revenue: 72450 },
    { day: 'Sat', orders: 4892, revenue: 85680 },
    { day: 'Sun', orders: 3682, revenue: 64120 },
  ];

  const pendingVendors = [
    { id: 1, name: 'Sakura Sushi', type: 'Japanese Restaurant', city: 'Vienna', submitted: '2 hours ago', plan: 'Standard' },
    { id: 2, name: 'Burger Palace', type: 'Fast Food', city: 'Salzburg', submitted: '5 hours ago', plan: 'Premium' },
    { id: 3, name: 'Green Bowl', type: 'Healthy Café', city: 'Graz', submitted: '1 day ago', plan: 'Basic' },
    { id: 4, name: 'Pizza Express', type: 'Italian', city: 'Linz', submitted: '1 day ago', plan: 'Standard' },
    { id: 5, name: 'Coffee Corner', type: 'Café', city: 'Innsbruck', submitted: '2 days ago', plan: 'Basic' },
  ];

  const recentComplaints = [
    { id: 1, customer: 'John D.', vendor: 'Bella Italia', issue: 'Wrong order delivered', time: '15 min ago', severity: 'high' },
    { id: 2, customer: 'Sarah M.', vendor: 'Cafe Noir', issue: 'Food quality issue', time: '1 hour ago', severity: 'medium' },
    { id: 3, customer: 'Mike P.', vendor: 'Taco House', issue: 'Long wait time', time: '2 hours ago', severity: 'low' },
  ];

  return (
    <div className="p-6 max-w-[1800px] mx-auto">
      {/* Page Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-2">
          <div>
            <h1 className="text-2xl mb-1">Platform Overview</h1>
            <p className="text-sm text-gray-500">Real-time insights and platform metrics</p>
          </div>
          <div className="flex items-center gap-3">
            <button className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 text-sm">
              <Calendar className="w-4 h-4" />
              <span>Last 30 days</span>
            </button>
            <button className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 text-sm">
              <Filter className="w-4 h-4" />
              <span>Filters</span>
            </button>
            <button className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
              Generate Report
            </button>
          </div>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {stats.map((stat, index) => {
          const Icon = stat.icon;
          const colorClasses = {
            purple: 'bg-purple-100 text-purple-600',
            blue: 'bg-blue-100 text-blue-600',
            green: 'bg-green-100 text-green-600',
            emerald: 'bg-emerald-100 text-emerald-600',
            orange: 'bg-orange-100 text-orange-600',
            indigo: 'bg-indigo-100 text-indigo-600',
            pink: 'bg-pink-100 text-pink-600',
            red: 'bg-red-100 text-red-600',
          };

          return (
            <div key={index} className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
              <div className="flex items-start justify-between mb-3">
                <div className={`w-10 h-10 ${colorClasses[stat.color as keyof typeof colorClasses]} rounded-lg flex items-center justify-center`}>
                  <Icon className="w-5 h-5" />
                </div>
                {stat.trend === 'up' && (
                  <span className="flex items-center gap-1 text-green-600 text-sm">
                    <TrendingUp className="w-4 h-4" />
                    {stat.change}
                  </span>
                )}
                {stat.trend === 'down' && (
                  <span className="flex items-center gap-1 text-red-600 text-sm">
                    <TrendingDown className="w-4 h-4" />
                    {stat.change}
                  </span>
                )}
                {stat.trend === 'warning' && (
                  <span className="flex items-center gap-1 text-red-600 text-sm">
                    <AlertCircle className="w-4 h-4" />
                    {stat.change}
                  </span>
                )}
                {stat.trend === 'neutral' && (
                  <span className="text-gray-600 text-sm">{stat.change}</span>
                )}
              </div>
              <div className="mb-1">
                <div className="text-2xl mb-1">{stat.value}</div>
                <div className="text-sm text-gray-600">{stat.label}</div>
              </div>
              <div className="text-xs text-gray-500 mt-2">{stat.details}</div>
              <div className="text-xs text-gray-500 mt-2">{stat.tooltip}</div>
            </div>
          );
        })}
      </div>

      {/* AI Insights Section */}
      <div className="mb-6">
        <div className="flex items-center gap-2 mb-4">
          <Sparkles className="w-5 h-5 text-purple-600" />
          <h2 className="text-lg">AI Platform Insights</h2>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <AIInsightCard
            type="warning"
            title="High Risk Vendor Detected"
            description="La Bella Vista has 8 unresolved complaints and declining ratings. Requires immediate review."
            metric="Risk Score: 7.2/10"
            action={{
              label: 'Review vendor',
              onClick: () => {}
            }}
            explanation="Based on complaint patterns, rating trends, and response times"
          />
          
          <AIInsightCard
            type="success"
            title="Revenue Optimization"
            description="3 vendors are underutilizing premium features. Upsell opportunities could add €450/month."
            metric="Potential MRR increase: €450"
            action={{
              label: 'View vendors',
              onClick: () => {}
            }}
            explanation="Analysis of feature usage vs subscription tiers"
          />
          
          <AIInsightCard
            type="recommendation"
            title="Churn Risk Alert"
            description="5 vendors at risk of churning based on declining usage and support tickets."
            metric="Total MRR at risk: €2,840"
            action={{
              label: 'View details',
              onClick: () => {}
            }}
            explanation="Predictive analysis of vendor engagement patterns"
          />
        </div>
      </div>

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {/* Vendor Growth */}
        <div className="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h2 className="text-lg mb-1">Vendor Growth</h2>
              <p className="text-sm text-gray-500">Total vs active vendors over time</p>
            </div>
            <select className="px-3 py-1.5 border border-gray-200 rounded-lg text-sm">
              <option>Last 6 months</option>
              <option>Last year</option>
              <option>All time</option>
            </select>
          </div>
          <ResponsiveContainer width="100%" height={280}>
            <LineChart data={vendorGrowthData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="month" tick={{ fontSize: 12 }} />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="vendors" stroke="#8b5cf6" strokeWidth={2} name="Total Vendors" />
              <Line type="monotone" dataKey="active" stroke="#10b981" strokeWidth={2} name="Active Subscriptions" />
            </LineChart>
          </ResponsiveContainer>
        </div>

        {/* Revenue by Source */}
        <div className="bg-white rounded-xl border border-gray-200 p-6">
          <div className="mb-6">
            <h2 className="text-lg mb-1">Revenue by Source</h2>
            <p className="text-sm text-gray-500">Monthly breakdown</p>
          </div>
          <ResponsiveContainer width="100%" height={200}>
            <PieChart>
              <Pie
                data={revenueSourceData}
                cx="50%"
                cy="50%"
                innerRadius={60}
                outerRadius={80}
                paddingAngle={2}
                dataKey="value"
              >
                {revenueSourceData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip formatter={(value: number) => `€${value.toLocaleString()}`} />
            </PieChart>
          </ResponsiveContainer>
          <div className="mt-4 space-y-2">
            {revenueSourceData.map((item) => (
              <div key={item.name} className="flex items-center justify-between text-sm">
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 rounded-full" style={{ backgroundColor: item.color }}></div>
                  <span className="text-gray-700">{item.name}</span>
                </div>
                <span>€{item.value.toLocaleString()}</span>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Order Volume */}
      <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-lg mb-1">Order Volume & Revenue</h2>
            <p className="text-sm text-gray-500">Last 7 days performance</p>
          </div>
        </div>
        <ResponsiveContainer width="100%" height={280}>
          <BarChart data={orderVolumeData}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="day" tick={{ fontSize: 12 }} />
            <YAxis yAxisId="left" tick={{ fontSize: 12 }} />
            <YAxis yAxisId="right" orientation="right" tick={{ fontSize: 12 }} />
            <Tooltip />
            <Legend />
            <Bar yAxisId="left" dataKey="orders" fill="#3b82f6" name="Orders" />
            <Bar yAxisId="right" dataKey="revenue" fill="#10b981" name="Revenue (€)" />
          </BarChart>
        </ResponsiveContainer>
      </div>

      {/* Action Items Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Pending Vendor Approvals */}
        <div className="bg-white rounded-xl border border-gray-200">
          <div className="p-6 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-lg mb-1">Pending Vendor Approvals</h2>
                <p className="text-sm text-gray-500">5 vendors waiting for review</p>
              </div>
              <button className="text-sm text-purple-600 hover:text-purple-700 flex items-center gap-1">
                View all
                <ArrowUpRight className="w-4 h-4" />
              </button>
            </div>
          </div>
          <div className="divide-y divide-gray-100">
            {pendingVendors.map((vendor) => (
              <div key={vendor.id} className="p-4 hover:bg-gray-50">
                <div className="flex items-start justify-between mb-2">
                  <div>
                    <div className="font-medium mb-0.5">{vendor.name}</div>
                    <div className="text-sm text-gray-500">{vendor.type} • {vendor.city}</div>
                  </div>
                  <span className="text-xs px-2 py-1 bg-purple-100 text-purple-700 rounded">
                    {vendor.plan}
                  </span>
                </div>
                <div className="flex items-center justify-between mt-3">
                  <span className="text-xs text-gray-500">{vendor.submitted}</span>
                  <div className="flex gap-2">
                    <button className="px-3 py-1.5 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700">
                      Approve
                    </button>
                    <button className="px-3 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50">
                      Review
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Recent Complaints */}
        <div className="bg-white rounded-xl border border-gray-200">
          <div className="p-6 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-lg mb-1">Recent Complaints</h2>
                <p className="text-sm text-gray-500">12 open complaints</p>
              </div>
              <button className="text-sm text-purple-600 hover:text-purple-700 flex items-center gap-1">
                View all
                <ArrowUpRight className="w-4 h-4" />
              </button>
            </div>
          </div>
          <div className="divide-y divide-gray-100">
            {recentComplaints.map((complaint) => (
              <div key={complaint.id} className="p-4 hover:bg-gray-50">
                <div className="flex items-start gap-3 mb-2">
                  <div className={`w-2 h-2 rounded-full mt-1.5 ${
                    complaint.severity === 'high' ? 'bg-red-500' :
                    complaint.severity === 'medium' ? 'bg-orange-500' :
                    'bg-yellow-500'
                  }`}></div>
                  <div className="flex-1">
                    <div className="flex items-start justify-between mb-1">
                      <div>
                        <div className="font-medium">{complaint.customer}</div>
                        <div className="text-sm text-gray-500">vs {complaint.vendor}</div>
                      </div>
                      <span className="text-xs text-gray-500">{complaint.time}</span>
                    </div>
                    <div className="text-sm text-gray-700 mb-3">{complaint.issue}</div>
                    <button className="text-xs text-purple-600 hover:text-purple-700 flex items-center gap-1">
                      <MessageSquare className="w-3 h-3" />
                      Resolve complaint
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <button className="p-4 bg-purple-600 text-white rounded-xl hover:bg-purple-700 text-left">
          <div className="text-sm mb-1">Review Pending Vendors</div>
          <div className="text-2xl">5</div>
        </button>
        <button className="p-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-left">
          <div className="text-sm mb-1">Create Invoice</div>
          <div className="text-lg">+ New</div>
        </button>
        <button className="p-4 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 text-left">
          <div className="text-sm mb-1">Send Announcement</div>
          <div className="text-lg">Broadcast</div>
        </button>
        <button className="p-4 bg-orange-600 text-white rounded-xl hover:bg-orange-700 text-left">
          <div className="text-sm mb-1">System Status</div>
          <div className="text-lg">All systems operational</div>
        </button>
      </div>
    </div>
  );
}