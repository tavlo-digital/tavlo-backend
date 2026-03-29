import { useState } from 'react';
import { Search, Store, Globe, LogIn, UserPlus, CheckCircle, QrCode, ShoppingCart, CreditCard, Utensils, BarChart3, Users, Languages, Settings, TrendingUp, Shield, Lock, Server, Clock, Award, ChevronDown, X, Mail, MessageCircle, User, LogOut } from 'lucide-react';
import { TavloLogo } from './branding/TavloLogo';
import { Button } from './ui/button';
import { Card, CardContent } from './ui/card';
import { AuthForm } from './AuthForm';
import { toast } from 'sonner@2.0.3';

type FooterSection = 
  | 'about'
  | 'how-it-works'
  | 'features'
  | 'pricing'
  | 'for-restaurants'
  | 'for-guests'
  | 'contact'
  | 'help'
  | 'faq'
  | 'terms'
  | 'privacy'
  | 'cookies'
  | 'imprint'
  | null;

interface TavloPlatformPageProps {
  onNavigateToBrowse: () => void;
  onNavigateToVendorOnboarding?: () => void;
  onNavigateToAccount?: () => void;
}

export function TavloPlatformPage({ onNavigateToBrowse, onNavigateToVendorOnboarding, onNavigateToAccount }: TavloPlatformPageProps) {
  const [language, setLanguage] = useState('EN');
  const [showLanguageDropdown, setShowLanguageDropdown] = useState(false);
  const [activeFooterSection, setActiveFooterSection] = useState<FooterSection>(null);
  const [user, setUser] = useState<any>(null);
  const [showAuthModal, setShowAuthModal] = useState(false);
  const [authMode, setAuthMode] = useState<'login' | 'register'>('login');
  const [showUserMenu, setShowUserMenu] = useState(false);

  const languages = ['EN', 'DE', 'AR', 'FR', 'ES', 'IT', 'TR', 'RO', 'PL', 'UK', 'RU', 'ZH'];

  // Convert language code to lowercase for AuthForm
  const languageCode = language.toLowerCase();

  const handleLogin = () => {
    setAuthMode('login');
    setShowAuthModal(true);
  };

  const handleRegister = () => {
    setAuthMode('register');
    setShowAuthModal(true);
  };

  const handleAuthSuccess = (userData: any) => {
    setUser(userData);
    setShowAuthModal(false);
    toast.success(authMode === 'login' ? 'Welcome back!' : 'Account created successfully!');
  };

  const handleLogout = () => {
    setUser(null);
    setShowUserMenu(false);
    toast.success('Logged out successfully');
  };

  // Convert 'login' to 'signin' for AuthForm
  const authFormMode = authMode === 'login' ? 'signin' : 'register';

  const scrollToVendorSection = () => {
    const section = document.getElementById('for-restaurants');
    if (section) {
      section.scrollIntoView({ behavior: 'smooth' });
    }
  };

  const openFooterModal = (section: FooterSection) => {
    setActiveFooterSection(section);
    document.body.style.overflow = 'hidden';
  };

  const closeFooterModal = () => {
    setActiveFooterSection(null);
    document.body.style.overflow = 'auto';
  };

  return (
    <div className="min-h-screen bg-white">
      {/* MINIMAL HEADER - Non-dominant, sticky */}
      <header className="sticky top-0 z-50 bg-white/95 backdrop-blur-sm border-b border-gray-100">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-14">
            {/* Small wordmark */}
            <div className="flex-shrink-0">
              <TavloLogo variant="wordmark" size={32} colorScheme="black" />
            </div>

            {/* Right navigation */}
            <div className="flex items-center gap-6">
              <nav className="hidden md:flex items-center gap-6">
                <button onClick={onNavigateToBrowse} className="text-sm text-gray-600 hover:text-gray-900 transition-colors">
                  Find Restaurants
                </button>
                <a href="#for-restaurants" className="text-sm text-gray-900 hover:text-black font-semibold transition-colors">
                  For Restaurants
                </a>
              </nav>

              <div className="flex items-center gap-3">
                {/* Minimal language selector */}
                <div className="relative">
                  <button
                    onClick={() => setShowLanguageDropdown(!showLanguageDropdown)}
                    className="flex items-center gap-1 px-2 py-1 text-xs text-gray-600 hover:text-gray-900 transition-colors"
                  >
                    <Globe className="w-3 h-3" />
                    <span>{language}</span>
                    <ChevronDown className="w-3 h-3" />
                  </button>
                  {showLanguageDropdown && (
                    <div className="absolute right-0 mt-2 w-32 bg-white border border-gray-200 rounded-lg shadow-lg py-1 grid grid-cols-2 gap-1 p-2 z-10">
                      {languages.map((lang) => (
                        <button
                          key={lang}
                          onClick={() => {
                            setLanguage(lang);
                            setShowLanguageDropdown(false);
                          }}
                          className={`px-2 py-1 text-xs rounded ${
                            language === lang
                              ? 'bg-gray-900 text-white'
                              : 'text-gray-700 hover:bg-gray-100'
                          }`}
                        >
                          {lang}
                        </button>
                      ))}
                    </div>
                  )}
                </div>

                {user ? (
                  <div className="relative">
                    <button
                      onClick={() => setShowUserMenu(!showUserMenu)}
                      className="flex items-center gap-1 px-2 py-1 text-xs text-gray-600 hover:text-gray-900 transition-colors"
                    >
                      <User className="w-3 h-3 mr-1" />
                      {user.name}
                      <ChevronDown className="w-3 h-3" />
                    </button>
                    {showUserMenu && (
                      <div className="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-10">
                        <button
                          onClick={() => {
                            setShowUserMenu(false);
                            onNavigateToAccount?.();
                          }}
                          className="w-full px-3 py-2 text-xs text-left rounded text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                        >
                          <User className="w-3 h-3" />
                          My Account
                        </button>
                        <button
                          onClick={handleLogout}
                          className="w-full px-3 py-2 text-xs text-left rounded text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                        >
                          <LogOut className="w-3 h-3" />
                          Logout
                        </button>
                      </div>
                    )}
                  </div>
                ) : (
                  <>
                    <Button variant="ghost" size="sm" className="hidden sm:inline-flex text-xs h-8" onClick={handleLogin}>
                      <LogIn className="w-3 h-3 mr-1" />
                      Login
                    </Button>
                    <Button size="sm" className="bg-gray-900 hover:bg-black text-white text-xs h-8" onClick={handleRegister}>
                      Sign Up
                    </Button>
                  </>
                )}
              </div>
            </div>
          </div>
        </div>
      </header>

      {/* HERO SECTION - Large centered logo, calm layout */}
      <section className="py-24 px-4 sm:px-6 lg:px-8 bg-white">
        <div className="max-w-3xl mx-auto text-center">
          {/* Large centered logo */}
          <div className="mb-12 flex justify-center">
            <TavloLogo className="w-48 h-48 md:w-64 md:h-64" style={{ filter: 'brightness(0) saturate(100%) invert(5%) sepia(8%) saturate(3211%) hue-rotate(186deg) brightness(96%) contrast(97%)' }} />
          </div>

          {/* Headline */}
          <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-5 tracking-tight">
            Infrastructure for Modern Restaurants
          </h2>

          {/* Subheadline */}
          <p className="text-lg md:text-xl text-gray-600 mb-3 max-w-2xl mx-auto leading-relaxed">
            Ordering, payments, reservations, and loyalty — connected in one calm, reliable platform.
          </p>
          <p className="text-base text-gray-500 mb-2">
            Scan, order, and pay without apps. Built for modern dining.
          </p>

          {/* Supporting line */}
          <p className="text-sm text-gray-400 mb-10 italic">
            For guests who want speed. For restaurants that want control.
          </p>

          {/* CTAs - simple and balanced */}
          <div className="flex flex-col sm:flex-row items-center justify-center gap-3 mb-12">
            <Button onClick={onNavigateToBrowse} className="bg-gray-900 hover:bg-black text-white px-7 py-5 text-base h-auto">
              <Search className="w-4 h-4 mr-2" />
              Find Restaurants
            </Button>
            <Button onClick={scrollToVendorSection} variant="outline" className="border-gray-900 text-gray-900 hover:bg-gray-900 hover:text-white px-7 py-5 text-base h-auto">
              <Store className="w-4 h-4 mr-2" />
              For Restaurant Owners
            </Button>
          </div>

          {/* Trust stats - integrated into hero */}
          <div className="grid grid-cols-3 gap-6 max-w-xl mx-auto pt-8 border-t border-gray-100">
            <div>
              <div className="text-3xl font-bold text-gray-900 mb-1">12</div>
              <div className="text-xs text-gray-500 uppercase tracking-wide">Languages</div>
            </div>
            <div>
              <div className="text-3xl font-bold text-gray-900 mb-1">500+</div>
              <div className="text-xs text-gray-500 uppercase tracking-wide">Restaurants</div>
            </div>
            <div>
              <div className="text-3xl font-bold text-gray-900 mb-1">50K+</div>
              <div className="text-xs text-gray-500 uppercase tracking-wide">Orders</div>
            </div>
          </div>
        </div>
      </section>

      {/* TRANSITION LINE */}
      <div className="py-8 px-4 text-center">
        <p className="text-sm text-gray-400">Are you a guest or a restaurant owner?</p>
      </div>

      {/* CHOOSE YOUR PATH - Refined, not loud */}
      <section className="py-16 px-4 sm:px-6 lg:px-8 bg-white">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-gray-900 mb-3">Choose Your Path</h2>
            <p className="text-lg text-gray-600">Discover how Tavlo fits your dining experience.</p>
          </div>

          <div className="grid md:grid-cols-2 gap-6">
            {/* CUSTOMER CARD - Soft */}
            <Card className="border border-gray-200 hover:border-gray-300 transition-all">
              <CardContent className="p-7">
                <div className="mb-5">
                  <Search className="w-10 h-10 text-gray-700 mb-3" />
                  <h3 className="text-xl font-bold text-gray-900 mb-2">I'm a Customer</h3>
                  <p className="text-gray-600 text-sm leading-relaxed">
                    Discover nearby restaurants and enjoy dining without waiting, apps, or confusion.
                  </p>
                </div>

                <ul className="space-y-2 mb-6">
                  <li className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-gray-700 flex-shrink-0 mt-0.5" />
                    <span className="text-sm text-gray-700">Scan QR & order instantly</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-gray-700 flex-shrink-0 mt-0.5" />
                    <span className="text-sm text-gray-700">Split bills and pay in seconds</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-gray-700 flex-shrink-0 mt-0.5" />
                    <span className="text-sm text-gray-700">Dine in, takeaway, or reserve a table</span>
                  </li>
                </ul>

                <Button className="w-full bg-gray-900 hover:bg-black text-white py-5 text-sm h-auto">
                  Find Restaurants
                </Button>
              </CardContent>
            </Card>

            {/* RESTAURANT CARD - Slightly stronger */}
            <Card className="border-2 border-gray-900 bg-gray-50 hover:shadow-md transition-all">
              <CardContent className="p-7">
                <div className="mb-5">
                  <Store className="w-10 h-10 text-gray-900 mb-3" />
                  <h3 className="text-xl font-bold text-gray-900 mb-2">I'm a Restaurant</h3>
                  <p className="text-gray-700 text-sm leading-relaxed font-medium">
                    Digital tools for ordering, payments, analytics, and full control.
                  </p>
                </div>

                <ul className="space-y-2 mb-6">
                  <li className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-gray-900 flex-shrink-0 mt-0.5" />
                    <span className="text-sm text-gray-900 font-medium">No commission</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-gray-900 flex-shrink-0 mt-0.5" />
                    <span className="text-sm text-gray-900 font-medium">VAT-compliant receipts</span>
                  </li>
                  <li className="flex items-start gap-2">
                    <CheckCircle className="w-4 h-4 text-gray-900 flex-shrink-0 mt-0.5" />
                    <span className="text-sm text-gray-900 font-medium">Full control over menu & availability</span>
                  </li>
                </ul>

                <Button className="w-full bg-gray-900 hover:bg-black text-white py-5 text-sm h-auto font-semibold">
                  Get Started for Free
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* BUILT FOR REAL RESTAURANTS - Reduced density */}
      <section className="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-gray-900 mb-3">Built for Real Restaurants</h2>
            <p className="text-lg text-gray-600">A complete restaurant platform built for how modern dining works.</p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div className="bg-white p-5 rounded-lg border border-gray-200">
              <CheckCircle className="w-11 h-11 text-gray-900 mb-3" />
              <h3 className="text-base font-bold text-gray-900 mb-1">Digital Receipts</h3>
              <p className="text-sm text-gray-600">VAT-compliant with Austrian & German regulations.</p>
            </div>

            <div className="bg-white p-5 rounded-lg border border-gray-200">
              <CreditCard className="w-11 h-11 text-gray-900 mb-3" />
              <h3 className="text-base font-bold text-gray-900 mb-1">Payments</h3>
              <p className="text-sm text-gray-600">Cards, Apple Pay, Google Pay, and cash settlements.</p>
            </div>

            <div className="bg-white p-5 rounded-lg border border-gray-200">
              <QrCode className="w-11 h-11 text-gray-900 mb-3" />
              <h3 className="text-base font-bold text-gray-900 mb-1">QR Table Ordering</h3>
              <p className="text-sm text-gray-600">Scan, order, and pay without an app.</p>
            </div>

            <div className="bg-white p-5 rounded-lg border border-gray-200">
              <Settings className="w-11 h-11 text-gray-900 mb-3" />
              <h3 className="text-base font-bold text-gray-900 mb-1">Menu Control</h3>
              <p className="text-sm text-gray-600">Edit menus, prices, and availability in real time.</p>
            </div>

            <div className="bg-white p-5 rounded-lg border border-gray-200">
              <Languages className="w-11 h-11 text-gray-900 mb-3" />
              <h3 className="text-base font-bold text-gray-900 mb-1">Multi-language</h3>
              <p className="text-sm text-gray-600">Menus available in 12+ languages automatically.</p>
            </div>

            <div className="bg-white p-5 rounded-lg border border-gray-200">
              <Award className="w-11 h-11 text-gray-900 mb-3" />
              <h3 className="text-base font-bold text-gray-900 mb-1">Loyalty & Rewards</h3>
              <p className="text-sm text-gray-600">Create discounts and repeat visits easily.</p>
            </div>

            <div className="bg-white p-5 rounded-lg border border-gray-200">
              <Users className="w-11 h-11 text-gray-900 mb-3" />
              <h3 className="text-base font-bold text-gray-900 mb-1">Reviews & Ratings</h3>
              <p className="text-sm text-gray-600">Collect verified reviews from your dashboard.</p>
            </div>

            <div className="bg-white p-5 rounded-lg border border-gray-200">
              <UserPlus className="w-11 h-11 text-gray-900 mb-3" />
              <h3 className="text-base font-bold text-gray-900 mb-1">Guest & Auth Modes</h3>
              <p className="text-sm text-gray-600">Order as a guest or sign in — customer choice.</p>
            </div>
          </div>
        </div>
      </section>

      {/* FOR RESTAURANT OWNERS - Dark, serious, moved higher */}
      <section id="for-restaurants" className="py-20 px-4 sm:px-6 lg:px-8 bg-gray-900 text-white">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-14">
            <h2 className="text-3xl font-bold mb-3">For Restaurant Owners</h2>
            <p className="text-lg text-gray-300">Transform your restaurant with a complete digital ordering platform.</p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-7 mb-10">
            <div>
              <BarChart3 className="w-9 h-9 mb-3" />
              <h3 className="text-base font-bold mb-1">Complete Dashboard</h3>
              <p className="text-gray-400 text-sm">Manage orders, menus, pricing, and settings in one place.</p>
            </div>
            <div>
              <TrendingUp className="w-9 h-9 mb-3" />
              <h3 className="text-base font-bold mb-1">Analytics & Insights</h3>
              <p className="text-gray-400 text-sm">Track popular dishes, peak times, and revenue performance.</p>
            </div>
            <div>
              <Users className="w-9 h-9 mb-3" />
              <h3 className="text-base font-bold mb-1">Customer Management</h3>
              <p className="text-gray-400 text-sm">View reviews, loyalty activity, and customer preferences.</p>
            </div>
            <div>
              <Languages className="w-9 h-9 mb-3" />
              <h3 className="text-base font-bold mb-1">Multi-Language Menus</h3>
              <p className="text-gray-400 text-sm">Automatically translate menus for international guests.</p>
            </div>
            <div>
              <Settings className="w-9 h-9 mb-3" />
              <h3 className="text-base font-bold mb-1">Full Control</h3>
              <p className="text-gray-400 text-sm">Set prices, VAT rates, availability, and ordering rules.</p>
            </div>
            <div>
              <TrendingUp className="w-9 h-9 mb-3" />
              <h3 className="text-base font-bold mb-1">Increase Revenue</h3>
              <p className="text-gray-400 text-sm">Faster ordering, higher basket sizes, and repeat visits.</p>
            </div>
          </div>

          <div className="flex flex-wrap justify-center gap-6 mb-10 py-6 border-y border-gray-700">
            <div className="text-sm font-semibold">Quick setup</div>
            <div className="text-sm font-semibold">No commission</div>
            <div className="text-sm font-semibold">No app required</div>
          </div>

          <div className="text-center">
            <Button onClick={onNavigateToVendorOnboarding} className="bg-white text-gray-900 hover:bg-gray-100 px-7 py-5 text-base h-auto font-semibold mb-2">
              Get Started for Free
            </Button>
            <p className="text-xs text-gray-400 mb-2">No credit card required · Setup in minutes</p>
            <a href="#contact" onClick={(e) => { e.preventDefault(); openFooterModal('contact'); }} className="text-xs text-gray-300 hover:text-white underline inline-flex items-center gap-1">
              <MessageCircle className="w-3 h-3" />
              Or talk to us
            </a>
          </div>
        </div>
      </section>

      {/* HOW IT WORKS - Visual flow with emphasis */}
      <section className="py-16 px-4 sm:px-6 lg:px-8 bg-white">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-gray-900 mb-3">How It Works</h2>
            <p className="text-lg text-gray-600">Order in simple steps. No app. No hassle. No waiting.</p>
          </div>

          <div className="relative">
            {/* Connecting line */}
            <div className="hidden md:block absolute top-8 left-0 right-0 h-px bg-gray-200" style={{ zIndex: 0 }}></div>

            <div className="grid md:grid-cols-5 gap-6 relative" style={{ zIndex: 1 }}>
              {/* Step 1 - Emphasized */}
              <div className="text-center">
                <div className="w-14 h-14 bg-gray-900 text-white rounded-full flex items-center justify-center mx-auto mb-3 text-lg font-bold shadow-md ring-2 ring-gray-200">
                  1
                </div>
                <QrCode className="w-12 h-12 text-gray-900 mx-auto mb-2" />
                <h3 className="font-bold text-gray-900 mb-1 text-sm">Scan QR Code</h3>
                <p className="text-xs text-gray-600">Scan at your table.</p>
              </div>

              <div className="text-center">
                <div className="w-14 h-14 bg-gray-900 text-white rounded-full flex items-center justify-center mx-auto mb-3 text-lg font-bold">
                  2
                </div>
                <Utensils className="w-10 h-10 text-gray-700 mx-auto mb-2" />
                <h3 className="font-bold text-gray-900 mb-1 text-sm">Browse Menu</h3>
                <p className="text-xs text-gray-600">View in your language.</p>
              </div>

              <div className="text-center">
                <div className="w-14 h-14 bg-gray-900 text-white rounded-full flex items-center justify-center mx-auto mb-3 text-lg font-bold">
                  3
                </div>
                <ShoppingCart className="w-10 h-10 text-gray-700 mx-auto mb-2" />
                <h3 className="font-bold text-gray-900 mb-1 text-sm">Add to Basket</h3>
                <p className="text-xs text-gray-600">Order alone or together.</p>
              </div>

              {/* Step 4 - Emphasized */}
              <div className="text-center">
                <div className="w-14 h-14 bg-gray-900 text-white rounded-full flex items-center justify-center mx-auto mb-3 text-lg font-bold shadow-md ring-2 ring-gray-200">
                  4
                </div>
                <CreditCard className="w-12 h-12 text-gray-900 mx-auto mb-2" />
                <h3 className="font-bold text-gray-900 mb-1 text-sm">Pay & Order</h3>
                <p className="text-xs text-gray-600">Pay instantly.</p>
              </div>

              <div className="text-center">
                <div className="w-14 h-14 bg-gray-900 text-white rounded-full flex items-center justify-center mx-auto mb-3 text-lg font-bold">
                  5
                </div>
                <CheckCircle className="w-10 h-10 text-gray-700 mx-auto mb-2" />
                <h3 className="font-bold text-gray-900 mb-1 text-sm">Enjoy Your Meal</h3>
                <p className="text-xs text-gray-600">Order on the way.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* TRUST & RELIABILITY */}
      <section className="py-16 px-4 sm:px-6 lg:px-8 bg-gray-50 border-t border-gray-200">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-10">
            <h2 className="text-3xl font-bold text-gray-900 mb-3">Built for Trust & Reliability</h2>
          </div>

          <div className="grid md:grid-cols-3 lg:grid-cols-6 gap-6">
            <div className="text-center">
              <Shield className="w-10 h-10 text-gray-700 mx-auto mb-2" />
              <p className="text-xs font-medium text-gray-900">Secure infrastructure</p>
            </div>
            <div className="text-center">
              <Lock className="w-10 h-10 text-gray-700 mx-auto mb-2" />
              <p className="text-xs font-medium text-gray-900">GDPR compliant</p>
            </div>
            <div className="text-center">
              <Server className="w-10 h-10 text-gray-700 mx-auto mb-2" />
              <p className="text-xs font-medium text-gray-900">EU-hosted data</p>
            </div>
            <div className="text-center">
              <TrendingUp className="w-10 h-10 text-gray-700 mx-auto mb-2" />
              <p className="text-xs font-medium text-gray-900">High availability</p>
            </div>
            <div className="text-center">
              <Award className="w-10 h-10 text-gray-700 mx-auto mb-2" />
              <p className="text-xs font-medium text-gray-900">Quality assurance</p>
            </div>
            <div className="text-center">
              <Clock className="w-10 h-10 text-gray-700 mx-auto mb-2" />
              <p className="text-xs font-medium text-gray-900">24/7 monitoring</p>
            </div>
          </div>
        </div>
      </section>

      {/* FOOTER */}
      <footer className="py-10 px-4 sm:px-6 lg:px-8 bg-gray-900 text-white">
        <div className="max-w-6xl mx-auto">
          <div className="grid md:grid-cols-3 lg:grid-cols-4 gap-8 mb-10">
            {/* Column 1 */}
            <div>
              <h3 className="font-semibold text-sm mb-3">Company</h3>
              <ul className="space-y-2 text-sm">
                <li>
                  <button onClick={() => openFooterModal('about')} className="text-gray-400 hover:text-white transition-colors">
                    About Tavlo
                  </button>
                </li>
                <li>
                  <button onClick={() => openFooterModal('how-it-works')} className="text-gray-400 hover:text-white transition-colors">
                    How Tavlo Works
                  </button>
                </li>
                <li>
                  <button onClick={() => openFooterModal('features')} className="text-gray-400 hover:text-white transition-colors">
                    Features
                  </button>
                </li>
                <li>
                  <button onClick={() => openFooterModal('pricing')} className="text-gray-400 hover:text-white transition-colors">
                    Pricing
                  </button>
                </li>
              </ul>
            </div>

            {/* Column 2 */}
            <div>
              <h3 className="font-semibold text-sm mb-3">For You</h3>
              <ul className="space-y-2 text-sm">
                <li>
                  <button onClick={() => openFooterModal('for-restaurants')} className="text-gray-400 hover:text-white transition-colors">
                    For Restaurants
                  </button>
                </li>
                <li>
                  <button onClick={() => openFooterModal('for-guests')} className="text-gray-400 hover:text-white transition-colors">
                    For Guests
                  </button>
                </li>
              </ul>
            </div>

            {/* Column 3 */}
            <div>
              <h3 className="font-semibold text-sm mb-3">Support</h3>
              <ul className="space-y-2 text-sm">
                <li>
                  <button onClick={() => openFooterModal('contact')} className="text-gray-400 hover:text-white transition-colors">
                    Contact
                  </button>
                </li>
                <li>
                  <button onClick={() => openFooterModal('help')} className="text-gray-400 hover:text-white transition-colors">
                    Help Center
                  </button>
                </li>
                <li>
                  <button onClick={() => openFooterModal('faq')} className="text-gray-400 hover:text-white transition-colors">
                    FAQ
                  </button>
                </li>
              </ul>
            </div>

            {/* Column 4 */}
            <div>
              <h3 className="font-semibold text-sm mb-3">Legal</h3>
              <ul className="space-y-2 text-sm">
                <li>
                  <button onClick={() => openFooterModal('terms')} className="text-gray-400 hover:text-white transition-colors">
                    Terms & Conditions
                  </button>
                </li>
                <li>
                  <button onClick={() => openFooterModal('privacy')} className="text-gray-400 hover:text-white transition-colors">
                    Privacy Policy
                  </button>
                </li>
                <li>
                  <button onClick={() => openFooterModal('cookies')} className="text-gray-400 hover:text-white transition-colors">
                    Cookie Policy
                  </button>
                </li>
                <li>
                  <button onClick={() => openFooterModal('imprint')} className="text-gray-400 hover:text-white transition-colors">
                    Imprint
                  </button>
                </li>
              </ul>
            </div>
          </div>

          <div className="pt-8 border-t border-gray-800 text-center">
            <div className="flex justify-center mb-2">
              <TavloLogo variant="wordmark" size={28} colorScheme="white" />
            </div>
            <p className="text-sm text-gray-400 mb-1">
              Your gateway to discovering and ordering from the best restaurants.
            </p>
            <p className="text-sm text-gray-300 font-medium mb-5">
              Scan. Order. Pay. Enjoy.
            </p>
            <p className="text-xs text-gray-500">© 2026 Tavlo. All rights reserved.</p>
          </div>
        </div>
      </footer>

      {/* STICKY MICRO-CTA - Very subtle */}
      <button
        onClick={scrollToVendorSection}
        className="fixed bottom-5 right-5 z-40 text-gray-600 hover:text-gray-900 text-xs underline transition-colors bg-white px-3 py-2 rounded shadow-sm"
      >
        Own a restaurant?
      </button>

      {/* FOOTER MODALS */}
      {activeFooterSection && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
          <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <div className="sticky top-0 bg-white border-b border-gray-200 p-5 flex items-center justify-between">
              <h2 className="text-xl font-bold text-gray-900">
                {activeFooterSection === 'about' && 'About Tavlo'}
                {activeFooterSection === 'how-it-works' && 'How Tavlo Works'}
                {activeFooterSection === 'features' && 'Features'}
                {activeFooterSection === 'pricing' && 'Pricing'}
                {activeFooterSection === 'for-restaurants' && 'For Restaurants'}
                {activeFooterSection === 'for-guests' && 'For Guests'}
                {activeFooterSection === 'contact' && 'Contact'}
                {activeFooterSection === 'help' && 'Help Center'}
                {activeFooterSection === 'faq' && 'FAQ'}
                {activeFooterSection === 'terms' && 'Terms & Conditions'}
                {activeFooterSection === 'privacy' && 'Privacy Policy'}
                {activeFooterSection === 'cookies' && 'Cookie Policy'}
                {activeFooterSection === 'imprint' && 'Imprint'}
              </h2>
              <button
                onClick={closeFooterModal}
                className="text-gray-500 hover:text-gray-900 transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-5">
              {activeFooterSection === 'about' && (
                <div className="prose prose-sm max-w-none">
                  <p className="text-gray-700 leading-relaxed mb-3">
                    Tavlo is a digital infrastructure platform built for modern restaurants in Europe.
                  </p>
                  <p className="text-gray-700 leading-relaxed mb-3">
                    We connect ordering, payments, reservations, and guest interaction into one calm, reliable system — without forcing restaurants to change how they operate.
                  </p>
                  <p className="text-gray-700 leading-relaxed mb-3">
                    Restaurants use Tavlo to simplify operations, reduce friction at the table, and stay fully compliant with European regulations. Guests use Tavlo to order together, split bills effortlessly, and pay securely — directly from their own device.
                  </p>
                  <p className="text-gray-700 leading-relaxed mb-3 font-medium">
                    Tavlo is designed as infrastructure, not a gimmick.
                  </p>
                  <p className="text-gray-700 leading-relaxed mb-3">
                    No unnecessary apps. No locked hardware. No hidden complexity.
                  </p>
                  <p className="text-gray-700 leading-relaxed font-medium">
                    Built for restaurants that value clarity, control, and long-term reliability.
                  </p>
                </div>
              )}

              {activeFooterSection === 'how-it-works' && (
                <div className="prose prose-sm max-w-none">
                  <h3 className="text-lg font-bold text-gray-900 mb-3">For Guests</h3>
                  <ul className="list-disc pl-5 space-y-1 mb-5 text-gray-700 text-sm">
                    <li>Scan the QR code at the table or on the menu</li>
                    <li>Join a shared table session</li>
                    <li>Order together in real time</li>
                    <li>Pay individually or as a group</li>
                    <li>Leave anytime — no app required</li>
                  </ul>
                  <p className="text-gray-700 leading-relaxed mb-5 text-sm">
                    Guests can order and pay using their own phone, in their own language, with full price transparency.
                  </p>

                  <h3 className="text-lg font-bold text-gray-900 mb-3">For Restaurants</h3>
                  <ul className="list-disc pl-5 space-y-1 mb-5 text-gray-700 text-sm">
                    <li>Create your restaurant profile</li>
                    <li>Upload and manage your menu</li>
                    <li>Receive live orders in real time</li>
                    <li>Track payments and settlements</li>
                    <li>Access reports and insights</li>
                  </ul>
                  <p className="text-gray-700 leading-relaxed mb-5 text-sm">
                    Tavlo works alongside your existing setup. No forced POS replacement. No mandatory hardware.
                  </p>

                  <h3 className="text-lg font-bold text-gray-900 mb-3">Payments & Security</h3>
                  <p className="text-gray-700 leading-relaxed mb-3 text-sm">
                    All payments are processed via PCI-compliant payment service providers. Tavlo never stores card details.
                  </p>
                  <p className="text-gray-700 font-medium mb-2 text-sm">Supported methods may include:</p>
                  <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                    <li>Debit & credit cards</li>
                    <li>Apple Pay</li>
                    <li>Google Pay</li>
                  </ul>
                </div>
              )}

              {activeFooterSection === 'features' && (
                <div className="prose prose-sm max-w-none">
                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Ordering</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>QR-based ordering</li>
                      <li>Shared table ordering</li>
                      <li>Real-time basket sync</li>
                      <li>Multi-language menus</li>
                      <li>Takeaway & dine-in support</li>
                    </ul>
                  </div>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Payments</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>Split bill automation</li>
                      <li>Individual or group payment</li>
                      <li>Digital receipts</li>
                      <li>VAT-compliant invoices</li>
                      <li>Secure checkout</li>
                    </ul>
                  </div>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Operations</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>Vendor dashboard</li>
                      <li>Live order overview</li>
                      <li>Menu & pricing control</li>
                      <li>Opening hours management</li>
                    </ul>
                  </div>

                  <div>
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Insights</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>Order volume</li>
                      <li>Revenue overview</li>
                      <li>Guest behavior trends</li>
                      <li>Performance over time</li>
                    </ul>
                  </div>
                </div>
              )}

              {activeFooterSection === 'pricing' && (
                <div className="prose prose-sm max-w-none">
                  <p className="text-gray-700 leading-relaxed mb-5 text-sm">
                    Tavlo offers transparent pricing with no hidden fees.
                  </p>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Subscription</h3>
                    <p className="text-gray-700 leading-relaxed text-sm">
                      A fixed monthly fee for access to the Tavlo platform, dashboard, and core features.
                    </p>
                  </div>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Transaction Fees</h3>
                    <p className="text-gray-700 leading-relaxed text-sm">
                      Payment processing fees may apply depending on the selected payment service provider.
                    </p>
                  </div>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">What's Included</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>QR ordering</li>
                      <li>Shared tables</li>
                      <li>Vendor dashboard</li>
                      <li>VAT-compliant invoicing</li>
                      <li>Customer support</li>
                    </ul>
                  </div>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">What's Not Included</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>Payment processing fees charged by third-party providers</li>
                      <li>Hardware costs (if any)</li>
                    </ul>
                  </div>

                  <p className="text-xs text-gray-600 italic">
                    Exact pricing may vary by country and setup.
                  </p>
                </div>
              )}

              {activeFooterSection === 'for-restaurants' && (
                <div className="prose prose-sm max-w-none">
                  <p className="text-gray-700 leading-relaxed mb-5 font-medium text-sm">
                    Tavlo is built for restaurants that want simplicity without losing control.
                  </p>

                  <h3 className="text-lg font-bold text-gray-900 mb-2">Tavlo helps you:</h3>
                  <ul className="list-disc pl-5 space-y-1 mb-5 text-gray-700 text-sm">
                    <li>Reduce ordering friction</li>
                    <li>Speed up table turnover</li>
                    <li>Eliminate manual bill splitting</li>
                    <li>Improve guest experience</li>
                    <li>Stay compliant from day one</li>
                  </ul>

                  <h3 className="text-lg font-bold text-gray-900 mb-2">Tavlo does NOT:</h3>
                  <ul className="list-disc pl-5 space-y-1 mb-5 text-gray-700 text-sm">
                    <li>Replace your kitchen workflow</li>
                    <li>Lock you into proprietary hardware</li>
                    <li>Force guests to download an app</li>
                  </ul>

                  <p className="text-gray-700 leading-relaxed font-medium text-sm">
                    You stay in control. Tavlo handles the complexity.
                  </p>
                </div>
              )}

              {activeFooterSection === 'for-guests' && (
                <div className="prose prose-sm max-w-none">
                  <p className="text-gray-700 leading-relaxed mb-5 font-medium text-sm">
                    Tavlo makes dining simpler.
                  </p>

                  <ul className="list-disc pl-5 space-y-1 mb-5 text-gray-700 text-sm">
                    <li>No app download required</li>
                    <li>No account required (unless stated otherwise)</li>
                    <li>Order together from multiple phones</li>
                    <li>Pay safely from your own device</li>
                  </ul>

                  <p className="text-gray-700 leading-relaxed text-sm">
                    Your data is handled responsibly and in accordance with EU privacy laws.
                  </p>
                </div>
              )}

              {activeFooterSection === 'contact' && (
                <div className="prose prose-sm max-w-none">
                  <p className="text-gray-700 leading-relaxed mb-5 text-sm">
                    Have a question or need support?
                  </p>

                  <div className="space-y-3">
                    <div>
                      <p className="font-bold text-gray-900 mb-1 text-sm">Email:</p>
                      <a href="mailto:support@tavlo.com" className="text-gray-700 hover:text-gray-900 flex items-center gap-2 text-sm">
                        <Mail className="w-4 h-4" />
                        support@tavlo.com
                      </a>
                    </div>

                    <div>
                      <p className="font-bold text-gray-900 mb-1 text-sm">Business inquiries:</p>
                      <a href="mailto:hello@tavlo.com" className="text-gray-700 hover:text-gray-900 flex items-center gap-2 text-sm">
                        <Mail className="w-4 h-4" />
                        hello@tavlo.com
                      </a>
                    </div>
                  </div>

                  <p className="text-xs text-gray-600 mt-5">
                    We aim to respond within one business day.
                  </p>
                </div>
              )}

              {activeFooterSection === 'help' && (
                <div className="prose prose-sm max-w-none">
                  <p className="text-gray-700 leading-relaxed mb-5 text-sm">
                    Find answers and guidance for using Tavlo.
                  </p>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Guests</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>How to order</li>
                      <li>How to split bills</li>
                      <li>Payment issues</li>
                      <li>Receipts & invoices</li>
                    </ul>
                  </div>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Restaurants</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>Account setup</li>
                      <li>Menu management</li>
                      <li>Payments & settlements</li>
                      <li>Dashboard usage</li>
                    </ul>
                  </div>

                  <div>
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Payments</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>Supported methods</li>
                      <li>Refunds</li>
                      <li>Failed transactions</li>
                    </ul>
                  </div>
                </div>
              )}

              {activeFooterSection === 'faq' && (
                <div className="prose prose-sm max-w-none space-y-4">
                  <div>
                    <h3 className="font-bold text-gray-900 mb-1 text-sm">Do guests need to download an app?</h3>
                    <p className="text-gray-700 text-sm">No. Tavlo works directly in the browser.</p>
                  </div>

                  <div>
                    <h3 className="font-bold text-gray-900 mb-1 text-sm">Who processes the payments?</h3>
                    <p className="text-gray-700 text-sm">Payments are processed by certified third-party payment service providers.</p>
                  </div>

                  <div>
                    <h3 className="font-bold text-gray-900 mb-1 text-sm">Is Tavlo VAT-compliant?</h3>
                    <p className="text-gray-700 text-sm">Yes. Tavlo supports VAT-compliant invoicing in accordance with EU regulations.</p>
                  </div>

                  <div>
                    <h3 className="font-bold text-gray-900 mb-1 text-sm">Can Tavlo work with my existing POS?</h3>
                    <p className="text-gray-700 text-sm">Yes. Tavlo can operate independently alongside your existing systems.</p>
                  </div>

                  <div>
                    <h3 className="font-bold text-gray-900 mb-1 text-sm">How do refunds work?</h3>
                    <p className="text-gray-700 text-sm">Refunds are handled through the payment provider and reflected in the vendor dashboard.</p>
                  </div>
                </div>
              )}

              {activeFooterSection === 'terms' && (
                <div className="prose prose-sm max-w-none">
                  <p className="text-gray-700 leading-relaxed mb-3 text-sm">
                    Tavlo provides a digital platform that enables restaurants and guests to interact.
                  </p>

                  <h3 className="text-base font-bold text-gray-900 mb-2">Tavlo is not responsible for:</h3>
                  <ul className="list-disc pl-5 space-y-1 mb-5 text-gray-700 text-sm">
                    <li>Food quality</li>
                    <li>Preparation</li>
                    <li>Delivery</li>
                    <li>Service provided by the restaurant</li>
                  </ul>

                  <p className="text-gray-700 leading-relaxed mb-3 text-sm">
                    Payments are processed via third-party providers. Tavlo does not act as the merchant of record unless explicitly stated.
                  </p>

                  <p className="text-gray-700 leading-relaxed mb-3 text-sm">
                    Liability is limited to the maximum extent permitted by law.
                  </p>

                  <p className="text-xs text-gray-600 mb-1">
                    <strong>Governing law:</strong> Austria
                  </p>
                  <p className="text-xs text-gray-600 mb-3">
                    <strong>Jurisdiction:</strong> Austrian courts
                  </p>

                  <p className="text-xs text-gray-500 italic">
                    Full legal text subject to updates.
                  </p>
                </div>
              )}

              {activeFooterSection === 'privacy' && (
                <div className="prose prose-sm max-w-none">
                  <p className="text-gray-700 leading-relaxed mb-5 text-sm">
                    Tavlo respects your privacy and complies with the General Data Protection Regulation (GDPR).
                  </p>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Data We Collect</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>Device and browser information</li>
                      <li>Order and payment metadata</li>
                      <li>Contact details (if provided)</li>
                    </ul>
                  </div>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Why We Collect It</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>To provide and operate the service</li>
                      <li>To process payments</li>
                      <li>To improve platform performance</li>
                    </ul>
                  </div>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Data Storage & Sharing</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>Stored securely within the EU</li>
                      <li>Shared only with necessary service providers (e.g. payment processors)</li>
                    </ul>
                  </div>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Your Rights</h3>
                    <p className="text-gray-700 text-sm">
                      You have the right to access, correct, or delete your data at any time.
                    </p>
                  </div>

                  <p className="text-xs text-gray-600">
                    Contact: <a href="mailto:privacy@tavlo.com" className="text-gray-900 hover:underline">privacy@tavlo.com</a>
                  </p>
                </div>
              )}

              {activeFooterSection === 'cookies' && (
                <div className="prose prose-sm max-w-none">
                  <p className="text-gray-700 leading-relaxed mb-5 text-sm">
                    Tavlo uses cookies to ensure proper functionality and improve user experience.
                  </p>

                  <div className="mb-5">
                    <h3 className="text-lg font-bold text-gray-900 mb-2">Types of Cookies</h3>
                    <ul className="list-disc pl-5 space-y-1 text-gray-700 text-sm">
                      <li>Essential cookies (required)</li>
                      <li>Analytics cookies (optional)</li>
                      <li>Marketing cookies (if enabled)</li>
                    </ul>
                  </div>

                  <p className="text-gray-700 leading-relaxed text-sm">
                    You can manage cookie preferences at any time.
                  </p>
                </div>
              )}

              {activeFooterSection === 'imprint' && (
                <div className="prose prose-sm max-w-none">
                  <div className="space-y-1 text-gray-700 text-sm">
                    <p className="font-bold text-gray-900">Tavlo GmbH</p>
                    <p>[Street Address]</p>
                    <p>[Postal Code, City]</p>
                    <p>Austria</p>
                  </div>

                  <div className="mt-5 space-y-1 text-gray-700 text-sm">
                    <p><strong>Managing Director:</strong> [Name]</p>
                    <p><strong>Company Register:</strong> [Number]</p>
                    <p><strong>VAT ID:</strong> [VAT Number]</p>
                  </div>

                  <div className="mt-5">
                    <p className="text-gray-700 text-sm">
                      <strong>Email:</strong> <a href="mailto:hello@tavlo.com" className="text-gray-900 hover:underline">hello@tavlo.com</a>
                    </p>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Auth Modal */}
      {showAuthModal && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-md w-full relative max-h-[90vh] overflow-y-auto">
            {/* Close Button */}
            <button
              onClick={() => setShowAuthModal(false)}
              className="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors z-10"
              aria-label="Close"
            >
              <X className="w-5 h-5" />
            </button>

            {/* Use unified AuthForm */}
            <div className="p-6 sm:p-8">
              <AuthForm
                mode={authFormMode}
                onSuccess={handleAuthSuccess}
                onBack={() => setShowAuthModal(false)}
                standalone={false}
                language={languageCode}
              />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}