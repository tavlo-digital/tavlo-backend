<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('placed_by')->nullable()->index()->after('table_scan_session_id');
            $table->foreignId('placed_by_team_member_id')
                ->nullable()
                ->after('placed_by')
                ->constrained('team_members')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('placed_by_team_member_id');
            $table->dropIndex(['placed_by']);
            $table->dropColumn('placed_by');
        });
    }
};
