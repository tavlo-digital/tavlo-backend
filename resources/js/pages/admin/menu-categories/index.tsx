import { Head, router, useForm } from '@inertiajs/react';
import { CircleCheckBig, CircleX, Edit2, Search, Tags, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ChangeEvent, FormEvent } from 'react';
import AdminLanguageTabs, { languageDirection } from '@/components/admin-language-tabs';
import type { AdminLanguage } from '@/components/admin-language-tabs';
import AdminLayout from '@/layouts/admin-layout';

type MasterMenuCategory = {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    sortOrder: number;
    isActive: boolean;
    vendorCount: number;
    translations: Record<string, { name?: string }>;
};

type CategoryForm = {
    _method?: string;
    name: string;
    icon: File | null;
    remove_icon: boolean;
    sort_order: number;
    is_active: boolean;
    translations: Record<string, { name: string }>;
};

const emptyForm = (languages: AdminLanguage[]): CategoryForm => ({
    name: '',
    icon: null,
    remove_icon: false,
    sort_order: 0,
    is_active: true,
    translations: Object.fromEntries(languages.map((language) => [language.code, { name: '' }])),
});

export default function AdminMenuCategoriesIndex({ categories, languages }: { categories: MasterMenuCategory[]; languages: AdminLanguage[] }) {
    const [editing, setEditing] = useState<MasterMenuCategory | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedLang, setSelectedLang] = useState('en');
    const [localIconPreview, setLocalIconPreview] = useState<string | null>(null);
    const [iconInputKey, setIconInputKey] = useState(0);
    const form = useForm<CategoryForm>(emptyForm(languages));

    const filteredCategories = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();

        if (!query) {
            return categories;
        }

        return categories.filter((category) => (
            category.name.toLowerCase().includes(query)
            || category.slug.toLowerCase().includes(query)
        ));
    }, [categories, searchQuery]);

    const stats = [
        {
            label: 'Total Categories',
            value: categories.length,
            icon: Tags,
            iconColor: 'text-gray-600',
        },
        {
            label: 'Active',
            value: categories.filter((category) => category.isActive).length,
            icon: CircleCheckBig,
            iconColor: 'text-green-600',
        },
        {
            label: 'Inactive',
            value: categories.filter((category) => !category.isActive).length,
            icon: CircleX,
            iconColor: 'text-red-600',
        },
        {
            label: 'Used by Vendors',
            value: categories.filter((category) => category.vendorCount > 0).length,
            icon: Tags,
            iconColor: 'text-purple-600',
        },
    ];

    function startEdit(category: MasterMenuCategory) {
        setEditing(category);
        setSelectedLang('en');
        setLocalIconPreview(null);
        setIconInputKey((key) => key + 1);
        form.setData({
            name: category.name,
            icon: null,
            remove_icon: false,
            sort_order: category.sortOrder,
            is_active: category.isActive,
            translations: Object.fromEntries(languages.map((language) => [language.code, { name: category.translations[language.code]?.name ?? '' }])),
        });
        form.clearErrors();
    }

    function resetForm() {
        setEditing(null);
        setSelectedLang('en');
        setLocalIconPreview(null);
        setIconInputKey((key) => key + 1);
        form.setData(emptyForm(languages));
        form.clearErrors();
    }

    function changeIcon(event: ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0] ?? null;

        form.setData('icon', file);
        form.setData('remove_icon', false);

        if (!file) {
            setLocalIconPreview(null);
            return;
        }

        setLocalIconPreview(URL.createObjectURL(file));
    }

    function removeIcon() {
        form.setData('icon', null);
        form.setData('remove_icon', true);
        setLocalIconPreview(null);
        setIconInputKey((key) => key + 1);
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: resetForm,
        };

        if (editing) {
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`/admin/menu-categories/${editing.id}`, options);
            return;
        }

        form.transform((data) => data);
        form.post('/admin/menu-categories', options);
    }

    function destroy(category: MasterMenuCategory) {
        if (!confirm(`Delete "${category.name}"?`)) {
            return;
        }

        router.delete(`/admin/menu-categories/${category.id}`, { preserveScroll: true });
    }

    function setTranslation(language: string, value: string) {
        form.setData({
            ...form.data,
            name: language === 'en' ? value : form.data.name,
            translations: {
                ...form.data.translations,
                [language]: { name: value },
            },
        });
    }

    const previewIcon = form.data.remove_icon ? null : (localIconPreview ?? editing?.icon ?? null);
    const selectedLanguage = languages.find((language) => language.code === selectedLang) ?? languages[0];
    const namesByLanguage = Object.fromEntries(languages.map((language) => [language.code, language.code === 'en' ? form.data.name : form.data.translations[language.code]?.name]));

    return (
        <AdminLayout>
            <Head title="Menu Categories" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Menu Categories</h1>
                        <p className="mt-1 text-sm text-gray-600">
                            Master category names and icons that vendors can select for their menus.
                        </p>
                    </div>
                </div>

                <div>
                    <h2 className="mb-3 text-sm font-medium tracking-wide text-gray-700 uppercase">Category Overview</h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {stats.map((card) => (
                            <div key={card.label} className="rounded-lg border border-gray-200 bg-white p-4">
                                <div className="mb-2 flex items-center justify-between">
                                    <card.icon className={`h-5 w-5 ${card.iconColor}`} aria-hidden="true" />
                                </div>
                                <div className="mb-1 text-2xl font-semibold text-gray-900">{card.value}</div>
                                <div className="text-sm text-gray-600">{card.label}</div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_380px]">
                    <div className="space-y-4">
                        <div className="flex items-center justify-between gap-4">
                            <div className="max-w-md flex-1">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                                    <input
                                        type="text"
                                        placeholder="Search category or slug"
                                        value={searchQuery}
                                        onChange={(event) => setSearchQuery(event.target.value)}
                                        className="w-full rounded-lg border border-gray-300 py-2.5 pr-4 pl-11 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    />
                                </div>
                            </div>
                            <div className="text-sm text-gray-600">
                                {filteredCategories.length} shown
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                            <table className="w-full">
                                <thead className="border-b border-gray-200 bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Category</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Slug</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Vendors</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Sort</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filteredCategories.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">
                                                No categories found.
                                            </td>
                                        </tr>
                                    ) : filteredCategories.map((category) => (
                                        <tr key={category.id} className="transition-colors hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <span className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-lg">
                                                        {category.icon ? (
                                                            <img src={category.icon} alt="" className="h-7 w-7 rounded-md object-cover" />
                                                        ) : (
                                                            <Tags className="h-4 w-4 text-gray-400" />
                                                        )}
                                                    </span>
                                                    <div>
                                                        <div className="font-medium text-gray-900">{category.name}</div>
                                                        <div className="text-xs font-mono text-gray-500">MC-{category.id}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-mono text-sm text-gray-600">{category.slug}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="text-sm text-gray-900">{category.vendorCount}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className={`rounded-full px-2 py-1 text-xs font-medium ${category.isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}`}>
                                                    {category.isActive ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="text-sm text-gray-600">{category.sortOrder}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        className="rounded p-1.5 transition-colors hover:bg-gray-100"
                                                        onClick={() => startEdit(category)}
                                                        title="Edit category"
                                                    >
                                                        <Edit2 className="h-4 w-4 text-gray-600" aria-hidden="true" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="rounded p-1.5 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                        onClick={() => destroy(category)}
                                                        disabled={category.vendorCount > 0}
                                                        title={category.vendorCount > 0 ? 'Category is used by vendors' : 'Delete category'}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-red-600" aria-hidden="true" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <form onSubmit={submit} className="h-fit rounded-lg border border-gray-200 bg-white">
                        <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                            <div>
                                <h2 className="font-semibold text-gray-900">{editing ? 'Edit Category' : 'Add Category'}</h2>
                                {editing && <p className="mt-0.5 text-xs text-gray-500">Editing MC-{editing.id}</p>}
                            </div>
                            {editing && (
                                <button type="button" className="rounded p-1.5 transition-colors hover:bg-gray-100" onClick={resetForm} title="Cancel edit">
                                    <X className="h-4 w-4 text-gray-600" aria-hidden="true" />
                                </button>
                            )}
                        </div>

                        <div className="space-y-4 p-5">
                            <AdminLanguageTabs languages={languages} activeLanguage={selectedLang} onLanguageChange={setSelectedLang} values={namesByLanguage} />

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Name ({selectedLanguage?.name ?? selectedLang.toUpperCase()}) *</span>
                                <input
                                    id="name"
                                    type="text"
                                    value={selectedLang === 'en' ? form.data.name : (form.data.translations[selectedLang]?.name ?? '')}
                                    onChange={(event) => setTranslation(selectedLang, event.target.value)}
                                    dir={languageDirection(selectedLanguage)}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder={`Enter category name in ${selectedLanguage?.name ?? selectedLang.toUpperCase()}`}
                                />
                                {selectedLang === 'en' && form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Icon Image</span>
                                <div className="mt-1.5 space-y-2">
                                    <input
                                        key={iconInputKey}
                                        id="icon"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={changeIcon}
                                        className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    />
                                    {editing && previewIcon && (
                                        <div className="flex items-center gap-3">
                                            <span className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-300 bg-white">
                                                <img src={previewIcon} alt="" className="h-full w-full object-cover" />
                                            </span>
                                            <button
                                                type="button"
                                                onClick={removeIcon}
                                                className="text-xs font-medium text-red-600 hover:text-red-700"
                                            >
                                                Remove image
                                            </button>
                                        </div>
                                    )}
                                </div>
                                {form.errors.icon && <p className="mt-1 text-xs text-red-600">{form.errors.icon}</p>}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Sort Order</span>
                                <input
                                    id="sort_order"
                                    type="number"
                                    min={0}
                                    value={form.data.sort_order}
                                    onChange={(event) => form.setData('sort_order', Number(event.target.value))}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                />
                                {form.errors.sort_order && <p className="mt-1 text-xs text-red-600">{form.errors.sort_order}</p>}
                            </label>

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

                            <button
                                type="submit"
                                className="w-full rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-60"
                                disabled={form.processing}
                            >
                                {form.processing ? 'Saving...' : editing ? 'Save Category' : 'Add Category'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
