<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vendor_settings', 'currency')) {
            Schema::table('vendor_settings', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('vendor_settings', 'currency')) {
            Schema::table('vendor_settings', function (Blueprint $table) {
                $table->string('currency', 3)->default('EUR');
            });
        }
    }
};
