<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_loyalty_points', function (Blueprint $table) {
            $table->string('source', 20)->default('system')->after('total_redeemed');
        });
    }

    public function down(): void
    {
        Schema::table('customer_loyalty_points', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
