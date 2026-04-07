<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_settings', function (Blueprint $table) {
            // Card sub-types
            $table->boolean('accept_visa')->default(true)->after('accept_google_pay');
            $table->boolean('accept_mastercard')->default(true)->after('accept_visa');
            $table->boolean('accept_amex')->default(false)->after('accept_mastercard');
            // Bank transfer (SEPA / eps)
            $table->boolean('accept_bank_transfer')->default(false)->after('accept_amex');
            // Stripe Connect Express
            $table->string('stripe_account_id')->nullable()->after('stripe_enabled');
            $table->boolean('stripe_onboarding_complete')->default(false)->after('stripe_account_id');
            // Loyalty redemption rate (€ per point)
            $table->decimal('redemption_rate', 8, 4)->default(0.05)->after('point_value');
            // Push notification: order ready
            $table->boolean('notify_push_order_ready')->default(true)->after('notify_push_new_order');
            // Language: local formatting
            $table->string('date_format', 20)->default('DD.MM.YYYY')->after('supported_languages');
            $table->string('time_format', 10)->default('24h')->after('date_format');
            // Appearance: background image
            $table->string('background_image_url')->nullable()->after('menu_layout');
            // Privacy / data settings
            $table->boolean('show_in_top_customers')->default(true)->after('background_image_url');
            $table->unsignedInteger('data_retention_days')->default(365)->after('show_in_top_customers');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_settings', function (Blueprint $table) {
            $table->dropColumn([
                'accept_visa',
                'accept_mastercard',
                'accept_amex',
                'accept_bank_transfer',
                'stripe_account_id',
                'stripe_onboarding_complete',
                'redemption_rate',
                'notify_push_order_ready',
                'date_format',
                'time_format',
                'background_image_url',
                'show_in_top_customers',
                'data_retention_days',
            ]);
        });
    }
};
