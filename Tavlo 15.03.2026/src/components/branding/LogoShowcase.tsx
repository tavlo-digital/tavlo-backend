import { TavloLogo } from './TavloLogo';

export function LogoShowcase() {
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-6 py-8">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl mb-2">TAVLO Brand Assets</h1>
              <p className="text-gray-600">Bold Uppercase Wordmark System</p>
            </div>
            <a 
              href="#view-concepts"
              onClick={(e) => {
                e.preventDefault()
                // Dispatch event to switch to concepts view
                window.dispatchEvent(new CustomEvent('viewLogoConcepts'));
              }}
              className="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm"
            >
              View Full Brand Guide →
            </a>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-6 py-12 space-y-16">
        
        {/* Primary Logo */}
        <section>
          <h2 className="text-xl mb-6 text-gray-900">Primary Logo</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            {/* Light background */}
            <div className="bg-white rounded-xl p-12 shadow-sm border border-gray-200">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Black on Light</div>
              <div className="flex justify-center">
                <TavloLogo variant="full" theme="light" colorScheme="black" size={140} />
              </div>
              <div className="mt-8 pt-6 border-t border-gray-200 text-xs text-gray-500">
                Primary logo color - use on white or light backgrounds
              </div>
            </div>

            {/* Dark background */}
            <div className="bg-gray-900 rounded-xl p-12 shadow-sm border border-gray-800">
              <div className="text-xs uppercase tracking-wider text-gray-400 mb-6">White on Dark</div>
              <div className="flex justify-center">
                <TavloLogo variant="full" theme="light" colorScheme="white" size={140} />
              </div>
              <div className="mt-8 pt-6 border-t border-gray-800 text-xs text-gray-400">
                Use white on dark backgrounds
              </div>
            </div>
          </div>
        </section>

        {/* Brand Colors */}
        <section>
          <h2 className="text-xl mb-6 text-gray-900">Brand Color Versions</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            {/* Emerald */}
            <div className="bg-white rounded-xl p-12 shadow-sm border border-gray-200">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Emerald Brand Color</div>
              <div className="flex justify-center">
                <TavloLogo variant="full" theme="light" colorScheme="emerald" size={140} />
              </div>
            </div>

            {/* Terracotta */}
            <div className="bg-white rounded-xl p-12 shadow-sm border border-gray-200">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Terracotta Accent</div>
              <div className="flex justify-center">
                <TavloLogo variant="full" theme="light" colorScheme="terracotta" size={140} />
              </div>
            </div>
          </div>
        </section>

        {/* Icon Only */}
        <section>
          <h2 className="text-xl mb-6 text-gray-900">Icon Only – App Icon / QR Stickers</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {/* Large */}
            <div className="bg-white rounded-xl p-12 shadow-sm border border-gray-200">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Large (120px)</div>
              <div className="flex justify-center">
                <TavloLogo variant="icon" theme="light" colorScheme="emerald" size={120} />
              </div>
            </div>

            {/* Medium */}
            <div className="bg-white rounded-xl p-12 shadow-sm border border-gray-200">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Medium (64px)</div>
              <div className="flex justify-center">
                <TavloLogo variant="icon" theme="light" colorScheme="emerald" size={64} />
              </div>
            </div>

            {/* Small */}
            <div className="bg-white rounded-xl p-12 shadow-sm border border-gray-200">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Small (32px)</div>
              <div className="flex justify-center">
                <TavloLogo variant="icon" theme="light" colorScheme="emerald" size={32} />
              </div>
            </div>
          </div>
        </section>

        {/* Wordmark Only */}
        <section>
          <h2 className="text-xl mb-6 text-gray-900">Wordmark Only – Invoices / Documents</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div className="bg-white rounded-xl p-12 shadow-sm border border-gray-200">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Light Background</div>
              <div className="flex justify-center">
                <TavloLogo variant="wordmark" theme="light" colorScheme="emerald" size={180} />
              </div>
            </div>

            <div className="bg-gray-900 rounded-xl p-12 shadow-sm border border-gray-800">
              <div className="text-xs uppercase tracking-wider text-gray-400 mb-6">Dark Background</div>
              <div className="flex justify-center">
                <TavloLogo variant="wordmark" theme="dark" colorScheme="emerald" size={180} />
              </div>
            </div>
          </div>
        </section>

        {/* Size Variations */}
        <section>
          <h2 className="text-xl mb-6 text-gray-900">Size Scalability Test</h2>
          <div className="bg-white rounded-xl p-12 shadow-sm border border-gray-200">
            <div className="flex items-end gap-8 justify-center flex-wrap">
              <div className="text-center">
                <TavloLogo variant="full" theme="light" colorScheme="emerald" size={200} />
                <div className="text-xs text-gray-500 mt-4">200px</div>
              </div>
              <div className="text-center">
                <TavloLogo variant="full" theme="light" colorScheme="emerald" size={120} />
                <div className="text-xs text-gray-500 mt-4">120px</div>
              </div>
              <div className="text-center">
                <TavloLogo variant="full" theme="light" colorScheme="emerald" size={80} />
                <div className="text-xs text-gray-500 mt-4">80px</div>
              </div>
              <div className="text-center">
                <TavloLogo variant="full" theme="light" colorScheme="emerald" size={48} />
                <div className="text-xs text-gray-500 mt-4">48px</div>
              </div>
            </div>
          </div>
        </section>

        {/* Usage Examples */}
        <section>
          <h2 className="text-xl mb-6 text-gray-900">Usage Examples</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            {/* QR Sticker Example */}
            <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
              <div className="bg-gray-50 px-6 py-3 border-b border-gray-200">
                <div className="text-sm text-gray-600">Table QR Sticker</div>
              </div>
              <div className="p-8 flex flex-col items-center">
                <TavloLogo variant="icon" theme="light" colorScheme="emerald" size={72} />
                <div className="mt-6 w-32 h-32 bg-gray-100 rounded-lg flex items-center justify-center">
                  <div className="text-xs text-gray-400">QR Code</div>
                </div>
                <div className="mt-4 text-center">
                  <div className="text-sm">Scan to order</div>
                  <div className="text-xs text-gray-500 mt-1">Table 12</div>
                </div>
              </div>
            </div>

            {/* Invoice Header Example */}
            <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
              <div className="bg-gray-50 px-6 py-3 border-b border-gray-200">
                <div className="text-sm text-gray-600">Invoice Header</div>
              </div>
              <div className="p-8">
                <div className="flex justify-between items-start mb-6">
                  <TavloLogo variant="wordmark" theme="light" colorScheme="emerald" size={120} />
                  <div className="text-right text-sm">
                    <div className="text-gray-900">Invoice #1234</div>
                    <div className="text-gray-500">14.12.2024</div>
                  </div>
                </div>
                <div className="border-t border-gray-200 pt-4 space-y-2 text-xs text-gray-600">
                  <div>Bella Cucina Restaurant</div>
                  <div>Hauptstraße 123, 1010 Vienna</div>
                  <div>UID: ATU12345678</div>
                </div>
              </div>
            </div>

            {/* Mobile App Icon */}
            <div className="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
              <div className="bg-gray-800/50 px-6 py-3 border-b border-gray-700">
                <div className="text-sm text-gray-300">Mobile App Icon</div>
              </div>
              <div className="p-8 flex flex-col items-center">
                <div className="bg-white rounded-2xl shadow-xl p-1">
                  <TavloLogo variant="icon" theme="light" colorScheme="emerald" size={80} />
                </div>
                <div className="mt-4 text-white text-sm">TAVLO</div>
              </div>
            </div>

            {/* Vendor Dashboard */}
            <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
              <div className="bg-gray-50 px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                <TavloLogo variant="full" theme="light" colorScheme="emerald" size={80} />
                <div className="flex items-center gap-2 text-sm text-gray-600">
                  <div className="w-8 h-8 bg-gray-200 rounded-full"></div>
                  <span>Vendor</span>
                </div>
              </div>
              <div className="p-8">
                <div className="text-sm text-gray-900 mb-2">Dashboard</div>
                <div className="text-xs text-gray-500">Welcome back to your restaurant platform</div>
              </div>
            </div>
          </div>
        </section>

        {/* Design Specifications */}
        <section className="bg-white rounded-xl p-8 shadow-sm border border-gray-200">
          <h2 className="text-xl mb-6 text-gray-900">Design Specifications</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
              <h3 className="text-sm mb-4 text-gray-900">Color Palette</h3>
              <div className="space-y-3">
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 rounded-lg" style={{ backgroundColor: '#0F5257' }}></div>
                  <div>
                    <div className="text-sm">Emerald (Light)</div>
                    <div className="text-xs text-gray-500 font-mono">#0F5257</div>
                  </div>
                </div>
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 rounded-lg" style={{ backgroundColor: '#1A8B94' }}></div>
                  <div>
                    <div className="text-sm">Emerald (Dark)</div>
                    <div className="text-xs text-gray-500 font-mono">#1A8B94</div>
                  </div>
                </div>
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 rounded-lg" style={{ backgroundColor: '#B85C3F' }}></div>
                  <div>
                    <div className="text-sm">Terracotta (Light)</div>
                    <div className="text-xs text-gray-500 font-mono">#B85C3F</div>
                  </div>
                </div>
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 rounded-lg" style={{ backgroundColor: '#D97B5F' }}></div>
                  <div>
                    <div className="text-sm">Terracotta (Dark)</div>
                    <div className="text-xs text-gray-500 font-mono">#D97B5F</div>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <h3 className="text-sm mb-4 text-gray-900">Design Principles</h3>
              <ul className="space-y-2 text-sm text-gray-600">
                <li>✓ Flat design, no shadows or gradients</li>
                <li>✓ Minimal, timeless aesthetic</li>
                <li>✓ Abstract representation, not literal</li>
                <li>✓ Scalable from 16px to print</li>
                <li>✓ Professional, infrastructure-level feel</li>
                <li>✓ Trustworthy and calm</li>
                <li>✓ Modern humanist sans-serif</li>
                <li>✓ Increased letter spacing (0.05em)</li>
              </ul>
            </div>
          </div>
        </section>

        {/* Symbol Meaning */}
        <section className="bg-white rounded-xl p-8 shadow-sm border border-gray-200">
          <h2 className="text-xl mb-4 text-gray-900">Logo Design</h2>
          <div className="flex items-start gap-8">
            <div className="flex-shrink-0">
              <TavloLogo variant="full" theme="light" colorScheme="black" size={120} />
            </div>
            <div className="space-y-4 text-sm text-gray-600">
              <p>
                <strong className="text-gray-900">Bold Wordmark:</strong> Ultra-heavy uppercase letterforms 
                create maximum impact and professional presence across all contexts.
              </p>
              <p>
                <strong className="text-gray-900">Geometric Sans-Serif:</strong> Clean, modern typeface 
                conveys confidence, strength, and reliability — essential for restaurant infrastructure.
              </p>
              <p>
                <strong className="text-gray-900">Black Primary Color:</strong> Professional default that 
                works on documents, invoices, and high-contrast applications.
              </p>
              <p>
                <strong className="text-gray-900">Versatile System:</strong> Works seamlessly from tiny 
                favicons to large format printing while maintaining clarity and recognition.
              </p>
            </div>
          </div>
        </section>

      </div>
    </div>
  );
}