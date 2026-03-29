import { QrCode, Menu, ShoppingCart, CreditCard, Check } from 'lucide-react';

export function HowItWorks() {
  const steps = [
    {
      icon: QrCode,
      number: '1',
      title: 'Scan QR Code',
      description: 'Point your camera at the QR code on your table',
      color: 'bg-emerald-100 text-emerald-600'
    },
    {
      icon: Menu,
      number: '2',
      title: 'Browse Menu',
      description: 'View dishes in your language with photos and details',
      color: 'bg-blue-100 text-blue-600'
    },
    {
      icon: ShoppingCart,
      number: '3',
      title: 'Add to Basket',
      description: 'Select items, customize, add special requests',
      color: 'bg-purple-100 text-purple-600'
    },
    {
      icon: CreditCard,
      number: '4',
      title: 'Pay & Order',
      description: 'Choose payment method and submit your order',
      color: 'bg-orange-100 text-orange-600'
    },
    {
      icon: Check,
      number: '5',
      title: 'Track & Enjoy',
      description: 'Follow your order status in real-time',
      color: 'bg-teal-100 text-teal-600'
    }
  ];

  return (
    <div className="py-24 bg-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6">
        {/* Section Header */}
        <div className="text-center mb-16">
          <h2 className="text-4xl sm:text-5xl mb-4 text-gray-900">
            How it works
          </h2>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto">
            Order in 5 simple steps. No app, no hassle, no wait.
          </p>
        </div>

        {/* Steps */}
        <div className="grid grid-cols-1 md:grid-cols-5 gap-8 relative">
          {/* Connection Lines - Desktop */}
          <div className="hidden md:block absolute top-16 left-0 right-0 h-0.5 bg-gradient-to-r from-emerald-200 via-purple-200 to-teal-200 -z-10"></div>

          {steps.map((step, index) => {
            const Icon = step.icon;
            return (
              <div key={step.number} className="relative">
                {/* Mobile Connection Line */}
                {index < steps.length - 1 && (
                  <div className="md:hidden absolute left-1/2 top-32 -ml-px w-0.5 h-8 bg-gradient-to-b from-emerald-200 to-purple-200"></div>
                )}

                <div className="flex flex-col items-center text-center">
                  {/* Icon Container */}
                  <div className={`w-16 h-16 rounded-full ${step.color} flex items-center justify-center mb-4 shadow-lg relative z-10`}>
                    <Icon className="w-8 h-8" />
                  </div>
                  
                  {/* Step Number */}
                  <div className="absolute top-0 left-1/2 -ml-3 w-6 h-6 bg-white border-2 border-gray-300 rounded-full flex items-center justify-center text-xs text-gray-600 shadow-sm">
                    {step.number}
                  </div>

                  {/* Content */}
                  <h3 className="text-lg mb-2 text-gray-900 mt-2">
                    {step.title}
                  </h3>
                  <p className="text-sm text-gray-600 leading-relaxed">
                    {step.description}
                  </p>
                </div>
              </div>
            );
          })}
        </div>

        {/* CTA */}
        <div className="mt-16 text-center">
          <div className="inline-flex items-center gap-2 px-6 py-3 bg-emerald-50 rounded-full border border-emerald-200">
            <div className="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
            <span className="text-sm text-emerald-800">Ready to order? Scan any TAVLO QR code to begin</span>
          </div>
        </div>
      </div>
    </div>
  );
}
