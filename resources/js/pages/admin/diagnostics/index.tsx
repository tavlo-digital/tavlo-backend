import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CircleCheckBig,
    CircleX,
    TriangleAlert,
    RefreshCw,
    ArrowRight,
    Activity,
    ShieldCheck,
    AlertCircle,
    Clock,
    CreditCard,
    ScrollText,
    ChevronDown,
    Bell,
    Send,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { useNotifications } from '@/hooks/use-notifications';

type Check = {
    name: string;
    status: 'ok' | 'warning' | 'error';
    detail: string;
};

type WebhookStats = {
    total: number;
    processed: number;
    signature_invalid: number;
    payment_not_found: number;
    ignored: number;
};

type WebhookLog = {
    id: number;
    event_type: string;
    stripe_payment_intent_id: string | null;
    http_status: number;
    outcome: string;
    error_message: string | null;
    created_at: string;
};

type LogEntry = {
    id: number;
    level: string;
    message: string;
    context: Record<string, unknown> | null;
    channel: string | null;
    logged_at: string;
};

type StripeData = {
    checks: Check[];
    webhookStats: WebhookStats;
    stalePayments: number;
    recentLogs: WebhookLog[];
};

type AppLogsData = {
    recentLogs: LogEntry[];
    levelCounts: Record<string, number>;
    currentLevel: string | null;
    totalErrors24h: number;
};

type Flash = {
    type: string;
    message: string;
};

type Props = {
    currentTab: string;
    stripe: StripeData;
    appLogs: AppLogsData;
    flash?: Flash;
};

const tabs = [
    { key: 'stripe', label: 'Stripe', icon: CreditCard },
    { key: 'logs', label: 'Application Logs', icon: ScrollText },
    { key: 'test-notification', label: 'Test Notification', icon: Bell },
];

const statusIcon = (status: string) => {
    switch (status) {
        case 'ok':
            return <CircleCheckBig className="h-5 w-5 text-green-600" />;
        case 'warning':
            return <TriangleAlert className="h-5 w-5 text-amber-500" />;
        case 'error':
            return <CircleX className="h-5 w-5 text-red-600" />;
        default:
            return null;
    }
};

const statusBg = (status: string) => {
    switch (status) {
        case 'ok':
            return 'bg-green-50 border-green-200';
        case 'warning':
            return 'bg-amber-50 border-amber-200';
        case 'error':
            return 'bg-red-50 border-red-200';
        default:
            return 'bg-gray-50 border-gray-200';
    }
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

function StripeTab({ stripe, flash }: { stripe: StripeData; flash?: Flash }) {
    const { checks, webhookStats, stalePayments, recentLogs } = stripe;

    const overallStatus = checks.some((c) => c.status === 'error')
        ? 'error'
        : checks.some((c) => c.status === 'warning')
          ? 'warning'
          : 'ok';

    const handleReconcile = () => {
        router.post('/admin/diagnostics/reconcile', {}, { preserveScroll: true });
    };

    return (
        <>
            {/* Flash message */}
            {flash && (
                <div
                    className={`mb-6 rounded-lg border px-4 py-3 text-sm ${
                        flash.type === 'success'
                            ? 'border-green-200 bg-green-50 text-green-800'
                            : 'border-red-200 bg-red-50 text-red-800'
                    }`}
                >
                    {flash.message}
                </div>
            )}

            {/* Overall status banner */}
            <div className={`mb-6 flex items-center gap-3 rounded-lg border p-4 ${statusBg(overallStatus)}`}>
                {overallStatus === 'ok' && <ShieldCheck className="h-6 w-6 text-green-600" />}
                {overallStatus === 'warning' && <AlertCircle className="h-6 w-6 text-amber-500" />}
                {overallStatus === 'error' && <CircleX className="h-6 w-6 text-red-600" />}
                <div>
                    <p className="font-medium text-gray-900">
                        {overallStatus === 'ok' && 'All Stripe checks passing'}
                        {overallStatus === 'warning' && 'Stripe integration has warnings'}
                        {overallStatus === 'error' && 'Stripe integration has errors — action required'}
                    </p>
                    <p className="text-sm text-gray-600">
                        {checks.filter((c) => c.status === 'ok').length}/{checks.length} checks passing
                    </p>
                </div>
            </div>

            {/* Health checks grid */}
            <div className="mb-8 grid grid-cols-1 gap-4 md:grid-cols-2">
                {checks.map((check) => (
                    <div
                        key={check.name}
                        className={`flex items-start gap-3 rounded-lg border p-4 ${statusBg(check.status)}`}
                    >
                        <div className="mt-0.5">{statusIcon(check.status)}</div>
                        <div>
                            <p className="font-medium text-gray-900">{check.name}</p>
                            <p className="text-sm text-gray-600">{check.detail}</p>
                        </div>
                    </div>
                ))}
            </div>

            {/* Stats + actions row */}
            <div className="mb-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div className="rounded-lg border border-gray-200 bg-white p-6 lg:col-span-2">
                    <div className="mb-4 flex items-center gap-2">
                        <Activity className="h-5 w-5 text-gray-600" />
                        <h2 className="text-lg font-semibold text-gray-900">Webhook Activity (24h)</h2>
                    </div>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div className="rounded-lg bg-gray-50 p-4 text-center">
                            <p className="text-2xl font-bold text-gray-900">{webhookStats.total}</p>
                            <p className="text-xs text-gray-500">Total Events</p>
                        </div>
                        <div className="rounded-lg bg-green-50 p-4 text-center">
                            <p className="text-2xl font-bold text-green-700">{webhookStats.processed}</p>
                            <p className="text-xs text-green-600">Processed</p>
                        </div>
                        <div className="rounded-lg bg-red-50 p-4 text-center">
                            <p className="text-2xl font-bold text-red-700">{webhookStats.signature_invalid}</p>
                            <p className="text-xs text-red-600">Sig. Failures</p>
                        </div>
                        <div className="rounded-lg bg-amber-50 p-4 text-center">
                            <p className="text-2xl font-bold text-amber-700">{webhookStats.payment_not_found}</p>
                            <p className="text-xs text-amber-600">Not Found</p>
                        </div>
                    </div>
                </div>

                <div className="rounded-lg border border-gray-200 bg-white p-6">
                    <div className="mb-4 flex items-center gap-2">
                        <Clock className="h-5 w-5 text-gray-600" />
                        <h2 className="text-lg font-semibold text-gray-900">Stale Payments</h2>
                    </div>
                    <p className="mb-1 text-3xl font-bold text-gray-900">{stalePayments}</p>
                    <p className="mb-4 text-sm text-gray-500">Payments pending {'>'}10 min without confirmation</p>
                    <button
                        onClick={handleReconcile}
                        disabled={stalePayments === 0}
                        className="flex w-full items-center justify-center gap-2 rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <RefreshCw className="h-4 w-4" />
                        Reconcile Now
                    </button>
                </div>
            </div>

            {/* Recent webhook logs */}
            <div className="rounded-lg border border-gray-200 bg-white">
                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h2 className="text-lg font-semibold text-gray-900">Recent Webhook Events</h2>
                    <Link
                        href="/admin/diagnostics/webhook-logs"
                        className="flex items-center gap-1 text-sm font-medium text-purple-600 hover:text-purple-700"
                    >
                        View all
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium tracking-wide text-gray-500 uppercase">
                                <th className="px-6 py-3">Time</th>
                                <th className="px-6 py-3">Event Type</th>
                                <th className="px-6 py-3">Payment Intent</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">Outcome</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {recentLogs.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-8 text-center text-sm text-gray-400">
                                        No webhook events recorded yet
                                    </td>
                                </tr>
                            )}
                            {recentLogs.map((log) => (
                                <tr key={log.id} className="hover:bg-gray-50">
                                    <td className="whitespace-nowrap px-6 py-3 text-sm text-gray-500">
                                        {new Date(log.created_at).toLocaleString()}
                                    </td>
                                    <td className="px-6 py-3 font-mono text-sm text-gray-900">{log.event_type}</td>
                                    <td className="px-6 py-3 font-mono text-sm text-gray-500">
                                        {log.stripe_payment_intent_id ?? '-'}
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
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function AppLogsTab({ appLogs }: { appLogs: AppLogsData }) {
    const { recentLogs, levelCounts, totalErrors24h } = appLogs;
    const [expandedId, setExpandedId] = useState<number | null>(null);

    const totalLogs = Object.values(levelCounts).reduce((sum, c) => sum + c, 0);

    return (
        <>
            {/* Summary cards */}
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-lg border border-gray-200 bg-white p-5 text-center">
                    <p className="text-3xl font-bold text-gray-900">{totalLogs}</p>
                    <p className="text-sm text-gray-500">Total Logs Stored</p>
                </div>
                <div className="rounded-lg border border-red-200 bg-red-50 p-5 text-center">
                    <p className="text-3xl font-bold text-red-700">{totalErrors24h}</p>
                    <p className="text-sm text-red-600">Errors (24h)</p>
                </div>
                <div className="rounded-lg border border-gray-200 bg-white p-5">
                    <p className="mb-2 text-sm font-medium text-gray-500">By Level</p>
                    <div className="flex flex-wrap gap-1.5">
                        {levelOrder
                            .filter((l) => (levelCounts[l] ?? 0) > 0)
                            .map((level) => (
                                <span
                                    key={level}
                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${levelBadge(level)}`}
                                >
                                    {level}: {levelCounts[level]}
                                </span>
                            ))}
                        {totalLogs === 0 && <span className="text-xs text-gray-400">No logs yet</span>}
                    </div>
                </div>
            </div>

            {/* Recent logs table */}
            <div className="rounded-lg border border-gray-200 bg-white">
                <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h2 className="text-lg font-semibold text-gray-900">Recent Application Logs</h2>
                    <Link
                        href="/admin/diagnostics/application-logs"
                        className="flex items-center gap-1 text-sm font-medium text-purple-600 hover:text-purple-700"
                    >
                        View all
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
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
                            {recentLogs.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-8 text-center text-sm text-gray-400">
                                        No log entries recorded yet
                                    </td>
                                </tr>
                            )}
                            {recentLogs.map((log) => (
                                <tr key={log.id} className="hover:bg-gray-50">
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
            </div>
        </>
    );
}

function TestNotificationTab({ flash }: { flash?: Flash }) {
    const { auth } = usePage().props;
    const { notifications, clearNotifications } = useNotifications('admin', auth.user.id);

    const [message, setMessage] = useState('This is a test notification from the admin diagnostics panel.');
    const [sending, setSending] = useState(false);

    const handleSend = (e: FormEvent) => {
        e.preventDefault();
        setSending(true);
        router.post(
            '/admin/diagnostics/test-notification',
            { message },
            {
                preserveScroll: true,
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            {flash && (
                <div
                    className={`mb-6 rounded-lg border px-4 py-3 text-sm ${
                        flash.type === 'success'
                            ? 'border-green-200 bg-green-50 text-green-800'
                            : 'border-red-200 bg-red-50 text-red-800'
                    }`}
                >
                    {flash.message}
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Send form */}
                <div className="rounded-lg border border-gray-200 bg-white p-6">
                    <div className="mb-4 flex items-center gap-2">
                        <Send className="h-5 w-5 text-gray-600" />
                        <h2 className="text-lg font-semibold text-gray-900">Send Test Notification</h2>
                    </div>
                    <p className="mb-6 text-sm text-gray-500">
                        Sends a notification with event <code className="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono">test_notification</code> to the current admin user.
                    </p>

                    <form onSubmit={handleSend}>
                        <div className="mb-2">
                            <label htmlFor="notif-event" className="block mb-1 text-sm font-medium text-gray-700">Event</label>
                            <input
                                id="notif-event"
                                type="text"
                                value="test_notification"
                                disabled
                                className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm text-gray-500"
                            />
                        </div>

                        <div className="mb-2">
                            <label htmlFor="notif-role" className="block mb-1 text-sm font-medium text-gray-700">Role</label>
                            <input
                                id="notif-role"
                                type="text"
                                value={auth.user.role?.name ?? 'admin'}
                                disabled
                                className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm text-gray-500"
                            />
                        </div>

                        <div className="mb-2">
                            <label htmlFor="notif-user-id" className="block mb-1 text-sm font-medium text-gray-700">User ID</label>
                            <input
                                id="notif-user-id"
                                type="text"
                                value={auth.user.id}
                                disabled
                                className="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm text-gray-500"
                            />
                        </div>

                        <div className="mb-4">
                            <label htmlFor="notif-message" className="block mb-1 text-sm font-medium text-gray-700">Message</label>
                            <textarea
                                id="notif-message"
                                value={message}
                                onChange={(e) => setMessage(e.target.value)}
                                rows={3}
                                maxLength={500}
                                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-purple-500 focus:ring-purple-500"
                            />
                            <p className="mt-1 text-xs text-gray-400">{message.length}/500</p>
                        </div>

                        <button
                            type="submit"
                            disabled={sending || message.trim().length === 0}
                            className="flex w-full items-center justify-center gap-2 rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Send className="h-4 w-4" />
                            {sending ? 'Sending...' : 'Send Test Notification'}
                        </button>
                    </form>
                </div>

                {/* Realtime feed */}
                <div className="rounded-lg border border-gray-200 bg-white p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Bell className="h-5 w-5 text-gray-600" />
                            <h2 className="text-lg font-semibold text-gray-900">Realtime Feed</h2>
                            <span className="relative flex h-2.5 w-2.5">
                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                                <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500" />
                            </span>
                        </div>
                        {notifications.length > 0 && (
                            <button
                                onClick={clearNotifications}
                                className="text-xs text-gray-400 hover:text-gray-600"
                            >
                                Clear
                            </button>
                        )}
                    </div>
                    <p className="mb-4 text-sm text-gray-500">
                        Listening for notifications where <code className="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono">admin_id={auth.user.id}</code>
                    </p>

                    <div className="space-y-3">
                        {notifications.length === 0 && (
                            <div className="rounded-lg border border-dashed border-gray-200 py-10 text-center">
                                <Bell className="mx-auto mb-2 h-8 w-8 text-gray-300" />
                                <p className="text-sm text-gray-400">No notifications yet</p>
                                <p className="text-xs text-gray-400">Send a test notification to see it appear here in realtime</p>
                            </div>
                        )}
                        {notifications.map((n) => (
                            <div
                                key={n.id}
                                className="animate-in slide-in-from-top rounded-lg border border-purple-200 bg-purple-50 p-4"
                            >
                                <div className="mb-1 flex items-center justify-between">
                                    <span className="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">
                                        {n.event}
                                    </span>
                                    <span className="text-xs text-gray-400">
                                        {new Date(n.created_at).toLocaleTimeString()}
                                    </span>
                                </div>
                                <p className="text-sm text-gray-900">{n.message}</p>
                                <p className="mt-1 text-xs text-gray-400">
                                    admin_id: {n.admin_id}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

function DiagnosticsPage({ currentTab, stripe, appLogs, flash }: Props) {
    const [activeTab, setActiveTab] = useState(currentTab);

    const switchTab = (tab: string) => {
        setActiveTab(tab);
        router.get('/admin/diagnostics', { tab }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <>
            <Head title="Diagnostics" />
            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">System Diagnostics</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Monitor system health, integrations, and application logs
                        </p>
                    </div>
                    <button
                        onClick={() => router.reload()}
                        className="flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        <RefreshCw className="h-4 w-4" />
                        Refresh
                    </button>
                </div>

                {/* Tabs */}
                <div className="mb-6 border-b border-gray-200">
                    <nav className="-mb-px flex gap-6">
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                onClick={() => switchTab(tab.key)}
                                className={`flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium transition-colors ${
                                    activeTab === tab.key
                                        ? 'border-purple-600 text-purple-600'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                                }`}
                            >
                                <tab.icon className="h-4 w-4" />
                                {tab.label}
                            </button>
                        ))}
                    </nav>
                </div>

                {/* Tab content */}
                {activeTab === 'stripe' && <StripeTab stripe={stripe} flash={flash} />}
                {activeTab === 'logs' && <AppLogsTab appLogs={appLogs} />}
                {activeTab === 'test-notification' && <TestNotificationTab flash={flash} />}
            </div>
        </>
    );
}

DiagnosticsPage.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
export default DiagnosticsPage;
