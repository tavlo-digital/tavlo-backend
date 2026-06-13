<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_request_changes', function (Blueprint $table) {
            $table->string('company_type')->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_request_changes', function (Blueprint $table) {
            $table->dropColumn('company_type');
        });
    }
};
