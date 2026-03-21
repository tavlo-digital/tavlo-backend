import { useEffect } from 'react';
import CustomerAppLayout from '@/layouts/customer-app-layout';
import { useCustomerAuth } from '@/hooks/use-customer-auth';
import { customerToken } from '@/lib/api';

export default function CustomerDashboardPage() {
    const { user, fetchMe, logout } = useCustomerAuth();

    useEffect(() => {
        if (!customerToken.get()) {
            window.location.href = '/customer/login';
            return;
        }
        fetchMe().then((u) => {
            if (!u) window.location.href = '/customer/login';
        });
    }, []);

    const handleLogout = async () => {
        await logout();
        window.location.href = '/customer/login';
    };

    return (
        <CustomerAppLayout user={user} onLogout={handleLogout}>
            <div className="space-y-4">
                <h1 className="text-2xl font-semibold">Dashboard</h1>
                {user ? (
                    <p className="text-muted-foreground">
                        Welcome back, <strong>{user.name}</strong>!
                    </p>
                ) : (
                    <p className="text-muted-foreground">Loading…</p>
                )}
            </div>
        </CustomerAppLayout>
    );
}
