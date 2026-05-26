import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RefreshCw } from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';

type WebhookLog = {
    id: number;
    event_type: string;
    stripe_payment_intent_id: string | null;
    stripe_event_id: string | null;
    http_status: number;
    outcome: string;
    error_message: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

type PaginatedLogs = {
    data: WebhookLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    logs: PaginatedLogs;
};

const outcomeBadge = (outcome: string) => {
    const styles: Record<string, string> = {
        processed: 'bg-green-100 text-green-800',
        signature_invalid: 'bg-red-100 text-red-800',
        payment_not_found: 'bg-amber-100 text-amber-800',
        ignored_event_type: 'bg-gray-100 text-gray-600',
    };
    return styles[outcome] ?? 'bg-gray-100 text-gray-600';
};

const outcomeLabel = (outcome: string) => {
    const labels: Record<string, string> = {
        processed: 'Processed',
        signature_invalid: 'Sig. Invalid',
        payment_not_found: 'Not Found',
        ignored_event_type: 'Ignored',
    };
    return labels[outcome] ?? outcome;
};

function WebhookLogsPage({ logs }: Props) {
    return (
        <>
            <Head title="Webhook Logs" />
            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href="/admin/diagnostics?tab=stripe"
                            className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Diagnostics
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Webhook Logs</h1>
                            <p className="text-sm text-gray-500">
                                {logs.total} total event{logs.total !== 1 ? 's' : ''} recorded
                            </p>
                        </div>
                    </div>
                    <button
                        onClick={() => router.reload()}
                        className="flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        <RefreshCw className="h-4 w-4" />
                        Refresh
                    </button>
                </div>

                {/* Table */}
                <div className="rounded-lg border border-gray-200 bg-white">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">
                                    <th className="px-6 py-3">ID</th>
                                    <th className="px-6 py-3">Time</th>
                                    <th className="px-6 py-3">Event Type</th>
                                    <th className="px-6 py-3">Payment Intent</th>
                                    <th className="px-6 py-3">HTTP</th>
                                    <th className="px-6 py-3">Outcome</th>
                                    <th className="px-6 py-3">Error</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {logs.data.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-6 py-12 text-center text-sm text-gray-400">
                                            No webhook events recorded yet
                                        </td>
                                    </tr>
                                )}
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-gray-50">
                                        <td className="whitespace-nowrap px-6 py-3 text-sm text-gray-400">
                                            #{log.id}
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-3 text-sm text-gray-500">
                                            {new Date(log.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-6 py-3 font-mono text-sm text-gray-900">
                                            {log.event_type}
                                        </td>
                                        <td className="px-6 py-3 font-mono text-sm text-gray-500">
                                            {log.stripe_payment_intent_id
                                                ? log.stripe_payment_intent_id.length > 20
                                                    ? log.stripe_payment_intent_id.slice(0, 20) + '...'
                                                    : log.stripe_payment_intent_id
                                                : '-'}
                                        </td>
                                        <td className="px-6 py-3">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    log.http_status === 200
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800'
                                                }`}
                                            >
                                                {log.http_status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${outcomeBadge(log.outcome)}`}
                                            >
                                                {outcomeLabel(log.outcome)}
                                            </span>
                                        </td>
                                        <td className="max-w-xs truncate px-6 py-3 text-sm text-red-600">
                                            {log.error_message ?? '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {logs.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-gray-200 px-6 py-3">
                            <p className="text-sm text-gray-500">
                                Page {logs.current_page} of {logs.last_page}
                            </p>
                            <div className="flex gap-1">
                                {logs.links.map((link, i) => {
                                    if (!link.url) {
                                        return (
                                            <span
                                                key={i}
                                                className="rounded px-3 py-1 text-sm text-gray-400"
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        );
                                    }
                                    return (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            className={`rounded px-3 py-1 text-sm transition-colors ${
                                                link.active
                                                    ? 'bg-purple-600 text-white'
                                                    : 'text-gray-700 hover:bg-gray-100'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

WebhookLogsPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
export default WebhookLogsPage;
