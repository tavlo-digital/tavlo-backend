<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorSetting extends Model
{
    use HasFactory;

    protected $table = 'vendor_settings';

    protected $fillable = [
        'vendor_id',
        'description',
        'logo_url',
        'cover_photo_url',
        'is_live_and_discoverable',
        'business_hours',
        'accept_on_site',
        'accept_pickup_cash',
        'stripe_enabled',
        'service_fee_rate',
        'invoice_prefix',
        'next_invoice_number',
        'auto_generate_receipts',
        'company_type',
        'first_invoice_issued',
        'number_of_tables',
        'table_prefix',
        'enable_shared_basket',
        'max_guests_per_table',
        'enable_reservations',
        'total_tables_for_reservations',
        'max_table_capacity',
        'auto_accept_orders',
        'estimated_prep_time',
        'max_orders_per_slot',
        'allow_guest_ordering',
        'require_phone_number',
        'min_order_amount',
        'max_order_amount',
        'inventory_tracking_enabled',
        'auto_stock_deduction',
        'allow_negative_stock',
        'auto_mark_unavailable_critical',
        'notify_email_new_order',
        'notify_email_review',
        'notify_sms_new_order',
        'notify_push_new_order',
        'notification_email',
        'enable_reviews',
        'enable_menu_reviews',
        'allow_anonymous_reviews',
        'dashboard_language',
        'supported_languages',
        'loyalty_enabled',
        'points_per_euro',
        'minimum_redemption_points',
        'point_value',
        'points_expiry_days',
        'menu_theme',
        'primary_color',
        'accent_color',
        'menu_layout',
        'stripe_account_id',
        'stripe_onboarding_complete',
        'redemption_rate',
        'notify_push_order_ready',
        'date_format',
        'time_format',
        'background_image_url',
        'show_in_top_customers',
        'data_retention_days',
        // about page
        'years_of_experience',
        'signature_recipes_count',
        'happy_customers_count',
        'restaurant_features',
        'show_phone_public',
        'show_email_public',
        'show_website_public',
    ];

    protected function casts(): array
    {
        return [
            'is_live_and_discoverable' => 'boolean',
            'business_hours' => 'array',
            'accept_on_site' => 'boolean',
            'accept_pickup_cash' => 'boolean',
            'stripe_enabled' => 'boolean',
            'stripe_onboarding_complete' => 'boolean',
            'auto_generate_receipts' => 'boolean',
            'first_invoice_issued' => 'boolean',
            'enable_shared_basket' => 'boolean',
            'enable_reservations' => 'boolean',
            'auto_accept_orders' => 'boolean',
            'allow_guest_ordering' => 'boolean',
            'require_phone_number' => 'boolean',
            'inventory_tracking_enabled' => 'boolean',
            'auto_stock_deduction' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'auto_mark_unavailable_critical' => 'boolean',
            'notify_email_new_order' => 'boolean',
            'notify_email_review' => 'boolean',
            'notify_sms_new_order' => 'boolean',
            'notify_push_new_order' => 'boolean',
            'notify_push_order_ready' => 'boolean',
            'enable_reviews' => 'boolean',
            'enable_menu_reviews' => 'boolean',
            'allow_anonymous_reviews' => 'boolean',
            'supported_languages' => 'array',
            'loyalty_enabled' => 'boolean',
            'show_in_top_customers' => 'boolean',
            'service_fee_rate' => 'float',
            'point_value' => 'float',
            'redemption_rate' => 'float',
            'min_order_amount' => 'float',
            'max_order_amount' => 'float',
            'restaurant_features' => 'array',
            'show_phone_public' => 'boolean',
            'show_email_public' => 'boolean',
            'show_website_public' => 'boolean',
        ];
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function getLogoUrlAttribute(?string $value): ?string
    {
        return app(\App\Services\MediaService::class)->url($value);
    }

    public function getCoverPhotoUrlAttribute(?string $value): ?string
    {
        return app(\App\Services\MediaService::class)->url($value);
    }

    /**
     * Returns the default business hours array.
     */
    public static function defaultBusinessHours(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = [];
        foreach ($days as $day) {
            $hours[$day] = ['open' => '11:00', 'close' => '22:00', 'closed' => false];
        }

        return $hours;
    }

    private function normalizeMediaUrl(?string $value): ?string
    {
        return app(\App\Services\MediaService::class)->url($value);
    }
}
