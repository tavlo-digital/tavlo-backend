import { Head, router, useForm } from '@inertiajs/react';
import { Bell, Edit2, Globe2, Languages, Plus, Save, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import AdminLayout from '@/layouts/admin-layout';

type Language = {
    id: number;
    code: string;
    name: string;
    nativeName: string | null;
    flag: string | null;
    direction: 'ltr' | 'rtl';
    sortOrder: number;
    isActive: boolean;
};

type ActiveLanguage = Pick<Language, 'code' | 'name' | 'nativeName' | 'flag' | 'direction'>;

type NotificationTemplate = {
    key: string;
    event: string;
    label: string;
    defaultMessage: string;
    placeholders: string[];
    messages: Record<string, string>;
};

type LanguageForm = {
    code: string;
    name: string;
    native_name: string;
    flag: string;
    direction: 'ltr' | 'rtl';
    sort_order: number;
    is_active: boolean;
};

const emptyLanguageForm: LanguageForm = {
    code: '',
    name: '',
    native_name: '',
    flag: '',
    direction: 'ltr',
    sort_order: 0,
    is_active: true,
};

export default function AdminLanguagesIndex({
    languages,
    activeLanguages,
    notificationTemplates,
}: {
    languages: Language[];
    activeLanguages: ActiveLanguage[];
    notificationTemplates: NotificationTemplate[];
}) {
    const [activeTab, setActiveTab] = useState<'languages' | 'notifications'>('languages');
    const [editing, setEditing] = useState<Language | null>(null);
    const [selectedLanguage, setSelectedLanguage] = useState(activeLanguages[0]?.code ?? 'en');
    const languageForm = useForm<LanguageForm>(emptyLanguageForm);

    const initialMessages = useMemo(
        () => Object.fromEntries(
            notificationTemplates.map((template) => [
                template.key,
                Object.fromEntries(
                    activeLanguages.map((language) => [
                        language.code,
                        template.messages[language.code] ?? '',
                    ]),
                ),
            ]),
        ),
        [activeLanguages, notificationTemplates],
    );
    const notificationForm = useForm<{ messages: Record<string, Record<string, string>> }>({
        messages: initialMessages,
    });

    function startEdit(language: Language) {
        setEditing(language);
        languageForm.setData({
            code: language.code,
            name: language.name,
            native_name: language.nativeName ?? '',
            flag: language.flag ?? '',
            direction: language.direction,
            sort_order: language.sortOrder,
            is_active: language.isActive,
        });
        languageForm.clearErrors();
    }

    function resetLanguageForm() {
        setEditing(null);
        languageForm.setData(emptyLanguageForm);
        languageForm.clearErrors();
    }

    function submitLanguage(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: resetLanguageForm };

        if (editing) {
            languageForm.put(`/admin/languages/${editing.id}`, options);
            return;
        }

        languageForm.post('/admin/languages', options);
    }

    function destroyLanguage(language: Language) {
        if (!confirm(`Delete ${language.name} (${language.code})?`)) return;
        router.delete(`/admin/languages/${language.id}`, { preserveScroll: true });
    }

    function submitNotifications(event: FormEvent) {
        event.preventDefault();
        notificationForm.post('/admin/languages/notifications', { preserveScroll: true });
    }

    function updateMessage(templateKey: string, language: string, value: string) {
        notificationForm.setData('messages', {
            ...notificationForm.data.messages,
            [templateKey]: {
                ...(notificationForm.data.messages[templateKey] ?? {}),
                [language]: value,
            },
        });
    }

    const selectedLanguageOption = activeLanguages.find((language) => language.code === selectedLanguage)
        ?? activeLanguages[0];

    return (
        <AdminLayout>
            <Head title="Languages" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Languages</h1>
                        <p className="mt-1 text-sm text-gray-600">
                            Define the languages available across menus, translations, and notifications.
                        </p>
                    </div>
                    {activeTab === 'notifications' && (
                        <button
                            type="button"
                            onClick={() => notificationForm.post('/admin/languages/notifications', { preserveScroll: true })}
                            disabled={notificationForm.processing}
                            className="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-60"
                        >
                            <Save className="h-4 w-4" /> Save Notifications
                        </button>
                    )}
                </div>

                <div className="inline-flex rounded-lg bg-gray-100 p-1">
                    <button
                        type="button"
                        onClick={() => setActiveTab('languages')}
                        className={`inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium ${activeTab === 'languages' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'}`}
                    >
                        <Globe2 className="h-4 w-4" /> Languages
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('notifications')}
                        className={`inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium ${activeTab === 'notifications' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600'}`}
                    >
                        <Bell className="h-4 w-4" /> Notifications
                    </button>
                </div>

                {activeTab === 'languages' ? (
                    <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_390px]">
                        <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                            <table className="w-full">
                                <thead className="border-b border-gray-200 bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Language</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Code</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Direction</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Sort</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-700">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {languages.map((language) => (
                                        <tr key={language.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <span className="text-xl">{language.flag ?? '🌐'}</span>
                                                    <div>
                                                        <div className="font-medium text-gray-900">{language.name}</div>
                                                        {language.nativeName && <div className="text-xs text-gray-500">{language.nativeName}</div>}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-sm text-gray-600">{language.code}</td>
                                            <td className="px-4 py-3 text-sm uppercase text-gray-600">{language.direction}</td>
                                            <td className="px-4 py-3">
                                                <span className={`rounded-full px-2 py-1 text-xs font-medium ${language.isActive ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                                    {language.isActive ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600">{language.sortOrder}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-2">
                                                    <button type="button" onClick={() => startEdit(language)} className="rounded p-1.5 hover:bg-gray-100" title="Edit language">
                                                        <Edit2 className="h-4 w-4 text-gray-600" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => destroyLanguage(language)}
                                                        disabled={language.code === 'en'}
                                                        className="rounded p-1.5 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-30"
                                                        title={language.code === 'en' ? 'English is required' : 'Delete language'}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-red-600" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <form onSubmit={submitLanguage} className="h-fit rounded-lg border border-gray-200 bg-white">
                            <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                                <div>
                                    <h2 className="font-semibold text-gray-900">{editing ? 'Edit Language' : 'Add Language'}</h2>
                                    {editing && <p className="mt-0.5 text-xs text-gray-500">Editing {editing.code.toUpperCase()}</p>}
                                </div>
                                {editing ? (
                                    <button type="button" onClick={resetLanguageForm} className="rounded p-1.5 hover:bg-gray-100"><X className="h-4 w-4" /></button>
                                ) : <Plus className="h-4 w-4 text-purple-600" />}
                            </div>
                            <div className="space-y-4 p-5">
                                <label className="block text-sm font-medium text-gray-700">
                                    Language Code *
                                    <input
                                        value={languageForm.data.code}
                                        onChange={(event) => languageForm.setData('code', event.target.value.toLowerCase())}
                                        disabled={Boolean(editing)}
                                        placeholder="e.g. pt or pt-br"
                                        className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 font-mono text-sm disabled:bg-gray-100"
                                    />
                                    {languageForm.errors.code && <span className="mt-1 block text-xs text-red-600">{languageForm.errors.code}</span>}
                                </label>
                                <label className="block text-sm font-medium text-gray-700">
                                    English Name *
                                    <input value={languageForm.data.name} onChange={(event) => languageForm.setData('name', event.target.value)} placeholder="Portuguese" className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                                    {languageForm.errors.name && <span className="mt-1 block text-xs text-red-600">{languageForm.errors.name}</span>}
                                </label>
                                <label className="block text-sm font-medium text-gray-700">
                                    Native Name
                                    <input value={languageForm.data.native_name} onChange={(event) => languageForm.setData('native_name', event.target.value)} placeholder="Português" className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                                </label>
                                <div className="grid grid-cols-2 gap-3">
                                    <label className="block text-sm font-medium text-gray-700">
                                        Flag
                                        <input value={languageForm.data.flag} onChange={(event) => languageForm.setData('flag', event.target.value)} placeholder="🇵🇹" className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                                    </label>
                                    <label className="block text-sm font-medium text-gray-700">
                                        Direction
                                        <select value={languageForm.data.direction} onChange={(event) => languageForm.setData('direction', event.target.value as 'ltr' | 'rtl')} className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm">
                                            <option value="ltr">Left to right</option>
                                            <option value="rtl">Right to left</option>
                                        </select>
                                    </label>
                                </div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Sort Order
                                    <input type="number" min={0} value={languageForm.data.sort_order} onChange={(event) => languageForm.setData('sort_order', Number(event.target.value))} className="mt-1.5 w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm" />
                                </label>
                                <label className="flex items-center gap-2 text-sm text-gray-700">
                                    <input
                                        type="checkbox"
                                        checked={languageForm.data.is_active}
                                        disabled={editing?.code === 'en'}
                                        onChange={(event) => languageForm.setData('is_active', event.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300"
                                    />
                                    Available for translations
                                </label>
                                {(languageForm.errors as Record<string, string>).language && <p className="text-xs text-red-600">{(languageForm.errors as Record<string, string>).language}</p>}
                                <button type="submit" disabled={languageForm.processing} className="w-full rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-60">
                                    {languageForm.processing ? 'Saving...' : editing ? 'Save Language' : 'Add Language'}
                                </button>
                            </div>
                        </form>
                    </div>
                ) : (
                    <form onSubmit={submitNotifications} className="rounded-lg border border-gray-200 bg-white">
                        <div className="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
                            <Bell className="h-4 w-4 text-purple-600" />
                            <span className="text-sm font-medium text-gray-900">Notification Messages</span>
                        </div>
                        <div className="border-b border-gray-200 px-4 py-3">
                            <div className="flex flex-wrap gap-2">
                                {activeLanguages.map((language) => (
                                    <button key={language.code} type="button" onClick={() => setSelectedLanguage(language.code)} className={`rounded-lg border px-3 py-1.5 text-sm ${selectedLanguage === language.code ? 'border-purple-600 bg-purple-50 text-purple-700' : 'border-gray-200 text-gray-700'}`}>
                                        {language.flag ?? '🌐'} {language.nativeName ?? language.name}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {notificationTemplates.map((template) => (
                                <div key={template.key} className="grid grid-cols-1 gap-4 p-4 xl:grid-cols-[360px_1fr]">
                                    <div className="flex items-start gap-3">
                                        <span className="mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-700"><Languages className="h-4 w-4" /></span>
                                        <div>
                                            <div className="text-sm font-semibold text-gray-900">{template.label}</div>
                                            <div className="mt-1 font-mono text-xs text-gray-500">{template.key}</div>
                                            <div className="mt-2 text-xs text-gray-600">Event: <span className="font-mono">{template.event}</span></div>
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {template.placeholders.map((placeholder) => <span key={placeholder} className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600">{'{'}{placeholder}{'}'}</span>)}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <label className="block text-sm font-medium text-gray-700">{selectedLanguageOption?.name ?? selectedLanguage} message</label>
                                        <textarea
                                            value={notificationForm.data.messages[template.key]?.[selectedLanguage] ?? ''}
                                            onChange={(event) => updateMessage(template.key, selectedLanguage, event.target.value)}
                                            dir={selectedLanguageOption?.direction ?? 'ltr'}
                                            placeholder={template.defaultMessage}
                                            rows={3}
                                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                        />
                                        <div className="text-xs text-gray-500">Default: {template.defaultMessage}</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </form>
                )}
            </div>
        </AdminLayout>
    );
}
