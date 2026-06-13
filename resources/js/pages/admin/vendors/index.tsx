import { Head, Link, usePage } from '@inertiajs/react';
import {
    Store,
    CircleCheckBig,
    CircleX,
    Globe,
    GlobeLock,
    CircleAlert,
    TriangleAlert,
    Clock,
    Star,
    Flag,
    Filter,
    Download,
    Repeat,
    CreditCard,
    Eye,
    Ban,
    EllipsisVertical,
    FileSpreadsheet,
    FileText,
    ShoppingCart,
    MessageSquare,
    History,
    Square,
    X,
    RotateCcw,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AdminLayout from '@/layouts/admin-layout';

type StatCard = {
    icon: React.ElementType;
    value: number;
    label: string;
    bg: string;
    border: string;
    hoverBg: string;
    hoverBorder: string;
    iconColor: string;
    textColor: string;
    tooltip: string;
};

type BackendVendor = {
    id: number;
    publicId: string;
    name: string;
    risk: string;
    riskTooltip: string;
    status: string;
    subscriptionPlan: string;
    subscriptionVariant: string;
    subscriptionExtra: string | null;
    paymentStatus: string;
    paymentVariant: string;
    paymentTooltip: string | null;
    ordersCount: number;
    revenue: string;
};

type Stats = {
    total: number;
    active: number;
    inactive: number;
    live: number;
    notLive: number;
};

function buildStatCards(stats: Stats): StatCard[] {
    return [
        { icon: Store, value: stats.total, label: 'Total Vendors', bg: 'bg-gray-50', border: 'border-gray-200', hoverBg: 'hover:bg-gray-100', hoverBorder: 'hover:border-gray-300', iconColor: 'text-gray-600', textColor: 'text-gray-900', tooltip: 'All vendors in system' },
        { icon: CircleCheckBig, value: stats.active, label: 'Active Vendors', bg: 'bg-green-50', border: 'border-green-200', hoverBg: 'hover:bg-green-100', hoverBorder: 'hover:border-green-300', iconColor: 'text-green-600', textColor: 'text-green-900', tooltip: 'Vendors with active subscription' },
        { icon: CircleX, value: stats.inactive, label: 'Inactive Vendors', bg: 'bg-red-50', border: 'border-red-200', hoverBg: 'hover:bg-red-100', hoverBorder: 'hover:border-red-300', iconColor: 'text-red-600', textColor: 'text-red-900', tooltip: 'Suspended or inactive vendors' },
        { icon: Globe, value: stats.live, label: 'Live Vendors', bg: 'bg-blue-50', border: 'border-blue-200', hoverBg: 'hover:bg-blue-100', hoverBorder: 'hover:border-blue-300', iconColor: 'text-blue-600', textColor: 'text-blue-900', tooltip: 'Menu published + accepting orders' },
        { icon: GlobeLock, value: stats.notLive, label: 'Not Live Vendors', bg: 'bg-orange-50', border: 'border-orange-200', hoverBg: 'hover:bg-orange-100', hoverBorder: 'hover:border-orange-300', iconColor: 'text-orange-600', textColor: 'text-orange-900', tooltip: 'Menu unpublished OR orders disabled' },
    ];
}

const riskIcons: Record<string, React.ElementType | undefined> = {
    red: CircleAlert,
    orange: TriangleAlert,
    yellow: Clock,
    none: undefined,
};

function mapVendor(v: BackendVendor): Vendor {
    return {
        id: v.id,
        publicId: v.publicId,
        name: v.name,
        risk: v.risk as RiskLevel,
        riskIcon: riskIcons[v.risk],
        riskTooltip: v.riskTooltip,
        status: v.status as Vendor['status'],
        subscription: { plan: v.subscriptionPlan, variant: v.subscriptionVariant as 'green' | 'red', extra: v.subscriptionExtra ?? undefined },
        payment: { status: v.paymentStatus, variant: v.paymentVariant as Vendor['payment']['variant'], tooltip: v.paymentTooltip ?? undefined },
        orders: v.ordersCount,
        revenue: v.revenue,
    };
}

type QuickFilter = {
    label: string;
    emoji?: string;
    count?: number;
    icon?: React.ElementType;
    bg: string;
    text: string;
    border: string;
    hoverBg: string;
};

const quickFilters: QuickFilter[] = [
    { label: 'All Vendors', bg: 'bg-gray-900', text: 'text-white', border: '', hoverBg: '' },
    { label: '🔴 Payment Issues', icon: CircleAlert, count: 2, bg: 'bg-red-50', text: 'text-red-700', border: 'border-red-200', hoverBg: 'hover:bg-red-100' },
    { label: '🟠 Subscription Issues', icon: TriangleAlert, count: 1, bg: 'bg-orange-50', text: 'text-orange-700', border: 'border-orange-200', hoverBg: 'hover:bg-orange-100' },
    { label: '🟡 Onboarding Stuck', icon: Clock, count: 1, bg: 'bg-yellow-50', text: 'text-yellow-700', border: 'border-yellow-200', hoverBg: 'hover:bg-yellow-100' },
    { label: '⭐ High GMV Vendors', icon: Star, count: 2, bg: 'bg-purple-50', text: 'text-purple-700', border: 'border-purple-200', hoverBg: 'hover:bg-purple-100' },
    { label: '⚠️ Flagged Content', icon: Flag, bg: 'bg-pink-50', text: 'text-pink-700', border: 'border-pink-200', hoverBg: 'hover:bg-pink-100' },
];

type RiskLevel = 'red' | 'orange' | 'yellow' | 'none';

type Vendor = {
    id: number;
    publicId: string;
    name: string;
    risk: RiskLevel;
    riskIcon?: React.ElementType;
    riskTooltip?: string;
    status: 'Active' | 'Pending' | 'Inactive';
    subscription: { plan: string; variant: 'green' | 'red'; extra?: string };
    payment: { status: string; variant: 'green' | 'red' | 'orange' | 'blue'; tooltip?: string };
    orders: number;
    revenue: string;
};

const riskColors: Record<RiskLevel, { border: string; bg: string; ring: string }> = {
    red: { border: 'border-red-200', bg: 'bg-red-50', ring: 'hover:ring-red-600' },
    orange: { border: 'border-orange-200', bg: 'bg-orange-50', ring: 'hover:ring-orange-600' },
    yellow: { border: 'border-yellow-200', bg: 'bg-yellow-50', ring: 'hover:ring-yellow-600' },
    none: { border: '', bg: '', ring: '' },
};

const riskIconColors: Record<RiskLevel, string> = {
    red: 'text-red-600',
    orange: 'text-orange-600',
    yellow: 'text-yellow-600',
    none: '',
};

const statusColors: Record<string, string> = {
    Active: 'bg-green-100 text-green-700',
    Pending: 'bg-yellow-100 text-yellow-700',
    Inactive: 'bg-red-100 text-red-700',
};

const paymentVariantColors: Record<string, { border: string; bg: string; text: string; ring: string }> = {
    green: { border: 'border-green-200', bg: 'bg-green-50', text: 'text-green-700', ring: 'hover:ring-green-700' },
    red: { border: 'border-red-200', bg: 'bg-red-50', text: 'text-red-700', ring: 'hover:ring-red-700' },
    orange: { border: 'border-orange-200', bg: 'bg-orange-50', text: 'text-orange-700', ring: 'hover:ring-orange-700' },
    blue: { border: 'border-blue-200', bg: 'bg-blue-50', text: 'text-blue-700', ring: 'hover:ring-blue-700' },
};

const subVariantColors: Record<string, { border: string; bg: string; text: string }> = {
    green: { border: 'border-green-200', bg: 'bg-green-50', text: 'text-green-700' },
    red: { border: 'border-red-200', bg: 'bg-red-50', text: 'text-red-700' },
};

function Tooltip({ children, text }: { children: React.ReactNode; text: string }) {
    return (
        <div className="relative group">
            {children}
            <div className="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 hidden group-hover:block z-50 pointer-events-none">
                <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
                    {text.split('\n').map((line, i) => (
                        <div key={i} className={i > 0 ? 'mt-1' : ''}>{line}</div>
                    ))}
                    <div className="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
                        <div className="border-4 border-transparent border-t-gray-900" />
                    </div>
                </div>
            </div>
        </div>
    );
}

function TooltipLeft({ children, text }: { children: React.ReactNode; text: string }) {
    return (
        <div className="relative group">
            {children}
            <div className="absolute left-0 bottom-full mb-2 hidden group-hover:block z-50 pointer-events-none">
                <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
                    {text.split('\n').map((line, i) => (
                        <div key={i} className={i > 0 ? 'mt-1' : ''}>{line}</div>
                    ))}
                    <div className="absolute top-full left-6 -mt-1">
                        <div className="border-4 border-transparent border-t-gray-900" />
                    </div>
                </div>
            </div>
        </div>
    );
}

function RiskCell({ vendor }: { vendor: Vendor }) {
    if (vendor.risk === 'none') {
        return (
            <div className="w-8 h-8 flex items-center justify-center">
                <div className="w-2 h-2 rounded-full bg-gray-200" />
            </div>
        );
    }

    const colors = riskColors[vendor.risk];
    const Icon = vendor.riskIcon!;

    return (
        <Tooltip text={vendor.riskTooltip!}>
            <button
                className={`w-8 h-8 rounded-lg border ${colors.border} ${colors.bg} flex items-center justify-center hover:ring-2 hover:ring-offset-1 ${colors.ring} transition-all`}
            >
                <Icon className={`w-4 h-4 ${riskIconColors[vendor.risk]}`} aria-hidden="true" />
            </button>
        </Tooltip>
    );
}

function SubscriptionCell({ subscription }: { subscription: Vendor['subscription'] }) {
    const colors = subVariantColors[subscription.variant];

    return (
        <div className="relative group">
            <button
                className={`px-3 py-1.5 rounded-lg border ${colors.border} ${colors.bg} ${colors.text} text-sm font-medium hover:ring-2 hover:ring-offset-1 transition-all inline-flex items-center gap-1.5`}
            >
                <Repeat className="w-3.5 h-3.5" aria-hidden="true" />
                <span>{subscription.plan}</span>
                {subscription.extra && (
                    <>
                        <span className="mx-1 text-gray-400">·</span>
                        <span className="inline-flex items-center gap-1">
                            <TriangleAlert className="w-3 h-3" aria-hidden="true" />
                            {subscription.extra}
                        </span>
                    </>
                )}
            </button>
            {subscription.extra && (
                <div className="absolute left-0 bottom-full mb-2 hidden group-hover:block z-50 pointer-events-none">
                    <div className="bg-gray-900 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap shadow-lg">
                        <div className="font-semibold text-red-300">⚠️ Revenue Risk</div>
                        <div className="mt-1">Expired 4 days ago</div>
                        <div className="absolute top-full left-6 -mt-1">
                            <div className="border-4 border-transparent border-t-gray-900" />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function PaymentCell({ payment }: { payment: Vendor['payment'] }) {
    const colors = paymentVariantColors[payment.variant];

    if (payment.tooltip) {
        return (
            <TooltipLeft text={payment.tooltip}>
                <button
                    className={`px-3 py-1.5 rounded-lg border ${colors.border} ${colors.bg} ${colors.text} text-sm font-medium hover:ring-2 hover:ring-offset-1 ${colors.ring} transition-all inline-flex items-center gap-1.5`}
                >
                    <CreditCard className="w-3.5 h-3.5" aria-hidden="true" />
                    {payment.status}
                </button>
            </TooltipLeft>
        );
    }

    return (
        <div className="relative group">
            <button
                className={`px-3 py-1.5 rounded-lg border ${colors.border} ${colors.bg} ${colors.text} text-sm font-medium hover:ring-2 hover:ring-offset-1 ${colors.ring} transition-all inline-flex items-center gap-1.5`}
            >
                <CreditCard className="w-3.5 h-3.5" aria-hidden="true" />
                {payment.status}
            </button>
        </div>
    );
}

function ActionsCell({ vendor }: { vendor: Vendor }) {
    const [open, setOpen] = useState(false);
    const identifier = vendor.id;
    return (
        <div className="flex items-center gap-2">
            <Link href={`/admin/vendor/${identifier}/overview`} className="p-1.5 hover:bg-gray-100 rounded transition-colors" title="View vendor details">
                <Eye className="w-4 h-4 text-gray-600" aria-hidden="true" />
            </Link>
            <button className="p-1.5 hover:bg-red-50 rounded transition-colors" title="Suspend vendor">
                <Ban className="w-4 h-4 text-red-600" aria-hidden="true" />
            </button>
            <div className="relative">
                <button className="p-1.5 hover:bg-gray-100 rounded transition-colors" title="More actions" onClick={() => setOpen(!open)}>
                    <EllipsisVertical className="w-4 h-4 text-gray-600" aria-hidden="true" />
                </button>
                {open && (
                    <>
                        <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
                        <div className="absolute right-0 top-full mt-1 bg-white rounded-lg shadow-lg border border-gray-200 py-1 min-w-[180px] z-20">
                            <Link href={`/admin/vendor/${identifier}/payments`} className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                <CreditCard className="w-4 h-4 text-gray-500" aria-hidden="true" />
                                View Payments
                            </Link>
                            <Link href={`/admin/vendor/${identifier}/subscription`} className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                <FileText className="w-4 h-4 text-gray-500" aria-hidden="true" />
                                View Subscription
                            </Link>
                            <Link href={`/admin/vendor/${identifier}/orders`} className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                <ShoppingCart className="w-4 h-4 text-gray-500" aria-hidden="true" />
                                View Orders
                            </Link>
                            <Link href={`/admin/vendor/${identifier}/reviews`} className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                <MessageSquare className="w-4 h-4 text-gray-500" aria-hidden="true" />
                                View Reviews
                            </Link>
                            <Link href={`/admin/vendor/${identifier}/activity`} className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                <History className="w-4 h-4 text-gray-500" aria-hidden="true" />
                                View Activity
                            </Link>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

export default function AdminVendorsIndex() {
    const { stats, vendorsData } = usePage<{ stats: Stats; vendorsData: BackendVendor[] }>().props;
    const vendors = (vendorsData ?? []).map(mapVendor);
    const statCards = buildStatCards(stats ?? { total: 0, active: 0, inactive: 0, live: 0, notLive: 0 });

    const [activeFilter, setActiveFilter] = useState(0);
    const [selectAll, setSelectAll] = useState(false);
    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [exportOpen, setExportOpen] = useState(false);
    const [exportAll, setExportAll] = useState(false);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [filterPlans, setFilterPlans] = useState<Set<string>>(new Set());
    const [filterCountries, setFilterCountries] = useState<Set<string>>(new Set());
    const [filterCities, setFilterCities] = useState<Set<string>>(new Set());
    const [filterLive, setFilterLive] = useState('both');
    const [filterSubStatus, setFilterSubStatus] = useState<Set<string>>(new Set());
    const exportRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (exportRef.current && !exportRef.current.contains(e.target as Node)) {
                setExportOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    function toggleSelectAll() {
        if (selectAll) {
            setSelected(new Set());
        } else {
            setSelected(new Set(vendors.map((v) => v.id)));
        }
        setSelectAll(!selectAll);
    }

    function toggleSelect(id: number) {
        const next = new Set(selected);
        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }
        setSelected(next);
        setSelectAll(next.size === vendors.length);
    }

    const activeFilterCount = filterPlans.size + filterCountries.size + filterCities.size + filterSubStatus.size + (filterLive !== 'both' ? 1 : 0);

    function clearAllFilters() {
        setFilterPlans(new Set());
        setFilterCountries(new Set());
        setFilterCities(new Set());
        setFilterLive('both');
        setFilterSubStatus(new Set());
    }

    function toggleSet(set: Set<string>, value: string, setter: (s: Set<string>) => void) {
        const next = new Set(set);
        if (next.has(value)) next.delete(value); else next.add(value);
        setter(next);
    }

    return (
        <AdminLayout>
            <Head title="Vendors Management" />

            <div className="flex-1 bg-gray-50">
                {/* Stat Cards */}
                <div className="px-6 py-4">
                    <div className="grid grid-cols-5 gap-4">
                        {statCards.map((card) => (
                            <button
                                key={card.label}
                                className={`relative p-4 rounded-lg border-2 transition-all ${card.bg} ${card.border} ${card.hoverBg} ${card.hoverBorder}`}
                                title={card.tooltip}
                            >
                                <div className="flex items-start justify-between mb-2">
                                    <card.icon className={`w-5 h-5 ${card.iconColor}`} aria-hidden="true" />
                                </div>
                                <div className={`text-2xl font-bold ${card.textColor} mb-1`}>{card.value}</div>
                                <div className={`text-xs font-medium ${card.textColor} opacity-70`}>{card.label}</div>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Quick Filters */}
                <div className="px-6 py-3">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-gray-700 mr-2">Quick Filters:</span>
                        {quickFilters.map((filter, i) => (
                            <button
                                key={filter.label}
                                onClick={() => setActiveFilter(i)}
                                className={
                                    i === 0 && activeFilter === 0
                                        ? 'px-3 py-1.5 rounded-lg text-sm font-medium transition-all bg-gray-900 text-white'
                                        : i === activeFilter
                                          ? `px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${filter.bg} ${filter.text} border ${filter.border} ring-2 ring-offset-1`
                                          : i === 0
                                            ? 'px-3 py-1.5 rounded-lg border text-sm font-medium transition-all bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                            : `px-3 py-1.5 rounded-lg border text-sm font-medium transition-all inline-flex items-center gap-1.5 ${filter.bg} ${filter.text} ${filter.border} ${filter.hoverBg}`
                                }
                            >
                                {filter.icon && <filter.icon className="w-4 h-4 inline-block mr-1" aria-hidden="true" />}
                                <span>{filter.label}</span>
                                {filter.count !== undefined && (
                                    <span className="ml-1 px-1.5 py-0.5 rounded-full text-xs font-semibold bg-black/10">
                                        {filter.count}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Advanced Filters */}
                <div className="bg-white border-b border-gray-200 px-6 py-3">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <button
                                onClick={() => setFiltersOpen(!filtersOpen)}
                                className={`flex items-center gap-2 px-3 py-1.5 rounded-lg border transition-all ${
                                    filtersOpen
                                        ? 'bg-purple-50 border-purple-600 text-purple-700'
                                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                }`}
                            >
                                <Filter className="w-4 h-4" aria-hidden="true" />
                                <span className="text-sm font-medium">Advanced Filters</span>
                                {activeFilterCount > 0 && (
                                    <span className="px-1.5 py-0.5 rounded-full text-xs font-semibold bg-purple-600 text-white">
                                        {activeFilterCount}
                                    </span>
                                )}
                            </button>

                            {activeFilterCount > 0 && (
                                <button
                                    onClick={clearAllFilters}
                                    className="flex items-center gap-1.5 px-2.5 py-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                                >
                                    <RotateCcw className="w-3.5 h-3.5" aria-hidden="true" />
                                    Clear all
                                </button>
                            )}
                        </div>
                    </div>

                    {filtersOpen && (
                        <div className="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div className="grid grid-cols-4 gap-6">
                                {/* Subscription Plan */}
                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">Subscription Plan</label>
                                    <div className="space-y-2">
                                        {['Basic', 'Standard', 'Premium', 'Trial'].map((plan) => (
                                            <label key={plan} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                                                <input
                                                    type="checkbox"
                                                    className="checkbox-custom"
                                                    checked={filterPlans.has(plan)}
                                                    onChange={() => toggleSet(filterPlans, plan, setFilterPlans)}
                                                />
                                                <span className="text-gray-900">{plan}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                {/* Country */}
                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">Country</label>
                                    <div className="space-y-2">
                                        {['Austria', 'Germany', 'Switzerland'].map((country) => (
                                            <label key={country} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                                                <input
                                                    type="checkbox"
                                                    className="checkbox-custom"
                                                    checked={filterCountries.has(country)}
                                                    onChange={() => toggleSet(filterCountries, country, setFilterCountries)}
                                                />
                                                <span className="text-gray-900">{country}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                {/* City */}
                                <div>
                                    <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">City</label>
                                    <div className="space-y-2 max-h-32 overflow-y-auto">
                                        {['Vienna', 'Salzburg', 'Innsbruck', 'Graz', 'Munich', 'Berlin', 'Hamburg', 'Frankfurt', 'Zurich', 'Geneva', 'Basel', 'Bern'].map((city) => (
                                            <label key={city} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                                                <input
                                                    type="checkbox"
                                                    className="checkbox-custom"
                                                    checked={filterCities.has(city)}
                                                    onChange={() => toggleSet(filterCities, city, setFilterCities)}
                                                />
                                                <span className="text-gray-900">{city}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                {/* Live Status + Subscription Status */}
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">Live Status</label>
                                        <div className="space-y-2">
                                            {['Live', 'Not Live', 'Both'].map((opt) => (
                                                <label key={opt} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                                                    <input
                                                        type="radio"
                                                        name="liveStatus"
                                                        className="radio-custom"
                                                        checked={filterLive === opt.toLowerCase().replace(' ', '-')}
                                                        onChange={() => setFilterLive(opt.toLowerCase().replace(' ', '-'))}
                                                    />
                                                    <span className="text-gray-900">{opt}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">Subscription</label>
                                        <div className="space-y-2">
                                            {['Active', 'Expired', 'Trial', 'Overdue'].map((status) => (
                                                <label key={status} className="flex items-center gap-2 text-sm cursor-pointer hover:bg-white px-2 py-1 rounded">
                                                    <input
                                                        type="checkbox"
                                                        className="checkbox-custom"
                                                        checked={filterSubStatus.has(status)}
                                                        onChange={() => toggleSet(filterSubStatus, status, setFilterSubStatus)}
                                                    />
                                                    <span className="text-gray-900">{status}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Count + Export */}
                <div className="px-6 py-4 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="text-sm text-gray-600">
                            Showing <span className="font-semibold text-gray-900">{vendors.length}</span> of{' '}
                            <span className="font-semibold text-gray-900">{vendors.length}</span> vendors
                        </div>
                    </div>
                    <div className="relative" ref={exportRef}>
                        <button
                            onClick={() => setExportOpen(!exportOpen)}
                            className="flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg text-sm font-medium transition-colors"
                        >
                            <Download className="w-4 h-4" aria-hidden="true" />
                            Export
                        </button>

                        {exportOpen && (
                            <div className="absolute right-0 top-full mt-1.5 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50 overflow-hidden">
                                <div className="px-3 py-2.5 border-b border-gray-100">
                                    <div className="flex items-center gap-2">
                                        <FileSpreadsheet className="w-4 h-4 text-green-600" aria-hidden="true" />
                                        <div>
                                            <div className="text-sm font-semibold text-gray-900">Export to Excel</div>
                                            <div className="text-xs text-gray-500">Export vendor data with all fields</div>
                                        </div>
                                    </div>
                                </div>

                                <div className="px-3 py-2.5 border-b border-gray-100">
                                    <label className="flex items-center gap-2 cursor-pointer" onClick={() => setExportAll(!exportAll)}>
                                        <Square
                                            className={`w-4 h-4 ${exportAll ? 'text-blue-600' : 'text-gray-300'}`}
                                            aria-hidden="true"
                                        />
                                        <div>
                                            <div className="text-sm font-semibold text-gray-900">Export All ({vendors.length})</div>
                                            <div className="text-xs text-gray-500">Export all vendors in system</div>
                                        </div>
                                    </label>
                                </div>

                                <div className="px-3 py-2.5">
                                    <div className="text-xs font-semibold text-gray-900 mb-1.5">Exported Fields:</div>
                                    <ul className="space-y-0.5 text-xs text-gray-500">
                                        <li>• Vendor ID, Name, Category</li>
                                        <li>• Location (Country, City, Address)</li>
                                        <li>• Status, Live Status, Subscription</li>
                                        <li>• Payment, GMV, Rating</li>
                                        <li>• Contact (Email, Phone, Website, VAT)</li>
                                        <li>• Timestamps (Created, Last Active)</li>
                                    </ul>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Vendor Table */}
                <div className="px-6 pb-6">
                    <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <table className="w-full">
                            <thead className="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th className="text-left px-4 py-3 w-12">
                                        <input
                                            type="checkbox"
                                            className="checkbox-custom"
                                            title="Select all vendors"
                                            checked={selectAll}
                                            onChange={toggleSelectAll}
                                        />
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide w-12">Risk</th>
                                    <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">Vendor</th>
                                    <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">Status</th>
                                    <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">Subscription</th>
                                    <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">Payment</th>
                                    <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">Orders</th>
                                    <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide">Revenue</th>
                                    <th className="text-left px-4 py-3 text-xs font-medium text-gray-600 uppercase tracking-wide w-32">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {vendors.map((vendor) => (
                                    <tr key={vendor.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3">
                                            <input
                                                type="checkbox"
                                                className="checkbox-custom"
                                                checked={selected.has(vendor.id)}
                                                onChange={() => toggleSelect(vendor.id)}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <RiskCell vendor={vendor} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <div>
                                                <div className="font-medium text-gray-900">{vendor.name}</div>
                                                <div className="text-xs text-gray-500 font-mono">{vendor.publicId}</div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${statusColors[vendor.status]}`}>
                                                {vendor.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <SubscriptionCell subscription={vendor.subscription} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <PaymentCell payment={vendor.payment} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="text-sm text-gray-900">{vendor.orders.toLocaleString()}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="text-sm font-medium text-gray-900">{vendor.revenue}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <ActionsCell vendor={vendor} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
