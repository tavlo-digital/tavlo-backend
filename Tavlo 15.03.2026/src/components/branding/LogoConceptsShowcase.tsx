import { TavloLogo } from './TavloLogo';

export function LogoConceptsShowcase() {
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b">
        <div className="max-w-7xl mx-auto px-6 py-8">
          <h1 className="text-3xl mb-2">TAVLO Brand Identity</h1>
          <p className="text-gray-600">Bold Wordmark Logo System</p>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-6 py-12 space-y-16">
        
        {/* Logo Display */}
        <section>
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-12">
            <div className="flex items-center justify-center mb-8">
              <TavloLogo 
                variant="full" 
                theme="light" 
                colorScheme="black" 
                size={200}
              />
            </div>
            <div className="text-center text-gray-600 text-sm">
              The TAVLO logo features bold, uppercase letterforms with maximum impact and clarity
            </div>
          </div>
        </section>

        {/* Design Concept */}
        <section>
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
            <h2 className="text-2xl mb-6">Logo Concept</h2>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <div>
                <h3 className="text-lg mb-4">Design Elements</h3>
                <ul className="space-y-3 text-gray-600">
                  <li className="flex items-start gap-2">
                    <span className="text-emerald-600 mt-1">✓</span>
                    <span><strong>Bold uppercase wordmark</strong> - Maximum impact and professional presence</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="text-emerald-600 mt-1">✓</span>
                    <span><strong>Heavy geometric letterforms</strong> - Strong, confident, memorable</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="text-emerald-600 mt-1">✓</span>
                    <span><strong>Ultra-bold weight</strong> - Commands attention at all sizes</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="text-emerald-600 mt-1">✓</span>
                    <span><strong>Clean, modern aesthetic</strong> - Timeless and professional</span>
                  </li>
                </ul>
              </div>
              <div>
                <h3 className="text-lg mb-4">Brand Attributes</h3>
                <ul className="space-y-3 text-gray-600">
                  <li className="flex items-start gap-2">
                    <span className="text-emerald-600 mt-1">•</span>
                    <span><strong>Bold & Confident</strong> - Heavy letterforms convey strength</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="text-emerald-600 mt-1">•</span>
                    <span><strong>Professional</strong> - Infrastructure-level platform aesthetic</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="text-emerald-600 mt-1">•</span>
                    <span><strong>Modern & Clean</strong> - Contemporary design language</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="text-emerald-600 mt-1">•</span>
                    <span><strong>Memorable</strong> - Strong presence in all contexts</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        {/* Primary Logo Variations */}
        <section>
          <h2 className="text-2xl mb-6">Logo Variations</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            {/* Black */}
            <div className="bg-white rounded-xl shadow border border-gray-200 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Primary - Black</div>
              <div className="flex items-center justify-center min-h-[120px] bg-gray-50 rounded-lg p-6">
                <TavloLogo 
                  variant="full" 
                  theme="light" 
                  colorScheme="black" 
                  size={140}
                />
              </div>
            </div>

            {/* Emerald */}
            <div className="bg-white rounded-xl shadow border border-gray-200 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Brand Color - Emerald</div>
              <div className="flex items-center justify-center min-h-[120px] bg-gray-50 rounded-lg p-6">
                <TavloLogo 
                  variant="full" 
                  theme="light" 
                  colorScheme="emerald" 
                  size={140}
                />
              </div>
            </div>

            {/* White */}
            <div className="bg-gray-900 rounded-xl shadow border border-gray-800 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-400 mb-6">Dark Backgrounds - White</div>
              <div className="flex items-center justify-center min-h-[120px] bg-gray-800 rounded-lg p-6">
                <TavloLogo 
                  variant="full" 
                  theme="light" 
                  colorScheme="white" 
                  size={140}
                />
              </div>
            </div>

            {/* Terracotta */}
            <div className="bg-white rounded-xl shadow border border-gray-200 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Accent - Terracotta</div>
              <div className="flex items-center justify-center min-h-[120px] bg-gray-50 rounded-lg p-6">
                <TavloLogo 
                  variant="full" 
                  theme="light" 
                  colorScheme="terracotta" 
                  size={140}
                />
              </div>
            </div>

            {/* Icon */}
            <div className="bg-white rounded-xl shadow border border-gray-200 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Icon Version</div>
              <div className="flex items-center justify-center min-h-[120px] bg-gray-50 rounded-lg p-6">
                <TavloLogo 
                  variant="icon" 
                  theme="light" 
                  colorScheme="emerald" 
                  size={100}
                />
              </div>
            </div>

            {/* Dark Emerald */}
            <div className="bg-gray-900 rounded-xl shadow border border-gray-800 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-400 mb-6">Dark Theme - Emerald</div>
              <div className="flex items-center justify-center min-h-[120px] bg-gray-800 rounded-lg p-6">
                <TavloLogo 
                  variant="full" 
                  theme="dark" 
                  colorScheme="emerald" 
                  size={140}
                />
              </div>
            </div>
          </div>
        </section>

        {/* Size Scalability */}
        <section>
          <h2 className="text-2xl mb-6">Size Scalability</h2>
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
            <div className="text-sm text-gray-600 mb-6">
              The wordmark maintains clarity and impact at all sizes.
            </div>
            <div className="flex items-end gap-8 flex-wrap justify-center">
              <div className="text-center">
                <div className="bg-gray-50 rounded-lg p-6 inline-block">
                  <TavloLogo 
                    variant="full" 
                    theme="light" 
                    colorScheme="black" 
                    size={200}
                  />
                </div>
                <div className="text-xs text-gray-500 mt-3">Large</div>
              </div>
              <div className="text-center">
                <div className="bg-gray-50 rounded-lg p-4 inline-block">
                  <TavloLogo 
                    variant="full" 
                    theme="light" 
                    colorScheme="black" 
                    size={140}
                  />
                </div>
                <div className="text-xs text-gray-500 mt-3">Medium</div>
              </div>
              <div className="text-center">
                <div className="bg-gray-50 rounded-lg p-3 inline-block">
                  <TavloLogo 
                    variant="full" 
                    theme="light" 
                    colorScheme="black" 
                    size={80}
                  />
                </div>
                <div className="text-xs text-gray-500 mt-3">Small</div>
              </div>
            </div>
          </div>
        </section>

        {/* Usage Examples */}
        <section>
          <h2 className="text-2xl mb-6">Real-World Applications</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            {/* Website Header */}
            <div className="bg-white rounded-xl shadow border border-gray-200 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Website Header</div>
              <div className="flex flex-col justify-center min-h-[200px] bg-gray-50 rounded-lg p-6">
                <div className="flex items-center justify-between mb-6">
                  <TavloLogo 
                    variant="full" 
                    theme="light" 
                    colorScheme="emerald" 
                    size={80}
                  />
                  <div className="flex gap-2 text-xs">
                    <button className="px-3 py-1.5 hover:bg-gray-200 rounded">Login</button>
                    <button className="px-3 py-1.5 bg-emerald-600 text-white rounded">Sign Up</button>
                  </div>
                </div>
              </div>
            </div>

            {/* App Icon */}
            <div className="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl shadow p-8">
              <div className="text-xs uppercase tracking-wider text-gray-400 mb-6">Mobile App Icon</div>
              <div className="flex flex-col items-center justify-center min-h-[200px]">
                <TavloLogo 
                  variant="icon" 
                  theme="light" 
                  colorScheme="emerald" 
                  size={100}
                />
                <div className="text-white text-xs mt-4 text-center">iOS/Android</div>
              </div>
            </div>

            {/* Invoice */}
            <div className="bg-white rounded-xl shadow border border-gray-200 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Invoice Header</div>
              <div className="flex flex-col justify-center min-h-[200px] border-2 border-gray-200 rounded-lg p-6 bg-gray-50">
                <div className="mb-4 pb-4 border-b border-gray-300">
                  <TavloLogo 
                    variant="full" 
                    theme="light" 
                    colorScheme="black" 
                    size={70}
                  />
                </div>
                <div className="text-xs text-gray-700 space-y-1">
                  <div className="font-semibold">Invoice #2024-0542</div>
                  <div>Bella Cucina Restaurant</div>
                </div>
              </div>
            </div>

            {/* QR Sticker */}
            <div className="bg-white rounded-xl shadow border border-gray-200 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-500 mb-6">Table QR Sticker</div>
              <div className="flex flex-col items-center justify-center min-h-[200px]">
                <TavloLogo 
                  variant="icon" 
                  theme="light" 
                  colorScheme="emerald" 
                  size={60}
                />
                <div className="mt-4 w-28 h-28 bg-gray-100 rounded-lg flex items-center justify-center border-2 border-gray-300">
                  <div className="text-xs text-gray-400 font-mono">QR CODE</div>
                </div>
                <div className="text-xs text-gray-600 mt-3">Scan • Table 12</div>
              </div>
            </div>

            {/* Dashboard */}
            <div className="bg-gray-900 rounded-xl shadow border border-gray-800 p-8">
              <div className="text-xs uppercase tracking-wider text-gray-400 mb-6">Vendor Dashboard</div>
              <div className="flex flex-col min-h-[200px] bg-gray-800 rounded-lg p-6">
                <div className="mb-6">
                  <TavloLogo 
                    variant="full" 
                    theme="dark" 
                    colorScheme="emerald" 
                    size={80}
                  />
                </div>
                <div className="space-y-2 text-xs text-gray-400">
                  <div className="px-3 py-2 bg-gray-700 rounded">Dashboard</div>
                  <div className="px-3 py-2">Orders</div>
                </div>
              </div>
            </div>

            {/* Marketing */}
            <div className="bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-xl shadow p-8">
              <div className="text-xs uppercase tracking-wider text-emerald-200 mb-6">Marketing Materials</div>
              <div className="flex flex-col items-center justify-center min-h-[200px] text-white text-center">
                <TavloLogo 
                  variant="full" 
                  theme="light" 
                  colorScheme="white" 
                  size={100}
                />
                <div className="mt-6 text-sm text-emerald-100">Restaurant Platform<br/>That Works</div>
              </div>
            </div>
          </div>
        </section>

        {/* Color Palette */}
        <section>
          <h2 className="text-2xl mb-6">Brand Color Palette</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            {/* Primary */}
            <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
              <h3 className="text-lg mb-4">Primary: Black</h3>
              <div className="flex items-center gap-4 mb-4">
                <div className="w-20 h-20 rounded-lg bg-black"></div>
                <div>
                  <div className="text-sm font-semibold">#000000</div>
                  <div className="text-xs text-gray-500">Primary Logo Color</div>
                </div>
              </div>
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-sm text-gray-700">
                  <strong>Usage:</strong> Default logo color, professional documents, high-contrast applications
                </div>
              </div>
            </div>

            {/* Emerald */}
            <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
              <h3 className="text-lg mb-4">Brand: Emerald</h3>
              <div className="space-y-3">
                <div className="flex items-center gap-4">
                  <div className="w-20 h-20 rounded-lg" style={{ backgroundColor: '#0F5257' }}></div>
                  <div>
                    <div className="text-sm font-semibold">#0F5257</div>
                    <div className="text-xs text-gray-500">Light Theme</div>
                  </div>
                </div>
                <div className="flex items-center gap-4">
                  <div className="w-20 h-20 rounded-lg" style={{ backgroundColor: '#1A8B94' }}></div>
                  <div>
                    <div className="text-sm font-semibold">#1A8B94</div>
                    <div className="text-xs text-gray-500">Dark Theme</div>
                  </div>
                </div>
              </div>
              <div className="mt-4 p-4 bg-emerald-50 rounded-lg">
                <div className="text-sm text-emerald-900">
                  <strong>Usage:</strong> Platform branding, customer apps, primary CTAs
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Usage Guidelines */}
        <section>
          <div className="bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
            <h2 className="text-xl mb-6">Logo Usage Guidelines</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div>
                <h3 className="text-sm mb-4 text-emerald-600">✓ Do</h3>
                <ul className="space-y-2 text-sm text-gray-600">
                  <li>• Use black as primary logo color</li>
                  <li>• Use emerald for branded applications</li>
                  <li>• Maintain proper clear space around logo</li>
                  <li>• Scale proportionally</li>
                  <li>• Use white on dark backgrounds</li>
                  <li>• Ensure high contrast with background</li>
                  <li>• Keep letterforms crisp and bold</li>
                </ul>
              </div>
              <div>
                <h3 className="text-sm mb-4 text-red-600">✗ Don't</h3>
                <ul className="space-y-2 text-sm text-gray-600">
                  <li>• Distort or stretch the wordmark</li>
                  <li>• Use light/thin font weights</li>
                  <li>• Add outlines or effects</li>
                  <li>• Change letter spacing</li>
                  <li>• Use on low-contrast backgrounds</li>
                  <li>• Rotate or tilt the logo</li>
                  <li>• Recreate using different fonts</li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        {/* Design Philosophy */}
        <section>
          <div className="bg-emerald-50 border-2 border-emerald-200 rounded-2xl p-8">
            <h2 className="text-2xl text-emerald-900 mb-4">Design Philosophy</h2>
            <div className="space-y-4 text-emerald-900">
              <p>
                The TAVLO wordmark uses <strong>ultra-bold uppercase letterforms</strong> to create a commanding, 
                professional presence that reflects the platform's role as critical restaurant infrastructure.
              </p>
              <p>
                The <strong>heavy geometric weight</strong> conveys strength, reliability, and confidence — essential 
                qualities for a platform handling orders, payments, and customer data.
              </p>
              <p>
                <strong>Black as the primary color</strong> reinforces professionalism and seriousness, while 
                emerald green adds warmth and brand personality in customer-facing contexts.
              </p>
              <p>
                The wordmark is designed to work across all touchpoints: from tiny favicons to large format 
                printing, from legal invoices to marketing billboards. Bold simplicity ensures maximum impact 
                and recognition.
              </p>
            </div>
          </div>
        </section>

      </div>
    </div>
  );
}
