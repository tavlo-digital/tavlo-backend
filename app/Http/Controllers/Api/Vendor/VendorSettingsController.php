<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorSettingsController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/settings
     * Returns merged vendor + vendor_settings response.
     */
    public function show(string $vendorId): JsonResponse
    {
        $vendor  = $this->resolveVendor($vendorId);
        $settings = $vendor->vendorSetting ?? new VendorSetting(['business_hours' => VendorSetting::defaultBusinessHours()]);

        return response()->json($this->buildResponse($vendor, $settings));
    }

    /**
     * PUT /api/vendor/{vendorId}/settings
     */
    public function update(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        // ---- Validate all settings sections ----
        $data = $request->validate([
            // core vendor fields
            'restaurantName'         => ['sometimes', 'string', 'max:255'],
            'website'                => ['nullable', 'url', 'max:255'],
            'city'                   => ['sometimes', 'string', 'max:255'],
            'address'                => ['sometimes', 'string', 'max:500'],
            'phone'                  => ['sometimes', 'string', 'max:30'],
            // vendor_settings fields
            'description'            => ['nullable', 'string', 'max:1000'],
            'logoUrl'                => ['nullable', 'string', 'max:500'],
            'coverPhotoUrl'          => ['nullable', 'string', 'max:500'],
            'isLiveAndDiscoverable'  => ['sometimes', 'boolean'],
            'businessHours'          => ['nullable', 'array'],
            // payment
            'paymentCollectionModel' => ['sometimes', 'string', 'max:50'],
            'acceptCash'             => ['sometimes', 'boolean'],
            'acceptCashTakeaway'     => ['sometimes', 'boolean'],
            'acceptCard'             => ['sometimes', 'boolean'],
            'acceptApplePay'         => ['sometimes', 'boolean'],
            'acceptGooglePay'        => ['sometimes', 'boolean'],
            'stripeEnabled'          => ['sometimes', 'boolean'],
            'currency'               => ['sometimes', 'string', 'size:3'],
            // tax & receipts
            'serviceFeeRate'         => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'invoicePrefix'          => ['nullable', 'string', 'max:20'],
            'nextInvoiceNumber'      => ['sometimes', 'integer', 'min:1'],
            'autoGenerateReceipts'   => ['sometimes', 'boolean'],
            'companyType'            => ['nullable', 'string', 'max:50'],
            'firstInvoiceIssued'     => ['sometimes', 'boolean'],
            // table / QR
            'numberOfTables'         => ['sometimes', 'integer', 'min:0'],
            'tablePrefix'            => ['nullable', 'string', 'max:20'],
            'enableSharedBasket'     => ['sometimes', 'boolean'],
            'maxGuestsPerTable'      => ['sometimes', 'integer', 'min:1'],
            'enableReservations'     => ['sometimes', 'boolean'],
            'totalTablesForReservations' => ['sometimes', 'integer', 'min:0'],
            'maxTableCapacity'       => ['sometimes', 'integer', 'min:1'],
            // ordering
            'autoAcceptOrders'       => ['sometimes', 'boolean'],
            'estimatedPrepTime'      => ['sometimes', 'integer', 'min:1'],
            'maxOrdersPerSlot'       => ['sometimes', 'integer', 'min:1'],
            'allowGuestOrdering'     => ['sometimes', 'boolean'],
            'requirePhoneNumber'     => ['sometimes', 'boolean'],
            'minOrderAmount'         => ['sometimes', 'numeric', 'min:0'],
            'maxOrderAmount'         => ['nullable', 'numeric', 'min:0'],
            // inventory
            'inventoryTrackingEnabled'    => ['sometimes', 'boolean'],
            'autoStockDeduction'          => ['sometimes', 'boolean'],
            'allowNegativeStock'          => ['sometimes', 'boolean'],
            'autoMarkUnavailableCritical' => ['sometimes', 'boolean'],
            // notifications
            'notifyEmailNewOrder'         => ['sometimes', 'boolean'],
            'notifyEmailReview'           => ['sometimes', 'boolean'],
            'notifySmsNewOrder'           => ['sometimes', 'boolean'],
            'notifyPushNewOrder'          => ['sometimes', 'boolean'],
            'notificationEmail'           => ['nullable', 'email', 'max:255'],
            // reviews
            'enableReviews'               => ['sometimes', 'boolean'],
            'enableMenuReviews'           => ['sometimes', 'boolean'],
            'allowAnonymousReviews'       => ['sometimes', 'boolean'],
            // language
            'defaultLanguage'             => ['sometimes', 'string', 'max:10'],
            'supportedLanguages'          => ['nullable', 'array'],
            // loyalty
            'loyaltyEnabled'              => ['sometimes', 'boolean'],
            'pointsPerEuro'               => ['sometimes', 'integer', 'min:0'],
            'minimumRedemptionPoints'     => ['sometimes', 'integer', 'min:0'],
            'pointValue'                  => ['sometimes', 'numeric', 'min:0'],
            'pointsExpiryDays'            => ['nullable', 'integer', 'min:0'],
            // appearance
            'menuTheme'                   => ['sometimes', 'string', 'max:50'],
            'primaryColor'                => ['nullable', 'string', 'max:20'],
            'accentColor'                 => ['nullable', 'string', 'max:20'],
            'menuLayout'                  => ['sometimes', 'string', 'max:50'],
        ]);

        // ---- Update core vendor fields ----
        $vendorFields = [];
        if (isset($data['restaurantName'])) $vendorFields['restaurant_name'] = $data['restaurantName'];
        if (array_key_exists('website', $data)) $vendorFields['website'] = $data['website'];
        if (isset($data['city'])) $vendorFields['city'] = $data['city'];
        if (isset($data['address'])) $vendorFields['address'] = $data['address'];
        if (isset($data['phone'])) $vendorFields['phone'] = $data['phone'];
        if (! empty($vendorFields)) {
            $vendor->update($vendorFields);
        }

        // ---- Update vendor_settings ----
        $settingsMap = [
            'description'                 => 'description',
            'logoUrl'                     => 'logo_url',
            'coverPhotoUrl'               => 'cover_photo_url',
            'isLiveAndDiscoverable'       => 'is_live_and_discoverable',
            'businessHours'               => 'business_hours',
            'paymentCollectionModel'      => 'payment_collection_model',
            'acceptCash'                  => 'accept_cash',
            'acceptCashTakeaway'          => 'accept_cash_takeaway',
            'acceptCard'                  => 'accept_card',
            'acceptApplePay'              => 'accept_apple_pay',
            'acceptGooglePay'             => 'accept_google_pay',
            'stripeEnabled'               => 'stripe_enabled',
            'currency'                    => 'currency',
            'serviceFeeRate'              => 'service_fee_rate',
            'invoicePrefix'               => 'invoice_prefix',
            'nextInvoiceNumber'           => 'next_invoice_number',
            'autoGenerateReceipts'        => 'auto_generate_receipts',
            'companyType'                 => 'company_type',
            'firstInvoiceIssued'          => 'first_invoice_issued',
            'numberOfTables'              => 'number_of_tables',
            'tablePrefix'                 => 'table_prefix',
            'enableSharedBasket'          => 'enable_shared_basket',
            'maxGuestsPerTable'           => 'max_guests_per_table',
            'enableReservations'          => 'enable_reservations',
            'totalTablesForReservations'  => 'total_tables_for_reservations',
            'maxTableCapacity'            => 'max_table_capacity',
            'autoAcceptOrders'            => 'auto_accept_orders',
            'estimatedPrepTime'           => 'estimated_prep_time',
            'maxOrdersPerSlot'            => 'max_orders_per_slot',
            'allowGuestOrdering'          => 'allow_guest_ordering',
            'requirePhoneNumber'          => 'require_phone_number',
            'minOrderAmount'              => 'min_order_amount',
            'maxOrderAmount'              => 'max_order_amount',
            'inventoryTrackingEnabled'    => 'inventory_tracking_enabled',
            'autoStockDeduction'          => 'auto_stock_deduction',
            'allowNegativeStock'          => 'allow_negative_stock',
            'autoMarkUnavailableCritical' => 'auto_mark_unavailable_critical',
            'notifyEmailNewOrder'         => 'notify_email_new_order',
            'notifyEmailReview'           => 'notify_email_review',
            'notifySmsNewOrder'           => 'notify_sms_new_order',
            'notifyPushNewOrder'          => 'notify_push_new_order',
            'notificationEmail'           => 'notification_email',
            'enableReviews'               => 'enable_reviews',
            'enableMenuReviews'           => 'enable_menu_reviews',
            'allowAnonymousReviews'       => 'allow_anonymous_reviews',
            'defaultLanguage'             => 'default_language',
            'supportedLanguages'          => 'supported_languages',
            'loyaltyEnabled'              => 'loyalty_enabled',
            'pointsPerEuro'               => 'points_per_euro',
            'minimumRedemptionPoints'     => 'minimum_redemption_points',
            'pointValue'                  => 'point_value',
            'pointsExpiryDays'            => 'points_expiry_days',
            'menuTheme'                   => 'menu_theme',
            'primaryColor'                => 'primary_color',
            'accentColor'                 => 'accent_color',
            'menuLayout'                  => 'menu_layout',
        ];

        $settingsData = [];
        foreach ($settingsMap as $requestKey => $dbKey) {
            if (array_key_exists($requestKey, $data)) {
                $settingsData[$dbKey] = $data[$requestKey];
            }
        }

        if (! empty($settingsData)) {
            $vendor->vendorSetting()->updateOrCreate(
                ['vendor_id' => $vendor->id],
                $settingsData
            );
        }

        return $this->show($vendorId);
    }

    /**
     * GET /api/vendor/{vendorId}/subscription
     */
    public function subscription(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $subscription = $vendor->subscriptions()
            ->with('plan')
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json(null);
        }

        return response()->json([
            'id'                 => (string) $subscription->id,
            'planName'           => $subscription->plan?->name,
            'billingCycle'       => $subscription->billing_cycle,
            'status'             => $subscription->status,
            'currentPeriodStart' => $subscription->current_period_start?->toISOString(),
            'currentPeriodEnd'   => $subscription->current_period_end?->toISOString(),
            'autoRenew'          => $subscription->auto_renew,
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/legal-info
     */
    public function submitLegalInfo(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'legalEntityName'            => ['required', 'string', 'max:255'],
            'businessRegistrationNumber' => ['required', 'string', 'max:100'],
            'vatNumber'                  => ['required', 'string', 'max:50'],
        ]);

        $vendor->requestChanges()->create([
            'field'     => 'legal_info',
            'old_value' => json_encode([
                'legal_entity_name'              => $vendor->legal_entity_name,
                'business_registration_number'   => $vendor->business_registration_number,
                'vat_number'                     => $vendor->vat_number,
            ]),
            'new_value' => json_encode([
                'legal_entity_name'              => $data['legalEntityName'],
                'business_registration_number'   => $data['businessRegistrationNumber'],
                'vat_number'                     => $data['vatNumber'],
            ]),
            'status'    => 'pending',
        ]);

        return response()->json(['message' => 'Legal info submitted for approval']);
    }

    // ----------------------------------------------------------------

    private function buildResponse(Vendor $vendor, VendorSetting $settings): array
    {
        return [
            // ---- core vendor ----
            'id'                        => (string) $vendor->id,
            'vendorPublicId'            => $vendor->vendor_public_id,
            'name'                      => $vendor->name,
            'restaurantName'            => $vendor->restaurant_name,
            'legalEntityName'           => $vendor->legal_entity_name,
            'businessRegistrationNumber' => $vendor->business_registration_number,
            'vatNumber'                  => $vendor->vat_number,
            'website'                    => $vendor->website,
            'country'                    => $vendor->country,
            'city'                       => $vendor->city,
            'address'                    => $vendor->address,
            'phone'                      => $vendor->phone,
            'email'                      => $vendor->email,
            'status'                     => $vendor->status,
            'liveStatus'                 => $vendor->live_status,
            // ---- vendor_settings ----
            'description'                => $settings->description,
            'logo'                       => $settings->logo_url,
            'coverPhoto'                 => $settings->cover_photo_url,
            'isLiveAndDiscoverable'      => (bool) $settings->is_live_and_discoverable,
            'businessHours'              => $settings->business_hours ?? VendorSetting::defaultBusinessHours(),
            // payment
            'paymentCollectionModel'     => $settings->payment_collection_model ?? 'on-site',
            'acceptCash'                 => (bool) $settings->accept_cash,
            'acceptCashTakeaway'         => $settings->accept_cash_takeaway ?? true,
            'acceptCard'                 => (bool) $settings->accept_card,
            'acceptApplePay'             => (bool) $settings->accept_apple_pay,
            'acceptGooglePay'            => (bool) $settings->accept_google_pay,
            'stripeEnabled'              => (bool) $settings->stripe_enabled,
            'currency'                   => $settings->currency ?? 'EUR',
            // tax & receipts
            'serviceFeeRate'             => (float) ($settings->service_fee_rate ?? 0),
            'invoicePrefix'              => $settings->invoice_prefix ?? 'INV',
            'nextInvoiceNumber'          => (int) ($settings->next_invoice_number ?? 1),
            'autoGenerateReceipts'       => (bool) $settings->auto_generate_receipts,
            'companyType'                => $settings->company_type,
            'firstInvoiceIssued'         => (bool) $settings->first_invoice_issued,
            // table / QR
            'numberOfTables'             => (int) ($settings->number_of_tables ?? 20),
            'tablePrefix'                => $settings->table_prefix ?? 'T',
            'enableSharedBasket'         => (bool) $settings->enable_shared_basket,
            'maxGuestsPerTable'          => (int) ($settings->max_guests_per_table ?? 8),
            'enableReservations'         => $settings->enable_reservations ?? true,
            'totalTables'                => (int) ($settings->total_tables_for_reservations ?? 20),
            'maxTableCapacity'           => (int) ($settings->max_table_capacity ?? 6),
            // ordering
            'autoAcceptOrders'           => (bool) $settings->auto_accept_orders,
            'estimatedPrepTime'          => (int) ($settings->estimated_prep_time ?? 20),
            'maxOrdersPerSlot'           => (int) ($settings->max_orders_per_slot ?? 10),
            'allowGuestOrdering'         => $settings->allow_guest_ordering ?? true,
            'requirePhoneNumber'         => (bool) $settings->require_phone_number,
            'minOrderAmount'             => (float) ($settings->min_order_amount ?? 0),
            'maxOrderAmount'             => $settings->max_order_amount ? (float) $settings->max_order_amount : null,
            // inventory
            'inventoryTrackingEnabled'   => $settings->inventory_tracking_enabled ?? true,
            'autoStockDeduction'         => $settings->auto_stock_deduction ?? true,
            'allowNegativeStock'         => (bool) $settings->allow_negative_stock,
            'autoMarkUnavailableCritical' => $settings->auto_mark_unavailable_critical ?? true,
            // notifications
            'notifyEmailNewOrder'        => $settings->notify_email_new_order ?? true,
            'notifyEmailReview'          => (bool) $settings->notify_email_review,
            'notifySmsNewOrder'          => (bool) $settings->notify_sms_new_order,
            'notifyPushNewOrder'         => $settings->notify_push_new_order ?? true,
            'notificationEmail'          => $settings->notification_email ?? $vendor->email,
            // reviews
            'enableReviews'              => $settings->enable_reviews ?? true,
            'enableMenuReviews'          => $settings->enable_menu_reviews ?? true,
            'allowAnonymousReviews'      => (bool) $settings->allow_anonymous_reviews,
            // language
            'defaultLanguage'            => $settings->default_language ?? 'en',
            'supportedLanguages'         => $settings->supported_languages ?? ['en'],
            // loyalty
            'loyaltyEnabled'             => (bool) $settings->loyalty_enabled,
            'pointsPerEuro'              => (int) ($settings->points_per_euro ?? 10),
            'minimumRedemptionPoints'    => (int) ($settings->minimum_redemption_points ?? 100),
            'pointValue'                 => (float) ($settings->point_value ?? 0.01),
            'pointsExpiryDays'           => $settings->points_expiry_days ? (int) $settings->points_expiry_days : null,
            // appearance
            'menuTheme'                  => $settings->menu_theme ?? 'default',
            'primaryColor'               => $settings->primary_color ?? '#000000',
            'accentColor'                => $settings->accent_color ?? '#F97316',
            'menuLayout'                 => $settings->menu_layout ?? 'grid',
        ];
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->orWhere('id', $vendorId)
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
