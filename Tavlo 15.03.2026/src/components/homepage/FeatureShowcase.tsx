import { 
  Globe, 
  QrCode, 
  CreditCard, 
  Receipt, 
  Users, 
  Star,
  ShoppingBag,
  Clock,
  Award,
  Shield,
  Smartphone,
  Zap
} from 'lucide-react';

export function FeatureShowcase() {
  const features = [
    {
      icon: Globe,
      title: 'Multi-Language',
      description: '12 languages including English, German, Italian, French, Arabic, Turkish, Chinese, Japanese, Serbian, Czech, Spanish',
      color: 'from-blue-500 to-blue-600'
    },
    {
      icon: QrCode,
      title: 'QR Table Ordering',
      description: 'Scan, browse menu, order instantly. No app download required',
      color: 'from-emerald-500 to-emerald-600'
    },
    {
      icon: ShoppingBag,
      title: 'Takeaway & Pickup',
      description: 'Order ahead, skip the wait. Schedule pickup or order ASAP',
      color: 'from-orange-500 to-orange-600'
    },
    {
      icon: Users,
      title: 'Shared Basket',
      description: 'Groups at the same table can share one basket and order together',
      color: 'from-purple-500 to-purple-600'
    },
    {
      icon: CreditCard,
      title: 'Flexible Payments',
      description: 'Card, cash, split bills, Apple Pay, Google Pay. Your choice',
      color: 'from-pink-500 to-pink-600'
    },
    {
      icon: Receipt,
      title: 'Digital Receipts',
      description: 'Austrian VAT-compliant invoices with full legal compliance',
      color: 'from-indigo-500 to-indigo-600'
    },
    {
      icon: Clock,
      title: 'Real-Time Tracking',
      description: 'See your order status live: received, preparing, ready, delivered',
      color: 'from-teal-500 to-teal-600'
    },
    {
      icon: Award,
      title: 'Loyalty & Rewards',
      description: 'Earn points, get discounts, track rewards automatically',
      color: 'from-yellow-500 to-yellow-600'
    },
    {
      icon: Star,
      title: 'Reviews & Ratings',
      description: 'Rate dishes and restaurants, help others decide',
      color: 'from-red-500 to-red-600'
    },
    {
      icon: Shield,
      title: 'Guest & Auth Modes',
      description: 'Order as guest or sign in with Google, Apple, Facebook, GitHub',
      color: 'from-gray-500 to-gray-600'
    },
    {
      icon: Smartphone,
      title: 'Mobile-First Design',
      description: 'Optimized for phones, tablets, and desktop. Works everywhere',
      color: 'from-cyan-500 to-cyan-600'
    },
    {
      icon: Zap,
      title: 'Instant Access',
      description: 'No registration required. Scan QR and start ordering in seconds',
      color: 'from-lime-500 to-lime-600'
    }
  ];

  return (
    <div className="py-24 bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6">
        {/* Section Header */}
        <div className="text-center mb-16">
          <h2 className="text-4xl sm:text-5xl mb-4 text-gray-900">
            Everything you need
          </h2>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto">
            A complete restaurant platform built for the modern dining experience
          </p>
        </div>

        {/* Features Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {features.map((feature) => {
            const Icon = feature.icon;
            return (
              <div
                key={feature.title}
                className="bg-white rounded-2xl p-8 shadow-sm hover:shadow-lg transition-all border border-gray-100 hover:border-emerald-200 group"
              >
                <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ${feature.color} flex items-center justify-center mb-4 group-hover:scale-110 transition-transform`}>
                  <Icon className="w-6 h-6 text-white" />
                </div>
                <h3 className="text-xl mb-3 text-gray-900">
                  {feature.title}
                </h3>
                <p className="text-gray-600 leading-relaxed">
                  {feature.description}
                </p>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
