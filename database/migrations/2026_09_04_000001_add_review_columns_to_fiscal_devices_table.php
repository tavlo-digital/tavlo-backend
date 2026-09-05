<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_devices', function (Blueprint $table) {
            // When the vendor handed over their details, and when we last tried
            // to register them. Registration now happens on admin approval, so
            // these two can be far apart and the admin needs to see both.
            $table->timestamp('submitted_at')->nullable()->after('state');
            $table->timestamp('last_attempted_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_devices', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'last_attempted_at']);
        });
    }
};
