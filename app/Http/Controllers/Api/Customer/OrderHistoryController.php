<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Vendor;
use App\Services\LocaleService;
use App\Services\MediaService;
use App\Services\MenuCustomizationService;
use App\Services\TaxCalculationService;
use App\Services\VendorDateTimeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderHistoryController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly VendorDateTimeService $dateTimes,
        private readonly LocaleService $locales,
        private readonly MenuCustomizationService $customizations,
    ) {}

    /**
     * Get the customer's full account order history grouped by restaurant.
     */
    public function history(Request $request): JsonResponse
    {
        $customer = $request->user();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $page = max($request->integer('page', 1), 1);

        $base = Order::query()
            ->where(fn (Builder $query) => $this->scopeCustomerOrders($query, $customer->id))
            ->where('status', '!=', 'draft');

        $orders = (clone $base)
            ->with([
                'vendor.vendorSetting:id,vendor_id,logo_url,service_fee_rate,date_format,time_format,supported_languages',
                'tableScanSession:id,customer_id',
                'paidBy:id,first_name,last_name',
            ])
            ->orderByDesc('created_at')
            ->get();

        $history = $orders
            ->groupBy('vendor_id')
            ->sortByDesc(fn (Collection $vendorOrders) => $vendorOrders->max('created_at'))
            ->map(function (Collection $vendorOrders) use ($page, $perPage, $request) {
                /** @var Order $first */
                $first = $vendorOrders->first();
                $vendor = $first->vendor;
                $settings = $vendor?->vendorSetting;
                $pagedOrders = $vendorOrders->forPage($page, $perPage)->values();
                $total = $vendorOrders->count();

                return [
                    'restaurant_public_id' => $vendor?->vendor_public_id,
                    'restaurant_name' => $vendor?->restaurant_name,
                    'restaurant_logo_url' => $this->media->url($settings?->logo_url),
                    'currency' => $first->currency ?? $vendor?->currency,
                    'orders_count' => $total,
                    'total_spent' => round((float) $vendorOrders->sum(fn (Order $order) => (float) $order->amount), 2),
                    'last_ordered_at' => $this->dateTimes->formatDateTime(
                        $vendorOrders->max('created_at'),
                        $vendor,
                    ),
                    'orders' => $pagedOrders->map(fn (Order $order) => $this->formatHistoryOrder($order, $request))->all(),
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => max((int) ceil($total / $perPage), 1),
                        'has_more' => $page < max((int) ceil($total / $perPage), 1),
                    ],
                ];
            })
            ->values();

        return response()->json([
            'history' => $history,
            'summary' => [
                'restaurants_count' => $history->count(),
                'orders_count' => (clone $base)->count(),
                'total_spent' => round((float) (clone $base)->sum('amount'), 2),
            ],
        ]);
    }

    /**
     * Get restaurants the customer has ordered from (grouped).
     */
    public function restaurants(Request $request): JsonResponse
    {
        $customer = $request->user();

        $byCustomer = fn ($q) => $q->whereHas(
            'tableScanSession',
            fn ($s) => $s->where('customer_id', $customer->id)
        );

        $restaurants = Vendor::whereHas('orders', $byCustomer)
            ->with('vendorSetting:id,vendor_id,date_format,time_format')
            ->withCount(['orders' => $byCustomer])
            ->withSum(['orders' => $byCustomer], 'amount')
            ->withMax(['orders' => $byCustomer], 'created_at')
            ->get([
                'id', 'vendor_public_id', 'restaurant_name', 'slug',
            ])
            ->map(fn (Vendor $vendor) => [
                'id' => $vendor->id,
                'vendor_public_id' => $vendor->vendor_public_id,
                'restaurant_name' => $vendor->restaurant_name,
                'slug' => $vendor->slug,
                'orders_count' => $vendor->orders_count,
                'orders_sum_amount' => $vendor->orders_sum_amount,
                'orders_max_created_at' => $this->dateTimes->formatDateTime(
                    $vendor->orders_max_created_at,
                    $vendor,
                ),
            ]);

        return response()->json($restaurants);
    }

    /**
     * Get orders for a specific restaurant.
     */
    public function vendorOrders(Request $request, string $vendorPublicId): JsonResponse
    {
        $customer = $request->user();
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)
            ->with('vendorSetting:id,vendor_id,date_format,time_format')
            ->firstOrFail();

        $base = fn () => Order::whereHas(
            'tableScanSession',
            fn ($s) => $s->where('customer_id', $customer->id)
        )->where('vendor_id', $vendor->id);

        $orders = $base()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20), [
                'id', 'order_public_id', 'order_type', 'table_number',
                'amount', 'currency', 'status', 'paid_by', 'created_at',
            ]);
        $orders->getCollection()->load('paidBy:id,first_name,last_name');
        $orders->getCollection()->transform(function (Order $order) use ($vendor) {
            $payload = $order->toArray();
            $payload['paid_by'] = $this->paidByPayload($order);
            $payload['created_at'] = $this->dateTimes->formatDateTime($order->created_at, $vendor);

            return $payload;
        });

        $summary = [
            'total_orders' => $base()->count(),
            'total_spent' => $base()->sum('amount'),
        ];

        return response()->json([
            'restaurant' => [
                'id' => $vendor->vendor_public_id,
                'name' => $vendor->restaurant_name,
            ],
            'summary' => $summary,
            'orders' => $orders,
        ]);
    }

    /**
     * Show a single order detail.
     */
    public function show(Request $request, string $orderPublicId): JsonResponse
    {
        $customer = $request->user();

        $order = Order::where('order_public_id', $orderPublicId)
            ->where(fn (Builder $query) => $this->scopeCustomerAccessibleOrders($query, $customer->id))
            ->with([
                'vendor.vendorSetting:id,vendor_id,logo_url,service_fee_rate,date_format,time_format,supported_languages',
                'tableScanSession:id,customer_id',
                'paidBy:id,first_name,last_name',
            ])
            ->firstOrFail();

        return response()->json($this->formatOrderDetail($order, $request));
    }

    /**
     * Track one authenticated participant's order.
     */
    public function tracking(Request $request, string $orderPublicId): JsonResponse
    {
        $customer = $request->user();

        $order = Order::where('order_public_id', $orderPublicId)
            ->where(fn (Builder $query) => $this->scopeCustomerAccessibleOrders($query, $customer->id))
            ->with([
                'vendor.vendorSetting:id,vendor_id,estimated_prep_time,service_fee_rate,date_format,time_format,supported_languages',
                'tableScanSession:id,customer_id',
                'paidBy:id,first_name,last_name',
            ])
            ->firstOrFail();

        return response()->json($this->formatTrackingOrder($order, $request));
    }

    public function receipt(Request $request, string $orderPublicId): JsonResponse
    {
        $customer = $request->user();

        $order = Order::where('order_public_id', $orderPublicId)
            ->where(fn (Builder $query) => $this->scopeCustomerReceiptOrders($query, $customer->id))
            ->with([
                'vendor.vendorSetting',
                'tableScanSession.restaurantTable:id,number,name',
                'paidBy:id,first_name,last_name',
            ])
            ->firstOrFail();

        if (! $order->payment_received) {
            return response()->json(['message' => 'Receipt is only available for paid orders.'], 422);
        }

        $vendor = $order->vendor;
        $settings = $vendor?->vendorSetting;
        $vendorCountry = $vendor?->country ?? 'AT';
        $contentLocale = $vendor ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor) : 'en';
        $items = $this->linkedCartItems($order);

        $taxGroups = TaxCalculationService::computeTaxGroups($items, $vendorCountry, true);
        $totals = TaxCalculationService::computeTotals($taxGroups, 0);
        $totals = $this->applyStoredServiceFee($totals, $taxGroups, $order);

        $receiptTip = round((float) ($order->tip_amount ?? 0), 2);
        $totals['total_tips'] = $receiptTip;
        $totals['grand_total'] = round($totals['grand_total'] + $receiptTip, 2);

        $invoiceNumber = $this->resolveInvoiceNumber($order, $settings);

        $payment = OrderPayment::where('order_id', $order->id)
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->first();

        $table = $order->tableScanSession?->restaurantTable;
        $tableName = $table ? ($table->name ?? 'Table '.$table->number) : null;

        $countryCode = TaxCalculationService::countryCode($vendorCountry);
        $receiptLocale = 'en-'.$countryCode;

        $receiptItems = $items
            ->map(fn (CartItem $item) => $this->formatReceiptItem($item, $order, $vendorCountry, $vendor, $contentLocale))
            ->values()
            ->all();

        $taxGroupsFormatted = array_map(fn (array $group) => array_merge($group, [
            'tax_category' => strtoupper($group['tax_category']),
            'label' => $this->locales->translatedTaxCategoryName($group['tax_category'], $vendorCountry, $contentLocale),
        ]), $taxGroups);

        return response()->json([
            'data' => [
                'restaurant' => [
                    'name' => $vendor?->restaurant_name,
                    'logo_url' => $this->media->url($settings?->logo_url),
                    'address' => $this->formatFullAddress($vendor),
                    'vat_id' => $vendor?->vat_number,
                    'phone' => $vendor?->phone,
                    'email' => $vendor?->email,
                    'company_register_number' => $vendor?->business_registration_number,
                ],
                'receipt' => [
                    'invoice_number' => $invoiceNumber,
                    'date' => $this->dateTimes->formatDate($order->created_at, $vendor),
                    'time' => $this->dateTimes->formatTime($order->created_at, $vendor),
                    'table' => $tableName,
                    'order_id' => $order->order_public_id,
                    'currency' => $order->currency ?? $vendor?->currency ?? 'EUR',
                    'locale' => $receiptLocale,
                ],
                'order' => [
                    'paid_by' => $this->paidByPayload($order),
                    'items' => $receiptItems,
                ],
                'tax_groups' => $taxGroupsFormatted,
                'totals' => $totals,
                'payment' => [
                    'method' => $order->payment_method,
                    'status' => $order->payment_received ? 'CONFIRMED' : 'PENDING',
                    'transaction_id' => $order->transaction_id ?? $payment?->stripe_payment_intent_id,
                    'paid_at' => $this->dateTimes->formatDateTime(
                        $order->payment_confirmed_at ?? $payment?->paid_at,
                        $vendor,
                    ),
                ],
                'legal' => $this->legalBlock($countryCode, $vendor),
            ],
            'meta' => [
                'generated_at' => $this->dateTimes->formatDateTime(now(), $vendor),
                'template' => 'tavlo-receipt-template',
                'version' => '1.0',
            ],
        ]);
    }

    /**
     * GET /api/customer/receipts
     */
    public function receipts(Request $request): JsonResponse
    {
        $customer = $request->user();

        $payments = OrderPayment::with([
            'vendor.vendorSetting:id,vendor_id,logo_url,date_format,time_format',
            'order',
            'orders',
        ])
            ->where('customer_id', $customer->id)
            ->where(function (Builder $query) {
                $query->where('status', 'succeeded')->orWhereNotNull('paid_at');
            })
            ->orderByDesc('id')
            ->get();

        $receipts = $payments->map(function (OrderPayment $payment) {
            $vendor = $payment->vendor;
            $orders = $this->paymentCoveredOrders($payment);

            return [
                'receipt_id' => $payment->id,
                'payment_intent_id' => $payment->stripe_payment_intent_id,
                'restaurant' => [
                    'restaurant_public_id' => $vendor?->vendor_public_id,
                    'restaurant_name' => $vendor?->restaurant_name,
                    'logo_url' => $this->media->url($vendor?->vendorSetting?->logo_url),
                ],
                'amount' => round((float) $payment->amount, 2),
                'currency' => $payment->currency,
                'payment_method' => $payment->stripe_payment_intent_id ? 'stripe' : 'cash',
                'status' => $payment->status,
                'paid_at' => $this->dateTimes->formatDateTime($payment->paid_at, $vendor),
                'orders_count' => $orders->count(),
                'orders' => $orders->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_public_id' => $order->order_public_id,
                    'amount' => round((float) ($order->pivot?->amount ?? $order->amount), 2),
                ])->values()->all(),
            ];
        })->values();

        return response()->json([
            'receipts' => $receipts,
            'receipts_count' => $receipts->count(),
        ]);
    }

    /**
     * GET /api/customer/receipts/{receiptId}
     */
    public function receiptShow(Request $request, int $receiptId): JsonResponse
    {
        $customer = $request->user();

        $payment = OrderPayment::with([
            'vendor.vendorSetting',
            'order.tableScanSession.restaurantTable:id,number,name',
            'order.paidBy:id,first_name,last_name',
            'orders.tableScanSession.restaurantTable:id,number,name',
            'orders.paidBy:id,first_name,last_name',
        ])
            ->where('customer_id', $customer->id)
            ->where('id', $receiptId)
            ->firstOrFail();

        if ($payment->status !== 'succeeded' && ! $payment->paid_at) {
            return response()->json(['message' => 'Receipt is only available for completed payments.'], 422);
        }

        $orders = $this->paymentCoveredOrders($payment);

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'No orders are associated with this receipt.'], 422);
        }

        $anchor = $orders->first(fn (Order $order) => (int) $order->id === (int) $payment->order_id)
            ?? $orders->first();
        $vendor = $payment->vendor ?? $anchor->vendor;
        $settings = $vendor?->vendorSetting;
        $vendorCountry = $vendor?->country ?? 'AT';
        $contentLocale = $vendor ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor) : 'en';
        $countryCode = TaxCalculationService::countryCode($vendorCountry);
        $receiptLocale = 'en-'.$countryCode;

        $allItems = collect();
        $orderBlocks = $orders->map(function (Order $order) use (&$allItems, $vendorCountry, $vendor, $contentLocale) {
            $items = $this->linkedCartItems($order);
            $allItems = $allItems->merge($items);

            return [
                'order_id' => $order->order_public_id,
                'paid_by' => $this->paidByPayload($order),
                'tip_amount' => round((float) ($order->tip_amount ?? 0), 2),
                'items' => $items
                    ->map(fn (CartItem $item) => $this->formatReceiptItem($item, $order, $vendorCountry, $vendor, $contentLocale))
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        $uniqueItems = $allItems->unique('id')->values();
        $taxGroups = TaxCalculationService::computeTaxGroups($uniqueItems, $vendorCountry, true);
        $totals = TaxCalculationService::computeTotals($taxGroups, 0);

        $serviceFee = round((float) $orders->sum(fn (Order $order) => (float) ($order->service_fee ?? 0)), 2);
        $tipTotal = round((float) $orders->sum(fn (Order $order) => (float) ($order->tip_amount ?? 0)), 2);
        $totals['service_fee'] = $serviceFee;
        $totals['total_tips'] = $tipTotal;
        $totals['grand_total'] = round($totals['grand_total'] + $serviceFee + $tipTotal, 2);
        $totals['amount_charged'] = round((float) $payment->amount, 2);

        $table = $anchor->tableScanSession?->restaurantTable;
        $tableName = $table ? ($table->name ?? 'Table '.$table->number) : null;

        $taxGroupsFormatted = array_map(fn (array $group) => array_merge($group, [
            'tax_category' => strtoupper($group['tax_category']),
            'label' => $this->locales->translatedTaxCategoryName($group['tax_category'], $vendorCountry, $contentLocale),
        ]), $taxGroups);

        return response()->json([
            'data' => [
                'restaurant' => [
                    'name' => $vendor?->restaurant_name,
                    'logo_url' => $this->media->url($settings?->logo_url),
                    'address' => $this->formatFullAddress($vendor),
                    'vat_id' => $vendor?->vat_number,
                    'phone' => $vendor?->phone,
                    'email' => $vendor?->email,
                    'company_register_number' => $vendor?->business_registration_number,
                ],
                'receipt' => [
                    'receipt_id' => $payment->id,
                    'invoice_number' => $this->resolveInvoiceNumber($anchor, $settings),
                    'date' => $this->dateTimes->formatDate($payment->paid_at ?? $anchor->created_at, $vendor),
                    'time' => $this->dateTimes->formatTime($payment->paid_at ?? $anchor->created_at, $vendor),
                    'table' => $tableName,
                    'order_ids' => $orders->pluck('order_public_id')->values()->all(),
                    'currency' => $payment->currency ?? $anchor->currency ?? $vendor?->currency ?? 'EUR',
                    'locale' => $receiptLocale,
                ],
                'orders' => $orderBlocks,
                'tax_groups' => $taxGroupsFormatted,
                'totals' => $totals,
                'payment' => [
                    'method' => $payment->stripe_payment_intent_id ? 'stripe' : 'cash',
                    'status' => 'CONFIRMED',
                    'transaction_id' => $payment->stripe_payment_intent_id,
                    'paid_at' => $this->dateTimes->formatDateTime($payment->paid_at, $vendor),
                ],
                'legal' => $this->legalBlock($countryCode, $vendor),
            ],
            'meta' => [
                'generated_at' => $this->dateTimes->formatDateTime(now(), $vendor),
                'template' => 'tavlo-receipt-template',
                'version' => '1.0',
            ],
        ]);
    }

    private function paymentCoveredOrders(OrderPayment $payment): Collection
    {
        if ($payment->orders->isNotEmpty()) {
            return collect($payment->orders->all())->values();
        }

        return $payment->order ? collect([$payment->order]) : collect();
    }

    private function formatReceiptItem(CartItem $item, Order $order, string $vendorCountry, ?Vendor $vendor, string $contentLocale): array
    {
        $menuItem = $item->menuItem;
        $unitPrice = $this->cartItemUnitPrice($item, $vendorCountry);
        $lineGross = round($unitPrice * $item->quantity, 2);
        $taxCategory = $menuItem?->tax_category ?? 'food';
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);
        $vatAmount = TaxCalculationService::vatFromGross($lineGross, $vatRate);

        $sharedBetween = 1 + count(is_array($item->shared_order_ids) ? $item->shared_order_ids : []);
        $myShare = round($lineGross / $sharedBetween, 2);

        return [
            'id' => $item->id,
            'name' => $menuItem && $vendor
                ? $this->customizations->menuItemName($menuItem, $vendor, $contentLocale)
                : $menuItem?->name,
            'quantity' => $item->quantity,
            'unit_price_gross' => $unitPrice,
            'line_gross' => $lineGross,
            'paid_addons' => $this->formatPaidAddons($item, $taxCategory, $vendorCountry, $vendor, $contentLocale),
            'free_addons' => $this->formatNamedSelections($item, 'free_addons', $vendor, $contentLocale),
            'removed_items' => $this->formatNamedSelections($item, 'removed_items', $vendor, $contentLocale),
            'selected_modifiers' => $this->formatSelectedModifiers($item, $taxCategory, $vendorCountry, $vendor, $contentLocale),
            'tax_category' => strtoupper($taxCategory),
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'is_mine' => (int) $item->order_id === (int) $order->id,
            'shared_between' => $sharedBetween,
            'my_share' => $myShare,
        ];
    }

    private function resolveInvoiceNumber(Order $order, $settings): string
    {
        if ($order->invoice_number) {
            return $order->invoice_number;
        }

        $prefix = $settings?->invoice_prefix ?? 'INV';

        $nextNumber = DB::table('vendor_settings')
            ->where('vendor_id', $order->vendor_id)
            ->lockForUpdate()
            ->value('next_invoice_number') ?? 1001;

        DB::table('vendor_settings')
            ->where('vendor_id', $order->vendor_id)
            ->increment('next_invoice_number');

        $invoiceNumber = $prefix.'-'.str_pad((string) $nextNumber, 7, '0', STR_PAD_LEFT);

        $order->update(['invoice_number' => $invoiceNumber]);

        return $invoiceNumber;
    }

    private function formatFullAddress($vendor): ?string
    {
        if (! $vendor) {
            return null;
        }

        $parts = array_filter([
            $vendor->address,
            $vendor->city,
            $vendor->country,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    private function legalBlock(string $countryCode, $vendor): array
    {
        $regNumber = $vendor?->business_registration_number;

        $legalNotes = match ($countryCode) {
            'AT' => [
                'invoice_note' => 'This invoice was issued in accordance with § 11 UStG (Austria).',
                'tax_note' => 'All prices include statutory VAT. The service date corresponds to the invoice date.',
            ],
            'DE' => [
                'invoice_note' => 'This invoice was issued in accordance with § 14 UStG (Germany).',
                'tax_note' => 'All prices include statutory VAT. The service date corresponds to the invoice date.',
            ],
            'GB' => [
                'invoice_note' => 'This invoice was issued in accordance with UK VAT regulations.',
                'tax_note' => 'All prices include VAT where applicable.',
            ],
            default => [
                'invoice_note' => 'This invoice was issued in accordance with applicable tax regulations.',
                'tax_note' => 'All prices include statutory VAT where applicable.',
            ],
        };

        return [
            'invoice_note' => $legalNotes['invoice_note'],
            'tax_note' => $legalNotes['tax_note'],
            'company_register_note' => $regNumber
                ? 'Registration number: '.$regNumber
                : null,
            'rksv_required_check' => $countryCode === 'AT',
        ];
    }

    private function scopeCustomerOrders(Builder $query, int $customerId): void
    {
        $query
            ->where('customer_id', $customerId)
            ->orWhereHas(
                'tableScanSession',
                fn (Builder $session) => $session->where('customer_id', $customerId)
            );
    }

    /**
     * Order detail and tracking are available to the customer who placed the
     * order and to the customer who is paying (or has paid) for it.
     */
    private function scopeCustomerAccessibleOrders(Builder $query, int $customerId): void
    {
        $query
            ->where(fn (Builder $owner) => $this->scopeCustomerOrders($owner, $customerId))
            ->orWhere('paid_by', $customerId)
            ->orWhereHas(
                'coveredPayments',
                fn (Builder $payment) => $payment->where('customer_id', $customerId)
            )
            ->orWhereHas(
                'payments',
                fn (Builder $payment) => $payment->where('customer_id', $customerId)
            );
    }

    /**
     * A receipt belongs to the payer, not necessarily to the customer who
     * placed the order. The final branch preserves old self-paid orders that
     * predate payment attribution while preventing an owner from reading a
     * receipt backed by another customer's completed payment.
     */
    private function scopeCustomerReceiptOrders(Builder $query, int $customerId): void
    {
        $query
            ->where('paid_by', $customerId)
            ->orWhereHas(
                'coveredPayments',
                fn (Builder $payment) => $this->scopeCompletedCustomerPayments($payment, $customerId)
            )
            ->orWhereHas(
                'payments',
                fn (Builder $payment) => $this->scopeCompletedCustomerPayments($payment, $customerId)
            )
            ->orWhere(function (Builder $legacy) use ($customerId) {
                $legacy
                    ->whereNull('paid_by')
                    ->where(fn (Builder $owner) => $this->scopeCustomerOrders($owner, $customerId))
                    ->whereDoesntHave(
                        'coveredPayments',
                        fn (Builder $payment) => $this->scopeCompletedPayments($payment)
                    )
                    ->whereDoesntHave(
                        'payments',
                        fn (Builder $payment) => $this->scopeCompletedPayments($payment)
                    );
            });
    }

    private function scopeCompletedCustomerPayments(Builder $query, int $customerId): void
    {
        $query
            ->where('customer_id', $customerId)
            ->where(fn (Builder $completed) => $this->scopeCompletedPayments($completed));
    }

    private function scopeCompletedPayments(Builder $query): void
    {
        $query->where(function (Builder $completed) {
            $completed
                ->where('status', 'succeeded')
                ->orWhereNotNull('paid_at');
        });
    }

    private function customerCanViewReceipt(Order $order, int $customerId): bool
    {
        if (! $order->payment_received) {
            return false;
        }

        return Order::query()
            ->whereKey($order->id)
            ->where(fn (Builder $query) => $this->scopeCustomerReceiptOrders($query, $customerId))
            ->exists();
    }

    private function paidByPayload(Order $order): ?array
    {
        if (! $order->relationLoaded('paidBy')) {
            $order->load('paidBy:id,first_name,last_name');
        }

        if (! $order->paidBy) {
            return null;
        }

        return [
            'id' => $order->paidBy->id,
            'name' => trim(($order->paidBy->first_name ?? '').' '.($order->paidBy->last_name ?? '')) ?: 'Guest',
        ];
    }

    private function formatHistoryOrder(Order $order, Request $request): array
    {
        $items = $this->linkedCartItems($order);
        $vendorCountry = $order->vendor?->country ?? 'AT';

        $taxGroups = TaxCalculationService::computeTaxGroups($items, $vendorCountry, true);
        $totals = TaxCalculationService::computeTotals($taxGroups, 0);
        $totals = $this->applyStoredServiceFee($totals, $taxGroups, $order);

        $tipAmount = round((float) ($order->tip_amount ?? 0), 2);
        $totals['total_tips'] = $tipAmount;
        $totals['grand_total'] = round($totals['grand_total'] + $tipAmount, 2);

        $ordersById = $this->sharingOrdersById($items);
        $sessionCustomerNames = $this->sessionCustomerNames($ordersById);
        $vendor = $order->vendor;
        $locale = $vendor ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor) : 'en';

        return [
            'order_id' => $order->order_number ?? (string) $order->id,
            'order_public_id' => $order->order_public_id,
            'created_at' => $this->dateTimes->formatDateTime($order->created_at, $vendor),
            'order_type' => $order->order_type,
            'payment_status' => $this->paymentStatus($order),
            'payment_method' => $order->payment_method,
            'paid_by' => $this->paidByPayload($order),
            'items_count' => (int) $items->sum('quantity'),
            'total_amount' => round((float) $order->amount, 2),
            'tip_amount' => $tipAmount,
            'items' => $items->map(fn (CartItem $item) => $this->formatHistoryItem($item, $order, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor, $locale))->values()->all(),
            'tax_groups' => $this->translateTaxGroups($taxGroups, $vendorCountry, $locale),
            'totals' => $totals,
        ];
    }

    private function formatHistoryItem(CartItem $item, Order $order, Collection $ordersById, Collection $sessionCustomerNames, string $vendorCountry = 'AT', $vendor = null, string $locale = 'en'): array
    {
        $menuItem = $item->menuItem;
        $itemTaxCategory = $menuItem?->tax_category ?? 'food';
        $unitPrice = $this->cartItemUnitPrice($item, $vendorCountry);
        $lineTotal = round($unitPrice * $item->quantity, 2);
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);
        $vatAmount = TaxCalculationService::vatFromGross($lineTotal, $vatRate);

        $orderIds = array_values(array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []));
        $sharedBetween = 1 + count($orderIds);
        $myShare = round($lineTotal / $sharedBetween, 2);

        $sharedWith = array_values(array_filter(array_map(function (int $oid) use ($ordersById, $sessionCustomerNames) {
            $o = $ordersById->get($oid);
            if (! $o) {
                return null;
            }

            return [
                'order_id' => $o->id,
                'customer_id' => $o->customer_id,
                'customer_name' => $sessionCustomerNames->get($o->table_scan_session_id, 'Guest'),
            ];
        }, $orderIds)));

        return [
            'cart_item_id' => $item->id,
            'menu_item_id' => $item->menu_item_id,
            'name' => $menuItem && $vendor
                ? $this->customizations->menuItemName($menuItem, $vendor, $locale)
                : $menuItem?->name,
            'image_url' => $this->media->url($menuItem?->image_url),
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'paid_addons' => $this->formatPaidAddons($item, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'free_addons' => $this->formatNamedSelections($item, 'free_addons', $vendor, $locale),
            'removed_items' => $this->formatNamedSelections($item, 'removed_items', $vendor, $locale),
            'selected_modifiers' => $this->formatSelectedModifiers($item, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'vat_rate' => $vatRate,
            'tax_category' => $itemTaxCategory,
            'vat_amount' => $vatAmount,
            'line_total' => $lineTotal,
            'is_mine' => (int) $item->order_id === (int) $order->id,
            'shared_between' => $sharedBetween,
            'shared_with' => $sharedWith,
            'my_share' => $myShare,
            'status' => $item->status(),
            'received_at' => $this->dateTimes->formatDateTime($item->received_at, $vendor),
            'preparing_start_at' => $this->dateTimes->formatDateTime($item->preparing_start_at, $vendor),
            'ready_at' => $this->dateTimes->formatDateTime($item->ready_at, $vendor),
            'served_at' => $this->dateTimes->formatDateTime($item->served_at, $vendor),
        ];
    }

    private function formatPaidAddons(CartItem $item, string $itemTaxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;

        if ($menuItem && $vendor) {
            return $this->customizations->formatPaidAddons($menuItem, $item->paid_addons ?? [], $vendor, $locale, $itemTaxCategory, $vendorCountry);
        }

        return collect($item->paid_addons ?? [])->map(function ($addon) use ($itemTaxCategory, $vendorCountry) {
            $addon = is_array($addon) ? $addon : [];
            $vatRate = TaxCalculationService::addonVatRate($addon, $itemTaxCategory, $vendorCountry);

            return [
                'id' => $addon['id'] ?? null,
                'name' => $addon['name'] ?? '',
                'price' => TaxCalculationService::gross((float) ($addon['price'] ?? 0), $vatRate),
                'vat_rate' => $vatRate,
            ];
        })->values()->all();
    }

    private function formatNamedSelections(CartItem $item, string $field, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;
        $selected = $field === 'free_addons'
            ? ($item->free_addons ?? [])
            : ($item->removed_items ?? []);

        if ($menuItem && $vendor) {
            $configured = $field === 'free_addons'
                ? ($menuItem->free_addons ?? [])
                : ($menuItem->removable_items ?? []);

            return $this->customizations->formatNamedSelections($configured, $selected, $vendor, $locale);
        }

        return collect($selected)->map(fn ($value) => is_array($value) ? (string) ($value['name'] ?? '') : (string) $value)
            ->filter()
            ->values()
            ->all();
    }

    private function formatSelectedModifiers(CartItem $item, string $itemTaxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        if ($vendor) {
            return $this->customizations->formatSelectedModifiers($item->selected_modifiers ?? [], $vendor, $locale, $itemTaxCategory, $vendorCountry);
        }

        return collect($item->selected_modifiers ?? [])->map(function ($group) use ($itemTaxCategory, $vendorCountry) {
            $groupTaxCategory = $group['tax_category'] ?? '';
            $vatRate = TaxCalculationService::modifierGroupVatRate($groupTaxCategory, $itemTaxCategory, $vendorCountry);

            $options = collect($group['options'] ?? [])->map(function ($option) use ($vatRate) {
                return [
                    'id' => $option['id'] ?? null,
                    'name' => $option['name'] ?? null,
                    'price_adjustment' => TaxCalculationService::gross((float) ($option['price_adjustment'] ?? 0), $vatRate),
                ];
            })->values()->all();

            return array_merge($group, [
                'vat_rate' => $vatRate,
                'options' => $options,
            ]);
        })->values()->all();
    }

    private function formatOrderDetail(Order $order, Request $request): array
    {
        $vendor = $order->vendor;
        $settings = $vendor?->vendorSetting;
        $items = $this->linkedCartItems($order);
        $vendorCountry = $vendor?->country ?? 'AT';

        $taxGroups = TaxCalculationService::computeTaxGroups($items, $vendorCountry, true);
        $totals = TaxCalculationService::computeTotals($taxGroups, 0);
        $totals = $this->applyStoredServiceFee($totals, $taxGroups, $order);

        $tipAmount = round((float) ($order->tip_amount ?? 0), 2);
        $totals['total_tips'] = $tipAmount;
        $totals['grand_total'] = round($totals['grand_total'] + $tipAmount, 2);

        $ordersById = $this->sharingOrdersById($items);
        $sessionCustomerNames = $this->sessionCustomerNames($ordersById);
        $locale = $vendor ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor) : 'en';

        return [
            'order_id' => $order->order_number ?? (string) $order->id,
            'order_public_id' => $order->order_public_id,
            'restaurant' => [
                'restaurant_public_id' => $vendor?->vendor_public_id,
                'restaurant_name' => $vendor?->restaurant_name,
                'logo_url' => $this->media->url($settings?->logo_url),
            ],
            'created_at' => $this->dateTimes->formatDateTime($order->created_at, $vendor),
            'status' => $order->status,
            'order_type' => $order->order_type,
            'payment_status' => $this->paymentStatus($order),
            'payment_method' => $order->payment_method,
            'paid_by' => $this->paidByPayload($order),
            'can_view_receipt' => $this->customerCanViewReceipt($order, (int) $request->user()->id),
            'tip_amount' => $tipAmount,
            'items' => $items->map(fn (CartItem $item) => $this->formatHistoryItem($item, $order, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor, $locale))->values()->all(),
            'tax_groups' => $this->translateTaxGroups($taxGroups, $vendorCountry, $locale),
            'totals' => array_merge($totals, [
                'currency' => $order->currency ?? $vendor?->currency,
            ]),
        ];
    }

    private function formatTrackingOrder(Order $order, Request $request): array
    {
        $vendor = $order->vendor;
        $vendorCountry = $order->vendor?->country ?? 'AT';
        $locale = $vendor ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor) : 'en';
        $ownedItems = $this->ownedCartItems($order);
        $sharedIntoItems = $this->sharedIntoCartItems($order);
        $ownedSharedItems = $ownedItems
            ->filter(fn (CartItem $item) => ! empty($item->shared_order_ids))
            ->values();
        $sharedItems = $ownedSharedItems->merge($sharedIntoItems)->values();
        $ordersById = $this->sharingOrdersById($sharedItems);
        $sessionCustomerNames = $this->sessionCustomerNames($ordersById);

        $allItems = $ownedItems->merge($sharedIntoItems);
        $taxGroups = TaxCalculationService::computeTaxGroups($allItems, $vendorCountry, true);
        $totals = TaxCalculationService::computeTotals($taxGroups, 0);
        $totals = $this->applyStoredServiceFee($totals, $taxGroups, $order);

        return [
            'id' => $order->id,
            'order_public_id' => $order->order_public_id,
            'order_number' => $order->order_number ? (string) $order->order_number : null,
            'status' => $order->status,
            'estimated_delivery_time' => $this->estimatedDeliveryTime($order),
            'total_amount' => round((float) $order->amount, 2),
            'currency' => $order->currency ?? $order->vendor?->currency,
            'order_type' => $order->order_type,
            'payment_method' => $order->payment_method,
            'paid_by' => $this->paidByPayload($order),
            'payment_pending' => (bool) $order->payment_pending,
            'payment_received' => (bool) $order->payment_received,
            'can_view_receipt' => $this->customerCanViewReceipt($order, (int) $request->user()->id),
            'items' => $ownedItems
                ->map(fn (CartItem $item) => $this->formatTrackingItem($item, $vendorCountry, $vendor, $locale))
                ->values()
                ->all(),
            'shared_items' => $sharedItems
                ->map(fn (CartItem $item) => $this->formatTrackingSharedItem($item, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor, $locale))
                ->values()
                ->all(),
            'tax_groups' => $this->translateTaxGroups($taxGroups, $vendorCountry, $locale),
            'totals' => $totals,
        ];
    }

    private function formatTrackingItem(CartItem $item, string $vendorCountry = 'AT', ?Vendor $vendor = null, string $locale = 'en'): array
    {
        $menuItem = $item->menuItem;
        $itemTaxCategory = $menuItem?->tax_category ?? 'food';
        $unitPrice = $this->cartItemUnitPrice($item, $vendorCountry);
        $lineTotal = round($unitPrice * $item->quantity, 2);
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);

        return [
            'cart_item_id' => $item->id,
            'menu_item_id' => $item->menu_item_id,
            'name' => $menuItem && $vendor
                ? $this->customizations->menuItemName($menuItem, $vendor, $locale)
                : $menuItem?->name,
            'image_url' => $this->media->url($menuItem?->image_url),
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'vat_rate' => $vatRate,
            'tax_category' => $itemTaxCategory,
            'status' => $item->status(),
            'notes' => $item->notes,
            'paid_addons' => $this->formatPaidAddons($item, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'free_addons' => $this->formatNamedSelections($item, 'free_addons', $vendor, $locale),
            'removed_items' => $this->formatNamedSelections($item, 'removed_items', $vendor, $locale),
            'selected_modifiers' => $this->formatSelectedModifiers($item, $itemTaxCategory, $vendorCountry, $vendor, $locale),
        ];
    }

    private function translateTaxGroups(array $taxGroups, string $vendorCountry, string $locale): array
    {
        return array_map(fn (array $group) => array_merge($group, [
            'label' => $this->locales->translatedTaxCategoryName($group['tax_category'], $vendorCountry, $locale),
        ]), $taxGroups);
    }

    private function cartItemUnitPrice(CartItem $item, string $vendorCountry = 'AT'): float
    {
        return TaxCalculationService::cartItemUnitPriceGross($item, $vendorCountry);
    }

    private function formatTrackingSharedItem(CartItem $item, Collection $ordersById, Collection $sessionCustomerNames, string $vendorCountry = 'AT', ?Vendor $vendor = null, string $locale = 'en'): array
    {
        $payload = $this->formatTrackingItem($item, $vendorCountry, $vendor, $locale);
        $orderIds = array_values(array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []));
        $sharedBetween = 1 + count($orderIds);

        $payload['shared_between'] = $sharedBetween;
        $payload['shared_with'] = array_values(array_filter(array_map(function (int $orderId) use ($ordersById, $sessionCustomerNames) {
            $order = $ordersById->get($orderId);

            if (! $order) {
                return null;
            }

            $customer = $order->tableScanSession?->customer ?? $order->customer;
            $customerName = $sessionCustomerNames->get($order->table_scan_session_id);

            return [
                'order_id' => $order->id,
                'customer_id' => $customer?->id,
                'customer_name' => $customerName,
            ];
        }, $orderIds)));
        $payload['my_share'] = round($payload['line_total'] / $sharedBetween, 2);

        return $payload;
    }

    private function ownedCartItems(Order $order): Collection
    {
        if (! $order->table_scan_session_id) {
            return collect();
        }

        return CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where(function (Builder $query) use ($order) {
                if ($order->status === 'draft') {
                    $query->where('table_scan_session_id', $order->table_scan_session_id)
                        ->whereNull('order_id');

                    return;
                }

                $query->where('order_id', $order->id);
            })
            ->orderBy('id')
            ->get();
    }

    private function sharedIntoCartItems(Order $order): Collection
    {
        if (! $order->table_scan_session_id) {
            return collect();
        }

        return CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->whereJsonContains('shared_order_ids', $order->id)
            ->where('table_scan_session_id', '!=', $order->table_scan_session_id)
            ->orderBy('id')
            ->get();
    }

    private function sharingOrdersById(Collection $items): Collection
    {
        $orderIds = $items
            ->flatMap(fn (CartItem $item) => is_array($item->shared_order_ids) ? $item->shared_order_ids : [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return Order::with([
            'customer:id,first_name,last_name',
            'tableScanSession.customer:id,first_name,last_name',
        ])
            ->whereIn('id', $orderIds->all())
            ->get()
            ->keyBy('id');
    }

    private function sessionCustomerNames(Collection $ordersById): Collection
    {
        return $ordersById->mapWithKeys(function (Order $order) {
            $customer = $order->tableScanSession?->customer ?? $order->customer;

            return [
                $order->table_scan_session_id => $customer
                    ? trim($customer->first_name.' '.$customer->last_name)
                    : 'Waiter',
            ];
        });
    }

    private function estimatedDeliveryTime(Order $order): ?string
    {
        if (! $order->created_at) {
            return null;
        }

        if (in_array($order->status, Order::TERMINAL_STATUSES, true)) {
            return null;
        }

        $prepMinutes = (int) ($order->vendor?->vendorSetting?->estimated_prep_time ?? 20);

        return $this->dateTimes->formatDateTime(
            $order->created_at->copy()->addMinutes($prepMinutes),
            $order->vendor,
        );
    }

    private function linkedCartItems(Order $order): Collection
    {
        $items = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where(function (Builder $query) use ($order) {
                if ($order->status === 'draft' && $order->table_scan_session_id) {
                    $query->where('table_scan_session_id', $order->table_scan_session_id);
                    $query->whereNull('order_id');
                } else {
                    $query->where('order_id', $order->id);
                }

                $query->orWhereJsonContains('shared_order_ids', $order->id);
            })
            ->orderBy('id')
            ->get();

        if ($items->isNotEmpty() || ! $order->table_scan_session_id || $order->status === 'draft') {
            return $items;
        }

        $orderedAt = $order->updated_at ?? $order->created_at;

        return CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where('table_scan_session_id', $order->table_scan_session_id)
            ->whereNull('order_id')
            ->when($orderedAt, fn (Builder $query) => $query->where('created_at', '<=', $orderedAt))
            ->orderBy('id')
            ->get();
    }

    private function paymentStatus(Order $order): string
    {
        if ($order->payment_received) {
            return 'paid';
        }

        if ($order->payment_pending) {
            return 'pending';
        }

        return 'unpaid';
    }

    private function applyStoredServiceFee(array $totals, array $taxGroups, Order $order): array
    {
        $serviceFee = round((float) ($order->service_fee ?? 0), 2);
        $totals['service_fee'] = $serviceFee;
        $totals['grand_total'] = round($totals['grand_total'] + $serviceFee, 2);

        return $totals;
    }
}
