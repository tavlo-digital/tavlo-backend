import { Link, usePage } from '@inertiajs/react';
import {
    ChartColumn,
    Users,
    CircleUser,
    FileText,
    CreditCard,
    MessageSquareWarning,
    Sparkles,
    Settings,
    ScrollText,
    X,
    Shield,
    Tags,
    Stethoscope,
    Receipt,
    Globe,
} from 'lucide-react';
import type { Auth } from '@/types';

interface NavItem {
    title: string;
    href: string;
    icon: React.ElementType;
    disabled?: boolean;
}

const navItems: NavItem[] = [
    { title: 'Overview', href: '/dashboard', icon: ChartColumn },
    { title: 'Vendors Management', href: '/admin/vendors', icon: Users },
    { title: 'Customers Management', href: '/admin/customers', icon: CircleUser },
    { title: 'Finance & Billing Overview', href: '/admin/finance', icon: FileText, disabled: true },
    { title: 'Subscriptions Management', href: '/admin/subscriptions/plans', icon: CreditCard },
    { title: 'Menu Categories', href: '/admin/menu-categories', icon: Tags },
    { title: 'Tax Management', href: '/admin/tax-categories', icon: Receipt },
    { title: 'Countries', href: '/admin/countries', icon: Globe },
    { title: 'Reviews & Complaints', href: '/admin/reviews', icon: MessageSquareWarning, disabled: true },
    { title: 'Insights & Analysis', href: '/admin/insights', icon: Sparkles, disabled: true },
    { title: 'Diagnostics', href: '/admin/diagnostics', icon: Stethoscope },
    { title: 'System Settings', href: '/admin/settings', icon: Settings, disabled: true },
    { title: 'Audit Log', href: '/admin/audit-log', icon: ScrollText, disabled: true },
];

const adminNavItems: NavItem[] = [
    { title: 'Users', href: '/admin/users', icon: Users },
    { title: 'Roles', href: '/admin/roles', icon: Shield },
];

function isActive(href: string, currentPath: string) {
    if (href === '/dashboard') return currentPath === '/dashboard';
    return currentPath.startsWith(href);
}

export function AdminSidebar({ collapsed, onToggle }: { collapsed?: boolean; onToggle?: () => void }) {
    const { auth, url } = usePage<{ auth: Auth; url: string }>().props;
    const currentPath = typeof url === 'string' ? url : window.location.pathname;
    const isAdmin = auth?.user?.role?.name === 'admin';

    return (
        <aside className="flex w-64 flex-col border-r border-gray-200 bg-white transition-all duration-300">
            {/* Header */}
            <div className="flex h-16 items-center justify-between border-b border-gray-200 px-4">
                <div className="flex items-center gap-2">
                    <img src="/logo.png" alt="TAVLO" className="h-8 w-auto" />
                    <span className="rounded bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">
                        ADMIN
                    </span>
                </div>
                {onToggle && (
                    <button onClick={onToggle} className="rounded-lg p-1.5 hover:bg-gray-100">
                        <X className="h-5 w-5" />
                    </button>
                )}
            </div>

            {/* Navigation */}
            <nav className="flex-1 overflow-y-auto py-4">
                {navItems.map((item) => {
                    const active = isActive(item.href, currentPath);
                    if (item.disabled) {
                        return (
                            <div key={item.href}>
                                <div
                                    className="relative flex w-full cursor-not-allowed items-center gap-3 px-4 py-2.5 text-sm text-gray-400"
                                >
                                    <item.icon className="h-5 w-5 shrink-0" />
                                    <span className="flex-1 text-left">{item.title}</span>
                                    <span className="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-400">
                                        Soon
                                    </span>
                                </div>
                            </div>
                        );
                    }
                    return (
                        <div key={item.href}>
                            <Link
                                href={item.href}
                                className={`relative flex w-full items-center gap-3 px-4 py-2.5 text-sm transition-colors ${
                                    active
                                        ? 'border-r-2 border-purple-600 bg-purple-50 text-purple-700'
                                        : 'text-gray-700 hover:bg-gray-50'
                                }`}
                            >
                                <item.icon className="h-5 w-5 shrink-0" />
                                <span className="flex-1 text-left">{item.title}</span>
                            </Link>
                        </div>
                    );
                })}

                {isAdmin && (
                    <>
                        <div className="mx-4 my-3 border-t border-gray-200" />
                        <div className="px-4 pb-1">
                            <span className="text-xs font-medium tracking-wide text-gray-400 uppercase">
                                Administration
                            </span>
                        </div>
                        {adminNavItems.map((item) => {
                            const active = isActive(item.href, currentPath);
                            return (
                                <div key={item.href}>
                                    <Link
                                        href={item.href}
                                        className={`relative flex w-full items-center gap-3 px-4 py-2.5 text-sm transition-colors ${
                                            active
                                                ? 'border-r-2 border-purple-600 bg-purple-50 text-purple-700'
                                                : 'text-gray-700 hover:bg-gray-50'
                                        }`}
                                    >
                                        <item.icon className="h-5 w-5 shrink-0" />
                                        <span className="flex-1 text-left">{item.title}</span>
                                    </Link>
                                </div>
                            );
                        })}
                    </>
                )}
            </nav>

            {/* Footer */}
            <div className="border-t border-gray-200 p-4">
                <div className="text-xs text-gray-500">Logged in as</div>
                <div className="text-sm">{auth?.user?.name ?? 'Unknown'}</div>
            </div>
        </aside>
    );
}
