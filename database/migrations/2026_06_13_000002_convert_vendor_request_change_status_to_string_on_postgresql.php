<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasColumn('vendor_request_changes', 'reviewed_at')) {
            Schema::table('vendor_request_changes', function (Blueprint $table) {
                $table->timestamp('reviewed_at')->nullable();
            });
        }

        $statusColumn = DB::selectOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'vendor_request_changes'
              AND column_name = 'status'
        ");

        if (($statusColumn->data_type ?? null) !== 'boolean') {
            return;
        }

        DB::statement('
            ALTER TABLE vendor_request_changes
            ALTER COLUMN status DROP DEFAULT
        ');

        DB::statement("
            ALTER TABLE vendor_request_changes
            ALTER COLUMN status TYPE VARCHAR(20)
            USING CASE
                WHEN status IS TRUE THEN 'approved'
                ELSE 'pending'
            END
        ");

        DB::statement("
            ALTER TABLE vendor_request_changes
            ALTER COLUMN status SET DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $statusColumn = DB::selectOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'vendor_request_changes'
              AND column_name = 'status'
        ");

        if (($statusColumn->data_type ?? null) !== 'character varying') {
            return;
        }

        DB::statement('
            ALTER TABLE vendor_request_changes
            ALTER COLUMN status DROP DEFAULT
        ');

        DB::statement("
            ALTER TABLE vendor_request_changes
            ALTER COLUMN status TYPE BOOLEAN
            USING status = 'approved'
        ");

        DB::statement('
            ALTER TABLE vendor_request_changes
            ALTER COLUMN status SET DEFAULT FALSE
        ');
    }
};
