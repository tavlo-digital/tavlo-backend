import { Head, router, useForm } from '@inertiajs/react';
import { CircleCheckBig, CircleX, Edit2, Globe, Search, Trash2, X } from 'lucide-react';
import type { FormEvent} from 'react';
import { useMemo, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';

type Country = {
    id: number;
    code: string;
    name: string;
    flag: string | null;
    currency: string;
    timezone: string;
    isActive: boolean;
    taxCategoryCount: number;
};

type CountryForm = {
    _method?: string;
    code: string;
    name: string;
    flag: string;
    currency: string;
    timezone: string;
    is_active: boolean;
};

const emptyForm: CountryForm = {
    code: '',
    name: '',
    flag: '',
    currency: 'EUR',
    timezone: 'UTC',
    is_active: true,
};

export default function AdminCountriesIndex({ countries }: { countries: Country[] }) {
    const [editing, setEditing] = useState<Country | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const form = useForm<CountryForm>(emptyForm);

    const filteredCountries = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return countries;

        return countries.filter(
            (c) =>
                c.name.toLowerCase().includes(query) ||
                c.code.toLowerCase().includes(query) ||
                c.currency.toLowerCase().includes(query),
        );
    }, [countries, searchQuery]);

    const stats = [
        {
            label: 'Total Countries',
            value: countries.length,
            icon: Globe,
            iconColor: 'text-gray-600',
        },
        {
            label: 'Active',
            value: countries.filter((c) => c.isActive).length,
            icon: CircleCheckBig,
            iconColor: 'text-green-600',
        },
        {
            label: 'Inactive',
            value: countries.filter((c) => !c.isActive).length,
            icon: CircleX,
            iconColor: 'text-red-600',
        },
        {
            label: 'With Tax Rules',
            value: countries.filter((c) => c.taxCategoryCount > 0).length,
            icon: Globe,
            iconColor: 'text-purple-600',
        },
    ];

    function startEdit(country: Country) {
        setEditing(country);
        form.setData({
            code: country.code,
            name: country.name,
            flag: country.flag ?? '',
            currency: country.currency,
            timezone: country.timezone,
            is_active: country.isActive,
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
            form.post(`/admin/countries/${editing.id}`, options);
            return;
        }

        form.transform((data) => data);
        form.post('/admin/countries', options);
    }

    function destroy(country: Country) {
        if (!confirm(`Delete "${country.name}" (${country.code})?`)) {
            return;
        }

        router.delete(`/admin/countries/${country.id}`, { preserveScroll: true });
    }

    return (
        <AdminLayout>
            <Head title="Countries" />
            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Countries</h1>
                    <p className="mt-1 text-sm text-gray-600">
                        Manage countries available in the system. Countries are used for tax rules and vendor registration.
                    </p>
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
                            <div className="relative max-w-md flex-1">
                                <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                                <input
                                    type="text"
                                    placeholder="Search name, code, or currency"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="w-full rounded-lg border border-gray-300 py-2.5 pr-4 pl-11 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                />
                            </div>
                            <div className="text-sm text-gray-600">{filteredCountries.length} shown</div>
                        </div>

                        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                            <table className="w-full">
                                <thead className="border-b border-gray-200 bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Country</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Code</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Currency</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Timezone</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Tax Rules</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filteredCountries.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-500">
                                                No countries found.
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredCountries.map((country) => (
                                            <tr key={country.id} className="transition-colors hover:bg-gray-50">
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-xl">{country.flag}</span>
                                                        <span className="text-sm font-medium text-gray-900">{country.name}</span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="font-mono text-sm text-gray-600">{country.code}</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="font-mono text-sm text-gray-600">{country.currency}</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="text-sm text-gray-600">{country.timezone}</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="text-sm text-gray-900">{country.taxCategoryCount}</span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`rounded-full px-2 py-1 text-xs font-medium ${
                                                            country.isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'
                                                        }`}
                                                    >
                                                        {country.isActive ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            className="rounded p-1.5 transition-colors hover:bg-gray-100"
                                                            onClick={() => startEdit(country)}
                                                            title="Edit country"
                                                        >
                                                            <Edit2 className="h-4 w-4 text-gray-600" aria-hidden="true" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="rounded p-1.5 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                            onClick={() => destroy(country)}
                                                            disabled={country.taxCategoryCount > 0}
                                                            title={country.taxCategoryCount > 0 ? 'Country has tax categories' : 'Delete country'}
                                                        >
                                                            <Trash2 className="h-4 w-4 text-red-600" aria-hidden="true" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <form onSubmit={submit} className="h-fit rounded-lg border border-gray-200 bg-white">
                        <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                            <div>
                                <h2 className="font-semibold text-gray-900">
                                    {editing ? 'Edit Country' : 'Add Country'}
                                </h2>
                                {editing && <p className="mt-0.5 text-xs text-gray-500">Editing {editing.code}</p>}
                            </div>
                            {editing && (
                                <button type="button" className="rounded p-1.5 transition-colors hover:bg-gray-100" onClick={resetForm} title="Cancel edit">
                                    <X className="h-4 w-4 text-gray-600" aria-hidden="true" />
                                </button>
                            )}
                        </div>

                        <div className="space-y-4 p-5">
                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Country Code</span>
                                <input
                                    type="text"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                                    maxLength={5}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder="e.g. AT, DE, GB"
                                />
                                <p className="mt-1 text-xs text-gray-500">ISO alpha-2 code</p>
                                {form.errors.code && <p className="mt-1 text-xs text-red-600">{form.errors.code}</p>}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Name</span>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder="e.g. Austria, Germany"
                                />
                                {form.errors.name && <p className="mt-1 text-xs text-red-600">{form.errors.name}</p>}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Flag Emoji</span>
                                <input
                                    type="text"
                                    value={form.data.flag}
                                    onChange={(e) => form.setData('flag', e.target.value)}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder="e.g. \u{1F1E6}\u{1F1F9}"
                                />
                                {form.errors.flag && <p className="mt-1 text-xs text-red-600">{form.errors.flag}</p>}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Currency</span>
                                <input
                                    type="text"
                                    value={form.data.currency}
                                    onChange={(e) => form.setData('currency', e.target.value.toUpperCase())}
                                    maxLength={5}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder="e.g. EUR, GBP, USD"
                                />
                                {form.errors.currency && <p className="mt-1 text-xs text-red-600">{form.errors.currency}</p>}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">Timezone</span>
                                <input
                                    type="text"
                                    value={form.data.timezone}
                                    onChange={(e) => form.setData('timezone', e.target.value)}
                                    maxLength={50}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder="e.g. Europe/Vienna, Europe/Berlin"
                                />
                                <p className="mt-1 text-xs text-gray-500">IANA timezone identifier</p>
                                {form.errors.timezone && <p className="mt-1 text-xs text-red-600">{form.errors.timezone}</p>}
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

                            {(form.errors as Record<string, string>).country && (
                                <p className="text-xs text-red-600">{(form.errors as Record<string, string>).country}</p>
                            )}

                            <button
                                type="submit"
                                className="w-full rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-60"
                                disabled={form.processing}
                            >
                                {form.processing ? 'Saving...' : editing ? 'Save Country' : 'Add Country'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
