import AdminLayout from '@/layouts/admin-layout';
import { Head, useForm } from '@inertiajs/react';
import { Bell, Languages, Save } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

type Language = {
    code: string;
    name: string;
};

type NotificationTemplate = {
    key: string;
    event: string;
    label: string;
    defaultMessage: string;
    placeholders: string[];
    messages: Record<string, string>;
};

type FormData = {
    messages: Record<string, Record<string, string>>;
};

export default function AdminLanguagesIndex({
    languages,
    notificationTemplates,
}: {
    languages: Language[];
    notificationTemplates: NotificationTemplate[];
}) {
    const initialMessages = useMemo(() => {
        return Object.fromEntries(
            notificationTemplates.map((template) => [
                template.key,
                Object.fromEntries(
                    languages.map((language) => [
                        language.code,
                        template.messages[language.code] ?? '',
                    ]),
                ),
            ]),
        );
    }, [languages, notificationTemplates]);

    const form = useForm<FormData>({ messages: initialMessages });
    const [selectedLanguage, setSelectedLanguage] = useState(languages[0]?.code ?? 'en');

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/admin/languages/notifications', {
            preserveScroll: true,
        });
    }

    function updateMessage(templateKey: string, language: string, value: string) {
        form.setData('messages', {
            ...form.data.messages,
            [templateKey]: {
                ...(form.data.messages[templateKey] ?? {}),
                [language]: value,
            },
        });
    }

    const selectedLanguageName = languages.find((language) => language.code === selectedLanguage)?.name ?? selectedLanguage;

    return (
        <AdminLayout>
            <Head title="Languages" />
            <form onSubmit={submit} className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Languages</h1>
                        <p className="mt-1 text-sm text-gray-600">
                            Manage translated notification messages shown to customer apps.
                        </p>
                    </div>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Save className="h-4 w-4" aria-hidden="true" />
                        Save
                    </button>
                </div>

                <div className="rounded-lg border border-gray-200 bg-white">
                    <div className="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
                        <Bell className="h-4 w-4 text-purple-600" aria-hidden="true" />
                        <span className="text-sm font-medium text-gray-900">Notifications</span>
                    </div>

                    <div className="border-b border-gray-200 px-4 py-3">
                        <div className="flex flex-wrap gap-2">
                            {languages.map((language) => (
                                <button
                                    key={language.code}
                                    type="button"
                                    onClick={() => setSelectedLanguage(language.code)}
                                    className={`rounded-lg border px-3 py-1.5 text-sm transition-colors ${
                                        selectedLanguage === language.code
                                            ? 'border-purple-600 bg-purple-50 text-purple-700'
                                            : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                                    }`}
                                >
                                    {language.name}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 divide-y divide-gray-100">
                        {notificationTemplates.map((template) => (
                            <div key={template.key} className="grid grid-cols-1 gap-4 p-4 xl:grid-cols-[360px_1fr]">
                                <div>
                                    <div className="flex items-start gap-3">
                                        <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-700">
                                            <Languages className="h-4 w-4" aria-hidden="true" />
                                        </span>
                                        <div>
                                            <div className="text-sm font-semibold text-gray-900">{template.label}</div>
                                            <div className="mt-1 font-mono text-xs text-gray-500">{template.key}</div>
                                            <div className="mt-2 text-xs text-gray-600">
                                                Event: <span className="font-mono">{template.event}</span>
                                            </div>
                                            {template.placeholders.length > 0 && (
                                                <div className="mt-2 flex flex-wrap gap-1">
                                                    {template.placeholders.map((placeholder) => (
                                                        <span
                                                            key={placeholder}
                                                            className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600"
                                                        >
                                                            {'{'}{placeholder}{'}'}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label className="block text-sm font-medium text-gray-700">
                                        {selectedLanguageName} message
                                    </label>
                                    <textarea
                                        value={form.data.messages[template.key]?.[selectedLanguage] ?? ''}
                                        onChange={(event) => updateMessage(template.key, selectedLanguage, event.target.value)}
                                        placeholder={template.defaultMessage}
                                        rows={3}
                                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                                    />
                                    <div className="text-xs text-gray-500">
                                        Default: {template.defaultMessage}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </form>
        </AdminLayout>
    );
}
