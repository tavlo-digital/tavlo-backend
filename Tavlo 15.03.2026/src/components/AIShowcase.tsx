import { useState } from 'react';
import { Sparkles, Users, Store, Shield, ChevronRight } from 'lucide-react';
import { AIMenuEditor } from './vendor/AIMenuEditor';
import { AIInsights } from './vendor/AIInsights';
import { AIMenuDiscovery } from './customer/AIMenuDiscovery';
import { AIReviewSummary } from './customer/AIReviewSummary';
import { AIAdminInsights } from './admin/AIAdminInsights';

type View = 'home' | 'vendor-menu' | 'vendor-insights' | 'customer-discovery' | 'customer-reviews' | 'admin-insights';

export function AIShowcase() {
  const [currentView, setCurrentView] = useState<View>('home');

  if (currentView === 'vendor-menu') {
    return (
      <div>
        <button
          onClick={() => setCurrentView('home')}
          className="mb-4 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
        >
          ← Back to AI Showcase
        </button>
        <AIMenuEditor />
      </div>
    );
  }

  if (currentView === 'vendor-insights') {
    return (
      <div>
        <button
          onClick={() => setCurrentView('home')}
          className="mb-4 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
        >
          ← Back to AI Showcase
        </button>
        <AIInsights />
      </div>
    );
  }

  if (currentView === 'customer-discovery') {
    return (
      <div>
        <button
          onClick={() => setCurrentView('home')}
          className="mb-4 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
        >
          ← Back to AI Showcase
        </button>
        <AIMenuDiscovery />
      </div>
    );
  }

  if (currentView === 'customer-reviews') {
    return (
      <div>
        <button
          onClick={() => setCurrentView('home')}
          className="mb-4 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
        >
          ← Back to AI Showcase
        </button>
        <div className="max-w-4xl mx-auto p-6">
          <AIReviewSummary itemName="Margherita Pizza" itemType="dish" />
        </div>
      </div>
    );
  }

  if (currentView === 'admin-insights') {
    return (
      <div>
        <button
          onClick={() => setCurrentView('home')}
          className="mb-4 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
        >
          ← Back to AI Showcase
        </button>
        <AIAdminInsights />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-purple-50 to-blue-50 p-6">
      <div className="max-w-6xl mx-auto">
        {/* Hero Header */}
        <div className="text-center mb-12">
          <div className="flex items-center justify-center gap-3 mb-4">
            <Sparkles className="w-10 h-10 text-purple-600" />
            <h1 className="text-4xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
              AI in TAVLO
            </h1>
          </div>
          <p className="text-xl text-gray-600 mb-3">
            Invisible · Assistive · Data-Driven · Business-Oriented
          </p>
          <p className="text-gray-500 max-w-2xl mx-auto">
            AI that quietly helps you make better decisions. Not chat bubbles, not gimmicks—just smart, contextual assistance where you need it.
          </p>
        </div>

        {/* Design Principles */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-12">
          {[
            { icon: '🎯', title: 'Contextual', desc: 'Appears where needed' },
            { icon: '✏️', title: 'Editable', desc: 'Always your control' },
            { icon: '💡', title: 'Explainable', desc: 'Shows why' },
            { icon: '🤝', title: 'Optional', desc: 'Never forced' }
          ].map((principle, index) => (
            <div key={index} className="bg-white rounded-xl border border-gray-200 p-5 text-center">
              <div className="text-3xl mb-2">{principle.icon}</div>
              <div className="font-medium mb-1">{principle.title}</div>
              <div className="text-sm text-gray-600">{principle.desc}</div>
            </div>
          ))}
        </div>

        {/* Features by User Type */}
        <div className="space-y-6">
          {/* Vendor Features */}
          <div className="bg-white rounded-2xl border-2 border-purple-200 overflow-hidden">
            <div className="bg-gradient-to-r from-purple-500 to-purple-600 p-6 text-white">
              <div className="flex items-center gap-3 mb-2">
                <Store className="w-6 h-6" />
                <h2 className="text-2xl font-semibold">AI for Vendors</h2>
              </div>
              <p className="text-purple-100">Save time, optimize menus, grow revenue</p>
            </div>
            <div className="p-6 space-y-4">
              <button
                onClick={() => setCurrentView('vendor-menu')}
                className="w-full group"
              >
                <div className="p-5 border-2 border-gray-200 rounded-xl hover:border-purple-500 hover:bg-purple-50 transition-all text-left">
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-2 mb-2">
                        <Sparkles className="w-5 h-5 text-purple-600" />
                        <h3 className="text-lg font-medium">AI Menu Creation & Translation</h3>
                      </div>
                      <p className="text-sm text-gray-600 mb-3">
                        Generate descriptions, detect allergens, estimate nutrition, translate to 11 languages
                      </p>
                      <div className="flex flex-wrap gap-2">
                        <span className="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full">Auto descriptions</span>
                        <span className="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full">Allergen detection</span>
                        <span className="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full">Nutrition estimates</span>
                        <span className="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full">Multi-language</span>
                      </div>
                    </div>
                    <ChevronRight className="w-6 h-6 text-gray-400 group-hover:text-purple-600 group-hover:translate-x-1 transition-all" />
                  </div>
                </div>
              </button>

              <button
                onClick={() => setCurrentView('vendor-insights')}
                className="w-full group"
              >
                <div className="p-5 border-2 border-gray-200 rounded-xl hover:border-purple-500 hover:bg-purple-50 transition-all text-left">
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-2 mb-2">
                        <Sparkles className="w-5 h-5 text-purple-600" />
                        <h3 className="text-lg font-medium">Performance Insights & Optimization</h3>
                      </div>
                      <p className="text-sm text-gray-600 mb-3">
                        Understand what's working, get actionable recommendations to increase orders and revenue
                      </p>
                      <div className="flex flex-wrap gap-2">
                        <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">Conversion analysis</span>
                        <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">Price optimization</span>
                        <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">Menu positioning</span>
                        <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">Photo quality</span>
                      </div>
                    </div>
                    <ChevronRight className="w-6 h-6 text-gray-400 group-hover:text-purple-600 group-hover:translate-x-1 transition-all" />
                  </div>
                </div>
              </button>
            </div>
          </div>

          {/* Customer Features */}
          <div className="bg-white rounded-2xl border-2 border-blue-200 overflow-hidden">
            <div className="bg-gradient-to-r from-blue-500 to-blue-600 p-6 text-white">
              <div className="flex items-center gap-3 mb-2">
                <Users className="w-6 h-6" />
                <h2 className="text-2xl font-semibold">AI for Customers</h2>
              </div>
              <p className="text-blue-100">Find perfect dishes faster, understand reviews better</p>
            </div>
            <div className="p-6 space-y-4">
              <button
                onClick={() => setCurrentView('customer-discovery')}
                className="w-full group"
              >
                <div className="p-5 border-2 border-gray-200 rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-all text-left">
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-2 mb-2">
                        <Sparkles className="w-5 h-5 text-blue-600" />
                        <h3 className="text-lg font-medium">Smart Menu Discovery</h3>
                      </div>
                      <p className="text-sm text-gray-600 mb-3">
                        Quick filters for "Most Popular", "Quick Lunch", dietary preferences—no chatbot needed
                      </p>
                      <div className="flex flex-wrap gap-2">
                        <span className="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">Smart filters</span>
                        <span className="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">Popularity signals</span>
                        <span className="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">AI picks</span>
                      </div>
                    </div>
                    <ChevronRight className="w-6 h-6 text-gray-400 group-hover:text-blue-600 group-hover:translate-x-1 transition-all" />
                  </div>
                </div>
              </button>

              <button
                onClick={() => setCurrentView('customer-reviews')}
                className="w-full group"
              >
                <div className="p-5 border-2 border-gray-200 rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-all text-left">
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-2 mb-2">
                        <Sparkles className="w-5 h-5 text-blue-600" />
                        <h3 className="text-lg font-medium">AI Review Summaries</h3>
                      </div>
                      <p className="text-sm text-gray-600 mb-3">
                        "What people say" at a glance—common pros, cons, phrases from hundreds of reviews
                      </p>
                      <div className="flex flex-wrap gap-2">
                        <span className="px-2 py-1 bg-orange-100 text-orange-700 text-xs rounded-full">Sentiment analysis</span>
                        <span className="px-2 py-1 bg-orange-100 text-orange-700 text-xs rounded-full">Key themes</span>
                        <span className="px-2 py-1 bg-orange-100 text-orange-700 text-xs rounded-full">Common phrases</span>
                      </div>
                    </div>
                    <ChevronRight className="w-6 h-6 text-gray-400 group-hover:text-blue-600 group-hover:translate-x-1 transition-all" />
                  </div>
                </div>
              </button>
            </div>
          </div>

          {/* Admin Features */}
          <div className="bg-white rounded-2xl border-2 border-red-200 overflow-hidden">
            <div className="bg-gradient-to-r from-red-500 to-red-600 p-6 text-white">
              <div className="flex items-center gap-3 mb-2">
                <Shield className="w-6 h-6" />
                <h2 className="text-2xl font-semibold">AI for Platform Admins</h2>
              </div>
              <p className="text-red-100">Predict risks, spot opportunities, scale operations</p>
            </div>
            <div className="p-6 space-y-4">
              <button
                onClick={() => setCurrentView('admin-insights')}
                className="w-full group"
              >
                <div className="p-5 border-2 border-gray-200 rounded-xl hover:border-red-500 hover:bg-red-50 transition-all text-left">
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="flex items-center gap-2 mb-2">
                        <Sparkles className="w-5 h-5 text-red-600" />
                        <h3 className="text-lg font-medium">Risk Scoring & Revenue Insights</h3>
                      </div>
                      <p className="text-sm text-gray-600 mb-3">
                        Predict churn, identify upgrade opportunities, detect fraud patterns, prioritize actions
                      </p>
                      <div className="flex flex-wrap gap-2">
                        <span className="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">Churn prediction</span>
                        <span className="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">Risk scoring</span>
                        <span className="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">Revenue ops</span>
                        <span className="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">Fraud detection</span>
                      </div>
                    </div>
                    <ChevronRight className="w-6 h-6 text-gray-400 group-hover:text-red-600 group-hover:translate-x-1 transition-all" />
                  </div>
                </div>
              </button>
            </div>
          </div>
        </div>

        {/* Design Philosophy */}
        <div className="mt-12 bg-white rounded-2xl border border-gray-200 p-8">
          <h2 className="text-2xl font-semibold mb-6 text-center">Our AI Philosophy</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <h3 className="font-medium text-green-700 mb-3">✅ We Do</h3>
              <ul className="space-y-2 text-sm text-gray-700">
                <li>• Subtle badges and tooltips</li>
                <li>• Inline suggestions you can edit</li>
                <li>• "Why this?" explanations</li>
                <li>• Data-driven insights with confidence scores</li>
                <li>• Optional AI assistance</li>
                <li>• Plain language, no hype</li>
              </ul>
            </div>
            <div>
              <h3 className="font-medium text-red-700 mb-3">❌ We Don't</h3>
              <ul className="space-y-2 text-sm text-gray-700">
                <li>• Big chat bubbles everywhere</li>
                <li>• AI avatars or mascots</li>
                <li>• Forced conversations</li>
                <li>• Unexplained magic</li>
                <li>• Loud animations</li>
                <li>• Marketing hype ("Revolutionary AI!")</li>
              </ul>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="mt-8 text-center text-sm text-gray-500">
          <p>AI in TAVLO feels like: <span className="font-medium text-gray-700">"The system quietly helps me make better decisions."</span></p>
          <p className="mt-2">Not like: <span className="line-through">"I'm talking to a robot."</span></p>
        </div>
      </div>
    </div>
  );
}
