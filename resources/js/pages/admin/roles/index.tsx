import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin-layout';

type RoleRow = {
    id: number;
    name: string;
    label: string;
    users_count: number;
    permissions: string[];
};

export default function AdminRolesIndex({ roles }: { roles: RoleRow[] }) {
    function handleDelete(roleId: number) {
        if (confirm('Delete this role? Users with this role will have their role cleared.')) {
            router.delete(`/admin/roles/${roleId}`);
        }
    }

    return (
        <AdminLayout>
            <Head title="Roles" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Roles</h1>
                        <p className="text-sm text-muted-foreground">{roles.length} roles defined</p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/roles/create">New Role</Link>
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Name</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Label</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Users</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Permissions</th>
                                <th className="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-sidebar-border/50">
                            {roles.map((role) => (
                                <tr key={role.id} className="hover:bg-muted/30 transition-colors">
                                    <td className="px-4 py-3 font-mono text-xs">{role.name}</td>
                                    <td className="px-4 py-3 font-medium">{role.label}</td>
                                    <td className="px-4 py-3 text-muted-foreground">{role.users_count}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {role.permissions.slice(0, 4).map((p) => (
                                                <Badge key={p} variant="secondary" className="font-mono text-xs">{p}</Badge>
                                            ))}
                                            {role.permissions.length > 4 && (
                                                <Badge variant="outline" className="text-xs">+{role.permissions.length - 4} more</Badge>
                                            )}
                                            {role.permissions.length === 0 && (
                                                <span className="text-muted-foreground text-xs">None</span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`/admin/roles/${role.id}/edit`}>Edit</Link>
                                            </Button>
                                            <Button variant="destructive" size="sm" onClick={() => handleDelete(role.id)}>
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
