import image_a41c5ed9cb37da642e00d6ad4d3424dd23759fa0 from 'figma:asset/a41c5ed9cb37da642e00d6ad4d3424dd23759fa0.png';
import { Store, BarChart3, Users, Globe, Settings, TrendingUp, ArrowRight } from 'lucide-react';
import tavloLogo from 'figma:asset/d442f812b641089c191ab222c1e3bb84e36bdccf.png';

interface ForRestaurantsProps {
  onGetStarted: () => void;
}

export function ForRestaurants({ onGetStarted }: ForRestaurantsProps) {
  const features = [
    {
      icon: Store,
      title: 'Complete Dashboard',
      description: 'Manage orders, menu, pricing, and settings in one place'
    },
    {
      icon: BarChart3,
      title: 'Analytics & Insights',
      description: 'Track top customers, popular dishes, revenue trends'
    },
    {
      icon: Users,
      title: 'Customer Management',
      description: 'Handle reviews, complaints, loyalty programs'
    },
    {
      icon: Globe,
      title: 'Multi-Language Menus',
      description: 'Translate your menu into 12 languages automatically'
    },
    {
      icon: Settings,
      title: 'Full Control',
      description: 'Set pricing, VAT rates, service fees, order limits'
    },
    {
      icon: TrendingUp,
      title: 'Increase Revenue',
      description: 'Boost orders with promotions, loyalty, and takeaway'
    }
  ];

  return (
    <div className="py-24 bg-gradient-to-b from-gray-900 to-gray-800 text-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6">
        {/* Header */}
        <div className="text-center mb-16">
          <div className="flex justify-center mb-6">
            <img src={image_a41c5ed9cb37da642e00d6ad4d3424dd23759fa0} alt="Tavlo Logo" className="w-35 h-35 brightness-0 invert" />
          </div>
          <h2 className="text-4xl sm:text-5xl mb-4">
            For Restaurant Owners
          </h2>
          <p className="text-xl text-gray-300 max-w-2xl mx-auto">
            Transform your restaurant with a complete digital ordering platform
          </p>
        </div>

        {/* Features Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
          {features.map((feature) => {
            const Icon = feature.icon;
            return (
              <div
                key={feature.title}
                className="bg-white/5 backdrop-blur-sm rounded-2xl p-8 border border-white/10 hover:border-emerald-500/50 transition-all"
              >
                <div className="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center mb-4">
                  <Icon className="w-6 h-6 text-emerald-400" />
                </div>
                <h3 className="text-xl mb-3">
                  {feature.title}
                </h3>
                <p className="text-gray-400 leading-relaxed">
                  {feature.description}
                </p>
              </div>
            );
          })}
        </div>

        {/* Value Props */}
        <div className="bg-emerald-500/10 backdrop-blur-sm border border-emerald-500/20 rounded-2xl p-12 mb-12">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div>
              <div className="text-4xl mb-2">🚀</div>
              <div className="text-2xl mb-2">Quick Setup</div>
              <div className="text-gray-400">Go live in under 30 minutes</div>
            </div>
            <div>
              <div className="text-4xl mb-2">💰</div>
              <div className="text-2xl mb-2">No Commission</div>
              <div className="text-gray-400">Keep 100% of your revenue</div>
            </div>
            <div>
              <div className="text-4xl mb-2">📱</div>
              <div className="text-2xl mb-2">No App Required</div>
              <div className="text-gray-400">Customers scan QR and order</div>
            </div>
          </div>
        </div>

        {/* CTA */}
        <div className="text-center">
          <button
            onClick={onGetStarted}
            className="group inline-flex items-center gap-3 bg-emerald-600 hover:bg-emerald-700 text-white px-10 py-5 rounded-xl transition-all shadow-xl hover:shadow-2xl text-lg"
          >
            <Store className="w-6 h-6" />
            <span>Get Started for Free</span>
            <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform" />
          </button>
          <p className="mt-4 text-sm text-gray-400">
            No credit card required • 30-day free trial • Cancel anytime
          </p>
        </div>
      </div>
    </div>
  );
}