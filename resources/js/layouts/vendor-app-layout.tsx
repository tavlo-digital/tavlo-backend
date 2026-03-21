import AppLogoIcon from '@/components/app-logo-icon';
import type { Vendor } from '@/hooks/use-vendor-auth';

interface Props {
    children: React.ReactNode;
    user?: Vendor | null;
    onLogout?: () => void;
}

export default function VendorAppLayout({ children, user, onLogout }: Props) {
    return (
        <div className="min-h-svh bg-background">
            {/* Navbar */}
            <header className="border-b bg-card">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
                    <a href="/vendor/dashboard" className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-8 fill-current text-[var(--foreground)] dark:text-white" />
                        </div>
                        <span className="font-semibold text-foreground">Tavlo</span>
                        <span className="text-xs text-muted-foreground">Vendor</span>
                    </a>

                    {user && (
                        <div className="flex items-center gap-4">
                            <span className="text-sm text-muted-foreground">{user.name}</span>
                            <button
                                onClick={onLogout}
                                className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                            >
                                Sign out
                            </button>
                        </div>
                    )}
                </div>
            </header>

            {/* Page content */}
            <main className="mx-auto max-w-6xl px-6 py-8">{children}</main>
        </div>
    );
}
