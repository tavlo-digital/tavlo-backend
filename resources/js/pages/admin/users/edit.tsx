import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import type { Role, User } from '@/types';

type EditableUser = Pick<User, 'id' | 'name' | 'email' | 'created_at'> & { role_id: number | null; role: Role | null };

export default function AdminUsersEdit({ user, roles }: { user: EditableUser; roles: Role[] }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        role_id: user.role_id ? String(user.role_id) : '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        patch(`/admin/users/${user.id}`);
    }

    return (
        <AdminLayout>
            <Head title={`Edit ${user.name}`} />
            <div className="flex flex-col gap-6 p-6 max-w-xl">
                <div>
                    <h1 className="text-2xl font-bold">Edit User</h1>
                    <p className="text-sm text-muted-foreground">Update name, email, or role for {user.name}.</p>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="off"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="role_id">Role</Label>
                        <select
                            id="role_id"
                            value={data.role_id}
                            onChange={(e) => setData('role_id', e.target.value)}
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="">— No role —</option>
                            {roles.map((role) => (
                                <option key={role.id} value={String(role.id)}>
                                    {role.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.role_id} />
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save changes'}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/users">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
