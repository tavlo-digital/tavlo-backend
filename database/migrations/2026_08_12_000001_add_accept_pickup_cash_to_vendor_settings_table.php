<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vendor_settings', 'accept_pickup_cash')) {
            Schema::table('vendor_settings', function (Blueprint $table) {
                $table->boolean('accept_pickup_cash')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vendor_settings', 'accept_pickup_cash')) {
            Schema::table('vendor_settings', function (Blueprint $table) {
                $table->dropColumn('accept_pickup_cash');
            });
        }
    }
};
