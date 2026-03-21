import { Head, Link, usePage } from '@inertiajs/react';
import {
    Users,
    ShoppingCart,
    CircleAlert,
    FileText,
    TrendingUp,
    Shield,
    Lock,
    Unlock,
    Search,
    Download,
    Eye,
    EllipsisVertical,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';
import { CheckboxCustom } from '@/components/checkbox-custom';

type Stats = {
    totalCustomers: number;
    totalOrders: number;
    flaggedAccounts: number;
    gdprRequests: number;
    highActivity: number;
};

type BackendCustomer = {
    id: string;
    accountType: string;
    email: string;
    phone: string;
    risk: string;
    riskTooltip: string | null;
    ordersCount: number;
    totalSpend: string;
    lastActive: string;
};

type StatCard = {
    icon: React.ElementType;
    value: number;
    label: string;
    iconColor: string;
};

function buildStatCards(stats: Stats): StatCard[] {
    return [
        { icon: Users, value: stats.totalCustomers, label: 'Total Customers', iconColor: 'text-gray-600' },
        { icon: ShoppingCart, value: stats.totalOrders, label: 'Total Orders', iconColor: 'text-gray-600' },
        { icon: CircleAlert, value: stats.flaggedAccounts, label: 'Flagged Accounts', iconColor: 'text-red-600' },
        { icon: FileText, value: stats.gdprRequests, label: 'GDPR Requests (30d)', iconColor: 'text-blue-600' },
        { icon: TrendingUp, value: stats.highActivity, label: 'High-Activity (30d)', iconColor: 'text-green-600' },
    ];
}

const riskConfig: Record<string, { border: string; bg: string; Icon: React.ElementType; iconColor: string }> = {
    red: { border: 'border-red-200', bg: 'bg-red-50', Icon: CircleAlert, iconColor: 'text-red-600' },
    orange: { border: 'border-orange-200', bg: 'bg-orange-50', Icon: TriangleAlert, iconColor: 'text-orange-600' },
};

const accountTypeColors: Record<string, string> = {
    Registered: 'bg-green-100 text-green-700',
    Guest: 'bg-gray-100 text-gray-700',
};

function Tooltip({ children, text }: { children: React.ReactNode; text: string }) {
    return (
        <div className="group relative">
            {children}
            <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 hidden -translate-x-1/2 group-hover:block">
                <div className="rounded-lg bg-gray-900 px-3 py-2 text-xs whitespace-nowrap text-white shadow-lg">
                    {text}
                    <div className="absolute top-full left-1/2 -mt-1 -translate-x-1/2">
                        <div className="border-4 border-transparent border-t-gray-900" />
                    </div>
                </div>
            </div>
        </div>
    );
}

function RiskCell({ risk, tooltip }: { risk: string; tooltip: string | null }) {
    if (risk === 'none' || !riskConfig[risk]) {
        return (
            <div className="flex h-8 w-8 items-center justify-center">
                <div className="h-2 w-2 rounded-full bg-gray-200" />
            </div>
        );
    }

    const { border, bg, Icon, iconColor } = riskConfig[risk];

    const btn = (
        <button
            className={`flex h-8 w-8 items-center justify-center rounded-lg border ${border} ${bg} transition-all hover:ring-2 hover:ring-offset-1`}
            aria-label={risk === 'red' ? '🔴 Flagged account' : '🟠 Unusual activity'}
        >
            <Icon className={`h-4 w-4 ${iconColor}`} aria-hidden="true" />
        </button>
    );

    if (tooltip) {
        return <Tooltip text={tooltip}>{btn}</Tooltip>;
    }
    return btn;
}

function HiddenField() {
    return (
        <div className="flex items-center gap-2 text-gray-400">
            <Lock className="h-3 w-3" aria-hidden="true" />
            <span className="text-sm">Hidden</span>
        </div>
    );
}

function RevealedField({ value }: { value: string }) {
    return <span className="text-sm text-gray-900">{value}</span>;
}

export default function AdminCustomersIndex() {
    const { stats, customersData } = usePage<{ stats: Stats; customersData: BackendCustomer[] }>().props;
    const [showRestricted, setShowRestricted] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedStat, setSelectedStat] = useState(0);
    const [selectedRows, setSelectedRows] = useState<Set<string>>(new Set());

    const statCards = buildStatCards(stats);

    const filteredCustomers = customersData.filter((c) => {
        if (searchQuery && !c.id.toLowerCase().includes(searchQuery.toLowerCase())) return false;
        return true;
    });

    const allSelected = filteredCustomers.length > 0 && filteredCustomers.every((c) => selectedRows.has(c.id));

    function toggleSelectAll() {
        if (allSelected) {
            setSelectedRows(new Set());
        } else {
            setSelectedRows(new Set(filteredCustomers.map((c) => c.id)));
        }
    }

    function toggleRow(id: string) {
        setSelectedRows((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    return (
        <AdminLayout>
            <Head title="Customers Management" />
            <div className="space-y-6 p-6">
                {/* GDPR Banner */}
                <div className={`rounded-lg border transition-colors ${showRestricted ? 'border-amber-200 bg-amber-50' : 'border-blue-200 bg-blue-50'}`}>
                    <div className="px-6 py-4">
                        <div className="flex items-start justify-between gap-6">
                            <div className="flex items-start gap-3">
                                <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${showRestricted ? 'bg-amber-100' : 'bg-blue-100'}`}>
                                    <Shield className={`h-5 w-5 ${showRestricted ? 'text-amber-600' : 'text-blue-600'}`} aria-hidden="true" />
                                </div>
                                <div>
                                    <h3 className={`mb-1 font-semibold ${showRestricted ? 'text-amber-900' : 'text-blue-900'}`}>
                                        Privacy & GDPR Compliance
                                    </h3>
                                    <p className={`text-sm ${showRestricted ? 'text-amber-800' : 'text-blue-800'}`}>
                                        {showRestricted
                                            ? 'Restricted data is currently visible. All access is logged for GDPR audit compliance. Hide data when no longer needed.'
                                            : 'Customer personal data (email, phone) is hidden by default for GDPR compliance. Enable only when necessary for customer support, fraud investigation, or legal requests.'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <button
                                    onClick={() => setShowRestricted(!showRestricted)}
                                    className={`flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors ${
                                        showRestricted ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-600 hover:bg-blue-700'
                                    }`}
                                >
                                    {showRestricted ? (
                                        <>
                                            <Unlock className="h-4 w-4" aria-hidden="true" />
                                            Hide Restricted Data
                                        </>
                                    ) : (
                                        <>
                                            <Lock className="h-4 w-4" aria-hidden="true" />
                                            Show Restricted Data
                                        </>
                                    )}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stat Cards */}
                <div>
                    <h2 className="mb-3 text-sm font-medium tracking-wide text-gray-700 uppercase">Customer Overview</h2>
                    <div className="grid grid-cols-5 gap-4">
                        {statCards.map((card, i) => (
                            <button
                                key={card.label}
                                onClick={() => setSelectedStat(i)}
                                className={`rounded-lg border bg-white p-4 text-left transition-all hover:shadow-sm ${
                                    selectedStat === i ? 'border-purple-200 ring-2 ring-purple-600' : 'border-gray-200'
                                }`}
                            >
                                <div className="mb-2 flex items-center justify-between">
                                    <card.icon className={`h-5 w-5 ${card.iconColor}`} aria-hidden="true" />
                                </div>
                                <div className="mb-1 text-2xl font-semibold text-gray-900">{card.value}</div>
                                <div className="text-sm text-gray-600">{card.label}</div>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Search + Export */}
                <div className="flex items-center justify-between gap-4">
                    <div className="max-w-md flex-1">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" aria-hidden="true" />
                            <input
                                type="text"
                                placeholder="Search: C-1024"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full rounded-lg border border-gray-300 py-2.5 pr-4 pl-11 text-sm focus:border-transparent focus:ring-2 focus:ring-purple-600 focus:outline-none"
                            />
                        </div>
                        <p className="mt-1 text-xs text-gray-500">💡 Hint: Search by Customer ID only (paste from order or support ticket)</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button className="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50">
                            <Download className="h-4 w-4" aria-hidden="true" />
                            Export All Filtered
                        </button>
                    </div>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-lg border border-gray-200 bg-white">
                    <table className="w-full">
                        <thead className="border-b border-gray-200 bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left">
                                    <CheckboxCustom
                                        checked={allSelected}
                                        onChange={toggleSelectAll}
                                    />
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Risk</th>
                                <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Customer ID</th>
                                <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Account Type</th>
                                <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Email</th>
                                <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Phone</th>
                                <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Orders</th>
                                <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Total Spend</th>
                                <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Last Active</th>
                                <th className="px-4 py-3 text-left text-xs font-medium tracking-wide text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {filteredCustomers.map((customer) => (
                                <tr key={customer.id} className="transition-colors hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <CheckboxCustom
                                            checked={selectedRows.has(customer.id)}
                                            onChange={() => toggleRow(customer.id)}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <RiskCell risk={customer.risk} tooltip={customer.riskTooltip} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="font-mono text-sm font-medium text-gray-900">{customer.id}</div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-1 text-xs font-medium ${accountTypeColors[customer.accountType] ?? 'bg-gray-100 text-gray-700'}`}>
                                            {customer.accountType}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {showRestricted ? <RevealedField value={customer.email} /> : <HiddenField />}
                                    </td>
                                    <td className="px-4 py-3">
                                        {showRestricted ? <RevealedField value={customer.phone} /> : <HiddenField />}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="text-sm text-gray-900">{customer.ordersCount}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="text-sm font-medium text-gray-900">{customer.totalSpend}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="text-sm text-gray-600">{customer.lastActive}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <Link href={`/admin/customer/${customer.id}/orders`} className="rounded p-1.5 transition-colors hover:bg-gray-100" title="View Customer Support Overview">
                                                <Eye className="h-4 w-4 text-gray-600" aria-hidden="true" />
                                            </Link>
                                            <button className="rounded p-1.5 transition-colors hover:bg-gray-100">
                                                <EllipsisVertical className="h-4 w-4 text-gray-600" aria-hidden="true" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {filteredCustomers.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="px-4 py-8 text-center text-sm text-gray-500">
                                        No customers found matching your search.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
