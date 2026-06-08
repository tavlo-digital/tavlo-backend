import { Head, router, useForm } from '@inertiajs/react';
import { CircleCheckBig, CircleX, Edit2, Receipt, Search, Trash2, X } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';

type TaxCategory = {
    id: number;
    country: string;
    slug: string;
    name: string;
    vatRate: number;
    isActive: boolean;
};

type TaxCategoryForm = {
    _method?: string;
    country: string;
    slug: string;
    name: string;
    vat_rate: number;
    is_active: boolean;
};

type CountryOption = {
    code: string;
    name: string;
    flag: string | null;
};

const emptyForm: TaxCategoryForm = {
    country: 'AT',
    slug: '',
    name: '',
    vat_rate: 0,
    is_active: true,
};

export default function AdminTaxCategoriesIndex({ categories, countries }: { categories: TaxCategory[]; countries: CountryOption[] }) {
    function countryLabel(code: string) {
        return countries.find((c) => c.code === code);
    }
    const [editing, setEditing] = useState<TaxCategory | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [countryFilter, setCountryFilter] = useState<string>('all');
    const form = useForm<TaxCategoryForm>(emptyForm);

    const filteredCategories = useMemo(() => {
        let result = categories;

        if (countryFilter !== 'all') {
            result = result.filter((tc) => tc.country === countryFilter);
        }

        const query = searchQuery.trim().toLowerCase();
        if (query) {
            result = result.filter(
                (tc) =>
                    tc.name.toLowerCase().includes(query) ||
                    tc.slug.toLowerCase().includes(query) ||
                    tc.country.toLowerCase().includes(query),
            );
        }

        return result;
    }, [categories, searchQuery, countryFilter]);

    const uniqueCountries = useMemo(
        () => [...new Set(categories.map((tc) => tc.country))].sort(),
        [categories],
    );

    const stats = [
        {
            label: 'Total Categories',
            value: categories.length,
            icon: Receipt,
            iconColor: 'text-gray-600',
        },
        {
            label: 'Active',
            value: categories.filter((tc) => tc.isActive).length,
            icon: CircleCheckBig,
            iconColor: 'text-green-600',
        },
        {
            label: 'Inactive',
            value: categories.filter((tc) => !tc.isActive).length,
            icon: CircleX,
            iconColor: 'text-red-600',
        },
        {
            label: 'Countries',
            value: uniqueCountries.length,
            icon: Receipt,
            iconColor: 'text-purple-600',
        },
    ];

    function startEdit(tc: TaxCategory) {
        setEditing(tc);
        form.setData({
            country: tc.country,
            slug: tc.slug,
            name: tc.name,
            vat_rate: tc.vatRate,
            is_active: tc.isActive,
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
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`/admin/tax-categories/${editing.id}`, options);
            return;
        }

        form.transform((data) => data);
        form.post('/admin/tax-categories', options);
    }

    function destroy(tc: TaxCategory) {
        if (!confirm(`Delete tax category "${tc.name}" (${tc.country})?`)) {
            return;
        }

        router.delete(`/admin/tax-categories/${tc.id}`, { preserveScroll: true });
    }

    return (
        <AdminLayout>
            <Head title="Tax Management" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Tax Management</h1>
                        <p className="mt-1 text-sm text-gray-600">
                            Manage VAT categories and rates by country. These are applied automatically to vendor menu items.
                        </p>
                    </div>
                </div>

                <div>
                    <h2 className="mb-3 text-sm font-medium tracking-wide text-gray-700 uppercase">Overview</h2>
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
                            <div className="flex flex-1 items-center gap-3">
                                <div className="relative max-w-md flex-1">
                                    <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                                    <input
                                        type="text"
                                        placeholder="Search name, slug, or country"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="w-full rounded-lg border border-gray-300 py-2.5 pr-4 pl-11 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    />
                                </div>
                                <select
                                    value={countryFilter}
                                    onChange={(e) => setCountryFilter(e.target.value)}
                                    className="rounded-lg border border-gray-300 py-2.5 pr-8 pl-3 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                >
                                    <option value="all">All Countries</option>
                                    {uniqueCountries.map((code) => {
                                        const c = countryLabel(code);
                                        return (
                                            <option key={code} value={code}>
                                                {c ? `${c.flag} ${c.name}` : code}
                                            </option>
                                        );
                                    })}
                                </select>
                            </div>
                            <div className="text-sm text-gray-600">{filteredCategories.length} shown</div>
                        </div>

                        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                            <table className="w-full">
                                <thead className="border-b border-gray-200 bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Country</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Name</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Slug</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">VAT Rate</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filteredCategories.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">
                                                No tax categories found.
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredCategories.map((tc) => {
                                            const c = countryLabel(tc.country);
                                            return (
                                                <tr key={tc.id} className="transition-colors hover:bg-gray-50">
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-lg">{c?.flag ?? ''}</span>
                                                            <span className="text-sm font-medium text-gray-900">{c?.name ?? tc.country}</span>
                                                            <span className="font-mono text-xs text-gray-500">({tc.country})</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span className="text-sm font-medium text-gray-900">{tc.name}</span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span className="font-mono text-sm text-gray-600">{tc.slug}</span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span className="font-mono text-sm font-semibold text-gray-900">{tc.vatRate}%</span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span
                                                            className={`rounded-full px-2 py-1 text-xs font-medium ${
                                                                tc.isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'
                                                            }`}
                                                        >
                                                            {tc.isActive ? 'Active' : 'Inactive'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <button
                                                                type="button"
                                                                className="rounded p-1.5 transition-colors hover:bg-gray-100"
                                                                onClick={() => startEdit(tc)}
                                                                title="Edit tax category"
                                                            >
                                                                <Edit2 className="h-4 w-4 text-gray-600" aria-hidden="true" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className="rounded p-1.5 transition-colors hover:bg-red-50"
                                                                onClick={() => destroy(tc)}
                                                                title="Delete tax category"
                                                            >
                                                                <Trash2 className="h-4 w-4 text-red-600" aria-hidden="true" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <form onSubmit={submit} className="h-fit rounded-lg border border-gray-200 bg-white">
                        <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                            <div>
                                <h2 className="font-semibold text-gray-900">
                                    {editing ? 'Edit Tax Category' : 'Add Tax Category'}
                                </h2>
                                {editing && (
                                    <p className="mt-0.5 text-xs text-gray-500">
                                        Editing TC-{editing.id}
                                    </p>
                                )}
                            </div>
                            {editing && (
                                <button type="button" className="rounded p-1.5 transition-colors hover:bg-gray-100" onClick={resetForm} title="Cancel edit">
                                    <X className="h-4 w-4 text-gray-600" aria-hidden="true" />
                                </button>
                            )}
                        </div>

                        <div className="space-y-4 p-5">
                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Country</span>
                                <select
                                    value={form.data.country}
                                    onChange={(e) => form.setData('country', e.target.value)}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                >
                                    {countries.map((c) => (
                                        <option key={c.code} value={c.code}>
                                            {c.flag ?? ''} {c.name} ({c.code})
                                        </option>
                                    ))}
                                </select>
                                {form.errors.country && <p className="mt-1 text-xs text-red-600">{form.errors.country}</p>}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Name</span>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder="e.g. Food, Beverage (Non-Alcoholic)"
                                />
                                {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Slug</span>
                                <input
                                    type="text"
                                    value={form.data.slug}
                                    onChange={(e) => form.setData('slug', e.target.value)}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder="e.g. food, beverage_non_alcoholic"
                                />
                                <p className="mt-1 text-xs text-gray-500">Unique per country. Used internally to match items.</p>
                                {form.errors.slug && <p className="mt-1 text-xs text-red-600">{form.errors.slug}</p>}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">VAT Rate (%)</span>
                                <input
                                    type="number"
                                    min={0}
                                    max={100}
                                    step={0.01}
                                    value={form.data.vat_rate}
                                    onChange={(e) => form.setData('vat_rate', parseFloat(e.target.value) || 0)}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                />
                                {form.errors.vat_rate && <p className="mt-1 text-xs text-red-600">{form.errors.vat_rate}</p>}
                            </label>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(e) => form.setData('is_active', e.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300"
                                />
                                Active
                            </label>

                            <button
                                type="submit"
                                className="w-full rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-60"
                                disabled={form.processing}
                            >
                                {form.processing ? 'Saving...' : editing ? 'Save Tax Category' : 'Add Tax Category'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
