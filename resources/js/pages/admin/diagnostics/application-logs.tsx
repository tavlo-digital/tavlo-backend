import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RefreshCw, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';

type LogEntry = {
    id: number;
    level: string;
    message: string;
    context: Record<string, unknown> | null;
    channel: string | null;
    logged_at: string;
};

type PaginatedLogs = {
    data: LogEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    logs: PaginatedLogs;
    levelCounts: Record<string, number>;
    currentLevel: string | null;
};

const levelBadge = (level: string) => {
    const styles: Record<string, string> = {
        EMERGENCY: 'bg-red-200 text-red-900',
        ALERT: 'bg-red-200 text-red-900',
        CRITICAL: 'bg-red-100 text-red-800',
        ERROR: 'bg-red-100 text-red-700',
        WARNING: 'bg-amber-100 text-amber-800',
        NOTICE: 'bg-blue-100 text-blue-700',
        INFO: 'bg-blue-50 text-blue-600',
        DEBUG: 'bg-gray-100 text-gray-600',
    };
    return styles[level] ?? 'bg-gray-100 text-gray-600';
};

const levelOrder = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];

function ApplicationLogsPage({ logs, levelCounts, currentLevel }: Props) {
    const [expandedId, setExpandedId] = useState<number | null>(null);

    const totalLogs = Object.values(levelCounts).reduce((sum, c) => sum + c, 0);

    return (
        <>
            <Head title="Application Logs" />
            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href="/admin/diagnostics?tab=logs"
                            className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Diagnostics
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Application Logs</h1>
                            <p className="text-sm text-gray-500">
                                {logs.total} {currentLevel ? currentLevel.toLowerCase() : 'total'} log
                                {logs.total !== 1 ? 's' : ''} stored
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

                {/* Level filter pills */}
                <div className="mb-6 flex flex-wrap gap-2">
                    <Link
                        href="/admin/diagnostics/application-logs"
                        className={`rounded-full px-3 py-1.5 text-xs font-medium transition-colors ${
                            !currentLevel
                                ? 'bg-purple-600 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        }`}
                    >
                        All ({totalLogs})
                    </Link>
                    {levelOrder
                        .filter((l) => (levelCounts[l] ?? 0) > 0)
                        .map((level) => (
                            <Link
                                key={level}
                                href={`/admin/diagnostics/application-logs?level=${level}`}
                                className={`rounded-full px-3 py-1.5 text-xs font-medium transition-colors ${
                                    currentLevel === level
                                        ? 'bg-purple-600 text-white'
                                        : `${levelBadge(level)} hover:opacity-80`
                                }`}
                            >
                                {level} ({levelCounts[level]})
                            </Link>
                        ))}
                </div>

                {/* Log entries */}
                <div className="rounded-lg border border-gray-200 bg-white">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">
                                    <th className="px-6 py-3">Time</th>
                                    <th className="px-6 py-3">Level</th>
                                    <th className="px-6 py-3">Channel</th>
                                    <th className="px-6 py-3">Message</th>
                                    <th className="w-10 px-6 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {logs.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-6 py-12 text-center text-sm text-gray-400"
                                        >
                                            No log entries found
                                        </td>
                                    </tr>
                                )}
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="group">
                                        <td className="whitespace-nowrap px-6 py-3 align-top text-sm text-gray-500">
                                            {new Date(log.logged_at).toLocaleString()}
                                        </td>
                                        <td className="px-6 py-3 align-top">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${levelBadge(log.level)}`}
                                            >
                                                {log.level}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-6 py-3 align-top text-sm text-gray-500">
                                            {log.channel ?? '-'}
                                        </td>
                                        <td className="max-w-xl px-6 py-3 align-top">
                                            <p className="truncate text-sm text-gray-900">{log.message}</p>
                                            {expandedId === log.id && log.context && (
                                                <pre className="mt-2 max-h-64 overflow-auto rounded bg-gray-50 p-3 text-xs text-gray-700">
                                                    {JSON.stringify(log.context, null, 2)}
                                                </pre>
                                            )}
                                        </td>
                                        <td className="px-6 py-3 align-top">
                                            {log.context && Object.keys(log.context).length > 0 && (
                                                <button
                                                    onClick={() =>
                                                        setExpandedId(expandedId === log.id ? null : log.id)
                                                    }
                                                    className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                                                    title="Toggle context"
                                                >
                                                    <ChevronDown
                                                        className={`h-4 w-4 transition-transform ${expandedId === log.id ? 'rotate-180' : ''}`}
                                                    />
                                                </button>
                                            )}
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

ApplicationLogsPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
export default ApplicationLogsPage;
