import { Facebook, Twitter, Instagram, Linkedin, Mail, Phone, MapPin } from 'lucide-react';
import tavloLogo from 'figma:asset/d442f812b641089c191ab222c1e3bb84e36bdccf.png';

export function Footer() {
  return (
    <footer className="bg-gray-900 text-gray-300 mt-16">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-12">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
          {/* About */}
          <div>
            <div className="mb-4">
              <img src={tavloLogo} alt="TAVLO" className="h-8 w-auto brightness-0 invert" />
            </div>
            <p className="text-sm leading-relaxed mb-4">
              Your gateway to discovering and ordering from the best restaurants. 
              Scan, order, and enjoy delicious food with ease.
            </p>
            <div className="flex gap-3">
              <a href="#" className="w-9 h-9 bg-gray-800 rounded-full flex items-center justify-center hover:bg-orange-500 transition-colors">
                <Facebook className="w-4 h-4" />
              </a>
              <a href="#" className="w-9 h-9 bg-gray-800 rounded-full flex items-center justify-center hover:bg-orange-500 transition-colors">
                <Twitter className="w-4 h-4" />
              </a>
              <a href="#" className="w-9 h-9 bg-gray-800 rounded-full flex items-center justify-center hover:bg-orange-500 transition-colors">
                <Instagram className="w-4 h-4" />
              </a>
              <a href="#" className="w-9 h-9 bg-gray-800 rounded-full flex items-center justify-center hover:bg-orange-500 transition-colors">
                <Linkedin className="w-4 h-4" />
              </a>
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <h3 className="text-white text-lg mb-4">Quick Links</h3>
            <ul className="space-y-2 text-sm">
              <li>
                <a href="#" className="hover:text-orange-500 transition-colors">About Us</a>
              </li>
              <li>
                <a href="#" className="hover:text-orange-500 transition-colors">How It Works</a>
              </li>
              <li>
                <a href="#" className="hover:text-orange-500 transition-colors">For Restaurants</a>
              </li>
              <li>
                <a href="#" className="hover:text-orange-500 transition-colors">Careers</a>
              </li>
              <li>
                <a href="#" className="hover:text-orange-500 transition-colors">Blog</a>
              </li>
            </ul>
          </div>

          {/* Legal */}
          <div>
            <h3 className="text-white text-lg mb-4">Legal</h3>
            <ul className="space-y-2 text-sm">
              <li>
                <a href="#" className="hover:text-orange-500 transition-colors">Terms of Service</a>
              </li>
              <li>
                <a href="#" className="hover:text-orange-500 transition-colors">Privacy Policy</a>
              </li>
              <li>
                <a href="#" className="hover:text-orange-500 transition-colors">Cookie Policy</a>
              </li>
              <li>
                <a href="#" className="hover:text-orange-500 transition-colors">Refund Policy</a>
              </li>
            </ul>
          </div>

          {/* Contact */}
          <div>
            <h3 className="text-white text-lg mb-4">Contact Us</h3>
            <ul className="space-y-3 text-sm">
              <li className="flex items-start gap-2">
                <MapPin className="w-4 h-4 mt-1 shrink-0" />
                <span>123 Food Street, Vienna, Austria</span>
              </li>
              <li className="flex items-center gap-2">
                <Phone className="w-4 h-4 shrink-0" />
                <a href="tel:+431234567890" className="hover:text-orange-500 transition-colors">
                  +43 1 234 567 890
                </a>
              </li>
              <li className="flex items-center gap-2">
                <Mail className="w-4 h-4 shrink-0" />
                <a href="mailto:info@tavlo.com" className="hover:text-orange-500 transition-colors">
                  info@tavlo.com
                </a>
              </li>
            </ul>
          </div>
        </div>

        {/* Bottom Bar */}
        <div className="border-t border-gray-800 pt-8 text-center text-sm">
          <p>© {new Date().getFullYear()} TAVLO QR Platform. All rights reserved.</p>
        </div>
      </div>
    </footer>
  );
}