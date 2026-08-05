<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_scan_sessions', function (Blueprint $table) {
            $table->string('type', 20)->default('dine_in')->after('customer_id');
            $table->foreignId('restaurant_table_id')->nullable()->change();
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('table_scan_sessions', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropColumn('type');
            $table->foreignId('restaurant_table_id')->nullable(false)->change();
        });
    }
};
