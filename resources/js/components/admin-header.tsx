import { usePage } from '@inertiajs/react';
import { Search, CircleCheckBig, User, Moon, Sun } from 'lucide-react';
import { useState, useEffect } from 'react';
import type { Auth } from '@/types';

export function AdminHeader() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [dark, setDark] = useState(false);

    useEffect(() => {
        const storedTheme = localStorage.getItem('theme') ?? localStorage.getItem('appearance');
        const shouldUseDark = storedTheme === 'dark';

        document.documentElement.classList.toggle('dark', shouldUseDark);
        document.documentElement.style.colorScheme = shouldUseDark ? 'dark' : 'light';
        setDark(shouldUseDark);
    }, []);

    const toggleTheme = () => {
        const next = !dark;
        setDark(next);
        document.documentElement.classList.toggle('dark', next);
        document.documentElement.style.colorScheme = next ? 'dark' : 'light';
        localStorage.setItem('theme', next ? 'dark' : 'light');
        localStorage.setItem('appearance', next ? 'dark' : 'light');
    };

    return (
        <div className="sticky top-0 z-10 border-b border-gray-200 bg-white">
            <div className="mx-auto max-w-[1800px] px-6 py-4">
                <div className="flex items-center justify-between gap-6">
                    {/* Search */}
                    <div className="max-w-2xl flex-1">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Search vendors, orders, payments…"
                                className="w-full rounded-lg border border-gray-300 py-2.5 pr-4 pl-11 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                            />
                        </div>
                    </div>

                    {/* Right side */}
                    <div className="flex items-center gap-3">
                        <button
                            onClick={toggleTheme}
                            className="rounded-lg border border-gray-200 p-2 text-gray-600 transition-colors hover:bg-gray-100"
                            title={dark ? 'Switch to light mode' : 'Switch to dark mode'}
                        >
                            {dark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                        </button>

                        <span className="flex items-center gap-2 rounded-lg bg-green-50 px-3 py-1.5">
                            <CircleCheckBig className="h-4 w-4 text-green-600" />
                            <span className="text-sm font-medium text-green-600">All systems operational</span>
                        </span>

                        <div className="text-right">
                            <div className="text-sm font-medium text-gray-900">{auth?.user?.name}</div>
                            <div className="text-xs text-gray-500">{auth?.user?.role?.label ?? 'User'}</div>
                        </div>

                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-purple-600">
                            <User className="h-5 w-5 text-white" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
