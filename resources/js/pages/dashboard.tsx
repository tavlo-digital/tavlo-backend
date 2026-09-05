import { Head } from '@inertiajs/react';
import {
    Store,
    Users,
    ShoppingCart,
    DollarSign,
    QrCode,
    AlertTriangle,
    CircleAlert,
    Clock,
    CircleUser,
    UserCheck,
    ShieldAlert,
    ChevronRight,
    CircleCheckBig,
    X,
    TrendingUp,
} from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';

// ─── KPI Card ──────────────────────────────────────────────────
function KpiCard({
    icon: Icon,
    value,
    label,
    sub,
    extra,
}: {
    icon: React.ElementType;
    value: string;
    label: string;
    sub?: string;
    extra?: string;
}) {
    return (
        <div className="w-full rounded-lg border border-gray-200 bg-white p-4 text-left transition-all hover:border-gray-300 hover:shadow-sm">
            <div className="mb-3 flex items-start justify-between">
                <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
            <div className="mb-1 text-2xl font-semibold text-gray-900">{value}</div>
            <div className="text-sm text-gray-600">{label}</div>
            {sub && <div className="mt-2 text-xs text-gray-500">{sub}</div>}
            {extra && <div className="mt-1 text-xs text-gray-400">{extra}</div>}
        </div>
    );
}

// ─── Alert Card ────────────────────────────────────────────────
function AlertCard({
    severity,
    title,
    affected,
    impact,
    openFor,
    assignee,
    timeAgo,
    actions,
}: {
    severity: 'critical' | 'warning';
    title: string;
    affected: string;
    impact: string;
    openFor: string;
    assignee: string;
    timeAgo: string;
    actions: { label: string; variant?: 'outline' | 'solid' }[];
}) {
    const colors =
        severity === 'critical'
            ? { border: 'border-red-200', bg: 'bg-red-50', icon: 'text-red-600', badge: 'bg-red-600' }
            : { border: 'border-orange-200', bg: 'bg-orange-50', icon: 'text-orange-600', badge: 'bg-orange-600' };

    const AlertIcon = severity === 'critical' ? CircleAlert : AlertTriangle;

    return (
        <div className={`rounded-lg border ${colors.border} ${colors.bg} p-4`}>
            <div className="flex items-start gap-3">
                <div className={`mt-0.5 ${colors.icon}`}>
                    <AlertIcon className="h-5 w-5" />
                </div>
                <div className="min-w-0 flex-1">
                    <div className="mb-2 flex items-start justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <h3 className="font-medium text-gray-900">{title}</h3>
                            <span className={`rounded ${colors.badge} px-2 py-0.5 text-xs font-medium uppercase text-white`}>
                                {severity}
                            </span>
                        </div>
                        <button className="flex-shrink-0 text-gray-400 transition-colors hover:text-gray-600" aria-label="Dismiss">
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                    <div className="mb-3 space-y-1 text-sm">
                        <div className="text-gray-700">
                            <span className="font-medium">Affected:</span> {affected}
                        </div>
                        <div className="text-gray-600">
                            <span className="font-medium">Impact:</span> {impact}
                        </div>
                    </div>
                    <div className="mb-3 flex items-center gap-4 text-xs text-gray-500">
                        <div className="flex items-center gap-1">
                            <Clock className="h-3 w-3" />
                            <span>Open for: {openFor}</span>
                        </div>
                        <div className="flex items-center gap-1">
                            <CircleUser className="h-3 w-3" />
                            <span>{assignee}</span>
                        </div>
                        <span>{timeAgo}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        {actions.map((a) =>
                            a.variant === 'outline' ? (
                                <button
                                    key={a.label}
                                    className="inline-flex h-7 items-center gap-1.5 rounded-md border bg-white px-3 text-xs font-medium text-gray-700 transition-all hover:bg-gray-50"
                                >
                                    <CircleCheckBig className="mr-1 h-3 w-3" />
                                    {a.label}
                                </button>
                            ) : (
                                <button
                                    key={a.label}
                                    className="inline-flex h-7 items-center gap-1.5 rounded-md bg-gray-900 px-3 text-xs font-medium text-white transition-all hover:bg-gray-800"
                                >
                                    {a.label}
                                </button>
                            ),
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

// ─── Action Queue ──────────────────────────────────────────────
function ActionQueueCard({
    icon: Icon,
    title,
    subtitle,
    items,
    footerLabel,
    headerBorder,
    headerBg,
    iconBg,
    iconColor,
}: {
    icon: React.ElementType;
    title: string;
    subtitle: React.ReactNode;
    items: { name: string; detail: string; urgent?: boolean }[];
    footerLabel: string;
    headerBorder: string;
    headerBg: string;
    iconBg: string;
    iconColor: string;
}) {
    return (
        <div className={`rounded-lg border ${headerBorder} bg-white shadow-sm`}>
            <div className={`border-b ${headerBorder.replace('border-', 'border-').replace('200', '100')} ${headerBg} px-6 py-4`}>
                <div className="flex items-center gap-3">
                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${iconBg}`}>
                        <Icon className={`h-5 w-5 ${iconColor}`} />
                    </div>
                    <div>
                        <h3 className="font-semibold text-gray-900">{title}</h3>
                        <p className="text-sm text-gray-600">{subtitle}</p>
                    </div>
                </div>
            </div>
            <div className="divide-y divide-gray-100">
                {items.map((item) => (
                    <button key={item.name} className="group flex w-full items-center justify-between px-6 py-4 transition-colors hover:bg-gray-50">
                        <div className="flex items-center gap-4">
                            {item.urgent && <div className="h-2 w-2 rounded-full bg-orange-500" />}
                            <div className="text-left">
                                <div className="font-medium text-gray-900 transition-colors group-hover:text-purple-700">{item.name}</div>
                                <div className="text-sm text-gray-600">{item.detail}</div>
                            </div>
                        </div>
                        <ChevronRight className="h-5 w-5 text-gray-400 transition-colors group-hover:text-purple-600" />
                    </button>
                ))}
            </div>
            <div className="border-t border-gray-100 bg-gray-50 px-6 py-3">
                <button className="text-sm font-medium text-purple-600 hover:text-purple-700">{footerLabel}</button>
            </div>
        </div>
    );
}

// ─── Simple Queue Item ─────────────────────────────────────────
function QueueItem({
    icon: Icon,
    title,
    count,
    description,
    priority,
    priorityColor,
    oldest,
}: {
    icon: React.ElementType;
    title: string;
    count: number;
    description: string;
    priority: string;
    priorityColor: string;
    oldest: string;
}) {
    return (
        <button className="group w-full rounded-lg border border-gray-200 bg-white p-4 text-left transition-all hover:shadow-sm">
            <div className="mb-3 flex items-start justify-between">
                <div className="flex flex-1 items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                        <Icon className="h-5 w-5" />
                    </div>
                    <div className="flex-1">
                        <div className="mb-1 flex items-center gap-2">
                            <h3 className="font-medium text-gray-900">{title}</h3>
                            <span className="rounded-full bg-gray-600 px-2 py-0.5 text-xs font-semibold text-white">{count}</span>
                        </div>
                        <p className="text-sm text-gray-600">{description}</p>
                    </div>
                </div>
                <ChevronRight className="h-5 w-5 flex-shrink-0 text-gray-400 transition-all group-hover:translate-x-1 group-hover:text-gray-600" />
            </div>
            <div className="flex items-center gap-4 text-xs">
                <div className="flex items-center gap-1">
                    <CircleAlert className={`h-3 w-3 ${priorityColor}`} />
                    <span className={`font-medium ${priorityColor}`}>{priority}</span>
                </div>
                <span className="text-gray-500">Oldest: {oldest}</span>
            </div>
        </button>
    );
}

// ─── Page ──────────────────────────────────────────────────────
export default function Dashboard() {
    return (
        <AdminLayout>
            <Head title="Dashboard" />

            {/* Title */}
            <div className="mb-6">
                <h1 className="mb-1 text-2xl font-semibold text-gray-900">Platform Operations</h1>
                <p className="text-sm text-gray-600">Real-time monitoring and action center</p>
            </div>

            {/* KPI Cards */}
            <div className="mb-6">
                <h2 className="mb-3 text-sm font-medium uppercase tracking-wide text-gray-700">Platform Health</h2>
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <KpiCard icon={Store} value="142" label="Active Vendors Today" sub="of 247 total" extra="vs yesterday: +3" />
                    <KpiCard icon={Users} value="1284" label="Active Customers Today" extra="7-day avg: 1,156" />
                    <KpiCard icon={ShoppingCart} value="3482" label="Orders Today" extra="vs yesterday: +12%" />
                    <KpiCard icon={DollarSign} value="€47.2K" label="Revenue Today" sub="MRR: €285K" extra="vs yesterday: +8%" />
                    <KpiCard icon={QrCode} value="892" label="QR Scans Today" extra="conversion: 64%" />
                    <KpiCard icon={TrendingUp} value="99.8%" label="System Uptime" extra="last 30 days" />
                </div>
            </div>

            {/* Active Alerts */}
            <div className="mb-6">
                <h2 className="mb-3 text-sm font-medium uppercase tracking-wide text-gray-700">Active Alerts</h2>
                <div className="space-y-3">
                    <AlertCard
                        severity="warning"
                        title="Vendor Onboarding Stuck"
                        affected="3 vendors (legal forms incomplete)"
                        impact="Delayed go-live, lost subscription revenue"
                        openFor="2h 15m"
                        assignee="Unassigned"
                        timeAgo="2 hours ago"
                        actions={[{ label: 'Review Queue', variant: 'solid' }]}
                    />
                    <AlertCard
                        severity="critical"
                        title="Subscription Expired But Active"
                        affected='Vendor "Pizza Express" (VID-2847)'
                        impact="Unmonetized service usage"
                        openFor="4h 32m"
                        assignee="Mike Johnson"
                        timeAgo="4 hours ago"
                        actions={[
                            { label: 'Mark Resolved', variant: 'outline' },
                            { label: 'Suspend Access', variant: 'solid' },
                        ]}
                    />
                </div>
            </div>

            {/* Action Queues */}
            <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <h2 className="mb-3 text-sm font-medium uppercase tracking-wide text-gray-700">Action Queues</h2>
                    <div className="mb-3">
                        <ActionQueueCard
                            icon={CircleAlert}
                            title="Pending Vendor Changes"
                            subtitle={
                                <>
                                    3 changes awaiting review
                                    <span className="ml-2 font-medium text-orange-600">• 1 urgent</span>
                                </>
                            }
                            items={[
                                { name: 'Bella Italia', detail: 'Legal information changes • 2 hours ago', urgent: true },
                                { name: 'Pizza Express', detail: 'Business details changes • 5 hours ago' },
                                { name: 'Sakura Sushi', detail: 'Contact information changes • 1 day ago' },
                            ]}
                            footerLabel="View all pending changes →"
                            headerBorder="border-orange-200"
                            headerBg="bg-orange-50"
                            iconBg="bg-orange-100"
                            iconColor="text-orange-600"
                        />
                    </div>
                    <div className="space-y-3">
                        <QueueItem
                            icon={UserCheck}
                            title="Vendors Pending Approval"
                            count={5}
                            description="KYC verification completed, awaiting final approval"
                            priority="Medium priority"
                            priorityColor="text-orange-600"
                            oldest="18h"
                        />
                        <QueueItem
                            icon={ShieldAlert}
                            title="KYC Verification Failed"
                            count={3}
                            description="Identity documents require manual review"
                            priority="High priority"
                            priorityColor="text-red-600"
                            oldest="6h"
                        />
                    </div>
                </div>

                <div>
                    <h2 className="mb-3 text-sm font-medium uppercase tracking-wide text-gray-700">Recent Activity</h2>
                    <div className="rounded-lg border border-gray-200 bg-white">
                        <div className="divide-y divide-gray-100">
                            {[
                                { text: 'New vendor "Taco Bell" completed onboarding', time: '5 min ago', dot: 'bg-green-500' },
                                { text: 'Customer #4521 filed a complaint', time: '12 min ago', dot: 'bg-orange-500' },
                                { text: 'Subscription renewed for "Burger King"', time: '25 min ago', dot: 'bg-blue-500' },
                                { text: 'KYC approved for "Nando\'s"', time: '1 hour ago', dot: 'bg-green-500' },
                                { text: 'Payment of €2,450 received from "Dominos"', time: '2 hours ago', dot: 'bg-blue-500' },
                                { text: 'System maintenance completed', time: '3 hours ago', dot: 'bg-gray-400' },
                            ].map((item, i) => (
                                <div key={i} className="flex items-center gap-3 px-6 py-3">
                                    <div className={`h-2 w-2 flex-shrink-0 rounded-full ${item.dot}`} />
                                    <div className="flex-1 text-sm text-gray-700">{item.text}</div>
                                    <span className="text-xs text-gray-400">{item.time}</span>
                                </div>
                            ))}
                        </div>
                        <div className="border-t border-gray-100 bg-gray-50 px-6 py-3">
                            <button className="text-sm font-medium text-purple-600 hover:text-purple-700">
                                View full activity log →
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
