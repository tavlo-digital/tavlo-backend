import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';
import type { Role, User } from '@/types';

type AdminUser = Pick<User, 'id' | 'name' | 'email' | 'email_verified_at' | 'created_at'> & { role: Role | null };

type PaginatedUsers = {
    data: AdminUser[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    total: number;
};

export default function AdminUsersIndex({ users }: { users: PaginatedUsers }) {
    function handleDelete(userId: number) {
        if (confirm('Are you sure you want to delete this user?')) {
            router.delete(`/admin/users/${userId}`);
        }
    }

    return (
        <AdminLayout>
            <Head title="Manage Users" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Users</h1>
                        <p className="text-sm text-muted-foreground">{users.total} total users</p>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Name</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Email</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Role</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Verified</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Joined</th>
                                <th className="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-sidebar-border/50">
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-muted/30 transition-colors">
                                    <td className="px-4 py-3 font-medium">{user.name}</td>
                                    <td className="px-4 py-3 text-muted-foreground">{user.email}</td>
                                    <td className="px-4 py-3">
                                        {user.role ? (
                                            <Badge variant={user.role.name === 'admin' ? 'default' : 'secondary'}>
                                                {user.role.label}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">No role</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {user.email_verified_at ? (
                                            <Badge variant="outline" className="text-green-600 border-green-600">Verified</Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-yellow-600 border-yellow-600">Unverified</Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {new Date(user.created_at as string).toLocaleDateString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`/admin/users/${user.id}/edit`}>Edit</Link>
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => handleDelete(user.id)}
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {users.current_page} of {users.last_page}
                        </p>
                        <div className="flex gap-2">
                            {users.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={users.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {users.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={users.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
