import { Head, Link, usePage, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Store,
    CircleAlert,
    CreditCard,
    Repeat,
    ShoppingCart,
    MessageSquare,
    History,
    Building2,
    Mail,
    Phone,
    Globe,
    FileText,
    MapPin,
    TriangleAlert,
    Info,
    X,
    Check,
    Calendar,
    Download,
} from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';

const tabs = [
    { label: 'Overview', icon: Store, slug: 'overview' },
    { label: 'Pending Changes', icon: CircleAlert, slug: 'pending-changes' },
    { label: 'Payments', icon: CreditCard, slug: 'payments' },
    { label: 'Subscription', icon: Repeat, slug: 'subscription' },
    { label: 'Orders', icon: ShoppingCart, slug: 'orders' },
    { label: 'Reviews', icon: MessageSquare, slug: 'reviews' },
    { label: 'Activity', icon: History, slug: 'activity' },
];

function CopyButton({ text }: { text: string }) {
    function copy() {
        navigator.clipboard.writeText(text);
    }
    return (
        <button className="text-gray-400 transition-colors hover:text-gray-600" title="Copy to clipboard" onClick={copy}>
            <FileText className="h-3.5 w-3.5" aria-hidden="true" />
        </button>
    );
}

type VendorData = {
    name: string;
    id: string;
    slug: string;
    status: string;
    subscription: string;
    paymentStatus: string;
    businessName: string;
    email: string;
    phone: string;
    website: string | null;
    vat: string | null;
    legalEntity: string | null;
    address: string | null;
    city: string;
    country: string;
    usersUsed: number;
    usersAllowed: number;
    issues: string[];
    recentActivity: { event: string; time: string }[];
};

type PendingChangeData = {
    id: number;
    changes: { field: string; current: string; newValue: string; impact: string }[];
    vendorNotes: string | null;
    submittedAt: string;
    submittedBy: string;
};

type PaymentYearData = {
    year: number;
    paidCount: number;
    unpaidCount: number;
    total: string;
    months: {
        name: string;
        paid: number;
        total: number;
        amount: string;
        invoices: { id: string; amount: string; status: 'paid' | 'unpaid' | 'failed'; date: string }[];
    }[];
};

type SubscriptionDetailsData = {
    plan: string;
    status: string;
    billingCycle: string;
    nextBillingDate: string;
    history: { title: string; detail: string; time: string }[];
};

type ActivityData = {
    color: string;
    title: string;
    detail: string;
    time: string;
};

type PageProps = {
    vendor: string;
    tab: string;
    vendorData: VendorData;
    pendingChanges?: PendingChangeData[];
    paymentData?: PaymentYearData[];
    paymentFailures24h?: number;
    subscriptionDetails?: SubscriptionDetailsData;
    activities?: ActivityData[];
    [key: string]: unknown;
};

export default function VendorOverview() {
    const { vendor: vendorSlug, tab, vendorData } = usePage<PageProps>().props;
    const activeTab = tab && tabs.some((t) => t.slug === tab) ? tab : 'overview';

    const vendor = vendorData;

    return (
        <AdminLayout>
            <Head title={`${vendor.name} - Overview`} />

            <div className="flex-1 bg-gray-50">
                {/* Header */}
                <div className="border-b border-gray-200 bg-white px-6 py-4">
                    <Link href="/admin/vendors" className="mb-3 flex items-center gap-2 text-gray-600 hover:text-gray-900">
                        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                        Back to Vendors
                    </Link>
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">{vendor.name}</h1>
                            <p className="mt-1 text-sm text-gray-600">Vendor ID: {vendor.id}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="rounded-lg bg-green-100 px-3 py-1.5 text-sm font-medium text-green-700">
                                {vendor.status}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="border-b border-gray-200 bg-white px-6">
                    <div className="flex gap-1">
                        {tabs.map((tab) => (
                            <Link
                                key={tab.slug}
                                href={`/admin/vendor/${vendorSlug}/${tab.slug}`}
                                className={`flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                                    tab.slug === activeTab
                                        ? 'border-purple-600 text-purple-600'
                                        : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900'
                                }`}
                            >
                                <tab.icon className="h-4 w-4" aria-hidden="true" />
                                {tab.label}
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1">
                    {activeTab === 'overview' && <OverviewTab vendor={vendor} />}
                    {activeTab === 'pending-changes' && <PendingChangesTab vendorSlug={vendorSlug} />}
                    {activeTab === 'payments' && <PaymentsTab />}
                    {activeTab === 'subscription' && <SubscriptionTab />}
                    {activeTab === 'orders' && <OrdersTab />}
                    {activeTab === 'reviews' && <ReviewsTab />}
                    {activeTab === 'activity' && <ActivityTab />}
                </div>
            </div>
        </AdminLayout>
    );
}

/* ─── Overview Tab ─── */
function OverviewTab({ vendor }: { vendor: VendorData }) {
    return (
        <div className="space-y-6 p-6">
            {/* Active Issues */}
            {vendor.issues.length > 0 && (
                <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                    <h3 className="mb-2 font-medium text-red-900">⚠️ Active Issues</h3>
                    <ul className="space-y-2 text-sm text-red-800">
                        {vendor.issues.map((issue, i) => (
                            <li key={i}>• {issue}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Info Cards */}
            <div className="grid grid-cols-4 gap-4">
                <div className="rounded-lg border border-gray-200 bg-white p-4">
                    <div className="mb-1 text-sm text-gray-600">Vendor ID</div>
                    <div className="font-mono text-sm font-medium text-gray-900">{vendor.id}</div>
                </div>
                <div className="rounded-lg border border-gray-200 bg-white p-4">
                    <div className="mb-1 text-sm text-gray-600">Status</div>
                    <div className="font-medium text-gray-900">{vendor.status}</div>
                </div>
                <div className="rounded-lg border border-gray-200 bg-white p-4">
                    <div className="mb-1 text-sm text-gray-600">Subscription</div>
                    <div className="font-medium text-gray-900">{vendor.subscription}</div>
                </div>
                <div className="rounded-lg border border-gray-200 bg-white p-4">
                    <div className="mb-1 text-sm text-gray-600">Payment Status</div>
                    <div className="font-medium text-red-600">{vendor.paymentStatus}</div>
                </div>
            </div>

            {/* Users */}
            <div className="rounded-lg border border-gray-200 bg-white p-4">
                <h3 className="mb-3 font-medium text-gray-900">Users</h3>
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-gray-600">Used:</span>
                        <span className="text-sm font-medium text-gray-900">{vendor.usersUsed}</span>
                    </div>
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-gray-600">Allowed:</span>
                        <span className="text-sm font-medium text-gray-900">{vendor.usersAllowed}</span>
                    </div>
                    <div className="border-t border-gray-200 pt-3">
                        <div className={`text-sm font-medium ${vendor.usersUsed <= vendor.usersAllowed ? 'text-green-600' : 'text-red-600'}`}>
                            {vendor.usersUsed <= vendor.usersAllowed ? '✓ Within limit' : '✗ Over limit'}
                        </div>
                    </div>
                </div>
            </div>

            {/* Contact & Legal Details */}
            <div className="rounded-lg border border-gray-200 bg-white">
                <div className="flex items-center justify-between border-b border-gray-200 p-4">
                    <h3 className="font-semibold text-gray-900">Contact &amp; Legal Details</h3>
                </div>
                <div className="grid grid-cols-2 gap-4 p-4">
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5">
                            <Building2 className="h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
                            <span className="text-xs font-medium tracking-wide text-gray-600 uppercase">
                                Business Name<span className="ml-0.5 text-red-600">*</span>
                            </span>
                        </div>
                        <span className="text-sm text-gray-900">{vendor.businessName}</span>
                    </div>
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5">
                            <Mail className="h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
                            <span className="text-xs font-medium tracking-wide text-gray-600 uppercase">
                                Contact Email<span className="ml-0.5 text-red-600">*</span>
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-900">{vendor.email}</span>
                            <CopyButton text={vendor.email} />
                        </div>
                    </div>
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5">
                            <Phone className="h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
                            <span className="text-xs font-medium tracking-wide text-gray-600 uppercase">
                                Phone Number<span className="ml-0.5 text-red-600">*</span>
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-900">{vendor.phone}</span>
                            <CopyButton text={vendor.phone} />
                        </div>
                    </div>
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5">
                            <Globe className="h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
                            <span className="text-xs font-medium tracking-wide text-gray-600 uppercase">Website</span>
                        </div>
                        {vendor.website ? (
                        <a
                            href={vendor.website}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-sm text-purple-600 hover:underline"
                        >
                            {vendor.website}
                        </a>
                        ) : (
                        <span className="text-sm text-gray-400">Not set</span>
                        )}
                    </div>
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5">
                            <FileText className="h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
                            <span className="text-xs font-medium tracking-wide text-gray-600 uppercase">VAT Number</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-900">{vendor.vat ?? 'Not set'}</span>
                            {vendor.vat && <CopyButton text={vendor.vat} />}
                        </div>
                    </div>
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5">
                            <Building2 className="h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
                            <span className="text-xs font-medium tracking-wide text-gray-600 uppercase">Legal Entity Name</span>
                        </div>
                        <span className="text-sm text-gray-900">{vendor.legalEntity}</span>
                    </div>
                    <div className="col-span-2">
                        <div className="mb-1.5 flex items-center gap-1.5">
                            <MapPin className="h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
                            <span className="text-xs font-medium tracking-wide text-gray-600 uppercase">
                                Registered Address<span className="ml-0.5 text-red-600">*</span>
                            </span>
                        </div>
                        <span className="text-sm text-gray-900">{vendor.address}</span>
                    </div>
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5">
                            <MapPin className="h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
                            <span className="text-xs font-medium tracking-wide text-gray-600 uppercase">
                                City<span className="ml-0.5 text-red-600">*</span>
                            </span>
                        </div>
                        <span className="text-sm text-gray-900">{vendor.city}</span>
                    </div>
                    <div>
                        <div className="mb-1.5 flex items-center gap-1.5">
                            <MapPin className="h-3.5 w-3.5 text-gray-500" aria-hidden="true" />
                            <span className="text-xs font-medium tracking-wide text-gray-600 uppercase">
                                Country<span className="ml-0.5 text-red-600">*</span>
                            </span>
                        </div>
                        <span className="text-sm text-gray-900">{vendor.country}</span>
                    </div>
                </div>
            </div>

            {/* Recent Activity */}
            <div className="rounded-lg border border-gray-200 bg-white p-4">
                <h3 className="mb-3 font-medium text-gray-900">Recent Activity</h3>
                <div className="space-y-2 text-sm">
                    {vendor.recentActivity.map((activity, i) => (
                        <div
                            key={i}
                            className={`flex items-center justify-between py-2 ${
                                i < vendor.recentActivity.length - 1 ? 'border-b border-gray-100' : ''
                            }`}
                        >
                            <span className="text-gray-600">{activity.event}</span>
                            <span className="text-gray-500">{activity.time}</span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}

/* ─── Pending Changes Tab ─── */
function PendingChangesTab({ vendorSlug }: { vendorSlug: string }) {
    const { pendingChanges } = usePage<PageProps>().props;
    const changes = pendingChanges ?? [];

    if (changes.length === 0) {
        return (
            <div className="p-6">
                <div className="rounded-lg border border-gray-200 bg-white p-8 text-center">
                    <Check className="mx-auto mb-3 h-8 w-8 text-green-500" />
                    <h3 className="font-medium text-gray-900">No Pending Changes</h3>
                    <p className="mt-1 text-sm text-gray-500">All vendor information is up to date.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6 p-6">
            {changes.map((changeRequest) => (
            <div key={changeRequest.id} className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <button className="flex w-full items-center justify-between px-6 py-4 transition-colors hover:bg-gray-50">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100">
                            <TriangleAlert className="h-5 w-5 text-orange-600" aria-hidden="true" />
                        </div>
                        <div className="text-left">
                            <div className="font-semibold text-gray-900">Legal Information Changes</div>
                            <div className="text-sm text-gray-600">Submitted {changeRequest.submittedAt} by {changeRequest.submittedBy}</div>
                        </div>
                    </div>
                    <span className="rounded-full bg-orange-100 px-3 py-1 text-xs font-medium text-orange-700">
                        Urgent Review
                    </span>
                </button>

                <div className="border-t border-gray-200">
                    {/* Impact notice */}
                    <div className="flex items-start gap-3 border-b border-amber-100 bg-amber-50 px-6 py-4">
                        <Info className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />
                        <div className="text-sm text-amber-800">
                            <span className="font-medium">Impact:</span> Your current approved value will remain active on invoices and customer-facing pages until these changes are approved by Tavlo admin.
                        </div>
                    </div>

                    {/* Change fields */}
                    <div className="space-y-4 px-6 py-4">
                        {changeRequest.changes.map((change, i) => (
                            <div key={i} className="grid grid-cols-2 gap-4">
                                <div>
                                    <div className="mb-1 text-xs font-medium text-gray-500 uppercase">{change.field}</div>
                                    <div className="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                                        <span className="text-gray-500">Current: </span>{change.current}
                                    </div>
                                </div>
                                <div>
                                    <div className="mb-1 text-xs font-medium text-gray-500 uppercase">New Value</div>
                                    <div className="rounded border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-gray-900">
                                        {change.newValue}
                                    </div>
                                </div>
                                <div className="col-span-2 rounded border-l-2 border-amber-300 bg-amber-50 px-3 py-2 pl-3 text-xs text-gray-600">
                                    <span className="font-medium">Impact:</span> {change.impact}
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Vendor Notes */}
                    {changeRequest.vendorNotes && (
                    <div className="border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <div className="mb-2 text-xs font-medium text-gray-500 uppercase">Vendor Notes</div>
                        <div className="text-sm text-gray-700">
                            {changeRequest.vendorNotes}
                        </div>
                    </div>
                    )}

                    {/* Admin Notes */}
                    <div className="space-y-3 border-t border-gray-200 px-6 py-4">
                        <div>
                            <label className="mb-2 block text-xs font-medium text-gray-700">Admin Notes (Optional)</label>
                            <textarea
                                placeholder="Add any notes about this approval..."
                                className="w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm"
                                rows={2}
                            />
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-4">
                        <div className="text-xs text-gray-500">Changes will be logged to audit trail</div>
                        <div className="flex items-center gap-3">
                            <button
                                onClick={() => router.post(`/admin/vendor/${vendorSlug}/changes/${changeRequest.id}/decline`)}
                                className="flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-700 transition-colors hover:bg-red-50"
                            >
                                <X className="h-4 w-4" aria-hidden="true" />
                                Decline Changes
                            </button>
                            <button
                                onClick={() => router.post(`/admin/vendor/${vendorSlug}/changes/${changeRequest.id}/approve`)}
                                className="flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-green-700"
                            >
                                <Check className="h-4 w-4" aria-hidden="true" />
                                Approve Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            ))}
        </div>
    );
}

/* ─── Payments Tab ─── */
function PaymentsTab() {
    const { paymentData, paymentFailures24h } = usePage<PageProps>().props;
    const years = paymentData ?? [];

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900">Payment History</h2>
            </div>

            {/* Failed payment alert */}
            {(paymentFailures24h ?? 0) > 0 && (
            <div className="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <h3 className="mb-2 font-medium text-red-900">🔴 Failed Payments</h3>
                <p className="text-sm text-red-800">{paymentFailures24h} payment failures in the last 24 hours</p>
            </div>
            )}

            <div className="space-y-6">
                {years.map((yearData) => (
                    <div key={yearData.year} className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        {/* Year header */}
                        <div className="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                            <div className="flex items-center gap-3">
                                <Calendar className="h-4 w-4 text-gray-600" aria-hidden="true" />
                                <span className="font-semibold text-gray-900">Year {yearData.year}</span>
                                <span className="text-sm text-gray-600">
                                    {yearData.paidCount} paid, {yearData.unpaidCount} unpaid
                                </span>
                                <span className="text-sm font-medium text-gray-900">Total: {yearData.total}</span>
                            </div>
                            <button className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-green-700 transition-colors hover:bg-green-50">
                                <Download className="h-4 w-4" aria-hidden="true" />
                                Download Year
                            </button>
                        </div>

                        {/* Months */}
                        <div className="divide-y divide-gray-100">
                            {yearData.months.map((month) => (
                                <div key={month.name} className="p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-gray-900">{month.name}</span>
                                            <span className="text-sm text-gray-600">
                                                {month.paid}/{month.total} paid
                                            </span>
                                            <span className="text-sm font-medium text-gray-900">{month.amount}</span>
                                        </div>
                                        <button className="flex items-center gap-1.5 rounded px-2 py-1 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-100">
                                            <Download className="h-3.5 w-3.5" aria-hidden="true" />
                                            Download Month
                                        </button>
                                    </div>
                                    <div className="overflow-hidden rounded-lg bg-gray-50">
                                        <table className="w-full">
                                            <thead className="border-b border-gray-200 bg-gray-100">
                                                <tr>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Invoice ID</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Amount</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Status</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Date</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-600 uppercase">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100 bg-white">
                                                {month.invoices.map((inv) => (
                                                    <tr key={inv.id} className="hover:bg-gray-50">
                                                        <td className="px-3 py-2 font-mono text-sm">{inv.id}</td>
                                                        <td className="px-3 py-2 text-sm font-medium">{inv.amount}</td>
                                                        <td className="px-3 py-2">
                                                            <span
                                                                className={`rounded-full px-2 py-1 text-xs font-medium ${
                                                                    inv.status === 'paid'
                                                                        ? 'bg-green-100 text-green-700'
                                                                        : 'bg-yellow-100 text-yellow-700'
                                                                }`}
                                                            >
                                                                {inv.status}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 text-sm text-gray-600">{inv.date}</td>
                                                        <td className="px-3 py-2">
                                                            <button className="flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-purple-700 transition-colors hover:bg-purple-50">
                                                                <FileText className="h-3.5 w-3.5" aria-hidden="true" />
                                                                PDF
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ─── Subscription Tab ─── */
function SubscriptionTab() {
    const { subscriptionDetails } = usePage<PageProps>().props;
    const sub = subscriptionDetails ?? { plan: 'None', status: 'None', billingCycle: 'N/A', nextBillingDate: 'N/A', history: [] };

    return (
        <div className="p-6">
            <h2 className="mb-4 text-lg font-semibold text-gray-900">Subscription Details</h2>
            <div className="mb-6 rounded-lg border border-gray-200 bg-white p-6">
                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <div className="mb-1 text-sm text-gray-600">Current Plan</div>
                        <div className="text-xl font-semibold text-gray-900">{sub.plan}</div>
                    </div>
                    <div>
                        <div className="mb-1 text-sm text-gray-600">Status</div>
                        <div className={`text-xl font-semibold ${sub.status === 'Active' ? 'text-green-600' : 'text-gray-900'}`}>{sub.status}</div>
                    </div>
                    <div>
                        <div className="mb-1 text-sm text-gray-600">Billing Cycle</div>
                        <div className="text-sm font-medium text-gray-900">{sub.billingCycle}</div>
                    </div>
                    <div>
                        <div className="mb-1 text-sm text-gray-600">Next Billing Date</div>
                        <div className="text-sm font-medium text-gray-900">{sub.nextBillingDate}</div>
                    </div>
                </div>
            </div>

            {sub.history.length > 0 && (
            <>
            <h3 className="mb-3 font-medium text-gray-900">Subscription History</h3>
            <div className="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                {sub.history.map((item, i) => (
                    <div key={i} className="flex items-center justify-between p-4">
                        <div>
                            <div className="font-medium text-gray-900">{item.title}</div>
                            <div className="text-sm text-gray-600">{item.detail}</div>
                        </div>
                        <div className="text-sm text-gray-500">{item.time}</div>
                    </div>
                ))}
            </div>
            </>
            )}
        </div>
    );
}

/* ─── Orders Tab ─── */
function OrdersTab() {
    return (
        <div className="p-6">
            <h2 className="mb-4 text-lg font-semibold text-gray-900">Order History</h2>
            <p className="text-gray-600">Order history for Bella Italia</p>
        </div>
    );
}

/* ─── Reviews Tab ─── */
function ReviewsTab() {
    return (
        <div className="p-6">
            <h2 className="mb-4 text-lg font-semibold text-gray-900">Reviews &amp; Ratings</h2>
            <p className="text-gray-600">Customer reviews for Bella Italia</p>
        </div>
    );
}

/* ─── Activity Tab ─── */
function ActivityTab() {
    const { activities } = usePage<PageProps>().props;
    const events = activities ?? [];

    if (events.length === 0) {
        return (
            <div className="p-6">
                <h2 className="mb-4 text-lg font-semibold text-gray-900">Activity Timeline</h2>
                <div className="rounded-lg border border-gray-200 bg-white p-8 text-center">
                    <History className="mx-auto mb-3 h-8 w-8 text-gray-400" />
                    <h3 className="font-medium text-gray-900">No Activity</h3>
                    <p className="mt-1 text-sm text-gray-500">No activity recorded for this vendor yet.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="p-6">
            <h2 className="mb-4 text-lg font-semibold text-gray-900">Activity Timeline</h2>
            <div className="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                {events.map((event, i) => (
                    <div key={i} className="p-4">
                        <div className="flex items-start gap-3">
                            <div className={`mt-2 h-2 w-2 rounded-full ${event.color}`} />
                            <div className="flex-1">
                                <div className="font-medium text-gray-900">{event.title}</div>
                                <div className="text-sm text-gray-600">{event.detail}</div>
                                <div className="mt-1 text-xs text-gray-500">{event.time}</div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
