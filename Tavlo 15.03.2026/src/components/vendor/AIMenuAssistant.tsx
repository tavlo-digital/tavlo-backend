import { X, Sparkles, TrendingUp, AlertTriangle, Lightbulb, ArrowRight, Languages, ShieldCheck, ChefHat, BarChart3, DollarSign, Image, TrendingDown } from 'lucide-react';
import { Button } from '../ui/button';
import { analyzeMenuPerformance, generateMenuSuggestions } from '../../utils/aiHelpers';
import { AIBadge } from '../ai/AIComponents';
import showcaseImage from 'figma:asset/fbac487590a0d8f0c3decfdf37f8f6dac7a43a06.png';
import { AIMenuEditor } from './AIMenuEditor';
import { AIInsights } from './AIInsights';
import { useState } from 'react';

interface AIMenuAssistantProps {
  isOpen: boolean;
  onClose: () => void;
  menuItems: any[];
  onApplySuggestion: (suggestion: any) => void;
}

export function AIMenuAssistant({ isOpen, onClose, menuItems, onApplySuggestion }: AIMenuAssistantProps) {
  const [currentView, setCurrentView] = useState<'home' | 'menu-editor' | 'insights'>('home');
  
  if (!isOpen) return null;

  const insights = analyzeMenuPerformance(menuItems);
  const suggestions = generateMenuSuggestions(menuItems);

  // Show AIMenuEditor view
  if (currentView === 'menu-editor') {
    return (
      <>
        {/* Backdrop */}
        <div 
          className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50"
          onClick={onClose}
        />

        {/* Modal */}
        <div className="fixed inset-4 md:inset-8 bg-white rounded-2xl z-50 flex flex-col overflow-hidden shadow-2xl">
          {/* Header */}
          <div className="relative px-6 pt-6 pb-4 border-b bg-gradient-to-r from-purple-50 to-white flex-shrink-0">
            <button 
              onClick={onClose}
              className="absolute top-4 right-4 p-2 hover:bg-white rounded-full transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
            
            <button
              onClick={() => setCurrentView('home')}
              className="mb-2 px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm flex items-center gap-2"
            >
              ← Back to AI Assistant
            </button>
            
            <div className="flex items-center gap-3 mb-2">
              <div className="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
                <Sparkles className="w-5 h-5 text-white" />
              </div>
              <div>
                <h2 className="text-xl">AI Menu Creation & Translation</h2>
                <p className="text-sm text-gray-600">Generate descriptions, detect allergens, translate automatically</p>
              </div>
            </div>
          </div>

          {/* Content - Scrollable */}
          <div className="flex-1 overflow-y-auto min-h-0">
            <div className="p-6">
              <AIMenuEditor />
            </div>
          </div>
        </div>
      </>
    );
  }

  // Show AIInsights view
  if (currentView === 'insights') {
    return (
      <>
        {/* Backdrop */}
        <div 
          className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50"
          onClick={onClose}
        />

        {/* Modal */}
        <div className="fixed inset-4 md:inset-8 bg-white rounded-2xl z-50 flex flex-col overflow-hidden shadow-2xl">
          {/* Header */}
          <div className="relative px-6 pt-6 pb-4 border-b bg-gradient-to-r from-purple-50 to-white flex-shrink-0">
            <button 
              onClick={onClose}
              className="absolute top-4 right-4 p-2 hover:bg-white rounded-full transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
            
            <button
              onClick={() => setCurrentView('home')}
              className="mb-2 px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm flex items-center gap-2"
            >
              ← Back to AI Assistant
            </button>
            
            <div className="flex items-center gap-3 mb-2">
              <div className="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
                <Sparkles className="w-5 h-5 text-white" />
              </div>
              <div>
                <h2 className="text-xl">Performance Insights & Optimization</h2>
                <p className="text-sm text-gray-600">Data-driven recommendations to grow your revenue</p>
              </div>
            </div>
          </div>

          {/* Content - Scrollable */}
          <div className="flex-1 overflow-y-auto min-h-0">
            <div className="p-6">
              <AIInsights />
            </div>
          </div>
        </div>
      </>
    );
  }

  return (
    <>
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="fixed inset-x-4 top-4 bottom-4 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:w-full md:max-w-3xl bg-white rounded-2xl z-50 flex flex-col overflow-hidden shadow-2xl">
        {/* Header */}
        <div className="relative px-6 pt-6 pb-4 border-b bg-gradient-to-r from-purple-50 to-white">
          <button 
            onClick={onClose}
            className="absolute top-4 right-4 p-2 hover:bg-white rounded-full transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
          
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center">
              <Sparkles className="w-5 h-5 text-white" />
            </div>
            <div>
              <h2 className="text-xl">AI Menu Assistant</h2>
              <p className="text-sm text-gray-600">Data-driven insights to optimize your menu</p>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto px-6 py-6">
          {/* AI for Vendors Showcase */}
          <div className="mb-8 bg-gradient-to-br from-purple-600 to-purple-700 rounded-xl p-6 text-white">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                <Sparkles className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-lg font-semibold">AI for Vendors</h3>
                <p className="text-sm text-purple-100">Save time, optimize menus, grow revenue</p>
              </div>
            </div>

            <div className="space-y-3 mt-4">
              {/* AI Menu Creation & Translation Card */}
              <button
                onClick={() => setCurrentView('menu-editor')}
                className="w-full text-left bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20 hover:bg-white/15 transition-colors cursor-pointer group"
              >
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-2">
                      <Sparkles className="w-5 h-5 text-purple-200" />
                      <h4 className="font-medium">AI Menu Creation & Translation</h4>
                    </div>
                    <p className="text-sm text-purple-100 mb-3">
                      Generate descriptions, detect allergens, estimate nutrition, translate to 11 languages
                    </p>
                    <div className="flex flex-wrap gap-2">
                      <span className="text-xs px-2 py-1 bg-purple-500/40 rounded-full border border-purple-300/30">
                        Auto descriptions
                      </span>
                      <span className="text-xs px-2 py-1 bg-purple-500/40 rounded-full border border-purple-300/30">
                        Allergen detection
                      </span>
                      <span className="text-xs px-2 py-1 bg-purple-500/40 rounded-full border border-purple-300/30">
                        Nutrition estimates
                      </span>
                      <span className="text-xs px-2 py-1 bg-purple-500/40 rounded-full border border-purple-300/30">
                        Multi-language
                      </span>
                    </div>
                  </div>
                  <ArrowRight className="w-5 h-5 text-white/60 group-hover:text-white transition-colors ml-3" />
                </div>
              </button>

              {/* Performance Insights & Optimization Card */}
              <button
                onClick={() => setCurrentView('insights')}
                className="w-full text-left bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20 hover:bg-white/15 transition-colors cursor-pointer group"
              >
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-2">
                      <Sparkles className="w-5 h-5 text-purple-200" />
                      <h4 className="font-medium">Performance Insights & Optimization</h4>
                    </div>
                    <p className="text-sm text-purple-100 mb-3">
                      Understand what's working, get actionable recommendations to increase orders and revenue
                    </p>
                    <div className="flex flex-wrap gap-2">
                      <span className="text-xs px-2 py-1 bg-purple-500/40 rounded-full border border-purple-300/30">
                        Conversion analysis
                      </span>
                      <span className="text-xs px-2 py-1 bg-purple-500/40 rounded-full border border-purple-300/30">
                        Price optimization
                      </span>
                      <span className="text-xs px-2 py-1 bg-purple-500/40 rounded-full border border-purple-300/30">
                        Menu positioning
                      </span>
                      <span className="text-xs px-2 py-1 bg-purple-500/40 rounded-full border border-purple-300/30">
                        Photo quality
                      </span>
                    </div>
                  </div>
                  <ArrowRight className="w-5 h-5 text-white/60 group-hover:text-white transition-colors ml-3" />
                </div>
              </button>
            </div>
          </div>

          {/* Performance Insights */}
          <div className="mb-6">
            <div className="flex items-center gap-2 mb-4">
              <TrendingUp className="w-5 h-5 text-purple-600" />
              <h3 className="font-medium">Performance Insights</h3>
              <AIBadge />
            </div>

            {insights.length > 0 ? (
              <div className="space-y-3">
                {insights.map((insight, idx) => (
                  <div 
                    key={idx}
                    className={`p-4 rounded-lg border ${
                      insight.type === 'warning' ? 'bg-orange-50 border-orange-200' :
                      insight.type === 'success' ? 'bg-green-50 border-green-200' :
                      'bg-purple-50 border-purple-200'
                    }`}
                  >
                    <div className="flex items-start gap-3">
                      <div className="mt-0.5">
                        {insight.type === 'warning' && <AlertTriangle className="w-5 h-5 text-orange-600" />}
                        {insight.type === 'success' && <TrendingUp className="w-5 h-5 text-green-600" />}
                        {insight.type === 'recommendation' && <Sparkles className="w-5 h-5 text-purple-600" />}
                      </div>
                      <div className="flex-1">
                        <h4 className="font-medium mb-1">{insight.title}</h4>
                        <p className="text-sm opacity-90 mb-2">{insight.description}</p>
                        {insight.metric && (
                          <div className="text-xs font-medium opacity-75 mb-2">{insight.metric}</div>
                        )}
                        <div className="text-xs text-gray-600 italic">
                          {insight.explanation}
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-sm text-gray-600 p-4 bg-gray-50 rounded-lg">
                Your menu is performing well! Keep monitoring for opportunities.
              </div>
            )}
          </div>

          {/* Menu Suggestions */}
          <div>
            <div className="flex items-center gap-2 mb-4">
              <Lightbulb className="w-5 h-5 text-purple-600" />
              <h3 className="font-medium">Smart Suggestions</h3>
              <AIBadge />
            </div>

            {suggestions.length > 0 ? (
              <div className="space-y-3">
                {suggestions.map((suggestion, idx) => (
                  <div 
                    key={idx}
                    className="p-4 rounded-lg border bg-blue-50 border-blue-200 flex items-start justify-between gap-4"
                  >
                    <div className="flex-1">
                      <h4 className="font-medium mb-1">{suggestion.title}</h4>
                      <p className="text-sm opacity-90 mb-2">{suggestion.description}</p>
                      <div className="flex items-center gap-2">
                        <span className={`text-xs px-2 py-0.5 rounded ${
                          suggestion.impact === 'high' ? 'bg-green-100 text-green-700' :
                          suggestion.impact === 'medium' ? 'bg-yellow-100 text-yellow-700' :
                          'bg-gray-100 text-gray-700'
                        }`}>
                          {suggestion.impact} impact
                        </span>
                      </div>
                    </div>
                    <Button
                      size="sm"
                      onClick={() => onApplySuggestion(suggestion)}
                      className="bg-purple-600 hover:bg-purple-700"
                    >
                      <ArrowRight className="w-4 h-4" />
                    </Button>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-sm text-gray-600 p-4 bg-gray-50 rounded-lg">
                No immediate suggestions. Your menu structure looks good!
              </div>
            )}
          </div>

          {/* Footer Info */}
          <div className="mt-6 p-4 bg-purple-50 rounded-lg border border-purple-200">
            <p className="text-xs text-gray-700">
              <strong>How it works:</strong> AI analyzes your menu performance data including views, orders, ratings, and pricing compared to industry benchmarks. All suggestions are based on real data patterns and can be customized to fit your restaurant's unique style.
            </p>
          </div>
        </div>

        {/* Footer Actions */}
        <div className="px-6 py-4 border-t bg-gray-50">
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={onClose}>
              Close
            </Button>
          </div>
        </div>
      </div>
    </>
  );
}