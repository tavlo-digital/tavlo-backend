import { useState } from 'react';
import { 
  CreditCard, 
  DollarSign, 
  CheckCircle, 
  XCircle, 
  AlertTriangle, 
  Info, 
  Eye, 
  EyeOff, 
  Activity,
  Clock,
  Shield,
  Key,
  RefreshCw,
  ExternalLink,
  AlertCircle,
  Lock,
  Zap,
  TrendingUp
} from 'lucide-react';
import { Input } from '../ui/input';
import { toast } from 'sonner@2.0.3';

type ProviderStatus = 'disabled' | 'configured' | 'active';
type Environment = 'test' | 'live';
type WebhookHealth = 'healthy' | 'degraded' | 'down';

interface PaymentProvider {
  id: string;
  name: string;
  description: string;
  logo: any; // Lucide icon component
  status: ProviderStatus;
  environment: Environment;
  credentials: {
    publishableKey: string;
    secretKey: string;
  };
  webhook: {
    health: WebhookHealth;
    lastEventReceived: string | null;
    failedEventsLast24h: number;
    lastRetry: string | null;
  };
  vendorImpact: {
    vendorsUsing: number;
    activeSubscriptions: number;
    paymentsLast24h: number;
  };
}

interface EnhancedPaymentInfrastructureProps {
  isSuperAdmin?: boolean;
}

export function EnhancedPaymentInfrastructure({ isSuperAdmin = true }: EnhancedPaymentInfrastructureProps) {
  const [providers, setProviders] = useState<PaymentProvider[]>([
    {
      id: 'stripe',
      name: 'Stripe',
      description: 'Card payments & digital wallets',
      logo: CreditCard,
      status: 'active',
      environment: 'live',
      credentials: {
        publishableKey: 'pk_live_••••••••••••••••',
        secretKey: 'sk_live_••••••••••••••••'
      },
      webhook: {
        health: 'healthy',
        lastEventReceived: '2025-01-06 16:30:22',
        failedEventsLast24h: 0,
        lastRetry: null
      },
      vendorImpact: {
        vendorsUsing: 1043,
        activeSubscriptions: 1043,
        paymentsLast24h: 2847
      }
    },
    {
      id: 'paypal',
      name: 'PayPal',
      description: 'PayPal & Venmo payments',
      logo: DollarSign,
      status: 'configured',
      environment: 'test',
      credentials: {
        publishableKey: 'paypal_client_••••••••••••••••',
        secretKey: 'paypal_secret_••••••••••••••••'
      },
      webhook: {
        health: 'degraded',
        lastEventReceived: '2025-01-06 14:20:15',
        failedEventsLast24h: 3,
        lastRetry: '2025-01-06 16:15:00'
      },
      vendorImpact: {
        vendorsUsing: 0,
        activeSubscriptions: 0,
        paymentsLast24h: 0
      }
    }
  ]);

  const [showEnvironmentSwitch, setShowEnvironmentSwitch] = useState<string | null>(null);
  const [showReplaceKey, setShowReplaceKey] = useState<{ providerId: string; keyType: 'publishable' | 'secret' } | null>(null);
  const [showActivateProvider, setShowActivateProvider] = useState<string | null>(null);
  const [confirmationText, setConfirmationText] = useState('');

  const handleEnvironmentSwitch = (providerId: string, newEnvironment: Environment) => {
    setShowEnvironmentSwitch(providerId);
  };

  const confirmEnvironmentSwitch = (providerId: string, newEnvironment: Environment) => {
    if (newEnvironment === 'live' && confirmationText !== 'SWITCH TO LIVE') {
      toast.error('Please type "SWITCH TO LIVE" to confirm');
      return;
    }

    // Audit log
    console.log('AUDIT LOG: Environment switched', {
      providerId,
      newEnvironment,
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString(),
      reason: 'Production deployment'
    });

    setProviders(providers.map(p => 
      p.id === providerId ? { ...p, environment: newEnvironment } : p
    ));

    setShowEnvironmentSwitch(null);
    setConfirmationText('');

    toast.success('Environment switched', {
      description: `${providers.find(p => p.id === providerId)?.name} is now in ${newEnvironment} mode`
    });
  };

  const handleActivateProvider = (providerId: string) => {
    const provider = providers.find(p => p.id === providerId);
    if (!provider) return;

    if (provider.status === 'disabled') {
      toast.error('Cannot activate', {
        description: 'Please configure credentials first'
      });
      return;
    }

    if (provider.webhook.health === 'down') {
      toast.error('Cannot activate', {
        description: 'Webhook is down. Fix connectivity first.'
      });
      return;
    }

    setShowActivateProvider(providerId);
  };

  const confirmActivateProvider = (providerId: string) => {
    const provider = providers.find(p => p.id === providerId);
    if (!provider) return;

    // Audit log
    console.log('AUDIT LOG: Provider activated', {
      providerId,
      providerName: provider.name,
      environment: provider.environment,
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString()
    });

    setProviders(providers.map(p => 
      p.id === providerId ? { ...p, status: 'active' } : p
    ));

    setShowActivateProvider(null);

    toast.success('Provider activated', {
      description: `${provider.name} is now available to all vendors`
    });
  };

  const handleDeactivateProvider = (providerId: string) => {
    const provider = providers.find(p => p.id === providerId);
    if (!provider) return;

    if (provider.vendorImpact.vendorsUsing > 0) {
      toast.error('Cannot deactivate', {
        description: `${provider.vendorImpact.vendorsUsing} vendors are using this provider`
      });
      return;
    }

    // Audit log
    console.log('AUDIT LOG: Provider deactivated', {
      providerId,
      providerName: provider.name,
      admin: 'Current Super Admin',
      timestamp: new Date().toISOString()
    });

    setProviders(providers.map(p => 
      p.id === providerId ? { ...p, status: 'configured' } : p
    ));

    toast.success('Provider deactivated', {
      description: `${provider.name} is no longer available to vendors`
    });
  };

  return (
    <div className="p-6 space-y-6">
      {/* Page Header */}
      <div>
        <h2 className="text-lg font-medium mb-2">Payment Infrastructure</h2>
        <p className="text-sm text-gray-500">
          Platform-level payment provider control and monitoring
        </p>
      </div>

      {/* Platform Governance Banner (Strengthened) */}
      <div className="bg-purple-50 border-2 border-purple-300 rounded-xl p-4">
        <div className="flex items-start gap-3">
          <Shield className="w-5 h-5 text-purple-600 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-semibold text-purple-900 mb-1">
              Platform Infrastructure Only
            </h3>
            <p className="text-sm text-purple-800 mb-2">
              <strong>These settings control Tavlo's payment infrastructure only.</strong>
              {' '}Vendors configure payment methods, tipping, and service fees in their own dashboards.
              {' '}<strong className="text-purple-900">Changes here affect the entire platform.</strong>
            </p>
            <div className="flex items-center gap-2 mt-2">
              <Lock className="w-4 h-4 text-purple-700" />
              <span className="text-xs font-medium text-purple-900">Super Admin Only</span>
            </div>
          </div>
        </div>
      </div>

      {/* Scope Clarification */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* What This Page Does */}
        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
          <div className="flex items-start gap-2 mb-2">
            <CheckCircle className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
            <h3 className="font-semibold text-green-900">What This Page Does</h3>
          </div>
          <ul className="text-sm text-green-800 space-y-1 pl-7">
            <li>• Enables or disables payment providers globally</li>
            <li>• Stores and validates PSP credentials</li>
            <li>• Monitors webhook and provider health</li>
            <li>• Shows platform-wide payment impact</li>
          </ul>
        </div>

        {/* What This Page Does NOT Do */}
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <div className="flex items-start gap-2 mb-2">
            <XCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
            <h3 className="font-semibold text-red-900">What This Page Does NOT Do</h3>
          </div>
          <ul className="text-sm text-red-800 space-y-1 pl-7">
            <li>• Configure vendor fees or payment rules</li>
            <li>• Control cash acceptance</li>
            <li>• Manage refunds or retries</li>
            <li>• Resolve individual payment issues</li>
          </ul>
        </div>
      </div>

      {/* Permission Notice for Non-Super Admin */}
      {!isSuperAdmin && (
        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
          <div className="flex items-start gap-3">
            <Lock className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="font-medium text-amber-900 mb-1">Read-Only Access</h3>
              <p className="text-sm text-amber-800">
                You have read-only access to payment infrastructure. 
                Only Super Admin can modify credentials or activate providers.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Payment Provider Cards */}
      <div className="space-y-6">
        {providers.map((provider) => (
          <PaymentProviderCard
            key={provider.id}
            provider={provider}
            isSuperAdmin={isSuperAdmin}
            onEnvironmentSwitch={(env) => handleEnvironmentSwitch(provider.id, env)}
            onActivate={() => handleActivateProvider(provider.id)}
            onDeactivate={() => handleDeactivateProvider(provider.id)}
            onReplaceKey={(keyType) => setShowReplaceKey({ providerId: provider.id, keyType })}
          />
        ))}
      </div>

      {/* Cash Payments Section (Clarified) */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-start gap-3">
          <Info className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
          <div>
            <h3 className="font-medium text-blue-900 mb-1">Cash Payment Configuration</h3>
            <p className="text-sm text-blue-800">
              <strong>Cash acceptance is configured by vendors.</strong>
              {' '}Tavlo does not enable or disable cash payments. 
              Each vendor decides whether to accept cash in their dashboard settings.
            </p>
          </div>
        </div>
      </div>

      {/* Environment Switch Modal */}
      {showEnvironmentSwitch && (
        <EnvironmentSwitchModal
          provider={providers.find(p => p.id === showEnvironmentSwitch)!}
          onClose={() => {
            setShowEnvironmentSwitch(null);
            setConfirmationText('');
          }}
          onConfirm={(newEnv) => confirmEnvironmentSwitch(showEnvironmentSwitch, newEnv)}
          confirmationText={confirmationText}
          setConfirmationText={setConfirmationText}
        />
      )}

      {/* Activate Provider Modal */}
      {showActivateProvider && (
        <ActivateProviderModal
          provider={providers.find(p => p.id === showActivateProvider)!}
          onClose={() => setShowActivateProvider(null)}
          onConfirm={() => confirmActivateProvider(showActivateProvider)}
        />
      )}
    </div>
  );
}

// Payment Provider Card Component
interface PaymentProviderCardProps {
  provider: PaymentProvider;
  isSuperAdmin: boolean;
  onEnvironmentSwitch: (env: Environment) => void;
  onActivate: () => void;
  onDeactivate: () => void;
  onReplaceKey: (keyType: 'publishable' | 'secret') => void;
}

function PaymentProviderCard({ 
  provider, 
  isSuperAdmin,
  onEnvironmentSwitch, 
  onActivate, 
  onDeactivate,
  onReplaceKey 
}: PaymentProviderCardProps) {
  const Logo = provider.logo;

  const getStatusBadge = () => {
    switch (provider.status) {
      case 'active':
        return (
          <span className="flex items-center gap-1.5 text-xs px-2.5 py-1 bg-green-100 text-green-700 rounded-full font-medium">
            <CheckCircle className="w-3.5 h-3.5" />
            Active
          </span>
        );
      case 'configured':
        return (
          <span className="flex items-center gap-1.5 text-xs px-2.5 py-1 bg-blue-100 text-blue-700 rounded-full font-medium">
            <Zap className="w-3.5 h-3.5" />
            Configured
          </span>
        );
      case 'disabled':
        return (
          <span className="flex items-center gap-1.5 text-xs px-2.5 py-1 bg-gray-100 text-gray-700 rounded-full font-medium">
            <XCircle className="w-3.5 h-3.5" />
            Disabled
          </span>
        );
    }
  };

  const getEnvironmentBadge = () => {
    if (provider.environment === 'live') {
      return (
        <span className="flex items-center gap-1.5 text-xs px-2.5 py-1 bg-green-100 text-green-700 rounded-full font-medium border-2 border-green-300">
          🟢 Live (Production)
        </span>
      );
    } else {
      return (
        <span className="flex items-center gap-1.5 text-xs px-2.5 py-1 bg-yellow-100 text-yellow-700 rounded-full font-medium border-2 border-yellow-300">
          🟡 Test (Sandbox)
        </span>
      );
    }
  };

  const getWebhookHealthBadge = () => {
    switch (provider.webhook.health) {
      case 'healthy':
        return (
          <span className="flex items-center gap-1.5 text-xs text-green-600">
            <Activity className="w-3.5 h-3.5" />
            Healthy
          </span>
        );
      case 'degraded':
        return (
          <span className="flex items-center gap-1.5 text-xs text-amber-600">
            <AlertTriangle className="w-3.5 h-3.5" />
            Degraded
          </span>
        );
      case 'down':
        return (
          <span className="flex items-center gap-1.5 text-xs text-red-600">
            <XCircle className="w-3.5 h-3.5" />
            Down
          </span>
        );
    }
  };

  return (
    <div className={`border-2 rounded-xl p-5 ${
      provider.webhook.health === 'down' ? 'border-red-300 bg-red-50' :
      provider.webhook.health === 'degraded' ? 'border-amber-300 bg-amber-50' :
      'border-gray-200 bg-white'
    }`}>
      {/* Provider Header */}
      <div className="flex items-start justify-between mb-5">
        <div className="flex items-center gap-3">
          <div className={`w-12 h-12 rounded-lg flex items-center justify-center ${
            provider.id === 'stripe' ? 'bg-purple-100' : 'bg-blue-100'
          }`}>
            <Logo className={`w-6 h-6 ${
              provider.id === 'stripe' ? 'text-purple-600' : 'text-blue-600'
            }`} />
          </div>
          <div>
            <h3 className="font-semibold text-lg">{provider.name}</h3>
            <p className="text-sm text-gray-600">{provider.description}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {getStatusBadge()}
          {getEnvironmentBadge()}
        </div>
      </div>

      {/* Webhook Health Alert */}
      {provider.webhook.health !== 'healthy' && (
        <div className={`mb-4 p-3 rounded-lg border ${
          provider.webhook.health === 'down' ? 'bg-red-100 border-red-300' : 'bg-amber-100 border-amber-300'
        }`}>
          <div className="flex items-start gap-2">
            <AlertTriangle className={`w-4 h-4 flex-shrink-0 mt-0.5 ${
              provider.webhook.health === 'down' ? 'text-red-600' : 'text-amber-600'
            }`} />
            <div>
              <p className={`text-sm font-medium ${
                provider.webhook.health === 'down' ? 'text-red-900' : 'text-amber-900'
              }`}>
                {provider.webhook.health === 'down' ? 'Webhook Down' : 'Webhook Degraded'}
              </p>
              <p className={`text-xs mt-0.5 ${
                provider.webhook.health === 'down' ? 'text-red-800' : 'text-amber-800'
              }`}>
                {provider.webhook.failedEventsLast24h} failed events in last 24h. 
                This may affect payment confirmations.
              </p>
            </div>
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: Credentials & Environment */}
        <div className="lg:col-span-2 space-y-4">
          {/* Environment Selector */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Environment
            </label>
            <select
              value={provider.environment}
              onChange={(e) => isSuperAdmin && onEnvironmentSwitch(e.target.value as Environment)}
              disabled={!isSuperAdmin}
              className={`w-full px-3 py-2 border border-gray-300 rounded-lg text-sm ${
                isSuperAdmin ? 'cursor-pointer' : 'cursor-not-allowed bg-gray-50'
              }`}
            >
              <option value="test">Test Environment (Sandbox)</option>
              <option value="live">Live Environment (Production)</option>
            </select>
            {provider.environment === 'live' && (
              <p className="text-xs text-red-600 mt-1 flex items-center gap-1">
                <AlertTriangle className="w-3 h-3" />
                Production mode - real payments processed
              </p>
            )}
          </div>

          {/* Credentials */}
          <div className="space-y-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Publishable Key
              </label>
              <div className="flex gap-2">
                <Input
                  type="password"
                  value={provider.credentials.publishableKey}
                  className="flex-1 text-sm bg-gray-50"
                  readOnly
                />
                {isSuperAdmin && (
                  <button
                    onClick={() => onReplaceKey('publishable')}
                    className="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
                  >
                    <RefreshCw className="w-4 h-4 text-gray-600" />
                  </button>
                )}
              </div>
              <p className="text-xs text-gray-500 mt-1">
                Keys cannot be viewed after save. Only replacement allowed.
              </p>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Secret Key
              </label>
              <div className="flex gap-2">
                <Input
                  type="password"
                  value={provider.credentials.secretKey}
                  className="flex-1 text-sm bg-gray-50"
                  readOnly
                />
                {isSuperAdmin && (
                  <button
                    onClick={() => onReplaceKey('secret')}
                    className="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm"
                  >
                    <RefreshCw className="w-4 h-4 text-gray-600" />
                  </button>
                )}
              </div>
              <p className="text-xs text-gray-500 mt-1">
                Fully masked. Never stored in plain text.
              </p>
            </div>
          </div>

          {/* Webhook Health Panel */}
          <div className="pt-4 border-t border-gray-200">
            <h4 className="text-sm font-medium text-gray-900 mb-3">Webhook Health</h4>
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-600">Status</span>
                {getWebhookHealthBadge()}
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-600">Last Event Received</span>
                <span className="text-gray-900">
                  {provider.webhook.lastEventReceived || 'Never'}
                </span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-600">Failed Events (24h)</span>
                <span className={`font-medium ${
                  provider.webhook.failedEventsLast24h > 0 ? 'text-red-600' : 'text-gray-900'
                }`}>
                  {provider.webhook.failedEventsLast24h}
                </span>
              </div>
              {provider.webhook.lastRetry && (
                <div className="flex items-center justify-between text-sm">
                  <span className="text-gray-600">Last Retry</span>
                  <span className="text-gray-900">{provider.webhook.lastRetry}</span>
                </div>
              )}
              <button className="text-xs text-purple-600 hover:text-purple-700 flex items-center gap-1 mt-2">
                View webhook logs
                <ExternalLink className="w-3 h-3" />
              </button>
            </div>
          </div>
        </div>

        {/* Right: Vendor Impact & Actions */}
        <div className="space-y-4">
          {/* Vendor Impact Summary */}
          <div className="bg-gray-50 rounded-lg p-4 border border-gray-200">
            <h4 className="text-sm font-medium text-gray-900 mb-3 flex items-center gap-2">
              <TrendingUp className="w-4 h-4" />
              Vendor Impact
            </h4>
            <div className="space-y-3">
              <div>
                <div className="text-2xl font-bold text-gray-900">
                  {provider.vendorImpact.vendorsUsing}
                </div>
                <div className="text-xs text-gray-600">Vendors using this provider</div>
              </div>
              <div>
                <div className="text-lg font-semibold text-gray-900">
                  {provider.vendorImpact.activeSubscriptions}
                </div>
                <div className="text-xs text-gray-600">Active subscriptions</div>
              </div>
              <div>
                <div className="text-lg font-semibold text-gray-900">
                  {provider.vendorImpact.paymentsLast24h}
                </div>
                <div className="text-xs text-gray-600">Payments (last 24h)</div>
              </div>
            </div>
          </div>

          {/* Action Buttons */}
          {isSuperAdmin && (
            <div className="space-y-2">
              {provider.status === 'active' ? (
                <button
                  onClick={onDeactivate}
                  disabled={provider.vendorImpact.vendorsUsing > 0}
                  className={`w-full px-4 py-2 text-sm font-medium rounded-lg ${
                    provider.vendorImpact.vendorsUsing > 0
                      ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                      : 'bg-red-600 text-white hover:bg-red-700'
                  }`}
                >
                  Deactivate Provider
                </button>
              ) : (
                <button
                  onClick={onActivate}
                  disabled={provider.status === 'disabled'}
                  className={`w-full px-4 py-2 text-sm font-medium rounded-lg ${
                    provider.status === 'disabled'
                      ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                      : 'bg-green-600 text-white hover:bg-green-700'
                  }`}
                >
                  Activate Provider
                </button>
              )}
              
              {provider.vendorImpact.vendorsUsing > 0 && provider.status === 'active' && (
                <p className="text-xs text-gray-500 text-center">
                  Cannot deactivate while vendors are using this provider
                </p>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// Environment Switch Modal
interface EnvironmentSwitchModalProps {
  provider: PaymentProvider;
  onClose: () => void;
  onConfirm: (newEnv: Environment) => void;
  confirmationText: string;
  setConfirmationText: (text: string) => void;
}

function EnvironmentSwitchModal({ 
  provider, 
  onClose, 
  onConfirm, 
  confirmationText, 
  setConfirmationText 
}: EnvironmentSwitchModalProps) {
  const targetEnv = provider.environment === 'test' ? 'live' : 'test';
  const isGoingLive = targetEnv === 'live';

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-lg w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className={`w-12 h-12 rounded-full flex items-center justify-center ${
            isGoingLive ? 'bg-red-100' : 'bg-blue-100'
          }`}>
            <AlertTriangle className={`w-6 h-6 ${
              isGoingLive ? 'text-red-600' : 'text-blue-600'
            }`} />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">
              Switch to {targetEnv === 'live' ? 'Live' : 'Test'} Environment?
            </h3>
            <p className="text-sm text-gray-600">{provider.name}</p>
          </div>
        </div>

        {isGoingLive && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <div className="flex items-start gap-2">
              <AlertTriangle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
              <div>
                <p className="text-sm font-medium text-red-900 mb-1">
                  This affects real payments platform-wide
                </p>
                <p className="text-sm text-red-800">
                  Switching to live mode will process real transactions for all {provider.vendorImpact.vendorsUsing} vendors using {provider.name}.
                </p>
              </div>
            </div>
          </div>
        )}

        <div className="mb-4">
          <p className="text-sm text-gray-700 mb-3">
            {isGoingLive 
              ? 'Type "SWITCH TO LIVE" to confirm this production change:'
              : 'This will switch the provider to test/sandbox mode.'
            }
          </p>
          {isGoingLive && (
            <Input
              value={confirmationText}
              onChange={(e) => setConfirmationText(e.target.value)}
              placeholder="SWITCH TO LIVE"
              className="font-mono"
            />
          )}
        </div>

        <div className="flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            onClick={() => onConfirm(targetEnv)}
            disabled={isGoingLive && confirmationText !== 'SWITCH TO LIVE'}
            className={`flex-1 px-4 py-2 text-sm font-medium rounded-lg ${
              isGoingLive && confirmationText !== 'SWITCH TO LIVE'
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                : isGoingLive
                  ? 'bg-red-600 text-white hover:bg-red-700'
                  : 'bg-blue-600 text-white hover:bg-blue-700'
            }`}
          >
            Switch Environment
          </button>
        </div>
      </div>
    </div>
  );
}

// Activate Provider Modal
interface ActivateProviderModalProps {
  provider: PaymentProvider;
  onClose: () => void;
  onConfirm: () => void;
}

function ActivateProviderModal({ provider, onClose, onConfirm }: ActivateProviderModalProps) {
  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-lg w-full p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
            <CheckCircle className="w-6 h-6 text-green-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">
              Activate Provider?
            </h3>
            <p className="text-sm text-gray-600">{provider.name}</p>
          </div>
        </div>

        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
          <div className="flex items-start gap-2">
            <Info className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-medium text-blue-900 mb-1">
                Platform-Wide Activation
              </p>
              <p className="text-sm text-blue-800">
                This will make {provider.name} available to all vendors on the platform. 
                Vendors can then choose to enable it in their payment settings.
              </p>
            </div>
          </div>
        </div>

        <div className="mb-4">
          <h4 className="text-sm font-medium text-gray-900 mb-2">Readiness Checklist</h4>
          <div className="space-y-2">
            <div className="flex items-center gap-2 text-sm">
              <CheckCircle className="w-4 h-4 text-green-600" />
              <span className="text-gray-700">Credentials configured</span>
            </div>
            <div className="flex items-center gap-2 text-sm">
              <CheckCircle className="w-4 h-4 text-green-600" />
              <span className="text-gray-700">Environment: {provider.environment}</span>
            </div>
            <div className="flex items-center gap-2 text-sm">
              {provider.webhook.health === 'healthy' ? (
                <CheckCircle className="w-4 h-4 text-green-600" />
              ) : (
                <AlertTriangle className="w-4 h-4 text-amber-600" />
              )}
              <span className="text-gray-700">Webhook status: {provider.webhook.health}</span>
            </div>
          </div>
        </div>

        <div className="flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-100"
          >
            Cancel
          </button>
          <button
            onClick={onConfirm}
            className="flex-1 px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700"
          >
            Activate for All Vendors
          </button>
        </div>
      </div>
    </div>
  );
}
