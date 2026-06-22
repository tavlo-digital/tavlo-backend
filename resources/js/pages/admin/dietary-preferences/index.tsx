import { Head, router, useForm } from '@inertiajs/react';
import { CircleCheckBig, CircleX, Edit2, Leaf, Search, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import AdminLanguageTabs, { languageDirection } from '@/components/admin-language-tabs';
import type { AdminLanguage } from '@/components/admin-language-tabs';
import AdminLayout from '@/layouts/admin-layout';

type Preference = {
    id: number;
    slug: string;
    name: string;
    icon: string | null;
    sortOrder: number;
    isActive: boolean;
    itemCount: number;
    translations: Record<string, { name?: string }>;
};

type PreferenceForm = {
    name: string;
    slug: string;
    icon: string;
    sort_order: number;
    is_active: boolean;
    translations: Record<string, { name: string }>;
};

const emptyForm = (languages: AdminLanguage[]): PreferenceForm => ({
    name: '',
    slug: '',
    icon: '',
    sort_order: 0,
    is_active: true,
    translations: Object.fromEntries(languages.map((language) => [language.code, { name: '' }])),
});

export default function DietaryPreferencesIndex({
    preferences,
    languages,
}: {
    preferences: Preference[];
    languages: AdminLanguage[];
}) {
    const [editing, setEditing] = useState<Preference | null>(null);
    const [search, setSearch] = useState('');
    const [selectedLanguage, setSelectedLanguage] = useState('en');
    const form = useForm<PreferenceForm>(emptyForm(languages));

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();
        if (!query) return preferences;
        return preferences.filter((preference) => (
            preference.name.toLowerCase().includes(query)
            || preference.slug.toLowerCase().includes(query)
        ));
    }, [preferences, search]);

    function startEdit(preference: Preference) {
        setEditing(preference);
        setSelectedLanguage('en');
        form.setData({
            name: preference.name,
            slug: preference.slug,
            icon: preference.icon ?? '',
            sort_order: preference.sortOrder,
            is_active: preference.isActive,
            translations: Object.fromEntries(languages.map((language) => [
                language.code,
                { name: preference.translations[language.code]?.name ?? '' },
            ])),
        });
        form.clearErrors();
    }

    function resetForm() {
        setEditing(null);
        setSelectedLanguage('en');
        form.setData(emptyForm(languages));
        form.clearErrors();
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: resetForm };

        if (editing) {
            form.put(`/admin/dietary-preferences/${editing.id}`, options);
            return;
        }

        form.post('/admin/dietary-preferences', options);
    }

    function destroy(preference: Preference) {
        if (!confirm(`Delete "${preference.name}"?`)) return;
        router.delete(`/admin/dietary-preferences/${preference.id}`, { preserveScroll: true });
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

    const activeLanguage = languages.find((language) => language.code === selectedLanguage) ?? languages[0];
    const namesByLanguage = Object.fromEntries(languages.map((language) => [
        language.code,
        language.code === 'en' ? form.data.name : form.data.translations[language.code]?.name,
    ]));

    const stats = [
        { label: 'Total Preferences', value: preferences.length, icon: Leaf, color: 'text-gray-600' },
        { label: 'Active', value: preferences.filter((preference) => preference.isActive).length, icon: CircleCheckBig, color: 'text-green-600' },
        { label: 'Inactive', value: preferences.filter((preference) => !preference.isActive).length, icon: CircleX, color: 'text-red-600' },
    ];

    return (
        <AdminLayout>
            <Head title="Dietary Preferences" />
            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Dietary Preferences</h1>
                    <p className="mt-1 text-sm text-gray-600">
                        Manage the dietary choices vendors can assign to menu items.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    {stats.map((stat) => (
                        <div key={stat.label} className="rounded-lg border border-gray-200 bg-white p-4">
                            <stat.icon className={`mb-2 h-5 w-5 ${stat.color}`} />
                            <div className="text-2xl font-semibold text-gray-900">{stat.value}</div>
                            <div className="text-sm text-gray-600">{stat.label}</div>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_420px]">
                    <div className="space-y-4">
                        <div className="relative max-w-md">
                            <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                            <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search preferences" className="w-full rounded-lg border border-gray-300 py-2.5 pr-4 pl-11 text-sm focus:ring-2 focus:ring-purple-600 focus:outline-none" />
                        </div>
                        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                            <table className="w-full">
                                <thead className="border-b border-gray-200 bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Preference</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Slug</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Items</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filtered.map((preference) => (
                                        <tr key={preference.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <span className="mr-2 text-lg">{preference.icon ?? '🌿'}</span>
                                                <span className="font-medium text-gray-900">{preference.name}</span>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-sm text-gray-600">{preference.slug}</td>
                                            <td className="px-4 py-3 text-sm text-gray-600">{preference.itemCount}</td>
                                            <td className="px-4 py-3">
                                                <span className={`rounded-full px-2 py-1 text-xs font-medium ${preference.isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                                    {preference.isActive ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-2">
                                                    <button type="button" onClick={() => startEdit(preference)} className="rounded p-1.5 hover:bg-gray-100"><Edit2 className="h-4 w-4 text-gray-600" /></button>
                                                    <button type="button" onClick={() => destroy(preference)} disabled={preference.itemCount > 0} title={preference.itemCount > 0 ? 'Deactivate this preference instead' : 'Delete preference'} className="rounded p-1.5 hover:bg-red-50 disabled:opacity-30"><Trash2 className="h-4 w-4 text-red-600" /></button>
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
                                <h2 className="font-semibold text-gray-900">{editing ? 'Edit Preference' : 'Add Preference'}</h2>
                                {editing && <p className="mt-0.5 text-xs text-gray-500">Editing #{editing.id}</p>}
                            </div>
                            {editing && <button type="button" onClick={resetForm} className="rounded p-1.5 hover:bg-gray-100"><X className="h-4 w-4" /></button>}
                        </div>
                        <div className="space-y-4 p-5">
                            <AdminLanguageTabs languages={languages} activeLanguage={selectedLanguage} onLanguageChange={setSelectedLanguage} values={namesByLanguage} />
                            <label className="block text-sm font-medium text-gray-700">
                                Name ({activeLanguage?.name ?? selectedLanguage.toUpperCase()}) *
                                <input value={selectedLanguage === 'en' ? form.data.name : (form.data.translations[selectedLanguage]?.name ?? '')} onChange={(event) => setTranslation(selectedLanguage, event.target.value)} dir={languageDirection(activeLanguage)} placeholder={`Enter name in ${activeLanguage?.name ?? selectedLanguage}`} className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                                {selectedLanguage === 'en' && form.errors.name && <span className="mt-1 block text-xs text-red-600">{form.errors.name}</span>}
                            </label>
                            <label className="block text-sm font-medium text-gray-700">
                                Slug
                                <input value={form.data.slug} onChange={(event) => form.setData('slug', event.target.value)} placeholder="Generated from English name" className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 font-mono text-sm" />
                                {form.errors.slug && <span className="mt-1 block text-xs text-red-600">{form.errors.slug}</span>}
                            </label>
                            <div className="grid grid-cols-2 gap-3">
                                <label className="block text-sm font-medium text-gray-700">Icon<input value={form.data.icon} onChange={(event) => form.setData('icon', event.target.value)} placeholder="🌱" className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" /></label>
                                <label className="block text-sm font-medium text-gray-700">Sort Order<input type="number" min={0} value={form.data.sort_order} onChange={(event) => form.setData('sort_order', Number(event.target.value))} className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" /></label>
                            </div>
                            <label className="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-gray-300" />Available to vendors</label>
                            {(form.errors as Record<string, string>).preference && <p className="text-xs text-red-600">{(form.errors as Record<string, string>).preference}</p>}
                            <button type="submit" disabled={form.processing} className="w-full rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-60">{form.processing ? 'Saving...' : editing ? 'Save Preference' : 'Add Preference'}</button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
