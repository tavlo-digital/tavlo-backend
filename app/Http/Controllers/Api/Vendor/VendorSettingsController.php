<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorRequestChange;
use App\Models\VendorSetting;
use App\Services\LocaleService;
use App\Services\MediaService;
use App\Services\VendorMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorSettingsController extends Controller
{
    public function __construct(
        private readonly VendorMediaService $mediaService,
        private readonly MediaService $media,
        private readonly LocaleService $locales,
    ) {}

    /**
     * GET /api/vendor/{vendorId}/settings
     * Returns merged vendor + vendor_settings response.
     */
    public function show(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
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

        // Decode JSON-string array fields before validation (some clients double-encode).
        foreach (['businessHours', 'supportedLanguages'] as $jsonField) {
            $val = $request->input($jsonField);
            if (is_string($val) && $val !== '') {
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge([$jsonField => $decoded]);
                }
            }
        }

        // ---- Validate all settings sections ----
        // All scalar fields are `sometimes` + `nullable` so the frontend can send a partial
        // payload (or null/empty values from ConvertEmptyStringsToNull) without 422 errors
        // on unrelated fields.
        $data = $request->validate([
            // core vendor fields
            'restaurantName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            // vendor_settings fields
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'logoUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
            'coverPhotoUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
            'isLiveAndDiscoverable' => ['sometimes', 'nullable', 'boolean'],
            'businessHours' => ['sometimes', 'nullable', 'array'],
            // payment
            'acceptOnSite' => ['sometimes', 'nullable', 'boolean'],
            'stripeEnabled' => ['sometimes', 'nullable', 'boolean'],
            // tax & receipts
            'serviceFeeRate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'invoicePrefix' => ['sometimes', 'nullable', 'string', 'max:20'],
            'nextInvoiceNumber' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'autoGenerateReceipts' => ['sometimes', 'nullable', 'boolean'],
            'companyType' => ['sometimes', 'nullable', 'string', 'max:50'],
            'firstInvoiceIssued' => ['sometimes', 'nullable', 'boolean'],
            // table / QR
            'numberOfTables' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tablePrefix' => ['sometimes', 'nullable', 'string', 'max:20'],
            'enableSharedBasket' => ['sometimes', 'nullable', 'boolean'],
            'maxGuestsPerTable' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'enableReservations' => ['sometimes', 'nullable', 'boolean'],
            'totalTablesForReservations' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'maxTableCapacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            // ordering
            'autoAcceptOrders' => ['sometimes', 'nullable', 'boolean'],
            'estimatedPrepTime' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'maxOrdersPerSlot' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'allowGuestOrdering' => ['sometimes', 'nullable', 'boolean'],
            'requirePhoneNumber' => ['sometimes', 'nullable', 'boolean'],
            'minOrderAmount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'maxOrderAmount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            // inventory
            'inventoryTrackingEnabled' => ['sometimes', 'nullable', 'boolean'],
            'autoStockDeduction' => ['sometimes', 'nullable', 'boolean'],
            'allowNegativeStock' => ['sometimes', 'nullable', 'boolean'],
            'autoMarkUnavailableCritical' => ['sometimes', 'nullable', 'boolean'],
            // notifications
            'notifyEmailNewOrder' => ['sometimes', 'nullable', 'boolean'],
            'notifyEmailReview' => ['sometimes', 'nullable', 'boolean'],
            'notifySmsNewOrder' => ['sometimes', 'nullable', 'boolean'],
            'notifyPushNewOrder' => ['sometimes', 'nullable', 'boolean'],
            'notifyPushOrderReady' => ['sometimes', 'nullable', 'boolean'],
            'notificationEmail' => ['sometimes', 'nullable', 'email', 'max:255'],
            // reviews
            'enableReviews' => ['sometimes', 'nullable', 'boolean'],
            'enableMenuReviews' => ['sometimes', 'nullable', 'boolean'],
            'allowAnonymousReviews' => ['sometimes', 'nullable', 'boolean'],
            // language
            'dashboardLanguage' => ['sometimes', 'nullable', 'string', 'max:10'],
            'supportedLanguages' => ['sometimes', 'nullable', 'array'],
            'supportedLanguages.*' => ['string', 'max:10'],
            // loyalty
            'loyaltyEnabled' => ['sometimes', 'nullable', 'boolean'],
            'pointsPerEuro' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minimumRedemptionPoints' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'pointValue' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'redemptionRate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'pointsExpiryDays' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'showInTopCustomers' => ['sometimes', 'nullable', 'boolean'],
            // appearance
            'menuTheme' => ['sometimes', 'nullable', 'string', 'max:50'],
            'primaryColor' => ['sometimes', 'nullable', 'string', 'max:20'],
            'accentColor' => ['sometimes', 'nullable', 'string', 'max:20'],
            'menuLayout' => ['sometimes', 'nullable', 'string', 'max:50'],
            'backgroundImageUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
            // date / time formatting
            'dateFormat' => ['sometimes', 'nullable', Rule::in(['DD.MM.YYYY', 'MM/DD/YYYY', 'YYYY-MM-DD'])],
            'timeFormat' => ['sometimes', 'nullable', Rule::in(['24h', '12h'])],
            // data retention
            'dataRetentionDays' => ['sometimes', 'nullable', 'integer', 'min:0'],
            // about page
            'yearsOfExperience' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'signatureRecipesCount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'happyCustomersCount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'restaurantFeatures' => ['sometimes', 'nullable', 'array'],
            'restaurantFeatures.*.title' => ['required', 'string', 'max:100'],
            'restaurantFeatures.*.description' => ['nullable', 'string', 'max:500'],
            'restaurantFeatures.*.translations' => ['nullable', 'array'],
            'restaurantFeatures.*.translations.*' => ['array'],
            'restaurantFeatures.*.translations.*.title' => ['nullable', 'string', 'max:100'],
            'restaurantFeatures.*.translations.*.description' => ['nullable', 'string', 'max:500'],
            'showPhonePublic' => ['sometimes', 'nullable', 'boolean'],
            'showEmailPublic' => ['sometimes', 'nullable', 'boolean'],
            'showWebsitePublic' => ['sometimes', 'nullable', 'boolean'],
        ]);

        // ---- Update core vendor fields ----
        $vendorFields = [];
        if (isset($data['restaurantName'])) {
            $vendorFields['restaurant_name'] = $data['restaurantName'];
        }
        if (array_key_exists('website', $data)) {
            $vendorFields['website'] = $data['website'];
        }
        if (isset($data['city'])) {
            $vendorFields['city'] = $data['city'];
        }
        if (isset($data['address'])) {
            $vendorFields['address'] = $data['address'];
        }
        if (array_key_exists('latitude', $data)) {
            $vendorFields['latitude'] = $data['latitude'];
        }
        if (array_key_exists('longitude', $data)) {
            $vendorFields['longitude'] = $data['longitude'];
        }
        if (isset($data['phone'])) {
            $vendorFields['phone'] = $data['phone'];
        }
        if (! empty($vendorFields)) {
            $vendor->update($vendorFields);
        }

        // ---- Update vendor_settings ----
        $settingsMap = [
            'description' => 'description',
            'logoUrl' => 'logo_url',
            'coverPhotoUrl' => 'cover_photo_url',
            'isLiveAndDiscoverable' => 'is_live_and_discoverable',
            'businessHours' => 'business_hours',
            'acceptOnSite' => 'accept_on_site',
            'stripeEnabled' => 'stripe_enabled',
            'serviceFeeRate' => 'service_fee_rate',
            'invoicePrefix' => 'invoice_prefix',
            'nextInvoiceNumber' => 'next_invoice_number',
            'autoGenerateReceipts' => 'auto_generate_receipts',
            'companyType' => 'company_type',
            'firstInvoiceIssued' => 'first_invoice_issued',
            'numberOfTables' => 'number_of_tables',
            'tablePrefix' => 'table_prefix',
            'enableSharedBasket' => 'enable_shared_basket',
            'maxGuestsPerTable' => 'max_guests_per_table',
            'enableReservations' => 'enable_reservations',
            'totalTablesForReservations' => 'total_tables_for_reservations',
            'maxTableCapacity' => 'max_table_capacity',
            'autoAcceptOrders' => 'auto_accept_orders',
            'estimatedPrepTime' => 'estimated_prep_time',
            'maxOrdersPerSlot' => 'max_orders_per_slot',
            'allowGuestOrdering' => 'allow_guest_ordering',
            'requirePhoneNumber' => 'require_phone_number',
            'minOrderAmount' => 'min_order_amount',
            'maxOrderAmount' => 'max_order_amount',
            'inventoryTrackingEnabled' => 'inventory_tracking_enabled',
            'autoStockDeduction' => 'auto_stock_deduction',
            'allowNegativeStock' => 'allow_negative_stock',
            'autoMarkUnavailableCritical' => 'auto_mark_unavailable_critical',
            'notifyEmailNewOrder' => 'notify_email_new_order',
            'notifyEmailReview' => 'notify_email_review',
            'notifySmsNewOrder' => 'notify_sms_new_order',
            'notifyPushNewOrder' => 'notify_push_new_order',
            'notifyPushOrderReady' => 'notify_push_order_ready',
            'notificationEmail' => 'notification_email',
            'enableReviews' => 'enable_reviews',
            'enableMenuReviews' => 'enable_menu_reviews',
            'allowAnonymousReviews' => 'allow_anonymous_reviews',
            'dashboardLanguage' => 'dashboard_language',
            'supportedLanguages' => 'supported_languages',
            'loyaltyEnabled' => 'loyalty_enabled',
            'pointsPerEuro' => 'points_per_euro',
            'minimumRedemptionPoints' => 'minimum_redemption_points',
            'pointValue' => 'point_value',
            'redemptionRate' => 'redemption_rate',
            'pointsExpiryDays' => 'points_expiry_days',
            'showInTopCustomers' => 'show_in_top_customers',
            'menuTheme' => 'menu_theme',
            'primaryColor' => 'primary_color',
            'accentColor' => 'accent_color',
            'menuLayout' => 'menu_layout',
            'backgroundImageUrl' => 'background_image_url',
            'dateFormat' => 'date_format',
            'timeFormat' => 'time_format',
            'dataRetentionDays' => 'data_retention_days',
            // about page
            'yearsOfExperience' => 'years_of_experience',
            'signatureRecipesCount' => 'signature_recipes_count',
            'happyCustomersCount' => 'happy_customers_count',
            'restaurantFeatures' => 'restaurant_features',
            'showPhonePublic' => 'show_phone_public',
            'showEmailPublic' => 'show_email_public',
            'showWebsitePublic' => 'show_website_public',
        ];

        $settingsData = [];
        foreach ($settingsMap as $requestKey => $dbKey) {
            if (array_key_exists($requestKey, $data)) {
                $settingsData[$dbKey] = $data[$requestKey];
            }
        }

        if (array_key_exists('dashboard_language', $settingsData)) {
            $normalized = $this->locales->normalize($settingsData['dashboard_language']);
            if ($normalized === null) {
                return response()->json([
                    'message' => 'The selected language is not supported.',
                    'errors' => [
                        'dashboard_language' => ['The selected language is not supported.'],
                    ],
                ], 422);
            }
            $settingsData['dashboard_language'] = $normalized;
        }

        if (array_key_exists('supported_languages', $settingsData)) {
            $settingsData['supported_languages'] = collect(
                $this->locales->normalizeList($settingsData['supported_languages'])
            )
                ->prepend('en')
                ->unique()
                ->values()
                ->all();
        }

        // Normalise restaurant_features to a list of { title, description, translations? } objects.
        if (array_key_exists('restaurant_features', $settingsData)) {
            $features = $settingsData['restaurant_features'];
            if (! is_array($features)) {
                $settingsData['restaurant_features'] = [];
            } else {
                $settingsData['restaurant_features'] = array_values(array_filter(array_map(function ($item) {
                    if (is_string($item)) {
                        $title = trim($item);

                        return $title === '' ? null : ['title' => $title, 'description' => null];
                    }
                    if (is_array($item)) {
                        $title = isset($item['title']) ? trim((string) $item['title']) : '';
                        if ($title === '') {
                            return null;
                        }
                        $description = isset($item['description']) && $item['description'] !== ''
                            ? (string) $item['description']
                            : null;

                        $entry = ['title' => $title, 'description' => $description];

                        if (isset($item['translations']) && is_array($item['translations'])) {
                            $translations = [];
                            foreach ($item['translations'] as $lang => $values) {
                                if (! is_array($values)) {
                                    continue;
                                }
                                $t = array_filter([
                                    'title' => isset($values['title']) ? trim((string) $values['title']) : null,
                                    'description' => isset($values['description']) && $values['description'] !== ''
                                        ? (string) $values['description']
                                        : null,
                                ], fn ($v) => $v !== null && $v !== '');
                                if ($t !== []) {
                                    $translations[$lang] = $t;
                                }
                            }
                            if ($translations !== []) {
                                $entry['translations'] = $translations;
                            }
                        }

                        return $entry;
                    }

                    return null;
                }, $features)));
            }
        }

        $currentlyLive = (bool) ($vendor->vendorSetting?->is_live_and_discoverable);
        $requestingLive = ($data['isLiveAndDiscoverable'] ?? false) === true;

        if ($requestingLive && ! $currentlyLive) {
            $hasApprovedLegal = $vendor->requestChanges()
                ->where('status', 'approved')
                ->exists();

            if (! $hasApprovedLegal) {
                return response()->json([
                    'message' => 'Legal information must be submitted and approved before your restaurant can go live.',
                ], 422);
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
            'id' => (string) $subscription->id,
            'planName' => $subscription->plan?->name,
            'billingCycle' => $subscription->billing_cycle,
            'status' => $subscription->status,
            'currentPeriodStart' => $subscription->current_period_start?->toISOString(),
            'currentPeriodEnd' => $subscription->current_period_end?->toISOString(),
            'autoRenew' => $subscription->auto_renew,
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/legal-info
     */
    public function submitLegalInfo(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $pendingChange = VendorRequestChange::where('vendor_id', $vendor->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        $presenceRules = $pendingChange
            ? ['sometimes', 'nullable']
            : ['required'];

        $data = $request->validate([
            'restaurantName' => [...$presenceRules, 'string', 'max:255'],
            'legalEntityName' => [...$presenceRules, 'string', 'max:255'],
            'businessRegistrationNumber' => [...$presenceRules, 'string', 'max:100'],
            'vatNumber' => [...$presenceRules, 'string', 'max:50'],
            'companyType' => [...$presenceRules, 'string', 'max:50'],
            'country' => [...$presenceRules, 'string', 'max:100'],
            'city' => [...$presenceRules, 'string', 'max:255'],
            'address' => [...$presenceRules, 'string', 'max:500'],
            'vendorNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $changeData = [
            'vendor_id' => $vendor->id,
            'restaurant_name' => $data['restaurantName']
                ?? $pendingChange?->restaurant_name
                ?? $vendor->restaurant_name,
            'legal_entity_name' => $data['legalEntityName']
                ?? $pendingChange?->legal_entity_name
                ?? $vendor->legal_entity_name,
            'business_registration_number' => $data['businessRegistrationNumber']
                ?? $pendingChange?->business_registration_number
                ?? $vendor->business_registration_number,
            'vat_number' => $data['vatNumber']
                ?? $pendingChange?->vat_number
                ?? $vendor->vat_number,
            'company_type' => $data['companyType']
                ?? $pendingChange?->company_type
                ?? $vendor->vendorSetting?->company_type,
            'country' => $data['country']
                ?? $pendingChange?->country
                ?? $vendor->country,
            'city' => $data['city']
                ?? $pendingChange?->city
                ?? $vendor->city,
            'address' => $data['address']
                ?? $pendingChange?->address
                ?? $vendor->address,
            'vendor_notes' => $data['vendorNotes']
                ?? $pendingChange?->vendor_notes,
            'status' => 'pending',
            'admin_notes' => null,
            'checked_by' => null,
            'reviewed_at' => null,
        ];

        if ($pendingChange) {
            $pendingChange->update($changeData);

            return response()->json([
                'message' => 'Pending legal info updated successfully.',
            ]);
        }

        VendorRequestChange::create($changeData);

        return response()->json([
            'message' => 'Legal info submitted for approval.',
        ], 201);
    }

    /**
     * GET /api/vendor/{vendorId}/legal-info/status
     */
    public function getLegalChangeStatus(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $latest = VendorRequestChange::where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->first();

        if (! $latest) {
            return response()->json(null);
        }

        return response()->json([
            'id' => $latest->id,
            'status' => $latest->status,
            'legalInfo' => [
                'restaurantName' => $latest->restaurant_name,
                'legalEntityName' => $latest->legal_entity_name,
                'businessRegistrationNumber' => $latest->business_registration_number,
                'vatNumber' => $latest->vat_number,
                'companyType' => $latest->company_type
                    ?? $vendor->vendorSetting?->company_type,
                'country' => $latest->country,
                'city' => $latest->city,
                'address' => $latest->address,
            ],
            'adminNotes' => $latest->admin_notes,
            'vendorNotes' => $latest->vendor_notes,
            'reviewedAt' => $latest->reviewed_at?->toISOString(),
            'createdAt' => $latest->created_at?->toISOString(),
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/settings/logo
     */
    public function uploadLogo(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $request->validate([
            'logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $this->mediaService->uploadLogo($vendor, $request->file('logo'));

        $vendor->vendorSetting()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            ['logo_url' => $path]
        );

        return response()->json(['logoUrl' => $this->media->url($path)]);
    }

    /**
     * POST /api/vendor/{vendorId}/settings/cover-photo
     */
    public function uploadCoverPhoto(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        // Cover photos must be 16:9 (recommended 1600×900, minimum 1200×675).
        $request->validate([
            'cover' => [
                'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                Rule::dimensions()->ratio(16 / 9)->minWidth(1200)->minHeight(675),
            ],
        ], [
            'cover.dimensions' => 'The cover image must have a 16:9 aspect ratio and be at least 1200×675 px (recommended 1600×900 px).',
        ]);

        $path = $this->mediaService->uploadCoverPhoto($vendor, $request->file('cover'));

        $vendor->vendorSetting()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            ['cover_photo_url' => $path]
        );

        return response()->json(['coverPhotoUrl' => $this->media->url($path)]);
    }

    /**
     * POST /api/vendor/{vendorId}/settings/delete-account
     */
    public function deleteAccount(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! \Hash::check($request->input('password'), $vendor->password)) {
            return response()->json(['message' => 'The password you entered is incorrect.'], 422);
        }

        $vendor->tokens()->delete();

        $vendor->update([
            'status' => 'deactivated',
            'live_status' => 'offline',
        ]);

        if ($vendor->vendorSetting) {
            $vendor->vendorSetting->update(['is_live_and_discoverable' => false]);
        }

        return response()->json(['message' => 'Your account has been deactivated successfully.']);
    }

    /**
     * GET /api/vendor/{vendorId}/settings/export
     * Returns a JSON blob of vendor data for download.
     */
    public function exportData(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $settings = $vendor->vendorSetting;

        $payload = [
            'exportedAt' => now()->toISOString(),
            'vendor' => $this->buildResponse($vendor, $settings ?? new VendorSetting),
        ];

        return response()->json($payload)
            ->header('Content-Disposition', "attachment; filename=\"vendor-{$vendorId}-data.json\"");
    }

    // ----------------------------------------------------------------

    private function buildResponse(Vendor $vendor, VendorSetting $settings): array
    {
        return [
            // ---- core vendor ----
            'id' => (string) $vendor->id,
            'vendorPublicId' => $vendor->vendor_public_id,
            'name' => $vendor->name,
            'restaurantName' => $vendor->restaurant_name,
            'legalEntityName' => $vendor->legal_entity_name,
            'businessRegistrationNumber' => $vendor->business_registration_number,
            'vatNumber' => $vendor->vat_number,
            'website' => $vendor->website,
            'country' => $vendor->country,
            'city' => $vendor->city,
            'address' => $vendor->address,
            'latitude' => $vendor->latitude !== null ? (float) $vendor->latitude : null,
            'longitude' => $vendor->longitude !== null ? (float) $vendor->longitude : null,
            'phone' => $vendor->phone,
            'email' => $vendor->email,
            'status' => $vendor->status,
            'liveStatus' => $vendor->live_status,
            // ---- vendor_settings ----
            'description' => $settings->description,
            'logo' => $this->media->url($settings->getRawOriginal('logo_url')),
            'coverPhoto' => $this->media->url($settings->getRawOriginal('cover_photo_url')),
            'backgroundImageUrl' => $settings->background_image_url,
            'isLiveAndDiscoverable' => (bool) $settings->is_live_and_discoverable,
            'businessHours' => $settings->business_hours ?? VendorSetting::defaultBusinessHours(),
            // payment
            'acceptOnSite' => $settings->accept_on_site ?? true,
            'stripeEnabled' => (bool) $settings->stripe_enabled,
            'stripeAccountId' => $settings->stripe_account_id,
            'stripeOnboardingComplete' => (bool) $settings->stripe_onboarding_complete,
            'currency' => $vendor->currency,
            // tax & receipts
            'serviceFeeRate' => (float) ($settings->service_fee_rate ?? 0),
            'invoicePrefix' => $settings->invoice_prefix ?? 'INV',
            'nextInvoiceNumber' => (int) ($settings->next_invoice_number ?? 1),
            'autoGenerateReceipts' => (bool) $settings->auto_generate_receipts,
            'companyType' => $settings->company_type,
            'firstInvoiceIssued' => (bool) $settings->first_invoice_issued,
            // table / QR
            'numberOfTables' => (int) ($settings->number_of_tables ?? 20),
            'tablePrefix' => $settings->table_prefix ?? 'T',
            'enableSharedBasket' => (bool) $settings->enable_shared_basket,
            'maxGuestsPerTable' => (int) ($settings->max_guests_per_table ?? 8),
            'enableReservations' => $settings->enable_reservations ?? true,
            'totalTables' => (int) ($settings->total_tables_for_reservations ?? 20),
            'maxTableCapacity' => (int) ($settings->max_table_capacity ?? 6),
            // ordering
            'autoAcceptOrders' => (bool) $settings->auto_accept_orders,
            'estimatedPrepTime' => (int) ($settings->estimated_prep_time ?? 20),
            'maxOrdersPerSlot' => (int) ($settings->max_orders_per_slot ?? 10),
            'allowGuestOrdering' => $settings->allow_guest_ordering ?? true,
            'requirePhoneNumber' => (bool) $settings->require_phone_number,
            'minOrderAmount' => (float) ($settings->min_order_amount ?? 0),
            'maxOrderAmount' => $settings->max_order_amount ? (float) $settings->max_order_amount : null,
            // inventory
            'inventoryTrackingEnabled' => $settings->inventory_tracking_enabled ?? true,
            'autoStockDeduction' => $settings->auto_stock_deduction ?? true,
            'allowNegativeStock' => (bool) $settings->allow_negative_stock,
            'autoMarkUnavailableCritical' => $settings->auto_mark_unavailable_critical ?? true,
            // notifications
            'notifyEmailNewOrder' => $settings->notify_email_new_order ?? true,
            'notifyEmailReview' => (bool) $settings->notify_email_review,
            'notifySmsNewOrder' => (bool) $settings->notify_sms_new_order,
            'notifyPushNewOrder' => $settings->notify_push_new_order ?? true,
            'notifyPushOrderReady' => (bool) $settings->notify_push_order_ready,
            'notificationEmail' => $settings->notification_email ?? $vendor->email,
            // reviews
            'enableReviews' => $settings->enable_reviews ?? true,
            'enableMenuReviews' => $settings->enable_menu_reviews ?? true,
            'allowAnonymousReviews' => (bool) $settings->allow_anonymous_reviews,
            // language
            'dashboardLanguage' => $this->locales->normalize($settings->dashboard_language) ?? 'en',
            'supportedLanguages' => collect($this->locales->normalizeList($settings->supported_languages ?? []))
                ->prepend('en')
                ->unique()
                ->values()
                ->all(),
            'availableLanguages' => $this->locales->languageOptions(),
            // loyalty
            'loyaltyEnabled' => (bool) $settings->loyalty_enabled,
            'pointsPerEuro' => (int) ($settings->points_per_euro ?? 10),
            'minimumRedemptionPoints' => (int) ($settings->minimum_redemption_points ?? 100),
            'pointValue' => (float) ($settings->point_value ?? 0.01),
            'redemptionRate' => (float) ($settings->redemption_rate ?? 0.01),
            'pointsExpiryDays' => $settings->points_expiry_days ? (int) $settings->points_expiry_days : null,
            'showInTopCustomers' => (bool) $settings->show_in_top_customers,
            // appearance
            'menuTheme' => $settings->menu_theme ?? 'default',
            'primaryColor' => $settings->primary_color ?? '#000000',
            'accentColor' => $settings->accent_color ?? '#F97316',
            'menuLayout' => $settings->menu_layout ?? 'grid',
            // date / time formatting
            'dateFormat' => $settings->date_format ?? 'DD.MM.YYYY',
            'timeFormat' => $settings->time_format ?? '24h',
            // data retention
            'dataRetentionDays' => $settings->data_retention_days ? (int) $settings->data_retention_days : null,
            // about page
            'yearsOfExperience' => $settings->years_of_experience !== null ? (int) $settings->years_of_experience : null,
            'signatureRecipesCount' => $settings->signature_recipes_count !== null ? (int) $settings->signature_recipes_count : null,
            'happyCustomersCount' => $settings->happy_customers_count !== null ? (int) $settings->happy_customers_count : null,
            'restaurantFeatures' => $settings->restaurant_features ?? [],
            'showPhonePublic' => $settings->show_phone_public ?? true,
            'showEmailPublic' => $settings->show_email_public ?? false,
            'showWebsitePublic' => $settings->show_website_public ?? true,
        ];
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->with('countryRecord')
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
