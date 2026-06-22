<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('draft_at')->nullable()->after('waiter_confirmed_at');
            $table->timestamp('confirmed_at')->nullable()->after('draft_at');
        });

        // Backfill: draft_at = created_at for all orders
        DB::table('orders')->update(['draft_at' => DB::raw('created_at')]);

        // Backfill: confirmed_at for orders past draft status
        DB::table('orders')
            ->whereNotIn('status', ['draft'])
            ->whereNull('confirmed_at')
            ->update(['confirmed_at' => DB::raw('COALESCE(waiter_confirmed_at, created_at)')]);

        // Normalize: 'pending' orders become 'confirmed' (customer confirmed, awaiting waiter)
        DB::table('orders')
            ->where('status', 'pending')
            ->update(['status' => 'confirmed']);

        // Normalize: 'preparing'/'ready' orders become 'in_progress'
        DB::table('orders')
            ->whereIn('status', ['preparing', 'ready'])
            ->update([
                'status' => 'in_progress',
                'in_progress_at' => DB::raw('COALESCE(in_progress_at, updated_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['draft_at', 'confirmed_at']);
        });
    }
};
