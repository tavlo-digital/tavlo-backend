<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained()->cascadeOnDelete();

            // Vendor profile extras
            $table->string('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('cover_photo_url')->nullable();
            $table->boolean('is_live_and_discoverable')->default(false);

            // Business hours: JSON keyed by day
            // {monday:{open:"11:00",close:"22:00",closed:false}, ...}
            $table->json('business_hours')->nullable();

            // Payment settings
            $table->string('payment_collection_model')->default('on-site'); // on-site | online
            $table->boolean('accept_cash')->default(true);
            $table->boolean('accept_cash_takeaway')->default(true);
            $table->boolean('accept_card')->default(true);
            $table->boolean('accept_apple_pay')->default(false);
            $table->boolean('accept_google_pay')->default(false);
            $table->boolean('stripe_enabled')->default(false);
            $table->string('currency', 3)->default('EUR');

            // Tax & receipts
            $table->decimal('service_fee_rate', 5, 2)->default(0);
            $table->string('invoice_prefix', 20)->default('INV');
            $table->unsignedInteger('next_invoice_number')->default(1001);
            $table->boolean('auto_generate_receipts')->default(true);
            $table->string('company_type')->nullable();
            $table->boolean('first_invoice_issued')->default(false);

            // Table & QR settings
            $table->unsignedInteger('number_of_tables')->default(10);
            $table->string('table_prefix', 10)->default('T');
            $table->boolean('enable_shared_basket')->default(true);
            $table->unsignedInteger('max_guests_per_table')->default(10);
            $table->boolean('enable_reservations')->default(false);
            $table->unsignedInteger('total_tables_for_reservations')->default(0);
            $table->unsignedInteger('max_table_capacity')->default(6);

            // Ordering settings
            $table->boolean('auto_accept_orders')->default(false);
            $table->unsignedInteger('estimated_prep_time')->default(20); // minutes
            $table->unsignedInteger('max_orders_per_slot')->default(50);
            $table->boolean('allow_guest_ordering')->default(true);
            $table->boolean('require_phone_number')->default(false);
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->decimal('max_order_amount', 10, 2)->default(1000);

            // Inventory settings (general)
            $table->boolean('inventory_tracking_enabled')->default(false);
            $table->boolean('auto_stock_deduction')->default(false);
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('auto_mark_unavailable_critical')->default(true);

            // Notification settings
            $table->boolean('notify_email_new_order')->default(true);
            $table->boolean('notify_email_review')->default(true);
            $table->boolean('notify_sms_new_order')->default(false);
            $table->boolean('notify_push_new_order')->default(true);
            $table->string('notification_email')->nullable();

            // Review settings
            $table->boolean('enable_reviews')->default(true);
            $table->boolean('enable_menu_reviews')->default(false);
            $table->boolean('allow_anonymous_reviews')->default(false);

            // Language settings
            $table->string('default_language', 10)->default('en');
            $table->json('supported_languages')->nullable();

            // Loyalty settings
            $table->boolean('loyalty_enabled')->default(false);
            $table->unsignedInteger('points_per_euro')->default(10);
            $table->unsignedInteger('minimum_redemption_points')->default(100);
            $table->decimal('point_value', 8, 4)->default(0.01);
            $table->unsignedInteger('points_expiry_days')->default(365);

            // Appearance settings
            $table->string('menu_theme')->default('classic');
            $table->string('primary_color', 10)->default('#1a1a1a');
            $table->string('accent_color', 10)->default('#f59e0b');
            $table->string('menu_layout')->default('grid');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_settings');
    }
};
