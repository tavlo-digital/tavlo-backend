import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ShoppingCart,
    ExternalLink,
    TriangleAlert,
    CircleCheckBig,
    CircleX,
    MessageSquare,
    Star,
    Clock,
    FileText,
    Archive,
    Trash2,
    Shield,
    DollarSign,
    Gift,
} from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';

type CustomerData = {
    id: string;
    name: string;
    accountType: string;
    email: string;
    phone: string;
    risk: string;
    riskTooltip: string | null;
    ordersCount: number;
    totalSpend: string;
    loyaltyPoints: number;
    accountCreated: string;
    lastLogin: string;
    registrationSource: string;
};

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'orders', label: 'Orders', count: 47 },
    { key: 'refunds', label: 'Refunds / Disputes', count: 2 },
    { key: 'reviews', label: 'Reviews / Complaints', count: 8 },
    { key: 'activity', label: 'Activity Log' },
    { key: 'gdpr', label: 'GDPR Requests', count: 0 },
] as const;

type TabKey = (typeof tabs)[number]['key'];

const orders = [
    { id: 'ORD-8472', vendor: 'Bella Italia', date: '2025-01-06 13:15', status: 'Delivered', items: 3, amount: '€34.50' },
    { id: 'ORD-8401', vendor: 'Sushi Tokyo', date: '2025-01-04 19:30', status: 'Delivered', items: 5, amount: '€52.90' },
    { id: 'ORD-8324', vendor: 'Pizza Express', date: '2025-01-02 18:45', status: 'Cancelled', items: 2, amount: '€28.00' },
    { id: 'ORD-8256', vendor: 'Burger House', date: '2024-12-30 12:20', status: 'Delivered', items: 4, amount: '€41.20' },
    { id: 'ORD-8198', vendor: 'Bella Italia', date: '2024-12-28 20:10', status: 'Delivered', items: 6, amount: '€67.80' },
    { id: 'ORD-8142', vendor: 'Thai Garden', date: '2024-12-25 14:00', status: 'Delivered', items: 3, amount: '€45.30' },
];

const refunds = [
    {
        id: 'DIS-1024', type: 'DISPUTE' as const, status: 'Open', vendor: 'Pizza Express', orderId: 'ORD-8324',
        amount: '€28.00', date: '2025-01-03', reason: 'Card declined but still charged',
        description: 'Payment failed during checkout but amount was deducted from account',
    },
    {
        id: 'REF-2048', type: 'REFUND' as const, status: 'Approved', vendor: 'Sushi Tokyo', orderId: 'ORD-7892',
        amount: '€38.50', date: '2024-12-15', reason: 'Food quality issue',
        description: 'Sushi was not fresh, customer complained immediately after delivery',
    },
];

const reviews = [
    { id: 'REV-501', vendor: 'Bella Italia', rating: 5, date: '2025-01-06', text: 'Amazing pizza! Fast delivery and still hot. Will order again.', flagged: false },
    { id: 'REV-502', vendor: 'Sushi Tokyo', rating: 4, date: '2025-01-04', text: 'Good quality sushi, delivery was a bit slow.', flagged: false },
    { id: 'REV-503', vendor: 'Pizza Express', rating: 1, date: '2025-01-03', text: 'Terrible experience! Order never arrived but I was charged!', flagged: true },
    { id: 'REV-504', vendor: 'Burger House', rating: 5, date: '2024-12-30', text: 'Best burgers in Vienna! Perfectly cooked.', flagged: false },
    { id: 'REV-505', vendor: 'Bella Italia', rating: 4, date: '2024-12-28', text: 'Great pasta, generous portions.', flagged: false },
    { id: 'REV-506', vendor: 'Thai Garden', rating: 5, date: '2024-12-25', text: 'Excellent pad thai, authentic taste!', flagged: false },
    { id: 'REV-507', vendor: 'Sushi Tokyo', rating: 3, date: '2024-12-20', text: 'Average sushi, expected better quality for the price.', flagged: false },
    { id: 'REV-508', vendor: 'Pizza Express', rating: 2, date: '2024-12-18', text: 'Pizza was cold and soggy when it arrived.', flagged: true },
];

const activityLogs = [
    { event: 'Login', time: '2025-01-06 14:32', detail: 'Logged in via mobile app', ip: '185.34.xxx.xxx' },
    { event: 'Order Placed', time: '2025-01-06 13:15', detail: 'Order ORD-8472 at Bella Italia (€34.50)', ip: '185.34.xxx.xxx' },
    { event: 'QR Scan', time: '2025-01-06 13:14', detail: 'Scanned QR code at table 12, Bella Italia', ip: '185.34.xxx.xxx' },
    { event: 'Order Placed', time: '2025-01-04 19:30', detail: 'Order ORD-8401 at Sushi Tokyo (€52.90)', ip: '185.34.xxx.xxx' },
    { event: 'Login', time: '2025-01-04 19:28', detail: 'Logged in via web browser', ip: '185.34.xxx.xxx' },
    { event: 'Dispute Filed', time: '2025-01-03 10:45', detail: 'Dispute DIS-1024 for Order ORD-8324', ip: '185.34.xxx.xxx' },
    { event: 'Order Cancelled', time: '2025-01-02 18:45', detail: 'Order ORD-8324 at Pizza Express (€28.00)', ip: '185.34.xxx.xxx' },
    { event: 'Order Placed', time: '2024-12-30 12:20', detail: 'Order ORD-8256 at Burger House (€41.20)', ip: '185.34.xxx.xxx' },
    { event: 'Login', time: '2024-12-30 12:18', detail: 'Logged in via mobile app', ip: '185.34.xxx.xxx' },
    { event: 'Order Placed', time: '2024-12-28 20:10', detail: 'Order ORD-8198 at Bella Italia (€67.80)', ip: '185.34.xxx.xxx' },
];

function StatusBadge({ status }: { status: string }) {
    const styles: Record<string, string> = {
        Delivered: 'bg-green-100 text-green-700',
        Cancelled: 'bg-red-100 text-red-700',
        Pending: 'bg-yellow-100 text-yellow-700',
    };
    return (
        <span className={`rounded-full px-2 py-1 text-xs font-medium ${styles[status] ?? 'bg-gray-100 text-gray-700'}`}>
            {status}
        </span>
    );
}

function Stars({ count }: { count: number }) {
    return (
        <div className="flex items-center gap-1">
            {Array.from({ length: 5 }, (_, i) => (
                <Star
                    key={i}
                    className={`h-4 w-4 ${i < count ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`}
                />
            ))}
        </div>
    );
}

/* ─── Tab content components ─── */

function OrdersTab() {
    return (
        <div className="p-6">
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead>
                        <tr className="border-b border-gray-200">
                            {['Order ID', 'Vendor', 'Date', 'Status', 'Items', 'Amount', 'Action'].map((h) => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {orders.map((o) => (
                            <tr key={o.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3"><span className="font-mono text-sm font-medium text-gray-900">{o.id}</span></td>
                                <td className="px-4 py-3"><span className="text-sm text-gray-900">{o.vendor}</span></td>
                                <td className="px-4 py-3"><span className="text-sm text-gray-600">{o.date}</span></td>
                                <td className="px-4 py-3"><StatusBadge status={o.status} /></td>
                                <td className="px-4 py-3"><span className="text-sm text-gray-900">{o.items}</span></td>
                                <td className="px-4 py-3"><span className="text-sm font-medium text-gray-900">{o.amount}</span></td>
                                <td className="px-4 py-3">
                                    <button className="flex items-center gap-1 text-sm font-medium text-purple-600 hover:text-purple-700">
                                        View <ExternalLink className="h-3 w-3" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function RefundsTab() {
    return (
        <div className="p-6">
            <div className="space-y-4">
                {refunds.map((r) => {
                    const isDispute = r.type === 'DISPUTE';
                    const isOpen = r.status === 'Open';
                    const borderColor = isOpen ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50';
                    const iconBg = isDispute ? (isOpen ? 'bg-red-100' : 'bg-orange-100') : 'bg-orange-100';
                    const iconColor = isDispute ? (isOpen ? 'text-red-600' : 'text-orange-600') : 'text-orange-600';
                    const typeBg = isDispute ? 'bg-red-600 text-white' : 'bg-orange-600 text-white';
                    const statusBg = isOpen ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';

                    return (
                        <div key={r.id} className={`rounded-lg border-2 p-4 ${borderColor}`}>
                            <div className="mb-3 flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${iconBg}`}>
                                        <TriangleAlert className={`h-5 w-5 ${iconColor}`} />
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono font-semibold text-gray-900">{r.id}</span>
                                            <span className={`rounded px-2 py-0.5 text-xs font-semibold ${typeBg}`}>{r.type}</span>
                                            <span className={`rounded px-2 py-0.5 text-xs font-semibold ${statusBg}`}>{r.status}</span>
                                        </div>
                                        <p className="mt-1 text-sm text-gray-600">{r.vendor} &bull; Order {r.orderId}</p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="text-lg font-semibold text-gray-900">{r.amount}</div>
                                    <div className="text-xs text-gray-500">{r.date}</div>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <div>
                                    <div className="mb-1 text-xs font-medium uppercase text-gray-500">Reason</div>
                                    <div className="text-sm font-medium text-gray-900">{r.reason}</div>
                                </div>
                                <div>
                                    <div className="mb-1 text-xs font-medium uppercase text-gray-500">Description</div>
                                    <div className="text-sm text-gray-700">{r.description}</div>
                                </div>
                            </div>
                            {isOpen && (
                                <div className="mt-4 flex items-center gap-3 border-t border-red-200 pt-4">
                                    <button className="flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700">
                                        <CircleCheckBig className="h-4 w-4" /> Approve Refund
                                    </button>
                                    <button className="flex items-center gap-2 rounded-lg bg-gray-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-gray-700">
                                        <CircleX className="h-4 w-4" /> Decline
                                    </button>
                                    <button className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                                        <MessageSquare className="h-4 w-4" /> Contact Vendor
                                    </button>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function ReviewsTab() {
    return (
        <div className="p-6">
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-600">All customer reviews and ratings</p>
                </div>
                <div className="space-y-3">
                    {reviews.map((r) => (
                        <div key={r.id} className={`rounded-lg border p-4 ${r.flagged ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white'}`}>
                            <div className="mb-2 flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-mono text-sm font-medium text-gray-600">{r.id}</span>
                                            {r.flagged && (
                                                <span className="flex items-center gap-1 rounded bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">
                                                    <TriangleAlert className="h-3 w-3" /> FLAGGED
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-0.5 text-sm text-gray-600">{r.vendor}</p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <Stars count={r.rating} />
                                    <div className="mt-1 text-xs text-gray-500">{r.date}</div>
                                </div>
                            </div>
                            <p className="mt-2 text-sm text-gray-900">{r.text}</p>
                            {r.flagged && (
                                <div className="mt-3 flex items-center gap-2 border-t border-red-200 pt-3">
                                    <button className="rounded bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700">Remove Review</button>
                                    <button className="rounded bg-amber-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-amber-700">Unflag</button>
                                    <button className="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50">Contact Vendor</button>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function ActivityLogTab() {
    return (
        <div className="p-6">
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-600">Recent customer activity (last 10 events)</p>
                </div>
                <div className="space-y-2">
                    {activityLogs.map((a, i) => (
                        <div key={i} className="flex items-start gap-4 rounded-lg border border-gray-200 bg-gray-50 p-3 transition-colors hover:bg-gray-100">
                            <div className="flex-shrink-0">
                                <Clock className="h-5 w-5 text-gray-400" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="mb-1 flex items-center gap-2">
                                    <span className="text-sm font-medium text-gray-900">{a.event}</span>
                                    <span className="text-xs text-gray-500">{a.time}</span>
                                </div>
                                <p className="text-sm text-gray-600">{a.detail}</p>
                                <p className="mt-1 text-xs text-gray-500">IP: {a.ip}</p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

function OverviewTab({ customer }: { customer?: CustomerData }) {
    if (!customer) return null;
    return (
        <div className="space-y-6 p-6">
            {/* Support-Only View */}
            <div className="rounded-lg border border-blue-200 bg-blue-50 px-6 py-4">
                <div className="flex items-start gap-3">
                    <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100">
                        <Shield className="h-5 w-5 text-blue-600" aria-hidden="true" />
                    </div>
                    <div>
                        <h3 className="mb-1 font-semibold text-blue-900">Support-Only View</h3>
                        <p className="text-sm text-blue-800">
                            Visible for support purposes only. Personal data access is logged in Audit Trail and not visible to vendors. This customer cannot see or access this admin view.
                        </p>
                    </div>
                </div>
            </div>

            {/* Basic Information */}
            <div className="rounded-lg border border-gray-200 bg-white">
                <div className="border-b border-gray-200 px-6 py-4">
                    <h3 className="font-semibold text-gray-900">Basic Information</h3>
                </div>
                <div className="px-6 py-4">
                    <div className="grid grid-cols-2 gap-x-8 gap-y-4">
                        <div>
                            <div className="mb-1 text-xs font-medium uppercase text-gray-500">Customer ID</div>
                            <div className="font-mono text-sm font-medium text-gray-900">{customer.id}</div>
                        </div>
                        <div>
                            <div className="mb-1 text-xs font-medium uppercase text-gray-500">Account Type</div>
                            <div className="text-sm text-gray-900">{customer.accountType}</div>
                        </div>
                        <div>
                            <div className="mb-1 text-xs font-medium uppercase text-gray-500">Email (Restricted)</div>
                            <div className="text-sm text-gray-400">Hidden (enable restricted data access)</div>
                        </div>
                        <div>
                            <div className="mb-1 text-xs font-medium uppercase text-gray-500">Phone (Restricted)</div>
                            <div className="text-sm text-gray-400">Hidden (enable restricted data access)</div>
                        </div>
                        <div>
                            <div className="mb-1 text-xs font-medium uppercase text-gray-500">Account Created</div>
                            <div className="text-sm text-gray-900">{customer.accountCreated}</div>
                        </div>
                        <div>
                            <div className="mb-1 text-xs font-medium uppercase text-gray-500">Last Login</div>
                            <div className="text-sm text-gray-900">{customer.lastLogin}</div>
                        </div>
                        <div>
                            <div className="mb-1 text-xs font-medium uppercase text-gray-500">Registration Source</div>
                            <div className="text-sm text-gray-900">{customer.registrationSource}</div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Activity Summary */}
            <div>
                <h3 className="mb-3 text-sm font-medium uppercase tracking-wide text-gray-700">Activity Summary (Support Context)</h3>
                <div className="grid grid-cols-3 gap-4">
                    <div className="rounded-lg border border-gray-200 bg-white p-4">
                        <div className="mb-2 flex items-center justify-between">
                            <ShoppingCart className="h-5 w-5 text-gray-600" aria-hidden="true" />
                        </div>
                        <div className="mb-1 text-2xl font-semibold text-gray-900">{customer.ordersCount}</div>
                        <div className="text-sm text-gray-600">Total Orders</div>
                    </div>
                    <div className="rounded-lg border border-gray-200 bg-white p-4">
                        <div className="mb-2 flex items-center justify-between">
                            <DollarSign className="h-5 w-5 text-gray-600" aria-hidden="true" />
                        </div>
                        <div className="mb-1 text-2xl font-semibold text-gray-900">€{customer.totalSpend}</div>
                        <div className="text-sm text-gray-600">Total Spend</div>
                    </div>
                    <div className="rounded-lg border border-gray-200 bg-white p-4">
                        <div className="mb-2 flex items-center justify-between">
                            <Gift className="h-5 w-5 text-gray-600" aria-hidden="true" />
                        </div>
                        <div className="mb-1 text-2xl font-semibold text-gray-900">{customer.loyaltyPoints}</div>
                        <div className="text-sm text-gray-600">Loyalty Points</div>
                    </div>
                </div>
            </div>

            {/* GDPR Actions */}
            <div className="rounded-lg border-2 border-red-200 bg-white">
                <div className="border-b border-red-200 bg-red-50 px-6 py-4">
                    <h3 className="flex items-center gap-2 font-semibold text-red-900">
                        <TriangleAlert className="h-5 w-5" />
                        GDPR Actions (Strictly Controlled)
                    </h3>
                    <p className="mt-1 text-sm text-red-800">
                        These actions are logged, audited, and may be irreversible. Proceed with caution.
                    </p>
                </div>
                <div className="space-y-3 px-6 py-4">
                    <div className="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div>
                            <div className="font-medium text-gray-900">Export Personal Data</div>
                            <div className="mt-1 text-sm text-gray-600">GDPR Right to Access &bull; Export all customer data to JSON/CSV</div>
                        </div>
                        <button className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 transition-colors hover:bg-blue-100">
                            Export Data
                        </button>
                    </div>
                    <div className="flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div>
                            <div className="flex items-center gap-2 font-medium text-gray-900">
                                Anonymize Customer
                                <span className="rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">SUPER ADMIN ONLY</span>
                            </div>
                            <div className="mt-1 text-sm text-gray-600">GDPR Right to Erasure &bull; Remove personal data, retain anonymized orders</div>
                        </div>
                        <button className="flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-100 px-4 py-2 text-sm font-medium text-amber-700 transition-colors hover:bg-amber-200">
                            <Archive className="h-4 w-4" /> Anonymize
                        </button>
                    </div>
                    <div className="flex items-center justify-between rounded-lg border-2 border-red-300 bg-red-50 p-4">
                        <div>
                            <div className="flex items-center gap-2 font-medium text-red-900">
                                Delete Account Permanently
                                <span className="rounded bg-red-600 px-2 py-0.5 text-xs font-semibold text-white">SUPER ADMIN ONLY</span>
                            </div>
                            <div className="mt-1 text-sm text-red-700">
                                ⚠️ IRREVERSIBLE &bull; Permanently delete all data including order history
                            </div>
                        </div>
                        <button className="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700">
                            <Trash2 className="h-4 w-4" /> Delete Forever
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function GdprTab() {
    return (
        <div className="p-6">
            <div className="py-12 text-center">
                <FileText className="mx-auto mb-3 h-12 w-12 text-gray-300" />
                <p className="font-medium text-gray-600">No GDPR requests</p>
                <p className="mt-1 text-sm text-gray-500">This customer has not submitted any GDPR data requests</p>
            </div>
        </div>
    );
}

const tabContentMap: Record<TabKey, React.FC<{ customer?: CustomerData }>> = {
    overview: OverviewTab,
    orders: OrdersTab,
    refunds: RefundsTab,
    reviews: ReviewsTab,
    activity: ActivityLogTab,
    gdpr: GdprTab,
};

/* ─── Main page ─── */

export default function CustomerShow() {
    const { customer, activeTab } = usePage<{ customer: CustomerData; activeTab: TabKey }>().props;

    const ActiveTabContent = tabContentMap[activeTab];

    return (
        <AdminLayout>
            <Head title={`Customer ${customer.id}`} />

            <div className="flex-1 bg-gray-50">
                {/* Header */}
                <div className="border-b border-gray-200 bg-white px-6 py-4">
                    <Link href="/admin/customers" className="mb-3 flex items-center gap-2 text-gray-600 hover:text-gray-900">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Back to Customers
                    </Link>
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">Customer Support Overview</h1>
                            <p className="mt-1 text-sm text-gray-600">Customer ID: {customer.id}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="rounded-lg bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-700">
                                {customer.accountType}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="border-b border-gray-200 bg-white px-6">
                    <div className="flex gap-1">
                        {tabs.map((t) => (
                            <Link
                                key={t.key}
                                href={`/admin/customer/${customer.id}/${t.key}`}
                                preserveScroll
                                className={`flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                                    activeTab === t.key
                                        ? 'border-purple-600 text-purple-600'
                                        : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900'
                                }`}
                            >
                                {t.label}
                                {'count' in t && (
                                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                        activeTab === t.key ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600'
                                    }`}>
                                        {t.count}
                                    </span>
                                )}
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1">
                    {activeTab === 'overview' ? (
                        <ActiveTabContent customer={customer} />
                    ) : (
                        <div className="p-6">
                            <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                                <ActiveTabContent customer={customer} />
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
