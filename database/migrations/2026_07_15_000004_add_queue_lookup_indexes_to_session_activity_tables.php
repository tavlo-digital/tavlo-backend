<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_scan_sessions', function (Blueprint $table) {
            $table->index(
                ['customer_id', 'scanned_at'],
                'table_sessions_customer_scanned_index',
            );
        });

        Schema::table('customer_session_activities', function (Blueprint $table) {
            $table->index('created_at', 'session_activities_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_session_activities', function (Blueprint $table) {
            $table->dropIndex('session_activities_created_index');
        });

        Schema::table('table_scan_sessions', function (Blueprint $table) {
            $table->dropIndex('table_sessions_customer_scanned_index');
        });
    }
};
