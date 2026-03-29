import { User, Store } from 'lucide-react';

interface ModeSwitcherProps {
  mode: 'customer' | 'vendor';
  onModeChange: (mode: 'customer' | 'vendor') => void;
}

export function ModeSwitcher({ mode, onModeChange }: ModeSwitcherProps) {
  return (
    <div className="fixed bottom-4 right-4 z-50">
      <div className="bg-white rounded-full shadow-lg border p-1 flex gap-1">
        <button
          onClick={() => onModeChange('customer')}
          className={`px-4 py-2 rounded-full flex items-center gap-2 transition-colors ${
            mode === 'customer'
              ? 'bg-gray-900 text-white'
              : 'text-gray-600 hover:bg-gray-100'
          }`}
        >
          <User className="w-4 h-4" />
          <span className="text-sm">Customer</span>
        </button>
        <button
          onClick={() => onModeChange('vendor')}
          className={`px-4 py-2 rounded-full flex items-center gap-2 transition-colors ${
            mode === 'vendor'
              ? 'bg-gray-900 text-white'
              : 'text-gray-600 hover:bg-gray-100'
          }`}
        >
          <Store className="w-4 h-4" />
          <span className="text-sm">Vendor</span>
        </button>
      </div>
    </div>
  );
}
