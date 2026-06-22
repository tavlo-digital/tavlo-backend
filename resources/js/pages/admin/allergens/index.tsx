import { Head, router, useForm } from '@inertiajs/react';
import {
    CircleCheckBig,
    CircleX,
    Edit2,
    Search,
    ShieldAlert,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import AdminLanguageTabs, { languageDirection } from '@/components/admin-language-tabs';
import type { AdminLanguage } from '@/components/admin-language-tabs';
import AdminLayout from '@/layouts/admin-layout';

type Language = AdminLanguage;

type AllergenData = {
    id: number;
    name: string;
    icon: string | null;
    sortOrder: number;
    isActive: boolean;
    translations: Record<string, { name?: string }>;
};

type AllergenForm = {
    _method?: string;
    name: string;
    icon: string;
    sort_order: number;
    is_active: boolean;
    translations: Record<string, { name: string }>;
};

const emptyForm = (languages: Language[]): AllergenForm => ({
    name: '',
    icon: '',
    sort_order: 0,
    is_active: true,
    translations: Object.fromEntries(
        languages.map((l) => [l.code, { name: '' }]),
    ),
});

export default function AdminAllergensIndex({
    allergens,
    languages,
}: {
    allergens: AllergenData[];
    languages: Language[];
}) {
    const [editing, setEditing] = useState<AllergenData | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedLang, setSelectedLang] = useState('en');
    const form = useForm<AllergenForm>(emptyForm(languages));

    const filteredAllergens = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return allergens;
        return allergens.filter((a) => a.name.toLowerCase().includes(query));
    }, [allergens, searchQuery]);

    const stats = [
        {
            label: 'Total Allergens',
            value: allergens.length,
            icon: ShieldAlert,
            iconColor: 'text-gray-600',
        },
        {
            label: 'Active',
            value: allergens.filter((a) => a.isActive).length,
            icon: CircleCheckBig,
            iconColor: 'text-green-600',
        },
        {
            label: 'Inactive',
            value: allergens.filter((a) => !a.isActive).length,
            icon: CircleX,
            iconColor: 'text-red-600',
        },
    ];

    function startEdit(allergen: AllergenData) {
        setEditing(allergen);
        setSelectedLang('en');
        const translations = Object.fromEntries(
            languages.map((l) => [
                l.code,
                { name: allergen.translations[l.code]?.name ?? '' },
            ]),
        );
        form.setData({
            name: allergen.name,
            icon: allergen.icon ?? '',
            sort_order: allergen.sortOrder,
            is_active: allergen.isActive,
            translations,
        });
        form.clearErrors();
    }

    function resetForm() {
        setEditing(null);
        setSelectedLang('en');
        form.setData(emptyForm(languages));
        form.clearErrors();
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: resetForm };

        if (editing) {
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`/admin/allergens/${editing.id}`, options);
            return;
        }

        form.transform((data) => data);
        form.post('/admin/allergens', options);
    }

    function destroy(allergen: AllergenData) {
        if (!confirm(`Delete "${allergen.name}"?`)) return;
        router.delete(`/admin/allergens/${allergen.id}`, {
            preserveScroll: true,
        });
    }

    function setTranslation(lang: string, value: string) {
        form.setData({
            ...form.data,
            name: lang === 'en' ? value : form.data.name,
            translations: {
                ...form.data.translations,
                [lang]: { name: value },
            },
        });
    }

    const selectedLanguage =
        languages.find((language) => language.code === selectedLang) ??
        languages[0];
    const namesByLanguage = Object.fromEntries(
        languages.map((language) => [
            language.code,
            language.code === 'en'
                ? form.data.name
                : form.data.translations[language.code]?.name,
        ]),
    );

    return (
        <AdminLayout>
            <Head title="Allergens" />
            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">
                        Allergens
                    </h1>
                    <p className="mt-1 text-sm text-gray-600">
                        Manage allergens with multi-language translations.
                    </p>
                </div>

                <div>
                    <h2 className="mb-3 text-sm font-medium tracking-wide text-gray-700 uppercase">
                        Overview
                    </h2>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        {stats.map((card) => (
                            <div
                                key={card.label}
                                className="rounded-lg border border-gray-200 bg-white p-4"
                            >
                                <div className="mb-2 flex items-center justify-between">
                                    <card.icon
                                        className={`h-5 w-5 ${card.iconColor}`}
                                        aria-hidden="true"
                                    />
                                </div>
                                <div className="mb-1 text-2xl font-semibold text-gray-900">
                                    {card.value}
                                </div>
                                <div className="text-sm text-gray-600">
                                    {card.label}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_420px]">
                    <div className="space-y-4">
                        <div className="flex items-center justify-between gap-4">
                            <div className="max-w-md flex-1">
                                <div className="relative">
                                    <Search
                                        className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400"
                                        aria-hidden="true"
                                    />
                                    <input
                                        type="text"
                                        placeholder="Search allergens"
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-gray-300 py-2.5 pr-4 pl-11 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    />
                                </div>
                            </div>
                            <div className="text-sm text-gray-600">
                                {filteredAllergens.length} shown
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                            <table className="w-full">
                                <thead className="border-b border-gray-200 bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">
                                            Allergen
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">
                                            Icon
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">
                                            Languages
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">
                                            Sort
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filteredAllergens.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-4 py-8 text-center text-sm text-gray-500"
                                            >
                                                No allergens found.
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredAllergens.map((allergen) => {
                                            const translatedCount =
                                                Object.values(
                                                    allergen.translations,
                                                ).filter(
                                                    (t) =>
                                                        t?.name &&
                                                        t.name.trim() !== '',
                                                ).length;
                                            return (
                                                <tr
                                                    key={allergen.id}
                                                    className="transition-colors hover:bg-gray-50"
                                                >
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium text-gray-900">
                                                            {allergen.name}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-lg">
                                                        {allergen.icon ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span className="text-sm text-gray-600">
                                                            {translatedCount}/
                                                            {languages.length}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span
                                                            className={`rounded-full px-2 py-1 text-xs font-medium ${allergen.isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}`}
                                                        >
                                                            {allergen.isActive
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-gray-600">
                                                        {allergen.sortOrder}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <button
                                                                type="button"
                                                                className="rounded p-1.5 transition-colors hover:bg-gray-100"
                                                                onClick={() =>
                                                                    startEdit(
                                                                        allergen,
                                                                    )
                                                                }
                                                                title="Edit"
                                                            >
                                                                <Edit2 className="h-4 w-4 text-gray-600" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                className="rounded p-1.5 transition-colors hover:bg-red-50"
                                                                onClick={() =>
                                                                    destroy(
                                                                        allergen,
                                                                    )
                                                                }
                                                                title="Delete"
                                                            >
                                                                <Trash2 className="h-4 w-4 text-red-600" />
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

                    <form
                        onSubmit={submit}
                        className="h-fit rounded-lg border border-gray-200 bg-white"
                    >
                        <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                            <div>
                                <h2 className="font-semibold text-gray-900">
                                    {editing ? 'Edit Allergen' : 'Add Allergen'}
                                </h2>
                                {editing && (
                                    <p className="mt-0.5 text-xs text-gray-500">
                                        Editing #{editing.id}
                                    </p>
                                )}
                            </div>
                            {editing && (
                                <button
                                    type="button"
                                    className="rounded p-1.5 transition-colors hover:bg-gray-100"
                                    onClick={resetForm}
                                    title="Cancel"
                                >
                                    <X className="h-4 w-4 text-gray-600" />
                                </button>
                            )}
                        </div>

                        <div className="space-y-4 p-5">
                            <AdminLanguageTabs
                                languages={languages}
                                activeLanguage={selectedLang}
                                onLanguageChange={setSelectedLang}
                                values={namesByLanguage}
                            />

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">
                                    Name (
                                    {selectedLanguage?.name ??
                                        selectedLang.toUpperCase()}
                                    ) *
                                </span>
                                <input
                                    type="text"
                                    value={
                                        selectedLang === 'en'
                                            ? form.data.name
                                            : (form.data.translations[
                                                  selectedLang
                                              ]?.name ?? '')
                                    }
                                    onChange={(e) =>
                                        setTranslation(
                                            selectedLang,
                                            e.target.value,
                                        )
                                    }
                                    dir={languageDirection(selectedLanguage)}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder={`Enter allergen name in ${selectedLanguage?.name ?? selectedLang.toUpperCase()}`}
                                />
                                {selectedLang === 'en' && form.errors.name && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {form.errors.name}
                                    </p>
                                )}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">
                                    Icon (Emoji)
                                </span>
                                <input
                                    type="text"
                                    value={form.data.icon}
                                    onChange={(e) =>
                                        form.setData('icon', e.target.value)
                                    }
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder="e.g. 🌾"
                                />
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">
                                    Sort Order
                                </span>
                                <input
                                    type="number"
                                    min={0}
                                    value={form.data.sort_order}
                                    onChange={(e) =>
                                        form.setData(
                                            'sort_order',
                                            Number(e.target.value),
                                        )
                                    }
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                />
                            </label>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_active',
                                            e.target.checked,
                                        )
                                    }
                                    className="h-4 w-4 rounded border-gray-300"
                                />
                                Active
                            </label>

                            <button
                                type="submit"
                                className="w-full rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-60"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Saving...'
                                    : editing
                                      ? 'Save Allergen'
                                      : 'Add Allergen'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
