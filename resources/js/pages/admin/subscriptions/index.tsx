import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Plus,
    DollarSign,
    Users,
    TrendingUp,
    CircleAlert,
    ExternalLink,
    SquarePen,
    Check,
    Star,
    X,
    Info,
    Lock,
} from 'lucide-react';
import AdminLayout from '@/layouts/admin-layout';
import { useState } from 'react';

type Tab = 'plans' | 'active' | 'overdue';

interface Plan {
    id: number;
    name: string;
    description: string | null;
    monthlyPrice: number;
    yearlyPrice: number;
    yearlySavings: number;
    maxUsers: number;
    activeSubs: number;
    mrr: string;
    featureCount: number;
    isPopular: boolean;
    parentPlanId: number | null;
    parentPlanName: string | null;
    previewFeatures: string[];
    moreCount: number;
    featureNames: string[];
}

interface Feature {
    id: number;
    name: string;
    description: string;
    requires?: string;
}

interface FeatureCategory {
    name: string;
    features: Feature[];
}

interface SubscriptionRow {
    vendor: string;
    vendorId: string;
    plan: string;
    planColor: string;
    status: string;
    startDate: string;
    nextBilling: string;
    mrr: string;
    autoRenew: boolean;
}

interface Metrics {
    totalMrr: number;
    activeSubsCount: number;
    planCount: number;
    overdueTotal: number;
    overdueCount: number;
}

interface PageProps {
    [key: string]: unknown;
    tab: Tab;
    plans: Plan[];
    features: FeatureCategory[];
    activeSubscriptions: SubscriptionRow[];
    overdueSubscriptions: SubscriptionRow[];
    metrics: Metrics;
}

export default function SubscriptionsIndex() {
    const { tab, plans, features, activeSubscriptions, overdueSubscriptions, metrics } = usePage<PageProps>().props;
    const activeTab = tab || 'plans';
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingPlan, setEditingPlan] = useState<Plan | null>(null);

    const tabs: { key: Tab; label: string; count: number; href: string }[] = [
        { key: 'plans', label: 'Plans', count: plans.length, href: '/admin/subscriptions/plans' },
        { key: 'active', label: 'Active', count: activeSubscriptions.length, href: '/admin/subscriptions/active' },
        { key: 'overdue', label: 'Overdue', count: overdueSubscriptions.length, href: '/admin/subscriptions/overdue' },
    ];

    return (
        <AdminLayout>
            <Head title="Subscription Management" />
            <div className="p-6">
                {/* Header */}
                <div className="mb-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="mb-1 text-2xl text-gray-900">Subscription Management</h1>
                            <p className="text-sm text-gray-500">
                                Feature-based plans with hierarchical inheritance
                            </p>
                        </div>
                        <button
                            onClick={() => setShowCreateModal(true)}
                            className="flex items-center gap-2 rounded-lg bg-purple-600 px-6 py-2.5 text-white hover:bg-purple-700"
                        >
                            <Plus className="h-4 w-4" />
                            Create Plan
                        </button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div className="rounded-xl border border-gray-200 bg-white p-5">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-sm text-gray-600">Total MRR</span>
                            <DollarSign className="h-4 w-4 text-green-500" />
                        </div>
                        <div className="mb-1 text-2xl font-semibold text-gray-900">€{metrics.totalMrr.toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</div>
                        <div className="text-xs text-gray-500">Monthly recurring revenue</div>
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-5">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-sm text-gray-600">Active Subs</span>
                            <Users className="h-4 w-4 text-blue-500" />
                        </div>
                        <div className="mb-1 text-2xl font-semibold text-gray-900">{metrics.activeSubsCount.toLocaleString()}</div>
                        <div className="text-xs text-gray-500">Across {metrics.planCount} plans</div>
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-5">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-sm text-gray-600">Churn Rate</span>
                            <TrendingUp className="h-4 w-4 text-orange-500" />
                        </div>
                        <div className="mb-1 text-2xl font-semibold text-gray-900">—</div>
                        <div className="text-xs text-gray-500">Not yet tracked</div>
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-5">
                        <div className="mb-2 flex items-center justify-between">
                            <span className="text-sm text-gray-600">Overdue</span>
                            <CircleAlert className="h-4 w-4 text-red-500" />
                        </div>
                        <div className="mb-1 text-2xl font-semibold text-gray-900">€{metrics.overdueTotal.toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</div>
                        <div className="text-xs text-red-600">{metrics.overdueCount} subscriptions</div>
                    </div>
                </div>

                {/* Tabs */}
                <div className="mb-6 border-b border-gray-200">
                    <div className="flex gap-6">
                        {tabs.map((tab) => (
                            <Link
                                key={tab.key}
                                href={tab.href}
                                className={`flex items-center gap-2 border-b-2 pb-3 transition-colors ${
                                    activeTab === tab.key
                                        ? 'border-purple-600 text-purple-600'
                                        : 'border-transparent text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                <span className="text-sm font-medium">{tab.label}</span>
                                <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                    {tab.count}
                                </span>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Tab Content */}
                {activeTab === 'plans' && <PlansTab plans={plans} onEditPlan={setEditingPlan} />}
                {activeTab === 'active' && <SubscriptionsTable rows={activeSubscriptions} />}
                {activeTab === 'overdue' && <SubscriptionsTable rows={overdueSubscriptions} />}
            </div>

            {showCreateModal && <CreatePlanModal plans={plans} featureCategories={features} onClose={() => setShowCreateModal(false)} />}
            {editingPlan && <EditPlanModal plan={editingPlan} plans={plans} featureCategories={features} onClose={() => setEditingPlan(null)} />}
        </AdminLayout>
    );
}

function PlansTab({ plans, onEditPlan }: { plans: Plan[]; onEditPlan: (plan: Plan) => void }) {
    return (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            {plans.map((plan) => (
                <div
                    key={plan.name}
                    className={`relative rounded-xl border-2 bg-white p-6 ${
                        plan.isPopular ? 'border-purple-500' : 'border-gray-200'
                    }`}
                >
                    {plan.isPopular && (
                        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                            <span className="flex items-center gap-1 rounded-full bg-purple-500 px-3 py-1 text-xs font-medium text-white">
                                <Star className="h-3 w-3 fill-white" />
                                Most Popular
                            </span>
                        </div>
                    )}

                    <div className="mb-6 text-center">
                        <h3 className="mb-3 text-xl font-semibold text-gray-900">{plan.name}</h3>
                        <div className="mb-2">
                            <div className="flex items-baseline justify-center gap-1">
                                <span className="text-3xl font-bold text-gray-900">€{plan.monthlyPrice}</span>
                                <span className="text-gray-500">/month</span>
                            </div>
                        </div>
                        <div className="mb-3">
                            <div className="flex items-baseline justify-center gap-1">
                                <span className="text-xl font-semibold text-green-700">€{plan.yearlyPrice}</span>
                                <span className="text-sm text-gray-500">/year</span>
                            </div>
                            <div className="text-xs text-green-600">Save €{plan.yearlySavings}/year</div>
                        </div>
                        <div className="mb-1 text-sm text-gray-500">{plan.activeSubs} active subscriptions</div>
                        <div className="text-sm font-medium text-green-600">MRR: {plan.mrr}</div>
                    </div>

                    <div className="mb-6">
                        <button className="w-full rounded-lg border border-blue-200 bg-blue-50 p-3 text-left transition-colors hover:bg-blue-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <div className="text-sm font-medium text-blue-900">
                                        Includes {plan.featureCount} features
                                    </div>
                                    <div className="mt-0.5 text-xs text-blue-700">Click to view and edit features</div>
                                </div>
                                <ExternalLink className="h-4 w-4 text-blue-600" />
                            </div>
                        </button>
                        <div className="mt-3 space-y-1.5">
                            {plan.previewFeatures.map((feature) => (
                                <div key={feature} className="flex items-start gap-2 text-xs">
                                    <Check className="mt-0.5 h-3 w-3 shrink-0 text-green-600" />
                                    <span className="text-gray-700">{feature}</span>
                                </div>
                            ))}
                            <div className="pl-5 text-xs text-gray-500">+ {plan.moreCount} more features</div>
                        </div>
                    </div>

                    <div className="space-y-2 border-t border-gray-200 pt-4">
                        <button
                            onClick={() => onEditPlan(plan)}
                            className="flex w-full items-center justify-center gap-2 rounded-lg border border-purple-600 py-2 text-sm text-purple-600 hover:bg-purple-50"
                        >
                            <SquarePen className="h-4 w-4" />
                            Edit Plan
                        </button>
                        <button
                            className="flex w-full items-center justify-center gap-2 rounded-lg border border-gray-300 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            title="Navigate to Vendors page with plan filter"
                        >
                            View Subscribers
                            <ExternalLink className="h-3 w-3" />
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}

function SubscriptionsTable({ rows }: { rows: SubscriptionRow[] }) {
    const planColorMap: Record<string, string> = {
        purple: 'bg-purple-100 text-purple-700',
        blue: 'bg-blue-100 text-blue-700',
        gray: 'bg-gray-100 text-gray-700',
    };

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white">
            <table className="w-full">
                <thead className="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th className="px-6 py-3 text-left text-xs tracking-wider text-gray-600 uppercase">Vendor</th>
                        <th className="px-6 py-3 text-left text-xs tracking-wider text-gray-600 uppercase">Plan</th>
                        <th className="px-6 py-3 text-left text-xs tracking-wider text-gray-600 uppercase">Status</th>
                        <th className="px-6 py-3 text-left text-xs tracking-wider text-gray-600 uppercase">Start Date</th>
                        <th className="px-6 py-3 text-left text-xs tracking-wider text-gray-600 uppercase">Next Billing</th>
                        <th className="px-6 py-3 text-left text-xs tracking-wider text-gray-600 uppercase">MRR</th>
                        <th className="px-6 py-3 text-left text-xs tracking-wider text-gray-600 uppercase">Auto-Renew</th>
                        <th className="px-6 py-3 text-right text-xs tracking-wider text-gray-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {rows.length === 0 ? (
                        <tr>
                            <td colSpan={8} className="px-6 py-12 text-center">
                                <p className="text-sm font-medium text-gray-900">No subscriptions found</p>
                                <p className="mt-1 text-xs text-gray-500">Subscriptions will appear here once vendors subscribe to a plan.</p>
                            </td>
                        </tr>
                    ) : rows.map((row) => (
                        <tr key={row.vendorId} className="hover:bg-gray-50">
                            <td className="px-6 py-4">
                                <div>
                                    <div className="font-medium text-gray-900">{row.vendor}</div>
                                    <div className="text-xs text-gray-500">{row.vendorId}</div>
                                </div>
                            </td>
                            <td className="px-6 py-4">
                                <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${planColorMap[row.planColor] || 'bg-gray-100 text-gray-700'}`}>
                                    {row.plan}
                                </span>
                            </td>
                            <td className="px-6 py-4">
                                <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                                    row.status === 'Active'
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-red-100 text-red-700'
                                }`}>
                                    {row.status}
                                </span>
                            </td>
                            <td className="px-6 py-4 text-sm text-gray-900">{row.startDate}</td>
                            <td className="px-6 py-4 text-sm text-gray-900">{row.nextBilling}</td>
                            <td className="px-6 py-4 text-sm font-medium text-gray-900">{row.mrr}</td>
                            <td className="px-6 py-4">
                                <span className={`text-sm ${row.autoRenew ? 'text-green-600' : 'text-red-600'}`}>
                                    {row.autoRenew ? 'Yes' : 'No'}
                                </span>
                            </td>
                            <td className="px-6 py-4 text-right">
                                <button className="text-sm font-medium text-purple-600 hover:text-purple-700">
                                    View in Vendor Detail
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function FeatureCheckboxList({
    featureCategories,
    selectedFeatures,
    onToggle,
    inheritedFeatures = [],
    inheritedFrom,
}: {
    featureCategories: FeatureCategory[];
    selectedFeatures: Set<string>;
    onToggle: (name: string) => void;
    inheritedFeatures?: string[];
    inheritedFrom?: string;
}) {
    const count = selectedFeatures.size;

    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-semibold tracking-wide text-gray-900 uppercase">Features</h3>
                <span className="text-sm text-gray-600">{count} features selected</span>
            </div>
            <div className="space-y-4">
                {featureCategories.map((category) => (
                    <div key={category.name} className="rounded-lg border border-gray-200 p-4">
                        <h4 className="mb-3 text-sm font-semibold text-gray-900">{category.name}</h4>
                        <div className="space-y-2">
                            {category.features.map((feature) => {
                                const isInherited = inheritedFeatures.includes(feature.name);
                                const isChecked = selectedFeatures.has(feature.name);

                                return (
                                    <div
                                        key={feature.name}
                                        className={`flex items-start gap-3 rounded-lg p-2 ${
                                            isInherited ? 'bg-gray-50' : 'hover:bg-gray-50'
                                        }`}
                                    >
                                        <input
                                            type="checkbox"
                                            className="checkbox-custom mt-1"
                                            checked={isChecked || isInherited}
                                            disabled={isInherited}
                                            onChange={() => !isInherited && onToggle(feature.name)}
                                        />
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className={`text-sm font-medium ${isInherited ? 'text-gray-600' : 'text-gray-900'}`}>
                                                    {feature.name}
                                                </span>
                                                {isInherited && inheritedFrom && (
                                                    <span className="flex items-center gap-1 rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-700">
                                                        <Lock className="h-3 w-3" />
                                                        Included from {inheritedFrom}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-0.5 text-xs text-gray-500">{feature.description}</p>
                                            {feature.requires && (
                                                <p className="mt-1 text-xs text-blue-600">Requires: {feature.requires}</p>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function CreatePlanModal({ plans, featureCategories, onClose }: { plans: Plan[]; featureCategories: FeatureCategory[]; onClose: () => void }) {
    const [name, setName] = useState('');
    const [monthlyPrice, setMonthlyPrice] = useState(0);
    const [yearlyPrice, setYearlyPrice] = useState(0);
    const [maxUsers, setMaxUsers] = useState(5);
    const [parentPlanId, setParentPlanId] = useState('');
    const [isPopular, setIsPopular] = useState(false);
    const [selectedFeatures, setSelectedFeatures] = useState<Set<string>>(new Set());
    const [processing, setProcessing] = useState(false);

    const allFeaturesByName = featureCategories.flatMap((c) => c.features);

    const toggleFeature = (featureName: string) => {
        setSelectedFeatures((prev) => {
            const next = new Set(prev);
            if (next.has(featureName)) {
                next.delete(featureName);
            } else {
                next.add(featureName);
            }
            return next;
        });
    };

    const handleSubmit = () => {
        const featureIds = allFeaturesByName
            .filter((f) => selectedFeatures.has(f.name))
            .map((f) => f.id);

        setProcessing(true);
        router.post('/admin/subscriptions/plans', {
            name,
            monthly_price: monthlyPrice,
            yearly_price: yearlyPrice,
            max_users: maxUsers,
            parent_plan_id: parentPlanId || null,
            is_popular: isPopular,
            feature_ids: featureIds,
        }, {
            onFinish: () => setProcessing(false),
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-900">Create New Plan</h2>
                    <button onClick={onClose} className="rounded-lg p-2 hover:bg-gray-100">
                        <X className="h-5 w-5" />
                    </button>
                </div>
                <div className="space-y-6">
                    {/* Plan Name */}
                    <div>
                        <label className="mb-2 block text-sm font-medium text-gray-900">Plan Name</label>
                        <input
                            type="text"
                            className="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
                            placeholder="Premium"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </div>

                    {/* Pricing */}
                    <div>
                        <h3 className="mb-3 text-sm font-semibold tracking-wide text-gray-900 uppercase">Pricing</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-900">Monthly Price (€)</label>
                                <input
                                    type="number"
                                    className="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
                                    placeholder="99"
                                    value={monthlyPrice}
                                    onChange={(e) => setMonthlyPrice(Number(e.target.value))}
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-900">Yearly Price (€)</label>
                                <input
                                    type="number"
                                    className="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
                                    placeholder="950"
                                    value={yearlyPrice}
                                    onChange={(e) => setYearlyPrice(Number(e.target.value))}
                                />
                                <p className="mt-1 text-xs text-gray-500">Yearly billing typically offers a discount (~20%)</p>
                            </div>
                        </div>
                    </div>

                    {/* Limits */}
                    <div>
                        <h3 className="mb-3 text-sm font-semibold tracking-wide text-gray-900 uppercase">Limits</h3>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-gray-900">Maximum users</label>
                            <input
                                type="number"
                                className="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
                                min={1}
                                placeholder="5"
                                value={maxUsers}
                                onChange={(e) => setMaxUsers(Number(e.target.value))}
                            />
                            <p className="mt-1 text-xs text-gray-500">Maximum number of staff accounts allowed for this plan.</p>
                        </div>
                    </div>

                    {/* Base Plan */}
                    <div>
                        <h3 className="mb-3 text-sm font-semibold tracking-wide text-gray-900 uppercase">Base Plan (Optional)</h3>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-gray-900">Inherit from</label>
                            <select
                                className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-purple-500 focus:ring-purple-500 focus:outline-none"
                                value={parentPlanId}
                                onChange={(e) => setParentPlanId(e.target.value)}
                            >
                                <option value="">None (Start from scratch)</option>
                                {plans.map((p) => (
                                    <option key={p.id} value={p.id}>{p.name}</option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-gray-500">Select a base plan to inherit features from.</p>
                        </div>
                    </div>

                    {/* Features */}
                    <FeatureCheckboxList featureCategories={featureCategories} selectedFeatures={selectedFeatures} onToggle={toggleFeature} />

                    {/* Most Popular */}
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            className="checkbox-custom"
                            checked={isPopular}
                            onChange={(e) => setIsPopular(e.target.checked)}
                        />
                        <span className="text-sm font-medium text-gray-900">Mark as "Most Popular"</span>
                    </label>
                </div>
                <div className="mt-6 flex gap-3 border-t border-gray-200 pt-6">
                    <button
                        onClick={onClose}
                        className="flex-1 rounded-lg border border-gray-300 py-2.5 font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleSubmit}
                        disabled={processing}
                        className="flex-1 rounded-lg bg-purple-600 py-2.5 font-medium text-white hover:bg-purple-700 disabled:opacity-50"
                    >
                        {processing ? 'Creating...' : 'Create Plan'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function EditPlanModal({ plan, plans, featureCategories, onClose }: { plan: Plan; plans: Plan[]; featureCategories: FeatureCategory[]; onClose: () => void }) {
    const [name, setName] = useState(plan.name);
    const [monthlyPrice, setMonthlyPrice] = useState(plan.monthlyPrice);
    const [yearlyPrice, setYearlyPrice] = useState(plan.yearlyPrice);
    const [maxUsers, setMaxUsers] = useState(plan.maxUsers);
    const [isPopular, setIsPopular] = useState(plan.isPopular);
    const [selectedFeatures, setSelectedFeatures] = useState<Set<string>>(
        new Set(plan.featureNames)
    );
    const [processing, setProcessing] = useState(false);

    const allFeaturesByName = featureCategories.flatMap((c) => c.features);

    // Get inherited features from parent plan
    const parentPlan = plans.find((p) => p.id === plan.parentPlanId);
    const inheritedFrom = parentPlan?.name;

    // Get all feature names from the parent plan (recursively including its parents)
    const computedInheritedFeatures: string[] = (() => {
        if (!parentPlan) return [];
        // The parent plan's featureNames are its direct features
        // But we also need to recursively include the parent's parent features
        const getParentFeatures = (p: Plan): string[] => {
            const parent = plans.find((pp) => pp.id === p.parentPlanId);
            const parentFeatures = parent ? getParentFeatures(parent) : [];
            return [...new Set([...parentFeatures, ...p.featureNames])];
        };
        return getParentFeatures(parentPlan);
    })();

    const toggleFeature = (featureName: string) => {
        setSelectedFeatures((prev) => {
            const next = new Set(prev);
            if (next.has(featureName)) {
                next.delete(featureName);
            } else {
                next.add(featureName);
            }
            return next;
        });
    };

    const allSelected = new Set([...selectedFeatures, ...computedInheritedFeatures]);

    const handleSubmit = () => {
        const featureIds = allFeaturesByName
            .filter((f) => selectedFeatures.has(f.name))
            .map((f) => f.id);

        setProcessing(true);
        router.put(`/admin/subscriptions/plans/${plan.id}`, {
            name,
            monthly_price: monthlyPrice,
            yearly_price: yearlyPrice,
            max_users: maxUsers,
            parent_plan_id: plan.parentPlanId,
            is_popular: isPopular,
            feature_ids: featureIds,
        }, {
            onFinish: () => setProcessing(false),
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-xl bg-white p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-gray-900">Edit Plan: {plan.name}</h2>
                    <button onClick={onClose} className="rounded-lg p-2 hover:bg-gray-100">
                        <X className="h-5 w-5" />
                    </button>
                </div>

                {/* Inheritance info */}
                {inheritedFrom && (
                    <div className="mb-6 flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <Info className="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600" />
                        <div>
                            <p className="text-sm font-medium text-blue-900">Hierarchical Plan</p>
                            <p className="mt-1 text-sm text-blue-800">
                                This plan inherits all features from <strong>{inheritedFrom}</strong>. Inherited features are locked and cannot be removed.
                            </p>
                        </div>
                    </div>
                )}

                <div className="space-y-6">
                    {/* Plan Name */}
                    <div>
                        <label className="mb-2 block text-sm font-medium text-gray-900">Plan Name</label>
                        <input
                            type="text"
                            className="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </div>

                    {/* Pricing */}
                    <div>
                        <h3 className="mb-3 text-sm font-semibold tracking-wide text-gray-900 uppercase">Pricing</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-900">Monthly Price (€)</label>
                                <input
                                    type="number"
                                    className="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
                                    placeholder="99"
                                    value={monthlyPrice}
                                    onChange={(e) => setMonthlyPrice(Number(e.target.value))}
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-gray-900">Yearly Price (€)</label>
                                <input
                                    type="number"
                                    className="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
                                    placeholder="950"
                                    value={yearlyPrice}
                                    onChange={(e) => setYearlyPrice(Number(e.target.value))}
                                />
                                <p className="mt-1 text-xs text-gray-500">Yearly billing typically offers a discount (~20%)</p>
                            </div>
                        </div>
                    </div>

                    {/* Limits */}
                    <div>
                        <h3 className="mb-3 text-sm font-semibold tracking-wide text-gray-900 uppercase">Limits</h3>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-gray-900">Maximum users</label>
                            <input
                                type="number"
                                className="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-sm text-gray-900 placeholder:text-gray-400 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none"
                                min={1}
                                placeholder="5"
                                value={maxUsers}
                                onChange={(e) => setMaxUsers(Number(e.target.value))}
                            />
                            <p className="mt-1 text-xs text-gray-500">Maximum number of staff accounts allowed for this plan.</p>
                        </div>
                    </div>

                    {/* Features */}
                    <FeatureCheckboxList
                        featureCategories={featureCategories}
                        selectedFeatures={allSelected}
                        onToggle={toggleFeature}
                        inheritedFeatures={computedInheritedFeatures}
                        inheritedFrom={inheritedFrom}
                    />

                    {/* Most Popular */}
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            className="checkbox-custom"
                            checked={isPopular}
                            onChange={(e) => setIsPopular(e.target.checked)}
                        />
                        <span className="text-sm font-medium text-gray-900">Mark as "Most Popular"</span>
                    </label>
                </div>
                <div className="mt-6 flex gap-3 border-t border-gray-200 pt-6">
                    <button
                        onClick={onClose}
                        className="flex-1 rounded-lg border border-gray-300 py-2.5 font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleSubmit}
                        disabled={processing}
                        className="flex-1 rounded-lg bg-purple-600 py-2.5 font-medium text-white hover:bg-purple-700 disabled:opacity-50"
                    >
                        {processing ? 'Updating...' : 'Update Plan'}
                    </button>
                </div>
            </div>
        </div>
    );
}
