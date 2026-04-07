<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vendor_request_changes', 'vendor_notes')) {
            return; // Already added by the original migration on fresh installs.
        }

        Schema::table('vendor_request_changes', function (Blueprint $table) {
            $table->text('vendor_notes')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_request_changes', function (Blueprint $table) {
            $table->dropColumn('vendor_notes');
        });
    }
};
