<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('postal_code', 32)->nullable()->after('city');
        });

        Schema::table('vendor_request_changes', function (Blueprint $table) {
            $table->string('postal_code', 32)->nullable()->after('city');
        });

        Schema::table('fiscal_devices', function (Blueprint $table) {
            // A managed organization is shown as a Unit in fiskaly HUB. Its API
            // key secret lives in the already-encrypted credentials column.
            $table->uuid('fiskaly_organization_id')->nullable()->after('environment');
            $table->uuid('fiskaly_api_key_id')->nullable()->after('fiskaly_organization_id');
            $table->index('fiskaly_organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_devices', function (Blueprint $table) {
            $table->dropIndex(['fiskaly_organization_id']);
            $table->dropColumn(['fiskaly_organization_id', 'fiskaly_api_key_id']);
        });

        Schema::table('vendor_request_changes', function (Blueprint $table) {
            $table->dropColumn('postal_code');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('postal_code');
        });
    }
};
