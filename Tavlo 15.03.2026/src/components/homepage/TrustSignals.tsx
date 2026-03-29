import { Shield, Lock, FileCheck, Globe, Award, Users } from 'lucide-react';

export function TrustSignals() {
  return (
    <div className="py-20 bg-gradient-to-b from-gray-50 to-white">
      <div className="max-w-7xl mx-auto px-4 sm:px-6">
        {/* Header */}
        <div className="text-center mb-12">
          <h2 className="text-3xl sm:text-4xl mb-3 text-gray-900">
            Built for trust & reliability
          </h2>
          <p className="text-lg text-gray-600">
            Professional infrastructure you can depend on
          </p>
        </div>

        {/* Trust Badges */}
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
          <div className="bg-white rounded-xl p-6 text-center border border-gray-100 shadow-sm">
            <Shield className="w-8 h-8 mx-auto mb-3 text-emerald-600" />
            <div className="text-sm text-gray-900 mb-1">Secure</div>
            <div className="text-xs text-gray-500">SSL Encrypted</div>
          </div>

          <div className="bg-white rounded-xl p-6 text-center border border-gray-100 shadow-sm">
            <Lock className="w-8 h-8 mx-auto mb-3 text-emerald-600" />
            <div className="text-sm text-gray-900 mb-1">Privacy</div>
            <div className="text-xs text-gray-500">GDPR Compliant</div>
          </div>

          <div className="bg-white rounded-xl p-6 text-center border border-gray-100 shadow-sm">
            <FileCheck className="w-8 h-8 mx-auto mb-3 text-emerald-600" />
            <div className="text-sm text-gray-900 mb-1">Legal</div>
            <div className="text-xs text-gray-500">Austrian VAT</div>
          </div>

          <div className="bg-white rounded-xl p-6 text-center border border-gray-100 shadow-sm">
            <Globe className="w-8 h-8 mx-auto mb-3 text-emerald-600" />
            <div className="text-sm text-gray-900 mb-1">Global</div>
            <div className="text-xs text-gray-500">12 Languages</div>
          </div>

          <div className="bg-white rounded-xl p-6 text-center border border-gray-100 shadow-sm">
            <Award className="w-8 h-8 mx-auto mb-3 text-emerald-600" />
            <div className="text-sm text-gray-900 mb-1">Quality</div>
            <div className="text-xs text-gray-500">Verified Venues</div>
          </div>

          <div className="bg-white rounded-xl p-6 text-center border border-gray-100 shadow-sm">
            <Users className="w-8 h-8 mx-auto mb-3 text-emerald-600" />
            <div className="text-sm text-gray-900 mb-1">Support</div>
            <div className="text-xs text-gray-500">24/7 Available</div>
          </div>
        </div>

        {/* Payment Methods */}
        <div className="mt-16 pt-12 border-t border-gray-200">
          <div className="text-center mb-8">
            <p className="text-sm text-gray-600 mb-4">Accepted Payment Methods</p>
            <div className="flex flex-wrap items-center justify-center gap-6">
              <div className="px-6 py-3 bg-white rounded-lg border border-gray-200 shadow-sm text-sm text-gray-700">
                💳 Credit Card
              </div>
              <div className="px-6 py-3 bg-white rounded-lg border border-gray-200 shadow-sm text-sm text-gray-700">
                 Apple Pay
              </div>
              <div className="px-6 py-3 bg-white rounded-lg border border-gray-200 shadow-sm text-sm text-gray-700">
                📱 Google Pay
              </div>
              <div className="px-6 py-3 bg-white rounded-lg border border-gray-200 shadow-sm text-sm text-gray-700">
                💵 Cash
              </div>
              <div className="px-6 py-3 bg-white rounded-lg border border-gray-200 shadow-sm text-sm text-gray-700">
                🔄 Split Bill
              </div>
            </div>
          </div>
        </div>

        {/* Social Login */}
        <div className="mt-8 text-center">
          <p className="text-sm text-gray-600 mb-4">Sign in with your favorite service</p>
          <div className="flex flex-wrap items-center justify-center gap-4">
            <div className="px-5 py-2 bg-white rounded-lg border border-gray-200 shadow-sm text-sm text-gray-700 flex items-center gap-2">
              <div className="w-5 h-5 bg-gradient-to-br from-blue-500 to-blue-600 rounded"></div>
              <span>Google</span>
            </div>
            <div className="px-5 py-2 bg-white rounded-lg border border-gray-200 shadow-sm text-sm text-gray-700 flex items-center gap-2">
              <div className="w-5 h-5 bg-gradient-to-br from-gray-800 to-black rounded"></div>
              <span>Apple</span>
            </div>
            <div className="px-5 py-2 bg-white rounded-lg border border-gray-200 shadow-sm text-sm text-gray-700 flex items-center gap-2">
              <div className="w-5 h-5 bg-gradient-to-br from-blue-600 to-blue-700 rounded"></div>
              <span>Facebook</span>
            </div>
            <div className="px-5 py-2 bg-white rounded-lg border border-gray-200 shadow-sm text-sm text-gray-700 flex items-center gap-2">
              <div className="w-5 h-5 bg-gradient-to-br from-gray-700 to-gray-900 rounded"></div>
              <span>GitHub</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
