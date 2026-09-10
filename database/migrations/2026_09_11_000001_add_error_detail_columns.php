<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits a fiskaly failure in two: the plain-English reasons everyone reads,
 * and fiskaly's own wording, which only an admin sees.
 *
 * Before this, one column held both, so the restaurant was shown things like
 * "E_FAILED_SCHEMA_VALIDATION — body.legal_entity_id should match exactly one
 * schema in oneOf" and had no way to know which box to retype.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_devices', function (Blueprint $table) {
            $table->text('last_error_detail')->nullable()->after('last_error');
        });

        Schema::table('vendor_request_changes', function (Blueprint $table) {
            $table->text('admin_notes_detail')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_devices', function (Blueprint $table) {
            $table->dropColumn('last_error_detail');
        });

        Schema::table('vendor_request_changes', function (Blueprint $table) {
            $table->dropColumn('admin_notes_detail');
        });
    }
};
