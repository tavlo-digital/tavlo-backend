import { Head, router, useForm } from '@inertiajs/react';
import { Edit2, Plus, Tags, Trash2, X } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';

type MasterMenuCategory = {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    sortOrder: number;
    isActive: boolean;
    vendorCount: number;
};

type CategoryForm = {
    name: string;
    icon: string;
    sort_order: number;
    is_active: boolean;
};

const emptyForm: CategoryForm = {
    name: '',
    icon: '',
    sort_order: 0,
    is_active: true,
};

export default function AdminMenuCategoriesIndex({ categories }: { categories: MasterMenuCategory[] }) {
    const [editing, setEditing] = useState<MasterMenuCategory | null>(null);
    const form = useForm<CategoryForm>(emptyForm);

    function startEdit(category: MasterMenuCategory) {
        setEditing(category);
        form.setData({
            name: category.name,
            icon: category.icon ?? '',
            sort_order: category.sortOrder,
            is_active: category.isActive,
        });
        form.clearErrors();
    }

    function resetForm() {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: resetForm,
        };

        if (editing) {
            form.put(`/admin/menu-categories/${editing.id}`, options);
            return;
        }

        form.post('/admin/menu-categories', options);
    }

    function destroy(category: MasterMenuCategory) {
        if (!confirm(`Delete "${category.name}"?`)) {
            return;
        }

        router.delete(`/admin/menu-categories/${category.id}`, { preserveScroll: true });
    }

    return (
        <AdminLayout>
            <Head title="Menu Categories" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Menu Categories</h1>
                        <p className="text-sm text-muted-foreground">
                            Master category names and icons that vendors can select for their menus.
                        </p>
                    </div>
                    <Badge variant="outline" className="w-fit">{categories.length} total</Badge>
                </div>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_380px]">
                    <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-white dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-muted-foreground">Category</th>
                                    <th className="px-4 py-3 text-left font-medium text-muted-foreground">Slug</th>
                                    <th className="px-4 py-3 text-left font-medium text-muted-foreground">Vendors</th>
                                    <th className="px-4 py-3 text-left font-medium text-muted-foreground">Status</th>
                                    <th className="px-4 py-3 text-right font-medium text-muted-foreground">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/50">
                                {categories.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-12 text-center text-muted-foreground">
                                            No master categories yet.
                                        </td>
                                    </tr>
                                ) : categories.map((category) => (
                                    <tr key={category.id} className="transition-colors hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <span className="flex h-9 w-9 items-center justify-center rounded-lg border bg-white text-lg">
                                                    {category.icon || <Tags className="h-4 w-4 text-muted-foreground" />}
                                                </span>
                                                <div>
                                                    <div className="font-medium">{category.name}</div>
                                                    <div className="text-xs text-muted-foreground">Sort {category.sortOrder}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">{category.slug}</td>
                                        <td className="px-4 py-3">{category.vendorCount}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={category.isActive ? 'default' : 'secondary'}>
                                                {category.isActive ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button variant="outline" size="sm" onClick={() => startEdit(category)}>
                                                    <Edit2 className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => destroy(category)}
                                                    disabled={category.vendorCount > 0}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <form onSubmit={submit} className="h-fit rounded-xl border border-sidebar-border/70 bg-white p-5 dark:border-sidebar-border">
                        <div className="mb-5 flex items-center justify-between">
                            <h2 className="font-semibold">{editing ? 'Edit Category' : 'Add Category'}</h2>
                            {editing && (
                                <Button type="button" variant="ghost" size="sm" onClick={resetForm}>
                                    <X className="h-4 w-4" />
                                </Button>
                            )}
                        </div>

                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    className="mt-1.5"
                                    placeholder="Pizza, Pasta, Desserts"
                                />
                                {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                            </div>

                            <div>
                                <Label htmlFor="icon">Icon</Label>
                                <Input
                                    id="icon"
                                    value={form.data.icon}
                                    onChange={(event) => form.setData('icon', event.target.value)}
                                    className="mt-1.5"
                                    placeholder="🍕"
                                    maxLength={64}
                                />
                                {form.errors.icon && <p className="mt-1 text-xs text-red-600">{form.errors.icon}</p>}
                            </div>

                            <div>
                                <Label htmlFor="sort_order">Sort Order</Label>
                                <Input
                                    id="sort_order"
                                    type="number"
                                    min={0}
                                    value={form.data.sort_order}
                                    onChange={(event) => form.setData('sort_order', Number(event.target.value))}
                                    className="mt-1.5"
                                />
                                {form.errors.sort_order && <p className="mt-1 text-xs text-red-600">{form.errors.sort_order}</p>}
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(event) => form.setData('is_active', event.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300"
                                />
                                Available for vendors
                            </label>

                            {(form.errors as Record<string, string>).category && (
                                <p className="text-xs text-red-600">{(form.errors as Record<string, string>).category}</p>
                            )}

                            <Button type="submit" className="w-full" disabled={form.processing}>
                                <Plus className="h-4 w-4" />
                                {form.processing ? 'Saving...' : editing ? 'Save Category' : 'Add Category'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
