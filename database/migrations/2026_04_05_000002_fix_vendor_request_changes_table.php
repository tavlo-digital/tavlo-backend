<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * This migration fixes production databases that were created before the
 * vendor_request_changes.status column was changed from boolean to string.
 *
 * On fresh installs (tests / new deployments) the original migration already
 * creates these columns correctly, so this migration is a safe no-op for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // This migration only applies to MySQL production databases that were deployed
        // before the original migration was corrected.
        // SQLite (tests) and fresh installs already have the correct schema from the
        // original migration — this is a complete no-op for them.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('vendor_request_changes', 'vendor_notes')) {
            Schema::table('vendor_request_changes', function (Blueprint $table) {
                $table->text('vendor_notes')->nullable()->after('admin_notes');
            });
        }

        if (! Schema::hasColumn('vendor_request_changes', 'reviewed_at')) {
            Schema::table('vendor_request_changes', function (Blueprint $table) {
                $table->timestamp('reviewed_at')->nullable()->after('checked_by');
            });
        }

        DB::statement("
            ALTER TABLE vendor_request_changes
            MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'
        ");
        DB::statement("
            UPDATE vendor_request_changes
            SET status = CASE WHEN status = '1' THEN 'approved' ELSE 'pending' END
            WHERE status IN ('0', '1')
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE vendor_request_changes
            MODIFY COLUMN status TINYINT(1) NOT NULL DEFAULT 0
        ");
    }
};
