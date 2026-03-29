import { useState, ReactNode } from 'react';
import { 
  BarChart3, 
  Users, 
  CreditCard, 
  DollarSign, 
  Activity, 
  Sparkles,
  Settings,
  ScrollText,
  Menu,
  X,
  Search,
  Bell,
  User,
  ChevronDown,
  Key,
  HelpCircle,
  LogOut,
  UserCircle,
  FileText,
  MessageSquareWarning
} from 'lucide-react';
import tavloLogo from 'figma:asset/d442f812b641089c191ab222c1e3bb84e36bdccf.png';
import { Input } from '../ui/input';

interface AdminLayoutProps {
  children: ReactNode;
  currentPage: string;
  onNavigate: (page: string) => void;
}

interface NavItem {
  id: string;
  label: string;
  icon: any;
  badge?: number;
  children?: NavItem[];
}

export function AdminLayout({ children, currentPage, onNavigate }: AdminLayoutProps) {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [showProfileMenu, setShowProfileMenu] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);

  const menuItems: NavItem[] = [
    { id: 'overview', label: 'Overview', icon: BarChart3 },
    { id: 'vendors', label: 'Vendors Management', icon: Users },
    { id: 'customers', label: 'Customers Management', icon: UserCircle },
    { id: 'billing', label: 'Finance & Billing Overview', icon: FileText },
    { id: 'subscriptions', label: 'Subscriptions Management', icon: CreditCard },
    { id: 'reviews', label: 'Reviews & Complaints', icon: MessageSquareWarning },
    { id: 'ai-insights', label: 'Insights & Analysis', icon: Sparkles },
    { id: 'system', label: 'System Settings', icon: Settings },
    { id: 'audit-log', label: 'Audit Log', icon: ScrollText },
  ];

  const notifications = [
    { id: 1, title: 'New vendor application', description: 'Sakura Sushi submitted application', time: '5m ago', unread: true },
    { id: 2, title: 'Payment overdue', description: 'Bella Italia subscription payment overdue', time: '1h ago', unread: true },
    { id: 3, title: 'Complaint filed', description: 'Customer complaint against Cafe Noir', time: '2h ago', unread: false },
  ];

  return (
    <div className="h-screen flex overflow-hidden bg-gray-50">
      {/* Sidebar */}
      <aside className={`${sidebarCollapsed ? 'w-20' : 'w-64'} bg-white border-r border-gray-200 transition-all duration-300 flex flex-col`}>
        {/* Logo & Toggle */}
        <div className="h-16 border-b border-gray-200 flex items-center justify-between px-4">
          {!sidebarCollapsed && (
            <div className="flex items-center gap-2">
              <img src={tavloLogo} alt="TAVLO" className="h-8 w-auto" />
              <span className="text-xs text-gray-500 px-2 py-0.5 bg-purple-100 text-purple-700 rounded">ADMIN</span>
            </div>
          )}
          <button
            onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
            className="p-1.5 hover:bg-gray-100 rounded-lg"
          >
            {sidebarCollapsed ? <Menu className="w-5 h-5" /> : <X className="w-5 h-5" />}
          </button>
        </div>

        {/* Navigation */}
        <nav className="flex-1 overflow-y-auto py-4">
          {menuItems.map((item) => (
            <div key={item.id}>
              <button
                onClick={() => onNavigate(item.id)}
                className={`w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-colors relative ${
                  currentPage === item.id
                    ? 'bg-purple-50 text-purple-700 border-r-2 border-purple-600'
                    : 'text-gray-700 hover:bg-gray-50'
                }`}
              >
                <item.icon className="w-5 h-5 shrink-0" />
                {!sidebarCollapsed && (
                  <>
                    <span className="flex-1 text-left">{item.label}</span>
                    {item.badge && (
                      <span className="px-1.5 py-0.5 bg-red-500 text-white text-xs rounded-full min-w-[20px] text-center">
                        {item.badge}
                      </span>
                    )}
                  </>
                )}
              </button>
              {!sidebarCollapsed && item.children && (
                <div className="pl-12 py-1 space-y-0.5">
                  {item.children.map((child) => (
                    <button
                      key={child.id}
                      onClick={() => onNavigate(child.id)}
                      className={`w-full flex items-center justify-between px-3 py-1.5 text-xs transition-colors rounded ${
                        currentPage === child.id
                          ? 'bg-purple-50 text-purple-700'
                          : 'text-gray-600 hover:bg-gray-50'
                      }`}
                    >
                      <span>{child.label}</span>
                      {child.badge && (
                        <span className="px-1.5 py-0.5 bg-red-500 text-white text-xs rounded-full">
                          {child.badge}
                        </span>
                      )}
                    </button>
                  ))}
                </div>
              )}
            </div>
          ))}
        </nav>

        {/* Admin Info */}
        {!sidebarCollapsed && (
          <div className="p-4 border-t border-gray-200">
            <div className="text-xs text-gray-500">Logged in as</div>
            <div className="text-sm">Super Admin</div>
          </div>
        )}
      </aside>

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top Bar - Hidden for dashboard since it has its own */}
        {currentPage !== 'overview' && (
          <header className="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
            {/* Search */}
            <div className="flex-1 max-w-2xl">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                <Input
                  type="text"
                  placeholder="Search vendors, customers, orders..."
                  className="pl-10 bg-gray-50 border-gray-200"
                />
              </div>
            </div>

            {/* Right Actions */}
            <div className="flex items-center gap-4 ml-6">
              {/* Notifications */}
              <div className="relative">
                <button
                  onClick={() => setShowNotifications(!showNotifications)}
                  className="relative p-2 hover:bg-gray-100 rounded-lg"
                >
                  <Bell className="w-5 h-5 text-gray-600" />
                  <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>

                {showNotifications && (
                  <div className="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50">
                    <div className="p-4 border-b border-gray-100">
                      <div className="flex items-center justify-between">
                        <h3 className="font-semibold">Notifications</h3>
                        <button className="text-xs text-purple-600 hover:text-purple-700">Mark all read</button>
                      </div>
                    </div>
                    <div className="max-h-96 overflow-y-auto">
                      {notifications.map((notif) => (
                        <div
                          key={notif.id}
                          className={`p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer ${
                            notif.unread ? 'bg-purple-50' : ''
                          }`}
                        >
                          <div className="flex items-start gap-3">
                            {notif.unread && <div className="w-2 h-2 bg-purple-600 rounded-full mt-2"></div>}
                            <div className="flex-1">
                              <div className="text-sm">{notif.title}</div>
                              <div className="text-xs text-gray-500">{notif.description}</div>
                              <div className="text-xs text-gray-400 mt-1">{notif.time}</div>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                    <div className="p-3 text-center border-t">
                      <button className="text-sm text-purple-600 hover:text-purple-700">View all notifications</button>
                    </div>
                  </div>
                )}
              </div>

              {/* Profile Menu */}
              <div className="relative">
                <button
                  onClick={() => setShowProfileMenu(!showProfileMenu)}
                  className="flex items-center gap-3 p-2 hover:bg-gray-100 rounded-lg"
                >
                  <div className="w-8 h-8 bg-gradient-to-br from-purple-500 to-blue-500 rounded-full flex items-center justify-center">
                    <User className="w-4 h-4 text-white" />
                  </div>
                  <div className="text-left hidden lg:block">
                    <div className="text-sm">Admin User</div>
                    <div className="text-xs text-gray-500">Super Admin</div>
                  </div>
                  <ChevronDown className="w-4 h-4 text-gray-400" />
                </button>

                {showProfileMenu && (
                  <div className="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-200 z-50">
                    <div className="p-2">
                      <button className="w-full flex items-center gap-3 px-3 py-2 text-sm hover:bg-gray-50 rounded-lg">
                        <User className="w-4 h-4" />
                        <span>Profile Settings</span>
                      </button>
                      <button className="w-full flex items-center gap-3 px-3 py-2 text-sm hover:bg-gray-50 rounded-lg">
                        <Key className="w-4 h-4" />
                        <span>Change Password</span>
                      </button>
                      <button className="w-full flex items-center gap-3 px-3 py-2 text-sm hover:bg-gray-50 rounded-lg">
                        <HelpCircle className="w-4 h-4" />
                        <span>Help & Support</span>
                      </button>
                    </div>
                    <div className="border-t border-gray-100 p-2">
                      <button className="w-full flex items-center gap-3 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">
                        <LogOut className="w-4 h-4" />
                        <span>Logout</span>
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </header>
        )}

        {/* Page Content */}
        <main className="flex-1 overflow-auto">
          {children}
        </main>
      </div>
    </div>
  );
}