import { useState } from 'react';
import { 
  TrendingUp, 
  TrendingDown, 
  DollarSign, 
  ShoppingCart, 
  Users, 
  Clock, 
  CreditCard, 
  Star, 
  Lock,
  Sparkles,
  AlertCircle,
  CheckCircle,
  ChevronRight,
  ChevronDown,
  ChevronUp,
  Calendar,
  Apple,
  Smartphone,
  Banknote,
  Target,
  Zap,
  UserCheck,
  ThumbsUp,
  Filter,
  Info,
  ArrowRight,
  X
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '../ui/card';
import { Button } from '../ui/button';
import { 
  LineChart, 
  Line, 
  BarChart, 
  Bar, 
  PieChart, 
  Pie, 
  Cell,
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  Legend, 
  ResponsiveContainer 
} from 'recharts';

interface AnalyticsProps {
  vendorId: string;
  onNavigate?: (screen: 'loyalty') => void;
}

type TimePeriod = 'daily' | 'weekly' | 'monthly';
type AnalyticsView = 'standard' | 'ai';
type InsightCategory = 'all' | 'revenue' | 'operational' | 'retention' | 'reputation';
type ConfidenceLevel = 'high' | 'medium' | 'low';

interface AIInsight {
  id: string;
  category: Exclude<InsightCategory, 'all'>;
  title: string;
  description: string;
  estimatedImpact: string;
  confidence: ConfidenceLevel;
  whatHappening: string;
  whyMatters: string;
  suggestedAction: string;
  dataSource: string;
  priority: number;
}

export function Analytics({ vendorId, onNavigate }: AnalyticsProps) {
  const [timePeriod, setTimePeriod] = useState<TimePeriod>('daily');
  const [activeView, setActiveView] = useState<AnalyticsView>('standard');
  const [showServiceDetails, setShowServiceDetails] = useState(false);
  const [expandedMenuItem, setExpandedMenuItem] = useState<string | null>(null);
  const [highlightedSection, setHighlightedSection] = useState<string | null>(null);
  const [insightCategory, setInsightCategory] = useState<InsightCategory>('all');
  const [expandedInsight, setExpandedInsight] = useState<string | null>(null);
  const [showActionPanel, setShowActionPanel] = useState<string | null>(null);
  const [hasAIAccess] = useState(true); // Set to false to preview non-subscriber view

  // Sample data for Standard Analytics
  const revenueData = [
    { day: 'Mon', revenue: 2400, orders: 48 },
    { day: 'Tue', revenue: 2800, orders: 52 },
    { day: 'Wed', revenue: 2600, orders: 47 },
    { day: 'Thu', revenue: 3200, orders: 61 },
    { day: 'Fri', revenue: 4100, orders: 78 },
    { day: 'Sat', revenue: 4500, orders: 85 },
    { day: 'Sun', revenue: 3800, orders: 72 }
  ];

  const paymentMethodsData = [
    { name: 'Apple Pay', value: 35, avgTime: '8s', icon: Apple },
    { name: 'Google Pay', value: 28, avgTime: '9s', icon: Smartphone },
    { name: 'Card', value: 25, avgTime: '12s', icon: CreditCard },
    { name: 'Cash', value: 12, avgTime: '45s', icon: Banknote }
  ];

  const menuPerformanceData = [
    { name: 'Margherita Pizza', orders: 156, revenue: 1872, soldOut: 2 },
    { name: 'Carbonara', orders: 142, revenue: 1988, soldOut: 0 },
    { name: 'Tiramisu', orders: 98, revenue: 686, soldOut: 1 },
    { name: 'Caesar Salad', orders: 87, revenue: 957, soldOut: 0 },
    { name: 'Truffle Pasta', orders: 54, revenue: 1134, soldOut: 3 }
  ];

  const customerSegmentData = [
    { segment: '1 visit', customers: 245, revenue: 3185 },
    { segment: '2-3 visits', customers: 98, revenue: 4214 },
    { segment: '4+ visits', customers: 42, revenue: 5892 }
  ];

  // Peak Hours Heat Map Data (24 hours x 7 days)
  const peakHoursData = [
    { hour: '11am', Mon: 12, Tue: 15, Wed: 13, Thu: 18, Fri: 25, Sat: 32, Sun: 28 },
    { hour: '12pm', Mon: 28, Tue: 32, Wed: 30, Thu: 35, Fri: 42, Sat: 58, Sun: 52 },
    { hour: '1pm', Mon: 35, Tue: 38, Wed: 36, Thu: 41, Fri: 48, Sat: 62, Sun: 58 },
    { hour: '2pm', Mon: 22, Tue: 25, Wed: 23, Thu: 28, Fri: 35, Sat: 45, Sun: 42 },
    { hour: '3pm', Mon: 8, Tue: 10, Wed: 9, Thu: 12, Fri: 15, Sat: 18, Sun: 16 },
    { hour: '4pm', Mon: 5, Tue: 6, Wed: 5, Thu: 8, Fri: 12, Sat: 14, Sun: 12 },
    { hour: '5pm', Mon: 10, Tue: 12, Wed: 11, Thu: 15, Fri: 22, Sat: 28, Sun: 24 },
    { hour: '6pm', Mon: 25, Tue: 28, Wed: 26, Thu: 32, Fri: 45, Sat: 65, Sun: 58 },
    { hour: '7pm', Mon: 42, Tue: 45, Wed: 43, Thu: 52, Fri: 68, Sat: 85, Sun: 78 },
    { hour: '8pm', Mon: 48, Tue: 52, Wed: 50, Thu: 58, Fri: 75, Sat: 92, Sun: 85 },
    { hour: '9pm', Mon: 32, Tue: 35, Wed: 33, Thu: 38, Fri: 52, Sat: 68, Sun: 62 },
    { hour: '10pm', Mon: 15, Tue: 18, Wed: 16, Thu: 22, Fri: 35, Sat: 42, Sun: 38 }
  ];

  // Peak Days Data (aggregated)
  const peakDaysData = [
    { day: 'Monday', orders: 207, revenue: 2400, avgOrders: 17 },
    { day: 'Tuesday', orders: 236, revenue: 2800, avgOrders: 20 },
    { day: 'Wednesday', orders: 225, revenue: 2600, avgOrders: 19 },
    { day: 'Thursday', orders: 281, revenue: 3200, avgOrders: 23 },
    { day: 'Friday', orders: 424, revenue: 4100, avgOrders: 35 },
    { day: 'Saturday', orders: 510, revenue: 4500, avgOrders: 43 },
    { day: 'Sunday', orders: 443, revenue: 3800, avgOrders: 37 }
  ];

  // Orders by Category Data
  const ordersByCategoryData = [
    { category: 'Pizza', orders: 342, revenue: 4788, percentage: 28 },
    { category: 'Pasta', orders: 298, revenue: 4172, percentage: 24 },
    { category: 'Salads', orders: 165, revenue: 1815, percentage: 14 },
    { category: 'Desserts', orders: 201, revenue: 1407, percentage: 16 },
    { category: 'Beverages', orders: 223, revenue: 1115, percentage: 18 }
  ];

  const COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#6366f1'];

  // AI Insights Data
  const aiInsights: AIInsight[] = [
    {
      id: '1',
      category: 'operational',
      title: 'Low Activity at 4:00 PM',
      description: 'Orders drop to daily minimum around 16:00',
      estimatedImpact: '+€180/day',
      confidence: 'high',
      whatHappening: 'Orders at 16:00 are the lowest of the day, averaging 4–6 orders compared to 18–22 orders during peak hours.',
      whyMatters: 'This creates unused kitchen and table capacity that could be converted into revenue.',
      suggestedAction: 'Create a time-based promotion from 15:30–17:00',
      dataSource: 'Data from last 45 days · 1,120 orders analyzed',
      priority: 1
    },
    {
      id: '2',
      category: 'revenue',
      title: 'Monday Underutilization',
      description: 'Mondays generate 32% fewer orders than weekly average',
      estimatedImpact: '+€520/week',
      confidence: 'medium',
      whatHappening: 'Monday orders average 207 compared to the weekly average of 304 orders per day.',
      whyMatters: 'Fixed costs (staff, rent, utilities) remain the same regardless of traffic. Low Monday volume reduces profitability.',
      suggestedAction: 'Run a Monday-only dine-in offer',
      dataSource: 'Data from last 60 days · 8 Mondays analyzed',
      priority: 2
    },
    {
      id: '3',
      category: 'operational',
      title: 'Peak Hour Payment Delays',
      description: 'Checkout time increases by 41% between 7–8 PM',
      estimatedImpact: '−12 min table turnover',
      confidence: 'high',
      whatHappening: 'Average checkout time increases from 14.2s to 20.1s during peak hours, likely due to server workload and payment system queuing.',
      whyMatters: 'Longer payment times extend table occupancy during your busiest period, reducing capacity for new customers.',
      suggestedAction: 'Enable self-checkout via QR code during 7–9 PM',
      dataSource: 'Data from last 30 days · 2,840 transactions analyzed',
      priority: 3
    },
    {
      id: '4',
      category: 'retention',
      title: 'First-Time Customer Return Rate',
      description: 'Only 8% of first-time customers return within 30 days',
      estimatedImpact: '+€1,200/month',
      confidence: 'medium',
      whatHappening: '245 first-time customers visited this month. Only 19 returned within 30 days. Industry average for similar restaurants is 24%.',
      whyMatters: 'Acquiring new customers costs 5-7x more than retaining existing ones. Low retention directly impacts profitability.',
      suggestedAction: 'Send automated follow-up email 7 days after first visit with a personalized 10% return offer',
      dataSource: 'Data from last 90 days · 685 new customers tracked',
      priority: 4
    },
    {
      id: '5',
      category: 'revenue',
      title: 'High-Interest Menu Item',
      description: 'Truffle Pasta has 3x menu views but low conversion',
      estimatedImpact: '+€400/week',
      confidence: 'low',
      whatHappening: 'Truffle Pasta gets viewed 162 times per day but only ordered 7.7 times. Conversion rate is 4.7%, compared to 18% for similar items.',
      whyMatters: 'High interest with low conversion suggests barriers: pricing, description clarity, or perceived value.',
      suggestedAction: 'Test reducing price by €2 or add photo + detailed description highlighting ingredients',
      dataSource: 'Data from last 30 days · Limited menu interaction data',
      priority: 5
    },
    {
      id: '6',
      category: 'reputation',
      title: 'Low Ratings During Peak Hours',
      description: '42% of 1–2 star reviews occur between 7–9 PM',
      estimatedImpact: 'Rating risk',
      confidence: 'medium',
      whatHappening: 'Of 12 low ratings received this month, 5 were submitted during peak dinner hours. Most cite wait times or service speed.',
      whyMatters: 'Peak hour service pressure correlates with negative reviews. Ratings directly influence new customer acquisition.',
      suggestedAction: 'Highlight high-margin items that require less kitchen time during peak hours',
      dataSource: 'Data from last 60 days · 284 reviews analyzed',
      priority: 6
    }
  ];

  // Filter insights by category
  const filteredInsights = insightCategory === 'all' 
    ? aiInsights 
    : aiInsights.filter(insight => insight.category === insightCategory);

  // Top 3 priority insights for overview
  const topInsights = aiInsights.sort((a, b) => a.priority - b.priority).slice(0, 3);

  // Get category icon
  const getCategoryIcon = (category: Exclude<InsightCategory, 'all'>) => {
    switch (category) {
      case 'revenue': return Target;
      case 'operational': return Zap;
      case 'retention': return UserCheck;
      case 'reputation': return ThumbsUp;
    }
  };

  // Get confidence color
  const getConfidenceColor = (confidence: ConfidenceLevel) => {
    switch (confidence) {
      case 'high': return 'bg-emerald-100 text-emerald-700';
      case 'medium': return 'bg-blue-100 text-blue-700';
      case 'low': return 'bg-amber-100 text-amber-700';
    }
  };

  // Scroll to section helper
  const scrollToSection = (sectionId: string) => {
    const element = document.getElementById(sectionId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'start' });
      setHighlightedSection(sectionId);
      setTimeout(() => setHighlightedSection(null), 2000);
    }
  };

  const MetricCard = ({ 
    title, 
    value, 
    change, 
    icon: Icon, 
    positive = true,
    contextLabel
  }: { 
    title: string; 
    value: string; 
    change: string; 
    icon: any; 
    positive?: boolean;
    contextLabel?: string;
  }) => (
    <Card>
      <CardContent className="p-6">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm text-gray-600">{title}</span>
          <Icon className="w-5 h-5 text-gray-400" />
        </div>
        <div className="text-3xl mb-1">{value}</div>
        {contextLabel && (
          <div className="text-xs text-gray-400 mb-1">{contextLabel}</div>
        )}
        <div className="flex items-center gap-1 text-sm">
          {positive ? (
            <TrendingUp className="w-4 h-4 text-emerald-600" />
          ) : (
            <TrendingDown className="w-4 h-4 text-red-600" />
          )}
          <span className={positive ? 'text-emerald-600' : 'text-red-600'}>
            {change}
          </span>
          <span className="text-gray-500">vs last period</span>
        </div>
      </CardContent>
    </Card>
  );

  const SectionBadge = ({ type }: { type: 'included' | 'premium' }) => (
    <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs ${
      type === 'included' 
        ? 'bg-emerald-100 text-emerald-700' 
        : 'bg-purple-100 text-purple-700'
    }`}>
      {type === 'included' ? (
        <>
          <CheckCircle className="w-3 h-3" />
          Included in your plan
        </>
      ) : (
        <>
          <Sparkles className="w-3 h-3" />
          AI – Advanced Plan
        </>
      )}
    </span>
  );

  const AIInsightCard = ({ 
    title, 
    problem, 
    impact, 
    action 
  }: { 
    title: string; 
    problem: string; 
    impact: string; 
    action: string;
  }) => (
    <div className="p-6 bg-white/50 backdrop-blur-sm border border-purple-200 rounded-xl">
      <div className="flex items-start gap-4">
        <div className="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
          <Sparkles className="w-5 h-5 text-purple-600" />
        </div>
        <div className="flex-1">
          <h4 className="text-lg mb-2">{title}</h4>
          <p className="text-sm text-gray-600 mb-3">{problem}</p>
          <div className="flex items-center gap-2 mb-3">
            <TrendingUp className="w-4 h-4 text-emerald-600" />
            <span className="text-sm font-medium text-emerald-700">{impact}</span>
          </div>
          <div className="p-3 bg-purple-50 rounded-lg">
            <div className="text-xs text-purple-600 uppercase tracking-wide mb-1">Suggested Action</div>
            <div className="text-sm text-gray-700">{action}</div>
          </div>
        </div>
      </div>
    </div>
  );

  return (
    <div className="p-6 max-w-[1600px] mx-auto">
      {/* Page Header */}
      <div className="mb-8">
        <h1 className="text-3xl mb-2">Analytics & Insights</h1>
        <p className="text-lg text-gray-600">Understand performance. Improve decisions. Stay in control.</p>
      </div>

      {/* View Toggle */}
      <div className="flex gap-2 mb-8 p-1 bg-gray-100 rounded-lg inline-flex">
        <button
          onClick={() => setActiveView('standard')}
          className={`px-6 py-3 rounded-lg transition-all ${
            activeView === 'standard'
              ? 'bg-white shadow-sm'
              : 'text-gray-600 hover:text-gray-900'
          }`}
        >
          Standard Analytics
        </button>
        <button
          onClick={() => setActiveView('ai')}
          className={`px-6 py-3 rounded-lg transition-all flex items-center gap-2 ${
            activeView === 'ai'
              ? 'bg-gradient-to-r from-purple-600 to-purple-500 text-white shadow-sm'
              : 'text-gray-600 hover:text-gray-900'
          }`}
        >
          <Lock className="w-4 h-4" />
          AI Insights
          <span className="px-2 py-0.5 bg-purple-900/20 rounded text-xs">Phase 3</span>
        </button>
      </div>

      {/* Standard Analytics Section */}
      {activeView === 'standard' && (
        <div className="space-y-8">
          {/* Time Period Toggle */}
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <SectionBadge type="included" />
            </div>
            <div className="flex gap-2 p-1 bg-gray-100 rounded-lg">
              <button
                onClick={() => setTimePeriod('daily')}
                className={`px-4 py-2 rounded text-sm transition-all ${
                  timePeriod === 'daily' ? 'bg-white shadow-sm' : 'text-gray-600'
                }`}
              >
                Daily
              </button>
              <button
                onClick={() => setTimePeriod('weekly')}
                className={`px-4 py-2 rounded text-sm transition-all ${
                  timePeriod === 'weekly' ? 'bg-white shadow-sm' : 'text-gray-600'
                }`}
              >
                Weekly
              </button>
              <button
                onClick={() => setTimePeriod('monthly')}
                className={`px-4 py-2 rounded text-sm transition-all ${
                  timePeriod === 'monthly' ? 'bg-white shadow-sm' : 'text-gray-600'
                }`}
              >
                Monthly
              </button>
            </div>
          </div>

          {/* Today at a Glance */}
          <div>
            <h2 className="text-xl mb-4">Today at a Glance</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
              {/* Signal Pills - Now clickable */}
              <button
                onClick={() => scrollToSection('service-efficiency')}
                className="flex items-center gap-3 p-4 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-all cursor-pointer text-left"
              >
                <AlertCircle className="w-5 h-5 text-amber-600 flex-shrink-0" />
                <div>
                  <div className="text-sm font-medium text-amber-900">Table turnover +6 min</div>
                  <div className="text-xs text-amber-700">vs yesterday</div>
                </div>
              </button>
              
              <button
                onClick={() => scrollToSection('payments-checkout')}
                className="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-all cursor-pointer text-left"
              >
                <CheckCircle className="w-5 h-5 text-emerald-600 flex-shrink-0" />
                <div>
                  <div className="text-sm font-medium text-emerald-900">Payment completion 97.2%</div>
                  <div className="text-xs text-emerald-700">healthy</div>
                </div>
              </button>
              
              <button
                onClick={() => scrollToSection('menu-performance')}
                className="flex items-center gap-3 p-4 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-all cursor-pointer text-left"
              >
                <AlertCircle className="w-5 h-5 text-amber-600 flex-shrink-0" />
                <div>
                  <div className="text-sm font-medium text-amber-900">Truffle Pasta sold out</div>
                  <div className="text-xs text-amber-700">twice today</div>
                </div>
              </button>
              
              <button
                onClick={() => scrollToSection('revenue-trend')}
                className="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-all cursor-pointer text-left"
              >
                <TrendingUp className="w-5 h-5 text-emerald-600 flex-shrink-0" />
                <div>
                  <div className="text-sm font-medium text-emerald-900">AOV +5.2%</div>
                  <div className="text-xs text-emerald-700">vs last week</div>
                </div>
              </button>
            </div>
          </div>

          {/* Key Business Metrics - Added context labels */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
            <MetricCard
              title="Total Revenue"
              value="€23,456"
              change="+12.3%"
              icon={DollarSign}
              positive={true}
              contextLabel="Gross"
            />
            <MetricCard
              title="Average Order Value"
              value="€52.80"
              change="+5.2%"
              icon={ShoppingCart}
              positive={true}
              contextLabel="Per order"
            />
            <MetricCard
              title="Orders Today"
              value="443"
              change="+8.1%"
              icon={ShoppingCart}
              positive={true}
            />
            <MetricCard
              title="Avg Guests per Table"
              value="2.7"
              change="+0.2"
              icon={Users}
              positive={true}
              contextLabel="Per table"
            />
            <MetricCard
              title="Repeat Customer Rate"
              value="38.5%"
              change="-2.1%"
              icon={Users}
              positive={false}
              contextLabel="Last 30 days"
            />
          </div>

          {/* Revenue Trend - Added ID for scrolling */}
          <Card id="revenue-trend" className={`transition-all duration-300 ${
            highlightedSection === 'revenue-trend' ? 'ring-2 ring-blue-400 shadow-lg' : ''
          }`}>
            <CardHeader>
              <CardTitle>Revenue & Orders Trend</CardTitle>
              <CardDescription>Daily performance over the last 7 days</CardDescription>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={revenueData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                  <XAxis dataKey="day" stroke="#666" />
                  <YAxis yAxisId="left" stroke="#666" />
                  <YAxis yAxisId="right" orientation="right" stroke="#666" />
                  <Tooltip />
                  <Legend />
                  <Line 
                    yAxisId="left"
                    type="monotone" 
                    dataKey="revenue" 
                    stroke="#10b981" 
                    strokeWidth={2}
                    name="Revenue (€)"
                  />
                  <Line 
                    yAxisId="right"
                    type="monotone" 
                    dataKey="orders" 
                    stroke="#3b82f6" 
                    strokeWidth={2}
                    name="Orders"
                  />
                </LineChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          {/* Order Quality & Revenue Health */}
          <div>
            <h2 className="text-2xl mb-4">Order Quality & Revenue Health</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <Card>
                <CardContent className="p-6">
                  <div className="text-sm text-gray-600 mb-2">Avg Items per Order</div>
                  <div className="text-3xl mb-1">3.2</div>
                  <div className="text-sm text-gray-500">+0.3 vs last week</div>
                </CardContent>
              </Card>
              <Card>
                <CardContent className="p-6">
                  <div className="text-sm text-gray-600 mb-2">First-Time vs Returning</div>
                  <div className="flex items-baseline gap-2 mb-1">
                    <span className="text-2xl">61%</span>
                    <span className="text-lg text-gray-500">/ 39%</span>
                  </div>
                  <div className="text-sm text-gray-500">Revenue split</div>
                </CardContent>
              </Card>
              <Card>
                <CardContent className="p-6">
                  <div className="text-sm text-gray-600 mb-2">Discounted vs Full-Price</div>
                  <div className="flex items-baseline gap-2 mb-1">
                    <span className="text-2xl">18%</span>
                    <span className="text-lg text-gray-500">/ 82%</span>
                  </div>
                  <div className="text-sm text-gray-500">Order split</div>
                </CardContent>
              </Card>
            </div>
          </div>

          {/* Service & Table Efficiency */}
          <div id="service-efficiency" className={`transition-all duration-300 ${
            highlightedSection === 'service-efficiency' ? 'ring-2 ring-blue-400 shadow-lg rounded-lg' : ''
          }`}>
            <h2 className="text-2xl mb-4">Service & Table Efficiency</h2>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <Card>
                <CardHeader>
                  <CardTitle>Average Times</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                      <div className="text-sm text-gray-600 mb-1">Table Turnover Time</div>
                      <div className="text-2xl">68 min</div>
                    </div>
                    <Clock className="w-8 h-8 text-gray-400" />
                  </div>
                  <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                      <div className="text-sm text-gray-600 mb-1">Order → Kitchen Time</div>
                      <div className="text-2xl">2.3 min</div>
                    </div>
                    <Clock className="w-8 h-8 text-gray-400" />
                  </div>
                  <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                      <div className="text-sm text-gray-600 mb-1">Order → Payment Time</div>
                      <div className="text-2xl">12.8 min</div>
                    </div>
                    <Clock className="w-8 h-8 text-gray-400" />
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Time Period Breakdown</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    <div>
                      <div className="flex justify-between text-sm mb-2">
                        <span className="text-gray-600">Lunch (11am - 2pm)</span>
                        <span className="font-medium">54 min avg</span>
                      </div>
                      <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div className="h-full bg-emerald-500 rounded-full" style={{ width: '75%' }} />
                      </div>
                    </div>
                    <div>
                      <div className="flex justify-between text-sm mb-2">
                        <span className="text-gray-600">Dinner (6pm - 10pm)</span>
                        <span className="font-medium">82 min avg</span>
                      </div>
                      <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div className="h-full bg-blue-500 rounded-full" style={{ width: '90%' }} />
                      </div>
                    </div>
                    <div className="pt-4 border-t">
                      <div className="flex justify-between text-sm mb-2">
                        <span className="text-gray-600">Weekday Average</span>
                        <span className="font-medium">64 min</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-600">Weekend Average</span>
                        <span className="font-medium">72 min</span>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>

          {/* Payments & Checkout Performance */}
          <div id="payments-checkout" className={`transition-all duration-300 ${
            highlightedSection === 'payments-checkout' ? 'ring-2 ring-blue-400 shadow-lg rounded-lg' : ''
          }`}>
            <h2 className="text-2xl mb-4">Payments & Checkout Performance</h2>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <Card>
                <CardHeader>
                  <CardTitle>Payment Method Breakdown</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    {paymentMethodsData.map((method) => {
                      const Icon = method.icon;
                      return (
                        <div key={method.name} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                          <div className="flex items-center gap-3">
                            <Icon className="w-5 h-5 text-gray-600" />
                            <div>
                              <div className="font-medium">{method.name}</div>
                              <div className="text-sm text-gray-500">Avg: {method.avgTime}</div>
                            </div>
                          </div>
                          <div className="text-right">
                            <div className="text-xl font-medium">{method.value}%</div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Checkout Metrics</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                    <div className="flex items-center justify-between">
                      <div>
                        <div className="text-sm text-emerald-700 mb-1">Payment Completion Rate</div>
                        <div className="text-3xl text-emerald-600">97.2%</div>
                      </div>
                      <CheckCircle className="w-10 h-10 text-emerald-500" />
                    </div>
                  </div>
                  <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div className="flex items-center justify-between">
                      <div>
                        <div className="text-sm text-red-700 mb-1">Payment Failures</div>
                        <div className="text-3xl text-red-600">2.8%</div>
                      </div>
                      <AlertCircle className="w-10 h-10 text-red-500" />
                    </div>
                  </div>
                  <div className="pt-2">
                    <div className="text-sm text-gray-600 mb-2">Average Checkout Time</div>
                    <div className="text-2xl">14.2 seconds</div>
                    <div className="text-sm text-gray-500 mt-1">Excluding cash payments</div>
                  </div>
                  <div className="pt-4 border-t">
                    <div className="text-xs text-gray-500 mb-2">Compared to last 7 days</div>
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-gray-600">Payment completion rate</span>
                      <span className="flex items-center gap-1 text-emerald-600">
                        <TrendingUp className="w-3 h-3" />
                        +1.2%
                      </span>
                    </div>
                    <div className="flex items-center justify-between text-sm mt-2">
                      <span className="text-gray-600">Avg checkout time</span>
                      <span className="flex items-center gap-1 text-emerald-600">
                        <TrendingDown className="w-3 h-3" />
                        -2.1s
                      </span>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>

          {/* Menu Performance */}
          <div id="menu-performance" className={`transition-all duration-300 ${
            highlightedSection === 'menu-performance' ? 'ring-2 ring-blue-400 shadow-lg rounded-lg' : ''
          }`}>
            <h2 className="text-2xl mb-4">Menu Performance</h2>
            <Card>
              <CardContent className="p-0">
                <div className="overflow-x-auto">
                  <table className="w-full">
                    <thead className="bg-gray-50 border-b">
                      <tr>
                        <th className="text-left p-4 font-medium text-gray-700">Menu Item</th>
                        <th className="text-right p-4 font-medium text-gray-700">Orders</th>
                        <th className="text-right p-4 font-medium text-gray-700">Revenue</th>
                        <th className="text-right p-4 font-medium text-gray-700">Sold Out Times</th>
                        <th className="text-right p-4 font-medium text-gray-700">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {menuPerformanceData.map((item, index) => (
                        <tr key={item.name} className="border-b hover:bg-gray-50">
                          <td className="p-4">
                            <div className="flex items-center gap-2">
                              <div className={`w-2 h-2 rounded-full ${
                                index === 0 || index === 1 ? 'bg-emerald-500' : 
                                index === 4 ? 'bg-amber-500' : 'bg-gray-300'
                              }`} />
                              {item.name}
                            </div>
                          </td>
                          <td className="text-right p-4 font-medium">{item.orders}</td>
                          <td className="text-right p-4 font-medium">€{item.revenue}</td>
                          <td className="text-right p-4">{item.soldOut}</td>
                          <td className="text-right p-4">
                            {index === 0 || index === 1 ? (
                              <span className="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-sm">
                                Top Performer
                              </span>
                            ) : index === 4 ? (
                              <span className="px-2 py-1 bg-amber-100 text-amber-700 rounded text-sm">
                                Stock Issues
                              </span>
                            ) : (
                              <span className="text-gray-500">—</span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Customer Behavior */}
          <div>
            <h2 className="text-2xl mb-4">Customer Behavior</h2>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <Card>
                <CardHeader>
                  <CardTitle>Visit Frequency Distribution</CardTitle>
                </CardHeader>
                <CardContent>
                  <ResponsiveContainer width="100%" height={250}>
                    <BarChart data={customerSegmentData}>
                      <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                      <XAxis dataKey="segment" stroke="#666" />
                      <YAxis stroke="#666" />
                      <Tooltip />
                      <Bar dataKey="customers" fill="#3b82f6" name="Customers" />
                    </BarChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Revenue per Segment</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  {customerSegmentData.map((segment, index) => (
                    <div key={segment.segment} className="p-4 bg-gray-50 rounded-lg">
                      <div className="flex items-center justify-between mb-2">
                        <span className="font-medium">{segment.segment}</span>
                        <span className="text-lg font-bold">€{segment.revenue}</span>
                      </div>
                      <div className="flex items-center justify-between text-sm text-gray-600">
                        <span>{segment.customers} customers</span>
                        <span>€{(segment.revenue / segment.customers).toFixed(2)} avg</span>
                      </div>
                    </div>
                  ))}
                  <div className="pt-4 border-t">
                    <div className="text-sm text-gray-600 mb-1">Average Days Until Return</div>
                    <div className="text-2xl">18.5 days</div>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Revenue Concentration</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center justify-center h-[250px]">
                    <div className="text-center">
                      <div className="text-sm text-gray-600 mb-2">Top 20% of customers</div>
                      <div className="text-5xl font-bold text-blue-600 mb-2">58%</div>
                      <div className="text-sm text-gray-500">of total revenue</div>
                      <div className="mt-6 p-4 bg-blue-50 rounded-lg">
                        <div className="text-xs text-blue-700 mb-1">High-value segment</div>
                        <div className="text-2xl font-bold text-blue-900">77</div>
                        <div className="text-xs text-blue-600">customers</div>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>

          {/* Peak Hours Heat Map */}
          <div>
            <h2 className="text-2xl mb-4">Peak Hours Heat Map</h2>
            <Card>
              <CardHeader>
                <CardTitle>Orders by Hour & Day</CardTitle>
                <CardDescription>Identify your busiest times to optimize staffing and inventory</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="overflow-x-auto">
                  <div className="inline-block min-w-full">
                    <div className="grid grid-cols-8 gap-1 mb-2">
                      <div className="text-xs font-medium text-gray-500 p-2"></div>
                      {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(day => (
                        <div key={day} className="text-xs font-medium text-gray-700 p-2 text-center">
                          {day}
                        </div>
                      ))}
                    </div>
                    {peakHoursData.map((row) => {
                      const maxValue = 92; // Maximum order count for scaling
                      return (
                        <div key={row.hour} className="grid grid-cols-8 gap-1 mb-1">
                          <div className="text-xs font-medium text-gray-700 p-2 flex items-center">
                            {row.hour}
                          </div>
                          {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(day => {
                            const value = row[day as keyof typeof row] as number;
                            const intensity = Math.round((value / maxValue) * 100);
                            let bgColor = 'bg-gray-100';
                            if (intensity > 80) bgColor = 'bg-emerald-600';
                            else if (intensity > 60) bgColor = 'bg-emerald-500';
                            else if (intensity > 40) bgColor = 'bg-emerald-400';
                            else if (intensity > 20) bgColor = 'bg-emerald-300';
                            else if (intensity > 10) bgColor = 'bg-emerald-200';
                            else if (intensity > 0) bgColor = 'bg-emerald-100';
                            
                            return (
                              <div
                                key={day}
                                className={`${bgColor} p-3 rounded text-center text-xs font-medium ${
                                  intensity > 60 ? 'text-white' : 'text-gray-700'
                                } hover:ring-2 ring-gray-400 cursor-pointer transition-all`}
                                title={`${day} ${row.hour}: ${value} orders`}
                              >
                                {value}
                              </div>
                            );
                          })}
                        </div>
                      );
                    })}
                  </div>
                </div>
                <div className="mt-4 flex items-center justify-between text-xs text-gray-500">
                  <div className="flex items-center gap-2">
                    <span>Low Activity</span>
                    <div className="flex gap-1">
                      <div className="w-4 h-4 bg-gray-100 rounded"></div>
                      <div className="w-4 h-4 bg-emerald-100 rounded"></div>
                      <div className="w-4 h-4 bg-emerald-200 rounded"></div>
                      <div className="w-4 h-4 bg-emerald-300 rounded"></div>
                      <div className="w-4 h-4 bg-emerald-400 rounded"></div>
                      <div className="w-4 h-4 bg-emerald-500 rounded"></div>
                      <div className="w-4 h-4 bg-emerald-600 rounded"></div>
                    </div>
                    <span>High Activity</span>
                  </div>
                  <div>
                    <span className="font-medium">Peak:</span> Sat 8pm (92 orders)
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Peak Days Comparison */}
          <div>
            <h2 className="text-2xl mb-4">Peak Days Analysis</h2>
            <Card>
              <CardHeader>
                <CardTitle>Weekly Performance Breakdown</CardTitle>
                <CardDescription>Compare daily performance to identify trends</CardDescription>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={peakDaysData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                    <XAxis dataKey="day" stroke="#666" />
                    <YAxis yAxisId="left" stroke="#666" />
                    <YAxis yAxisId="right" orientation="right" stroke="#666" />
                    <Tooltip />
                    <Legend />
                    <Bar yAxisId="left" dataKey="orders" fill="#3b82f6" name="Total Orders" />
                    <Bar yAxisId="right" dataKey="avgOrders" fill="#10b981" name="Avg Orders/Hour" />
                  </BarChart>
                </ResponsiveContainer>
                <div className="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="p-3 bg-blue-50 rounded-lg">
                    <div className="text-xs text-blue-600 mb-1">Busiest Day</div>
                    <div className="text-lg font-medium">Saturday</div>
                    <div className="text-sm text-gray-600">510 orders</div>
                  </div>
                  <div className="p-3 bg-emerald-50 rounded-lg">
                    <div className="text-xs text-emerald-600 mb-1">Best Revenue</div>
                    <div className="text-lg font-medium">Saturday</div>
                    <div className="text-sm text-gray-600">€4,500</div>
                  </div>
                  <div className="p-3 bg-amber-50 rounded-lg">
                    <div className="text-xs text-amber-600 mb-1">Slowest Day</div>
                    <div className="text-lg font-medium">Monday</div>
                    <div className="text-sm text-gray-600">207 orders</div>
                  </div>
                  <div className="p-3 bg-purple-50 rounded-lg">
                    <div className="text-xs text-purple-600 mb-1">Weekend vs Weekday</div>
                    <div className="text-lg font-medium">+78%</div>
                    <div className="text-sm text-gray-600">more orders</div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Orders by Category */}
          <div>
            <h2 className="text-2xl mb-4">Orders by Category</h2>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <Card>
                <CardHeader>
                  <CardTitle>Category Distribution</CardTitle>
                </CardHeader>
                <CardContent>
                  <ResponsiveContainer width="100%" height={300}>
                    <PieChart>
                      <Pie
                        data={ordersByCategoryData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={({ category, percentage }) => `${category} (${percentage}%)`}
                        outerRadius={100}
                        fill="#8884d8"
                        dataKey="percentage"
                      >
                        {ordersByCategoryData.map((entry, index) => (
                          <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                        ))}
                      </Pie>
                      <Tooltip />
                    </PieChart>
                  </ResponsiveContainer>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Category Performance</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {ordersByCategoryData.map((category, index) => (
                    <div key={category.category} className="border-b last:border-b-0 pb-3 last:pb-0">
                      <div className="flex items-center justify-between mb-2">
                        <div className="flex items-center gap-2">
                          <div 
                            className="w-3 h-3 rounded-full" 
                            style={{ backgroundColor: COLORS[index % COLORS.length] }}
                          />
                          <span className="font-medium">{category.category}</span>
                        </div>
                        <span className="text-sm text-gray-500">{category.percentage}%</span>
                      </div>
                      <div className="flex items-center justify-between text-sm">
                        <div className="flex gap-4">
                          <span className="text-gray-600">
                            <span className="font-medium text-gray-900">{category.orders}</span> orders
                          </span>
                          <span className="text-gray-600">
                            <span className="font-medium text-gray-900">€{category.revenue}</span> revenue
                          </span>
                        </div>
                        <span className="text-gray-600">
                          €{(category.revenue / category.orders).toFixed(2)} avg
                        </span>
                      </div>
                    </div>
                  ))}
                </CardContent>
              </Card>
            </div>
          </div>

          {/* Reviews & Reputation */}
          <div>
            <h2 className="text-2xl mb-4">Reviews & Reputation</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <Card>
                <CardContent className="p-6">
                  <div className="flex items-center gap-2 mb-2">
                    <Star className="w-5 h-5 text-amber-500 fill-amber-500" />
                    <span className="text-sm text-gray-600">Average Rating</span>
                  </div>
                  <div className="text-3xl mb-1">4.6</div>
                  <div className="text-sm text-gray-500">Based on 284 reviews</div>
                </CardContent>
              </Card>
              <Card>
                <CardContent className="p-6">
                  <div className="text-sm text-gray-600 mb-2">Reviews per 100 Orders</div>
                  <div className="text-3xl mb-1">12.3</div>
                  <div className="text-sm text-gray-500">+2.1 vs last month</div>
                </CardContent>
              </Card>
              <Card>
                <CardContent className="p-6">
                  <div className="text-sm text-gray-600 mb-3">Rating Distribution</div>
                  <div className="space-y-2">
                    {[5, 4, 3, 2, 1].map(rating => {
                      const percentage = rating === 5 ? 68 : rating === 4 ? 22 : rating === 3 ? 7 : rating === 2 ? 2 : 1;
                      return (
                        <div key={rating} className="flex items-center gap-2">
                          <span className="text-sm w-4">{rating}</span>
                          <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div 
                              className="h-full bg-amber-500 rounded-full" 
                              style={{ width: `${percentage}%` }} 
                            />
                          </div>
                          <span className="text-sm text-gray-600 w-10 text-right">{percentage}%</span>
                        </div>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      )}

      {/* AI Insights Section */}
      {activeView === 'ai' && (
        <div className="space-y-6 bg-gradient-to-br from-gray-50/50 to-purple-50/30 -m-6 p-6">
          {/* Page Header */}
          <div className="flex items-center justify-between">
            <div>
              <div className="flex items-center gap-3 mb-2">
                <h2 className="text-2xl">AI Insights</h2>
                <span className="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm">
                  Phase 3 · Advanced Plan
                </span>
              </div>
              <p className="text-gray-600">Actionable recommendations based on your real restaurant data.</p>
            </div>
          </div>

          {hasAIAccess ? (
            <>
              {/* Today's AI Insights Overview */}
              <div>
                <h3 className="text-lg mb-4">Today's AI Insights</h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  {topInsights.map((insight) => {
                    const CategoryIcon = getCategoryIcon(insight.category);
                    return (
                      <Card key={insight.id} className="hover:shadow-md transition-shadow cursor-pointer" onClick={() => setExpandedInsight(insight.id)}>
                        <CardContent className="p-5">
                          <div className="flex items-start gap-3 mb-3">
                            <div className="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                              <CategoryIcon className="w-5 h-5 text-gray-700" />
                            </div>
                            <div className="flex-1 min-w-0">
                              <h4 className="font-medium mb-1 text-sm">{insight.title}</h4>
                              <p className="text-xs text-gray-600 line-clamp-2">{insight.description}</p>
                            </div>
                          </div>
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                              <span className="text-sm font-medium text-emerald-600">{insight.estimatedImpact}</span>
                            </div>
                            <span className={`px-2 py-0.5 rounded-full text-xs ${getConfidenceColor(insight.confidence)}`}>
                              {insight.confidence}
                            </span>
                          </div>
                        </CardContent>
                      </Card>
                    );
                  })}
                </div>
              </div>

              {/* Category Filters */}
              <div className="flex items-center gap-2 flex-wrap">
                <Filter className="w-4 h-4 text-gray-500" />
                <button
                  onClick={() => setInsightCategory('all')}
                  className={`px-4 py-2 rounded-lg text-sm transition-all ${
                    insightCategory === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  All Insights
                </button>
                <button
                  onClick={() => setInsightCategory('revenue')}
                  className={`px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 ${
                    insightCategory === 'revenue' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  <Target className="w-4 h-4" />
                  Revenue
                </button>
                <button
                  onClick={() => setInsightCategory('operational')}
                  className={`px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 ${
                    insightCategory === 'operational' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  <Zap className="w-4 h-4" />
                  Operational
                </button>
                <button
                  onClick={() => setInsightCategory('retention')}
                  className={`px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 ${
                    insightCategory === 'retention' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  <UserCheck className="w-4 h-4" />
                  Retention
                </button>
                <button
                  onClick={() => setInsightCategory('reputation')}
                  className={`px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 ${
                    insightCategory === 'reputation' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  <ThumbsUp className="w-4 h-4" />
                  Reputation
                </button>
              </div>

              {/* Insight Detail Cards */}
              <div className="space-y-4">
                {filteredInsights.map((insight) => {
                  const CategoryIcon = getCategoryIcon(insight.category);
                  const isExpanded = expandedInsight === insight.id;
                  
                  return (
                    <Card key={insight.id} className="overflow-hidden bg-white">
                      <CardContent className="p-0">
                        {/* Card Header - Always Visible */}
                        <button
                          onClick={() => setExpandedInsight(isExpanded ? null : insight.id)}
                          className="w-full p-6 text-left hover:bg-gray-50 transition-colors"
                        >
                          <div className="flex items-start justify-between gap-4">
                            <div className="flex items-start gap-4 flex-1">
                              <div className="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                <CategoryIcon className="w-6 h-6 text-gray-700" />
                              </div>
                              <div className="flex-1">
                                <div className="flex items-center gap-2 mb-2">
                                  <h3 className="text-lg font-medium">{insight.title}</h3>
                                  <span className={`px-2 py-0.5 rounded-full text-xs ${getConfidenceColor(insight.confidence)}`}>
                                    {insight.confidence}
                                  </span>
                                </div>
                                <p className="text-sm text-gray-600 mb-3">{insight.description}</p>
                                <div className="flex items-center gap-4">
                                  <span className="text-sm font-medium text-emerald-600">{insight.estimatedImpact}</span>
                                  <span className="text-xs text-gray-500 capitalize">{insight.category}</span>
                                </div>
                              </div>
                            </div>
                            <div className="flex-shrink-0">
                              {isExpanded ? (
                                <ChevronUp className="w-5 h-5 text-gray-400" />
                              ) : (
                                <ChevronDown className="w-5 h-5 text-gray-400" />
                              )}
                            </div>
                          </div>
                        </button>

                        {/* Expanded Content - 5-Part Structure */}
                        {isExpanded && (
                          <div className="border-t border-gray-200 bg-gray-50 p-6 space-y-6">
                            {/* 1. What's happening */}
                            <div>
                              <div className="flex items-center gap-2 mb-2">
                                <div className="w-1 h-4 bg-blue-500 rounded-full"></div>
                                <h4 className="font-medium text-sm">What's happening</h4>
                              </div>
                              <p className="text-sm text-gray-700 ml-3">{insight.whatHappening}</p>
                            </div>

                            {/* 2. Why it matters */}
                            <div>
                              <div className="flex items-center gap-2 mb-2">
                                <div className="w-1 h-4 bg-purple-500 rounded-full"></div>
                                <h4 className="font-medium text-sm">Why it matters</h4>
                              </div>
                              <p className="text-sm text-gray-700 ml-3">{insight.whyMatters}</p>
                            </div>

                            {/* 3. Estimated impact */}
                            <div>
                              <div className="flex items-center gap-2 mb-2">
                                <div className="w-1 h-4 bg-emerald-500 rounded-full"></div>
                                <h4 className="font-medium text-sm">Estimated impact</h4>
                              </div>
                              <div className="ml-3 flex items-center gap-2">
                                <TrendingUp className="w-4 h-4 text-emerald-600" />
                                <span className="text-sm font-medium text-emerald-600">{insight.estimatedImpact}</span>
                              </div>
                            </div>

                            {/* 4. Suggested action */}
                            <div>
                              <div className="flex items-center gap-2 mb-2">
                                <div className="w-1 h-4 bg-amber-500 rounded-full"></div>
                                <h4 className="font-medium text-sm">Suggested action</h4>
                              </div>
                              <div className="ml-3 p-4 bg-white border border-gray-200 rounded-lg">
                                <p className="text-sm text-gray-900 mb-3">{insight.suggestedAction}</p>
                                <Button 
                                  onClick={() => {
                                    if (onNavigate) {
                                      onNavigate('loyalty');
                                    }
                                  }}
                                  className="bg-gray-900 hover:bg-gray-800 text-white"
                                >
                                  Configure Action
                                  <ArrowRight className="w-4 h-4 ml-2" />
                                </Button>
                              </div>
                            </div>

                            {/* 5. Data used (Transparency) */}
                            <div className="border-t border-gray-200 pt-4">
                              <div className="flex items-center gap-2 text-xs text-gray-500">
                                <Info className="w-3.5 h-3.5" />
                                <span>{insight.dataSource}</span>
                              </div>
                              {insight.confidence === 'low' && (
                                <div className="mt-2 flex items-start gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                  <AlertCircle className="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                                  <div>
                                    <div className="text-xs font-medium text-amber-900 mb-1">Informational — Review before acting</div>
                                    <div className="text-xs text-amber-700">Limited data available for this insight. Consider as directional guidance.</div>
                                  </div>
                                </div>
                              )}
                            </div>
                          </div>
                        )}
                      </CardContent>
                    </Card>
                  );
                })}
              </div>

              {/* Action Panel - Side Modal - REMOVED: Now navigates to Loyalty page */}
            </>
          ) : (
            // Non-subscriber preview
            <div className="relative">
              {/* Blurred insights */}
              <div className="filter blur-sm pointer-events-none select-none">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                  {topInsights.map((insight) => (
                    <Card key={insight.id}>
                      <CardContent className="p-5">
                        <div className="h-24 bg-gray-100 rounded"></div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
                <div className="space-y-4">
                  <Card><CardContent className="p-6 h-32 bg-gray-50"></CardContent></Card>
                  <Card><CardContent className="p-6 h-32 bg-gray-50"></CardContent></Card>
                  <Card><CardContent className="p-6 h-32 bg-gray-50"></CardContent></Card>
                </div>
              </div>

              {/* Unlock CTA Overlay */}
              <div className="absolute inset-0 flex items-center justify-center">
                <Card className="max-w-lg shadow-2xl">
                  <CardContent className="p-8 text-center">
                    <div className="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
                      <Lock className="w-8 h-8 text-purple-600" />
                    </div>
                    <h3 className="text-2xl font-medium mb-2">Unlock AI Insights</h3>
                    <p className="text-gray-600 mb-6">
                      Get data-backed recommendations that help you increase revenue and reduce operational friction.
                    </p>
                    <Button className="bg-purple-600 hover:bg-purple-700 text-white">
                      Upgrade to Advanced Plan
                      <ChevronRight className="w-4 h-4 ml-2" />
                    </Button>
                  </CardContent>
                </Card>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}