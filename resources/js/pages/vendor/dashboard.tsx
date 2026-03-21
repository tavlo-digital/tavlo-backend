import { useEffect } from 'react';
import VendorAppLayout from '@/layouts/vendor-app-layout';
import { useVendorAuth } from '@/hooks/use-vendor-auth';
import { vendorToken } from '@/lib/api';

export default function VendorDashboardPage() {
    const { user, fetchMe, logout } = useVendorAuth();

    useEffect(() => {
        if (!vendorToken.get()) {
            window.location.href = '/vendor/login';
            return;
        }
        fetchMe().then((u) => {
            if (!u) window.location.href = '/vendor/login';
        });
    }, []);

    const handleLogout = async () => {
        await logout();
        window.location.href = '/vendor/login';
    };

    return (
        <VendorAppLayout user={user} onLogout={handleLogout}>
            <div className="space-y-4">
                <h1 className="text-2xl font-semibold">Vendor Dashboard</h1>
                {user ? (
                    <p className="text-muted-foreground">
                        Welcome back, <strong>{user.name}</strong> — {user.country}
                    </p>
                ) : (
                    <p className="text-muted-foreground">Loading…</p>
                )}
            </div>
        </VendorAppLayout>
    );
}
