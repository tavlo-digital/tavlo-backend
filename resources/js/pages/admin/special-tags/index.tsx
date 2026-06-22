import { Head, router, useForm } from '@inertiajs/react';
import {
    CircleCheckBig,
    CircleX,
    Edit2,
    Search,
    Tags,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import AdminLanguageTabs, { languageDirection } from '@/components/admin-language-tabs';
import type { AdminLanguage } from '@/components/admin-language-tabs';
import AdminLayout from '@/layouts/admin-layout';

type Language = AdminLanguage;

type TagData = {
    id: number;
    slug: string;
    label: string;
    icon: string | null;
    sortOrder: number;
    isActive: boolean;
    translations: Record<string, { label?: string }>;
};

type TagForm = {
    _method?: string;
    label: string;
    slug: string;
    icon: string;
    sort_order: number;
    is_active: boolean;
    translations: Record<string, { label: string }>;
};

const emptyForm = (languages: Language[]): TagForm => ({
    label: '',
    slug: '',
    icon: '',
    sort_order: 0,
    is_active: true,
    translations: Object.fromEntries(
        languages.map((l) => [l.code, { label: '' }]),
    ),
});

export default function AdminSpecialTagsIndex({
    tags,
    languages,
}: {
    tags: TagData[];
    languages: Language[];
}) {
    const [editing, setEditing] = useState<TagData | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedLang, setSelectedLang] = useState('en');
    const form = useForm<TagForm>(emptyForm(languages));

    const filteredTags = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        if (!query) return tags;
        return tags.filter(
            (t) =>
                t.label.toLowerCase().includes(query) ||
                t.slug.toLowerCase().includes(query),
        );
    }, [tags, searchQuery]);

    const stats = [
        {
            label: 'Total Tags',
            value: tags.length,
            icon: Tags,
            iconColor: 'text-gray-600',
        },
        {
            label: 'Active',
            value: tags.filter((t) => t.isActive).length,
            icon: CircleCheckBig,
            iconColor: 'text-green-600',
        },
        {
            label: 'Inactive',
            value: tags.filter((t) => !t.isActive).length,
            icon: CircleX,
            iconColor: 'text-red-600',
        },
    ];

    function startEdit(tag: TagData) {
        setEditing(tag);
        setSelectedLang('en');
        const translations = Object.fromEntries(
            languages.map((l) => [
                l.code,
                { label: tag.translations[l.code]?.label ?? '' },
            ]),
        );
        form.setData({
            label: tag.label,
            slug: tag.slug,
            icon: tag.icon ?? '',
            sort_order: tag.sortOrder,
            is_active: tag.isActive,
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
            form.post(`/admin/special-tags/${editing.id}`, options);
            return;
        }

        form.transform((data) => data);
        form.post('/admin/special-tags', options);
    }

    function destroy(tag: TagData) {
        if (!confirm(`Delete "${tag.label}"?`)) return;
        router.delete(`/admin/special-tags/${tag.id}`, {
            preserveScroll: true,
        });
    }

    function setTranslation(lang: string, value: string) {
        form.setData({
            ...form.data,
            label: lang === 'en' ? value : form.data.label,
            translations: {
                ...form.data.translations,
                [lang]: { label: value },
            },
        });
    }

    const selectedLanguage =
        languages.find((language) => language.code === selectedLang) ??
        languages[0];
    const labelsByLanguage = Object.fromEntries(
        languages.map((language) => [
            language.code,
            language.code === 'en'
                ? form.data.label
                : form.data.translations[language.code]?.label,
        ]),
    );

    return (
        <AdminLayout>
            <Head title="Special Tags" />
            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">
                        Special Tags
                    </h1>
                    <p className="mt-1 text-sm text-gray-600">
                        Manage special tags (dietary preferences, badges) with
                        multi-language translations.
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
                                        placeholder="Search tags"
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                        className="w-full rounded-lg border border-gray-300 py-2.5 pr-4 pl-11 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    />
                                </div>
                            </div>
                            <div className="text-sm text-gray-600">
                                {filteredTags.length} shown
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                            <table className="w-full">
                                <thead className="border-b border-gray-200 bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">
                                            Label
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">
                                            Slug
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
                                    {filteredTags.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-4 py-8 text-center text-sm text-gray-500"
                                            >
                                                No tags found.
                                            </td>
                                        </tr>
                                    ) : (
                                        filteredTags.map((tag) => {
                                            const translatedCount =
                                                Object.values(
                                                    tag.translations,
                                                ).filter(
                                                    (t) =>
                                                        t?.label &&
                                                        t.label.trim() !== '',
                                                ).length;
                                            return (
                                                <tr
                                                    key={tag.id}
                                                    className="transition-colors hover:bg-gray-50"
                                                >
                                                    <td className="px-4 py-3 font-medium text-gray-900">
                                                        {tag.label}
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-sm text-gray-600">
                                                        {tag.slug}
                                                    </td>
                                                    <td className="px-4 py-3 text-lg">
                                                        {tag.icon ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-gray-600">
                                                        {translatedCount}/
                                                        {languages.length}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span
                                                            className={`rounded-full px-2 py-1 text-xs font-medium ${tag.isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'}`}
                                                        >
                                                            {tag.isActive
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-gray-600">
                                                        {tag.sortOrder}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center gap-2">
                                                            <button
                                                                type="button"
                                                                className="rounded p-1.5 transition-colors hover:bg-gray-100"
                                                                onClick={() =>
                                                                    startEdit(
                                                                        tag,
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
                                                                    destroy(tag)
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
                                    {editing ? 'Edit Tag' : 'Add Tag'}
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
                                values={labelsByLanguage}
                            />

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">
                                    Label (
                                    {selectedLanguage?.name ??
                                        selectedLang.toUpperCase()}
                                    ) *
                                </span>
                                <input
                                    type="text"
                                    value={
                                        selectedLang === 'en'
                                            ? form.data.label
                                            : (form.data.translations[
                                                  selectedLang
                                              ]?.label ?? '')
                                    }
                                    onChange={(e) =>
                                        setTranslation(
                                            selectedLang,
                                            e.target.value,
                                        )
                                    }
                                    dir={languageDirection(selectedLanguage)}
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder={`Enter tag label in ${selectedLanguage?.name ?? selectedLang.toUpperCase()}`}
                                />
                                {selectedLang === 'en' && form.errors.label && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {form.errors.label}
                                    </p>
                                )}
                            </label>

                            <label className="block">
                                <span className="text-sm font-medium text-gray-700">
                                    Slug
                                </span>
                                <input
                                    type="text"
                                    value={form.data.slug}
                                    onChange={(e) =>
                                        form.setData('slug', e.target.value)
                                    }
                                    className="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    placeholder="auto-generated if empty"
                                />
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
                                    placeholder="e.g. 🌿"
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
                                      ? 'Save Tag'
                                      : 'Add Tag'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
