<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('in_progress_at')->nullable()->after('waiter_confirmed_at');
            $table->timestamp('picked_up_at')->nullable()->after('served_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['in_progress_at', 'picked_up_at']);
        });
    }
};
