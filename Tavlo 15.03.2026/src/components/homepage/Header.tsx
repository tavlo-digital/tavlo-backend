import image_a41c5ed9cb37da642e00d6ad4d3424dd23759fa0 from 'figma:asset/a41c5ed9cb37da642e00d6ad4d3424dd23759fa0.png';
import { User, LogIn, UserPlus } from 'lucide-react';
import { Button } from '../ui/button';
import { SearchBar } from '../shared/SearchBar';
import { PlatformLanguageSelector } from './PlatformLanguageSelector';
import tavloLogo from 'figma:asset/d442f812b641089c191ab222c1e3bb84e36bdccf.png';

interface HeaderProps {
  user: any;
  onLoginClick: () => void;
  onRegisterClick: () => void;
  onProfileClick: () => void;
  onSearch: (query: string) => void;
  onLogoClick?: () => void;
}

export function Header({
  user,
  onLoginClick,
  onRegisterClick,
  onProfileClick,
  onSearch,
  onLogoClick
}: HeaderProps) {
  return (
    <header className="sticky top-0 z-40 bg-white border-b border-gray-100 shadow-sm">
      {/* Top Bar */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-4">
        <div className="flex items-center justify-between gap-4">
          {/* Logo */}
          <button 
            onClick={onLogoClick}
            className="shrink-0 hover:opacity-80 transition-opacity cursor-pointer"
            aria-label="Return to home"
          >
            <img 
              src={image_a41c5ed9cb37da642e00d6ad4d3424dd23759fa0} 
              alt="TAVLO"
              className="h-12 w-auto object-contain"
            />
          </button>

          {/* Search Bar - Desktop */}
          <div className="hidden lg:block flex-1 max-w-2xl">
            <SearchBar onSearch={onSearch} />
          </div>

          {/* Right Side: Language Selector + Auth Buttons */}
          <div className="flex items-center gap-3">
            {/* Language Selector */}
            <PlatformLanguageSelector />
            
            {/* Auth Buttons */}
            {user ? (
              <Button
                onClick={onProfileClick}
                variant="ghost"
                className="gap-2"
              >
                <div className="w-8 h-8 bg-gradient-to-br from-orange-500 to-red-500 rounded-full flex items-center justify-center">
                  <User className="w-4 h-4 text-white" />
                </div>
                <span className="hidden sm:inline">{user.name}</span>
              </Button>
            ) : (
              <>
                <Button
                  onClick={onLoginClick}
                  variant="ghost"
                  size="sm"
                  className="text-gray-600 hover:text-gray-900"
                >
                  <span className="text-sm">Login</span>
                </Button>
                <Button
                  onClick={onRegisterClick}
                  variant="ghost"
                  size="sm"
                  className="text-gray-600 hover:text-gray-900"
                >
                  <span className="text-sm">Sign Up</span>
                </Button>
              </>
            )}
          </div>
        </div>

        {/* Search Bar - Mobile */}
        <div className="lg:hidden mt-4">
          <SearchBar onSearch={onSearch} />
        </div>
      </div>
    </header>
  );
}