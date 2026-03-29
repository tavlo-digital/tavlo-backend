import { X, Sparkles, TrendingUp, AlertTriangle, Lightbulb, CheckCircle } from 'lucide-react';
import { Button } from '../ui/button';
import { AIInsightCard } from '../ai/AIComponents';

interface AIInsightsModalProps {
  isOpen: boolean;
  onClose: () => void;
  insights: Array<{
    type: 'recommendation' | 'warning' | 'success';
    title: string;
    description: string;
    metric?: string;
    action?: {
      label: string;
      onClick: () => void;
    };
    explanation: string;
  }>;
}

export function AIInsightsModal({ isOpen, onClose, insights }: AIInsightsModalProps) {
  if (!isOpen) return null;

  return (
    <>
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="fixed inset-x-4 top-4 bottom-4 md:inset-8 md:max-w-5xl md:mx-auto bg-white rounded-2xl z-50 flex flex-col overflow-hidden shadow-2xl">
        {/* Header */}
        <div className="relative px-6 py-5 border-b bg-gradient-to-r from-purple-50 to-white">
          <button 
            onClick={onClose}
            className="absolute top-4 right-4 p-2 hover:bg-white rounded-full transition-colors"
          >
            <X className="w-6 h-6" />
          </button>
          
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
              <Sparkles className="w-6 h-6 text-purple-600" />
            </div>
            <div>
              <h2 className="text-2xl mb-1">AI Performance Insights</h2>
              <p className="text-sm text-gray-600">Data-driven recommendations to improve your business</p>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto px-6 py-6">
          {/* Stats Overview */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div className="p-4 bg-green-50 border border-green-200 rounded-xl">
              <div className="flex items-center gap-2 mb-2">
                <CheckCircle className="w-5 h-5 text-green-600" />
                <span className="text-sm text-green-800">Positive Insights</span>
              </div>
              <div className="text-2xl text-green-900">
                {insights.filter(i => i.type === 'success').length}
              </div>
            </div>
            
            <div className="p-4 bg-blue-50 border border-blue-200 rounded-xl">
              <div className="flex items-center gap-2 mb-2">
                <Lightbulb className="w-5 h-5 text-blue-600" />
                <span className="text-sm text-blue-800">Recommendations</span>
              </div>
              <div className="text-2xl text-blue-900">
                {insights.filter(i => i.type === 'recommendation').length}
              </div>
            </div>
            
            <div className="p-4 bg-orange-50 border border-orange-200 rounded-xl">
              <div className="flex items-center gap-2 mb-2">
                <AlertTriangle className="w-5 h-5 text-orange-600" />
                <span className="text-sm text-orange-800">Attention Needed</span>
              </div>
              <div className="text-2xl text-orange-900">
                {insights.filter(i => i.type === 'warning').length}
              </div>
            </div>
          </div>

          {/* All Insights */}
          <div>
            <h3 className="mb-4">All Insights</h3>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              {insights.map((insight, index) => (
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

          {/* How AI Works Section */}
          <div className="mt-8 p-5 bg-gray-50 rounded-xl border border-gray-200">
            <h4 className="mb-3 flex items-center gap-2">
              <Sparkles className="w-5 h-5 text-purple-600" />
              How Our AI Works
            </h4>
            <ul className="space-y-2 text-sm text-gray-700">
              <li className="flex gap-2">
                <span className="text-purple-600 mt-0.5">•</span>
                <span><strong>Order Pattern Analysis:</strong> We analyze your hourly order data to identify peak times and opportunities</span>
              </li>
              <li className="flex gap-2">
                <span className="text-purple-600 mt-0.5">•</span>
                <span><strong>Customer Behavior:</strong> Track repeat customer rates, average order values, and lifetime value</span>
              </li>
              <li className="flex gap-2">
                <span className="text-purple-600 mt-0.5">•</span>
                <span><strong>Menu Performance:</strong> Identify slow-moving items, popular dishes, and pricing opportunities</span>
              </li>
              <li className="flex gap-2">
                <span className="text-purple-600 mt-0.5">•</span>
                <span><strong>Revenue Optimization:</strong> Compare your performance with similar restaurants to find growth opportunities</span>
              </li>
            </ul>
            <p className="text-xs text-gray-600 mt-3 italic">
              All insights are based on your actual data and can be verified. You remain in full control of all decisions.
            </p>
          </div>
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t bg-gray-50 flex items-center justify-between">
          <p className="text-sm text-gray-600">Last updated: Just now</p>
          <Button onClick={onClose} variant="default">
            Close
          </Button>
        </div>
      </div>
    </>
  );
}
