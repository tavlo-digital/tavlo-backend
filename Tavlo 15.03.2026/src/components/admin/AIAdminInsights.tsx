import { useState, useEffect } from 'react';
import { 
  TrendingUp, 
  TrendingDown, 
  AlertTriangle, 
  CheckCircle, 
  DollarSign, 
  Activity, 
  CreditCard, 
  Sparkles,
  Info,
  Zap,
  Shield,
  Server,
  Users,
  BarChart3,
  Clock,
  AlertCircle,
  ArrowUpRight,
  Loader2,
  Heart,
  PhoneCall,
  Calendar,
  Mail
} from 'lucide-react';
import { toast } from 'sonner@2.0.3';
import {
  fetchSubscriptionRisks,
  fetchRevenueOpportunities,
  fetchSystemAlerts,
  fetchPlatformHealth,
  type SubscriptionRisk,
  type RevenueOpportunity,
  type SystemAlert,
  type PlatformHealthMetrics
} from '../../services/aiInsightsService';

type TabType = 'health' | 'subscription-risk' | 'revenue' | 'alerts';

export function AIAdminInsights() {
  const [activeTab, setActiveTab] = useState<TabType>('health');
  const [loading, setLoading] = useState(true);
  
  // State for AI insights data
  const [subscriptionRisks, setSubscriptionRisks] = useState<SubscriptionRisk[]>([]);
  const [revenueOpportunities, setRevenueOpportunities] = useState<RevenueOpportunity[]>([]);
  const [systemAlerts, setSystemAlerts] = useState<SystemAlert[]>([]);
  const [platformHealth, setPlatformHealth] = useState<PlatformHealthMetrics | null>(null);

  // Load AI insights on component mount
  useEffect(() => {
    loadAIInsights();
  }, []);

  async function loadAIInsights() {
    setLoading(true);
    try {
      // Fetch all AI insights in parallel
      const [risks, opportunities, alerts, health] = await Promise.all([
        fetchSubscriptionRisks(),
        fetchRevenueOpportunities(),
        fetchSystemAlerts(),
        fetchPlatformHealth()
      ]);

      setSubscriptionRisks(risks);
      setRevenueOpportunities(opportunities);
      setSystemAlerts(alerts);
      setPlatformHealth(health);
    } catch (error) {
      console.error('Error loading AI insights:', error);
      toast.error('Failed to load AI insights');
    } finally {
      setLoading(false);
    }
  }

  const tabs = [
    { id: 'health' as TabType, label: 'Platform Health', icon: Activity },
    { id: 'subscription-risk' as TabType, label: 'Subscription Risk', icon: AlertTriangle, count: subscriptionRisks.length },
    { id: 'revenue' as TabType, label: 'Revenue Opportunities', icon: DollarSign, count: revenueOpportunities.length },
    { id: 'alerts' as TabType, label: 'System Alerts', icon: Shield, count: systemAlerts.length },
  ];

  if (loading) {
    return (
      <div className="p-6 flex items-center justify-center min-h-screen">
        <div className="text-center">
          <Loader2 className="w-8 h-8 text-purple-600 animate-spin mx-auto mb-3" />
          <p className="text-sm text-gray-600">Loading AI insights...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6">
      {/* Header with Disclaimer */}
      <div className="mb-6">
        <div className="flex items-center gap-3 mb-2">
          <h1 className="text-2xl">AI Platform Insights</h1>
          <span className="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm font-medium flex items-center gap-1.5">
            <Sparkles className="w-3.5 h-3.5" />
            AI-Powered
          </span>
        </div>
        <p className="text-sm text-gray-500 mb-4">
          Predictive analytics for subscription health and platform performance
        </p>

        {/* AI Advisory Notice */}
        <div className="bg-purple-50 border border-purple-200 rounded-xl p-4">
          <div className="flex items-start gap-3">
            <Info className="w-5 h-5 text-purple-600 mt-0.5 shrink-0" />
            <div>
              <h3 className="font-medium text-purple-900 mb-1">AI Advisory Notice</h3>
              <p className="text-sm text-purple-700">
                <strong>AI insights are advisory and based on Tavlo platform data only.</strong>
                {' '}Tavlo does not analyze or manage restaurant operations, menus, pricing, tips, or customer behavior.
                All suggestions relate to subscription health, platform usage, and revenue protection.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Active Subscriptions</span>
            <CheckCircle className="w-4 h-4 text-green-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">347</div>
          <div className="text-xs text-green-600 flex items-center gap-1">
            <TrendingUp className="w-3 h-3" />
            +12 this month
          </div>
        </div>

        {/* NEW: Vendor Health Overview Card */}
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Vendor Health</span>
            <Heart className="w-4 h-4 text-green-500" />
          </div>
          <div className="space-y-1.5 mt-2">
            <div className="flex items-center justify-between text-xs">
              <span className="text-green-600">● Healthy</span>
              <span className="font-medium">245</span>
            </div>
            <div className="flex items-center justify-between text-xs">
              <span className="text-blue-600">● Stable</span>
              <span className="font-medium">78</span>
            </div>
            <div className="flex items-center justify-between text-xs">
              <span className="text-orange-600">● Warning</span>
              <span className="font-medium">18</span>
            </div>
            <div className="flex items-center justify-between text-xs">
              <span className="text-red-600">● High Risk</span>
              <span className="font-medium">6</span>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Churn Risk</span>
            <AlertTriangle className="w-4 h-4 text-orange-500" />
          </div>
          <div className="text-2xl font-semibold mb-1 text-orange-600">2.8%</div>
          <div className="text-xs text-gray-500">Predicted (next 30 days)</div>
        </div>

        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Payment Success Rate</span>
            <CreditCard className="w-4 h-4 text-blue-500" />
          </div>
          <div className="text-2xl font-semibold mb-1 text-blue-600">96.2%</div>
          <div className="text-xs text-gray-500">Last 30 days</div>
        </div>

        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Platform Health Score</span>
            <Activity className="w-4 h-4 text-green-500" />
          </div>
          <div className="text-2xl font-semibold mb-1 text-green-600">92/100</div>
          <div className="text-xs text-gray-500">Excellent</div>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-gray-200 mb-6 overflow-x-auto">
        {tabs.map((tab) => {
          const Icon = tab.icon;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`pb-3 px-4 border-b-2 transition-colors whitespace-nowrap flex items-center gap-2 ${
                activeTab === tab.id
                  ? 'border-purple-600 text-purple-600'
                  : 'border-transparent text-gray-600 hover:text-gray-900'
              }`}
            >
              <Icon className="w-4 h-4" />
              <span className="text-sm font-medium">{tab.label}</span>
              {tab.count !== undefined && tab.count > 0 && (
                <span className={`px-2 py-0.5 rounded-full text-xs ${
                  activeTab === tab.id 
                    ? 'bg-purple-100 text-purple-700'
                    : 'bg-gray-100 text-gray-700'
                }`}>
                  {tab.count}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* Tab Content */}
      <div>
        {activeTab === 'health' && <PlatformHealthTab health={platformHealth} />}
        {activeTab === 'subscription-risk' && <SubscriptionRiskTab risks={subscriptionRisks} />}
        {activeTab === 'revenue' && <RevenueOpportunitiesTab opportunities={revenueOpportunities} />}
        {activeTab === 'alerts' && <SystemAlertsTab alerts={systemAlerts} />}
      </div>
    </div>
  );
}

// Platform Health Tab
function PlatformHealthTab({ health }: { health: PlatformHealthMetrics | null }) {
  return (
    <div className="space-y-6">
      {/* Overall Health */}
      <div className="bg-white rounded-xl border border-gray-200 p-6">
        <h3 className="font-medium mb-4">Overall Platform Health</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <div className="text-sm text-gray-600 mb-2">System Uptime</div>
            <div className="text-3xl font-semibold text-green-600 mb-1">{health?.systemUptime || 99.97}%</div>
            <div className="text-xs text-gray-500">Last 30 days</div>
            <div className="mt-3 w-full bg-gray-100 rounded-full h-2">
              <div className="bg-green-500 h-2 rounded-full" style={{ width: `${health?.systemUptime || 99.97}%` }}></div>
            </div>
          </div>
          <div>
            <div className="text-sm text-gray-600 mb-2">API Response Time</div>
            <div className="text-3xl font-semibold text-blue-600 mb-1">{health?.apiResponseTime || 142}ms</div>
            <div className="text-xs text-gray-500">Average (p95)</div>
            <div className="mt-3 text-xs text-green-600 flex items-center gap-1">
              <TrendingDown className="w-3 h-3" />
              -23ms from last week
            </div>
          </div>
          <div>
            <div className="text-sm text-gray-600 mb-2">Critical Incidents</div>
            <div className="text-3xl font-semibold text-gray-900 mb-1">{health?.criticalIncidents || 0}</div>
            <div className="text-xs text-gray-500">Last 30 days</div>
            <div className="mt-3 text-xs text-green-600 flex items-center gap-1">
              <CheckCircle className="w-3 h-3" />
              No incidents
            </div>
          </div>
        </div>
      </div>

      {/* Subscription Metrics */}
      <div className="bg-white rounded-xl border border-gray-200 p-6">
        <h3 className="font-medium mb-4">Subscription Metrics</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm text-gray-600">Active Subscriptions</span>
              <span className="text-sm font-medium">{health?.activeSubscriptions || 347}</span>
            </div>
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Basic (€49/mo)</span>
                <span className="text-gray-700">142 vendors</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Standard (€99/mo)</span>
                <span className="text-gray-700">158 vendors</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Premium (€199/mo)</span>
                <span className="text-gray-700">47 vendors</span>
              </div>
            </div>
          </div>
          <div>
            <div className="flex items-center justify-between mb-3">
              <span className="text-sm text-gray-600">Monthly Recurring Revenue</span>
              <span className="text-sm font-medium">€{(health?.mrr || 34287).toLocaleString()}</span>
            </div>
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Trial Conversions (MTD)</span>
                <span className="text-green-600 font-medium">{health?.trialConversionRate || 68}%</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Renewal Rate</span>
                <span className="text-green-600 font-medium">{health?.renewalRate || 94.2}%</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Expansion Revenue</span>
                <span className="text-blue-600 font-medium">+€1,240</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Payment Health */}
      <div className="bg-white rounded-xl border border-gray-200 p-6">
        <h3 className="font-medium mb-4">Payment Health</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="p-4 border border-gray-200 rounded-lg">
            <div className="text-sm text-gray-600 mb-1">Successful Payments</div>
            <div className="text-2xl font-semibold text-green-600">{health?.paymentSuccessRate || 96.2}%</div>
            <div className="text-xs text-gray-500 mt-1">334 of 347 vendors</div>
          </div>
          <div className="p-4 border border-gray-200 rounded-lg">
            <div className="text-sm text-gray-600 mb-1">Overdue Payments</div>
            <div className="text-2xl font-semibold text-orange-600">8</div>
            <div className="text-xs text-gray-500 mt-1">Requires follow-up</div>
          </div>
          <div className="p-4 border border-gray-200 rounded-lg">
            <div className="text-sm text-gray-600 mb-1">Grace Period</div>
            <div className="text-2xl font-semibold text-yellow-600">5</div>
            <div className="text-xs text-gray-500 mt-1">Within 7-day window</div>
          </div>
        </div>
      </div>

      {/* NEW: Vendor Lifecycle Insights */}
      <div className="bg-white rounded-xl border border-gray-200 p-6">
        <h3 className="font-medium mb-4">Vendor Lifecycle Insights</h3>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="p-4 border border-gray-200 rounded-lg bg-gradient-to-br from-green-50 to-white">
            <div className="flex items-center gap-2 mb-2">
              <ArrowUpRight className="w-4 h-4 text-green-600" />
              <div className="text-sm text-gray-600">New Vendors</div>
            </div>
            <div className="text-2xl font-semibold text-green-600">23</div>
            <div className="text-xs text-gray-500 mt-1">Last 30 days</div>
          </div>
          <div className="p-4 border border-gray-200 rounded-lg bg-gradient-to-br from-blue-50 to-white">
            <div className="flex items-center gap-2 mb-2">
              <TrendingUp className="w-4 h-4 text-blue-600" />
              <div className="text-sm text-gray-600">Growing Vendors</div>
            </div>
            <div className="text-2xl font-semibold text-blue-600">58</div>
            <div className="text-xs text-gray-500 mt-1">Increased activity</div>
          </div>
          <div className="p-4 border border-gray-200 rounded-lg bg-gradient-to-br from-orange-50 to-white">
            <div className="flex items-center gap-2 mb-2">
              <AlertTriangle className="w-4 h-4 text-orange-600" />
              <div className="text-sm text-gray-600">At-Risk Vendors</div>
            </div>
            <div className="text-2xl font-semibold text-orange-600">24</div>
            <div className="text-xs text-gray-500 mt-1">Requires attention</div>
          </div>
          <div className="p-4 border border-gray-200 rounded-lg bg-gradient-to-br from-gray-50 to-white">
            <div className="flex items-center gap-2 mb-2">
              <TrendingDown className="w-4 h-4 text-gray-600" />
              <div className="text-sm text-gray-600">Churned Vendors</div>
            </div>
            <div className="text-2xl font-semibold text-gray-900">7</div>
            <div className="text-xs text-gray-500 mt-1">Last 30 days</div>
          </div>
        </div>
      </div>

      {/* NEW: Vendor Ecosystem Health */}
      <div className="bg-white rounded-xl border border-gray-200 p-6">
        <h3 className="font-medium mb-4">Vendor Ecosystem Health</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <div className="text-sm text-gray-600 mb-2">Average Vendor Health Score</div>
            <div className="text-3xl font-semibold text-green-600 mb-3">78/100</div>
            <div className="text-xs text-gray-500 flex items-center gap-1">
              <TrendingUp className="w-3 h-3 text-green-600" />
              +2.5 from last month
            </div>
          </div>
          <div className="md:col-span-2">
            <div className="text-sm text-gray-600 mb-3">Health Distribution</div>
            <div className="space-y-3">
              <div>
                <div className="flex items-center justify-between text-xs mb-1.5">
                  <span className="text-green-600 font-medium">Healthy (80–100)</span>
                  <span className="text-gray-900 font-medium">245 vendors (70.6%)</span>
                </div>
                <div className="w-full bg-gray-100 rounded-full h-2">
                  <div className="bg-green-500 h-2 rounded-full" style={{ width: '70.6%' }}></div>
                </div>
              </div>
              <div>
                <div className="flex items-center justify-between text-xs mb-1.5">
                  <span className="text-blue-600 font-medium">Stable (60–79)</span>
                  <span className="text-gray-900 font-medium">78 vendors (22.5%)</span>
                </div>
                <div className="w-full bg-gray-100 rounded-full h-2">
                  <div className="bg-blue-500 h-2 rounded-full" style={{ width: '22.5%' }}></div>
                </div>
              </div>
              <div>
                <div className="flex items-center justify-between text-xs mb-1.5">
                  <span className="text-orange-600 font-medium">Warning (40–59)</span>
                  <span className="text-gray-900 font-medium">18 vendors (5.2%)</span>
                </div>
                <div className="w-full bg-gray-100 rounded-full h-2">
                  <div className="bg-orange-500 h-2 rounded-full" style={{ width: '5.2%' }}></div>
                </div>
              </div>
              <div>
                <div className="flex items-center justify-between text-xs mb-1.5">
                  <span className="text-red-600 font-medium">High Risk (0–39)</span>
                  <span className="text-gray-900 font-medium">6 vendors (1.7%)</span>
                </div>
                <div className="w-full bg-gray-100 rounded-full h-2">
                  <div className="bg-red-500 h-2 rounded-full" style={{ width: '1.7%' }}></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// Subscription Risk Tab
function SubscriptionRiskTab({ risks }: { risks: SubscriptionRisk[] }) {
  return (
    <div className="space-y-4">
      <div className="flex items-start justify-between mb-4">
        <p className="text-sm text-gray-600 max-w-3xl">
          AI analyzes subscription status, payment patterns, platform engagement, and support activity to identify vendors at risk of churn.
          <strong className="block mt-1">Focus: Subscription health only — not restaurant performance.</strong>
        </p>
      </div>

      {risks.map((risk) => (
        <div key={risk.vendorId} className="bg-white rounded-xl border-2 border-gray-200 p-6">
          {/* Header */}
          <div className="flex items-start justify-between mb-4">
            <div>
              <div className="flex items-center gap-3 mb-2">
                <h3 className="text-lg font-medium">{risk.vendorName}</h3>
                <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                  risk.riskLevel === 'high' ? 'bg-red-100 text-red-700' :
                  risk.riskLevel === 'medium' ? 'bg-orange-100 text-orange-700' :
                  'bg-yellow-100 text-yellow-700'
                }`}>
                  {risk.riskLevel === 'high' ? '🔴 High Risk' :
                   risk.riskLevel === 'medium' ? '🟡 Medium Risk' :
                   '🟢 Low Risk'}
                </span>
                {/* NEW: Vendor Health Score Badge */}
                {risk.healthScore !== undefined && (
                  <span className={`px-3 py-1 rounded-full text-xs font-medium flex items-center gap-1.5 ${
                    risk.healthScore >= 80 ? 'bg-green-100 text-green-700' :
                    risk.healthScore >= 60 ? 'bg-blue-100 text-blue-700' :
                    risk.healthScore >= 40 ? 'bg-orange-100 text-orange-700' :
                    'bg-red-100 text-red-700'
                  }`}>
                    <Heart className="w-3 h-3" />
                    Health: {risk.healthScore}/100
                  </span>
                )}
              </div>
              <p className="text-sm text-gray-500">Vendor ID: {risk.vendorId}</p>
            </div>
            <div className="text-right">
              <div className="text-sm text-gray-600 mb-1">Risk Score</div>
              <div className={`text-3xl font-semibold ${
                risk.riskScore >= 70 ? 'text-red-600' :
                risk.riskScore >= 50 ? 'text-orange-600' :
                'text-yellow-600'
              }`}>
                {risk.riskScore}
              </div>
            </div>
          </div>

          {/* NEW: Vendor Health Factors */}
          {risk.healthFactors && (
            <div className="mb-4 p-4 bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-lg">
              <div className="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                <Heart className="w-4 h-4 text-gray-600" />
                Vendor Health Breakdown
              </div>
              <div className="grid grid-cols-2 md:grid-cols-5 gap-3 text-xs">
                <div>
                  <div className="text-gray-500 mb-1">Platform Engagement</div>
                  <div className={`font-medium ${
                    risk.healthFactors.platformEngagement === 'high' ? 'text-green-600' :
                    risk.healthFactors.platformEngagement === 'medium' ? 'text-blue-600' :
                    'text-red-600'
                  }`}>
                    {risk.healthFactors.platformEngagement === 'high' ? '✓ High' :
                     risk.healthFactors.platformEngagement === 'medium' ? '○ Medium' :
                     '✗ Low'}
                  </div>
                </div>
                <div>
                  <div className="text-gray-500 mb-1">Payment Reliability</div>
                  <div className={`font-medium ${
                    risk.healthFactors.paymentReliability === 'perfect' ? 'text-green-600' :
                    risk.healthFactors.paymentReliability === 'good' ? 'text-blue-600' :
                    'text-red-600'
                  }`}>
                    {risk.healthFactors.paymentReliability === 'perfect' ? '✓ Perfect' :
                     risk.healthFactors.paymentReliability === 'good' ? '○ Good' :
                     '✗ Poor'}
                  </div>
                </div>
                <div>
                  <div className="text-gray-500 mb-1">Subscription Stability</div>
                  <div className="font-medium text-gray-700">
                    {risk.healthFactors.subscriptionStability}
                  </div>
                </div>
                <div>
                  <div className="text-gray-500 mb-1">Support Activity</div>
                  <div className={`font-medium ${
                    risk.healthFactors.supportActivity === 'low' ? 'text-green-600' :
                    risk.healthFactors.supportActivity === 'medium' ? 'text-blue-600' :
                    'text-orange-600'
                  }`}>
                    {risk.healthFactors.supportActivity === 'low' ? '✓ Low' :
                     risk.healthFactors.supportActivity === 'medium' ? '○ Medium' :
                     '⚠ High'}
                  </div>
                </div>
                <div>
                  <div className="text-gray-500 mb-1">Growth Trend</div>
                  <div className={`font-medium ${
                    risk.healthFactors.growthTrend === 'increasing' ? 'text-green-600' :
                    risk.healthFactors.growthTrend === 'stable' ? 'text-blue-600' :
                    'text-red-600'
                  }`}>
                    {risk.healthFactors.growthTrend === 'increasing' ? '↗ Increasing' :
                     risk.healthFactors.growthTrend === 'stable' ? '→ Stable' :
                     '↘ Declining'}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Risk Factors */}
          <div className="mb-4">
            <div className="text-sm font-medium text-gray-700 mb-3">Subscription Risk Indicators:</div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {risk.factors.map((factor, index) => (
                <div key={index} className={`p-3 rounded-lg border ${
                  factor.severity === 'high' ? 'bg-red-50 border-red-200' :
                  factor.severity === 'medium' ? 'bg-orange-50 border-orange-200' :
                  'bg-yellow-50 border-yellow-200'
                }`}>
                  <div className="flex items-start gap-2">
                    <AlertCircle className={`w-4 h-4 mt-0.5 shrink-0 ${
                      factor.severity === 'high' ? 'text-red-600' :
                      factor.severity === 'medium' ? 'text-orange-600' :
                      'text-yellow-600'
                    }`} />
                    <div className="flex-1">
                      <div className="text-sm font-medium text-gray-900">{factor.label}</div>
                      <div className="text-xs text-gray-600 mt-0.5">{factor.metric}</div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* AI Explanation */}
          <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg mb-4">
            <div className="flex items-start gap-3">
              <Sparkles className="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
              <div className="flex-1">
                <div className="text-sm font-medium text-blue-900 mb-2">AI Analysis</div>
                <div className="text-sm text-blue-800 mb-3">{risk.suggestedAction}</div>
                
                <div className="pt-3 border-t border-blue-200 space-y-2">
                  <div className="flex items-start gap-2 text-xs">
                    <Info className="w-3.5 h-3.5 text-blue-600 mt-0.5 shrink-0" />
                    <div>
                      <strong>Data Sources:</strong> {risk.dataSource}
                    </div>
                  </div>
                  <div className="flex items-center gap-2 text-xs">
                    <BarChart3 className="w-3.5 h-3.5 text-blue-600 shrink-0" />
                    <div>
                      <strong>Confidence:</strong> {risk.confidence}%
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* NEW: AI Retention Recommendation */}
          {risk.retentionRecommendation && (
            <div className="p-4 bg-purple-50 border border-purple-200 rounded-lg mb-4">
              <div className="flex items-start gap-3">
                <Zap className="w-5 h-5 text-purple-600 shrink-0 mt-0.5" />
                <div className="flex-1">
                  <div className="text-sm font-medium text-purple-900 mb-2 flex items-center gap-2">
                    AI Retention Recommendation
                    <span className="px-2 py-0.5 bg-purple-200 text-purple-700 rounded-full text-xs">
                      {risk.retentionRecommendation.confidence}% confidence
                    </span>
                  </div>
                  <p className="text-sm text-purple-800 mb-3">{risk.retentionRecommendation.action}</p>
                  
                  <div className="flex flex-wrap gap-2">
                    {risk.retentionRecommendation.suggestedActions.map((action, idx) => (
                      <button
                        key={idx}
                        onClick={() => toast.success(`Action: ${action}`)}
                        className="px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-xs"
                      >
                        {action}
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex gap-2">
            <button
              onClick={() => toast.success('Contact vendor action initiated')}
              className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm"
            >
              Contact Vendor
            </button>
            <button 
              onClick={() => toast.info('Opening vendor subscription details...')}
              className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
            >
              View Subscription
            </button>
            <button 
              onClick={() => toast.info('Creating support task...')}
              className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
            >
              Create Task
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}

// Revenue Opportunities Tab
function RevenueOpportunitiesTab({ opportunities }: { opportunities: RevenueOpportunity[] }) {
  const totalMRR = opportunities.reduce((sum, opp) => sum + opp.estimatedMRR, 0);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between mb-4">
        <p className="text-sm text-gray-600 max-w-2xl">
          AI identifies commercial opportunities based on subscription usage patterns and platform engagement.
          <strong className="block mt-1">All suggestions are Tavlo subscription-related only.</strong>
        </p>
        <div className="text-right">
          <div className="text-sm text-gray-500">Total Opportunity</div>
          <div className="text-2xl font-semibold text-green-600">€{totalMRR.toLocaleString()}/mo</div>
        </div>
      </div>

      {opportunities.map((opp) => (
        <div key={opp.vendorId} className="bg-white rounded-xl border-2 border-gray-200 p-6">
          {/* Header */}
          <div className="flex items-start justify-between mb-4">
            <div>
              <div className="flex items-center gap-3 mb-2">
                <h3 className="text-lg font-medium">{opp.vendorName}</h3>
                <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                  opp.type === 'upgrade' ? 'bg-blue-100 text-blue-700' :
                  opp.type === 'retention' ? 'bg-green-100 text-green-700' :
                  'bg-purple-100 text-purple-700'
                }`}>
                  {opp.type === 'upgrade' ? '📈 Upgrade Opportunity' :
                   opp.type === 'retention' ? '🔒 Retention Action' :
                   '🚀 Enterprise Eligibility'}
                </span>
              </div>
              <p className="text-sm text-gray-600">{opp.currentPlan} → {opp.suggestedPlan}</p>
            </div>
            <div className="text-right">
              <div className="text-sm text-gray-600 mb-1">Additional MRR</div>
              <div className="text-3xl font-semibold text-green-600">
                +€{opp.estimatedMRR}
              </div>
              {/* NEW: Projected Impact */}
              {opp.projectedImpact && (
                <div className="text-xs text-gray-500 mt-1">
                  Projected Impact: €{opp.projectedImpact}
                </div>
              )}
            </div>
          </div>

          {/* NEW: Growth Signals */}
          {opp.growthSignals && opp.growthSignals.length > 0 && (
            <div className="mb-4 p-3 bg-gradient-to-br from-blue-50 to-white border border-blue-200 rounded-lg">
              <div className="text-xs font-medium text-blue-900 mb-2 flex items-center gap-2">
                <TrendingUp className="w-3.5 h-3.5" />
                Growth Signals
              </div>
              <div className="flex flex-wrap gap-2">
                {opp.growthSignals.map((signal, idx) => (
                  <span key={idx} className="px-2 py-1 bg-blue-100 text-blue-700 rounded-md text-xs font-medium">
                    {signal}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Why This Suggestion */}
          <div className="p-4 bg-green-50 border border-green-200 rounded-lg mb-4">
            <div className="flex items-start gap-3">
              <Sparkles className="w-5 h-5 text-green-600 shrink-0 mt-0.5" />
              <div className="flex-1">
                <div className="text-sm font-medium text-green-900 mb-2">Why This Was Flagged</div>
                <p className="text-sm text-green-800 mb-3">{opp.reason}</p>
                
                <div className="pt-3 border-t border-green-200">
                  <div className="text-xs font-medium text-green-900 mb-2">Data Points:</div>
                  <ul className="space-y-1.5">
                    {opp.dataPoints.map((point, index) => (
                      <li key={index} className="flex items-start gap-2 text-xs text-green-800">
                        <CheckCircle className="w-3.5 h-3.5 text-green-600 shrink-0 mt-0.5" />
                        {point}
                      </li>
                    ))}
                  </ul>
                </div>

                <div className="flex items-center gap-2 text-xs text-green-800 mt-3">
                  <BarChart3 className="w-3.5 h-3.5" />
                  <strong>Confidence:</strong> {opp.confidence}%
                </div>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="flex gap-2">
            <button
              onClick={() => toast.success(`${opp.type} offer prepared`)}
              className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
            >
              {opp.type === 'upgrade' ? 'Send Upgrade Offer' :
               opp.type === 'retention' ? 'Send Retention Offer' :
               'Contact for Enterprise'}
            </button>
            <button 
              onClick={() => toast.info('Opening subscription analytics...')}
              className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
            >
              View Analytics
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}

// System Alerts Tab
function SystemAlertsTab({ alerts }: { alerts: SystemAlert[] }) {
  return (
    <div className="space-y-4">
      <p className="text-sm text-gray-600 mb-4">
        Platform-level alerts detected by AI pattern recognition. Focus: technical infrastructure, billing anomalies, and compliance.
      </p>

      {alerts.map((alert) => (
        <div key={alert.id} className={`bg-white rounded-xl border-2 p-6 ${
          alert.severity === 'high' ? 'border-red-200' :
          alert.severity === 'medium' ? 'border-orange-200' :
          'border-yellow-200'
        }`}>
          {/* Header */}
          <div className="flex items-start justify-between mb-4">
            <div className="flex items-start gap-3 flex-1">
              <div className={`p-2 rounded-lg ${
                alert.type === 'billing' ? 'bg-blue-100' :
                alert.type === 'security' ? 'bg-red-100' :
                alert.type === 'technical' ? 'bg-purple-100' :
                'bg-green-100'
              }`}>
                {alert.type === 'billing' && <CreditCard className="w-5 h-5 text-blue-600" />}
                {alert.type === 'security' && <Shield className="w-5 h-5 text-red-600" />}
                {alert.type === 'technical' && <Server className="w-5 h-5 text-purple-600" />}
                {alert.type === 'compliance' && <CheckCircle className="w-5 h-5 text-green-600" />}
              </div>
              <div className="flex-1">
                <div className="flex items-center gap-2 mb-1">
                  <h3 className="font-medium">{alert.title}</h3>
                  <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                    alert.severity === 'high' ? 'bg-red-100 text-red-700' :
                    alert.severity === 'medium' ? 'bg-orange-100 text-orange-700' :
                    'bg-yellow-100 text-yellow-700'
                  }`}>
                    {alert.severity === 'high' ? 'High' :
                     alert.severity === 'medium' ? 'Medium' :
                     'Low'} Priority
                  </span>
                </div>
                <p className="text-sm text-gray-600 mb-3">{alert.description}</p>
                
                <div className="flex items-center gap-4 text-xs text-gray-500">
                  <div className="flex items-center gap-1.5">
                    <Users className="w-3.5 h-3.5" />
                    Affected: {alert.affectedCount > 0 ? `${alert.affectedCount} vendors` : 'None'}
                  </div>
                  <div className="flex items-center gap-1.5">
                    <Clock className="w-3.5 h-3.5" />
                    Detected: {alert.detectedAt}
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* AI Context */}
          <div className="p-3 bg-gray-50 border border-gray-200 rounded-lg mb-4">
            <div className="flex items-start gap-2">
              <Info className="w-4 h-4 text-gray-600 mt-0.5 shrink-0" />
              <div className="text-xs text-gray-700">
                <strong>Data Source:</strong> {alert.dataSource}
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="flex gap-2">
            <button
              onClick={() => toast.info('Opening investigation tools...')}
              className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm"
            >
              Investigate
            </button>
            <button 
              onClick={() => toast.success('Alert marked as reviewed')}
              className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
            >
              Mark as Reviewed
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}