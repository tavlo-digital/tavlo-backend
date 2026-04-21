<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_settings', function (Blueprint $table) {
            // About-page fields
            $table->unsignedInteger('years_of_experience')->nullable()->after('description');
            $table->unsignedInteger('signature_recipes_count')->nullable()->after('years_of_experience');
            $table->unsignedInteger('happy_customers_count')->nullable()->after('signature_recipes_count');
            // List of feature keys / labels (e.g. wifi, outdoor seating, parking, vegan options)
            $table->json('restaurant_features')->nullable()->after('happy_customers_count');

            // Public-visibility toggles for contact info
            $table->boolean('show_phone_public')->default(true)->after('restaurant_features');
            $table->boolean('show_email_public')->default(false)->after('show_phone_public');
            $table->boolean('show_website_public')->default(true)->after('show_email_public');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_settings', function (Blueprint $table) {
            $table->dropColumn([
                'years_of_experience',
                'signature_recipes_count',
                'happy_customers_count',
                'restaurant_features',
                'show_phone_public',
                'show_email_public',
                'show_website_public',
            ]);
        });
    }
};
