<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('vendor_public_id')->nullable()->after('id');
            $table->string('restaurant_name')->nullable()->after('name');
            $table->string('legal_entity_name')->nullable()->after('restaurant_name');
            $table->string('business_registration_number')->nullable()->after('legal_entity_name');
            $table->string('vat_number')->nullable()->after('business_registration_number');
            $table->string('website')->nullable()->after('vat_number');
            $table->string('city')->nullable()->after('country');
            $table->string('address')->nullable()->after('city');
            $table->string('status')->default('pending')->after('password');
            $table->string('live_status')->default('not-live')->after('status');
            $table->string('risk_level')->default('none')->after('live_status');
            $table->timestamp('last_activity_at')->nullable()->after('risk_level');
        });

        // Backfill existing rows with unique VID-XXXX
        $vendors = DB::table('vendors')->whereNull('vendor_public_id')->get();
        foreach ($vendors as $vendor) {
            do {
                $publicId = 'VID-' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            } while (DB::table('vendors')->where('vendor_public_id', $publicId)->exists());

            DB::table('vendors')->where('id', $vendor->id)->update([
                'vendor_public_id' => $publicId,
                'restaurant_name' => $vendor->name,
            ]);
        }

        // Now make vendor_public_id non-null and unique
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('vendor_public_id')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'vendor_public_id',
                'restaurant_name',
                'legal_entity_name',
                'business_registration_number',
                'vat_number',
                'website',
                'city',
                'address',
                'status',
                'live_status',
                'risk_level',
                'last_activity_at',
            ]);
        });
    }
};
