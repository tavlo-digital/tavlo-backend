import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';

type Permission = { id: number; name: string; label: string; group: string | null };
type RoleData = { id: number; name: string; label: string; permissions: number[] };

export default function AdminRolesEdit({ role, permissions }: { role: RoleData; permissions: Permission[] }) {
    const { data, setData, patch, processing, errors } = useForm({
        label: role.label,
        permissions: role.permissions as number[],
    });

    function togglePermission(id: number) {
        setData('permissions', data.permissions.includes(id)
            ? data.permissions.filter((p) => p !== id)
            : [...data.permissions, id],
        );
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        patch(`/admin/roles/${role.id}`);
    }

    const groups = permissions.reduce<Record<string, Permission[]>>((acc, p) => {
        const key = p.group ?? 'Other';
        (acc[key] ??= []).push(p);
        return acc;
    }, {});

    return (
        <AdminLayout>
            <Head title={`Edit ${role.label}`} />
            <div className="flex flex-col gap-6 p-6 max-w-xl">
                <div>
                    <h1 className="text-2xl font-bold">Edit Role</h1>
                    <p className="text-sm text-muted-foreground font-mono">{role.name}</p>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="label">Label</Label>
                        <Input id="label" value={data.label} onChange={(e) => setData('label', e.target.value)} />
                        <InputError message={errors.label} />
                    </div>

                    <div className="flex flex-col gap-3">
                        <Label>Permissions</Label>
                        {Object.entries(groups).map(([group, perms]) => (
                            <div key={group} className="rounded-lg border border-sidebar-border/70 p-3 flex flex-col gap-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{group}</p>
                                {perms.map((p) => (
                                    <label key={p.id} className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.permissions.includes(p.id)}
                                            onChange={() => togglePermission(p.id)}
                                            className="rounded"
                                        />
                                        <span className="text-sm">{p.label}</span>
                                        <span className="text-xs text-muted-foreground font-mono">{p.name}</span>
                                    </label>
                                ))}
                            </div>
                        ))}
                        <InputError message={errors.permissions} />
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <Button type="submit" disabled={processing}>{processing ? 'Saving…' : 'Save changes'}</Button>
                        <Button variant="outline" asChild><Link href="/admin/roles">Cancel</Link></Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
