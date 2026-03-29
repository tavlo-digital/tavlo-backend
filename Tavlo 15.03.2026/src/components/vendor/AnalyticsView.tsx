import { useState, useEffect } from 'react';
import { TrendingUp, Users, Star, DollarSign, Sparkles } from "lucide-react";
import { Card } from "../ui/card";
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from "recharts";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "../ui/tabs";
import { api } from '../../utils/api';
import { AIInsightCard } from '../ai/AIComponents';
import { Button } from '../ui/button';
import { analyzeOrderPatterns, analyzeCustomerRetention } from '../../utils/aiHelpers';
import { AIInsightsModal } from './AIInsightsModal';

const dailyData = [
  { hour: "9AM", orders: 5, revenue: 125 },
  { hour: "10AM", orders: 8, revenue: 200 },
  { hour: "11AM", orders: 12, revenue: 300 },
  { hour: "12PM", orders: 25, revenue: 625 },
  { hour: "1PM", orders: 32, revenue: 800 },
  { hour: "2PM", orders: 18, revenue: 450 },
  { hour: "3PM", orders: 10, revenue: 250 },
  { hour: "4PM", orders: 6, revenue: 150 },
  { hour: "5PM", orders: 15, revenue: 375 },
  { hour: "6PM", orders: 28, revenue: 700 },
  { hour: "7PM", orders: 38, revenue: 950 },
  { hour: "8PM", orders: 35, revenue: 875 },
  { hour: "9PM", orders: 22, revenue: 550 },
];

const categoryData = [
  { name: "Main Courses", value: 45, color: "#1A73E8" },
  { name: "Appetizers", value: 25, color: "#34A853" },
  { name: "Desserts", value: 15, color: "#FBBC04" },
  { name: "Drinks", value: 15, color: "#EA4335" },
];

interface AnalyticsViewProps {
  vendorId: string;
}

export function AnalyticsView({ vendorId }: AnalyticsViewProps) {
  const [topCustomers, setTopCustomers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [showInsightsModal, setShowInsightsModal] = useState(false);

  useEffect(() => {
    loadTopCustomers();
  }, []);

  const loadTopCustomers = async () => {
    try {
      const data = await api.getTopCustomers(vendorId);
      setTopCustomers(data);
    } catch (error) {
      console.error('Error loading top customers:', error);
    } finally {
      setLoading(false);
    }
  };

  // Generate AI insights from real data
  const orderInsights = analyzeOrderPatterns(dailyData);
  const customerInsights = analyzeCustomerRetention(topCustomers);
  const allInsights = [...orderInsights, ...customerInsights];

  return (
    <div className="p-4 md:p-6">
      {/* Header */}
      <div className="mb-6">
        <h2>Customer Analytics</h2>
        <p className="text-neutral-600 mt-1">Insights about your customers and sales</p>
      </div>

      {/* AI Performance Insights */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-purple-600" />
            <h3>AI Performance Insights</h3>
          </div>
          <Button variant="outline" size="sm" className="text-purple-600 border-purple-600 hover:bg-purple-50" onClick={() => setShowInsightsModal(true)}>
            View All Insights
          </Button>
        </div>
        
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {allInsights.map((insight, index) => (
            <AIInsightCard
              key={index}
              type={insight.type}
              title={insight.title}
              description={insight.description}
              metric={insight.metric}
              action={insight.action}
              explanation={insight.explanation}
            />
          ))}
        </div>
      </div>

      {/* Time Period Tabs */}
      <Tabs defaultValue="daily" className="w-full mb-6">
        <TabsList>
          <TabsTrigger value="daily">Daily</TabsTrigger>
          <TabsTrigger value="weekly">Weekly</TabsTrigger>
          <TabsTrigger value="monthly">Monthly</TabsTrigger>
        </TabsList>

        <TabsContent value="daily" className="space-y-6 mt-6">
          {/* Key Metrics */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card className="p-4 md:p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-neutral-600">Total Customers</p>
                  <h3 className="mt-2">287</h3>
                  <div className="flex items-center gap-1 mt-2">
                    <TrendingUp className="h-4 w-4 text-[--color-success]" />
                    <span className="text-sm text-[--color-success]">+15%</span>
                  </div>
                </div>
                <div className="h-12 w-12 bg-[--color-primary-light] rounded-lg flex items-center justify-center">
                  <Users className="h-6 w-6 text-[--color-primary]" />
                </div>
              </div>
            </Card>

            <Card className="p-4 md:p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-neutral-600">Repeat Customers</p>
                  <h3 className="mt-2">64%</h3>
                  <div className="flex items-center gap-1 mt-2">
                    <TrendingUp className="h-4 w-4 text-[--color-success]" />
                    <span className="text-sm text-[--color-success]">+8%</span>
                  </div>
                </div>
                <div className="h-12 w-12 bg-[--color-success-light] rounded-lg flex items-center justify-center">
                  <Users className="h-6 w-6 text-[--color-success]" />
                </div>
              </div>
            </Card>

            <Card className="p-4 md:p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-neutral-600">Avg. Rating</p>
                  <h3 className="mt-2">4.8</h3>
                  <div className="flex items-center gap-1 mt-2">
                    <Star className="h-4 w-4 text-[--color-warning] fill-current" />
                    <span className="text-sm text-neutral-600">156 reviews</span>
                  </div>
                </div>
                <div className="h-12 w-12 bg-[--color-warning-light] rounded-lg flex items-center justify-center">
                  <Star className="h-6 w-6 text-[--color-warning]" />
                </div>
              </div>
            </Card>

            <Card className="p-4 md:p-6">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-neutral-600">Lifetime Value</p>
                  <h3 className="mt-2">€142</h3>
                  <div className="flex items-center gap-1 mt-2">
                    <TrendingUp className="h-4 w-4 text-[--color-success]" />
                    <span className="text-sm text-[--color-success]">+12%</span>
                  </div>
                </div>
                <div className="h-12 w-12 bg-[--color-success-light] rounded-lg flex items-center justify-center">
                  <DollarSign className="h-6 w-6 text-[--color-success]" />
                </div>
              </div>
            </Card>
          </div>

          {/* Charts */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Peak Hours */}
            <Card className="p-4 md:p-6 lg:col-span-2">
              <h3 className="mb-4">Peak Hours Heatmap</h3>
              <div className="w-full" style={{ height: '256px' }}>
                <ResponsiveContainer width="100%" height={256}>
                  <BarChart data={dailyData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#E5E5E5" />
                    <XAxis 
                      dataKey="hour" 
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
                    <Bar dataKey="orders" fill="#1A73E8" radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Card>

            {/* Category Distribution */}
            <Card className="p-4 md:p-6">
              <h3 className="mb-4">Order by Category</h3>
              <div className="w-full" style={{ height: '192px' }}>
                <ResponsiveContainer width="100%" height={192}>
                  <PieChart>
                    <Pie
                      data={categoryData}
                      cx="50%"
                      cy="50%"
                      innerRadius={50}
                      outerRadius={80}
                      paddingAngle={2}
                      dataKey="value"
                    >
                      {categoryData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={entry.color} />
                      ))}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
              </div>
              <div className="space-y-2 mt-4">
                {categoryData.map((item) => (
                  <div key={item.name} className="flex items-center justify-between text-sm">
                    <div className="flex items-center gap-2">
                      <div className="h-3 w-3 rounded-full" style={{ backgroundColor: item.color }}></div>
                      <span className="text-neutral-700">{item.name}</span>
                    </div>
                    <span className="text-neutral-900">{item.value}%</span>
                  </div>
                ))}
              </div>
            </Card>
          </div>

          {/* Top Customers */}
          <Card className="p-4 md:p-6">
            <h3 className="mb-4">Top Customers</h3>
            <div className="space-y-4">
              {loading ? (
                <div className="text-center py-8 text-gray-500">Loading...</div>
              ) : topCustomers.length === 0 ? (
                <div className="text-center py-8 text-gray-500">No customer data yet</div>
              ) : (
                topCustomers.slice(0, 4).map((customer, idx) => (
                  <div key={customer.customerId} className="flex items-center justify-between pb-4 border-b border-neutral-200 last:border-0 last:pb-0">
                    <div className="flex items-center gap-3">
                      <div className="h-10 w-10 bg-[--color-primary-light] rounded-full flex items-center justify-center">
                        <span className="text-[--color-primary]">{customer.name.charAt(0)}</span>
                      </div>
                      <div>
                        <p className="text-sm text-neutral-900">{customer.name}</p>
                        <p className="text-xs text-neutral-600">{customer.ordersCount} orders</p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="text-sm text-neutral-900">€{customer.totalSpent.toFixed(2)}</p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="weekly">
          <Card className="p-12 text-center">
            <p className="text-neutral-600">Weekly analytics view</p>
          </Card>
        </TabsContent>

        <TabsContent value="monthly">
          <Card className="p-12 text-center">
            <p className="text-neutral-600">Monthly analytics view</p>
          </Card>
        </TabsContent>
      </Tabs>

      {/* AI Insights Modal */}
      <AIInsightsModal
        isOpen={showInsightsModal}
        onClose={() => setShowInsightsModal(false)}
        insights={allInsights}
      />
    </div>
  );
}