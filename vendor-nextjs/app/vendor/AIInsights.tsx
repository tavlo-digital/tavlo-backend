import { useState } from 'react';
import { TrendingUp, TrendingDown, Eye, ShoppingCart, DollarSign, Star, AlertTriangle, CheckCircle, Sparkles } from 'lucide-react';
import { AIInsightCard, AITooltip, AISuggestionChip } from '@/components/ai/AIComponents';
import { toast } from 'sonner';

interface DishPerformance {
  id: string;
  name: string;
  views: number;
  orders: number;
  revenue: number;
  conversionRate: number;
  avgRating: number;
  photoQuality: number;
  aiInsights: {
    type: 'underperforming' | 'highpotential' | 'bestseller' | 'priceopportunity';
    message: string;
    action: string;
  }[];
}

export function AIInsights() {
  const [timeframe, setTimeframe] = useState('30days');
  
  const dishesData: DishPerformance[] = [
    {
      id: '1',
      name: 'Truffle Pasta',
      views: 847,
      orders: 102,
      revenue: 1683,
      conversionRate: 12,
      avgRating: 4.8,
      photoQuality: 85,
      aiInsights: [
        {
          type: 'priceopportunity',
          message: 'High satisfaction score (4.8★) suggests you could increase price by €1-2',
          action: 'Consider raising price from €16.50 to €17.90'
        }
      ]
    },
    {
      id: '2',
      name: 'Caesar Salad',
      views: 1243,
      orders: 78,
      revenue: 858,
      conversionRate: 6.3,
      avgRating: 3.9,
      photoQuality: 45,
      aiInsights: [
        {
          type: 'underperforming',
          message: 'Frequently viewed but rarely ordered. Photo quality is below average.',
          action: 'Upload a professional photo to improve conversion'
        },
        {
          type: 'highpotential',
          message: 'Moving this item higher in menu could increase orders by 15-20%',
          action: 'Reorder menu categories'
        }
      ]
    },
    {
      id: '3',
      name: 'Margherita Pizza',
      views: 2103,
      orders: 487,
      revenue: 6282,
      conversionRate: 23,
      avgRating: 4.9,
      photoQuality: 92,
      aiInsights: [
        {
          type: 'bestseller',
          message: 'Top performer! Consider featuring this dish prominently',
          action: 'Add "Most Popular" badge'
        }
      ]
    },
    {
      id: '4',
      name: 'Seafood Risotto',
      views: 456,
      orders: 34,
      revenue: 748,
      conversionRate: 7.5,
      avgRating: 4.2,
      photoQuality: 68,
      aiInsights: [
        {
          type: 'underperforming',
          message: 'Price ($22) may be too high compared to similar items in your area',
          action: 'Lower price to €19.90 or add value (larger portion)'
        }
      ]
    }
  ];

  const overallInsights = [
    {
      type: 'recommendation' as const,
      title: 'Menu Positioning Opportunity',
      description: 'Your Caesar Salad gets 1,243 views but only 6.3% conversion. Moving it from position #12 to #4 could increase orders by 18%.',
      metric: 'Potential additional revenue: €156/month',
      action: {
        label: 'Reorder menu now',
        onClick: () => toast.success('Menu reordering tools coming soon')
      },
      explanation: 'Analysis based on view patterns, scroll depth data, and comparison with 127 similar restaurants'
    },
    {
      type: 'warning' as const,
      title: 'Photo Quality Alert',
      description: '3 of your items have below-average photos. Dishes with professional photos convert 2.4x better.',
      metric: 'Affected items: Caesar Salad, Seafood Risotto, Bruschetta',
      action: {
        label: 'View photo tips',
        onClick: () => toast.info('Opening photo guidelines...')
      },
      explanation: 'Based on image quality analysis and performance comparison with similar items'
    },
    {
      type: 'success' as const,
      title: 'Strong Performance This Week',
      description: 'Your overall conversion rate (14.2%) is 23% higher than similar restaurants. Great job!',
      metric: 'Average conversion in your category: 11.5%',
      explanation: 'Compared with 84 Italian restaurants in Vienna'
    }
  ];

  const applyAction = (dishName: string, action: string) => {
    toast.success(`Applied: ${action}`);
  };

  return (
    <div className="p-6">
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-3 mb-1">
              <h1 className="text-2xl">AI Insights</h1>
              <span className="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm font-medium flex items-center gap-1.5">
                <Sparkles className="w-3.5 h-3.5" />
                AI-Powered
              </span>
            </div>
            <p className="text-sm text-gray-500">Data-driven recommendations to improve your menu performance</p>
          </div>
          <select
            value={timeframe}
            onChange={(e) => setTimeframe(e.target.value)}
            className="px-4 py-2 border border-gray-200 rounded-lg"
          >
            <option value="7days">Last 7 days</option>
            <option value="30days">Last 30 days</option>
            <option value="90days">Last 90 days</option>
          </select>
        </div>
      </div>

      {/* Overall Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Total Views</span>
            <Eye className="w-4 h-4 text-blue-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">4,649</div>
          <div className="flex items-center gap-1 text-xs text-green-600">
            <TrendingUp className="w-3 h-3" />
            +12.4% vs last period
          </div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Total Orders</span>
            <ShoppingCart className="w-4 h-4 text-green-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">701</div>
          <div className="flex items-center gap-1 text-xs text-green-600">
            <TrendingUp className="w-3 h-3" />
            +8.7%
          </div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Conversion Rate</span>
            <TrendingUp className="w-4 h-4 text-purple-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">14.2%</div>
          <div className="flex items-center gap-1 text-xs text-green-600">
            <TrendingUp className="w-3 h-3" />
            +3.1%
          </div>
        </div>
        <div className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">Revenue</span>
            <DollarSign className="w-4 h-4 text-green-500" />
          </div>
          <div className="text-2xl font-semibold mb-1">€9,571</div>
          <div className="flex items-center gap-1 text-xs text-green-600">
            <TrendingUp className="w-3 h-3" />
            +15.2%
          </div>
        </div>
      </div>

      {/* Overall Insights */}
      <div className="mb-6 space-y-3">
        {overallInsights.map((insight, index) => (
          <AIInsightCard key={index} {...insight} />
        ))}
      </div>

      {/* Dish Performance Table */}
      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-medium">Dish Performance Analysis</h2>
            <div className="flex items-center gap-2 text-sm text-gray-600">
              <Sparkles className="w-4 h-4 text-purple-600" />
              <span>Sorted by AI priority</span>
              <AITooltip
                title="How we prioritize"
                explanation="Dishes with actionable insights and highest potential impact are shown first"
              />
            </div>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Dish Name
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Views
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Orders
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Conversion
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Revenue
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Rating
                </th>
                <th className="px-6 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">
                  Photo
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {dishesData.map((dish) => (
                <tr key={dish.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4">
                    <div className="font-medium">{dish.name}</div>
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-700">
                    {dish.views.toLocaleString()}
                  </td>
                  <td className="px-6 py-4 text-sm text-gray-700">
                    {dish.orders}
                  </td>
                  <td className="px-6 py-4">
                    <div className={`text-sm font-medium ${
                      dish.conversionRate >= 15 ? 'text-green-600' :
                      dish.conversionRate >= 10 ? 'text-blue-600' :
                      'text-orange-600'
                    }`}>
                      {dish.conversionRate}%
                    </div>
                  </td>
                  <td className="px-6 py-4 text-sm font-medium">
                    €{dish.revenue.toLocaleString()}
                  </td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-1">
                      <Star className="w-4 h-4 text-yellow-400 fill-yellow-400" />
                      <span className="text-sm font-medium">{dish.avgRating}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <div className={`text-xs font-medium ${
                      dish.photoQuality >= 80 ? 'text-green-600' :
                      dish.photoQuality >= 60 ? 'text-orange-600' :
                      'text-red-600'
                    }`}>
                      {dish.photoQuality >= 80 ? 'Good' :
                       dish.photoQuality >= 60 ? 'Fair' :
                       'Poor'}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Detailed Insights per Dish */}
      <div className="mt-6 space-y-4">
        <h2 className="text-lg font-medium">AI Recommendations by Dish</h2>
        {dishesData.map((dish) => (
          <div key={dish.id} className="bg-white rounded-xl border border-gray-200 p-5">
            <div className="flex items-start justify-between mb-4">
              <div>
                <h3 className="font-medium text-lg mb-1">{dish.name}</h3>
                <div className="flex items-center gap-4 text-sm text-gray-600">
                  <span>{dish.views} views</span>
                  <span>•</span>
                  <span>{dish.orders} orders</span>
                  <span>•</span>
                  <span>{dish.conversionRate}% conversion</span>
                </div>
              </div>
              {dish.aiInsights.length > 0 && (
                <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                  dish.aiInsights[0].type === 'bestseller' ? 'bg-green-100 text-green-700' :
                  dish.aiInsights[0].type === 'priceopportunity' ? 'bg-purple-100 text-purple-700' :
                  dish.aiInsights[0].type === 'highpotential' ? 'bg-blue-100 text-blue-700' :
                  'bg-orange-100 text-orange-700'
                }`}>
                  {dish.aiInsights[0].type === 'bestseller' ? '🏆 Bestseller' :
                   dish.aiInsights[0].type === 'priceopportunity' ? '💰 Price Opportunity' :
                   dish.aiInsights[0].type === 'highpotential' ? '📈 High Potential' :
                   '⚠️ Needs Attention'}
                </span>
              )}
            </div>

            <div className="space-y-3">
              {dish.aiInsights.map((insight, index) => (
                <div key={index} className="p-4 bg-gray-50 rounded-lg">
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <p className="text-sm text-gray-700 mb-2">{insight.message}</p>
                      <p className="text-sm font-medium text-purple-700">{insight.action}</p>
                    </div>
                    <button
                      onClick={() => applyAction(dish.name, insight.action)}
                      className="ml-4 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm whitespace-nowrap"
                    >
                      Apply
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
