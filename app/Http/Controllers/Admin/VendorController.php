<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::with(['subscriptions.plan'])->get();

        $stats = [
            'total' => $vendors->count(),
            'active' => $vendors->where('status', 'active')->count(),
            'inactive' => $vendors->where('status', 'inactive')->count(),
            'live' => $vendors->where('live_status', 'live')->count(),
            'notLive' => $vendors->where('live_status', '!=', 'live')->count(),
        ];

        $vendorsData = $vendors->map(function (Vendor $v) {
            $subscription = $v->subscriptions->sortByDesc('created_at')->first();
            $plan = $subscription?->plan;

            $subVariant = 'green';
            $subExtra = null;
            if ($subscription && $subscription->status === 'expired') {
                $subVariant = 'red';
                $subExtra = 'Expired · ' . ucfirst($v->status);
            }

            $paymentVariants = [
                'paid' => 'green',
                'failed' => 'red',
                'overdue' => 'orange',
                'trial' => 'blue',
            ];

            $paymentTooltip = null;
            if ($v->payment_status === 'failed') {
                $paymentTooltip = "Last attempt: " . ($v->payment_last_success ? $v->payment_last_success->diffForHumans() : 'Never') .
                    "\nPSP: Stripe\nError: Card declined - insufficient funds";
            } elseif ($v->payment_status === 'overdue') {
                $paymentTooltip = ($v->payment_failures_24h ?: 4) . ' days overdue';
            }

            $riskTooltip = '';
            if ($v->risk_level === 'red') {
                $riskTooltip = $v->payment_failures_24h . ' failed payments in last 24h';
            } elseif ($v->risk_level === 'orange') {
                $riskTooltip = 'Subscription expired but vendor still active';
            } elseif ($v->risk_level === 'yellow') {
                $riskTooltip = 'KYC verification incomplete';
            }

            return [
                'id' => $v->id,
                'publicId' => $v->vendor_public_id,
                'name' => $v->name,
                'risk' => $v->risk_level,
                'riskTooltip' => $riskTooltip,
                'status' => ucfirst($v->status),
                'subscriptionPlan' => $plan?->name ?? ($subscription ? ucfirst($subscription->status) : 'None'),
                'subscriptionVariant' => $subVariant,
                'subscriptionExtra' => $subExtra,
                'paymentStatus' => ucfirst($v->payment_status),
                'paymentVariant' => $paymentVariants[$v->payment_status] ?? 'green',
                'paymentTooltip' => $paymentTooltip,
                'ordersCount' => $v->orders_count,
                'revenue' => '€' . number_format($v->revenue_total, 0, ',', ','),
            ];
        });

        return Inertia::render('admin/vendors/index', [
            'stats' => $stats,
            'vendorsData' => $vendorsData->values(),
        ]);
    }

    public function show(int $vendor, string $tab = 'overview')
    {
        $vendor = Vendor::findOrFail($vendor);
        $subscription = $vendor->subscriptions()->with('plan')->latest()->first();

        $vendorData = [
            'name' => $vendor->name,
            'id' => $vendor->vendor_public_id,
            'slug' => $vendor->slug,
            'status' => ucfirst($vendor->status),
            'subscription' => $subscription?->plan?->name ?? 'None',
            'paymentStatus' => $vendor->payment_status,
            'businessName' => $vendor->restaurant_name,
            'email' => $vendor->email,
            'phone' => $vendor->phone,
            'website' => $vendor->website,
            'vat' => $vendor->vat_number,
            'legalEntity' => $vendor->legal_entity_name,
            'address' => $vendor->address,
            'city' => $vendor->city,
            'country' => $vendor->country,
            'usersUsed' => $vendor->users_used,
            'usersAllowed' => $subscription?->plan?->max_users ?? 0,
            'issues' => $this->getIssues($vendor),
            'recentActivity' => $vendor->activities()
                ->latest()
                ->take(3)
                ->get()
                ->map(fn ($a) => [
                    'event' => $a->title,
                    'time' => $a->created_at->diffForHumans(),
                ])
                ->toArray(),
        ];

        $props = [
            'vendor' => $vendor->id,
            'tab' => $tab,
            'vendorData' => $vendorData,
        ];

        if ($tab === 'pending-changes') {
            $props['pendingChanges'] = $this->getPendingChanges($vendor);
        }

        if ($tab === 'payments') {
            $props['paymentData'] = $this->getPaymentData($vendor);
            $props['paymentFailures24h'] = $vendor->payment_failures_24h;
        }

        if ($tab === 'subscription') {
            $props['subscriptionDetails'] = $this->getSubscriptionDetails($vendor, $subscription);
        }

        if ($tab === 'activity') {
            $props['activities'] = $this->getActivities($vendor);
        }

        return Inertia::render('admin/vendor/[vendor]/show', $props);
    }

    public function approveChange(int|string $vendor, int $change)
    {
        $vendor = $this->resolveVendorRouteValue($vendor);
        $change = VendorRequestChange::where('vendor_id', $vendor->id)
            ->where('id', $change)
            ->where('status', 'pending')
            ->firstOrFail();

        $fieldMap = [
            'restaurant_name', 'legal_entity_name', 'business_registration_number',
            'vat_number', 'country', 'city', 'address',
        ];

        foreach ($fieldMap as $column) {
            if ($change->$column !== null) {
                $vendor->$column = $change->$column;
            }
        }

        $vendor->save();

        if ($change->company_type !== null) {
            $vendor->vendorSetting()->updateOrCreate(
                ['vendor_id' => $vendor->id],
                ['company_type' => $change->company_type]
            );
        }

        $change->update([
            'status' => 'approved',
            'checked_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Changes approved successfully.');
    }

    public function declineChange(Request $request, int|string $vendor, int $change)
    {
        $vendor = $this->resolveVendorRouteValue($vendor);
        $change = VendorRequestChange::where('vendor_id', $vendor->id)
            ->where('id', $change)
            ->where('status', 'pending')
            ->firstOrFail();

        $change->update([
            'status' => 'rejected',
            'checked_by' => auth()->id(),
            'admin_notes' => $request->input('admin_notes', 'Declined'),
            'reviewed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Changes declined.');
    }

    private function resolveVendorRouteValue(int|string $vendor): Vendor
    {
        return Vendor::query()
            ->where(is_numeric($vendor) ? 'id' : 'slug', $vendor)
            ->firstOrFail();
    }

    private function getIssues(Vendor $vendor): array
    {
        $issues = [];

        if ($vendor->payment_failures_24h > 0) {
            $issues[] = "Payment failures detected ({$vendor->payment_failures_24h} in last 24h)";
        }

        if ($vendor->payment_status === 'failed' && $vendor->payment_last_success) {
            $issues[] = 'Last successful payment: ' . $vendor->payment_last_success->diffForHumans();
        }

        $subscription = $vendor->subscriptions()->latest()->first();
        if ($subscription && $subscription->status === 'expired') {
            $issues[] = 'Subscription expired';
        }

        return $issues;
    }

    private function getPendingChanges(Vendor $vendor): array
    {
        $fieldMap = [
            'restaurant_name' => ['label' => 'Restaurant Name', 'impact' => 'Will update restaurant name on all invoices, receipts, and customer-facing pages'],
            'business_registration_number' => ['label' => 'Business Registration Number', 'impact' => 'Must match official business registry - will affect tax compliance'],
            'vat_number' => ['label' => 'VAT Number', 'impact' => 'Critical for Austrian VAT compliance - invoices may be rejected if incorrect'],
            'company_type' => ['label' => 'Company Type', 'impact' => 'Company type shown on legal and tax records will be updated'],
            'legal_entity_name' => ['label' => 'Legal Entity Name', 'impact' => 'Legal entity on all invoices will be updated'],
            'address' => ['label' => 'Legal Address', 'impact' => 'Must match official business registry'],
            'city' => ['label' => 'City', 'impact' => 'Address update on all documents'],
            'country' => ['label' => 'Country', 'impact' => 'Country update on all documents'],
        ];

        return $vendor->requestChanges()
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function ($change) use ($vendor, $fieldMap) {
                $fields = [];
                foreach ($fieldMap as $column => $meta) {
                    if ($change->$column !== null) {
                        $fields[] = [
                            'field' => $meta['label'],
                            'current' => $column === 'company_type'
                                ? ($vendor->vendorSetting?->company_type ?? 'Not set')
                                : ($vendor->$column ?? 'Not set'),
                            'newValue' => $change->$column,
                            'impact' => $meta['impact'],
                        ];
                    }
                }

                return [
                    'id' => $change->id,
                    'changes' => $fields,
                    'vendorNotes' => $change->vendor_notes,
                    'submittedAt' => $change->created_at->diffForHumans(),
                    'submittedBy' => 'Vendor Owner',
                ];
            })
            ->values()
            ->toArray();
    }

    private function getPaymentData(Vendor $vendor): array
    {
        $invoices = Invoice::whereHas('subscription', function ($q) use ($vendor) {
            $q->where('vendor_id', $vendor->id);
        })->orderByDesc('billing_period_start')->get();

        return $invoices->groupBy(fn ($inv) => $inv->billing_period_start->year)
            ->map(function ($yearInvoices, $year) {
                $months = $yearInvoices->groupBy(fn ($inv) => $inv->billing_period_start->format('F Y'))
                    ->map(function ($monthInvoices, $monthName) {
                        return [
                            'name' => $monthName,
                            'paid' => $monthInvoices->where('status', 'paid')->count(),
                            'total' => $monthInvoices->count(),
                            'amount' => '€' . number_format($monthInvoices->sum('amount'), 2),
                            'invoices' => $monthInvoices->map(fn ($inv) => [
                                'id' => $inv->invoice_number,
                                'amount' => '€' . number_format($inv->amount, 2),
                                'status' => $inv->status,
                                'date' => $inv->status === 'paid'
                                    ? ($inv->paid_at?->format('M j, Y') ?? 'Paid')
                                    : 'Due: ' . $inv->due_date->format('M j, Y'),
                            ])->values()->toArray(),
                        ];
                    })->values()->toArray();

                return [
                    'year' => (int) $year,
                    'paidCount' => $yearInvoices->where('status', 'paid')->count(),
                    'unpaidCount' => $yearInvoices->where('status', '!=', 'paid')->count(),
                    'total' => '€' . number_format($yearInvoices->sum('amount'), 2),
                    'months' => $months,
                ];
            })->values()->toArray();
    }

    private function getSubscriptionDetails(Vendor $vendor, $subscription): array
    {
        $events = $subscription
            ? $subscription->events()->with(['previousPlan', 'newPlan'])->latest()->get()
            : collect();

        return [
            'plan' => $subscription?->plan?->name ?? 'None',
            'status' => $subscription ? ucfirst($subscription->status) : 'None',
            'billingCycle' => $subscription ? ucfirst($subscription->billing_cycle) : 'N/A',
            'nextBillingDate' => $subscription?->next_billing_date?->format('M j, Y') ?? 'N/A',
            'history' => $events->map(function ($e) {
                $title = match ($e->event_type) {
                    'upgrade' => 'Upgraded to ' . ($e->newPlan?->name ?? 'Unknown'),
                    'downgrade' => 'Downgraded to ' . ($e->newPlan?->name ?? 'Unknown'),
                    'created' => 'Subscription Started',
                    'cancelled' => 'Subscription Cancelled',
                    'renewed' => 'Subscription Renewed',
                    default => ucfirst(str_replace('_', ' ', $e->event_type)),
                };

                $detail = match ($e->event_type) {
                    'upgrade', 'downgrade' => 'From ' . ($e->previousPlan?->name ?? 'None') . ' plan',
                    'created' => ($e->newPlan?->name ?? 'Unknown') . ' plan',
                    default => '',
                };

                return [
                    'title' => $title,
                    'detail' => $detail,
                    'time' => $e->created_at->diffForHumans(),
                ];
            })->toArray(),
        ];
    }

    private function getActivities(Vendor $vendor): array
    {
        return $vendor->activities()
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($a) => [
                'color' => $a->color,
                'title' => $a->title,
                'detail' => $a->description,
                'time' => $a->created_at->diffForHumans() . ' • ' . $a->actor,
            ])
            ->toArray();
    }
}
