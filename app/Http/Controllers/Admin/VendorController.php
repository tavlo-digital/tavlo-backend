<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\FiscalizationException;
use App\Http\Controllers\Controller;
use App\Models\FiscalDevice;
use App\Models\Invoice;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use App\Services\Fiscal\FiscalizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

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
                $subExtra = 'Expired · '.ucfirst($v->status);
            }

            $paymentVariants = [
                'paid' => 'green',
                'failed' => 'red',
                'overdue' => 'orange',
                'trial' => 'blue',
            ];

            $paymentTooltip = null;
            if ($v->payment_status === 'failed') {
                $paymentTooltip = 'Last attempt: '.($v->payment_last_success ? $v->payment_last_success->diffForHumans() : 'Never').
                    "\nPSP: Stripe\nError: Card declined - insufficient funds";
            } elseif ($v->payment_status === 'overdue') {
                $paymentTooltip = ($v->payment_failures_24h ?: 4).' days overdue';
            }

            $riskTooltip = '';
            if ($v->risk_level === 'red') {
                $riskTooltip = $v->payment_failures_24h.' failed payments in last 24h';
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
                'revenue' => '€'.number_format($v->revenue_total, 0, ',', ','),
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
            'postalCode' => $vendor->postal_code,
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
            $props['cashRegister'] = $this->getCashRegister($vendor);
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

        try {
            // Create the managed fiskaly Unit outside the DB approval
            // transaction. Its API secret is returned once; rolling it back
            // locally after a later SIGN failure would make it unrecoverable.
            $this->prepareRegistration($vendor, $change);

            // The approval and the registration stand or fall together. A cash
            // register the tax office will not accept means the details behind
            // it are wrong, and approving them would leave the restaurant with
            // data that is approved but cannot be used.
            $device = DB::transaction(function () use ($vendor, $change) {
                $this->applyApprovedChange($vendor, $change);

                return $this->registerOnApproval($vendor, $change);
            });
        } catch (Throwable $exception) {
            return $this->handleRegistrationFailure($vendor, $change, $exception);
        }

        return redirect()->back()->with(
            'success',
            'Changes approved successfully.'
                .($device ? ' Cash register registered ('.$device->serial_number.').' : ''),
        );
    }

    /**
     * POST admin/vendor/{vendor}/fiscal/retry
     *
     * Registers a vendor whose legal details are already approved but whose
     * cash register did not get through.
     */
    public function retryCashRegister(int|string $vendor)
    {
        $vendor = $this->resolveVendorRouteValue($vendor);

        try {
            $device = app(FiscalizationService::class)->provisionOnApproval($vendor);
        } catch (Throwable $exception) {
            Log::error('Cash register retry failed.', [
                'vendor_id' => $vendor->id,
                'exception' => $exception,
            ]);

            return redirect()->back()->with(
                'warning',
                FiscalizationService::friendlyError($exception),
            );
        }

        return redirect()->back()->with(
            'success',
            $device
                ? 'Cash register registered ('.$device->serial_number.').'
                : 'Nothing to register for this vendor.',
        );
    }

    private function applyApprovedChange(Vendor $vendor, VendorRequestChange $change): void
    {
        $fieldMap = [
            'restaurant_name', 'legal_entity_name', 'business_registration_number',
            'vat_number', 'country', 'city', 'postal_code', 'address',
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
            'admin_notes' => null,
            'reviewed_at' => now(),
        ]);
    }

    private function prepareRegistration(Vendor $vendor, VendorRequestChange $change): void
    {
        $fiscalization = app(FiscalizationService::class);

        if (! $fiscalization->required($vendor)) {
            return;
        }

        $credentials = $change->fiscalCredentials();
        $existingDevice = FiscalDevice::where('vendor_id', $vendor->id)->first();
        $storedCredentials = $existingDevice?->credentials ?? [];

        if ($fiscalization->needsMerchantCredentials($vendor)
            && blank($credentials['fon_user_pin'] ?? $storedCredentials['fon_user_pin'] ?? null)) {
            return;
        }

        $fiscalization->prepareForProvisioning($vendor, $credentials, [
            'restaurant_name' => $change->restaurant_name,
            'legal_entity_name' => $change->legal_entity_name,
            'vat_number' => $change->vat_number,
            'country' => $change->country,
            'city' => $change->city,
            'postal_code' => $change->postal_code,
            'address' => $change->address,
        ]);
    }

    private function registerOnApproval(Vendor $vendor, VendorRequestChange $change): ?FiscalDevice
    {
        $fiscalization = app(FiscalizationService::class);

        // Credentials travelled with the approval request. Hand them to the
        // device now, then drop the PIN from the approval history — it has
        // served its purpose and does not belong in an audit trail.
        if ($change->hasFiscalDetails() && $fiscalization->required($vendor)) {
            $fiscalization->submit($vendor, $change->fiscalCredentials());
            $change->forceFill(['fon_user_pin' => null])->save();
        }

        return $fiscalization->provisionOnApproval($vendor->fresh());
    }

    /**
     * Registration failed, so nothing was approved. Either the restaurant has
     * to correct what it sent, or the problem is ours and the request simply
     * waits for another attempt.
     */
    private function handleRegistrationFailure(
        Vendor $vendor,
        VendorRequestChange $change,
        Throwable $exception,
    ) {
        Log::error('Cash register registration failed during approval.', [
            'vendor_id' => $vendor->id,
            'change_id' => $change->id,
            'context' => $exception instanceof FiscalizationException ? $exception->context : null,
            'exception' => $exception,
        ]);

        $reason = FiscalizationService::friendlyError($exception);

        if (! ($exception instanceof FiscalizationException && $exception->isVendorDataRejection())) {
            // Ours to fix. The submission stays pending so the admin can simply
            // approve again once the problem is resolved.
            return redirect()->back()->with(
                'warning',
                'Nothing was approved. '.$reason.' The request is still pending — try approving again.',
            );
        }

        $change->forceFill([
            'status' => 'rejected',
            'checked_by' => auth()->id(),
            'admin_notes' => $reason,
            'reviewed_at' => now(),
        ])->save();

        return redirect()->back()->with(
            'warning',
            'Changes were not approved — '.$reason.' The restaurant has been asked to submit corrected details.',
        );
    }

    /**
     * Registration state for the admin vendor page.
     *
     * @return array<string, mixed>|null
     */
    private function getCashRegister(Vendor $vendor): ?array
    {
        $fiscalization = app(FiscalizationService::class);

        if (! $fiscalization->required($vendor)) {
            return null;
        }

        $device = FiscalDevice::where('vendor_id', $vendor->id)->first();

        // Registration details ride on the pending change, and the device row
        // only appears once registration is attempted. Reading the device alone
        // told the admin "not submitted" while the vendor's details sat in the
        // diff directly below.
        $pending = VendorRequestChange::where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        $awaiting = $pending !== null && (
            $pending->hasFiscalDetails() || ! $fiscalization->needsMerchantCredentials($vendor)
        );

        // Approval failures are rolled back, so an existing failed device can
        // still carry an older error. The rejected change is the durable record
        // of the latest approval attempt and should win when it is newer.
        $rejected = VendorRequestChange::where('vendor_id', $vendor->id)
            ->where('status', 'rejected')
            ->whereNotNull('admin_notes')
            ->latest('reviewed_at')
            ->first();

        $deviceErrorAt = $device?->last_attempted_at
            ?? ($device?->last_error ? $device->updated_at : null);
        $rejectedErrorAt = $rejected?->reviewed_at ?? $rejected?->updated_at;
        $lastError = $device?->last_error;

        if ($rejected && $rejectedErrorAt
            && (! $deviceErrorAt || $rejectedErrorAt->greaterThanOrEqualTo($deviceErrorAt))) {
            $lastError = $rejected->admin_notes;
        }

        return [
            'country' => $fiscalization->countryCode($vendor),
            'environment' => (string) config('services.fiskaly.environment', 'sandbox'),
            // A corrected resubmission supersedes a failed prior attempt.
            'state' => $awaiting
                ? FiscalDevice::STATE_AWAITING_APPROVAL
                : ($device?->state ?? 'not_submitted'),
            'serialNumber' => $device?->serial_number,
            'submittedAt' => ($device?->submitted_at ?? $pending?->created_at)?->diffForHumans(),
            'lastAttemptedAt' => $device?->last_attempted_at?->diffForHumans(),
            'registeredAt' => $device?->initialized_at?->diffForHumans(),
            'lastError' => $awaiting ? null : $lastError,
            // Nothing to retry while an approval is what it is waiting for.
            'canRetry' => $device !== null && $device->needsRegistration() && ! $awaiting,
        ];
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
            $issues[] = 'Last successful payment: '.$vendor->payment_last_success->diffForHumans();
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
            'postal_code' => ['label' => 'Postal Code', 'impact' => 'Used for the fiskaly managed Unit address'],
            'country' => ['label' => 'Country', 'impact' => 'Country update on all documents'],
            'fon_participant_id' => ['label' => 'FinanzOnline Teilnehmer-ID', 'impact' => 'Used to register the cash register with the Austrian tax office on approval'],
            'fon_user_id' => ['label' => 'FinanzOnline Benutzer-ID', 'impact' => 'Web-service user the cash register registers under'],
            'fon_user_pin' => ['label' => 'FinanzOnline PIN', 'impact' => 'Required to register the cash register; never shown in full'],
        ];

        return $vendor->requestChanges()
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(function ($change) use ($vendor, $fieldMap) {
                $fields = [];
                $device = FiscalDevice::where('vendor_id', $vendor->id)->first();

                foreach ($fieldMap as $column => $meta) {
                    // The PIN is a live tax-office secret. An admin needs to see
                    // that one was supplied, never what it is.
                    if ($column === 'fon_user_pin') {
                        if (($change->getAttributes()['fon_user_pin'] ?? null) !== null) {
                            $fields[] = [
                                'field' => $meta['label'],
                                'current' => $device?->credentials['fon_user_pin'] ?? null ? '••••••••' : 'Not set',
                                'newValue' => '•••••••• (supplied)',
                                'impact' => $meta['impact'],
                            ];
                        }

                        continue;
                    }

                    if ($change->$column !== null) {
                        $fields[] = [
                            'field' => $meta['label'],
                            'current' => match ($column) {
                                'company_type' => $vendor->vendorSetting?->company_type ?? 'Not set',
                                'fon_participant_id', 'fon_user_id' => $device?->credentials[$column] ?? 'Not set',
                                default => $vendor->$column ?? 'Not set',
                            },
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
                            'amount' => '€'.number_format($monthInvoices->sum('amount'), 2),
                            'invoices' => $monthInvoices->map(fn ($inv) => [
                                'id' => $inv->invoice_number,
                                'amount' => '€'.number_format($inv->amount, 2),
                                'status' => $inv->status,
                                'date' => $inv->status === 'paid'
                                    ? ($inv->paid_at?->format('M j, Y') ?? 'Paid')
                                    : 'Due: '.$inv->due_date->format('M j, Y'),
                            ])->values()->toArray(),
                        ];
                    })->values()->toArray();

                return [
                    'year' => (int) $year,
                    'paidCount' => $yearInvoices->where('status', 'paid')->count(),
                    'unpaidCount' => $yearInvoices->where('status', '!=', 'paid')->count(),
                    'total' => '€'.number_format($yearInvoices->sum('amount'), 2),
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
                    'upgrade' => 'Upgraded to '.($e->newPlan?->name ?? 'Unknown'),
                    'downgrade' => 'Downgraded to '.($e->newPlan?->name ?? 'Unknown'),
                    'created' => 'Subscription Started',
                    'cancelled' => 'Subscription Cancelled',
                    'renewed' => 'Subscription Renewed',
                    default => ucfirst(str_replace('_', ' ', $e->event_type)),
                };

                $detail = match ($e->event_type) {
                    'upgrade', 'downgrade' => 'From '.($e->previousPlan?->name ?? 'None').' plan',
                    'created' => ($e->newPlan?->name ?? 'Unknown').' plan',
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
                'time' => $a->created_at->diffForHumans().' • '.$a->actor,
            ])
            ->toArray();
    }
}
