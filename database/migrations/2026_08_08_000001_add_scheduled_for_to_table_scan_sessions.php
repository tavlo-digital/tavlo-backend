<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_scan_sessions', function (Blueprint $table) {
            $table->timestamp('scheduled_for')->nullable()->after('scanned_at');
            $table->index(
                ['vendor_id', 'type', 'status', 'pin'],
                'table_sessions_off_premise_group_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('table_scan_sessions', function (Blueprint $table) {
            $table->dropIndex('table_sessions_off_premise_group_index');
            $table->dropColumn('scheduled_for');
        });
    }
};
