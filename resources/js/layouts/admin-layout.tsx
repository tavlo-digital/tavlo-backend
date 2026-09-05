import { AdminHeader } from '@/components/admin-header';
import { AdminSidebar } from '@/components/admin-sidebar';

interface AdminLayoutProps {
    children: React.ReactNode;
}

export default function AdminLayout({ children }: AdminLayoutProps) {
    return (
        <div className="flex h-screen overflow-hidden bg-gray-50">
            <AdminSidebar />
            <div className="flex flex-1 flex-col overflow-hidden">
                <main className="flex-1 overflow-auto">
                    <div className="min-h-screen bg-gray-50">
                        <AdminHeader />
                        <div className="mx-auto max-w-[1800px]">
                            {children}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
