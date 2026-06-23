<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('table_scan_session_id')
                ->nullable()
                ->after('order_id')
                ->constrained('table_scan_sessions')
                ->nullOnDelete();
        });

        Schema::table('review_items', function (Blueprint $table) {
            $table->json('images')->nullable()->after('text');
        });

        $claimedSessionIds = [];

        DB::table('reviews')
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->eachById(function (object $review) use (&$claimedSessionIds) {
                $sessionId = DB::table('orders')
                    ->where('id', $review->order_id)
                    ->value('table_scan_session_id');

                if ($sessionId === null || isset($claimedSessionIds[$sessionId])) {
                    return;
                }

                DB::table('reviews')
                    ->where('id', $review->id)
                    ->update(['table_scan_session_id' => $sessionId]);

                $claimedSessionIds[$sessionId] = true;
            });

        Schema::table('reviews', function (Blueprint $table) {
            $table->unique('table_scan_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['table_scan_session_id']);
            $table->dropForeign(['table_scan_session_id']);
            $table->dropColumn('table_scan_session_id');
        });

        Schema::table('review_items', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
