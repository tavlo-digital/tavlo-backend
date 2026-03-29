import { ArrowLeft, Store, ShoppingCart, CreditCard, Repeat, QrCode } from 'lucide-react';

interface SearchResult {
  type: 'vendor' | 'order' | 'payment' | 'subscription' | 'qr';
  id: string;
  title: string;
  subtitle?: string;
  metadata?: string;
}

interface AdminSearchResultsProps {
  query: string;
  results: SearchResult[];
  onBack: () => void;
  onSelectResult: (result: SearchResult) => void;
}

export function AdminSearchResults({ query, results, onBack, onSelectResult }: AdminSearchResultsProps) {
  // Group results by type
  const groupedResults = results.reduce((acc, result) => {
    if (!acc[result.type]) {
      acc[result.type] = [];
    }
    acc[result.type].push(result);
    return acc;
  }, {} as Record<string, SearchResult[]>);

  const typeConfig = {
    vendor: { icon: Store, label: 'Vendors', color: 'text-purple-600' },
    order: { icon: ShoppingCart, label: 'Orders', color: 'text-blue-600' },
    payment: { icon: CreditCard, label: 'Payments', color: 'text-green-600' },
    subscription: { icon: Repeat, label: 'Subscriptions', color: 'text-orange-600' },
    qr: { icon: QrCode, label: 'QR Codes', color: 'text-pink-600' },
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-[1400px] mx-auto px-6 py-6">
        {/* Header */}
        <div className="mb-6">
          <button
            onClick={onBack}
            className="flex items-center gap-2 text-gray-600 hover:text-gray-900 mb-4"
          >
            <ArrowLeft className="w-4 h-4" />
            Back to Dashboard
          </button>
          
          <h1 className="text-2xl font-semibold text-gray-900 mb-2">
            Search Results
          </h1>
          <p className="text-sm text-gray-600">
            Found {results.length} result{results.length !== 1 ? 's' : ''} for "{query}"
          </p>
        </div>

        {/* Results grouped by type */}
        {results.length === 0 ? (
          <div className="bg-white border border-gray-200 rounded-lg p-12 text-center">
            <p className="text-gray-500">No results found for "{query}"</p>
            <p className="text-sm text-gray-400 mt-2">
              Try searching by: vendor name/ID, order ID, payment ID, subscription ID, or QR ID
            </p>
          </div>
        ) : (
          <div className="space-y-6">
            {Object.entries(groupedResults).map(([type, items]) => {
              const config = typeConfig[type as keyof typeof typeConfig];
              const Icon = config.icon;
              
              return (
                <div key={type}>
                  <div className="flex items-center gap-2 mb-3">
                    <Icon className={`w-5 h-5 ${config.color}`} />
                    <h2 className="text-lg font-medium text-gray-900">
                      {config.label} ({items.length})
                    </h2>
                  </div>
                  
                  <div className="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100">
                    {items.map((result) => (
                      <button
                        key={result.id}
                        onClick={() => onSelectResult(result)}
                        className="w-full p-4 text-left hover:bg-gray-50 transition-colors"
                      >
                        <div className="flex items-start justify-between">
                          <div>
                            <h3 className="font-medium text-gray-900 mb-1">
                              {result.title}
                            </h3>
                            {result.subtitle && (
                              <p className="text-sm text-gray-600">{result.subtitle}</p>
                            )}
                            <p className="text-xs text-gray-500 font-mono mt-1">
                              ID: {result.id}
                            </p>
                          </div>
                          {result.metadata && (
                            <span className="text-xs text-gray-500">{result.metadata}</span>
                          )}
                        </div>
                      </button>
                    ))}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
